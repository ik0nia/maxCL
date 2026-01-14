<?php
use App\Core\Url;
use App\Core\View;

$finishes = $finishes ?? [];
$stockByFinish = $stockByFinish ?? [];
$totalByFinish = $totalByFinish ?? [];

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Catalog</h1>
    <div class="text-muted">Toate Tip-urile de culori + stoc disponibil pe grosimi</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/hpl/tip-culoare')) ?>" class="btn btn-outline-secondary">Gestionează Tip culoare</a>
  </div>
</div>

<div class="card app-card p-3 mb-3">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="input-group" style="max-width:460px">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input type="text" class="form-control" id="hplCatalogSearch" placeholder="Caută după cod / culoare / cod culoare" autocomplete="off">
      <button class="btn btn-outline-secondary" type="button" id="hplCatalogClear" title="Șterge">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" id="hplCatalogToggleInStock">
        <label class="form-check-label" for="hplCatalogToggleInStock" style="font-weight:600;color:#5F6B72">Doar cu stoc</label>
      </div>
      <div class="text-muted small" id="hplCatalogLoading" style="display:none">
        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
        Se caută…
      </div>
    </div>
  </div>
</div>

<div id="hplCatalogGrid" data-endpoint="<?= htmlspecialchars(Url::to('/api/hpl/catalog')) ?>">
  <?= View::render('hpl/catalog/_grid', compact('finishes', 'stockByFinish', 'totalByFinish')) ?>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const input = document.getElementById('hplCatalogSearch');
    const clear = document.getElementById('hplCatalogClear');
    const tStock = document.getElementById('hplCatalogToggleInStock');
    const grid = document.getElementById('hplCatalogGrid');
    const loading = document.getElementById('hplCatalogLoading');
    const endpoint = grid ? (grid.getAttribute('data-endpoint') || '') : '';
    if (!input || !grid || !loading || !endpoint) return;

    let timer = null;
    let lastKey = null;
    let ctrl = null;

    function setLoading(on){
      loading.style.display = on ? 'block' : 'none';
    }

    async function fetchJson(url, params){
      let u = String(url || '');
      const qp = new URLSearchParams();
      Object.keys(params || {}).forEach(k => qp.set(k, String(params[k] ?? '')));
      const qs = qp.toString();
      if (qs) u += (u.indexOf('?') >= 0 ? '&' : '?') + qs;

      if (ctrl) ctrl.abort();
      ctrl = new AbortController();

      const res = await fetch(u, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        signal: ctrl.signal
      });

      const ct = (res.headers.get('content-type') || '').toLowerCase();
      if (!ct.includes('application/json')) {
        const txt = await res.text();
        const snip = String(txt || '').slice(0, 240).replace(/\s+/g, ' ').trim();
        throw new Error('non_json:' + res.status + ':' + snip);
      }
      const data = await res.json();
      if (!res.ok) {
        const dbg = (data && (data.debug || data.error)) ? String(data.debug || data.error) : '';
        throw new Error('http_' + res.status + (dbg ? (': ' + dbg) : ''));
      }
      return data;
    }

    async function load(q){
      setLoading(true);
      try {
        const inStock = !!(tStock && tStock.checked);
        const data = await fetchJson(endpoint, { q: q || '', in_stock: inStock ? '1' : '0' });
        if (!data || data.ok !== true) {
          const extra = data && (data.debug || data.error) ? ('<div class="text-muted small mt-1">' + String(data.debug || data.error) + '</div>') : '';
          grid.innerHTML = '<div class="text-muted">Nu am putut încărca catalogul.</div>' + extra;
          return;
        }
        grid.innerHTML = String(data.html || '');
      } catch (e) {
        if (e && String(e.name || '') === 'AbortError') return;
        const msg = e && e.message ? String(e.message) : '';
        const extra = msg ? ('<div class="text-muted small mt-1">' + msg.replace(/</g,'&lt;') + '</div>') : '';
        grid.innerHTML = '<div class="text-muted">Nu am putut încărca catalogul.</div>' + extra;
      } finally {
        setLoading(false);
      }
    }

    // persist toggle in localStorage (default OFF)
    try {
      if (tStock) tStock.checked = (localStorage.getItem('hpl_catalog_in_stock') === '1');
    } catch (e) {}

    input.addEventListener('input', function(){
      const q = String(input.value || '');
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(function(){
        const inStock = !!(tStock && tStock.checked);
        const key = q + '|' + (inStock ? '1' : '0');
        if (key === lastKey) return;
        lastKey = key;
        load(q);
      }, 250);
    });

    if (clear) {
      clear.addEventListener('click', function(){
        input.value = '';
        input.dispatchEvent(new Event('input'));
        input.focus();
      });
    }

    if (tStock) {
      tStock.addEventListener('change', function(){
        try { localStorage.setItem('hpl_catalog_in_stock', tStock.checked ? '1' : '0'); } catch (e) {}
        // Force reload even if input value didn't change
        lastKey = null;
        load(String(input.value || ''));
      });
      // if ON from localStorage, trigger first load to apply filter
      if (tStock.checked) {
        lastKey = null;
        load(String(input.value || ''));
      }
    }
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

