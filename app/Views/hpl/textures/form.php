<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$isEdit = ($mode ?? '') === 'edit';
$action = $isEdit ? Url::to('/hpl/texturi/' . (int)($row['id'] ?? 0) . '/edit') : Url::to('/hpl/texturi/create');
$v = $row ?? [];
$errors = $errors ?? [];

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0"><?= $isEdit ? 'Editează textură' : 'Textură nouă' ?></h1>
    <div class="text-muted">Texturi fără poze (cod opțional)</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/hpl/texturi')) ?>" class="btn btn-outline-secondary">Înapoi</a>
  </div>
</div>

<div class="card app-card p-4">
  <form method="post" action="<?= htmlspecialchars($action) ?>" class="row g-3">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="col-12 col-md-4">
      <label class="form-label">Cod</label>
      <input class="form-control" name="code" value="<?= htmlspecialchars((string)($v['code'] ?? '')) ?>">
    </div>

    <div class="col-12 col-md-8">
      <label class="form-label">Denumire *</label>
      <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" value="<?= htmlspecialchars((string)($v['name'] ?? '')) ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2 me-1"></i> Salvează
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/hpl/texturi')) ?>">Renunță</a>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

