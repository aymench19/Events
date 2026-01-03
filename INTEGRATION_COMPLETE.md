# 🎫 Event Management System - Production Ready

**Unified Symfony 7.3 Project | Payment Processing | Ticket Management | Event Reservations**

---

## ✨ Complete Features

### 🎯 Core Components

#### 1. **Ticket & Payment System** (Oussema's Work)
- Professional ticket shop interface
- Secure Stripe payment processing
- PDF ticket download with QR codes
- Real-time inventory management
- JWT authentication
- Brute-force protection (10 attempts → 5 min lockout)
- Transaction-level safety

#### 2. **Event Management** (Aymen's Work)
- Full CRUD operations for events
- Event capacity tracking
- Pricing management
- Event search and filtering

#### 3. **Reservation System** (Aymen's Work)
- Reservation booking for events
- Reservation status management (PENDING, CONFIRMED, CANCELLED)
- User-friendly reservation interface
- Event availability checking

### 🔐 Security Features
- ✅ JWT token authentication
- ✅ CSRF protection on forms
- ✅ Brute-force detection (10 attempts → 5 min lockout)
- ✅ User ownership verification
- ✅ SQL injection prevention (ORM)
- ✅ XSS protection (Twig escaping)
- ✅ Stripe PCI compliance
- ✅ Transaction-level safety (database locks)

### 💳 Payment Features
- ✅ Real Stripe API integration
- ✅ 15+ error codes mapped to user-friendly messages
- ✅ Card validation (Luhn algorithm)
- ✅ Expiry & CVC verification
- ✅ Oversell prevention
- ✅ Automatic refund processing
- ✅ Card brand detection

---

## 🚀 Installation & Setup

### Prerequisites
- PHP 8.2+
- Composer
- MySQL/MariaDB 8.0+
- Docker (optional)

### Step 1: Clone the Project

```bash
cd "c:\Users\User\Desktop\Event Project\EventProject"
```

### Step 2: Install Dependencies

```bash
composer install
```

### Step 3: Configure Environment

Copy and edit the `.env.local` file:

```bash
cp .env .env.local
```

Edit `.env.local` with your database credentials and Stripe API key:

```dotenv
DATABASE_URL="mysql://root:@127.0.0.1:3306/events?serverVersion=8.0"
STRIPE_API_KEY="sk_test_your_stripe_key"
JWT_SECRET="your_jwt_secret_key"
```

### Step 4: Setup Database

```bash
# Create database
php bin/console doctrine:database:create

# Run migrations
php bin/console doctrine:migrations:migrate

# Load fixtures (optional)
php bin/console doctrine:fixtures:load
```

### Step 5: Start Development Server

```bash
symfony server:start
```

Or using PHP built-in server:

```bash
php -S localhost:8000 -t public/
```

Visit: **http://localhost:8000**

---

## 📋 Database Schema

### Users Table
```
id (PK)
├── email (UNIQUE)
├── password (hashed)
├── roles (JSON)
├── firstName
├── lastName
└── createdAt
```

### Tickets Table
```
id (PK)
├── ticket_key (UNIQUE)
├── user_id (FK → Users)
├── payment_id (FK → Payments, nullable)
├── event_name
├── ticket_type
├── price
├── quantity
├── status (ACTIVE, USED, EXPIRED, CANCELLED)
├── issued_at
├── expires_at
└── qr_code
```

### Payments Table
```
id (PK, UUID)
├── user_id (FK → Users)
├── ticket_id (FK → Tickets, nullable)
├── amount
├── currency
├── status (PENDING, COMPLETED, FAILED, REFUNDED)
├── card_brand
├── card_last_four
├── transaction_id (Stripe)
├── error_message
├── created_at
└── completed_at
```

### Events Table
```
id (PK)
├── title
├── date
├── capacity
└── price
```

### Reservations Table
```
id (PK)
├── event_id (FK → Events)
├── date
└── status (PENDING, CONFIRMED, CANCELLED)
```

---

## 🔌 API Endpoints

### Authentication
```
POST   /login              - User login
POST   /register           - Create new account
GET    /logout             - Clear session
```

### Dashboard
```
GET    /dashboard          - User dashboard
```

### Tickets
```
GET    /api/ticket/list    - Get user's tickets
POST   /api/ticket/download/{id}  - Download PDF
GET    /api/ticket/qr/{id} - Get QR code
```

### Payments
```
GET    /api/payment/available-tickets    - List available tickets
POST   /api/payment/process              - Process payment
GET    /api/payment/status/{id}          - Check payment status
POST   /api/payment/validate-card        - Validate card
```

### Events
```
GET    /event             - List all events
POST   /event/new         - Create event (requires admin)
GET    /event/{id}        - View event details
POST   /event/{id}/edit   - Update event
POST   /event/{id}        - Delete event
```

### Reservations
```
GET    /reservation                      - List all reservations
POST   /reservation/new                  - Create reservation
GET    /reservation/{id}                 - View reservation
POST   /reservation/{id}/edit            - Update reservation
POST   /reservation/{id}                 - Delete reservation
```

---

## 🧪 Testing

### Test Payment Card
- **Card:** `4242 4242 4242 4242`
- **Expiry:** `12/2026`
- **CVC:** `123`
- **Amount:** Any amount > 0.50

### Test Failed Card
- **Card:** `4000 0000 0000 0002`

### Running Tests

```bash
# Run all tests
php bin/phpunit

# Run specific test
php bin/phpunit tests/PaymentTest.php

# With coverage
php bin/phpunit --coverage-html coverage/
```

---

## 📁 Project Structure

```
EventProject/
├── src/
│   ├── Controller/
│   │   ├── AuthController.php        - Authentication (login, register, logout)
│   │   ├── DashboardController.php   - User dashboard
│   │   ├── PaymentController.php     - Payment processing
│   │   ├── TicketController.php      - Ticket management
│   │   ├── EventController.php       - Event CRUD operations
│   │   └── ReservationController.php - Reservation management
│   ├── Entity/
│   │   ├── User.php                  - User entity
│   │   ├── Payment.php               - Payment records
│   │   ├── Ticket.php                - Ticket inventory
│   │   ├── Event.php                 - Event data
│   │   ├── Reservation.php           - Reservation records
│   │   └── LoginAttempt.php          - Brute-force tracking
│   ├── Enum/
│   │   └── ReservationStatus.php     - Reservation status enum
│   ├── Service/
│   │   ├── StripeService.php         - Stripe API integration
│   │   ├── QrCodeService.php         - QR code generation
│   │   ├── JwtService.php            - JWT token handling
│   │   └── BruteForceProtectionService.php - Attack prevention
│   ├── Form/
│   │   ├── RegistrationFormType.php
│   │   ├── EventType.php             - Event form
│   │   └── ReservationType.php       - Reservation form
│   └── Security/
│       └── AuthAuthenticator.php     - Custom authenticator
├── config/
│   ├── bundles.php
│   ├── services.yaml
│   ├── routes.yaml
│   └── packages/
│       ├── security.yaml
│       ├── doctrine.yaml
│       ├── framework.yaml
│       └── lexik_jwt_authentication.yaml
├── templates/
│   ├── base.html.twig                - Main layout
│   ├── dashboard.html.twig           - Dashboard page
│   ├── security/
│   │   └── login.html.twig           - Login form
│   ├── registration/
│   │   └── register.html.twig        - Registration form
│   ├── event/
│   │   ├── index.html.twig           - Events list
│   │   ├── show.html.twig            - Event details
│   │   ├── new.html.twig             - Create event
│   │   └── edit.html.twig            - Edit event
│   └── reservation/
│       ├── index.html.twig           - Reservations list
│       ├── show.html.twig            - Reservation details
│       ├── new.html.twig             - Create reservation
│       └── edit.html.twig            - Edit reservation
├── migrations/
│   └── Version*.php                  - Database migrations
├── public/
│   └── index.php                     - Entry point
├── docker-compose.yaml               - Docker configuration
├── composer.json                     - PHP dependencies
└── .env                             - Environment variables
```

---

## 🛠 Development

### Clear Cache
```bash
php bin/console cache:clear
```

### Generate Database Schema
```bash
php bin/console doctrine:schema:update --force
```

### Create New Migration
```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

### Create New Entity
```bash
php bin/console make:entity
```

### Create New Controller
```bash
php bin/console make:controller EventController
```

---

## 📊 Key Services

### StripeService
Handles all Stripe API interactions:
- Payment processing
- Refund handling
- Card validation
- Error handling with 15+ mapped error codes

### QrCodeService
Generates and manages QR codes:
- QR code generation for tickets
- PDF embedding
- QR code display on dashboard

### JwtService
Manages JWT tokens:
- Token generation
- Token validation
- Claims management

### BruteForceProtectionService
Prevents brute-force attacks:
- Tracks login attempts
- Implements 5-minute lockout after 10 failed attempts
- Automatic cleanup of old records

---

## 🔄 Git Workflow

### Branch Strategy
- **main** - Production-ready code
- **feature/oussema** - Payment & Ticket system
- **feature/aymen** - Event & Reservation system
- **feature/amani** - Available for new features
- **feature/ranim** - Available for new features

### Committing Changes
```bash
# Stage changes
git add .

# Commit with clear message
git commit -m "Add feature description"

# Push to your branch
git push origin feature/yourname
```

### Merging to Main
1. Ensure all tests pass
2. Create a pull request on GitHub
3. Code review
4. Merge to main

---

## 📞 Support & Documentation

- **API Documentation:** See [API_DOCUMENTATION.md](API_DOCUMENTATION.md)
- **Testing Guide:** See [TESTING_GUIDE.md](TESTING_GUIDE.md)
- **Project Summary:** See [PROJECT_SUMMARY.md](PROJECT_SUMMARY.md)
- **Database Setup:** See [DATABASE_SETUP_COMPLETE.md](DATABASE_SETUP_COMPLETE.md)

---

## 🚀 Deployment

### Docker Deployment

```bash
# Build images
docker-compose build

# Start services
docker-compose up -d

# Run migrations
docker-compose exec app php bin/console doctrine:migrations:migrate
```

### Environment Variables (Production)
```dotenv
APP_ENV=prod
APP_DEBUG=false
DATABASE_URL="mysql://user:pass@db:3306/events?serverVersion=8.0"
STRIPE_API_KEY="sk_live_your_production_key"
JWT_SECRET="generate_strong_secret_key"
```

---

## 📝 License

Proprietary - All rights reserved

---

## ✅ Status

**PRODUCTION READY** ✓

All components integrated and tested. Ready for deployment.

Last Updated: January 3, 2026
