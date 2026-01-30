<?php
// /inscripciones/vistas/funcionarios/plantilla.php
if (session_status() === PHP_SESSION_NONE) session_start();

$nombre = $_SESSION['FUNC_USER']['nombre'] ?? '';
$usuario = $_SESSION['FUNC_USER']['usuario'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($titulo ?? 'Módulo Interno'); ?></title>
  <style>
    body{font-family:Arial, sans-serif; margin:0; background:#f5f6f8;}
    header{background:#0b2a4a; color:#fff; padding:12px 16px;}
    main{padding:16px; max-width:900px; margin:0 auto;}
    .card{background:#fff; border-radius:10px; padding:14px; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:12px;}
    .row{display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between;}
    .btn{display:inline-block; padding:10px 12px; border-radius:8px; text-decoration:none; background:#0b2a4a; color:#fff;}
    .muted{color:#6b7280; font-size:12px;}
    .warn{background:#fff7ed; border:1px solid #fed7aa;}
    .warn b{color:#9a3412;}
  </style>
</head>
<body>
  <header>
    <div class="row">
      <div>
        <div style="font-weight:700;">Inscripciones Delegados — Uso interno</div>
        <div class="muted">Funcionario: <?php echo htmlspecialchars($nombre); ?> (<?php echo htmlspecialchars($usuario); ?>)</div>
      </div>
      <div>
        <a class="btn" href="/inscripciones/controladores/funcionarios_Controller.php?accion=logout">Salir</a>
      </div>
    </div>
  </header>

  <main>
    <?php echo $contenido ?? ''; ?>
  </main>
</body>
</html>
