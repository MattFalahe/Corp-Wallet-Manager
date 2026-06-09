<?php

namespace CorpWalletManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Models\Webhook;
use CorpWalletManager\Services\WebhookService;

/**
 * CRUD + test endpoints for Corp Wallet Manager Discord webhooks.
 *
 * Gated by the corpwalletmanager.settings permission (see routes.php).
 * The webhook list itself is rendered server-side on the Settings page;
 * this controller only handles mutations and the AJAX test action.
 */
class WebhookController extends Controller
{
    /**
     * Create or update a webhook. A filled `webhook_id` means update.
     */
    public function save(Request $request)
    {
        $creating = ! $request->filled('webhook_id');

        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'webhook_url'     => array_merge(
                [$creating ? 'required' : 'nullable'],
                ['url', 'max:2048', 'regex:/^https:\/\/(discord\.com|discordapp\.com)\/api\/webhooks\//i']
            ),
            'corporation_id'  => 'nullable|integer|min:1',
            'discord_role_id' => 'nullable|string|max:32',
        ], [
            'webhook_url.regex' => 'The webhook URL must be a Discord webhook (https://discord.com/api/webhooks/...).',
        ]);

        $data = [
            'name'                    => $validated['name'],
            // Empty corporation select means a global webhook — store NULL,
            // never an empty string, so the bigint column stays clean.
            'corporation_id'          => $request->filled('corporation_id') ? (int) $request->input('corporation_id') : null,
            'discord_role_id'         => $request->filled('discord_role_id') ? trim($request->input('discord_role_id')) : null,
            'is_enabled'              => $request->boolean('is_enabled'),
            'notify_weekly_report'     => $request->boolean('notify_weekly_report'),
            'notify_monthly_report'    => $request->boolean('notify_monthly_report'),
            'notify_on_demand_report'  => $request->boolean('notify_on_demand_report'),
            'notify_large_transfer'    => $request->boolean('notify_large_transfer'),
            'notify_low_balance'       => $request->boolean('notify_low_balance'),
            'notify_contribution_drop' => $request->boolean('notify_contribution_drop'),
            'notify_unusual_recipient' => $request->boolean('notify_unusual_recipient'),
        ];

        // Only touch the secret URL when one was actually supplied — on
        // edit, a blank field means "keep the current URL".
        if ($request->filled('webhook_url')) {
            $data['webhook_url'] = $validated['webhook_url'];
        }

        if (! $creating) {
            $webhook = Webhook::find($request->input('webhook_id'));
            if ($webhook) {
                $webhook->update($data);

                return redirect()
                    ->route('corpwalletmanager.settings')
                    ->with('success', 'Webhook updated.');
            }
        }

        Webhook::create($data);

        return redirect()
            ->route('corpwalletmanager.settings')
            ->with('success', 'Webhook created.');
    }

    /**
     * Delete a webhook.
     */
    public function destroy(int $webhook)
    {
        Webhook::where('id', $webhook)->delete();

        return redirect()
            ->route('corpwalletmanager.settings')
            ->with('success', 'Webhook deleted.');
    }

    /**
     * Send a test message to a webhook (AJAX).
     */
    public function test(int $webhook)
    {
        $row = Webhook::find($webhook);

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook not found.',
            ], 404);
        }

        try {
            app(WebhookService::class)->sendTest($row);

            return response()->json([
                'success' => true,
                'message' => 'Test message delivered to Discord.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] Webhook test failed', [
                'webhook_id' => $webhook,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
            ], 502);
        }
    }
}
