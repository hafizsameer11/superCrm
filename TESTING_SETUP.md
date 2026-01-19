# Testing Setup - LEO24 CRM

## ✅ Test Suite Implementation Complete

A comprehensive test suite has been created for the LEO24 CRM system.

---

## 📁 Test Structure

### Base Test Case (`backend/tests/TestCase.php`)
Enhanced with helper methods:
- `createSuperAdmin()` - Create super admin user
- `createCompanyAdmin()` - Create company admin user
- `createUser()` - Create regular user
- `createCompany()` - Create active company
- `actingAsUser()` - Authenticate as user
- `actingAsSuperAdmin()` - Authenticate as super admin
- `actingAsCompanyAdmin()` - Authenticate as company admin

### Feature Tests Created

#### 1. **AuthTest** (`backend/tests/Feature/AuthTest.php`)
Tests authentication functionality:
- ✅ User can login with valid credentials
- ✅ User cannot login with invalid credentials
- ✅ User cannot login with inactive account
- ✅ User can logout
- ✅ User can get current user
- ✅ Unauthenticated user cannot access protected routes
- ✅ Login requires email and password

#### 2. **MultiTenantIsolationTest** (`backend/tests/Feature/MultiTenantIsolationTest.php`)
Tests multi-tenant isolation:
- ✅ User can only see own company customers
- ✅ User cannot access other company customers
- ✅ Super admin can access all companies
- ✅ Super admin can filter by company_id
- ✅ User cannot create customer for other company

#### 3. **CustomerTest** (`backend/tests/Feature/CustomerTest.php`)
Tests customer management:
- ✅ User can list customers
- ✅ User can create customer
- ✅ User can update customer
- ✅ User can view customer
- ✅ User can search customers
- ✅ Customer creation requires email
- ✅ Customer email must be unique

#### 4. **CompanyTest** (`backend/tests/Feature/CompanyTest.php`)
Tests company management:
- ✅ Super admin can list companies
- ✅ Regular user cannot list companies
- ✅ Super admin can create company
- ✅ Super admin can update company
- ✅ Super admin can delete company
- ✅ Company creation requires name

#### 5. **ProjectTest** (`backend/tests/Feature/ProjectTest.php`)
Tests project management:
- ✅ User can list accessible projects
- ✅ Super admin can see all projects
- ✅ User can view accessible project
- ✅ User cannot view inaccessible project
- ✅ Super admin can create project
- ✅ Regular user cannot create project

---

## 🏭 Model Factories Created

### 1. **CompanyFactory** (`backend/database/factories/CompanyFactory.php`)
- Default state with active company
- `pending()` - Create pending company
- `inactive()` - Create inactive company

### 2. **CustomerFactory** (`backend/database/factories/CustomerFactory.php`)
- Default state with all customer fields
- Automatically creates associated company

### 3. **ProjectFactory** (`backend/database/factories/ProjectFactory.php`)
- Default state with all project fields
- `api()` - Create API integration project
- `iframe()` - Create iframe integration project
- `hybrid()` - Create hybrid integration project
- `inactive()` - Create inactive project

### 4. **CompanyProjectAccessFactory** (`backend/database/factories/CompanyProjectAccessFactory.php`)
- Default state with active access
- `pending()` - Create pending access
- `suspended()` - Create suspended access
- `partialFailed()` - Create partial failed access

### 5. **UserFactory** (Updated)
- Added `company_id`, `role`, `permissions`, `status` fields
- `superAdmin()` - Create super admin user
- `companyAdmin()` - Create company admin user
- `inactive()` - Create inactive user

---

## 🚀 Running Tests

### Run All Tests
```bash
cd backend
php artisan test
```

### Run Specific Test Suite
```bash
# Run only feature tests
php artisan test --testsuite=Feature

# Run only unit tests
php artisan test --testsuite=Unit
```

### Run Specific Test File
```bash
php artisan test tests/Feature/AuthTest.php
```

### Run Specific Test Method
```bash
php artisan test --filter test_user_can_login_with_valid_credentials
```

### Run Tests with Coverage
```bash
php artisan test --coverage
```

---

## 📊 Test Coverage

### Current Coverage
- ✅ Authentication (7 tests)
- ✅ Multi-tenant isolation (5 tests)
- ✅ Customer management (7 tests)
- ✅ Company management (6 tests)
- ✅ Project management (6 tests)

**Total: 31+ feature tests**

### Areas Covered
- ✅ Authentication flow
- ✅ Authorization (role-based access)
- ✅ Multi-tenant data isolation
- ✅ CRUD operations
- ✅ Validation
- ✅ Search and filtering
- ✅ Super admin privileges

---

## 🔧 Test Configuration

### PHPUnit Configuration (`backend/phpunit.xml`)
- Uses SQLite in-memory database for tests
- Automatic database refresh between tests
- Test environment variables configured
- Code coverage enabled

### Test Database
- **Connection:** SQLite
- **Database:** `:memory:` (in-memory)
- **Refresh:** Automatic (RefreshDatabase trait)

---

## 📝 Writing New Tests

### Example: Creating a New Feature Test

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class MyFeatureTest extends TestCase
{
    public function test_something(): void
    {
        // Arrange
        $user = $this->createUser();
        $this->actingAsUser($user);

        // Act
        $response = $this->getJson('/api/some-endpoint');

        // Assert
        $response->assertStatus(200);
    }
}
```

### Using Factories

```php
// Create a company
$company = Company::factory()->create();

// Create a company with specific state
$company = Company::factory()->pending()->create();

// Create multiple customers
Customer::factory()->count(5)->create(['company_id' => $company->id]);
```

### Using Helper Methods

```php
// Create and authenticate as super admin
$this->actingAsSuperAdmin();

// Create and authenticate as company admin
$company = $this->createCompany();
$this->actingAsCompanyAdmin($company);

// Create and authenticate as regular user
$user = $this->createUser();
$this->actingAsUser($user);
```

---

## ✅ Next Steps

### Recommended Additional Tests

1. **SignupRequestTest**
   - Test signup request creation
   - Test approval workflow
   - Test rejection workflow
   - Test API orchestration

2. **SSOTest**
   - Test SSO token generation
   - Test SSO redirect flow
   - Test JWT replay protection
   - Test iframe callback

3. **OpportunityTest**
   - Test opportunity CRUD
   - Test pipeline stages
   - Test conversion
   - Test value calculations

4. **TaskTest**
   - Test task CRUD
   - Test task assignment
   - Test task completion
   - Test recurring tasks

5. **DocumentTest**
   - Test document upload
   - Test document download
   - Test document versioning
   - Test access control

6. **Integration Tests**
   - Test full signup approval flow
   - Test SSO flow end-to-end
   - Test customer deduplication
   - Test rate limiting

---

## 🐛 Troubleshooting

### Tests Failing Due to Database Issues
```bash
# Clear test database
php artisan migrate:fresh --env=testing

# Run migrations
php artisan migrate --env=testing
```

### Tests Failing Due to Missing Factories
- Ensure all models have corresponding factories
- Check factory names match model names
- Verify factories are in `database/factories` directory

### Tests Failing Due to Authentication
- Ensure using `actingAsUser()` or similar helpers
- Check Sanctum is properly configured
- Verify middleware is not blocking requests

---

## 📚 Resources

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Laravel Factories](https://laravel.com/docs/eloquent-factories)

---

*Last Updated: 2026-01-16*

