# Namecheap Shared Hosting Setup

## 1. MySQL
Create the MySQL database and user in cPanel, then import `database/schema.sql` with phpMyAdmin.

## 2. Database password
For security, the real database password is intentionally NOT committed to this public repository. Edit `config/config.php` after upload and set `db.pass` to the database password.

## 3. Web root
Upload the repository's `public_html/` contents into the domain's document root. Keep `config/` and `database/` outside the public web root when possible. If your domain document root is already the repository root, point it to `public_html` or move only `public_html/index.php` and `.htaccess` into the document root and adjust the config path.

## 4. PHP
Use PHP 8.1+ (8.3 recommended). PDO MySQL must be enabled. RouterOS uses raw TCP API sockets, so Composer is not required.

## 5. MikroTik
Enable the RouterOS API on the router, normally TCP 8728, or API-SSL TCP 8729. Create a dedicated API user with only the permissions required by your deployment. Add the router from Super Admin.

## 6. ECOM OLT
Add the OLT from Super Admin. ECOM/CDATA command syntax differs by model and firmware. The UI intentionally stores status, ONU/laser and reboot commands per OLT. Configure read-only commands for monitoring and a vendor-supported reboot command for ONU restart.

## 7. Reseller isolation
Super Admin assigns each reseller specific MikroTik/OLT resources. PPPoE customer ownership is recorded in `pppoe_assignments`; reseller pages filter by the logged-in reseller ID. Therefore reseller A cannot list reseller B's assigned users through the application.

## 8. Initial accounts
The SQL file contains bcrypt hashes for the requested initial Super Admin and two reseller accounts. Because this is a public repository, change all initial passwords immediately after installation.
