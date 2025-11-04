# Changelog

All notable changes to Corp-Wallet-Manager will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-11-04

### Added
- **Reports System**: Comprehensive report generation for daily, weekly, and monthly summaries
- **Discord Integration**: Automated report delivery via Discord webhooks with beautiful embeds
- **Custom Date Range Reports**: Generate reports for any custom time period
- **Report History**: View, track, and re-send previously generated reports
- **Alert System**: Automated notifications for low balances and large transactions
- **Help & Documentation Page**: Built-in comprehensive documentation accessible within the plugin
- **Search Functionality**: Quick search across all help documentation
- **Interactive FAQ**: Common questions and troubleshooting guides with collapsible sections
- **Integrity Check Command**: New `corpwalletmanager:integrity-check` command to verify database consistency
- **Report Generation Command**: Manual report generation via `corpwalletmanager:generate-report`
- **Enhanced Logging**: Improved job execution logging and error tracking
- **Executive Insights**: Auto-generated recommendations and trend analysis in reports

### Changed
- **Job Scheduling Refactor**: Complete overhaul of job scheduling system for better reliability
  - Updated `UpdateHourlyWalletData` to run at :20 past each hour
  - Changed `DailyAggregation` to run at 01:00 daily
  - Changed `ComputePredictions` to run at 02:00 daily  
  - Changed `ComputeDivisionPredictions` to run at 02:30 daily
  - Added monthly report generation at 03:00 on the 1st of each month
  - Added weekly report generation at 03:30 every Monday
- **Job Registration**: Jobs are now automatically registered on plugin installation
- **Error Handling**: Improved error messages and graceful failure handling across all commands
- **UI Improvements**: Enhanced user interface across all views for better usability
- **Code Quality**: Major refactoring for better maintainability and performance

### Fixed
- **Month Boundary Bug**: Fixed integrity constraint violation in `UpdateHourlyWalletData` when transactions occur at month boundaries
- **Duplicate Entries**: Enhanced duplicate prevention in monthly balance aggregation
- **Prediction Edge Cases**: Improved handling of predictions with limited historical data
- **Missing Corporation Data**: Better handling when corporation data is unavailable
- **Route Conflicts**: Resolved route naming conflicts with reports

### Removed
- **Setup Command**: Removed deprecated `corpwalletmanager:setup` command (permissions now auto-created)
- **Redundant Jobs**: Cleaned up unnecessary scheduled jobs
- **Obsolete Code**: Removed unused functions and deprecated methods

### Security
- **Access Controls**: Enhanced permission checking for report generation and Discord integration
- **Webhook Validation**: Added validation for Discord webhook URLs
- **Data Sanitization**: Improved input sanitization across all forms

## [1.1.1] - 2025-11-02

### Added
- Discord Integration for reports

### Fixed
- Fixed `UpdateHourlyWalletData` to resolve integrity constraint violation on month boundary

## [1.1.0] - 2025-09-18

### Added
- **ARIMA Prediction Model**: Advanced prediction model for corporations with sufficient historical data
- **Automatic Model Switching**: 
  - Days 1-60: Uses simple linear model
  - Day 61+: Automatically upgrades to ARIMA model
  - Continues with increasing accuracy as more data accumulates
- **Graceful Degradation**: System falls back to simple model if ARIMA fails
- **Asset Publishing**: Additional assets now published with the plugin

### Changed
- **Prediction System**: Enhanced with dual-model approach for better accuracy
- **Data Requirements**: Flexible prediction generation based on available data

## [1.0.5] - 2025-09-13

### Added
- **Members View**: Fully functional member dashboard with modular sections
- **Section Toggle**: Options to hide/show individual sections in member view
- **Division Charts**: New layout for cash flow tab in directors view with division-specific charts
- **Modular Design**: Member tab now fully modular with customizable visibility options

### Changed
- **Cash Flow Layout**: Redesigned cash flow visualization with improved division breakdowns
- **Member Dashboard**: Enhanced member view with better organization and layout

## [1.0.4] - 2024-09-09

### Fixed
- Merged latest fixes from dev-testing branch
- Various stability improvements

## [1.0.3] - 2024-09-09

### Changed
- Synced changes from dev-testing-final branch
- Code cleanup and optimization

## [1.0.2] - 2024-09-02

### Fixed
- Fixed backfill jobs execution issues
- Improved job reliability

## [1.0.1] - 2024-09-01

### Fixed
- Fixed sidebar menu display issues
- Improved navigation consistency

## [1.0.0] - 2024-09-01

### Added
- **Director View**: Comprehensive financial dashboard for corporation directors
  - Real-time balance tracking across all divisions
  - Advanced analytics with health scores and burn rates
  - 30-day balance predictions
  - Cash flow analysis with daily/weekly/monthly breakdowns
  - Division performance tracking
  - Activity heatmaps
- **Member View**: Simplified dashboard for corporation members
  - Corporation health indicators
  - Goal tracking
  - Achievement system
  - Performance metrics
  - Weekly activity patterns
  - Privacy controls for ISK values
- **Analytics System**: 
  - Health score calculations
  - Burn rate analysis
  - Financial ratio tracking
  - ROI metrics
- **Prediction System**: 
  - Balance forecasting
  - Trend analysis
  - Division-specific predictions
- **Settings Management**:
  - Customizable refresh intervals
  - Color scheme configuration
  - Member view controls
  - Goal management
  - Access logging
- **Database Schema**:
  - Monthly balance aggregation tables
  - Prediction storage
  - Division tracking
  - Settings persistence
  - Access logs
- **Console Commands**:
  - `corpwalletmanager:backfill` - Historical data import
  - `corpwalletmanager:setup` - Permission initialization
- **Scheduled Jobs**:
  - Hourly wallet data updates
  - Daily aggregation
  - Prediction computation
  - Division analysis
- **API Endpoints**:
  - Latest balance data
  - Monthly comparisons
  - Predictions
  - Summary statistics
  - Health scores
  - Burn rates

### Security
- Permission-based access control
- User access logging
- Optional ISK value masking
- Data delay options for operational security

---

## Version History Summary

- **2.0.0**: Major feature release with Reports, Discord Integration, and Help Documentation
- **1.1.x**: ARIMA predictions and Discord integration
- **1.0.x**: Initial release with Director/Member views and basic analytics

## Links

- [Repository](https://github.com/MattFalahe/Corp-Wallet-Manager)
- [Issue Tracker](https://github.com/MattFalahe/Corp-Wallet-Manager/issues)
- [Releases](https://github.com/MattFalahe/Corp-Wallet-Manager/releases)

## Upgrade Instructions

### Upgrading to 2.0.0

1. Update via Composer: `composer update mattfalahe/corp-wallet-manager`
2. Run migrations: `php artisan migrate`
3. Clear caches: `php artisan config:clear && php artisan route:clear && php artisan view:clear`
4. Restart workers: `supervisorctl restart all`
5. Configure Discord (optional): Visit Settings → Reports section
6. Run integrity check (optional): `php artisan corpwalletmanager:integrity-check`

### Upgrading from 1.0.x to 1.1.x

1. Update via Composer: `composer update mattfalahe/corp-wallet-manager`
2. Run migrations: `php artisan migrate`
3. Clear caches: `php artisan config:clear && php artisan route:clear && php artisan view:clear`
4. Restart workers: `supervisorctl restart all`

---

Made with ❤️ for the EVE Online community
