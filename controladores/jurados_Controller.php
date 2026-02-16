<?php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


  session_start();
  header('Content-Type: application/json; charset=utf-8');

  function respond($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
  }


  if (empty($_SESSION['JUR_USER'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'code' => 'SESSION_TIMEOUT',
        'msg' => 'Sesión expirada por inactividad (15 minutos). Inicie sesión nuevamente.',
        'redirect' => '/inscripciones/vistas/jurados/login.php?timeout=1'
    ]);
    exit;
  }

  // === Timeout por INACTIVIDAD (15 min) ===
  $now = time();
  $ttl = 15 * 60;

  if (!isset($_SESSION['JUR_LAST_ACTIVITY'])) {
    $_SESSION['JUR_LAST_ACTIVITY'] = $now;
  }

  if (($now - (int)$_SESSION['JUR_LAST_ACTIVITY']) > $ttl) {
    session_unset();
    session_destroy();
    http_response_code(401);
    echo json_encode([
      'ok' => false,
      'code' => 'SESSION_TIMEOUT',
      'msg' => 'Sesión expirada por inactividad (15 minutos). Inicie sesión nuevamente.',
      'redirect' => '/inscripciones/vistas/jurados/login.php?timeout=1'
    ]);
    exit;
  }


  // Renovar actividad
  $_SESSION['JUR_LAST_ACTIVITY'] = $now;

  $jurado = $_SESSION['JUR_USER'];

  require_once __DIR__ . '/../config/conecta.php';
  $conn = Conectar::conexion();


  function clean_id($v) {
    $v = trim((string)$v);

    // solo letras/números (quitar espacios, puntos, guiones, etc.)
    $v = preg_replace('/[^\p{L}\p{N}]/u', '', $v);

    // normalizar a mayúsculas
    if (function_exists('mb_strtoupper')) {
      $v = mb_strtoupper($v, 'UTF-8');
    } else {
      $v = strtoupper($v);
    }

    return $v;
  }

    function obtener_urnas_del_jurado($conn, $jurado_user) {
    $out = [];

    $sql = "SELECT u.urna_Id, u.urna_Nombre
            FROM jurados_urnas ju
            JOIN urnas u ON u.urna_Id = ju.urna_Id
            WHERE ju.jurado_usuario = ?
            ORDER BY u.urna_Id";
    $st = $conn->prepare($sql);
    if (!$st) return $out;

    $st->bind_param("s", $jurado_user);
    $st->execute();
    $st->bind_result($id, $nom);
    while ($st->fetch()) {
      $out[] = ['id' => (int)$id, 'nombre' => (string)$nom];
    }
    $st->close();
    return $out;
  }


  function jurado_puede_ver_asociado($conn, $jurado_user, $aso_Id) {
    
    // Valida que el asociado esté en alguna urna asignada al jurado
    $sql = "SELECT 1
            FROM jurados_urnas ju
            JOIN urnas_asociados ua ON ua.urna_Id = ju.urna_Id
            WHERE ju.jurado_usuario = ?
              AND ua.aso_Id = ?
            LIMIT 1";
    $st = $conn->prepare($sql);
    if (!$st) return false;
    $st->bind_param("ss", $jurado_user, $aso_Id);
    $st->execute();
    $st->store_result();
    return ($st->num_rows > 0);
  }

  function jurado_urna_detectada($conn, $jurado_user, $aso_Id) {
    // Devuelve UNA urna válida del jurado para ese asociado.
    // Si hay varias (letras + puesto físico), preferimos la que NO sea "OTRAS AGENCIAS"
    $sql = "SELECT ju.urna_Id, u.urna_Nombre, u.punto_Id
            FROM jurados_urnas ju
            JOIN urnas_asociados ua ON ua.urna_Id = ju.urna_Id
            JOIN urnas u ON u.urna_Id = ju.urna_Id
            WHERE ju.jurado_usuario = ?
              AND ua.aso_Id = ?
            ORDER BY (u.urna_Nombre LIKE '%OTRAS AGENCIAS%') ASC, ju.urna_Id ASC
            LIMIT 1";
    $st = $conn->prepare($sql);
    if (!$st) return null;
    $st->bind_param("ss", $jurado_user, $aso_Id);
    $st->execute();
    $st->store_result();
    if ($st->num_rows === 0) return null;

    $st->bind_result($urna_Id, $urna_Nombre, $punto_Id);
    $st->fetch();

    return [
      'id' => (int)$urna_Id,
      'nombre' => $urna_Nombre,
      'punto_Id' => (int)$punto_Id,
      'es_otras' => (stripos($urna_Nombre, 'OTRAS AGENCIAS') !== false) ? 1 : 0
    ];
  }


  function jurado_urna_de_asociado($conn, $jurado_user, $aso_Id) {
    $sql = "SELECT ju.urna_Id
            FROM jurados_urnas ju
            JOIN urnas_asociados ua ON ua.urna_Id = ju.urna_Id
            WHERE ju.jurado_usuario = ?
              AND ua.aso_Id = ?
            LIMIT 1";
    $st = $conn->prepare($sql);
    if (!$st) return null;
    $st->bind_param("ss", $jurado_user, $aso_Id);
    $st->execute();
    $st->bind_result($urnaId);
    if ($st->fetch()) return (int)$urnaId;
    return null;
  }



  $accion = isset($_REQUEST['accion']) ? (int)$_REQUEST['accion'] : 0;

  // =======================
  // ACCION 1: CONSULTAR
  // =======================
  if ($accion === 1) {
    $aso_Id = clean_id($_POST['aso_Id'] ?? $_GET['aso_Id'] ?? '');
    if ($aso_Id === '') {
      respond(['ok' => false, 'msg' => 'Digite la cédula.'], 400);
    }

    // Buscar asociado (SIN get_result)
    $st = $conn->prepare("SELECT a.aso_Id, a.aso_Nombre, a.aso_Correo, a.aso_Inhabil, a.aso_Agen_Id,
                                p.punto_Nombre AS punto_nombre
                          FROM asociados a
                          LEFT JOIN ptos_atencion p ON p.punto_Id = a.aso_Agen_Id
                          WHERE a.aso_Id = ?
                          LIMIT 1");
    if (!$st) respond(['ok' => false, 'msg' => 'Error preparando consulta.'], 500);

    $st->bind_param("s", $aso_Id);
    $st->execute();
    $st->store_result();

    if ($st->num_rows === 0) {
      $motivo = "No existe en tabla asociados";
      $st2 = $conn->prepare("INSERT INTO elecciones_consultas (aso_Id, jurado_usuario, resultado, motivo)
                            VALUES (?,?, 'NO_EXISTE', ?)");
      if ($st2) {
        $st2->bind_param("sss", $aso_Id, $jurado, $motivo);
        $st2->execute();
      }

      respond(['ok' => true, 'estado' => 'NO_EXISTE', 'msg' => 'El asociado no está registrado.']);
    }

    $st->bind_result($db_id, $db_nombre, $db_correo, $db_inhabil, $db_agen_id, $db_punto_nombre);
    $st->fetch();

    $aso = [
      'id' => $db_id,
      'nombre' => $db_nombre,
      'correo' => $db_correo,
      'punto' => $db_punto_nombre ?: ('Punto ' . $db_agen_id)
    ];

    // Inhabilitado
    if ((int)$db_inhabil === 1) {
      $motivo = "aso_Inhabil=1";
      $st2 = $conn->prepare("INSERT INTO elecciones_consultas (aso_Id, jurado_usuario, resultado, motivo)
                            VALUES (?,?, 'INHABIL', ?)");
      if ($st2) {
        $st2->bind_param("sss", $aso_Id, $jurado, $motivo);
        $st2->execute();
      }

      respond(['ok' => true, 'estado' => 'INHABIL', 'msg' => 'Asociado INHÁBIL. No puede votar.', 'aso' => $aso]);
    }

    // === VALIDACION DE URNA DEL JURADO ===
    if (!jurado_puede_ver_asociado($conn, $jurado, $aso_Id)) {

      // Registrar consulta FUERA_URNA en auditoría
      $motivo = "FUERA_URNA: no pertenece a urnas asignadas al jurado";
      $st2 = $conn->prepare("INSERT INTO elecciones_consultas (aso_Id, jurado_usuario, resultado, motivo)
                            VALUES (?,?, 'FUERA_URNA', ?)");
      if ($st2) {
        $st2->bind_param("sss", $aso_Id, $jurado, $motivo);
        $st2->execute();
      }

      respond([
        'ok' => true,
        'estado' => 'FUERA_URNA',
        'msg' => 'Este asociado NO está asignado a su mesa/urna.',
        'aso' => $aso
      ]);
    }

    // Obtener datos de la urna (preferir principal) para mostrar al jurado
    $urna = jurado_urna_detectada($conn, $jurado, $aso_Id);

    // Validar si ya votó (confirmado)
    $st3 = $conn->prepare("SELECT id, creado_en
                          FROM elecciones_votos
                          WHERE aso_Id = ? AND accion='CONFIRMADO'
                          LIMIT 1");
    $st3->bind_param("s", $aso_Id);
    $st3->execute();
    $st3->store_result();

    $yaVoto = ($st3->num_rows > 0);

    $motivo = $yaVoto ? "Hábil, pero ya registra voto confirmado" : "Hábil";
    $st2 = $conn->prepare("INSERT INTO elecciones_consultas (aso_Id, jurado_usuario, resultado, motivo)
                          VALUES (?,?, 'HABIL', ?)");
    if ($st2) {
      $st2->bind_param("sss", $aso_Id, $jurado, $motivo);
      $st2->execute();
    }

    if ($yaVoto) {
      $st3->bind_result($v_id, $v_fecha);
      $st3->fetch();

      respond([
        'ok' => true,
        'estado' => 'YA_VOTO',
        'msg' => 'El asociado ya registra voto.',
        'voto' => ['fecha' => $v_fecha],
        'aso' => $aso,
        'urna' => $urna
      ]);
    }

    // Hábil y puede votar
    respond([
      'ok' => true,
      'estado' => 'HABIL',
      'msg' => 'Asociado HÁBIL. Puede votar.',
      'aso' => $aso,
      'urna' => $urna
    ]);
  }


  // ====================================================================
  // ACCION 2: REGISTRAR VOTO (CONFIRMAR / ANULAR)
  // - CONFIRMAR: inserta un voto CONFIRMADO (con urna_Id) y aplica el único uq_confirm_unique
  // - ANULAR: cambia el voto CONFIRMADO actual a CANCELADO (solo si el jurado tiene esa urna)
  // ====================================================================
  if ($accion === 2) {

      header('Content-Type: application/json; charset=utf-8');

      // --- sesión jurado ---
      if (empty($_SESSION['JUR_USER'])) {
          respond(['ok' => false, 'msg' => 'No autorizado.'], 401);
      }

      // --- timeout por inactividad (AJAX -> JSON) ---
      $ttl = 900; // 15 min
      $now = time();
      if (isset($_SESSION['JUR_LAST_ACTIVITY']) && (($now - (int)$_SESSION['JUR_LAST_ACTIVITY']) > $ttl)) {
          session_unset();
          session_destroy();
          respond([
              'ok' => false,
              'code' => 'SESSION_TIMEOUT',
              'msg' => 'Sesión expirada por inactividad (15 minutos). Inicie sesión nuevamente.',
              'redirect' => '/inscripciones/vistas/jurados/login.php?timeout=1'
          ], 401);
      }
      $_SESSION['JUR_LAST_ACTIVITY'] = $now;

      $jurado   = $_SESSION['JUR_USER'];
      $aso_Id   = trim($_POST['aso_Id'] ?? '');
      $decision = strtoupper(trim($_POST['decision'] ?? '')); // CONFIRMAR | ANULAR

      if ($aso_Id === '' || !in_array($decision, ['CONFIRMAR', 'ANULAR'], true)) {
          respond(['ok' => false, 'msg' => 'Solicitud inválida.'], 400);
      }

      // --- revalidar asociado y su estado (inhabilitado) ---
      $stA = $conn->prepare("SELECT aso_Id, aso_Nombre, aso_Inhabil FROM asociados WHERE aso_Id = ? LIMIT 1");
      if (!$stA) respond(['ok' => false, 'msg' => 'Error preparando consulta de asociado.'], 500);

      $stA->bind_param("s", $aso_Id);
      $stA->execute();
      $stA->store_result();

      if ($stA->num_rows === 0) {
          respond(['ok' => false, 'msg' => 'Asociado no encontrado.'], 404);
      }

      $stA->bind_result($asoIdDb, $asoNombreDb, $asoInhabilDb);
      $stA->fetch();

      if ((int)$asoInhabilDb === 1) {
          respond(['ok' => false, 'msg' => 'Asociado inhabilitado para votar.'], 403);
      }

      // --- función local para validar/obtener urna del asociado para este jurado (prefiere urna principal) ---
      $getUrnaDelJurado = function($conn, $jurado_user, $aso_id) {
          $sql = "SELECT ju.urna_Id, u.urna_Nombre
                  FROM jurados_urnas ju
                  JOIN urnas_asociados ua ON ua.urna_Id = ju.urna_Id
                  JOIN urnas u ON u.urna_Id = ju.urna_Id
                  WHERE ju.jurado_usuario = ?
                    AND ua.aso_Id = ?
                  ORDER BY (u.urna_Nombre LIKE '%OTRAS AGENCIAS%') ASC, ju.urna_Id ASC
                  LIMIT 1";
          $st = $conn->prepare($sql);
          if (!$st) return null;
          $st->bind_param("ss", $jurado_user, $aso_id);
          $st->execute();
          $st->bind_result($urnaId, $urnaNombre);
          if ($st->fetch()) {
              return [
                  'id' => (int)$urnaId,
                  'nombre' => (string)$urnaNombre,
                  'es_otras' => (stripos($urnaNombre, 'OTRAS AGENCIAS') !== false) ? 1 : 0
              ];
          }
          return null;
      };


      // =========================================================
      // CONFIRMAR -> INSERT (con urna_Id)
      // =========================================================
      if ($decision === 'CONFIRMAR') {

        $urna = $getUrnaDelJurado($conn, $jurado, $aso_Id);
        if (!$urna) {
            respond(['ok' => false, 'msg' => 'No autorizado: asociado fuera de su urna.'], 403);
        }
        $urnaId = (int)$urna['id'];

        $sqlIns = "INSERT INTO elecciones_votos
                    (aso_Id, jurado_usuario, accion, urna_Id, creado_en)
                  VALUES (?, ?, 'CONFIRMADO', ?, NOW())";

        $stI = $conn->prepare($sqlIns);
        if (!$stI) respond(['ok' => false, 'msg' => 'Error preparando registro de voto.'], 500);

        $stI->bind_param("ssi", $aso_Id, $jurado, $urnaId);

        if (!$stI->execute()) {
            // uq_confirm_unique dispara 1062 cuando ya hay un CONFIRMADO para ese aso_Id
            if ((int)$conn->errno === 1062) {
                respond(['ok' => false, 'msg' => 'El asociado ya registra voto confirmado.'], 409);
            }
            respond(['ok' => false, 'msg' => 'No fue posible registrar el voto.'], 500);
        }

        $idVoto = $conn->insert_id;

        // Envío de correo
        $emailOk = 0;
        $emailErr = null;

        /*try {
          require_once __DIR__ . '/../config/cooptraiss_mail.php';

          $to = $db_correo;
          //$cc = ['elecciones2026@cooptraiss.com', 'notificaciones@solucionescooptraiss.com'];
          $cc = ['notificaciones@solucionescooptraiss.com'];

          $subject = "ESTO ES UNA PRUEBA - Registro de voto - Elecciones Cooptraiss 2026 - ESTO ES UNA PRUEBA";
          $body = "
            <div style='font-family: Arial, Helvetica, sans-serif; background:#f5f7f9; padding:24px;'>
              <div style='max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e6e9ee;'>
                <div style='background:#08a750; color:#fff; padding:18px 22px;'>
                  <h2 style='margin:0; font-size:18px;'>ESTO ES UNA PRUEBA - Voto registrado - ESTO ES UNA PRUEBA</h2>
                  <div style='opacity:.95; font-size:13px; margin-top:4px;'>Elección de Delegados<br>ESTO ES UNA PRUEBA - COOPTRAISS 2026-2030 - ESTO ES UNA PRUEBA</div>
                </div>

                <div style='padding:20px 22px; color:#1f2a37;'>
                  <div style='margin:0 0 12px 0; font-size:14px;'>
                    <p>Apreciado(a) asociado(a),</p><p><b>" . htmlspecialchars($db_nombre) . "</b></p>
                    <!--p>Su voto ha sido <b>registrado exitosamente</b>.</p -->
                  </div>

                  <table style='width:100%; border-collapse:collapse; font-size:14px; margin:12px 0 18px 0;'>
                    <tr>
                      <td style='padding:8px 10px; background:#f3f4f6; width:38%; border:1px solid #e5e7eb;'><b>Documento</b></td>
                      <td style='padding:8px 10px; border:1px solid #e5e7eb;'>". $aso_Id ."</td>
                    </tr>
                    <tr>
                      <td style='padding:8px 10px; background:#f3f4f6; border:1px solid #e5e7eb;'><b>Fecha y hora</b></td>
                      <td style='padding:8px 10px; border:1px solid #e5e7eb;'>". date('Y-m-d H:i:s') ."</td>
                    </tr>
                  </table>

                  <p style='margin:16px 0 0 0; font-size:13px; color:#374151;'>
                    Si requiere soporte escriba al correo <b>elecciones2026@cooptraiss.com</b>.
                  </p>

                  <p style='margin:18px 0 0 0; font-size:13px; color:#6b7280;'>
                    Cordialmente,<br>
                    <b>COOPTRAISS</b>
                  </p>
                </div>

                <div style='padding:12px 22px; background:#f9fafb; color:#6b7280; font-size:12px; border-top:1px solid #e6e9ee;'>
                  Por favor no responda este mensaje;--ESTO ES UNA PRUEBA-- se envía desde una cuenta de notificaciones automáticas.
                </div>
              </div>
            </div>
          ";

          send_mail($to, $subject, $body, $cc);
          $emailOk = 1;
        } catch (Throwable $e) {
          $emailOk = 0;
          $emailErr = substr($e->getMessage(), 0, 255);
        }*/

        $emailOk = 0;
        $emailErr = 'No se están enviando correos por decisión gerencial';
        $stU = $conn->prepare("UPDATE elecciones_votos SET email_enviado = ?, email_error = ? WHERE id = ?");
        if ($stU) {
            $stU->bind_param("isi", $emailOk, $emailErr, $idVoto);
            $stU->execute();
        }

        respond([
            'ok' => true,
            'msg' => 'Voto registrado.',
            'urna' => $urna,          // <-- devuelve nombre y es_otras
            'urna_Id' => $urnaId,     // <-- devuelve solo el ID de la urna (para registro)
            'email_enviado' => $emailOk
        ]);
      }

      // =========================================================
      // ANULAR -> UPDATE (solo jurado de esa urna)
      // =========================================================
      if ($decision === 'ANULAR') {

          // 1) buscar voto confirmado actual
          $stC = $conn->prepare("SELECT id, urna_Id
                                FROM elecciones_votos
                                WHERE aso_Id = ? AND accion='CONFIRMADO'
                                ORDER BY creado_en DESC
                                LIMIT 1");
          if (!$stC) respond(['ok' => false, 'msg' => 'Error preparando consulta de voto.'], 500);

          $stC->bind_param("s", $aso_Id);
          $stC->execute();
          $stC->store_result();

          if ($stC->num_rows === 0) {
              respond(['ok' => false, 'msg' => 'No hay voto confirmado para anular.'], 404);
          }

          $stC->bind_result($votoId, $urnaIdConfirmado);
          $stC->fetch();

          // 2) validar que el jurado tenga esa urna asignada
          $stAuth = $conn->prepare("SELECT 1
                                    FROM jurados_urnas
                                    WHERE jurado_usuario = ? AND urna_Id = ?
                                    LIMIT 1");
          if (!$stAuth) respond(['ok' => false, 'msg' => 'Error preparando autorización.'], 500);

          $stAuth->bind_param("si", $jurado, $urnaIdConfirmado);
          $stAuth->execute();
          $stAuth->store_result();

          if ($stAuth->num_rows === 0) {
              respond(['ok' => false, 'msg' => 'No autorizado para anular este voto.'], 403);
          }

          // 3) anular
          $stU = $conn->prepare("UPDATE elecciones_votos
                                SET accion='CANCELADO'
                                WHERE id = ? AND accion='CONFIRMADO'
                                LIMIT 1");
          if (!$stU) respond(['ok' => false, 'msg' => 'Error preparando anulación.'], 500);

          $stU->bind_param("i", $votoId);
          $stU->execute();

          respond(['ok' => true, 'msg' => 'Voto anulado. El asociado puede volver a votar.']);
      }

      // fallback (no debería llegar)
      respond(['ok' => false, 'msg' => 'Operación no soportada.'], 400);
  }

  // ===========================================
  // ACCION 3: PROGRESO DE INSCRIPCIÓN DE JURADO
  // ===========================================

  if ($accion === 3) {
    // Progreso del jurado: SOLO su urna principal (excluye "OTRAS AGENCIAS")

    while ($conn->more_results() && $conn->next_result()) {
      if ($res = $conn->use_result()) { $res->free(); }
    }

    // Total hábiles en urnas PRINCIPALES asignadas al jurado
    $sqlTotal = "
      SELECT COUNT(DISTINCT ua.aso_Id) AS total_urna
      FROM jurados_urnas ju
      JOIN urnas u ON u.urna_Id = ju.urna_Id
      JOIN urnas_asociados ua ON ua.urna_Id = ju.urna_Id
      JOIN asociados a ON a.aso_Id = ua.aso_Id
      WHERE ju.jurado_usuario = ?
        AND a.aso_Inhabil = 0
        AND UPPER(u.urna_Nombre) NOT LIKE '%OTRAS AGENCIAS%'
    ";
    $total = 0;
    $stT = $conn->prepare($sqlTotal);
    if (!$stT) respond(['ok' => false, 'msg' => 'Error preparando total.'], 500);

    $stT->bind_param("s", $jurado);
    $stT->execute();
    $stT->bind_result($total);
    $stT->fetch();
    $stT->close();

    // Inscritos por ese jurado SOLO en urnas PRINCIPALES
    $sqlIns = "
      SELECT COUNT(*) AS inscritos
      FROM elecciones_votos ev
      JOIN urnas u ON u.urna_Id = ev.urna_Id
      WHERE ev.jurado_usuario = ?
        AND UPPER(u.urna_Nombre) NOT LIKE '%OTRAS AGENCIAS%'
        AND ev.accion = 'CONFIRMADO'
    ";
    $inscritos = 0;
    $stI = $conn->prepare($sqlIns);
    if (!$stI) respond(['ok' => false, 'msg' => 'Error preparando inscritos.'], 500);

    $stI->bind_param("s", $jurado);
    $stI->execute();
    $stI->bind_result($inscritos);
    $stI->fetch();
    $stI->close();

    $faltan = max(0, (int)$total - (int)$inscritos);

    respond([
      'ok' => true,
      'total' => (int)$total,
      'inscritos' => (int)$inscritos,
      'faltan' => (int)$faltan
    ]);
  }

  // ===========================
  // ACCION 4: URNAS DEL JURADO 
  // ===========================
  if ($accion === 4) {
    if (empty($_SESSION['JUR_USER'])) {
      respond(['ok' => false, 'msg' => 'No autorizado.'], 401);
    }

    $jurado = $_SESSION['JUR_USER'];
    $urnas = [];

    $sql = "SELECT u.urna_Id, u.urna_Nombre
            FROM jurados_urnas ju
            JOIN urnas u ON u.urna_Id = ju.urna_Id
            WHERE ju.jurado_usuario = ?
            ORDER BY u.urna_Id";
    $st = $conn->prepare($sql);
    if ($st) {
      $st->bind_param("s", $jurado);
      $st->execute();
      $st->bind_result($id, $nom);
      while ($st->fetch()) {
        $urnas[] = [
          'id' => (int)$id,
          'nombre' => (string)$nom,
          'es_otras' => (stripos($nom, 'OTRAS AGENCIAS') !== false) ? 1 : 0
        ];
      }
      $st->close();
    }

    respond(['ok' => true, 'urnas' => $urnas]);
  }

  respond(['ok' => false, 'msg' => 'Acción no válida.'], 400);