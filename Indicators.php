<?php
/**
 * Technical Indicators Class
 * 
 * Provides calculation methods for technical indicators
 * used in cryptocurrency market analysis.
 */

class Indicators {
    
    /**
     * Calculate Relative Strength Index (RSI)
     * 
     * @param array $prices Array of closing prices
     * @param int $period Period for RSI calculation (default: 14)
     * @return array Array of RSI values
     */
    public static function calculateRSI($prices, $period = 14) {
        if (count($prices) < $period + 1) {
            return [];
        }

        $gains = [];
        $losses = [];

        // Calculate price changes
        for ($i = 1; $i < count($prices); $i++) {
            $change = $prices[$i] - $prices[$i - 1];
            $gains[] = max($change, 0);
            $losses[] = abs(min($change, 0));
        }

        $rsi = [];
        
        // Calculate initial average gain and loss
        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        // Calculate first RSI value
        if ($avgLoss != 0) {
            $rs = $avgGain / $avgLoss;
            $rsi[] = 100 - (100 / (1 + $rs));
        } else {
            $rsi[] = 100;
        }

        // Calculate subsequent RSI values
        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = (($avgGain * ($period - 1)) + $gains[$i]) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $losses[$i]) / $period;

            if ($avgLoss != 0) {
                $rs = $avgGain / $avgLoss;
                $rsi[] = 100 - (100 / (1 + $rs));
            } else {
                $rsi[] = 100;
            }
        }

        return $rsi;
    }

    /**
     * Calculate Stochastic RSI
     * 
     * @param array $prices Array of closing prices
     * @param int $rsiPeriod RSI period (default: 14)
     * @param int $stochPeriod Stochastic period (default: 14)
     * @param int $smoothK Smooth K period (default: 3)
     * @param int $smoothD Smooth D period (default: 3)
     * @return array Array with 'k' and 'd' values
     */
    public static function calculateStochRSI($prices, $rsiPeriod = 14, $stochPeriod = 14, $smoothK = 3, $smoothD = 3) {
        // Calculate RSI first
        $rsiValues = self::calculateRSI($prices, $rsiPeriod);
        
        if (count($rsiValues) < $stochPeriod) {
            return ['k' => [], 'd' => []];
        }

        $stochRSI = [];

        // Calculate Stochastic of RSI
        for ($i = $stochPeriod - 1; $i < count($rsiValues); $i++) {
            $rsiSlice = array_slice($rsiValues, $i - $stochPeriod + 1, $stochPeriod);
            $maxRSI = max($rsiSlice);
            $minRSI = min($rsiSlice);

            if ($maxRSI - $minRSI != 0) {
                $stochRSI[] = (($rsiValues[$i] - $minRSI) / ($maxRSI - $minRSI)) * 100;
            } else {
                $stochRSI[] = 0;
            }
        }

        // Smooth K line (SMA of StochRSI)
        $k = self::calculateSMA($stochRSI, $smoothK);

        // Smooth D line (SMA of K)
        $d = self::calculateSMA($k, $smoothD);

        return ['k' => $k, 'd' => $d];
    }

    /**
     * Calculate Simple Moving Average (SMA)
     * 
     * @param array $values Array of values
     * @param int $period Period for SMA
     * @return array Array of SMA values
     */
    public static function calculateSMA($values, $period) {
        if (count($values) < $period) {
            return [];
        }

        $sma = [];

        for ($i = $period - 1; $i < count($values); $i++) {
            $slice = array_slice($values, $i - $period + 1, $period);
            $sma[] = array_sum($slice) / $period;
        }

        return $sma;
    }

    /**
     * Calculate Exponential Moving Average (EMA)
     * 
     * @param array $values Array of values
     * @param int $period Period for EMA
     * @return array Array of EMA values
     */
    public static function calculateEMA($values, $period) {
        if (count($values) < $period) {
            return [];
        }

        $ema = [];
        $multiplier = 2 / ($period + 1);

        // First EMA is SMA
        $sma = array_sum(array_slice($values, 0, $period)) / $period;
        $ema[] = $sma;

        // Calculate subsequent EMA values
        for ($i = $period; $i < count($values); $i++) {
            $ema[] = (($values[$i] - $ema[count($ema) - 1]) * $multiplier) + $ema[count($ema) - 1];
        }

        return $ema;
    }

    /**
     * Interpret StochRSI signal
     * 
     * @param float $kValue Current K value
     * @param float $dValue Current D value
     * @return array Signal information
     */
    public static function interpretStochRSI($kValue, $dValue) {
        $signal = [
            'condition' => '',
            'action' => '',
            'emoji' => '',
            'description' => ''
        ];

        if ($kValue < 20 && $dValue < 20) {
            $signal['condition'] = 'Oversold';
            $signal['action'] = 'Potential BUY';
            $signal['emoji'] = '🟢';
            $signal['description'] = 'Market is oversold, potential buying opportunity';
        } elseif ($kValue > 80 && $dValue > 80) {
            $signal['condition'] = 'Overbought';
            $signal['action'] = 'Potential SELL';
            $signal['emoji'] = '🔴';
            $signal['description'] = 'Market is overbought, potential selling opportunity';
        } elseif ($kValue > $dValue && $kValue < 50) {
            $signal['condition'] = 'Bullish Crossover';
            $signal['action'] = 'BUY Signal';
            $signal['emoji'] = '🚀';
            $signal['description'] = 'K line crossed above D line, bullish signal';
        } elseif ($kValue < $dValue && $kValue > 50) {
            $signal['condition'] = 'Bearish Crossover';
            $signal['action'] = 'SELL Signal';
            $signal['emoji'] = '📉';
            $signal['description'] = 'K line crossed below D line, bearish signal';
        } else {
            $signal['condition'] = 'Neutral';
            $signal['action'] = 'HOLD';
            $signal['emoji'] = '⚪';
            $signal['description'] = 'No clear signal, hold position';
        }

        $signal['k'] = round($kValue, 2);
        $signal['d'] = round($dValue, 2);

        return $signal;
    }

    /**
     * Calculate price change percentage
     * 
     * @param float $oldPrice Old price
     * @param float $newPrice New price
     * @return float Percentage change
     */
    public static function calculatePriceChange($oldPrice, $newPrice) {
        if ($oldPrice == 0) {
            return 0;
        }
        return (($newPrice - $oldPrice) / $oldPrice) * 100;
    }

    /**
     * Determine market sentiment level
     * 
     * @param int $count Number of coins with significant change
     * @param bool $isMoon True for moon (bullish), false for crash (bearish)
     * @return array Sentiment information
     */
    public static function getMarketSentiment($count, $isMoon = true) {
        $levels = [
            ['threshold' => 121, 'name' => 'Diamond', 'level' => '', 'emoji' => '💎'],
            ['threshold' => 111, 'name' => 'Golden', 'level' => '2', 'emoji' => '🥇'],
            ['threshold' => 101, 'name' => 'Golden', 'level' => '1', 'emoji' => '🥇'],
            ['threshold' => 91, 'name' => 'Ultra', 'level' => '2', 'emoji' => '🔥'],
            ['threshold' => 81, 'name' => 'Ultra', 'level' => '1', 'emoji' => '🔥'],
            ['threshold' => 71, 'name' => 'Mega', 'level' => '2', 'emoji' => '⚡'],
            ['threshold' => 61, 'name' => 'Mega', 'level' => '1', 'emoji' => '⚡'],
            ['threshold' => 51, 'name' => 'Super', 'level' => '2', 'emoji' => '🌟'],
            ['threshold' => 41, 'name' => 'Super', 'level' => '1', 'emoji' => '🌟'],
        ];

        if ($isMoon) {
            $levels = array_merge($levels, [
                ['threshold' => 31, 'name' => 'Moon', 'level' => '2', 'emoji' => '🌙'],
                ['threshold' => 21, 'name' => 'Moon', 'level' => '1', 'emoji' => '🌙'],
                ['threshold' => 11, 'name' => 'Go Moon', 'level' => '2', 'emoji' => '🚀'],
                ['threshold' => 1, 'name' => 'Go Moon', 'level' => '1', 'emoji' => '🚀'],
            ]);
        } else {
            $levels = array_merge($levels, [
                ['threshold' => 31, 'name' => 'Crash', 'level' => '2', 'emoji' => '📉'],
                ['threshold' => 21, 'name' => 'Crash', 'level' => '1', 'emoji' => '📉'],
                ['threshold' => 11, 'name' => 'Go Crash', 'level' => '2', 'emoji' => '🔻'],
                ['threshold' => 1, 'name' => 'Go Crash', 'level' => '1', 'emoji' => '🔻'],
            ]);
        }

        foreach ($levels as $level) {
            if ($count >= $level['threshold']) {
                $sentiment = [
                    'count' => $count,
                    'name' => $level['name'],
                    'level' => $level['level'],
                    'emoji' => $level['emoji'],
                    'type' => $isMoon ? 'Moon' : 'Crash',
                    'full_name' => $level['emoji'] . ' ' . $level['name'] . ' ' . ($isMoon ? 'Moon' : 'Crash') . ' ' . $level['level']
                ];
                return $sentiment;
            }
        }

        return [
            'count' => $count,
            'name' => 'Neutral',
            'level' => '',
            'emoji' => '⚪',
            'type' => 'Neutral',
            'full_name' => '⚪ Neutral Market'
        ];
    }
}
