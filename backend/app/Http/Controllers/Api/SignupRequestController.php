<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SignupRequest;
use App\Services\SignupApprovalService;
use Illuminate\Http\Request;

class SignupRequestController extends Controller
{
    public function __construct(
        private SignupApprovalService $approvalService
    ) {}

    /**
     * Display a listing of signup requests.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Only super admin can see all requests
        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admin can view signup requests');
        }

        $query = SignupRequest::with('company', 'reviewer');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($requests);
    }

    /**
     * Store a newly created signup request.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_data' => 'required|array',
            'company_data.name' => 'required|string',
            'company_data.vat' => 'nullable|string',
            'company_data.address' => 'nullable|string',
            'contact_person' => 'required|array',
            'contact_person.name' => 'required|string',
            'contact_person.email' => 'required|email',
            'contact_person.password' => 'required|string|min:8',
            'requested_projects' => 'required|array',
            'requested_projects.*' => 'exists:projects,id',
        ]);

        // Create company first
        $company = \App\Models\Company::create([
            'name' => $validated['company_data']['name'],
            'vat' => $validated['company_data']['vat'] ?? null,
            'address' => $validated['company_data']['address'] ?? null,
            'status' => 'pending',
        ]);

        // Create user
        $user = \App\Models\User::create([
            'company_id' => $company->id,
            'name' => $validated['contact_person']['name'],
            'email' => $validated['contact_person']['email'],
            'password' => $validated['contact_person']['password'],
            'role' => 'company_admin',
            'status' => 'pending',
        ]);

        // Create signup request
        $signupRequest = SignupRequest::create([
            'company_id' => $company->id,
            'requested_projects' => $validated['requested_projects'],
            'company_data' => $validated['company_data'],
            'contact_person' => $validated['contact_person'],
            'status' => 'pending',
        ]);

        return response()->json($signupRequest->load('company'), 201);
    }

    /**
     * Approve a signup request.
     */
    public function approve(Request $request, SignupRequest $signupRequest)
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admin can approve signup requests');
        }

        $selectedProjects = $request->input('selected_projects', $signupRequest->requested_projects);

        $result = $this->approvalService->approveSignupRequest($signupRequest, $user, $selectedProjects);

        return response()->json([
            'message' => 'Signup request approved',
            'result' => $result,
        ]);
    }

    /**
     * Reject a signup request.
     */
    public function reject(Request $request, SignupRequest $signupRequest)
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admin can reject signup requests');
        }

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string',
        ]);

        $signupRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return response()->json(['message' => 'Signup request rejected']);
    }
}
