CREATE TABLE IF NOT EXISTS `sm_user_credential_link` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token` CHAR(64) NOT NULL,
    `no_cust` VARCHAR(32) NOT NULL,
    `custid` BIGINT UNSIGNED NULL DEFAULT NULL,
    `tahun_akademik` VARCHAR(32) NOT NULL DEFAULT 'all',
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `created_by` VARCHAR(64) NULL DEFAULT NULL,
    `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sm_user_credential_link_token_unique` (`token`),
    KEY `sm_user_credential_link_no_cust_index` (`no_cust`),
    KEY `sm_user_credential_link_active_index` (`no_cust`, `used_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
