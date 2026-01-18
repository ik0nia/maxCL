<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$snapshots = is_array($snapshots ?? null) ? $snapshots : [];
$canExec = (bool)($canExec ?? false);
$hasDump = (bool)($hasDump ?? false);
$hasMysql = (bool)($hasMysql ?? false);

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Setări admin</h1>
    <div class="text-muted">Funcții avansate pentru administrare</div>
  </div>
</div>

<div class="card app-card p-3 mb-3">
  <div class="h5 m-0">Time machine DB</div>
  <div class="text-muted">Creează snapshot-uri și revino la un punct anterior</div>

  <?php if (!$canExec): ?>
    <div class="alert alert-warning mt-2 mb-0">Funcțiile exec sunt dezactivate pe server. Time machine nu poate rula.</div>
  <?php else: ?>
    <?php if (!$hasDump): ?>
      <div class="alert alert-warning mt-2 mb-0">`mysqldump` nu este disponibil pe server.</div>
    <?php endif; ?>
    <?php if (!$hasMysql): ?>
      <div class="alert alert-warning mt-2 mb-0">`mysql` client nu este disponibil pe server.</div>
    <?php endif; ?>
  <?php endif; ?>

  <form method="post" action="<?= htmlspecialchars(Url::to('/system/admin-settings/snapshot/create')) ?>" class="mt-3">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <button class="btn btn-primary" type="submit" <?= (!$hasDump ? 'disabled' : '') ?>>
      <i class="bi bi-clock-history me-1"></i> Creează snapshot
    </button>
  </form>
</div>

<div class="card app-card p-3">
  <div class="h5 m-0">Snapshot-uri</div>
  <div class="text-muted">Restaurarea va suprascrie baza de date curentă</div>

  <?php if (!$snapshots): ?>
    <div class="text-muted mt-2">Nu există snapshot-uri încă.</div>
  <?php else: ?>
    <div class="table-responsive mt-2">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Fișier</th>
            <th>Dată</th>
            <th class="text-end">Mărime</th>
            <th class="text-end">Acțiuni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($snapshots as $s): ?>
            <?php
              $name = (string)($s['name'] ?? '');
              $ts = (int)($s['mtime'] ?? 0);
              $size = (int)($s['size'] ?? 0);
            ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars($name) ?></td>
              <td class="text-muted"><?= htmlspecialchars($ts > 0 ? date('Y-m-d H:i:s', $ts) : '—') ?></td>
              <td class="text-end text-muted"><?= htmlspecialchars($size > 0 ? number_format($size / 1024, 2, '.', '') . ' KB' : '—') ?></td>
              <td class="text-end">
                <form method="post" action="<?= htmlspecialchars(Url::to('/system/admin-settings/snapshot/restore')) ?>" class="d-inline"
                      onsubmit="return confirm('Restaurezi snapshot-ul? Baza de date curentă va fi suprascrisă.');">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                  <input type="hidden" name="snapshot" value="<?= htmlspecialchars($name) ?>">
                  <button class="btn btn-outline-secondary btn-sm" type="submit" <?= (!$hasMysql ? 'disabled' : '') ?>>
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Restaurează
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));
