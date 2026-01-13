<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
$isAdmin = $u && (string)$u['role'] === Auth::ROLE_ADMIN;

$row = $row ?? [];
$projects = $projects ?? [];

$type = (string)($row['type'] ?? '');
$typeLabel = $type === 'FIRMA' ? 'Firmă' : 'Persoană fizică';

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Client</h1>
    <div class="text-muted"><?= htmlspecialchars((string)($row['name'] ?? '')) ?> · <?= htmlspecialchars($typeLabel) ?></div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/clients')) ?>" class="btn btn-outline-secondary">Înapoi</a>
    <?php if ($canWrite): ?>
      <a href="<?= htmlspecialchars(Url::to('/clients/' . (int)$row['id'] . '/edit')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-pencil me-1"></i> Editează
      </a>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
      <form method="post" action="<?= htmlspecialchars(Url::to('/clients/' . (int)$row['id'] . '/delete')) ?>" class="m-0"
            onsubmit="return confirm('Sigur vrei să ștergi acest client?');">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <button class="btn btn-outline-secondary" type="submit">
          <i class="bi bi-trash me-1"></i> Șterge
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-5">
    <div class="card app-card p-3">
      <div class="h5 m-0">Date client</div>
      <div class="text-muted">Informații de contact și livrare</div>

      <div class="mt-3">
        <div class="d-flex justify-content-between border-bottom py-2">
          <div class="text-muted">Tip</div>
          <div class="fw-semibold"><?= htmlspecialchars($typeLabel) ?></div>
        </div>
        <?php if (!empty($row['cui'])): ?>
          <div class="d-flex justify-content-between border-bottom py-2">
            <div class="text-muted">CUI</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)$row['cui']) ?></div>
          </div>
        <?php endif; ?>
        <?php if (!empty($row['contact_person'])): ?>
          <div class="d-flex justify-content-between border-bottom py-2">
            <div class="text-muted">Persoană contact</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)$row['contact_person']) ?></div>
          </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between border-bottom py-2">
          <div class="text-muted">Telefon</div>
          <div class="fw-semibold"><?= htmlspecialchars((string)($row['phone'] ?? '')) ?></div>
        </div>
        <div class="d-flex justify-content-between border-bottom py-2">
          <div class="text-muted">Email</div>
          <div class="fw-semibold"><?= htmlspecialchars((string)($row['email'] ?? '')) ?></div>
        </div>
        <div class="py-2">
          <div class="text-muted">Adresă livrare</div>
          <div class="fw-semibold mt-1"><?= nl2br(htmlspecialchars((string)($row['address'] ?? ''))) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card app-card p-3">
      <div class="h5 m-0">Proiecte asociate</div>
      <div class="text-muted">Proiecte care folosesc acest client</div>

      <table class="table table-hover align-middle mb-0 mt-2" id="clientProjectsTable">
        <thead>
          <tr>
            <th>Cod</th>
            <th>Denumire</th>
            <th>Status</th>
            <th class="text-end">Creat</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($projects as $p): ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars((string)($p['code'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string)($p['name'] ?? '')) ?></td>
              <td><span class="badge app-badge"><?= htmlspecialchars((string)($p['status'] ?? '')) ?></span></td>
              <td class="text-end text-muted small"><?= htmlspecialchars((string)($p['created_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if (!$projects): ?>
        <div class="text-muted mt-2">Nu există proiecte asociate încă.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('clientProjectsTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25, order: [[3,'desc']] });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

