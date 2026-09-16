# Secured Tenant Dashboard & Data API

This document describes the OpenKos Tenant Portal REST API endpoints. External applications (e.g. mobile apps, client web portals, third-party dashboards) use these endpoints to display a tenant's dashboard, active and past leases, billing invoices, payment histories, and maintenance tickets.

---

## 1. Authentication & Security

> [!IMPORTANT]
> **Every endpoint in this API requires authentication via Laravel Sanctum.**
> Requests MUST include the `Authorization` header with a valid Bearer token obtained from `/api/v1/auth/login` or `/api/v1/auth/register`:
> ```http
> Authorization: Bearer <your_access_token>
> Accept: application/json
> ```
> 
> **Strict Tenant Scoping**: All queries are strictly scoped to the authenticated user's tenant account. A tenant can NEVER view, query, or submit payments/tickets for other tenants. Unauthorized resource requests return `404 Not Found`.

---

## 2. API Endpoints Summary

Base URL: `https://dashboard.highlanderstay.com/api/v1/tenant`

| Category | Method | Endpoint | Description |
| :--- | :--- | :--- | :--- |
| **Orders** | `POST` | `/api/v1/orders`<br>*(alias: `/api/v1/bookings`)* | Create room order: creates Tenant, Lease, Invoice, and DOKU Checkout URL |
| **Dashboard** | `GET` | `/dashboard` | Aggregated overview (active lease, invoices, tickets, next action) |
| **Leases** | `GET` | `/leases` | List current active and past leases |
| **Leases** | `GET` | `/leases/{id}` | Detailed lease info, unit, property, and rent history |
| **Invoices** | `GET` | `/invoices` | List invoices (filter by `status`, `lease_id`, paginated) |
| **Invoices** | `GET` | `/invoices/{id}` | Invoice breakdown, line items, and payment transaction history |
| **Invoices** | `POST` | `/invoices/{id}/pay` | Submit payment proof (receipt image / bank transfer record) |
| **Invoices** | `POST` | `/invoices/{id}/checkout` | Generate online payment checkout session (DOKU QRIS, VA, E-Wallet) |
| **Maintenance**| `GET` | `/maintenance-tickets` | List maintenance requests submitted by tenant |
| **Maintenance**| `POST`| `/maintenance-tickets` | Submit a new maintenance ticket |
| **Maintenance**| `GET` | `/maintenance-tickets/{id}` | View maintenance ticket details and resolution notes |

---

## 3. Endpoints Specification

### 3.1 Tenant Dashboard Overview

Fetches complete aggregated data needed to render a rich mobile/web tenant home screen in a single request.

- **Method**: `GET`
- **URL**: `/api/v1/tenant/dashboard`
- **Headers**:
  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```

#### Response (`200 OK`)
```json
{
  "tenant": {
    "id": 1,
    "name": "Jane Doe",
    "phone": "6281234567890",
    "email": "jane@example.com",
    "phone_verified": true
  },
  "active_lease": {
    "id": 14,
    "reference": "LSE-2026-0014",
    "start_date": "2026-03-01",
    "end_date": "2027-03-01",
    "rent_amount": 1500000.00,
    "billing_label": "Monthly",
    "status": "active",
    "unit": {
      "id": 8,
      "name": "Kamar 204",
      "status": "occupied"
    },
    "property": {
      "id": 3,
      "name": "Highlander Stay Grogol",
      "address": "Jl. Alpukat No. 12",
      "city": "Jakarta Barat",
      "image_url": "http://example.com/storage/properties/thumb.jpg"
    }
  },
  "account_summary": {
    "total_unpaid_invoices": 1,
    "total_outstanding_amount": 1500000.00,
    "next_due_date": "2026-10-01",
    "open_maintenance_tickets": 0
  },
  "next_action": {
    "type": "pay_invoice",
    "title": "Upcoming Rent Payment",
    "message": "Invoice INV-2026-0089 is due on 2026-10-01.",
    "invoice_id": 89,
    "reference": "INV-2026-0089",
    "amount": 1500000.00,
    "due_date": "2026-10-01"
  },
  "recent_invoices": [
    {
      "id": 89,
      "reference": "INV-2026-0089",
      "period_start": "2026-10-01",
      "period_end": "2026-10-31",
      "due_date": "2026-10-01",
      "status": "pending",
      "total": 1500000.00,
      "amount_paid": 0.00,
      "outstanding": 1500000.00,
      "is_overdue": false
    }
  ],
  "recent_tickets": []
}
```

---

### 3.2 List Leases

Returns all current active leases and past/historical leases.

- **Method**: `GET`
- **URL**: `/api/v1/tenant/leases`
- **Headers**:
  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```

#### Response (`200 OK`)
```json
{
  "current_leases": [
    {
      "id": 14,
      "reference": "LSE-2026-0014",
      "start_date": "2026-03-01",
      "end_date": "2027-03-01",
      "rent_amount": 1500000.00,
      "billing_label": "Monthly",
      "billing_cycle": "monthly",
      "status": "active",
      "unit": {
        "id": 8,
        "name": "Kamar 204",
        "floor": 2,
        "status": "occupied"
      },
      "property": {
        "id": 3,
        "name": "Highlander Stay Grogol",
        "address": "Jl. Alpukat No. 12",
        "city": "Jakarta Barat",
        "image_url": "http://example.com/storage/properties/thumb.jpg"
      }
    }
  ],
  "lease_history": []
}
```

---

### 3.3 Get Single Lease Details

- **Method**: `GET`
- **URL**: `/api/v1/tenant/leases/{id}`
- **Headers**:
  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```

#### Response (`200 OK`)
```json
{
  "lease": {
    "id": 14,
    "reference": "LSE-2026-0014",
    "start_date": "2026-03-01",
    "end_date": "2027-03-01",
    "rent_amount": 1500000.00,
    "deposit_amount": 1000000.00,
    "billing_label": "Monthly",
    "status": "active",
    "notes": null,
    "unit": { ... },
    "property": { ... },
    "invoices": [
      {
        "id": 89,
        "reference": "INV-2026-0089",
        "period_start": "2026-10-01",
        "period_end": "2026-10-31",
        "due_date": "2026-10-01",
        "status": "pending",
        "total": 1500000.00,
        "amount_paid": 0.00,
        "outstanding": 1500000.00
      }
    ]
  }
}
```

---

### 3.4 List Invoices

Paginated list of invoices with query filtering.

- **Method**: `GET`
- **URL**: `/api/v1/tenant/invoices`
- **Query Parameters**:
  - `status`: `unpaid` (pending/partial), `paid`, `overdue`, or any specific status.
  - `lease_id`: Filter invoices by specific lease ID.
  - `page`: Page number (default: 1).
- **Headers**:
  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```

#### Response (`200 OK`)
```json
{
  "invoices": {
    "current_page": 1,
    "data": [
      {
        "id": 89,
        "reference": "INV-2026-0089",
        "lease_id": 14,
        "lease_reference": "LSE-2026-0014",
        "property_name": "Highlander Stay Grogol",
        "unit_name": "Kamar 204",
        "period_start": "2026-10-01",
        "period_end": "2026-10-31",
        "due_date": "2026-10-01",
        "status": "pending",
        "total": 1500000.00,
        "amount_paid": 0.00,
        "outstanding": 1500000.00,
        "is_overdue": false
      }
    ],
    "last_page": 1,
    "total": 1
  }
}
```

---

### 3.5 Single Invoice Details

- **Method**: `GET`
- **URL**: `/api/v1/tenant/invoices/{id}`
- **Headers**:
  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```

#### Response (`200 OK`)
```json
{
  "invoice": {
    "id": 89,
    "reference": "INV-2026-0089",
    "lease_id": 14,
    "due_date": "2026-10-01",
    "status": "pending",
    "total": 1500000.00,
    "amount_paid": 0.00,
    "outstanding": 1500000.00,
    "is_overdue": false,
    "lease": {
      "reference": "LSE-2026-0014",
      "unit_name": "Kamar 204",
      "property_name": "Highlander Stay Grogol",
      "address": "Jl. Alpukat No. 12"
    },
    "payments": []
  }
}
```

---

### 3.6 Submit Payment Proof

Allows a tenant to submit payment proof (bank transfer receipt image or record).

- **Method**: `POST`
- **URL**: `/api/v1/tenant/invoices/{id}/pay`
- **Headers**:
  ```http
  Authorization: Bearer <token>
  Content-Type: multipart/form-data
  Accept: application/json
  ```

#### Request Fields
| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `amount` | number | **Yes** | Paid amount (e.g. `1500000`) |
| `payment_method` | string | **Yes** | e.g. `bank_transfer`, `bca_va`, `qris`, `cash` |
| `proof_image` | file | No | Image file (jpg, png, webp, max 5MB) |
| `payment_date` | date | No | YYYY-MM-DD (defaults to today) |
| `notes` | string | No | Optional note (e.g. sender name or reference) |

#### Response (`201 Created`)
```json
{
  "message": "Payment submitted successfully. Awaiting verification by management.",
  "payment": {
    "id": 45,
    "invoice_id": 89,
    "amount": 1500000.00,
    "status": "pending",
    "payment_date": "2026-09-14"
  }
}
```

---

### 3.7 Online Payment Checkout (DOKU Gateway / QRIS / Virtual Account / E-Wallet)

Generates a hosted payment session link (DOKU Jokul Checkout). The tenant or WhatsApp Bot can open this link to complete the payment via:
- **QRIS** (GoPay, OVO, ShopeePay, Dana, LinkAja, Mobile Banking QR)
- **Virtual Accounts** (BCA, Mandiri, BRI, BNI, Permata, Danamon, CIMB)
- **Credit / Debit Cards**
- **Convenience Stores** (Indomaret, Alfamart)

Upon successful payment, DOKU sends a real-time signed webhook to `POST /api/webhooks/payment/doku`, which automatically settles the invoice and records the transaction in OpenKos.

- **Method**: `POST`
- **URL**: `/api/v1/tenant/invoices/{id}/checkout`
- **Headers**:
  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```

#### Response (`200 OK`)
```json
{
  "message": "Checkout session created successfully.",
  "checkout_url": "https://staging.doku.com/checkout-link-v2/xxxxxxxxx",
  "reused": false,
  "attempt": {
    "id": 14,
    "reference": "25fbbdae-3dbb-4fc6-b816-16010d8a9563",
    "provider_reference": "25fbbdae-3dbb-4fc6-b816-16010d8a9563",
    "amount": 1500000,
    "currency": "IDR",
    "status": "pending",
    "expires_at": "2026-09-16T09:45:00+00:00"
  }
}
```

#### WhatsApp Bot Example Usage
When a tenant asks the WhatsApp bot *"Bayar kos bulan ini"* or *"Minta link pembayaran"*:
1. The bot authenticates or identifies the tenant phone number.
2. Bot calls `GET /api/v1/tenant/invoices?status=unpaid` to find the invoice ID.
3. Bot calls `POST /api/v1/tenant/invoices/{id}/checkout`.
4. Bot sends message:
   ```text
   Halo Kak Budi, berikut link pembayaran sewa Kamar 204:
   Total: Rp 1.500.000
   Link Pembayaran: https://staging.doku.com/checkout-link-v2/xxxxxxxxx
   (Mendukung QRIS, BCA, Mandiri, BRI, BNI, OVO, ShopeePay, dll.)
   ```

---

### 3.8 List Maintenance Tickets

- **Method**: `GET`
- **URL**: `/api/v1/tenant/maintenance-tickets`
- **Headers**:
  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```

#### Response (`200 OK`)
```json
{
  "tickets": {
    "current_page": 1,
    "data": [
      {
        "id": 12,
        "reference": "TKT20260012",
        "title": "Air Conditioner Leak",
        "description": "Water leaking from indoor unit.",
        "status": "reported",
        "priority": "high",
        "location": "Main Bedroom",
        "property_name": "Highlander Stay Grogol",
        "unit_name": "Kamar 204",
        "created_at": "2026-09-14T07:45:00.000000Z",
        "resolved_at": null
      }
    ],
    "total": 1
  }
}
```

---

### 3.9 Submit Maintenance Ticket

- **Method**: `POST`
- **URL**: `/api/v1/tenant/maintenance-tickets`
- **Headers**:
  ```http
  Authorization: Bearer <token>
  Content-Type: application/json
  Accept: application/json
  ```

#### Request Body
```json
{
  "title": "Air Conditioner Leak",
  "description": "Water leaking from indoor unit onto the floor.",
  "priority": "high",
  "location": "Main Bedroom"
}
```
*(If `property_id` or `unit_id` are omitted, OpenKos automatically links the ticket to the tenant's current active room!)*

#### Response (`201 Created`)
```json
{
  "message": "Maintenance ticket created successfully.",
  "ticket": {
    "id": 12,
    "reference": "TKT20260012",
    "title": "Air Conditioner Leak",
    "status": "reported",
    "priority": "high",
    "created_at": "2026-09-14T07:45:00.000000Z"
  }
}
```

---

## 4. Frontend & Mobile Code Example (React Native / Axios)

```typescript
import axios from 'axios';

const api = axios.create({
  baseURL: 'https://dashboard.highlanderstay.com/api/v1/tenant',
  headers: {
    Accept: 'application/json',
  },
});

// Interceptor to inject Sanctum token
api.interceptors.request.use((config) => {
  const token = getStoredAuthToken(); // Your secure storage / state
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// 1. Fetch Dashboard Overview
export async function getTenantDashboard() {
  const response = await api.get('/dashboard');
  return response.data;
}

// 2. Fetch Unpaid Invoices
export async function getUnpaidInvoices() {
  const response = await api.get('/invoices', {
    params: { status: 'unpaid' },
  });
  return response.data.invoices.data;
}

// 3. Online Payment Checkout (DOKU QRIS, Virtual Account, E-Wallet)
export async function createCheckoutSession(invoiceId: number) {
  const response = await api.post(`/invoices/${invoiceId}/checkout`);
  // Open the returned URL in browser or in-app webview
  return response.data.checkout_url;
}

// 4. Manual Upload Payment Receipt
export async function submitPaymentProof(invoiceId: number, amount: number, fileUri: string) {
  const formData = new FormData();
  formData.append('amount', String(amount));
  formData.append('payment_method', 'bank_transfer');
  formData.append('proof_image', {
    uri: fileUri,
    type: 'image/jpeg',
    name: 'receipt.jpg',
  } as any);

  const response = await api.post(`/invoices/${invoiceId}/pay`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return response.data;
}

// 5. Create Maintenance Ticket
export async function createMaintenanceTicket(title: string, description: string, priority = 'medium') {
  const response = await api.post('/maintenance-tickets', {
    title,
    description,
    priority,
  });
  return response.data;
}
```
