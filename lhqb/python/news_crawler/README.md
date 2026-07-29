# 新闻爬虫

当前只采集 Python 输出中的六个字段：

- `title`
- `summary`
- `category`
- `published_at`
- `content`
- `url`

暂不采集图片。任务每 30 分钟检查一次 CoinDesk 列表页；没有新 URL 时不会请求文章详情，也不会写数据库。

## 正式环境配置

运行配置位于 `/data/lhqb/shared/python/news-crawler.env`：

```bash
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lhqb
DB_USERNAME=root
DB_PASSWORD=数据库密码
NEWS_CRAWLER_CONCURRENCY=4
NEWS_CRAWLER_MAX_ARTICLES=30
```

Python 虚拟环境：

```text
/data/lhqb/shared/python/venvs/news-crawler
```

定时任务由 systemd 管理：

```bash
systemctl status lhqb-news-crawler.timer
systemctl list-timers lhqb-news-crawler.timer
systemctl start lhqb-news-crawler.service
```

查看日志：

```bash
tail -f /data/lhqb/logs/crawler/news-crawler.log
```
