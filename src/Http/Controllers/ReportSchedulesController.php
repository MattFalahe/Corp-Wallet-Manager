<?php

namespace CorpWalletManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use CorpWalletManager\Http\Controllers\Concerns\AuthorizesCorporationAccess;
use CorpWalletManager\Models\ReportSchedule;

/**
 * CRUD endpoints for the Settings -> Scheduled Reports panel.
 *
 * Schedules live in `corpwalletmanager_report_schedules` and are read by the
 * `corpwalletmanager:dispatch-scheduled-reports` cron (every 5 minutes) to
 * decide which corp + cadence combos are due to fire. Webhook delivery
 * routing stays separate (see `corpwalletmanager_webhooks`).
 *
 * Authorization: the route group's `can:corpwalletmanager.settings` middleware
 * gates access to the settings surface as a whole; corp-level access is
 * checked per-request via AuthorizesCorporationAccess so a director with
 * settings rights on one corp can't manage another corp's schedules.
 */
class ReportSchedulesController extends Controller
{
    use AuthorizesCorporationAccess;

    /**
     * List schedules. Accepts ?corporation_id=N to scope. Admins with no
     * corp filter selected see every row.
     */
    public function index(Request $request)
    {
        try {
            $requested = $request->get('corporation_id');

            // Admin with no filter: return every schedule.
            if (($requested === null || $requested === '') && $this->userIsAdmin()) {
                $rows = ReportSchedule::orderBy('corporation_id')
                    ->orderBy('report_type')
                    ->get();
            } else {
                $corpId = $this->getCorporationId($request);
                if (! $corpId) {
                    return response()->json([
                        'success'   => false,
                        'message'   => 'No corporation context resolved.',
                        'schedules' => [],
                    ], 400);
                }
                $rows = ReportSchedule::where('corporation_id', $corpId)
                    ->orderBy('report_type')
                    ->get();
            }

            $corpNames = $this->corporationNameMap($rows->pluck('corporation_id')->all());

            return response()->json([
                'success'   => true,
                'schedules' => $rows->map(fn ($row) => $this->presentRow($row, $corpNames))->values(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] ReportSchedules index error: ' . $e->getMessage());
            return response()->json([
                'success'   => false,
                'message'   => 'Could not load schedules.',
                'schedules' => [],
            ], 500);
        }
    }

    /**
     * Create a new schedule.
     */
    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        // Corp-level authorization: the operator must be allowed to manage
        // the requested corp (admins bypass).
        if (! $this->userCanAccessCorporation((int) $validated['corporation_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to schedule reports for this corporation.',
            ], 403);
        }

        // Unique (corp, type) - the DB index enforces this but we return a
        // friendly message instead of letting it 500.
        $existing = ReportSchedule::where('corporation_id', $validated['corporation_id'])
            ->where('report_type', $validated['report_type'])
            ->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A schedule for this corporation + cadence already exists. Edit it instead.',
            ], 409);
        }

        $row = new ReportSchedule($this->normalizePayload($validated));
        $row->next_run_at = $row->computeNextRunAt();
        $row->save();

        return response()->json([
            'success'  => true,
            'message'  => 'Schedule created.',
            'schedule' => $this->presentRow($row, $this->corporationNameMap([$row->corporation_id])),
        ], 201);
    }

    /**
     * Update an existing schedule. Recomputes next_run_at on every save.
     */
    public function update(Request $request, $id)
    {
        $row = ReportSchedule::find((int) $id);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Schedule not found.'], 404);
        }

        if (! $this->userCanAccessCorporation((int) $row->corporation_id)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to manage this corporation\'s schedules.',
            ], 403);
        }

        $validated = $this->validatePayload($request, (int) $row->id);

        // Reject changing the corp (would let an operator pivot a row onto
        // a different corp's namespace - if they want that, they should
        // delete + recreate).
        if ((int) $validated['corporation_id'] !== (int) $row->corporation_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change a schedule\'s corporation. Delete and recreate.',
            ], 422);
        }

        $row->fill($this->normalizePayload($validated));
        $row->next_run_at = $row->computeNextRunAt();
        $row->save();

        return response()->json([
            'success'  => true,
            'message'  => 'Schedule updated.',
            'schedule' => $this->presentRow($row, $this->corporationNameMap([$row->corporation_id])),
        ]);
    }

    /**
     * Delete a schedule.
     */
    public function destroy($id)
    {
        $row = ReportSchedule::find((int) $id);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Schedule not found.'], 404);
        }

        if (! $this->userCanAccessCorporation((int) $row->corporation_id)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to manage this corporation\'s schedules.',
            ], 403);
        }

        $row->delete();

        return response()->json(['success' => true, 'message' => 'Schedule deleted.']);
    }

    /**
     * Validate the create/update payload. Day-field requirements differ
     * per cadence (e.g. weekly REQUIRES day_of_week, REJECTS day_of_month).
     * `report_type` immutability on update is checked separately by the
     * unique-index lookup; here we only validate the values are sane.
     */
    private function validatePayload(Request $request, ?int $existingId = null): array
    {
        $cadence = strtolower((string) $request->input('report_type', ''));

        $rules = [
            'corporation_id' => 'required|integer|min:1',
            'report_type'    => ['required', Rule::in(ReportSchedule::CADENCES)],
            'enabled'        => 'nullable|boolean',
            'minute'         => 'required|integer|min:0|max:59',
            'hour'           => 'required|integer|min:0|max:23',
            'day_of_week'    => 'nullable|integer|min:1|max:7',
            'day_of_month'   => 'nullable|integer|min:1|max:28',
            'month_of_year'  => 'nullable|integer|min:1|max:12',
        ];

        // Cadence-specific day-field requirements: required-vs-rejected
        // matrix matches the dispatcher's date-window logic so a row that
        // validates here will always be dispatchable.
        switch ($cadence) {
            case 'daily':
                $rules['day_of_week']   = 'nullable|in:'; // effectively must be null/absent
                $rules['day_of_month']  = 'nullable|in:';
                $rules['month_of_year'] = 'nullable|in:';
                break;

            case 'weekly':
                $rules['day_of_week']   = 'required|integer|min:1|max:7';
                $rules['day_of_month']  = 'nullable|in:';
                $rules['month_of_year'] = 'nullable|in:';
                break;

            case 'monthly':
            case 'quarterly':
                $rules['day_of_week']   = 'nullable|in:';
                $rules['day_of_month']  = 'required|integer|min:1|max:28';
                $rules['month_of_year'] = 'nullable|in:';
                break;

            case 'annual':
                $rules['day_of_week']   = 'nullable|in:';
                $rules['day_of_month']  = 'required|integer|min:1|max:28';
                $rules['month_of_year'] = 'required|integer|min:1|max:12';
                break;
        }

        $messages = [
            'day_of_week.required'   => 'Weekly schedules require a day of the week (1-7, Monday-Sunday).',
            'day_of_week.in'         => 'Day of week is not used for this cadence.',
            'day_of_month.required'  => 'This cadence requires a day of the month (1-28).',
            'day_of_month.in'        => 'Day of month is not used for this cadence.',
            'month_of_year.required' => 'Annual schedules require a month of the year (1-12).',
            'month_of_year.in'       => 'Month of year is not used for this cadence.',
        ];

        return $request->validate($rules, $messages);
    }

    /**
     * Coerce the validated payload into the columns the model expects.
     * Empty-string day fields get nulled. `enabled` defaults to true when
     * absent (form-style PATCH payloads often omit unchecked checkboxes).
     */
    private function normalizePayload(array $v): array
    {
        return [
            'corporation_id' => (int) $v['corporation_id'],
            'report_type'    => strtolower((string) $v['report_type']),
            'enabled'        => array_key_exists('enabled', $v) ? (bool) $v['enabled'] : true,
            'minute'         => (int) $v['minute'],
            'hour'           => (int) $v['hour'],
            'day_of_week'    => isset($v['day_of_week']) && $v['day_of_week'] !== '' ? (int) $v['day_of_week'] : null,
            'day_of_month'   => isset($v['day_of_month']) && $v['day_of_month'] !== '' ? (int) $v['day_of_month'] : null,
            'month_of_year'  => isset($v['month_of_year']) && $v['month_of_year'] !== '' ? (int) $v['month_of_year'] : null,
        ];
    }

    /**
     * Resolve corp names for the supplied corp ids (cheap single query).
     * Falls back to a synthetic "Corp {id}" label when no name is on file.
     */
    private function corporationNameMap(array $corpIds): array
    {
        $corpIds = array_values(array_unique(array_filter(array_map('intval', $corpIds))));
        if (empty($corpIds)) {
            return [];
        }

        try {
            return DB::table('corporation_infos')
                ->whereIn('corporation_id', $corpIds)
                ->pluck('name', 'corporation_id')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Wire-shape: shrink the model row into the JSON the UI consumes.
     */
    private function presentRow(ReportSchedule $row, array $corpNames): array
    {
        $corpId = (int) $row->corporation_id;

        return [
            'id'             => (int) $row->id,
            'corporation_id' => $corpId,
            'corporation'    => $corpNames[$corpId] ?? ('Corp ' . $corpId),
            'report_type'    => $row->report_type,
            'enabled'        => (bool) $row->enabled,
            'minute'         => (int) $row->minute,
            'hour'           => (int) $row->hour,
            'day_of_week'    => $row->day_of_week !== null ? (int) $row->day_of_week : null,
            'day_of_month'   => $row->day_of_month !== null ? (int) $row->day_of_month : null,
            'month_of_year'  => $row->month_of_year !== null ? (int) $row->month_of_year : null,
            'human'          => $row->human_cadence,
            'last_run_at'    => $row->last_run_at ? $row->last_run_at->toIso8601String() : null,
            'next_run_at'    => $row->next_run_at ? $row->next_run_at->toIso8601String() : null,
            'last_status'    => $row->last_status,
            'last_error'     => $row->last_error,
        ];
    }
}
