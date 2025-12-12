<?php
/**
 * Stochastic RSI Checker
 * 
 * Checks StochRSI values for monitored pairs and sends alerts for significant signals.
 * Run this script via cron every 4 hours: 0 star/4 * * * php /path/to/cek_stoch_rsi.php
 * (replace "star" with asterisk symbol)
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

// Initialize components
$telegram = new TelegramHelper();
$binance = new BinanceAPI();
$db = Database::getInstance();

// Get supported trading pairs
$supportedBases = $config['pairs']['supported_bases'];
$quote = $config['pairs']['default_quote'];
$chatId = $config['telegram']['chat_id_notifikasi'];

echo "Starting StochRSI check...\n";

$signals = [];

foreach ($supportedBases as $base) {
    $symbol = BinanceAPI::formatSymbol($base, $quote);
    
    try {
        echo "Checking $symbol...\n";
        
        // Get 4-hour klines
        $klines = $binance->getKlines($symbol, '4h', 100);
        
        if (!$klines) {
            throw new Exception("Failed to fetch klines");
        }

        $ohlc = $binance->parseOHLC($klines);
        $closePrices = array_column($ohlc, 'close');
        
        // Calculate StochRSI
        $stochRSI = Indicators::calculateStochRSI($closePrices, 14, 14, 3, 3);
        
        if (empty($stochRSI['k']) || empty($stochRSI['d'])) {
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
