<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$isEdit = ($mode ?? '') === 'edit';
$action = $isEdit ? Url::to('/catalog/variants/' . (int)($row['id'] ?? 0) . '/edit') : Url::to('/catalog/variants/create');
$v = $row ?? [];
$errors = $errors ?? [];
$materials = $materials ?? [];
$finishes = $finishes ?? [];

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0"><?= $isEdit ? 'Editează variantă' : 'Variantă nouă' ?></h1>
    <div class="text-muted">Selectează materialul și finisajele (Select2 cu thumbnails)</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/catalog/variants')) ?>" class="btn btn-outline-secondary">Înapoi</a>
  </div>
</div>

<div class="card app-card p-4">
  <form method="post" action="<?= htmlspecialchars($action) ?>" class="row g-3">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="col-12 col-lg-6">
      <label class="form-label">Material *</label>
      <select class="form-select <?= isset($errors['material_id']) ? 'is-invalid' : '' ?>" name="material_id" id="material_id" required>
        <option value="">Alege material...</option>
        <?php foreach ($materials as $m): ?>
          <?php
            $mid = (int)$m['id'];
            $selVal = (string)($v['material_id'] ?? '');
            $selected = ((string)$mid === $selVal) ? 'selected' : '';
            $label = (string)$m['brand'] . ' · ' . (string)$m['name'] . ' · ' . (int)$m['thickness_mm'] . 'mm';
          ?>
          <option value="<?= $mid ?>" <?= $selected ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['material_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['material_id']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-lg-6">
      <div class="alert alert-light border mb-0" style="border-radius:14px">
        <div class="fw-semibold">Tip:</div>
        <div class="text-muted">Dacă la „Verso” lași gol, varianta se consideră „Aceeași față/verso”.</div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <label class="form-label">Finisaj față *</label>
      <select class="form-select <?= isset($errors['finish_face_id']) ? 'is-invalid' : '' ?>" name="finish_face_id" id="finish_face_id" required>
        <option value="">Alege finisaj față...</option>
        <?php foreach ($finishes as $f): ?>
          <?php
            $fid = (int)$f['id'];
            $selVal = (string)($v['finish_face_id'] ?? '');
            $selected = ((string)$fid === $selVal) ? 'selected' : '';
            $label = (string)$f['color_name'] . ' · ' . (string)$f['texture_name'] . ' (' . (string)$f['code'] . ')';
          ?>
          <option value="<?= $fid ?>" data-thumb="<?= htmlspecialchars((string)$f['thumb_path']) ?>" <?= $selected ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['finish_face_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['finish_face_id']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 col-lg-6">
      <label class="form-label">Finisaj verso (opțional)</label>
      <select class="form-select <?= isset($errors['finish_back_id']) ? 'is-invalid' : '' ?>" name="finish_back_id" id="finish_back_id">
        <option value="">Aceeași față/verso</option>
        <?php foreach ($finishes as $f): ?>
          <?php
            $fid = (int)$f['id'];
            $selVal = (string)($v['finish_back_id'] ?? '');
            $selected = ((string)$fid === $selVal) ? 'selected' : '';
            $label = (string)$f['color_name'] . ' · ' . (string)$f['texture_name'] . ' (' . (string)$f['code'] . ')';
          ?>
          <option value="<?= $fid ?>" data-thumb="<?= htmlspecialchars((string)$f['thumb_path']) ?>" <?= $selected ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['finish_back_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['finish_back_id']) ?></div><?php endif; ?>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2 me-1"></i> Salvează
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/catalog/variants')) ?>">Renunță</a>
    </div>
  </form>
</div>

<style>
  .s2-thumb{width:34px;height:34px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;margin-right:10px}
  .s2-row{display:flex;align-items:center}
</style>
<script>
  function fmtFinish(opt){
    if (!opt.id) return opt.text;
    const el = opt.element;
    const thumb = el ? el.getAttribute('data-thumb') : null;
    if (!thumb) return opt.text;
    const $row = $('<span class="s2-row"></span>');
    $row.append($('<img class="s2-thumb" />').attr('src', thumb));
    $row.append($('<span></span>').text(opt.text));
    return $row;
  }
  $(function(){
    $('#material_id').select2({ width: '100%' });
    $('#finish_face_id').select2({ width: '100%', templateResult: fmtFinish, templateSelection: fmtFinish, escapeMarkup: m => m });
    $('#finish_back_id').select2({ width: '100%', templateResult: fmtFinish, templateSelection: fmtFinish, escapeMarkup: m => m });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

