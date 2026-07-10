# Núcleo transversal (src/Shared)

Implementa una sola vez las funciones comunes a todos los módulos (§5 del Documento Maestro) para su reutilización:

| Contexto | Responsabilidad |
|---|---|
| `Api/` | Controladores base, formato uniforme de respuestas/errores, paginación, filtros, ordenamiento |
| `Auditing/` | Auditoría automática (valores antes/después), historial de cambios, soft delete |
| `Security/` | Autenticación JWT, matriz rol-permiso (módulo/pantalla/acción), voters base |
| `Settings/` | Configuración centralizada en BD (§23.10) |
| `Export/` | Exportación PDF y Excel genérica |
| `Files/` | Adjuntos de documentos (polimórfico) |

Nada en `Shared` depende de módulos de negocio; la dependencia siempre es Módulo → Shared.
