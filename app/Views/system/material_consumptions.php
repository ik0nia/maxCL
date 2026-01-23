<?php
use App\Core\Url;
use App\Core\View;

$tab = (string)($tab ?? 'hpl');
$mode = (string)($mode ?? 'CONSUMED');
$dateFrom = (string)($date_from ?? '');
$dateTo = (string)($date_to ?? '');
$hplRows = is_array($hplRows ?? null) ? $hplRows : [];
$magRows = is_array($magRows ?? null) ? $magRows : [];
$hplAgg = is_array($hplAgg ?? null) ? $hplAgg : [];
$magAgg = is_array($magAgg ?? null) ? $magAgg : [];

$tabs = [
  'hpl' => 'Consum HPL',
  'accesorii' => 'Consum accesorii',
];

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Consumuri materiale</h1>
    <div class="text-muted">Centralizat pe proiecte · filtrabil pe perioadă</div>
  </div>
</div>

<div class="card app-card p-3 mb-3">
  <form method="get" action="<?= htmlspecialchars(Url::to('/system/consumuri-materiale')) ?>" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold mb-1">De la</label>
      <input class="form-control" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold mb-1">Până la</label>
      <input class="form-control" type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold mb-1">Mod</label>
      <select class="form-select" name="mode">
        <option value="CONSUMED" <?= $mode === 'CONSUMED' ? 'selected' : '' ?>>consumat</option>
        <option value="RESERVED" <?= $mode === 'RESERVED' ? 'selected' : '' ?>>rezervat</option>
        <option value="ALL" <?= $mode === 'ALL' ? 'selected' : '' ?>>toate</option>
      </select>
    </div>
    <div class="col-12 col-md-3 d-flex gap-2">
      <button class="btn btn-outline-secondary" type="submit">
        <i class="bi bi-search me-1"></i> Filtrează
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/system/consumuri-materiale?tab=' . urlencode($tab))) ?>">
        <i class="bi bi-x-lg me-1"></i> Reset
      </a>
    </div>
  </form>
</div>

<ul class="nav nav-tabs mb-3">
  <?php foreach ($tabs as $k => $lbl): ?>
    <li class="nav-item">
      <a class="nav-link <?= $tab === $k ? 'active' : '' ?>"
         href="<?= htmlspecialchars(Url::to('/system/consumuri-materiale?tab=' . urlencode($k) . ($dateFrom !== '' ? ('&date_from=' . urlencode($dateFrom)) : '') . ($dateTo !== '' ? ('&date_to=' . urlencode($dateTo)) : '') . ($mode !== '' ? ('&mode=' . urlencode($mode)) : ''))) ?>">
        <?= htmlspecialchars($lbl) ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<div class="d-flex justify-content-end mb-2">
  <div class="form-check form-switch m-0">
    <input class="form-check-input" type="checkbox" role="switch" id="toggleAgg">
    <label class="form-check-label text-muted" for="toggleAgg">Afișează cumulat</label>
  </div>
</div>

<?php if ($tab === 'hpl'): ?>
  <div class="card app-card p-3">
    <div class="h5 m-0">Consum HPL</div>
    <div class="text-muted">Din `project_hpl_consumptions` + `project_product_hpl_consumptions`</div>
    <div class="table-responsive mt-2">
      <table class="table table-hover align-middle mb-0" id="hplTable">
        <thead>
          <tr>
            <th style="width:170px">Data</th>
            <th style="width:110px">Mod</th>
            <th>Sursă</th>
            <th>Proiect</th>
            <th>Piesă</th>
            <th>Placă</th>
            <th class="text-end" style="width:110px">Buc</th>
            <th class="text-end" style="width:110px">mp</th>
            <th>Notă</th>
            <th style="width:220px">User</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($hplRows as $r): ?>
            <?php
              $pid = (int)($r['project_id'] ?? 0);
              $bid = (int)($r['board_id'] ?? 0);
              $pname = trim((string)($r['project_name'] ?? ''));
              $src = (string)($r['source'] ?? 'PROIECT');
              $ppId = (int)($r['project_product_id'] ?? 0);
              $prodName = trim((string)($r['product_name'] ?? ''));
              $btxt = trim((string)($r['board_code'] ?? '') . ' · ' . (string)($r['board_name'] ?? ''));
              $user = trim((string)($r['user_name'] ?? '') . ' ' . (string)($r['user_email'] ?? ''));
              $modeTxt = (string)($r['mode'] ?? '');
              $note = (string)($r['note'] ?? '');
              $qb = isset($r['qty_boards']) ? (float)($r['qty_boards'] ?? 0) : 0.0;
              $qm2 = isset($r['qty_m2']) ? (float)($r['qty_m2'] ?? 0) : 0.0;
              $stdW = (int)($r['std_width_mm'] ?? 0);
              $stdH = (int)($r['std_height_mm'] ?? 0);
              $area = ($stdW > 0 && $stdH > 0) ? (($stdW * $stdH) / 1000000.0) : 0.0;
              // Preferăm mp/aria plăcii, ca să includă și jumătăți (qty_boards e 0 la 1/2).
              $eq = ($area > 0 && $qm2 > 0) ? ($qm2 / $area) : $qb;
              $eqTxt = '0';
              if ($eq > 0) {
                if (abs($eq - 0.5) < 0.06) $eqTxt = '0.5';
                elseif (abs($eq - round($eq)) < 1e-6) $eqTxt = (string)((int)round($eq));
                else $eqTxt = number_format($eq, 2, '.', '');
              }
            ?>
            <tr>
              <td class="text-muted small"><?= htmlspecialchars((string)($r['created_at'] ?? '')) ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($modeTxt) ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($src) ?></td>
              <td>
                <a class="text-decoration-none fw-semibold" href="<?= htmlspecialchars(Url::to('/projects/' . $pid . '?tab=consum')) ?>">
                  <?= htmlspecialchars($pname !== '' ? $pname : ('Proiect #' . $pid)) ?>
                </a>
                <div class="text-muted small"><?= htmlspecialchars((string)($r['project_code'] ?? '')) ?></div>
              </td>
              <td>
                <?php if ($ppId > 0): ?>
                  <div class="fw-semibold"><?= htmlspecialchars($prodName !== '' ? $prodName : ('#' . $ppId)) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars('Piesă #' . $ppId) ?></div>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <a class="text-decoration-none fw-semibold" href="<?= htmlspecialchars(Url::to('/stock/boards/' . $bid)) ?>">
                  <?= htmlspecialchars($btxt) ?>
                </a>
                <div class="text-muted small"><?= htmlspecialchars((string)($r['thickness_mm'] ?? '')) ?>mm</div>
              </td>
              <td class="text-end fw-semibold"><?= htmlspecialchars($eqTxt) ?></td>
              <td class="text-end fw-semibold"><?= number_format((float)($r['qty_m2'] ?? 0), 2, '.', '') ?></td>
              <td class="text-muted small"><?= htmlspecialchars($note) ?></td>
              <td class="text-muted small"><?= htmlspecialchars($user !== '' ? $user : '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card app-card p-3 mt-3 d-none" id="aggWrap">
    <div class="h5 m-0">Cumulat pe material</div>
    <div class="text-muted">Grupat pe placă (cantități adunate)</div>
    <div class="table-responsive mt-2">
      <table class="table table-hover align-middle mb-0" id="hplAggTable">
        <thead>
          <tr>
            <th>Placă</th>
            <th class="text-end" style="width:120px">Întregi</th>
            <th class="text-end" style="width:120px">Jumătăți</th>
            <th class="text-end" style="width:130px">Buc (echiv.)</th>
            <th class="text-end" style="width:110px">mp</th>
            <th class="text-end" style="width:110px">Rânduri</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($hplAgg as $r): ?>
            <?php
              $bid = (int)($r['board_id'] ?? 0);
              $btxt = trim((string)($r['board_code'] ?? '') . ' · ' . (string)($r['board_name'] ?? ''));
              $qb = (float)($r['sum_qty_boards'] ?? 0.0);
              $qm2 = (float)($r['sum_qty_m2'] ?? 0.0);
              $stdW = (int)($r['std_width_mm'] ?? 0);
              $stdH = (int)($r['std_height_mm'] ?? 0);
              $area = ($stdW > 0 && $stdH > 0) ? (($stdW * $stdH) / 1000000.0) : 0.0;
              // Preferăm mp/aria plăcii, ca să includă și jumătăți (qty_boards e 0 la 1/2).
              $eq = ($area > 0 && $qm2 > 0) ? ($qm2 / $area) : $qb;
              $fullTxt = (abs($qb - round($qb)) < 1e-6) ? (string)((int)round($qb)) : number_format($qb, 2, '.', '');
              // presupunem fracții doar de 0.5 (jumătăți). Convertim restul la număr de jumătăți.
              $halfCount = 0.0;
              if ($area > 0 && $eq > 0) {
                $restEq = max(0.0, $eq - max(0.0, $qb));
                $halfCount = $restEq / 0.5;
              }
              $halfTxt = (abs($halfCount - round($halfCount)) < 0.06) ? (string)((int)round($halfCount)) : number_format($halfCount, 2, '.', '');
              $eqTxt = '0';
              if ($eq > 0) {
                if (abs($eq - 0.5) < 0.06) $eqTxt = '0.5';
                elseif (abs($eq - round($eq)) < 1e-6) $eqTxt = (string)((int)round($eq));
                else $eqTxt = number_format($eq, 2, '.', '');
              }
            ?>
            <tr>
              <td>
                <a class="text-decoration-none fw-semibold" href="<?= htmlspecialchars(Url::to('/stock/boards/' . $bid)) ?>">
                  <?= htmlspecialchars($btxt) ?>
                </a>
              </td>
              <td class="text-end fw-semibold"><?= htmlspecialchars($fullTxt) ?></td>
              <td class="text-end fw-semibold"><?= htmlspecialchars($halfTxt) ?></td>
              <td class="text-end fw-semibold"><?= htmlspecialchars($eqTxt) ?></td>
              <td class="text-end fw-semibold"><?= number_format($qm2, 2, '.', '') ?></td>
              <td class="text-end text-muted"><?= (int)($r['rows'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <div class="card app-card p-3">
    <div class="h5 m-0">Consum accesorii</div>
    <div class="text-muted">Din `project_magazie_consumptions`</div>
    <div class="table-responsive mt-2">
      <table class="table table-hover align-middle mb-0" id="magTable">
        <thead>
          <tr>
            <th style="width:170px">Data</th>
            <th style="width:110px">Mod</th>
            <th>Proiect</th>
            <th>Accesoriu</th>
            <th style="width:200px">Piesă</th>
            <th class="text-end" style="width:140px">Cant.</th>
            <th class="text-end" style="width:140px">Valoare</th>
            <th>Notă</th>
            <th style="width:220px">User</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($magRows as $r): ?>
            <?php
              $pid = (int)($r['project_id'] ?? 0);
              $pname = trim((string)($r['project_name'] ?? ''));
              $ppId = (int)($r['project_product_id'] ?? 0);
              $prodName = trim((string)($r['product_name'] ?? ''));
              $itemTxt = trim((string)($r['winmentor_code'] ?? '') . ' · ' . (string)($r['item_name'] ?? ''));
              $qty = (float)($r['qty'] ?? 0);
              $unit = (string)($r['unit'] ?? '');
              $up = (isset($r['unit_price']) && $r['unit_price'] !== null && $r['unit_price'] !== '' && is_numeric($r['unit_price'])) ? (float)$r['unit_price'] : null;
              $val = ($up !== null) ? ($up * $qty) : null;
              $user = trim((string)($r['user_name'] ?? '') . ' ' . (string)($r['user_email'] ?? ''));
              $modeTxt = (string)($r['mode'] ?? '');
              $note = (string)($r['note'] ?? '');
            ?>
            <tr>
              <td class="text-muted small"><?= htmlspecialchars((string)($r['created_at'] ?? '')) ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($modeTxt) ?></td>
              <td>
                <a class="text-decoration-none fw-semibold" href="<?= htmlspecialchars(Url::to('/projects/' . $pid . '?tab=consum')) ?>">
                  <?= htmlspecialchars($pname !== '' ? $pname : ('Proiect #' . $pid)) ?>
                </a>
                <div class="text-muted small"><?= htmlspecialchars((string)($r['project_code'] ?? '')) ?></div>
              </td>
              <td class="fw-semibold">
                <a class="text-decoration-none" href="<?= htmlspecialchars(Url::to('/magazie/stoc/' . (int)($r['item_id'] ?? 0))) ?>">
                  <?= htmlspecialchars($itemTxt) ?>
                </a>
              </td>
              <td class="text-muted small">
                <?php if ($ppId > 0): ?>
                  <a class="text-decoration-none" href="<?= htmlspecialchars(Url::to('/projects/' . $pid . '?tab=products')) ?>">
                    #<?= (int)$ppId ?><?= $prodName !== '' ? (' · ' . htmlspecialchars($prodName)) : '' ?>
                  </a>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td class="text-end fw-semibold"><?= number_format($qty, 2, '.', '') ?> <?= htmlspecialchars($unit) ?></td>
              <td class="text-end fw-semibold"><?= $val !== null ? number_format((float)$val, 2, '.', '') . ' lei' : '—' ?></td>
              <td class="text-muted small"><?= htmlspecialchars($note) ?></td>
              <td class="text-muted small"><?= htmlspecialchars($user !== '' ? $user : '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card app-card p-3 mt-3 d-none" id="aggWrap">
    <div class="h5 m-0">Cumulat pe accesorii</div>
    <div class="text-muted">Grupat pe accesoriu (cantități adunate)</div>
    <div class="table-responsive mt-2">
      <table class="table table-hover align-middle mb-0" id="magAggTable">
        <thead>
          <tr>
            <th>Accesoriu</th>
            <th class="text-end" style="width:160px">Cant.</th>
            <th class="text-end" style="width:140px">Valoare</th>
            <th class="text-end" style="width:110px">Rânduri</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($magAgg as $r): ?>
            <?php
              $iid = (int)($r['item_id'] ?? 0);
              $itemTxt = trim((string)($r['winmentor_code'] ?? '') . ' · ' . (string)($r['item_name'] ?? ''));
              $sumQty = (float)($r['sum_qty'] ?? 0.0);
              $unit = (string)($r['unit'] ?? '');
              $sumVal = (float)($r['sum_value'] ?? 0.0);
              $hasVal = isset($r['unit_price']) && $r['unit_price'] !== null && $r['unit_price'] !== '';
            ?>
            <tr>
              <td class="fw-semibold">
                <a class="text-decoration-none" href="<?= htmlspecialchars(Url::to('/magazie/stoc/' . $iid)) ?>">
                  <?= htmlspecialchars($itemTxt) ?>
                </a>
              </td>
              <td class="text-end fw-semibold"><?= number_format($sumQty, 2, '.', '') ?> <?= htmlspecialchars($unit) ?></td>
              <td class="text-end fw-semibold"><?= $hasVal ? (number_format($sumVal, 2, '.', '') . ' lei') : '—' ?></td>
              <td class="text-end text-muted"><?= (int)($r['rows'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    function init(id){
      const el = document.getElementById(id);
      if (!el || !window.DataTable) return;
      new DataTable(el, {
        pageLength: 50,
        lengthMenu: [[25, 50, 100, 200], [25, 50, 100, 200]],
        order: [[0, 'desc']]
      });
    }
    init('hplTable');
    init('magTable');
    init('hplAggTable');
    init('magAggTable');

    const cb = document.getElementById('toggleAgg');
    const wrap = document.getElementById('aggWrap');
    if (cb && wrap) {
      const key = 'sys_material_consumptions_agg_v1';
      const apply = (on) => { if (on) wrap.classList.remove('d-none'); else wrap.classList.add('d-none'); };
      try {
        const saved = localStorage.getItem(key);
        const on = saved === '1';
        cb.checked = on;
        apply(on);
      } catch (e) {}
      cb.addEventListener('change', function(){
        const on = !!cb.checked;
        apply(on);
        try { localStorage.setItem(key, on ? '1' : '0'); } catch (e) {}
      });
    }
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

