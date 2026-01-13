<?php
$topColors = $topColors ?? [];
?>
<div class="row g-2">
  <?php foreach ($topColors as $c): ?>
    <?php
      $thumb = (string)($c['thumb_path'] ?? '');
      $big = (string)($c['image_path'] ?? '') ?: $thumb;
      $code = (string)($c['color_code'] ?? '');
      if ($code === '') $code = '—';
      $href = \App\Core\Url::to('/stock') . ($code !== '—' ? ('?color=' . rawurlencode($code)) : '');
    ?>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="border rounded-4 p-2 h-100" style="border-color:#D9E3E6;background:#fff">
        <div class="text-center">
          <?php if ($thumb): ?>
            <a href="#"
               data-bs-toggle="modal" data-bs-target="#appLightbox"
               data-lightbox-src="<?= htmlspecialchars($big) ?>"
               data-lightbox-fallback="<?= htmlspecialchars($thumb) ?>"
               data-lightbox-title="<?= htmlspecialchars($code) ?>"
               style="display:inline-block;cursor:zoom-in">
              <img src="<?= htmlspecialchars($thumb) ?>" style="width:128px;height:128px;object-fit:cover;border-radius:18px;border:1px solid #D9E3E6;">
            </a>
          <?php endif; ?>
        </div>

        <div class="mt-2 text-center">
          <a href="<?= htmlspecialchars($href) ?>" class="text-decoration-none">
            <div class="fw-semibold" style="font-size:1.15rem;line-height:1.1;color:#111"><?= htmlspecialchars($code) ?></div>
            <div class="text-muted" style="font-weight:600"><?= htmlspecialchars((string)($c['color_name'] ?? '')) ?></div>
          </a>
          <div class="text-muted small mt-1">Suprafața totală: <span class="fw-semibold"><?= number_format((float)($c['total_m2'] ?? 0), 2, '.', '') ?></span> mp</div>
        </div>

        <div class="mt-2 d-flex flex-wrap gap-1 justify-content-center">
          <?php foreach (($c['by_thickness'] ?? []) as $t => $m2): ?>
            <span class="badge app-badge"><?= (int)$t ?>mm: <?= number_format((float)$m2, 2, '.', '') ?> mp</span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$topColors): ?>
    <div class="col-12 text-muted">Nimic găsit.</div>
  <?php endif; ?>
</div>

