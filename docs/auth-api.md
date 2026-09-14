# Authentication & Phone Verification API

This document provides complete instructions and endpoint references for integrating external applications (mobile apps, frontends, third-party services) with the OpenKos Authentication API.

---

## 1. What is the API?

The OpenKos Auth API provides secure, token-based authentication using **Laravel Sanctum**. It allows external clients to:
- **Register New Accounts**: Create a user account and automatic tenant profile.
- **Log In Flexibly**: Sign in using either **Email OR Phone Number** with password.
- **Bearer Token Authorization**: Issue secure personal access tokens for API requests.
- **Phone Number Verification**: Generate and dispatch 6-digit OTP codes via WhatsApp, verify them, and record verification timestamps.

---

## 2. Technical Implementation Overview

| Component | Implementation Details |
| :--- | :--- |
| **Authentication Engine** | [Laravel Sanctum](https://laravel.com/docs/sanctum) via personal access tokens table. |
| **User Model** | [User.php](file:///c:/xampp/htdocs/OpenKos/app/Models/User.php) with `HasApiTokens` trait, `phone`, and `phone_verified_at`. |
| **OTP Delivery** | [PhoneVerificationService.php](file:///c:/xampp/htdocs/OpenKos/app/Services/PhoneVerificationService.php) utilizing `WhatsAppManager` (Fonnte gateway or `laravel.log` fallback). |
| **Security & Rate Limiting** | OTPs are 6 digits, cached for 10 minutes, with a 60-second cooldown per user. |
| **Route Protection** | `auth:sanctum` for token validation; `phone.verified` middleware for verified phone enforcement. |

---

## 3. Endpoints Reference

### Base URL
```text
https://dashboard.highlanderstay.com/api/v1/auth
```

### Quick Reference Table

| Action | Method | URL | Auth Required |
| :--- | :--- | :--- | :--- |
| **Register** | `POST` | `/api/v1/auth/register` | No |
| **Login** | `POST` | `/api/v1/auth/login` | No |
| **Get Current User** | `GET` | `/api/v1/auth/me` | Yes (`Bearer <token>`) |
| **Send / Resend OTP** | `POST` | `/api/v1/auth/phone/send-otp` | Yes (`Bearer <token>`) |
| **Verify OTP** | `POST` | `/api/v1/auth/phone/verify-otp` | Yes (`Bearer <token>`) |
| **Logout** | `POST` | `/api/v1/auth/logout` | Yes (`Bearer <token>`) |

---

## 4. Detailed Endpoint Specifications

### 4.1 Register New Account

Creates a new user and an associated tenant profile. If a phone number is provided, an OTP is automatically dispatched.

- **Method**: `POST`
- **URL**: `/api/v1/auth/register`
- **Headers**:
  ```http
  Accept: application/json
  Content-Type: application/json
  ```

#### Request Body
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "081234567890",
  "password": "password123",
  "password_confirmation": "password123",
  "device_name": "mobile-app"
}
```

#### Response (`201 Created`)
```json
{
  "message": "Account registered successfully.",
  "token": "1|qW8u8wK...",
  "otp_sent": true,
  "user": {
    "id": 12,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "6281234567890",
    "phone_verified": false,
    "phone_verified_at": null,
    "is_active": true,
    "roles": [],
    "has_tenant_profile": true
  }
}
```

---

### 4.2 Login (Email or Phone Number)

Authenticates an existing user and returns a Sanctum Bearer token. The `login` field accepts either an email address or a phone number.

- **Method**: `POST`
- **URL**: `/api/v1/auth/login`
- **Headers**:
  ```http
  Accept: application/json
  Content-Type: application/json
  ```

#### Request Body
```json
{
  "login": "081234567890",
  "password": "password123",
  "device_name": "flutter-app"
}
```
*(You can also use `"login": "jane@example.com"`)*

#### Response (`200 OK`)
```json
{
  "message": "Login successful.",
  "token": "2|hJ3k9L...",
  "user": {
    "id": 12,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "6281234567890",
    "phone_verified": false,
    "phone_verified_at": null,
    "is_active": true,
    "roles": [],
    "has_tenant_profile": true
  }
}
```

---

### 4.3 Send / Resend Phone OTP

Sends a 6-digit verification code to the authenticated user's WhatsApp number.

- **Method**: `POST`
- **URL**: `/api/v1/auth/phone/send-otp`
- **Headers**:
  ```http
  Accept: application/json
  Authorization: Bearer <your-token>
  Content-Type: application/json
  ```

#### Request Body (Optional)
```json
{
  "phone": "081234567890"
}
```
*(Leave empty to send to the phone number registered on the profile)*

#### Response (`200 OK`)
```json
{
  "message": "Verification code sent successfully.",
  "phone": "6281234567890"
}
```

#### Rate Limit Error (`422 Unprocessable Entity`)
If requested again within 60 seconds:
```json
{
  "message": "Please wait 45 seconds before requesting a new code.",
  "errors": {
    "otp": ["Please wait 45 seconds before requesting a new code."]
  }
}
```

---

### 4.4 Verify OTP Code

Verifies the 6-digit code received on WhatsApp. Once verified, `phone_verified_at` is updated to the current timestamp.

- **Method**: `POST`
- **URL**: `/api/v1/auth/phone/verify-otp`
- **Headers**:
  ```http
  Accept: application/json
  Authorization: Bearer <your-token>
  Content-Type: application/json
  ```

#### Request Body
```json
{
  "code": "729401"
}
```

#### Response (`200 OK`)
```json
{
  "message": "Phone number verified successfully.",
  "phone_verified": true,
  "phone_verified_at": "2026-09-14T14:42:00+07:00"
}
```

---

### 4.5 Get Current Profile (`/me`)

Fetches the logged-in user's profile, roles, and tenant profile.

- **Method**: `GET`
- **URL**: `/api/v1/auth/me`
- **Headers**:
  ```http
  Accept: application/json
  Authorization: Bearer <your-token>
  ```

#### Response (`200 OK`)
```json
{
  "user": {
    "id": 12,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "6281234567890",
    "phone_verified": true,
    "phone_verified_at": "2026-09-14T14:42:00+07:00",
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

### 4.6 Logout

Revokes and destroys the current Bearer token.

- **Method**: `POST`
- **URL**: `/api/v1/auth/logout`
- **Headers**:
  ```http
  Accept: application/json
  Authorization: Bearer <your-token>
  ```

#### Response (`200 OK`)
```json
{
  "message": "Logged out successfully."
}
```

---

## 5. Client Integration Code Examples

### JavaScript / Fetch
```javascript
const API_BASE = "https://dashboard.highlanderstay.com/api/v1/auth";

// 1. Login
async function login(loginIdentifier, password) {
  const response = await fetch(`${API_BASE}/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json", "Accept": "application/json" },
    body: JSON.stringify({ login: loginIdentifier, password: password })
  });
  const data = await response.json();
  if (response.ok) {
    localStorage.setItem("authToken", data.token);
    return data;
  }
  throw new Error(data.message || "Login failed");
}

// 2. Verify OTP
async function verifyOtp(code) {
  const token = localStorage.getItem("authToken");
  const response = await fetch(`${API_BASE}/phone/verify-otp`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Accept": "application/json",
      "Authorization": `Bearer ${token}`
    },
    body: JSON.stringify({ code })
  });
  return await response.json();
}
```

### cURL
```bash
# Register
curl -X POST "https://dashboard.highlanderstay.com/api/v1/auth/register" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"John","email":"john@test.com","phone":"081234567890","password":"password123","password_confirmation":"password123"}'

# Login
curl -X POST "https://dashboard.highlanderstay.com/api/v1/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"login":"081234567890","password":"password123"}'

# Verify OTP
curl -X POST "https://dashboard.highlanderstay.com/api/v1/auth/phone/verify-otp" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|your_access_token_here" \
  -H "Content-Type: application/json" \
  -d '{"code":"123456"}'
```
