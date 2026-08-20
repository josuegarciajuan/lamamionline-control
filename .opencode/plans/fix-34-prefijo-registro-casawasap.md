# Fix: quitar prefijo "34" automático al user en registro de casawasap.com

## Problema
Al registrarse en `casawasap.com` con un teléfono sin prefijo, el usuario de
login (username en bot-casa) sale con `34` delante. Debe ser el número tal cual.

## Causa raíz
`/var/www/html/wasapbot/landing/index.php` (vhost casawasap.com, fuera del repo,
no está en git). La función `normalizarTelefono()` añade `34` cuando el número
empieza por 6/7, y ese valor se usa como username (`createUser`) y como chatId
del wasap de bienvenida.

## Cambios (3)

### 1. `normalizarTelefono()` → solo dígitos, sin `34`
```php
/**
 * Normaliza un número de teléfono: quita todo lo que no sea dígito.
 * El usuario queda tal cual lo introdujo (sin añadir prefijo 34).
 */
function normalizarTelefono(string $raw): string {
    return preg_replace('/[^0-9]/', '', $raw);
}
```

### 2. Nuevo helper `telefonoParaWhatsApp()` (mantiene la lógica del 34)
```php
/**
 * Devuelve el número en formato internacional para WAHA (añade prefijo 34
 * si es un móvil español de 9 dígitos sin prefijo). Solo para el chatId.
 */
function telefonoParaWhatsApp(string $raw): string {
    $s = preg_replace('/[^0-9]/', '', $raw);
    if (strlen($s) >= 9 && preg_match('/^[67]/', $s)) {
        $s = '34' . $s;
    }
    return $s;
}
```

### 3. En `enviarWhatsAppRegistro()` usar el formato WAHA para el chatId
```php
    $chatId = telefonoParaWhatsApp($telefono) . '@c.us';
```
(antes era `$chatId = $telefono . '@c.us';`)

## Resultado
- Username = dígitos introducidos tal cual (ej. `612345678`).
- El wasap de bienvenida sigue llegando (chatId `34612345678@c.us`).
- El texto del wasap muestra el usuario correcto (sin 34).

## Notas
- `alta.php` / `alta_bck.php` son copias antiguas; el formulario vivo postea a
  `index.php`. No se tocan.
- Usuarios ya existentes (`346...`) conservan su username; solo los registros
  nuevos quedan sin `34`.
