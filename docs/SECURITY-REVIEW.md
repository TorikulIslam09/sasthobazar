# SasthoBazar – security review (Step 12)

Scope: the whole project (`core/`, `app/`, `public/`, `admin/`, `vendor-panel/`, `install/`, `database/`, `.htaccess` files).
Method: automated scans (every route's guard and permission, every SQL string built from a variable, every unescaped `<?= … ?>` in the views, every file upload path), reading the security-relevant code, and live tests against Apache and the PHP test server.

## Result

| Area | Status | Notes |
|---|---|---|
| SQL injection | OK | All user input goes through PDO bound parameters with native prepares (`ATTR_EMULATE_PREPARES = false`). Dynamic SQL fragments are whitelisted sort keys, `(int)` casts, generated `:p1, :p2` placeholders, or identifiers passed through `Database::ident()`. |
| XSS | OK | Views escape with `e()` (`htmlspecialchars`, `ENT_QUOTES`). Helpers that build HTML escape their inputs. CMS HTML (pages, blog, FAQ) is cleaned by `Html::clean()` on save **and** on display. JavaScript inserts server data with `textContent` or an escaping helper. Probes with `<script>` / `onerror` in every search and filter parameter were not reflected. |
| CSRF | OK + fixed | The router checks a token on every POST (form field or `X-CSRF-TOKEN` header). The only exception is the courier webhook, which checks its own secret with `hash_equals`. Session cookie is `SameSite=Lax`. **Fixed:** failed AJAX checks returned status 419, which Apache turns into 500; now 403. |
| Uploads | OK + hardened | Images: real type check (`finfo` + `getimagesize`), size and dimension limits, embedded PHP / script rejected, random file name, extension taken from the detected type. Prescriptions and seller documents are stored in `storage/private` and streamed only to their owner or staff, with a sandbox policy. **Hardened** `public/uploads/.htaccess`: PHP switched off, `.php.jpg` / `.phtml` / HTML / JS refused, SVG served with a script-blocking policy. Tested: a PHP file placed in uploads is refused (403) and never runs. |
| Sessions | OK + hardened | Strict mode, cookies only, `HttpOnly`, idle timeout, session ID regenerated on login and logout, 48-character IDs. **Hardened:** the cookie is HTTPS-only automatically on HTTPS requests (it was a manual setting before). |
| Login | OK | Rate limit per IP and per account, account lock after 5 failures, same message for every failure, constant-time check for unknown users, bcrypt with automatic rehash. **Added:** customer registration is limited to 5 accounts per IP per hour. |
| Access control | OK | 300+ routes listed by script: every admin route requires the admin guard and a permission (except dashboard / own profile / own notifications, which any staff may use; role sync is super-admin only). Vendor and customer routes are guarded, and every record is loaded with the owner in the query (`… AND vendor_id = :v`, `… AND user_id = :u`). Tested: customer → admin 403, vendor → admin 403, customer → vendor 403, limited employees get 403 outside their permissions, restore needs `settings.backup` + password. |
| Redirects | OK | After login only same-site paths (`/…`, not `//…`) are used. Logout `next` only accepts the three login pages. Notification links only go to our own paths. |
| Error handling | Hardened | Error details are now shown **only on localhost / `*.test`**, even if `debug` is left on by mistake. Live visitors get a plain 500 page with a reference code; the detail goes to `storage/logs/php-error.log`. |
| Headers | Hardened | Added `Content-Security-Policy` (scripts only from this site and cdn.jsdelivr.net, no plugins, no `<base>` hijack, no framing by other sites), `Permissions-Policy`, `Strict-Transport-Security` on HTTPS. `X-Powered-By` removed. Apache adds the basic headers to static files without duplicating PHP's. |
| Installer | Fixed | `install/` answered with a page (requirements, paths) after installation. Now it returns **404** once `config/installed.lock` exists. Still delete the folder on the live server. |
| Web access to private files | Hardened | Root `.htaccess` blocks `config, core, app, database, storage, cron, docs, deploy`, dot-files, and `.sql/.gz/.log/.md/.ini/.env…` files, plus the old static prototypes (`index.html`, `admin.html`, `db.js`). All verified with live requests (403). |
| Secrets | OK + hardened | Database password only in `config/database.php` (blocked). SMTP password and SMS API key are never shown back on the settings page and are masked in the audit log. **Added:** they are also kept out of the "old input" stored in the session after a form error. |
| Proxy / CDN | Added | `app.trust_proxy` (off by default): when the site is behind Cloudflare or a reverse proxy, rate limits and HTTPS detection use the forwarded headers. Off = forwarded headers are ignored and cannot be spoofed. |

## Known limits (accepted, or for later)

* **Inline scripts are still allowed by the CSP** (16 inline `onclick`/`onchange` handlers and 3 small inline scripts). Moving them to `app.js` would allow a strict `script-src 'self'`.
* **No "forgot password" by SMS/email yet.** Staff reset passwords from Employees; customers contact support. Add once an SMS gateway is live.
* **No two-factor login for staff.** Recommended for the super admin once SMS works.
* **SMS gateway URL is admin-controlled.** A super admin / settings editor can make the server call any URL (by design). Keep `settings.edit` for trusted people only.
* **Backups contain personal data.** They are in `storage/backups` (blocked from the web). Copy them off the server encrypted, and delete old copies.
* **Tested on PHP 8.0** (XAMPP). PHP 8.0 no longer gets security fixes. Use PHP **8.2** on the server and check `storage/logs/php-error.log` after the first day.

## Files changed in this step

* `core/bootstrap.php` – HTTPS detection, local-only debug, secure cookie, CSP / HSTS / Permissions-Policy, friendly 500 page with reference.
* `core/Router.php` – CSRF failure status 403.
* `core/Helpers.php` – `clientIp()` with optional proxy support; secrets removed from saved form input.
* `app/controllers/AuthController.php` – registration rate limit.
* `install/index.php` – 404 after installation.
* `.htaccess`, `public/.htaccess`, `public/uploads/.htaccess`, `cron/.htaccess`, `docs/.htaccess`, `deploy/.htaccess`, `public/robots.txt`.
* `config/config.php` – `debug_anywhere`, `url`, `trust_proxy` options.
* `cron/cron.php` – scheduled tasks; `AdminBackupController` + backup page – schedule options and cron status.
* `deploy/` – Nginx, Apache and PHP production examples.
