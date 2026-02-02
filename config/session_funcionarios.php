<?php
// /inscripciones/config/session_funcionarios.php


require_once __DIR__ . "/session.php";


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validación de sesión de funcionarios (clave separada del módulo de asociados)
if (empty($_SESSION['FUNC_USER'])) {
    header("Location: /inscripciones/vistas/funcionarios/login.php");
    exit;
}
