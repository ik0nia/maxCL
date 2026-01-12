<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$isEdit = ($mode ?? '') === 'edit';
$action = $isEdit ? Url::to('/catalog/materials/' . (int)($row['id'] ?? 0) . '/edit') : Url::to('/catalog/materials/create');
$v = $row ?? [];
$errors = $errors ?? [];

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0"><?= $isEdit ? 'Editează material' : 'Material nou' ?></h1>
    <div class="text-muted">Detalii tehnice + opțiune „urmărit în stoc”</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/catalog/materials')) ?>" class="btn btn-outline-secondary">Înapoi</a>
  </div>
</div>

<div class="card app-card p-4">
  <form method="post" action="<?= htmlspecialchars($action) ?>" class="row g-3">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="col-12 col-md-4">
      <label class="form-label">Cod *</label>
      <input class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" name="code" value="<?= htmlspecialchars((string)($v['code'] ?? '')) ?>" required>
      <?php if (isset($errors['code'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['code']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Denumire *</label>
      <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" value="<?= htmlspecialchars((string)($v['name'] ?? '')) ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Brand *</label>
      <input class="form-control <?= isset($errors['brand']) ? 'is-invalid' : '' ?>" name="brand" value="<?= htmlspecialchars((string)($v['brand'] ?? '')) ?>" required>
      <?php if (isset($errors['brand'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['brand']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Grosime (mm) *</label>
      <input type="number" min="1" max="200" class="form-control <?= isset($errors['thickness_mm']) ? 'is-invalid' : '' ?>" name="thickness_mm"
             value="<?= htmlspecialchars((string)($v['thickness_mm'] ?? '')) ?>" required>
      <?php if (isset($errors['thickness_mm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['thickness_mm']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-8">
      <label class="form-label">Note</label>
      <input class="form-control" name="notes" value="<?= htmlspecialchars((string)($v['notes'] ?? '')) ?>">
    </div>

    <div class="col-12">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="track_stock" id="track_stock" <?= ((int)($v['track_stock'] ?? 1) === 1) ? 'checked' : '' ?>>
        <label class="form-check-label" for="track_stock">
          Urmărit în stoc
        </label>
      </div>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2 me-1"></i> Salvează
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/catalog/materials')) ?>">Renunță</a>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

