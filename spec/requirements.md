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
