# Trading engine

`trading_engine.py` is the only spot trading entry point. It reads robot settings
from MySQL and prices from the unified Redis market service.

The existing investment modes are preserved: multiple, equal, and difference.
When `number` is greater than zero, the strategy switches to doubling from that
completed-order count onward, matching the current H5 robot configuration.

## Safe check

The default mode never writes the database and never places an order:

```bash
cd /data/lhqb/current/python
/data/lhqb/shared/python/venvs/trading/bin/python -m trading.trading_engine --once
```

## Real orders

Real orders require both safeguards below:

1. Set `TRADING_EXECUTION_ENABLED=true` in the private runtime environment.
2. Start the engine with `--live`.

Do not enable live mode until active robots, API permissions, balances, and any
pending `order_status` records have been reviewed.

Configuration names are listed in `trading.env.example`. Secrets must stay on
the server and must not be committed to Git.
