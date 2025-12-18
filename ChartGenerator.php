<?php
/**
 * Chart Generator Class
 * 
 * Generates chart URLs for displaying cryptocurrency price data
 * using QuickChart.io API for rendering charts.
 */

class ChartGenerator {
    private const QUICKCHART_BASE_URL = 'https://quickchart.io/chart';

    /**
     * Generate line chart URL
     * 
     * @param string $pair Trading pair (e.g., 'BTC_IDR')
     * @param array $timestamps Array of timestamps
     * @param array $prices Array of prices
     * @return string Chart URL
     */
    public static function getLineChartUrl(string $pair, array $timestamps, array $prices): string {
        if (empty($timestamps) || empty($prices)) {
            return '';
        }

        $labels = array_map(fn($ts) => date('H:i', $ts), $timestamps);
        $minPrice = min($prices);
        $maxPrice = max($prices);
        $padding = 0.01;
        
        $chartConfig = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => $pair,
                    'data' => array_map('floatval', $prices),
                    'fill' => false,
                    'borderColor' => 'rgb(75, 192, 192)',
                    'tension' => 0.1,
                    'pointRadius' => 2
                ]]
            ],
            'options' => [
                'title' => ['display' => true, 'text' => "Chart Harga {$pair}"],
                'scales' => [
                    'y' => [
                        'beginAtZero' => false,
                        'min' => $minPrice * (1 - $padding),
                        'max' => $maxPrice * (1 + $padding)
                    ]
                ]
            ]
        ];
        
        return self::buildChartUrl($chartConfig, 800, 400);
    }

    /**
     * Generate candlestick chart URL
     * 
     * @param string $pair Trading pair (e.g., 'BTC_IDR')
     * @param array $ohlcData Array of OHLC data from database
     * @return string Chart URL
     */
    public static function getCandlestickChartUrl(string $pair, array $ohlcData): string {
        if (empty($ohlcData)) {
            return '';
        }

        // FIX: Konversi timestamp ke milidetik untuk QuickChart
        $dataPoints = array_map(function($row) {
            $timestamp = (int)$row['waktu_buka'];
            // Jika timestamp dalam detik (10 digit), konversi ke milidetik
            if ($timestamp < 10000000000) {
                $timestamp = $timestamp * 1000;
            }
            return [
                't' => $timestamp,
                'o' => (float)$row['harga_buka'],
                'h' => (float)$row['harga_tertinggi'],
                'l' => (float)$row['harga_terendah'],
                'c' => (float)$row['harga_tutup']
            ];
        }, $ohlcData);
        
        $lows = array_map('floatval', array_column($ohlcData, 'harga_terendah'));
        $highs = array_map('floatval', array_column($ohlcData, 'harga_tertinggi'));
        $padding = 0.02;
        
        $chartConfig = [
            'type' => 'candlestick',
            'data' => [
                'datasets' => [[
                    'label' => $pair,
                    'data' => $dataPoints
                ]]
            ],
            'options' => [
                'title' => ['display' => true, 'text' => "Daily Candlestick Chart {$pair}"],
                'scales' => [
                    'x' => ['type' => 'time', 'time' => ['unit' => 'day']],
                    'y' => [
                        'beginAtZero' => false,
                        'min' => min($lows) * (1 - $padding),
                        'max' => max($highs) * (1 + $padding)
                    ]
                ]
            ]
        ];
        
        return self::buildChartUrl($chartConfig, 800, 500);
    }

    /**
     * Generate StochRSI indicator chart URL
     * 
     * @param array $kValues K line values
     * @param array $dValues D line values
     * @param string $symbol Trading pair symbol
     * @return string Chart URL
     */
    public static function generateStochRSIChart($kValues, $dValues, $symbol) {
        if (empty($kValues) || empty($dValues)) {
            return '';
        }

        $labels = [];
        for ($i = 0; $i < count($kValues); $i++) {
            $labels[] = $i + 1;
        }

        $chartConfig = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'K Line',
                        'data' => $kValues,
                        'fill' => false,
                        'borderColor' => 'rgb(54, 162, 235)',
                        'tension' => 0.1,
                        'pointRadius' => 2
                    ],
                    [
                        'label' => 'D Line',
                        'data' => $dValues,
                        'fill' => false,
                        'borderColor' => 'rgb(255, 99, 132)',
                        'tension' => 0.1,
                        'pointRadius' => 2
                    ]
                ]
            ],
            'options' => [
                'title' => [
                    'display' => true,
                    'text' => "$symbol Stochastic RSI",
                    'fontSize' => 16
                ],
                'legend' => [
                    'display' => true,
                    'position' => 'top'
                ],
                'scales' => [
                    'xAxes' => [
                        [
                            'display' => true,
                            'scaleLabel' => [
                                'display' => true,
                                'labelString' => 'Period'
                            ]
                        ]
                    ],
                    'yAxes' => [
                        [
                            'display' => true,
                            'scaleLabel' => [
                                'display' => true,
                                'labelString' => 'StochRSI Value'
                            ],
                            'ticks' => [
                                'min' => 0,
                                'max' => 100
                            ]
                        ]
                    ]
                ],
                'annotation' => [
                    'annotations' => [
                        [
                            'type' => 'line',
                            'mode' => 'horizontal',
                            'scaleID' => 'y-axis-0',
                            'value' => 80,
                            'borderColor' => 'red',
                            'borderWidth' => 1,
                            'borderDash' => [5, 5],
                            'label' => [
                                'content' => 'Overbought',
                                'enabled' => true,
                                'position' => 'left'
                            ]
                        ],
                        [
                            'type' => 'line',
                            'mode' => 'horizontal',
                            'scaleID' => 'y-axis-0',
                            'value' => 20,
                            'borderColor' => 'green',
                            'borderWidth' => 1,
                            'borderDash' => [5, 5],
                            'label' => [
                                'content' => 'Oversold',
                                'enabled' => true,
                                'position' => 'left'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return self::buildChartUrl($chartConfig, 800, 400);
    }

    /**
     * Build chart URL from configuration
     * 
     * @param array $config Chart configuration
     * @param int $width Chart width
     * @param int $height Chart height
     * @return string Chart URL
     */
    private static function buildChartUrl($config, $width = 800, $height = 400) {
        $params = [
            'c' => json_encode($config),
            'width' => $width,
            'height' => $height,
            'backgroundColor' => 'white',
            'devicePixelRatio' => 2.0
        ];

        return self::QUICKCHART_BASE_URL . '?' . http_build_query($params);
    }

    /**
     * Generate simple price comparison chart
     * 
     * @param array $symbols Array of symbols
     * @param array $prices Array of price changes for each symbol
     * @return string Chart URL
     */
    public static function generateComparisonChart($symbols, $prices) {
        if (empty($symbols) || empty($prices)) {
            return '';
        }

        $colors = [];
        foreach ($prices as $price) {
            $colors[] = $price >= 0 ? 'rgba(75, 192, 192, 0.8)' : 'rgba(255, 99, 132, 0.8)';
        }

        $chartConfig = [
            'type' => 'bar',
            'data' => [
                'labels' => $symbols,
                'datasets' => [
                    [
                        'label' => '24h Change (%)',
                        'data' => $prices,
                        'backgroundColor' => $colors
                    ]
                ]
            ],
            'options' => [
                'title' => [
                    'display' => true,
                    'text' => 'Top Movers (24h)',
                    'fontSize' => 16
                ],
                'legend' => [
                    'display' => false
                ],
                'scales' => [
                    'yAxes' => [
                        [
                            'ticks' => [
                                'callback' => '%%function(value) { return value + "%"; }%%'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return self::buildChartUrl($chartConfig, 800, 400);
    }
}
