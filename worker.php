<?php
/**
 * Worker Script for Processing Job Queue
 * 
 * Processes queued jobs in batches for better performance.
 * Run this script via cron every minute: * * * * * php /path/to/worker.php
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load configuration
$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['app']['timezone']);

// Load dependencies
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TelegramHelper.php';
require_once __DIR__ . '/BinanceAPI.php';
require_once __DIR__ . '/Indicators.php';
require_once __DIR__ . '/ChartGenerator.php';

// Initialize components
$telegram = new TelegramHelper();
$binance = new BinanceAPI();
$db = Database::getInstance();

// Get batch size from config
$batchSize = $config['worker']['batch_size'];

// Fetch pending jobs
$result = $db->query(
    "SELECT * FROM bot_job_queue 
     WHERE status = 'pending' 
     ORDER BY created_at ASC 
     LIMIT $batchSize"
);

if (!$result) {
    error_log("Failed to fetch jobs from queue");
    exit(1);
}

$jobs = [];
while ($row = $result->fetch_assoc()) {
    $jobs[] = $row;
}
$result->free();

if (empty($jobs)) {
    // No jobs to process
    exit(0);
}

// Process each job
foreach ($jobs as $job) {
    try {
        // Mark job as processing
        updateJobStatus($db, $job['id'], 'processing');

        // Process based on command
        switch ($job['command']) {
            case 'price':
                processPrice($telegram, $binance, $job);
                break;

            case 'chart':
                processChart($telegram, $binance, $job);
                break;

            case 'candlestick':
                processCandlestick($telegram, $binance, $job);
                break;

            case 'indicator':
                processIndicator($telegram, $binance, $db, $job);
                break;

            default:
                throw new Exception("Unknown command: {$job['command']}");
        }

        // Mark job as done
        updateJobStatus($db, $job['id'], 'done');

    } catch (Exception $e) {
        error_log("Job {$job['id']} failed: " . $e->getMessage());
        updateJobStatus($db, $job['id'], 'failed');
        
        // Send error message to user
        $telegram->sendMessage(
            $job['chat_id'],
            "❌ Failed to process your request: " . $e->getMessage()
        );
    }

    // Small delay between jobs to avoid rate limiting
    usleep(500000); // 0.5 seconds
}

echo "Processed " . count($jobs) . " jobs\n";
exit(0);

/**
 * Update job status in database
 */
function updateJobStatus($db, $jobId, $status) {
    $stmt = $db->prepare(
        "UPDATE bot_job_queue SET status = ? WHERE id = ?",
        [$status, $jobId],
        'si'
    );
    
    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Process price command
 */
function processPrice($telegram, $binance, $job) {
    $symbol = $job['pair'];
    
    // Get 24hr ticker data
    $ticker = $binance->get24hrTicker($symbol);
    
    if (!$ticker) {
        throw new Exception("Failed to fetch price data for $symbol");
    }

    $price = floatval($ticker['lastPrice']);
    $change24h = floatval($ticker['priceChangePercent']);
    $high24h = floatval($ticker['highPrice']);
    $low24h = floatval($ticker['lowPrice']);
    $volume = floatval($ticker['volume']);

    // Format message
    $message = "💰 *{$symbol} Price*\n\n";
    $message .= "💵 Price: `" . TelegramHelper::formatPrice($price) . " USDT`\n";
    $message .= "📊 24h Change: " . TelegramHelper::formatPercentage($change24h) . "\n";
    $message .= "📈 24h High: `" . TelegramHelper::formatPrice($high24h) . " USDT`\n";
    $message .= "📉 24h Low: `" . TelegramHelper::formatPrice($low24h) . " USDT`\n";
    $message .= "📦 24h Volume: `" . number_format($volume, 2) . "`\n";
    $message .= "\n⏰ Updated: " . date('Y-m-d H:i:s');

    $telegram->sendMessage($job['chat_id'], $message);

    // Save to database
    savePrice($binance, $symbol, $price, $high24h, $low24h);
}

/**
 * Process chart command
 */
function processChart($telegram, $binance, $job) {
    $symbol = $job['pair'];
    
    // Get 5-minute klines for last hour (12 candles)
    $klines = $binance->getKlines($symbol, '5m', 12);
    
    if (!$klines) {
        throw new Exception("Failed to fetch chart data for $symbol");
    }

    $ohlc = $binance->parseOHLC($klines);
    
    // Generate chart URL
    $chartUrl = ChartGenerator::generateLineChart($ohlc, $symbol, '5m');
    
    if (!$chartUrl) {
        throw new Exception("Failed to generate chart for $symbol");
    }

    // Send chart
    $caption = "📈 *{$symbol} Line Chart*\n5-minute intervals (Last hour)";
    $telegram->sendPhoto($job['chat_id'], $chartUrl, $caption);
}

/**
 * Process candlestick chart command
 */
function processCandlestick($telegram, $binance, $job) {
    $symbol = $job['pair'];
    
    // Get daily klines for last 30 days
    $klines = $binance->getKlines($symbol, '1d', 30);
    
    if (!$klines) {
        throw new Exception("Failed to fetch candlestick data for $symbol");
    }

    $ohlc = $binance->parseOHLC($klines);
    
    // Generate chart URL
    $chartUrl = ChartGenerator::generateCandlestickChart($ohlc, $symbol);
    
    if (!$chartUrl) {
        throw new Exception("Failed to generate candlestick chart for $symbol");
    }

    // Send chart
    $caption = "🕯️ *{$symbol} Candlestick Chart*\nDaily candles (Last 30 days)";
    $telegram->sendPhoto($job['chat_id'], $chartUrl, $caption);
}

/**
 * Process indicator command (StochRSI)
 */
function processIndicator($telegram, $binance, $db, $job) {
    $symbol = $job['pair'];
    
    // Get 4-hour klines for sufficient data (100 candles)
    $klines = $binance->getKlines($symbol, '4h', 100);
    
    if (!$klines) {
        throw new Exception("Failed to fetch data for indicator calculation");
    }

    $ohlc = $binance->parseOHLC($klines);
    
    // Extract closing prices
    $closePrices = array_column($ohlc, 'close');
    
    // Calculate StochRSI
    $stochRSI = Indicators::calculateStochRSI($closePrices, 14, 14, 3, 3);
    
    if (empty($stochRSI['k']) || empty($stochRSI['d'])) {
        throw new Exception("Insufficient data for StochRSI calculation");
    }

    // Get latest values
    $latestK = end($stochRSI['k']);
    $latestD = end($stochRSI['d']);
    
    // Interpret signal
    $signal = Indicators::interpretStochRSI($latestK, $latestD);
    
    // Format message
    $message = "📊 *{$symbol} Stochastic RSI*\n\n";
    $message .= "{$signal['emoji']} *{$signal['condition']}*\n";
    $message .= "📌 Signal: *{$signal['action']}*\n\n";
    $message .= "📈 K Line: `{$signal['k']}`\n";
    $message .= "📉 D Line: `{$signal['d']}`\n\n";
    $message .= "💡 {$signal['description']}\n";
    $message .= "\n⏰ Calculated: " . date('Y-m-d H:i:s');

    $telegram->sendMessage($job['chat_id'], $message);
    
    // Generate and send StochRSI chart
    $chartUrl = ChartGenerator::generateStochRSIChart($stochRSI['k'], $stochRSI['d'], $symbol);
    
    if ($chartUrl) {
        $telegram->sendPhoto($job['chat_id'], $chartUrl, "StochRSI Chart for $symbol");
    }
}

/**
 * Save price to database
 */
function savePrice($binance, $symbol, $price, $high, $low) {
    global $db;
    
    // Sanitize symbol - only allow alphanumeric characters
    $sanitizedSymbol = preg_replace('/[^A-Za-z0-9]/', '', $symbol);
    
    // Create table name from symbol (e.g., BTCUSDT -> riwayat_btc_usdt)
    $tableName = 'riwayat_' . strtolower(str_replace('USDT', '_usdt', $sanitizedSymbol));
    
    // Create table if not exists
    $createTableQuery = "CREATE TABLE IF NOT EXISTS `$tableName` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        harga DECIMAL(20,8) NOT NULL,
        harga_tertinggi DECIMAL(20,8) NOT NULL,
        harga_terendah DECIMAL(20,8) NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_timestamp (timestamp)
    )";
    
    $db->query($createTableQuery);
    
    // Insert price data
    $stmt = $db->prepare(
        "INSERT INTO `$tableName` (harga, harga_tertinggi, harga_terendah) VALUES (?, ?, ?)",
        [$price, $high, $low],
        'ddd'
    );
    
    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}
