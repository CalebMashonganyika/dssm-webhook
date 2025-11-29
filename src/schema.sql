-- DSSM WhatsApp EcoCash Subscription System Database Schema
-- Compatible with MySQL 5.7+

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(15) UNIQUE NOT NULL,
    device_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_device_id (device_id)
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone VARCHAR(15) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_ref VARCHAR(50) UNIQUE NOT NULL,
    timestamp DATETIME NOT NULL,
    status ENUM('pending', 'verified', 'failed') DEFAULT 'pending',
    raw_message JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_transaction_ref (transaction_ref),
    INDEX idx_status (status),
    INDEX idx_timestamp (timestamp)
);

-- Activation codes table
CREATE TABLE IF NOT EXISTS activation_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    payment_id INT NOT NULL,
    plan VARCHAR(10) NOT NULL DEFAULT '1M', -- 1M = Monthly
    amount DECIMAL(10,2) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    INDEX idx_code (code),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used (used)
);

-- Subscriptions table
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activation_code VARCHAR(32) NOT NULL,
    payment_id INT NOT NULL,
    plan VARCHAR(10) NOT NULL DEFAULT '1M',
    start_date DATETIME NOT NULL,
    expiry_date DATETIME NOT NULL,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (activation_code) REFERENCES activation_codes(code) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_activation_code (activation_code),
    INDEX idx_status (status),
    INDEX idx_expiry_date (expiry_date)
);

-- Insert sample data for testing (optional)
-- INSERT INTO users (phone, device_id) VALUES ('0771234567', 'test_device_123');