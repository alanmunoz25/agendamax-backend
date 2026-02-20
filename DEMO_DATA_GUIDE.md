# Crezer Backend - Demo Data Guide

This guide provides comprehensive information about the demo data seeded in your Crezer backend for testing and development.

## Quick Start

```bash
# Seed demo data (fresh database)
php artisan migrate:fresh --seed --seeder=DemoDataSeeder

# Or seed without clearing database
php artisan db:seed --class=DemoDataSeeder
```

## Demo Businesses

### 1. Luxe Beauty Salon 💅
- **Business ID**: 1
- **Invitation Code**: `LUXE2024`
- **Location**: 123 Main Street, Downtown, Los Angeles, CA 90012
- **Phone**: +1 (555) 123-4567
- **Email**: contact@luxebeauty.com
- **Loyalty Program**: 10 stamps → FREE haircut
- **Services**: 8 beauty services
- **Employees**: 4 stylists

#### Services
| ID | Service Name | Category | Duration | Price |
|----|--------------|----------|----------|-------|
| 1 | Women's Haircut | Hair | 45 min | $65.00 |
| 2 | Men's Haircut | Hair | 30 min | $35.00 |
| 3 | Hair Coloring | Hair | 120 min | $120.00 |
| 4 | Balayage | Hair | 180 min | $200.00 |
| 5 | Manicure | Nails | 45 min | $35.00 |
| 6 | Pedicure | Nails | 60 min | $50.00 |
| 7 | Gel Nails | Nails | 75 min | $60.00 |
| 8 | Facial Treatment | Skin | 60 min | $85.00 |

#### Employees
| ID | Name | Email | Specialization |
|----|------|-------|----------------|
| 1 | Sarah Johnson | sarah@luxebeauty.com | Hair coloring & styling (15 years) |
| 2 | Michael Chen | michael@luxebeauty.com | Nail technician & makeup artist |
| 3 | Emily Rodriguez | emily@luxebeauty.com | Balayage specialist |
| 4 | Jessica Williams | jessica@luxebeauty.com | Bridal styling & updos |

---

### 2. Urban Cuts Barbershop ✂️
- **Business ID**: 2
- **Invitation Code**: `URBAN123`
- **Location**: 456 Oak Avenue, Brooklyn, NY 11201
- **Phone**: +1 (555) 987-6543
- **Email**: hello@urbancuts.com
- **Loyalty Program**: 8 stamps → FREE beard trim
- **Services**: 6 barbershop services
- **Employees**: 3 barbers

#### Services
| ID | Service Name | Category | Duration | Price |
|----|--------------|----------|----------|-------|
| 9 | Classic Haircut | Hair | 30 min | $30.00 |
| 10 | Fade Haircut | Hair | 45 min | $40.00 |
| 11 | Beard Trim | Grooming | 20 min | $20.00 |
| 12 | Hot Towel Shave | Grooming | 30 min | $35.00 |
| 13 | Haircut + Beard | Combo | 60 min | $55.00 |
| 14 | Kids Haircut | Hair | 25 min | $22.00 |

#### Employees
| ID | Name | Email | Specialization |
|----|------|-------|----------------|
| 5 | Marcus Thompson | marcus@urbancuts.com | Master barber (20+ years) |
| 6 | David Park | david@urbancuts.com | Fade specialist |
| 7 | Anthony Russo | anthony@urbancuts.com | Traditional barbering |

---

### 3. Serenity Spa & Wellness 🧘
- **Business ID**: 3
- **Invitation Code**: `SERENITY`
- **Location**: 789 Beach Boulevard, Miami Beach, FL 33139
- **Phone**: +1 (555) 246-8135
- **Email**: info@serenityspa.com
- **Loyalty Program**: 12 stamps → FREE 30-min massage
- **Services**: 7 wellness services
- **Employees**: 4 therapists

#### Services
| ID | Service Name | Category | Duration | Price |
|----|--------------|----------|----------|-------|
| 15 | Swedish Massage | Massage | 60 min | $90.00 |
| 16 | Deep Tissue Massage | Massage | 90 min | $120.00 |
| 17 | Hot Stone Massage | Massage | 75 min | $110.00 |
| 18 | Aromatherapy | Wellness | 60 min | $95.00 |
| 19 | Couples Massage | Massage | 60 min | $180.00 |
| 20 | Signature Facial | Skin | 75 min | $100.00 |
| 21 | Body Scrub | Wellness | 45 min | $80.00 |

#### Employees
| ID | Name | Email | Specialization |
|----|------|-------|----------------|
| 8 | Lisa Martinez | lisa@serenityspa.com | Deep tissue specialist |
| 9 | Amanda Taylor | amanda@serenityspa.com | Aromatherapist |
| 10 | Rachel Green | rachel@serenityspa.com | Organic skincare esthetician |
| 11 | Nicole Brown | nicole@serenityspa.com | Hot stone & couples therapy |

---

## Test User Accounts

### Client Accounts (Mobile App Testing)
All client passwords: `password`

| Name | Email | Phone | User ID |
|------|-------|-------|---------|
| John Doe | john@example.com | +1 (555) 111-2222 | Auto-generated |
| Jane Smith | jane@example.com | +1 (555) 333-4444 | Auto-generated |
| Bob Wilson | bob@example.com | +1 (555) 555-6666 | Auto-generated |

### Employee Accounts (Admin/Backend Testing)
All employee passwords: `password`

**Luxe Beauty Salon:**
- sarah@luxebeauty.com
- michael@luxebeauty.com
- emily@luxebeauty.com
- jessica@luxebeauty.com

**Urban Cuts Barbershop:**
- marcus@urbancuts.com
- david@urbancuts.com
- anthony@urbancuts.com

**Serenity Spa & Wellness:**
- lisa@serenityspa.com
- amanda@serenityspa.com
- rachel@serenityspa.com
- nicole@serenityspa.com

---

## Postman Testing Workflow

### 1. Import Postman Files
1. Import `Crezer_API.postman_collection.json`
2. Import `Crezer_API.postman_environment.json`
3. Select "Crezer Development" environment

### 2. Test Complete Appointment Flow

#### Step 1: Register New Client
**Endpoint**: POST `/auth/register`
```json
{
  "name": "Test User",
  "email": "test@example.com",
  "password": "password",
  "password_confirmation": "password",
  "phone": "+1 (555) 999-8888"
}
```
✅ Token will be auto-saved

#### Step 2: Discover Business by Invitation Code
**Endpoint**: GET `/businesses/LUXE2024`

Response includes:
- Business details
- Loyalty program info
- Contact information

#### Step 3: Browse Business Services
**Endpoint**: GET `/businesses/1/services`

Returns all 8 services at Luxe Beauty Salon

#### Step 4: View Service Employees
**Endpoint**: GET `/businesses/1/services/1/employees`

Shows all employees who can perform "Women's Haircut"

#### Step 5: Check Employee Details
**Endpoint**: GET `/employees/1`

Returns:
- Employee bio
- All services they offer
- Business information
- Work schedules

#### Step 6: Check Employee Availability
**Endpoint**: GET `/appointments/availability`
```json
{
  "employee_id": 1,
  "service_id": 1,
  "date": "2025-12-15"
}
```

#### Step 7: Create Appointment
**Endpoint**: POST `/appointments`
```json
{
  "service_id": 1,
  "employee_id": 1,
  "scheduled_at": "2025-12-15 14:00:00"
}
```
✅ Appointment ID will be auto-saved

#### Step 8: View My Appointments
**Endpoint**: GET `/appointments`

Shows all user's appointments

---

## Testing Different Scenarios

### Scenario 1: Beauty Salon Client Journey
```bash
# Register
POST /auth/register (use jane@test.com)

# Find salon
GET /businesses/LUXE2024

# Browse services
GET /businesses/1/services

# Pick Hair Coloring (service_id: 3, $120, 120min)
# Check who does it
GET /businesses/1/services/3/employees

# Book with Sarah
POST /appointments
{
  "service_id": 3,
  "employee_id": 1,
  "scheduled_at": "2025-12-16 10:00:00"
}
```

### Scenario 2: Barbershop Walk-in
```bash
# Login existing client
POST /auth/login
{
  "email": "john@example.com",
  "password": "password"
}

# Discover barbershop
GET /businesses/URBAN123

# Quick fade haircut
GET /businesses/2/services/10/employees

# Book with Marcus (master barber)
POST /appointments
{
  "service_id": 10,
  "employee_id": 5,
  "scheduled_at": "2025-12-15 15:30:00"
}
```

### Scenario 3: Spa Couples Massage
```bash
# Login
POST /auth/login

# Find spa
GET /businesses/SERENITY

# Couples massage service
GET /services/19/employees

# Book with Nicole (couples therapy expert)
POST /appointments
{
  "service_id": 19,
  "employee_id": 11,
  "scheduled_at": "2025-12-20 18:00:00"
}
```

---

## Database Queries for Verification

```sql
-- Check all businesses
SELECT id, name, invitation_code, loyalty_stamps_required FROM businesses;

-- Services per business
SELECT b.name, COUNT(s.id) as service_count
FROM businesses b
LEFT JOIN services s ON s.business_id = b.id
GROUP BY b.id, b.name;

-- Employees per business
SELECT b.name, COUNT(e.id) as employee_count
FROM businesses b
LEFT JOIN employees e ON e.business_id = b.id
GROUP BY b.id, b.name;

-- Employee-Service assignments
SELECT
  u.name as employee_name,
  s.name as service_name,
  s.price,
  s.duration
FROM employee_service es
JOIN employees e ON e.id = es.employee_id
JOIN users u ON u.id = e.user_id
JOIN services s ON s.id = es.service_id
ORDER BY u.name, s.name;
```

---

## Mobile App Integration Guide

### Environment Variables (.env in React Native)
```bash
API_BASE_URL=https://crezer-app-laravel.ddev.site/api/v1

# Test Data
TEST_BUSINESS_LUXE=LUXE2024
TEST_BUSINESS_URBAN=URBAN123
TEST_BUSINESS_SERENITY=SERENITY

TEST_CLIENT_EMAIL=john@example.com
TEST_CLIENT_PASSWORD=password
```

### Axios Configuration
```typescript
import axios from 'axios';

const api = axios.create({
  baseURL: process.env.API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Add token from AsyncStorage
api.interceptors.request.use(async (config) => {
  const token = await AsyncStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
```

---

## Troubleshooting

### Issue: "Call to undefined method Business::services()"
**Solution**: Make sure you have the latest Business model with relationship methods:
```bash
git pull origin main
# or manually add services() and employees() methods to Business model
```

### Issue: "SQLSTATE[23000]: Integrity constraint violation"
**Solution**: Fresh migrate before seeding:
```bash
php artisan migrate:fresh --seed --seeder=DemoDataSeeder
```

### Issue: "No employees available for service"
**Solution**: Check employee-service pivot table:
```sql
SELECT * FROM employee_service;
```
Should show multiple assignments. Re-run seeder if empty.

---

## Next Steps

1. **Test in Postman**: Import collection and run through complete appointment flow
2. **Integrate Mobile App**: Use test credentials to authenticate from React Native
3. **Test Multi-tenancy**: Login with different business employees, verify data isolation
4. **QR Code Testing**: Create appointments, verify QR generation
5. **Loyalty System**: Complete appointments, check stamp accumulation

---

## Support

For issues or questions:
- Check API_DOCUMENTATION.md for endpoint details
- Review Postman collection for request examples
- Verify database state with SQL queries above
