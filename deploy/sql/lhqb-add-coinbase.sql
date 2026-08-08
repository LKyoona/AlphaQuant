SET NAMES utf8mb4;

DELETE FROM `jl_ticker`
WHERE `exchange_class` = 'coinbase'
  AND `market` IN ('BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'XRP/USDT');

DELETE FROM `jl_spot_market`
WHERE `platform` = 'coinbase'
  AND `market` IN ('BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'XRP/USDT');

DELETE FROM `jl_third_platform` WHERE `platform` = 'coinbase';

INSERT INTO `jl_third_platform` (`platform`, `class`)
VALUES ('coinbase', 'coinbase');

INSERT INTO `jl_spot_market`
  (`platform`, `market_name`, `market`, `type`, `stock`, `money`, `min_stock`, `min_money`, `update_time`, `sort`, `status`)
VALUES
  ('coinbase', 'BTC/USDT', 'BTC/USDT', 1, 'BTC', 'USDT', 0.0000500000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 50, 1),
  ('coinbase', 'ETH/USDT', 'ETH/USDT', 1, 'ETH', 'USDT', 0.0010000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 40, 1),
  ('coinbase', 'SOL/USDT', 'SOL/USDT', 1, 'SOL', 'USDT', 0.0600000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 30, 1),
  ('coinbase', 'XRP/USDT', 'XRP/USDT', 1, 'XRP', 'USDT', 1.6500000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 20, 1);

INSERT INTO `jl_ticker`
  (`exchange_name`, `exchange_class`, `market`, `coin`, `currency`, `volume`, `price`, `change`, `update_time`, `sort`, `default`, `status`)
VALUES
  ('Coinbase', 'coinbase', 'BTC/USDT', 'BTC', 'USDT', 0.0000, 0.0000, 0.0000, UNIX_TIMESTAMP(), 50, 0, 1),
  ('Coinbase', 'coinbase', 'ETH/USDT', 'ETH', 'USDT', 0.0000, 0.0000, 0.0000, UNIX_TIMESTAMP(), 40, 0, 1),
  ('Coinbase', 'coinbase', 'SOL/USDT', 'SOL', 'USDT', 0.0000, 0.0000, 0.0000, UNIX_TIMESTAMP(), 30, 0, 1),
  ('Coinbase', 'coinbase', 'XRP/USDT', 'XRP', 'USDT', 0.0000, 0.0000, 0.0000, UNIX_TIMESTAMP(), 20, 0, 1);
