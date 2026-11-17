# Pediatric Handover System

A clinical pediatric handover system for a closed hospital department.

## Tech Stack
- PHP 8.3+ (Strict Types, PSR-12)
- Laravel 13 (Latest Stable)
- MySQL 8
- Server-rendered Blade with placeholder scaffolding (Tailwind/Vite)
- Session-based multi-guard authentication (`doctor` and `admin`)

---

## Setup & Installation

### Prerequisites
- PHP 8.3 or higher (with `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `curl`)
- Composer 2.x
- Node.js 20+ & npm
- MySQL 8.x

### 1. Clone & Install Dependencies
```bash
composer install
npm install
```

### 2. Environment Configuration
Copy `.env.example` to `.env`:
```bash
cp .env.example .env
```
Generate the application key:
```bash
php artisan key:generate
```

Configure your MySQL database settings in `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pedi_handover
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password
DB_TIMEZONE="+00:00"
```

Configure default administrator bootstrap credentials in `.env`:
```env
ADMIN_NAME="Hospital Admin"
ADMIN_EMAIL=admin@hospital.local
ADMIN_PASSWORD=your_secure_password
```

### 3. Database Migration & Seeding
Ensure MySQL has the database created:
```sql
CREATE DATABASE pedi_handover CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Run database migrations:
```bash
php artisan migrate
```

### 4. Code Quality & Formatting
Run Laravel Pint to ensure PSR-12 and strict types compliance:
```bash
vendor/bin/pint --test
```
To automatically format code:
```bash
vendor/bin/pint
```

### 5. Running the Application
Start the PHP development server:
```bash
php artisan serve
```
In a separate terminal, compile assets with Vite:
```bash
npm run dev
```
Visit `http://localhost:8000` in your browser.
