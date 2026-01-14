<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$mode = (string)($mode ?? 'create');
$row = is_array($row ?? null) ? $row : [];
$errors = is_array($errors ?? null) ? $errors : [];
$statuses = $statuses ?? [];
$allocationModes = $allocationModes ?? [];
$clients = $clients ?? [];
$groups = $groups ?? [];

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0"><?= $mode === 'edit' ? 'Editează proiect' : 'Proiect nou' ?></h1>
    <div class="text-muted">Date generale proiect producție</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/projects')) ?>" class="btn btn-outline-secondary">Înapoi</a>
  </div>
</div>

<div class="card app-card p-3">
  <form method="post" action="<?= htmlspecialchars($mode === 'edit' ? Url::to('/projects/' . (int)($row['id'] ?? 0) . '/edit') : Url::to('/projects/create')) ?>" class="row g-3">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold">Cod</label>
      <input class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" name="code" value="<?= htmlspecialchars((string)($row['code'] ?? '')) ?>">
      <?php if (isset($errors['code'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string)$errors['code']) ?></div><?php endif; ?>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label fw-semibold">Nume</label>
      <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" value="<?= htmlspecialchars((string)($row['name'] ?? '')) ?>">
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string)$errors['name']) ?></div><?php endif; ?>
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold">Status</label>
      <select class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" name="status">
        <?php foreach ($statuses as $s): ?>
          <option value="<?= htmlspecialchars((string)$s['value']) ?>" <?= ((string)($row['status'] ?? '') === (string)$s['value']) ? 'selected' : '' ?>>
            <?= htmlspecialchars((string)$s['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['status'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string)$errors['status']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold">Prioritate</label>
      <input class="form-control" type="number" name="priority" value="<?= htmlspecialchars((string)($row['priority'] ?? '0')) ?>">
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold">Categorie</label>
      <input class="form-control" name="category" value="<?= htmlspecialchars((string)($row['category'] ?? '')) ?>">
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold">Start</label>
      <input class="form-control" type="date" name="start_date" value="<?= htmlspecialchars((string)($row['start_date'] ?? '')) ?>">
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold">Deadline</label>
      <input class="form-control" type="date" name="due_date" value="<?= htmlspecialchars((string)($row['due_date'] ?? '')) ?>">
    </div>

    <div class="col-12">
      <label class="form-label fw-semibold">Descriere</label>
      <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars((string)($row['description'] ?? '')) ?></textarea>
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label fw-semibold">Client (opțional)</label>
      <select class="form-select <?= isset($errors['client_id']) ? 'is-invalid' : '' ?>" name="client_id">
        <option value="">—</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)($c['id'] ?? 0) ?>" <?= ((string)($row['client_id'] ?? '') === (string)($c['id'] ?? '')) ? 'selected' : '' ?>>
            <?= htmlspecialchars((string)($c['name'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['client_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string)$errors['client_id']) ?></div><?php endif; ?>
      <div class="text-muted small mt-1">Alege fie client, fie grup.</div>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label fw-semibold">Grup de clienți (opțional)</label>
      <select class="form-select <?= isset($errors['client_group_id']) ? 'is-invalid' : '' ?>" name="client_group_id">
        <option value="">—</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int)$g['id'] ?>" <?= ((string)($row['client_group_id'] ?? '') === (string)$g['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars((string)$g['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['client_group_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string)$errors['client_group_id']) ?></div><?php endif; ?>
      <div class="text-muted small mt-1">Alege fie client, fie grup.</div>
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label fw-semibold">Allocation mode</label>
      <select class="form-select <?= isset($errors['allocation_mode']) ? 'is-invalid' : '' ?>" name="allocation_mode">
        <?php foreach ($allocationModes as $m): ?>
          <option value="<?= htmlspecialchars((string)$m['value']) ?>" <?= ((string)($row['allocation_mode'] ?? '') === (string)$m['value']) ? 'selected' : '' ?>>
            <?= htmlspecialchars((string)$m['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['allocation_mode'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string)$errors['allocation_mode']) ?></div><?php endif; ?>
      <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" name="allocations_locked" id="allocLocked" <?= !empty($row['allocations_locked']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="allocLocked">Lock distribuție</label>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label fw-semibold">Etichete (tags)</label>
      <input class="form-control" name="tags" value="<?= htmlspecialchars((string)($row['tags'] ?? '')) ?>" placeholder="ex: urgent, client VIP, etc">
      <div class="text-muted small mt-1">Separă cu virgulă.</div>
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label fw-semibold">Note</label>
      <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars((string)($row['notes'] ?? '')) ?></textarea>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label fw-semibold">Note tehnice</label>
      <textarea class="form-control" name="technical_notes" rows="3"><?= htmlspecialchars((string)($row['technical_notes'] ?? '')) ?></textarea>
    </div>

    <div class="col-12 d-flex justify-content-end">
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-save me-1"></i> Salvează
      </button>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

