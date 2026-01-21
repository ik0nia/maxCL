<?php
use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canSeePrices = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR], true);

$item = $item ?? [];
$movements = $movements ?? [];

$qty = (float)($item['stock_qty'] ?? 0);
$price = null;
if (isset($item['unit_price']) && $item['unit_price'] !== null && $item['unit_price'] !== '' && is_numeric($item['unit_price'])) {
  $price = (float)$item['unit_price'];
}
$unit = (string)($item['unit'] ?? 'buc');

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Produs Magazie</h1>
    <div class="text-muted">
      <?= htmlspecialchars((string)($item['winmentor_code'] ?? '')) ?> · <?= htmlspecialchars((string)($item['name'] ?? '')) ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/magazie/stoc')) ?>" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Înapoi la stoc
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card app-card p-3">
      <div class="h5 m-0">Stoc curent</div>
      <div class="text-muted">Informații produs</div>
      <div class="mt-3">
        <div class="text-muted small">Bucăți</div>
        <div class="fw-semibold" style="font-size:1.15rem">
          <?= number_format((float)$qty, 3, '.', '') ?> <?= htmlspecialchars($unit) ?>
        </div>
      </div>
      <?php if ($canSeePrices): ?>
        <div class="mt-3">
          <div class="text-muted small">Preț/unit</div>
          <div class="fw-semibold">
            <?= $price !== null ? number_format($price, 2, '.', '') : '—' ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card app-card p-3">
      <div class="h5 m-0">Istoric mișcări (IN/OUT)</div>
      <div class="text-muted">Cine / când / proiect / notă</div>

      <div class="table-responsive mt-2">
        <table class="table table-hover align-middle mb-0" id="magazieItemHistoryTable">
          <thead>
            <tr>
              <th style="width:170px">Dată</th>
              <th style="width:70px">Tip</th>
              <th class="text-end" style="width:120px">Cant</th>
              <?php if ($canSeePrices): ?>
                <th class="text-end" style="width:130px">Preț/unit</th>
              <?php endif; ?>
              <th style="width:220px">Proiect</th>
              <th style="width:200px">User</th>
              <th>Notă</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($movements as $m): ?>
              <?php
                $dir = (string)($m['direction'] ?? '');
                $mqty = (float)($m['qty'] ?? 0);
                $mprice = null;
                if (isset($m['unit_price']) && $m['unit_price'] !== null && $m['unit_price'] !== '' && is_numeric($m['unit_price'])) {
                  $mprice = (float)$m['unit_price'];
                }
                $pcode = (string)($m['project_code_display'] ?? ($m['project_code'] ?? ''));
                $pname = (string)($m['project_name'] ?? '');
                $uname = (string)($m['user_name'] ?? '');
                $uemail = (string)($m['user_email'] ?? '');
              ?>
              <tr>
                <td class="text-muted"><?= htmlspecialchars((string)($m['created_at'] ?? '')) ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($dir) ?></td>
                <td class="text-end fw-semibold"><?= number_format((float)$mqty, 3, '.', '') ?> <?= htmlspecialchars($unit) ?></td>
                <?php if ($canSeePrices): ?>
                  <td class="text-end"><?= $mprice !== null ? number_format($mprice, 2, '.', '') : '—' ?></td>
                <?php endif; ?>
                <td>
                  <?php if ($pcode !== '' && $pname !== ''): ?>
                    <div class="fw-semibold"><?= htmlspecialchars($pcode) ?></div>
                    <div class="text-muted small" style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                      <?= htmlspecialchars($pname) ?>
                    </div>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($uname !== '' || $uemail !== ''): ?>
                    <div class="fw-semibold"><?= htmlspecialchars($uname !== '' ? $uname : $uemail) ?></div>
                    <?php if ($uname !== '' && $uemail !== ''): ?>
                      <div class="text-muted small"><?= htmlspecialchars($uemail) ?></div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-muted"><?= htmlspecialchars((string)($m['note'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
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
    const el = document.getElementById('magazieItemHistoryTable');
    if (el && window.DataTable) {
      window.__magazieItemHistoryDT = new DataTable(el, {
        pageLength: 50,
        lengthMenu: [[25, 50, 100, 200], [25, 50, 100, 200]],
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

