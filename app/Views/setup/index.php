<?php
use App\Core\View;
use App\Core\Csrf;

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Instalare / Setup</h1>
    <div class="text-muted">Instalează baza de date și adminul inițial (MVP)</div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-7">
    <div class="card app-card p-4">
      <div class="h5">Pasul 1: Rulează schema</div>
      <div class="text-muted">Rulează `database/schema.sql` și creează userul admin dacă nu există.</div>
      <div class="mt-3">
        <form method="post" action="/setup/run" class="m-0">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-database-check me-1"></i> Instalează acum
          </button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-5">
    <div class="card app-card p-4">
      <div class="h5">Admin inițial</div>
      <ul class="mb-0">
        <li>Email: <code>admin@local</code></li>
        <li>Parolă: <code>admin123</code></li>
      </ul>
      <div class="text-muted mt-2">După login, schimbă parola imediat.</div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

