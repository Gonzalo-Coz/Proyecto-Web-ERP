# Guía de Despliegue — YIGM ERP (servidor local con acceso por red)

Objetivo: dejar el ERP corriendo en una máquina (PC/mini PC) que **da el servicio localmente** y puede ser accedido por otros equipos de la red (o por internet con las precauciones del final). Todo se parametriza por variables de entorno para mantener la portabilidad (§Infraestructura futura del Documento Maestro).

> Regla de oro: **ningún secreto en el repositorio**. Todo valor sensible va en `backend/.env.local` (no versionado). `.env` solo lleva valores por defecto no secretos.

---

## 0. Requisito crítico — CA bundle para HTTPS saliente (arregla las consultas DNI/RUC)

PHP en Windows **no trae un CA bundle configurado**, por lo que toda llamada HTTPS saliente (APISPERU hoy; SUNAT/facturación mañana) falla con `cURL 60: SSL certificate problem`. Es la causa del error "No se pudo conectar con el proveedor de consultas".

**Solución (una sola vez, sirve para TODAS las integraciones):**

1. Descarga el CA bundle oficial: **https://curl.se/ca/cacert.pem**
2. Guárdalo en una ruta estable, p. ej. `D:\Development\Tools\PHP\extras\ssl\cacert.pem`.
3. En tu `php.ini` (el que usa `php-cgi`; verifica con `php --ini`), configura:
   ```ini
   curl.cainfo = "D:\Development\Tools\PHP\extras\ssl\cacert.pem"
   openssl.cafile = "D:\Development\Tools\PHP\extras\ssl\cacert.pem"
   ```
4. Reinicia el servidor: `symfony server:stop` y `symfony server:start`.

**Alternativa portable (sin tocar php.ini):** en `backend/.env.local` añade
`APISPERU_CAINFO=D:\Development\Tools\PHP\extras\ssl\cacert.pem`. Esto solo cubre APISPERU; la opción de php.ini es preferible porque cubre todo el sistema.

Nunca se desactiva la verificación TLS (`CURLOPT_SSL_VERIFYPEER`): "funcionaría" pero sería inseguro.

---

## 1. Advertencias de `symfony server:start` (qué significan)

- **`run "symfony server:ca:install" first ... TLS`** — informativo: el server local no tiene HTTPS. Para HTTPS local ejecuta `symfony server:ca:install` una vez; o ignóralo si sirves por HTTP en la LAN.
- **`the local web server ... MUST never be used in a production setup`** — el server de la CLI es para desarrollo/uso local liviano. Para exposición seria a internet, usar un servidor web real (nginx/Caddy) — ver §4.
- **`Symfony CLI only listens on 127.0.0.1 by default`** — por eso otros equipos no pueden entrar. Para exponerlo en la red local, arranca con:
  ```powershell
  symfony server:start --port=8000 --allow-all-ip
  ```
  Luego otros equipos acceden por `http://<IP-del-servidor>:8000`.

---

## 2. Preparar el backend

```powershell
cd D:\Development\ERP\YIGM-ERP\backend
# Dependencias optimizadas
composer install --no-dev --optimize-autoloader
# Claves JWT (si no existen en el servidor)
php bin/console lexik:jwt:generate-keypair --skip-if-exists
# Migraciones
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:security:sync-permissions
```

`backend/.env.local` (ejemplo de producción — valores reales, NO versionado):
```
APP_ENV=prod
APP_SECRET=<genera uno largo y aleatorio>
DATABASE_URL="postgresql://usuario:password@127.0.0.1:5432/yigm_erp?serverVersion=17&charset=utf8"
JWT_PASSPHRASE=<passphrase de las claves JWT>
APISPERU_TOKEN=<tu token APISPERU>
# Orígenes permitidos del frontend (LAN/dominio). Ajusta a tu escenario:
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1|192\.168\.\d+\.\d+)(:[0-9]+)?$'
```

En `prod`, tras cambios: `php bin/console cache:clear` y `cache:warmup`.

---

## 3. Preparar el frontend

El frontend es una SPA que se **compila** a archivos estáticos:

```powershell
cd D:\Development\ERP\YIGM-ERP\frontend
# Apunta la SPA al backend accesible desde los navegadores cliente:
#   crea frontend/.env.local con:  VITE_API_URL=/api/v1   (si se sirve al mismo origen)
#   o                              VITE_API_URL=http://<IP>:8000/api/v1  (dos orígenes)
pnpm install
pnpm build      # genera frontend/dist/
```

Dos formas de servir la SPA:

- **A · Mismo origen (recomendado):** un servidor web (nginx/Caddy) sirve `frontend/dist/` y hace *proxy* de `/api` y `/uploads` al backend (puerto 8000). Sin problemas de CORS. Es lo más parecido a producción.
- **B · Dos orígenes:** sirves la SPA por un lado y el backend por otro; entonces **debes** incluir el origen de la SPA en `CORS_ALLOW_ORIGIN`.

---

## 4. Exposición en red / internet

- **Solo LAN (recomendado para tu caso):** en dos terminales del servidor:
  ```powershell
  # Backend accesible en la red
  cd D:\Development\ERP\YIGM-ERP\backend
  symfony server:start --port=8000 --allow-all-ip

  # Frontend compilado, servido en la red (el proxy /api y /uploads ya está
  # configurado también para `preview`, así que NO hay problema de CORS)
  cd D:\Development\ERP\YIGM-ERP\frontend
  pnpm build
  pnpm preview --host 0.0.0.0 --port 4173
  ```
  Los demás equipos entran por `http://<IP-del-servidor>:4173`. (Averigua la IP con `ipconfig`.) El navegador del cliente pega a `/api`, y el `preview` lo reenvía al backend local — por eso el backend puede seguir en `127.0.0.1`.
- **Internet / producción real (recomendado):** poner **Caddy** o **nginx** al frente:
  - Sirve `frontend/dist/` como estáticos.
  - Proxy `/api` y `/uploads` → `127.0.0.1:8000` (backend).
  - HTTPS automático (Caddy con Let's Encrypt) — imprescindible si sale a internet, porque se envían JWT y datos.
  - Requiere un dominio y abrir/redirigir el puerto 80/443 en el router.

---

## 5. Respaldos y portabilidad (§Respaldos / §Migraciones del Maestro)

Respaldar periódicamente: base de datos (`pg_dump`), carpeta `backend/public/uploads/` (imágenes) y `backend/.env.local` + claves JWT. La app es portable: para migrar a otra máquina/VPS/Docker basta copiar el proyecto, restaurar la BD y ajustar `.env.local` — sin tocar el código.

---

## 6. Checklist de "listo para servidor local"

- [ ] CA bundle configurado (§0) — consultas DNI/RUC y futuras integraciones funcionan.
- [ ] `.env.local` con secretos reales y `APP_ENV=prod`.
- [ ] Claves JWT generadas en el servidor.
- [ ] Migraciones + permisos aplicados.
- [ ] Frontend compilado (`pnpm build`) y servido; `VITE_API_URL` correcto.
- [ ] `CORS_ALLOW_ORIGIN` incluye el origen real del frontend.
- [ ] Servidor accesible por red (`--allow-all-ip` o web server al frente).
- [ ] (Internet) HTTPS con dominio y proxy inverso.
- [ ] Respaldos programados (BD + uploads + config).
