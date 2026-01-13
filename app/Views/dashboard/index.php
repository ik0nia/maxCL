<?php
use App\Core\View;
use App\Core\Url;

ob_start();
$byThickness = $byThickness ?? [];
$topColors = $topColors ?? [];
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

  <div class="col-12">
    <div class="card app-card p-3">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="h5 m-0">Culori cu cea mai mare cantitate</div>
          <div class="text-muted">Agregat pe Tip culoare (față), indiferent de textură · evidențiere pe grosimi</div>
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
          <?php foreach ($topColors as $c): ?>
            <?php
              $thumb = (string)($c['thumb_path'] ?? '');
              $big = (string)($c['image_path'] ?? '') ?: $thumb;
              $code = (string)($c['color_code'] ?? '');
              if ($code === '') $code = '—';
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
                  <div class="fw-semibold" style="font-size:1.15rem;line-height:1.1"><?= htmlspecialchars($code) ?></div>
                  <div class="text-muted" style="font-weight:600"><?= htmlspecialchars((string)$c['color_name']) ?></div>
                  <div class="text-muted small mt-1">Suprafața totală: <span class="fw-semibold"><?= number_format((float)$c['total_m2'], 2, '.', '') ?></span> mp</div>
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
            <div class="col-12 text-muted">Nu există date încă.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
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
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

