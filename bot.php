<?php
/**
 * Crypto Telegram Bot - Main Entry Point
 * 
 * Handles Telegram webhook requests and manages bot commands.
 * This file processes incoming messages and queues jobs for the worker.
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

/**
 * Handle cron job requests for market sentiment monitoring
 */
if (isset($_GET['cron']) && hash_equals($config['cron']['secret_key'], $_GET['cron'])) {
    handleCronJob($telegram, $binance, $db, $config);
    exit;
}

/**
 * Handle Telegram webhook
 */
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    http_response_code(200);
    exit;
}

// Extract message data
$message = $update['message'] ?? null;
$callbackQuery = $update['callback_query'] ?? null;

if ($message) {
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    // Sanitize userName to prevent Markdown injection
    $userName = preg_replace('/[*_`\[\]]/', '', $message['from']['first_name'] ?? 'User');

    // Parse command
    $parts = explode(' ', trim($text));
    $command = strtolower($parts[0]);

    switch ($command) {
        case '/start':
            handleStartCommand($telegram, $chatId, $userName);
            break;

        case '/harga':
        case '/price':
            $base = strtoupper($parts[1] ?? 'BTC');
            $quote = strtoupper($parts[2] ?? 'USDT');
            queueJob($db, $chatId, 'price', $base, $quote);
            $telegram->sendMessage($chatId, "⏳ Fetching price for $base/$quote...");
            break;

        case '/chart':
            $base = strtoupper($parts[1] ?? 'BTC');
            $quote = strtoupper($parts[2] ?? 'USDT');
            queueJob($db, $chatId, 'chart', $base, $quote);
            $telegram->sendMessage($chatId, "⏳ Generating chart for $base/$quote...");
            break;

        case '/chartdaily':
        case '/candlestick':
            $base = strtoupper($parts[1] ?? 'BTC');
            $quote = strtoupper($parts[2] ?? 'USDT');
            queueJob($db, $chatId, 'candlestick', $base, $quote);
            $telegram->sendMessage($chatId, "⏳ Generating candlestick chart for $base/$quote...");
            break;

        case '/indicator':
        case '/stochrsi':
            $base = strtoupper($parts[1] ?? 'BTC');
            $quote = strtoupper($parts[2] ?? 'USDT');
            queueJob($db, $chatId, 'indicator', $base, $quote);
            $telegram->sendMessage($chatId, "⏳ Calculating StochRSI for $base/$quote...");
            break;

        case '/stop':
        case '/cancel':
            cancelJobs($db, $chatId);
            $telegram->sendMessage($chatId, "✅ All pending jobs have been cancelled.");
            break;

        case '/help':
            handleHelpCommand($telegram, $chatId);
            break;

        default:
            if (strpos($text, '/') === 0) {
                $telegram->sendMessage($chatId, "❌ Unknown command. Use /help to see available commands.");
            }
            break;
    }
}

http_response_code(200);

/**
 * Handle /start command
 */
function handleStartCommand($telegram, $chatId, $userName) {
    $message = "👋 Hello, $userName!\n\n";
    $message .= "🤖 *Crypto Telegram Bot*\n";
    $message .= "Monitor cryptocurrency prices from Binance\n\n";
    $message .= "📊 *Available Commands:*\n";
    $message .= "/harga BTC USDT - Get current price\n";
    $message .= "/chart ETH USDT - Line chart (1 hour)\n";
    $message .= "/chartdaily BTC USDT - Candlestick chart (30 days)\n";
    $message .= "/indicator BTC USDT - Stochastic RSI analysis\n";
    $message .= "/stop - Cancel all pending jobs\n";
    $message .= "/help - Show this help message\n\n";
    $message .= "💡 *Example:* `/harga BTC USDT`\n";
    $message .= "📈 Supports all Binance USDT pairs!";

    $telegram->sendMessage($chatId, $message);
}

/**
 * Handle /help command
 */
function handleHelpCommand($telegram, $chatId) {
    $message = "📚 *Command Guide*\n\n";
    $message .= "🔹 */harga [BASE] [QUOTE]*\n";
    $message .= "Get real-time price and 24h statistics\n";
    $message .= "Example: `/harga BTC USDT`\n\n";
    
    $message .= "🔹 */chart [BASE] [QUOTE]*\n";
    $message .= "Generate 5-minute interval line chart\n";
    $message .= "Shows last hour of price movement\n";
    $message .= "Example: `/chart ETH USDT`\n\n";
    
    $message .= "🔹 */chartdaily [BASE] [QUOTE]*\n";
    $message .= "Generate daily candlestick chart\n";
    $message .= "Shows last 30 days of trading\n";
    $message .= "Example: `/chartdaily BNB USDT`\n\n";
    
    $message .= "🔹 */indicator [BASE] [QUOTE]*\n";
    $message .= "Calculate Stochastic RSI indicator\n";
    $message .= "Provides buy/sell signals\n";
    $message .= "Example: `/indicator SOL USDT`\n\n";
    
    $message .= "🔹 */stop*\n";
    $message .= "Cancel all your pending jobs\n\n";
    
    $message .= "💡 *Tips:*\n";
    $message .= "• Default quote currency is USDT\n";
    $message .= "• Commands are case-insensitive\n";
    $message .= "• Most popular pairs: BTC, ETH, BNB, SOL, XRP";

    $telegram->sendMessage($chatId, $message);
}

/**
 * Queue a job for processing
 */
function queueJob($db, $chatId, $command, $base, $quote) {
    $symbol = BinanceAPI::formatSymbol($base, $quote);
    
    $stmt = $db->prepare(
        "INSERT INTO bot_job_queue (chat_id, command, pair, status) VALUES (?, ?, ?, 'pending')",
        [$chatId, $command, $symbol],
        'sss'
    );
    
    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Cancel all pending jobs for a chat
 */
function cancelJobs($db, $chatId) {
    $stmt = $db->prepare(
        "UPDATE bot_job_queue SET status = 'cancelled' WHERE chat_id = ? AND status = 'pending'",
        [$chatId],
        's'
    );
    
    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Handle cron job for market sentiment monitoring
 */
function handleCronJob($telegram, $binance, $db, $config) {
    try {
        $tickers = $binance->getAllTickers();
        
        if (!$tickers) {
            error_log("Failed to fetch tickers for cron job");
            return;
        }

        $moonCoins = [];
        $crashCoins = [];
        $threshold = $config['monitoring']['price_change_threshold'];

        foreach ($tickers as $ticker) {
            // Only process USDT pairs
            if (substr($ticker['symbol'], -4) !== 'USDT') {
                continue;
            }

            $priceChange = floatval($ticker['priceChangePercent']);

            if ($priceChange >= $threshold) {
                $moonCoins[] = [
                    'symbol' => $ticker['symbol'],
                    'change' => $priceChange
                ];
            } elseif ($priceChange <= -$threshold) {
                $crashCoins[] = [
                    'symbol' => $ticker['symbol'],
                    'change' => $priceChange
                ];
            }
        }

        $moonCount = count($moonCoins);
        $crashCount = count($crashCoins);

        // Determine sentiment levels
        $moonSentiment = Indicators::getMarketSentiment($moonCount, true);
        $crashSentiment = Indicators::getMarketSentiment($crashCount, false);

        // Save to database
        $stmt = $db->prepare(
            "INSERT INTO laporan_sentimen_pasar (moon_count, moon_level, crash_count, crash_level) VALUES (?, ?, ?, ?)",
            [$moonCount, $moonSentiment['full_name'], $crashCount, $crashSentiment['full_name']],
            'isis'
        );

        if ($stmt) {
            $stmt->execute();
            $stmt->close();
        }

        // Send notification if significant movement
        if ($moonCount >= 10 || $crashCount >= 10) {
            $chatId = $config['telegram']['chat_id_notifikasi'];
            $message = "🔔 *Market Sentiment Alert*\n\n";
            
            if ($moonCount >= 10) {
                $message .= "🚀 *Bullish Movement*\n";
                $message .= "{$moonSentiment['full_name']}\n";
                $message .= "Coins up >{$threshold}%: $moonCount\n\n";
            }
            
            if ($crashCount >= 10) {
                $message .= "📉 *Bearish Movement*\n";
                $message .= "{$crashSentiment['full_name']}\n";
                $message .= "Coins down <-{$threshold}%: $crashCount\n";
            }

            $telegram->sendMessage($chatId, $message);
        }

    } catch (Exception $e) {
        error_log("Cron job error: " . $e->getMessage());
    }

    echo "Cron job completed";
}
