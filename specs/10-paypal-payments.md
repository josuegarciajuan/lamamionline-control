# 10 — Sistema de cobros con PayPal (credenciales, estado y plan)

## Estado actual

- **Credenciales guardadas**: `bot-casa/config.local.json` → sección `paypal` (gitignored, fuera de git).
- **Módulo de pago existente**: `bot-casa/src/Payment/PayPalClient.php` — wrapper de la Orders API v2 con cURL puro (sin SDK).
- **Test existente**: `bot-casa/tests/Unit/Payment/PayPalClientTest.php`.
- **Sistema de cobros end-to-end**: PENDIENTE de implementar en sesiones futuras (ver "Próximos pasos").

## Dónde viven las credenciales

| Qué | Dónde |
|---|---|
| Credenciales reales (sandbox + live + cuenta sandbox) | `bot-casa/config.local.json` → `paypal` |
| Template con placeholders (commiteado) | `bot-casa/config.dist.json` → `paypal` |
| Cliente de la API de PayPal | `bot-casa/src/Payment/PayPalClient.php` |

`config.local.json` **no se commitea** (`bot-casa/.gitignore` líneas 15-18: `config.json`, `config.local.json`).
Nunca mover credenciales a archivos trackeados.

## Estructura de la sección `paypal`

```json
{
  "paypal": {
    "client_id": "...",             // par ACTIVO que usa PayPalClient
    "secret": "...",
    "mode": "sandbox",              // "sandbox" | "live"
    "webhook_id": "",               // pendiente de configurar
    "sandbox": { "client_id": "...", "secret": "..." },
    "live": { "client_id": "...", "secret": "..." },
    "live_account_email": "tracatrack@gmail.com",
    "sandbox_account": { "email": "...", "password": "..." }
  }
}
```

- El par **activo** (`client_id` + `secret` + `mode`) es el único que lee `PayPalClient`; determina la base URL (`api-m.sandbox.paypal.com` vs `api-m.paypal.com`).
- Los sub-objetos `sandbox` y `live` son los pares de **referencia** para cambiar de entorno sin ir al dashboard.
- `sandbox_account` es la cuenta de comprador de prueba para simular pagos en `sandbox.paypal.com`.

## Cómo cambiar de sandbox a live

1. Copiar `paypal.live.client_id` → `paypal.client_id` y `paypal.live.secret` → `paypal.secret`.
2. Poner `paypal.mode = "live"`.
3. Configurar `webhook_id` (ver abajo).
4. Probar siempre primero en sandbox; solo mover a live cuando el flujo esté verificado.

## Módulo existente (`PayPalClient`)

| Método | Función |
|---|---|
| `createOrder(amount, description, returnUrl, cancelUrl, customId)` | Crea orden con `intent=CAPTURE` (EUR) |
| `captureOrder(orderId)` | Captura el pago tras aprobación del comprador |
| `verifyWebhook(headers, body, webhookId)` | Verifica la firma de un webhook con PayPal |
| `getOrder(orderId)` | Consulta la orden (resuelve `custom_id` cuando el evento no lo trae) |

Instanciación:

```php
$paypal = new WasapBot\Payment\PayPalClient($config->get('paypal'));
```

`custom_id` se usa para atribuir el pago en el servidor (p. ej. `"user:<id>"`) sin confiar en datos del cliente.

## Próximos pasos (sistema de cobros)

1. **Webhook**: registrar una URL pública (p. ej. `https://<dominio>/paypal/webhook.php`) en
   `developer.paypal.com` → Apps & Credentials → app `lamamionline` → Webhooks, suscribirse a
   `PAYMENT.CAPTURE.COMPLETED` y copiar el **Webhook ID** a `paypal.webhook_id`.
2. **Endpoints**:
   - Crear orden (POST) → `createOrder` con `custom_id` del comprador.
   - Retorno/cancelación del comprador tras aprobación.
   - Webhook → `verifyWebhook` + idempotencia + registro del cobro en `data/`.
3. **UI de pago**: botón PayPal (smart buttons) o enlace de aprobación, integrado en el flujo del bot o del CRM.
4. **Reglas de negocio**: qué se cobra, cuánto, qué acceso/beneficio se concede tras el pago confirmado.
5. Tests del flujo completo en sandbox (crear → aprobar → capturar → webhook).

## Reglas de seguridad

- Nunca commitear `config.local.json` ni valores reales (Client Secret, password de la cuenta sandbox).
- El hook pre-commit bloquea patrones `password`/`token`/`secret`: no pegar secretos en specs ni código.
- `client_id`/`secret` se usan solo server-side. Nunca exponerlos en JS del cliente.
- Nada se da por pagado sin verificación server-side: `verifyWebhook` para webhooks y/o `getOrder` para confirmar el estado `COMPLETED`.
