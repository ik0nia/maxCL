<?php
use App\Core\Csrf;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Url;
use App\Core\View;
use App\Models\Finish;
use App\Models\Texture;

$board = $board ?? [];
$pieces = $pieces ?? [];
$internalPieces = $internalPieces ?? [];
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

// Cerință UI: piesele CONSUMED nu apar în același tabel cu cele disponibile/rezervate.
$piecesConsumed = [];
$piecesActive = [];
foreach ($pieces as $p) {
  if ((string)($p['status'] ?? '') === 'CONSUMED') $piecesConsumed[] = $p;
  else $piecesActive[] = $p;
}

// Mapare consum HPL (#id) -> project_id (pentru link-uri din notițele pieselor rezervate)
$hplConsumptionToProject = [];
try {
  $ids = [];
  foreach ($pieces as $p) {
    $note = (string)($p['notes'] ?? '');
    if ($note === '') continue;
    if (preg_match_all('/consum\s+HPL\s*#\s*(\d+)/i', $note, $m)) {
      foreach (($m[1] ?? []) as $cidRaw) {
        $cid = is_numeric($cidRaw) ? (int)$cidRaw : 0;
        if ($cid > 0) $ids[$cid] = true;
      }
    }
  }
  $ids = array_keys($ids);
  if ($ids) {
    /** @var \PDO $pdo */
    $pdo = DB::pdo();
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, project_id FROM project_hpl_consumptions WHERE id IN ($ph)");
    $st->execute($ids);
    foreach ($st->fetchAll() as $r) {
      $cid = (int)($r['id'] ?? 0);
      $pid = (int)($r['project_id'] ?? 0);
      if ($cid > 0 && $pid > 0) $hplConsumptionToProject[$cid] = $pid;
    }
  }
} catch (\Throwable $e) {
  // ignore (nu blocăm pagina)
}

// Context consumuri pentru piesele CONSUMED: proiect + piesă (produs) + user.
$ppInfoById = [];        // ppId => [project_id, project_code, project_name, product_name]
$ppLastLogById = [];     // ppId => [created_at, user_name, user_email]
$pieceConsumeLogById = []; // pieceId => [created_at, user_name, user_email]
try {
  /** @var \PDO $pdo */
  $pdo = DB::pdo();

  // 1) Extract project_product_ids from notes (ex: "piesă #14")
  $ppIds = [];
  $pieceIds = [];
  foreach ($piecesConsumed as $p) {
    $pieceId = (int)($p['id'] ?? 0);
    if ($pieceId > 0) $pieceIds[$pieceId] = true;
    $note = (string)($p['notes'] ?? '');
    if ($note !== '' && preg_match_all('/piesă\s*#\s*(\d+)/iu', $note, $m)) {
      foreach (($m[1] ?? []) as $raw) {
        $id = is_numeric($raw) ? (int)$raw : 0;
        if ($id > 0) $ppIds[$id] = true;
      }
    }
  }
  $ppIds = array_keys($ppIds);
  $pieceIds = array_keys($pieceIds);

  // 2) ppId -> project/product details
  if ($ppIds) {
    $ph = implode(',', array_fill(0, count($ppIds), '?'));
    $st = $pdo->prepare("
      SELECT
        pp.id,
        pr.id AS project_id,
        pr.code AS project_code,
        pr.name AS project_name,
        p.name AS product_name
      FROM project_products pp
      INNER JOIN projects pr ON pr.id = pp.project_id
      INNER JOIN products p ON p.id = pp.product_id
      WHERE pp.id IN ($ph)
    ");
    $st->execute($ppIds);
    foreach ($st->fetchAll() as $r) {
      $id = (int)($r['id'] ?? 0);
      if ($id <= 0) continue;
      $ppInfoById[$id] = [
        'project_id' => (int)($r['project_id'] ?? 0),
        'project_code' => (string)($r['project_code'] ?? ''),
        'project_name' => (string)($r['project_name'] ?? ''),
        'product_name' => (string)($r['product_name'] ?? ''),
      ];
    }

    // 3) Last status change log per ppId (fallback "cine a dat în consum")
    $ph2 = implode(',', array_fill(0, count($ppIds), '?'));
    $st2 = $pdo->prepare("
      SELECT a.entity_id, a.created_at, a.meta_json, u.name AS user_name, u.email AS user_email
      FROM audit_log a
      LEFT JOIN users u ON u.id = a.actor_user_id
      WHERE a.entity_type = 'project_products'
        AND a.action = 'PROJECT_PRODUCT_STATUS_CHANGE'
        AND a.entity_id IN ($ph2)
      ORDER BY a.id DESC
      LIMIT 2000
    ");
    $st2->execute($ppIds);
    foreach ($st2->fetchAll() as $r) {
      $eid = is_numeric($r['entity_id'] ?? null) ? (int)$r['entity_id'] : 0;
      if ($eid <= 0 || isset($ppLastLogById[$eid])) continue;
      $ppLastLogById[$eid] = [
        'created_at' => (string)($r['created_at'] ?? ''),
        'user_name' => (string)($r['user_name'] ?? ''),
        'user_email' => (string)($r['user_email'] ?? ''),
      ];
    }
  }

  // 4) If we have explicit STOCK_PIECE_MOVE to CONSUMED, prefer that actor
  if ($pieceIds) {
    $ph3 = implode(',', array_fill(0, count($pieceIds), '?'));
    $st3 = $pdo->prepare("
      SELECT a.entity_id, a.created_at, a.meta_json, u.name AS user_name, u.email AS user_email
      FROM audit_log a
      LEFT JOIN users u ON u.id = a.actor_user_id
      WHERE a.entity_type = 'hpl_stock_pieces'
        AND a.action = 'STOCK_PIECE_MOVE'
        AND a.entity_id IN ($ph3)
      ORDER BY a.id DESC
      LIMIT 2000
    ");
    $st3->execute($pieceIds);
    foreach ($st3->fetchAll() as $r) {
      $eid = is_numeric($r['entity_id'] ?? null) ? (int)$r['entity_id'] : 0;
      if ($eid <= 0 || isset($pieceConsumeLogById[$eid])) continue;
      $mj = (string)($r['meta_json'] ?? '');
      $m = $mj !== '' ? json_decode($mj, true) : null;
      $to = (is_array($m) && isset($m['to_status'])) ? (string)$m['to_status'] : '';
      if ($to !== 'CONSUMED') continue;
      $pieceConsumeLogById[$eid] = [
        'created_at' => (string)($r['created_at'] ?? ''),
        'user_name' => (string)($r['user_name'] ?? ''),
        'user_email' => (string)($r['user_email'] ?? ''),
      ];
    }
  }
} catch (\Throwable $e) {
  // ignore (best-effort context)
}

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
                $meta = is_array($h['meta'] ?? null) ? $h['meta'] : null;
                $projId = (is_array($meta) && isset($meta['project_id']) && is_numeric($meta['project_id'])) ? (int)$meta['project_id'] : 0;
                $projCode = (is_array($meta) && isset($meta['project_code'])) ? (string)$meta['project_code'] : '';
                $projName = (is_array($meta) && isset($meta['project_name'])) ? (string)$meta['project_name'] : '';

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
                <?php if ($projId > 0): ?>
                  <div class="mt-1 d-flex flex-wrap gap-2">
                    <a class="badge app-badge text-decoration-none" href="<?= htmlspecialchars(Url::to('/projects/' . $projId)) ?>">
                      Proiect <?= htmlspecialchars($projCode !== '' ? $projCode : ('#' . $projId)) ?>
                    </a>
                    <a class="badge app-badge text-decoration-none" href="<?= htmlspecialchars(Url::to('/projects/' . $projId . '?tab=consum')) ?>">
                      Consum materiale
                    </a>
                    <?php if (trim($projName) !== ''): ?>
                      <span class="text-muted small"><?= htmlspecialchars($projName) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
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
          <?php foreach ($piecesActive as $p): ?>
            <?php
              $noteRaw = (string)($p['notes'] ?? '');
              $noteTrim = trim($noteRaw);
              $noteShort = $noteTrim;
              if ($noteShort !== '') {
                if (mb_strlen($noteShort) > 500) {
                  $noteShort = mb_substr($noteShort, 0, 500) . '…';
                }
              }
              $pStatus = (string)($p['status'] ?? '');
              $pLoc = (string)($p['location'] ?? '');
              // Cerință: ascundem notița DOAR când piesa este în Depozit și Disponibilă,
              // cu excepția revenirilor în stoc (vrem să vedem mesajul de retur).
              $noteLower = mb_strtolower($noteShort);
              $isReturnNote = str_contains($noteLower, 'revenire în stoc') || str_contains($noteLower, 'revenire in stoc');
              $showNote = ($noteShort !== '') && (!($pStatus === 'AVAILABLE' && $pLoc === 'Depozit') || $isReturnNote);

              $noteLink = null;
              if ($showNote && preg_match('/consum\s+HPL\s*#\s*(\d+)/i', $noteShort, $mm)) {
                $cid = is_numeric($mm[1] ?? null) ? (int)$mm[1] : 0;
                $pid = ($cid > 0 && isset($hplConsumptionToProject[$cid])) ? (int)$hplConsumptionToProject[$cid] : 0;
                if ($pid > 0) {
                  $noteLink = Url::to('/projects/' . $pid . '?tab=consum');
                }
              }
            ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars((string)$p['piece_type']) ?></td>
              <td>
                <div class="d-flex flex-column align-items-start">
                  <div><?= htmlspecialchars($pStatus) ?></div>
                  <?php if ($showNote): ?>
                    <?php if ($noteLink): ?>
                      <a class="small d-inline-block rounded text-decoration-none"
                         href="<?= htmlspecialchars($noteLink) ?>"
                         style="margin-top:2px;align-self:flex-start;max-width:520px;text-align:left;white-space:pre-line;line-height:1.15;padding:.1rem .45rem;background:#F8D7DA;border:1px solid #f5c2c7;color:#842029">
                        <?= htmlspecialchars($noteShort) ?>
                      </a>
                    <?php else: ?>
                      <div class="small d-inline-block rounded"
                           style="margin-top:2px;align-self:flex-start;max-width:520px;text-align:left;white-space:pre-line;line-height:1.15;padding:.1rem .45rem;background:#F8D7DA;border:1px solid #f5c2c7;color:#842029">
                        <?= htmlspecialchars($noteShort) ?>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </td>
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

    <div class="card app-card p-3 mt-3">
      <div class="h5 m-0">Consumuri</div>
      <div class="text-muted">Piese HPL consumate (cu proiect, piesă și cine a consumat)</div>
      <?php if (!$piecesConsumed): ?>
        <div class="text-muted mt-2">Nu există consumuri încă.</div>
      <?php else: ?>
        <div class="table-responsive mt-2">
          <table class="table table-hover align-middle mb-0" id="consumedPiecesTable">
            <thead>
              <tr>
                <th>Tip</th>
                <th>Status</th>
                <th>Dimensiuni</th>
                <th class="text-end">Buc</th>
                <th>Locație</th>
                <th class="text-end">mp</th>
                <th>Consum</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($piecesConsumed as $p): ?>
                <?php
                  $pid = (int)($p['id'] ?? 0);
                  $noteRaw = (string)($p['notes'] ?? '');
                  $noteTrim = trim($noteRaw);
                  $noteShort = $noteTrim;
                  if ($noteShort !== '' && mb_strlen($noteShort) > 500) $noteShort = mb_substr($noteShort, 0, 500) . '…';

                  $ppId = 0;
                  if ($noteTrim !== '' && preg_match('/piesă\s*#\s*(\d+)/iu', $noteTrim, $mm)) {
                    $ppId = is_numeric($mm[1] ?? null) ? (int)$mm[1] : 0;
                  }

                  $projId = 0;
                  $projCode = '';
                  $projName = '';
                  $prodName = '';
                  if ($ppId > 0 && isset($ppInfoById[$ppId])) {
                    $projId = (int)($ppInfoById[$ppId]['project_id'] ?? 0);
                    $projCode = (string)($ppInfoById[$ppId]['project_code'] ?? '');
                    $projName = (string)($ppInfoById[$ppId]['project_name'] ?? '');
                    $prodName = (string)($ppInfoById[$ppId]['product_name'] ?? '');
                  } elseif ($noteTrim !== '' && preg_match('/consum\s+HPL\s*#\s*(\d+)/i', $noteTrim, $mm2)) {
                    $cid = is_numeric($mm2[1] ?? null) ? (int)$mm2[1] : 0;
                    if ($cid > 0 && isset($hplConsumptionToProject[$cid])) $projId = (int)$hplConsumptionToProject[$cid];
                  }

                  $who = '';
                  $when = '';
                  if ($pid > 0 && isset($pieceConsumeLogById[$pid])) {
                    $who = trim((string)($pieceConsumeLogById[$pid]['user_name'] ?? '') . ' ' . (string)($pieceConsumeLogById[$pid]['user_email'] ?? ''));
                    $when = (string)($pieceConsumeLogById[$pid]['created_at'] ?? '');
                  } elseif ($ppId > 0 && isset($ppLastLogById[$ppId])) {
                    $who = trim((string)($ppLastLogById[$ppId]['user_name'] ?? '') . ' ' . (string)($ppLastLogById[$ppId]['user_email'] ?? ''));
                    $when = (string)($ppLastLogById[$ppId]['created_at'] ?? '');
                  }
                ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars((string)($p['piece_type'] ?? '')) ?></td>
                  <td class="fw-semibold">
                    <?= htmlspecialchars((string)($p['status'] ?? '')) ?>
                    <?php if ($noteShort !== ''): ?>
                      <div class="small d-inline-block rounded"
                           style="margin-top:2px;align-self:flex-start;max-width:720px;text-align:left;white-space:pre-line;line-height:1.15;padding:.1rem .45rem;background:#F8D7DA;border:1px solid #f5c2c7;color:#842029">
                        <?= htmlspecialchars($noteShort) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td><?= (int)($p['height_mm'] ?? 0) ?> × <?= (int)($p['width_mm'] ?? 0) ?> mm</td>
                  <td class="text-end"><?= (int)($p['qty'] ?? 0) ?></td>
                  <td><?= htmlspecialchars((string)($p['location'] ?? '')) ?></td>
                  <td class="text-end fw-semibold"><?= number_format((float)($p['area_total_m2'] ?? 0), 2, '.', '') ?></td>
                  <td>
                    <?php if ($projId > 0): ?>
                      <div class="d-flex flex-wrap gap-2 align-items-center">
                        <a class="badge app-badge text-decoration-none" href="<?= htmlspecialchars(Url::to('/projects/' . $projId)) ?>">
                          Proiect <?= htmlspecialchars($projCode !== '' ? $projCode : ('#' . $projId)) ?>
                        </a>
                        <a class="badge app-badge text-decoration-none" href="<?= htmlspecialchars(Url::to('/projects/' . $projId . '?tab=consum')) ?>">
                          Consum materiale
                        </a>
                        <?php if (trim($projName) !== ''): ?>
                          <span class="text-muted small"><?= htmlspecialchars($projName) ?></span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                    <?php if ($ppId > 0): ?>
                      <div class="text-muted small mt-1">
                        Piesă #<?= (int)$ppId ?><?= $prodName !== '' ? (' · ' . htmlspecialchars($prodName)) : '' ?>
                      </div>
                    <?php endif; ?>
                    <?php if ($who !== ''): ?>
                      <div class="text-muted small mt-1">
                        <?= htmlspecialchars($who) ?><?= $when !== '' ? (' · ' . htmlspecialchars($when)) : '' ?>
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card app-card p-3 mt-3">
      <div class="h5 m-0">Piese interne (nestocabile)</div>
      <div class="text-muted">Uz intern · nu intră în totalurile stocului / valoare / mp disponibil</div>
      <?php if (!$internalPieces): ?>
        <div class="text-muted mt-2">Nu există piese interne încă.</div>
      <?php else: ?>
        <table class="table table-hover align-middle mb-0 mt-2">
          <thead>
            <tr>
              <th>Status</th>
              <th>Dimensiuni</th>
              <th class="text-end">Buc</th>
              <th>Locație</th>
              <th class="text-end">mp</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($internalPieces as $p): ?>
              <?php
                $noteRaw = (string)($p['notes'] ?? '');
                $noteTrim = trim($noteRaw);
                $noteShort = $noteTrim;
                if ($noteShort !== '') {
                  if (mb_strlen($noteShort) > 500) {
                    $noteShort = mb_substr($noteShort, 0, 500) . '…';
                  }
                }
                $pStatus = (string)($p['status'] ?? '');
                // La interne păstrăm notițele vizibile (sunt pentru uz intern).
              ?>
              <tr>
                <td>
                  <div class="d-flex flex-column align-items-start">
                    <div><?= htmlspecialchars($pStatus) ?></div>
                    <?php if ($noteShort !== ''): ?>
                      <div class="small d-inline-block rounded"
                           style="margin-top:2px;align-self:flex-start;max-width:520px;text-align:left;white-space:pre-line;line-height:1.15;padding:.1rem .45rem;background:#F3F7F8;border:1px solid #D9E3E6;color:#5F6B72">
                        <?= htmlspecialchars($noteShort) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </td>
                <td><?= (int)$p['height_mm'] ?> × <?= (int)$p['width_mm'] ?> mm</td>
                <td class="text-end"><?= (int)$p['qty'] ?></td>
                <td><?= htmlspecialchars((string)$p['location']) ?></td>
                <td class="text-end fw-semibold"><?= number_format((float)$p['area_total_m2'], 2, '.', '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div class="mt-2">
        <a class="btn btn-outline-secondary w-100" href="<?= htmlspecialchars(Url::to('/hpl/piese-interne')) ?>">
          <i class="bi bi-plus-lg me-1"></i> Adaugă piese interne (nestocabile)
        </a>
      </div>
    </div>

    <?php if ($canMove): ?>
      <div class="card app-card p-3 mt-3">
        <div class="h5 m-0">Mutare stoc</div>
        <div class="text-muted">Mută o cantitate pe altă locație și/sau schimbă statusul (devine indisponibil dacă nu este „Disponibil”). <strong>Producție</strong> setează automat statusul pe <strong>Rezervat</strong>.</div>
        <form class="row g-2 mt-2" method="post" action="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)$board['id'] . '/pieces/move')) ?>">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

          <div class="col-12">
            <label class="form-label small">Din piesă</label>
            <select class="form-select" name="from_piece_id" required>
              <option value="">Alege piesa sursă...</option>
              <?php foreach ($piecesActive as $p): ?>
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
            <select class="form-select" name="to_status" id="move_to_status" required>
              <option value="RESERVED" selected>Rezervat / Indisponibil</option>
              <option value="AVAILABLE" id="opt_move_available">Disponibil</option>
              <option value="SCRAP">Rebut / Stricat</option>
              <option value="CONSUMED">Consumat</option>
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label small">Locație destinație</label>
            <select class="form-select" name="to_location" id="move_to_location" required>
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

    // Regulă UI: Producție => status forțat RESERVED (nu permite AVAILABLE)
    const loc = document.getElementById('move_to_location');
    const st = document.getElementById('move_to_status');
    const optAvail = document.getElementById('opt_move_available');
    function applyMoveRule(){
      if (!loc || !st) return;
      const isProd = String(loc.value || '') === 'Producție';
      if (optAvail) optAvail.disabled = isProd;
      if (isProd) st.value = 'RESERVED';
    }
    if (loc) loc.addEventListener('change', applyMoveRule);
    applyMoveRule();
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

