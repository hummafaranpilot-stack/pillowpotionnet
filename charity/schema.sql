-- Run this once in phpMyAdmin (SQL tab) on the u373133718_charity database

CREATE TABLE IF NOT EXISTS income_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_name VARCHAR(255) NOT NULL,
    entry_date DATE NOT NULL,
    amount_usd DECIMAL(12,2) NOT NULL,
    exchange_rate DECIMAL(12,4) NOT NULL,
    charity_percent DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    charity_usd DECIMAL(12,2) NOT NULL,
    charity_pkr DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If you already created the table before this change, run this instead:
-- ALTER TABLE income_entries ADD COLUMN charity_percent DECIMAL(5,2) NOT NULL DEFAULT 5.00 AFTER exchange_rate;

CREATE TABLE IF NOT EXISTS charity_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_name VARCHAR(255) NOT NULL,
    payment_date DATE NOT NULL,
    amount_pkr DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
