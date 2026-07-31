#!/usr/bin/env python3
"""Collect enabled exchange tickers and expose a small Redis-backed reader API."""

import json
import logging
import os
import signal
import time
from collections import defaultdict
from urllib.request import Request, urlopen

import ccxt
import redis


LOG_LEVEL = os.getenv("MARKET_LOG_LEVEL", "INFO").upper()
POLL_SECONDS = max(float(os.getenv("MARKET_POLL_SECONDS", "3")), 1.0)
CONFIG_REFRESH_SECONDS = max(int(os.getenv("MARKET_CONFIG_REFRESH_SECONDS", "60")), 10)
TICKER_TTL_SECONDS = max(int(os.getenv("MARKET_TICKER_TTL_SECONDS", "30")), 10)
KEY_PREFIX = os.getenv("MARKET_REDIS_PREFIX", "lhqb:market:v1")
EXCHANGE_ALIASES = {
    "okex": "okx",
    "huobipro": "htx",
}

logging.basicConfig(
    level=getattr(logging, LOG_LEVEL, logging.INFO),
    format="%(asctime)s %(levelname)s %(message)s",
)
logger = logging.getLogger("lhqb.market")

running = True
_redis_client = None


def normalize_exchange(value):
    exchange = str(value or "").strip().lower()
    return EXCHANGE_ALIASES.get(exchange, exchange)


def normalize_market(value):
    market = str(value or "").strip().upper().replace("-", "/").replace("_", "/")
    if "/" not in market and market.endswith("USDT"):
        market = market[:-4] + "/USDT"
    return market


def symbol_key(market):
    return normalize_market(market).replace("/", "").replace(":", "")


def ticker_key(exchange, market):
    return "%s:ticker:%s:%s" % (KEY_PREFIX, normalize_exchange(exchange), symbol_key(market))


def health_key(exchange):
    return "%s:health:%s" % (KEY_PREFIX, normalize_exchange(exchange))


def redis_client():
    global _redis_client
    if _redis_client is not None:
        return _redis_client

    redis_url = os.getenv("REDIS_URL")
    if redis_url:
        _redis_client = redis.Redis.from_url(redis_url, decode_responses=True)
    else:
        _redis_client = redis.Redis(
            host=os.getenv("REDIS_HOST", "127.0.0.1"),
            port=int(os.getenv("REDIS_PORT", "6379")),
            db=int(os.getenv("REDIS_DB", "0")),
            password=os.getenv("REDIS_PASSWORD") or None,
            socket_connect_timeout=3,
            socket_timeout=3,
            decode_responses=True,
        )
    _redis_client.ping()
    return _redis_client


def config_url():
    configured = os.getenv("MARKET_CONFIG_URL", "").strip()
    if configured:
        return configured
    domain = os.getenv("LHQB_DOMAIN", "").strip()
    if domain:
        return "https://%s/api/home/ticker/config" % domain
    return "http://127.0.0.1/api/home/ticker/config"


def load_enabled_markets():
    request = Request(config_url(), headers={"Accept": "application/json", "User-Agent": "lhqb-market/1.0"})
    with urlopen(request, timeout=10) as response:
        payload = json.loads(response.read().decode("utf-8"))

    data = payload.get("data") if isinstance(payload, dict) else None
    rows = data.get("list", []) if isinstance(data, dict) else []
    grouped = defaultdict(list)
    for row in rows:
        exchange = normalize_exchange(row.get("exchange") or row.get("platform"))
        market = normalize_market(row.get("market"))
        if exchange and market and market not in grouped[exchange]:
            grouped[exchange].append(market)
    if not grouped:
        raise RuntimeError("no enabled markets returned by config endpoint")
    return dict(grouped)


def create_exchange(exchange_name):
    exchange_class = getattr(ccxt, exchange_name, None)
    if exchange_class is None:
        raise RuntimeError("unsupported CCXT exchange: %s" % exchange_name)
    return exchange_class({
        "enableRateLimit": True,
        "timeout": 12000,
    })


def decimal_value(value):
    if value is None:
        return None
    return str(value)


def normalize_ticker(exchange, market, ticker):
    info = ticker.get("info") if isinstance(ticker.get("info"), dict) else {}
    received_at = int(time.time())
    event_time = ticker.get("timestamp") or info.get("closeTime") or info.get("E") or received_at * 1000
    return {
        "exchange": normalize_exchange(exchange),
        "symbol": normalize_market(ticker.get("symbol") or market),
        "last_price": decimal_value(ticker.get("last")),
        "open_24h": decimal_value(ticker.get("open")),
        "change_24h": decimal_value(ticker.get("change")),
        "change_percent_24h": decimal_value(ticker.get("percentage")),
        "high_24h": decimal_value(ticker.get("high")),
        "low_24h": decimal_value(ticker.get("low")),
        "base_volume_24h": decimal_value(ticker.get("baseVolume")),
        "quote_volume_24h": decimal_value(ticker.get("quoteVolume")),
        "event_time": int(event_time),
        "received_at": received_at,
    }


def fetch_exchange_tickers(exchange, markets):
    if exchange.has.get("fetchTickers"):
        values = exchange.fetch_tickers(markets)
        return {normalize_market(key): value for key, value in values.items()}
    return {market: exchange.fetch_ticker(market) for market in markets}


def store_tickers(exchange_name, markets, tickers):
    client = redis_client()
    pipeline = client.pipeline(transaction=False)
    stored = 0
    for market in markets:
        ticker = tickers.get(normalize_market(market))
        if not ticker or ticker.get("last") is None:
            continue
        quote = normalize_ticker(exchange_name, market, ticker)
        pipeline.set(ticker_key(exchange_name, market), json.dumps(quote, ensure_ascii=False), ex=TICKER_TTL_SECONDS)
        stored += 1
    pipeline.set(
        health_key(exchange_name),
        json.dumps({"exchange": exchange_name, "updated_at": int(time.time()), "count": stored}),
        ex=TICKER_TTL_SECONDS,
    )
    pipeline.execute()
    return stored


def get_ticker(exchange, market):
    raw = redis_client().get(ticker_key(exchange, market))
    return json.loads(raw) if raw else None


def get_price(exchange, market):
    ticker = get_ticker(exchange, market)
    return float(ticker["last_price"]) if ticker and ticker.get("last_price") is not None else None


def stop_service(signum, _frame):
    global running
    logger.info("received signal %s, stopping", signum)
    running = False


def run():
    signal.signal(signal.SIGTERM, stop_service)
    signal.signal(signal.SIGINT, stop_service)

    enabled = {}
    exchanges = {}
    last_config_refresh = 0

    while running:
        started_at = time.monotonic()
        now = time.time()
        if not enabled or now - last_config_refresh >= CONFIG_REFRESH_SECONDS:
            try:
                enabled = load_enabled_markets()
                last_config_refresh = now
                logger.info("enabled markets: %s", enabled)
            except Exception as exc:
                logger.error("market config refresh failed: %s", exc)

        for exchange_name, markets in enabled.items():
            if not running:
                break
            try:
                exchange = exchanges.get(exchange_name)
                if exchange is None:
                    exchange = create_exchange(exchange_name)
                    exchanges[exchange_name] = exchange
                tickers = fetch_exchange_tickers(exchange, markets)
                stored = store_tickers(exchange_name, markets, tickers)
                logger.info("updated exchange=%s tickers=%d", exchange_name, stored)
            except Exception as exc:
                logger.error("ticker update failed exchange=%s: %s", exchange_name, exc)
                failed_exchange = exchanges.pop(exchange_name, None)
                if failed_exchange is not None:
                    try:
                        failed_exchange.close()
                    except Exception:
                        pass

        delay = POLL_SECONDS - (time.monotonic() - started_at)
        if delay > 0:
            time.sleep(delay)

    for exchange in exchanges.values():
        try:
            exchange.close()
        except Exception:
            pass


if __name__ == "__main__":
    run()
