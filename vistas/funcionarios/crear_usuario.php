<?php
require_once __DIR__ . "/../../config/session_funcionarios.php";

// Solo admin puede crear usuarios
if (empty($_SESSION['FUNC_USER']['usuario']) || $_SESSION['FUNC_USER']['usuario'] !== 'admin') {
  http_response_code(403);
  echo "Acceso denegado.";
  exit;
}

$e = $_GET['e'] ?? '';
$msg = '';
if ($e === '1') $msg = 'Todos los campos son obligatorios.';
if ($e === '2') $msg = 'El usuario ya existe.';
if ($e === '3') $msg = 'Usuario creado correctamente.';
if ($e === '4') $msg = 'Error al crear el usuario.';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Crear usuario - Funcionarios</title>
</head>
<body>
  <h3>Crear usuario (Funcionarios)</h3>

  <?php if ($msg !== ''): ?>
    <p style="<?php echo ($e === '3') ? 'color:green;' : 'color:#b00020;'; ?>">
      <?php echo htmlspecialchars($msg); ?>
    </p>
  <?php endif; ?>

  <form method="POST" action="/inscripciones/controladores/funcionarios_Controller.php?accion=crear_usuario" autocomplete="off">
    <div>
      <label>Usuario (sin espacios)</label><br>
      <input type="text" name="usuario" required>
    </div><br>

    <div>
      <label>Nombre</label><br>
      <input type="text" name="nombre" required>
    </div><br>

    <div>
      <label>Contraseña</label><br>
      <input type="password" name="password" required>
    </div><br>

    <button type="submit">Crear</button>
  </form>

  <hr>
  <a href="/inscripciones/vistas/funcionarios/index.php">Volver</a>
</body>
</html>
