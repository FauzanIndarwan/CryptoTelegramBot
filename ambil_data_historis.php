<?php
/**
 * Historical Data Fetcher
 * 
 * Fetches historical OHLC data from Binance and stores it in the database.
 * Run this script via cron daily: 5 0 * * * php /path/to/ambil_data_historis.php
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
require_once __DIR__ . '/BinanceAPI.php';

// Initialize components
$binance = new BinanceAPI();
$db = Database::getInstance();

// Get supported trading pairs
$supportedBases = $config['pairs']['supported_bases'];
$quote = $config['pairs']['default_quote'];

echo "Starting historical data fetch...\n";

$successCount = 0;
$errorCount = 0;

foreach ($supportedBases as $base) {
    $symbol = BinanceAPI::formatSymbol($base, $quote);
    
    try {
        echo "Fetching data for $symbol...\n";
        
        // Fetch daily klines for last 365 days
        $klines = $binance->getKlines($symbol, '1d', 365);
        
        if (!$klines) {
            throw new Exception("Failed to fetch klines for $symbol");
        }

        // Insert data into database
        $insertedCount = insertHistoricalData($db, $symbol, $klines);
        
        echo "  ✓ Inserted/updated $insertedCount candles for $symbol\n";
        $successCount++;
        
        // Avoid rate limiting
        sleep(1);
        
    } catch (Exception $e) {
        echo "  ✗ Error processing $symbol: " . $e->getMessage() . "\n";
        error_log("Historical data fetch error for $symbol: " . $e->getMessage());
        $errorCount++;
    }
}

echo "\n=== Summary ===\n";
echo "Successful: $successCount pairs\n";
echo "Errors: $errorCount pairs\n";
echo "Total: " . count($supportedBases) . " pairs\n";

exit($errorCount > 0 ? 1 : 0);

/**
 * Insert historical data into database
 */
function insertHistoricalData($db, $symbol, $klines) {
    $insertedCount = 0;
    
    foreach ($klines as $kline) {
        $timestamp = $kline[0];
        $open = floatval($kline[1]);
        $high = floatval($kline[2]);
        $low = floatval($kline[3]);
        $close = floatval($kline[4]);
        $volume = floatval($kline[5]);
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE to avoid duplicates
        $query = "INSERT INTO data_historis_harian 
                  (simbol, waktu_buka, harga_buka, harga_tertinggi, harga_terendah, harga_tutup, volume)
                  VALUES (?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE
                  harga_buka = VALUES(harga_buka),
                  harga_tertinggi = VALUES(harga_tertinggi),
                  harga_terendah = VALUES(harga_terendah),
                  harga_tutup = VALUES(harga_tutup),
                  volume = VALUES(volume)";
        
        $stmt = $db->prepare(
            $query,
            [$symbol, $timestamp, $open, $high, $low, $close, $volume],
            'sidddd'
        );
        
        if ($stmt) {
            $stmt->execute();
            $stmt->close();
            $insertedCount++;
        }
    }
    
    return $insertedCount;
}

/**
 * Create historical data table if not exists
 */
function ensureTableExists($db) {
    $query = "CREATE TABLE IF NOT EXISTS data_historis_harian (
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
    )";
    
    $db->query($query);
}

// Ensure table exists before starting
ensureTableExists($db);
