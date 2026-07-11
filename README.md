# YIGM ERP — Yamaha Integral Global Motors

ERP empresarial para la gestión integral de venta de motocicletas, repuestos, taller, compras, inventario, caja y facturación electrónica SUNAT.

**Especificación oficial:** Documento Maestro v1.0 (ver `docs/`).

## Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | Symfony (PHP 8.5) — API REST |
| Frontend | Vue 3 + Vite + TypeScript + Tailwind CSS |
| Estado | Pinia |
| Base de datos | PostgreSQL 17 |
| Autenticación | JWT (LexikJWTAuthenticationBundle) |
| ORM | Doctrine ORM + Migrations |
| Documentación API | OpenAPI / Swagger (NelmioApiDocBundle) |

## Estructura del repositorio

```
YIGM-ERP/
├── backend/     # API Symfony (src/Shared + src/Module/<módulo>)
├── frontend/    # SPA Vue 3 (src/views, components, stores, services)
└── docs/        # Documentación del proyecto y decisiones técnicas
```

## Puesta en marcha (Windows / VS Code)

Requisitos ya instalados en `D:\Development\Tools`: PHP 8.5, Composer, Symfony CLI, Git, Node.js. Asegúrate de que estén en el `PATH`.

### 1. Base de datos

Crear la base de datos en PostgreSQL local:

```sql
CREATE DATABASE yigm_erp;
```

Ajustar credenciales en `backend/.env.local` (crear el archivo si no existe):

```
DATABASE_URL="postgresql://postgres:TU_PASSWORD@127.0.0.1:5432/yigm_erp?serverVersion=17&charset=utf8"
```

### 2. Backend

```bash
cd backend
composer install
php bin/console lexik:jwt:generate-keypair
symfony server:start --port=8000
# alternativa sin Symfony CLI:
# php -S 127.0.0.1:8000 -t public
```

Verificar: http://127.0.0.1:8000/api/v1/health

### 3. Frontend

```bash
cd frontend
pnpm install
pnpm dev
```

Abrir: http://localhost:5173 (el proxy `/api` apunta al backend en el puerto 8000).

## Arranque diario (tras reiniciar el equipo)

PostgreSQL arranca automáticamente como servicio de Windows. Solo necesitas dos terminales en VS Code:

```powershell
# Terminal 1 — Backend
cd D:\Development\ERP\YIGM-ERP\backend
symfony server:start --port=8000

# Terminal 2 — Frontend
cd D:\Development\ERP\YIGM-ERP\frontend
pnpm dev
```

Abrir http://localhost:5173. Las migraciones (`doctrine:migrations:migrate`) solo se ejecutan cuando hay código nuevo con cambios de base de datos.

## Ramas Git

- `main` — producción (estable)
- `develop` — desarrollo activo

Commits descriptivos en español siguiendo el formato `tipo: descripción` (feat, fix, docs, refactor, test, chore).

## Documentación

- `docs/DECISIONES.md` — decisiones técnicas aprobadas
- `docs/DOCUMENTO_DE_ADICIONES.md` — nuevos requerimientos fuera del Documento Maestro
- `docs/INFORME_TECNICO_YIGM_ERP.md` — análisis técnico inicial
