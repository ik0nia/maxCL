<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$u = Auth::user();
$canWrite = $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);
$isAdmin = $u && (string)$u['role'] === Auth::ROLE_ADMIN;

$row = $row ?? [];
$projects = $projects ?? [];
$addresses = $addresses ?? [];
$group = $group ?? null;
$groupMembers = $groupMembers ?? [];

$type = (string)($row['type'] ?? '');
$typeLabel = $type === 'FIRMA' ? 'Firmă' : 'Persoană fizică';

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Client</h1>
    <div class="text-muted"><?= htmlspecialchars((string)($row['name'] ?? '')) ?> · <?= htmlspecialchars($typeLabel) ?></div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars(Url::to('/clients')) ?>" class="btn btn-outline-secondary">Înapoi</a>
    <?php if ($canWrite): ?>
      <a href="<?= htmlspecialchars(Url::to('/clients/' . (int)$row['id'] . '/edit')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-pencil me-1"></i> Editează
      </a>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
      <form method="post" action="<?= htmlspecialchars(Url::to('/clients/' . (int)$row['id'] . '/delete')) ?>" class="m-0"
            onsubmit="return confirm('Sigur vrei să ștergi acest client?');">
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
    <div class="card app-card p-3">
      <div class="h5 m-0">Date client</div>
      <div class="text-muted">Informații de contact și livrare</div>

      <div class="mt-3">
        <div class="d-flex justify-content-between border-bottom py-2">
          <div class="text-muted">Tip</div>
          <div class="fw-semibold"><?= htmlspecialchars($typeLabel) ?></div>
        </div>
        <?php if (!empty($row['cui'])): ?>
          <div class="d-flex justify-content-between border-bottom py-2">
            <div class="text-muted">CUI</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)$row['cui']) ?></div>
          </div>
        <?php endif; ?>
        <?php if (!empty($row['contact_person'])): ?>
          <div class="d-flex justify-content-between border-bottom py-2">
            <div class="text-muted">Persoană contact</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)$row['contact_person']) ?></div>
          </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between border-bottom py-2">
          <div class="text-muted">Telefon</div>
          <div class="fw-semibold"><?= htmlspecialchars((string)($row['phone'] ?? '')) ?></div>
        </div>
        <div class="d-flex justify-content-between border-bottom py-2">
          <div class="text-muted">Email</div>
          <div class="fw-semibold"><?= htmlspecialchars((string)($row['email'] ?? '')) ?></div>
        </div>
        <?php if ($group && !empty($group['name'])): ?>
          <div class="d-flex justify-content-between border-bottom py-2">
            <div class="text-muted">Grup firme</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)$group['name']) ?></div>
          </div>
          <?php if ($groupMembers): ?>
            <div class="py-2 border-bottom">
              <div class="text-muted">Alte firme din grup</div>
              <div class="d-flex flex-wrap gap-1 mt-1">
                <?php foreach ($groupMembers as $m): ?>
                  <a class="badge app-badge text-decoration-none" href="<?= htmlspecialchars(Url::to('/clients/' . (int)$m['id'])) ?>">
                    <?= htmlspecialchars((string)$m['name']) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
        <div class="py-2">
          <div class="text-muted">Adresă livrare (principală)</div>
          <div class="fw-semibold mt-1"><?= nl2br(htmlspecialchars((string)($row['address'] ?? ''))) ?></div>
        </div>
      </div>
    </div>

    <div class="card app-card p-3 mt-3">
      <div class="h5 m-0">Adrese de livrare</div>
      <div class="text-muted">Poți avea mai multe adrese pentru același client</div>

      <?php if (!$addresses): ?>
        <div class="text-muted mt-2">Nu există adrese suplimentare încă.</div>
      <?php else: ?>
        <div class="list-group list-group-flush mt-2">
          <?php foreach ($addresses as $a): ?>
            <?php
              $isDef = ((int)($a['is_default'] ?? 0) === 1);
              $lbl = trim((string)($a['label'] ?? ''));
              if ($lbl === '') $lbl = $isDef ? 'Adresă principală' : 'Adresă';
            ?>
            <div class="list-group-item px-0">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-semibold" style="color:#111">
                    <?= htmlspecialchars($lbl) ?>
                    <?php if ($isDef): ?><span class="badge app-badge ms-1">Implicit</span><?php endif; ?>
                  </div>
                  <div class="text-muted" style="white-space:pre-wrap"><?= htmlspecialchars((string)($a['address'] ?? '')) ?></div>
                  <?php if (!empty($a['notes'])): ?>
                    <div class="text-muted small mt-1"><?= nl2br(htmlspecialchars((string)$a['notes'])) ?></div>
                  <?php endif; ?>
                </div>
                <?php if ($canWrite): ?>
                  <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm js-edit-address"
                            type="button"
                            data-id="<?= (int)$a['id'] ?>"
                            data-label="<?= htmlspecialchars((string)($a['label'] ?? ''), ENT_QUOTES) ?>"
                            data-address="<?= htmlspecialchars((string)($a['address'] ?? ''), ENT_QUOTES) ?>"
                            data-notes="<?= htmlspecialchars((string)($a['notes'] ?? ''), ENT_QUOTES) ?>"
                            data-default="<?= $isDef ? '1' : '0' ?>">
                      <i class="bi bi-pencil me-1"></i> Editează
                    </button>
                    <form method="post" action="<?= htmlspecialchars(Url::to('/clients/' . (int)$row['id'] . '/addresses/' . (int)$a['id'] . '/delete')) ?>"
                          class="m-0" onsubmit="return confirm('Sigur vrei să ștergi această adresă?');">
                      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                      <button class="btn btn-outline-secondary btn-sm" type="submit">
                        <i class="bi bi-trash me-1"></i> Șterge
                      </button>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($canWrite): ?>
        <div class="border-top pt-2 mt-2">
          <div class="fw-semibold">Adaugă adresă</div>
          <form class="row g-2 mt-1" method="post" action="<?= htmlspecialchars(Url::to('/clients/' . (int)$row['id'] . '/addresses/create')) ?>">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <div class="col-12 col-md-4">
              <input class="form-control" name="label" placeholder="Etichetă (ex: Sediu / Șantier)">
            </div>
            <div class="col-12 col-md-8">
              <input class="form-control" name="address" placeholder="Adresă (obligatoriu)" required>
            </div>
            <div class="col-12">
              <input class="form-control" name="notes" placeholder="Note (opțional)">
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center">
              <label class="form-check d-flex align-items-center gap-2 m-0">
                <input class="form-check-input" type="checkbox" name="is_default" value="1">
                <span class="form-check-label">Setează ca implicită</span>
              </label>
              <button class="btn btn-outline-secondary" type="submit">
                <i class="bi bi-plus-lg me-1"></i> Adaugă
              </button>
            </div>
          </form>
        </div>

        <!-- Modal edit -->
        <div class="modal fade" id="editAddressModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:14px">
              <div class="modal-header">
                <h5 class="modal-title">Editează adresă</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
              </div>
              <form method="post" id="editAddressForm">
                <div class="modal-body">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                  <div class="mb-2">
                    <label class="form-label small">Etichetă</label>
                    <input class="form-control" name="label" id="ea_label">
                  </div>
                  <div class="mb-2">
                    <label class="form-label small">Adresă *</label>
                    <textarea class="form-control" name="address" id="ea_address" rows="2" required></textarea>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small">Note</label>
                    <textarea class="form-control" name="notes" id="ea_notes" rows="2"></textarea>
                  </div>
                  <label class="form-check d-flex align-items-center gap-2">
                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="ea_default">
                    <span class="form-check-label">Setează ca implicită</span>
                  </label>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Renunță</button>
                  <button type="submit" class="btn btn-primary">Salvează</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card app-card p-3">
      <div class="h5 m-0">Proiecte asociate</div>
      <div class="text-muted">Proiecte care folosesc acest client</div>

      <table class="table table-hover align-middle mb-0 mt-2" id="clientProjectsTable">
        <thead>
          <tr>
            <th>Cod</th>
            <th>Denumire</th>
            <th>Status</th>
            <th class="text-end">Creat</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($projects as $p): ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars((string)($p['code'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string)($p['name'] ?? '')) ?></td>
              <td><span class="badge app-badge"><?= htmlspecialchars((string)($p['status'] ?? '')) ?></span></td>
              <td class="text-end text-muted small"><?= htmlspecialchars((string)($p['created_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if (!$projects): ?>
        <div class="text-muted mt-2">Nu există proiecte asociate încă.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('clientProjectsTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25, order: [[3,'desc']] });

    // Edit address modal
    const btns = Array.from(document.querySelectorAll('.js-edit-address'));
    const form = document.getElementById('editAddressForm');
    const modalEl = document.getElementById('editAddressModal');
    const modal = (modalEl && window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    function openEdit(btn){
      if (!btn || !form) return;
      const id = btn.getAttribute('data-id') || '';
      if (!id) return;
      form.action = <?= json_encode(Url::to('/clients/' . (int)($row['id'] ?? 0) . '/addresses/'), JSON_UNESCAPED_UNICODE) ?> + id + '/edit';
      const elLabel = document.getElementById('ea_label');
      const elAddr = document.getElementById('ea_address');
      const elNotes = document.getElementById('ea_notes');
      const elDef = document.getElementById('ea_default');
      if (elLabel) elLabel.value = btn.getAttribute('data-label') || '';
      if (elAddr) elAddr.value = btn.getAttribute('data-address') || '';
      if (elNotes) elNotes.value = btn.getAttribute('data-notes') || '';
      if (elDef) elDef.checked = (btn.getAttribute('data-default') === '1');
      if (modal) modal.show();
    }
    btns.forEach(b => b.addEventListener('click', () => openEdit(b)));
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

