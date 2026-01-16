<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SignupRequestController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\CustomFieldController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\CampaignController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Companies
    Route::apiResource('companies', CompanyController::class);
    Route::get('/companies/{company}/projects', [CompanyController::class, 'projects']); // Super admin only
    Route::post('/companies/{company}/projects/grant', [CompanyController::class, 'grantProjectAccess']); // Super admin only
    Route::delete('/companies/{company}/projects/{projectId}', [CompanyController::class, 'revokeProjectAccess']); // Super admin only
    Route::put('/companies/{company}/projects/{projectId}', [CompanyController::class, 'updateProjectAccess']); // Super admin only

    // Signup Requests
    Route::get('/signup-requests', [SignupRequestController::class, 'index']);
    Route::post('/signup-requests', [SignupRequestController::class, 'store']);
    Route::put('/signup-requests/{signupRequest}/approve', [SignupRequestController::class, 'approve']);
    Route::put('/signup-requests/{signupRequest}/reject', [SignupRequestController::class, 'reject']);

    // Projects
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::post('/projects', [ProjectController::class, 'store']); // Super admin only
    Route::put('/projects/{project}', [ProjectController::class, 'update']); // Super admin only
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']); // Super admin only
    Route::post('/projects/{project}/sso/redirect', [ProjectController::class, 'generateSSORedirect']);
    Route::get('/projects/{project}/iframe-callback', [ProjectController::class, 'iframeCallback']);

    // Customers
    Route::apiResource('customers', CustomerController::class);
    Route::post('/customers/merge', [CustomerController::class, 'merge']);

    // Leads
    Route::apiResource('leads', LeadController::class);

    // Dashboard
    Route::get('/dashboard/kpis', [DashboardController::class, 'kpis']);
    Route::get('/dashboard/pipeline', [DashboardController::class, 'pipeline']);
    Route::get('/dashboard/leads', [DashboardController::class, 'leads']);
    Route::get('/dashboard/lead-sources', [DashboardController::class, 'leadSources']);
    Route::get('/dashboard/top-operators', [DashboardController::class, 'topOperators']);

    // Opportunities
    Route::apiResource('opportunities', OpportunityController::class);
    Route::post('/opportunities/{opportunity}/convert', [OpportunityController::class, 'convert']);

    // Tasks
    Route::apiResource('tasks', TaskController::class);
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete']);
    Route::post('/tasks/{task}/assign', [TaskController::class, 'assign']);

    // Notes
    Route::apiResource('notes', NoteController::class);
    Route::post('/notes/{note}/pin', [NoteController::class, 'pin']);

    // Documents
    Route::apiResource('documents', DocumentController::class);
    Route::post('/documents/upload', [DocumentController::class, 'upload']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);

    // Activity Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show']);

    // Reports
    Route::apiResource('reports', ReportController::class);
    Route::post('/reports/{report}/generate', [ReportController::class, 'generate']);
    Route::post('/reports/{report}/schedule', [ReportController::class, 'schedule']);

    // Webhooks
    Route::apiResource('webhooks', WebhookController::class);
    Route::post('/webhooks/{webhook}/test', [WebhookController::class, 'test']);
    Route::get('/webhooks/{webhook}/logs', [WebhookController::class, 'logs']);

    // Custom Fields
    Route::apiResource('custom-fields', CustomFieldController::class);
    Route::post('/custom-fields/{customField}/values', [CustomFieldController::class, 'setValue']);

    // Calls
    Route::apiResource('calls', CallController::class);
    Route::get('/calls/stats', [CallController::class, 'stats']);
    Route::get('/calls/operators', [CallController::class, 'operators']);
    Route::get('/calls/today', [CallController::class, 'today']);
    Route::post('/calls/{call}/complete', [CallController::class, 'complete']);

    // Support Tickets
    Route::apiResource('support-tickets', SupportTicketController::class);
    Route::post('/support-tickets/{supportTicket}/assign', [SupportTicketController::class, 'assign']);
    Route::post('/support-tickets/{supportTicket}/close', [SupportTicketController::class, 'close']);

    // Campaigns
    Route::apiResource('campaigns', CampaignController::class);
    Route::get('/campaigns/stats', [CampaignController::class, 'stats']);
});
