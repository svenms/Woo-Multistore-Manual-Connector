# Chile Vapo - Andes Vapor Attributes Connector

Plugin para conectar atributos de productos entre Chile Vapo y Andes Vapor usando WooCommerce Multistore.

## Descripción

Este plugin proporciona una interfaz de administración para conectar atributos de productos entre dos tiendas WooCommerce (Chile Vapo y Andes Vapor) utilizando el sistema de relaciones de WooCommerce Multistore. Una vez conectados, los atributos se sincronizan automáticamente cuando se actualizan en cualquiera de las tiendas.

## Características

- **Interfaz de administración intuitiva**: Gestión fácil de conexiones entre atributos
- **Integración con WooCommerce Multistore**: Utiliza el sistema nativo de relaciones
- **Conexión manual y automática**: Conecta atributos manualmente o automáticamente por nombre
- **Validación en tiempo real**: Verifica la validez de las conexiones antes de aplicarlas
- **Notificaciones**: Sistema de notificaciones para informar sobre el estado de las operaciones
- **Configuración flexible**: Selección de sitios y opciones de configuración

## Requisitos

- WordPress 5.0 o superior
- WooCommerce 3.6.0 o superior
- WooCommerce Multistore activo
- PHP 7.4 o superior
- Red multisite configurada

## Instalación

1. Sube el plugin a la carpeta `/wp-content/plugins/`
2. Activa el plugin desde el panel de administración
3. Ve a **Conector CV-AV > Configuración** para configurar los sitios
4. Configura Chile Vapo y Andes Vapor como sitios de origen y destino

## Configuración

### Configuración Inicial

1. **Seleccionar Sitios**: 
   - Chile Vapo: Selecciona el sitio que representa Chile Vapo
   - Andes Vapor: Selecciona el sitio que representa Andes Vapor

2. **Opciones Adicionales**:
   - Conectar automáticamente: Conecta atributos con el mismo nombre automáticamente
   - Email de notificación: Email para recibir notificaciones

### Gestión de Conexiones

1. **Ver Conexiones Existentes**: 
   - Ve a **Conector CV-AV > Conexiones**
   - Visualiza todas las conexiones activas

2. **Conectar Nuevos Atributos**:
   - Haz clic en "Conectar Nuevos Atributos"
   - Selecciona el atributo de Chile Vapo
   - Selecciona el atributo de Andes Vapor
   - Confirma la conexión

3. **Desconectar Atributos**:
   - En la lista de conexiones, haz clic en "Desconectar"
   - Confirma la acción

## Uso

### Conectar Atributos Manualmente

1. Ve a **Conector CV-AV > Conexiones**
2. Haz clic en "Conectar Nuevos Atributos"
3. Selecciona los atributos que deseas conectar
4. Haz clic en "Conectar"

### Conectar Atributos Automáticamente

1. Ve a **Conector CV-AV > Configuración**
2. Activa "Conectar automáticamente atributos con el mismo nombre"
3. Los nuevos atributos con nombres idénticos se conectarán automáticamente

### Verificar Conexiones

1. Ve a **Conector CV-AV > Conexiones**
2. Revisa la lista de atributos conectados
3. El estado "Conectado" indica que la sincronización está activa

## Estructura de Base de Datos

El plugin utiliza las tablas existentes de WooCommerce Multistore:

```sql
-- Tabla de relaciones de atributos (WooCommerce Multistore)
wp_woo_multistore_attributes_relationships
```

### Campos Utilizados

- `attribute_id`: ID del atributo en Chile Vapo
- `child_attribute_id`: ID del atributo en Andes Vapor

## Hooks y Filtros

### Actions

```php
// Antes de conectar atributos
do_action('cvav_before_connect_attributes', $chilevapo_id, $andesvapor_id);

// Después de conectar atributos
do_action('cvav_after_connect_attributes', $chilevapo_id, $andesvapor_id, $result);

// Antes de desconectar atributos
do_action('cvav_before_disconnect_attributes', $chilevapo_id, $andesvapor_id);

// Después de desconectar atributos
do_action('cvav_after_disconnect_attributes', $chilevapo_id, $andesvapor_id, $result);
```

### Filters

```php
// Filtrar configuración del plugin
apply_filters('cvav_connector_settings', $settings);

// Filtrar atributos disponibles
apply_filters('cvav_available_attributes', $attributes, $site_id);

// Filtrar conexiones existentes
apply_filters('cvav_existing_connections', $connections);
```

## API REST

### Endpoints Disponibles

```
GET /wp-json/cvav/v1/connections
GET /wp-json/cvav/v1/attributes/{site_id}
POST /wp-json/cvav/v1/connections
DELETE /wp-json/cvav/v1/connections/{connection_id}
```

### Ejemplo de Uso

```php
// Obtener conexiones
$response = wp_remote_get('/wp-json/cvav/v1/connections');

// Conectar atributos
$response = wp_remote_post('/wp-json/cvav/v1/connections', array(
    'body' => array(
        'chilevapo_attribute_id' => 123,
        'andesvapor_attribute_id' => 456
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

### Logs de Error

Los errores se registran en:
- `wp-content/debug.log` (si WP_DEBUG está activado)
- Panel de administración de WordPress

### Verificación de Estado

1. **Verificar Configuración**:
   - Ve a **Conector CV-AV > Configuración**
   - Confirma que los sitios estén seleccionados

2. **Verificar Conexiones**:
   - Ve a **Conector CV-AV > Conexiones**
   - Revisa que las conexiones aparezcan correctamente

3. **Verificar WooCommerce Multistore**:
   - Confirma que WooCommerce Multistore esté activo
   - Verifica la configuración de red multisite

## Seguridad

### Permisos Requeridos

- `manage_options`: Para acceder a la configuración
- `manage_woocommerce`: Para gestionar atributos

### Validaciones

- Verificación de nonces en todas las operaciones AJAX
- Sanitización de datos de entrada
- Validación de permisos de usuario
- Verificación de existencia de atributos

### Recomendaciones

1. **Backup**: Realiza backup antes de instalar
2. **Testing**: Prueba en entorno de desarrollo
3. **Updates**: Mantén WordPress y WooCommerce actualizados
4. **Security**: Usa HTTPS en producción

## Compatibilidad

### Versiones Soportadas

- **WordPress**: 5.0 - 6.8.1
- **WooCommerce**: 3.6.0 - 9.9.5
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
chilevapo-andesvapor-attributes-connector/
├── chilevapo-andesvapor-attributes-connector.php
├── includes/
│   ├── class-cvav-admin.php
│   ├── class-cvav-connector.php
│   ├── class-cvav-settings.php
│   └── class-cvav-ajax.php
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── languages/
├── README.md
└── uninstall.php
```

### Clases Principales

- **CVAV_Admin**: Interfaz de administración
- **CVAV_Connector**: Lógica principal de conexiones
- **CVAV_Settings**: Gestión de configuración
- **CVAV_Ajax**: Manejo de operaciones AJAX

### Extensibilidad

El plugin está diseñado para ser extensible:

```php
// Agregar validación personalizada
add_filter('cvav_validate_connection', 'mi_validacion_personalizada');

// Agregar acción después de conectar
add_action('cvav_after_connect_attributes', 'mi_funcion_personalizada');
```

## Changelog

### Versión 1.0.0
- Lanzamiento inicial
- Interfaz de administración básica
- Conexión manual de atributos
- Integración con WooCommerce Multistore
- Sistema de notificaciones

## Soporte

Para soporte técnico:
- Email: soporte@chilevapo.cl
- Documentación: [URL de documentación]
- GitHub: [URL del repositorio]

## Licencia

Este plugin es propiedad de Chile Vapo y está diseñado específicamente para su uso interno.

## Créditos

Desarrollado por Chile Vapo para la gestión de atributos entre tiendas WooCommerce Multistore. 