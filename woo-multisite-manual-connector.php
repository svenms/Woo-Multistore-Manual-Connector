<?php
/**
 * Plugin Name: Woo Multisite Manual Connector
 * Plugin URI: https://woomultisiteconnector.com
 * Description: Conecta productos y atributos entre sitios master y slave usando WooCommerce Multistore.
 * Version: 1.1.1
 * Author: Woo Multisite Connector
 * Author URI: https://woomultisiteconnector.com
 * Text Domain: woo-multisite-connector
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.9.5
 * Network: true
 * Woo: 12345:342928dfsfhsf2349842374wdf4234sfd
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('CVAV_CONNECTOR_VERSION', '1.1.1');
define('CVAV_CONNECTOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CVAV_CONNECTOR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CVAV_CONNECTOR_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Declarar compatibilidad con HPOS
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Clase principal del plugin
 */
class WooMultisiteManualConnector {

    /**
     * Instancia única del plugin
     */
    private static $instance = null;

    /**
     * Configuración del plugin
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Obtener instancia única
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar el plugin
     */
    private function init() {
        // Verificar dependencias
        if (!$this->check_dependencies()) {
            return;
        }

        // Cargar archivos necesarios
        $this->load_files();

        // Configurar hooks
        $this->setup_hooks();

        // Inicializar componentes
        $this->init_components();
    }

    /**
     * Verificar dependencias
     */
    private function check_dependencies() {
        // Verificar que WooCommerce esté activo
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . 
                     __('Woo Multisite Manual Connector requiere que WooCommerce esté instalado y activado.', 'woo-multisite-connector') . 
                     '</p></div>';
            });
            return false;
        }

        // Verificar que WooCommerce Multistore esté activo
        if (!class_exists('WOO_MSTORE_MULTI_INIT')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . 
                     __('Woo Multisite Manual Connector requiere que WooCommerce Multistore esté instalado y activado.', 'woo-multisite-connector') . 
                     '</p></div>';
            });
            return false;
        }

        return true;
    }

    /**
     * Cargar archivos necesarios
     */
    private function load_files() {
        // Cargar clases principales
        require_once CVAV_CONNECTOR_PLUGIN_PATH . 'includes/class-cvav-admin.php';
        require_once CVAV_CONNECTOR_PLUGIN_PATH . 'includes/class-cvav-connector.php';
        require_once CVAV_CONNECTOR_PLUGIN_PATH . 'includes/class-cvav-settings.php';
        require_once CVAV_CONNECTOR_PLUGIN_PATH . 'includes/class-cvav-ajax.php';
        require_once CVAV_CONNECTOR_PLUGIN_PATH . 'includes/class-cvav-products.php';
    }

    /**
     * Configurar hooks
     */
    private function setup_hooks() {
        // Hook de activación
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Hook de desactivación
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Hook de desinstalación
        register_uninstall_hook(__FILE__, array('WooMultisiteManualConnector', 'uninstall'));

        // Cargar texto del plugin
        add_action('init', array($this, 'load_textdomain'));

        // Agregar enlaces de acción en la página de plugins
        add_filter('plugin_action_links_' . CVAV_CONNECTOR_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }

    /**
     * Inicializar componentes
     */
    private function init_components() {
        // Inicializar administración
        if (is_admin()) {
            new CVAV_Admin();
        }

        // Inicializar conector
        new CVAV_Connector();

        // Inicializar AJAX
        new CVAV_Ajax();

        // Inicializar productos
        new CVAV_Products();
    }

    /**
     * Activar plugin
     */
    public function activate() {
        // Configurar opciones por defecto
        $this->set_default_options();

        // Limpiar caché
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Desactivar plugin
     */
    public function deactivate() {
        // Limpiar caché
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Desinstalar plugin
     */
    public static function uninstall() {
        // Eliminar opciones
        delete_option('cvav_connector_settings');
        delete_option('cvav_connector_version');

        // Limpiar caché
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Configurar opciones por defecto
     */
    private function set_default_options() {
        $default_settings = array(
                    'master_site_id' => get_current_blog_id(), // Automáticamente el sitio actual
        'slave_site_id' => '',
            'auto_connect_new_attributes' => false,
            'auto_sync' => false,
            'sync_interval' => 'hourly',
            'notification_email' => get_option('admin_email'),
            'force_product_update' => true // Habilitar sincronización forzada por defecto
        );

        add_option('cvav_connector_settings', $default_settings);
        add_option('cvav_connector_version', CVAV_CONNECTOR_VERSION);
    }

    /**
     * Cargar dominio de texto
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'woo-multisite-connector',
            false,
            dirname(CVAV_CONNECTOR_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Agregar enlaces de acción
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=cvav-connector-settings') . '">' . 
                         __('Configuración', 'woo-multisite-connector') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Obtener configuración
     */
    public function get_settings() {
        if (!$this->settings) {
            $this->settings = get_option('cvav_connector_settings', array());
        }
        return $this->settings;
    }

    /**
     * Actualizar configuración
     */
    public function update_settings($settings) {
        $this->settings = $settings;
        $result = update_option('cvav_connector_settings', $settings);
        return $result;
    }

    /**
     * Obtener sitios configurados
     */
    public function get_configured_sites() {
        $settings = $this->get_settings();
        $sites = array(
            'master' => $settings['master_site_id'] ?? '',
            'slave' => $settings['slave_site_id'] ?? ''
        );
        return $sites;
    }

    /**
     * Verificar si el plugin está configurado correctamente
     */
    public function is_configured() {
        $sites = $this->get_configured_sites();
        
        // Master debe ser el sitio actual
        $current_site_id = get_current_blog_id();
        $master_configured = !empty($sites['master']) && $sites['master'] == $current_site_id;
        
        // Slave debe estar configurado y ser diferente a Master
        $slave_configured = !empty($sites['slave']) && $sites['slave'] != $current_site_id;
        
        $result = $master_configured && $slave_configured;
        return $result;
    }

    /**
     * Obtener sitios disponibles de WooCommerce Multistore
     */
    public function get_available_sites() {
        error_log('CVAV DEBUG: get_available_sites() called');
        $sites = array();
        

        
        // Método 1: Obtener sitios de WooCommerce Multistore
        if (function_exists('wc_multistore_get_sites')) {
            error_log('CVAV DEBUG: get_available_sites() - wc_multistore_get_sites function exists');
            try {
                $multistore_sites = wc_multistore_get_sites();
                if (!empty($multistore_sites) && is_array($multistore_sites)) {
                    $sites = $multistore_sites;
                    error_log('CVAV DEBUG: get_available_sites() - using multistore sites');
                }
            } catch (Exception $e) {
                error_log('CVAV DEBUG: get_available_sites() - Exception in wc_multistore_get_sites: ' . $e->getMessage());
                // Error silencioso
            }
        } else {
            error_log('CVAV DEBUG: get_available_sites() - wc_multistore_get_sites function does not exist');
        }
        
        // Método 2: Si no hay sitios de WooCommerce Multistore, obtener de la red multisite
        if (empty($sites) && is_multisite()) {
            error_log('CVAV DEBUG: get_available_sites() - using multisite method');
            try {
                $network_sites = get_sites(array(
                    'number' => 0,
                    'public' => 1
                ));
                error_log('CVAV DEBUG: get_available_sites() - network_sites count: ' . count($network_sites));
                
                foreach ($network_sites as $site) {
                    $site_id = $site->blog_id;
                    $site_name = get_blog_option($site_id, 'blogname', 'Sitio ' . $site_id);
                    $site_url = get_home_url($site_id);
                    
                    // Crear objeto similar al de WooCommerce Multistore
                    $site_obj = new stdClass();
                    $site_obj->id = $site_id;
                    $site_obj->name = $site_name;
                    $site_obj->url = $site_url;
                    $site_obj->is_main_site = is_main_site($site_id);
                    
                    $sites[] = $site_obj;
                }
                error_log('CVAV DEBUG: get_available_sites() - processed ' . count($sites) . ' sites from multisite');
            } catch (Exception $e) {
                error_log('CVAV DEBUG: get_available_sites() - Exception in multisite method: ' . $e->getMessage());
                // Error silencioso
            }
        } else {
            error_log('CVAV DEBUG: get_available_sites() - not using multisite method, sites empty: ' . (empty($sites) ? 'true' : 'false') . ', is_multisite: ' . (is_multisite() ? 'true' : 'false'));
        }
        
        // Método 3: Si aún no hay sitios, incluir al menos el sitio actual
        if (empty($sites)) {
            error_log('CVAV DEBUG: get_available_sites() - using current site method');
            try {
                $current_site_id = get_current_blog_id();
                $current_site_name = get_bloginfo('name');
                $current_site_url = get_home_url();
                
                $site_obj = new stdClass();
                $site_obj->id = $current_site_id;
                $site_obj->name = $current_site_name;
                $site_obj->url = $current_site_url;
                $site_obj->is_main_site = is_main_site($current_site_id);
                
                $sites[] = $site_obj;
                error_log('CVAV DEBUG: get_available_sites() - added current site: ' . print_r($site_obj, true));
            } catch (Exception $e) {
                error_log('CVAV DEBUG: get_available_sites() - Exception in current site method: ' . $e->getMessage());
                // Error silencioso
            }
        }
        
        error_log('CVAV DEBUG: get_available_sites() returning ' . count($sites) . ' sites');
        return $sites;
    }

    /**
     * Función de debug para diagnosticar sitios disponibles
     */
    public function debug_available_sites() {
        $debug_info = array();
        
        // Información básica
        $debug_info['is_multisite'] = is_multisite();
        $debug_info['current_blog_id'] = get_current_blog_id();
        $debug_info['is_main_site'] = is_main_site();
        
        // WooCommerce Multistore
        if (function_exists('wc_multistore_get_sites')) {
            try {
                $multistore_sites = wc_multistore_get_sites();
                $debug_info['wc_multistore_sites'] = $multistore_sites;
                $debug_info['wc_multistore_sites_count'] = is_array($multistore_sites) ? count($multistore_sites) : 0;
            } catch (Exception $e) {
                $debug_info['wc_multistore_sites'] = 'Error: ' . $e->getMessage();
                $debug_info['wc_multistore_sites_count'] = 0;
            }
        } else {
            $debug_info['wc_multistore_sites'] = 'Función no disponible';
            $debug_info['wc_multistore_sites_count'] = 0;
        }
        
        // Sitios de la red
        if (is_multisite()) {
            try {
                $network_sites = get_sites(array(
                    'number' => 0,
                    'public' => 1
                ));
                $debug_info['network_sites'] = array();
                foreach ($network_sites as $site) {
                    $debug_info['network_sites'][] = array(
                        'id' => $site->blog_id,
                        'name' => get_blog_option($site->blog_id, 'blogname', 'Sitio ' . $site->blog_id),
                        'url' => get_home_url($site->blog_id),
                        'is_main_site' => is_main_site($site->blog_id)
                    );
                }
                $debug_info['network_sites_count'] = count($network_sites);
            } catch (Exception $e) {
                $debug_info['network_sites'] = array();
                $debug_info['network_sites_count'] = 0;
            }
        } else {
            $debug_info['network_sites'] = array();
            $debug_info['network_sites_count'] = 0;
        }
        
        // Sitios disponibles según nuestro método
        try {
            $available_sites = $this->get_available_sites();
            $debug_info['available_sites'] = $available_sites;
            $debug_info['available_sites_count'] = count($available_sites);
        } catch (Exception $e) {
            $debug_info['available_sites'] = array();
            $debug_info['available_sites_count'] = 0;
        }
        
        return $debug_info;
    }

    /**
     * Función de prueba para obtener sitios de forma simple
     */
    public function get_sites_simple() {
        $sites = array();
        
        if (is_multisite()) {
            // Obtener sitios de forma directa
            $network_sites = get_sites(array(
                'number' => 0,
                'public' => 1
            ));
            
            foreach ($network_sites as $site) {
                $site_obj = new stdClass();
                $site_obj->id = $site->blog_id;
                $site_obj->name = get_blog_option($site->blog_id, 'blogname', 'Sitio ' . $site->blog_id);
                $site_obj->url = get_home_url($site->blog_id);
                $site_obj->is_main_site = is_main_site($site->blog_id);
                
                $sites[] = $site_obj;
            }
        } else {
            // Si no es multisite, solo el sitio actual
            $site_obj = new stdClass();
            $site_obj->id = get_current_blog_id();
            $site_obj->name = get_bloginfo('name');
            $site_obj->url = get_home_url();
            $site_obj->is_main_site = true;
            
            $sites[] = $site_obj;
        }
        
        return $sites;
    }

    /**
     * Obtener atributos de un sitio específico
     */
    public function get_site_attributes($site_id, $exclude_connected = false) {
        error_log('CVAV DEBUG: get_site_attributes() called with site_id: ' . $site_id . ', exclude_connected: ' . ($exclude_connected ? 'true' : 'false'));
        $attributes = array();

        if (is_multisite()) {
            error_log('CVAV DEBUG: get_site_attributes() - is_multisite: true');
            $original_blog_id = get_current_blog_id();
            error_log('CVAV DEBUG: get_site_attributes() - original_blog_id: ' . $original_blog_id);
            
            try {
                error_log('CVAV DEBUG: get_site_attributes() - switching to blog: ' . $site_id);
                switch_to_blog($site_id);

                // Verificar que WooCommerce esté activo en este sitio
                if (!class_exists('WooCommerce')) {
                    error_log('CVAV DEBUG: get_site_attributes() - WooCommerce not active on site ' . $site_id);
                    restore_current_blog();
                    return $attributes;
                }

                // Obtener atributos usando WooCommerce
                if (function_exists('wc_get_attribute_taxonomies')) {
                    $attribute_taxonomies = wc_get_attribute_taxonomies();
                    error_log('CVAV DEBUG: get_site_attributes() - found ' . count($attribute_taxonomies) . ' attributes');
                    
                    // Si se solicita excluir conectados, obtener las conexiones existentes
                    $connected_attributes = array();
                    if ($exclude_connected) {
                        error_log('CVAV DEBUG: get_site_attributes() - getting existing connections for filtering');
                        $existing_connections = $this->get_existing_connections();
                        error_log('CVAV DEBUG: get_site_attributes() - found ' . count($existing_connections) . ' existing connections');
                        
                        $configured_sites = $this->get_configured_sites();
                        error_log('CVAV DEBUG: get_site_attributes() - configured sites: ' . print_r($configured_sites, true));
                        error_log('CVAV DEBUG: get_site_attributes() - current site_id: ' . $site_id);
                        
                        if ($site_id == $configured_sites['master']) {
                            // Para Master, excluir atributos que están conectados
                            error_log('CVAV DEBUG: get_site_attributes() - filtering for Master site');
                            foreach ($existing_connections as $connection) {
                                $connected_attributes[] = $connection->master_attribute_id;
                                error_log('CVAV DEBUG: get_site_attributes() - adding connected Master attribute ID: ' . $connection->master_attribute_id);
                            }
                        } else {
                            // Para Slave, excluir atributos que están conectados
                            error_log('CVAV DEBUG: get_site_attributes() - filtering for Slave site');
                            foreach ($existing_connections as $connection) {
                                $connected_attributes[] = $connection->slave_attribute_id;
                                error_log('CVAV DEBUG: get_site_attributes() - adding connected Slave attribute ID: ' . $connection->slave_attribute_id);
                            }
                        }
                        error_log('CVAV DEBUG: get_site_attributes() - connected attributes to exclude: ' . print_r($connected_attributes, true));
                    }
                    
                    foreach ($attribute_taxonomies as $taxonomy) {
                        // Si se solicita excluir conectados y este atributo está conectado, saltarlo
                        if ($exclude_connected && in_array($taxonomy->attribute_id, $connected_attributes)) {
                            error_log('CVAV DEBUG: get_site_attributes() - skipping connected attribute: ' . $taxonomy->attribute_label . ' (ID: ' . $taxonomy->attribute_id . ')');
                            continue;
                        } else {
                            error_log('CVAV DEBUG: get_site_attributes() - including attribute: ' . $taxonomy->attribute_label . ' (ID: ' . $taxonomy->attribute_id . ')');
                        }
                        
                        $attributes[] = array(
                            'id' => $taxonomy->attribute_id,
                            'name' => $taxonomy->attribute_label,
                            'slug' => $taxonomy->attribute_name,
                            'type' => $taxonomy->attribute_type
                        );
                        error_log('CVAV DEBUG: get_site_attributes() - Attribute - ID: ' . $taxonomy->attribute_id . ', Name: ' . $taxonomy->attribute_label);
                    }
                } else {
                    error_log('CVAV DEBUG: get_site_attributes() - wc_get_attribute_taxonomies function not available');
                }

                error_log('CVAV DEBUG: get_site_attributes() - restoring to blog: ' . $original_blog_id);
                restore_current_blog();
                error_log('CVAV DEBUG: get_site_attributes() - restored to blog ID: ' . get_current_blog_id());
                
            } catch (Exception $e) {
                error_log('CVAV DEBUG: get_site_attributes() - Exception: ' . $e->getMessage());
                restore_current_blog();
            }
        } else {
            error_log('CVAV DEBUG: get_site_attributes() - is_multisite: false');
            // Si no es multisite, obtener atributos del sitio actual
            if (function_exists('wc_get_attribute_taxonomies')) {
                $attribute_taxonomies = wc_get_attribute_taxonomies();
                error_log('CVAV DEBUG: get_site_attributes() - found ' . count($attribute_taxonomies) . ' attributes (non-multisite)');
                
                foreach ($attribute_taxonomies as $taxonomy) {
                    $attributes[] = array(
                        'id' => $taxonomy->attribute_id,
                        'name' => $taxonomy->attribute_label,
                        'slug' => $taxonomy->attribute_name,
                        'type' => $taxonomy->attribute_type
                    );
                }
            } else {
                error_log('CVAV DEBUG: get_site_attributes() - wc_get_attribute_taxonomies function not available (non-multisite)');
            }
        }

        error_log('CVAV DEBUG: get_site_attributes() returning ' . count($attributes) . ' attributes');
        return $attributes;
    }

    /**
     * Conectar atributos entre dos sitios
     */
    public function connect_attributes($master_attribute_id, $slave_attribute_id) {
        error_log('CVAV DEBUG: connect_attributes() called with master_attribute_id: ' . $master_attribute_id . ', slave_attribute_id: ' . $slave_attribute_id);
        global $wpdb;

        // Obtener los sitios configurados
        $sites = $this->get_configured_sites();
        $secondary_site_id = $sites['slave'];
        error_log('CVAV DEBUG: connect_attributes() - secondary_site_id: ' . $secondary_site_id);

        if (empty($secondary_site_id)) {
            error_log('CVAV DEBUG: connect_attributes() - secondary site not configured');
            return array('success' => false, 'message' => 'Sitio secundario no configurado');
        }

        $table = $wpdb->prefix . $secondary_site_id . '_woo_multistore_attributes_relationships';
        error_log('CVAV DEBUG: connect_attributes() - table: ' . $table);
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            error_log('CVAV DEBUG: connect_attributes() - table not found: ' . $table);
            return array('success' => false, 'message' => 'Tabla de relaciones no encontrada');
        }

        error_log('CVAV DEBUG: connect_attributes() - table exists, inserting relationship');
        // Insertar la relación
        $result = $wpdb->insert(
            $table,
            array(
                'attribute_id' => $master_attribute_id,
                'child_attribute_id' => $slave_attribute_id
            ),
            array('%d', '%d')
        );

        if ($result === false) {
            error_log('CVAV DEBUG: connect_attributes() - insert failed: ' . $wpdb->last_error);
            return array('success' => false, 'message' => 'Error al conectar atributos');
        }

        error_log('CVAV DEBUG: connect_attributes() - insert successful, rows affected: ' . $result);
        return array('success' => true, 'message' => 'Atributos conectados exitosamente');
    }

    /**
     * Desconectar atributos
     */
    public function disconnect_attributes($master_attribute_id, $slave_attribute_id) {
        error_log('CVAV DEBUG: disconnect_attributes() called with master_attribute_id: ' . $master_attribute_id . ', slave_attribute_id: ' . $slave_attribute_id);
        global $wpdb;

        // Obtener los sitios configurados
        $sites = $this->get_configured_sites();
        $secondary_site_id = $sites['slave'];
        error_log('CVAV DEBUG: disconnect_attributes() - secondary_site_id: ' . $secondary_site_id);

        if (empty($secondary_site_id)) {
            error_log('CVAV DEBUG: disconnect_attributes() - secondary site not configured');
            return array('success' => false, 'message' => 'Sitio secundario no configurado');
        }

        $table = $wpdb->prefix . $secondary_site_id . '_woo_multistore_attributes_relationships';
        error_log('CVAV DEBUG: disconnect_attributes() - table: ' . $table);
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            error_log('CVAV DEBUG: disconnect_attributes() - table not found: ' . $table);
            return array('success' => false, 'message' => 'Tabla de relaciones no encontrada');
        }

        error_log('CVAV DEBUG: disconnect_attributes() - table exists, deleting relationship');
        $result = $wpdb->delete(
            $table,
            array(
                'attribute_id' => $master_attribute_id,
                'child_attribute_id' => $slave_attribute_id
            ),
            array('%d', '%d')
        );

        if ($result === false) {
            error_log('CVAV DEBUG: disconnect_attributes() - delete failed: ' . $wpdb->last_error);
            return array('success' => false, 'message' => 'Error al desconectar atributos');
        }

        error_log('CVAV DEBUG: disconnect_attributes() - delete successful, rows affected: ' . $result);
        return array('success' => true, 'message' => 'Atributos desconectados exitosamente');
    }

    /**
     * Obtener conexiones existentes
     */
    public function get_existing_connections() {
        error_log('CVAV DEBUG: get_existing_connections() called');
        global $wpdb;

        // Obtener los sitios configurados
        $sites = $this->get_configured_sites();
        $master_site_id = $sites['master'];
        $secondary_site_id = $sites['slave']; // Puede ser Slave u otro sitio

        error_log('CVAV DEBUG: get_existing_connections() - master_site_id: ' . $master_site_id . ', secondary_site_id: ' . $secondary_site_id);

        if (empty($master_site_id) || empty($secondary_site_id)) {
            error_log('CVAV DEBUG: get_existing_connections() - empty site IDs');
            return array();
        }

        // La tabla de relaciones está en el sitio secundario
        $table = $wpdb->prefix . $secondary_site_id . '_woo_multistore_attributes_relationships';
        error_log('CVAV DEBUG: get_existing_connections() - table: ' . $table);
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            error_log('CVAV DEBUG: get_existing_connections() - table not found');
            return array();
        }

        // Simplificar: solo obtener las relaciones directas de la tabla
        $query = "SELECT attribute_id, child_attribute_id FROM $table";
        $connections = $wpdb->get_results($query);
        
        error_log('CVAV DEBUG: get_existing_connections() - found ' . count($connections) . ' raw connections');

        // Obtener nombres de atributos para cada conexión
        $normalized_connections = array();
        foreach ($connections as $connection) {
            $master_attr_name = '';
            $slave_attr_name = '';
            
            // Obtener nombre del atributo de Master
            $master_query = "SELECT attribute_label FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_id = %d";
            $master_attr_name = $wpdb->get_var($wpdb->prepare($master_query, $connection->attribute_id));
            
            // Obtener nombre del atributo de Slave
            $slave_query = "SELECT attribute_label FROM {$wpdb->prefix}{$secondary_site_id}_woocommerce_attribute_taxonomies WHERE attribute_id = %d";
            $slave_attr_name = $wpdb->get_var($wpdb->prepare($slave_query, $connection->child_attribute_id));
            
            $normalized_connections[] = (object) array(
                'master_attribute_id' => $connection->attribute_id,
                'slave_attribute_id' => $connection->child_attribute_id,
                'master_attribute_name' => $master_attr_name ?: 'Atributo ID: ' . $connection->attribute_id,
                'slave_attribute_name' => $slave_attr_name ?: 'Atributo ID: ' . $connection->child_attribute_id
            );
            
            error_log('CVAV DEBUG: get_existing_connections() - added connection: ' . $connection->attribute_id . ' -> ' . $connection->child_attribute_id);
        }

        error_log('CVAV DEBUG: get_existing_connections() - returning ' . count($normalized_connections) . ' connections');
        return $normalized_connections;
    }

    /**
     * Verificar si dos atributos están conectados
     */
    public function are_attributes_connected($master_attribute_id, $slave_attribute_id) {
        global $wpdb;

        // Obtener los sitios configurados
        $sites = $this->get_configured_sites();
        $secondary_site_id = $sites['slave'];

        if (empty($secondary_site_id)) {
            return false;
        }

        $table = $wpdb->prefix . $secondary_site_id . '_woo_multistore_attributes_relationships';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return false;
        }

        $result = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $table 
            WHERE attribute_id = %d AND child_attribute_id = %d
        ", $master_attribute_id, $slave_attribute_id));

        return $result > 0;
    }

    /**
     * Verificar si WooCommerce está activo en un sitio específico
     */
    public function is_woocommerce_active($site_id) {
        error_log('CVAV DEBUG: is_woocommerce_active() called with site_id: ' . $site_id);
        if (!is_multisite()) {
            $is_active = class_exists('WooCommerce');
            error_log('CVAV DEBUG: is_woocommerce_active() - not multisite, WooCommerce active: ' . ($is_active ? 'true' : 'false'));
            return $is_active;
        }

        $original_blog_id = get_current_blog_id();
        $is_active = false;
        error_log('CVAV DEBUG: is_woocommerce_active() - original_blog_id: ' . $original_blog_id);

        try {
            error_log('CVAV DEBUG: is_woocommerce_active() - switching to blog: ' . $site_id);
            switch_to_blog($site_id);
            $is_active = class_exists('WooCommerce');
            error_log('CVAV DEBUG: is_woocommerce_active() - WooCommerce active on site ' . $site_id . ': ' . ($is_active ? 'true' : 'false'));
            restore_current_blog();
            error_log('CVAV DEBUG: is_woocommerce_active() - restored to blog: ' . get_current_blog_id());
        } catch (Exception $e) {
            error_log('CVAV DEBUG: is_woocommerce_active() - Exception: ' . $e->getMessage());
            restore_current_blog();
        }

        error_log('CVAV DEBUG: is_woocommerce_active() returning: ' . ($is_active ? 'true' : 'false'));
        return $is_active;
    }

    /**
     * Verificar configuración de sitios
     */
    public function verify_sites_configuration() {
        $sites = $this->get_configured_sites();
        $errors = array();

        if (empty($sites['master'])) {
            $errors[] = 'Master no está configurado';
        } else {
                    if (!$this->is_woocommerce_active($sites['master'])) {
            $errors[] = 'WooCommerce no está activo en Master';
            }
        }

        if (empty($sites['slave'])) {
            $errors[] = 'Slave no está configurado';
        } else {
                    if (!$this->is_woocommerce_active($sites['slave'])) {
            $errors[] = 'WooCommerce no está activo en Slave';
            }
        }

        if (!empty($sites['master']) && !empty($sites['slave']) && $sites['master'] == $sites['slave']) {
            $errors[] = 'Master y Slave no pueden ser el mismo sitio';
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Limpiar conexiones que no correspondan a Master y el sitio secundario
     */
    public function clean_unrelated_connections() {
        global $wpdb;

        // Obtener los sitios configurados
        $sites = $this->get_configured_sites();
        $master_site_id = $sites['master'];
        $slave_site_id = $sites['slave'];

        if (empty($master_site_id) || empty($slave_site_id)) {
            return array('success' => false, 'message' => 'Sitios no configurados');
        }

        $table = $wpdb->prefix . $slave_site_id . '_woo_multistore_attributes_relationships';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return array('success' => false, 'message' => 'Tabla de relaciones no encontrada');
        }

        // Obtener atributos de ambos sitios
        $master_attributes = $this->get_site_attributes($master_site_id);
        $slave_attributes = $this->get_site_attributes($slave_site_id);

        // Crear arrays de IDs de atributos para cada sitio
        $master_attribute_ids = array();
        foreach ($master_attributes as $attr) {
            $master_attribute_ids[] = $attr['id'];
        }

        $slave_attribute_ids = array();
        foreach ($slave_attributes as $attr) {
            $slave_attribute_ids[] = $attr['id'];
        }

        if (empty($master_attribute_ids) || empty($slave_attribute_ids)) {
            return array('success' => false, 'message' => 'No hay atributos disponibles');
        }

        // Construir la consulta para eliminar conexiones que no correspondan
        $master_ids_str = implode(',', $master_attribute_ids);
        $slave_ids_str = implode(',', $slave_attribute_ids);

        $query = $wpdb->prepare("
            DELETE FROM $table 
            WHERE NOT (
                attribute_id IN ($master_ids_str) 
                AND child_attribute_id IN ($slave_ids_str)
            )
        ");

        $result = $wpdb->query($query);

        if ($result === false) {
            return array('success' => false, 'message' => 'Error al limpiar conexiones');
        }

        return array('success' => true, 'message' => 'Conexiones limpiadas exitosamente. Se eliminaron ' . $result . ' conexiones no relacionadas.');
    }
}

// Inicializar el plugin
function cvav_connector_init() {
    return WooMultisiteManualConnector::getInstance();
}

// Inicializar cuando WordPress esté listo
add_action('plugins_loaded', 'cvav_connector_init');

// Función global para acceder al plugin
function CVAV_Connector() {
    return WooMultisiteManualConnector::getInstance();
} 