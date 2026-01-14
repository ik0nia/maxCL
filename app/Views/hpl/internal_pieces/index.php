<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$can = $u && in_array((string)($u['role'] ?? ''), [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Adăugare plăci mici (nestocabile)</h1>
    <div class="text-muted">Piese pentru uz intern, separate de stocul contabil (nu intră în totaluri).</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-outline-secondary">Stoc</a>
  </div>
</div>

<?php if (!$can): ?>
  <div class="alert alert-warning">Nu ai acces la această pagină.</div>
<?php else: ?>
  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card app-card p-3">
        <div class="h5 m-0">Adaugă piesă internă</div>
        <div class="text-muted">Se salvează ca <strong>OFFCUT</strong> și este marcată ca <strong>nestocabilă</strong>.</div>

        <form class="row g-2 mt-2" method="post" action="<?= htmlspecialchars(Url::to('/hpl/piese-interne/create')) ?>">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

          <div class="col-12">
            <label class="form-label small">Tip placă</label>
            <select class="form-select" name="board_id" id="board_id" required></select>
            <div class="text-muted small mt-1">Caută după cod/denumire/brand.</div>
          </div>

          <div class="col-6">
            <label class="form-label small">Lungime (mm)</label>
            <input type="number" min="1" class="form-control" name="height_mm" required>
          </div>
          <div class="col-6">
            <label class="form-label small">Lățime (mm)</label>
            <input type="number" min="1" class="form-control" name="width_mm" required>
          </div>

          <div class="col-6">
            <label class="form-label small">Buc</label>
            <input type="number" min="1" class="form-control" name="qty" value="1" required>
          </div>
          <div class="col-6">
            <label class="form-label small">Locație</label>
            <select class="form-select" name="location" required>
              <option value="">Alege locație...</option>
              <option value="Depozit">Depozit</option>
              <option value="Producție">Producție</option>
              <option value="Magazin">Magazin</option>
              <option value="Depozit (Stricat)">Depozit (Stricat)</option>
            </select>
            <div class="text-muted small mt-1"><strong>Producție</strong> setează automat statusul pe <strong>Rezervat</strong>.</div>
          </div>

          <div class="col-12">
            <label class="form-label small">Note</label>
            <input class="form-control" name="notes" placeholder="opțional">
          </div>

          <div class="col-12">
            <button class="btn btn-primary w-100" type="submit">
              <i class="bi bi-plus-lg me-1"></i> Adaugă
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card app-card p-3">
        <div class="h5 m-0">Observații</div>
        <div class="text-muted mt-2">
          - Piesele interne sunt salvate în aceeași tabelă ca stocul, dar cu <strong>is_accounting=0</strong>.<br>
          - Nu sunt incluse în: stoc disponibil (buc/mp), valoare stoc, carduri din panou sau filtrările “doar cu stoc”.<br>
          - Dacă adaugi o piesă internă identică (aceeași placă/dimensiuni/status/locație), cantitatea se <strong>cumulează</strong>.
        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const $ = window.jQuery;
      if (!$ || !$.fn || !$.fn.select2) return;

      $('#board_id').select2({
        width: '100%',
        placeholder: 'Alege tipul plăcii...',
        allowClear: true,
        ajax: {
          url: <?= json_encode(Url::to('/api/hpl/boards/search'), JSON_UNESCAPED_UNICODE) ?>,
          dataType: 'json',
          delay: 150,
          data: function (params) { return { q: params.term || '' }; },
          processResults: function (data) {
            if (!data || !data.ok) return { results: [] };
            return { results: (data.items || []).map(function (it) { return { id: it.id, text: it.text }; }) };
          }
        }
      });
    });
  </script>
<?php endif; ?>

<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

