<?php
use App\Core\Url;
use App\Core\View;

$rows = $rows ?? [];
$q = trim((string)($q ?? ''));
$label = trim((string)($label ?? ''));
$stLbl = [
  'CREAT' => 'Creat',
  'PROIECTARE' => 'Proiectare',
  'CNC' => 'CNC',
  'MONTAJ' => 'Montaj',
  'GATA_DE_LIVRARE' => 'Gata de livrare',
  'AVIZAT' => 'Avizat',
  'LIVRAT' => 'Livrat',
];

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Produse</h1>
    <div class="text-muted">Produsele sunt piesele folosite în proiecte (status controlat din proiect)</div>
  </div>
</div>

<div class="card app-card p-3 mb-3">
  <form method="get" action="<?= htmlspecialchars(Url::to('/products')) ?>" class="row g-2 align-items-end">
    <div style="min-width:320px;flex:1">
      <label class="form-label fw-semibold mb-1">Caută</label>
      <input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cod sau nume…">
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label fw-semibold mb-1">Etichetă (label)</label>
      <input class="form-control" name="label" value="<?= htmlspecialchars($label) ?>" placeholder="ex: urgent">
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary" type="submit">
        <i class="bi bi-search me-1"></i> Caută
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/products')) ?>">
        <i class="bi bi-x-lg me-1"></i> Reset
      </a>
    </div>
  </form>
</div>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="productsTable">
    <thead>
      <tr>
        <th style="width:160px">Proiect</th>
        <th>Produs</th>
        <th style="width:140px">Status</th>
        <th class="text-end" style="width:130px">Cant.</th>
        <th class="text-end" style="width:130px">Livrat</th>
        <th style="width:220px">Etichete</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="js-row-link" data-href="<?= htmlspecialchars(Url::to('/projects/' . (int)($r['project_id'] ?? 0) . '?tab=products')) ?>" role="button" tabindex="0">
          <td>
            <a class="text-decoration-none fw-semibold" href="<?= htmlspecialchars(Url::to('/projects/' . (int)($r['project_id'] ?? 0) . '?tab=products')) ?>">
              <?= htmlspecialchars((string)($r['project_name'] ?? '')) ?>
            </a>
            <div class="text-muted small"><?= htmlspecialchars((string)($r['project_status'] ?? '')) ?></div>
          </td>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars((string)($r['product_name'] ?? '')) ?></div>
            <div class="text-muted small"><?= htmlspecialchars((string)($r['product_code'] ?? '')) ?></div>
          </td>
          <?php $sv = (string)($r['production_status'] ?? ''); ?>
          <td class="fw-semibold"><?= htmlspecialchars($stLbl[$sv] ?? $sv) ?></td>
          <td class="text-end"><?= number_format((float)($r['qty'] ?? 0), 2, '.', '') ?> <?= htmlspecialchars((string)($r['unit'] ?? '')) ?></td>
          <td class="text-end fw-semibold"><?= number_format((float)($r['delivered_qty'] ?? 0), 2, '.', '') ?></td>
          <td class="text-muted"><?= htmlspecialchars((string)($r['labels'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('productsTable');
    if (el && window.DataTable) {
      window.__productsDT = new DataTable(el, {
        pageLength: 100,
        lengthMenu: [[25, 50, 100, 200], [25, 50, 100, 200]],
        language: {
          search: 'Caută:',
          searchPlaceholder: 'Caută în tabel…',
          lengthMenu: 'Afișează _MENU_',
        }
      });
    }

    // Click oriunde pe rând -> intră în Proiect -> tab Produse (fără să strice link-urile).
    document.querySelectorAll('#productsTable tbody tr.js-row-link[data-href]').forEach(function (tr) {
      function go(e) {
        const t = (e && e.target) ? e.target : null;
        if (t && t.closest && t.closest('a,button,input,select,textarea,label,form')) return;
        const href = tr.getAttribute('data-href');
        if (href) window.location.href = href;
      }
      tr.addEventListener('click', go);
      tr.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          go(e);
        }
      });
    });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

