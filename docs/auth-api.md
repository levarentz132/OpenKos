# Authentication & Tenant Registration OTP API

This document provides complete instructions and endpoint references for integrating external client applications (mobile apps in Flutter/React Native, web portals, third-party services) with the OpenKos Tenant Authentication & OTP API.

All endpoints are hosted at:
```text
https://dashboard.highlanderstay.com/api/v1/auth
```

---

## 1. Overview & Security Architecture

1. **Strict Tenant Separation (Direct `tenants` Table Architecture)**:
   - Registration creates records **exclusively in the `tenants` table**. The administrative `users` table is never written to.
   - `Tenant` models implement Laravel's `Authenticatable` contract and issue Sanctum personal access tokens directly (`App\Models\Tenant`).
   - Administrator and property owner accounts in `users` are strictly prevented from registering or logging in through the tenant API.
2. **Anti-Spam Staged Registration**:
   - Calling `/api/v1/auth/register` creates **zero** database rows. Registration payloads are held in memory/cache with a 10-minute TTL.
   - Database records are created **only** when the applicant verifies the 6-digit OTP code via `/api/v1/auth/otp/verify`.
3. **Dual-Channel OTP Delivery**:
   - **WhatsApp**: Dispatched using the configured WhatsApp provider (Fonnte/Wablas/WABA) to Indonesian (`08...` or `628...`) and international mobile numbers.
   - **Email**: Dispatched via standard SMTP mail driver with an HTML verification card.
4. **Flexible Verification Methods**:
   - **Staged Registration**: Pass `registration_token` and `code` to `/api/v1/auth/otp/verify` to complete registration and obtain an API token.
   - **Authenticated Session**: Pass `Authorization: Bearer <token>` for existing accounts.
   - **Public Identifier**: Pass `login` (phone number or email) directly along with the `code`.
5. **Account Lifecycle & Deletion**:
   - Authenticated tenants can delete their own account and revoke tokens via `DELETE /api/v1/auth/me`.
   - Administrators can delete user accounts via `DELETE /api/v1/auth/users/{user}`.
6. **Rate Limiting & Expiry**:
   - OTP codes are 6 digits and expire in **10 minutes**.
   - Cooldown period of **60 seconds** per target per channel prevents spam and abuse.

---

## 2. Endpoints Summary

| Action | Method | URL | Authentication | Description |
| :--- | :--- | :--- | :--- | :--- |
| **Register Tenant** | `POST` | `/api/v1/auth/register` | Public | Initiates staged registration (creates tenant only upon OTP verification) |
| **Check Status** | `POST` | `/api/v1/auth/check-status` | Public | Inspects dual-channel verification status (WhatsApp & Email) |
| **Login Tenant** | `POST` | `/api/v1/auth/login` | Public | Login via Email or Phone Number |
| **Send / Resend OTP** | `POST` | `/api/v1/auth/otp/send` | Optional (Bearer or login) | Dispatches OTP via WhatsApp or Email |
| **Verify OTP** | `POST` | `/api/v1/auth/otp/verify` | Optional (Bearer or login) | Verifies code, creates `Tenant` row if pending, returns token |
| **Get My Profile** | `GET` | `/api/v1/auth/me` | Bearer Token Required | Gets tenant profile data & verification status |
| **Delete My Account** | `DELETE` | `/api/v1/auth/me` | Bearer Token Required | Deletes authenticated tenant/user account & revokes tokens |
| **Delete User (Admin)** | `DELETE` | `/api/v1/auth/users/{user}` | Bearer Token (Admin) | Deletes target user and associated tenant data |
| **Logout** | `POST` | `/api/v1/auth/logout` | Bearer Token Required | Revokes current API access token |

---

## 3. Detailed Endpoint Reference

### 3.1 Register Tenant Account

Registers a new user, creates their tenant profile, and immediately generates and dispatches an OTP code via their preferred channel.

- **Method**: `POST`
- **URL**: `https://dashboard.highlanderstay.com/api/v1/auth/register`
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
  "otp_channel": "whatsapp",
  "device_name": "mobile-client"
}
```

| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `name` | string | Yes | Full name of the tenant |
| `email` | string | Yes | Unique email address |
| `phone` | string | Optional* | Phone number (*Required if `otp_channel` is `"whatsapp"`) |
| `password` | string | Yes | Minimum 8 characters |
| `password_confirmation` | string | Yes | Must match `password` |
| `otp_channel` | string | No | `"whatsapp"` (default if phone provided) or `"email"` |
| `device_name` | string | No | Friendly device label (e.g. `iphone-15`, `samsung-s24`) |

#### Successful Response (`201 Created`)
```json
{
  "message": "Verification code sent. Please submit the OTP code to complete registration.",
  "registration_token": "reg_a1b2c3d4e5f6g7h8...",
  "otp_sent": true,
  "otp_channel": "whatsapp",
  "target": "6281234567890"
}
```
> [!NOTE]
> **Anti-Spam Protection**: The `User` and `Tenant` database records are **NOT** inserted into the database at this step. This ensures that unverified or abandoned registrations do not pollute your database. Once the user submits the correct OTP via `/api/v1/auth/otp/verify`, their account and tenant profile are created.

*(In debug/development mode, `"debug_otp": "123456"` is also included in the response for convenience)*.

#### Validation Errors (`422 Unprocessable Entity`)
- If the email belongs to an administrator:
  ```json
  {
    "message": "This email belongs to an administrator account. Tenant accounts must use a separate email address.",
    "errors": {
      "email": ["This email belongs to an administrator account. Tenant accounts must use a separate email address."]
    }
  }
  ```
- If WhatsApp is chosen without providing a phone number:
  ```json
  {
    "message": "A phone number is required when selecting WhatsApp as the OTP verification channel.",
    "errors": {
      "phone": ["A phone number is required when selecting WhatsApp as the OTP verification channel."]
    }
  }
  ```

---

### 3.2 Send / Resend OTP

Sends or resends an OTP code via **WhatsApp** or **Email**. Can be called with an active `Bearer` token OR without a token by passing `login` (email or phone).

- **Method**: `POST`
- **URL**: `https://dashboard.highlanderstay.com/api/v1/auth/otp/send`
- **Headers**:
  ```http
  Accept: application/json
  Content-Type: application/json
  Authorization: Bearer <token>   <-- Optional if 'login' is supplied in body
  ```

#### Request Body (When Authenticated with Token)
```json
{
  "channel": "whatsapp"
}
```
*(Or `"channel": "email"`)*

#### Request Body (Unauthenticated / Public)
```json
{
  "channel": "whatsapp",
  "login": "081234567890"
}
```
*(Or `"login": "jane@example.com"` with `"channel": "email"`)*

#### Successful Response (`200 OK`)
```json
{
  "message": "Verification code sent successfully via whatsapp.",
  "channel": "whatsapp",
  "target": "6281234567890",
  "sent": true
}
```

#### Cooldown Restriction (`422 Unprocessable Entity`)
If requested again within the 60-second cooldown window:
```json
{
  "message": "Please wait 48 seconds before requesting a new WhatsApp code.",
  "errors": {
    "otp": ["Please wait 48 seconds before requesting a new WhatsApp code."]
  }
}
```

---

### 3.3 Verify OTP Code

Verifies the 6-digit code submitted by the tenant. Updates `phone_verified_at` (for WhatsApp) or `email_verified_at` (for Email).

- **Method**: `POST`
- **URL**: `https://dashboard.highlanderstay.com/api/v1/auth/otp/verify`
- **Headers**:
  ```http
  Accept: application/json
  Content-Type: application/json
  Authorization: Bearer <token>   <-- Optional if 'login' is supplied in body
  ```

#### Request Body (When Authenticated with Token)
```json
{
  "code": "481920"
}
```

#### Request Body (Unauthenticated / Public)
```json
{
  "login": "081234567890",
  "code": "481920",
  "device_name": "my-phone"
}
```

#### Successful Response (`200 OK`)
```json
{
  "message": "Whatsapp verified successfully.",
  "verified": true,
  "token": "2|hJ3k9L...",
  "channel": "whatsapp",
  "phone_verified": true,
  "email_verified": false,
  "user": {
    "id": 15,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "6281234567890",
    "phone_verified": true,
    "email_verified": false,
    "is_active": true
  }
}
```
*(Note: If called unauthenticated, `token` contains a fresh Sanctum token so the user is immediately logged in).*

#### Code Expired / Invalid (`422 Unprocessable Entity`)
```json
{
  "message": "The verification code is invalid or has expired. Please request a new code.",
  "errors": {
    "code": ["The verification code is invalid or has expired. Please request a new code."]
  }
}
```

---

### 3.4 Login Tenant (Email or Phone)

Authenticates an existing tenant using either **Email OR Phone Number** with password.

- **Method**: `POST`
- **URL**: `https://dashboard.highlanderstay.com/api/v1/auth/login`
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
  "device_name": "mobile-app"
}
```
*(Or use `"login": "jane@example.com"`)*

#### Successful Response (`200 OK`)
```json
{
  "message": "Login successful.",
  "token": "3|aBcD123...",
  "user": {
    "id": 15,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "6281234567890",
    "phone_verified": true,
    "phone_verified_at": "2026-09-14T15:20:00+07:00",
    "is_active": true,
    "roles": [],
    "has_tenant_profile": true,
    "tenant": {
      "id": 15,
      "name": "Jane Doe",
      "phone": "6281234567890",
      "id_card_number": null
    }
  }
}
```

---

### 3.5 Get Profile (`/me`)

Fetches the currently authenticated tenant's profile, verification status, and tenant metadata.

- **Method**: `GET`
- **URL**: `https://dashboard.highlanderstay.com/api/v1/auth/me`
- **Headers**:
  ```http
  Accept: application/json
  Authorization: Bearer <token>
  ```

---

### 3.6 Logout

Revokes and deletes the current access token.

- **Method**: `POST`
- **URL**: `https://dashboard.highlanderstay.com/api/v1/auth/logout`
- **Headers**:
  ```http
  Accept: application/json
  Authorization: Bearer <token>
  ```

---

### 3.7 Delete My Account (Self-Deletion)

Allows the authenticated tenant (or user) to delete their own account. All active API tokens are revoked, audit logs are recorded, and the tenant record is deleted from the database.

- **Method**: `DELETE`
- **URL**: `https://dashboard.highlanderstay.com/api/v1/auth/me`
- **Headers**:
  ```http
  Accept: application/json
  Authorization: Bearer <token>
  ```

#### Successful Response (`200 OK`)
```json
{
  "message": "Account deleted successfully."
}
```

#### Restriction on Last Administrator (`422 Unprocessable Entity`)
If the account being deleted is the last remaining administrator/owner of the property, deletion is blocked:
```json
{
  "message": "The last remaining administrator account cannot be deleted.",
  "errors": {
    "account": ["The last remaining administrator account cannot be deleted."]
  }
}
```

---

### 3.8 Delete User Account (Administrator Only)

Allows property administrators/owners to permanently delete any user account and any associated tenant record.

- **Method**: `DELETE`
- **URL**: `https://dashboard.highlanderstay.com/api/v1/auth/users/{user_id}`
- **Headers**:
  ```http
  Accept: application/json
  Authorization: Bearer <admin_token>
  ```

#### Successful Response (`200 OK`)
```json
{
  "message": "User deleted successfully."
}
```

#### Forbidden for Non-Administrators (`403 Forbidden`)
If called with a tenant token:
```json
{
  "message": "Only administrators can delete user accounts."
}
```

---

## 4. Integration Examples for Mobile & Frontend

### Flutter (Dart) Example
```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

class AuthService {
  static const String baseUrl = 'https://dashboard.highlanderstay.com/api/v1/auth';

  // 1. Register Tenant
  static Future<Map<String, dynamic>> register({
    required String name,
    required String email,
    required String phone,
    required String password,
    String otpChannel = 'whatsapp',
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/register'),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: jsonEncode({
        'name': name,
        'email': email,
        'phone': phone,
        'password': password,
        'password_confirmation': password,
        'otp_channel': otpChannel,
      }),
    );
    return jsonDecode(response.body);
  }

  // 2. Verify OTP
  static Future<Map<String, dynamic>> verifyOtp({
    required String code,
    String? token,
    String? login,
  }) async {
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (token != null) {
      headers['Authorization'] = 'Bearer $token';
    }

    final body = <String, dynamic>{'code': code};
    if (login != null) {
      body['login'] = login;
    }

    final response = await http.post(
      Uri.parse('$baseUrl/otp/verify'),
      headers: headers,
      body: jsonEncode(body),
    );
    return jsonDecode(response.body);
  }

  // 3. Resend OTP
  static Future<Map<String, dynamic>> resendOtp({
    required String channel,
    String? token,
    String? login,
  }) async {
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (token != null) {
      headers['Authorization'] = 'Bearer $token';
    }

    final body = <String, dynamic>{'channel': channel};
    if (login != null) {
      body['login'] = login;
    }

    final response = await http.post(
      Uri.parse('$baseUrl/otp/send'),
      headers: headers,
      body: jsonEncode(body),
    );
    return jsonDecode(response.body);
  }
}
```

### React Native / Axios Example
```typescript
import axios from 'axios';

const api = axios.create({
  baseURL: 'https://dashboard.highlanderstay.com/api/v1/auth',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

// Register
export const registerTenant = async (data: {
  name: string;
  email: string;
  phone: string;
  password: string;
  otp_channel?: 'whatsapp' | 'email';
}) => {
  const response = await api.post('/register', {
    ...data,
    password_confirmation: data.password,
  });
  return response.data;
};

// Resend OTP
export const resendOtp = async (channel: 'whatsapp' | 'email', token?: string, login?: string) => {
  const headers = token ? { Authorization: `Bearer ${token}` } : {};
  const response = await api.post('/otp/send', { channel, login }, { headers });
  return response.data;
};

// Verify OTP
export const verifyOtp = async (code: string, token?: string, login?: string) => {
  const headers = token ? { Authorization: `Bearer ${token}` } : {};
  const response = await api.post('/otp/verify', { code, login }, { headers });
  return response.data;
};
```

### cURL Quick Verification
```bash
# 1. Register with WhatsApp OTP
curl -X POST "https://dashboard.highlanderstay.com/api/v1/auth/register" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Tenant",
    "email": "jane.tenant@example.com",
    "phone": "081234567890",
    "password": "password123",
    "password_confirmation": "password123",
    "otp_channel": "whatsapp"
  }'

# 2. Verify OTP using Token
curl -X POST "https://dashboard.highlanderstay.com/api/v1/auth/otp/verify" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|your_token_here" \
  -H "Content-Type: application/json" \
  -d '{"code": "123456"}'

# 3. Or Verify OTP using Phone Number directly
curl -X POST "https://dashboard.highlanderstay.com/api/v1/auth/otp/verify" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"login": "081234567890", "code": "123456"}'
```
