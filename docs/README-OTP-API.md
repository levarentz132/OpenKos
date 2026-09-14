# OpenKos OTP & Tenant Authentication API — Developer README

Comprehensive technical guide and endpoint reference for mobile application developers, frontend engineers, and backend integrators interacting with the **OpenKos Tenant Authentication and Dual-Channel OTP API**.

---

## 1. Core Architecture & Security Rules

| Rule | Description |
| :--- | :--- |
| **Strict `tenants` Table Separation** | Tenant registrations and logins operate **exclusively** on the `tenants` table. The administrative `users` table is reserved for kos owners/admins and is **never** written to by this API. |
| **Anti-Spam Staged Registration** | Calling `/register` writes **zero rows** to the database. The registration state is held in cache (10-minute TTL). The database record is created **only after** the user submits the correct 6-digit OTP code. |
| **Direct Sanctum Auth on `Tenant`** | Personal access tokens are issued directly on `App\Models\Tenant`. Authenticated requests (`Bearer <token>`) resolve directly to the `Tenant` instance. |
| **Dual-Channel Delivery** | Supports dispatching 6-digit OTP codes via **WhatsApp** (WABA, Fonnte, Wablas) or **Email** (SMTP). |
| **Rate Limiting & Cooldown** | 60-second cooldown per target phone/email between OTP requests. Codes expire after 10 minutes. |

---

## 2. API Base URL & Endpoints Summary

Base URL:
```text
https://dashboard.highlanderstay.com/api/v1/auth
```

### Endpoints Table

| # | Action | Method | Endpoint | Auth Required | Description |
| :-: | :--- | :---: | :--- | :---: | :--- |
| 1 | **Register Tenant** | `POST` | `/register` | No | Starts staged registration & sends OTP |
| 2 | **Check Status** | `POST` | `/check-status` | No | Checks dual-channel verification status (WhatsApp & Email) |
| 3 | **Send / Resend OTP** | `POST` | `/otp/send` | Optional | Dispatches OTP via WhatsApp or Email |
| 4 | **Verify OTP** | `POST` | `/otp/verify` | Optional | Verifies code, creates `Tenant` row if pending, returns token |
| 5 | **Login Tenant** | `POST` | `/login` | No | Login via Email or Phone Number + Password |
| 6 | **Get Profile** | `GET` | `/me` | Bearer Token | Returns authenticated tenant profile |
| 7 | **Delete My Account** | `DELETE` | `/me` | Bearer Token | Permanently deletes authenticated account & revokes tokens |
| 8 | **Delete User (Admin)**| `DELETE` | `/users/{user_id}` | Bearer Token (Admin) | Deletes target user and associated tenant data |
| 9 | **Logout** | `POST` | `/logout` | Bearer Token | Revokes active Sanctum API token |

---

## 3. Step-by-Step Flow: Tenant Registration & OTP Verification

```mermaid
sequenceDiagram
    autonumber
    actor Tenant as Mobile App / User
    participant API as OpenKos Auth API
    participant Cache as Cache / In-Memory
    participant WA as WhatsApp (Meta WABA / Fonnte)
    participant DB as MySQL (tenants table)

    Tenant->>API: POST /api/v1/auth/register (name, email, phone, password, otp_channel)
    API->>Cache: Store pending registration payload (TTL: 10m)
    API->>WA: Dispatch 6-digit OTP to phone
    API-->>Tenant: 201 Created (registration_token: "reg_xxx", target: "628xxx")
    Note over DB: No database row inserted yet (Anti-Spam)

    Tenant->>API: POST /api/v1/auth/otp/verify (registration_token: "reg_xxx", code: "123456")
    API->>Cache: Verify OTP match & get cached registration data
    API->>DB: INSERT into tenants (name, email, phone, password, phone_verified_at)
    API->>API: Generate Sanctum Personal Access Token
    API-->>Tenant: 201 Created (verified: true, token: "1|xxx", user: {...})
    Tenant->>API: GET /api/v1/tenant/dashboard (Authorization: Bearer 1|xxx)
```

---

## 4. Endpoint Specifications

### 4.1 Register Tenant (Staged)

- **URL**: `POST /api/v1/auth/register`
- **Headers**:
  ```http
  Accept: application/json
  Content-Type: application/json
  ```
- **Request Body**:
  ```json
  {
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "081234567890",
    "password": "password123",
    "password_confirmation": "password123",
    "otp_channel": "whatsapp",
    "device_name": "iPhone-15"
  }
  ```
  *(Set `"otp_channel": "email"` if email verification is preferred).*

- **Response (`201 Created`)**:
  ```json
  {
    "message": "Verification code sent. Please submit the OTP code to complete registration.",
    "registration_token": "reg_7b8a1c9e2f...",
    "otp_sent": true,
    "otp_channel": "whatsapp",
    "target": "6281234567890"
  }
  ```

---

### 4.2 Check Account & Dual Verification Status

Allows external projects (mobile apps, n8n, CRM, bots) to check the state of an account and inspect both **WhatsApp OTP** and **Email OTP** verification statuses.

- **URL**: `POST /api/v1/auth/check-status`
- **Headers**:
  ```http
  Accept: application/json
  Content-Type: application/json
  ```
- **Request Body**:
  ```json
  {
    "login": "081234567890"
  }
  ```
  *(Can also pass `"email": "jane@example.com"` or `"registration_token": "reg_xxx"` or `Authorization: Bearer <token>`)*.

#### 1. Pending Registration Response
Returned when the user has called `/register` but has not verified their OTP code:
```json
{
  "status": "pending_registration",
  "registered": false,
  "pending_registration": true,
  "registration_token": "reg_7b8a1c9e2f...",
  "user": {
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "6281234567890"
  },
  "verifications": {
    "whatsapp": {
      "available": true,
      "target": "6281234567890",
      "verified": false,
      "verified_at": null,
      "status": "pending"
    },
    "email": {
      "available": true,
      "target": "jane@example.com",
      "verified": false,
      "verified_at": null,
      "status": "pending"
    }
  },
  "is_fully_verified": false
}
```

#### 2. Fully Registered Tenant Response
Returned when the tenant is active in the database:
```json
{
  "status": "registered",
  "registered": true,
  "pending_registration": false,
  "id": 12,
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "6281234567890",
  "is_active": true,
  "verifications": {
    "whatsapp": {
      "available": true,
      "target": "6281234567890",
      "verified": true,
      "verified_at": "2026-09-14T16:35:00+07:00",
      "status": "verified"
    },
    "email": {
      "available": true,
      "target": "jane@example.com",
      "verified": true,
      "verified_at": "2026-09-14T16:40:00+07:00",
      "status": "verified"
    }
  },
  "is_fully_verified": true
}
```

#### 3. Unregistered Response
```json
{
  "status": "unregistered",
  "registered": false,
  "pending_registration": false,
  "message": "No account or pending registration found for this identifier."
}
```

---

### 4.3 Send / Resend OTP

Dispatches a new OTP code to an applicant or existing tenant. Can be invoked in 3 ways:
1. With `registration_token` from `/register`
2. With `Authorization: Bearer <token>`
3. With `login` (phone number or email)

- **URL**: `POST /api/v1/auth/otp/send`
- **Headers**:
  ```http
  Accept: application/json
  Content-Type: application/json
  ```
- **Request Body (Resend for pending registration)**:
  ```json
  {
    "registration_token": "reg_7b8a1c9e2f...",
    "channel": "whatsapp"
  }
  ```
- **Request Body (Existing tenant requesting code)**:
  ```json
  {
    "login": "081234567890",
    "channel": "whatsapp"
  }
  ```
- **Response (`200 OK`)**:
  ```json
  {
    "message": "Verification code sent successfully via whatsapp.",
    "channel": "whatsapp",
    "target": "6281234567890",
    "sent": true
  }
  ```
- **Cooldown Error (`422 Unprocessable Entity`)**:
  ```json
  {
    "message": "Please wait 52 seconds before requesting a new WhatsApp code.",
    "errors": {
      "otp": ["Please wait 52 seconds before requesting a new WhatsApp code."]
    }
  }
  ```

---

### 4.4 Verify OTP

Verifies the 6-digit code.
- If verifying a **pending registration**, it inserts the record into `tenants`, deletes the temporary session, and returns a fresh Bearer token.
- If verifying an **existing tenant**, it updates `phone_verified_at` or `email_verified_at`.

- **URL**: `POST /api/v1/auth/otp/verify`
- **Headers**:
  ```http
  Accept: application/json
  Content-Type: application/json
  ```
- **Request Body (Completing Registration)**:
  ```json
  {
    "registration_token": "reg_7b8a1c9e2f...",
    "code": "491823",
    "device_name": "iPhone-15"
  }
  ```
- **Request Body (Direct Phone Verification & Instant Login)**:
  ```json
  {
    "login": "081234567890",
    "code": "491823",
    "device_name": "iPhone-15"
  }
  ```
- **Response (`201 Created` or `200 OK`)**:
  ```json
  {
    "message": "Account registered and verified successfully.",
    "verified": true,
    "token": "4|2x8bKq19cM...",
    "channel": "whatsapp",
    "phone_verified": true,
    "email_verified": false,
    "user": {
      "id": 12,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "phone": "6281234567890",
      "phone_verified": true,
      "phone_verified_at": "2026-09-14T16:35:00+07:00",
      "is_active": true,
      "roles": [],
      "has_tenant_profile": true,
      "tenant": {
        "id": 12,
        "name": "Jane Doe",
        "phone": "6281234567890",
        "id_card_number": null
      }
    }
  }
  ```

---

### 4.5 Tenant Login

Authenticates using either **phone number** (automatically normalized from `08...` to `628...`) or **email address** along with password.

- **URL**: `POST /api/v1/auth/login`
- **Request Body**:
  ```json
  {
    "login": "081234567890",
    "password": "password123",
    "device_name": "mobile-app"
  }
  ```
- **Response (`200 OK`)**:
  ```json
  {
    "message": "Login successful.",
    "token": "5|pLm91kAz...",
    "user": {
      "id": 12,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "phone": "6281234567890",
      "phone_verified": true,
      "is_active": true
    }
  }
  ```

---

### 4.6 Self-Delete Account

Allows authenticated tenants to permanently delete their account and invalidate all issued tokens.

- **URL**: `DELETE /api/v1/auth/me`
- **Headers**:
  ```http
  Accept: application/json
  Authorization: Bearer <tenant_token>
  ```
- **Response (`200 OK`)**:
  ```json
  {
    "message": "Account deleted successfully."
  }
  ```

---

### 4.7 Delete User (Admin Only)

Allows property administrators to delete any user account and associated tenant record by ID.

- **URL**: `DELETE /api/v1/auth/users/{user_id}`
- **Headers**:
  ```http
  Accept: application/json
  Authorization: Bearer <admin_token>
  ```
- **Response (`200 OK`)**:
  ```json
  {
    "message": "User deleted successfully."
  }
  ```

---

## 5. WhatsApp Gateway Configuration

OpenKos supports multiple WhatsApp delivery backends configured in the Admin Web Settings (`/settings`):

### Option A: Meta WhatsApp Cloud API (WABA)
When sending outbound OTPs outside Meta's 24-hour customer care window, use an approved Authentication template:
- **Phone Number ID**: Your Meta WABA Phone Number ID
- **Permanent Access Token**: System User access token from Meta Business Manager
- **Template Name**: e.g., `otp_verification`
- **Template Body**:
  ```text
  Your OpenKos verification code is: {{1}}. Valid for 10 minutes. Please do not share this code with anyone.
  ```

### Option B: Fonnte / Wablas (Default)
Standard API token-based delivery. Works automatically with international number normalization (`0812...` $\rightarrow$ `62812...`).

---

## 6. Frontend & Mobile Integration Snippets

### Flutter (Dart)
```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

class AuthService {
  static const String baseUrl = 'https://dashboard.highlanderstay.com/api/v1/auth';

  // 1. Staged Registration
  static Future<Map<String, dynamic>> register({
    required String name,
    required String email,
    required String phone,
    required String password,
  }) async {
    final res = await http.post(
      Uri.parse('$baseUrl/register'),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: jsonEncode({
        'name': name,
        'email': email,
        'phone': phone,
        'password': password,
        'password_confirmation': password,
        'otp_channel': 'whatsapp',
      }),
    );
    return jsonDecode(res.body);
  }

  // 2. Submit OTP
  static Future<Map<String, dynamic>> verifyOtp({
    required String registrationToken,
    required String code,
  }) async {
    final res = await http.post(
      Uri.parse('$baseUrl/otp/verify'),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: jsonEncode({
        'registration_token': registrationToken,
        'code': code,
      }),
    );
    return jsonDecode(res.body);
  }
}
```

### TypeScript / Axios
```typescript
import axios from 'axios';

const api = axios.create({
  baseURL: 'https://dashboard.highlanderstay.com/api/v1/auth',
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
});

// Register
export const register = async (userData: any) => {
  const res = await api.post('/register', userData);
  return res.data; // contains registration_token
};

// Verify OTP
export const verifyOtp = async (registrationToken: string, code: string) => {
  const res = await api.post('/otp/verify', {
    registration_token: registrationToken,
    code,
  });
  return res.data; // contains Sanctum token & tenant profile
};

// Delete Account
export const deleteAccount = async (token: string) => {
  const res = await api.delete('/me', {
    headers: { Authorization: `Bearer ${token}` },
  });
  return res.data;
};
```

---

## 7. cURL Quick Test Script

```bash
# Step 1: Register (Anti-Spam: 0 rows created in DB)
REG_RES=$(curl -s -X POST "https://dashboard.highlanderstay.com/api/v1/auth/register" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Tenant",
    "email": "test.tenant@example.com",
    "phone": "081234567890",
    "password": "password123",
    "password_confirmation": "password123",
    "otp_channel": "whatsapp"
  }')

echo "$REG_RES"
# Extract registration_token from response

# Step 2: Verify OTP code received on WhatsApp
curl -X POST "https://dashboard.highlanderstay.com/api/v1/auth/otp/verify" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "registration_token": "PASTE_REGISTRATION_TOKEN_HERE",
    "code": "123456"
  }'

# Step 3: Delete Account
curl -X DELETE "https://dashboard.highlanderstay.com/api/v1/auth/me" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer PASTE_BEARER_TOKEN_HERE"
```

---

## 8. Delivery Troubleshooting & Gateway Configuration

If OTP codes are not arriving at your target WhatsApp phone or Email inbox, inspect the following:

### 8.1 Diagnostics in API Responses

Every `/register` and `/otp/send` response includes delivery diagnostic fields:

| Field | Type | Description |
| :--- | :--- | :--- |
| `otp_sent` / `sent` | `boolean` | `true` if the message was accepted by the gateway/mailer. `false` if connection or API failed. |
| `driver` | `string` | The active driver handling delivery (e.g. `log`, `fonnte`, `waba`, `smtp`). |
| `delivery_warning` | `string\|null` | Present when driver is in mock/log mode (e.g., `WhatsApp driver is set to [log]. The message was written to server logs, not sent to a physical phone.`). |
| `delivery_error` | `string\|null` | Contains the exact failure message if the mail server or WhatsApp API rejected the request. |
| `debug_otp` | `string\|null` | Returned only when `APP_DEBUG=true` in `.env`, allowing instant verification during local development. |

### 8.2 WhatsApp Delivery Configuration

OpenKos supports three WhatsApp drivers in `.env`:

```ini
# .env

# Option 1: Development / Mock (Writes to storage/logs/laravel.log)
WHATSAPP_DRIVER=log

# Option 2: Fonnte WhatsApp Gateway (Recommended for Indonesian numbers)
WHATSAPP_DRIVER=fonnte
FONNTE_TOKEN=your_fonnte_api_token_here

# Option 3: Meta WhatsApp Cloud API (WABA)
WHATSAPP_DRIVER=waba
WABA_PHONE_NUMBER_ID=your_phone_number_id
WABA_ACCESS_TOKEN=your_meta_system_user_token
WABA_TEMPLATE_NAME=otp_verification
WABA_TEMPLATE_LANGUAGE=id
```

> **Note**: If `WHATSAPP_DRIVER=log`, no actual WhatsApp message will be sent to the physical phone. Check `storage/logs/laravel.log` to view the dispatched code.

### 8.3 Email (SMTP) Configuration

Ensure `.env` contains valid SMTP credentials. If `MAIL_HOST=mailpit` is used without a running Mailpit instance, delivery will fail with connection timeout:

```ini
# .env

# Local Development without external mail server (writes to storage/logs/laravel.log):
MAIL_MAILER=log

# Production / Real Delivery (e.g. Gmail, Brevo, Mailgun, SMTP2GO):
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_specific_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@highlanderstay.com"
MAIL_FROM_NAME="Highlander Stay"
```

