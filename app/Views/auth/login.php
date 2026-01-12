<?php
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;

$toastError = Session::flash('toast_error');
?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Autentificare · HPL Manager</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css" rel="stylesheet">
  <link href="<?= htmlspecialchars(Url::asset('/assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="app-body">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-7 col-lg-5">
        <div class="card app-card p-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="app-dot"></span>
            <div>
              <div class="h4 m-0" style="font-weight:700">Autentificare</div>
              <div class="text-muted">Gestiune stoc HPL · atelier producție</div>
            </div>
          </div>

          <form method="post" action="<?= htmlspecialchars(Url::to('/login')) ?>" class="vstack gap-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <div>
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" placeholder="admin@local" required>
            </div>
            <div>
              <label class="form-label">Parola</label>
              <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button class="btn btn-primary btn-lg" type="submit">Intră în aplicație</button>
            <div class="small text-muted">
              Dacă este prima rulare, mergi la <a href="<?= htmlspecialchars(Url::to('/setup')) ?>">Setup</a> pentru instalare.
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.js"></script>
  <script>
    toastr.options = { positionClass: "toast-bottom-right", timeOut: 3500 };
    const err = <?= json_encode($toastError, JSON_UNESCAPED_UNICODE) ?>;
    if (err) toastr.error(err);
  </script>
</body>
</html>

