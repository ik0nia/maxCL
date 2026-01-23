<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);
$canSeePrices = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR], true);

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
    Nu ai drepturi pentru recepție marfă (doar Admin/Manager/Gestionar/Operator).
  </div>
<?php else: ?>
  <div class="card app-card p-3 mb-3">
    <form method="post" action="<?= htmlspecialchars(Url::to('/magazie/receptie/create')) ?>" class="row g-3" id="magazieReceptionForm">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

      <div class="col-12">
        <label class="form-label fw-semibold">Poziții recepție</label>
        <div class="table-responsive">
          <table class="table align-middle mb-0" id="magazieReceptionLines">
            <thead>
              <tr>
                <th style="width:220px">Cod WinMentor</th>
                <th>Denumire</th>
                <th class="text-end" style="width:140px">Cantitate</th>
                <th style="width:110px">U.M.</th>
                <th class="text-end" style="width:140px">Preț/unit</th>
                <th style="width:64px"></th>
              </tr>
            </thead>
            <tbody>
              <tr class="mag-line">
                <td>
                  <input class="form-control" name="winmentor_code[]" required maxlength="64" placeholder="ex: 8997...">
                </td>
                <td>
                  <input class="form-control" name="name[]" required maxlength="190" placeholder="ex: Șuruburi, balamale…">
                </td>
                <td class="text-end">
                  <input class="form-control text-end" type="number" name="qty[]" required min="0.001" step="0.001" value="1">
                </td>
                <td>
                  <input class="form-control" name="unit[]" required maxlength="16" value="buc" list="magazieUnits">
                </td>
                <td class="text-end">
                  <input class="form-control text-end" name="unit_price[]" required placeholder="0.00">
                </td>
                <td class="text-end">
                  <button class="btn btn-outline-secondary btn-sm mag-remove" type="button" title="Șterge rând">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-end mt-2">
          <button class="btn btn-outline-secondary" type="button" id="magazieAddLine">
            <i class="bi bi-plus-lg me-1"></i> Adaugă poziție
          </button>
        </div>
        <datalist id="magazieUnits">
          <option value="buc"></option>
          <option value="set"></option>
          <option value="m"></option>
          <option value="m2"></option>
          <option value="kg"></option>
          <option value="l"></option>
        </datalist>
        <div class="text-muted small mt-2">Unitatea este implicit <span class="fw-semibold">buc</span>, dar poate fi modificată pe fiecare linie.</div>
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

  <script>
    document.addEventListener('DOMContentLoaded', function(){
      const table = document.getElementById('magazieReceptionLines');
      const addBtn = document.getElementById('magazieAddLine');
      if (!table || !addBtn) return;

      function bindRemove(btn){
        btn.addEventListener('click', function(){
          const tr = btn.closest('tr');
          if (!tr) return;
          const body = table.querySelector('tbody');
          if (!body) return;
          const rows = body.querySelectorAll('tr.mag-line');
          if (rows.length <= 1) {
            // dacă e singurul rând, doar îl golim
            tr.querySelectorAll('input').forEach(function(inp){ inp.value = ''; });
            const qty = tr.querySelector('input[name="qty[]"]');
            if (qty) qty.value = '1';
            const unit = tr.querySelector('input[name="unit[]"]');
            if (unit) unit.value = 'buc';
            return;
          }
          tr.remove();
        });
      }

      table.querySelectorAll('.mag-remove').forEach(bindRemove);

      addBtn.addEventListener('click', function(){
        const body = table.querySelector('tbody');
        if (!body) return;
        const tpl = body.querySelector('tr.mag-line');
        if (!tpl) return;
        const tr = tpl.cloneNode(true);
        tr.querySelectorAll('input').forEach(function(inp){ inp.value = ''; });
        const qty = tr.querySelector('input[name="qty[]"]');
        if (qty) qty.value = '1';
        const unit = tr.querySelector('input[name="unit[]"]');
        if (unit) unit.value = 'buc';
        const rm = tr.querySelector('.mag-remove');
        if (rm) bindRemove(rm);
        body.appendChild(tr);
        const code = tr.querySelector('input[name="winmentor_code[]"]');
        if (code) code.focus();
      });
    });
  </script>
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
        <th>Accesoriu</th>
        <th class="text-end" style="width:140px">Cantitate</th>
        <?php if ($canSeePrices): ?>
          <th class="text-end" style="width:130px">Preț/unit</th>
        <?php endif; ?>
        <th style="width:160px">Proiect</th>
        <th style="width:200px">Produs proiect</th>
        <th>Notă</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $m): ?>
        <?php
          $dir = (string)($m['direction'] ?? '');
          $qty = (float)($m['qty'] ?? 0);
          $price = null;
          if (isset($m['unit_price']) && $m['unit_price'] !== null && $m['unit_price'] !== '' && is_numeric($m['unit_price'])) {
            $price = (float)$m['unit_price'];
          }
          $unit = (string)($m['item_unit'] ?? 'buc');
          $date = (string)($m['created_at'] ?? '');
        ?>
        <tr>
          <td class="text-muted"><?= htmlspecialchars($date) ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($dir) ?></td>
          <td class="fw-semibold"><?= htmlspecialchars((string)($m['winmentor_code'] ?? '')) ?></td>
          <?php
            $ppId = (int)($m['project_product_id'] ?? 0);
            $ppCode = trim((string)($m['project_product_code'] ?? ''));
            $ppName = trim((string)($m['project_product_name'] ?? ''));
            $ppLabel = trim($ppCode . ' · ' . $ppName);
            if ($ppLabel === '·' || $ppLabel === '· ') $ppLabel = $ppName !== '' ? $ppName : $ppCode;
            $projId = (int)($m['project_id'] ?? 0);
            $pcode = (string)($m['project_code_display'] ?? ($m['project_code'] ?? ''));
            $pname = (string)($m['project_name'] ?? '');
          ?>
          <td><?= htmlspecialchars((string)($m['item_name'] ?? '')) ?></td>
          <td class="text-end fw-semibold"><?= number_format((float)$qty, 3, '.', '') ?> <?= htmlspecialchars($unit) ?></td>
          <?php if ($canSeePrices): ?>
            <td class="text-end"><?= $price !== null ? number_format($price, 2, '.', '') : '—' ?></td>
          <?php endif; ?>
          <td>
            <?php if ($projId > 0 && $pname !== ''): ?>
              <a class="text-decoration-none fw-semibold" href="<?= htmlspecialchars(Url::to('/projects/' . $projId)) ?>">
                <?= htmlspecialchars($pname) ?>
              </a>
              <div class="text-muted small">#<?= htmlspecialchars($pcode !== '' ? $pcode : (string)$projId) ?></div>
            <?php elseif ($projId > 0): ?>
              <a class="text-decoration-none fw-semibold" href="<?= htmlspecialchars(Url::to('/projects/' . $projId)) ?>">
                Proiect #<?= htmlspecialchars((string)$projId) ?>
              </a>
              <div class="text-muted small">#<?= htmlspecialchars($pcode !== '' ? $pcode : (string)$projId) ?></div>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($ppId > 0 && $projId > 0): ?>
              <a class="text-decoration-none fw-semibold" href="<?= htmlspecialchars(Url::to('/projects/' . $projId . '?tab=products#pp-' . $ppId)) ?>">
                <?= htmlspecialchars($ppLabel !== '' ? $ppLabel : ('Produs #' . $ppId)) ?>
              </a>
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

