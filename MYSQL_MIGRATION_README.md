# PostgreSQL to MySQL Migration Guide

This guide explains how to migrate your Notes Marketplace project from PostgreSQL to MySQL.

## Overview

The migration involves:
1. Converting database connection code from PostgreSQL to MySQL
2. Updating SQL syntax for MySQL compatibility
3. Converting all models and controllers to use MySQL functions
4. Creating new MySQL database schema
5. Setting up the MySQL database

## Key Changes Made

### 1. Database Configuration
- **File**: `config/config.development.php`
- **Changes**: Updated port from 5432 (PostgreSQL) to 3306 (MySQL)
- **Database**: Changed from `postgres` to `notes_marketplace`
- **Credentials**: Updated to MySQL defaults (root user)

### 2. Database Connection Classes
- **Files**: `src/Db.php`, `src/Helpers/Database.php`
- **Changes**: 
  - Replaced `pg_connect()` with `mysqli_connect()`
  - Updated connection string format
  - Added UTF-8 charset configuration

### 3. SQL Syntax Changes
- **Parameter Placeholders**: Changed from `$1, $2, $3` to `?, ?, ?`
- **RETURNING Clauses**: Removed (MySQL doesn't support RETURNING)
- **ILIKE**: Changed to `LIKE` (case-insensitive search)
- **SERIAL**: Changed to `AUTO_INCREMENT`
- **NOW()**: Kept (works in both)
- **COALESCE**: Kept (works in both)

### 4. Function Replacements
| PostgreSQL | MySQL |
|------------|-------|
| `pg_connect()` | `mysqli_connect()` |
| `pg_query_params()` | `mysqli_prepare()` + `mysqli_stmt_bind_param()` |
| `pg_fetch_assoc()` | `mysqli_fetch_assoc()` |
| `pg_affected_rows()` | `mysqli_stmt_affected_rows()` |
| `pg_insert_id()` | `mysqli_insert_id()` |
| `pg_last_error()` | `mysqli_error()` |

### 5. Models Converted
- ✅ User.php
- ✅ Note.php
- ✅ Order.php
- ✅ Cart.php
- ✅ RefreshToken.php
- ✅ Contact.php
- ✅ Coupon.php
- ✅ Review.php
- ✅ Download.php
- ✅ Dashboard.php
- ✅ Admin.php

### 6. Controllers Converted
- ✅ AdminAuthController.php
- ✅ EmailService.php (database queries)

## Database Schema

### New MySQL Schema Location
- **Complete Schema**: `database/mysql_migrations/00_complete_schema.sql`
- **Individual Migrations**: `database/mysql_migrations/01_*.sql` to `07_*.sql`

### Key Schema Changes
1. **Primary Keys**: `SERIAL` → `INT AUTO_INCREMENT`
2. **Foreign Keys**: Added proper MySQL foreign key constraints
3. **Indexes**: Optimized for MySQL performance
4. **Data Types**: Adjusted for MySQL compatibility
5. **Character Set**: UTF8MB4 for full Unicode support

## Setup Instructions

### Prerequisites
1. MySQL server installed and running
2. PHP with mysqli extension enabled
3. Access to create databases and tables

### Step 1: Run the Setup Script
```bash
cd php-backend-starter
php setup_mysql.php
```

This script will:
- Create the `notes_marketplace` database
- Create all necessary tables
- Insert sample data
- Verify the setup

### Step 2: Verify Configuration
Ensure your `config/config.development.php` has the correct MySQL settings:

```php
'db' => [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'notes_marketplace',
    'user' => 'root',
    'password' => '', // Update with your MySQL password
],
```

### Step 3: Test the Application
1. Start your PHP development server
2. Test the API endpoints
3. Verify database operations work correctly

## Default Credentials

### Admin Account
- **Username**: `admin`
- **Password**: `password123`

### Sample User Account
- **Email**: `user@example.com`
- **Password**: `password123`

## Sample Data

The setup script creates:
- 1 admin user
- 1 sample user
- 3 sample notes (Computer Science, Mathematics, Business)
- 2 sample coupons (WELCOME10, STUDENT20)

## Troubleshooting

### Common Issues

1. **Connection Failed**
   - Verify MySQL is running
   - Check credentials in config file
   - Ensure mysqli extension is enabled

2. **Table Not Found**
   - Run the setup script again
   - Check if database exists
   - Verify table creation was successful

3. **Character Encoding Issues**
   - Ensure UTF8MB4 charset is set
   - Check PHP mysqli charset configuration

4. **Permission Denied**
   - Ensure MySQL user has proper permissions
   - Check database and table privileges

### Debugging
- Check PHP error logs
- Enable error reporting in development
- Use `mysqli_error()` to get detailed error messages

## Performance Considerations

### MySQL Optimizations
1. **Indexes**: All foreign keys and frequently queried columns are indexed
2. **Character Set**: UTF8MB4 for full Unicode support
3. **Connection Pooling**: Consider implementing for production
4. **Query Optimization**: Use prepared statements (already implemented)

### Migration Benefits
1. **Wider Hosting Support**: MySQL is more commonly available
2. **Easier Setup**: More straightforward installation and configuration
3. **Better Documentation**: More resources and community support
4. **Cost Effective**: Often cheaper hosting options available

## Rollback Plan

If you need to revert to PostgreSQL:
1. Keep the original PostgreSQL files in a backup
2. Restore the original `config.development.php`
3. Restore original models and controllers
4. Use your existing PostgreSQL database

## Next Steps

1. **Test thoroughly** in development environment
2. **Update production** configuration when ready
3. **Migrate data** from PostgreSQL if needed
4. **Update documentation** for your team
5. **Monitor performance** and optimize as needed

## Support

If you encounter issues:
1. Check the troubleshooting section above
2. Review MySQL error logs
3. Verify PHP mysqli extension configuration
4. Test database connectivity separately

## Files Modified

### Core Files
- `config/config.development.php`
- `src/Db.php`
- `src/Helpers/Database.php`
- `src/Services/EmailService.php`

### Models
- `models/User.php`
- `models/Note.php`
- `models/Order.php`
- `models/Cart.php`
- `models/RefreshToken.php`
- `models/Contact.php`
- `models/Coupon.php`
- `models/Review.php`
- `models/Download.php`
- `models/Dashboard.php`
- `models/Admin.php`

### Controllers
- `Controllers/Admin/AdminAuthController.php`

### New Files
- `database/mysql_migrations/00_complete_schema.sql`
- `database/mysql_migrations/01_create_users_table.sql`
- `database/mysql_migrations/02_add_google_oauth_columns.sql`
- `database/mysql_migrations/03_add_refresh_tokens_table.sql`
- `database/mysql_migrations/04_add_is_active_to_notes.sql`
- `database/mysql_migrations/05_add_email_verification.sql`
- `database/mysql_migrations/06_add_password_reset.sql`
- `database/mysql_migrations/07_create_contacts_table.sql`
- `setup_mysql.php`
- `MYSQL_MIGRATION_README.md`

The migration is now complete! Your application should work with MySQL instead of PostgreSQL. 