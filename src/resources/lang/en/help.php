<?php

return [
    // v3.0.0 - The Integration Era
    'v3_badge' => 'NEW in v3.0.0',
    'whats_new_v3_title' => 'What\'s New in v3.0.0 - The Integration Era',
    'whats_new_v3_intro' => 'Corp Wallet Manager v3.0.0 brings CWM fully into the SeAT plugin suite. The Discord delivery layer is rebuilt around per-corporation webhooks with role mentions, per-report-type subscriptions, and delivery health tracking. A new hourly alert detector raises four classes of wallet alerts (large transactions, low balance, member contribution drops, unusual recipients) that flow to subscribed webhooks (standalone) and to Manager Core\'s cross-plugin EventBus (when MC is installed). A new per-character contribution cache classifies every journal row into a per-member bucket, surfaces as a Top Contributors leaderboard, a Profit Attribution by Activity dashboard, an Alliance Tax reconciliation tab, and 14 PluginBridge capabilities consumed by HR Manager for member assessment. <strong>CWM still works fully standalone</strong>; every cross-plugin integration is <code>class_exists</code>-guarded and additive.',
    'whats_new_v3_list' => '<p><strong>Headline features:</strong></p>
        <ul>
            <li><strong>Per-Corporation Discord Webhooks</strong>: Replaces the single global webhook URL with a managed table of webhooks scoped per corporation, each with its own role mention, choice of which report types it receives (weekly / monthly / on-demand), choice of which alerts it receives (large transfer / low balance / contribution drop / unusual recipient), and per-webhook delivery health tracking. Pre-3.0 settings are automatically folded into a first-class webhook row on upgrade.</li>
            <li><strong>Wallet Alerts (four kinds)</strong>: <em>Large Transaction</em> when a single journal row meets a configurable ISK threshold. <em>Low Balance</em> when a corporation total balance crosses below a threshold (latched per corp so it fires once per crossing, not every hour). <em>Contribution Drop</em> (anomaly) when a member\'s 3-month contribution average collapses to under 20% of the previous 3-month window, latched in <code>corpwalletmanager_anomaly_state</code> so re-stalls after recovery re-fire. <em>Unusual Recipient</em> (anomaly) when a corp withdrawal goes to a party with no prior interaction in the last 7 days, above a configurable threshold. All four configurable in Settings &rarr; Alert Thresholds; <code>0</code> disables.</li>
            <li><strong>Event Bus Publishing</strong>: When Manager Core is installed, CWM publishes <code>wallet.transaction_detected</code>, <code>wallet.balance_low</code>, <code>member.contribution.drop_detected</code>, <code>wallet.unusual_recipient_detected</code>, plus three milestone events (<code>member.contribution.stalled</code>, <code>member.contribution.milestone</code>, <code>member.tax.compliance_dropped</code>) so other plugins can react to corp wallet activity.</li>
            <li><strong>Per-Character Contribution Tracking</strong>: An hourly job classifies corp wallet journal entries into per-character buckets - Ratting (bounty + ESS escrow + corp-tax variants), Mission (rewards + time bonus + corp-tax variants), Industry (industry_job_tax for members on corp structures), Tax Payment (Mining Manager linkage via <code>mining_taxes.transaction_id</code> first, description tax-code fallback), Voluntary Donation, and Withdrawal. Bounty / mission attribution uses <code>context_id</code> (the real character) so NPC faction IDs no longer appear as ratters. Run <code>php artisan corpwalletmanager:backfill-contributions --months=N</code> once after upgrading to populate history.</li>
            <li><strong>Inter-Division Transfer Filtering</strong>: ISK moved between divisions of the same corp (where both parties equal the corp id) is filtered out plugin-wide via a shared <code>JournalFilters</code> helper. Affects every chart, backfill, prediction model, scheduled report, alert scan, and member-facing aggregate so internal rebalancing no longer inflates income, expense, breakdown, or per-division numbers.</li>
            <li><strong>Top Contributors tab (Director view)</strong>: Per-member leaderboard with main-character grouping (alts collapse under the main, click the caret to expand). Columns: Ratting, Mission, Industry, Tax Payment (paid/owed when MM installed), Voluntary Donation, Total, plus Alliance Tax + Net to Corp when any alliance rate is above zero. Without MM the Tax / Donation columns merge.</li>
            <li><strong>Profit Attribution by Activity tab (Director view)</strong>: Answers "where did corp profit come from?" - pie chart of per-activity share, per-activity efficiency table (total / members / avg-per-member / % of profit / trend vs prior period). Top Contributors asks <em>who</em> paid; Profit Attribution asks <em>what activity</em> drove it.</li>
            <li><strong>Alliance Tax tab (Director view)</strong>: Compares expected alliance tax (per-bucket income × per-bucket rate) against actual outgoing remits (matched via recipient party IDs and / or description keywords). Grouped bar chart over the trailing 3 / 6 / 12 months with a per-month detail table.</li>
            <li><strong>Annual + Quarterly retrospective reports</strong>: Two new report types with multi-section PDFs (cover page + Executive Summary + Monthly Trend + Top 10 Contributors + Activity Mix + Notable Transactions + Division Performance + Alliance Tax Remits + Milestones Reached + Risk Assessment + YoY/QoQ comparison) and a matching CSV with seven retrospective sections.</li>
            <li><strong>PDF + CSV export for every report</strong>: Every stored report can be downloaded as a formatted PDF (<code>barryvdh/laravel-dompdf</code>) or a multi-section CSV from the Report History table and from the report view modal.</li>
            <li><strong>Settings sidebar redesign</strong>: Reorganised into Configuration (General / Member View / Alert Thresholds / Alliance Tax), Integrations (Discord Webhooks / Notification Routing), and Operations (Maintenance / Job Status / Access Logs). One section visible at a time; URL hash persists active section through reloads. Matches the Mining Manager and Structure Manager pattern.</li>
            <li><strong>Notification Routing Map (Settings tab)</strong>: Read-only snapshot of every notification CWM can deliver, which webhook(s) currently route each category, the resolved role-mention pill, and the scope (Global vs Corp N). Surfaces a delivery-gap warning when alliance tax is configured but no webhook subscribes to any report category.</li>
            <li><strong>Discord role picker</strong>: When a Discord role provider (SeAT Broadcast / SeAT Connector / legacy warlof) is installed, webhook role mentions can be picked from an inline list with color dots, role names, snowflake IDs, and a live preview pill. Mirrors the Structure Manager pattern.</li>
            <li><strong>Diagnostic page (admin-only)</strong>: A new admin surface at <code>/corp-wallet-manager/diagnostic</code> (gated by <code>corpwalletmanager.settings</code>, intentionally NOT in the sidebar). Eight tabs: Health Checks, Master Test, System Validation, Settings Health, Data Integrity, Wallet Trace (walks one journal row through classify &rarr; alert &rarr; publish), Donation Audit (batch view of <code>player_donation</code> rows with classifier decision + MM-link), Notification Testing (fire test webhook deliveries on demand). SM-style summary banner (OK / Warnings / Errors counts) with Reload + Force refresh buttons.</li>
            <li><strong>HR Manager Integration (14 PluginBridge capabilities)</strong>: Existing 3 ratting capabilities + 4 contribution analytics (Summary / ByCategory / Entries / CorpOutflows) + 5 new analytics (Trend / ActivityGaps / NetPosition / LifetimeSummary / CharacterPercentile) + MM tax compliance + director attribution. State for milestone events tracked in <code>corpwalletmanager_member_milestone_state</code> so each transition publishes exactly once.</li>
            <li><strong>Namespace migration: dropped <code>Seat\\</code> prefix</strong>: CWM\'s PHP namespace is now <code>CorpWalletManager\\...</code> (matching every other suite plugin). Cross-plugin integration is string-keyed (PluginBridge capability names, MC topics, view aliases) so the rename is internal only. <strong>Operator action on upgrade</strong>: queued background jobs in Redis serialise their class FQN, so any in-flight job from v2.x will fail to deserialise after the rename. Easiest mitigation for Docker users is <code>docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down && docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d</code> (default SeAT compose stacks have no persistent Redis volume so this clears the queue + cache + sessions in one shot). a plain restart is NOT sufficient.</li>
        </ul>
        <p style="margin-top:12px;"><strong>Companion plugins (all optional):</strong></p>
        <ul>
            <li><strong>Manager Core</strong>: When installed, CWM publishes the 7 wallet / member topics above to MC\'s cross-plugin EventBus and the 14 PluginBridge capabilities are exposed.</li>
            <li><strong>Mining Manager</strong>: When installed, the per-character contribution tracker splits tax payments from voluntary donations using <code>mining_taxes.transaction_id</code> first and the description tax-code marker as fallback. Top Contributors\' Tax Payment column shows <code>paid / owed</code> with compliance percentage.</li>
            <li><strong>HR Manager</strong>: Consumes the 14 capabilities to assemble per-member assessments and subscribes to the 3 milestone events for the member profile timeline. CWM and MM are both optional from HR\'s perspective.</li>
        </ul>',
    'whats_new_v3_upgrade_note' => 'Upgrading from v2.x is seamless schema-wise: the v3 migrations run additively on top of your existing tables, and the legacy single Discord webhook setting (<code>discord_webhook_url</code>) is folded into a first-class webhook row on upgrade. Alert thresholds default to <code>0</code> (disabled) so nothing alerts until you opt in via Settings &rarr; Alert Thresholds. Two operator actions: (1) run <code>php artisan corpwalletmanager:backfill-contributions --months=6</code> once after upgrading to populate per-character contribution history (or use the Settings &rarr; Maintenance &rarr; Backfill Contributions button); (2) restart your stack via <code>docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down && docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d</code> to flush queued v2-namespaced jobs from Redis. Both are one-shot tasks.',

    // Navigation
    'help_documentation' => 'Help & Documentation',
    'search_placeholder' => 'Search documentation...',
    'overview' => 'Overview',
    'getting_started' => 'Getting Started',
    'features' => 'Features Deep Dive',
    'director_tabs' => 'Director Tabs',
    'predictions' => 'Predictions',
    'reports' => 'Reports',
    'analytics' => 'Analytics',
    'commands' => 'Commands',
    'settings' => 'Settings',
    'member_view' => 'Member View',
    'contributions' => 'Contributions & Tax',
    'webhooks' => 'Discord Webhooks',
    'diagnostic' => 'Diagnostic',
    'integrations' => 'Integrations',
    'faq' => 'FAQ',
    'troubleshooting' => 'Troubleshooting',

    // v3.0.0 sidebar entries.
    // Overview consolidates Plugin Info (with four GitHub chips), Version
    // Status, Welcome, What's New (.whats-new-box green callout), What Is
    // CWM, and Key Features into a single long scroll page. Mirrors the
    // MM / MC pattern verbatim.
    // The five legacy nav_* keys below remain in the file unused but are
    // kept for backwards compat in case operators have a bookmarked URL
    // hash that names one of those sections.
    'nav_overview'       => 'Overview',
    'nav_plugin_info'    => 'Plugin Information',
    'nav_version_status' => 'Version Status',
    'nav_welcome'        => 'Welcome',
    'nav_what_is'        => 'What is Corp Wallet Manager?',
    'nav_key_features'   => 'Key Features',

    // ============================================================
    // Version Status (sidebar entry 2)
    // ============================================================
    'version_status_title'           => 'Version Status',
    'version_installed'              => 'Installed',
    'version_codename'               => 'Codename',
    'version_view_release_notes'     => 'View release notes',
    'version_upgrade_recipe_title'   => 'Upgrade recipe (SeAT Docker stack)',
    'version_upgrade_recipe_intro'   => 'From the directory holding your SeAT compose file, run:',
    'version_upgrade_recipe_note'    => 'Bringing the stack fully down (not just restart) clears the default Redis container, which flushes queued background jobs that may carry pre-v3 class FQNs. Container boot pulls the latest plugin via Composer, runs any new migrations, and re-seeds schedules automatically.',
    'version_source_composer'        => "Resolved via Composer's installed.json (preferred).",
    'version_source_constant'        => "Resolved via the CWM_VERSION constant (fallback for non-Composer installs).",
    'version_source_hint'            => "Installed version comes from Composer's runtime metadata when available; the v3.0.0 release tag is hardcoded. No external HTTP calls.",

    // ============================================================
    // Welcome (sidebar entry 3)
    // ============================================================
    'welcome_title' => 'Welcome to Corp Wallet Manager',
    'welcome_body'  => '<p>Corp Wallet Manager (CWM) is a SeAT plugin for corporation directors, finance officers, and alliance treasurers who want to see where their corp\'s ISK comes from and where it goes, in numbers that match what their members are doing in-game.</p>
        <p>What it produces is a continuous, per-character, per-activity picture of the corp wallet: a leaderboard of who paid what tax, an attribution of which activities drove profit, an expense breakdown of where the corp spent its ISK, an alliance-tax reconciliation that surfaces the gap between what the corp owes and what it remitted, and a stream of automated reports that land in Discord on the cadences directors actually want (daily morning pulse, weekly Monday summary, monthly close, quarterly board pack, annual retrospective).</p>
        <p>CWM was built so it works fully standalone, but it gets noticeably more interesting when other plugins in the same suite are installed alongside it. <strong>Manager Core</strong> gives CWM an event bus other plugins subscribe to (seven topics published, plus a fan-out of PluginBridge capabilities exposed). <strong>Mining Manager</strong> contributes the authoritative tax-payment signal that splits mining-tax payers from voluntary donors. <strong>HR Manager</strong> consumes CWM\'s contribution analytics for member assessment. <strong>Structure Manager</strong>, <strong>SeAT Broadcast</strong>, and <strong>Buyback Manager</strong> round out the suite for fuel ops, ping coordination, and corp-buyback workflows respectively.</p>
        <p>Every cross-plugin integration is opt-in by detection: if a companion plugin is installed, the relevant capability lights up; if not, that surface area degrades cleanly. There is no composer dependency between CWM and any companion plugin, so they can be installed and uninstalled independently. The same is true the other way: HR Manager will use CWM\'s wallet signals when CWM is installed, but it does not require CWM. Pick the slice of the suite that fits your corp.</p>',

    // ============================================================
    // What's New in v3.0.0 (sidebar entry 4)
    // ============================================================
    'whats_new_title' => "What's New in 3.0.0 - The Ecosystem Era",
    'whats_new_intro' => '<p>v3.0.0 is the release where CWM stopped being a standalone wallet tool and became a member of the manager-suite ecosystem. The Discord delivery layer was rebuilt around per-corporation webhooks, the contribution cache was unlocked as a Top Contributors / Profit Attribution / Expense Attribution / Alliance Tax view stack, scheduling moved from hardcoded cron entries to an operator UI, and a fan-out of cross-plugin event publishing + PluginBridge capabilities + tax-signal linkage now connects CWM to Manager Core, HR Manager, and Mining Manager. Highlights below.</p>',

    'whats_new_section_ecosystem' => '<i class="fas fa-globe text-info"></i> &nbsp;Ecosystem Integration',
    'whats_new_section_ecosystem_body' => 'When Manager Core is installed, CWM publishes <strong>seven topics</strong> to MC\'s cross-plugin EventBus (<code>wallet.transaction_detected</code>, <code>wallet.balance_low</code>, <code>member.contribution.drop_detected</code>, <code>wallet.unusual_recipient_detected</code>, <code>member.contribution.stalled</code>, <code>member.contribution.milestone</code>, <code>member.tax.compliance_dropped</code>). A fan-out of <strong>PluginBridge capabilities</strong> is exposed for other plugins to consume - HR Manager wires up the contribution + tax-compliance signals (and the corp-wide member summary) to its member assessment classifier. When Mining Manager is installed, the per-character contribution tracker uses MM\'s authoritative <code>mining_taxes.transaction_id</code> linkage to split tax payments from voluntary donations cleanly. When Structure Manager is installed, the visibility-honoring contract is in place so CWM event subscribers (none yet, this side) would respect SM\'s structure-level visibility flags.',

    'whats_new_section_analytics' => '<i class="fas fa-chart-bar text-success"></i> &nbsp;New Analytics Tabs',
    'whats_new_section_analytics_body' => 'Four new tabs land on the Director view. <strong>Top Contributors</strong> shows per-member contribution rankings with main-character grouping (alts collapse under the main), the alliance-tax math when rates are configured, Mining Manager compliance when MM is installed, and a dedicated Industry bucket for members paying industry job tax, with a Contribution Concentration doughnut and a Members vs External Contributors stacked bar sitting above the table so a director reads the shape of the period before drilling in. <strong>Profit Attribution by Activity</strong> asks "what activity drove the corp\'s profit?" with a pie + per-activity efficiency table + multi-line trailing-months trend. <strong>Expense Attribution by Category</strong> is the counterpart for outgoings, with a hardcoded nine-category taxonomy (Alliance Tax / Corp Withdrawal / Market Fees / Office Rental / Industry Costs / Contracts / Structure & Sovereignty / Insurance & War / Other) that survives across CCP ref_type drift. <strong>Alliance Tax Reconciliation</strong> compares expected (per-bucket rate math) against actual (outgoing matching configured recipient IDs or description keywords) over the trailing 3 / 6 / 12 months.',

    'whats_new_section_scheduled' => '<i class="fas fa-calendar-alt text-warning"></i> &nbsp;Scheduled Reports UI',
    'whats_new_section_scheduled_body' => 'Replaces the two hardcoded weekly + monthly ScheduleSeeder entries that fired blanket for every corp. The new <strong>Settings -> Scheduled Reports</strong> panel holds one row per (corporation_id, report_type), with explicit hour + minute + day-axis columns. The dispatcher cron (<code>corpwalletmanager:dispatch-scheduled-reports</code>) runs every 5 minutes to scan for due rows and fires GenerateReport with the right window for the cadence (daily = yesterday, weekly = prior Mon-Sun, monthly = prior calendar month, quarterly = prior calendar quarter, annual = prior calendar year). The migration seeds default weekly + monthly rows for every corp with wallet history on first upgrade so nobody loses their existing cadence.',

    'whats_new_section_reports' => '<i class="fas fa-file-alt text-info"></i> &nbsp;Report Types Differentiated',
    'whats_new_section_reports_body' => 'Executive / Financial / Division previously rendered the same balance-income-expense-risk tuple with only the title swapped. Each shape now composes a genuinely distinct payload. The on-demand dropdown also gained Daily / Weekly / Monthly entries at the top so directors can fire those cadences without waiting for the scheduler, and Annual + Quarterly are now first-class report types with multi-section PDF templates. The table below shows what each cadence covers at a glance.',

    'whats_new_report_table_title' => 'Report Type Comparison',
    'whats_new_report_table' => '<table style="width: 100%; margin: 1rem 0; border-collapse: collapse;">
        <thead>
            <tr style="background: rgba(102, 126, 234, 0.15);">
                <th style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3); text-align: left;">Report Type</th>
                <th style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3); text-align: left;">Shape</th>
                <th style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3); text-align: left;">Best For</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);"><strong>Daily</strong></td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Terse: balance + flow + risk indicator</td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Morning pulse-check (5 second read)</td>
            </tr>
            <tr>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);"><strong>Weekly</strong></td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Retro sections: top contributors + activity mix + alliance tax + anomalies</td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Monday leadership sync</td>
            </tr>
            <tr>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);"><strong>Monthly</strong></td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Full retro: contributors + mix + alliance tax + notable transactions + anomalies + MM compliance + milestones</td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Month-end close, director review</td>
            </tr>
            <tr>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);"><strong>Quarterly</strong></td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Multi-section PDF with cover page + executive summary + QoQ comparison</td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Board pack, quarterly all-hands</td>
            </tr>
            <tr>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);"><strong>Annual</strong></td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Multi-section PDF with cover page + executive summary + YoY comparison + milestones reached</td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Yearly retrospective, anniversary review</td>
            </tr>
            <tr>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);"><strong>Executive</strong></td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">KPI snapshot + headline + risk + top 3 contributors</td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">CEO / exec one-pager</td>
            </tr>
            <tr>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);"><strong>Financial</strong></td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Deep dive: per-ref_type income + expense + activity attribution + alliance tax + MM compliance</td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Finance officer audit</td>
            </tr>
            <tr>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);"><strong>Division</strong></td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Per-wallet-division balances + top ref_types + inter-division summary</td>
                <td style="padding: 0.5rem; border: 1px solid rgba(102, 126, 234, 0.3);">Per-department accountability</td>
            </tr>
        </tbody>
    </table>',

    'whats_new_section_annual' => '<i class="fas fa-file-alt text-info"></i> &nbsp;Annual + Quarterly Summary Reports',
    'whats_new_section_annual_body' => 'Two new report types backed by a multi-section PDF template (built on the existing <code>barryvdh/laravel-dompdf</code> infrastructure). Each PDF carries a cover page (corp ticker [ID] + period dates + generated timestamp), an Executive Summary, a Monthly Balance Trend with inline colored bars, Top 10 Contributors (main-character grouped), Activity Mix (per-bucket totals + members + colored swatches), Notable Transactions (top 10 incoming + top 10 outgoing with party names via EntityNameResolver), Division Performance, Alliance Tax Remits (when recipient IDs / keywords are configured), Milestones Reached (cross-referencing <code>corpwalletmanager_member_milestone_state</code> from the HR integration), a Risk Assessment, and a Year-over-Year (or Quarter-over-Quarter) comparison from prior reports. CSV export carries seven matching retrospective sections.',

    'whats_new_section_anomaly' => '<i class="fas fa-bell text-warning"></i> &nbsp;Anomaly Detection',
    'whats_new_section_anomaly_body' => 'Two new alert kinds added to the hourly DetectWalletAlerts job. <strong>Contribution Drop</strong> fires when a member\'s prior 3-month contribution average collapses to less than 20% of the previous 3-month window, latched per-(corp, character) in the new <code>corpwalletmanager_anomaly_state</code> table and cleared on recovery above 50% so re-stalls after recovery re-fire. <strong>Unusual Recipient</strong> fires when a corp withdrawal goes to a party with no prior interaction in the 7-day cold window, above a configurable threshold. Both deliver to subscribed Discord webhooks AND publish to MC\'s EventBus under <code>member.contribution.drop_detected</code> and <code>wallet.unusual_recipient_detected</code>.',

    'whats_new_section_hr' => '<i class="fas fa-users text-info"></i> &nbsp;HR Manager Integration',
    'whats_new_section_hr_body' => '7 new PluginBridge capabilities for HR consumption (<code>contribution.getCharacterTrend</code>, <code>contribution.getActivityGaps</code>, <code>contribution.getNetPosition</code>, <code>contribution.getLifetimeSummary</code>, <code>contribution.getCharacterPercentile</code>, <code>contribution.getCharacterTaxCompliance</code>, <code>wallet.getDirectorAttribution</code>) plus 3 milestone events (<code>member.contribution.stalled</code>, <code>member.contribution.milestone</code>, <code>member.tax.compliance_dropped</code>). HR composes these into per-member assessment signals on its member profile page. State for milestone events is tracked in <code>corpwalletmanager_member_milestone_state</code> so each transition publishes exactly once across the event lifetime.',

    'whats_new_section_fixes' => '<i class="fas fa-bug text-danger"></i> &nbsp;Fixes from v2 that operators will feel',
    'whats_new_section_fixes_body' => 'Three things v2 was doing wrong are now correct. <strong>Inter-division transfer double-counting</strong> inflated income, expense, breakdown, and per-division numbers across every CWM surface; a shared <code>JournalFilters::excludeInternalTransfers</code> helper now filters these rows out plugin-wide so charts, backfills, prediction models, scheduled reports, alert scans, and member-facing aggregates reflect real corp activity instead of internal rebalancing. <strong>Scheduled Discord report delivery never fired</strong> because the <code>corpwalletmanager:generate-report</code> command ran corp-less in v2 and never reached any webhook; the dispatcher now fans out one report per corp with wallet data. <strong>Settings save no longer writes the Discord webhook URL to the application log</strong> on every save (v2 dumped the full request payload including the secret URL to <code>storage/logs</code>).',

    'upgrade_notes_title' => 'Upgrade Notes (v2 -> v3)',
    'upgrade_notes_body'  => 'Upgrading from v2.x is seamless schema-wise: the v3 migrations run additively on top of your existing tables, and the legacy single Discord webhook setting (<code>discord_webhook_url</code>) is folded into a first-class webhook row on upgrade. Alert thresholds default to <code>0</code> (disabled) so nothing alerts until you opt in via Settings -> Alert Thresholds. <strong>Two operator actions are required.</strong> First, bring the SeAT Docker stack down and back up (<code>docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down &amp;&amp; docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d</code>) to flush queued v2-namespaced jobs from Redis. The PHP namespace dropped its <code>Seat\\</code> prefix in this release, so any v2 job queued in Redis cannot deserialise after the rename; the default Redis container has no persistent volume so bringing the stack down clears the queue + cache + sessions in one shot. a plain restart is NOT sufficient. Second, run <code>docker exec -it seat-docker-front-1 php artisan corpwalletmanager:initialize</code> once the stack is back up. The command sequences every CWM cache-populating step in the right order with per-step progress bars (wallet backfill, division backfill, daily aggregation, predictions, contribution backfill, personal wallet aggregates backfill, milestone state recompute, wallet alert detection) and is idempotent so it is safe to re-run. Cross-plugin integration is string-keyed (PluginBridge capability names, MC topics, view aliases) so the namespace rename is internal only and no companion plugin needs an update.',

    // ============================================================
    // What is Corp Wallet Manager? (sidebar entry 5)
    // ============================================================
    'what_is_title' => 'What is Corp Wallet Manager?',
    'what_is_desc'  => 'Corp Wallet Manager is the data engine that sits between a corp\'s raw wallet journal and the questions directors and finance officers actually want answered. The deeper sections below cover the architecture in detail.',

    'what_is_data_model_title' => 'Data Model',
    'what_is_data_model_body'  => 'The journal pipeline runs in four stages. <strong>Stage 1: raw rows</strong> come from SeAT\'s own <code>corporation_wallet_journals</code> sync. <strong>Stage 2: the classifier</strong> reads each row and assigns it to a per-character bucket (Ratting / Mission / Industry / Tax Payment / Voluntary Donation / Withdrawal) using context_id-first attribution for bounty + mission rows, Mining Manager\'s <code>mining_taxes.transaction_id</code> linkage for tax payments, and the <code>JournalFilters::excludeInternalTransfers</code> guard to drop rows that are just corp-internal rebalancing. <strong>Stage 3: the contribution cache</strong> (<code>corpwalletmanager_character_contributions</code>) holds one row per (corp, character, year, month) with bucket totals + counts, atomically incremented as the hourly job sweeps new journal rows. <strong>Stage 4: reports + analytics</strong> read from the cache (cheap, indexed) rather than re-scanning the journal each time, which is what makes Top Contributors and the four director tabs render in milliseconds.',

    'what_is_data_freshness_title' => 'Data Freshness',
    'what_is_data_freshness_body'  => 'End-to-end lag from an in-game EVE event to it showing up on a CWM surface is <strong>1.5 to 2.5 hours typical</strong>. The pipeline runs through four caching stages, each adding a known amount of lag. <strong>Stage A: CCP\'s ESI cache.</strong> ESI itself caches wallet journal data for up to 1 hour before returning a fresh response, so even an instant SeAT poll cannot see a transaction younger than the cache age. <strong>Stage B: SeAT\'s wallet poll.</strong> SeAT polls corp wallet journals on its own schedule (default once per hour, configurable). On average a row arrives in SeAT\'s database half a poll interval after CCP releases it. <strong>Stage C: CWM\'s hourly aggregation jobs.</strong> <code>UpdateHourlyWalletData</code> + <code>ComputeCharacterContributions</code> + <code>ComputePersonalWalletAggregates</code> sweep new rows and update the precomputed cache. <strong>Stage D: 5-minute Redis read cache.</strong> The heaviest member-facing endpoints (Top Contributors, personal contribution, personal wallet stats) cache responses for 5 minutes so a hot tab does not hammer the DB. <strong>Implication for operators:</strong> a donation made 30 seconds ago will not appear in Top Contributors yet, and that is normal. Set member expectations accordingly. The Refresh button in the member view\'s tab nav passes <code>?refresh=1</code> to bypass the 5-minute read cache (Stage D), but Stages A-C still apply.',

    'what_is_cross_plugin_title' => 'Cross-Plugin Integration',
    'what_is_cross_plugin_body'  => 'Detection is via <code>class_exists()</code> at runtime, never composer dependency. When Manager Core is detected, the EventBus publisher lights up and the 14 PluginBridge capabilities register. When Mining Manager is detected, the contribution classifier uses MM\'s tax-code linkage and the Top Contributors leaderboard gains a paid/owed column. When HR Manager is installed, it consumes the capabilities and subscribes to the milestone topics on its side (CWM doesn\'t care whether HR is there). Plugins can be installed and removed in any order; CWM degrades gracefully when companions are absent and lights back up when they\'re installed.',

    'what_is_permissions_title' => 'Permissions',
    'what_is_permissions_body'  => '<ul>
        <li><code>corpwalletmanager.view</code> &mdash; baseline plugin access (required by every CWM page).</li>
        <li><code>corpwalletmanager.director_view</code> &mdash; the full Director view with all nine tabs, charts, and report generation.</li>
        <li><code>corpwalletmanager.member_view</code> &mdash; the simplified Member view (no sensitive financial detail; configurable per-section toggles via Settings).</li>
        <li><code>corpwalletmanager.settings</code> &mdash; Settings page, Data Export, and the Diagnostic page (admin / director only).</li>
    </ul>
    <p>Permissions are managed through SeAT\'s standard role system. CEO + Director on a tracked corp get an implicit shortcut to the director surface; everyone else needs the explicit permission grant.</p>',

    'what_is_member_surface_title' => 'Member Page Personal Surface',
    'what_is_member_surface_body'  => 'Members log in and see a personal angle alongside the corp-wide health and goals: My Contribution (their own monthly contribution rolled up across every alt they have in the corp, with rank, percentile, lifetime, months active, and a per-bucket sparkline), a Top Contributors leaderboard with the privacy mode the operator picked, an optional My Tax Compliance card when Mining Manager is installed, and a My Milestones card walking the lifetime ladder. <strong>Three leaderboard privacy modes</strong> let operators decide how transparent the corp is: <em>ISK Visible</em> shows actual contribution amounts (best for transparent corps), <em>Percentage</em> shows each contributor\'s share as a percentage of corp total (less revealing than raw ISK), and <em>Rank Only</em> shows ranks and names but no amounts at all (most private, useful for big corps). The mode is enforced server-side, so a member opening devtools cannot reveal hidden values. The viewer\'s own row is always highlighted in the leaderboard, and if they sit outside the top N a separator and a pinned row at the bottom always show their position. Five operator toggles in Settings -> Member View control which of the four new surfaces each corp\'s members actually see, plus the leaderboard size (Top 5 / 10 / 20) and the privacy mode itself.',

    'what_is_diagnostic_title' => 'Diagnostic Page',
    'what_is_diagnostic_body'  => 'A new admin-only surface at <code>/corp-wallet-manager/diagnostic</code> (gated by <code>corpwalletmanager.settings</code>, intentionally NOT in the sidebar per the suite\'s diagnostic-standard convention). Eight tabs cover the universal Tier-1 set (Health Checks / Master Test / System Validation / Settings Health / Data Integrity) plus three CWM-specific tabs: Wallet Trace (walks one journal row through the full classify -> alert -> publish pipeline), Donation Audit (batch view of every <code>player_donation</code> row in a corp + month with the classifier\'s bucket decision and the MM-link shown side-by-side), and Notification Testing (fire test webhook deliveries on demand). The default landing tab is always Health Checks.',

    'what_is_key_benefit_title' => 'Key Benefit',
    'what_is_key_benefit_body'  => 'Directors stop pulling weekly summaries by hand. The data model + scheduling + Discord delivery + report differentiation combine so that the regular review cadences (morning pulse, Monday summary, month-end close, quarterly board pack, annual retrospective) land in the right channels at the right times with the right shape, automatically.',

    // ============================================================
    // Key Features (sidebar entry 6)
    // ============================================================
    'key_features_title' => 'Key Features',

    'kf_top_contributors_title' => 'Top Contributors Leaderboard',
    'kf_top_contributors_body'  => 'Per-member ranking grouped by main character. Columns: Ratting / Mission / Industry / Tax Payment (paid/owed when MM installed) / Voluntary Donation / Total, plus Alliance Tax + Net to Corp when alliance rates are configured.',

    'kf_profit_attribution_title' => 'Profit Attribution by Activity',
    'kf_profit_attribution_body'  => 'Pie chart + per-activity efficiency table + multi-line trend. Answers "what activity drove the corp\'s profit this period?" with NPC and corp-self defensive guards on aggregation.',

    'kf_expense_attribution_title' => 'Expense Attribution by Category',
    'kf_expense_attribution_body'  => 'Nine-category taxonomy (Alliance Tax / Corp Withdrawal / Market Fees / Office Rental / Industry Costs / Contracts / Structure & Sovereignty / Insurance & War / Other). Pie + trend mirrors the Profit Attribution shape.',

    'kf_alliance_tax_title' => 'Alliance Tax Reconciliation',
    'kf_alliance_tax_body'  => 'Compares expected (per-bucket rate math) vs actual (outgoing payments matching configured recipient IDs or keywords) over the trailing 3 / 6 / 12 months. Per-month detail table flags overpaid / underpaid gaps.',

    'kf_webhooks_title' => 'Per-Corp Discord Webhooks',
    'kf_webhooks_body'  => 'Any number of webhooks per corp, each with its own role mention, choice of report types, and choice of alert types. Per-webhook delivery health tracking. Notification Routing Map surfaces silent categories.',

    'kf_alerts_title' => 'Four Alert Kinds',
    'kf_alerts_body'  => 'Large Transaction / Low Balance / Contribution Drop / Unusual Recipient. All four configurable in Settings, threshold 0 disables. Delivered to subscribed webhooks AND published to MC EventBus when Manager Core is installed.',

    'kf_scheduled_reports_title' => 'Scheduled Reports UI',
    'kf_scheduled_reports_body'  => 'Per-corp, per-cadence schedule rows with day-axis + hour + minute. Daily / Weekly / Monthly / Quarterly / Annual. Dispatcher cron ticks every 5 minutes for tight time-window adherence.',

    'kf_data_export_title' => 'Data Export',
    'kf_data_export_body'  => 'Settings -> Data Export. Pick sections (journal / contributions / reports / alerts / anomaly state) + date range + format (ZIP of CSVs or single multi-section CSV). Queued generation, signed download URLs valid for 24h. Recent exports table.',

    'kf_diagnostic_title' => 'Diagnostic Surface',
    'kf_diagnostic_body'  => 'Eight-tab admin diagnostic page. Wallet Trace walks a journal row through the pipeline, Donation Audit batch-displays player_donation classification decisions, Notification Testing fires test webhook deliveries on demand.',

    'kf_member_surface_title' => 'Member Page Personal Surface',
    'kf_member_surface_body'  => 'The member view opens with a My Contribution card (headline ISK, rank, percentile, lifetime, per-bucket sparkline), a Top Contributors leaderboard with the privacy mode the operator picked, a My Tax Compliance card when Mining Manager is installed, and a My Milestones card walking the lifetime ladder. Three leaderboard privacy modes: ISK Visible (raw amounts), Percentage (share of corp total, less revealing than raw ISK), Rank Only (names and ranks but no amounts). The mode is enforced server-side, so a curious member cannot reveal hidden values via devtools.',

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
    'feature_predictions_desc' => 'Uses a simple linear trend for new corporations and, once 60+ days of history are available, an advanced weighted-average model with learned seasonal factors for forecasting up to 90 days ahead.',
    'feature_analytics_title' => 'Advanced Analytics',
    'feature_analytics_desc' => 'Comprehensive financial analysis including health scores, burn rates, cash flow patterns, activity heatmaps, and performance metrics across multiple timeframes.',
    'feature_reports_title' => 'Automated Reports',
    'feature_reports_desc' => 'Generate and schedule financial reports with Discord integration. Supports custom, weekly, and monthly reports with automated delivery and notification triggers.',
    'feature_divisions_title' => 'Division Tracking',
    'feature_divisions_desc' => 'Monitor individual wallet divisions separately. Track division-specific balances, predictions, and performance metrics for better resource allocation.',
    'feature_permissions_title' => 'Role-Based Access',
    'feature_permissions_desc' => 'Granular permission system with separate views for directors and members. Control access to sensitive financial data and analytics features.',

    // Quick Links chip labels (github_repo / changelog / report_issues /
    // readme are defined under the Plugin Information section above and
    // power the four canonical chips embedded inside Plugin Information).
    // The standalone Quick Links panel was removed in favour of the MM/MC
    // four-chip pattern; the larger key set that backed it (quick_links_title,
    // view_dashboard, configure_settings, view_reports, quick_link_*) has
    // been dropped because nothing references it anywhere in the codebase.

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
    'step4_desc' => 'After 2+ months of data, predictions will automatically generate. With 60+ days of data, the system upgrades from the Basic linear model to the Advanced Weighted model.',
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

    'arima_model_title' => 'Advanced Weighted Model',
    'arima_model_subtitle' => 'Automatically activated with sufficient data',
    'arima_model_features' => '<ul>
        <li><i class="fas fa-check"></i> Requires: 60+ days in last 3 months</li>
        <li><i class="fas fa-check"></i> Method: Multi-window weighted moving average + multiplicative seasonality + trend momentum</li>
        <li><i class="fas fa-check"></i> Seasonality: Learned from each corporation\'s own history (day-of-week, week-of-month, month-of-year)</li>
        <li><i class="fas fa-check"></i> Predictions: 30, 60, 90 days with different confidence</li>
        <li><i class="fas fa-check"></i> Confidence intervals: Derived from historical volatility (standard deviation)</li>
        <li><i class="fas fa-check"></i> Factors: Seasonal, momentum, activity, volatility</li>
        <li><i class="fas fa-check"></i> Bounds: Upper/lower prediction bounds</li>
        <li><i class="fas fa-check"></i> Metadata: Detailed analysis factors</li>
    </ul>',

    'model_migration' => 'Automatic Model Migration',
    'model_migration_desc' => 'As your corporation accumulates data, the system automatically upgrades from the Basic linear model to the Advanced Weighted model on day 61+. This transition is seamless and requires no manual intervention. You can identify which model is being used by checking the prediction_method field in the database (simple_linear vs advanced_weighted) or the metadata displayed in predictions.',

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
    'director_tabs_intro' => 'The Director View provides comprehensive financial oversight through nine specialised tabs: Overview, Analytics, Trends, Performance, Cash Flow, Reports, plus three new in v3.0.0 - Top Contributors, Profit Attribution, and Alliance Tax. Each tab offers unique insights and tools for managing your corporation\'s finances.',

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
        <li>✅ PDF export</li>
        <li>✅ CSV export</li>
        <li>🚧 Email integration (coming soon)</li>
    </ul>',
    'reports_tab_best' => 'Sharing financial summaries with leadership, scheduled updates',

    'predictions_tab_title' => 'Predictions Tab',
    'predictions_tab_purpose' => 'View and analyze future balance predictions with confidence intervals.',
    'predictions_tab_features' => '<ul>
        <li>30, 60, and 90-day predictions (Advanced Weighted model)</li>
        <li>Confidence interval visualization</li>
        <li>Upper and lower bound forecasts</li>
        <li>Prediction method indicator (Basic linear vs. Advanced Weighted)</li>
        <li>Historical prediction accuracy tracking</li>
        <li>Seasonal pattern identification</li>
        <li>Momentum and activity factor analysis</li>
    </ul>',
    'predictions_tab_best' => 'Long-term planning, budget forecasting, risk assessment',

    // New in v3.0.0
    'contributors_tab_title' => 'Top Contributors Tab',
    'contributors_tab_purpose' => 'Per-member corp wallet contribution leaderboard, grouped by main character so a SeAT user\'s alts collapse under their main (click the caret to expand the alt list).',
    'contributors_tab_features' => '<ul>
        <li><strong>Ratting</strong>: bounty_prizes + ess_escrow_transfer + bounty_prize_corporation_tax variants. Attribution uses <code>context_id</code> when populated so NPC faction IDs no longer appear as ratters.</li>
        <li><strong>Mission</strong>: agent_mission_reward + agent_mission_time_bonus_reward + their corp-tax variants.</li>
        <li><strong>Industry</strong>: industry_job_tax from corp-affiliated members running jobs on corp structures.</li>
        <li><strong>Tax Payment</strong>: Mining Manager tax payments (matched via <code>mining_taxes.transaction_id</code> first, description tax-code as fallback). Displays <code>paid / owed</code> with compliance percentage; below 80% renders yellow. Column hidden when MM is absent.</li>
        <li><strong>Voluntary Donation</strong>: player_donation rows not linked to MM and without a description tax-code. Column hidden when MM is absent (donations still count toward Total).</li>
        <li><strong>Alliance Tax + Net to Corp</strong>: Visible only when any per-bucket alliance rate is above zero. Shows the alliance share and what the corp keeps after surrendering it.</li>
        <li><strong>Main-character grouping</strong>: Resolved via <code>refresh_tokens.user_id</code> &rarr; <code>users.main_character_id</code>. Characters with no linked SeAT user appear ungrouped.</li>
    </ul>',
    'contributors_tab_best' => 'Identifying top contributors, surfacing inactive members, validating MM tax compliance per character',

    'profit_attribution_tab_title' => 'Profit Attribution by Activity Tab',
    'profit_attribution_tab_purpose' => 'Per-activity ranking of corp profit income. Top Contributors asks <em>who</em> paid; Profit Attribution asks <em>what activity</em> drove the income, so directors can decide where to invest corp resources.',
    'profit_attribution_tab_features' => '<ul>
        <li><strong>Activity Share pie chart</strong>: % of total profit by activity bucket (Ratting / Mission / Industry / Mining Tax / Voluntary Donations when MM is installed; merged Donations bucket when not).</li>
        <li><strong>Period-over-period summary</strong>: Total profit this period, prior period total, trend with direction indicator.</li>
        <li><strong>Per-activity efficiency table</strong>: Total, distinct members contributing, average per member, % of profit, trend vs prior calendar period (color-coded).</li>
        <li>Period selector matches the Top Contributors pattern.</li>
    </ul>',
    'profit_attribution_tab_best' => 'Strategic resource allocation - deciding whether to expand mining infrastructure, fleet doctrines, or industry rigs',

    'alliance_tax_tab_title' => 'Alliance Tax Reconciliation Tab',
    'alliance_tax_tab_purpose' => 'Compares <strong>expected</strong> alliance tax (calculated from per-bucket rates × corp-wide member contribution income) against <strong>actual</strong> alliance tax (sum of outgoing payments matching the configured recipient IDs or description keywords).',
    'alliance_tax_tab_features' => '<ul>
        <li><strong>Grouped bar chart</strong>: Expected vs Actual across the trailing 3, 6, or 12 months.</li>
        <li><strong>Per-month detail table</strong>: Expected per bucket, observed total, difference (green = aligned, yellow = overpaid, red = underpaid).</li>
        <li><strong>Match rules</strong>: Recipient party IDs and / or description keywords (OR-combined). Operators pick whichever signal is more stable for their workflow.</li>
        <li>When neither match rule is configured the tab still shows the calculated expected amounts so operators can see projected liability even without the matching side.</li>
        <li>Keyword matching is case-insensitive and contains-based with LIKE wildcards properly escaped, so a literal <code>%</code> in a memo does not become a wildcard.</li>
    </ul>',
    'alliance_tax_tab_best' => 'Auditing alliance remittance, catching missed payments, validating per-bucket rates against actual outflows',

    'data_refresh' => 'Data Refresh',
    'data_refresh_desc' => 'Most tabs refresh automatically every 5 minutes. Manual refresh buttons are available on each tab for immediate updates. The refresh rate can be configured in Settings. Top Contributors / Profit Attribution / Alliance Tax lazy-load on tab open and re-query when the period selector changes.',

    // Predictions Section - Technical
    'predictions_guide' => 'Prediction System - Technical Details',
    'predictions_intro' => 'Corp Wallet Manager uses a dual-model prediction system that adapts to data availability: a simple linear model for new corps, and an advanced weighted-average model with learned per-corporation seasonal factors once 60+ days of history exist.',

    'model_selection_title' => 'Model Selection Logic',
    'model_selection_desc' => 'The system automatically chooses the appropriate prediction model based on data availability:',
    'model_selection_code' => 'if (corporation has 60+ days of data in last 3 months) {
    Use Advanced Weighted model
    - 12-month weighted moving average
    - Learned per-corp seasonal factors
    - Trend momentum overlay
    - Confidence intervals from historical volatility
    - Multiple timeframe predictions (30/60/90 days)
} else {
    Use Basic linear model (fallback)
    - Simple 6-month average
    - Linear trend calculation
    - 30-day predictions only
}',

    'arima_details_title' => 'Advanced Weighted Model',
    'arima_details_desc' => 'The Advanced model is a weighted moving average with multiplicative seasonality and a trend-momentum overlay. It is not an ARIMA model - there are no fitted AR/MA coefficients or differencing - but it is more sophisticated than the Basic linear fallback:',
    'arima_details_list' => '<ul>
        <li><strong>Data window:</strong> 12 months of daily aggregates from corporation_wallet_journals</li>
        <li><strong>Weighted averages:</strong> Five time-window tiers (current month 35%, previous month 25%, older quarters 20%/12%, half-year tail 8%)</li>
        <li><strong>Learned seasonality:</strong> Per-corporation day-of-week, week-of-month and month-of-year factors derived from the corp\'s own history - not hardcoded assumptions about "typical EVE"</li>
        <li><strong>Trend momentum:</strong> Ratio of 7-day vs 30-day moving averages, decayed with horizon</li>
        <li><strong>Activity analysis:</strong> Recurring-transaction detection from the ref_type breakdown</li>
        <li><strong>Volatility:</strong> Rolling standard deviation over multiple windows</li>
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
    'improvement_desc' => 'As your corporation accumulates more data, prediction accuracy naturally improves. The Advanced Weighted model becomes more refined with each month of additional data, learning your corporation\'s unique financial patterns (day-of-week, week-of-month and - once 12+ months of history exist - month-of-year seasonality).',

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
        <li><strong>Executive:</strong> High-level corp financial summary, ideal for leadership briefings.</li>
        <li><strong>Financial:</strong> Detailed P&L-style breakdown of income, expense, and transaction patterns.</li>
        <li><strong>Division:</strong> Specialised reports focusing on individual wallet divisions.</li>
        <li><strong>Custom:</strong> Generate reports for any date range you specify. Perfect for board meetings or specific period analysis.</li>
        <li><strong>Weekly:</strong> Automated Monday summaries showing the previous week\'s financial performance.</li>
        <li><strong>Monthly:</strong> Comprehensive monthly summaries delivered on the 1st of each month.</li>
        <li><strong>Annual</strong> (v3.0.0): A multi-section retrospective covering a full calendar year. PDF has a cover page + Executive Summary + Monthly Trend + Top 10 Contributors + Activity Mix + Notable Transactions + Division Performance + Alliance Tax Remits + Milestones Reached + Risk Assessment + Year-over-Year comparison. CSV mirrors the same seven retrospective sections. Generate from the Director view Reports tab (year picker appears when type is Annual).</li>
        <li><strong>Quarterly</strong> (v3.0.0): Same multi-section template as Annual, scoped to one fiscal quarter with Quarter-over-Quarter comparison instead of YoY. Generate from the Director view Reports tab (quarter picker appears when type is Quarterly).</li>
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
    'discord_integration_intro' => 'Reports and wallet alerts are delivered to Discord through webhooks, configured in Settings under Discord Webhooks.',

    'discord_setup' => 'Setting Up Discord Webhooks',
    'discord_step1_title' => 'Create the Webhook in Discord',
    'discord_step1_desc' => 'In Discord, go to Server Settings &rarr; Integrations &rarr; Webhooks &rarr; New Webhook. Pick the destination channel and copy the webhook URL (https://discord.com/api/webhooks/...).',
    'discord_step2_title' => 'Open Settings &rarr; Discord Webhooks',
    'discord_step2_desc' => 'In CWM, open Settings and click Discord Webhooks in the sidebar. Click Add Webhook to open the inline form.',
    'discord_step3_title' => 'Fill in the Webhook',
    'discord_step3_desc' => 'Enter a friendly name (e.g. "Leadership Channel"), optionally scope it to one corporation (leave global to receive all corps), paste the Discord URL, and (optional) pick a role mention. If a Discord role provider is installed (SeAT Broadcast / SeAT Connector / warlof) the Pick from Discord button opens an inline role list with color dots and snowflake IDs; otherwise enter the role ID by hand.',
    'discord_step4_title' => 'Pick Subscriptions',
    'discord_step4_desc' => 'Tick which reports (Weekly / Monthly / On-Demand) and which alerts (Large Transfer / Low Balance / Contribution Drop / Unusual Recipient) this webhook should receive. A single corp can have multiple webhooks subscribed to different categories (e.g. one channel for reports, another for alerts).',
    'discord_step5_title' => 'Test Delivery',
    'discord_step5_desc' => 'After saving, use the Test button on the webhook row to fire a sample delivery. For per-category test deliveries, the Diagnostic page (admin-only) has a Notification Testing tab that lets you fire any alert category against any subscribed webhook without waiting for a real trigger.',

    'report_automation' => 'Report Automation',
    'report_automation_intro' => 'Automated reports are operator-configurable per corporation and per cadence from Settings &rarr; Scheduled Reports. The dispatcher cron checks every 5 minutes for due schedules, so a schedule set for 03:00 fires within a 5-minute window of 03:00. All schedule times are UTC.',
    'automation_schedule' => '<ul>
        <li><strong>Cadences:</strong> Daily / Weekly / Monthly / Quarterly / Annual. Each row picks a day-of-week (weekly), day-of-month (monthly + quarterly + annual, capped at 28 so Feb does not skip) or month + day pair (annual), plus a UTC hour and minute.</li>
        <li><strong>First-install defaults:</strong> on upgrade to v3.0, weekly (Monday 03:30 UTC) and monthly (day 1 at 03:00 UTC) schedules are auto-created for every corporation with existing wallet history. That preserves pre-3.0 delivery cadence without re-configuration. Edit, disable, or delete them from the panel.</li>
        <li><strong>Reporting window:</strong> each schedule covers the COMPLETED prior period when it fires (monthly on the 1st reports the prior calendar month; weekly on Monday reports the prior Mon..Sun; quarterly reports the prior quarter; annual reports the prior calendar year; daily reports yesterday).</li>
        <li><strong>Delivery routing:</strong> schedules drive WHEN a report runs. WHERE it is delivered is configured separately under Settings &rarr; Discord Webhooks (each webhook chooses which report types it wants).</li>
    </ul>',

    'notification_triggers' => 'Event Alerts',
    'notification_triggers_intro' => 'Beyond scheduled reports, the hourly alert detector watches for four classes of wallet activity:',
    'notification_triggers_list' => '<ul>
        <li><strong>Large Transaction Alert:</strong> When a single wallet transaction (in or out) meets the configured ISK threshold. Inter-division transfers are explicitly excluded from the scan so corp-internal rebalancing does not spam alerts.</li>
        <li><strong>Low Balance Alert:</strong> When a corporation total balance crosses below the configured threshold. Latched per corporation so it fires once per crossing, not every hour.</li>
        <li><strong>Member Contribution Drop (anomaly):</strong> When a member\'s prior 3-month contribution average collapses to under 20% of the previous 3-month window. Latched per (corp, character) in <code>corpwalletmanager_anomaly_state</code> and cleared on recovery above 50% so re-stalls after recovery re-fire.</li>
        <li><strong>Unusual Recipient (anomaly):</strong> When a <code>corporation_account_withdrawal</code> goes to a party with no prior interaction history in the last 7 days, above a configurable threshold.</li>
    </ul>
    <p>All four configurable in Settings &rarr; Alert Thresholds (<code>0</code> disables an alert). Each Discord webhook independently chooses which alerts it receives in the webhook edit form. When Manager Core is installed, alerts are also published to MC\'s cross-plugin EventBus under <code>wallet.transaction_detected</code> / <code>wallet.balance_low</code> / <code>member.contribution.drop_detected</code> / <code>wallet.unusual_recipient_detected</code>.</p>',

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
    'cmd_aggregation_schedule' => 'Daily at 01:00 UTC',
    'cmd_aggregation_desc' => 'Calculates daily average, stores monthly balance, computes trends',
    'cmd_aggregation_options' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li><code>--date=YYYY-MM-DD</code> - Process specific date</li>
        <li><code>--corporation=ID</code> - Process specific corporation only</li>
        <li><code>--force</code> - Recalculate even if already exists</li>
    </ul>',

    'cmd_compute_predictions' => 'Compute Predictions',
    'cmd_predictions_purpose' => 'Generates future balance predictions',
    'cmd_predictions_schedule' => 'Daily at 02:00 UTC',
    'cmd_predictions_desc' => 'Runs prediction model (Basic linear or Advanced Weighted), stores 30/60/90 day forecasts',
    'cmd_predictions_options' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li><code>--corporation=ID</code> - Generate predictions for specific corporation</li>
        <li><code>--force</code> - Force regeneration even if recent predictions exist</li>
        <li><code>--days=30,60,90</code> - Specify prediction timeframes</li>
    </ul>',

    'cmd_compute_division_predictions' => 'Compute Division Predictions',
    'cmd_division_predictions_purpose' => 'Generates predictions for individual wallet divisions',
    'cmd_division_predictions_schedule' => 'Daily at 02:30 UTC',
    'cmd_division_predictions_desc' => 'Same as main predictions but for each division separately',
    'cmd_division_predictions_options' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li><code>--corporation=ID</code> - Process specific corporation</li>
        <li><code>--division=ID</code> - Process specific division only</li>
        <li><code>--force</code> - Force regeneration</li>
    </ul>',

    'cmd_generate_report' => 'Generate Reports',
    'cmd_report_purpose' => 'Generates and sends automated reports',
    'cmd_report_schedule' => 'Operator-configurable per (corporation, cadence) via Settings -> Scheduled Reports. The dispatcher cron ticks every 5 minutes. First-install defaults seed a weekly (Monday 03:30 UTC) + monthly (day 1 at 03:00 UTC) row for every corp with wallet history.',
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
    'manual_commands_intro' => 'After a fresh install or a major upgrade the easiest one-shot is <code>corpwalletmanager:initialize</code> at the top of this list. It runs every cache-populating command in the correct order, with a per-step progress bar, and is safe to re-run because every underlying step is an idempotent upsert. The long-form individual commands underneath still work for operators who want fine control or who need to re-populate just one piece (e.g. only the contribution cache after an MM tax-code change).',

    'cmd_initialize' => 'Initialize (recommended after install / upgrade)',
    'cmd_initialize_purpose' => 'Run every CWM cache-populating step in the right order for the first time',
    'cmd_initialize_when' => 'After a fresh install, after upgrading to a new major version, or after an extended outage where the queue fell behind',
    'cmd_initialize_desc' => 'Orchestrates wallet + division balance backfill, daily aggregation, predictions, contribution backfill, personal-wallet aggregate backfill, milestone state recompute, and wallet alert detection. Each step prints its own progress bar. Step failures are recorded and the run continues, so a single bad step does not abort the whole init. Idempotent: re-running is safe and just re-populates the same data.',
    'cmd_initialize_options' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li><code>--months=12</code> - How many months of history to backfill (default: 12)</li>
        <li><code>--days=180</code> - How many days of daily aggregation history to backfill (default: 180)</li>
        <li><code>--skip=wallet,division,daily,predictions,contributions,personal,milestones,alerts</code> - Comma-separated step names to skip (case-insensitive; unknown keys produce a warning but do not fail)</li>
        <li><code>--force</code> - Skip the are-you-sure confirmation prompt</li>
        <li><code>--queue</code> - Dispatch each step as a queued job instead of running synchronously</li>
    </ul>',

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

    'cmd_backtest' => 'Backtest Predictions',
    'cmd_backtest_purpose' => 'Compares yesterday\'s predictions against today\'s actual balances to track model accuracy (MAPE)',
    'cmd_backtest_schedule' => 'Daily at 02:45 UTC (after predictions are computed)',
    'cmd_backtest_desc' => 'Records the observed prediction error for each corporation and uses it to size the confidence intervals shown in the Predictions UI',

    'cmd_detect_alerts' => 'Detect Wallet Alerts',
    'cmd_detect_alerts_purpose' => 'Scans recent wallet activity for alert conditions (large transactions, low balance crossings)',
    'cmd_detect_alerts_schedule' => 'Hourly at :40 past',
    'cmd_detect_alerts_desc' => 'Reads alert thresholds from settings; delivers alerts to subscribed Discord webhooks and publishes <code>wallet.transaction_detected</code> / <code>wallet.balance_low</code> events to Manager Core when MC is installed. Thresholds default to 0 (disabled); set them in Settings &rarr; Alert Thresholds.',

    'cmd_compute_contributions' => 'Compute Character Contributions',
    'cmd_compute_contributions_purpose' => 'Updates the per-character contribution cache incrementally from new wallet journal entries',
    'cmd_compute_contributions_schedule' => 'Hourly at :50 past',
    'cmd_compute_contributions_desc' => 'Reads journal entries since the last watermark, classifies each into a per-character bucket (ratting, mission, tax payment, voluntary donation, withdrawal), and atomically increments the corpwalletmanager_character_contributions cache. Powers the Top Contributors leaderboard and the HR Manager capabilities.',

    'cmd_backfill_contributions' => 'Backfill Character Contributions',
    'cmd_backfill_contributions_purpose' => 'Rebuilds the per-character contribution cache for a given number of trailing months',
    'cmd_backfill_contributions_when' => 'After installing v3.0.0 (the hourly job adopts the high-water mark on first run and does not backfill on its own), or after a Mining Manager tax-code configuration change that should re-classify historical donations',
    'cmd_backfill_contributions_desc' => 'Wipes existing cache rows for the affected months and replays corporation_wallet_journals through the classification pipeline. Idempotent and safe to re-run.',
    'cmd_backfill_contributions_options' => '<ul style="margin-left: 20px; margin-top: 5px;">
        <li><code>--months=6</code> - How many trailing months (including current) to rebuild. Default: 6.</li>
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
        <li><strong>Prediction Method:</strong> Choose between Auto (recommended), Basic Only, or Advanced Only. Auto uses the Advanced Weighted model when enough data exists.</li>
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

    'report_settings' => 'Report Settings (in Settings &rarr; Discord Webhooks)',
    'report_settings_list' => '<ul>
        <li><strong>Discord Webhooks panel:</strong> All Discord delivery is now managed under Settings &rarr; Discord Webhooks. Add any number of webhooks, each optionally scoped to a single corporation, each with its own role mention, each choosing which report types (Weekly / Monthly / On-Demand) and which alert types (Large Transfer / Low Balance / Contribution Drop / Unusual Recipient) it receives.</li>
        <li><strong>Per-Webhook Delivery Health:</strong> Each webhook tracks success and failure counts plus the last error, shown as a percentage on the webhook list (green &geq; 90%, yellow &geq; 70%, red below).</li>
        <li><strong>Test button per row:</strong> Fires a sample delivery to the saved URL. For per-category test deliveries (large transfer, contribution drop, etc) use the Diagnostic page Notification Testing tab.</li>
        <li><strong>Notification Routing</strong> (Settings &rarr; Notification Routing): Read-only map of every category, the webhooks routing it, and the resolved role pill. Flags categories with no enabled subscriber.</li>
        <li><strong>Scheduled cadence:</strong> Configured per (corporation, cadence) under Settings &rarr; Scheduled Reports. The dispatcher cron checks every 5 minutes for due schedules. First-install defaults seed weekly (Monday 03:30 UTC) + monthly (day 1 at 03:00 UTC) rows for every corp with wallet history. Quarterly + Annual cadences are available too.</li>
    </ul>',

    'alert_settings' => 'Alert Settings (in Settings &rarr; Alert Thresholds)',
    'alert_settings_list' => '<ul>
        <li><strong>Large Transaction Threshold:</strong> ISK amount that triggers a large-transaction alert. <code>0</code> disables it.</li>
        <li><strong>Low Balance Threshold:</strong> Corporation balance that triggers a low-balance alert. <code>0</code> disables it. Latched per corp so it fires once per crossing.</li>
        <li><strong>Anomaly: Contribution Drop Threshold:</strong> The collapse ratio (default 20%) that triggers the contribution-drop alert. <code>0</code> disables.</li>
        <li><strong>Anomaly: Unusual Recipient Threshold:</strong> ISK threshold for the first-time-recipient alert. <code>0</code> disables. The 7-day cold window is fixed.</li>
        <li><strong>Webhook Subscriptions:</strong> Each Discord webhook chooses which alerts it receives in its edit form (Settings &rarr; Discord Webhooks).</li>
        <li><strong>Event Bus:</strong> When Manager Core is installed, all four alerts are also published to MC\'s cross-plugin EventBus under <code>wallet.transaction_detected</code> / <code>wallet.balance_low</code> / <code>member.contribution.drop_detected</code> / <code>wallet.unusual_recipient_detected</code>.</li>
    </ul>',

    'advanced_settings' => 'Advanced Settings',
    'advanced_settings_list' => '<ul>
        <li><strong>Data Retention:</strong> How long to keep historical data. Options: 6 months, 1 year, 2 years, 5 years, Forever.</li>
        <li><strong>Enable Access Logging:</strong> Log member view access for analytics. Viewable in Settings → Access Logs.</li>
        <li><strong>Cache Duration:</strong> How long to cache API responses. Default: 5 minutes. Range: 1-60 minutes.</li>
        <li><strong>Debug Mode:</strong> Enable detailed logging for troubleshooting. Disable in production.</li>
    </ul>',

    'maintenance_actions' => 'Maintenance Actions',
    'maintenance_actions_intro' => 'Settings &rarr; Maintenance has manual triggers for the data jobs. The selected corporation badge at the top of the card shows which corp the jobs will run for (all corps if none is selected):',
    'maintenance_actions_list' => '<ul>
        <li><strong>Wallet Backfill:</strong> Manually start historical balance backfill (max 3 months from ESI).</li>
        <li><strong>Compute Predictions:</strong> Force immediate prediction recalculation for the selected corp.</li>
        <li><strong>Division Backfill:</strong> Backfill division-specific balance data.</li>
        <li><strong>Division Predictions:</strong> Recalculate predictions per division.</li>
        <li><strong>Backfill Contributions</strong> (v3.0.0): Rebuilds the per-character contribution cache for the selected number of trailing months (1 / 3 / 6 / 12 / 24, default 6). Runs in the background. Existing cache rows for the chosen period are deleted and rebuilt from scratch, so the button is safe to run repeatedly. Use after first install, alliance-tax setup, MM install, or a classifier upgrade.</li>
        <li><strong>Reset Settings</strong> (Settings &rarr; General &rarr; sticky save row): Restore default settings (requires confirmation).</li>
    </ul>',

    'warning' => 'Warning',
    'settings_warning' => 'Changing corporation selection or disabling features will affect all users. Resetting settings cannot be undone. Always back up your configuration before making major changes.',

    'saving_changes' => 'Saving Changes',
    'saving_changes_desc' => 'Settings are saved immediately when you click "Save Settings". Most changes take effect immediately, but some (like prediction frequency) will apply on the next scheduled run.',

    // Member View
    'member_view_title' => 'Member View - Features & Access',
    'member_view_intro' => 'The Member View provides corporation members with relevant financial information without exposing sensitive details. Access via: Main Menu &rarr; Corp Wallet Manager &rarr; Member View',

    'member_view_tabs_heading' => 'Three-Tab Layout',
    'member_view_tabs_intro' => 'The member view splits into three tabs so each lens stays focused:',
    'member_view_tabs_list' => '<ul>
        <li><strong>Corp Wallet</strong> (default tab): The "look at the corp\'s data" lens. Corporation health, monthly trend, activity level, performance score, corp goals, balance trend chart, performance metrics radar, weekly activity pattern, monthly summary, upcoming corp events, and the Top Contributors leaderboard.</li>
        <li><strong>My Contribution</strong>: The "what I have done for the corp" lens. Personal contribution card with rank / percentile / lifetime / per-bucket sparkline strip, the My Mining Manager Tax Compliance card (only when Mining Manager is installed and the operator has not turned the card off), and the My Milestones ladder.</li>
        <li><strong>My Personal Wallet</strong>: The "how am I doing personally" lens. Aggregates the viewer\'s SeAT personal wallet across every character they own (no corp filter; personal wallet is independent of corp affiliation). Income / expense / net flow info-boxes with trend pills vs the prior period, top 5 income + expense ref types, a 6-month end-of-month balance sparkline, the top 5 biggest income + expense transactions, and a per-character breakdown table so the viewer can see which alt is the big earner.</li>
    </ul>',
    'member_view_aggregation_heading' => 'Character Aggregation Rule',
    'member_view_aggregation_desc' => 'Both the My Contribution tab and the My Personal Wallet tab aggregate across every character the viewer owns (every active row in <code>refresh_tokens</code> for the user), regardless of which corp those characters are currently in. For My Contribution that means a main who has since moved to a different corp still shows their full lifetime contribution to the corp being viewed (the corp scoping happens at the contribution-cache query, not at the character-ownership step). For My Personal Wallet that means every character is included with no corp filter at all, because personal wallet is per-character and has no corp affiliation. The refresh button in the tab nav refreshes whichever tab is currently active.',

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
    'faq_a4' => 'Prediction accuracy improves with more data. The Advanced Weighted model (activated after 60+ days of data) provides significantly better accuracy than the Basic linear fallback, typically within 10-15% of actual values. Note: despite older documentation, the model is not ARIMA - it is a weighted moving average with learned seasonal factors and a trend-momentum overlay.',
    'faq_q5' => 'What\'s the difference between Director and Member views?',
    'faq_a5' => 'Director View provides full access to all features, analytics, and sensitive data. Member View shows only aggregated financial health indicators without sensitive details.',
    'faq_q6' => 'Can I export the data?',
    'faq_a6' => 'Yes! Use the Reports feature to generate and export financial reports. PDF export and additional formats are coming soon.',
    'faq_q7' => 'How do I set up Discord notifications?',
    'faq_a7' => 'Open Settings &rarr; Discord Webhooks, click Add Webhook, paste your Discord webhook URL, pick a corporation scope (or leave global), tick the report types and alert types you want, and save. The webhook can mention a Discord role; pick from the inline role list if SeAT Broadcast / SeAT Connector / warlof is installed. Use the per-row Test button to verify delivery.',
    'faq_q8' => 'What happens if I change corporations?',
    'faq_a8' => 'All historical data for the previous corporation is preserved. You can switch back anytime. The new corporation will start collecting data immediately.',
    'faq_q9' => 'How far back can I backfill data?',
    'faq_a9' => 'ESI provides up to 3 months of wallet journal data. For older data, the plugin uses wallet journal entries already stored in your SeAT database from normal operation.',
    'faq_q10' => 'What permissions do I need?',
    'faq_a10' => 'Directors need <code>corpwalletmanager.director_view</code> for the full director view. Members need <code>corpwalletmanager.member_view</code> for the Member View. Settings + Diagnostic pages require <code>corpwalletmanager.settings</code> (admin-only). Permissions are managed through SeAT\'s role system.',
    'faq_q11' => 'Why do some IDs show as "Unknown" on Top Contributors or Alliance Tax?',
    'faq_a11' => 'CWM resolves names through a layered pipeline: <code>character_infos</code> &rarr; <code>corporation_infos</code> &rarr; <code>alliance_infos</code> &rarr; <code>universe_names</code> &rarr; ESI /universe/names/. If a name still shows unresolved, it usually means the party is external to your corp and SeAT has never synced it. The ESI fallback runs synchronously on first lookup and writes back into <code>universe_names</code> so the next lookup is free. NPC range IDs (under 90M) are deliberately not resolved as ratters thanks to the bounty / mission attribution fix.',
    'faq_q12' => 'Why doesn\'t HR Manager see my milestone events?',
    'faq_a12' => 'Milestone events (<code>member.contribution.stalled</code>, <code>member.contribution.milestone</code>, <code>member.tax.compliance_dropped</code>) are published to Manager Core\'s EventBus, which HR subscribes to. Manager Core MUST be installed for HR to receive them. Without MC, CWM still tracks state locally in <code>corpwalletmanager_member_milestone_state</code> so the moment MC is installed, future transitions fire normally (no backfill needed). Each milestone publishes exactly once per state transition; if a member already crossed the 1B rung, that event has already fired.',
    'faq_q13' => 'Where did Corp Withdrawal go in the expense breakdown?',
    'faq_a13' => 'v3.0.0 splits alliance tax out of the <code>corporation_account_withdrawal</code> bucket on expense breakdowns. Any outgoing row matching the recipient party IDs or description keywords configured in Settings &rarr; Alliance Tax now appears under a dedicated Alliance Tax line, so the remaining Corp Withdrawal line is no longer inflated by remits. The Alliance Tax tab on the Director view shows the reconciliation in full.',
    'faq_q14' => 'How does inter-division transfer filtering work?',
    'faq_a14' => 'ISK moved between divisions of the same corp (rows where both <code>first_party_id</code> and <code>second_party_id</code> equal the corp id) is filtered out plugin-wide via a shared <code>JournalFilters::excludeInternalTransfers</code> helper. It applies to every chart, backfill, prediction model, scheduled report, alert scan, and member-facing aggregate. Without the filter, income and expense were each double-counted by the transfer amount even though the pair netted to zero on balance. Diagnostic &rarr; Wallet Trace shows the filter decision per row.',
    'faq_q15' => 'I upgraded from v2 but the namespace rename broke something. What do I do?',
    'faq_a15' => 'Queued background jobs in Redis serialise their PHP class FQN, so any in-flight v2 job (e.g. <code>Seat\\CorpWalletManager\\Jobs\\...</code>) cannot deserialise after the v3 rename to <code>CorpWalletManager\\Jobs\\...</code>. For standard SeAT Docker stacks, run <code>docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down && docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d</code> (the default Redis container has no persistent volume so this clears queue + cache + sessions in one shot). a plain restart is NOT sufficient because it pauses containers without destroying them. The database is unaffected; only the Redis-resident state needs clearing.',

    // Troubleshooting
    'troubleshooting_guide' => 'Troubleshooting Guide',
    'troubleshooting_intro' => 'Common issues and their solutions.',

    'common_issues' => 'Common Issues',
    
    'issue1_title' => 'Balance Not Updating',
    'issue1_desc' => 'If your balance appears frozen or outdated:',
    'issue1_solutions' => '<ul>
        <li>Verify the corporation is selected in Settings &rarr; General.</li>
        <li>Open Diagnostic &rarr; Health Checks and review the Job Status section for the last run timestamp of <code>update-hourly</code>.</li>
        <li>Manually trigger an update from Settings &rarr; Maintenance &rarr; Wallet Backfill (or run <code>php artisan corpwalletmanager:update-hourly</code> for the selected corp).</li>
        <li>Open Diagnostic &rarr; System Validation to confirm CWM\'s ESI token / scope situation.</li>
    </ul>',

    'issue2_title' => 'Predictions Not Generating',
    'issue2_desc' => 'If predictions are missing or outdated:',
    'issue2_solutions' => '<ul>
        <li>Ensure you have at least 2 months of balance data (Diagnostic &rarr; Data Integrity shows row counts per table).</li>
        <li>Check that predictions are enabled in Settings &rarr; General.</li>
        <li>Manually trigger predictions from Settings &rarr; Maintenance &rarr; Compute Predictions (or run <code>php artisan corpwalletmanager:compute-predictions --force</code>).</li>
        <li>Run the integrity check: <code>php artisan corpwalletmanager:integrity-check --detailed</code>.</li>
    </ul>',

    'issue3_title' => 'Reports or Alerts Not Reaching Discord',
    'issue3_desc' => 'If Discord deliveries fail or simply do not arrive:',
    'issue3_solutions' => '<ul>
        <li>Open Settings &rarr; Discord Webhooks and confirm the webhook is <em>Enabled</em> and subscribed to the category you expect (Health column shows last-known success %).</li>
        <li>Open Settings &rarr; Notification Routing and confirm a webhook actually routes the category. The map flags categories with no enabled subscriber.</li>
        <li>Open Diagnostic &rarr; Notification Testing and fire a test delivery for the category against the corp. The result shows per-webhook outcomes with Discord\'s HTTP detail.</li>
        <li>Hover the warning icon next to the webhook\'s health pill to see the last delivery error.</li>
        <li>Verify the Discord channel allows webhook posts and the webhook URL has not been rotated in Discord (rotating revokes the URL).</li>
    </ul>',

    'issue4_title' => 'Top Contributors Shows Unknown / NPC IDs',
    'issue4_desc' => 'If contributors render as bare IDs (e.g. "1000125") or NPC faction names:',
    'issue4_solutions' => '<ul>
        <li>v3.0.0 fixes bounty / mission attribution to use <code>context_id</code> (the real character) instead of <code>first_party_id</code> (which CCP fills with the NPC faction id). Run Settings &rarr; Maintenance &rarr; Backfill Contributions with 6 months selected to replay the classifier over historical rows. The forward-only cleanup migration also removes pre-v3 bad rows from the cache.</li>
        <li>If a row still shows an unresolved ID, open Diagnostic &rarr; Wallet Trace and paste the journal id. The trace walks the row through every step of the classify pipeline and shows the name-resolver fallback chain (character_infos &rarr; corporation_infos &rarr; alliance_infos &rarr; universe_names &rarr; ESI /universe/names/).</li>
        <li>External players using your corp infrastructure (industry tax payers not currently affiliated to your corp) intentionally do NOT appear on Top Contributors; they show in the income / breakdown views as corp revenue but stay off the per-member leaderboard.</li>
    </ul>',

    'issue5_title' => 'Tax Payment Column Hidden on Top Contributors',
    'issue5_desc' => 'If the Tax Payment column does not appear:',
    'issue5_solutions' => '<ul>
        <li>The Tax Payment and Voluntary Donation columns only render when Mining Manager is installed and active. Without MM, donations still count toward Total but are not shown as a separate column (CCP exposes no distinction between tax payments and voluntary gifts; only MM provides the signal).</li>
        <li>If MM is installed but the column shows zeros, run Settings &rarr; Maintenance &rarr; Backfill Contributions so historical donations get re-classified using MM\'s <code>mining_taxes.transaction_id</code> linkage.</li>
        <li>Open Diagnostic &rarr; Donation Audit to see batch view of every <code>player_donation</code> row with the classifier\'s bucket decision and whether MM linked it. Rows flagged "suspect" are voluntary-bucketed donations whose description hints at tax that MM has not linked.</li>
    </ul>',

    'issue6_title' => 'HR Manager Does Not See My Milestones',
    'issue6_desc' => 'If HR Manager\'s member profile does not show CWM milestone events:',
    'issue6_solutions' => '<ul>
        <li>Manager Core MUST be installed and active. The milestone events (<code>member.contribution.stalled</code>, <code>member.contribution.milestone</code>, <code>member.tax.compliance_dropped</code>) are published to MC\'s EventBus, which HR subscribes to. Without MC, milestones are still tracked locally in <code>corpwalletmanager_member_milestone_state</code> so they auto-fire the moment MC is installed without a backfill.</li>
        <li>Each milestone publishes exactly once per state transition. If a member has already crossed the 1B rung, the 1B event has already fired; the 5B event fires when they cross that next.</li>
        <li>Confirm CWM &rarr; Diagnostic &rarr; System Validation reports MC as detected.</li>
    </ul>',

    'need_help' => 'Need Help',
    'support_message' => 'If you can\'t resolve your issue, open an issue at <a href="https://github.com/MattFalahe/Corp-Wallet-Manager/issues">github.com/MattFalahe/Corp-Wallet-Manager/issues</a> with the Diagnostic page screenshot and steps to reproduce. Direct contact: <a href="mailto:mattfalahe@gmail.com">mattfalahe@gmail.com</a> or the SeAT Discord (<a href="https://discord.gg/azquy29nqs">discord.gg/azquy29nqs</a>).',

    // ============================================================
    // Contributions & Tax (v3.0.0)
    // ============================================================
    'contributions_title' => 'Contributions & Tax (v3.0.0)',
    'contributions_intro' => 'CWM v3.0.0 introduces a per-character contribution cache that classifies every corp wallet journal row into a per-member bucket. The cache powers the Top Contributors leaderboard, the Profit Attribution dashboard, the Alliance Tax reconciliation tab, and 14 PluginBridge capabilities exposed to HR Manager.',
    'contributions_buckets_title' => 'Classification Buckets',
    'contributions_buckets_list' => '<ul>
        <li><strong>Ratting:</strong> <code>bounty_prizes</code> + <code>ess_escrow_transfer</code> + <code>bounty_prize_corporation_tax</code>. Attribution uses <code>context_id</code> when populated (the real character) and falls back to <code>first_party_id</code> only when that id is in the player range (&geq; 90M) so NPC faction IDs cannot appear as ratters. ESS transfers parse the ratter from the description ("Encounter Surveillance System in {system} transferred funds to {character}").</li>
        <li><strong>Mission:</strong> <code>agent_mission_reward</code> + <code>agent_mission_time_bonus_reward</code> + their <code>_corporation_tax</code> variants. Same <code>context_id</code>-first attribution as ratting.</li>
        <li><strong>Industry:</strong> <code>industry_job_tax</code> from members running jobs on corp structures. Classifier parses the installer\'s name from the description and joins <code>character_affiliations</code> to confirm current corp membership. External players using corp infrastructure show in corp revenue but stay off the per-member leaderboard.</li>
        <li><strong>Tax Payment:</strong> <code>player_donation</code> rows that Mining Manager has linked to a tax invoice via <code>mining_taxes.transaction_id</code>. Falls back to description tax-code extraction for invoices MM has not yet processed (mid-backfill race). When MM is absent, this bucket is empty and the column hides on Top Contributors.</li>
        <li><strong>Voluntary Donation:</strong> <code>player_donation</code> rows not linked to MM and without a description tax-code. When MM is absent, tax + voluntary collapse into a single "Donation" bucket (still counted toward Total, just not split out).</li>
        <li><strong>Withdrawal:</strong> Outgoing <code>corporation_account_withdrawal</code> rows. Used by HR via the <code>wallet.getCorpOutflows</code> capability for net-position analysis.</li>
    </ul>',
    'contributions_inter_division_title' => 'Inter-Division Transfer Filtering',
    'contributions_inter_division_desc' => 'ISK moved between divisions of the same corp (rows where both <code>first_party_id</code> and <code>second_party_id</code> equal the corp id) is filtered out plugin-wide via the shared <code>JournalFilters::excludeInternalTransfers</code> helper. The pair always netted to zero on balance, but income / expense / breakdown / per-division numbers were inflated by the transfer amount. The filter applies to every chart, backfill, prediction model, scheduled report, alert scan, and member-facing aggregate. Diagnostic &rarr; Wallet Trace shows the filter decision per row.',
    'contributions_alliance_tax_title' => 'Alliance Tax',
    'contributions_alliance_tax_desc' => 'Settings &rarr; Alliance Tax exposes five per-bucket rate knobs (Ratting / Mission / Tax Payment / Voluntary Donation / Industry, all default 0). Operators identify alliance remits via two complementary match rules: recipient party IDs (the alliance master character, holding corp, or alliance entity itself) and / or description keywords (a distinctive memo string like <code>MINC-TAX</code>). Rules are OR-combined. The Alliance Tax tab on the Director view shows expected vs actual side-by-side over the trailing 3 / 6 / 12 months. With all rates at 0 the columns / tab hide so corps not in an alliance see no UI churn.',
    'contributions_main_grouping_title' => 'Main-Character Grouping',
    'contributions_main_grouping_desc' => 'The Top Contributors leaderboard groups a SeAT user\'s alts under their main character (resolved via <code>refresh_tokens.user_id</code> &rarr; <code>users.main_character_id</code>). Each row shows the main with aggregate totals and an <code>(+N alts)</code> tag; clicking the caret expands a nested list of every contributing alt with its own bucket breakdown. Characters with no linked SeAT user appear ungrouped as themselves.',
    'contributions_backfill_title' => 'Backfilling the Cache',
    'contributions_backfill_desc' => 'The hourly job (<code>corpwalletmanager:compute-contributions</code>) adopts the high-water mark on first run and never replays history. Run a backfill via Settings &rarr; Maintenance &rarr; Backfill Contributions (or <code>php artisan corpwalletmanager:backfill-contributions --months=N</code>) after first install, after configuring alliance-tax rates, after installing or upgrading Mining Manager, or after a classifier upgrade. Existing cache rows for the chosen months are deleted and rebuilt, so it is safe to re-run.',

    // ============================================================
    // Discord Webhooks (v3.0.0)
    // ============================================================
    'webhooks_title' => 'Discord Webhooks (v3.0.0)',
    'webhooks_intro' => 'v3.0.0 replaces the pre-3.0 single global <code>discord_webhook_url</code> setting with a managed table of webhooks under Settings &rarr; Discord Webhooks. Add any number of webhooks, each optionally scoped to a single corporation, each with its own role mention and choice of subscriptions. Pre-3.0 settings are folded into a first-class webhook row on upgrade.',
    'webhooks_fields_title' => 'Per-Webhook Configuration',
    'webhooks_fields_list' => '<ul>
        <li><strong>Name:</strong> Operator-friendly label (e.g. "Leadership Channel", "Alerts to FC").</li>
        <li><strong>Scope:</strong> A single corporation (receives that corp\'s reports / alerts) or Global (receives every corp\'s reports / alerts).</li>
        <li><strong>Discord URL:</strong> Validated to be a Discord webhook endpoint. The stored URL is hidden from model serialisation and never written to logs.</li>
        <li><strong>Role mention:</strong> Optional Discord role ID pinged on delivery. When a Discord role provider is installed (SeAT Broadcast / SeAT Connector / warlof), the "Pick from Discord" button opens an inline picker with role color dots, names, and snowflake IDs; otherwise enter the role ID by hand. A live preview pill shows how the saved value will display.</li>
        <li><strong>Report subscriptions:</strong> Weekly summary (Mondays) / Monthly summary (1st of month) / On-demand reports (generated from the Director view).</li>
        <li><strong>Alert subscriptions:</strong> Large transactions / Low balance / Member contribution drop / Unusual recipient.</li>
        <li><strong>Enabled toggle:</strong> Disable a webhook without deleting it (subscriptions persist).</li>
    </ul>',
    'webhooks_health_title' => 'Delivery Health',
    'webhooks_health_desc' => 'Each webhook tracks success and failure counts plus the last error. The webhook list shows a success-rate percentage colour-coded (&geq; 90% green, &geq; 70% yellow, below red, "Not tested" when no deliveries have fired). Hover the warning icon next to the percentage to see the last delivery error verbatim.',
    'webhooks_routing_title' => 'Notification Routing Map',
    'webhooks_routing_desc' => 'Settings &rarr; Notification Routing shows a read-only map of every notification category, which webhook(s) currently route each one, and the resolved role-mention pill for each (scope: Global or Corp N). The summary at the top counts total categories, delivering categories, and silent categories (any with no enabled subscriber). Used as a sanity check that the routing matches operator intent without opening each webhook\'s edit form.',
    'webhooks_event_bus_title' => 'Event Bus Publishing (Manager Core required)',
    'webhooks_event_bus_desc' => 'When Manager Core is installed, every alert is ALSO published to MC\'s cross-plugin EventBus so other plugins can subscribe. Topics: <code>wallet.transaction_detected</code>, <code>wallet.balance_low</code>, <code>member.contribution.drop_detected</code>, <code>wallet.unusual_recipient_detected</code>. Plus three milestone events from the contribution tracker: <code>member.contribution.stalled</code>, <code>member.contribution.milestone</code> (ladder: 1B / 5B / 10B / 25B / 50B / 100B), <code>member.tax.compliance_dropped</code>. All MC publishing is <code>class_exists</code>-guarded so CWM standalone is a complete no-op.',

    // ============================================================
    // Diagnostic Page (v3.0.0)
    // ============================================================
    'diagnostic_title' => 'Diagnostic Page (v3.0.0)',
    'diagnostic_intro' => 'A new admin-only diagnostic surface at <code>/corp-wallet-manager/diagnostic</code> (gated by <code>corpwalletmanager.settings</code>, intentionally NOT in the sidebar per the suite\'s diagnostic-standard convention). A summary banner at the top shows OK / Warnings / Errors counts with Reload + Force refresh buttons (Force refresh busts the cached check results). Heavy check results cache for 30-60s so re-opening a tab is fast.',
    'diagnostic_tabs_title' => 'Tabs',
    'diagnostic_tabs_list' => '<ul>
        <li><strong>Health Checks:</strong> Tier-1 universal - schedule health, queue depth, ESI rate-limit signal, recent job error counts, recent webhook delivery errors. The default landing tab.</li>
        <li><strong>Master Test:</strong> Tier-1 universal - runs every other tab in sequence and rolls up a single pass / fail.</li>
        <li><strong>System Validation:</strong> Tier-1 universal - companion plugin detection (Manager Core / Mining Manager / HR Manager / Buyback Manager), PluginBridge capability registration, PHP / Composer version sanity checks.</li>
        <li><strong>Settings Health:</strong> Tier-1 universal - confirms required settings have values, alert thresholds default to 0 are flagged as disabled.</li>
        <li><strong>Data Integrity:</strong> Tier-1 universal - table row counts, duplicate detection, orphaned predictions, missing corp references.</li>
        <li><strong>Wallet Trace:</strong> CWM-specific (Tier 3) - paste a journal id and the trace walks the row through every step: corp / division resolve, ref_type classify, party / context resolve via the entity-name fallback chain, alert decision (would-trigger / would-not), filter decision (inter-division transfer flagged with callout).</li>
        <li><strong>Donation Audit</strong> (v3.0.0): Batch view of every <code>player_donation</code> row in a corp + month with the classifier\'s bucket decision and the MM tax-code linkage shown side-by-side. The MM Linked column shows whether <code>mining_taxes.transaction_id</code> matched. Rows that went to <code>donation_voluntary</code> while their description hints at tax are flagged "suspect" for operator review. Click a journal id to jump to that row in Wallet Trace.</li>
        <li><strong>Notification Testing</strong> (v3.0.0): Tier-2 conditional - fire test webhook deliveries on demand without waiting for a real trigger. Category dropdown + corporation dropdown. Per-webhook delivery outcomes shown with success / failure status, Discord HTTP detail, and the resolved role pill. Uses the same <code>WebhookService</code> code path as real notifications.</li>
    </ul>',
    'diagnostic_tab_intro_note' => 'Every tab opens with a mandatory intro box explaining purpose, when to use it, and any heads-up. The default landing tab is always Health Checks (no localStorage restore).',

    // ============================================================
    // Plugin Integrations (v3.0.0)
    // ============================================================
    'integrations_title' => 'Plugin Integrations (v3.0.0)',
    'integrations_intro' => 'CWM works fully standalone, but slots into the wider SeAT plugin suite when companion plugins are installed. Every integration is <code>class_exists</code>-guarded so CWM degrades gracefully if a companion is missing or disabled.',
    'integrations_mc_title' => 'Manager Core',
    'integrations_mc_desc' => 'When Manager Core is installed, two things happen. First, CWM publishes seven topics to MC\'s cross-plugin EventBus: <code>wallet.transaction_detected</code>, <code>wallet.balance_low</code>, <code>member.contribution.drop_detected</code>, <code>wallet.unusual_recipient_detected</code> (alert topics), plus <code>member.contribution.stalled</code>, <code>member.contribution.milestone</code>, <code>member.tax.compliance_dropped</code> (milestone topics). Second, 14 PluginBridge capabilities are exposed for other plugins to call.',
    'integrations_mc_capabilities_title' => '14 PluginBridge Capabilities',
    'integrations_mc_capabilities_list' => '<ul>
        <li><strong>Ratting (3):</strong> <code>ratting.getCharacterIncome</code> / <code>ratting.getCharacterMonthly</code> / <code>ratting.getCharacterBreakdown</code>.</li>
        <li><strong>Contribution analytics (4):</strong> <code>contribution.getCharacterSummary</code> / <code>contribution.getCharacterByCategory</code> / <code>contribution.getCharacterEntries</code> / <code>wallet.getCorpOutflows</code>.</li>
        <li><strong>Advanced analytics (5, v3.0.0):</strong> <code>contribution.getCharacterTrend</code> (slope + velocity + last-3-months vs prior-3-months %-change) / <code>contribution.getActivityGaps</code> (gap count + longest gap + last active period) / <code>contribution.getNetPosition</code> (contributed minus withdrawn + withdrawal-to-contribution ratio) / <code>contribution.getLifetimeSummary</code> (all-time totals + months_active + first/last contribution period) / <code>contribution.getCharacterPercentile</code> (corp median / p25 / p75 + character percentile rank).</li>
        <li><strong>MM tax compliance (1, v3.0.0):</strong> <code>contribution.getCharacterTaxCompliance</code> returns per-period MM owed / paid / compliance% + consecutive_overdue count. Returns null when MM is absent so callers can detect "tax signal unavailable" cleanly.</li>
        <li><strong>Director attribution (1, v3.0.0):</strong> <code>wallet.getDirectorAttribution</code> best-effort attributes <code>corp_account_withdrawal</code> rows to the acting director using <code>context_id</code> when CCP populates it plus a logon-proximity heuristic against <code>corporation_member_trackings</code>; returns per-director count + total + signal_split with an unattributable bucket holding up to 20 sample rows.</li>
    </ul>',
    'integrations_mm_title' => 'Mining Manager',
    'integrations_mm_desc' => 'When Mining Manager is installed, the per-character contribution tracker splits tax payments from voluntary donations using <code>mining_taxes.transaction_id</code> first (authoritative - MM matches journal ids to invoices in its own wallet-listener pipeline) and the description tax-code as fallback. Top Contributors\' Tax Payment column then shows <code>paid / owed</code> sourced from <code>mining_taxes</code> with a compliance percentage tooltip. Values below 80% render in warning yellow. The aggregation respects main-character grouping (a main\'s compliance is summed across all alts; each alt row shows its own). No Mining Manager changes required.',
    'integrations_hr_title' => 'HR Manager',
    'integrations_hr_desc' => 'HR Manager consumes the 14 PluginBridge capabilities to assemble per-member assessments and subscribes to the 3 milestone events for the member profile timeline. HR composes whatever combination of CWM + MM is installed: with both, HR sees full income / contribution / tax signals; with CWM only, HR\'s "tax signal" branch reports unavailable; with MM only, HR loses the contribution analytics but keeps the MM income signal. State for milestone events is tracked in <code>corpwalletmanager_member_milestone_state</code> so each transition publishes exactly once across the whole event lifetime.',
    'integrations_standalone_title' => 'Standalone Mode',
    'integrations_standalone_desc' => 'Without any companion plugins, CWM still delivers everything except the integration-specific features: Top Contributors works but the Tax Payment column hides, the milestone events still track state locally (auto-fire the moment MC is installed), Discord webhooks deliver normally, the Diagnostic page shows System Validation reporting MC / MM / HR as "Not detected". No composer dependency exists between CWM and any companion plugin; the runtime detection is <code>class_exists</code>-guarded.',

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
