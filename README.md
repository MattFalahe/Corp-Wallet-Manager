# Corp-Wallet-Manager

A comprehensive SeAT plugin for EVE Online corporation wallet tracking, analysis, and predictions. This plugin provides powerful financial analytics, trend analysis, and predictive modeling for corporation directors and members.

[![Latest Version](https://img.shields.io/github/v/release/MattFalahe/Corp-Wallet-Manager)](https://github.com/MattFalahe/Corp-Wallet-Manager/releases)
![License](https://img.shields.io/badge/license-GPL--2.0-blue)
![SeAT](https://img.shields.io/badge/SeAT-5.0-green)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)
![Laravel](https://img.shields.io/badge/Laravel-10.0-red)

## Features

### 🎯 Director View
- **Real-time Balance Tracking**: Monitor actual wallet balances across all divisions
- **Advanced Analytics**: Health scores, burn rates, financial ratios
- **Predictive Modeling**: 30-day balance forecasts using historical data
- **Cash Flow Analysis**: Daily, weekly, and monthly cash flow waterfalls
- **Division Performance**: Track individual division metrics and ROI
- **Activity Heatmaps**: Visualize transaction patterns over time
- **Executive Reports**: Auto-generated insights and recommendations

### 👥 Member View
- **Corporation Health Dashboard**: Simplified health indicators
- **Goal Tracking**: Savings, activity, and growth targets
- **Achievement System**: Milestones and upcoming events
- **Performance Metrics**: Radar charts showing key performance indicators
- **Weekly Patterns**: Activity analysis by day of week
- **Privacy Controls**: Optional ISK value masking for operational security

### ⚙️ Settings & Administration
- **Flexible Configuration**: Customizable refresh intervals, colors, and display options
- **Member View Controls**: Toggle sections and set data delays
- **Goal Management**: Set corporation-wide targets
- **Maintenance Tools**: Manual job triggers for data processing
- **Access Logging**: Track user access for security
- **Job Monitoring**: Real-time status of background processes

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- SeAT 5.0 or higher
- MySQL/MariaDB database
- Supervisor (for queue workers)

## Installation

### 1. Install via Composer

```bash
composer require mattfalahe/corp-wallet-manager
```

### 2. Run Migrations

```bash
php artisan migrate
```

### 3. Setup Permissions

```bash
php artisan corpwalletmanager:setup
```

### 4. Initial Data Backfill

```bash
# Backfill last 3 months of data
php artisan corpwalletmanager:backfill --months=3

# Or backfill all historical data (use with caution)
php artisan corpwalletmanager:backfill --all
```

### 5. Clear Cache

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 6. Restart Queue Workers

```bash
supervisorctl restart all
```

## Configuration

### Permissions

After installation, assign the following permissions in SeAT's Access Management:

- `corpwalletmanager.view` - Basic plugin access (required)
- `corpwalletmanager.director_view` - Access to director dashboard
- `corpwalletmanager.member_view` - Access to member dashboard
- `corpwalletmanager.settings` - Manage plugin settings

### Scheduled Jobs

The plugin automatically registers the following scheduled jobs:

| Job | Schedule | Description |
|-----|----------|-------------|
| Hourly Update | Every hour | Updates wallet data for the last hour |
| Compute Predictions | Every 6 hours | Calculates balance predictions |
| Daily Aggregation | Daily at 01:00 | Aggregates daily statistics |
| Division Predictions | Weekly (Mondays at 02:00) | Computes division-specific predictions |
| Monthly Backfill | Monthly (1st at 03:00) | Data integrity check and backfill |

### Settings Options

Navigate to **Corp Wallet Manager → Settings** to configure:

**Display Settings:**
- Corporation selection (specific or all)
- Chart refresh intervals (5, 15, 30, 60 minutes)
- Decimal places for ISK values
- Chart colors for actual and predicted values

**Performance Settings:**
- Use precomputed predictions (recommended)
- Use precomputed monthly balances (recommended)

**Member View Settings:**
- Toggle section visibility
- Set ISK value privacy (show/hide actual amounts)
- Configure goal targets
- Set data delay for operational security

## Usage

### For Directors

1. Navigate to **Corp Wallet Manager → Director View**
2. Use the tabs to access different analytics:
   - **Overview**: Current status, balance trends, predictions
   - **Analytics**: Health scores, burn rates, financial ratios
   - **Trends**: Activity patterns, best/worst days
   - **Performance**: Division metrics and comparisons
   - **Cash Flow**: Detailed income/expense analysis
   - **Reports**: Executive summaries and custom reports

### For Members

1. Navigate to **Corp Wallet Manager → Member View**
2. View simplified dashboard with:
   - Corporation health status
   - Progress toward goals
   - Recent achievements
   - Activity patterns
   - Monthly summaries

### API Endpoints

The plugin provides REST API endpoints for integration:

```
GET /corp-wallet-manager/api/latest
GET /corp-wallet-manager/api/monthly-comparison
GET /corp-wallet-manager/api/predictions
GET /corp-wallet-manager/api/summary
GET /corp-wallet-manager/api/analytics/health-score
GET /corp-wallet-manager/api/analytics/burn-rate
```

All endpoints support optional `corporation_id` parameter for filtering.

## Console Commands

### Backfill Command

```bash
# Backfill specific month
php artisan corpwalletmanager:backfill 2024 12

# Backfill last month only
php artisan corpwalletmanager:backfill --recent

# Backfill specific corporation
php artisan corpwalletmanager:backfill --corporation=98765432

# Backfill custom number of months
php artisan corpwalletmanager:backfill --months=6
```

### Setup Command

```bash
# Initialize or verify permissions
php artisan corpwalletmanager:setup
```

## Troubleshooting

### No Data Showing

1. Check if wallet data exists in SeAT:
```sql
SELECT COUNT(*) FROM corporation_wallet_journals;
```

2. Run backfill command:
```bash
php artisan corpwalletmanager:backfill --months=1
```

3. Check job status in Settings page

### Permission Errors

1. Ensure permissions are set up:
```bash
php artisan corpwalletmanager:setup
```

2. Verify user has correct roles in SeAT Access Management

### Charts Not Updating

1. Check refresh interval in Settings
2. Verify queue workers are running:
```bash
php artisan queue:work
```

3. Check browser console for JavaScript errors

### High Memory Usage

1. Reduce backfill batch size by processing fewer months at once
2. Increase PHP memory limit in `php.ini`
3. Use precomputed options in Settings

## Database Tables

The plugin creates the following tables:

- `corpwalletmanager_monthly_balances` - Monthly balance aggregates
- `corpwalletmanager_predictions` - Balance predictions
- `corpwalletmanager_division_balances` - Division-specific balances
- `corpwalletmanager_division_predictions` - Division predictions
- `corpwalletmanager_settings` - Plugin configuration
- `corpwalletmanager_recalc_logs` - Job execution logs
- `corpwalletmanager_access_logs` - User access tracking
- `corpwalletmanager_daily_summaries` - Daily aggregated data

## Development

### Running Tests

```bash
composer test
```

### Building Assets

```bash
npm install
npm run dev
```

### Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Performance Optimization

### Recommended Settings for Large Corporations

For corporations with >10,000 transactions per month:

1. Enable both precomputed options in Settings
2. Set refresh interval to 15 minutes or higher
3. Run backfill jobs during off-peak hours
4. Consider increasing job timeout in `config/queue.php`

### Database Indexes

The plugin automatically creates optimal indexes. For additional performance, consider:

```sql
-- Add index for frequent corporation queries
CREATE INDEX idx_corp_date ON corporation_wallet_journals(corporation_id, date);
```

## Security

- All endpoints require authentication
- Permissions are enforced at route level
- Member view can hide sensitive ISK values
- Access logging tracks all user interactions
- Data delay option for operational security

## Support

- **Issues**: [GitHub Issues](https://github.com/MattFalahe/Corp-Wallet-Manager/issues)
- **Discord**: Join SeAT Discord and ask in #developers
- **Email**: mattfalahe@gmail.com

## Credits

- **Author**: Matt Falahe
- **Contributors**: See [contributors page](https://github.com/MattFalahe/Corp-Wallet-Manager/graphs/contributors)
- **Thanks to**: SeAT Development Team

## License

This project is licensed under the GPL-2.0-or-later License - see the [LICENSE](LICENSE) file for details.

## Changelog

### Version 1.0.5 (2025-09-13)
- Director and Member views
- Analytics dashboard
- Prediction system
- Division tracking
- Goal management
- Access logging

## Roadmap

### Planned Features

- [ ] Export to Excel/PDF
- [ ] Multi-corporation comparison
- [ ] Custom alert rules
- [ ] API rate limiting
- [ ] Webhook integrations
- [ ] Mobile-responsive improvements
- [ ] Custom report builder
- [ ] Budget planning tools

---

Made with ❤️ for the EVE Online community
