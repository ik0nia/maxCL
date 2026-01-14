<?php
use App\Controllers\ProjectsController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = ProjectsController::canWrite();

$project = $project ?? [];
$tab = (string)($tab ?? 'general');
$statuses = $statuses ?? [];
$allocationModes = $allocationModes ?? [];
$clients = $clients ?? [];
$groups = $groups ?? [];

$tabs = [
  'general' => 'General',
  'products' => 'Produse (piese)',
  'consum' => 'Consum materiale',
  'cnc' => 'CNC / Tehnic',
  'hours' => 'Ore & Manoperă',
  'deliveries' => 'Livrări',
  'files' => 'Fișiere',
  'history' => 'Istoric / Log-uri',
];
if (!isset($tabs[$tab])) $tab = 'general';

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Proiect</h1>
    <div class="text-muted">
      <?= htmlspecialchars((string)($project['code'] ?? '')) ?> · <?= htmlspecialchars((string)($project['name'] ?? '')) ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/projects')) ?>" class="btn btn-outline-secondary">Înapoi</a>
  </div>
</div>

<ul class="nav nav-tabs mb-3">
  <?php foreach ($tabs as $k => $label): ?>
    <li class="nav-item">
      <a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '?tab=' . $k)) ?>">
        <?= htmlspecialchars($label) ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'general'): ?>
  <div class="row g-3">
    <div class="col-12 col-lg-8">
      <div class="card app-card p-3">
        <div class="h5 m-0">General</div>
        <div class="text-muted">Date proiect + setări distribuție</div>

        <?php if ($canWrite): ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/edit')) ?>" class="row g-3 mt-1">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold">Cod</label>
              <input class="form-control" name="code" value="<?= htmlspecialchars((string)($project['code'] ?? '')) ?>">
            </div>
            <div class="col-12 col-md-8">
              <label class="form-label fw-semibold">Nume</label>
              <input class="form-control" name="name" value="<?= htmlspecialchars((string)($project['name'] ?? '')) ?>">
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Descriere</label>
              <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars((string)($project['description'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 col-md-3">
              <label class="form-label fw-semibold">Prioritate</label>
              <input class="form-control" type="number" name="priority" value="<?= htmlspecialchars((string)($project['priority'] ?? '0')) ?>">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label fw-semibold">Categorie</label>
              <input class="form-control" name="category" value="<?= htmlspecialchars((string)($project['category'] ?? '')) ?>">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label fw-semibold">Start</label>
              <input class="form-control" type="date" name="start_date" value="<?= htmlspecialchars((string)($project['start_date'] ?? '')) ?>">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label fw-semibold">Deadline</label>
              <input class="form-control" type="date" name="due_date" value="<?= htmlspecialchars((string)($project['due_date'] ?? '')) ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Client</label>
              <select class="form-select" name="client_id">
                <option value="">—</option>
                <?php foreach ($clients as $c): ?>
                  <option value="<?= (int)($c['id'] ?? 0) ?>" <?= ((string)($project['client_id'] ?? '') === (string)($c['id'] ?? '')) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)($c['name'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="text-muted small mt-1">Alege fie client, fie grup.</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Grup clienți</label>
              <select class="form-select" name="client_group_id">
                <option value="">—</option>
                <?php foreach ($groups as $g): ?>
                  <option value="<?= (int)$g['id'] ?>" <?= ((string)($project['client_group_id'] ?? '') === (string)$g['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$g['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="text-muted small mt-1">Alege fie client, fie grup.</div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Allocation mode</label>
              <select class="form-select" name="allocation_mode">
                <?php foreach ($allocationModes as $m): ?>
                  <option value="<?= htmlspecialchars((string)$m['value']) ?>" <?= ((string)($project['allocation_mode'] ?? '') === (string)$m['value']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$m['label']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="allocations_locked" id="allocLocked" <?= !empty($project['allocations_locked']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="allocLocked">Lock distribuție</label>
              </div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Etichete (tags)</label>
              <input class="form-control" name="tags" value="<?= htmlspecialchars((string)($project['tags'] ?? '')) ?>">
              <div class="text-muted small mt-1">Separă cu virgulă.</div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Note</label>
              <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars((string)($project['notes'] ?? '')) ?></textarea>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Note tehnice</label>
              <textarea class="form-control" name="technical_notes" rows="3"><?= htmlspecialchars((string)($project['technical_notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-primary" type="submit">
                <i class="bi bi-save me-1"></i> Salvează
              </button>
            </div>
          </form>
        <?php else: ?>
          <div class="text-muted mt-2">Nu ai drepturi de editare.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card app-card p-3">
        <div class="h5 m-0">Status</div>
        <div class="text-muted">Schimbă status proiect (se loghează)</div>

        <div class="mt-2">
          <div class="text-muted small">Status curent</div>
          <div class="fw-semibold"><?= htmlspecialchars((string)($project['status'] ?? '')) ?></div>
        </div>

        <?php if ($canWrite): ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/status')) ?>" class="mt-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <label class="form-label fw-semibold">Status nou</label>
            <select class="form-select" name="status">
              <?php foreach ($statuses as $s): ?>
                <option value="<?= htmlspecialchars((string)$s['value']) ?>"><?= htmlspecialchars((string)$s['label']) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="form-label fw-semibold mt-2">Notă (opțional)</label>
            <input class="form-control" name="note" maxlength="255" placeholder="motiv / observații…">
            <button class="btn btn-outline-secondary w-100 mt-3" type="submit">
              <i class="bi bi-arrow-repeat me-1"></i> Schimbă status
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="card app-card p-4">
    <div class="h5 m-0"><?= htmlspecialchars($tabs[$tab]) ?></div>
    <div class="text-muted mt-1">Acest tab va fi completat în pasul următor.</div>
  </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

