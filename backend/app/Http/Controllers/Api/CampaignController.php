<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\ActivityLogService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    protected $activityLogService;
    protected $stripeService;

    public function __construct(ActivityLogService $activityLogService, StripeService $stripeService)
    {
        $this->activityLogService = $activityLogService;
        $this->stripeService = $stripeService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        // Super admin sees all campaigns, others see only their company's campaigns
        // The global scope handles this, but we need to bypass it for super admin
        if ($user->isSuperAdmin()) {
            $query = Campaign::withoutGlobalScope('company')->with(['creator', 'project', 'company']);
        } else {
            $query = Campaign::with(['creator', 'project']);
        }

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        if ($request->has('project_id')) {
            $projectId = $request->project_id;
            // For non-super-admin, validate they have access to the project
            if (!$user->isSuperAdmin() && !$user->hasProjectAccess($projectId)) {
                return response()->json([
                    'message' => 'You do not have access to this project',
                ], 403);
            }
            $query->where('project_id', $projectId);
        } elseif (!$user->isSuperAdmin()) {
            // For non-super-admin, filter campaigns to only show projects they have access to
            $accessibleProjectIds = $user->getAccessibleProjectIds();
            if (empty($accessibleProjectIds)) {
                // User has no project access, return empty result
                $query->whereRaw('1 = 0'); // Force no results
            } else {
                $query->whereIn('project_id', $accessibleProjectIds);
            }
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
        
        // Validate project access if project_id is provided
        if (isset($validated['project_id']) && !$user->hasProjectAccess($validated['project_id'])) {
            return response()->json([
                'message' => 'You do not have access to this project',
            ], 403);
        }
        
        $validated['company_id'] = $user->company_id;
        $validated['created_by'] = $user->id;
        // Always set status to 'draft' (pending) when creating - admin will approve later
        $validated['status'] = 'draft';

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
        $user = $request->user();
        
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

        // Validate project access if project_id is being updated
        if (isset($validated['project_id']) && !$user->hasProjectAccess($validated['project_id'])) {
            return response()->json([
                'message' => 'You do not have access to this project',
            ], 403);
        }

        $oldValues = $campaign->getAttributes();

        $campaign = DB::transaction(function () use ($campaign, $validated, $oldValues) {
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
        
        // Super admin sees all campaigns, others see only their company's campaigns
        if ($user->isSuperAdmin()) {
            $query = Campaign::withoutGlobalScope('company');
            if ($request->has('company_id')) {
                $query->where('company_id', $request->company_id);
            }
        } else {
            $query = Campaign::where('company_id', $user->company_id);
        }

        $stats = [
            'total_campaigns' => (clone $query)->count(),
            'active_campaigns' => (clone $query)->where('status', 'active')->count(),
            'total_sent' => (clone $query)->sum('sent_count') ?? 0,
            'total_opened' => (clone $query)->sum('opened_count') ?? 0,
            'total_clicked' => (clone $query)->sum('clicked_count') ?? 0,
            'total_converted' => (clone $query)->sum('converted_count') ?? 0,
            'total_budget' => (clone $query)->sum('budget') ?? 0,
            'total_spent' => (clone $query)->sum('spent') ?? 0,
        ];

        // Calculate averages from active campaigns
        $activeCampaigns = (clone $query)->where('status', 'active')->get();
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

    /**
     * Create Stripe checkout session for campaign payment.
     */
    public function createPaymentCheckout(Request $request, Campaign $campaign)
    {
        $user = $request->user();

        // Check access
        if (!$user->isSuperAdmin() && $campaign->company_id !== $user->company_id) {
            abort(403, 'Access denied');
        }

        // Validate campaign has budget
        if (!$campaign->budget || $campaign->budget <= 0) {
            return response()->json([
                'message' => 'Campaign must have a valid budget to proceed with payment',
            ], 400);
        }

        // Check if already paid
        if ($campaign->payment_status === 'paid') {
            return response()->json([
                'message' => 'Campaign payment has already been completed',
            ], 400);
        }

        try {
            $result = $this->stripeService->createCampaignCheckoutSession($campaign);

            return response()->json([
                'session_id' => $result['session_id'],
                'checkout_url' => $result['checkout_url'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Log::error('Stripe API error in campaign payment', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
                'stripe_code' => $e->getStripeCode(),
            ]);
            return response()->json([
                'message' => 'Payment processing error: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Failed to create payment checkout', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to create payment checkout: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle payment success callback.
     */
    public function handlePaymentSuccess(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'campaign_id' => 'required|exists:campaigns,id',
        ]);

        try {
            $session = $this->stripeService->getCheckoutSession($request->session_id);
            $campaign = Campaign::findOrFail($request->campaign_id);

            // Verify session metadata matches campaign
            if ($session->metadata->campaign_id != $campaign->id) {
                return response()->json([
                    'message' => 'Invalid payment session',
                ], 400);
            }

            // Update campaign payment status
            if ($session->payment_status === 'paid') {
                $campaign->update([
                    'payment_status' => 'paid',
                    'stripe_payment_intent_id' => $session->payment_intent,
                ]);

                return response()->json([
                    'message' => 'Payment successful',
                    'campaign' => $campaign->load(['creator', 'project']),
                ]);
            } else {
                $campaign->update([
                    'payment_status' => 'pending',
                ]);

                return response()->json([
                    'message' => 'Payment is pending',
                    'campaign' => $campaign->load(['creator', 'project']),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process payment: ' . $e->getMessage(),
            ], 500);
        }
    }
}
