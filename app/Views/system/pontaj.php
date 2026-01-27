<?php
use App\Core\Url;
use App\Core\View;

$rows = $rows ?? [];
$sumMinutes = (int)($sumMinutes ?? 0);
$filters = $filters ?? [];
$categories = $categories ?? [];

$category = (string)($filters['category'] ?? '');
$person = (string)($filters['person'] ?? '');
$dateFrom = (string)($filters['date_from'] ?? '');
$dateTo = (string)($filters['date_to'] ?? '');

$catMap = [];
foreach ($categories as $c) {
  $val = (string)($c['value'] ?? '');
  $lbl = (string)($c['label'] ?? '');
  if ($val !== '') $catMap[$val] = $lbl;
}

$sumHours = $sumMinutes > 0 ? ($sumMinutes / 60.0) : 0.0;

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Pontaj</h1>
    <div class="text-muted">Timp lucrat pe categorii, persoane si zile.</div>
  </div>
</div>

<div class="card app-card p-3">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold">Categorie</label>
      <select class="form-select" name="category">
        <option value="">Toate</option>
        <?php foreach ($categories as $c): ?>
          <?php $val = (string)($c['value'] ?? ''); ?>
          <option value="<?= htmlspecialchars($val) ?>" <?= $val === $category ? 'selected' : '' ?>>
            <?= htmlspecialchars((string)($c['label'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold">Persoana</label>
      <select class="form-select js-time-person" name="person" data-placeholder="Toate persoanele">
        <?php if ($person !== ''): ?>
          <option value="<?= htmlspecialchars($person) ?>" selected><?= htmlspecialchars($person) ?></option>
        <?php endif; ?>
      </select>
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold">De la</label>
      <input class="form-control" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label fw-semibold">Pana la</label>
      <input class="form-control" type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
    </div>
    <div class="col-12 d-flex justify-content-end gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/system/pontaj')) ?>">Reset</a>
      <button class="btn btn-primary btn-sm" type="submit">
        <i class="bi bi-funnel me-1"></i> Filtreaza
      </button>
    </div>
  </form>
</div>

<div class="card app-card p-3 mt-3">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th style="width:120px">Data</th>
          <th style="width:140px">Categorie</th>
          <th style="width:160px">Persoana</th>
          <th>Descriere</th>
          <th class="text-end" style="width:120px">Minute</th>
          <th style="width:160px">Proiect</th>
          <th style="width:160px">Produs</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="7" class="text-muted">Nu exista inregistrari.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $dtRaw = (string)($r['created_at'] ?? '');
              $dtLabel = $dtRaw;
              $dtTime = '';
              if ($dtRaw !== '') {
                $dt = new DateTime($dtRaw);
                $dtLabel = $dt->format('d.m.Y');
                $dtTime = $dt->format('H:i');
              }
              $cat = (string)($r['category'] ?? '');
              $catLabel = $catMap[$cat] ?? $cat;
              $personLabel = (string)($r['person'] ?? '');
              $desc = (string)($r['description'] ?? '');
              $minutes = (int)($r['minutes'] ?? 0);
              $projectId = (int)($r['project_id'] ?? 0);
              $projectCode = (string)($r['project_code'] ?? '');
              $projectName = (string)($r['project_name'] ?? '');
              $productName = (string)($r['product_name'] ?? '');
              $projLabel = trim($projectCode . ' ' . $projectName);
            ?>
            <tr>
              <td>
                <div><?= htmlspecialchars($dtLabel) ?></div>
                <?php if ($dtTime !== ''): ?>
                  <div class="text-muted small"><?= htmlspecialchars($dtTime) ?></div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($catLabel) ?></td>
              <td><?= htmlspecialchars($personLabel !== '' ? $personLabel : '—') ?></td>
              <td><?= htmlspecialchars($desc) ?></td>
              <td class="text-end fw-semibold"><?= (int)$minutes ?></td>
              <td>
                <?php if ($projectId > 0): ?>
                  <a class="text-decoration-none" href="<?= htmlspecialchars(Url::to('/projects/' . $projectId)) ?>">
                    <?= htmlspecialchars($projLabel !== '' ? $projLabel : ('#' . $projectId)) ?>
                  </a>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($productName !== '' ? $productName : '—') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if ($rows): ?>
        <tfoot>
          <tr>
            <th colspan="4" class="text-end">Total</th>
            <th class="text-end"><?= (int)$sumMinutes ?> min</th>
            <th colspan="2" class="text-muted"><?= number_format($sumHours, 2, '.', '') ?> ore</th>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
    const $ = window.jQuery;
    const $el = $('.js-time-person');
    if ($el.length && !$el.data('select2')) {
      $el.select2({
        width: '100%',
        placeholder: $el.data('placeholder') || 'Toate persoanele',
        allowClear: true,
        tags: true,
        minimumInputLength: 1,
        ajax: {
          url: "<?= htmlspecialchars(Url::to('/api/time/people/search')) ?>",
          dataType: 'json',
          delay: 250,
          headers: { 'Accept': 'application/json' },
          data: function(params){ return { q: params.term }; },
          processResults: function(resp){
            const items = (resp && resp.items) ? resp.items : [];
            return { results: items };
          },
          cache: true
        }
      });
    }
  });
</script>

<?php
$content = ob_get_clean();
echo View::render('layout/app', ['title' => $title ?? 'Pontaj', 'content' => $content]);
?>

