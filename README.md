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
- **Predictive Modeling**: 30-day balance forecasts using ARIMA modeling
- **Cash Flow Analysis**: Daily, weekly, and monthly cash flow waterfalls
- **Division Performance**: Track individual division metrics and ROI
- **Activity Heatmaps**: Visualize transaction patterns over time
- **Automated Reports**: Generate and send executive reports via Discord

### 👥 Member View
- **Corporation Health Dashboard**: Simplified health indicators
- **Goal Tracking**: Savings, activity, and growth targets
- **Achievement System**: Milestones and upcoming events
- **Performance Metrics**: Radar charts showing key performance indicators
- **Weekly Patterns**: Activity analysis by day of week
- **Privacy Controls**: Optional ISK value masking for operational security

### 📊 Reports & Discord Integration
- **Automated Report Generation**: Daily, weekly, and monthly summaries
- **Discord Webhooks**: Beautiful embedded reports sent directly to Discord
- **Custom Date Ranges**: Generate reports for any period
- **Report History**: Track and re-send previously generated reports
- **Alert System**: Low balance warnings and large transaction notifications
- **Executive Insights**: Auto-generated recommendations and trend analysis

### 📚 Help & Documentation
- **Built-in Documentation**: Comprehensive help system accessible within the plugin
- **Search Functionality**: Quick access to relevant information
- **FAQ Section**: Common questions and troubleshooting
- **Command Reference**: Detailed explanation of all console commands

### ⚙️ Settings & Administration
- **Flexible Configuration**: Customizable refresh intervals, colors, and display options
- **Member View Controls**: Toggle sections and set data delays
- **Goal Management**: Set corporation-wide targets
- **Discord Configuration**: Easy webhook setup and testing
- **Maintenance Tools**: Manual job triggers and integrity checking
- **Access Logging**: Track user access for security
- **Job Monitoring**: Real-time status of background processes

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- SeAT 5.0 or higher
- MySQL/MariaDB database
- Supervisor (for queue workers)

## Installation

### Install via Composer

```bash
composer require mattfalahe/corp-wallet-manager
```

### Run Migrations

```bash
php artisan migrate
```

### Optional: Backfill Historical Data

```bash
# Backfill last 3 months of data
php artisan corpwalletmanager:backfill --months=3

# Or backfill all historical data (use with caution)
php artisan corpwalletmanager:backfill --all
```

### Clear Cache & Restart Workers

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
supervisorctl restart all
```

## Configuration

All configuration is done through the plugin's Settings page:
- Navigate to **Corp Wallet Manager → Settings**
- Configure display options, refresh intervals, and goals
- Set up Discord webhooks for automated reports
- Manage permissions and member view settings

For detailed documentation on all features and commands, visit the **Help & Documentation** page within the plugin.

## Scheduled Jobs

The plugin automatically registers the following scheduled jobs:

| Job | Schedule | Description |
|-----|----------|-------------|
| Hourly Update | Every hour at :20 | Updates wallet data for the last hour |
| Daily Aggregation | Daily at 01:00 | Aggregates daily statistics |
| Compute Predictions | Daily at 02:00 | Calculates balance predictions |
| Division Predictions | Daily at 02:30 | Computes division-specific predictions |
| Monthly Report | Monthly (1st at 03:00) | Generates monthly report |
| Weekly Report | Weekly (Mondays at 03:30) | Generates weekly report |

## Console Commands

### Backfill Commands
```bash
# Backfill specific month
php artisan corpwalletmanager:backfill 2024 12

# Backfill last 3 months
php artisan corpwalletmanager:backfill --months=3

# Backfill division data
php artisan corpwalletmanager:backfill-divisions
```

### Maintenance Commands
```bash
# Run database integrity check
php artisan corpwalletmanager:integrity-check

# Fix issues automatically (use with caution)
php artisan corpwalletmanager:integrity-check --fix

# Generate report manually
php artisan corpwalletmanager:generate-report --period=monthly
```

For detailed command documentation, see the built-in **Help & Documentation** page.

## Permissions

Assign these permissions in SeAT's Access Management:

- `corpwalletmanager.view` - Basic plugin access (required)
- `corpwalletmanager.director_view` - Access to director dashboard
- `corpwalletmanager.member_view` - Access to member dashboard
- `corpwalletmanager.settings` - Manage plugin settings

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

---

Made with ❤️ for the EVE Online community
