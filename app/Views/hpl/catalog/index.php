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
  (function () {
    var $ = window.jQuery;
    if (!$) return;
    var $input = $('#hplCatalogSearch');
    var $clear = $('#hplCatalogClear');
    var $grid = $('#hplCatalogGrid');
    var $loading = $('#hplCatalogLoading');
    var endpoint = $grid.data('endpoint');
    var timer = null;
    var lastQ = null;
    var req = null;

    function setLoading(on){ $loading.css('display', on ? 'block' : 'none'); }

    function load(q) {
      if (!endpoint) return;
      if (req && req.abort) req.abort();
      setLoading(true);
      req = $.getJSON(endpoint, { q: q || '' })
        .done(function (res) {
          if (!res || res.ok !== true) {
            $grid.html('<div class="text-muted">Nu am putut încărca catalogul.</div>');
            return;
          }
          $grid.html(res.html || '');
        })
        .fail(function (xhr, statusText) {
          if (statusText === 'abort') return;
          $grid.html('<div class="text-muted">Nu am putut încărca catalogul.</div>');
        })
        .always(function () { setLoading(false); });
    }

    $input.on('input', function () {
      var q = String($input.val() || '');
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        if (q === lastQ) return;
        lastQ = q;
        load(q);
      }, 250);
    });

    $clear.on('click', function () {
      $input.val('');
      $input.trigger('input');
      $input.focus();
    });
  })();
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

