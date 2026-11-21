<?php
/**
 * Vendor types (admin-editable): Pharmacy, Medical equipment, Beauty & personal care, Grocery, Baby care,
 * Herbal / Homeopathy, Supplements, Other. Each type decides
 *  - which documents the seller uploads at sign-up (required_docs / optional_docs, keys from VendorType::DOCS)
 *  - needs_drug_license (DGDA licence no. + expiry), can_sell_rx (may list prescription medicine)
 *  - commission_rate (default for all sellers of this type), allowed_categories (top categories they may sell in; NULL = all)
 * vendors.vendor_type_id links a seller to its type; the old enum vendors.vendor_type becomes a VARCHAR holding the slug.
 * Safe to re-run.
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    $has = static fn(string $t, string $c): bool => (bool) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}'"
    )->fetchColumn();

    $pdo->exec("CREATE TABLE IF NOT EXISTS vendor_types (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(40) NOT NULL,
        name_en VARCHAR(100) NOT NULL,
        name_bn VARCHAR(100) NULL,
        description_en VARCHAR(255) NULL,
        description_bn VARCHAR(255) NULL,
        icon VARCHAR(50) NULL,
        required_docs VARCHAR(255) NULL COMMENT 'JSON list of document keys',
        optional_docs VARCHAR(255) NULL COMMENT 'JSON list of document keys',
        needs_drug_license TINYINT(1) NOT NULL DEFAULT 0,
        can_sell_rx TINYINT(1) NOT NULL DEFAULT 0,
        commission_rate DECIMAL(5,2) NULL,
        allowed_categories VARCHAR(500) NULL COMMENT 'JSON list of category ids (children included); NULL = all',
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        UNIQUE KEY uq_vendor_types_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $cat = static function (array $slugs) use ($pdo): ?string {
        if (!$slugs) {
            return null;
        }
        $in = implode(',', array_fill(0, count($slugs), '?'));
        $st = $pdo->prepare("SELECT id FROM categories WHERE slug IN ({$in}) ORDER BY id");
        $st->execute($slugs);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        return $ids ? json_encode($ids) : null;
    };

    // slug, en, bn, desc en, desc bn, icon, required, optional, drug licence, rx, categories (slugs; [] = all), sort
    $types = [
        ['pharmacy', 'Pharmacy', 'ফার্মেসি', 'Medicine shops and distributors (DGDA licence)', 'ওষুধের দোকান ও ডিস্ট্রিবিউটর (ড্রাগ লাইসেন্স)', 'bi-capsule',
            ['trade_license', 'nid', 'drug_license'], ['tin'], 1, 1, [], 10],
        ['medical_equipment', 'Medical equipment', 'মেডিকেল যন্ত্রপাতি', 'BP machines, glucometers, wheelchairs, first aid', 'প্রেশার মেশিন, গ্লুকোমিটার, হুইলচেয়ার, ফার্স্ট এইড', 'bi-heart-pulse',
            ['trade_license', 'nid'], ['tin'], 0, 0, ['healthcare'], 20],
        ['beauty', 'Beauty & personal care', 'বিউটি ও পার্সোনাল কেয়ার', 'Face wash, skin care, cosmetics, hair care', 'ফেসওয়াশ, স্কিন কেয়ার, কসমেটিকস, হেয়ার কেয়ার', 'bi-stars',
            ['trade_license', 'nid'], ['bsti', 'tin'], 0, 0, ['beauty', 'personal-care'], 30],
        ['grocery', 'Grocery', 'গ্রোসারি', 'Daily needs, home care, baby food, nutrition', 'নিত্যপ্রয়োজনীয়, হোম কেয়ার, শিশুখাদ্য, পুষ্টি', 'bi-basket',
            ['trade_license', 'nid'], ['bsti', 'tin'], 0, 0, ['home-care', 'personal-care', 'baby-mom-care', 'nutrition-supplements', 'pet-care'], 40],
        ['baby_care', 'Baby & mom care', 'বেবি ও মম কেয়ার', 'Diapers, baby care, mother care', 'ডায়াপার, শিশুর যত্ন, মায়ের যত্ন', 'bi-emoji-smile',
            ['trade_license', 'nid'], ['bsti'], 0, 0, ['baby-mom-care', 'personal-care'], 50],
        ['herbal', 'Herbal / Homeopathy', 'হারবাল / হোমিওপ্যাথি', 'Unani, Ayurvedic and homeopathic medicine', 'ইউনানি, আয়ুর্বেদিক ও হোমিও ওষুধ', 'bi-flower1',
            ['trade_license', 'nid', 'drug_license'], ['tin'], 1, 0, ['herbal-unani', 'homeopathy', 'nutrition-supplements'], 60],
        ['supplements', 'Supplements', 'সাপ্লিমেন্ট', 'Vitamins, protein, nutrition', 'ভিটামিন, প্রোটিন, পুষ্টি', 'bi-capsule-pill',
            ['trade_license', 'nid'], ['bsti', 'tin'], 0, 0, ['nutrition-supplements', 'sexual-wellness'], 70],
        ['other', 'Other', 'অন্যান্য', 'Any other health or lifestyle products', 'অন্যান্য স্বাস্থ্য ও লাইফস্টাইল পণ্য', 'bi-shop',
            ['nid'], ['trade_license', 'tin'], 0, 0, [], 90],
    ];
    $ins = $pdo->prepare('INSERT IGNORE INTO vendor_types (slug, name_en, name_bn, description_en, description_bn, icon, required_docs, optional_docs,
                          needs_drug_license, can_sell_rx, allowed_categories, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($types as [$slug, $en, $bn, $den, $dbn, $icon, $req, $opt, $drug, $rx, $cats, $sort]) {
        $ins->execute([$slug, $en, $bn, $den, $dbn, $icon, json_encode($req), json_encode($opt), $drug, $rx, $cat($cats), $sort]);
    }

    if (!$has('vendors', 'vendor_type_id')) {
        $pdo->exec("ALTER TABLE vendors MODIFY vendor_type VARCHAR(40) NOT NULL DEFAULT 'pharmacy',
                    ADD COLUMN vendor_type_id INT UNSIGNED NULL AFTER vendor_type,
                    ADD KEY idx_vendors_type (vendor_type_id),
                    ADD CONSTRAINT fk_vendors_type FOREIGN KEY (vendor_type_id) REFERENCES vendor_types (id) ON DELETE SET NULL");
        // old fixed types -> new types: distributors sell medicine (pharmacy), brands / retailers -> other
        $pdo->exec("UPDATE vendors SET vendor_type = 'pharmacy' WHERE vendor_type = 'distributor'");
        $pdo->exec("UPDATE vendors SET vendor_type = 'other' WHERE vendor_type IN ('brand','retailer')");
        $pdo->exec('UPDATE vendors v JOIN vendor_types t ON t.slug = v.vendor_type SET v.vendor_type_id = t.id');
    }
};
