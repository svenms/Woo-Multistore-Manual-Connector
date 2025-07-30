<?php
/**
 * Clase de administración del plugin
 */

defined('ABSPATH') || exit;

class CVAV_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        // Menú principal
        add_menu_page(
            __('Conector de Atributos', 'chilevapo-andesvapor-connector'),
            __('Conector CV-AV', 'chilevapo-andesvapor-connector'),
            'manage_options',
            'cvav-connector',
            array($this, 'main_page'),
            'dashicons-admin-links',
            30
        );

        // Submenús
        add_submenu_page(
            'cvav-connector',
            __('Conexiones', 'chilevapo-andesvapor-connector'),
            __('Conexiones', 'chilevapo-andesvapor-connector'),
            'manage_options',
            'cvav-connector',
            array($this, 'main_page')
        );

        add_submenu_page(
            'cvav-connector',
            __('Configuración', 'chilevapo-andesvapor-connector'),
            __('Configuración', 'chilevapo-andesvapor-connector'),
            'manage_options',
            'cvav-connector-settings',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'cvav-connector',
            __('Productos', 'chilevapo-andesvapor-connector'),
            __('Productos', 'chilevapo-andesvapor-connector'),
            'manage_options',
            'cvav-connector-products',
            array($this, 'products_page')
        );

        // Página de debug (solo para administradores)
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'cvav-connector',
                __('Debug', 'chilevapo-andesvapor-connector'),
                __('Debug', 'chilevapo-andesvapor-connector'),
                'manage_options',
                'cvav-connector-debug',
                array($this, 'debug_page')
            );
        }
    }

    /**
     * Cargar scripts y estilos de administración
     */
    public function enqueue_admin_scripts($hook) {
        // Solo cargar scripts en el sitio principal
        if (!is_main_site()) {
            return;
        }
        
        if (strpos($hook, 'cvav-connector') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-dialog');

        wp_enqueue_script(
            'cvav-admin-js',
            CVAV_CONNECTOR_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable', 'jquery-ui-dialog'),
            CVAV_CONNECTOR_VERSION,
            true
        );

        wp_enqueue_style(
            'cvav-admin-css',
            CVAV_CONNECTOR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CVAV_CONNECTOR_VERSION
        );

        // Localizar script
        wp_localize_script('cvav-admin-js', 'cvav_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cvav_nonce'),
            'strings' => array(
                'confirm_delete' => __('¿Estás seguro de que quieres desconectar estos atributos?', 'chilevapo-andesvapor-connector'),
                'connect_success' => __('Atributos conectados exitosamente.', 'chilevapo-andesvapor-connector'),
                'disconnect_success' => __('Atributos desconectados exitosamente.', 'chilevapo-andesvapor-connector'),
                'connect_error' => __('Error al conectar atributos.', 'chilevapo-andesvapor-connector')
            )
        ));
    }

    /**
     * Página principal
     */
    public function main_page() {
        error_log('CVAV DEBUG: main_page() called');
        
        // Verificar que sea el sitio principal
        if (!is_main_site()) {
            error_log('CVAV DEBUG: main_page() - not main site, access denied');
            wp_die(__('Acceso denegado. Este plugin solo está disponible en el sitio principal.', 'chilevapo-andesvapor-connector'));
        }
        
        $connector = CVAV_Connector();
        error_log('CVAV DEBUG: main_page() - connector instance obtained');
        
        if (!$connector->is_configured()) {
            error_log('CVAV DEBUG: main_page() - not configured, showing warning');
            $this->render_configuration_warning();
            return;
        }
        error_log('CVAV DEBUG: main_page() - configured, rendering main page');
        $this->render_main_page();
    }

    /**
     * Página de configuración
     */
    public function settings_page() {
        error_log('CVAV DEBUG: settings_page() called');
        
        // Verificar que sea el sitio principal
        if (!is_main_site()) {
            error_log('CVAV DEBUG: settings_page() - not main site, access denied');
            wp_die(__('Acceso denegado. Este plugin solo está disponible en el sitio principal.', 'chilevapo-andesvapor-connector'));
        }
        
        $this->render_settings_page();
    }

    /**
     * Página de productos
     */
    public function products_page() {
        error_log('CVAV DEBUG: products_page() called');
        
        // Verificar que sea el sitio principal
        if (!is_main_site()) {
            error_log('CVAV DEBUG: products_page() - not main site, access denied');
            wp_die(__('Acceso denegado. Este plugin solo está disponible en el sitio principal.', 'chilevapo-andesvapor-connector'));
        }
        
        $connector = CVAV_Connector();
        error_log('CVAV DEBUG: products_page() - connector instance obtained');
        
        if (!$connector->is_configured()) {
            error_log('CVAV DEBUG: products_page() - not configured, showing warning');
            $this->render_configuration_warning();
            return;
        }
        error_log('CVAV DEBUG: products_page() - configured, rendering products page');
        $this->render_products_page();
    }

    /**
     * Página de debug
     */
    public function debug_page() {
        error_log('CVAV DEBUG: debug_page() called');
        
        // Verificar que sea el sitio principal
        if (!is_main_site()) {
            error_log('CVAV DEBUG: debug_page() - not main site, access denied');
            wp_die(__('Acceso denegado. Este plugin solo está disponible en el sitio principal.', 'chilevapo-andesvapor-connector'));
        }
        
        $connector = CVAV_Connector();
        error_log('CVAV DEBUG: debug_page() - connector instance obtained');
        
        // Obtener información de debug de forma segura
        try {
            error_log('CVAV DEBUG: debug_page() - calling debug_available_sites()');
            $debug_info = $connector->debug_available_sites();
            error_log('CVAV DEBUG: debug_page() - debug_available_sites() completed successfully');
        } catch (Exception $e) {
            error_log('CVAV DEBUG: debug_page() - Exception in debug_available_sites(): ' . $e->getMessage());
            $debug_info = array(
                'error' => $e->getMessage(),
                'is_multisite' => is_multisite(),
                'current_blog_id' => get_current_blog_id(),
                'is_main_site' => is_main_site(),
                'wc_multistore_sites' => 'Error al obtener',
                'wc_multistore_sites_count' => 0,
                'network_sites' => array(),
                'network_sites_count' => 0,
                'available_sites' => array(),
                'available_sites_count' => 0
            );
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Debug - Conector de Atributos', 'chilevapo-andesvapor-connector'); ?></h1>
            
            <?php if (isset($debug_info['error'])): ?>
                <div class="notice notice-error">
                    <p><strong><?php _e('Error:', 'chilevapo-andesvapor-connector'); ?></strong> <?php echo esc_html($debug_info['error']); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="cvav-debug-section">
                <h2><?php _e('Información del Sistema', 'chilevapo-andesvapor-connector'); ?></h2>
                <table class="widefat">
                    <tr>
                        <th><?php _e('Propiedad', 'chilevapo-andesvapor-connector'); ?></th>
                        <th><?php _e('Valor', 'chilevapo-andesvapor-connector'); ?></th>
                    </tr>
                    <tr>
                        <td><?php _e('Es Multisite', 'chilevapo-andesvapor-connector'); ?></td>
                        <td><?php echo $debug_info['is_multisite'] ? 'Sí' : 'No'; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Blog ID Actual', 'chilevapo-andesvapor-connector'); ?></td>
                        <td><?php echo esc_html($debug_info['current_blog_id']); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Es Sitio Principal', 'chilevapo-andesvapor-connector'); ?></td>
                        <td><?php echo $debug_info['is_main_site'] ? 'Sí' : 'No'; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('WooCommerce Multistore Disponible', 'chilevapo-andesvapor-connector'); ?></td>
                        <td><?php echo function_exists('wc_multistore_get_sites') ? 'Sí' : 'No'; ?></td>
                    </tr>
                </table>
            </div>

            <div class="cvav-debug-section">
                <h2><?php _e('Sitios de WooCommerce Multistore', 'chilevapo-andesvapor-connector'); ?></h2>
                <?php if (is_array($debug_info['wc_multistore_sites']) && !empty($debug_info['wc_multistore_sites'])): ?>
                    <p><?php printf(__('Se encontraron %d sitios de WooCommerce Multistore:', 'chilevapo-andesvapor-connector'), $debug_info['wc_multistore_sites_count']); ?></p>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('ID', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Nombre', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('URL', 'chilevapo-andesvapor-connector'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debug_info['wc_multistore_sites'] as $site): ?>
                                <tr>
                                    <td><?php echo esc_html(is_object($site) ? $site->get_id() : (isset($site->id) ? $site->id : 'N/A')); ?></td>
                                    <td><?php echo esc_html(is_object($site) ? $site->get_name() : (isset($site->name) ? $site->name : 'N/A')); ?></td>
                                    <td><?php echo esc_html(is_object($site) ? $site->get_url() : (isset($site->url) ? $site->url : 'N/A')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php echo esc_html($debug_info['wc_multistore_sites']); ?></p>
                <?php endif; ?>
            </div>

            <div class="cvav-debug-section">
                <h2><?php _e('Sitios de la Red', 'chilevapo-andesvapor-connector'); ?></h2>
                <?php if (is_multisite() && !empty($debug_info['network_sites'])): ?>
                    <p><?php printf(__('Se encontraron %d sitios en la red:', 'chilevapo-andesvapor-connector'), $debug_info['network_sites_count']); ?></p>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('ID', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Nombre', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('URL', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Sitio Principal', 'chilevapo-andesvapor-connector'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debug_info['network_sites'] as $site): ?>
                                <tr>
                                    <td><?php echo esc_html($site['id']); ?></td>
                                    <td><?php echo esc_html($site['name']); ?></td>
                                    <td><?php echo esc_html($site['url']); ?></td>
                                    <td><?php echo $site['is_main_site'] ? 'Sí' : 'No'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('No es una red multisite o no se encontraron sitios.', 'chilevapo-andesvapor-connector'); ?></p>
                <?php endif; ?>
            </div>

            <div class="cvav-debug-section">
                <h2><?php _e('Sitios Disponibles para el Plugin', 'chilevapo-andesvapor-connector'); ?></h2>
                <?php if (!empty($debug_info['available_sites'])): ?>
                    <p><?php printf(__('El plugin encontró %d sitios disponibles:', 'chilevapo-andesvapor-connector'), $debug_info['available_sites_count']); ?></p>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('ID', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Nombre', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('URL', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Sitio Principal', 'chilevapo-andesvapor-connector'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debug_info['available_sites'] as $site): ?>
                                <?php 
                                $site_id = is_object($site) ? (method_exists($site, 'get_id') ? $site->get_id() : $site->id) : $site['id'] ?? 'N/A';
                                $site_name = is_object($site) ? (method_exists($site, 'get_name') ? $site->get_name() : $site->name) : $site['name'] ?? 'N/A';
                                $site_url = is_object($site) ? (method_exists($site, 'get_url') ? $site->get_url() : $site->url) : $site['url'] ?? 'N/A';
                                $is_main_site = is_object($site) ? ($site->is_main_site ?? false) : ($site['is_main_site'] ?? false);
                                ?>
                                <tr>
                                    <td><?php echo esc_html($site_id); ?></td>
                                    <td><?php echo esc_html($site_name); ?></td>
                                    <td><?php echo esc_html($site_url); ?></td>
                                    <td><?php echo $is_main_site ? 'Sí' : 'No'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('No se encontraron sitios disponibles para el plugin.', 'chilevapo-andesvapor-connector'); ?></p>
                <?php endif; ?>
            </div>

            <div class="cvav-debug-section">
                <h2><?php _e('Configuración Actual', 'chilevapo-andesvapor-connector'); ?></h2>
                <?php 
                try {
                    $settings = $connector->get_settings();
                    $configured_sites = $connector->get_configured_sites();
                    $is_configured = $connector->is_configured();
                } catch (Exception $e) {
                    $settings = array();
                    $configured_sites = array('master' => '', 'slave' => '');
                    $is_configured = false;
                }
                ?>
                <table class="widefat">
                    <tr>
                        <th><?php _e('Configuración', 'chilevapo-andesvapor-connector'); ?></th>
                        <th><?php _e('Valor', 'chilevapo-andesvapor-connector'); ?></th>
                    </tr>
                    <tr>
                        <td><?php _e('Master Site ID', 'chilevapo-andesvapor-connector'); ?></td>
                        <td><?php echo esc_html($configured_sites['master'] ?: 'No configurado'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Slave Site ID', 'chilevapo-andesvapor-connector'); ?></td>
                        <td><?php echo esc_html($configured_sites['slave'] ?: 'No configurado'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Plugin Configurado', 'chilevapo-andesvapor-connector'); ?></td>
                        <td><?php echo $is_configured ? 'Sí' : 'No'; ?></td>
                    </tr>
                </table>
            </div>

            <div class="cvav-debug-section">
                <h2><?php _e('Acciones de Debug', 'chilevapo-andesvapor-connector'); ?></h2>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=cvav-connector-settings'); ?>" class="button button-primary">
                        <?php _e('Ir a Configuración', 'chilevapo-andesvapor-connector'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=cvav-connector'); ?>" class="button">
                        <?php _e('Ir a Conexiones', 'chilevapo-andesvapor-connector'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar advertencia de configuración
     */
    private function render_configuration_warning() {
        $current_site_name = get_bloginfo('name');
        $current_site_url = get_home_url();
        ?>
        <div class="wrap">
            <h1><?php _e('Woo Multistore Manual Connector', 'chilevapo-andesvapor-connector'); ?></h1>
            
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('Configuración automática', 'chilevapo-andesvapor-connector'); ?></strong><br>
                    <?php printf(__('Master está configurado automáticamente como: <strong>%s</strong> (%s)', 'chilevapo-andesvapor-connector'), $current_site_name, $current_site_url); ?>
                </p>
                <p>
                    <?php _e('Solo necesitas seleccionar el sitio de Slave para completar la configuración.', 'chilevapo-andesvapor-connector'); ?>
                </p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=cvav-connector-settings'); ?>" class="button button-primary">
                        <?php _e('Configurar Slave', 'chilevapo-andesvapor-connector'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar página principal
     */
    private function render_main_page() {
        error_log('CVAV DEBUG: render_main_page() called');
        $connector = CVAV_Connector();
        error_log('CVAV DEBUG: render_main_page() - connector instance obtained');
        
        // Verificar configuración
        error_log('CVAV DEBUG: render_main_page() - verifying sites configuration');
        $verification = $connector->verify_sites_configuration();
        error_log('CVAV DEBUG: render_main_page() - verification result: ' . print_r($verification, true));
        if (!$verification['valid']) {
            error_log('CVAV DEBUG: render_main_page() - configuration not valid, showing error');
            ?>
            <div class="wrap">
                <h1><?php _e('Conexiones de Atributos', 'chilevapo-andesvapor-connector'); ?></h1>
                
                <div class="notice notice-error">
                    <p><strong><?php _e('Error de configuración:', 'chilevapo-andesvapor-connector'); ?></strong></p>
                    <ul>
                        <?php foreach ($verification['errors'] as $error): ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=cvav-connector-settings'); ?>" class="button button-primary">
                            <?php _e('Ir a Configuración', 'chilevapo-andesvapor-connector'); ?>
                        </a>
                    </p>
                </div>
            </div>
            <?php
            return;
        }
        error_log('CVAV DEBUG: render_main_page() - configuration valid, continuing');
        
        error_log('CVAV DEBUG: render_main_page() - getting configured sites');
        $sites = $connector->get_configured_sites();
        error_log('CVAV DEBUG: render_main_page() - getting existing connections');
        $existing_connections = $connector->get_existing_connections();
        error_log('CVAV DEBUG: render_main_page() - existing connections count: ' . count($existing_connections));
        
        // Obtener atributos de ambos sitios
        $master_site_id = $sites['master'];
        $slave_site_id = $sites['slave'];
        error_log('CVAV DEBUG: render_main_page() - master_site_id: ' . $master_site_id . ', slave_site_id: ' . $slave_site_id);
        
        // Obtener información del sitio secundario para mostrar su nombre
        $secondary_site_info = null;
        if (!empty($slave_site_id)) {
            $all_sites = $connector->get_sites_simple();
            foreach ($all_sites as $site) {
                if ($site->id == $slave_site_id) {
                    $secondary_site_info = $site;
                    break;
                }
            }
        }
        
        $secondary_site_name = $secondary_site_info ? $secondary_site_info->name : 'Sitio Secundario';
        
        error_log('CVAV DEBUG: render_main_page() - getting attributes with filtering');
        $master_attributes = $connector->get_site_attributes($master_site_id, true); // Excluir conectados
        $slave_attributes = $connector->get_site_attributes($slave_site_id, true); // Excluir conectados
        error_log('CVAV DEBUG: render_main_page() - master_attributes count: ' . count($master_attributes));
        error_log('CVAV DEBUG: render_main_page() - slave_attributes count: ' . count($slave_attributes));
        
        // Mostrar advertencia si no hay atributos
        $warnings = array();
        if (empty($master_attributes)) {
                            $warnings[] = __('No se encontraron atributos en Master.', 'chilevapo-andesvapor-connector');
        }
        if (empty($slave_attributes)) {
            $warnings[] = sprintf(__('No se encontraron atributos en %s.', 'chilevapo-andesvapor-connector'), $secondary_site_name);
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Conexiones de Atributos', 'chilevapo-andesvapor-connector'); ?></h1>
            
            <?php if (!empty($warnings)): ?>
                <div class="notice notice-warning">
                    <p><strong><?php _e('Advertencias:', 'chilevapo-andesvapor-connector'); ?></strong></p>
                    <ul>
                        <?php foreach ($warnings as $warning): ?>
                            <li><?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="cvav-header-actions">
                <button type="button" class="button button-primary" id="cvav-add-connection" <?php echo (empty($master_attributes) || empty($slave_attributes)) ? 'disabled' : ''; ?>>
                    <?php _e('Conectar Nuevos Atributos', 'chilevapo-andesvapor-connector'); ?>
                </button>
                <button type="button" class="button" id="cvav-refresh-connections">
                    <?php _e('Actualizar Lista', 'chilevapo-andesvapor-connector'); ?>
                </button>
                <button type="button" class="button button-secondary" id="cvav-clean-connections">
                    <?php _e('Limpiar Conexiones No Relacionadas', 'chilevapo-andesvapor-connector'); ?>
                </button>
            </div>

            <div class="cvav-connections-container">
                <?php if (empty($existing_connections)): ?>
                    <div class="cvav-no-connections">
                        <p><?php printf(__('No hay atributos conectados entre Master y %s. Haz clic en "Conectar Nuevos Atributos" para comenzar.', 'chilevapo-andesvapor-connector'), $secondary_site_name); ?></p>
                    </div>
                <?php else: ?>
                                            <h3><?php printf(__('Atributos Conectados entre Master y %s', 'chilevapo-andesvapor-connector'), $secondary_site_name); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Atributo Master', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php printf(__('Atributo %s', 'chilevapo-andesvapor-connector'), $secondary_site_name); ?></th>
                                <th><?php _e('Estado', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Acciones', 'chilevapo-andesvapor-connector'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existing_connections as $connection): ?>
                                <tr>
                                    <td><?php echo esc_html($connection->master_attribute_name ?: 'Atributo ID: ' . $connection->master_attribute_id); ?></td>
                                    <td><?php echo esc_html($connection->slave_attribute_name ?: 'Atributo ID: ' . $connection->slave_attribute_id); ?></td>
                                    <td>
                                        <span class="cvav-status cvav-status-active">
                                            <?php _e('Conectado', 'chilevapo-andesvapor-connector'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="cvav-actions">
                                            <button type="button" class="button button-small button-link-delete cvav-disconnect-attributes" 
                                                    data-master="<?php echo esc_attr($connection->master_attribute_id); ?>"
                                                    data-slave="<?php echo esc_attr($connection->slave_attribute_id); ?>">
                                                <?php _e('Desconectar', 'chilevapo-andesvapor-connector'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Modal para conectar atributos -->
            <div id="cvav-connection-modal" class="cvav-modal" style="display: none;">
                <div class="cvav-modal-content">
                    <div class="cvav-modal-header">
                        <h2 id="cvav-modal-title"><?php _e('Conectar Atributos', 'chilevapo-andesvapor-connector'); ?></h2>
                        <span class="cvav-modal-close">&times;</span>
                    </div>
                    <div class="cvav-modal-body">
                        <form id="cvav-connection-form">
                            <div class="cvav-form-row">
                                <label for="cvav-master-attribute"><?php _e('Atributo Master:', 'chilevapo-andesvapor-connector'); ?></label>
                                <select id="cvav-master-attribute" name="master_attribute_id" required>
                                    <option value=""><?php _e('Seleccionar atributo...', 'chilevapo-andesvapor-connector'); ?></option>
                                    <?php foreach ($master_attributes as $attribute): ?>
                                        <option value="<?php echo esc_attr($attribute['id']); ?>">
                                            <?php echo esc_html($attribute['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="cvav-form-row">
                                <label for="cvav-slave-attribute"><?php printf(__('Atributo %s:', 'chilevapo-andesvapor-connector'), $secondary_site_name); ?></label>
                                <select id="cvav-slave-attribute" name="slave_attribute_id" required>
                                    <option value=""><?php _e('Seleccionar atributo...', 'chilevapo-andesvapor-connector'); ?></option>
                                    <?php foreach ($slave_attributes as $attribute): ?>
                                        <option value="<?php echo esc_attr($attribute['id']); ?>">
                                            <?php echo esc_html($attribute['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="cvav-form-row">
                                <p class="description">
                                    <?php _e('Al conectar estos atributos, WooCommerce Multistore mantendrá sincronizados los valores entre ambas tiendas.', 'chilevapo-andesvapor-connector'); ?>
                                </p>
                            </div>
                        </form>
                    </div>
                    <div class="cvav-modal-footer">
                        <button type="button" class="button" id="cvav-modal-cancel"><?php _e('Cancelar', 'chilevapo-andesvapor-connector'); ?></button>
                        <button type="button" class="button button-primary" id="cvav-modal-save"><?php _e('Conectar', 'chilevapo-andesvapor-connector'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar página de configuración
     */
    private function render_settings_page() {
        $connector = CVAV_Connector();
        $settings = $connector->get_settings();
        
        // Usar la función simple para obtener sitios
        $sites = $connector->get_sites_simple();
        

        
        if (isset($_POST['cvav_save_settings'])) {
            $this->save_settings();
            $settings = $connector->get_settings();
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Configuración del Conector', 'chilevapo-andesvapor-connector'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('cvav_settings_nonce', 'cvav_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                                                            <label><?php _e('Sitio Master:', 'chilevapo-andesvapor-connector'); ?></label>
                        </th>
                        <td>
                            <?php 
                            $current_site_id = get_current_blog_id();
                            $current_site_name = get_bloginfo('name');
                            $current_site_url = get_home_url();
                            $is_main_site = is_main_site($current_site_id);
                            ?>
                            <div class="cvav-site-display">
                                <strong><?php echo esc_html($current_site_name); ?></strong>
                                <?php if ($is_main_site): ?>
                                    <span class="cvav-badge cvav-badge-success"><?php _e('Sitio Principal', 'chilevapo-andesvapor-connector'); ?></span>
                                <?php endif; ?>
                                <br>
                                <small><?php echo esc_html($current_site_url); ?> (ID: <?php echo esc_html($current_site_id); ?>)</small>
                            </div>
                            <input type="hidden" name="master_site_id" value="<?php echo esc_attr($current_site_id); ?>">
                            <p class="description">
                                <?php _e('Master está configurado automáticamente como el sitio actual.', 'chilevapo-andesvapor-connector'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                                                            <label for="slave_site_id"><?php _e('Sitio Slave:', 'chilevapo-andesvapor-connector'); ?></label>
                        </th>
                        <td>
                            <select name="slave_site_id" id="slave_site_id">
                                <option value=""><?php _e('Seleccionar sitio...', 'chilevapo-andesvapor-connector'); ?></option>
                                <?php foreach ($sites as $site): ?>
                                    <?php 
                                    $site_id = $site->id;
                                    $site_name = $site->name;
                                    $site_url = $site->url;
                                    $is_main_site = $site->is_main_site;
                                    
                                    // No mostrar el sitio actual (Master) en la lista
                                    if ($site_id == $current_site_id) {
                                        continue;
                                    }
                                    
                                    $display_name = $site_name;
                                    if ($is_main_site) {
                                        $display_name .= ' (Sitio Principal)';
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr($site_id); ?>" <?php selected($settings['slave_site_id'], $site_id); ?>>
                                        <?php echo esc_html($display_name); ?> - <?php echo esc_html($site_url); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Selecciona el sitio que representa Slave en tu red multistore.', 'chilevapo-andesvapor-connector'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="auto_connect_new_attributes"><?php _e('Conectar Automáticamente:', 'chilevapo-andesvapor-connector'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_connect_new_attributes" id="auto_connect_new_attributes" value="1" <?php checked($settings['auto_connect_new_attributes'], true); ?>>
                                <?php _e('Conectar automáticamente atributos con el mismo nombre', 'chilevapo-andesvapor-connector'); ?>
                            </label>
                            <p class="description"><?php _e('Cuando se creen nuevos atributos en ambas tiendas con el mismo nombre, se conectarán automáticamente.', 'chilevapo-andesvapor-connector'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="notification_email"><?php _e('Email de Notificación:', 'chilevapo-andesvapor-connector'); ?></label>
                        </th>
                        <td>
                            <input type="email" name="notification_email" id="notification_email" value="<?php echo esc_attr($settings['notification_email']); ?>" class="regular-text">
                            <p class="description"><?php _e('Email para recibir notificaciones de conexiones de atributos.', 'chilevapo-andesvapor-connector'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="force_product_update"><?php _e('Forzar Actualización de Productos:', 'chilevapo-andesvapor-connector'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="force_product_update" id="force_product_update" value="1" <?php checked($settings['force_product_update'], true); ?>>
                                <?php _e('Forzar actualización del producto en el sitio hijo al conectar', 'chilevapo-andesvapor-connector'); ?>
                            </label>
                            <p class="description"><?php _e('Cuando está activado, se fuerza una actualización del producto en el sitio hijo después de conectarlo, activando hooks de sincronización de WooCommerce Multistore.', 'chilevapo-andesvapor-connector'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="cvav_save_settings" class="button-primary" value="<?php _e('Guardar Configuración', 'chilevapo-andesvapor-connector'); ?>">
                </p>
            </form>

            <div class="cvav-info-section">
                <h3><?php _e('Información del Plugin', 'chilevapo-andesvapor-connector'); ?></h3>
                <p><?php _e('Este plugin utiliza el sistema de relaciones de WooCommerce Multistore para conectar atributos entre Master y Slave.', 'chilevapo-andesvapor-connector'); ?></p>
                <p><?php _e('Una vez conectados, los atributos se sincronizarán automáticamente cuando se actualicen en cualquiera de las tiendas.', 'chilevapo-andesvapor-connector'); ?></p>
                
                <h4><?php _e('Sitios Disponibles:', 'chilevapo-andesvapor-connector'); ?></h4>
                <ul>
                    <?php foreach ($sites as $site): ?>
                        <?php 
                        $site_id = $site->id;
                        $site_name = $site->name;
                        $site_url = $site->url;
                        $is_main_site = $site->is_main_site;
                        $current_site = (get_current_blog_id() == $site_id);
                        ?>
                        <li>
                            <strong><?php echo esc_html($site_name); ?></strong>
                            <?php if ($is_main_site): ?>
                                <span class="cvav-badge cvav-badge-success"><?php _e('Sitio Principal', 'chilevapo-andesvapor-connector'); ?></span>
                            <?php endif; ?>
                            <?php if ($current_site): ?>
                                <span class="cvav-badge cvav-badge-warning"><?php _e('Sitio Actual', 'chilevapo-andesvapor-connector'); ?></span>
                            <?php endif; ?>
                            <br>
                            <small><?php echo esc_html($site_url); ?> (ID: <?php echo esc_html($site_id); ?>)</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if (empty($sites)): ?>
                    <div class="notice notice-warning">
                        <p><?php _e('No se encontraron sitios disponibles. Verifica que WooCommerce Multistore esté configurado correctamente.', 'chilevapo-andesvapor-connector'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Guardar configuración
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['cvav_settings_nonce'], 'cvav_settings_nonce')) {
            wp_die(__('Error de seguridad.', 'chilevapo-andesvapor-connector'));
        }

        $connector = CVAV_Connector();
        $settings = $connector->get_settings();

        // Chile Vapo siempre debe ser el sitio actual
        $settings['master_site_id'] = get_current_blog_id();
        $settings['slave_site_id'] = sanitize_text_field($_POST['slave_site_id']);
        $settings['auto_connect_new_attributes'] = isset($_POST['auto_connect_new_attributes']);
        $settings['notification_email'] = sanitize_email($_POST['notification_email']);
        $settings['force_product_update'] = isset($_POST['force_product_update']);

        $connector->update_settings($settings);

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . 
                 __('Configuración guardada correctamente.', 'chilevapo-andesvapor-connector') . 
                 '</p></div>';
        });
    }

    /**
     * Notificaciones de administración
     */
    public function admin_notices() {
        $connector = CVAV_Connector();
        
        if (!$connector->is_configured()) {
            echo '<div class="notice notice-warning"><p>' . 
                 __('El Woo Multistore Manual Connector necesita ser configurado. ', 'chilevapo-andesvapor-connector') .
                 '<a href="' . admin_url('admin.php?page=cvav-connector-settings') . '">' . 
                 __('Configurar ahora', 'chilevapo-andesvapor-connector') . '</a></p></div>';
        }
    }

    /**
     * Renderizar página de productos
     */
    private function render_products_page() {
        error_log('CVAV DEBUG: render_products_page() called');
        $connector = CVAV_Connector();
        error_log('CVAV DEBUG: render_products_page() - connector instance obtained');
        $settings = $connector->get_settings();
        error_log('CVAV DEBUG: render_products_page() - settings obtained');
        $child_site_id = isset($settings['slave_site_id']) ? $settings['slave_site_id'] : '';
        error_log('CVAV DEBUG: render_products_page() - child_site_id: ' . $child_site_id);
        
        // Verificar si hay un sitio configurado
        if (!$child_site_id) {
            error_log('CVAV DEBUG: render_products_page() - no child site configured, showing warning');
            ?>
            <div class="wrap">
                <h1><?php _e('Conectar Productos', 'chilevapo-andesvapor-connector'); ?></h1>
                
                <div class="notice notice-warning">
                    <p><strong><?php _e('Configuración Requerida:', 'chilevapo-andesvapor-connector'); ?></strong> 
                    <?php _e('Debes configurar un sitio objetivo en la página de configuración antes de poder conectar productos.', 'chilevapo-andesvapor-connector'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=cvav-connector-settings'); ?>" class="button button-primary"><?php _e('Ir a Configuración', 'chilevapo-andesvapor-connector'); ?></a></p>
                </div>
            </div>
            <?php
            return;
        }
        error_log('CVAV DEBUG: render_products_page() - child site configured, continuing');
        
        // Obtener información del sitio configurado
        error_log('CVAV DEBUG: render_products_page() - getting available sites');
        $child_site_info = null;
        $available_sites = $connector->get_available_sites();
        error_log('CVAV DEBUG: render_products_page() - available sites count: ' . count($available_sites));

        error_log('CVAV DEBUG: render_products_page() - looking for child_site_id: ' . $child_site_id);
        
        foreach ($available_sites as $site) {
            error_log('CVAV DEBUG: render_products_page() - checking site: ' . print_r($site, true));
            // Los sitios son objetos WC_Multistore_Site con propiedades privadas
            $site_id = null;
            
            // Intentar acceder a la propiedad id usando reflexión
            try {
                $reflection = new ReflectionClass($site);
                $id_property = $reflection->getProperty('id');
                $id_property->setAccessible(true);
                $site_id = $id_property->getValue($site);
                error_log('CVAV DEBUG: render_products_page() - extracted site_id via reflection: ' . $site_id);
            } catch (Exception $e) {
                error_log('CVAV DEBUG: render_products_page() - reflection failed: ' . $e->getMessage());
                // Fallback: intentar métodos públicos si existen
                if (method_exists($site, 'get_id')) {
                    $site_id = $site->get_id();
                    error_log('CVAV DEBUG: render_products_page() - extracted site_id via get_id(): ' . $site_id);
                } elseif (method_exists($site, 'get_blog_id')) {
                    $site_id = $site->get_blog_id();
                    error_log('CVAV DEBUG: render_products_page() - extracted site_id via get_blog_id(): ' . $site_id);
                }
            }
            
            if ($site_id == $child_site_id) {
                $child_site_info = $site;
                error_log('CVAV DEBUG: render_products_page() - found child site info: ' . print_r($site, true));
                break;
            }
        }
        if (!$child_site_info) {
            error_log('CVAV DEBUG: render_products_page() - child site info not found');
        }
        
        error_log('CVAV DEBUG: render_products_page() - starting HTML output');
        ?>
        <div class="wrap">
            <h1><?php _e('Conectar Productos', 'chilevapo-andesvapor-connector'); ?></h1>
            
            <div class="cvav-notice">
                <p><strong><?php _e('Nota:', 'chilevapo-andesvapor-connector'); ?></strong> 
                <?php _e('Esta herramienta permite conectar productos entre Master (sitio principal) y el sitio configurado. Los productos deben tener SKUs idénticos para poder conectarlos.', 'chilevapo-andesvapor-connector'); ?></p>
            </div>

            <!-- Configuración de sitios -->
            <div class="cvav-section">
                <h2><?php _e('Configuración de Sitios', 'chilevapo-andesvapor-connector'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Sitio Principal (Master)', 'chilevapo-andesvapor-connector'); ?></th>
                        <td>
                            <strong><?php echo get_bloginfo('name'); ?></strong> 
                            <span class="cvav-badge cvav-badge-primary"><?php _e('Automático', 'chilevapo-andesvapor-connector'); ?></span>
                            <br>
                            <small><?php echo get_site_url(); ?> (ID: <?php echo get_current_blog_id(); ?>)</small>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Sitio Objetivo', 'chilevapo-andesvapor-connector'); ?></th>
                        <td>
                            <?php if ($child_site_info): ?>
                                <div class="cvav-site-display">
                                    <?php 
                                    // Extraer información del sitio usando reflexión
                                    $site_name = '';
                                    $site_url = '';
                                    try {
                                        $reflection = new ReflectionClass($child_site_info);
                                        
                                        $name_property = $reflection->getProperty('name');
                                        $name_property->setAccessible(true);
                                        $site_name = $name_property->getValue($child_site_info);
                                        
                                        $url_property = $reflection->getProperty('url');
                                        $url_property->setAccessible(true);
                                        $site_url = $url_property->getValue($child_site_info);
                                    } catch (Exception $e) {
                                        // Fallback a métodos públicos si existen
                                        if (method_exists($child_site_info, 'get_name')) {
                                            $site_name = $child_site_info->get_name();
                                        }
                                        if (method_exists($child_site_info, 'get_url')) {
                                            $site_url = $child_site_info->get_url();
                                        }
                                    }
                                    ?>
                                    <strong><?php echo esc_html($site_name); ?></strong>
                                    <span class="cvav-badge cvav-badge-success"><?php _e('Configurado', 'chilevapo-andesvapor-connector'); ?></span>
                                    <br>
                                    <small><?php echo esc_html($site_url); ?> (ID: <?php echo esc_html($child_site_id); ?>)</small>
                                </div>
                                <input type="hidden" id="child-site-selector" value="<?php echo esc_attr($child_site_id); ?>">
                                <p class="description">
                                    <?php _e('Sitio configurado en la página de configuración. Puedes cambiarlo desde', 'chilevapo-andesvapor-connector'); ?> 
                                    <a href="<?php echo admin_url('admin.php?page=cvav-connector-settings'); ?>"><?php _e('aquí', 'chilevapo-andesvapor-connector'); ?></a>.
                                </p>
                            <?php else: ?>
                                <div class="notice notice-error">
                                    <p><?php _e('El sitio configurado no está disponible. Verifica la configuración.', 'chilevapo-andesvapor-connector'); ?></p>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Productos con SKUs coincidentes -->
            <div class="cvav-section" id="matching-products-section">
                <h2><?php _e('Productos con SKUs Coincidentes', 'chilevapo-andesvapor-connector'); ?></h2>
                
                <div id="matching-products-loading" class="cvav-loading">
                    <p><?php _e('Cargando productos con SKUs coincidentes...', 'chilevapo-andesvapor-connector'); ?></p>
                </div>
                
                <div id="matching-products-content" style="display: none;">
                    <table class="widefat" id="matching-products-table">
                        <thead>
                            <tr>
                                <th><?php _e('Master - Nombre', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Master - ID Padre', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Master - SKU', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Sitio Secundario - Nombre', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Sitio Secundario - ID Padre', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Sitio Secundario - SKU', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Acciones', 'chilevapo-andesvapor-connector'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="matching-products-tbody">
                            <!-- Los productos con SKUs coincidentes se cargarán aquí -->
                        </tbody>
                    </table>
                </div>
                
                <div id="matching-products-empty" style="display: none;">
                    <p><?php _e('No se encontraron productos con SKUs coincidentes entre estos sitios.', 'chilevapo-andesvapor-connector'); ?></p>
                </div>
            </div>

            <!-- Productos conectados -->
            <div class="cvav-section" id="connected-products-section">
                <h2><?php _e('Productos Conectados', 'chilevapo-andesvapor-connector'); ?></h2>
                
                <div id="connected-products-loading" class="cvav-loading">
                    <p><?php _e('Cargando productos conectados...', 'chilevapo-andesvapor-connector'); ?></p>
                </div>
                
                <div id="connected-products-content" style="display: none;">
                    <table class="widefat" id="connected-products-table">
                        <thead>
                            <tr>
                                <th><?php _e('Chile Vapo - Nombre', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Chile Vapo - SKU', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Sitio Secundario - Nombre', 'chilevapo-andesvapor-connector'); ?></th>
                                <th><?php _e('Acciones', 'chilevapo-andesvapor-connector'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="connected-products-tbody">
                            <!-- Los productos conectados se cargarán aquí -->
                        </tbody>
                    </table>
                </div>
                
                <div id="connected-products-empty" style="display: none;">
                    <p><?php _e('No hay productos conectados entre estos sitios.', 'chilevapo-andesvapor-connector'); ?></p>
                </div>
            </div>

            <!-- Variables para JavaScript -->
            <script type="text/javascript">
                var cvav_products_nonce = '<?php echo wp_create_nonce('cvav_products_nonce'); ?>';
                var cvav_current_site_id = <?php echo get_current_blog_id(); ?>;
            </script>
        </div>
        <?php
        error_log('CVAV DEBUG: render_products_page() - HTML output completed');
    }
} 