# SasthoBazar – deployment guide

Two ways to run the shop on the internet:

* **A. cPanel shared hosting:** easiest, managed by the host.
* **B. VPS with Ubuntu:** your own server, more control. It covers Apache or Nginx, SSL, cron and backups.

> ⚠️ **The installer (`/install/`) deletes and recreates all tables.** Use it only for a brand-new empty shop. To move your existing shop (products, orders, users), **import a backup** instead (section 2.2). Delete the `install/` folder on the live server.

---

## 1. What you need

| | Minimum | Recommended |
|---|---|---|
| PHP | 8.0 | **8.2** (8.0 no longer gets security fixes) |
| PHP extensions | pdo_mysql, mbstring, fileinfo, json, openssl, curl, zip, zlib, dom | + opcache, intl, gd |
| Database | MySQL 5.7 / MariaDB 10.4 | MySQL 8 / MariaDB 10.6+ |
| Web server | Apache 2.4 with `mod_rewrite` + `AllowOverride All` | or Nginx 1.18+ (config in `deploy/nginx.conf.example`) |
| Disk | 1 GB | 5 GB+ (pictures and backups grow) |
| Other | a domain, SSL certificate (free Let's Encrypt), cron | SMTP mailbox, SMS gateway account |

The app works in the domain root (`https://example.com/`) or a subfolder (`https://example.com/shop/`); the folder is detected automatically.

---

## 2. Prepare the release (on your computer)

### 2.1 Files

1. Make a copy of the project folder.
2. In the copy, edit **`config/config.php`**:
   ```php
   'debug'       => false,
   'url'         => 'https://example.com',   // your real address (used in emails / SMS sent by cron)
   'trust_proxy' => false,                   // true only behind Cloudflare / a reverse proxy
   'session'  => ['secure' => true, ...],     // after SSL works
   ```
3. Delete from the copy: `storage/logs/*`, `storage/backups/*`, `config/installed.lock` and `config/database.php`, unless you are moving data (2.2). Also delete the old prototypes `index.html`, `admin.html` and `db.js` (not used by the site). Keep `docs/` and `deploy/` (they are blocked from the web) or delete them.
4. Zip the folder.

### 2.2 Moving existing data (instead of a fresh install)

1. On your computer: remove the demo data if it is still there: `C:\xampp\php\php.exe database\demo.php --remove`.
2. Admin → Settings → Backup → **Create & download**. You get `sasthobazar-….sql.gz`.
3. Also zip `public/uploads/` (product pictures, banners, logo) and `storage/private/` (prescriptions, seller documents).
4. On the server: create an empty database, import the `.sql.gz` (phpMyAdmin → Import, or `gunzip < file.sql.gz | mysql -u USER -p DBNAME`).
5. Write `config/database.php` (copy the one from your PC and change host / name / user / password), create an empty file `config/installed.lock`, then run `php database/migrate.php` (it only runs migrations that are missing).

---

## 3. A. cPanel shared hosting

1. **PHP version:** cPanel → *MultiPHP Manager* → choose **PHP 8.2** for the domain. In *Select PHP Version → Extensions* make sure pdo_mysql, mbstring, fileinfo, curl, zip, openssl, dom, opcache are ticked.
2. **PHP settings:** *MultiPHP INI Editor* → set the values from `deploy/php-production.ini`:
   - `display_errors Off`
   - `upload_max_filesize 20M`, `post_max_size 25M`
   - `memory_limit 256M`
   - `expose_php Off`
3. **Database:** *MySQL Databases* → create a database (e.g. `user_sasthobazar`), a user with a strong password, and *Add user to database* with **All privileges**.
4. **Upload:** *File Manager* → `public_html` (or a subfolder) → *Upload* the zip → *Extract*. The folders `admin, app, config, core, …` must sit directly inside `public_html`.
5. **Install:**
   * New shop: open `https://example.com/install/`, enter the database details and the super admin account. When it says "installed", **delete the `install` folder** in File Manager.
   * Existing shop: follow 2.2 (phpMyAdmin import, `config/database.php`, `config/installed.lock`). For `php database/migrate.php` use *Terminal* in cPanel (or skip it if the backup came from the same version).
6. **Permissions:** folders `755`, files `644`.
   - Writable by PHP: `storage/` (all subfolders) and `public/uploads/`. On cPanel PHP runs as your user, so 755 is enough.
   - `config/database.php`: `600`.
7. **SSL:** *SSL/TLS Status* → *Run AutoSSL* (or *Let's Encrypt SSL*). When `https://` works, open the root `.htaccess` and remove the `#` in front of the three "force HTTPS" lines. Set `'secure' => true` in `config/config.php`.
8. **Cron job:** *Cron Jobs* → add **every 5 minutes** (`*/5 * * * *`):
   ```
   /usr/local/bin/php /home/USERNAME/public_html/cron/cron.php >> /home/USERNAME/public_html/storage/logs/cron.log 2>&1
   ```
   If that PHP is the wrong version, use `/opt/cpanel/ea-php82/root/usr/bin/php`. Check Admin → Settings → Backup: "Scheduled tasks last ran …" should update within 5 minutes.
9. **Email:** create a mailbox like `no-reply@example.com` in *Email Accounts*. In Admin → Settings → Email choose SMTP:
   - host `mail.example.com`, port `465`, SSL
   - that mailbox and its password as the username and password
   - From address: `no-reply@example.com`

   Turn on "Send emails" and send a test.
10. **Backups:** the app makes a daily database backup (cron). Also enable the host's backups (JetBackup / *Backup* in cPanel). Once a week download the newest file from Admin → Settings → Backup and a zip of `public/uploads/` to your PC or cloud storage.

---

## 4. B. VPS – Ubuntu 22.04 / 24.04

Commands are run as a sudo user. Replace `example.com`, `DBPASS` etc.

### 4.1 Server basics
```bash
sudo apt update && sudo apt -y upgrade
sudo adduser deploy && sudo usermod -aG sudo deploy          # log in as deploy from now on (SSH key)
sudo ufw allow OpenSSH && sudo ufw allow 80 && sudo ufw allow 443 && sudo ufw enable
sudo apt -y install fail2ban unattended-upgrades
sudo timedatectl set-timezone Asia/Dhaka
```

### 4.2 PHP, database, web server
```bash
# PHP 8.2 (Ubuntu 24.04 ships 8.3, which also works - then use php8.3-* and php8.3-fpm below)
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt -y install php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-opcache
sudo cp deploy/php-production.ini /etc/php/8.2/fpm/conf.d/99-sasthobazar.ini   # after uploading the code (4.3)
sudo cp deploy/php-production.ini /etc/php/8.2/cli/conf.d/99-sasthobazar.ini

sudo apt -y install mariadb-server
sudo mysql_secure_installation
sudo mysql -e "CREATE DATABASE sasthobazar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'sasthobazar'@'localhost' IDENTIFIED BY 'DBPASS';
  GRANT ALL PRIVILEGES ON sasthobazar.* TO 'sasthobazar'@'localhost'; FLUSH PRIVILEGES;"

# choose ONE web server
sudo apt -y install nginx            # Nginx
# or: sudo apt -y install apache2 && sudo a2enmod rewrite headers expires deflate ssl proxy_fcgi setenvif && sudo a2enconf php8.2-fpm
```

### 4.3 Code and permissions
```bash
sudo mkdir -p /var/www/sasthobazar && sudo chown deploy:www-data /var/www/sasthobazar
# upload the zip (scp sasthobazar.zip deploy@SERVER:/var/www/) and unzip into /var/www/sasthobazar, or git clone
cd /var/www/sasthobazar
sudo find . -type d -exec chmod 755 {} \; && sudo find . -type f -exec chmod 644 {} \;
sudo chown -R deploy:www-data . && sudo chown -R www-data:www-data storage public/uploads
sudo chmod -R 775 storage public/uploads
```
For a new shop, `config/` must be writable once for the installer: run `sudo chown www-data config`, install, then `sudo chown deploy config`. After that, `config/database.php` should be `640` and owned by `deploy:www-data`.

### 4.4 Web server site
* **Nginx:** `sudo cp deploy/nginx.conf.example /etc/nginx/sites-available/sasthobazar`. Edit `server_name`, `root` and the `php8.2-fpm.sock` path, then:
  ```bash
  sudo ln -s /etc/nginx/sites-available/sasthobazar /etc/nginx/sites-enabled/
  sudo rm /etc/nginx/sites-enabled/default
  sudo nginx -t && sudo systemctl reload nginx
  ```
  Nginx ignores `.htaccess`; the example file contains the same blocking rules. **Do not skip it.**
* **Apache:** `sudo cp deploy/apache-vhost.conf.example /etc/apache2/sites-available/sasthobazar.conf`, edit it, then `sudo a2ensite sasthobazar && sudo a2dissite 000-default && sudo systemctl reload apache2`. Also set `ServerTokens Prod` and `ServerSignature Off` in `/etc/apache2/conf-available/security.conf`.

### 4.5 Install or import
* New shop: open `http://example.com/install/`, finish, then `sudo rm -rf /var/www/sasthobazar/install`. For Nginx, temporarily comment out the `location ^~ /install` line, then put it back.
* Existing shop: section 2.2, then `sudo -u www-data php database/migrate.php`.

### 4.6 SSL (Let's Encrypt)
```bash
sudo apt -y install certbot python3-certbot-nginx     # or python3-certbot-apache
sudo certbot --nginx -d example.com -d www.example.com --redirect -m you@example.com --agree-tos   # or --apache
sudo certbot renew --dry-run                           # renewal is automatic (systemd timer)
```
Then set `'secure' => true` and `'url' => 'https://example.com'` in `config/config.php`. With Apache you may also enable the HTTPS lines in `.htaccess` (certbot `--redirect` already does it).

### 4.7 Cron
```bash
sudo crontab -u www-data -e
# add:
*/5 * * * * /usr/bin/php /var/www/sasthobazar/cron/cron.php >> /var/www/sasthobazar/storage/logs/cron.log 2>&1
```
Test it once by hand:
```bash
sudo -u www-data php /var/www/sasthobazar/cron/cron.php --list
sudo -u www-data php /var/www/sasthobazar/cron/cron.php
```

### 4.8 Backups (keep copies OFF the server)
The app already writes a daily database backup to `storage/backups/` (14 kept; change it in Admin → Settings → Backup). Copy them away every night together with the uploads, for example to another server or cloud storage with `rclone`:
```bash
sudo apt -y install rclone && rclone config            # set up a remote named "offsite" (Google Drive, S3, Backblaze ...)
sudo crontab -e
# 03:30 every night: database backups + pictures + private files
30 3 * * * rclone copy /var/www/sasthobazar/storage/backups offsite:sasthobazar/db --max-age 2d && rclone sync /var/www/sasthobazar/public/uploads offsite:sasthobazar/uploads && rclone sync /var/www/sasthobazar/storage/private offsite:sasthobazar/private
```
**Test a restore once a month** on a separate test site (Admin → Settings → Backup → Restore, or `gunzip < file.sql.gz | mysql …`).

### 4.9 Logs and monitoring
```bash
sudo tee /etc/logrotate.d/sasthobazar >/dev/null <<'EOF'
/var/www/sasthobazar/storage/logs/*.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    copytruncate
}
EOF
```
Use a free uptime monitor (UptimeRobot, Better Stack) on `https://example.com/` and `https://example.com/admin/login`.

---

## 5. Go-live checklist

- [ ] `config/config.php`: `debug => false`, `url` set, `session.secure => true`, `trust_proxy` only if behind Cloudflare / a proxy.
- [ ] `install/` folder deleted; `config/installed.lock` exists.
- [ ] Demo data removed (`php database/demo.php --remove`) and demo coupons / banners reviewed.
- [ ] HTTPS works and `http://` redirects to `https://`.
- [ ] Super admin password is strong and unique; staff have only the roles they need.
- [ ] Admin → Settings:
  - General (name, logo, contacts)
  - Orders & payments (numbers for bKash / Nagad)
  - Email (test sent)
  - SMS (when the gateway account is ready)
- [ ] Shipping zones and rates checked; courier API keys entered; **courier mock mode switched off** in Couriers.
- [ ] VAT settings decided with your accountant (Accounting → VAT settings).
- [ ] Footer pages filled in (About, Privacy, Terms, Return policy, Contact).
- [ ] Cron running (Settings → Backup shows a recent "last ran").
- [ ] First backup downloaded off the server.
- [ ] Security spot-checks and the 5-minute smoke test from `docs/TESTING-CHECKLIST.md` done.

## 6. Updating the site later

1. Admin → Settings → Backup → *Create & download* (and keep a copy of the current files).
2. Upload the changed files (never overwrite `config/database.php`, `config/installed.lock`, `storage/` or `public/uploads/`).
3. Run `php database/migrate.php` (cPanel Terminal / SSH).
4. VPS: `sudo systemctl reload php8.2-fpm` (clears the opcache).
5. Do the 5-minute smoke test and check `storage/logs/php-error.log`.

## 7. Troubleshooting

| Problem | Cause / fix |
|---|---|
| Every page redirects to `/install/` | `config/installed.lock` is missing. Create an empty file with that name. **Do not run the installer on a shop that has data.** |
| Blank page or "500" | Read `storage/logs/php-error.log` (the 500 page shows a reference code you can search for). Common causes: a missing PHP extension, a wrong `config/database.php`, folder permissions. |
| Pages work but no styles, or "404" on every link | Apache: `mod_rewrite` off or `AllowOverride None` – enable both. Nginx: the site config from `deploy/` is missing. |
| Picture / prescription upload fails | `public/uploads/` or `storage/` not writable by PHP, or `upload_max_filesize` / `post_max_size` too small. |
| Logged out all the time behind Cloudflare | Set `'trust_proxy' => true` (and SSL mode "Full (strict)" in Cloudflare). |
| Login says "too many attempts" for everyone | The same as above: behind a proxy every visitor looks like one IP until `trust_proxy` is on. |
| Emails / SMS not arriving | Admin → Settings → SMS & email log: "logged" = sending is switched off; "failed" shows the gateway's answer. |
| "Scheduled tasks have never run" | The cron job is missing or uses the wrong PHP path. Run `php cron/cron.php` by hand to see the error. |
| Seller balances stay "pending" | Cron is not running (earnings are released by the `release_earnings` task). |
