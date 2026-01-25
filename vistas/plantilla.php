<!doctype html>
<html lang="en">
 
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Inscripción</title>
    <link href="images/favicon_cooptraiss.png" rel="shortcut icon" type="image/vnd.microsoft.icon">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/boton_Iniciar.css">
    <link href="assets/vendor/fonts/circular-std/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/libs/css/style.css">
    <link rel="stylesheet" href="assets/vendor/fonts/fontawesome/css/fontawesome-all.css">
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
            <input type='text' name='evento' class='form-control' id='evento' value='1' style="display: none" readonly></input> 
            <div class="container-fluid dashboard-content" style="max-width: 400px;background-color: #e2f2e4"> 
                <div style="margin: auto; text-align: center"><h3>INSCRIPCIÓN</h3></div>
                <hr>             
                <form>
                    <div class='form-group'>
                        <h5>Número de documento</h5>
                        <span class='input-group-prepend'>
                            <input type='text' name='documento' class='form-control' placeholder='Ingrese el número' id='documento' onKeyDown='numeros()'></input>
                            <span class='input-group-text' style ='background-color:#08a750;color:#fff101'><i class='fas fa-id-card'></i></span>
                        </span>
                    </div>
                </form>
                <form id="inscripcion" name="inscripcion">
                    <div id='div_Validar' class='form-group' style='text-align: center'>
                        <input class='btn btn-success' type='button' name='validar' id='validar' value='Validar documento' class='form-control' style='font-size: large; font-weight:bold' onclick="validar_Documento()">
                    </div>
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
    <div class="btn-youtube" title="Regresar">
        <a href="https://www.cooptraiss.com" target="_blank">
            <!--<a href="#" class="btn btn-rounded btn-success">Regresar<div></div></a>-->
        </a>
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
    <script src="js/auditoria.js"></script>
</body>

 
</html>