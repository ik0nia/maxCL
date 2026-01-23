<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR], true);
$canSeePrices = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR], true);
$isAdmin = $u && (string)$u['role'] === Auth::ROLE_ADMIN;
$rows = $rows ?? [];
$filterColor = $filterColor ?? null;
$filterColorQuery = (string)($filterColorQuery ?? '');
$filterThicknessMm = $filterThicknessMm ?? null;
$thicknessOptions = $thicknessOptions ?? [];

$exportParams = [];
if ($filterColorQuery !== '') $exportParams['color'] = $filterColorQuery;
if ($filterThicknessMm !== null && (int)$filterThicknessMm > 0) $exportParams['thickness_mm'] = (int)$filterThicknessMm;
$exportQuery = $exportParams ? ('&' . http_build_query($exportParams)) : '';
$exportCsvUrl = Url::to('/stock/export?format=csv' . $exportQuery);
$exportXlsUrl = Url::to('/stock/export?format=xls' . $exportQuery);

// Valoare stoc (lei) — vizibilă doar la cerere (toggle), pentru Admin/Gestionar
$totalValueLei = 0.0;
if ($canSeePrices) {
  foreach ($rows as $r) {
    $m2 = (float)($r['stock_m2_available'] ?? 0);
    if ($m2 <= 0) continue;
    $ppm = null;
    if (isset($r['sale_price_per_m2']) && $r['sale_price_per_m2'] !== null && $r['sale_price_per_m2'] !== '' && is_numeric($r['sale_price_per_m2'])) {
      $ppm = (float)$r['sale_price_per_m2'];
    } elseif (isset($r['sale_price']) && $r['sale_price'] !== null && $r['sale_price'] !== '' && is_numeric($r['sale_price'])) {
      $stdW = (int)($r['std_width_mm'] ?? 0);
      $stdH = (int)($r['std_height_mm'] ?? 0);
      $area = ($stdW > 0 && $stdH > 0) ? (($stdW * $stdH) / 1000000.0) : 0.0;
      if ($area > 0) $ppm = ((float)$r['sale_price']) / $area;
    }
    if ($ppm !== null && $ppm >= 0 && is_finite($ppm)) {
      $totalValueLei += ($m2 * $ppm);
    }
  }
}

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Stoc</h1>
    <div class="text-muted">Catalog plăci + total buc/mp (disponibil)</div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($isAdmin): ?>
      <form method="post" action="<?= htmlspecialchars(Url::to('/stock/mentor-sync')) ?>" class="d-inline">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <button class="btn btn-outline-success" type="submit"
                onclick="return confirm('Actualizezi stocurile din WinMentor?');">
          <i class="bi bi-cloud-download me-1"></i> Actualizează Stocuri din Mentor
        </button>
      </form>
    <?php endif; ?>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <a href="<?= htmlspecialchars($exportCsvUrl) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-filetype-csv me-1"></i> Export CSV
      </a>
      <a href="<?= htmlspecialchars($exportXlsUrl) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export XLS
      </a>
    </div>
    <?php if ($canWrite): ?>
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="form-check form-switch m-0 app-title-switch">
          <input class="form-check-input" type="checkbox" id="stockToggleWmCode">
          <label class="form-check-label" for="stockToggleWmCode">Cod WinMentor</label>
        </div>
        <div class="form-check form-switch m-0 app-title-switch">
          <input class="form-check-input" type="checkbox" id="stockTogglePrices">
          <label class="form-check-label" for="stockTogglePrices">Afișare prețuri</label>
        </div>
        <div class="form-check form-switch m-0 app-title-switch">
          <input class="form-check-input" type="checkbox" id="stockToggleAdmin">
          <label class="form-check-label" for="stockToggleAdmin">Administrare</label>
        </div>
      </div>
      <a href="<?= htmlspecialchars(Url::to('/stock/boards/create')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Placă nouă
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (is_array($filterColor) || ($filterThicknessMm !== null && (int)$filterThicknessMm > 0)): ?>
  <div class="card app-card p-3 mb-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <div class="fw-semibold">Filtre active</div>
        <div class="text-muted">
          <?php if (is_array($filterColor)): ?>
            Culoare (față sau verso): <strong><?= htmlspecialchars((string)($filterColor['code'] ?? '—')) ?></strong> · <?= htmlspecialchars((string)($filterColor['color_name'] ?? '')) ?>
            <?php if (!empty($filterColor['color_code'])): ?> (<?= htmlspecialchars((string)$filterColor['color_code']) ?>)<?php endif; ?>
          <?php endif; ?>
          <?php if ($filterThicknessMm !== null && (int)$filterThicknessMm > 0): ?>
            <?php if (is_array($filterColor)): ?> · <?php endif; ?>
            Grosime: <strong><?= (int)$filterThicknessMm ?> mm</strong>
          <?php endif; ?>
        </div>
      </div>
      <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-x-lg me-1"></i> Șterge filtru
      </a>
    </div>
  </div>
<?php endif; ?>

<?php if ($canSeePrices): ?>
  <div class="row g-3 mb-3 d-none" id="stockValueCard">
    <div class="col-12">
      <div class="card app-card p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="h5 m-0">Valoare stoc (disponibil)</div>
            <div class="text-muted">Calcul: mp disponibili × preț/mp (din prețul setat pe placa standard)</div>
          </div>
          <div class="text-end">
            <div class="text-muted small">Total general</div>
            <div class="fw-semibold" style="font-size:1.35rem;line-height:1.1">
              <?= number_format((float)$totalValueLei, 2, '.', '') ?> lei
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="boardsTable">
    <thead>
      <tr>
        <th style="width:110px">Preview</th>
        <th class="js-wmcode-col">Cod WinMentor</th>
        <th>Denumire</th>
        <th>Grosime</th>
        <th>Dim. standard</th>
        <th class="text-end">Stoc FULL (buc)</th>
        <th class="text-end">Stoc OFFCUT (buc)</th>
        <th class="text-end">Stoc pt WinMentor</th>
        <?php if ($isAdmin): ?>
          <th class="text-end js-mentor-col">Stoc Actual Mentor</th>
        <?php endif; ?>
        <th class="text-end">Stoc (mp)</th>
        <?php if ($canSeePrices): ?>
          <th class="text-end js-price-col">Preț/mp</th>
          <th class="text-end js-price-col">Valoare (lei)</th>
        <?php endif; ?>
        <th class="text-end" style="width:220px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="js-row-link" data-href="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$r['id'])) ?>" role="button" tabindex="0">
          <td>
            <div class="d-flex gap-2">
              <?php
                $faceThumb = (string)$r['face_thumb_path'];
                $backThumb = (string)($r['back_thumb_path'] ?? '');
                $faceCode = (string)($r['face_color_code'] ?? '');
                $backCode = (string)($r['back_color_code'] ?? '');

                $faceBigRaw = (string)($r['face_image_path'] ?? '') ?: $faceThumb;
                $backBigRaw = (string)($r['back_image_path'] ?? '') ?: $backThumb;

                // Normalizează rutele vechi (ex: /uploads/... fără /public)
                $faceBig = (str_starts_with($faceBigRaw, '/uploads/')) ? Url::to($faceBigRaw) : $faceBigRaw;
                $backBig = (str_starts_with($backBigRaw, '/uploads/')) ? Url::to($backBigRaw) : $backBigRaw;
              ?>
              <div class="text-center" style="min-width:52px">
                <a href="#"
                   data-bs-toggle="modal" data-bs-target="#appLightbox"
                   data-lightbox-src="<?= htmlspecialchars($faceBig) ?>"
                   data-lightbox-title="<?= htmlspecialchars((string)$r['face_color_name']) ?>"
                   data-lightbox-fallback="<?= htmlspecialchars($faceThumb) ?>"
                   style="display:inline-block;cursor:zoom-in">
                  <img src="<?= htmlspecialchars($faceThumb) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
                </a>
                <?php if (trim($faceCode) !== ''): ?>
                  <div class="text-muted" style="font-size:.75rem;line-height:1.05;margin-top:2px; font-weight:600">
                    <?= htmlspecialchars($faceCode) ?>
                  </div>
                <?php endif; ?>
              </div>
              <?php if (!empty($r['back_thumb_path'])): ?>
                <div class="text-center" style="min-width:52px">
                  <a href="#"
                     data-bs-toggle="modal" data-bs-target="#appLightbox"
                     data-lightbox-src="<?= htmlspecialchars($backBig) ?>"
                     data-lightbox-title="<?= htmlspecialchars((string)$r['back_color_name']) ?>"
                     data-lightbox-fallback="<?= htmlspecialchars($backThumb) ?>"
                     style="display:inline-block;cursor:zoom-in">
                    <img src="<?= htmlspecialchars($backThumb) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
                  </a>
                  <?php if (trim($backCode) !== ''): ?>
                    <div class="text-muted" style="font-size:.75rem;line-height:1.05;margin-top:2px; font-weight:600">
                      <?= htmlspecialchars($backCode) ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </td>
          <td class="fw-semibold js-wmcode-col"><?= htmlspecialchars((string)$r['code']) ?></td>
          <td><?= htmlspecialchars((string)$r['name']) ?></td>
          <td><?= (int)$r['thickness_mm'] ?> mm</td>
          <td><?= (int)$r['std_height_mm'] ?> × <?= (int)$r['std_width_mm'] ?> mm</td>
          <?php
            $fullQty = (float)($r['stock_qty_full_available'] ?? 0);
            $offcutQty = (float)($r['stock_qty_offcut_available'] ?? 0);
            $wmStock = $fullQty + ($offcutQty * 0.5);
            $wmStockTxt = number_format((float)$wmStock, 2, '.', '');
          ?>
          <td class="text-end fw-semibold"><?= (int)$fullQty ?></td>
          <td class="text-end fw-semibold"><?= (int)$offcutQty ?></td>
          <td class="text-end fw-semibold"><?= htmlspecialchars($wmStockTxt) ?></td>
          <?php if ($isAdmin): ?>
            <?php
              $mentorStock = $r['mentor_stock'] ?? null;
              $mentorTxt = ($mentorStock !== null && $mentorStock !== '' && is_numeric($mentorStock))
                ? number_format((float)$mentorStock, 2, '.', '')
                : '—';
            ?>
            <td class="text-end fw-semibold js-mentor-col"><?= htmlspecialchars($mentorTxt) ?></td>
          <?php endif; ?>
          <td class="text-end fw-semibold"><?= number_format((float)$r['stock_m2_available'], 2, '.', '') ?></td>
          <?php if ($canSeePrices): ?>
            <?php
              $m2 = (float)($r['stock_m2_available'] ?? 0);
              $ppm = null;
              if (isset($r['sale_price_per_m2']) && $r['sale_price_per_m2'] !== null && $r['sale_price_per_m2'] !== '' && is_numeric($r['sale_price_per_m2'])) {
                $ppm = (float)$r['sale_price_per_m2'];
              } elseif (isset($r['sale_price']) && $r['sale_price'] !== null && $r['sale_price'] !== '' && is_numeric($r['sale_price'])) {
                $stdW = (int)($r['std_width_mm'] ?? 0);
                $stdH = (int)($r['std_height_mm'] ?? 0);
                $area = ($stdW > 0 && $stdH > 0) ? (($stdW * $stdH) / 1000000.0) : 0.0;
                if ($area > 0) $ppm = ((float)$r['sale_price']) / $area;
              }
              $val = ($ppm !== null && $ppm >= 0 && is_finite($ppm) && $m2 > 0) ? ($m2 * $ppm) : null;
            ?>
            <td class="text-end js-price-col"><?= $ppm !== null ? number_format((float)$ppm, 2, '.', '') : '—' ?></td>
            <td class="text-end fw-semibold js-price-col"><?= $val !== null ? number_format((float)$val, 2, '.', '') : '—' ?></td>
          <?php endif; ?>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$r['id'])) ?>">
              <i class="bi bi-eye me-1"></i> Vezi
            </a>
            <?php if ($canWrite): ?>
              <span class="js-admin-actions d-none">
                <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$r['id'] . '/edit')) ?>">
                  <i class="bi bi-pencil me-1"></i> Editează
                </a>
                <form method="post" action="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$r['id'] . '/delete')) ?>" class="d-inline"
                      onsubmit="return confirm('Sigur vrei să ștergi această placă? (doar dacă nu are piese asociate)');">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                  <button class="btn btn-outline-secondary btn-sm" type="submit">
                    <i class="bi bi-trash me-1"></i> Șterge
                  </button>
                </form>
              </span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<style>
  .app-title-switch{display:flex;align-items:center;gap:.35rem}
  .app-title-switch .form-check-input{margin-top:0}
  .app-title-switch .form-check-label{font-weight:600;color:#5F6B72}

  /* DataTables: search mai vizibil */
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
  #boardsTable tbody tr.js-row-link{cursor:pointer}
  @media (max-width: 991.98px){
    .dt-search{width:100%}
    .dt-search input{min-width:0;width:100%}
  }
</style>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('boardsTable');
    if (el && window.DataTable) {
      window.__stockBoardsDT = new DataTable(el, {
        pageLength: 100,
        lengthMenu: [[25, 50, 100, 200], [25, 50, 100, 200]],
        language: {
          search: 'Caută:',
          searchPlaceholder: 'Caută în coloanele vizibile…',
          lengthMenu: 'Afișează _MENU_',
        }
      });
    }

    // Click oriunde pe rând -> intră pe pagina plăcii (dar thumbnailurile rămân deschise în lightbox).
    document.querySelectorAll('#boardsTable tbody tr.js-row-link[data-href]').forEach(function (tr) {
      function go(e) {
        const t = (e && e.target) ? e.target : null;
        // Ignoră click pe elemente interactive (inclusiv link-ul de thumbnail / acțiuni)
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

<?php if ($canWrite): ?>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    const tWm = document.getElementById('stockToggleWmCode');
    const tPrices = document.getElementById('stockTogglePrices');
    const tAdmin = document.getElementById('stockToggleAdmin');
    const adminCells = Array.from(document.querySelectorAll('.js-admin-actions'));
    const valueCard = document.getElementById('stockValueCard');
    const dt = window.__stockBoardsDT || null;

    // Column indices (DataTables) – calculate from header (admin-only columns exist)
    const headerCells = Array.from(document.querySelectorAll('#boardsTable thead th'));
    const IDX_WM = headerCells.findIndex(th => th.classList.contains('js-wmcode-col'));
    const PRICE_IDXS = headerCells.reduce((acc, th, idx) => {
      if (th.classList.contains('js-price-col')) acc.push(idx);
      return acc;
    }, []);

    function apply(){
      const showWm = !!(tWm && tWm.checked);
      const showPrices = !!(tPrices && tPrices.checked);
      const showAdmin = !!(tAdmin && tAdmin.checked);

      // Prefer DataTables visibility so columns reflow nicely.
      if (dt && dt.column) {
        try { if (IDX_WM >= 0) dt.column(IDX_WM).visible(showWm, false); } catch (e) {}
        try {
          // price columns exist only for Admin/Gestionar (canSeePrices=true)
          PRICE_IDXS.forEach(idx => { if (idx >= 0) dt.column(idx).visible(showPrices, false); });
        } catch (e) {}
      } else {
        // Fallback without DataTables
        document.querySelectorAll('.js-wmcode-col').forEach(el => el.classList.toggle('d-none', !showWm));
        document.querySelectorAll('.js-price-col').forEach(el => el.classList.toggle('d-none', !showPrices));
      }

      if (valueCard) valueCard.classList.toggle('d-none', !showPrices);
      adminCells.forEach(el => el.classList.toggle('d-none', !showAdmin));
      try {
        localStorage.setItem('stock_show_wmcode', showWm ? '1' : '0');
        localStorage.setItem('stock_show_prices', showPrices ? '1' : '0');
        localStorage.setItem('stock_show_admin', showAdmin ? '1' : '0');
      } catch (e) {}

      try {
        if (dt && dt.columns && dt.columns.adjust) dt.columns.adjust().draw(false);
      } catch (e) {}
    }

    // default OFF, dar dacă userul a activat anterior, reținem în localStorage
    try {
      if (tWm) tWm.checked = (localStorage.getItem('stock_show_wmcode') === '1');
      if (tPrices) tPrices.checked = (localStorage.getItem('stock_show_prices') === '1');
      if (tAdmin) tAdmin.checked = (localStorage.getItem('stock_show_admin') === '1');
    } catch (e) {
      if (tWm) tWm.checked = false;
      if (tPrices) tPrices.checked = false;
      if (tAdmin) tAdmin.checked = false;
    }

    if (tWm) tWm.addEventListener('change', apply);
    if (tPrices) tPrices.addEventListener('change', apply);
    if (tAdmin) tAdmin.addEventListener('change', apply);
    apply();
  });
</script>
<?php endif; ?>

<script>
  // Search doar în coloanele vizibile (DataTables).
  document.addEventListener('DOMContentLoaded', function(){
    const dt = window.__stockBoardsDT || null;
    const table = document.getElementById('boardsTable');
    if (!dt || !table) return;

    // Avoid double-install if this view is ever re-rendered.
    if (window.__stockVisibleColsSearchInstalled) return;
    window.__stockVisibleColsSearchInstalled = true;

    const extSearch = (window.DataTable && window.DataTable.ext && window.DataTable.ext.search)
      ? window.DataTable.ext.search
      : null;
    if (!extSearch || !Array.isArray(extSearch)) return;

    extSearch.push(function(settings, data){
      // Scope to this table only
      try {
        if (!settings || settings.nTable !== table) return true;
      } catch (e) {}

      let term = '';
      try { term = String(dt.search() || '').trim().toLowerCase(); } catch (e) { term = ''; }
      if (!term) return true;

      const colCount = (dt.columns && dt.columns().count) ? dt.columns().count() : (Array.isArray(data) ? data.length : 0);
      const lastIdx = Math.max(0, colCount - 1); // Acțiuni
      // Excludem Preview (0) și Acțiuni (last) din căutarea “logică”
      for (let i = 1; i < lastIdx; i++) {
        try {
          if (dt.column && dt.column(i) && dt.column(i).visible && dt.column(i).visible() !== true) continue;
        } catch (e) {}
        const cell = String((Array.isArray(data) && data[i] !== undefined) ? data[i] : '');
        if (cell.toLowerCase().includes(term)) return true;
      }
      return false;
    });

    // Ensure filter runs on first draw
    try { dt.draw(false); } catch (e) {}
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

