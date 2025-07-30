<?php
/**
 * Archivo de desinstalación del plugin
 * 
 * Este archivo se ejecuta cuando el plugin es desinstalado
 */

// Si no se llama desde WordPress, salir
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Verificar permisos
if (!current_user_can('activate_plugins')) {
    return;
}

/**
 * Limpiar todas las opciones del plugin
 */
function cvav_cleanup_options() {
    // Eliminar opciones principales
    delete_option('cvav_connector_settings');
    delete_option('cvav_connector_version');
    
    // Eliminar opciones de configuración específicas
            delete_option('cvav_master_site_id');
            delete_option('cvav_slave_site_id');
    delete_option('cvav_auto_connect_new_attributes');
    delete_option('cvav_notification_email');
    
    // Eliminar transientes relacionados
    delete_transient('cvav_available_sites');
            delete_transient('cvav_master_attributes');
            delete_transient('cvav_slave_attributes');
    delete_transient('cvav_existing_connections');
}

/**
 * Limpiar cron jobs
 */
function cvav_cleanup_cron() {
    // Eliminar cron jobs si existen
    wp_clear_scheduled_hook('cvav_auto_connect_attributes');
    wp_clear_scheduled_hook('cvav_cleanup_old_logs');
}

/**
 * Limpiar caché
 */
function cvav_cleanup_cache() {
    // Limpiar caché de WordPress
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Limpiar caché de WooCommerce si está disponible
    if (function_exists('wc_cache_helper_get_transient_version')) {
        delete_transient('wc_cache_helper_get_transient_version');
    }
    
    // Limpiar caché de objetos
    wp_cache_flush_group('cvav_connector');
}

/**
 * Limpiar logs y archivos temporales
 */
function cvav_cleanup_files() {
    $upload_dir = wp_upload_dir();
    $plugin_log_dir = $upload_dir['basedir'] . '/cvav-connector-logs/';
    
    // Eliminar directorio de logs si existe
    if (is_dir($plugin_log_dir)) {
        $files = glob($plugin_log_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($plugin_log_dir);
    }
}

/**
 * Limpiar hooks y filtros
 */
function cvav_cleanup_hooks() {
    // Remover hooks específicos del plugin
    remove_action('admin_menu', 'cvav_add_admin_menu');
    remove_action('admin_enqueue_scripts', 'cvav_enqueue_admin_scripts');
    remove_action('admin_notices', 'cvav_admin_notices');
    
    // Remover filtros
    remove_filter('plugin_action_links_woo-multisite-manual-connector/woo-multisite-manual-connector.php', 'cvav_add_action_links');
}

/**
 * Limpiar metadatos de usuarios
 */
function cvav_cleanup_user_meta() {
    global $wpdb;
    
    // Eliminar metadatos de usuarios relacionados con el plugin
    $wpdb->delete(
        $wpdb->usermeta,
        array('meta_key' => 'cvav_connector_preferences')
    );
}

/**
 * Limpiar opciones de red (multisite)
 */
function cvav_cleanup_network_options() {
    if (is_multisite()) {
        delete_site_option('cvav_connector_network_settings');
        delete_site_option('cvav_connector_network_version');
    }
}

/**
 * Limpiar tablas personalizadas (si existen)
 */
function cvav_cleanup_tables() {
    global $wpdb;
    
    // Lista de tablas que podrían haber sido creadas por el plugin
    $tables = array(
        $wpdb->prefix . 'cvav_attribute_connections',
        $wpdb->prefix . 'cvav_sync_log',
        $wpdb->prefix . 'cvav_connection_history'
    );
    
    foreach ($tables as $table) {
        // Verificar si la tabla existe antes de intentar eliminarla
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}

/**
 * Limpiar opciones de WooCommerce relacionadas
 */
function cvav_cleanup_woocommerce_options() {
    // Eliminar opciones específicas de WooCommerce relacionadas con el plugin
    delete_option('cvav_woocommerce_integration_settings');
    delete_option('cvav_woocommerce_multistore_settings');
    
    // Limpiar transientes de WooCommerce relacionados
    delete_transient('cvav_woocommerce_attributes_cache');
    delete_transient('cvav_woocommerce_sites_cache');
}

/**
 * Limpiar logs de errores
 */
function cvav_cleanup_error_logs() {
    $log_file = WP_CONTENT_DIR . '/debug-cvav-connector.log';
    
    if (file_exists($log_file)) {
        unlink($log_file);
    }
}

/**
 * Función principal de limpieza
 */
function cvav_uninstall_plugin() {
    // Ejecutar todas las funciones de limpieza
    cvav_cleanup_options();
    cvav_cleanup_cron();
    cvav_cleanup_cache();
    cvav_cleanup_files();
    cvav_cleanup_hooks();
    cvav_cleanup_user_meta();
    cvav_cleanup_network_options();
    cvav_cleanup_tables();
    cvav_cleanup_woocommerce_options();
    cvav_cleanup_error_logs();
    
    // Registrar la desinstalación
    if (function_exists('error_log')) {
        error_log('Woo Multistore Manual Connector plugin uninstalled at ' . date('Y-m-d H:i:s'));
    }
}

// Ejecutar la limpieza
cvav_uninstall_plugin(); 