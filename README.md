# Corp Wallet Manager

[![Latest Version](https://img.shields.io/packagist/v/mattfalahe/corp-wallet-manager?label=release&color=667eea)](https://packagist.org/packages/mattfalahe/corp-wallet-manager)
![SeAT](https://img.shields.io/badge/SeAT-5.x-764ba2)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

**v3.0.0 "The Ecosystem Era"** is the release where Corp Wallet Manager (CWM) stopped being a standalone corp wallet tracker and became a first-class member of the manager-suite ecosystem. It still works perfectly on its own, but in v3 it publishes events to the suite's cross-plugin event bus, exposes capabilities its sibling plugins consume, and pulls richer data back when those siblings are installed. Wire it up next to Manager Core, Mining Manager, and HR Manager and the whole suite gets smarter about where your corp's ISK comes from, where it goes, and who is contributing.

> **Mental model**: CWM is the corp wallet plugin for the manager suite. It tracks where ISK comes from, where it goes, and who is contributing, and it talks to your other suite plugins about all three.

---

## What "The Ecosystem Era" means

CWM works fully standalone with zero companion plugins installed. Every integration below is detected at runtime via `class_exists`, never through a Composer dependency, so you can install and uninstall any suite plugin in any order without breaking CWM (or the sibling). Install the slice of the suite that fits your corp; CWM lights up the matching surface area and degrades cleanly where a sibling is absent.

### 🌐 Ecosystem Integration

When Manager Core is installed, CWM publishes seven wallet and member topics to Manager Core's cross-plugin EventBus and exposes a fan-out of PluginBridge capabilities that other plugins read. HR Manager consumes those capabilities (per-character contribution trend, net position, lifetime summary, percentile, tax compliance, director attribution, and a corp-wide member summary) to fold wallet signals into its member assessment classifier, and subscribes to the three milestone events for its member profile timeline. When Mining Manager is installed, the contribution classifier uses MM's authoritative `mining_taxes.transaction_id` linkage to split tax payments from voluntary donations cleanly. None of this is required: with no companion plugin present the topics are no-ops and the capabilities sit registered but unused.

### 📊 Director Analytics

Four director-view tabs answer the operator's most common questions about corp finances, backed by a per-character contribution cache that classifies every journal row into a per-member bucket.

| Tab | Question it answers |
|-----|---------------------|
| **Top Contributors** | Who paid what? Per-member ranking grouped by main character (alts collapse under the main). Columns: Ratting, Mission, Industry, Tax Payment, Voluntary Donation, Total. When Mining Manager is installed the Tax Payment column shows `paid / owed` with compliance percentage; when alliance rates are configured, Alliance Tax and Net to Corp columns surface. Two charts sit above the table: a Contribution Concentration doughnut (a Top 1 / Top 2-5 / Top 6-10 / everyone-else Pareto split) and a Members vs External Contributors stacked bar comparing this month to last. |
| **Profit Attribution by Activity** | What activity drove the corp's profit this period? Pie chart of per-activity share, per-activity efficiency table (total, members, avg per member, percent of profit, trend), and a multi-line trailing-months trend. |
| **Expense Attribution by Category** | Where did the corp's ISK go? A hardcoded nine-category taxonomy that survives across CCP ref_type drift (Alliance Tax, Corp Withdrawal, Market Fees, Office Rental, Industry Costs, Contracts, Structure & Sovereignty, Insurance & War, Other). Pie, per-category table, and a click-to-toggle multi-line trend. |
| **Alliance Tax Reconciliation** | Is the corp's alliance remit math right? Expected (per-bucket income times per-bucket rate) vs actual (outgoing payments matching configured recipient IDs or description keywords) over the trailing 3 / 6 / 12 months, with a per-month detail table flagging overpaid and underpaid gaps. |

### 👤 Member-Facing Surface

The member page opens with a three-tab nav so each angle a member cares about gets its own room.

| Tab | What is on it |
|-----|---------------|
| **Corp Wallet** | Corp health, trend, activity, performance score, goals, balance chart, radar, weekly pattern, monthly summary, and the Top Contributors leaderboard (so opening the page still shows the corp at a glance). |
| **My Contribution** | Personal contribution card (headline ISK, rank, percentile, lifetime, months active, per-bucket sparkline), a Mining Manager tax compliance card when MM is installed, and a My Milestones ladder (1B / 5B / 10B / 25B / 50B / 100B). |
| **My Personal Wallet** | The viewer's SeAT personal wallet aggregated across every character they own, no corp filter. Income, expense, and net flow with trend pills, top 5 ref types each way, a 6-month balance sparkline, the top 5 biggest transactions each way (with the player-typed reason surfaced), and a per-character breakdown. |

Three leaderboard privacy modes let operators decide how transparent the corp is, enforced server-side so a curious member cannot reveal hidden values via devtools:

- **ISK Visible** for transparent corps that want raw amounts.
- **Percentage** for corps that want the relative split without the raw numbers.
- **Rank Only** for big corps that want names and positions but no numbers at all.

### 📅 Scheduled Reports and Report Types

Scheduling moved from two hardcoded ScheduleSeeder entries (that fired blanket for every corp) to a per-corp, per-cadence UI panel. The dispatcher cron ticks every 5 minutes so a 03:00 slot fires within five minutes of 03:00. The eight report types each render a genuinely distinct payload:

| Type | Shape | Best for |
|------|-------|----------|
| **Daily** | Terse: balance + flow + risk indicator | Morning pulse-check (5 second read) |
| **Weekly** | Retro sections: top contributors + activity mix + alliance tax + anomalies | Monday leadership sync |
| **Monthly** | Full retro: contributors + mix + alliance tax + notable transactions + anomalies + MM compliance + milestones | Month-end close |
| **Quarterly** | Multi-section PDF: cover + executive summary + QoQ comparison | Board pack |
| **Annual** | Multi-section PDF: cover + executive summary + YoY comparison + milestones reached | Yearly retrospective |
| **Executive** | KPI snapshot + headline + risk + top 3 contributors | CEO one-pager |
| **Financial** | Deep dive: per-ref_type income + expense + activity attribution + alliance tax + MM compliance | Finance officer audit |
| **Division** | Per-wallet-division balances + top ref_types + inter-division summary | Per-department accountability |

Daily, Weekly, and Monthly cadences can also be fired on demand from the dropdown without waiting for the scheduler. PDF and CSV export are available from every Report History row.

### 🔔 Anomaly Detection

Two alert kinds beyond the v2 large-transaction and low-balance pair. **Contribution Drop** fires when a member's prior 3-month contribution average collapses to under 20% of the previous 3-month window, latched per member and cleared on recovery above 50% so a re-stall re-fires. **Unusual Recipient** fires when a corp withdrawal goes to a party with no prior interaction in the last 7 days. Both deliver to subscribed Discord webhooks and (when Manager Core is installed) publish to its EventBus. Thresholds are configured in Settings; 0 disables.

Delivery rides on multiple Discord webhooks per corp, each with its own role mention, choice of report types, and choice of alert types, plus per-webhook delivery health (success and failure counts, last error). The Notification Routing Map surfaces every notification CWM can deliver and which webhook(s) currently route it, and the Discord Role Picker reads role lists from SeAT Broadcast, SeAT Connector, or legacy warlof when present so role mentions can be picked from a list instead of pasted by snowflake.

### 📤 Data Export

Settings -> Data Export. Pick sections (journal, contributions, reports, alerts, anomaly state), a date range, and a format (ZIP of CSVs or a single multi-section CSV). Generation is queued, download URLs are signed and valid for 24 hours, and a recent exports table offers one-click re-download and delete. Exports are corp-scoped so non-admins can only export for corporations they have a character in.

### 🩺 Diagnostics

An admin-only Diagnostic page at `/corp-wallet-manager/diagnostic` (gated by `corpwalletmanager.settings`, intentionally not in the sidebar). Five universal tabs (Health Checks, Master Test, System Validation, Settings Health, Data Integrity) plus four CWM-specific tabs: Wallet Trace walks a single journal row through the full classify -> alert -> publish pipeline, Donation Audit batch-displays every `player_donation` row in a corp and month with the classifier decision and the Mining Manager link side by side, Schedule Trace walks a corporation and cadence through the dispatcher to show exactly which webhooks would deliver and what period the next firing covers, and Notification Testing fires test webhook deliveries on demand. The System Validation tab carries a cross-plugin detection block, and Data Integrity surfaces schedule status, personal wallet aggregator status, and open anomaly latches.

---

## Compatibility

| Requirement | Version |
|-------------|---------|
| SeAT | 5.x |
| PHP | 8.1 or higher |
| MariaDB | 10.6 or higher (or equivalent MySQL) |
| Redis | required for queue + cache |
| Laravel | 10.x (inherited from SeAT) |

---

## Installation

```bash
composer require mattfalahe/corp-wallet-manager
```

The SeAT Docker stack auto-runs migrations on container boot. Outside Docker, run:

```bash
php artisan migrate
php artisan config:clear
php artisan view:clear
```

After the migrations land, run the one-shot initializer to populate every CWM cache table in the right order. This is the recommended first-run command:

```bash
php artisan corpwalletmanager:initialize
```

It sequences wallet backfill, division backfill, daily aggregation, predictions (corp + division), contribution backfill, personal wallet aggregates backfill, milestone state recompute, and wallet alert detection, printing a section header and progress bar for each step. It is idempotent and safe to re-run.

---

## First-run configuration

1. **Settings -> General**: pick the corporation you want CWM to focus on (the dropdown lists corps where you have director roles).
2. **Settings -> Discord Webhooks**: add at least one webhook. Paste the Discord URL, pick a scope (Global or a specific corp), pick a role mention (manual entry, or pick from the inline role list when SeAT Broadcast / SeAT Connector / warlof is installed), and tick the report types and alert types it should receive.
3. **Settings -> Scheduled Reports**: the panel auto-seeds weekly (Monday 03:30 UTC) and monthly (day 1 at 03:00 UTC) for every corp with wallet history on first upgrade. Add Quarterly or Annual rows from this panel, or change the cadence in place.
4. **Settings -> Alliance Tax** (optional, alliance corps only): set the per-bucket rates (Ratting / Mission / Tax Payment / Voluntary Donation / Industry, all default 0) and configure the recipient party IDs or description keywords that match your alliance remit payments. The Alliance Tax tab on the Director view surfaces the expected vs actual reconciliation.
5. **Settings -> Alert Thresholds**: set the large-transaction, low-balance, contribution-drop, and unusual-recipient thresholds (0 disables each one).
6. **Settings -> Member View**: toggle which of the personal-side cards your members see, pick the leaderboard size (5 / 10 / 20), and pick the privacy mode.

---

## Cross-plugin integration matrix

The matrix below shows what additional surface area lights up when each suite sibling is installed alongside CWM. Every row is optional; CWM runs standalone without any of them.

| Companion plugin | What CWM gains | What CWM publishes |
|------------------|----------------|--------------------|
| **Manager Core** | EventBus publishing (otherwise the topics are no-ops). The PluginBridge surface CWM exposes for siblings to consume. Entity name resolution via Manager Core's SDE for cleaner ID rendering. | Seven topics (`wallet.transaction_detected`, `wallet.balance_low`, `member.contribution.drop_detected`, `wallet.unusual_recipient_detected`, `member.contribution.stalled`, `member.contribution.milestone`, `member.tax.compliance_dropped`) plus the PluginBridge capabilities below. |
| **Mining Manager** | The authoritative tax-payment signal via `mining_taxes.transaction_id` linkage. Top Contributors' Tax Payment column shows `paid / owed` with compliance percentage; below 80% renders in warning yellow. | Nothing direct (CWM consumes MM's signal, not the reverse). |
| **HR Manager** | Nothing direct from HR to CWM (HR is a pure consumer). | HR subscribes to the three milestone topics and consumes the PluginBridge contribution analytics, tax compliance, director attribution, and corp-wide member summary capabilities to fold wallet signals into its Corp Health assessment. |
| **Structure Manager** | No direct consumption in v3.0. The visibility-honoring contract is in place for future SM-event consumption. | Nothing direct. |
| **SeAT Broadcast** | The Discord Role Picker reads Broadcast's `discord_roles` table when present so webhook role mentions can be picked from a list. | Nothing direct. |

---

## Permissions

Assign these permissions in SeAT's Access Management:

- `corpwalletmanager.view` baseline plugin access (required).
- `corpwalletmanager.director_view` the full Director view with all tabs, charts, and report generation.
- `corpwalletmanager.member_view` the simplified Member view.
- `corpwalletmanager.settings` Settings page, Data Export, and the Diagnostic page (admin / director only).

CEO and Director on a tracked corp get an implicit shortcut to the director surface; everyone else needs the explicit permission grant.

---

## Upgrade from v2

The v3 migrations run additively on top of your existing tables. The legacy single Discord webhook setting (`discord_webhook_url`) is folded into a first-class webhook row on upgrade, and cross-plugin integration is string-keyed (PluginBridge capability names, MC topics, view aliases) so the namespace rename in v3 is internal only and no companion plugin needs an update.

**Two operator actions:**

1. Flush queued v2-namespaced jobs from Redis. The PHP namespace dropped its `Seat\` prefix in v3, so any v2 job queued in Redis can no longer deserialise after the rename. For the standard SeAT Docker stack, bring the whole stack down and back up:

   ```bash
   docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down
   docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d
   ```

   The default Redis container has no persistent volume, so bringing the stack down clears the queue, cache, and sessions in one shot. A plain restart is NOT sufficient. For a non-Docker install, run `php artisan queue:flush && php artisan config:clear && php artisan cache:clear` instead.

2. Run the one-shot initializer to populate the new caches:

   ```bash
   php artisan corpwalletmanager:initialize
   ```

Alert thresholds default to 0 (disabled) so nothing alerts until you opt in via Settings -> Alert Thresholds.

---

## Known limitations

- **Data lag from EVE event to UI display is 1.5 to 2.5 hours typical.** The pipeline runs through four stages: CCP's ESI cache (1 hour), SeAT's wallet poll (up to 1 hour depending on schedule), CWM's hourly aggregation jobs, and a 5-minute Redis read cache on the heaviest member-facing endpoints. A donation made 30 seconds ago will not appear in Top Contributors yet; this is normal. See Help -> Data Freshness.
- **AIR Daily Goal Reward** entries (75K-ish ISK from completing daily activities) are not yet classified. The ref_type used by CCP is not in SeAT's wallet language file; once verified via Diagnostic -> Wallet Trace it can be added as a one-line classifier case.
- **Director attribution** for corp withdrawals is best-effort. CCP exposes no authoritative actor identity for `corp_account_withdrawal` rows; CWM uses `context_id` when populated plus a logon-proximity heuristic, with an unattributable bucket holding sample rows.
- **External players using corp infrastructure** (industry tax payers not currently affiliated with the tracked corp) appear in income and breakdown views as corp revenue but stay off the per-member Top Contributors leaderboard by design.

---

## Support

- **Issues**: [GitHub Issues](https://github.com/MattFalahe/Corp-Wallet-Manager/issues)
- **Email**: mattfalahe@gmail.com
- **SeAT Discord**: https://discord.gg/azquy29nqs

---

## Credits

- **Author**: Matt Falahe
- **Contributors**: [contributors page](https://github.com/MattFalahe/Corp-Wallet-Manager/graphs/contributors)
- **Thanks to**: the SeAT development team for the plugin architecture this builds on, the wider EVE Online tooling community, and the directors and finance officers who paged me back when a number on a leaderboard did not match what they expected.

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for full terms.

---

Made for the corporations of New Eden.
