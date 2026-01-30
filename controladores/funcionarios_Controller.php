<?php
// /inscripciones/controladores/funcionarios_Controller.php

require_once __DIR__ . "/../config/conecta.php";
require_once __DIR__ . "/../config/session.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    case 'login':
        $usuario = trim($_POST['usuario'] ?? '');
        $pass    = $_POST['password'] ?? '';

        if ($usuario === '' || $pass === '') {
            header("Location: /inscripciones/vistas/funcionarios/login.php?e=1");
            exit;
        }

        $cn = Conectar::conexion();

        $sql = "SELECT id, usuario, nombre, pass_hash, activo, intentos, bloqueado_hasta
                FROM usuarios_funcionarios
                WHERE usuario = ?
                LIMIT 1";
        $stmt = $cn->prepare($sql);
        $stmt->bind_param("s", $usuario);
        $stmt->execute();

        // Sin get_result(): bind_result
        $id = $u_usuario = $nombre = $pass_hash = $bloqueado_hasta = null;
        $activo = 0;
        $intentos = 0;

        $stmt->bind_result($id, $u_usuario, $nombre, $pass_hash, $activo, $intentos, $bloqueado_hasta);
        $encontro = $stmt->fetch();
        $stmt->close();

        if (!$encontro || (int)$activo !== 1) {
            header("Location: /inscripciones/vistas/funcionarios/login.php?e=1");
            exit;
        }

        if (!empty($bloqueado_hasta) && strtotime($bloqueado_hasta) > time()) {
            header("Location: /inscripciones/vistas/funcionarios/login.php?e=2");
            exit;
        }

        if (!password_verify($pass, $pass_hash)) {
            $intentos_nuevo = (int)$intentos + 1;
            $bloqueado_nuevo = null;

            if ($intentos_nuevo >= 5) {
                $bloqueado_nuevo = date('Y-m-d H:i:s', time() + 600);
                $intentos_nuevo = 0;
            }

            $upd = $cn->prepare("UPDATE usuarios_funcionarios SET intentos=?, bloqueado_hasta=? WHERE id=?");
            $upd->bind_param("isi", $intentos_nuevo, $bloqueado_nuevo, $id);
            $upd->execute();
            $upd->close();

            header("Location: /inscripciones/vistas/funcionarios/login.php?e=1");
            exit;
        }

        session_regenerate_id(true);

        $_SESSION['FUNC_USER'] = [
            'id'      => (int)$id,
            'usuario' => $u_usuario,
            'nombre'  => $nombre
        ];

        $upd = $cn->prepare("UPDATE usuarios_funcionarios SET ultimo_login=NOW(), intentos=0, bloqueado_hasta=NULL WHERE id=?");
        $upd->bind_param("i", $id);
        $upd->execute();
        $upd->close();

        header("Location: /inscripciones/vistas/funcionarios/index.php");
        exit;


    case 'crear_usuario':
        if (empty($_SESSION['FUNC_USER'])) {
            header("Location: /inscripciones/vistas/funcionarios/login.php");
            exit;
        }
        if (empty($_SESSION['FUNC_USER']['usuario']) || $_SESSION['FUNC_USER']['usuario'] !== 'admin') {
            http_response_code(403);
            echo "Acceso denegado.";
            exit;
        }

        $usuario = trim($_POST['usuario'] ?? '');
        $nombreU = trim($_POST['nombre'] ?? '');
        $passU   = $_POST['password'] ?? '';

        if ($usuario === '' || $nombreU === '' || $passU === '') {
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=1");
            exit;
        }

        $usuario = preg_replace('/\s+/', '', $usuario);

        $cn = Conectar::conexion();

        // Verificar si existe (sin get_result)
        $stmt = $cn->prepare("SELECT id FROM usuarios_funcionarios WHERE usuario=? LIMIT 1");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $stmt->bind_result($id_existente);
        $stmt->fetch();
        $stmt->close();

        if (!empty($id_existente)) {
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=2");
            exit;
        }

        $hash = password_hash($passU, PASSWORD_DEFAULT);

        $ins = $cn->prepare("INSERT INTO usuarios_funcionarios (usuario, nombre, pass_hash, activo) VALUES (?, ?, ?, 1)");
        $ins->bind_param("sss", $usuario, $nombreU, $hash);

        if ($ins->execute()) {
            $ins->close();
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=3");
            exit;
        } else {
            $ins->close();
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=4");
            exit;
        }


    case 'logout':
        unset($_SESSION['FUNC_USER']);
        session_regenerate_id(true);
        header("Location: /inscripciones/vistas/funcionarios/login.php");
        exit;

    default:
        header("Location: /inscripciones/vistas/funcionarios/login.php");
        exit;
}
