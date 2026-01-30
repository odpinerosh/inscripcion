<?php 
	require_once("../config/conecta.php");
	require_once("../config/session.php");
	require_once("../modelos/eventos_Model.php");
	require_once("../modelos/asociados_Model.php");
	date_default_timezone_set("America/Bogota");
	//Llamada al modelo
	//require_once("../modelos/citas_Model.php");

	$accion = $_GET['accion'];

	switch ($accion) {

		case '1': // Confirmar inscripción (JSON)
			session_start();
			header('Content-Type: application/json; charset=utf-8');

			// Params
			$id_Evento   = $_POST['id_Evento'] ?? ($_GET['id_Evento'] ?? '');
			$id_Asociado = $_POST['id_Asociado'] ?? ($_POST['identificacion'] ?? '');
			$correo      = $_POST['correo'] ?? '';
			$celular     = $_POST['celular'] ?? '';

			// Guard de sesión OTP (mismo criterio del asociados_Controller)
			$msgSess = '';
			$okSess = false;

			if (!isset($_SESSION['otp_ok'], $_SESSION['otp_aso'], $_SESSION['otp_even'], $_SESSION['otp_expires'])) {
				$msgSess = "Debes validar tu acceso con el código enviado a tu correo antes de continuar.";
			} elseif (time() > (int)$_SESSION['otp_expires']) {
				$_SESSION = [];
				session_destroy();
				$msgSess = "Tu sesión venció. Solicita un nuevo código e inténtalo de nuevo.";
			} elseif ((string)$_SESSION['otp_aso'] !== (string)$id_Asociado || (string)$_SESSION['otp_even'] !== (string)$id_Evento) {
				$msgSess = "Sesión inválida para este documento/evento.";
			} else {
				$okSess = true;
			}

			if (!$okSess) {
				echo json_encode(['ok'=>false, 'msg'=>$msgSess]);
				exit;
			}

			if (trim((string)$id_Asociado) === '' || trim((string)$id_Evento) === '') {
				echo json_encode(['ok'=>false, 'msg'=>'Parámetros incompletos (documento/evento).']);
				exit;
			}

			// Modelos
			$eventos = new Eventos();
			$asociados = new Asociados();

			// Ya inscrito
			$existe = $eventos->validar_Inscripcion($id_Asociado, $id_Evento);
			if ($existe) {
				$f = $existe['ins_Fecha'] ?? '';
				echo json_encode([
					'ok'=>false,
					'code'=>'YA_INSCRITO',
					'msg'=>"El documento $id_Asociado ya se encuentra inscrito. Registrado en $f."
				]);
				exit;
			}

			// Consultar asociado (NO confiar en POST es_delegado/antiguedad)
			$aso = $asociados->consultar_Asociado($id_Asociado, $id_Evento);
			if (!$aso) {
				echo json_encode(['ok'=>false, 'msg'=>'Asociado no encontrado.']);
				exit;
			}

			$esDelegado = (int)($aso['aso_Delegado'] ?? 0) === 1;
			$antiguedad = (float)($aso['aso_Antiguedad'] ?? 0);
			$inhabil    = (int)($aso['aso_Inhabil'] ?? 0) === 1;
			$empleado   = (int)($aso['aso_Empleado'] ?? 0) === 1;
			$horas      = (float)($aso['aso_Horas'] ?? 0);
			$requiereCert = (!$esDelegado && $horas < 80);

			// Reglas servidor
			if (!$esDelegado) {
				if ($inhabil) {
					echo json_encode(['ok'=>false, 'code'=>'INHABIL_MORA', 'msg'=>'No puedes inscribirte: actualmente estás inhábil por mora.']);
					exit;
				}
				if ($empleado) {
					echo json_encode(['ok'=>false, 'code'=>'EX_EMPLEADO_CONFIANZA', 'msg'=>'No puedes inscribirte: fuiste empleado de confianza en los últimos tres (3) años.']);
					exit;
				}
				if ($antiguedad < 5.0) {
					$antTxt = number_format($antiguedad, 1, '.', '');
					echo json_encode(['ok'=>false, 'code'=>'ANTIGUEDAD_INSUFICIENTE', 'msg'=>"No cumples la antigüedad mínima de 5 años. (Antigüedad actual: $antTxt años)"]);
					exit;
				}
			}

			// Helpers: validar PDF por MIME real + cabecera
			$maxBytes = 10 * 1024 * 1024; // 10MB
			$validatePdf = function($file) use ($maxBytes) {
				if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
					return [false, 'Archivo no recibido.'];
				}
				$name = $file['name'] ?? '';
				$tmp  = $file['tmp_name'] ?? '';
				$ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
				$size = (int)($file['size'] ?? 0);

				if ($ext !== 'pdf') return [false, 'El archivo debe tener extensión .pdf.'];
				if ($size <= 0) return [false, 'No fue posible leer el archivo subido (tamaño 0).'];
				if ($size > $maxBytes) return [false, 'El archivo supera el tamaño permitido (10MB).'];

				// MIME real
				if (function_exists('finfo_open')) {
					$finfo = finfo_open(FILEINFO_MIME_TYPE);
					if ($finfo) {
						$mime = finfo_file($finfo, $tmp);
						finfo_close($finfo);
						if ($mime !== 'application/pdf') {
							return [false, 'El archivo no es un PDF válido (MIME).'];
						}
					}
				}

				$fh = @fopen($tmp, 'rb');
				if (!$fh) return [false, 'No fue posible leer el archivo subido.'];
				$head = fread($fh, 4096);
				fclose($fh);
				if ($head === '' || strpos($head, '%PDF') === false) {
					return [false, 'El archivo no parece ser un PDF válido (cabecera).'];
				}

				return [true, 'OK'];
			};

			// Soportes: delegado no sube nada
			$rutas = ['cedula'=>null, 'certificado'=>null];

			if (!$esDelegado) {
				// Cédula obligatoria
				if (!isset($_FILES['pdf_cedula'])) {
					echo json_encode(['ok'=>false, 'msg'=>'Debes adjuntar la fotocopia de la cédula (PDF).']);
					exit;
				}
				[$okPdf, $msgPdf] = $validatePdf($_FILES['pdf_cedula']);
				if (!$okPdf) {
					echo json_encode(['ok'=>false, 'msg'=>"Cédula: $msgPdf"]);
					exit;
				}

				// Certificado si aplica
				if ($requiereCert) {
					if (!isset($_FILES['pdf_certificado'])) {
						echo json_encode(['ok'=>false, 'msg'=>'Debes adjuntar el certificado de cooperativismo (PDF).']);
						exit;
					}
					[$okPdf2, $msgPdf2] = $validatePdf($_FILES['pdf_certificado']);
					if (!$okPdf2) {
						echo json_encode(['ok'=>false, 'msg'=>"Certificado: $msgPdf2"]);
						exit;
					}
				}

				// Guardar archivos en carpeta por cédula
				$asoSafe = preg_replace('/[^0-9A-Za-z_-]/', '', (string)$id_Asociado);
				$baseDir = __DIR__ . '/../soportes/inscripciones/' . $asoSafe . '/';
				if (!is_dir($baseDir)) {
					if (!mkdir($baseDir, 0755, true)) {
						echo json_encode(['ok'=>false, 'msg'=>'No fue posible crear la carpeta de soportes.']);
						exit;
					}
				}

				$ts = date('Ymd_His');

				// Guardar cédula
				$cedName = 'cedula_' . $ts . '.pdf';
				$cedAbs  = $baseDir . $cedName;
				if (!move_uploaded_file($_FILES['pdf_cedula']['tmp_name'], $cedAbs)) {
					echo json_encode(['ok'=>false, 'msg'=>'No fue posible guardar la fotocopia de la cédula.']);
					exit;
				}
				$rutas['cedula'] = 'soportes/inscripciones/' . $asoSafe . '/' . $cedName;

				// Guardar certificado si aplica
				if ($requiereCert) {
					$cerName = 'certificado_' . $ts . '.pdf';
					$cerAbs  = $baseDir . $cerName;
					if (!move_uploaded_file($_FILES['pdf_certificado']['tmp_name'], $cerAbs)) {
						echo json_encode(['ok'=>false, 'msg'=>'No fue posible guardar el certificado de cooperativismo.']);
						exit;
					}
					$rutas['certificado'] = 'soportes/inscripciones/' . $asoSafe . '/' . $cerName;
				}
			}

			// Insertar inscripción
			$fecha = date('Y-m-d H:i:s');
			$rutaAdj = $esDelegado ? null : json_encode($rutas, JSON_UNESCAPED_SLASHES);

			$ok = $eventos->crear_Inscripcion($id_Asociado, $fecha, $correo, $celular, $rutaAdj);
			if (!$ok) {
				echo json_encode(['ok'=>false, 'msg'=>'No fue posible registrar la inscripción.']);
				exit;
			}

			// Correo confirmación (si falla, no anulamos inscripción)
			$sent = true;
			if (trim((string)$correo) !== '') {
				$sent = $eventos->mail_ConfirmacionInscripcion($correo, $id_Asociado, $fecha);
			}

			// Cerrar sesión OTP después de inscribir
			$_SESSION = [];
			session_destroy();

			if (!$sent) {
				echo json_encode([
					'ok'=>true,
					'msg'=>'Inscripción registrada, pero no fue posible enviar el correo de confirmación.'
				]);
				exit;
			}

			echo json_encode(['ok'=>true, 'msg'=>'Inscripción confirmada.']);
		exit;
		
		case '3'://Generar Reporte
			$usuario = $_GET['usuario'];
			$password = $_GET['password'];
			//echo "la fecha inicial es ".$fecha_Inicial." y la fecha final es ".$fecha_Final;
			if ($usuario==$admin && $password==$pass) {
				$eventos = New Eventos();
				$inscritos = $eventos->consultar_Inscripciones();

				$delimiter = "|";
			    $filename = "inscripciones" . date('Y-m-d') . ".csv";
			    
			    //create a file pointer
			    $f = fopen('php://memory', 'w');
			    
			    //set column headers
			    $fields = array('id_Inscrip', 'Evento', 'Fecha y Hora', 'id_Asociado', 'nombre', 'correo', 'celular', 'agencia');
			    fputcsv($f, $fields, $delimiter);

				if (!$inscritos) {
					$lineData = array('', '', '', '', '', '', '');
				        fputcsv($f, $lineData, $delimiter);
				}else{
					//var_dump($inscritos);
				    
				    //output each row of the data, format line as csv and write to file pointer
				    while($row = $inscritos->fetch_assoc()){
				        //$status = ($row['status'] == '1')?'Active':'Inactive';
				        $lineData = array($row['ins_Id'], $row['even_Nombre'], $row['ins_Fecha'], $row['aso_Id'], $row['aso_Nombre'], $row['ins_Correo'], $row['ins_Celular'], $row['agen_Nombre']);
				        fputcsv($f, $lineData, $delimiter);
				    }		
				}

				//move back to beginning of file
			    fseek($f, 0);
			    
			    //set headers to download file rather than displayed
			    header('Content-Type: text/csv');
			    header('Content-Disposition: attachment; filename="' . $filename . '";');
			    
			    //output all remaining data on a file pointer
			    fpassthru($f);
			}else{
				
			    echo"
	                <script language='JavaScript'>
	                	alert('Acceso no autorizado. Verifique los datos ingresados.')
					    history.back();
					 </script>
	                ";
			}
		break;

		default:
		break;
	}

?>