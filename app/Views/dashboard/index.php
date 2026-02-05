<?php
use App\Core\Auth;
use App\Core\View;
use App\Core\Url;

ob_start();
$byThickness = $byThickness ?? [];
$topColors = $topColors ?? [];
$stockError = $stockError ?? null;
$bottomColors = $bottomColors ?? [];
$readyProductsCount = array_key_exists('readyProductsCount', get_defined_vars()) ? $readyProductsCount : null;
$readyProducts = is_array($readyProducts ?? null) ? $readyProducts : [];
$readyProductsError = $readyProductsError ?? null;
$projectsInWorkCount = array_key_exists('projectsInWorkCount', get_defined_vars()) ? $projectsInWorkCount : null;
$projectsInWork = is_array($projectsInWork ?? null) ? $projectsInWork : [];
$projectsInWorkError = $projectsInWorkError ?? null;
$latestOffers = is_array($latestOffers ?? null) ? $latestOffers : [];
$latestOffersError = $latestOffersError ?? null;
$lowMagazieItems = is_array($lowMagazieItems ?? null) ? $lowMagazieItems : [];
$lowMagazieError = $lowMagazieError ?? null;
$u = Auth::user();
$isViewRole = $u && (string)($u['role'] ?? '') === Auth::ROLE_VIEW;

$fmtQty = function ($v): string {
  $s = number_format((float)$v, 3, '.', '');
  $s = rtrim(rtrim($s, '0'), '.');
  return $s === '' ? '0' : $s;
};
?>
<div class="row g-3">
  <div class="col-12">
    <div class="app-page-title">
      <div>
        <h1 class="m-0">Panou</h1>
        <div class="text-muted">Privire de ansamblu</div>
      </div>
      <?php
        $canStockButtons = $u && in_array((string)($u['role'] ?? ''), [
            \App\Core\Auth::ROLE_ADMIN,
            \App\Core\Auth::ROLE_MANAGER,
            \App\Core\Auth::ROLE_GESTIONAR,
            \App\Core\Auth::ROLE_OPERATOR,
        ], true);
      ?>
      <?php if ($canStockButtons): ?>
        <div class="d-flex gap-2">
          <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-primary"><i class="bi bi-box-seam me-1"></i> Stoc HPL</a>
          <a href="<?= htmlspecialchars(Url::to('/magazie/stoc')) ?>" class="btn btn-primary"><i class="bi bi-boxes me-1"></i> Stoc Magazie</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$isViewRole): ?>
    <div class="col-12">
      <div class="row g-3">
      <div class="col-12 col-lg-4">
        <div class="card app-card p-3 h-100">
          <div class="d-flex align-items-center justify-content-between gap-2">
            <div class="h5 m-0">Produse gata de livrare</div>
            <span class="badge app-badge">Top 5</span>
          </div>
          <div class="text-muted">Neavizate</div>
          <?php if ($readyProductsCount === null): ?>
            <div class="text-muted mt-3">Date indisponibile.</div>
          <?php else: ?>
            <div class="display-6 fw-semibold mt-2"><?= (int)$readyProductsCount ?></div>
            <div class="text-muted small">Status: GATA_DE_LIVRARE</div>
            <a class="btn btn-outline-secondary btn-sm mt-2" href="<?= htmlspecialchars(Url::to('/products')) ?>">
              Vezi produse
            </a>
          <?php endif; ?>
          <?php if ($readyProductsError): ?>
            <div class="text-muted mt-3">Lista indisponibilă.</div>
          <?php elseif (!$readyProducts): ?>
            <div class="text-muted mt-3">Nu există produse încă.</div>
          <?php else: ?>
            <ul class="list-group list-group-flush mt-2">
              <?php foreach ($readyProducts as $r): ?>
                <?php
                  $ppId = (int)($r['project_product_id'] ?? 0);
                  $projId = (int)($r['project_id'] ?? 0);
                  $projCode = trim((string)($r['project_code'] ?? ''));
                  $projName = trim((string)($r['project_name'] ?? ''));
                  $prodName = trim((string)($r['product_name'] ?? ''));
                  $prodCode = trim((string)($r['product_code'] ?? ''));
                  $label = $prodName !== '' ? $prodName : ($prodCode !== '' ? $prodCode : ('Produs #' . $ppId));
                  $pLabel = $projName !== '' ? $projName : ($projCode !== '' ? $projCode : ('Proiect #' . $projId));
                  $href = Url::to('/projects/' . $projId . '?tab=products#pp-' . $ppId);
                ?>
                <li class="list-group-item px-0">
                  <a class="text-decoration-none fw-semibold" href="<?= htmlspecialchars($href) ?>">
                    <?= htmlspecialchars($label) ?>
                  </a>
                  <div class="text-muted small"><?= htmlspecialchars($pLabel) ?></div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="card app-card p-3 h-100">
          <div class="d-flex align-items-center justify-content-between gap-2">
            <div class="h5 m-0">Proiecte în lucru</div>
            <span class="badge app-badge">Top 5</span>
          </div>
          <div class="text-muted">Statusuri active</div>
          <?php if ($projectsInWorkCount === null): ?>
            <div class="text-muted mt-3">Date indisponibile.</div>
          <?php else: ?>
            <div class="display-6 fw-semibold mt-2"><?= (int)$projectsInWorkCount ?></div>
            <div class="text-muted small">Excludem anulat/livrat complet/arhivat</div>
            <a class="btn btn-outline-secondary btn-sm mt-2" href="<?= htmlspecialchars(Url::to('/projects')) ?>">
              Vezi proiecte
            </a>
          <?php endif; ?>
          <?php if ($projectsInWorkError): ?>
            <div class="text-muted mt-3">Lista indisponibilă.</div>
          <?php elseif (!$projectsInWork): ?>
            <div class="text-muted mt-3">Nu există proiecte încă.</div>
          <?php else: ?>
            <ul class="list-group list-group-flush mt-2">
              <?php foreach ($projectsInWork as $p): ?>
                <?php
                  $pid = (int)($p['id'] ?? 0);
                  $pcode = trim((string)($p['code'] ?? ''));
                  $pname = trim((string)($p['name'] ?? ''));
                  $pstatus = (string)($p['status'] ?? '');
                  $label = $pname !== '' ? $pname : ($pcode !== '' ? $pcode : ('Proiect #' . $pid));
                  $sub = $pcode !== '' ? $pcode : ('#' . $pid);
                ?>
                <li class="list-group-item px-0">
                  <a class="text-decoration-none fw-semibold" href="<?= htmlspecialchars(Url::to('/projects/' . $pid)) ?>">
                    <?= htmlspecialchars($label) ?>
                  </a>
                  <div class="text-muted small">
                    <?= htmlspecialchars($sub) ?><?php if ($pstatus !== ''): ?> · <?= htmlspecialchars($pstatus) ?><?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="card app-card p-3 h-100">
          <div class="h5 m-0">Ultimele oferte generate</div>
          <div class="text-muted">Ultimele 5 înregistrări</div>
          <?php if ($latestOffersError): ?>
            <div class="text-muted mt-3">Date indisponibile.</div>
          <?php elseif (!$latestOffers): ?>
            <div class="text-muted mt-3">Nu există oferte încă.</div>
          <?php else: ?>
            <ul class="list-group list-group-flush mt-2">
              <?php foreach ($latestOffers as $o): ?>
                <?php
                  $oid = (int)($o['id'] ?? 0);
                  $ocode = trim((string)($o['code'] ?? ''));
                  $oname = trim((string)($o['name'] ?? ''));
                  $ostat = (string)($o['status'] ?? '');
                  $odate = (string)($o['created_at'] ?? '');
                  $label = $ocode !== '' ? ('Oferta ' . $ocode) : ('Oferta #' . $oid);
                ?>
                <li class="list-group-item px-0">
                  <a class="text-decoration-none fw-semibold" href="<?= htmlspecialchars(Url::to('/offers/' . $oid)) ?>">
                    <?= htmlspecialchars($label) ?>
                  </a>
                  <?php if ($oname !== ''): ?>
                    <div class="text-muted small"><?= htmlspecialchars($oname) ?></div>
                  <?php endif; ?>
                  <div class="text-muted small">
                    <?= htmlspecialchars($odate !== '' ? $odate : '—') ?>
                    <?php if ($ostat !== ''): ?> · <?= htmlspecialchars($ostat) ?><?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <a class="btn btn-outline-secondary btn-sm mt-2" href="<?= htmlspecialchars(Url::to('/offers')) ?>">
            Vezi ofertele
          </a>
        </div>
      </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="col-12">
    <div class="card app-card p-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <div class="h5 m-0">Culori cu cea mai mare cantitate</div>
          <div class="text-muted">Agregat pe Tip culoare (față + verso), indiferent de textură · evidențiere pe grosimi</div>
        </div>
        <?php if ($isViewRole): ?>
          <a href="<?= htmlspecialchars(Url::to('/hpl/catalog')) ?>" class="btn btn-outline-secondary btn-sm">Vezi Catalog</a>
        <?php else: ?>
          <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-outline-secondary btn-sm">Vezi Stoc</a>
        <?php endif; ?>
      </div>

      <?php if ($stockError): ?>
        <div class="alert alert-warning border mt-3 mb-0" style="border-radius:14px">
          <div class="fw-semibold">Statistici indisponibile.</div>
          <div class="text-muted">Rulează <a href="<?= htmlspecialchars(Url::to('/setup')) ?>">Setup</a> dacă tabelele de stoc nu sunt instalate încă.</div>
        </div>
      <?php else: ?>
        <div class="mt-2">
          <div id="dashboardTopColorsGrid">
            <?= View::render('dashboard/_top_colors_grid', [
              'topColors' => $topColors,
              'linkBase' => $isViewRole ? '/hpl/catalog' : '/stock',
            ]) ?>
          </div>
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
        <?php if ($isViewRole): ?>
          <a href="<?= htmlspecialchars(Url::to('/hpl/catalog')) ?>" class="btn btn-outline-secondary btn-sm">Vezi Catalog</a>
        <?php else: ?>
          <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-outline-secondary btn-sm">Vezi Stoc</a>
        <?php endif; ?>
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
                  <?php $thicknessHref = $isViewRole ? Url::to('/hpl/catalog') : (Url::to('/stock') . '?thickness_mm=' . (int)$r['thickness_mm']); ?>
                  <td class="fw-semibold">
                    <a class="text-decoration-none" style="color:#111"
                       href="<?= htmlspecialchars($thicknessHref) ?>">
                      <?= (int)$r['thickness_mm'] ?> mm
                    </a>
                  </td>
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

  <?php if (!$isViewRole): ?>
  <div class="col-12 col-lg-6">
    <div class="card app-card p-3">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="h5 m-0">Accesorii cu stoc redus</div>
          <div class="text-muted">Top 5 cele mai mici stocuri</div>
        </div>
        <a href="<?= htmlspecialchars(Url::to('/magazie/stoc')) ?>" class="btn btn-outline-secondary btn-sm">Vezi Magazie</a>
      </div>

      <?php if ($lowMagazieError): ?>
        <div class="text-muted mt-3">Date indisponibile.</div>
      <?php else: ?>
        <div class="mt-3">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Accesoriu</th>
                <th class="text-end" style="width:130px">Stoc</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$lowMagazieItems): ?>
                <tr><td colspan="2" class="text-muted">Nu există accesorii încă.</td></tr>
              <?php endif; ?>
              <?php foreach ($lowMagazieItems as $it): ?>
                <?php
                  $id = (int)($it['id'] ?? 0);
                  $code = (string)($it['winmentor_code'] ?? '');
                  $name = (string)($it['name'] ?? '');
                  $qty = (float)($it['stock_qty'] ?? 0);
                  $unit = (string)($it['unit'] ?? 'buc');
                  $label = trim($code . ' · ' . $name);
                  if ($label === '·' || $label === '· ') $label = $name !== '' ? $name : $code;
                ?>
                <tr>
                  <td>
                    <a class="text-decoration-none fw-semibold" style="color:#111"
                       href="<?= htmlspecialchars(Url::to('/magazie/stoc/' . $id)) ?>">
                      <?= htmlspecialchars($label !== '' ? $label : ('Produs #' . $id)) ?>
                    </a>
                  </td>
                  <td class="text-end fw-semibold"><?= $fmtQty($qty) ?> <?= htmlspecialchars($unit) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12">
    <div class="card app-card p-3">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="h5 m-0">Culori cu cea mai mică cantitate</div>
          <div class="text-muted">Fără stoc 0 · cele mai apropiate de zero</div>
        </div>
        <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-outline-secondary btn-sm">Vezi Stoc</a>
      </div>

      <?php if ($stockError): ?>
        <div class="alert alert-warning border mt-3 mb-0" style="border-radius:14px">
          <div class="fw-semibold">Statistici indisponibile.</div>
          <div class="text-muted">Rulează <a href="<?= htmlspecialchars(Url::to('/setup')) ?>">Setup</a> dacă tabelele de stoc nu sunt instalate încă.</div>
        </div>
      <?php else: ?>
        <div class="mt-2">
          <?= View::render('dashboard/_top_colors_grid', [
            'topColors' => $bottomColors,
            'linkBase' => '/stock',
          ]) ?>
        </div>
      <?php endif; ?>
    </div>
    </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

