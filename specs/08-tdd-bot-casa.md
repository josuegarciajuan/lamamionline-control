# 08 · TDD — Mapa de tests de bot-casa

Define la estructura de tests PHPUnit para `bot-casa/`, la correspondencia
con cada punto del pipeline (spec 06) y del prompt (spec 07), las políticas
de ejecución, y el harness de aislamiento.

---

## 1. Estructura de directorios

```
bot-casa/tests/
├── bootstrap.php              # Autoloader (PSR-4 manual o Composer)
├── Support/                   # Harness de aislamiento y fábricas
│   ├── TmpEnv.php             # Entorno temporal (cero contacto con data/)
│   ├── PayloadFactory.php     # Fábrica de payloads WAHA sintéticos
│   └── Fakes.php              # Dobles de prueba (mocks/stubs)
├── Unit/
│   ├── Pipeline/              # Tests de gates y procesadores
│   │   ├── BotModeGateTest.php
│   │   ├── RoutingGateTest.php
│   │   ├── DedupGateTest.php
│   │   ├── CoalescerTest.php
│   │   ├── MessageExtractorTest.php
│   │   ├── PauseGateTest.php
│   │   ├── InflightGateTest.php
│   │   ├── ContextAssemblerTest.php
│   │   ├── IntentRouterTest.php
│   │   ├── ConversationStateMachineTest.php
│   │   ├── ToneBuilderTest.php
│   │   ├── ResponseNormalizerTest.php
│   │   ├── CatalogFormatterTest.php
│   │   ├── DedupeReplyTest.php
│   │   └── ImageSplitterTest.php
│   ├── Services/              # Tests de servicios externos
│   │   ├── WahaApiTest.php
│   │   └── BlacklistServiceTest.php
│   ├── SideEffects/           # Tests de side-effects
│   │   ├── LeadDetectorTest.php
│   │   ├── LeadLoggerTest.php
│   │   ├── AutoOffTest.php
│   │   ├── ReminderWriterTest.php
│   │   └── ResponseScorerTest.php
│   └── Prompt/                # Tests del prompt y sus secciones
│       ├── BuildSystemPromptTest.php
│       ├── PromptSectionsTest.php
│       ├── BuildChatHistoryTest.php
│       └── SemanticFieldsTest.php
├── Integration/               # Tests de integración (pipeline parcial)
│   ├── WebhookToPipelineTest.php
│   ├── FastPathTest.php
│   └── SideEffectsChainTest.php
├── Contract/                  # Tests de contrato JSON
│   ├── JsonSchemaTest.php
│   └── ResponseNormalizerContractTest.php
└── Llm/                       # Tests con LLM real (solo manual)
    └── LiveLlmTest.php
```

---

## 2. Correspondencia con el pipeline (spec 06)

| Paso del pipeline | Test(s) correspondiente(s) |
|---|---|
| 1.1 Tenant routing | `Unit/Pipeline/RoutingGateTest.php` |
| 1.2 Webhook dedup 15s | `Integration/WebhookToPipelineTest.php` |
| 1.3 threadId | `Unit/Pipeline/ContextAssemblerTest.php` |
| 1.4 Persistencia inmediata | `Integration/WebhookToPipelineTest.php` |
| 1.5 Cortes tempranos | `Unit/Pipeline/BotModeGateTest.php`, `Unit/Pipeline/PauseGateTest.php` |
| 2.1 BotModeGate | `Unit/Pipeline/BotModeGateTest.php` |
| 2.2 RoutingGate | `Unit/Pipeline/RoutingGateTest.php` |
| 2.3 DedupGate | `Unit/Pipeline/DedupGateTest.php` |
| 2.4 Coalescer | `Unit/Pipeline/CoalescerTest.php` |
| 2.5 MessageExtractor | `Unit/Pipeline/MessageExtractorTest.php` |
| 2.6 PauseGate | `Unit/Pipeline/PauseGateTest.php` |
| 2.7 InflightGate | `Unit/Pipeline/InflightGateTest.php` |
| 3.1 ContextAssembler | `Unit/Pipeline/ContextAssemblerTest.php` |
| 3.2 ResponseScorer | `Unit/SideEffects/ResponseScorerTest.php` |
| 3.3 IntentRouter | `Unit/Pipeline/IntentRouterTest.php` |
| 3.4 ConversationStateMachine | `Unit/Pipeline/ConversationStateMachineTest.php` |
| 3.5 ToneBuilder | `Unit/Pipeline/ToneBuilderTest.php` |
| 3.6 ResponseNormalizer | `Unit/Pipeline/ResponseNormalizerTest.php` |
| 3.7 CatalogFormatter | `Unit/Pipeline/CatalogFormatterTest.php` |
| 3.8 DedupeReply | `Unit/Pipeline/DedupeReplyTest.php` |
| 3.9 ImageSplitter | `Unit/Pipeline/ImageSplitterTest.php` |
| 4.1 IntentRouter fast-path | `Integration/FastPathTest.php` |
| 4.2 Audio auto-reply | `Integration/FastPathTest.php` |
| 4.3 Primer contacto | `Integration/FastPathTest.php` |
| 5.1 buildSystemPrompt | `Unit/Prompt/BuildSystemPromptTest.php` |
| 5.2 buildChatHistory | `Unit/Prompt/BuildChatHistoryTest.php` |
| 5.3 Selección de proveedor | `Unit/Pipeline/RoutingGateTest.php` |
| 6.1 applyLlmSemanticFields | `Unit/Prompt/SemanticFieldsTest.php` |
| 6.2 Guards post-AI | `Unit/Pipeline/ContextAssemblerTest.php`, `Integration/SideEffectsChainTest.php` |
| 6.3 Drain pending | `Unit/Pipeline/InflightGateTest.php` |
| 6.4 sendHumanized | `Unit/Services/WahaApiTest.php` |
| 6.5 Persistencia final | `Integration/WebhookToPipelineTest.php` |
| 6.6 Side-effects | `Unit/SideEffects/LeadDetectorTest.php`, `Unit/SideEffects/AutoOffTest.php`, `Unit/SideEffects/ReminderWriterTest.php`, `Unit/SideEffects/LeadLoggerTest.php` |
| 7 Cleanup | `Unit/Pipeline/InflightGateTest.php` |

---

## 3. Correspondencia con el prompt (spec 07)

| Sección del prompt | Test(s) correspondiente(s) |
|---|---|
| `rol` | `Unit/Prompt/PromptSectionsTest.php` |
| `estilo` | `Unit/Prompt/PromptSectionsTest.php` |
| `tarifas` | `Unit/Prompt/PromptSectionsTest.php` |
| `ofertas` | `Unit/Prompt/PromptSectionsTest.php` |
| `servicios` | `Unit/Prompt/PromptSectionsTest.php` |
| `ubicacion` | `Unit/Prompt/PromptSectionsTest.php` |
| `instrucciones_fotos` | `Unit/Prompt/PromptSectionsTest.php` |
| `identidad_chicas` | `Unit/Prompt/PromptSectionsTest.php` |
| `seguridad` | `Unit/Prompt/PromptSectionsTest.php` |
| `ejemplos` | `Unit/Prompt/PromptSectionsTest.php` |
| `formato_respuesta` | `Unit/Prompt/PromptSectionsTest.php`, `Contract/JsonSchemaTest.php` |
| Campos semánticos extra | `Unit/Prompt/SemanticFieldsTest.php` |
| Contrato JSON completo | `Contract/ResponseNormalizerContractTest.php`, `Contract/JsonSchemaTest.php` |
| Trim de girls_config | `Unit/Prompt/BuildSystemPromptTest.php` |
| Invariantes del prompt | `Unit/Prompt/BuildSystemPromptTest.php` |

---

## 4. Política de gates

### `composer test` — gate verde obligatorio
```bash
cd bot-casa && composer test
```
Ejecuta: `phpunit tests/` con exclusión del grupo `@llm` (configurado en
`phpunit.xml`).

**Cubre:** `tests/Unit/` + `tests/Integration/` + `tests/Contract/`.

**Regla:** todo cambio que afecte al algoritmo de respuesta DEBE pasar
`composer test` en verde.

### `composer test:llm` — manual opcional
```bash
cd bot-casa && composer test:llm
```
Ejecuta: `phpunit tests/Llm/ --group llm`.

Requiere API keys reales y conexión a internet. NO se ejecuta en CI.

### `composer phpstan` — análisis estático
```bash
cd bot-casa && composer phpstan
```
Ejecuta: `phpstan analyse src/ --level=8`.

**Regla:** debe pasar en verde para todo cambio.

---

## 5. Harness de aislamiento — `TmpEnv`

- **Archivo:** `tests/Support/TmpEnv.php`
- **Principio:** cero contacto con `data/` de producción.
- **Funcionamiento:**
  1. Crea directorio temporal en `sys_get_temp_dir() / wasapbot_test_{uniqid}`.
  2. Copia `config.dist.json` como base.
  3. Sobrescribe TODOS los paths de archivos (`files.*`, `bot.mode_file`,
     `routing.lines`, `waha.*`) para apuntar al directorio temporal.
  4. Crea subdirectorios necesarios (`data/`, `data/locks/`,
     `data/locks/event_dedup/`, `data/locks/coalesce/`,
     `data/locks/inflight/`).
  5. Expone `$env->config` (instancia de `Config` aislada).
  6. Métodos helper: `stopBot()`, `startBot()`, `pauseThread(threadId)`,
     `readSessionMemory()`, `writeSessionRecord(record)`.
  7. Cleanup automático en destructor.

**Uso en tests:**
```php
final class BotModeGateTest extends TestCase
{
    public function testBotStoppedHaltsPipeline(): void
    {
        $env = new TmpEnv();
        $env->stopBot();
        $gate = new BotModeGate($env->config);
        $this->assertNull($gate->process(['body' => []]));
    }
}
```

---

## 6. `PayloadFactory` — fábrica de payloads WAHA

- **Archivo:** `tests/Support/PayloadFactory.php`
- **Métodos:**
  - `textMessage(string $text, string $from = '34666111222', string $to = '34999000111'): array`
  - `audioMessage(string $from = '34666111222'): array`
  - `imageMessage(string $from = '34666111222'): array`
  - `withMe(string $payload, string $meId): array` — añade `me.id` al payload.
  - `webhookEnvelope(array $payload): array` — envuelve en `{event: "message",
    payload: ...}`.

- **Principio:** cada test construye su payload de forma explícita y
  declarativa. Sin dependencia de datos reales.

---

## 7. `Fakes.php` — dobles de prueba

- **Archivo:** `tests/Support/Fakes.php`
- **Clases:**
  - `FakeLogger` — implementa `LoggerInterface`, captura mensajes en array.
  - `FakeHttpClient` — implementa `HttpClientInterface`, responde con datos
    preconfigurados.
  - `FakeWahaApi` — implementa `WahaApiInterface`, captura llamadas.
  - `FakeOpenAiClient` — implementa `OpenAiClientInterface`, devuelve
    respuestas predefinidas.
  - `FakeGirlsService` — implementa `GirlsServiceInterface`, devuelve
    catálogo sintético.
  - `FakeSessionMemory` — implementa `SessionMemoryInterface`, memoria en
    array.
  - `FakeMemory` — implementa `MemoryInterface`, valores preconfigurados.
  - `FakeTelegramService` — implementa `TelegramServiceInterface`,
    no-op capturable.

---

## 8. Convenciones de tests

### Nomenclatura
- **Clase:** `{Componente}Test.php`
- **Método:** `test{ComportamientoEsperado}()` o
  `test{Componente}_{Condicion}_{Resultado}()`.

### Estructura AAA (Arrange-Act-Assert)
```php
public function testBotModeGateReturnsNullWhenStopped(): void
{
    // Arrange
    $env = new TmpEnv();
    $env->stopBot();
    $gate = new BotModeGate($env->config);

    // Act
    $result = $gate->process(['body' => []]);

    // Assert
    $this->assertNull($result);
}
```

### Grupos de PHPUnit
- `@group llm` — tests que requieren API keys reales.
- `@group slow` — tests que usan `sleep()` o `usleep()` (Coalescer,
  sendHumanized).
- Sin grupo → ejecución normal en `composer test`.

---

## 9. Lista mínima de tests requeridos (gate verde)

### Unit/Pipeline (15 archivos)
Cada gate y procesador DEBE tener al menos:
- 1 test de "happy path" (procesa correctamente).
- 1 test de "abort" (retorna null cuando debe).
- 1 test de "edge case" (input vacío, nulo, malformado).

### Unit/Services (2 archivos)
- `WahaApiTest`: verifica que `sendHumanized` respeta delays mínimos y
  que `sendQuick` no tiene delays.
- `BlacklistServiceTest`: verifica match exacto, last9, y wildcard.

### Unit/SideEffects (5 archivos)
- `LeadDetectorTest`: verifica detección con `lead_detected=true` y
  `lead_confidence >= 0.95`.
- `LeadLoggerTest`: verifica escritura en archivo temporal.
- `AutoOffTest`: verifica que escribe "stop" en `.bot_mode`.
- `ReminderWriterTest`: verifica escritura de recordatorio con ETA > 0.
- `ResponseScorerTest`: verifica que no lanza excepciones.

### Unit/Prompt (4 archivos)
- `BuildSystemPromptTest`: verifica que el prompt contiene secciones
  obligatorias, no está vacío, tiene JSON esperado.
- `PromptSectionsTest`: verifica cada sección individual (rol, tarifas, etc.).
- `BuildChatHistoryTest`: verifica ventana temporal, compresión, filtro
  "no entiendo".
- `SemanticFieldsTest`: verifica que `applyLlmSemanticFields` asigna
  correctamente los campos del LLM al contexto.

### Integration (3 archivos)
- `WebhookToPipelineTest`: flujo completo con bot mockeado.
- `FastPathTest`: verifica que audio, primer contacto, y conversation_ended
  no llaman al LLM.
- `SideEffectsChainTest`: verifica que lead detection + auto-off +
  logging se ejecutan en orden.

### Contract (2 archivos)
- `JsonSchemaTest`: valida que el JSON de respuesta cumple el esquema
  (campos obligatorios, tipos, valores válidos).
- `ResponseNormalizerContractTest`: valida que `ResponseNormalizer` produce
  siempre los 6 campos base con tipos correctos.

---

## 10. Integración con CI/CD

```yaml
# .github/workflows/test.yml (ejemplo)
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: php-actions/composer@v6
        with:
          working_dir: bot-casa
      - name: PHPStan
        run: cd bot-casa && composer phpstan
      - name: Unit + Integration + Contract
        run: cd bot-casa && composer test
```

- `composer test:llm` NUNCA se ejecuta en CI.
- `composer phpstan` se ejecuta antes de `composer test`.
