# LEO24 CRM - Multi-Tenant Enterprise CRM System

A comprehensive multi-tenant CRM system with React frontend and Laravel backend, featuring project integration, SSO, and modular architecture.

## Technology Stack

### Backend
- Laravel 11
- MySQL 8.0+
- Laravel Sanctum (API authentication)
- Database Queue (no Redis required)
- Database/File Cache

### Frontend
- React 18+ with Vite
- TypeScript
- Tailwind CSS
- Zustand (state management)
- React Router v6
- Axios

## Project Structure

```
saad-crm/
├── backend/          # Laravel API
├── frontend/         # React + Vite
└── requirements/     # Client requirements
```

## Setup Instructions

### Backend Setup

1. **Navigate to backend directory:**
   ```bash
   cd backend
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment:**
   Copy `.env.example` to `.env` and configure:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=leo24_crm
   DB_USERNAME=root
   DB_PASSWORD=

   QUEUE_CONNECTION=database
   CACHE_DRIVER=database

   APP_URL=http://localhost:8000
   ```

4. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

5. **Run migrations:**
   ```bash
   php artisan migrate
   ```

6. **Start the server:**
   ```bash
   php artisan serve
   ```

### Frontend Setup

1. **Navigate to frontend directory:**
   ```bash
   cd frontend
   ```

2. **Install dependencies:**
   ```bash
   npm install
   ```

3. **Configure environment:**
   Create `.env` file:
   ```env
   VITE_API_URL=http://localhost:8000/api
   ```

4. **Start development server:**
   ```bash
   npm run dev
   ```

## Features

### Core Features
- Multi-tenant architecture with company isolation
- Role-based access control (Super Admin, Company Admin, Manager, Staff, Read-only)
- Project integration system (API, Iframe, Hybrid)
- SSO with JWT tokens and replay protection
- Customer deduplication (single source of truth)
- Signup approval workflow with API orchestration
- Rate limiting and circuit breaker
- Audit logging

### Integration Types
1. **API Integration**: Full API control, custom CRM interface
2. **Iframe Integration**: Embed existing admin panels with top-level redirect SSO
3. **Hybrid**: Both API and iframe capabilities

## API Endpoints

### Authentication
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Current user

### Companies
- `GET /api/companies` - List companies
- `POST /api/companies` - Create company (super admin only)
- `GET /api/companies/{id}` - Get company
- `PUT /api/companies/{id}` - Update company
- `DELETE /api/companies/{id}` - Delete company

### Projects
- `GET /api/projects` - List accessible projects
- `GET /api/projects/{id}` - Get project details
- `POST /api/projects/{id}/sso/redirect` - Generate SSO URL

### Customers
- `GET /api/customers` - List customers
- `POST /api/customers` - Create customer (with deduplication)
- `PUT /api/customers/{id}` - Update customer
- `POST /api/customers/merge` - Merge duplicates

### Signup Requests
- `GET /api/signup-requests` - List requests (super admin)
- `POST /api/signup-requests` - Create request
- `PUT /api/signup-requests/{id}/approve` - Approve request
- `PUT /api/signup-requests/{id}/reject` - Reject request

### Dashboard
- `GET /api/dashboard/kpis` - KPI data
- `GET /api/dashboard/pipeline` - Pipeline data
- `GET /api/dashboard/leads` - Hot leads

## Database Schema

### Core Tables
- `companies` - Multi-tenant root
- `users` - All system users with roles
- `projects` - Available projects/portals
- `company_project_access` - Company-Project mapping with credentials
- `company_project_users` - Multi-user mapping per project
- `customers` - Single source of truth (deduplicated)
- `signup_requests` - Company registration queue
- `sso_token_usage` - JWT replay protection
- `api_integration_logs` - Audit trail

## Security Features

1. **Password Storage**: Never stores passwords, only external_user_ids
2. **JWT Security**: Standard claims with replay protection
3. **API Encryption**: All credentials encrypted at rest
4. **Tenant Isolation**: Enforced at middleware and model level
5. **Rate Limiting**: Per-project limits with circuit breaker
6. **Audit Logs**: All API calls logged (sanitized)

## Development

### Running Queue Worker
```bash
php artisan queue:work
```

### Running Tests
```bash
php artisan test
```

## License

Proprietary - All rights reserved
# superCrm
