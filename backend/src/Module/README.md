# Módulos del ERP (src/Module)

Cada módulo de negocio vive en su propio directorio con esta estructura mínima (§23.2 del Documento Maestro):

```
Module/<Nombre>/
├── Controller/       # Solo recibe requests y delega a Services
├── Service/          # Lógica de negocio y orquestación transaccional
├── Repository/       # Acceso a datos (Doctrine)
├── Entity/           # Entidades del dominio
├── Dto/              # Objetos de entrada/salida de la API
├── Validator/        # Validaciones específicas del módulo
├── Event/            # Eventos de dominio (ej. SaleCompleted)
├── EventListener/    # Reacciones a eventos de otros módulos
└── Security/         # Voters y permisos del módulo
```

Módulos planificados (orden de implementación por fases):

| Módulo | Fase |
|---|---|
| `Security` (usuarios, roles, permisos) | 0 |
| `Customer` | 1 |
| `Supplier` | 1 |
| `Catalog` (marcas, categorías, métodos de pago, bancos) | 1 |
| `Motorcycle` (modelos + unidades + expediente) | 2 |
| `Inventory` (repuestos, stock, Kardex, compatibilidades) | 3 |
| `Purchasing` | 4 |
| `CashRegister` | 5 |
| `Sales` (cotización, reserva, venta, CxC) | 6 |
| `Invoicing` (SUNAT vía PSE/OSE) | 7 |
| `Workshop` | 8 |
| `Reporting` y `Dashboard` | 9 |

Reglas: los módulos NO se referencian entre sí directamente; se comunican
mediante eventos de dominio o interfaces definidas en `src/Shared`.
