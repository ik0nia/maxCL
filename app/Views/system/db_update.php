<?php
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;

$plan = $plan ?? [];
$resJson = Session::flash('db_update_result');
$res = null;
if (is_string($resJson) && $resJson !== '') {
  $tmp = json_decode($resJson, true);
  if (is_array($tmp)) $res = $tmp;
}

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Update DB</h1>
    <div class="text-muted">Rulează migrările doar când facem update-uri care schimbă structura bazei de date.</div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card app-card p-3">
      <div class="h5 m-0">Ce verificăm</div>
      <div class="text-muted">Dacă lipsește ceva, update-ul îl va crea.</div>

      <ul class="mt-3 mb-0">
        <?php foreach ($plan as $p): ?>
          <li class="<?= !empty($p['needed']) ? 'fw-semibold' : 'text-muted' ?>">
            <?= htmlspecialchars((string)$p['label']) ?>
            <?php if (!empty($p['needed'])): ?>
              <span class="badge app-badge ms-2">lipsește</span>
            <?php else: ?>
              <span class="badge app-badge ms-2">ok</span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>

      <form method="post" action="<?= htmlspecialchars(Url::to('/system/db-update/run')) ?>" class="mt-3"
            onsubmit="return confirm('Rulez update DB? (se aplică doar schimbările lipsă)');">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-database-check me-1"></i> Rulează update DB
        </button>
      </form>
      <div class="text-muted small mt-2">
        Când să îl rulezi: **după un update din Git** în care am adăugat tabele/coloane noi (ex: „preț vânzare”, „clienți”, „texturi”, etc).
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card app-card p-3">
      <div class="h5 m-0">Ultimul rezultat</div>
      <div class="text-muted">Se completează după ce apeși „Rulează update DB”.</div>

      <?php if (!$res): ?>
        <div class="text-muted mt-3">Nu există un rezultat încă.</div>
      <?php else: ?>
        <div class="mt-3">
          <div class="fw-semibold">Aplicate</div>
          <ul class="mb-2">
            <?php foreach (($res['applied'] ?? []) as $x): ?>
              <li><?= htmlspecialchars((string)$x) ?></li>
            <?php endforeach; ?>
            <?php if (empty($res['applied'])): ?><li class="text-muted">Nimic (deja la zi).</li><?php endif; ?>
          </ul>

          <div class="fw-semibold">Sărite</div>
          <ul class="mb-2">
            <?php foreach (($res['skipped'] ?? []) as $x): ?>
              <li class="text-muted"><?= htmlspecialchars((string)$x) ?></li>
            <?php endforeach; ?>
          </ul>

          <div class="fw-semibold">Erori</div>
          <ul class="mb-0">
            <?php foreach (($res['errors'] ?? []) as $x): ?>
              <li class="text-danger"><?= htmlspecialchars((string)$x) ?></li>
            <?php endforeach; ?>
            <?php if (empty($res['errors'])): ?><li class="text-muted">Nicio eroare.</li><?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

