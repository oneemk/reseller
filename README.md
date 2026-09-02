# Reseller ISP Network Manager

PHP 8.1+ / MySQL / Bootstrap 5 application designed for Namecheap shared hosting.

## Features
- Super Admin and isolated Reseller accounts
- Assign specific MikroTik routers and OLTs to each reseller
- MikroTik PPPoE secret list: add, remove, enable/disable, live RX/TX and customer details
- ECOM OLT inventory, online status, ONU optical/laser monitoring and reboot action through configurable SSH commands
- Per-reseller data isolation enforced server-side
- CSRF protection, password hashing, session authentication and prepared SQL statements

## Deployment
1. Create MySQL database/user and import `database/schema.sql`.
2. Edit `config/config.php` with database credentials and application URL.
3. Upload the repository contents to `public_html`.
4. Ensure PHP 8.1+ is selected. MikroTik uses the RouterOS API protocol directly and does not require Composer.
5. Configure MikroTik API access and ECOM OLT SSH credentials/commands from the Super Admin panel.

### ECOM OLT note
ECOM firmware/CLI syntax differs by model and firmware. The OLT adapter stores commands in the database so the Super Admin can configure status/ONU/reboot commands without changing PHP code.

Default accounts are inserted by the SQL installer using bcrypt hashes. Change all default passwords immediately after first login.
