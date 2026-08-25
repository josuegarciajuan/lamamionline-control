# Ingesta de scrapers

## Contrato

El cliente hace `POST` con `Content-Type: application/json` al endpoint CRM.
Los headers obligatorios son:

- `X-Scraper-Timestamp`: Unix timestamp.
- `X-Scraper-Nonce`: valor único por intento.
- `X-Scraper-Signature`: `HMAC-SHA256(timestamp + "\n" + nonce + "\n" + body)`.

El JSON contiene un `event_id` estable, un `type` (`individual`, `house` o
`collaborator`) y `items`. Cada item debe contener un móvil español válido:
`6XXXXXXXX`, `7XXXXXXXX` o su forma `+34`.

`individual` actualiza directamente `telefonosbd.f_clientes`. `house` y
`collaborator` se escriben en las colas comerciales existentes. Un evento
repetido devuelve una respuesta idempotente; un nonce repetido se rechaza.

## Configuración local del endpoint CRM

No se deben añadir secretos al repositorio. En el servidor CRM, crear solo
localmente `data/scraper_ingest_config.php`:

```php
<?php
return array(
    'secret' => 'PONER_SECRETO_LOCAL',
    'max_skew' => 300,
);
```

También puede usarse otro archivo mediante `SCRAPER_INGEST_CONFIG`. Las
variables `SCRAPER_INGEST_HMAC_SECRET` y `SCRAPER_INGEST_MAX_SKEW`, si están
disponibles, tienen prioridad sobre el archivo. El endpoint busca por defecto
`data/scraper_ingest_config.php`, que está dentro de `data/` ignorado por Git.

## Configuración local del scraper

El cliente usa variables de entorno o, por defecto, `ingest_config.php` junto
a `ingest_client.php`. Crear ese archivo solo en la máquina que ejecuta el
scraper:

```php
<?php
return array(
    'endpoint' => 'https://DOMINIO_LOCAL/api/scraper_ingest.php',
    'secret' => 'PONER_EL_MISMO_SECRETO_LOCAL',
);
```

Como alternativa, indicar `SCRAPER_INGEST_CONFIG` y/o definir
`SCRAPER_INGEST_ENDPOINT` y `SCRAPER_INGEST_HMAC_SECRET`. El archivo
`ingest_config.php` está excluido explícitamente de Git.

## Fallos y recuperación

Cada envío se reintenta con un nonce nuevo. Si no se confirma, el payload se
guarda en `data/scraper_ingest_spool/`. Los tres scrapers drenan ese spool al
inicio de cada ejecución. Los estados de `publicistasycasas` solo avanzan
cuando el endpoint confirma el lote.

Antes de activar producción, comprobar configuración con valores reales solo
en archivos locales y ejecutar los tests sin apuntar al endpoint productivo.
