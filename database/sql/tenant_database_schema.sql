-- Tenant Database Schema
-- This file creates all default tables for a new tenant
-- Placeholders: {DATABASE_NAME} and {TENANT_ID} will be replaced dynamically

-- Note: This is a placeholder file. 
-- Please replace this content with your actual SQL schema when ready.
-- The SQL file should contain CREATE TABLE statements for all tenant-specific tables.

-- Example structure:
-- CREATE TABLE IF NOT EXISTS `users` (
--     `id` CHAR(36) PRIMARY KEY,
--     `email` VARCHAR(255) UNIQUE NOT NULL,
--     ...
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TODO: Replace this file with your actual tenant database schema SQL

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'create, update, delete, login, logout, approve, reject, etc.',
  `module` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'loans, members, contributions, etc.',
  `resource_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'App\\Models\\Tenant\\Loan, etc.',
  `resource_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID of the affected resource',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` json DEFAULT NULL COMMENT 'Previous values (for updates)',
  `new_values` json DEFAULT NULL COMMENT 'New values (for updates)',
  `metadata` json DEFAULT NULL COMMENT 'Additional data',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_index` (`user_id`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_module_index` (`module`),
  KEY `audit_logs_resource_type_index` (`resource_type`),
  KEY `audit_logs_resource_id_index` (`resource_id`),
  KEY `audit_logs_created_at_index` (`created_at`),
  KEY `audit_logs_resource_type_resource_id_index` (`resource_type`,`resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

-- --------------------------------------------------------

--
-- Table structure for table `blockchain_property_records`
--

DROP TABLE IF EXISTS `blockchain_property_records`;
CREATE TABLE IF NOT EXISTS `blockchain_property_records` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `blockchain_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','confirmed','failed','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `property_data` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON snapshot of property at registration time',
  `ownership_data` json DEFAULT NULL COMMENT 'Array of owners with their wallet addresses',
  `network` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'ethereum' COMMENT 'blockchain network',
  `contract_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'smart contract address',
  `token_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NFT token ID if using NFT standard',
  `gas_fee` decimal(15,8) DEFAULT NULL COMMENT 'transaction gas fee',
  `gas_price` decimal(15,8) DEFAULT NULL COMMENT 'gas price in wei',
  `block_number` int DEFAULT NULL COMMENT 'block number where transaction was mined',
  `registered_at` timestamp NULL DEFAULT NULL COMMENT 'when registration was initiated',
  `confirmed_at` timestamp NULL DEFAULT NULL COMMENT 'when blockchain confirmed',
  `failed_at` timestamp NULL DEFAULT NULL COMMENT 'when transaction failed',
  `failure_reason` text COLLATE utf8mb4_unicode_ci,
  `verification_notes` text COLLATE utf8mb4_unicode_ci,
  `registered_by` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'admin user who initiated registration',
  `verified_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'admin user who verified',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blockchain_hash` (`blockchain_hash`),
  KEY `idx_property_id` (`property_id`),
  KEY `idx_blockchain_hash` (`blockchain_hash`),
  KEY `idx_transaction_hash` (`transaction_hash`),
  KEY `idx_status` (`status`),
  KEY `idx_network` (`network`),
  KEY `idx_block_number` (`block_number`),
  KEY `fk_blockchain_property_records_registered_by` (`registered_by`),
  KEY `fk_blockchain_property_records_verified_by` (`verified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blockchain_settings`
--

DROP TABLE IF EXISTS `blockchain_settings`;
CREATE TABLE IF NOT EXISTS `blockchain_settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `primary_network` enum('ethereum','polygon','bsc','arbitrum','optimism') COLLATE utf8mb4_unicode_ci DEFAULT 'ethereum',
  `is_enabled` tinyint(1) DEFAULT '0',
  `setup_completed` tinyint(1) DEFAULT '0',
  `ethereum_rpc_url` text COLLATE utf8mb4_unicode_ci,
  `polygon_rpc_url` text COLLATE utf8mb4_unicode_ci,
  `bsc_rpc_url` text COLLATE utf8mb4_unicode_ci,
  `arbitrum_rpc_url` text COLLATE utf8mb4_unicode_ci,
  `optimism_rpc_url` text COLLATE utf8mb4_unicode_ci,
  `etherscan_api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `polygonscan_api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bscscan_api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arbiscan_api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `optimistic_etherscan_api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ethereum_contract_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `polygon_contract_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bsc_contract_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arbitrum_contract_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `optimism_contract_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `webhooks_enabled` tinyint(1) DEFAULT '0',
  `webhook_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `webhook_url` text COLLATE utf8mb4_unicode_ci,
  `gas_price_multiplier` decimal(5,2) DEFAULT '1.20' COMMENT '20% buffer',
  `default_gas_limit` int DEFAULT '100000',
  `setup_completed_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setup_completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_blockchain_settings_setup_completed_by` (`setup_completed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blockchain_settings`
--

-- --------------------------------------------------------

--
-- Table structure for table `blockchain_transactions`
--

DROP TABLE IF EXISTS `blockchain_transactions`;
CREATE TABLE IF NOT EXISTS `blockchain_transactions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('contribution','loan','investment','payment','transfer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `status` enum('pending','confirmed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `metadata` json DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blockchain_transactions_hash_unique` (`hash`),
  KEY `blockchain_transactions_hash_index` (`hash`),
  KEY `blockchain_transactions_reference_index` (`reference`),
  KEY `blockchain_transactions_type_index` (`type`),
  KEY `blockchain_transactions_status_index` (`status`),
  KEY `blockchain_transactions_user_id_index` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blockchain_wallets`
--

DROP TABLE IF EXISTS `blockchain_wallets`;
CREATE TABLE IF NOT EXISTS `blockchain_wallets` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Wallet name/identifier',
  `network` enum('ethereum','polygon','bsc','arbitrum','optimism') COLLATE utf8mb4_unicode_ci DEFAULT 'ethereum',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Wallet address (public key)',
  `private_key_encrypted` text COLLATE utf8mb4_unicode_ci COMMENT 'Encrypted private key (use encryption)',
  `mnemonic_encrypted` text COLLATE utf8mb4_unicode_ci COMMENT 'Encrypted mnemonic phrase',
  `balance` decimal(30,18) DEFAULT '0.000000000000000000' COMMENT 'Native token balance (ETH, MATIC, etc.)',
  `is_active` tinyint(1) DEFAULT '1',
  `is_default` tinyint(1) DEFAULT '0' COMMENT 'Default wallet for network',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_network_address` (`network`,`address`),
  KEY `fk_blockchain_wallets_created_by` (`created_by`),
  KEY `idx_network` (`network`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--
-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contributions`
--

DROP TABLE IF EXISTS `contributions`;
CREATE TABLE IF NOT EXISTS `contributions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `type` enum('monthly','quarterly','annual','special','emergency') COLLATE utf8mb4_unicode_ci NOT NULL,
  `frequency` enum('monthly','quarterly','annually','one_time') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `contribution_date` date NOT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejected_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contributions_member_id_index` (`member_id`),
  KEY `contributions_status_index` (`status`),
  KEY `contributions_type_index` (`type`),
  KEY `contributions_plan_id_index` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contributions`
--

-- --------------------------------------------------------

--
-- Table structure for table `contribution_auto_pay_settings`
--

DROP TABLE IF EXISTS `contribution_auto_pay_settings`;
CREATE TABLE IF NOT EXISTS `contribution_auto_pay_settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'wallet',
  `amount` decimal(15,2) DEFAULT NULL,
  `day_of_month` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `metadata` json DEFAULT NULL,
  `card_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_run_at` timestamp NULL DEFAULT NULL,
  `next_run_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contribution_auto_pay_settings`
--
-- --------------------------------------------------------

--
-- Table structure for table `contribution_payments`
--

DROP TABLE IF EXISTS `contribution_payments`;
CREATE TABLE IF NOT EXISTS `contribution_payments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contribution_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contribution_payments_contribution_id_index` (`contribution_id`),
  KEY `contribution_payments_payment_date_index` (`payment_date`),
  KEY `contribution_payments_status_index` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contribution_payments`
-- --------------------------------------------------------

--
-- Table structure for table `contribution_plans`
--

DROP TABLE IF EXISTS `contribution_plans`;
CREATE TABLE IF NOT EXISTS `contribution_plans` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `amount` decimal(15,2) NOT NULL,
  `minimum_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `frequency` enum('monthly','quarterly','annually','one_time') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contribution_plans`
--
-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
CREATE TABLE IF NOT EXISTS `documents` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `file_path` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL,
  `mime_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejected_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documents_member_id_index` (`member_id`),
  KEY `documents_type_index` (`type`),
  KEY `documents_status_index` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equity_contributions`
--

DROP TABLE IF EXISTS `equity_contributions`;
CREATE TABLE IF NOT EXISTS `equity_contributions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('paystack','remita','stripe','manual','bank_transfer','wallet') COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `status` enum('pending','approved','rejected','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `payment_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `approved_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_equity_contributions_approved_by` (`approved_by`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equity_contributions`
--
-- --------------------------------------------------------

--
-- Table structure for table `equity_plans`
--

DROP TABLE IF EXISTS `equity_plans`;
CREATE TABLE IF NOT EXISTS `equity_plans` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `min_amount` decimal(15,2) DEFAULT '0.00',
  `max_amount` decimal(15,2) DEFAULT NULL,
  `frequency` enum('monthly','quarterly','annually','one_time','custom') COLLATE utf8mb4_unicode_ci DEFAULT 'monthly',
  `is_mandatory` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equity_plans`
--
-- --------------------------------------------------------

--
-- Table structure for table `equity_transactions`
--

DROP TABLE IF EXISTS `equity_transactions`;
CREATE TABLE IF NOT EXISTS `equity_transactions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `equity_wallet_balance_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('contribution','deposit_payment','refund','adjustment') COLLATE utf8mb4_unicode_ci DEFAULT 'contribution',
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) DEFAULT '0.00',
  `balance_after` decimal(15,2) DEFAULT '0.00',
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Can be property_id, contribution_id, etc.',
  `reference_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'property, contribution, etc.',
  `description` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_equity_wallet_balance_id` (`equity_wallet_balance_id`),
  KEY `idx_type` (`type`),
  KEY `idx_reference` (`reference`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equity_transactions`
-- --------------------------------------------------------

--
-- Table structure for table `equity_wallet_balances`
--

DROP TABLE IF EXISTS `equity_wallet_balances`;
CREATE TABLE IF NOT EXISTS `equity_wallet_balances` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` decimal(15,2) DEFAULT '0.00',
  `total_contributed` decimal(15,2) DEFAULT '0.00',
  `total_used` decimal(15,2) DEFAULT '0.00',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'NGN',
  `is_active` tinyint(1) DEFAULT '1',
  `last_updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_id` (`member_id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equity_wallet_balances`
-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `internal_mortgage_plans`
--

DROP TABLE IF EXISTS `internal_mortgage_plans`;
CREATE TABLE IF NOT EXISTS `internal_mortgage_plans` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `configured_by` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `principal` decimal(20,2) NOT NULL,
  `interest_rate` decimal(8,4) NOT NULL,
  `tenure_months` int NOT NULL,
  `monthly_payment` decimal(20,2) DEFAULT NULL,
  `frequency` enum('monthly','quarterly','biannually','annually') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `status` enum('draft','active','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `schedule_approved` tinyint(1) NOT NULL DEFAULT '0',
  `schedule_approved_at` timestamp NULL DEFAULT NULL,
  `starts_on` timestamp NULL DEFAULT NULL,
  `ends_on` timestamp NULL DEFAULT NULL,
  `schedule` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_property_member` (`property_id`,`member_id`),
  KEY `idx_configured_by` (`configured_by`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `internal_mortgage_plans`
--
-- --------------------------------------------------------

--
-- Table structure for table `internal_mortgage_repayments`
--

DROP TABLE IF EXISTS `internal_mortgage_repayments`;
CREATE TABLE IF NOT EXISTS `internal_mortgage_repayments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `internal_mortgage_plan_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Link to property if plan is tied to property',
  `amount` decimal(15,2) NOT NULL COMMENT 'Total payment amount',
  `principal_paid` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Principal portion',
  `interest_paid` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Interest portion',
  `due_date` date NOT NULL,
  `status` enum('pending','paid','overdue','partial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'monthly, yearly, bi-yearly, etc.',
  `frequency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'monthly, quarterly, biannually, annually',
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Admin who recorded the payment',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_internal_mortgage_repayments_property_id` (`property_id`),
  KEY `idx_internal_mortgage_plan_id` (`internal_mortgage_plan_id`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

DROP TABLE IF EXISTS `investments`;
CREATE TABLE IF NOT EXISTS `investments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `type` enum('savings','fixed_deposit','treasury_bills','bonds','stocks') COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_months` int NOT NULL,
  `expected_return_rate` decimal(5,2) NOT NULL,
  `status` enum('pending','active','rejected','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `investment_date` date NOT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejected_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investments_member_id_index` (`member_id`),
  KEY `investments_status_index` (`status`),
  KEY `investments_type_index` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `investment_plans`
--

DROP TABLE IF EXISTS `investment_plans`;
CREATE TABLE IF NOT EXISTS `investment_plans` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `min_amount` decimal(15,2) NOT NULL,
  `max_amount` decimal(15,2) NOT NULL,
  `expected_return_rate` decimal(5,2) NOT NULL,
  `min_duration_months` int NOT NULL,
  `max_duration_months` int NOT NULL,
  `return_type` enum('fixed','variable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `risk_level` enum('low','medium','high') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `features` json DEFAULT NULL,
  `terms_and_conditions` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_plans_is_active_index` (`is_active`),
  KEY `investment_plans_risk_level_index` (`risk_level`),
  KEY `investment_plans_min_amount_index` (`min_amount`),
  KEY `investment_plans_max_amount_index` (`max_amount`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `investment_returns`
--

DROP TABLE IF EXISTS `investment_returns`;
CREATE TABLE IF NOT EXISTS `investment_returns` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `investment_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `return_date` date NOT NULL,
  `status` enum('pending','paid','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_returns_investment_id_index` (`investment_id`),
  KEY `investment_returns_return_date_index` (`return_date`),
  KEY `investment_returns_status_index` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `landing_page_configs`
--

DROP TABLE IF EXISTS `landing_page_configs`;
CREATE TABLE IF NOT EXISTS `landing_page_configs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `sections` json DEFAULT NULL,
  `theme` json DEFAULT NULL,
  `seo` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `landing_page_configs_tenant_id_index` (`tenant_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `landing_page_configs`
--
-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

DROP TABLE IF EXISTS `loans`;
CREATE TABLE IF NOT EXISTS `loans` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `duration_months` int NOT NULL,
  `type` enum('personal','housing','business','emergency') COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected','disbursed','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `application_date` date NOT NULL,
  `monthly_payment` decimal(15,2) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `interest_amount` decimal(15,2) DEFAULT NULL,
  `processing_fee` decimal(15,2) DEFAULT NULL,
  `required_documents` json DEFAULT NULL,
  `application_metadata` json DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejected_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disbursed_at` timestamp NULL DEFAULT NULL,
  `disbursed_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loans_member_id_index` (`member_id`),
  KEY `loans_status_index` (`status`),
  KEY `loans_type_index` (`type`),
  KEY `loans_product_id_index` (`product_id`),
  KEY `idx_loans_property_id` (`property_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loans`
--
-- --------------------------------------------------------

--
-- Table structure for table `loan_products`
--

DROP TABLE IF EXISTS `loan_products`;
CREATE TABLE IF NOT EXISTS `loan_products` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `min_amount` decimal(15,2) NOT NULL,
  `max_amount` decimal(15,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `min_tenure_months` int NOT NULL,
  `max_tenure_months` int NOT NULL,
  `interest_type` enum('simple','compound') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'simple',
  `eligibility_criteria` json DEFAULT NULL,
  `required_documents` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `processing_fee_percentage` int NOT NULL DEFAULT '0',
  `late_payment_fee` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_products_is_active_index` (`is_active`),
  KEY `loan_products_min_amount_index` (`min_amount`),
  KEY `loan_products_max_amount_index` (`max_amount`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_products`
--
-- --------------------------------------------------------

--
-- Table structure for table `loan_repayments`
--

DROP TABLE IF EXISTS `loan_repayments`;
CREATE TABLE IF NOT EXISTS `loan_repayments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `loan_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `principal_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `interest_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `due_date` date NOT NULL,
  `status` enum('pending','paid','overdue') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_repayments_loan_id_index` (`loan_id`),
  KEY `loan_repayments_due_date_index` (`due_date`),
  KEY `loan_repayments_status_index` (`status`),
  KEY `idx_property_id` (`property_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_repayments`
--
-- --------------------------------------------------------

--
-- Table structure for table `mails`
--

DROP TABLE IF EXISTS `mails`;
CREATE TABLE IF NOT EXISTS `mails` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_type` enum('all','active','specific','group') COLLATE utf8mb4_unicode_ci DEFAULT 'specific',
  `subject` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `cc` json DEFAULT NULL,
  `bcc` json DEFAULT NULL,
  `type` enum('internal','system','notification') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal',
  `status` enum('draft','sent','delivered','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `folder` enum('inbox','sent','drafts','trash') COLLATE utf8mb4_unicode_ci DEFAULT 'inbox',
  `category` enum('general','investment','contribution','loan','property') COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `is_starred` tinyint(1) DEFAULT '0',
  `is_archived` tinyint(1) DEFAULT '0',
  `is_read` tinyint(1) DEFAULT '0',
  `is_urgent` tinyint(1) DEFAULT '0',
  `sent_at` timestamp NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text COLLATE utf8mb4_unicode_ci,
  `parent_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mails_sender_id_index` (`sender_id`),
  KEY `mails_recipient_id_index` (`recipient_id`),
  KEY `mails_type_index` (`type`),
  KEY `mails_status_index` (`status`),
  KEY `mails_parent_id_index` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mail_attachments`
--

DROP TABLE IF EXISTS `mail_attachments`;
CREATE TABLE IF NOT EXISTS `mail_attachments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint UNSIGNED NOT NULL COMMENT 'in bytes',
  `order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mail_id` (`mail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mail_recipients`
--

DROP TABLE IF EXISTS `mail_recipients`;
CREATE TABLE IF NOT EXISTS `mail_recipients` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('to','cc','bcc') COLLATE utf8mb4_unicode_ci DEFAULT 'to',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'For cases where recipient is not a user',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','delivered','failed','read') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mail_id` (`mail_id`),
  KEY `idx_recipient_id` (`recipient_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
CREATE TABLE IF NOT EXISTS `members` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contribution_plan_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `member_number` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `staff_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ippis_number` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nigerian',
  `state_of_origin` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lga` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `residential_address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rank` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `command_state` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employment_date` date DEFAULT NULL,
  `years_of_service` int DEFAULT NULL,
  `membership_type` enum('regular','premium','vip','non-member') COLLATE utf8mb4_unicode_ci DEFAULT 'regular',
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `activated_at` timestamp NULL DEFAULT NULL,
  `activated_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deactivated_at` timestamp NULL DEFAULT NULL,
  `deactivated_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `suspended_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suspension_reason` text COLLATE utf8mb4_unicode_ci,
  `kyc_status` enum('pending','submitted','verified','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `kyc_submitted_at` timestamp NULL DEFAULT NULL,
  `kyc_verified_at` timestamp NULL DEFAULT NULL,
  `kyc_rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `kyc_documents` json DEFAULT NULL,
  `next_of_kin_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_of_kin_relationship` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_of_kin_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_of_kin_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_of_kin_address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `members_member_number_unique` (`member_number`),
  KEY `members_user_id_index` (`user_id`),
  KEY `members_member_number_index` (`member_number`),
  KEY `members_staff_id_index` (`staff_id`),
  KEY `members_kyc_status_index` (`kyc_status`),
  KEY `members_membership_type_index` (`membership_type`),
  KEY `members_status_index` (`status`),
  KEY `members_activated_by_index` (`activated_by`),
  KEY `members_deactivated_by_index` (`deactivated_by`),
  KEY `members_suspended_by_index` (`suspended_by`),
  KEY `members_contribution_plan_id_foreign` (`contribution_plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2024_01_01_100000_create_cache_table', 1),
(2, '2024_01_01_100001_create_jobs_table', 1),
(3, '2024_01_01_100001_create_users_table', 1),
(4, '2024_01_01_100002_create_members_table', 1),
(5, '2024_01_01_100003_create_landing_page_configs_table', 1),
(6, '2024_01_01_100004_create_wallets_table', 1),
(7, '2024_01_01_100005_create_wallet_transactions_table', 1),
(8, '2024_01_01_100006_create_properties_table', 1),
(9, '2024_01_01_100007_create_property_images_table', 1),
(10, '2024_01_01_100008_create_property_allocations_table', 1),
(11, '2024_01_01_100009_create_payments_table', 1),
(12, '2024_01_01_100010_create_payment_gateways_table', 1),
(13, '2024_01_01_100011_create_loans_table', 1),
(14, '2024_01_01_100012_create_investments_table', 1),
(15, '2024_01_01_100013_create_contributions_table', 1),
(16, '2024_01_01_100014_create_white_label_settings_table', 1),
(17, '2024_01_01_100015_create_mails_table', 1),
(18, '2024_01_01_100016_create_notifications_table', 1),
(19, '2024_01_01_100017_create_documents_table', 1),
(20, '2024_01_01_100018_create_onboarding_steps_table', 1),
(21, '2024_01_01_100019_create_statutory_charges_table', 1),
(22, '2024_01_01_100020_create_statutory_charge_payments_table', 1),
(23, '2024_01_01_100021_create_loan_repayments_table', 1),
(24, '2024_01_01_100022_create_investment_returns_table', 1),
(25, '2024_01_01_100023_create_contribution_payments_table', 1),
(26, '2024_01_01_100024_create_loan_products_table', 1),
(27, '2024_01_01_100025_create_investment_plans_table', 1),
(28, '2024_01_01_100026_create_blockchain_transactions_table', 1),
(29, '2024_01_01_100027_create_otp_verifications_table', 1),
(30, '2024_01_01_100028_create_property_interests_table', 1),
(31, '2024_01_01_100029_create_sessions_table', 1),
(32, '2024_01_01_100030_create_password_reset_tokens_table', 1),
(33, '2024_01_01_100031_create_personal_access_tokens_table', 1),
(34, '2025_10_20_141025_create_roles_and_permissions_tables', 1);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
CREATE TABLE IF NOT EXISTS `model_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
CREATE TABLE IF NOT EXISTS `model_has_roles` (
  `role_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `model_has_roles`
--
-- --------------------------------------------------------

--
-- Table structure for table `mortgages`
--

DROP TABLE IF EXISTS `mortgages`;
CREATE TABLE IF NOT EXISTS `mortgages` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loan_amount` decimal(15,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `tenure_years` int NOT NULL,
  `monthly_payment` decimal(15,2) NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `schedule_approved` tinyint(1) NOT NULL DEFAULT '0',
  `schedule_approved_at` timestamp NULL DEFAULT NULL,
  `application_date` date NOT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejected_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mortgages_member_id_index` (`member_id`),
  KEY `mortgages_provider_id_index` (`provider_id`),
  KEY `mortgages_property_id_index` (`property_id`),
  KEY `mortgages_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mortgages`
--
-- --------------------------------------------------------

--
-- Table structure for table `mortgage_providers`
--

DROP TABLE IF EXISTS `mortgage_providers`;
CREATE TABLE IF NOT EXISTS `mortgage_providers` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `interest_rate_min` decimal(5,2) DEFAULT NULL,
  `interest_rate_max` decimal(5,2) DEFAULT NULL,
  `min_loan_amount` decimal(15,2) DEFAULT NULL,
  `max_loan_amount` decimal(15,2) DEFAULT NULL,
  `min_tenure_years` int DEFAULT NULL,
  `max_tenure_years` int DEFAULT NULL,
  `requirements` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mortgage_providers_is_active_index` (`is_active`),
  KEY `mortgage_providers_name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mortgage_providers`
--

--
-- Table structure for table `mortgage_repayments`
--

DROP TABLE IF EXISTS `mortgage_repayments`;
CREATE TABLE IF NOT EXISTS `mortgage_repayments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mortgage_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Link to property if mortgage is tied to property',
  `amount` decimal(15,2) NOT NULL COMMENT 'Total payment amount',
  `principal_paid` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Principal portion',
  `interest_paid` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Interest portion',
  `due_date` date NOT NULL,
  `status` enum('pending','paid','overdue','partial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'monthly, yearly, bi-yearly, etc.',
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Admin who recorded the payment',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_mortgage_repayments_property_id` (`property_id`),
  KEY `idx_mortgage_id` (`mortgage_id`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mortgage_repayments`
--
-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('info','success','warning','error','system') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_index` (`user_id`),
  KEY `notifications_type_index` (`type`),
  KEY `notifications_read_at_index` (`read_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--
-- --------------------------------------------------------

--
-- Table structure for table `onboarding_steps`
--

DROP TABLE IF EXISTS `onboarding_steps`;
CREATE TABLE IF NOT EXISTS `onboarding_steps` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `step_number` int NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','completed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `skipped_at` timestamp NULL DEFAULT NULL,
  `skip_reason` text COLLATE utf8mb4_unicode_ci,
  `data` json DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `onboarding_steps_member_id_index` (`member_id`),
  KEY `onboarding_steps_step_number_index` (`step_number`),
  KEY `onboarding_steps_status_index` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

DROP TABLE IF EXISTS `otp_verifications`;
CREATE TABLE IF NOT EXISTS `otp_verifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('registration','password_reset','email_verification') COLLATE utf8mb4_unicode_ci DEFAULT 'registration',
  `otp` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `attempts` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `otp_verifications_email_index` (`email`),
  KEY `otp_verifications_expires_at_index` (`expires_at`),
  KEY `otp_verifications_type_index` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_verifications`
--
-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `payment_method` enum('paystack','remita','stripe','wallet','bank_transfer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','completed','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approval_status` enum('pending','approved','rejected','auto_approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approval_notes` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `gateway_reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payer_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_details` text COLLATE utf8mb4_unicode_ci,
  `payment_date` timestamp NULL DEFAULT NULL,
  `payment_evidence` json DEFAULT NULL,
  `gateway_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gateway_response` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_reference_unique` (`reference`),
  KEY `payments_user_id_index` (`user_id`),
  KEY `payments_reference_index` (`reference`),
  KEY `payments_status_index` (`status`),
  KEY `payments_payment_method_index` (`payment_method`),
  KEY `payments_approval_status_index` (`approval_status`),
  KEY `payments_approved_by_index` (`approved_by`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
-- --------------------------------------------------------

--
-- Table structure for table `payment_gateways`
--

DROP TABLE IF EXISTS `payment_gateways`;
CREATE TABLE IF NOT EXISTS `payment_gateways` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_type` enum('paystack','remita','stripe','manual') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `is_test_mode` tinyint(1) NOT NULL DEFAULT '1',
  `credentials` json NOT NULL,
  `configuration` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_gateways_tenant_id_gateway_type_unique` (`tenant_id`,`gateway_type`),
  KEY `payment_gateways_tenant_id_index` (`tenant_id`),
  KEY `payment_gateways_gateway_type_index` (`gateway_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_gateways`
-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(125) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(125) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `group` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`),
  KEY `permissions_group_index` (`group`),
  KEY `permissions_is_active_index` (`is_active`),
  KEY `permissions_sort_order_index` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `description`, `group`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'view_members', 'web', 'View member information', 'members', 1, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(2, 'create_members', 'web', 'Create new members', 'members', 1, 2, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(3, 'edit_members', 'web', 'Edit member information', 'members', 1, 3, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(4, 'delete_members', 'web', 'Delete members', 'members', 1, 4, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(5, 'bulk_upload_members', 'web', 'Bulk upload members', 'members', 1, 5, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(6, 'manage_member_kyc', 'web', 'Manage member KYC status', 'members', 1, 6, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(7, 'view_contributions', 'web', 'View contributions', 'financial', 1, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(8, 'create_contributions', 'web', 'Create contributions', 'financial', 1, 2, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(9, 'edit_contributions', 'web', 'Edit contributions', 'financial', 1, 3, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(10, 'delete_contributions', 'web', 'Delete contributions', 'financial', 1, 4, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(11, 'bulk_upload_contributions', 'web', 'Bulk upload contributions', 'financial', 1, 5, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(12, 'view_wallets', 'web', 'View wallet information', 'financial', 1, 6, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(13, 'manage_wallets', 'web', 'Manage wallet transactions', 'financial', 1, 7, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(14, 'view_wallet_transactions', 'web', 'View wallet transactions', 'financial', 1, 8, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(15, 'view_pending_wallets', 'web', 'View pending wallet transactions', 'financial', 1, 9, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(16, 'view_financial_reports', 'web', 'View financial reports', 'financial', 1, 10, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(17, 'view_contribution_reports', 'web', 'View contribution reports', 'financial', 1, 11, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(18, 'manage_statutory_charges', 'web', 'Manage statutory charges', 'financial', 1, 12, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(19, 'manage_statutory_charge_types', 'web', 'Manage statutory charge types', 'statutory_charges', 1, 7, '2025-10-29 22:18:02', '2025-11-05 10:22:39'),
(20, 'manage_statutory_charge_payments', 'web', 'Manage statutory charge payments', 'financial', 1, 14, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(21, 'manage_statutory_charge_departments', 'web', 'Manage statutory charge departments', 'statutory_charges', 1, 8, '2025-10-29 22:18:02', '2025-11-05 10:22:39'),
(22, 'view_loans', 'web', 'View loan applications', 'loans', 1, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(23, 'create_loans', 'web', 'Create loan applications', 'loans', 1, 2, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(24, 'edit_loans', 'web', 'Edit loan applications', 'loans', 1, 3, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(25, 'approve_loans', 'web', 'Approve loan applications', 'loans', 1, 4, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(26, 'reject_loans', 'web', 'Reject loan applications', 'loans', 1, 5, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(27, 'manage_loan_repayments', 'web', 'Manage loan repayments', 'loans', 1, 6, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(28, 'bulk_upload_loan_repayments', 'web', 'Bulk upload loan repayments', 'loans', 1, 7, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(29, 'manage_loan_products', 'web', 'Manage loan products', 'loans', 1, 8, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(30, 'manage_loan_settings', 'web', 'Manage loan settings', 'loans', 1, 9, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(31, 'manage_mortgages', 'web', 'Manage mortgages', 'loans', 1, 10, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(32, 'view_loan_reports', 'web', 'View loan reports', 'loans', 1, 11, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(33, 'view_properties', 'web', 'View properties', 'properties', 1, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(34, 'create_properties', 'web', 'Create properties', 'properties', 1, 2, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(35, 'edit_properties', 'web', 'Edit properties', 'properties', 1, 3, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(36, 'delete_properties', 'web', 'Delete properties', 'properties', 1, 4, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(37, 'manage_eoi_forms', 'web', 'Manage Expression of Interest forms', 'properties', 1, 5, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(38, 'manage_property_estates', 'web', 'Manage property estates', 'properties', 1, 6, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(39, 'manage_property_allottees', 'web', 'Manage property allottees', 'properties', 1, 7, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(40, 'manage_property_maintenance', 'web', 'Manage property maintenance', 'properties', 1, 8, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(41, 'view_property_reports', 'web', 'View property reports', 'properties', 1, 9, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(42, 'manage_blockchain', 'web', 'Manage blockchain transactions', 'properties', 1, 10, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(43, 'view_investments', 'web', 'View investments', 'investments', 1, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(44, 'create_investments', 'web', 'Create investments', 'investments', 1, 2, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(45, 'edit_investments', 'web', 'Edit investments', 'investments', 1, 3, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(46, 'approve_investments', 'web', 'Approve investments', 'investments', 1, 4, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(47, 'manage_investment_plans', 'web', 'Manage investment plans', 'investments', 1, 5, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(48, 'view_investment_reports', 'web', 'View investment reports', 'investments', 1, 6, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(49, 'view_documents', 'web', 'View documents', 'documents', 1, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(50, 'upload_documents', 'web', 'Upload documents', 'documents', 1, 2, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(51, 'approve_documents', 'web', 'Approve documents', 'documents', 1, 3, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(52, 'reject_documents', 'web', 'Reject documents', 'documents', 1, 4, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(53, 'delete_documents', 'web', 'Delete documents', 'documents', 1, 5, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(54, 'view_reports', 'web', 'View all reports', 'reports', 1, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(55, 'export_reports', 'web', 'Export reports', 'reports', 1, 2, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(56, 'view_analytics', 'web', 'View analytics dashboard', 'reports', 1, 3, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(57, 'view_users', 'web', 'View users', 'users', 1, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(58, 'create_users', 'web', 'Create users', 'users', 1, 2, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(59, 'edit_users', 'web', 'Edit users', 'users', 1, 3, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(60, 'delete_users', 'web', 'Delete users', 'users', 1, 4, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(61, 'manage_roles', 'web', 'Manage roles and permissions', 'users', 1, 5, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(62, 'view_activity_logs', 'web', 'View activity logs', 'system', 1, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(63, 'manage_settings', 'web', 'Manage system settings', 'system', 1, 2, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(64, 'view_equity_contributions', 'web', 'View equity contributions', 'equity', 1, 1, '2025-11-05 10:22:38', '2025-11-05 10:22:38'),
(65, 'create_equity_contributions', 'web', 'Create equity contributions', 'equity', 1, 2, '2025-11-05 10:22:38', '2025-11-05 10:22:38'),
(66, 'approve_equity_contributions', 'web', 'Approve equity contributions', 'equity', 1, 3, '2025-11-05 10:22:38', '2025-11-05 10:22:38'),
(67, 'reject_equity_contributions', 'web', 'Reject equity contributions', 'equity', 1, 4, '2025-11-05 10:22:38', '2025-11-05 10:22:38'),
(68, 'bulk_upload_equity_contributions', 'web', 'Bulk upload equity contributions', 'equity', 1, 5, '2025-11-05 10:22:38', '2025-11-05 10:22:38'),
(69, 'manage_equity_plans', 'web', 'Manage equity plans', 'equity', 1, 6, '2025-11-05 10:22:38', '2025-11-05 10:22:38'),
(70, 'view_equity_wallet', 'web', 'View equity wallet balances', 'equity', 1, 7, '2025-11-05 10:22:38', '2025-11-05 10:22:38'),
(71, 'view_equity_wallet_transactions', 'web', 'View equity wallet transactions', 'equity', 1, 8, '2025-11-05 10:22:38', '2025-11-05 10:22:38'),
(72, 'view_equity_reports', 'web', 'View equity contribution reports', 'equity', 1, 9, '2025-11-05 10:22:38', '2025-11-05 10:22:38'),
(73, 'view_payment_gateways', 'web', 'View payment gateways', 'payment_gateways', 1, 1, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(74, 'manage_payment_gateways', 'web', 'Manage payment gateway configurations', 'payment_gateways', 1, 2, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(75, 'test_payment_gateways', 'web', 'Test payment gateway connections', 'payment_gateways', 1, 3, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(76, 'view_statutory_charges', 'web', 'View statutory charges', 'statutory_charges', 1, 1, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(77, 'create_statutory_charges', 'web', 'Create statutory charges', 'statutory_charges', 1, 2, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(78, 'edit_statutory_charges', 'web', 'Edit statutory charges', 'statutory_charges', 1, 3, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(79, 'delete_statutory_charges', 'web', 'Delete statutory charges', 'statutory_charges', 1, 4, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(80, 'approve_statutory_charges', 'web', 'Approve statutory charge payments', 'statutory_charges', 1, 5, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(81, 'reject_statutory_charges', 'web', 'Reject statutory charge payments', 'statutory_charges', 1, 6, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(84, 'view_maintenance', 'web', 'View maintenance requests', 'maintenance', 1, 1, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(85, 'create_maintenance', 'web', 'Create maintenance requests', 'maintenance', 1, 2, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(86, 'edit_maintenance', 'web', 'Edit maintenance requests', 'maintenance', 1, 3, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(87, 'assign_maintenance', 'web', 'Assign maintenance requests', 'maintenance', 1, 4, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(88, 'complete_maintenance', 'web', 'Complete maintenance requests', 'maintenance', 1, 5, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(89, 'delete_maintenance', 'web', 'Delete maintenance requests', 'maintenance', 1, 6, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(90, 'view_mail', 'web', 'View mail messages', 'mail_service', 1, 1, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(91, 'compose_mail', 'web', 'Compose mail messages', 'mail_service', 1, 2, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(92, 'reply_mail', 'web', 'Reply to mail messages', 'mail_service', 1, 3, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(93, 'assign_mail', 'web', 'Assign mail messages', 'mail_service', 1, 4, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(94, 'bulk_mail', 'web', 'Send bulk mail messages', 'mail_service', 1, 5, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(95, 'delete_mail', 'web', 'Delete mail messages', 'mail_service', 1, 6, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(96, 'view_white_label', 'web', 'View white label settings', 'white_label', 1, 1, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(97, 'manage_white_label', 'web', 'Manage white label settings', 'white_label', 1, 2, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(98, 'view_kyc', 'web', 'View KYC submissions', 'members', 1, 7, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(99, 'approve_kyc', 'web', 'Approve KYC submissions', 'members', 1, 8, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(100, 'reject_kyc', 'web', 'Reject KYC submissions', 'members', 1, 9, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(101, 'create_loan_plans', 'web', 'Create loan plans', 'loans', 1, 8, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(102, 'edit_loan_plans', 'web', 'Edit loan plans', 'loans', 1, 9, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(103, 'delete_loan_plans', 'web', 'Delete loan plans', 'loans', 1, 10, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(104, 'disburse_loans', 'web', 'Disburse approved loans', 'loans', 1, 11, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(105, 'create_investment_plans', 'web', 'Create investment plans', 'investments', 1, 6, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(106, 'edit_investment_plans', 'web', 'Edit investment plans', 'investments', 1, 7, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(107, 'delete_investment_plans', 'web', 'Delete investment plans', 'investments', 1, 8, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(108, 'approve_allotments', 'web', 'Approve property allotments', 'properties', 1, 8, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(109, 'reject_allotments', 'web', 'Reject property allotments', 'properties', 1, 9, '2025-11-05 10:22:39', '2025-11-05 10:22:39'),
(110, 'manage_payments', 'web', 'Manage payment transactions', 'financial', 1, 8, '2025-11-05 10:22:39', '2025-11-05 10:22:39');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_tenant_id_index` (`tenant_id`)
) ENGINE=MyISAM AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

DROP TABLE IF EXISTS `properties`;
CREATE TABLE IF NOT EXISTS `properties` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('apartment','house','duplex','bungalow','land','commercial') COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `property_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(15,2) NOT NULL,
  `size` decimal(10,2) DEFAULT NULL,
  `bedrooms` int DEFAULT NULL,
  `bathrooms` int DEFAULT NULL,
  `features` json DEFAULT NULL,
  `status` enum('available','allocated','sold','maintenance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `coordinates` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `properties_type_index` (`type`),
  KEY `properties_status_index` (`status`),
  KEY `properties_is_featured_index` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `properties`
-- --------------------------------------------------------

--
-- Table structure for table `property_allocations`
--

DROP TABLE IF EXISTS `property_allocations`;
CREATE TABLE IF NOT EXISTS `property_allocations` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `allocation_date` date NOT NULL,
  `status` enum('pending','approved','rejected','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_allocations_property_id_index` (`property_id`),
  KEY `property_allocations_member_id_index` (`member_id`),
  KEY `property_allocations_status_index` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property_documents`
--

DROP TABLE IF EXISTS `property_documents`;
CREATE TABLE IF NOT EXISTS `property_documents` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_by_role` enum('member','admin','system') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint UNSIGNED DEFAULT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'eoi, payment_proof, mortgage_agreement, certificate, other',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_property_member` (`property_id`,`member_id`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_documents`
-- --------------------------------------------------------

--
-- Table structure for table `property_images`
--

DROP TABLE IF EXISTS `property_images`;
CREATE TABLE IF NOT EXISTS `property_images` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `alt_text` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_images_property_id_index` (`property_id`),
  KEY `property_images_is_primary_index` (`is_primary`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_images`
-- --------------------------------------------------------

--
-- Table structure for table `property_interests`
--

DROP TABLE IF EXISTS `property_interests`;
CREATE TABLE IF NOT EXISTS `property_interests` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `interest_type` enum('rental','purchase','investment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected','withdrawn') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `priority` int NOT NULL DEFAULT '0',
  `applicant_snapshot` json DEFAULT NULL,
  `next_of_kin_snapshot` json DEFAULT NULL,
  `net_salary` decimal(15,2) DEFAULT NULL,
  `has_existing_loan` tinyint(1) NOT NULL DEFAULT '0',
  `existing_loan_types` json DEFAULT NULL,
  `property_snapshot` json DEFAULT NULL,
  `funding_option` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `funding_breakdown` json DEFAULT NULL,
  `preferred_payment_methods` json DEFAULT NULL,
  `documents` json DEFAULT NULL,
  `signature_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `mortgage_preferences` json DEFAULT NULL,
  `mortgage_flagged` tinyint(1) NOT NULL DEFAULT '0',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejected_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_interests_member_id_foreign` (`member_id`),
  KEY `property_interests_property_id_member_id_index` (`property_id`,`member_id`),
  KEY `property_interests_status_index` (`status`),
  KEY `property_interests_priority_index` (`priority`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_interests`
-- --------------------------------------------------------

--
-- Table structure for table `property_maintenance_records`
--

DROP TABLE IF EXISTS `property_maintenance_records`;
CREATE TABLE IF NOT EXISTS `property_maintenance_records` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reported_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_to` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_cost` decimal(15,2) DEFAULT NULL,
  `actual_cost` decimal(15,2) DEFAULT NULL,
  `reported_date` date DEFAULT NULL,
  `started_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_maintenance_records_property_id_status_index` (`property_id`,`status`),
  KEY `property_maintenance_records_reported_date_index` (`reported_date`),
  KEY `property_maintenance_records_property_id_index` (`property_id`),
  KEY `property_maintenance_records_reported_by_index` (`reported_by`),
  KEY `property_maintenance_records_assigned_to_index` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property_payment_plans`
--

DROP TABLE IF EXISTS `property_payment_plans`;
CREATE TABLE IF NOT EXISTS `property_payment_plans` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `interest_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `configured_by` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','active','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `funding_option` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `selected_methods` json DEFAULT NULL,
  `configuration` json DEFAULT NULL,
  `schedule` json DEFAULT NULL,
  `total_amount` decimal(20,2) DEFAULT NULL,
  `initial_balance` decimal(20,2) DEFAULT NULL,
  `remaining_balance` decimal(20,2) DEFAULT NULL,
  `starts_on` timestamp NULL DEFAULT NULL,
  `ends_on` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_property_member` (`property_id`,`member_id`),
  KEY `idx_member_status` (`member_id`,`status`),
  KEY `idx_configured_by` (`configured_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_payment_plans`

--
-- Table structure for table `property_payment_transactions`
--

DROP TABLE IF EXISTS `property_payment_transactions`;
CREATE TABLE IF NOT EXISTS `property_payment_transactions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mortgage_plan_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'loan, mortgage, equity_wallet, cooperative, cash, refund, adjustment',
  `amount` decimal(20,2) NOT NULL,
  `direction` enum('debit','credit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'credit',
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `paid_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_property_member` (`property_id`,`member_id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_mortgage_plan_id` (`mortgage_plan_id`),
  KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_payment_transactions`
-- --------------------------------------------------------

--
-- Table structure for table `property_transfers`
--

DROP TABLE IF EXISTS `property_transfers`;
CREATE TABLE IF NOT EXISTS `property_transfers` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transfer_type` enum('sale','gift','external') COLLATE utf8mb4_unicode_ci NOT NULL,
  `buyer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `buyer_contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buyer_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_price` decimal(15,2) NOT NULL,
  `transfer_fee` decimal(15,2) NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `documents` json DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `approved_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_property_transfers_approved_by` (`approved_by`),
  KEY `idx_property_id` (`property_id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

DROP TABLE IF EXISTS `refunds`;
CREATE TABLE IF NOT EXISTS `refunds` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_type` enum('refund','stoppage_of_deduction','building_plan','tdp','change_of_ownership','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'refund',
  `status` enum('pending','approved','rejected','processing','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `requested_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` enum('wallet','contribution','investment_return','equity_wallet') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `admin_response` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `processed_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejected_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `refunds_member_id_index` (`member_id`),
  KEY `refunds_source_index` (`source`),
  KEY `refunds_created_at_index` (`created_at`),
  KEY `refunds_processed_by_foreign` (`processed_by`),
  KEY `fk_refunds_requested_by` (`requested_by`),
  KEY `fk_refunds_approved_by` (`approved_by`),
  KEY `fk_refunds_rejected_by` (`rejected_by`),
  KEY `idx_refunds_status` (`status`),
  KEY `idx_refunds_request_type` (`request_type`),
  KEY `idx_refunds_ticket_number` (`ticket_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `refunds`
-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(125) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `guard_name` varchar(125) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`),
  KEY `roles_is_active_index` (`is_active`),
  KEY `roles_sort_order_index` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `display_name`, `description`, `guard_name`, `color`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'super_admin', 'Super Admin', 'Full access to all system features and settings', 'web', 'bg-red-500', 1, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(2, 'admin', 'Business Admin', 'Full access to business management features', 'web', 'bg-blue-500', 2, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(3, 'finance_manager', 'Finance Manager', 'Manage contributions, wallets, and financial reports', 'web', 'bg-green-500', 3, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(4, 'loan_officer', 'Loan Officer', 'Manage loans, mortgages, and repayments', 'web', 'bg-purple-500', 4, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(5, 'property_manager', 'Property Manager', 'Manage properties, estates, and maintenance', 'web', 'bg-orange-500', 5, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(6, 'member_manager', 'Member Manager', 'Manage members, subscriptions, and KYC', 'web', 'bg-indigo-500', 6, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(7, 'document_manager', 'Document Manager', 'Manage documents and approvals', 'web', 'bg-pink-500', 7, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(8, 'investment_manager', 'Investment Manager', 'Manage investment plans and portfolios', 'web', 'bg-yellow-500', 8, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02'),
(9, 'system_admin', 'System Administrator', 'Manage users, roles, and system settings', 'web', 'bg-gray-500', 9, 1, '2025-10-29 22:18:02', '2025-10-29 22:18:02');

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
CREATE TABLE IF NOT EXISTS `role_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 1),
(5, 1),
(6, 1),
(7, 1),
(8, 1),
(9, 1),
(10, 1),
(11, 1),
(12, 1),
(13, 1),
(14, 1),
(15, 1),
(16, 1),
(17, 1),
(18, 1),
(19, 1),
(20, 1),
(21, 1),
(22, 1),
(23, 1),
(24, 1),
(25, 1),
(26, 1),
(27, 1),
(28, 1),
(29, 1),
(30, 1),
(31, 1),
(32, 1),
(33, 1),
(34, 1),
(35, 1),
(36, 1),
(37, 1),
(38, 1),
(39, 1),
(40, 1),
(41, 1),
(42, 1),
(43, 1),
(44, 1),
(45, 1),
(46, 1),
(47, 1),
(48, 1),
(49, 1),
(50, 1),
(51, 1),
(52, 1),
(53, 1),
(54, 1),
(55, 1),
(56, 1),
(57, 1),
(58, 1),
(59, 1),
(60, 1),
(61, 1),
(62, 1),
(63, 1),
(64, 1),
(65, 1),
(66, 1),
(67, 1),
(68, 1),
(69, 1),
(70, 1),
(71, 1),
(72, 1),
(73, 1),
(74, 1),
(75, 1),
(76, 1),
(77, 1),
(78, 1),
(79, 1),
(80, 1),
(81, 1),
(84, 1),
(85, 1),
(86, 1),
(87, 1),
(88, 1),
(89, 1),
(90, 1),
(91, 1),
(92, 1),
(93, 1),
(94, 1),
(95, 1),
(96, 1),
(97, 1),
(98, 1),
(99, 1),
(100, 1),
(101, 1),
(102, 1),
(103, 1),
(104, 1),
(105, 1),
(106, 1),
(107, 1),
(108, 1),
(109, 1),
(110, 1),
(1, 2),
(2, 2),
(3, 2),
(4, 2),
(5, 2),
(6, 2),
(7, 2),
(8, 2),
(9, 2),
(10, 2),
(11, 2),
(12, 2),
(13, 2),
(14, 2),
(15, 2),
(16, 2),
(17, 2),
(18, 2),
(19, 2),
(20, 2),
(21, 2),
(22, 2),
(23, 2),
(24, 2),
(25, 2),
(26, 2),
(27, 2),
(28, 2),
(29, 2),
(30, 2),
(31, 2),
(32, 2),
(33, 2),
(34, 2),
(35, 2),
(36, 2),
(37, 2),
(38, 2),
(39, 2),
(40, 2),
(41, 2),
(42, 2),
(43, 2),
(44, 2),
(45, 2),
(46, 2),
(47, 2),
(48, 2),
(49, 2),
(50, 2),
(51, 2),
(52, 2),
(53, 2),
(54, 2),
(55, 2),
(56, 2),
(57, 2),
(58, 2),
(59, 2),
(60, 2),
(61, 2),
(64, 2),
(65, 2),
(66, 2),
(67, 2),
(68, 2),
(69, 2),
(70, 2),
(71, 2),
(72, 2),
(73, 2),
(74, 2),
(75, 2),
(76, 2),
(77, 2),
(78, 2),
(79, 2),
(80, 2),
(81, 2),
(84, 2),
(85, 2),
(86, 2),
(87, 2),
(88, 2),
(89, 2),
(90, 2),
(91, 2),
(92, 2),
(93, 2),
(94, 2),
(95, 2),
(96, 2),
(97, 2),
(98, 2),
(99, 2),
(100, 2),
(101, 2),
(102, 2),
(103, 2),
(104, 2),
(105, 2),
(106, 2),
(107, 2),
(108, 2),
(109, 2),
(110, 2),
(1, 3),
(7, 3),
(8, 3),
(9, 3),
(10, 3),
(11, 3),
(12, 3),
(13, 3),
(14, 3),
(15, 3),
(16, 3),
(17, 3),
(18, 3),
(19, 3),
(20, 3),
(21, 3),
(22, 3),
(43, 3),
(49, 3),
(54, 3),
(55, 3),
(56, 3),
(64, 3),
(65, 3),
(66, 3),
(67, 3),
(68, 3),
(69, 3),
(70, 3),
(71, 3),
(72, 3),
(73, 3),
(74, 3),
(75, 3),
(76, 3),
(77, 3),
(78, 3),
(79, 3),
(80, 3),
(81, 3),
(110, 3),
(1, 4),
(16, 4),
(22, 4),
(23, 4),
(24, 4),
(25, 4),
(26, 4),
(27, 4),
(28, 4),
(29, 4),
(30, 4),
(31, 4),
(32, 4),
(49, 4),
(54, 4),
(55, 4),
(56, 4),
(101, 4),
(102, 4),
(103, 4),
(104, 4),
(1, 5),
(33, 5),
(34, 5),
(35, 5),
(36, 5),
(37, 5),
(38, 5),
(39, 5),
(40, 5),
(41, 5),
(42, 5),
(49, 5),
(54, 5),
(55, 5),
(56, 5),
(84, 5),
(85, 5),
(86, 5),
(87, 5),
(88, 5),
(89, 5),
(108, 5),
(109, 5),
(1, 6),
(2, 6),
(3, 6),
(4, 6),
(5, 6),
(6, 6),
(7, 6),
(22, 6),
(43, 6),
(49, 6),
(50, 6),
(51, 6),
(52, 6),
(53, 6),
(54, 6),
(55, 6),
(56, 6),
(64, 6),
(69, 6),
(70, 6),
(71, 6),
(72, 6),
(90, 6),
(91, 6),
(92, 6),
(93, 6),
(94, 6),
(95, 6),
(98, 6),
(99, 6),
(100, 6),
(1, 7),
(49, 7),
(50, 7),
(51, 7),
(52, 7),
(53, 7),
(54, 7),
(55, 7),
(56, 7),
(1, 8),
(16, 8),
(43, 8),
(44, 8),
(45, 8),
(46, 8),
(47, 8),
(48, 8),
(54, 8),
(55, 8),
(56, 8),
(1, 9),
(7, 9),
(22, 9),
(33, 9),
(43, 9),
(49, 9),
(54, 9),
(55, 9),
(56, 9),
(57, 9),
(58, 9),
(59, 9),
(60, 9),
(61, 9),
(62, 9),
(63, 9),
(73, 9),
(74, 9),
(75, 9),
(96, 9),
(97, 9);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `statutory_charges`
--

DROP TABLE IF EXISTS `statutory_charges`;
CREATE TABLE IF NOT EXISTS `statutory_charges` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejected_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `statutory_charges_member_id_index` (`member_id`),
  KEY `statutory_charges_type_index` (`type`),
  KEY `statutory_charges_status_index` (`status`),
  KEY `statutory_charges_due_date_index` (`due_date`),
  KEY `statutory_charges_department_id_index` (`department_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `statutory_charges`
-- --------------------------------------------------------

--
-- Table structure for table `statutory_charge_departments`
--

DROP TABLE IF EXISTS `statutory_charge_departments`;
CREATE TABLE IF NOT EXISTS `statutory_charge_departments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `statutory_charge_departments`
-- --------------------------------------------------------

--
-- Table structure for table `statutory_charge_payments`
--

DROP TABLE IF EXISTS `statutory_charge_payments`;
CREATE TABLE IF NOT EXISTS `statutory_charge_payments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statutory_charge_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `statutory_charge_payments_statutory_charge_id_index` (`statutory_charge_id`),
  KEY `statutory_charge_payments_status_index` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `statutory_charge_payments`

--
-- Table structure for table `statutory_charge_types`
--

DROP TABLE IF EXISTS `statutory_charge_types`;
CREATE TABLE IF NOT EXISTS `statutory_charge_types` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `default_amount` decimal(15,2) DEFAULT NULL,
  `frequency` enum('monthly','quarterly','bi_annually','annually') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'annually',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`type`),
  KEY `idx_type` (`type`),
  KEY `idx_is_active` (`is_active`),
  KEY `statutory_charge_types_frequency_index` (`frequency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `statutory_charge_types`
-- --------------------------------------------------------

--
-- Table structure for table `tenant_settings`
--

DROP TABLE IF EXISTS `tenant_settings`;
CREATE TABLE IF NOT EXISTS `tenant_settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'string' COMMENT 'string, boolean, integer, json, array',
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'general' COMMENT 'general, email, security, notifications, system',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_public` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_id_key` (`tenant_id`,`key`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_key` (`key`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenant_settings`
-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','manager','staff','member') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_email_index` (`email`),
  KEY `users_role_index` (`role`),
  KEY `users_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

DROP TABLE IF EXISTS `user_settings`;
CREATE TABLE IF NOT EXISTS `user_settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_notifications` tinyint(1) NOT NULL DEFAULT '1',
  `sms_notifications` tinyint(1) NOT NULL DEFAULT '0',
  `payment_reminders` tinyint(1) NOT NULL DEFAULT '1',
  `loan_updates` tinyint(1) NOT NULL DEFAULT '1',
  `investment_updates` tinyint(1) NOT NULL DEFAULT '1',
  `property_updates` tinyint(1) NOT NULL DEFAULT '1',
  `contribution_updates` tinyint(1) NOT NULL DEFAULT '1',
  `language` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `timezone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Africa/Lagos',
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `two_factor_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_recovery_codes` json DEFAULT NULL,
  `profile_visible` tinyint(1) NOT NULL DEFAULT '1',
  `show_email` tinyint(1) NOT NULL DEFAULT '0',
  `show_phone` tinyint(1) NOT NULL DEFAULT '0',
  `preferences` json DEFAULT NULL COMMENT 'For future extensibility',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_settings_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_settings`
-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

DROP TABLE IF EXISTS `wallets`;
CREATE TABLE IF NOT EXISTS `wallets` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wallets_user_id_index` (`user_id`),
  KEY `wallets_is_active_index` (`is_active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wallets`
-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

DROP TABLE IF EXISTS `wallet_transactions`;
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wallet_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('pending','completed','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_method` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wallet_transactions_wallet_id_index` (`wallet_id`),
  KEY `wallet_transactions_type_index` (`type`),
  KEY `wallet_transactions_status_index` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wallet_transactions`
-- --------------------------------------------------------

--
-- Table structure for table `white_label_settings`
--

DROP TABLE IF EXISTS `white_label_settings`;
CREATE TABLE IF NOT EXISTS `white_label_settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_tagline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_description` text COLLATE utf8mb4_unicode_ci,
  `logo_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_dark_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#FDB11E',
  `secondary_color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#276254',
  `accent_color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#10b981',
  `background_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `font_family` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Inter',
  `heading_font` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_font` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_css` text COLLATE utf8mb4_unicode_ci,
  `custom_js` text COLLATE utf8mb4_unicode_ci,
  `favicon_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_background_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dashboard_hero_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `email_sender_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_reply_to` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_footer_text` text COLLATE utf8mb4_unicode_ci,
  `email_logo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `privacy_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `support_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `support_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `help_center_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `footer_text` text COLLATE utf8mb4_unicode_ci,
  `footer_links` json DEFAULT NULL,
  `social_links` json DEFAULT NULL,
  `enabled_modules` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `white_label_settings_tenant_id_unique` (`tenant_id`),
  KEY `white_label_settings_tenant_id_index` (`tenant_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `white_label_settings`
--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `blockchain_property_records`
--
ALTER TABLE `blockchain_property_records`
  ADD CONSTRAINT `fk_blockchain_property_records_property_id` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_blockchain_property_records_registered_by` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_blockchain_property_records_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `blockchain_settings`
--
ALTER TABLE `blockchain_settings`
  ADD CONSTRAINT `fk_blockchain_settings_setup_completed_by` FOREIGN KEY (`setup_completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `blockchain_wallets`
--
ALTER TABLE `blockchain_wallets`
  ADD CONSTRAINT `fk_blockchain_wallets_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `contributions`
--
ALTER TABLE `contributions`
  ADD CONSTRAINT `contributions_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `contribution_plans` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `contribution_auto_pay_settings`
--
ALTER TABLE `contribution_auto_pay_settings`
  ADD CONSTRAINT `contribution_auto_pay_settings_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `equity_contributions`
--
ALTER TABLE `equity_contributions`
  ADD CONSTRAINT `fk_equity_contributions_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_equity_contributions_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_equity_contributions_plan_id` FOREIGN KEY (`plan_id`) REFERENCES `equity_plans` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `equity_transactions`
--
ALTER TABLE `equity_transactions`
  ADD CONSTRAINT `fk_equity_transactions_equity_wallet_balance_id` FOREIGN KEY (`equity_wallet_balance_id`) REFERENCES `equity_wallet_balances` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_equity_transactions_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `equity_wallet_balances`
--
ALTER TABLE `equity_wallet_balances`
  ADD CONSTRAINT `fk_equity_wallet_balances_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `internal_mortgage_repayments`
--
ALTER TABLE `internal_mortgage_repayments`
  ADD CONSTRAINT `fk_internal_mortgage_repayments_plan_id` FOREIGN KEY (`internal_mortgage_plan_id`) REFERENCES `internal_mortgage_plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_internal_mortgage_repayments_property_id` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `mail_attachments`
--
ALTER TABLE `mail_attachments`
  ADD CONSTRAINT `fk_mail_attachments_mail_id` FOREIGN KEY (`mail_id`) REFERENCES `mails` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mail_recipients`
--
ALTER TABLE `mail_recipients`
  ADD CONSTRAINT `fk_mail_recipients_mail_id` FOREIGN KEY (`mail_id`) REFERENCES `mails` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mail_recipients_recipient_id` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_contribution_plan_id_foreign` FOREIGN KEY (`contribution_plan_id`) REFERENCES `contribution_plans` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mortgage_repayments`
--
ALTER TABLE `mortgage_repayments`
  ADD CONSTRAINT `fk_mortgage_repayments_mortgage_id` FOREIGN KEY (`mortgage_id`) REFERENCES `mortgages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mortgage_repayments_property_id` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `property_transfers`
--
ALTER TABLE `property_transfers`
  ADD CONSTRAINT `fk_property_transfers_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_property_transfers_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_property_transfers_property_id` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `fk_refunds_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_refunds_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_refunds_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `refunds_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `refunds_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_user_settings_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;