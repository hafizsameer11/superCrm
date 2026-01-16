<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Company::query();

        // Super admin sees all, others see only their company
        if (!$user->isSuperAdmin()) {
            $query->where('id', $user->company_id);
        }

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('vat', 'like', "%{$search}%");
            });
        }

        $companies = $query->with('users', 'projectAccesses.project')
            ->paginate($request->get('per_page', 15));

        return response()->json($companies);
    }

    /**
     * Store a newly created company.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Only super admin can create companies
        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admin can create companies');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'vat' => 'nullable|string|unique:companies,vat',
            'address' => 'nullable|string',
            'status' => 'nullable|in:pending,active,suspended',
            'settings' => 'nullable|array',
        ]);

        $company = Company::create($validated);

        return response()->json($company, 201);
    }

    /**
     * Display the specified company.
     */
    public function show(Request $request, Company $company)
    {
        $user = $request->user();

        // Check access
        if (!$user->isSuperAdmin() && $company->id !== $user->company_id) {
            abort(403, 'Access denied');
        }

        $company->load('users', 'projectAccesses.project', 'customers');

        return response()->json($company);
    }

    /**
     * Update the specified company.
     */
    public function update(Request $request, Company $company)
    {
        $user = $request->user();

        // Check access
        if (!$user->isSuperAdmin() && $company->id !== $user->company_id) {
            abort(403, 'Access denied');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'vat' => 'sometimes|string|unique:companies,vat,' . $company->id,
            'address' => 'nullable|string',
            'status' => 'sometimes|in:pending,active,suspended',
            'settings' => 'nullable|array',
        ]);

        $company->update($validated);

        return response()->json($company);
    }

    /**
     * Remove the specified company.
     */
    public function destroy(Request $request, Company $company)
    {
        $user = $request->user();

        // Only super admin can delete companies
        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admin can delete companies');
        }

        $company->delete();

        return response()->json(['message' => 'Company deleted successfully']);
    }

    /**
     * Get projects accessible by a company.
     */
    public function projects(Request $request, Company $company)
    {
        $user = $request->user();

        // Only super admin can view company projects
        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admin can view company projects');
        }

        $accesses = \App\Models\CompanyProjectAccess::where('company_id', $company->id)
            ->with('project')
            ->get();

        return response()->json($accesses);
    }

    /**
     * Grant project access to a company.
     */
    public function grantProjectAccess(Request $request, Company $company)
    {
        $user = $request->user();

        // Only super admin can grant access
        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admin can grant project access');
        }

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'status' => 'sometimes|in:pending,active,suspended,revoked',
            'api_credentials' => 'nullable|array',
            'external_company_id' => 'nullable|string|max:255',
        ]);

        // Check if access already exists
        $existingAccess = \App\Models\CompanyProjectAccess::where('company_id', $company->id)
            ->where('project_id', $validated['project_id'])
            ->first();

        if ($existingAccess) {
            // Update existing access
            $existingAccess->update([
                'status' => $validated['status'] ?? 'active',
                'external_company_id' => $validated['external_company_id'] ?? $existingAccess->external_company_id,
                'approved_at' => now(),
                'approved_by' => $user->id,
            ]);

            if (isset($validated['api_credentials'])) {
                $existingAccess->setEncryptedApiCredentials($validated['api_credentials']);
                $existingAccess->save();
            }

            return response()->json($existingAccess->load('project'));
        }

        // Create new access
        $access = \App\Models\CompanyProjectAccess::create([
            'company_id' => $company->id,
            'project_id' => $validated['project_id'],
            'status' => $validated['status'] ?? 'active',
            'external_company_id' => $validated['external_company_id'] ?? null,
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        if (isset($validated['api_credentials'])) {
            $access->setEncryptedApiCredentials($validated['api_credentials']);
            $access->save();
        }

        return response()->json($access->load('project'), 201);
    }

    /**
     * Revoke project access from a company.
     */
    public function revokeProjectAccess(Request $request, Company $company, int $projectId)
    {
        $user = $request->user();

        // Only super admin can revoke access
        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admin can revoke project access');
        }

        $access = \App\Models\CompanyProjectAccess::where('company_id', $company->id)
            ->where('project_id', $projectId)
            ->firstOrFail();

        $access->update([
            'status' => 'revoked',
        ]);

        return response()->json(['message' => 'Project access revoked successfully']);
    }

    /**
     * Update project access status.
     */
    public function updateProjectAccess(Request $request, Company $company, int $projectId)
    {
        $user = $request->user();

        // Only super admin can update access
        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admin can update project access');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,active,suspended,revoked',
        ]);

        $access = \App\Models\CompanyProjectAccess::where('company_id', $company->id)
            ->where('project_id', $projectId)
            ->firstOrFail();

        $access->update($validated);

        return response()->json($access->load('project'));
    }
}
