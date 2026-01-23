<?php
use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

$bucket = (string)($bucket ?? '');
$scrapOnly = !empty($scrapOnly);
$showAccounting = !empty($showAccounting);
$colorId = (int)($colorId ?? 0);
$counts = $counts ?? ['all' => 0, 'gt_half' => 0, 'half_to_quarter' => 0, 'lt_quarter' => 0, 'scrap' => 0];
$items = $items ?? [];
$colors = is_array($colors ?? null) ? $colors : [];
$u = Auth::user();
$canUpload = $u && in_array((string)($u['role'] ?? ''), [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);

function _normImg2(string $p): string {
  $p = trim($p);
  if ($p === '') return '';
  if (str_starts_with($p, '/uploads/')) return Url::to($p);
  return $p;
}

function _bucketLabel(string $b): string {
  return match ($b) {
    'gt_half' => 'Mai mari de 1/2 din placa standard',
    'half_to_quarter' => 'Între 1/2 și 1/4 din placa standard',
    'lt_quarter' => 'Mai mici de 1/4 din placa standard',
    'scrap' => 'Stricate',
    default => 'Toate',
  };
}

function _statusLabel(string $s): string {
  return match ($s) {
    'AVAILABLE' => 'Disponibil',
    'RESERVED' => 'Rezervat',
    'SCRAP' => 'Rebut',
    'CONSUMED' => 'Consumat',
    default => $s,
  };
}

function _ratioLabel(?float $pct): string {
  if ($pct === null) return '—';
  if ($pct <= 0) return '<1% din standard';
  if ($pct < 1) return '<1% din standard';
  return number_format($pct, 0, '.', '') . '% din standard';
}

function _qs(array $params): string {
  $parts = [];
  foreach ($params as $k => $v) {
    if ($v === null || $v === '' || $v === false) continue;
    $parts[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
  }
  return $parts ? ('?' . implode('&', $parts)) : '';
}

function _offcutsUrl(string $bucket, bool $scrapOnly, int $colorId, bool $showAccounting): string {
  $params = [];
  if ($bucket !== '') $params['bucket'] = $bucket;
  if ($scrapOnly) $params['scrap'] = '1';
  if ($colorId > 0) $params['color_id'] = (string)$colorId;
  if ($showAccounting) $params['accounting'] = '1';
  return Url::to('/hpl/bucati-rest' . _qs($params));
}

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Bucăți rest</h1>
    <div class="text-muted">Toate piesele (stocabile + interne) care nu sunt la dimensiunea standard.</div>
  </div>
</div>

<div class="card app-card p-3 mb-3">
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <div class="fw-semibold">Filtre:</div>
    <a class="btn btn-sm <?= !$scrapOnly && $bucket === '' ? 'btn-primary' : 'btn-outline-secondary' ?>"
       href="<?= htmlspecialchars(_offcutsUrl('', false, $colorId, $showAccounting)) ?>">
      Toate <span class="badge text-bg-light ms-1"><?= (int)($counts['all'] ?? 0) ?></span>
    </a>
    <a class="btn btn-sm <?= !$scrapOnly && $bucket === 'gt_half' ? 'btn-primary' : 'btn-outline-secondary' ?>"
       href="<?= htmlspecialchars(_offcutsUrl('gt_half', false, $colorId, $showAccounting)) ?>">
      &gt; 1/2 <span class="badge text-bg-light ms-1"><?= (int)($counts['gt_half'] ?? 0) ?></span>
    </a>
    <a class="btn btn-sm <?= !$scrapOnly && $bucket === 'half_to_quarter' ? 'btn-primary' : 'btn-outline-secondary' ?>"
       href="<?= htmlspecialchars(_offcutsUrl('half_to_quarter', false, $colorId, $showAccounting)) ?>">
      1/2 – 1/4 <span class="badge text-bg-light ms-1"><?= (int)($counts['half_to_quarter'] ?? 0) ?></span>
    </a>
    <a class="btn btn-sm <?= !$scrapOnly && $bucket === 'lt_quarter' ? 'btn-primary' : 'btn-outline-secondary' ?>"
       href="<?= htmlspecialchars(_offcutsUrl('lt_quarter', false, $colorId, $showAccounting)) ?>">
      &lt; 1/4 <span class="badge text-bg-light ms-1"><?= (int)($counts['lt_quarter'] ?? 0) ?></span>
    </a>
    <a class="btn btn-sm <?= $scrapOnly ? 'btn-danger' : 'btn-outline-danger' ?>"
       href="<?= htmlspecialchars(_offcutsUrl('', true, $colorId, $showAccounting)) ?>">
      Stricate <span class="badge text-bg-light ms-1"><?= (int)($counts['scrap'] ?? 0) ?></span>
    </a>
    <form method="get" class="d-flex align-items-center gap-2 ms-2">
      <?php if ($bucket !== ''): ?><input type="hidden" name="bucket" value="<?= htmlspecialchars($bucket) ?>"><?php endif; ?>
      <?php if ($scrapOnly): ?><input type="hidden" name="scrap" value="1"><?php endif; ?>
      <?php if ($colorId > 0): ?><input type="hidden" name="color_id" value="<?= (int)$colorId ?>"><?php endif; ?>
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" role="switch" id="toggleAccounting" name="accounting" value="1" <?= $showAccounting ? 'checked' : '' ?>
               onchange="this.form.submit()">
        <label class="form-check-label" for="toggleAccounting">Arată stocuri contabile</label>
      </div>
    </form>
    <div class="ms-auto text-muted small">
      <?= htmlspecialchars($scrapOnly ? _bucketLabel('scrap') : _bucketLabel($bucket)) ?> · Afișez <strong><?= count($items) ?></strong>
    </div>
  </div>
  <?php if ($colors): ?>
    <div class="mt-3">
      <div class="fw-semibold mb-2">Filtru tip culoare:</div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm <?= $colorId <= 0 ? 'btn-primary' : 'btn-outline-secondary' ?>"
           href="<?= htmlspecialchars(_offcutsUrl($bucket, $scrapOnly, 0, $showAccounting)) ?>">
          Toate culorile
        </a>
        <?php foreach ($colors as $c): ?>
          <?php
            $cid = (int)($c['id'] ?? 0);
            $code = (string)($c['code'] ?? '');
            $name = (string)($c['name'] ?? '');
            $thumb = _normImg2((string)($c['thumb'] ?? ''));
            if ($thumb === '') $thumb = _normImg2((string)($c['image'] ?? ''));
            $label = $code !== '' ? $code : ($name !== '' ? $name : ('#' . $cid));
          ?>
          <a class="btn btn-sm <?= $colorId === $cid ? 'btn-primary' : 'btn-outline-secondary' ?> d-flex align-items-center gap-2"
             href="<?= htmlspecialchars(_offcutsUrl($bucket, $scrapOnly, $cid, $showAccounting)) ?>"
             title="<?= htmlspecialchars($name !== '' ? $name : $label) ?>">
            <?php if ($thumb !== ''): ?>
              <img src="<?= htmlspecialchars($thumb) ?>" alt="" style="width:26px;height:26px;object-fit:cover;border-radius:8px;border:1px solid #D9E3E6">
            <?php endif; ?>
            <span class="fw-semibold"><?= htmlspecialchars($label) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<style>
  .offcut-canvas{
    height: 160px;
    background: #F3F7F8;
    border: 1px solid #D9E3E6;
    border-radius: 14px;
    display:flex;
    align-items:center;
    justify-content:center;
    padding: 10px;
    overflow:hidden;
  }
  .offcut-rect{
    background: #E9F4E4;
    border: 2px solid #6FA94A;
    border-radius: 12px;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    color:#111;
    font-weight:700;
    line-height:1.1;
    padding: 6px;
  }
  .offcut-meta{
    display:flex;
    gap:10px;
    align-items:flex-start;
  }
  .offcut-thumbs img{
    width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;
  }
  .offcut-thumbs .lbl{font-size:.78rem;color:#5F6B72;font-weight:700;line-height:1}
  .offcut-kv{font-size:.9rem;color:#111;font-weight:700;line-height:1.15}
  .offcut-sub{font-size:.82rem;color:#5F6B72;font-weight:600;line-height:1.2}
  .offcut-badges{display:flex;gap:6px;flex-wrap:wrap}
  .js-card-link{cursor:pointer}
</style>

<?php if (!$items): ?>
  <div class="card app-card p-4 text-muted">Nu există bucăți rest pentru filtrul curent.</div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($items as $it): ?>
      <?php
        $w = (int)($it['width_mm'] ?? 0);
        $h = (int)($it['height_mm'] ?? 0);
        $qty = (int)($it['qty'] ?? 0);
        $isAcc = (int)($it['is_accounting'] ?? 1);
        $isInternal = ($isAcc === 0);
        $status = (string)($it['status'] ?? '');
        $location = (string)($it['location'] ?? '');
        $isScrap = ($status === 'SCRAP' || $location === 'Depozit (Stricat)');
        $pieceType = (string)($it['piece_type'] ?? '');
        $m2 = (float)($it['area_total_m2'] ?? 0);

        $fThumb = _normImg2((string)($it['face_thumb_path'] ?? ''));
        $bThumb = _normImg2((string)($it['back_thumb_path'] ?? ''));
        if ($bThumb === '') $bThumb = $fThumb;
        $fCode = (string)($it['face_color_code'] ?? '');
        $bCode = (string)($it['back_color_code'] ?? '');
        if ($bCode === '') $bCode = $fCode;
        $fTex = (string)($it['face_texture_name'] ?? '');
        $bTex = (string)($it['back_texture_name'] ?? '');
        if ($bTex === '') $bTex = $fTex;

        $ratio = $it['_area_ratio'] ?? null;
        $ratioPct = (is_numeric($ratio) ? ((float)$ratio * 100.0) : null);
        $bucketKey = (string)($it['_bucket'] ?? '');
        $photo = is_array($it['_photo'] ?? null) ? $it['_photo'] : null;
        $photoUrl = $photo ? (string)($photo['url'] ?? '') : '';
        $photoTitle = $photo ? (string)($photo['original_name'] ?? 'Poză piesă') : '';
        $trashInfo = is_array($it['_trash'] ?? null) ? $it['_trash'] : null;
        $uploadAction = '/hpl/bucati-rest/' . (int)($it['piece_id'] ?? 0) . '/photo';
        $trashAction = '/hpl/bucati-rest/' . (int)($it['piece_id'] ?? 0) . '/trash';
        $qstr = _qs([
          'bucket' => $bucket !== '' ? $bucket : null,
          'scrap' => $scrapOnly ? '1' : null,
          'color_id' => $colorId > 0 ? $colorId : null,
          'accounting' => $showAccounting ? '1' : null,
        ]);
        if ($qstr !== '') {
          $uploadAction .= $qstr;
          $trashAction .= $qstr;
        }
        $pieceLabel = trim((string)($it['board_code'] ?? '') . ' · ' . $h . '×' . $w . ' mm');
      ?>
      <div class="col-12 col-md-6 col-lg-3">
        <div class="card app-card p-3 h-100 js-card-link"
             data-href="<?= htmlspecialchars(Url::to('/stock/boards/' . (int)($it['board_id'] ?? 0))) ?>"
             role="button" tabindex="0">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="offcut-badges">
              <span class="badge <?= $isInternal ? 'text-bg-secondary' : 'text-bg-success' ?>">
                <?= $isInternal ? 'Rest intern' : 'Contabil (stocabil)' ?>
              </span>
              <span class="badge text-bg-light"><?= htmlspecialchars(_statusLabel($status)) ?></span>
              <span class="badge text-bg-light"><?= htmlspecialchars($location) ?></span>
            </div>
            <div class="text-end">
              <div class="text-muted small" title="Suprafață piesă raportată la placa standard">
                <?= htmlspecialchars(_ratioLabel($ratioPct !== null ? (float)$ratioPct : null)) ?>
              </div>
              <?php if ($photoUrl !== ''): ?>
                <a class="btn btn-sm btn-outline-secondary mt-1"
                   href="<?= htmlspecialchars($photoUrl) ?>"
                   data-lightbox-src="<?= htmlspecialchars($photoUrl) ?>"
                   data-lightbox-title="<?= htmlspecialchars($photoTitle !== '' ? $photoTitle : 'Poză piesă') ?>">
                  <i class="bi bi-camera me-1"></i> Poză
                </a>
              <?php endif; ?>
              <?php if ($canUpload): ?>
                <form class="d-inline-block mt-1 js-photo-form"
                      method="post"
                      enctype="multipart/form-data"
                      action="<?= htmlspecialchars(Url::to($uploadAction)) ?>">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                  <input type="file"
                         class="d-none js-photo-input"
                         name="photo"
                         accept="image/jpeg,image/png,image/webp"
                         required>
                  <button class="btn btn-sm btn-outline-secondary js-photo-btn" type="button">
                    <?= $photoUrl !== '' ? 'Schimbă poză' : 'Adaugă poză' ?>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <div class="offcut-canvas mt-2">
            <div class="offcut-rect js-offcut-rect" data-w="<?= $w ?>" data-h="<?= $h ?>">
              <div>
                <div style="font-size:1.05rem"><?= $h ?> × <?= $w ?> mm</div>
                <div class="text-muted" style="font-weight:700;font-size:.85rem"><?= number_format($m2, 2, '.', '') ?> mp</div>
              </div>
            </div>
          </div>

          <div class="offcut-meta mt-2">
            <div class="offcut-thumbs">
              <div class="d-flex gap-1">
                <div class="text-center">
                  <img src="<?= htmlspecialchars($fThumb) ?>" alt="">
                  <div class="lbl mt-1"><?= htmlspecialchars($fCode !== '' ? $fCode : '—') ?></div>
                  <div class="offcut-sub">
                    <?= htmlspecialchars($fTex !== '' ? ('Textură: ' . $fTex) : 'Textură: —') ?>
                  </div>
                </div>
                <div class="text-center">
                  <img src="<?= htmlspecialchars($bThumb) ?>" alt="">
                  <div class="lbl mt-1"><?= htmlspecialchars($bCode !== '' ? $bCode : '—') ?></div>
                  <div class="offcut-sub">
                    <?= htmlspecialchars($bTex !== '' ? ('Textură: ' . $bTex) : 'Textură: —') ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="flex-grow-1">
              <div class="offcut-kv"><?= htmlspecialchars((string)($it['board_code'] ?? '')) ?></div>
              <div class="offcut-sub"><?= htmlspecialchars((string)($it['board_name'] ?? '')) ?></div>
              <div class="offcut-sub mt-1">
                <strong><?= $qty ?></strong> buc · <?= htmlspecialchars($pieceType) ?> · <?= number_format($m2, 2, '.', '') ?> mp
              </div>
              <div class="offcut-sub">
                Filtru: <?= htmlspecialchars($scrapOnly ? _bucketLabel('scrap') : _bucketLabel($bucketKey)) ?>
              </div>
            </div>
          </div>
          <?php if ($canUpload && !$isScrap): ?>
            <div class="mt-2 d-flex justify-content-end">
              <button class="btn btn-sm btn-outline-danger js-trash-piece"
                      type="button"
                      data-piece-id="<?= (int)($it['piece_id'] ?? 0) ?>"
                      data-piece-label="<?= htmlspecialchars($pieceLabel, ENT_QUOTES) ?>"
                      data-action="<?= htmlspecialchars(Url::to($trashAction)) ?>">
                <i class="bi bi-trash3 me-1"></i> Scoate piesa din stoc
              </button>
            </div>
          <?php endif; ?>
          <?php if ($isScrap && $trashInfo): ?>
            <?php
              $trashUser = trim((string)($trashInfo['user_name'] ?? ''));
              if ($trashUser === '') $trashUser = 'User #' . (int)($trashInfo['user_id'] ?? 0);
              $trashNote = trim((string)($trashInfo['note'] ?? ''));
              $trashDt = trim((string)($trashInfo['created_at'] ?? ''));
              $trashDtLabel = '';
              if ($trashDt !== '') {
                try {
                  $dt = new \DateTime($trashDt);
                  $trashDtLabel = $dt->format('d.m.Y H:i');
                } catch (\Throwable $e) {}
              }
            ?>
            <div class="mt-2 p-2 rounded" style="background:#FFF6F6;border:1px solid #F3C6C6">
              <div class="small text-danger fw-semibold">
                Marcat ca stricat de <?= htmlspecialchars($trashUser) ?><?= $trashDtLabel !== '' ? (' · ' . htmlspecialchars($trashDtLabel)) : '' ?>
              </div>
              <?php if ($trashNote !== ''): ?>
                <div class="small text-muted mt-1"><?= nl2br(htmlspecialchars($trashNote)) ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Modal: Scoate piesa din stoc -->
  <div class="modal fade" id="offcutTrashModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border-radius:14px">
        <div class="modal-header">
          <h5 class="modal-title">Scoate piesa din stoc</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
        </div>
        <form method="post" id="offcutTrashForm">
          <div class="modal-body">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <div class="text-muted small mb-2" id="offcutTrashLabel"></div>
            <label class="form-label small">Notă explicativă (obligatoriu)</label>
            <textarea class="form-control" name="note" rows="3" placeholder="Ex: piesă defectă / lovită / zgâriată" required></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Renunță</button>
            <button type="submit" class="btn btn-danger">Confirmă scoaterea</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Scale rectangles to fit canvas, preserving aspect ratio.
    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('.js-offcut-rect').forEach(function(el){
        const w = parseInt(el.getAttribute('data-w') || '0', 10);
        const h = parseInt(el.getAttribute('data-h') || '0', 10);
        const max = Math.max(w, h, 1);
        let wp = (w / max) * 100;
        let hp = (h / max) * 100;
        // avoid too tiny shapes
        wp = Math.max(wp, 22);
        hp = Math.max(hp, 22);
        el.style.width = wp + '%';
        el.style.height = hp + '%';
      });

      // Click pe card -> intră în pagina de stoc a materialului (plăcii).
      document.querySelectorAll('.js-card-link[data-href]').forEach(function(card){
        function go(e){
          const t = (e && e.target) ? e.target : null;
          if (t && t.closest && t.closest('a,button,input,select,textarea,label,form')) return;
          const href = card.getAttribute('data-href');
          if (href) window.location.href = href;
        }
        card.addEventListener('click', go);
        card.addEventListener('keydown', function(e){
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            go(e);
          }
        });
      });

      const modalEl = document.getElementById('offcutTrashModal');
      const formEl = document.getElementById('offcutTrashForm');
      const labelEl = document.getElementById('offcutTrashLabel');
      document.querySelectorAll('.js-trash-piece').forEach(function(btn){
        btn.addEventListener('click', function(){
          const action = btn.getAttribute('data-action') || '';
          const label = btn.getAttribute('data-piece-label') || '';
          if (formEl && action) formEl.setAttribute('action', action);
          if (labelEl) labelEl.textContent = label !== '' ? label : '—';
          if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
          }
        });
      });

      document.querySelectorAll('.js-photo-form').forEach(function(form){
        const input = form.querySelector('.js-photo-input');
        const btn = form.querySelector('.js-photo-btn');
        if (!input || !btn) return;
        btn.addEventListener('click', function(){
          input.click();
        });
        input.addEventListener('change', function(){
          if (input.files && input.files.length > 0) form.submit();
        });
      });
    });
  </script>
<?php endif; ?>

<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

