<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$snapshots = is_array($snapshots ?? null) ? $snapshots : [];
$companySettings = is_array($companySettings ?? null) ? $companySettings : [];
$canExec = (bool)($canExec ?? false);
$hasDump = (bool)($hasDump ?? false);
$hasMysql = (bool)($hasMysql ?? false);
$hasPhpDump = (bool)($hasPhpDump ?? false);
$hasPhpRestore = (bool)($hasPhpRestore ?? false);
$isWritable = (bool)($isWritable ?? false);
$canCreate = $isWritable && ($hasDump || $hasPhpDump);
$canRestore = ($hasMysql || $hasPhpRestore);
$companyName = (string)($companySettings['company_name'] ?? '');
$companyCui = (string)($companySettings['company_cui'] ?? '');
$companyReg = (string)($companySettings['company_reg'] ?? '');
$companyAddress = (string)($companySettings['company_address'] ?? '');
$companyPhone = (string)($companySettings['company_phone'] ?? '');
$companyEmail = (string)($companySettings['company_email'] ?? '');
$contactName = (string)($companySettings['company_contact_name'] ?? '');
$contactPhone = (string)($companySettings['company_contact_phone'] ?? '');
$contactEmail = (string)($companySettings['company_contact_email'] ?? '');
$contactPosition = (string)($companySettings['company_contact_position'] ?? '');
$logoUrl = (string)($companySettings['company_logo_thumb_url'] ?? $companySettings['company_logo_url'] ?? '');

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Setări admin</h1>
    <div class="text-muted">Funcții avansate pentru administrare</div>
  </div>
</div>

<div class="card app-card p-3 mb-3">
  <div class="h5 m-0">Date firmă</div>
  <div class="text-muted">Datele firmei care produce produsele și logo pentru documente</div>

  <form method="post" action="<?= htmlspecialchars(Url::to('/system/admin-settings/company/update')) ?>" class="row g-2 mt-2" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <div class="col-12 col-md-6">
      <label class="form-label fw-semibold mb-1">Nume firmă</label>
      <input class="form-control" name="company_name" value="<?= htmlspecialchars($companyName) ?>" placeholder="ex: MaxCL SRL">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold mb-1">CUI</label>
      <input class="form-control" name="company_cui" value="<?= htmlspecialchars($companyCui) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold mb-1">Nr. Reg. Com.</label>
      <input class="form-control" name="company_reg" value="<?= htmlspecialchars($companyReg) ?>">
    </div>
    <div class="col-12">
      <label class="form-label fw-semibold mb-1">Adresă firmă</label>
      <textarea class="form-control" name="company_address" rows="2" placeholder="Adresă completă"><?= htmlspecialchars($companyAddress) ?></textarea>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label fw-semibold mb-1">Telefon firmă</label>
      <input class="form-control" name="company_phone" value="<?= htmlspecialchars($companyPhone) ?>">
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label fw-semibold mb-1">Email firmă</label>
      <input class="form-control" type="email" name="company_email" value="<?= htmlspecialchars($companyEmail) ?>">
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label fw-semibold mb-1">Funcție contact</label>
      <input class="form-control" name="company_contact_position" value="<?= htmlspecialchars($contactPosition) ?>" placeholder="ex: Administrator">
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label fw-semibold mb-1">Persoană contact</label>
      <input class="form-control" name="company_contact_name" value="<?= htmlspecialchars($contactName) ?>">
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label fw-semibold mb-1">Telefon contact</label>
      <input class="form-control" name="company_contact_phone" value="<?= htmlspecialchars($contactPhone) ?>">
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label fw-semibold mb-1">Email contact</label>
      <input class="form-control" type="email" name="company_contact_email" value="<?= htmlspecialchars($contactEmail) ?>">
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label fw-semibold mb-1">Logo firmă</label>
      <input class="form-control" type="file" name="company_logo" accept="image/png,image/jpeg,image/webp">
      <div class="text-muted small mt-1">PNG/JPG/WEBP, se generează automat un thumbnail.</div>
    </div>
    <div class="col-12 col-md-6 d-flex align-items-end">
      <?php if ($logoUrl !== ''): ?>
        <div class="d-flex align-items-center gap-2">
          <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="height:48px;width:auto;border:1px solid #D9E3E6;border-radius:8px;background:#fff;padding:4px">
          <div class="text-muted small">Logo curent</div>
        </div>
      <?php else: ?>
        <div class="text-muted small">Nu există logo încă.</div>
      <?php endif; ?>
    </div>
    <div class="col-12 d-flex justify-content-end">
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-save me-1"></i> Salvează date firmă
      </button>
    </div>
  </form>
</div>

<div class="card app-card p-3 mb-3">
  <div class="h5 m-0">Time machine DB</div>
  <div class="text-muted">Creează snapshot-uri și revino la un punct anterior</div>

  <?php if (!$isWritable): ?>
    <div class="alert alert-warning mt-2 mb-0">Directorul `storage/db_backups` nu este accesibil la scriere.</div>
  <?php endif; ?>
  <?php if (!$canExec): ?>
    <div class="alert alert-info mt-2 mb-0">Funcțiile exec sunt dezactivate pe server. Se folosește fallback PHP (mai lent).</div>
  <?php else: ?>
    <?php if (!$hasDump): ?>
      <div class="alert alert-warning mt-2 mb-0">`mysqldump` nu este disponibil pe server. Se folosește fallback PHP.</div>
    <?php endif; ?>
    <?php if (!$hasMysql): ?>
      <div class="alert alert-warning mt-2 mb-0">`mysql` client nu este disponibil pe server. Restaurarea folosește fallback PHP.</div>
    <?php endif; ?>
  <?php endif; ?>

  <form method="post" action="<?= htmlspecialchars(Url::to('/system/admin-settings/snapshot/create')) ?>" class="mt-3">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <button class="btn btn-primary" type="submit" <?= (!$canCreate ? 'disabled' : '') ?>>
      <i class="bi bi-clock-history me-1"></i> Creează snapshot
    </button>
  </form>
</div>

<div class="card app-card p-3 mb-3">
  <div class="h5 m-0">Indexare căutare globală</div>
  <div class="text-muted">Regenerează indexul pentru căutarea din bara de sus</div>
  <form method="post" action="<?= htmlspecialchars(Url::to('/system/admin-settings/search-index/rebuild')) ?>" class="mt-3"
        onsubmit="return confirm('Regenerezi indexul de căutare?');">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <button class="btn btn-outline-secondary" type="submit">
      <i class="bi bi-arrow-repeat me-1"></i> Reindexează
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
                  <button class="btn btn-outline-secondary btn-sm" type="submit" <?= (!$canRestore ? 'disabled' : '') ?>>
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
