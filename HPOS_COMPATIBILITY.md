# Compatibilidad con HPOS (High-Performance Order Storage)

## Cambios Realizados

### Versión 1.1.0

Este plugin ha sido actualizado para ser compatible con la característica de **Almacenamiento de pedidos de alto rendimiento (HPOS)** de WooCommerce.

### Cambios Específicos

1. **Reemplazo de `get_post_meta()`**: 
   - **Archivo**: `includes/class-cvav-products.php`
   - **Línea**: 493
   - **Cambio**: Reemplazado `get_post_meta($child_product->get_id(), '_woonet_network_is_child_product_id', true)` por `$child_product->get_meta('_woonet_network_is_child_product_id')`

2. **Declaración de Compatibilidad**:
   - **Archivo**: `chilevapo-andesvapor-attributes-connector.php`
   - **Agregado**: Declaración de compatibilidad con HPOS usando `FeaturesUtil::declare_compatibility()`

### ¿Por qué estos cambios?

- **HPOS** almacena los pedidos en tablas personalizadas en lugar de usar la tabla `wp_posts`
- Las funciones `get_post_meta()`, `update_post_meta()`, y `delete_post_meta()` no funcionan correctamente con HPOS
- Los métodos de WooCommerce como `get_meta()`, `update_meta()`, y `delete_meta()` son compatibles con ambos sistemas

### Beneficios

- ✅ Compatible con HPOS
- ✅ Mantiene compatibilidad con el sistema tradicional de pedidos
- ✅ Mejor rendimiento cuando HPOS está habilitado
- ✅ Sin cambios en la funcionalidad del plugin

### Verificación

Para verificar que el plugin es compatible:

1. Ve a **WooCommerce > Configuración > Avanzado > Características**
2. Activa "Almacenamiento de pedidos de alto rendimiento"
3. El plugin ya no debería mostrar advertencias de incompatibilidad

### Notas Importantes

- Este plugin no maneja pedidos directamente, por lo que la compatibilidad con HPOS es principalmente preventiva
- Los cambios son mínimos y no afectan la funcionalidad existente
- Se recomienda probar en un entorno de desarrollo antes de aplicar en producción 