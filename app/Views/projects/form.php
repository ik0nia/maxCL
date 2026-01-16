<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$mode = (string)($mode ?? 'create');
$row = is_array($row ?? null) ? $row : [];
$errors = is_array($errors ?? null) ? $errors : [];
$statuses = $statuses ?? [];
$clients = $clients ?? [];
$groups = $groups ?? [];
$labelsAll = is_array($labelsAll ?? null) ? $labelsAll : [];
$labelsSelected = is_array($labelsSelected ?? null) ? $labelsSelected : [];

$labelsInit = [];
$labelsCsv = trim((string)($row['labels'] ?? ''));
if ($labelsCsv !== '') {
  $parts = preg_split('/[,\n]+/', $labelsCsv) ?: [];
  foreach ($parts as $p) {
    $p = trim((string)$p);
    if ($p !== '') $labelsInit[] = $p;
  }
} else {
  foreach ($labelsSelected as $l) {
    if (is_array($l) && isset($l['name'])) $labelsInit[] = trim((string)$l['name']);
    elseif (is_string($l)) $labelsInit[] = trim($l);
  }
}
$labelsInit = array_values(array_unique(array_filter($labelsInit, fn($s) => $s !== '')));

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
      <input class="form-control" value="<?= htmlspecialchars((string)($row['code'] ?? '')) ?>" readonly>
      <div class="text-muted small mt-1">Se generează automat (începând de la 1000).</div>
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

    <div class="col-12">
      <label class="form-label fw-semibold">Etichete (labels)</label>
      <div class="text-muted small">Se propagă automat la produsele din proiect.</div>

      <div class="d-flex flex-wrap gap-2 mt-2" id="labelsChips">
        <?php foreach ($labelsInit as $ln): ?>
          <span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-semibold px-3 py-2 d-inline-flex align-items-center gap-2">
            <span><?= htmlspecialchars($ln) ?></span>
            <button type="button" class="btn btn-sm p-0" style="border:0;background:transparent" aria-label="Șterge" data-label-remove="<?= htmlspecialchars($ln) ?>">
              <i class="bi bi-x-circle"></i>
            </button>
          </span>
        <?php endforeach; ?>
      </div>

      <div class="input-group mt-2">
        <input class="form-control" id="labelInput" list="labelsDatalist" placeholder="Adaugă etichetă…" maxlength="64">
        <button class="btn btn-outline-secondary" type="button" id="labelAddBtn" title="Adaugă">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
      <datalist id="labelsDatalist">
        <?php foreach ($labelsAll as $l): ?>
          <?php $nm = trim((string)($l['name'] ?? '')); if ($nm === '') continue; ?>
          <option value="<?= htmlspecialchars($nm) ?>"></option>
        <?php endforeach; ?>
      </datalist>
      <input type="hidden" name="labels" id="labelsHidden" value="<?= htmlspecialchars(implode(', ', $labelsInit)) ?>">
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

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const chips = document.getElementById('labelsChips');
    const input = document.getElementById('labelInput');
    const addBtn = document.getElementById('labelAddBtn');
    const hidden = document.getElementById('labelsHidden');
    if (!chips || !input || !addBtn || !hidden) return;

    function norm(s) {
      return String(s || '').trim();
    }
    function normKey(s) {
      return norm(s).toLowerCase();
    }
    function getLabels() {
      const out = [];
      chips.querySelectorAll('[data-label-chip]').forEach(function (el) {
        const v = norm(el.getAttribute('data-label-chip'));
        if (v) out.push(v);
      });
      return out;
    }
    function syncHidden() {
      hidden.value = getLabels().join(', ');
    }
    function hasLabel(v) {
      const k = normKey(v);
      if (!k) return true;
      return getLabels().some(function (x) { return normKey(x) === k; });
    }
    function addOne(v) {
      v = norm(v);
      if (!v || hasLabel(v)) return;
      const span = document.createElement('span');
      span.className = 'badge rounded-pill bg-success-subtle text-success-emphasis fw-semibold px-3 py-2 d-inline-flex align-items-center gap-2';
      span.setAttribute('data-label-chip', v);
      span.innerHTML = '<span></span><button type="button" class="btn btn-sm p-0" style="border:0;background:transparent" aria-label="Șterge"><i class="bi bi-x-circle"></i></button>';
      span.querySelector('span').textContent = v;
      span.querySelector('button').addEventListener('click', function () {
        span.remove();
        syncHidden();
      });
      chips.appendChild(span);
      syncHidden();
    }
    function addFromInput() {
      const raw = norm(input.value);
      if (!raw) return;
      raw.split(',').map(function (x) { return norm(x); }).filter(Boolean).forEach(addOne);
      input.value = '';
      input.focus();
    }

    // init: convert server-rendered remove buttons
    chips.querySelectorAll('[data-label-remove]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const chip = btn.closest('span');
        if (chip) chip.remove();
        syncHidden();
      });
      const chip = btn.closest('span');
      if (chip) {
        const val = norm(btn.getAttribute('data-label-remove'));
        if (val) chip.setAttribute('data-label-chip', val);
      }
    });
    syncHidden();

    addBtn.addEventListener('click', addFromInput);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        addFromInput();
      }
    });
    input.addEventListener('blur', function () {
      addFromInput();
    });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

