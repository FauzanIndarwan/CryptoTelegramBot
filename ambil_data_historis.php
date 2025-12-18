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
require_once __DIR__ . '/config.php';
date_default_timezone_set('Asia/Jakarta');

// Load dependencies
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/IndodaxAPI.php';

// Initialize components
$db = Database::getInstance();

// Get supported trading pairs from Indodax
$supportedBases = ['BTC', 'ETH', 'XRP', 'TRX', 'DOGE', 'LTC', 'XLM', 'ADA', 'BNB', 'USDT'];
$quote = 'IDR';

echo "Starting historical data fetch...\n";

$successCount = 0;
$errorCount = 0;

foreach ($supportedBases as $base) {
    $symbol = IndodaxAPI::formatSymbol($base, $quote); // Returns 'BTC_IDR' format
    $symbolLower = strtolower(str_replace('_', '', $symbol)); // 'btcidr' for API
    
    try {
        echo "Fetching data for $symbol...\n";
        
        // Fetch daily OHLC data from Indodax
        $ohlcData = IndodaxAPI::getDailyOHLC($symbolLower);
        
        if (!$ohlcData) {
            throw new Exception("Failed to fetch OHLC data for $symbol");
        }

        // Validate data
        if (!is_array($ohlcData) || empty($ohlcData)) {
            throw new Exception("Empty or invalid OHLC data for $symbol");
        }

        // Insert data into database with UPPERCASE symbol
        $insertedCount = insertHistoricalData($db, $symbol, $ohlcData);
        
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
 * 
 * @param Database $db Database instance
 * @param string $symbol Symbol in UPPERCASE format (e.g., 'BTC_IDR')
 * @param array $ohlcData OHLC data from Indodax API
 * @return int Number of inserted/updated records
 */
function insertHistoricalData($db, $symbol, $ohlcData) {
    $insertedCount = 0;
    
    foreach ($ohlcData as $candle) {
        // Indodax OHLC format: [timestamp, open, high, low, close, volume]
        if (!is_array($candle) || count($candle) < 6) {
            continue;
        }
        
        $timestamp = (int)$candle[0];
        $open = floatval($candle[1]);
        $high = floatval($candle[2]);
        $low = floatval($candle[3]);
        $close = floatval($candle[4]);
        $volume = floatval($candle[5]);
        
        // Validate data
        if ($timestamp <= 0 || $open <= 0 || $high <= 0 || $low <= 0 || $close <= 0) {
            continue;
        }
        
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
            'sidddd d'
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
