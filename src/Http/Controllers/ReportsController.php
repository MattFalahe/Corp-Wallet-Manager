<?php
namespace Seat\CorpWalletManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Seat\CorpWalletManager\Models\Settings;
use Seat\CorpWalletManager\Jobs\GenerateReport;

class ReportsController extends Controller
{
    /**
     * Display the reports view
     */
    public function index()
    {
        // Reports feature is now integrated in Director view
        return redirect()
            ->route('corpwalletmanager.director')
            ->with('info', 'Reports are now in the Director view. Click the Reports tab to access all reporting features.');
    }

    /**
     * Generate a report
     */
    public function generate(Request $request)
    {
        try {
            $validated = $request->validate([
                'report_type' => 'required|in:executive,financial,division,custom',
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
                'sections' => 'array',
                'send_to_discord' => 'boolean'
            ]);

            $corporationId = Settings::getSetting('selected_corporation_id');
            
            if (!$corporationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a corporation in settings first.'
                ], 400);
            }

            // Dispatch the job
            GenerateReport::dispatch(
                $corporationId,
                $validated['report_type'],
                Carbon::parse($validated['date_from']),
                Carbon::parse($validated['date_to']),
                $validated['sections'] ?? [],
                $validated['send_to_discord'] ?? false
            );

            return response()->json([
                'success' => true,
                'message' => 'Report generation started. You will receive a notification when complete.'
            ]);

        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get report history
     */
    public function history()
    {
        try {
            $reports = DB::table('corpwalletmanager_reports')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'reports' => $reports
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch report history', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch report history'
            ], 500);
        }
    }

    /**
     * Test Discord webhook
     */
    public function testWebhook(Request $request)
    {
        try {
            $webhookUrl = Settings::getSetting('discord_webhook_url');
            
            if (!$webhookUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'Discord webhook URL not configured'
                ], 400);
            }

            $this->sendDiscordMessage($webhookUrl, [
                'embeds' => [[
                    'title' => '✅ Webhook Test',
                    'description' => 'Your Discord webhook is configured correctly!',
                    'color' => 3447003, // Blue
                    'timestamp' => now()->toIso8601String(),
                    'footer' => [
                        'text' => 'CorpWallet Manager'
                    ]
                ]]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test message sent to Discord successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('Discord webhook test failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send test message: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a message to Discord webhook
     */
    public function sendDiscordMessage($webhookUrl, $data)
    {
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception('Discord webhook returned HTTP ' . $httpCode);
        }

        return $response;
    }

    /**
     * Get available report templates
     */
    public function templates()
    {
        return response()->json([
            'templates' => [
                [
                    'id' => 'executive',
                    'name' => 'Executive Summary',
                    'description' => 'High-level overview of corporation financial health',
                    'sections' => ['balance_history', 'income_analysis', 'expense_analysis', 'risk_assessment']
                ],
                [
                    'id' => 'financial',
                    'name' => 'Financial Report',
                    'description' => 'Detailed financial analysis with trends',
                    'sections' => ['balance_history', 'income_expense', 'transaction_breakdown', 'predictions']
                ],
                [
                    'id' => 'division',
                    'name' => 'Division Performance',
                    'description' => 'Performance breakdown by division',
                    'sections' => ['division_summary', 'division_comparison', 'division_trends']
                ],
                [
                    'id' => 'custom',
                    'name' => 'Custom Report',
                    'description' => 'Build your own report with selected sections',
                    'sections' => []
                ]
            ]
        ]);
    }
}
