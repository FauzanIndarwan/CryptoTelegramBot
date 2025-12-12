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
     * @param array $data Array of price data with timestamp and price
     * @param string $symbol Trading pair symbol
     * @param string $interval Time interval
     * @return string Chart URL
     */
    public static function generateLineChart($data, $symbol, $interval = '5m') {
        if (empty($data)) {
            return '';
        }

        $labels = [];
        $prices = [];

        foreach ($data as $point) {
            $labels[] = date('H:i', $point['timestamp'] / 1000);
            $prices[] = $point['close'];
        }

        $chartConfig = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => $symbol,
                        'data' => $prices,
                        'fill' => false,
                        'borderColor' => 'rgb(75, 192, 192)',
                        'tension' => 0.1,
                        'pointRadius' => 2,
                        'pointHoverRadius' => 5
                    ]
                ]
            ],
            'options' => [
                'title' => [
                    'display' => true,
                    'text' => "$symbol Price Chart ($interval)",
                    'fontSize' => 16
                ],
                'legend' => [
                    'display' => false
                ],
                'scales' => [
                    'xAxes' => [
                        [
                            'display' => true,
                            'scaleLabel' => [
                                'display' => true,
                                'labelString' => 'Time'
                            ]
                        ]
                    ],
                    'yAxes' => [
                        [
                            'display' => true,
                            'scaleLabel' => [
                                'display' => true,
                                'labelString' => 'Price (USDT)'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return self::buildChartUrl($chartConfig, 800, 400);
    }

    /**
     * Generate candlestick chart URL
     * 
     * @param array $data Array of OHLC data
     * @param string $symbol Trading pair symbol
     * @return string Chart URL
     */
    public static function generateCandlestickChart($data, $symbol) {
        if (empty($data)) {
            return '';
        }

        $labels = [];
        $ohlcData = [];

        foreach ($data as $candle) {
            $labels[] = date('M d', $candle['timestamp'] / 1000);
            $ohlcData[] = [
                'o' => $candle['open'],
                'h' => $candle['high'],
                'l' => $candle['low'],
                'c' => $candle['close']
            ];
        }

        $chartConfig = [
            'type' => 'candlestick',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => $symbol,
                        'data' => $ohlcData
                    ]
                ]
            ],
            'options' => [
                'title' => [
                    'display' => true,
                    'text' => "$symbol Candlestick Chart (30 Days)",
                    'fontSize' => 16
                ],
                'legend' => [
                    'display' => false
                ],
                'scales' => [
                    'xAxes' => [
                        [
                            'display' => true,
                            'scaleLabel' => [
                                'display' => true,
                                'labelString' => 'Date'
                            ]
                        ]
                    ],
                    'yAxes' => [
                        [
                            'display' => true,
                            'scaleLabel' => [
                                'display' => true,
                                'labelString' => 'Price (USDT)'
                            ]
                        ]
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
