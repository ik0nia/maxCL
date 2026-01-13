<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);

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
        <th class="text-end" style="width:220px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td>
            <div class="d-flex gap-1">
              <?php
                $faceBig = (string)($r['face_image_path'] ?? '') ?: (string)$r['face_thumb_path'];
                $backBig = (string)($r['back_image_path'] ?? '') ?: (string)($r['back_thumb_path'] ?? '');
              ?>
              <a href="#"
                 data-bs-toggle="modal" data-bs-target="#appLightbox"
                 data-lightbox-src="<?= htmlspecialchars($faceBig) ?>"
                 data-lightbox-title="<?= htmlspecialchars((string)$r['face_color_name']) ?>"
                 style="display:inline-block;cursor:zoom-in">
                <img src="<?= htmlspecialchars((string)$r['face_thumb_path']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
              </a>
              <?php if (!empty($r['back_thumb_path'])): ?>
                <a href="#"
                   data-bs-toggle="modal" data-bs-target="#appLightbox"
                   data-lightbox-src="<?= htmlspecialchars($backBig) ?>"
                   data-lightbox-title="<?= htmlspecialchars((string)$r['back_color_name']) ?>"
                   style="display:inline-block;cursor:zoom-in">
                  <img src="<?= htmlspecialchars((string)$r['back_thumb_path']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
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

