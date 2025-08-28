<?php

return [
    [
        'name' => 'corp-wallet-director',
        'label' => 'Corp Wallet (Director)',
        'permission' => 'corporation.wallet.view',
        'highlight_view' => 'corp-wallet-manager',
        'route' => 'corpwalletmanager.director',
        'icon' => 'fas fa-line-chart',
    ],
    [
        'name' => 'corp-wallet-member', 
        'label' => 'Corp Wallet (Member)',
        'permission' => 'corporation.member.view',
        'highlight_view' => 'corp-wallet-manager',
        'route' => 'corpwalletmanager.member',
        'icon' => 'fas fa-area-chart',
    ],
    [
        'name' => 'corp-wallet-settings',
        'label' => 'Corp Wallet Settings',
        'permission' => 'corporation.wallet.view',
        'highlight_view' => 'corp-wallet-manager',
        'route' => 'corpwalletmanager.settings',
        'icon' => 'fas fa-cog',
    ],
];
