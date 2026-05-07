# Mobile API Contract — v1

Base URL: `/api/v1`
Auth: Sanctum token via `Authorization: Bearer <token>` header.

---

## Business Discovery & Profile Endpoints

| # | Method | Path | Status | Notes |
|---|--------|------|--------|-------|
| 1 | GET | `/api/v1/businesses` | Available | List/discover. Filters: `search` or `q`, `sector`, `province`, `lat`+`lng`+`radius_km`, `service_id` |
| 2 | GET | `/api/v1/businesses/{id}` | Available | Numeric ID. Returns full public profile (services, employees, categories) |
| 2b | GET | `/api/v1/businesses/by-slug/{slug}` | Available | URL-friendly slug lookup. Same response shape as `/{id}` |
| 2c | GET | `/api/v1/businesses/{invitationCode}` | Available | Legacy alphanumeric code. Mobile uses this today |
| 3 | GET | `/api/v1/client/businesses` | ✅ Available (F3) | Lists client's enrolled businesses with `is_blocked` flag |
| 4 | POST | `/api/v1/client/businesses` | ✅ Available (F3) | Body: `{ invitation_code }` or `{ business_slug }`. Idempotent |
| 5 | DELETE | `/api/v1/client/businesses/{businessId}` | ✅ Available (F3) | Soft leave (status=left). Historical appointments preserved |
| 6 | GET | `/api/v1/client/appointments` | ✅ Available (F4) | `scope=all\|business`, `status`, `from`, `to`, `per_page`. See response shapes below. |

---

## Business Catalog & Availability Endpoints

| # | Method | Path | Auth | Notes |
|---|--------|------|------|-------|
| C1 | GET | `/api/v1/businesses/{businessId}/categories` | public | List service categories (top-level + sub-categories via `children`) |
| C2 | GET | `/api/v1/businesses/{businessId}/categories/{categoryId}` | public | Category detail with nested services |
| S1 | GET | `/api/v1/businesses/{businessId}/services` | public | Paginated services list. Filters: `category_id`, `search`, `per_page` |
| S2 | GET | `/api/v1/businesses/{businessId}/services/{serviceId}` | public | Service detail with assigned employees |
| S3 | GET | `/api/v1/services/{serviceId}/employees` | public | Active employees that perform this service |
| S4 | GET | `/api/v1/businesses/{businessId}/services/{serviceId}/employees` | public | Same, scoped explicitly to a business |
| E1 | GET | `/api/v1/businesses/{businessId}/employees` | public | Active employees of a business |
| E2 | GET | `/api/v1/employees/{employeeId}` | public | Single employee profile |
| A1 | GET | `/api/v1/businesses/{businessId}/availability` | public | Available time slots. Required: `service_id`, `date`. Optional: `employee_id` |

---

## Endpoint Details

### 1. GET /api/v1/businesses — Discover businesses

Returns paginated list of active businesses.

**Query parameters:**

| Param | Type | Notes |
|-------|------|-------|
| `search` | string (min 2) | Text search by name/description. **Mobile param.** |
| `q` | string (min 2) | Text search alias. Takes precedence over `search` if both provided. |
| `sector` | string | Filter by sector/neighborhood |
| `province` | string | Filter by province |
| `lat` | numeric | Latitude (-90 to 90). Requires `lng` |
| `lng` | numeric | Longitude (-180 to 180) |
| `radius_km` | numeric | Search radius in km (default 25). Requires `lat`+`lng` |
| `service_id` | integer | Filter to businesses offering this service |
| `per_page` | integer | Results per page (max 50, default 15) |

**Response shape:**
```json
{
  "data": [{ "id": 1, "name": "...", "slug": "...", ... }],
  "meta": { "current_page": 1, "last_page": 3, "total": 45 },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

### 2. GET /api/v1/businesses/{id} — Business by numeric ID

Route model binding. `{id}` must be a numeric integer (enforced via `whereNumber`).

**Response shape:**
```json
{
  "data": {
    "id": 4,
    "name": "Paola Beauty Studio",
    "slug": "paola-beauty-studio",
    "invitation_code": "EIVE3FX9",
    "services": [...],
    "employees": [...],
    "categories": [...]
  }
}
```

Returns 404 if business does not exist or is not `active`.

### 2b. GET /api/v1/businesses/by-slug/{slug} — Business by slug

Same response shape as `/{id}`. Useful for deep links using URL-friendly identifiers.

### 2c. GET /api/v1/businesses/{invitationCode} — Business by invitation code (legacy)

Alphanumeric string (e.g. `EIVE3FX9`). **This is the route the mobile app uses today.**
Route ordering guarantees that purely numeric segments resolve to `/{id}` and alphanumeric
segments resolve to this invitation-code route. Both contracts are maintained simultaneously.

Returns 404 if the invitation code is not found or the business is not `active`.

---

## Route Disambiguation (important for mobile)

Laravel resolves the `/businesses/{segment}` ambiguity through constraint ordering:

1. `/businesses` — root collection (no segment)
2. `/businesses/search` — static literal, matched first
3. `/businesses/{business}` with `whereNumber` — only matches integers like `/businesses/4`
4. `/businesses/by-slug/{slug}` — static `by-slug` prefix matched before catch-all
5. `/businesses/{invitationCode}` — catch-all for alphanumeric codes

This means:
- `/businesses/4` → `show(Business $business)` — numeric ID lookup
- `/businesses/EIVE3FX9` → `showByInvitationCode(string $invitationCode)` — legacy contract

---

## Client Appointments Endpoint

### 6. GET /api/v1/client/appointments

Returns the authenticated client's appointments across all enrolled businesses.

**Auth:** `Authorization: Bearer <token>` (Sanctum)
**Rate limit:** 60 requests/minute

**Query parameters:**

| Param | Type | Notes |
|-------|------|-------|
| `scope` | string | `all` (default) or `business` |
| `status` | string | Optional. One of `pending`, `confirmed`, `completed`, `cancelled` |
| `from` | string (YYYY-MM-DD) | Optional. Lower bound on `scheduled_at` |
| `to` | string (YYYY-MM-DD) | Optional. Upper bound on `scheduled_at` |
| `per_page` | integer (1-50) | Optional. Only used when `scope=business`. Default `20` |

#### Response — scope=all (default)

Groups appointments by business. No pagination. All enrolled businesses with status `active` or `blocked` are included (status `left` is excluded).

```json
{
  "data": [
    {
      "business": {
        "id": 4,
        "name": "PM Beauty Studio",
        "slug": "pm-beauty-studio",
        "logo_url": "https://..."
      },
      "appointments": [
        {
          "id": 101,
          "business_id": 4,
          "client_id": 7,
          "scheduled_at": "2025-06-15T10:00:00+00:00",
          "scheduled_until": "2025-06-15T11:00:00+00:00",
          "status": "completed",
          "notes": null,
          "service": { "id": 3, "name": "Haircut", "duration": 60, "price": "50.00" },
          "employee": { "id": 2, "user": { "name": "Maria Lopez" } },
          "services": [],
          "business": { "id": 4, "name": "PM Beauty Studio", "slug": "pm-beauty-studio" }
        }
      ],
      "total_count": 12,
      "is_blocked": false
    }
  ],
  "meta": {
    "total_businesses": 2,
    "total_appointments": 18,
    "active_enrollments": 2,
    "blocked_enrollments": 0
  }
}
```

**Notes:**
- `is_blocked: true` means the client is currently blocked at that business. Historical appointments are still returned.
- `total_count` is the count of appointments in the group after applying any `status`/`from`/`to` filters.

#### Response — scope=business

Standard Laravel paginated response. Requires `X-Business-Id` header to identify the target business. Returns 422 if header is missing.

**Required header:** `X-Business-Id: <business_id>`

```json
{
  "data": [
    {
      "id": 101,
      "business_id": 4,
      "client_id": 7,
      "scheduled_at": "2025-06-15T10:00:00+00:00",
      "scheduled_until": "2025-06-15T11:00:00+00:00",
      "status": "completed",
      "notes": null,
      "service": { "id": 3, "name": "Haircut", "duration": 60, "price": "50.00" },
      "employee": { "id": 2, "user": { "name": "Maria Lopez" } },
      "services": [],
      "business": { "id": 4, "name": "PM Beauty Studio", "slug": "pm-beauty-studio" }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 20,
    "total": 12
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

**Error — missing header (422):**
```json
{ "message": "X-Business-Id header required for business scope" }
```

---

## Catalog & Availability Endpoint Details

### C1. GET /api/v1/businesses/{businessId}/categories — List service categories

Returns all active service categories for a business. The hierarchy is exposed via the `children` field — top-level categories include their sub-categories.

**Response shape:**
```json
{
  "data": [
    {
      "id": 12,
      "name": "Hair",
      "slug": "hair",
      "description": "Cut, color, styling",
      "image_url": "https://...",
      "sort_order": 1,
      "is_active": true,
      "children": [
        { "id": 18, "name": "Hair Color", "slug": "hair-color", "is_active": true, "children": [], "services_count": 6 }
      ],
      "services_count": 14
    }
  ]
}
```

**Mobile usage:** call once on the business profile screen and render a two-level menu. Use `services_count` to decide whether to show a category badge.

### C2. GET /api/v1/businesses/{businessId}/categories/{categoryId} — Category detail with services

Same shape as C1 plus a `services` array (when the category is loaded with services).

```json
{
  "data": {
    "id": 18,
    "name": "Hair Color",
    "children": [],
    "services": [
      { "id": 41, "name": "Highlights", "duration": 90, "price": "120.00", ... }
    ]
  }
}
```

### S1. GET /api/v1/businesses/{businessId}/services — Paginated services list

**Query parameters:**

| Param | Type | Notes |
|-------|------|-------|
| `category_id` | integer | Filter by category OR sub-category id (matches `service_category_id` exactly) |
| `search` | string | Substring match on service name |
| `per_page` | integer (1-50) | Default 15 |
| `page` | integer | Standard pagination |

**Response shape:**
```json
{
  "data": [
    {
      "id": 41,
      "name": "Highlights",
      "description": "Partial or full highlights",
      "duration": 90,
      "price": "120.00",
      "category": "Hair Color",
      "is_active": true,
      "service_category": {
        "id": 18,
        "name": "Hair Color",
        "parent": { "id": 12, "name": "Hair" }
      },
      "employees_count": 3
    }
  ],
  "meta": { "current_page": 1, "last_page": 2, "total": 22 },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

**Notes for mobile:**
- This is the source-of-truth catalog endpoint. Always prefer it over the embedded `services` array in the business profile (`/businesses/{id}`), which only includes the eager-loaded subset.
- When a service has `service_category.parent`, render the breadcrumb `parent.name › service_category.name`.

### S2. GET /api/v1/businesses/{businessId}/services/{serviceId} — Service detail

Same shape as S1 single item plus `employees` array with the staff who perform the service.

```json
{
  "data": {
    "id": 41,
    "name": "Highlights",
    "duration": 90,
    "price": "120.00",
    "service_category": { "id": 18, "name": "Hair Color", "parent": { "id": 12, "name": "Hair" } },
    "employees_count": 3,
    "employees": [
      { "id": 7, "name": "Maria Lopez", "photo_url": "https://...", "bio": "...", "is_active": true }
    ]
  }
}
```

### S3 / S4. Employees that perform a specific service

- `GET /api/v1/services/{serviceId}/employees` — global lookup by service id
- `GET /api/v1/businesses/{businessId}/services/{serviceId}/employees` — same, scoped to a business explicitly

Use these to power the "choose a stylist" step in the booking flow when the user has already picked a service. Response is a flat array of employees (same shape as `EmployeeResource`).

### E1. GET /api/v1/businesses/{businessId}/employees — List employees

Returns active employees of a business. Response is a `EmployeeResource` collection:

```json
{
  "data": [
    {
      "id": 7,
      "name": "Maria Lopez",
      "photo_url": "https://...",
      "bio": "Senior stylist with 8 years of experience",
      "is_active": true
    }
  ]
}
```

### E2. GET /api/v1/employees/{employeeId} — Employee profile

Single employee. May include `services` array when loaded.

### A1. GET /api/v1/businesses/{businessId}/availability — Available time slots

Returns a list of bookable start/end intervals for a given service and date.

**Query parameters:**

| Param | Type | Required | Notes |
|-------|------|----------|-------|
| `service_id` | integer | yes | Must exist in `services` |
| `date` | string | yes | `YYYY-MM-DD` |
| `employee_id` | integer | no | When provided, returns slots only for that employee. When omitted, returns slots aggregated across employees that perform the service |

**Response shape (with `employee_id`):**
```json
{
  "date": "2026-05-12",
  "slots": [
    { "start": "09:00:00", "end": "10:00:00" },
    { "start": "10:00:00", "end": "11:00:00" }
  ]
}
```

**Response shape (without `employee_id`):**
The endpoint groups slots by employee — see `app/Http/Controllers/Api/BusinessController.php::availability()` for the multi-employee shape.

**Notes for mobile:**
- Call A1 after the user picks a service + date in the booking flow.
- If no slots are returned, the day is fully booked or the employee has no schedule for that date.
- Slots respect employee schedules from `employee_schedules` and existing appointments. **PM Beauty Studio note:** only employees with rows in `employee_schedules` will return slots — employees without configured hours return an empty list. This is by design; the admin must configure schedules from the dashboard before slots become available.

---

## Catalog Flow Recipe (mobile booking)

Recommended call sequence for the "Book at PM Beauty" flow:

1. `GET /businesses/{id}` — header info (name, logo, address)
2. `GET /businesses/{id}/categories` — render category tabs
3. `GET /businesses/{id}/services?category_id={id}` — services within tab
4. `GET /businesses/{id}/services/{id}` — service detail screen with employees list
5. `GET /businesses/{id}/availability?service_id={id}&date={YYYY-MM-DD}&employee_id={id}` — slot picker
6. `POST /appointments` (auth required) — create the booking

Each call is independently cacheable on the client. Categories and services rarely change; consider 5-min TTL. Availability must be fresh, no caching.

---

## Client Enrollment & Multi-Business (F3 — Available)

### 3. GET /api/v1/client/businesses — List enrolled businesses

**Auth:** Sanctum. **Rate limit:** 30/min on the entire `/client/*` group.

```json
{
  "data": [
    {
      "id": 4,
      "name": "PM Beauty Studio",
      "slug": "pm-beauty-studio",
      "logo_url": "https://...",
      "is_blocked": false,
      "joined_at": "2026-04-12T15:30:00+00:00"
    }
  ]
}
```

### 4. POST /api/v1/client/businesses — Enroll in a business

**Auth:** Sanctum.
**Body:** `{ "invitation_code": "EIVE3FX9" }` OR `{ "business_slug": "pm-beauty-studio" }`. Idempotent — re-enrolling returns 200 instead of 201.

**Errors:**
- `404` — invitation code or slug not found
- `409` — business is not active
- `422` — validation errors

### 5. DELETE /api/v1/client/businesses/{businessId} — Leave a business

**Auth:** Sanctum.
Soft leave: pivot row is set to `status='left'` rather than deleted. Past appointments remain visible via endpoint #6. Re-enrolling later re-activates the same row.

---

## Authentication Endpoints (public)

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/v1/auth/register` | Registers user. Accepts optional `invitation_code` |
| POST | `/api/v1/auth/login` | Returns Sanctum token |
| POST | `/api/v1/auth/forgot-password` | Sends reset code |
| POST | `/api/v1/auth/reset-password` | Resets password with code |

---

*Last updated: 2026-05-05*
