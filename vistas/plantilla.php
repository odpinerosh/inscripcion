<!doctype html>
<html lang="en">
 
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Inscripción</title>
    <link href="images/favicon.ico" rel="shortcut icon" type="image/vnd.microsoft.icon">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
    <!--link rel="stylesheet" href="css/boton_Iniciar.css"-->
    <link href="assets/vendor/fonts/circular-std/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/libs/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal-header, h4, .close {
            background-color: #5cb85c;
            color:white !important;
            text-align: center;
            font-size: 30px;
        }
        thead tr th { 
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #ffffff;
        }
    
        .table-responsive { 
            height:200px;
            overflow:scroll;
        }
        
        #amortizacion{
          max-height: 350px;
          width: 100%;
          overflow-y: auto;
          min-width: 200px;
        }        
        table.pagos {
            margin:auto;
        }
        table.pagos th {
            font-size: 14px;
            text-align: center;
            background-color: #5cb85c;
            color: white;
            padding: 8px;
        }
        table.pagos tbody {
            font-size: 14px;
            text-align: center;
        }
        table.pagos td {
            padding: 5px;
        }
        
    </style>
</head>

<body>
    <div style="width: 100%; height: 20px; background-color: #08a750;">
            
    </div>
   <!-- ============================================================== -->
    <!-- main wrapper -->
    <!-- ============================================================== -->
    <div class="dashboard-main-wrapper">
       <!-- ============================================================== -->
        <!-- wrapper  -->
        <!-- ============================================================== -->
        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12" style="text-align: center;">
            <div class="page-header">
                <img src="images/logo.jpg" style="max-width: 300px">
            </div>
        </div> 
        <hr>
        <div class="dashboard-wrapper" style="margin-left: 0">
            <div style="text-align: center">
                <h3>INSCRIPCION DE DELEGADOS<br>2026</h3>
            </div>
            <div style="position:fixed;bottom:10px;right:10px;z-index:99999;background:#000;color:#fff;padding:6px 10px;font-size:12px;border-radius:6px;opacity:.75;">
                CNX'N_TEST :: <?php echo date('Y-m-d H:i:s'); ?>
            </div>
            <input type='text' name='evento' class='form-control' id='evento' value='1' style="display: none" readonly></input> 
            <div class="container-fluid dashboard-content" style="max-width: 400px;background-color: #e2f2e4"> 
                <div style="margin: auto; text-align: center"><h3>INSCRIPCIÓN</h3></div>
                <hr>             
                <!--form id="inscripcion" name="inscripcion" autocomplete="off"-->
                <form id="inscripcion" name="inscripcion" autocomplete="off" enctype="multipart/form-data">


                    <!-- Contenedor login (se oculta cuando el OTP valida) -->
                    <div id="div_LoginOtp">
                    <div class='form-group'>
                        <h5>Número de documento</h5>
                        <span class='input-group-prepend'>
                            <input type='text' name='documento' class='form-control' placeholder='Ingrese el número y presione Enter' id='documento' onKeyDown='numeros(event)' inputmode='numeric'></input>
                            <span class='input-group-text' style ='background-color:#08a750;color:#fff101'><i class='fas fa-id-card'></i></span>
                        </span>
                    </div>

                    <!-- Paso 2: pedir correo (se muestra después de Enter en documento) -->
                    <div id='div_CorreoLogin' class='form-group' style='display:none'>
                        <h5>Correo electrónico registrado en Cooptraiss</h5>
                        <span class='input-group-prepend'>
                            <input type='text' name='correo_login' class='form-control' placeholder='Ingrese su correo' id='correo_login' autocapitalize='none' autocomplete='off'></input>
                            <span class='input-group-text' style ='background-color:#08a750;color:#fff101'><i class='fas fa-at'></i></span>
                        </span>
                        <small id='correo_hint' class='text-muted' style='display:block;margin-top:6px;'></small>
                        <button id="btnEnviarOtp" type="button" class="btn btn-primary mt-2">
                            Enviar código
                        </button>
                    </div>

                    <!-- Paso 3: pedir código OTP -->
                    <div id='div_CodigoLogin' class='form-group' style='display:none'>
                        <h5>Código de verificación (6 dígitos)</h5>
                        <span class='input-group-prepend'>
                            <input type='text' name='codigo_login' class='form-control' placeholder='Ingrese el código enviado a su correo' id='codigo_login' maxlength='6' inputmode='numeric'></input>
                            <span class='input-group-text' style ='background-color:#08a750;color:#fff101'><i class='fas fa-key'></i></span>
                        </span>
                        <small class='text-muted' style='display:block;margin-top:6px;'>El código vence en 10 minutos.</small>
                        <button id="btnValidarOtp" type="button" class="btn btn-success mt-2">
                            Validar código
                        </button>

                        <button id="btnReenviarOtp" type="button" class="btn btn-outline-primary mt-2">
                            Reenviar código
                        </button>
                        <button id="btnCancelarOtp" type="button" class="btn btn-secondary mt-2">
                            Cancelar
                        </button>
                        <small id="otpCooldownMsg" class="text-muted" style="display:block;margin-top:6px;"></small>
                      
                    </div>

                    <div id='div_LoginAlert' class='form-group' style='margin-top:8px;'></div>

                    <!-- Botón opcional (deja Enter como flujo principal).  -->
                    <div id='div_Validar' class='form-group' style='text-align: center' display:none'>
                        <!--input class='btn btn-success' type='button' name='validar' id='validar' value='Continuar' class='form-control' style='font-size: large; font-weight:bold' onclick="validar_Documento()"-->
                        <button id="btnContinuarOtp" type="button" class="btn btn-primary">Continuar</button>
                    </div>
                    
                    </div><!-- /div_LoginOtp -->

                    <div id="div_Agencias" class="form-group" style="display: none">
                      
                    </div>
                    <div id='div_Datos' class='form-group'>
                        
                    </div>
                    <div id="div_Alertas" class="form-group">
                        
                    </div>
                    
                </form>
            </div>
        </div>
    </div>

    <footer style="background-color: #289548">
        <div style="text-align: center; margin-top: 10px; color: white">  
            COOPTRAISS 2026 - Unidad de Tecnología
        </div>
    </footer>

   <!-- ============================================================== -->
    <!-- end main wrapper -->
    <!-- ============================================================== -->
    <!-- Optional JavaScript -->
    <script src="assets/vendor/jquery/jquery-3.3.1.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.js"></script>
    <script src="assets/vendor/slimscroll/jquery.slimscroll.js"></script>
    <script src="assets/libs/js/main-js.js"></script>
    <script src="assets/vendor/timeline/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/auditoria.js?v=20260131_1"></script>
</body>

 
</html>