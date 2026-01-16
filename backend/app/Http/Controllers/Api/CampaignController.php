<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Campaign::with(['creator', 'project']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['required', Rule::in(['email', 'sms', 'social_media', 'advertising', 'content', 'event', 'other'])],
            'status' => ['sometimes', Rule::in(['draft', 'scheduled', 'active', 'paused', 'completed', 'cancelled'])],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'scheduled_at' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'target_audience' => 'nullable|array',
            'target_criteria' => 'nullable|array',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'content_data' => 'nullable|array',
            'settings' => 'nullable|array',
            'track_clicks' => 'sometimes|boolean',
            'track_opens' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $validated['company_id'] = $user->company_id;
        $validated['created_by'] = $user->id;
        $validated['status'] = $validated['status'] ?? 'draft';

        $campaign = DB::transaction(function () use ($validated) {
            $campaign = Campaign::create($validated);
            $this->activityLogService->logCreated($campaign);
            return $campaign;
        });

        return response()->json($campaign->load(['creator', 'project']), 201);
    }

    public function show(Campaign $campaign)
    {
        $this->activityLogService->logViewed($campaign);
        return response()->json($campaign->load(['creator', 'project', 'company']));
    }

    public function update(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => ['sometimes', Rule::in(['email', 'sms', 'social_media', 'advertising', 'content', 'event', 'other'])],
            'status' => ['sometimes', Rule::in(['draft', 'scheduled', 'active', 'paused', 'completed', 'cancelled'])],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'scheduled_at' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'target_audience' => 'nullable|array',
            'target_criteria' => 'nullable|array',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'content_data' => 'nullable|array',
            'settings' => 'nullable|array',
            'track_clicks' => 'sometimes|boolean',
            'track_opens' => 'sometimes|boolean',
        ]);

        $oldValues = $campaign->getAttributes();

        $campaign = DB::transaction(function () use ($campaign, $validated) {
            $campaign->update($validated);
            
            // If status changed to active and start_date is in the past, update it
            if (isset($validated['status']) && $validated['status'] === 'active' && !$campaign->start_date) {
                $campaign->start_date = now();
            }

            $this->activityLogService->logUpdated($campaign, $oldValues, $campaign->getAttributes());
            return $campaign;
        });

        return response()->json($campaign->load(['creator', 'project']));
    }

    public function destroy(Campaign $campaign)
    {
        $this->activityLogService->logDeleted($campaign);
        $campaign->delete();
        return response()->json(null, 204);
    }

    /**
     * Get campaign statistics.
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        $companyId = $user->isSuperAdmin() && $request->has('company_id')
            ? $request->company_id
            : $user->company_id;

        $stats = [
            'total_campaigns' => Campaign::where('company_id', $companyId)->count(),
            'active_campaigns' => Campaign::where('company_id', $companyId)->active()->count(),
            'total_sent' => Campaign::where('company_id', $companyId)->sum('sent_count'),
            'total_opened' => Campaign::where('company_id', $companyId)->sum('opened_count'),
            'total_clicked' => Campaign::where('company_id', $companyId)->sum('clicked_count'),
            'total_converted' => Campaign::where('company_id', $companyId)->sum('converted_count'),
            'total_budget' => Campaign::where('company_id', $companyId)->sum('budget'),
            'total_spent' => Campaign::where('company_id', $companyId)->sum('spent'),
        ];

        // Calculate averages
        $activeCampaigns = Campaign::where('company_id', $companyId)->active()->get();
        if ($activeCampaigns->count() > 0) {
            $stats['avg_open_rate'] = $activeCampaigns->avg('open_rate') ?? 0;
            $stats['avg_click_rate'] = $activeCampaigns->avg('click_rate') ?? 0;
            $stats['avg_conversion_rate'] = $activeCampaigns->avg('conversion_rate') ?? 0;
        } else {
            $stats['avg_open_rate'] = 0;
            $stats['avg_click_rate'] = 0;
            $stats['avg_conversion_rate'] = 0;
        }

        return response()->json($stats);
    }
}
