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

  // Lightbox: click on elements with data-lightbox-src
  document.addEventListener('click', function (e) {
    const el = e.target.closest('[data-lightbox-src]');
    if (!el) return;
    const src = el.getAttribute('data-lightbox-src');
    if (!src) return;
    const title = el.getAttribute('data-lightbox-title') || 'Imagine';
    const img = document.getElementById('appLightboxImg');
    const ttl = document.getElementById('appLightboxTitle');
    const modalEl = document.getElementById('appLightbox');
    if (!img || !ttl || !modalEl || !window.bootstrap) return;

    e.preventDefault();
    img.src = src;
    img.alt = title;
    ttl.textContent = title;
    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
})();

