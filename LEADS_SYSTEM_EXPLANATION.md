# How Leads Work in LEO24 CRM

## 🎯 Core Concept: Leads = Customers + Opportunities

**Important:** This system does NOT have a separate "leads" table. Instead, **leads are a VIEW/REPRESENTATION** of:
- **Customer** (the person/company) 
- **Opportunity** (the sales opportunity/deal)

## 📊 Data Model Architecture

```
Customer (Base Entity)
    ├── Has many Opportunities
    ├── Has contact info (email, phone, name, VAT)
    └── Belongs to Company (multi-tenant)

Opportunity (Sales Deal)
    ├── Belongs to Customer
    ├── Has Stage (prospecting, qualification, proposal, etc.)
    ├── Has Value & Probability
    ├── Can be linked to Project (source)
    └── Can be assigned to User
```

## 🔄 Lead Lifecycle

### 1. **Creating a Lead**

When you create a lead via `POST /api/leads`:

```php
// Input: Lead data (name, email, phone, status, value, source)
{
  "name": "Paolo Neri",
  "email": "paolo@example.com",
  "phone": "+39 333 123 4567",
  "status": "hot",        // hot, warm, cold, converted
  "value": 390,
  "source": "OptyShop",
  "assigned_to": 5,
  "project_id": 1
}
```

**What Happens:**
1. **Customer Deduplication**: System checks if customer already exists (by email/phone/VAT)
   - If exists → Uses existing customer
   - If new → Creates new customer
2. **Creates Opportunity**: Automatically creates an Opportunity linked to the customer
3. **Status Mapping**: Lead status maps to Opportunity stage:
   - `hot` → `prospecting`
   - `warm` → `proposal`
   - `cold` → `on_hold`
   - `converted` → `closed_won`

### 2. **Viewing Leads**

When you fetch leads via `GET /api/leads`:

**What Happens:**
1. Fetches all **Customers** for the company
2. For each customer, gets their **primary open Opportunity** (latest, not closed)
3. Transforms into "lead" format:
   ```json
   {
     "id": 1,
     "name": "Paolo Neri",
     "email": "paolo@example.com",
     "phone": "+39 333 123 4567",
     "source": "OptyShop",           // From opportunity.project.name or opportunity.source
     "status": "hot",                 // Mapped from opportunity.stage
     "value": 390,                    // From opportunity.value
     "assigned_to": "Mario Rossi",    // From opportunity.assignee.name
     "created_at": "2024-01-15",
     "opportunity_id": 5
   }
   ```

### 3. **Lead Status Mapping**

| Lead Status | Opportunity Stage | Meaning |
|------------|------------------|---------|
| `hot` | `prospecting`, `qualification` | Active, interested lead |
| `warm` | `proposal`, `negotiation` | In discussion, considering |
| `cold` | `on_hold`, `closed_lost` | Not interested or paused |
| `converted` | `closed_won` | Successfully converted to sale |

## 🗄️ Database Structure

### Customers Table
```sql
- id
- company_id (multi-tenant)
- email (unique)
- phone (unique)
- vat (unique)
- first_name
- last_name
- address
- notes
- created_at, updated_at, deleted_at
```

### Opportunities Table
```sql
- id
- company_id (multi-tenant)
- customer_id (FK to customers)
- project_id (FK to projects - source)
- created_by (FK to users)
- assigned_to (FK to users)
- name
- description
- stage (prospecting, qualification, proposal, negotiation, closed_won, closed_lost, on_hold)
- value (decimal)
- probability (0-100)
- weighted_value (value * probability / 100)
- source (string - where lead came from)
- campaign (string)
- expected_close_date
- closed_at
- loss_reason
```

## 🔍 Key Features

### 1. **Deduplication**
- When creating a lead, system checks for existing customers
- Prevents duplicate customers across all portals
- Uses `CustomerDeduplicationService` to find or create

### 2. **Source Tracking**
Lead source comes from:
1. **Project name** (if opportunity is linked to a project)
2. **Opportunity.source** field (direct source)
3. **"Direct"** (if neither exists)

### 3. **Multi-Tenant Isolation**
- All leads are scoped by `company_id`
- Super Admin can see all companies' leads
- Regular users only see their company's leads

### 4. **Assignment**
- Leads can be assigned to users via `assigned_to`
- Shows assignee name in lead list

## 📈 Dashboard Integration

### Lead KPIs
- **New Leads**: Count of customers created in last period
- **Open Opportunities**: Count of open opportunities
- **Sales Count**: Count of closed_won opportunities
- **Sales Value**: Sum of closed_won opportunity values

### Lead Sources Chart
Shows leads grouped by source (project name or direct source)

## 🔄 Lead → Opportunity → Sale Flow

```
1. Lead Created
   ↓
2. Customer Created/Found (deduplication)
   ↓
3. Opportunity Created (stage: prospecting)
   ↓
4. Lead Status: "hot"
   ↓
5. Opportunity Moves Through Stages:
   - prospecting → qualification → proposal → negotiation
   ↓
6. If Won: stage = closed_won, Lead Status = "converted"
   If Lost: stage = closed_lost, Lead Status = "cold"
```

## 💡 Why This Design?

### Advantages:
1. **Single Source of Truth**: Customer data is centralized
2. **No Duplication**: Deduplication prevents duplicate customers
3. **Flexible**: One customer can have multiple opportunities
4. **Trackable**: Full history of all opportunities per customer
5. **Multi-Portal**: Customer can come from any project/portal

### Example Scenario:
```
Customer: "Paolo Neri"
├── Opportunity 1: From OptyShop (€390) - closed_won
├── Opportunity 2: From TGImmobiliare (€1200) - negotiation
└── Opportunity 3: From MyDoctor+ (€500) - prospecting

When viewing leads:
- Shows Paolo Neri as ONE lead
- Status = "warm" (from latest open opportunity)
- Value = €1200 (from opportunity in negotiation)
- Source = "TGImmobiliare" (from latest open opportunity)
```

## 🎯 API Endpoints

### Get All Leads
```
GET /api/leads
Query Params:
  - search: Search by name, email, phone
  - status: Filter by hot/warm/cold/converted
  - source: Filter by source/project
```

### Create Lead
```
POST /api/leads
Body: {
  name, email, phone, status, value, source, assigned_to, project_id
}
```

### Update Lead
```
PUT /api/leads/{customerId}
Body: {
  name, email, phone, status, value, source, assigned_to, project_id
}
```

### Delete Lead
```
DELETE /api/leads/{customerId}
```

## 📝 Summary

**Leads are NOT a separate entity** - they are a **virtual representation** of:
- **Customer** (who they are)
- **Opportunity** (the deal/sale)

This design ensures:
- ✅ No duplicate customers
- ✅ Full sales history per customer
- ✅ Multi-portal integration
- ✅ Single source of truth
- ✅ Flexible opportunity tracking
