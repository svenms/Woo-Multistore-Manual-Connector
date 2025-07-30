<?php
/**
 * Clase AJAX del plugin
 */

defined('ABSPATH') || exit;

class CVAV_Ajax {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_cvav_connect_attributes', array($this, 'connect_attributes'));
        add_action('wp_ajax_cvav_save_connection', array($this, 'connect_attributes')); // Alias para compatibilidad
        add_action('wp_ajax_cvav_disconnect_attributes', array($this, 'disconnect_attributes'));
        add_action('wp_ajax_cvav_get_site_attributes', array($this, 'get_site_attributes'));
        add_action('wp_ajax_cvav_get_existing_connections', array($this, 'get_existing_connections'));
        add_action('wp_ajax_cvav_get_site_connections', array($this, 'get_site_connections'));
        add_action('wp_ajax_cvav_refresh_connections', array($this, 'refresh_connections'));
        add_action('wp_ajax_cvav_check_connection_status', array($this, 'check_connection_status'));
        add_action('wp_ajax_cvav_get_connection_stats', array($this, 'get_connection_stats'));
        add_action('wp_ajax_cvav_search_attributes', array($this, 'search_attributes'));
        add_action('wp_ajax_cvav_auto_connect_by_name', array($this, 'auto_connect_by_name'));
        add_action('wp_ajax_cvav_validate_connection', array($this, 'validate_connection'));
        add_action('wp_ajax_cvav_test_site', array($this, 'test_site'));
        add_action('wp_ajax_cvav_debug_connection', array($this, 'debug_connection'));
    }

    /**
     * Conectar atributos
     */
    public function connect_attributes() {
        error_log('CVAV DEBUG: connect_attributes() AJAX called');
        
        // Verificar nonce con manejo de errores mejorado
        if (!isset($_POST['nonce'])) {
            error_log('CVAV DEBUG: connect_attributes() AJAX - nonce not set');
            wp_send_json_error(__('Error de seguridad: Falta nonce. Por favor, recarga la página.', 'chilevapo-andesvapor-connector'));
        }
        
        $nonce_check = wp_verify_nonce($_POST['nonce'], 'cvav_nonce');
        if (!$nonce_check) {
            error_log('CVAV DEBUG: connect_attributes() AJAX - nonce verification failed');
            $new_nonce = wp_create_nonce('cvav_nonce');
            wp_send_json_error(array(
                'message' => __('Error de seguridad: Nonce expirado. Por favor, recarga la página.', 'chilevapo-andesvapor-connector'),
                'new_nonce' => $new_nonce,
                'old_nonce' => $_POST['nonce']
            ));
        }
        error_log('CVAV DEBUG: connect_attributes() AJAX - nonce verification passed');

        if (!current_user_can('manage_options')) {
            error_log('CVAV DEBUG: connect_attributes() AJAX - user not authorized');
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }
        error_log('CVAV DEBUG: connect_attributes() AJAX - user authorized');

        // Verificar que los datos estén presentes
        if (!isset($_POST['master_attribute_id']) || !isset($_POST['slave_attribute_id'])) {
            error_log('CVAV DEBUG: connect_attributes() AJAX - missing data');
            wp_send_json_error(__('Datos incompletos.', 'chilevapo-andesvapor-connector'));
        }

        $master_attribute_id = intval($_POST['master_attribute_id']);
        $slave_attribute_id = intval($_POST['slave_attribute_id']);
        error_log('CVAV DEBUG: connect_attributes() AJAX - master_attribute_id: ' . $master_attribute_id . ', slave_attribute_id: ' . $slave_attribute_id);

        if ($master_attribute_id <= 0 || $slave_attribute_id <= 0) {
            error_log('CVAV DEBUG: connect_attributes() AJAX - invalid attribute IDs');
            wp_send_json_error(__('IDs de atributos inválidos.', 'chilevapo-andesvapor-connector'));
        }

        // Verificar que los atributos no sean del mismo sitio
        // Primero obtenemos los sitios configurados
        $connector = CVAV_Connector();
        $sites = $connector->get_configured_sites();
        
        if (empty($sites['master']) || empty($sites['slave'])) {
            error_log('CVAV DEBUG: connect_attributes() AJAX - sites not configured properly');
            wp_send_json_error(__('Los sitios no están configurados correctamente.', 'chilevapo-andesvapor-connector'));
        }
        
        // Verificar que los atributos pertenezcan a sitios diferentes
        $master_attributes = $connector->get_site_attributes($sites['master']);
        $slave_attributes = $connector->get_site_attributes($sites['slave']);
        
        $master_attr_site_id = null;
        $slave_attr_site_id = null;
        
        // Buscar el sitio del atributo de Master
        foreach ($master_attributes as $attr) {
            if ($attr['id'] == $master_attribute_id) {
                $master_attr_site_id = $sites['master'];
                break;
            }
        }
        
        // Buscar el sitio del atributo de Slave
        foreach ($slave_attributes as $attr) {
            if ($attr['id'] == $slave_attribute_id) {
                $slave_attr_site_id = $sites['slave'];
                break;
            }
        }
        
        // Si ambos atributos pertenecen al mismo sitio, no se pueden conectar
        if ($master_attr_site_id && $slave_attr_site_id && $master_attr_site_id === $slave_attr_site_id) {
            error_log('CVAV DEBUG: connect_attributes() AJAX - attributes from same site');
            wp_send_json_error(__('No se pueden conectar atributos del mismo sitio.', 'chilevapo-andesvapor-connector'));
        }

        error_log('CVAV DEBUG: connect_attributes() AJAX - sites configured: ' . print_r($sites, true));
        error_log('CVAV DEBUG: connect_attributes() AJAX - master attributes count: ' . count($master_attributes));
        error_log('CVAV DEBUG: connect_attributes() AJAX - slave attributes count: ' . count($slave_attributes));
        
        $master_exists = false;
        $slave_exists = false;
        $master_attr_name = '';
        $slave_attr_name = '';
        
        error_log('CVAV DEBUG: connect_attributes() AJAX - checking if master attribute exists');
        foreach ($master_attributes as $attr) {
            if ($attr['id'] == $master_attribute_id) {
                $master_exists = true;
                $master_attr_name = $attr['name'];
                error_log('CVAV DEBUG: connect_attributes() AJAX - master attribute found: ' . $master_attr_name);
                break;
            }
        }
        
        error_log('CVAV DEBUG: connect_attributes() AJAX - checking if slave attribute exists');
        foreach ($slave_attributes as $attr) {
            if ($attr['id'] == $slave_attribute_id) {
                $slave_exists = true;
                $slave_attr_name = $attr['name'];
                error_log('CVAV DEBUG: connect_attributes() AJAX - slave attribute found: ' . $slave_attr_name);
                break;
            }
        }
        
        if (!$master_exists) {
            wp_send_json_error(sprintf(__('El atributo con ID %d no existe en Master.', 'chilevapo-andesvapor-connector'), $master_attribute_id));
        }
        
        if (!$slave_exists) {
            wp_send_json_error(sprintf(__('El atributo con ID %d no existe en Slave.', 'chilevapo-andesvapor-connector'), $slave_attribute_id));
        }

        // Verificar si ya están conectados
        if ($connector->are_attributes_connected($master_attribute_id, $slave_attribute_id)) {
            wp_send_json_error(__('Estos atributos ya están conectados.', 'chilevapo-andesvapor-connector'));
        }

        $result = $connector->connect_attributes($master_attribute_id, $slave_attribute_id);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Desconectar atributos
     */
    public function disconnect_attributes() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $master_attribute_id = intval($_POST['master_attribute_id']);
        $slave_attribute_id = intval($_POST['slave_attribute_id']);

        if ($master_attribute_id <= 0 || $slave_attribute_id <= 0) {
            wp_send_json_error(__('IDs de atributos inválidos.', 'chilevapo-andesvapor-connector'));
        }

        $connector = CVAV_Connector();
        $result = $connector->disconnect_attributes($master_attribute_id, $slave_attribute_id);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Obtener atributos de un sitio
     */
    public function get_site_attributes() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $site_id = intval($_POST['site_id']);
        
        if ($site_id <= 0) {
            wp_send_json_error(__('ID de sitio inválido.', 'chilevapo-andesvapor-connector'));
        }

        $connector = CVAV_Connector();
        $attributes = $connector->get_site_attributes($site_id);

        wp_send_json_success($attributes);
    }

    /**
     * Obtener conexiones existentes
     */
    public function get_existing_connections() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $connector = CVAV_Connector();
        $connections = $connector->get_existing_connections();

        wp_send_json_success($connections);
    }

    /**
     * Obtener conexiones para un sitio específico
     */
    public function get_site_connections() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $slave_site_id = intval($_POST['slave_site_id']);
        
        if ($slave_site_id <= 0) {
            wp_send_json_error(__('ID de sitio inválido.', 'chilevapo-andesvapor-connector'));
        }

        $connector = CVAV_Connector();
        
        // Actualizar temporalmente la configuración para obtener las conexiones
        $settings = $connector->get_settings();
        $settings['slave_site_id'] = $slave_site_id;
        $connector->update_settings($settings);
        
        $connections = $connector->get_existing_connections();
        
        wp_send_json_success($connections);
    }

    /**
     * Actualizar conexiones
     */
    public function refresh_connections() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $connector = CVAV_Connector();
        $connections = $connector->get_existing_connections();

        wp_send_json_success(array(
            'connections' => $connections,
            'message' => __('Lista de conexiones actualizada.', 'chilevapo-andesvapor-connector')
        ));
    }

    /**
     * Verificar si dos atributos están conectados
     */
    public function check_connection_status() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $master_attribute_id = intval($_POST['master_attribute_id']);
        $slave_attribute_id = intval($_POST['slave_attribute_id']);

        if ($master_attribute_id <= 0 || $slave_attribute_id <= 0) {
            wp_send_json_error(__('IDs de atributos inválidos.', 'chilevapo-andesvapor-connector'));
        }

        $connector = CVAV_Connector();
        $is_connected = $connector->are_attributes_connected($master_attribute_id, $slave_attribute_id);

        wp_send_json_success(array(
            'connected' => $is_connected
        ));
    }

    /**
     * Obtener estadísticas de conexiones
     */
    public function get_connection_stats() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $connector = CVAV_Connector();
        $connections = $connector->get_existing_connections();
        $sites = $connector->get_configured_sites();

        $stats = array(
            'total_connections' => count($connections),
            'master_attributes' => count($connector->get_site_attributes($sites['master'])),
            'slave_attributes' => count($connector->get_site_attributes($sites['slave']))
        );

        wp_send_json_success($stats);
    }

    /**
     * Buscar atributos por nombre
     */
    public function search_attributes() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $site_id = intval($_POST['site_id']);
        $search_term = sanitize_text_field($_POST['search_term'] ?? '');
        
        if ($site_id <= 0) {
            wp_send_json_error(__('ID de sitio inválido.', 'chilevapo-andesvapor-connector'));
        }

        $connector = CVAV_Connector();
        $attributes = $connector->get_site_attributes($site_id);

        // Filtrar por término de búsqueda
        if (!empty($search_term)) {
            $filtered_attributes = array();
            foreach ($attributes as $attribute) {
                if (stripos($attribute['name'], $search_term) !== false || 
                    stripos($attribute['slug'], $search_term) !== false) {
                    $filtered_attributes[] = $attribute;
                }
            }
            $attributes = $filtered_attributes;
        }

        wp_send_json_success($attributes);
    }

    /**
     * Conectar atributos automáticamente por nombre
     */
    public function auto_connect_by_name() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $connector = CVAV_Connector();
        $sites = $connector->get_configured_sites();

        $master_attributes = $connector->get_site_attributes($sites['master']);
        $slave_attributes = $connector->get_site_attributes($sites['slave']);

        $connections_made = 0;
        $errors = array();

        foreach ($master_attributes as $master_attribute) {
            foreach ($slave_attributes as $slave_attribute) {
                if ($master_attribute['name'] === $slave_attribute['name']) {
                    // Verificar si ya están conectados
                    if (!$connector->are_attributes_connected($master_attribute['id'], $slave_attribute['id'])) {
                        $result = $connector->connect_attributes($master_attribute['id'], $slave_attribute['id']);
                        if ($result['success']) {
                            $connections_made++;
                        } else {
                            $errors[] = $result['message'];
                        }
                    }
                }
            }
        }

        wp_send_json_success(array(
            'connections_made' => $connections_made,
            'errors' => $errors,
            'message' => sprintf(
                __('Se conectaron %d pares de atributos automáticamente.', 'chilevapo-andesvapor-connector'),
                $connections_made
            )
        ));
    }

    /**
     * Validar conexión antes de conectar
     */
    public function validate_connection() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $master_attribute_id = intval($_POST['master_attribute_id'] ?? 0);
        $slave_attribute_id = intval($_POST['slave_attribute_id'] ?? 0);

        $errors = array();

        // Validar atributo de Master
        if ($master_attribute_id <= 0) {
            $errors[] = __('Debe seleccionar un atributo de Master.', 'chilevapo-andesvapor-connector');
        }

        // Validar atributo de Slave
        if ($slave_attribute_id <= 0) {
            $errors[] = __('Debe seleccionar un atributo de Slave.', 'chilevapo-andesvapor-connector');
        }

        // Validar que no sean el mismo
        if ($master_attribute_id === $slave_attribute_id) {
            $errors[] = __('Los atributos de Master y Slave deben ser diferentes.', 'chilevapo-andesvapor-connector');
        }

        // Verificar si ya están conectados
        if (empty($errors)) {
            $connector = CVAV_Connector();
            if ($connector->are_attributes_connected($master_attribute_id, $slave_attribute_id)) {
                $errors[] = __('Estos atributos ya están conectados.', 'chilevapo-andesvapor-connector');
            }
        }

        if (empty($errors)) {
            wp_send_json_success(__('Datos válidos.', 'chilevapo-andesvapor-connector'));
        } else {
            wp_send_json_error(array(
                'message' => __('Datos inválidos.', 'chilevapo-andesvapor-connector'),
                'errors' => $errors
            ));
        }
    }

    /**
     * Debug de conexión de atributos
     */
    public function debug_connection() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $master_attribute_id = intval($_POST['master_attribute_id'] ?? 0);
        $slave_attribute_id = intval($_POST['slave_attribute_id'] ?? 0);

        $connector = CVAV_Connector();
        $sites = $connector->get_configured_sites();
        
        $debug_info = array(
            'input_data' => array(
                'master_attribute_id' => $master_attribute_id,
                'slave_attribute_id' => $slave_attribute_id,
                'post_data' => $_POST
            ),
            'configured_sites' => $sites,
            'current_site_id' => get_current_blog_id(),
            'is_configured' => $connector->is_configured()
        );

        if (!empty($sites['master'])) {
            $master_attributes = $connector->get_site_attributes($sites['master']);
            $debug_info['master_attributes'] = $master_attributes;
            
            $master_attribute_exists = false;
            foreach ($master_attributes as $attr) {
                if ($attr['id'] == $master_attribute_id) {
                    $master_attribute_exists = true;
                    $debug_info['master_attribute_found'] = $attr;
                    break;
                }
            }
            $debug_info['master_attribute_exists'] = $master_attribute_exists;
        }

        if (!empty($sites['slave'])) {
            $slave_attributes = $connector->get_site_attributes($sites['slave']);
            $debug_info['slave_attributes'] = $slave_attributes;
            
            $slave_attribute_exists = false;
            foreach ($slave_attributes as $attr) {
                if ($attr['id'] == $slave_attribute_id) {
                    $slave_attribute_exists = true;
                    $debug_info['slave_attribute_found'] = $attr;
                    break;
                }
            }
            $debug_info['slave_attribute_exists'] = $slave_attribute_exists;
        }

        // Verificar si ya están conectados
        if ($master_attribute_id > 0 && $slave_attribute_id > 0) {
            $debug_info['already_connected'] = $connector->are_attributes_connected($master_attribute_id, $slave_attribute_id);
        }

        wp_send_json_success($debug_info);
    }

    /**
     * Test de funcionamiento con un sitio específico
     */
    public function test_site() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $test_site_id = intval($_POST['test_site_id'] ?? 0);
        
        if ($test_site_id <= 0) {
            wp_send_json_error(__('ID de sitio inválido.', 'chilevapo-andesvapor-connector'));
        }

        $connector = CVAV_Connector();
        
        // Obtener configuración actual
        $current_settings = $connector->get_settings();
        
        // Configurar temporalmente con el sitio de test
        $test_settings = $current_settings;
        $test_settings['slave_site_id'] = $test_site_id;
        $connector->update_settings($test_settings);
        
        // Obtener información de test
        $test_info = array(
            'site_id' => $test_site_id,
            'configured_sites' => $connector->get_configured_sites(),
            'is_configured' => $connector->is_configured(),
            'existing_connections' => $connector->get_existing_connections(),
            'master_attributes' => $connector->get_site_attributes(get_current_blog_id()),
            'target_site_attributes' => $connector->get_site_attributes($test_site_id)
        );
        
        // Restaurar configuración original
        $connector->update_settings($current_settings);
        
        wp_send_json_success($test_info);
    }
} 