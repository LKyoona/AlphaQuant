#!/usr/bin/env python3
"""Safe, testable spot-grid trading engine for LHQB.

The engine is read-only unless both TRADING_EXECUTION_ENABLED=true and the
--live command-line flag are present. This double opt-in prevents an accidental
service start from placing real exchange orders.
"""

import argparse
import json
import logging
import os
import re
import signal
import threading
import time
import uuid
from concurrent.futures import ThreadPoolExecutor, as_completed
from contextlib import contextmanager
from dataclasses import dataclass, replace
from decimal import Decimal, InvalidOperation

import ccxt
import pymysql
import redis


LOGGER = logging.getLogger("lhqb.trading")
ZERO = Decimal("0")
ONE_HUNDRED = Decimal("100")
EXCHANGE_ALIASES = {
    "okex": "okx",
    "huobi": "htx",
    "huobipro": "htx",
}


USER_TEXT_TRANSLATIONS = {
    "首次建仓": "Open initial position",
    "补仓": "Add position",
    "平仓": "Close position",
    "等待": "Wait",
    "确认空仓": "Confirm empty position",
    "用户发起手动清仓": "Manual close requested",
    "已达到止盈线，开始跟踪回撤": "Profit target reached; tracking pullback",
    "已更新盈利高点": "Profit high updated",
    "已达到止盈回撤条件": "Profit pullback threshold reached",
    "已达到补仓线，开始跟踪反弹": "Add-position threshold reached; tracking rebound",
    "已更新补仓低点": "Add-position low updated",
    "已达到反弹补仓条件": "Add-position rebound threshold reached",
    "暂时没有交易动作": "No trading action required",
    "已确认清仓指令，当前没有持仓": "Clear request acknowledged; no open position",
    "当前没有可清理的持仓": "No open position to clear",
    "订单已成交": "Order filled",
    "持仓已平仓": "Position closed",
    "缺少已启用的交易市场配置": "No enabled market configuration",
    "缺少已启用的交易所 API": "No enabled exchange API configured",
    "运行状态数据无效，为避免重复下单已暂停该机器人": "Invalid runtime state; robot paused to prevent duplicate orders",
    "交易所 API 身份验证失败，请检查 API Key 和 Secret": "Exchange API authentication failed; check the API key and secret",
    "交易所 API 权限不足，请开启现货读取和现货交易权限": "Exchange API permission denied; enable spot read and trading permissions",
    "交易所请求过于频繁，请稍后重试": "Exchange request rate limit reached; try again later",
    "Binance API 无效、服务器 IP 不在白名单，或 API 未开启现货交易权限（错误码 -2015）": (
        "Binance API is invalid, the server IP is not whitelisted, or spot trading permission is disabled (error -2015)"
    ),
}


def user_text(value):
    """Convert internal Chinese diagnostics to stable English text shown to users."""
    text = str(value or "").replace("\r", " ").replace("\n", " ").strip()
    if text in USER_TEXT_TRANSLATIONS:
        return USER_TEXT_TRANSLATIONS[text]

    balance_match = re.fullmatch(r"([^ ]+) 可用余额不足：需要 ([^，]+)，可用 (.+)", text)
    if balance_match:
        currency, required, available = balance_match.groups()
        return "%s available balance is insufficient: required %s, available %s" % (
            currency,
            required,
            available,
        )

    rejected_match = re.match(r"(.+?) 被交易所拒绝：(.*)", text)
    if rejected_match:
        return "%s was rejected by the exchange: %s" % (
            user_text(rejected_match.group(1)),
            user_text(rejected_match.group(2)),
        )

    failed_match = re.match(r"(.+?) 执行失败：(.*)", text)
    if failed_match:
        return "%s failed: %s" % (
            user_text(failed_match.group(1)),
            user_text(failed_match.group(2)),
        )

    strategy_match = re.fullmatch(r"策略状态：动作=([^；]+)；原因=(.+)", text)
    if strategy_match:
        return "Strategy status: action=%s; reason=%s" % (
            user_text(strategy_match.group(1)),
            user_text(strategy_match.group(2)),
        )

    replacements = (
        (" 可用余额不足，交易所拒绝下单", " available balance is insufficient; the exchange rejected the order"),
        ("连接交易所失败：", "Failed to connect to the exchange: "),
        ("不支持的交易所：", "Unsupported exchange: "),
        ("交易所余额中没有返回 ", "The exchange balance did not include "),
        ("没有可卖出的 ", "No available "),
        (" 可用余额", " balance to sell"),
        ("卖出数量低于交易所最小精度", "Sell amount is below the exchange minimum precision"),
        ("交易所返回的成交订单数据不完整", "The exchange returned incomplete filled-order data"),
        ("行情价格必须大于 0", "Market price must be greater than zero"),
        ("买单可能已成交，但成交结果确认失败：", "Buy order may have filled, but settlement confirmation failed: "),
        ("卖单可能已成交，但成交结果确认失败：", "Sell order may have filled, but settlement confirmation failed: "),
        ("买单已成交但数据库记录失败，需要人工核对：", "Buy order filled but database recording failed; manual reconciliation required: "),
        ("卖单已成交但数据库记录失败，需要人工核对：", "Sell order filled but database recording failed; manual reconciliation required: "),
    )
    for chinese, english in replacements:
        text = text.replace(chinese, english)
    for chinese, english in sorted(USER_TEXT_TRANSLATIONS.items(), key=lambda item: len(item[0]), reverse=True):
        text = text.replace(chinese, english)
    return text


def as_decimal(value, default=ZERO):
    if value is None or value == "":
        return default
    try:
        return Decimal(str(value))
    except (InvalidOperation, ValueError, TypeError):
        return default


def env_bool(name, default=False):
    value = os.getenv(name)
    if value is None:
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}


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


@dataclass(frozen=True)
class DatabaseConfig:
    host: str
    port: int
    database: str
    user: str
    password: str
    charset: str
    prefix: str

    @classmethod
    def from_env(cls):
        prefix = os.getenv("DB_PREFIX", "jl_").strip()
        if not re.fullmatch(r"[A-Za-z0-9_]+", prefix):
            raise ValueError("DB_PREFIX 只能包含字母、数字和下划线")
        return cls(
            host=os.getenv("DB_HOST", "127.0.0.1"),
            port=int(os.getenv("DB_PORT", "3306")),
            database=os.getenv("DB_DATABASE", "lhqb"),
            user=os.getenv("DB_USERNAME", "root"),
            password=os.getenv("DB_PASSWORD", ""),
            charset=os.getenv("DB_CHARSET", "utf8mb4"),
            prefix=prefix,
        )


@dataclass(frozen=True)
class EngineConfig:
    database: DatabaseConfig
    poll_seconds: float
    workers: int
    lock_seconds: int
    market_prefix: str

    @classmethod
    def from_env(cls):
        return cls(
            database=DatabaseConfig.from_env(),
            poll_seconds=max(float(os.getenv("TRADING_POLL_SECONDS", "10")), 1.0),
            workers=max(1, min(int(os.getenv("TRADING_WORKERS", "4")), 32)),
            lock_seconds=max(int(os.getenv("TRADING_LOCK_SECONDS", "120")), 30),
            market_prefix=os.getenv("MARKET_REDIS_PREFIX", "lhqb:market:v1").strip(),
        )


@dataclass(frozen=True)
class RobotConfig:
    id: int
    uid: int
    platform: str
    market: str
    stock: str
    money: str
    first_order_value: Decimal
    max_order_count: int
    stop_profit_rate: Decimal
    stop_profit_callback_rate: Decimal
    cover_rates: dict
    cover_callback_rate: Decimal
    recycle_status: int
    c_type: int
    number: int
    is_clean: bool
    api_id: int
    api_key: str
    secret_key: str
    passphrase: str

    @classmethod
    def from_row(cls, row):
        raw_cover_rates = row.get("cover_rate") or "{}"
        try:
            decoded_cover_rates = json.loads(raw_cover_rates)
        except (TypeError, ValueError, json.JSONDecodeError):
            decoded_cover_rates = {}
        if not isinstance(decoded_cover_rates, dict):
            decoded_cover_rates = {}
        cover_rates = {
            int(key): as_decimal(value)
            for key, value in decoded_cover_rates.items()
            if str(key).isdigit()
        }
        return cls(
            id=int(row["id"]),
            uid=int(row["uid"]),
            platform=normalize_exchange(row.get("platform")),
            market=normalize_market(row.get("market")),
            stock=str(row.get("stock") or "").upper(),
            money=str(row.get("money") or "").upper(),
            first_order_value=as_decimal(row.get("first_order_value")),
            max_order_count=max(int(row.get("max_order_count") or 1), 1),
            stop_profit_rate=as_decimal(row.get("stop_profit_rate")),
            stop_profit_callback_rate=as_decimal(row.get("stop_profit_callback_rate")),
            cover_rates=cover_rates,
            cover_callback_rate=as_decimal(row.get("cover_callback_rate")),
            recycle_status=int(row.get("recycle_status") or 0),
            c_type=int(row.get("c_type") or 1),
            number=max(int(row.get("number") or 0), 0),
            is_clean=int(row.get("is_clean") or 0) == 1,
            api_id=int(row.get("api_id") or 0),
            api_key=str(row.get("api_key") or ""),
            secret_key=str(row.get("secret_key") or ""),
            passphrase=str(row.get("passphrase") or ""),
        )

    def cover_quote_amount(self, completed_orders):
        # Existing strategies can run equal/difference orders first, then switch
        # to doubling from the configured order count onward.
        effective_type = 1 if self.number > 0 and completed_orders >= self.number else self.c_type
        if effective_type == 1:
            return self.first_order_value * (Decimal(2) ** completed_orders)
        if effective_type == 2:
            return self.first_order_value
        return self.first_order_value * Decimal(completed_orders + 1)


@dataclass(frozen=True)
class RuntimeState:
    first_order_price: Decimal
    base_price: Decimal
    up_price: Decimal
    down_price: Decimal
    trend_side: int
    order_count: int
    deal_amount: Decimal
    deal_money: Decimal
    order_finish: bool
    pid: str

    @classmethod
    def from_json(cls, value):
        if not value:
            return None
        try:
            data = json.loads(value)
            if not isinstance(data, dict):
                return None
            state = cls(
                first_order_price=as_decimal(data.get("first_order_price")),
                base_price=as_decimal(data.get("base_price")),
                up_price=as_decimal(data.get("up_price")),
                down_price=as_decimal(data.get("down_price")),
                trend_side=int(data.get("trend_side") or 0),
                order_count=max(int(data.get("order_count") or 0), 0),
                deal_amount=as_decimal(data.get("deal_amount")),
                deal_money=as_decimal(data.get("deal_money")),
                order_finish=bool(int(data.get("order_finish") or 0)),
                pid=str(data.get("pid") or ""),
            )
        except (TypeError, ValueError, json.JSONDecodeError):
            return None
        if state.base_price <= ZERO or state.deal_amount <= ZERO or state.deal_money <= ZERO:
            return None
        return state

    def to_json(self):
        return json.dumps(
            {
                "first_order_price": str(self.first_order_price),
                "base_price": str(self.base_price),
                "up_price": str(self.up_price),
                "down_price": str(self.down_price),
                "trend_side": self.trend_side,
                "order_count": self.order_count,
                "deal_amount": str(self.deal_amount),
                "deal_money": str(self.deal_money),
                "order_finish": int(self.order_finish),
                "pid": self.pid,
            },
            separators=(",", ":"),
        )


@dataclass(frozen=True)
class Decision:
    action: str
    state: RuntimeState
    revenue: Decimal
    reason: str
    quote_amount: Decimal = ZERO


@dataclass(frozen=True)
class OrderResult:
    order_id: str
    cost: Decimal
    amount: Decimal
    average: Decimal


class UncertainOrderError(RuntimeError):
    """交易所可能已接受订单，此时自动重试并不安全。"""


class InsufficientBalanceError(RuntimeError):
    """单个机器人余额不足，不应影响其他机器人继续运行。"""

    def __init__(self, currency, required, available):
        self.currency = currency
        self.required = as_decimal(required)
        self.available = as_decimal(available)
        super().__init__("%s 可用余额不足：需要 %s，可用 %s" % (
            self.currency,
            self.required,
            self.available,
        ))


class StrategyPlanner:
    WAIT = "wait"
    OPEN = "open"
    COVER = "cover"
    CLOSE = "close"
    RESET_CLEAN = "reset_clean"

    @classmethod
    def evaluate(cls, robot, state, price):
        if price <= ZERO:
            raise ValueError("行情价格必须大于 0")

        if state is None:
            if robot.is_clean:
                return Decision(cls.RESET_CLEAN, None, ZERO, "已确认清仓指令，当前没有持仓")
            return Decision(cls.OPEN, None, ZERO, "首次建仓", robot.first_order_value)

        revenue = state.deal_amount * price - state.deal_money
        if robot.is_clean:
            return Decision(cls.CLOSE, state, revenue, "用户发起手动清仓")

        current = state
        if price > current.base_price:
            profit_rate = (price - current.base_price) * ONE_HUNDRED / current.base_price
            if current.trend_side != 1 and profit_rate >= robot.stop_profit_rate:
                current = replace(current, trend_side=1, up_price=price, down_price=ZERO)
                return Decision(cls.WAIT, current, revenue, "已达到止盈线，开始跟踪回撤")
            if current.trend_side == 1:
                if price > current.up_price:
                    current = replace(current, up_price=price)
                    return Decision(cls.WAIT, current, revenue, "已更新盈利高点")
                callback = (current.up_price - price) * ONE_HUNDRED / current.up_price
                if callback >= robot.stop_profit_callback_rate:
                    return Decision(cls.CLOSE, current, revenue, "已达到止盈回撤条件")

        if price < current.base_price and not current.order_finish:
            drop_rate = (current.base_price - price) * ONE_HUNDRED / current.base_price
            cover_rate = robot.cover_rates.get(current.order_count)
            if cover_rate is not None and current.trend_side != 2 and drop_rate >= cover_rate:
                current = replace(current, trend_side=2, down_price=price, up_price=ZERO)
                return Decision(cls.WAIT, current, revenue, "已达到补仓线，开始跟踪反弹")
            if current.trend_side == 2:
                if price < current.down_price:
                    current = replace(current, down_price=price)
                    return Decision(cls.WAIT, current, revenue, "已更新补仓低点")
                rebound = (price - current.down_price) * ONE_HUNDRED / current.down_price
                if rebound >= robot.cover_callback_rate:
                    amount = robot.cover_quote_amount(current.order_count)
                    return Decision(cls.COVER, current, revenue, "已达到反弹补仓条件", amount)

        return Decision(cls.WAIT, current, revenue, "暂时没有交易动作")


class MarketPriceReader:
    def __init__(self, client, prefix):
        self.client = client
        self.prefix = prefix

    def get_price(self, exchange, market):
        key = "%s:ticker:%s:%s" % (self.prefix, normalize_exchange(exchange), symbol_key(market))
        raw = self.client.get(key)
        if not raw:
            return None
        try:
            payload = json.loads(raw)
            price = as_decimal(payload.get("last_price"))
            return price if price > ZERO else None
        except (TypeError, ValueError, json.JSONDecodeError):
            return None


class RobotLock:
    RELEASE_SCRIPT = """
if redis.call('get', KEYS[1]) == ARGV[1] then
  return redis.call('del', KEYS[1])
end
return 0
"""

    def __init__(self, client, robot_id, ttl):
        self.client = client
        self.key = "lhqb:trading:v1:lock:robot:%s" % robot_id
        self.token = uuid.uuid4().hex
        self.ttl = ttl
        self.acquired = False

    def __enter__(self):
        self.acquired = bool(self.client.set(self.key, self.token, nx=True, ex=self.ttl))
        return self.acquired

    def __exit__(self, _exc_type, _exc, _traceback):
        if self.acquired:
            try:
                self.client.eval(self.RELEASE_SCRIPT, 1, self.key, self.token)
            except redis.RedisError:
                LOGGER.exception("释放机器人锁失败，锁=%s", self.key)


class Repository:
    def __init__(self, config):
        self.config = config
        self.tables = {
            name: "`%s%s`" % (config.prefix, name)
            for name in (
                "quant_robot",
                "spot_market",
                "third_api",
                "quant_robot_order",
                "quant_robot_revenue",
                "quant_robot_log",
                "quant_robot_score",
            )
        }

    def connect(self):
        return pymysql.connect(
            host=self.config.host,
            port=self.config.port,
            user=self.config.user,
            password=self.config.password,
            database=self.config.database,
            charset=self.config.charset,
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=False,
            connect_timeout=5,
            read_timeout=15,
            write_timeout=15,
        )

    @contextmanager
    def transaction(self):
        connection = self.connect()
        try:
            yield connection
            connection.commit()
        except Exception:
            connection.rollback()
            raise
        finally:
            connection.close()

    def load_active_robots(self, shard_index, shard_count):
        robot = self.tables["quant_robot"]
        market = self.tables["spot_market"]
        api = self.tables["third_api"]
        sql = f"""
SELECT r.*, m.market, m.market_name, m.stock, m.money,
       a.id AS api_id, a.api_key, a.secret_key, a.passphrase
FROM {robot} AS r
LEFT JOIN {market} AS m
  ON m.id = r.market_id AND m.status = 1
LEFT JOIN {api} AS a
  ON a.id = (
      SELECT MAX(a2.id) FROM {api} AS a2
      WHERE a2.uid = r.uid AND a2.platform = r.platform AND a2.status = 1
  )
WHERE r.status = 1 AND r.type = 1 AND MOD(r.id, %s) = %s
ORDER BY r.id
"""
        connection = self.connect()
        try:
            with connection.cursor() as cursor:
                cursor.execute(sql, (shard_count, shard_index))
                return cursor.fetchall()
        finally:
            connection.close()

    def refresh_robot_row(self, robot_id):
        robot = self.tables["quant_robot"]
        connection = self.connect()
        try:
            with connection.cursor() as cursor:
                cursor.execute(
                    f"SELECT values_str, is_clean, status, order_status, order_id "
                    f"FROM {robot} WHERE id = %s",
                    (robot_id,),
                )
                return cursor.fetchone()
        finally:
            connection.close()

    def begin_order_intent(self, robot, client_order_id, action):
        robot_table = self.tables["quant_robot"]
        with self.transaction() as connection:
            with connection.cursor() as cursor:
                affected = cursor.execute(
                    f"UPDATE {robot_table} "
                    "SET order_status = 1, order_id = %s, show_msg = %s "
                    "WHERE id = %s AND status = 1 AND COALESCE(order_status, 0) = 0",
                    (client_order_id, "%s order is being submitted" % action, robot.id),
                )
                return affected == 1

    def release_order_intent(self, robot, message):
        with self.transaction() as connection:
            with connection.cursor() as cursor:
                cursor.execute(
                    f"UPDATE {self.tables['quant_robot']} "
                    "SET order_status = 0, order_id = NULL, show_msg = %s WHERE id = %s",
                    (user_text(message)[:180], robot.id),
                )

    def update_runtime(self, robot, state, revenue, event_message=""):
        robot_table = self.tables["quant_robot"]
        with self.transaction() as connection:
            with connection.cursor() as cursor:
                cursor.execute(
                    f"UPDATE {robot_table} SET values_str = %s, revenue = %s WHERE id = %s",
                    (state.to_json(), str(revenue), robot.id),
                )
                if event_message:
                    self._insert_log(cursor, robot, user_text(event_message))

    def record_buy(self, robot, previous_state, order, reason):
        if previous_state is None:
            state = RuntimeState(
                first_order_price=order.average,
                base_price=order.average,
                up_price=ZERO,
                down_price=ZERO,
                trend_side=0,
                order_count=1,
                deal_amount=order.amount,
                deal_money=order.cost,
                order_finish=robot.max_order_count <= 1,
                pid=str(uuid.uuid4()),
            )
            is_first = 1
        else:
            deal_amount = previous_state.deal_amount + order.amount
            deal_money = previous_state.deal_money + order.cost
            order_count = previous_state.order_count + 1
            state = replace(
                previous_state,
                base_price=deal_money / deal_amount,
                up_price=ZERO,
                down_price=ZERO,
                trend_side=0,
                order_count=order_count,
                deal_amount=deal_amount,
                deal_money=deal_money,
                order_finish=order_count >= robot.max_order_count,
            )
            is_first = 0

        with self.transaction() as connection:
            with connection.cursor() as cursor:
                self._insert_order(cursor, robot, order, side=2, is_first=is_first, pid=state.pid)
                cursor.execute(
                    f"INSERT INTO {self.tables['quant_robot_score']} "
                    "(robot_id, uid, price, type, deal_status) VALUES (%s, %s, %s, %s, 0)",
                    (robot.id, robot.uid, str(robot.first_order_value), state.order_count),
                )
                cursor.execute(
                    f"UPDATE {self.tables['quant_robot']} "
                    "SET values_str = %s, revenue = 0, order_status = 0, order_id = %s, "
                    "show_msg = %s WHERE id = %s",
                    (state.to_json(), order.order_id, "Order filled", robot.id),
                )
                self._insert_log(cursor, robot, "%s; average=%s amount=%s cost=%s" % (
                    user_text(reason), order.average, order.amount, order.cost
                ))
        return state

    def record_sell(self, robot, state, order, reason):
        revenue = order.cost - state.deal_money
        next_status = 1 if robot.recycle_status == 1 else 0
        with self.transaction() as connection:
            with connection.cursor() as cursor:
                self._insert_order(cursor, robot, order, side=1, is_first=2, pid=state.pid)
                cursor.execute(
                    f"INSERT INTO {self.tables['quant_robot_revenue']} "
                    "(platform, qrobot_id, pid, uid, market, stock, money, revenue, deal_status) "
                    "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, 0)",
                    (
                        robot.platform,
                        robot.id,
                        state.pid,
                        robot.uid,
                        robot.market,
                        robot.stock,
                        robot.money,
                        str(revenue),
                    ),
                )
                cursor.execute(
                    f"UPDATE {self.tables['quant_robot']} "
                    "SET values_str = '', revenue = 0, is_clean = 0, status = %s, "
                    "order_status = 0, order_id = %s, show_msg = %s "
                    "WHERE id = %s",
                    (next_status, order.order_id, "Position closed", robot.id),
                )
                self._insert_log(cursor, robot, "%s; average=%s amount=%s proceeds=%s revenue=%s" % (
                    user_text(reason), order.average, order.amount, order.cost, revenue
                ))
        return revenue

    def acknowledge_empty_clean(self, robot):
        with self.transaction() as connection:
            with connection.cursor() as cursor:
                cursor.execute(
                    f"UPDATE {self.tables['quant_robot']} "
                    "SET is_clean = 0, values_str = '', revenue = 0, show_msg = %s WHERE id = %s",
                    ("No open position to clear", robot.id),
                )
                self._insert_log(cursor, robot, "Clear request acknowledged; no open position")

    def record_error(self, robot, message):
        safe_message = user_text(message)[:180]
        with self.transaction() as connection:
            with connection.cursor() as cursor:
                cursor.execute(
                    f"UPDATE {self.tables['quant_robot']} SET show_msg = %s WHERE id = %s",
                    (safe_message, robot.id),
                )
                self._insert_log(
                    cursor,
                    robot,
                    safe_message,
                    log_type=2,
                    dedup_seconds=max(int(os.getenv("TRADING_ERROR_LOG_DEDUP_SECONDS", "86400")), 60),
                )

    def record_strategy_status(self, robot, action, reason):
        content = "Strategy status: action=%s; reason=%s" % (
            user_text(action),
            user_text(reason),
        )
        with self.transaction() as connection:
            with connection.cursor() as cursor:
                self._insert_log(
                    cursor,
                    robot,
                    content[:1000],
                    log_type=3,
                    dedup_seconds=max(int(os.getenv("TRADING_STRATEGY_LOG_DEDUP_SECONDS", "86400")), 60),
                )

    def cleanup_error_logs(self):
        retention_days = max(int(os.getenv("TRADING_ERROR_LOG_RETENTION_DAYS", "30")), 1)
        strategy_retention_days = max(int(os.getenv("TRADING_STRATEGY_LOG_RETENTION_DAYS", "90")), 1)
        with self.transaction() as connection:
            with connection.cursor() as cursor:
                return cursor.execute(
                    f"DELETE FROM {self.tables['quant_robot_log']} "
                    "WHERE (type = 2 AND ctime < FROM_UNIXTIME(UNIX_TIMESTAMP() - %s)) "
                    "OR (type = 3 AND ctime < FROM_UNIXTIME(UNIX_TIMESTAMP() - %s))",
                    (retention_days * 86400, strategy_retention_days * 86400),
                )

    def _insert_order(self, cursor, robot, order, side, is_first, pid):
        cursor.execute(
            f"INSERT INTO {self.tables['quant_robot_order']} "
            "(platform, uid, order_id, qrobot_id, side, market, stock, money, "
            "deal_money, deal_amount, price, order_status, is_first, pid) "
            "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 1, %s, %s)",
            (
                robot.platform,
                robot.uid,
                order.order_id,
                robot.id,
                side,
                robot.market,
                robot.stock,
                robot.money,
                str(order.cost),
                str(order.amount),
                str(order.average),
                is_first,
                pid,
            ),
        )

    def _insert_log(self, cursor, robot, content, log_type=1, dedup_seconds=0):
        content = user_text(content)[:1000]
        if dedup_seconds > 0:
            cursor.execute(
                f"SELECT content FROM {self.tables['quant_robot_log']} "
                "WHERE qrobot_id = %s AND type = %s AND content = %s "
                "AND ctime >= FROM_UNIXTIME(UNIX_TIMESTAMP() - %s) LIMIT 1",
                (robot.id, log_type, content, dedup_seconds),
            )
        else:
            cursor.execute(
                f"SELECT content FROM {self.tables['quant_robot_log']} "
                "WHERE qrobot_id = %s ORDER BY id DESC LIMIT 1",
                (robot.id,),
            )
        previous = cursor.fetchone()
        if previous:
            return
        cursor.execute(
            f"INSERT INTO {self.tables['quant_robot_log']} "
            "(platform, uid, type, qrobot_id, content) VALUES (%s, %s, %s, %s, %s)",
            (robot.platform, robot.uid, log_type, robot.id, content),
        )


class ExchangeGateway:
    def create_exchange(self, robot):
        exchange_class = getattr(ccxt, normalize_exchange(robot.platform), None)
        if exchange_class is None:
            raise RuntimeError("不支持的交易所：%s" % robot.platform)
        exchange = exchange_class(
            {
                "apiKey": robot.api_key,
                "secret": robot.secret_key,
                "password": robot.passphrase or None,
                "enableRateLimit": True,
                "timeout": int(os.getenv("TRADING_EXCHANGE_TIMEOUT_MS", "30000")),
                "options": {"createMarketBuyOrderRequiresPrice": False},
            }
        )
        proxy = os.getenv("TRADING_HTTPS_PROXY", "").strip()
        if proxy:
            exchange.proxies = {"http": proxy, "https": proxy}
        return exchange

    def market_buy(self, robot, quote_amount, reference_price, client_order_id):
        exchange = self.create_exchange(robot)
        try:
            exchange.load_markets()
            balance = exchange.fetch_balance()
            free_balances = balance.get("free") or {}
            available = as_decimal(free_balances.get(robot.money))
            if available < quote_amount:
                raise InsufficientBalanceError(robot.money, quote_amount, available)
            params = self._client_order_params(exchange, client_order_id)
            if exchange.has.get("createMarketBuyOrderWithCost"):
                order = exchange.create_market_buy_order_with_cost(robot.market, float(quote_amount), params)
            elif exchange.id == "binance":
                order = exchange.create_order(
                    robot.market,
                    "market",
                    "buy",
                    None,
                    None,
                    dict(params, quoteOrderQty=float(quote_amount)),
                )
            else:
                amount = quote_amount / reference_price
                amount = exchange.amount_to_precision(robot.market, float(amount))
                order = exchange.create_market_buy_order(robot.market, amount, params)
            try:
                return self._settle_order(exchange, robot, order, side="buy")
            except Exception as exc:
                raise UncertainOrderError("买单可能已成交，但成交结果确认失败：%s" % exc) from exc
        finally:
            exchange.close()

    def market_sell(self, robot, requested_amount, client_order_id):
        exchange = self.create_exchange(robot)
        try:
            exchange.load_markets()
            balance = exchange.fetch_balance()
            free_balances = balance.get("free") or {}
            if robot.stock not in free_balances:
                raise RuntimeError("交易所余额中没有返回 %s" % robot.stock)
            free_amount = as_decimal(free_balances.get(robot.stock))
            if free_amount <= ZERO:
                raise RuntimeError("没有可卖出的 %s 可用余额" % robot.stock)
            sell_amount = min(requested_amount, free_amount)
            precise_amount = exchange.amount_to_precision(robot.market, float(sell_amount))
            if as_decimal(precise_amount) <= ZERO:
                raise RuntimeError("卖出数量低于交易所最小精度")
            params = self._client_order_params(exchange, client_order_id)
            order = exchange.create_market_sell_order(robot.market, precise_amount, params)
            try:
                return self._settle_order(exchange, robot, order, side="sell")
            except Exception as exc:
                raise UncertainOrderError("卖单可能已成交，但成交结果确认失败：%s" % exc) from exc
        finally:
            exchange.close()

    @staticmethod
    def _client_order_params(exchange, client_order_id):
        if exchange.id == "binance":
            return {"newClientOrderId": client_order_id}
        return {"clientOrderId": client_order_id}

    def _settle_order(self, exchange, robot, order, side):
        current = order
        order_id = str(current.get("id") or "")
        for _ in range(5):
            if self._is_settled(current):
                break
            if not order_id or not exchange.has.get("fetchOrder"):
                break
            time.sleep(0.6)
            current = exchange.fetch_order(order_id, robot.market)

        amount = as_decimal(current.get("filled") or current.get("amount"))
        cost = as_decimal(current.get("cost"))
        average = as_decimal(current.get("average"))
        if average <= ZERO and amount > ZERO and cost > ZERO:
            average = cost / amount
        if cost <= ZERO and amount > ZERO and average > ZERO:
            cost = amount * average

        fees = current.get("fees") or []
        if not fees and current.get("fee"):
            fees = [current["fee"]]
        for fee in fees:
            fee_cost = as_decimal((fee or {}).get("cost"))
            fee_currency = str((fee or {}).get("currency") or "").upper()
            if fee_cost <= ZERO:
                continue
            if side == "buy" and fee_currency == robot.stock:
                amount -= fee_cost
            elif side == "buy" and fee_currency == robot.money:
                cost += fee_cost
            elif side == "sell" and fee_currency == robot.money:
                cost -= fee_cost

        if not order_id or amount <= ZERO or cost <= ZERO or average <= ZERO:
            raise RuntimeError("交易所返回的成交订单数据不完整")
        return OrderResult(order_id=order_id, cost=cost, amount=amount, average=average)

    @staticmethod
    def _is_settled(order):
        status = str(order.get("status") or "").lower()
        return status in {"closed", "filled"} and as_decimal(order.get("filled")) > ZERO


def create_redis_client():
    redis_url = os.getenv("REDIS_URL", "").strip()
    if redis_url:
        client = redis.Redis.from_url(redis_url, decode_responses=True)
    else:
        client = redis.Redis(
            host=os.getenv("REDIS_HOST", "127.0.0.1"),
            port=int(os.getenv("REDIS_PORT", "6379")),
            db=int(os.getenv("REDIS_DB", "0")),
            password=os.getenv("REDIS_PASSWORD") or None,
            socket_connect_timeout=3,
            socket_timeout=3,
            decode_responses=True,
        )
    client.ping()
    return client


class TradingEngine:
    def __init__(self, config, repository, redis_client, live):
        self.config = config
        self.repository = repository
        self.redis = redis_client
        self.market = MarketPriceReader(redis_client, config.market_prefix)
        self.gateway = ExchangeGateway()
        self.live = live
        self.running = True
        self._status_lock = threading.Lock()
        self._robot_status = {}
        self._last_heartbeat = time.monotonic()

    def stop(self, signum, _frame):
        LOGGER.info("收到停止信号=%s，交易引擎正在停止", signum)
        self.running = False

    def run(self, shard_index, shard_count, once=False):
        mode = "正式交易" if self.live else "安全预演"
        LOGGER.warning("========== 交易引擎启动｜模式=%s｜分片=%s/%s｜扫描间隔=%s秒 ==========", mode, shard_index, shard_count, self.config.poll_seconds)
        try:
            deleted = self.repository.cleanup_error_logs()
            if deleted:
                LOGGER.info("日志维护：已清理 %s 条超过保留期限的错误日志", deleted)
        except Exception as exc:
            LOGGER.error("错误日志自动清理失败：%s，交易引擎继续运行", self._error_summary(exc))
        while self.running:
            started = time.monotonic()
            active_count = 0
            try:
                rows = self.repository.load_active_robots(shard_index, shard_count)
                active_count = len(rows)
                with ThreadPoolExecutor(max_workers=self.config.workers) as executor:
                    futures = [executor.submit(self.process_robot, row) for row in rows]
                    for future in as_completed(futures):
                        try:
                            future.result()
                        except Exception as exc:
                            LOGGER.error("单个机器人线程异常：%s，其他机器人继续运行", self._error_summary(exc))
            except Exception as exc:
                LOGGER.error("读取活跃机器人失败：%s，本轮跳过，下一轮将自动重试", self._error_summary(exc))

            now = time.monotonic()
            if not once and now - self._last_heartbeat >= 60:
                LOGGER.info(
                    "========== 运行心跳｜活跃机器人=%s｜工作线程=%s｜服务状态=正常 ==========" ,
                    active_count,
                    self.config.workers,
                )
                self._last_heartbeat = now

            if once:
                break
            delay = self.config.poll_seconds - (time.monotonic() - started)
            if delay > 0:
                time.sleep(delay)

    def process_robot(self, row):
        robot = RobotConfig.from_row(row)
        with RobotLock(self.redis, robot.id, self.config.lock_seconds) as acquired:
            if not acquired:
                LOGGER.debug("机器人=%s 正在被其他任务处理，本轮跳过", robot.id)
                return

            if not robot.market or not robot.stock or not robot.money:
                self._handle_invalid_robot(robot, "缺少已启用的交易市场配置")
                return
            if robot.api_id <= 0 or not robot.api_key or not robot.secret_key:
                self._handle_invalid_robot(robot, "缺少已启用的交易所 API")
                return

            latest = self.repository.refresh_robot_row(robot.id)
            if not latest or int(latest.get("status") or 0) != 1:
                return
            if int(latest.get("order_status") or 0) != 0:
                self._log_robot_status(
                    robot,
                    "pending:%s" % latest.get("order_id"),
                    logging.ERROR,
                    "状态=暂停｜原因=存在未确认订单｜订单标识=%s｜需要人工核对",
                    latest.get("order_id"),
                )
                return
            robot = replace(robot, is_clean=int(latest.get("is_clean") or 0) == 1)
            raw_state = latest.get("values_str")
            state = RuntimeState.from_json(raw_state)
            if raw_state and state is None:
                self._handle_invalid_robot(robot, "运行状态数据无效，为避免重复下单已暂停该机器人")
                return
            price = self.market.get_price(robot.platform, robot.market)
            if price is None:
                self._log_robot_status(robot, "market-missing", logging.WARNING, "状态=跳过｜原因=暂时没有可用行情")
                return

            decision = StrategyPlanner.evaluate(robot, state, price)
            if not self.live:
                self._log_robot_status(
                    robot,
                    "preview:%s:%s" % (decision.action, decision.reason),
                    logging.INFO,
                    "模式=安全预演｜动作=%s｜价格=%s｜原因=%s",
                    self._action_name(decision.action),
                    price,
                    decision.reason,
                )
                return

            try:
                if decision.action == StrategyPlanner.WAIT:
                    changed = state is not decision.state and state != decision.state
                    self.repository.update_runtime(
                        robot,
                        decision.state,
                        decision.revenue,
                        decision.reason if changed else "",
                    )
                    status_changed = self._log_robot_status(
                        robot,
                        "wait:%s" % decision.reason,
                        logging.INFO,
                        "状态=运行中｜动作=等待｜价格=%s｜原因=%s",
                        price,
                        decision.reason,
                    )
                    if status_changed:
                        self._record_strategy_status_safely(robot, "等待", decision.reason)
                elif decision.action == StrategyPlanner.RESET_CLEAN:
                    self.repository.acknowledge_empty_clean(robot)
                    self._log_robot_status(robot, "clean-reset", logging.INFO, "状态=成功｜动作=确认空仓")
                elif decision.action in {StrategyPlanner.OPEN, StrategyPlanner.COVER}:
                    self._execute_buy(robot, state, decision, price)
                    self._log_robot_status(
                        robot,
                        "buy-success:%s" % decision.action,
                        logging.INFO,
                        "状态=成功｜动作=%s｜价格=%s｜原因=%s",
                        self._action_name(decision.action),
                        price,
                        decision.reason,
                    )
                elif decision.action == StrategyPlanner.CLOSE:
                    self._execute_sell(robot, state, decision)
                    self._log_robot_status(
                        robot,
                        "sell-success",
                        logging.INFO,
                        "状态=成功｜动作=平仓｜价格=%s｜原因=%s",
                        price,
                        decision.reason,
                    )
            except InsufficientBalanceError as exc:
                message = str(exc)
                if self._log_robot_status(robot, "balance:%s" % message, logging.WARNING, "状态=跳过｜原因=%s", message):
                    self._record_robot_error_safely(robot, message)
            except ccxt.InsufficientFunds:
                message = "%s 可用余额不足，交易所拒绝下单" % robot.money
                if self._log_robot_status(robot, "balance:%s" % message, logging.WARNING, "状态=跳过｜原因=%s", message):
                    self._record_robot_error_safely(robot, message)
            except ccxt.BaseError as exc:
                message = self._exchange_error_message(exc)
                if self._log_robot_status(
                    robot,
                    "exchange:%s" % message,
                    logging.ERROR,
                    "状态=失败｜API=%s｜动作=%s｜原因=%s｜其他用户继续运行",
                    robot.api_id,
                    self._action_name(decision.action),
                    message,
                ):
                    self._record_robot_error_safely(robot, message)
            except Exception as exc:
                message = self._error_summary(exc)
                if self._log_robot_status(
                    robot,
                    "program:%s" % message,
                    logging.ERROR,
                    "状态=失败｜API=%s｜动作=%s｜程序异常=%s｜其他用户继续运行",
                    robot.api_id,
                    self._action_name(decision.action),
                    message,
                ):
                    self._record_robot_error_safely(robot, "%s 执行失败：%s" % (decision.reason, message))

    def _execute_buy(self, robot, state, decision, price):
        client_order_id = self._client_order_id(robot.id)
        if not self.repository.begin_order_intent(robot, client_order_id, decision.action):
            LOGGER.warning("机器人=%s 无法取得数据库订单锁，本轮跳过", robot.id)
            return
        try:
            order = self.gateway.market_buy(robot, decision.quote_amount, price, client_order_id)
        except UncertainOrderError:
            raise
        except ccxt.NetworkError as exc:
            if self._is_definitive_exchange_rejection(exc):
                self.repository.release_order_intent(robot, "%s 被交易所拒绝：%s" % (decision.reason, exc))
            raise
        except Exception as exc:
            self.repository.release_order_intent(robot, "%s 被交易所拒绝：%s" % (decision.reason, exc))
            raise
        try:
            self.repository.record_buy(robot, state, order, decision.reason)
        except Exception as exc:
            raise UncertainOrderError("买单已成交但数据库记录失败，需要人工核对：%s" % exc) from exc

    def _execute_sell(self, robot, state, decision):
        client_order_id = self._client_order_id(robot.id)
        if not self.repository.begin_order_intent(robot, client_order_id, decision.action):
            LOGGER.warning("机器人=%s 无法取得数据库订单锁，本轮跳过", robot.id)
            return
        try:
            order = self.gateway.market_sell(robot, state.deal_amount, client_order_id)
        except UncertainOrderError:
            raise
        except ccxt.NetworkError as exc:
            if self._is_definitive_exchange_rejection(exc):
                self.repository.release_order_intent(robot, "%s 被交易所拒绝：%s" % (decision.reason, exc))
            raise
        except Exception as exc:
            self.repository.release_order_intent(robot, "%s 被交易所拒绝：%s" % (decision.reason, exc))
            raise
        try:
            self.repository.record_sell(robot, state, order, decision.reason)
        except Exception as exc:
            raise UncertainOrderError("卖单已成交但数据库记录失败，需要人工核对：%s" % exc) from exc

    @staticmethod
    def _client_order_id(robot_id):
        return "lhqb%s%s" % (robot_id, uuid.uuid4().hex[:20])

    @staticmethod
    def _action_name(action):
        return {
            StrategyPlanner.WAIT: "等待",
            StrategyPlanner.OPEN: "首次建仓",
            StrategyPlanner.COVER: "补仓",
            StrategyPlanner.CLOSE: "平仓",
            StrategyPlanner.RESET_CLEAN: "确认空仓",
        }.get(action, action)

    def _log_robot_status(self, robot, status_key, level, message, *args):
        with self._status_lock:
            if self._robot_status.get(robot.id) == status_key:
                return False
            self._robot_status[robot.id] = status_key

        detail = message % args if args else message
        LOGGER.log(
            level,
            "[用户=%s｜机器人=%s｜%s｜%s] %s",
            robot.uid,
            robot.id,
            robot.platform or "未配置交易所",
            robot.market or "未配置交易对",
            detail,
        )
        return True

    def _handle_invalid_robot(self, robot, message):
        changed = self._log_robot_status(robot, "invalid:%s" % message, logging.ERROR, "状态=跳过｜配置异常=%s", message)
        if self.live and changed:
            self._record_robot_error_safely(robot, message)

    def _record_robot_error_safely(self, robot, message):
        try:
            self.repository.record_error(robot, message)
        except Exception as exc:
            LOGGER.error("机器人=%s 写入错误信息失败：%s，但不会影响其他机器人", robot.id, self._error_summary(exc))

    def _record_strategy_status_safely(self, robot, action, reason):
        try:
            self.repository.record_strategy_status(robot, action, reason)
        except Exception as exc:
            LOGGER.error("机器人=%s 写入策略状态失败：%s，但不会影响交易", robot.id, self._error_summary(exc))

    @staticmethod
    def _error_summary(exc):
        message = str(exc).strip().replace("\r", " ").replace("\n", " ")
        return message or exc.__class__.__name__

    @classmethod
    def _is_definitive_exchange_rejection(cls, exc):
        raw = cls._error_summary(exc)
        return (
            "-2015" in raw
            or "Invalid API-key, IP, or permissions" in raw
            or isinstance(exc, (ccxt.AuthenticationError, ccxt.PermissionDenied))
        )

    @classmethod
    def _exchange_error_message(cls, exc):
        raw = cls._error_summary(exc)
        if "-2015" in raw or "Invalid API-key, IP, or permissions" in raw:
            return "Binance API 无效、服务器 IP 不在白名单，或 API 未开启现货交易权限（错误码 -2015）"
        if isinstance(exc, ccxt.AuthenticationError):
            return "交易所 API 身份验证失败，请检查 API Key 和 Secret"
        if isinstance(exc, ccxt.PermissionDenied):
            return "交易所 API 权限不足，请开启现货读取和现货交易权限"
        if isinstance(exc, ccxt.RateLimitExceeded):
            return "交易所请求过于频繁，请稍后重试"
        if isinstance(exc, ccxt.NetworkError):
            return "连接交易所失败：%s" % raw
        return "%s：%s" % (exc.__class__.__name__, raw)


def build_argument_parser():
    parser = argparse.ArgumentParser(description="LHQB 现货交易引擎")
    parser.add_argument("--live", action="store_true", help="允许提交真实交易所订单")
    parser.add_argument("--once", action="store_true", help="只处理一轮机器人后退出")
    parser.add_argument("--shard-index", type=int, default=int(os.getenv("TRADING_SHARD_INDEX", "0")))
    parser.add_argument("--shard-count", type=int, default=int(os.getenv("TRADING_SHARD_COUNT", "1")))
    return parser


def main():
    logging.basicConfig(
        level=getattr(logging, os.getenv("TRADING_LOG_LEVEL", "INFO").upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s %(message)s",
    )
    args = build_argument_parser().parse_args()
    if args.shard_count < 1 or not 0 <= args.shard_index < args.shard_count:
        raise SystemExit("分片编号或分片数量无效")

    execution_enabled = env_bool("TRADING_EXECUTION_ENABLED", False)
    if args.live and not execution_enabled:
        raise SystemExit("正式交易还需要设置 TRADING_EXECUTION_ENABLED=true")

    config = EngineConfig.from_env()
    client = create_redis_client()
    repository = Repository(config.database)
    engine = TradingEngine(config, repository, client, live=args.live and execution_enabled)
    signal.signal(signal.SIGTERM, engine.stop)
    signal.signal(signal.SIGINT, engine.stop)
    engine.run(args.shard_index, args.shard_count, once=args.once)


if __name__ == "__main__":
    main()
