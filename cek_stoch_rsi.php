<?php
/**
 * Stochastic RSI Checker
 * 
 * Checks StochRSI values for monitored pairs and sends alerts for significant signals.
 * Run this script via cron every 4 hours.
 * 
 * Crontab entry example:
 * 0 *\/4 * * * php /path/to/cek_stoch_rsi.php
 * (Remove the backslash before the forward slash when adding to crontab)
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

// Initialize components
$telegram = new TelegramHelper();
$db = Database::getInstance();

// Get supported trading pairs
$supportedBases = ['BTC', 'ETH', 'XRP', 'TRX', 'DOGE', 'LTC', 'XLM', 'ADA', 'BNB', 'USDT'];
$quote = 'IDR';
$chatId = CHAT_ID_NOTIFIKASI;

echo "Starting StochRSI check...\n";

$signals = [];

foreach ($supportedBases as $base) {
    $symbol = IndodaxAPI::formatSymbol($base, $quote); // Returns 'BTC_IDR' format
    
    try {
        echo "Checking $symbol...\n";
        
        // Query historical closing prices dengan simbol UPPERCASE
        $query = "SELECT harga_tutup 
                  FROM data_historis_harian 
                  WHERE simbol = ?
                  ORDER BY waktu_buka DESC 
                  LIMIT 100";
        
        $stmt = $db->prepare($query, [$symbol], 's');
        
        if (!$stmt) {
            throw new Exception("Failed to prepare query");
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
            echo "  ⚠️  Insufficient data: " . count($closePrices) . " records\n";
            continue;
        }
        
        // Calculate StochRSI
        $stochRSI = Indicators::calculateStochRSI($closePrices, 14, 14, 3, 3);
        
        if (empty($stochRSI['k']) || empty($stochRSI['d'])) {
            echo "  ⚠️  Failed to calculate StochRSI\n";
            continue;
        }

        $latestK = end($stochRSI['k']);
        $latestD = end($stochRSI['d']);
        
        // Get signal interpretation
        $signal = Indicators::interpretStochRSI($latestK, $latestD);
        
        // Only report significant signals (oversold, overbought, or crossovers)
        if ($signal['condition'] !== 'Neutral') {
            $signals[] = [
                'symbol' => $symbol,
                'signal' => $signal
            ];
            
            echo "  ⚠️  Signal detected: {$signal['condition']}\n";
        }
        
        // Avoid rate limiting
        usleep(500000); // 0.5 seconds
        
    } catch (Exception $e) {
        echo "  ✗ Error checking $symbol: " . $e->getMessage() . "\n";
        error_log("StochRSI check error for $symbol: " . $e->getMessage());
    }
}

// Send notification if signals found
if (!empty($signals)) {
    sendSignalNotification($telegram, $chatId, $signals);
    echo "\n✓ Sent notification with " . count($signals) . " signal(s)\n";
} else {
    echo "\n✓ No significant signals detected\n";
}

exit(0);

/**
 * Send signal notification
 */
function sendSignalNotification($telegram, $chatId, $signals) {
    $message = "🔔 *StochRSI Alert*\n";
    $message .= "⏰ " . date('Y-m-d H:i:s') . "\n\n";
    
    // Group signals by condition
    $grouped = [];
    foreach ($signals as $s) {
        $condition = $s['signal']['condition'];
        if (!isset($grouped[$condition])) {
            $grouped[$condition] = [];
        }
        $grouped[$condition][] = $s;
    }
    
    foreach ($grouped as $condition => $items) {
        $emoji = $items[0]['signal']['emoji'];
        $message .= "$emoji *$condition*\n";
        
        foreach ($items as $item) {
            $symbol = $item['symbol'];
            $k = $item['signal']['k'];
            $d = $item['signal']['d'];
            $action = $item['signal']['action'];
            
            $message .= "• `$symbol`: K=$k, D=$d - $action\n";
        }
        
        $message .= "\n";
    }
    
    $telegram->sendMessage($chatId, $message);
}
