<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Finisaje</h1>
    <div class="text-muted">Culori + texturi (o față)</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/catalog/finishes/create')) ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Finisaj nou
    </a>
  </div>
</div>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="finishesTable">
    <thead>
      <tr>
        <th style="width:64px">Poză</th>
        <th>Cod</th>
        <th>Culoare</th>
        <th>Textură</th>
        <th class="text-end" style="width:180px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td>
            <img src="<?= htmlspecialchars((string)$r['thumb_path']) ?>" alt="thumb" style="width:42px;height:42px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;">
          </td>
          <td class="fw-semibold"><?= htmlspecialchars((string)$r['code']) ?></td>
          <td>
            <?= htmlspecialchars((string)$r['color_name']) ?>
            <?php if (!empty($r['color_code'])): ?>
              <div class="text-muted small"><?= htmlspecialchars((string)$r['color_code']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?= htmlspecialchars((string)$r['texture_name']) ?>
            <?php if (!empty($r['texture_code'])): ?>
              <div class="text-muted small"><?= htmlspecialchars((string)$r['texture_code']) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/catalog/finishes/' . (int)$r['id'] . '/edit')) ?>">
              <i class="bi bi-pencil me-1"></i> Editează
            </a>
            <form method="post" action="<?= htmlspecialchars(Url::to('/catalog/finishes/' . (int)$r['id'] . '/delete')) ?>" class="d-inline"
                  onsubmit="return confirm('Sigur vrei să ștergi acest finisaj?');">
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
    const el = document.getElementById('finishesTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25, order: [[2,'asc']] });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

