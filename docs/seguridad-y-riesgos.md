# Seguridad y riesgos (estado actual)

Documento de riesgos observables en el código actual, con priorización y medidas recomendadas.

## Prioridades

- **P0**: crítico (alto impacto + fácil explotación)
- **P1**: alto
- **P2**: medio
- **P3**: bajo

## Riesgos detectados

## P0 · Credenciales y autenticación

1. **Contraseña de usuario en texto plano** (`data/users.json`, login por comparación directa).
2. **Credenciales DB embebidas en código** (`app/db.php`).

Medidas recomendadas:

- Migrar contraseñas de usuarios a hash (`password_hash` / `password_verify`) con plan de transición.
- Mover credenciales DB a variables de entorno y rotarlas.

## P1 · Control de acceso

1. **Auto-login por whitelist de IP** (`auth_auto_login_from_whitelist`) puede abrir acceso si la red/IP se expone o se enruta incorrectamente.

Medidas recomendadas:

- Mantener whitelist mínima y revisada.
- Añadir opción de desactivar auto-login en producción.
- Registrar auditoría explícita de accesos auto-login.

## P1 · Integridad de datos

1. En runtime JSON, un fichero malformado puede degradar lecturas y provocar estados vacíos/lógicos inconsistentes.
2. Escritura por fichero completo en JSON grandes eleva riesgo ante concurrencia/cortes.

Medidas recomendadas:

- Validaciones de JSON y backups antes de escrituras sensibles.
- Alertar cuando `json_decode` falle (telemetría explícita).
- Priorizar módulos pesados para backend transaccional (`dual/mysql`).

## P2 · Exposición operativa por logs

1. `comercial_webhook_log.jsonl` guarda fragmentos de payload/metadata.
2. `php_errors.log` puede contener rutas/mensajes sensibles.

Medidas recomendadas:

- Política de retención y rotación de logs.
- Revisar redacción de campos sensibles en logs.

## P2 · Superficie CSRF y endurecimiento de sesión

1. No se ha identificado en esta revisión un mecanismo global anti-CSRF para formularios POST.
2. Endurecimiento de cookies/sesión depende de configuración de servidor.

Medidas recomendadas:

- Introducir token CSRF por formulario en acciones sensibles.
- Revisar `secure`, `httponly`, `samesite` en cookies de sesión.

## P3 · Mantenibilidad de seguridad

1. Dispatcher de acciones muy extenso dificulta auditoría rápida.
2. Casos duplicados en switch de acciones pueden introducir errores humanos.

Medidas recomendadas:

- Refactor progresivo por dominio (sin alterar comportamiento).
- Matriz de permisos/acciones documentada.

---

## Plan recomendado por fases (sin tocar negocio funcional)

1. **Fase A (rápida)**: sacar secretos de código + política de rotación.
2. **Fase B**: hash de usuarios + transición de login.
3. **Fase C**: CSRF y endurecimiento de sesión.
4. **Fase D**: controles de integridad/alertas en storage JSON y plan de avance a `dual/mysql` en módulos críticos.

---

## Supuestos no cerrados en esta revisión

- No se auditó configuración de servidor web/reverse proxy.
- No se ejecutó escaneo dinámico (DAST) ni pentest.
- No se validó cifrado en tránsito fuera del código de aplicación.
