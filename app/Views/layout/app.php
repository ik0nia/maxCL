<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;
use App\Models\AppSetting;

$user = Auth::user();
$brandSettings = AppSetting::getMany(['company_name', 'company_logo_thumb_url', 'company_logo_url']);
$brandName = trim((string)($brandSettings['company_name'] ?? ''));
if ($brandName === '') $brandName = 'HPL Manager';
$brandLogo = (string)($brandSettings['company_logo_thumb_url'] ?? $brandSettings['company_logo_url'] ?? '');
$toastSuccess = Session::flash('toast_success');
$toastError = Session::flash('toast_error');
?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars(($title ?? 'Aplicație atelier') . ' · ' . $brandName) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css" rel="stylesheet">

  <link href="<?= htmlspecialchars(Url::asset('/assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="app-body">
  <header class="app-header">
    <div class="container-fluid d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-secondary d-lg-none" id="sidebarToggle" type="button" aria-label="Meniu">
          <i class="bi bi-list"></i>
        </button>
        <div class="app-brand">
          <?php if ($brandLogo !== ''): ?>
            <img src="<?= htmlspecialchars($brandLogo) ?>" alt="<?= htmlspecialchars($brandName) ?>" style="height:34px;width:auto;display:block">
            <span class="visually-hidden"><?= htmlspecialchars($brandName) ?></span>
          <?php else: ?>
            <span class="app-dot"></span>
            <span class="fw-bold"><?= htmlspecialchars($brandName) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex align-items-center gap-2">
        <?php if ($user): ?>
          <div class="text-end me-2 d-none d-md-block">
            <div class="fw-semibold" style="line-height: 1.1"><?= htmlspecialchars((string)$user['name']) ?></div>
            <div class="text-muted small"><?= htmlspecialchars((string)$user['role']) ?></div>
          </div>
          <form method="post" action="<?= htmlspecialchars(Url::to('/logout')) ?>" class="m-0">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <button class="btn btn-outline-secondary" type="submit">
              <i class="bi bi-box-arrow-right me-1"></i> Ieșire
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <div class="app-shell">
    <aside class="app-sidebar" id="sidebar">
      <nav class="app-nav">
        <?php $p = Url::currentPath(); ?>
        <a class="app-nav-link <?= $p === '/' ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/')) ?>">
          <i class="bi bi-grid me-2"></i> Panou
        </a>

        <div class="app-nav-section">Proiecte</div>
        <a class="app-nav-link <?= str_starts_with($p, '/projects') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/projects')) ?>">
          <i class="bi bi-kanban me-2"></i> Proiecte
        </a>
        <a class="app-nav-link <?= str_starts_with($p, '/offers') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/offers')) ?>">
          <i class="bi bi-clipboard-check me-2"></i> Oferte
        </a>
        <a class="app-nav-link <?= str_starts_with($p, '/products') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/products')) ?>">
          <i class="bi bi-box2-heart me-2"></i> Produse
        </a>

        <div class="app-nav-section">Clienți</div>
        <a class="app-nav-link <?= str_starts_with($p, '/clients') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/clients')) ?>">
          <i class="bi bi-people me-2"></i> Clienți
        </a>

        <div class="app-nav-section">Plăci HPL</div>
        <a class="app-nav-link <?= str_starts_with($p, '/hpl/catalog') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/hpl/catalog')) ?>">
          <i class="bi bi-grid-3x3-gap me-2"></i> Catalog
        </a>
        <a class="app-nav-link <?= str_starts_with($p, '/hpl/tip-culoare') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/hpl/tip-culoare')) ?>">
          <i class="bi bi-palette2 me-2"></i> Tip culoare
        </a>
        <a class="app-nav-link <?= str_starts_with($p, '/stock') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/stock')) ?>">
          <i class="bi bi-box-seam me-2"></i> Stoc
        </a>
        <?php
          $canInternal = $user && in_array((string)($user['role'] ?? ''), [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);
        ?>
        <?php if ($canInternal): ?>
          <a class="app-nav-link <?= str_starts_with($p, '/hpl/bucati-rest') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/hpl/bucati-rest')) ?>">
            <i class="bi bi-bounding-box-circles me-2"></i> Bucăți rest
          </a>
          <a class="app-nav-link <?= str_starts_with($p, '/hpl/piese-interne') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/hpl/piese-interne')) ?>">
            <i class="bi bi-scissors me-2"></i> Adăugare plăci mici (nestocabile)
          </a>
        <?php endif; ?>

        <div class="app-nav-section">Magazie</div>
        <a class="app-nav-link <?= str_starts_with($p, '/magazie/stoc') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/magazie/stoc')) ?>">
          <i class="bi bi-boxes me-2"></i> Stoc Magazie
        </a>
        <a class="app-nav-link <?= str_starts_with($p, '/magazie/receptie') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/magazie/receptie')) ?>">
          <i class="bi bi-truck me-2"></i> Recepție marfă
        </a>

        <div class="app-nav-section">Sistem</div>
        <a class="app-nav-link <?= str_starts_with($p, '/audit') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/audit')) ?>">
          <i class="bi bi-journal-text me-2"></i> Jurnal activitate
        </a>
        <?php if ($user && in_array((string)($user['role'] ?? ''), [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true)): ?>
          <a class="app-nav-link <?= str_starts_with($p, '/manual') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/manual')) ?>">
            <i class="bi bi-journal-bookmark me-2"></i> Manual avansat
          </a>
        <?php endif; ?>
        <?php if ($user && in_array((string)($user['role'] ?? ''), [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true)): ?>
          <a class="app-nav-link <?= str_starts_with($p, '/system/consumuri-materiale') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/system/consumuri-materiale')) ?>">
            <i class="bi bi-clipboard-data me-2"></i> Consumuri materiale
          </a>
        <?php endif; ?>
        <a class="app-nav-link <?= str_starts_with($p, '/users') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/users')) ?>">
          <i class="bi bi-person-gear me-2"></i> Utilizatori
        </a>
        <?php if ($user && in_array((string)($user['role'] ?? ''), [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER], true)): ?>
          <a class="app-nav-link <?= str_starts_with($p, '/system/costuri') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/system/costuri')) ?>">
            <i class="bi bi-cash-coin me-2"></i> Setări costuri
          </a>
          <a class="app-nav-link <?= str_starts_with($p, '/system/admin-settings') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/system/admin-settings')) ?>">
            <i class="bi bi-shield-lock me-2"></i> Setări admin
          </a>
        <?php endif; ?>
        <?php if ($user && strtolower((string)($user['email'] ?? '')) === 'sacodrut@ikonia.ro'): ?>
          <a class="app-nav-link <?= str_starts_with($p, '/system/db-update') ? 'active' : '' ?>" href="<?= htmlspecialchars(Url::to('/system/db-update')) ?>">
            <i class="bi bi-database-gear me-2"></i> Update DB
          </a>
        <?php endif; ?>
      </nav>
    </aside>

    <main class="app-main">
      <div class="container-fluid py-3">
        <?= $content ?? '' ?>
      </div>
    </main>
  </div>

  <!-- Lightbox (Bootstrap modal) -->
  <div class="modal fade" id="appLightbox" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
      <div class="modal-content" style="border-radius:14px">
        <div class="modal-header">
          <h5 class="modal-title" id="appLightboxTitle">Imagine</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
        </div>
        <div class="modal-body p-2">
          <div id="appLightboxError" class="p-3 text-muted" style="display:none">
            Nu pot încărca imaginea.
            <a id="appLightboxLink" href="#" target="_blank" rel="noopener">Deschide în tab nou</a>
          </div>
          <img id="appLightboxImg" src="" alt="" style="width:100%;height:auto;border-radius:12px;">
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.js"></script>

  <script src="<?= htmlspecialchars(Url::asset('/assets/js/app.js')) ?>"></script>
  <script>
    window.__APP_TOAST_SUCCESS__ = <?= json_encode($toastSuccess, JSON_UNESCAPED_UNICODE) ?>;
    window.__APP_TOAST_ERROR__ = <?= json_encode($toastError, JSON_UNESCAPED_UNICODE) ?>;
  </script>
</body>
</html>

