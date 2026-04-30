-- ============================================================
-- GoldApp — Country / Currency / Religion Settings Table
-- Run this SQL in phpMyAdmin or MySQL on your `demo` database
-- ============================================================

CREATE TABLE IF NOT EXISTS `country_currency_config` (
    `id`              TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `country_code`    VARCHAR(5)    NOT NULL DEFAULT 'IN'               COMMENT 'ISO 3166-1 alpha-2 country code',
    `country_name`    VARCHAR(100)  NOT NULL DEFAULT 'India',
    `religion`        VARCHAR(50)   NOT NULL DEFAULT 'Hindu'            COMMENT 'Hindu | Islamic | Christian | Buddhist | Sikh | Jain | Jewish | Others',
    `currency_code`   VARCHAR(10)   NOT NULL DEFAULT 'INR'             COMMENT 'ISO 4217 currency code',
    `currency_symbol` VARCHAR(20)   NOT NULL DEFAULT '₹'               COMMENT 'Display symbol e.g. ₹ $ £ د.إ',
    `currency_name`   VARCHAR(100)  NOT NULL DEFAULT 'Indian Rupee',
    `exchange_rate`   DECIMAL(15,6) NOT NULL DEFAULT 1.000000          COMMENT '1 unit of this currency = exchange_rate base_currency units',
    `base_currency`   VARCHAR(10)   NOT NULL DEFAULT 'INR'             COMMENT 'Home/base currency amounts are stored in',
    `date_format`     VARCHAR(20)   NOT NULL DEFAULT 'DD/MM/YYYY'      COMMENT 'DD/MM/YYYY | MM/DD/YYYY | YYYY-MM-DD | DD-MM-YYYY | DD MMM YYYY',
    `calendar_type`   VARCHAR(20)   NOT NULL DEFAULT 'gregorian'       COMMENT 'gregorian | hijri | both | hebrew',
    `weight_unit`     VARCHAR(20)   NOT NULL DEFAULT 'gram'            COMMENT 'gram | tola | troy_oz | masha | baht | chi',
    `purity_system`   VARCHAR(20)   NOT NULL DEFAULT 'millesimal'      COMMENT 'millesimal | karat | percentage',
    `decimal_places`  TINYINT UNSIGNED NOT NULL DEFAULT 2              COMMENT 'Decimal places for currency amounts (0-4)',
    `tax_label`       VARCHAR(20)   NOT NULL DEFAULT 'GST'             COMMENT 'GST | VAT | SST | Tax — shown on bills',
    `number_format`   VARCHAR(20)   NOT NULL DEFAULT 'indian'          COMMENT 'indian (1,00,000) | western (100,000)',
    `rtl`             TINYINT(1)    NOT NULL DEFAULT 0                 COMMENT '0=LTR, 1=RTL (Arabic/Hebrew)',
    `working_days`    VARCHAR(100)  NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat' COMMENT 'Comma-separated working day names',
    `updated_at`      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Single-row table storing country, currency and religion settings for GoldApp';

-- ============================================================
-- Default row — India / INR
-- (INSERT IGNORE skips if row already exists)
-- ============================================================
INSERT IGNORE INTO `country_currency_config`
  (`id`, `country_code`, `country_name`, `religion`,
   `currency_code`, `currency_symbol`, `currency_name`,
   `exchange_rate`, `base_currency`,
   `date_format`, `calendar_type`,
   `weight_unit`, `purity_system`, `decimal_places`,
   `tax_label`, `number_format`, `rtl`, `working_days`)
VALUES
  (1, 'IN', 'India', 'Hindu',
   'INR', '₹', 'Indian Rupee',
   1.000000, 'INR',
   'DD/MM/YYYY', 'gregorian',
   'gram', 'millesimal', 2,
   'GST', 'indian', 0, 'Mon,Tue,Wed,Thu,Fri,Sat');

-- ============================================================
-- To check the current settings:
--   SELECT * FROM country_currency_config;
--
-- To reset to India defaults:
--   UPDATE country_currency_config SET
--     country_code='IN', country_name='India', religion='Hindu',
--     currency_code='INR', currency_symbol='₹', currency_name='Indian Rupee',
--     exchange_rate=1.0, base_currency='INR',
--     date_format='DD/MM/YYYY', calendar_type='gregorian',
--     weight_unit='gram', purity_system='millesimal', decimal_places=2,
--     tax_label='GST', number_format='indian', rtl=0,
--     working_days='Mon,Tue,Wed,Thu,Fri,Sat'
--   WHERE id=1;
-- ============================================================
