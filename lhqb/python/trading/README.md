# 交易引擎说明

`trading_engine.py` 是现货交易的唯一入口。它会从 MySQL 读取机器人配置，再从统一的 Redis 行情服务读取价格。

当前保留了原有的投资模式：

- 倍数模式
- 等额模式
- 差值模式

当 `number` 大于 0 时，策略会在累计完成订单数达到这个值后切换成倍数模式，这和当前 H5 机器人配置保持一致。

## 安全检查

默认模式不会写数据库，也不会真正下单：

```bash
cd /data/lhqb/current/python
/data/lhqb/shared/python/venvs/market/bin/python -m trading.trading_engine --once
```

## 真正下单

只有同时满足下面两个条件，才会真正下单：

1. 在私有运行环境里设置 `TRADING_EXECUTION_ENABLED=true`
2. 启动引擎时加上 `--live`

在开启正式模式之前，必须先检查活跃机器人、API 权限、余额，以及所有待处理的 `order_status` 记录。

配置项名称写在 `trading.env.example` 里。密钥必须保留在服务器上，不能提交到 Git。

## 正式环境常驻服务

正式环境由 systemd 守护常驻进程，默认每 10 秒扫描一轮：

```bash
systemctl status lhqb-trading.service
tail -f /data/lhqb/logs/trading/trading.log
```

私有配置位于 `/data/lhqb/shared/python/trading.env`。停止和恢复交易：

```bash
sed -i 's/^TRADING_EXECUTION_ENABLED=true/TRADING_EXECUTION_ENABLED=false/' /data/lhqb/shared/python/trading.env
sed -i 's/^TRADING_EXECUTION_ENABLED=false/TRADING_EXECUTION_ENABLED=true/' /data/lhqb/shared/python/trading.env
```

## 日志语言

- H5/API 读取的机器人状态和交易记录统一保存为英文，面向英语用户。
- 服务器排障日志固定为中文，位置是 `/data/lhqb/logs/trading/trading.log`。
- 该规则由代码自动处理，不需要设置语言参数。
