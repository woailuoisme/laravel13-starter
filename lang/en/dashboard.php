<?php

declare(strict_types=1);

return [
    'title' => 'Demo Dashboard',
    'navigation' => [
        'label' => 'Dashboard',
    ],
    'widgets' => [
        'stats' => [
            'visitors' => [
                'label' => 'Visitors',
                'description' => 'Mock page views over the last week',
            ],
            'signups' => [
                'label' => 'Signups',
                'description' => 'Mock registrations over the last week',
            ],
            'conversion_rate' => [
                'label' => 'Conversion rate',
                'description' => 'Mock conversion rate trend',
            ],
        ],
        'chart' => [
            'heading' => 'Traffic trend',
            'description' => 'Demo traffic data only',
            'dataset_label' => 'Traffic',
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ],
    ],
];
