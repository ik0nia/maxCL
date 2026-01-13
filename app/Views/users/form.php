<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$isEdit = ($mode ?? '') === 'edit';
$id = (int)($row['id'] ?? 0);
$action = $isEdit ? Url::to('/users/' . $id . '/edit') : Url::to('/users/create');
$v = $row ?? [];
$errors = $errors ?? [];
$roles = $roles ?? [];

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0"><?= $isEdit ? 'Editează utilizator' : 'Utilizator nou' ?></h1>
    <div class="text-muted"><?= $isEdit ? 'Actualizează detaliile și rolul' : 'Creează un cont nou' ?></div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/users')) ?>" class="btn btn-outline-secondary">Înapoi</a>
  </div>
</div>

<div class="card app-card p-4">
  <form method="post" action="<?= htmlspecialchars($action) ?>" class="row g-3">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="col-12 col-md-5">
      <label class="form-label">Email *</label>
      <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email"
             value="<?= htmlspecialchars((string)($v['email'] ?? '')) ?>" required>
      <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Nume *</label>
      <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name"
             value="<?= htmlspecialchars((string)($v['name'] ?? '')) ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label">Rol *</label>
      <select class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>" name="role" required>
        <option value="">Alege rol...</option>
        <?php foreach ($roles as $r): ?>
          <?php $sel = ((string)$r === (string)($v['role'] ?? '')) ? 'selected' : ''; ?>
          <option value="<?= htmlspecialchars($r) ?>" <?= $sel ?>><?= htmlspecialchars($r) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['role'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['role']) ?></div><?php endif; ?>
    </div>

    <div class="col-12">
      <div class="form-check">
        <input class="form-check-input <?= isset($errors['is_active']) ? 'is-invalid' : '' ?>" type="checkbox" name="is_active" id="is_active"
               <?= ((int)($v['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
        <label class="form-check-label" for="is_active">Cont activ</label>
        <?php if (isset($errors['is_active'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['is_active']) ?></div><?php endif; ?>
      </div>
    </div>

    <div class="col-12">
      <div class="alert alert-light border mb-0" style="border-radius:14px">
        <div class="fw-semibold"><?= $isEdit ? 'Schimbă parola (opțional)' : 'Setează parola (obligatoriu)' ?></div>
        <div class="text-muted">Minim 8 caractere.</div>
      </div>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label"><?= $isEdit ? 'Parolă nouă' : 'Parolă *' ?></label>
      <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" name="password" <?= $isEdit ? '' : 'required' ?>>
      <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label"><?= $isEdit ? 'Confirmare parolă nouă' : 'Confirmare parolă *' ?></label>
      <input type="password" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" name="password_confirm" <?= $isEdit ? '' : 'required' ?>>
      <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password_confirm']) ?></div><?php endif; ?>
    </div>

    <?php if ($isEdit): ?>
      <div class="col-12 col-md-4">
        <label class="form-label">Ultimul login</label>
        <input class="form-control" value="<?= htmlspecialchars((string)($v['last_login_at'] ?? '—')) ?>" disabled>
      </div>
    <?php endif; ?>

    <div class="col-12 d-flex gap-2 pt-2">
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2 me-1"></i> Salvează
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/users')) ?>">Renunță</a>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

