<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para manejar la conexión de productos entre sitios
 */

class CVAV_Products {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_cvav_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_cvav_connect_products', array($this, 'ajax_connect_products'));
        add_action('wp_ajax_cvav_disconnect_products', array($this, 'ajax_disconnect_products'));
        add_action('wp_ajax_cvav_get_connected_products', array($this, 'ajax_get_connected_products'));
        add_action('wp_ajax_cvav_get_matching_products', array($this, 'ajax_get_matching_products'));
    }

    /**
     * Buscar productos en un sitio específico
     */
    public function search_products($site_id, $search_term, $limit = 20) {
        error_log('CVAV DEBUG: search_products() called with site_id: ' . $site_id . ', search_term: ' . $search_term);
        if (!$site_id || !$search_term) {
            error_log('CVAV DEBUG: search_products() - invalid parameters');
            return array();
        }

        // Cambiar al sitio especificado
        error_log('CVAV DEBUG: search_products() - switching to blog: ' . $site_id);
        switch_to_blog($site_id);

        $args = array(
            'post_type' => array('product', 'product_variation'),
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_sku',
                    'value' => $search_term,
                    'compare' => 'LIKE'
                )
            ),
            's' => $search_term
        );

        $query = new WP_Query($args);
        $products = array();
        error_log('CVAV DEBUG: search_products() - query found ' . $query->found_posts . ' posts');

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product) {
                    $products[] = array(
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'type' => $product->get_type(),
                        'price' => $product->get_price(),
                        'stock_status' => $product->get_stock_status(),
                        'permalink' => get_permalink()
                    );
                    error_log('CVAV DEBUG: search_products() - added product: ' . $product->get_name() . ' (ID: ' . $product->get_id() . ')');
                }
            }
        }

        wp_reset_postdata();
        error_log('CVAV DEBUG: search_products() - restoring to original blog');
        restore_current_blog();

        error_log('CVAV DEBUG: search_products() returning ' . count($products) . ' products');
        return $products;
    }

    /**
     * AJAX: Buscar productos
     */
    public function ajax_search_products() {
        error_log('CVAV DEBUG: ajax_search_products() called');
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cvav_products_nonce')) {
            error_log('CVAV DEBUG: ajax_search_products() - Invalid nonce');
            wp_die('Nonce inválido');
        }

        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            error_log('CVAV DEBUG: ajax_search_products() - Insufficient permissions');
            wp_die('Permisos insuficientes');
        }

        $site_id = intval($_POST['site_id']);
        $search_term = sanitize_text_field($_POST['search_term']);
        error_log('CVAV DEBUG: ajax_search_products() - site_id: ' . $site_id . ', search_term: ' . $search_term);

        if (!$site_id || !$search_term) {
            error_log('CVAV DEBUG: ajax_search_products() - Invalid parameters');
            wp_send_json_error('Parámetros inválidos');
        }

        error_log('CVAV DEBUG: ajax_search_products() - calling search_products()');
        $products = $this->search_products($site_id, $search_term);
        error_log('CVAV DEBUG: ajax_search_products() - found ' . count($products) . ' products');
        wp_send_json_success($products);
    }

    /**
     * Conectar productos entre sitios
     */
    public function connect_products($master_product_id, $child_product_id, $child_site_id) {
        error_log('CVAV DEBUG: connect_products() START - Master ID: ' . $master_product_id . ', Child ID: ' . $child_product_id . ', Child Site: ' . $child_site_id);
        
        if (!$master_product_id || !$child_product_id || !$child_site_id) {
            return array('success' => false, 'message' => 'Parámetros inválidos');
        }

        // PASO 1: Identificar el producto maestro real (si es variación, usar el padre)
        $original_master_id = $master_product_id;
        $master_product = wc_get_product($master_product_id);
        if (!$master_product) {
            return array('success' => false, 'message' => 'Producto maestro no encontrado');
        }

        // Si el maestro es una variación, obtener el producto padre variable
        if ($master_product->get_type() === 'variation') {
            $master_parent_id = $master_product->get_parent_id();
            $master_product = wc_get_product($master_parent_id);
            if (!$master_product) {
                return array('success' => false, 'message' => 'Producto padre maestro no encontrado');
            }
            $master_product_id = $master_parent_id;
            error_log('CVAV DEBUG: connect_products() - Master is variation, using parent ID: ' . $master_product_id);
        }

        // PASO 2: Identificar el producto hijo real (si es variación, usar el padre)
        $original_child_id = $child_product_id;
        switch_to_blog($child_site_id);
        $child_product = wc_get_product($child_product_id);
        
        if (!$child_product) {
            restore_current_blog();
            return array('success' => false, 'message' => 'Producto hijo no encontrado');
        }

        // Si el hijo es una variación, obtener el producto padre variable
        if ($child_product->get_type() === 'variation') {
            $child_parent_id = $child_product->get_parent_id();
            $child_product = wc_get_product($child_parent_id);
            if (!$child_product) {
                restore_current_blog();
                return array('success' => false, 'message' => 'Producto padre hijo no encontrado');
            }
            $child_product_id = $child_parent_id;
            error_log('CVAV DEBUG: connect_products() - Child is variation, using parent ID: ' . $child_product_id);
        }

        // PASO 3: Conectar el producto hijo al maestro usando WooCommerce Multistore
        error_log('CVAV DEBUG: connect_products() - Connecting child product ' . $child_product_id . ' to master ' . $master_product_id);
        
        $master_sku = $master_product->get_sku();
        $child_sku = $child_product->get_sku();
        
        // Establecer la conexión WooCommerce Multistore
        $child_product->update_meta_data('_woonet_network_is_child_product_id', $master_product_id);
        $child_product->update_meta_data('_woonet_network_is_child_product_sku', $master_sku);
        $child_product->update_meta_data('_woonet_network_is_child_site_id', get_current_blog_id());
        $child_product->update_meta_data('_woonet_network_is_child_product_url', admin_url("post.php?post={$master_product_id}&action=edit"));
        $child_product->save();
        
        error_log('CVAV DEBUG: connect_products() - Child product connected with WooCommerce Multistore metadata');

        // PASO 4: Volver al sitio maestro y configurar la sincronización
        restore_current_blog();
        
        // Configurar el producto maestro para sincronización
        $master_product->update_meta_data('_woonet_network_main_product', '1');
        
        $master_settings = $master_product->get_meta('_woonet_settings') ?: array();
        $master_settings["_woonet_publish_to_{$child_site_id}"] = 'yes';
        $master_settings["_woonet_publish_to_{$child_site_id}_child_inheir"] = 'yes';
        $master_settings["_woonet_{$child_site_id}_child_stock_synchronize"] = 'yes';
        
        $master_product->update_meta_data('_woonet_settings', $master_settings);
        $master_product->update_meta_data("_woonet_publish_to_{$child_site_id}", 'yes');
        $master_product->save();
        
        error_log('CVAV DEBUG: connect_products() - Master product configured for sync to site: ' . $child_site_id);

        // Activar sincronización completa de WooCommerce Multistore
        error_log('CVAV DEBUG: connect_products() - Ejecutando sincronización completa de WooCommerce Multistore');
        
        // Usar la función que replica exactamente WooCommerce Multistore para sincronización completa
        $sync_result = $this->force_woocommerce_multistore_sync($master_product_id, $child_site_id);
        if (!$sync_result) {
            error_log('CVAV DEBUG: connect_products() - Fallback al método anterior');
            // Fallback al método anterior si la nueva función falla
            $this->trigger_master_product_sync($master_product_id, $child_site_id);
        }

        // Ya no necesitamos manejar variaciones manualmente porque WooCommerce Multistore lo hace
        // if ($master_product->get_type() === 'variable' && $child_product->get_type() === 'variable') {
        //     try {
        //         $this->sync_variations_between_products($master_product, $child_product, $child_site_id);
        //     } catch (Exception $e) {
        //         // Continuar con la conexión principal aunque falle la sincronización de variaciones
        //     }
        // }

        // PASO 7: Preparar información de respuesta
        $connected_products = array(
            array(
                'master_id' => $master_product_id,
                'master_name' => $master_product->get_name(),
                'master_sku' => $master_sku,
                'master_type' => $master_product->get_type(),
                'child_id' => $child_product_id,
                'child_name' => $child_product->get_name(),
                'child_sku' => $child_sku,
                'child_type' => $child_product->get_type(),
                'child_site_id' => $child_site_id
            )
        );

        // PASO 7: Determinar variaciones a eliminar de la tabla
        $removed_variations = array();
        $connected_variations = array();
        
        // Obtener variaciones del maestro para eliminar de la tabla
        if ($master_product->get_type() === 'variable') {
            $master_variations = $master_product->get_children();
            error_log('CVAV DEBUG: connect_products() - Master has ' . count($master_variations) . ' variations to remove from table');
            
            foreach ($master_variations as $master_variation_id) {
                $master_variation = wc_get_product($master_variation_id);
                if ($master_variation) {
                    $removed_variations[] = array(
                        'master_id' => $master_variation_id,
                        'child_id' => 0,
                        'type' => 'master_variation',
                        'sku' => $master_variation->get_sku()
                    );
                }
            }
            
            // Contar las variaciones que se sincronizarán
            $connected_variations = array_fill(0, count($master_variations), array('placeholder' => true));
        }

        // Obtener variaciones del hijo para eliminar de la tabla
        switch_to_blog($child_site_id);
        $child_variations = $child_product->get_children();
        error_log('CVAV DEBUG: connect_products() - Child has ' . count($child_variations) . ' variations to remove from table');
        
        foreach ($child_variations as $child_variation_id) {
            $child_variation = wc_get_product($child_variation_id);
            if ($child_variation) {
                $removed_variations[] = array(
                    'master_id' => 0,
                    'child_id' => $child_variation_id,
                    'type' => 'child_variation',
                    'sku' => $child_variation->get_sku()
                );
            }
        }
        restore_current_blog();

        // Limpiar caché
        wp_cache_flush();

        $total_connected = count($connected_products) + count($connected_variations);
        $message = sprintf('Producto conectado exitosamente. Se sincronizará %d producto principal y %d variaciones via WooCommerce Multistore.', 
                          count($connected_products), count($connected_variations));

        error_log('CVAV DEBUG: connect_products() COMPLETED - Connected: ' . count($connected_products) . ' products, ' . count($connected_variations) . ' variations');

        return array(
            'success' => true,
            'message' => $message,
            'connected_products' => $connected_products,
            'connected_variations' => $connected_variations,
            'total_connected' => $total_connected,
            'removed_variations' => $removed_variations,
            'master_parent_id' => $master_product_id,
            'child_parent_id' => $child_product_id,
            'original_master_id' => $original_master_id,
            'original_child_id' => $original_child_id
        );
    }

    /**
     * AJAX: Conectar productos
     */
    public function ajax_connect_products() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cvav_products_nonce')) {
            wp_die('Nonce inválido');
        }

        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permisos insuficientes');
        }

        $master_product_id = intval($_POST['master_product_id']);
        $child_product_id = intval($_POST['child_product_id']);
        $child_site_id = intval($_POST['child_site_id']);

        if (!$master_product_id || !$child_product_id || !$child_site_id) {
            wp_send_json_error('Parámetros inválidos');
        }

        $result = $this->connect_products($master_product_id, $child_product_id, $child_site_id);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'connected_products' => $result['connected_products'],
                'connected_variations' => $result['connected_variations']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Desconectar productos
     */
    public function disconnect_products($master_product_id, $child_product_id, $child_site_id) {
        if (!$master_product_id || !$child_product_id || !$child_site_id) {
            return false;
        }

        // Cambiar al sitio hijo
        switch_to_blog($child_site_id);
        $child_product = wc_get_product($child_product_id);
        
        if ($child_product) {
            // Limpiar metadatos del producto principal
            $child_product->delete_meta_data('_woonet_network_is_child_product_id');
            $child_product->delete_meta_data('_woonet_network_is_child_product_sku');
            $child_product->delete_meta_data('_woonet_network_is_child_site_id');
            $child_product->delete_meta_data('_woonet_network_is_child_product_url');
            $child_product->delete_meta_data('_cvav_force_sync');
            $child_product->delete_meta_data('_cvav_last_master_update');
            $child_product->save();
            
            // Si es un producto variable, también desconectar todas sus variaciones
            if ($child_product->get_type() === 'variable') {
                $child_variations = $child_product->get_children();
                foreach ($child_variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $variation->delete_meta_data('_woonet_network_is_child_product_id');
                        $variation->delete_meta_data('_woonet_network_is_child_product_sku');
                        $variation->delete_meta_data('_woonet_network_is_child_site_id');
                        $variation->delete_meta_data('_woonet_network_is_child_product_url');
                        $variation->delete_meta_data('_cvav_force_sync');
                        $variation->delete_meta_data('_cvav_last_master_update');
                        $variation->save();
                    }
                }
            }
        }

        restore_current_blog();

        // Configurar producto maestro
        $master_product = wc_get_product($master_product_id);
        if ($master_product) {
            $settings = $master_product->get_meta('_woonet_settings') ?: array();
            
            // Remover configuración para el sitio hijo
            unset($settings["_woonet_publish_to_{$child_site_id}"]);
            unset($settings["_woonet_publish_to_{$child_site_id}_child_inheir"]);
            unset($settings["_woonet_{$child_site_id}_child_stock_synchronize"]);
            
            $master_product->update_meta_data('_woonet_settings', $settings);
            $master_product->delete_meta_data("_woonet_publish_to_{$child_site_id}");
            $master_product->save();
            
            // Si es un producto variable, también limpiar configuración de sus variaciones
            if ($master_product->get_type() === 'variable') {
                $master_variations = $master_product->get_children();
                foreach ($master_variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $variation_settings = $variation->get_meta('_woonet_settings') ?: array();
                        unset($variation_settings["_woonet_publish_to_{$child_site_id}"]);
                        unset($variation_settings["_woonet_publish_to_{$child_site_id}_child_inheir"]);
                        unset($variation_settings["_woonet_{$child_site_id}_child_stock_synchronize"]);
                        $variation->update_meta_data('_woonet_settings', $variation_settings);
                        $variation->delete_meta_data("_woonet_publish_to_{$child_site_id}");
                        $variation->save();
                    }
                }
            }
        }

        // Limpiar caché
        wp_cache_flush();

        return true;
    }

    /**
     * AJAX: Desconectar productos
     */
    public function ajax_disconnect_products() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cvav_products_nonce')) {
            wp_die('Nonce inválido');
        }

        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permisos insuficientes');
        }

        $master_product_id = intval($_POST['master_product_id']);
        $child_product_id = intval($_POST['child_product_id']);
        $child_site_id = intval($_POST['child_site_id']);

        if (!$master_product_id || !$child_product_id || !$child_site_id) {
            wp_send_json_error('Parámetros inválidos');
        }

        $result = $this->disconnect_products($master_product_id, $child_product_id, $child_site_id);
        
        if ($result) {
            wp_send_json_success('Productos desconectados exitosamente');
        } else {
            wp_send_json_error('Error al desconectar productos');
        }
    }

    /**
     * Obtener productos conectados entre sitios
     */
    public function get_connected_products($child_site_id = null) {
        if (!$child_site_id) {
            return array();
        }

        $connections = array();
        $current_site_id = get_current_blog_id();

        // Cambiar al sitio hijo para buscar productos conectados
        switch_to_blog($child_site_id);
        
        // Buscar solo productos principales (excluir variaciones)
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_woonet_network_is_child_product_id',
                    'compare' => 'EXISTS'
                )
            )
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $child_product = wc_get_product(get_the_ID());
                
                if ($child_product) {
                    $master_product_id = $child_product->get_meta('_woonet_network_is_child_product_id');
                    
                    if ($master_product_id) {
                        // Obtener información del producto maestro
                        restore_current_blog();
                        $master_product = wc_get_product($master_product_id);
                        
                        if ($master_product) {
                            $connections[] = array(
                                'master_product_id' => $master_product_id,
                                'master_product_name' => $master_product->get_name(),
                                'master_product_sku' => $master_product->get_sku(),
                                'master_product_type' => $master_product->get_type(),
                                'master_edit_url' => admin_url("post.php?post={$master_product_id}&action=edit"),
                                'child_product_id' => $child_product->get_id(),
                                'child_product_name' => $child_product->get_name(),
                                'child_product_sku' => $child_product->get_sku(),
                                'child_product_type' => $child_product->get_type(),
                                'child_edit_url' => get_admin_url($child_site_id, "post.php?post={$child_product->get_id()}&action=edit"),
                                'child_site_id' => $child_site_id,
                                'connected_date' => $child_product->get_meta('_woonet_network_is_child_product_id')
                            );
                        }
                        
                        switch_to_blog($child_site_id);
                    }
                }
            }
        }

        wp_reset_postdata();
        restore_current_blog();

        return $connections;
    }

    /**
     * AJAX: Obtener productos conectados
     */
    public function ajax_get_connected_products() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cvav_products_nonce')) {
            wp_die('Nonce inválido');
        }

        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permisos insuficientes');
        }

        $child_site_id = isset($_POST['child_site_id']) ? intval($_POST['child_site_id']) : null;
        $connections = $this->get_connected_products($child_site_id);
        
        wp_send_json_success($connections);
    }

    /**
     * Verificar si dos productos están conectados
     */
    public function are_products_connected($master_product_id, $child_product_id, $child_site_id) {
        if (!$master_product_id || !$child_product_id || !$child_site_id) {
            return false;
        }

        // Obtener el master product
        $master_product = wc_get_product($master_product_id);
        if (!$master_product) {
            return false;
        }

        // Determinar el ID del master padre (si es variación)
        $master_parent_id = $master_product_id;
        if ($master_product->get_type() === 'variation') {
            $master_parent_id = $master_product->get_parent_id();
        }

        // Cambiar al sitio child
        switch_to_blog($child_site_id);
        
        $child_product = wc_get_product($child_product_id);
        if (!$child_product) {
            restore_current_blog();
            return false;
        }

        // Determinar el ID del child padre (si es variación)
        $child_parent_id = $child_product_id;
        if ($child_product->get_type() === 'variation') {
            $child_parent_id = $child_product->get_parent_id();
        }

        // Verificar si el child (o su padre) está conectado al master (o su padre)
        $child_to_check = wc_get_product($child_parent_id);
        $is_connected = false;
        
        if ($child_to_check) {
            $connected_master_id = $child_to_check->get_meta('_woonet_network_is_child_product_id');
            $is_connected = ($connected_master_id && ($connected_master_id == $master_parent_id));
        }
        
        restore_current_blog();
        return $is_connected;
    }

    /**
     * Obtener productos con SKUs coincidentes entre sitios
     */
    public function get_matching_products($child_site_id) {
        if (!$child_site_id) {
            return array();
        }

        $matches = array();
        $current_site_id = get_current_blog_id();

        // Obtener todos los productos del sitio actual (Master) - incluir variaciones
        $master_args = array(
            'post_type' => array('product', 'product_variation'),
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC'
        );

        $master_query = new WP_Query($master_args);
        error_log('CVAV DEBUG: get_matching_products() - master query found ' . $master_query->found_posts . ' products');

        if ($master_query->have_posts()) {
            while ($master_query->have_posts()) {
                $master_query->the_post();
                $master_product = wc_get_product(get_the_ID());
                
                if ($master_product) {
                    $master_sku = $master_product->get_sku();
                    
                    // Para variaciones, requerir SKU. Para productos padres, permitir sin SKU
                    if ($master_product->get_type() === 'variation' && empty(trim($master_sku))) {
                        continue; // Saltar variaciones sin SKU
                    }
                    
                    $current_master_site_id = get_current_blog_id();
                    
                    // VERIFICACIÓN: Excluir productos huérfanos (variaciones sin padre)
                    if ($master_product->get_type() === 'variation') {
                        $master_parent = wc_get_product($master_product->get_parent_id());
                        if (!$master_parent) {
                            error_log("CVAV DEBUG: Excluyendo variación huérfana con ID: " . $master_product->get_id());
                            continue; // Saltar esta variación huérfana
                        }
                    }
                    
                    // Si es variación, usar el padre
                    $master_connection_id = $master_product->get_id();
                    $master_parent_info = '';
                    $master_display_id = $master_product->get_id();
                    $master_edit_id = $master_product->get_id();
                    if ($master_product->get_type() === 'variation') {
                        $master_parent = wc_get_product($master_product->get_parent_id());
                        if ($master_parent) {
                            $master_connection_id = $master_parent->get_id();
                            $master_parent_info = ' (Variación de: ' . $master_parent->get_name() . ')';
                            $master_display_id = $master_parent->get_id();
                            $master_edit_id = $master_parent->get_id();
                        }
                    }
                    
                    // Verificar que el master product existe ANTES de cambiar al child site
                    $master_product_to_check = wc_get_product($master_connection_id);
                    if (!$master_product_to_check) {
                        error_log("CVAV DEBUG: Master product $master_connection_id no existe en master site, saltando");
                        continue;
                    }
                    
                    switch_to_blog($child_site_id);
                    
                    // Solo procesar si el master tiene SKU para evitar consultas muy lentas
                    if (!empty(trim($master_sku))) {
                        // Obtener productos child con el mismo SKU, EXCLUYENDO los ya conectados
                        global $wpdb;
                        $excluded_products = $wpdb->get_col($wpdb->prepare("
                            SELECT DISTINCT p.ID 
                            FROM {$wpdb->posts} p
                            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                            WHERE pm.meta_key = '_woonet_network_is_child_product_id'
                            AND p.post_type IN ('product', 'product_variation')
                            AND p.post_status = 'publish'
                        "));
                        
                        // Si es variación, también excluir el padre si está conectado
                        if ($master_product->get_type() === 'variation') {
                            $master_parent_id = $master_product->get_parent_id();
                            $parent_connected_products = $wpdb->get_col($wpdb->prepare("
                                SELECT DISTINCT p.ID 
                                FROM {$wpdb->posts} p
                                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                                WHERE pm.meta_key = '_woonet_network_is_child_product_id'
                                AND pm.meta_value = %d
                                AND p.post_type IN ('product', 'product_variation')
                                AND p.post_status = 'publish'
                            ", $master_parent_id));
                            $excluded_products = array_merge($excluded_products, $parent_connected_products);
                        }
                        
                        $excluded_ids = !empty($excluded_products) ? implode(',', $excluded_products) : '0';
                        
                        $child_args = array(
                            'post_type' => array('product', 'product_variation'),
                            'post_status' => 'publish',
                            'posts_per_page' => 20, // Limitar resultados
                            'post__not_in' => $excluded_products, // Excluir productos ya conectados
                            'meta_query' => array(
                                array(
                                    'key' => '_sku',
                                    'value' => $master_sku,
                                    'compare' => '='
                                )
                            )
                        );
                        
                        $child_query = new WP_Query($child_args);
                        if ($child_query->have_posts()) {
                            while ($child_query->have_posts()) {
                                $child_query->the_post();
                                $child_product = wc_get_product(get_the_ID());
                                if ($child_product) {
                                    // Si es variación, usar el padre
                                    $child_connection_id = $child_product->get_id();
                                    $child_parent_info = '';
                                    $child_display_id = $child_product->get_id();
                                    $child_edit_id = $child_product->get_id();
                                    if ($child_product->get_type() === 'variation') {
                                        $child_parent = wc_get_product($child_product->get_parent_id());
                                        if ($child_parent) {
                                            $child_connection_id = $child_parent->get_id();
                                            $child_parent_info = ' (Variación de: ' . $child_parent->get_name() . ')';
                                            $child_display_id = $child_parent->get_id();
                                            $child_edit_id = $child_parent->get_id();
                                        }
                                    }
                                    
                                    // Construir nombres correctos para las variaciones
                                    $master_name = $master_product->get_name();
                                    $child_name = $child_product->get_name();
                                    
                                    // Si es una variación, usar el nombre completo de la variación (que ya incluye los atributos)
                                    if ($master_product->get_type() === 'variation') {
                                        // Usar directamente el nombre de la variación que ya incluye los atributos
                                        $master_name = $master_product->get_name();
                                    }
                                    
                                    if ($child_product->get_type() === 'variation') {
                                        // Usar directamente el nombre de la variación que ya incluye los atributos
                                        $child_name = $child_product->get_name();
                                    }
                                    
                                    $matches[] = array(
                                        'master_product_id' => $master_connection_id,
                                        'master_product_name' => $master_name,
                                        'master_product_sku' => $master_sku,
                                        'master_product_type' => $master_product->get_type(),
                                        'master_parent_id' => $master_product->get_type() === 'variation' ? $master_product->get_parent_id() : 0,
                                        'master_display_id' => $master_display_id,
                                        'master_edit_url' => get_admin_url($current_master_site_id, "post.php?post={$master_edit_id}&action=edit"),
                                        'child_product_id' => $child_connection_id,
                                        'child_product_name' => $child_name,
                                        'child_product_sku' => $child_product->get_sku(),
                                        'child_product_type' => $child_product->get_type(),
                                        'child_parent_id' => $child_product->get_type() === 'variation' ? $child_product->get_parent_id() : 0,
                                        'child_display_id' => $child_display_id,
                                        'child_edit_url' => get_admin_url($child_site_id, "post.php?post={$child_edit_id}&action=edit"),
                                        'child_site_id' => $child_site_id
                                    );
                                }
                            }
                            wp_reset_postdata();
                        }
                    }
                    restore_current_blog();
                }
            }
        }
        wp_reset_postdata();
        // Ordenar los resultados alfabéticamente por el nombre del producto maestro
        usort($matches, function($a, $b) {
            $name_a = preg_replace('/\s*\(Variación de:.*?\)$/', '', $a['master_product_name']);
            $name_b = preg_replace('/\s*\(Variación de:.*?\)$/', '', $b['master_product_name']);
            return strcasecmp($name_a, $name_b);
        });
        
        return $matches;
    }

    /**
     * AJAX: Obtener productos con SKUs coincidentes
     */
    public function ajax_get_matching_products() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cvav_products_nonce')) {
            wp_die('Nonce inválido');
        }

        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permisos insuficientes');
        }

        $child_site_id = intval($_POST['child_site_id']);
        if (!$child_site_id) {
            wp_send_json_error('ID del sitio hijo inválido');
        }

        $matches = $this->get_matching_products($child_site_id);
        
        wp_send_json_success($matches);
    }

    /**
     * Forzar actualización del producto en el sitio hijo
     */
    private function force_product_update($child_product) {
        error_log('CVAV DEBUG: force_product_update() called for product ID: ' . $child_product->get_id());
        
        if (!$child_product) {
            error_log('CVAV DEBUG: force_product_update() - Product not found.');
            return false;
        }

        // Obtener el ID del producto maestro
        $master_product_id = $child_product->get_meta('_woonet_network_is_child_product_id');
        if (!$master_product_id) {
            error_log('CVAV DEBUG: force_product_update() - Master product ID not found for child product ID: ' . $child_product->get_id());
            return false;
        }

        // Obtener el sitio hijo actual
        $current_blog_id = get_current_blog_id();
        
        try {
            // Primero, simular la actualización del producto maestro para activar WooCommerce Multistore
            restore_current_blog();
            $master_product = wc_get_product($master_product_id);
            
            if ($master_product) {
                error_log('CVAV DEBUG: force_product_update() - Triggering master product update to sync with child sites');
                
                // Simular la actualización del producto maestro que activa la sincronización
                $master_product->set_date_modified(current_time('mysql'));
                $master_product->save();
                
                // Ejecutar hooks específicos de WooCommerce Multistore
                do_action('woocommerce_update_product', $master_product->get_id(), $master_product);
                do_action('save_post_product', $master_product->get_id(), get_post($master_product->get_id()), true);
                
                // Hooks específicos de WooCommerce Multistore si existen
                if (function_exists('wc_multistore_sync_product_to_sites')) {
                    wc_multistore_sync_product_to_sites($master_product->get_id());
                } elseif (class_exists('WC_Multistore_Product_Admin')) {
                    $multistore_admin = new WC_Multistore_Product_Admin();
                    if (method_exists($multistore_admin, 'sync_product_to_child_sites')) {
                        $multistore_admin->sync_product_to_child_sites($master_product->get_id());
                    }
                }
                
                error_log('CVAV DEBUG: force_product_update() - Master product update triggered successfully');
            }
            
            // Volver al sitio hijo
            switch_to_blog($current_blog_id);
            
            // Ahora actualizar el producto hijo para asegurar que reciba la sincronización
            $child_product->set_date_modified(current_time('mysql'));
            $child_product->update_meta_data('_cvav_force_sync', time());
            $child_product->update_meta_data('_cvav_last_master_update', time());
            $child_product->save();
            
            // Ejecutar hooks de sincronización en el sitio hijo
            do_action('cvav_product_connected', $child_product->get_id(), $master_product_id, $current_blog_id);
            do_action('woocommerce_update_product', $child_product->get_id(), $child_product);
            
            // Limpiar cachés
            wc_delete_product_transients($child_product->get_id());
            wp_cache_delete($child_product->get_id(), 'posts');
            
            // Si el producto es variable, también actualizar sus variaciones
            if ($child_product->get_type() === 'variable') {
                $child_variations = $child_product->get_children();
                foreach ($child_variations as $variation_id) {
                    wc_delete_product_transients($variation_id);
                    wp_cache_delete($variation_id, 'posts');
                }
                error_log('CVAV DEBUG: force_product_update() - Cleared cache for ' . count($child_variations) . ' variations');
            }
            
            error_log('CVAV DEBUG: force_product_update() - Product update forced successfully for product ID: ' . $child_product->get_id());
            return true;
            
        } catch (Exception $e) {
            error_log('CVAV DEBUG: force_product_update() - Exception occurred: ' . $e->getMessage());
            // Asegurar que volvemos al sitio hijo en caso de error
            switch_to_blog($current_blog_id);
            return false;
        }
    }

    /**
     * Sincronizar variaciones entre productos variables
     */
    private function sync_variations_between_products($master_product, $child_product, $child_site_id) {
        error_log('CVAV DEBUG: sync_variations_between_products() - Starting variation sync');
        
        // Cambiar al child site para ejecutar la sincronización
        $current_site_id = get_current_blog_id();
        switch_to_blog($child_site_id);
        
        // Verificar que tenemos referencias válidas a los productos
        if (!$master_product || !$child_product) {
            error_log('CVAV DEBUG: sync_variations_between_products() - Invalid product references');
            restore_current_blog();
            return;
        }
        
        // Verificar que ambos productos sean válidos
        if (!$master_product || !$child_product) {
            error_log('CVAV DEBUG: sync_variations_between_products() - Invalid product references');
            restore_current_blog();
            return;
        }
        
        // Verificar que ambos productos sean variables
        if ($master_product->get_type() !== 'variable' || $child_product->get_type() !== 'variable') {
            error_log('CVAV DEBUG: sync_variations_between_products() - One or both products are not variable');
            restore_current_blog();
            return;
        }
        
        // Si el producto hijo es simple pero tiene el mismo SKU que una variación del maestro, convertirlo a variable
        $child_sku = $child_product->get_sku();
        if ($child_product->get_type() === 'simple' && $child_sku) {
            error_log('CVAV DEBUG: sync_variations_between_products() - Child product is simple with SKU: ' . $child_sku . ', converting to variable');
            
            // Convertir el producto hijo a variable
            $child_product = $this->convert_simple_to_variable($child_product, $master_product);
            
            if (!$child_product || $child_product->get_type() !== 'variable') {
                error_log('CVAV DEBUG: sync_variations_between_products() - Failed to convert child product to variable');
                restore_current_blog();
                return;
            }
            
            error_log('CVAV DEBUG: sync_variations_between_products() - Child product successfully converted to variable');
        }
        
        // Primero, copiar atributos del producto maestro al hijo si no los tiene
        $this->copy_product_attributes_and_taxonomies($master_product, $child_product->get_id());
        
        // Obtener variaciones del producto maestro
        $master_variations = $master_product->get_children();
        error_log('CVAV DEBUG: sync_variations_between_products() - Master has ' . count($master_variations) . ' variations');
        
        // Obtener variaciones del producto hijo
        $child_variations = $child_product->get_children();
        error_log('CVAV DEBUG: sync_variations_between_products() - Child has ' . count($child_variations) . ' variations');
        
        // Crear un mapa de SKUs para las variaciones del hijo
        $child_variation_sku_map = array();
        foreach ($child_variations as $child_variation_id) {
            $child_variation = wc_get_product($child_variation_id);
            if ($child_variation && $child_variation->get_sku()) {
                $child_variation_sku_map[$child_variation->get_sku()] = $child_variation_id;
            }
        }
        
        // Conectar variaciones con SKUs coincidentes y crear las faltantes
        foreach ($master_variations as $master_variation_id) {
            $master_variation = wc_get_product($master_variation_id);
            if ($master_variation && $master_variation->get_sku()) {
                $master_variation_sku = $master_variation->get_sku();
                
                if (isset($child_variation_sku_map[$master_variation_sku])) {
                    // La variación ya existe en el producto hijo - conectarla
                    $child_variation_id = $child_variation_sku_map[$master_variation_sku];
                    $child_variation = wc_get_product($child_variation_id);
                    
                    if ($child_variation) {
                        // Conectar la variación usando WooCommerce Multistore
                        $child_variation->update_meta_data('_woonet_network_is_child_product_id', $master_variation_id);
                        $child_variation->update_meta_data('_woonet_network_is_child_product_sku', $master_variation_sku);
                        $child_variation->update_meta_data('_woonet_network_is_child_site_id', get_current_blog_id());
                        $child_variation->update_meta_data('_woonet_network_is_child_product_url', admin_url("post.php?post={$master_variation_id}&action=edit"));
                        $child_variation->save();
                        
                        error_log('CVAV DEBUG: sync_variations_between_products() - Connected existing variation: ' . $master_variation->get_name() . ' <-> ' . $child_variation->get_name());
                    }
                } else {
                    // La variación no existe en el producto hijo - crearla
                    error_log('CVAV DEBUG: sync_variations_between_products() - Creating missing variation for SKU: ' . $master_variation_sku);
                    
                    $new_variation_id = $this->create_variation_copy($child_product->get_id(), $master_variation);
                    
                    if ($new_variation_id) {
                        // Conectar la nueva variación
                        $new_variation = wc_get_product($new_variation_id);
                        $new_variation->update_meta_data('_woonet_network_is_child_product_id', $master_variation_id);
                        $new_variation->update_meta_data('_woonet_network_is_child_product_sku', $master_variation_sku);
                        $new_variation->update_meta_data('_woonet_network_is_child_site_id', get_current_blog_id());
                        $new_variation->update_meta_data('_woonet_network_is_child_product_url', admin_url("post.php?post={$master_variation_id}&action=edit"));
                        $new_variation->save();
                        
                        error_log('CVAV DEBUG: sync_variations_between_products() - Created and connected new variation: ' . $master_variation->get_name() . ' <-> ' . $new_variation->get_name());
                    }
                }
            }
        }
        
        // En lugar de forzar el guardado del producto hijo (que causa problemas con WooCommerce Multistore),
        // confiar en la sincronización automática que se activa en trigger_master_product_sync
        error_log('CVAV DEBUG: sync_variations_between_products() - Variation sync completed, relying on automatic sync');
        
        // Solo limpiar cachés para reflejar cambios sin guardar el producto
        if ($child_product) {
            $child_product_id = $child_product->get_id();
            $child_variations = $child_product->get_children();
            error_log('CVAV DEBUG: sync_variations_between_products() - Producto hijo tiene ' . count($child_variations) . ' variaciones después de la sincronización');
            
            // Forzar la regeneración de las variaciones del producto padre
            $this->force_regenerate_variations($child_product_id);
            
            // Limpiar cachés nuevamente para asegurar que los cambios se reflejen
            wc_delete_product_transients($child_product_id);
            
            error_log('CVAV DEBUG: sync_variations_between_products() - Cachés limpiados para producto hijo');
        }
        
        error_log('CVAV DEBUG: sync_variations_between_products() - Variation sync completed');
        restore_current_blog();
    }

    /**
     * Forzar la regeneración de las variaciones de un producto variable
     */
    private function force_regenerate_variations($product_id) {
        try {
            $product = wc_get_product($product_id);
            if (!$product || $product->get_type() !== 'variable') {
                return false;
            }
            
            // Obtener todas las variaciones del producto
            $variations = $product->get_children();
            error_log("CVAV DEBUG: force_regenerate_variations() - Producto ID: $product_id tiene " . count($variations) . " variaciones");
            
            // Forzar la actualización del meta _children
            update_post_meta($product_id, '_children', $variations);
            
            // También actualizar el meta _product_attributes si es necesario
            $attributes = $product->get_attributes();
            if (!empty($attributes)) {
                update_post_meta($product_id, '_product_attributes', $attributes);
            }
            
            // Limpiar cachés
            wc_delete_product_transients($product_id);
            foreach ($variations as $variation_id) {
                wc_delete_product_transients($variation_id);
            }
            
            // Forzar la actualización del producto para activar hooks de WooCommerce
            $product->set_date_modified(current_time('mysql'));
            $product->save();
            
            error_log("CVAV DEBUG: force_regenerate_variations() - Variaciones regeneradas para producto ID: $product_id");
            return true;
            
        } catch (Exception $e) {
            error_log("CVAV DEBUG: force_regenerate_variations() - Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Activar sincronización usando WooCommerce Multistore específicamente
     */
    private function trigger_master_product_sync($master_product_id, $child_site_id) {
        error_log('CVAV DEBUG: trigger_master_product_sync() called for master ID: ' . $master_product_id . ' to child site: ' . $child_site_id);
        
        try {
            // Cambiar al sitio maestro temporalmente
            $current_blog_id = get_current_blog_id();
            restore_current_blog();
            
            $master_product = wc_get_product($master_product_id);
            if (!$master_product) {
                error_log('CVAV DEBUG: trigger_master_product_sync() - Master product not found: ' . $master_product_id);
                switch_to_blog($current_blog_id);
                return false;
            }
            
            error_log('CVAV DEBUG: trigger_master_product_sync() - Triggering WooCommerce Multistore sync for: ' . $master_product->get_name());
            
            // Método simple y directo: Ejecutar el hook principal que WooCommerce Multistore escucha
            // Este es el hook que se ejecuta cuando se guarda un producto en el admin
            do_action('woocommerce_process_product_meta', $master_product_id);
            
            // Ejecutar también el hook de actualización de producto
            do_action('woocommerce_update_product', $master_product_id, $master_product);
            
            // Hook de guardado de post que muchos plugins multistore monitean
            do_action('save_post_product', $master_product_id, get_post($master_product_id), true);
            
            // Si es un producto variable, también sincronizar sus variaciones
            if ($master_product->get_type() === 'variable') {
                error_log('CVAV DEBUG: trigger_master_product_sync() - Master is variable product, syncing variations');
                $master_variations = $master_product->get_children();
                
                foreach ($master_variations as $variation_id) {
                    // Obtener referencia fresca de la variación
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        // Ejecutar hooks para cada variación de forma más cuidadosa
                        do_action('woocommerce_process_product_meta', $variation_id);
                        do_action('woocommerce_update_product', $variation_id, $variation);
                        
                        // Solo ejecutar save_post si el post existe
                        $variation_post = get_post($variation_id);
                        if ($variation_post) {
                            do_action('save_post_product_variation', $variation_id, $variation_post, true);
                        }
                        
                        error_log('CVAV DEBUG: trigger_master_product_sync() - Synced variation: ' . $variation->get_name());
                    } else {
                        error_log('CVAV DEBUG: trigger_master_product_sync() - Could not get variation reference for ID: ' . $variation_id);
                    }
                }
            }
            
            // Volver al sitio hijo
            switch_to_blog($current_blog_id);
            
            error_log('CVAV DEBUG: trigger_master_product_sync() - WooCommerce Multistore sync triggered successfully');
            return true;
            
        } catch (Exception $e) {
            error_log('CVAV DEBUG: trigger_master_product_sync() - Exception: ' . $e->getMessage());
            // Asegurar que volvemos al sitio correcto
            switch_to_blog($current_blog_id);
            return false;
        }
    }

    /**
     * Forzar sincronización usando WooCommerce Multistore como lo hace naturalmente
     */
    private function force_woocommerce_multistore_sync($master_product_id, $child_site_id) {
        try {
            error_log("CVAV DEBUG: force_woocommerce_multistore_sync() - Iniciando sincronización completa para Master: $master_product_id, Child Site: $child_site_id");
            
            // Obtener el producto master
            $master_product = wc_get_product($master_product_id);
            if (!$master_product) {
                error_log("CVAV DEBUG: force_woocommerce_multistore_sync() - Master product no encontrado");
                return false;
            }
            
            // Verificar que tenemos la clase necesaria de WooCommerce Multistore
            $classname = wc_multistore_get_product_class_name('master', $master_product->get_type());
            if (!$classname || !class_exists($classname)) {
                error_log("CVAV DEBUG: force_woocommerce_multistore_sync() - Clase WooCommerce Multistore no encontrada: $classname");
                return false;
            }
            
            error_log("CVAV DEBUG: force_woocommerce_multistore_sync() - Usando clase: $classname");
            
            // Crear la instancia del producto multistore
            $multistore_product = new $classname($master_product);
            
            // Configurar el producto para sincronización completa
            $master_settings = $master_product->get_meta('_woonet_settings') ?: array();
            $master_settings["_woonet_publish_to_{$child_site_id}"] = 'yes';
            $master_settings["_woonet_publish_to_{$child_site_id}_child_inheir"] = 'yes';
            $master_settings["_woonet_{$child_site_id}_child_stock_synchronize"] = 'yes';
            
            $master_product->update_meta_data('_woonet_settings', $master_settings);
            $master_product->update_meta_data("_woonet_publish_to_{$child_site_id}", 'yes');
            $master_product->save();
            
            error_log("CVAV DEBUG: force_woocommerce_multistore_sync() - Configuración de sincronización aplicada");
            
            // Para productos variables, configurar también las variaciones
            if ($master_product->get_type() === 'variable') {
                error_log("CVAV DEBUG: force_woocommerce_multistore_sync() - Configurando variaciones para sincronización");
                
                $variations = $master_product->get_children();
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation && $variation->get_type() === 'variation') {
                        // Configurar la variación para sincronización
                        $variation_settings = $variation->get_meta('_woonet_settings') ?: array();
                        $variation_settings["_woonet_publish_to_{$child_site_id}"] = 'yes';
                        $variation_settings["_woonet_publish_to_{$child_site_id}_child_inheir"] = 'yes';
                        $variation_settings["_woonet_{$child_site_id}_child_stock_synchronize"] = 'yes';
                        
                        $variation->update_meta_data('_woonet_settings', $variation_settings);
                        $variation->update_meta_data("_woonet_publish_to_{$child_site_id}", 'yes');
                        $variation->save();
                        
                        error_log("CVAV DEBUG: force_woocommerce_multistore_sync() - Configurada variación: $variation_id");
                    }
                }
            }
            
            // Ejecutar la sincronización completa usando el método nativo de WooCommerce Multistore
            // Esto es exactamente lo que hace WooCommerce Multistore cuando se actualiza un producto
            error_log("CVAV DEBUG: force_woocommerce_multistore_sync() - Ejecutando sync() completo");
            $multistore_product->sync();
            
            // También ejecutar sync_to() específico para el sitio hijo
            error_log("CVAV DEBUG: force_woocommerce_multistore_sync() - Ejecutando sync_to() para sitio $child_site_id");
            $result = $multistore_product->sync_to($child_site_id);
            
            error_log("CVAV DEBUG: force_woocommerce_multistore_sync() - Resultado de sincronización: " . print_r($result, true));
            
            // Forzar la actualización del producto master para activar hooks adicionales
            $master_product->set_date_modified(current_time('mysql'));
            $master_product->save();
            
            // Limpiar transients para asegurar que los cambios se reflejen
            wc_delete_product_transients($master_product_id);
            
            error_log("CVAV DEBUG: force_woocommerce_multistore_sync() - Sincronización completa completada exitosamente");
            return true;
            
        } catch (Exception $e) {
            error_log("CVAV DEBUG: force_woocommerce_multistore_sync() - Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Convertir un producto variable a producto simple
     */
    private function convert_variable_to_simple($product) {
        try {
            error_log("CVAV DEBUG: convert_variable_to_simple() - Iniciando conversión para producto ID: " . $product->get_id());
            
            // 1. Cambiar el tipo de producto
            $product_data = array(
                'ID' => $product->get_id(),
                'post_type' => 'product'
            );
            wp_update_post($product_data);
            
            // 2. Eliminar término de taxonomía "variable"
            wp_remove_object_terms($product->get_id(), 'variable', 'product_type');
            wp_set_object_terms($product->get_id(), 'simple', 'product_type');
            
            // 3. Eliminar todas las variaciones asociadas
            $variations = $product->get_children();
            foreach ($variations as $variation_id) {
                wp_delete_post($variation_id, true);
                error_log("CVAV DEBUG: convert_variable_to_simple() - Eliminada variación ID: " . $variation_id);
            }
            
            // 4. Limpiar meta datos específicos de productos variables
            delete_post_meta($product->get_id(), '_product_attributes');
            delete_post_meta($product->get_id(), '_default_attributes');
            
            // 5. Actualizar el objeto producto
            wc_delete_product_transients($product->get_id());
            $updated_product = wc_get_product($product->get_id());
            
            error_log("CVAV DEBUG: convert_variable_to_simple() - Conversión completada. Nuevo tipo: " . $updated_product->get_type());
            return true;
            
        } catch (Exception $e) {
            error_log("CVAV DEBUG: convert_variable_to_simple() - Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Convertir un producto simple a producto variable basado en un producto maestro
     */
    private function convert_simple_to_variable($child_product, $master_product) {
        try {
            error_log("CVAV DEBUG: convert_simple_to_variable() - Iniciando conversión para producto ID: " . $child_product->get_id());
            
            // 1. Cambiar el tipo de producto
            $product_data = array(
                'ID' => $child_product->get_id(),
                'post_type' => 'product'
            );
            wp_update_post($product_data);
            
            // 2. Cambiar término de taxonomía a "variable"
            wp_remove_object_terms($child_product->get_id(), 'simple', 'product_type');
            wp_set_object_terms($child_product->get_id(), 'variable', 'product_type');
            
            // 3. Copiar atributos y taxonomías del producto maestro si es variable
            if ($master_product->get_type() === 'variable') {
                $this->copy_product_attributes_and_taxonomies($master_product, $child_product->get_id());
                
                // 4. Crear variaciones basadas en el producto maestro
                $master_variations = $master_product->get_children();
                foreach ($master_variations as $master_variation_id) {
                    $master_variation = wc_get_product($master_variation_id);
                    if ($master_variation) {
                        $this->create_variation_copy($child_product->get_id(), $master_variation);
                    }
                }
            }
            
            // 5. Actualizar el objeto producto
            wc_delete_product_transients($child_product->get_id());
            $updated_product = wc_get_product($child_product->get_id());
            
            error_log("CVAV DEBUG: convert_simple_to_variable() - Conversión completada. Nuevo tipo: " . $updated_product->get_type());
            return true;
            
        } catch (Exception $e) {
            error_log("CVAV DEBUG: convert_simple_to_variable() - Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Copiar atributos y taxonomías de un producto maestro a un producto hijo
     */
    private function copy_product_attributes_and_taxonomies($master_product, $child_product_id) {
        try {
            error_log("CVAV DEBUG: copy_product_attributes_and_taxonomies() - Copiando atributos del producto maestro ID: " . $master_product->get_id() . " al producto hijo ID: " . $child_product_id);
            
            $master_attributes = $master_product->get_attributes();
            
            if (empty($master_attributes)) {
                error_log("CVAV DEBUG: copy_product_attributes_and_taxonomies() - No hay atributos para copiar");
                return false;
            }
            
            $child_attributes = array();
            
            foreach ($master_attributes as $attribute_name => $attribute) {
                error_log("CVAV DEBUG: copy_product_attributes_and_taxonomies() - Procesando atributo: " . $attribute_name);
                
                // Si es un atributo de taxonomía global
                if ($attribute->is_taxonomy()) {
                    $taxonomy = $attribute->get_taxonomy();
                    error_log("CVAV DEBUG: copy_product_attributes_and_taxonomies() - Atributo de taxonomía: " . $taxonomy);
                    
                    // Obtener términos del producto maestro
                    $master_terms = wp_get_post_terms($master_product->get_id(), $taxonomy, array('fields' => 'all'));
                    
                    if (!is_wp_error($master_terms) && !empty($master_terms)) {
                        $term_ids = array();
                        $term_names = array();
                        
                        foreach ($master_terms as $term) {
                            // Verificar si el término existe en el sitio hijo
                            $existing_term = get_term_by('slug', $term->slug, $taxonomy);
                            
                            if (!$existing_term) {
                                // Crear el término si no existe
                                $new_term = wp_insert_term($term->name, $taxonomy, array(
                                    'slug' => $term->slug,
                                    'description' => $term->description
                                ));
                                
                                if (!is_wp_error($new_term)) {
                                    $term_ids[] = $new_term['term_id'];
                                    $term_names[] = $term->name;
                                    error_log("CVAV DEBUG: copy_product_attributes_and_taxonomies() - Término creado: " . $term->name);
                                }
                            } else {
                                $term_ids[] = $existing_term->term_id;
                                $term_names[] = $existing_term->name;
                                error_log("CVAV DEBUG: copy_product_attributes_and_taxonomies() - Término existente: " . $existing_term->name);
                            }
                        }
                        
                        // Asignar términos al producto hijo
                        if (!empty($term_ids)) {
                            wp_set_object_terms($child_product_id, $term_ids, $taxonomy);
                            error_log("CVAV DEBUG: copy_product_attributes_and_taxonomies() - Términos asignados al producto hijo para taxonomía: " . $taxonomy);
                        }
                        
                        // Configurar atributo para el producto hijo
                        $child_attributes[$attribute_name] = array(
                            'name' => $taxonomy,
                            'value' => implode(' | ', $term_names),
                            'position' => $attribute->get_position(),
                            'is_visible' => $attribute->get_visible(),
                            'is_variation' => $attribute->get_variation(),
                            'is_taxonomy' => 1
                        );
                    }
                } else {
                    // Atributo personalizado (no taxonomía)
                    $child_attributes[$attribute_name] = array(
                        'name' => $attribute->get_name(),
                        'value' => $attribute->get_options() ? implode(' | ', $attribute->get_options()) : '',
                        'position' => $attribute->get_position(),
                        'is_visible' => $attribute->get_visible(),
                        'is_variation' => $attribute->get_variation(),
                        'is_taxonomy' => 0
                    );
                    error_log("CVAV DEBUG: copy_product_attributes_and_taxonomies() - Atributo personalizado copiado: " . $attribute->get_name());
                }
            }
            
            // Guardar atributos en el producto hijo
            if (!empty($child_attributes)) {
                update_post_meta($child_product_id, '_product_attributes', $child_attributes);
                error_log("CVAV DEBUG: copy_product_attributes_and_taxonomies() - Atributos guardados en producto hijo");
            }
            
            // Copiar atributos por defecto
            $default_attributes = $master_product->get_default_attributes();
            if ($default_attributes) {
                update_post_meta($child_product_id, '_default_attributes', $default_attributes);
                error_log("CVAV DEBUG: copy_product_attributes_and_taxonomies() - Atributos por defecto copiados");
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("CVAV DEBUG: copy_product_attributes_and_taxonomies() - Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear una copia de variación basada en una variación maestro
     */
    private function create_variation_copy($parent_id, $master_variation) {
        try {
            // CRÍTICO: Verificar que estamos en el child site
            $current_site_id = get_current_blog_id();
            error_log("CVAV DEBUG: create_variation_copy() - Current site ID: " . $current_site_id);
            error_log("CVAV DEBUG: create_variation_copy() - Creando variación para producto padre ID: " . $parent_id);
            
            // Si estamos en el master site (ID 1), NO crear variaciones
            if ($current_site_id == 1) {
                error_log("CVAV DEBUG: create_variation_copy() - ERROR: Intentando crear variación en master site. ABORTANDO.");
                return false;
            }
            
            $master_sku = $master_variation->get_sku();
            
            // Verificar si ya existe una variación con el mismo SKU
            if ($master_sku) {
                $existing_variation = $this->find_variation_by_sku($parent_id, $master_sku);
                if ($existing_variation) {
                    error_log("CVAV DEBUG: create_variation_copy() - Variation with SKU " . $master_sku . " already exists with ID: " . $existing_variation->get_id());
                    return $existing_variation->get_id();
                }
            }
            
            // Obtener el nombre del producto padre
            $parent_product = wc_get_product($parent_id);
            $parent_name = $parent_product ? $parent_product->get_name() : 'Producto';
            
            // Obtener atributos de la variación master para el nombre
            $variation_attributes = $master_variation->get_variation_attributes();
            $attribute_names = array();
            foreach ($variation_attributes as $attr_name => $attr_value) {
                $taxonomy = str_replace('attribute_', '', $attr_name);
                $term = get_term_by('slug', $attr_value, $taxonomy);
                if ($term) {
                    $attribute_names[] = $term->name;
                } else {
                    $attribute_names[] = $attr_value;
                }
            }
            
            // Construir nombre limpio: Producto Padre - Atributos
            $variation_title = $parent_name;
            if (!empty($attribute_names)) {
                $variation_title .= ' - ' . implode(' / ', $attribute_names);
            }
            
            // Crear nuevo post de variación
            $variation_post = array(
                'post_title'  => $variation_title,
                'post_name'   => 'product-' . $parent_id . '-variation-' . $master_variation->get_sku(),
                'post_status' => 'publish',
                'post_parent' => $parent_id,
                'post_type'   => 'product_variation',
                'menu_order'  => 0
            );
            
            $variation_id = wp_insert_post($variation_post);
            
            if (is_wp_error($variation_id)) {
                error_log("CVAV DEBUG: create_variation_copy() - Error creando post de variación: " . $variation_id->get_error_message());
                return false;
            }
            
            // Copiar SKU y otros meta datos básicos
            update_post_meta($variation_id, '_sku', $master_variation->get_sku());
            update_post_meta($variation_id, '_regular_price', $master_variation->get_regular_price());
            update_post_meta($variation_id, '_sale_price', $master_variation->get_sale_price());
            update_post_meta($variation_id, '_price', $master_variation->get_price());
            update_post_meta($variation_id, '_manage_stock', $master_variation->get_manage_stock() ? 'yes' : 'no');
            
            if ($master_variation->get_manage_stock()) {
                update_post_meta($variation_id, '_stock', $master_variation->get_stock_quantity());
                update_post_meta($variation_id, '_stock_status', $master_variation->get_stock_status());
            }
            
            // Copiar atributos de variación con taxonomías
            $variation_attributes = $master_variation->get_variation_attributes();
            error_log("CVAV DEBUG: create_variation_copy() - Atributos de variación maestro: " . print_r($variation_attributes, true));
            
            foreach ($variation_attributes as $attribute_name => $attribute_value) {
                // Limpiar el nombre del atributo para obtener la taxonomía
                $taxonomy = str_replace('attribute_', '', $attribute_name);
                
                if (!empty($attribute_value)) {
                    // Si es una taxonomía de atributo
                    if (taxonomy_exists($taxonomy)) {
                        // Buscar el término por slug
                        $term = get_term_by('slug', $attribute_value, $taxonomy);
                        
                        if (!$term) {
                            // Si el término no existe, crearlo
                            $term_data = wp_insert_term($attribute_value, $taxonomy);
                            if (!is_wp_error($term_data)) {
                                $term = get_term($term_data['term_id'], $taxonomy);
                                error_log("CVAV DEBUG: create_variation_copy() - Término creado: " . $attribute_value . " para taxonomía: " . $taxonomy);
                            }
                        }
                        
                        if ($term) {
                            // Asignar el término a la variación
                            wp_set_object_terms($variation_id, array($term->term_id), $taxonomy);
                            error_log("CVAV DEBUG: create_variation_copy() - Término asignado a variación: " . $term->name);
                        }
                    }
                }
                
                // Guardar como meta también
                update_post_meta($variation_id, $attribute_name, $attribute_value);
                error_log("CVAV DEBUG: create_variation_copy() - Meta guardado: " . $attribute_name . " = " . $attribute_value);
            }
            
            // Limpiar cachés
            wc_delete_product_transients($parent_id);
            wc_delete_product_transients($variation_id);
            
            // Forzar la asociación de la variación al producto padre
            $parent_product = wc_get_product($parent_id);
            if ($parent_product) {
                // Regenerar las variaciones del producto padre
                $parent_variations = $parent_product->get_children();
                if (!in_array($variation_id, $parent_variations)) {
                    // Agregar la nueva variación a la lista de variaciones del padre
                    $parent_variations[] = $variation_id;
                    update_post_meta($parent_id, '_children', $parent_variations);
                    error_log("CVAV DEBUG: create_variation_copy() - Variación agregada a la lista de hijos del producto padre");
                }
                
                // Forzar la actualización del producto padre para activar hooks
                $parent_product->set_date_modified(current_time('mysql'));
                $parent_product->save();
                
                // Limpiar cachés nuevamente después de la actualización
                wc_delete_product_transients($parent_id);
                wc_delete_product_transients($variation_id);
            }
            
            error_log("CVAV DEBUG: create_variation_copy() - Cachés limpiados para variación creada");
            
            error_log("CVAV DEBUG: create_variation_copy() - Variación creada con ID: " . $variation_id);
            return $variation_id;
            
        } catch (Exception $e) {
            error_log("CVAV DEBUG: create_variation_copy() - Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar una variación por SKU en el producto padre.
     */
    private function find_variation_by_sku($parent_id, $sku) {
        $variations = $this->get_product_variations($parent_id);
        foreach ($variations as $variation) {
            if ($variation->get_sku() === $sku) {
                return $variation;
            }
        }
        return false;
    }

    /**
     * Obtener todas las variaciones de un producto.
     */
    private function get_product_variations($product_id) {
        $variations = array();
        $args = array(
            'post_type' => 'product_variation',
            'post_parent' => $product_id,
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        );
        $variation_posts = get_posts($args);
        foreach ($variation_posts as $variation_post) {
            $variations[] = wc_get_product($variation_post->ID);
        }
        return $variations;
    }
} 