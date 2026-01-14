<?php
use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);

$rows = $rows ?? [];
$q = trim((string)($q ?? ''));
$status = trim((string)($status ?? ''));
$statuses = $statuses ?? [];

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Proiecte de producție</h1>
    <div class="text-muted">Listă proiecte + status/progres</div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($canWrite): ?>
      <a class="btn btn-primary" href="<?= htmlspecialchars(Url::to('/projects/create')) ?>">
        <i class="bi bi-plus-lg me-1"></i> Proiect nou
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="card app-card p-3 mb-3">
  <form method="get" action="<?= htmlspecialchars(Url::to('/projects')) ?>" class="row g-2 align-items-end">
    <div class="col-12 col-md-6">
      <label class="form-label fw-semibold mb-1">Caută</label>
      <input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cod sau nume…">
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label fw-semibold mb-1">Status</label>
      <select class="form-select" name="status">
        <option value="">Toate</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= htmlspecialchars((string)$s['value']) ?>" <?= ((string)$s['value'] === $status) ? 'selected' : '' ?>>
            <?= htmlspecialchars((string)$s['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-2 d-flex gap-2">
      <button class="btn btn-outline-secondary w-100" type="submit">
        <i class="bi bi-search me-1"></i> Caută
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/projects')) ?>">
        <i class="bi bi-x-lg"></i>
      </a>
    </div>
  </form>
</div>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="projectsTable">
    <thead>
      <tr>
        <th style="width:160px">Cod</th>
        <th>Nume</th>
        <th style="width:160px">Client/Grup</th>
        <th style="width:160px">Status</th>
        <th class="text-end" style="width:110px">Prioritate</th>
        <th style="width:140px">Deadline</th>
        <th class="text-end" style="width:120px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars((string)($r['code'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['name'] ?? '')) ?></td>
          <td class="text-muted">
            <?php if (!empty($r['client_name'])): ?>
              <?= htmlspecialchars((string)$r['client_name']) ?>
            <?php elseif (!empty($r['client_group_name'])): ?>
              Grup: <?= htmlspecialchars((string)$r['client_group_name']) ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td class="fw-semibold"><?= htmlspecialchars((string)($r['status'] ?? '')) ?></td>
          <td class="text-end"><?= (int)($r['priority'] ?? 0) ?></td>
          <td><?= htmlspecialchars((string)($r['due_date'] ?? '')) ?></td>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/projects/' . (int)$r['id'])) ?>">
              <i class="bi bi-eye me-1"></i> Vezi
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('projectsTable');
    if (el && window.DataTable) {
      window.__projectsDT = new DataTable(el, {
        pageLength: 50,
        lengthMenu: [[25, 50, 100], [25, 50, 100]],
        language: {
          search: 'Caută:',
          searchPlaceholder: 'Caută în tabel…',
          lengthMenu: 'Afișează _MENU_',
        }
      });
    }
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

