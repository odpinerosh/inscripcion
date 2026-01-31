$(document).on('submit', '#inscripcion', function(e){
  e.preventDefault();
  return false;
});

function syncConfirmButtons() {
  var can = ($('#can_inscribir').val() || '0') === '1';

  // Botón CONFIRMAR superior (gris)
  if ($('#enviar').length) {
    $('#enviar').prop('disabled', !can);
  }

  // Botón CONFIRMAR inferior (verde)
  if ($('#enviar2').length) {
    $('#enviar2').prop('disabled', !can);
  }
}


function mostrar_Agencias(){
  var urldat = (window.INS_BASE || "") + "/controladores/asociados_Controller.php?accion=2";
  var page = $(this).attr('data');
  var dataString = 'page='+page;

  $.ajax({
    type: "GET",
    url:  urldat,
    data: dataString,
    success: function(data) {
      $('#div_Agencias').html(data);
    }
  });
}

function enviar_Documento(){
  var documento = $('#documento').val();
  var urldat = (window.INS_BASE || "") + "/controladores/asociados_Controller.php?accion=1&id_Asociado=" + encodeURIComponent(documento);

  $.ajax({
    type: "GET",
    url:  urldat,
    success: function(data) {
      $('#div_Datos').empty();
      $('#div_Alertas').empty();
      $('#div_Datos').html(data);
      syncConfirmButtons();
      // En funcionarios habilitar 
      toggleBtnInscribir();
    }
  });
}


// En funcionarios: sin OTP, entra directo
function validar_Documento(){
  var documento = $('#documento').val();
  if (documento === '') {
    alert('El número de identificación no puede estar vacío.');
    return;
  }
  enviar_Documento();
}

// En funcionarios: confirmar y enviar (SIN validar adjuntos)
async function validar_Formulario(){

  var can = ($('#can_inscribir').val() || '0') === '1';
  if (!can) {
    Swal.fire({ icon:'warning', title:'No habilitado', text:'Este asociado no cumple los requisitos para inscribirse.' });
    return;
  }

  const r = await Swal.fire({
    icon: 'question',
    title: 'Confirmar inscripción',
    text: '¿Está seguro de confirmar la inscripción?',
    showCancelButton: true,
    confirmButtonText: 'Sí, confirmar',
    cancelButtonText: 'No, cancelar'
  });

  if (!r.isConfirmed) return;

  $('#enviar').prop('disabled', true);
  realizar_Inscripcion();
}

function escHtml(s){
  return String(s || '')
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');
}


function realizar_Inscripcion(){
  
  $('#div_Loading').show(300);
  $('html, body').animate({ scrollTop: $("#div_Loading").offset().top }, 500);
  $('#div_Validar').hide();
  $('#div_Enviar').hide();
  $('#enviar').prop('disabled', true);
  
  var urldat = (window.INS_BASE || "") + "/controladores/eventos_Controller.php?accion=1";

  var fd = new FormData(document.getElementById('inscripcion'));

  $.ajax({
    type: "POST",
    url: urldat,
    data: fd,
    processData: false,
    contentType: false,
    //dataType: "json",
    success: async function(data){
      $('#div_Loading').hide();
      $('#div_Validar').show();
      $('#div_Enviar').show();

      let resp = data;

      // Si el servidor devolvió texto, intentamos parsear a JSON
      if (typeof data === 'string') {
        try {
          resp = JSON.parse(data);
        } catch (e) {
          await Swal.fire({
            icon: 'error',
            title: 'Respuesta no válida (no es JSON)',
            html: "<pre style='text-align:left;white-space:pre-wrap;max-height:300px;overflow:auto;'>" +
                  escHtml((data || '').slice(0, 2000)) +
                  "</pre>"
          });
          $('#enviar').prop('disabled', false);
          return;
        }
      }

      if (resp && resp.ok){
        await Swal.fire({
          icon: 'success',
          title: 'Inscripción realizada',
          text: resp.msg || 'Su inscripción fue realizada con éxito',
          confirmButtonText: 'Aceptar'
        });
        window.location.reload();
        return;
      }

      if (resp && resp.code === 'YA_INSCRITO'){
        await Swal.fire({
          icon: 'info',
          title: 'Ya inscrito',
          html: resp.msg || 'El asociado ya se encuentra inscrito.',
          confirmButtonText: 'Salir',
          allowOutsideClick: false,
          allowEscapeKey: false
        });
        window.location.reload();
        return;
      }

      await Swal.fire({
        icon: 'error',
        title: 'No se pudo completar',
        text: (resp && resp.msg) ? resp.msg : 'Error al procesar la inscripción'
      });
      $('#enviar').prop('disabled', false);
    },

    error: async function(jqXHR, textStatus, errorThrown){
      $('#div_Loading').hide();
      $('#div_Validar').show();
      $('#div_Enviar').show();

      const body = (jqXHR.responseText || '').slice(0, 2000);

      await Swal.fire({
        icon: 'error',
        title: 'Error del servidor / Respuesta inesperada',
        html:
          "HTTP: <b>" + jqXHR.status + "</b> (" + escHtml(textStatus || '') + ")<br>" +
          "<pre style='text-align:left;white-space:pre-wrap;max-height:300px;overflow:auto;'>" +
          escHtml(body) +
          "</pre>"
      });

      $('#enviar').prop('disabled', false);
    }

  });
}

function numeros(e){
  const allow = ["Enter","Tab","Backspace","Delete","ArrowLeft","ArrowRight","ArrowUp","ArrowDown","Home","End"];
  if (allow.includes(e.key)) return;
  if (e.ctrlKey || e.metaKey) return;
  if (!/^\d$/.test(e.key)) e.preventDefault();
}

// En funcionarios: siempre habilitado
function toggleBtnInscribir(){
  if ($('#enviar').length) $('#enviar').prop('disabled', false);
}
