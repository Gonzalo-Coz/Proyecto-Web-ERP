# Componentes UI base (reutilizables)

Aquí viven los componentes transversales que garantizan la interfaz homogénea
exigida por el Documento Maestro (§5). Se implementarán en la Fase 0 (parte 2)
y se reutilizarán en TODOS los módulos:

- `DataTable.vue` — tabla con ordenamiento, paginación, búsqueda rápida, filtros avanzados, exportación PDF/Excel e impresión
- `SearchBar.vue`, `AdvancedFilters.vue`
- `BaseModal.vue`, `ConfirmDialog.vue`
- `FormField.vue` (validaciones espejo del backend)
- `FileAttachments.vue` (adjuntos)
- `AuditTrail.vue` (historial de cambios)
- `StatusBadge.vue` (Activo/Inactivo y estados de dominio)

Regla: ningún módulo implementa su propia tabla o formulario base;
siempre se componen a partir de estos componentes.
