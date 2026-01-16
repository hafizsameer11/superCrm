<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = User::query();

        // Scope by company (unless super admin)
        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        } elseif ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Apply search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply role filter
        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // Apply status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $users = $query->with('company')->latest()->paginate($request->get('per_page', 15));

        return response()->json($users);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Only super admins and company admins can create users
        if (!$user->isSuperAdmin() && !$user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to create users');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['super_admin', 'company_admin', 'manager', 'staff', 'readonly'])],
            'permissions' => 'nullable|array',
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'company_id' => 'nullable|exists:companies,id',
        ]);

        // Set company_id based on user role
        if ($user->isSuperAdmin() && isset($validated['company_id'])) {
            $companyId = $validated['company_id'];
        } else {
            $companyId = $user->company_id;
        }

        // Super admins can create super_admin users, others cannot
        if ($validated['role'] === 'super_admin' && !$user->isSuperAdmin()) {
            abort(403, 'Only super admins can create super admin users');
        }

        $userData = [
            'company_id' => $companyId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'permissions' => $validated['permissions'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ];

        $newUser = User::create($userData);

        return response()->json($newUser->load('company'), 201);
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, User $user)
    {
        $currentUser = $request->user();

        // Check access
        if (!$currentUser->isSuperAdmin() && $user->company_id !== $currentUser->company_id) {
            abort(403, 'Access denied');
        }

        return response()->json($user->load('company'));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $currentUser = $request->user();

        // Check access
        if (!$currentUser->isSuperAdmin() && $user->company_id !== $currentUser->company_id) {
            abort(403, 'Access denied');
        }

        // Only super admins and company admins can update users
        if (!$currentUser->isSuperAdmin() && !$currentUser->isCompanyAdmin()) {
            abort(403, 'You do not have permission to update users');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => ['sometimes', Rule::in(['super_admin', 'company_admin', 'manager', 'staff', 'readonly'])],
            'permissions' => 'nullable|array',
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        // Super admins can change roles to super_admin, others cannot
        if (isset($validated['role']) && $validated['role'] === 'super_admin' && !$currentUser->isSuperAdmin()) {
            abort(403, 'Only super admins can assign super admin role');
        }

        // Prevent users from changing their own role/status (unless super admin)
        if ($user->id === $currentUser->id && !$currentUser->isSuperAdmin()) {
            unset($validated['role']);
            unset($validated['status']);
        }

        // Update password if provided
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user->load('company'));
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user)
    {
        $currentUser = $request->user();

        // Check access
        if (!$currentUser->isSuperAdmin() && $user->company_id !== $currentUser->company_id) {
            abort(403, 'Access denied');
        }

        // Only super admins and company admins can delete users
        if (!$currentUser->isSuperAdmin() && !$currentUser->isCompanyAdmin()) {
            abort(403, 'You do not have permission to delete users');
        }

        // Prevent users from deleting themselves
        if ($user->id === $currentUser->id) {
            abort(403, 'You cannot delete your own account');
        }

        // Prevent deleting super admin users (unless current user is super admin)
        if ($user->isSuperAdmin() && !$currentUser->isSuperAdmin()) {
            abort(403, 'You cannot delete super admin users');
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully'], 204);
    }
}
