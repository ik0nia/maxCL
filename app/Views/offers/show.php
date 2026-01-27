<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$offer = $offer ?? [];
$offerProducts = $offerProducts ?? [];
$hplByProduct = $hplByProduct ?? [];
$accByProduct = $accByProduct ?? [];
$workByProduct = $workByProduct ?? [];
$productTotals = $productTotals ?? [];
$totals = $totals ?? ['cost_total' => 0.0, 'sale_total' => 0.0];
$tab = $tab ?? 'general';
$statuses = $statuses ?? [];
$clients = $clients ?? [];
$groups = $groups ?? [];
$openNewProduct = empty($offerProducts);
$validityValue = (string)($offer['validity_days'] ?? '');
if ($validityValue === '') $validityValue = '14';

$u = Auth::user();
$canWrite = $u && in_array((string)($u['role'] ?? ''), [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);
$isAdmin = $u && (string)($u['role'] ?? '') === Auth::ROLE_ADMIN;
$offerId = (int)($offer['id'] ?? 0);
$convertedProjectId = (int)($offer['converted_project_id'] ?? 0);

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Oferta <?= htmlspecialchars((string)($offer['code'] ?? '')) ?></h1>
    <div class="text-muted"><?= htmlspecialchars((string)($offer['name'] ?? '')) ?></div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/offers')) ?>" class="btn btn-outline-secondary">Înapoi</a>
    <a class="btn btn-primary" target="_blank" href="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/bon-general')) ?>">
      <i class="bi bi-file-earmark-text me-1"></i> Print ofertă
    </a>
    <?php if ($convertedProjectId > 0): ?>
      <a class="btn btn-success" href="<?= htmlspecialchars(Url::to('/projects/' . $convertedProjectId)) ?>">
        <i class="bi bi-box-arrow-up-right me-1"></i> Proiect creat
      </a>
    <?php elseif ($canWrite): ?>
      <form method="post" action="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/convert')) ?>" class="m-0"
            onsubmit="return confirm('Transformi oferta în proiect? Se vor rezerva materialele.');">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <button class="btn btn-success" type="submit">
          <i class="bi bi-box-arrow-up-right me-1"></i> Transformă în proiect
        </button>
      </form>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
      <form method="post" action="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/delete')) ?>" class="m-0"
            onsubmit="return confirm('Ștergi oferta? Se vor șterge produsele și consumurile asociate.');">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <button class="btn btn-outline-danger" type="submit" <?= $convertedProjectId > 0 ? 'disabled' : '' ?>>
          <i class="bi bi-trash me-1"></i> Șterge oferta
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'general' ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '?tab=general')) ?>">General</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'products' ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '?tab=products')) ?>">Produse</a>
  </li>
</ul>

<?php if ($tab === 'general'): ?>
  <div class="card app-card p-3">
    <form method="post" action="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/edit')) ?>" class="row g-3">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
      <div class="col-12 col-md-3">
        <label class="form-label fw-semibold">Cod</label>
        <input class="form-control" value="<?= htmlspecialchars((string)($offer['code'] ?? '')) ?>" readonly>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Nume</label>
        <input class="form-control" name="name" value="<?= htmlspecialchars((string)($offer['name'] ?? '')) ?>" <?= $canWrite ? '' : 'readonly' ?>>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label fw-semibold">Status</label>
        <select class="form-select" name="status" <?= $canWrite ? '' : 'disabled' ?>>
          <?php foreach ($statuses as $s): ?>
            <option value="<?= htmlspecialchars((string)$s['value']) ?>" <?= ((string)($offer['status'] ?? '') === (string)$s['value']) ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)$s['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label fw-semibold">Categorie</label>
        <input class="form-control" name="category" value="<?= htmlspecialchars((string)($offer['category'] ?? '')) ?>" <?= $canWrite ? '' : 'readonly' ?>>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label fw-semibold">Deadline</label>
        <input class="form-control" type="date" name="due_date" value="<?= htmlspecialchars((string)($offer['due_date'] ?? '')) ?>" <?= $canWrite ? '' : 'readonly' ?>>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label fw-semibold">Valabilitate ofertă (zile)</label>
        <input class="form-control" type="number" min="1" max="3650" name="validity_days" value="<?= htmlspecialchars($validityValue) ?>" <?= $canWrite ? '' : 'readonly' ?>>
      </div>
      <div class="col-12">
        <label class="form-label fw-semibold">Descriere</label>
        <textarea class="form-control" name="description" rows="2" <?= $canWrite ? '' : 'readonly' ?>><?= htmlspecialchars((string)($offer['description'] ?? '')) ?></textarea>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Client (opțional)</label>
        <select class="form-select" name="client_id" <?= $canWrite ? '' : 'disabled' ?>>
          <option value="">—</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int)($c['id'] ?? 0) ?>" <?= ((string)($offer['client_id'] ?? '') === (string)($c['id'] ?? '')) ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)($c['name'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="text-muted small mt-1">Alege fie client, fie grup.</div>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Grup de clienți (opțional)</label>
        <select class="form-select" name="client_group_id" <?= $canWrite ? '' : 'disabled' ?>>
          <option value="">—</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= (int)$g['id'] ?>" <?= ((string)($offer['client_group_id'] ?? '') === (string)$g['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)$g['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="text-muted small mt-1">Alege fie client, fie grup.</div>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Note</label>
        <textarea class="form-control" name="notes" rows="3" <?= $canWrite ? '' : 'readonly' ?>><?= htmlspecialchars((string)($offer['notes'] ?? '')) ?></textarea>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Note tehnice</label>
        <textarea class="form-control" name="technical_notes" rows="3" <?= $canWrite ? '' : 'readonly' ?>><?= htmlspecialchars((string)($offer['technical_notes'] ?? '')) ?></textarea>
      </div>
      <?php if ($canWrite): ?>
        <div class="col-12 d-flex justify-content-end">
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-save me-1"></i> Salvează
          </button>
        </div>
      <?php endif; ?>
    </form>
  </div>
<?php elseif ($tab === 'products'): ?>
  <div class="row g-3">
    <div class="col-12">
      <div class="card app-card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="h5 m-0">Adaugă produs (nou)</div>
            <div class="text-muted">Completează cantitatea, produsul se adaugă direct în ofertă</div>
          </div>
          <?php if ($canWrite): ?>
            <button class="btn btn-success btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#offerAddNewCollapse" aria-expanded="<?= $openNewProduct ? 'true' : 'false' ?>" aria-controls="offerAddNewCollapse">
              <i class="bi bi-plus-lg me-1"></i> Creează produs
            </button>
          <?php endif; ?>
        </div>
        <?php if (!$canWrite): ?>
          <div class="text-muted mt-2">Nu ai drepturi de editare.</div>
        <?php else: ?>
          <div class="collapse mt-3<?= $openNewProduct ? ' show' : '' ?>" id="offerAddNewCollapse">
            <form method="post" action="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/products/create')) ?>" class="row g-2">
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
                <label class="form-label fw-semibold">Preț cu discount (lei)</label>
                <input class="form-control" type="number" step="0.01" min="0" name="sale_price" placeholder="opțional">
              </div>
              <div class="col-12 d-flex justify-content-end">
                <button class="btn btn-primary" type="submit">
                  <i class="bi bi-plus-lg me-1"></i> Creează
                </button>
              </div>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <div class="card app-card p-3 mb-3">
        <div class="d-flex align-items-center justify-content-between">
          <div class="h5 m-0">Total ofertă</div>
          <div class="text-end">
            <div class="fw-semibold">Cost total: <?= number_format((float)$totals['cost_total'], 2, '.', '') ?> lei</div>
            <div class="text-muted">Preț ofertă: <?= number_format((float)$totals['sale_total'], 2, '.', '') ?> lei</div>
          </div>
        </div>
      </div>

      <?php foreach ($offerProducts as $op): ?>
        <?php
          $opId = (int)($op['id'] ?? 0);
          $hplRows = $hplByProduct[$opId] ?? [];
          $accRows = $accByProduct[$opId] ?? [];
          $workRows = $workByProduct[$opId] ?? [];
          $tot = $productTotals[$opId] ?? ['hpl_cost' => 0.0, 'acc_cost' => 0.0, 'labor_cost' => 0.0, 'cost_total' => 0.0, 'sale_total' => 0.0];
          $desc = trim((string)($op['notes'] ?? ''));
          if ($desc === '') $desc = trim((string)($op['product_notes'] ?? ''));
        ?>
        <div class="card app-card p-3 mb-3" id="op-<?= $opId ?>">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
              <div class="h4 m-0 text-success fw-semibold"><?= htmlspecialchars((string)($op['product_name'] ?? '')) ?></div>
              <?php if ($desc !== ''): ?>
                <div class="text-muted"><?= nl2br(htmlspecialchars($desc)) ?></div>
              <?php endif; ?>
              <div class="text-muted small">Cantitate: <?= number_format((float)($op['qty'] ?? 0), 2, '.', '') ?> <?= htmlspecialchars((string)($op['unit'] ?? 'buc')) ?></div>
            </div>
            <div class="text-end">
              <div class="text-muted small">Preț cu discount</div>
              <div class="fw-semibold"><?= number_format((float)($op['product_sale_price'] ?? 0), 2, '.', '') ?> lei</div>
            </div>
          </div>

          <?php if ($canWrite): ?>
            <form method="post" action="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/products/' . $opId . '/update')) ?>" class="row g-2 mt-2">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
              <div class="col-6 col-md-3">
                <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="qty" value="<?= htmlspecialchars((string)($op['qty'] ?? '')) ?>">
              </div>
              <div class="col-6 col-md-2">
                <input class="form-control form-control-sm" name="unit" value="<?= htmlspecialchars((string)($op['unit'] ?? 'buc')) ?>">
              </div>
              <div class="col-12 col-md-4">
                <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="sale_price"
                       value="<?= isset($op['product_sale_price']) && $op['product_sale_price'] !== null ? htmlspecialchars(number_format((float)$op['product_sale_price'], 2, '.', '')) : '' ?>"
                       placeholder="Preț cu discount">
              </div>
              <div class="col-12 col-md-12">
                <textarea class="form-control form-control-sm" name="description" rows="2" maxlength="4000" placeholder="Descriere (opțional)"><?= htmlspecialchars($desc) ?></textarea>
              </div>
              <div class="col-12 col-md-12 d-flex justify-content-end gap-2">
                <button class="btn btn-outline-secondary btn-sm" type="submit">Actualizează</button>
              </div>
            </form>
          <?php endif; ?>

          <div class="mt-2">
            <div class="h5 text-success fw-semibold">Consum HPL</div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Placă</th>
                    <th>Mod</th>
                    <th class="text-end">Cantitate</th>
                    <th class="text-end">Preț</th>
                    <th class="text-end">Total</th>
                    <th style="width:80px"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($hplRows as $hr): ?>
                    <?php
                      $bprice = isset($hr['board_sale_price']) && $hr['board_sale_price'] !== null && $hr['board_sale_price'] !== '' ? (float)$hr['board_sale_price'] : 0.0;
                      $qtyBoards = (float)($hr['qty'] ?? 0.0);
                      $mode = strtoupper((string)($hr['consume_mode'] ?? 'FULL'));
                      $coef = $mode === 'HALF' ? 0.5 : 1.0;
                      $lineTotal = $bprice * $qtyBoards * $coef;
                    ?>
                    <tr>
                      <td><?= htmlspecialchars((string)($hr['board_code'] ?? '')) ?> · <?= htmlspecialchars((string)($hr['board_name'] ?? '')) ?></td>
                      <td><?= htmlspecialchars($mode) ?></td>
                      <td class="text-end"><?= number_format($qtyBoards, 2, '.', '') ?></td>
                      <td class="text-end"><?= number_format($bprice * $coef, 2, '.', '') ?></td>
                      <td class="text-end fw-semibold"><?= number_format($lineTotal, 2, '.', '') ?></td>
                      <td class="text-end">
                        <?php if ($canWrite): ?>
                          <form method="post" action="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/products/' . $opId . '/hpl/' . (int)($hr['id'] ?? 0) . '/delete')) ?>" class="m-0"
                                onsubmit="return confirm('Ștergi consumul HPL?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <button class="btn btn-outline-secondary btn-sm" type="submit">Șterge</button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$hplRows): ?>
                    <tr><td colspan="6" class="text-muted">—</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php if ($canWrite): ?>
              <form method="post" action="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/products/' . $opId . '/hpl/create')) ?>" class="row g-2 mt-2 align-items-end">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <div class="col-12 col-md-6">
                  <select class="form-select form-select-sm js-offer-hpl-board" name="board_id" data-placeholder="Caută placă HPL…" style="width:100%"></select>
                </div>
                <div class="col-6 col-md-3">
                  <input class="form-control form-control-sm" type="number" step="0.01" min="0.01" name="qty" value="1">
                </div>
                <div class="col-6 col-md-2">
                  <select class="form-select form-select-sm" name="consume_mode">
                    <option value="FULL">Full</option>
                    <option value="HALF">Jumătate</option>
                  </select>
                </div>
                <div class="col-12 col-md-1 d-flex align-items-end">
                  <button class="btn btn-success btn-sm w-100" type="submit">+</button>
                </div>
              </form>
            <?php endif; ?>
          </div>

          <div class="mt-2">
            <div class="h5 text-success fw-semibold">Consum Accesorii</div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Accesoriu</th>
                    <th class="text-end">Cantitate</th>
                    <th class="text-end">Preț</th>
                    <th class="text-end">Total</th>
                    <th style="width:80px"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($accRows as $ar): ?>
                    <?php
                      $unitPrice = isset($ar['unit_price']) && $ar['unit_price'] !== null && $ar['unit_price'] !== '' ? (float)$ar['unit_price'] : (float)($ar['item_unit_price'] ?? 0);
                      $lineTotal = $unitPrice * (float)($ar['qty'] ?? 0.0);
                    ?>
                    <tr>
                      <td><?= htmlspecialchars((string)($ar['item_code'] ?? '')) ?> · <?= htmlspecialchars((string)($ar['item_name'] ?? '')) ?></td>
                      <td class="text-end"><?= number_format((float)($ar['qty'] ?? 0), 2, '.', '') ?> <?= htmlspecialchars((string)($ar['unit'] ?? 'buc')) ?></td>
                      <td class="text-end"><?= number_format($unitPrice, 2, '.', '') ?></td>
                      <td class="text-end fw-semibold"><?= number_format($lineTotal, 2, '.', '') ?></td>
                      <td class="text-end">
                        <?php if ($canWrite): ?>
                          <form method="post" action="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/products/' . $opId . '/accessories/' . (int)($ar['id'] ?? 0) . '/delete')) ?>" class="m-0"
                                onsubmit="return confirm('Ștergi accesoriul?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <button class="btn btn-outline-secondary btn-sm" type="submit">Șterge</button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$accRows): ?>
                    <tr><td colspan="5" class="text-muted">—</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php if ($canWrite): ?>
              <form method="post" action="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/products/' . $opId . '/accessories/create')) ?>" class="row g-2 mt-2 align-items-end">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <div class="col-12 col-md-6">
                  <select class="form-select form-select-sm js-offer-magazie-item" name="item_id" data-placeholder="Caută accesoriu…" style="width:100%"></select>
                </div>
                <div class="col-6 col-md-3">
                  <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="qty" value="1">
                </div>
                <div class="col-6 col-md-2 d-flex align-items-center">
                  <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="include_in_deviz" id="accDeviz<?= $opId ?>">
                    <label class="form-check-label" for="accDeviz<?= $opId ?>">Vizibil pe deviz</label>
                  </div>
                </div>
                <div class="col-12 col-md-1 d-flex align-items-end">
                  <button class="btn btn-success btn-sm w-100" type="submit">+</button>
                </div>
              </form>
            <?php endif; ?>
          </div>

          <div class="mt-2">
            <div class="h5 text-success fw-semibold">Manoperă</div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Tip</th>
                    <th class="text-end">Minute</th>
                    <th class="text-end">Cost/oră</th>
                    <th class="text-end">Total</th>
                    <th style="width:80px"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($workRows as $wr): ?>
                    <?php
                      $he = isset($wr['hours_estimated']) && $wr['hours_estimated'] !== null && $wr['hours_estimated'] !== '' ? (float)$wr['hours_estimated'] : 0.0;
                      $cph = isset($wr['cost_per_hour']) && $wr['cost_per_hour'] !== null && $wr['cost_per_hour'] !== '' ? (float)$wr['cost_per_hour'] : 0.0;
                      $lineTotal = ($he / 60.0) * $cph;
                    ?>
                    <tr>
                      <td><?= htmlspecialchars((string)($wr['work_type'] ?? '')) ?></td>
                      <td class="text-end"><?= number_format($he, 2, '.', '') ?></td>
                      <td class="text-end"><?= number_format($cph, 2, '.', '') ?></td>
                      <td class="text-end fw-semibold"><?= number_format($lineTotal, 2, '.', '') ?></td>
                      <td class="text-end">
                        <?php if ($canWrite): ?>
                          <form method="post" action="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/products/' . $opId . '/work/' . (int)($wr['id'] ?? 0) . '/delete')) ?>" class="m-0"
                                onsubmit="return confirm('Ștergi manopera?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <button class="btn btn-outline-secondary btn-sm" type="submit">Șterge</button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$workRows): ?>
                    <tr><td colspan="5" class="text-muted">—</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php if ($canWrite): ?>
              <form method="post" action="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/products/' . $opId . '/work/create')) ?>" class="row g-2 mt-2 align-items-end">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <div class="col-12 col-md-3">
                  <select class="form-select form-select-sm" name="work_type">
                    <option value="CNC">CNC</option>
                    <option value="ATELIER">Atelier</option>
                  </select>
                </div>
                <div class="col-6 col-md-3">
                  <input class="form-control form-control-sm" type="number" step="0.01" min="0.01" name="minutes_estimated" placeholder="Minute">
                </div>
                <div class="col-6 col-md-4">
                  <input class="form-control form-control-sm" name="note" placeholder="Notă (opțional)">
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end">
                  <button class="btn btn-success btn-sm w-100" type="submit">+</button>
                </div>
              </form>
            <?php endif; ?>
          </div>

          <div class="mt-3 d-flex justify-content-end">
            <div class="text-end">
              <div class="fw-semibold">Preț de listă: <?= number_format((float)($tot['cost_total'] ?? 0), 2, '.', '') ?> lei</div>
              <div class="text-muted">Preț cu discount: <?= number_format((float)($tot['sale_total'] ?? 0), 2, '.', '') ?> lei</div>
            </div>
          </div>
          <?php if ($canWrite): ?>
            <div class="mt-2 d-flex justify-content-end">
              <form method="post" action="<?= htmlspecialchars(Url::to('/offers/' . $offerId . '/products/' . $opId . '/delete')) ?>"
                    onsubmit="return confirm('Ștergi produsul din ofertă?');">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button class="btn btn-danger btn-sm" type="submit">Șterge produs</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <style>
    .s2-thumb{width:28px;height:28px;object-fit:cover;border-radius:8px;border:1px solid #D9E3E6;margin-right:8px}
    .s2-thumb2{width:28px;height:28px;object-fit:cover;border-radius:8px;border:1px solid #D9E3E6;margin-right:8px;margin-left:-6px}
    .s2-row{display:flex;align-items:center}
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
      const $ = window.jQuery;
      $('.js-offer-magazie-item').each(function(){
        const $el = $(this);
        if ($el.data('select2')) return;
        $el.select2({
          width: '100%',
          placeholder: $el.data('placeholder') || 'Caută accesoriu…',
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

      const basePath = "<?= htmlspecialchars(Url::basePath()) ?>";
      function normThumb(path){
        if (!path) return '';
        const s = String(path);
        if (/^https?:\/\//i.test(s)) return s;
        if (basePath && s.startsWith(basePath + '/')) return s;
        if (s.startsWith('/')) return (basePath || '') + s;
        return (basePath || '') + '/' + s;
      }
      function fmtBoard(opt){
        if (!opt || opt.loading) return (opt && opt.text) ? opt.text : '';
        const thumb = normThumb(opt.thumb || '');
        const thumbBack = normThumb(opt.thumb_back || '');
        const fc = opt.face_color_code || '';
        const bc = opt.back_color_code || '';
        let colors = fc ? String(fc) : '';
        if (bc && bc !== fc) colors = colors ? (colors + '/' + String(bc)) : String(bc);
        const label = String(opt.text || '');
        if (!thumb && !thumbBack && !colors) return label;
        const esc = (s) => String(s || '').replace(/</g,'&lt;');
        const $row = $('<span class="s2-row"></span>');
        if (thumb) $row.append($('<img class="s2-thumb" alt="" />').attr('src', thumb));
        if (thumbBack && thumbBack !== thumb) $row.append($('<img class="s2-thumb2" alt="" />').attr('src', thumbBack));
        const $txt = $('<span></span>');
        if (colors) {
          $txt.html('<strong>' + esc(colors) + '</strong>' + (label ? (' · ' + esc(label)) : ''));
        } else {
          $txt.text(label);
        }
        $row.append($txt);
        return $row;
      }

      $('.js-offer-hpl-board').each(function(){
        const $el = $(this);
        if ($el.data('select2')) return;
        $el.select2({
          width: '100%',
          placeholder: $el.data('placeholder') || 'Caută placă HPL…',
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
    });
  </script>
<?php endif; ?>

<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

