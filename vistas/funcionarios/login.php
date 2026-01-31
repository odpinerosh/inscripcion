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
</head>
<body>
  <h3>Ingreso Funcionarios</h3>

  <?php if ($mensaje !== ''): ?>
    <p style="color:#b00020;"><?php echo htmlspecialchars($mensaje); ?></p>
  <?php endif; ?>

  <form method="POST" action="/inscripciones/controladores/funcionarios_Controller.php?accion=login" autocomplete="off">
    <div>
      <label>Usuario</label><br>
      <input type="text" name="usuario" required>
    </div>
    <br>
    <div>
      <label>Contraseña</label><br>
      <input type="password" name="password" required>
    </div>
    <br>
    <button type="submit">Ingresar</button>
  </form>

  <hr>
  <small>Uso interno - Funcionarios Cooptraiss</small>
</body>
</html>
