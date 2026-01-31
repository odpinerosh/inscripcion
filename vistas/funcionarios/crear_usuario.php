<?php
require_once __DIR__ . "/../../config/session_funcionarios.php";

if (empty($_SESSION["FUNC_USER"]["usuario"]) || $_SESSION["FUNC_USER"]["usuario"] !== "admin") {
  header("Location: /inscripciones/vistas/funcionarios/index.php");
  exit;
}

$titulo = "Crear usuario (Funcionarios)";

$ok = $_GET['ok'] ?? '';
$e  = $_GET['e'] ?? '';

$msgOk = ($ok === '1') ? "Usuario creado correctamente." : "";
$msgEr = "";
if ($e === '1') $msgEr = "Debes diligenciar todos los campos.";
if ($e === '2') $msgEr = "Las contraseñas no coinciden.";
if ($e === '3') $msgEr = "El usuario ya existe.";
if ($e === '4') $msgEr = "No fue posible crear el usuario. Intenta nuevamente.";

$contenido = '
  <div class="card">
    <h3 style="margin:0 0 10px 0;">Crear usuario</h3>
    <div class="muted">Solo para administración del módulo interno.</div>
  </div>
';

if ($msgOk !== '') {
  $contenido .= '<div class="card" style="border-left:6px solid #2e7d32;"><b>✅ '.$msgOk.'</b></div>';
}
if ($msgEr !== '') {
  $contenido .= '<div class="card" style="border-left:6px solid #b00020;"><b>⛔ '.$msgEr.'</b></div>';
}

$contenido .= '
  <div class="card">
    <form method="POST" action="/inscripciones/controladores/funcionarios_Controller.php?accion=crear_usuario" autocomplete="off">
      <div style="margin-bottom:10px;">
        <label><b>Usuario</b></label><br>
        <input type="text" name="usuario" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
      </div>

      <div style="margin-bottom:10px;">
        <label><b>Nombre</b></label><br>
        <input type="text" name="nombre" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
      </div>

      <div style="margin-bottom:10px;">
        <label><b>Contraseña</b></label><br>
        <input type="password" name="password" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
      </div>

      <div style="margin-bottom:14px;">
        <label><b>Confirmar contraseña</b></label><br>
        <input type="password" name="password2" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button class="btn" type="submit">Crear usuario</button>
        <a class="btn" style="background:#6c757d;" href="/inscripciones/vistas/funcionarios/index.php">Volver</a>
      </div>
    </form>
  </div>
';

require __DIR__ . "/plantilla.php";
