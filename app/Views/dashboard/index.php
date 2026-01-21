<?php
use App\Core\View;
use App\Core\Url;

ob_start();
$byThickness = $byThickness ?? [];
$topColors = $topColors ?? [];
$stockError = $stockError ?? null;
$readyProductsCount = array_key_exists('readyProductsCount', get_defined_vars()) ? $readyProductsCount : null;
$projectsInWorkCount = array_key_exists('projectsInWorkCount', get_defined_vars()) ? $projectsInWorkCount : null;
$latestOffers = is_array($latestOffers ?? null) ? $latestOffers : [];
$latestOffersError = $latestOffersError ?? null;
$lowMagazieItems = is_array($lowMagazieItems ?? null) ? $lowMagazieItems : [];
$lowMagazieError = $lowMagazieError ?? null;
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
    <div class="row g-3">
      <div class="col-12 col-lg-4">
        <div class="card app-card p-3 h-100">
          <div class="h5 m-0">Produse gata de livrare</div>
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
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="card app-card p-3 h-100">
          <div class="h5 m-0">Proiecte în lucru</div>
          <div class="text-muted">Statusuri active</div>
          <?php if ($projectsInWorkCount === null): ?>
            <div class="text-muted mt-3">Date indisponibile.</div>
          <?php else: ?>
            <div class="display-6 fw-semibold mt-2"><?= (int)$projectsInWorkCount ?></div>
            <div class="text-muted small">Excludem draft/anulat/livrat complet/arhivat</div>
            <a class="btn btn-outline-secondary btn-sm mt-2" href="<?= htmlspecialchars(Url::to('/projects')) ?>">
              Vezi proiecte
            </a>
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
            <a class="btn btn-outline-secondary btn-sm mt-2" href="<?= htmlspecialchars(Url::to('/offers')) ?>">
              Vezi ofertele
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card app-card p-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <div class="h5 m-0">Culori cu cea mai mare cantitate</div>
          <div class="text-muted">Agregat pe Tip culoare (față + verso), indiferent de textură · evidențiere pe grosimi</div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
          <div class="input-group input-group-sm" style="max-width:360px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text"
                   class="form-control"
                   id="topColorsSearch"
                   placeholder="Caută după cod / culoare / cod culoare"
                   autocomplete="off">
            <button class="btn btn-outline-secondary" type="button" id="topColorsClear" title="Șterge">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
          <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-outline-secondary btn-sm">Vezi Stoc</a>
        </div>
      </div>

      <?php if ($stockError): ?>
        <div class="alert alert-warning border mt-3 mb-0" style="border-radius:14px">
          <div class="fw-semibold">Statistici indisponibile.</div>
          <div class="text-muted">Rulează <a href="<?= htmlspecialchars(Url::to('/setup')) ?>">Setup</a> dacă tabelele de stoc nu sunt instalate încă.</div>
        </div>
      <?php else: ?>
        <div class="mt-2">
          <div id="dashboardTopColorsLoading" class="text-muted small" style="display:none">
            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
            Se caută…
          </div>
          <div id="dashboardTopColorsGrid" data-endpoint="<?= htmlspecialchars(Url::to('/api/dashboard/top-colors')) ?>">
            <?= View::render('dashboard/_top_colors_grid', ['topColors' => $topColors]) ?>
          </div>
        </div>

        <script>
          (function () {
            var $ = window.jQuery;
            if (!$) return;

            var $input = $('#topColorsSearch');
            var $clear = $('#topColorsClear');
            var $gridWrap = $('#dashboardTopColorsGrid');
            var $loading = $('#dashboardTopColorsLoading');
            var endpoint = $gridWrap.data('endpoint');

            var timer = null;
            var lastQ = null;
            var req = null;

            function setLoading(on) {
              if (!$loading.length) return;
              $loading.css('display', on ? 'block' : 'none');
            }

            function load(q) {
              if (!endpoint) return;
              if (req && req.abort) req.abort();
              setLoading(true);
              req = $.getJSON(endpoint, { q: q || '' })
                .done(function (res) {
                  if (!res || res.ok !== true) {
                    $gridWrap.html('<div class="text-muted">Nu am putut încărca rezultatele.</div>');
                    return;
                  }
                  $gridWrap.html(res.html || '');
                })
                .fail(function (xhr, statusText) {
                  if (statusText === 'abort') return;
                  $gridWrap.html('<div class="text-muted">Nu am putut încărca rezultatele.</div>');
                })
                .always(function () {
                  setLoading(false);
                });
            }

            $input.on('input', function () {
              var q = String($input.val() || '');
              if (timer) window.clearTimeout(timer);
              timer = window.setTimeout(function () {
                if (q === lastQ) return;
                lastQ = q;
                load(q);
              }, 250);
            });

            $clear.on('click', function () {
              $input.val('');
              $input.trigger('input');
              $input.focus();
            });
          })();
        </script>
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
                  <td class="fw-semibold">
                    <a class="text-decoration-none" style="color:#111"
                       href="<?= htmlspecialchars(Url::to('/stock') . '?thickness_mm=' . (int)$r['thickness_mm']) ?>">
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
                  <td class="text-end fw-semibold"><?= number_format((float)$qty, 3, '.', '') ?> <?= htmlspecialchars($unit) ?></td>
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

