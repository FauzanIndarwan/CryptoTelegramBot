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
require_once __DIR__ . '/config.php';
date_default_timezone_set('Asia/Jakarta');

// Load dependencies
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TelegramHelper.php';
require_once __DIR__ . '/IndodaxAPI.php';
require_once __DIR__ . '/Indicators.php';
require_once __DIR__ . '/ChartGenerator.php';

// Initialize components
$telegram = new TelegramHelper();
$db = Database::getInstance();

// Get batch size (default 5)
$batchSize = 5;

// Fetch pending jobs
$result = $db->query(
    "SELECT * FROM bot_job_queue 
     WHERE status = 'pending' 
     ORDER BY created_at ASC 
     LIMIT " . $batchSize
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
                processPrice($telegram, $db, $job);
                break;

            case 'chart':
                processChart($telegram, $db, $job);
                break;

            case 'candlestick':
                processCandlestick($telegram, $db, $job);
                break;

            case 'indicator':
                processIndicator($telegram, $db, $job);
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
            "❌ Gagal memproses permintaan: " . $e->getMessage()
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
function processPrice($telegram, $db, $job) {
    $symbol = $job['pair'];
    $symbolLower = strtolower(str_replace('_', '', $symbol));
    
    // Get ticker data
    $ticker = IndodaxAPI::getPrice($symbolLower);
    
    if (!$ticker) {
        throw new Exception("Gagal mengambil data harga untuk $symbol");
    }

    $price = floatval($ticker['last']);
    $high24h = floatval($ticker['high']);
    $low24h = floatval($ticker['low']);
    $volume = floatval($ticker['vol_idr'] ?? 0);

    // Calculate 24h change
    $midPrice = ($high24h + $low24h) / 2;
    $change24h = $midPrice > 0 ? (($price - $midPrice) / $midPrice) * 100 : 0;

    // Format message
    $message = "💰 *{$symbol} Harga*\n\n";
    $message .= "💵 Harga: `" . number_format($price, 0, ',', '.') . " IDR`\n";
    $message .= "📊 Perubahan ~24j: " . ($change24h >= 0 ? '🟢' : '🔴') . " " . number_format($change24h, 2) . "%\n";
    $message .= "📈 Tertinggi 24j: `" . number_format($high24h, 0, ',', '.') . " IDR`\n";
    $message .= "📉 Terendah 24j: `" . number_format($low24h, 0, ',', '.') . " IDR`\n";
    if ($volume > 0) {
        $message .= "📦 Volume 24j: `" . number_format($volume, 0, ',', '.') . " IDR`\n";
    }
    $message .= "\n⏰ Update: " . date('Y-m-d H:i:s');

    $telegram->sendMessage($job['chat_id'], $message);
}

/**
 * Process chart command
 */
function processChart($telegram, $db, $job) {
    $symbol = $job['pair'];
    $symbolLower = strtolower(str_replace('_', '', $symbol));
    
    // Query historical data from database
    $tableName = 'riwayat_' . strtolower(str_replace('_', '_', $symbol));
    
    // Validate table name
    if (!preg_match('/^riwayat_[a-z0-9_]+$/', $tableName)) {
        throw new Exception("Nama tabel tidak valid");
    }
    
    $query = "SELECT UNIX_TIMESTAMP(timestamp) as ts, harga 
              FROM `{$tableName}` 
              ORDER BY timestamp DESC 
              LIMIT 60";
    
    $result = $db->query($query);
    
    if (!$result) {
        throw new Exception("Gagal mengambil data chart untuk $symbol. Pastikan data historis tersedia.");
    }
    
    $timestamps = [];
    $prices = [];
    
    while ($row = $result->fetch_assoc()) {
        $timestamps[] = (int)$row['ts'];
        $prices[] = (float)$row['harga'];
    }
    $result->free();
    
    // Reverse arrays (oldest to newest)
    $timestamps = array_reverse($timestamps);
    $prices = array_reverse($prices);
    
    if (empty($timestamps) || empty($prices)) {
        throw new Exception("Data chart tidak tersedia untuk $symbol");
    }
    
    // Generate chart URL
    $chartUrl = ChartGenerator::getLineChartUrl($symbol, $timestamps, $prices);
    
    if (!$chartUrl) {
        throw new Exception("Gagal membuat chart untuk $symbol");
    }

    // Send chart
    $caption = "📈 *{$symbol} Line Chart*\nData historis terbaru";
    $telegram->sendPhoto($job['chat_id'], $chartUrl, $caption);
}

/**
 * Process candlestick chart command
 */
function processCandlestick($telegram, $db, $job) {
    $symbol = $job['pair'];
    
    // Query OHLC data from database dengan simbol uppercase
    $query = "SELECT waktu_buka, harga_buka, harga_tertinggi, harga_terendah, harga_tutup 
              FROM data_historis_harian 
              WHERE simbol = ?
              ORDER BY waktu_buka DESC 
              LIMIT 30";
    
    $stmt = $db->prepare($query, [$symbol], 's');
    
    if (!$stmt) {
        throw new Exception("Gagal menyiapkan query candlestick");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ohlcData = [];
    while ($row = $result->fetch_assoc()) {
        $ohlcData[] = $row;
    }
    $stmt->close();
    
    // Reverse untuk urutan oldest to newest
    $ohlcData = array_reverse($ohlcData);
    
    if (empty($ohlcData)) {
        throw new Exception("Data candlestick tidak tersedia untuk $symbol. Jalankan ambil_data_historis.php terlebih dahulu.");
    }
    
    // Generate chart URL
    $chartUrl = ChartGenerator::getCandlestickChartUrl($symbol, $ohlcData);
    
    if (!$chartUrl) {
        throw new Exception("Gagal membuat candlestick chart untuk $symbol");
    }

    // Send chart
    $caption = "🕯️ *{$symbol} Candlestick Chart*\nDaily candles (30 hari terakhir)";
    $telegram->sendPhoto($job['chat_id'], $chartUrl, $caption);
}

/**
 * Process indicator command (StochRSI)
 */
function processIndicator($telegram, $db, $job) {
    $symbol = $job['pair'];
    
    // Query historical closing prices dengan simbol uppercase
    $query = "SELECT harga_tutup 
              FROM data_historis_harian 
              WHERE simbol = ?
              ORDER BY waktu_buka DESC 
              LIMIT 100";
    
    $stmt = $db->prepare($query, [$symbol], 's');
    
    if (!$stmt) {
        throw new Exception("Gagal menyiapkan query indicator");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $closePrices = [];
    while ($row = $result->fetch_assoc()) {
        $closePrices[] = floatval($row['harga_tutup']);
    }
    $stmt->close();
    
    // Reverse untuk urutan oldest to newest
    $closePrices = array_reverse($closePrices);
    
    if (count($closePrices) < 30) {
        throw new Exception("Data tidak cukup untuk menghitung StochRSI. Butuh minimal 30 data, tersedia " . count($closePrices) . ". Jalankan ambil_data_historis.php terlebih dahulu.");
    }
    
    // Calculate StochRSI
    $stochRSI = Indicators::calculateStochRSI($closePrices, 14, 14, 3, 3);
    
    if (empty($stochRSI['k']) || empty($stochRSI['d'])) {
        throw new Exception("Gagal menghitung StochRSI untuk $symbol");
    }

    // Get latest values
    $latestK = end($stochRSI['k']);
    $latestD = end($stochRSI['d']);
    
    // Interpret signal
    $signal = Indicators::interpretStochRSI($latestK, $latestD);
    
    // Format message
    $message = "📊 *{$symbol} Stochastic RSI*\n\n";
    $message .= "{$signal['emoji']} *{$signal['condition']}*\n";
    $message .= "📌 Sinyal: *{$signal['action']}*\n\n";
    $message .= "📈 K Line: `{$signal['k']}`\n";
    $message .= "📉 D Line: `{$signal['d']}`\n\n";
    $message .= "💡 {$signal['description']}\n";
    $message .= "\n⏰ Dihitung: " . date('Y-m-d H:i:s');

    $telegram->sendMessage($job['chat_id'], $message);
}
