<?php
/**
 * Clase de configuración del plugin
 */

defined('ABSPATH') || exit;

class CVAV_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting('cvav_connector_settings', 'cvav_connector_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
    }

    /**
     * Sanitizar configuraciones
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        // Sitios
        $sanitized['master_site_id'] = sanitize_text_field($input['master_site_id'] ?? '');
        $sanitized['slave_site_id'] = sanitize_text_field($input['slave_site_id'] ?? '');

        // Configuración de sincronización
        $sanitized['auto_sync'] = isset($input['auto_sync']);
        $sanitized['sync_interval'] = sanitize_text_field($input['sync_interval'] ?? 'hourly');
        $sanitized['sync_batch_size'] = intval($input['sync_batch_size'] ?? 50);
        $sanitized['max_retries'] = intval($input['max_retries'] ?? 3);

        // Notificaciones
        $sanitized['notification_email'] = sanitize_email($input['notification_email'] ?? '');
        $sanitized['log_level'] = sanitize_text_field($input['log_level'] ?? 'info');

        return $sanitized;
    }

    /**
     * Obtener configuración por defecto
     */
    public function get_default_settings() {
        return array(
            'auto_sync' => false,
            'sync_interval' => 'hourly',
            'log_level' => 'info',
            'master_site_id' => '',
            'slave_site_id' => '',
            'notification_email' => get_option('admin_email'),
            'sync_batch_size' => 50,
            'max_retries' => 3
        );
    }

    /**
     * Validar configuración
     */
    public function validate_settings($settings) {
        $errors = array();

        // Validar sitios
        if (empty($settings['master_site_id'])) {
            $errors[] = __('El sitio Master es requerido.', 'chilevapo-andesvapor-connector');
        }

        if (empty($settings['slave_site_id'])) {
            $errors[] = __('El sitio Slave es requerido.', 'chilevapo-andesvapor-connector');
        }

        if (!empty($settings['master_site_id']) && !empty($settings['slave_site_id'])) {
            if ($settings['master_site_id'] === $settings['slave_site_id']) {
                $errors[] = __('Los sitios Master y Slave deben ser diferentes.', 'chilevapo-andesvapor-connector');
            }
        }

        // Validar email de notificación
        if (!empty($settings['notification_email']) && !is_email($settings['notification_email'])) {
            $errors[] = __('El email de notificación no es válido.', 'chilevapo-andesvapor-connector');
        }

        // Validar tamaño del lote
        if ($settings['sync_batch_size'] < 10 || $settings['sync_batch_size'] > 500) {
            $errors[] = __('El tamaño del lote debe estar entre 10 y 500.', 'chilevapo-andesvapor-connector');
        }

        // Validar número de reintentos
        if ($settings['max_retries'] < 1 || $settings['max_retries'] > 10) {
            $errors[] = __('El número de reintentos debe estar entre 1 y 10.', 'chilevapo-andesvapor-connector');
        }

        return $errors;
    }

    /**
     * Obtener intervalos de sincronización disponibles
     */
    public function get_sync_intervals() {
        return array(
            'hourly' => __('Cada hora', 'chilevapo-andesvapor-connector'),
            'twicedaily' => __('Dos veces al día', 'chilevapo-andesvapor-connector'),
            'daily' => __('Diario', 'chilevapo-andesvapor-connector')
        );
    }

    /**
     * Obtener niveles de log disponibles
     */
    public function get_log_levels() {
        return array(
            'debug' => __('Debug', 'chilevapo-andesvapor-connector'),
            'info' => __('Info', 'chilevapo-andesvapor-connector'),
            'warning' => __('Warning', 'chilevapo-andesvapor-connector'),
            'error' => __('Error', 'chilevapo-andesvapor-connector')
        );
    }

    /**
     * Obtener sitios disponibles
     */
    public function get_available_sites() {
        if (function_exists('wc_multistore_get_sites')) {
            return wc_multistore_get_sites();
        }
        return array();
    }

    /**
     * Obtener atributos de un sitio específico
     */
    public function get_site_attributes($site_id) {
        $attributes = array();

        if (is_multisite()) {
            $original_blog_id = get_current_blog_id();
            switch_to_blog($site_id);

            // Obtener atributos usando WooCommerce
            if (function_exists('wc_get_attribute_taxonomies')) {
                $attribute_taxonomies = wc_get_attribute_taxonomies();
                
                foreach ($attribute_taxonomies as $taxonomy) {
                    $attributes[] = array(
                        'id' => $taxonomy->attribute_id,
                        'name' => $taxonomy->attribute_label,
                        'slug' => $taxonomy->attribute_name,
                        'type' => $taxonomy->attribute_type
                    );
                }
            }

            restore_current_blog();
        }

        return $attributes;
    }

    /**
     * Crear conexión de atributos
     */
    public function create_connection($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'cvav_attribute_connections';

        // Validar datos
        $errors = $this->validate_connection_data($data);
        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors);
        }

        // Insertar conexión
        $result = $wpdb->insert(
            $table,
            array(
                'master_attribute_id' => intval($data['master_attribute_id']),
                'slave_attribute_id' => intval($data['slave_attribute_id']),
                'master_attribute_name' => sanitize_text_field($data['master_attribute_name']),
                'slave_attribute_name' => sanitize_text_field($data['slave_attribute_name']),
                'sync_direction' => sanitize_text_field($data['sync_direction']),
                'is_active' => isset($data['is_active']) ? 1 : 0
            ),
            array('%d', '%d', '%s', '%s', '%s', '%d')
        );

        if ($result === false) {
            return array('success' => false, 'errors' => array(__('Error al crear la conexión.', 'chilevapo-andesvapor-connector')));
        }

        return array('success' => true, 'id' => $wpdb->insert_id);
    }

    /**
     * Actualizar conexión de atributos
     */
    public function update_connection($connection_id, $data) {
        global $wpdb;

        $table = $wpdb->prefix . 'cvav_attribute_connections';

        // Validar datos
        $errors = $this->validate_connection_data($data);
        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors);
        }

        // Actualizar conexión
        $result = $wpdb->update(
            $table,
            array(
                'master_attribute_id' => intval($data['master_attribute_id']),
                'slave_attribute_id' => intval($data['slave_attribute_id']),
                'master_attribute_name' => sanitize_text_field($data['master_attribute_name']),
                'slave_attribute_name' => sanitize_text_field($data['slave_attribute_name']),
                'sync_direction' => sanitize_text_field($data['sync_direction']),
                'is_active' => isset($data['is_active']) ? 1 : 0
            ),
            array('id' => $connection_id),
            array('%d', '%d', '%s', '%s', '%s', '%d'),
            array('%d')
        );

        if ($result === false) {
            return array('success' => false, 'errors' => array(__('Error al actualizar la conexión.', 'chilevapo-andesvapor-connector')));
        }

        return array('success' => true);
    }

    /**
     * Eliminar conexión de atributos
     */
    public function delete_connection($connection_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'cvav_attribute_connections';

        $result = $wpdb->delete(
            $table,
            array('id' => $connection_id),
            array('%d')
        );

        if ($result === false) {
            return array('success' => false, 'errors' => array(__('Error al eliminar la conexión.', 'chilevapo-andesvapor-connector')));
        }

        return array('success' => true);
    }

    /**
     * Obtener conexión por ID
     */
    public function get_connection($connection_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'cvav_attribute_connections';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $connection_id));
    }

    /**
     * Obtener todas las conexiones
     */
    public function get_all_connections() {
        global $wpdb;

        $table = $wpdb->prefix . 'cvav_attribute_connections';
        
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }

    /**
     * Validar datos de conexión
     */
    private function validate_connection_data($data) {
        $errors = array();

        // Validar atributo de Chile Vapo
        if (empty($data['master_attribute_id'])) {
            $errors[] = __('El atributo de Master es requerido.', 'chilevapo-andesvapor-connector');
        }

        // Validar atributo de Andes Vapor
        if (empty($data['slave_attribute_id'])) {
            $errors[] = __('El atributo de Slave es requerido.', 'chilevapo-andesvapor-connector');
        }

        // Validar que no sean el mismo atributo
        if (!empty($data['master_attribute_id']) && !empty($data['slave_attribute_id'])) {
            if ($data['master_attribute_id'] == $data['slave_attribute_id']) {
                $errors[] = __('Los atributos de Master y Slave deben ser diferentes.', 'chilevapo-andesvapor-connector');
            }
        }

        // Validar dirección de sincronización
        $valid_directions = array('master_to_slave', 'slave_to_master', 'bidirectional');
        if (!in_array($data['sync_direction'], $valid_directions)) {
            $errors[] = __('La dirección de sincronización no es válida.', 'chilevapo-andesvapor-connector');
        }

        return $errors;
    }

    /**
     * Obtener estadísticas de sincronización
     */
    public function get_sync_stats() {
        global $wpdb;

        $connections_table = $wpdb->prefix . 'cvav_attribute_connections';
        $log_table = $wpdb->prefix . 'cvav_sync_log';

        $stats = array(
            'total_connections' => $wpdb->get_var("SELECT COUNT(*) FROM $connections_table"),
            'active_connections' => $wpdb->get_var("SELECT COUNT(*) FROM $connections_table WHERE is_active = 1"),
            'inactive_connections' => $wpdb->get_var("SELECT COUNT(*) FROM $connections_table WHERE is_active = 0"),
            'total_syncs' => $wpdb->get_var("SELECT COUNT(*) FROM $log_table"),
            'successful_syncs' => $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE status = 'success'"),
            'failed_syncs' => $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE status = 'error'"),
            'last_sync' => $wpdb->get_var("SELECT MAX(created_at) FROM $log_table WHERE status = 'success'")
        );

        return $stats;
    }

    /**
     * Limpiar logs antiguos
     */
    public function cleanup_old_logs($days = 30) {
        global $wpdb;

        $table = $wpdb->prefix . 'cvav_sync_log';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < %s",
            $cutoff_date
        ));

        return $deleted;
    }

    /**
     * Exportar configuración
     */
    public function export_settings() {
        $connector = CVAV_Connector();
        $settings = $connector->get_settings();
        $connections = $this->get_all_connections();

        $export_data = array(
            'settings' => $settings,
            'connections' => $connections,
            'export_date' => current_time('mysql'),
            'version' => CVAV_CONNECTOR_VERSION
        );

        return $export_data;
    }

    /**
     * Importar configuración
     */
    public function import_settings($import_data) {
        $errors = array();

        // Validar datos de importación
        if (!isset($import_data['version']) || version_compare($import_data['version'], '1.0.0', '<')) {
            $errors[] = __('La versión del archivo de importación no es compatible.', 'chilevapo-andesvapor-connector');
        }

        if (empty($import_data['settings'])) {
            $errors[] = __('Los datos de configuración son requeridos.', 'chilevapo-andesvapor-connector');
        }

        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors);
        }

        // Importar configuración
        $connector = CVAV_Connector();
        $connector->update_settings($import_data['settings']);

        // Importar conexiones
        if (!empty($import_data['connections'])) {
            global $wpdb;
            $table = $wpdb->prefix . 'cvav_attribute_connections';

            // Limpiar conexiones existentes
            $wpdb->query("TRUNCATE TABLE $table");

            // Insertar nuevas conexiones
            foreach ($import_data['connections'] as $connection) {
                $wpdb->insert(
                    $table,
                    array(
                        'master_attribute_id' => $connection->master_attribute_id,
                        'slave_attribute_id' => $connection->slave_attribute_id,
                        'master_attribute_name' => $connection->master_attribute_name,
                        'slave_attribute_name' => $connection->slave_attribute_name,
                        'sync_direction' => $connection->sync_direction,
                        'is_active' => $connection->is_active,
                        'last_sync' => $connection->last_sync,
                        'created_at' => $connection->created_at,
                        'updated_at' => $connection->updated_at
                    ),
                    array('%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
                );
            }
        }

        return array('success' => true);
    }
} 