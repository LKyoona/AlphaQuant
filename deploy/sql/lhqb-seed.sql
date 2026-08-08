SET NAMES utf8mb4;

DELETE FROM `jl_rate` WHERE `currency_symbol` IN ('USD', 'USDT', 'CNY');
DELETE FROM `jl_ticker` WHERE `exchange_class` = 'binance' AND `market` IN ('BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'SOL/USDT', 'XRP/USDT');
DELETE FROM `jl_ticker` WHERE `exchange_class` = 'kraken' AND `market` IN ('BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'XRP/USDT');
DELETE FROM `jl_ticker` WHERE `exchange_class` = 'coinbase' AND `market` IN ('BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'XRP/USDT');
DELETE FROM `jl_spot_market` WHERE `platform` = 'binance' AND `market` IN ('BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'SOL/USDT', 'XRP/USDT');
DELETE FROM `jl_spot_market` WHERE `platform` = 'kraken' AND `market` IN ('BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'XRP/USDT');
DELETE FROM `jl_spot_market` WHERE `platform` = 'coinbase' AND `market` IN ('BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'XRP/USDT');
DELETE FROM `jl_third_platform` WHERE `platform` IN ('binance', 'okex', 'huobipro', 'kraken', 'coinbase');
DELETE FROM `jl_coin` WHERE `coin_symbol` IN ('BTC', 'ETH', 'USDT', 'BNB', 'SOL', 'XRP');

INSERT INTO `jl_rate` (`currency_symbol`, `currency_symbol_char`, `usd_rate`, `name`)
VALUES
  ('USD', '$', 1.0000, 'US Dollar'),
  ('USDT', 'USDT', 1.0000, 'Tether'),
  ('CNY', '¥', 7.2500, 'Chinese Yuan')
;

INSERT INTO `jl_coin` (`coin_name`, `coin_symbol`, `coin_type`, `img_url`, `cloud_status`, `cloud_default`, `usd_price`, `price_change`, `price_change_week`, `sort`)
VALUES
  ('Bitcoin', 'BTC', 'coin', '', 1, 1, 61000.0000, 1.2500, 3.2000, 100),
  ('Ethereum', 'ETH', 'coin', '', 1, 1, 3400.0000, 0.8500, 2.1000, 90),
  ('Tether', 'USDT', 'token', '', 1, 1, 1.0000, 0.0000, 0.0000, 80),
  ('BNB', 'BNB', 'coin', '', 1, 1, 580.0000, 0.6500, 1.9000, 70),
  ('Solana', 'SOL', 'coin', '', 1, 1, 140.0000, 1.6000, 4.0000, 60),
  ('XRP', 'XRP', 'coin', '', 1, 1, 0.5200, -0.3000, 1.1000, 50)
;

INSERT INTO `jl_third_platform` (`platform`, `class`)
VALUES
  ('binance', 'binance'),
  ('okex', 'okex'),
  ('huobipro', 'huobipro'),
  ('kraken', 'kraken'),
  ('coinbase', 'coinbase')
;

INSERT INTO `jl_spot_market` (`platform`, `market_name`, `market`, `type`, `stock`, `money`, `min_stock`, `min_money`, `update_time`, `sort`, `status`)
VALUES
  ('binance', 'BTC/USDT', 'BTC/USDT', 1, 'BTC', 'USDT', 0.0001000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 100, 1),
  ('binance', 'ETH/USDT', 'ETH/USDT', 1, 'ETH', 'USDT', 0.0010000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 90, 1),
  ('binance', 'BNB/USDT', 'BNB/USDT', 1, 'BNB', 'USDT', 0.0100000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 80, 1),
  ('binance', 'SOL/USDT', 'SOL/USDT', 1, 'SOL', 'USDT', 0.0100000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 70, 1),
  ('binance', 'XRP/USDT', 'XRP/USDT', 1, 'XRP', 'USDT', 1.0000000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 60, 1),
  ('kraken', 'BTC/USDT', 'BTC/USDT', 1, 'BTC', 'USDT', 0.0000500000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 50, 1),
  ('kraken', 'ETH/USDT', 'ETH/USDT', 1, 'ETH', 'USDT', 0.0010000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 40, 1),
  ('kraken', 'SOL/USDT', 'SOL/USDT', 1, 'SOL', 'USDT', 0.0600000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 30, 1),
  ('kraken', 'XRP/USDT', 'XRP/USDT', 1, 'XRP', 'USDT', 1.6500000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 20, 1)
  ,('coinbase', 'BTC/USDT', 'BTC/USDT', 1, 'BTC', 'USDT', 0.0000500000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 50, 1)
  ,('coinbase', 'ETH/USDT', 'ETH/USDT', 1, 'ETH', 'USDT', 0.0010000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 40, 1)
  ,('coinbase', 'SOL/USDT', 'SOL/USDT', 1, 'SOL', 'USDT', 0.0600000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 30, 1)
  ,('coinbase', 'XRP/USDT', 'XRP/USDT', 1, 'XRP', 'USDT', 1.6500000000000000, 10.0000000000000000, UNIX_TIMESTAMP(), 20, 1)
;

INSERT INTO `jl_ticker` (`exchange_name`, `exchange_class`, `market`, `coin`, `currency`, `volume`, `price`, `change`, `update_time`, `sort`, `default`, `status`)
VALUES
  ('Binance', 'binance', 'BTC/USDT', 'BTC', 'USDT', 123456.0000, 61000.0000, 1.2500, UNIX_TIMESTAMP(), 100, 1, 1),
  ('Binance', 'binance', 'ETH/USDT', 'ETH', 'USDT', 234567.0000, 3400.0000, 0.8500, UNIX_TIMESTAMP(), 90, 1, 1),
  ('Binance', 'binance', 'BNB/USDT', 'BNB', 'USDT', 98765.0000, 580.0000, 0.6500, UNIX_TIMESTAMP(), 80, 1, 1),
  ('Binance', 'binance', 'SOL/USDT', 'SOL', 'USDT', 87654.0000, 140.0000, 1.6000, UNIX_TIMESTAMP(), 70, 1, 1),
  ('Binance', 'binance', 'XRP/USDT', 'XRP', 'USDT', 76543.0000, 0.5200, -0.3000, UNIX_TIMESTAMP(), 60, 1, 1),
  ('Kraken', 'kraken', 'BTC/USDT', 'BTC', 'USDT', 0.0000, 0.0000, 0.0000, UNIX_TIMESTAMP(), 50, 0, 1),
  ('Kraken', 'kraken', 'ETH/USDT', 'ETH', 'USDT', 0.0000, 0.0000, 0.0000, UNIX_TIMESTAMP(), 40, 0, 1),
  ('Kraken', 'kraken', 'SOL/USDT', 'SOL', 'USDT', 0.0000, 0.0000, 0.0000, UNIX_TIMESTAMP(), 30, 0, 1),
  ('Kraken', 'kraken', 'XRP/USDT', 'XRP', 'USDT', 0.0000, 0.0000, 0.0000, UNIX_TIMESTAMP(), 20, 0, 1)
  ,('Coinbase', 'coinbase', 'BTC/USDT', 'BTC', 'USDT', 0.0000, 0.0000, 0.0000, UNIX_TIMESTAMP(), 50, 0, 1)
  ,('Coinbase', 'coinbase', 'ETH/USDT', 'ETH', 'USDT', 0.0000, 0.0000, 0.0000, UNIX_TIMESTAMP(), 40, 0, 1)
  ,('Coinbase', 'coinbase', 'SOL/USDT', 'SOL', 'USDT', 0.0000, 0.0000, 0.0000, UNIX_TIMESTAMP(), 30, 0, 1)
  ,('Coinbase', 'coinbase', 'XRP/USDT', 'XRP', 'USDT', 0.0000, 0.0000, 0.0000, UNIX_TIMESTAMP(), 20, 0, 1)
;

INSERT INTO `jl_option` (`autoload`, `option_name`, `option_value`)
VALUES
  (1, 'site_info', '{"site_name":"AI Crypto Star","site_seo_title":"AI Crypto Star","site_seo_keywords":"AI,Crypto,Quant","site_seo_description":"AI Crypto Star"}'),
  (1, 'app_config', '{"close_reg":"0","must_invite_code":"0","quant_system_rate":"0","quant_level1_rate":"0","quant_level2_rate":"0","quant_start_rate":"0","quant_revenue_rate":"0","cdkey_price":"0","ios_download_url":"","android_download_url":""}'),
  (1, 'email_template_verification_code', '{"subject":"AI Crypto Star 验证码","template":"您的验证码是：{$code}"}')
ON DUPLICATE KEY UPDATE
  `autoload` = VALUES(`autoload`),
  `option_value` = VALUES(`option_value`);
