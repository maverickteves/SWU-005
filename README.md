# Estateflow Backend (PHP + PostgreSQL for XAMPP)

This directory contains the complete backend database and REST API implementation for the Estateflow Real Estate application, designed to run using **XAMPP (Apache + PHP)** connected to a **PostgreSQL** database.

---

## 📁 Architecture Overview

```
backend/
├── config/
│   └── db.php                  # PostgreSQL PDO connection & CORS headers
├── database/
│   └── schema.sql              # Complete PostgreSQL database schema + seed data
├── api/
│   ├── status.php              # Health check & PostgreSQL connection verification
│   ├── properties.php          # Real estate listings CRUD & favorite toggling
│   ├── appointments.php        # Viewing appointments & booking scheduler
│   ├── inquiries.php           # Inquiries & threaded chat messaging
│   ├── auth.php                # Client/Agent login, registration & agent profiles
│   ├── notifications.php       # In-app activity alerts
│   └── sync.php                # Bidirectional bulk synchronization engine
├── index.php                   # Interactive Web Test Dashboard (view in browser)
├── .htaccess                   # Apache CORS and URL rewrite configuration
└── XAMPP_POSTGRES_SETUP_GUIDE.md # Detailed step-by-step setup guide
```

---

## 🚀 Quick Setup Instructions

### 1. PostgreSQL Database Setup
1. Open **pgAdmin** or your PostgreSQL command line (`psql`).
2. Create the database:
   ```sql
   CREATE DATABASE estateflow_db;
   ```
3. Import the schema and seed data:
   - In pgAdmin: Right-click `estateflow_db` &rarr; **Query Tool** &rarr; Open and run `backend/database/schema.sql`.
   - Or using terminal:
     ```bash
     psql -U postgres -d estateflow_db -f backend/database/schema.sql
     ```

### 2. Enable PostgreSQL in XAMPP
By default, XAMPP comes with MySQL enabled, but PostgreSQL support is built into PHP and just needs to be uncommented:
1. Open the **XAMPP Control Panel**.
2. Click **Config** next to Apache &rarr; choose **PHP (php.ini)**.
3. Search (Ctrl+F) for `pdo_pgsql` and `pgsql`.
4. Remove the semicolon `;` from the beginning of these two lines:
   ```ini
   extension=pdo_pgsql
   extension=pgsql
   ```
5. Save `php.ini` and restart Apache in XAMPP (click **Stop**, then **Start**).

### 3. Deploy to XAMPP `htdocs`
1. Copy the entire `backend/` folder into your XAMPP web root:
   - **Windows:** `C:\xampp\htdocs\estateflow-backend`
   - **macOS:** `/Applications/XAMPP/htdocs/estateflow-backend`
   - **Linux:** `/opt/lampp/htdocs/estateflow-backend`
2. Configure credentials in `backend/config/db.php` if your PostgreSQL password is not `postgres`:
   ```php
   $dbHost = '127.0.0.1';
   $dbPort = '5432';
   $dbName = 'estateflow_db';
   $dbUser = 'postgres';
   $dbPass = 'your_postgres_password'; // Update here
   ```

### 4. Test the Backend in Browser
Open your browser and navigate to:
- **Interactive Dashboard:** `http://localhost/estateflow-backend/`
- **Database Status JSON:** `http://localhost/estateflow-backend/api/status.php`

You will see:
```json
{
  "success": true,
  "status": "ONLINE",
  "backend": "PHP (XAMPP / Apache)",
  "database": "PostgreSQL",
  "postgres_version": "PostgreSQL 16.x ...",
  "table_stats": {
    "properties": 5,
    "viewing_appointments": 2,
    "inquiries": 1,
    "inquiry_messages": 2,
    "notifications": 2,
    "users": 1,
    "agent_profiles": 1
  }
}
```

---

## 📱 Connecting the Android App

The Android app features a built-in **"PHP & PostgreSQL Backend (XAMPP)"** manager in the **Profile & Account** screen!

1. Open the app &rarr; go to **Profile**.
2. Scroll to the **PHP & PostgreSQL Backend** card.
3. Base URL options:
   - **Android Emulator:** `http://10.0.2.2/estateflow-backend/api/` (`10.0.2.2` automatically points to the host computer running XAMPP).
   - **Physical Device:** Connect device and PC to the same Wi-Fi network, and use: `http://192.168.x.x/estateflow-backend/api/`.
4. Tap **Test Connection** &rarr; the app pings `/api/status.php` and verifies live communication with PostgreSQL!
5. Tap **Sync with Backend** to pull or push properties, viewing appointments, and inquiries.
