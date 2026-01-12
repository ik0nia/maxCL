<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Variante</h1>
    <div class="text-muted">Material + finisaj față + finisaj verso (opțional)</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/catalog/variants/create')) ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Variantă nouă
    </a>
  </div>
</div>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="variantsTable">
    <thead>
      <tr>
        <th style="width:110px">Preview</th>
        <th>Material</th>
        <th>Față</th>
        <th>Verso</th>
        <th class="text-end" style="width:180px">Acțiuni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td>
            <div class="d-flex gap-1">
              <div class="text-center">
                <img src="<?= htmlspecialchars((string)$r['face_thumb_path']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
                <div class="text-muted small">Față</div>
              </div>
              <?php if (!empty($r['back_thumb_path'])): ?>
                <div class="text-center">
                  <img src="<?= htmlspecialchars((string)$r['back_thumb_path']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
                  <div class="text-muted small">Verso</div>
                </div>
              <?php endif; ?>
            </div>
          </td>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars((string)$r['material_brand']) ?> · <?= htmlspecialchars((string)$r['material_name']) ?></div>
            <div class="text-muted small"><?= htmlspecialchars((string)$r['material_code']) ?> · <?= (int)$r['thickness_mm'] ?> mm</div>
          </td>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars((string)$r['face_color_name']) ?></div>
            <div class="text-muted small"><?= htmlspecialchars((string)$r['face_texture_name']) ?> · <?= htmlspecialchars((string)$r['face_code']) ?></div>
          </td>
          <td>
            <?php if (empty($r['finish_back_id'])): ?>
              <span class="badge app-badge">Aceeași față/verso</span>
            <?php else: ?>
              <div class="fw-semibold"><?= htmlspecialchars((string)$r['back_color_name']) ?></div>
              <div class="text-muted small"><?= htmlspecialchars((string)$r['back_texture_name']) ?> · <?= htmlspecialchars((string)$r['back_code']) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/catalog/variants/' . (int)$r['id'] . '/edit')) ?>">
              <i class="bi bi-pencil me-1"></i> Editează
            </a>
            <form method="post" action="<?= htmlspecialchars(Url::to('/catalog/variants/' . (int)$r['id'] . '/delete')) ?>" class="d-inline"
                  onsubmit="return confirm('Sigur vrei să ștergi această variantă?');">
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
    const el = document.getElementById('variantsTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25 });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

