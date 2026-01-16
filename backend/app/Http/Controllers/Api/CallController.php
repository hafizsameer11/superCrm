<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Customer;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CallController extends Controller
{
    /**
     * Get list of calls with filters.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->isSuperAdmin() && $request->has('company_id')
            ? $request->company_id
            : $user->company_id;

        $query = Call::with(['user', 'customer', 'opportunity'])
            ->where('company_id', $companyId);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('today')) {
            $query->today();
        }
        if ($request->has('needs_callback')) {
            $query->needsCallback();
        }
        if ($request->has('date')) {
            $query->whereDate('scheduled_at', $request->date);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'scheduled_at');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        return response()->json($query->paginate($perPage));
    }

    /**
     * Get call center statistics/KPIs.
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        $companyId = $user->isSuperAdmin() && $request->has('company_id')
            ? $request->company_id
            : $user->company_id;

        $today = now()->startOfDay();
        $tomorrow = now()->addDay()->startOfDay();

        // Calls to do (scheduled for today)
        $callsToDo = Call::where('company_id', $companyId)
            ->where('status', 'scheduled')
            ->whereDate('scheduled_at', today())
            ->count();

        // Callbacks (needs callback within 24h)
        $callbacks = Call::where('company_id', $companyId)
            ->whereNotNull('callback_at')
            ->where('callback_at', '<=', now()->addHours(24))
            ->where('status', '!=', 'completed')
            ->count();

        // Calls done today
        $callsDone = Call::where('company_id', $companyId)
            ->where('status', 'completed')
            ->whereDate('completed_at', today())
            ->count();

        // Conversion rate (calls that converted to opportunities / total completed calls)
        $totalCompleted = Call::where('company_id', $companyId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(30))
            ->count();
        
        $convertedCalls = Call::where('company_id', $companyId)
            ->where('status', 'completed')
            ->where('converted_to_opportunity', true)
            ->where('completed_at', '>=', now()->subDays(30))
            ->count();

        $conversionRate = $totalCompleted > 0 
            ? round(($convertedCalls / $totalCompleted) * 100, 1)
            : 0;

        return response()->json([
            'calls_to_do' => $callsToDo,
            'callbacks' => $callbacks,
            'calls_done' => $callsDone,
            'conversion_rate' => $conversionRate . '%',
        ]);
    }

    /**
     * Get operator performance.
     */
    public function operators(Request $request)
    {
        $user = $request->user();
        $companyId = $user->isSuperAdmin() && $request->has('company_id')
            ? $request->company_id
            : $user->company_id;

        $period = $request->get('period', 30); // days
        $startDate = now()->subDays($period);

        // Get calls grouped by user
        $calls = Call::where('company_id', $companyId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $startDate)
            ->with('user')
            ->get()
            ->groupBy('user_id');

        $operators = $calls->map(function ($userCalls, $userId) {
            $user = $userCalls->first()->user;
            $totalCalls = $userCalls->count();
            $convertedCalls = $userCalls->where('converted_to_opportunity', true)->count();
            
            // Calculate average duration
            $totalDuration = $userCalls->sum('duration_seconds');
            $avgDurationSeconds = $totalCalls > 0 ? round($totalDuration / $totalCalls) : 0;
            $avgDuration = $avgDurationSeconds > 0 
                ? gmdate('i:s', $avgDurationSeconds) 
                : '0:00';
            
            // Format as "X:XX min" for display
            $minutes = floor($avgDurationSeconds / 60);
            $seconds = $avgDurationSeconds % 60;
            $avgDisplay = $minutes > 0 ? "{$minutes}:{$seconds}" : "0:{$seconds}";

            return [
                'id' => $userId,
                'name' => $user->name ?? 'Unknown',
                'calls' => $totalCalls,
                'sales' => $convertedCalls,
                'avg' => $avgDisplay,
            ];
        })->sortByDesc('calls')->take(10)->values();

        return response()->json($operators->toArray());
    }

    /**
     * Get today's calls.
     */
    public function today(Request $request)
    {
        $user = $request->user();
        $companyId = $user->isSuperAdmin() && $request->has('company_id')
            ? $request->company_id
            : $user->company_id;

        $calls = Call::where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereDate('scheduled_at', today())
                    ->orWhereDate('completed_at', today());
            })
            ->with(['customer', 'user'])
            ->orderBy('scheduled_at', 'asc')
            ->get();

        $formatted = $calls->map(function ($call) {
            $contactName = $call->contact_name 
                ?? ($call->customer ? ($call->customer->first_name . ' ' . $call->customer->last_name) : 'Unknown');
            
            $source = $call->source ?? 'Direct';
            
            // Map priority
            $priorityMap = [
                'urgent' => 'Alta',
                'high' => 'Alta',
                'medium' => 'Media',
                'low' => 'Bassa',
            ];
            $priority = $priorityMap[$call->priority] ?? 'Media';

            $time = $call->scheduled_at 
                ? $call->scheduled_at->format('H:i')
                : ($call->completed_at ? $call->completed_at->format('H:i') : '');

            return [
                'id' => $call->id,
                'time' => $time,
                'who' => $contactName,
                'source' => $source,
                'sourceKey' => strtolower(str_replace(' ', '', $source)),
                'prio' => $priority,
                'status' => $call->status,
                'phone' => $call->contact_phone ?? $call->customer?->phone,
            ];
        });

        return response()->json($formatted->toArray());
    }

    /**
     * Store a new call.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'opportunity_id' => 'nullable|exists:opportunities,id',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:255',
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'status' => ['sometimes', Rule::in(['scheduled', 'in_progress', 'completed', 'no_answer', 'busy', 'cancelled'])],
            'scheduled_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'next_action' => 'nullable|string',
            'callback_at' => 'nullable|date',
        ]);

        $user = $request->user();
        $validated['company_id'] = $user->company_id;
        $validated['user_id'] = $user->id;
        $validated['status'] = $validated['status'] ?? 'scheduled';

        // If customer_id is provided, get customer info
        if (isset($validated['customer_id'])) {
            $customer = Customer::find($validated['customer_id']);
            if ($customer) {
                $validated['contact_name'] = $validated['contact_name'] ?? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                $validated['contact_phone'] = $validated['contact_phone'] ?? $customer->phone;
            }
        }

        $call = Call::create($validated);

        return response()->json($call->load(['user', 'customer', 'opportunity']), 201);
    }

    /**
     * Update a call.
     */
    public function update(Request $request, Call $call)
    {
        $validated = $request->validate([
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:255',
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'status' => ['sometimes', Rule::in(['scheduled', 'in_progress', 'completed', 'no_answer', 'busy', 'cancelled'])],
            'outcome' => ['sometimes', Rule::in(['successful', 'no_answer', 'busy', 'voicemail', 'callback_requested', 'not_interested', 'other'])],
            'scheduled_at' => 'nullable|date',
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'duration_seconds' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'next_action' => 'nullable|string',
            'callback_at' => 'nullable|date',
            'converted_to_opportunity' => 'sometimes|boolean',
            'value' => 'nullable|numeric|min:0',
        ]);

        // If status changed to completed, set completed_at
        if (isset($validated['status']) && $validated['status'] === 'completed' && !$call->completed_at) {
            $validated['completed_at'] = now();
            
            // Calculate duration if started_at exists
            if ($call->started_at && !isset($validated['duration_seconds'])) {
                $validated['duration_seconds'] = now()->diffInSeconds($call->started_at);
            }
        }

        // If status changed to in_progress, set started_at
        if (isset($validated['status']) && $validated['status'] === 'in_progress' && !$call->started_at) {
            $validated['started_at'] = now();
        }

        $call->update($validated);

        return response()->json($call->load(['user', 'customer', 'opportunity']));
    }

    /**
     * Complete a call.
     */
    public function complete(Request $request, Call $call)
    {
        $validated = $request->validate([
            'outcome' => ['required', Rule::in(['successful', 'no_answer', 'busy', 'voicemail', 'callback_requested', 'not_interested', 'other'])],
            'notes' => 'nullable|string',
            'next_action' => 'nullable|string',
            'callback_at' => 'nullable|date',
            'converted_to_opportunity' => 'sometimes|boolean',
            'value' => 'nullable|numeric|min:0',
            'duration_seconds' => 'nullable|integer|min:0',
        ]);

        $updateData = array_merge($validated, [
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Calculate duration if started_at exists
        if ($call->started_at && !isset($updateData['duration_seconds'])) {
            $updateData['duration_seconds'] = now()->diffInSeconds($call->started_at);
        }

        // If not started_at, set it now
        if (!$call->started_at) {
            $updateData['started_at'] = now();
        }

        $call->update($updateData);

        return response()->json($call->load(['user', 'customer', 'opportunity']));
    }

    /**
     * Show a single call.
     */
    public function show(Call $call)
    {
        return response()->json($call->load(['user', 'customer', 'opportunity']));
    }

    /**
     * Delete a call.
     */
    public function destroy(Call $call)
    {
        $call->delete();
        return response()->json(null, 204);
    }
}
