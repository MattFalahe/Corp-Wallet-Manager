<?php
return [
    'corpwalletmanager' => [
        'name'          => 'Corp Wallet Manager',
        'icon'          => 'fas fa-wallet',
        'route_segment' => 'corp-wallet-manager',
        'permission'    => 'corpwalletmanager.view',
        'entries'       => [
            [
                'name'  => 'Director View',
                'icon'  => 'fas fa-chart-line',
                'route' => 'corpwalletmanager.director',
                'permission' => 'corpwalletmanager.director_view',
            ],
            [
                'name'  => 'Member View',
                'icon'  => 'fas fa-chart-area',
                'route' => 'corpwalletmanager.member',
                'permission' => 'corpwalletmanager.member_view',
            ],
            [
                'name'  => 'Settings',
                'icon'  => 'fas fa-cog',
                'route' => 'corpwalletmanager.settings',
                'permission' => 'corpwalletmanager.settings',
            ],
        ]
    ]
];
