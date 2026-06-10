# Fix: Detección temprana de leads en bot casa

## Problema
El bot lanza notificación de lead demasiado pronto — cuando el cliente comparte ubicación pero sin intención clara de venir. 5 factores encadenados:

1. **Prompt AI: señales medias laxas** — `selected_girl + maps_sent = lead` con 0.65-0.85
2. **info_pack_ready prematuro** — se activa antes de enviar maps, forzando modo "cierre"
3. **ETA request automático** — al enviar maps se inyecta "cuanto tardas?", provocando falsas ETA
4. **Umbral 0.5 demasiado bajo** — cualquier confianza >50% activa lead
5. **maps_sent suma +10 a confianza determinista** — ruido sin correlación real

## Cambios

### 1. LeadDetector.php — Subir umbral 0.5 → 0.7
```diff
- private const float HIGH_CONFIDENCE_THRESHOLD = 0.5;
+ private const float HIGH_CONFIDENCE_THRESHOLD = 0.7;
```

### 2. config.dist.json — Endurecer señales medias en prompt AI
Sección `prompt.sections.formato_respuesta`, reemplazar el texto de SEÑALES MEDIAS:
- Requerir que el cliente diga EXPLÍCITAMENTE que viene ("voy", "estoy saliendo")
- No vale solo preguntar ubicación o responder ETA sin lenguaje de intención

### 3. ContextAssembler.php — Limitar info_pack_ready
```diff
- if ($mapsBeingSentNow) {
+ if ($mapsBeingSentNow && ($ctx['interes_fuerte'] || $ctx['eta_from_user_flag'])) {
```
Solo activar `info_pack_ready` si hay señal de intención real.

### 4. Bot.php — Quitar ETA request automático en primer maps
```diff
- if (!$hasEta && !empty($messages[0]['text'])) {
+ if (!$hasEta && !empty($messages[0]['text']) && ($interesFuerte || $chooseLoop >= 2)) {
```

### 5. TelegramService.php — Quitar maps_sent de confianza determinista
```diff
- if (!empty($data['maps_sent'])) {
-     $score += 10;
- }
```
