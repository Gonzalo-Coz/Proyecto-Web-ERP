# Bitácora Técnica — YIGM ERP

## Fase 0 — Preparación del Entorno (CERRADA · 2026-07-10)

**Entorno validado:** Windows 11 · PHP 8.5.8 · Symfony 7.4.14 · Composer 2.10.2 · PostgreSQL 17.5 · Node.js 22 LTS · pnpm · Git · VS Code.

**Hitos completados:**

1. `composer install` exitoso (incidencia resuelta: habilitación de `ext-sodium` y extensiones PostgreSQL en `php.ini`; `lcobucci/jwt 5.6.0` es la única versión compatible con PHP 8.5).
2. Variables de entorno correctas (`debug:dotenv`): `DATABASE_URL` con `serverVersion=17`, secretos reales en `.env.local` (no versionado).
3. Base de datos `yigm_erp` creada vía `doctrine:database:create`; conexión verificada contra PostgreSQL 17.5.
4. Par de claves JWT generado (`config/jwt/*.pem`, fuera de Git); `lexik:jwt:check-config` OK.
5. Servidor local operativo: `symfony server:start --port=8000`.

**Decisiones operativas registradas:**

- NO usar `symfony server:start -d` en Windows (bloqueo del archivo de log del daemon). Comando oficial del proyecto: `symfony server:start --port=8000`.
- Pruebas de API en PowerShell con `Invoke-RestMethod` (NO `curl.exe -d "{...}"`: PowerShell mutila las comillas escapadas del JSON).
- **Estándar del proyecto — unicidad + soft delete:** los índices únicos de entidades con eliminación lógica se definen como índices parciales de PostgreSQL (`WHERE deleted_at IS NULL`), permitiendo reutilizar el identificador de un registro dado de baja sin perder el histórico. Aplicado a users (username, email) y roles (code). Pendiente evaluar excepción para VIN en Fase 2.
- Gestor de paquetes frontend: **pnpm**.
- Advertencias informativas del Symfony CLI (TLS, listen 127.0.0.1) aceptadas sin acción para entorno de red local.

**No repetir:** composer install, configuración PHP/Symfony/PostgreSQL/Doctrine/JWT/variables/BD/servidor. Todo forma parte de la línea base.

---

## Fase 0 — Parte 2: Módulo Security + transversales (BACKEND VALIDADO · 2026-07-10)

Pruebas superadas: login JWT (token + user con permisos), `GET /auth/me` con Bearer, 401 sin token. Migración inicial ejecutada (6 tablas: users, roles, permissions, user_roles, role_permissions, audit_logs). Usuario administrador creado con rol ADMIN superadmin.

### Alcance implementado

Implementación según §22, §23.6, §23.7, §23.9 del Documento Maestro:

- `Shared/Doctrine`: timestamps automáticos, soft delete (interfaz + trait + filtro SQL global).
- `Shared/Auditing`: registro automático de auditoría (usuario, IP, módulo, entidad, acción, valores anteriores/nuevos).
- `Module/Security`: entidades `User`, `Role`, `Permission` (permisos dinámicos `modulo.pantalla.accion`), login JWT, voter de permisos, comando de creación de administrador.
