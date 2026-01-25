<?php 

	class sesion_Activa{

		public function iniciar($datos){
			try {
				session_start();
				$_SESSION['loggedin'] = true;
				$_SESSION['id_User'] = $datos['usuario_Id'];
				$_SESSION['id_Rol'] = $datos['usuario_Rol'];
				$_SESSION['user'] = $datos['usuario_Nombre'];
				$_SESSION['mail'] = $datos['usuario_Correo'];
				$_SESSION['start'] = time();
				$_SESSION['expire'] = $_SESSION['start'] + (30 * 60) ;
				return true;
			} catch (Exception $e) {
				return false;
			}
		}

		public function validar(){
			session_start();
			if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true){
				$now = time();            
        		if ($now > $_SESSION['expire'] ){
        			session_destroy();
        			header('Location: ../administracion');
        		}else{
        			$validar = true;
        		}
			}else{
				header('Location: ../administracion');
			}
		}

		public function validar_Menu(){
			session_start();
			if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true){
				$now = time();            
        		if ($now > $_SESSION['expire'] ){
        			session_destroy();
        			header('Location: index.php');
        		}else{

        		}
			}else{
				header('Location: index.php');
			}
		}

		public function validar_Login(){
			session_start();
			if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true){
				$now = time();            
        		if ($now > $_SESSION['expire'] ){

        		}else{
        			header('Location: inicio.php');
        			$validar = true;
        		}
			}
		}

		public function cerrar_Sesion(){
			session_destroy();

        	header('Location: ../administracion');
		}			

	}

 ?>