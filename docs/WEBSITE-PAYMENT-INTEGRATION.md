# Panduan Integrasi Pembayaran DOKU & Keranjang Pemesanan untuk Website (OpenKos)

Dokumentasi lengkap bagi pengembang frontend (React, Next.js, Vue, Nuxt, Svelte, atau HTML/JS) untuk mengintegrasikan sistem **Keranjang Kamar (Cart-First Booking)** dan **Pembayaran Tagihan DOKU (Jokul Checkout)** pada backend OpenKos.

---

## 1. Arsitektur Pemesanan & Pembayaran (Website Frontend)

Sistem mendukung dua alur pembayaran utama:
1. **Pemesanan Kamar Masuk Keranjang (Tamu / Calon Penghuni)**:
   - Kamar dimasukkan ke keranjang (`/api/v1/cart`) dengan status `pending`.
   - Kontrak sewa (**Lease**) **BELUM** dibuat dan kamar **TIDAK** langsung dikunci (*tetap available*).
   - Link pembayaran DOKU Checkout dibuat langsung untuk pesanan tersebut.
   - Saat pembayaran berhasil, webhook OpenKos otomatis menerbitkan akun tenant, membuat Lease, menandai kamar `occupied`, dan menerbitkan kwitansi lunas.
2. **Pembayaran Tagihan Rutin (Penghuni Terdaftar / Tenant Portal)**:
   - Penghuni login dan melihat tagihan bulanan (`/api/v1/tenant/invoices`).
   - Penghuni menekan tombol bayar dan diarahkan ke DOKU Checkout.

---

## 2. Alur 1: Keranjang Kamar (Cart-First Booking Flow)

### 2.1 Menambahkan Kamar ke Keranjang & Mendapatkan Link DOKU

- **Method**: `POST`
- **URL**: `https://api.domain-anda.com/api/v1/cart`  
  *(Atau `POST /api/v1/orders` / `POST /api/v1/bookings`)*
- **Headers**:
  ```http
  Content-Type: application/json
  Accept: application/json
  X-Cart-Token: <uuid-keranjang-browser>
  ```

#### Request Payload:
```json
{
  "unit_id": 5,
  "name": "Budi Santoso",
  "phone": "081299998888",
  "email": "budi.santoso@example.com",
  "start_date": "2026-10-01",
  "duration_months": 1,
  "notes": "Booking lewat website"
}
```

#### Response Sukses (`201 Created`):
```json
{
  "message": "Booking order created and saved in cart. Please complete payment to confirm your lease.",
  "order": {
    "id": 14,
    "reference": "BK-X8K2M9LP1Q",
    "cart_token": "a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d",
    "status": "pending",
    "property": {
      "id": 1,
      "name": "Highlander Stay Grogol",
      "address": "Jl. Alpukat No. 12, Jakarta Barat"
    },
    "unit": {
      "id": 5,
      "name": "Kamar 101"
    },
    "guest": {
      "name": "Budi Santoso",
      "phone": "6281299998888",
      "email": "budi.santoso@example.com"
    },
    "period": {
      "start_date": "2026-10-01",
      "end_date": "2026-11-01",
      "duration_months": 1
    },
    "amount": 1750000.0,
    "currency": "IDR",
    "checkout_url": "https://staging.doku.com/checkout-link-v2/9b7f9a12-xxxx-xxxx-xxxx",
    "expires_at": "2026-09-16T11:45:00+00:00",
    "lease_created": false
  }
}
```

---

### 2.2 Menampilkan Keranjang Belanja

Simpan `cart_token` di `localStorage` browser agar isi keranjang tetap tersimpan saat pengguna berpindah halaman:

- **Method**: `GET`
- **URL**: `https://api.domain-anda.com/api/v1/cart?cart_token={cart_token}`

#### Response:
```json
{
  "cart": {
    "cart_token": "a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d",
    "count": 1,
    "total": 1750000.0,
    "items": [
      {
        "id": 14,
        "reference": "BK-X8K2M9LP1Q",
        "property_name": "Highlander Stay Grogol",
        "unit_name": "Kamar 101",
        "guest_name": "Budi Santoso",
        "guest_phone": "6281299998888",
        "start_date": "2026-10-01",
        "end_date": "2026-11-01",
        "duration_months": 1,
        "amount": 1750000.0,
        "status": "pending",
        "checkout_url": "https://staging.doku.com/checkout-link-v2/9b7f9a12-xxxx-xxxx-xxxx",
        "expires_at": "2026-09-16T11:45:00+00:00"
      }
    ]
  }
}
```

---

### 2.3 Menghapus Kamar dari Keranjang
- **Method**: `DELETE`
- **URL**: `https://api.domain-anda.com/api/v1/cart/{orderId}`

```json
{
  "message": "Booking item removed from cart."
}
```

---

### 2.4 Memperbarui Link Checkout Keranjang
Jika link pembayaran kadaluarsa sebelum dibayar:
- **Method**: `POST`
- **URL**: `https://api.domain-anda.com/api/v1/cart/{orderId}/checkout`

```json
{
  "message": "Checkout URL generated successfully.",
  "checkout_url": "https://staging.doku.com/checkout-link-v2/fresh-url",
  "order": {
    "id": 14,
    "reference": "BK-X8K2M9LP1Q",
    "amount": 1750000.0,
    "status": "pending"
  }
}
```

---

## 3. Alur 2: Pembayaran Tagihan Rutin Penghuni (Tenant Portal)

Bagi penghuni yang sudah aktif dan memiliki tagihan bulanan:

### 3.1 Mengambil Daftar Tagihan Belum Lunas
- **Method**: `GET`
- **URL**: `/api/v1/tenant/invoices?status=unpaid`
- **Header**: `Authorization: Bearer <tenant_token>`

### 3.2 Membuat Link Checkout Tagihan
- **Method**: `POST`
- **URL**: `/api/v1/tenant/invoices/{id}/checkout`
- **Header**: `Authorization: Bearer <tenant_token>`

#### Response:
```json
{
  "message": "Checkout session created successfully.",
  "checkout_url": "https://staging.doku.com/checkout-link-v2/9b7f9a12-xxxx-xxxx-xxxx"
}
```

---

## 4. Contoh Komponen React (TypeScript + Tailwind CSS)

### Contoh Komponen: Keranjang Kamar & Checkout Langsung (`BookingCart.tsx`)

```tsx
import React, { useEffect, useState } from 'react';
import axios from 'axios';

interface CartItem {
  id: number;
  reference: string;
  property_name: string;
  unit_name: string;
  guest_name: string;
  start_date: string;
  duration_months: number;
  amount: number;
  checkout_url: string;
}

export function BookingCart() {
  const [cartToken, setCartToken] = useState<string>('');
  const [items, setItems] = useState<CartItem[]>([]);
  const [total, setTotal] = useState<number>(0);
  const [loading, setLoading] = useState<boolean>(true);

  // Ambil atau buat cart_token di browser
  useEffect(() => {
    let token = localStorage.getItem('openkos_cart_token');
    if (!token) {
      token = 'cart-' + Math.random().toString(36).substring(2, 15);
      localStorage.setItem('openkos_cart_token', token);
    }
    setCartToken(token);
    fetchCart(token);
  }, []);

  const fetchCart = async (token: string) => {
    try {
      setLoading(true);
      const res = await axios.get(`https://api.domain-anda.com/api/v1/cart?cart_token=${token}`);
      setItems(res.data.cart.items);
      setTotal(res.data.cart.total);
    } catch (err) {
      console.error('Gagal mengambil keranjang:', err);
    } finally {
      setLoading(false);
    }
  };

  const handlePay = (checkoutUrl: string) => {
    // Redirect langsung ke DOKU Hosted Checkout
    window.location.href = checkoutUrl;
  };

  const handleRemove = async (orderId: number) => {
    try {
      await axios.delete(`https://api.domain-anda.com/api/v1/cart/${orderId}`);
      fetchCart(cartToken);
    } catch (err) {
      alert('Gagal menghapus item dari keranjang.');
    }
  };

  if (loading) return <div className="p-4 text-gray-500">Memuat keranjang...</div>;

  if (items.length === 0) {
    return (
      <div className="p-6 text-center border rounded-xl bg-gray-50">
        <p className="text-gray-600">Keranjang pesanan kamar Anda masih kosong.</p>
      </div>
    );
  }

  return (
    <div className="p-6 bg-white rounded-2xl shadow border max-w-lg mx-auto">
      <h2 className="text-xl font-bold text-gray-900 mb-4">Keranjang Booking Kos</h2>

      <div className="space-y-4">
        {items.map((item) => (
          <div key={item.id} className="p-4 border rounded-xl bg-gray-50 flex justify-between items-start">
            <div>
              <h3 className="font-semibold text-gray-800">{item.unit_name}</h3>
              <p className="text-sm text-gray-500">{item.property_name}</p>
              <p className="text-xs text-gray-400 mt-1">Check-in: {item.start_date} ({item.duration_months} Bulan)</p>
              <p className="text-sm font-bold text-indigo-600 mt-2">
                Rp {item.amount.toLocaleString('id-ID')}
              </p>
            </div>
            <button
              onClick={() => handleRemove(item.id)}
              className="text-xs text-red-500 hover:text-red-700 underline"
            >
              Hapus
            </button>
          </div>
        ))}
      </div>

      <div className="mt-6 pt-4 border-t flex justify-between items-center">
        <span className="text-gray-600 font-medium">Total Tagihan:</span>
        <span className="text-xl font-black text-gray-900">
          Rp {total.toLocaleString('id-ID')}
        </span>
      </div>

      {items.length > 0 && items[0].checkout_url && (
        <button
          onClick={() => handlePay(items[0].checkout_url)}
          className="mt-6 w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl transition shadow-lg flex items-center justify-center gap-2"
        >
          Bayar Sekarang dengan DOKU (QRIS/VA)
        </button>
      )}
    </div>
  );
}
```

---

## 5. Ringkasan Tanya Jawab Frontend

| Pertanyaan | Solusi Frontend |
| :--- | :--- |
| **Kapan tenant akun dan lease dibuat?** | Dibuat otomatis oleh webhook setelah user selesai membayar di DOKU. Frontend tidak perlu membuat tenant/lease manual. |
| **Bagaimana jika browser user di-refresh?** | Simpan `cart_token` di `localStorage`. Panggil `GET /api/v1/cart?cart_token=...` saat halaman dimuat. |
| **Bagaimana jika link checkout sudah expired?** | Panggil `POST /api/v1/cart/{id}/checkout` untuk mendapatkan URL checkout baru. |
| **Metode bayar apa saja yang didukung?** | Halaman DOKU Checkout otomatis menampilkan seluruh channel aktif: QRIS (GoPay, OVO, ShopeePay, Dana), BCA/Mandiri/BRI/BNI Virtual Account, dan minimarket. |
