<?php
use App\Core\View;

ob_start();
?>
<div class="card app-card p-4">
  <div class="h5 m-0"><?= htmlspecialchars((string)($title ?? 'Modul')) ?></div>
  <div class="text-muted mt-1">Acest modul este în lucru. Catalogul este funcțional (Finisaje/Materiale/Variante).</div>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

