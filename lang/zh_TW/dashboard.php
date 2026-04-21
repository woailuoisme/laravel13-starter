<?php

declare(strict_types=1);

return [
    'title' => '示範儀表板',
    'navigation' => [
        'label' => '儀表板',
    ],
    'widgets' => [
        'stats' => [
            'visitors' => [
                'label' => '瀏覽量',
                'description' => '過去一週的示範頁面瀏覽量',
            ],
            'signups' => [
                'label' => '註冊量',
                'description' => '過去一週的示範註冊數量',
            ],
            'conversion_rate' => [
                'label' => '轉換率',
                'description' => '示範轉換率趨勢',
            ],
        ],
        'chart' => [
            'heading' => '流量趨勢',
            'description' => '僅供示範的數據',
            'dataset_label' => '流量',
            'labels' => ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
        ],
    ],
];
