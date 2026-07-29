#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${NEWS_CRAWLER_ENV_FILE:-/data/lhqb/shared/python/news-crawler.env}"
PYTHON_BIN="${NEWS_CRAWLER_PYTHON:-/data/lhqb/shared/python/venvs/news-crawler/bin/python}"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "新闻爬虫配置不存在: $ENV_FILE" >&2
    exit 1
fi

set -a
source "$ENV_FILE"
set +a

if [[ ! -x "$PYTHON_BIN" ]]; then
    echo "新闻爬虫 Python 环境不存在: $PYTHON_BIN" >&2
    exit 1
fi

exec "$PYTHON_BIN" "$SCRIPT_DIR/coindesk_crawler.py"
