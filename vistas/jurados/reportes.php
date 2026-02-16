<?php
session_start();

// anti-cache fuerte
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (empty($_SESSION['JUR_USER'])) {
  header('Location: /inscripciones/vistas/jurados/login.php?timeout=1');
  exit;
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta http-equiv="Cache-Control" content="no-store" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reportes</title>
</head>
<body>
  <div style="padding:16px;font-family:Arial">
    <h3>Reportes (en construcción)</h3>
    <p>Usuario: <b><?= htmlspecialchars($_SESSION['JUR_USER']) ?></b></p>
    <p><a href="/inscripciones/controladores/jurados_logout.php">Salir</a></p>
  </div>

  <script>
    // evita volver a una página "congelada" del historial
    window.addEventListener('pageshow', (e) => {
      if (e.persisted) window.location.reload();
    });
  </script>
</body>
</html>
