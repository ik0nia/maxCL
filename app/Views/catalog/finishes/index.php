<?php
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Models\Texture;

ob_start();
$textures = [];
try { $textures = Texture::all(); } catch (\Throwable $e) { $textures = []; }
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Tip culoare</h1>
    <div class="text-muted">Culoare (fără textură). Texturile se gestionează separat.</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/hpl/tip-culoare/create')) ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Tip culoare nou
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-7">
    <div class="card app-card p-3">
      <table class="table table-hover align-middle mb-0" id="finishesTable">
        <thead>
          <tr>
            <th style="width:64px">Poză</th>
            <th>Cod</th>
            <th>Culoare</th>
            <th class="text-end" style="width:180px">Acțiuni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($rows ?? []) as $r): ?>
            <tr>
              <td>
                <img src="<?= htmlspecialchars((string)$r['thumb_path']) ?>" alt="thumb" style="width:42px;height:42px;object-fit:cover;border-radius:10px;border:1px solid #D9E3E6;">
              </td>
              <td class="fw-semibold"><?= htmlspecialchars((string)$r['code']) ?></td>
              <td>
                <?= htmlspecialchars((string)$r['color_name']) ?>
                <?php if (!empty($r['color_code'])): ?>
                  <div class="text-muted small"><?= htmlspecialchars((string)$r['color_code']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(Url::to('/hpl/tip-culoare/' . (int)$r['id'] . '/edit')) ?>">
                  <i class="bi bi-pencil me-1"></i> Editează
                </a>
                <form method="post" action="<?= htmlspecialchars(Url::to('/hpl/tip-culoare/' . (int)$r['id'] . '/delete')) ?>" class="d-inline"
                      onsubmit="return confirm('Sigur vrei să ștergi acest tip de culoare?');">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                  <button class="btn btn-outline-secondary btn-sm" type="submit">
                    <i class="bi bi-trash me-1"></i> Șterge
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-12 col-lg-5">
    <div class="card app-card p-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
          <div class="h5 m-0">Texturi</div>
          <div class="text-muted">Tabel separat (fără poze)</div>
        </div>
        <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(Url::to('/hpl/texturi')) ?>">
          <i class="bi bi-arrow-right me-1"></i> Gestionează
        </a>
      </div>
      <table class="table table-hover align-middle mb-0" id="texturesMini">
        <thead>
          <tr>
            <th style="width:120px">Cod</th>
            <th>Denumire</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($textures as $t): ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars((string)($t['code'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string)$t['name']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('finishesTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25, order: [[2,'asc']] });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

