<?php
// /inscripciones/controladores/funcionarios_Controller.php

require_once __DIR__ . "/../config/conecta.php";
require_once __DIR__ . "/../config/session.php";

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Funciones de ayuda para manejo de usuarios y roles
// Obtener datos del usuario actual
function func_user() {
    return $_SESSION['FUNC_USER'] ?? [];
}

// Obtener rol del usuario actual
function func_role() {
    $u = func_user();
    return $u['rol'] ?? 'usuario';
}

// Requiere que el usuario tenga uno de los roles especificados
function require_roles(array $roles) {
    if (empty($_SESSION['FUNC_USER']['usuario'])) {
        http_response_code(401);
        exit('No autenticado.');
    }
    if (!in_array(func_role(), $roles, true)) {
        http_response_code(403);
        exit('Acceso denegado.');
    }
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

        $sql = "SELECT id, usuario, nombre, pass_hash, activo, intentos, bloqueado_hasta, rol
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
        $rol = null;
        
        $stmt->bind_result($id, $u_usuario, $nombre, $pass_hash, $activo, $intentos, $bloqueado_hasta, $rol);
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
            'nombre'  => $nombre,
            'rol'     => $rol ?: 'usuario'
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

        // Solo superadmin y gestor pueden crear usuarios
        require_roles(['superadmin','gestor']);

        $usuario  = trim($_POST['usuario'] ?? '');
        $nombreU  = trim($_POST['nombre'] ?? '');
        $passU1   = (string)($_POST['password'] ?? '');
        $passU2   = (string)($_POST['password2'] ?? '');

        if ($usuario === '' || $nombreU === '' || $passU1 === '' || $passU2 === '') {
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=1");
            exit;
        }
        if ($passU1 !== $passU2) {
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=2");
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
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=3");
            exit;
        }

        $hash = password_hash($passU1, PASSWORD_DEFAULT);

        $ins = $cn->prepare("INSERT INTO usuarios_funcionarios (usuario, nombre, pass_hash, activo) VALUES (?, ?, ?, 1)");
        $ins->bind_param("sss", $usuario, $nombreU, $hash);

        if ($ins->execute()) {
            $ins->close();
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?ok=1");
            exit;
        } else {
            $ins->close();
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=4");
            exit;
        }

    case 'importar_usuarios':

        if (empty($_SESSION['FUNC_USER'])) {
            header("Location: /inscripciones/vistas/funcionarios/login.php");
            exit;
        }

        // Solo superadmin y gestor pueden crear usuarios
        require_roles(['superadmin','gestor']);
        
        $csv = trim($_POST['csv'] ?? '');
        if ($csv === '') {
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=1");
            exit;
        }

        $cn = Conectar::conexion();

        $lineas = preg_split("/\r\n|\n|\r/", $csv);
        $creados = 0;
        $existentes = 0;
        $invalidos = 0;

        $generadas = []; // usuario => pass temporal

        foreach ($lineas as $ln) {
            $ln = trim($ln);
            if ($ln === '') continue;

            // usuario,nombre,password (password opcional)
            // dividir por comas
            $parts = array_map('trim', explode(',', $ln));

            $usuario = $parts[0] ?? '';
            $nombre  = $parts[1] ?? '';
            $pass    = $parts[2] ?? '';

            // Normaliza usuario
            $usuario = preg_replace('/\s+/', '', $usuario);

            if ($usuario === '' || $nombre === '') {
                $invalidos++;
                continue;
            }

            // Validar que usuario tenga caracteres razonables
            if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $usuario)) {
                $invalidos++;
                continue;
            }

            // Si password viene vacío, generar temporal
            if ($pass === '') {
                // temporal de 10 chars (fácil de dictar)
                $pass = substr(bin2hex(random_bytes(8)), 0, 10);
                $generadas[$usuario] = $pass;
            }

            // Existe?
            $stmt = $cn->prepare("SELECT id FROM usuarios_funcionarios WHERE usuario=? LIMIT 1");
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $stmt->bind_result($id_exist);
            $stmt->fetch();
            $stmt->close();

            if (!empty($id_exist)) {
                $existentes++;
                continue;
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);

            $ins = $cn->prepare("INSERT INTO usuarios_funcionarios (usuario, nombre, pass_hash, activo) VALUES (?, ?, ?, 1)");
            $ins->bind_param("sss", $usuario, $nombre, $hash);

            if ($ins->execute()) {
                $creados++;
            } else {
                $invalidos++;
            }

            $ins->close();
        }

        // Guardar resumen para mostrarlo en la vista (flash simple en sesión)
        $_SESSION['IMPORT_RES'] = [
            'creados' => $creados,
            'existentes' => $existentes,
            'invalidos' => $invalidos,
            'generadas' => $generadas
        ];

        header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?ok=1");
        exit;
 
    //resetear contraseña de un usuario existente
    case 'reset_password':

        if (empty($_SESSION['FUNC_USER'])) {
            header("Location: /inscripciones/vistas/funcionarios/login.php");
            exit;
        }

        // Solo superadmin y gestor pueden resetear contraseñas
        require_roles(['superadmin','gestor']);

        $usuario = trim($_POST['usuario'] ?? '');
        $p1      = (string)($_POST['password'] ?? '');
        $p2      = (string)($_POST['password2'] ?? '');

        if ($usuario === '' || $p1 === '' || $p2 === '') {
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=1");
            exit;
        }
        if ($p1 !== $p2) {
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=2");
            exit;
        }

        $usuario = preg_replace('/\s+/', '', $usuario);

        // Proteger cambio de clave de admin/superadmin
        $target = strtolower($usuario);
        if (($target === 'admin' || $target === 'superadmin') && func_role() !== 'superadmin') {
            http_response_code(403);
            exit('No autorizado para cambiar la clave de admin/superadmin.');
        }


        $cn = Conectar::conexion();

        // Verificar existencia
        $stmt = $cn->prepare("SELECT id FROM usuarios_funcionarios WHERE usuario=? LIMIT 1");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        $stmt->close();

        if (empty($id)) {
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=5");
            exit;
        }

        $hash = password_hash($p1, PASSWORD_DEFAULT);

        $up = $cn->prepare("UPDATE usuarios_funcionarios
                            SET pass_hash=?, intentos=0, bloqueado_hasta=NULL
                            WHERE id=? LIMIT 1");
        $up->bind_param("si", $hash, $id);

        if ($up->execute()) {
            $up->close();
            header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?ok=2");
            exit;
        }

        $up->close();
        header("Location: /inscripciones/vistas/funcionarios/crear_usuario.php?e=6");
        exit;


    case 'logout':
        unset($_SESSION['FUNC_USER']);
        session_regenerate_id(true);
        header("Location: /inscripciones/vistas/funcionarios/login.php");
        exit;

    default:
        header("Location: /inscripciones/vistas/funcionarios/login.php");
        exit;
}
