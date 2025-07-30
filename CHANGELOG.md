# Changelog

## [1.1.0] - 2024-12-19

### Agregado
- Compatibilidad con HPOS (High-Performance Order Storage) de WooCommerce
- Declaración de compatibilidad usando `FeaturesUtil::declare_compatibility()`
- Documentación sobre compatibilidad con HPOS

### Cambiado
- Reemplazado `get_post_meta()` por `get_meta()` en `class-cvav-products.php`
- Actualizada versión del plugin de 1.0.9 a 1.1.0

### Corregido
- Incompatibilidad con la característica "Almacenamiento de pedidos de alto rendimiento" de WooCommerce

### Técnico
- Uso de métodos de WooCommerce en lugar de funciones de WordPress para metadatos
- Mantenimiento de compatibilidad con versiones anteriores de WooCommerce

## [1.0.9] - Versión anterior

### Características
- Conexión de atributos entre tiendas WooCommerce Multistore
- Interfaz de administración para gestionar conexiones
- Sincronización automática de atributos
- Gestión de productos conectados
- Sistema de notificaciones y validaciones 