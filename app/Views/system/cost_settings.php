<?php
use App\Core\Csrf;
use App\Core\View;
use App\Core\Url;

$labor = isset($labor) && $labor !== null ? (float)$labor : null;
$cnc = isset($cnc) && $cnc !== null ? (float)$cnc : null;

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Setări costuri</h1>
    <div class="text-muted">Valori globale folosite în Proiecte → Minute &amp; Manoperă</div>
  </div>
</div>

<div class="card app-card p-3">
  <form method="post" action="<?= htmlspecialchars(Url::to('/system/costuri')) ?>" class="row g-3">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="col-12 col-md-6">
      <label class="form-label fw-semibold">Cost manoperă / oră</label>
      <div class="input-group">
        <input class="form-control" type="number" step="0.01" min="0" name="cost_labor_per_hour" value="<?= $labor !== null ? htmlspecialchars(number_format($labor, 2, '.', '')) : '' ?>" placeholder="ex: 65.00">
        <span class="input-group-text">lei/h</span>
      </div>
      <div class="text-muted small mt-1">Folosit pentru înregistrările cu tip <strong>ATELIER</strong>.</div>
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label fw-semibold">Cost CNC / oră</label>
      <div class="input-group">
        <input class="form-control" type="number" step="0.01" min="0" name="cost_cnc_per_hour" value="<?= $cnc !== null ? htmlspecialchars(number_format($cnc, 2, '.', '')) : '' ?>" placeholder="ex: 120.00">
        <span class="input-group-text">lei/h</span>
      </div>
      <div class="text-muted small mt-1">Folosit pentru înregistrările cu tip <strong>CNC</strong>.</div>
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

