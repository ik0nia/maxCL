<?php
use App\Core\View;

ob_start();
$message = $message ?? 'Acest modul este în lucru.';
?>
<div class="card app-card p-4">
  <div class="h5 m-0"><?= htmlspecialchars((string)($title ?? 'Modul')) ?></div>
  <div class="text-muted mt-1"><?= htmlspecialchars((string)$message) ?></div>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

