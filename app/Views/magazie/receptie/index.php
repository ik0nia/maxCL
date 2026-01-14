<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
$canSeePrices = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);

$recent = $recent ?? [];

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Recepție marfă</h1>
    <div class="text-muted">Adaugă produse și crește stocul de accesorii</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/magazie/stoc')) ?>" class="btn btn-outline-secondary">
      <i class="bi bi-box-seam me-1"></i> Stoc Magazie
    </a>
  </div>
</div>

<?php if (!$canWrite): ?>
  <div class="alert alert-warning">
    Nu ai drepturi pentru recepție marfă (doar Admin/Gestionar).
  </div>
<?php else: ?>
  <div class="card app-card p-3 mb-3">
    <form method="post" action="<?= htmlspecialchars(Url::to('/magazie/receptie/create')) ?>" class="row g-3">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

      <div class="col-12 col-md-3">
        <label class="form-label fw-semibold">Cod WinMentor</label>
        <input class="form-control" name="winmentor_code" required maxlength="64" placeholder="ex: ACC-123">
      </div>
      <div class="col-12 col-md-5">
        <label class="form-label fw-semibold">Denumire</label>
        <input class="form-control" name="name" required maxlength="190" placeholder="ex: Balamale, șuruburi…">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Bucăți</label>
        <input class="form-control" type="number" name="qty" required min="1" step="1" value="1">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Preț/buc</label>
        <input class="form-control" name="unit_price" required placeholder="0.00">
      </div>

      <div class="col-12">
        <label class="form-label fw-semibold">Notă (opțional)</label>
        <input class="form-control" name="note" maxlength="255" placeholder="ex: factură, furnizor…">
      </div>

      <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-plus-lg me-1"></i> Salvează recepția
        </button>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="card app-card p-3">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
    <div class="fw-semibold">Istoric recent</div>
    <div class="text-muted small">Ultimele mișcări (IN/OUT)</div>
  </div>
  <table class="table table-hover align-middle mb-0" id="magazieHistoryTable">
    <thead>
      <tr>
        <th style="width:170px">Dată</th>
        <th style="width:70px">Tip</th>
        <th style="width:160px">Cod WinMentor</th>
        <th>Produs</th>
        <th class="text-end" style="width:110px">Bucăți</th>
        <?php if ($canSeePrices): ?>
          <th class="text-end" style="width:130px">Preț/buc</th>
        <?php endif; ?>
        <th style="width:160px">Proiect</th>
        <th>Notă</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $m): ?>
        <?php
          $dir = (string)($m['direction'] ?? '');
          $qty = (int)($m['qty'] ?? 0);
          $price = null;
          if (isset($m['unit_price']) && $m['unit_price'] !== null && $m['unit_price'] !== '' && is_numeric($m['unit_price'])) {
            $price = (float)$m['unit_price'];
          }
          $date = (string)($m['created_at'] ?? '');
        ?>
        <tr>
          <td class="text-muted"><?= htmlspecialchars($date) ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($dir) ?></td>
          <td class="fw-semibold"><?= htmlspecialchars((string)($m['winmentor_code'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($m['item_name'] ?? '')) ?></td>
          <td class="text-end fw-semibold"><?= $qty ?></td>
          <?php if ($canSeePrices): ?>
            <td class="text-end"><?= $price !== null ? number_format($price, 2, '.', '') : '—' ?></td>
          <?php endif; ?>
          <td><?= htmlspecialchars((string)($m['project_code'] ?? '')) ?></td>
          <td class="text-muted"><?= htmlspecialchars((string)($m['note'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<style>
  .dt-search{display:flex;align-items:center;gap:.5rem}
  .dt-search label{font-weight:700;color:#111;margin:0}
  .dt-search input{
    min-width: 360px;
    border-radius: 14px;
    border: 1px solid #D9E3E6;
    padding: 10px 12px;
    outline: none;
  }
  .dt-search input:focus{
    border-color:#6FA94A;
    box-shadow: 0 0 0 .2rem rgba(111,169,74,.15);
  }
  @media (max-width: 991.98px){
    .dt-search{width:100%}
    .dt-search input{min-width:0;width:100%}
  }
</style>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('magazieHistoryTable');
    if (el && window.DataTable) {
      window.__magazieHistoryDT = new DataTable(el, {
        pageLength: 50,
        lengthMenu: [[25, 50, 100], [25, 50, 100]],
        order: [[0, 'desc']],
        language: {
          search: 'Caută:',
          searchPlaceholder: 'Caută în istoric…',
          lengthMenu: 'Afișează _MENU_',
        }
      });
    }
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

