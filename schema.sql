-- Schema for the click-tracking table used by rp.php.
-- Run this once against the u373133718_ppnet database (see instructions.txt).

CREATE TABLE IF NOT EXISTS clicks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    click_id VARCHAR(255) NOT NULL,
    offer VARCHAR(50) NULL,
    fbclid VARCHAR(255) NULL,
    campaign_id VARCHAR(100) NULL,
    adset_id VARCHAR(100) NULL,
    ad_id VARCHAR(100) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    referrer VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    payout DECIMAL(10,2) NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    converted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_click_id (click_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adds the offer column to a clicks table created before this column existed.
-- Safe to run again: it no-ops if the column is already there.
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clicks' AND COLUMN_NAME = 'offer'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE clicks ADD COLUMN offer VARCHAR(50) NULL AFTER click_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
