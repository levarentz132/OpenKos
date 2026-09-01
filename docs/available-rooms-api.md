# Available Rooms & Properties API

This document describes the OpenKos REST API endpoints for querying properties and their currently available rooms.

---

## 1. Get All Properties & Available Rooms

Returns all active properties with their full property details (image, address, Google Maps link, phone, description, location) along with their currently available/vacant rooms and rental rates.

- **Method**: `GET`
- **URL**: `/api/v1/available-rooms`
- **Authentication**: Optional (Public by default; see [Authentication](#authentication) if secret is configured in `.env`).

### Query Parameters

| Parameter | Type | Description | Example |
| :--- | :--- | :--- | :--- |
| `property_id` | `integer` | Filter by specific property ID | `?property_id=1` |
| `property_slug` / `property` | `string` | Filter by property slug | `?property_slug=kemang-exclusive` |
| `city` | `string` | Filter by city name (partial match) | `?city=Jakarta` |
| `city_id` | `integer` | Filter by city ID | `?city_id=3` |
| `type` | `string` | Filter by property type slug | `?type=boarding_house` |
| `search` | `string` | Keyword search in property name, address, description, city, or room name | `?search=Kemang` |
| `min_price` | `number` | Minimum room rate amount | `?min_price=1500000` |
| `max_price` | `number` | Maximum room rate amount | `?max_price=3000000` |
| `only_available` | `boolean` | If `true` (default), only includes properties that currently have at least one vacant room. If `false`, includes all active properties. | `?only_available=false` |

---

### Example Request

```bash
curl -X GET "http://localhost:8080/api/v1/available-rooms?city=Jakarta" \
  -H "Accept: application/json"
```

### Example Response (`200 OK`)

```json
{
  "success": true,
  "total_properties": 1,
  "total_available_rooms": 2,
  "properties": [
    {
      "id": 1,
      "name": "Kos Exclusive Kemang",
      "slug": "kos-exclusive-kemang",
      "type": "boarding_house",
      "type_label": "Boarding House",
      "description": "Kost eksklusif nyaman dan strategis dengan fasilitas AC, WiFi kencang, dan keamanan 24 jam.",
      "address": "Jl. Kemang Raya No. 45",
      "address_url": "https://maps.google.com/?q=-6.2608,106.8166",
      "city": "Jakarta Selatan",
      "province": "DKI Jakarta",
      "region": "DKI Jakarta",
      "postal_code": "12730",
      "phone": "+6281234567890",
      "image": "properties/kemang-facade.jpg",
      "image_url": "http://localhost:8080/storage/properties/kemang-facade.jpg",
      "total_units": 10,
      "occupied_units": 8,
      "available_rooms_count": 2,
      "occupancy_rate": 80.0,
      "availability_status": "Ready 2 room(s)",
      "available_rooms": [
        {
          "id": 101,
          "name": "Room 101",
          "floor": "1",
          "price": 2500000,
          "price_formatted": "Rp 2.500.000/month",
          "status": "available"
        },
        {
          "id": 102,
          "name": "Room 102",
          "floor": "1",
          "price": 2700000,
          "price_formatted": "Rp 2.700.000/month",
          "status": "available"
        }
      ]
    }
  ]
}
```

---

## 2. Get Available Rooms for a Single Property

Returns the details and available rooms for a specific property using its route slug.

- **Method**: `GET`
- **URL**: `/api/v1/properties/{property_slug}/available-rooms`

### Example Request

```bash
curl -X GET "http://localhost:8080/api/v1/properties/kos-exclusive-kemang/available-rooms" \
  -H "Accept: application/json"
```

---

## Authentication

By default, the endpoint is public. If you wish to protect the endpoint, set `OPENKOS_API_SECRET` or `API_SECRET` in your `.env` file:

```env
OPENKOS_API_SECRET=your-secure-api-key-here
```

When configured, pass the secret using any of the following methods:

- **HTTP Header**: `X-API-Key: your-secure-api-key-here`
- **Bearer Token**: `Authorization: Bearer your-secure-api-key-here`
- **Query Parameter**: `?api_key=your-secure-api-key-here`

If the secret does not match, the API will respond with `401 Unauthorized`:

```json
{
  "success": false,
  "message": "Unauthorized: Invalid API key or token."
}
```

---

## Field Reference

### Property Fields
| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `integer` | Property unique database ID |
| `name` | `string` | Property display name |
| `slug` | `string` | URL-safe property slug |
| `type` | `string` | Property type slug (e.g., `boarding_house`, `apartment`, `villa`) |
| `type_label` | `string` | Human-readable property type name |
| `description` | `string|null` | Property overview description and facilities |
| `address` | `string|null` | Physical street address |
| `address_url` | `string|null` | Google Maps / map location URL |
| `city` | `string|null` | City / Regency name |
| `province` / `region` | `string|null` | Province / State name |
| `postal_code` | `string|null` | Postal ZIP code |
| `phone` | `string|null` | Contact / WhatsApp phone number |
| `image` | `string|null` | Stored relative image file path |
| `image_url` | `string|null` | Full accessible public URL for the property image banner |
| `total_units` | `integer` | Total number of units in the property |
| `occupied_units` | `integer` | Number of currently occupied units |
| `available_rooms_count` | `integer` | Number of currently vacant/available rooms |
| `occupancy_rate` | `number` | Current occupancy percentage (0 - 100%) |
| `availability_status` | `string` | Readiness label (e.g. `"Ready 2 room(s)"`) |
| `available_rooms` | `array` | List of vacant room objects |

### Room Fields (`available_rooms[]`)
| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `integer` | Unit / Room ID |
| `name` | `string` | Room name or room number (e.g. `"101"`, `"Room A"`) |
| `floor` | `string|null` | Floor level |
| `price` | `number|null` | Primary active rate amount |
| `price_formatted` | `string|null` | Formatted price string (e.g. `"Rp 2.500.000/month"`) |
| `status` | `string` | Current unit status (`"available"`) |
