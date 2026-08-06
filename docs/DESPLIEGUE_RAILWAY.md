# Despliegue en Railway — YIGM ERP

Arquitectura: 3 servicios en un proyecto de Railway.
1. **PostgreSQL** (administrado por Railway).
2. **Backend** (Symfony) — carpeta `backend/`, con `Dockerfile`.
3. **Frontend** (Vue) — carpeta `frontend/`, con `Dockerfile`. Proxya `/api` y `/uploads` al backend.

Archivos de despliegue ya incluidos: `backend/Dockerfile`, `backend/docker/*`, `frontend/Dockerfile`, `frontend/docker/*`.

---

## Paso 1 — Subir el código a GitHub

El proyecto ya es un repo Git. Falta conectarlo a GitHub.

1. Crea un repositorio **vacío** en GitHub (sin README), ej. `yigm-erp` (privado).
2. En `D:\Development\ERP\YIGM-ERP`, en la terminal:

```
git add .
git commit -m "Preparado para despliegue en Railway"
git branch -M main
git remote add origin https://github.com/<tu-usuario>/yigm-erp.git
git push -u origin main
```

> El `.gitignore` ya excluye secretos y dependencias (`.env.local`, `vendor/`, `node_modules/`, claves JWT, uploads). No se suben.

---

## Paso 2 — Crear el proyecto y la base de datos en Railway

1. Entra a Railway → **New Project** → **Deploy from GitHub repo** → elige `yigm-erp`.
2. En el proyecto, **+ New** → **Database** → **PostgreSQL**. Railway crea la base y expone la variable `DATABASE_URL`.

---

## Paso 3 — Servicio Backend

1. El servicio creado desde el repo: en **Settings** → **Root Directory** = `backend`. (Railway detecta el `Dockerfile`.)
2. **Settings → Networking → Generate Domain** (te da una URL pública `https://<algo>.up.railway.app`).
3. **Variables** (Settings → Variables) — agrega:

```
APP_ENV=prod
APP_SECRET=<genera uno nuevo: 32 hex>
DATABASE_URL=${{Postgres.DATABASE_URL}}
JWT_PASSPHRASE=<secreto nuevo>
CORS_ALLOW_ORIGIN=^https://<dominio-del-frontend>$
NUBEFACT_URL=<ruta de producción de NubeFact>
NUBEFACT_TOKEN=<token de producción>
NUBEFACT_AMBIENTE=produccion
NUBEFACT_TIMEOUT=20
APISPERU_BASE_URL=https://dniruc.apisperu.com/api/v1
APISPERU_TOKEN=<tu token APISPERU>
APISPERU_TIMEOUT=8
APISPERU_CAINFO=
```

   - `DATABASE_URL=${{Postgres.DATABASE_URL}}` referencia la base (ajusta el nombre si tu servicio Postgres se llama distinto).
   - `APISPERU_CAINFO` vacío: en Linux el CA bundle ya está, no hace falta.

4. **Volumen para uploads:** Settings → **Volumes** → New Volume → Mount path = `/app/public/uploads`. (Así las imágenes/logo persisten entre despliegues.)

5. Railway hará el build y deploy. Las **migraciones corren solas** al arrancar (entrypoint).

---

## Paso 4 — Servicio Frontend

1. **+ New** → **GitHub Repo** → el mismo `yigm-erp` → en **Settings → Root Directory** = `frontend`.
2. **Generate Domain** (te da la URL pública que usarán tus usuarios).
3. **Variables:**

```
BACKEND_URL=https://<url-publica-del-backend>.up.railway.app
```

   (La URL del backend del Paso 3. El frontend proxya `/api` y `/uploads` a esa URL — por eso no hay problemas de CORS.)

4. Vuelve al **backend** y pon en `CORS_ALLOW_ORIGIN` el dominio del frontend (por si alguna llamada va directa).

---

## Paso 5 — Configuración inicial (una sola vez)

En el servicio **Backend**, abre una consola (Railway → el servicio → pestaña de shell/`railway run`) y ejecuta:

```
php bin/console app:security:sync-permissions
php bin/console app:security:create-admin
```

- `sync-permissions` carga el catálogo de permisos.
- `create-admin` crea tu usuario administrador (te pedirá usuario/clave).

Luego entra a la app (URL del frontend), inicia sesión, y configura los datos de tu empresa (Configuración General) e importa tus productos/motos.

---

## Notas

- **JWT:** el par de claves se genera solo en el primer arranque con `JWT_PASSPHRASE`. Para que no se regenere en cada deploy (y no invalide sesiones), lo ideal es montar `config/jwt` en un volumen o fijar las claves como variables — se puede afinar luego.
- **HTTPS:** Railway lo da automático en ambos dominios.
- **Producción NubeFact:** cuando te habiliten, pon la ruta y token de producción y `NUBEFACT_AMBIENTE=produccion` en las variables del backend.
- **Primer deploy:** los Dockerfiles son una v1 estándar. Si el primer build falla, se revisa el log de Railway y se ajusta (es normal iterar una vez).
- **Reset de datos:** la base de producción arranca vacía; no se sube la data de prueba local.
