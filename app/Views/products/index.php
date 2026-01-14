<?php
use App\Core\Url;
use App\Core\View;

$rows = $rows ?? [];
$q = trim((string)($q ?? ''));

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Produse</h1>
    <div class="text-muted">Produsele sunt piesele folosite în proiecte</div>
  </div>
</div>

<div class="card app-card p-3 mb-3">
  <form method="get" action="<?= htmlspecialchars(Url::to('/products')) ?>" class="d-flex gap-2 flex-wrap align-items-end">
    <div style="min-width:320px;flex:1">
      <label class="form-label fw-semibold mb-1">Caută</label>
      <input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cod sau nume…">
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
        <th style="width:160px">Cod</th>
        <th>Denumire</th>
        <th style="width:160px">Dimensiuni</th>
        <th style="width:160px">Creat</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars((string)($r['code'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['name'] ?? '')) ?></td>
          <td class="text-muted">
            <?php
              $w = $r['width_mm'] ?? null;
              $h = $r['height_mm'] ?? null;
              if ($w && $h) echo (int)$h . '×' . (int)$w . ' mm';
              else echo '—';
            ?>
          </td>
          <td class="text-muted"><?= htmlspecialchars((string)($r['created_at'] ?? '')) ?></td>
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
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

