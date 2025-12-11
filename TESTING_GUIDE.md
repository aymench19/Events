# 🎫 Event Project - Complete Testing Steps

## 📋 Quick Start

### 1. Start the Server
```bash
cd "c:\Users\User\Desktop\Event Project\EventProject"
symfony server:start
# Or: php -S localhost:8000 -t public
```
Server should be at: `http://localhost:8000`

### 2. Database Status
✅ Migrations applied  
✅ `payment_id` is nullable  
✅ All tables created

---

## 🧪 Test Scenarios

### Step 1: Register User

**URL:** `http://localhost:8000/register`

**Form:**
- Email: `test@example.com`
- Password: `SecurePass123!`
- Confirm: `SecurePass123!`

**Expected:** Success message + redirect to login

**Database Check:**
```bash
php bin/console dbal:run-sql "SELECT id, email, roles FROM users WHERE email='test@example.com';"
```

---

### Step 2: Login & Get JWT Token

**URL:** `http://localhost:8000/login`

**Form:**
- Username: `test@example.com`
- Password: `SecurePass123!`

**Expected:** Redirect to dashboard

**Extract JWT Token:**
1. Open DevTools (F12) → Application/Storage
2. Check Cookies → look for `JWT` or session cookie
3. Or check Network tab → Response headers

**Test Brute Force (Optional):**
- Enter wrong password 10 times
- Should see: "Account locked for 5 minutes"
- Wait or modify DB: `UPDATE login_attempts SET locked_until = NOW() WHERE user_id = 1;`

---

### Step 3: Create Ticket (Admin Only)

**Using Curl:**
```bash
curl -X POST http://localhost:8000/api/tickets \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "event_name": "Summer Music Festival 2025",
    "ticket_type": "VIP",
    "price": "99.99",
    "quantity": 50
  }'
```

**Expected Response (201 Created):**
```json
{
  "success": true,
  "message": "Ticket created successfully",
  "ticket": {
    "id": 1,
    "event_name": "Summer Music Festival 2025",
    "ticket_type": "VIP",
    "price": "99.99",
    "quantity": 50,
    "sold_out": false,
    "status": "ACTIVE"
  }
}
```

**Database Check:**
```bash
php bin/console dbal:run-sql "SELECT id, event_name, quantity, payment_id FROM tickets WHERE id=1;"
```

Expected: `payment_id = NULL` (nullable)

---

### Step 4: Get Available Tickets (Shop List)

**Using Curl:**
```bash
curl -X GET http://localhost:8000/api/payment/available-tickets \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Expected Response (200 OK):**
```json
{
  "success": true,
  "count": 1,
  "tickets": [
    {
      "id": 1,
      "event_name": "Summer Music Festival 2025",
      "ticket_type": "VIP",
      "price": "99.99",
      "quantity": 50,
      "sold_out": false,
      "expires_at": "2025-01-10T12:00:00"
    }
  ]
}
```

---

### Step 5: Validate Card (Pre-Check)

**Using Curl:**
```bash
curl -X POST http://localhost:8000/api/payment/validate-card \
  -H "Content-Type: application/json" \
  -d '{
    "card_number": "4242424242424242",
    "expiry_month": "12",
    "expiry_year": "2026",
    "cvv": "123",
    "first_name": "John",
    "last_name": "Doe"
  }'
```

**Expected Response (200 OK):**
```json
{
  "valid": true,
  "message": "Card details are valid"
}
```

**Test Invalid Card:**
```bash
# Use: card_number: "4000000000000002" (Visa decline card)
# Should return: valid: false, error: "Invalid card number" or similar
```

---

### Step 6: Process Payment (Main Test)

**Using Curl:**
```bash
curl -X POST http://localhost:8000/api/payment/process \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "ticket_id": 1,
    "quantity": 2,
    "card_number": "4242424242424242",
    "expiry_month": "12",
    "expiry_year": "2026",
    "cvv": "123",
    "first_name": "John",
    "last_name": "Doe",
    "amount": "199.98",
    "currency": "USD",
    "event_name": "Summer Music Festival 2025"
  }'
```

**Expected Response (201 Created):**
```json
{
  "success": true,
  "message": "Payment processed successfully",
  "payment": {
    "id": "pay_...",
    "status": "COMPLETED",
    "amount": "199.98",
    "currency": "USD",
    "card_brand": "VISA",
    "card_last_four": "4242",
    "transaction_id": "ch_..."
  },
  "ticket": {
    "key": "ticket_...",
    "event_name": "Summer Music Festival 2025",
    "ticket_type": "VIP",
    "price": "99.99",
    "status": "ACTIVE"
  }
}
```

**Critical Database Checks:**
```bash
# Check payment was created
php bin/console dbal:run-sql "SELECT id, user_id, status, amount FROM payments ORDER BY id DESC LIMIT 1;"

# Check ticket quantity was decremented
php bin/console dbal:run-sql "SELECT id, quantity, payment_id FROM tickets WHERE id=1;"
# Should now show: quantity = 48 (was 50, decremented by 2), payment_id = payment ID
```

---

### Step 7: Test Oversell Prevention

**Goal:** Prevent buying more tickets than available

**Setup:** Create new ticket with only 1 available
```bash
curl -X POST http://localhost:8000/api/tickets \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "event_name": "Limited Event",
    "ticket_type": "GENERAL",
    "price": "49.99",
    "quantity": 1
  }'
```

**Attempt 1: Buy 1 ticket (should succeed)**
```bash
curl -X POST http://localhost:8000/api/payment/process \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "ticket_id": 2,
    "quantity": 1,
    "card_number": "4242424242424242",
    "expiry_month": "12",
    "expiry_year": "2026",
    "cvv": "123",
    "first_name": "Jane",
    "last_name": "Smith",
    "amount": "49.99",
    "currency": "USD",
    "event_name": "Limited Event"
  }'
```

**Expected:** ✅ 201 Created + payment succeeds

**Attempt 2: Buy another 1 ticket (should fail)**
```bash
# Run same request immediately after
# Should get: 409 Conflict - "Insufficient inventory at finalization"
```

**Why?** The transaction uses `FOR UPDATE` to lock the ticket row, rechecks availability, and refunds the charge if inventory is insufficient.

**Verify:**
```bash
# Check both payments exist
php bin/console dbal:run-sql "SELECT id, status, amount FROM payments WHERE status IN ('COMPLETED', 'FAILED');"

# Ticket should have quantity = 0 (not negative, not -1)
php bin/console dbal:run-sql "SELECT id, quantity FROM tickets WHERE id=2;"
```

---

### Step 8: Check Payment Status

**Using Curl:**
```bash
curl -X GET http://localhost:8000/api/payment/status/pay_abc123... \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

Replace `pay_abc123...` with actual payment ID from Step 6 response.

**Expected Response:**
```json
{
  "payment": {
    "id": "pay_...",
    "status": "COMPLETED",
    "amount": "199.98",
    "currency": "USD",
    "card_brand": "VISA",
    "card_last_four": "4242",
    "created_at": "2025-12-11T10:30:00",
    "completed_at": "2025-12-11T10:30:05"
  },
  "ticket": {
    "key": "ticket_...",
    "event_name": "Summer Music Festival 2025",
    "status": "ACTIVE",
    "quantity": 2
  }
}
```

---

### Step 9: Dashboard / Shop UI Test

**URL:** `http://localhost:8000/dashboard`

**Manual Test (Browser):**
1. ✅ Login successfully
2. ✅ See "Shop" tab with available tickets
3. ✅ Each ticket shows: name, price, available quantity, "Sold Out?" badge
4. ✅ Click ticket → quantity selector appears (max = available quantity)
5. ✅ Click "Buy Now" → Payment form opens
6. ✅ Fill card details
7. ✅ Click "Pay" → form submits to `/api/payment/process`
8. ✅ On success: show payment confirmation with ticket key
9. ✅ On failure: show error message

**Automated Test (Check Console):**
Open DevTools (F12) → Console:
```javascript
// Should see API calls:
// GET /api/payment/available-tickets → 200 OK
// POST /api/payment/validate-card → 200 OK (optional)
// POST /api/payment/process → 201 Created (success) or 409 (conflict)
```

---

### Step 10: List User Tickets

**Using Curl:**
```bash
curl -X GET http://localhost:8000/api/tickets \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Expected Response:**
```json
{
  "success": true,
  "count": 1,
  "tickets": [
    {
      "id": 1,
      "event_name": "Summer Music Festival 2025",
      "ticket_type": "VIP",
      "price": "99.99",
      "quantity": 48,
      "sold_out": false,
      "status": "ACTIVE",
      "issued_at": "2025-12-11T10:30:00",
      "expires_at": "2026-01-10T10:30:00"
    }
  ]
}
```

---

## 🔐 Security Tests

### JWT Token Validation

**Test Missing Token:**
```bash
curl -X GET http://localhost:8000/api/tickets
# Should return: 401 Unauthorized
```

**Test Invalid Token:**
```bash
curl -X GET http://localhost:8000/api/tickets \
  -H "Authorization: Bearer invalid_token_12345"
# Should return: 401 Unauthorized
```

**Test Expired Token:**
- JWT expires after 1 hour
- Generate new token after expiry
- Token should include `exp` claim

### CSRF Protection

- Form login should use CSRF tokens
- Check `<input name="_csrf_token" ... >` in login form
- POST requests should include CSRF token

---

## 📊 Test Cards (Stripe)

| Card Type | Number | Exp | CVC | Result |
|-----------|--------|-----|-----|--------|
| Visa | 4242424242424242 | 12/26 | 123 | ✅ Success |
| Visa | 4000000000000002 | 12/26 | 123 | ❌ Decline |
| Mastercard | 5555555555554444 | 12/26 | 123 | ✅ Success |
| Amex | 378282246310005 | 12/26 | 1234 | ✅ Success |
| Discover | 6011111111111117 | 12/26 | 123 | ✅ Success |

---

## ✅ Verification Checklist

- [ ] User can register and login
- [ ] JWT token is generated after login
- [ ] Admin can create tickets (quantity=50)
- [ ] Tickets appear in available-tickets endpoint
- [ ] Card validation rejects invalid cards
- [ ] Payment processes successfully for valid cards
- [ ] Ticket quantity decrements after payment
- [ ] Cannot oversell (409 Conflict when no inventory)
- [ ] Failed payment triggers refund
- [ ] Brute force blocks after 10 attempts
- [ ] Dashboard shows tickets and payment form
- [ ] Payment status endpoint returns correct data
- [ ] User can view purchased tickets
- [ ] JWT token required for API calls

---

## Dashboard Testing

### Accessing the Dashboard

1. **Login:** `http://localhost:8000/login`
   - Email: testuser@example.com
   - Password: TestPassword123!

2. **Dashboard:** `http://localhost:8000/dashboard`
   - Professional testing interface loads automatically

### Dashboard Features

#### 📊 Dashboard Tab
- View ticket statistics
- See inventory summary
- Monitor active/sold out tickets
- Check total inventory

#### 🎟️ Ticket Management Tab

**Create Ticket:**
1. Click "+ Create Ticket" button
2. Fill in form:
   - Event Name: "Tech Conference 2025"
   - Ticket Type: "VIP"
   - Price: "299.99"
   - Quantity: "100"
   - Expires At: Optional
3. Click "Create Ticket"
4. Verify success message appears

**View Tickets:**
- All created tickets display in a card layout
- Shows: Event name, type, price, quantity, status
- "SOLD OUT" badge appears when quantity = 0

**Purchase Tickets:**
1. Click "Purchase" button on any ticket
2. Enter quantity to purchase
3. Quantity decrements automatically
4. If quantity reaches 0, ticket shows "SOLD OUT"

**Edit Tickets:**
1. Click "Edit" button
2. Modify quantity or status
3. Changes apply immediately

**Delete Tickets:**
1. Click "Delete" button
2. Confirm deletion
3. Ticket removed from list

#### 💳 Payments Tab
- View all payment history
- See payment status and transaction details
- Check associated tickets
- Filter by status (COMPLETED, FAILED, PENDING)

#### ➕ New Payment Tab

**Creating a Payment:**

1. Fill Payment Details:
   ```
   Event Name: Tech Conference 2025
   Ticket Type: VIP
   Amount: $299.99
   Currency: USD
   ```

2. Fill Card Details:
   ```
   Card Number: 4111 1111 1111 1111 (Test Visa)
   Expiry Month: 12
   Expiry Year: 2025
   CVV: 123
   First Name: John
   Last Name: Doe
   ```

3. Click "Process Payment"

4. Success Response Shows:
   ```
   Payment ID: pay_xxxxx
   Status: COMPLETED
   Ticket Key: ticket_xxxxx
   ```

**Test Card Numbers (Always Success):**
```
Visa:        4111 1111 1111 1111
Mastercard:  5555 5555 5555 4444
Amex:        3782 822463 10005
Discover:    6011 1111 1111 1117
```

Use any future expiry date and any 3-4 digit CVV.

#### 🧪 API Testing Tab

Direct API testing console:

1. **Select Endpoint:**
   - GET /api/tickets
   - POST /api/payment/process
   - GET /api/payment/status/{id}
   - etc.

2. **Enter Request Body (JSON):**
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

3. **Click "Execute Request"**

4. **View Response:**
   - Status code
   - JSON response
   - Success/error messages

#### 📖 Documentation Tab
- Full API reference
- All endpoints listed
- Example requests and responses
- Status codes explained

---

## API Testing

### Using cURL

#### Test 1: Register User

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "email":"newuser@example.com",
    "password":"SecurePass123!",
    "first_name":"Jane",
    "last_name":"Doe"
  }'
```

Expected Response (201):
```json
{
  "success": true,
  "message": "User registered successfully",
  "user": {
    "id": 2,
    "email": "newuser@example.com",
    "first_name": "Jane",
    "last_name": "Doe",
    "roles": ["ROLE_PARTICIPANT"]
  }
}
```

#### Test 2: Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email":"testuser@example.com",
    "password":"TestPassword123!"
  }'
```

Expected Response (200):
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "email": "testuser@example.com",
  "roles": ["ROLE_PARTICIPANT"]
}
```

**Save token for next requests:**
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."
```

#### Test 3: List Tickets

```bash
curl -X GET http://localhost:8000/api/tickets \
  -H "Authorization: Bearer $TOKEN"
```

Expected Response (200):
```json
{
  "success": true,
  "count": 0,
  "tickets": []
}
```

#### Test 4: Create Ticket (Admin only)

```bash
curl -X POST http://localhost:8000/api/tickets \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "event_name": "Tech Summit 2025",
    "ticket_type": "STANDARD",
    "price": "99.99",
    "quantity": 500
  }'
```

Expected Response (201):
```json
{
  "success": true,
  "message": "Ticket created successfully",
  "ticket": {
    "id": 1,
    "event_name": "Tech Summit 2025",
    "quantity": 500,
    "sold_out": false
  }
}
```

#### Test 5: Get Ticket Details

```bash
curl -X GET http://localhost:8000/api/tickets/1 \
  -H "Authorization: Bearer $TOKEN"
```

#### Test 6: Update Ticket Quantity

```bash
curl -X PUT http://localhost:8000/api/tickets/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "quantity": 400,
    "status": "ACTIVE"
  }'
```

Expected Response (200):
```json
{
  "success": true,
  "message": "Ticket updated successfully"
}
```

#### Test 7: Purchase Tickets

```bash
curl -X POST http://localhost:8000/api/tickets/1/purchase \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "quantity": 5
  }'
```

Expected Response (200):
```json
{
  "success": true,
  "message": "Successfully purchased 5 ticket(s)",
  "remaining_quantity": 395
}
```

#### Test 8: Test Sold Out

Run the purchase command multiple times (50+ times with quantity 10) until:

```json
{
  "success": false,
  "error": "Ticket sold out"
}
```

Response Status: **409 Conflict**

#### Test 9: Process Payment

```bash
curl -X POST http://localhost:8000/api/payment/process \
  -H "Authorization: Bearer $TOKEN" \
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
    "event_name": "Tech Summit 2025",
    "ticket_type": "STANDARD"
  }'
```

Expected Response (201):
```json
{
  "success": true,
  "message": "Payment processed successfully",
  "payment": {
    "id": "pay_...",
    "status": "COMPLETED"
  },
  "ticket": {
    "key": "ticket_...",
    "event_name": "Tech Summit 2025"
  }
}
```

#### Test 10: Check Payment Status

```bash
curl -X GET http://localhost:8000/api/payment/status/pay_abc123 \
  -H "Authorization: Bearer $TOKEN"
```

---

## Ticket Management

### Quantity & Sold Out Testing

#### Test Case: Create Ticket with Low Quantity

```bash
curl -X POST http://localhost:8000/api/tickets \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "event_name": "Limited Concert",
    "ticket_type": "VVIP",
    "price": "499.99",
    "quantity": 5
  }'
```

#### Test Case: Sell All Tickets

1. Purchase 5 tickets (1 at a time)
2. Response shows: `"remaining_quantity": 0`
3. Ticket shows: `"sold_out": true`
4. Dashboard shows: "SOLD OUT" badge

#### Test Case: Try to Buy from Sold Out

```bash
curl -X POST http://localhost:8000/api/tickets/1/purchase \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"quantity": 1}'
```

Response (409):
```json
{
  "success": false,
  "error": "Ticket sold out"
}
```

#### Test Case: Manually Update Quantity

```bash
curl -X PUT http://localhost:8000/api/tickets/1 \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"quantity": 10}'
```

Result: Tickets back in stock, "SOLD OUT" badge removed.

### CRUD Operations

#### Create (POST)
✅ Creates new ticket with all fields
✅ Validates required fields
✅ Returns 201 status
✅ Returns full ticket object

#### Read (GET)
✅ Lists all user tickets
✅ Gets single ticket details
✅ Checks ownership (403 if not owner)
✅ Shows sold out status

#### Update (PUT/PATCH)
✅ Updates quantity
✅ Updates status
✅ Updates event name, type, price
✅ Validates new values
✅ Returns 200 status

#### Delete (DELETE)
✅ Removes ticket from database
✅ Checks ownership
✅ Returns 200 status
✅ Removes from list

---

## Payment Processing

### Successful Payment Flow

```
1. Register → 2. Login → 3. Process Payment → 4. Check Status
```

### Test Payment States

#### Pending Payment
```json
{
  "payment": {
    "status": "PENDING",
    "completed_at": null
  }
}
```

#### Completed Payment
```json
{
  "payment": {
    "status": "COMPLETED",
    "completed_at": "2024-12-20 10:35:00"
  }
}
```

#### Failed Payment
```json
{
  "payment": {
    "status": "FAILED",
    "error_message": "Card has expired"
  }
}
```

### Card Validation Tests

#### Test 1: Invalid Card Number

```bash
curl -X POST http://localhost:8000/api/payment/process \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 99.99,
    "currency": "USD",
    "card_number": "1234567890123456",
    "expiry_month": 12,
    "expiry_year": 2025,
    "cvv": "123",
    "first_name": "John",
    "last_name": "Doe",
    "event_name": "Event",
    "ticket_type": "GENERAL"
  }'
```

Expected Response (400):
```json
{
  "success": false,
  "error": "Invalid card number - failed Luhn check"
}
```

#### Test 2: Expired Card

```bash
{
  "expiry_month": 1,
  "expiry_year": 2024
}
```

Expected Response (400):
```json
{
  "error": "Card has expired"
}
```

#### Test 3: Invalid CVV

```bash
{
  "cvv": "12"
}
```

Expected Response (400):
```json
{
  "error": "Invalid CVV"
}
```

---

## Advanced Testing

### Brute Force Protection Testing

#### Test 1: 5 Failed Attempts

```bash
# Run this 5 times with wrong password
for i in {1..5}; do
  curl -X POST http://localhost:8000/api/login \
    -H "Content-Type: application/json" \
    -d '{
      "email":"testuser@example.com",
      "password":"wrongpassword"
    }'
  echo "\n--- Attempt $i ---"
  sleep 1
done
```

**Results:**
- Attempts 1-4: 401 "Invalid credentials" + "X attempts remaining"
- Attempt 5: 429 "Too Many Requests" + "retry_after: 60"

#### Test 2: Account Locked

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email":"testuser@example.com",
    "password":"TestPassword123!"
  }'
```

Response (429):
```json
{
  "error": "Account locked due to too many failed login attempts",
  "retry_after": 45
}
```

#### Test 3: Automatic Unlock

Wait 60+ seconds, then try with correct password:

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email":"testuser@example.com",
    "password":"TestPassword123!"
  }'
```

Response (200):
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "email": "testuser@example.com",
  "roles": ["ROLE_PARTICIPANT"]
}
```

### Admin Functions Testing

#### Get Ticket Statistics

Requires ROLE_ADMIN:

```bash
curl -X GET http://localhost:8000/api/tickets/stats/overview \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

Response (200):
```json
{
  "success": true,
  "stats": {
    "total_tickets": 10,
    "active_tickets": 8,
    "sold_out_tickets": 2,
    "total_inventory": 1000,
    "available_inventory": 950
  }
}
```

---

## Troubleshooting

### Common Issues

#### "Unauthorized" Error

**Cause:** Missing or invalid JWT token

**Solution:**
1. Login again: `POST /api/login`
2. Copy the returned token
3. Include in header: `Authorization: Bearer <TOKEN>`

#### "Card has expired" Error

**Cause:** Expiry date is in the past

**Solution:** Use future expiry date (e.g., 12/2025)

#### "Too Many Requests" (429)

**Cause:** Brute force protection locked account

**Solution:** Wait for retry_after seconds (usually 60s)

#### "Ticket sold out" Error

**Cause:** Quantity is 0

**Solution:** 
- Admin can update quantity: `PUT /api/tickets/{id}`
- Or create new ticket with more inventory

#### Database Connection Error

**Cause:** MySQL not running

**Solution:** Start Docker containers:
```bash
docker-compose up -d
```

Or check `.env` DATABASE_URL is correct.

#### JWT Token Expired

**Cause:** Token was generated >24 hours ago

**Solution:** Login again to get new token

---

## Test Results Checklist

- [ ] User registration works
- [ ] Login creates JWT token
- [ ] Dashboard loads after login
- [ ] Can create ticket (Admin)
- [ ] Can view all tickets
- [ ] Can purchase tickets
- [ ] Quantity decrements correctly
- [ ] "SOLD OUT" shows when quantity = 0
- [ ] Can update ticket details
- [ ] Can delete tickets
- [ ] Payment processing works
- [ ] Card validation works
- [ ] Brute force locks after 5 failures
- [ ] Account unlocks after timeout
- [ ] Admin statistics endpoint works

---

## Performance Notes

- Dashboard loads in <1 second
- API responses in <200ms
- Payment processing in <500ms
- Ticket operations are instant

---

**Testing Guide v1.0**  
**Last Updated:** December 20, 2024  
**Status:** Complete
