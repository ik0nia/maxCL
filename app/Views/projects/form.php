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

      <style>
        /* UI pentru etichete ca în pagina proiectului (chips verzi) */
        .pp-label-chip {
          display: inline-flex;
          align-items: center;
          gap: .5rem;
          padding: .55rem 1rem;
          border-radius: .8rem;
          background: #EAF6EA;
          border: 1px solid #B7DDB7;
          color: #111;
          font-weight: 700;
        }
        .pp-label-chip .pp-label-x {
          width: 26px;
          height: 26px;
          border-radius: 999px;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          border: 1px solid rgba(0,0,0,.2);
          background: transparent;
          padding: 0;
          line-height: 1;
        }
      </style>

      <div class="d-flex flex-wrap gap-2 mt-2" id="labelsChips">
        <?php foreach ($labelsInit as $ln): ?>
          <span class="pp-label-chip">
            <span><?= htmlspecialchars($ln) ?></span>
            <button type="button" class="pp-label-x" aria-label="Șterge" data-label-remove="<?= htmlspecialchars($ln) ?>">×</button>
          </span>
        <?php endforeach; ?>
      </div>

      <div class="d-flex gap-2 mt-2 align-items-stretch">
        <input class="form-control form-control-lg" id="labelInput" list="labelsDatalist" placeholder="Adaugă etichetă..." maxlength="64">
        <button class="btn btn-outline-secondary px-4" type="button" id="labelAddBtn" title="Adaugă">
          <span style="font-size:26px; line-height:1">+</span>
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
      span.className = 'pp-label-chip';
      span.setAttribute('data-label-chip', v);
      span.innerHTML = '<span></span><button type="button" class="pp-label-x" aria-label="Șterge">×</button>';
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
      } else if (e.key === ',') {
        e.preventDefault();
        addFromInput();
      }
    });
    input.addEventListener('input', function () {
      // dacă user-ul tastează "cuvânt," transformăm imediat în etichetă
      const v = norm(input.value);
      if (v.endsWith(',')) {
        input.value = v.replace(/,+$/, '');
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

