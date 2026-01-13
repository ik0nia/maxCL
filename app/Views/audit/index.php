<?php
use App\Core\Url;
use App\Core\View;

$filters = $filters ?? [];
$users = $users ?? [];
$actions = $actions ?? [];

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Jurnal activitate</h1>
    <div class="text-muted">Login / creare / modificare / ștergere (audit complet)</div>
  </div>
</div>

<div class="card app-card p-3 mb-3">
  <form class="row g-2 align-items-end" method="get" action="<?= htmlspecialchars(Url::to('/audit')) ?>">
    <div class="col-12 col-md-4">
      <label class="form-label">Utilizator</label>
      <select class="form-select" name="user_id" id="audit_user_id">
        <option value="">Toți</option>
        <?php foreach ($users as $u): ?>
          <?php $sel = ((string)$u['id'] === (string)($filters['user_id'] ?? '')) ? 'selected' : ''; ?>
          <option value="<?= (int)$u['id'] ?>" <?= $sel ?>><?= htmlspecialchars((string)$u['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">Acțiune</label>
      <select class="form-select" name="action" id="audit_action">
        <option value="">Toate</option>
        <?php foreach ($actions as $a): ?>
          <?php $sel = ((string)$a === (string)($filters['action'] ?? '')) ? 'selected' : ''; ?>
          <option value="<?= htmlspecialchars((string)$a) ?>" <?= $sel ?>><?= htmlspecialchars((string)$a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">De la</label>
      <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars((string)($filters['date_from'] ?? '')) ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Până la</label>
      <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars((string)($filters['date_to'] ?? '')) ?>">
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i> Filtrează</button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(Url::to('/audit')) ?>">Resetează</a>
    </div>
  </form>
</div>

<div class="card app-card p-3">
  <table class="table table-hover align-middle mb-0" id="auditTable">
    <thead>
      <tr>
        <th style="width:160px">Data</th>
        <th>Utilizator</th>
        <th>Acțiune</th>
        <th>Entitate</th>
        <th style="width:110px">IP</th>
        <th class="text-end" style="width:140px">Detalii</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td class="text-muted"><?= htmlspecialchars((string)$r['created_at']) ?></td>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars((string)($r['user_name'] ?? 'Sistem')) ?></div>
            <div class="text-muted small"><?= htmlspecialchars((string)($r['user_email'] ?? '—')) ?></div>
          </td>
          <td><span class="badge app-badge"><?= htmlspecialchars((string)$r['action']) ?></span></td>
          <td class="text-muted"><?= htmlspecialchars((string)($r['entity_type'] ?? '—')) ?><?= $r['entity_id'] ? ' #' . htmlspecialchars((string)$r['entity_id']) : '' ?></td>
          <td class="text-muted"><?= htmlspecialchars((string)($r['ip'] ?? '—')) ?></td>
          <td class="text-end">
            <button class="btn btn-outline-secondary btn-sm auditDetailsBtn" type="button" data-id="<?= (int)$r['id'] ?>" data-bs-toggle="modal" data-bs-target="#auditDetailsModal">
              <i class="bi bi-eye me-1"></i> Vezi
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="auditDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header">
        <h5 class="modal-title">Detalii audit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12 col-lg-6">
            <div class="fw-semibold mb-1">Înainte</div>
            <pre class="p-3 border bg-light" style="border-radius:14px;white-space:pre-wrap" id="auditBefore">—</pre>
          </div>
          <div class="col-12 col-lg-6">
            <div class="fw-semibold mb-1">După</div>
            <pre class="p-3 border bg-light" style="border-radius:14px;white-space:pre-wrap" id="auditAfter">—</pre>
          </div>
          <div class="col-12">
            <div class="fw-semibold mb-1">Meta</div>
            <pre class="p-3 border bg-light" style="border-radius:14px;white-space:pre-wrap" id="auditMeta">—</pre>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Închide</button>
      </div>
    </div>
  </div>
</div>

<script>
  $(function(){
    $('#audit_user_id').select2({ width: '100%' });
    $('#audit_action').select2({ width: '100%' });

    const el = document.getElementById('auditTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25, order: [[0,'desc']] });

    $('#auditDetailsModal').on('show.bs.modal', async function (ev) {
      const btn = ev.relatedTarget;
      if (!btn) return;
      const id = btn.getAttribute('data-id');
      $('#auditBefore').text('Se încarcă...');
      $('#auditAfter').text('Se încarcă...');
      $('#auditMeta').text('Se încarcă...');
      try{
        const res = await fetch(<?= json_encode(Url::to('/api/audit/')) ?> + id, { headers: { 'Accept': 'application/json' }});
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || 'Eroare.');
        const d = json.data;
        const pretty = (s) => {
          if (!s) return '—';
          try { return JSON.stringify(JSON.parse(s), null, 2); } catch(e){ return String(s); }
        };
        $('#auditBefore').text(pretty(d.before_json));
        $('#auditAfter').text(pretty(d.after_json));
        $('#auditMeta').text(pretty(d.meta_json));
      } catch(e){
        $('#auditBefore').text('Eroare la încărcare.');
        $('#auditAfter').text('Eroare la încărcare.');
        $('#auditMeta').text(String(e));
      }
    });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

