<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Materiale</h1>
    <div class="text-muted">Materiale HPL (brand, grosime, urmărit în stoc)</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/catalog/materials/create')) ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Material nou
    </a>
  </div>
</div>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="materialsTable">
    <thead>
      <tr>
        <th>Cod</th>
        <th>Denumire</th>
        <th>Brand</th>
        <th>Grosime</th>
        <th>În stoc</th>
        <th class="text-end" style="width:180px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars((string)$r['code']) ?></td>
          <td><?= htmlspecialchars((string)$r['name']) ?></td>
          <td><?= htmlspecialchars((string)$r['brand']) ?></td>
          <td><?= (int)$r['thickness_mm'] ?> mm</td>
          <td>
            <?php if ((int)$r['track_stock'] === 1): ?>
              <span class="badge app-badge">Da</span>
            <?php else: ?>
              <span class="badge text-bg-light border">Nu</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/catalog/materials/' . (int)$r['id'] . '/edit')) ?>">
              <i class="bi bi-pencil me-1"></i> Editează
            </a>
            <form method="post" action="<?= htmlspecialchars(Url::to('/catalog/materials/' . (int)$r['id'] . '/delete')) ?>" class="d-inline"
                  onsubmit="return confirm('Sigur vrei să ștergi acest material?');">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
              <button class="btn btn-outline-secondary btn-sm" type="submit">
                <i class="bi bi-trash me-1"></i> Șterge
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('materialsTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25, order: [[2,'asc'],[1,'asc']] });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

