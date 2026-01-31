<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Si ya está logueado, lo mandamos al módulo
if (!empty($_SESSION['FUNC_USER']['usuario'])) {
  header("Location: /inscripciones/vistas/funcionarios/index.php");
  exit;
}

// Manejo de mensajes de error
$e = $_GET['e'] ?? '';
$mensaje = '';
if ($e === '1') $mensaje = 'Usuario o contraseña inválidos.';
if ($e === '2') $mensaje = 'Usuario bloqueado temporalmente por intentos fallidos. Intenta más tarde.';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ingreso Funcionarios - Inscripciones</title>
  <link href="/inscripciones/images/favicon_cooptraiss.png" rel="shortcut icon" type="image/vnd.microsoft.icon">

  <!-- Bootstrap (si está disponible en tu proyecto) -->
  <link rel="stylesheet" href="/inscripciones/assets/vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">

  <style>
    :root{
      --brand:#0b2a4a;
      --brand-2:#08a750;
      --bg:#f5f6f8;
    }
    body{ background:var(--bg); }
    .top-strip{ height: 10px; background: var(--brand-2); }
    .btn-brand{
      background: var(--brand);
      border-color: var(--brand);
      color:#fff;
      border-radius: .75rem;
      padding: .65rem 1rem;
    }
    .btn-brand:hover{ filter: brightness(.95); color:#fff; }
    .card{ border-radius: 1rem; box-shadow: 0 2px 12px rgba(0,0,0,.08); border:1px solid rgba(15,23,42,.08); }
    .muted{ color:#6b7280; font-size:.875rem; }
    .brand-title{ font-weight: 800; color: var(--brand); }
  </style>
</head>
<body>
  <div class="top-strip"></div>

  <div class="container py-4 py-md-5">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-7 col-lg-5">
        <div class="card">
          <div class="card-body p-4">
            <div class="mb-3">
              <div class="brand-title h4 mb-1">Ingreso Funcionarios</div>
              <div class="muted">Uso interno - Funcionarios Cooptraiss</div>
            </div>

            <?php if ($mensaje !== ''): ?>
              <div class="alert alert-danger py-2" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
              </div>
            <?php endif; ?>

            <form method="POST" action="/inscripciones/controladores/funcionarios_Controller.php?accion=login" autocomplete="off">
              <div class="mb-3">
                <label class="form-label">Usuario</label>
                <input class="form-control" type="text" name="usuario" required autofocus>
              </div>

              <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <input class="form-control" type="password" name="password" required>
              </div>

              <div class="d-grid">
                <button class="btn btn-brand" type="submit">Ingresar</button>
              </div>
            </form>

            <hr class="my-4">
            <div class="muted">
              Si tienes inconvenientes con el acceso, valida con el administrador del módulo.
            </div>
          </div>
        </div>
        <div class="text-center mt-3 muted">
          &copy; <?php echo date('Y'); ?> Cooptraiss
        </div>
      </div>
    </div>
  </div>

  <script src="/inscripciones/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
