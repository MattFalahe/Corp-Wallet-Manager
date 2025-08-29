<?php

return [
    // Define the permissions your menu items reference
    'wallet' => [
        'view' => [
            'label' => 'corpwalletmanager::permissions.wallet_view_label',
            'description' => 'corpwalletmanager::permissions.wallet_view_description',
            'division' => 'financial',
        ],
    ],
    'member' => [
        'view' => [
            'label' => 'corpwalletmanager::permissions.member_view_label',
            'description' => 'corpwalletmanager::permissions.member_view_description',
            'division' => 'financial',
        ],
    ],
];
