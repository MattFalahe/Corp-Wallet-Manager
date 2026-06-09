# Changelog

All notable changes to Corp-Wallet-Manager will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - The Ecosystem Era (2026-06-09)

This is the release where Corp Wallet Manager stopped being a standalone wallet tool and became a first-class member of the manager-suite ecosystem. The Discord delivery layer is now built around per-corporation webhooks with role mentions and per-category subscriptions; the per-character contribution cache unlocks Top Contributors, Profit Attribution by Activity, Expense Attribution by Category, and Alliance Tax Reconciliation as a stack of four director-view tabs; scheduling moves from two hardcoded ScheduleSeeder entries to a per-corp UI that operators control directly; and the namespace migration drops the `Seat\` prefix that was blocking Manager Core's plugin bridge from seeing CWM as part of the suite. Cross-plugin integration is the through-line: when Manager Core is installed CWM publishes the wallet and member topics other plugins subscribe to and exposes a fan-out of PluginBridge capabilities; when Mining Manager is installed the contribution classifier uses MM's authoritative `mining_taxes.transaction_id` linkage to split tax payments from voluntary donations; HR Manager consumes the contribution analytics for its member assessment classifier. None of the integrations require a composer dependency, so plugins can be installed and uninstalled in any order without breakage.

The member page also gets a structural rework so each of the three angles a member cares about has its own room to breathe. The page opens with a three-tab nav: a Corp Wallet tab as the default (corp health, trend, activity, performance score, goals, balance chart, radar, weekly pattern, monthly summary, upcoming events, plus the Top Contributors leaderboard so existing user muscle memory of "open the page, see corp" stays intact); a My Contribution tab pulling the personal-contribution card, the Mining Manager tax compliance card, and the My Milestones ladder together as the "what I have done for the corp" lens; and a new My Personal Wallet tab as the "how am I doing personally" lens that aggregates the viewer's SeAT personal wallet across every character they own. Three leaderboard privacy modes (ISK Visible, Percentage, Rank Only) let operators decide how transparent the corp is, enforced server-side so a member opening devtools cannot reveal hidden values.

### Added

**Per-character contribution tracking and Top Contributors**

- Per-character contribution cache. An hourly job classifies every corp wallet journal row into a per-character bucket (Ratting, Mission, Industry, Tax Payment, Voluntary Donation, Withdrawal) and maintains the `corpwalletmanager_character_contributions` precomputed cache. Bounty and mission rows use `context_id` for attribution so NPC faction IDs no longer surface as ratters. ESS escrow transfers are classified into the ratting bucket. Industry job tax for members on corp-owned structures lands in a dedicated Industry bucket.
- Top Contributors leaderboard (Director view) with main-character grouping (alts collapse under the main, click the caret to expand). Columns: Ratting, Mission, Industry, Tax Payment, Voluntary Donation, Total, plus Alliance Tax and Net to Corp when alliance rates are configured.
- Top Contributors member-facing view with three privacy modes: ISK Visible (raw amounts), Percentage (share of corp total without raw ISK), Rank Only (names and ranks but no amounts). The mode is enforced server-side. The viewer's own row is highlighted; if they sit outside the top N a separator and a pinned row at the bottom always show their position.
- Two supporting charts sit above the Top Contributors leaderboard so a director scans the shape of the period before drilling into individual rows. A Contribution Concentration doughnut buckets income into Top 1 / Top 2-5 / Top 6-10 / Everyone else (a Pareto split) with a story line underneath summarising how much of the period's contributions the top five carried. A Members vs External Contributors stacked bar puts the current and prior month side by side, each split into the share that came from current corp members versus characters whose contribution rows survive but who have moved out of the corp (or were never in it but paid industry tax / dropped a donation). Both charts share the leaderboard's period selector and apply the same eligibility filters (player IDs only, corp- and alliance-self rows excluded, income-only, current-corp-member detection via `character_affiliations` with the same fail-open guard the leaderboard uses) so all three surfaces reconcile on screen. A single `/api/analytics/contributor-mix` endpoint returns both shapes in one round trip and is cached for 5 minutes in Redis.

**Director analytics tabs**

- Profit Attribution by Activity tab. Pie chart of per-activity share + per-activity efficiency table (total, members, avg per member, percent of profit, trend vs prior period) + multi-line trend over the trailing 6 / 12 / 18 / 24 months. Tax payment and voluntary donation split when Mining Manager is installed, merged Donations bucket when not.
- Expense Attribution by Category tab. Hardcoded nine-category taxonomy that survives across CCP ref_type drift: Alliance Tax, Corp Withdrawal, Market Fees, Office Rental, Industry Costs, Contracts, Structure & Sovereignty, Insurance & War, Other. Pie + per-category table + multi-line trend with a click-to-toggle legend.
- Alliance Tax Settings + Reconciliation tab. Five per-bucket rates (Ratting, Mission, Tax Payment, Voluntary Donation, Industry, all default zero, fractional rates supported). Recipient party IDs and description keywords match outgoing remits; rules OR-combine so either signal alone is enough. Grouped bar chart of expected vs actual over the trailing 3 / 6 / 12 months with a per-month detail table flagging overpaid / underpaid gaps in colour. A recipient picker in Settings lists the top 20 recent outflow recipients with resolved names so operators do not type IDs by hand.
- Entity Name Resolver with ESI fallback. Layered lookup (`character_infos`, `corporation_infos`, `alliance_infos`, `universe_names`, ESI `/universe/names/`) used by the Top Contributors leaderboard fallback, the recipient picker, and the Wallet Trace diagnostic so party IDs render as `Name [ID]` instead of bare snowflakes.

**Reports**

- Annual and Quarterly report types backed by multi-section PDF templates: cover page, executive summary, monthly balance trend, top 10 contributors, activity mix, notable transactions, division performance, alliance tax remits, milestones reached, risk assessment, and a Year-over-Year (or Quarter-over-Quarter) comparison from prior reports. CSV export carries seven matching retrospective sections.
- Weekly and monthly reports get the retrospective enrichment too: top contributors, activity mix, expense attribution, anomaly summary, alliance tax expected vs actual, Mining Manager compliance, monthly breakdown, notable transactions, milestones, and prior-period comparison. Daily reports stay terse on purpose (balance + flow + risk indicator) for the morning pulse-check.
- PDF and CSV download from every Report History row and from the report view modal. PDF is rendered via `barryvdh/laravel-dompdf`; CSV groups balance summary, daily changes, income/expense, transaction breakdown, division summary, risk assessment, and the retrospective sections into labelled sections separated by blank rows so Excel opens it directly.

**Scheduling and delivery**

- Scheduled Reports settings panel. Per (corporation, report type) row with explicit hour, minute, and day-axis columns. Daily, Weekly, Monthly, Quarterly, Annual cadences each get a day-axis picker that toggles by cadence (none for daily, day-of-week for weekly, day-of-month for monthly and quarterly, month and day for annual). Day-of-month capped at 28 so February never skips a monthly schedule. The dispatcher cron `corpwalletmanager:dispatch-scheduled-reports` ticks every 5 minutes so a 03:00 slot fires within five minutes of 03:00 rather than slipping to 03:59 the way an hourly tick would.
- Multiple Discord webhooks per corp, each with its own role mention, choice of report types (Daily / Weekly / Monthly / On-Demand), and choice of alert types. Per-webhook delivery health tracking (success count, failure count, last error).
- Discord Role Picker. When a Discord role provider is installed (SeAT Broadcast, SeAT Connector, legacy warlof), webhook role mentions can be picked from an inline list with colour dots, role names, snowflake IDs, and a live preview pill. The webhook list summary gains a Role Mention column rendering each saved webhook's role as a pill.
- Notification Routing Map (Settings tab). Read-only snapshot of every notification CWM can deliver, which webhook(s) currently route each category, the resolved role-mention pill, and the scope (Global vs Corp). Surfaces a delivery-gap warning when alliance tax is configured but no webhook subscribes to any report category.
- Daily, Weekly, and Monthly entries added to the on-demand report dropdown so directors can fire those cadences without waiting for the scheduler.

**Anomaly detection**

- Two new alert kinds added to the hourly `DetectWalletAlerts` job. Contribution Drop fires when a member's prior 3-month contribution average collapses to less than 20% of the previous 3-month window, latched per (corp, character) and cleared on recovery above 50% so re-stalls re-fire. Unusual Recipient fires when a corp withdrawal goes to a party with no prior interaction in the 7-day cold window. Both deliver to subscribed Discord webhooks and publish to MC EventBus under `member.contribution.drop_detected` and `wallet.unusual_recipient_detected`.

**Cross-plugin integration**

- Event Bus publishing. When Manager Core is installed, CWM publishes `wallet.transaction_detected`, `wallet.balance_low`, `member.contribution.drop_detected`, `wallet.unusual_recipient_detected`, `member.contribution.stalled`, `member.contribution.milestone`, and `member.tax.compliance_dropped` to MC's cross-plugin EventBus. CWM stays fully functional standalone when Manager Core is absent.
- HR Manager integration. PluginBridge capabilities for HR consumption (`contribution.getCharacterTrend`, `contribution.getActivityGaps`, `contribution.getNetPosition`, `contribution.getLifetimeSummary`, `contribution.getCharacterPercentile`, `contribution.getCharacterTaxCompliance`, `wallet.getDirectorAttribution`) plus the three milestone events above. HR composes these into per-member assessment signals on its member profile page. State for milestone events is tracked in `corpwalletmanager_member_milestone_state` so each transition publishes exactly once and recovery re-arms the next emit.
- Corp-wide `contribution.getCorpMemberSummary` capability (PluginBridge). One grouped query over `corpwalletmanager_character_contributions` returns a per-member financial roll-up for every member with wallet activity in the corp (registered or not): lifetime contribution, ratting income, tax paid, withdrawals, net position (contributed minus withdrawn), active months, and best-effort mining-tax compliance from Mining Manager. The all-member counterpart to the per-character `getNetPosition` / `getLifetimeSummary` calls, so HR Manager's Corp Health wallet cards cover the whole corp rather than only the members in its own assessment cache.
- Mining Manager integration. The contribution classifier checks `mining_taxes.transaction_id` first when classifying donation rows; when MM has matched a journal id to a tax invoice, that journal id is authoritatively a tax payment regardless of description text. Description tax-code extraction stays as a fallback for invoices MM has not yet processed. Top Contributors' Tax Payment column shows `paid / owed` with compliance percentage when MM is installed; below 80% renders in warning yellow.

**Member-facing surface**

- Three-tab member view (Corp Wallet / My Contribution / My Personal Wallet). Each tab defers its first load to a Bootstrap `shown.bs.tab` hook so a viewer who never opens a tab pays nothing for it. The refresh button in the tab nav refreshes whichever tab is currently active.
- My Contribution card. Headline ISK with a character-count chip and a period selector (This Month / Last Month), main character name as a prominent heading, one-line explainer reading "Aggregated across your main character plus N alts", trend pill against the prior month, rank-of-corp pill, percentile badge, lifetime and months-active stats, and a per-bucket sparkline strip that breaks the headline number into ratting / mission / industry / tax / donation / withdrawal.
- My Tax Compliance card (conditional on Mining Manager). The viewer's owed / paid / compliance percentage for the period with a per-character expansion when alts have separate compliance.
- My Milestones card walking the lifetime ladder (1B / 5B / 10B / 25B / 50B / 100B) showing the rungs crossed and the next rung's percent-to-go.
- My Personal Wallet tab aggregating the viewer's SeAT personal wallet across every character they own, with no corp filter. Income, expense, net flow with trend pills against the prior period; top 5 income and expense ref types; 6-month end-of-month balance sparkline; top 5 biggest income and expense transactions (player-typed reason shown when the journal row carries one); per-character breakdown table. Backed by a precomputed `corpwalletmanager_personal_wallet_aggregates` table that is refreshed hourly by `ComputePersonalWalletAggregates`.
- Five operator toggles in Settings -> Member View control which of the four new surfaces each corp's members see, plus the leaderboard size (5 / 10 / 20) and the privacy mode.

**Ops, diagnostics, and data export**

- `corpwalletmanager:initialize` command runs every CWM cache-populating step in the right order for first install and post-upgrade so operators do not have to figure out the sequence by reading the docs: wallet backfill, division backfill, daily aggregation, predictions (corp + division), contribution backfill, personal wallet aggregates backfill, milestone state recompute, and wallet alert detection. Flags: `--months=12`, `--days=180`, `--skip=`, `--force`, `--queue`. Each step prints a section header and a progress bar. The command is idempotent and safe to re-run.
- Progress bars on every backfill command (wallet, division, contributions, personal wallet aggregates) showing current/total, percentage, elapsed time, ETA, and the per-item label. Wallet and division backfills gained a `--queue` flag so the previous dispatcher-then-return shape stays available.
- Diagnostic page at `/corp-wallet-manager/diagnostic`, admin-only via `corpwalletmanager.settings`, intentionally NOT in the sidebar per the suite convention. Tier-1 universal tabs (Health Checks, Master Test, System Validation, Settings Health, Data Integrity) plus four CWM-specific tabs. Wallet Trace walks one journal row through the full classify -> alert -> publish pipeline. Donation Audit shows a batch view of every `player_donation` row in a corp + month with the classifier bucket decision and the Mining Manager link side by side, with a suspect-row highlight. Schedule Trace takes a corporation + cadence and shows the schedule row, dispatcher status, the webhook delivery preview using the same selection logic delivery uses at runtime, and the computed report window mirroring the dispatcher's date-window math so the operator can confirm exactly what period the next firing covers. Notification Testing fires test webhook deliveries on demand without waiting for a real trigger. The System Validation tab carries a Cross-plugin Integration block with a detection row for every sibling (Manager Core, Mining Manager, HR Manager, Structure Manager, SeAT Broadcast, SeAT Connector), and the Data Integrity tab carries Schedule Status (every report schedule ordered by next firing with last-status and enabled flags), Personal Wallet Aggregator Status (row count, last refresh, and a gap count of player characters missing an aggregate this period), and Anomaly State (open contribution-drop latches with corp and character names resolved).
- Data Export feature at Settings -> Data Export. Pick sections (journal, contributions, reports, alerts, anomaly state) + date range + format (ZIP of CSVs or single multi-section CSV). Queued generation, signed download URLs valid for 24 hours, recent exports table with one-click re-download and delete. Corp-scoped: non-admins can only export reports for corporations they have a character in.
- Player-typed reason on Biggest Income / Expense transactions. The personal wallet tab now surfaces the in-game memo the donor wrote on a journal row alongside the amount and party name.

### Changed

- **Namespace migration: dropped the `Seat\` prefix.** CWM was the only plugin in the suite whose PHP namespace included the `Seat\` vendor prefix from SeAT's plugin scaffolding (every other plugin uses `MiningManager\...`, `StructureManager\...`, etc.). This inconsistency caused Manager Core's Plugin Bridge to mark CWM as Offline. Now `namespace CorpWalletManager\...` throughout. **Operator action required on upgrade**: any queued background jobs in Redis serialise their class FQN as `Seat\CorpWalletManager\Jobs\...`, and those FQNs no longer exist after the rename. For Docker users running the standard SeAT compose stack, bringing the stack down and back up (see the full command under "Upgrading to 3.0.0" below) fully resets the default Redis container and clears the queue + cache + sessions in one shot. A plain restart is NOT sufficient. For non-Docker installs run `php artisan queue:flush && php artisan config:clear && php artisan cache:clear`. No database migrations are involved.
- **Webhook delivery rewrite.** Report delivery now uses a single retry-aware HTTP path (5xx and 429 with Retry-After handling) instead of the two hand-rolled cURL calls v2 shipped. `allowed_mentions` is locked down so report content can never trigger an unintended `@everyone` / `@here` ping.
- **Multi-webhook subscription model.** The single global `discord_webhook_url` setting from v2 is migrated automatically into a webhook row on upgrade and the legacy settings row is left in place as dormant data. No reconfiguration is required for the carry-over, but the new per-corp, per-category subscription model is where any further wiring lands.
- **Scheduled reports moved from cron to UI.** The two hardcoded ScheduleSeeder entries that fired blanket for every corp regardless of intent are retired (listed in `getDeprecatedSchedules()` so AbstractScheduleSeeder removes them on the next seed pass). The new `corpwalletmanager_report_schedules` table is seeded with default weekly (Monday 03:30 UTC) and monthly (day 1 at 03:00 UTC) rows for every corp with wallet history on first upgrade so nobody loses their existing cadence.
- **Scheduled reports fan out per corporation.** The dispatcher produces one report per corporation with wallet data and delivers each to that corporation's subscribed webhooks, so a single scheduled cadence covers every tracked corp at once instead of a single global run.
- **Executive / Financial / Division report shapes are now distinct.** v2 rendered the same balance-income-expense-risk tuple with only the title swapped, which made the dropdown a lie about what each report was for. Each shape now composes a genuinely distinct payload: Executive is a KPI snapshot with a one-line headline, risk assessment, and the top three contributors collapsed into a single block; Financial is the deep dive with full per-ref_type income and expense breakdowns, activity attribution, expense attribution, alliance tax expected vs actual, and MM compliance when present; Division is per-wallet-division with opening + closing balance per division, top five incoming and outgoing ref_types within each division, and a summary of inter-division transfers the corp totals exclude. The Discord embed varies its field composition per type so the post matches the PDF.
- **Discord footer normalised** from "CorpWallet Manager" to "Corp Wallet Manager [CWM]" so the brand reads consistently with the rest of the suite.
- **Member view restructured into three tabs** (Corp Wallet / My Contribution / My Personal Wallet). See the prologue above and the Added section for the full surface.
- **Settings page reorganised** around a left-hand sidebar (matching Mining Manager and Structure Manager). Sections: Configuration (General / Member View / Alert Thresholds / Alliance Tax), Integrations (Discord Webhooks / Notification Routing), Operations (Maintenance / Job Status / Access Logs). Active section persists in the URL hash.
- **Help & Documentation Overview consolidated.** The Plugin Information, Version Status, Welcome, What Is CWM, and Key Features panels were collapsed into a single Overview entry that scrolls through every panel in order, matching the Mining Manager and Manager Core layout. What's New in v3.0.0 lives inside Overview as a green callout rather than a separate sidebar entry, and Plugin Information carries the four canonical quick-link chips (GitHub repo, changelog, issues, README). The legacy `nav_*` lang keys are kept in `en/help.php` so a bookmarked `#version-status` / `#welcome` / `#what-is-cwm` / `#key-features` hash still resolves even though those sidebar links no longer exist.

### Fixed

- **Inter-division transfer double-counting.** ISK moved between divisions of the same corporation (rows where `first_party_id` and `second_party_id` both equal the corporation id) was counted on both sides of the journal in v2: the receiving division's positive amount inflated income, the sending division's negative amount inflated expenses. The pair always netted to zero so the headline balance was correct, but income, expense, breakdown, per-division, and runway numbers were all overstated by the transfer amount everywhere CWM aggregated the journal. A shared `JournalFilters` helper now filters these rows out plugin-wide: the contribution classifier, every scheduled-report income and expense query, the large-transaction alert scan, every chart endpoint, the member-view aggregates, the wallet and division backfills (per-division balances were the worst hit, inflated twofold by internal transfers), the daily and hourly aggregation jobs, and every prediction and backtest model. Every chart, backfill, prediction, and member-facing number now reflects real corp income and expense instead of corp-internal rebalancing.
- **Scheduled Discord reports never delivered.** The v2 report command ran corp-less, so the weekly and monthly scheduled summaries built reports against no corporation and never reached any webhook. The dispatcher now produces one report per corporation with wallet data and delivers each to that corporation's subscribed webhooks.

### Security

- **Settings saves no longer leak the Discord webhook URL to the application log.** v2 dumped the full request payload (including the webhook URL) to `storage/logs` on every settings save. The dump is removed.
- Webhook URLs are validated to be Discord webhook endpoints, and the stored URL is hidden from model serialization.

### Known Limitations

- **AIR Daily Goal Reward entries** (75K-ish ISK from completing daily activities) are not yet classified. The ref_type used by CCP is not in SeAT's wallet language file; once verified via Diagnostic -> Wallet Trace it can be added as a one-line classifier case.
- **End-to-end data lag is 1.5 to 2.5 hours typical** from an in-game EVE event to it showing up on a CWM surface. The pipeline runs through four stages: CCP's ESI cache (1 hour), SeAT's wallet poll (up to 1 hour, depending on schedule), CWM's hourly aggregation jobs, and a 5-minute Redis read cache on the heaviest member-facing endpoints. A donation made 30 seconds ago will not appear in Top Contributors yet; this is normal and is documented in Help -> Data Freshness so operators can set member expectations correctly.

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

- **3.0.0**: The Ecosystem Era. Per-character contribution cache, four director analytics tabs, scheduled reports UI, anomaly detection, Manager Core / Mining Manager / HR Manager integration, three-tab member view, namespace migration.
- **2.0.0**: Major feature release with Reports, Discord Integration, and Help Documentation.
- **1.1.x**: ARIMA predictions and Discord integration.
- **1.0.x**: Initial release with Director / Member views and basic analytics.

## Links

- [Repository](https://github.com/MattFalahe/Corp-Wallet-Manager)
- [Issue Tracker](https://github.com/MattFalahe/Corp-Wallet-Manager/issues)
- [Releases](https://github.com/MattFalahe/Corp-Wallet-Manager/releases)

## Upgrade Instructions

### Upgrading to 3.0.0

1. Update via Composer: `composer require mattfalahe/corp-wallet-manager`.
2. Bring the SeAT Docker stack down and back up so the default Redis container clears queued v2-namespaced jobs:
   ```
   docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down
   docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d
   ```
3. Run the one-shot initializer once the stack is back up:
   ```
   docker exec -it seat-docker-front-1 php artisan corpwalletmanager:initialize
   ```
   The command sequences every CWM cache-populating step in the right order with per-step progress bars and is safe to re-run.

### Upgrading to 2.0.0

1. Update via Composer: `composer update mattfalahe/corp-wallet-manager`
2. Run migrations: `php artisan migrate`
3. Clear caches: `php artisan config:clear && php artisan route:clear && php artisan view:clear`
4. Restart workers: `supervisorctl restart all`
5. Configure Discord (optional): Visit Settings -> Reports section
6. Run integrity check (optional): `php artisan corpwalletmanager:integrity-check`

### Upgrading from 1.0.x to 1.1.x

1. Update via Composer: `composer update mattfalahe/corp-wallet-manager`
2. Run migrations: `php artisan migrate`
3. Clear caches: `php artisan config:clear && php artisan route:clear && php artisan view:clear`
4. Restart workers: `supervisorctl restart all`

---

Made for the corporations of New Eden.
