/* global toastr */
(function () {
  // Sidebar (mobile)
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('sidebarToggle');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });
  }

  // Toastr defaults (RO)
  if (typeof toastr !== 'undefined') {
    toastr.options = {
      positionClass: 'toast-bottom-right',
      timeOut: 3500,
      closeButton: true,
      progressBar: true
    };
    if (window.__APP_TOAST_SUCCESS__) toastr.success(window.__APP_TOAST_SUCCESS__);
    if (window.__APP_TOAST_ERROR__) toastr.error(window.__APP_TOAST_ERROR__);
  }

  // DataTables default language (RO minimal)
  if (window.DataTable) {
    DataTable.defaults = Object.assign({}, DataTable.defaults, {
      language: {
        search: 'Caută:',
        lengthMenu: 'Afișează _MENU_',
        info: 'Afișez _START_–_END_ din _TOTAL_',
        infoEmpty: 'Nicio înregistrare',
        zeroRecords: 'Nimic găsit',
        paginate: { first: 'Prima', last: 'Ultima', next: 'Următoarea', previous: 'Înapoi' }
      }
    });
  }

  // Bootstrap tooltips (global)
  try {
    if (window.bootstrap && window.bootstrap.Tooltip) {
      const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.forEach(function (el) {
        // container: body => evită clipping în carduri/tabele cu overflow
        window.bootstrap.Tooltip.getOrCreateInstance(el, { container: 'body' });
      });
    }
  } catch (e) {
    // ignore
  }

  // Notes modal (click on .js-piece-notes)
  function ensureNotesModal () {
    let modalEl = document.getElementById('appNotesModal');
    if (modalEl) return modalEl;
    if (!window.bootstrap || !window.bootstrap.Modal) return null;
    const wrap = document.createElement('div');
    wrap.innerHTML = [
      '<div class="modal fade" id="appNotesModal" tabindex="-1" aria-hidden="true">',
      '  <div class="modal-dialog modal-dialog-centered">',
      '    <div class="modal-content" style="border-radius:14px">',
      '      <div class="modal-header">',
      '        <h5 class="modal-title" id="appNotesModalTitle">Notiță</h5>',
      '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>',
      '      </div>',
      '      <div class="modal-body">',
      '        <div id="appNotesModalBody" style="white-space:pre-wrap"></div>',
      '      </div>',
      '    </div>',
      '  </div>',
      '</div>'
    ].join('\n');
    document.body.appendChild(wrap.firstChild);
    modalEl = document.getElementById('appNotesModal');
    return modalEl;
  }

  document.addEventListener('click', function (e) {
    const el = e.target && e.target.closest ? e.target.closest('.js-piece-notes') : null;
    if (!el) return;
    const notes = el.getAttribute('data-notes') || el.getAttribute('title') || '';
    if (!notes) return;
    e.preventDefault();
    e.stopPropagation();

    // Hide tooltip if open
    try {
      if (window.bootstrap && window.bootstrap.Tooltip) {
        const inst = window.bootstrap.Tooltip.getInstance(el);
        if (inst) inst.hide();
      }
    } catch (err) {}

    const modalEl = ensureNotesModal();
    if (!modalEl) return;
    const body = document.getElementById('appNotesModalBody');
    const ttl = document.getElementById('appNotesModalTitle');
    if (ttl) ttl.textContent = 'Notiță';
    if (body) body.textContent = notes;
    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });

  // Lightbox: click on elements with data-lightbox-src
  function ensureLightbox() {
    let modalEl = document.getElementById('appLightbox');
    if (modalEl) return modalEl;
    // Fallback: create modal dynamically if missing in layout
    const wrap = document.createElement('div');
    wrap.innerHTML = [
      '<div class="modal fade" id="appLightbox" tabindex="-1" aria-hidden="true">',
      '  <div class="modal-dialog modal-dialog-centered modal-xl">',
      '    <div class="modal-content" style="border-radius:14px">',
      '      <div class="modal-header">',
      '        <h5 class="modal-title" id="appLightboxTitle">Imagine</h5>',
      '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>',
      '      </div>',
      '      <div class="modal-body p-2">',
      '        <img id="appLightboxImg" src="" alt="" style="width:100%;height:auto;border-radius:12px;">',
      '      </div>',
      '    </div>',
      '  </div>',
      '</div>'
    ].join('\n');
    document.body.appendChild(wrap.firstChild);
    modalEl = document.getElementById('appLightbox');
    return modalEl;
  }

  function openLightbox(src, title) {
    // If Bootstrap JS is missing, at least open in new tab
    if (!window.bootstrap || !window.bootstrap.Modal) {
      window.open(src, '_blank', 'noopener');
      return;
    }
    const modalEl = ensureLightbox();
    const img = document.getElementById('appLightboxImg');
    const ttl = document.getElementById('appLightboxTitle');
    if (!modalEl || !img || !ttl) {
      window.open(src, '_blank', 'noopener');
      return;
    }
    img.src = src;
    img.alt = title || 'Imagine';
    ttl.textContent = title || 'Imagine';
    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }

  // Bootstrap-native: when modal opens, read src/title from the clicked element
  document.addEventListener('show.bs.modal', function (ev) {
    const modal = ev.target;
    if (!modal || modal.id !== 'appLightbox') return;
    const trigger = ev.relatedTarget;
    if (!trigger || !trigger.getAttribute) return;
    const src = trigger.getAttribute('data-lightbox-src') || trigger.getAttribute('data-src');
    if (!src) return;
    const title = trigger.getAttribute('data-lightbox-title') || trigger.getAttribute('data-title') || 'Imagine';
    const fallback = trigger.getAttribute('data-lightbox-fallback') || '';

    const img = document.getElementById('appLightboxImg');
    const ttl = document.getElementById('appLightboxTitle');
    const err = document.getElementById('appLightboxError');
    const link = document.getElementById('appLightboxLink');
    if (img) {
      if (err) err.style.display = 'none';
      img.style.display = '';
      if (link) link.href = src;

      // Fallback dacă imaginea mare nu se încarcă (ex: URL vechi fără /public)
      img.onerror = function () {
        if (fallback && img.src !== fallback) {
          img.src = fallback;
          if (link) link.href = fallback;
          return;
        }
        // dacă și fallback-ul eșuează, afișează mesaj
        if (err) err.style.display = 'block';
        img.style.display = 'none';
      };
      img.onload = function () {
        if (err) err.style.display = 'none';
        img.style.display = '';
      };
      img.src = src;
      img.alt = title;
    }
    if (ttl) ttl.textContent = title;
  });

  // Native delegation
  document.addEventListener('click', function (e) {
    const el = e.target && e.target.closest ? e.target.closest('[data-lightbox-src]') : null;
    if (!el) return;
    const src = el.getAttribute('data-lightbox-src');
    if (!src) return;
    const title = el.getAttribute('data-lightbox-title') || 'Imagine';
    e.preventDefault();
    openLightbox(src, title);
  });

  // jQuery delegation (more robust with DataTables)
  if (window.jQuery) {
    window.jQuery(document).on('click', '[data-lightbox-src]', function (e) {
      const src = this.getAttribute('data-lightbox-src');
      if (!src) return;
      const title = this.getAttribute('data-lightbox-title') || 'Imagine';
      e.preventDefault();
      openLightbox(src, title);
    });
  }
})();

