import asyncio
import html
import logging
import os
import re
import sys
from datetime import datetime, timedelta, timezone
from urllib.parse import urljoin, urlparse, urlunparse

import httpx
import pymysql
from selectolax.lexbor import LexborHTMLParser


LIST_URL = "https://www.coindesk.com/latest-crypto-news"
HEADERS = {
    "User-Agent": "Mozilla/5.0 (compatible; AlphaQuantNewsBot/1.0)",
    "Accept-Language": "en-US,en;q=0.9",
}
ARTICLE_RE = re.compile(r"^/[^/]+/\d{4}/\d{2}/\d{2}/[^/]+/?$")
CONCURRENCY = int(os.getenv("NEWS_CRAWLER_CONCURRENCY", "4"))
MAX_ARTICLES = int(os.getenv("NEWS_CRAWLER_MAX_ARTICLES", "30"))
ROOT_CATEGORY_ID = 1
ROOT_CATEGORY_NAME = "新闻资讯"


def clean(value):
    return " ".join(value.split()) if isinstance(value, str) else value


def normalize_url(url):
    parsed = urlparse(url)
    return urlunparse(
        (
            parsed.scheme.lower(),
            parsed.netloc.lower(),
            parsed.path.rstrip("/") or "/",
            "",
            "",
            "",
        )
    )


def is_article(url):
    parsed = urlparse(url)
    return (
        parsed.scheme in {"http", "https"}
        and (parsed.hostname or "").lower() in {"coindesk.com", "www.coindesk.com"}
        and bool(ARTICLE_RE.fullmatch(parsed.path))
    )


def meta(tree, name):
    node = tree.css_first(f'meta[name="{name}"]')
    return clean(node.attributes.get("content")) if node else None


def get_content(tree):
    start = tree.css_first("[data-article-body-start]")
    bodies = start.css(".document-body") if start else []
    if not bodies:
        bodies = tree.css(".document-body")

    body = bodies[-1] if bodies else tree.css_first("article")
    if not body:
        return None

    parts = []
    for node in body.css("p,h2,h3,blockquote,li"):
        text = clean(node.text())
        if text:
            parts.append(text)
    return "\n\n".join(parts) or None


def is_recent_article(url, days=3):
    parts = urlparse(url).path.strip("/").split("/")
    if len(parts) < 4:
        return False
    try:
        published_date = datetime(
            int(parts[1]), int(parts[2]), int(parts[3]), tzinfo=timezone.utc
        )
    except (TypeError, ValueError):
        return False
    now = datetime.now(tz=timezone.utc)
    return now - timedelta(days=days) <= published_date <= now + timedelta(days=1)


def get_list_items(html_text):
    tree = LexborHTMLParser(html_text)
    seen = set()
    items = []
    for anchor in tree.css("a[href]"):
        url = normalize_url(urljoin(LIST_URL, anchor.attributes.get("href", "")))
        if not is_article(url) or not is_recent_article(url) or url in seen:
            continue

        title = clean(anchor.text())
        if not title:
            continue

        seen.add(url)
        items.append(
            {
                "title": title,
                "category": urlparse(url).path.split("/")[1].title(),
                "url": url,
            }
        )
    return items


async def fetch_detail(client, semaphore, item):
    async with semaphore:
        response = await client.get(item["url"])
    response.raise_for_status()
    tree = LexborHTMLParser(response.text)
    return {
        "title": meta(tree, "content_title") or item["title"],
        "summary": meta(tree, "description"),
        "category": meta(tree, "parsely-section") or item["category"],
        "published_at": meta(tree, "parsely-pub-date"),
        "content": get_content(tree),
        "url": item["url"],
    }


def database_connection():
    return pymysql.connect(
        host=os.getenv("DB_HOST", "127.0.0.1"),
        port=int(os.getenv("DB_PORT", "3306")),
        user=os.getenv("DB_USERNAME", "root"),
        password=os.getenv("DB_PASSWORD", ""),
        database=os.getenv("DB_DATABASE", "lhqb"),
        charset="utf8mb4",
        autocommit=False,
        cursorclass=pymysql.cursors.DictCursor,
    )


def known_urls(connection, urls):
    if not urls:
        return set()
    placeholders = ",".join(["%s"] * len(urls))
    with connection.cursor() as cursor:
        cursor.execute(
            f"SELECT url FROM jl_news_crawl_record WHERE url IN ({placeholders})",
            urls,
        )
        return {row["url"] for row in cursor.fetchall()}


def parse_published_time(value):
    if not value:
        return int(datetime.now(tz=timezone.utc).timestamp())
    try:
        return int(datetime.fromisoformat(value.replace("Z", "+00:00")).timestamp())
    except ValueError:
        logging.warning("无法解析发布时间 %s，使用当前时间", value)
        return int(datetime.now(tz=timezone.utc).timestamp())


def ensure_root_category(cursor):
    cursor.execute("SELECT id FROM jl_portal_category WHERE id=%s", (ROOT_CATEGORY_ID,))
    row = cursor.fetchone()
    if row:
        return ROOT_CATEGORY_ID

    cursor.execute(
        """
        INSERT INTO jl_portal_category
            (id, parent_id, post_count, status, delete_time, list_order, name,
             description, path, seo_title, seo_keywords, seo_description,
             list_tpl, one_tpl, more)
        VALUES
            (%s, 0, 0, 1, 0, 10000, %s, '', %s, '', '', '', '', '', '')
        """,
        (ROOT_CATEGORY_ID, ROOT_CATEGORY_NAME, f"0-{ROOT_CATEGORY_ID}"),
    )
    return ROOT_CATEGORY_ID


def ensure_detail_category(cursor, category_name):
    category_name = (clean(category_name) or "CoinDesk")[:200]
    cursor.execute(
        """
        SELECT id FROM jl_portal_category
        WHERE parent_id=%s AND name=%s AND delete_time=0
        LIMIT 1
        """,
        (ROOT_CATEGORY_ID, category_name),
    )
    row = cursor.fetchone()
    if row:
        return row["id"]

    cursor.execute(
        """
        INSERT INTO jl_portal_category
            (parent_id, post_count, status, delete_time, list_order, name,
             description, path, seo_title, seo_keywords, seo_description,
             list_tpl, one_tpl, more)
        VALUES
            (%s, 0, 1, 0, 10000, %s, '', '', '', '', '', '', '', '')
        """,
        (ROOT_CATEGORY_ID, category_name),
    )
    category_id = cursor.lastrowid
    cursor.execute(
        "UPDATE jl_portal_category SET path=%s WHERE id=%s",
        (f"0-{ROOT_CATEGORY_ID}-{category_id}", category_id),
    )
    return category_id


def import_article(connection, article):
    title = clean(article.get("title"))
    content = article.get("content")
    url = normalize_url(article.get("url") or "")
    if not title or not content or not url:
        raise ValueError("文章缺少 title、content 或 url")

    summary = clean(article.get("summary")) or ""
    category = clean(article.get("category")) or "CoinDesk"
    published_time = parse_published_time(article.get("published_at"))
    now = int(datetime.now(tz=timezone.utc).timestamp())

    with connection.cursor() as cursor:
        cursor.execute("SELECT post_id FROM jl_news_crawl_record WHERE url=%s", (url,))
        if cursor.fetchone():
            connection.rollback()
            return False

        root_category_id = ensure_root_category(cursor)
        detail_category_id = ensure_detail_category(cursor, category)
        cursor.execute(
            """
            INSERT INTO jl_portal_post
                (parent_id, post_type, post_format, user_id, post_status,
                 comment_status, is_top, recommended, post_hits, post_favorites,
                 post_like, post_cai, comment_count, create_time, update_time,
                 published_time, delete_time, post_title, post_keywords,
                 post_excerpt, post_source, thumbnail, post_content,
                 post_content_filtered, more)
            VALUES
                (0, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, %s, %s, %s, 0,
                 %s, %s, %s, %s, '', %s, '', '')
            """,
            (
                published_time,
                now,
                published_time,
                title[:100],
                category[:150],
                summary[:500],
                url[:150],
                html.escape(content),
            ),
        )
        post_id = cursor.lastrowid

        category_ids = {root_category_id, detail_category_id}
        cursor.executemany(
            """
            INSERT INTO jl_portal_category_post
                (post_id, category_id, list_order, status)
            VALUES (%s, %s, 10000, 1)
            """,
            [(post_id, category_id) for category_id in category_ids],
        )
        cursor.execute(
            """
            INSERT INTO jl_news_crawl_record
                (url, post_id, create_time)
            VALUES (%s, %s, %s)
            """,
            (url, post_id, now),
        )
        cursor.execute(
            """
            UPDATE jl_portal_category category
            SET post_count=(
                SELECT COUNT(*) FROM jl_portal_category_post relation
                WHERE relation.category_id=category.id AND relation.status=1
            )
            WHERE category.id IN (%s, %s)
            """,
            (root_category_id, detail_category_id),
        )
    connection.commit()
    return True


def mark_checked_without_content(connection, article):
    url = normalize_url(article.get("url") or "")
    if not url:
        return
    now = int(datetime.now(tz=timezone.utc).timestamp())
    with connection.cursor() as cursor:
        cursor.execute(
            """
            INSERT IGNORE INTO jl_news_crawl_record (url, post_id, create_time)
            VALUES (%s, 0, %s)
            """,
            (url, now),
        )
    connection.commit()


async def crawl_new_articles(connection):
    limits = httpx.Limits(
        max_connections=CONCURRENCY + 2,
        max_keepalive_connections=CONCURRENCY + 2,
    )
    async with httpx.AsyncClient(
        headers=HEADERS,
        follow_redirects=True,
        timeout=30,
        limits=limits,
        http2=True,
    ) as client:
        response = await client.get(LIST_URL)
        response.raise_for_status()
        items = get_list_items(response.text)
        existing_urls = known_urls(connection, [item["url"] for item in items])
        new_items = [item for item in items if item["url"] not in existing_urls]
        new_items = new_items[:MAX_ARTICLES]

        if not new_items:
            logging.info("列表页没有发现新文章，本次不抓取详情")
            return []

        logging.info("发现 %d 篇新文章，开始抓取详情", len(new_items))
        semaphore = asyncio.Semaphore(CONCURRENCY)
        return await asyncio.gather(
            *(fetch_detail(client, semaphore, item) for item in new_items),
            return_exceptions=True,
        )


async def main():
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(message)s",
    )
    connection = database_connection()
    imported = 0
    failed = 0
    skipped = 0
    try:
        results = await crawl_new_articles(connection)
        for result in results:
            if isinstance(result, Exception):
                failed += 1
                logging.error("抓取文章失败: %s", result)
                continue
            if not result.get("content"):
                skipped += 1
                mark_checked_without_content(connection, result)
                logging.warning("正文为空，已跳过并避免重复抓取: %s", result.get("url"))
                continue
            try:
                if import_article(connection, result):
                    imported += 1
                    logging.info("资讯已同步: %s", result["title"])
            except Exception:
                failed += 1
                connection.rollback()
                logging.exception("资讯入库失败: %s", result.get("url"))
    finally:
        connection.close()

    logging.info("任务结束：新增 %d 篇，跳过 %d 篇，失败 %d 篇", imported, skipped, failed)
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(asyncio.run(main()))
