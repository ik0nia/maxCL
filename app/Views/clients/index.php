<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);
$isAdmin = $u && (string)$u['role'] === Auth::ROLE_ADMIN;
$groups = $groups ?? [];

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

<div class="row g-3">
  <div class="col-12 col-lg-8">
    <div class="card app-card p-3">
      <table class="table table-hover align-middle mb-0" id="clientsTable">
        <thead>
          <tr>
            <th style="width:150px">Tip</th>
            <th>Nume</th>
            <th>Telefon</th>
            <th>Proiecte</th>
            <th>Oferte</th>
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
              $olist = (string)($r['offer_list'] ?? '');
              $offers = $olist !== '' ? explode('||', $olist) : [];
              $oCount = (int)($r['offer_count'] ?? 0);
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
              <td>
                <?php if ($pCount <= 0): ?>
                  <span class="text-muted">—</span>
                <?php else: ?>
                  <div class="small text-muted mb-1"><?= $pCount ?> proiect(e)</div>
                  <div class="d-flex flex-wrap gap-1">
                    <?php foreach (array_slice($projects, 0, 4) as $p): ?>
                      <?php
                        $pid = 0;
                        $plabel = $p;
                        if (strpos($p, '::') !== false) {
                          [$pidRaw, $plabel] = explode('::', $p, 2);
                          $pid = is_numeric($pidRaw) ? (int)$pidRaw : 0;
                        }
                      ?>
                      <?php if ($pid > 0): ?>
                        <a class="badge app-badge text-decoration-none" href="<?= htmlspecialchars(Url::to('/projects/' . $pid)) ?>">
                          <?= htmlspecialchars($plabel) ?>
                        </a>
                      <?php else: ?>
                        <span class="badge app-badge"><?= htmlspecialchars($plabel) ?></span>
                      <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (count($projects) > 4): ?>
                      <span class="text-muted small">+<?= (int)(count($projects) - 4) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
          <td>
            <?php if ($oCount <= 0): ?>
              <span class="text-muted">—</span>
            <?php else: ?>
              <div class="small text-muted mb-1"><?= $oCount ?> ofertă(e)</div>
              <div class="d-flex flex-wrap gap-1">
                <?php foreach (array_slice($offers, 0, 4) as $o): ?>
                  <?php
                    $oid = 0;
                    $olabel = $o;
                    if (strpos($o, '::') !== false) {
                      [$oidRaw, $olabel] = explode('::', $o, 2);
                      $oid = is_numeric($oidRaw) ? (int)$oidRaw : 0;
                    }
                  ?>
                  <?php if ($oid > 0): ?>
                    <a class="badge app-badge text-decoration-none" href="<?= htmlspecialchars(Url::to('/offers/' . $oid)) ?>">
                      <?= htmlspecialchars($olabel) ?>
                    </a>
                  <?php else: ?>
                    <span class="badge app-badge"><?= htmlspecialchars($olabel) ?></span>
                  <?php endif; ?>
                <?php endforeach; ?>
                <?php if (count($offers) > 4): ?>
                  <span class="text-muted small">+<?= (int)(count($offers) - 4) ?></span>
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
  </div>
  <div class="col-12 col-lg-4">
    <div class="card app-card p-3 mb-3">
      <div class="h5 m-0">Grupuri de clienți</div>
      <div class="text-muted">Creează și vezi grupurile existente</div>
      <?php if ($canWrite): ?>
        <form class="row g-2 mt-2" method="post" action="<?= htmlspecialchars(Url::to('/clients/groups/create')) ?>">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
          <div class="col-12">
            <label class="form-label small">Nume grup</label>
            <input class="form-control" name="name" maxlength="190" required>
          </div>
          <div class="col-12">
            <button class="btn btn-primary w-100" type="submit">
              <i class="bi bi-plus-lg me-1"></i> Creează grup
            </button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <div class="card app-card p-3">
      <div class="h5 m-0">Grupuri existente</div>
      <?php if (!$groups): ?>
        <div class="text-muted mt-2">Nu există grupuri încă.</div>
      <?php else: ?>
        <div class="mt-2 d-flex flex-column gap-2">
          <?php foreach ($groups as $g): ?>
            <?php
              $gname = (string)($g['name'] ?? '');
              $cnt = (int)($g['client_count'] ?? 0);
              $list = (string)($g['client_list'] ?? '');
              $members = $list !== '' ? explode('||', $list) : [];
            ?>
            <div class="p-2 rounded" style="background:#F7FAFB;border:1px solid #E5EEF1">
              <div class="fw-semibold"><?= htmlspecialchars($gname) ?></div>
              <div class="text-muted small"><?= $cnt ?> client(i)</div>
              <?php if ($members): ?>
                <div class="d-flex flex-wrap gap-1 mt-1">
                  <?php foreach ($members as $m): ?>
                    <span class="badge app-badge"><?= htmlspecialchars($m) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="text-muted small mt-1">—</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
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

