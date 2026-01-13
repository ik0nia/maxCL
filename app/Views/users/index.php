<?php
use App\Core\Url;
use App\Core\View;

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Utilizatori</h1>
    <div class="text-muted">Administrare conturi și roluri</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/users/create')) ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Utilizator nou
    </a>
  </div>
</div>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="usersTable">
    <thead>
      <tr>
        <th>Email</th>
        <th>Nume</th>
        <th>Rol</th>
        <th>Status</th>
        <th>Ultimul login</th>
        <th class="text-end" style="width:160px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars((string)$r['email']) ?></td>
          <td><?= htmlspecialchars((string)$r['name']) ?></td>
          <td><span class="badge app-badge"><?= htmlspecialchars((string)$r['role']) ?></span></td>
          <td>
            <?php if ((int)$r['is_active'] === 1): ?>
              <span class="badge app-badge">Activ</span>
            <?php else: ?>
              <span class="badge text-bg-light border">Inactiv</span>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= htmlspecialchars((string)($r['last_login_at'] ?? '—')) ?></td>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/users/' . (int)$r['id'] . '/edit')) ?>">
              <i class="bi bi-pencil me-1"></i> Editează
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('usersTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25, order: [[4,'desc']] });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

