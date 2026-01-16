# LEO24 CRM - Setup Guide

## Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8.0+
- npm or yarn

### Backend Setup

1. **Navigate to backend:**
   ```bash
   cd backend
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   ```
   
   Edit `.env` and set:
   ```env
   DB_CONNECTION=mysql
   DB_DATABASE=leo24_crm
   DB_USERNAME=root
   DB_PASSWORD=your_password
   
   QUEUE_CONNECTION=database
   CACHE_DRIVER=database
   
   FRONTEND_URL=http://localhost:5173
   ```

4. **Generate key and run migrations:**
   ```bash
   php artisan key:generate
   php artisan migrate
   php artisan db:seed
   ```

5. **Start server:**
   ```bash
   php artisan serve
   ```

6. **Start queue worker (in separate terminal):**
   ```bash
   php artisan queue:work
   ```

### Frontend Setup

1. **Navigate to frontend:**
   ```bash
   cd frontend
   ```

2. **Install dependencies:**
   ```bash
   npm install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   ```
   
   Edit `.env`:
   ```env
   VITE_API_URL=http://localhost:8000/api
   ```

4. **Start development server:**
   ```bash
   npm run dev
   ```

## Default Login

After running `php artisan db:seed`:
- **Email:** admin@leo24.com
- **Password:** password

## Project Structure

- `backend/` - Laravel API
- `frontend/` - React + Vite application
- `requirements/` - Client requirements and documentation

## Key Features Implemented

✅ Multi-tenant architecture with company isolation
✅ Role-based access control (5 roles)
✅ Project integration system (API/Iframe/Hybrid)
✅ SSO with JWT and replay protection
✅ Customer deduplication
✅ Signup approval workflow
✅ Rate limiting and circuit breaker
✅ Audit logging
✅ Database queue (no Redis required)
✅ MySQL database

## Next Steps

1. Configure your MySQL database
2. Run migrations and seeders
3. Start backend and frontend servers
4. Login with super admin credentials
5. Create projects and configure integrations
6. Test signup workflow

## API Documentation

All API endpoints are documented in `/backend/routes/api.php`

Base URL: `http://localhost:8000/api`

## Troubleshooting

### CORS Issues
- Ensure `FRONTEND_URL` in backend `.env` matches your frontend URL
- Check `config/cors.php` settings

### Database Connection
- Verify MySQL is running
- Check database credentials in `.env`
- Ensure database exists: `CREATE DATABASE leo24_crm;`

### Queue Not Processing
- Make sure queue worker is running: `php artisan queue:work`
- Check `QUEUE_CONNECTION=database` in `.env`
