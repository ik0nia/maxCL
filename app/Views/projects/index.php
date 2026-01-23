<?php
use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);

$rows = $rows ?? [];
$projectMeta = is_array($projectMeta ?? null) ? $projectMeta : [];
$q = trim((string)($q ?? ''));
$status = trim((string)($status ?? ''));
$statuses = $statuses ?? [];

function _daysRemaining(?string $dueDate, ?string $refDate = null): ?int {
  $dueDate = trim((string)($dueDate ?? ''));
  if ($dueDate === '') return null;
  try {
    $due = new DateTime($dueDate);
    $ref = new DateTime($refDate ?: date('Y-m-d'));
  } catch (Throwable $e) {
    return null;
  }
  return (int)$ref->diff($due)->format('%r%a');
}

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

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="projectsTable">
    <thead>
      <tr>
        <th style="width:44px"></th>
        <th style="width:170px">Data creare</th>
        <th>Nume</th>
        <th style="width:160px">Client/Grup</th>
        <th style="width:160px">Status</th>
        <th style="width:140px">Deadline</th>
        <th class="text-end" style="width:130px">Zile rămase</th>
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
          if ($prodCount <= 0) {
            $icon = 'bi-info-circle';
            $iconClass = 'text-muted';
            $iconTitle = 'Fără produse';
          } elseif (!$allDelivered) {
            $icon = 'bi-gear-fill';
            $iconClass = 'text-primary';
            $iconTitle = 'În lucru';
          } elseif ($reservedAny) {
            $icon = 'bi-exclamation-triangle-fill';
            $iconClass = 'text-danger';
            $iconTitle = 'Livrat, dar există rezervări';
          } else {
            $icon = 'bi-check-circle-fill';
            $iconClass = 'text-success';
            $iconTitle = 'Livrat complet';
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
          <?php
            $dueRaw = (string)($r['due_date'] ?? '');
            $daysLocked = isset($r['days_remaining_locked']) ? (int)$r['days_remaining_locked'] : null;
            $statusVal = (string)($r['status'] ?? '');
            $daysLeft = null;
            if ($dueRaw !== '') {
              if ($statusVal === 'LIVRAT_COMPLET') {
                if ($daysLocked !== null) {
                  $daysLeft = $daysLocked;
                } else {
                  $ref = trim((string)($r['completed_at'] ?? ''));
                  if ($ref !== '') $ref = substr($ref, 0, 10);
                  $daysLeft = _daysRemaining($dueRaw, $ref !== '' ? $ref : null);
                }
              } else {
                $daysLeft = _daysRemaining($dueRaw);
              }
            }
            $daysClass = '';
            $daysBold = '';
            if ($daysLeft !== null) {
              if ($daysLeft < 0) $daysClass = 'text-danger';
              else $daysClass = 'text-success';
              if ($daysLeft >= 0 && $daysLeft <= 3) $daysBold = 'fw-bold';
            }
          ?>
          <td><?= htmlspecialchars($dueRaw !== '' ? $dueRaw : '—') ?></td>
          <td class="text-end <?= $daysClass ?> <?= $daysBold ?>">
            <?= $daysLeft !== null ? (int)$daysLeft : '—' ?>
          </td>
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
    <span><i class="bi bi-info-circle text-muted me-1"></i> Fără produse</span>
    <span><i class="bi bi-gear-fill text-primary me-1"></i> În lucru (are produse nelivrate)</span>
    <span><i class="bi bi-check-circle-fill text-success me-1"></i> Livrat complet</span>
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

