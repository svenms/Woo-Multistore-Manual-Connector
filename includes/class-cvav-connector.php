<?php
/**
 * Clase principal del conector
 */

defined('ABSPATH') || exit;

class CVAV_Connector {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_cvav_sync_connection', array($this, 'ajax_sync_connection'));
        add_action('wp_ajax_cvav_sync_all', array($this, 'ajax_sync_all'));
        add_action('cvav_scheduled_sync', array($this, 'scheduled_sync'));
    }

    /**
     * Inicializar
     */
    public function init() {
        $connector = CVAV_Connector();
        $settings = $connector->get_settings();

        // Programar sincronización automática si está habilitada
        if (($settings['auto_sync'] ?? false) && !wp_next_scheduled('cvav_scheduled_sync')) {
            wp_schedule_event(time(), $settings['sync_interval'] ?? 'hourly', 'cvav_scheduled_sync');
        } elseif (!($settings['auto_sync'] ?? false) && wp_next_scheduled('cvav_scheduled_sync')) {
            wp_clear_scheduled_hook('cvav_scheduled_sync');
        }
    }

    /**
     * Sincronizar conexión específica
     */
    public function sync_connection($connection_id, $direction = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'cvav_attribute_connections';
        $connection = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $connection_id));

        if (!$connection) {
            return $this->log_sync($connection_id, 'error', 'Conexión no encontrada');
        }

        if (!$connection->is_active) {
            return $this->log_sync($connection_id, 'warning', 'Conexión inactiva');
        }

        $connector = CVAV_Connector();
        $sites = $connector->get_configured_sites();

        // Determinar dirección de sincronización
        if (!$direction) {
            $direction = $connection->sync_direction;
        }

        try {
            switch ($direction) {
                case 'master_to_slave':
                    $result = $this->sync_master_to_slave($connection, $sites);
                    break;
                case 'slave_to_master':
                    $result = $this->sync_slave_to_master($connection, $sites);
                    break;
                case 'bidirectional':
                    $result1 = $this->sync_master_to_slave($connection, $sites);
                    $result2 = $this->sync_slave_to_master($connection, $sites);
                    $result = $result1 && $result2;
                    break;
                default:
                    return $this->log_sync($connection_id, 'error', 'Dirección de sincronización inválida');
            }

            if ($result) {
                // Actualizar última sincronización
                $wpdb->update(
                    $table,
                    array('last_sync' => current_time('mysql')),
                    array('id' => $connection_id)
                );

                $this->log_sync($connection_id, 'success', 'Sincronización completada exitosamente');
                return true;
            } else {
                $this->log_sync($connection_id, 'error', 'Error en la sincronización');
                return false;
            }

        } catch (Exception $e) {
            $this->log_sync($connection_id, 'error', 'Excepción: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sincronizar de Master a Slave
     */
    private function sync_master_to_slave($connection, $sites) {
        // Obtener atributo de Master
        $master_attribute = $this->get_attribute_from_site($connection->master_attribute_id, $sites['master']);
        
        if (!$master_attribute) {
            $this->log_sync($connection->id, 'error', 'No se pudo obtener el atributo de Master');
            return false;
        }

        // Sincronizar a Slave
        $result = $this->sync_attribute_to_site($master_attribute, $sites['slave'], $connection->slave_attribute_id);
        
        return $result;
    }

    /**
     * Sincronizar de Slave a Master
     */
    private function sync_slave_to_master($connection, $sites) {
        // Obtener atributo de Slave
        $slave_attribute = $this->get_attribute_from_site($connection->slave_attribute_id, $sites['slave']);
        
        if (!$slave_attribute) {
            $this->log_sync($connection->id, 'error', 'No se pudo obtener el atributo de Slave');
            return false;
        }

        // Sincronizar a Master
        $result = $this->sync_attribute_to_site($slave_attribute, $sites['master'], $connection->master_attribute_id);
        
        return $result;
    }

    /**
     * Obtener atributo de un sitio específico
     */
    private function get_attribute_from_site($attribute_id, $site_id) {
        // Cambiar al sitio específico
        $original_blog_id = get_current_blog_id();
        
        if (is_multisite()) {
            switch_to_blog($site_id);
        }

        // Obtener el atributo usando WooCommerce Multistore
        $attribute = null;
        
        if (function_exists('wc_get_attribute')) {
            $attribute = wc_get_attribute($attribute_id);
        }

        // Restaurar al sitio original
        if (is_multisite()) {
            restore_current_blog();
        }

        return $attribute;
    }

    /**
     * Sincronizar atributo a un sitio específico
     */
    private function sync_attribute_to_site($source_attribute, $target_site_id, $target_attribute_id) {
        // Cambiar al sitio objetivo
        $original_blog_id = get_current_blog_id();
        
        if (is_multisite()) {
            switch_to_blog($target_site_id);
        }

        try {
            // Preparar datos del atributo para sincronización
            $attribute_data = $this->prepare_attribute_data($source_attribute);
            
            // Usar WooCommerce Multistore para sincronizar
            if (class_exists('WC_Multistore_Product_Attribute_Child')) {
                $child_attribute = new WC_Multistore_Product_Attribute_Child($attribute_data);
                $result = $child_attribute->save();
                
                if ($result) {
                    // Actualizar la relación en la tabla de WooCommerce Multistore
                    $this->update_multistore_relationship($source_attribute->get_id(), $result->get_id());
                }
            } else {
                // Fallback: actualizar directamente
                $result = $this->update_attribute_directly($target_attribute_id, $attribute_data);
            }

            // Restaurar al sitio original
            if (is_multisite()) {
                restore_current_blog();
            }

            return $result;

        } catch (Exception $e) {
            // Restaurar al sitio original en caso de error
            if (is_multisite()) {
                restore_current_blog();
            }
            throw $e;
        }
    }

    /**
     * Preparar datos del atributo para sincronización
     */
    private function prepare_attribute_data($attribute) {
        $data = array(
            'id' => $attribute->get_id(),
            'name' => $attribute->get_name(),
            'slug' => $attribute->get_name(),
            'variation' => $attribute->get_variation(),
            'taxonomy' => $attribute->is_taxonomy(),
            'position' => $attribute->get_position(),
            'visible' => $attribute->get_visible(),
            'terms' => array()
        );

        // Obtener términos si es una taxonomía
        if ($attribute->is_taxonomy()) {
            $terms = $attribute->get_terms();
            foreach ($terms as $term) {
                $data['terms'][] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description
                );
            }
        } else {
            // Para atributos personalizados
            $data['terms'] = $attribute->get_options();
        }

        return $data;
    }

    /**
     * Actualizar atributo directamente
     */
    private function update_attribute_directly($attribute_id, $data) {
        global $wpdb;

        if ($attribute_id > 0) {
            // Actualizar atributo existente
            $result = wc_update_attribute($attribute_id, array(
                'name' => $data['name'],
                'slug' => $data['slug'],
                'type' => $data['taxonomy'] ? 'select' : 'text',
                'order_by' => 'menu_order',
                'has_archives' => true
            ));

            // Actualizar términos si es una taxonomía
            if ($data['taxonomy'] && !empty($data['terms'])) {
                $this->sync_attribute_terms($attribute_id, $data['terms']);
            }

            return $result;
        } else {
            // Crear nuevo atributo
            $attribute_id = wc_create_attribute(array(
                'name' => $data['name'],
                'slug' => $data['slug'],
                'type' => $data['taxonomy'] ? 'select' : 'text',
                'order_by' => 'menu_order',
                'has_archives' => true
            ));

            if ($attribute_id && $data['taxonomy'] && !empty($data['terms'])) {
                $this->sync_attribute_terms($attribute_id, $data['terms']);
            }

            return $attribute_id;
        }
    }

    /**
     * Sincronizar términos del atributo
     */
    private function sync_attribute_terms($attribute_id, $terms) {
        $taxonomy = wc_attribute_taxonomy_name_by_id($attribute_id);
        
        if (!$taxonomy) {
            return false;
        }

        foreach ($terms as $term_data) {
            if (is_array($term_data)) {
                $term_name = $term_data['name'];
                $term_slug = $term_data['slug'];
                $term_description = $term_data['description'] ?? '';
            } else {
                $term_name = $term_data;
                $term_slug = sanitize_title($term_data);
                $term_description = '';
            }

            // Verificar si el término ya existe
            $existing_term = get_term_by('slug', $term_slug, $taxonomy);
            
            if (!$existing_term) {
                // Crear nuevo término
                $result = wp_insert_term($term_name, $taxonomy, array(
                    'slug' => $term_slug,
                    'description' => $term_description
                ));
            } else {
                // Actualizar término existente
                $result = wp_update_term($existing_term->term_id, $taxonomy, array(
                    'name' => $term_name,
                    'description' => $term_description
                ));
            }
        }

        return true;
    }

    /**
     * Actualizar relación en WooCommerce Multistore
     */
    private function update_multistore_relationship($master_attribute_id, $child_attribute_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'woo_multistore_attributes_relationships';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
            $wpdb->replace(
                $table,
                array(
                    'attribute_id' => $master_attribute_id,
                    'child_attribute_id' => $child_attribute_id
                ),
                array('%d', '%d')
            );
        }
    }

    /**
     * Registrar log de sincronización
     */
    private function log_sync($connection_id, $status, $message, $data = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'cvav_sync_log';
        
        $wpdb->insert(
            $table,
            array(
                'connection_id' => $connection_id,
                'sync_direction' => 'manual',
                'sync_type' => 'attribute_sync',
                'status' => $status,
                'message' => $message,
                'data' => $data ? json_encode($data) : null
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        return $wpdb->insert_id;
    }

    /**
     * AJAX: Sincronizar conexión específica
     */
    public function ajax_sync_connection() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        $connection_id = intval($_POST['connection_id']);
        $direction = sanitize_text_field($_POST['direction'] ?? '');

        $result = $this->sync_connection($connection_id, $direction);

        wp_send_json(array(
            'success' => $result,
            'message' => $result ? 
                __('Sincronización completada exitosamente.', 'chilevapo-andesvapor-connector') : 
                __('Error en la sincronización.', 'chilevapo-andesvapor-connector')
        ));
    }

    /**
     * AJAX: Sincronizar todas las conexiones
     */
    public function ajax_sync_all() {
        check_ajax_referer('cvav_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'chilevapo-andesvapor-connector'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cvav_attribute_connections';
        $connections = $wpdb->get_results("SELECT id FROM $table WHERE is_active = 1");

        $success_count = 0;
        $error_count = 0;

        foreach ($connections as $connection) {
            $result = $this->sync_connection($connection->id);
            if ($result) {
                $success_count++;
            } else {
                $error_count++;
            }
        }

        wp_send_json(array(
            'success' => true,
            'message' => sprintf(
                __('Sincronización completada. %d exitosas, %d errores.', 'chilevapo-andesvapor-connector'),
                $success_count,
                $error_count
            ),
            'success_count' => $success_count,
            'error_count' => $error_count
        ));
    }

    /**
     * Sincronización programada
     */
    public function scheduled_sync() {
        global $wpdb;
        $table = $wpdb->prefix . 'cvav_attribute_connections';
        $connections = $wpdb->get_results("SELECT id FROM $table WHERE is_active = 1");

        foreach ($connections as $connection) {
            $this->sync_connection($connection->id);
        }

        // Enviar notificación por email si está configurada
        $connector = CVAV_Connector();
        $settings = $connector->get_settings();
        
        if (!empty($settings['notification_email'])) {
            $this->send_sync_notification($settings['notification_email'], count($connections));
        }
    }

    /**
     * Enviar notificación de sincronización
     */
    private function send_sync_notification($email, $connection_count) {
        $subject = sprintf(__('Sincronización de Atributos - %s', 'chilevapo-andesvapor-connector'), get_bloginfo('name'));
        
        $message = sprintf(
            __('La sincronización automática de atributos se ha completado. Se procesaron %d conexiones.', 'chilevapo-andesvapor-connector'),
            $connection_count
        );

        wp_mail($email, $subject, $message);
    }
} 