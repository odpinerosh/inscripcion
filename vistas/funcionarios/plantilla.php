<?php
// /inscripciones/vistas/funcionarios/plantilla.php
if (session_status() === PHP_SESSION_NONE) session_start();

$nombre  = $_SESSION['FUNC_USER']['nombre']  ?? '';
$usuario = $_SESSION['FUNC_USER']['usuario'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($titulo ?? 'Módulo Interno'); ?></title>
  <link href="/inscripciones/images/favicon.ico" rel="shortcut icon" type="image/vnd.microsoft.icon">

  <!-- Bootstrap -->
  <link rel="stylesheet" href="/inscripciones/assets/vendor/bootstrap/css/bootstrap.min.css">
  
  <style>
    :root{
      --brand:#0b2a4a;
      --brand-2:#08a750;
      --bg:#f5f6f8;
    }
    body{ background:var(--bg); }
    /* Fallbacks básicos si Bootstrap no carga */
    .card{
      border-radius: .9rem;
      box-shadow: 0 2px 10px rgba(0,0,0,.06);
      border:1px solid rgba(15,23,42,.08);
    }

    .content-wrap .card{ padding: 1rem; }
    @media (min-width: 768px){
      .content-wrap .card{ padding: 1.25rem; }
    }
    .content-wrap .card.card-no-pad{ padding: 0; }

    .content-wrap .card > .card-header{
      margin: -1rem -1rem 1rem;
      padding: .75rem 1rem;
      border-top-left-radius: inherit;
      border-top-right-radius: inherit;
    }
    .content-wrap .card > .card-footer{
      margin: 1rem -1rem -1rem;
      padding: .75rem 1rem;
      border-bottom-left-radius: inherit;
      border-bottom-right-radius: inherit;
    }
    @media (min-width: 768px){
      .content-wrap .card > .card-header{ margin: -1.25rem -1.25rem 1.25rem; padding: .9rem 1.25rem; }
      .content-wrap .card > .card-footer{ margin: 1.25rem -1.25rem -1.25rem; padding: .9rem 1.25rem; }
    }
    .btn{ border-radius: .75rem; }
    .muted{ color:#6b7280; font-size:.875rem; }
    .top-strip{ height: 10px; background: var(--brand-2); }
    .app-header{ background: var(--brand); color:#fff; }
    .app-header .brand-title{ font-weight: 800; letter-spacing: .2px; }
    .app-header .user-meta{ color: rgba(255,255,255,.85); font-size: .9rem; }
    .content-wrap{ padding: 1rem; }
    @media (min-width: 992px){
      .content-wrap{ padding: 1.5rem; }
    }
    .warn{
      background: #fff7ed;
      border-color: #fed7aa;
    }
    .warn b{ color:#9a3412; }
    .btn-brand{
      background: var(--brand);
      border-color: var(--brand);
      color:#fff;
    }
    .btn-brand:hover{ filter: brightness(.95); color:#fff; }
    .btn-exit{
      white-space: nowrap;
    }

    .input-group-text.bg-success,
    .input-group-text.text-bg-success{
      color:#fff;
      font-weight:700;
      min-width: 2.5rem;
      justify-content: center;
    }
  </style>
</head>
<body>
  <div class="top-strip"></div>

  <header class="app-header">
    <div class="container-fluid py-3">
      <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
        <div>
          <div class="brand-title">Inscripciones Delegados — Uso interno</div>
          <div class="user-meta">
            Funcionario: <?php echo htmlspecialchars($nombre); ?>
            <?php if ($usuario !== ''): ?>(<?php echo htmlspecialchars($usuario); ?>)<?php endif; ?>
          </div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2">
          <a class="btn btn-outline-light btn-exit" href="/inscripciones/controladores/funcionarios_Controller.php?accion=logout">Salir</a>
        </div>
      </div>
    </div>
  </header>

  <main class="content-wrap">
    <div class="container" style="max-width: 980px;">
      <?php echo $contenido ?? ''; ?>
    </div>
  </main>

  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <script>
    // Inyectar ✓ en indicadores “verificado” vacíos 
    document.addEventListener('DOMContentLoaded', function(){
      var candidates = document.querySelectorAll(
        '.input-group-text.bg-success, .input-group-text.text-bg-success, .btn.btn-success'
      );
      candidates.forEach(function(el){
        var hasIcon = el.querySelector('i,svg,img');
        var text = (el.textContent || '').trim();
        if (!hasIcon && text === '') {
          el.textContent = '✓';
          el.setAttribute('aria-label','Verificado');
          el.setAttribute('title','Verificado');
        }
      });
    });
  </script>
</body>
</html>
