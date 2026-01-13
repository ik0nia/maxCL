<?php
use App\Core\Url;
use App\Core\View;

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Stoc</h1>
    <div class="text-muted">Catalog plăci + total buc/mp (disponibil)</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/stock/boards/create')) ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Placă nouă
    </a>
  </div>
</div>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="boardsTable">
    <thead>
      <tr>
        <th style="width:110px">Preview</th>
        <th>Cod</th>
        <th>Denumire</th>
        <th>Brand</th>
        <th>Grosime</th>
        <th>Dim. standard</th>
        <th class="text-end">Stoc (buc)</th>
        <th class="text-end">Stoc (mp)</th>
        <th class="text-end" style="width:140px">Detalii</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td>
            <div class="d-flex gap-1">
              <img src="<?= htmlspecialchars((string)$r['face_thumb_path']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
              <?php if (!empty($r['back_thumb_path'])): ?>
                <img src="<?= htmlspecialchars((string)$r['back_thumb_path']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
              <?php endif; ?>
            </div>
          </td>
          <td class="fw-semibold"><?= htmlspecialchars((string)$r['code']) ?></td>
          <td><?= htmlspecialchars((string)$r['name']) ?></td>
          <td><?= htmlspecialchars((string)$r['brand']) ?></td>
          <td><?= (int)$r['thickness_mm'] ?> mm</td>
          <td><?= (int)$r['std_width_mm'] ?> × <?= (int)$r['std_height_mm'] ?> mm</td>
          <td class="text-end fw-semibold"><?= (int)$r['stock_qty_available'] ?></td>
          <td class="text-end fw-semibold"><?= number_format((float)$r['stock_m2_available'], 2, '.', '') ?></td>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$r['id'])) ?>">
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
    const el = document.getElementById('boardsTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25 });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

