# Professional API Documentation - Event Ticket System

## Table of Contents
1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Endpoints](#endpoints)
   - [Ticket Endpoints](#ticket-endpoints)
   - [Payment Endpoints](#payment-endpoints)
4. [Response Format](#response-format)
5. [Error Handling](#error-handling)
6. [Examples](#examples)

---

## Overview

The Event Ticket System API provides comprehensive endpoints for managing tickets, processing payments, and handling user authentication. All endpoints return JSON responses with consistent formatting.

### Base URL
```
http://localhost:8000/api
```

### API Version
- Current: v1
- Last Updated: December 20, 2024

---

## Authentication

All protected endpoints require a **JWT Bearer Token** in the Authorization header.

### Getting a Token

**Endpoint:** `POST /api/login`

**Request:**
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "SecurePassword123!"
  }'
```

**Response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "email": "user@example.com",
  "roles": ["ROLE_PARTICIPANT"]
}
```

### Using the Token

Include the token in all subsequent requests:

```bash
curl -X GET http://localhost:8000/api/tickets \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

## Endpoints

### Ticket Endpoints

#### 1. List All User Tickets

**Endpoint:** `GET /api/tickets`

**Authentication:** Required (ROLE_USER)

**Description:** Retrieve all tickets owned by the authenticated user.

**Request:**
```bash
curl -X GET http://localhost:8000/api/tickets \
  -H "Authorization: Bearer <TOKEN>"
```

**Response (200 OK):**
```json
{
  "success": true,
  "count": 3,
  "tickets": [
    {
      "id": 1,
      "ticket_key": "ticket_63a9c8d0f3e2a",
      "event_name": "Tech Conference 2025",
      "ticket_type": "VIP",
      "price": "299.99",
      "quantity": 50,
      "available": true,
      "sold_out": false,
      "status": "ACTIVE",
      "issued_at": "2024-12-20 10:30:00",
      "expires_at": "2025-12-31 23:59:59",
      "qr_code": null
    }
  ]
}
```

---

#### 2. Get Single Ticket Details

**Endpoint:** `GET /api/tickets/{id}`

**Authentication:** Required (ROLE_USER)

**Parameters:**
- `id` (path, integer, required): Ticket ID

**Request:**
```bash
curl -X GET http://localhost:8000/api/tickets/1 \
  -H "Authorization: Bearer <TOKEN>"
```

**Response (200 OK):**
```json
{
  "success": true,
  "ticket": {
    "id": 1,
    "ticket_key": "ticket_63a9c8d0f3e2a",
    "event_name": "Tech Conference 2025",
    "ticket_type": "VIP",
    "price": "299.99",
    "quantity": 50,
    "available": true,
    "sold_out": false,
    "status": "ACTIVE",
    "issued_at": "2024-12-20 10:30:00",
    "expires_at": "2025-12-31 23:59:59"
  }
}
```

**Response (403 Forbidden):**
```json
{
  "error": "Unauthorized"
}
```

---

#### 3. Create Ticket

**Endpoint:** `POST /api/tickets`

**Authentication:** Required (ROLE_ADMIN only)

**Request Body:**
```json
{
  "event_name": "Tech Conference 2025",
  "ticket_type": "VIP",
  "price": "299.99",
  "quantity": 100,
  "expires_at": "2025-12-31T23:59:59Z"
}
```

**Request:**
```bash
curl -X POST http://localhost:8000/api/tickets \
  -H "Authorization: Bearer <ADMIN_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "event_name": "Tech Conference 2025",
    "ticket_type": "VIP",
    "price": "299.99",
    "quantity": 100
  }'
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Ticket created successfully",
  "ticket": {
    "id": 1,
    "ticket_key": "ticket_63a9c8d0f3e2a",
    "event_name": "Tech Conference 2025",
    "ticket_type": "VIP",
    "price": "299.99",
    "quantity": 100,
    "available": true,
    "sold_out": false,
    "status": "ACTIVE",
    "issued_at": "2024-12-20 10:30:00",
    "expires_at": null
  }
}
```

**Response (400 Bad Request):**
```json
{
  "error": "Missing required field: event_name"
}
```

**Response (403 Forbidden):**
```json
{
  "error": "Unauthorized - Admin role required"
}
```

**Required Fields:**
- `event_name` (string): Name of the event
- `ticket_type` (string): Type of ticket (GENERAL, VIP, STUDENT, etc.)
- `price` (number): Price per ticket (must be >= 0)
- `quantity` (integer): Number of tickets (must be > 0)

**Optional Fields:**
- `expires_at` (ISO 8601 datetime): When tickets expire

---

#### 4. Update Ticket

**Endpoint:** `PUT /api/tickets/{id}` or `PATCH /api/tickets/{id}`

**Authentication:** Required (ROLE_USER or ROLE_ADMIN)

**Parameters:**
- `id` (path, integer, required): Ticket ID

**Request Body (all fields optional):**
```json
{
  "quantity": 50,
  "status": "ACTIVE",
  "ticket_type": "VIP",
  "event_name": "Tech Conference 2025",
  "price": "349.99"
}
```

**Request:**
```bash
curl -X PUT http://localhost:8000/api/tickets/1 \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "quantity": 50,
    "status": "ACTIVE"
  }'
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Ticket updated successfully",
  "ticket": {
    "id": 1,
    "quantity": 50,
    "status": "ACTIVE"
  }
}
```

**Valid Status Values:**
- `ACTIVE` - Available for purchase
- `USED` - Already used/consumed
- `EXPIRED` - Past expiration date
- `CANCELLED` - Cancelled by admin

---

#### 5. Purchase Tickets (Decrement Quantity)

**Endpoint:** `POST /api/tickets/{id}/purchase`

**Authentication:** Required (ROLE_USER)

**Parameters:**
- `id` (path, integer, required): Ticket ID

**Request Body:**
```json
{
  "quantity": 5
}
```

**Request:**
```bash
curl -X POST http://localhost:8000/api/tickets/1/purchase \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "quantity": 5
  }'
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Successfully purchased 5 ticket(s)",
  "remaining_quantity": 45,
  "ticket": {
    "id": 1,
    "quantity": 45,
    "available": true,
    "sold_out": false
  }
}
```

**Response (409 Conflict - Sold Out):**
```json
{
  "success": false,
  "error": "Ticket sold out",
  "ticket": {
    "id": 1,
    "quantity": 0,
    "sold_out": true
  }
}
```

**Response (400 Bad Request - Insufficient Quantity):**
```json
{
  "success": false,
  "error": "Insufficient quantity. Available: 3",
  "available": 3
}
```

---

#### 6. Delete Ticket

**Endpoint:** `DELETE /api/tickets/{id}`

**Authentication:** Required (ROLE_USER or ROLE_ADMIN)

**Parameters:**
- `id` (path, integer, required): Ticket ID

**Request:**
```bash
curl -X DELETE http://localhost:8000/api/tickets/1 \
  -H "Authorization: Bearer <TOKEN>"
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Ticket deleted successfully"
}
```

**Response (403 Forbidden):**
```json
{
  "error": "Unauthorized"
}
```

---

#### 7. Get Ticket Statistics

**Endpoint:** `GET /api/tickets/stats/overview`

**Authentication:** Required (ROLE_ADMIN only)

**Description:** Get comprehensive statistics about all tickets.

**Request:**
```bash
curl -X GET http://localhost:8000/api/tickets/stats/overview \
  -H "Authorization: Bearer <ADMIN_TOKEN>"
```

**Response (200 OK):**
```json
{
  "success": true,
  "stats": {
    "total_tickets": 5,
    "active_tickets": 4,
    "sold_out_tickets": 1,
    "used_tickets": 0,
    "expired_tickets": 0,
    "cancelled_tickets": 0,
    "total_inventory": 500,
    "available_inventory": 450
  }
}
```

---

### Payment Endpoints

#### 1. Process Payment

**Endpoint:** `POST /api/payment/process`

**Authentication:** Required (ROLE_USER)

**Description:** Process a credit card payment and generate a ticket.

**Request Body:**
```json
{
  "amount": 299.99,
  "currency": "USD",
  "card_number": "4111111111111111",
  "expiry_month": 12,
  "expiry_year": 2025,
  "cvv": "123",
  "first_name": "John",
  "last_name": "Doe",
  "event_name": "Tech Conference 2025",
  "ticket_type": "VIP"
}
```

**Request:**
```bash
curl -X POST http://localhost:8000/api/payment/process \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 299.99,
    "currency": "USD",
    "card_number": "4111111111111111",
    "expiry_month": 12,
    "expiry_year": 2025,
    "cvv": "123",
    "first_name": "John",
    "last_name": "Doe",
    "event_name": "Tech Conference 2025",
    "ticket_type": "VIP"
  }'
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Payment processed successfully",
  "payment": {
    "id": "pay_abc123def456",
    "status": "COMPLETED",
    "amount": "299.99",
    "currency": "USD",
    "card_brand": "visa",
    "card_last_four": "1111",
    "transaction_id": "stripe_txn_..."
  },
  "ticket": {
    "key": "ticket_xyz789",
    "event_name": "Tech Conference 2025",
    "ticket_type": "VIP",
    "price": "299.99",
    "status": "ACTIVE",
    "issued_at": "2024-12-20 10:30:00",
    "expires_at": "2025-01-19 10:30:00"
  }
}
```

**Response (400 Bad Request):**
```json
{
  "error": "Invalid card number - failed Luhn check"
}
```

**Response (400 Bad Request - Expired Card):**
```json
{
  "error": "Card has expired"
}
```

**Test Card Numbers:**
- Visa: `4111 1111 1111 1111`
- Mastercard: `5555 5555 5555 4444`
- American Express: `3782 822463 10005`
- Discover: `6011 1111 1111 1117`

Use any future expiry date and any 3-4 digit CVV.

**Required Fields:**
- `amount` (number): Amount to charge (in dollars/euros, etc.)
- `currency` (string): 3-letter ISO currency code (USD, EUR, GBP, CAD)
- `card_number` (string): 13-19 digit card number
- `expiry_month` (integer): 1-12
- `expiry_year` (integer): 4-digit year
- `cvv` (string): 3-4 digit security code
- `first_name` (string): Cardholder first name
- `last_name` (string): Cardholder last name
- `event_name` (string): Event name
- `ticket_type` (string): Type of ticket

---

#### 2. Get Payment Status

**Endpoint:** `GET /api/payment/status/{paymentId}`

**Authentication:** Required (ROLE_USER)

**Parameters:**
- `paymentId` (path, string, required): Payment ID

**Request:**
```bash
curl -X GET http://localhost:8000/api/payment/status/pay_abc123def456 \
  -H "Authorization: Bearer <TOKEN>"
```

**Response (200 OK):**
```json
{
  "payment": {
    "id": "pay_abc123def456",
    "status": "COMPLETED",
    "amount": "299.99",
    "currency": "USD",
    "card_brand": "visa",
    "card_last_four": "1111",
    "created_at": "2024-12-20 10:30:00",
    "completed_at": "2024-12-20 10:30:05",
    "error_message": null
  },
  "ticket": {
    "key": "ticket_xyz789",
    "event_name": "Tech Conference 2025",
    "ticket_type": "VIP",
    "status": "ACTIVE",
    "issued_at": "2024-12-20 10:30:00",
    "expires_at": "2025-01-19 10:30:00"
  }
}
```

**Response (404 Not Found):**
```json
{
  "error": "Payment not found"
}
```

**Response (403 Forbidden):**
```json
{
  "error": "Unauthorized"
}
```

---

#### 3. Validate Card

**Endpoint:** `POST /api/payment/validate-card`

**Authentication:** Not Required

**Description:** Validate a card without processing a payment.

**Request Body:**
```json
{
  "card_number": "4111111111111111",
  "expiry_month": 12,
  "expiry_year": 2025,
  "cvv": "123"
}
```

**Request:**
```bash
curl -X POST http://localhost:8000/api/payment/validate-card \
  -H "Content-Type: application/json" \
  -d '{
    "card_number": "4111111111111111",
    "expiry_month": 12,
    "expiry_year": 2025,
    "cvv": "123"
  }'
```

**Response (200 OK):**
```json
{
  "valid": true,
  "card_brand": "visa",
  "card_last_four": "1111"
}
```

**Response (400 Bad Request):**
```json
{
  "valid": false,
  "error": "Invalid card number - failed Luhn check"
}
```

---

## Response Format

### Standard Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {}
}
```

### Standard Error Response
```json
{
  "success": false,
  "error": "Descriptive error message"
}
```

---

## Error Handling

### HTTP Status Codes

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | OK | Request successful |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Invalid input or missing required fields |
| 401 | Unauthorized | Missing or invalid JWT token |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 409 | Conflict | Conflict (e.g., sold out) |
| 429 | Too Many Requests | Brute force protection active |
| 500 | Server Error | Internal server error |

### Common Error Messages

**Invalid Card:**
```json
{
  "error": "Invalid card number - failed Luhn check"
}
```

**Card Expired:**
```json
{
  "error": "Card has expired"
}
```

**Invalid CVV:**
```json
{
  "error": "Invalid CVV"
}
```

**Insufficient Inventory:**
```json
{
  "error": "Insufficient quantity. Available: 5"
}
```

**Ticket Sold Out:**
```json
{
  "error": "Ticket sold out"
}
```

**Unauthorized:**
```json
{
  "error": "Unauthorized"
}
```

**Brute Force Locked:**
```json
{
  "error": "Account locked due to too many failed login attempts",
  "retry_after": 60
}
```

---

## Examples

### Complete Workflow

#### 1. Register User

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "SecurePassword123!",
    "first_name": "John",
    "last_name": "Doe"
  }'
```

#### 2. Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "SecurePassword123!"
  }'
```

Save the returned token for next steps.

#### 3. Create Ticket (as Admin)

```bash
curl -X POST http://localhost:8000/api/tickets \
  -H "Authorization: Bearer <ADMIN_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "event_name": "Tech Conference 2025",
    "ticket_type": "VIP",
    "price": "299.99",
    "quantity": 100
  }'
```

#### 4. Process Payment

```bash
curl -X POST http://localhost:8000/api/payment/process \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 299.99,
    "currency": "USD",
    "card_number": "4111111111111111",
    "expiry_month": 12,
    "expiry_year": 2025,
    "cvv": "123",
    "first_name": "John",
    "last_name": "Doe",
    "event_name": "Tech Conference 2025",
    "ticket_type": "VIP"
  }'
```

#### 5. List Tickets

```bash
curl -X GET http://localhost:8000/api/tickets \
  -H "Authorization: Bearer <TOKEN>"
```

#### 6. Check Payment Status

```bash
curl -X GET http://localhost:8000/api/payment/status/pay_abc123def456 \
  -H "Authorization: Bearer <TOKEN>"
```

---

## Rate Limiting & Security

### Brute Force Protection

- **5 failed login attempts:** Account locked for 60 seconds
- **8 total failed attempts:** Account locked for 120 seconds (doubled)
- **Pattern continues:** Each additional 5 failures doubles the lockout duration

### Response Header
```
HTTP/1.1 429 Too Many Requests
Retry-After: 60
```

---

## Versioning

The API follows semantic versioning. Current version is **v1**. 

Breaking changes will increment the major version. Non-breaking changes will increment the minor version.

---

## Support

For API issues or questions:
1. Check this documentation
2. Review error responses carefully
3. Check the dashboard for detailed error messages
4. Review code examples in the dashboard

---

**API Documentation v1.0**  
**Last Updated:** December 20, 2024  
**Status:** Production Ready
