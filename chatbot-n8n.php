<?php
/**
 * Plugin Name:     n8n Integration
 * Plugin URI:      https://faos.tech
 * Description:     Un plugin para integrar facilmente n8n. en tus sitios y comenzar a automatizar tus tareas con wordpress.
 * Version:         1.0.0
 * Author:          Fausto Reyes
 * Text Domain:     chatbot-n8n
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Previene acceso directo
}

// --------------------------------------------------
// CONSTANTES
// --------------------------------------------------
define( 'CBN8N_URL',  plugin_dir_url( __FILE__ ) );
define( 'CBN8N_PATH', plugin_dir_path( __FILE__ ) );

// --------------------------------------------------
// ACTIVACIÓN / DESACTIVACIÓN
// --------------------------------------------------
register_activation_hook( __FILE__, 'cbn8n_activate' );
function cbn8n_activate() {
    // Aquí podrías crear tablas o poner opciones por defecto
}

register_deactivation_hook( __FILE__, 'cbn8n_deactivate' );
function cbn8n_deactivate() {
    // Limpieza si es necesario al desactivar
}

// --------------------------------------------------
// FUNCIONES DE LOGGING
// --------------------------------------------------
function cbn8n_log($message, $level = 'info') {
    $log_file = CBN8N_PATH . 'logs/chatbot.log';
    $date = date('Y-m-d H:i:s');
    $log_message = "[$date] [$level] $message\n";
    
    // Crear directorio de logs si no existe
    if (!file_exists(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    
    // Escribir en el log
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    // También escribir en el log de WordPress
    error_log($log_message);
}

// --------------------------------------------------
// ENCOLADO DE ASSETS (JS + CSS)
// --------------------------------------------------
add_action( 'wp_enqueue_scripts', 'cbn8n_enqueue_assets' );
function cbn8n_enqueue_assets() {
    // CSS del chatbot
    wp_enqueue_style( 'cbn8n-style', CBN8N_URL . 'assets/css/chatbot.css' );

    // JS del chatbot (depende de jQuery)
    wp_enqueue_script(
        'cbn8n-script',
        CBN8N_URL . 'assets/js/chatbot.js',
        [ 'jquery' ],
        '1.0.0',
        true
    );

    // Pasamos URL de admin-ajax, nonce y webhook al JS
    wp_localize_script( 'cbn8n-script', 'CBN8N', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'cbn8n_nonce' ),
        'webhook_url' => get_option( 'cbn8n_webhook_url', '' )
    ] );
}

// --------------------------------------------------
// SHORTCODE [chatbot_n8n]
// --------------------------------------------------
add_shortcode( 'chatbot_n8n', 'cbn8n_chatbot_shortcode' );
function cbn8n_chatbot_shortcode() {
    return '
      <div id="cbn8n-chat">
        <div class="messages"></div>
        <input type="text" id="cbn8n-input" placeholder="Escribe tu mensaje…" />
        <button id="cbn8n-send">Enviar</button>
      </div>
    ';
}

// --------------------------------------------------
// AJAX HANDLER: envía el mensaje a n8n y devuelve la respuesta
// --------------------------------------------------
add_action( 'wp_ajax_cbn8n_chat',      'cbn8n_handle_chat' );
add_action( 'wp_ajax_nopriv_cbn8n_chat','cbn8n_handle_chat' );

function cbn8n_handle_chat() {
    // Verificar nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cbn8n_nonce')) {
        wp_send_json_error([
            'message' => 'Verificación de seguridad fallida',
            'details' => 'Por favor, recarga la página y prueba nuevamente'
        ]);
        return;
    }

    // Verificar permisos
    if (!current_user_can('read')) {
        wp_send_json_error([
            'message' => 'No tienes permisos suficientes',
            'details' => 'Necesitas estar registrado para usar el chatbot'
        ]);
        return;
    }

    // Sanitize input
    $mensaje = sanitize_text_field( $_POST['mensaje'] ?? '' );
    if ( empty( $mensaje ) ) {
        wp_send_json_error( 'Mensaje vacío' );
    }

    // Obtén y valida la URL del webhook
    $webhook_url = get_option( 'cbn8n_webhook_url' );
    cbn8n_log("URL del webhook: $webhook_url");
    
    if ( empty( $webhook_url ) ) {
        cbn8n_log('Webhook no configurado', 'error');
        wp_send_json_error([
            'message' => 'Webhook no configurado',
            'details' => 'Por favor, configura la URL del webhook en Ajustes > Chatbot n8n'
        ]);
    }

    // Validar que la URL sea una URL válida
    if ( ! filter_var( $webhook_url, FILTER_VALIDATE_URL ) ) {
        cbn8n_log("URL inválida: $webhook_url", 'error');
        wp_send_json_error([
            'message' => 'URL inválida',
            'details' => 'La URL del webhook no es válida'
        ]);
    }

    // Llamada HTTP al webhook de n8n con mejor manejo de errores
    $response = wp_remote_post( $webhook_url, [
        'method' => 'POST',
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
        ],
        'body' => wp_json_encode([
            'text' => $mensaje
        ]),
        'timeout' => 60, // Aumentado a 60 segundos
        'sslverify' => false, // Desactivar verificación SSL
        'redirection' => 5, // Permitir hasta 5 redirecciones
        'blocking' => true, // Asegurar que la petición sea bloqueante
        'follow_redirects' => true, // Seguir redirecciones
        'httpversion' => '1.1', // Usar HTTP/1.1
        'sslcertificates' => ABSPATH . 'wp-includes/certificates/ca-bundle.crt', // Usar certificados de WordPress
        'debug' => true // Habilitar depuración
    ] );

    // Manejo completo de errores
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        cbn8n_log("Error en llamada a webhook: $error_message", 'error');
        wp_send_json_error([
            'message' => 'Error de conexión con el servidor',
            'details' => $error_message
        ]);
    }

    // Verificar código de estado HTTP
    $status_code = wp_remote_retrieve_response_code( $response );
    cbn8n_log("Código de estado HTTP: $status_code");
    
    if ( $status_code !== 200 ) {
        $error_message = wp_remote_retrieve_response_message( $response );
        cbn8n_log("Error HTTP: Código $status_code - $error_message", 'error');
        wp_send_json_error([
            'message' => 'Error en la respuesta del servidor',
            'status_code' => $status_code,
            'details' => $error_message
        ]);
    }

    // Procesa la respuesta
    $body = wp_remote_retrieve_body( $response );
    cbn8n_log("Respuesta recibida: $body");

    // Intentar decodificar el JSON
    $data = json_decode($body, true);
    
    if (is_wp_error($data)) {
        cbn8n_log("Error al decodificar JSON: " . print_r($data, true), 'error');
        wp_send_json_error([
            'message' => 'Error al procesar la respuesta',
            'details' => 'Formato de respuesta no válido'
        ]);
    }

    // Verificar si la respuesta es válida
    if (isset($data['text'])) {
        // Si el texto está vacío, devolvemos un mensaje por defecto
        if (empty(trim($data['text']))) {
            $default_response = 'Lo siento, no pude procesar tu mensaje. Por favor, intenta de nuevo.';
            cbn8n_log("Respuesta vacía, usando respuesta por defecto");
            wp_send_json_success($default_response);
        } else {
            wp_send_json_success($data['text']);
        }
    } else {
        // Si no tenemos texto, pero el cuerpo no está vacío, devolvemos el cuerpo
        if (!empty(trim($body))) {
            wp_send_json_success($body);
        } else {
            cbn8n_log("Formato de respuesta no reconocido: " . print_r($data, true), 'error');
            wp_send_json_error([
                'message' => 'Respuesta inválida desde n8n',
                'details' => 'Formato de respuesta no esperado'
            ]);
        }
    }
}

// 7) Muestra el chatbot en todas las páginas, justo antes del cierre de </body>
add_action( 'wp_footer', 'cbn8n_render_chatbot_global' );
function cbn8n_render_chatbot_global() {
    // Sólo en frontend, no en admin
    if ( is_admin() ) {
        return;
    }
    // Llama al shortcode que ya definimos
    echo '<div class="cbn8n-fixed-container">';
    echo do_shortcode( '[chatbot_n8n]' );
    echo '</div>';
}

// --------------------------------------------------
// ADMIN: página de ajustes para configurar el webhook
// --------------------------------------------------

// 1) Añade un submenú bajo “Ajustes”
add_action( 'admin_menu', 'cbn8n_add_settings_page' );
function cbn8n_add_settings_page() {
    add_options_page(
        'Chatbot n8n',          // Título de la página
        'Chatbot n8n',          // Texto en el menú
        'manage_options',       // Capacidad requerida
        'cbn8n-settings',       // Slug de la página
        'cbn8n_settings_page'   // Función callback
    );
}

// 2) Registra la opción en WP
add_action( 'admin_init', 'cbn8n_register_settings' );
function cbn8n_register_settings() {
    register_setting(
        'cbn8n_settings_group', // Grupo de ajustes
        'cbn8n_webhook_url',     // Nombre de la opción
        [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]
    );
}

// 3) Renderiza el formulario de ajustes
function cbn8n_settings_page() { ?>
    <div class="wrap">
      <h1>Chatbot n8n Settings</h1>
      <form method="post" action="options.php">
        <?php
          settings_fields( 'cbn8n_settings_group' );
        ?>
        <table class="form-table">
          <tr valign="top">
            <th scope="row">
              <label for="cbn8n_webhook_url">Webhook URL de n8n</label>
            </th>
            <td>
              <input
                type="url"
                id="cbn8n_webhook_url"
                name="cbn8n_webhook_url"
                value="<?php echo esc_attr( get_option('cbn8n_webhook_url') ); ?>"
                style="width:100%; max-width:400px;"
              />
              <p class="description">
                Pega aquí la URL de tu Webhook de n8n (último nodo “Respond to Webhook”).
              </p>
            </td>
          </tr>
        </table>
        <?php submit_button( 'Guardar Cambios' ); ?>
      </form>
    </div>
<?php }

