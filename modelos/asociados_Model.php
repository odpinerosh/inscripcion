<?php 
date_default_timezone_set("America/Bogota");

	class Asociados{

	    private $conecta;

	    public function __construct(){
	    	$this->conecta=Conectar::conexion();
	    }

		public function consultar_Asociado($identificacion, $evento){
			$query_bs_acces = "SELECT * FROM participantes_view WHERE part_Aso_Id = '$identificacion' AND even_Id = '$evento'";
			//echo $query_bs_acces;
			$bs_acces = mysqli_query( $this->conecta, $query_bs_acces);
			if ($bs_acces) {
				$totalRows_bs_acces = mysqli_num_rows($bs_acces);
				if($totalRows_bs_acces >0){
		        	$acceso = mysqli_fetch_array($bs_acces);
		    	}else{
		        	$acceso = false;
		    	} 
			}else{
				$acceso = false;
			}
			//var_dump($query_bs_acces);
			return $acceso;
		}

		public function consultar_Asociados(){
			$query_bs_acces = "SELECT * FROM asociados";	
			//echo $query_bs_acces;
			
			$bs_acces = mysqli_query( $this->conecta, $query_bs_acces);
			if ($bs_acces) {
				$totalRows_bs_acces = mysqli_num_rows($bs_acces);
				if($totalRows_bs_acces >0){
		        	$info = mysqli_fetch_array($bs_acces);
		    	}else{
		        	$info = false;
		    	} 
			}else{
				$info = false;
			}
		    return $info;   
		}

		public function consultar_Agencias(){
			$query_bs_acces = "SELECT * FROM agencias";	
			//echo $query_bs_acces;
			
			$bs_acces = mysqli_query( $this->conecta, $query_bs_acces);
			if ($bs_acces) {
				$totalRows_bs_acces = mysqli_num_rows($bs_acces);
				if($totalRows_bs_acces >0){
		        	$info = $bs_acces;
		    	}else{
		        	$info = false;
		    	} 
			}else{
				$info = false;
			}
		    return $info;   
		}

		public function consultar_Agencia($id_Asociado){
			$query_bs_acces = "SELECT * FROM asociados_Agencia WHERE aso_Id = '$id_Asociado'";	
			//echo $query_bs_acces;
			
			$bs_acces = mysqli_query( $this->conecta, $query_bs_acces);
			if ($bs_acces) {
				$totalRows_bs_acces = mysqli_num_rows($bs_acces);
				if($totalRows_bs_acces >0){
		        	$info = $bs_acces;
		    	}else{
		        	$info = false;
		    	} 
			}else{
				$info = false;
			}
		    return $info;   
		}

		public function mascarar_Correo($correo){
			$correo = trim((string)$correo);
			if ($correo === '' || strpos($correo,'@') === false) return "correo registrado";
			[$u,$d] = explode('@', $correo, 2);
			$uMask = substr($u,0,1) . str_repeat('*', max(1, strlen($u)-1));
			return $uMask . '@' . $d;
		}

		public function crear_OTP($asoId, $evenId, $otpPlain, $ttlMin){
			$asoId = mysqli_real_escape_string($this->conecta, $asoId);
			$evenId = mysqli_real_escape_string($this->conecta, $evenId);

			$hash = password_hash($otpPlain, PASSWORD_DEFAULT);
			$hash = mysqli_real_escape_string($this->conecta, $hash);

			$ttlMin = (int)$ttlMin;
			if ($ttlMin < 1) $ttlMin = 10;

			$ip = mysqli_real_escape_string($this->conecta, $_SERVER['REMOTE_ADDR'] ?? '');
			$ua = mysqli_real_escape_string($this->conecta, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250));

			$sql = "
			INSERT INTO acceso_otp (aso_Id, even_Id, code_hash, expires_at, ip, user_agent)
			VALUES ('$asoId', '$evenId', '$hash', DATE_ADD(NOW(), INTERVAL $ttlMin MINUTE), '$ip', '$ua')
			";
			return (bool)mysqli_query($this->conecta, $sql);
		}

		public function verificar_OTP($asoId, $evenId, $otpPlain){
			$asoId = mysqli_real_escape_string($this->conecta, $asoId);
			$evenId = mysqli_real_escape_string($this->conecta, $evenId);

			$sql = "
			SELECT id, code_hash, expires_at, attempts, used_at
			FROM acceso_otp
			WHERE aso_Id='$asoId' AND even_Id='$evenId'
				AND used_at IS NULL
			ORDER BY id DESC
			LIMIT 1
			";
			$rs = mysqli_query($this->conecta, $sql);
			if (!$rs || mysqli_num_rows($rs) === 0){
				return ["ok"=>false,"msg"=>"No hay código activo. Solicita uno nuevo."];
			}

			$row = mysqli_fetch_assoc($rs);

			// vencido
			if (strtotime($row['expires_at']) < time()){
				return ["ok"=>false,"msg"=>"El código venció. Solicita uno nuevo."];
			}

			// límite de intentos (ej. 5)
			if ((int)$row['attempts'] >= 5){
				return ["ok"=>false,"msg"=>"Demasiados intentos. Solicita un nuevo código."];
			}

			$ok = password_verify($otpPlain, $row['code_hash']);

			// siempre incrementa attempts si falla
			if (!$ok){
				$id = (int)$row['id'];
				mysqli_query($this->conecta, "UPDATE acceso_otp SET attempts = attempts + 1 WHERE id = $id");
				return ["ok"=>false,"msg"=>"Código incorrecto."];
			}

			// marcar usado
			$id = (int)$row['id'];
			mysqli_query($this->conecta, "UPDATE acceso_otp SET used_at = NOW() WHERE id = $id");

			return ["ok"=>true];
		}

   }

?>