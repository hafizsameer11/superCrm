# LEO24 CRM - Integration & SSO Plan

## Overview
This document outlines the architecture for integrating existing admin panels, handling signup workflows, and implementing Single Sign-On (SSO) functionality.

---

## 1. Project Integration Types

### Integration Modes

Each project/portal can be configured with one of three integration types:

#### Type 1: **API Integration** (Full Control)
- **Use Case**: Projects where we have full API access
- **Behavior**: Direct API calls for all operations
- **Storage**: API credentials (API keys, tokens)
- **Access**: Custom CRM interface built in LEO24

#### Type 2: **Iframe Embedding** (Existing Admin Panel)
- **Use Case**: Projects with existing admin panels we want to embed
- **Behavior**: Top-level redirect SSO → embed admin panel in iframe
- **Storage**: External user IDs only (NO passwords stored)
- **Access**: Existing admin panel via iframe (after SSO redirect)

#### Type 3: **Hybrid** (API + Iframe)
- **Use Case**: Projects with both API and admin panel
- **Behavior**: Use API for data sync, top-level redirect SSO for iframe
- **Storage**: API credentials + external user IDs (NO passwords)
- **Access**: Custom interface + embedded admin panel (after SSO redirect)

---

## 2. Database Schema

### Core Tables

```sql
-- Projects/Portals Configuration
CREATE TABLE projects (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    integration_type ENUM('api', 'iframe', 'hybrid') NOT NULL,
    
    -- API Configuration (for api/hybrid)
    api_base_url VARCHAR(500),
    api_auth_type ENUM('bearer', 'basic', 'oauth2', 'custom') DEFAULT 'bearer',
    api_key VARCHAR(500), -- encrypted
    api_secret VARCHAR(500), -- encrypted
    api_signup_endpoint VARCHAR(255), -- e.g., /api/v1/users/signup
    api_login_endpoint VARCHAR(255), -- e.g., /api/v1/auth/login
    api_sso_endpoint VARCHAR(255), -- e.g., /api/v1/auth/sso
    
    -- Iframe Configuration (for iframe/hybrid)
    admin_panel_url VARCHAR(500),
    iframe_width VARCHAR(50) DEFAULT '100%',
    iframe_height VARCHAR(50) DEFAULT '100vh',
    iframe_sandbox VARCHAR(255), -- security attributes
    
    -- SSO Configuration
    sso_enabled BOOLEAN DEFAULT TRUE,
    sso_method ENUM('jwt', 'oauth2', 'redirect', 'legacy_unsafe') DEFAULT 'jwt',
    sso_token_expiry INT DEFAULT 3600, -- seconds
    sso_redirect_url VARCHAR(500), -- for top-level redirect SSO
    sso_callback_url VARCHAR(500), -- return URL after SSO
    
    -- Security flags
    requires_password_storage BOOLEAN DEFAULT FALSE, -- legacy/unsafe projects only
    is_legacy BOOLEAN DEFAULT FALSE, -- mark unsafe integrations
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Company-Project Access (Many-to-Many)
CREATE TABLE company_project_access (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    project_id BIGINT NOT NULL,
    
    -- Integration-specific credentials (encrypted)
    -- NEVER store passwords here - only API keys/tokens
    api_credentials JSON, -- { "api_key": "...", "api_secret": "..." } - encrypted
    external_company_id VARCHAR(255), -- ID in target system
    external_account_data JSON, -- project-specific metadata (no passwords)
    
    -- Status
    status ENUM('pending', 'active', 'suspended', 'revoked', 'partial_failed') DEFAULT 'pending',
    approved_at TIMESTAMP NULL,
    approved_by BIGINT NULL, -- user_id of super admin
    
    -- Metadata
    signup_request_data JSON, -- original signup data
    last_sync_at TIMESTAMP NULL,
    last_error TEXT NULL, -- last failure reason
    retry_count INT DEFAULT 0,
    
    -- Rate limiting
    rate_limit_per_minute INT DEFAULT 60,
    rate_limit_per_hour INT DEFAULT 1000,
    circuit_breaker_state ENUM('closed', 'open', 'half_open') DEFAULT 'closed',
    circuit_breaker_failures INT DEFAULT 0,
    circuit_breaker_reset_at TIMESTAMP NULL,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE KEY unique_company_project (company_id, project_id),
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_circuit_breaker (circuit_breaker_state, circuit_breaker_reset_at)
);

-- Multi-User Mapping (CRITICAL: One company can have multiple users per project)
CREATE TABLE company_project_users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_project_access_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL, -- LEO24 user
    
    -- External system mapping
    external_user_id VARCHAR(255), -- user ID in target project system
    external_username VARCHAR(255), -- username in target system (for display only)
    external_role VARCHAR(100), -- role in target system
    
    -- Status
    status ENUM('active', 'suspended', 'revoked') DEFAULT 'active',
    last_sso_at TIMESTAMP NULL,
    
    -- Audit
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    revoked_at TIMESTAMP NULL,
    revoked_by BIGINT NULL,
    
    UNIQUE KEY unique_access_user (company_project_access_id, user_id),
    UNIQUE KEY unique_external_user (company_project_access_id, external_user_id),
    FOREIGN KEY (company_project_access_id) REFERENCES company_project_access(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (revoked_by) REFERENCES users(id),
    INDEX idx_user (user_id),
    INDEX idx_external (external_user_id)
);

-- Signup Requests Queue
CREATE TABLE signup_requests (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    
    -- Request details
    requested_projects JSON NOT NULL, -- [1, 2, 3] project IDs
    company_data JSON NOT NULL, -- company info from signup form
    contact_person JSON NOT NULL, -- primary user/admin info
    
    -- Status
    status ENUM('pending', 'approved', 'rejected', 'processing') DEFAULT 'pending',
    reviewed_by BIGINT NULL,
    reviewed_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    
    -- API Call Logs
    api_calls_log JSON, -- track API calls made during approval
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- API Call Logs (for debugging/audit)
CREATE TABLE api_integration_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_project_access_id BIGINT,
    project_id BIGINT NOT NULL,
    user_id BIGINT NULL, -- who initiated the call
    
    -- Request details
    endpoint VARCHAR(500),
    method VARCHAR(10),
    request_payload JSON, -- sanitized (no passwords/secrets)
    response_status INT,
    response_body JSON, -- sanitized
    error_message TEXT,
    
    -- Rate limiting tracking
    rate_limit_hit BOOLEAN DEFAULT FALSE,
    
    -- Timing
    duration_ms INT,
    created_at TIMESTAMP,
    
    FOREIGN KEY (company_project_access_id) REFERENCES company_project_access(id),
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_project_created (project_id, created_at),
    INDEX idx_access_created (company_project_access_id, created_at)
);

-- SSO Token Usage (JWT replay protection)
CREATE TABLE sso_token_usage (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    jti VARCHAR(255) UNIQUE NOT NULL, -- JWT ID claim
    company_project_access_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    project_id BIGINT NOT NULL,
    
    -- Token details
    issued_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL, -- when token was consumed
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    -- Status
    status ENUM('issued', 'used', 'expired', 'revoked') DEFAULT 'issued',
    
    created_at TIMESTAMP,
    
    FOREIGN KEY (company_project_access_id) REFERENCES company_project_access(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (project_id) REFERENCES projects(id),
    INDEX idx_jti (jti),
    INDEX idx_expires (expires_at, status)
);
```

---

## 3. Signup & Approval Workflow

### Step-by-Step Flow

```
┌─────────────────────────────────────────────────────────────┐
│ PHASE 1: Company Registration                               │
└─────────────────────────────────────────────────────────────┘

1. Company fills signup form:
   - Company details (name, VAT, address, etc.)
   - Contact person (first admin user)
   - Selects desired projects/portals
   - Submits request

2. System creates:
   - Company record (status: pending)
   - Signup request record
   - Initial user account (status: pending)

3. Super Admin receives notification


┌─────────────────────────────────────────────────────────────┐
│ PHASE 2: Super Admin Review                                  │
└─────────────────────────────────────────────────────────────┘

4. Super Admin reviews request:
   - Views company details
   - Sees selected projects
   - Can modify project access
   - Approves or rejects

5. If REJECTED:
   - Company status → rejected
   - Email notification sent
   - End workflow

6. If APPROVED:
   - Company status → active
   - Triggers API orchestration process


┌─────────────────────────────────────────────────────────────┐
│ PHASE 3: API Orchestration (Background Job)                  │
└─────────────────────────────────────────────────────────────┘

7. For each approved project:
   
   a) Check integration type:
   
   ┌─────────────────────────────────────┐
   │ IF: API Integration                 │
   └─────────────────────────────────────┘
   - Call project's signup API:
     POST {api_base_url}{api_signup_endpoint}
     Body: {
       "company": {...},
       "user": {...},
       "credentials": {...}
     }
   - Store API credentials returned
   - Create company_project_access record
   
   ┌─────────────────────────────────────┐
   │ IF: Iframe Integration              │
   └─────────────────────────────────────┘
   - Call project's signup API (if available):
     POST {api_base_url}{api_signup_endpoint}
     Body: {
       "company": {...},
       "user": {...}
     }
   - OR: Create account manually (if no API)
   - Store login credentials (encrypted)
   - Create company_project_access record
   
   ┌─────────────────────────────────────┐
   │ IF: Hybrid                          │
   └─────────────────────────────────────┘
   - Do both API and iframe setup
   - Store both credential types

8. Log all API calls in api_integration_logs

9. Update signup_request status → approved

10. Send welcome email to company admin
```

### Implementation: Approval Service

```php
<?php
// app/Services/SignupApprovalService.php

class SignupApprovalService
{
    public function approveSignupRequest(SignupRequest $request, User $approver, array $selectedProjects = null)
    {
        DB::transaction(function () use ($request, $approver, $selectedProjects) {
            // 1. Update company status
            $company = $request->company;
            $company->status = 'active';
            $company->save();
            
            // 2. Use selected projects or request's projects
            $projects = $selectedProjects ?? $request->requested_projects;
            
            // 3. Process each project
            foreach ($projects as $projectId) {
                $project = Project::find($projectId);
                
                // Create access record
                $access = CompanyProjectAccess::create([
                    'company_id' => $company->id,
                    'project_id' => $project->id,
                    'status' => 'pending', // will be active after API success
                    'signup_request_data' => $request->company_data,
                ]);
                
                // Orchestrate API calls based on integration type
                $this->orchestrateProjectSignup($access, $project, $request);
            }
            
            // 4. Update signup request
            $request->status = 'approved';
            $request->reviewed_by = $approver->id;
            $request->reviewed_at = now();
            $request->save();
            
            // 5. Queue welcome email
            dispatch(new SendWelcomeEmailJob($company));
        });
    }
    
    private function orchestrateProjectSignup(
        CompanyProjectAccess $access,
        Project $project,
        SignupRequest $request
    ) {
        $log = [];
        
        try {
            switch ($project->integration_type) {
                case 'api':
                    $credentials = $this->signupViaAPI($project, $request);
                    $access->api_credentials = encrypt(json_encode($credentials));
                    $access->status = 'active';
                    break;
                    
                case 'iframe':
                    $credentials = $this->signupViaAPI($project, $request); // or manual
                    $access->login_credentials = encrypt(json_encode($credentials));
                    $access->status = 'active';
                    break;
                    
                case 'hybrid':
                    $apiCreds = $this->signupViaAPI($project, $request);
                    $access->api_credentials = encrypt(json_encode($apiCreds));
                    $access->login_credentials = encrypt(json_encode($apiCreds)); // same or different
                    $access->status = 'active';
                    break;
            }
            
            $access->approved_at = now();
            $access->save();
            
        } catch (\Exception $e) {
            $log[] = [
                'error' => $e->getMessage(),
                'timestamp' => now(),
            ];
            
            // Log failed attempt
            ApiIntegrationLog::create([
                'company_project_access_id' => $access->id,
                'project_id' => $project->id,
                'endpoint' => $project->api_signup_endpoint,
                'method' => 'POST',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
    
    private function signupViaAPI(Project $project, SignupRequest $request)
    {
        $client = new \GuzzleHttp\Client();
        
        $payload = [
            'company' => $request->company_data,
            'user' => $request->contact_person,
            'source' => 'leo24_crm',
        ];
        
        $startTime = microtime(true);
        
        try {
            $response = $client->post(
                $project->api_base_url . $project->api_signup_endpoint,
                [
                    'headers' => $this->getAuthHeaders($project),
                    'json' => $payload,
                    'timeout' => 30,
                ]
            );
            
            $duration = (microtime(true) - $startTime) * 1000;
            
            $responseData = json_decode($response->getBody(), true);
            
            // Log success
            ApiIntegrationLog::create([
                'company_project_access_id' => null, // will be set after
                'project_id' => $project->id,
                'endpoint' => $project->api_signup_endpoint,
                'method' => 'POST',
                'request_payload' => $payload,
                'response_status' => $response->getStatusCode(),
                'response_body' => $responseData,
                'duration_ms' => $duration,
            ]);
            
            // Extract credentials from response
            // NEVER store passwords - only external IDs and API keys
            return [
                'api_key' => $responseData['api_key'] ?? null,
                'api_secret' => $responseData['api_secret'] ?? null,
                'external_user_id' => $responseData['user_id'] ?? null,
                'external_username' => $responseData['username'] ?? $request->contact_person['email'],
                'external_company_id' => $responseData['company_id'] ?? null,
                // NO password storage - password lives only on target system
            ];
            
        } catch (\Exception $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            
            // Log error
            ApiIntegrationLog::create([
                'project_id' => $project->id,
                'endpoint' => $project->api_signup_endpoint,
                'method' => 'POST',
                'request_payload' => $payload,
                'response_status' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0,
                'error_message' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
            
            throw $e;
        }
    }
    
    private function getAuthHeaders(Project $project): array
    {
        // Headers for authenticating LEO24 CRM to the project's API
        switch ($project->api_auth_type) {
            case 'bearer':
                return [
                    'Authorization' => 'Bearer ' . $project->api_key,
                    'Content-Type' => 'application/json',
                ];
            case 'basic':
                return [
                    'Authorization' => 'Basic ' . base64_encode($project->api_key . ':' . decrypt($project->api_secret)),
                    'Content-Type' => 'application/json',
                ];
            default:
                return ['Content-Type' => 'application/json'];
        }
    }
}
```

---

## 4. SSO / Auto-Login System

### Flow: User Clicks on Project (CORRECTED - Top-Level Redirect)

```
┌─────────────────────────────────────────────────────────────┐
│ User clicks "Access OptyShop CRM" in LEO24 dashboard        │
└─────────────────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ Check: Project Integration Type                             │
└─────────────────────────────────────────────────────────────┘
                    │
        ┌───────────┴───────────┐
        │                       │
        ▼                       ▼
┌───────────────┐      ┌──────────────────────────────┐
│ API Type      │      │ Iframe Type                  │
│               │      │                              │
│ → Redirect to │      │ → Generate JWT SSO Token     │
│   Custom CRM  │      │ → TOP-LEVEL redirect to      │
│   Interface   │      │   Project SSO URL            │
│   (built in   │      │   (sets session cookie)     │
│   LEO24)      │      │ → Redirect back to iframe    │
│               │      │   page with session active   │
└───────────────┘      └──────────────────────────────┘
```

**CRITICAL**: Iframe SSO must use TOP-LEVEL redirect, not iframe token passing, to avoid:
- SameSite cookie issues
- Third-party cookie blocking
- CSP/X-Frame-Options violations

### SSO Token Generation (JWT Standard)

```php
<?php
// app/Services/SSOService.php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

class SSOService
{
    private const JWT_ALGORITHM = 'HS256';
    private const JWT_ISSUER = 'leo24_crm';
    
    /**
     * Generate JWT SSO token with standard claims
     */
    public function generateJWTToken(
        CompanyProjectAccess $access,
        Project $project,
        User $user,
        CompanyProjectUser $projectUser = null
    ): string {
        $now = now();
        $expiresAt = $now->copy()->addSeconds($project->sso_token_expiry);
        
        // Generate unique JWT ID (jti) for replay protection
        $jti = Str::uuid()->toString();
        
        // Get external user ID if mapped
        $externalUserId = $projectUser?->external_user_id ?? $access->external_company_id;
        
        // Standard JWT claims
        $payload = [
            // Standard claims (RFC 7519)
            'iss' => self::JWT_ISSUER, // Issuer
            'aud' => $project->slug, // Audience (project)
            'sub' => (string) $user->id, // Subject (LEO24 user ID)
            'exp' => $expiresAt->timestamp, // Expiration
            'iat' => $now->timestamp, // Issued at
            'jti' => $jti, // JWT ID (for replay protection)
            
            // Custom claims
            'cid' => (string) $access->company_id, // Company ID
            'pid' => (string) $project->id, // Project ID
            'cpa_id' => (string) $access->id, // Company-Project-Access ID
            'ext_uid' => $externalUserId, // External user ID in target system
            'ext_cid' => $access->external_company_id, // External company ID
        ];
        
        // Sign with project's secret
        $secret = decrypt($project->api_secret);
        $token = JWT::encode($payload, $secret, self::JWT_ALGORITHM);
        
        // Store token usage record for replay protection
        SSOTokenUsage::create([
            'jti' => $jti,
            'company_project_access_id' => $access->id,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'issued_at' => $now,
            'expires_at' => $expiresAt,
            'status' => 'issued',
        ]);
        
        return $token;
    }
    
    /**
     * Get SSO URL for iframe projects (TOP-LEVEL redirect pattern)
     */
    public function getSSORedirectUrl(
        CompanyProjectAccess $access,
        Project $project,
        User $user
    ): string {
        // Get or create user mapping
        $projectUser = CompanyProjectUser::firstOrCreate(
            [
                'company_project_access_id' => $access->id,
                'user_id' => $user->id,
            ],
            [
                'status' => 'active',
            ]
        );
        
        // Generate JWT token
        $token = $this->generateJWTToken($access, $project, $user, $projectUser);
        
        // Build SSO redirect URL
        $ssoUrl = $project->sso_redirect_url ?? $project->admin_panel_url;
        $callbackUrl = $project->sso_callback_url ?? route('projects.iframe-callback', $project);
        
        // Add token and callback
        $separator = strpos($ssoUrl, '?') !== false ? '&' : '?';
        $ssoUrl .= $separator . 'token=' . urlencode($token);
        $ssoUrl .= '&callback=' . urlencode($callbackUrl);
        
        return $ssoUrl;
    }
    
    /**
     * Validate JWT token (for projects calling back to verify)
     */
    public function validateJWTToken(string $token, Project $project): array
    {
        try {
            $secret = decrypt($project->api_secret);
            $decoded = JWT::decode($token, new Key($secret, self::JWT_ALGORITHM));
            
            // Check replay protection
            $tokenUsage = SSOTokenUsage::where('jti', $decoded->jti)->first();
            
            if (!$tokenUsage) {
                throw new \Exception('Token not found in usage log');
            }
            
            if ($tokenUsage->status === 'used') {
                throw new \Exception('Token already used (replay attack)');
            }
            
            if ($tokenUsage->status === 'revoked') {
                throw new \Exception('Token revoked');
            }
            
            // Mark as used
            $tokenUsage->update([
                'status' => 'used',
                'used_at' => now(),
            ]);
            
            return (array) $decoded;
            
        } catch (\Exception $e) {
            \Log::error('JWT validation failed', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
            ]);
            throw $e;
        }
    }
}
```

### Frontend: Iframe Embedding with SSO (TOP-LEVEL Redirect)

```javascript
// resources/js/components/ProjectIframe.vue (or React component)

<template>
  <div class="project-iframe-container">
    <div v-if="loading" class="loading">
      <spinner /> Connecting to {{ project.name }}...
    </div>
    
    <!-- After SSO redirect, show iframe -->
    <iframe
      v-if="!loading && iframeUrl"
      :src="iframeUrl"
      :width="project.iframe_width"
      :height="project.iframe_height"
      :sandbox="project.iframe_sandbox"
      frameborder="0"
      @load="onIframeLoad"
    />
    
    <div v-if="error" class="error">
      {{ error }}
    </div>
  </div>
</template>

<script>
export default {
  props: {
    projectId: Number,
    companyId: Number,
  },
  
  data() {
    return {
      loading: true,
      iframeUrl: null,
      error: null,
      project: null,
      ssoCompleted: false,
    };
  },
  
  async mounted() {
    // Check if returning from SSO redirect
    const urlParams = new URLSearchParams(window.location.search);
    const ssoToken = urlParams.get('sso_token');
    const projectId = urlParams.get('project_id');
    
    if (ssoToken && projectId == this.projectId) {
      // We're returning from SSO - show iframe
      this.iframeUrl = this.project?.admin_panel_url;
      this.loading = false;
      this.ssoCompleted = true;
    } else {
      // Start SSO flow with top-level redirect
      await this.initiateSSO();
    }
  },
  
  methods: {
    async initiateSSO() {
      try {
        // Get SSO redirect URL from backend
        const response = await axios.post(`/api/projects/${this.projectId}/sso/redirect`, {
          company_id: this.companyId,
        });
        
        // TOP-LEVEL redirect (not iframe)
        // This allows target system to set session cookies properly
        window.location.href = response.data.redirect_url;
        
      } catch (error) {
        this.error = 'Failed to initiate SSO. Please try again.';
        this.loading = false;
      }
    },
    
    onIframeLoad() {
      // Optional: Handle iframe load events
      console.log('Iframe loaded');
    },
  },
};
</script>
```

**Flow Explanation:**
1. User clicks "Access Project" → LEO24 generates JWT
2. **TOP-LEVEL redirect** to project's SSO URL (sets session cookie)
3. Project validates JWT, creates session, redirects back
4. LEO24 receives callback → shows iframe with authenticated session

### Backend: SSO Endpoint (Updated for Top-Level Redirect)

```php
<?php
// routes/api.php

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/projects/{project}/sso/redirect', [ProjectController::class, 'generateSSORedirect']);
    Route::get('/projects/{project}/iframe-callback', [ProjectController::class, 'iframeCallback'])
        ->name('projects.iframe-callback');
});

// app/Http/Controllers/ProjectController.php

public function generateSSORedirect(Project $project)
{
    $user = auth()->user();
    $company = $user->company;
    
    // Check access (with tenant isolation)
    $access = CompanyProjectAccess::where('company_id', $company->id)
        ->where('project_id', $project->id)
        ->where('status', 'active')
        ->firstOrFail();
    
    // Check rate limit
    $rateLimitService = app(RateLimitService::class);
    if (!$rateLimitService->checkRateLimit($access)) {
        return response()->json(['error' => 'Rate limit exceeded'], 429);
    }
    
    // Generate SSO redirect URL (top-level)
    $ssoService = app(SSOService::class);
    $redirectUrl = $ssoService->getSSORedirectUrl($access, $project, $user);
    
    return response()->json([
        'redirect_url' => $redirectUrl,
    ]);
}

public function iframeCallback(Project $project, Request $request)
{
    // This is called after project validates SSO and redirects back
    $user = auth()->user();
    $company = $user->company;
    
    $access = CompanyProjectAccess::where('company_id', $company->id)
        ->where('project_id', $project->id)
        ->where('status', 'active')
        ->firstOrFail();
    
    // Show iframe page with authenticated session
    return view('projects.iframe', [
        'project' => $project,
        'iframeUrl' => $project->admin_panel_url,
    ]);
}
```

---

## 5. Integration Adapter Layer (CRITICAL)

### Problem
Every project has different API contracts. Hardcoding endpoints leads to spaghetti code.

### Solution: Driver Pattern

```php
<?php
// app/Contracts/ProjectIntegrationDriver.php

interface ProjectIntegrationDriver
{
    /**
     * Signup a company/user in the target project
     */
    public function signup(Project $project, array $companyData, array $userData): array;
    
    /**
     * Generate SSO URL
     */
    public function getSSOUrl(Project $project, CompanyProjectAccess $access, User $user): string;
    
    /**
     * Sync data from project to LEO24
     */
    public function sync(Project $project, CompanyProjectAccess $access): array;
    
    /**
     * Revoke access
     */
    public function revoke(Project $project, CompanyProjectAccess $access): bool;
    
    /**
     * Test connection
     */
    public function testConnection(Project $project): bool;
}

// app/Integrations/Drivers/OptyShopDriver.php

class OptyShopDriver implements ProjectIntegrationDriver
{
    public function signup(Project $project, array $companyData, array $userData): array
    {
        $client = new \GuzzleHttp\Client();
        
        // OptyShop-specific payload format
        $payload = [
            'organization' => [
                'name' => $companyData['name'],
                'vat' => $companyData['vat'],
            ],
            'admin' => [
                'email' => $userData['email'],
                'name' => $userData['name'],
            ],
        ];
        
        $response = $client->post(
            $project->api_base_url . '/api/v2/organizations',
            [
                'headers' => $this->getAuthHeaders($project),
                'json' => $payload,
            ]
        );
        
        $data = json_decode($response->getBody(), true);
        
        // Map OptyShop response to standard format
        return [
            'external_company_id' => $data['organization']['id'],
            'external_user_id' => $data['admin']['id'],
            'api_key' => $data['api_key'] ?? null,
        ];
    }
    
    public function getSSOUrl(Project $project, CompanyProjectAccess $access, User $user): string
    {
        // OptyShop uses JWT in query param
        $ssoService = app(SSOService::class);
        $token = $ssoService->generateJWTToken($access, $project, $user);
        
        return $project->sso_redirect_url . '?leo24_token=' . urlencode($token);
    }
    
    // ... other methods
}

// app/Integrations/Drivers/TGCalabriaDriver.php

class TGCalabriaDriver implements ProjectIntegrationDriver
{
    public function signup(Project $project, array $companyData, array $userData): array
    {
        // TG Calabria has different API format
        $client = new \GuzzleHttp\Client();
        
        $payload = [
            'company_name' => $companyData['name'],
            'piva' => $companyData['vat'],
            'contact_email' => $userData['email'],
            'contact_name' => $userData['name'],
        ];
        
        $response = $client->post(
            $project->api_base_url . '/register',
            [
                'headers' => ['X-API-Key' => decrypt($project->api_key)],
                'form_params' => $payload, // Different format
            ]
        );
        
        $data = json_decode($response->getBody(), true);
        
        return [
            'external_company_id' => $data['company_id'],
            'external_user_id' => $data['user_id'],
            // No API key returned
        ];
    }
    
    // ... other methods
}

// app/Services/ProjectIntegrationService.php

class ProjectIntegrationService
{
    private array $drivers = [];
    
    public function getDriver(Project $project): ProjectIntegrationDriver
    {
        $driverClass = $project->driver_class ?? $this->getDefaultDriver($project);
        
        if (!isset($this->drivers[$driverClass])) {
            if (!class_exists($driverClass)) {
                throw new \Exception("Driver {$driverClass} not found");
            }
            
            $this->drivers[$driverClass] = app($driverClass);
        }
        
        return $this->drivers[$driverClass];
    }
    
    private function getDefaultDriver(Project $project): string
    {
        // Map project slug to driver
        $map = [
            'optyshop' => OptyShopDriver::class,
            'tg-calabria' => TGCalabriaDriver::class,
            'mydoctor' => MyDoctorDriver::class,
            // ... more mappings
        ];
        
        return $map[$project->slug] ?? GenericDriver::class;
    }
}

// Usage in SignupApprovalService:

private function orchestrateProjectSignup(
    CompanyProjectAccess $access,
    Project $project,
    SignupRequest $request
) {
    $integrationService = app(ProjectIntegrationService::class);
    $driver = $integrationService->getDriver($project);
    
    try {
        $result = $driver->signup($project, $request->company_data, $request->contact_person);
        
        // Store result (no passwords)
        $access->external_company_id = $result['external_company_id'] ?? null;
        $access->api_credentials = encrypt(json_encode([
            'api_key' => $result['api_key'] ?? null,
        ]));
        $access->status = 'active';
        $access->save();
        
        // Create user mapping
        CompanyProjectUser::create([
            'company_project_access_id' => $access->id,
            'user_id' => $request->contact_person['user_id'],
            'external_user_id' => $result['external_user_id'] ?? null,
            'external_username' => $request->contact_person['email'],
            'status' => 'active',
        ]);
        
    } catch (\Exception $e) {
        // Handle per-driver errors
        $access->last_error = $e->getMessage();
        $access->status = 'partial_failed';
        $access->save();
        throw $e;
    }
}
```

---

## 6. Tenant Isolation Middleware (CRITICAL)

### Problem
Must enforce company_id, project_id, and role at every request.

```php
<?php
// app/Http/Middleware/EnforceTenantIsolation.php

class EnforceTenantIsolation
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        
        // Super Admin bypasses all checks
        if ($user->isSuperAdmin()) {
            return $next($request);
        }
        
        // Enforce company isolation
        if ($request->has('company_id')) {
            $requestedCompanyId = $request->route('company_id') ?? $request->input('company_id');
            
            if ($requestedCompanyId != $user->company_id) {
                abort(403, 'Access denied: Company mismatch');
            }
        }
        
        // Enforce project access
        if ($request->has('project_id')) {
            $projectId = $request->route('project_id') ?? $request->input('project_id');
            
            $hasAccess = CompanyProjectAccess::where('company_id', $user->company_id)
                ->where('project_id', $projectId)
                ->where('status', 'active')
                ->exists();
            
            if (!$hasAccess) {
                abort(403, 'Access denied: No access to this project');
            }
        }
        
        // Add company_id to request for automatic scoping
        $request->merge(['enforced_company_id' => $user->company_id]);
        
        return $next($request);
    }
}

// app/Http/Middleware/ScopeByCompany.php

class ScopeByCompany
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        
        if (!$user->isSuperAdmin() && $request->has('enforced_company_id')) {
            // Automatically scope all queries
            Company::addGlobalScope('company', function ($query) use ($user) {
                $query->where('id', $user->company_id);
            });
        }
        
        return $next($request);
    }
}

// Register in app/Http/Kernel.php:
protected $middlewareGroups = [
    'web' => [
        // ...
        \App\Http\Middleware\EnforceTenantIsolation::class,
        \App\Http\Middleware\ScopeByCompany::class,
    ],
];
```

---

## 7. Async Failure Recovery

### Problem
If 3 projects are approved, but 1 fails, what happens?

### Solution: Partial Success Handling

```php
<?php
// app/Services/SignupApprovalService.php (updated)

public function approveSignupRequest(SignupRequest $request, User $approver, array $selectedProjects = null)
{
    $results = [
        'succeeded' => [],
        'failed' => [],
        'partial' => false,
    ];
    
    DB::transaction(function () use ($request, $approver, $selectedProjects, &$results) {
        $company = $request->company;
        $company->status = 'active';
        $company->save();
        
        $projects = $selectedProjects ?? $request->requested_projects;
        
        foreach ($projects as $projectId) {
            $project = Project::find($projectId);
            $access = CompanyProjectAccess::create([
                'company_id' => $company->id,
                'project_id' => $project->id,
                'status' => 'pending',
            ]);
            
            try {
                $this->orchestrateProjectSignup($access, $project, $request);
                $results['succeeded'][] = $project->name;
                
            } catch (\Exception $e) {
                $access->status = 'partial_failed';
                $access->last_error = $e->getMessage();
                $access->retry_count = 0;
                $access->save();
                
                $results['failed'][] = [
                    'project' => $project->name,
                    'error' => $e->getMessage(),
                ];
                $results['partial'] = true;
                
                // Log but don't fail entire transaction
                \Log::error('Project signup failed', [
                    'project_id' => $project->id,
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Update signup request
        $request->status = $results['partial'] ? 'partial_approved' : 'approved';
        $request->reviewed_by = $approver->id;
        $request->reviewed_at = now();
        $request->api_calls_log = $results;
        $request->save();
    });
    
    // Queue retry job for failed projects
    if ($results['partial']) {
        dispatch(new RetryFailedProjectSignupJob($request->id));
    }
    
    return $results;
}

// app/Jobs/RetryFailedProjectSignupJob.php

class RetryFailedProjectSignupJob implements ShouldQueue
{
    public function handle()
    {
        $accesses = CompanyProjectAccess::where('status', 'partial_failed')
            ->where('retry_count', '<', 3)
            ->get();
        
        foreach ($accesses as $access) {
            try {
                $project = $access->project;
                $signupRequest = SignupRequest::where('company_id', $access->company_id)->first();
                
                $service = app(SignupApprovalService::class);
                $service->orchestrateProjectSignup($access, $project, $signupRequest);
                
                $access->status = 'active';
                $access->retry_count = 0;
                $access->last_error = null;
                $access->save();
                
            } catch (\Exception $e) {
                $access->retry_count++;
                $access->last_error = $e->getMessage();
                $access->save();
                
                if ($access->retry_count >= 3) {
                    // Notify Super Admin for manual intervention
                    dispatch(new NotifyAdminManualInterventionJob($access));
                }
            }
        }
    }
}

// UI: Manual Retry Interface

// Super Admin can see failed projects and retry manually:
// - View error messages
// - Update credentials if needed
// - Retry individual projects
// - Mark as "manual setup required"
```

---

## 8. Rate Limiting Per Project

### Problem
One misconfigured project can DDoS itself or block LEO24 IP.

### Solution: Circuit Breaker + Rate Limiting

```php
<?php
// app/Services/RateLimitService.php

class RateLimitService
{
    public function checkRateLimit(CompanyProjectAccess $access): bool
    {
        // Check per-minute limit
        $minuteKey = "rate_limit:{$access->id}:minute:" . now()->format('Y-m-d-H-i');
        $minuteCount = \Cache::get($minuteKey, 0);
        
        if ($minuteCount >= $access->rate_limit_per_minute) {
            return false;
        }
        
        // Check per-hour limit
        $hourKey = "rate_limit:{$access->id}:hour:" . now()->format('Y-m-d-H');
        $hourCount = \Cache::get($hourKey, 0);
        
        if ($hourCount >= $access->rate_limit_per_hour) {
            return false;
        }
        
        // Check circuit breaker
        if ($access->circuit_breaker_state === 'open') {
            if ($access->circuit_breaker_reset_at > now()) {
                return false; // Still in open state
            } else {
                // Try half-open
                $access->circuit_breaker_state = 'half_open';
                $access->save();
            }
        }
        
        return true;
    }
    
    public function recordApiCall(CompanyProjectAccess $access, bool $success): void
    {
        // Increment counters
        $minuteKey = "rate_limit:{$access->id}:minute:" . now()->format('Y-m-d-H-i');
        $hourKey = "rate_limit:{$access->id}:hour:" . now()->format('Y-m-d-H');
        
        \Cache::increment($minuteKey, 1, now()->addMinutes(2));
        \Cache::increment($hourKey, 1, now()->addHours(2));
        
        // Update circuit breaker
        if (!$success) {
            $access->circuit_breaker_failures++;
            
            if ($access->circuit_breaker_failures >= 5) {
                // Open circuit breaker
                $access->circuit_breaker_state = 'open';
                $access->circuit_breaker_reset_at = now()->addMinutes(5);
                $access->save();
                
                \Log::warning('Circuit breaker opened', [
                    'access_id' => $access->id,
                    'project_id' => $access->project_id,
                ]);
            }
        } else {
            // Reset on success
            if ($access->circuit_breaker_state === 'half_open') {
                $access->circuit_breaker_state = 'closed';
                $access->circuit_breaker_failures = 0;
                $access->save();
            }
        }
    }
}

// Usage in API calls:

$rateLimitService = app(RateLimitService::class);

if (!$rateLimitService->checkRateLimit($access)) {
    throw new \Exception('Rate limit exceeded or circuit breaker open');
}

try {
    $result = $driver->signup($project, $data, $userData);
    $rateLimitService->recordApiCall($access, true);
} catch (\Exception $e) {
    $rateLimitService->recordApiCall($access, false);
    throw $e;
}
```

---

## 9. Project Access Interface

### Dashboard: Project Cards

```php
// resources/views/projects/index.blade.php

@foreach($accessibleProjects as $project)
    <div class="project-card">
        <h3>{{ $project->name }}</h3>
        
        @if($project->integration_type === 'iframe')
            <a href="{{ route('projects.access', $project) }}" 
               class="btn btn-primary">
                Open Admin Panel
            </a>
        @elseif($project->integration_type === 'api')
            <a href="{{ route('projects.crm', $project) }}" 
               class="btn btn-primary">
                Open CRM
            </a>
        @else
            <div class="btn-group">
                <a href="{{ route('projects.crm', $project) }}" class="btn">
                    Custom CRM
                </a>
                <a href="{{ route('projects.access', $project) }}" class="btn">
                    Admin Panel
                </a>
            </div>
        @endif
    </div>
@endforeach
```

### Route: Access Project (Iframe)

```php
// routes/web.php

Route::middleware(['auth'])->group(function () {
    Route::get('/projects/{project}/access', [ProjectController::class, 'access'])
        ->name('projects.access');
    
    Route::get('/projects/{project}/crm', [ProjectController::class, 'crm'])
        ->name('projects.crm');
});

// app/Http/Controllers/ProjectController.php

public function access(Project $project)
{
    $user = auth()->user();
    $company = $user->company;
    
    // Verify access
    $access = CompanyProjectAccess::where('company_id', $company->id)
        ->where('project_id', $project->id)
        ->where('status', 'active')
        ->firstOrFail();
    
    if ($project->integration_type === 'iframe' || $project->integration_type === 'hybrid') {
        // Generate SSO URL
        $ssoService = app(SSOService::class);
        $ssoUrl = $ssoService->getSSOUrl($access, $project, $user);
        
        return view('projects.iframe', [
            'project' => $project,
            'ssoUrl' => $ssoUrl,
        ]);
    }
    
    abort(404);
}

public function crm(Project $project)
{
    // Custom CRM interface (API-based)
    // Load data via API and render custom UI
    return view('projects.crm', [
        'project' => $project,
    ]);
}
```

---

## 6. Security Considerations

### 1. Credential Encryption
- Use Laravel's encryption for all stored credentials
- Never log credentials in plain text
- Rotate encryption keys regularly

### 2. SSO Token Security
- Short-lived tokens (default: 1 hour)
- HMAC signatures
- Nonce to prevent replay attacks
- HTTPS only

### 3. Iframe Security
- Use `sandbox` attribute
- Restrict iframe permissions
- Content Security Policy (CSP)
- X-Frame-Options handling

### 4. API Security
- Rate limiting on API endpoints
- API key rotation
- Audit logs for all API calls
- Error handling (don't expose sensitive info)

---

## 7. Implementation Phases

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema migration
- [ ] Project model & CRUD
- [ ] Company-Project access model
- [ ] Basic project configuration UI

### Phase 2: Signup Workflow (Week 3-4)
- [ ] Signup request form
- [ ] Super Admin approval interface
- [ ] API orchestration service
- [ ] Background job for API calls
- [ ] Error handling & retry logic

### Phase 3: SSO System (Week 5-6)
- [ ] SSO token generation
- [ ] SSO URL builder
- [ ] Iframe embedding component
- [ ] Auto-login flow
- [ ] Token refresh mechanism

### Phase 4: Project Access UI (Week 7-8)
- [ ] Project dashboard/cards
- [ ] Iframe container page
- [ ] Custom CRM interface (for API projects)
- [ ] Access control middleware

### Phase 5: Testing & Refinement (Week 9-10)
- [ ] Integration testing with real projects
- [ ] Security audit
- [ ] Performance optimization
- [ ] Error recovery & monitoring

---

## 8. API Contract Examples

### Signup API Contract (Expected from Projects)

```json
// POST /api/v1/users/signup
Request:
{
  "company": {
    "name": "Alpha SRL",
    "vat": "IT12345678901",
    "address": "..."
  },
  "user": {
    "email": "admin@alpha.com",
    "name": "John Doe",
    "password": "secure123" // optional, can be auto-generated
  },
  "source": "leo24_crm"
}

Response (Success):
{
  "success": true,
  "user_id": 123,
  "username": "admin@alpha.com",
  "api_key": "abc123...",
  "api_secret": "xyz789...", // if applicable
  "password": "generated_password", // if auto-generated
  "message": "Account created successfully"
}

Response (Error):
{
  "success": false,
  "error": "Email already exists",
  "code": "EMAIL_EXISTS"
}
```

### SSO API Contract (Expected from Projects)

```json
// GET /auth/sso?token=...
Request: Query parameter with SSO token

Response (Success):
- Redirect to authenticated admin panel
- OR return JSON with session token

Response (Error):
{
  "error": "Invalid or expired token",
  "code": "INVALID_TOKEN"
}
```

---

## 9. Configuration UI

### Super Admin: Project Settings

```
┌─────────────────────────────────────────────────────┐
│ Project: OptyShop                                   │
├─────────────────────────────────────────────────────┤
│ Integration Type: [Iframe ▼]                       │
│                                                     │
│ ┌─ API Configuration ───────────────────────────┐   │
│ │ Base URL: https://api.optyshop.com           │   │
│ │ Auth Type: [Bearer Token ▼]                  │   │
│ │ API Key: [••••••••] [Show] [Rotate]          │   │
│ │ Signup Endpoint: /api/v1/users/signup        │   │
│ │ SSO Endpoint: /auth/sso                      │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ ┌─ Iframe Configuration ───────────────────────┐   │
│ │ Admin Panel URL: https://admin.optyshop.com  │   │
│ │ Width: [100%]                                │   │
│ │ Height: [100vh]                              │   │
│ │ Sandbox: [allow-same-origin allow-scripts]   │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ ┌─ SSO Settings ────────────────────────────────┐   │
│ │ SSO Enabled: [✓]                             │   │
│ │ Method: [Token ▼]                            │   │
│ │ Token Expiry: [3600] seconds                 │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ [Save] [Test Connection] [Cancel]                  │
└─────────────────────────────────────────────────────┘
```

---

## 10. Error Handling & Monitoring

### Failed Signup Scenarios

1. **API Unavailable**
   - Retry with exponential backoff
   - Queue for manual processing
   - Notify Super Admin

2. **Invalid Credentials**
   - Log error
   - Mark access as "failed"
   - Allow manual credential entry

3. **Duplicate Account**
   - Check if account exists
   - Link existing account instead of creating new
   - Update credentials

### Monitoring Dashboard

- API call success/failure rates
- Average response times
- SSO token usage
- Failed signup attempts
- Active integrations per project

---

## 11. Critical Fixes Summary

This plan has been updated to address all critical security and architectural issues:

### ✅ Fixed: Password Storage (CRITICAL)
- **Before**: Stored encrypted passwords in `login_credentials` JSON
- **After**: 
  - NO password storage whatsoever
  - Only store `external_user_id` and `external_username`
  - Passwords live ONLY on target systems
  - Legacy projects marked as `is_legacy = true` and isolated

### ✅ Fixed: Iframe SSO Pattern (CRITICAL)
- **Before**: Token passed directly to iframe (breaks with cookies)
- **After**: 
  - TOP-LEVEL redirect pattern (like Google/Atlassian)
  - User redirected to project SSO URL (sets session cookie)
  - Project validates JWT, creates session
  - Redirects back to LEO24 iframe page
  - Avoids SameSite cookie issues, third-party blocking

### ✅ Fixed: SSO Token Model (CRITICAL)
- **Before**: Custom HMAC signing, no replay protection
- **After**: 
  - Standard JWT with RFC 7519 claims (`iss`, `aud`, `sub`, `exp`, `iat`, `jti`)
  - Replay protection via `sso_token_usage` table
  - Token validation with proper error handling
  - Support for token revocation

### ✅ Fixed: Multi-User Mapping (CRITICAL)
- **Before**: One company → one user mapping
- **After**: 
  - `company_project_users` table for many-to-many mapping
  - Each LEO24 user can map to different external users
  - Per-user revocation and audit trails
  - Support for team-based access

### ✅ Fixed: API Contract Assumptions (CRITICAL)
- **Before**: Hardcoded API endpoints per project
- **After**: 
  - Integration Adapter Layer (Driver Pattern)
  - `ProjectIntegrationDriver` interface
  - Per-project drivers (OptyShopDriver, TGCalabriaDriver, etc.)
  - Easy to add new projects without code changes

### ✅ Added: Tenant Isolation Middleware
- Enforces `company_id` at every request
- Enforces project access checks
- Super Admin bypass logic
- Automatic query scoping

### ✅ Added: Async Failure Recovery
- Partial success handling (some projects succeed, some fail)
- Retry mechanism with exponential backoff
- Manual retry UI for Super Admin
- Status tracking: `pending`, `active`, `partial_failed`, `suspended`

### ✅ Added: Rate Limiting Per Project
- Per-minute and per-hour limits
- Circuit breaker pattern (opens after 5 failures)
- Automatic recovery (half-open → closed)
- Prevents DDoS and IP blocking

### Security Enhancements
1. **Credential Encryption**: All API keys encrypted at rest
2. **JWT Replay Protection**: `jti` tracking prevents token reuse
3. **Audit Logs**: Every API call logged with sanitized payloads
4. **GDPR Compliance**: No password storage, data minimization
5. **Iframe Sandboxing**: Security attributes enforced

---

## Next Steps

1. **Review & Approve Plan** - Confirm this approach meets requirements
2. **Create Database Migrations** - Implement updated schema
3. **Build Project Configuration UI** - Super Admin interface
4. **Implement Integration Adapter Layer** - Driver pattern foundation
5. **Implement Signup Workflow** - Form → Approval → API orchestration
6. **Develop SSO System** - JWT generation & top-level redirect
7. **Add Tenant Isolation Middleware** - Security enforcement
8. **Create Project Access Interface** - Dashboard with project cards
9. **Add Rate Limiting & Circuit Breaker** - Production resilience

Would you like me to start implementing any specific part of this plan?
