<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$isEdit = ($mode ?? '') === 'edit';
$action = $isEdit ? Url::to('/stock/boards/' . (int)($row['id'] ?? 0) . '/edit') : Url::to('/stock/boards/create');
$v = $row ?? [];
$errors = $errors ?? [];
$colors = $colors ?? [];
$textures = $textures ?? [];

ob_start();
$stdW0 = (int)($v['std_width_mm'] ?? 0);
$stdH0 = (int)($v['std_height_mm'] ?? 0);
$area0 = ($stdW0 > 0 && $stdH0 > 0) ? (($stdW0 * $stdH0) / 1000000.0) : 0.0;
$sale0 = $v['sale_price'] ?? '';
$sale0num = is_numeric(str_replace(',', '.', (string)$sale0)) ? (float)str_replace(',', '.', (string)$sale0) : null;
$ppm0 = ($sale0num !== null && $area0 > 0) ? ($sale0num / $area0) : null;
$finishMap = [];
foreach ($colors as $c) {
  $finishMap[(int)$c['id']] = $c;
}
$faceColorId0 = (int)($v['face_color_id'] ?? 0);
$backColorId0 = (int)($v['back_color_id'] ?? 0);
$faceOpt = $faceColorId0 && isset($finishMap[$faceColorId0]) ? $finishMap[$faceColorId0] : null;
$backOpt = $backColorId0 && isset($finishMap[$backColorId0]) ? $finishMap[$backColorId0] : null;
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0"><?= $isEdit ? 'Editează placă' : 'Placă nouă' ?></h1>
    <div class="text-muted">Alege culori + texturi pe fiecare față și setează dimensiunile standard</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/stock')) ?>" class="btn btn-outline-secondary">Înapoi</a>
  </div>
</div>

<div class="card app-card p-4">
  <form method="post" action="<?= htmlspecialchars($action) ?>" class="row g-3">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="col-12 col-md-3">
      <label class="form-label">Cod *</label>
      <input class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" name="code" value="<?= htmlspecialchars((string)($v['code'] ?? '')) ?>" required>
      <?php if (isset($errors['code'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['code']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-5">
      <label class="form-label">Denumire *</label>
      <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" value="<?= htmlspecialchars((string)($v['name'] ?? '')) ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Brand *</label>
      <input class="form-control <?= isset($errors['brand']) ? 'is-invalid' : '' ?>" name="brand" value="<?= htmlspecialchars((string)($v['brand'] ?? '')) ?>" required>
      <?php if (isset($errors['brand'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['brand']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-2">
      <label class="form-label">Grosime (mm) *</label>
      <input type="number" min="1" class="form-control <?= isset($errors['thickness_mm']) ? 'is-invalid' : '' ?>" name="thickness_mm"
             value="<?= htmlspecialchars((string)($v['thickness_mm'] ?? '')) ?>" required>
      <?php if (isset($errors['thickness_mm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['thickness_mm']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label">Lățime standard (mm) *</label>
      <input type="number" min="1" class="form-control <?= isset($errors['std_width_mm']) ? 'is-invalid' : '' ?>" name="std_width_mm"
             value="<?= htmlspecialchars((string)($v['std_width_mm'] ?? '')) ?>" required>
      <?php if (isset($errors['std_width_mm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['std_width_mm']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label">Lungime standard (mm) *</label>
      <input type="number" min="1" class="form-control <?= isset($errors['std_height_mm']) ? 'is-invalid' : '' ?>" name="std_height_mm"
             value="<?= htmlspecialchars((string)($v['std_height_mm'] ?? '')) ?>" required>
      <?php if (isset($errors['std_height_mm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['std_height_mm']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-md-2">
      <label class="form-label">Preț vânzare (placă standard) (lei)</label>
      <input type="text"
             inputmode="decimal"
             class="form-control <?= isset($errors['sale_price']) ? 'is-invalid' : '' ?>"
             name="sale_price"
             id="sale_price"
             placeholder="ex: 350.00"
             value="<?= htmlspecialchars((string)($v['sale_price'] ?? '')) ?>">
      <?php if (isset($errors['sale_price'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['sale_price']) ?></div><?php endif; ?>
      <div class="form-text">Poți folosi și virgulă (ex: 350,00).</div>
    </div>

    <div class="col-12 col-md-2">
      <label class="form-label">Preț / mp (calculat)</label>
      <input type="text" class="form-control" id="sale_price_per_m2" value="<?= $ppm0 !== null ? htmlspecialchars(number_format((float)$ppm0, 2, '.', '')) : '' ?>" readonly>
      <div class="form-text">Calculat automat din dimensiunea standard.</div>
    </div>

    <div class="col-12 col-lg-8">
      <label class="form-label">Culoare față *</label>
      <select class="form-select <?= isset($errors['face_color_id']) ? 'is-invalid' : '' ?>"
              name="face_color_id"
              id="face_color_id"
              data-endpoint="<?= htmlspecialchars(Url::to('/api/finishes/search')) ?>"
              required>
        <?php if ($faceOpt): ?>
          <option value="<?= (int)$faceOpt['id'] ?>" selected data-thumb="<?= htmlspecialchars((string)$faceOpt['thumb_path']) ?>">
            <?= htmlspecialchars((string)$faceOpt['color_name'] . ' (' . (string)$faceOpt['code'] . ')') ?>
          </option>
        <?php endif; ?>
      </select>
      <?php if (isset($errors['face_color_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['face_color_id']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label">Textură față *</label>
      <select class="form-select <?= isset($errors['face_texture_id']) ? 'is-invalid' : '' ?>" name="face_texture_id" id="face_texture_id" required>
        <option value="">Alege textură...</option>
        <?php foreach ($textures as $t): ?>
          <?php
            $id = (int)$t['id'];
            $sel = ((string)$id === (string)($v['face_texture_id'] ?? '')) ? 'selected' : '';
            $label = (string)$t['name'] . (!empty($t['code']) ? ' (' . (string)$t['code'] . ')' : '');
          ?>
          <option value="<?= $id ?>" <?= $sel ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['face_texture_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['face_texture_id']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-lg-8">
      <label class="form-label">Culoare verso (opțional)</label>
      <select class="form-select <?= isset($errors['back_color_id']) ? 'is-invalid' : '' ?>"
              name="back_color_id"
              id="back_color_id"
              data-endpoint="<?= htmlspecialchars(Url::to('/api/finishes/search')) ?>">
        <?php if ($backOpt): ?>
          <option value="<?= (int)$backOpt['id'] ?>" selected data-thumb="<?= htmlspecialchars((string)$backOpt['thumb_path']) ?>">
            <?= htmlspecialchars((string)$backOpt['color_name'] . ' (' . (string)$backOpt['code'] . ')') ?>
          </option>
        <?php endif; ?>
      </select>
      <?php if (isset($errors['back_color_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['back_color_id']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label">Textură verso (opțional)</label>
      <select class="form-select <?= isset($errors['back_texture_id']) ? 'is-invalid' : '' ?>" name="back_texture_id" id="back_texture_id">
        <option value="">Aceeași față/verso</option>
        <?php foreach ($textures as $t): ?>
          <?php
            $id = (int)$t['id'];
            $sel = ((string)$id === (string)($v['back_texture_id'] ?? '')) ? 'selected' : '';
            $label = (string)$t['name'] . (!empty($t['code']) ? ' (' . (string)$t['code'] . ')' : '');
          ?>
          <option value="<?= $id ?>" <?= $sel ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['back_texture_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['back_texture_id']) ?></div><?php endif; ?>
    </div>

    <div class="col-12">
      <label class="form-label">Note</label>
      <input class="form-control" name="notes" value="<?= htmlspecialchars((string)($v['notes'] ?? '')) ?>">
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2 me-1"></i> Salvează
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/stock')) ?>">Renunță</a>
    </div>
  </form>
</div>

<style>
  .s2-thumb{width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;margin-right:10px}
  .s2-row{display:flex;align-items:center}
</style>
<script>
  function fmtColor(opt){
    if (!opt || !opt.id) return opt && opt.text ? opt.text : '';
    const el = opt.element;
    const thumb = (opt.thumb || (el ? el.getAttribute('data-thumb') : null)) || null;
    if (!thumb) return opt.text;
    const $row = $('<span class="s2-row"></span>');
    $row.append($('<img class="s2-thumb" />').attr('src', thumb));
    $row.append($('<span></span>').text(opt.text));
    return $row;
  }
  $(function(){
    const finishesEndpoint = $('#face_color_id').data('endpoint') || <?= json_encode(Url::to('/api/finishes/search')) ?>;
    function initColorSelect($el, opts){
      const cfg = Object.assign({
        width: '100%',
        templateResult: fmtColor,
        templateSelection: fmtColor,
        escapeMarkup: m => m,
        minimumInputLength: 1,
        minimumResultsForSearch: 0,
        ajax: {
          url: finishesEndpoint,
          dataType: 'json',
          delay: 200,
          data: function (params) { return { q: params.term || '' }; },
          processResults: function (res) {
            if (!res || res.ok !== true) return { results: [] };
            return { results: res.items || [] };
          }
        }
      }, opts || {});
      $el.select2(cfg);
    }

    function makeSelect2BehaveLikeInput($el){
      // Focus direct în câmpul de căutare când se deschide dropdown-ul
      $el.on('select2:open', function(){
        window.setTimeout(function(){
          const s = document.querySelector('.select2-container--open .select2-search__field');
          if (s) s.focus();
        }, 0);
      });
      // Deschide dropdown-ul la focus / tastare (ca un input)
      const $sel = $el.next('.select2-container').find('.select2-selection');
      $sel.on('focus', function(){ $el.select2('open'); });
      $sel.on('keydown', function(e){
        if (!e || !e.key) return;
        // Dacă dropdown-ul e deja deschis, Select2 gestionează tastarea
        if (document.querySelector('.select2-container--open')) return;

        // La tastare, deschide dropdown-ul și pune caracterul în search
        if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
          e.preventDefault();
          const ch = e.key;
          $el.select2('open');
          window.setTimeout(function(){
            const s = document.querySelector('.select2-container--open .select2-search__field');
            if (!s) return;
            s.value = ch;
            // Trigger input pentru AJAX
            s.dispatchEvent(new Event('input', { bubbles: true }));
            s.focus();
          }, 0);
        } else if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          $el.select2('open');
        }
      });
      // Click oriunde pe selecție -> open
      $sel.on('click', function(){ $el.select2('open'); });
    }

    const $face = $('#face_color_id');
    const $back = $('#back_color_id');

    initColorSelect($face, { placeholder: 'Scrie codul… (ex: 1522)' });
    initColorSelect($back, { placeholder: 'Aceeași față/verso (opțional)', allowClear: true });
    makeSelect2BehaveLikeInput($face);
    makeSelect2BehaveLikeInput($back);
    $('#face_texture_id').select2({ width: '100%' });
    $('#back_texture_id').select2({ width: '100%' });

    // Dacă se golește culoarea verso, golește și textura verso (rămâne "Aceeași față/verso")
    $('#back_color_id').on('select2:clear', function(){
      $('#back_texture_id').val('').trigger('change');
    });

    function parseDec(v){
      v = String(v || '').trim().replace(',', '.');
      if (!v) return null;
      const n = Number(v);
      return Number.isFinite(n) ? n : null;
    }
    function recomputePrice(){
      const w = parseInt($('input[name="std_width_mm"]').val() || '0', 10);
      const h = parseInt($('input[name="std_height_mm"]').val() || '0', 10);
      const sp = parseDec($('#sale_price').val());
      const area = (w > 0 && h > 0) ? ((w * h) / 1000000.0) : 0;
      if (sp === null || area <= 0) {
        $('#sale_price_per_m2').val('');
        return;
      }
      const ppm = sp / area;
      $('#sale_price_per_m2').val(ppm.toFixed(2));
    }
    $('#sale_price').on('input', recomputePrice);
    $('input[name="std_width_mm"], input[name="std_height_mm"]').on('input', recomputePrice);
    recomputePrice();
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

