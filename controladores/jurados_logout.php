<?php
session_start();

// Borrar solo sesión de jurados (para no afectar otras sesiones de funcionarios, si las hay)
unset($_SESSION['JUR_USER'], $_SESSION['JUR_NOMBRE'], $_SESSION['JUR_ROL']);

// session_unset(); session_destroy();

header('Location: /inscripciones/vistas/jurados/login.php');
exit;