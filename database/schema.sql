-- =====================================================================
-- SasthoBazar - Multi-vendor healthcare e-commerce + business management
-- Database schema (MySQL 5.7+/MariaDB 10.4+), InnoDB, utf8mb4
-- Money columns: DECIMAL(12,2). Times stored in Asia/Dhaka (+06:00).
-- Run order: schema.sql -> seed.sql   (the /install page does this)
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+06:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. ACCESS CONTROL
-- ---------------------------------------------------------------------

CREATE TABLE `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `name_bn`     VARCHAR(100) NULL,
  `slug`        VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `is_system`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'system roles cannot be deleted',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module`     VARCHAR(50)  NOT NULL COMMENT 'e.g. products',
  `action`     VARCHAR(50)  NOT NULL COMMENT 'e.g. view, create, edit, delete',
  `key`        VARCHAR(100) NOT NULL COMMENT 'module.action',
  `label_en`   VARCHAR(150) NOT NULL,
  `label_bn`   VARCHAR(150) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_key` (`key`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `idx_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id`           INT UNSIGNED NULL,
  `user_type`         ENUM('super_admin','employee','vendor','customer') NOT NULL DEFAULT 'customer',
  `name`              VARCHAR(150) NOT NULL,
  `email`             VARCHAR(191) NULL,
  `phone`             VARCHAR(20)  NULL,
  `password_hash`     VARCHAR(255) NOT NULL,
  `avatar`            VARCHAR(255) NULL,
  `gender`            ENUM('male','female','other') NULL,
  `date_of_birth`     DATE NULL,
  `lang`              ENUM('bn','en') NOT NULL DEFAULT 'bn',
  `status`            ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
  `email_verified_at` DATETIME NULL,
  `phone_verified_at` DATETIME NULL,
  `failed_logins`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`      DATETIME NULL,
  `last_login_at`     DATETIME NULL,
  `last_login_ip`     VARCHAR(45) NULL,
  `remember_token`    VARCHAR(100) NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  KEY `idx_users_type_status` (`user_type`, `status`),
  KEY `idx_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. LOCATIONS & SHIPPING ZONES
-- ---------------------------------------------------------------------

CREATE TABLE `shipping_zones` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `name_bn`     VARCHAR(100) NULL,
  `description` VARCHAR(255) NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Division > District > Upazila (> Area/Union later). District carries the shipping zone.
CREATE TABLE `locations` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`        INT UNSIGNED NULL,
  `type`             ENUM('division','district','upazila','area') NOT NULL,
  `name_en`          VARCHAR(100) NOT NULL,
  `name_bn`          VARCHAR(100) NULL,
  `postcode`         VARCHAR(10)  NULL,
  `shipping_zone_id` INT UNSIGNED NULL,
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_locations_parent` (`parent_id`),
  KEY `idx_locations_type` (`type`, `is_active`),
  KEY `idx_locations_zone` (`shipping_zone_id`),
  CONSTRAINT `fk_locations_parent` FOREIGN KEY (`parent_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_locations_zone` FOREIGN KEY (`shipping_zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shipping_rates` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `zone_id`                 INT UNSIGNED NOT NULL,
  `name`                    VARCHAR(100) NOT NULL COMMENT 'e.g. Standard, Express',
  `name_bn`                 VARCHAR(100) NULL,
  `min_weight_grams`        INT UNSIGNED NOT NULL DEFAULT 0,
  `max_weight_grams`        INT UNSIGNED NULL,
  `min_order_amount`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `rate`                    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `extra_per_kg`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `free_shipping_threshold` DECIMAL(12,2) NULL,
  `cod_charge_percent`      DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `est_days_min`            TINYINT UNSIGNED NULL,
  `est_days_max`            TINYINT UNSIGNED NULL,
  `is_active`               TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shipping_rates_zone` (`zone_id`, `is_active`),
  CONSTRAINT `fk_shipping_rates_zone` FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `addresses` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `label`          VARCHAR(50)  NULL COMMENT 'Home, Office',
  `recipient_name` VARCHAR(150) NOT NULL,
  `phone`          VARCHAR(20)  NOT NULL,
  `alt_phone`      VARCHAR(20)  NULL,
  `division_id`    INT UNSIGNED NULL,
  `district_id`    INT UNSIGNED NULL,
  `upazila_id`     INT UNSIGNED NULL,
  `address_line`   VARCHAR(255) NOT NULL,
  `postcode`       VARCHAR(10)  NULL,
  `is_default`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_addresses_user` (`user_id`, `is_default`),
  CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_addresses_division` FOREIGN KEY (`division_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_addresses_district` FOREIGN KEY (`district_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_addresses_upazila` FOREIGN KEY (`upazila_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. VENDORS, WALLETS, PAYOUTS
-- ---------------------------------------------------------------------

CREATE TABLE `vendors` (
  `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`                 BIGINT UNSIGNED NOT NULL,
  `shop_name`               VARCHAR(150) NOT NULL,
  `shop_name_bn`            VARCHAR(150) NULL,
  `slug`                    VARCHAR(180) NOT NULL,
  `vendor_type`             ENUM('pharmacy','distributor','brand','retailer') NOT NULL DEFAULT 'pharmacy',
  `logo`                    VARCHAR(255) NULL,
  `banner`                  VARCHAR(255) NULL,
  `description`             TEXT NULL,
  `contact_person`          VARCHAR(150) NULL,
  `phone`                   VARCHAR(20)  NOT NULL,
  `email`                   VARCHAR(191) NULL,
  `address`                 VARCHAR(255) NULL,
  `location_id`             INT UNSIGNED NULL COMMENT 'district/upazila',
  `trade_license_no`        VARCHAR(100) NULL,
  `drug_license_no`         VARCHAR(100) NULL COMMENT 'DGDA licence, required for medicine sellers',
  `drug_license_expiry`     DATE NULL,
  `tin_no`                  VARCHAR(50)  NULL,
  `bin_no`                  VARCHAR(50)  NULL,
  `nid_no`                  VARCHAR(30)  NULL,
  `documents`               JSON NULL COMMENT 'uploaded licence file paths',
  `commission_rate`         DECIMAL(5,2) NULL COMMENT 'NULL = use category/global rate',
  `bank_name`               VARCHAR(100) NULL,
  `bank_branch`             VARCHAR(100) NULL,
  `bank_account_name`       VARCHAR(150) NULL,
  `bank_account_no`         VARCHAR(50)  NULL,
  `bank_routing_no`         VARCHAR(20)  NULL,
  `mobile_banking_provider` ENUM('bkash','nagad','rocket','upay') NULL,
  `mobile_banking_no`       VARCHAR(20)  NULL,
  `status`                  ENUM('pending','approved','suspended','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason`        VARCHAR(255) NULL,
  `approved_by`             BIGINT UNSIGNED NULL,
  `approved_at`             DATETIME NULL,
  `rating_avg`              DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `rating_count`            INT UNSIGNED NOT NULL DEFAULT 0,
  `is_featured`             TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`              DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vendors_user` (`user_id`),
  UNIQUE KEY `uq_vendors_slug` (`slug`),
  KEY `idx_vendors_status` (`status`),
  CONSTRAINT `fk_vendors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_vendors_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vendors_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_vendors_commission` CHECK (`commission_rate` IS NULL OR (`commission_rate` >= 0 AND `commission_rate` <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vendor_wallets` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id`       BIGINT UNSIGNED NOT NULL,
  `balance`         DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'withdrawable',
  `pending_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'earned, inside hold period',
  `total_earned`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_withdrawn` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vendor_wallets_vendor` (`vendor_id`),
  CONSTRAINT `fk_vendor_wallets_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ledger: every wallet change is recorded (credit +, debit -)
CREATE TABLE `vendor_wallet_transactions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `wallet_id`      BIGINT UNSIGNED NOT NULL,
  `vendor_id`      BIGINT UNSIGNED NOT NULL,
  `type`           ENUM('order_earning','commission','refund','payout','adjustment','release') NOT NULL,
  `amount`         DECIMAL(12,2) NOT NULL,
  `balance_after`  DECIMAL(12,2) NOT NULL,
  `reference_type` VARCHAR(50) NULL,
  `reference_id`   BIGINT UNSIGNED NULL,
  `note`           VARCHAR(255) NULL,
  `created_by`     BIGINT UNSIGNED NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vwt_vendor` (`vendor_id`, `created_at`),
  KEY `idx_vwt_ref` (`reference_type`, `reference_id`),
  CONSTRAINT `fk_vwt_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `vendor_wallets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vwt_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vwt_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vendor_payouts` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id`       BIGINT UNSIGNED NOT NULL,
  `amount`          DECIMAL(12,2) NOT NULL,
  `fee`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `method`          ENUM('bank','bkash','nagad','rocket','cash') NOT NULL DEFAULT 'bank',
  `account_details` VARCHAR(255) NULL,
  `status`          ENUM('requested','approved','paid','rejected') NOT NULL DEFAULT 'requested',
  `reference_no`    VARCHAR(100) NULL COMMENT 'bank/MFS transaction id',
  `vendor_note`     VARCHAR(255) NULL,
  `admin_note`      VARCHAR(255) NULL,
  `requested_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_by`    BIGINT UNSIGNED NULL,
  `processed_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vendor_payouts_vendor` (`vendor_id`, `status`),
  KEY `idx_vendor_payouts_status` (`status`),
  CONSTRAINT `fk_vendor_payouts_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_vendor_payouts_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_vendor_payouts_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. CATALOG
-- ---------------------------------------------------------------------

CREATE TABLE `categories` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`             INT UNSIGNED NULL,
  `name_en`               VARCHAR(150) NOT NULL,
  `name_bn`               VARCHAR(150) NULL,
  `slug`                  VARCHAR(180) NOT NULL,
  `icon`                  VARCHAR(100) NULL,
  `image`                 VARCHAR(255) NULL,
  `description`           TEXT NULL,
  `commission_rate`       DECIMAL(5,2) NULL COMMENT 'NULL = use global rate',
  `requires_prescription` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`            INT NOT NULL DEFAULT 0,
  `is_featured`           TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`             TINYINT(1) NOT NULL DEFAULT 1,
  `meta_title`            VARCHAR(255) NULL,
  `meta_description`      VARCHAR(500) NULL,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_parent` (`parent_id`, `sort_order`),
  KEY `idx_categories_active` (`is_active`, `is_featured`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_categories_commission` CHECK (`commission_rate` IS NULL OR (`commission_rate` >= 0 AND `commission_rate` <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `brands` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(150) NOT NULL,
  `name_bn`         VARCHAR(150) NULL,
  `slug`            VARCHAR(180) NOT NULL,
  `logo`            VARCHAR(255) NULL,
  `is_manufacturer` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'pharma company (Square, Beximco...)',
  `country`         VARCHAR(80) NULL,
  `is_featured`     TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brands_slug` (`slug`),
  KEY `idx_brands_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id`             BIGINT UNSIGNED NULL COMMENT 'NULL = sold by SasthoBazar (in-house)',
  `category_id`           INT UNSIGNED NULL,
  `brand_id`              INT UNSIGNED NULL,
  `product_type`          ENUM('medicine','otc','device','general') NOT NULL DEFAULT 'general',
  `name_en`               VARCHAR(255) NOT NULL,
  `name_bn`               VARCHAR(255) NULL,
  `slug`                  VARCHAR(280) NOT NULL,
  `sku`                   VARCHAR(100) NULL,
  `barcode`               VARCHAR(100) NULL,
  `generic_name`          VARCHAR(255) NULL COMMENT 'medicine: e.g. Paracetamol',
  `strength`              VARCHAR(100) NULL COMMENT 'e.g. 500 mg',
  `dosage_form`           VARCHAR(100) NULL COMMENT 'Tablet, Syrup, Capsule',
  `pack_size`             VARCHAR(100) NULL COMMENT 'e.g. 10x10',
  `unit`                  VARCHAR(30)  NOT NULL DEFAULT 'pcs',
  `short_description`     VARCHAR(500) NULL,
  `description`           MEDIUMTEXT NULL,
  `indications`           TEXT NULL,
  `side_effects`          TEXT NULL,
  `mrp`                   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `price`                 DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'selling price',
  `cost_price`            DECIMAL(12,2) NULL,
  `discount_type`         ENUM('none','percent','fixed') NOT NULL DEFAULT 'none',
  `discount_value`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vat_percent`           DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `commission_rate`       DECIMAL(5,2) NULL COMMENT 'NULL = vendor/category/global rate',
  `requires_prescription` TINYINT(1) NOT NULL DEFAULT 0,
  `has_variants`          TINYINT(1) NOT NULL DEFAULT 0,
  `track_stock`           TINYINT(1) NOT NULL DEFAULT 1,
  `stock_qty`             INT NOT NULL DEFAULT 0 COMMENT 'cached sum of available batches',
  `low_stock_threshold`   INT UNSIGNED NOT NULL DEFAULT 10,
  `min_order_qty`         INT UNSIGNED NOT NULL DEFAULT 1,
  `max_order_qty`         INT UNSIGNED NULL,
  `weight_grams`          INT UNSIGNED NOT NULL DEFAULT 0,
  `status`                ENUM('draft','pending','active','inactive','rejected') NOT NULL DEFAULT 'draft',
  `rejection_reason`      VARCHAR(255) NULL,
  `is_featured`           TINYINT(1) NOT NULL DEFAULT 0,
  `views`                 INT UNSIGNED NOT NULL DEFAULT 0,
  `sold_count`            INT UNSIGNED NOT NULL DEFAULT 0,
  `rating_avg`            DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `rating_count`          INT UNSIGNED NOT NULL DEFAULT 0,
  `meta_title`            VARCHAR(255) NULL,
  `meta_description`      VARCHAR(500) NULL,
  `created_by`            BIGINT UNSIGNED NULL,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`            DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  UNIQUE KEY `uq_products_vendor_sku` (`vendor_id`, `sku`),
  KEY `idx_products_category` (`category_id`, `status`),
  KEY `idx_products_brand` (`brand_id`),
  KEY `idx_products_vendor_status` (`vendor_id`, `status`),
  KEY `idx_products_status_featured` (`status`, `is_featured`),
  KEY `idx_products_generic` (`generic_name`(100)),
  KEY `idx_products_barcode` (`barcode`),
  FULLTEXT KEY `ft_products_search` (`name_en`, `name_bn`, `generic_name`),
  CONSTRAINT `fk_products_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_products_price` CHECK (`price` >= 0 AND `mrp` >= 0),
  CONSTRAINT `chk_products_commission` CHECK (`commission_rate` IS NULL OR (`commission_rate` >= 0 AND `commission_rate` <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_images` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `path`       VARCHAR(255) NOT NULL,
  `alt`        VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_images_product` (`product_id`, `sort_order`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- e.g. "1 Strip (10 tablets)" vs "1 Box (10 strips)"
CREATE TABLE `product_variants` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`      BIGINT UNSIGNED NOT NULL,
  `name_en`         VARCHAR(150) NOT NULL,
  `name_bn`         VARCHAR(150) NULL,
  `sku`             VARCHAR(100) NULL,
  `barcode`         VARCHAR(100) NULL,
  `unit_multiplier` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'base units in this variant',
  `mrp`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `price`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cost_price`      DECIMAL(12,2) NULL,
  `stock_qty`       INT NOT NULL DEFAULT 0,
  `weight_grams`    INT UNSIGNED NOT NULL DEFAULT 0,
  `is_default`      TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_variants_sku` (`product_id`, `sku`),
  KEY `idx_product_variants_product` (`product_id`, `is_active`),
  CONSTRAINT `fk_product_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_product_variants_price` CHECK (`price` >= 0 AND `mrp` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. INVENTORY (batch / expiry tracking, FEFO)
-- ---------------------------------------------------------------------

CREATE TABLE `inventory_batches` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`         BIGINT UNSIGNED NOT NULL,
  `variant_id`         BIGINT UNSIGNED NULL,
  `vendor_id`          BIGINT UNSIGNED NULL,
  `batch_no`           VARCHAR(100) NOT NULL,
  `manufacture_date`   DATE NULL,
  `expiry_date`        DATE NULL,
  `quantity_received`  INT UNSIGNED NOT NULL DEFAULT 0,
  `quantity_available` INT NOT NULL DEFAULT 0,
  `cost_price`         DECIMAL(12,2) NULL,
  `supplier_name`      VARCHAR(150) NULL,
  `invoice_no`         VARCHAR(100) NULL,
  `received_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `received_by`        BIGINT UNSIGNED NULL,
  `status`             ENUM('active','expired','recalled','depleted') NOT NULL DEFAULT 'active',
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_batches_product_expiry` (`product_id`, `variant_id`, `status`, `expiry_date`),
  KEY `idx_batches_expiry` (`expiry_date`),
  KEY `idx_batches_vendor` (`vendor_id`),
  CONSTRAINT `fk_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_batches_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_batches_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_batches_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_batches_qty` CHECK (`quantity_available` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable stock ledger: +in / -out
CREATE TABLE `stock_movements` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`     BIGINT UNSIGNED NOT NULL,
  `variant_id`     BIGINT UNSIGNED NULL,
  `batch_id`       BIGINT UNSIGNED NULL,
  `vendor_id`      BIGINT UNSIGNED NULL,
  `type`           ENUM('purchase','sale','return','adjustment','damage','expired','transfer','cancel') NOT NULL,
  `quantity`       INT NOT NULL COMMENT 'positive = in, negative = out',
  `balance_after`  INT NOT NULL,
  `unit_cost`      DECIMAL(12,2) NULL,
  `reference_type` VARCHAR(50) NULL COMMENT 'order, refund, adjustment...',
  `reference_id`   BIGINT UNSIGNED NULL,
  `note`           VARCHAR(255) NULL,
  `created_by`     BIGINT UNSIGNED NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stock_mov_product` (`product_id`, `variant_id`, `created_at`),
  KEY `idx_stock_mov_ref` (`reference_type`, `reference_id`),
  KEY `idx_stock_mov_batch` (`batch_id`),
  KEY `idx_stock_mov_vendor` (`vendor_id`, `created_at`),
  CONSTRAINT `fk_stock_mov_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_mov_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stock_mov_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stock_mov_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stock_mov_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. CART, WISHLIST, PRESCRIPTIONS
-- ---------------------------------------------------------------------

-- One row per cart line. Guests use session_id; merged into user_id at login.
CREATE TABLE `carts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NULL,
  `session_id` VARCHAR(128) NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `variant_id` BIGINT UNSIGNED NULL,
  `quantity`   INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_carts_user` (`user_id`),
  KEY `idx_carts_session` (`session_id`),
  KEY `idx_carts_product` (`product_id`),
  CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_carts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_carts_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_carts_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wishlists` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wishlists_user_product` (`user_id`, `product_id`),
  KEY `idx_wishlists_product` (`product_id`),
  CONSTRAINT `fk_wishlists_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wishlists_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Files are stored OUTSIDE the web root (storage/private/prescriptions)
CREATE TABLE `prescriptions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `file_path`     VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NULL,
  `mime_type`     VARCHAR(100) NULL,
  `file_size`     INT UNSIGNED NULL,
  `patient_name`  VARCHAR(150) NULL,
  `patient_age`   TINYINT UNSIGNED NULL,
  `doctor_name`   VARCHAR(150) NULL,
  `doctor_bmdc`   VARCHAR(50)  NULL COMMENT 'BMDC registration no.',
  `issued_date`   DATE NULL,
  `notes`         VARCHAR(500) NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`   BIGINT UNSIGNED NULL COMMENT 'pharmacist',
  `reviewed_at`   DATETIME NULL,
  `review_note`   VARCHAR(500) NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prescriptions_user` (`user_id`),
  KEY `idx_prescriptions_status` (`status`, `created_at`),
  CONSTRAINT `fk_prescriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prescriptions_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. PROMOTIONS
-- ---------------------------------------------------------------------

CREATE TABLE `coupons` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`                 VARCHAR(50) NOT NULL,
  `description`          VARCHAR(255) NULL,
  `type`                 ENUM('percent','fixed','free_shipping') NOT NULL DEFAULT 'percent',
  `value`                DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `max_discount`         DECIMAL(12,2) NULL COMMENT 'cap for percent coupons',
  `min_order_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `usage_limit`          INT UNSIGNED NULL COMMENT 'NULL = unlimited',
  `usage_limit_per_user` INT UNSIGNED NULL DEFAULT 1,
  `used_count`           INT UNSIGNED NOT NULL DEFAULT 0,
  `vendor_id`            BIGINT UNSIGNED NULL COMMENT 'vendor-funded coupon',
  `funded_by`            ENUM('platform','vendor','shared') NOT NULL DEFAULT 'platform',
  `applies_to`           ENUM('all','category','product','vendor','brand') NOT NULL DEFAULT 'all',
  `applies_ids`          JSON NULL,
  `first_order_only`     TINYINT(1) NOT NULL DEFAULT 0,
  `starts_at`            DATETIME NULL,
  `ends_at`              DATETIME NULL,
  `is_active`            TINYINT(1) NOT NULL DEFAULT 1,
  `created_by`           BIGINT UNSIGNED NULL,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupons_code` (`code`),
  KEY `idx_coupons_active_dates` (`is_active`, `starts_at`, `ends_at`),
  CONSTRAINT `fk_coupons_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_coupons_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `offers` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title_en`    VARCHAR(200) NOT NULL,
  `title_bn`    VARCHAR(200) NULL,
  `slug`        VARCHAR(220) NOT NULL,
  `description` TEXT NULL,
  `type`        ENUM('percent','fixed','bogo','bundle') NOT NULL DEFAULT 'percent',
  `value`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `max_discount` DECIMAL(12,2) NULL,
  `applies_to`  ENUM('all','category','product','vendor','brand') NOT NULL DEFAULT 'product',
  `applies_ids` JSON NULL,
  `vendor_id`   BIGINT UNSIGNED NULL,
  `banner`      VARCHAR(255) NULL,
  `priority`    INT NOT NULL DEFAULT 0,
  `starts_at`   DATETIME NULL,
  `ends_at`     DATETIME NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_offers_slug` (`slug`),
  KEY `idx_offers_active_dates` (`is_active`, `starts_at`, `ends_at`),
  CONSTRAINT `fk_offers_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `flash_sales` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title_en`   VARCHAR(200) NOT NULL,
  `title_bn`   VARCHAR(200) NULL,
  `banner`     VARCHAR(255) NULL,
  `starts_at`  DATETIME NOT NULL,
  `ends_at`    DATETIME NOT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_flash_sales_dates` (`is_active`, `starts_at`, `ends_at`),
  CONSTRAINT `chk_flash_sales_dates` CHECK (`ends_at` > `starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `flash_sale_items` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `flash_sale_id`  INT UNSIGNED NOT NULL,
  `product_id`     BIGINT UNSIGNED NOT NULL,
  `variant_id`     BIGINT UNSIGNED NULL,
  `sale_price`     DECIMAL(12,2) NOT NULL,
  `quantity_limit` INT UNSIGNED NULL,
  `sold_qty`       INT UNSIGNED NOT NULL DEFAULT 0,
  `per_user_limit` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_flash_item` (`flash_sale_id`, `product_id`, `variant_id`),
  KEY `idx_flash_items_product` (`product_id`),
  CONSTRAINT `fk_flash_items_sale` FOREIGN KEY (`flash_sale_id`) REFERENCES `flash_sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_flash_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_flash_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. COMMISSION RULES
-- Resolution order (most specific wins): product > vendor+category > vendor > category > global (8%)
-- ---------------------------------------------------------------------

CREATE TABLE `commission_rules` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope`       ENUM('global','category','vendor','vendor_category','product') NOT NULL,
  `vendor_id`   BIGINT UNSIGNED NULL,
  `category_id` INT UNSIGNED NULL,
  `product_id`  BIGINT UNSIGNED NULL,
  `rate_type`   ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `rate`        DECIMAL(12,2) NOT NULL COMMENT 'percent (0-100) or fixed BDT per unit',
  `priority`    INT NOT NULL DEFAULT 0,
  `starts_at`   DATETIME NULL,
  `ends_at`     DATETIME NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `note`        VARCHAR(255) NULL,
  `created_by`  BIGINT UNSIGNED NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comm_rules_lookup` (`scope`, `is_active`, `vendor_id`, `category_id`, `product_id`),
  KEY `idx_comm_rules_vendor` (`vendor_id`),
  KEY `idx_comm_rules_category` (`category_id`),
  KEY `idx_comm_rules_product` (`product_id`),
  CONSTRAINT `fk_comm_rules_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comm_rules_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comm_rules_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comm_rules_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_comm_rules_rate` CHECK (`rate` >= 0 AND (`rate_type` = 'fixed' OR `rate` <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. ORDERS
-- One customer order -> one split per vendor (sub-order) -> items.
-- ---------------------------------------------------------------------

CREATE TABLE `orders` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`              VARCHAR(30) NOT NULL,
  `user_id`               BIGINT UNSIGNED NULL,
  `customer_name`         VARCHAR(150) NOT NULL,
  `customer_phone`        VARCHAR(20)  NOT NULL,
  `customer_email`        VARCHAR(191) NULL,
  `shipping_name`         VARCHAR(150) NOT NULL,
  `shipping_phone`        VARCHAR(20)  NOT NULL,
  `shipping_address`      VARCHAR(255) NOT NULL,
  `division_id`           INT UNSIGNED NULL,
  `district_id`           INT UNSIGNED NULL,
  `upazila_id`            INT UNSIGNED NULL,
  `postcode`              VARCHAR(10) NULL,
  `shipping_zone_id`      INT UNSIGNED NULL,
  `shipping_rate_id`      INT UNSIGNED NULL,
  `subtotal`              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_total`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `coupon_id`             INT UNSIGNED NULL,
  `coupon_code`           VARCHAR(50) NULL,
  `coupon_discount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `shipping_total`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cod_charge`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vat_total`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `grand_total`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `refunded_amount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `commission_total`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method`        ENUM('cod','bkash','nagad','rocket','sslcommerz','card','bank','wallet') NOT NULL DEFAULT 'cod',
  `payment_status`        ENUM('unpaid','partial','paid','refunded','partially_refunded','failed') NOT NULL DEFAULT 'unpaid',
  `status`                ENUM('pending','confirmed','processing','packed','shipped','out_for_delivery','delivered','cancelled','returned','failed') NOT NULL DEFAULT 'pending',
  `requires_prescription` TINYINT(1) NOT NULL DEFAULT 0,
  `prescription_id`       BIGINT UNSIGNED NULL,
  `customer_note`         VARCHAR(500) NULL,
  `admin_note`            VARCHAR(500) NULL,
  `source`                ENUM('web','mobile','admin','phone') NOT NULL DEFAULT 'web',
  `ip_address`            VARCHAR(45) NULL,
  `user_agent`            VARCHAR(255) NULL,
  `placed_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at`          DATETIME NULL,
  `delivered_at`          DATETIME NULL,
  `cancelled_at`          DATETIME NULL,
  `cancel_reason`         VARCHAR(255) NULL,
  `created_by`            BIGINT UNSIGNED NULL COMMENT 'staff who created phone/admin orders',
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_order_no` (`order_no`),
  KEY `idx_orders_user` (`user_id`, `placed_at`),
  KEY `idx_orders_status` (`status`, `placed_at`),
  KEY `idx_orders_payment` (`payment_status`, `payment_method`),
  KEY `idx_orders_phone` (`customer_phone`),
  KEY `idx_orders_placed` (`placed_at`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_division` FOREIGN KEY (`division_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_district` FOREIGN KEY (`district_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_upazila` FOREIGN KEY (`upazila_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_zone` FOREIGN KEY (`shipping_zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_rate` FOREIGN KEY (`shipping_rate_id`) REFERENCES `shipping_rates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_orders_totals` CHECK (`grand_total` >= 0 AND `paid_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sub-order per vendor (vendor_id NULL = in-house items)
CREATE TABLE `order_vendor_splits` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`          BIGINT UNSIGNED NOT NULL,
  `vendor_id`         BIGINT UNSIGNED NULL,
  `sub_order_no`      VARCHAR(40) NOT NULL,
  `subtotal`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_total`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `shipping_total`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `grand_total`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `commission_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vendor_earning`    DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'grand_total - commission - vendor-funded discounts',
  `status`            ENUM('pending','confirmed','processing','packed','shipped','out_for_delivery','delivered','cancelled','returned','failed') NOT NULL DEFAULT 'pending',
  `settlement_status` ENUM('unsettled','pending','eligible','settled','reversed') NOT NULL DEFAULT 'unsettled',
  `settled_at`        DATETIME NULL,
  `vendor_note`       VARCHAR(255) NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_splits_sub_order_no` (`sub_order_no`),
  UNIQUE KEY `uq_splits_order_vendor` (`order_id`, `vendor_id`),
  KEY `idx_splits_vendor_status` (`vendor_id`, `status`),
  KEY `idx_splits_settlement` (`settlement_status`),
  CONSTRAINT `fk_splits_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_splits_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`              BIGINT UNSIGNED NOT NULL,
  `split_id`              BIGINT UNSIGNED NOT NULL,
  `product_id`            BIGINT UNSIGNED NULL,
  `variant_id`            BIGINT UNSIGNED NULL,
  `vendor_id`             BIGINT UNSIGNED NULL,
  `product_name`          VARCHAR(255) NOT NULL COMMENT 'snapshot',
  `variant_name`          VARCHAR(150) NULL,
  `sku`                   VARCHAR(100) NULL,
  `quantity`              INT UNSIGNED NOT NULL,
  `mrp`                   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `unit_price`            DECIMAL(12,2) NOT NULL,
  `discount_amount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vat_amount`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `line_total`            DECIMAL(12,2) NOT NULL,
  `unit_cost`             DECIMAL(12,2) NULL COMMENT 'for profit reports',
  `commission_rate`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `commission_type`       ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `commission_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `requires_prescription` TINYINT(1) NOT NULL DEFAULT 0,
  `batch_allocations`     JSON NULL COMMENT '[{batch_id, qty}] FEFO allocation',
  `returned_qty`          INT UNSIGNED NOT NULL DEFAULT 0,
  `status`                ENUM('active','cancelled','returned') NOT NULL DEFAULT 'active',
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_split` (`split_id`),
  KEY `idx_order_items_product` (`product_id`),
  KEY `idx_order_items_vendor` (`vendor_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_split` FOREIGN KEY (`split_id`) REFERENCES `order_vendor_splits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_items_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_order_items_qty` CHECK (`quantity` > 0 AND `returned_qty` <= `quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_status_history` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`        BIGINT UNSIGNED NOT NULL,
  `split_id`        BIGINT UNSIGNED NULL,
  `from_status`     VARCHAR(30) NULL,
  `to_status`       VARCHAR(30) NOT NULL,
  `note`            VARCHAR(500) NULL,
  `notify_customer` TINYINT(1) NOT NULL DEFAULT 0,
  `changed_by`      BIGINT UNSIGNED NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_osh_order` (`order_id`, `created_at`),
  KEY `idx_osh_split` (`split_id`),
  CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_osh_split` FOREIGN KEY (`split_id`) REFERENCES `order_vendor_splits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_osh_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `coupon_usages` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coupon_id`  INT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NULL,
  `order_id`   BIGINT UNSIGNED NOT NULL,
  `discount`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_usage_order` (`coupon_id`, `order_id`),
  KEY `idx_coupon_usage_user` (`coupon_id`, `user_id`),
  CONSTRAINT `fk_coupon_usage_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_coupon_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_coupon_usage_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commission earned by the platform per order item
CREATE TABLE `commissions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`      BIGINT UNSIGNED NOT NULL,
  `split_id`      BIGINT UNSIGNED NOT NULL,
  `order_item_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id`     BIGINT UNSIGNED NULL,
  `rule_id`       INT UNSIGNED NULL,
  `rule_scope`    VARCHAR(30) NULL COMMENT 'which level the rate came from',
  `base_amount`   DECIMAL(12,2) NOT NULL,
  `rate_type`     ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `rate`          DECIMAL(12,2) NOT NULL,
  `amount`        DECIMAL(12,2) NOT NULL,
  `status`        ENUM('pending','earned','reversed','settled') NOT NULL DEFAULT 'pending',
  `settled_at`    DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_commissions_item` (`order_item_id`),
  KEY `idx_commissions_vendor` (`vendor_id`, `status`),
  KEY `idx_commissions_order` (`order_id`),
  KEY `idx_commissions_created` (`created_at`),
  CONSTRAINT `fk_commissions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_commissions_split` FOREIGN KEY (`split_id`) REFERENCES `order_vendor_splits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_commissions_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_commissions_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_commissions_rule` FOREIGN KEY (`rule_id`) REFERENCES `commission_rules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. DELIVERY
-- ---------------------------------------------------------------------

CREATE TABLE `couriers` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                  VARCHAR(100) NOT NULL,
  `code`                  VARCHAR(50)  NOT NULL COMMENT 'pathao, steadfast, redx, paperfly, self',
  `phone`                 VARCHAR(20)  NULL,
  `api_base_url`          VARCHAR(255) NULL,
  `api_key`               VARCHAR(255) NULL,
  `api_secret`            VARCHAR(255) NULL,
  `api_config`            JSON NULL,
  `tracking_url_template` VARCHAR(255) NULL COMMENT 'use {tracking_no}',
  `is_active`             TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_couriers_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shipments` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`        BIGINT UNSIGNED NOT NULL,
  `split_id`        BIGINT UNSIGNED NULL,
  `courier_id`      INT UNSIGNED NULL,
  `tracking_no`     VARCHAR(100) NULL,
  `consignment_id`  VARCHAR(100) NULL,
  `status`          ENUM('pending','booked','picked_up','in_transit','out_for_delivery','delivered','partial_delivered','returned','cancelled','lost') NOT NULL DEFAULT 'pending',
  `cod_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `collected_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `delivery_charge` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'charged by courier',
  `weight_grams`    INT UNSIGNED NULL,
  `rider_name`      VARCHAR(150) NULL,
  `rider_phone`     VARCHAR(20)  NULL,
  `api_response`    JSON NULL,
  `note`            VARCHAR(255) NULL,
  `booked_at`       DATETIME NULL,
  `shipped_at`      DATETIME NULL,
  `delivered_at`    DATETIME NULL,
  `created_by`      BIGINT UNSIGNED NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shipments_order` (`order_id`),
  KEY `idx_shipments_split` (`split_id`),
  KEY `idx_shipments_tracking` (`tracking_no`),
  KEY `idx_shipments_status` (`courier_id`, `status`),
  CONSTRAINT `fk_shipments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shipments_split` FOREIGN KEY (`split_id`) REFERENCES `order_vendor_splits` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipments_courier` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. PAYMENTS & REFUNDS
-- ---------------------------------------------------------------------

CREATE TABLE `payments` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`       BIGINT UNSIGNED NOT NULL,
  `user_id`        BIGINT UNSIGNED NULL,
  `method`         ENUM('cod','bkash','nagad','rocket','sslcommerz','card','bank','wallet') NOT NULL,
  `gateway`        VARCHAR(50) NULL,
  `transaction_id` VARCHAR(150) NULL COMMENT 'gateway trx id',
  `gateway_ref`    VARCHAR(150) NULL COMMENT 'our ref / payment id sent to gateway',
  `amount`         DECIMAL(12,2) NOT NULL,
  `fee`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `currency`       CHAR(3) NOT NULL DEFAULT 'BDT',
  `status`         ENUM('initiated','pending','success','failed','cancelled','refunded') NOT NULL DEFAULT 'initiated',
  `payer_account`  VARCHAR(50) NULL COMMENT 'masked bKash/card no',
  `raw_response`   JSON NULL,
  `paid_at`        DATETIME NULL,
  `received_by`    BIGINT UNSIGNED NULL COMMENT 'staff for COD/manual',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_gateway_trx` (`gateway`, `transaction_id`),
  KEY `idx_payments_order` (`order_id`),
  KEY `idx_payments_status` (`status`, `created_at`),
  KEY `idx_payments_ref` (`gateway_ref`),
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_payments_amount` CHECK (`amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `refunds` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `refund_no`      VARCHAR(30) NOT NULL,
  `order_id`       BIGINT UNSIGNED NOT NULL,
  `split_id`       BIGINT UNSIGNED NULL,
  `order_item_id`  BIGINT UNSIGNED NULL,
  `payment_id`     BIGINT UNSIGNED NULL,
  `quantity`       INT UNSIGNED NULL,
  `amount`         DECIMAL(12,2) NOT NULL,
  `reason`         VARCHAR(500) NOT NULL,
  `method`         ENUM('original','bkash','nagad','bank','wallet','cash') NOT NULL DEFAULT 'original',
  `account_details` VARCHAR(255) NULL,
  `restock`        TINYINT(1) NOT NULL DEFAULT 1,
  `status`         ENUM('requested','approved','processed','rejected') NOT NULL DEFAULT 'requested',
  `reference_no`   VARCHAR(100) NULL,
  `admin_note`     VARCHAR(500) NULL,
  `requested_by`   BIGINT UNSIGNED NULL,
  `processed_by`   BIGINT UNSIGNED NULL,
  `processed_at`   DATETIME NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_refunds_no` (`refund_no`),
  KEY `idx_refunds_order` (`order_id`),
  KEY `idx_refunds_status` (`status`, `created_at`),
  CONSTRAINT `fk_refunds_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_refunds_split` FOREIGN KEY (`split_id`) REFERENCES `order_vendor_splits` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_refunds_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_refunds_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_refunds_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_refunds_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. REVIEWS
-- ---------------------------------------------------------------------

CREATE TABLE `reviews` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`           BIGINT UNSIGNED NOT NULL,
  `user_id`              BIGINT UNSIGNED NOT NULL,
  `order_id`             BIGINT UNSIGNED NULL,
  `vendor_id`            BIGINT UNSIGNED NULL,
  `rating`               TINYINT UNSIGNED NOT NULL,
  `title`                VARCHAR(200) NULL,
  `comment`              TEXT NULL,
  `images`               JSON NULL,
  `is_verified_purchase` TINYINT(1) NOT NULL DEFAULT 0,
  `status`               ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `vendor_reply`         TEXT NULL,
  `replied_at`           DATETIME NULL,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reviews_user_product_order` (`user_id`, `product_id`, `order_id`),
  KEY `idx_reviews_product` (`product_id`, `status`),
  KEY `idx_reviews_vendor` (`vendor_id`),
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reviews_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_reviews_rating` CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. CONTENT
-- ---------------------------------------------------------------------

CREATE TABLE `banners` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`        VARCHAR(200) NULL,
  `subtitle`     VARCHAR(255) NULL,
  `image`        VARCHAR(255) NOT NULL,
  `image_mobile` VARCHAR(255) NULL,
  `link_url`     VARCHAR(255) NULL,
  `position`     ENUM('home_slider','home_middle','home_bottom','category_top','sidebar','popup') NOT NULL DEFAULT 'home_slider',
  `lang`         ENUM('all','bn','en') NOT NULL DEFAULT 'all',
  `sort_order`   INT NOT NULL DEFAULT 0,
  `starts_at`    DATETIME NULL,
  `ends_at`      DATETIME NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_banners_position` (`position`, `is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pages` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`             VARCHAR(180) NOT NULL,
  `title_en`         VARCHAR(200) NOT NULL,
  `title_bn`         VARCHAR(200) NULL,
  `content_en`       MEDIUMTEXT NULL,
  `content_bn`       MEDIUMTEXT NULL,
  `meta_title`       VARCHAR(255) NULL,
  `meta_description` VARCHAR(500) NULL,
  `show_in_footer`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`       INT NOT NULL DEFAULT 0,
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `type`       VARCHAR(50) NOT NULL COMMENT 'order_status, low_stock, payout, ticket...',
  `title`      VARCHAR(200) NOT NULL,
  `message`    VARCHAR(1000) NULL,
  `link`       VARCHAR(255) NULL,
  `data`       JSON NULL,
  `channel`    ENUM('database','sms','email','push') NOT NULL DEFAULT 'database',
  `read_at`    DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_unread` (`user_id`, `read_at`, `created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. SUPPORT
-- ---------------------------------------------------------------------

CREATE TABLE `support_tickets` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_no`     VARCHAR(30) NOT NULL,
  `user_id`       BIGINT UNSIGNED NOT NULL COMMENT 'customer or vendor user who opened it',
  `vendor_id`     BIGINT UNSIGNED NULL,
  `order_id`      BIGINT UNSIGNED NULL,
  `subject`       VARCHAR(255) NOT NULL,
  `category`      ENUM('order','payment','delivery','product','refund','prescription','account','vendor','other') NOT NULL DEFAULT 'other',
  `priority`      ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status`        ENUM('open','in_progress','waiting_customer','resolved','closed') NOT NULL DEFAULT 'open',
  `assigned_to`   BIGINT UNSIGNED NULL,
  `last_reply_at` DATETIME NULL,
  `closed_at`     DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tickets_no` (`ticket_no`),
  KEY `idx_tickets_user` (`user_id`),
  KEY `idx_tickets_status` (`status`, `priority`),
  KEY `idx_tickets_assigned` (`assigned_to`, `status`),
  CONSTRAINT `fk_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tickets_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tickets_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tickets_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `support_ticket_replies` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id`   BIGINT UNSIGNED NOT NULL,
  `user_id`     BIGINT UNSIGNED NULL,
  `message`     TEXT NOT NULL,
  `attachments` JSON NULL,
  `is_internal` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'staff-only note',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_replies_ticket` (`ticket_id`, `created_at`),
  CONSTRAINT `fk_ticket_replies_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_replies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 15. SYSTEM
-- ---------------------------------------------------------------------

CREATE TABLE `settings` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group`       VARCHAR(50) NOT NULL DEFAULT 'general',
  `key`         VARCHAR(100) NOT NULL,
  `value`       TEXT NULL,
  `type`        ENUM('string','int','decimal','bool','json','text') NOT NULL DEFAULT 'string',
  `label`       VARCHAR(150) NULL,
  `is_public`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'safe to expose on storefront',
  `updated_by`  BIGINT UNSIGNED NULL,
  `updated_at`  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key`),
  KEY `idx_settings_group` (`group`),
  CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NULL,
  `action`      VARCHAR(100) NOT NULL COMMENT 'login, product.update, order.status ...',
  `entity_type` VARCHAR(50) NULL,
  `entity_id`   BIGINT UNSIGNED NULL,
  `old_values`  JSON NULL,
  `new_values`  JSON NULL,
  `ip_address`  VARCHAR(45) NULL,
  `user_agent`  VARCHAR(255) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`, `created_at`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_action` (`action`, `created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
