# Namecheap Shared Hosting Setup

## 1. MySQL
Create the MySQL database and user in cPanel, then import `database/schema.sql` with phpMyAdmin.

For the new ECOM HTTP monitor, also import `database/ecom_olt_web.sql` once after `schema.sql`. Existing OLT records receive the default web port `9092` and web scheme `http`.

## 2. Database password
For security, the real database password is intentionally NOT committed to this public repository. Edit `config/config.php` after upload and set `db.pass` to the database password.

## 3. Web root
Upload the repository's `public_html/` contents into the domain's document root. Keep `config/` and `database/` outside the public web root when possible. If your domain document root is already the repository root, point it to `public_html` or move only `public_html/index.php` and `.htaccess` into the document root and adjust the config path.

## 4. PHP
Use PHP 8.1+ (8.3 recommended). PDO MySQL and cURL must be enabled. RouterOS uses raw TCP API sockets, so Composer is not required.

## 5. MikroTik
Enable the RouterOS API on the router, normally TCP 8728, or API-SSL TCP 8729. Create a dedicated API user with only the permissions required by your deployment. Add the router from Super Admin.

## 6. ECOM OLT HTTP monitoring
The new `public_html/ecom-olt.php` page performs ECOM CGI requests server-side and avoids browser CORS restrictions. It supports the supplied endpoints:

- `/cgi-bin/h.cgi?module=sys_alarm_active&PageSize=5&PageNumber=1`
- `/cgi-bin/h.cgi?module=port_onu_auth_statitics`

Configure the OLT with host `103.186.218.145`, web scheme `http`, and web port `9092` when using the OLT from the supplied example. Open `ecom-olt.php?olt=OLT_ID` after assigning the OLT to the logged-in reseller.

The exact CGI module for the ECOM optical-power/laser screen was not present in the supplied requests; the monitor therefore accepts a custom module name instead of guessing a vendor-specific endpoint. Once the browser request made by that screen is known, it can be added as a fixed module without changing the monitoring architecture.

ONU reboot continues to use the existing per-OLT SSH reboot command and requires manage permission.

## 7. Reseller isolation
Super Admin assigns each reseller specific MikroTik/OLT resources. PPPoE customer ownership is recorded in `pppoe_assignments`; reseller pages filter by the logged-in reseller ID. Therefore reseller A cannot list reseller B's assigned users through the application.

## 8. Initial accounts
The SQL file contains bcrypt hashes for the requested initial Super Admin and two reseller accounts. Because this is a public repository, change all initial passwords immediately after installation.
