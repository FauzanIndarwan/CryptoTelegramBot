<?php
/**
 * Binance API Wrapper Class
 * 
 * Provides methods to interact with Binance API endpoints
 * with caching, retry logic, and error handling.
 */

class BinanceAPI {
    private $baseUrl;
    private $apiKey;
    private $apiSecret;
    private $cacheDuration;
    private $cache = [];
    private $curlHandle;

    /**
     * Constructor
     */
    public function __construct() {
        $config = require __DIR__ . '/config.php';
        $binanceConfig = $config['binance'];
        
        $this->baseUrl = $binanceConfig['base_url'];
        $this->apiKey = $binanceConfig['api_key'];
        $this->apiSecret = $binanceConfig['api_secret'];
        $this->cacheDuration = $binanceConfig['cache_duration'];
        
        // Initialize persistent cURL handle
        $this->curlHandle = curl_init();
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($this->curlHandle, CURLOPT_TIMEOUT, 30);
    }

    /**
     * Make API request with caching and retry logic
     * 
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param bool $useCache Whether to use cache
     * @param int $maxRetries Maximum retry attempts
     * @return array|null
     */
    private function request($endpoint, $params = [], $useCache = true, $maxRetries = 3) {
        $url = $this->baseUrl . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        // Check cache
        $cacheKey = md5($url);
        if ($useCache && isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if (time() - $cached['time'] < $this->cacheDuration) {
                return $cached['data'];
            }
        }

        // Make request with retry logic
        $attempt = 0;
        $lastError = null;

        while ($attempt < $maxRetries) {
            try {
                curl_setopt($this->curlHandle, CURLOPT_URL, $url);
                
                $response = curl_exec($this->curlHandle);
                $httpCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

                if (curl_errno($this->curlHandle)) {
                    throw new Exception(curl_error($this->curlHandle));
                }

                if ($httpCode !== 200) {
                    throw new Exception("HTTP Error: $httpCode");
                }

                $data = json_decode($response, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("JSON decode error: " . json_last_error_msg());
                }

                // Cache the result
                if ($useCache) {
                    $this->cache[$cacheKey] = [
                        'data' => $data,
                        'time' => time()
                    ];
                }

                return $data;

            } catch (Exception $e) {
                $lastError = $e->getMessage();
                $attempt++;
                
                if ($attempt < $maxRetries) {
                    // Exponential backoff
                    sleep(pow(2, $attempt - 1));
                }
            }
        }

        error_log("Binance API request failed after $maxRetries attempts: $lastError");
        return null;
    }

    /**
     * Get current price for a symbol
     * 
     * @param string $symbol Trading pair (e.g., 'BTCUSDT')
     * @return array|null
     */
    public function getPrice($symbol) {
        return $this->request('/api/v3/ticker/price', ['symbol' => strtoupper($symbol)]);
    }

    /**
     * Get 24hr ticker statistics
     * 
     * @param string $symbol Trading pair (e.g., 'BTCUSDT')
     * @return array|null
     */
    public function get24hrTicker($symbol) {
        return $this->request('/api/v3/ticker/24hr', ['symbol' => strtoupper($symbol)]);
    }

    /**
     * Get all 24hr ticker statistics
     * 
     * @return array|null
     */
    public function getAllTickers() {
        return $this->request('/api/v3/ticker/24hr');
    }

    /**
     * Get kline/candlestick data
     * 
     * @param string $symbol Trading pair
     * @param string $interval Kline interval (1m, 5m, 1h, 1d, etc.)
     * @param int $limit Number of klines to retrieve
     * @param int|null $startTime Start time in milliseconds
     * @param int|null $endTime End time in milliseconds
     * @return array|null
     */
    public function getKlines($symbol, $interval = '5m', $limit = 100, $startTime = null, $endTime = null) {
        $params = [
            'symbol' => strtoupper($symbol),
            'interval' => $interval,
            'limit' => $limit
        ];

        if ($startTime !== null) {
            $params['startTime'] = $startTime;
        }
        if ($endTime !== null) {
            $params['endTime'] = $endTime;
        }

        return $this->request('/api/v3/klines', $params);
    }

    /**
     * Get exchange information
     * 
     * @return array|null
     */
    public function getExchangeInfo() {
        return $this->request('/api/v3/exchangeInfo', [], true);
    }

    /**
     * Get all available trading symbols with USDT quote
     * 
     * @return array
     */
    public function getUSDTSymbols() {
        $exchangeInfo = $this->getExchangeInfo();
        $symbols = [];

        if ($exchangeInfo && isset($exchangeInfo['symbols'])) {
            foreach ($exchangeInfo['symbols'] as $symbol) {
                if ($symbol['quoteAsset'] === 'USDT' && $symbol['status'] === 'TRADING') {
                    $symbols[] = [
                        'symbol' => $symbol['symbol'],
                        'baseAsset' => $symbol['baseAsset'],
                        'quoteAsset' => $symbol['quoteAsset']
                    ];
                }
            }
        }

        return $symbols;
    }

    /**
     * Format symbol for Binance (e.g., 'BTC', 'USDT' -> 'BTCUSDT')
     * 
     * @param string $base Base currency
     * @param string $quote Quote currency
     * @return string
     */
    public static function formatSymbol($base, $quote) {
        // Sanitize inputs - only allow alphanumeric characters
        $base = preg_replace('/[^A-Za-z0-9]/', '', $base);
        $quote = preg_replace('/[^A-Za-z0-9]/', '', $quote);
        
        return strtoupper($base) . strtoupper($quote);
    }

    /**
     * Parse OHLC data from klines
     * 
     * @param array $klines Kline data from API
     * @return array
     */
    public function parseOHLC($klines) {
        $ohlc = [];

        foreach ($klines as $kline) {
            $ohlc[] = [
                'timestamp' => $kline[0],
                'open' => floatval($kline[1]),
                'high' => floatval($kline[2]),
                'low' => floatval($kline[3]),
                'close' => floatval($kline[4]),
                'volume' => floatval($kline[5]),
                'close_time' => $kline[6]
            ];
        }

        return $ohlc;
    }

    /**
     * Clear cache
     */
    public function clearCache() {
        $this->cache = [];
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
