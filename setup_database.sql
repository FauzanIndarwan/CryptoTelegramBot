-- Database Setup for Crypto Telegram Bot
-- Run this script to create the necessary database tables

-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS crypto_bot;
USE crypto_bot;

-- Table: bot_job_queue
-- Stores queued jobs for the worker to process
CREATE TABLE IF NOT EXISTS bot_job_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id VARCHAR(50) NOT NULL,
    command VARCHAR(50) NOT NULL,
    pair VARCHAR(20) NOT NULL,
    status ENUM('pending', 'processing', 'done', 'failed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_chat_id (chat_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: data_historis_harian
-- Stores historical daily OHLC data
CREATE TABLE IF NOT EXISTS data_historis_harian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    simbol VARCHAR(20) NOT NULL,
    waktu_buka BIGINT NOT NULL,
    harga_buka DECIMAL(20,8),
    harga_tertinggi DECIMAL(20,8),
    harga_terendah DECIMAL(20,8),
    harga_tutup DECIMAL(20,8),
    volume DECIMAL(30,8),
    UNIQUE KEY unique_symbol_time (simbol, waktu_buka),
    INDEX idx_simbol (simbol),
    INDEX idx_waktu (waktu_buka)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: laporan_sentimen_pasar
-- Stores market sentiment reports
CREATE TABLE IF NOT EXISTS laporan_sentimen_pasar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moon_count INT DEFAULT 0,
    moon_level VARCHAR(100),
    crash_count INT DEFAULT 0,
    crash_level VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Note: Price history tables (riwayat_*) are created dynamically
-- when the first price data for a pair is saved
-- Example table structure:
-- 
-- CREATE TABLE riwayat_btc_usdt (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     harga DECIMAL(20,8) NOT NULL,
--     harga_tertinggi DECIMAL(20,8) NOT NULL,
--     harga_terendah DECIMAL(20,8) NOT NULL,
--     timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     INDEX idx_timestamp (timestamp)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: Clean up old data periodically
-- DELETE FROM bot_job_queue WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
-- DELETE FROM laporan_sentimen_pasar WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
