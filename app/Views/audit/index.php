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
        <th>Descriere</th>
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
          <td>
            <?php
              $msg = null;
              if (!empty($r['meta_json'])) {
                $decoded = json_decode((string)$r['meta_json'], true);
                if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
                  $msg = $decoded['message'];
                }
              }
              // Fallback pentru log-uri vechi (ex: STOCK_PIECE_DELETE fără Placă:)
              if ($msg && str_starts_with($msg, 'A șters piesă') && !str_contains($msg, 'Placă:') && !empty($r['board_code'])) {
                $msg .= ' · Placă: ' . (string)$r['board_code'] . ' · ' . (string)($r['board_name'] ?? '') . ' · ' . (string)($r['board_brand'] ?? '') .
                        ' · ' . (int)($r['board_thickness_mm'] ?? 0) . 'mm · ' . (int)($r['board_std_height_mm'] ?? 0) . '×' . (int)($r['board_std_width_mm'] ?? 0);
              }
              if (!$msg) {
                $msg = '—';
              }
            ?>
            <div class="fw-semibold"><?= htmlspecialchars($msg) ?></div>
          </td>
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
        <div class="card border-0 mb-3" style="background:#F3F7F8;border-radius:14px">
          <div class="p-3">
            <div class="row g-2">
              <div class="col-12 col-lg-6">
                <div class="text-muted small">Acțiune</div>
                <div class="fw-semibold" id="auditHdrAction">—</div>
              </div>
              <div class="col-12 col-lg-6">
                <div class="text-muted small">Entitate</div>
                <div class="fw-semibold" id="auditHdrEntity">—</div>
              </div>
              <div class="col-12">
                <div class="text-muted small">Descriere</div>
                <div class="fw-semibold" id="auditHdrMessage">—</div>
              </div>
              <div class="col-12 col-lg-6">
                <div class="text-muted small">Data</div>
                <div class="fw-semibold" id="auditHdrDate">—</div>
              </div>
              <div class="col-12 col-lg-6">
                <div class="text-muted small">IP / User-Agent</div>
                <div class="fw-semibold" id="auditHdrIpUa">—</div>
              </div>
            </div>
          </div>
        </div>

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
  // IMPORTANT: acest script rulează înainte de footer, unde sunt încărcate jQuery/Select2.
  // Folosim vanilla JS pentru modal ca să fie sigur că se populează.
  document.addEventListener('DOMContentLoaded', function () {
    // Select2 (opțional)
    const $ = window.jQuery;
    if ($ && $.fn && $.fn.select2) {
      $('#audit_user_id').select2({ width: '100%' });
      $('#audit_action').select2({ width: '100%' });
    }

    // DataTables (vanilla)
    const el = document.getElementById('auditTable');
    if (el && window.DataTable) new DataTable(el, { pageLength: 25, order: [[0,'desc']] });

    // Fallback: Bootstrap poate trimite relatedTarget=null în anumite cazuri.
    window.__LAST_AUDIT_ID__ = null;
    document.addEventListener('click', function (e) {
      const btn = e.target && e.target.closest ? e.target.closest('.auditDetailsBtn') : null;
      if (!btn) return;
      window.__LAST_AUDIT_ID__ = btn.getAttribute('data-id');
    });

    const modalEl = document.getElementById('auditDetailsModal');
    if (!modalEl) return;

    function setText(id, v) {
      const el = document.getElementById(id);
      if (el) el.textContent = v;
    }
    function pretty(v) {
      if (v === null || v === undefined || v === '') return '—';
      if (typeof v === 'object') return JSON.stringify(v, null, 2);
      try { return JSON.stringify(JSON.parse(v), null, 2); } catch (e) { return String(v); }
    }

    modalEl.addEventListener('show.bs.modal', async function (ev) {
      const btn = ev.relatedTarget;
      const id = (btn && btn.getAttribute) ? btn.getAttribute('data-id') : (window.__LAST_AUDIT_ID__ || null);
      if (!id) return;

      setText('auditBefore', 'Se încarcă...');
      setText('auditAfter', 'Se încarcă...');
      setText('auditMeta', 'Se încarcă...');
      setText('auditHdrAction', 'Se încarcă...');
      setText('auditHdrEntity', 'Se încarcă...');
      setText('auditHdrMessage', 'Se încarcă...');
      setText('auditHdrDate', 'Se încarcă...');
      setText('auditHdrIpUa', 'Se încarcă...');

      try {
        const res = await fetch(<?= json_encode(Url::to('/api/audit/')) ?> + id, { headers: { 'Accept': 'application/json' }});
        const json = await res.json();
        if (!json || json.ok !== true) throw new Error((json && json.error) ? json.error : 'Eroare.');
        const d = json.data || {};

        setText('auditBefore', pretty(d.before_json));
        setText('auditAfter', pretty(d.after_json));
        setText('auditMeta', pretty(d.meta_json));
        setText('auditHdrAction', d.action || '—');
        setText('auditHdrEntity', (d.entity_type ? d.entity_type : '—') + (d.entity_id ? (' #' + d.entity_id) : ''));
        setText('auditHdrMessage', d.message || '—');
        setText('auditHdrDate', d.created_at || '—');
        setText('auditHdrIpUa', (d.ip || '—') + (d.user_agent ? (' · ' + d.user_agent) : ''));
      } catch (e) {
        setText('auditBefore', 'Eroare la încărcare.');
        setText('auditAfter', 'Eroare la încărcare.');
        setText('auditMeta', String(e));
        setText('auditHdrAction', 'Eroare');
        setText('auditHdrEntity', '—');
        setText('auditHdrMessage', '—');
        setText('auditHdrDate', '—');
        setText('auditHdrIpUa', '—');
      }
    });
  });
</script>
<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));

