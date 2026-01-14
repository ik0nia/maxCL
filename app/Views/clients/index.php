<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
$isAdmin = $u && (string)$u['role'] === Auth::ROLE_ADMIN;

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Clienți</h1>
    <div class="text-muted">Persoane fizice și firme · cu proiecte asociate</div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($canWrite): ?>
      <a href="<?= htmlspecialchars(Url::to('/clients/create')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Client nou
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="clientsTable">
    <thead>
      <tr>
        <th style="width:150px">Tip</th>
        <th>Nume</th>
        <th>Telefon</th>
        <th>Email</th>
        <th>Adresă livrare</th>
        <th>Proiecte</th>
        <th class="text-end" style="width:240px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <?php
          $type = (string)($r['type'] ?? '');
          $typeLabel = $type === 'FIRMA' ? 'Firmă' : 'Persoană fizică';
          $plist = (string)($r['project_list'] ?? '');
          $projects = $plist !== '' ? explode('||', $plist) : [];
          $pCount = (int)($r['project_count'] ?? 0);
        ?>
        <tr class="js-row-link" data-href="<?= htmlspecialchars(Url::to('/clients/' . (int)$r['id'])) ?>" role="button" tabindex="0">
          <td>
            <span class="badge app-badge"><?= htmlspecialchars($typeLabel) ?></span>
          </td>
          <td class="fw-semibold">
            <a href="<?= htmlspecialchars(Url::to('/clients/' . (int)$r['id'])) ?>" class="text-decoration-none">
              <?= htmlspecialchars((string)$r['name']) ?>
            </a>
            <?php if (!empty($r['cui'])): ?>
              <div class="text-muted small">CUI: <?= htmlspecialchars((string)$r['cui']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars((string)($r['phone'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['email'] ?? '')) ?></td>
          <td class="text-muted"><?= nl2br(htmlspecialchars((string)($r['address'] ?? ''))) ?></td>
          <td>
            <?php if ($pCount <= 0): ?>
              <span class="text-muted">—</span>
            <?php else: ?>
              <div class="small text-muted mb-1"><?= $pCount ?> proiect(e)</div>
              <div class="d-flex flex-wrap gap-1">
                <?php foreach (array_slice($projects, 0, 4) as $p): ?>
                  <span class="badge app-badge"><?= htmlspecialchars($p) ?></span>
                <?php endforeach; ?>
                <?php if (count($projects) > 4): ?>
                  <span class="text-muted small">+<?= (int)(count($projects) - 4) ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/clients/' . (int)$r['id'])) ?>">
              <i class="bi bi-eye me-1"></i> Vezi
            </a>
            <?php if ($canWrite): ?>
              <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/clients/' . (int)$r['id'] . '/edit')) ?>">
                <i class="bi bi-pencil me-1"></i> Editează
              </a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
              <form method="post" action="<?= htmlspecialchars(Url::to('/clients/' . (int)$r['id'] . '/delete')) ?>" class="d-inline"
                    onsubmit="return confirm('Sigur vrei să ștergi acest client?');">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button class="btn btn-outline-secondary btn-sm" type="submit">
                  <i class="bi bi-trash me-1"></i> Șterge
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('clientsTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25, order: [[1,'asc']] });

    // Click oriunde pe rând -> intră în client (fără să strice butoanele/link-urile).
    document.querySelectorAll('#clientsTable tbody tr.js-row-link[data-href]').forEach(function (tr) {
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

