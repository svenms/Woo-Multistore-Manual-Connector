# Woo Multisite Manual Connector

Plugin para conectar productos y atributos entre sitios master y slave usando WooCommerce Multistore.

## Descripción

Este plugin proporciona una interfaz de administración para conectar atributos de productos entre múltiples tiendas WooCommerce utilizando el sistema de relaciones de WooCommerce Multistore. Una vez conectados, los atributos se sincronizan automáticamente cuando se actualizan en cualquiera de las tiendas configuradas.

## Características

- **Interfaz de administración intuitiva**: Gestión fácil de conexiones entre atributos
- **Integración con WooCommerce Multistore**: Utiliza el sistema nativo de relaciones
- **Conexión manual y automática**: Conecta atributos manualmente o automáticamente por nombre
- **Validación en tiempo real**: Verifica la validez de las conexiones antes de aplicarlas
- **Notificaciones**: Sistema de notificaciones para informar sobre el estado de las operaciones
- **Configuración flexible**: Selección de sitios master y slave
- **Compatibilidad con HPOS**: Soporte para High-Performance Order Storage
- **Sincronización forzada**: Opción para forzar la actualización de productos

## Requisitos

- WordPress 5.8 o superior
- WooCommerce 7.0 o superior
- WooCommerce Multistore activo
- PHP 7.4 o superior
- Red multisite configurada

## Instalación

1. Sube el plugin a la carpeta `/wp-content/plugins/`
2. Activa el plugin desde el panel de administración
3. Ve a **Woo Multisite Connector > Configuración** para configurar los sitios
4. Configura los sitios master y slave según tus necesidades

## Configuración

### Configuración Inicial

1. **Seleccionar Sitios**: 
   - **Sitio Master**: Selecciona el sitio principal que controlará las conexiones
   - **Sitio Slave**: Selecciona el sitio secundario que recibirá las conexiones

2. **Opciones Adicionales**:
   - **Conectar automáticamente**: Conecta atributos con el mismo nombre automáticamente
   - **Sincronización automática**: Activa la sincronización automática de productos
   - **Intervalo de sincronización**: Configura la frecuencia de sincronización
   - **Email de notificación**: Email para recibir notificaciones
   - **Forzar actualización**: Habilita la actualización forzada de productos

### Gestión de Conexiones

1. **Ver Conexiones Existentes**: 
   - Ve a **Woo Multisite Connector > Conexiones**
   - Visualiza todas las conexiones activas entre sitios

2. **Conectar Nuevos Atributos**:
   - Haz clic en "Conectar Nuevos Atributos"
   - Selecciona el atributo del sitio master
   - Selecciona el atributo del sitio slave
   - Confirma la conexión

3. **Desconectar Atributos**:
   - En la lista de conexiones, haz clic en "Desconectar"
   - Confirma la acción

## Uso

### Conectar Atributos Manualmente

1. Ve a **Woo Multisite Connector > Conexiones**
2. Haz clic en "Conectar Nuevos Atributos"
3. Selecciona los atributos que deseas conectar
4. Haz clic en "Conectar"

### Conectar Atributos Automáticamente

1. Ve a **Woo Multisite Connector > Configuración**
2. Activa "Conectar automáticamente atributos con el mismo nombre"
3. Los nuevos atributos con nombres idénticos se conectarán automáticamente

### Verificar Conexiones

1. Ve a **Woo Multisite Connector > Conexiones**
2. Revisa la lista de atributos conectados
3. El estado "Conectado" indica que la sincronización está activa

### Sincronización de Productos

1. Ve a **Woo Multisite Connector > Productos**
2. Selecciona los productos que deseas sincronizar
3. Haz clic en "Sincronizar Productos"
4. Los productos se sincronizarán entre los sitios configurados

## Estructura de Base de Datos

El plugin utiliza las tablas existentes de WooCommerce Multistore:

```sql
-- Tabla de relaciones de atributos (WooCommerce Multistore)
wp_{site_id}_woo_multistore_attributes_relationships
```

### Campos Utilizados

- `attribute_id`: ID del atributo en el sitio master
- `child_attribute_id`: ID del atributo en el sitio slave

## Hooks y Filtros

### Actions

```php
// Antes de conectar atributos
do_action('cvav_before_connect_attributes', $master_attribute_id, $slave_attribute_id);

// Después de conectar atributos
do_action('cvav_after_connect_attributes', $master_attribute_id, $slave_attribute_id, $result);

// Antes de desconectar atributos
do_action('cvav_before_disconnect_attributes', $master_attribute_id, $slave_attribute_id);

// Después de desconectar atributos
do_action('cvav_after_disconnect_attributes', $master_attribute_id, $slave_attribute_id, $result);

// Antes de sincronizar productos
do_action('cvav_before_sync_products', $product_ids);

// Después de sincronizar productos
do_action('cvav_after_sync_products', $product_ids, $result);
```

### Filters

```php
// Filtrar configuración del plugin
apply_filters('cvav_connector_settings', $settings);

// Filtrar atributos disponibles
apply_filters('cvav_available_attributes', $attributes, $site_id);

// Filtrar conexiones existentes
apply_filters('cvav_existing_connections', $connections);

// Filtrar productos para sincronización
apply_filters('cvav_products_to_sync', $product_ids);
```

## API REST

### Endpoints Disponibles

```
GET /wp-json/cvav/v1/connections
GET /wp-json/cvav/v1/attributes/{site_id}
GET /wp-json/cvav/v1/products/{site_id}
POST /wp-json/cvav/v1/connections
POST /wp-json/cvav/v1/sync-products
DELETE /wp-json/cvav/v1/connections/{connection_id}
```

### Ejemplo de Uso

```php
// Obtener conexiones
$response = wp_remote_get('/wp-json/cvav/v1/connections');

// Conectar atributos
$response = wp_remote_post('/wp-json/cvav/v1/connections', array(
    'body' => array(
        'master_attribute_id' => 123,
        'slave_attribute_id' => 456
    )
));

// Sincronizar productos
$response = wp_remote_post('/wp-json/cvav/v1/sync-products', array(
    'body' => array(
        'product_ids' => [1, 2, 3]
    )
));
```

## Solución de Problemas

### Problemas Comunes

1. **Plugin no aparece en el menú**:
   - Verifica que WooCommerce Multistore esté activo
   - Revisa los logs de errores de WordPress

2. **No se pueden conectar atributos**:
   - Verifica que los sitios estén configurados correctamente
   - Asegúrate de que los atributos existan en ambos sitios

3. **Sincronización no funciona**:
   - Verifica que WooCommerce Multistore esté configurado correctamente
   - Revisa la configuración de red multisite

4. **Sitios no aparecen en la lista**:
   - Verifica que WooCommerce esté activo en todos los sitios
   - Confirma que la red multisite esté configurada correctamente

### Logs de Error

Los errores se registran en:
- `wp-content/debug.log` (si WP_DEBUG está activado)
- Panel de administración de WordPress

### Verificación de Estado

1. **Verificar Configuración**:
   - Ve a **Woo Multisite Connector > Configuración**
   - Confirma que los sitios estén seleccionados

2. **Verificar Conexiones**:
   - Ve a **Woo Multisite Connector > Conexiones**
   - Revisa que las conexiones aparezcan correctamente

3. **Verificar WooCommerce Multistore**:
   - Confirma que WooCommerce Multistore esté activo
   - Verifica la configuración de red multisite

4. **Debug de Sitios Disponibles**:
   - Usa la función de debug para verificar qué sitios están disponibles
   - Verifica que WooCommerce esté activo en todos los sitios

## Seguridad

### Permisos Requeridos

- `manage_options`: Para acceder a la configuración
- `manage_woocommerce`: Para gestionar atributos y productos

### Validaciones

- Verificación de nonces en todas las operaciones AJAX
- Sanitización de datos de entrada
- Validación de permisos de usuario
- Verificación de existencia de atributos y productos

### Recomendaciones

1. **Backup**: Realiza backup antes de instalar
2. **Testing**: Prueba en entorno de desarrollo
3. **Updates**: Mantén WordPress y WooCommerce actualizados
4. **Security**: Usa HTTPS en producción

## Compatibilidad

### Versiones Soportadas

- **WordPress**: 5.8 - 6.8
- **WooCommerce**: 7.0 - 9.9.5
- **WooCommerce Multistore**: Versiones compatibles
- **PHP**: 7.4 - 8.3

### Plugins Compatibles

- WooCommerce Multistore
- WooCommerce Subscriptions
- WooCommerce Bookings
- WooCommerce Memberships

### Temas Compatibles

- Todos los temas compatibles con WooCommerce
- Temas personalizados que respeten los hooks de WordPress

## Desarrollo

### Estructura del Plugin

```
woo-multisite-manual-connector/
├── woo-multisite-manual-connector.php
├── includes/
│   ├── class-cvav-admin.php
│   ├── class-cvav-connector.php
│   ├── class-cvav-settings.php
│   ├── class-cvav-ajax.php
│   └── class-cvav-products.php
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── languages/
├── README.md
├── CHANGELOG.md
├── HPOS_COMPATIBILITY.md
└── uninstall.php
```

### Clases Principales

- **CVAV_Admin**: Interfaz de administración
- **CVAV_Connector**: Lógica principal de conexiones
- **CVAV_Settings**: Gestión de configuración
- **CVAV_Ajax**: Manejo de operaciones AJAX
- **CVAV_Products**: Gestión de productos y sincronización

### Extensibilidad

El plugin está diseñado para ser extensible:

```php
// Agregar validación personalizada
add_filter('cvav_validate_connection', 'mi_validacion_personalizada');

// Agregar acción después de conectar
add_action('cvav_after_connect_attributes', 'mi_funcion_personalizada');

// Agregar filtro para productos
add_filter('cvav_products_to_sync', 'filtrar_productos_personalizado');
```

## Changelog

### Versión 1.1.1
- Mejoras en la detección de sitios disponibles
- Soporte mejorado para WooCommerce Multistore
- Correcciones en la interfaz de administración
- Mejoras en el sistema de debug

### Versión 1.0.0
- Lanzamiento inicial
- Interfaz de administración básica
- Conexión manual de atributos
- Integración con WooCommerce Multistore
- Sistema de notificaciones

## Soporte

Para soporte técnico:
- Email: info@woomultisiteconnector.com
- Documentación: https://woomultisiteconnector.com
- GitHub: https://github.com/svenms/Woo-Multistore-Manual-Connector

## Créditos

Desarrollado para la gestión de productos y atributos entre tiendas WooCommerce Multistore. 