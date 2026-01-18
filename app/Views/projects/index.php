<?php
use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);

$rows = $rows ?? [];
$projectMeta = is_array($projectMeta ?? null) ? $projectMeta : [];
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
        <th style="width:44px"></th>
        <th style="width:170px">Data creare</th>
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
        <?php
          $pid = (int)($r['id'] ?? 0);
          $meta = $pid > 0 && isset($projectMeta[$pid]) ? $projectMeta[$pid] : ['products_count' => 0, 'all_delivered' => false, 'reserved_any' => false];
          $prodCount = (int)($meta['products_count'] ?? 0);
          $allDelivered = (bool)($meta['all_delivered'] ?? false);
          $reservedAny = (bool)($meta['reserved_any'] ?? false);
          $icon = '';
          $iconClass = '';
          $iconTitle = '';
          if ($prodCount > 0 && !$allDelivered) {
            $icon = 'bi-gear-fill';
            $iconClass = 'text-primary';
            $iconTitle = 'În lucru';
          } elseif ($prodCount > 0 && $allDelivered) {
            $icon = 'bi-exclamation-triangle-fill';
            if ($reservedAny) {
              $iconClass = 'text-danger';
              $iconTitle = 'Livrat, dar există rezervări';
            } else {
              $iconClass = 'text-warning';
              $iconTitle = 'Livrat, fără rezervări';
            }
          }
        ?>
        <tr class="js-row-link" data-href="<?= htmlspecialchars(Url::to('/projects/' . (int)$r['id'])) ?>" role="button" tabindex="0">
          <td class="text-center">
            <?php if ($icon !== ''): ?>
              <i class="bi <?= htmlspecialchars($icon) ?> <?= htmlspecialchars($iconClass) ?>" title="<?= htmlspecialchars($iconTitle) ?>"></i>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
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
<div class="text-muted small mt-2">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <span><i class="bi bi-gear-fill text-primary me-1"></i> În lucru (are produse nelivrate)</span>
    <span><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Livrat, fără rezervări</span>
    <span><i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> Livrat, cu rezervări rămase</span>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('projectsTable');
    if (el && window.DataTable) {
      window.__projectsDT = new DataTable(el, {
        pageLength: 50,
        lengthMenu: [[25, 50, 100], [25, 50, 100]],
        // implicit: cele mai noi proiecte primele (data creare desc)
        order: [[1, 'desc']],
        language: {
          search: 'Caută:',
          searchPlaceholder: 'Caută în tabel…',
          lengthMenu: 'Afișează _MENU_',
        }
      });
    }

    // Click oriunde pe rând -> intră în proiect (fără să strice butoanele/link-urile).
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

