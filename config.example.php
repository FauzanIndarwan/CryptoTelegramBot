<?php
/**
 * Configuration Template for Crypto Telegram Bot
 * 
 * Copy this file to config.php and fill in your actual credentials.
 * DO NOT commit config.php to version control!
 * 
 * For production, use environment variables instead of hardcoded values.
 */

return [
    // Telegram Bot Configuration
    'telegram' => [
        'bot_token' => getenv('BOT_TOKEN') ?: 'YOUR_TELEGRAM_BOT_TOKEN_HERE',
        'chat_id_notifikasi' => getenv('CHAT_ID_NOTIFIKASI') ?: 'YOUR_NOTIFICATION_CHAT_ID',
    ],

    // Database Configuration
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'user' => getenv('DB_USER') ?: 'your_database_user',
        'password' => getenv('DB_PASS') ?: 'your_database_password',
        'name' => getenv('DB_NAME') ?: 'crypto_bot',
        'charset' => 'utf8mb4',
    ],

    // Binance API Configuration
    'binance' => [
        'base_url' => 'https://api.binance.com',
        'api_key' => getenv('BINANCE_API_KEY') ?: '', // Optional for public endpoints
        'api_secret' => getenv('BINANCE_API_SECRET') ?: '', // Optional for public endpoints
        'cache_duration' => 60, // Cache duration in seconds
    ],

    // Cron Security
    'cron' => [
        'secret_key' => getenv('CRON_KEY') ?: 'your_secret_cron_key_here',
    ],

    // Application Settings
    'app' => [
        'timezone' => 'Asia/Jakarta',
        'debug' => false,
        'log_errors' => true,
        'log_file' => __DIR__ . '/logs/app.log',
    ],

    // Worker Settings
    'worker' => [
        'batch_size' => 5, // Number of jobs to process in one batch
        'max_retries' => 3, // Maximum number of retry attempts
        'retry_delay' => 5, // Delay between retries in seconds
    ],

    // Chart Settings
    'chart' => [
        'default_interval' => '5m',
        'default_limit' => 100,
        'candlestick_days' => 30,
    ],

    // Market Monitoring
    'monitoring' => [
        'price_change_threshold' => 5.0, // Percentage change for moon/crash alerts
        'stochrsI_period' => 14,
        'stochrsi_smooth_k' => 3,
        'stochrsi_smooth_d' => 3,
    ],

    // Supported Trading Pairs
    'pairs' => [
        'default_quote' => 'USDT',
        'supported_bases' => ['BTC', 'ETH', 'BNB', 'XRP', 'ADA', 'DOGE', 'SOL', 'MATIC', 'DOT', 'AVAX'],
    ],
];
