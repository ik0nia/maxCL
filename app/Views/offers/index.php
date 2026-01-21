<?php
use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);

$rows = $rows ?? [];
$q = trim((string)($q ?? ''));
$status = trim((string)($status ?? ''));
$statuses = $statuses ?? [];
$statusLabels = [];
foreach ($statuses as $s) {
    if (isset($s['value'], $s['label'])) {
        $statusLabels[(string)$s['value']] = (string)$s['label'];
    }
}

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Oferte</h1>
    <div class="text-muted">Listă oferte + status</div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($canWrite): ?>
      <a class="btn btn-primary" href="<?= htmlspecialchars(Url::to('/offers/create')) ?>">
        <i class="bi bi-plus-lg me-1"></i> Ofertă nouă
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="card app-card p-3 mb-3">
  <form method="get" action="<?= htmlspecialchars(Url::to('/offers')) ?>" class="row g-2 align-items-end">
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
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/offers')) ?>">
        <i class="bi bi-x-lg"></i>
      </a>
    </div>
  </form>
</div>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="offersTable">
    <thead>
      <tr>
        <th style="width:170px">Data creare</th>
        <th>Nume</th>
        <th style="width:160px">Client/Grup</th>
        <th style="width:140px">Status</th>
        <th style="width:160px">Proiect</th>
        <th class="text-end" style="width:120px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <?php $oid = (int)($r['id'] ?? 0); ?>
        <tr class="js-row-link" data-href="<?= htmlspecialchars(Url::to('/offers/' . $oid)) ?>" role="button" tabindex="0">
          <td class="text-muted fw-semibold"><?= htmlspecialchars((string)($r['created_at'] ?? '')) ?></td>
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
          <?php
            $stVal = (string)($r['status'] ?? '');
            $stLabel = $statusLabels[$stVal] ?? $stVal;
          ?>
          <td class="fw-semibold"><?= htmlspecialchars($stLabel) ?></td>
          <td>
            <?php $pid = (int)($r['converted_project_id'] ?? 0); ?>
            <?php if ($pid > 0): ?>
              <a class="text-decoration-none" href="<?= htmlspecialchars(Url::to('/projects/' . $pid)) ?>">Proiect #<?= $pid ?></a>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/offers/' . $oid)) ?>">
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
    const el = document.getElementById('offersTable');
    if (el && window.DataTable) {
      window.__offersDT = new DataTable(el, {
        pageLength: 50,
        lengthMenu: [[25, 50, 100], [25, 50, 100]],
        order: [[0, 'desc']],
        language: {
          search: 'Caută:',
          searchPlaceholder: 'Caută în tabel…',
          lengthMenu: 'Afișează _MENU_',
        }
      });
    }

    document.querySelectorAll('tr.js-row-link[data-href]').forEach(function (tr) {
      function go(e) {
        const t = (e && e.target) ? e.target : null;
        if (t && t.closest && t.closest('a,button,input,select,textarea,label,form')) return;
        const href = tr.getAttribute('data-href');
        if (href) window.location.href = href;
      }
      tr.addEventListener('click', go);
      tr.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          go(e);
        }
      });
    });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

