-- DSSM WhatsApp Premium Activation Code System Database Schema
-- Compatible with MySQL 5.7+

-- Activation codes table (simplified for premium unlocks)
CREATE TABLE IF NOT EXISTS activation_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(16) UNIQUE NOT NULL, -- 8-character codes
    phone VARCHAR(15) NOT NULL, -- Phone number that requested the code
    expires_at DATETIME NOT NULL, -- Code expires in 5 minutes
    used BOOLEAN DEFAULT FALSE, -- One-time use only
    used_at DATETIME NULL, -- When code was redeemed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_phone (phone),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used (used)
);

-- Unlock keys table for manual admin-generated keys
CREATE TABLE IF NOT EXISTS unlock_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(20) UNIQUE NOT NULL, -- Alphanumeric key, e.g., XXXX-XXXX-XXXX
    expires_at DATETIME NOT NULL, -- Expires in 5 minutes
    used BOOLEAN DEFAULT FALSE, -- One-time use only
    user_id VARCHAR(50) NULL, -- User ID who redeemed the key
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_key (`key`),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used (used)
);

-- Insert sample data for testing (optional)
-- INSERT INTO activation_codes (code, phone, expires_at) VALUES ('TEST1234', '0771234567', DATE_ADD(NOW(), INTERVAL 5 MINUTE));