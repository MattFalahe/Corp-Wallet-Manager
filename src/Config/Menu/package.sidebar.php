<?php

return [
    'corpwalletmanager' => [
        'name' => 'Corp Wallet Manager',
        'label' => 'corpwalletmanager::menu.main_title',
        'plural' => true,
        'icon' => 'fas fa-wallet',
        'route_segment' => 'corp-wallet-manager',
        'permission' => 'corpwalletmanager.view',  // Base permission to see the menu
        'entries' => [
            [
                'name' => 'Director View',
                'label' => 'corpwalletmanager::menu.director_view',
                'icon' => 'fas fa-chart-line',
                'route' => 'corpwalletmanager.director',
                'permission' => 'corpwalletmanager.director_view',
            ],
            [
                'name' => 'Member View',
                'label' => 'corpwalletmanager::menu.member_view',
                'icon' => 'fas fa-chart-area',
                'route' => 'corpwalletmanager.member',
                'permission' => 'corpwalletmanager.member_view',
            ],
            [
                'name' => 'Settings',
                'label' => 'corpwalletmanager::menu.settings',
                'icon' => 'fas fa-cog',
                'route' => 'corpwalletmanager.settings',
                'permission' => 'corpwalletmanager.settings',
            ],
        ],
    ],
];
