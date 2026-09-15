# Panduan Integrasi Cepat (Mobile / Web App ke API Highlanderstay)

Panduan praktis dan sederhana untuk mengintegrasikan alur **Pendaftaran & Autentikasi Tenant (WhatsApp OTP Saja)** ke dalam aplikasi Anda (Flutter, React Native, React, Vue, atau aplikasi lainnya).

---

## 🌐 Base URL API

* **Production**: `https://dashboard.highlanderstay.com/api/v1/auth`
* **Header Wajib** untuk setiap request:
  ```http
  Content-Type: application/json
  Accept: application/json
  ```

---

## 🚀 Alur Kerja di Halaman Daftar

```text
[ Form Pendaftaran ]
1. User mengisi: Nama, Email, No. WhatsApp, Password.
2. User klik tombol "Kirim OTP WhatsApp" ──> API kirim 6 digit kode ke WhatsApp user.
3. User memasukkan 6 digit kode OTP WhatsApp ke form.
4. User klik tombol "Daftar" ───────────────> Akun terverifikasi penuh & token login didapat!
```

---

## 1. Langkah 1: Minta OTP WhatsApp di Form Daftar

Panggil endpoint ini ketika user menekan tombol **"Kirim OTP"** di sebelah kolom nomor WhatsApp.
* **Jika user baru**: Kode OTP dikirimkan untuk mendaftar akun baru.
* **Jika nomor sudah ada tetapi belum diverifikasi**: Kode OTP dikirimkan untuk mengonversi & memverifikasi akun penyewa tersebut.
* **Jika nomor sudah terverifikasi sebelumnya**: API mengembalikan error 422 agar user langsung login.

* **Method**: `POST`
* **URL**: `/otp/send`
* **Request Body**:
  ```json
  {
    "channel": "whatsapp",
    "phone": "081234567890"
  }
  ```
  *(Format nomor telepon bisa diawali `08...`, `+628...`, atau `628...`, backend otomatis merapikannya)*

* **Response Sukses (`200 OK`)**:
  ```json
  {
    "message": "Verification code sent successfully via whatsapp.",
    "channel": "whatsapp",
    "target": "6281234567890",
    "sent": true
  }
  ```

> 💡 **Tips UI**:
> * Setelah tombol ditekan, aktifkan timer hitung mundur **60 detik** sebelum tombol "Kirim Ulang OTP" bisa diklik kembali.
> * Buka/aktifkan kolom input OTP 6-digit.

---

## 2. Langkah 2: Submit Form Pendaftaran (Beserta Kode OTP)

Setelah user melengkapi semua data dan mengisi kode OTP dari WhatsApp, kirim semua data sekaligus dalam 1 request:

* **Method**: `POST`
* **URL**: `/register`
* **Request Body**:
  ```json
  {
    "name": "Budi Santoso",
    "email": "budi@example.com",
    "phone": "081234567890",
    "password": "password123",
    "password_confirmation": "password123",
    "otp": "123456",
    "device_name": "Flutter-App"
  }
  ```

* **Response Sukses (`201 Created`)**:
  ```json
  {
    "message": "Pendaftaran berhasil! Nomor WhatsApp Anda telah diverifikasi.",
    "token": "1|7b8a1c9e2f4a5b6c...",
    "phone_verified": true,
    "user": {
      "id": 15,
      "name": "Budi Santoso",
      "email": "budi@example.com",
      "phone": "6281234567890",
      "role": "tenant"
    }
  }
  ```

* **Apa yang terjadi di sini?**
  1. No. WhatsApp langsung terverifikasi (`phone_verified: true`, `phone_verified_at = now()`).
  2. Jika sebelumnya data penyewa sudah dimasukkan oleh admin di dashboard tetapi belum diverifikasi, statusnya otomatis dikonversi menjadi **Verified** dan password aktif.
  3. Anda langsung mendapatkan `token` login (Sanctum Bearer Token). Simpan token ini di penyimpanan lokal (*SecureStorage* / *AsyncStorage* / *LocalStorage*). User langsung login!

---

## 3. Langkah 3: Selesai (Langsung Masuk Dashboard)

Arahkan user langsung ke Halaman Utama / Dashboard aplikasi:

> **"Pendaftaran Berhasil! 🎉"**  
> *"Selamat datang di Highlanderstay! Akun dan WhatsApp Anda telah terverifikasi."*

---

## 4. Cara Aplikasi Mengetahui Status Akun

Aplikasi Anda bisa mengecek status verifikasi user kapan saja:

### Cara A: Cek Berdasarkan No. WhatsApp atau Email (Tanpa Butuh Token)
* **Method**: `POST`
* **URL**: `/check-status`
* **Request Body**:
  ```json
  {
    "identifier": "081234567890"
  }
  ```
* **Response**:
  ```json
  {
    "status": "registered",
    "registered": true,
    "phone_verified": true,
    "is_fully_verified": true
  }
  ```

### Cara B: Menggunakan Token Login (Bearer Token)
* **Method**: `GET`
* **URL**: `/me`
* **Headers**:
  ```http
  Authorization: Bearer 1|7b8a1c9e2f4a5b6c...
  ```
* **Response**:
  ```json
  {
    "user": {
      "id": 15,
      "name": "Budi Santoso",
      "email": "budi@example.com",
      "phone_verified_at": "2026-09-14 11:28:00",
      "email_verified_at": "2026-09-14 11:32:00"
    }
  }
  ```
  *(Jika `email_verified_at` bernilai `null`, berarti user belum mengklik link di email).*

---

## 5. Login Tenant (Setelah Akun Terdaftar)

Untuk login berikutnya, penyewa dapat menggunakan **Email ATAU Nomor HP**:

* **Method**: `POST`
* **URL**: `/login`
* **Request Body**:
  ```json
  {
    "login": "081234567890",
    "password": "password123",
    "device_name": "Flutter-App"
  }
  ```
  *(Kolom `login` bisa diisi alamat email atau nomor telepon).*

---

## 💻 Contoh Kode Siap Pakai

### JavaScript / TypeScript (Fetch API)

```javascript
const BASE_URL = 'https://dashboard.highlanderstay.com/api/v1/auth';

// 1. Kirim OTP WhatsApp
async function requestWhatsAppOtp(phone) {
  const res = await fetch(`${BASE_URL}/otp/send`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ channel: 'whatsapp', phone })
  });
  return await res.json();
}

// 2. Submit Pendaftaran
async function registerTenant(formData) {
  const res = await fetch(`${BASE_URL}/register`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({
      name: formData.name,
      email: formData.email,
      phone: formData.phone,
      password: formData.password,
      password_confirmation: formData.passwordConfirmation,
      otp: formData.otp,
      device_name: 'WebApp'
    })
  });
  
  const data = await res.json();
  if (res.ok) {
    // Simpan token login
    localStorage.setItem('auth_token', data.token);
    return data;
  } else {
    throw new Error(data.message || 'Pendaftaran gagal');
  }
}
```

### Flutter (Dart + `http` package)

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

const String baseUrl = 'https://dashboard.highlanderstay.com/api/v1/auth';

// 1. Kirim OTP WhatsApp
Future<bool> sendWhatsAppOtp(String phone) async {
  final response = await http.post(
    Uri.parse('$baseUrl/otp/send'),
    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
    body: jsonEncode({'channel': 'whatsapp', 'phone': phone}),
  );
  return response.statusCode == 200;
}

// 2. Submit Pendaftaran
Future<Map<String, dynamic>?> submitRegister({
  required String name,
  required String email,
  required String phone,
  required String password,
  required String otp,
}) async {
  final response = await http.post(
    Uri.parse('$baseUrl/register'),
    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
    body: jsonEncode({
      'name': name,
      'email': email,
      'phone': phone,
      'password': password,
      'password_confirmation': password,
      'otp': otp,
      'device_name': 'Mobile-App',
    }),
  );

  if (response.statusCode == 201) {
    final data = jsonDecode(response.body);
    // Simpan data['token'] ke FlutterSecureStorage
    return data;
  }
  return null;
}
```
