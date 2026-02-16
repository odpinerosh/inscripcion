<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conecta.php';
$conn = Conectar::conexion();

function respond($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

$usuario = trim($_POST['usuario'] ?? '');
$pass    = (string)($_POST['pass'] ?? '');

if ($usuario === '' || $pass === '') {
  respond(['ok' => false, 'msg' => 'Usuario y contraseña son obligatorios.'], 400);
}

$sql = "SELECT usuario, nombre, pass_hash, activo, rol
        FROM usuarios_funcionarios
        WHERE usuario = ?
        LIMIT 1";

$st = $conn->prepare($sql);
if (!$st) {
  respond(['ok' => false, 'msg' => 'Error preparando consulta.'], 500);
}

$st->bind_param("s", $usuario);
$st->execute();

$st->store_result();

if ($st->num_rows === 0) {
  respond(['ok' => false, 'msg' => 'Credenciales inválidas.'], 401);
}

$st->bind_result($db_user, $db_nombre, $db_hash, $db_activo, $db_rol);
$st->fetch();

if ((int)$db_activo !== 1) {
  respond(['ok' => false, 'msg' => 'Usuario inactivo.'], 403);
}

if (!password_verify($pass, $db_hash)) {
  respond(['ok' => false, 'msg' => 'Credenciales inválidas.'], 401);
}

$rol = strtolower(trim((string)$db_rol));
$user = trim((string)$db_user);
$permitidos_reportes = ['admin', '39581549', '51589322', '11798151'];
$esJuradoOSuper = in_array($rol, ['jurado', 'superadmin'], true);
$esReporte      = in_array($user, $permitidos_reportes, true);

if (!$esJuradoOSuper && !$esReporte) {
  respond(['ok' => false, 'msg' => 'No autorizado para el módulo de jurados.'], 403);
}

$redirect = $esReporte
  ? '/inscripciones/vistas/jurados/reportes.php'
  : '/inscripciones/vistas/jurados/jurados.php';

$_SESSION['JUR_USER']   = $user;      
$_SESSION['JUR_NOMBRE'] = $db_nombre;
$_SESSION['JUR_ROL']    = $rol;

respond(['ok' => true, 'msg' => 'Ingreso correcto.', 'redirect' => $redirect]);
