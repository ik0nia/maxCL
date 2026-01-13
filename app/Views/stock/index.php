<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
$isAdmin = $u && (string)$u['role'] === Auth::ROLE_ADMIN;
$rows = $rows ?? [];
$filterColor = $filterColor ?? null;

// Admin-only: calculează valoarea stocului disponibil (lei)
$totalValueLei = 0.0;
if ($isAdmin) {
  foreach ($rows as $r) {
    $m2 = (float)($r['stock_m2_available'] ?? 0);
    if ($m2 <= 0) continue;
    $ppm = null;
    if (isset($r['sale_price_per_m2']) && $r['sale_price_per_m2'] !== null && $r['sale_price_per_m2'] !== '' && is_numeric($r['sale_price_per_m2'])) {
      $ppm = (float)$r['sale_price_per_m2'];
    } elseif (isset($r['sale_price']) && $r['sale_price'] !== null && $r['sale_price'] !== '' && is_numeric($r['sale_price'])) {
      $stdW = (int)($r['std_width_mm'] ?? 0);
      $stdH = (int)($r['std_height_mm'] ?? 0);
      $area = ($stdW > 0 && $stdH > 0) ? (($stdW * $stdH) / 1000000.0) : 0.0;
      if ($area > 0) $ppm = ((float)$r['sale_price']) / $area;
    }
    if ($ppm !== null && $ppm >= 0 && is_finite($ppm)) {
      $totalValueLei += ($m2 * $ppm);
    }
  }
}

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Stoc</h1>
    <div class="text-muted">Catalog plăci + total buc/mp (disponibil)</div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($canWrite): ?>
      <a href="<?= htmlspecialchars(Url::to('/stock/boards/create')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Placă nouă
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (is_array($filterColor)): ?>
  <div class="card app-card p-3 mb-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <div class="fw-semibold">Filtru culoare (față sau verso)</div>
        <div class="text-muted">
          <?= htmlspecialchars((string)($filterColor['code'] ?? '—')) ?> · <?= htmlspecialchars((string)($filterColor['color_name'] ?? '')) ?>
          <?php if (!empty($filterColor['color_code'])): ?> (<?= htmlspecialchars((string)$filterColor['color_code']) ?>)<?php endif; ?>
        </div>
      </div>
      <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-x-lg me-1"></i> Șterge filtru
      </a>
    </div>
  </div>
<?php endif; ?>

<?php if ($isAdmin): ?>
  <div class="row g-3 mb-3">
    <div class="col-12">
      <div class="card app-card p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="h5 m-0">Valoare stoc (disponibil)</div>
            <div class="text-muted">Calcul: mp disponibili × preț/mp (din prețul setat pe placa standard)</div>
          </div>
          <div class="text-end">
            <div class="text-muted small">Total general</div>
            <div class="fw-semibold" style="font-size:1.35rem;line-height:1.1">
              <?= number_format((float)$totalValueLei, 2, '.', '') ?> lei
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="boardsTable">
    <thead>
      <tr>
        <th style="width:110px">Preview</th>
        <th>Cod</th>
        <th>Denumire</th>
        <th>Brand</th>
        <th>Grosime</th>
        <th>Dim. standard</th>
        <th class="text-end">Stoc (buc)</th>
        <th class="text-end">Stoc (mp)</th>
        <?php if ($isAdmin): ?>
          <th class="text-end">Preț/mp</th>
          <th class="text-end">Valoare (lei)</th>
        <?php endif; ?>
        <th class="text-end" style="width:220px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td>
            <div class="d-flex gap-1">
              <?php
                $faceThumb = (string)$r['face_thumb_path'];
                $backThumb = (string)($r['back_thumb_path'] ?? '');

                $faceBigRaw = (string)($r['face_image_path'] ?? '') ?: $faceThumb;
                $backBigRaw = (string)($r['back_image_path'] ?? '') ?: $backThumb;

                // Normalizează rutele vechi (ex: /uploads/... fără /public)
                $faceBig = (str_starts_with($faceBigRaw, '/uploads/')) ? Url::to($faceBigRaw) : $faceBigRaw;
                $backBig = (str_starts_with($backBigRaw, '/uploads/')) ? Url::to($backBigRaw) : $backBigRaw;
              ?>
              <a href="#"
                 data-bs-toggle="modal" data-bs-target="#appLightbox"
                 data-lightbox-src="<?= htmlspecialchars($faceBig) ?>"
                 data-lightbox-title="<?= htmlspecialchars((string)$r['face_color_name']) ?>"
                 data-lightbox-fallback="<?= htmlspecialchars($faceThumb) ?>"
                 style="display:inline-block;cursor:zoom-in">
                <img src="<?= htmlspecialchars($faceThumb) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
              </a>
              <?php if (!empty($r['back_thumb_path'])): ?>
                <a href="#"
                   data-bs-toggle="modal" data-bs-target="#appLightbox"
                   data-lightbox-src="<?= htmlspecialchars($backBig) ?>"
                   data-lightbox-title="<?= htmlspecialchars((string)$r['back_color_name']) ?>"
                   data-lightbox-fallback="<?= htmlspecialchars($backThumb) ?>"
                   style="display:inline-block;cursor:zoom-in">
                  <img src="<?= htmlspecialchars($backThumb) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
                </a>
              <?php endif; ?>
            </div>
          </td>
          <td class="fw-semibold"><?= htmlspecialchars((string)$r['code']) ?></td>
          <td><?= htmlspecialchars((string)$r['name']) ?></td>
          <td><?= htmlspecialchars((string)$r['brand']) ?></td>
          <td><?= (int)$r['thickness_mm'] ?> mm</td>
          <td><?= (int)$r['std_width_mm'] ?> × <?= (int)$r['std_height_mm'] ?> mm</td>
          <td class="text-end fw-semibold"><?= (int)$r['stock_qty_available'] ?></td>
          <td class="text-end fw-semibold"><?= number_format((float)$r['stock_m2_available'], 2, '.', '') ?></td>
          <?php if ($isAdmin): ?>
            <?php
              $m2 = (float)($r['stock_m2_available'] ?? 0);
              $ppm = null;
              if (isset($r['sale_price_per_m2']) && $r['sale_price_per_m2'] !== null && $r['sale_price_per_m2'] !== '' && is_numeric($r['sale_price_per_m2'])) {
                $ppm = (float)$r['sale_price_per_m2'];
              } elseif (isset($r['sale_price']) && $r['sale_price'] !== null && $r['sale_price'] !== '' && is_numeric($r['sale_price'])) {
                $stdW = (int)($r['std_width_mm'] ?? 0);
                $stdH = (int)($r['std_height_mm'] ?? 0);
                $area = ($stdW > 0 && $stdH > 0) ? (($stdW * $stdH) / 1000000.0) : 0.0;
                if ($area > 0) $ppm = ((float)$r['sale_price']) / $area;
              }
              $val = ($ppm !== null && $ppm >= 0 && is_finite($ppm) && $m2 > 0) ? ($m2 * $ppm) : null;
            ?>
            <td class="text-end"><?= $ppm !== null ? number_format((float)$ppm, 2, '.', '') : '—' ?></td>
            <td class="text-end fw-semibold"><?= $val !== null ? number_format((float)$val, 2, '.', '') : '—' ?></td>
          <?php endif; ?>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$r['id'])) ?>">
              <i class="bi bi-eye me-1"></i> Vezi
            </a>
            <?php if ($canWrite): ?>
              <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$r['id'] . '/edit')) ?>">
                <i class="bi bi-pencil me-1"></i> Editează
              </a>
              <form method="post" action="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$r['id'] . '/delete')) ?>" class="d-inline"
                    onsubmit="return confirm('Sigur vrei să ștergi această placă? (doar dacă nu are piese asociate)');">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button class="btn btn-outline-secondary btn-sm" type="submit">
                  <i class="bi bi-trash me-1"></i> Șterge
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('boardsTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25 });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

