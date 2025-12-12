<?php
/**
 * Telegram API Helper Class
 * 
 * Provides methods to interact with Telegram Bot API
 * with reusable cURL handle for better performance.
 */

class TelegramHelper {
    private $botToken;
    private $baseUrl;
    private $curlHandle;

    /**
     * Constructor
     */
    public function __construct() {
        $config = require __DIR__ . '/config.php';
        $this->botToken = $config['telegram']['bot_token'];
        $this->baseUrl = "https://api.telegram.org/bot{$this->botToken}/";
        
        // Initialize persistent cURL handle
        $this->curlHandle = curl_init();
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($this->curlHandle, CURLOPT_TIMEOUT, 30);
    }

    /**
     * Make API request to Telegram
     * 
     * @param string $method API method name
     * @param array $params Parameters to send
     * @return array|null
     */
    private function request($method, $params = []) {
        $url = $this->baseUrl . $method;

        curl_setopt($this->curlHandle, CURLOPT_URL, $url);
        curl_setopt($this->curlHandle, CURLOPT_POST, true);
        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, http_build_query($params));

        $response = curl_exec($this->curlHandle);

        if (curl_errno($this->curlHandle)) {
            error_log("Telegram API cURL error: " . curl_error($this->curlHandle));
            return null;
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['ok']) || !$data['ok']) {
            error_log("Telegram API error: " . ($data['description'] ?? 'Unknown error'));
            return null;
        }

        return $data['result'] ?? null;
    }

    /**
     * Send text message
     * 
     * @param string $chatId Chat ID
     * @param string $text Message text
     * @param string $parseMode Parse mode (Markdown, HTML, or null)
     * @param array $replyMarkup Keyboard markup
     * @return array|null
     */
    public function sendMessage($chatId, $text, $parseMode = 'Markdown', $replyMarkup = null) {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request('sendMessage', $params);
    }

    /**
     * Send photo
     * 
     * @param string $chatId Chat ID
     * @param string $photo Photo URL or file_id
     * @param string $caption Caption text
     * @param string $parseMode Parse mode
     * @return array|null
     */
    public function sendPhoto($chatId, $photo, $caption = '', $parseMode = 'Markdown') {
        $params = [
            'chat_id' => $chatId,
            'photo' => $photo,
        ];

        if ($caption) {
            $params['caption'] = $caption;
            $params['parse_mode'] = $parseMode;
        }

        return $this->request('sendPhoto', $params);
    }

    /**
     * Send chat action (typing, upload_photo, etc.)
     * 
     * @param string $chatId Chat ID
     * @param string $action Action type
     * @return array|null
     */
    public function sendChatAction($chatId, $action = 'typing') {
        return $this->request('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action
        ]);
    }

    /**
     * Answer callback query
     * 
     * @param string $callbackQueryId Callback query ID
     * @param string $text Text to show
     * @param bool $showAlert Show as alert
     * @return array|null
     */
    public function answerCallbackQuery($callbackQueryId, $text = '', $showAlert = false) {
        $params = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text) {
            $params['text'] = $text;
        }

        if ($showAlert) {
            $params['show_alert'] = true;
        }

        return $this->request('answerCallbackQuery', $params);
    }

    /**
     * Edit message text
     * 
     * @param string $chatId Chat ID
     * @param int $messageId Message ID
     * @param string $text New text
     * @param string $parseMode Parse mode
     * @param array $replyMarkup Reply markup
     * @return array|null
     */
    public function editMessageText($chatId, $messageId, $text, $parseMode = 'Markdown', $replyMarkup = null) {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ];

        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request('editMessageText', $params);
    }

    /**
     * Delete message
     * 
     * @param string $chatId Chat ID
     * @param int $messageId Message ID
     * @return array|null
     */
    public function deleteMessage($chatId, $messageId) {
        return $this->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }

    /**
     * Get updates
     * 
     * @param int $offset Update offset
     * @param int $limit Number of updates
     * @param int $timeout Long polling timeout
     * @return array|null
     */
    public function getUpdates($offset = 0, $limit = 100, $timeout = 0) {
        return $this->request('getUpdates', [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => $timeout
        ]);
    }

    /**
     * Format price with proper decimal places
     * 
     * @param float $price Price value
     * @param int $decimals Decimal places
     * @return string
     */
    public static function formatPrice($price, $decimals = 2) {
        if ($price >= 1000) {
            return number_format($price, $decimals);
        } elseif ($price >= 1) {
            return number_format($price, 4);
        } else {
            return number_format($price, 8);
        }
    }

    /**
     * Format percentage change
     * 
     * @param float $change Percentage change
     * @return string
     */
    public static function formatPercentage($change) {
        $emoji = $change >= 0 ? '🟢' : '🔴';
        $sign = $change >= 0 ? '+' : '';
        return $emoji . ' ' . $sign . number_format($change, 2) . '%';
    }

    /**
     * Destructor - close cURL handle
     */
    public function __destruct() {
        if ($this->curlHandle) {
            curl_close($this->curlHandle);
        }
    }
}
