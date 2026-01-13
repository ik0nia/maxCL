<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Texturi</h1>
    <div class="text-muted">Texturi HPL (fără imagini)</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/hpl/texturi/create')) ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Textură nouă
    </a>
  </div>
</div>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="texturesTable">
    <thead>
      <tr>
        <th style="width:180px">Cod</th>
        <th>Denumire</th>
        <th class="text-end" style="width:180px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars((string)($r['code'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)$r['name']) ?></td>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/hpl/texturi/' . (int)$r['id'] . '/edit')) ?>">
              <i class="bi bi-pencil me-1"></i> Editează
            </a>
            <form method="post" action="<?= htmlspecialchars(Url::to('/hpl/texturi/' . (int)$r['id'] . '/delete')) ?>" class="d-inline"
                  onsubmit="return confirm('Sigur vrei să ștergi această textură?');">
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
    const el = document.getElementById('texturesTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25, order: [[1,'asc']] });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

