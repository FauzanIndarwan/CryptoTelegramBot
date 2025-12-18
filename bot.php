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

/**
 * Handle cron job requests for market sentiment monitoring
 */
if (isset($_GET['cron']) && hash_equals(KUNCI_RAHASIA_CRON, $_GET['cron'])) {
    handleCronJob($telegram, $db);
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
            $quote = strtoupper($parts[2] ?? 'IDR');
            queueJob($db, $chatId, 'price', $base, $quote);
            $telegram->sendMessage($chatId, "⏳ Mengambil harga $base/$quote...");
            break;

        case '/chart':
            $base = strtoupper($parts[1] ?? 'BTC');
            $quote = strtoupper($parts[2] ?? 'IDR');
            queueJob($db, $chatId, 'chart', $base, $quote);
            $telegram->sendMessage($chatId, "⏳ Membuat chart untuk $base/$quote...");
            break;

        case '/chartdaily':
        case '/candlestick':
            $base = strtoupper($parts[1] ?? 'BTC');
            $quote = strtoupper($parts[2] ?? 'IDR');
            queueJob($db, $chatId, 'candlestick', $base, $quote);
            $telegram->sendMessage($chatId, "⏳ Membuat candlestick chart untuk $base/$quote...");
            break;

        case '/indicator':
        case '/stochrsi':
            $base = strtoupper($parts[1] ?? 'BTC');
            $quote = strtoupper($parts[2] ?? 'IDR');
            queueJob($db, $chatId, 'indicator', $base, $quote);
            $telegram->sendMessage($chatId, "⏳ Menghitung StochRSI untuk $base/$quote...");
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
    $message = "👋 Halo, $userName!\n\n";
    $message .= "🤖 *Crypto Telegram Bot*\n";
    $message .= "Monitor harga cryptocurrency dari Indodax\n\n";
    $message .= "📊 *Perintah yang Tersedia:*\n";
    $message .= "/harga BTC IDR - Cek harga terkini\n";
    $message .= "/chart ETH IDR - Line chart\n";
    $message .= "/chartdaily BTC IDR - Candlestick chart (30 hari)\n";
    $message .= "/indicator BTC IDR - Analisis Stochastic RSI\n";
    $message .= "/stop - Batalkan semua pekerjaan\n";
    $message .= "/help - Lihat panduan\n\n";
    $message .= "💡 *Contoh:* `/harga BTC IDR`\n";
    $message .= "📈 Mendukung semua pair IDR di Indodax!";

    $telegram->sendMessage($chatId, $message);
}

/**
 * Handle /help command
 */
function handleHelpCommand($telegram, $chatId) {
    $message = "📚 *Panduan Perintah*\n\n";
    $message .= "🔹 */harga [BASE] [QUOTE]*\n";
    $message .= "Cek harga real-time dan statistik 24 jam\n";
    $message .= "Contoh: `/harga BTC IDR`\n\n";
    
    $message .= "🔹 */chart [BASE] [QUOTE]*\n";
    $message .= "Buat line chart pergerakan harga\n";
    $message .= "Menampilkan data historis\n";
    $message .= "Contoh: `/chart ETH IDR`\n\n";
    
    $message .= "🔹 */chartdaily [BASE] [QUOTE]*\n";
    $message .= "Buat candlestick chart harian\n";
    $message .= "Menampilkan 30 hari terakhir\n";
    $message .= "Contoh: `/chartdaily BTC IDR`\n\n";
    
    $message .= "🔹 */indicator [BASE] [QUOTE]*\n";
    $message .= "Hitung indikator Stochastic RSI\n";
    $message .= "Memberikan sinyal beli/jual\n";
    $message .= "Contoh: `/indicator BTC IDR`\n\n";
    
    $message .= "🔹 */stop*\n";
    $message .= "Batalkan semua pekerjaan yang tertunda\n\n";
    
    $message .= "💡 *Tips:*\n";
    $message .= "• Quote currency default adalah IDR\n";
    $message .= "• Perintah tidak case-sensitive\n";
    $message .= "• Pair populer: BTC, ETH, XRP, TRX, DOGE";

    $telegram->sendMessage($chatId, $message);
}

/**
 * Queue a job for processing
 */
function queueJob($db, $chatId, $command, $base, $quote) {
    $symbol = IndodaxAPI::formatSymbol($base, $quote);
    
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
function handleCronJob($telegram, $db) {
    try {
        $tickers = IndodaxAPI::getTickers();
        
        if (!$tickers) {
            error_log("Failed to fetch tickers for cron job");
            return;
        }

        $moonCoins = [];
        $crashCoins = [];
        $threshold = AMBANG_BATAS_PERSENTASE;

        foreach ($tickers as $pair => $ticker) {
            // Skip if not IDR pair
            if (!str_contains($pair, 'idr')) {
                continue;
            }

            // Calculate 24h change percentage
            $last = floatval($ticker['last']);
            $high = floatval($ticker['high']);
            $low = floatval($ticker['low']);
            
            // Estimate change based on current position between high and low
            if ($high > 0 && $low > 0) {
                $midPrice = ($high + $low) / 2;
                if ($midPrice > 0) {
                    $priceChange = (($last - $midPrice) / $midPrice) * 100;
                    
                    $symbol = strtoupper(str_replace('idr', '_idr', $pair));
                    
                    if ($priceChange >= $threshold) {
                        $moonCoins[] = [
                            'symbol' => $symbol,
                            'change' => $priceChange
                        ];
                    } elseif ($priceChange <= -$threshold) {
                        $crashCoins[] = [
                            'symbol' => $symbol,
                            'change' => $priceChange
                        ];
                    }
                }
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
            $chatId = CHAT_ID_NOTIFIKASI;
            
            // Validate chat ID
            if (empty($chatId) || $chatId === 'YOUR_CHAT_ID') {
                error_log("Chat ID notifikasi tidak valid");
                return;
            }
            
            $message = "🔔 *Sentimen Pasar Alert*\n\n";
            
            if ($moonCount >= 10) {
                $message .= "🚀 *Pergerakan Bullish*\n";
                $message .= "{$moonSentiment['full_name']}\n";
                $message .= "Koin naik >{$threshold}%: $moonCount\n\n";
            }
            
            if ($crashCount >= 10) {
                $message .= "📉 *Pergerakan Bearish*\n";
                $message .= "{$crashSentiment['full_name']}\n";
                $message .= "Koin turun <-{$threshold}%: $crashCount\n";
            }

            $result = $telegram->sendMessage($chatId, $message);
            
            if (!$result) {
                error_log("Failed to send notification message");
            }
        }

    } catch (Exception $e) {
        error_log("Cron job error: " . $e->getMessage());
    }

    echo "Cron job completed";
}
