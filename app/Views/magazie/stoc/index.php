<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR], true);
$canConsume = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);
$canSeePrices = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR], true);
$canDelete = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER], true);

$items = $items ?? [];
$q = trim((string)($q ?? ''));

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Stoc Magazie</h1>
    <div class="text-muted">Accesorii (Cod WinMentor, bucăți, preț/buc)</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/magazie/receptie')) ?>" class="btn btn-primary">
      <i class="bi bi-truck me-1"></i> Recepție marfă
    </a>
  </div>
</div>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="magazieStockTable">
    <thead>
      <tr>
        <th style="width:160px">Cod WinMentor</th>
        <th>Denumire</th>
        <th class="text-end" style="width:110px">Bucăți</th>
        <?php if ($canSeePrices): ?>
          <th class="text-end" style="width:130px">Preț/buc</th>
        <?php endif; ?>
        <th class="text-end" style="width:360px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <?php
          $id = (int)($it['id'] ?? 0);
          $qty = (float)($it['stock_qty'] ?? 0);
          $price = null;
          if (isset($it['unit_price']) && $it['unit_price'] !== null && $it['unit_price'] !== '' && is_numeric($it['unit_price'])) {
            $price = (float)$it['unit_price'];
          }
        ?>
        <tr>
          <td class="fw-semibold">
            <a href="<?= htmlspecialchars(Url::to('/magazie/stoc/' . $id)) ?>" class="text-decoration-none">
              <?= htmlspecialchars((string)($it['winmentor_code'] ?? '')) ?>
            </a>
          </td>
          <td>
            <a href="<?= htmlspecialchars(Url::to('/magazie/stoc/' . $id)) ?>" class="text-decoration-none">
              <?= htmlspecialchars((string)($it['name'] ?? '')) ?>
            </a>
          </td>
          <td class="text-end fw-semibold"><?= number_format((float)$qty, 3, '.', '') ?></td>
          <?php if ($canSeePrices): ?>
            <td class="text-end"><?= $price !== null ? number_format($price, 2, '.', '') : '—' ?></td>
          <?php endif; ?>
          <td class="text-end">
            <?php if ($canConsume): ?>
              <form method="post" action="<?= htmlspecialchars(Url::to('/magazie/stoc/' . $id . '/consume')) ?>" class="d-inline-flex gap-2 align-items-center flex-wrap justify-content-end">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="number" step="0.001" min="0.001" max="<?= max(0.001, $qty) ?>" name="qty" value="1" class="form-control form-control-sm" style="width:96px" <?= $qty <= 0 ? 'disabled' : '' ?>>
                <input type="text" name="project_code" class="form-control form-control-sm" style="width:160px"
                       placeholder="Cod proiect…" <?= $qty <= 0 ? 'disabled' : '' ?>>
                <button class="btn btn-outline-secondary btn-sm" type="submit" <?= $qty <= 0 ? 'disabled' : '' ?>
                        onclick="return confirm('Scazi ' + this.form.qty.value + ' buc din stoc pentru proiectul ' + (this.form.project_code.value || '—') + '?');">
                  <i class="bi bi-dash-lg me-1"></i> Scade
                </button>
              </form>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
            <?php if ($canDelete): ?>
              <form method="post" action="<?= htmlspecialchars(Url::to('/magazie/stoc/' . $id . '/delete')) ?>" class="d-inline ms-2"
                    onsubmit="return confirm('Ștergi definitiv produsul din Magazie? Vor fi șterse și mișcările/consumurile asociate.');">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button class="btn btn-outline-danger btn-sm" type="submit">
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
    const el = document.getElementById('magazieStockTable');
    if (el && window.DataTable) {
      window.__magazieStockDT = new DataTable(el, {
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

