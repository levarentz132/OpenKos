# Panduan Integrasi Pembayaran DOKU & Booking Keranjang untuk WhatsApp Bot (OpenKos)

Dokumentasi ini menjelaskan langkah-langkah teknis untuk mengintegrasikan layanan **DOKU Checkout (Jokul)** dan **Sistem Keranjang Pemesanan Kamar (Cart-First Booking)** ke dalam sistem **WhatsApp Bot** (misalnya bot berbasis Node.js/Baileys, Python, n8n, Fonnte, Wwebjs, atau WhatsApp Cloud API).

---

## 1. Dua Alur Transaksi Utama pada WhatsApp Bot

1. **Alur Calon Penghuni Baru (Booking Masuk Keranjang -> Bayar -> Otomatis Terbit Sewa)**:
   - Calon tamu memilih kamar lewat WA.
   - Bot memanggil endpoint `POST /api/v1/cart` (atau `/api/v1/orders`).
   - Sistem menyimpan pesanan ke database dengan status `pending` (kamar belum dikunci/belum berstatus occupied, belum ada sewa resmi).
   - Bot mengirimkan tautan DOKU Checkout ke calon penghuni.
   - Saat calon penghuni membayar (QRIS / VA / E-Wallet), webhook DOKU otomatis membuat akun tenant, menerbitkan kontrak sewa (`Lease`), mengunci kamar (`Occupied`), dan menerbitkan kwitansi lunas.
2. **Alur Penghuni Aktif (Bayar Tagihan Bulanan / Invoice Rutin)**:
   - Penghuni meminta tagihan sewa berjalan.
   - Bot mengambil tagihan via `GET /api/v1/tenant/invoices?status=unpaid`.
   - Bot meminta link checkout via `POST /api/v1/tenant/invoices/{id}/checkout` dan mengirimkannya ke penghuni.

---

## 2. Alur 1: Calon Tamu Booking Kamar (Keranjang & Sewa Otomatis)

### Langkah 1: Calon Tamu Menanyakan Kamar Tersedia
Bot memanggil endpoint kamar kosong:
- **Method**: `GET`
- **URL**: `https://api.domain-anda.com/api/v1/available-rooms`

#### Contoh Pesan WhatsApp ke Calon Tamu:
```text
Halo Kak Budi! 👋
Berikut kamar yang sedang tersedia di Highlander Stay:

1. Kamar 101 (Tipe Deluxe) - Rp 1.750.000 / bulan
2. Kamar 102 (Tipe Superior) - Rp 1.500.000 / bulan

Ketik: BOOKING [Nomor Kamar] [Tanggal Mulai: YYYY-MM-DD]
Contoh: BOOKING 101 2026-10-01
```

---

### Langkah 2: Bot Menyimpan Booking ke Keranjang (`POST /api/v1/cart`)

Ketika pengguna membalas `BOOKING 101 2026-10-01`:

- **Method**: `POST`
- **URL**: `https://api.domain-anda.com/api/v1/cart`
- **Body**:
  ```json
  {
    "unit_id": 5,
    "name": "Budi Santoso",
    "phone": "6281299998888",
    "email": "budi@example.com",
    "start_date": "2026-10-01",
    "duration_months": 1,
    "notes": "Booking via WhatsApp Bot"
  }
  ```

#### Response dari API OpenKos:
```json
{
  "message": "Booking order created and saved in cart. Please complete payment to confirm your lease.",
  "order": {
    "id": 14,
    "reference": "BK-X8K2M9LP1Q",
    "amount": 1750000.0,
    "checkout_url": "https://staging.doku.com/checkout-link-v2/9b7f9a12-xxxx-xxxx-xxxx",
    "expires_at": "2026-09-16T11:45:00+00:00",
    "lease_created": false
  }
}
```

---

### Langkah 3: Bot Mengirimkan Link Pembayaran ke Calon Tamu

Bot merespons pesan WhatsApp calon tamu:

```text
Pesanan kamar berhasil dicatat di keranjang! 🛒

Detail Pemesanan:
• Kamar: 101 (Highlander Stay)
• Check-in: 01 Oktober 2026
• Durasi: 1 Bulan
• Total Biaya: Rp 1.750.000

Silakan selesaikan pembayaran melalui tautan resmi DOKU berikut untuk mengaktifkan sewa kamar Anda:
👉 https://staging.doku.com/checkout-link-v2/9b7f9a12-xxxx-xxxx-xxxx

(Bisa bayar via QRIS GoPay/OVO/Dana, BCA VA, Mandiri VA, BRI, atau BNI)
Link berlaku selama 60 menit.
```

---

### Langkah 4: Pembayaran Berhasil & Notifikasi Otomatis
1. Tamu membuka link dan membayar via QRIS / VA.
2. DOKU mengirim webhook callback ke OpenKos backend:
   ```http
   POST /api/webhooks/payment/doku
   ```
3. OpenKos secara atomik:
   - Membuat akun User & Tenant.
   - Menerbitkan kontrak sewa aktif (**Lease**).
   - Mengubah kamar 101 menjadi **Occupied**.
   - Menerbitkan Invoice & mencatat pembayaran sukses di tabel `payments`.
4. Bot (melalui event webhook atau n8n automation) dapat langsung mengirim pesan selamat datang:

```text
Hore! Pembayaran sebesar Rp 1.750.000 telah kami terima. 🎉

Sewa Kamar 101 Anda kini telah RESMI AKTIF!
Kwitansi pembayaran Anda telah diterbitkan.

Selamat bergabung di Highlander Stay! Jika ada pertanyaan atau kebutuhan selama tinggal, Anda dapat chat bot ini kapan saja. 🙏
```

---

## 3. Alur 2: Penghuni Lama Bayar Tagihan Rutin

### Langkah 1: Cek Tagihan Belum Lunas
- **Method**: `GET`
- **URL**: `https://api.domain-anda.com/api/v1/tenant/invoices?status=unpaid`
- **Header**: `Authorization: Bearer <tenant_token>`

### Langkah 2: Buat Link Checkout Tagihan
- **Method**: `POST`
- **URL**: `https://api.domain-anda.com/api/v1/tenant/invoices/{invoiceId}/checkout`
- **Header**: `Authorization: Bearer <tenant_token>`

Bot mengirim link checkout tagihan ke penghuni. Setelah dibayar, invoice otomatis berubah status menjadi `Paid` tanpa perlu upload bukti transfer atau verifikasi admin manual.

---

## 4. Contoh Kode Bot WhatsApp (Node.js / Baileys / Wwebjs)

```javascript
const axios = require('axios');

const API_BASE = 'https://api.domain-anda.com/api/v1';

// Handler saat calon penghuni mengetik pesan booking
async function handleBookingCommand(sock, senderJid, senderName, unitId, startDate) {
  const cleanPhone = senderJid.replace('@s.whatsapp.net', '');

  try {
    // 1. Simpan pemesanan ke keranjang API OpenKos
    const response = await axios.post(`${API_BASE}/cart`, {
      unit_id: parseInt(unitId, 10),
      name: senderName || 'Tamu WhatsApp',
      phone: cleanPhone,
      start_date: startDate,
      duration_months: 1,
      notes: 'Pemesanan melalui WhatsApp Bot',
    });

    const order = response.data.order;

    // 2. Format pesan ramah pengguna beserta link checkout DOKU
    const replyMessage = 
      `*Pemesanan Berhasil Disimpan di Keranjang!* 🛒\n\n` +
      `Nomor Referensi: *${order.reference}*\n` +
      `Kamar: *${order.unit.name}* (${order.property.name})\n` +
      `Check-in: *${order.period.start_date}*\n` +
      `Total: *Rp ${order.amount.toLocaleString('id-ID')}*\n\n` +
      `Silakan selesaikan pembayaran untuk mengunci kamar dan mengaktifkan sewa Anda:\n` +
      `👉 ${order.checkout_url}\n\n` +
      `_Tersedia pembayaran via QRIS, BCA VA, Mandiri VA, BNI, BRI, OVO, dan ShopeePay._\n` +
      `_Kontrak sewa kamar akan terbit otomatis setelah pembayaran berhasil diproses._`;

    await sock.sendMessage(senderJid, { text: replyMessage });
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Maaf, gagal memproses booking kamar.';
    await sock.sendMessage(senderJid, { text: `⚠️ ${errorMsg}` });
  }
}
```

---

## 5. Ringkasan Endpoint untuk WhatsApp Bot

| Endpoint | Method | Keterangan |
| :--- | :---: | :--- |
| `/api/v1/available-rooms` | `GET` | Menampilkan daftar kamar kosong yang dapat dipilih. |
| `/api/v1/cart` | `POST` | Menyimpan kamar ke keranjang & langsung mengembalikan link DOKU Checkout. |
| `/api/v1/cart?phone={phone}` | `GET` | Melihat daftar booking pending milik nomor WhatsApp tersebut. |
| `/api/v1/cart/{id}` | `DELETE` | Membatalkan / menghapus booking dari keranjang. |
| `/api/v1/cart/{id}/checkout` | `POST` | Menerbitkan ulang link checkout DOKU jika sudah kedaluwarsa. |
| `/api/v1/tenant/invoices?status=unpaid` | `GET` | Melihat tagihan sewa berjalan bagi penghuni terdaftar. |
| `/api/v1/tenant/invoices/{id}/checkout` | `POST` | Menerbitkan link DOKU untuk tagihan sewa berjalan. |
| `/api/webhooks/payment/doku` | `POST` | Webhook otomatis dari DOKU: saat pembayaran sukses, otomatis menerbitkan sewa, tagihan, dan pembayaran lunas. |

---

## 6. Penanganan Jika Kamar Sudah Dibayar Pengguna Lain (Manajemen Konflik Bot)

Jika calon penyewa A menunda pembayaran, lalu calon penyewa B membayar kamar yang sama lebih dulu:

1. Sistem OpenKos otomatis membatalkan pesanan penyewa A (`status: cancelled`).
2. Jika penyewa A mengklik link checkout lama atau meminta link pembayaran ulang ke Bot via `POST /api/v1/cart/{orderId}/checkout`:
   - API merespons dengan HTTP `422 (ROOM_ALREADY_PAID)`.
   - Bot dapat membalas dengan pesan informatif:

```text
Mohon maaf Kak, Kamar 101 baru saja disewa dan dibayar oleh calon penghuni lain yang menyelesaikan pembayaran lebih dulu. 🙏

Kamar lain yang masih tersedia saat ini:
1. Kamar 102 (Tipe Superior) - Rp 1.500.000 / bulan
2. Kamar 103 (Tipe Deluxe) - Rp 1.750.000 / bulan

Ketik: BOOKING [Nomor Kamar] [Tanggal Check-in] untuk memesan kamar pengganti.
```
