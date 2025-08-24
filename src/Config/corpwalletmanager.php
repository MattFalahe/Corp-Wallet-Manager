<?php

return [
    // how often front-end should refresh graphs (ms)
    'refresh_interval' => 60000,
    // chart colors
    'color_actual' => '#4cafef',
    'color_predicted' => '#ef4444',
    // how many decimals to display on isk values
    'decimals' => 2,
    // whether to use cached precomputations or live queries
    'use_precomputed_predictions' => true,
    'use_precomputed_monthly_balances' => true,
];
