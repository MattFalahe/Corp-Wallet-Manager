<?php

namespace Seat\CorpWalletManager\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Seat\CorpWalletManager\Models\Settings;
use Carbon\Carbon;

class DiscordService
{
    protected $webhookUrl;
    protected $enabled;
    
    public function __construct()
    {
        $this->webhookUrl = Settings::getSetting('discord_webhook_url');
        $this->enabled = Settings::getBooleanSetting('discord_enabled', false);
    }
    
    /**
     * Send a message to Discord
     */
    public function send($content, $embeds = [], $mentionRole = null)
    {
        if (!$this->enabled || !$this->webhookUrl) {
            Log::info('Discord webhook not configured or disabled');
            return false;
        }
        
        $payload = [];
        
        // Add mention if specified
        if ($mentionRole) {
            $mentionRole = Settings::getSetting('discord_mention_role', $mentionRole);
            if ($mentionRole === '@everyone' || $mentionRole === '@here') {
                $payload['content'] = $mentionRole . ' ' . ($content ?? '');
            } elseif ($mentionRole) {
                $payload['content'] = "<@&{$mentionRole}> " . ($content ?? '');
            }
        } elseif ($content) {
            $payload['content'] = $content;
        }
        
        // Add embeds
        if (!empty($embeds)) {
            $payload['embeds'] = $embeds;
        }
        
        // Add username and avatar
        $payload['username'] = 'Corp Wallet Manager';
        $payload['avatar_url'] = 'https://eve-seat.github.io/docs/img/seat.png';
        
        try {
            $response = Http::timeout(10)->post($this->webhookUrl, $payload);
            
            if ($response->successful()) {
                Log::info('Discord webhook sent successfully');
                return true;
            } else {
                Log::error('Discord webhook failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Discord webhook exception', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Format ISK amount for display
     */
    private function formatISK($amount)
    {
        $billion = 1000000000;
        $million = 1000000;
        
        if (abs($amount) >= $billion) {
            return number_format($amount / $billion, 2) . 'B';
        } elseif (abs($amount) >= $million) {
            return number_format($amount / $million, 2) . 'M';
        } else {
            return number_format($amount, 0);
        }
    }
    
    /**
     * Send Monthly Report to Discord
     */
    public function sendMonthlyReport($reportData)
    {
        if (!Settings::getBooleanSetting('discord_monthly_reports', true)) {
            return false;
        }
        
        $changeColor = $reportData['change'] >= 0 ? 0x00ff00 : 0xff0000;
        $changeEmoji = $reportData['change'] >= 0 ? '📈' : '📉';
        
        $embed = [
            'title' => "📊 Monthly Financial Report - {$reportData['period']}",
            'color' => $changeColor,
            'timestamp' => now()->toIso8601String(),
            'fields' => [
                [
                    'name' => '💰 Ending Balance',
                    'value' => '**' . $this->formatISK($reportData['ending_balance']) . ' ISK**',
                    'inline' => true
                ],
                [
                    'name' => $changeEmoji . ' Monthly Change',
                    'value' => ($reportData['change'] >= 0 ? '+' : '') . 
                              $this->formatISK($reportData['change']) . ' ISK' . "\n" .
                              '(' . ($reportData['change'] >= 0 ? '+' : '') . 
                              number_format($reportData['change_percent'], 1) . '%)',
                    'inline' => true
                ],
                [
                    'name' => '📊 Transactions',
                    'value' => number_format($reportData['statistics']->total_transactions),
                    'inline' => true
                ],
                [
                    'name' => '💵 Total Income',
                    'value' => $this->formatISK($reportData['statistics']->total_income) . ' ISK',
                    'inline' => true
                ],
                [
                    'name' => '💸 Total Expenses',
                    'value' => $this->formatISK($reportData['statistics']->total_expenses) . ' ISK',
                    'inline' => true
                ],
                [
                    'name' => '💎 Net Profit/Loss',
                    'value' => ($reportData['change'] >= 0 ? '**+' : '**') . 
                              $this->formatISK($reportData['change']) . ' ISK**',
                    'inline' => true
                ]
            ]
        ];
        
        // Add health score with emoji
        if (isset($reportData['health_metrics'])) {
            $healthScore = $reportData['health_metrics']['overall_health'];
            $healthEmoji = $healthScore >= 80 ? '🟢' : ($healthScore >= 50 ? '🟡' : '🔴');
            
            $embed['fields'][] = [
                'name' => '🏥 Health Score',
                'value' => $healthEmoji . ' ' . number_format($healthScore, 1) . '/100',
                'inline' => true
            ];
        }
        
        // Add top income sources if available
        if (!empty($reportData['top_income_sources'])) {
            $incomeList = [];
            foreach ($reportData['top_income_sources']->take(5) as $source) {
                $incomeList[] = "• **{$source->ref_type}**: " . 
                               $this->formatISK($source->total) . ' ISK';
            }
            $embed['fields'][] = [
                'name' => '📥 Top Income Sources',
                'value' => implode("\n", $incomeList) ?: 'No income this period',
                'inline' => false
            ];
        }
        
        // Add top expense sources if available
        if (!empty($reportData['top_expense_sources'])) {
            $expenseList = [];
            foreach ($reportData['top_expense_sources']->take(5) as $source) {
                $expenseList[] = "• **{$source->ref_type}**: " . 
                                $this->formatISK($source->total) . ' ISK';
            }
            $embed['fields'][] = [
                'name' => '📤 Top Expense Categories',
                'value' => implode("\n", $expenseList) ?: 'No expenses this period',
                'inline' => false
            ];
        }
        
        // Add footer
        $embed['footer'] = [
            'text' => 'Corp Wallet Manager • ' . config('app.name', 'SeAT'),
            'icon_url' => 'https://eve-seat.github.io/docs/img/seat.png'
        ];
        
        // Check for alerts
        $alertThreshold = Settings::getSetting('discord_alert_threshold', 1000000000);
        $mention = null;
        $content = null;
        
        if ($reportData['ending_balance'] < $alertThreshold) {
            $mention = Settings::getSetting('discord_mention_role');
            $content = "⚠️ **ALERT**: Corporation balance is below the threshold of " . 
                      $this->formatISK($alertThreshold) . " ISK!";
        } elseif ($reportData['change_percent'] < -20) {
            $content = "⚠️ **WARNING**: Significant monthly loss detected (" . 
                      number_format($reportData['change_percent'], 1) . "%)";
        }
        
        return $this->send($content, [$embed], $mention);
    }
    
    /**
     * Send Quarterly Report to Discord
     */
    public function sendQuarterlyReport($reportData)
    {
        if (!Settings::getBooleanSetting('discord_quarterly_reports', false)) {
            return false;
        }
        
        $trendEmoji = [
            'improving' => '📈',
            'declining' => '📉',
            'stable' => '➡️',
            'insufficient_data' => '❓'
        ];
        
        $netChangeColor = $reportData['net_change'] >= 0 ? 0x00ff00 : 0xff0000;
        
        $embed = [
            'title' => "📊 Quarterly Financial Report - {$reportData['period']}",
            'color' => $netChangeColor,
            'timestamp' => now()->toIso8601String(),
            'fields' => [
                [
                    'name' => '💵 Total Income',
                    'value' => '**' . $this->formatISK($reportData['total_income']) . ' ISK**',
                    'inline' => true
                ],
                [
                    'name' => '💸 Total Expenses',
                    'value' => '**' . $this->formatISK($reportData['total_expenses']) . ' ISK**',
                    'inline' => true
                ],
                [
                    'name' => '📊 Net Change',
                    'value' => ($reportData['net_change'] >= 0 ? '**+' : '**') . 
                              $this->formatISK($reportData['net_change']) . ' ISK**',
                    'inline' => true
                ],
                [
                    'name' => 'Trend Analysis',
                    'value' => $trendEmoji[$reportData['trend']] . ' **' . 
                              ucfirst($reportData['trend']) . '**',
                    'inline' => true
                ]
            ]
        ];
        
        // Add monthly breakdown
        if (!empty($reportData['months'])) {
            $monthlyBreakdown = [];
            foreach ($reportData['months'] as $month) {
                $emoji = $month['change'] >= 0 ? '✅' : '❌';
                $monthlyBreakdown[] = sprintf(
                    "%s **%s**: %s ISK (%+.1f%%)",
                    $emoji,
                    $month['period'],
                    $this->formatISK($month['ending_balance']),
                    $month['change_percent']
                );
            }
            
            $embed['fields'][] = [
                'name' => '📅 Monthly Breakdown',
                'value' => implode("\n", $monthlyBreakdown),
                'inline' => false
            ];
        }
        
        // Add performance summary
        $avgMonthlyIncome = $reportData['total_income'] / 3;
        $avgMonthlyExpense = $reportData['total_expenses'] / 3;
        
        $embed['fields'][] = [
            'name' => '📊 Quarterly Averages',
            'value' => "**Avg Monthly Income**: " . $this->formatISK($avgMonthlyIncome) . " ISK\n" .
                      "**Avg Monthly Expenses**: " . $this->formatISK($avgMonthlyExpense) . " ISK",
            'inline' => false
        ];
        
        $embed['footer'] = [
            'text' => 'Corp Wallet Manager • Quarterly Report',
            'icon_url' => 'https://eve-seat.github.io/docs/img/seat.png'
        ];
        
        return $this->send(null, [$embed]);
    }
    
    /**
     * Send Daily Summary to Discord
     */
    public function sendDailySummary($summaryData)
    {
        if (!Settings::getBooleanSetting('discord_daily_summary', false)) {
            return false;
        }
        
        $netFlow = $summaryData['summary']->net_flow ?? 0;
        $flowColor = $netFlow >= 0 ? 0x3498db : 0xe74c3c;
        $flowEmoji = $netFlow >= 0 ? '📈' : '📉';
        
        $embed = [
            'title' => "📅 Daily Summary - " . Carbon::parse($summaryData['date'])->format('l, F j, Y'),
            'color' => $flowColor,
            'fields' => [
                [
                    'name' => '💰 Current Balance',
                    'value' => '**' . $this->formatISK($summaryData['current_balance']) . ' ISK**',
                    'inline' => true
                ],
                [
                    'name' => $flowEmoji . ' Net Flow',
                    'value' => ($netFlow >= 0 ? '+' : '') . $this->formatISK($netFlow) . ' ISK',
                    'inline' => true
                ],
                [
                    'name' => '📊 Transactions',
                    'value' => $summaryData['summary']->transactions ?? 0,
                    'inline' => true
                ],
                [
                    'name' => '💵 Income',
                    'value' => $this->formatISK($summaryData['summary']->income) . ' ISK',
                    'inline' => true
                ],
                [
                    'name' => '💸 Expenses',
                    'value' => $this->formatISK($summaryData['summary']->expenses) . ' ISK',
                    'inline' => true
                ],
                [
                    'name' => '📈 Daily Change',
                    'value' => ($netFlow >= 0 ? '+' : '') . 
                              number_format(($netFlow / max($summaryData['current_balance'], 1)) * 100, 2) . '%',
                    'inline' => true
                ]
            ]
        ];
        
        // Add 7-day forecast if available
        if (!empty($summaryData['week_forecast']) && count($summaryData['week_forecast']) > 0) {
            $forecast = [];
            foreach ($summaryData['week_forecast']->take(7) as $prediction) {
                $date = Carbon::parse($prediction->date);
                $emoji = $prediction->predicted_balance > $summaryData['current_balance'] ? '📈' : '📉';
                $forecast[] = sprintf(
                    "%s **%s**: %s ISK",
                    $emoji,
                    $date->format('M j'),
                    $this->formatISK($prediction->predicted_balance)
                );
            }
            $embed['fields'][] = [
                'name' => '🔮 7-Day Forecast',
                'value' => implode("\n", array_slice($forecast, 0, 7)),
                'inline' => false
            ];
        }
        
        $embed['footer'] = [
            'text' => 'Daily summary generated at ' . now()->format('H:i:s T'),
            'icon_url' => 'https://eve-seat.github.io/docs/img/seat.png'
        ];
        
        // Alert if balance is critically low
        $alertThreshold = Settings::getSetting('discord_alert_threshold', 1000000000);
        $content = null;
        
        if ($summaryData['current_balance'] < $alertThreshold) {
            $content = "⚠️ **Daily Alert**: Balance below threshold!";
        }
        
        return $this->send($content, [$embed]);
    }
    
    /**
     * Send Test Message
     */
    public function sendTest()
    {
        $embed = [
            'title' => '✅ Discord Integration Test',
            'description' => 'Your Discord webhook is configured correctly and ready to receive reports!',
            'color' => 0x00ff00,
            'fields' => [
                [
                    'name' => '🟢 Status',
                    'value' => 'Connection successful',
                    'inline' => true
                ],
                [
                    'name' => '⏰ Time',
                    'value' => now()->format('Y-m-d H:i:s'),
                    'inline' => true
                ],
                [
                    'name' => '🔧 Configuration',
                    'value' => "Monthly Reports: " . (Settings::getBooleanSetting('discord_monthly_reports') ? '✅' : '❌') . "\n" .
                              "Quarterly Reports: " . (Settings::getBooleanSetting('discord_quarterly_reports') ? '✅' : '❌') . "\n" .
                              "Daily Summary: " . (Settings::getBooleanSetting('discord_daily_summary') ? '✅' : '❌'),
                    'inline' => false
                ]
            ],
            'footer' => [
                'text' => 'Corp Wallet Manager Test Message',
                'icon_url' => 'https://eve-seat.github.io/docs/img/seat.png'
            ]
        ];
        
        return $this->send('🎉 Test message from Corp Wallet Manager!', [$embed]);
    }
    
    /**
     * Send Custom Alert
     */
    public function sendAlert($title, $message, $type = 'info', $fields = [])
    {
        $colors = [
            'info' => 0x3498db,
            'success' => 0x00ff00,
            'warning' => 0xffa500,
            'danger' => 0xff0000
        ];
        
        $emojis = [
            'info' => 'ℹ️',
            'success' => '✅',
            'warning' => '⚠️',
            'danger' => '🚨'
        ];
        
        $embed = [
            'title' => $emojis[$type] . ' ' . $title,
            'description' => $message,
            'color' => $colors[$type] ?? $colors['info'],
            'fields' => $fields,
            'timestamp' => now()->toIso8601String(),
            'footer' => [
                'text' => 'Corp Wallet Manager Alert',
                'icon_url' => 'https://eve-seat.github.io/docs/img/seat.png'
            ]
        ];
        
        $mention = null;
        if ($type === 'danger' || $type === 'warning') {
            $mention = Settings::getSetting('discord_mention_role');
        }
        
        return $this->send(null, [$embed], $mention);
    }
}
