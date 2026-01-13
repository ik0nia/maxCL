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
      <input type="hidden" name="face_color_id" id="face_color_id" value="<?= htmlspecialchars((string)($v['face_color_id'] ?? '')) ?>">
      <div class="position-relative">
        <div class="input-group">
          <span class="input-group-text" style="width:54px;justify-content:center">
            <img id="face_color_thumb"
                 src="<?= htmlspecialchars((string)($faceOpt['thumb_path'] ?? '')) ?>"
                 alt=""
                 style="width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;<?= $faceOpt ? '' : 'display:none' ?>">
          </span>
          <input class="form-control <?= isset($errors['face_color_id']) ? 'is-invalid' : '' ?>"
                 id="face_color_q"
                 placeholder="Scrie codul… (ex: 1522)"
                 autocomplete="off"
                 value="<?= $faceOpt ? htmlspecialchars((string)$faceOpt['color_name'] . ' (' . (string)$faceOpt['code'] . ')') : '' ?>">
        </div>
        <?php if (isset($errors['face_color_id'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['face_color_id']) ?></div><?php endif; ?>
        <div class="app-ac-list" id="face_color_list" style="display:none"></div>
      </div>
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
      <input type="hidden" name="back_color_id" id="back_color_id" value="<?= htmlspecialchars((string)($v['back_color_id'] ?? '')) ?>">
      <div class="position-relative">
        <div class="input-group">
          <span class="input-group-text" style="width:54px;justify-content:center">
            <img id="back_color_thumb"
                 src="<?= htmlspecialchars((string)($backOpt['thumb_path'] ?? '')) ?>"
                 alt=""
                 style="width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;<?= $backOpt ? '' : 'display:none' ?>">
          </span>
          <input class="form-control <?= isset($errors['back_color_id']) ? 'is-invalid' : '' ?>"
                 id="back_color_q"
                 placeholder="Scrie codul… (opțional)"
                 autocomplete="off"
                 value="<?= $backOpt ? htmlspecialchars((string)$backOpt['color_name'] . ' (' . (string)$backOpt['code'] . ')') : '' ?>">
          <button class="btn btn-outline-secondary" type="button" id="back_color_clear" title="Șterge">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <?php if (isset($errors['back_color_id'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['back_color_id']) ?></div><?php endif; ?>
        <div class="app-ac-list" id="back_color_list" style="display:none"></div>
      </div>
      <div class="form-text">Dacă lași gol, se consideră „Aceeași față/verso”.</div>
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
  .app-ac-list{
    position:fixed;
    z-index: 2000;
    background: #fff;
    border: 1px solid #D9E3E6;
    border-radius: 14px;
    box-shadow: 0 10px 24px rgba(17,17,17,0.08);
    max-height: 320px;
    overflow: auto;
  }
  .app-ac-item{
    display:flex;
    align-items:center;
    gap:10px;
    padding:10px 12px;
    cursor:pointer;
    border-bottom: 1px solid #EEF3F5;
  }
  .app-ac-item:last-child{border-bottom:0}
  .app-ac-item:hover, .app-ac-item.active{background:#F3F7F8}
  .app-ac-thumb{
    width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;flex:0 0 auto;
  }
  .app-ac-text{font-weight:600;color:#111}
  .app-ac-sub{font-size:.85rem;color:#5F6B72}
</style>
<script>
  $(function(){
    const finishesEndpoint = <?= json_encode(Url::to('/api/finishes/search')) ?>;

    function debounce(fn, ms){
      let t = null;
      return function(){
        const args = arguments;
        if (t) window.clearTimeout(t);
        t = window.setTimeout(function(){ fn.apply(null, args); }, ms);
      };
    }

    function bindColorAutocomplete(opts){
      const $q = $(opts.q);
      const $id = $(opts.id);
      const $thumb = $(opts.thumb);
      const $list = $(opts.list);
      const allowEmpty = !!opts.allowEmpty;
      let items = [];
      let active = -1;

      // Move dropdown to <body> to avoid overflow clipping
      if (!$list.data('acInBody')) {
        $list.appendTo(document.body);
        $list.data('acInBody', true);
      }

      function place(){
        const el = $q.get(0);
        if (!el) return;
        const r = el.getBoundingClientRect();
        $list.css({
          top: (r.bottom + 6) + 'px',
          left: r.left + 'px',
          width: r.width + 'px'
        });
      }

      function hide(){ $list.hide().empty(); active = -1; items = []; $(window).off('scroll.ac resize.ac', place); }
      function show(){ if ($list.children().length) { place(); $list.show(); $(window).on('scroll.ac resize.ac', place); } }

      function setSelected(it){
        $id.val(String(it.id || ''));
        $q.val(String(it.text || ''));
        if (it.thumb) {
          $thumb.attr('src', it.thumb).show();
        } else {
          $thumb.hide();
        }
        hide();
      }

      function clearSelected(){
        $id.val('');
        $q.val('');
        $thumb.hide();
        hide();
      }

      function render(resItems){
        items = resItems || [];
        $list.empty();
        if (!items.length) {
          $list.append($('<div class="app-ac-item"></div>').append($('<div class="text-muted small"></div>').text('Nimic găsit.')));
          show();
          return;
        }
        items.forEach(function(it, idx){
          const $row = $('<div class="app-ac-item"></div>');
          $row.attr('data-idx', String(idx));
          if (it.thumb) $row.append($('<img class="app-ac-thumb" />').attr('src', it.thumb));
          const $txt = $('<div style="min-width:0"></div>');
          $txt.append($('<div class="app-ac-text"></div>').text(it.text || ''));
          $row.append($txt);
          $row.on('mousedown', function(e){
            // mousedown ca să nu se închidă la blur înainte de click
            e.preventDefault();
            setSelected(it);
          });
          $list.append($row);
        });
        show();
      }

      const doSearch = debounce(function(){
        const q = String($q.val() || '').trim();
        if (q.length < 1) { if (allowEmpty) hide(); else hide(); return; }
        // Show immediate feedback while searching
        $list.empty().append($('<div class="app-ac-item"></div>').append($('<div class="text-muted small"></div>').text('Se caută…')));
        show();
        $.getJSON(finishesEndpoint, { q: q })
          .done(function(res){
            if (!res || res.ok !== true) { render([]); return; }
            render(res.items || []);
          })
          .fail(function(){ render([]); });
      }, 200);

      $q.on('input', function(){
        // dacă userul scrie, invalidează selecția anterioară
        $id.val('');
        $thumb.hide();
        doSearch();
      });

      $q.on('focus', function(){
        const q = String($q.val() || '').trim();
        if (q.length >= 1) doSearch();
      });

      $q.on('blur', function(){
        window.setTimeout(function(){ hide(); }, 150);
      });

      $q.on('keydown', function(e){
        if (!$list.is(':visible')) return;
        const max = items.length - 1;
        if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(max, active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(0, active - 1); }
        else if (e.key === 'Escape') { e.preventDefault(); hide(); return; }
        else if (e.key === 'Enter') {
          if (active >= 0 && items[active]) { e.preventDefault(); setSelected(items[active]); }
          return;
        } else {
          return;
        }
        $list.children('.app-ac-item').removeClass('active');
        const $a = $list.children('.app-ac-item[data-idx="' + active + '"]');
        $a.addClass('active');
        show();
      });

      if (opts.clearBtn) {
        $(opts.clearBtn).on('click', function(){
          clearSelected();
          if (opts.onClear) opts.onClear();
          $q.focus();
        });
      }
    }

    // Texturi rămân Select2
    $('#face_texture_id').select2({ width: '100%' });
    $('#back_texture_id').select2({ width: '100%' });

    bindColorAutocomplete({
      q: '#face_color_q',
      id: '#face_color_id',
      thumb: '#face_color_thumb',
      list: '#face_color_list',
      allowEmpty: false
    });

    bindColorAutocomplete({
      q: '#back_color_q',
      id: '#back_color_id',
      thumb: '#back_color_thumb',
      list: '#back_color_list',
      allowEmpty: true,
      clearBtn: '#back_color_clear',
      onClear: function(){
        // dacă se golește culoarea verso, golește și textura verso
        $('#back_texture_id').val('').trigger('change');
      }
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

