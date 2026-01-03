# 🚀 Quick Start Guide - Event Management System

## What's New?

Your project has been **fully integrated** with all team members' work:

✅ **Oussema's Work**: Payment system, tickets, dashboard, security  
✅ **Aymen's Work**: Event management, reservations, event CRUD  
✅ **All Merged**: Single unified project on the `main` branch  

---

## 📦 What You Have Now

### Entities (Database Models)
- `User.php` - User management & authentication
- `Ticket.php` - Ticket inventory & tracking
- `Payment.php` - Payment records & Stripe integration
- `LoginAttempt.php` - Brute-force protection
- `Event.php` - Event catalog & management **[NEW]**
- `Reservation.php` - Event reservations **[NEW]**

### Controllers (Routes & Logic)
- `AuthController.php` - Login, register, logout
- `DashboardController.php` - User dashboard
- `PaymentController.php` - Payment processing
- `TicketController.php` - Ticket management
- `EventController.php` - Event CRUD operations **[NEW]**
- `ReservationController.php` - Reservation booking **[NEW]**

### Services (Business Logic)
- `StripeService.php` - Payment processing
- `QrCodeService.php` - QR code generation
- `JwtService.php` - JWT authentication
- `BruteForceProtectionService.php` - Attack prevention

---

## 🔧 Quick Setup (5 Minutes)

### Step 1: Install Dependencies
```bash
cd "c:\Users\User\Desktop\Event Project\EventProject"
composer install
```

### Step 2: Configure Database
Edit `.env.local`:
```dotenv
DATABASE_URL="mysql://root:@127.0.0.1:3306/events?serverVersion=8.0"
```

### Step 3: Setup Database
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### Step 4: Run Server
```bash
symfony server:start
```

✅ Visit: **http://localhost:8000**

---

## 🧭 Project Navigation

### User Features
- **Register**: `/register` - Create new account
- **Login**: `/login` - Sign in
- **Dashboard**: `/dashboard` - View tickets & profile

### Admin/Shop Features
- **Events List**: `/event` - Browse all events
- **Create Event**: `/event/new` - Create new event
- **Reservations**: `/reservation` - Manage reservations
- **Buy Tickets**: `/api/payment/available-tickets` - Shop for tickets

### Payment Flow
1. User browses tickets: `/api/payment/available-tickets`
2. Selects tickets and pays: `/api/payment/process`
3. Downloads PDF with QR: `/api/ticket/download/{id}`

### Event Workflow
1. Admin creates event: `/event/new`
2. Users reserve spots: `/reservation/new?eventId={id}`
3. Users manage reservations: `/reservation/{id}/edit`

---

## 📊 Database Tables

**Users** → **Tickets** → **Payments**  
**Events** → **Reservations** → **(Users)**

```
Users (1) ───────── (Many) Tickets
   │
   └─────────────────────(Many) Payments
   
Events (1) ────────── (Many) Reservations
```

---

## 🎯 Available Endpoints

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/` | Homepage |
| POST | `/register` | Create account |
| POST | `/login` | User login |
| GET | `/logout` | Logout |
| GET | `/dashboard` | User dashboard |
| GET | `/event` | List events |
| POST | `/event/new` | Create event |
| GET | `/event/{id}` | Event details |
| POST | `/event/{id}/edit` | Update event |
| GET | `/reservation` | List reservations |
| POST | `/reservation/new` | Create reservation |
| GET | `/api/payment/available-tickets` | Get tickets |
| POST | `/api/payment/process` | Process payment |
| GET | `/api/ticket/download/{id}` | Download ticket PDF |

---

## 🧪 Test Stripe Card

- **Number**: `4242 4242 4242 4242`
- **Expiry**: `12/26`
- **CVC**: `123`
- **Amount**: Any > 0.50

---

## 📝 Important Files

- **`INTEGRATION_COMPLETE.md`** - Full technical documentation
- **`API_DOCUMENTATION.md`** - API specifications
- **`TESTING_GUIDE.md`** - How to test features
- **`PROJECT_SUMMARY.md`** - Architecture overview
- **`README.md`** - Main documentation

---

## 🔍 Troubleshooting

### Database not connecting?
```bash
# Check MySQL is running
# Update DATABASE_URL in .env.local with your credentials
# Run: php bin/console doctrine:database:create
```

### Migrations failed?
```bash
# Rollback last migration
php bin/console doctrine:migrations:migrate --all down

# Re-run migrations
php bin/console doctrine:migrations:migrate
```

### Cache issues?
```bash
# Clear cache
php bin/console cache:clear

# Warm up cache
php bin/console cache:warmup
```

### Missing dependencies?
```bash
# Update composer
composer update

# Reinstall
composer install --no-dev
```

---

## 🚀 Next Steps

1. ✅ **Database Setup** - Run migrations
2. ✅ **Server Start** - Start Symfony server
3. ✅ **Create Admin** - Register an admin user
4. ✅ **Add Events** - Create test events
5. ✅ **Test Payments** - Try test payment card
6. ✅ **Test Reservations** - Create test reservations

---

## 📞 Git Commands

```bash
# Check current branch
git branch

# See latest commits
git log --oneline -5

# Pull latest changes
git pull origin main

# See what changed
git status

# Push your work
git push origin main
```

---

## ✨ Features at a Glance

| Feature | Status | Team |
|---------|--------|------|
| User Authentication | ✅ | Oussema |
| Payment Processing | ✅ | Oussema |
| Ticket Generation | ✅ | Oussema |
| QR Codes | ✅ | Oussema |
| Dashboard | ✅ | Oussema |
| Event Management | ✅ | Aymen |
| Reservations | ✅ | Aymen |
| Brute-force Protection | ✅ | Oussema |
| JWT Authentication | ✅ | Oussema |

---

## 🎓 Learning Path

1. **Users**: Study [AuthController.php](src/Controller/AuthController.php)
2. **Events**: Study [EventController.php](src/Controller/EventController.php)
3. **Payments**: Study [PaymentController.php](src/Controller/PaymentController.php)
4. **Database**: Check [migrations/](migrations/)
5. **Services**: Review [src/Service/](src/Service/)

---

**Version:** 2.0 - Fully Integrated  
**Last Updated:** January 3, 2026  
**Status:** ✅ Production Ready
