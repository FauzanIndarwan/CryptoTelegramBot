<?php
/**
 * Configuration Template for Crypto Telegram Bot
 * 
 * Copy this file to config.php and fill in your actual credentials.
 * DO NOT commit config.php to version control!
 * 
 * For production, use environment variables instead of hardcoded values.
 */

// TELEGRAM
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE');
define('CHAT_ID_NOTIFIKASI', getenv('CHAT_ID_NOTIFIKASI') ?: 'YOUR_CHAT_ID');

// DATABASE
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_pass');
define('DB_NAME', getenv('DB_NAME') ?: 'your_db_name');

// BOT SETTINGS
define('KUNCI_RAHASIA_CRON', getenv('CRON_KEY') ?: 'your_secret_cron_key');
define('AMBANG_BATAS_PERSENTASE', 5);
define('STOCH_RSI_OVERSOLD', 20);
define('STOCH_RSI_OVERBOUGHT', 80);
define('STOCH_RSI_PERIODE', 14);
