<?php
/**
 * Step 11 - CMS, support, settings, notifications, messaging
 *  - faqs, blog_posts (health tips), message_logs (SMS / email outbox)
 *  - settings: logo, favicon, English tagline, social links, SMS + email gateway config, notification events
 *  - permissions: settings.backup; modules pages / support / reviews / notifications get their admin URLs
 *  - sample FAQs and two health tips
 * Safe to re-run.
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    $q = static function (string $sql, array $p = []) use ($pdo) {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st;
    };

    $pdo->exec("CREATE TABLE IF NOT EXISTS faqs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(40) NOT NULL DEFAULT 'general',
        question_en VARCHAR(255) NOT NULL,
        question_bn VARCHAR(255) NULL,
        answer_en TEXT NOT NULL,
        answer_bn TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_faqs_cat (category, is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(190) NOT NULL,
        title_en VARCHAR(200) NOT NULL,
        title_bn VARCHAR(200) NULL,
        excerpt_en VARCHAR(500) NULL,
        excerpt_bn VARCHAR(500) NULL,
        content_en MEDIUMTEXT NULL,
        content_bn MEDIUMTEXT NULL,
        image VARCHAR(255) NULL,
        category VARCHAR(40) NOT NULL DEFAULT 'health_tips',
        tags VARCHAR(255) NULL,
        author_id BIGINT UNSIGNED NULL,
        status ENUM('draft','published') NOT NULL DEFAULT 'draft',
        published_at DATETIME NULL,
        views INT UNSIGNED NOT NULL DEFAULT 0,
        meta_title VARCHAR(255) NULL,
        meta_description VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_blog_slug (slug),
        KEY idx_blog_pub (status, published_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS message_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        channel ENUM('sms','email') NOT NULL,
        recipient VARCHAR(190) NOT NULL,
        subject VARCHAR(255) NULL,
        body TEXT NOT NULL,
        event VARCHAR(50) NULL,
        user_id BIGINT UNSIGNED NULL,
        status ENUM('queued','sent','failed','logged') NOT NULL DEFAULT 'queued',
        provider VARCHAR(30) NULL,
        response VARCHAR(1000) NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        sent_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_msg_status (status, created_at),
        KEY idx_msg_channel (channel, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $defaults = [
        ['general', 'site_logo', '', 'string'], ['general', 'site_favicon', '', 'string'],
        ['general', 'site_tagline_en', 'Your trusted online pharmacy & healthcare shop', 'string'],
        ['social', 'instagram_url', '', 'string'], ['social', 'whatsapp_number', '', 'string'],
        ['order', 'review_auto_approve', '1', 'bool'],
        ['sms', 'sms_driver', 'log', 'string'], ['sms', 'sms_api_url', '', 'string'], ['sms', 'sms_api_method', 'GET', 'string'],
        ['sms', 'sms_api_key', '', 'string'], ['sms', 'sms_sender_id', '', 'string'], ['sms', 'sms_success_keyword', '', 'string'],
        ['mail', 'mail_enabled', '0', 'bool'], ['mail', 'mail_driver', 'log', 'string'], ['mail', 'mail_host', '', 'string'],
        ['mail', 'mail_port', '587', 'int'], ['mail', 'mail_username', '', 'string'], ['mail', 'mail_password', '', 'string'],
        ['mail', 'mail_encryption', 'tls', 'string'], ['mail', 'mail_from_address', '', 'string'], ['mail', 'mail_from_name', 'SasthoBazar', 'string'],
        ['notify', 'notify_events', json_encode([
            'order_placed' => ['sms' => 1, 'email' => 1], 'order_confirmed' => ['sms' => 0, 'email' => 1],
            'order_shipped' => ['sms' => 1, 'email' => 1], 'order_delivered' => ['sms' => 1, 'email' => 1],
            'order_cancelled' => ['sms' => 1, 'email' => 1], 'refund_update' => ['sms' => 1, 'email' => 1],
            'prescription' => ['sms' => 1, 'email' => 1], 'ticket_reply' => ['sms' => 0, 'email' => 1],
            'payout_update' => ['sms' => 1, 'email' => 1], 'vendor_status' => ['sms' => 1, 'email' => 1],
        ]), 'json'],
    ];
    foreach ($defaults as [$g, $k, $v, $t]) {
        $q('INSERT IGNORE INTO settings (`group`, `key`, `value`, `type`, `label`, `is_public`) VALUES (?, ?, ?, ?, ?, 0)', [$g, $k, $v, $t, $k]);
    }

    // sample FAQs (only when there are none)
    if (!(int) $q('SELECT COUNT(*) FROM faqs')->fetchColumn()) {
        $faqs = [
            ['order', 'How do I place an order?', 'কীভাবে অর্ডার করব?', 'Search or browse for a product, add it to the cart and check out. You can pay cash on delivery or with bKash / Nagad.', 'পণ্য খুঁজে কার্টে যোগ করুন এবং চেকআউট করুন। ক্যাশ অন ডেলিভারি অথবা বিকাশ / নগদে পরিশোধ করতে পারবেন।'],
            ['order', 'Do I need a prescription?', 'প্রেসক্রিপশন কি লাগবে?', 'Prescription medicines (marked Rx) need a valid prescription. Upload a photo at checkout; our pharmacist checks it before the order is confirmed.', 'প্রেসক্রিপশন ওষুধে (Rx চিহ্নিত) বৈধ প্রেসক্রিপশন লাগবে। চেকআউটে ছবি আপলোড করুন; ফার্মাসিস্ট যাচাই করে অর্ডার নিশ্চিত করবেন।'],
            ['delivery', 'How long does delivery take?', 'ডেলিভারিতে কত সময় লাগে?', 'Inside Dhaka usually 1-2 days, outside Dhaka 2-4 days. You can track every order from My orders.', 'ঢাকার ভিতরে সাধারণত ১-২ দিন, ঢাকার বাইরে ২-৪ দিন। আমার অর্ডার থেকে প্রতিটি অর্ডার ট্র্যাক করতে পারবেন।'],
            ['delivery', 'How much is the delivery charge?', 'ডেলিভারি চার্জ কত?', 'It depends on your area and the parcel weight and is shown at checkout before you order. Larger orders may ship free.', 'এলাকা ও পার্সেলের ওজন অনুযায়ী, যা অর্ডারের আগে চেকআউটে দেখানো হয়। বড় অর্ডারে ফ্রি ডেলিভারি হতে পারে।'],
            ['payment', 'Which payment methods do you accept?', 'কোন কোন পেমেন্ট পদ্ধতি আছে?', 'Cash on delivery, bKash, Nagad and Rocket.', 'ক্যাশ অন ডেলিভারি, বিকাশ, নগদ ও রকেট।'],
            ['returns', 'Can I return a product?', 'পণ্য কি ফেরত দেওয়া যায়?', 'Yes, within the return window shown on your order, if the product is unused and sealed. Request a return from the order page.', 'হ্যাঁ, অর্ডারে দেখানো সময়ের মধ্যে, পণ্য অব্যবহৃত ও সিল করা থাকলে। অর্ডারের পাতা থেকে রিটার্নের অনুরোধ করুন।'],
        ];
        $ins = $pdo->prepare('INSERT INTO faqs (category, question_en, question_bn, answer_en, answer_bn, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($faqs as $i => $f) {
            $ins->execute([$f[0], $f[1], $f[2], $f[3], $f[4], $i]);
        }
    }
    if (!(int) $q('SELECT COUNT(*) FROM blog_posts')->fetchColumn()) {
        $posts = [
            ['stay-hydrated-in-summer', 'Stay hydrated in the summer heat', 'গরমে শরীরে পানির ঘাটতি দূর করুন',
             'Simple ways to avoid dehydration during hot Bangladeshi summers.', 'বাংলাদেশের প্রচণ্ড গরমে পানিশূন্যতা এড়ানোর সহজ উপায়।',
             '<p>Hot and humid days make us lose water and salts quickly. Drink water regularly, even before you feel thirsty.</p><h2>Tips</h2><ul><li>Drink 8-10 glasses of water a day.</li><li>Use oral saline (ORS) after heavy sweating or diarrhoea.</li><li>Eat water-rich fruit such as watermelon and cucumber.</li><li>Avoid long exposure to the midday sun.</li></ul><p>See a doctor if you feel dizzy, confused or stop sweating.</p>',
             '<p>গরম ও আর্দ্র আবহাওয়ায় শরীর থেকে দ্রুত পানি ও লবণ বেরিয়ে যায়। তৃষ্ণা পাওয়ার আগেই নিয়মিত পানি পান করুন।</p><h2>পরামর্শ</h2><ul><li>দিনে ৮-১০ গ্লাস পানি পান করুন।</li><li>বেশি ঘাম বা ডায়রিয়া হলে খাবার স্যালাইন খান।</li><li>তরমুজ, শসার মতো রসালো ফল খান।</li><li>দুপুরের রোদে বেশিক্ষণ থাকবেন না।</li></ul><p>মাথা ঘোরা, বিভ্রান্তি বা ঘাম বন্ধ হয়ে গেলে ডাক্তার দেখান।</p>'],
            ['managing-diabetes-daily', 'Managing diabetes every day', 'প্রতিদিন ডায়াবেটিস নিয়ন্ত্রণ',
             'Small daily habits that keep blood sugar under control.', 'রক্তে শর্করা নিয়ন্ত্রণে রাখার ছোট ছোট দৈনিক অভ্যাস।',
             '<p>Diabetes can be managed well with medicine, food and activity.</p><ul><li>Check your blood sugar as advised by your doctor.</li><li>Take medicine at the same time every day.</li><li>Walk at least 30 minutes a day.</li><li>Choose whole grains and vegetables; limit sweets and soft drinks.</li></ul><p>Never change your dose without talking to your doctor.</p>',
             '<p>ওষুধ, খাবার ও শারীরিক পরিশ্রমের মাধ্যমে ডায়াবেটিস ভালোভাবে নিয়ন্ত্রণ করা যায়।</p><ul><li>ডাক্তারের পরামর্শমতো রক্তের শর্করা পরীক্ষা করুন।</li><li>প্রতিদিন একই সময়ে ওষুধ খান।</li><li>দিনে অন্তত ৩০ মিনিট হাঁটুন।</li><li>লাল চাল-আটা ও সবজি বেছে নিন; মিষ্টি ও কোমল পানীয় কমান।</li></ul><p>ডাক্তারের সঙ্গে কথা না বলে কখনো ডোজ বদলাবেন না।</p>'],
        ];
        $ins = $pdo->prepare("INSERT INTO blog_posts (slug, title_en, title_bn, excerpt_en, excerpt_bn, content_en, content_bn, status, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'published', NOW())");
        foreach ($posts as $p) {
            $ins->execute($p);
        }
    }

    Permission::sync($pdo, false);
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r JOIN permissions p
                 WHERE (r.slug = 'super-admin' AND p.module IN ('pages', 'support', 'reviews', 'notifications', 'settings'))
                    OR (r.slug = 'manager' AND p.module IN ('pages', 'support', 'reviews', 'notifications'))
                    OR (r.slug = 'support-agent' AND (p.module IN ('support', 'reviews') OR p.`key` IN ('refunds.view', 'orders.view')))
                    OR (r.slug = 'content-editor' AND p.`key` IN ('reviews.view', 'reviews.moderate'))");
};
