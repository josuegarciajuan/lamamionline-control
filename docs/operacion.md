# Operación

## 1) Ejecuciones programadas (cron)

## Avisos

Script:

- `cron_avisos.php`

Comportamiento:

- Carga bootstrap y ejecuta `avisos_run_all_generators(true)`.
- Salida esperada: `OK`.

Referencia existente (`README.txt`):

```cron
* * * * * /usr/bin/php /var/www/html/atupuerta/control/cron_avisos.php >/dev/null 2>&1
```

## Comercial

Script:

- `cron_comercial.php`

Comportamiento:

- Ejecuta `comercial_run_tick()` y responde JSON con `ok`, `ran_at`, `results`.

> No se ha verificado en esta iteración una línea cron activa equivalente para `cron_comercial.php`.

---

## 2) Webhook comercial

Endpoint:

- `comercial_webhook.php`

Flujo operativo:

1. Lee `php://input`.
2. Extrae campos de mensaje (from/to/text/message_id) en múltiples rutas de payload.
3. Registra entrada en `data/comercial_webhook_log.jsonl`.
4. Carga bootstrap y delega en `comercial_handle_webhook_http()`.
5. En error fatal, registra evento `request_shutdown_error`.

Recomendaciones operativas:

- Monitorizar crecimiento de `comercial_webhook_log.jsonl`.
- Validar permisos de escritura en `data/`.

---

## 3) Requisitos de sistema de archivos

- `data/` debe ser escribible (indicado también en `README.txt`).
- Ficheros clave de operación:
  - `data/php_errors.log`
  - `data/cron_comercial.log` (si se usa)
  - `data/comercial_webhook_log.jsonl`
  - `data/avisos*.json`

---

## 4) Checklist básico de mantenimiento

Diario:

- Revisar avisos activos en panel.
- Comprobar que cron de avisos está ejecutando.
- Verificar ausencia de errores repetidos en `php_errors.log`.

Semanal:

- Revisar tamaño de JSON/JSONL pesados (Publicista/Comercial).
- Validar consistencia visual de métricas en Dashboard/Informes.

---

## 5) Troubleshooting rápido

## Problema: pantalla en blanco / error fatal

1. Revisar `data/php_errors.log`.
2. Verificar permisos de `data/`.
3. Comprobar JSON malformado en fichero recientemente tocado.

## Problema: no se disparan avisos

1. Verificar cron de `cron_avisos.php`.
2. Revisar `avisos_config.php` (umbrales/flags).
3. Comprobar que se generan runs en `data/avisos_runs.json`.

## Problema: webhook comercial “no entra”

1. Verificar acceso HTTP al endpoint.
2. Revisar append en `data/comercial_webhook_log.jsonl`.
3. Revisar errores en `php_errors.log`.

## Problema: sesión no persiste

1. Validar `session.save_path` del servidor.
2. Confirmar fallback utilizable en `data/sessions`.

---

## 6) Nota sobre migración MySQL

Documentación reutilizable ya existente:

- `docs/mysql_migration_phase1.md`
- `docs/mysql_phase2_setup.md`

Uso operativo actual: el sistema mantiene compatibilidad `json | dual | mysql`, con default habitual en `json` salvo configuración explícita.
