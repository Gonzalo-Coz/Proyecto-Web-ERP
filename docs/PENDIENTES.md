# Pendientes técnicos — YIGM ERP

## P-001 · No se puede recrear usuario eliminado ("javier") — ABIERTO

**Síntoma:** tras crear la migración `Version20260711001000` (índices únicos parciales `WHERE deleted_at IS NULL`), la creación del usuario "javier" sigue fallando en la interfaz.

**Hipótesis, en orden de probabilidad:**

1. La migración no llegó a ejecutarse (`doctrine:migrations:migrate` no corrido o cancelado en la confirmación).
2. El correo del nuevo "javier" coincide con el del eliminado y el mensaje corresponde al índice de email (mismo problema, otra columna) — descartable si la migración corrió.
3. El error mostrado no es de unicidad sino de validación (contraseña < 8, roles, etc.) — el modal muestra el `detail` de la API; revisar el texto exacto.

**Diagnóstico cuando se retome:**

```powershell
# 1. ¿Se ejecutó la migración?
php bin/console doctrine:migrations:status
# 2. ¿Los índices son parciales? (deben mostrar "WHERE (deleted_at IS NULL)")
php bin/console dbal:run-sql "SELECT indexname, indexdef FROM pg_indexes WHERE tablename IN ('users','roles');"
# 3. ¿Qué registros 'javier' existen (incluidos eliminados)?
php bin/console dbal:run-sql "SELECT id, username, email, deleted_at FROM users;"
```

**Solución esperada:** si `indexdef` no muestra el `WHERE`, ejecutar `php bin/console doctrine:migrations:migrate`. Si ya lo muestra, capturar el error exacto de la respuesta HTTP (pestaña Red del navegador) y analizar.
