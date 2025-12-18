<?php
/**
 * Indodax API Wrapper Class
 * 
 * Provides methods to interact with Indodax API endpoints
 * with caching, retry logic, and error handling.
 */

class IndodaxAPI {
    private const BASE_URL = 'https://indodax.com/api';
    private const CACHE_TTL = 60;
    private static $cache = [];
    private static $curlHandle = null;
    
    /**
     * Get or initialize cURL handle
     */
    private static function getCurl() {
        if (self::$curlHandle === null) {
            self::$curlHandle = curl_init();
            curl_setopt_array(self::$curlHandle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['Accept: application/json']
            ]);
        }
        return self::$curlHandle;
    }
    
    /**
     * Make API request with caching and retry logic
     * 
     * @param string $endpoint API endpoint
     * @param bool $useCache Whether to use cache
     * @return array|null
     */
    private static function fetch(string $endpoint, bool $useCache = true): ?array {
        $cacheKey = md5($endpoint);
        
        // Check cache
        if ($useCache && isset(self::$cache[$cacheKey])) {
            if (time() - self::$cache[$cacheKey]['time'] < self::CACHE_TTL) {
                return self::$cache[$cacheKey]['data'];
            }
        }
        
        $ch = self::getCurl();
        curl_setopt($ch, CURLOPT_URL, self::BASE_URL . $endpoint);
        
        // Retry logic with exponential backoff
        for ($i = 0; $i < 3; $i++) {
            $response = curl_exec($ch);
            
            if ($response !== false) {
                $data = json_decode($response, true);
                if ($data !== null) {
                    // Cache successful result
                    self::$cache[$cacheKey] = ['data' => $data, 'time' => time()];
                    return $data;
                }
            }
            
            // Wait before retry (100ms, 200ms, 400ms)
            usleep(100000 * ($i + 1));
        }
        
        error_log("IndodaxAPI fetch failed: " . $endpoint);
        return null;
    }
    
    /**
     * Get all tickers
     * 
     * @return array|null Array of tickers
     */
    public static function getTickers(): ?array {
        $data = self::fetch('/tickers');
        return $data['tickers'] ?? null;
    }
    
    /**
     * Get ticker for specific pair
     * 
     * @param string $pair Trading pair (e.g., 'btc_idr')
     * @return array|null
     */
    public static function getPrice(string $pair): ?array {
        $pair = strtolower($pair);
        $data = self::fetch("/ticker/{$pair}");
        return $data['ticker'] ?? null;
    }
    
    /**
     * Get daily OHLC data for a pair
     * 
     * @param string $pair Trading pair (e.g., 'btc_idr')
     * @return array|null
     */
    public static function getDailyOHLC(string $pair): ?array {
        $pair = strtolower($pair);
        $data = self::fetch("/v2/ohcl/{$pair}?period=D", false);
        return is_array($data) && !empty($data) ? $data : null;
    }
    
    /**
     * Format symbol for Indodax (e.g., 'BTC', 'IDR' -> 'BTC_IDR')
     * 
     * @param string $base Base currency
     * @param string $quote Quote currency (default: 'IDR')
     * @return string
     */
    public static function formatSymbol(string $base, string $quote = 'IDR'): string {
        // Sanitize inputs - only allow alphanumeric characters
        $base = preg_replace('/[^A-Za-z0-9]/', '', $base);
        $quote = preg_replace('/[^A-Za-z0-9]/', '', $quote);
        
        return strtoupper($base) . '_' . strtoupper($quote);
    }
    
    /**
     * Parse symbol to get base and quote
     * 
     * @param string $symbol Symbol like 'BTC_IDR'
     * @return array Array with 'base' and 'quote'
     */
    public static function parseSymbol(string $symbol): array {
        $parts = explode('_', $symbol);
        return [
            'base' => strtoupper($parts[0] ?? ''),
            'quote' => strtoupper($parts[1] ?? 'IDR')
        ];
    }
    
    /**
     * Get supported pairs from Indodax
     * 
     * @return array Array of supported pairs
     */
    public static function getSupportedPairs(): array {
        $tickers = self::getTickers();
        if (!$tickers) {
            return [];
        }
        
        $pairs = [];
        foreach ($tickers as $pair => $data) {
            $pairs[] = strtoupper(str_replace('idr', '_idr', $pair));
        }
        
        return $pairs;
    }
    
    /**
     * Clear cache
     */
    public static function clearCache(): void {
        self::$cache = [];
    }
    
    /**
     * Close cURL handle
     */
    public static function close(): void {
        if (self::$curlHandle !== null) {
            curl_close(self::$curlHandle);
            self::$curlHandle = null;
        }
    }
}
