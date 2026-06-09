<?php

namespace CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Seat\Services\Models\ExtensibleModel;

/**
 * A Discord webhook that receives Corp Wallet Manager reports.
 *
 * Replaces the single global discord_webhook_url setting from 2.x with
 * per-corporation webhook routing: a webhook scoped to a corporation also
 * receives every global (corporation_id = NULL) webhook's traffic, while a
 * global webhook receives reports for every corporation.
 */
class Webhook extends ExtensibleModel
{
    /**
     * @var string
     */
    protected $table = 'corpwalletmanager_webhooks';

    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'webhook_url',
        'corporation_id',
        'is_enabled',
        'discord_role_id',
        'notify_weekly_report',
        'notify_monthly_report',
        'notify_on_demand_report',
        'notify_large_transfer',
        'notify_low_balance',
        'notify_contribution_drop',
        'notify_unusual_recipient',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'corporation_id'           => 'integer',
        'is_enabled'               => 'boolean',
        'notify_weekly_report'     => 'boolean',
        'notify_monthly_report'    => 'boolean',
        'notify_on_demand_report'  => 'boolean',
        'notify_large_transfer'    => 'boolean',
        'notify_low_balance'       => 'boolean',
        'notify_contribution_drop' => 'boolean',
        'notify_unusual_recipient' => 'boolean',
        'success_count'            => 'integer',
        'failure_count'            => 'integer',
        'last_success_at'          => 'datetime',
        'last_failure_at'          => 'datetime',
    ];

    /**
     * Keep the secret webhook URL out of array/JSON serialization so it is
     * never leaked into a Blade @json() call or an API response.
     *
     * @var array
     */
    protected $hidden = [
        'webhook_url',
    ];

    /**
     * Map a GenerateReport report type onto one of the three webhook
     * subscription categories. Scheduled reports dispatch with the period
     * ('weekly' / 'monthly'); on-demand reports dispatch with a template
     * name ('executive' / 'financial' / 'division' / 'custom').
     *
     * @param  string  $reportType
     * @return string  'weekly' | 'monthly' | 'on_demand'
     */
    public static function reportCategoryFor(string $reportType): string
    {
        return match (strtolower($reportType)) {
            'weekly'  => 'weekly',
            'monthly' => 'monthly',
            default   => 'on_demand',
        };
    }

    /**
     * Only enabled webhooks.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Webhooks that should receive a notification for a given corporation.
     * A corp-scoped report reaches that corp's own webhooks plus every
     * global webhook. With no corporation it reaches only global webhooks.
     */
    public function scopeForCorporation(Builder $query, ?int $corporationId): Builder
    {
        if ($corporationId) {
            return $query->where(function (Builder $q) use ($corporationId) {
                $q->where('corporation_id', $corporationId)
                  ->orWhereNull('corporation_id');
            });
        }

        return $query->whereNull('corporation_id');
    }

    /**
     * Webhooks subscribed to a given report category.
     *
     * @param  string  $category  'weekly' | 'monthly' | 'on_demand'
     */
    public function scopeForReport(Builder $query, string $category): Builder
    {
        $column = match ($category) {
            'weekly'  => 'notify_weekly_report',
            'monthly' => 'notify_monthly_report',
            default   => 'notify_on_demand_report',
        };

        return $query->where($column, true);
    }

    /**
     * Webhooks subscribed to a given alert type.
     *
     * @param  string  $alertType  'large_transfer' | 'low_balance'
     *                             | 'contribution_drop' | 'unusual_recipient'
     */
    public function scopeForAlert(Builder $query, string $alertType): Builder
    {
        $column = match ($alertType) {
            'large_transfer'    => 'notify_large_transfer',
            'low_balance'       => 'notify_low_balance',
            'contribution_drop' => 'notify_contribution_drop',
            'unusual_recipient' => 'notify_unusual_recipient',
            default             => 'notify_large_transfer',
        };

        return $query->where($column, true);
    }

    /**
     * Discord-ready role mention string, or null when no role is set.
     * Accepts either a bare snowflake or an already-formatted <@&id>.
     */
    public function getDiscordRoleMention(): ?string
    {
        $snowflake = $this->getDiscordRoleSnowflake();

        return $snowflake !== null ? '<@&' . $snowflake . '>' : null;
    }

    /**
     * The numeric role snowflake on its own (used for allowed_mentions).
     */
    public function getDiscordRoleSnowflake(): ?string
    {
        $raw = trim((string) $this->discord_role_id);

        if ($raw !== '' && preg_match('/(\d{2,})/', $raw, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Delivery success rate as a percentage. 100.0 when nothing has been
     * attempted yet.
     */
    public function getHealthPercentageAttribute(): float
    {
        $total = (int) $this->success_count + (int) $this->failure_count;

        if ($total === 0) {
            return 100.0;
        }

        return round(($this->success_count / $total) * 100, 1);
    }

    /**
     * Record a successful delivery in a single round-trip, then mirror the
     * new values onto the in-memory model.
     */
    public function recordSuccess(): void
    {
        $now = now();

        self::where('id', $this->id)->update([
            'success_count'   => DB::raw('success_count + 1'),
            'last_success_at' => $now,
            'last_error'      => null,
        ]);

        $this->success_count = (int) $this->success_count + 1;
        $this->last_success_at = $now;
        $this->last_error = null;
    }

    /**
     * Record a failed delivery.
     */
    public function recordFailure(?string $error = null): void
    {
        $now = now();

        self::where('id', $this->id)->update([
            'failure_count'   => DB::raw('failure_count + 1'),
            'last_failure_at' => $now,
            'last_error'      => $error,
        ]);

        $this->failure_count = (int) $this->failure_count + 1;
        $this->last_failure_at = $now;
        $this->last_error = $error;
    }
}
