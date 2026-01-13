<?php
use App\Core\Url;

$finishes = $finishes ?? [];
$stockByFinish = $stockByFinish ?? [];
$totalByFinish = $totalByFinish ?? [];
?>
<div class="row g-3">
  <?php foreach ($finishes as $f): ?>
    <?php
      $id = (int)$f['id'];
      $thumb = (string)($f['thumb_path'] ?? '');
      $bigRaw = (string)($f['image_path'] ?? '') ?: $thumb;
      $big = (str_starts_with($bigRaw, '/uploads/')) ? Url::to($bigRaw) : $bigRaw;
      $code = (string)($f['code'] ?? '—');
      $name = (string)($f['color_name'] ?? '');
      $byT = $stockByFinish[$id] ?? [];
      $tot = (float)($totalByFinish[$id] ?? 0.0);
      $href = Url::to('/stock') . ($id > 0 ? ('?color_id=' . $id) : '');
    ?>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xxl-2">
      <div class="card app-card p-3 h-100">
        <div class="text-center">
          <?php if ($thumb): ?>
            <a href="#"
               data-bs-toggle="modal" data-bs-target="#appLightbox"
               data-lightbox-src="<?= htmlspecialchars($big) ?>"
               data-lightbox-fallback="<?= htmlspecialchars($thumb) ?>"
               data-lightbox-title="<?= htmlspecialchars($code) ?>"
               style="display:inline-block;cursor:zoom-in">
              <img src="<?= htmlspecialchars($thumb) ?>" style="width:170px;height:170px;object-fit:cover;border-radius:18px;border:1px solid #D9E3E6;">
            </a>
          <?php endif; ?>
        </div>

        <div class="mt-2 text-center">
          <a href="<?= htmlspecialchars($href) ?>" class="text-decoration-none">
            <div class="fw-semibold" style="font-size:1.25rem;line-height:1.1;color:#111"><?= htmlspecialchars($code) ?></div>
            <div class="text-muted" style="font-weight:600"><?= htmlspecialchars($name) ?></div>
          </a>
          <div class="text-muted small mt-1">Total disponibil: <span class="fw-semibold"><?= number_format((float)$tot, 2, '.', '') ?></span> mp</div>
        </div>

        <div class="mt-2 d-flex flex-wrap gap-1 justify-content-center">
          <?php if (!$byT): ?>
            <span class="text-muted small">Fără stoc.</span>
          <?php endif; ?>
          <?php foreach ($byT as $t => $m2): ?>
            <span class="badge app-badge"><?= (int)$t ?>mm: <?= number_format((float)$m2, 2, '.', '') ?> mp</span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$finishes): ?>
    <div class="col-12 text-muted">Nimic găsit.</div>
  <?php endif; ?>
</div>

