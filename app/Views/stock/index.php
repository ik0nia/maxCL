<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
$canSeePrices = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
$rows = $rows ?? [];
$filterColor = $filterColor ?? null;
$filterColorQuery = (string)($filterColorQuery ?? '');
$filterThicknessMm = $filterThicknessMm ?? null;
$thicknessOptions = $thicknessOptions ?? [];

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
    <?php if ($canWrite): ?>
      <div class="d-flex align-items-center gap-3 flex-wrap">
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

<div class="card app-card p-3 mb-3">
  <form method="get" action="<?= htmlspecialchars(Url::to('/stock')) ?>" class="row g-2 align-items-start">
    <div class="col-12 col-lg-7 app-stock-filter-color">
      <label class="form-label mb-1">Culoare (față sau verso)</label>
      <div class="input-group position-relative">
        <span class="input-group-text" style="width:54px;justify-content:center">
          <?php $fcThumb = is_array($filterColor) ? (string)($filterColor['thumb_path'] ?? '') : ''; ?>
          <img id="stock_filter_color_thumb"
               src="<?= htmlspecialchars($fcThumb) ?>"
               alt=""
               style="width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;<?= $fcThumb !== '' ? '' : 'display:none' ?>">
        </span>
        <input class="form-control"
               id="stock_filter_color_q"
               name="color"
               placeholder="Scrie codul… (ex: 617)"
               autocomplete="off"
               value="<?= htmlspecialchars($filterColorQuery !== '' ? $filterColorQuery : (is_array($filterColor) ? (string)($filterColor['code'] ?? '') : '')) ?>">
        <button class="btn btn-outline-secondary" type="button" id="stock_filter_color_clear" title="Șterge">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="app-ac-list" id="stock_filter_color_list" style="display:none"></div>
      <div class="form-text">Caută după cod (autocomplete) și filtrează plăcile care au culoarea pe față sau pe verso.</div>
    </div>

    <div class="col-12 col-lg-2 app-stock-filter-thickness">
      <label class="form-label mb-1">Grosime</label>
      <select class="form-select" name="thickness_mm">
        <option value="">Toate grosimile</option>
        <?php foreach ($thicknessOptions as $th): ?>
          <?php $sel = ((string)(int)$th === (string)(int)($filterThicknessMm ?? 0)) ? 'selected' : ''; ?>
          <option value="<?= (int)$th ?>" <?= $sel ?>><?= (int)$th ?> mm</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-lg-3 app-stock-filter-actions d-flex gap-2">
      <button class="btn btn-primary flex-grow-1" type="submit">
        <i class="bi bi-funnel me-1"></i> Filtrează
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/stock')) ?>">
        Resetează
      </a>
    </div>
  </form>
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
        <th>Cod WinMentor</th>
        <th>Denumire</th>
        <th>Grosime</th>
        <th>Dim. standard</th>
        <th class="text-end">Stoc FULL (buc)</th>
        <th class="text-end">Stoc OFFCUT (buc)</th>
        <th class="text-end">Stoc (mp)</th>
        <?php if ($canSeePrices): ?>
          <th class="text-end js-price-col d-none">Preț/mp</th>
          <th class="text-end js-price-col d-none">Valoare (lei)</th>
        <?php endif; ?>
        <th class="text-end" style="width:220px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
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
          <td class="fw-semibold"><?= htmlspecialchars((string)$r['code']) ?></td>
          <td><?= htmlspecialchars((string)$r['name']) ?></td>
          <td><?= (int)$r['thickness_mm'] ?> mm</td>
          <td><?= (int)$r['std_width_mm'] ?> × <?= (int)$r['std_height_mm'] ?> mm</td>
          <td class="text-end fw-semibold"><?= (int)($r['stock_qty_full_available'] ?? 0) ?></td>
          <td class="text-end fw-semibold"><?= (int)($r['stock_qty_offcut_available'] ?? 0) ?></td>
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
            <td class="text-end js-price-col d-none"><?= $ppm !== null ? number_format((float)$ppm, 2, '.', '') : '—' ?></td>
            <td class="text-end fw-semibold js-price-col d-none"><?= $val !== null ? number_format((float)$val, 2, '.', '') : '—' ?></td>
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
  /* Fine-tuning pentru bara de filtre */
  .app-stock-filter-color .form-text{margin-bottom:0}
  .app-stock-filter-actions{align-items:center}
  .app-title-switch{display:flex;align-items:center;gap:.35rem}
  .app-title-switch .form-check-input{margin-top:0}
  .app-title-switch .form-check-label{font-weight:600;color:#5F6B72}
  @media (max-width: 991.98px){
    .app-stock-filter-actions .btn{flex:1 1 auto;}
  }
  .app-ac-list{
    position:fixed;
    z-index: 2000;
    background: #fff;
    border: 1px solid #D9E3E6;
    border-radius: 14px;
    box-shadow: 0 10px 24px rgba(17,17,17,0.08);
    max-height: 320px;
    overflow: auto;
  }
  .app-ac-item{
    display:flex;
    align-items:center;
    gap:10px;
    padding:10px 12px;
    cursor:pointer;
    border-bottom: 1px solid #EEF3F5;
  }
  .app-ac-item:last-child{border-bottom:0}
  .app-ac-item:hover, .app-ac-item.active{background:#F3F7F8}
  .app-ac-thumb{
    width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;flex:0 0 auto;
  }
  .app-ac-text{font-weight:600;color:#111}
  .app-ac-sub{font-size:.85rem;color:#5F6B72}
</style>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('boardsTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25 });
  });
</script>

<?php if ($canWrite): ?>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    const tPrices = document.getElementById('stockTogglePrices');
    const tAdmin = document.getElementById('stockToggleAdmin');
    const priceCells = Array.from(document.querySelectorAll('.js-price-col'));
    const adminCells = Array.from(document.querySelectorAll('.js-admin-actions'));
    const valueCard = document.getElementById('stockValueCard');

    function setVisible(list, on){
      list.forEach(el => {
        if (!el) return;
        el.classList.toggle('d-none', !on);
      });
    }

    function apply(){
      const showPrices = !!(tPrices && tPrices.checked);
      const showAdmin = !!(tAdmin && tAdmin.checked);
      setVisible(priceCells, showPrices);
      if (valueCard) valueCard.classList.toggle('d-none', !showPrices);
      setVisible(adminCells, showAdmin);
      try {
        localStorage.setItem('stock_show_prices', showPrices ? '1' : '0');
        localStorage.setItem('stock_show_admin', showAdmin ? '1' : '0');
      } catch (e) {}
    }

    // default OFF, dar dacă userul a activat anterior, reținem în localStorage
    try {
      if (tPrices) tPrices.checked = (localStorage.getItem('stock_show_prices') === '1');
      if (tAdmin) tAdmin.checked = (localStorage.getItem('stock_show_admin') === '1');
    } catch (e) {
      if (tPrices) tPrices.checked = false;
      if (tAdmin) tAdmin.checked = false;
    }

    if (tPrices) tPrices.addEventListener('change', apply);
    if (tAdmin) tAdmin.addEventListener('change', apply);
    apply();
  });
</script>
<?php endif; ?>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const finishesEndpoint = <?= json_encode(Url::to('/api/finishes/search')) ?>;
    const qEl = document.getElementById('stock_filter_color_q');
    const listEl = document.getElementById('stock_filter_color_list');
    const thumbEl = document.getElementById('stock_filter_color_thumb');
    const clearBtn = document.getElementById('stock_filter_color_clear');
    if (!qEl || !listEl || !thumbEl) return;

    // dropdown în <body> (evită overflow clipping)
    if (!listEl.__acInBody) {
      document.body.appendChild(listEl);
      listEl.__acInBody = true;
    }

    function debounce(fn, ms){
      let t = null;
      return function(){
        const args = arguments;
        if (t) window.clearTimeout(t);
        t = window.setTimeout(function(){ fn.apply(null, args); }, ms);
      };
    }

    async function fetchJson(url, params){
      let u = String(url || '');
      const qp = new URLSearchParams();
      Object.keys(params || {}).forEach(k => qp.set(k, String(params[k] ?? '')));
      const qs = qp.toString();
      if (qs) u += (u.indexOf('?') >= 0 ? '&' : '?') + qs;

      const res = await fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'application/json' }});
      const ct = (res.headers.get('content-type') || '').toLowerCase();
      if (!ct.includes('application/json')) throw new Error('non_json');
      return await res.json();
    }

    let items = [];
    let active = -1;

    function place(){
      const r = qEl.getBoundingClientRect();
      listEl.style.top = (r.bottom + 6) + 'px';
      listEl.style.left = r.left + 'px';
      listEl.style.width = r.width + 'px';
    }
    function hide(){
      listEl.style.display = 'none';
      listEl.innerHTML = '';
      active = -1;
      items = [];
      window.removeEventListener('scroll', place, true);
      window.removeEventListener('resize', place, true);
    }
    function show(){
      if (!listEl.children.length) return;
      place();
      listEl.style.display = 'block';
      window.addEventListener('scroll', place, true);
      window.addEventListener('resize', place, true);
    }
    function setThumb(url){
      if (url) {
        thumbEl.setAttribute('src', url);
        thumbEl.style.display = '';
      } else {
        thumbEl.style.display = 'none';
      }
    }
    function setSelected(it){
      const code = String(it.code || '').trim();
      qEl.value = code !== '' ? code : String(it.text || '');
      setThumb(it.thumb || '');
      hide();
    }

    function render(resItems){
      items = Array.isArray(resItems) ? resItems : [];
      listEl.innerHTML = '';
      if (!items.length) {
        const row = document.createElement('div');
        row.className = 'app-ac-item';
        const muted = document.createElement('div');
        muted.className = 'text-muted small';
        muted.textContent = 'Nimic găsit.';
        row.appendChild(muted);
        listEl.appendChild(row);
        show();
        return;
      }
      items.forEach(function(it, idx){
        const row = document.createElement('div');
        row.className = 'app-ac-item';
        row.dataset.idx = String(idx);
        if (it.thumb) {
          const img = document.createElement('img');
          img.className = 'app-ac-thumb';
          img.src = String(it.thumb);
          img.alt = '';
          row.appendChild(img);
        } else {
          const ph = document.createElement('div');
          ph.className = 'app-ac-thumb';
          ph.style.background = '#F3F7F8';
          row.appendChild(ph);
        }
        const wrap = document.createElement('div');
        const t = document.createElement('div');
        t.className = 'app-ac-text';
        t.textContent = String(it.code || it.text || '—');
        const s = document.createElement('div');
        s.className = 'app-ac-sub';
        s.textContent = String(it.name || '');
        wrap.appendChild(t);
        if (s.textContent.trim() !== '') wrap.appendChild(s);
        row.appendChild(wrap);
        row.addEventListener('mousedown', function(e){
          e.preventDefault();
          setSelected(it);
        });
        listEl.appendChild(row);
      });
      show();
    }

    const doSearch = debounce(async function(){
      const q = String(qEl.value || '').trim();
      if (q.length < 1) { hide(); return; }
      try {
        const data = await fetchJson(finishesEndpoint, { q: q, limit: 20 });
        render(data && data.items ? data.items : []);
      } catch (e) {
        hide();
      }
    }, 200);

    qEl.addEventListener('input', doSearch);
    qEl.addEventListener('focus', doSearch);
    document.addEventListener('click', function(ev){
      if (ev.target === qEl) return;
      if (listEl.contains(ev.target)) return;
      hide();
    });

    qEl.addEventListener('keydown', function(ev){
      if (listEl.style.display !== 'block') return;
      const max = items.length - 1;
      if (ev.key === 'ArrowDown') { ev.preventDefault(); active = Math.min(max, active + 1); }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); active = Math.max(0, active - 1); }
      else if (ev.key === 'Enter') {
        if (active >= 0 && items[active]) { ev.preventDefault(); setSelected(items[active]); }
        hide();
      } else if (ev.key === 'Escape') { hide(); }
      const els = listEl.querySelectorAll('.app-ac-item');
      els.forEach(function(el, i){
        if (i === active) el.classList.add('active');
        else el.classList.remove('active');
      });
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', function(){
        qEl.value = '';
        setThumb('');
        hide();
      });
    }
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

