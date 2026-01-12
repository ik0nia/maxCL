<?php
use App\Core\View;
ob_start();
?>
<div class="card app-card p-4">
  <div class="h3 m-0">Acces interzis</div>
  <div class="text-muted">Nu ai permisiuni pentru această acțiune.</div>
  <div class="mt-3">
    <a href="/" class="btn btn-primary">Înapoi la Panou</a>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

