<?php
use App\Controllers\ProjectsController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Core\Session;

$u = Auth::user();
$canWrite = ProjectsController::canWrite();
$canEditProducts = ProjectsController::canEditProjectProducts();
$canMoveHpl = $u && in_array((string)($u['role'] ?? ''), [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);
$canDelete = ProjectsController::canDelete();

$project = $project ?? [];
$tab = (string)($tab ?? 'general');
$projectProducts = $projectProducts ?? [];
$magazieConsum = $magazieConsum ?? [];
$hplConsum = $hplConsum ?? [];
$projectHplPieces = $projectHplPieces ?? [];
$hplBoards = $hplBoards ?? [];
$magazieItems = $magazieItems ?? [];
$deliveries = $deliveries ?? [];
$deliveryItems = $deliveryItems ?? [];
$projectFiles = $projectFiles ?? [];
$workLogs = $workLogs ?? [];
$history = $history ?? [];
$projectProductLabels = $projectProductLabels ?? [];
$discussions = $discussions ?? [];
$productComments = $productComments ?? [];
$docsByPp = $docsByPp ?? [];
$laborByProduct = $laborByProduct ?? [];
$materialsByProduct = $materialsByProduct ?? [];
$projectCostSummary = $projectCostSummary ?? [];
$billingClients = $billingClients ?? [];
$billingAddresses = $billingAddresses ?? [];
$projectLabels = $projectLabels ?? [];
$cncFiles = $cncFiles ?? [];
$statuses = $statuses ?? [];
$clients = $clients ?? [];
$groups = $groups ?? [];
$ppStatusError = null;
$ppStatusErrorRaw = Session::flash('pp_status_error');
if (is_string($ppStatusErrorRaw) && $ppStatusErrorRaw !== '') {
  $ppStatusError = json_decode($ppStatusErrorRaw, true);
  if (!is_array($ppStatusError) || !isset($ppStatusError['id'], $ppStatusError['message'])) {
    $ppStatusError = null;
  } else {
    $ppStatusError = [
      'id' => (int)$ppStatusError['id'],
      'message' => (string)$ppStatusError['message'],
    ];
  }
}

$tabs = [
  'general' => 'General',
  'products' => 'Produse',
  'consum' => 'Consum materiale',
  'hours' => 'Ore & Manoperă',
  'cnc' => 'CNC / Tehnic',
  'deliveries' => 'Livrări',
  'files' => 'Fișiere',
  'discutii' => 'Discuții',
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
        <div class="text-muted">Date proiect</div>

        <?php if ($canWrite): ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/edit')) ?>" class="row g-3 mt-1">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold">Cod</label>
              <input class="form-control" name="code" value="<?= htmlspecialchars((string)($project['code'] ?? '')) ?>" readonly>
              <div class="text-muted small mt-1">Se generează automat (incremental).</div>
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
          <?php if ($canDelete): ?>
            <div class="d-flex justify-content-end mt-3">
              <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/delete')) ?>"
                    onsubmit="return confirm('Ștergi proiectul <?= htmlspecialchars((string)($project['code'] ?? '')) ?> · <?= htmlspecialchars((string)($project['name'] ?? '')) ?>?');">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button class="btn btn-outline-danger" type="submit">
                  <i class="bi bi-trash me-1"></i> Șterge proiect
                </button>
              </form>
            </div>
          <?php endif; ?>
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

      <div class="card app-card p-3 mt-3">
        <div class="h5 m-0">Etichete (labels)</div>
        <div class="text-muted">Se propagă automat la produsele din proiect</div>

        <?php if (!$projectLabels): ?>
          <div class="text-muted mt-2">Nu există etichete încă.</div>
        <?php else: ?>
          <div class="d-flex flex-wrap gap-1 mt-2">
            <?php foreach ($projectLabels as $l): ?>
              <?php
                $lid = (int)($l['label_id'] ?? 0);
                $lname = (string)($l['name'] ?? '');
                $src = (string)($l['source'] ?? '');
              ?>
              <span class="badge app-badge">
                <?= htmlspecialchars($lname) ?>
                <?php if ($src === 'DIRECT' && $canWrite): ?>
                  <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/labels/' . $lid . '/remove')) ?>" class="d-inline m-0"
                        onsubmit="return confirm('Ștergi eticheta?');">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <button class="btn btn-sm p-0 ms-1" style="border:0;background:transparent" type="submit" aria-label="Șterge">
                      <i class="bi bi-x-circle"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($canWrite): ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/labels/add')) ?>" class="mt-3 d-flex gap-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input class="form-control" name="label_name" placeholder="Adaugă etichetă…" maxlength="64">
            <button class="btn btn-outline-secondary" type="submit">
              <i class="bi bi-plus-lg"></i>
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php elseif ($tab === 'products'): ?>
  <div class="row g-3">
    <div class="col-12">
      <div class="card app-card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="h5 m-0">Adaugă produs (nou)</div>
            <div class="text-muted">Fiecare produs se creează direct în proiect</div>
          </div>
          <?php if ($canWrite): ?>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#ppAddNewCollapse" aria-expanded="false" aria-controls="ppAddNewCollapse">
              <i class="bi bi-plus-lg me-1"></i> Creează produs
            </button>
          <?php endif; ?>
        </div>
        <?php if (!$canWrite): ?>
          <div class="text-muted mt-2">Nu ai drepturi de editare.</div>
        <?php else: ?>
          <div class="collapse mt-3" id="ppAddNewCollapse">
            <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/create')) ?>" class="row g-2">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
              <div class="col-12">
                <label class="form-label fw-semibold">Denumire</label>
                <input class="form-control" name="name" required>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Descriere</label>
                <textarea class="form-control" name="description" rows="2" maxlength="4000" placeholder="Opțional…"></textarea>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Cod (opțional)</label>
                <input class="form-control" name="code">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Cantitate</label>
                <input class="form-control" type="number" step="0.01" min="0" name="qty" value="1" required>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Preț vânzare (lei)</label>
                <input class="form-control" type="number" step="0.01" min="0" name="sale_price" placeholder="opțional">
              </div>
              <?php /* Suprafața nu mai este obligatorie la creare. Se poate seta ulterior din edit. */ ?>
              <?php /* HPL-ul pe piesă se adaugă din butonul "Consum HPL" (pe cardul piesei). */ ?>
              <div class="col-12 d-flex justify-content-end">
                <button class="btn btn-primary" type="submit">
                  <i class="bi bi-plus-lg me-1"></i> Creează
                </button>
              </div>
            </form>
          </div>

          <script>
            document.addEventListener('DOMContentLoaded', function(){
              if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
              const $ = window.jQuery;
              $('.js-pp-magazie-item').each(function(){
                const el = this;
                const $el = $(el);
                if ($el.data('select2')) return;
                $el.select2({
                  width: '100%',
                  placeholder: 'Caută accesoriu…',
                  allowClear: true,
                  minimumInputLength: 1,
                  ajax: {
                    url: "<?= htmlspecialchars(Url::to('/api/magazie/items/search')) ?>",
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
              });
            });
          </script>

          <script>
            document.addEventListener('DOMContentLoaded', function(){
              if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
              const $ = window.jQuery;
              $('.js-pp-hpl-piece').each(function(){
                const el = this;
                const $el = $(el);
                if ($el.data('select2')) return;
                const projId = parseInt(String(el.getAttribute('data-project-id') || ''), 10) || 0;
                function fmtPiece(opt){
                  if (!opt || !opt.id) return (opt && opt.text) ? opt.text : '';
                  const thumb = opt.thumb || null;
                  const thumbBack = opt.thumb_back || null;
                  const fc = opt.face_color_code || '';
                  const bc = opt.back_color_code || '';
                  let colors = fc ? String(fc) : '';
                  if (bc && bc !== fc) colors = colors ? (colors + '/' + String(bc)) : String(bc);

                  const th = (opt.thickness_mm !== undefined && opt.thickness_mm !== null) ? String(opt.thickness_mm) : '';
                  const code = String(opt.code || '');
                  const name = String(opt.name || '');
                  const ph = (opt.piece_height_mm !== undefined && opt.piece_height_mm !== null) ? String(opt.piece_height_mm) : '';
                  const pw = (opt.piece_width_mm !== undefined && opt.piece_width_mm !== null) ? String(opt.piece_width_mm) : '';
                  const dim = (ph && pw) ? (ph + '×' + pw) : '';
                  const pt = String(opt.piece_type || '');
                  const loc = String(opt.location || '');
                  const qty = (opt.qty !== undefined && opt.qty !== null) ? String(opt.qty) : '';

                  const esc = (s) => String(s || '').replace(/</g,'&lt;');
                  const $row = $('<span class="s2-row"></span>');
                  if (thumb) $row.append($('<img class="s2-thumb" />').attr('src', thumb));
                  if (thumbBack && thumbBack !== thumb) $row.append($('<img class="s2-thumb2" />').attr('src', thumbBack));

                  const $txt = $('<span></span>');
                  let tail = '';
                  // Cerință: coduri culori + thumbnails la început, apoi dimensiune/denumire/rest.
                  if (th) tail += esc(th) + 'mm';
                  if (dim) tail += (tail ? ' · ' : '') + esc(dim) + ' mm';
                  const nm = (code || name) ? (esc(code) + (name ? (' · ' + esc(name)) : '')) : '';
                  if (nm) tail += (tail ? ' · ' : '') + '<strong>' + nm + '</strong>';
                  if (pt) tail += (tail ? ' · ' : '') + esc(pt);
                  if (loc) tail += (tail ? ' · ' : '') + esc(loc);
                  if (qty) tail += (tail ? ' · ' : '') + 'buc: ' + esc(qty);

                  if (colors) $txt.html('<strong>' + esc(colors) + '</strong>' + (tail ? (' · ' + tail) : ''));
                  else $txt.html(tail || esc(opt.text || ''));
                  $row.append($txt);
                  return $row;
                }
                $el.select2({
                  width: '100%',
                  placeholder: 'Alege piesa HPL…',
                  allowClear: true,
                  minimumInputLength: 0,
                  templateResult: fmtPiece,
                  templateSelection: fmtPiece,
                  escapeMarkup: m => m,
                  ajax: {
                    url: "<?= htmlspecialchars(Url::to('/api/hpl/pieces/search')) ?>",
                    dataType: 'json',
                    delay: 250,
                    headers: { 'Accept': 'application/json' },
                    data: function(params){
                      const $form = $el.closest('form');
                      const src = $form.find('input.js-pp-hpl-source:checked').val() || 'PROJECT';
                      return { q: params.term || '', project_id: projId, source: src };
                    },
                    processResults: function(resp){
                      const items = (resp && resp.items) ? resp.items : [];
                      return { results: items };
                    },
                    cache: true
                  }
                });

                const $form = $el.closest('form');
                const $wrap = $form.find('.js-pp-hpl-consume-wrap');
                const $hidden = $form.find('.js-pp-hpl-consume-hidden');
                const $half = $form.find('.js-pp-hpl-half');
                function setConsumeMode(val){
                  if ($hidden.length) $hidden.val(val);
                }
                function updateConsumeUi(pieceType){
                  const src = String($form.find('input.js-pp-hpl-source:checked').val() || 'PROJECT');
                  const pt = String(pieceType || '').toUpperCase();
                  const show = (src === 'PROJECT' && pt === 'FULL');
                  if (show) {
                    $wrap.removeClass('d-none');
                  } else {
                    $wrap.addClass('d-none');
                    if ($half.length) $half.prop('checked', false);
                    setConsumeMode('FULL');
                  }
                }
                $half.on('change', function(){
                  setConsumeMode(this.checked ? 'HALF' : 'FULL');
                });
                $el.on('select2:select', function(e){
                  const data = (e && e.params && e.params.data) ? e.params.data : {};
                  updateConsumeUi(data.piece_type || '');
                });
                $el.on('select2:clear', function(){
                  updateConsumeUi('');
                });
                updateConsumeUi('');

                // toggle consume UI on source change (REST => hide half option)
                $form.find('input.js-pp-hpl-source').on('change', function(){
                  updateConsumeUi('');
                  // reset selection to refetch from correct source
                  $el.val(null).trigger('change');
                });
              });
            });
          </script>

          <style>
            .s2-thumb{width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;margin-right:10px}
            .s2-thumb2{width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;margin-right:10px;margin-left:-8px}
            .s2-row{display:flex;align-items:center}
            /* Make this select2 field taller (scoped) */
            .js-pp-hpl-reserved-select + .select2-container .select2-selection--single{
              min-height: 54px;
              padding: 8px 10px;
            }
            .js-pp-hpl-reserved-select + .select2-container .select2-selection--single .select2-selection__rendered{
              line-height: 34px;
              padding-left: 0;
              padding-right: 22px;
              font-size: 1.05rem;
            }
            .js-pp-hpl-reserved-select + .select2-container .select2-selection--single .select2-selection__arrow{
              height: 52px;
            }
            .js-pp-hpl-reserved-select + .select2-container .select2-selection--single .select2-selection__clear{
              margin-top: 6px;
            }
          </style>
          <script>
            document.addEventListener('DOMContentLoaded', function(){
              if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
              const $ = window.jQuery;
              function fmtBoard(opt){
                if (!opt.id) return opt.text;
                const thumb = opt.thumb || null;
                const thumbBack = opt.thumb_back || null;
                const fc = opt.face_color_code || '';
                const bc = opt.back_color_code || '';
                let colors = fc ? String(fc) : '';
                if (bc && bc !== fc) colors = colors ? (colors + '/' + String(bc)) : String(bc);

                const th = (opt.thickness_mm !== undefined && opt.thickness_mm !== null) ? String(opt.thickness_mm) : '';
                const name = String(opt.name || '');
                const h = (opt.std_height_mm !== undefined && opt.std_height_mm !== null) ? String(opt.std_height_mm) : '';
                const w = (opt.std_width_mm !== undefined && opt.std_width_mm !== null) ? String(opt.std_width_mm) : '';
                const dim = (h && w) ? (h + '×' + w) : String(opt.text || '');
                const ft = String(opt.face_texture_name || '');
                const bt = String(opt.back_texture_name || '');
                let tex = ft ? ft : '';
                if (bt && bt !== ft) tex = tex ? (tex + '/' + bt) : bt;
                // IMPORTANT: aici asociem doar tipul de placă (nu arătăm stoc global).

                if (!thumb && !thumbBack && !colors && !th && !name && !dim && !tex) return opt.text;
                const $row = $('<span class="s2-row"></span>');
                if (thumb) $row.append($('<img class="s2-thumb" />').attr('src', thumb));
                if (thumbBack && thumbBack !== thumb) $row.append($('<img class="s2-thumb2" />').attr('src', thumbBack));
                const $txt = $('<span></span>');
                const esc = (s) => String(s || '').replace(/</g,'&lt;');
                let base = '';
                if (th) base += esc(th) + 'mm';
                if (name) base += (base ? ' · ' : '') + '<strong>' + esc(name) + '</strong>';
                if (dim) base += (base ? ' · ' : '') + 'Dimensiune standard: ' + esc(dim);
                if (tex) base += (base ? ' · ' : '') + esc(tex);

                if (colors) $txt.html('<strong>' + esc(colors) + '</strong> · ' + base);
                else $txt.html(base);
                $row.append($txt);
                return $row;
              }
              function init(el){
                const $el = $(el);
                const projId = parseInt(String(el.getAttribute('data-project-id') || ''), 10) || 0;
                $el.select2({
                  width: '100%',
                  placeholder: 'Alege HPL rezervat…',
                  allowClear: true,
                  minimumInputLength: 0,
                  templateResult: fmtBoard,
                  templateSelection: fmtBoard,
                  escapeMarkup: m => m,
                  ajax: {
                    url: "<?= htmlspecialchars(Url::to('/api/hpl/boards/search')) ?>",
                    dataType: 'json',
                    delay: 250,
                    headers: { 'Accept': 'application/json' },
                    data: function(params){
                      return { q: params.term || '', project_id: projId, reserved_only: 1 };
                    },
                    transport: function (params, success, failure) {
                      const $ = window.jQuery;
                      if (!$ || !$.ajax) return $.ajax(params).then(success).catch(failure);
                      const req = $.ajax(params);
                      req.done(function (resp) {
                        if (resp && resp.ok === false) {
                          let msg = String(resp.error || 'Nu pot încărca plăcile HPL rezervate.');
                          if (resp.debug) msg += ' — ' + String(resp.debug);
                          if (window.toastr) window.toastr.error(msg);
                          success({ ok: true, items: [] });
                          return;
                        }
                        success(resp);
                      });
                      req.fail(function (xhr) {
                        let msg = 'Nu pot încărca plăcile HPL rezervate. (API)';
                        try {
                          const ct = String((xhr && xhr.getResponseHeader) ? (xhr.getResponseHeader('content-type') || '') : '');
                          if (ct.toLowerCase().includes('application/json') && xhr.responseJSON) {
                            msg = String(xhr.responseJSON.error || msg);
                            if (xhr.responseJSON.debug) msg += ' — ' + String(xhr.responseJSON.debug);
                          } else if (xhr && typeof xhr.status === 'number' && xhr.status) {
                            msg += ' HTTP ' + String(xhr.status);
                          }
                        } catch (e) {}
                        if (window.toastr) window.toastr.error(msg);
                        success({ ok: true, items: [] });
                        if (typeof failure === 'function') failure();
                      });
                      return req;
                    },
                    processResults: function(resp){
                      const items = (resp && resp.items) ? resp.items : [];
                      return { results: items };
                    },
                    cache: true
                  }
                });
              }

              document.querySelectorAll('select.js-pp-hpl-reserved-select').forEach(function (el) {
                init(el);
              });
            });
          </script>
        <?php endif; ?>
      </div>

      <div class="card app-card p-3">
        <div class="h5 m-0">Produse în proiect</div>
        <div class="text-muted">Status producție + cantități (livrate) — totul se loghează</div>

        <?php
          $canSeePricesRole = $u && in_array((string)($u['role'] ?? ''), [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);

          $ppStatusesAll = ProjectsController::projectProductStatuses();
          $ppStatusLabel = [];
          foreach ($ppStatusesAll as $s) $ppStatusLabel[(string)$s['value']] = (string)$s['label'];
          $canSetPPStatus = ProjectsController::canSetProjectProductStatus();
          $canSetPPFinal = ProjectsController::canSetProjectProductFinalStatus();
          $ppAllowedValues = array_map(fn($s) => (string)$s['value'], $ppStatusesAll);
          if (!$canSetPPFinal) $ppAllowedValues = array_values(array_filter($ppAllowedValues, fn($v) => !in_array($v, ['AVIZAT','LIVRAT'], true)));

          // HPL "stoc proiect" pentru CNC->Montaj (din hpl_stock_pieces):
          // - FULL RESERVED per board
          // - OFFCUT RESERVED (jumătate) per board
          $hplPieceRows = is_array($projectHplPieces ?? null) ? $projectHplPieces : [];
          $reservedFullByBoard = [];
          $reservedHalvesByBoard = [];
          foreach ($hplPieceRows as $p) {
            $bid = (int)($p['board_id'] ?? 0);
            if ($bid <= 0) continue;
            if ((string)($p['status'] ?? '') !== 'RESERVED') continue;
            $qty = (int)($p['qty'] ?? 0);
            if ($qty <= 0) continue;
            $type = (string)($p['piece_type'] ?? '');
            if ($type === 'FULL') {
              $reservedFullByBoard[$bid] = (int)($reservedFullByBoard[$bid] ?? 0) + $qty;
              continue;
            }
            if ($type !== 'OFFCUT') continue;
            $note = (string)($p['notes'] ?? '');
            $wmm = (int)($p['width_mm'] ?? 0);
            $hmm = (int)($p['height_mm'] ?? 0);
            $stdW = (int)($p['board_std_width_mm'] ?? 0);
            $stdH = (int)($p['board_std_height_mm'] ?? 0);
            $halfH = ($stdH > 0) ? (int)floor($stdH / 2.0) : 0;
            $isHalf = false;
            if ($note !== '' && strpos($note, 'REST_JUMATATE') === 0) $isHalf = true;
            elseif ($halfH > 0 && $stdW > 0 && $hmm === $halfH && $wmm === $stdW) $isHalf = true;
            if ($isHalf) {
              $reservedHalvesByBoard[$bid] = (int)($reservedHalvesByBoard[$bid] ?? 0) + $qty;
            }
          }
        ?>

        <?php if (!$projectProducts): ?>
          <div class="text-muted mt-2">Nu există produse încă.</div>
        <?php else: ?>
          <?php
            // Total bucăți (pentru împărțirea consumurilor la nivel de proiect)
            $ppTotalQty = 0.0;
            foreach ($projectProducts as $pp0) {
              $ppTotalQty += max(0.0, (float)($pp0['qty'] ?? 0));
            }
          ?>
          <?php if ($canSeePricesRole): ?>
            <div class="d-flex justify-content-end mt-2">
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" id="ppTogglePrices">
                <label class="form-check-label text-muted" for="ppTogglePrices">Afișează prețuri</label>
              </div>
            </div>
            <script>
              document.addEventListener('DOMContentLoaded', function(){
                const key = 'pp_show_prices_v1';
                const cb = document.getElementById('ppTogglePrices');
                if (!cb) return;
                const apply = (on) => {
                  document.querySelectorAll('.js-price').forEach(function(el){
                    if (on) el.classList.remove('d-none');
                    else el.classList.add('d-none');
                  });
                };
                try {
                  const saved = localStorage.getItem(key);
                  const on = saved === '1';
                  cb.checked = on;
                  apply(on);
                } catch (e) {}
                cb.addEventListener('change', function(){
                  const on = !!cb.checked;
                  apply(on);
                  try { localStorage.setItem(key, on ? '1' : '0'); } catch (e) {}
                });
              });
            </script>
          <?php endif; ?>

          <div class="row g-3 mt-2">
            <?php foreach ($projectProducts as $ppIdx => $pp): ?>
              <?php
                $ppId = (int)($pp['id'] ?? 0);
                $qty = (float)($pp['qty'] ?? 0);
                $del = (float)($pp['delivered_qty'] ?? 0);
                $pname = (string)($pp['product_name'] ?? '');
                $pcode = (string)($pp['product_code'] ?? '');
                $unitLabel = trim((string)($pp['unit'] ?? ''));
                if ($unitLabel === '') $unitLabel = 'buc';
                $qtyLabelTxt = $qty > 0 ? (number_format($qty, 2, '.', '') . ' ' . $unitLabel) : '';

                $lab = (isset($laborByProduct[$ppId]) && is_array($laborByProduct[$ppId])) ? $laborByProduct[$ppId] : null;
                $cncH = $lab ? (float)($lab['cnc_hours'] ?? 0.0) : 0.0;
                $cncC = $lab ? (float)($lab['cnc_cost'] ?? 0.0) : 0.0;
                $cncR = $lab ? (float)($lab['cnc_rate'] ?? 0.0) : 0.0;
                $atH = $lab ? (float)($lab['atelier_hours'] ?? 0.0) : 0.0;
                $atC = $lab ? (float)($lab['atelier_cost'] ?? 0.0) : 0.0;
                $atR = $lab ? (float)($lab['atelier_rate'] ?? 0.0) : 0.0;
                $manCost = $lab ? (float)($lab['total_cost'] ?? 0.0) : 0.0;

                $mat = (isset($materialsByProduct[$ppId]) && is_array($materialsByProduct[$ppId])) ? $materialsByProduct[$ppId] : null;
                $magCost = $mat ? (float)($mat['mag_cost'] ?? 0.0) : 0.0;
                $hplCost = $mat ? (float)($mat['hpl_cost'] ?? 0.0) : 0.0;
                $matCost = $magCost + $hplCost;
                $totalEst = $manCost + $matCost;
                $ppComments = isset($productComments[$ppId]) && is_array($productComments[$ppId]) ? $productComments[$ppId] : [];
                $ppCommentsCount = count($ppComments);
                $docLinks = $ppId > 0 && isset($docsByPp[$ppId]) && is_array($docsByPp[$ppId]) ? $docsByPp[$ppId] : [];

                $projectClientId = (int)($project['client_id'] ?? 0);
                $invoiceClientId = isset($pp['invoice_client_id']) ? (int)$pp['invoice_client_id'] : 0;
                if ($invoiceClientId <= 0) $invoiceClientId = $projectClientId;
                $deliveryAddressId = isset($pp['delivery_address_id']) ? (int)$pp['delivery_address_id'] : 0;
                $addrList = is_array(($billingAddresses[$invoiceClientId] ?? null)) ? $billingAddresses[$invoiceClientId] : [];
                $defaultAddrId = $deliveryAddressId;
                if ($defaultAddrId <= 0 && $addrList) {
                  foreach ($addrList as $addrRow) {
                    if ((int)($addrRow['is_default'] ?? 0) === 1) {
                      $defaultAddrId = (int)($addrRow['id'] ?? 0);
                      break;
                    }
                  }
                  if ($defaultAddrId <= 0) {
                    $defaultAddrId = (int)($addrList[0]['id'] ?? 0);
                  }
                }

                $qtyUnits = ($qty > 0.0) ? $qty : 0.0;
                $showPerUnit = ($qtyUnits > 1.0001);
                $cncHUnit = $showPerUnit ? ($cncH / $qtyUnits) : $cncH;
                $cncCUnit = $showPerUnit ? ($cncC / $qtyUnits) : $cncC;
                $atHUnit = $showPerUnit ? ($atH / $qtyUnits) : $atH;
                $atCUnit = $showPerUnit ? ($atC / $qtyUnits) : $atC;
                $manUnit = $showPerUnit ? ($manCost / $qtyUnits) : $manCost;
                $magUnit = $showPerUnit ? ($magCost / $qtyUnits) : $magCost;
                $hplUnit = $showPerUnit ? ($hplCost / $qtyUnits) : $hplCost;
                $matUnit = $showPerUnit ? ($matCost / $qtyUnits) : $matCost;
                $totUnit = $showPerUnit ? ($totalEst / $qtyUnits) : $totalEst;
              ?>
              <div class="col-12">
                <div class="card app-card p-3" id="pp-<?= (int)$ppId ?>">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                      <div class="h2 m-0 text-success">
                        <?= htmlspecialchars($pname) ?>
                        <?php if ($qtyLabelTxt !== ''): ?>
                          <span class="text-muted fw-semibold small">(<?= htmlspecialchars($qtyLabelTxt) ?>)</span>
                        <?php endif; ?>
                      </div>
                      <div class="text-muted small"><?= htmlspecialchars($pcode) ?></div>
                    </div>
                  </div>
                  <?php if ($ppStatusError && (int)($ppStatusError['id'] ?? 0) === $ppId): ?>
                    <div class="alert alert-danger py-2 px-3 mt-2 mb-0" role="alert">
                      <?= htmlspecialchars((string)($ppStatusError['message'] ?? '')) ?>
                    </div>
                  <?php endif; ?>

                  <?php
                    $stVal = (string)($pp['production_status'] ?? '');
                    $stLbl = $ppStatusLabel[$stVal] ?? $stVal;
                    $idx = array_search($stVal, $ppAllowedValues, true);
                  ?>
                  <?php if ($canSetPPStatus): ?>
                    <?php
                      $flowAll = array_map(fn($s) => (string)$s['value'], $ppStatusesAll);
                      $idxAll = array_search($stVal, $flowAll, true);
                      if ($idxAll === false) $idxAll = 0;
                      $nextVal = $flowAll[$idxAll + 1] ?? null;
                      $nextLbl = $nextVal !== null ? ($ppStatusLabel[$nextVal] ?? $nextVal) : null;
                      $canAdvance = ($nextVal !== null) && in_array($nextVal, $ppAllowedValues, true);
                    ?>
                    <div class="mt-2">
                      <div class="d-flex flex-wrap align-items-center gap-1">
                        <?php foreach ($ppStatusesAll as $i => $s): ?>
                          <?php
                            $v = (string)$s['value'];
                            $lbl = (string)$s['label'];
                            $isDone = ($i < $idxAll);
                            $isCur = ($i === $idxAll);
                            $isNext = ($i === $idxAll + 1);
                            $isVisible = true;
                            if (!$canSetPPFinal && in_array($v, ['AVIZAT','LIVRAT'], true) && !in_array($stVal, ['AVIZAT','LIVRAT'], true)) {
                              // Operator: arătăm statusurile finale ca "locked", dar nu le facem clickabile.
                              $isVisible = true;
                            }
                          ?>
                          <?php if (!$isVisible) continue; ?>

                          <?php if ($isNext && $canAdvance): ?>
                            <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/status')) ?>" class="m-0"
                                  <?= $nextVal === 'AVIZAT' ? 'data-aviz-required="1"' : '' ?>>
                              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                              <?php if ($nextVal === 'AVIZAT'): ?>
                                <input type="hidden" name="aviz_number" value="">
                              <?php endif; ?>
                              <button class="btn btn-sm btn-outline-success px-2 py-1" type="submit" title="Treci la următorul status">
                                <?= htmlspecialchars($lbl) ?>
                              </button>
                            </form>
                          <?php else: ?>
                            <?php
                              $cls = 'bg-secondary-subtle text-secondary-emphasis';
                              if ($isDone) $cls = 'bg-success-subtle text-success-emphasis';
                              if ($isCur) $cls = 'bg-success text-white';
                            ?>
                            <span class="badge rounded-pill <?= $cls ?> px-2 py-1"><?= htmlspecialchars($lbl) ?></span>
                          <?php endif; ?>

                          <?php if ($i < count($ppStatusesAll) - 1): ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </div>

                      <?php if (!$canSetPPFinal): ?>
                    <div class="text-muted small">Avizare/Livrat: doar Admin/Gestionar.</div>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>

                  <?php
                    $hbCode = trim((string)($pp['hpl_board_code'] ?? ''));
                    $hbName = trim((string)($pp['hpl_board_name'] ?? ''));
                  ?>
                  <?php // HPL se afișează în secțiunea "Consum" de mai jos. ?>

                  <?php
                    // Accesorii alocate (DIRECT + PROIECT), calculate în controller (respectă finalizarea piesei).
                    $accRows = [];
                    $mbr = $materialsByProduct[$ppId] ?? null;
                    if (is_array($mbr) && isset($mbr['acc_rows']) && is_array($mbr['acc_rows'])) {
                      $accRows = $mbr['acc_rows'];
                    }
                    // drepturi pentru acțiuni pe această piesă (folosit și în tabelul HPL pentru butonul "Debitat")
                    $canEditThis = $canEditProducts && ProjectsController::canOperatorEditProjectProduct($pp);
                    $hasReservedAcc = false;
                    foreach ($accRows as $ar) {
                      if ((string)($ar['mode'] ?? '') === 'RESERVED') {
                        $hasReservedAcc = true;
                        break;
                      }
                    }
                  ?>

                  <div class="mt-3">
                    <div class="mt-2">
                      <div class="d-flex justify-content-between align-items-center gap-2">
                        <div class="h5 m-0 text-success fw-semibold">Accesorii</div>
                        <?php if ($canEditThis && $hasReservedAcc): ?>
                          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/magazie/consume')) ?>" class="m-0"
                                onsubmit="return confirm('Dai în consum accesoriile rezervate pe acest produs?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <button class="btn btn-outline-success btn-sm" type="submit">Dat în consum</button>
                          </form>
                        <?php endif; ?>
                      </div>
                      <?php if (!$accRows): ?>
                        <div class="text-muted small">—</div>
                      <?php else: ?>
                        <div class="table-responsive mt-1">
                          <table class="table table-sm align-middle mb-0">
                            <thead>
                              <tr class="text-muted small">
                                <th>Accesoriu</th>
                                <th style="width:110px" class="text-end">Buc</th>
                                <?php if ($canSeePricesRole): ?>
                                  <th style="width:140px" class="text-end js-price d-none">Preț</th>
                                <?php endif; ?>
                                <th style="width:110px"></th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($accRows as $ar): ?>
                                <?php
                                  $aq = (float)($ar['qty'] ?? 0);
                                  if ($aq <= 0) continue;
                                  $unit = (string)($ar['unit'] ?? '');
                                  $mode = (string)($ar['mode'] ?? '');
                                  $srcTag = (string)($ar['src'] ?? '');
                                  $up = $ar['unit_price'];
                                  $val = ($up !== null) ? ($up * $aq) : null;
                                  $badgeCls = ($mode === 'CONSUMED') ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis';
                                  $showDeviz = (int)($ar['include_in_deviz'] ?? 1) === 1;
                                  $iid = (int)($ar['item_id'] ?? 0);
                                ?>
                                <tr>
                                  <td class="fw-semibold">
                                    <?= htmlspecialchars(trim((string)($ar['code'] ?? '') . ' · ' . (string)($ar['name'] ?? ''))) ?>
                                    <?php if ($srcTag === 'PROIECT'): ?>
                                      <span class="badge rounded-pill bg-light text-secondary-emphasis ms-1"><?= htmlspecialchars($srcTag) ?></span>
                                    <?php endif; ?>
                                    <?php if ($mode !== ''): ?>
                                      <span class="badge rounded-pill <?= $badgeCls ?> ms-1"><?= htmlspecialchars($mode) ?></span>
                                    <?php endif; ?>
                                    <?php if (!$showDeviz): ?>
                                      <span class="badge rounded-pill bg-light text-secondary-emphasis ms-1">fără deviz</span>
                                    <?php endif; ?>
                                  </td>
                                  <td class="text-end fw-semibold"><?= number_format($aq, 3, '.', '') ?> <?= htmlspecialchars($unit) ?></td>
                                  <?php if ($canSeePricesRole): ?>
                                    <td class="text-end js-price d-none">
                                      <?php if ($up !== null): ?>
                                        <?= number_format((float)$up, 2, '.', '') ?> × <?= number_format((float)$aq, 3, '.', '') ?>
                                        = <span class="fw-semibold"><?= number_format((float)$val, 2, '.', '') ?> lei</span>
                                      <?php else: ?>
                                        <span class="text-muted">—</span>
                                      <?php endif; ?>
                                    </td>
                                  <?php endif; ?>
                                  <td class="text-end">
                                    <?php if ($canEditThis && $mode === 'RESERVED' && $iid > 0 && $srcTag === 'DIRECT'): ?>
                                      <div class="d-flex justify-content-end gap-1 flex-wrap">
                                        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/magazie/' . $iid . '/update')) ?>" class="d-inline-flex align-items-center gap-1">
                                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                          <input type="hidden" name="src" value="<?= htmlspecialchars($srcTag) ?>">
                                          <input type="hidden" name="include_in_deviz" value="0">
                                          <input class="form-control form-control-sm" type="number" step="0.001" min="0.001" name="qty"
                                                 value="<?= htmlspecialchars(number_format((float)$aq, 3, '.', '')) ?>" style="width:92px">
                                          <label class="form-check m-0 d-flex align-items-center gap-1">
                                            <input class="form-check-input m-0" type="checkbox" name="include_in_deviz_flag" value="1" <?= $showDeviz ? 'checked' : '' ?>>
                                            <span class="small text-muted">Deviz</span>
                                          </label>
                                          <button class="btn btn-outline-primary btn-sm" type="submit">Salvează</button>
                                        </form>
                                        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/magazie/' . $iid . '/unallocate')) ?>" class="m-0"
                                              onsubmit="return confirm('Renunți la acest accesoriu rezervat pe produs?');">
                                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                          <input type="hidden" name="src" value="<?= htmlspecialchars($srcTag) ?>">
                                          <input type="hidden" name="qty" value="<?= htmlspecialchars(number_format((float)$aq, 3, '.', '')) ?>">
                                          <button class="btn btn-outline-danger btn-sm" type="submit">Renunță</button>
                                        </form>
                                      </div>
                                    <?php elseif ($canEditThis && $mode === 'RESERVED' && $iid > 0 && ($srcTag === 'DIRECT' || $srcTag === 'PROIECT')): ?>
                                      <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/magazie/' . $iid . '/unallocate')) ?>" class="m-0"
                                            onsubmit="return confirm('Renunți la acest accesoriu rezervat pe produs?');">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                        <input type="hidden" name="src" value="<?= htmlspecialchars($srcTag) ?>">
                                        <input type="hidden" name="qty" value="<?= htmlspecialchars(number_format((float)$aq, 3, '.', '')) ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Renunță</button>
                                      </form>
                                    <?php else: ?>
                                      <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                  </td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php endif; ?>
                    </div>

                    <?php
                      $hplRows = [];
                      if (is_array($mbr) && isset($mbr['hpl_rows']) && is_array($mbr['hpl_rows'])) {
                        $hplRows = $mbr['hpl_rows'];
                      }
                    ?>
                    <div class="mt-2">
                      <div class="h5 m-0 text-success fw-semibold">Consum HPL</div>
                      <?php if (!$hplRows): ?>
                        <div class="text-muted small">—</div>
                      <?php else: ?>
                        <div class="table-responsive mt-1">
                          <table class="table table-sm align-middle mb-0">
                            <thead>
                              <tr class="text-muted small">
                                <th>Placă</th>
                                <th style="width:90px">Tip</th>
                                <th style="width:110px">Status</th>
                                <th style="width:160px">Dimensiuni</th>
                                <th class="text-end" style="width:80px">Buc</th>
                                <th style="width:120px">Locație</th>
                                <th>Notă</th>
                                <th class="text-end" style="width:90px">mp</th>
                                <th style="width:80px">Mod</th>
                                <th style="width:80px">Sursă</th>
                                <th style="width:150px">Dată</th>
                                <th style="width:120px"></th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($hplRows as $hr): ?>
                                <?php
                                  $hrId = (int)($hr['id'] ?? 0);
                                  $bcode2 = (string)($hr['board_code'] ?? '');
                                  $bname2 = (string)($hr['board_name'] ?? '');
                                  // preferăm piesa consumată dacă există, altfel piesa rezervată
                                  $pt2 = (string)($hr['consumed_piece_type'] ?? '');
                                  $pw2 = (int)($hr['consumed_piece_width_mm'] ?? 0);
                                  $ph2 = (int)($hr['consumed_piece_height_mm'] ?? 0);
                                  $pq2 = (int)($hr['consumed_piece_qty'] ?? 0);
                                  $pl2 = (string)($hr['consumed_piece_location'] ?? '');
                                  $pn2 = (string)($hr['consumed_piece_notes'] ?? '');
                                  $pm2 = isset($hr['consumed_piece_area_total_m2']) ? (float)($hr['consumed_piece_area_total_m2'] ?? 0) : 0.0;
                                  if ($pt2 === '' && $pw2 === 0 && $ph2 === 0) {
                                    $pt2 = (string)($hr['piece_type'] ?? '');
                                    $pw2 = (int)($hr['piece_width_mm'] ?? 0);
                                    $ph2 = (int)($hr['piece_height_mm'] ?? 0);
                                    $pq2 = (int)($hr['piece_qty'] ?? 0);
                                    $pl2 = (string)($hr['piece_location'] ?? '');
                                    $pn2 = (string)($hr['piece_notes'] ?? '');
                                    $pm2 = isset($hr['piece_area_total_m2']) ? (float)($hr['piece_area_total_m2'] ?? 0) : 0.0;
                                  }
                                  $cm2 = (string)($hr['consume_mode'] ?? '');
                                  $src2 = (string)($hr['source'] ?? '');
                                  $st2 = (string)($hr['status'] ?? '');
                                  $bid2 = (int)($hr['board_id'] ?? 0);
                                  $boardTxt = trim($bcode2 . ' · ' . $bname2);
                                  $fc2 = trim((string)($hr['face_color_code'] ?? ''));
                                  $bc2 = trim((string)($hr['back_color_code'] ?? ''));
                                  $thumbF = trim((string)($hr['face_thumb'] ?? ''));
                                  $thumbB = trim((string)($hr['back_thumb'] ?? ''));
                                  $colors2 = $fc2;
                                  if ($bc2 !== '' && $bc2 !== $fc2) {
                                    $colors2 = $colors2 !== '' ? ($colors2 . '/' . $bc2) : $bc2;
                                  }
                                  $dimTxt = ($ph2 > 0 && $pw2 > 0) ? ($ph2 . ' × ' . $pw2 . ' mm') : '—';
                                  $noteTxt = trim($pn2);
                                  if ($noteTxt !== '' && mb_strlen($noteTxt) > 110) $noteTxt = mb_substr($noteTxt, 0, 110) . '…';
                                  $createdAt = (string)($hr['created_at'] ?? '');
                                ?>
                                <tr>
                                  <td class="fw-semibold">
                                    <?php
                                      $boardLabel = $boardTxt !== '' ? $boardTxt : '—';
                                      $colorLabel = $colors2 !== '' ? $colors2 : '';
                                    ?>
                                    <?php if ($bid2 > 0): ?>
                                      <a class="text-decoration-none" href="<?= htmlspecialchars(Url::to('/stock/boards/' . $bid2)) ?>">
                                        <span class="d-inline-flex align-items-center">
                                          <?php if ($thumbF !== ''): ?>
                                            <img class="s2-thumb" src="<?= htmlspecialchars($thumbF) ?>" alt="">
                                          <?php endif; ?>
                                          <?php if ($thumbB !== '' && $thumbB !== $thumbF): ?>
                                            <img class="s2-thumb2" src="<?= htmlspecialchars($thumbB) ?>" alt="">
                                          <?php endif; ?>
                                          <span>
                                            <?php if ($colorLabel !== ''): ?>
                                              <strong><?= htmlspecialchars($colorLabel) ?></strong> ·
                                            <?php endif; ?>
                                            <?= htmlspecialchars($boardLabel) ?>
                                          </span>
                                        </span>
                                      </a>
                                    <?php else: ?>
                                      <?php if ($colorLabel !== ''): ?>
                                        <strong><?= htmlspecialchars($colorLabel) ?></strong> ·
                                      <?php endif; ?>
                                      <?= htmlspecialchars($boardLabel) ?>
                                    <?php endif; ?>
                                  </td>
                                  <td class="fw-semibold"><?= htmlspecialchars($pt2 !== '' ? $pt2 : '—') ?></td>
                                  <td><span class="badge app-badge"><?= htmlspecialchars($st2) ?></span></td>
                                  <td class="text-muted"><?= htmlspecialchars($dimTxt) ?></td>
                                  <td class="text-end fw-semibold"><?= $pq2 > 0 ? (int)$pq2 : '—' ?></td>
                                  <td class="text-muted"><?= htmlspecialchars($pl2) ?></td>
                                  <td class="text-muted small" style="max-width:420px;white-space:pre-line"><?= htmlspecialchars($noteTxt) ?></td>
                                  <td class="text-end fw-semibold"><?= $pm2 > 0 ? number_format((float)$pm2, 2, '.', '') : '—' ?></td>
                                  <td><?= htmlspecialchars($cm2) ?></td>
                                  <td><?= htmlspecialchars($src2) ?></td>
                                  <td class="text-muted small"><?= htmlspecialchars($createdAt) ?></td>
                                  <td class="text-end">
                                    <?php if (($canEditProducts && ProjectsController::canOperatorEditProjectProduct($pp)) && $st2 === 'RESERVED' && $hrId > 0): ?>
                                      <div class="d-flex justify-content-end gap-1">
                                        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/hpl/' . $hrId . '/cut')) ?>" class="m-0">
                                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                          <button class="btn btn-outline-success btn-sm" type="submit">Debitat</button>
                                        </form>
                                        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/hpl/' . $hrId . '/unallocate')) ?>" class="m-0"
                                              onsubmit="return confirm('Renunți la această alocare HPL (revine în stoc)?');">
                                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                          <button class="btn btn-outline-danger btn-sm" type="submit">Renunță</button>
                                        </form>
                                      </div>
                                    <?php else: ?>
                                      <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                  </td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php endif; ?>
                    </div>

                    <div class="mt-2">
                      <div class="h5 m-0 text-success fw-semibold">Manopere</div>
                      <div class="text-muted small">
                        CNC: <span class="fw-semibold"><?= number_format((float)$cncH, 2, '.', '') ?>h</span>
                        <?php if ($canSeePricesRole): ?>
                          <span class="js-price d-none"> · <span class="fw-semibold"><?= number_format((float)$cncC, 2, '.', '') ?> lei</span></span>
                        <?php endif; ?>
                        <span class="text-muted"> · </span>
                        Atelier: <span class="fw-semibold"><?= number_format((float)$atH, 2, '.', '') ?>h</span>
                        <?php if ($canSeePricesRole): ?>
                          <span class="js-price d-none"> · <span class="fw-semibold"><?= number_format((float)$atC, 2, '.', '') ?> lei</span></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>

                  <?php if ($docLinks): ?>
                    <div class="d-flex flex-column align-items-end mt-2">
                      <?php if (isset($docLinks['deviz'])): ?>
                        <?php $d = $docLinks['deviz']; ?>
                        <a class="text-decoration-none small" href="<?= htmlspecialchars(Url::to('/uploads/files/' . (string)($d['stored_name'] ?? ''))) ?>" target="_blank" rel="noopener">
                          <i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars((string)($d['label'] ?? 'Deviz')) ?>
                        </a>
                      <?php endif; ?>
                      <?php if (isset($docLinks['bon'])): ?>
                        <?php $b = $docLinks['bon']; ?>
                        <a class="text-decoration-none small" href="<?= htmlspecialchars(Url::to('/uploads/files/' . (string)($b['stored_name'] ?? ''))) ?>" target="_blank" rel="noopener">
                          <i class="bi bi-receipt me-1"></i><?= htmlspecialchars((string)($b['label'] ?? 'Bon consum')) ?>
                        </a>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>

                  <?php if ($canEditThis): ?>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                      <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#ppHpl<?= $ppId ?>">
                        <i class="bi bi-layers me-1"></i> Consum HPL
                      </button>
                      <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#ppAcc<?= $ppId ?>">
                        <i class="bi bi-box-seam me-1"></i> Adaugă accesorii
                      </button>
                      <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#ppLabor<?= $ppId ?>">
                        <i class="bi bi-tools me-1"></i> Adaugă manoperă
                      </button>
                      <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#ppBill<?= $ppId ?>">
                        <i class="bi bi-receipt me-1"></i> Facturare/Livrare
                      </button>
                      <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#ppObs<?= $ppId ?>">
                        <i class="bi bi-chat-dots me-1"></i> Observații(<?= (int)$ppCommentsCount ?>)
                      </button>
                      <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#ppEdit<?= $ppId ?>">
                        <i class="bi bi-pencil me-1"></i> Editează
                      </button>
                      <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/unlink')) ?>" class="m-0"
                            onsubmit="return confirm('Scoți produsul din proiect?');">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                        <button class="btn btn-outline-secondary btn-sm" type="submit">
                          <i class="bi bi-link-45deg me-1"></i> Scoate
                        </button>
                      </form>
                    </div>

                    <div class="collapse mt-3" id="ppHpl<?= $ppId ?>">
                      <div class="p-2 rounded" style="background:#F3F7F8;border:1px solid #D9E3E6">
                        <div class="fw-semibold">Consum HPL (alocare pe produs)</div>
                        <div class="text-muted small">Aloci din piesele rezervate pe proiect sau din plăci REST (nestocate). Consumul efectiv se face manual din butonul „Debitat” din tabelul HPL.</div>

                        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/hpl/create')) ?>" class="row g-2 mt-2 js-pp-hpl-form">
                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

                          <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold mb-1">Sursă</label>
                            <div class="d-flex flex-wrap gap-3">
                              <label class="form-check form-check-inline m-0">
                                <input class="form-check-input js-pp-hpl-source" type="radio" name="source" value="PROJECT" checked>
                                <span class="form-check-label">Din proiect (rezervat)</span>
                              </label>
                              <label class="form-check form-check-inline m-0">
                                <input class="form-check-input js-pp-hpl-source" type="radio" name="source" value="REST">
                                <span class="form-check-label">REST (nestocat)</span>
                              </label>
                            </div>
                          </div>

                          <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold mb-1">Placă / piesă</label>
                            <select class="form-select form-select-sm js-pp-hpl-piece" name="piece_id" data-project-id="<?= (int)$project['id'] ?>" style="width:100%"></select>
                          </div>

                          <div class="col-12 col-md-4 js-pp-hpl-consume-wrap">
                            <label class="form-label fw-semibold mb-1">Consum</label>
                            <input type="hidden" name="consume_mode" value="FULL" class="js-pp-hpl-consume-hidden">
                            <label class="form-check m-0">
                              <input class="form-check-input js-pp-hpl-half" type="checkbox" value="HALF">
                              <span class="form-check-label">1/2 placă (din FULL)</span>
                            </label>
                            <div class="text-muted small mt-1">Disponibil doar pentru plăci FULL din proiect.</div>
                          </div>

                          <div class="col-12 d-flex justify-content-end">
                            <button class="btn btn-primary btn-sm" type="submit">
                              <i class="bi bi-plus-lg me-1"></i> Adaugă
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>

                    <div class="collapse mt-3" id="ppLabor<?= $ppId ?>">
                      <div class="p-2 rounded" style="background:#F3F7F8;border:1px solid #D9E3E6">
                        <div class="fw-semibold">Manoperă (CNC / Atelier)</div>
                        <div class="text-muted small">Înregistrare estimată pentru acest produs.</div>
                        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/hours/create')) ?>" class="row g-2 mt-2">
                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                          <input type="hidden" name="project_product_id" value="<?= (int)$ppId ?>">
                          <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold mb-1">Tip</label>
                            <select class="form-select form-select-sm" name="work_type">
                              <option value="CNC">CNC</option>
                              <option value="ATELIER">Atelier</option>
                            </select>
                          </div>
                          <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold mb-1">Ore estimate</label>
                            <input class="form-control form-control-sm" type="number" step="0.01" min="0.01" name="hours_estimated" required>
                          </div>
                          <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold mb-1">Notă</label>
                            <input class="form-control form-control-sm" name="note" maxlength="255">
                          </div>
                          <div class="col-12 d-flex justify-content-end">
                            <button class="btn btn-primary btn-sm" type="submit">
                              <i class="bi bi-plus-lg me-1"></i> Adaugă
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>

                    <div class="collapse mt-3" id="ppObs<?= $ppId ?>">
                      <div class="p-2 rounded" style="background:#F3F7F8;border:1px solid #D9E3E6">
                        <div class="fw-semibold">Observații produs</div>
                        <div class="text-muted small">Mesaje pe produs (cu user + dată/oră).</div>
                        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/comments/create')) ?>" class="mt-2">
                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                          <label class="form-label fw-semibold mb-1">Mesaj</label>
                          <textarea class="form-control form-control-sm" name="comment" rows="2" maxlength="4000" placeholder="Scrie observația…"></textarea>
                          <div class="d-flex justify-content-end mt-2">
                            <button class="btn btn-primary btn-sm" type="submit">
                              <i class="bi bi-send me-1"></i> Trimite
                            </button>
                          </div>
                        </form>
                        <hr class="my-3">
                        <?php if (!$ppComments): ?>
                          <div class="text-muted small">Nu există observații încă.</div>
                        <?php else: ?>
                          <div class="d-flex flex-column gap-2">
                            <?php foreach ($ppComments as $m): ?>
                              <?php
                                $who = (string)($m['user_name'] ?? '');
                                if ($who === '') $who = (string)($m['user_email'] ?? '');
                                if ($who === '') $who = '—';
                                $dt = (string)($m['created_at'] ?? '');
                                $txt = (string)($m['comment'] ?? '');
                              ?>
                              <div class="p-2 rounded" style="background:#F7FAFB;border:1px solid #D9E3E6">
                                <div class="d-flex justify-content-between gap-2">
                                  <div class="fw-semibold"><?= htmlspecialchars($who) ?></div>
                                  <div class="text-muted small"><?= htmlspecialchars($dt) ?></div>
                                </div>
                                <div class="mt-1"><?= nl2br(htmlspecialchars($txt)) ?></div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="collapse mt-3" id="ppAcc<?= $ppId ?>">
                      <div class="p-2 rounded" style="background:#F3F7F8;border:1px solid #D9E3E6">
                        <div class="fw-semibold">Accesorii (rezervate pentru acest produs)</div>
                        <div class="text-muted small">Se rezervă automat. La “Gata de livrare” se consumă din stoc.</div>
                        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/magazie/create')) ?>" class="row g-2 mt-2">
                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                          <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold mb-1">Accesoriu</label>
                            <select class="form-select form-select-sm js-pp-magazie-item" name="item_id" data-pp-id="<?= (int)$ppId ?>" style="width:100%"></select>
                          </div>
                          <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold mb-1">Cantitate</label>
                            <input class="form-control form-control-sm" type="number" step="0.001" min="0.001" name="qty" value="1" required>
                          </div>
                          <div class="col-12">
                            <input type="hidden" name="include_in_deviz" value="0">
                            <label class="form-check m-0">
                              <input class="form-check-input" type="checkbox" name="include_in_deviz_flag" value="1" checked>
                              <span class="form-check-label">Apare pe deviz</span>
                            </label>
                          </div>
                          <div class="col-12 d-flex justify-content-end">
                            <button class="btn btn-primary btn-sm" type="submit">
                              <i class="bi bi-plus-lg me-1"></i> Adaugă
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                    <div class="collapse mt-3" id="ppBill<?= $ppId ?>">
                      <div class="p-2 rounded" style="background:#F3F7F8;border:1px solid #D9E3E6">
                        <div class="fw-semibold">Facturare & livrare</div>
                        <div class="text-muted small">Alege firma de facturare și adresa de livrare pentru acest produs.</div>
                        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/billing/update')) ?>" class="row g-2 mt-2">
                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                          <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold mb-1">Firmă facturare</label>
                            <select class="form-select form-select-sm js-pp-bill-client" name="invoice_client_id"
                                    data-addr-target="ppBillAddr<?= $ppId ?>" data-default-addr="<?= (int)$defaultAddrId ?>"
                                    <?= $billingClients ? '' : 'disabled' ?>>
                              <?php if (!$billingClients): ?>
                                <option value="">Nu există firme</option>
                              <?php else: ?>
                                <option value="">—</option>
                                <?php foreach ($billingClients as $bc): ?>
                                  <?php $bcId = (int)($bc['id'] ?? 0); ?>
                                  <option value="<?= $bcId ?>" <?= $bcId > 0 && $bcId === (int)$invoiceClientId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)($bc['name'] ?? '')) ?>
                                  </option>
                                <?php endforeach; ?>
                              <?php endif; ?>
                            </select>
                          </div>
                          <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold mb-1">Adresă livrare</label>
                            <select class="form-select form-select-sm" name="delivery_address_id" id="ppBillAddr<?= $ppId ?>" <?= $billingClients ? '' : 'disabled' ?>>
                              <?php if (!$addrList): ?>
                                <option value="">Nu există adrese</option>
                              <?php else: ?>
                                <option value="">—</option>
                                <?php foreach ($addrList as $addrRow): ?>
                                  <?php
                                    $addrId = (int)($addrRow['id'] ?? 0);
                                    $addrLabel = trim((string)($addrRow['label'] ?? ''));
                                    $addrText = trim((string)($addrRow['address'] ?? ''));
                                    $addrFull = trim($addrLabel !== '' ? ($addrLabel . ' · ' . $addrText) : $addrText);
                                  ?>
                                  <option value="<?= $addrId ?>" <?= $addrId > 0 && $addrId === (int)$defaultAddrId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($addrFull !== '' ? $addrFull : ('Adresă #' . $addrId)) ?>
                                  </option>
                                <?php endforeach; ?>
                              <?php endif; ?>
                            </select>
                          </div>
                          <div class="col-12 d-flex justify-content-end">
                            <button class="btn btn-primary btn-sm" type="submit">
                              <i class="bi bi-save me-1"></i> Salvează
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                    <div class="collapse mt-3" id="ppEdit<?= $ppId ?>">
                      <?php
                        $pDesc = (string)($pp['product_notes'] ?? '');
                        $pSale = isset($pp['product_sale_price']) && $pp['product_sale_price'] !== null && $pp['product_sale_price'] !== '' ? (float)$pp['product_sale_price'] : null;
                        $curM2 = isset($pp['m2_per_unit']) ? (float)($pp['m2_per_unit'] ?? 0) : 0.0;
                        $curSurfaceType = (string)($pp['surface_type'] ?? '');
                        $curSurfaceVal = isset($pp['surface_value']) && $pp['surface_value'] !== null && $pp['surface_value'] !== '' ? (float)$pp['surface_value'] : null;
                        $curHplId = isset($pp['hpl_board_id']) && $pp['hpl_board_id'] !== null && $pp['hpl_board_id'] !== '' ? (int)$pp['hpl_board_id'] : 0;
                        $curHplText = trim((string)($pp['hpl_board_code'] ?? '') . ' · ' . (string)($pp['hpl_board_name'] ?? ''));
                        if ($curHplText === '·' || $curHplText === '· ') $curHplText = '';
                        if ($curSurfaceType === '' && $curM2 > 0) { $curSurfaceType = 'M2'; $curSurfaceVal = round($curM2, 2); }
                      ?>
                      <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/update')) ?>" class="row g-2">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                        <?php
                          $uiName = 'surface_mode_ui_' . (int)$ppId;
                          $curMode = ($curSurfaceType === 'BOARD' && $curSurfaceVal !== null)
                            ? (abs($curSurfaceVal - 0.5) < 1e-9 ? '0.5' : '1')
                            : 'M2';
                        ?>
                        <input type="hidden" name="surface_mode" value="<?= htmlspecialchars($curMode) ?>" class="js-surface-mode-hidden">
                        <div class="col-12">
                          <label class="form-label fw-semibold mb-1">Denumire</label>
                          <input class="form-control form-control-sm" name="name" required value="<?= htmlspecialchars($pname) ?>">
                        </div>
                        <div class="col-12">
                          <label class="form-label fw-semibold mb-1">Descriere</label>
                          <textarea class="form-control form-control-sm" name="description" rows="2" maxlength="4000" placeholder="Opțional…"><?= htmlspecialchars($pDesc) ?></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                          <label class="form-label fw-semibold mb-1">Cod (opțional)</label>
                          <input class="form-control form-control-sm" name="code" value="<?= htmlspecialchars($pcode) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                          <label class="form-label fw-semibold mb-1">Cantitate</label>
                          <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="qty" value="<?= htmlspecialchars((string)$qty) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                          <label class="form-label fw-semibold mb-1">Preț vânzare (lei)</label>
                          <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="sale_price"
                                 value="<?= $pSale !== null ? htmlspecialchars(number_format((float)$pSale, 2, '.', '')) : '' ?>"
                                 placeholder="opțional">
                        </div>
                        <div class="col-12">
                          <label class="form-label fw-semibold mb-1">Suprafață</label>
                          <div class="d-flex flex-wrap gap-3">
                            <label class="form-check form-check-inline m-0">
                              <input class="form-check-input js-surface-mode-radio" type="radio" name="<?= htmlspecialchars($uiName) ?>" value="0.5" required <?= ($curMode === '0.5') ? 'checked' : '' ?>>
                              <span class="form-check-label">1/2 placă</span>
                            </label>
                            <label class="form-check form-check-inline m-0">
                              <input class="form-check-input js-surface-mode-radio" type="radio" name="<?= htmlspecialchars($uiName) ?>" value="1" required <?= ($curMode === '1') ? 'checked' : '' ?>>
                              <span class="form-check-label">1 placă</span>
                            </label>
                            <label class="form-check form-check-inline m-0">
                              <input class="form-check-input js-surface-mode-radio" type="radio" name="<?= htmlspecialchars($uiName) ?>" value="M2" required <?= ($curMode === 'M2') ? 'checked' : '' ?>>
                              <span class="form-check-label">mp</span>
                            </label>
                          </div>
                          <div class="mt-2 <?= ($curSurfaceType === 'BOARD') ? 'd-none' : '' ?>" id="ppEditSurfaceM2Wrap<?= $ppId ?>">
                            <input class="form-control form-control-sm" type="number" step="0.01" min="0.01" name="surface_m2" value="<?= htmlspecialchars(number_format(max(0.01, ($curSurfaceType === 'M2' && $curSurfaceVal !== null) ? $curSurfaceVal : ($curM2 > 0 ? round($curM2, 2) : 0.01)), 2, '.', '')) ?>">
                            <div class="text-muted small mt-1">Suprafață per bucată (mp).</div>
                          </div>
                        </div>
                        <?php /* HPL-ul pe piesă se gestionează prin butonul "Consum HPL". */ ?>
                        <div class="col-12 d-flex justify-content-end">
                          <button class="btn btn-primary btn-sm" type="submit">
                            <i class="bi bi-save me-1"></i> Salvează
                          </button>
                        </div>
                      </form>
                      <script>
                        document.addEventListener('DOMContentLoaded', function () {
                          const root = document.getElementById('ppEdit<?= (int)$ppId ?>');
                          if (!root) return;
                          const hidden = root.querySelector('input.js-surface-mode-hidden');
                          const wrap = document.getElementById('ppEditSurfaceM2Wrap<?= (int)$ppId ?>');
                          const radios = root.querySelectorAll('input.js-surface-mode-radio');
                          if (!hidden || !wrap || !radios.length) return;
                          function sync() {
                            let v = '';
                            radios.forEach(function (r) { if (r.checked) v = String(r.value || ''); });
                            if (v) hidden.value = v;
                            if (v === 'M2') wrap.classList.remove('d-none');
                            else wrap.classList.add('d-none');
                          }
                          radios.forEach(function (r) { r.addEventListener('change', sync); });
                          sync();
                        });
                      </script>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function(){
        const addrMap = <?= json_encode($billingAddresses ?? [], JSON_UNESCAPED_UNICODE) ?>;
        function addrLabel(a){
          const label = a && a.label ? String(a.label) : '';
          const addr = a && a.address ? String(a.address) : '';
          return (label && addr) ? (label + ' · ' + addr) : (label || addr);
        }
        function buildAddrOptions(select, clientId, selectedId){
          if (!select) return;
          const list = (clientId && addrMap && addrMap[clientId]) ? addrMap[clientId] : [];
          let selected = String(selectedId || '');
          if (!selected && list && list.length) {
            const def = list.find(a => a && String(a.is_default || '') === '1');
            if (def && def.id) selected = String(def.id);
          }
          select.innerHTML = '';
          if (!list || !list.length) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'Nu există adrese';
            select.appendChild(opt);
            select.disabled = true;
            return;
          }
          select.disabled = false;
          const empty = document.createElement('option');
          empty.value = '';
          empty.textContent = '—';
          select.appendChild(empty);
          list.forEach(function(a){
            const opt = document.createElement('option');
            const id = a && a.id ? String(a.id) : '';
            opt.value = id;
            opt.textContent = addrLabel(a) || ('Adresă #' + id);
            if (id !== '' && selected === id) opt.selected = true;
            select.appendChild(opt);
          });
        }
        document.querySelectorAll('.js-pp-bill-client').forEach(function(sel){
          const targetId = sel.getAttribute('data-addr-target') || '';
          const addrSel = targetId ? document.getElementById(targetId) : null;
          if (!addrSel) return;
          const defaultAddr = sel.getAttribute('data-default-addr') || '';
          buildAddrOptions(addrSel, sel.value, defaultAddr);
          sel.addEventListener('change', function(){
            buildAddrOptions(addrSel, sel.value, '');
          });
        });
      });
    </script>

    <div class="col-12">
      <div class="card app-card p-3 mb-3">
        <div class="h5 m-0">Sumar costuri proiect</div>
        <div class="text-muted">Manoperă + materiale + HPL rezervat (neconsumat)</div>
        <?php
          $sum = is_array($projectCostSummary ?? null) ? $projectCostSummary : [];
          $sumLabor = (float)($sum['labor_cost'] ?? 0);
          $sumMag = (float)($sum['mag_cost'] ?? 0);
          $sumHpl = (float)($sum['hpl_cost'] ?? 0);
          $sumTotal = (float)($sum['total_cost'] ?? 0);
          $resM2 = (float)($sum['hpl_reserved_remaining_m2'] ?? 0);
          $resCost = (float)($sum['hpl_reserved_remaining_cost'] ?? 0);
          $cncH = (float)($sum['labor_cnc_hours'] ?? 0);
          $atH = (float)($sum['labor_atelier_hours'] ?? 0);
          $hplRes = (float)($sum['hpl_reserved_m2'] ?? 0);
          $hplCon = (float)($sum['hpl_consumed_m2'] ?? 0);
          $needM2 = (float)($sum['products_need_m2'] ?? 0);
          $prodHplM2 = (float)($sum['products_hpl_m2'] ?? 0);
          $magCon = is_array($sum['mag_consumed_by_unit'] ?? null) ? $sum['mag_consumed_by_unit'] : [];
          $magRes = is_array($sum['mag_reserved_by_unit'] ?? null) ? $sum['mag_reserved_by_unit'] : [];
          $magItems = is_array($sum['mag_items'] ?? null) ? $sum['mag_items'] : [];
          $fmtUnits = function(array $m): string {
            if (!$m) return '—';
            $parts = [];
            foreach ($m as $u => $q) {
              $parts[] = number_format((float)$q, 3, '.', '') . ' ' . (string)$u;
            }
            return implode(', ', $parts);
          };
        ?>
        <div class="mt-2">
          <div class="d-flex justify-content-between">
            <div class="text-muted">Manoperă (estim.)</div>
            <div class="fw-semibold"><?= number_format($sumLabor, 2, '.', '') ?> lei</div>
          </div>
          <div class="text-muted small mt-1">
            CNC: <?= number_format($cncH, 2, '.', '') ?> h · Atelier: <?= number_format($atH, 2, '.', '') ?> h
          </div>
          <div class="d-flex justify-content-between mt-1">
            <div class="text-muted">Materiale Magazie</div>
            <div class="fw-semibold"><?= number_format($sumMag, 2, '.', '') ?> lei</div>
          </div>
          <div class="text-muted small mt-1">
            Consum: <?= htmlspecialchars($fmtUnits($magCon)) ?> · Rezervat: <?= htmlspecialchars($fmtUnits($magRes)) ?>
          </div>
          <?php if ($magItems): ?>
            <div class="mt-2">
              <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#magItemsSummary">
                Detaliu accesorii
              </button>
              <div class="collapse mt-2" id="magItemsSummary">
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Accesoriu</th>
                        <th class="text-end" style="width:120px">Consum</th>
                        <th class="text-end" style="width:120px">Rezervat</th>
                        <th class="text-end" style="width:120px">Preț</th>
                        <th class="text-end" style="width:140px">Valoare</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($magItems as $it): ?>
                        <?php
                          $code = trim((string)($it['winmentor_code'] ?? ''));
                          $name = trim((string)($it['item_name'] ?? ''));
                          $unit = (string)($it['unit'] ?? 'buc');
                          $qc = (float)($it['qty_consumed'] ?? 0.0);
                          $qr = (float)($it['qty_reserved'] ?? 0.0);
                          $price = (float)($it['unit_price'] ?? 0.0);
                          $val = ($qc + $qr) * $price;
                          if (($qc + $qr) <= 0) continue;
                        ?>
                        <tr>
                          <td>
                            <div class="fw-semibold">
                              <?= htmlspecialchars(($code !== '' ? ($code . ' · ') : '') . ($name !== '' ? $name : ('#' . (int)($it['item_id'] ?? 0)))) ?>
                            </div>
                            <div class="text-muted small"><?= htmlspecialchars($unit) ?></div>
                          </td>
                          <td class="text-end"><?= $qc > 0 ? number_format($qc, 3, '.', '') . ' ' . htmlspecialchars($unit) : '—' ?></td>
                          <td class="text-end"><?= $qr > 0 ? number_format($qr, 3, '.', '') . ' ' . htmlspecialchars($unit) : '—' ?></td>
                          <td class="text-end"><?= number_format($price, 2, '.', '') ?></td>
                          <td class="text-end fw-semibold"><?= number_format($val, 2, '.', '') ?> lei</td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          <?php endif; ?>
          <div class="d-flex justify-content-between mt-1">
            <div class="text-muted">Materiale HPL</div>
            <div class="fw-semibold"><?= number_format($sumHpl, 2, '.', '') ?> lei</div>
          </div>
          <div class="text-muted small mt-1">
            Rezervat: <?= number_format($hplRes, 2, '.', '') ?> mp · Consumat: <?= number_format($hplCon, 2, '.', '') ?> mp ·
            Produse (mp): <?= number_format($prodHplM2, 2, '.', '') ?> mp (din mp/buc: <?= number_format($needM2, 2, '.', '') ?> mp)
          </div>
          <hr class="my-2">
          <div class="d-flex justify-content-between">
            <div class="text-muted fw-semibold">Total estimat</div>
            <div class="fw-semibold" style="font-size:1.15rem"><?= number_format($sumTotal, 2, '.', '') ?> lei</div>
          </div>
          <div class="mt-3 p-2 rounded" style="background:#F3F7F8;border:1px solid #D9E3E6">
            <div class="fw-semibold">HPL rezervat rămas (neconsumat)</div>
            <div class="text-muted small">Calcul: rezervat mp − mp necesari (din mp/buc × cantitate), la costul mediu lei/mp al plăcilor din proiect.</div>
            <div class="d-flex justify-content-between mt-1">
              <div class="text-muted">Suprafață</div>
              <div class="fw-semibold"><?= number_format($resM2, 2, '.', '') ?> mp</div>
            </div>
            <div class="d-flex justify-content-between mt-1">
              <div class="text-muted">Valoare</div>
              <div class="fw-semibold"><?= number_format($resCost, 2, '.', '') ?> lei</div>
            </div>
          </div>
          <div class="d-flex justify-content-end mt-2">
            <a class="btn btn-sm btn-outline-primary" target="_blank"
               href="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/bon-consum-general')) ?>">
              Bon consum general
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php elseif ($tab === 'consum'): ?>
  <?php
    $consumTabs = [
      'hpl' => 'Consum HPL',
      'accesorii' => 'Consum accesorii',
    ];
    $consumTab = isset($_GET['consum_tab']) ? trim((string)$_GET['consum_tab']) : 'hpl';
    if (!isset($consumTabs[$consumTab])) $consumTab = 'hpl';
  ?>
  <ul class="nav nav-tabs mb-3">
    <?php foreach ($consumTabs as $k => $label): ?>
      <li class="nav-item">
        <a class="nav-link <?= $consumTab === $k ? 'active' : '' ?>"
           href="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '?tab=consum&consum_tab=' . $k)) ?>">
          <?= htmlspecialchars($label) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($consumTab === 'accesorii'): ?>
    <div class="card app-card p-3">
        <div class="h5 m-0">Consum Magazie (accesorii)</div>
        <div class="text-muted">Rezervat — legabil la produs</div>

        <?php if ($canWrite): ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/consum/magazie/create')) ?>" class="row g-2 mt-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="consum_tab" value="accesorii">
            <div class="col-12">
              <label class="form-label fw-semibold">Accesoriu</label>
              <select class="form-select" name="item_id" id="magazieItemSelect" style="width:100%"></select>
              <div class="text-muted small mt-1">Caută după Cod WinMentor sau denumire.</div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Cantitate</label>
              <input class="form-control" type="number" step="0.001" min="0.001" name="qty" value="1">
            </div>
            <input type="hidden" name="mode" value="RESERVED">
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Produs (opțional)</label>
              <select class="form-select" name="project_product_id">
                <option value="">—</option>
                <?php foreach ($projectProducts as $pp): ?>
                  <option value="<?= (int)($pp['id'] ?? 0) ?>">
                    <?= htmlspecialchars((string)($pp['product_name'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <input type="hidden" name="include_in_deviz" value="0">
              <label class="form-check m-0">
                <input class="form-check-input" type="checkbox" name="include_in_deviz_flag" value="1" checked>
                <span class="form-check-label">Apare pe deviz</span>
              </label>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Notă</label>
              <input class="form-control" name="note" maxlength="255" placeholder="opțional…">
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-outline-secondary" type="submit">
                <i class="bi bi-plus-lg me-1"></i> Adaugă consum
              </button>
            </div>
          </form>

          <script>
            document.addEventListener('DOMContentLoaded', function(){
              const el = document.getElementById('magazieItemSelect');
              if (!el || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
              const $el = window.jQuery(el);

              $el.select2({
                width: '100%',
                placeholder: 'Caută accesoriu…',
                allowClear: true,
                minimumInputLength: 1,
                ajax: {
                  url: "<?= htmlspecialchars(Url::to('/api/magazie/items/search')) ?>",
                  dataType: 'json',
                  delay: 250,
                  headers: { 'Accept': 'application/json' },
                  data: function(params){ return { q: params.term }; },
                  transport: function (params, success, failure) {
                    const $ = window.jQuery;
                    if (!$ || !$.ajax) return $.ajax(params).then(success).catch(failure);
                    const req = $.ajax(params);
                    req.done(function (resp) {
                      if (resp && resp.ok === false) {
                        let msg = String(resp.error || 'Nu pot încărca accesoriile.');
                        if (resp.debug) msg += ' — ' + String(resp.debug);
                        if (window.toastr) window.toastr.error(msg);
                        // Nu lăsa Select2 să afișeze eroare generică
                        success({ ok: true, items: [] });
                        return;
                      }
                      success(resp);
                    });
                    req.fail(function (xhr) {
                      let msg = 'Nu pot încărca accesoriile. (API)';
                      try {
                        const ct = String((xhr && xhr.getResponseHeader) ? (xhr.getResponseHeader('content-type') || '') : '');
                        if (ct.toLowerCase().includes('application/json') && xhr.responseJSON) {
                          msg = String(xhr.responseJSON.error || msg);
                          if (xhr.responseJSON.debug) msg += ' — ' + String(xhr.responseJSON.debug);
                        } else if (xhr && typeof xhr.status === 'number' && xhr.status) {
                          msg += ' HTTP ' + String(xhr.status);
                        }
                      } catch (e) {}
                      if (window.toastr) window.toastr.error(msg);
                      // Evită mesajul "The results could not be loaded."
                      success({ ok: true, items: [] });
                      if (typeof failure === 'function') failure();
                    });
                    return req;
                  },
                  processResults: function(resp){
                    const items = (resp && resp.items) ? resp.items : [];
                    return { results: items };
                  },
                  cache: true
                }
              });
            });
          </script>
        <?php endif; ?>

        <div class="mt-3">
          <?php if (!$magazieConsum): ?>
            <div class="text-muted">Nu există consumuri Magazie încă.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Accesoriu</th>
                    <th style="width:110px" class="text-end">Cant</th>
                    <th style="width:110px">Mod</th>
                    <th>Notă</th>
                    <th class="text-end" style="width:160px">Acțiuni</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($magazieConsum as $c): ?>
                    <?php $cid = (int)($c['id'] ?? 0); ?>
                    <tr>
                      <td class="fw-semibold">
                        <a class="text-decoration-none" href="<?= htmlspecialchars(Url::to('/magazie/stoc/' . (int)($c['item_id'] ?? 0))) ?>">
                          <?= htmlspecialchars((string)($c['winmentor_code'] ?? '')) ?> · <?= htmlspecialchars((string)($c['item_name'] ?? '')) ?>
                        </a>
                        <?php $ppid = (int)($c['project_product_id'] ?? 0); ?>
                        <?php if ($ppid > 0): ?>
                          <div class="text-muted small">
                            Consum la produs: <span class="fw-semibold"><?= htmlspecialchars((string)($c['linked_product_name'] ?? '')) ?></span>
                          </div>
                        <?php else: ?>
                          <div class="text-muted small">Consum la proiect</div>
                        <?php endif; ?>
                      </td>
                      <td class="text-end fw-semibold"><?= number_format((float)($c['qty'] ?? 0), 3, '.', '') ?> <?= htmlspecialchars((string)($c['unit'] ?? '')) ?></td>
                      <td><?= htmlspecialchars((string)($c['mode'] ?? '')) ?></td>
                      <td class="text-muted"><?= htmlspecialchars((string)($c['note'] ?? '')) ?></td>
                      <td class="text-end">
                        <?php if ($canWrite): ?>
                          <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#mcEdit<?= $cid ?>">
                            <i class="bi bi-pencil me-1"></i> Editează
                          </button>
                          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/consum/magazie/' . $cid . '/delete')) ?>" class="d-inline"
                                onsubmit="return confirm('Ștergi consumul?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <input type="hidden" name="consum_tab" value="accesorii">
                            <button class="btn btn-outline-secondary btn-sm" type="submit">
                              <i class="bi bi-trash me-1"></i> Șterge
                            </button>
                          </form>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php if ($canWrite): ?>
                      <tr class="collapse" id="mcEdit<?= $cid ?>">
                        <td colspan="5">
                          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/consum/magazie/' . $cid . '/update')) ?>" class="row g-2 align-items-end">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <input type="hidden" name="consum_tab" value="accesorii">
                            <div class="col-6 col-md-2">
                              <label class="form-label fw-semibold mb-1">Cant</label>
                              <input class="form-control form-control-sm" type="number" step="0.001" min="0.001" name="qty" value="<?= htmlspecialchars((string)($c['qty'] ?? '')) ?>">
                            </div>
                            <div class="col-6 col-md-2">
                              <label class="form-label fw-semibold mb-1">Unit</label>
                              <input class="form-control form-control-sm" name="unit" value="<?= htmlspecialchars((string)($c['unit'] ?? 'buc')) ?>">
                            </div>
                            <div class="col-12 col-md-2">
                              <label class="form-label fw-semibold mb-1">Mod</label>
                              <select class="form-select form-select-sm" name="mode">
                                <option value="CONSUMED" <?= ((string)($c['mode'] ?? '') === 'CONSUMED') ? 'selected' : '' ?>>consumat</option>
                                <option value="RESERVED" <?= ((string)($c['mode'] ?? '') === 'RESERVED') ? 'selected' : '' ?>>rezervat</option>
                              </select>
                            </div>
                            <div class="col-12 col-md-2">
                              <label class="form-label fw-semibold mb-1">Deviz</label>
                              <input type="hidden" name="include_in_deviz" value="0">
                              <label class="form-check m-0">
                                <input class="form-check-input" type="checkbox" name="include_in_deviz_flag" value="1" <?= ((int)($c['include_in_deviz'] ?? 1) === 1) ? 'checked' : '' ?>>
                                <span class="form-check-label">Apare</span>
                              </label>
                            </div>
                            <div class="col-12 col-md-3">
                              <label class="form-label fw-semibold mb-1">Produs</label>
                              <select class="form-select form-select-sm" name="project_product_id">
                                <option value="">—</option>
                                <?php foreach ($projectProducts as $pp): ?>
                                  <option value="<?= (int)($pp['id'] ?? 0) ?>" <?= ((string)($c['project_product_id'] ?? '') === (string)($pp['id'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)($pp['product_name'] ?? '')) ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                            <div class="col-12 col-md-3">
                              <label class="form-label fw-semibold mb-1">Notă</label>
                              <input class="form-control form-control-sm" name="note" value="<?= htmlspecialchars((string)($c['note'] ?? '')) ?>">
                            </div>
                            <div class="col-12 d-flex justify-content-end">
                              <button class="btn btn-primary btn-sm" type="submit">
                                <i class="bi bi-save me-1"></i> Salvează
                              </button>
                            </div>
                          </form>
                        </td>
                      </tr>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
  <?php else: ?>
    <div class="card app-card p-3">
        <div class="h5 m-0">Consum HPL</div>
        <div class="text-muted">Rezervat (plăci întregi / resturi)</div>

        <?php if ($canWrite): ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/consum/hpl/create')) ?>" class="row g-2 mt-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="consum_tab" value="hpl">
            <input type="hidden" name="mode" value="RESERVED">
            <div class="col-12">
              <label class="form-label fw-semibold">Placă HPL</label>
              <select class="form-select" name="board_id" id="hplBoardSelect" style="width:100%"></select>
              <div class="text-muted small mt-1">Caută după cod placă sau coduri culoare. (Cu thumbnail)</div>
            </div>
            <div class="col-12" id="hplOffcutWrap" style="display:none;">
              <label class="form-label fw-semibold">Dimensiune rest (opțional)</label>
              <select class="form-select" name="offcut_dim" id="hplOffcutSelect">
                <option value="">Alege rest (opțional)…</option>
              </select>
              <div class="text-muted small mt-1">Apare când există resturi disponibile pentru placa selectată.</div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold" id="hplQtyLabel">Plăci (buc)</label>
              <input class="form-control" type="number" step="1" min="1" name="qty_boards" value="1" id="hplQtyBoards">
            </div>
            <div class="col-6"></div>
            <div class="col-12">
              <label class="form-label fw-semibold">Notă</label>
              <input class="form-control" name="note" maxlength="255" placeholder="opțional…">
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-outline-secondary" type="submit">
                <i class="bi bi-plus-lg me-1"></i> Adaugă consum HPL
              </button>
            </div>
          </form>

          <style>
            .s2-thumb{width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;margin-right:10px}
            .s2-thumb2{width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;margin-right:10px;margin-left:-8px}
            .s2-row{display:flex;align-items:center}
          </style>
          <script>
            document.addEventListener('DOMContentLoaded', function(){
              const el = document.getElementById('hplBoardSelect');
              if (!el || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
              const $ = window.jQuery;
              const $el = $(el);
              const qtyEl = document.getElementById('hplQtyBoards');
              const qtyLabelEl = document.getElementById('hplQtyLabel');
              const offcutWrap = document.getElementById('hplOffcutWrap');
              const offcutSelect = document.getElementById('hplOffcutSelect');
              const offcutUrl = "<?= htmlspecialchars(Url::to('/api/hpl/boards/offcuts')) ?>";
              const defaultQtyLabel = qtyLabelEl ? String(qtyLabelEl.textContent || '') : 'Plăci (buc)';
              let currentBoard = null;
              function fmtBoard(opt){
                if (!opt.id) return opt.text;
                const thumb = opt.thumb || null;
                const thumbBack = opt.thumb_back || null;
                const fc = opt.face_color_code || '';
                const bc = opt.back_color_code || '';
                let colors = fc ? String(fc) : '';
                if (bc && bc !== fc) colors = colors ? (colors + '/' + String(bc)) : String(bc);

                // Construim textul consistent: grosime · <bold>denumire</bold> · dimensiune · texturi · stoc.
                const th = (opt.thickness_mm !== undefined && opt.thickness_mm !== null) ? String(opt.thickness_mm) : '';
                const name = String(opt.name || '');
                const h = (opt.std_height_mm !== undefined && opt.std_height_mm !== null) ? String(opt.std_height_mm) : '';
                const w = (opt.std_width_mm !== undefined && opt.std_width_mm !== null) ? String(opt.std_width_mm) : '';
                const dim = (h && w) ? (h + '×' + w) : String(opt.text || '');
                const ft = String(opt.face_texture_name || '');
                const bt = String(opt.back_texture_name || '');
                let tex = ft ? ft : '';
                if (bt && bt !== ft) tex = tex ? (tex + '/' + bt) : bt;
                const stock = (opt.stock_qty_full_available !== undefined && opt.stock_qty_full_available !== null)
                  ? parseInt(String(opt.stock_qty_full_available), 10)
                  : NaN;
                const stockTxt = Number.isFinite(stock) ? stock : null;
                const offcut = (opt.stock_qty_offcut_available !== undefined && opt.stock_qty_offcut_available !== null)
                  ? parseInt(String(opt.stock_qty_offcut_available), 10)
                  : NaN;
                const offcutTxt = Number.isFinite(offcut) ? offcut : null;

                if (!thumb && !thumbBack && !colors && !th && !name && !dim && !tex && stockTxt === null && offcutTxt === null) return opt.text;
                const $row = $('<span class="s2-row"></span>');
                if (thumb) $row.append($('<img class="s2-thumb" />').attr('src', thumb));
                if (thumbBack && thumbBack !== thumb) $row.append($('<img class="s2-thumb2" />').attr('src', thumbBack));
                const $txt = $('<span></span>');
                const esc = (s) => String(s || '').replace(/</g,'&lt;');
                let base = '';
                if (th) base += esc(th) + 'mm';
                if (name) base += (base ? ' · ' : '') + '<strong>' + esc(name) + '</strong>';
                if (dim) base += (base ? ' · ' : '') + esc(dim);
                if (tex) base += (base ? ' · ' : '') + esc(tex);
                if (stockTxt !== null) base += ' · <span class="text-muted">stoc: <strong>' + esc(String(stockTxt)) + '</strong> buc</span>';
                if (offcutTxt !== null && offcutTxt > 0) base += ' · <span class="text-muted">rest: <strong>' + esc(String(offcutTxt)) + '</strong> buc</span>';

                if (colors) {
                  $txt.html('<strong>' + esc(colors) + '</strong> · ' + base);
                } else {
                  $txt.html(base);
                }
                $row.append($txt);
                return $row;
              }
              function setQtyLabel(isOffcut){
                if (!qtyLabelEl) return;
                qtyLabelEl.textContent = isOffcut ? 'Bucăți (rest)' : (defaultQtyLabel || 'Plăci (buc)');
              }
              function resetOffcutOptions(){
                if (!offcutSelect) return;
                offcutSelect.innerHTML = '<option value="">Alege rest (opțional)…</option>';
                offcutSelect.value = '';
              }
              function hideOffcuts(){
                if (offcutWrap) offcutWrap.style.display = 'none';
                if (offcutSelect) offcutSelect.disabled = false;
                resetOffcutOptions();
                setQtyLabel(false);
              }
              function getSelectedOffcutQty(){
                if (!offcutSelect || !offcutSelect.value) return null;
                const opt = offcutSelect.options[offcutSelect.selectedIndex];
                const raw = opt ? opt.getAttribute('data-qty') : null;
                const qty = raw !== null ? parseInt(String(raw), 10) : NaN;
                return Number.isFinite(qty) ? qty : null;
              }
              function applyMaxValue(maxVal){
                if (!qtyEl) return;
                if (Number.isFinite(maxVal) && maxVal > 0) {
                  qtyEl.max = String(maxVal);
                  const cur = parseInt(String(qtyEl.value || '1'), 10);
                  if (Number.isFinite(cur) && cur > maxVal) {
                    qtyEl.value = String(maxVal);
                    if (window.toastr) window.toastr.warning('Cantitatea a fost ajustată la stocul disponibil: ' + maxVal + ' buc.');
                  }
                } else {
                  qtyEl.removeAttribute('max');
                }
              }
              function loadOffcuts(boardId){
                if (!offcutWrap || !offcutSelect) return;
                if (!boardId) {
                  hideOffcuts();
                  return;
                }
                offcutWrap.style.display = '';
                offcutSelect.disabled = true;
                offcutSelect.innerHTML = '<option value="">Se încarcă…</option>';
                const url = offcutUrl + '?board_id=' + encodeURIComponent(String(boardId));
                fetch(url, { headers: { 'Accept': 'application/json' } })
                  .then(r => r.json())
                  .then(resp => {
                    if (!resp || resp.ok === false) {
                      let msg = String((resp && resp.error) ? resp.error : 'Nu pot încărca resturile disponibile.');
                      if (resp && resp.debug) msg += ' — ' + String(resp.debug);
                      if (window.toastr) window.toastr.error(msg);
                      hideOffcuts();
                      return;
                    }
                    const items = Array.isArray(resp.items) ? resp.items : [];
                    if (!items.length) {
                      hideOffcuts();
                      return;
                    }
                    offcutSelect.disabled = false;
                    offcutSelect.innerHTML = '<option value="">Alege rest (opțional)…</option>';
                    items.forEach(function(it){
                      const opt = document.createElement('option');
                      opt.value = String(it.dim || it.id || '');
                      opt.textContent = String(it.text || '');
                      if (it.qty !== undefined && it.qty !== null) opt.setAttribute('data-qty', String(it.qty));
                      offcutSelect.appendChild(opt);
                    });
                    setQtyLabel(false);
                    applyQtyMax();
                  })
                  .catch(function(){
                    if (window.toastr) window.toastr.error('Nu pot încărca resturile disponibile. (API)');
                    hideOffcuts();
                  });
              }
              $el.select2({
                width: '100%',
                placeholder: 'Caută placă HPL…',
                allowClear: true,
                minimumInputLength: 1,
                templateResult: fmtBoard,
                templateSelection: fmtBoard,
                escapeMarkup: m => m,
                ajax: {
                  url: "<?= htmlspecialchars(Url::to('/api/hpl/boards/search')) ?>",
                  dataType: 'json',
                  delay: 250,
                  headers: { 'Accept': 'application/json' },
                  data: function(params){ return { q: params.term }; },
                  transport: function (params, success, failure) {
                    const $ = window.jQuery;
                    if (!$ || !$.ajax) return $.ajax(params).then(success).catch(failure);
                    const req = $.ajax(params);
                    req.done(function (resp) {
                      if (resp && resp.ok === false) {
                        let msg = String(resp.error || 'Nu pot încărca plăcile HPL.');
                        if (resp.debug) msg += ' — ' + String(resp.debug);
                        if (window.toastr) window.toastr.error(msg);
                        success({ ok: true, items: [] });
                        return;
                      }
                      success(resp);
                    });
                    req.fail(function (xhr) {
                      let msg = 'Nu pot încărca plăcile HPL. (API)';
                      try {
                        const ct = String((xhr && xhr.getResponseHeader) ? (xhr.getResponseHeader('content-type') || '') : '');
                        if (ct.toLowerCase().includes('application/json') && xhr.responseJSON) {
                          msg = String(xhr.responseJSON.error || msg);
                          if (xhr.responseJSON.debug) msg += ' — ' + String(xhr.responseJSON.debug);
                        } else if (xhr && typeof xhr.status === 'number' && xhr.status) {
                          msg += ' HTTP ' + String(xhr.status);
                        }
                      } catch (e) {}
                      if (window.toastr) window.toastr.error(msg);
                      success({ ok: true, items: [] });
                      if (typeof failure === 'function') failure();
                    });
                    return req;
                  },
                  processResults: function(resp){
                    const items = (resp && resp.items) ? resp.items : [];
                    return { results: items };
                  },
                  cache: true
                }
              });

              // UI guard: nu permite introducerea unei cantități > stoc disponibil pentru placa selectată.
              function applyMaxFromSelection(sel){
                const stock = sel && sel.stock_qty_full_available !== undefined ? parseInt(String(sel.stock_qty_full_available), 10) : NaN;
                applyMaxValue(stock);
              }
              function applyQtyMax(){
                const offcutQty = getSelectedOffcutQty();
                if (offcutQty !== null) {
                  setQtyLabel(true);
                  applyMaxValue(offcutQty);
                  return;
                }
                setQtyLabel(false);
                if (currentBoard) {
                  applyMaxFromSelection(currentBoard);
                  return;
                }
                if (qtyEl) qtyEl.removeAttribute('max');
              }
              $el.on('select2:select', function(e){
                const data = (e && e.params && e.params.data) ? e.params.data : null;
                currentBoard = data;
                const offcutCount = data && data.stock_qty_offcut_available !== undefined ? parseInt(String(data.stock_qty_offcut_available), 10) : NaN;
                if (Number.isFinite(offcutCount) && offcutCount > 0) {
                  loadOffcuts(data && data.id ? data.id : null);
                } else {
                  hideOffcuts();
                }
                applyQtyMax();
              });
              $el.on('select2:clear', function(){
                currentBoard = null;
                hideOffcuts();
                if (qtyEl) qtyEl.removeAttribute('max');
              });
              if (offcutSelect) {
                offcutSelect.addEventListener('change', function(){
                  applyQtyMax();
                });
              }
              if (qtyEl) {
                qtyEl.addEventListener('input', function(){
                  const max = qtyEl.max ? parseInt(String(qtyEl.max), 10) : NaN;
                  const cur = parseInt(String(qtyEl.value || ''), 10);
                  if (Number.isFinite(max) && Number.isFinite(cur) && cur > max) {
                    qtyEl.value = String(max);
                    if (window.toastr) window.toastr.warning('Nu poți depăși stocul disponibil: ' + max + ' buc.');
                  }
                });
              }
            });
          </script>
        <?php endif; ?>

        <div class="mt-3">
          <div class="fw-semibold">Piese HPL (stoc proiect)</div>
          <div class="text-muted small">FULL/OFFCUT cu dimensiuni, la fel ca în Stoc → Placă.</div>
          <?php if (!$projectHplPieces): ?>
            <div class="text-muted mt-2">Nu există piese HPL asociate proiectului încă.</div>
          <?php else: ?>
            <div class="table-responsive mt-2">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Placă</th>
                    <th>Tip</th>
                    <th>Status</th>
                    <th>Dimensiuni</th>
                    <th class="text-end">Buc</th>
                    <th>Locație</th>
                    <th>Notă</th>
                    <th class="text-end">mp</th>
                    <?php if ($canMoveHpl): ?><th class="text-end" style="width:260px">Acțiuni</th><?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($projectHplPieces as $p): ?>
                    <?php
                      $bid = (int)($p['board_id'] ?? 0);
                      $pid = (int)($p['id'] ?? 0);
                      $bLabel = trim((string)($p['board_code'] ?? '') . ' · ' . (string)($p['board_name'] ?? ''));
                      $wmm = (int)($p['width_mm'] ?? 0);
                      $hmm = (int)($p['height_mm'] ?? 0);
                      $qty = (int)($p['qty'] ?? 0);
                      $mp = isset($p['area_total_m2']) ? (float)$p['area_total_m2'] : 0.0;
                      $ptype = (string)($p['piece_type'] ?? '');
                      $pstatus = (string)($p['status'] ?? '');
                      $ploc = (string)($p['location'] ?? '');
                      $note = trim((string)($p['notes'] ?? ''));
                      $noteShort = $note;
                      if ($noteShort !== '' && mb_strlen($noteShort) > 140) $noteShort = mb_substr($noteShort, 0, 140) . '…';
                      $isAcc = (int)($p['is_accounting'] ?? 1);
                      $isReturnable = ($pstatus === 'RESERVED' && $qty > 0);
                      $isReturnableStock = ($isReturnable && $ptype === 'FULL');
                      $isReturnableRest = ($isReturnable && $isAcc === 0);
                      $isReturnableOffcut = ($isReturnable && $ptype === 'OFFCUT' && $isAcc !== 0);
                    ?>
                    <tr>
                      <td class="fw-semibold">
                        <?php if ($bid > 0): ?>
                          <a class="text-decoration-none" href="<?= htmlspecialchars(Url::to('/stock/boards/' . $bid)) ?>">
                            <?= htmlspecialchars($bLabel) ?>
                          </a>
                        <?php else: ?>
                          <?= htmlspecialchars($bLabel) ?>
                        <?php endif; ?>
                      </td>
                      <td class="fw-semibold"><?= htmlspecialchars((string)($p['piece_type'] ?? '')) ?></td>
                      <td class="fw-semibold"><?= htmlspecialchars((string)($p['status'] ?? '')) ?></td>
                      <td class="text-muted"><?= $hmm > 0 && $wmm > 0 ? (htmlspecialchars($hmm . ' × ' . $wmm . ' mm')) : '—' ?></td>
                      <td class="text-end fw-semibold"><?= $qty > 0 ? (int)$qty : '—' ?></td>
                      <td class="text-muted"><?= htmlspecialchars((string)($p['location'] ?? '')) ?></td>
                      <td class="text-muted small" style="max-width:420px;white-space:pre-line"><?= htmlspecialchars($noteShort) ?></td>
                      <td class="text-end fw-semibold"><?= number_format((float)$mp, 2, '.', '') ?></td>
                      <?php if ($canMoveHpl): ?>
                        <td class="text-end">
                          <?php if ($isReturnableRest && $bid > 0 && $pid > 0): ?>
                            <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/hpl/pieces/' . $pid . '/return')) ?>" class="d-inline-flex gap-2 align-items-center justify-content-end js-return-note-form"
                                  onsubmit="return window.appReturnNote ? window.appReturnNote.handleSubmit(this) : confirm('Revii în stoc (Depozit/Disponibil) această piesă REST?');">
                              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                              <input type="hidden" name="consum_tab" value="hpl">
                              <input type="hidden" name="note_user" value="">
                              <button class="btn btn-outline-secondary btn-sm" type="submit">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Revenire stoc
                              </button>
                            </form>
                          <?php elseif ($isReturnableStock && $bid > 0 && $pid > 0 && $qty > 0): ?>
                            <form method="post" action="<?= htmlspecialchars(Url::to('/stock/boards/' . $bid . '/pieces/move')) ?>" class="d-inline-flex gap-2 align-items-center justify-content-end js-return-note-form"
                                  onsubmit="return window.appReturnNote ? window.appReturnNote.handleSubmit(this) : confirm('Revii în stoc (Depozit/Disponibil) această placă?');">
                              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                              <input type="hidden" name="from_piece_id" value="<?= (int)$pid ?>">
                              <input type="hidden" name="to_location" value="Depozit">
                              <input type="hidden" name="to_status" value="AVAILABLE">
                              <input type="hidden" name="note_user" value="">
                              <input class="form-control form-control-sm text-end" type="number" min="1" max="<?= (int)$qty ?>" step="1"
                                     name="qty" value="<?= min(1, (int)$qty) ?>" style="width:90px" title="Bucăți de returnat">
                              <button class="btn btn-outline-secondary btn-sm" type="submit">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Revenire stoc
                              </button>
                            </form>
                          <?php elseif ($isReturnableOffcut && $bid > 0 && $pid > 0 && $qty > 0): ?>
                            <form method="post" action="<?= htmlspecialchars(Url::to('/stock/boards/' . $bid . '/pieces/move')) ?>" class="d-inline-flex gap-2 align-items-center justify-content-end js-return-note-form"
                                  onsubmit="return window.appReturnNote ? window.appReturnNote.handleSubmit(this) : confirm('Revii în stoc (Depozit/Disponibil) această piesă OFFCUT?');">
                              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                              <input type="hidden" name="from_piece_id" value="<?= (int)$pid ?>">
                              <input type="hidden" name="to_location" value="Depozit">
                              <input type="hidden" name="to_status" value="AVAILABLE">
                              <input type="hidden" name="note_user" value="">
                              <input class="form-control form-control-sm text-end" type="number" min="1" max="<?= (int)$qty ?>" step="1"
                                     name="qty" value="<?= min(1, (int)$qty) ?>" style="width:90px" title="Bucăți de returnat">
                              <button class="btn btn-outline-secondary btn-sm" type="submit">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Revenire stoc
                              </button>
                            </form>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>
  <?php endif; ?>
<?php elseif ($tab === 'deliveries'): ?>
  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card app-card p-3">
        <div class="h5 m-0">Livrare nouă</div>
        <div class="text-muted">Livrări multiple, cu cantități pe produs</div>

        <?php if (!$canWrite): ?>
          <div class="text-muted mt-2">Nu ai drepturi de editare.</div>
        <?php else: ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/deliveries/create')) ?>" class="mt-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <div class="row g-2">
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Data livrare</label>
                <input class="form-control" type="date" name="delivery_date" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Notă (opțional)</label>
                <input class="form-control" name="note" maxlength="255">
              </div>
            </div>

            <div class="mt-3">
              <div class="fw-semibold">Produse</div>
              <div class="text-muted small">Introdu cantitatea livrată acum (nu depăși “rămas”).</div>
              <div class="table-responsive mt-2">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Produs</th>
                      <th class="text-end" style="width:120px">Total</th>
                      <th class="text-end" style="width:120px">Livrat</th>
                      <th class="text-end" style="width:120px">Rămas</th>
                      <th class="text-end" style="width:160px">Livrare acum</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($projectProducts as $pp): ?>
                      <?php
                        $ppId = (int)($pp['id'] ?? 0);
                        $total = (float)($pp['qty'] ?? 0);
                        $del = (float)($pp['delivered_qty'] ?? 0);
                        $left = max(0.0, $total - $del);
                      ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars((string)($pp['product_name'] ?? '')) ?></td>
                        <td class="text-end"><?= number_format($total, 2, '.', '') ?> <?= htmlspecialchars((string)($pp['unit'] ?? '')) ?></td>
                        <td class="text-end"><?= number_format($del, 2, '.', '') ?></td>
                        <td class="text-end fw-semibold"><?= number_format($left, 2, '.', '') ?></td>
                        <td class="text-end">
                          <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" max="<?= htmlspecialchars((string)$left) ?>"
                                 name="delivery_qty[<?= $ppId ?>]" value="0">
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
              <button class="btn btn-primary" type="submit" onclick="return confirm('Salvez livrarea?');">
                <i class="bi bi-truck me-1"></i> Salvează livrarea
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card app-card p-3">
        <div class="h5 m-0">Livrări existente</div>
        <div class="text-muted">Istoric livrări (cantități pe produse)</div>

        <?php if (!$deliveries): ?>
          <div class="text-muted mt-2">Nu există livrări încă.</div>
        <?php else: ?>
          <div class="accordion mt-2" id="deliveriesAcc">
            <?php foreach ($deliveries as $d): ?>
              <?php
                $did = (int)($d['id'] ?? 0);
                $items = is_array($deliveryItems[$did] ?? null) ? $deliveryItems[$did] : [];
              ?>
              <div class="accordion-item">
                <h2 class="accordion-header" id="h<?= $did ?>">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c<?= $did ?>">
                    <?= htmlspecialchars((string)($d['delivery_date'] ?? '')) ?> · <?= count($items) ?> produse
                    <?php if (!empty($d['note'])): ?>
                      <span class="text-muted ms-2"><?= htmlspecialchars((string)$d['note']) ?></span>
                    <?php endif; ?>
                  </button>
                </h2>
                <div id="c<?= $did ?>" class="accordion-collapse collapse" data-bs-parent="#deliveriesAcc">
                  <div class="accordion-body">
                    <?php if (!$items): ?>
                      <div class="text-muted">Fără produse.</div>
                    <?php else: ?>
                      <table class="table table-sm align-middle mb-0">
                        <thead>
                          <tr>
                            <th>Produs</th>
                            <th class="text-end" style="width:140px">Cantitate</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($items as $it): ?>
                            <tr>
                              <td class="fw-semibold"><?= htmlspecialchars((string)($it['product_name'] ?? '')) ?></td>
                              <td class="text-end fw-semibold"><?= number_format((float)($it['qty'] ?? 0), 2, '.', '') ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php elseif ($tab === 'cnc'): ?>
  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card app-card p-3">
        <div class="h5 m-0">CNC / Tehnic</div>
        <div class="text-muted">Note tehnice + fișiere (DXF/G-code/PDF)</div>

        <div class="mt-2">
          <div class="fw-semibold">Note tehnice</div>
          <div class="text-muted small">Se editează din tab-ul General.</div>
          <div class="mt-2 p-2 rounded" style="background:#F7FAFB;border:1px solid #E5EEF1">
            <?= nl2br(htmlspecialchars((string)($project['technical_notes'] ?? ''))) ?>
          </div>
        </div>

        <div class="mt-3">
          <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '?tab=files')) ?>">
            <i class="bi bi-paperclip me-1"></i> Mergi la Fișiere (upload)
          </a>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card app-card p-3">
        <div class="h5 m-0">Fișiere tehnice</div>
        <div class="text-muted">Proiect + produse (ordonate după dată)</div>

        <?php if (!$cncFiles): ?>
          <div class="text-muted mt-2">Nu există fișiere încă.</div>
        <?php else: ?>
          <div class="list-group list-group-flush mt-2">
            <?php foreach ($cncFiles as $f): ?>
              <?php
                $url = Url::to('/uploads/files/' . (string)($f['stored_name'] ?? ''));
                $cat = (string)($f['category'] ?? '');
                $pname = (string)($f['_product_name'] ?? '');
              ?>
              <div class="list-group-item px-0">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div>
                    <div class="fw-semibold">
                      <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                        <?= htmlspecialchars((string)($f['original_name'] ?? '')) ?>
                      </a>
                    </div>
                    <div class="text-muted small">
                      <?= $cat !== '' ? htmlspecialchars($cat) : '' ?>
                      <?= $pname !== '' ? (' · Produs: ' . htmlspecialchars($pname)) : '' ?>
                      <?= !empty($f['created_at']) ? (' · ' . htmlspecialchars((string)$f['created_at'])) : '' ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php elseif ($tab === 'files'): ?>
  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="card app-card p-3">
        <div class="h5 m-0">Upload fișier</div>
        <div class="text-muted">DXF / G-code / PDF / imagini etc. (proiect sau produs)</div>

        <?php if (!$canWrite): ?>
          <div class="text-muted mt-2">Nu ai drepturi de editare.</div>
        <?php else: ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/files/upload')) ?>" enctype="multipart/form-data" class="mt-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

            <div class="mb-2">
              <label class="form-label fw-semibold">Destinație</label>
              <select class="form-select" name="entity_type" id="fileEntityType">
                <option value="projects">Proiect</option>
                <option value="project_products">Produs (din proiect)</option>
              </select>
            </div>

            <div class="mb-2" id="fileEntityIdWrap" style="display:none">
              <label class="form-label fw-semibold">Produs</label>
              <select class="form-select" name="entity_id">
                <?php foreach ($projectProducts as $pp): ?>
                  <option value="<?= (int)($pp['id'] ?? 0) ?>"><?= htmlspecialchars((string)($pp['product_name'] ?? '')) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label fw-semibold">Categorie (opțional)</label>
              <input class="form-control" name="category" placeholder="ex: DXF, GCODE, PDF, IMG">
            </div>

            <div class="mb-2">
              <label class="form-label fw-semibold">Fișier</label>
              <input class="form-control" type="file" name="file" required>
            </div>

            <div class="d-flex justify-content-end">
              <button class="btn btn-primary" type="submit">
                <i class="bi bi-upload me-1"></i> Upload
              </button>
            </div>
          </form>

          <script>
            document.addEventListener('DOMContentLoaded', function(){
              const sel = document.getElementById('fileEntityType');
              const wrap = document.getElementById('fileEntityIdWrap');
              function apply(){
                if (!sel || !wrap) return;
                wrap.style.display = (sel.value === 'project_products') ? '' : 'none';
              }
              if (sel) sel.addEventListener('change', apply);
              apply();
            });
          </script>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-12 col-lg-7">
      <div class="card app-card p-3">
        <div class="h5 m-0">Fișiere</div>
        <div class="text-muted">Click pentru deschidere; ștergere logată</div>

        <?php if (!$projectFiles): ?>
          <div class="text-muted mt-2">Nu există fișiere încă (pe proiect).</div>
        <?php else: ?>
          <div class="list-group list-group-flush mt-2">
            <?php foreach ($projectFiles as $f): ?>
              <?php
                $fid = (int)($f['id'] ?? 0);
                $url = Url::to('/uploads/files/' . (string)($f['stored_name'] ?? ''));
              ?>
              <div class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                <div>
                  <div class="fw-semibold">
                    <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                      <?= htmlspecialchars((string)($f['original_name'] ?? '')) ?>
                    </a>
                  </div>
                  <div class="text-muted small">
                    <?= htmlspecialchars((string)($f['category'] ?? '')) ?>
                    <?= !empty($f['created_at']) ? (' · ' . htmlspecialchars((string)$f['created_at'])) : '' ?>
                  </div>
                </div>
                <?php if ($canWrite): ?>
                  <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/files/' . $fid . '/delete')) ?>" class="m-0"
                        onsubmit="return confirm('Ștergi fișierul?');">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">
                      <i class="bi bi-trash me-1"></i> Șterge
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php elseif ($tab === 'hours'): ?>
  <?php
    $totEst = 0.0;
    $totCostEst = 0.0;
    $totEstCnc = 0.0;
    $totEstAtelier = 0.0;
    $totCostEstCnc = 0.0;
    $totCostEstAtelier = 0.0;
    $laborRate = isset($costSettings['labor']) && $costSettings['labor'] !== null ? (float)$costSettings['labor'] : null;
    $cncRate = isset($costSettings['cnc']) && $costSettings['cnc'] !== null ? (float)$costSettings['cnc'] : null;
    foreach ($workLogs as $w) {
      $he = isset($w['hours_estimated']) && $w['hours_estimated'] !== null && $w['hours_estimated'] !== '' ? (float)$w['hours_estimated'] : 0.0;
      $cph = isset($w['cost_per_hour']) && $w['cost_per_hour'] !== null && $w['cost_per_hour'] !== '' ? (float)$w['cost_per_hour'] : null;
      $totEst += $he;
      if ($cph !== null && $cph >= 0 && is_finite($cph) && $he > 0) {
        $totCostEst += ($he * $cph);
        $wt = (string)($w['work_type'] ?? '');
        if ($wt === 'CNC') {
          $totEstCnc += $he;
          $totCostEstCnc += ($he * $cph);
        } elseif ($wt === 'ATELIER') {
          $totEstAtelier += $he;
          $totCostEstAtelier += ($he * $cph);
        }
      } else {
        $wt = (string)($w['work_type'] ?? '');
        if ($wt === 'CNC') $totEstCnc += $he;
        elseif ($wt === 'ATELIER') $totEstAtelier += $he;
      }
    }
  ?>
  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="card app-card p-3">
        <div class="h5 m-0">Adaugă ore</div>
        <div class="text-muted">CNC / Atelier (doar estimări)</div>
        <div class="text-muted small mt-1">
          Costuri din Setări: CNC <strong><?= $cncRate !== null ? number_format($cncRate, 2, '.', '') : '—' ?></strong> lei/h ·
          Manoperă <strong><?= $laborRate !== null ? number_format($laborRate, 2, '.', '') : '—' ?></strong> lei/h
        </div>

        <?php if (!$canWrite): ?>
          <div class="text-muted mt-2">Nu ai drepturi de editare.</div>
        <?php else: ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/hours/create')) ?>" class="row g-2 mt-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Tip</label>
              <select class="form-select" name="work_type">
                <option value="CNC">CNC</option>
                <option value="ATELIER">Atelier</option>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Produs (opțional)</label>
              <select class="form-select" name="project_product_id">
                <option value="">—</option>
                <?php foreach ($projectProducts as $pp): ?>
                  <option value="<?= (int)($pp['id'] ?? 0) ?>"><?= htmlspecialchars((string)($pp['product_name'] ?? '')) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Ore estimate</label>
              <input class="form-control" type="number" step="0.01" min="0.01" name="hours_estimated" required placeholder="ex: 2.50">
              <div class="text-muted small mt-1">Câmp obligatoriu. Ore reale nu se mai introduc aici.</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Notă</label>
              <input class="form-control" name="note" maxlength="255">
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-primary" type="submit">
                <i class="bi bi-plus-lg me-1"></i> Adaugă
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <div class="card app-card p-3 mt-3">
        <div class="h5 m-0">Total</div>
        <div class="text-muted">Sumar estimări + costuri</div>
        <div class="d-flex justify-content-between mt-2">
          <div class="text-muted">Estimate (total)</div>
          <div class="fw-semibold"><?= number_format($totEst, 2, '.', '') ?> h</div>
        </div>
        <div class="d-flex justify-content-between mt-2">
          <div class="text-muted">Cost estimat (total)</div>
          <div class="fw-semibold"><?= number_format($totCostEst, 2, '.', '') ?> lei</div>
        </div>
        <hr class="my-3">
        <div class="d-flex justify-content-between mt-2">
          <div class="text-muted">CNC (estim.)</div>
          <div class="fw-semibold"><?= number_format($totEstCnc, 2, '.', '') ?> h · <?= number_format($totCostEstCnc, 2, '.', '') ?> lei</div>
        </div>
        <div class="d-flex justify-content-between mt-2">
          <div class="text-muted">Atelier (estim.)</div>
          <div class="fw-semibold"><?= number_format($totEstAtelier, 2, '.', '') ?> h · <?= number_format($totCostEstAtelier, 2, '.', '') ?> lei</div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-7">
      <div class="card app-card p-3">
        <div class="h5 m-0">Istoric ore</div>
        <div class="text-muted">Toate modificările sunt logate</div>

        <?php if (!$workLogs): ?>
          <div class="text-muted mt-2">Nu există înregistrări încă.</div>
        <?php else: ?>
          <div class="table-responsive mt-2">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Dată</th>
                  <th>Tip</th>
                  <th>Produs</th>
                  <th class="text-end">Est.</th>
                  <th class="text-end">Cost/oră</th>
                  <th class="text-end">Cost (estim.)</th>
                  <th>Notă</th>
                  <th class="text-end">Acțiuni</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($workLogs as $w): ?>
                  <?php
                    $wid = (int)($w['id'] ?? 0);
                    $he = isset($w['hours_estimated']) && $w['hours_estimated'] !== null && $w['hours_estimated'] !== '' ? (float)$w['hours_estimated'] : null;
                    $cph = isset($w['cost_per_hour']) && $w['cost_per_hour'] !== null && $w['cost_per_hour'] !== '' ? (float)$w['cost_per_hour'] : null;
                    $costEst = ($he !== null && $cph !== null) ? ($he * $cph) : null;

                    $ppId = isset($w['project_product_id']) && $w['project_product_id'] !== null && $w['project_product_id'] !== '' ? (int)$w['project_product_id'] : 0;
                    $prodName = trim((string)($w['product_name'] ?? ''));
                    $isProductLinked = ($ppId > 0);
                  ?>
                  <tr<?= $isProductLinked ? ' style="background:#F7FBFF"' : '' ?>>
                    <td class="text-muted"><?= htmlspecialchars((string)($w['created_at'] ?? '')) ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars((string)($w['work_type'] ?? '')) ?></td>
                    <td>
                      <?php if ($isProductLinked): ?>
                        <span class="badge bg-primary-subtle text-primary-emphasis me-2">Produs</span>
                        <span class="fw-semibold">
                          <?= $prodName !== '' ? htmlspecialchars($prodName) : ('#' . (int)$ppId) ?>
                        </span>
                      <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis me-2">Proiect</span>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end"><?= $he !== null ? number_format($he, 2, '.', '') : '—' ?></td>
                    <td class="text-end"><?= $cph !== null ? number_format($cph, 2, '.', '') : '—' ?></td>
                    <td class="text-end fw-semibold"><?= $costEst !== null ? number_format((float)$costEst, 2, '.', '') : '—' ?></td>
                    <td class="text-muted"><?= htmlspecialchars((string)($w['note'] ?? '')) ?></td>
                    <td class="text-end">
                      <?php if ($canWrite): ?>
                        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/hours/' . $wid . '/delete')) ?>" class="m-0"
                              onsubmit="return confirm('Ștergi înregistrarea?');">
                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                          <button class="btn btn-outline-secondary btn-sm" type="submit">
                            <i class="bi bi-trash me-1"></i> Șterge
                          </button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php elseif ($tab === 'history'): ?>
  <div class="card app-card p-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <div class="h5 m-0">Istoric / Log-uri</div>
        <div class="text-muted">Acțiuni pe proiect/produs/consum/livrare/fișiere/ore</div>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/audit')) ?>">
        <i class="bi bi-journal-text me-1"></i> Jurnal global
      </a>
    </div>

    <?php if (!$history): ?>
      <div class="text-muted mt-3">Nu există log-uri încă.</div>
    <?php else: ?>
      <div class="table-responsive mt-3">
        <table class="table table-hover align-middle mb-0" id="projectHistoryTable">
          <thead>
            <tr>
              <th style="width:170px">Dată</th>
              <th style="width:160px">Acțiune</th>
              <th style="width:170px">User</th>
              <th>Mesaj</th>
              <th>Notă</th>
              <th style="width:140px">Entitate</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $h): ?>
              <tr>
                <td class="text-muted"><?= htmlspecialchars((string)($h['created_at'] ?? '')) ?></td>
                <td class="fw-semibold"><?= htmlspecialchars((string)($h['action'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($h['user_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($h['message'] ?? '')) ?></td>
                <td class="text-muted"><?= htmlspecialchars((string)($h['note'] ?? '')) ?></td>
                <td class="text-muted">
                  <?php
                    $etype = (string)($h['entity_type'] ?? '');
                    $eid = isset($h['entity_id']) && is_numeric($h['entity_id']) ? (int)$h['entity_id'] : 0;
                    $projLabel = trim((string)($project['code'] ?? '') . ' · ' . (string)($project['name'] ?? ''));
                    if ($etype === 'projects') {
                      echo 'Proiect ' . htmlspecialchars($projLabel !== '' ? $projLabel : ('#' . $eid));
                    } elseif ($etype === 'project_products' && $eid > 0 && isset($projectProductLabels[$eid])) {
                      echo 'Produs ' . htmlspecialchars((string)$projectProductLabels[$eid]);
                    } else {
                      echo htmlspecialchars($etype !== '' ? $etype : '—');
                      if ($eid > 0) echo ' #' . htmlspecialchars((string)$eid);
                    }
                  ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <script>
        document.addEventListener('DOMContentLoaded', function(){
          const el = document.getElementById('projectHistoryTable');
          if (el && window.DataTable) {
            window.__projectHistoryDT = new DataTable(el, {
              pageLength: 50,
              lengthMenu: [[25, 50, 100, 200], [25, 50, 100, 200]],
              order: [[0, 'desc']],
              language: {
                search: 'Caută:',
                searchPlaceholder: 'Caută în istoric…',
                lengthMenu: 'Afișează _MENU_',
              }
            });
          }
        });
      </script>
    <?php endif; ?>
  </div>
<?php elseif ($tab === 'discutii'): ?>
  <div class="row g-3">
    <div class="col-12 col-lg-8">
      <div class="card app-card p-3">
        <div class="h5 m-0">Discuții</div>
        <div class="text-muted">Mesaje pe proiect (cu user + dată/oră)</div>

        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/discutii/create')) ?>" class="mt-3">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
          <label class="form-label fw-semibold">Mesaj</label>
          <textarea class="form-control" name="comment" rows="3" maxlength="4000" placeholder="Scrie mesajul…"></textarea>
          <div class="d-flex justify-content-end mt-2">
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-send me-1"></i> Trimite
            </button>
          </div>
        </form>

        <hr class="my-3">

        <?php if (!$discussions): ?>
          <div class="text-muted">Nu există mesaje încă.</div>
        <?php else: ?>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($discussions as $m): ?>
              <?php
                $who = (string)($m['user_name'] ?? '');
                if ($who === '') $who = (string)($m['user_email'] ?? '');
                if ($who === '') $who = '—';
                $dt = (string)($m['created_at'] ?? '');
                $txt = (string)($m['comment'] ?? '');
              ?>
              <div class="p-3 rounded" style="background:#F7FAFB;border:1px solid #D9E3E6">
                <div class="d-flex justify-content-between gap-2">
                  <div class="fw-semibold"><?= htmlspecialchars($who) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($dt) ?></div>
                </div>
                <div class="mt-1"><?= nl2br(htmlspecialchars($txt)) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
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

<div class="modal fade" id="avizNumberModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Număr aviz</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="avizNumberInput" class="form-label fw-semibold">Introdu numărul de aviz</label>
        <input class="form-control" id="avizNumberInput" maxlength="40" placeholder="ex: AVZ-10234">
        <div class="invalid-feedback">Introdu un număr de aviz.</div>
        <div class="text-muted small mt-2">Numărul de aviz va fi afișat jos pe Deviz și Bonul de consum.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Renunță</button>
        <button type="button" class="btn btn-primary" id="avizNumberConfirm">Continuă</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="returnNoteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Notă pentru revenire în stoc</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="returnNoteText" class="form-label fw-semibold">Notă (opțional)</label>
        <textarea class="form-control" id="returnNoteText" rows="3" maxlength="500" placeholder="Scrie o notă pentru această revenire..."></textarea>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="returnNoteSkip">
          <label class="form-check-label" for="returnNoteSkip">Nu doresc să adaug notă</label>
        </div>
        <div class="text-muted small mt-2">Nota introdusă va fi adăugată după nota automată.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Renunță</button>
        <button type="button" class="btn btn-primary" id="returnNoteConfirm">Continuă</button>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('returnNoteModal');
    const modal = (modalEl && window.bootstrap && window.bootstrap.Modal)
      ? window.bootstrap.Modal.getOrCreateInstance(modalEl)
      : null;
    const noteEl = document.getElementById('returnNoteText');
    const skipEl = document.getElementById('returnNoteSkip');
    const confirmBtn = document.getElementById('returnNoteConfirm');
    let activeForm = null;

    function resetForm() {
      if (noteEl) noteEl.value = '';
      if (skipEl) skipEl.checked = false;
      if (noteEl) noteEl.disabled = false;
    }

    function openModal(form) {
      activeForm = form;
      resetForm();
      if (modal) {
        modal.show();
      } else {
        const wantNote = window.confirm('Vrei să adaugi o notă pentru această revenire?');
        if (!wantNote) {
          applyAndSubmit('');
          return;
        }
        const txt = window.prompt('Notă pentru revenire în stoc:') || '';
        if (txt === '') return;
        applyAndSubmit(txt);
      }
    }

    function applyAndSubmit(txtOverride) {
      if (!activeForm) return;
      const input = activeForm.querySelector('input[name="note_user"]');
      if (input) {
        const noteText = typeof txtOverride === 'string' ? txtOverride : '';
        const value = noteText !== ''
          ? noteText.trim()
          : (skipEl && skipEl.checked ? '' : (noteEl ? noteEl.value.trim() : ''));
        input.value = value;
      }
      activeForm.dataset.returnNoteConfirmed = '1';
      if (modal) modal.hide();
      activeForm.submit();
    }

    if (skipEl && noteEl) {
      skipEl.addEventListener('change', function () {
        noteEl.disabled = skipEl.checked;
        if (skipEl.checked) noteEl.value = '';
      });
    }
    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        applyAndSubmit('');
      });
    }

    window.appReturnNote = {
      handleSubmit: function (form) {
        if (!form) return true;
        if (form.dataset.returnNoteConfirmed === '1') {
          form.dataset.returnNoteConfirmed = '';
          return true;
        }
        openModal(form);
        return false;
      }
    };
  });
</script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('avizNumberModal');
    const modal = (modalEl && window.bootstrap && window.bootstrap.Modal)
      ? window.bootstrap.Modal.getOrCreateInstance(modalEl)
      : null;
    const input = document.getElementById('avizNumberInput');
    const confirmBtn = document.getElementById('avizNumberConfirm');
    let activeForm = null;

    function resetInput() {
      if (input) {
        input.value = '';
        input.classList.remove('is-invalid');
      }
    }

    function applyAndSubmit(value) {
      if (!activeForm) return;
      const target = activeForm.querySelector('input[name="aviz_number"]');
      if (target) target.value = value;
      activeForm.dataset.avizConfirmed = '1';
      if (modal) modal.hide();
      activeForm.submit();
    }

    function openModal(form) {
      activeForm = form;
      resetInput();
      if (modal) {
        modal.show();
      } else {
        const txt = window.prompt('Număr de aviz:') || '';
        const val = txt.trim();
        if (val === '') return;
        applyAndSubmit(val);
      }
    }

    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        const val = input ? input.value.trim() : '';
        if (val === '') {
          if (input) {
            input.classList.add('is-invalid');
            input.focus();
          }
          return;
        }
        applyAndSubmit(val);
      });
    }

    if (input) {
      input.addEventListener('input', function () {
        input.classList.remove('is-invalid');
      });
    }

    document.querySelectorAll('form[data-aviz-required="1"]').forEach(function (form) {
      form.addEventListener('submit', function (ev) {
        if (form.dataset.avizConfirmed === '1') {
          form.dataset.avizConfirmed = '';
          return;
        }
        const current = form.querySelector('input[name="aviz_number"]');
        if (current && current.value.trim() !== '') return;
        ev.preventDefault();
        openModal(form);
      });
    });
  });
</script>

<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

