/**
 * JavaScript para el Conector de Atributos Chile Vapo - Andes Vapor
 * Version: 1.1.2
 */

jQuery(document).ready(function($) {
    'use strict';

    // Limpiar cualquier variable global previa que pueda estar causando conflictos
    if (window.CVAV_Connector) {
        delete window.CVAV_Connector;
    }
    if (window.loadSiteAttributes) {
        delete window.loadSiteAttributes;
    }

    // Variables globales
    var modal = $('#cvav-connection-modal');
    var form = $('#cvav-connection-form');

    // Inicializar
    init();

    function init() {
        bindEvents();
        setupModals();
    }

    function bindEvents() {
        // Botón para agregar conexión
        $('#cvav-add-connection').on('click', function() {
            showConnectionModal();
        });

        // Botón para actualizar conexiones
        $('#cvav-refresh-connections').on('click', function() {
            refreshConnections();
        });

        // Evento para detectar cambios en el selector de sitio de Slave
        $('#slave_site_id').on('change', function() {
            var selectedSite = $(this).val();
            if (selectedSite) {
                // Mostrar indicador de carga
                var select = $(this);
                var originalText = select.find('option:selected').text();
                select.prop('disabled', true);
                
                // Obtener conexiones para el nuevo sitio
                $.ajax({
                    url: cvav_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cvav_get_site_connections',
                        nonce: cvav_ajax.nonce,
                        slave_site_id: selectedSite
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('Conexiones actualizadas para el nuevo sitio.', 'success');
                            // Recargar la página para mostrar las nuevas conexiones
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showNotification('Error al obtener conexiones: ' + response.data, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        showNotification('Error de conexión al obtener conexiones.', 'error');
                    },
                    complete: function() {
                        select.prop('disabled', false);
                    }
                });
            }
        });

        // Botones para desconectar atributos
        $(document).on('click', '.cvav-disconnect-attributes', function() {
            var masterId = $(this).data('master');
            var slaveId = $(this).data('slave');
            disconnectAttributes(masterId, slaveId);
        });

        // Modal events
        $('#cvav-modal-close, #cvav-modal-cancel').on('click', function() {
            hideConnectionModal();
        });

        $('#cvav-modal-save').on('click', function() {
            saveConnection();
        });

        // Cerrar modal al hacer clic fuera
        $(window).on('click', function(e) {
            if (e.target === modal[0]) {
                hideConnectionModal();
            }
        });

        // Validación en tiempo real
        $('#cvav-master-attribute, #cvav-slave-attribute').on('change', function() {
            validateConnectionForm();
        });
    }

    function setupModals() {
        // Configurar modal usando CSS puro en lugar de jQuery UI
        // El modal se controla completamente con CSS y JavaScript personalizado
    }

    function showConnectionModal() {
        // Limpiar formulario
        form[0].reset();
        
        // Mostrar modal
        modal.addClass('cvav-modal-open').show();
        
        // Enfocar primer campo
        setTimeout(function() {
            $('#cvav-master-attribute').focus();
        }, 100);
    }

    function hideConnectionModal() {
        modal.removeClass('cvav-modal-open').hide();
        form[0].reset();
        clearFormErrors();
    }

    // Función para debug de conexión
    function debugConnection(masterId, slaveId) {
        if (!checkConfiguration()) {
            return;
        }

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_debug_connection',
                nonce: cvav_ajax.nonce,
                master_attribute_id: masterId,
                slave_attribute_id: slaveId
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Debug completado. Revisa la consola para más detalles.', 'success');
                } else {
                    showNotification('Error en debug: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error de conexión en debug.', 'error');
            }
        });
    }

    // Modificar función saveConnection para incluir debug automático en caso de error
    function saveConnection() {
        var masterId = $('#cvav-master-attribute').val();
        var slaveId = $('#cvav-slave-attribute').val();

        if (!masterId || !slaveId) {
            showFormError('Por favor, selecciona ambos atributos.');
            return;
        }

        // Comentado: No hay razón técnica para impedir conectar atributos con el mismo nombre
        // if (chilevapoId === andesvaporId) {
        //     showFormError('Los atributos de Chile Vapo y Andes Vapor deben ser diferentes.');
        //     return;
        // }

        // Mostrar loading
        var saveButton = $('#cvav-modal-save');
        var originalText = saveButton.text();
        saveButton.prop('disabled', true).text('Conectando...');

        // Preparar datos para AJAX
        var ajaxData = {
            action: 'cvav_connect_attributes',
            nonce: cvav_ajax.nonce,
            master_attribute_id: masterId,
            slave_attribute_id: slaveId
        };

        // Usar el nuevo sistema de manejo de nonces
        doAjaxWithNonceHandling(
            ajaxData,
            function(response) {
                if (response.success) {
                    showNotification(response.data, 'success');
                    hideConnectionModal();
                    refreshConnections();
                } else {
                    showFormError(response.data);
                }
                saveButton.prop('disabled', false).text(originalText);
            },
            function(xhr, status, error) {
                // Ejecutar debug automáticamente en caso de error persistente
                debugConnection(masterId, slaveId);
                
                var errorMessage = 'Error de conexión. Por favor, intenta de nuevo.';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    if (typeof xhr.responseJSON.data === 'string') {
                        errorMessage = xhr.responseJSON.data;
                    } else if (xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    }
                } else if (xhr.status === 400) {
                    errorMessage = 'Error 400: Solicitud incorrecta. Revisa la consola para más detalles.';
                }
                showFormError(errorMessage);
                saveButton.prop('disabled', false).text(originalText);
            }
        );
    }

    function disconnectAttributes(masterId, slaveId) {
        if (!confirm(cvav_ajax.strings.confirm_delete)) {
            return;
        }

        // Mostrar loading en el botón
        var button = $('.cvav-disconnect-attributes[data-master="' + masterId + '"][data-slave="' + slaveId + '"]');
        var originalText = button.text();
        button.prop('disabled', true).text('Desconectando...');

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_disconnect_attributes',
                nonce: cvav_ajax.nonce,
                master_attribute_id: masterId,
                slave_attribute_id: slaveId
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data, 'success');
                    refreshConnections();
                } else {
                    showNotification(response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Error de conexión. Por favor, intenta de nuevo.';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                showNotification(errorMessage, 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    function refreshConnections() {
        var refreshButton = $('#cvav-refresh-connections');
        var originalText = refreshButton.text();
        refreshButton.prop('disabled', true).text('Actualizando...');

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_refresh_connections',
                nonce: cvav_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    showNotification(response.data, 'error');
                }
            },
            error: function() {
                showNotification('Error al actualizar conexiones.', 'error');
            },
            complete: function() {
                refreshButton.prop('disabled', false).text(originalText);
            }
        });
    }

    function validateConnectionForm() {
        var masterId = $('#cvav-master-attribute').val();
        var slaveId = $('#cvav-slave-attribute').val();
        var saveButton = $('#cvav-modal-save');

        // Solo verificar que ambos atributos estén seleccionados, permitir IDs iguales
        if (masterId && slaveId) {
            saveButton.prop('disabled', false);
            clearFormErrors();
        } else {
            saveButton.prop('disabled', true);
        }
    }

    function showFormError(message) {
        clearFormErrors();
        
        var errorDiv = $('<div class="cvav-form-error">' + message + '</div>');
        form.append(errorDiv);
        
        // Scroll al error
        $('html, body').animate({
            scrollTop: errorDiv.offset().top - 100
        }, 300);
    }

    function clearFormErrors() {
        $('.cvav-form-error').remove();
    }

    function showNotification(message, type) {
        // Remover notificaciones existentes
        $('.cvav-notification').remove();

        // Crear nueva notificación
        var notificationClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notification = $('<div class="notice ' + notificationClass + ' cvav-notification"><p>' + message + '</p></div>');
        
        // Insertar después del título
        $('.wrap h1').after(notification);

        // Log en consola para debug
        if (type === 'error') {
            // Error silencioso
        }

        // Auto-remover después de 5 segundos
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Función para obtener estadísticas
    function getConnectionStats() {
        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_get_connection_stats',
                nonce: cvav_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatsDisplay(response.data);
                }
            }
        });
    }

    function updateStatsDisplay(stats) {
        // Actualizar estadísticas si existen elementos para mostrar
        if ($('.cvav-stats').length) {
            $('.cvav-total-connections').text(stats.total_connections);
            $('.cvav-master-attributes').text(stats.master_attributes);
            $('.cvav-slave-attributes').text(stats.slave_attributes);
        }
    }

    // Función para buscar atributos
    function searchAttributes(siteId, searchTerm) {
        return $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_search_attributes',
                nonce: cvav_ajax.nonce,
                site_id: siteId,
                search_term: searchTerm
            }
        });
    }

    // Función para conectar automáticamente por nombre
    function autoConnectByName() {
        if (!confirm('¿Estás seguro de que quieres conectar automáticamente todos los atributos con el mismo nombre?')) {
            return;
        }

        var button = $('#cvav-auto-connect');
        var originalText = button.text();
        button.prop('disabled', true).text('Conectando...');

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_auto_connect_by_name',
                nonce: cvav_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    if (response.data.connections_made > 0) {
                        refreshConnections();
                    }
                } else {
                    showNotification(response.data, 'error');
                }
            },
            error: function() {
                showNotification('Error al conectar automáticamente.', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    // Función para refrescar nonce manualmente
    function refreshNonce() {
        $.get(window.location.href, function(data) {
            var nonceMatch = data.match(/cvav_ajax['"]\s*:\s*\{[^}]*nonce['"]\s*:\s*['"]([^'"]+)['"]/);
            if (nonceMatch && nonceMatch[1]) {
                var oldNonce = cvav_ajax.nonce;
                cvav_ajax.nonce = nonceMatch[1];
                showNotification('Nonce actualizado correctamente', 'success');
            } else {
                showNotification('Error al actualizar nonce', 'error');
            }
        }).fail(function() {
            showNotification('Error de conexión al actualizar nonce', 'error');
        });
    }

    // Exponer funciones globalmente si es necesario
    window.CVAV_Admin = {
        showConnectionModal: showConnectionModal,
        refreshConnections: refreshConnections,
        autoConnectByName: autoConnectByName,
        debugConnection: debugConnection,
        testSite: testSite,
        testStorzBickel: function() { testSite(2); },
        testAndesVapor: function() { testSite(7); },
        refreshNonce: refreshNonce,
        getCurrentNonce: function() { return cvav_ajax.nonce; }
    };

    // Función para testear un sitio específico
    function testSite(siteId) {
        if (!checkConfiguration()) {
            return;
        }

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_test_site',
                nonce: cvav_ajax.nonce,
                test_site_id: siteId
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Test completado. Revisa la consola para más detalles.', 'success');
                } else {
                    console.error('CVAV Test Error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('CVAV Test AJAX Error:', xhr, status, error);
                console.error('Response Text:', xhr.responseText);
            }
        });
    }

    // Función para verificar configuración
    function checkConfiguration() {
        if (typeof cvav_ajax === 'undefined') {
            console.error('CVAV: cvav_ajax no está definido');
            return false;
        }
        
        if (!cvav_ajax.ajax_url) {
            console.error('CVAV: ajax_url no está definido');
            return false;
        }
        
        if (!cvav_ajax.nonce) {
            console.error('CVAV: nonce no está definido');
            return false;
        }
        
        return true;
    }

    // Verificar configuración al inicializar
    if (!checkConfiguration()) {
        console.error('CVAV: Error en la configuración del plugin');
    }

    // Función para manejar errores de nonce expirado
    function handleNonceError(xhr, retryFunction, retryData) {
        if (xhr.status === 400 && xhr.responseJSON && xhr.responseJSON.data) {
            var response = xhr.responseJSON.data;
            if (response.new_nonce) {
                // Actualizar el nonce global
                cvav_ajax.nonce = response.new_nonce;
                
                // Reintentar la operación con el nuevo nonce
                if (retryFunction && retryData) {
                    retryData.nonce = response.new_nonce;
                    setTimeout(function() {
                        retryFunction(retryData);
                    }, 1000);
                    return true;
                }
            }
        }
        return false;
    }

    // Función para realizar AJAX con manejo automático de nonce
    function doAjaxWithNonceHandling(ajaxData, successCallback, errorCallback) {
        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: ajaxData,
            success: successCallback,
            error: function(xhr, status, error) {
                // Intentar manejar error de nonce
                var handled = handleNonceError(xhr, function(retryData) {
                    doAjaxWithNonceHandling(retryData, successCallback, errorCallback);
                }, ajaxData);
                
                if (!handled && errorCallback) {
                    errorCallback(xhr, status, error);
                }
            }
        });
    }

    // ========================================
    // FUNCIONALIDAD DE PRODUCTOS
    // ========================================

    // Variables para productos
    var selectedMasterProduct = null;
    var selectedChildProduct = null;
    var currentChildSiteId = null;

    // Inicializar funcionalidad de productos si estamos en la página de productos
    if ($('#child-site-selector').length > 0) {
        initProducts();
    }

    function initProducts() {
        bindProductEvents();
        setupProductSearch();
    }

    function bindProductEvents() {
        // Usar automáticamente el sitio configurado
        var configuredSiteId = $('#child-site-selector').val();
        if (configuredSiteId) {
            currentChildSiteId = configuredSiteId;
            loadConnectedProducts(configuredSiteId);
            showConnectProductsSection();
        } else {
            hideConnectProductsSection();
            hideConnectedProductsSection();
        }

        // Búsqueda de productos maestro
        $('#search-master-product').on('click', function() {
            searchMasterProducts();
        });

        $('#master-product-search').on('keypress', function(e) {
            if (e.which === 13) {
                searchMasterProducts();
            }
        });

        // Búsqueda de productos hijo
        $('#search-child-product').on('click', function() {
            searchChildProducts();
        });

        $('#child-product-search').on('keypress', function(e) {
            if (e.which === 13) {
                searchChildProducts();
            }
        });

        // Conectar productos
        $('#connect-products-btn').on('click', function() {
            connectProducts();
        });

        // Desconectar productos
        $(document).on('click', '.cvav-disconnect-product', function() {
            var masterId = $(this).data('master-id');
            var childId = $(this).data('child-id');
            var childSiteId = $(this).data('child-site-id');
            disconnectProduct(masterId, childId, childSiteId);
        });
    }

    function setupProductSearch() {
        // Configurar búsqueda con debounce
        var searchTimeout;
        
        $('#master-product-search, #child-product-search').on('input', function() {
            clearTimeout(searchTimeout);
            var searchBox = $(this);
            var searchTerm = searchBox.val();
            
            if (searchTerm.length >= 2) {
                searchTimeout = setTimeout(function() {
                    if (searchBox.attr('id') === 'master-product-search') {
                        searchMasterProducts();
                    } else {
                        searchChildProducts();
                    }
                }, 500);
            }
        });
    }

    function searchMasterProducts() {
        var searchTerm = $('#master-product-search').val();
        
        if (!searchTerm) {
            showNotification('Ingresa un término de búsqueda.', 'warning');
            return;
        }

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_search_products',
                nonce: cvav_products_nonce,
                site_id: cvav_current_site_id,
                search_term: searchTerm
            },
            success: function(response) {
                if (response.success) {
                    displayProductResults('master', response.data);
                } else {
                    showNotification('Error al buscar productos: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error de conexión al buscar productos.', 'error');
            }
        });
    }

    function searchChildProducts() {
        var searchTerm = $('#child-product-search').val();
        var siteId = currentChildSiteId;
        
        if (!searchTerm || !siteId) {
            showNotification('Ingresa un término de búsqueda y selecciona un sitio.', 'warning');
            return;
        }

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_search_products',
                nonce: cvav_products_nonce,
                site_id: siteId,
                search_term: searchTerm
            },
            success: function(response) {
                if (response.success) {
                    displayProductResults('child', response.data);
                } else {
                    showNotification('Error al buscar productos: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error de conexión al buscar productos.', 'error');
            }
        });
    }

    function displayProductResults(type, products) {
        var resultsContainer = type === 'master' ? $('#master-product-results') : $('#child-product-results');
        var searchInput = type === 'master' ? $('#master-product-search') : $('#child-product-search');
        
        resultsContainer.empty();
        
        if (products.length === 0) {
            resultsContainer.html('<p class="cvav-no-results">No se encontraron productos.</p>');
        } else {
            var html = '<div class="cvav-product-list">';
            products.forEach(function(product) {
                html += '<div class="cvav-product-item" data-product-id="' + product.id + '" data-product-sku="' + product.sku + '">';
                html += '<div class="cvav-product-info">';
                html += '<strong>' + product.name + '</strong><br>';
                html += '<small>ID: ' + product.id + ' | SKU: ' + product.sku + '</small><br>';
                html += '<small>Tipo: ' + product.type + ' | Precio: ' + product.price + '</small>';
                html += '</div>';
                html += '<button type="button" class="button cvav-select-product">Seleccionar</button>';
                html += '</div>';
            });
            html += '</div>';
            resultsContainer.html(html);
        }
        
        resultsContainer.show();
        
        // Evento para seleccionar producto
        resultsContainer.find('.cvav-select-product').on('click', function() {
            var productItem = $(this).closest('.cvav-product-item');
            var productId = productItem.data('product-id');
            var productSku = productItem.data('product-sku');
            var productName = productItem.find('.cvav-product-info strong').text();
            
            selectProduct(type, {
                id: productId,
                name: productName,
                sku: productSku
            });
            
            resultsContainer.hide();
            searchInput.val(productName);
        });
    }

    function selectProduct(type, product) {
        if (type === 'master') {
            selectedMasterProduct = product;
            $('#master-product-search').val(product.name);
        } else {
            selectedChildProduct = product;
            $('#child-product-search').val(product.name);
        }
        
        checkProductConnectionReady();
    }

    function checkProductConnectionReady() {
        if (selectedMasterProduct && selectedChildProduct) {
            $('.cvav-connect-actions').show();
            
            // Verificar si los SKUs coinciden
            if (selectedMasterProduct.sku === selectedChildProduct.sku) {
                $('#connect-products-btn').prop('disabled', false).text('Conectar Productos');
            } else {
                $('#connect-products-btn').prop('disabled', true).text('SKUs no coinciden');
            }
        } else {
            $('.cvav-connect-actions').hide();
        }
    }

    function connectProducts() {
        if (!selectedMasterProduct || !selectedChildProduct || !currentChildSiteId) {
            showNotification('Selecciona ambos productos y un sitio.', 'warning');
            return;
        }

        if (selectedMasterProduct.sku !== selectedChildProduct.sku) {
            showNotification('Los SKUs de los productos deben coincidir.', 'error');
            return;
        }

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_connect_products',
                nonce: cvav_products_nonce,
                master_product_id: selectedMasterProduct.id,
                child_product_id: selectedChildProduct.id,
                child_site_id: currentChildSiteId
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Productos conectados exitosamente.', 'success');
                    clearProductSelection();
                    loadConnectedProducts(currentChildSiteId);
                } else {
                    showNotification('Error al conectar productos: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('CVAV Connect Products Error:', xhr, status, error);
                showNotification('Error de conexión al conectar productos.', 'error');
            }
        });
    }

    function disconnectProduct(masterId, childId, childSiteId) {
        if (!confirm('¿Estás seguro de que quieres desconectar estos productos?')) {
            return;
        }

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_disconnect_products',
                nonce: cvav_products_nonce,
                master_product_id: masterId,
                child_product_id: childId,
                child_site_id: childSiteId
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Productos desconectados exitosamente.', 'success');
                    loadConnectedProducts(childSiteId);
                } else {
                    showNotification('Error al desconectar productos: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('CVAV Disconnect Products Error:', xhr, status, error);
                showNotification('Error de conexión al desconectar productos.', 'error');
            }
        });
    }

    function loadConnectedProducts(childSiteId) {
        if (!childSiteId) return;

        $('#connected-products-loading').show();
        $('#connected-products-content').hide();
        $('#connected-products-empty').hide();

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_get_connected_products',
                nonce: cvav_products_nonce,
                child_site_id: childSiteId
            },
            success: function(response) {
                $('#connected-products-loading').hide();
                
                if (response.success) {
                    displayConnectedProducts(response.data);
                } else {
                    showNotification('Error al cargar productos conectados: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('CVAV Load Connected Products Error:', xhr, status, error);
                $('#connected-products-loading').hide();
                showNotification('Error de conexión al cargar productos conectados.', 'error');
            }
        });
    }

    function displayConnectedProducts(connections) {
        var tbody = $('#connected-products-tbody');
        tbody.empty();

        if (connections.length === 0) {
            $('#connected-products-empty').show();
            $('#connected-products-content').hide();
        } else {
            connections.forEach(function(connection) {
                // Crear badge de tipo de producto si está disponible
                var masterTypeBadge = '';
                var childTypeBadge = '';
                
                if (connection.master_product_type) {
                    masterTypeBadge = '<span class="cvav-product-type-badge cvav-product-type-' + connection.master_product_type + '">' + connection.master_product_type + '</span>';
                }
                
                if (connection.child_product_type) {
                    childTypeBadge = '<span class="cvav-product-type-badge cvav-product-type-' + connection.child_product_type + '">' + connection.child_product_type + '</span>';
                }
                
                // Crear links para los productos
                var masterProductLink = '<a href="' + connection.master_edit_url + '" target="_blank" class="cvav-product-link">' + connection.master_product_name + '</a>';
                var childProductLink = '<a href="' + connection.child_edit_url + '" target="_blank" class="cvav-product-link">' + connection.child_product_name + '</a>';
                
                var row = '<tr>';
                row += '<td><strong>' + masterProductLink + '</strong>' + masterTypeBadge + '<br><small>ID: ' + connection.master_product_id + '</small></td>';
                row += '<td>' + connection.master_product_sku + '</td>';
                row += '<td><strong>' + childProductLink + '</strong>' + childTypeBadge + '<br><small>ID: ' + connection.child_product_id + '</small></td>';
                row += '<td>';
                row += '<button type="button" class="button button-link-delete cvav-disconnect-connected-product" ';
                row += 'data-master-id="' + connection.master_product_id + '" ';
                row += 'data-child-id="' + connection.child_product_id + '" ';
                row += 'data-child-site-id="' + connection.child_site_id + '">';
                row += 'Desconectar</button>';
                row += '</td>';
                row += '</tr>';
                tbody.append(row);
            });

            $('#connected-products-content').show();
            $('#connected-products-empty').hide();
        }

        $('#connected-products-section').show();
    }

    function clearProductSelection() {
        selectedMasterProduct = null;
        selectedChildProduct = null;
        $('#master-product-search').val('');
        $('#child-product-search').val('');
        $('#master-product-results').hide();
        $('#child-product-results').hide();
        $('.cvav-connect-actions').hide();
    }

    function showConnectProductsSection() {
        $('#connect-products-section').show();
    }

    function hideConnectProductsSection() {
        $('#connect-products-section').hide();
        clearProductSelection();
    }

    function showConnectedProductsSection() {
        $('#connected-products-section').show();
    }

    function hideConnectedProductsSection() {
        $('#connected-products-section').hide();
    }

    // ===== NUEVAS FUNCIONES PARA PRODUCTOS CON SKUs COINCIDENTES =====

    // Inicializar funcionalidad de productos con SKUs coincidentes
    function initMatchingProducts() {
        console.log('CVAV DEBUG: initMatchingProducts() called');
        
        // Cargar productos con SKUs coincidentes y conectados al cargar la página
        var childSiteId = $('#child-site-selector').val();
        console.log('CVAV DEBUG: initMatchingProducts() - childSiteId:', childSiteId);
        
        if (childSiteId) {
            console.log('CVAV DEBUG: initMatchingProducts() - loading matching and connected products');
            loadMatchingProducts(childSiteId);
            loadConnectedProducts(childSiteId);
        } else {
            console.log('CVAV DEBUG: initMatchingProducts() - no childSiteId found');
        }

        // Eventos para botones de conectar productos
        $(document).on('click', '.cvav-connect-matching-product', function() {
            var masterId = $(this).data('master-id');
            var childId = $(this).data('child-id');
            var childSiteId = $(this).data('child-site-id');
            
            connectMatchingProducts(masterId, childId, childSiteId);
        });

        // Eventos para botones de desconectar productos conectados
        $(document).on('click', '.cvav-disconnect-connected-product', function() {
            var masterId = $(this).data('master-id');
            var childId = $(this).data('child-id');
            var childSiteId = $(this).data('child-site-id');
            
            disconnectConnectedProducts(masterId, childId, childSiteId);
        });
    }

    // Cargar productos con SKUs coincidentes
    function loadMatchingProducts(childSiteId) {
        console.log('CVAV DEBUG: loadMatchingProducts() called with childSiteId:', childSiteId);
        
        if (!childSiteId) {
            console.log('CVAV DEBUG: loadMatchingProducts() - no childSiteId provided');
            return;
        }

        $('#matching-products-loading').show();
        $('#matching-products-content').hide();
        $('#matching-products-empty').hide();

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_get_matching_products',
                nonce: cvav_products_nonce,
                child_site_id: childSiteId
            },
            success: function(response) {
                console.log('CVAV DEBUG: loadMatchingProducts() - AJAX response:', response);
                $('#matching-products-loading').hide();
                
                if (response.success) {
                    displayMatchingProducts(response.data);
                } else {
                    console.error('CVAV DEBUG: loadMatchingProducts() - AJAX error:', response.data);
                    showNotification('Error al cargar productos con SKUs coincidentes: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('CVAV DEBUG: loadMatchingProducts() - AJAX error:', xhr, status, error);
                $('#matching-products-loading').hide();
                showNotification('Error de conexión al cargar productos con SKUs coincidentes.', 'error');
            }
        });
    }

    // Mostrar productos con SKUs coincidentes en la tabla
    function displayMatchingProducts(matches) {
        console.log('CVAV DEBUG: displayMatchingProducts() called with matches:', matches);
        
        var tbody = $('#matching-products-tbody');
        tbody.empty();

        if (matches.length === 0) {
            $('#matching-products-empty').show();
            $('#matching-products-content').hide();
            $('#matching-products-counter').text('(0)').addClass('zero');
        } else {
            matches.forEach(function(match) {
                // Crear badge de tipo de producto si está disponible
                var masterTypeBadge = '';
                var childTypeBadge = '';
                
                if (match.master_product_type) {
                    masterTypeBadge = '<span class="cvav-product-type-badge cvav-product-type-' + match.master_product_type + '">' + match.master_product_type + '</span>';
                }
                
                if (match.child_product_type) {
                    childTypeBadge = '<span class="cvav-product-type-badge cvav-product-type-' + match.child_product_type + '">' + match.child_product_type + '</span>';
                }
                
                // Extraer información de variación si existe
                var masterName = match.master_product_name;
                var childName = match.child_product_name;
                
                // Si es una variación, mostrar información del padre
                if (match.master_product_type === 'variation' && match.master_parent_id) {
                    masterName = match.master_product_name.replace(' (Variación de: ', '<br><small class="cvav-variation-info">Variación de: ');
                    masterName = masterName.replace(')', ')</small>');
                }
                
                if (match.child_product_type === 'variation' && match.child_parent_id) {
                    childName = match.child_product_name.replace(' (Variación de: ', '<br><small class="cvav-variation-info">Variación de: ');
                    childName = childName.replace(')', ')</small>');
                }
                
                // Crear links para los productos
                var masterProductLink = '<a href="' + match.master_edit_url + '" target="_blank" class="cvav-product-link">' + masterName + '</a>';
                var childProductLink = '<a href="' + match.child_edit_url + '" target="_blank" class="cvav-product-link">' + childName + '</a>';
                
                var row = '<tr>';
                row += '<td><strong>' + masterProductLink + '</strong>' + masterTypeBadge + '</td>';
                // Mostrar ID del padre si es variación, sino el ID del producto
                var masterDisplayId = match.master_display_id || match.master_product_id;
                row += '<td>' + masterDisplayId + '</td>';
                row += '<td>' + match.master_product_sku + '</td>';
                row += '<td><strong>' + childProductLink + '</strong>' + childTypeBadge + '</td>';
                // Mostrar ID del padre si es variación, sino el ID del producto
                var childDisplayId = match.child_display_id || match.child_product_id;
                row += '<td>' + childDisplayId + '</td>';
                row += '<td>' + match.child_product_sku + '</td>';
                row += '<td>';
                row += '<button type="button" class="button button-primary cvav-connect-matching-product" ';
                row += 'data-master-id="' + match.master_product_id + '" ';
                row += 'data-child-id="' + match.child_product_id + '" ';
                row += 'data-child-site-id="' + match.child_site_id + '">';
                row += 'Conectar</button>';
                row += '</td>';
                row += '</tr>';
                tbody.append(row);
            });

            $('#matching-products-content').show();
            $('#matching-products-empty').hide();
            $('#matching-products-counter').text('(' + matches.length + ')').removeClass('zero');
        }

        $('#matching-products-section').show();
    }

    // Conectar productos con SKUs coincidentes
    function connectMatchingProducts(masterId, childId, childSiteId) {
        console.log('CVAV DEBUG: connectMatchingProducts() called with:', masterId, childId, childSiteId);
        
        if (!confirm('¿Deseas conectar los productos completos? Esto conectará el producto principal y todas sus variaciones asociadas.')) {
            return;
        }

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_connect_products',
                nonce: cvav_products_nonce,
                master_product_id: masterId,
                child_product_id: childId,
                child_site_id: childSiteId
            },
            success: function(response) {
                console.log('CVAV DEBUG: connectMatchingProducts() - AJAX response:', response);
                
                if (response.success) {
                    var message = response.data.message || 'Productos conectados exitosamente';
                    showNotification(message, 'success');
                    
                    // Actualizar tablas de manera eficiente
                    updateTablesAfterConnection(masterId, childId, response.data);
                    
                    // Recargar la tabla de productos conectados después de un delay más largo 
                    // para permitir que WooCommerce Multistore complete la sincronización
                    setTimeout(function() {
                        console.log('CVAV DEBUG: First reload - checking initial sync status');
                        loadConnectedProducts(childSiteId);
                        
                        // Segundo reload para asegurar que todas las variaciones aparezcan
                        setTimeout(function() {
                            console.log('CVAV DEBUG: Second reload - ensuring all variations are synced');
                            loadConnectedProducts(childSiteId);
                        }, 3000); // Segundo reload después de 3 segundos adicionales
                        
                    }, 2500); // 2.5 segundos para sincronización inicial
                } else {
                    showNotification('Error al conectar productos: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('CVAV DEBUG: connectMatchingProducts() - AJAX error:', xhr, status, error);
                showNotification('Error de conexión al conectar productos.', 'error');
            }
        });
    }

    // Función para actualizar tablas de manera eficiente después de conectar
    function updateTablesAfterConnection(masterId, childId, connectionData) {
        console.log('CVAV DEBUG: updateTablesAfterConnection() called with:', masterId, childId, connectionData);
        
        // Obtener información del producto conectado para agregar a la tabla de conectados
        var connectedProduct = null;
        if (connectionData.connected_products && connectionData.connected_products.length > 0) {
            connectedProduct = connectionData.connected_products[0];
        }
        
        // Eliminar filas de la tabla de productos coincidentes
        removeMatchingProductRows(masterId, childId, connectionData);
        
        // Agregar fila a la tabla de productos conectados si el proceso fue exitoso
        if (connectedProduct) {
            addConnectedProductRow(connectedProduct);
        }
    }

    // Función para eliminar filas de productos coincidentes
    function removeMatchingProductRows(masterId, childId, connectionData) {
        console.log('CVAV DEBUG: removeMatchingProductRows() called with:', masterId, childId, connectionData);
        
        var rowsToRemove = [];
        var masterParentId = connectionData.master_parent_id || masterId;
        var childParentId = connectionData.child_parent_id || childId;
        
        // Primero, recopilar información de las variaciones específicas a eliminar del backend
        var variationsToRemove = [];
        if (connectionData.removed_variations && connectionData.removed_variations.length > 0) {
            connectionData.removed_variations.forEach(function(variation) {
                if (variation.master_id > 0) {
                    variationsToRemove.push(variation.master_id);
                }
                if (variation.child_id > 0) {
                    variationsToRemove.push(variation.child_id);
                }
            });
        }
        
        console.log('CVAV DEBUG: Variations to remove from backend:', variationsToRemove);
        
        // Recopilar todas las filas que deben eliminarse
        $('#matching-products-tbody tr').each(function() {
            var row = $(this);
            var rowMasterId = row.find('.cvav-connect-matching-product').data('master-id');
            var rowChildId = row.find('.cvav-connect-matching-product').data('child-id');
            var shouldRemove = false;
            
            // Eliminar si es exactamente la fila conectada
            if (rowMasterId == masterId && rowChildId == childId) {
                shouldRemove = true;
                console.log('CVAV DEBUG: Removing exact match row - Master:', rowMasterId, 'Child:', rowChildId);
            }
            // Eliminar si el master ID está en la lista de variaciones a eliminar
            else if (variationsToRemove.indexOf(parseInt(rowMasterId)) !== -1) {
                shouldRemove = true;
                console.log('CVAV DEBUG: Removing master variation from list - Master:', rowMasterId);
            }
            // Eliminar si el child ID está en la lista de variaciones a eliminar
            else if (variationsToRemove.indexOf(parseInt(rowChildId)) !== -1) {
                shouldRemove = true;
                console.log('CVAV DEBUG: Removing child variation from list - Child:', rowChildId);
            }
            // Eliminar si es del mismo producto padre master (todas las variaciones del master)
            else if (rowMasterId == masterParentId) {
                shouldRemove = true;
                console.log('CVAV DEBUG: Removing master parent variation - Master:', rowMasterId, 'Parent:', masterParentId);
            }
            // Eliminar si es del mismo producto padre child (todas las variaciones del child)
            else if (rowChildId == childParentId) {
                shouldRemove = true;
                console.log('CVAV DEBUG: Removing child parent variation - Child:', rowChildId, 'Parent:', childParentId);
            }
            
            if (shouldRemove) {
                rowsToRemove.push(row);
            }
        });
        
        // Eliminar las filas identificadas con animación
        console.log('CVAV DEBUG: Removing', rowsToRemove.length, 'rows from matching products table');
        rowsToRemove.forEach(function(row, index) {
            setTimeout(function() {
                row.fadeOut(300, function() {
                    $(this).remove();
                    
                    // Verificar si no quedan filas después de la última eliminación
                    if (index === rowsToRemove.length - 1) {
                        setTimeout(function() {
                            if ($('#matching-products-tbody tr:visible').length === 0) {
                                $('#matching-products-empty').show();
                                $('#matching-products-content').hide();
                                $('#matching-products-counter').text('(0)').addClass('zero');
                            } else {
                                var remainingCount = $('#matching-products-tbody tr:visible').length;
                                $('#matching-products-counter').text('(' + remainingCount + ')').removeClass('zero');
                            }
                        }, 100);
                    }
                });
            }, index * 50); // Escalonar las eliminaciones para mejor efecto visual
        });
        
        // Si no hay filas para eliminar, verificar el contador inmediatamente
        if (rowsToRemove.length === 0) {
            var remainingCount = $('#matching-products-tbody tr:visible').length;
            $('#matching-products-counter').text('(' + remainingCount + ')').removeClass('zero');
        }
    }

    // Función para verificar si un ID es variación del mismo producto padre
    function isVariationOfSameParent(variationId, parentId) {
        // Si el ID de la variación es diferente al ID del padre, es una variación
        return variationId != parentId && variationId > 0 && parentId > 0;
    }

    // Función para agregar fila a la tabla de productos conectados
    function addConnectedProductRow(connectedProduct) {
        console.log('CVAV DEBUG: addConnectedProductRow() called with:', connectedProduct);
        
        // Ocultar mensaje de tabla vacía si existe
        $('#connected-products-empty').hide();
        $('#connected-products-content').show();
        
        // Crear la nueva fila
        var newRow = '<tr>';
        newRow += '<td><strong>' + connectedProduct.master_name + '</strong><br><small>ID: ' + connectedProduct.master_id + '</small></td>';
        newRow += '<td>' + connectedProduct.master_sku + '</td>';
        newRow += '<td><strong>' + connectedProduct.child_name + '</strong><br><small>ID: ' + connectedProduct.child_id + '</small></td>';
        newRow += '<td>' + connectedProduct.child_sku + '</td>';
        newRow += '<td>';
        newRow += '<button type="button" class="button cvav-disconnect-connected-product" ';
        newRow += 'data-master-id="' + connectedProduct.master_id + '" ';
        newRow += 'data-child-id="' + connectedProduct.child_id + '" ';
        newRow += 'data-child-site-id="' + connectedProduct.child_site_id + '">';
        newRow += 'Desconectar</button>';
        newRow += '</td>';
        newRow += '</tr>';
        
        // Agregar la fila con animación
        var tbody = $('#connected-products-tbody');
        var newRowElement = $(newRow);
        newRowElement.hide();
        tbody.append(newRowElement);
        newRowElement.fadeIn(300);
    }

    // Desconectar productos conectados
    function disconnectConnectedProducts(masterId, childId, childSiteId) {
        console.log('CVAV DEBUG: disconnectConnectedProducts() called with:', masterId, childId, childSiteId);
        
        if (!confirm('¿Estás seguro de que quieres desconectar estos productos?')) {
            return;
        }

        var button = $('.cvav-disconnect-connected-product[data-master-id="' + masterId + '"][data-child-id="' + childId + '"]');
        var originalText = button.text();
        button.prop('disabled', true).text('Desconectando...');

        $.ajax({
            url: cvav_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cvav_disconnect_products',
                nonce: cvav_products_nonce,
                master_product_id: masterId,
                child_product_id: childId,
                child_site_id: childSiteId
            },
            success: function(response) {
                console.log('CVAV DEBUG: disconnectConnectedProducts() - AJAX response:', response);
                
                if (response.success) {
                    showNotification('Productos desconectados exitosamente.', 'success');
                    
                    // Recargar ambas tablas después de un pequeño delay para asegurar que los cambios se han aplicado
                    setTimeout(function() {
                        loadMatchingProducts(childSiteId);
                        loadConnectedProducts(childSiteId);
                    }, 500);
                } else {
                    showNotification('Error al desconectar productos: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('CVAV DEBUG: disconnectConnectedProducts() - AJAX error:', xhr, status, error);
                showNotification('Error de conexión al desconectar productos.', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    // Actualizar la función displayConnectedProducts para actualizar el contador
    function displayConnectedProducts(connections) {
        console.log('CVAV DEBUG: displayConnectedProducts() called with connections:', connections);
        
        var tbody = $('#connected-products-tbody');
        tbody.empty();

        if (connections.length === 0) {
            $('#connected-products-empty').show();
            $('#connected-products-content').hide();
            $('#connected-products-counter').text('(0)').addClass('zero');
        } else {
            connections.forEach(function(connection) {
                // Crear badge de tipo de producto si está disponible
                var masterTypeBadge = '';
                var childTypeBadge = '';
                
                if (connection.master_product_type) {
                    masterTypeBadge = '<span class="cvav-product-type-badge cvav-product-type-' + connection.master_product_type + '">' + connection.master_product_type + '</span>';
                }
                
                if (connection.child_product_type) {
                    childTypeBadge = '<span class="cvav-product-type-badge cvav-product-type-' + connection.child_product_type + '">' + connection.child_product_type + '</span>';
                }
                
                // Crear links para los productos
                var masterProductLink = '<a href="' + connection.master_edit_url + '" target="_blank" class="cvav-product-link">' + connection.master_product_name + '</a>';
                var childProductLink = '<a href="' + connection.child_edit_url + '" target="_blank" class="cvav-product-link">' + connection.child_product_name + '</a>';
                
                var row = '<tr>';
                row += '<td><strong>' + masterProductLink + '</strong>' + masterTypeBadge + '<br><small>ID: ' + connection.master_product_id + '</small></td>';
                row += '<td>' + connection.master_product_sku + '</td>';
                row += '<td><strong>' + childProductLink + '</strong>' + childTypeBadge + '<br><small>ID: ' + connection.child_product_id + '</small></td>';
                row += '<td>';
                row += '<button type="button" class="button button-link-delete cvav-disconnect-connected-product" ';
                row += 'data-master-id="' + connection.master_product_id + '" ';
                row += 'data-child-id="' + connection.child_product_id + '" ';
                row += 'data-child-site-id="' + connection.child_site_id + '">';
                row += 'Desconectar</button>';
                row += '</td>';
                row += '</tr>';
                tbody.append(row);
            });

            $('#connected-products-content').show();
            $('#connected-products-empty').hide();
            $('#connected-products-counter').text('(' + connections.length + ')').removeClass('zero');
        }

        $('#connected-products-section').show();
    }

    // Inicializar la nueva funcionalidad cuando se carga la página de productos
    if (window.location.href.indexOf('page=cvav-connector-products') !== -1) {
        console.log('CVAV DEBUG: Products page detected, initializing matching products functionality');
        initMatchingProducts();
    } else {
        console.log('CVAV DEBUG: Not on products page, current URL:', window.location.href);
    }
}); 