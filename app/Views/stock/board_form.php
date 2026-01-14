<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$isEdit = ($mode ?? '') === 'edit';
$action = $isEdit ? Url::to('/stock/boards/' . (int)($row['id'] ?? 0) . '/edit') : Url::to('/stock/boards/create');
$v = $row ?? [];
$errors = $errors ?? [];
$colors = $colors ?? [];
$textures = $textures ?? [];

ob_start();
$stdW0 = (int)($v['std_width_mm'] ?? 0);
$stdH0 = (int)($v['std_height_mm'] ?? 0);
$area0 = ($stdW0 > 0 && $stdH0 > 0) ? (($stdW0 * $stdH0) / 1000000.0) : 0.0;
$sale0 = $v['sale_price'] ?? '';
$sale0num = is_numeric(str_replace(',', '.', (string)$sale0)) ? (float)str_replace(',', '.', (string)$sale0) : null;
$ppm0 = ($sale0num !== null && $area0 > 0) ? ($sale0num / $area0) : null;
$finishMap = [];
foreach ($colors as $c) {
  $finishMap[(int)$c['id']] = $c;
}
$faceColorId0 = (int)($v['face_color_id'] ?? 0);
$backColorId0 = (int)($v['back_color_id'] ?? 0);
$faceOpt = $faceColorId0 && isset($finishMap[$faceColorId0]) ? $finishMap[$faceColorId0] : null;
$backOpt = $backColorId0 && isset($finishMap[$backColorId0]) ? $finishMap[$backColorId0] : null;
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0"><?= $isEdit ? 'Editează placă' : 'Placă nouă' ?></h1>
    <div class="text-muted">Alege culori + texturi pe fiecare față și setează dimensiunile standard</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-outline-secondary">Înapoi</a>
  </div>
</div>

<div class="card app-card p-4">
  <form method="post" action="<?= htmlspecialchars($action) ?>" class="row g-3">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="col-12 col-md-3">
      <label class="form-label">Cod *</label>
      <input class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" name="code" value="<?= htmlspecialchars((string)($v['code'] ?? '')) ?>" required>
      <?php if (isset($errors['code'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['code']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-5">
      <label class="form-label">Denumire *</label>
      <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" value="<?= htmlspecialchars((string)($v['name'] ?? '')) ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Brand *</label>
      <input class="form-control <?= isset($errors['brand']) ? 'is-invalid' : '' ?>" name="brand" value="<?= htmlspecialchars((string)($v['brand'] ?? '')) ?>" required>
      <?php if (isset($errors['brand'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['brand']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-2">
      <label class="form-label">Grosime (mm) *</label>
      <input type="number" min="1" class="form-control <?= isset($errors['thickness_mm']) ? 'is-invalid' : '' ?>" name="thickness_mm"
             value="<?= htmlspecialchars((string)($v['thickness_mm'] ?? '')) ?>" required>
      <?php if (isset($errors['thickness_mm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['thickness_mm']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label">Lungime standard (mm) *</label>
      <input type="number" min="1" class="form-control <?= isset($errors['std_height_mm']) ? 'is-invalid' : '' ?>" name="std_height_mm"
             value="<?= htmlspecialchars((string)($v['std_height_mm'] ?? '')) ?>" required>
      <?php if (isset($errors['std_height_mm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['std_height_mm']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label">Lățime standard (mm) *</label>
      <input type="number" min="1" class="form-control <?= isset($errors['std_width_mm']) ? 'is-invalid' : '' ?>" name="std_width_mm"
             value="<?= htmlspecialchars((string)($v['std_width_mm'] ?? '')) ?>" required>
      <?php if (isset($errors['std_width_mm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['std_width_mm']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-2">
      <label class="form-label">Preț vânzare (placă standard) (lei)</label>
      <input type="text"
             inputmode="decimal"
             class="form-control <?= isset($errors['sale_price']) ? 'is-invalid' : '' ?>"
             name="sale_price"
             id="sale_price"
             placeholder="ex: 350.00"
             value="<?= htmlspecialchars((string)($v['sale_price'] ?? '')) ?>">
      <?php if (isset($errors['sale_price'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['sale_price']) ?></div><?php endif; ?>
      <div class="form-text">Poți folosi și virgulă (ex: 350,00).</div>
    </div>

    <div class="col-12 col-md-2">
      <label class="form-label">Preț / mp (calculat)</label>
      <input type="text" class="form-control" id="sale_price_per_m2" value="<?= $ppm0 !== null ? htmlspecialchars(number_format((float)$ppm0, 2, '.', '')) : '' ?>" readonly>
      <div class="form-text">Calculat automat din dimensiunea standard.</div>
    </div>

    <div class="col-12 col-lg-8">
      <label class="form-label">Culoare față *</label>
      <input type="hidden" name="face_color_id" id="face_color_id" value="<?= htmlspecialchars((string)($v['face_color_id'] ?? '')) ?>">
      <div class="position-relative">
        <div class="input-group">
          <span class="input-group-text" style="width:54px;justify-content:center">
            <img id="face_color_thumb"
                 src="<?= htmlspecialchars((string)($faceOpt['thumb_path'] ?? '')) ?>"
                 alt=""
                 style="width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;<?= $faceOpt ? '' : 'display:none' ?>">
          </span>
          <input class="form-control <?= isset($errors['face_color_id']) ? 'is-invalid' : '' ?>"
                 id="face_color_q"
                 placeholder="Scrie codul… (ex: 1522)"
                 autocomplete="off"
                 value="<?= $faceOpt ? htmlspecialchars((string)$faceOpt['color_name'] . ' (' . (string)$faceOpt['code'] . ')') : '' ?>">
        </div>
        <?php if (isset($errors['face_color_id'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['face_color_id']) ?></div><?php endif; ?>
        <div class="app-ac-list" id="face_color_list" style="display:none"></div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label">Textură față *</label>
      <select class="form-select <?= isset($errors['face_texture_id']) ? 'is-invalid' : '' ?>" name="face_texture_id" id="face_texture_id" required>
        <option value="">Alege textură...</option>
        <?php foreach ($textures as $t): ?>
          <?php
            $id = (int)$t['id'];
            $sel = ((string)$id === (string)($v['face_texture_id'] ?? '')) ? 'selected' : '';
            $label = (string)$t['name'] . (!empty($t['code']) ? ' (' . (string)$t['code'] . ')' : '');
          ?>
          <option value="<?= $id ?>" <?= $sel ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['face_texture_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['face_texture_id']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-lg-8">
      <label class="form-label">Culoare verso (opțional)</label>
      <input type="hidden" name="back_color_id" id="back_color_id" value="<?= htmlspecialchars((string)($v['back_color_id'] ?? '')) ?>">
      <div class="position-relative">
        <div class="input-group">
          <span class="input-group-text" style="width:54px;justify-content:center">
            <img id="back_color_thumb"
                 src="<?= htmlspecialchars((string)($backOpt['thumb_path'] ?? '')) ?>"
                 alt=""
                 style="width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;<?= $backOpt ? '' : 'display:none' ?>">
          </span>
          <input class="form-control <?= isset($errors['back_color_id']) ? 'is-invalid' : '' ?>"
                 id="back_color_q"
                 placeholder="Scrie codul… (opțional)"
                 autocomplete="off"
                 value="<?= $backOpt ? htmlspecialchars((string)$backOpt['color_name'] . ' (' . (string)$backOpt['code'] . ')') : '' ?>">
          <button class="btn btn-outline-secondary" type="button" id="back_color_clear" title="Șterge">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <?php if (isset($errors['back_color_id'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['back_color_id']) ?></div><?php endif; ?>
        <div class="app-ac-list" id="back_color_list" style="display:none"></div>
      </div>
      <div class="form-text">Dacă lași gol, se consideră „Aceeași față/verso”.</div>
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label">Textură verso (opțional)</label>
      <select class="form-select <?= isset($errors['back_texture_id']) ? 'is-invalid' : '' ?>" name="back_texture_id" id="back_texture_id">
        <option value="">Aceeași față/verso</option>
        <?php foreach ($textures as $t): ?>
          <?php
            $id = (int)$t['id'];
            $sel = ((string)$id === (string)($v['back_texture_id'] ?? '')) ? 'selected' : '';
            $label = (string)$t['name'] . (!empty($t['code']) ? ' (' . (string)$t['code'] . ')' : '');
          ?>
          <option value="<?= $id ?>" <?= $sel ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['back_texture_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['back_texture_id']) ?></div><?php endif; ?>
    </div>

    <div class="col-12">
      <label class="form-label">Note</label>
      <input class="form-control" name="notes" value="<?= htmlspecialchars((string)($v['notes'] ?? '')) ?>">
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2 me-1"></i> Salvează
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/stock')) ?>">Renunță</a>
    </div>
  </form>
</div>

<style>
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
    const finishesEndpoint = <?= json_encode(Url::to('/api/finishes/search')) ?>;

    function debounce(fn, ms){
      let t = null;
      return function(){
        const args = arguments;
        if (t) window.clearTimeout(t);
        t = window.setTimeout(function(){ fn.apply(null, args); }, ms);
      };
    }

    async function fetchJson(url, params){
      // url vine deja cu basePath (Url::to), deci îl folosim direct
      let u = String(url || '');
      const qp = new URLSearchParams();
      Object.keys(params || {}).forEach(k => qp.set(k, String(params[k] ?? '')));
      const qs = qp.toString();
      if (qs) u += (u.indexOf('?') >= 0 ? '&' : '?') + qs;

      const res = await fetch(u, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });

      const ct = (res.headers.get('content-type') || '').toLowerCase();
      if (!ct.includes('application/json')) {
        throw new Error('non_json');
      }
      return await res.json();
    }

    function bindColorAutocomplete(opts){
      const qEl = document.querySelector(opts.q);
      const idEl = document.querySelector(opts.id);
      const thumbEl = document.querySelector(opts.thumb);
      const listEl = document.querySelector(opts.list);
      const allowEmpty = !!opts.allowEmpty;
      if (!qEl || !idEl || !thumbEl || !listEl) return;

      // Move dropdown to <body> to avoid overflow clipping
      if (!listEl.__acInBody) {
        document.body.appendChild(listEl);
        listEl.__acInBody = true;
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
        idEl.value = String(it.id || '');
        qEl.value = String(it.text || '');
        setThumb(it.thumb || '');
        hide();
      }

      function clearSelected(){
        idEl.value = '';
        qEl.value = '';
        setThumb('');
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
            img.src = it.thumb;
            row.appendChild(img);
          }
          const box = document.createElement('div');
          box.style.minWidth = '0';
          const t = document.createElement('div');
          t.className = 'app-ac-text';
          t.textContent = it.text || '';
          box.appendChild(t);
          row.appendChild(box);
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
        listEl.innerHTML = '<div class="app-ac-item"><div class="text-muted small">Se caută…</div></div>';
        show();
        try {
          const res = await fetchJson(finishesEndpoint, { q: q });
          if (!res || res.ok !== true) {
            let msg = (res && res.error) ? String(res.error) : 'Nu pot încărca sugestiile.';
            if (res && res.debug) msg += ' — ' + String(res.debug);
            listEl.innerHTML = '<div class="app-ac-item"><div class="text-muted small">' + msg.replace(/</g,'&lt;') + '</div></div>';
            show();
            return;
          }
          render(res.items || []);
        } catch (e) {
          // De obicei: redirect către /login (HTML) sau o eroare de rețea
          listEl.innerHTML = '<div class="app-ac-item"><div class="text-muted small">Nu pot încărca sugestiile. Verifică dacă ești autentificat.</div></div>';
          show();
        }
      }, 200);

      qEl.addEventListener('input', function(){
        idEl.value = '';
        setThumb('');
        doSearch();
      });
      qEl.addEventListener('focus', function(){
        const q = String(qEl.value || '').trim();
        if (q.length >= 1) doSearch();
      });
      qEl.addEventListener('blur', function(){
        window.setTimeout(hide, 150);
      });
      qEl.addEventListener('keydown', function(e){
        if (listEl.style.display !== 'block') return;
        const max = items.length - 1;
        if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(max, active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(0, active - 1); }
        else if (e.key === 'Escape') { e.preventDefault(); hide(); return; }
        else if (e.key === 'Enter') {
          if (active >= 0 && items[active]) { e.preventDefault(); setSelected(items[active]); }
          return;
        } else { return; }

        Array.from(listEl.querySelectorAll('.app-ac-item')).forEach(el => el.classList.remove('active'));
        const a = listEl.querySelector('.app-ac-item[data-idx="' + active + '"]');
        if (a) a.classList.add('active');
        show();
      });

      if (opts.clearBtn) {
        const btn = document.querySelector(opts.clearBtn);
        if (btn) {
          btn.addEventListener('click', function(){
            clearSelected();
            if (opts.onClear) opts.onClear();
            qEl.focus();
          });
        }
      }
    }

    // Texturi: Select2 dacă există jQuery + plugin, altfel rămâne select normal
    const $ = window.jQuery;
    if ($ && $.fn && $.fn.select2) {
      $('#face_texture_id').select2({ width: '100%' });
      $('#back_texture_id').select2({ width: '100%' });
    }

    bindColorAutocomplete({
      q: '#face_color_q',
      id: '#face_color_id',
      thumb: '#face_color_thumb',
      list: '#face_color_list',
      allowEmpty: false
    });

    bindColorAutocomplete({
      q: '#back_color_q',
      id: '#back_color_id',
      thumb: '#back_color_thumb',
      list: '#back_color_list',
      allowEmpty: true,
      clearBtn: '#back_color_clear',
      onClear: function(){
        const backTex = document.getElementById('back_texture_id');
        if (!backTex) return;
        backTex.value = '';
        if (window.jQuery) window.jQuery(backTex).trigger('change');
      }
    });

    function parseDec(v){
      v = String(v || '').trim().replace(',', '.');
      if (!v) return null;
      const n = Number(v);
      return Number.isFinite(n) ? n : null;
    }
    function recomputePrice(){
      const w = parseInt($('input[name="std_width_mm"]').val() || '0', 10);
      const h = parseInt($('input[name="std_height_mm"]').val() || '0', 10);
      const sp = parseDec($('#sale_price').val());
      const area = (w > 0 && h > 0) ? ((w * h) / 1000000.0) : 0;
      if (sp === null || area <= 0) {
        $('#sale_price_per_m2').val('');
        return;
      }
      const ppm = sp / area;
      $('#sale_price_per_m2').val(ppm.toFixed(2));
    }
    // Price / mp (vanilla listeners)
    const saleEl = document.getElementById('sale_price');
    const wEl = document.querySelector('input[name="std_width_mm"]');
    const hEl = document.querySelector('input[name="std_height_mm"]');
    if (saleEl) saleEl.addEventListener('input', recomputePrice);
    if (wEl) wEl.addEventListener('input', recomputePrice);
    if (hEl) hEl.addEventListener('input', recomputePrice);
    recomputePrice();
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

