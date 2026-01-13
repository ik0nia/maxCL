<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$isEdit = ($mode ?? '') === 'edit';
$v = $row ?? [];
$errors = $errors ?? [];
$types = $types ?? [];
$action = $isEdit ? Url::to('/clients/' . (int)($v['id'] ?? 0) . '/edit') : Url::to('/clients/create');

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0"><?= $isEdit ? 'Editează client' : 'Client nou' ?></h1>
    <div class="text-muted">Date minime: nume, telefon, email, adresă livrare</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/clients')) ?>" class="btn btn-outline-secondary">Înapoi</a>
  </div>
</div>

<div class="card app-card p-4">
  <form method="post" action="<?= htmlspecialchars($action) ?>" class="row g-3">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="col-12 col-md-4">
      <label class="form-label">Tip client *</label>
      <select class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>" name="type" id="client_type" required>
        <?php foreach ($types as $t): ?>
          <?php $sel = ((string)$t['value'] === (string)($v['type'] ?? '')) ? 'selected' : ''; ?>
          <option value="<?= htmlspecialchars((string)$t['value']) ?>" <?= $sel ?>><?= htmlspecialchars((string)$t['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['type'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['type']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-8">
      <label class="form-label">Nume (client/companie) *</label>
      <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" value="<?= htmlspecialchars((string)($v['name'] ?? '')) ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4" id="cui_wrap">
      <label class="form-label">CUI (doar pentru firmă) *</label>
      <input class="form-control <?= isset($errors['cui']) ? 'is-invalid' : '' ?>" name="cui" value="<?= htmlspecialchars((string)($v['cui'] ?? '')) ?>">
      <?php if (isset($errors['cui'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['cui']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-8">
      <label class="form-label">Persoană contact (opțional)</label>
      <input class="form-control" name="contact_person" value="<?= htmlspecialchars((string)($v['contact_person'] ?? '')) ?>">
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Telefon *</label>
      <input class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" name="phone" value="<?= htmlspecialchars((string)($v['phone'] ?? '')) ?>" required>
      <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['phone']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Email *</label>
      <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email" value="<?= htmlspecialchars((string)($v['email'] ?? '')) ?>" required>
      <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Adresă livrare *</label>
      <textarea class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>" name="address" rows="2" required><?= htmlspecialchars((string)($v['address'] ?? '')) ?></textarea>
      <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['address']) ?></div><?php endif; ?>
    </div>

    <div class="col-12">
      <label class="form-label">Note (opțional)</label>
      <textarea class="form-control" name="notes" rows="2"><?= htmlspecialchars((string)($v['notes'] ?? '')) ?></textarea>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2 me-1"></i> Salvează
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/clients')) ?>">Renunță</a>
    </div>
  </form>
</div>

<script>
  (function(){
    function toggleCui(){
      var t = document.getElementById('client_type');
      var w = document.getElementById('cui_wrap');
      if (!t || !w) return;
      var isFirma = (t.value === 'FIRMA');
      w.style.display = isFirma ? '' : 'none';
      var inp = w.querySelector('input[name="cui"]');
      if (inp) inp.required = isFirma;
    }
    document.addEventListener('DOMContentLoaded', function(){
      var t = document.getElementById('client_type');
      if (t) t.addEventListener('change', toggleCui);
      toggleCui();
    });
  })();
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

