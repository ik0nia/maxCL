<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$can = $u && in_array((string)($u['role'] ?? ''), [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);
$boards = $boards ?? [];

if (!function_exists('_hplInternalNormThumb')) {
  function _hplInternalNormThumb(string $p): string {
    $p = trim($p);
    if ($p === '') return '';
    if (str_starts_with($p, '/uploads/')) return Url::to($p);
    return $p;
  }
}

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
    <div class="col-12 col-lg-7">
      <div class="card app-card p-3">
        <div class="h5 m-0">Pas 1: Alege tipul plăcii</div>
        <div class="text-muted">Caută după cod/denumire (ca în Stoc) și apasă “Alege”.</div>

        <div class="table-responsive mt-2">
          <table class="table table-hover align-middle mb-0" id="boardsPicker">
            <thead>
              <tr>
                <th style="width:86px">Preview</th>
                <th>Cod</th>
                <th>Denumire</th>
                <th class="text-end">Grosime</th>
                <th class="text-end">Standard</th>
                <th class="text-end" style="width:90px">Alege</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($boards as $b): ?>
                <?php
                  $id = (int)($b['id'] ?? 0);
                  $code = (string)($b['code'] ?? '');
                  $name = (string)($b['name'] ?? '');
                  $th = (int)($b['thickness_mm'] ?? 0);
                  $w = (int)($b['std_width_mm'] ?? 0);
                  $h = (int)($b['std_height_mm'] ?? 0);
                  $fThumb = _hplInternalNormThumb((string)($b['face_thumb_path'] ?? ''));
                  $bThumb = _hplInternalNormThumb((string)($b['back_thumb_path'] ?? ''));
                  $label = trim($code . ' · ' . $name . ' · ' . $th . 'mm · ' . $h . '×' . $w);
                ?>
                <tr data-board-id="<?= $id ?>" data-board-label="<?= htmlspecialchars($label, ENT_QUOTES) ?>">
                  <td>
                    <div class="d-flex gap-1">
                      <img src="<?= htmlspecialchars($fThumb) ?>" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;">
                      <img src="<?= htmlspecialchars($bThumb !== '' ? $bThumb : $fThumb) ?>" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;">
                    </div>
                  </td>
                  <td class="fw-semibold"><?= htmlspecialchars($code) ?></td>
                  <td><?= htmlspecialchars($name) ?></td>
                  <td class="text-end"><?= $th ?> mm</td>
                  <td class="text-end"><?= $h ?> × <?= $w ?> mm</td>
                  <td class="text-end">
                    <button type="button" class="btn btn-outline-secondary btn-sm js-pick-board">Alege</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-5">
      <div class="card app-card p-3">
        <div class="h5 m-0">Pas 2: Adaugă piesă internă</div>
        <div class="text-muted">Se salvează ca <strong>OFFCUT</strong> și este marcată ca <strong>nestocabilă</strong>.</div>

        <form class="row g-2 mt-2" method="post" action="<?= htmlspecialchars(Url::to('/hpl/piese-interne/create')) ?>" id="internalPieceForm" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

          <div class="col-12">
            <label class="form-label small">Tip placă</label>
            <input type="hidden" name="board_id" id="board_id" value="">
            <div class="form-control bg-white" id="pickedBoardLabel" style="cursor:not-allowed;opacity:.85">Alege o placă din listă…</div>
            <div class="text-muted small mt-1">După selectare, se activează câmpurile de mai jos.</div>
          </div>

          <div class="col-6">
            <label class="form-label small">Lungime (mm)</label>
            <input type="number" min="1" class="form-control js-step2" name="height_mm" required disabled>
          </div>
          <div class="col-6">
            <label class="form-label small">Lățime (mm)</label>
            <input type="number" min="1" class="form-control js-step2" name="width_mm" required disabled>
          </div>

          <div class="col-6">
            <label class="form-label small">Buc</label>
            <input type="number" min="1" class="form-control js-step2" name="qty" value="1" required disabled>
          </div>
          <div class="col-6">
            <label class="form-label small">Locație</label>
            <select class="form-select js-step2" name="location" required disabled>
              <option value="">Alege locație...</option>
              <option value="Depozit">Depozit</option>
              <option value="Producție">Producție</option>
              <option value="Magazin">Magazin</option>
              <option value="Atelier">Atelier</option>
              <option value="Depozit (Stricat)">Depozit (Stricat)</option>
            </select>
            <div class="text-muted small mt-1"><strong>Producție</strong> setează automat statusul pe <strong>Rezervat</strong>.</div>
          </div>

          <div class="col-12">
            <label class="form-label small">Note</label>
            <input class="form-control js-step2" name="notes" placeholder="opțional" disabled>
          </div>

          <div class="col-12">
            <label class="form-label small">Poză piesă (opțional)</label>
            <input class="form-control js-step2" type="file" name="photo" accept="image/jpeg,image/png,image/webp" disabled>
            <div class="text-muted small mt-1">JPG/PNG/WEBP · max 100MB.</div>
          </div>

          <div class="col-12">
            <button class="btn btn-primary w-100 js-step2" type="submit" disabled>
              <i class="bi bi-plus-lg me-1"></i> Adaugă
            </button>
          </div>
        </form>
      </div>

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
      const tableEl = document.getElementById('boardsPicker');
      const pickedIdEl = document.getElementById('board_id');
      const pickedLabelEl = document.getElementById('pickedBoardLabel');
      const step2Els = Array.from(document.querySelectorAll('.js-step2'));

      function setStep2Enabled(on){
        step2Els.forEach(el => { try { el.disabled = !on; } catch (e) {} });
      }

      function pickFromRow(tr){
        if (!tr) return;
        const id = tr.getAttribute('data-board-id') || '';
        const lbl = tr.getAttribute('data-board-label') || '';
        if (pickedIdEl) pickedIdEl.value = id;
        if (pickedLabelEl) pickedLabelEl.textContent = (lbl !== '' ? lbl : 'Alege o placă din listă…');
        setStep2Enabled(id !== '');
        document.querySelectorAll('#boardsPicker tbody tr').forEach(r => r.classList.remove('table-active'));
        tr.classList.add('table-active');
      }

      if (tableEl && window.DataTable) {
        try {
          new DataTable(tableEl, {
            pageLength: 100,
            lengthMenu: [[25, 50, 100, 200], [25, 50, 100, 200]],
            order: [[1, 'asc']],
            language: {
              search: 'Caută:',
              searchPlaceholder: 'caută cod/denumire…',
              lengthMenu: 'Afișează _MENU_',
            }
          });
        } catch (e) {}
      }

      document.querySelectorAll('#boardsPicker tbody tr').forEach(function(tr){
        tr.addEventListener('click', function(e){
          const tgt = e.target;
          if (tgt && tgt.closest && tgt.closest('.js-pick-board')) {
            pickFromRow(tr);
            return;
          }
          pickFromRow(tr);
        });
      });

      setStep2Enabled(false);
    });
  </script>
<?php endif; ?>

<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

