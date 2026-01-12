<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$isEdit = ($mode ?? '') === 'edit';
$action = $isEdit ? Url::to('/catalog/finishes/' . (int)($row['id'] ?? 0) . '/edit') : Url::to('/catalog/finishes/create');
$v = $row ?? [];
$errors = $errors ?? [];

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0"><?= $isEdit ? 'Editează finisaj' : 'Finisaj nou' ?></h1>
    <div class="text-muted">Thumbnail obligatoriu · acceptă JPG/PNG/WEBP · generează thumb 256px</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/catalog/finishes')) ?>" class="btn btn-outline-secondary">Înapoi</a>
  </div>
</div>

<div class="card app-card p-4">
  <form method="post" action="<?= htmlspecialchars($action) ?>" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="col-12 col-md-4">
      <label class="form-label">Cod *</label>
      <input class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" name="code" value="<?= htmlspecialchars((string)($v['code'] ?? '')) ?>" required>
      <?php if (isset($errors['code'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['code']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Nume culoare *</label>
      <input class="form-control <?= isset($errors['color_name']) ? 'is-invalid' : '' ?>" name="color_name" value="<?= htmlspecialchars((string)($v['color_name'] ?? '')) ?>" required>
      <?php if (isset($errors['color_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['color_name']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Cod culoare</label>
      <input class="form-control" name="color_code" value="<?= htmlspecialchars((string)($v['color_code'] ?? '')) ?>">
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Nume textură *</label>
      <input class="form-control <?= isset($errors['texture_name']) ? 'is-invalid' : '' ?>" name="texture_name" value="<?= htmlspecialchars((string)($v['texture_name'] ?? '')) ?>" required>
      <?php if (isset($errors['texture_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['texture_name']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Cod textură</label>
      <input class="form-control" name="texture_code" value="<?= htmlspecialchars((string)($v['texture_code'] ?? '')) ?>">
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label"><?= $isEdit ? 'Schimbă imagine (opțional)' : 'Imagine (obligatoriu)' ?></label>
      <input type="file" class="form-control <?= isset($errors['image']) ? 'is-invalid' : '' ?>" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?= $isEdit ? '' : 'required' ?>>
      <?php if (isset($errors['image'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['image']) ?></div><?php endif; ?>
      <?php if ($isEdit && !empty($v['thumb_path'])): ?>
        <div class="mt-2 d-flex align-items-center gap-2">
          <img src="<?= htmlspecialchars((string)$v['thumb_path']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:12px;border:1px solid #D9E3E6;">
          <div class="text-muted small">Thumbnail curent</div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2 me-1"></i> Salvează
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/catalog/finishes')) ?>">Renunță</a>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

