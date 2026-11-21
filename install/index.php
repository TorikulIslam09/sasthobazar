<?php
/**
 * SasthoBazar installer
 * URL: http://localhost/sasthobazar/install/
 *
 * 1. Checks requirements
 * 2. Creates the database (utf8mb4)
 * 3. Runs database/schema.sql, database/seed.sql, then database/migrations/*
 * 4. Sets your Super Admin credentials
 * 5. Writes config/database.php and config/installed.lock
 *
 * To re-install: delete config/installed.lock and open this page again.
 */
declare(strict_types=1);

define('SKIP_INSTALL_CHECK', true);
require dirname(__DIR__) . '/core/bootstrap.php';

// Once installed the installer is switched off completely (it could otherwise reveal server details).
// To reinstall on purpose: delete config/installed.lock. On a live server, delete the whole install/ folder.
if (is_file(CONFIG_PATH . '/installed.lock') && !IS_CLI) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Not found');
}

@set_time_limit(300);

$lockFile   = CONFIG_PATH . '/installed.lock';
$dbConfFile = CONFIG_PATH . '/database.php';
$schemaFile = ROOT_PATH . '/database/schema.sql';
$seedFile   = ROOT_PATH . '/database/seed.sql';
$db         = config('db');

$errors = [];
$done   = false;
$stats  = [];

/* ---------------- Requirements ---------------- */
$requirements = [
    'PHP >= 8.0'                       => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO MySQL extension'              => extension_loaded('pdo_mysql'),
    'mbstring extension'               => extension_loaded('mbstring'),
    'fileinfo extension (uploads)'     => extension_loaded('fileinfo'),
    'config/ is writable'              => is_writable(CONFIG_PATH),
    'database/schema.sql exists'       => is_file($schemaFile),
    'database/seed.sql exists'         => is_file($seedFile),
];
foreach (['storage/logs', 'storage/private/prescriptions', 'public/uploads'] as $dir) {
    if (!is_dir(ROOT_PATH . '/' . $dir)) {
        @mkdir(ROOT_PATH . '/' . $dir, 0775, true);
    }
    $requirements[$dir . '/ is writable'] = is_writable(ROOT_PATH . '/' . $dir);
}
$requirementsOk = !in_array(false, $requirements, true);

/* ---------------- Already installed ---------------- */
$installed = is_file($lockFile);

/* ---------------- Handle POST ---------------- */
$form = [
    'db_host' => $db['host'] ?? '127.0.0.1',
    'db_port' => (string) ($db['port'] ?? 3306),
    'db_name' => $db['database'] ?? 'sasthobazar',
    'db_user' => $db['username'] ?? 'root',
    'admin_name'  => 'Super Admin',
    'admin_email' => 'admin@sasthobazar.com',
    'admin_phone' => '01700000000',
    'reset'       => '',
];

if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($form as $k => $_) {
        $form[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $dbPass        = (string) ($_POST['db_pass'] ?? '');
    $adminPass     = (string) ($_POST['admin_pass'] ?? '');
    $adminPassConf = (string) ($_POST['admin_pass_confirmation'] ?? '');

    if (!CSRF::verifyRequest()) {
        $errors[] = 'Session expired. Please submit again. / সেশন শেষ হয়েছে, আবার চেষ্টা করুন।';
    }
    if (!$requirementsOk) {
        $errors[] = 'Fix the requirements first. / আগে রিকোয়ারমেন্টগুলো ঠিক করুন।';
    }
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $form['db_name'])) {
        $errors[] = 'Database name: only letters, numbers and underscore.';
    }
    if ($form['db_host'] === '' || $form['db_user'] === '') {
        $errors[] = 'Database host and username are required.';
    }
    if (!ctype_digit($form['db_port'])) {
        $errors[] = 'Database port must be a number.';
    }
    if ($form['admin_name'] === '') {
        $errors[] = 'Admin name is required.';
    }
    if (!filter_var($form['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid admin email is required.';
    }
    $form['admin_phone'] = Auth::normalizePhone($form['admin_phone']);
    if (!Auth::isValidBdPhone($form['admin_phone'])) {
        $errors[] = 'Admin phone must be a valid Bangladeshi mobile number (01XXXXXXXXX).';
    }
    if (strlen($adminPass) < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    }
    if ($adminPass !== $adminPassConf) {
        $errors[] = 'Admin passwords do not match.';
    }

    if (!$errors) {
        try {
            // 1) Connect to server (no DB yet)
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $form['db_host'], (int) $form['db_port']),
                $form['db_user'],
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );

            // 2) Create database
            $dbName = $form['db_name'];
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+06:00'");

            // 3) Existing tables?
            $stmt = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
            $stmt->execute([$dbName]);
            $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($existing && $form['reset'] !== '1') {
                throw new RuntimeException(sprintf(
                    'Database "%s" already has %d tables. Tick "Delete existing tables" to reinstall (ALL DATA WILL BE LOST), or use a different database name.',
                    $dbName, count($existing)
                ));
            }
            if ($existing) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach ($existing as $t) {
                    $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $t) . '`');
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }

            // 4) Schema (DDL auto-commits, so no transaction here)
            $stats['schema statements'] = SqlImporter::runFile($pdo, $schemaFile);

            // 5) Seed + admin credentials in one transaction
            $pdo->beginTransaction();
            try {
                $stats['seed statements'] = SqlImporter::runFile($pdo, $seedFile);
                $upd = $pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ?, password_hash = ? WHERE id = 1');
                $upd->execute([$form['admin_name'], strtolower($form['admin_email']), $form['admin_phone'], password_hash($adminPass, PASSWORD_DEFAULT)]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            // 5b) Migrations (database/migrations/*) - later steps add tables/permissions here
            $stats['migrations'] = count((new Migrator($pdo, ROOT_PATH . '/database/migrations'))->run());

            foreach (['tables' => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $pdo->quote($dbName),
                      'permissions' => 'SELECT COUNT(*) FROM permissions',
                      'roles' => 'SELECT COUNT(*) FROM roles',
                      'divisions' => "SELECT COUNT(*) FROM locations WHERE type = 'division'",
                      'districts' => "SELECT COUNT(*) FROM locations WHERE type = 'district'",
                      'upazilas'  => "SELECT COUNT(*) FROM locations WHERE type = 'upazila'"] as $label => $q) {
                $stats[$label] = (int) $pdo->query($q)->fetchColumn();
            }

            // 6) Write config/database.php
            $conf = [
                'host' => $form['db_host'], 'port' => (int) $form['db_port'], 'database' => $dbName,
                'username' => $form['db_user'], 'password' => $dbPass,
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            ];
            $php = "<?php\n/**\n * Database connection settings (written by installer on " . date('Y-m-d H:i:s') . ").\n */\nreturn " . var_export($conf, true) . ";\n";
            if (file_put_contents($dbConfFile, $php, LOCK_EX) === false) {
                throw new RuntimeException('Could not write config/database.php');
            }

            // 7) Lock
            file_put_contents($lockFile, 'Installed on ' . date('Y-m-d H:i:s') . "\n", LOCK_EX);
            $done = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SasthoBazar Installer / ইনস্টলার</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { font-family: 'Hind Siliguri', system-ui, sans-serif; background: #f2f7f5; }
  .brand { color: #0b8457; font-weight: 700; }
  .card { border: 0; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
</style>
</head>
<body>
<div class="container py-4" style="max-width: 760px">
  <h1 class="h3 mb-1 brand">স্বাস্থ্যবাজার <small class="text-muted fs-6">SasthoBazar Installer</small></h1>
  <p class="text-muted mb-4">ডাটাবেস তৈরি করে schema.sql ও seed.sql চালানো হবে।</p>

  <?php if ($installed && !$done): ?>
    <div class="alert alert-info">
      <strong>Already installed / ইতিমধ্যে ইনস্টল করা আছে।</strong><br>
      To reinstall, delete <code>config/installed.lock</code> and reload this page.
    </div>
    <a class="btn btn-success" href="<?= $h(BASE_PATH . '/') ?>">Open Store</a>
    <a class="btn btn-outline-success" href="<?= $h(BASE_PATH . '/admin/login') ?>">Admin Login</a>

  <?php elseif ($done): ?>
    <div class="alert alert-success">
      <h2 class="h5">✅ Installation complete / ইনস্টলেশন সম্পন্ন হয়েছে!</h2>
      <ul class="mb-0">
        <?php foreach ($stats as $k => $v): ?><li><?= $h($k) ?>: <strong><?= $h($v) ?></strong></li><?php endforeach; ?>
      </ul>
    </div>
    <div class="alert alert-warning">For safety, delete or rename the <code>/install</code> folder on a live server.</div>
    <a class="btn btn-success" href="<?= $h(BASE_PATH . '/admin/login') ?>">Go to Admin Login</a>
    <a class="btn btn-outline-success" href="<?= $h(BASE_PATH . '/') ?>">Open Store</a>

  <?php else: ?>
    <div class="card mb-4">
      <div class="card-body">
        <h2 class="h6 mb-3">1. Requirements / প্রয়োজনীয়তা</h2>
        <ul class="list-unstyled mb-0">
          <?php foreach ($requirements as $label => $ok): ?>
            <li><?= $ok ? '✅' : '❌' ?> <?= $h($label) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= $h($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" class="card" autocomplete="off">
      <div class="card-body">
        <?= CSRF::field() ?>
        <h2 class="h6 mb-3">2. Database / ডাটাবেস</h2>
        <div class="row g-3 mb-4">
          <div class="col-8"><label class="form-label">Host</label><input class="form-control" name="db_host" value="<?= $h($form['db_host']) ?>" required></div>
          <div class="col-4"><label class="form-label">Port</label><input class="form-control" name="db_port" value="<?= $h($form['db_port']) ?>" required></div>
          <div class="col-md-4"><label class="form-label">Database name</label><input class="form-control" name="db_name" value="<?= $h($form['db_name']) ?>" required></div>
          <div class="col-md-4"><label class="form-label">Username</label><input class="form-control" name="db_user" value="<?= $h($form['db_user']) ?>" required></div>
          <div class="col-md-4"><label class="form-label">Password</label><input class="form-control" type="password" name="db_pass" placeholder="XAMPP: empty"></div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="reset" value="1" id="reset" <?= $form['reset'] === '1' ? 'checked' : '' ?>>
              <label class="form-check-label text-danger" for="reset">Delete existing tables in this database (reinstall — all data lost) / পুরনো টেবিল মুছে ফেলুন</label>
            </div>
          </div>
        </div>

        <h2 class="h6 mb-3">3. Super Admin / সুপার অ্যাডমিন</h2>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Name / নাম</label><input class="form-control" name="admin_name" value="<?= $h($form['admin_name']) ?>" required></div>
          <div class="col-md-6"><label class="form-label">Mobile / মোবাইল</label><input class="form-control" name="admin_phone" value="<?= $h($form['admin_phone']) ?>" required></div>
          <div class="col-12"><label class="form-label">Email / ইমেইল</label><input class="form-control" type="email" name="admin_email" value="<?= $h($form['admin_email']) ?>" required></div>
          <div class="col-md-6"><label class="form-label">Password (min 8)</label><input class="form-control" type="password" name="admin_pass" minlength="8" required></div>
          <div class="col-md-6"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="admin_pass_confirmation" minlength="8" required></div>
        </div>
      </div>
      <div class="card-footer bg-white border-0 pb-4">
        <button class="btn btn-success btn-lg w-100" <?= $requirementsOk ? '' : 'disabled' ?>>Install / ইনস্টল করুন</button>
      </div>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
