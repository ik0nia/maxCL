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
    <div class="card app-card p-3" id="texturi">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
          <div class="h5 m-0">Texturi</div>
          <div class="text-muted">Adaugă/editează aici (fără poze)</div>
        </div>
      </div>

      <form class="row g-2 mb-2" method="post" action="<?= htmlspecialchars(Url::to('/hpl/tip-culoare/texturi/create')) ?>">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <div class="col-4">
          <input class="form-control" name="code" placeholder="Cod">
        </div>
        <div class="col-8">
          <input class="form-control" name="name" placeholder="Denumire *" required>
        </div>
        <div class="col-12">
          <button class="btn btn-primary btn-sm w-100" type="submit">
            <i class="bi bi-plus-lg me-1"></i> Adaugă textură
          </button>
        </div>
      </form>

      <table class="table table-hover align-middle mb-0" id="texturesMini">
        <thead>
          <tr>
            <th style="width:120px">Cod</th>
            <th>Denumire</th>
            <th class="text-end" style="width:140px">Acțiuni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($textures as $t): ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars((string)($t['code'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string)$t['name']) ?></td>
              <td class="text-end">
                <button type="button"
                        class="btn btn-outline-secondary btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#editTextureModal"
                        data-id="<?= (int)$t['id'] ?>"
                        data-code="<?= htmlspecialchars((string)($t['code'] ?? ''), ENT_QUOTES) ?>"
                        data-name="<?= htmlspecialchars((string)$t['name'], ENT_QUOTES) ?>">
                  <i class="bi bi-pencil me-1"></i> Editează
                </button>
                <form method="post" action="<?= htmlspecialchars(Url::to('/hpl/tip-culoare/texturi/' . (int)$t['id'] . '/delete')) ?>" class="d-inline"
                      onsubmit="return confirm('Sigur vrei să ștergi această textură?');">
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
</div>

<div class="modal fade" id="editTextureModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header">
        <h5 class="modal-title">Editează textură</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
      </div>
      <form method="post" id="editTextureForm">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
          <div class="mb-2">
            <label class="form-label">Cod</label>
            <input class="form-control" name="code" id="tex_code">
          </div>
          <div>
            <label class="form-label">Denumire *</label>
            <input class="form-control" name="name" id="tex_name" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Renunță</button>
          <button type="submit" class="btn btn-primary">Salvează</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('finishesTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25, order: [[2,'asc']] });
  });

  document.addEventListener('show.bs.modal', function (ev) {
    const modal = ev.target;
    if (!modal || modal.id !== 'editTextureModal') return;
    const btn = ev.relatedTarget;
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const code = btn.getAttribute('data-code') || '';
    const name = btn.getAttribute('data-name') || '';
    document.getElementById('tex_code').value = code;
    document.getElementById('tex_name').value = name;
    document.getElementById('editTextureForm').action = <?= json_encode(Url::to('/hpl/tip-culoare/texturi/')) ?> + id + '/edit';
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

