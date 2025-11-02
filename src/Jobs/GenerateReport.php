<?php
namespace Seat\CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Seat\CorpWalletManager\Models\Settings;

class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 1;

    protected $corporationId;
    protected $reportType;
    protected $dateFrom;
    protected $dateTo;
    protected $sections;
    protected $sendToDiscord;

    public function __construct($corporationId, $reportType, $dateFrom, $dateTo, $sections = [], $sendToDiscord = false)
    {
        $this->corporationId = $corporationId;
        $this->reportType = $reportType;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->sections = $sections;
        $this->sendToDiscord = $sendToDiscord;
    }

    public function handle()
    {
        try {
            Log::info('GenerateReport started', [
                'corporation_id' => $this->corporationId,
                'type' => $this->reportType,
                'from' => $this->dateFrom,
                'to' => $this->dateTo
            ]);

            // Get corporation name
            $corpInfo = DB::table('corporation_infos')
                ->where('corporation_id', $this->corporationId)
                ->first();
            
            $corpName = $corpInfo ? $corpInfo->name : "Corporation {$this->corporationId}";

            // Generate report data based on type
            $reportData = $this->generateReportData();
            
            // Save report to database
            $reportId = DB::table('corpwalletmanager_reports')->insertGetId([
                'corporation_id' => $this->corporationId,
                'report_type' => $this->reportType,
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
                'data' => json_encode($reportData),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Send to Discord if enabled
            if ($this->sendToDiscord && Settings::getBooleanSetting('discord_webhook_enabled', false)) {
                $this->sendToDiscordWebhook($corpName, $reportData);
            }

            Log::info('GenerateReport completed', [
                'report_id' => $reportId,
                'corporation_id' => $this->corporationId
            ]);

        } catch (\Exception $e) {
            Log::error('GenerateReport failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function generateReportData()
    {
        $data = [
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'from' => $this->dateFrom->toDateString(),
                'to' => $this->dateTo->toDateString(),
                'days' => $this->dateFrom->diffInDays($this->dateTo) + 1
            ]
        ];

        // Balance History
        if ($this->shouldIncludeSection('balance_history')) {
            $data['balance_history'] = $this->getBalanceHistory();
        }

        // Income/Expense Analysis
        if ($this->shouldIncludeSection('income_analysis')) {
            $data['income_analysis'] = $this->getIncomeAnalysis();
        }

        if ($this->shouldIncludeSection('expense_analysis')) {
            $data['expense_analysis'] = $this->getExpenseAnalysis();
        }

        // Transaction Breakdown
        if ($this->shouldIncludeSection('transaction_breakdown')) {
            $data['transaction_breakdown'] = $this->getTransactionBreakdown();
        }

        // Division Performance
        if ($this->shouldIncludeSection('division_summary')) {
            $data['division_summary'] = $this->getDivisionSummary();
        }

        // Risk Assessment
        if ($this->shouldIncludeSection('risk_assessment')) {
            $data['risk_assessment'] = $this->getRiskAssessment();
        }

        return $data;
    }

    protected function shouldIncludeSection($section)
    {
        // If no sections specified, include all for the report type
        if (empty($this->sections)) {
            return true;
        }
        return in_array($section, $this->sections);
    }

    protected function getBalanceHistory()
    {
        $balances = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->selectRaw('DATE(date) as date, SUM(amount) as daily_change')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $startBalance = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->where('date', '<', $this->dateFrom)
            ->sum('amount');

        $endBalance = $startBalance + $balances->sum('daily_change');

        return [
            'start_balance' => $startBalance,
            'end_balance' => $endBalance,
            'change' => $endBalance - $startBalance,
            'change_percent' => $startBalance != 0 ? (($endBalance - $startBalance) / abs($startBalance)) * 100 : 0,
            'daily_data' => $balances
        ];
    }

    protected function getIncomeAnalysis()
    {
        $income = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->where('amount', '>', 0)
            ->selectRaw('
                COUNT(*) as transaction_count,
                SUM(amount) as total_income,
                AVG(amount) as avg_income,
                MAX(amount) as max_income,
                MIN(amount) as min_income
            ')
            ->first();

        return [
            'total' => $income->total_income ?? 0,
            'transactions' => $income->transaction_count ?? 0,
            'average' => $income->avg_income ?? 0,
            'highest' => $income->max_income ?? 0,
            'lowest' => $income->min_income ?? 0
        ];
    }

    protected function getExpenseAnalysis()
    {
        $expenses = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->where('amount', '<', 0)
            ->selectRaw('
                COUNT(*) as transaction_count,
                SUM(ABS(amount)) as total_expenses,
                AVG(ABS(amount)) as avg_expense,
                MAX(ABS(amount)) as max_expense,
                MIN(ABS(amount)) as min_expense
            ')
            ->first();

        return [
            'total' => $expenses->total_expenses ?? 0,
            'transactions' => $expenses->transaction_count ?? 0,
            'average' => $expenses->avg_expense ?? 0,
            'highest' => $expenses->max_expense ?? 0,
            'lowest' => $expenses->min_expense ?? 0
        ];
    }

    protected function getTransactionBreakdown()
    {
        $breakdown = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->selectRaw('
                ref_type,
                COUNT(*) as count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            ')
            ->groupBy('ref_type')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        return $breakdown;
    }

    protected function getDivisionSummary()
    {
        $divisions = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->selectRaw('
                division,
                COUNT(*) as transactions,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses,
                SUM(amount) as net_change
            ')
            ->groupBy('division')
            ->get();

        return $divisions;
    }

    protected function getRiskAssessment()
    {
        $currentBalance = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->sum('amount');

        $avgDailyExpenses = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->where('amount', '<', 0)
            ->selectRaw('AVG(ABS(amount)) as avg_expense')
            ->value('avg_expense') ?? 0;

        $daysOfRunway = $avgDailyExpenses > 0 ? $currentBalance / $avgDailyExpenses : 0;

        $volatility = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->selectRaw('STDDEV(amount) as volatility')
            ->value('volatility') ?? 0;

        return [
            'current_balance' => $currentBalance,
            'days_of_runway' => round($daysOfRunway, 1),
            'volatility' => $volatility,
            'risk_level' => $this->calculateRiskLevel($daysOfRunway, $volatility)
        ];
    }

    protected function calculateRiskLevel($daysOfRunway, $volatility)
    {
        if ($daysOfRunway < 30) return 'HIGH';
        if ($daysOfRunway < 90) return 'MEDIUM';
        if ($daysOfRunway < 180) return 'LOW';
        return 'VERY_LOW';
    }

    protected function sendToDiscordWebhook($corpName, $reportData)
    {
        $webhookUrl = Settings::getSetting('discord_webhook_url');
        
        if (!$webhookUrl) {
            Log::warning('Discord webhook URL not configured');
            return;
        }

        // Create embed based on report type
        $embed = $this->createDiscordEmbed($corpName, $reportData);

        $payload = [
            'embeds' => [$embed]
        ];

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            Log::error('Discord webhook failed', [
                'http_code' => $httpCode,
                'response' => $response
            ]);
        } else {
            Log::info('Report sent to Discord successfully');
        }
    }

    protected function createDiscordEmbed($corpName, $reportData)
    {
        $color = $this->getEmbedColor($reportData);
        
        $embed = [
            'title' => "📊 {$this->getReportTitle()}",
            'description' => "Financial report for **{$corpName}**",
            'color' => $color,
            'timestamp' => now()->toIso8601String(),
            'fields' => []
        ];

        // Period
        $embed['fields'][] = [
            'name' => '📅 Period',
            'value' => "{$reportData['period']['from']} to {$reportData['period']['to']} ({$reportData['period']['days']} days)",
            'inline' => false
        ];

        // Balance History
        if (isset($reportData['balance_history'])) {
            $bh = $reportData['balance_history'];
            $changeIcon = $bh['change'] >= 0 ? '📈' : '📉';
            $embed['fields'][] = [
                'name' => "{$changeIcon} Balance Change",
                'value' => sprintf(
                    "Start: %s ISK\nEnd: %s ISK\nChange: %s ISK (%+.2f%%)",
                    number_format($bh['start_balance'], 0),
                    number_format($bh['end_balance'], 0),
                    number_format($bh['change'], 0),
                    $bh['change_percent']
                ),
                'inline' => false
            ];
        }

        // Income & Expenses
        if (isset($reportData['income_analysis']) && isset($reportData['expense_analysis'])) {
            $income = $reportData['income_analysis'];
            $expenses = $reportData['expense_analysis'];
            
            $embed['fields'][] = [
                'name' => '💰 Income',
                'value' => sprintf(
                    "Total: %s ISK\nTransactions: %s",
                    number_format($income['total'], 0),
                    number_format($income['transactions'], 0)
                ),
                'inline' => true
            ];
            
            $embed['fields'][] = [
                'name' => '💸 Expenses',
                'value' => sprintf(
                    "Total: %s ISK\nTransactions: %s",
                    number_format($expenses['total'], 0),
                    number_format($expenses['transactions'], 0)
                ),
                'inline' => true
            ];
        }

        // Risk Assessment
        if (isset($reportData['risk_assessment'])) {
            $risk = $reportData['risk_assessment'];
            $riskIcons = [
                'HIGH' => '🔴',
                'MEDIUM' => '🟡',
                'LOW' => '🟢',
                'VERY_LOW' => '🔵'
            ];
            $riskIcon = $riskIcons[$risk['risk_level']] ?? '⚪';
            
            $embed['fields'][] = [
                'name' => "{$riskIcon} Risk Assessment",
                'value' => sprintf(
                    "Risk Level: **%s**\nDays of Runway: **%.1f days**\nCurrent Balance: %s ISK",
                    $risk['risk_level'],
                    $risk['days_of_runway'],
                    number_format($risk['current_balance'], 0)
                ),
                'inline' => false
            ];
        }

        $embed['footer'] = [
            'text' => 'CorpWallet Manager'
        ];

        return $embed;
    }

    protected function getReportTitle()
    {
        $titles = [
            'executive' => 'Executive Summary Report',
            'financial' => 'Financial Analysis Report',
            'division' => 'Division Performance Report',
            'custom' => 'Custom Report'
        ];

        return $titles[$this->reportType] ?? 'Financial Report';
    }

    protected function getEmbedColor($reportData)
    {
        // Determine color based on financial health
        if (isset($reportData['risk_assessment'])) {
            $risk = $reportData['risk_assessment']['risk_level'];
            $colors = [
                'HIGH' => 15158332, // Red
                'MEDIUM' => 16776960, // Yellow
                'LOW' => 3066993, // Green
                'VERY_LOW' => 3447003 // Blue
            ];
            return $colors[$risk] ?? 3447003;
        }

        // Default to blue
        return 3447003;
    }
}
