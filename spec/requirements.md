# Requirements · Plan de fases rendimiento y migración

## Objetivo
Mejorar el rendimiento percibido del sistema sin romper funcionalidad y preparar migración completa a MySQL con riesgo controlado.

## Fases aprobadas
1. **Fase 1 — Rendimiento Inmediato (Online)**
   - Sacar compacción de bootstrap a cron/mantenimiento.
   - Quitar escritura en GET del panel de avisos.
   - Añadir snapshot/index de avisos activos para reducir full-scan.
2. **Fase 2 — Migración Segura (Dual-Run Online)**
   - Ajustes de schema/índices.
   - Backfill JSON -> MySQL idempotente.
   - Activación dual + validación de paridad.
3. **Fase 3 — Corte Controlado a MySQL (Ventana corta)**
   - Freeze de escrituras + delta final.
   - Cambio efectivo a backend MySQL.
   - Smoke tests y reapertura con rollback <10 minutos.

## Restricciones
- Cambios mínimos y seguros.
- Sin dependencias nuevas sin permiso.
- Mantener compatibilidad funcional durante Fase 1.

## PRF-IDENTIDAD-FOTO-2026 — Mejora de parecido y realismo en generación Publicista

### Objetivo
Generar candidatas **muy similares físicamente** a la original (sin clon exacto), con **foto real creíble**, **identidad/silueta preservadas** y **coherencia sujeto-entorno**, eliminando efectos artificiales (iluminaciones rosas, piel plastificada, look CGI/caricatura, fondos sintéticos).

### Alcance por fases
1. **F01_BASELINE_Y_METRICAS** — Medir estado actual, definir KPIs y umbrales de calidad.
2. **F02_REARQUITECTURA_PROMPT** — Rediseñar construcción de prompt (capas, prioridades, anti-contradicciones).
3. **F03_CONTROL_IDENTIDAD_Y_SILUETA** — Blindar parecido físico fuerte sin clon exacto.
4. **F04_COHERENCIA_1A1_Y_ENTORNO** — Extensión de escena natural, no sustitución artificial.
5. **F05_RERANKING_Y_SELECCION_FINAL** — Impedir que candidatas malas pasen a finales.
6. **F06_EXPERIMENTOS_AB_Y_HARDENING** — Validar científicamente y cerrar versión estable.

### Restricciones
- Cambios mínimos y seguros.
- Sin dependencias nuevas sin permiso.
- Compatibilidad con flujo Pollo existente.
- No modificar comportamiento de otros módulos (comercial, avisos, etc.).

## Bloque CX2 — Desbloqueo SDD bot comercial

### Objetivo
Habilitar un modelo operativo y trazable de **interés real** para el bot comercial, con reglas explícitas de transición de estado, score de interés y criterios de escalado a gestión humana, para mejorar priorización y respuesta comercial sin romper el flujo actual.

### Alcance por fases (CX2-F1..CX2-F8)
1. **CX2-F1 — Modelo base de interés real (spec unlock)**
   - Definir estados canónicos, score (0-100) y reglas mínimas de escalado.
   - Formalizar contratos de comportamiento sin cambios de runtime.
2. **CX2-F2 — Señales de entrada y normalización**
    - Definir catálogo de señales (mensaje, respuesta, silencio, rechazo, intención explícita).
    - Normalizar eventos para evaluación homogénea.
    - Incluir controles de seguridad contractual para evitar inyección por texto libre, spoofing básico y sobreexposición de PII en trazas.
3. **CX2-F3 — Motor de scoring inicial**
    - Aplicar ponderaciones base por señal y ventana temporal.
    - Establecer trazabilidad de cómo se calcula el score.
    - Definir controles contractuales anti-manipulación (replay, spam de señales y escalado indebido).
4. **CX2-F4 — Persistencia y auditoría de decisiones**
   - Registrar estado, score y motivo de transición.
   - Habilitar historial auditable por lead/conversación.
   - Definir retención mínima y controles de integridad/no repudio del historial.
5. **CX2-F5 — Reglas de escalado operativo**
   - Activar umbrales de escalado y anti-ruido.
   - Definir handoff a humano con contexto mínimo obligatorio.
6. **CX2-F6 — Integración en panel/operación comercial**
   - Exponer estado y score para priorización.
   - Permitir confirmación o corrección humana con trazabilidad.
7. **CX2-F7 — Calibración y guardrails**
   - Ajustar pesos/umbrales con datos reales.
   - Añadir controles para evitar sobre-escalado y falsos positivos.
8. **CX2-F8 — Cierre de despliegue y criterios de éxito**
   - Definir métricas de aceptación (precisión operativa y tiempos de respuesta).
   - Cerrar documentación y checklist de salida.

## COM-BALANCE — Balanceo equitativo de envíos entre líneas comerciales

### Objetivo
Corregir el desequilibrio en el reparto de envíos de publicidad entre líneas WhatsApp. Actualmente el algoritmo de selección de línea (`comercial_order_lines_for_process`) usa round-robin por proceso sin tracking diario de envíos por línea, lo que provoca que una línea acapare casi todo el tráfico mientras otras envían 1-2 mensajes al día. El nuevo algoritmo debe garantizar que todas las líneas seleccionadas reciban un volumen de envíos equitativo, respetando las restricciones de disponibilidad y power factor de cada línea.

### Alcance por fases
1. **COM-BALANCE-F0 — Spec & Design**
   - Formalizar requisitos, diseño del algoritmo min-count-first y contratos.
2. **COM-BALANCE-F1 — Data Layer**
   - Añadir `daily_sent_count` y `daily_sent_date` al estado de cada línea.
   - Implementar funciones de incremento, consulta y reset automático al cambiar de día.
3. **COM-BALANCE-F2 — Core Algorithm**
   - Reescribir `comercial_order_lines_for_process()` con selección min-count-first.
   - Adaptar `comercial_pick_line_for_process()` con la misma lógica.
   - Integrar incremento de contador en envíos exitosos.
4. **COM-BALANCE-F3 — Integration & Verification**
   - Cablear todo en `comercial_run_tick()`, validar edge cases, simular reparto.
5. **COM-BALANCE-F4 — UI & Monitoring (opcional)**
   - Mostrar contadores diarios en panel comercial para transparencia operativa.

### Restricciones
- Cambios mínimos y seguros.
- Sin dependencias nuevas.
- Compatible con el sistema de power factor y autoregulación existente.
- No modificar el comportamiento de otros módulos (publicista, avisos, etc.).
- El balanceo debe operar exclusivamente sobre líneas disponibles (`comercial_line_is_available`).

## COM-LINEAS-UI — Mejora de la sección Comercial > Líneas

### Objetivo
Mejorar la UI de la sección `Comercial > Líneas` del CRM eliminando la duplicación de listados (dos tablas iterando el mismo array `$lines`), liberando espacio horizontal ocupado por el formulario CRUD siempre visible, y convirtiendo el formulario en un modal emergente. El resultado debe ser una única tabla unificada a ancho completo con todas las columnas relevantes y un modal para crear/editar líneas.

### Fases aprobadas
1. **COM-LINEAS-UI-F0 — Especificación (Spec)**
   - Formalizar requisitos, diseño, contratos y tracking de tareas.
2. **COM-LINEAS-UI-F1 — Implementación**
   - Reestructurar PHP en `app/comercial.php`, refactorizar JS en `assets/app.js`, añadir estilos CSS en `assets/style.css` y `assets/theme.css`.
3. **COM-LINEAS-UI-F2 — Verificación**
   - Lint PHP, validar selectores JS, bump de versiones cache en `index.php`, actualizar changelog.

### Restricciones
- Cambios mínimos y seguros.
- Sin dependencias nuevas (sin librerías JS/CSS externas adicionales).
- Mantener el estilo visual actual (tema oscuro).
- No modificar el comportamiento de otros módulos (publicista, avisos, procesos, etc.).
- Las acciones POST existentes (`save_telefono`, `delete_telefono`, `save_comercial_line_state`, `comercial_check_lines_health`) deben seguir funcionando exactamente igual (contrato de no regresión).

## BOT-CASA-MULTIUSER — Ampliación bot-casa Multi-Usuario v2.0

### Objetivo
Transformar bot-casa actual (single-tenant) en un servicio multi-usuario donde cada cliente tiene su panel aislado con sus datos, comparte el motor del bot, y el admin gestiona clientes.

### Alcance por fases
1. **FASE 1 — Fundación Multi-Tenant + Auth**: users.json, UserManager, login/logout, routing webhook por línea, aislamiento directorios, forward-compat, admin middleware.
2. **FASE 2 — Panel Admin**: auth gate en panel.php, sección Usuarios, suplantar usuario, externalizar CSS/JS.
3. **FASE 3 — Panel Cliente + Secciones Principales**: client.php, Dashboard, Mi Bot, Personalidad parametrizada.
4. **FASE 4 — Líneas + Chicas + Estados**: gestión líneas WAHA vía SSH, QR, health check; CRUD chicas; publicador estados.
5. **FASE 5 — Clientes + Mensajes + Ajustes + Stats + Logs**: leads con arrived marking, memoria con search, ajustes agrupados, estadísticas, logs sanitizados.
6. **FASE 6 — Onboarding + Cron Jobs + Pulido**: wizard, crons multi-usuario, playbook por usuario.

### Restricciones
- Cambios mínimos y seguros. Sin dependencias nuevas.
- No modificar lógica del pipeline (Bot.php, Pipeline/*).
- Mantener compatibilidad con sistema actual (forward-compat: sin data/users/ → usar data/).
- Auth: usuario/contraseña con bcrypt.
- WAHA: gestión vía SSH automático. Imágenes: mantener compartir.site.
- Aprendizaje: por usuario.
