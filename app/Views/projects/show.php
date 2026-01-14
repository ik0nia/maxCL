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
$projectProducts = $projectProducts ?? [];
$magazieConsum = $magazieConsum ?? [];
$hplConsum = $hplConsum ?? [];
$hplAlloc = $hplAlloc ?? [];
$hplBoards = $hplBoards ?? [];
$magazieItems = $magazieItems ?? [];
$deliveries = $deliveries ?? [];
$deliveryItems = $deliveryItems ?? [];
$projectFiles = $projectFiles ?? [];
$workLogs = $workLogs ?? [];
$history = $history ?? [];
$projectLabels = $projectLabels ?? [];
$cncFiles = $cncFiles ?? [];
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
    <div class="col-12 col-lg-7">
      <div class="card app-card p-3">
        <div class="h5 m-0">Produse (piese) în proiect</div>
        <div class="text-muted">Status producție + cantități (livrate) — totul se loghează</div>

        <?php if (!$projectProducts): ?>
          <div class="text-muted mt-2">Nu există produse încă.</div>
        <?php else: ?>
          <div class="table-responsive mt-2">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Produs</th>
                  <th style="width:120px" class="text-end">Cant.</th>
                  <th style="width:150px">Status</th>
                  <th style="width:140px" class="text-end">Livrat</th>
                  <th class="text-end" style="width:210px">Acțiuni</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($projectProducts as $pp): ?>
                  <?php
                    $ppId = (int)($pp['id'] ?? 0);
                    $qty = (float)($pp['qty'] ?? 0);
                    $del = (float)($pp['delivered_qty'] ?? 0);
                    $pname = (string)($pp['product_name'] ?? '');
                    $pcode = (string)($pp['product_code'] ?? '');
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= htmlspecialchars($pname) ?></div>
                      <div class="text-muted small"><?= htmlspecialchars($pcode) ?></div>
                    </td>
                    <td class="text-end"><?= number_format($qty, 2, '.', '') ?> <?= htmlspecialchars((string)($pp['unit'] ?? '')) ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars((string)($pp['production_status'] ?? '')) ?></td>
                    <td class="text-end"><?= number_format($del, 2, '.', '') ?></td>
                    <td class="text-end">
                      <?php if ($canWrite): ?>
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#ppEdit<?= $ppId ?>">
                          <i class="bi bi-pencil me-1"></i> Editează
                        </button>
                        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/unlink')) ?>" class="d-inline"
                              onsubmit="return confirm('Scoți produsul din proiect?');">
                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                          <button class="btn btn-outline-secondary btn-sm" type="submit">
                            <i class="bi bi-link-45deg me-1"></i> Scoate
                          </button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php if ($canWrite): ?>
                    <tr class="collapse" id="ppEdit<?= $ppId ?>">
                      <td colspan="5">
                        <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/' . $ppId . '/update')) ?>" class="row g-2 align-items-end">
                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                          <div class="col-6 col-md-2">
                            <label class="form-label fw-semibold mb-1">Cant.</label>
                            <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="qty" value="<?= htmlspecialchars((string)$qty) ?>">
                          </div>
                          <div class="col-6 col-md-2">
                            <label class="form-label fw-semibold mb-1">Unit</label>
                            <input class="form-control form-control-sm" name="unit" value="<?= htmlspecialchars((string)($pp['unit'] ?? 'buc')) ?>">
                          </div>
                          <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold mb-1">Status</label>
                            <select class="form-select form-select-sm" name="production_status">
                              <?php foreach (['DE_PREGATIT'=>'De pregătit','CNC'=>'CNC','ATELIER'=>'Atelier','FINISARE'=>'Finisare','GATA'=>'Gata','LIVRAT_PARTIAL'=>'Livrat parțial','LIVRAT_COMPLET'=>'Livrat complet','REBUT'=>'Rebut/Refăcut'] as $val => $lbl): ?>
                                <option value="<?= htmlspecialchars($val) ?>" <?= ((string)($pp['production_status'] ?? '') === $val) ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="col-6 col-md-2">
                            <label class="form-label fw-semibold mb-1">Livrat</label>
                            <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="delivered_qty" value="<?= htmlspecialchars((string)$del) ?>">
                          </div>
                          <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold mb-1">Notă</label>
                            <input class="form-control form-control-sm" name="notes" value="<?= htmlspecialchars((string)($pp['notes'] ?? '')) ?>">
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

    <div class="col-12 col-lg-5">
      <div class="card app-card p-3 mb-3">
        <div class="h5 m-0">Adaugă produs existent</div>
        <div class="text-muted">Selectează un produs din modulul Produse</div>
        <?php if (!$canWrite): ?>
          <div class="text-muted mt-2">Nu ai drepturi de editare.</div>
        <?php else: ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/add-existing')) ?>" class="row g-2 mt-1">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <div class="col-12">
              <label class="form-label fw-semibold">ID produs</label>
              <input class="form-control" name="product_id" placeholder="ex: 123">
              <div class="text-muted small mt-1">Momentan: introdu ID-ul produsului (următorul pas: search).</div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Cantitate</label>
              <input class="form-control" type="number" step="0.01" min="0" name="qty" value="1">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Unit</label>
              <input class="form-control" name="unit" value="buc">
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-outline-secondary" type="submit">
                <i class="bi bi-plus-lg me-1"></i> Adaugă
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <div class="card app-card p-3">
        <div class="h5 m-0">Creează produs nou în proiect</div>
        <div class="text-muted">Se creează produs + se atașează automat</div>
        <?php if (!$canWrite): ?>
          <div class="text-muted mt-2">Nu ai drepturi de editare.</div>
        <?php else: ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/products/create')) ?>" class="row g-2 mt-1">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <div class="col-12">
              <label class="form-label fw-semibold">Denumire</label>
              <input class="form-control" name="name" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Cod (opțional)</label>
              <input class="form-control" name="code">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Cantitate</label>
              <input class="form-control" type="number" step="0.01" min="0" name="qty" value="1" required>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Unit</label>
              <input class="form-control" name="unit" value="buc">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Lățime (mm)</label>
              <input class="form-control" type="number" name="width_mm" min="1">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Lungime (mm)</label>
              <input class="form-control" type="number" name="height_mm" min="1">
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-primary" type="submit">
                <i class="bi bi-plus-lg me-1"></i> Creează
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php elseif ($tab === 'consum'): ?>
  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card app-card p-3">
        <div class="h5 m-0">Consum Magazie (accesorii)</div>
        <div class="text-muted">Rezervat/Consumat — legabil la produs</div>

        <?php if ($canWrite): ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/consum/magazie/create')) ?>" class="row g-2 mt-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <div class="col-12">
              <label class="form-label fw-semibold">Accesoriu</label>
              <select class="form-select" name="item_id" id="magazieItemSelect" style="width:100%"></select>
              <div class="text-muted small mt-1">Caută după Cod WinMentor sau denumire.</div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Cantitate</label>
              <input class="form-control" type="number" step="0.001" min="0.001" name="qty" value="1">
            </div>
            <div class="col-6"></div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Mod</label>
              <select class="form-select" name="mode">
                <option value="CONSUMED">consumat</option>
                <option value="RESERVED">rezervat</option>
              </select>
            </div>
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
                        <?= htmlspecialchars((string)($c['winmentor_code'] ?? '')) ?> · <?= htmlspecialchars((string)($c['item_name'] ?? '')) ?>
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
    </div>

    <div class="col-12 col-lg-6">
      <div class="card app-card p-3">
        <div class="h5 m-0">Consum HPL</div>
        <div class="text-muted">Rezervat/Consumat (plăci întregi) + alocare automată (mp) pe produse</div>

        <?php if ($canWrite): ?>
          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/consum/hpl/create')) ?>" class="row g-2 mt-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <div class="col-12">
              <label class="form-label fw-semibold">Placă HPL</label>
              <select class="form-select" name="board_id" id="hplBoardSelect" style="width:100%"></select>
              <div class="text-muted small mt-1">Caută după cod placă sau coduri culoare. (Cu thumbnail)</div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Plăci (buc)</label>
              <input class="form-control" type="number" step="1" min="1" name="qty_boards" value="1">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Mod</label>
              <select class="form-select" name="mode">
                <option value="RESERVED">rezervat</option>
                <option value="CONSUMED">consumat</option>
              </select>
            </div>
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
              function fmtBoard(opt){
                if (!opt.id) return opt.text;
                const thumb = opt.thumb || null;
                const thumbBack = opt.thumb_back || null;
                if (!thumb && !thumbBack) return opt.text;
                const $row = $('<span class="s2-row"></span>');
                if (thumb) $row.append($('<img class="s2-thumb" />').attr('src', thumb));
                if (thumbBack && thumbBack !== thumb) $row.append($('<img class="s2-thumb2" />').attr('src', thumbBack));
                $row.append($('<span></span>').text(opt.text || ''));
                return $row;
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
          <?php if (!$hplConsum): ?>
            <div class="text-muted">Nu există consumuri HPL încă.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Placă</th>
                    <th style="width:140px" class="text-end">Plăci</th>
                    <th style="width:110px">Mod</th>
                    <th>Notă</th>
                    <th class="text-end" style="width:130px">Acțiuni</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($hplConsum as $c): ?>
                    <?php $cid = (int)($c['id'] ?? 0); ?>
                    <tr>
                      <td class="fw-semibold"><?= htmlspecialchars((string)($c['board_code'] ?? '')) ?> · <?= htmlspecialchars((string)($c['board_name'] ?? '')) ?></td>
                      <?php $qb = (int)($c['qty_boards'] ?? 0); ?>
                      <td class="text-end fw-semibold">
                        <?= $qb > 0 ? ($qb . ' buc') : '—' ?>
                        <div class="text-muted small"><?= number_format((float)($c['qty_m2'] ?? 0), 4, '.', '') ?> mp</div>
                      </td>
                      <td><?= htmlspecialchars((string)($c['mode'] ?? '')) ?></td>
                      <td class="text-muted"><?= htmlspecialchars((string)($c['note'] ?? '')) ?></td>
                      <td class="text-end">
                        <?php if ($canWrite): ?>
                          <form method="post" action="<?= htmlspecialchars(Url::to('/projects/' . (int)$project['id'] . '/consum/hpl/' . $cid . '/delete')) ?>" class="m-0 d-inline"
                                onsubmit="return confirm('Ștergi consumul HPL?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <button class="btn btn-outline-secondary btn-sm" type="submit">
                              <i class="bi bi-trash me-1"></i> Șterge
                            </button>
                          </form>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($hplAlloc): ?>
          <div class="mt-3">
            <div class="fw-semibold">Alocare HPL pe produse (auto)</div>
            <div class="text-muted small">Suma alocărilor per consum ≈ mp</div>
            <div class="table-responsive mt-2">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Placă</th>
                    <th>Produs</th>
                    <th class="text-end" style="width:110px">mp</th>
                    <th style="width:100px">Mod</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($hplAlloc as $a): ?>
                    <tr>
                      <td class="text-muted"><?= htmlspecialchars((string)($a['board_code'] ?? '')) ?></td>
                      <td><?= htmlspecialchars((string)($a['product_name'] ?? '')) ?></td>
                      <td class="text-end fw-semibold"><?= number_format((float)($a['qty_m2'] ?? 0), 4, '.', '') ?></td>
                      <td class="text-muted"><?= htmlspecialchars((string)($a['mode'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
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
    $totAct = 0.0;
    foreach ($workLogs as $w) {
      $he = isset($w['hours_estimated']) && $w['hours_estimated'] !== null && $w['hours_estimated'] !== '' ? (float)$w['hours_estimated'] : 0.0;
      $ha = isset($w['hours_actual']) && $w['hours_actual'] !== null && $w['hours_actual'] !== '' ? (float)$w['hours_actual'] : 0.0;
      $totEst += $he;
      $totAct += $ha;
    }
  ?>
  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="card app-card p-3">
        <div class="h5 m-0">Adaugă ore</div>
        <div class="text-muted">CNC / Atelier (estimate + reale)</div>

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
            <div class="col-4">
              <label class="form-label fw-semibold">Ore estimate</label>
              <input class="form-control" type="number" step="0.01" min="0" name="hours_estimated">
            </div>
            <div class="col-4">
              <label class="form-label fw-semibold">Ore reale</label>
              <input class="form-control" type="number" step="0.01" min="0" name="hours_actual">
            </div>
            <div class="col-4">
              <label class="form-label fw-semibold">Cost/oră</label>
              <input class="form-control" type="number" step="0.01" min="0" name="cost_per_hour">
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
        <div class="text-muted">Sumar ore pe proiect</div>
        <div class="d-flex justify-content-between mt-2">
          <div class="text-muted">Estimate</div>
          <div class="fw-semibold"><?= number_format($totEst, 2, '.', '') ?> h</div>
        </div>
        <div class="d-flex justify-content-between mt-2">
          <div class="text-muted">Reale</div>
          <div class="fw-semibold"><?= number_format($totAct, 2, '.', '') ?> h</div>
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
                  <th class="text-end">Real</th>
                  <th class="text-end">Cost/oră</th>
                  <th>Notă</th>
                  <th class="text-end">Acțiuni</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($workLogs as $w): ?>
                  <?php
                    $wid = (int)($w['id'] ?? 0);
                    $he = isset($w['hours_estimated']) && $w['hours_estimated'] !== null && $w['hours_estimated'] !== '' ? (float)$w['hours_estimated'] : null;
                    $ha = isset($w['hours_actual']) && $w['hours_actual'] !== null && $w['hours_actual'] !== '' ? (float)$w['hours_actual'] : null;
                    $cph = isset($w['cost_per_hour']) && $w['cost_per_hour'] !== null && $w['cost_per_hour'] !== '' ? (float)$w['cost_per_hour'] : null;
                  ?>
                  <tr>
                    <td class="text-muted"><?= htmlspecialchars((string)($w['created_at'] ?? '')) ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars((string)($w['work_type'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)($w['product_name'] ?? '')) ?></td>
                    <td class="text-end"><?= $he !== null ? number_format($he, 2, '.', '') : '—' ?></td>
                    <td class="text-end"><?= $ha !== null ? number_format($ha, 2, '.', '') : '—' ?></td>
                    <td class="text-end"><?= $cph !== null ? number_format($cph, 2, '.', '') : '—' ?></td>
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
                  <?= htmlspecialchars((string)($h['entity_type'] ?? '')) ?>#<?= htmlspecialchars((string)($h['entity_id'] ?? '')) ?>
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
<?php else: ?>
  <div class="card app-card p-4">
    <div class="h5 m-0"><?= htmlspecialchars($tabs[$tab]) ?></div>
    <div class="text-muted mt-1">Acest tab va fi completat în pasul următor.</div>
  </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

