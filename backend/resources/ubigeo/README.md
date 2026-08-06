# Datos de Ubigeo (Perú)

`UbigeoService` lee estos 3 archivos JSON (departamentos, provincias, distritos del Perú).
Descárgalos una vez en esta misma carpeta. Puedes commitearlos (~450 KB) para que el
despliegue funcione sin depender de internet.

Fuente: `joseluisq/ubigeos-peru` (servido por jsDelivr).

## Descargar (PowerShell, una sola vez)

```powershell
cd D:\Development\ERP\YIGM-ERP\backend\resources\ubigeo
$base = "https://cdn.jsdelivr.net/gh/joseluisq/ubigeos-peru@1.0.0/json"
Invoke-WebRequest "$base/departamentos.json" -OutFile "departamentos.json"
Invoke-WebRequest "$base/provincias.json"    -OutFile "provincias.json"
Invoke-WebRequest "$base/distritos.json"     -OutFile "distritos.json"
```

Verifica que los 3 archivos queden en esta carpeta. Con eso, los desplegables de
distrito/provincia/departamento funcionan (offline, sin API externa).

Formato esperado (array de objetos): `id_ubigeo`, `nombre_ubigeo`, `id_padre_ubigeo`,
`nivel_ubigeo` (1=departamento, 2=provincia, 3=distrito).
