jQuery(function($) {
  const $chat = $('#cbn8n-chat');
  const $messages = $chat.find('.messages');
  const $input = $('#cbn8n-input');
  const $sendBtn = $('#cbn8n-send');

  /**
   * Envía el mensaje del usuario y gestiona la respuesta
   */
  function sendMessage() {
    const msg = $input.val().trim();
    if (!msg) {
      $messages.append(`<div class="msg error">Por favor, escribe un mensaje.</div>`);
      scrollToBottom();
      return;
    }

    // 1. Mostrar mensaje del usuario
    const userMsg = `<div class="msg user">${msg}</div>`;
    $messages.append(userMsg);
    $input.val('');
    scrollToBottom();

    // Deshabilitar botón y mostrar estado de carga
    $sendBtn.prop('disabled', true).html('Enviando...');

    // 2. Llamada AJAX a WordPress
    // Verificar si hay una URL de webhook configurada
    if (!CBN8N.webhook_url) {
      $messages.append(`<div class="msg error">No se ha configurado la URL del webhook. Por favor, configúrala en Ajustes > Chatbot n8n.</div>`);
      $sendBtn.prop('disabled', true);
      scrollToBottom();
      return;
    }

    // Verificar si tenemos el nonce
    if (!CBN8N.nonce) {
      $messages.append(`<div class="msg error">Error de seguridad. Por favor, recarga la página.</div>`);
      $sendBtn.prop('disabled', true);
      scrollToBottom();
      return;
    }

    console.log('Enviando mensaje:', {
        mensaje: msg,
        webhook_url: CBN8N.webhook_url,
        nonce: CBN8N.nonce
    });

    $.ajax({
      url: CBN8N.ajax_url,
      type: 'POST',
      data: {
        action: 'cbn8n_chat',
        nonce: CBN8N.nonce,
        mensaje: msg,
        webhook_url: CBN8N.webhook_url
      },
      xhrFields: {
        withCredentials: true
      },
      timeout: 60000, // 60 segundos para coincidir con el backend
      dataType: 'json', // Especificar que esperamos JSON
      beforeSend: function(xhr) {
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      },
      success: function(res) {
        console.log('Respuesta exitosa:', res);
        if (res.success) {
          $messages.append(`<div class="msg bot">${res.data}</div>`);
        } else {
          let errorMessage = 'Error: ';
          if (res.data && typeof res.data === 'object') {
            errorMessage += res.data.message || 'Error desconocido';
            if (res.data.details) {
              errorMessage += `<br><small>Detalles: ${res.data.details}</small>`;
            }
            if (res.data.status_code) {
              errorMessage += `<br><small>Código: ${res.data.status_code}</small>`;
            }
          } else {
            errorMessage += 'Error desconocido';
          }
          $messages.append(`<div class="msg error">${errorMessage}</div>`);
        }
      },
      error: function(xhr, status, error) {
        console.error('Error AJAX:', {
          status: status,
          error: error,
          responseText: xhr.responseText,
          statusText: xhr.statusText,
          readyState: xhr.readyState
        });
        
        let errorMessage = 'Error de red. Por favor, intenta nuevamente.';
        if (xhr.status === 0) {
          errorMessage = 'No se puede conectar al servidor. Verifica tu conexión a internet.';
        } else if (xhr.status === 403) {
          errorMessage = 'Error de autenticación. Por favor, recarga la página.';
        } else if (xhr.status === 404) {
          errorMessage = 'Servicio no encontrado. Contacta al administrador.';
        } else if (xhr.status >= 500) {
          errorMessage = 'Error del servidor. Por favor, intenta nuevamente más tarde.';
        }
        
        // Mostrar el error completo en el mensaje
        $messages.append(`<div class="msg error">
          ${errorMessage}
          <pre style="white-space: pre-wrap; margin-top: 10px;">
            Status: ${xhr.status}
            Error: ${error}
            Response: ${xhr.responseText || 'Sin respuesta'}
          </pre>
        </div>`);
      },
      complete: function() {
        // Restaurar botón y estado normal
        $sendBtn.prop('disabled', false).html('Enviar');
        scrollToBottom();
      }
    });
  }

  /**
   * Desplaza el contenedor de mensajes al fondo
   */
  function scrollToBottom() {
    $messages.scrollTop($messages[0].scrollHeight);
  }

  // Envío al hacer clic en el botón
  $sendBtn.on('click', sendMessage);

  // Envío al presionar Enter en el input
  $input.on('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      sendMessage();
    }
  });
});
