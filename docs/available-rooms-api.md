# Available Rooms & Properties API

This document describes the OpenKos REST API endpoint for querying properties and their available rooms.

---

## 1. Get All Properties & Available Rooms

Returns clean, lightweight property information including location, contact, price range, and available room numbers.

- **Method**: `GET`
- **URL**: `/api/v1/available-rooms`
- **Authentication**: Optional (Public by default; see [Authentication](#authentication) if secret is configured in `.env`).

### Query Parameters

| Parameter | Type | Description | Example |
| :--- | :--- | :--- | :--- |
| `property_id` | `integer` | Filter by specific property ID | `?property_id=5` |
| `property_slug` / `property` | `string` | Filter by property slug | `?property_slug=alpukat` |
| `city` | `string` | Filter by city name (partial match) | `?city=Jakarta` |
| `city_id` | `integer` | Filter by city ID | `?city_id=3` |
| `kecamatan` | `string` | Filter by kecamatan / district name (partial match) | `?kecamatan=Grogol` |
| `type` | `string` | Filter by property type slug | `?type=boarding_house` |
| `search` | `string` | Keyword search in property name, address, kecamatan, description, or city | `?search=Alpukat` |
| `min_price` | `number` | Minimum room rate amount | `?min_price=1000000` |
| `max_price` | `number` | Maximum room rate amount | `?max_price=2000000` |
| `only_available` | `boolean` | If `true` (default), only includes properties with at least 1 vacant room. If `false`, includes all properties. | `?only_available=false` |

---

### Example Request

```bash
curl -X GET "http://localhost:8080/api/v1/available-rooms?kecamatan=Grogol" \
  -H "Accept: application/json"
```

### Example Response (`200 OK`)

```json
{
  "success": true,
  "data": [
    {
      "name": "Alpukat",
      "slug": "alpukat",
      "description": "AC, WiFi, kasur, lemari, meja, kamar mandi dalam",
      "address_url": "https://maps.app.goo.gl/YBVADLa7G7E2q9fW8",
      "kecamatan": "Grogol",
      "phone": "+6285894999293",
      "image_url": "http://dashboard.highlanderstay.com/storage/properties/nMetAAm73uduAjuNUXSbkIqcF98g6SFQ18Na5A21.jpg",
      "available_rooms": [
        "301"
      ],
      "availability_status": "Ready 1 kamar",
      "price_range": "Rp 1.400.000/bulan"
    }
  ]
}
```

---

## 2. Get Single Property by Slug

- **Method**: `GET`
- **URL**: `/api/v1/properties/{property_slug}/available-rooms`

### Example Request

```bash
curl -X GET "http://localhost:8080/api/v1/properties/alpukat/available-rooms" \
  -H "Accept: application/json"
```

---

## Authentication

By default, the endpoint is public. If you wish to protect the endpoint, configure `OPENKOS_API_SECRET` or `API_SECRET` in your `.env` file:

```env
OPENKOS_API_SECRET=your-secure-api-key-here
```

When configured, pass the secret using any of the following:

- **HTTP Header**: `X-API-Key: your-secure-api-key-here`
- **Bearer Token**: `Authorization: Bearer your-secure-api-key-here`
- **Query Parameter**: `?api_key=your-secure-api-key-here`

---

## Response Field Reference

| Field | Type | Description |
| :--- | :--- | :--- |
| `name` | `string` | Name of the property |
| `slug` | `string` | Unique URL-safe slug for the property |
| `description` | `string\|null` | Property description & facility list |
| `address_url` | `string\|null` | Google Maps or address URL link |
| `kecamatan` | `string\|null` | District / Kecamatan of the property |
| `phone` | `string\|null` | WhatsApp or phone contact number |
| `image_url` | `string\|null` | Full accessible public URL of the property image banner |
| `available_rooms` | `array<string>` | List of currently vacant room numbers / names (e.g. `["301", "302"]`) |
| `availability_status` | `string` | Status in Indonesian (e.g. `"Ready 1 kamar"`, `"Belum ada kamar ready"`) |
| `price_range` | `string\|null` | Formatted price or price range in IDR (e.g. `"Rp 1.400.000/bulan"`, `"Rp 1.200.000 - Rp 1.800.000/bulan"`) |
