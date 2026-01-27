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

		case '1': // Confirmar inscripción (HTML)
			session_start();

			$id_Evento = $_GET['id_Evento'] ?? ''; // se conserva por compatibilidad
			$id_Asociado = $_POST['identificacion'] ?? '';
			$correo = $_POST['correo'] ?? '';
			$celular = $_POST['celular'] ?? '';

			$esDelegado = (int)($_POST['es_delegado'] ?? 0);
			$antiguedad = (float)($_POST['antiguedad'] ?? 0);

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
				echo "<div class='alert alert-danger' role='alert'>".$msgSess."</div>";
				exit;
			}

			if (trim($id_Asociado) === '' || trim($correo) === '') {
				echo "<div class='alert alert-danger' role='alert'>Parámetros incompletos.</div>";
				exit;
			}

			$eventos = new Eventos();

			// Ya inscrito
			$existe = $eventos->validar_Inscripcion($id_Asociado, $id_Evento);
			if ($existe) {
				$f = $existe['ins_Fecha'] ?? '';
				echo "<div class='alert alert-primary' role='alert'>
						El documento ".$id_Asociado." ya se encuentra inscrito. Registrado en ".$f."
					</div>
					<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
				exit;
			}

			// Reglas
			$rutaAdj = null;

			if ($esDelegado !== 1) {
				if ($antiguedad < 5.0) {
				echo "<div class='alert alert-danger' role='alert'>
						No cumples la antigüedad mínima de 5 años para inscribirte.
						</div>
						<script>setTimeout(function(){ window.location.reload(); }, 2500);</script>";
				exit;
				}

				$chkCurso = isset($_POST['chk_curso80']) && $_POST['chk_curso80'] == '1';
				$chkNoDir = isset($_POST['chk_no_directivo']) && $_POST['chk_no_directivo'] == '1';

				if (!$chkCurso || !$chkNoDir) {
				echo "<div class='alert alert-danger' role='alert'>
						Debes marcar las certificaciones requeridas.
						</div>";
				exit;
				}

				// Archivo PDF único obligatorio
				if (!isset($_FILES['soporte_pdf']) || $_FILES['soporte_pdf']['error'] !== UPLOAD_ERR_OK) {
				echo "<div class='alert alert-danger' role='alert'>
						Debes adjuntar el archivo PDF único (certificado + cédula).
						</div>";
				exit;
				}

				error_log("UPLOAD soporte_pdf: name=".$_FILES['soporte_pdf']['name'].
					" size=".$_FILES['soporte_pdf']['size'].
					" tmp=".$_FILES['soporte_pdf']['tmp_name'].
					" err=".$_FILES['soporte_pdf']['error'].
					" type=".$_FILES['soporte_pdf']['type']);

				// Validar PDF básico
				// Extensión .pdf
				// Cabecera %PDF
				$tmp  = $_FILES['soporte_pdf']['tmp_name'];
				$name = $_FILES['soporte_pdf']['name'] ?? '';
				$ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
				$size = (int)($_FILES['soporte_pdf']['size'] ?? 0);

				if ($ext !== 'pdf') {
				echo "<div class='alert alert-danger' role='alert'>El archivo debe tener extensión .pdf.</div>";
				exit;
				}

				if ($size <= 0) {
				echo "<div class='alert alert-danger' role='alert'>No fue posible leer el archivo subido (tamaño 0).</div>";
				exit;
				}

				$fh = @fopen($tmp, 'rb');
				if (!$fh) {
				echo "<div class='alert alert-danger' role='alert'>No fue posible leer el archivo subido.</div>";
				exit;
				}

				$head = fread($fh, 4096);
				fclose($fh);

				error_log("UPLOAD head_hex=".bin2hex(substr($head, 0, 32)));

				if ($head === '' || strpos($head, '%PDF') === false) {
				echo "<div class='alert alert-danger' role='alert'>El archivo no parece ser un PDF válido.</div>";
				exit;
				}


				// Tamaño máximo 10MB
				$maxBytes = 10 * 1024 * 1024; // 10MB
				if ((int)$_FILES['soporte_pdf']['size'] > $maxBytes) {
				echo "<div class='alert alert-danger' role='alert'>El archivo supera el tamaño permitido (10MB).</div>";
				exit;
				}


				// Guardar archivo
				$baseDir = __DIR__ . '/../soportes/inscripciones/' . preg_replace('/[^0-9A-Za-z_-]/', '', (string)$id_Asociado) . '/';
				if (!is_dir($baseDir)) {
					if (!mkdir($baseDir, 0755, true)) {
						echo "<div class='alert alert-danger' role='alert'>No fue posible crear la carpeta de soportes.</div>";
						exit;
					}
				}

				$fileName = 'soporte_' . date('Ymd_His') . '.pdf';
				$destAbs = $baseDir . $fileName;

				if (!move_uploaded_file($tmp, $destAbs)) {
				echo "<div class='alert alert-danger' role='alert'>No fue posible guardar el archivo.</div>";
				exit;
				}

				// Ruta relativa para BD
				$rutaAdj = 'soportes/inscripciones/' . preg_replace('/[^0-9A-Za-z_-]/', '', (string)$id_Asociado) . '/' . $fileName;
			}

			// Insertar inscripción
			$fecha = date('Y-m-d H:i:s');

			$ok = $eventos->crear_Inscripcion($id_Asociado, $fecha, $correo, $celular, $rutaAdj);
			if (!$ok) {
				echo "<div class='alert alert-danger' role='alert'>No fue posible registrar la inscripción.</div>";
				exit;
			}

			// Correo confirmación
			$sent = $eventos->mail_ConfirmacionInscripcion($correo, $id_Asociado, $fecha);
			if (!$sent) {
				echo "<div class='alert alert-warning' role='alert'>
						Inscripción registrada, pero no fue posible enviar el correo de confirmación.
					</div>
					<script>setTimeout(function(){ window.location.reload(); }, 2500);</script>";
				exit;
			}

			// Cerrar sesión OTP después de inscribir
			$_SESSION = [];
			session_destroy();

			echo "<div class='alert alert-success' role='alert'>
					Inscripción confirmada. En breve se reiniciará el formulario.
					</div>
					<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
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