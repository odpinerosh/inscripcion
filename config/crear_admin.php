<?php
require_once __DIR__ . "/conecta.php";

$usuario = "79635523";
$nombre  = "Administrador";
$pass    = "Admin12345!"; // cámbiala luego
$hash    = password_hash($pass, PASSWORD_DEFAULT);

$cn = Conectar::conexion();

// Verificar si ya existe admin (sin get_result)
$stmt = $cn->prepare("SELECT id FROM usuarios_funcionarios WHERE usuario=? LIMIT 1");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$stmt->bind_result($id_existente);
$stmt->fetch();
$stmt->close();

if (!empty($id_existente)) {
  echo "Ya existe admin.";
  exit;
}

// Insertar admin
$ins = $cn->prepare("INSERT INTO usuarios_funcionarios (usuario, nombre, pass_hash, activo) VALUES (?, ?, ?, 1)");
$ins->bind_param("sss", $usuario, $nombre, $hash);

if ($ins->execute()) {
  echo "Admin creado. Usuario: admin / Clave: Admin12345!";
} else {
  echo "Error creando admin: " . htmlspecialchars($cn->error);
}
$ins->close();
