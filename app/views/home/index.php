<?php
/** Storefront home (foundation version). Vars: $categories, $locCounts, $divisions, $commission */
$bn = lang() === 'bn';
$n = static fn($v) => $bn ? bn_digits($v) : (string) $v;
?>
<section class="hero rounded-4 p-4 p-md-5 mb-4 text-white">
    <h1 class="h2 fw-bold mb-2"><?= e(__('app_name')) ?></h1>
    <p class="mb-3 opacity-75"><?= e(__('tagline')) ?></p>
    <a href="#" class="btn btn-warning"><i class="bi bi-file-earmark-medical"></i> <?= e(__('upload_prescription')) ?></a>
</section>

<h2 class="h5 mb-3"><?= e(__('shop_by_category')) ?></h2>
<div class="row g-2 g-md-3 mb-4">
    <?php foreach ($categories as $c): ?>
        <div class="col-4 col-md-3 col-lg-2">
            <a href="#" class="card category-card h-100 text-center text-decoration-none">
                <div class="card-body p-2 p-md-3">
                    <i class="bi <?= e($c['icon'] ?: 'bi-grid') ?> fs-2 brand-text"></i>
                    <div class="small mt-1 text-dark"><?= e(Category::label($c)) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-check2-circle text-success"></i> <?= e(__('system_status')) ?></h2>
                <ul class="mb-0 small">
                    <li><?= e(__('locations_loaded')) ?>:
                        <?= e(__('division')) ?> <?= e($n($locCounts['division'] ?? 0)) ?>,
                        <?= e(__('district')) ?> <?= e($n($locCounts['district'] ?? 0)) ?>,
                        <?= e(__('upazila')) ?> <?= e($n($locCounts['upazila'] ?? 0)) ?></li>
                    <li><?= e(__('default_commission')) ?>: <?= e($n(number_format($commission, 2))) ?>%</li>
                    <li><?= e(money(1234567.5)) ?> (money format test)</li>
                    <li><?= e(date('d M Y, h:i A')) ?> (Asia/Dhaka)</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-geo-alt"></i> <?= e(__('try_location')) ?></h2>
                <div class="row g-2" id="locationPicker" data-api="<?= e(url('api/locations')) ?>">
                    <div class="col-12 col-sm-4">
                        <select class="form-select form-select-sm" data-level="division">
                            <option value=""><?= e(__('division')) ?></option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= (int) $d['id'] ?>"><?= e($bn ? $d['name_bn'] : $d['name_en']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-4"><select class="form-select form-select-sm" data-level="district" disabled><option value=""><?= e(__('district')) ?></option></select></div>
                    <div class="col-12 col-sm-4"><select class="form-select form-select-sm" data-level="upazila" disabled><option value=""><?= e(__('upazila')) ?></option></select></div>
                </div>
            </div>
        </div>
    </div>
</div>
