<?php
use App\Core\Csrf;
use App\Core\Auth;
use App\Core\Url;
use App\Core\View;
use App\Models\Finish;
use App\Models\Texture;

$board = $board ?? [];
$pieces = $pieces ?? [];
$history = $history ?? [];
$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
$canMove = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);
$isAdmin = $u && (string)$u['role'] === Auth::ROLE_ADMIN;
$stdW = (int)($board['std_width_mm'] ?? 0);
$stdH = (int)($board['std_height_mm'] ?? 0);
$stdArea = ($stdW > 0 && $stdH > 0) ? (($stdW * $stdH) / 1000000.0) : 0.0;
$salePrice = $board['sale_price'] ?? null;
$salePriceNum = ($salePrice !== null && $salePrice !== '' && is_numeric($salePrice)) ? (float)$salePrice : null;
$salePerM2 = ($salePriceNum !== null && $stdArea > 0) ? ($salePriceNum / $stdArea) : null;
$availableM2 = 0.0;
foreach ($pieces as $p) {
  if ((string)($p['status'] ?? '') !== 'AVAILABLE') continue;
  $availableM2 += (float)($p['area_total_m2'] ?? 0);
}
$availableValueLei = ($canWrite && $salePerM2 !== null && is_finite($salePerM2) && $salePerM2 >= 0)
  ? ($availableM2 * $salePerM2)
  : null;

// Culori + finisaje (texturi) pentru față/verso
$faceFinish = null;
$backFinish = null;
$faceTex = null;
$backTex = null;
try {
  $faceFinish = !empty($board['face_color_id']) ? Finish::find((int)$board['face_color_id']) : null;
  $backFinish = !empty($board['back_color_id']) ? Finish::find((int)$board['back_color_id']) : null;
  if (!$backFinish) $backFinish = $faceFinish;

  $faceTex = !empty($board['face_texture_id']) ? Texture::find((int)$board['face_texture_id']) : null;
  $backTex = !empty($board['back_texture_id']) ? Texture::find((int)$board['back_texture_id']) : null;
  if (!$backTex) $backTex = $faceTex;
} catch (\Throwable $e) {
  // ignore - fallback to empty
}

function _normImg(string $p): string {
  $p = trim($p);
  if ($p === '') return '';
  if (str_starts_with($p, '/uploads/')) return Url::to($p);
  return $p;
}

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Stoc · Placă</h1>
    <div class="text-muted"><?= htmlspecialchars((string)($board['code'] ?? '')) ?> · <?= htmlspecialchars((string)($board['name'] ?? '')) ?></div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-outline-secondary">Înapoi</a>
    <?php if ($canWrite): ?>
      <a href="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$board['id'] . '/edit')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-pencil me-1"></i> Editează
      </a>
      <form method="post" action="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$board['id'] . '/delete')) ?>" class="m-0"
            onsubmit="return confirm('Sigur vrei să ștergi această placă? (doar dacă nu are piese asociate)');">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <button class="btn btn-outline-secondary" type="submit">
          <i class="bi bi-trash me-1"></i> Șterge
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-5">
    <div class="card app-card p-3 mb-3">
      <div class="h5 m-0">Detalii placă</div>
      <div class="text-muted"><?= $canWrite ? 'Dimensiuni standard și prețuri' : 'Dimensiuni standard' ?></div>

      <div class="mt-3">
        <?php
          $fThumb = $faceFinish ? _normImg((string)($faceFinish['thumb_path'] ?? '')) : '';
          $bThumb = $backFinish ? _normImg((string)($backFinish['thumb_path'] ?? '')) : '';
          $fBig = $faceFinish ? _normImg((string)($faceFinish['image_path'] ?? '')) : '';
          $bBig = $backFinish ? _normImg((string)($backFinish['image_path'] ?? '')) : '';
          if ($fBig === '') $fBig = $fThumb;
          if ($bBig === '') $bBig = $bThumb;
          $fCode = $faceFinish ? (string)($faceFinish['code'] ?? '') : '';
          $bCode = $backFinish ? (string)($backFinish['code'] ?? '') : '';
          $fFin = $faceTex ? (string)($faceTex['name'] ?? '') : '';
          $bFin = $backTex ? (string)($backTex['name'] ?? '') : '';
        ?>
        <div class="row g-2">
          <div class="col-6">
            <div class="text-center">
              <div class="text-muted small mb-1"><span class="badge app-badge">Față</span></div>
              <a href="#"
                 data-bs-toggle="modal" data-bs-target="#appLightbox"
                 data-lightbox-src="<?= htmlspecialchars($fBig) ?>"
                 data-lightbox-fallback="<?= htmlspecialchars($fThumb) ?>"
                 data-lightbox-title="<?= htmlspecialchars($fCode !== '' ? $fCode : 'Față') ?>"
                 style="display:inline-block;cursor:zoom-in">
                <img src="<?= htmlspecialchars($fThumb) ?>"
                     alt=""
                     style="width:170px;height:170px;object-fit:cover;border-radius:18px;border:1px solid #D9E3E6;">
              </a>
              <div class="mt-2">
                <div class="fw-semibold" style="font-size:1.05rem;line-height:1.1;color:#111">
                  <?= htmlspecialchars($fCode !== '' ? $fCode : '—') ?>
                </div>
                <div class="text-muted" style="font-weight:600">
                  <?= htmlspecialchars($fFin !== '' ? $fFin : '—') ?>
                </div>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="text-center">
              <div class="text-muted small mb-1"><span class="badge app-badge">Verso</span></div>
              <a href="#"
                 data-bs-toggle="modal" data-bs-target="#appLightbox"
                 data-lightbox-src="<?= htmlspecialchars($bBig) ?>"
                 data-lightbox-fallback="<?= htmlspecialchars($bThumb) ?>"
                 data-lightbox-title="<?= htmlspecialchars($bCode !== '' ? $bCode : 'Verso') ?>"
                 style="display:inline-block;cursor:zoom-in">
                <img src="<?= htmlspecialchars($bThumb) ?>"
                     alt=""
                     style="width:170px;height:170px;object-fit:cover;border-radius:18px;border:1px solid #D9E3E6;">
              </a>
              <div class="mt-2">
                <div class="fw-semibold" style="font-size:1.05rem;line-height:1.1;color:#111">
                  <?= htmlspecialchars($bCode !== '' ? $bCode : '—') ?>
                </div>
                <div class="text-muted" style="font-weight:600">
                  <?= htmlspecialchars($bFin !== '' ? $bFin : '—') ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-2">
        <div class="d-flex justify-content-between border-bottom py-2">
          <div class="text-muted">Brand</div>
          <div class="fw-semibold"><?= htmlspecialchars((string)($board['brand'] ?? '')) ?></div>
        </div>
        <div class="d-flex justify-content-between border-bottom py-2">
          <div class="text-muted">Grosime</div>
          <div class="fw-semibold"><?= (int)($board['thickness_mm'] ?? 0) ?> mm</div>
        </div>
        <div class="d-flex justify-content-between border-bottom py-2">
          <div class="text-muted">Standard</div>
          <div class="fw-semibold"><?= $stdH ?> × <?= $stdW ?> mm</div>
        </div>
        <div class="d-flex justify-content-between border-bottom py-2">
          <div class="text-muted">Suprafață standard</div>
          <div class="fw-semibold"><?= number_format((float)$stdArea, 2, '.', '') ?> mp</div>
        </div>
        <?php if ($canWrite): ?>
          <div class="d-flex justify-content-between border-bottom py-2">
            <div class="text-muted">Preț vânzare (placă)</div>
            <div class="fw-semibold"><?= $salePriceNum !== null ? number_format((float)$salePriceNum, 2, '.', '') . ' lei' : '—' ?></div>
          </div>
          <div class="d-flex justify-content-between py-2">
            <div class="text-muted">Preț / mp (calculat)</div>
            <div class="fw-semibold"><?= $salePerM2 !== null ? number_format((float)$salePerM2, 2, '.', '') . ' lei/mp' : '—' ?></div>
          </div>

          <div class="d-flex justify-content-between border-top pt-2 mt-2">
            <div class="text-muted">Valoare stoc disponibil</div>
            <div class="fw-semibold"><?= $availableValueLei !== null ? number_format((float)$availableValueLei, 2, '.', '') . ' lei' : '—' ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card app-card p-3">
      <div class="h5 m-0">Istoric</div>
      <div class="text-muted">Modificări și mișcări de stoc pentru această placă</div>

      <div class="mt-2">
        <?php if (!$history): ?>
          <div class="text-muted">Nu există evenimente încă.</div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($history as $h): ?>
              <?php
                $who = trim((string)($h['user_name'] ?? ''));
                if ($who === '') {
                  $em = trim((string)($h['user_email'] ?? ''));
                  $who = $em !== '' ? $em : '—';
                }
                $action = (string)($h['action'] ?? '');
                $msg = trim((string)($h['message'] ?? ''));
                if ($msg === '') $msg = $action;

                // Pe pagina plăcii nu repetăm identificarea plăcii (cod/denumire/brand/grosime),
                // fiindcă sunt deja în "Detalii placă".
                // Tăiem orice sufix de forma "· Placă: ...".
                if (str_contains($msg, '· Placă:')) {
                  $msg = trim(explode('· Placă:', $msg, 2)[0]);
                }
                // Mesaje mai scurte pentru acțiuni pe placa însăși
                if ($action === 'BOARD_CREATE') $msg = 'A creat placa.';
                if ($action === 'BOARD_UPDATE') $msg = 'A actualizat placa.';
                if ($action === 'BOARD_DELETE') $msg = 'A șters placa.';
              ?>
              <div class="list-group-item px-0">
                <div class="d-flex justify-content-between gap-2">
                  <div class="fw-semibold" style="color:#111"><?= htmlspecialchars($who) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars((string)($h['created_at'] ?? '')) ?></div>
                </div>
                <div class="text-muted" style="font-weight:600"><?= htmlspecialchars($msg) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card app-card p-3">
      <div class="h5 m-0">Piese asociate</div>
      <div class="text-muted">Lista pieselor pentru această placă</div>
      <table class="table table-hover align-middle mb-0 mt-2" id="piecesTable">
        <thead>
          <tr>
            <th>Tip</th>
            <th>Status</th>
            <th>Dimensiuni</th>
            <th class="text-end">Buc</th>
            <th>Locație</th>
            <th class="text-end">mp</th>
            <?php if ($canWrite): ?><th class="text-end" style="width:110px">Acțiuni</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pieces as $p): ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars((string)$p['piece_type']) ?></td>
              <td><?= htmlspecialchars((string)$p['status']) ?></td>
              <td><?= (int)$p['height_mm'] ?> × <?= (int)$p['width_mm'] ?> mm</td>
              <td class="text-end"><?= (int)$p['qty'] ?></td>
              <td><?= htmlspecialchars((string)$p['location']) ?></td>
              <td class="text-end fw-semibold"><?= number_format((float)$p['area_total_m2'], 2, '.', '') ?></td>
              <?php if ($canWrite): ?>
                <td class="text-end">
                  <form method="post" action="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$board['id'] . '/pieces/' . (int)$p['id'] . '/delete')) ?>"
                        class="d-inline" onsubmit="return confirm('Sigur vrei să ștergi această piesă?');">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">
                      <i class="bi bi-trash me-1"></i> Șterge
                    </button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($canMove): ?>
      <div class="card app-card p-3 mt-3">
        <div class="h5 m-0">Mutare stoc</div>
        <div class="text-muted">Mută o cantitate pe altă locație și/sau schimbă statusul (devine indisponibil dacă nu este „Disponibil”).</div>
        <form class="row g-2 mt-2" method="post" action="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$board['id'] . '/pieces/move')) ?>">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

          <div class="col-12">
            <label class="form-label small">Din piesă</label>
            <select class="form-select" name="from_piece_id" required>
              <option value="">Alege piesa sursă...</option>
              <?php foreach ($pieces as $p): ?>
                <?php
                  $pid = (int)$p['id'];
                  $lbl = (string)$p['piece_type'] . ' · ' . (int)$p['height_mm'] . '×' . (int)$p['width_mm'] . ' mm · ' . (int)$p['qty'] . ' buc · ' . (string)$p['location'] . ' · ' . (string)$p['status'];
                ?>
                <option value="<?= $pid ?>"><?= htmlspecialchars($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-4">
            <label class="form-label small">Bucăți de mutat</label>
            <input type="number" min="1" class="form-control" name="qty" value="1" required>
          </div>

          <div class="col-6 col-md-4">
            <label class="form-label small">Status destinație</label>
            <select class="form-select" name="to_status" required>
              <option value="RESERVED" selected>Rezervat / Indisponibil</option>
              <option value="AVAILABLE">Disponibil</option>
              <option value="SCRAP">Rebut / Stricat</option>
              <option value="CONSUMED">Consumat</option>
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label small">Locație destinație</label>
            <select class="form-select" name="to_location" required>
              <option value="">Alege locație...</option>
              <option value="Depozit">Depozit</option>
              <option value="Producție">Producție</option>
              <option value="Magazin">Magazin</option>
              <option value="Depozit (Stricat)">Depozit (Stricat)</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label small">Notiță (obligatoriu)</label>
            <textarea class="form-control" name="note" rows="2" placeholder="ex: Mutat pentru prelucrare / Rezervat pentru comandă / Placă defectă" required></textarea>
          </div>

          <div class="col-12">
            <button class="btn btn-outline-secondary w-100" type="submit">
              <i class="bi bi-arrow-left-right me-1"></i> Mută
            </button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($canWrite): ?>
      <div class="card app-card p-3 mt-3">
        <div class="h5 m-0">Adaugă piesă în stoc</div>
        <div class="text-muted">Poți adăuga plăci întregi (FULL) sau resturi (OFFCUT) cu dimensiuni specifice.</div>
        <form class="row g-2 mt-2" method="post" action="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$board['id'] . '/pieces/add')) ?>">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

          <div class="col-12 col-md-4">
            <label class="form-label small">Tip</label>
            <select class="form-select" name="piece_type" required>
              <option value="FULL">Placă (FULL)</option>
              <option value="OFFCUT">Rest (OFFCUT)</option>
            </select>
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label small">Lungime (mm)</label>
            <input type="number" min="1" class="form-control" name="height_mm" value="<?= (int)($board['std_height_mm'] ?? 0) ?>" required>
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label small">Lățime (mm)</label>
            <input type="number" min="1" class="form-control" name="width_mm" value="<?= (int)($board['std_width_mm'] ?? 0) ?>" required>
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label small">Buc</label>
            <input type="number" min="1" class="form-control" name="qty" value="1" required>
          </div>
          <div class="col-6 col-md-8">
            <label class="form-label small">Locație</label>
            <select class="form-select" name="location" required>
              <option value="">Alege locație...</option>
              <option value="Depozit">Depozit</option>
              <option value="Producție">Producție</option>
              <option value="Magazin">Magazin</option>
              <option value="Depozit (Stricat)">Depozit (Stricat)</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small">Note</label>
            <input class="form-control" name="notes">
          </div>
          <div class="col-12">
            <div class="text-muted small">
              Notă: dacă dimensiunile diferă de standard, piesa se salvează automat ca <strong>OFFCUT</strong>.
            </div>
          </div>
          <div class="col-12">
            <button class="btn btn-primary w-100" type="submit">
              <i class="bi bi-plus-lg me-1"></i> Adaugă piesă
            </button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('piecesTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25 });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

