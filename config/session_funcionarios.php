<?php
// /inscripciones/config/session_funcionarios.php


require_once __DIR__ . "/session.php";


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validación de sesión de funcionarios (clave separada del módulo de asociados)
if (empty($_SESSION['FUNC_USER'])) {
    
    //log temporal de errores
    error_log("SESSION_FUNC: SIN FUNC_USER | session_id=" . session_id());
    error_log("SESSION_FUNC: _SESSION=" . print_r($_SESSION, true));
    error_log("SESSION_FUNC: cookie=" . print_r($_COOKIE, true));

    header("Location: /inscripciones/vistas/funcionarios/login.php");
    exit;
}
