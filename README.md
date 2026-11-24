# Pediatric Handover System - API Backend

A secure, clinical pediatric handover backend system for a closed hospital department, designed as a stateless RESTful JSON API serving native mobile applications (iOS and Android).

## Tech Stack
- **Language**: PHP 8.4+ (Strict Types, PSR-12)
- **Framework**: Laravel 13
- **Database**: MySQL 8.x
- **Authentication**: Laravel Sanctum (Stateless Bearer Tokens, Personal Access Tokens)
- **API Documentation**: Scramble OpenAPI 3.1 & Interactive Documentation UI (available at `/docs/api`)

---

## Setup & Installation

### Prerequisites
- PHP 8.4 or higher with extensions: `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `curl`, `json`
- Composer 2.x
- MySQL 8.x

### 1. Clone & Install Dependencies
```bash
composer install
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

Configure API and Authentication settings in `.env`:
```env
# Session driver is set to array for stateless API operation
SESSION_DRIVER=array

# Comma-separated Google Client IDs for Web / Android / iOS
GOOGLE_CLIENT_IDS=your_client_id_web,your_client_id_android,your_client_id_ios

# API Documentation toggle (default: true)
API_DOCS_ENABLED=true

# Sanctum token expiration in minutes (default: 43200 = 30 days)
SANCTUM_EXPIRATION=43200
```

### 3. Database Migration & Seeding
Create the database in MySQL:
```sql
CREATE DATABASE pedi_handover CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Run database migrations:
```bash
php artisan migrate
```

Seed initial reference data:
```bash
php artisan db:seed
```

### 4. CLI Commands
Create a hospital administrator account:
```bash
php artisan admin:create
```

Import hospital patient data from CSV:
```bash
php artisan patients:import path/to/patients.csv
```

### 5. Running Tests & Quality Checks
Run the test suite:
```bash
php artisan test
```

Check code formatting with Laravel Pint:
```bash
vendor/bin/pint --test
```

Automatically format code:
```bash
vendor/bin/pint
```

### 6. Serving the Application
Start the PHP development server:
```bash
php artisan serve
```

Access the interactive API documentation at:
```
http://127.0.0.1:8000/docs/api
```
The OpenAPI JSON specification is accessible at:
```
http://127.0.0.1:8000/docs/api.json
```

---

## Clinical Updates are Immutable

Clinical updates within the Pediatric Handover System represent permanent, authoritative medical records and are strictly append-only. No doctor or administrator may alter or delete existing updates. Mistakes or revisions must be recorded by creating a new correction update referencing the original (`corrects_update_id`).

Immutability is enforced across multiple layers of defense:
1. **Model Guard**: The Eloquent `ClinicalUpdate` model registers a `deleting` event listener that unconditionally throws a `LogicException` ('Clinical updates are retained permanently and cannot be deleted.').
2. **Route Guard**: The routing layer registers only `POST` (create), `GET` (index), and `GET` (show) endpoints. Automated route guards verify that no route exists allowing `PUT`, `PATCH`, or `DELETE` on clinical updates.
3. **Database-Level Triggers**: Database migrations create `BEFORE UPDATE` and `BEFORE DELETE` triggers in MySQL/MariaDB raising `SQLSTATE '45000'` with the message `'Clinical updates are immutable'`.
4. **Least-Privilege Database Recommendation**: For production deployments, it is strongly recommended to configure the application's MySQL database user with `SELECT`, `INSERT`, and limited schema privileges, explicitly withholding `UPDATE` and `DELETE` grants on the `clinical_updates` table.
