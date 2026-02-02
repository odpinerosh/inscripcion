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
  <div class="card" style="margin-bottom:10px;">
    <h3 style="margin:0 0 10px 0;">Crear usuario</h3>
    <div class="muted">Solo para administración del módulo interno.</div>
  </div>
';

if ($msgOk !== '') {
  $contenido .= '<div class="card" style="margin-bottom:10px; border-left:6px solid #2e7d32;"><b>✅ '.$msgOk.'</b></div>';
}
if ($msgEr !== '') {
  $contenido .= '<div class="card" style="margin-bottom:10px; border-left:6px solid #b00020;"><b>⛔ '.$msgEr.'</b></div>';
}

if (!empty($_SESSION['IMPORT_RES'])) {
  $r = $_SESSION['IMPORT_RES'];
  unset($_SESSION['IMPORT_RES']);

  $contenido .= "<div class='card' style='border-left:6px solid #0ea5e9; margin-bottom:10px;'>
    <b>Resultado importación</b><br>
    Creados: <b>{$r['creados']}</b> · Existentes: <b>{$r['existentes']}</b> · Inválidos: <b>{$r['invalidos']}</b>
  </div>";

  if (!empty($r['generadas'])) {
      $filas = '';
      foreach ($r['generadas'] as $usuario => $clave) {
          $filas .= "<tr>
              <td style='padding:8px 12px; border-bottom:1px solid #e5e7eb; font-weight:500;'>"
                  . htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8') .
              "</td>
              <td style='padding:8px 12px; border-bottom:1px solid #e5e7eb; font-family:monospace;'>"
                  . htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') .
              "</td>
          </tr>";
      }

      $contenido .= "<div class='card' style='margin-bottom:10px;'>
          <b>Se generaron contraseñas</b>
          <div class='muted'>Copiar y entregar a cada funcionario.</div>
          <table style='width:100%; border-collapse:collapse; margin-top:10px; font-size:0.9rem;'>
              <thead>
                  <tr style='background:#f3f4f6; color:#6b7280; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;'>
                      <th style='padding:6px 12px; text-align:left; border-bottom:2px solid #e5e7eb;'>Usuario</th>
                      <th style='padding:6px 12px; text-align:left; border-bottom:2px solid #e5e7eb;'>Contraseña</th>
                  </tr>
              </thead>
              <tbody>$filas</tbody>
          </table>
      </div>";
  }
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
        <a class="btn" style="background:#6b7280; color:#fff;" href="/inscripciones/vistas/funcionarios/index.php">Volver</a>
      </div>
    </form>
  </div>
  <div class="card" style="margin-top:40px;">
    <h3 style="margin:0 0 10px 0;">Lista de usuarios (CSV)</h3>
    <div class="muted" style="margin-bottom:10px;">
      Un usuario por línea (separador , ): <b>usuario,nombre,password</b><br>
      Si <b>password</b> se deja vacío, el sistema genera una clave.
    </div>

    <form method="POST" action="/inscripciones/controladores/funcionarios_Controller.php?accion=importar_usuarios" autocomplete="off">
      <textarea name="csv" rows="10" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;"
        placeholder="ID,NOMBRES,Clave123">        
      </textarea>

      <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
        <button class="btn" type="submit">Importar usuarios</button>
        <a class="btn" style="background:#6b7280; color:#fff;" href="/inscripciones/vistas/funcionarios/index.php">Volver</a>
      </div>
    </form>
  </div>
';

require __DIR__ . "/plantilla.php";
