<?php
use App\Core\View;

ob_start();
?>
<div class="row g-3">
  <div class="col-12">
    <div class="app-page-title">
      <div>
        <h1 class="m-0">Panou</h1>
        <div class="text-muted">Privire de ansamblu (MVP)</div>
      </div>
      <div class="d-flex gap-2">
        <a href="/stock" class="btn btn-primary"><i class="bi bi-box-seam me-1"></i> Stoc</a>
        <a href="/projects" class="btn btn-outline-secondary"><i class="bi bi-kanban me-1"></i> Proiecte</a>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card app-card p-3">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted">Setup</div>
          <div class="h4 m-0">Instalare DB</div>
        </div>
        <a href="/setup" class="btn btn-outline-secondary">Deschide</a>
      </div>
      <div class="text-muted mt-2">Instalează tabelele și creează adminul inițial.</div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card app-card p-3">
      <div class="h5 m-0">Module MVP</div>
      <div class="text-muted mt-1">Catalog, stoc, clienți, proiecte, consum (deviz), rapoarte (minim), jurnal.</div>
      <div class="mt-3 d-flex flex-wrap gap-2">
        <span class="badge app-badge">UI în română</span>
        <span class="badge app-badge">Accent #6FA94A</span>
        <span class="badge app-badge">AJAX /api/stock/search</span>
        <span class="badge app-badge">Audit log</span>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

