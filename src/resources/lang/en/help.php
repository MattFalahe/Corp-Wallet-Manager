<?php

return [
    // Navigation
    'help_documentation' => 'Help & Documentation',
    'search_placeholder' => 'Search documentation...',
    'overview' => 'Overview',
    'getting_started' => 'Getting Started',
    'features' => 'Features',
    'director_tabs' => 'Director Tabs',
    'predictions' => 'Predictions',
    'reports' => 'Reports',
    'analytics' => 'Analytics',
    'commands' => 'Commands',
    'settings' => 'Settings',
    'member_view' => 'Member View',
    'faq' => 'FAQ',
    'troubleshooting' => 'Troubleshooting',

    // Plugin Information
    'plugin_info_title' => 'Plugin Information',
    'version' => 'Version',
    'license' => 'License',
    'author' => 'Author',
    'github_repo' => 'GitHub Repository',
    'changelog' => 'Full Changelog',
    'report_issues' => 'Report Issues',
    'readme' => 'README',
    'support_project' => 'Support the Project',
    'support_list' => '<ul style="margin-top: 10px; margin-bottom: 0;">
        <li>⭐ Star the GitHub repository</li>
        <li>🐛 Report bugs and issues</li>
        <li>💡 Suggest new features</li>
        <li>🔧 Contributing code improvements</li>
        <li>🌟 Share with other SeAT users</li>
    </ul>',

    // Overview
    'welcome_title' => 'Welcome to Corp Wallet Manager',
    'welcome_desc' => 'Your comprehensive financial tracking and prediction system for EVE Online corporations.',
    'what_is_title' => 'What is Corp Wallet Manager?',
    'what_is_desc' => 'Corp Wallet Manager is a comprehensive financial tracking and prediction system for EVE Online corporations. It provides real-time balance monitoring, advanced predictive analytics using statistical models, and automated reporting to help corporation leadership make informed financial decisions.',

    // Feature Cards
    'feature_tracking_title' => 'Real-Time Balance Tracking',
    'feature_tracking_desc' => 'Monitor corporation wallet balances with hourly updates. Track historical trends, daily changes, and month-over-month comparisons with precision.',
    'feature_predictions_title' => 'Dual Prediction Models',
    'feature_predictions_desc' => 'Utilizes both Basic statistical model (for new corporations) and Advanced ARIMA model (with 60+ days of data) for accurate financial forecasting up to 90 days ahead.',
    'feature_analytics_title' => 'Advanced Analytics',
    'feature_analytics_desc' => 'Comprehensive financial analysis including health scores, burn rates, cash flow patterns, activity heatmaps, and performance metrics across multiple timeframes.',
    'feature_reports_title' => 'Automated Reports',
    'feature_reports_desc' => 'Generate and schedule financial reports with Discord integration. Supports custom, weekly, and monthly reports with automated delivery and notification triggers.',
    'feature_divisions_title' => 'Division Tracking',
    'feature_divisions_desc' => 'Monitor individual wallet divisions separately. Track division-specific balances, predictions, and performance metrics for better resource allocation.',
    'feature_permissions_title' => 'Role-Based Access',
    'feature_permissions_desc' => 'Granular permission system with separate views for directors and members. Control access to sensitive financial data and analytics features.',

    // Quick Links
    'view_dashboard' => 'View Dashboard',
    'configure_settings' => 'Configure Settings',
    'view_reports' => 'View Reports History',

    // Getting Started
    'getting_started_title' => 'Getting Started',
    'getting_started_desc' => 'Follow these steps to set up Corp Wallet Manager for your corporation.',
    'quick_start_title' => 'Quick Start Guide',
    'step1_title' => 'Configure Corporation',
    'step1_desc' => 'Go to Settings and select your corporation from the dropdown. Only corporations where you have director roles will appear.',
    'step2_title' => 'Wait for Initial Data',
    'step2_desc' => 'The system will begin collecting balance data hourly. Wait at least 24 hours for initial trends to appear.',
    'step3_title' => 'Run Backfill (Optional)',
    'step3_desc' => 'To populate historical data faster, run the backfill command. Note: ESI only provides up to 3 months of financial data. Historical data beyond 3 months depends on how long your SeAT has been running and storing wallet journal entries.',
    'step4_title' => 'Enable Predictions',
    'step4_desc' => 'After 2+ months of data, predictions will automatically generate. With 60+ days of data, the system upgrades to the Advanced ARIMA model.',
    'step5_title' => 'Configure Reports (Optional)',
    'step5_desc' => 'Set up Discord webhooks in Settings to receive automated financial reports and alerts.',
    'success_tip' => 'Success Tip',
    'success_desc' => 'The more data the system collects, the more accurate your predictions become. Be patient during the first few weeks.',

    // Features Section - Enhanced
    'features_overview' => 'Features Overview',
    'balance_tracking_title' => 'Balance Tracking & Monitoring',
    'balance_tracking_desc' => 'Corp Wallet Manager provides comprehensive real-time tracking of your corporation\'s financial health with multiple layers of analysis:',
    'balance_features' => '<ul>
        <li><strong>Hourly Updates:</strong> Automatic balance updates every hour to ensure you always have current data</li>
        <li><strong>Historical Data:</strong> Complete balance history stored monthly for trend analysis and long-term planning</li>
        <li><strong>Division Tracking:</strong> Monitor each wallet division separately with individual balance histories and predictions</li>
        <li><strong>Daily Aggregation:</strong> Automatic end-of-day calculations that compute daily changes, averages, and accumulations</li>
        <li><strong>Multi-timeframe Analysis:</strong> View data across daily, weekly, monthly, quarterly, and yearly perspectives</li>
    </ul>',

    // Prediction Models
    'predictions_system_title' => 'Predictive Analytics System',
    'predictions_system_desc' => 'The plugin features a sophisticated dual-model prediction system that automatically adapts to your corporation\'s data availability:',
    
    'basic_model_title' => 'Basic Model',
    'basic_model_subtitle' => 'Used for new corporations with limited historical data',
    'basic_model_features' => '<ul>
        <li><i class="fas fa-check"></i> Requires: 2+ months of data</li>
        <li><i class="fas fa-check"></i> Method: Simple average + linear trend</li>
        <li><i class="fas fa-check"></i> Predictions: 30 days ahead</li>
        <li><i class="fas fa-check"></i> Confidence: Fixed decay (90% → 2% per day)</li>
        <li><i class="fas fa-check"></i> Factors: Historical average only</li>
    </ul>',

    'arima_model_title' => 'Advanced ARIMA Model',
    'arima_model_subtitle' => 'Automatically activated with sufficient data (v1.1.0+)',
    'arima_model_features' => '<ul>
        <li><i class="fas fa-check"></i> Requires: 60+ days in last 3 months</li>
        <li><i class="fas fa-check"></i> Method: Weighted averages + seasonal patterns</li>
        <li><i class="fas fa-check"></i> Predictions: 30, 60, 90 days with different confidence</li>
        <li><i class="fas fa-check"></i> Confidence: Statistical confidence intervals</li>
        <li><i class="fas fa-check"></i> Factors: Seasonal, momentum, activity, volatility</li>
        <li><i class="fas fa-check"></i> Bounds: Upper/lower prediction bounds</li>
        <li><i class="fas fa-check"></i> Metadata: Detailed analysis factors</li>
    </ul>',

    'model_migration' => 'Automatic Model Migration',
    'model_migration_desc' => 'As your corporation accumulates data, the system automatically upgrades from the Basic model to the Advanced ARIMA model on day 61+. This transition is seamless and requires no manual intervention. You can identify which model is being used by checking the prediction_method field in the database or the metadata displayed in predictions.',

    'division_management_title' => 'Division Management',
    'division_management_desc' => 'Track and analyze individual wallet divisions with the same depth as your main corporation wallet. Each division gets its own balance history, predictions, and performance metrics. Perfect for corporations that segregate funds by department or activity type.',

    'advanced_analytics_title' => 'Advanced Analytics',
    'advanced_analytics_desc' => 'Comprehensive analytics dashboard providing deep insights into your corporation\'s financial patterns:',
    'analytics_features' => '<ul>
        <li><strong>Health Score:</strong> Overall financial health indicator based on multiple factors</li>
        <li><strong>Burn Rate Analysis:</strong> Track how quickly ISK is being spent or earned</li>
        <li><strong>Cash Flow Patterns:</strong> Identify daily and weekly income/expense trends</li>
        <li><strong>Activity Heatmaps:</strong> Visualize transaction patterns across days and times</li>
        <li><strong>Financial Ratios:</strong> Key performance indicators and efficiency metrics</li>
        <li><strong>Best/Worst Days:</strong> Identify your most and least profitable days</li>
    </ul>',

    'pro_tip' => 'Pro Tip',
    'predictions_tip' => 'Enable daily predictions to automatically calculate future balances every 24 hours. This keeps your forecasts up-to-date without manual intervention. The prediction system learns from your actual spending and earning patterns to improve accuracy over time.',

    // Director Tabs
    'director_tabs_title' => 'Director View - Tab Guide',
    'director_tabs_intro' => 'The Director View provides comprehensive financial oversight through four specialized tabs. Each tab offers unique insights and tools for managing your corporation\'s finances.',

    'purpose' => 'Purpose',
    'features' => 'Features',
    'subsections' => 'Sub-sections',
    'best_for' => 'Best For',
    'currently_supported' => 'Currently Supported',

    'overview_tab_title' => 'Overview Tab',
    'overview_tab_purpose' => 'Primary dashboard showing high-level financial status and key metrics.',
    'overview_tab_features' => '<ul>
        <li>Current wallet balance with real-time updates</li>
        <li>Today\'s change and percentage movement</li>
        <li>Quick access to corporation info</li>
        <li>Monthly balance comparison chart</li>
        <li>30-day prediction visualization</li>
        <li>Division balance breakdown (if enabled)</li>
        <li>Recent transaction summary</li>
    </ul>',
    'overview_tab_best' => 'Daily check-ins and quick status overviews',

    'analytics_tab_title' => 'Analytics Tab',
    'analytics_tab_purpose' => 'Deep dive into financial patterns and performance metrics.',
    'analytics_tab_subsections' => '<ul>
        <li><strong>Health:</strong> Financial health score, burn rate analysis, and key ratios</li>
        <li><strong>Trends:</strong> Activity heatmaps, best/worst days, weekly patterns</li>
        <li><strong>Performance:</strong> Division performance comparison and efficiency metrics</li>
        <li><strong>Cash Flow:</strong> Daily income/expense tracking and division-specific cash flow</li>
    </ul>',
    'analytics_tab_best' => 'Strategic planning, identifying trends, and performance optimization',

    'reports_tab_title' => 'Reports Tab',
    'reports_tab_purpose' => 'Generate, schedule, and manage financial reports.',
    'reports_tab_features' => '<ul>
        <li>Custom report generation with date ranges</li>
        <li>Multiple report types (Custom, Weekly, Monthly, Division-specific)</li>
        <li>Report history and archive</li>
        <li>Discord integration for automated delivery</li>
        <li>Report preview before sending</li>
        <li>Export options for external analysis</li>
    </ul>',
    'reports_tab_supported' => '<ul>
        <li>✅ Discord webhook integration</li>
        <li>✅ Custom date range reports</li>
        <li>✅ Weekly automated reports</li>
        <li>✅ Monthly automated reports</li>
        <li>✅ View without sending</li>
        <li>🚧 Email integration (coming soon)</li>
        <li>🚧 PDF export (coming soon)</li>
    </ul>',
    'reports_tab_best' => 'Sharing financial summaries with leadership, scheduled updates',

    'predictions_tab_title' => 'Predictions Tab',
    'predictions_tab_purpose' => 'View and analyze future balance predictions with confidence intervals.',
    'predictions_tab_features' => '<ul>
        <li>30, 60, and 90-day predictions (ARIMA model)</li>
        <li>Confidence interval visualization</li>
        <li>Upper and lower bound forecasts</li>
        <li>Prediction method indicator (Basic vs. ARIMA)</li>
        <li>Historical prediction accuracy tracking</li>
        <li>Seasonal pattern identification</li>
        <li>Momentum and activity factor analysis</li>
    </ul>',
    'predictions_tab_best' => 'Long-term planning, budget forecasting, risk assessment',

    'data_refresh' => 'Data Refresh',
    'data_refresh_desc' => 'Most tabs refresh automatically every 5 minutes. Manual refresh buttons are available on each tab for immediate updates. The refresh rate can be configured in Settings.',

    // Predictions Section - Technical
    'predictions_guide' => 'Prediction System - Technical Details',
    'predictions_intro' => 'Corp Wallet Manager v1.1.0+ features a sophisticated dual-model prediction system that provides accurate financial forecasting.',

    'model_selection_title' => 'Model Selection Logic',
    'model_selection_desc' => 'The system automatically chooses the appropriate prediction model based on data availability:',
    'model_selection_code' => 'if (corporation has 60+ days of data in last 3 months) {
    Use Advanced ARIMA Model
    - 12-month weighted analysis
    - Seasonal pattern recognition
    - Confidence intervals
    - Multiple timeframe predictions (30/60/90 days)
} else {
    Use Basic Model (fallback)
    - Simple 6-month average
    - Linear trend calculation
    - 30-day predictions only
}',

    'arima_details_title' => 'Advanced ARIMA Model (v1.1.0+)',
    'arima_details_desc' => 'The Advanced model uses the PredictionService class with sophisticated statistical analysis:',
    'arima_details_list' => '<ul>
        <li><strong>Data Analysis:</strong> Examines 12 months of historical balance data</li>
        <li><strong>Weighted Averages:</strong> Recent data weighted more heavily than older data</li>
        <li><strong>Seasonal Patterns:</strong> Identifies recurring monthly patterns</li>
        <li><strong>Momentum Factors:</strong> Calculates velocity of balance changes</li>
        <li><strong>Activity Analysis:</strong> Considers transaction frequency and volume</li>
        <li><strong>Volatility Assessment:</strong> Measures balance stability</li>
    </ul>',

    'prediction_output' => 'Prediction Output Structure',
    'arima_output_example' => '[
    \'predicted_balance\' => 1050000000,
    \'confidence\' => 85.5,  // Statistical calculation
    \'lower_bound\' => 950000000,
    \'upper_bound\' => 1150000000,
    \'prediction_method\' => \'advanced_weighted\',
    \'metadata\' => [
        \'seasonal_factor\' => 1.08,
        \'momentum_factor\' => 1.02,
        \'activity_factor\' => 0.98,
        \'volatility\' => 0.15,
        \'data_points_used\' => 365,
        \'model_version\' => \'1.1.0\'
    ]
]',

    'basic_details_title' => 'Basic Model (Fallback)',
    'basic_details_desc' => 'Used for corporations with insufficient data (< 60 days):',
    'basic_details_list' => '<ul>
        <li><strong>Data Required:</strong> Minimum 2 months</li>
        <li><strong>Method:</strong> Simple moving average with linear trend</li>
        <li><strong>Confidence:</strong> Fixed decay from 90% to 2% over 30 days</li>
        <li><strong>Predictions:</strong> 30 days only</li>
    </ul>',

    'basic_output' => 'Basic Model Output',
    'basic_output_example' => '[
    \'predicted_balance\' => 1000000000,
    \'confidence\' => 88,  // Fixed decay: 90% - (2% * days)
    \'prediction_method\' => \'simple_linear\',
    \'metadata\' => null
]',

    'checking_model_title' => 'Checking Which Model Is Active',
    'checking_model_desc' => 'You can verify which model your corporation is using:',
    'checking_model_sql' => 'SELECT 
    corporation_id, 
    prediction_method, 
    COUNT(*) as prediction_count
FROM corpwalletmanager_predictions 
WHERE date >= CURDATE()
GROUP BY corporation_id, prediction_method;',

    'prediction_accuracy' => 'Prediction Accuracy',
    'prediction_accuracy_desc' => 'Factors that influence prediction accuracy:',
    'accuracy_factors' => '<ul>
        <li><strong>Data Quality:</strong> More consistent data = better predictions</li>
        <li><strong>Historical Length:</strong> 12+ months provides best results</li>
        <li><strong>Pattern Stability:</strong> Regular patterns are easier to predict</li>
        <li><strong>Transaction Volume:</strong> Higher activity provides more data points</li>
    </ul>',

    'important' => 'Important',
    'prediction_warning' => 'Predictions are statistical forecasts based on historical patterns. They cannot account for unexpected events, major policy changes, or unprecedented transactions. Always use predictions as one of several planning tools, not as absolute future values.',

    'improvement_over_time' => 'Improvement Over Time',
    'improvement_desc' => 'As your corporation accumulates more data, prediction accuracy naturally improves. The ARIMA model becomes more refined with each month of additional data, learning your corporation\'s unique financial patterns.',

    // Reports Section
    'reports_guide' => 'Reports & Automation',
    'reports_intro' => 'The Reports system allows you to generate, schedule, and automate financial reports for your corporation. Currently supports Discord integration with more delivery methods planned.',

    'accessing_reports' => 'Accessing Reports',
    'accessing_reports_list' => '<ul>
        <li>Director View → Reports Tab</li>
        <li>Main Menu → Corp Wallet Manager → Reports History</li>
    </ul>',

    'available_reports' => 'Available Report Types',
    'report_types' => '<ul>
        <li><strong>Custom Reports:</strong> Generate reports for any date range you specify. Perfect for board meetings or specific period analysis.</li>
        <li><strong>Weekly Reports:</strong> Automated Monday summaries showing the previous week\'s financial performance.</li>
        <li><strong>Monthly Reports:</strong> Comprehensive monthly summaries delivered on the 1st of each month.</li>
        <li><strong>Division Reports:</strong> Specialized reports focusing on specific wallet divisions.</li>
    </ul>',

    'report_contents' => 'Report Contents',
    'report_contents_intro' => 'Each report includes:',
    'report_contents_list' => '<ul>
        <li>Starting and ending balance for the period</li>
        <li>Total change amount and percentage</li>
        <li>Average daily change</li>
        <li>Largest single transaction</li>
        <li>Transaction count and volume</li>
        <li>Income vs. expense breakdown</li>
        <li>Predictions for the next 30 days</li>
        <li>Division breakdown (if applicable)</li>
    </ul>',

    'discord_integration' => 'Discord Integration',
    'discord_integration_intro' => 'Currently, reports can be delivered via Discord webhooks. This allows automatic delivery to your corporation\'s Discord server.',

    'discord_setup' => 'Setting Up Discord Webhooks',
    'discord_step1_title' => 'Create Webhook in Discord',
    'discord_step1_desc' => 'Go to your Discord server → Channel Settings → Integrations → Create Webhook',
    'discord_step2_title' => 'Copy Webhook URL',
    'discord_step2_desc' => 'Discord will provide a webhook URL (e.g., https://discord.com/api/webhooks/...)',
    'discord_step3_title' => 'Configure in Settings',
    'discord_step3_desc' => 'Go to Corp Wallet Manager → Settings → Reports section → Paste webhook URL',
    'discord_step4_title' => 'Test Connection',
    'discord_step4_desc' => 'Use the "Test Webhook" button to verify the connection works',

    'report_automation' => 'Report Automation',
    'report_automation_intro' => 'Automated reports are triggered by scheduled jobs:',
    'automation_schedule' => '<ul>
        <li><strong>Daily Summary:</strong> Sent at 00:00 UTC with previous day\'s activity</li>
        <li><strong>Weekly Summary:</strong> Sent every Monday at 00:00 UTC</li>
        <li><strong>Monthly Summary:</strong> Sent on the 1st of each month at 00:00 UTC</li>
    </ul>',

    'notification_triggers' => 'Notification Triggers',
    'notification_triggers_intro' => 'Beyond scheduled reports, the system can send alerts for specific events:',
    'notification_triggers_list' => '<ul>
        <li><strong>Low Balance Alert:</strong> Triggered when balance drops below configured threshold</li>
        <li><strong>Large Transaction Alert:</strong> Notifies when single transaction exceeds 100M ISK</li>
        <li><strong>Negative Trend Alert:</strong> Warns when balance shows consistent downward trend</li>
        <li><strong>Prediction Threshold:</strong> Alerts if predicted balance will drop below threshold</li>
    </ul>',

    'report_history' => 'Report History',
    'report_history_desc' => 'All generated reports are stored in the database and accessible via the Reports History page. You can:',
    'report_history_features' => '<ul>
        <li>View previously generated reports</li>
        <li>Re-send reports to Discord</li>
        <li>Download report data</li>
        <li>See report metadata (generation time, who generated it, etc.)</li>
    </ul>',

    'coming_soon' => 'Coming Soon',
    'coming_soon_features' => '<ul style="margin-top: 10px; margin-bottom: 0;">
        <li>📧 Email delivery support</li>
        <li>📄 PDF export functionality</li>
        <li>🔗 Slack integration</li>
        <li>📊 Custom report templates</li>
        <li>🎨 Branded report layouts</li>
    </ul>',

    'note' => 'Note',
    'reports_development_note' => 'The Reports feature is under active development. New delivery methods and customization options will be added in future releases. Check the GitHub repository for the latest updates and roadmap.',

    // Analytics
    'analytics_guide' => 'Analytics Dashboard',
    'analytics_intro' => 'The Analytics tab provides deep insights into your corporation\'s financial patterns and performance metrics.',

    'available_charts' => 'Available Analytics',
    'chart_types' => '<ul>
        <li><strong>Balance History:</strong> Visual timeline of your wallet balance changes over time</li>
        <li><strong>Trend Analysis:</strong> Identify upward or downward financial trends</li>
        <li><strong>Division Breakdown:</strong> Compare performance across wallet divisions</li>
        <li><strong>Comparison Charts:</strong> Month-over-month and year-over-year comparisons</li>
    </ul>',

    'customizing_view' => 'Customizing Your View',
    'customizing_view_desc' => 'Use the filters and date range selectors to customize your analytics view. Export charts and data for external analysis or presentations.',

    // Commands Section
    'commands_guide' => 'Console Commands Reference',
    'commands_intro' => 'Corp Wallet Manager provides several artisan commands for data management and maintenance. These commands are used by Laravel\'s scheduler and can also be run manually.',

    'scheduled_commands' => 'Scheduled Commands (Automatic)',
    'scheduled_commands_intro' => 'These commands run automatically via Laravel\'s scheduler:',
    
    'schedule' => 'Schedule',
    'what_it_does' => 'What it does',
    'options' => 'Options',
    'when_to_use' => 'When to use',
    'what_it_checks' => 'What it checks',
    'example' => 'Example',
    'none' => 'None',

    'cmd_update_hourly' => 'Update Hourly Wallet Data',
    'cmd_hourly_purpose' => 'Fetches current wallet balances from ESI',
    'cmd_hourly_schedule' => 'Every hour',
    'cmd_hourly_desc' => 'Updates current balance, calculates hourly change, stores timestamp',

    'cmd_daily_aggregation' => 'Daily Aggregation',
    'cmd_aggregation_purpose' => 'Computes end-of-day financial summaries',
    'cmd_aggregation_schedule' => 'Daily at 23:55 UTC',
    'cmd_aggregation_desc' => 'Calculates daily average, stores monthly balance, computes trends',
    'cmd_aggregation_options' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li><code>--date=YYYY-MM-DD</code> - Process specific date</li>
        <li><code>--corporation=ID</code> - Process specific corporation only</li>
        <li><code>--force</code> - Recalculate even if already exists</li>
    </ul>',

    'cmd_compute_predictions' => 'Compute Predictions',
    'cmd_predictions_purpose' => 'Generates future balance predictions',
    'cmd_predictions_schedule' => 'Daily at 00:30 UTC',
    'cmd_predictions_desc' => 'Runs prediction model (Basic or ARIMA), stores 30/60/90 day forecasts',
    'cmd_predictions_options' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li><code>--corporation=ID</code> - Generate predictions for specific corporation</li>
        <li><code>--force</code> - Force regeneration even if recent predictions exist</li>
        <li><code>--days=30,60,90</code> - Specify prediction timeframes</li>
    </ul>',

    'cmd_compute_division_predictions' => 'Compute Division Predictions',
    'cmd_division_predictions_purpose' => 'Generates predictions for individual wallet divisions',
    'cmd_division_predictions_schedule' => 'Daily at 01:00 UTC',
    'cmd_division_predictions_desc' => 'Same as main predictions but for each division separately',
    'cmd_division_predictions_options' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li><code>--corporation=ID</code> - Process specific corporation</li>
        <li><code>--division=ID</code> - Process specific division only</li>
        <li><code>--force</code> - Force regeneration</li>
    </ul>',

    'cmd_generate_report' => 'Generate Reports',
    'cmd_report_purpose' => 'Generates and sends automated reports',
    'cmd_report_schedule' => 'Daily at 00:00 UTC (checks if report should be sent)',
    'cmd_report_desc' => 'Generates financial report, sends to Discord if configured',
    'cmd_report_options' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li><code>--type=daily|weekly|monthly</code> - Report type to generate</li>
        <li><code>--corporation=ID</code> - Generate for specific corporation</li>
        <li><code>--from=YYYY-MM-DD</code> - Report start date</li>
        <li><code>--to=YYYY-MM-DD</code> - Report end date</li>
        <li><code>--send</code> - Send to Discord immediately</li>
        <li><code>--preview</code> - Generate without sending</li>
    </ul>',

    'manual_commands' => 'Manual Maintenance Commands',
    'manual_commands_intro' => 'Run these commands manually when needed:',

    'cmd_backfill' => 'Backfill Historical Data',
    'cmd_backfill_purpose' => 'Fill in missing historical balance data',
    'cmd_backfill_when' => 'After installation, or if data is missing',
    'cmd_backfill_desc' => 'Fetches historical journal entries from ESI and reconstructs balance history. IMPORTANT: ESI only provides up to 3 months of financial data. Historical data beyond 3 months depends on how long your SeAT instance has been running and storing wallet journal entries.',
    'cmd_backfill_options' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li><code>--months=3</code> - How many months back to attempt (max 3 from ESI, default: 3)</li>
        <li><code>--corporation=ID</code> - Backfill specific corporation only</li>
        <li><code>--force</code> - Overwrite existing data</li>
    </ul>',

    'cmd_backfill_divisions' => 'Backfill Division Data',
    'cmd_backfill_divisions_purpose' => 'Fill in missing division-specific balance data',
    'cmd_backfill_divisions_when' => 'After enabling division tracking',
    'cmd_backfill_divisions_desc' => 'Reconstructs balance history for each wallet division. Subject to same ESI limitations as main backfill (3 months maximum).',
    'cmd_backfill_divisions_options' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li><code>--months=3</code> - How many months back to fetch (max 3 from ESI)</li>
        <li><code>--corporation=ID</code> - Backfill specific corporation</li>
        <li><code>--division=ID</code> - Backfill specific division only</li>
        <li><code>--force</code> - Overwrite existing data</li>
    </ul>',

    'cmd_integrity_check' => 'Integrity Check',
    'cmd_integrity_purpose' => 'Verify database integrity and find issues',
    'cmd_integrity_when' => 'Troubleshooting, after updates, periodic maintenance',
    'cmd_integrity_checks' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li>Table structure completeness</li>
        <li>Duplicate entries</li>
        <li>Orphaned predictions (predictions without balance data)</li>
        <li>Missing corporation references</li>
        <li>Date consistency issues</li>
        <li>Settings validity</li>
    </ul>',
    'cmd_integrity_options' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li><code>--fix</code> - Automatically fix found issues</li>
        <li><code>--detailed</code> - Show detailed statistics</li>
        <li><code>--table=name</code> - Check specific table only</li>
    </ul>',

    'commands_note' => 'All scheduled commands are configured automatically during installation. You don\'t need to set up cron jobs manually - they use Laravel\'s built-in scheduler. Just ensure your SeAT instance has php artisan schedule:run running every minute.',

    'backfill_warning_title' => 'Backfill Warning',
    'backfill_warning' => 'ESI only provides 3 months of wallet journal data. The backfill command can only retrieve data from this 3-month window. For historical data older than 3 months, the plugin relies on wallet journal entries that your SeAT installation has been collecting over time. The further back your SeAT has been running, the more historical data will be available for backfilling.',

    // Settings Section
    'settings_guide' => 'Settings Configuration',
    'settings_intro' => 'Configure Corp Wallet Manager behavior through the Settings page. Access via: Main Menu → Corp Wallet Manager → Settings',

    'general_settings' => 'General Settings',
    'general_settings_list' => '<ul>
        <li><strong>Selected Corporation:</strong> Choose which corporation\'s wallet to track. Only corporations where you have director roles appear in this dropdown.</li>
        <li><strong>Auto-Refresh Interval:</strong> Set how often dashboard data refreshes automatically (in seconds). Default: 300 seconds (5 minutes). Range: 60-600 seconds.</li>
        <li><strong>Display Currency:</strong> Choose how to display ISK amounts (billions, millions, thousands, or full). Default: Billions.</li>
        <li><strong>Decimal Places:</strong> Number of decimal places to show in balance displays. Default: 2. Range: 0-4.</li>
        <li><strong>Date Format:</strong> Choose your preferred date format (YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY).</li>
        <li><strong>Time Zone:</strong> Set display timezone for reports and logs. Default: UTC.</li>
    </ul>',

    'prediction_settings' => 'Prediction Settings',
    'prediction_settings_list' => '<ul>
        <li><strong>Enable Predictions:</strong> Turn prediction calculations on/off globally. When disabled, no new predictions are generated.</li>
        <li><strong>Prediction Method:</strong> Choose between Auto (recommended), Basic Only, or Advanced Only. Auto uses ARIMA when enough data exists.</li>
        <li><strong>Prediction Timeframes:</strong> Select which timeframes to calculate (30, 60, 90 days). More timeframes = longer calculation time.</li>
        <li><strong>Minimum Data Requirement:</strong> Minimum months of data required before generating predictions. Default: 2 months.</li>
        <li><strong>Update Frequency:</strong> How often to recalculate predictions. Options: Daily (recommended), Weekly, Manual.</li>
    </ul>',

    'division_settings' => 'Division Settings',
    'division_settings_list' => '<ul>
        <li><strong>Enable Division Tracking:</strong> Track wallet divisions separately. When enabled, each division gets its own predictions and analytics.</li>
        <li><strong>Tracked Divisions:</strong> Select which specific divisions to track. Can select multiple or all divisions.</li>
        <li><strong>Division Predictions:</strong> Enable/disable predictions for divisions separately from main wallet.</li>
        <li><strong>Aggregate Division Data:</strong> Include division data in main corporation totals and reports.</li>
    </ul>',

    'report_settings' => 'Report Settings',
    'report_settings_list' => '<ul>
        <li><strong>Enable Reports:</strong> Turn report generation on/off.</li>
        <li><strong>Discord Webhook URL:</strong> Discord webhook for report delivery. Test connection with "Test Webhook" button.</li>
        <li><strong>Enable Daily Reports:</strong> Send daily summary at 00:00 UTC.</li>
        <li><strong>Enable Weekly Reports:</strong> Send weekly summary every Monday.</li>
        <li><strong>Enable Monthly Reports:</strong> Send monthly summary on the 1st.</li>
        <li><strong>Report Recipients:</strong> Additional Discord channels or email addresses (comma-separated).</li>
        <li><strong>Include Predictions in Reports:</strong> Add 30-day predictions to automated reports.</li>
        <li><strong>Include Division Breakdown:</strong> Add division-specific data to reports.</li>
    </ul>',

    'alert_settings' => 'Alert Settings',
    'alert_settings_list' => '<ul>
        <li><strong>Low Balance Alert:</strong> Enable alerts when balance drops below threshold.</li>
        <li><strong>Low Balance Threshold:</strong> ISK amount that triggers low balance alert.</li>
        <li><strong>Large Transaction Alert:</strong> Alert on transactions exceeding specified amount. Default: 100M ISK.</li>
        <li><strong>Negative Trend Alert:</strong> Alert when balance shows consistent downward trend over specified days.</li>
        <li><strong>Prediction Alert:</strong> Alert if predicted balance will drop below threshold within X days.</li>
    </ul>',

    'advanced_settings' => 'Advanced Settings',
    'advanced_settings_list' => '<ul>
        <li><strong>Data Retention:</strong> How long to keep historical data. Options: 6 months, 1 year, 2 years, 5 years, Forever.</li>
        <li><strong>Enable Access Logging:</strong> Log member view access for analytics. Viewable in Settings → Access Logs.</li>
        <li><strong>Cache Duration:</strong> How long to cache API responses. Default: 5 minutes. Range: 1-60 minutes.</li>
        <li><strong>Debug Mode:</strong> Enable detailed logging for troubleshooting. Disable in production.</li>
    </ul>',

    'maintenance_actions' => 'Maintenance Actions',
    'maintenance_actions_intro' => 'Quick action buttons available in Settings:',
    'maintenance_actions_list' => '<ul>
        <li><strong>Trigger Backfill:</strong> Manually start historical data backfill</li>
        <li><strong>Trigger Predictions:</strong> Force immediate prediction recalculation</li>
        <li><strong>Trigger Division Backfill:</strong> Backfill division-specific data</li>
        <li><strong>Trigger Division Predictions:</strong> Recalculate division predictions</li>
        <li><strong>Clear Cache:</strong> Clear all cached data</li>
        <li><strong>Reset Settings:</strong> Restore default settings (requires confirmation)</li>
    </ul>',

    'warning' => 'Warning',
    'settings_warning' => 'Changing corporation selection or disabling features will affect all users. Resetting settings cannot be undone. Always back up your configuration before making major changes.',

    'saving_changes' => 'Saving Changes',
    'saving_changes_desc' => 'Settings are saved immediately when you click "Save Settings". Most changes take effect immediately, but some (like prediction frequency) will apply on the next scheduled run.',

    // Member View
    'member_view_title' => 'Member View - Features & Access',
    'member_view_intro' => 'The Member View provides corporation members with relevant financial information without exposing sensitive details. Access via: Main Menu → Corp Wallet Manager → Member View',

    'available_information' => 'Available Information',
    
    'member_balance_overview' => 'Balance Overview',
    'member_balance_features' => '<ul>
        <li><strong>Current Balance:</strong> Real-time corporation wallet balance (updates hourly)</li>
        <li><strong>Today\'s Change:</strong> How much the balance changed today</li>
        <li><strong>Weekly Trend:</strong> Visual indicator of this week\'s performance</li>
        <li><strong>Monthly Trend:</strong> How this month compares to last month</li>
    </ul>',

    'member_health_score' => 'Financial Health Score',
    'member_health_desc' => 'Simple health indicator showing overall corporation financial status:',
    'member_health_ratings' => '<ul>
        <li>🟢 <strong>Healthy (80-100):</strong> Strong financial position, positive trends</li>
        <li>🟡 <strong>Stable (60-79):</strong> Decent position, some concerns</li>
        <li>🟠 <strong>Concerning (40-59):</strong> Negative trends, attention needed</li>
        <li>🔴 <strong>Critical (0-39):</strong> Serious financial issues</li>
    </ul>',

    'member_goals' => 'Corporation Goals',
    'member_goals_desc' => 'Visual progress tracking toward financial goals (if set by directors):',
    'member_goals_features' => '<ul>
        <li>Goal target amounts</li>
        <li>Current progress percentage</li>
        <li>Estimated time to reach goal</li>
        <li>Historical goal achievement</li>
    </ul>',

    'member_milestones' => 'Financial Milestones',
    'member_milestones_desc' => 'Celebrate corporation achievements:',
    'member_milestones_features' => '<ul>
        <li>Balance milestones reached (1B, 10B, 100B ISK, etc.)</li>
        <li>Longest positive streak</li>
        <li>Best month on record</li>
        <li>Year-over-year growth</li>
    </ul>',

    'member_activity_patterns' => 'Activity Patterns',
    'member_activity_features' => '<ul>
        <li><strong>Weekly Pattern:</strong> Which days are typically best/worst for the corporation</li>
        <li><strong>Monthly Summary:</strong> High-level summary of monthly performance</li>
        <li><strong>Activity Level:</strong> How active the corporation has been financially</li>
    </ul>',

    'member_performance' => 'Performance Metrics',
    'member_performance_features' => '<ul>
        <li><strong>30-Day Average Change:</strong> Average daily balance change over last month</li>
        <li><strong>Income/Expense Ratio:</strong> Simplified view of income vs spending</li>
        <li><strong>Trend Direction:</strong> Overall direction of corporation finances</li>
    </ul>',

    'member_cannot_see' => 'What Members Cannot See',
    'member_cannot_see_intro' => 'To protect sensitive information, members do not have access to:',
    'member_restrictions' => '<ul>
        <li>❌ Detailed transaction history</li>
        <li>❌ Individual division balances</li>
        <li>❌ Specific transaction amounts and parties</li>
        <li>❌ Long-term predictions (>30 days)</li>
        <li>❌ Detailed analytics and reports</li>
        <li>❌ Settings and configuration</li>
        <li>❌ Other members\' activity logs</li>
    </ul>',

    'access_logging' => 'Access Logging',
    'access_logging_desc' => 'When access logging is enabled in Settings, the system tracks:',
    'access_logging_items' => '<ul>
        <li>When members view the page</li>
        <li>How long they stay</li>
        <li>Which sections they interact with</li>
        <li>Access frequency patterns</li>
    </ul>',
    'access_logging_purpose' => 'This data helps directors understand member engagement and identify which information is most valuable to the corporation.',

    'customization_options' => 'Customization Options (Directors)',
    'customization_options_desc' => 'Directors can customize what information is shown to members:',
    'customization_options_list' => '<ul>
        <li><strong>Hide Balance Amount:</strong> Show trends only, not actual balance</li>
        <li><strong>Enable/Disable Sections:</strong> Control which widgets appear</li>
        <li><strong>Set Goal Visibility:</strong> Choose which goals members can see</li>
        <li><strong>Custom Messages:</strong> Add announcements or notes for members</li>
    </ul>',

    'privacy_security' => 'Privacy & Security',
    'privacy_desc' => 'The Member View is designed with privacy in mind. Members see aggregated, sanitized data that provides transparency without compromising operational security. All sensitive details remain director-only.',

    'building_trust' => 'Building Trust',
    'building_trust_desc' => 'A transparent financial picture helps build member trust and engagement. Consider enabling the Member View to show your corporation\'s financial health without revealing sensitive operational details.',

    // FAQ
    'frequently_asked' => 'Frequently Asked Questions',
    'faq_q1' => 'How often does the balance update?',
    'faq_a1' => 'Balance updates automatically every hour via the scheduled command. You can also manually refresh the dashboard at any time.',
    'faq_q2' => 'Why aren\'t my predictions showing?',
    'faq_a2' => 'Predictions require at least 2 months of historical data. Wait until enough data is collected, or run the backfill command to populate historical data.',
    'faq_q3' => 'Can I track multiple corporations?',
    'faq_a3' => 'Currently, Corp Wallet Manager tracks one corporation at a time. You can switch between corporations in Settings.',
    'faq_q4' => 'How accurate are the predictions?',
    'faq_a4' => 'Prediction accuracy improves with more data. The ARIMA model (activated after 60+ days of data) provides significantly better accuracy than the basic model, typically within 10-15% of actual values.',
    'faq_q5' => 'What\'s the difference between Director and Member views?',
    'faq_a5' => 'Director View provides full access to all features, analytics, and sensitive data. Member View shows only aggregated financial health indicators without sensitive details.',
    'faq_q6' => 'Can I export the data?',
    'faq_a6' => 'Yes! Use the Reports feature to generate and export financial reports. PDF export and additional formats are coming soon.',
    'faq_q7' => 'How do I set up Discord notifications?',
    'faq_a7' => 'Go to Settings → Report Settings, paste your Discord webhook URL, and enable the report types you want. Click "Test Webhook" to verify the connection.',
    'faq_q8' => 'What happens if I change corporations?',
    'faq_a8' => 'All historical data for the previous corporation is preserved. You can switch back anytime. The new corporation will start collecting data immediately.',
    'faq_q9' => 'How far back can I backfill data?',
    'faq_a9' => 'ESI provides up to 3 months of wallet journal data. For older data, the plugin uses wallet journal entries already stored in your SeAT database from normal operation.',
    'faq_q10' => 'What permissions do I need?',
    'faq_a10' => 'Directors need corpwalletmanager.director_view permission for full access. Members need corpwalletmanager.member_view for the Member View. Permissions are managed through SeAT\'s role system.',

    // Troubleshooting
    'troubleshooting_guide' => 'Troubleshooting Guide',
    'troubleshooting_intro' => 'Common issues and their solutions.',

    'common_issues' => 'Common Issues',
    
    'issue1_title' => 'Balance Not Updating',
    'issue1_desc' => 'If your balance appears frozen or outdated:',
    'issue1_solutions' => '<ul>
        <li>Verify the corporation is selected in Settings</li>
        <li>Check that scheduled commands are running (<code>php artisan schedule:work</code>)</li>
        <li>Manually trigger an update: <code>php artisan corpwalletmanager:update-hourly</code></li>
        <li>Check Laravel logs for ESI errors: <code>storage/logs/laravel.log</code></li>
    </ul>',

    'issue2_title' => 'Predictions Not Generating',
    'issue2_desc' => 'If predictions are missing or outdated:',
    'issue2_solutions' => '<ul>
        <li>Ensure you have at least 2 months of balance data</li>
        <li>Check that predictions are enabled in Settings</li>
        <li>Manually trigger predictions: <code>php artisan corpwalletmanager:compute-predictions --force</code></li>
        <li>Run integrity check: <code>php artisan corpwalletmanager:integrity-check</code></li>
    </ul>',

    'issue3_title' => 'Reports Not Sending to Discord',
    'issue3_desc' => 'If Discord reports fail to send:',
    'issue3_solutions' => '<ul>
        <li>Verify webhook URL is correct in Settings</li>
        <li>Test the webhook using the "Test Webhook" button</li>
        <li>Check Discord channel permissions allow webhook posts</li>
        <li>Review Laravel logs for webhook errors</li>
    </ul>',

    'need_help' => 'Need Help',
    'support_message' => 'If you can\'t resolve your issue, please report it on GitHub Issues with relevant log entries and steps to reproduce the problem.',

    // Misc
    'dashboard' => 'Dashboard',
    'dashboard_guide' => 'Dashboard Guide',
    'dashboard_intro' => 'The dashboard provides an overview of your corporation\'s financial status.',
    'director_dashboard' => 'Director Dashboard',
    'director_dashboard_desc' => 'Full access to all financial metrics and analytics.',
    'dashboard_corp_overview' => 'Corporation Overview',
    'dashboard_corp_overview_desc' => 'Current balance, today\'s change, and quick stats',
    'dashboard_predictions' => 'Predictions',
    'dashboard_predictions_desc' => 'Future balance predictions with confidence intervals',
    'dashboard_trends' => 'Trends',
    'dashboard_trends_desc' => 'Historical trends and patterns',
    'dashboard_divisions' => 'Divisions',
    'dashboard_divisions_desc' => 'Individual division breakdowns and performance',
    'member_dashboard' => 'Member Dashboard',
    'member_dashboard_desc' => 'Limited view for corporation members.',
    'dashboard_balance' => 'Balance',
    'dashboard_balance_desc' => 'Current corporation balance',
    'dashboard_health' => 'Health',
    'dashboard_health_desc' => 'Financial health score',
    'dashboard_goals' => 'Goals',
    'dashboard_goals_desc' => 'Progress toward corporation goals',
    'dashboard_activity' => 'Activity',
    'dashboard_activity_desc' => 'Recent activity patterns',
    'predictions_desc' => 'Advanced statistical models predict future balance trends.',
    'division_tracking_title' => 'Division Tracking',
    'division_tracking_desc' => 'Monitor individual wallet divisions separately with their own analytics.',
];
