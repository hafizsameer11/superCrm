<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyProjectAccess;
use App\Models\Project;
use App\Services\RateLimitService;
use App\Services\SSOService;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(
        private SSOService $ssoService,
        private RateLimitService $rateLimitService
    ) {}

    /**
     * Display a listing of accessible projects.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            // Super admin sees all projects (with optional filter)
            $query = Project::query();
            
            if ($request->has('is_active')) {
                if ($request->is_active === 'true') {
                    $query->active();
                } elseif ($request->is_active === 'false') {
                    $query->where('is_active', false);
                }
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }
            
            $projects = $query->with('companyAccesses.company')->get();
        } else {
            // Regular users see only projects they have access to
            $projectIds = CompanyProjectAccess::where('company_id', $user->company_id)
                ->where('status', 'active')
                ->pluck('project_id');

            $projects = Project::whereIn('id', $projectIds)
                ->active()
                ->get();
        }

        return response()->json($projects);
    }

    /**
     * Store a newly created project (Super Admin only).
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admin can create projects');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:projects,slug',
            'description' => 'nullable|string',
            'integration_type' => 'required|in:api,iframe,hybrid',
            'api_base_url' => 'nullable|url|max:500',
            'api_auth_type' => 'nullable|in:bearer,basic,oauth2,custom',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'api_signup_endpoint' => 'nullable|string|max:255',
            'api_login_endpoint' => 'nullable|string|max:255',
            'api_sso_endpoint' => 'nullable|string|max:255',
            'admin_panel_url' => 'nullable|url|max:500',
            'iframe_width' => 'nullable|string|max:50',
            'iframe_height' => 'nullable|string|max:50',
            'iframe_sandbox' => 'nullable|string|max:255',
            'sso_enabled' => 'sometimes|boolean',
            'sso_method' => 'nullable|in:jwt,oauth2,redirect,legacy_unsafe',
            'sso_token_expiry' => 'nullable|integer|min:60',
            'sso_redirect_url' => 'nullable|url|max:500',
            'sso_callback_url' => 'nullable|url|max:500',
            'driver_class' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        // Encrypt API credentials if provided
        if (isset($validated['api_key'])) {
            $validated['api_key'] = \Illuminate\Support\Facades\Crypt::encryptString($validated['api_key']);
        }
        if (isset($validated['api_secret'])) {
            $validated['api_secret'] = \Illuminate\Support\Facades\Crypt::encryptString($validated['api_secret']);
        }

        $project = Project::create($validated);

        return response()->json($project, 201);
    }

    /**
     * Update the specified project (Super Admin only).
     */
    public function update(Request $request, Project $project)
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admin can update projects');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:100|unique:projects,slug,' . $project->id,
            'description' => 'nullable|string',
            'integration_type' => 'sometimes|in:api,iframe,hybrid',
            'api_base_url' => 'nullable|url|max:500',
            'api_auth_type' => 'nullable|in:bearer,basic,oauth2,custom',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'api_signup_endpoint' => 'nullable|string|max:255',
            'api_login_endpoint' => 'nullable|string|max:255',
            'api_sso_endpoint' => 'nullable|string|max:255',
            'admin_panel_url' => 'nullable|url|max:500',
            'iframe_width' => 'nullable|string|max:50',
            'iframe_height' => 'nullable|string|max:50',
            'iframe_sandbox' => 'nullable|string|max:255',
            'sso_enabled' => 'sometimes|boolean',
            'sso_method' => 'nullable|in:jwt,oauth2,redirect,legacy_unsafe',
            'sso_token_expiry' => 'nullable|integer|min:60',
            'sso_redirect_url' => 'nullable|url|max:500',
            'sso_callback_url' => 'nullable|url|max:500',
            'driver_class' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        // Encrypt API credentials if provided
        if (isset($validated['api_key'])) {
            $validated['api_key'] = \Illuminate\Support\Facades\Crypt::encryptString($validated['api_key']);
        }
        if (isset($validated['api_secret'])) {
            $validated['api_secret'] = \Illuminate\Support\Facades\Crypt::encryptString($validated['api_secret']);
        }

        $project->update($validated);

        return response()->json($project);
    }

    /**
     * Remove the specified project (Super Admin only).
     */
    public function destroy(Request $request, Project $project)
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admin can delete projects');
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted successfully'], 204);
    }

    /**
     * Display the specified project.
     */
    public function show(Request $request, Project $project)
    {
        $user = $request->user();

        // Check access for non-super-admin
        if (!$user->isSuperAdmin()) {
            $hasAccess = CompanyProjectAccess::where('company_id', $user->company_id)
                ->where('project_id', $project->id)
                ->where('status', 'active')
                ->exists();

            if (!$hasAccess) {
                abort(403, 'Access denied: No access to this project');
            }
        }

        return response()->json($project);
    }

    /**
     * Generate SSO redirect URL.
     */
    public function generateSSORedirect(Request $request, Project $project)
    {
        $user = $request->user();
        $company = $user->company;

        // Check access
        $access = CompanyProjectAccess::where('company_id', $company->id)
            ->where('project_id', $project->id)
            ->where('status', 'active')
            ->firstOrFail();

        // Check rate limit
        if (!$this->rateLimitService->checkRateLimit($access)) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        // Generate SSO redirect URL (top-level)
        $redirectUrl = $this->ssoService->getSSORedirectUrl($access, $project, $user);

        return response()->json([
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * Handle iframe callback after SSO.
     */
    public function iframeCallback(Request $request, Project $project)
    {
        $user = $request->user();
        $company = $user->company;

        $access = CompanyProjectAccess::where('company_id', $company->id)
            ->where('project_id', $project->id)
            ->where('status', 'active')
            ->firstOrFail();

        // Return iframe page data
        return response()->json([
            'project' => $project,
            'iframe_url' => $project->admin_panel_url,
            'iframe_width' => $project->iframe_width,
            'iframe_height' => $project->iframe_height,
            'iframe_sandbox' => $project->iframe_sandbox,
        ]);
    }
}
