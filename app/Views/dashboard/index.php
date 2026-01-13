<?php
use App\Core\View;
use App\Core\Url;

ob_start();
$byThickness = $byThickness ?? [];
$topBoards = $topBoards ?? [];
$stockError = $stockError ?? null;
?>
<div class="row g-3">
  <div class="col-12">
    <div class="app-page-title">
      <div>
        <h1 class="m-0">Panou</h1>
        <div class="text-muted">Privire de ansamblu</div>
      </div>
      <div class="d-flex gap-2">
        <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-primary"><i class="bi bi-box-seam me-1"></i> Stoc</a>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card app-card p-3">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="h5 m-0">Stoc disponibil pe grosimi</div>
          <div class="text-muted">Total bucăți și mp (status AVAILABLE)</div>
        </div>
        <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-outline-secondary btn-sm">Vezi Stoc</a>
      </div>

      <?php if ($stockError): ?>
        <div class="alert alert-warning border mt-3 mb-0" style="border-radius:14px">
          <div class="fw-semibold">Statistici indisponibile.</div>
          <div class="text-muted">Rulează <a href="<?= htmlspecialchars(Url::to('/setup')) ?>">Setup</a> dacă tabelele de stoc nu sunt instalate încă.</div>
        </div>
      <?php else: ?>
        <div class="mt-3">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Grosime</th>
                <th class="text-end">Buc</th>
                <th class="text-end">mp</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$byThickness): ?>
                <tr><td colspan="3" class="text-muted">Nu există date încă.</td></tr>
              <?php endif; ?>
              <?php foreach ($byThickness as $r): ?>
                <tr>
                  <td class="fw-semibold"><?= (int)$r['thickness_mm'] ?> mm</td>
                  <td class="text-end"><?= (int)$r['qty'] ?></td>
                  <td class="text-end fw-semibold"><?= number_format((float)$r['m2'], 2, '.', '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card app-card p-3">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="h5 m-0">Plăci cu cea mai mare cantitate</div>
          <div class="text-muted">Top după mp disponibili (AVAILABLE)</div>
        </div>
        <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-outline-secondary btn-sm">Vezi Stoc</a>
      </div>

      <?php if ($stockError): ?>
        <div class="alert alert-warning border mt-3 mb-0" style="border-radius:14px">
          <div class="fw-semibold">Statistici indisponibile.</div>
          <div class="text-muted">Rulează <a href="<?= htmlspecialchars(Url::to('/setup')) ?>">Setup</a> dacă tabelele de stoc nu sunt instalate încă.</div>
        </div>
      <?php else: ?>
        <div class="row g-2 mt-2">
          <?php foreach ($topBoards as $b): ?>
            <?php
              $faceThumb = (string)($b['face_thumb_path'] ?? '');
              $faceBig = (string)($b['face_image_path'] ?? '') ?: $faceThumb;
              $backThumb = (string)($b['back_thumb_path'] ?? '');
              $backBig = (string)($b['back_image_path'] ?? '') ?: $backThumb;
            ?>
            <div class="col-12 col-md-6">
              <a href="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$b['id'])) ?>" class="text-decoration-none">
                <div class="border rounded-4 p-2 d-flex gap-2 align-items-center" style="border-color:#D9E3E6;background:#fff">
                  <div class="d-flex gap-1">
                    <?php if ($faceThumb): ?>
                      <a href="#"
                         data-bs-toggle="modal" data-bs-target="#appLightbox"
                         data-lightbox-src="<?= htmlspecialchars($faceBig) ?>"
                         data-lightbox-fallback="<?= htmlspecialchars($faceThumb) ?>"
                         data-lightbox-title="<?= htmlspecialchars((string)$b['code']) ?>"
                         style="display:inline-block;cursor:zoom-in">
                        <img src="<?= htmlspecialchars($faceThumb) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
                      </a>
                    <?php endif; ?>
                    <?php if ($backThumb): ?>
                      <a href="#"
                         data-bs-toggle="modal" data-bs-target="#appLightbox"
                         data-lightbox-src="<?= htmlspecialchars($backBig) ?>"
                         data-lightbox-fallback="<?= htmlspecialchars($backThumb) ?>"
                         data-lightbox-title="<?= htmlspecialchars((string)$b['code']) ?>"
                         style="display:inline-block;cursor:zoom-in">
                        <img src="<?= htmlspecialchars($backThumb) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
                      </a>
                    <?php endif; ?>
                  </div>
                  <div class="flex-grow-1">
                    <div class="fw-semibold"><?= htmlspecialchars((string)$b['code']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars((string)$b['brand']) ?> · <?= (int)$b['thickness_mm'] ?> mm</div>
                    <div class="text-muted small">Suprafața totală: <span class="fw-semibold"><?= number_format((float)$b['m2_available'], 2, '.', '') ?></span> mp</div>
                  </div>
                </div>
              </a>
            </div>
          <?php endforeach; ?>

          <?php if (!$topBoards): ?>
            <div class="col-12 text-muted">Nu există date încă.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

