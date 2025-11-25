# Database Testing Guide

## Quick Database Verification

Your system should have a MySQL database on Render.com with the following tables and data.

---

## Method 1: Via Laravel Tinker (Easiest)

If you have SSH access to your Render service:

```bash
# SSH into Render instance
php artisan tinker

# Check users
>>> DB::table('users')->count()
// Should return number > 0

>>> DB::table('users')->first()
// Should show user data

# Check appointments
>>> DB::table('appointments')->count()
>>> DB::table('appointments')->first()

# Check messages
>>> DB::table('messages')->count()

# Exit tinker
>>> exit
```

---

## Method 2: Via MySQL Client

If you have direct database access (MySQL credentials):

```bash
# Connect to database
mysql -h your-db-host -u username -p database_name

# List all tables
SHOW TABLES;

# Check table structure
DESCRIBE users;
DESCRIBE appointments;
DESCRIBE messages;

# Count records
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM appointments;
SELECT COUNT(*) FROM messages;

# View sample data
SELECT id, name, email, role FROM users LIMIT 5;
SELECT id, user_id, date, status FROM appointments LIMIT 5;

# Check for your test user
SELECT * FROM users WHERE email = 'admin@example.com';
```

---

## Method 3: Via Frontend (Best User Experience)

1. **Login to your frontend**
   - Go to `https://your-frontend.vercel.app`
   - Login with a test account

2. **Check Admin Dashboard**
   - If admin, go to dashboard
   - You should see counts:
     - Total Users
     - Total Appointments
     - Total Messages
   - These numbers come from the database

3. **View Lists**
   - Users List → Should show users from database
   - Appointments List → Should show appointments
   - Messages → Should show messages

---

## Expected Tables & Structure

Your database should have these tables:

### `users` table
```
id (INT, Primary Key)
name (VARCHAR)
email (VARCHAR, Unique)
password (VARCHAR, hashed)
role (ENUM: admin, staff, client)
created_at (TIMESTAMP)
updated_at (TIMESTAMP)
```

### `appointments` table
```
id (INT, Primary Key)
user_id (INT, Foreign Key → users.id)
date (DATE)
time (TIME)
status (ENUM: pending, confirmed, completed, cancelled)
notes (TEXT)
created_at (TIMESTAMP)
updated_at (TIMESTAMP)
```

### `messages` table
```
id (INT, Primary Key)
sender_id (INT, Foreign Key → users.id)
recipient_id (INT, Foreign Key → users.id)
content (TEXT)
read_at (TIMESTAMP, nullable)
created_at (TIMESTAMP)
updated_at (TIMESTAMP)
```

### Other tables
- `migrations` - Tracks which migrations have been run
- `audit_logs` - Logs of user actions
- `action_logs` - Activity tracking
- `notifications` - User notifications
- `services` - Available services
- `documents` - Document storage

---

## Checking If Migrations Ran

```bash
# Option 1: In Laravel Tinker
>>> DB::table('migrations')->get()

# Option 2: In MySQL
SELECT * FROM migrations;

# Option 3: Via artisan command
php artisan migrate:status
```

If migrations haven't run, you need to run them:
```bash
php artisan migrate
```

---

## Seeding Test Data (Optional)

If you want test data:

```bash
# Run seeders
php artisan db:seed

# Or run specific seeder
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=AppointmentSeeder
```

---

## Verifying Backend Can Access Database

From terminal on Render:

```bash
# Test database connection
php artisan db:show

# Or in tinker
>>> DB::connection()->getPdo()
// Should connect without error

>>> DB::table('users')->count()
// Should return a number
```

---

## Troubleshooting Database Issues

### Error: "SQLSTATE[HY000]: General error: 1030 Got error from storage engine"
- Database is out of space
- Or database connection timeout
- **Fix**: Check Render database logs

### Error: "SQLSTATE[HY000]: General error: 2006 MySQL server has gone away"
- Database connection lost
- **Fix**: Restart database or check Render logs

### Error: "No such table"
- Migrations haven't run
- **Fix**: Run `php artisan migrate`

### Tables exist but empty
- Database was not seeded
- **Fix**: Run `php artisan db:seed`

### Can connect but no tables
- Migrations definitely haven't run
- **Fix**: Connect to Render and run:
  ```bash
  cd /your/app/path
  php artisan migrate
  ```

---

## Database Backup (Important!)

Before making changes, backup your database:

```bash
# Backup to file
mysqldump -h host -u user -p database_name > backup.sql

# Or via Render dashboard
Go to Database Settings → Backups
```

---

## Quick Health Check Script

```sql
-- Run this SQL to check everything:

-- 1. Check all tables exist
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'your_database_name';

-- 2. Count records in main tables
SELECT 'users' AS table_name, COUNT(*) AS count FROM users
UNION ALL
SELECT 'appointments' AS table_name, COUNT(*) FROM appointments
UNION ALL
SELECT 'messages' AS table_name, COUNT(*) FROM messages
UNION ALL
SELECT 'services' AS table_name, COUNT(*) FROM services;

-- 3. Check latest users
SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 3;

-- 4. Check latest appointments
SELECT id, user_id, date, status, created_at FROM appointments ORDER BY created_at DESC LIMIT 3;

-- 5. Check if migrations are up to date
SELECT COUNT(*) as total_migrations FROM migrations;
```

---

## Database Monitoring

Check these regularly:

1. **Disk Space**: `SHOW VARIABLES LIKE 'datadir';` then check OS disk
2. **Connections**: `SHOW STATUS WHERE variable_name = 'Threads_connected';`
3. **Slow Queries**: `SELECT * FROM mysql.slow_log;`
4. **Recent Errors**: Check MySQL error log

---

## Performance Tips

```sql
-- Add indexes if missing
ALTER TABLE users ADD INDEX idx_email (email);
ALTER TABLE appointments ADD INDEX idx_user_id (user_id);
ALTER TABLE messages ADD INDEX idx_recipient (recipient_id);

-- Check query performance
EXPLAIN SELECT * FROM appointments WHERE date = '2024-01-01';
```

---

## Verification Checklist

- [ ] Can connect to database from backend
- [ ] All tables exist (SHOW TABLES;)
- [ ] Tables have data (COUNT records)
- [ ] At least one admin user exists
- [ ] Latest migrations have been run
- [ ] Backend can CRUD operations on tables
- [ ] Frontend shows data from database
- [ ] Login works and fetches user data
- [ ] Appointments/Messages load from database
