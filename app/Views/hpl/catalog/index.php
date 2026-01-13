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
    <div class="text-muted small" id="hplCatalogLoading" style="display:none">
      <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
      Se caută…
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
    const grid = document.getElementById('hplCatalogGrid');
    const loading = document.getElementById('hplCatalogLoading');
    const endpoint = grid ? (grid.getAttribute('data-endpoint') || '') : '';
    if (!input || !grid || !loading || !endpoint) return;

    let timer = null;
    let lastQ = null;
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
      if (!ct.includes('application/json')) throw new Error('non_json');
      return await res.json();
    }

    async function load(q){
      setLoading(true);
      try {
        const data = await fetchJson(endpoint, { q: q || '' });
        if (!data || data.ok !== true) {
          grid.innerHTML = '<div class="text-muted">Nu am putut încărca catalogul.</div>';
          return;
        }
        grid.innerHTML = String(data.html || '');
      } catch (e) {
        if (e && String(e.name || '') === 'AbortError') return;
        grid.innerHTML = '<div class="text-muted">Nu am putut încărca catalogul.</div>';
      } finally {
        setLoading(false);
      }
    }

    input.addEventListener('input', function(){
      const q = String(input.value || '');
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(function(){
        if (q === lastQ) return;
        lastQ = q;
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
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

