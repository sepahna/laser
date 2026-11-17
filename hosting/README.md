# Avid Beauty — DirectAdmin PHP/MySQL backend

This folder is the first PHP/MySQL migration layer for ordinary shared hosting. The React booking UI is retained; the Next.js runtime is not required for the PHP API.

## Requirements
- PHP 8.1+ (recommended 8.2 or 8.3)
- PHP extensions: PDO, pdo_mysql, fileinfo, session
- MySQL 5.7+ or MariaDB 10.4+
- HTTPS enabled

## DirectAdmin deployment
1. Create a MySQL database and database user in DirectAdmin; grant the user all privileges on that database.
2. Import `database.sql` through phpMyAdmin.
3. Copy `config.example.php` to `config.php` and set database credentials, an absolute private receipt directory OUTSIDE `public_html`, and an admin password hash.
4. Create the receipt directory outside the web root and make it writable by PHP (normally 750/770 depending on host).
5. Upload the contents of this folder so `api/index.php` is reachable at `https://YOUR-DOMAIN/api/index.php?action=services`.
6. Test the API before connecting the React UI.

Generate an admin password hash on a trusted PHP environment:
`php -r "echo password_hash('YOUR-STRONG-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"`
Never place a plaintext admin password in the repository.

## API
- GET `api/index.php?action=services`
- GET `api/index.php?action=settings`
- GET `api/index.php?action=availability&date=YYYY-MM-DD`
- POST multipart `api/index.php?action=booking`: name, phone, gender, date, time, service_ids (JSON array of MySQL service IDs), receipt file.
- POST JSON `api/index.php?action=admin-login`: password
- POST `api/index.php?action=admin-logout`
- GET `api/index.php?action=admin-bookings` (admin session)
- GET `api/index.php?action=admin-receipt&id=PAYMENT_ID` (admin session)
- POST JSON `api/index.php?action=admin-review`: booking_id, decision=approve|reject

## Important
- Booking creation serializes requests per date using MySQL GET_LOCK, checks overlapping time ranges in a transaction, stores receipt files outside the web root, and creates a temporary hold.
- Receipt approvals are manual. No payment gateway is implemented.
- The current React frontend still needs to be connected to these API endpoints and its hardcoded prices/time choices replaced by API data.
- Do not use this as production-ready until PHP version/extensions, database credentials, HTTPS, admin login, receipt storage permissions, and end-to-end tests have been verified on the actual hosting account.
