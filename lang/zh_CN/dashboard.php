<?php

declare(strict_types=1);

return [
    'title' => '演示仪表板',
    'navigation' => [
        'label' => '仪表板',
    ],
    'widgets' => [
        'stats' => [
            'visitors' => [
                'label' => '访问量',
                'description' => '过去一周的演示页面浏览量',
            ],
            'signups' => [
                'label' => '注册量',
                'description' => '过去一周的演示注册数量',
            ],
            'conversion_rate' => [
                'label' => '转化率',
                'description' => '演示转化率趋势',
            ],
        ],
        'chart' => [
            'heading' => '流量趋势',
            'description' => '仅用于演示的数据',
            'dataset_label' => '流量',
            'labels' => ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
        ],
    ],
];
