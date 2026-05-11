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
