-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 21, 2026 at 10:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sasthobazar`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_categories`
--

CREATE TABLE `account_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `name_bn` varchar(100) DEFAULT NULL,
  `slug` varchar(120) NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_categories`
--

INSERT INTO `account_categories` (`id`, `type`, `name_en`, `name_bn`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'expense', 'Rent', 'ভাড়া', 'rent', 1, 1, 0, '2026-11-21 09:00:22', '2026-11-21 09:18:56'),
(2, 'expense', 'Salaries & wages', 'বেতন ও মজুরি', 'salaries', 1, 1, 1, '2026-11-21 09:00:22', NULL),
(3, 'expense', 'Utilities (electricity, internet)', 'ইউটিলিটি (বিদ্যুৎ, ইন্টারনেট)', 'utilities', 1, 1, 2, '2026-11-21 09:00:22', NULL),
(4, 'expense', 'Marketing & ads', 'মার্কেটিং ও বিজ্ঞাপন', 'marketing', 1, 1, 3, '2026-11-21 09:00:22', NULL),
(5, 'expense', 'Packaging', 'প্যাকেজিং', 'packaging', 1, 1, 4, '2026-11-21 09:00:22', NULL),
(6, 'expense', 'Transport & own delivery', 'পরিবহন ও নিজস্ব ডেলিভারি', 'transport', 1, 1, 5, '2026-11-21 09:00:22', NULL),
(7, 'expense', 'Software & hosting', 'সফটওয়্যার ও হোস্টিং', 'software', 1, 1, 6, '2026-11-21 09:00:22', NULL),
(8, 'expense', 'Office & supplies', 'অফিস ও সরঞ্জাম', 'office', 1, 1, 7, '2026-11-21 09:00:22', NULL),
(9, 'expense', 'Bank & mobile banking charges', 'ব্যাংক ও মোবাইল ব্যাংকিং চার্জ', 'bank-charges', 1, 1, 8, '2026-11-21 09:00:22', NULL),
(10, 'expense', 'Taxes & licences', 'কর ও লাইসেন্স', 'taxes', 1, 1, 9, '2026-11-21 09:00:22', NULL),
(11, 'expense', 'Other expense', 'অন্যান্য খরচ', 'other-expense', 1, 1, 10, '2026-11-21 09:00:22', NULL),
(12, 'income', 'Seller registration / listing fees', 'বিক্রেতা নিবন্ধন / লিস্টিং ফি', 'seller-fees', 1, 1, 11, '2026-11-21 09:00:22', NULL),
(13, 'income', 'Advertising income', 'বিজ্ঞাপন আয়', 'ad-income', 1, 1, 12, '2026-11-21 09:00:22', NULL),
(14, 'income', 'Interest income', 'সুদ আয়', 'interest', 1, 1, 13, '2026-11-21 09:00:22', NULL),
(15, 'income', 'Scrap / other sales', 'স্ক্র্যাপ / অন্যান্য বিক্রি', 'scrap', 1, 1, 14, '2026-11-21 09:00:22', NULL),
(16, 'income', 'Other income', 'অন্যান্য আয়', 'other-income', 1, 1, 15, '2026-11-21 09:00:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `account_entries`
--

CREATE TABLE `account_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entry_no` varchar(30) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `entry_date` date NOT NULL,
  `payment_method` varchar(30) NOT NULL DEFAULT 'cash',
  `reference` varchar(100) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(50) DEFAULT NULL COMMENT 'Home, Office',
  `recipient_name` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `alt_phone` varchar(20) DEFAULT NULL,
  `division_id` int(10) UNSIGNED DEFAULT NULL,
  `district_id` int(10) UNSIGNED DEFAULT NULL,
  `upazila_id` int(10) UNSIGNED DEFAULT NULL,
  `address_line` varchar(255) NOT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `label`, `recipient_name`, `phone`, `alt_phone`, `division_id`, `district_id`, `upazila_id`, `address_line`, `postcode`, `is_default`, `created_at`, `updated_at`) VALUES
(5, 32, 'home', 'Demo Customer', '01700000099', NULL, 6, 147, 1365, 'House 7, Road 2, Dhanmondi', NULL, 1, '2026-11-21 03:28:02', NULL),
(6, 33, 'home', 'Mahbub Sajon', '01798060887', NULL, 4, 135, 1266, 'nadipara', NULL, 1, '2026-11-21 03:32:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL COMMENT 'login, product.update, order.status ...',
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(58, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-20 21:57:12'),
(59, 1, 'logout', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-20 22:01:30'),
(60, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-20 22:01:43'),
(61, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:17:03'),
(62, 1, 'categories.create', 'categories', 21, NULL, '{\"parent_id\":2,\"name_en\":\"Antibiotics\",\"name_bn\":\"????????????????\",\"slug\":\"antibiotics\",\"icon\":null,\"description\":null,\"commission_rate\":null,\"requires_prescription\":1,\"sort_order\":1,\"is_featured\":0,\"is_active\":1,\"meta_title\":null,\"meta_description\":null,\"image\":\"uploads\\/categories\\/2026\\/11\\/fc6f7d5f30b93c3f8194e8e65953b2cb.png\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:17:14'),
(63, 1, 'categories.update', 'categories', 21, '{\"name_bn\":\"????????????????\",\"sort_order\":1}', '{\"name_bn\":\"অ্যান্টিবায়োটিক\",\"sort_order\":0}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:17:36'),
(64, 1, 'brands.create', 'brands', 1, NULL, '{\"name\":\"Square Pharmaceuticals\",\"name_bn\":null,\"slug\":\"square-pharmaceuticals\",\"is_manufacturer\":1,\"country\":\"Bangladesh\",\"website\":\"https:\\/\\/squarepharma.com.bd\",\"description\":null,\"is_featured\":0,\"is_active\":1,\"meta_title\":null,\"meta_description\":null,\"logo\":\"uploads\\/brands\\/2026\\/11\\/baa715ebb56bcb5f5dd27bfbf33422e6.png\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:17:46'),
(65, 1, 'brands.create', 'brands', 2, NULL, '{\"name\":\"Beximco Pharmaceuticals\",\"name_bn\":null,\"slug\":\"beximco-pharmaceuticals\",\"is_manufacturer\":1,\"country\":null,\"website\":null,\"description\":null,\"is_featured\":0,\"is_active\":1,\"meta_title\":null,\"meta_description\":null}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:17:46'),
(66, 1, 'brands.export', 'brands', NULL, NULL, '{\"filters\":{\"q\":\"\",\"type\":\"\",\"status\":\"\"},\"rows\":2}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:17:46'),
(67, 1, 'brands.update', 'brands', 1, '{\"country\":\"Bangladesh\",\"website\":\"https:\\/\\/squarepharma.com.bd\",\"logo\":\"uploads\\/brands\\/2026\\/11\\/baa715ebb56bcb5f5dd27bfbf33422e6.png\"}', '{\"country\":null,\"website\":null,\"logo\":null}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:17:47'),
(68, 1, 'products.create', 'products', 1, NULL, '{\"vendor_id\":null,\"category_id\":3,\"brand_id\":2,\"product_type\":\"otc\",\"name_en\":\"Napa 500 mg Tablet\",\"name_bn\":null,\"slug\":\"napa-500-mg-tablet-500-mg\",\"sku\":\"NAPA-500\",\"barcode\":null,\"generic_name\":\"Paracetamol\",\"strength\":\"500 mg\",\"dosage_form\":\"Tablet\",\"pack_size\":\"10x10\",\"unit\":\"strip\",\"short_description\":null,\"description\":null,\"indications\":null,\"usage_instructions\":\"1-2 tablets every 4-6 hours\",\"side_effects\":null,\"mrp\":\"12\",\"price\":\"11.50\",\"cost_price\":\"10\",\"vat_percent\":\"0\",\"requires_prescription\":0,\"track_stock\":1,\"is_featured\":0,\"min_order_qty\":1,\"max_order_qty\":null,\"low_stock_threshold\":10,\"weight_grams\":20,\"status\":\"active\",\"rejection_reason\":null,\"meta_title\":\"Napa 500\",\"meta_description\":null,\"meta_keywords\":null,\"created_by\":1}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:18:06'),
(69, 1, 'products.variants', 'products', 1, NULL, '{\"added\":2,\"updated\":0,\"removed\":0,\"deactivated\":0}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:18:06'),
(70, 1, 'products.images', 'products', 1, NULL, '{\"added\":[\"uploads\\/products\\/2026\\/11\\/7a7cd3ccb580b9c96d44db7094b44bc6.png\",\"uploads\\/products\\/2026\\/11\\/f536634d45fdd3ef3ec7dbacd18f8733.png\"]}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:18:06'),
(71, 1, 'products.update', 'products', 1, '{\"slug\":\"napa-500-mg-tablet-500-mg\",\"dosage_form\":\"Tablet\",\"pack_size\":\"10x10\",\"usage_instructions\":\"1-2 tablets every 4-6 hours\",\"price\":\"11.50\",\"cost_price\":\"10.00\",\"weight_grams\":20,\"meta_title\":\"Napa 500\"}', '{\"slug\":\"napa-500-mg-tablet\",\"dosage_form\":null,\"pack_size\":null,\"usage_instructions\":null,\"price\":\"12\",\"cost_price\":null,\"weight_grams\":0,\"meta_title\":null}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:18:19'),
(72, 1, 'products.variants', 'products', 1, NULL, '{\"added\":0,\"updated\":1,\"removed\":1,\"deactivated\":0}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:18:19'),
(73, 1, 'products.images', 'products', 1, '{\"removed\":[\"uploads\\/products\\/2026\\/11\\/f536634d45fdd3ef3ec7dbacd18f8733.png\"]}', NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:18:19'),
(74, 1, 'products.import', 'products', NULL, NULL, '{\"file\":\"template.csv\",\"created\":1,\"updated\":1}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:18:42'),
(75, 1, 'products.export', 'products', NULL, NULL, '{\"filters\":{\"sort\":\"newest\",\"per_page\":20},\"rows\":2}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:19:03'),
(76, 1, 'products.import', 'products', NULL, NULL, '{\"file\":\"export.csv\",\"created\":0,\"updated\":2}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:19:03'),
(77, 1, 'products.import', 'products', NULL, NULL, '{\"file\":\"prices.csv\",\"created\":0,\"updated\":2}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:19:22'),
(78, 1, 'products.import', 'products', NULL, NULL, '{\"file\":\"prices2.csv\",\"created\":0,\"updated\":1}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:19:23'),
(79, NULL, 'login', 'users', 8, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:19:36'),
(80, 1, 'products.delete', 'products', 1, '{\"id\":1,\"vendor_id\":null,\"category_id\":3,\"brand_id\":2,\"product_type\":\"otc\",\"name_en\":\"Napa 500 mg Tablet\",\"name_bn\":\"নাপা ৫০০ মি.গ্রা. ট্যাবলেট\",\"slug\":\"napa-500-mg-tablet\",\"sku\":\"NAPA-500\",\"barcode\":null,\"generic_name\":\"Paracetamol\",\"strength\":\"500 mg\",\"dosage_form\":\"Tablet\",\"pack_size\":\"10x10\",\"unit\":\"strip\",\"short_description\":\"Fever and pain relief\",\"description\":null,\"indications\":null,\"usage_instructions\":\"1-2 tablets every 4-6 hours. Max 8 tablets a day.\",\"side_effects\":null,\"mrp\":\"10.00\",\"price\":\"10.00\",\"cost_price\":\"10.00\",\"discount_type\":\"none\",\"discount_value\":\"0.00\",\"vat_percent\":\"0.00\",\"commission_rate\":null,\"requires_prescription\":0,\"has_variants\":1,\"track_stock\":1,\"stock_qty\":0,\"low_stock_threshold\":10,\"min_order_qty\":1,\"max_order_qty\":null,\"weight_grams\":0,\"status\":\"draft\",\"rejection_reason\":null,\"is_featured\":0,\"views\":0,\"sold_count\":0,\"rating_avg\":\"0.00\",\"rating_count\":0,\"meta_title\":null,\"meta_description\":null,\"meta_keywords\":null,\"created_by\":1,\"created_at\":\"2026-11-20 22:18:06\",\"updated_at\":\"2026-11-20 22:19:23\",\"deleted_at\":null}', NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:19:59'),
(81, 1, 'products.delete', 'products', 2, '{\"name_en\":\"Seclo 20 mg Capsule\",\"sku\":\"SECLO-20\",\"slug\":\"seclo-20-mg-capsule\"}', '{\"soft_deleted\":true}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:19:59'),
(82, 1, 'products.create', 'products', 3, NULL, '{\"vendor_id\":null,\"category_id\":2,\"brand_id\":null,\"product_type\":\"medicine\",\"name_en\":\"Seclo 20 mg Capsule\",\"name_bn\":null,\"slug\":\"seclo-20-mg-capsule\",\"sku\":\"SECLO-20\",\"barcode\":null,\"generic_name\":\"Omeprazole\",\"strength\":null,\"dosage_form\":null,\"pack_size\":null,\"unit\":\"strip\",\"short_description\":null,\"description\":null,\"indications\":null,\"usage_instructions\":null,\"side_effects\":null,\"mrp\":\"60\",\"price\":\"60\",\"cost_price\":null,\"vat_percent\":\"0\",\"requires_prescription\":0,\"track_stock\":0,\"is_featured\":0,\"min_order_qty\":1,\"max_order_qty\":null,\"low_stock_threshold\":10,\"weight_grams\":0,\"status\":\"draft\",\"rejection_reason\":null,\"meta_title\":null,\"meta_description\":null,\"meta_keywords\":null,\"created_by\":1}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:20:00'),
(83, 1, 'categories.export', 'categories', NULL, NULL, '{\"rows\":21}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:20:19'),
(84, 1, 'categories.delete', 'categories', 21, '{\"id\":21,\"parent_id\":2,\"name_en\":\"Antibiotics\",\"name_bn\":\"অ্যান্টিবায়োটিক\",\"slug\":\"antibiotics\",\"icon\":null,\"image\":\"uploads\\/categories\\/2026\\/11\\/fc6f7d5f30b93c3f8194e8e65953b2cb.png\",\"description\":null,\"commission_rate\":null,\"requires_prescription\":1,\"sort_order\":0,\"is_featured\":0,\"is_active\":1,\"meta_title\":null,\"meta_description\":null,\"created_at\":\"2026-11-20 22:17:14\",\"updated_at\":\"2026-11-20 22:17:36\"}', NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:20:20'),
(85, 1, 'brands.delete', 'brands', 2, '{\"id\":2,\"name\":\"Beximco Pharmaceuticals\",\"name_bn\":null,\"slug\":\"beximco-pharmaceuticals\",\"logo\":null,\"description\":null,\"is_manufacturer\":1,\"country\":null,\"website\":null,\"is_featured\":0,\"is_active\":1,\"meta_title\":null,\"meta_description\":null,\"created_at\":\"2026-11-20 22:17:46\",\"updated_at\":null}', NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:20:20'),
(86, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:42:30'),
(87, 1, 'suppliers.create', 'suppliers', 1, NULL, '{\"name\":\"Acme Distributors\",\"contact_person\":null,\"phone\":\"01711111111\",\"email\":null,\"address\":null,\"drug_license_no\":null,\"tin_no\":null,\"payment_terms\":\"30 days credit\",\"notes\":null,\"is_active\":1,\"created_by\":1}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:42:31'),
(88, 1, 'inventory.batch_update', 'inventory_batches', 4, '{\"supplier_id\":1,\"invoice_no\":null,\"expiry_date\":\"2028-06-30\"}', '{\"supplier_id\":null,\"invoice_no\":\"INV-77\",\"expiry_date\":\"2028-07-31\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:42:50'),
(89, 1, 'purchase_orders.create', 'purchase_orders', 2, NULL, '{\"supplier_id\":1,\"vendor_id\":null,\"order_date\":\"2026-11-20\",\"expected_date\":\"2026-11-25\",\"subtotal\":2950,\"discount\":10,\"tax\":5,\"total\":2945,\"notes\":null,\"status\":\"draft\",\"created_by\":1,\"po_no\":\"TMP-8dd928adb4b89a8e\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:06'),
(90, 1, 'purchase_orders.ordered', 'purchase_orders', 2, '{\"status\":\"draft\"}', '{\"status\":\"ordered\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:06'),
(91, 1, 'purchase_orders.receive', 'purchase_orders', 2, '{\"status\":\"ordered\"}', '{\"status\":\"partial\",\"grn\":\"GRN-2611-00002\",\"quantity\":100,\"cost\":2190}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:08'),
(92, 1, 'purchase_orders.receive', 'purchase_orders', 2, '{\"status\":\"partial\"}', '{\"status\":\"received\",\"grn\":\"GRN-2611-00003\",\"quantity\":50,\"cost\":800}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:08'),
(93, 1, 'purchase_orders.export', 'purchase_orders', NULL, NULL, '{\"rows\":1}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:10'),
(94, 1, 'suppliers.export', 'suppliers', NULL, NULL, '{\"rows\":1}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:10'),
(95, 1, 'inventory.export', 'inventory_batches', NULL, NULL, '{\"report\":\"batches\",\"rows\":7}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:33'),
(96, 1, 'inventory.export', 'stock_movements', NULL, NULL, '{\"report\":\"movements\",\"filters\":[],\"rows\":13}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:33'),
(97, 1, 'inventory.export', 'inventory_batches', NULL, NULL, '{\"report\":\"valuation\",\"rows\":2}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:33'),
(98, 1, 'inventory.export', 'inventory_batches', NULL, NULL, '{\"report\":\"vendor_wise\",\"rows\":2}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:33'),
(99, 1, 'settings.update', 'settings', NULL, '{\"low_stock_threshold\":\"10\"}', '{\"low_stock_threshold\":\"25\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:34'),
(100, 1, 'settings.update', 'settings', NULL, '{\"expiry_alert_days\":\"90\"}', '{\"expiry_alert_days\":\"60\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:34'),
(101, 1, 'inventory.threshold_all', 'products', NULL, NULL, '{\"low_stock_threshold\":25,\"products\":3}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:34'),
(102, NULL, 'login', 'users', 10, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:53'),
(103, NULL, 'inventory.export', 'stock_movements', NULL, NULL, '{\"report\":\"movements\",\"filters\":[],\"rows\":13}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:44:54'),
(104, 1, 'purchase_orders.create', 'purchase_orders', 3, NULL, '{\"supplier_id\":1,\"vendor_id\":1,\"order_date\":\"2026-11-20\",\"expected_date\":null,\"subtotal\":300,\"discount\":0,\"tax\":0,\"total\":300,\"notes\":null,\"status\":\"draft\",\"created_by\":1,\"po_no\":\"TMP-92e8e0388b993d35\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:45:09'),
(105, 1, 'purchase_orders.update', 'purchase_orders', 3, '{\"subtotal\":\"300.00\",\"total\":\"300.00\"}', '{\"subtotal\":480,\"total\":480}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:45:09'),
(106, 1, 'purchase_orders.ordered', 'purchase_orders', 3, '{\"status\":\"draft\"}', '{\"status\":\"ordered\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:45:09'),
(107, 1, 'purchase_orders.cancelled', 'purchase_orders', 3, '{\"status\":\"ordered\"}', '{\"status\":\"cancelled\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:45:10'),
(108, 1, 'purchase_orders.create', 'purchase_orders', 4, NULL, '{\"supplier_id\":1,\"vendor_id\":null,\"order_date\":\"2026-11-20\",\"expected_date\":null,\"subtotal\":1,\"discount\":0,\"tax\":0,\"total\":1,\"notes\":null,\"status\":\"draft\",\"created_by\":1,\"po_no\":\"TMP-b4a169633025825b\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:45:10'),
(109, 1, 'purchase_orders.delete', 'purchase_orders', 4, '{\"id\":4,\"po_no\":\"PO-2611-00004\",\"supplier_id\":1,\"vendor_id\":null,\"status\":\"draft\",\"order_date\":\"2026-11-20\",\"expected_date\":null,\"subtotal\":\"1.00\",\"discount\":\"0.00\",\"tax\":\"0.00\",\"total\":\"1.00\",\"notes\":null,\"ordered_at\":null,\"closed_at\":null,\"created_by\":1,\"created_at\":\"2026-11-20 22:45:10\",\"updated_at\":\"2026-11-20 22:45:10\"}', NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-20 22:45:10'),
(110, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-20 23:07:06'),
(111, NULL, 'users.create', 'users', 13, NULL, '{\"role_id\":3,\"user_type\":\"customer\",\"name\":\"ZZ Rahim Uddin\",\"phone\":\"01799000002\",\"email\":null,\"password_hash\":\"***\",\"lang\":\"en\",\"status\":\"active\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 01:49:48'),
(128, NULL, 'users.create', 'users', 14, NULL, '{\"role_id\":3,\"user_type\":\"customer\",\"name\":\"Other\",\"phone\":\"01799000004\",\"email\":null,\"password_hash\":\"***\",\"lang\":\"bn\",\"status\":\"active\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 01:51:34'),
(131, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 01:51:51'),
(132, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 01:52:20'),
(133, NULL, 'users.create', 'users', 16, NULL, '{\"role_id\":3,\"user_type\":\"customer\",\"name\":\"ZZ Karim\",\"phone\":\"01799000002\",\"email\":null,\"password_hash\":\"***\",\"lang\":\"en\",\"status\":\"active\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 02:22:16'),
(140, NULL, 'users.create', 'users', 17, NULL, '{\"role_id\":3,\"user_type\":\"customer\",\"name\":\"Other\",\"phone\":\"01799000004\",\"email\":null,\"password_hash\":\"***\",\"lang\":\"bn\",\"status\":\"active\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 02:23:42'),
(145, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 02:24:31'),
(170, NULL, 'users.create', 'users', 20, NULL, '{\"role_id\":3,\"user_type\":\"customer\",\"name\":\"ZZ Dash Customer\",\"phone\":\"01799000002\",\"email\":null,\"password_hash\":\"***\",\"lang\":\"bn\",\"status\":\"active\"}', '127.0.0.1', 'cli', '2026-11-21 02:31:26'),
(175, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 02:31:28'),
(177, 1, 'login_failed', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 02:36:14'),
(178, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 02:37:21'),
(181, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 02:49:02'),
(185, NULL, 'users.create', 'users', 24, NULL, '{\"role_id\":3,\"user_type\":\"customer\",\"name\":\"ZZ Buyer\",\"phone\":\"01799000002\",\"email\":null,\"password_hash\":\"***\",\"lang\":\"bn\",\"status\":\"active\"}', '127.0.0.1', 'cli', '2026-11-21 02:49:07'),
(200, 1, 'vendor_payouts.reject', 'vendor_payouts', 1, '{\"status\":\"requested\"}', '{\"status\":\"rejected\",\"reference\":null}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 02:50:45'),
(202, 1, 'vendor_payouts.approve', 'vendor_payouts', 2, '{\"status\":\"requested\"}', '{\"status\":\"approved\",\"reference\":null}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 02:50:47'),
(203, 1, 'vendor_payouts.paid', 'vendor_payouts', 2, '{\"status\":\"approved\"}', '{\"status\":\"paid\",\"reference\":\"BKX12345\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 02:50:47'),
(214, 1, 'logout', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 02:54:50'),
(215, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 02:57:10'),
(216, NULL, 'users.create', 'users', 27, NULL, '{\"role_id\":3,\"user_type\":\"customer\",\"name\":\"ZZ Shopper\",\"phone\":\"01799000002\",\"email\":null,\"password_hash\":\"***\",\"lang\":\"en\",\"status\":\"active\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 03:14:06'),
(220, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 03:14:27'),
(253, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 03:21:24'),
(254, 1, 'logout', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 03:21:27'),
(256, 1, 'logout', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 03:22:47'),
(257, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 03:24:20'),
(258, 1, 'logout', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 03:26:32'),
(259, NULL, 'users.create', 'users', 32, NULL, '{\"role_id\":3,\"user_type\":\"customer\",\"name\":\"Demo Customer\",\"phone\":\"01700000099\",\"email\":null,\"password_hash\":\"***\",\"lang\":\"bn\",\"status\":\"active\"}', '127.0.0.1', 'cli', '2026-11-21 03:28:02'),
(260, NULL, 'users.create', 'users', 33, NULL, '{\"role_id\":3,\"user_type\":\"customer\",\"name\":\"Mahbub Sajon\",\"phone\":\"01798060887\",\"email\":null,\"password_hash\":\"***\",\"lang\":\"bn\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 03:31:10'),
(261, 33, 'user.register', 'user', 33, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 03:31:10'),
(262, 33, 'login', 'users', 33, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 03:31:10'),
(263, 33, 'orders.create', 'orders', 17, NULL, '{\"order_no\":\"SB26112144603\",\"grand_total\":876.68,\"method\":\"cod\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 03:32:48'),
(264, 33, 'addresses.create', 'addresses', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 03:32:48'),
(265, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 03:34:21'),
(267, 1, 'orders.status', 'orders', 17, '{\"status\":\"pending\"}', '{\"status\":\"confirmed\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 03:39:08'),
(268, 1, 'orders.status', 'orders', 17, '{\"status\":\"confirmed\"}', '{\"status\":\"processing\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 03:39:22'),
(272, 31, 'login', 'users', 31, NULL, NULL, '::1', 'curl/8.19.0', '2026-11-21 08:12:36'),
(273, 32, 'login', 'users', 32, NULL, NULL, '::1', 'curl/8.19.0', '2026-11-21 08:12:37'),
(274, 32, 'login', 'users', 32, NULL, NULL, '::1', 'curl/8.19.0', '2026-11-21 08:16:44'),
(275, 1, 'login_failed', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 08:30:41'),
(276, 1, 'login_failed', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 08:31:26'),
(277, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 08:34:32'),
(278, 1, 'banners.delete', 'banners', 1, '{\"id\":1,\"title\":\"Genuine medicine, at your door\",\"title_bn\":null,\"subtitle\":\"Order from 40+ trusted brands with home delivery\",\"subtitle_bn\":null,\"image\":\"uploads\\/demo\\/b-genuine.svg\",\"image_mobile\":null,\"link_url\":\"\\/shasthobazar\\/products\",\"button_text\":null,\"popup_delay\":3,\"position\":\"home_slider\",\"lang\":\"all\",\"sort_order\":0,\"starts_at\":null,\"ends_at\":null,\"is_active\":1,\"created_at\":\"2026-11-21 03:28:02\",\"updated_at\":\"2026-11-21 03:28:13\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 08:35:11'),
(279, 1, 'banners.delete', 'banners', 2, '{\"id\":2,\"title\":\"Weekly Health Deals\",\"title_bn\":null,\"subtitle\":\"Up to 25% off on devices, vitamins and baby care\",\"subtitle_bn\":null,\"image\":\"uploads\\/demo\\/b-deals.svg\",\"image_mobile\":null,\"link_url\":\"\\/shasthobazar\\/products?sort=discount\",\"button_text\":null,\"popup_delay\":3,\"position\":\"home_slider\",\"lang\":\"all\",\"sort_order\":1,\"starts_at\":null,\"ends_at\":null,\"is_active\":1,\"created_at\":\"2026-11-21 03:28:02\",\"updated_at\":\"2026-11-21 03:28:13\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 08:35:16'),
(280, 1, 'banners.delete', 'banners', 3, '{\"id\":3,\"title\":\"Diabetic care made easy\",\"title_bn\":null,\"subtitle\":\"Glucometers, test strips and medicines in one place\",\"subtitle_bn\":null,\"image\":\"uploads\\/demo\\/b-diabetes.svg\",\"image_mobile\":null,\"link_url\":\"\\/shasthobazar\\/category\\/diabetic-care\",\"button_text\":null,\"popup_delay\":3,\"position\":\"home_slider\",\"lang\":\"all\",\"sort_order\":2,\"starts_at\":null,\"ends_at\":null,\"is_active\":1,\"created_at\":\"2026-11-21 03:28:02\",\"updated_at\":\"2026-11-21 03:28:13\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 08:35:20'),
(284, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 08:43:02'),
(317, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 09:17:28'),
(371, 1, 'orders.split_status', 'order_vendor_splits', 23, '{\"status\":\"processing\"}', '{\"status\":\"shipped\",\"by_vendor\":false}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 09:36:44'),
(372, 1, 'orders.split_status', 'order_vendor_splits', 23, '{\"status\":\"shipped\"}', '{\"status\":\"delivered\",\"by_vendor\":false}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 09:37:29'),
(373, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 12:48:42'),
(374, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 13:10:56'),
(409, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 13:35:42'),
(410, 30, 'login', 'users', 30, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 13:36:01'),
(411, 32, 'login', 'users', 32, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 13:36:03'),
(412, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 13:51:52'),
(413, 1, 'settings.update', 'settings', NULL, '{\"site_tagline\":\"আপনার বিশ্বস্ত অনলাইন ফার্মেসি ও হেলথকেয়ার শপ\"}', '{\"site_tagline\":\"পরীক্ষা: বাংলা লেখা ঠিকমতো সংরক্ষিত হচ্ছে\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 13:51:53'),
(414, 1, 'settings.update', 'settings', NULL, '{\"site_tagline\":\"পরীক্ষা: বাংলা লেখা ঠিকমতো সংরক্ষিত হচ্ছে\"}', '{\"site_tagline\":\"আপনার বিশ্বস্ত অনলাইন ফার্মেসি ও হেলথকেয়ার শপ\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 13:51:54'),
(427, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 14:11:23'),
(428, 1, 'settings.update', 'settings', NULL, '{\"guest_checkout_enabled\":\"1\",\"guest_default_contact\":\"phone\"}', '{\"guest_checkout_enabled\":\"0\",\"guest_default_contact\":\"email\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 14:11:24'),
(429, 1, 'settings.update', 'settings', NULL, '{\"guest_checkout_enabled\":\"0\"}', '{\"guest_checkout_enabled\":\"1\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 14:11:24'),
(430, 1, 'settings.update', 'settings', NULL, '{\"guest_default_contact\":\"email\"}', '{\"guest_default_contact\":\"phone\"}', '127.0.0.1', 'curl/8.19.0', '2026-11-21 14:11:24'),
(447, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 14:34:53'),
(449, 1, 'login', 'users', 1, NULL, NULL, '127.0.0.1', 'curl/8.19.0', '2026-11-21 14:49:54'),
(453, 1, 'vendor_types.update', 'vendor_types', 1, '{\"is_active\":1,\"updated_at\":null}', '{\"is_active\":0,\"updated_at\":\"2026-11-21 15:05:58\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 15:05:58'),
(454, 1, 'vendor_types.toggle', 'vendor_types', 1, '{\"is_active\":1}', '{\"is_active\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 15:05:58'),
(455, 1, 'vendor_types.update', 'vendor_types', 2, '{\"is_active\":1,\"updated_at\":null}', '{\"is_active\":0,\"updated_at\":\"2026-11-21 15:06:01\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 15:06:01'),
(456, 1, 'vendor_types.toggle', 'vendor_types', 2, '{\"is_active\":1}', '{\"is_active\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 15:06:01'),
(457, 1, 'vendor_types.update', 'vendor_types', 3, '{\"is_active\":1,\"updated_at\":null}', '{\"is_active\":0,\"updated_at\":\"2026-11-21 15:06:02\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 15:06:02'),
(458, 1, 'vendor_types.toggle', 'vendor_types', 3, '{\"is_active\":1}', '{\"is_active\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 15:06:02'),
(459, 65, 'vendors.register', 'vendors', 23, NULL, '{\"shop_name\":\"rupok firma\",\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 15:12:41'),
(460, 65, 'login', 'users', 65, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 15:12:41'),
(461, 1, 'vendors.approve', 'vendors', 23, '{\"status\":\"pending\"}', '{\"status\":\"approved\",\"reason\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Avast/156.0.0.0', '2026-11-21 15:13:23');

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `title_bn` varchar(200) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `subtitle_bn` varchar(255) DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `image_mobile` varchar(255) DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `button_text` varchar(60) DEFAULT NULL,
  `popup_delay` smallint(5) UNSIGNED NOT NULL DEFAULT 3,
  `position` enum('home_slider','home_middle','home_bottom','category_top','sidebar','popup') NOT NULL DEFAULT 'home_slider',
  `lang` enum('all','bn','en') NOT NULL DEFAULT 'all',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(190) NOT NULL,
  `title_en` varchar(200) NOT NULL,
  `title_bn` varchar(200) DEFAULT NULL,
  `excerpt_en` varchar(500) DEFAULT NULL,
  `excerpt_bn` varchar(500) DEFAULT NULL,
  `content_en` mediumtext DEFAULT NULL,
  `content_bn` mediumtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `category` varchar(40) NOT NULL DEFAULT 'health_tips',
  `tags` varchar(255) DEFAULT NULL,
  `author_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `views` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `slug`, `title_en`, `title_bn`, `excerpt_en`, `excerpt_bn`, `content_en`, `content_bn`, `image`, `category`, `tags`, `author_id`, `status`, `published_at`, `views`, `meta_title`, `meta_description`, `created_at`, `updated_at`) VALUES
(1, 'stay-hydrated-in-summer', 'Stay hydrated in the summer heat', 'গরমে শরীরে পানির ঘাটতি দূর করুন', 'Simple ways to avoid dehydration during hot Bangladeshi summers.', 'বাংলাদেশের প্রচণ্ড গরমে পানিশূন্যতা এড়ানোর সহজ উপায়।', '<p>Hot and humid days make us lose water and salts quickly. Drink water regularly, even before you feel thirsty.</p><h2>Tips</h2><ul><li>Drink 8-10 glasses of water a day.</li><li>Use oral saline (ORS) after heavy sweating or diarrhoea.</li><li>Eat water-rich fruit such as watermelon and cucumber.</li><li>Avoid long exposure to the midday sun.</li></ul><p>See a doctor if you feel dizzy, confused or stop sweating.</p>', '<p>গরম ও আর্দ্র আবহাওয়ায় শরীর থেকে দ্রুত পানি ও লবণ বেরিয়ে যায়। তৃষ্ণা পাওয়ার আগেই নিয়মিত পানি পান করুন।</p><h2>পরামর্শ</h2><ul><li>দিনে ৮-১০ গ্লাস পানি পান করুন।</li><li>বেশি ঘাম বা ডায়রিয়া হলে খাবার স্যালাইন খান।</li><li>তরমুজ, শসার মতো রসালো ফল খান।</li><li>দুপুরের রোদে বেশিক্ষণ থাকবেন না।</li></ul><p>মাথা ঘোরা, বিভ্রান্তি বা ঘাম বন্ধ হয়ে গেলে ডাক্তার দেখান।</p>', NULL, 'health_tips', NULL, NULL, 'published', '2026-11-21 09:24:28', 1, NULL, NULL, '2026-11-21 09:24:28', NULL),
(2, 'managing-diabetes-daily', 'Managing diabetes every day', 'প্রতিদিন ডায়াবেটিস নিয়ন্ত্রণ', 'Small daily habits that keep blood sugar under control.', 'রক্তে শর্করা নিয়ন্ত্রণে রাখার ছোট ছোট দৈনিক অভ্যাস।', '<p>Diabetes can be managed well with medicine, food and activity.</p><ul><li>Check your blood sugar as advised by your doctor.</li><li>Take medicine at the same time every day.</li><li>Walk at least 30 minutes a day.</li><li>Choose whole grains and vegetables; limit sweets and soft drinks.</li></ul><p>Never change your dose without talking to your doctor.</p>', '<p>ওষুধ, খাবার ও শারীরিক পরিশ্রমের মাধ্যমে ডায়াবেটিস ভালোভাবে নিয়ন্ত্রণ করা যায়।</p><ul><li>ডাক্তারের পরামর্শমতো রক্তের শর্করা পরীক্ষা করুন।</li><li>প্রতিদিন একই সময়ে ওষুধ খান।</li><li>দিনে অন্তত ৩০ মিনিট হাঁটুন।</li><li>লাল চাল-আটা ও সবজি বেছে নিন; মিষ্টি ও কোমল পানীয় কমান।</li></ul><p>ডাক্তারের সঙ্গে কথা না বলে কখনো ডোজ বদলাবেন না।</p>', NULL, 'health_tips', NULL, NULL, 'published', '2026-11-21 09:24:28', 1, NULL, NULL, '2026-11-21 09:24:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `name_bn` varchar(150) DEFAULT NULL,
  `slug` varchar(180) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_manufacturer` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'pharma company (Square, Beximco...)',
  `country` varchar(80) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `name`, `name_bn`, `slug`, `logo`, `description`, `is_manufacturer`, `country`, `website`, `is_featured`, `is_active`, `meta_title`, `meta_description`, `created_at`, `updated_at`) VALUES
(11, 'Beximco Pharma', 'বেক্সিমকো ফার্মা', 'beximco-pharma', NULL, NULL, 1, 'Bangladesh', NULL, 1, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(12, 'Square Pharmaceuticals', 'স্কয়ার ফার্মাসিউটিক্যালস', 'square-pharmaceuticals', NULL, NULL, 1, 'Bangladesh', NULL, 1, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(13, 'Incepta Pharmaceuticals', 'ইনসেপ্টা ফার্মাসিউটিক্যালস', 'incepta-pharmaceuticals', NULL, NULL, 1, 'Bangladesh', NULL, 1, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(14, 'Healthcare Pharmaceuticals', 'হেলথকেয়ার ফার্মাসিউটিক্যালস', 'healthcare-pharmaceuticals', NULL, NULL, 1, 'Bangladesh', NULL, 1, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(15, 'ACME Laboratories', 'একমি ল্যাবরেটরিজ', 'acme-laboratories', NULL, NULL, 1, 'Bangladesh', NULL, 1, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(16, 'Renata Limited', 'রেনাটা লিমিটেড', 'renata-limited', NULL, NULL, 1, 'Bangladesh', NULL, 1, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(17, 'Eskayef Pharmaceuticals', 'এসকেএফ ফার্মাসিউটিক্যালস', 'eskayef-pharmaceuticals', NULL, NULL, 1, 'Bangladesh', NULL, 1, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(18, 'ACI Limited', 'এসিআই লিমিটেড', 'aci-limited', NULL, NULL, 1, 'Bangladesh', NULL, 1, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(19, 'SMC Enterprise', 'এসএমসি এন্টারপ্রাইজ', 'smc-enterprise', NULL, NULL, 1, 'Bangladesh', NULL, 1, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(20, 'Roche (Accu-Chek)', 'রোশ (একু-চেক)', 'roche-accu-chek', NULL, NULL, 0, NULL, NULL, 0, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(21, 'Omron', 'ওমরন', 'omron', NULL, NULL, 0, NULL, NULL, 0, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(22, 'Nestlé', 'নেসলে', 'nestlé', NULL, NULL, 0, NULL, NULL, 0, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(23, 'Johnson\'s Baby', 'জনসন্স বেবি', 'johnson-s-baby', NULL, NULL, 0, NULL, NULL, 0, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(24, 'Unilever', 'ইউনিলিভার', 'unilever', NULL, NULL, 0, NULL, NULL, 0, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(25, 'Haleon', 'হ্যালিওন', 'haleon', NULL, NULL, 0, NULL, NULL, 0, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(26, 'Reckitt', 'রেকিট', 'reckitt', NULL, NULL, 0, NULL, NULL, 0, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(27, 'P&G', 'পিএন্ডজি', 'p-g', NULL, NULL, 0, NULL, NULL, 0, 1, NULL, NULL, '2026-11-21 03:28:00', NULL),
(28, 'Himalaya', 'হিমালয়া', 'himalaya', NULL, NULL, 0, NULL, NULL, 0, 1, NULL, NULL, '2026-11-21 03:28:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `user_id`, `session_id`, `product_id`, `variant_id`, `quantity`, `created_at`, `updated_at`) VALUES
(35, NULL, 'faf6119cb0c574cf3f85a144d91d55389bb3a139', 59, NULL, 1, '2026-11-21 03:50:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `name_en` varchar(150) NOT NULL,
  `name_bn` varchar(150) DEFAULT NULL,
  `slug` varchar(180) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT NULL COMMENT 'NULL = use global rate',
  `requires_prescription` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `name_en`, `name_bn`, `slug`, `icon`, `image`, `description`, `commission_rate`, `requires_prescription`, `sort_order`, `is_featured`, `is_active`, `meta_title`, `meta_description`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Medicine', 'ওষুধ', 'medicine', 'bi-capsule', NULL, NULL, 5.00, 0, 1, 1, 1, NULL, NULL, '2026-11-20 21:35:13', '2026-11-21 03:52:24'),
(2, 1, 'Prescription Medicine', 'প্রেসক্রিপশন ওষুধ', 'prescription-medicine', 'bi-file-medical', NULL, NULL, 3.00, 1, 1, 0, 1, NULL, NULL, '2026-11-20 21:35:13', '2026-11-21 03:52:24'),
(3, 1, 'OTC Medicine', 'ওটিসি ওষুধ', 'otc-medicine', 'bi-capsule-pill', NULL, NULL, NULL, 0, 2, 0, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(4, 1, 'Herbal & Unani', 'হারবাল ও ইউনানি', 'herbal-unani', 'bi-flower1', NULL, NULL, 1.00, 0, 3, 0, 1, NULL, NULL, '2026-11-20 21:35:13', '2026-11-21 03:52:24'),
(5, 1, 'Homeopathy', 'হোমিওপ্যাথি', 'homeopathy', 'bi-droplet', NULL, NULL, NULL, 0, 4, 0, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(6, NULL, 'Healthcare', 'হেলথকেয়ার', 'healthcare', 'bi-heart-pulse', NULL, NULL, NULL, 0, 2, 1, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(7, 6, 'Medical Devices', 'মেডিকেল ডিভাইস', 'medical-devices', 'bi-thermometer-half', NULL, NULL, NULL, 0, 1, 0, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(8, 6, 'First Aid', 'ফার্স্ট এইড', 'first-aid', 'bi-bandaid', NULL, NULL, NULL, 0, 2, 0, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(9, 6, 'Diabetic Care', 'ডায়াবেটিক কেয়ার', 'diabetic-care', 'bi-activity', NULL, NULL, NULL, 0, 3, 0, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(10, NULL, 'Baby & Mom Care', 'মা ও শিশু', 'baby-mom-care', 'bi-balloon-heart', NULL, NULL, NULL, 0, 3, 1, 1, NULL, NULL, '2026-11-20 21:35:13', '2026-11-21 02:52:27'),
(11, NULL, 'Personal Care', 'পার্সোনাল কেয়ার', 'personal-care', 'bi-person-hearts', NULL, NULL, NULL, 0, 4, 1, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(12, 11, 'Skin Care', 'স্কিনকেয়ার', 'skin-care', 'bi-droplet-half', NULL, NULL, NULL, 0, 1, 0, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(13, 11, 'Hair Care', 'হেয়ারকেয়ার', 'hair-care', 'bi-scissors', NULL, NULL, NULL, 0, 2, 0, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(14, 11, 'Oral Care', 'ওরাল কেয়ার', 'oral-care', 'bi-emoji-smile', NULL, NULL, NULL, 0, 3, 0, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(15, 11, 'Feminine Care', 'ফেমিনিন কেয়ার', 'feminine-care', 'bi-flower2', NULL, NULL, NULL, 0, 4, 0, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(16, NULL, 'Nutrition & Supplements', 'পুষ্টি ও সাপ্লিমেন্ট', 'nutrition-supplements', 'bi-egg-fried', NULL, NULL, NULL, 0, 5, 1, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(17, NULL, 'Beauty', 'বিউটি', 'beauty', 'bi-stars', NULL, NULL, NULL, 0, 6, 1, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(18, NULL, 'Sexual Wellness', 'সেক্সুয়াল ওয়েলনেস', 'sexual-wellness', 'bi-shield-check', NULL, NULL, NULL, 0, 7, 0, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(19, NULL, 'Home Care', 'হোম কেয়ার', 'home-care', 'bi-house-heart', NULL, NULL, NULL, 0, 8, 0, 1, NULL, NULL, '2026-11-20 21:35:13', NULL),
(20, NULL, 'Pet Care', 'পেট কেয়ার', 'pet-care', 'bi-piggy-bank', NULL, NULL, NULL, 0, 9, 0, 1, NULL, NULL, '2026-11-20 21:35:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cod_settlements`
--

CREATE TABLE `cod_settlements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `courier_id` int(10) UNSIGNED NOT NULL,
  `reference_no` varchar(100) NOT NULL,
  `shipments_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `amount_expected` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_received` decimal(12,2) NOT NULL DEFAULT 0.00,
  `courier_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `received_at` date NOT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissions`
--

CREATE TABLE `commissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `split_id` bigint(20) UNSIGNED NOT NULL,
  `order_item_id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rule_id` int(10) UNSIGNED DEFAULT NULL,
  `rule_scope` varchar(30) DEFAULT NULL COMMENT 'which level the rate came from',
  `base_amount` decimal(12,2) NOT NULL,
  `rate_type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `rate` decimal(12,2) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','earned','reversed','settled') NOT NULL DEFAULT 'pending',
  `settled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commission_rules`
--

CREATE TABLE `commission_rules` (
  `id` int(10) UNSIGNED NOT NULL,
  `scope` enum('global','category','vendor','vendor_category','product') NOT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rate_type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `rate` decimal(12,2) NOT NULL COMMENT 'percent (0-100) or fixed BDT per unit',
  `priority` int(11) NOT NULL DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `commission_rules`
--

INSERT INTO `commission_rules` (`id`, `scope`, `vendor_id`, `category_id`, `product_id`, `rate_type`, `rate`, `priority`, `starts_at`, `ends_at`, `is_active`, `note`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'global', NULL, NULL, NULL, 'percent', 8.00, 0, NULL, NULL, 1, 'Default platform commission', 1, '2026-11-20 21:35:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `type` enum('percent','fixed','free_shipping') NOT NULL DEFAULT 'percent',
  `value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_discount` decimal(12,2) DEFAULT NULL COMMENT 'cap for percent coupons',
  `min_order_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `usage_limit` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = unlimited',
  `usage_limit_per_user` int(10) UNSIGNED DEFAULT 1,
  `used_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'vendor-funded coupon',
  `funded_by` enum('platform','vendor','shared') NOT NULL DEFAULT 'platform',
  `vendor_share_percent` decimal(5,2) NOT NULL DEFAULT 50.00,
  `applies_to` enum('all','category','product','vendor','brand') NOT NULL DEFAULT 'all',
  `applies_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`applies_ids`)),
  `first_order_only` tinyint(1) NOT NULL DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `description`, `type`, `value`, `max_discount`, `min_order_amount`, `usage_limit`, `usage_limit_per_user`, `used_count`, `vendor_id`, `funded_by`, `vendor_share_percent`, `applies_to`, `applies_ids`, `first_order_only`, `starts_at`, `ends_at`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(10, 'DEMO10', '10% off (max ৳100) on orders over ৳500', 'percent', 10.00, 100.00, 500.00, NULL, 5, 0, NULL, 'platform', 50.00, 'all', NULL, 0, NULL, NULL, 1, NULL, '2026-11-21 03:28:02', NULL),
(11, 'DEMOSHIP', 'Free delivery on orders over ৳300', 'free_shipping', 0.00, NULL, 300.00, NULL, 5, 0, NULL, 'platform', 50.00, 'all', NULL, 0, NULL, NULL, 1, NULL, '2026-11-21 03:28:02', NULL),
(12, 'WELCOME10', '10% off your first order (up to ৳150)', 'percent', 10.00, 150.00, 0.00, NULL, 1, 0, NULL, 'platform', 50.00, 'all', NULL, 1, NULL, NULL, 1, NULL, '2026-11-21 08:19:27', NULL),
(13, 'FLAT50', '৳50 off on orders of ৳500 or more', 'fixed', 50.00, NULL, 500.00, NULL, 3, 0, NULL, 'platform', 50.00, 'all', NULL, 0, NULL, NULL, 1, NULL, '2026-11-21 08:19:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `coupon_usages`
--

CREATE TABLE `coupon_usages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `coupon_id` int(10) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `couriers`
--

CREATE TABLE `couriers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL COMMENT 'pathao, steadfast, redx, paperfly, self',
  `phone` varchar(20) DEFAULT NULL,
  `api_base_url` varchar(255) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `api_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`api_config`)),
  `tracking_url_template` varchar(255) DEFAULT NULL COMMENT 'use {tracking_no}',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `couriers`
--

INSERT INTO `couriers` (`id`, `name`, `code`, `phone`, `api_base_url`, `api_key`, `api_secret`, `api_config`, `tracking_url_template`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Own delivery man', 'self', NULL, NULL, NULL, NULL, '{\"webhook_token\":\"566665856ef776b462fab0a0df23dc57a0d61361\",\"mock\":true}', NULL, 1, '2026-11-20 21:35:13', '2026-11-21 03:01:13'),
(2, 'Pathao Courier', 'pathao', NULL, 'https://api-hermes.pathao.com', NULL, NULL, '{\"webhook_token\":\"e5d390a57d37adfc917fa6b5db2b20065ae17df6\",\"mock\":true}', 'https://merchant.pathao.com/tracking?consignment_id={tracking_no}', 0, '2026-11-20 21:35:13', '2026-11-21 03:01:13'),
(3, 'Steadfast Courier', 'steadfast', NULL, 'https://portal.packzy.com/api/v1', NULL, NULL, '{\"webhook_token\": \"9c6915ba595e045ccbb58444e6e556183ea68ee4\", \"mock\": true}', 'https://steadfast.com.bd/t/{tracking_no}', 0, '2026-11-20 21:35:13', '2026-11-21 03:17:29'),
(4, 'RedX', 'redx', NULL, 'https://openapi.redx.com.bd/v1.0.0-beta', NULL, NULL, '{\"webhook_token\":\"8c6021d4873b62c26d2c2d20459e510bde7c1044\",\"mock\":true}', NULL, 0, '2026-11-20 21:35:13', '2026-11-21 03:17:29'),
(5, 'Paperfly', 'paperfly', NULL, 'https://api.paperfly.com.bd', NULL, NULL, '{\"webhook_token\":\"92f13c6072cc97d2483bad16a70812613cac26f4\",\"mock\":true}', NULL, 0, '2026-11-20 21:35:13', '2026-11-21 03:01:13'),
(6, 'eCourier', 'ecourier', NULL, 'https://backoffice.ecourier.com.bd/api', NULL, NULL, '{\"webhook_token\":\"0ccc4f7db0de7f722a2741d1f58c66a7dbb8fe87\",\"mock\":true}', 'https://ecourier.com.bd/track/?tracking={tracking_no}', 0, '2026-11-21 03:01:13', '2026-11-21 03:01:13');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(10) UNSIGNED NOT NULL,
  `category` varchar(40) NOT NULL DEFAULT 'general',
  `question_en` varchar(255) NOT NULL,
  `question_bn` varchar(255) DEFAULT NULL,
  `answer_en` text NOT NULL,
  `answer_bn` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `category`, `question_en`, `question_bn`, `answer_en`, `answer_bn`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'order', 'How do I place an order?', 'কীভাবে অর্ডার করব?', 'Search or browse for a product, add it to the cart and check out. You can pay cash on delivery or with bKash / Nagad.', 'পণ্য খুঁজে কার্টে যোগ করুন এবং চেকআউট করুন। ক্যাশ অন ডেলিভারি অথবা বিকাশ / নগদে পরিশোধ করতে পারবেন।', 0, 1, '2026-11-21 09:24:28', NULL),
(2, 'order', 'Do I need a prescription?', 'প্রেসক্রিপশন কি লাগবে?', 'Prescription medicines (marked Rx) need a valid prescription. Upload a photo at checkout; our pharmacist checks it before the order is confirmed.', 'প্রেসক্রিপশন ওষুধে (Rx চিহ্নিত) বৈধ প্রেসক্রিপশন লাগবে। চেকআউটে ছবি আপলোড করুন; ফার্মাসিস্ট যাচাই করে অর্ডার নিশ্চিত করবেন।', 1, 1, '2026-11-21 09:24:28', NULL),
(3, 'delivery', 'How long does delivery take?', 'ডেলিভারিতে কত সময় লাগে?', 'Inside Dhaka usually 1-2 days, outside Dhaka 2-4 days. You can track every order from My orders.', 'ঢাকার ভিতরে সাধারণত ১-২ দিন, ঢাকার বাইরে ২-৪ দিন। আমার অর্ডার থেকে প্রতিটি অর্ডার ট্র্যাক করতে পারবেন।', 2, 1, '2026-11-21 09:24:28', NULL),
(4, 'delivery', 'How much is the delivery charge?', 'ডেলিভারি চার্জ কত?', 'It depends on your area and the parcel weight and is shown at checkout before you order. Larger orders may ship free.', 'এলাকা ও পার্সেলের ওজন অনুযায়ী, যা অর্ডারের আগে চেকআউটে দেখানো হয়। বড় অর্ডারে ফ্রি ডেলিভারি হতে পারে।', 3, 1, '2026-11-21 09:24:28', NULL),
(5, 'payment', 'Which payment methods do you accept?', 'কোন কোন পেমেন্ট পদ্ধতি আছে?', 'Cash on delivery, bKash, Nagad and Rocket.', 'ক্যাশ অন ডেলিভারি, বিকাশ, নগদ ও রকেট।', 4, 1, '2026-11-21 09:24:28', NULL),
(6, 'returns', 'Can I return a product?', 'পণ্য কি ফেরত দেওয়া যায়?', 'Yes, within the return window shown on your order, if the product is unused and sealed. Request a return from the order page.', 'হ্যাঁ, অর্ডারে দেখানো সময়ের মধ্যে, পণ্য অব্যবহৃত ও সিল করা থাকলে। অর্ডারের পাতা থেকে রিটার্নের অনুরোধ করুন।', 5, 1, '2026-11-21 09:24:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `flash_sales`
--

CREATE TABLE `flash_sales` (
  `id` int(10) UNSIGNED NOT NULL,
  `title_en` varchar(200) NOT NULL,
  `title_bn` varchar(200) DEFAULT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `funded_by` enum('platform','vendor','shared') NOT NULL DEFAULT 'platform',
  `vendor_share_percent` decimal(5,2) NOT NULL DEFAULT 50.00,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `flash_sales`
--

INSERT INTO `flash_sales` (`id`, `title_en`, `title_bn`, `banner`, `funded_by`, `vendor_share_percent`, `starts_at`, `ends_at`, `is_active`, `created_at`, `updated_at`) VALUES
(6, 'Weekly Health Deals', 'সাপ্তাহিক হেলথ ডিল', NULL, 'platform', 50.00, '2026-11-21 02:28:02', '2026-11-28 23:59:59', 1, '2026-11-21 03:28:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `flash_sale_items`
--

CREATE TABLE `flash_sale_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `flash_sale_id` int(10) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_price` decimal(12,2) NOT NULL,
  `quantity_limit` int(10) UNSIGNED DEFAULT NULL,
  `sold_qty` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `per_user_limit` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `flash_sale_items`
--

INSERT INTO `flash_sale_items` (`id`, `flash_sale_id`, `product_id`, `variant_id`, `sale_price`, `quantity_limit`, `sold_qty`, `per_user_limit`) VALUES
(6, 6, 55, NULL, 1299.00, 30, 11, NULL),
(7, 6, 64, NULL, 369.00, 60, 29, NULL),
(8, 6, 72, NULL, 1099.00, 25, 9, NULL),
(9, 6, 60, NULL, 549.00, 40, 14, NULL),
(10, 6, 42, NULL, 75.00, 100, 46, NULL),
(11, 6, 57, NULL, 3599.00, 10, 3, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `grns`
--

CREATE TABLE `grns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `grn_no` varchar(30) NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `invoice_no` varchar(100) DEFAULT NULL COMMENT 'supplier invoice / challan',
  `received_date` date NOT NULL,
  `total_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(500) DEFAULT NULL,
  `received_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grn_items`
--

CREATE TABLE `grn_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `grn_id` bigint(20) UNSIGNED NOT NULL,
  `po_item_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `batch_no` varchar(100) NOT NULL,
  `manufacture_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) NOT NULL DEFAULT 0.00
) ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_batches`
--

CREATE TABLE `inventory_batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `variant_key` bigint(20) UNSIGNED GENERATED ALWAYS AS (ifnull(`variant_id`,0)) STORED,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `batch_no` varchar(100) NOT NULL,
  `manufacture_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity_received` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `quantity_available` int(11) NOT NULL DEFAULT 0,
  `cost_price` decimal(12,2) DEFAULT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
  `invoice_no` varchar(100) DEFAULT NULL,
  `grn_id` bigint(20) UNSIGNED DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `received_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('active','expired','recalled','depleted') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `inventory_batches`
--

INSERT INTO `inventory_batches` (`id`, `product_id`, `variant_id`, `vendor_id`, `batch_no`, `manufacture_date`, `expiry_date`, `quantity_received`, `quantity_available`, `cost_price`, `supplier_id`, `supplier_name`, `invoice_no`, `grn_id`, `received_at`, `received_by`, `status`, `created_at`, `updated_at`) VALUES
(32, 38, 17, NULL, 'DEMO-NAPA50-V1', '2026-09-21', '2028-07-21', 300, 300, 9.84, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(33, 38, 18, NULL, 'DEMO-NAPA50-V2', '2026-09-21', '2028-07-21', 25, 25, 186.96, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(34, 39, NULL, NULL, 'DEMO-NAPAEX-A', '2026-10-21', '2028-05-21', 300, 300, 20.50, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', '2026-11-21 14:28:58'),
(35, 40, NULL, 10, 'DEMO-ACE500-A', '2026-10-21', '2028-05-21', 250, 250, 9.02, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(36, 41, NULL, 10, 'DEMO-ACE-PL-A', '2026-10-21', '2028-05-21', 180, 180, 18.86, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(37, 42, NULL, NULL, 'DEMO-FEXO12-A', '2026-10-21', '2028-05-21', 150, 150, 69.70, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(38, 43, NULL, NULL, 'DEMO-ALATRO-A', '2026-10-21', '2028-05-21', 8, 8, 24.60, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(39, 44, NULL, NULL, 'DEMO-SECLO2-A', '2026-10-21', '2028-05-21', 200, 200, 51.66, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(40, 45, NULL, NULL, 'DEMO-SERGEL-A', '2026-10-21', '2028-05-21', 160, 160, 54.94, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(41, 46, NULL, 11, 'DEMO-MAXPRO-A', '2026-10-21', '2028-05-21', 120, 120, 57.40, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(42, 47, NULL, 11, 'DEMO-LOSECT-A', '2026-10-21', '2028-05-21', 90, 90, 38.54, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(43, 48, NULL, NULL, 'DEMO-MONAS1-A', '2026-10-21', '2028-05-21', 100, 100, 124.64, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(44, 49, NULL, NULL, 'DEMO-AZITHR-OLD', '2025-03-21', '2027-01-21', 12, 12, 86.10, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(45, 49, NULL, NULL, 'DEMO-AZITHR-A', '2026-10-21', '2028-05-21', 80, 80, 86.10, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(46, 50, NULL, NULL, 'DEMO-BIZORA-A', '2026-10-21', '2028-05-21', 110, 110, 46.74, NULL, NULL, NULL, NULL, '2026-11-21 03:28:01', NULL, 'active', '2026-11-21 03:28:01', NULL),
(47, 51, NULL, NULL, 'DEMO-COMET5-A', '2026-10-21', '2028-05-21', 220, 220, 31.16, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(48, 52, 19, NULL, 'DEMO-ORSALI-V1', '2026-09-21', '2028-07-21', 800, 800, 4.92, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(49, 52, 20, NULL, 'DEMO-ORSALI-V2', '2026-09-21', '2028-07-21', 40, 40, 90.20, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(50, 53, 21, NULL, 'DEMO-SAVLON-V1', '2026-09-21', '2028-07-21', 80, 80, 59.04, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(51, 53, 22, NULL, 'DEMO-SAVLON-V2', '2026-09-21', '2028-07-21', 30, 30, 327.18, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(52, 54, NULL, 10, 'DEMO-BANDAG-A', '2026-10-21', NULL, 70, 70, 69.70, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(53, 55, NULL, NULL, 'DEMO-ACCUCH-A', '2026-10-21', NULL, 25, 25, 1189.00, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(54, 56, NULL, NULL, 'DEMO-ACCUST-A', '2026-10-21', NULL, 40, 40, 1139.80, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(55, 57, NULL, 10, 'DEMO-BPMONI-A', '2026-10-21', NULL, 15, 15, 3239.00, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(56, 58, NULL, NULL, 'DEMO-THERMO-A', '2026-10-21', NULL, 60, 60, 327.18, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(57, 59, NULL, 11, 'DEMO-MASK-A', '2026-10-21', NULL, 100, 100, 163.18, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(58, 60, NULL, NULL, 'DEMO-CERELA-A', '2026-10-21', NULL, 60, 60, 487.90, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(59, 61, 23, NULL, 'DEMO-JBABY--V1', '2026-09-21', '2028-07-21', 40, 40, 171.38, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(60, 61, 24, NULL, 'DEMO-JBABY--V2', '2026-09-21', '2028-07-21', 25, 25, 291.10, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(61, 62, NULL, 10, 'DEMO-JBABY--A', '2026-10-21', NULL, 45, 45, 327.18, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(62, 63, NULL, NULL, 'DEMO-DIAPER-A', '2026-10-21', NULL, 30, 30, 983.18, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(63, 64, NULL, NULL, 'DEMO-FILWEL-A', '2026-10-21', '2028-05-21', 70, 68, 344.40, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', '2026-11-21 03:32:48'),
(64, 65, NULL, 11, 'DEMO-CEEVIT-A', '2026-10-21', '2028-05-21', 300, 300, 12.30, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(65, 66, NULL, NULL, 'DEMO-CALBO--A', '2026-10-21', '2028-05-21', 130, 130, 129.56, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(66, 67, NULL, NULL, 'DEMO-HORLIC-A', '2026-10-21', NULL, 50, 50, 508.40, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(67, 68, NULL, NULL, 'DEMO-SENSOD-A', '2026-10-21', NULL, 80, 80, 295.20, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(68, 69, NULL, NULL, 'DEMO-DETTOL-A', '2026-10-21', NULL, 200, 200, 65.60, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(69, 70, NULL, 11, 'DEMO-DETTOL-A', '2026-10-21', NULL, 150, 150, 86.10, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(70, 71, NULL, NULL, 'DEMO-WHISPE-A', '2026-10-21', NULL, 90, 90, 172.20, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(71, 72, NULL, NULL, 'DEMO-NEUTRO-A', '2026-10-21', NULL, 35, 35, 1057.80, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(72, 73, NULL, 10, 'DEMO-HIMALA-A', '2026-10-21', NULL, 75, 75, 241.90, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(73, 74, NULL, NULL, 'DEMO-DUREX-A', '2026-10-21', NULL, 100, 100, 114.80, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL),
(74, 75, NULL, NULL, 'DEMO-TULSI-A', '2026-10-21', '2028-05-21', 40, 40, 327.18, NULL, NULL, NULL, NULL, '2026-11-21 03:28:02', NULL, 'active', '2026-11-21 03:28:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('division','district','upazila','area') NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `name_bn` varchar(100) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `shipping_zone_id` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `parent_id`, `type`, `name_en`, `name_bn`, `postcode`, `shipping_zone_id`, `is_active`) VALUES
(1, NULL, 'division', 'Chattogram', 'চট্টগ্রাম', NULL, NULL, 1),
(2, NULL, 'division', 'Rajshahi', 'রাজশাহী', NULL, NULL, 1),
(3, NULL, 'division', 'Khulna', 'খুলনা', NULL, NULL, 1),
(4, NULL, 'division', 'Barishal', 'বরিশাল', NULL, NULL, 1),
(5, NULL, 'division', 'Sylhet', 'সিলেট', NULL, NULL, 1),
(6, NULL, 'division', 'Dhaka', 'ঢাকা', NULL, NULL, 1),
(7, NULL, 'division', 'Rangpur', 'রংপুর', NULL, NULL, 1),
(8, NULL, 'division', 'Mymensingh', 'ময়মনসিংহ', NULL, NULL, 1),
(101, 1, 'district', 'Cumilla', 'কুমিল্লা', NULL, 3, 1),
(102, 1, 'district', 'Feni', 'ফেনী', NULL, 3, 1),
(103, 1, 'district', 'Brahmanbaria', 'ব্রাহ্মণবাড়িয়া', NULL, 3, 1),
(104, 1, 'district', 'Rangamati', 'রাঙ্গামাটি', NULL, 3, 1),
(105, 1, 'district', 'Noakhali', 'নোয়াখালী', NULL, 3, 1),
(106, 1, 'district', 'Chandpur', 'চাঁদপুর', NULL, 3, 1),
(107, 1, 'district', 'Lakshmipur', 'লক্ষ্মীপুর', NULL, 3, 1),
(108, 1, 'district', 'Chattogram', 'চট্টগ্রাম', NULL, 3, 1),
(109, 1, 'district', 'Cox\'s Bazar', 'কক্সবাজার', NULL, 3, 1),
(110, 1, 'district', 'Khagrachhari', 'খাগড়াছড়ি', NULL, 3, 1),
(111, 1, 'district', 'Bandarban', 'বান্দরবান', NULL, 3, 1),
(112, 2, 'district', 'Sirajganj', 'সিরাজগঞ্জ', NULL, 3, 1),
(113, 2, 'district', 'Pabna', 'পাবনা', NULL, 3, 1),
(114, 2, 'district', 'Bogura', 'বগুড়া', NULL, 3, 1),
(115, 2, 'district', 'Rajshahi', 'রাজশাহী', NULL, 3, 1),
(116, 2, 'district', 'Natore', 'নাটোর', NULL, 3, 1),
(117, 2, 'district', 'Joypurhat', 'জয়পুরহাট', NULL, 3, 1),
(118, 2, 'district', 'Chapainababganj', 'চাঁপাইনবাবগঞ্জ', NULL, 3, 1),
(119, 2, 'district', 'Naogaon', 'নওগাঁ', NULL, 3, 1),
(120, 3, 'district', 'Jashore', 'যশোর', NULL, 3, 1),
(121, 3, 'district', 'Satkhira', 'সাতক্ষীরা', NULL, 3, 1),
(122, 3, 'district', 'Meherpur', 'মেহেরপুর', NULL, 3, 1),
(123, 3, 'district', 'Narail', 'নড়াইল', NULL, 3, 1),
(124, 3, 'district', 'Chuadanga', 'চুয়াডাঙ্গা', NULL, 3, 1),
(125, 3, 'district', 'Kushtia', 'কুষ্টিয়া', NULL, 3, 1),
(126, 3, 'district', 'Magura', 'মাগুরা', NULL, 3, 1),
(127, 3, 'district', 'Khulna', 'খুলনা', NULL, 3, 1),
(128, 3, 'district', 'Bagerhat', 'বাগেরহাট', NULL, 3, 1),
(129, 3, 'district', 'Jhenaidah', 'ঝিনাইদহ', NULL, 3, 1),
(130, 4, 'district', 'Jhalokati', 'ঝালকাঠি', NULL, 3, 1),
(131, 4, 'district', 'Patuakhali', 'পটুয়াখালী', NULL, 3, 1),
(132, 4, 'district', 'Pirojpur', 'পিরোজপুর', NULL, 3, 1),
(133, 4, 'district', 'Barishal', 'বরিশাল', NULL, 3, 1),
(134, 4, 'district', 'Bhola', 'ভোলা', NULL, 3, 1),
(135, 4, 'district', 'Barguna', 'বরগুনা', NULL, 3, 1),
(136, 5, 'district', 'Sylhet', 'সিলেট', NULL, 3, 1),
(137, 5, 'district', 'Moulvibazar', 'মৌলভীবাজার', NULL, 3, 1),
(138, 5, 'district', 'Habiganj', 'হবিগঞ্জ', NULL, 3, 1),
(139, 5, 'district', 'Sunamganj', 'সুনামগঞ্জ', NULL, 3, 1),
(140, 6, 'district', 'Narsingdi', 'নরসিংদী', NULL, 2, 1),
(141, 6, 'district', 'Gazipur', 'গাজীপুর', NULL, 2, 1),
(142, 6, 'district', 'Shariatpur', 'শরীয়তপুর', NULL, 2, 1),
(143, 6, 'district', 'Narayanganj', 'নারায়ণগঞ্জ', NULL, 2, 1),
(144, 6, 'district', 'Tangail', 'টাঙ্গাইল', NULL, 2, 1),
(145, 6, 'district', 'Kishoreganj', 'কিশোরগঞ্জ', NULL, 2, 1),
(146, 6, 'district', 'Manikganj', 'মানিকগঞ্জ', NULL, 2, 1),
(147, 6, 'district', 'Dhaka', 'ঢাকা', NULL, 1, 1),
(148, 6, 'district', 'Munshiganj', 'মুন্সিগঞ্জ', NULL, 2, 1),
(149, 6, 'district', 'Rajbari', 'রাজবাড়ী', NULL, 2, 1),
(150, 6, 'district', 'Madaripur', 'মাদারীপুর', NULL, 2, 1),
(151, 6, 'district', 'Gopalganj', 'গোপালগঞ্জ', NULL, 2, 1),
(152, 6, 'district', 'Faridpur', 'ফরিদপুর', NULL, 2, 1),
(153, 7, 'district', 'Panchagarh', 'পঞ্চগড়', NULL, 3, 1),
(154, 7, 'district', 'Dinajpur', 'দিনাজপুর', NULL, 3, 1),
(155, 7, 'district', 'Lalmonirhat', 'লালমনিরহাট', NULL, 3, 1),
(156, 7, 'district', 'Nilphamari', 'নীলফামারী', NULL, 3, 1),
(157, 7, 'district', 'Gaibandha', 'গাইবান্ধা', NULL, 3, 1),
(158, 7, 'district', 'Thakurgaon', 'ঠাকুরগাঁও', NULL, 3, 1),
(159, 7, 'district', 'Rangpur', 'রংপুর', NULL, 3, 1),
(160, 7, 'district', 'Kurigram', 'কুড়িগ্রাম', NULL, 3, 1),
(161, 8, 'district', 'Sherpur', 'শেরপুর', NULL, 3, 1),
(162, 8, 'district', 'Mymensingh', 'ময়মনসিংহ', NULL, 3, 1),
(163, 8, 'district', 'Jamalpur', 'জামালপুর', NULL, 3, 1),
(164, 8, 'district', 'Netrakona', 'নেত্রকোণা', NULL, 3, 1),
(1001, 101, 'upazila', 'Debidwar', 'দেবিদ্বার', NULL, NULL, 1),
(1002, 101, 'upazila', 'Barura', 'বরুড়া', NULL, NULL, 1),
(1003, 101, 'upazila', 'Brahmanpara', 'ব্রাহ্মণপাড়া', NULL, NULL, 1),
(1004, 101, 'upazila', 'Chandina', 'চান্দিনা', NULL, NULL, 1),
(1005, 101, 'upazila', 'Chauddagram', 'চৌদ্দগ্রাম', NULL, NULL, 1),
(1006, 101, 'upazila', 'Daudkandi', 'দাউদকান্দি', NULL, NULL, 1),
(1007, 101, 'upazila', 'Homna', 'হোমনা', NULL, NULL, 1),
(1008, 101, 'upazila', 'Laksam', 'লাকসাম', NULL, NULL, 1),
(1009, 101, 'upazila', 'Muradnagar', 'মুরাদনগর', NULL, NULL, 1),
(1010, 101, 'upazila', 'Nangalkot', 'নাঙ্গলকোট', NULL, NULL, 1),
(1011, 101, 'upazila', 'Adarsha Sadar', 'কুমিল্লা আদর্শ সদর', NULL, NULL, 1),
(1012, 101, 'upazila', 'Meghna', 'মেঘনা', NULL, NULL, 1),
(1013, 101, 'upazila', 'Manoharganj', 'মনোহরগঞ্জ', NULL, NULL, 1),
(1014, 101, 'upazila', 'Sadar Dakkhin', 'সদর দক্ষিণ', NULL, NULL, 1),
(1015, 101, 'upazila', 'Titas', 'তিতাস', NULL, NULL, 1),
(1016, 101, 'upazila', 'Burichang', 'বুড়িচং', NULL, NULL, 1),
(1017, 101, 'upazila', 'Lalmai', 'লালমাই', NULL, NULL, 1),
(1018, 102, 'upazila', 'Chhagalnaiya', 'ছাগলনাইয়া', NULL, NULL, 1),
(1019, 102, 'upazila', 'Feni Sadar', 'ফেনী সদর', NULL, NULL, 1),
(1020, 102, 'upazila', 'Sonagazi', 'সোনাগাজী', NULL, NULL, 1),
(1021, 102, 'upazila', 'Fulgazi', 'ফুলগাজী', NULL, NULL, 1),
(1022, 102, 'upazila', 'Parashuram', 'পরশুরাম', NULL, NULL, 1),
(1023, 102, 'upazila', 'Daganbhuiyan', 'দাগনভূঞা', NULL, NULL, 1),
(1024, 103, 'upazila', 'Brahmanbaria Sadar', 'ব্রাহ্মণবাড়িয়া সদর', NULL, NULL, 1),
(1025, 103, 'upazila', 'Kasba', 'কসবা', NULL, NULL, 1),
(1026, 103, 'upazila', 'Nasirnagar', 'নাসিরনগর', NULL, NULL, 1),
(1027, 103, 'upazila', 'Sarail', 'সরাইল', NULL, NULL, 1),
(1028, 103, 'upazila', 'Ashuganj', 'আশুগঞ্জ', NULL, NULL, 1),
(1029, 103, 'upazila', 'Akhaura', 'আখাউড়া', NULL, NULL, 1),
(1030, 103, 'upazila', 'Nabinagar', 'নবীনগর', NULL, NULL, 1),
(1031, 103, 'upazila', 'Banchharampur', 'বাঞ্ছারামপুর', NULL, NULL, 1),
(1032, 103, 'upazila', 'Bijoynagar', 'বিজয়নগর', NULL, NULL, 1),
(1033, 104, 'upazila', 'Rangamati Sadar', 'রাঙ্গামাটি সদর', NULL, NULL, 1),
(1034, 104, 'upazila', 'Kaptai', 'কাপ্তাই', NULL, NULL, 1),
(1035, 104, 'upazila', 'Kawkhali', 'কাউখালী', NULL, NULL, 1),
(1036, 104, 'upazila', 'Baghaichhari', 'বাঘাইছড়ি', NULL, NULL, 1),
(1037, 104, 'upazila', 'Barkal', 'বরকল', NULL, NULL, 1),
(1038, 104, 'upazila', 'Langadu', 'লংগদু', NULL, NULL, 1),
(1039, 104, 'upazila', 'Rajasthali', 'রাজস্থলী', NULL, NULL, 1),
(1040, 104, 'upazila', 'Belaichhari', 'বিলাইছড়ি', NULL, NULL, 1),
(1041, 104, 'upazila', 'Jurachhari', 'জুরাছড়ি', NULL, NULL, 1),
(1042, 104, 'upazila', 'Naniarchar', 'নানিয়ারচর', NULL, NULL, 1),
(1043, 105, 'upazila', 'Noakhali Sadar', 'নোয়াখালী সদর', NULL, NULL, 1),
(1044, 105, 'upazila', 'Companiganj', 'কোম্পানীগঞ্জ', NULL, NULL, 1),
(1045, 105, 'upazila', 'Begumganj', 'বেগমগঞ্জ', NULL, NULL, 1),
(1046, 105, 'upazila', 'Hatiya', 'হাতিয়া', NULL, NULL, 1),
(1047, 105, 'upazila', 'Subarnachar', 'সুবর্ণচর', NULL, NULL, 1),
(1048, 105, 'upazila', 'Kabirhat', 'কবিরহাট', NULL, NULL, 1),
(1049, 105, 'upazila', 'Senbag', 'সেনবাগ', NULL, NULL, 1),
(1050, 105, 'upazila', 'Chatkhil', 'চাটখিল', NULL, NULL, 1),
(1051, 105, 'upazila', 'Sonaimuri', 'সোনাইমুড়ী', NULL, NULL, 1),
(1052, 106, 'upazila', 'Haimchar', 'হাইমচর', NULL, NULL, 1),
(1053, 106, 'upazila', 'Kachua', 'কচুয়া', NULL, NULL, 1),
(1054, 106, 'upazila', 'Shahrasti', 'শাহরাস্তি', NULL, NULL, 1),
(1055, 106, 'upazila', 'Chandpur Sadar', 'চাঁদপুর সদর', NULL, NULL, 1),
(1056, 106, 'upazila', 'Matlab Dakkhin', 'মতলব দক্ষিণ', NULL, NULL, 1),
(1057, 106, 'upazila', 'Hajiganj', 'হাজীগঞ্জ', NULL, NULL, 1),
(1058, 106, 'upazila', 'Matlab Uttar', 'মতলব উত্তর', NULL, NULL, 1),
(1059, 106, 'upazila', 'Faridganj', 'ফরিদগঞ্জ', NULL, NULL, 1),
(1060, 107, 'upazila', 'Lakshmipur Sadar', 'লক্ষ্মীপুর সদর', NULL, NULL, 1),
(1061, 107, 'upazila', 'Kamalnagar', 'কমলনগর', NULL, NULL, 1),
(1062, 107, 'upazila', 'Raipur', 'রায়পুর', NULL, NULL, 1),
(1063, 107, 'upazila', 'Ramgati', 'রামগতি', NULL, NULL, 1),
(1064, 107, 'upazila', 'Ramganj', 'রামগঞ্জ', NULL, NULL, 1),
(1065, 108, 'upazila', 'Rangunia', 'রাঙ্গুনিয়া', NULL, NULL, 1),
(1066, 108, 'upazila', 'Sitakunda', 'সীতাকুন্ড', NULL, NULL, 1),
(1067, 108, 'upazila', 'Mirsarai', 'মীরসরাই', NULL, NULL, 1),
(1068, 108, 'upazila', 'Patiya', 'পটিয়া', NULL, NULL, 1),
(1069, 108, 'upazila', 'Sandwip', 'সন্দ্বীপ', NULL, NULL, 1),
(1070, 108, 'upazila', 'Banshkhali', 'বাঁশখালী', NULL, NULL, 1),
(1071, 108, 'upazila', 'Boalkhali', 'বোয়ালখালী', NULL, NULL, 1),
(1072, 108, 'upazila', 'Anwara', 'আনোয়ারা', NULL, NULL, 1),
(1073, 108, 'upazila', 'Chandanaish', 'চন্দনাইশ', NULL, NULL, 1),
(1074, 108, 'upazila', 'Satkania', 'সাতকানিয়া', NULL, NULL, 1),
(1075, 108, 'upazila', 'Lohagara', 'লোহাগাড়া', NULL, NULL, 1),
(1076, 108, 'upazila', 'Hathazari', 'হাটহাজারী', NULL, NULL, 1),
(1077, 108, 'upazila', 'Fatikchhari', 'ফটিকছড়ি', NULL, NULL, 1),
(1078, 108, 'upazila', 'Raozan', 'রাউজান', NULL, NULL, 1),
(1079, 108, 'upazila', 'Karnaphuli', 'কর্ণফুলী', NULL, NULL, 1),
(1080, 109, 'upazila', 'Coxs Bazar Sadar', 'কক্সবাজার সদর', NULL, NULL, 1),
(1081, 109, 'upazila', 'Chakaria', 'চকরিয়া', NULL, NULL, 1),
(1082, 109, 'upazila', 'Kutubdia', 'কুতুবদিয়া', NULL, NULL, 1),
(1083, 109, 'upazila', 'Ukhia', 'উখিয়া', NULL, NULL, 1),
(1084, 109, 'upazila', 'Maheshkhali', 'মহেশখালী', NULL, NULL, 1),
(1085, 109, 'upazila', 'Pekua', 'পেকুয়া', NULL, NULL, 1),
(1086, 109, 'upazila', 'Ramu', 'রামু', NULL, NULL, 1),
(1087, 109, 'upazila', 'Teknaf', 'টেকনাফ', NULL, NULL, 1),
(1088, 110, 'upazila', 'Khagrachhari Sadar', 'খাগড়াছড়ি সদর', NULL, NULL, 1),
(1089, 110, 'upazila', 'Dighinala', 'দিঘীনালা', NULL, NULL, 1),
(1090, 110, 'upazila', 'Panchhari', 'পানছড়ি', NULL, NULL, 1),
(1091, 110, 'upazila', 'Lakkhichhari', 'লক্ষীছড়ি', NULL, NULL, 1),
(1092, 110, 'upazila', 'Mahalchhari', 'মহালছড়ি', NULL, NULL, 1),
(1093, 110, 'upazila', 'Manikchhari', 'মানিকছড়ি', NULL, NULL, 1),
(1094, 110, 'upazila', 'Ramgarh', 'রামগড়', NULL, NULL, 1),
(1095, 110, 'upazila', 'Matiranga', 'মাটিরাঙ্গা', NULL, NULL, 1),
(1096, 110, 'upazila', 'Guimara', 'গুইমারা', NULL, NULL, 1),
(1097, 111, 'upazila', 'Bandarban Sadar', 'বান্দরবান সদর', NULL, NULL, 1),
(1098, 111, 'upazila', 'Alikadam', 'আলীকদম', NULL, NULL, 1),
(1099, 111, 'upazila', 'Naikkhongchhari', 'নাইক্ষ্যংছড়ি', NULL, NULL, 1),
(1100, 111, 'upazila', 'Rowangchhari', 'রোয়াংছড়ি', NULL, NULL, 1),
(1101, 111, 'upazila', 'Lama', 'লামা', NULL, NULL, 1),
(1102, 111, 'upazila', 'Ruma', 'রুমা', NULL, NULL, 1),
(1103, 111, 'upazila', 'Thanchi', 'থানচি', NULL, NULL, 1),
(1104, 112, 'upazila', 'Belkuchi', 'বেলকুচি', NULL, NULL, 1),
(1105, 112, 'upazila', 'Chouhali', 'চৌহালি', NULL, NULL, 1),
(1106, 112, 'upazila', 'Kamarkhanda', 'কামারখন্দ', NULL, NULL, 1),
(1107, 112, 'upazila', 'Kazipur', 'কাজীপুর', NULL, NULL, 1),
(1108, 112, 'upazila', 'Rayganj', 'রায়গঞ্জ', NULL, NULL, 1),
(1109, 112, 'upazila', 'Shahjadpur', 'শাহজাদপুর', NULL, NULL, 1),
(1110, 112, 'upazila', 'Sirajganj Sadar', 'সিরাজগঞ্জ সদর', NULL, NULL, 1),
(1111, 112, 'upazila', 'Tarash', 'তাড়াশ', NULL, NULL, 1),
(1112, 112, 'upazila', 'Ullapara', 'উল্লাপাড়া', NULL, NULL, 1),
(1113, 113, 'upazila', 'Sujanagar', 'সুজানগর', NULL, NULL, 1),
(1114, 113, 'upazila', 'Ishwardi', 'ঈশ্বরদী', NULL, NULL, 1),
(1115, 113, 'upazila', 'Bhangura', 'ভাঙ্গুড়া', NULL, NULL, 1),
(1116, 113, 'upazila', 'Pabna Sadar', 'পাবনা সদর', NULL, NULL, 1),
(1117, 113, 'upazila', 'Bera', 'বেড়া', NULL, NULL, 1),
(1118, 113, 'upazila', 'Atgharia', 'আটঘরিয়া', NULL, NULL, 1),
(1119, 113, 'upazila', 'Chatmohar', 'চাটমোহর', NULL, NULL, 1),
(1120, 113, 'upazila', 'Santhia', 'সাঁথিয়া', NULL, NULL, 1),
(1121, 113, 'upazila', 'Faridpur', 'ফরিদপুর', NULL, NULL, 1),
(1122, 114, 'upazila', 'Kahaloo', 'কাহালু', NULL, NULL, 1),
(1123, 114, 'upazila', 'Bogura Sadar', 'বগুড়া সদর', NULL, NULL, 1),
(1124, 114, 'upazila', 'Sariakandi', 'সারিয়াকান্দি', NULL, NULL, 1),
(1125, 114, 'upazila', 'Shajahanpur', 'শাজাহানপুর', NULL, NULL, 1),
(1126, 114, 'upazila', 'Dupchachia', 'দুপচাচিঁয়া', NULL, NULL, 1),
(1127, 114, 'upazila', 'Adamdighi', 'আদমদিঘি', NULL, NULL, 1),
(1128, 114, 'upazila', 'Nandigram', 'নন্দিগ্রাম', NULL, NULL, 1),
(1129, 114, 'upazila', 'Sonatala', 'সোনাতলা', NULL, NULL, 1),
(1130, 114, 'upazila', 'Dhunat', 'ধুনট', NULL, NULL, 1),
(1131, 114, 'upazila', 'Gabtali', 'গাবতলী', NULL, NULL, 1),
(1132, 114, 'upazila', 'Sherpur', 'শেরপুর', NULL, NULL, 1),
(1133, 114, 'upazila', 'Shibganj', 'শিবগঞ্জ', NULL, NULL, 1),
(1134, 115, 'upazila', 'Paba', 'পবা', NULL, NULL, 1),
(1135, 115, 'upazila', 'Durgapur', 'দুর্গাপুর', NULL, NULL, 1),
(1136, 115, 'upazila', 'Mohanpur', 'মোহনপুর', NULL, NULL, 1),
(1137, 115, 'upazila', 'Charghat', 'চারঘাট', NULL, NULL, 1),
(1138, 115, 'upazila', 'Puthia', 'পুঠিয়া', NULL, NULL, 1),
(1139, 115, 'upazila', 'Bagha', 'বাঘা', NULL, NULL, 1),
(1140, 115, 'upazila', 'Godagari', 'গোদাগাড়ী', NULL, NULL, 1),
(1141, 115, 'upazila', 'Tanore', 'তানোর', NULL, NULL, 1),
(1142, 115, 'upazila', 'Bagmara', 'বাগমারা', NULL, NULL, 1),
(1143, 116, 'upazila', 'Natore Sadar', 'নাটোর সদর', NULL, NULL, 1),
(1144, 116, 'upazila', 'Singra', 'সিংড়া', NULL, NULL, 1),
(1145, 116, 'upazila', 'Baraigram', 'বড়াইগ্রাম', NULL, NULL, 1),
(1146, 116, 'upazila', 'Bagatipara', 'বাগাতিপাড়া', NULL, NULL, 1),
(1147, 116, 'upazila', 'Lalpur', 'লালপুর', NULL, NULL, 1),
(1148, 116, 'upazila', 'Gurudaspur', 'গুরুদাসপুর', NULL, NULL, 1),
(1149, 116, 'upazila', 'Naldanga', 'নলডাঙ্গা', NULL, NULL, 1),
(1150, 117, 'upazila', 'Akkelpur', 'আক্কেলপুর', NULL, NULL, 1),
(1151, 117, 'upazila', 'Kalai', 'কালাই', NULL, NULL, 1),
(1152, 117, 'upazila', 'Khetlal', 'ক্ষেতলাল', NULL, NULL, 1),
(1153, 117, 'upazila', 'Panchbibi', 'পাঁচবিবি', NULL, NULL, 1),
(1154, 117, 'upazila', 'Joypurhat Sadar', 'জয়পুরহাট সদর', NULL, NULL, 1),
(1155, 118, 'upazila', 'Chapainawabganj Sadar', 'চাঁপাইনবাবগঞ্জ সদর', NULL, NULL, 1),
(1156, 118, 'upazila', 'Gomastapur', 'গোমস্তাপুর', NULL, NULL, 1),
(1157, 118, 'upazila', 'Nachole', 'নাচোল', NULL, NULL, 1),
(1158, 118, 'upazila', 'Bholahat', 'ভোলাহাট', NULL, NULL, 1),
(1159, 118, 'upazila', 'Shibganj', 'শিবগঞ্জ', NULL, NULL, 1),
(1160, 119, 'upazila', 'Mahadebpur', 'মহাদেবপুর', NULL, NULL, 1),
(1161, 119, 'upazila', 'Badalgachhi', 'বদলগাছী', NULL, NULL, 1),
(1162, 119, 'upazila', 'Patnitala', 'পত্নিতলা', NULL, NULL, 1),
(1163, 119, 'upazila', 'Dhamoirhat', 'ধামইরহাট', NULL, NULL, 1),
(1164, 119, 'upazila', 'Niamatpur', 'নিয়ামতপুর', NULL, NULL, 1),
(1165, 119, 'upazila', 'Manda', 'মান্দা', NULL, NULL, 1),
(1166, 119, 'upazila', 'Atrai', 'আত্রাই', NULL, NULL, 1),
(1167, 119, 'upazila', 'Raninagar', 'রাণীনগর', NULL, NULL, 1),
(1168, 119, 'upazila', 'Naogaon Sadar', 'নওগাঁ সদর', NULL, NULL, 1),
(1169, 119, 'upazila', 'Porsha', 'পোরশা', NULL, NULL, 1),
(1170, 119, 'upazila', 'Sapahar', 'সাপাহার', NULL, NULL, 1),
(1171, 120, 'upazila', 'Manirampur', 'মণিরামপুর', NULL, NULL, 1),
(1172, 120, 'upazila', 'Abhaynagar', 'অভয়নগর', NULL, NULL, 1),
(1173, 120, 'upazila', 'Bagharpara', 'বাঘারপাড়া', NULL, NULL, 1),
(1174, 120, 'upazila', 'Chaugachha', 'চৌগাছা', NULL, NULL, 1),
(1175, 120, 'upazila', 'Jhikargachha', 'ঝিকরগাছা', NULL, NULL, 1),
(1176, 120, 'upazila', 'Keshabpur', 'কেশবপুর', NULL, NULL, 1),
(1177, 120, 'upazila', 'Jashore Sadar', 'যশোর সদর', NULL, NULL, 1),
(1178, 120, 'upazila', 'Sharsha', 'শার্শা', NULL, NULL, 1),
(1179, 121, 'upazila', 'Ashashuni', 'আশাশুনি', NULL, NULL, 1),
(1180, 121, 'upazila', 'Debhata', 'দেবহাটা', NULL, NULL, 1),
(1181, 121, 'upazila', 'Kalaroa', 'কলারোয়া', NULL, NULL, 1),
(1182, 121, 'upazila', 'Satkhira Sadar', 'সাতক্ষীরা সদর', NULL, NULL, 1),
(1183, 121, 'upazila', 'Shyamnagar', 'শ্যামনগর', NULL, NULL, 1),
(1184, 121, 'upazila', 'Tala', 'তালা', NULL, NULL, 1),
(1185, 121, 'upazila', 'Kaliganj', 'কালিগঞ্জ', NULL, NULL, 1),
(1186, 122, 'upazila', 'Mujibnagar', 'মুজিবনগর', NULL, NULL, 1),
(1187, 122, 'upazila', 'Meherpur Sadar', 'মেহেরপুর সদর', NULL, NULL, 1),
(1188, 122, 'upazila', 'Gangni', 'গাংনী', NULL, NULL, 1),
(1189, 123, 'upazila', 'Narail Sadar', 'নড়াইল সদর', NULL, NULL, 1),
(1190, 123, 'upazila', 'Lohagara', 'লোহাগড়া', NULL, NULL, 1),
(1191, 123, 'upazila', 'Kalia', 'কালিয়া', NULL, NULL, 1),
(1192, 124, 'upazila', 'Chuadanga Sadar', 'চুয়াডাঙ্গা সদর', NULL, NULL, 1),
(1193, 124, 'upazila', 'Alamdanga', 'আলমডাঙ্গা', NULL, NULL, 1),
(1194, 124, 'upazila', 'Damurhuda', 'দামুড়হুদা', NULL, NULL, 1),
(1195, 124, 'upazila', 'Jibannagar', 'জীবননগর', NULL, NULL, 1),
(1196, 125, 'upazila', 'Kushtia Sadar', 'কুষ্টিয়া সদর', NULL, NULL, 1),
(1197, 125, 'upazila', 'Kumarkhali', 'কুমারখালী', NULL, NULL, 1),
(1198, 125, 'upazila', 'Khoksa', 'খোকসা', NULL, NULL, 1),
(1199, 125, 'upazila', 'Mirpur', 'মিরপুর', NULL, NULL, 1),
(1200, 125, 'upazila', 'Daulatpur', 'দৌলতপুর', NULL, NULL, 1),
(1201, 125, 'upazila', 'Bheramara', 'ভেড়ামারা', NULL, NULL, 1),
(1202, 126, 'upazila', 'Shalikha', 'শালিখা', NULL, NULL, 1),
(1203, 126, 'upazila', 'Sreepur', 'শ্রীপুর', NULL, NULL, 1),
(1204, 126, 'upazila', 'Magura Sadar', 'মাগুরা সদর', NULL, NULL, 1),
(1205, 126, 'upazila', 'Mohammadpur', 'মহম্মদপুর', NULL, NULL, 1),
(1206, 127, 'upazila', 'Paikgachha', 'পাইকগাছা', NULL, NULL, 1),
(1207, 127, 'upazila', 'Phultala', 'ফুলতলা', NULL, NULL, 1),
(1208, 127, 'upazila', 'Dighalia', 'দিঘলিয়া', NULL, NULL, 1),
(1209, 127, 'upazila', 'Rupsa', 'রূপসা', NULL, NULL, 1),
(1210, 127, 'upazila', 'Terokhada', 'তেরখাদা', NULL, NULL, 1),
(1211, 127, 'upazila', 'Dumuria', 'ডুমুরিয়া', NULL, NULL, 1),
(1212, 127, 'upazila', 'Batiaghata', 'বটিয়াঘাটা', NULL, NULL, 1),
(1213, 127, 'upazila', 'Dacope', 'দাকোপ', NULL, NULL, 1),
(1214, 127, 'upazila', 'Koyra', 'কয়রা', NULL, NULL, 1),
(1215, 128, 'upazila', 'Fakirhat', 'ফকিরহাট', NULL, NULL, 1),
(1216, 128, 'upazila', 'Bagerhat Sadar', 'বাগেরহাট সদর', NULL, NULL, 1),
(1217, 128, 'upazila', 'Mollahat', 'মোল্লাহাট', NULL, NULL, 1),
(1218, 128, 'upazila', 'Sharankhola', 'শরণখোলা', NULL, NULL, 1),
(1219, 128, 'upazila', 'Rampal', 'রামপাল', NULL, NULL, 1),
(1220, 128, 'upazila', 'Morelganj', 'মোড়েলগঞ্জ', NULL, NULL, 1),
(1221, 128, 'upazila', 'Kachua', 'কচুয়া', NULL, NULL, 1),
(1222, 128, 'upazila', 'Mongla', 'মোংলা', NULL, NULL, 1),
(1223, 128, 'upazila', 'Chitalmari', 'চিতলমারী', NULL, NULL, 1),
(1224, 129, 'upazila', 'Jhenaidah Sadar', 'ঝিনাইদহ সদর', NULL, NULL, 1),
(1225, 129, 'upazila', 'Shailkupa', 'শৈলকুপা', NULL, NULL, 1),
(1226, 129, 'upazila', 'Harinakundu', 'হরিণাকুন্ডু', NULL, NULL, 1),
(1227, 129, 'upazila', 'Kaliganj', 'কালীগঞ্জ', NULL, NULL, 1),
(1228, 129, 'upazila', 'Kotchandpur', 'কোটচাঁদপুর', NULL, NULL, 1),
(1229, 129, 'upazila', 'Maheshpur', 'মহেশপুর', NULL, NULL, 1),
(1230, 130, 'upazila', 'Jhalokathi Sadar', 'ঝালকাঠি সদর', NULL, NULL, 1),
(1231, 130, 'upazila', 'Kanthalia', 'কাঠালিয়া', NULL, NULL, 1),
(1232, 130, 'upazila', 'Nalchhity', 'নলছিটি', NULL, NULL, 1),
(1233, 130, 'upazila', 'Rajapur', 'রাজাপুর', NULL, NULL, 1),
(1234, 131, 'upazila', 'Bauphal', 'বাউফল', NULL, NULL, 1),
(1235, 131, 'upazila', 'Patuakhali Sadar', 'পটুয়াখালী সদর', NULL, NULL, 1),
(1236, 131, 'upazila', 'Dumki', 'দুমকি', NULL, NULL, 1),
(1237, 131, 'upazila', 'Dashmina', 'দশমিনা', NULL, NULL, 1),
(1238, 131, 'upazila', 'Kalapara', 'কলাপাড়া', NULL, NULL, 1),
(1239, 131, 'upazila', 'Mirzaganj', 'মির্জাগঞ্জ', NULL, NULL, 1),
(1240, 131, 'upazila', 'Galachipa', 'গলাচিপা', NULL, NULL, 1),
(1241, 131, 'upazila', 'Rangabali', 'রাঙ্গাবালী', NULL, NULL, 1),
(1242, 132, 'upazila', 'Pirojpur Sadar', 'পিরোজপুর সদর', NULL, NULL, 1),
(1243, 132, 'upazila', 'Nazirpur', 'নাজিরপুর', NULL, NULL, 1),
(1244, 132, 'upazila', 'Kawkhali', 'কাউখালী', NULL, NULL, 1),
(1245, 132, 'upazila', 'Indurkani', 'ইন্দুরকানী', NULL, NULL, 1),
(1246, 132, 'upazila', 'Bhandaria', 'ভান্ডারিয়া', NULL, NULL, 1),
(1247, 132, 'upazila', 'Mathbaria', 'মঠবাড়ীয়া', NULL, NULL, 1),
(1248, 132, 'upazila', 'Nesarabad (Swarupkathi)', 'নেছারাবাদ', NULL, NULL, 1),
(1249, 133, 'upazila', 'Barishal Sadar (Kotwali)', 'বরিশাল সদর', NULL, NULL, 1),
(1250, 133, 'upazila', 'Bakerganj', 'বাকেরগঞ্জ', NULL, NULL, 1),
(1251, 133, 'upazila', 'Babuganj', 'বাবুগঞ্জ', NULL, NULL, 1),
(1252, 133, 'upazila', 'Ujirpur', 'উজিরপুর', NULL, NULL, 1),
(1253, 133, 'upazila', 'Banaripara', 'বানারীপাড়া', NULL, NULL, 1),
(1254, 133, 'upazila', 'Gaurnadi', 'গৌরনদী', NULL, NULL, 1),
(1255, 133, 'upazila', 'Agailjhara', 'আগৈলঝাড়া', NULL, NULL, 1),
(1256, 133, 'upazila', 'Mehendiganj', 'মেহেন্দিগঞ্জ', NULL, NULL, 1),
(1257, 133, 'upazila', 'Muladi', 'মুলাদী', NULL, NULL, 1),
(1258, 133, 'upazila', 'Hijla', 'হিজলা', NULL, NULL, 1),
(1259, 134, 'upazila', 'Bhola Sadar', 'ভোলা সদর', NULL, NULL, 1),
(1260, 134, 'upazila', 'Borhanuddin', 'বোরহান উদ্দিন', NULL, NULL, 1),
(1261, 134, 'upazila', 'Charfasson', 'চরফ্যাশন', NULL, NULL, 1),
(1262, 134, 'upazila', 'Daulatkhan', 'দৌলতখান', NULL, NULL, 1),
(1263, 134, 'upazila', 'Monpura', 'মনপুরা', NULL, NULL, 1),
(1264, 134, 'upazila', 'Tazumuddin', 'তজুমদ্দিন', NULL, NULL, 1),
(1265, 134, 'upazila', 'Lalmohan', 'লালমোহন', NULL, NULL, 1),
(1266, 135, 'upazila', 'Amtali', 'আমতলী', NULL, NULL, 1),
(1267, 135, 'upazila', 'Barguna Sadar', 'বরগুনা সদর', NULL, NULL, 1),
(1268, 135, 'upazila', 'Betagi', 'বেতাগী', NULL, NULL, 1),
(1269, 135, 'upazila', 'Bamna', 'বামনা', NULL, NULL, 1),
(1270, 135, 'upazila', 'Patharghata', 'পাথরঘাটা', NULL, NULL, 1),
(1271, 135, 'upazila', 'Taltali', 'তালতলি', NULL, NULL, 1),
(1272, 136, 'upazila', 'Balaganj', 'বালাগঞ্জ', NULL, NULL, 1),
(1273, 136, 'upazila', 'Beanibazar', 'বিয়ানীবাজার', NULL, NULL, 1),
(1274, 136, 'upazila', 'Bishwanath', 'বিশ্বনাথ', NULL, NULL, 1),
(1275, 136, 'upazila', 'Companiganj', 'কোম্পানীগঞ্জ', NULL, NULL, 1),
(1276, 136, 'upazila', 'Fenchuganj', 'ফেঞ্চুগঞ্জ', NULL, NULL, 1),
(1277, 136, 'upazila', 'Golapganj', 'গোলাপগঞ্জ', NULL, NULL, 1),
(1278, 136, 'upazila', 'Gowainghat', 'গোয়াইনঘাট', NULL, NULL, 1),
(1279, 136, 'upazila', 'Jaintapur', 'জৈন্তাপুর', NULL, NULL, 1),
(1280, 136, 'upazila', 'Kanaighat', 'কানাইঘাট', NULL, NULL, 1),
(1281, 136, 'upazila', 'Sylhet Sadar', 'সিলেট সদর', NULL, NULL, 1),
(1282, 136, 'upazila', 'Zakiganj', 'জকিগঞ্জ', NULL, NULL, 1),
(1283, 136, 'upazila', 'Dakkhin Surma', 'দক্ষিণ সুরমা', NULL, NULL, 1),
(1284, 136, 'upazila', 'Osmaninagar', 'ওসমানী নগর', NULL, NULL, 1),
(1285, 137, 'upazila', 'Baralekha', 'বড়লেখা', NULL, NULL, 1),
(1286, 137, 'upazila', 'Kamalganj', 'কমলগঞ্জ', NULL, NULL, 1),
(1287, 137, 'upazila', 'Kulaura', 'কুলাউড়া', NULL, NULL, 1),
(1288, 137, 'upazila', 'Moulvibazar Sadar', 'মৌলভীবাজার সদর', NULL, NULL, 1),
(1289, 137, 'upazila', 'Rajnagar', 'রাজনগর', NULL, NULL, 1),
(1290, 137, 'upazila', 'Sreemangal', 'শ্রীমঙ্গল', NULL, NULL, 1),
(1291, 137, 'upazila', 'Juri', 'জুড়ী', NULL, NULL, 1),
(1292, 138, 'upazila', 'Nabiganj', 'নবীগঞ্জ', NULL, NULL, 1),
(1293, 138, 'upazila', 'Bahubal', 'বাহুবল', NULL, NULL, 1),
(1294, 138, 'upazila', 'Ajmiriganj', 'আজমিরীগঞ্জ', NULL, NULL, 1),
(1295, 138, 'upazila', 'Baniachong', 'বানিয়াচং', NULL, NULL, 1),
(1296, 138, 'upazila', 'Lakhai', 'লাখাই', NULL, NULL, 1),
(1297, 138, 'upazila', 'Chunarughat', 'চুনারুঘাট', NULL, NULL, 1),
(1298, 138, 'upazila', 'Habiganj Sadar', 'হবিগঞ্জ সদর', NULL, NULL, 1),
(1299, 138, 'upazila', 'Madhabpur', 'মাধবপুর', NULL, NULL, 1),
(1300, 139, 'upazila', 'Sunamganj Sadar', 'সুনামগঞ্জ সদর', NULL, NULL, 1),
(1301, 139, 'upazila', 'Shantiganj', 'শান্তিগঞ্জ', NULL, NULL, 1),
(1302, 139, 'upazila', 'Bishwambharpur', 'বিশ্বম্ভরপুর', NULL, NULL, 1),
(1303, 139, 'upazila', 'Chhatak', 'ছাতক', NULL, NULL, 1),
(1304, 139, 'upazila', 'Jagannathpur', 'জগন্নাথপুর', NULL, NULL, 1),
(1305, 139, 'upazila', 'Dowarabazar', 'দোয়ারাবাজার', NULL, NULL, 1),
(1306, 139, 'upazila', 'Tahirpur', 'তাহিরপুর', NULL, NULL, 1),
(1307, 139, 'upazila', 'Dharmapasha', 'ধর্মপাশা', NULL, NULL, 1),
(1308, 139, 'upazila', 'Jamalganj', 'জামালগঞ্জ', NULL, NULL, 1),
(1309, 139, 'upazila', 'Shalla', 'শাল্লা', NULL, NULL, 1),
(1310, 139, 'upazila', 'Derai', 'দিরাই', NULL, NULL, 1),
(1311, 140, 'upazila', 'Belabo', 'বেলাবো', NULL, NULL, 1),
(1312, 140, 'upazila', 'Manohardi', 'মনোহরদী', NULL, NULL, 1),
(1313, 140, 'upazila', 'Narsingdi Sadar', 'নরসিংদী সদর', NULL, NULL, 1),
(1314, 140, 'upazila', 'Palash', 'পলাশ', NULL, NULL, 1),
(1315, 140, 'upazila', 'Raipura', 'রায়পুরা', NULL, NULL, 1),
(1316, 140, 'upazila', 'Shibpur', 'শিবপুর', NULL, NULL, 1),
(1317, 141, 'upazila', 'Kaliganj', 'কালীগঞ্জ', NULL, NULL, 1),
(1318, 141, 'upazila', 'Kaliakair', 'কালিয়াকৈর', NULL, NULL, 1),
(1319, 141, 'upazila', 'Kapasia', 'কাপাসিয়া', NULL, NULL, 1),
(1320, 141, 'upazila', 'Gazipur Sadar', 'গাজীপুর সদর', NULL, NULL, 1),
(1321, 141, 'upazila', 'Sreepur', 'শ্রীপুর', NULL, NULL, 1),
(1322, 142, 'upazila', 'Shariatpur Sadar', 'শরিয়তপুর সদর', NULL, NULL, 1),
(1323, 142, 'upazila', 'Naria', 'নড়িয়া', NULL, NULL, 1),
(1324, 142, 'upazila', 'Zajira', 'জাজিরা', NULL, NULL, 1),
(1325, 142, 'upazila', 'Gosairhat', 'গোসাইরহাট', NULL, NULL, 1),
(1326, 142, 'upazila', 'Bhedarganj', 'ভেদরগঞ্জ', NULL, NULL, 1),
(1327, 142, 'upazila', 'Damudya', 'ডামুড্যা', NULL, NULL, 1),
(1328, 143, 'upazila', 'Araihazar', 'আড়াইহাজার', NULL, NULL, 1),
(1329, 143, 'upazila', 'Bandar', 'বন্দর', NULL, NULL, 1),
(1330, 143, 'upazila', 'Narayanganj Sadar', 'নারায়নগঞ্জ সদর', NULL, NULL, 1),
(1331, 143, 'upazila', 'Rupganj', 'রূপগঞ্জ', NULL, NULL, 1),
(1332, 143, 'upazila', 'Sonargaon', 'সোনারগাঁ', NULL, NULL, 1),
(1333, 144, 'upazila', 'Basail', 'বাসাইল', NULL, NULL, 1),
(1334, 144, 'upazila', 'Bhuanpur', 'ভুয়াপুর', NULL, NULL, 1),
(1335, 144, 'upazila', 'Delduar', 'দেলদুয়ার', NULL, NULL, 1),
(1336, 144, 'upazila', 'Ghatail', 'ঘাটাইল', NULL, NULL, 1),
(1337, 144, 'upazila', 'Gopalpur', 'গোপালপুর', NULL, NULL, 1),
(1338, 144, 'upazila', 'Madhupur', 'মধুপুর', NULL, NULL, 1),
(1339, 144, 'upazila', 'Mirzapur', 'মির্জাপুর', NULL, NULL, 1),
(1340, 144, 'upazila', 'Nagarpur', 'নাগরপুর', NULL, NULL, 1),
(1341, 144, 'upazila', 'Sakhipur', 'সখিপুর', NULL, NULL, 1),
(1342, 144, 'upazila', 'Tangail Sadar', 'টাঙ্গাইল সদর', NULL, NULL, 1),
(1343, 144, 'upazila', 'Kalihati', 'কালিহাতী', NULL, NULL, 1),
(1344, 144, 'upazila', 'Dhanbari', 'ধনবাড়ী', NULL, NULL, 1),
(1345, 145, 'upazila', 'Itna', 'ইটনা', NULL, NULL, 1),
(1346, 145, 'upazila', 'Katiadi', 'কটিয়াদী', NULL, NULL, 1),
(1347, 145, 'upazila', 'Bhairab', 'ভৈরব', NULL, NULL, 1),
(1348, 145, 'upazila', 'Tarail', 'তাড়াইল', NULL, NULL, 1),
(1349, 145, 'upazila', 'Hossainpur', 'হোসেনপুর', NULL, NULL, 1),
(1350, 145, 'upazila', 'Pakundia', 'পাকুন্দিয়া', NULL, NULL, 1),
(1351, 145, 'upazila', 'Kuliarchar', 'কুলিয়ারচর', NULL, NULL, 1),
(1352, 145, 'upazila', 'Kishoreganj Sadar', 'কিশোরগঞ্জ সদর', NULL, NULL, 1),
(1353, 145, 'upazila', 'Karimganj', 'করিমগঞ্জ', NULL, NULL, 1),
(1354, 145, 'upazila', 'Bajitpur', 'বাজিতপুর', NULL, NULL, 1),
(1355, 145, 'upazila', 'Austagram', 'অষ্টগ্রাম', NULL, NULL, 1),
(1356, 145, 'upazila', 'Mithamain', 'মিঠামইন', NULL, NULL, 1),
(1357, 145, 'upazila', 'Nikli', 'নিকলী', NULL, NULL, 1),
(1358, 146, 'upazila', 'Harirampur', 'হরিরামপুর', NULL, NULL, 1),
(1359, 146, 'upazila', 'Saturia', 'সাটুরিয়া', NULL, NULL, 1),
(1360, 146, 'upazila', 'Manikganj Sadar', 'মানিকগঞ্জ সদর', NULL, NULL, 1),
(1361, 146, 'upazila', 'Ghior', 'ঘিওর', NULL, NULL, 1),
(1362, 146, 'upazila', 'Shibalay', 'শিবালয়', NULL, NULL, 1),
(1363, 146, 'upazila', 'Daulatpur', 'দৌলতপুর', NULL, NULL, 1),
(1364, 146, 'upazila', 'Singair', 'সিংগাইর', NULL, NULL, 1),
(1365, 147, 'upazila', 'Savar', 'সাভার', NULL, NULL, 1),
(1366, 147, 'upazila', 'Dhamrai', 'ধামরাই', NULL, NULL, 1),
(1367, 147, 'upazila', 'Keraniganj', 'কেরাণীগঞ্জ', NULL, NULL, 1),
(1368, 147, 'upazila', 'Nawabganj', 'নবাবগঞ্জ', NULL, NULL, 1),
(1369, 147, 'upazila', 'Dohar', 'দোহার', NULL, NULL, 1),
(1370, 148, 'upazila', 'Munshiganj Sadar', 'মুন্সিগঞ্জ সদর', NULL, NULL, 1),
(1371, 148, 'upazila', 'Sreenagar', 'শ্রীনগর', NULL, NULL, 1),
(1372, 148, 'upazila', 'Sirajdikhan', 'সিরাজদিখান', NULL, NULL, 1),
(1373, 148, 'upazila', 'Louhajang', 'লৌহজং', NULL, NULL, 1),
(1374, 148, 'upazila', 'Gazaria', 'গজারিয়া', NULL, NULL, 1),
(1375, 148, 'upazila', 'Tongibari', 'টংগীবাড়ি', NULL, NULL, 1),
(1376, 149, 'upazila', 'Rajbari Sadar', 'রাজবাড়ী সদর', NULL, NULL, 1),
(1377, 149, 'upazila', 'Goalanda', 'গোয়ালন্দ', NULL, NULL, 1),
(1378, 149, 'upazila', 'Pangsha', 'পাংশা', NULL, NULL, 1),
(1379, 149, 'upazila', 'Baliakandi', 'বালিয়াকান্দি', NULL, NULL, 1),
(1380, 149, 'upazila', 'Kalukhali', 'কালুখালী', NULL, NULL, 1),
(1381, 150, 'upazila', 'Madaripur Sadar', 'মাদারীপুর সদর', NULL, NULL, 1),
(1382, 150, 'upazila', 'Shibchar', 'শিবচর', NULL, NULL, 1),
(1383, 150, 'upazila', 'Kalkini', 'কালকিনি', NULL, NULL, 1),
(1384, 150, 'upazila', 'Rajoir', 'রাজৈর', NULL, NULL, 1),
(1385, 151, 'upazila', 'Gopalganj Sadar', 'গোপালগঞ্জ সদর', NULL, NULL, 1),
(1386, 151, 'upazila', 'Kashiani', 'কাশিয়ানী', NULL, NULL, 1),
(1387, 151, 'upazila', 'Tungipara', 'টুংগীপাড়া', NULL, NULL, 1),
(1388, 151, 'upazila', 'Kotalipara', 'কোটালীপাড়া', NULL, NULL, 1),
(1389, 151, 'upazila', 'Muksudpur', 'মুকসুদপুর', NULL, NULL, 1),
(1390, 152, 'upazila', 'Faridpur Sadar', 'ফরিদপুর সদর', NULL, NULL, 1),
(1391, 152, 'upazila', 'Alfadanga', 'আলফাডাঙ্গা', NULL, NULL, 1),
(1392, 152, 'upazila', 'Boalmari', 'বোয়ালমারী', NULL, NULL, 1),
(1393, 152, 'upazila', 'Sadarpur', 'সদরপুর', NULL, NULL, 1),
(1394, 152, 'upazila', 'Nagarkanda', 'নগরকান্দা', NULL, NULL, 1),
(1395, 152, 'upazila', 'Bhanga', 'ভাঙ্গা', NULL, NULL, 1),
(1396, 152, 'upazila', 'Char Bhadrasan', 'চরভদ্রাসন', NULL, NULL, 1),
(1397, 152, 'upazila', 'Madhukhali', 'মধুখালী', NULL, NULL, 1),
(1398, 152, 'upazila', 'Saltha', 'সালথা', NULL, NULL, 1),
(1399, 153, 'upazila', 'Panchagarh Sadar', 'পঞ্চগড় সদর', NULL, NULL, 1),
(1400, 153, 'upazila', 'Debiganj', 'দেবীগঞ্জ', NULL, NULL, 1),
(1401, 153, 'upazila', 'Boda', 'বোদা', NULL, NULL, 1),
(1402, 153, 'upazila', 'Atowari', 'আটোয়ারী', NULL, NULL, 1),
(1403, 153, 'upazila', 'Tentulia', 'তেতুলিয়া', NULL, NULL, 1),
(1404, 154, 'upazila', 'Nababganj', 'নবাবগঞ্জ', NULL, NULL, 1),
(1405, 154, 'upazila', 'Birganj', 'বীরগঞ্জ', NULL, NULL, 1),
(1406, 154, 'upazila', 'Ghoraghat', 'ঘোড়াঘাট', NULL, NULL, 1),
(1407, 154, 'upazila', 'Birampur', 'বিরামপুর', NULL, NULL, 1),
(1408, 154, 'upazila', 'Parbatipur', 'পার্বতীপুর', NULL, NULL, 1),
(1409, 154, 'upazila', 'Bochaganj', 'বোচাগঞ্জ', NULL, NULL, 1),
(1410, 154, 'upazila', 'Kaharole', 'কাহারোল', NULL, NULL, 1),
(1411, 154, 'upazila', 'Fulbari', 'ফুলবাড়ী', NULL, NULL, 1),
(1412, 154, 'upazila', 'Dinajpur Sadar', 'দিনাজপুর সদর', NULL, NULL, 1),
(1413, 154, 'upazila', 'Hakimpur', 'হাকিমপুর', NULL, NULL, 1),
(1414, 154, 'upazila', 'Khansama', 'খানসামা', NULL, NULL, 1),
(1415, 154, 'upazila', 'Birol', 'বিরল', NULL, NULL, 1),
(1416, 154, 'upazila', 'Chirirbandar', 'চিরিরবন্দর', NULL, NULL, 1),
(1417, 155, 'upazila', 'Lalmonirhat Sadar', 'লালমনিরহাট সদর', NULL, NULL, 1),
(1418, 155, 'upazila', 'Kaliganj', 'কালীগঞ্জ', NULL, NULL, 1),
(1419, 155, 'upazila', 'Hatibandha', 'হাতীবান্ধা', NULL, NULL, 1),
(1420, 155, 'upazila', 'Patgram', 'পাটগ্রাম', NULL, NULL, 1),
(1421, 155, 'upazila', 'Aditmari', 'আদিতমারী', NULL, NULL, 1),
(1422, 156, 'upazila', 'Saidpur', 'সৈয়দপুর', NULL, NULL, 1),
(1423, 156, 'upazila', 'Domar', 'ডোমার', NULL, NULL, 1),
(1424, 156, 'upazila', 'Dimla', 'ডিমলা', NULL, NULL, 1),
(1425, 156, 'upazila', 'Jaldhaka', 'জলঢাকা', NULL, NULL, 1),
(1426, 156, 'upazila', 'Kishoreganj', 'কিশোরগঞ্জ', NULL, NULL, 1),
(1427, 156, 'upazila', 'Nilphamari Sadar', 'নীলফামারী সদর', NULL, NULL, 1),
(1428, 157, 'upazila', 'Sadullapur', 'সাদুল্লাপুর', NULL, NULL, 1),
(1429, 157, 'upazila', 'Gaibandha Sadar', 'গাইবান্ধা সদর', NULL, NULL, 1),
(1430, 157, 'upazila', 'Palashbari', 'পলাশবাড়ী', NULL, NULL, 1),
(1431, 157, 'upazila', 'Saghata', 'সাঘাটা', NULL, NULL, 1),
(1432, 157, 'upazila', 'Gobindaganj', 'গোবিন্দগঞ্জ', NULL, NULL, 1),
(1433, 157, 'upazila', 'Sundarganj', 'সুন্দরগঞ্জ', NULL, NULL, 1),
(1434, 157, 'upazila', 'Fulchhari', 'ফুলছড়ি', NULL, NULL, 1),
(1435, 158, 'upazila', 'Thakurgaon Sadar', 'ঠাকুরগাঁও সদর', NULL, NULL, 1),
(1436, 158, 'upazila', 'Pirganj', 'পীরগঞ্জ', NULL, NULL, 1),
(1437, 158, 'upazila', 'Ranishankail', 'রাণীশংকৈল', NULL, NULL, 1),
(1438, 158, 'upazila', 'Haripur', 'হরিপুর', NULL, NULL, 1),
(1439, 158, 'upazila', 'Baliadangi', 'বালিয়াডাঙ্গী', NULL, NULL, 1),
(1440, 159, 'upazila', 'Rangpur Sadar', 'রংপুর সদর', NULL, NULL, 1),
(1441, 159, 'upazila', 'Gangachara', 'গংগাচড়া', NULL, NULL, 1),
(1442, 159, 'upazila', 'Taraganj', 'তারাগঞ্জ', NULL, NULL, 1),
(1443, 159, 'upazila', 'Badarganj', 'বদরগঞ্জ', NULL, NULL, 1),
(1444, 159, 'upazila', 'Mithapukur', 'মিঠাপুকুর', NULL, NULL, 1),
(1445, 159, 'upazila', 'Pirganj', 'পীরগঞ্জ', NULL, NULL, 1),
(1446, 159, 'upazila', 'Kaunia', 'কাউনিয়া', NULL, NULL, 1),
(1447, 159, 'upazila', 'Pirgachha', 'পীরগাছা', NULL, NULL, 1),
(1448, 160, 'upazila', 'Kurigram Sadar', 'কুড়িগ্রাম সদর', NULL, NULL, 1),
(1449, 160, 'upazila', 'Nageshwari', 'নাগেশ্বরী', NULL, NULL, 1),
(1450, 160, 'upazila', 'Bhurungamari', 'ভুরুঙ্গামারী', NULL, NULL, 1),
(1451, 160, 'upazila', 'Phulbari', 'ফুলবাড়ী', NULL, NULL, 1),
(1452, 160, 'upazila', 'Rajarhat', 'রাজারহাট', NULL, NULL, 1),
(1453, 160, 'upazila', 'Ulipur', 'উলিপুর', NULL, NULL, 1),
(1454, 160, 'upazila', 'Chilmari', 'চিলমারী', NULL, NULL, 1),
(1455, 160, 'upazila', 'Roumari', 'রৌমারী', NULL, NULL, 1),
(1456, 160, 'upazila', 'Rajibpur', 'চর রাজিবপুর', NULL, NULL, 1),
(1457, 161, 'upazila', 'Sherpur Sadar', 'শেরপুর সদর', NULL, NULL, 1),
(1458, 161, 'upazila', 'Nalitabari', 'নালিতাবাড়ী', NULL, NULL, 1),
(1459, 161, 'upazila', 'Sreebardi', 'শ্রীবরদী', NULL, NULL, 1),
(1460, 161, 'upazila', 'Nakla', 'নকলা', NULL, NULL, 1),
(1461, 161, 'upazila', 'Jhenaigati', 'ঝিনাইগাতী', NULL, NULL, 1),
(1462, 162, 'upazila', 'Fulbaria', 'ফুলবাড়ীয়া', NULL, NULL, 1),
(1463, 162, 'upazila', 'Trishal', 'ত্রিশাল', NULL, NULL, 1),
(1464, 162, 'upazila', 'Bhaluka', 'ভালুকা', NULL, NULL, 1),
(1465, 162, 'upazila', 'Muktagachha', 'মুক্তাগাছা', NULL, NULL, 1),
(1466, 162, 'upazila', 'Mymensingh Sadar', 'ময়মনসিংহ সদর', NULL, NULL, 1),
(1467, 162, 'upazila', 'Dhobaura', 'ধোবাউড়া', NULL, NULL, 1),
(1468, 162, 'upazila', 'Fulpur', 'ফুলপুর', NULL, NULL, 1),
(1469, 162, 'upazila', 'Haluaghat', 'হালুয়াঘাট', NULL, NULL, 1),
(1470, 162, 'upazila', 'Gouripur', 'গৌরীপুর', NULL, NULL, 1),
(1471, 162, 'upazila', 'Gafargaon', 'গফরগাঁও', NULL, NULL, 1),
(1472, 162, 'upazila', 'Ishwarganj', 'ঈশ্বরগঞ্জ', NULL, NULL, 1),
(1473, 162, 'upazila', 'Nandail', 'নান্দাইল', NULL, NULL, 1),
(1474, 162, 'upazila', 'Tarakanda', 'তারাকান্দা', NULL, NULL, 1),
(1475, 163, 'upazila', 'Jamalpur Sadar', 'জামালপুর সদর', NULL, NULL, 1),
(1476, 163, 'upazila', 'Melandaha', 'মেলান্দহ', NULL, NULL, 1),
(1477, 163, 'upazila', 'Islampur', 'ইসলামপুর', NULL, NULL, 1),
(1478, 163, 'upazila', 'Dewanganj', 'দেওয়ানগঞ্জ', NULL, NULL, 1),
(1479, 163, 'upazila', 'Sarishabari', 'সরিষাবাড়ী', NULL, NULL, 1),
(1480, 163, 'upazila', 'Madarganj', 'মাদারগঞ্জ', NULL, NULL, 1),
(1481, 163, 'upazila', 'Bakshiganj', 'বকশীগঞ্জ', NULL, NULL, 1),
(1482, 164, 'upazila', 'Barhatta', 'বারহাট্টা', NULL, NULL, 1),
(1483, 164, 'upazila', 'Durgapur', 'দুর্গাপুর', NULL, NULL, 1),
(1484, 164, 'upazila', 'Kendua', 'কেন্দুয়া', NULL, NULL, 1),
(1485, 164, 'upazila', 'Atpara', 'আটপাড়া', NULL, NULL, 1),
(1486, 164, 'upazila', 'Madan', 'মদন', NULL, NULL, 1),
(1487, 164, 'upazila', 'Khaliajuri', 'খালিয়াজুরী', NULL, NULL, 1),
(1488, 164, 'upazila', 'Kalmakanda', 'কলমাকান্দা', NULL, NULL, 1),
(1489, 164, 'upazila', 'Mohanganj', 'মোহনগঞ্জ', NULL, NULL, 1),
(1490, 164, 'upazila', 'Purbadhala', 'পূর্বধলা', NULL, NULL, 1),
(1491, 164, 'upazila', 'Netrakona Sadar', 'নেত্রকোণা সদর', NULL, NULL, 1),
(1492, 109, 'upazila', 'Eidgaon', 'ঈদগাঁও', NULL, NULL, 1),
(1493, 139, 'upazila', 'Madhyanagar', 'মধ্যনগর', NULL, NULL, 1),
(1494, 150, 'upazila', 'Dasar', 'ডাসার', NULL, NULL, 1),
(1495, 138, 'upazila', 'Shayestaganj', 'শায়েস্তাগঞ্জ', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `message_logs`
--

CREATE TABLE `message_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `channel` enum('sms','email') NOT NULL,
  `recipient` varchar(190) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `event` varchar(50) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('queued','sent','failed','logged') NOT NULL DEFAULT 'queued',
  `provider` varchar(30) DEFAULT NULL,
  `response` varchar(1000) DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `message_logs`
--

INSERT INTO `message_logs` (`id`, `channel`, `recipient`, `subject`, `body`, `event`, `user_id`, `status`, `provider`, `response`, `attempts`, `sent_at`, `created_at`) VALUES
(1, 'sms', '8801798060887', NULL, 'স্বাস্থ্যবাজার: আপনার অর্ডারের অংশ SB26112144603-1: পাঠানো হয়েছে - পিকআপ হয়েছে', 'order_shipped', 33, 'logged', 'log', 'SMS sending is off - message logged only', 1, NULL, '2026-11-21 09:36:44'),
(2, 'sms', '8801798060887', NULL, 'স্বাস্থ্যবাজার: আপনার অর্ডারের অংশ SB26112144603-1: ডেলিভারি হয়েছে - ডেলিভারি হয়েছে', 'order_delivered', 33, 'logged', 'log', 'SMS sending is off - message logged only', 1, NULL, '2026-11-21 09:37:29'),
(32, 'sms', '8801896499266', NULL, 'স্বাস্থ্যবাজার: আপনার শপ অনুমোদিত হয়েছে', 'vendor_status', 65, 'logged', 'log', 'SMS sending is off - message logged only', 1, NULL, '2026-11-21 15:13:23'),
(33, 'email', 'toriqulislam333000@gmail.com', 'আপনার শপ অনুমোদিত হয়েছে', '<p>প্রিয় rupok,</p><p>আপনার শপ অনুমোদিত হয়েছে</p><p><a href=\"http://localhost/shasthobazar/vendor-panel\" style=\"display:inline-block;background:#0e7673;color:#fff;padding:9px 16px;border-radius:6px;text-decoration:none\">বিস্তারিত দেখুন</a></p>', 'vendor_status', 65, 'logged', 'log', 'Email sending is off - message logged only', 1, NULL, '2026-11-21 15:13:23');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(191) NOT NULL,
  `batch` int(10) UNSIGNED NOT NULL,
  `ran_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`, `ran_at`) VALUES
(1, '2026_11_20_000001_auth_rbac.php', 1, '2026-11-20 21:51:32'),
(2, '2026_11_20_000002_catalog.php', 2, '2026-11-20 22:16:31'),
(3, '2026_11_20_000003_inventory.php', 3, '2026-11-20 22:25:33'),
(4, '2026_11_21_000004_orders.php', 4, '2026-11-21 02:02:47'),
(5, '2026_11_21_000005_vendors.php', 5, '2026-11-21 02:34:06'),
(6, '2026_11_21_000006_shipping.php', 6, '2026-11-21 03:01:13'),
(7, '2026_11_22_000007_offers.php', 7, '2026-11-21 08:19:27'),
(8, '2026_11_23_000008_accounting.php', 8, '2026-11-21 09:00:22'),
(9, '2026_11_24_000009_cms_support.php', 9, '2026-11-21 09:24:28'),
(10, '2026_11_25_000010_utf8mb4.php', 10, '2026-11-21 13:50:58'),
(11, '2026_11_25_000011_guest_checkout.php', 11, '2026-11-21 13:58:31'),
(12, '2026_11_26_000012_prescription_requests.php', 12, '2026-11-21 14:12:31'),
(13, '2026_11_26_000013_vendor_types.php', 13, '2026-11-21 14:20:05'),
(14, '2026_11_26_000014_permission_cleanup.php', 14, '2026-11-21 14:34:15');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'order_status, low_stock, payout, ticket...',
  `title` varchar(200) NOT NULL,
  `message` varchar(1000) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `channel` enum('database','sms','email','push') NOT NULL DEFAULT 'database',
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `data`, `channel`, `read_at`, `created_at`) VALUES
(60, 33, 'order', 'অর্ডার SB26112144603 পাওয়া গেছে', NULL, '/shasthobazar/account/orders/SB26112144603', NULL, 'database', NULL, '2026-11-21 03:32:48'),
(61, 1, 'order', 'নতুন অর্ডার SB26112144603 (৳৮৭৬.৬৮)', NULL, '/shasthobazar/admin/orders/17', NULL, 'database', '2026-11-21 12:58:28', '2026-11-21 03:32:48'),
(62, 33, 'order', 'অর্ডার SB26112144603: কনফার্মড', NULL, '/shasthobazar/account/orders/SB26112144603', NULL, 'database', NULL, '2026-11-21 03:39:08'),
(63, 33, 'order', 'অর্ডার SB26112144603: প্রস্তুত হচ্ছে', NULL, '/shasthobazar/account/orders/SB26112144603', NULL, 'database', NULL, '2026-11-21 03:39:22'),
(80, 33, 'order', 'আপনার অর্ডারের অংশ SB26112144603-1: পাঠানো হয়েছে', 'পিকআপ হয়েছে', '/shasthobazar/account/orders/SB26112144603', NULL, 'database', NULL, '2026-11-21 09:36:44'),
(81, 33, 'order', 'আপনার অর্ডারের অংশ SB26112144603-1: ডেলিভারি হয়েছে', 'ডেলিভারি হয়েছে', '/shasthobazar/account/orders/SB26112144603', NULL, 'database', NULL, '2026-11-21 09:37:29'),
(91, 1, 'stock', 'মেয়াদ যাচাই: 0টি ব্যাচ মেয়াদোত্তীর্ণ, 90দিনের মধ্যে 1টি শেষ হবে', NULL, '/admin/reports/expiry', NULL, 'database', NULL, '2026-11-21 13:33:33'),
(108, 1, 'vendor', 'নতুন বিক্রেতা আবেদন: rupok firma', 'অন্যান্য', '/shasthobazar/admin/vendors/23', NULL, 'database', '2026-11-21 15:13:09', '2026-11-21 15:12:41'),
(109, 65, 'vendor', 'আপনার শপ অনুমোদিত হয়েছে', NULL, '/shasthobazar/vendor-panel', NULL, 'database', NULL, '2026-11-21 15:13:23');

-- --------------------------------------------------------

--
-- Table structure for table `offers`
--

CREATE TABLE `offers` (
  `id` int(10) UNSIGNED NOT NULL,
  `title_en` varchar(200) NOT NULL,
  `title_bn` varchar(200) DEFAULT NULL,
  `slug` varchar(220) NOT NULL,
  `description` text DEFAULT NULL,
  `kind` enum('price','bogo','first_order','free_shipping') NOT NULL DEFAULT 'price',
  `type` enum('percent','fixed','bogo','bundle') NOT NULL DEFAULT 'percent',
  `value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_discount` decimal(12,2) DEFAULT NULL,
  `buy_qty` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `get_qty` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `min_order_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `applies_to` enum('all','category','product','vendor','brand') NOT NULL DEFAULT 'product',
  `applies_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`applies_ids`)),
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `funded_by` enum('platform','vendor','shared') NOT NULL DEFAULT 'platform',
  `vendor_share_percent` decimal(5,2) NOT NULL DEFAULT 50.00,
  `banner` varchar(255) DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `requested_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_no` varchar(30) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_guest` tinyint(1) NOT NULL DEFAULT 0,
  `contact_verified` tinyint(1) NOT NULL DEFAULT 0,
  `customer_name` varchar(150) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_email` varchar(191) DEFAULT NULL,
  `shipping_name` varchar(150) NOT NULL,
  `shipping_phone` varchar(20) NOT NULL,
  `shipping_address` varchar(255) NOT NULL,
  `division_id` int(10) UNSIGNED DEFAULT NULL,
  `district_id` int(10) UNSIGNED DEFAULT NULL,
  `upazila_id` int(10) UNSIGNED DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `shipping_zone_id` int(10) UNSIGNED DEFAULT NULL,
  `shipping_rate_id` int(10) UNSIGNED DEFAULT NULL,
  `delivery_option` enum('standard','express','pickup') NOT NULL DEFAULT 'standard',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coupon_id` int(10) UNSIGNED DEFAULT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `promo_discount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'buy-X-get-Y + first-order offer',
  `shipping_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cod_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vat_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `refunded_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cod','bkash','nagad','rocket','sslcommerz','card','bank','wallet') NOT NULL DEFAULT 'cod',
  `payment_status` enum('unpaid','partial','paid','refunded','partially_refunded','failed') NOT NULL DEFAULT 'unpaid',
  `status` enum('pending','prescription_review','confirmed','processing','packed','shipped','out_for_delivery','delivered','cancelled','returned','failed') NOT NULL DEFAULT 'pending',
  `requires_prescription` tinyint(1) NOT NULL DEFAULT 0,
  `prescription_id` bigint(20) UNSIGNED DEFAULT NULL,
  `customer_note` varchar(500) DEFAULT NULL,
  `admin_note` varchar(500) DEFAULT NULL,
  `source` enum('web','mobile','admin','phone') NOT NULL DEFAULT 'web',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `placed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'staff who created phone/admin orders',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_no`, `user_id`, `is_guest`, `contact_verified`, `customer_name`, `customer_phone`, `customer_email`, `shipping_name`, `shipping_phone`, `shipping_address`, `division_id`, `district_id`, `upazila_id`, `postcode`, `shipping_zone_id`, `shipping_rate_id`, `delivery_option`, `subtotal`, `discount_total`, `coupon_id`, `coupon_code`, `coupon_discount`, `promo_discount`, `shipping_total`, `cod_charge`, `vat_total`, `grand_total`, `paid_amount`, `refunded_amount`, `commission_total`, `payment_method`, `payment_status`, `status`, `requires_prescription`, `prescription_id`, `customer_note`, `admin_note`, `source`, `ip_address`, `user_agent`, `placed_at`, `confirmed_at`, `delivered_at`, `cancelled_at`, `cancel_reason`, `created_by`, `created_at`, `updated_at`) VALUES
(17, 'SB26112144603', 33, 0, 0, 'Mahbub Sajon', '01798060887', NULL, 'Mahbub Sajon', '01798060887', 'nadipara', 4, 135, 1266, NULL, 3, 4, 'standard', 738.00, 0.00, NULL, NULL, 0.00, 0.00, 130.00, 8.68, 0.00, 876.68, 876.68, 0.00, 0.00, 'cod', 'paid', 'delivered', 0, NULL, NULL, NULL, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', '2026-11-21 03:32:48', '2026-11-21 03:39:08', '2026-11-21 09:37:29', NULL, NULL, NULL, '2026-11-21 03:32:48', '2026-11-21 09:37:29');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `split_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_name` varchar(255) NOT NULL COMMENT 'snapshot',
  `variant_name` varchar(150) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `mrp` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(12,2) NOT NULL,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `offer_discount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'flash / price-offer saving already inside unit_price',
  `vendor_funded` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'part of all discounts borne by the vendor',
  `platform_funded` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'part borne by the platform',
  `promo_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`promo_info`)),
  `vat_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `vat_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vat_in_price` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = VAT included in line_total, 0 = added on top',
  `line_total` decimal(12,2) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL COMMENT 'for profit reports',
  `commission_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `commission_scope` varchar(20) DEFAULT NULL COMMENT 'product / category / vendor / global',
  `commission_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `requires_prescription` tinyint(1) NOT NULL DEFAULT 0,
  `batch_allocations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '[{batch_id, qty}] FEFO allocation' CHECK (json_valid(`batch_allocations`)),
  `returned_qty` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('active','cancelled','returned') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `split_id`, `product_id`, `variant_id`, `vendor_id`, `product_name`, `variant_name`, `sku`, `quantity`, `mrp`, `unit_price`, `discount_amount`, `offer_discount`, `vendor_funded`, `platform_funded`, `promo_info`, `vat_rate`, `vat_amount`, `vat_in_price`, `line_total`, `unit_cost`, `commission_rate`, `commission_type`, `commission_scope`, `commission_amount`, `requires_prescription`, `batch_allocations`, `returned_qty`, `status`, `created_at`, `updated_at`) VALUES
(30, 17, 23, 64, NULL, NULL, 'Filwel Gold', NULL, 'DEMO-FILWEL', 2, 450.00, 369.00, 0.00, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 1, 738.00, 344.40, 0.00, 'percent', 'in_house', 0.00, 0, '[{\"batch_id\":63,\"batch_no\":\"DEMO-FILWEL-A\",\"expiry\":\"2028-05-21\",\"cost\":344.4,\"qty\":2,\"returned\":0}]', 0, 'active', '2026-11-21 03:32:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `split_id` bigint(20) UNSIGNED DEFAULT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `note` varchar(500) DEFAULT NULL,
  `notify_customer` tinyint(1) NOT NULL DEFAULT 0,
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`id`, `order_id`, `split_id`, `from_status`, `to_status`, `note`, `notify_customer`, `changed_by`, `created_at`) VALUES
(83, 17, NULL, NULL, 'pending', 'অর্ডার করা হয়েছে', 1, 33, '2026-11-21 03:32:48'),
(84, 17, NULL, 'pending', 'confirmed', NULL, 1, 1, '2026-11-21 03:39:08'),
(85, 17, NULL, 'confirmed', 'processing', NULL, 1, 1, '2026-11-21 03:39:22'),
(104, 17, 23, 'processing', 'shipped', 'পিকআপ হয়েছে', 0, 1, '2026-11-21 09:36:44'),
(105, 17, NULL, 'processing', 'shipped', 'পিকআপ হয়েছে', 0, 1, '2026-11-21 09:36:44'),
(106, 17, 23, 'shipped', 'delivered', 'ডেলিভারি হয়েছে', 0, 1, '2026-11-21 09:37:29'),
(107, 17, NULL, 'shipped', 'delivered', 'ডেলিভারি হয়েছে', 0, 1, '2026-11-21 09:37:29');

-- --------------------------------------------------------

--
-- Table structure for table `order_vendor_splits`
--

CREATE TABLE `order_vendor_splits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sub_order_no` varchar(40) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vat_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shipping_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vendor_earning` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'grand_total - commission - vendor-funded discounts',
  `status` enum('pending','prescription_review','confirmed','processing','packed','shipped','out_for_delivery','delivered','cancelled','returned','failed') NOT NULL DEFAULT 'pending',
  `settlement_status` enum('unsettled','pending','eligible','settled','reversed') NOT NULL DEFAULT 'unsettled',
  `settled_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `release_at` datetime DEFAULT NULL COMMENT 'earning becomes withdrawable',
  `vendor_note` varchar(255) DEFAULT NULL,
  `tracking_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_vendor_splits`
--

INSERT INTO `order_vendor_splits` (`id`, `order_id`, `vendor_id`, `sub_order_no`, `subtotal`, `discount_total`, `vat_total`, `shipping_total`, `grand_total`, `commission_amount`, `vendor_earning`, `status`, `settlement_status`, `settled_at`, `delivered_at`, `cancelled_at`, `release_at`, `vendor_note`, `tracking_note`, `created_at`, `updated_at`) VALUES
(23, 17, NULL, 'SB26112144603-1', 738.00, 0.00, 0.00, 138.68, 876.68, 0.00, 0.00, 'delivered', 'unsettled', NULL, '2026-11-21 09:37:29', NULL, NULL, NULL, 'পিকআপ হয়েছে', '2026-11-21 03:32:48', '2026-11-21 09:37:29');

-- --------------------------------------------------------

--
-- Table structure for table `otp_codes`
--

CREATE TABLE `otp_codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `channel` enum('sms','email') NOT NULL,
  `recipient` varchar(190) NOT NULL,
  `purpose` varchar(30) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(180) NOT NULL,
  `title_en` varchar(200) NOT NULL,
  `title_bn` varchar(200) DEFAULT NULL,
  `content_en` mediumtext DEFAULT NULL,
  `content_bn` mediumtext DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(500) DEFAULT NULL,
  `show_in_footer` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `slug`, `title_en`, `title_bn`, `content_en`, `content_bn`, `meta_title`, `meta_description`, `show_in_footer`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'about-us', 'About Us', 'আমাদের সম্পর্কে', '<p>SasthoBazar is a multi-vendor online pharmacy and healthcare marketplace in Bangladesh.</p>', '<p>স্বাস্থ্যবাজার বাংলাদেশের একটি মাল্টি-ভেন্ডর অনলাইন ফার্মেসি ও হেলথকেয়ার মার্কেটপ্লেস।</p>', NULL, NULL, 1, 1, 1, '2026-11-20 21:35:13', NULL),
(2, 'privacy-policy', 'Privacy Policy', 'গোপনীয়তা নীতি', '<p>Privacy policy content.</p>', '<p>গোপনীয়তা নীতির বিস্তারিত।</p>', NULL, NULL, 1, 2, 1, '2026-11-20 21:35:13', NULL),
(3, 'terms-conditions', 'Terms & Conditions', 'শর্তাবলী', '<p>Terms and conditions content.</p>', '<p>শর্তাবলীর বিস্তারিত।</p>', NULL, NULL, 1, 3, 1, '2026-11-20 21:35:13', NULL),
(4, 'return-refund-policy', 'Return & Refund Policy', 'রিটার্ন ও রিফান্ড নীতি', '<p>Return and refund policy content.</p>', '<p>রিটার্ন ও রিফান্ড নীতির বিস্তারিত।</p>', NULL, NULL, 1, 4, 1, '2026-11-20 21:35:13', NULL),
(5, 'contact-us', 'Contact Us', 'যোগাযোগ', '<p>Hotline: 09600000000</p>', '<p>হটলাইন: ০৯৬০০০০০০০০</p>', NULL, NULL, 1, 5, 1, '2026-11-20 21:35:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `method` enum('cod','bkash','nagad','rocket','sslcommerz','card','bank','wallet') NOT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(150) DEFAULT NULL COMMENT 'gateway trx id',
  `gateway_ref` varchar(150) DEFAULT NULL COMMENT 'our ref / payment id sent to gateway',
  `amount` decimal(12,2) NOT NULL,
  `fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'BDT',
  `status` enum('initiated','pending','success','failed','cancelled','refunded') NOT NULL DEFAULT 'initiated',
  `payer_account` varchar(50) DEFAULT NULL COMMENT 'masked bKash/card no',
  `raw_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_response`)),
  `paid_at` datetime DEFAULT NULL,
  `received_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'staff for COD/manual',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `user_id`, `method`, `gateway`, `transaction_id`, `gateway_ref`, `amount`, `fee`, `currency`, `status`, `payer_account`, `raw_response`, `paid_at`, `received_by`, `created_at`, `updated_at`) VALUES
(16, 17, 33, 'cod', NULL, NULL, NULL, 876.68, 0.00, 'BDT', 'success', NULL, NULL, '2026-11-21 09:37:29', 1, '2026-11-21 03:32:48', '2026-11-21 09:37:29');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `module` varchar(50) NOT NULL COMMENT 'e.g. products',
  `action` varchar(50) NOT NULL COMMENT 'e.g. view, create, edit, delete',
  `key` varchar(100) NOT NULL COMMENT 'module.action',
  `label_en` varchar(150) NOT NULL,
  `label_bn` varchar(150) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `module`, `action`, `key`, `label_en`, `label_bn`, `sort_order`) VALUES
(1, 'dashboard', 'view', 'dashboard.view', 'View Dashboard', 'ড্যাশবোর্ড দেখা', 1),
(2, 'products', 'view', 'products.view', 'View Products', 'পণ্য দেখা', 2),
(3, 'products', 'create', 'products.create', 'Create Products', 'পণ্য তৈরি', 3),
(4, 'products', 'edit', 'products.edit', 'Edit Products', 'পণ্য সম্পাদনা', 4),
(5, 'products', 'delete', 'products.delete', 'Delete Products', 'পণ্য মুছে ফেলা', 5),
(6, 'products', 'approve', 'products.approve', 'Approve Products', 'পণ্য অনুমোদন', 8),
(7, 'products', 'import', 'products.import', 'Import Products', 'পণ্য ইমপোর্ট', 7),
(8, 'categories', 'view', 'categories.view', 'View Categories', 'ক্যাটাগরি দেখা', 9),
(9, 'categories', 'create', 'categories.create', 'Create Categories', 'ক্যাটাগরি তৈরি', 10),
(10, 'categories', 'edit', 'categories.edit', 'Edit Categories', 'ক্যাটাগরি সম্পাদনা', 11),
(11, 'categories', 'delete', 'categories.delete', 'Delete Categories', 'ক্যাটাগরি মুছে ফেলা', 12),
(12, 'brands', 'view', 'brands.view', 'View Brands', 'ব্র্যান্ড দেখা', 14),
(13, 'brands', 'create', 'brands.create', 'Create Brands', 'ব্র্যান্ড তৈরি', 15),
(14, 'brands', 'edit', 'brands.edit', 'Edit Brands', 'ব্র্যান্ড সম্পাদনা', 16),
(15, 'brands', 'delete', 'brands.delete', 'Delete Brands', 'ব্র্যান্ড মুছে ফেলা', 17),
(16, 'inventory', 'view', 'inventory.view', 'View Inventory', 'ইনভেন্টরি দেখা', 19),
(17, 'inventory', 'adjust', 'inventory.adjust', 'Stock in/out & adjust Inventory', 'ইনভেন্টরি স্টক ইন/আউট ও সমন্বয়', 20),
(18, 'inventory', 'batches', 'inventory.batches', 'Batches & expiry Inventory', 'ইনভেন্টরি ব্যাচ ও মেয়াদ', 21),
(19, 'orders', 'view', 'orders.view', 'View Orders', 'অর্ডার দেখা', 36),
(20, 'orders', 'create', 'orders.create', 'Create Orders', 'অর্ডার তৈরি', 37),
(21, 'orders', 'edit', 'orders.edit', 'Edit Orders', 'অর্ডার সম্পাদনা', 38),
(22, 'orders', 'update_status', 'orders.update_status', 'Update status Orders', 'অর্ডার স্ট্যাটাস পরিবর্তন', 39),
(23, 'orders', 'cancel', 'orders.cancel', 'Cancel Orders', 'অর্ডার বাতিল', 40),
(24, 'orders', 'export', 'orders.export', 'Export Orders', 'অর্ডার এক্সপোর্ট', 41),
(25, 'prescriptions', 'view', 'prescriptions.view', 'View Prescriptions', 'প্রেসক্রিপশন দেখা', 42),
(26, 'prescriptions', 'verify', 'prescriptions.verify', 'Verify Prescriptions', 'প্রেসক্রিপশন যাচাই', 43),
(27, 'vendors', 'view', 'vendors.view', 'View Vendors', 'ভেন্ডর দেখা', 53),
(29, 'vendors', 'edit', 'vendors.edit', 'Edit Vendors', 'ভেন্ডর সম্পাদনা', 54),
(30, 'vendors', 'approve', 'vendors.approve', 'Approve Vendors', 'ভেন্ডর অনুমোদন', 55),
(32, 'vendor_payouts', 'view', 'vendor_payouts.view', 'View Vendor Payouts', 'ভেন্ডর পেআউট দেখা', 60),
(33, 'vendor_payouts', 'approve', 'vendor_payouts.approve', 'Approve Vendor Payouts', 'ভেন্ডর পেআউট অনুমোদন', 61),
(34, 'commissions', 'view', 'commissions.view', 'View Commissions', 'কমিশন দেখা', 62),
(35, 'commissions', 'edit', 'commissions.edit', 'Edit Commissions', 'কমিশন সম্পাদনা', 63),
(36, 'customers', 'view', 'customers.view', 'View Customers', 'কাস্টমার দেখা', 45),
(37, 'customers', 'edit', 'customers.edit', 'Edit Customers', 'কাস্টমার সম্পাদনা', 47),
(38, 'customers', 'delete', 'customers.delete', 'Delete Customers', 'কাস্টমার মুছে ফেলা', 48),
(39, 'coupons', 'view', 'coupons.view', 'View Coupons', 'কুপন দেখা', 64),
(40, 'coupons', 'create', 'coupons.create', 'Create Coupons', 'কুপন তৈরি', 65),
(41, 'coupons', 'edit', 'coupons.edit', 'Edit Coupons', 'কুপন সম্পাদনা', 66),
(42, 'coupons', 'delete', 'coupons.delete', 'Delete Coupons', 'কুপন মুছে ফেলা', 67),
(43, 'offers', 'view', 'offers.view', 'View Offers', 'অফার দেখা', 68),
(45, 'flash_sales', 'view', 'flash_sales.view', 'View Flash Sales', 'ফ্ল্যাশ সেল দেখা', 73),
(47, 'shipping', 'view', 'shipping.view', 'View Shipping Zones', 'শিপিং জোন ও রেট দেখা', 79),
(49, 'couriers', 'view', 'couriers.view', 'View Couriers', 'কুরিয়ার দেখা', 82),
(51, 'shipments', 'view', 'shipments.view', 'View Shipments', 'শিপমেন্ট দেখা', 84),
(53, 'payments', 'view', 'payments.view', 'View Payments', 'পেমেন্ট দেখা', 92),
(55, 'refunds', 'view', 'refunds.view', 'View Refunds', 'রিফান্ড দেখা', 95),
(56, 'refunds', 'approve', 'refunds.approve', 'Approve Refunds', 'রিফান্ড অনুমোদন', 96),
(57, 'reviews', 'view', 'reviews.view', 'View Reviews', 'রিভিউ দেখা', 50),
(58, 'reviews', 'moderate', 'reviews.moderate', 'Moderate Reviews', 'রিভিউ মডারেশন', 51),
(61, 'notifications', 'send', 'notifications.send', 'Send SMS & Email log', 'SMS ও ইমেইল লগ পাঠানো', 78),
(62, 'support', 'view', 'support.view', 'View Support Tickets', 'সাপোর্ট টিকেট দেখা', 113),
(63, 'support', 'reply', 'support.reply', 'Reply Support Tickets', 'সাপোর্ট টিকেট উত্তর', 114),
(64, 'support', 'assign', 'support.assign', 'Assign Support Tickets', 'সাপোর্ট টিকেট অ্যাসাইন', 115),
(65, 'reports', 'view', 'reports.view', 'View Reports', 'রিপোর্ট দেখা', 97),
(66, 'reports', 'export', 'reports.export', 'Export Reports', 'রিপোর্ট এক্সপোর্ট', 98),
(67, 'employees', 'view', 'employees.view', 'View Employees', 'কর্মী দেখা', 117),
(68, 'employees', 'create', 'employees.create', 'Create Employees', 'কর্মী তৈরি', 118),
(69, 'employees', 'edit', 'employees.edit', 'Edit Employees', 'কর্মী সম্পাদনা', 119),
(71, 'roles', 'view', 'roles.view', 'View Roles & Permissions', 'রোল ও পারমিশন দেখা', 121),
(74, 'settings', 'view', 'settings.view', 'View Settings', 'সেটিংস দেখা', 127),
(75, 'settings', 'edit', 'settings.edit', 'Edit Settings', 'সেটিংস সম্পাদনা', 128),
(76, 'audit_logs', 'view', 'audit_logs.view', 'View Audit Logs', 'অডিট লগ দেখা', 125),
(82, 'products', 'export', 'products.export', 'Export Products', 'পণ্য এক্সপোর্ট', 6),
(89, 'categories', 'export', 'categories.export', 'Export Categories', 'ক্যাটাগরি এক্সপোর্ট', 13),
(94, 'brands', 'export', 'brands.export', 'Export Brands', 'ব্র্যান্ড এক্সপোর্ট', 18),
(98, 'inventory', 'export', 'inventory.export', 'Export Inventory', 'ইনভেন্টরি এক্সপোর্ট', 22),
(109, 'customers', 'create', 'customers.create', 'Create Customers', 'কাস্টমার তৈরি', 46),
(112, 'customers', 'export', 'customers.export', 'Export Customers', 'কাস্টমার এক্সপোর্ট', 49),
(115, 'reviews', 'delete', 'reviews.delete', 'Delete Reviews', 'রিভিউ মুছে ফেলা', 52),
(134, 'offers', 'create', 'offers.create', 'Create Offers', 'অফার তৈরি', 69),
(135, 'offers', 'edit', 'offers.edit', 'Edit Offers', 'অফার সম্পাদনা', 70),
(136, 'offers', 'delete', 'offers.delete', 'Delete Offers', 'অফার মুছে ফেলা', 71),
(138, 'flash_sales', 'create', 'flash_sales.create', 'Create Flash Sales', 'ফ্ল্যাশ সেল তৈরি', 74),
(139, 'flash_sales', 'edit', 'flash_sales.edit', 'Edit Flash Sales', 'ফ্ল্যাশ সেল সম্পাদনা', 75),
(140, 'flash_sales', 'delete', 'flash_sales.delete', 'Delete Flash Sales', 'ফ্ল্যাশ সেল মুছে ফেলা', 76),
(141, 'notifications', 'view', 'notifications.view', 'View SMS & Email log', 'SMS ও ইমেইল লগ দেখা', 77),
(145, 'shipping', 'edit', 'shipping.edit', 'Edit Shipping Zones', 'শিপিং জোন ও রেট সম্পাদনা', 80),
(146, 'shipping', 'delete', 'shipping.delete', 'Delete Shipping Zones', 'শিপিং জোন ও রেট মুছে ফেলা', 81),
(149, 'couriers', 'edit', 'couriers.edit', 'Edit Couriers', 'কুরিয়ার সম্পাদনা', 83),
(152, 'shipments', 'create', 'shipments.create', 'Create Shipments', 'শিপমেন্ট তৈরি', 85),
(153, 'shipments', 'edit', 'shipments.edit', 'Edit Shipments', 'শিপমেন্ট সম্পাদনা', 86),
(154, 'shipments', 'export', 'shipments.export', 'Export Shipments', 'শিপমেন্ট এক্সপোর্ট', 87),
(155, 'locations', 'view', 'locations.view', 'View Locations', 'লোকেশন দেখা', 88),
(156, 'locations', 'create', 'locations.create', 'Create Locations', 'লোকেশন তৈরি', 89),
(157, 'locations', 'edit', 'locations.edit', 'Edit Locations', 'লোকেশন সম্পাদনা', 90),
(158, 'locations', 'delete', 'locations.delete', 'Delete Locations', 'লোকেশন মুছে ফেলা', 91),
(160, 'payments', 'edit', 'payments.edit', 'Edit Payments', 'পেমেন্ট সম্পাদনা', 93),
(161, 'payments', 'export', 'payments.export', 'Export Payments', 'পেমেন্ট এক্সপোর্ট', 94),
(168, 'banners', 'view', 'banners.view', 'View Banners', 'ব্যানার দেখা', 105),
(169, 'banners', 'create', 'banners.create', 'Create Banners', 'ব্যানার তৈরি', 106),
(170, 'banners', 'edit', 'banners.edit', 'Edit Banners', 'ব্যানার সম্পাদনা', 107),
(171, 'banners', 'delete', 'banners.delete', 'Delete Banners', 'ব্যানার মুছে ফেলা', 108),
(172, 'pages', 'view', 'pages.view', 'View Pages', 'পেজ দেখা', 109),
(173, 'pages', 'create', 'pages.create', 'Create Pages', 'পেজ তৈরি', 110),
(174, 'pages', 'edit', 'pages.edit', 'Edit Pages', 'পেজ সম্পাদনা', 111),
(175, 'pages', 'delete', 'pages.delete', 'Delete Pages', 'পেজ মুছে ফেলা', 112),
(179, 'support', 'export', 'support.export', 'Export Support Tickets', 'সাপোর্ট টিকেট এক্সপোর্ট', 116),
(184, 'employees', 'export', 'employees.export', 'Export Employees', 'কর্মী এক্সপোর্ট', 120),
(186, 'roles', 'create', 'roles.create', 'Create Roles & Permissions', 'রোল ও পারমিশন তৈরি', 122),
(187, 'roles', 'edit', 'roles.edit', 'Edit Roles & Permissions', 'রোল ও পারমিশন সম্পাদনা', 123),
(188, 'roles', 'delete', 'roles.delete', 'Delete Roles & Permissions', 'রোল ও পারমিশন মুছে ফেলা', 124),
(190, 'audit_logs', 'export', 'audit_logs.export', 'Export Audit Logs', 'অডিট লগ এক্সপোর্ট', 126),
(215, 'inventory', 'configure', 'inventory.configure', 'Configure Inventory', 'ইনভেন্টরি সেটিংস', 23),
(216, 'suppliers', 'view', 'suppliers.view', 'View Suppliers', 'সরবরাহকারী দেখা', 24),
(217, 'suppliers', 'create', 'suppliers.create', 'Create Suppliers', 'সরবরাহকারী তৈরি', 25),
(218, 'suppliers', 'edit', 'suppliers.edit', 'Edit Suppliers', 'সরবরাহকারী সম্পাদনা', 26),
(219, 'suppliers', 'delete', 'suppliers.delete', 'Delete Suppliers', 'সরবরাহকারী মুছে ফেলা', 27),
(220, 'suppliers', 'export', 'suppliers.export', 'Export Suppliers', 'সরবরাহকারী এক্সপোর্ট', 28),
(221, 'purchase_orders', 'view', 'purchase_orders.view', 'View Purchase Orders', 'ক্রয় আদেশ ও GRN দেখা', 29),
(222, 'purchase_orders', 'create', 'purchase_orders.create', 'Create Purchase Orders', 'ক্রয় আদেশ ও GRN তৈরি', 30),
(223, 'purchase_orders', 'edit', 'purchase_orders.edit', 'Edit Purchase Orders', 'ক্রয় আদেশ ও GRN সম্পাদনা', 31),
(224, 'purchase_orders', 'delete', 'purchase_orders.delete', 'Delete Purchase Orders', 'ক্রয় আদেশ ও GRN মুছে ফেলা', 32),
(225, 'purchase_orders', 'receive', 'purchase_orders.receive', 'Receive (GRN) Purchase Orders', 'ক্রয় আদেশ ও GRN মাল গ্রহণ (GRN)', 33),
(226, 'purchase_orders', 'cancel', 'purchase_orders.cancel', 'Cancel Purchase Orders', 'ক্রয় আদেশ ও GRN বাতিল', 34),
(227, 'purchase_orders', 'export', 'purchase_orders.export', 'Export Purchase Orders', 'ক্রয় আদেশ ও GRN এক্সপোর্ট', 35),
(782, 'offers', 'approve', 'offers.approve', 'Approve Offers', 'অফার অনুমোদন', 72),
(944, 'accounting', 'view', 'accounting.view', 'View Accounting', 'হিসাবরক্ষণ দেখা', 99),
(945, 'accounting', 'create', 'accounting.create', 'Create Accounting', 'হিসাবরক্ষণ তৈরি', 100),
(946, 'accounting', 'edit', 'accounting.edit', 'Edit Accounting', 'হিসাবরক্ষণ সম্পাদনা', 101),
(947, 'accounting', 'delete', 'accounting.delete', 'Delete Accounting', 'হিসাবরক্ষণ মুছে ফেলা', 102),
(948, 'accounting', 'export', 'accounting.export', 'Export Accounting', 'হিসাবরক্ষণ এক্সপোর্ট', 103),
(949, 'accounting', 'configure', 'accounting.configure', 'Configure Accounting', 'হিসাবরক্ষণ সেটিংস', 104),
(1111, 'settings', 'backup', 'settings.backup', 'Backup & restore Settings', 'সেটিংস ব্যাকআপ ও রিস্টোর', 129),
(1155, 'prescriptions', 'update_status', 'prescriptions.update_status', 'Update status Prescriptions', 'প্রেসক্রিপশন স্ট্যাটাস পরিবর্তন', 44),
(1167, 'vendor_types', 'view', 'vendor_types.view', 'View Vendor Types', 'ভেন্ডরের ধরন দেখা', 57),
(1168, 'vendor_types', 'create', 'vendor_types.create', 'Create Vendor Types', 'ভেন্ডরের ধরন তৈরি', 58),
(1169, 'vendor_types', 'edit', 'vendor_types.edit', 'Edit Vendor Types', 'ভেন্ডরের ধরন সম্পাদনা', 59),
(1423, 'vendors', 'delete', 'vendors.delete', 'Delete Vendors', 'ভেন্ডর মুছে ফেলা', 56);

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `guest_name` varchar(150) DEFAULT NULL,
  `guest_phone` varchar(20) DEFAULT NULL,
  `request_type` enum('checkout','order_request') NOT NULL DEFAULT 'checkout',
  `group_code` varchar(20) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(10) UNSIGNED DEFAULT NULL,
  `patient_name` varchar(150) DEFAULT NULL,
  `patient_age` tinyint(3) UNSIGNED DEFAULT NULL,
  `doctor_name` varchar(150) DEFAULT NULL,
  `doctor_bmdc` varchar(50) DEFAULT NULL COMMENT 'BMDC registration no.',
  `issued_date` date DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `district_id` int(10) UNSIGNED DEFAULT NULL,
  `upazila_id` int(10) UNSIGNED DEFAULT NULL,
  `request_status` enum('new','contacted','ordered','cancelled') DEFAULT NULL,
  `order_no` varchar(30) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'pharmacist',
  `reviewed_at` datetime DEFAULT NULL,
  `review_note` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'NULL = sold by SasthoBazar (in-house)',
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `brand_id` int(10) UNSIGNED DEFAULT NULL,
  `product_type` enum('medicine','otc','device','general') NOT NULL DEFAULT 'general',
  `name_en` varchar(255) NOT NULL,
  `name_bn` varchar(255) DEFAULT NULL,
  `slug` varchar(280) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `generic_name` varchar(255) DEFAULT NULL COMMENT 'medicine: e.g. Paracetamol',
  `strength` varchar(100) DEFAULT NULL COMMENT 'e.g. 500 mg',
  `dosage_form` varchar(100) DEFAULT NULL COMMENT 'Tablet, Syrup, Capsule',
  `pack_size` varchar(100) DEFAULT NULL COMMENT 'e.g. 10x10',
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `short_description` varchar(500) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `indications` text DEFAULT NULL,
  `usage_instructions` text DEFAULT NULL COMMENT 'dosage & administration',
  `side_effects` text DEFAULT NULL,
  `mrp` decimal(12,2) NOT NULL DEFAULT 0.00,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'selling price',
  `cost_price` decimal(12,2) DEFAULT NULL,
  `discount_type` enum('none','percent','fixed') NOT NULL DEFAULT 'none',
  `discount_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vat_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `commission_rate` decimal(5,2) DEFAULT NULL COMMENT 'NULL = vendor/category/global rate',
  `requires_prescription` tinyint(1) NOT NULL DEFAULT 0,
  `has_variants` tinyint(1) NOT NULL DEFAULT 0,
  `track_stock` tinyint(1) NOT NULL DEFAULT 1,
  `stock_qty` int(11) NOT NULL DEFAULT 0 COMMENT 'cached sum of available batches',
  `low_stock_threshold` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `min_order_qty` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `max_order_qty` int(10) UNSIGNED DEFAULT NULL,
  `weight_grams` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('draft','pending','active','inactive','rejected') NOT NULL DEFAULT 'draft',
  `rejection_reason` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `views` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sold_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_avg` decimal(3,2) NOT NULL DEFAULT 0.00,
  `rating_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(500) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `vendor_id`, `category_id`, `brand_id`, `product_type`, `name_en`, `name_bn`, `slug`, `sku`, `barcode`, `generic_name`, `strength`, `dosage_form`, `pack_size`, `unit`, `short_description`, `description`, `indications`, `usage_instructions`, `side_effects`, `mrp`, `price`, `cost_price`, `discount_type`, `discount_value`, `vat_percent`, `commission_rate`, `requires_prescription`, `has_variants`, `track_stock`, `stock_qty`, `low_stock_threshold`, `min_order_qty`, `max_order_qty`, `weight_grams`, `status`, `rejection_reason`, `is_featured`, `views`, `sold_count`, `rating_avg`, `rating_count`, `meta_title`, `meta_description`, `meta_keywords`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(38, NULL, 3, 11, 'otc', 'Napa 500', 'নাপা ৫০০', 'napa-500-500-mg', 'DEMO-NAPA500', NULL, 'Paracetamol', '500 mg', 'Tablet', '10 tablets', 'strip', 'Pain and fever relief.', 'Pain and fever relief.\n\nDemo product: details are for illustration only.', 'Fever, headache, toothache, period pain, muscle and joint pain.', 'Adults: 1-2 tablets every 4-6 hours. Do not take more than 8 tablets in 24 hours.', 'Rarely skin rash. Overdose can damage the liver.', 12.00, 12.00, 9.84, 'none', 0.00, 0.00, NULL, 0, 1, 1, 325, 10, 1, NULL, 20, 'active', NULL, 1, 738, 39, 5.00, 1, 'Napa 500 500 mg — buy online', 'Order Napa 500 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:01', NULL),
(39, NULL, 3, 11, 'otc', 'Napa Extra', 'নাপা এক্সট্রা', 'napa-extra-500-mg-65-mg', 'DEMO-NAPAEXTRA', NULL, 'Paracetamol + Caffeine', '500 mg + 65 mg', 'Tablet', '10 tablets', 'strip', 'Pain and fever relief.', 'Pain and fever relief.\n\nDemo product: details are for illustration only.', 'Fever, headache, toothache, period pain, muscle and joint pain.', 'Adults: 1-2 tablets every 4-6 hours. Do not take more than 8 tablets in 24 hours.', 'Rarely skin rash. Overdose can damage the liver.', 25.00, 25.00, 20.50, 'none', 0.00, 0.00, NULL, 0, 0, 1, 300, 10, 1, NULL, 20, 'active', NULL, 1, 274, 107, 0.00, 0, 'Napa Extra 500 mg + 65 mg — buy online', 'Order Napa Extra online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 14:28:58', NULL),
(40, 10, 3, 12, 'otc', 'Ace 500', 'এইস ৫০০', 'ace-500-500-mg', 'DEMO-ACE500', NULL, 'Paracetamol', '500 mg', 'Tablet', '10 tablets', 'strip', 'Pain and fever relief.', 'Pain and fever relief.\n\nDemo product: details are for illustration only.', 'Fever, headache, toothache, period pain, muscle and joint pain.', 'Adults: 1-2 tablets every 4-6 hours. Do not take more than 8 tablets in 24 hours.', 'Rarely skin rash. Overdose can damage the liver.', 12.00, 11.00, 9.02, 'none', 0.00, 0.00, NULL, 0, 0, 1, 250, 10, 1, NULL, 20, 'active', NULL, 0, 438, 8, 0.00, 0, 'Ace 500 500 mg — buy online', 'Order Ace 500 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:01', NULL),
(41, 10, 3, 12, 'otc', 'Ace Plus', 'এইস প্লাস', 'ace-plus-500-mg-65-mg', 'DEMO-ACE-PLUS', NULL, 'Paracetamol + Caffeine', '500 mg + 65 mg', 'Tablet', '10 tablets', 'strip', 'Pain and fever relief.', 'Pain and fever relief.\n\nDemo product: details are for illustration only.', 'Fever, headache, toothache, period pain, muscle and joint pain.', 'Adults: 1-2 tablets every 4-6 hours. Do not take more than 8 tablets in 24 hours.', 'Rarely skin rash. Overdose can damage the liver.', 25.00, 23.00, 18.86, 'none', 0.00, 0.00, NULL, 0, 0, 1, 180, 10, 1, NULL, 20, 'active', NULL, 0, 272, 108, 0.00, 0, 'Ace Plus 500 mg + 65 mg — buy online', 'Order Ace Plus online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:01', NULL),
(42, NULL, 3, 12, 'otc', 'Fexo 120', 'ফেক্সো ১২০', 'fexo-120-120-mg', 'DEMO-FEXO120', NULL, 'Fexofenadine Hydrochloride', '120 mg', 'Tablet', '10 tablets', 'strip', 'Fexo 120 — genuine product from Square Pharmaceuticals.', 'Genuine Fexo 120 from Square Pharmaceuticals.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 90.00, 85.00, 69.70, 'none', 0.00, 0.00, NULL, 0, 0, 1, 150, 10, 1, NULL, 20, 'active', NULL, 1, 725, 88, 0.00, 0, 'Fexo 120 120 mg — buy online', 'Order Fexo 120 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:01', NULL),
(43, NULL, 3, 12, 'otc', 'Alatrol', 'এলাট্রল', 'alatrol-10-mg', 'DEMO-ALATROL', NULL, 'Cetirizine Hydrochloride', '10 mg', 'Tablet', '10 tablets', 'strip', 'Alatrol — genuine product from Square Pharmaceuticals.', 'Genuine Alatrol from Square Pharmaceuticals.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 30.00, 30.00, 24.60, 'none', 0.00, 0.00, NULL, 0, 0, 1, 8, 10, 1, NULL, 20, 'active', NULL, 0, 643, 167, 0.00, 0, 'Alatrol 10 mg — buy online', 'Order Alatrol online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:01', NULL),
(44, NULL, 2, 12, 'medicine', 'Seclo 20', 'সেকলো ২০', 'seclo-20-20-mg', 'DEMO-SECLO20', NULL, 'Omeprazole', '20 mg', 'Capsule', '14 capsules', 'strip', 'Reduces stomach acid.', 'Reduces stomach acid.\n\nDemo product: details are for illustration only.', 'Gastric and duodenal ulcer, acid reflux (GERD), heartburn.', '1 capsule daily before breakfast, or as advised by your doctor.', 'Headache, diarrhoea, stomach pain (usually mild).', 70.00, 63.00, 51.66, 'none', 0.00, 0.00, NULL, 1, 0, 1, 200, 10, 1, NULL, 20, 'active', NULL, 1, 814, 76, 5.00, 1, 'Seclo 20 20 mg — buy online', 'Order Seclo 20 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:01', NULL),
(45, NULL, 2, 14, 'medicine', 'Sergel 20', 'সার্জেল ২০', 'sergel-20-20-mg', 'DEMO-SERGEL20', NULL, 'Esomeprazole', '20 mg', 'Capsule', '10 capsules', 'strip', 'Proton pump inhibitor for acidity.', 'Proton pump inhibitor for acidity.\n\nDemo product: details are for illustration only.', 'Acid reflux, ulcers, H. pylori treatment (with antibiotics).', '1 dose daily before a meal, as prescribed.', 'Headache, nausea, wind.', 70.00, 67.00, 54.94, 'none', 0.00, 0.00, NULL, 1, 0, 1, 160, 10, 1, NULL, 20, 'active', NULL, 0, 454, 11, 0.00, 0, 'Sergel 20 20 mg — buy online', 'Order Sergel 20 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:01', NULL),
(46, 11, 2, 16, 'medicine', 'Maxpro 20', 'ম্যাক্সপ্রো ২০', 'maxpro-20-20-mg', 'DEMO-MAXPRO20', NULL, 'Esomeprazole', '20 mg', 'Tablet', '10 tablets', 'strip', 'Proton pump inhibitor for acidity.', 'Proton pump inhibitor for acidity.\n\nDemo product: details are for illustration only.', 'Acid reflux, ulcers, H. pylori treatment (with antibiotics).', '1 dose daily before a meal, as prescribed.', 'Headache, nausea, wind.', 70.00, 70.00, 57.40, 'none', 0.00, 0.00, NULL, 1, 0, 1, 120, 10, 1, NULL, 20, 'active', NULL, 0, 146, 142, 0.00, 0, 'Maxpro 20 20 mg — buy online', 'Order Maxpro 20 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:01', NULL),
(47, 11, 2, 17, 'medicine', 'Losectil 20', 'লোসেকটিল ২০', 'losectil-20-20-mg', 'DEMO-LOSECTIL20', NULL, 'Omeprazole', '20 mg', 'Capsule', '10 capsules', 'strip', 'Reduces stomach acid.', 'Reduces stomach acid.\n\nDemo product: details are for illustration only.', 'Gastric and duodenal ulcer, acid reflux (GERD), heartburn.', '1 capsule daily before breakfast, or as advised by your doctor.', 'Headache, diarrhoea, stomach pain (usually mild).', 50.00, 47.00, 38.54, 'none', 0.00, 0.00, NULL, 1, 0, 1, 90, 10, 1, NULL, 20, 'active', NULL, 0, 575, 10, 0.00, 0, 'Losectil 20 20 mg — buy online', 'Order Losectil 20 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:01', NULL),
(48, NULL, 2, 15, 'medicine', 'Monas 10', 'মোনাস ১০', 'monas-10-10-mg', 'DEMO-MONAS10', NULL, 'Montelukast', '10 mg', 'Tablet', '10 tablets', 'strip', 'Monas 10 — genuine product from ACME Laboratories.', 'Genuine Monas 10 from ACME Laboratories.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 160.00, 152.00, 124.64, 'none', 0.00, 0.00, NULL, 1, 0, 1, 100, 10, 1, NULL, 20, 'active', NULL, 0, 170, 116, 0.00, 0, 'Monas 10 10 mg — buy online', 'Order Monas 10 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:01', NULL),
(49, NULL, 2, 11, 'medicine', 'Azithrocin 500', 'এজিথ্রোসিন ৫০০', 'azithrocin-500-500-mg', 'DEMO-AZITHROCIN', NULL, 'Azithromycin', '500 mg', 'Tablet', '3 tablets', 'strip', 'Azithrocin 500 — genuine product from Beximco Pharma.', 'Genuine Azithrocin 500 from Beximco Pharma.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 105.00, 105.00, 86.10, 'none', 0.00, 0.00, NULL, 1, 0, 1, 92, 10, 1, NULL, 20, 'active', NULL, 0, 320, 127, 0.00, 0, 'Azithrocin 500 500 mg — buy online', 'Order Azithrocin 500 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:01', NULL),
(50, NULL, 2, 13, 'medicine', 'Bislol 2.5', 'বিসলল ২.৫', 'bislol-2-5-2-5-mg', 'DEMO-BIZORAN', NULL, 'Bisoprolol Fumarate', '2.5 mg', 'Tablet', '10 tablets', 'strip', 'Bislol 2.5 — genuine product from Incepta Pharmaceuticals.', 'Genuine Bislol 2.5 from Incepta Pharmaceuticals.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 60.00, 57.00, 46.74, 'none', 0.00, 0.00, NULL, 1, 0, 1, 110, 10, 1, NULL, 20, 'active', NULL, 0, 186, 29, 0.00, 0, 'Bislol 2.5 2.5 mg — buy online', 'Order Bislol 2.5 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:01', NULL),
(51, NULL, 9, 12, 'medicine', 'Comet 500', 'কমেট ৫০০', 'comet-500-500-mg', 'DEMO-COMET500', NULL, 'Metformin Hydrochloride', '500 mg', 'Tablet', '10 tablets', 'strip', 'Comet 500 — genuine product from Square Pharmaceuticals.', 'Genuine Comet 500 from Square Pharmaceuticals.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 40.00, 38.00, 31.16, 'none', 0.00, 0.00, NULL, 1, 0, 1, 220, 10, 1, NULL, 20, 'active', NULL, 0, 145, 104, 0.00, 0, 'Comet 500 500 mg — buy online', 'Order Comet 500 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(52, NULL, 3, 19, 'otc', 'Orsaline-N', 'ওরস্যালাইন-এন', 'orsaline-n-25-g', 'DEMO-ORSALINE', NULL, 'Oral Rehydration Salts', '25 g', 'Powder', '1 sachet', 'sachet', 'Orsaline-N — genuine product from SMC Enterprise.', 'Genuine Orsaline-N from SMC Enterprise.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 6.00, 6.00, 4.92, 'none', 0.00, 0.00, NULL, 0, 1, 1, 840, 10, 1, NULL, 150, 'active', NULL, 1, 333, 180, 0.00, 0, 'Orsaline-N 25 g — buy online', 'Order Orsaline-N online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(53, NULL, 8, 18, 'otc', 'Savlon Antiseptic Liquid', 'স্যাভলন অ্যান্টিসেপটিক লিকুইড', 'savlon-antiseptic-liquid', 'DEMO-SAVLON', NULL, 'Chlorhexidine + Cetrimide', NULL, 'Solution', NULL, 'bottle', 'Savlon Antiseptic Liquid — genuine product from ACI Limited.', 'Genuine Savlon Antiseptic Liquid from ACI Limited.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 120.00, 115.00, 94.30, 'none', 0.00, 0.00, NULL, 0, 1, 1, 110, 10, 1, NULL, 150, 'active', NULL, 0, 710, 104, 0.00, 0, 'Savlon Antiseptic Liquid — buy online', 'Order Savlon Antiseptic Liquid online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(54, 10, 8, 18, 'general', 'Elastic Crepe Bandage 4 inch', 'ইলাস্টিক ক্রেপ ব্যান্ডেজ ৪ ইঞ্চি', 'elastic-crepe-bandage-4-inch', 'DEMO-BANDAGE', NULL, NULL, NULL, NULL, '1 roll', 'pcs', 'Elastic Crepe Bandage 4 inch — genuine product from ACI Limited.', 'Genuine Elastic Crepe Bandage 4 inch from ACI Limited.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 95.00, 85.00, 69.70, 'none', 0.00, 0.00, NULL, 0, 0, 1, 70, 10, 1, NULL, 150, 'active', NULL, 0, 719, 57, 0.00, 0, 'Elastic Crepe Bandage 4 inch — buy online', 'Order Elastic Crepe Bandage 4 inch online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(55, NULL, 7, 20, 'device', 'Accu-Chek Active Glucometer', 'একু-চেক অ্যাক্টিভ গ্লুকোমিটার', 'accu-chek-active-glucometer', 'DEMO-ACCUCHEK', NULL, NULL, NULL, NULL, 'Meter + 10 strips', 'box', 'Accu-Chek Active Glucometer — genuine product from Roche (Accu-Chek).', 'Genuine Accu-Chek Active Glucometer from Roche (Accu-Chek).\n\nDemo product: details are for illustration only.', NULL, 'Read the instruction leaflet before first use.', NULL, 1650.00, 1450.00, 1189.00, 'none', 0.00, 0.00, NULL, 0, 0, 1, 25, 10, 1, NULL, 350, 'active', NULL, 1, 626, 31, 5.00, 1, 'Accu-Chek Active Glucometer — buy online', 'Order Accu-Chek Active Glucometer online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(56, NULL, 9, 20, 'device', 'Accu-Chek Active Test Strips', 'একু-চেক অ্যাক্টিভ টেস্ট স্ট্রিপ', 'accu-chek-active-test-strips', 'DEMO-ACCUSTRIPS', NULL, NULL, NULL, NULL, '50 strips', 'box', 'Accu-Chek Active Test Strips — genuine product from Roche (Accu-Chek).', 'Genuine Accu-Chek Active Test Strips from Roche (Accu-Chek).\n\nDemo product: details are for illustration only.', NULL, 'Read the instruction leaflet before first use.', NULL, 1450.00, 1390.00, 1139.80, 'none', 0.00, 0.00, NULL, 0, 0, 1, 40, 10, 1, NULL, 150, 'active', NULL, 0, 514, 131, 0.00, 0, 'Accu-Chek Active Test Strips — buy online', 'Order Accu-Chek Active Test Strips online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(57, 10, 7, 21, 'device', 'Omron HEM-7120 BP Monitor', 'ওমরন HEM-7120 প্রেশার মাপার যন্ত্র', 'omron-hem-7120-bp-monitor', 'DEMO-BPMONITOR', NULL, NULL, NULL, NULL, '1 unit', 'pcs', 'Omron HEM-7120 BP Monitor — genuine product from Omron.', 'Genuine Omron HEM-7120 BP Monitor from Omron.\n\nDemo product: details are for illustration only.', NULL, 'Read the instruction leaflet before first use.', NULL, 4500.00, 3950.00, 3239.00, 'none', 0.00, 0.00, NULL, 0, 0, 1, 15, 10, 1, NULL, 600, 'active', NULL, 1, 403, 8, 0.00, 0, 'Omron HEM-7120 BP Monitor — buy online', 'Order Omron HEM-7120 BP Monitor online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(58, NULL, 7, 21, 'device', 'Omron Digital Thermometer MC-246', 'ওমরন ডিজিটাল থার্মোমিটার', 'omron-digital-thermometer-mc-246', 'DEMO-THERMO', NULL, NULL, NULL, NULL, '1 unit', 'pcs', 'Omron Digital Thermometer MC-246 — genuine product from Omron.', 'Genuine Omron Digital Thermometer MC-246 from Omron.\n\nDemo product: details are for illustration only.', NULL, 'Read the instruction leaflet before first use.', NULL, 450.00, 399.00, 327.18, 'none', 0.00, 0.00, NULL, 0, 0, 1, 60, 10, 1, NULL, 150, 'active', NULL, 0, 63, 93, 0.00, 0, 'Omron Digital Thermometer MC-246 — buy online', 'Order Omron Digital Thermometer MC-246 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(59, 11, 6, 18, 'general', 'Surgical Face Mask (3 ply)', 'সার্জিক্যাল ফেস মাস্ক (৩ স্তর)', 'surgical-face-mask-3-ply', 'DEMO-MASK', NULL, NULL, NULL, NULL, '50 pcs', 'box', 'Surgical Face Mask (3 ply) — genuine product from ACI Limited.', 'Genuine Surgical Face Mask (3 ply) from ACI Limited.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 250.00, 199.00, 163.18, 'none', 0.00, 0.00, NULL, 0, 0, 1, 100, 10, 1, NULL, 150, 'active', NULL, 0, 853, 161, 0.00, 0, 'Surgical Face Mask (3 ply) — buy online', 'Order Surgical Face Mask (3 ply) online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(60, NULL, 10, 22, 'general', 'Nestlé Cerelac Wheat & Milk', 'নেসলে সেরেল্যাক গম ও দুধ', 'nestlé-cerelac-wheat-milk', 'DEMO-CERELAC', NULL, NULL, NULL, NULL, '350 g', 'pack', 'Nestlé Cerelac Wheat & Milk — genuine product from Nestlé.', 'Genuine Nestlé Cerelac Wheat & Milk from Nestlé.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 620.00, 595.00, 487.90, 'none', 0.00, 0.00, NULL, 0, 0, 1, 60, 10, 1, NULL, 400, 'active', NULL, 1, 591, 75, 5.00, 1, 'Nestlé Cerelac Wheat & Milk — buy online', 'Order Nestlé Cerelac Wheat & Milk online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(61, NULL, 10, 23, 'general', 'Johnson\'s Baby Lotion', 'জনসন্স বেবি লোশন', 'johnson-s-baby-lotion', 'DEMO-JBABY-LOTION', NULL, NULL, NULL, NULL, NULL, 'bottle', 'Johnson\'s Baby Lotion — genuine product from Johnson\'s Baby.', 'Genuine Johnson\'s Baby Lotion from Johnson\'s Baby.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 380.00, 380.00, 311.60, 'none', 0.00, 0.00, NULL, 0, 1, 1, 65, 10, 1, NULL, 150, 'active', NULL, 0, 204, 48, 0.00, 0, 'Johnson\'s Baby Lotion — buy online', 'Order Johnson\'s Baby Lotion online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(62, 10, 10, 23, 'general', 'Johnson\'s Baby Shampoo', 'জনসন্স বেবি শ্যাম্পু', 'johnson-s-baby-shampoo', 'DEMO-JBABY-SHAMPOO', NULL, NULL, NULL, NULL, '200 ml', 'bottle', 'Johnson\'s Baby Shampoo — genuine product from Johnson\'s Baby.', 'Genuine Johnson\'s Baby Shampoo from Johnson\'s Baby.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 420.00, 399.00, 327.18, 'none', 0.00, 0.00, NULL, 0, 0, 1, 45, 10, 1, NULL, 150, 'active', NULL, 0, 418, 113, 0.00, 0, 'Johnson\'s Baby Shampoo — buy online', 'Order Johnson\'s Baby Shampoo online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(63, NULL, 10, 27, 'general', 'Baby Diaper Pant (Medium)', 'বেবি ডায়াপার প্যান্ট (মিডিয়াম)', 'baby-diaper-pant-medium', 'DEMO-DIAPER', NULL, NULL, NULL, NULL, '40 pcs', 'pack', 'Baby Diaper Pant (Medium) — genuine product from P&G.', 'Genuine Baby Diaper Pant (Medium) from P&G.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 1350.00, 1199.00, 983.18, 'none', 0.00, 0.00, NULL, 0, 0, 1, 30, 10, 1, NULL, 1300, 'active', NULL, 0, 199, 26, 0.00, 0, 'Baby Diaper Pant (Medium) — buy online', 'Order Baby Diaper Pant (Medium) online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(64, NULL, 16, 12, 'otc', 'Filwel Gold', 'ফিলওয়েল গোল্ড', 'filwel-gold', 'DEMO-FILWEL', NULL, 'Multivitamin & Multimineral', NULL, 'Tablet', '30 tablets', 'box', 'Filwel Gold — genuine product from Square Pharmaceuticals.', 'Genuine Filwel Gold from Square Pharmaceuticals.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 450.00, 420.00, 344.40, 'none', 0.00, 0.00, NULL, 0, 0, 1, 68, 10, 1, NULL, 150, 'active', NULL, 1, 893, 171, 4.00, 1, 'Filwel Gold — buy online', 'Order Filwel Gold online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:32:48', NULL),
(65, 11, 16, 12, 'otc', 'Ceevit 250', 'সিভিট ২৫০', 'ceevit-250-250-mg', 'DEMO-CEEVIT', NULL, 'Vitamin C (Ascorbic Acid)', '250 mg', 'Chewable Tablet', '10 tablets', 'strip', 'Ceevit 250 — genuine product from Square Pharmaceuticals.', 'Genuine Ceevit 250 from Square Pharmaceuticals.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 15.00, 15.00, 12.30, 'none', 0.00, 0.00, NULL, 0, 0, 1, 300, 10, 1, NULL, 20, 'active', NULL, 0, 243, 49, 0.00, 0, 'Ceevit 250 250 mg — buy online', 'Order Ceevit 250 online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(66, NULL, 16, 12, 'otc', 'Calbo-D', 'ক্যালবো-ডি', 'calbo-d-500-mg-200-iu', 'DEMO-CALBO-D', NULL, 'Calcium Carbonate + Vitamin D3', '500 mg + 200 IU', 'Tablet', '15 tablets', 'strip', 'Calbo-D — genuine product from Square Pharmaceuticals.', 'Genuine Calbo-D from Square Pharmaceuticals.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 165.00, 158.00, 129.56, 'none', 0.00, 0.00, NULL, 0, 0, 1, 130, 10, 1, NULL, 20, 'active', NULL, 0, 538, 161, 0.00, 0, 'Calbo-D 500 mg + 200 IU — buy online', 'Order Calbo-D online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(67, NULL, 16, 24, 'general', 'Horlicks Health Drink', 'হরলিক্স হেলথ ড্রিংক', 'horlicks-health-drink', 'DEMO-HORLICKS', NULL, NULL, NULL, NULL, '500 g', 'jar', 'Horlicks Health Drink — genuine product from Unilever.', 'Genuine Horlicks Health Drink from Unilever.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 650.00, 620.00, 508.40, 'none', 0.00, 0.00, NULL, 0, 0, 1, 50, 10, 1, NULL, 600, 'active', NULL, 0, 827, 104, 0.00, 0, 'Horlicks Health Drink — buy online', 'Order Horlicks Health Drink online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(68, NULL, 14, 25, 'general', 'Sensodyne Fresh Mint Toothpaste', 'সেনসোডাইন ফ্রেশ মিন্ট টুথপেস্ট', 'sensodyne-fresh-mint-toothpaste', 'DEMO-SENSODYNE', NULL, NULL, NULL, NULL, '100 g', 'tube', 'Sensodyne Fresh Mint Toothpaste — genuine product from Haleon.', 'Genuine Sensodyne Fresh Mint Toothpaste from Haleon.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 380.00, 360.00, 295.20, 'none', 0.00, 0.00, NULL, 0, 0, 1, 80, 10, 1, NULL, 150, 'active', NULL, 0, 758, 122, 0.00, 0, 'Sensodyne Fresh Mint Toothpaste — buy online', 'Order Sensodyne Fresh Mint Toothpaste online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(69, NULL, 11, 26, 'general', 'Dettol Original Soap', 'ডেটল অরিজিনাল সাবান', 'dettol-original-soap', 'DEMO-DETTOL-SOAP', NULL, NULL, NULL, NULL, '125 g', 'pcs', 'Dettol Original Soap — genuine product from Reckitt.', 'Genuine Dettol Original Soap from Reckitt.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 85.00, 80.00, 65.60, 'none', 0.00, 0.00, NULL, 0, 0, 1, 200, 10, 1, NULL, 150, 'active', NULL, 0, 612, 62, 0.00, 0, 'Dettol Original Soap — buy online', 'Order Dettol Original Soap online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(70, 11, 19, 26, 'general', 'Dettol Hand Wash Refill', 'ডেটল হ্যান্ড ওয়াশ রিফিল', 'dettol-hand-wash-refill', 'DEMO-DETTOL-HANDWASH', NULL, NULL, NULL, NULL, '170 ml', 'pack', 'Dettol Hand Wash Refill — genuine product from Reckitt.', 'Genuine Dettol Hand Wash Refill from Reckitt.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 115.00, 105.00, 86.10, 'none', 0.00, 0.00, NULL, 0, 0, 1, 150, 10, 1, NULL, 150, 'active', NULL, 0, 353, 152, 0.00, 0, 'Dettol Hand Wash Refill — buy online', 'Order Dettol Hand Wash Refill online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(71, NULL, 15, 27, 'general', 'Whisper Ultra Clean Sanitary Pad', 'হুইস্পার আল্ট্রা ক্লিন স্যানিটারি প্যাড', 'whisper-ultra-clean-sanitary-pad', 'DEMO-WHISPER', NULL, NULL, NULL, NULL, '8 pads', 'pack', 'Whisper Ultra Clean Sanitary Pad — genuine product from P&G.', 'Genuine Whisper Ultra Clean Sanitary Pad from P&G.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 220.00, 210.00, 172.20, 'none', 0.00, 0.00, NULL, 0, 0, 1, 90, 10, 1, NULL, 150, 'active', NULL, 0, 573, 10, 0.00, 0, 'Whisper Ultra Clean Sanitary Pad — buy online', 'Order Whisper Ultra Clean Sanitary Pad online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(72, NULL, 12, 23, 'general', 'Neutrogena Ultra Sheer Sunscreen SPF 50+', 'নিউট্রোজেনা আল্ট্রা শিয়ার সানস্ক্রিন SPF 50+', 'neutrogena-ultra-sheer-sunscreen-spf-50', 'DEMO-NEUTROGENA', NULL, NULL, NULL, NULL, '88 ml', 'tube', 'Neutrogena Ultra Sheer Sunscreen SPF 50+ — genuine product from Johnson\'s Baby.', 'Genuine Neutrogena Ultra Sheer Sunscreen SPF 50+ from Johnson\'s Baby.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 1450.00, 1290.00, 1057.80, 'none', 0.00, 0.00, NULL, 0, 0, 1, 35, 10, 1, NULL, 150, 'active', NULL, 1, 348, 142, 0.00, 0, 'Neutrogena Ultra Sheer Sunscreen SPF 50+ — buy online', 'Order Neutrogena Ultra Sheer Sunscreen SPF 50+ online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(73, 10, 12, 28, 'general', 'Himalaya Purifying Neem Face Wash', 'হিমালয়া পিউরিফাইং নিম ফেস ওয়াশ', 'himalaya-purifying-neem-face-wash', 'DEMO-HIMALAYA-FW', NULL, NULL, NULL, NULL, '100 ml', 'tube', 'Himalaya Purifying Neem Face Wash — genuine product from Himalaya.', 'Genuine Himalaya Purifying Neem Face Wash from Himalaya.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 320.00, 295.00, 241.90, 'none', 0.00, 0.00, NULL, 0, 0, 1, 75, 10, 1, NULL, 150, 'active', NULL, 0, 259, 87, 0.00, 0, 'Himalaya Purifying Neem Face Wash — buy online', 'Order Himalaya Purifying Neem Face Wash online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(74, NULL, 18, 26, 'general', 'Durex Extra Thin Condoms', 'ডিউরেক্স এক্সট্রা থিন কনডম', 'durex-extra-thin-condoms', 'DEMO-DUREX', NULL, NULL, NULL, NULL, '3 pcs', 'pack', 'Durex Extra Thin Condoms — genuine product from Reckitt.', 'Genuine Durex Extra Thin Condoms from Reckitt.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 150.00, 140.00, 114.80, 'none', 0.00, 0.00, NULL, 0, 0, 1, 100, 10, 1, NULL, 150, 'active', NULL, 0, 830, 167, 0.00, 0, 'Durex Extra Thin Condoms — buy online', 'Order Durex Extra Thin Condoms online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL),
(75, NULL, 4, 28, 'otc', 'Himalaya Tulasi Tablets', 'হিমালয়া তুলসি ট্যাবলেট', 'himalaya-tulasi-tablets-250-mg', 'DEMO-TULSI', NULL, 'Holy Basil (Tulsi) extract', '250 mg', 'Tablet', '60 tablets', 'bottle', 'Himalaya Tulasi Tablets — genuine product from Himalaya.', 'Genuine Himalaya Tulasi Tablets from Himalaya.\n\nDemo product: details are for illustration only.', NULL, NULL, NULL, 420.00, 399.00, 327.18, 'none', 0.00, 0.00, NULL, 0, 0, 1, 40, 10, 1, NULL, 150, 'active', NULL, 0, 820, 20, 0.00, 0, 'Himalaya Tulasi Tablets 250 mg — buy online', 'Order Himalaya Tulasi Tablets online with home delivery across Bangladesh.', NULL, NULL, '2026-11-21 03:28:01', '2026-11-21 03:28:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `path` varchar(255) NOT NULL,
  `alt` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `path`, `alt`, `sort_order`, `is_primary`, `created_at`) VALUES
(6, 38, 'uploads/demo/p-napa500.svg', 'Napa 500', 0, 1, '2026-11-21 03:28:01'),
(7, 39, 'uploads/demo/p-napaextra.svg', 'Napa Extra', 0, 1, '2026-11-21 03:28:01'),
(8, 40, 'uploads/demo/p-ace500.svg', 'Ace 500', 0, 1, '2026-11-21 03:28:01'),
(9, 41, 'uploads/demo/p-ace-plus.svg', 'Ace Plus', 0, 1, '2026-11-21 03:28:01'),
(10, 42, 'uploads/demo/p-fexo120.svg', 'Fexo 120', 0, 1, '2026-11-21 03:28:01'),
(11, 43, 'uploads/demo/p-alatrol.svg', 'Alatrol', 0, 1, '2026-11-21 03:28:01'),
(12, 44, 'uploads/demo/p-seclo20.svg', 'Seclo 20', 0, 1, '2026-11-21 03:28:01'),
(13, 45, 'uploads/demo/p-sergel20.svg', 'Sergel 20', 0, 1, '2026-11-21 03:28:01'),
(14, 46, 'uploads/demo/p-maxpro20.svg', 'Maxpro 20', 0, 1, '2026-11-21 03:28:01'),
(15, 47, 'uploads/demo/p-losectil20.svg', 'Losectil 20', 0, 1, '2026-11-21 03:28:01'),
(16, 48, 'uploads/demo/p-monas10.svg', 'Monas 10', 0, 1, '2026-11-21 03:28:01'),
(17, 49, 'uploads/demo/p-azithrocin.svg', 'Azithrocin 500', 0, 1, '2026-11-21 03:28:01'),
(18, 50, 'uploads/demo/p-bizoran.svg', 'Bislol 2.5', 0, 1, '2026-11-21 03:28:01'),
(19, 51, 'uploads/demo/p-comet500.svg', 'Comet 500', 0, 1, '2026-11-21 03:28:01'),
(20, 52, 'uploads/demo/p-orsaline.svg', 'Orsaline-N', 0, 1, '2026-11-21 03:28:01'),
(21, 53, 'uploads/demo/p-savlon.svg', 'Savlon Antiseptic Liquid', 0, 1, '2026-11-21 03:28:01'),
(22, 54, 'uploads/demo/p-bandage.svg', 'Elastic Crepe Bandage 4 inch', 0, 1, '2026-11-21 03:28:01'),
(23, 55, 'uploads/demo/p-accuchek.svg', 'Accu-Chek Active Glucometer', 0, 1, '2026-11-21 03:28:01'),
(24, 56, 'uploads/demo/p-accustrips.svg', 'Accu-Chek Active Test Strips', 0, 1, '2026-11-21 03:28:01'),
(25, 57, 'uploads/demo/p-bpmonitor.svg', 'Omron HEM-7120 BP Monitor', 0, 1, '2026-11-21 03:28:01'),
(26, 58, 'uploads/demo/p-thermo.svg', 'Omron Digital Thermometer MC-246', 0, 1, '2026-11-21 03:28:01'),
(27, 59, 'uploads/demo/p-mask.svg', 'Surgical Face Mask (3 ply)', 0, 1, '2026-11-21 03:28:01'),
(28, 60, 'uploads/demo/p-cerelac.svg', 'Nestlé Cerelac Wheat & Milk', 0, 1, '2026-11-21 03:28:01'),
(29, 61, 'uploads/demo/p-jbaby-lotion.svg', 'Johnson\'s Baby Lotion', 0, 1, '2026-11-21 03:28:01'),
(30, 62, 'uploads/demo/p-jbaby-shampoo.svg', 'Johnson\'s Baby Shampoo', 0, 1, '2026-11-21 03:28:01'),
(31, 63, 'uploads/demo/p-diaper.svg', 'Baby Diaper Pant (Medium)', 0, 1, '2026-11-21 03:28:01'),
(32, 64, 'uploads/demo/p-filwel.svg', 'Filwel Gold', 0, 1, '2026-11-21 03:28:01'),
(33, 65, 'uploads/demo/p-ceevit.svg', 'Ceevit 250', 0, 1, '2026-11-21 03:28:01'),
(34, 66, 'uploads/demo/p-calbo-d.svg', 'Calbo-D', 0, 1, '2026-11-21 03:28:01'),
(35, 67, 'uploads/demo/p-horlicks.svg', 'Horlicks Health Drink', 0, 1, '2026-11-21 03:28:01'),
(36, 68, 'uploads/demo/p-sensodyne.svg', 'Sensodyne Fresh Mint Toothpaste', 0, 1, '2026-11-21 03:28:01'),
(37, 69, 'uploads/demo/p-dettol-soap.svg', 'Dettol Original Soap', 0, 1, '2026-11-21 03:28:01'),
(38, 70, 'uploads/demo/p-dettol-handwash.svg', 'Dettol Hand Wash Refill', 0, 1, '2026-11-21 03:28:01'),
(39, 71, 'uploads/demo/p-whisper.svg', 'Whisper Ultra Clean Sanitary Pad', 0, 1, '2026-11-21 03:28:01'),
(40, 72, 'uploads/demo/p-neutrogena.svg', 'Neutrogena Ultra Sheer Sunscreen SPF 50+', 0, 1, '2026-11-21 03:28:01'),
(41, 73, 'uploads/demo/p-himalaya-fw.svg', 'Himalaya Purifying Neem Face Wash', 0, 1, '2026-11-21 03:28:01'),
(42, 74, 'uploads/demo/p-durex.svg', 'Durex Extra Thin Condoms', 0, 1, '2026-11-21 03:28:01'),
(43, 75, 'uploads/demo/p-tulsi.svg', 'Himalaya Tulasi Tablets', 0, 1, '2026-11-21 03:28:01');

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `name_en` varchar(150) NOT NULL,
  `name_bn` varchar(150) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `unit_multiplier` int(10) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'base units in this variant',
  `mrp` decimal(12,2) NOT NULL DEFAULT 0.00,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cost_price` decimal(12,2) DEFAULT NULL,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `weight_grams` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `name_en`, `name_bn`, `sku`, `barcode`, `unit_multiplier`, `mrp`, `price`, `cost_price`, `stock_qty`, `weight_grams`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
(17, 38, '1 Strip (10 tablets)', '১ পাতা (১০টি)', 'DEMO-NAPA500-1', NULL, 1, 12.00, 12.00, 9.84, 300, 0, 1, 1, '2026-11-21 03:28:01', '2026-11-21 03:28:01'),
(18, 38, '1 Box (20 strips)', '১ বক্স (২০ পাতা)', 'DEMO-NAPA500-2', NULL, 20, 240.00, 228.00, 186.96, 25, 0, 0, 1, '2026-11-21 03:28:01', '2026-11-21 03:28:01'),
(19, 52, '1 Sachet', '১টি প্যাকেট', 'DEMO-ORSALINE-1', NULL, 1, 6.00, 6.00, 4.92, 800, 0, 1, 1, '2026-11-21 03:28:02', '2026-11-21 03:28:02'),
(20, 52, 'Box of 20 sachets', '২০ প্যাকেটের বক্স', 'DEMO-ORSALINE-2', NULL, 20, 120.00, 110.00, 90.20, 40, 0, 0, 1, '2026-11-21 03:28:02', '2026-11-21 03:28:02'),
(21, 53, '112 ml', '১১২ মিলি', 'DEMO-SAVLON-1', NULL, 1, 75.00, 72.00, 59.04, 80, 0, 1, 1, '2026-11-21 03:28:02', '2026-11-21 03:28:02'),
(22, 53, '1 litre', '১ লিটার', 'DEMO-SAVLON-2', NULL, 1, 420.00, 399.00, 327.18, 30, 0, 0, 1, '2026-11-21 03:28:02', '2026-11-21 03:28:02'),
(23, 61, '100 ml', '১০০ মিলি', 'DEMO-JBABY-LOTION-1', NULL, 1, 220.00, 209.00, 171.38, 40, 0, 1, 1, '2026-11-21 03:28:02', '2026-11-21 03:28:02'),
(24, 61, '200 ml', '২০০ মিলি', 'DEMO-JBABY-LOTION-2', NULL, 1, 380.00, 355.00, 291.10, 25, 0, 0, 1, '2026-11-21 03:28:02', '2026-11-21 03:28:02');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `po_no` varchar(30) NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'stock owner; NULL = in-house',
  `status` enum('draft','ordered','partial','received','closed','cancelled') NOT NULL DEFAULT 'draft',
  `order_date` date NOT NULL,
  `expected_date` date DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `ordered_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity_ordered` int(10) UNSIGNED NOT NULL,
  `quantity_received` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) NOT NULL DEFAULT 0.00
) ;

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `key` char(64) NOT NULL COMMENT 'sha256 of the limiter key',
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `reset_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`key`, `attempts`, `reset_at`) VALUES
('00d7bdfcbfaa86bc337d479b7cf3820d4801671b20fe707b2af3c5b3a08eefc4', 2, '2026-11-21 15:10:33'),
('0595699be3d0904db5cbad7a0966a85f40b10bac77279f9705d992dd03673a86', 2, '2026-11-21 15:09:57'),
('519c783e113f732d8c82d1162db3a5dc807ff455169b6f2682af3da25efc6eb9', 3, '2026-11-21 16:11:50'),
('577f3375944248739f14359e27ca8e30d94206f1e76b084c1d0220c0b7b7b77d', 2, '2026-11-21 14:37:30'),
('880393ea3cbd69824e3ec5ed334d03dedfcf4e365934016f4d632f46b77c40c2', 1, '2026-11-21 13:44:23'),
('98d66203c366a94e41f9bbad8d18b6a363b7a8f4aa5596797f60bf020c42b76a', 4, '2026-11-21 15:09:29'),
('b2333b4653b0784cb36bbb5ab50959f8d1710c19fb062db072e6557debb013e8', 1, '2026-11-21 15:10:50'),
('c19eaea1d17763ba75fe7f0fccce49cfa92e80e134276b278a5e6457692df621', 1, '2026-11-21 15:09:29'),
('d3c0234c8ef2d5ccc4fe7610cc3f09a6065d81bff365cb620efdb3df18a93619', 2, '2026-11-21 15:17:52'),
('e362d0e9a4c434598965a45877468517ed3fee2ee5a32d78f7e203e1364fba8b', 4, '2026-11-21 15:26:05');

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `refund_no` varchar(30) NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `split_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` varchar(500) NOT NULL,
  `method` enum('original','bkash','nagad','bank','wallet','cash') NOT NULL DEFAULT 'original',
  `account_details` varchar(255) DEFAULT NULL,
  `restock` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('requested','approved','processed','rejected') NOT NULL DEFAULT 'requested',
  `reference_no` varchar(100) DEFAULT NULL,
  `admin_note` varchar(500) DEFAULT NULL,
  `requested_by` bigint(20) UNSIGNED DEFAULT NULL,
  `processed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `is_verified_purchase` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `vendor_reply` text DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `order_id`, `vendor_id`, `rating`, `title`, `comment`, `images`, `is_verified_purchase`, `status`, `vendor_reply`, `replied_at`, `created_at`, `updated_at`) VALUES
(2, 38, 32, NULL, NULL, 5, 'Works fast', 'Fever came down within an hour. Good packaging.', NULL, 0, 'approved', NULL, NULL, '2026-11-21 03:28:02', NULL),
(3, 55, 32, NULL, NULL, 5, 'Accurate meter', 'Easy to use, readings match my lab report.', NULL, 0, 'approved', NULL, NULL, '2026-11-21 03:28:02', NULL),
(4, 64, 32, NULL, NULL, 4, 'Good value', 'Original product, well before expiry.', NULL, 0, 'approved', NULL, NULL, '2026-11-21 03:28:02', NULL),
(5, 60, 32, NULL, NULL, 5, NULL, 'My baby loves it. Fresh stock.', NULL, 0, 'approved', NULL, NULL, '2026-11-21 03:28:02', NULL),
(6, 44, 32, NULL, NULL, 5, NULL, 'Genuine Square product, pharmacist called to check my prescription.', NULL, 0, 'approved', NULL, NULL, '2026-11-21 03:28:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `name_bn` varchar(100) DEFAULT NULL,
  `slug` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'system roles cannot be deleted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `name_bn`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'সুপার অ্যাডমিন', 'super-admin', 'Full access to everything', 1, '2026-11-20 21:35:13', NULL),
(2, 'Vendor', 'ভেন্ডর', 'vendor', 'Marketplace seller (vendor panel)', 1, '2026-11-20 21:35:13', NULL),
(3, 'Customer', 'কাস্টমার', 'customer', 'Storefront customer', 1, '2026-11-20 21:35:13', NULL),
(4, 'Manager', 'ম্যানেজার', 'manager', 'Operations manager - everything except staff, roles and system settings', 0, '2026-11-20 21:35:13', NULL),
(5, 'Pharmacist', 'ফার্মাসিস্ট', 'pharmacist', NULL, 0, '2026-11-20 21:35:13', '2026-11-21 13:49:37'),
(6, 'Order Manager', 'অর্ডার ম্যানেজার', 'order-manager', 'Processes orders and shipments', 0, '2026-11-20 21:35:13', NULL),
(7, 'Inventory Manager', 'ইনভেন্টরি ম্যানেজার', 'inventory-manager', 'Products, stock, batches and expiry', 0, '2026-11-20 21:35:13', NULL),
(8, 'Accountant', 'হিসাবরক্ষক', 'accountant', 'Payments, refunds, commissions, payouts and reports', 0, '2026-11-20 21:35:13', NULL),
(9, 'Support Agent', 'সাপোর্ট এজেন্ট', 'support-agent', 'Customer support tickets and reviews', 0, '2026-11-20 21:35:13', NULL),
(10, 'Content Editor', 'কনটেন্ট এডিটর', 'content-editor', 'Banners, pages, offers and catalog content', 0, '2026-11-20 21:35:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(1, 22),
(1, 23),
(1, 24),
(1, 25),
(1, 26),
(1, 27),
(1, 29),
(1, 30),
(1, 32),
(1, 33),
(1, 34),
(1, 35),
(1, 36),
(1, 37),
(1, 38),
(1, 39),
(1, 40),
(1, 41),
(1, 42),
(1, 43),
(1, 45),
(1, 47),
(1, 49),
(1, 51),
(1, 53),
(1, 55),
(1, 56),
(1, 57),
(1, 58),
(1, 61),
(1, 62),
(1, 63),
(1, 64),
(1, 65),
(1, 66),
(1, 67),
(1, 68),
(1, 69),
(1, 71),
(1, 74),
(1, 75),
(1, 76),
(1, 82),
(1, 89),
(1, 94),
(1, 98),
(1, 109),
(1, 112),
(1, 115),
(1, 134),
(1, 135),
(1, 136),
(1, 138),
(1, 139),
(1, 140),
(1, 141),
(1, 145),
(1, 146),
(1, 149),
(1, 152),
(1, 153),
(1, 154),
(1, 155),
(1, 156),
(1, 157),
(1, 158),
(1, 160),
(1, 161),
(1, 168),
(1, 169),
(1, 170),
(1, 171),
(1, 172),
(1, 173),
(1, 174),
(1, 175),
(1, 179),
(1, 184),
(1, 186),
(1, 187),
(1, 188),
(1, 190),
(1, 215),
(1, 216),
(1, 217),
(1, 218),
(1, 219),
(1, 220),
(1, 221),
(1, 222),
(1, 223),
(1, 224),
(1, 225),
(1, 226),
(1, 227),
(1, 782),
(1, 944),
(1, 945),
(1, 946),
(1, 947),
(1, 948),
(1, 949),
(1, 1111),
(4, 1),
(4, 2),
(4, 3),
(4, 4),
(4, 5),
(4, 6),
(4, 7),
(4, 8),
(4, 9),
(4, 10),
(4, 11),
(4, 12),
(4, 13),
(4, 14),
(4, 15),
(4, 16),
(4, 17),
(4, 18),
(4, 19),
(4, 20),
(4, 21),
(4, 22),
(4, 23),
(4, 24),
(4, 25),
(4, 26),
(4, 27),
(4, 29),
(4, 30),
(4, 32),
(4, 33),
(4, 34),
(4, 35),
(4, 36),
(4, 37),
(4, 38),
(4, 39),
(4, 40),
(4, 41),
(4, 42),
(4, 43),
(4, 45),
(4, 47),
(4, 49),
(4, 51),
(4, 53),
(4, 55),
(4, 56),
(4, 57),
(4, 58),
(4, 61),
(4, 62),
(4, 63),
(4, 64),
(4, 65),
(4, 66),
(4, 74),
(4, 75),
(4, 82),
(4, 89),
(4, 94),
(4, 98),
(4, 109),
(4, 112),
(4, 115),
(4, 134),
(4, 135),
(4, 136),
(4, 138),
(4, 139),
(4, 140),
(4, 141),
(4, 145),
(4, 146),
(4, 149),
(4, 152),
(4, 153),
(4, 154),
(4, 155),
(4, 156),
(4, 157),
(4, 158),
(4, 160),
(4, 161),
(4, 168),
(4, 169),
(4, 170),
(4, 171),
(4, 172),
(4, 173),
(4, 174),
(4, 175),
(4, 179),
(4, 215),
(4, 216),
(4, 217),
(4, 218),
(4, 219),
(4, 220),
(4, 221),
(4, 222),
(4, 223),
(4, 224),
(4, 225),
(4, 226),
(4, 227),
(4, 782),
(4, 944),
(4, 945),
(4, 946),
(4, 947),
(4, 948),
(4, 949),
(5, 1),
(5, 2),
(5, 4),
(5, 16),
(5, 18),
(5, 19),
(5, 22),
(5, 25),
(5, 26),
(5, 36),
(6, 1),
(6, 2),
(6, 16),
(6, 19),
(6, 20),
(6, 21),
(6, 22),
(6, 23),
(6, 24),
(6, 25),
(6, 27),
(6, 36),
(6, 39),
(6, 43),
(6, 45),
(6, 47),
(6, 49),
(6, 51),
(6, 53),
(6, 55),
(6, 62),
(6, 63),
(6, 65),
(6, 152),
(6, 153),
(6, 154),
(7, 1),
(7, 2),
(7, 3),
(7, 4),
(7, 5),
(7, 6),
(7, 7),
(7, 8),
(7, 12),
(7, 13),
(7, 14),
(7, 15),
(7, 16),
(7, 17),
(7, 18),
(7, 27),
(7, 65),
(7, 82),
(7, 94),
(7, 98),
(7, 215),
(7, 216),
(7, 217),
(7, 218),
(7, 219),
(7, 220),
(7, 221),
(7, 222),
(7, 223),
(7, 224),
(7, 225),
(7, 226),
(7, 227),
(8, 1),
(8, 16),
(8, 19),
(8, 24),
(8, 27),
(8, 32),
(8, 33),
(8, 34),
(8, 36),
(8, 39),
(8, 51),
(8, 53),
(8, 55),
(8, 56),
(8, 65),
(8, 66),
(8, 98),
(8, 153),
(8, 154),
(8, 160),
(8, 161),
(8, 216),
(8, 221),
(8, 227),
(8, 944),
(8, 945),
(8, 946),
(8, 947),
(8, 948),
(9, 1),
(9, 19),
(9, 25),
(9, 36),
(9, 51),
(9, 55),
(9, 57),
(9, 58),
(9, 62),
(9, 63),
(9, 64),
(9, 115),
(9, 179),
(10, 1),
(10, 2),
(10, 4),
(10, 8),
(10, 9),
(10, 10),
(10, 11),
(10, 12),
(10, 13),
(10, 14),
(10, 15),
(10, 39),
(10, 43),
(10, 45),
(10, 57),
(10, 58),
(10, 89),
(10, 94),
(10, 134),
(10, 135),
(10, 136),
(10, 138),
(10, 139),
(10, 140),
(10, 168),
(10, 169),
(10, 170),
(10, 171),
(10, 172),
(10, 173),
(10, 174),
(10, 175);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `group` varchar(50) NOT NULL DEFAULT 'general',
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `type` enum('string','int','decimal','bool','json','text') NOT NULL DEFAULT 'string',
  `label` varchar(150) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'safe to expose on storefront',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `group`, `key`, `value`, `type`, `label`, `is_public`, `updated_by`, `updated_at`) VALUES
(1, 'general', 'site_name', 'SasthoBazar', 'string', 'Site name (English)', 1, NULL, NULL),
(2, 'general', 'site_name_bn', 'স্বাস্থ্যবাজার', 'string', 'Site name (Bangla)', 1, 1, '2026-11-21 13:49:37'),
(3, 'general', 'site_tagline', 'আপনার বিশ্বস্ত অনলাইন ফার্মেসি ও হেলথকেয়ার শপ', 'string', 'Tagline', 1, 1, '2026-11-21 13:51:54'),
(4, 'general', 'contact_phone', '09600000000', 'string', 'Hotline', 1, NULL, NULL),
(5, 'general', 'contact_email', 'support@sasthobazar.com', 'string', 'Support email', 1, NULL, NULL),
(6, 'general', 'contact_address', 'ঢাকা, বাংলাদেশ', 'string', 'Office address', 1, 1, '2026-11-21 13:49:37'),
(7, 'general', 'default_language', 'bn', 'string', 'Default language (bn/en)', 1, NULL, NULL),
(8, 'general', 'timezone', 'Asia/Dhaka', 'string', 'Timezone', 0, NULL, NULL),
(9, 'general', 'maintenance_mode', '0', 'bool', 'Maintenance mode', 0, NULL, NULL),
(10, 'currency', 'currency_code', 'BDT', 'string', 'Currency code', 1, NULL, NULL),
(11, 'currency', 'currency_symbol', '৳', 'string', 'Currency symbol', 1, NULL, NULL),
(12, 'currency', 'currency_bn_digits', '1', 'bool', 'Show Bangla digits in Bangla UI', 1, NULL, NULL),
(13, 'commission', 'default_commission_rate', '2.00', 'decimal', 'Default commission % (global)', 0, NULL, '2026-11-21 03:51:50'),
(14, 'commission', 'commission_on', 'subtotal_after_discount', 'string', 'Commission base: subtotal_after_discount | subtotal', 0, NULL, NULL),
(15, 'vendor', 'vendor_registration_enabled', '1', 'bool', 'Allow vendor sign-up', 1, NULL, NULL),
(16, 'vendor', 'vendor_auto_approve', '0', 'bool', 'Auto-approve new vendors', 0, NULL, NULL),
(17, 'vendor', 'vendor_product_auto_approve', '0', 'bool', 'Auto-approve vendor products', 0, NULL, NULL),
(18, 'vendor', 'payout_min_amount', '500.00', 'decimal', 'Minimum payout request (BDT)', 0, NULL, NULL),
(19, 'vendor', 'payout_hold_days', '7', 'int', 'Days after delivery before earnings are withdrawable', 0, NULL, NULL),
(20, 'order', 'order_prefix', 'SB', 'string', 'Order number prefix', 0, NULL, NULL),
(21, 'order', 'min_order_amount', '100.00', 'decimal', 'Minimum order amount (BDT)', 1, NULL, NULL),
(22, 'order', 'max_cod_amount', '20000.00', 'decimal', 'Maximum Cash-on-Delivery amount (BDT)', 1, NULL, NULL),
(23, 'order', 'order_auto_cancel_hours', '48', 'int', 'Auto-cancel unconfirmed orders after (hours)', 0, NULL, NULL),
(24, 'order', 'return_window_days', '7', 'int', 'Return window after delivery (days)', 1, NULL, NULL),
(25, 'tax', 'vat_enabled', '0', 'bool', 'Charge VAT', 0, NULL, '2026-11-21 13:35:26'),
(26, 'tax', 'vat_rate', '0.00', 'decimal', 'Default VAT %', 0, NULL, '2026-11-21 13:35:26'),
(27, 'shipping', 'free_shipping_threshold', '1500.00', 'decimal', 'Free shipping above (BDT, 0 = off)', 1, NULL, NULL),
(28, 'inventory', 'low_stock_threshold', '10', 'int', 'Default low-stock alert level', 0, 1, '2026-11-20 22:45:24'),
(29, 'inventory', 'expiry_alert_days', '90', 'int', 'Warn when batch expires within (days)', 0, 1, '2026-11-20 22:45:24'),
(30, 'inventory', 'allocation_strategy', 'FEFO', 'string', 'Batch allocation: FEFO (first-expiry-first-out)', 0, NULL, NULL),
(31, 'prescription', 'prescription_max_size_mb', '5', 'int', 'Max prescription upload size (MB)', 0, NULL, NULL),
(32, 'prescription', 'prescription_allowed_types', '[\"jpg\",\"jpeg\",\"png\",\"pdf\"]', 'json', 'Allowed prescription file types', 0, NULL, NULL),
(33, 'payment', 'cod_enabled', '1', 'bool', 'Cash on Delivery', 1, NULL, NULL),
(34, 'payment', 'bkash_enabled', '0', 'bool', 'bKash', 1, NULL, '2026-11-21 03:17:29'),
(35, 'payment', 'nagad_enabled', '0', 'bool', 'Nagad', 1, NULL, NULL),
(36, 'payment', 'sslcommerz_enabled', '0', 'bool', 'SSLCommerz (cards)', 1, NULL, NULL),
(37, 'payment', 'payment_sandbox', '1', 'bool', 'Payment gateways in sandbox mode', 0, NULL, NULL),
(38, 'sms', 'sms_enabled', '0', 'bool', 'Send SMS notifications', 0, NULL, '2026-11-21 13:35:27'),
(39, 'sms', 'sms_provider', '', 'string', 'SMS gateway', 0, NULL, NULL),
(40, 'seo', 'meta_title', 'SasthoBazar - Online Pharmacy & Healthcare Shop in Bangladesh', 'string', 'Default meta title', 1, NULL, NULL),
(41, 'seo', 'meta_description', 'Order genuine medicines, healthcare & beauty products online with home delivery across Bangladesh.', 'text', 'Default meta description', 1, NULL, NULL),
(42, 'social', 'facebook_url', '', 'string', 'Facebook page', 1, NULL, NULL),
(43, 'social', 'youtube_url', '', 'string', 'YouTube channel', 1, NULL, NULL),
(44, 'inventory', 'block_expired_stock_in', '1', 'bool', 'Refuse stock-in of already expired batches', 0, 1, '2026-11-20 22:44:34'),
(50, 'payment', 'rocket_enabled', '0', 'bool', 'Rocket (manual TrxID) enabled', 1, NULL, '2026-11-21 02:27:58'),
(51, 'payment', 'bkash_number', '', 'string', 'bKash number customers send money to', 1, NULL, '2026-11-21 03:17:29'),
(52, 'payment', 'nagad_number', '', 'string', 'Nagad number customers send money to', 1, NULL, NULL),
(53, 'payment', 'rocket_number', '', 'string', 'Rocket number customers send money to', 1, NULL, '2026-11-21 02:27:58'),
(54, 'payment', 'bkash_account_type', 'personal', 'string', 'bKash account type (personal / merchant / agent)', 1, NULL, NULL),
(55, 'payment', 'nagad_account_type', 'personal', 'string', 'Nagad account type', 1, NULL, NULL),
(56, 'payment', 'rocket_account_type', 'personal', 'string', 'Rocket account type', 1, NULL, NULL),
(57, 'vendor', 'vendor_can_cancel', '1', 'bool', 'Vendors may cancel their confirmed sub-orders', 0, NULL, NULL),
(58, 'shipping', 'warehouse_district_id', '147', 'int', 'District of our own warehouse (in-house stock ships from here)', 0, NULL, NULL),
(59, 'shipping', 'store_pickup_enabled', '1', 'bool', 'Customers may collect in-house orders from the store', 1, NULL, NULL),
(60, 'shipping', 'store_pickup_address', '', 'string', 'Store pickup address shown at checkout', 1, NULL, NULL),
(61, 'shipping', 'courier_mock_mode', '1', 'bool', 'Use mock courier responses (no real API calls)', 0, NULL, NULL),
(62, 'system', 'demo_data', '{\"brands\":[11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28],\"vendors\":[10,11],\"users\":[32],\"products\":[38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75],\"flash_sales\":[6],\"banners\":[1,2,3],\"coupons\":[10,11]}', 'json', 'Records created by database/demo.php', 0, NULL, '2026-11-21 03:28:02'),
(65, 'tax', 'vat_inclusive', '1', 'bool', 'vat_inclusive', 0, NULL, '2026-11-21 13:35:26'),
(66, 'tax', 'vat_label', 'VAT', 'string', 'vat_label', 0, NULL, NULL),
(67, 'tax', 'vat_reg_no', '', 'string', 'vat_reg_no', 0, NULL, '2026-11-21 09:20:04'),
(68, 'general', 'site_logo', '', 'string', 'site_logo', 0, NULL, '2026-11-21 13:14:44'),
(69, 'general', 'site_favicon', '', 'string', 'site_favicon', 0, NULL, NULL),
(70, 'general', 'site_tagline_en', 'Your trusted online pharmacy & healthcare shop', 'string', 'site_tagline_en', 0, NULL, NULL),
(71, 'social', 'instagram_url', '', 'string', 'instagram_url', 0, NULL, NULL),
(72, 'social', 'whatsapp_number', '', 'string', 'whatsapp_number', 0, NULL, NULL),
(73, 'order', 'review_auto_approve', '1', 'bool', 'review_auto_approve', 0, NULL, NULL),
(74, 'sms', 'sms_driver', 'log', 'string', 'sms_driver', 0, NULL, '2026-11-21 13:35:27'),
(75, 'sms', 'sms_api_url', '', 'string', 'sms_api_url', 0, NULL, '2026-11-21 13:35:27'),
(76, 'sms', 'sms_api_method', 'GET', 'string', 'sms_api_method', 0, NULL, NULL),
(77, 'sms', 'sms_api_key', '', 'string', 'sms_api_key', 0, NULL, NULL),
(78, 'sms', 'sms_sender_id', '', 'string', 'sms_sender_id', 0, NULL, NULL),
(79, 'sms', 'sms_success_keyword', '', 'string', 'sms_success_keyword', 0, NULL, NULL),
(80, 'mail', 'mail_enabled', '0', 'bool', 'mail_enabled', 0, NULL, NULL),
(81, 'mail', 'mail_driver', 'log', 'string', 'mail_driver', 0, NULL, NULL),
(82, 'mail', 'mail_host', '', 'string', 'mail_host', 0, NULL, NULL),
(83, 'mail', 'mail_port', '587', 'int', 'mail_port', 0, NULL, NULL),
(84, 'mail', 'mail_username', '', 'string', 'mail_username', 0, NULL, NULL),
(85, 'mail', 'mail_password', '', 'string', 'mail_password', 0, NULL, '2026-11-21 13:14:44'),
(86, 'mail', 'mail_encryption', 'tls', 'string', 'mail_encryption', 0, NULL, NULL),
(87, 'mail', 'mail_from_address', '', 'string', 'mail_from_address', 0, NULL, NULL),
(88, 'mail', 'mail_from_name', 'SasthoBazar', 'string', 'mail_from_name', 0, NULL, NULL),
(89, 'notify', 'notify_events', '{\"order_placed\":{\"sms\":1,\"email\":1},\"order_confirmed\":{\"sms\":0,\"email\":1},\"order_shipped\":{\"sms\":1,\"email\":1},\"order_delivered\":{\"sms\":1,\"email\":1},\"order_cancelled\":{\"sms\":1,\"email\":1},\"refund_update\":{\"sms\":1,\"email\":1},\"prescription\":{\"sms\":1,\"email\":1},\"ticket_reply\":{\"sms\":0,\"email\":1},\"payout_update\":{\"sms\":1,\"email\":1},\"vendor_status\":{\"sms\":1,\"email\":1}}', 'json', 'notify_events', 0, NULL, '2026-11-21 13:14:44'),
(90, 'cron', 'cron_last_release_earnings', '2026-11-21 13:33:33', 'string', 'cron_last_release_earnings', 0, NULL, NULL),
(91, 'cron', 'cron_last_messages', '2026-11-21 13:33:33', 'string', 'cron_last_messages', 0, NULL, NULL),
(92, 'cron', 'cron_last_courier_sync', '2026-11-21 13:33:33', 'string', 'cron_last_courier_sync', 0, NULL, NULL),
(93, 'cron', 'cron_last_expiry_digest', '2026-11-21 13:33:33', 'string', 'cron_last_expiry_digest', 0, NULL, NULL),
(94, 'cron', 'cron_last_backup', '2026-11-21 13:50:59', 'string', 'cron_last_backup', 0, NULL, '2026-11-21 13:50:59'),
(95, 'cron', 'cron_last_cleanup', '2026-11-21 13:33:33', 'string', 'cron_last_cleanup', 0, NULL, NULL),
(96, 'cron', 'cron_last_heartbeat', '2026-11-21 13:50:59', 'string', 'cron_last_heartbeat', 0, NULL, '2026-11-21 13:50:59'),
(100, 'checkout', 'guest_checkout_enabled', '1', 'bool', 'guest_checkout_enabled', 0, 1, '2026-11-21 14:11:24'),
(101, 'checkout', 'guest_default_contact', 'phone', 'string', 'guest_default_contact', 0, 1, '2026-11-21 14:11:24'),
(102, 'checkout', 'guest_otp_required', '1', 'bool', 'guest_otp_required', 0, NULL, NULL),
(103, 'prescription', 'prescription_order_enabled', '1', 'bool', 'prescription_order_enabled', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `split_id` bigint(20) UNSIGNED DEFAULT NULL,
  `courier_id` int(10) UNSIGNED DEFAULT NULL,
  `delivery_option` enum('standard','express','pickup') NOT NULL DEFAULT 'standard',
  `zone_id` int(10) UNSIGNED DEFAULT NULL,
  `rate_id` int(10) UNSIGNED DEFAULT NULL,
  `origin_district_id` int(10) UNSIGNED DEFAULT NULL,
  `dest_district_id` int(10) UNSIGNED DEFAULT NULL,
  `tracking_no` varchar(100) DEFAULT NULL,
  `consignment_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','booked','picked_up','in_transit','out_for_delivery','on_hold','failed','delivered','partial_delivered','returning','returned','cancelled','lost') NOT NULL DEFAULT 'pending',
  `failed_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `cod_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'charged to the customer',
  `cod_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `collected_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cod_status` enum('none','pending','collected','settled') NOT NULL DEFAULT 'none',
  `cod_settlement_id` bigint(20) UNSIGNED DEFAULT NULL,
  `delivery_charge` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'charged by courier',
  `weight_grams` int(10) UNSIGNED DEFAULT NULL,
  `rider_name` varchar(150) DEFAULT NULL,
  `rider_phone` varchar(20) DEFAULT NULL,
  `rider_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `api_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`api_response`)),
  `is_mock` tinyint(1) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `booked_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `last_synced_at` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipments`
--

INSERT INTO `shipments` (`id`, `order_id`, `split_id`, `courier_id`, `delivery_option`, `zone_id`, `rate_id`, `origin_district_id`, `dest_district_id`, `tracking_no`, `consignment_id`, `status`, `failed_attempts`, `cod_amount`, `shipping_fee`, `cod_charge`, `collected_amount`, `cod_status`, `cod_settlement_id`, `delivery_charge`, `weight_grams`, `rider_name`, `rider_phone`, `rider_user_id`, `api_response`, `is_mock`, `note`, `booked_at`, `shipped_at`, `delivered_at`, `returned_at`, `last_synced_at`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 17, 23, 1, 'standard', 3, 4, 147, 135, 'OWN261121000005', 'OWN261121000005', 'delivered', 0, 876.68, 130.00, 8.68, 876.68, 'collected', NULL, 0.00, 300, 'rupok', '01798060887', 1, '{\"rider\":{\"name\":\"rupok\",\"phone\":\"01798060887\",\"user_id\":1}}', 0, NULL, '2026-11-21 09:36:37', '2026-11-21 09:36:44', '2026-11-21 09:37:29', NULL, NULL, 33, '2026-11-21 03:32:48', '2026-11-21 09:37:29');

-- --------------------------------------------------------

--
-- Table structure for table `shipment_events`
--

CREATE TABLE `shipment_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shipment_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(30) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `source` enum('system','api','webhook','staff','vendor') NOT NULL DEFAULT 'system',
  `raw` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw`)),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipment_events`
--

INSERT INTO `shipment_events` (`id`, `shipment_id`, `status`, `note`, `source`, `raw`, `created_by`, `created_at`) VALUES
(20, 5, 'pending', NULL, 'system', NULL, 33, '2026-11-21 03:32:48'),
(36, 5, 'booked', 'Own delivery man এ বুক হয়েছে, ট্র্যাকিং OWN261121000005।', 'staff', '{\"rider\":{\"name\":\"rupok\",\"phone\":\"01798060887\",\"user_id\":1}}', 1, '2026-11-21 09:36:37'),
(37, 5, 'picked_up', NULL, 'staff', NULL, 1, '2026-11-21 09:36:44'),
(38, 5, 'out_for_delivery', NULL, 'staff', NULL, 1, '2026-11-21 09:37:10'),
(39, 5, 'delivered', NULL, 'staff', NULL, 1, '2026-11-21 09:37:29');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_rates`
--

CREATE TABLE `shipping_rates` (
  `id` int(10) UNSIGNED NOT NULL,
  `zone_id` int(10) UNSIGNED NOT NULL,
  `service` enum('standard','express') NOT NULL DEFAULT 'standard',
  `name` varchar(100) NOT NULL COMMENT 'e.g. Standard, Express',
  `name_bn` varchar(100) DEFAULT NULL,
  `min_weight_grams` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `max_weight_grams` int(10) UNSIGNED DEFAULT NULL,
  `min_order_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `extra_per_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `free_shipping_threshold` decimal(12,2) DEFAULT NULL,
  `cod_charge_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `est_days_min` tinyint(3) UNSIGNED DEFAULT NULL,
  `est_days_max` tinyint(3) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipping_rates`
--

INSERT INTO `shipping_rates` (`id`, `zone_id`, `service`, `name`, `name_bn`, `min_weight_grams`, `max_weight_grams`, `min_order_amount`, `rate`, `extra_per_kg`, `free_shipping_threshold`, `cod_charge_percent`, `est_days_min`, `est_days_max`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'standard', 'Standard', 'স্ট্যান্ডার্ড', 0, NULL, 0.00, 60.00, 15.00, 1500.00, 0.00, 1, 2, 1, '2026-11-20 21:35:13', NULL),
(2, 1, 'express', 'Express (same day)', 'এক্সপ্রেস (একই দিনে)', 0, NULL, 0.00, 120.00, 20.00, NULL, 0.00, 0, 0, 1, '2026-11-20 21:35:13', '2026-11-21 03:01:12'),
(3, 2, 'standard', 'Standard', 'স্ট্যান্ডার্ড', 0, NULL, 0.00, 100.00, 20.00, 2000.00, 1.00, 2, 3, 1, '2026-11-20 21:35:13', NULL),
(4, 3, 'standard', 'Standard', 'স্ট্যান্ডার্ড', 0, NULL, 0.00, 130.00, 20.00, 2500.00, 1.00, 3, 5, 1, '2026-11-20 21:35:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `shipping_zones`
--

CREATE TABLE `shipping_zones` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `name_bn` varchar(100) DEFAULT NULL,
  `zone_type` enum('city','suburb','outside') NOT NULL DEFAULT 'outside',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipping_zones`
--

INSERT INTO `shipping_zones` (`id`, `name`, `name_bn`, `zone_type`, `sort_order`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Inside Dhaka City', 'ঢাকা সিটির ভিতরে', 'city', 1, 'Dhaka district', 1, '2026-11-20 21:35:13', '2026-11-21 03:01:12'),
(2, 'Dhaka Sub-area', 'ঢাকার উপশহর', 'suburb', 2, 'Other districts of Dhaka division', 1, '2026-11-20 21:35:13', '2026-11-21 03:01:12'),
(3, 'Outside Dhaka', 'ঢাকার বাইরে', 'outside', 3, 'All other districts', 1, '2026-11-20 21:35:13', '2026-11-21 03:01:12');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` enum('purchase','stock_in','sale','return','stock_out','adjustment','damage','expired','recall','transfer','cancel') NOT NULL,
  `quantity` int(11) NOT NULL COMMENT 'positive = in, negative = out',
  `balance_after` int(11) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'order, refund, adjustment...',
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `variant_id`, `batch_id`, `vendor_id`, `type`, `quantity`, `balance_after`, `unit_cost`, `reference_type`, `reference_id`, `note`, `created_by`, `created_at`) VALUES
(73, 38, 17, 32, NULL, 'stock_in', 300, 300, 9.84, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(74, 38, 18, 33, NULL, 'stock_in', 25, 25, 186.96, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(75, 39, NULL, 34, NULL, 'stock_in', 300, 300, 20.50, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(76, 40, NULL, 35, 10, 'stock_in', 250, 250, 9.02, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(77, 41, NULL, 36, 10, 'stock_in', 180, 180, 18.86, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(78, 42, NULL, 37, NULL, 'stock_in', 150, 150, 69.70, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(79, 43, NULL, 38, NULL, 'stock_in', 8, 8, 24.60, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(80, 44, NULL, 39, NULL, 'stock_in', 200, 200, 51.66, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(81, 45, NULL, 40, NULL, 'stock_in', 160, 160, 54.94, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(82, 46, NULL, 41, 11, 'stock_in', 120, 120, 57.40, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(83, 47, NULL, 42, 11, 'stock_in', 90, 90, 38.54, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(84, 48, NULL, 43, NULL, 'stock_in', 100, 100, 124.64, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(85, 49, NULL, 44, NULL, 'stock_in', 12, 12, 86.10, 'manual', NULL, 'Demo: near expiry', NULL, '2026-11-21 03:28:01'),
(86, 49, NULL, 45, NULL, 'stock_in', 80, 92, 86.10, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(87, 50, NULL, 46, NULL, 'stock_in', 110, 110, 46.74, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:01'),
(88, 51, NULL, 47, NULL, 'stock_in', 220, 220, 31.16, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(89, 52, 19, 48, NULL, 'stock_in', 800, 800, 4.92, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(90, 52, 20, 49, NULL, 'stock_in', 40, 40, 90.20, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(91, 53, 21, 50, NULL, 'stock_in', 80, 80, 59.04, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(92, 53, 22, 51, NULL, 'stock_in', 30, 30, 327.18, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(93, 54, NULL, 52, 10, 'stock_in', 70, 70, 69.70, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(94, 55, NULL, 53, NULL, 'stock_in', 25, 25, 1189.00, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(95, 56, NULL, 54, NULL, 'stock_in', 40, 40, 1139.80, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(96, 57, NULL, 55, 10, 'stock_in', 15, 15, 3239.00, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(97, 58, NULL, 56, NULL, 'stock_in', 60, 60, 327.18, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(98, 59, NULL, 57, 11, 'stock_in', 100, 100, 163.18, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(99, 60, NULL, 58, NULL, 'stock_in', 60, 60, 487.90, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(100, 61, 23, 59, NULL, 'stock_in', 40, 40, 171.38, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(101, 61, 24, 60, NULL, 'stock_in', 25, 25, 291.10, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(102, 62, NULL, 61, 10, 'stock_in', 45, 45, 327.18, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(103, 63, NULL, 62, NULL, 'stock_in', 30, 30, 983.18, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(104, 64, NULL, 63, NULL, 'stock_in', 70, 70, 344.40, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(105, 65, NULL, 64, 11, 'stock_in', 300, 300, 12.30, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(106, 66, NULL, 65, NULL, 'stock_in', 130, 130, 129.56, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(107, 67, NULL, 66, NULL, 'stock_in', 50, 50, 508.40, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(108, 68, NULL, 67, NULL, 'stock_in', 80, 80, 295.20, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(109, 69, NULL, 68, NULL, 'stock_in', 200, 200, 65.60, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(110, 70, NULL, 69, 11, 'stock_in', 150, 150, 86.10, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(111, 71, NULL, 70, NULL, 'stock_in', 90, 90, 172.20, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(112, 72, NULL, 71, NULL, 'stock_in', 35, 35, 1057.80, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(113, 73, NULL, 72, 10, 'stock_in', 75, 75, 241.90, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(114, 74, NULL, 73, NULL, 'stock_in', 100, 100, 114.80, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(115, 75, NULL, 74, NULL, 'stock_in', 40, 40, 327.18, 'manual', NULL, 'Demo opening stock', NULL, '2026-11-21 03:28:02'),
(116, 64, NULL, 63, NULL, 'sale', -2, 68, 344.40, 'order', 17, 'SB26112144603', 33, '2026-11-21 03:32:48');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `drug_license_no` varchar(100) DEFAULT NULL,
  `tin_no` varchar(50) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL COMMENT 'e.g. Cash, 30 days credit',
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_no` varchar(30) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT 'customer or vendor user who opened it',
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `category` enum('order','payment','delivery','product','refund','prescription','account','vendor','other') NOT NULL DEFAULT 'other',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status` enum('open','in_progress','waiting_customer','resolved','closed') NOT NULL DEFAULT 'open',
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `last_reply_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_replies`
--

CREATE TABLE `support_ticket_replies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `message` text NOT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `is_internal` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'staff-only note',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED DEFAULT NULL,
  `user_type` enum('super_admin','employee','vendor','customer') NOT NULL DEFAULT 'customer',
  `name` varchar(150) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `lang` enum('bn','en') NOT NULL DEFAULT 'bn',
  `status` enum('active','inactive','banned') NOT NULL DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `phone_verified_at` datetime DEFAULT NULL,
  `failed_logins` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `user_type`, `name`, `email`, `phone`, `password_hash`, `avatar`, `gender`, `date_of_birth`, `lang`, `status`, `email_verified_at`, `phone_verified_at`, `failed_logins`, `locked_until`, `last_login_at`, `last_login_ip`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'super_admin', 'Super Admin', 'admin@sasthobazar.com', '01700000000', '$2y$10$NrmKN1q.qym82Lcf.Qn7XOab3x4V8YIaRm7URwHcM5XgwFBneAZ7e', NULL, NULL, NULL, 'bn', 'active', '2026-11-20 21:35:13', '2026-11-20 21:35:13', 0, NULL, '2026-11-21 14:49:54', '127.0.0.1', NULL, '2026-11-20 21:35:13', '2026-11-21 14:49:54', NULL),
(30, 2, 'vendor', 'Demo Pharmacy Dhanmondi (owner)', NULL, '01700000101', '$2y$10$AeUHmrpsCjrzbzkklEdP1eur4Z3tC6/D/iNESDmFPNDj8bYg7HoyO', NULL, NULL, NULL, 'bn', 'active', NULL, NULL, 0, NULL, '2026-11-21 13:36:01', '127.0.0.1', NULL, '2026-11-21 03:28:01', '2026-11-21 13:36:01', NULL),
(31, 2, 'vendor', 'Demo Health Store Chattogram (owner)', NULL, '01700000102', '$2y$10$hMwEGWZY56VKkefYSrAEMuPT.g22DMaypsGjJk3J4semqOx7Zgu7u', NULL, NULL, NULL, 'bn', 'active', NULL, NULL, 0, NULL, '2026-11-21 08:12:36', '::1', NULL, '2026-11-21 03:28:01', '2026-11-21 08:12:36', NULL),
(32, 3, 'customer', 'Demo Customer', NULL, '01700000099', '$2y$10$NVMqBCKLyNLvp8hDv0SX6.kEKK2V8Fod4v2znRYRsKa6cbmeZK.7S', NULL, NULL, NULL, 'bn', 'active', NULL, NULL, 0, NULL, '2026-11-21 13:36:03', '127.0.0.1', NULL, '2026-11-21 03:28:02', '2026-11-21 13:36:03', NULL),
(33, 3, 'customer', 'Mahbub Sajon', NULL, '01798060887', '$2y$10$aTh6kcZv/I96856zfCNLwO9sh3FCQCwI6f.eDRibXowK4BS8BM4Oe', NULL, NULL, NULL, 'bn', 'active', NULL, NULL, 0, NULL, '2026-11-21 03:31:10', '::1', NULL, '2026-11-21 03:31:10', '2026-11-21 03:31:10', NULL),
(65, 2, 'vendor', 'rupok', 'toriqulislam333000@gmail.com', '01896499266', '$2y$10$PBl7F.5760hGEhHYD3tbaO7KL7U/VOHOGBIn4WX4V6ZelZ..6C/AW', NULL, NULL, NULL, 'bn', 'active', NULL, NULL, 0, NULL, '2026-11-21 15:12:41', '::1', NULL, '2026-11-21 15:12:41', '2026-11-21 15:12:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `shop_name` varchar(150) NOT NULL,
  `shop_name_bn` varchar(150) DEFAULT NULL,
  `slug` varchar(180) NOT NULL,
  `vendor_type` varchar(40) NOT NULL DEFAULT 'pharmacy',
  `vendor_type_id` int(10) UNSIGNED DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `pickup_phone` varchar(20) DEFAULT NULL,
  `location_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'district/upazila',
  `trade_license_no` varchar(100) DEFAULT NULL,
  `drug_license_no` varchar(100) DEFAULT NULL COMMENT 'DGDA licence, required for medicine sellers',
  `drug_license_expiry` date DEFAULT NULL,
  `tin_no` varchar(50) DEFAULT NULL,
  `bin_no` varchar(50) DEFAULT NULL,
  `nid_no` varchar(30) DEFAULT NULL,
  `documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'uploaded licence file paths' CHECK (json_valid(`documents`)),
  `commission_rate` decimal(5,2) DEFAULT NULL COMMENT 'NULL = use category/global rate',
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `bank_account_name` varchar(150) DEFAULT NULL,
  `bank_account_no` varchar(50) DEFAULT NULL,
  `bank_routing_no` varchar(20) DEFAULT NULL,
  `mobile_banking_provider` enum('bkash','nagad','rocket','upay') DEFAULT NULL,
  `mobile_banking_no` varchar(20) DEFAULT NULL,
  `status` enum('pending','approved','suspended','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(255) DEFAULT NULL,
  `suspension_reason` varchar(255) DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rating_avg` decimal(3,2) NOT NULL DEFAULT 0.00,
  `rating_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `user_id`, `shop_name`, `shop_name_bn`, `slug`, `vendor_type`, `vendor_type_id`, `logo`, `banner`, `description`, `contact_person`, `phone`, `email`, `address`, `pickup_phone`, `location_id`, `trade_license_no`, `drug_license_no`, `drug_license_expiry`, `tin_no`, `bin_no`, `nid_no`, `documents`, `commission_rate`, `bank_name`, `bank_branch`, `bank_account_name`, `bank_account_no`, `bank_routing_no`, `mobile_banking_provider`, `mobile_banking_no`, `status`, `rejection_reason`, `suspension_reason`, `approved_by`, `approved_at`, `rating_avg`, `rating_count`, `is_featured`, `created_at`, `updated_at`, `deleted_at`) VALUES
(10, 30, 'Demo Pharmacy Dhanmondi', 'ডেমো ফার্মেসি ধানমন্ডি', 'demo-pharmacy-dhanmondi', 'pharmacy', 1, NULL, NULL, 'Demo vendor created by database/demo.php', 'Demo owner', '01700000101', NULL, 'House 12, Road 5, Dhanmondi', NULL, 1365, 'DEMO-TL-1', 'DEMO-DL-1', '2028-11-21', NULL, NULL, '1990111111', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'bkash', '01700000101', 'approved', NULL, NULL, NULL, '2026-11-21 03:28:01', 0.00, 0, 0, '2026-11-21 03:28:01', '2026-11-21 14:20:05', NULL),
(11, 31, 'Demo Health Store Chattogram', 'ডেমো হেলথ স্টোর চট্টগ্রাম', 'demo-health-store-ctg', 'pharmacy', 1, NULL, NULL, 'Demo vendor created by database/demo.php', 'Demo owner', '01700000102', NULL, 'GEC Circle, Chattogram', NULL, 108, 'DEMO-TL-2', 'DEMO-DL-2', '2028-11-21', NULL, NULL, '1990222222', NULL, 3.00, NULL, NULL, NULL, NULL, NULL, 'bkash', '01700000102', 'approved', NULL, NULL, NULL, '2026-11-21 03:28:01', 0.00, 0, 0, '2026-11-21 03:28:01', '2026-11-21 14:20:05', NULL),
(23, 65, 'rupok firma', NULL, 'rupok-firma', 'other', 8, NULL, NULL, NULL, 'rupok', '01896499266', 'toriqulislam333000@gmail.com', 'sadar', NULL, 1473, NULL, NULL, NULL, NULL, NULL, '5112558134', '{\"nid\":{\"path\":\"vendor-docs\\/2026\\/11\\/cb582f7a2fd824ba0e817269b3d9ffe0.pdf\",\"mime\":\"application\\/pdf\",\"name\":\"6460d0bc-349a-4d57-ba1b-94bd2f222af3.pdf\",\"size\":172374}}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'approved', NULL, NULL, 1, '2026-11-21 15:13:23', 0.00, 0, 0, '2026-11-21 15:12:41', '2026-11-21 15:13:23', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_payouts`
--

CREATE TABLE `vendor_payouts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `method` enum('bank','bkash','nagad','rocket','cash') NOT NULL DEFAULT 'bank',
  `account_details` varchar(255) DEFAULT NULL,
  `status` enum('requested','approved','paid','rejected') NOT NULL DEFAULT 'requested',
  `reference_no` varchar(100) DEFAULT NULL COMMENT 'bank/MFS transaction id',
  `vendor_note` varchar(255) DEFAULT NULL,
  `admin_note` varchar(255) DEFAULT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_types`
--

CREATE TABLE `vendor_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(40) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `name_bn` varchar(100) DEFAULT NULL,
  `description_en` varchar(255) DEFAULT NULL,
  `description_bn` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `required_docs` varchar(255) DEFAULT NULL COMMENT 'JSON list of document keys',
  `optional_docs` varchar(255) DEFAULT NULL COMMENT 'JSON list of document keys',
  `needs_drug_license` tinyint(1) NOT NULL DEFAULT 0,
  `can_sell_rx` tinyint(1) NOT NULL DEFAULT 0,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `allowed_categories` varchar(500) DEFAULT NULL COMMENT 'JSON list of category ids (children included); NULL = all',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_types`
--

INSERT INTO `vendor_types` (`id`, `slug`, `name_en`, `name_bn`, `description_en`, `description_bn`, `icon`, `required_docs`, `optional_docs`, `needs_drug_license`, `can_sell_rx`, `commission_rate`, `allowed_categories`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'pharmacy', 'Pharmacy', 'ফার্মেসি', 'Medicine shops and distributors (DGDA licence)', 'ওষুধের দোকান ও ডিস্ট্রিবিউটর (ড্রাগ লাইসেন্স)', 'bi-capsule', '[\"trade_license\",\"nid\",\"drug_license\"]', '[\"tin\"]', 1, 1, NULL, NULL, 10, 0, '2026-11-21 14:20:05', '2026-11-21 15:05:58'),
(2, 'medical_equipment', 'Medical equipment', 'মেডিকেল যন্ত্রপাতি', 'BP machines, glucometers, wheelchairs, first aid', 'প্রেশার মেশিন, গ্লুকোমিটার, হুইলচেয়ার, ফার্স্ট এইড', 'bi-heart-pulse', '[\"trade_license\",\"nid\"]', '[\"tin\"]', 0, 0, NULL, '[6]', 20, 0, '2026-11-21 14:20:05', '2026-11-21 15:06:01'),
(3, 'beauty', 'Beauty & personal care', 'বিউটি ও পার্সোনাল কেয়ার', 'Face wash, skin care, cosmetics, hair care', 'ফেসওয়াশ, স্কিন কেয়ার, কসমেটিকস, হেয়ার কেয়ার', 'bi-stars', '[\"trade_license\",\"nid\"]', '[\"bsti\",\"tin\"]', 0, 0, NULL, '[11,17]', 30, 0, '2026-11-21 14:20:05', '2026-11-21 15:06:02'),
(4, 'grocery', 'Grocery', 'গ্রোসারি', NULL, NULL, 'bi-basket', '[\"trade_license\",\"nid\"]', '[\"bsti\",\"tin\"]', 0, 0, NULL, '[10,11,16,19,20]', 40, 1, '2026-11-21 14:20:05', NULL),
(5, 'baby_care', 'Baby & mom care', 'বেবি ও মম কেয়ার', 'Diapers, baby care, mother care', 'ডায়াপার, শিশুর যত্ন, মায়ের যত্ন', 'bi-emoji-smile', '[\"trade_license\",\"nid\"]', '[\"bsti\"]', 0, 0, NULL, '[10,11]', 50, 1, '2026-11-21 14:20:05', NULL),
(6, 'herbal', 'Herbal / Homeopathy', 'হারবাল / হোমিওপ্যাথি', 'Unani, Ayurvedic and homeopathic medicine', 'ইউনানি, আয়ুর্বেদিক ও হোমিও ওষুধ', 'bi-flower1', '[\"trade_license\",\"nid\",\"drug_license\"]', '[\"tin\"]', 1, 0, NULL, '[4,5,16]', 60, 1, '2026-11-21 14:20:05', NULL),
(7, 'supplements', 'Supplements', 'সাপ্লিমেন্ট', 'Vitamins, protein, nutrition', 'ভিটামিন, প্রোটিন, পুষ্টি', 'bi-capsule-pill', '[\"trade_license\",\"nid\"]', '[\"bsti\",\"tin\"]', 0, 0, NULL, '[16,18]', 70, 1, '2026-11-21 14:20:05', NULL),
(8, 'other', 'Other', 'অন্যান্য', 'Any other health or lifestyle products', 'অন্যান্য স্বাস্থ্য ও লাইফস্টাইল পণ্য', 'bi-shop', '[\"nid\"]', '[\"trade_license\",\"tin\"]', 0, 0, NULL, NULL, 90, 1, '2026-11-21 14:20:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_wallets`
--

CREATE TABLE `vendor_wallets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'withdrawable',
  `pending_balance` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'earned, inside hold period',
  `total_earned` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_withdrawn` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_wallets`
--

INSERT INTO `vendor_wallets` (`id`, `vendor_id`, `balance`, `pending_balance`, `total_earned`, `total_withdrawn`, `created_at`, `updated_at`) VALUES
(81, 10, 0.00, 0.00, 0.00, 0.00, '2026-11-21 03:28:01', NULL),
(82, 11, 0.00, 0.00, 0.00, 0.00, '2026-11-21 03:28:01', NULL),
(171, 23, 0.00, 0.00, 0.00, 0.00, '2026-11-21 15:12:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_wallet_transactions`
--

CREATE TABLE `vendor_wallet_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `wallet_id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('order_earning','commission','refund','payout','adjustment','release') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_categories`
--
ALTER TABLE `account_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_account_categories_slug` (`slug`),
  ADD KEY `idx_account_categories_type` (`type`,`is_active`);

--
-- Indexes for table `account_entries`
--
ALTER TABLE `account_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_account_entries_no` (`entry_no`),
  ADD KEY `idx_account_entries_date` (`type`,`entry_date`),
  ADD KEY `idx_account_entries_cat` (`category_id`);

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_addresses_user` (`user_id`,`is_default`),
  ADD KEY `fk_addresses_division` (`division_id`),
  ADD KEY `fk_addresses_district` (`district_id`),
  ADD KEY `fk_addresses_upazila` (`upazila_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`,`created_at`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_action` (`action`,`created_at`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_banners_position` (`position`,`is_active`,`sort_order`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_blog_slug` (`slug`),
  ADD KEY `idx_blog_pub` (`status`,`published_at`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_brands_slug` (`slug`),
  ADD KEY `idx_brands_active` (`is_active`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_carts_user` (`user_id`),
  ADD KEY `idx_carts_session` (`session_id`),
  ADD KEY `idx_carts_product` (`product_id`),
  ADD KEY `fk_carts_variant` (`variant_id`),
  ADD KEY `idx_carts_session_product` (`session_id`,`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_slug` (`slug`),
  ADD KEY `idx_categories_parent` (`parent_id`,`sort_order`),
  ADD KEY `idx_categories_active` (`is_active`,`is_featured`);

--
-- Indexes for table `cod_settlements`
--
ALTER TABLE `cod_settlements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cod_settlements_courier` (`courier_id`,`received_at`);

--
-- Indexes for table `commissions`
--
ALTER TABLE `commissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_commissions_item` (`order_item_id`),
  ADD KEY `idx_commissions_vendor` (`vendor_id`,`status`),
  ADD KEY `idx_commissions_order` (`order_id`),
  ADD KEY `idx_commissions_created` (`created_at`),
  ADD KEY `fk_commissions_split` (`split_id`),
  ADD KEY `fk_commissions_rule` (`rule_id`);

--
-- Indexes for table `commission_rules`
--
ALTER TABLE `commission_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_comm_rules_lookup` (`scope`,`is_active`,`vendor_id`,`category_id`,`product_id`),
  ADD KEY `idx_comm_rules_vendor` (`vendor_id`),
  ADD KEY `idx_comm_rules_category` (`category_id`),
  ADD KEY `idx_comm_rules_product` (`product_id`),
  ADD KEY `fk_comm_rules_created_by` (`created_by`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_coupons_code` (`code`),
  ADD KEY `idx_coupons_active_dates` (`is_active`,`starts_at`,`ends_at`),
  ADD KEY `fk_coupons_vendor` (`vendor_id`),
  ADD KEY `fk_coupons_created_by` (`created_by`);

--
-- Indexes for table `coupon_usages`
--
ALTER TABLE `coupon_usages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_coupon_usage_order` (`coupon_id`,`order_id`),
  ADD KEY `idx_coupon_usage_user` (`coupon_id`,`user_id`),
  ADD KEY `fk_coupon_usage_user` (`user_id`),
  ADD KEY `fk_coupon_usage_order` (`order_id`);

--
-- Indexes for table `couriers`
--
ALTER TABLE `couriers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_couriers_code` (`code`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_faqs_cat` (`category`,`is_active`,`sort_order`);

--
-- Indexes for table `flash_sales`
--
ALTER TABLE `flash_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_flash_sales_dates` (`is_active`,`starts_at`,`ends_at`);

--
-- Indexes for table `flash_sale_items`
--
ALTER TABLE `flash_sale_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_flash_item` (`flash_sale_id`,`product_id`,`variant_id`),
  ADD KEY `idx_flash_items_product` (`product_id`),
  ADD KEY `fk_flash_items_variant` (`variant_id`);

--
-- Indexes for table `grns`
--
ALTER TABLE `grns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_grn_no` (`grn_no`),
  ADD KEY `idx_grn_po` (`purchase_order_id`),
  ADD KEY `idx_grn_supplier_date` (`supplier_id`,`received_date`),
  ADD KEY `fk_grn_received_by` (`received_by`);

--
-- Indexes for table `grn_items`
--
ALTER TABLE `grn_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grn_items_grn` (`grn_id`),
  ADD KEY `idx_grn_items_po_item` (`po_item_id`),
  ADD KEY `fk_grn_items_product` (`product_id`),
  ADD KEY `fk_grn_items_batch` (`batch_id`);

--
-- Indexes for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_batches_product_batch` (`product_id`,`variant_key`,`batch_no`),
  ADD KEY `idx_batches_product_expiry` (`product_id`,`variant_id`,`status`,`expiry_date`),
  ADD KEY `idx_batches_expiry` (`expiry_date`),
  ADD KEY `idx_batches_vendor` (`vendor_id`),
  ADD KEY `fk_batches_variant` (`variant_id`),
  ADD KEY `fk_batches_received_by` (`received_by`),
  ADD KEY `fk_batches_supplier` (`supplier_id`),
  ADD KEY `fk_batches_grn` (`grn_id`),
  ADD KEY `idx_batches_status_expiry` (`status`,`expiry_date`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_locations_parent` (`parent_id`),
  ADD KEY `idx_locations_type` (`type`,`is_active`),
  ADD KEY `idx_locations_zone` (`shipping_zone_id`);

--
-- Indexes for table `message_logs`
--
ALTER TABLE `message_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_msg_status` (`status`,`created_at`),
  ADD KEY `idx_msg_channel` (`channel`,`created_at`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_migrations_name` (`migration`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_unread` (`user_id`,`read_at`,`created_at`);

--
-- Indexes for table `offers`
--
ALTER TABLE `offers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_offers_slug` (`slug`),
  ADD KEY `idx_offers_active_dates` (`is_active`,`starts_at`,`ends_at`),
  ADD KEY `fk_offers_vendor` (`vendor_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_orders_order_no` (`order_no`),
  ADD KEY `idx_orders_user` (`user_id`,`placed_at`),
  ADD KEY `idx_orders_status` (`status`,`placed_at`),
  ADD KEY `idx_orders_payment` (`payment_status`,`payment_method`),
  ADD KEY `idx_orders_phone` (`customer_phone`),
  ADD KEY `idx_orders_placed` (`placed_at`),
  ADD KEY `fk_orders_division` (`division_id`),
  ADD KEY `fk_orders_district` (`district_id`),
  ADD KEY `fk_orders_upazila` (`upazila_id`),
  ADD KEY `fk_orders_zone` (`shipping_zone_id`),
  ADD KEY `fk_orders_rate` (`shipping_rate_id`),
  ADD KEY `fk_orders_coupon` (`coupon_id`),
  ADD KEY `fk_orders_prescription` (`prescription_id`),
  ADD KEY `fk_orders_created_by` (`created_by`),
  ADD KEY `idx_orders_guest_phone` (`is_guest`,`customer_phone`),
  ADD KEY `idx_orders_guest_email` (`is_guest`,`customer_email`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_split` (`split_id`),
  ADD KEY `idx_order_items_product` (`product_id`),
  ADD KEY `idx_order_items_vendor` (`vendor_id`),
  ADD KEY `fk_order_items_variant` (`variant_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_osh_order` (`order_id`,`created_at`),
  ADD KEY `idx_osh_split` (`split_id`),
  ADD KEY `fk_osh_changed_by` (`changed_by`),
  ADD KEY `idx_osh_order_id` (`order_id`,`id`);

--
-- Indexes for table `order_vendor_splits`
--
ALTER TABLE `order_vendor_splits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_splits_sub_order_no` (`sub_order_no`),
  ADD UNIQUE KEY `uq_splits_order_vendor` (`order_id`,`vendor_id`),
  ADD KEY `idx_splits_vendor_status` (`vendor_id`,`status`),
  ADD KEY `idx_splits_settlement` (`settlement_status`),
  ADD KEY `idx_splits_release` (`vendor_id`,`settlement_status`,`release_at`),
  ADD KEY `idx_splits_delivered` (`vendor_id`,`delivered_at`);

--
-- Indexes for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp_lookup` (`recipient`,`purpose`,`created_at`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pages_slug` (`slug`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payments_gateway_trx` (`gateway`,`transaction_id`),
  ADD KEY `idx_payments_order` (`order_id`),
  ADD KEY `idx_payments_status` (`status`,`created_at`),
  ADD KEY `idx_payments_ref` (`gateway_ref`),
  ADD KEY `fk_payments_user` (`user_id`),
  ADD KEY `fk_payments_received_by` (`received_by`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissions_key` (`key`),
  ADD KEY `idx_permissions_module` (`module`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prescriptions_user` (`user_id`),
  ADD KEY `idx_prescriptions_status` (`status`,`created_at`),
  ADD KEY `fk_prescriptions_reviewed_by` (`reviewed_by`),
  ADD KEY `idx_prescriptions_guest_phone` (`guest_phone`),
  ADD KEY `idx_prescriptions_request` (`request_type`,`request_status`),
  ADD KEY `idx_prescriptions_group` (`group_code`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_slug` (`slug`),
  ADD UNIQUE KEY `uq_products_vendor_sku` (`vendor_id`,`sku`),
  ADD KEY `idx_products_category` (`category_id`,`status`),
  ADD KEY `idx_products_brand` (`brand_id`),
  ADD KEY `idx_products_vendor_status` (`vendor_id`,`status`),
  ADD KEY `idx_products_status_featured` (`status`,`is_featured`),
  ADD KEY `idx_products_generic` (`generic_name`(100)),
  ADD KEY `idx_products_barcode` (`barcode`),
  ADD KEY `fk_products_created_by` (`created_by`),
  ADD KEY `idx_products_sku` (`sku`),
  ADD KEY `idx_products_deleted_updated` (`deleted_at`,`updated_at`);
ALTER TABLE `products` ADD FULLTEXT KEY `ft_products_search` (`name_en`,`name_bn`,`generic_name`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_images_product` (`product_id`,`sort_order`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_variants_sku` (`product_id`,`sku`),
  ADD KEY `idx_product_variants_product` (`product_id`,`is_active`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_po_no` (`po_no`),
  ADD KEY `idx_po_supplier_status` (`supplier_id`,`status`),
  ADD KEY `idx_po_status_date` (`status`,`order_date`),
  ADD KEY `fk_po_vendor` (`vendor_id`),
  ADD KEY `fk_po_created_by` (`created_by`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_poi_po` (`purchase_order_id`),
  ADD KEY `idx_poi_product` (`product_id`),
  ADD KEY `fk_poi_variant` (`variant_id`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`key`),
  ADD KEY `idx_rate_limits_reset` (`reset_at`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_refunds_no` (`refund_no`),
  ADD KEY `idx_refunds_order` (`order_id`),
  ADD KEY `idx_refunds_status` (`status`,`created_at`),
  ADD KEY `fk_refunds_split` (`split_id`),
  ADD KEY `fk_refunds_item` (`order_item_id`),
  ADD KEY `fk_refunds_payment` (`payment_id`),
  ADD KEY `fk_refunds_requested_by` (`requested_by`),
  ADD KEY `fk_refunds_processed_by` (`processed_by`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reviews_user_product_order` (`user_id`,`product_id`,`order_id`),
  ADD KEY `idx_reviews_product` (`product_id`,`status`),
  ADD KEY `idx_reviews_vendor` (`vendor_id`),
  ADD KEY `fk_reviews_order` (`order_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_slug` (`slug`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `idx_rp_permission` (`permission_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_settings_key` (`key`),
  ADD KEY `idx_settings_group` (`group`),
  ADD KEY `fk_settings_updated_by` (`updated_by`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shipments_order` (`order_id`),
  ADD KEY `idx_shipments_split` (`split_id`),
  ADD KEY `idx_shipments_tracking` (`tracking_no`),
  ADD KEY `idx_shipments_status` (`courier_id`,`status`),
  ADD KEY `fk_shipments_created_by` (`created_by`),
  ADD KEY `idx_shipments_cod` (`courier_id`,`cod_status`),
  ADD KEY `idx_shipments_consignment` (`consignment_id`);

--
-- Indexes for table `shipment_events`
--
ALTER TABLE `shipment_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shipment_events` (`shipment_id`,`id`);

--
-- Indexes for table `shipping_rates`
--
ALTER TABLE `shipping_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shipping_rates_zone` (`zone_id`,`is_active`);

--
-- Indexes for table `shipping_zones`
--
ALTER TABLE `shipping_zones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_mov_product` (`product_id`,`variant_id`,`created_at`),
  ADD KEY `idx_stock_mov_ref` (`reference_type`,`reference_id`),
  ADD KEY `idx_stock_mov_batch` (`batch_id`),
  ADD KEY `idx_stock_mov_vendor` (`vendor_id`,`created_at`),
  ADD KEY `fk_stock_mov_variant` (`variant_id`),
  ADD KEY `fk_stock_mov_created_by` (`created_by`),
  ADD KEY `idx_stock_mov_type_date` (`type`,`created_at`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_suppliers_name` (`name`),
  ADD KEY `idx_suppliers_active` (`is_active`),
  ADD KEY `fk_suppliers_created_by` (`created_by`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tickets_no` (`ticket_no`),
  ADD KEY `idx_tickets_user` (`user_id`),
  ADD KEY `idx_tickets_status` (`status`,`priority`),
  ADD KEY `idx_tickets_assigned` (`assigned_to`,`status`),
  ADD KEY `fk_tickets_vendor` (`vendor_id`),
  ADD KEY `fk_tickets_order` (`order_id`);

--
-- Indexes for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_replies_ticket` (`ticket_id`,`created_at`),
  ADD KEY `fk_ticket_replies_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_phone` (`phone`),
  ADD KEY `idx_users_type_status` (`user_type`,`status`),
  ADD KEY `idx_users_role` (`role_id`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vendors_user` (`user_id`),
  ADD UNIQUE KEY `uq_vendors_slug` (`slug`),
  ADD KEY `idx_vendors_status` (`status`),
  ADD KEY `fk_vendors_location` (`location_id`),
  ADD KEY `fk_vendors_approved_by` (`approved_by`),
  ADD KEY `idx_vendors_type` (`vendor_type_id`);

--
-- Indexes for table `vendor_payouts`
--
ALTER TABLE `vendor_payouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor_payouts_vendor` (`vendor_id`,`status`),
  ADD KEY `idx_vendor_payouts_status` (`status`),
  ADD KEY `fk_vendor_payouts_processed_by` (`processed_by`);

--
-- Indexes for table `vendor_types`
--
ALTER TABLE `vendor_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vendor_types_slug` (`slug`);

--
-- Indexes for table `vendor_wallets`
--
ALTER TABLE `vendor_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vendor_wallets_vendor` (`vendor_id`);

--
-- Indexes for table `vendor_wallet_transactions`
--
ALTER TABLE `vendor_wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vwt_vendor` (`vendor_id`,`created_at`),
  ADD KEY `idx_vwt_ref` (`reference_type`,`reference_id`),
  ADD KEY `fk_vwt_wallet` (`wallet_id`),
  ADD KEY `fk_vwt_created_by` (`created_by`),
  ADD KEY `idx_vwt_vendor_id` (`vendor_id`,`id`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wishlists_user_product` (`user_id`,`product_id`),
  ADD KEY `idx_wishlists_product` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_categories`
--
ALTER TABLE `account_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `account_entries`
--
ALTER TABLE `account_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=462;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cod_settlements`
--
ALTER TABLE `cod_settlements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `commissions`
--
ALTER TABLE `commissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `commission_rules`
--
ALTER TABLE `commission_rules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `coupon_usages`
--
ALTER TABLE `coupon_usages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `couriers`
--
ALTER TABLE `couriers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `flash_sales`
--
ALTER TABLE `flash_sales`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `flash_sale_items`
--
ALTER TABLE `flash_sale_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `grns`
--
ALTER TABLE `grns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `grn_items`
--
ALTER TABLE `grn_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1496;

--
-- AUTO_INCREMENT for table `message_logs`
--
ALTER TABLE `message_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `offers`
--
ALTER TABLE `offers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `order_vendor_splits`
--
ALTER TABLE `order_vendor_splits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `otp_codes`
--
ALTER TABLE `otp_codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1497;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `shipment_events`
--
ALTER TABLE `shipment_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `shipping_rates`
--
ALTER TABLE `shipping_rates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shipping_zones`
--
ALTER TABLE `shipping_zones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=175;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_payouts`
--
ALTER TABLE `vendor_payouts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_types`
--
ALTER TABLE `vendor_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `vendor_wallets`
--
ALTER TABLE `vendor_wallets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=183;

--
-- AUTO_INCREMENT for table `vendor_wallet_transactions`
--
ALTER TABLE `vendor_wallet_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_entries`
--
ALTER TABLE `account_entries`
  ADD CONSTRAINT `fk_account_entries_cat` FOREIGN KEY (`category_id`) REFERENCES `account_categories` (`id`);

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `fk_addresses_district` FOREIGN KEY (`district_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_addresses_division` FOREIGN KEY (`division_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_addresses_upazila` FOREIGN KEY (`upazila_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `fk_carts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_carts_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cod_settlements`
--
ALTER TABLE `cod_settlements`
  ADD CONSTRAINT `fk_cod_settlements_courier` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`id`);

--
-- Constraints for table `commissions`
--
ALTER TABLE `commissions`
  ADD CONSTRAINT `fk_commissions_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_commissions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_commissions_rule` FOREIGN KEY (`rule_id`) REFERENCES `commission_rules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_commissions_split` FOREIGN KEY (`split_id`) REFERENCES `order_vendor_splits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_commissions_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `commission_rules`
--
ALTER TABLE `commission_rules`
  ADD CONSTRAINT `fk_comm_rules_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comm_rules_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_comm_rules_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comm_rules_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coupons`
--
ALTER TABLE `coupons`
  ADD CONSTRAINT `fk_coupons_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_coupons_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coupon_usages`
--
ALTER TABLE `coupon_usages`
  ADD CONSTRAINT `fk_coupon_usage_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_coupon_usage_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_coupon_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `flash_sale_items`
--
ALTER TABLE `flash_sale_items`
  ADD CONSTRAINT `fk_flash_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_flash_items_sale` FOREIGN KEY (`flash_sale_id`) REFERENCES `flash_sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_flash_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `grns`
--
ALTER TABLE `grns`
  ADD CONSTRAINT `fk_grn_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `fk_grn_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_grn_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `grn_items`
--
ALTER TABLE `grn_items`
  ADD CONSTRAINT `fk_grn_items_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_grn_items_grn` FOREIGN KEY (`grn_id`) REFERENCES `grns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_grn_items_po_item` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`id`),
  ADD CONSTRAINT `fk_grn_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD CONSTRAINT `fk_batches_grn` FOREIGN KEY (`grn_id`) REFERENCES `grns` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_batches_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_batches_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_batches_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_batches_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `locations`
--
ALTER TABLE `locations`
  ADD CONSTRAINT `fk_locations_parent` FOREIGN KEY (`parent_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_locations_zone` FOREIGN KEY (`shipping_zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `offers`
--
ALTER TABLE `offers`
  ADD CONSTRAINT `fk_offers_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_district` FOREIGN KEY (`district_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_division` FOREIGN KEY (`division_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_rate` FOREIGN KEY (`shipping_rate_id`) REFERENCES `shipping_rates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_upazila` FOREIGN KEY (`upazila_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_zone` FOREIGN KEY (`shipping_zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_order_items_split` FOREIGN KEY (`split_id`) REFERENCES `order_vendor_splits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_order_items_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `fk_osh_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_osh_split` FOREIGN KEY (`split_id`) REFERENCES `order_vendor_splits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_vendor_splits`
--
ALTER TABLE `order_vendor_splits`
  ADD CONSTRAINT `fk_splits_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_splits_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_prescriptions_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_prescriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`);

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `fk_product_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `fk_po_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `fk_poi_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_poi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_poi_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`);

--
-- Constraints for table `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `fk_refunds_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_refunds_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_refunds_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_refunds_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_refunds_split` FOREIGN KEY (`split_id`) REFERENCES `order_vendor_splits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `settings`
--
ALTER TABLE `settings`
  ADD CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shipments`
--
ALTER TABLE `shipments`
  ADD CONSTRAINT `fk_shipments_courier` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_shipments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_shipments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shipments_split` FOREIGN KEY (`split_id`) REFERENCES `order_vendor_splits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shipment_events`
--
ALTER TABLE `shipment_events`
  ADD CONSTRAINT `fk_shipment_events_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shipping_rates`
--
ALTER TABLE `shipping_rates`
  ADD CONSTRAINT `fk_shipping_rates_zone` FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_mov_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stock_mov_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stock_mov_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_mov_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stock_mov_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `fk_suppliers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `fk_tickets_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tickets_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tickets_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  ADD CONSTRAINT `fk_ticket_replies_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ticket_replies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vendors`
--
ALTER TABLE `vendors`
  ADD CONSTRAINT `fk_vendors_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vendors_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vendors_type` FOREIGN KEY (`vendor_type_id`) REFERENCES `vendor_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vendors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `vendor_payouts`
--
ALTER TABLE `vendor_payouts`
  ADD CONSTRAINT `fk_vendor_payouts_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vendor_payouts_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`);

--
-- Constraints for table `vendor_wallets`
--
ALTER TABLE `vendor_wallets`
  ADD CONSTRAINT `fk_vendor_wallets_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_wallet_transactions`
--
ALTER TABLE `vendor_wallet_transactions`
  ADD CONSTRAINT `fk_vwt_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vwt_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vwt_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `vendor_wallets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD CONSTRAINT `fk_wishlists_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wishlists_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
