# SasthoBazar (স্বাস্থ্যবাজার)

Multi-vendor healthcare e-commerce + business management system.
Raw PHP 8, PDO + MySQL, Bootstrap 5, Bengali/English UI, BDT, Asia/Dhaka.

## Folder structure

```
sasthobazar/
├── .htaccess            root rewrite: storefront -> public/, blocks private folders
├── index.php            fallback redirect to public/ (if mod_rewrite is off)
├── admin/               Admin panel front controller        -> /sasthobazar/admin
├── vendor-panel/        Vendor panel front controller       -> /sasthobazar/vendor-panel
├── public/              Storefront front controller, assets, uploads -> /sasthobazar/
├── install/             One-time web installer              -> /sasthobazar/install
├── app/
│   ├── controllers/     HomeController, AuthController, AdminDashboardController, ...
│   ├── models/          User, Category, Location
│   ├── views/           layouts/, partials/, home/, auth/, admin/, vendor/, errors/
│   ├── routes/          web.php, admin.php, vendor.php
│   └── lang/            bn.php, en.php
├── core/                bootstrap, Router, Database, Auth, CSRF, Helpers, View, Controller, Model, SqlImporter
├── config/              config.php, database.php (written by installer), installed.lock
├── database/            schema.sql, seed.sql, migrations/, migrate.php, demo.php (command line)
├── cron/                cron.php - scheduled tasks, run every 5 minutes (command line)
├── docs/                SECURITY-REVIEW.md, TESTING-CHECKLIST.md, DEPLOYMENT.md (not web-accessible)
├── deploy/              Nginx / Apache / PHP production examples (not web-accessible)
└── storage/             logs/, private/, backups/ (not web-accessible)
```

## Install (XAMPP)

1. Copy this folder to `C:\xampp\htdocs\sasthobazar`
2. Start **Apache** and **MySQL** in the XAMPP Control Panel
3. Open http://localhost/sasthobazar/install/ and submit the form
4. Log in at http://localhost/sasthobazar/admin/login

Re-install: delete `config/installed.lock`, open `/install/`, tick "Delete existing tables".

## Database migrations

Schema changes after Step 1 live in `database/migrations/` (`.sql` or `.php` returning `function (PDO $pdo)`).
Each runs once and is recorded in the `migrations` table. The installer runs them automatically; for an existing install:

```
cd C:\xampp\htdocs\sasthobazar
C:\xampp\php\php.exe database\migrate.php --status
C:\xampp\php\php.exe database\migrate.php
```

## Permissions & admin menu

`config/modules.php` is the single source of truth for admin modules, their actions (view/create/edit/delete/export + special
actions) and the sidebar. After adding a module there, click **Roles & Permissions → Sync permissions** (Super Admin).

- Route protection: `['guard' => 'admin', 'permission' => 'roles.edit']` (or `'a.view|b.view'` for any-of), groups via `$router->group()`
- In code/views: `can('module.action')`
- Every `Model::create/updateById/deleteById` is written to `audit_logs` automatically (secrets masked)

## Conventions

- Every POST is CSRF-checked in `Router::dispatch()`; forms use `<?= csrf_field() ?>`
- Escape all output with `e()`
- Money: `DECIMAL(12,2)` in DB, `money($x)` in views (৳, South-Asian grouping, Bangla digits in bn UI)
- Orders / stock / wallet changes: `Database::getInstance()->transaction(fn($db) => ...)` with `SELECT ... FOR UPDATE`
- Permissions: `module.action` keys; `Auth::can('products.edit')`; route option `['guard' => 'admin', 'permission' => 'products.edit']`
- Commission resolution: product > vendor+category > vendor > category > global (8%, `settings.default_commission_rate`)

## Going live

* Deployment (cPanel and Ubuntu VPS, SSL, cron, backups): [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md)
* Testing checklist (customer, vendor, admin, commission and stock math): [`docs/TESTING-CHECKLIST.md`](docs/TESTING-CHECKLIST.md)
* Security review and hardening notes: [`docs/SECURITY-REVIEW.md`](docs/SECURITY-REVIEW.md)
* Scheduled tasks: `php cron/cron.php` every 5 minutes (`--list` shows the tasks, `--task=backup` runs one now)

