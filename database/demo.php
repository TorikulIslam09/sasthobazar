<?php
/**
 * Demo data: brands, 2 demo vendors, ~40 products (medicine, OTC, devices, baby, personal care...) with
 * pictures, variants, stock batches, a flash sale, home banners, coupons, a demo customer and reviews.
 *
 *   C:\xampp\php\php.exe database\demo.php            add the demo data (skips if already added)
 *   C:\xampp\php\php.exe database\demo.php --remove   remove everything the demo added
 *
 * Everything created is listed in the setting "demo_data", so --remove deletes only demo records.
 * Demo logins:  customer 01700000099 / demo1234   vendors 01700000101, 01700000102 / demo1234 (vendor panel)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/core/bootstrap.php';

$db = Database::getInstance();
$manifest = json_decode((string) $db->fetchColumn("SELECT value FROM settings WHERE `key` = 'demo_data'"), true) ?: null;

/* ============================================================== remove */
if (in_array('--remove', $argv, true)) {
    if (!$manifest) {
        echo "No demo data found.\n";
        exit(0);
    }
    $ids = static fn(string $k) => array_map('intval', $manifest[$k] ?? []);
    $in = static fn(array $a) => $a ? implode(',', $a) : '0';
    $products = $ids('products');
    $db->query('DELETE FROM flash_sales WHERE id IN (' . $in($ids('flash_sales')) . ')');
    $db->query('DELETE FROM banners WHERE id IN (' . $in($ids('banners')) . ')');
    $db->query('DELETE FROM coupons WHERE id IN (' . $in($ids('coupons')) . ') AND used_count = 0');
    $db->query('UPDATE coupons SET is_active = 0 WHERE id IN (' . $in($ids('coupons')) . ')');
    foreach ($products as $pid) {
        $db->query('DELETE FROM stock_movements WHERE product_id = ?', [$pid]);
        $db->query('DELETE FROM inventory_batches WHERE product_id = ?', [$pid]);
        $ordered = (int) $db->fetchColumn('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$pid]);
        if ($ordered) {
            $db->query("UPDATE products SET status = 'inactive', deleted_at = NOW(), slug = CONCAT(slug, '-del', id), sku = NULL WHERE id = ?", [$pid]);
        } else {
            $db->query('DELETE FROM products WHERE id = ?', [$pid]);
        }
    }
    foreach ($ids('vendors') as $vid) {
        $hasOrders = (int) $db->fetchColumn('SELECT COUNT(*) FROM order_vendor_splits WHERE vendor_id = ?', [$vid]);
        $uid = (int) $db->fetchColumn('SELECT user_id FROM vendors WHERE id = ?', [$vid]);
        if ($hasOrders) {
            $db->query("UPDATE vendors SET status = 'suspended', suspension_reason = 'Demo data removed' WHERE id = ?", [$vid]);
            continue;
        }
        $db->query('DELETE FROM vendor_wallet_transactions WHERE vendor_id = ?', [$vid]);
        $db->query('DELETE FROM vendor_wallets WHERE vendor_id = ?', [$vid]);
        $db->query('DELETE FROM vendors WHERE id = ?', [$vid]);
        $db->query('DELETE FROM notifications WHERE user_id = ?', [$uid]);
        $db->query('DELETE FROM users WHERE id = ?', [$uid]);
    }
    foreach ($ids('brands') as $bid) {
        if (!(int) $db->fetchColumn('SELECT COUNT(*) FROM products WHERE brand_id = ?', [$bid])) {
            $db->query('DELETE FROM brands WHERE id = ?', [$bid]);
        }
    }
    foreach ($ids('users') as $uid) {
        if (!(int) $db->fetchColumn('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$uid])) {
            foreach (['reviews', 'wishlists', 'carts', 'addresses', 'notifications'] as $t) {
                $db->query("DELETE FROM {$t} WHERE user_id = ?", [$uid]);
            }
            $db->query('DELETE FROM users WHERE id = ?', [$uid]);
        }
    }
    foreach (glob(ROOT_PATH . '/public/uploads/demo/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir(ROOT_PATH . '/public/uploads/demo');
    $db->query("DELETE FROM settings WHERE `key` = 'demo_data'");
    echo "Demo data removed (" . count($products) . " products).\n";
    exit(0);
}

if ($manifest) {
    echo "Demo data is already installed. Run with --remove first to reinstall.\n";
    exit(0);
}

/* ============================================================== pictures (SVG, no GD needed) */
$dir = ROOT_PATH . '/public/uploads/demo';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$x = static fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');

/** Product picture: a pack shape in the brand colour with the name and strength. */
$picture = static function (string $file, string $name, string $sub, string $brand, string $color, string $shape) use ($dir, $x): string {
    $words = explode(' ', $name);
    $lines = [''];
    foreach ($words as $w) {
        $cur = &$lines[count($lines) - 1];
        if (mb_strlen($cur . ' ' . $w) > 16 && $cur !== '') {
            $lines[] = $w;
        } else {
            $cur = trim($cur . ' ' . $w);
        }
        unset($cur);
    }
    $lines = array_slice($lines, 0, 3);
    $text = '';
    $y = 330 - (count($lines) - 1) * 22;
    foreach ($lines as $i => $l) {
        $text .= '<text x="300" y="' . ($y + $i * 46) . '" font-size="40" font-weight="700" text-anchor="middle" fill="#1f2a2a">' . $x($l) . '</text>';
    }
    $shapes = [
        'strip'   => '<rect x="120" y="150" width="360" height="300" rx="18" fill="#eef3f3" stroke="#c9d6d6" stroke-width="3"/>'
            . implode('', array_map(static fn($i) => '<circle cx="' . (165 + ($i % 5) * 68) . '" cy="' . (190 + intdiv($i, 5) * 220) . '" r="22" fill="#fff" stroke="#c9d6d6" stroke-width="3"/>', range(0, 9))),
        'capsule' => '<rect x="120" y="150" width="360" height="300" rx="18" fill="#eef3f3" stroke="#c9d6d6" stroke-width="3"/>'
            . implode('', array_map(static fn($i) => '<rect x="' . (140 + ($i % 4) * 85) . '" y="' . (172 + intdiv($i, 4) * 225) . '" width="64" height="30" rx="15" fill="' . $color . '" opacity=".75"/>', range(0, 7))),
        'bottle'  => '<rect x="235" y="95" width="130" height="55" rx="10" fill="' . $color . '"/><rect x="170" y="145" width="260" height="355" rx="40" fill="#f7fbfb" stroke="#c9d6d6" stroke-width="3"/>',
        'tube'    => '<path d="M200 120 h200 l-25 360 h-150 z" fill="#f7fbfb" stroke="#c9d6d6" stroke-width="3"/><rect x="255" y="478" width="90" height="40" rx="6" fill="' . $color . '"/>',
        'box'     => '<rect x="130" y="140" width="340" height="340" rx="14" fill="#f7fbfb" stroke="#c9d6d6" stroke-width="3"/><path d="M130 140 l40 -40 h340 l-40 40 z" fill="#e3ecec"/><path d="M470 140 l40 -40 v340 l-40 40 z" fill="#d7e3e3"/>',
        'device'  => '<rect x="190" y="110" width="220" height="380" rx="40" fill="#f7fbfb" stroke="#c9d6d6" stroke-width="3"/><rect x="220" y="150" width="160" height="90" rx="10" fill="#1f2a2a" opacity=".85"/><circle cx="300" cy="420" r="28" fill="' . $color . '"/>',
        'sachet'  => '<path d="M150 130 h300 v340 h-300 z" fill="#f7fbfb" stroke="#c9d6d6" stroke-width="3" stroke-dasharray="10 6"/>',
        'pack'    => '<rect x="140" y="120" width="320" height="380" rx="30" fill="#f7fbfb" stroke="#c9d6d6" stroke-width="3"/>',
    ];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">'
        . '<rect width="600" height="600" fill="#ffffff"/><circle cx="520" cy="80" r="140" fill="' . $color . '" opacity=".10"/>'
        . ($shapes[$shape] ?? $shapes['box'])
        . '<rect x="130" y="250" width="340" height="' . (130 + (count($lines) - 1) * 46) . '" rx="12" fill="#ffffff" opacity=".93"/>'
        . '<text x="300" y="285" font-size="22" font-weight="600" text-anchor="middle" fill="' . $color . '" font-family="Arial, sans-serif">' . $x(mb_strtoupper($brand)) . '</text>'
        . str_replace('<text ', '<text font-family="Arial, sans-serif" ', $text)
        . '<text x="300" y="' . ($y + count($lines) * 46 + 4) . '" font-size="26" text-anchor="middle" fill="#6b7a7a" font-family="Arial, sans-serif">' . $x($sub) . '</text>'
        . '<rect x="0" y="560" width="600" height="40" fill="' . $color . '"/></svg>';
    file_put_contents($dir . '/' . $file . '.svg', $svg);
    return 'uploads/demo/' . $file . '.svg';
};

$banner = static function (string $file, string $title, string $sub, string $c1, string $c2, string $icon) use ($dir, $x): string {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1360" height="400" viewBox="0 0 1360 400">'
        . '<defs><linearGradient id="g" x1="0" x2="1"><stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient></defs>'
        . '<rect width="1360" height="400" fill="url(#g)"/><circle cx="1180" cy="200" r="220" fill="#fff" opacity=".08"/><circle cx="1080" cy="330" r="120" fill="#fff" opacity=".08"/>'
        . '<text x="1110" y="250" font-size="190" text-anchor="middle" fill="#fff" opacity=".9" font-family="Arial, sans-serif">' . $icon . '</text>'
        . '<text x="80" y="180" font-size="58" font-weight="700" fill="#fff" font-family="Arial, sans-serif">' . $x($title) . '</text>'
        . '<text x="80" y="245" font-size="30" fill="#fff" opacity=".92" font-family="Arial, sans-serif">' . $x($sub) . '</text></svg>';
    file_put_contents($dir . '/' . $file . '.svg', $svg);
    return 'uploads/demo/' . $file . '.svg';
};

/* ============================================================== data */
$cat = array_column($db->fetchAll('SELECT id, slug FROM categories'), 'id', 'slug');
$brands = [ // key => [name, name_bn, colour, manufacturer?]
    'beximco'   => ['Beximco Pharma', 'বেক্সিমকো ফার্মা', '#0b6fb3', 1],
    'square'    => ['Square Pharmaceuticals', 'স্কয়ার ফার্মাসিউটিক্যালস', '#d61f26', 1],
    'incepta'   => ['Incepta Pharmaceuticals', 'ইনসেপ্টা ফার্মাসিউটিক্যালস', '#5b2d8e', 1],
    'healthcare'=> ['Healthcare Pharmaceuticals', 'হেলথকেয়ার ফার্মাসিউটিক্যালস', '#00897b', 1],
    'acme'      => ['ACME Laboratories', 'একমি ল্যাবরেটরিজ', '#ef6c00', 1],
    'renata'    => ['Renata Limited', 'রেনাটা লিমিটেড', '#1565c0', 1],
    'skf'       => ['Eskayef Pharmaceuticals', 'এসকেএফ ফার্মাসিউটিক্যালস', '#2e7d32', 1],
    'aci'       => ['ACI Limited', 'এসিআই লিমিটেড', '#0d47a1', 1],
    'smc'       => ['SMC Enterprise', 'এসএমসি এন্টারপ্রাইজ', '#00838f', 1],
    'roche'     => ['Roche (Accu-Chek)', 'রোশ (একু-চেক)', '#00508c', 0],
    'omron'     => ['Omron', 'ওমরন', '#003c71', 0],
    'nestle'    => ['Nestlé', 'নেসলে', '#7b5e3c', 0],
    'johnson'   => ['Johnson\'s Baby', 'জনসন্স বেবি', '#e91e63', 0],
    'unilever'  => ['Unilever', 'ইউনিলিভার', '#1f3c88', 0],
    'haleon'    => ['Haleon', 'হ্যালিওন', '#6a1b9a', 0],
    'reckitt'   => ['Reckitt', 'রেকিট', '#c2185b', 0],
    'pg'        => ['P&G', 'পিএন্ডজি', '#0057a8', 0],
    'himalaya'  => ['Himalaya', 'হিমালয়া', '#2e7d32', 0],
];

/* key, name_en, name_bn, generic, strength, form, pack, unit, type, category, brand, mrp, sale, rx, featured, vendor(0/1/2), shape, stock, extra */
$products = [
    ['napa500', 'Napa 500', 'নাপা ৫০০', 'Paracetamol', '500 mg', 'Tablet', '10 tablets', 'strip', 'otc', 'otc-medicine', 'beximco', 12, 12, 0, 1, 0, 'strip', 400, ['variants' => [['1 Strip (10 tablets)', '১ পাতা (১০টি)', 1, 12, 12, 300], ['1 Box (20 strips)', '১ বক্স (২০ পাতা)', 20, 240, 228, 25]]]],
    ['napaextra', 'Napa Extra', 'নাপা এক্সট্রা', 'Paracetamol + Caffeine', '500 mg + 65 mg', 'Tablet', '10 tablets', 'strip', 'otc', 'otc-medicine', 'beximco', 25, 25, 0, 1, 0, 'strip', 300, []],
    ['ace500', 'Ace 500', 'এইস ৫০০', 'Paracetamol', '500 mg', 'Tablet', '10 tablets', 'strip', 'otc', 'otc-medicine', 'square', 12, 11, 0, 0, 1, 'strip', 250, []],
    ['ace-plus', 'Ace Plus', 'এইস প্লাস', 'Paracetamol + Caffeine', '500 mg + 65 mg', 'Tablet', '10 tablets', 'strip', 'otc', 'otc-medicine', 'square', 25, 23, 0, 0, 1, 'strip', 180, []],
    ['fexo120', 'Fexo 120', 'ফেক্সো ১২০', 'Fexofenadine Hydrochloride', '120 mg', 'Tablet', '10 tablets', 'strip', 'otc', 'otc-medicine', 'square', 90, 85, 0, 1, 0, 'strip', 150, []],
    ['alatrol', 'Alatrol', 'এলাট্রল', 'Cetirizine Hydrochloride', '10 mg', 'Tablet', '10 tablets', 'strip', 'otc', 'otc-medicine', 'square', 30, 30, 0, 0, 0, 'strip', 8, ['low' => true]],
    ['seclo20', 'Seclo 20', 'সেকলো ২০', 'Omeprazole', '20 mg', 'Capsule', '14 capsules', 'strip', 'medicine', 'prescription-medicine', 'square', 70, 63, 1, 1, 0, 'capsule', 200, []],
    ['sergel20', 'Sergel 20', 'সার্জেল ২০', 'Esomeprazole', '20 mg', 'Capsule', '10 capsules', 'strip', 'medicine', 'prescription-medicine', 'healthcare', 70, 67, 1, 0, 0, 'capsule', 160, []],
    ['maxpro20', 'Maxpro 20', 'ম্যাক্সপ্রো ২০', 'Esomeprazole', '20 mg', 'Tablet', '10 tablets', 'strip', 'medicine', 'prescription-medicine', 'renata', 70, 70, 1, 0, 2, 'strip', 120, []],
    ['losectil20', 'Losectil 20', 'লোসেকটিল ২০', 'Omeprazole', '20 mg', 'Capsule', '10 capsules', 'strip', 'medicine', 'prescription-medicine', 'skf', 50, 47, 1, 0, 2, 'capsule', 90, []],
    ['monas10', 'Monas 10', 'মোনাস ১০', 'Montelukast', '10 mg', 'Tablet', '10 tablets', 'strip', 'medicine', 'prescription-medicine', 'acme', 160, 152, 1, 0, 0, 'strip', 100, []],
    ['azithrocin', 'Azithrocin 500', 'এজিথ্রোসিন ৫০০', 'Azithromycin', '500 mg', 'Tablet', '3 tablets', 'strip', 'medicine', 'prescription-medicine', 'beximco', 105, 105, 1, 0, 0, 'strip', 80, ['near_expiry' => true]],
    ['bizoran', 'Bislol 2.5', 'বিসলল ২.৫', 'Bisoprolol Fumarate', '2.5 mg', 'Tablet', '10 tablets', 'strip', 'medicine', 'prescription-medicine', 'incepta', 60, 57, 1, 0, 0, 'strip', 110, []],
    ['comet500', 'Comet 500', 'কমেট ৫০০', 'Metformin Hydrochloride', '500 mg', 'Tablet', '10 tablets', 'strip', 'medicine', 'diabetic-care', 'square', 40, 38, 1, 0, 0, 'strip', 220, []],
    ['orsaline', 'Orsaline-N', 'ওরস্যালাইন-এন', 'Oral Rehydration Salts', '25 g', 'Powder', '1 sachet', 'sachet', 'otc', 'otc-medicine', 'smc', 6, 6, 0, 1, 0, 'sachet', 1000, ['variants' => [['1 Sachet', '১টি প্যাকেট', 1, 6, 6, 800], ['Box of 20 sachets', '২০ প্যাকেটের বক্স', 20, 120, 110, 40]]]],
    ['savlon', 'Savlon Antiseptic Liquid', 'স্যাভলন অ্যান্টিসেপটিক লিকুইড', 'Chlorhexidine + Cetrimide', null, 'Solution', null, 'bottle', 'otc', 'first-aid', 'aci', 120, 115, 0, 0, 0, 'bottle', 120, ['variants' => [['112 ml', '১১২ মিলি', 1, 75, 72, 80], ['1 litre', '১ লিটার', 1, 420, 399, 30]]]],
    ['bandage', 'Elastic Crepe Bandage 4 inch', 'ইলাস্টিক ক্রেপ ব্যান্ডেজ ৪ ইঞ্চি', null, null, null, '1 roll', 'pcs', 'general', 'first-aid', 'aci', 95, 85, 0, 0, 1, 'pack', 70, []],
    ['accuchek', 'Accu-Chek Active Glucometer', 'একু-চেক অ্যাক্টিভ গ্লুকোমিটার', null, null, null, 'Meter + 10 strips', 'box', 'device', 'medical-devices', 'roche', 1650, 1450, 0, 1, 0, 'device', 25, ['weight' => 350]],
    ['accustrips', 'Accu-Chek Active Test Strips', 'একু-চেক অ্যাক্টিভ টেস্ট স্ট্রিপ', null, null, null, '50 strips', 'box', 'device', 'diabetic-care', 'roche', 1450, 1390, 0, 0, 0, 'box', 40, []],
    ['bpmonitor', 'Omron HEM-7120 BP Monitor', 'ওমরন HEM-7120 প্রেশার মাপার যন্ত্র', null, null, null, '1 unit', 'pcs', 'device', 'medical-devices', 'omron', 4500, 3950, 0, 1, 1, 'device', 15, ['weight' => 600]],
    ['thermo', 'Omron Digital Thermometer MC-246', 'ওমরন ডিজিটাল থার্মোমিটার', null, null, null, '1 unit', 'pcs', 'device', 'medical-devices', 'omron', 450, 399, 0, 0, 0, 'device', 60, []],
    ['mask', 'Surgical Face Mask (3 ply)', 'সার্জিক্যাল ফেস মাস্ক (৩ স্তর)', null, null, null, '50 pcs', 'box', 'general', 'healthcare', 'aci', 250, 199, 0, 0, 2, 'box', 100, []],
    ['cerelac', 'Nestlé Cerelac Wheat & Milk', 'নেসলে সেরেল্যাক গম ও দুধ', null, null, null, '350 g', 'pack', 'general', 'baby-mom-care', 'nestle', 620, 595, 0, 1, 0, 'box', 60, ['weight' => 400]],
    ['jbaby-lotion', 'Johnson\'s Baby Lotion', 'জনসন্স বেবি লোশন', null, null, null, null, 'bottle', 'general', 'baby-mom-care', 'johnson', 380, 380, 0, 0, 0, 'bottle', 60, ['variants' => [['100 ml', '১০০ মিলি', 1, 220, 209, 40], ['200 ml', '২০০ মিলি', 1, 380, 355, 25]]]],
    ['jbaby-shampoo', 'Johnson\'s Baby Shampoo', 'জনসন্স বেবি শ্যাম্পু', null, null, null, '200 ml', 'bottle', 'general', 'baby-mom-care', 'johnson', 420, 399, 0, 0, 1, 'bottle', 45, []],
    ['diaper', 'Baby Diaper Pant (Medium)', 'বেবি ডায়াপার প্যান্ট (মিডিয়াম)', null, null, null, '40 pcs', 'pack', 'general', 'baby-mom-care', 'pg', 1350, 1199, 0, 0, 0, 'pack', 30, ['weight' => 1300]],
    ['filwel', 'Filwel Gold', 'ফিলওয়েল গোল্ড', 'Multivitamin & Multimineral', null, 'Tablet', '30 tablets', 'box', 'otc', 'nutrition-supplements', 'square', 450, 420, 0, 1, 0, 'box', 70, []],
    ['ceevit', 'Ceevit 250', 'সিভিট ২৫০', 'Vitamin C (Ascorbic Acid)', '250 mg', 'Chewable Tablet', '10 tablets', 'strip', 'otc', 'nutrition-supplements', 'square', 15, 15, 0, 0, 2, 'strip', 300, []],
    ['calbo-d', 'Calbo-D', 'ক্যালবো-ডি', 'Calcium Carbonate + Vitamin D3', '500 mg + 200 IU', 'Tablet', '15 tablets', 'strip', 'otc', 'nutrition-supplements', 'square', 165, 158, 0, 0, 0, 'strip', 130, []],
    ['horlicks', 'Horlicks Health Drink', 'হরলিক্স হেলথ ড্রিংক', null, null, null, '500 g', 'jar', 'general', 'nutrition-supplements', 'unilever', 650, 620, 0, 0, 0, 'bottle', 50, ['weight' => 600]],
    ['sensodyne', 'Sensodyne Fresh Mint Toothpaste', 'সেনসোডাইন ফ্রেশ মিন্ট টুথপেস্ট', null, null, null, '100 g', 'tube', 'general', 'oral-care', 'haleon', 380, 360, 0, 0, 0, 'tube', 80, []],
    ['dettol-soap', 'Dettol Original Soap', 'ডেটল অরিজিনাল সাবান', null, null, null, '125 g', 'pcs', 'general', 'personal-care', 'reckitt', 85, 80, 0, 0, 0, 'pack', 200, []],
    ['dettol-handwash', 'Dettol Hand Wash Refill', 'ডেটল হ্যান্ড ওয়াশ রিফিল', null, null, null, '170 ml', 'pack', 'general', 'home-care', 'reckitt', 115, 105, 0, 0, 2, 'sachet', 150, []],
    ['whisper', 'Whisper Ultra Clean Sanitary Pad', 'হুইস্পার আল্ট্রা ক্লিন স্যানিটারি প্যাড', null, null, null, '8 pads', 'pack', 'general', 'feminine-care', 'pg', 220, 210, 0, 0, 0, 'pack', 90, []],
    ['neutrogena', 'Neutrogena Ultra Sheer Sunscreen SPF 50+', 'নিউট্রোজেনা আল্ট্রা শিয়ার সানস্ক্রিন SPF 50+', null, null, null, '88 ml', 'tube', 'general', 'skin-care', 'johnson', 1450, 1290, 0, 1, 0, 'tube', 35, []],
    ['himalaya-fw', 'Himalaya Purifying Neem Face Wash', 'হিমালয়া পিউরিফাইং নিম ফেস ওয়াশ', null, null, null, '100 ml', 'tube', 'general', 'skin-care', 'himalaya', 320, 295, 0, 0, 1, 'tube', 75, []],
    ['durex', 'Durex Extra Thin Condoms', 'ডিউরেক্স এক্সট্রা থিন কনডম', null, null, null, '3 pcs', 'pack', 'general', 'sexual-wellness', 'reckitt', 150, 140, 0, 0, 0, 'box', 100, []],
    ['tulsi', 'Himalaya Tulasi Tablets', 'হিমালয়া তুলসি ট্যাবলেট', 'Holy Basil (Tulsi) extract', '250 mg', 'Tablet', '60 tablets', 'bottle', 'otc', 'herbal-unani', 'himalaya', 420, 399, 0, 0, 0, 'bottle', 40, []],
];
$descriptions = [
    'Paracetamol' => ['Pain and fever relief.', 'Adults: 1-2 tablets every 4-6 hours. Do not take more than 8 tablets in 24 hours.', 'Fever, headache, toothache, period pain, muscle and joint pain.', 'Rarely skin rash. Overdose can damage the liver.'],
    'Omeprazole'  => ['Reduces stomach acid.', '1 capsule daily before breakfast, or as advised by your doctor.', 'Gastric and duodenal ulcer, acid reflux (GERD), heartburn.', 'Headache, diarrhoea, stomach pain (usually mild).'],
    'Esomeprazole'=> ['Proton pump inhibitor for acidity.', '1 dose daily before a meal, as prescribed.', 'Acid reflux, ulcers, H. pylori treatment (with antibiotics).', 'Headache, nausea, wind.'],
];

/* ============================================================== create */
$made = ['brands' => [], 'vendors' => [], 'users' => [], 'products' => [], 'flash_sales' => [], 'banners' => [], 'coupons' => []];
$db->beginTransaction();
try {
    // brands (reuse an existing brand with the same slug)
    $brandId = [];
    foreach ($brands as $k => [$name, $bn, $color, $manu]) {
        $slug = Helpers::slugify($name);
        $id = $db->fetchColumn('SELECT id FROM brands WHERE slug = ?', [$slug]);
        if (!$id) {
            $id = $db->insert('brands', ['name' => $name, 'name_bn' => $bn, 'slug' => $slug, 'is_manufacturer' => $manu, 'country' => $manu ? 'Bangladesh' : null, 'is_featured' => $manu, 'is_active' => 1]);
            $made['brands'][] = (int) $id;
        }
        $brandId[$k] = (int) $id;
    }

    // demo vendors (approved) + their login users
    $vendorRole = $db->fetchColumn("SELECT id FROM roles WHERE slug = 'vendor'");
    $vendorDefs = [
        1 => ['Demo Pharmacy Dhanmondi', 'ডেমো ফার্মেসি ধানমন্ডি', 'demo-pharmacy-dhanmondi', '01700000101', 1365, 'House 12, Road 5, Dhanmondi', 'pharmacy', null],
        2 => ['Demo Health Store Chattogram', 'ডেমো হেলথ স্টোর চট্টগ্রাম', 'demo-health-store-ctg', '01700000102', 108, 'GEC Circle, Chattogram', 'pharmacy', 10.00],
    ];
    $vendorId = [0 => null];
    foreach ($vendorDefs as $k => [$shop, $shopBn, $slug, $phone, $loc, $addr, $type, $rate]) {
        if ($db->fetchColumn('SELECT 1 FROM users WHERE phone = ?', [$phone]) || $db->fetchColumn('SELECT 1 FROM vendors WHERE slug = ?', [$slug])) {
            throw new RuntimeException("Phone {$phone} or vendor {$slug} already exists - remove it first.");
        }
        $uid = $db->insert('users', ['role_id' => $vendorRole ?: null, 'user_type' => 'vendor', 'name' => $shop . ' (owner)', 'phone' => $phone,
            'password_hash' => password_hash('demo1234', PASSWORD_DEFAULT), 'status' => 'active']);
        $vid = $db->insert('vendors', ['user_id' => $uid, 'shop_name' => $shop, 'shop_name_bn' => $shopBn, 'slug' => $slug, 'vendor_type' => $type,
            'contact_person' => 'Demo owner', 'phone' => $phone, 'address' => $addr, 'location_id' => $loc, 'trade_license_no' => 'DEMO-TL-' . $k,
            'drug_license_no' => 'DEMO-DL-' . $k, 'drug_license_expiry' => date('Y-m-d', strtotime('+2 years')), 'nid_no' => '1990' . str_repeat((string) $k, 6),
            'mobile_banking_provider' => 'bkash', 'mobile_banking_no' => $phone, 'commission_rate' => $rate, 'status' => 'approved', 'approved_at' => date('Y-m-d H:i:s'),
            'description' => 'Demo vendor created by database/demo.php']);
        $db->insert('vendor_wallets', ['vendor_id' => $vid]);
        $vendorId[$k] = $vid;
        $made['vendors'][] = (int) $vid;
    }

    // products
    $pid = [];
    foreach ($products as $i => [$key, $en, $bn, $generic, $strength, $form, $pack, $unit, $type, $catSlug, $brand, $mrp, $sale, $rx, $featured, $vendor, $shape, $stock, $extra]) {
        $color = $brands[$brand][2];
        $slug = Product::uniqueSlug(Product::slugBase($en, $strength));
        $d = $descriptions[explode(' + ', (string) $generic)[0]] ?? null;
        $id = $db->insert('products', [
            'vendor_id' => $vendorId[$vendor], 'category_id' => $cat[$catSlug] ?? null, 'brand_id' => $brandId[$brand], 'product_type' => $type,
            'name_en' => $en, 'name_bn' => $bn, 'slug' => $slug, 'sku' => 'DEMO-' . strtoupper($key), 'generic_name' => $generic, 'strength' => $strength,
            'dosage_form' => $form, 'pack_size' => $pack, 'unit' => $unit,
            'short_description' => $d[0] ?? ($en . ' — genuine product from ' . $brands[$brand][0] . '.'),
            'description' => ($d[0] ?? 'Genuine ' . $en . ' from ' . $brands[$brand][0] . '.') . "\n\nDemo product: details are for illustration only.",
            'usage_instructions' => $d[1] ?? ($type === 'device' ? 'Read the instruction leaflet before first use.' : null),
            'indications' => $d[2] ?? null, 'side_effects' => $d[3] ?? null,
            'mrp' => $mrp, 'price' => $sale, 'cost_price' => round($sale * 0.82, 2), 'requires_prescription' => $rx,
            'has_variants' => !empty($extra['variants']) ? 1 : 0, 'track_stock' => 1, 'low_stock_threshold' => 10,
            'weight_grams' => $extra['weight'] ?? ($shape === 'strip' || $shape === 'capsule' ? 20 : 150),
            'status' => 'active', 'is_featured' => $featured, 'sold_count' => random_int(5, 180), 'views' => random_int(50, 900),
            'meta_title' => $en . ($strength ? ' ' . $strength : '') . ' — buy online', 'meta_description' => 'Order ' . $en . ' online with home delivery across Bangladesh.',
        ]);
        $sub = trim(($strength ?? '') . ($pack ? ' · ' . $pack : ''), ' ·') ?: $brands[$brand][0];
        $db->insert('product_images', ['product_id' => $id, 'path' => $picture('p-' . $key, $en, $sub, $brands[$brand][0], $color, $shape), 'alt' => $en, 'is_primary' => 1, 'sort_order' => 0]);
        $pid[$key] = (int) $id;
        $made['products'][] = (int) $id;
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    foreach (glob($dir . '/*') ?: [] as $f) {
        @unlink($f);
    }
    fwrite(STDERR, 'Demo install failed: ' . $e->getMessage() . "\n");
    exit(1);
}

// save the manifest now so --remove works even if a later step fails
$save = static function () use ($db, &$made): void {
    $json = json_encode($made);
    if ($db->fetchColumn("SELECT 1 FROM settings WHERE `key` = 'demo_data'")) {
        $db->update('settings', ['value' => $json], '`key` = :k', ['k' => 'demo_data']);
    } else {
        $db->insert('settings', ['group' => 'system', 'key' => 'demo_data', 'value' => $json, 'type' => 'json', 'label' => 'Records created by database/demo.php']);
    }
};
$save();

// variants + stock (through Inventory so batches, movements and caches are right)
foreach ($products as [$key, $en, , , , , , , $type, , , , , , , , , $stock, $extra]) {
    $id = $pid[$key];
    $needsExpiry = $type === 'medicine';
    $exp = static fn(int $months) => date('Y-m-d', strtotime("+{$months} months"));
    $cost = (float) $db->fetchColumn('SELECT cost_price FROM products WHERE id = ?', [$id]);
    if (!empty($extra['variants'])) {
        foreach ($extra['variants'] as $vi => [$vEn, $vBn, $mult, $vMrp, $vPrice, $vStock]) {
            $vid = $db->insert('product_variants', ['product_id' => $id, 'name_en' => $vEn, 'name_bn' => $vBn, 'sku' => 'DEMO-' . strtoupper($key) . '-' . ($vi + 1),
                'unit_multiplier' => $mult, 'mrp' => $vMrp, 'price' => $vPrice, 'cost_price' => round($vPrice * 0.82, 2), 'is_default' => $vi === 0 ? 1 : 0, 'is_active' => 1]);
            Inventory::receive(['product_id' => $id, 'variant_id' => $vid, 'batch_no' => 'DEMO-' . strtoupper(substr($key, 0, 6)) . '-V' . ($vi + 1),
                'quantity' => $vStock, 'expiry_date' => $exp(20), 'manufacture_date' => date('Y-m-d', strtotime('-2 months')),
                'unit_cost' => round($vPrice * 0.82, 2), 'type' => 'stock_in', 'note' => 'Demo opening stock']);
        }
        continue;
    }
    if (!empty($extra['near_expiry'])) { // one batch close to expiry for the inventory alerts
        Inventory::receive(['product_id' => $id, 'batch_no' => 'DEMO-' . strtoupper(substr($key, 0, 6)) . '-OLD', 'quantity' => 12, 'expiry_date' => $exp(2),
            'manufacture_date' => date('Y-m-d', strtotime('-20 months')), 'unit_cost' => $cost, 'type' => 'stock_in', 'note' => 'Demo: near expiry']);
    }
    Inventory::receive(['product_id' => $id, 'batch_no' => 'DEMO-' . strtoupper(substr($key, 0, 6)) . '-A', 'quantity' => $stock,
        'expiry_date' => $needsExpiry || $type === 'otc' ? $exp(18) : null, 'manufacture_date' => date('Y-m-d', strtotime('-1 month')),
        'unit_cost' => $cost, 'type' => 'stock_in', 'note' => 'Demo opening stock']);
}

// flash sale (7 days)
$fs = $db->insert('flash_sales', ['title_en' => 'Weekly Health Deals', 'title_bn' => 'সাপ্তাহিক হেলথ ডিল', 'starts_at' => date('Y-m-d H:i:s', time() - 3600),
    'ends_at' => date('Y-m-d 23:59:59', strtotime('+7 days')), 'is_active' => 1]);
$made['flash_sales'][] = (int) $fs;
foreach ([['accuchek', 1299, 30, 11], ['filwel', 369, 60, 27], ['neutrogena', 1099, 25, 9], ['cerelac', 549, 40, 14], ['fexo120', 75, 100, 46], ['bpmonitor', 3599, 10, 3]] as [$k, $price, $limit, $sold]) {
    $db->insert('flash_sale_items', ['flash_sale_id' => $fs, 'product_id' => $pid[$k], 'sale_price' => $price, 'quantity_limit' => $limit, 'sold_qty' => $sold]);
}

// home banners (the CLI does not know the web base path: use config app.base_path, else the folder name under htdocs)
$webBase = is_string(config('app.base_path')) ? rtrim((string) config('app.base_path'), '/') : '/' . basename(ROOT_PATH);
foreach ([
    ['b-genuine', 'Genuine medicine, at your door', 'Order from 40+ trusted brands with home delivery', '#0e7673', '#13a39e', '+', '/products'],
    ['b-deals', 'Weekly Health Deals', 'Up to 25% off on devices, vitamins and baby care', '#f0663a', '#f59e0b', '%', '/products?sort=discount'],
    ['b-diabetes', 'Diabetic care made easy', 'Glucometers, test strips and medicines in one place', '#1d4e89', '#0e7673', '♥', '/category/diabetic-care'],
] as $i => [$file, $title, $sub, $c1, $c2, $icon, $link]) {
    $made['banners'][] = (int) $db->insert('banners', ['title' => $title, 'subtitle' => $sub, 'image' => $banner($file, $title, $sub, $c1, $c2, $icon),
        'link_url' => $webBase . $link, 'position' => 'home_slider', 'lang' => 'all', 'sort_order' => $i, 'is_active' => 1]);
}

// coupons
foreach ([['DEMO10', '10% off (max ৳100) on orders over ৳500', 'percent', 10, 100, 500], ['DEMOSHIP', 'Free delivery on orders over ৳300', 'free_shipping', 0, null, 300]] as [$code, $desc, $type, $val, $max, $min]) {
    if (!$db->fetchColumn('SELECT 1 FROM coupons WHERE code = ?', [$code])) {
        $made['coupons'][] = (int) $db->insert('coupons', ['code' => $code, 'description' => $desc, 'type' => $type, 'value' => $val, 'max_discount' => $max,
            'min_order_amount' => $min, 'usage_limit_per_user' => 5, 'is_active' => 1]);
    }
}

// demo customer + reviews
if (!$db->fetchColumn("SELECT 1 FROM users WHERE phone = '01700000099'")) {
    $cid = User::createCustomer('Demo Customer', '01700000099', null, 'demo1234');
    $made['users'][] = $cid;
    $db->insert('addresses', ['user_id' => $cid, 'label' => 'home', 'recipient_name' => 'Demo Customer', 'phone' => '01700000099',
        'division_id' => 6, 'district_id' => 147, 'upazila_id' => 1365, 'address_line' => 'House 7, Road 2, Dhanmondi', 'is_default' => 1]);
    foreach ([['napa500', 5, 'Works fast', 'Fever came down within an hour. Good packaging.'], ['accuchek', 5, 'Accurate meter', 'Easy to use, readings match my lab report.'],
              ['accuchek', 4, null, 'Good device, delivery took two days.'], ['filwel', 4, 'Good value', 'Original product, well before expiry.'],
              ['cerelac', 5, null, 'My baby loves it. Fresh stock.'], ['seclo20', 5, null, 'Genuine Square product, pharmacist called to check my prescription.']] as $j => [$k, $rating, $title, $comment]) {
        $uid = $j === 0 || $j === 4 ? $cid : $cid;
        if ($db->fetchColumn('SELECT 1 FROM reviews WHERE user_id = ? AND product_id = ?', [$uid, $pid[$k]])) {
            continue;
        }
        $db->insert('reviews', ['product_id' => $pid[$k], 'user_id' => $uid, 'rating' => $rating, 'title' => $title, 'comment' => $comment, 'status' => 'approved']);
        Review::recalculate($pid[$k]);
    }
}
$save();
Category::clearCache();

echo 'Demo data added: ' . count($made['products']) . ' products, ' . count($made['brands']) . ' brands, ' . count($made['vendors']) . " vendors,\n"
    . "1 flash sale, " . count($made['banners']) . ' banners, ' . count($made['coupons']) . " coupons (DEMO10, DEMOSHIP), 1 demo customer.\n"
    . "Logins: customer 01700000099 / demo1234 · vendors 01700000101 and 01700000102 / demo1234\n"
    . "Remove later with: php database/demo.php --remove\n";
