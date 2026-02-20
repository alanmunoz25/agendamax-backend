# Multi-Tenant Operations Guide

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [User Roles](#user-roles)
- [User Management](#user-management)
- [Business Management](#business-management)
- [Service Import](#service-import)
- [Seeder Reference](#seeder-reference)
- [Default Credentials](#default-credentials)
- [Common Operations](#common-operations)

---

## Architecture Overview

Crezer uses a **single-database multi-tenant** architecture. All tenants share the same database, with data isolation enforced by `business_id` on every tenant-scoped model.

### How Isolation Works

1. **Global Scope** (`BelongsToBusinessScope`): Automatically filters queries by `business_id` of the authenticated user.
2. **Super Admin Bypass**: Users with `role = super_admin` and `business_id = null` bypass the global scope and see **all** records.
3. **Policies**: Every controller uses Laravel Policies to authorize access based on role and business ownership.
4. **Middleware** (`EnsureUserHasBusiness`): Blocks access to tenant-scoped routes for users without a `business_id`. Super admins are exempt.

### Models Using Multi-Tenant Scope

All of these models have the `BelongsToBusinessScope` and require a `business_id`:

- `Appointment`
- `Employee`
- `Service`
- `ServiceCategory`
- `QrCode`
- `Visit`
- `Stamp`
- `Offer`
- `EmployeeSchedule`

---

## User Roles

| Role | `business_id` | Scope | Description |
|------|--------------|-------|-------------|
| `super_admin` | `null` | Platform-wide | Can manage all businesses, users, and data |
| `business_admin` | Set | Own business | Can manage their own business, employees, services, and clients |
| `employee` | Set | Own business (read-only) | Can view appointments and services |
| `client` | Set | Own data | End user of the mobile app |

### Role Hierarchy

```
super_admin (platform owner)
  └── Can create/manage ALL roles, ALL businesses

business_admin (business owner)
  └── Can create/manage: employee, client (within their business only)

employee (service provider)
  └── Read-only access to appointments and services

client (end user)
  └── Mobile app access only
```

---

## User Management

### Web Interface

- **URL**: `/users` (accessible to `super_admin` and `business_admin`)
- **Create User**: `/users/create`
- **Edit User**: `/users/{id}/edit`

### Creating Users

#### As Super Admin
- Can create users with **any role**: `super_admin`, `business_admin`, `employee`, `client`
- Can assign users to **any business** or no business
- Access from sidebar: **Users > Create User**

#### As Business Admin
- Can create users with roles: `employee`, `client`
- Users are **automatically assigned** to the business admin's own business
- Cannot create `super_admin` or `business_admin` users

### Required Fields for User Creation

| Field | Required | Notes |
|-------|----------|-------|
| `name` | Yes | Full name, max 255 chars |
| `email` | Yes | Must be unique across the platform |
| `password` | Yes | Minimum 8 characters |
| `role` | Yes | Must be within allowed roles for your role |
| `business_id` | No | Super admin only; business admin's is auto-set |
| `phone` | No | Optional phone number |

### Editing Users

- **Super admin** can change: role, business assignment
- **Business admin** can change: role only (`employee` or `client`), cannot change business assignment
- User info (name, email, phone) is **read-only** in the edit view

---

## Business Management

### Web Interface (Super Admin Only)

- **List all businesses**: `/businesses`
- **Create business**: `/businesses/create`
- **View business**: `/businesses/{id}`
- **Edit business**: `/businesses/{id}/edit`

### Creating a New Business

1. Login as `super_admin`
2. Go to **Businesses** in the sidebar
3. Click **Create Business**
4. Fill in: name, slug (auto-generated), email, phone, address, status, timezone, loyalty settings
5. An `invitation_code` is auto-generated if not provided

### After Creating a Business

After creating a new business, you typically need to:

1. **Create a business_admin user** for it (see [User Management](#user-management))
2. **Import services** (see [Service Import](#service-import))
3. **Create employees** through the web interface (login as the business_admin)

---

## Service Import

There are **two ways** to import services into a business:

### Method 1: CSV Seeder (from existing price list)

Uses the CSV file at `database/seeders/data/price-structure.csv`.

```bash
# Import to a specific business (e.g., business_id = 4)
ddev exec "cd backend && php artisan services:seed --business-id=4"

# Import to the default business (ID 2)
ddev exec "cd backend && php artisan services:seed"
```

The CSV seeder:
- Creates service categories (parent + subcategories) from the CSV structure
- Creates services with prices from the CSV
- Uses `updateOrCreate` so it's safe to re-run (idempotent)
- Default duration: 30 minutes per service

### Method 2: JSON Import Command

For importing from a custom JSON file:

```bash
# Import from a JSON file
ddev exec "cd backend && php artisan services:import path/to/services.json --business-id=4"
```

The JSON file should follow the format expected by `ServiceImportService`.

### Method 3: Web Interface

Login as the `business_admin` for the target business, then use **Services > Create Service** to add services one by one.

---

## Seeder Reference

### DatabaseSeeder

Seeds the base data for development:

```bash
ddev exec "cd backend && php artisan db:seed"
```

Creates:
- 2 businesses (Test Barber Shop, Elite Salon)
- 1 super_admin user
- 2 business_admin users (one per business)
- 1 employee with Employee model
- 2 services for business 1
- 3 client users
- 4 sample appointments

### ServicePriceListSeeder

Imports the full CSV price list:

```bash
# Via artisan command (recommended)
ddev exec "cd backend && php artisan services:seed --business-id=4"

# Programmatically (e.g., from another seeder or tinker)
php artisan tinker
>>> $seeder = new \Database\Seeders\ServicePriceListSeeder();
>>> $seeder->setCommand($this);
>>> $seeder->run(businessId: 4);
```

### DemoDataSeeder

Check `database/seeders/DemoDataSeeder.php` for additional demo data.

---

## Default Credentials

All seeded users use password: `password`

| Email | Role | Business |
|-------|------|----------|
| `superadmin@crezer.com` | `super_admin` | None (platform-wide) |
| `admin@testbarber.com` | `business_admin` | Test Barber Shop |
| `admin@elitesalon.com` | `business_admin` | Elite Salon |
| `employee@testbarber.com` | `employee` | Test Barber Shop |
| `client@testbarber.com` | `client` | Test Barber Shop |

### Reset a User's Password

If you can't login with a seeded user (because the password wasn't updated by `firstOrCreate`):

```bash
ddev exec "cd backend && php artisan tinker --execute=\"App\Models\User::where('email','admin@testbarber.com')->update(['password' => bcrypt('password')])\""
```

---

## Common Operations

### Set Up a New Business (Complete Flow)

```bash
# 1. Login as super_admin at /login
#    Email: superadmin@crezer.com
#    Password: password

# 2. Create the business via web UI: /businesses/create

# 3. Create a business_admin for it via web UI: /users/create
#    - Select role: Business Admin
#    - Select the new business
#    - Set email/password

# 4. Import services (if using the standard price list)
ddev exec "cd backend && php artisan services:seed --business-id=<NEW_BUSINESS_ID>"

# 5. Login as the new business_admin to:
#    - Create employees
#    - Configure schedules
#    - Create QR codes
```

### Check Business IDs

```bash
ddev exec "cd backend && php artisan tinker --execute=\"App\Models\Business::all(['id','name','slug'])->toArray()\" "
```

### List All Users for a Business

```bash
ddev exec "cd backend && php artisan tinker --execute=\"App\Models\User::where('business_id', 4)->get(['id','name','email','role'])->toArray()\""
```

### Change a User's Role

```bash
ddev exec "cd backend && php artisan tinker --execute=\"App\Models\User::where('email','user@example.com')->update(['role' => 'business_admin'])\""
```

### Move a User to a Different Business

```bash
ddev exec "cd backend && php artisan tinker --execute=\"App\Models\User::where('email','user@example.com')->update(['business_id' => 4])\""
```

---

## Navigation by Role

The sidebar shows different items based on the user's role:

### Super Admin Sees
- Dashboard (platform stats)
- Businesses (CRUD)
- Users (CRUD)

### Business Admin Sees
- Dashboard (business stats)
- Appointments
- Clients
- Employees
- Services
- Business (own business settings)
- QR Codes

### Employee Sees
- Dashboard
- Appointments (read-only)
- Services (read-only)
