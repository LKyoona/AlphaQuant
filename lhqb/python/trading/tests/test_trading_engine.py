import unittest
import threading
from dataclasses import replace
from decimal import Decimal
from types import SimpleNamespace

import ccxt

from trading.trading_engine import (
    Decision,
    ExchangeGateway,
    InsufficientBalanceError,
    OrderResult,
    RobotConfig,
    RuntimeState,
    StrategyPlanner,
    TradingEngine,
    UncertainOrderError,
    ZERO,
    user_text,
)


def robot(**overrides):
    base = RobotConfig(
        id=1,
        uid=20,
        platform="binance",
        market="BTC/USDT",
        stock="BTC",
        money="USDT",
        first_order_value=Decimal("100"),
        max_order_count=6,
        stop_profit_rate=Decimal("2"),
        stop_profit_callback_rate=Decimal("0.5"),
        cover_rates={1: Decimal("2"), 2: Decimal("4")},
        cover_callback_rate=Decimal("0.3"),
        recycle_status=1,
        c_type=2,
        number=0,
        is_clean=False,
        api_id=1,
        api_key="key",
        secret_key="secret",
        passphrase="",
    )
    return replace(base, **overrides)


def state(**overrides):
    base = RuntimeState(
        first_order_price=Decimal("100"),
        base_price=Decimal("100"),
        up_price=Decimal("0"),
        down_price=Decimal("0"),
        trend_side=0,
        order_count=1,
        deal_amount=Decimal("1"),
        deal_money=Decimal("100"),
        order_finish=False,
        pid="cycle-1",
    )
    return replace(base, **overrides)


class StrategyPlannerTests(unittest.TestCase):
    def test_new_robot_opens_first_order(self):
        decision = StrategyPlanner.evaluate(robot(), None, Decimal("100"))
        self.assertEqual(StrategyPlanner.OPEN, decision.action)
        self.assertEqual(Decimal("100"), decision.quote_amount)
        self.assertEqual("首次建仓", decision.reason)

    def test_empty_robot_acknowledges_manual_clean(self):
        decision = StrategyPlanner.evaluate(robot(is_clean=True), None, Decimal("100"))
        self.assertEqual(StrategyPlanner.RESET_CLEAN, decision.action)

    def test_profit_target_then_callback_closes_position(self):
        tracking = StrategyPlanner.evaluate(robot(), state(), Decimal("103"))
        self.assertEqual(StrategyPlanner.WAIT, tracking.action)
        self.assertEqual(1, tracking.state.trend_side)
        self.assertEqual(Decimal("103"), tracking.state.up_price)

        close = StrategyPlanner.evaluate(robot(), tracking.state, Decimal("102.4"))
        self.assertEqual(StrategyPlanner.CLOSE, close.action)

    def test_cover_threshold_then_rebound_places_cover_order(self):
        tracking = StrategyPlanner.evaluate(robot(), state(), Decimal("97"))
        self.assertEqual(StrategyPlanner.WAIT, tracking.action)
        self.assertEqual(2, tracking.state.trend_side)
        self.assertEqual(Decimal("97"), tracking.state.down_price)

        cover = StrategyPlanner.evaluate(robot(), tracking.state, Decimal("97.4"))
        self.assertEqual(StrategyPlanner.COVER, cover.action)
        self.assertEqual(Decimal("100"), cover.quote_amount)

    def test_finished_order_plan_does_not_cover(self):
        finished = state(order_count=6, order_finish=True)
        decision = StrategyPlanner.evaluate(robot(), finished, Decimal("80"))
        self.assertEqual(StrategyPlanner.WAIT, decision.action)

    def test_manual_clean_closes_open_position(self):
        decision = StrategyPlanner.evaluate(robot(is_clean=True), state(), Decimal("99"))
        self.assertEqual(StrategyPlanner.CLOSE, decision.action)

    def test_investment_modes_match_existing_rules(self):
        self.assertEqual(Decimal("400"), robot(c_type=1).cover_quote_amount(2))
        self.assertEqual(Decimal("100"), robot(c_type=2).cover_quote_amount(2))
        self.assertEqual(Decimal("300"), robot(c_type=3).cover_quote_amount(2))

    def test_configured_order_count_switches_to_doubling(self):
        configured = robot(c_type=2, number=3)
        self.assertEqual(Decimal("100"), configured.cover_quote_amount(2))
        self.assertEqual(Decimal("800"), configured.cover_quote_amount(3))

    def test_runtime_state_round_trip_preserves_decimal_values(self):
        original = state(deal_amount=Decimal("0.123456789"), deal_money=Decimal("123.456789"))
        restored = RuntimeState.from_json(original.to_json())
        self.assertEqual(original, restored)

    def test_user_visible_strategy_text_is_english(self):
        self.assertEqual("Open initial position", user_text("首次建仓"))
        self.assertEqual(
            "USDT available balance is insufficient: required 6.0, available 0.0",
            user_text("USDT 可用余额不足：需要 6.0，可用 0.0"),
        )
        self.assertEqual(
            "Open initial position failed: Exchange API permission denied; enable spot read and trading permissions",
            user_text("首次建仓 执行失败：交易所 API 权限不足，请开启现货读取和现货交易权限"),
        )
        self.assertEqual(
            "Open initial position was rejected by the exchange: "
            "USDT available balance is insufficient: required 6.0, available 0.0",
            user_text("首次建仓 被交易所拒绝：USDT 可用余额不足：需要 6.0，可用 0.0"),
        )
        self.assertEqual(
            "Strategy status: action=Wait; reason=No trading action required",
            user_text("策略状态：动作=等待；原因=暂时没有交易动作"),
        )
        self.assertEqual(
            "Open initial position; average=1862.97 amount=0.0031968 cost=5.961504",
            user_text("首次建仓; average=1862.97 amount=0.0031968 cost=5.961504"),
        )

    def test_invalid_runtime_state_is_not_treated_as_a_position(self):
        self.assertIsNone(RuntimeState.from_json('{"base_price":0}'))
        self.assertIsNone(RuntimeState.from_json('[1,2,3]'))


class OrderIntentTests(unittest.TestCase):
    class FakeRepository:
        def __init__(self, record_error=None):
            self.record_error = record_error
            self.released = False

        def begin_order_intent(self, _robot, _client_order_id, _action):
            return True

        def release_order_intent(self, _robot, _message):
            self.released = True

        def record_buy(self, _robot, _state, _order, _reason):
            if self.record_error:
                raise self.record_error

    class FakeGateway:
        def __init__(self, error=None):
            self.error = error

        def market_buy(self, _robot, _quote_amount, _price, _client_order_id):
            if self.error:
                raise self.error
            return OrderResult("order-1", Decimal("100"), Decimal("1"), Decimal("100"))

    @staticmethod
    def engine(repository, gateway):
        value = object.__new__(TradingEngine)
        value.repository = repository
        value.gateway = gateway
        return value

    def test_definitive_exchange_rejection_releases_intent(self):
        repository = self.FakeRepository()
        engine = self.engine(repository, self.FakeGateway(ccxt.InsufficientFunds("rejected")))
        decision = Decision(StrategyPlanner.OPEN, None, ZERO, "open", Decimal("100"))
        with self.assertRaises(ccxt.InsufficientFunds):
            engine._execute_buy(robot(), None, decision, Decimal("100"))
        self.assertTrue(repository.released)

    def test_database_failure_after_fill_keeps_intent_locked(self):
        repository = self.FakeRepository(RuntimeError("db unavailable"))
        engine = self.engine(repository, self.FakeGateway())
        decision = Decision(StrategyPlanner.OPEN, None, ZERO, "open", Decimal("100"))
        with self.assertRaises(UncertainOrderError):
            engine._execute_buy(robot(), None, decision, Decimal("100"))
        self.assertFalse(repository.released)

    def test_binance_2015_rejection_releases_intent(self):
        repository = self.FakeRepository()
        error = ccxt.DDoSProtection(
            'binance {"code":-2015,"msg":"Invalid API-key, IP, or permissions for action."}'
        )
        engine = self.engine(repository, self.FakeGateway(error))
        decision = Decision(StrategyPlanner.OPEN, None, ZERO, "首次建仓", Decimal("100"))
        with self.assertRaises(ccxt.DDoSProtection):
            engine._execute_buy(robot(), None, decision, Decimal("100"))
        self.assertTrue(repository.released)


class BalanceCheckTests(unittest.TestCase):
    class FakeExchange:
        has = {"createMarketBuyOrderWithCost": True}
        id = "binance"

        def __init__(self):
            self.order_called = False

        def load_markets(self):
            return None

        def fetch_balance(self):
            return {"free": {"USDT": 25}}

        def create_market_buy_order_with_cost(self, *_args, **_kwargs):
            self.order_called = True
            raise AssertionError("余额不足时不应该调用下单接口")

        def close(self):
            return None

    def test_insufficient_balance_skips_exchange_order(self):
        exchange = self.FakeExchange()
        gateway = ExchangeGateway()
        gateway.create_exchange = lambda _robot: exchange

        with self.assertRaises(InsufficientBalanceError) as raised:
            gateway.market_buy(robot(), Decimal("100"), Decimal("10"), "client-1")

        self.assertEqual(Decimal("100"), raised.exception.required)
        self.assertEqual(Decimal("25"), raised.exception.available)
        self.assertFalse(exchange.order_called)


class UserIsolationTests(unittest.TestCase):
    class FakeRepository:
        @staticmethod
        def cleanup_error_logs():
            return 0

        @staticmethod
        def load_active_robots(_shard_index, _shard_count):
            return [{"id": 1}, {"id": 2}]

    def test_one_robot_failure_does_not_stop_other_robots(self):
        engine = object.__new__(TradingEngine)
        engine.config = SimpleNamespace(workers=2, poll_seconds=1)
        engine.repository = self.FakeRepository()
        engine.running = True
        engine.live = False
        processed = []

        def process(row):
            processed.append(row["id"])
            if row["id"] == 1:
                raise RuntimeError("模拟单用户异常")

        engine.process_robot = process
        engine.run(0, 1, once=True)
        self.assertCountEqual([1, 2], processed)

    def test_binance_permission_error_is_translated(self):
        error = ccxt.DDoSProtection(
            'binance {"code":-2015,"msg":"Invalid API-key, IP, or permissions for action."}'
        )
        message = TradingEngine._exchange_error_message(error)
        self.assertIn("服务器 IP 不在白名单", message)
        self.assertIn("错误码 -2015", message)

    def test_repeated_robot_status_is_logged_only_once(self):
        engine = object.__new__(TradingEngine)
        engine._status_lock = threading.Lock()
        engine._robot_status = {}
        configured = robot()

        with self.assertLogs("lhqb.trading", level="WARNING") as captured:
            first = engine._log_robot_status(configured, "balance:0", 30, "余额不足")
            repeated = engine._log_robot_status(configured, "balance:0", 30, "余额不足")
            changed = engine._log_robot_status(configured, "balance:10", 30, "余额发生变化")

        self.assertTrue(first)
        self.assertFalse(repeated)
        self.assertTrue(changed)
        self.assertEqual(2, len(captured.output))


if __name__ == "__main__":
    unittest.main()
