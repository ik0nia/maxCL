<?php
use App\Core\Url;
use App\Core\View;

$tab = (string)($tab ?? 'hpl');
$mode = (string)($mode ?? 'CONSUMED');
$dateFrom = (string)($date_from ?? '');
$dateTo = (string)($date_to ?? '');
$hplRows = is_array($hplRows ?? null) ? $hplRows : [];
$magRows = is_array($magRows ?? null) ? $magRows : [];

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

<?php if ($tab === 'hpl'): ?>
  <div class="card app-card p-3">
    <div class="h5 m-0">Consum HPL</div>
    <div class="text-muted">Din `project_hpl_consumptions`</div>
    <div class="table-responsive mt-2">
      <table class="table table-hover align-middle mb-0" id="hplTable">
        <thead>
          <tr>
            <th style="width:170px">Data</th>
            <th style="width:110px">Mod</th>
            <th>Proiect</th>
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
              $btxt = trim((string)($r['board_code'] ?? '') . ' · ' . (string)($r['board_name'] ?? ''));
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
              <td>
                <a class="text-decoration-none fw-semibold" href="<?= htmlspecialchars(Url::to('/stock/boards/' . $bid)) ?>">
                  <?= htmlspecialchars($btxt) ?>
                </a>
                <div class="text-muted small"><?= htmlspecialchars((string)($r['thickness_mm'] ?? '')) ?>mm</div>
              </td>
              <td class="text-end fw-semibold"><?= (int)($r['qty_boards'] ?? 0) ?></td>
              <td class="text-end fw-semibold"><?= number_format((float)($r['qty_m2'] ?? 0), 2, '.', '') ?></td>
              <td class="text-muted small"><?= htmlspecialchars($note) ?></td>
              <td class="text-muted small"><?= htmlspecialchars($user !== '' ? $user : '—') ?></td>
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
              <td class="text-end fw-semibold"><?= number_format($qty, 3, '.', '') ?> <?= htmlspecialchars($unit) ?></td>
              <td class="text-end fw-semibold"><?= $val !== null ? number_format((float)$val, 2, '.', '') . ' lei' : '—' ?></td>
              <td class="text-muted small"><?= htmlspecialchars($note) ?></td>
              <td class="text-muted small"><?= htmlspecialchars($user !== '' ? $user : '—') ?></td>
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
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

