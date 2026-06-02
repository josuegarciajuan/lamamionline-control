<?php

function lamami_normalize_clienta_lead_name($name) {
    $name = trim((string)$name);
    $name = preg_replace('/\s+/', '_', $name);
    return $name;
}

function lamami_recursive_copy($src, $dst) {
    if (!is_dir($src)) return false;
    if (!is_dir($dst) && !mkdir($dst, 0775, true)) return false;

    $items = scandir($src);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $from = rtrim($src, '/') . '/' . $item;
        $to   = rtrim($dst, '/') . '/' . $item;

        if (is_dir($from)) {
            if (!lamami_recursive_copy($from, $to)) return false;
        } else {
            if (!@copy($from, $to)) return false;
        }
    }

    return true;
}

function lamami_prepare_girls_panel($botCode, $clientaLeadCode, $clientaName, $servicios) {
    $srcDir = '/var/www/html/wasapbot/landing/girlsconf_srv1';
    $dstDir = '/var/www/html/wasapbot/landing/girlsconf_' . $botCode;

    if (is_dir($dstDir)) {
        return array(true, $dstDir . '/data/girls.json');
    }

    $cmd = 'cp -R ' . escapeshellarg($srcDir) . ' ' . escapeshellarg($dstDir) . ' 2>&1';
    @exec($cmd, $out, $code);

    if ($code !== 0 && !is_dir($dstDir)) {
        if (!lamami_recursive_copy($srcDir, $dstDir)) {
            return array(false, 'No se pudo crear el panel girlsconf para el bot.');
        }
    }

    $dataDir = $dstDir . '/data';
    if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true)) {
        return array(false, 'No se pudo crear la carpeta data del panel.');
    }

    $girlsJsonPath = $dataDir . '/girls.json';
    $girlsJson = array(
        'girls' => array(
            array(
                'id' => $clientaLeadCode,
                'nombre' => $clientaName,
                'descripcion_corta' => $servicios,
                'fotos' => array(),
                'activa' => true
            )
        )
    );

    $ok = @file_put_contents(
        $girlsJsonPath,
        json_encode($girlsJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    if ($ok === false) {
        return array(false, 'No se pudo escribir el girls.json del panel.');
    }

    return array(true, $girlsJsonPath);
}

function lamami_bot_vars($bot, $clienta) {
    $nombreBot = trim((string)($bot['nombre_bot'] ?? ''));
    $nombreClienta = trim((string)($clienta['nombre'] ?? ''));

    return array(
  '[LAMAMI_TARIFAS]'             => str_replace("\n", '\\n', (string)($clienta['tarifas'] ?? '')),
        '[LAMAMI_MAPS]'                => (string)($clienta['ubicacion_maps'] ?? ''),
        '[LAMAMI_ZONA]'                => (string)($clienta['zona'] ?? ''),
        '[LAMAMI_TFONO_BOT]'           => (string)($bot['telefono_bot'] ?? ''),
        '[LAMAMI_PORT_BOT]'            => (string)($bot['waha_port'] ?? ''),
        '[LAMAMI_NOMBRE_BOT]'          => $nombreBot,
        '[LAMAMI_NOMBRE_CLIENTA]'      => $nombreClienta,
        '[LAMAMI_SERVICIOS_CLIENTA]'   => (string)($clienta['servicios'] ?? ''),
        '[LAMAMI_NOMBRE_CLIENTA_LEAD]' => lamami_normalize_clienta_lead_name($nombreClienta),
        '[LAMAMI_SERVER_IP]'           => (string)($bot['server_ip'] ?? '100.113.76.93'),
        '[LAMAMI_GIRLS_PANEL_BASE]'    => 'https://casawasap.com/girlsconf_' . $nombreBot,
        '[LAMAMI_BOT_MODE]'            => (string)($bot['bot_mode'] ?? 'multiple'),
        '[LAMAMI_SESSION_MEMORY_FILE]'      => '/data/session_memory_' . $nombreBot . '.ndjson',
        '[LAMAMI_SESSION_MEMORY_FILE_TMP]'  => '/data/session_memory_' . $nombreBot . '.ndjson.tmp',
        '[LAMAMI_SESSION_MEMORY_LOCK]'      => '/data/.session_memory_' . $nombreBot . '.lock',
    );
}

function lamami_apply_vars($template, $vars) {
    return strtr($template, $vars);
}

/**
 * PEGA AQUÍ EXACTAMENTE el Texto1 que me has pasado, sin tocar nada.
 */
function lamami_template_texto1() {
    return <<<'TPL'
{
  "name": "wasapBot [LAMAMI_NOMBRE_BOT]",
  "nodes": [
    {
      "parameters": {
        "amount": "={{ (function(){\n  var ms = NaN;\n  try {\n    ms = Number(($json.human && $json.human.after_ms != null) ? $json.human.after_ms : NaN);\n  } catch (e) { ms = NaN; }\n\n  if (isFinite(ms) && ms > 0) {\n    return ms / 1000;\n  }\n\n  // Fallback seguro\n  return 0.4;\n})() }}",
        "unit": "seconds"
      },
      "id": "5458543c-0170-4da7-81e8-d123a95f5c38",
      "name": "Wait - After",
      "type": "n8n-nodes-base.wait",
      "typeVersion": 1,
      "position": [
        608,
        496
      ],
      "webhookId": "d7b2b1ad-87d7-4a06-a0c2-0f9cf1e2b9f5"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "={{ $(\"Build WAHA Antiban\").item.json.waha_base_url + '/api/stopTyping' }}",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "content-type",
              "value": "application/json"
            },
            {
              "name": "x-api-key",
              "value": "={{ $(\"Build WAHA Antiban\").item.json.waha_api_key }}"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ { \"session\": $(\"Build WAHA Antiban\").item.json.waha_session, \"chatId\": $(\"Build WAHA Antiban\").item.json.waha_chat_id } }}",
        "options": {
          "response": {
            "response": {
              "neverError": true
            }
          }
        }
      },
      "id": "1368a456-ccb7-4b89-aea1-c1795bfee1ff",
      "name": "WAHA stopTyping",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        448,
        496
      ]
    },
    {
      "parameters": {
        "functionCode": "function isMapsUrl(u) {\n  const s = String(u || '').toLowerCase();\n  return (\n    s.includes('maps.app.goo.gl') ||\n    s.includes('goo.gl/maps') ||\n    s.includes('google.com/maps') ||\n    s.includes('maps.google.com')\n  );\n}\n\nfunction extractUrls(text) {\n  const t = String(text || '');\n  const re = /https?:\\/\\/[^\\s<>\"'\\)\\]]+/g;\n  const m = t.match(re);\n  return m ? m.map(x => String(x).trim()).filter(Boolean) : [];\n}\n\nfunction lineHasImgUrl(line) {\n  return extractUrls(line).filter(u => !isMapsUrl(u)).length > 0;\n}\n\nfunction isNameLabel(lines, idx) {\n  const line = String(lines[idx] || '').trim();\n  if (!line) return false;\n  if (lineHasImgUrl(line)) return false;\n  for (let j = idx + 1; j < lines.length; j++) {\n    const next = String(lines[j] || '').trim();\n    if (!next) continue;\n    return lineHasImgUrl(next);\n  }\n  return false;\n}\n\nconst src = $json || {};\nconst original = String(src.output_text || src.waha_text || '').trim();\n\nif (!original) {\n  return [{ ...src, __is_first: true, __split_index: 0, __presend_sleep_sec: 0 }];\n}\n\nconst allUrls = extractUrls(original);\nconst imgUrls = allUrls.filter(u => !isMapsUrl(u));\n\nif (!imgUrls.length) {\n  return [{ ...src, output_text: original, __is_first: true, __split_index: 0, __presend_sleep_sec: 0 }];\n}\n\nconst lines = original.split('\\n');\n\nlet firstUrlLineIdx = -1;\nlet lastUrlLineIdx = -1;\nfor (let i = 0; i < lines.length; i++) {\n  if (lineHasImgUrl(lines[i])) {\n    if (firstUrlLineIdx === -1) firstUrlLineIdx = i;\n    lastUrlLineIdx = i;\n  }\n}\n\nconst introLines = [];\nfor (let i = 0; i < firstUrlLineIdx; i++) {\n  const l = String(lines[i] || '').trim();\n  if (l && !isNameLabel(lines, i)) introLines.push(l);\n}\nconst introText = introLines.join('\\n').trim();\n\nconst outroLines = [];\nfor (let i = lastUrlLineIdx + 1; i < lines.length; i++) {\n  const l = String(lines[i] || '').trim();\n  if (l && !isNameLabel(lines, i)) outroLines.push(l);\n}\nconst outroText = outroLines.join('\\n').trim();\n\nconst outItems = [];\n\nif (introText) {\n  outItems.push({ ...src, output_text: introText, __split_kind: 'text', __is_first: true });\n}\n\nfor (const u of imgUrls) {\n  const link = String(u || '').trim();\n  if (!link) continue;\n  outItems.push({ ...src, output_text: link, __split_kind: 'image_link', __is_first: outItems.length === 0 });\n}\n\nif (outroText) {\n  outItems.push({ ...src, output_text: outroText, __split_kind: 'text', __is_first: false });\n}\n\nif (!outItems.length) {\n  return [{ ...src, output_text: original, __is_first: true, __split_index: 0, __presend_sleep_sec: 0 }];\n}\n\nif (!outItems.some(i => i.__is_first === true)) outItems[0].__is_first = true;\nfor (let i = 1; i < outItems.length; i++) outItems[i].__is_first = false;\n\n// --- Calcular __presend_sleep_sec para items no-primeros ---\n// Estimamos el tiempo total del primer mensaje basandonos en habituation\nconst turn = Math.max(1, Number(src.bot_msg_count_recent || 0) + 1);\nconst habRaw = 6.2 * Math.pow(0.92, Math.max(0, turn - 1));\nconst hab = Math.max(1.25, habRaw);\n\nconst firstText = outItems[0] ? String(outItems[0].output_text || '').trim() : '';\nconst firstOutChars = firstText.length;\n\n// Replica simplificada de Compute Human Delays para el primer item\nconst readMs = Math.min((1500 + 50 * 22) * hab, 22000);\nconst typeMs = Math.min((700 + firstOutChars * 60) * hab, 45000);\nconst afterMs = Math.min(500 * hab, 2500);\n// El flujo hace: Delay before Seen (read_ms) + Delay before Typing (read_ms) + Delay while Typing (type_ms) + Wait-After (after_ms)\nconst firstMsgTotalSec = Math.ceil((2 * readMs + typeMs + afterMs) / 1000) + 2; // +2s margen\nconst IMG_GAP_SEC = 2;\n\nfor (let i = 0; i < outItems.length; i++) {\n  outItems[i].__split_index = i;\n  outItems[i].__presend_sleep_sec = i === 0 ? 0 : firstMsgTotalSec + (i - 1) * IMG_GAP_SEC;\n}\n\nreturn outItems;"
      },
      "id": "b4207076-3c31-4f4a-86e7-403c733fa6dd",
      "name": "Split Outgoing (images as solo-link msgs)",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -1088,
        496
      ]
    },
    {
      "parameters": {
        "amount": "={{ Math.max(1, Number($json.__presend_sleep_sec) || 15) }}",
        "unit": "seconds"
      },
      "id": "4d29323e-eae7-4375-b625-d9a857e3d705",
      "name": "Wait Before Send (img)",
      "type": "n8n-nodes-base.wait",
      "typeVersion": 1,
      "position": [
        -368,
        352
      ],
      "webhookId": "f6a7b8c9-d0e1-2345-fabc-de1234567801"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "={{ $(\"Build WAHA Antiban\").item.json.waha_base_url + '/api/startTyping' }}",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "content-type",
              "value": "application/json"
            },
            {
              "name": "x-api-key",
              "value": "={{ $(\"Build WAHA Antiban\").item.json.waha_api_key }}"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ { \"session\": $(\"Build WAHA Antiban\").item.json.waha_session, \"chatId\": $(\"Build WAHA Antiban\").item.json.waha_chat_id } }}",
        "options": {
          "response": {
            "response": {
              "neverError": true
            }
          }
        }
      },
      "id": "a6fdf9d2-50b1-4cd2-88e4-dbdd1628fb4e",
      "name": "WAHA startTyping (img)",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        -240,
        352
      ]
    },
    {
      "parameters": {
        "amount": 0.8,
        "unit": "seconds"
      },
      "id": "4913739c-9dde-4de4-9406-abbaff9c3c69",
      "name": "Wait Short Typing (img)",
      "type": "n8n-nodes-base.wait",
      "typeVersion": 1,
      "position": [
        -112,
        352
      ],
      "webhookId": "c9d0e1f2-a3b4-5678-cdef-012345678804"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "={{ $(\"Build WAHA Antiban\").item.json.waha_base_url + '/api/sendText' }}",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "content-type",
              "value": "application/json"
            },
            {
              "name": "x-api-key",
              "value": "={{ $(\"Build WAHA Antiban\").item.json.waha_api_key }}"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ { \"session\": $(\"Build WAHA Antiban\").item.json.waha_session, \"chatId\": $(\"Build WAHA Antiban\").item.json.waha_chat_id, \"text\": $(\"Build WAHA Antiban\").item.json.waha_text || 'vale cari' } }}",
        "options": {
          "response": {
            "response": {
              "neverError": true
            }
          }
        }
      },
      "id": "683b09a0-a4f3-4832-b997-989b68a37143",
      "name": "WAHA sendText (img)",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        16,
        352
      ]
    },
    {
      "parameters": {
        "method": "POST",
        "url": "={{ $(\"Build WAHA Antiban\").item.json.waha_base_url + '/api/stopTyping' }}",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "content-type",
              "value": "application/json"
            },
            {
              "name": "x-api-key",
              "value": "={{ $(\"Build WAHA Antiban\").item.json.waha_api_key }}"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ { \"session\": $(\"Build WAHA Antiban\").item.json.waha_session, \"chatId\": $(\"Build WAHA Antiban\").item.json.waha_chat_id } }}",
        "options": {
          "response": {
            "response": {
              "neverError": true
            }
          }
        }
      },
      "id": "1e5630bc-e05e-4147-9a35-077838cdcf01",
      "name": "WAHA stopTyping (img)",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        144,
        352
      ]
    },
    {
      "parameters": {
        "method": "POST",
        "url": "={{ $(\"Build WAHA Antiban\").item.json.waha_base_url + '/api/sendText' }}",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "content-type",
              "value": "application/json"
            },
            {
              "name": "x-api-key",
              "value": "={{ $(\"Build WAHA Antiban\").item.json.waha_api_key }}"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ { \"session\": $(\"Build WAHA Antiban\").item.json.waha_session, \"chatId\": $(\"Build WAHA Antiban\").item.json.waha_chat_id, \"text\": $(\"Build WAHA Antiban\").item.json.waha_text || 'vale cari' } }}",
        "options": {
          "response": {
            "response": {
              "neverError": true
            }
          }
        }
      },
      "id": "69cb8f47-27c9-482b-aeff-aad4caa5cf87",
      "name": "WAHA sendText",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        304,
        496
      ]
    },
    {
      "parameters": {
        "method": "POST",
        "url": "={{ $(\"Build WAHA Antiban\").item.json.waha_base_url + '/api/startTyping' }}",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "content-type",
              "value": "application/json"
            },
            {
              "name": "x-api-key",
              "value": "={{ $(\"Build WAHA Antiban\").item.json.waha_api_key }}"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ { \"session\": $(\"Build WAHA Antiban\").item.json.waha_session, \"chatId\": $(\"Build WAHA Antiban\").item.json.waha_chat_id } }}",
        "options": {
          "response": {
            "response": {
              "neverError": true
            }
          }
        }
      },
      "id": "8f9683bc-d62e-4a69-bda3-1116ba6114b9",
      "name": "WAHA startTyping",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        -160,
        496
      ]
    },
    {
      "parameters": {
        "method": "POST",
        "url": "={{ $(\"Build WAHA Antiban\").item.json.waha_base_url + '/api/sendSeen' }}",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "content-type",
              "value": "application/json"
            },
            {
              "name": "x-api-key",
              "value": "={{ $(\"Build WAHA Antiban\").item.json.waha_api_key }}"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ { \"session\": $(\"Build WAHA Antiban\").item.json.waha_session, \"chatId\": $(\"Build WAHA Antiban\").item.json.waha_chat_id } }}",
        "options": {
          "response": {
            "response": {
              "neverError": true
            }
          }
        }
      },
      "id": "a2a99549-eb26-42ca-8cae-34bea39433f8",
      "name": "WAHA sendSeen",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        -448,
        496
      ]
    },
    {
      "parameters": {
        "amount": "={{ (function(){\n  var ms = NaN;\n  try {\n    ms = Number(($json.human && $json.human.type_ms != null) ? $json.human.type_ms : NaN);\n  } catch (e) { ms = NaN; }\n\n  if (isFinite(ms) && ms > 0) {\n    return ms / 1000;\n  }\n\n  // Fallback de seguridad al valor anterior si por lo que sea human no existiera\n  try {\n    var fb = Number($(\"Build WAHA Antiban\").item.json.anti_delay_typing || 4);\n    return (isFinite(fb) && fb > 0) ? fb : 4;\n  } catch (e2) {\n    return 4;\n  }\n})() }}",
        "unit": "seconds"
      },
      "id": "bfbc2044-c1ba-4d65-8b9f-fb7070c96705",
      "name": "Delay while Typing",
      "type": "n8n-nodes-base.wait",
      "typeVersion": 1,
      "position": [
        0,
        496
      ],
      "webhookId": "1b8096f7-d158-474f-bd56-c9159f63a1c6"
    },
    {
      "parameters": {
        "amount": "={{ (function(){\n  var ms = NaN;\n  try {\n    ms = Number(($json.human && $json.human.read_ms != null) ? $json.human.read_ms : NaN);\n  } catch (e) { ms = NaN; }\n\n  if (isFinite(ms) && ms > 0) {\n    return ms / 1000;\n  }\n\n  // Fallback de seguridad al valor anterior si por lo que sea human no existiera\n  try {\n    var fb = Number($(\"Build WAHA Antiban\").item.json.anti_delay_seen || 1);\n    return (isFinite(fb) && fb > 0) ? fb : 1;\n  } catch (e2) {\n    return 1;\n  }\n})() }}",
        "unit": "seconds"
      },
      "id": "010fcf4a-f190-4d92-b5bb-51c2e9099562",
      "name": "Delay before Typing",
      "type": "n8n-nodes-base.wait",
      "typeVersion": 1,
      "position": [
        -304,
        496
      ],
      "webhookId": "a7def307-28d6-43fa-810f-d8c16b246738"
    },
    {
      "parameters": {
        "amount": "={{ (function(){\n  var ms = NaN;\n  try {\n    ms = Number(($json.human && $json.human.read_ms != null) ? $json.human.read_ms : NaN);\n  } catch (e) { ms = NaN; }\n\n  if (isFinite(ms) && ms > 0) {\n    return ms / 1000;\n  }\n\n  // Fallback de seguridad al valor anterior si por lo que sea human no existiera\n  try {\n    var fb = Number($(\"Build WAHA Antiban\").item.json.anti_delay_seen || 1);\n    return (isFinite(fb) && fb > 0) ? fb : 1;\n  } catch (e2) {\n    return 1;\n  }\n})() }}",
        "unit": "seconds"
      },
      "id": "10a123e4-2a76-4d5e-975f-425247a940e4",
      "name": "Delay before Seen (NEW)",
      "type": "n8n-nodes-base.wait",
      "typeVersion": 1,
      "position": [
        -496,
        496
      ],
      "webhookId": "f01b3c1a-7e2b-4c10-8c7a-8b7e2b3c1a10"
    },
    {
      "parameters": {
        "functionCode": "function rnd(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }\nfunction clamp(x, lo, hi) { if (!Number.isFinite(x)) return lo; if (x < lo) return lo; if (x > hi) return hi; return x; }\n\nfunction pickIncomingText() {\n  try {\n    const ext = ($node['Extract WA Text'] && $node['Extract WA Text'].json) ? $node['Extract WA Text'].json : {};\n    const t1 = (ext && typeof ext.message_text === 'string') ? ext.message_text : '';\n    if (t1 && String(t1).trim()) return String(t1);\n  } catch (e) {}\n  return '';\n}\n\nconst incoming_text = String(pickIncomingText() || '');\nconst in_chars = incoming_text.length;\n\nreturn items.map(function(item) {\n  const j = item.json || {};\n\n  function computeTurn() {\n    const tDirect = Number(j.turn);\n    if (Number.isFinite(tDirect) && tDirect >= 1) return Math.floor(tDirect);\n    const tHuman = (j.human && j.human.turn != null) ? Number(j.human.turn) : NaN;\n    if (Number.isFinite(tHuman) && tHuman >= 1) return Math.floor(tHuman);\n    const c = Number(j.bot_msg_count_recent);\n    if (Number.isFinite(c) && c >= 0) return Math.floor(c) + 1;\n    return 1;\n  }\n\n  const outgoing_text = String((typeof j.waha_text === 'string' && j.waha_text) || (typeof j.output_text === 'string' && j.output_text) || '');\n  const out_chars = outgoing_text.length;\n  const turn = computeTurn();\n\n  const startBoost = 6.2;\n  const decay = 0.92;\n  const floor = 1.25;\n  const habituationRaw = startBoost * Math.pow(decay, Math.max(0, (turn - 1)));\n  const habituation = Math.max(floor, habituationRaw);\n\n  const read_base = rnd(900, 2200);\n  const read_per_char = 22;\n  const read_raw = read_base + (Math.min(in_chars, 180) * read_per_char);\n  const read_ms = clamp(read_raw * habituation, 1200, 22000);\n\n  const typing_start = rnd(350, 1200);\n  const per_char = rnd(38, 85);\n  const chunk_size = 24;\n  const chunks = Math.floor(out_chars / chunk_size);\n  const chunk_pauses = chunks * 0.65 * 270;\n  const type_raw = typing_start + (out_chars * per_char) + chunk_pauses;\n  const type_ms = clamp(type_raw * habituation, 1200, 45000);\n\n  const after_raw = rnd(250, 900);\n  const after_ms = clamp(after_raw * habituation, 250, 2500);\n\n  return {\n    json: {\n      ...j,\n      human: { turn, in_chars, out_chars, habituation, read_ms, type_ms, after_ms }\n    }\n  };\n});"
      },
      "id": "311f0135-345d-45e7-a914-bd170222285c",
      "name": "Compute Human Delays",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -528,
        416
      ]
    },
    {
      "parameters": {
        "conditions": {
          "string": [
            {
              "value1": "={{ $json.waha_chat_id }}",
              "operation": "isNotEmpty",
              "value2": ""
            }
          ]
        },
        "options": {}
      },
      "id": "628ea220-3b95-4db9-ad5b-05e0bb8bb37d",
      "name": "IF WAHA antiban enabled?",
      "type": "n8n-nodes-base.if",
      "typeVersion": 2,
      "position": [
        -608,
        496
      ]
    },
    {
      "parameters": {
        "functionCode": "function safeNodeJson(name){try{return $node[name].json||{};}catch(e){return{};}}\n\nconst whIn=safeNodeJson('WAHA Webhook In');\nconst ext=safeNodeJson('Extract WA Text');\nconst norm=safeNodeJson('Normalize Output');\nconst body=whIn.body||{};\nconst payload=whIn.payload||body.payload||{};\nconst query=whIn.query||{};\n\nreturn items.map(function(item){\n  const ctx = item.json || {};\n\n  if (typeof ctx.waha_enabled === 'boolean' && ctx.waha_enabled === false) {\n    return { json: { ...ctx, waha_chat_id: '', waha_text: '', anti_delay_seen: 0, anti_delay_typing: 0 } };\n  }\n\n  let text = String(ctx.output_text || ctx.waha_text || norm.output_text || '').trim();\n  if (!text) { text = String(ext.message_text || '').trim(); }\n  if (!text && query && (query.body || query.message)) { text = String(query.body || query.message || '').trim(); }\n  if (!text && body && (body.body || body.message)) { text = String(body.body || body.message || '').trim(); }\n\n  let raw = ctx.waha_chat_id || ctx.chatId || ext.waha_chat_id_in || payload.chatId || payload.from || body.chatId || body.from || ctx.from_phone || norm.from_phone || ext.from_phone || (query && (query.chatId || query.from)) || whIn.chatId || whIn.from || (Array.isArray(whIn.contacts) && whIn.contacts[0] && (whIn.contacts[0].id || whIn.contacts[0].wa_id)) || '';\n\n  let chatId = '';\n  if (typeof raw === 'string' && raw.includes('@')) { chatId = raw; }\n  else { const digits = String(raw || '').replace(/[^0-9]/g, ''); if (digits) { chatId = digits + '@c.us'; } }\n\n  function rnd(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }\n  let seenDelay = rnd(1, 3);\n  let typingDelay = 2 + Math.floor((text || '').length / 25);\n  if (typingDelay < 2) typingDelay = 2;\n  if (typingDelay > 12) typingDelay = 12;\n  typingDelay += rnd(0, 2);\n\n  return { json: { ...ctx, waha_chat_id: chatId || '', waha_text: text, anti_delay_seen: seenDelay, anti_delay_typing: typingDelay } };\n});"
      },
      "id": "9c378cf9-1202-4d0a-8cab-c436d5a72c73",
      "name": "Build WAHA Antiban",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -768,
        496
      ]
    },
    {
      "parameters": {
        "conditions": {
          "number": [
            {
              "value1": "={{ $json.__is_first === false ? 1 : 0 }}",
              "operation": "equal",
              "value2": 1
            }
          ]
        }
      },
      "id": "38773dbe-3d8b-4cfe-93b1-ddb1d1849efb",
      "name": "IF Is Image Link?",
      "type": "n8n-nodes-base.if",
      "typeVersion": 1,
      "position": [
        -368,
        416
      ]
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "name": "waha_api_key",
              "type": "string",
              "value": "local321"
            },
            {
              "name": "waha_session",
              "type": "string",
              "value": "default"
            },
            {
              "name": "waha_enabled",
              "type": "boolean",
              "value": "={{ (function(){\n  const cfgItem = ($items('Routing + ACL Config') && $items('Routing + ACL Config')[0]) ? $items('Routing + ACL Config')[0].json : {};\n  const cfg = (cfgItem && typeof cfgItem === 'object') ? cfgItem : {};\n  const list = Array.isArray(cfg.waha_numbers_config) ? cfg.waha_numbers_config : [];\n  const defEnabled = (typeof cfg.default_enabled_if_not_found === 'boolean') ? cfg.default_enabled_if_not_found : false;\n\n  const whItem = ($items('WAHA Webhook In') && $items('WAHA Webhook In')[0]) ? $items('WAHA Webhook In')[0].json : {};\n  const wh = (whItem && typeof whItem === 'object') ? whItem : {};\n  const body = wh.body || {};\n  const me = body.me || {};\n  const payload = body.payload || {};\n  const id = String(me.id || payload.to || '').replace(/[^0-9]/g,'');\n  const last9 = id.slice(-9);\n\n  let entry = null;\n  for (const e of list) {\n    if (!e) continue;\n    const k = String(e.last9 || '').replace(/[^0-9]/g,'');\n    if (k && k === last9) { entry = e; break; }\n  }\n\n  return entry ? !!entry.enabled : defEnabled;\n})() }}"
            },
            {
              "name": "waha_base_url",
              "type": "string",
              "value": "={{ (function(){\n  const cfgItem = ($items('Routing + ACL Config') && $items('Routing + ACL Config')[0]) ? $items('Routing + ACL Config')[0].json : {};\n  const cfg = (cfgItem && typeof cfgItem === 'object') ? cfgItem : {};\n  const list = Array.isArray(cfg.waha_numbers_config) ? cfg.waha_numbers_config : [];\n  const defPort = String(cfg.default_port_if_not_found || '3000');\n\n  const whItem = ($items('WAHA Webhook In') && $items('WAHA Webhook In')[0]) ? $items('WAHA Webhook In')[0].json : {};\n  const wh = (whItem && typeof whItem === 'object') ? whItem : {};\n  const body = wh.body || {};\n  const me = body.me || {};\n  const payload = body.payload || {};\n  const id = String(me.id || payload.to || '').replace(/[^0-9]/g,'');\n  const last9 = id.slice(-9);\n\n  let port = defPort;\n  for (const e of list) {\n    if (!e) continue;\n    const k = String(e.last9 || '').replace(/[^0-9]/g,'');\n    if (k && k === last9) {\n      if (e.port != null) port = String(e.port);\n      break;\n    }\n  }\n\n  return 'http://[LAMAMI_SERVER_IP]:' + port;\n})() }}"
            },
            {
              "name": "output_text",
              "type": "string",
              "value": "={{ String($json.output_text || '') }}"
            },
            {
              "name": "__is_first",
              "type": "boolean",
              "value": "={{ $json.__is_first === true }}"
            },
            {
              "name": "__presend_sleep_sec",
              "type": "number",
              "value": "={{ Number($json.__presend_sleep_sec || 0) }}"
            }
          ]
        },
        "options": {}
      },
      "id": "488e2c22-529a-4402-aecc-7905950a8621",
      "name": "WAHA Config",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [
        -928,
        496
      ]
    },
    {
      "parameters": {
        "httpMethod": "POST",
        "path": "waha-in-[LAMAMI_NOMBRE_BOT]",
        "options": {}
      },
      "id": "09bf5694-ece2-4c63-b545-43d50a11ca01",
      "name": "WAHA Webhook In",
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 2.1,
      "position": [
        -2576,
        128
      ],
      "webhookId": "waha-webhook-01",
      "alwaysOutputData": true
    },
    {
      "parameters": {
        "functionCode": "return[{...$json,location_url:'[LAMAMI_MAPS]'}];"
      },
      "id": "3daa2b47-6aeb-4fea-b0d0-1a7f56cc1ce1",
      "name": "Set Location",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        0,
        112
      ]
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://api.openai.com/v1/chat/completions",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "Authorization",
              "value": "Bearer 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"
            },
            {
              "name": "Content-Type",
              "value": "application/json"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ ({ model: 'gpt-4o-mini', response_format: { type: 'json_object' }, messages: [ { role: 'system', content: 'Clasifica el mensaje en JSON:{\"sentiment\":\"positivo|neutro|negativo\",\"register\":\"formal|coloquial\",\"urgency\":\"baja|media|alta\"}. Devuelve SOLO JSON.' }, { role: 'user', content: ($json.user_message || 'Cliente: hola') } ], temperature: 0, max_tokens: 50 }) }}",
        "options": {}
      },
      "id": "7b14f466-57ed-4601-915d-ae056237fdb5",
      "name": "Tone Classifier",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        -288,
        112
      ]
    },
    {
      "parameters": {
        "command": "sh -lc \"rmdir [LAMAMI_SESSION_MEMORY_LOCK] 2>/dev/null || true\""
      },
      "id": "a73187b5-b0d7-4e18-aee2-ee44efb87d7f",
      "name": "Release Soft Lock",
      "type": "n8n-nodes-base.executeCommand",
      "typeVersion": 1,
      "position": [
        384,
        336
      ]
    },
    {
      "parameters": {
        "command": "sh -lc \"mv [LAMAMI_SESSION_MEMORY_FILE_TMP] [LAMAMI_SESSION_MEMORY_FILE]\""
      },
      "id": "ea6b02a2-7daf-4488-833a-d2254d2e7310",
      "name": "Atomic Move TMP→FINAL",
      "type": "n8n-nodes-base.executeCommand",
      "typeVersion": 1,
      "position": [
        64,
        336
      ]
    },
    {
      "parameters": {
        "fileName": "[LAMAMI_SESSION_MEMORY_FILE_TMP]",
        "options": {}
      },
      "id": "b248a85b-e5e8-46ff-9dda-f0db12ee3927",
      "name": "Write Memory (TMP)",
      "type": "n8n-nodes-base.writeBinaryFile",
      "typeVersion": 1,
      "position": [
        -80,
        336
      ]
    },
    {
      "parameters": {
        "functionCode": "const s=$json.memory_ndjson_out||'';const b64=Buffer.from(s,'utf8').toString('base64');return[{...$json,binary:{data:{data:b64}}}];"
      },
      "id": "e60831a7-d5a1-4a2b-9f11-0374a0912b74",
      "name": "Text To Binary (NDJSON)",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -256,
        336
      ]
    },
    {
      "parameters": {
        "functionCode": "function safeNodeJson(name){ try { return $node[name].json || {}; } catch(e){ return {}; } }\nfunction firstNonEmpty(arr){ for(const v of arr){ const s=String(v||'').trim(); if(s) return s; } return ''; }\n\nconst prev = $json.mem_prev_raw || '';\nconst NO = safeNodeJson('Normalize Output');\nconst AO = safeNodeJson('Audio Auto Reply');\nconst EXT = safeNodeJson('Extract WA Text');\nconst FM = safeNodeJson('Format Memory');\n\nconst phone = String(firstNonEmpty([NO.from_phone, AO.from_phone, EXT.from_phone, $json.from_phone]));\nlet rawUser = firstNonEmpty([NO.user_message, AO.user_message, EXT.message_text, $json.user_message]);\nrawUser = String(rawUser || '').replace(/^Cliente:\\s*/,'');\nif (!rawUser.trim()) rawUser = '[SIN_TEXTO]';\n\nconst user_msg = rawUser;\nconst reply_text = String(firstNonEmpty([NO.output_text, AO.output_text, $json.output_text]));\nconst ts = new Date().toISOString();\nconst thread_id = String(firstNonEmpty([NO.thread_id, AO.thread_id, FM.thread_id, $json.thread_id, ('th-' + Date.now())]));\n\nconst lines = prev.split('\\n').filter(l => l.trim().length > 0);\nconst recs = [];\nfor (const l of lines) { try { recs.push(JSON.parse(l)); } catch (e) {} }\n\nconst nextSeq = (recs.reduce((m,r)=>Math.max(m, r._seq || 0), 0) + 1);\n\nconst selected_girl_id = String(firstNonEmpty([\n  FM.selected_girl_id,\n  NO.selected_girl_id,\n  $json.selected_girl_id\n]));\n\nconst selected_girl_name = String(firstNonEmpty([\n  FM.selected_girl_name,\n  NO.selected_girl_name,\n  $json.selected_girl_name\n]));\n\n// speaker_girl: la chica que habla (fija para siempre)\nconst speaker_girl_id = String(firstNonEmpty([\n  FM.speaker_girl_id,\n  NO.speaker_girl_id,\n  $json.speaker_girl_id\n]));\n\nconst speaker_girl_name = String(firstNonEmpty([\n  FM.speaker_girl_name,\n  NO.speaker_girl_name,\n  $json.speaker_girl_name\n]));\n\nconst speaker_mode = String(firstNonEmpty([\n  FM.speaker_mode,\n  NO.speaker_mode,\n  $json.speaker_mode\n]));\n\nconst newRec = {\n  _seq: nextSeq,\n  ts,\n  phone,\n  user_msg,\n  reply_text,\n  thread_id,\n  selected_girl_id,\n  selected_girl_name,\n  speaker_girl_id,\n  speaker_girl_name,\n  speaker_mode\n};\n\nconst outText = recs.concat([newRec]).slice(-1000).map(o => JSON.stringify(o)).join('\\n') + '\\n';\n\nreturn [{ ...$json, memory_ndjson_out: outText }];"
      },
      "id": "617a8ff0-6894-47a5-beab-ecd4cf94b164",
      "name": "Append Memory",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -416,
        336
      ]
    },
    {
      "parameters": {
        "functionCode": "const b=(items[0]?.binary?.data?.data)||'';let t='';try{if(b)t=Buffer.from(b,'base64').toString('utf8');}catch(e){t='';}return[{...$json,mem_prev_raw:(t||'')}];"
      },
      "id": "3a894854-410f-4542-a4da-3fb6c3de1e32",
      "name": "Bin2Text Memory Prev",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -592,
        336
      ]
    },
    {
      "parameters": {
        "filePath": "[LAMAMI_SESSION_MEMORY_FILE]"
      },
      "id": "7b7a6e1f-3f00-4e5a-a177-6d523c545c91",
      "name": "Read Memory For Append",
      "type": "n8n-nodes-base.readBinaryFile",
      "typeVersion": 1,
      "position": [
        -768,
        336
      ]
    },
    {
      "parameters": {
        "command": "LOCK=[LAMAMI_SESSION_MEMORY_LOCK]\nTRIES=50\nSLEEP=0.1\ni=0\nwhile [ \"$i\" -lt \"$TRIES\" ]; do\n  if mkdir \"$LOCK\" 2>/dev/null; then\n    echo LOCKED\n    exit 0\n  fi\n  i=$((i+1))\n  sleep \"$SLEEP\"\ndone\necho BUSY\nexit 0\n"
      },
      "id": "8837322d-d430-4f48-911c-22b29b371961",
      "name": "Acquire Soft Lock",
      "type": "n8n-nodes-base.executeCommand",
      "typeVersion": 1,
      "position": [
        -928,
        336
      ]
    },
    {
      "parameters": {
        "functionCode": "function norm(s){\n  let out = String(s||'').toLowerCase();\n  try{ out = out.normalize('NFKD'); }catch(e){}\n  out = out.replace(/[\\u0300-\\u036f]/g,'');\n  out = out.replace(/[\\.,!\\?;:]/g,'');\n  out = out.replace(/\\s+/g,' ').trim();\n  return out;\n}\n\nfunction pick(arr){\n  return arr[Math.floor(Math.random()*arr.length)];\n}\n\nconst out = String($json.output_text || '').trim();\nconst outN = norm(out);\n\nconst recent = Array.isArray($json.recent_bot_replies_norm) ? $json.recent_bot_replies_norm : [];\nconst last = norm($json.last_bot_reply || '');\n\nconst isRepeat = (!!outN && (outN === last || recent.includes(outN)));\nif (!isRepeat) return [{...$json}];\n\n// Variaciones suaves (no cambian la info)\nconst startVariants = [\n  'vale, te cuento rapido',\n  'mira, te digo',\n  'te explico',\n  'ok, vamos a ello',\n  'perfecto, mira'\n];\n\nconst endVariants = [\n  'dime cual te encaja mas',\n  'cual prefieres?',\n  'con cual te quedas?',\n  'te va bien asi?'\n];\n\nlet rewritten = out;\n\n// Si empieza parecido, cambia el arranque\nconst lines = rewritten.split('\\n').map(x=>x.trim()).filter(Boolean);\nif (lines.length) {\n  // Cambia primera linea si es muy típica\n  lines[0] = pick(startVariants);\n  rewritten = lines.join('\\n');\n}\n\n// Si no hay pregunta final, añade una corta\nif (!/[\\?]$/.test(rewritten.trim())) {\n  rewritten = rewritten.trim() + '\\n\\n' + pick(endVariants);\n}\n\n// Última defensa: si sigue igual normalizado, añade micro-frase distinta\nif (norm(rewritten) === outN) {\n  rewritten = rewritten.trim() + ' (asi queda mas claro)';\n}\n\nreturn [{\n  ...$json,\n  output_text: rewritten.trim(),\n  __dedup_applied: true\n}];"
      },
      "id": "cab300d3-d8b8-47fa-910e-76059a026048",
      "name": "DeDupe Reply (guard)",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -832,
        400
      ]
    },
    {
      "parameters": {
        "functionCode": "function norm(s){\n  let out = String(s||'').toLowerCase();\n  try{ out = out.normalize('NFKD'); }catch(e){}\n  out = out.replace(/[\\u0300-\\u036f]/g,'');\n  out = out.replace(/\\s+/g,' ').trim();\n  return out;\n}\n\nfunction asBool(x){\n  if (typeof x==='boolean') return x;\n  if (typeof x==='number') return x!==0;\n  if (typeof x==='string') {\n    const s=x.trim().toLowerCase();\n    return ['true','1','yes','y','si','sí'].includes(s);\n  }\n  return false;\n}\n\nconst out = String($json.output_text || '').trim();\nconst girls = Array.isArray($json.girls_config) ? $json.girls_config : [];\nconst selectedName = String($json.selected_girl_name || '').trim();\nconst wantsMore = asBool($json.wants_more_girls);\n\n// Detecta links tipo ibb (o los que uses en fotos)\nconst linkRe = /https?:\\/\\/(?:ibb\\.co|i\\.ibb\\.co)\\/[A-Za-z0-9]+/g;\nconst foundLinks = out.match(linkRe) || [];\nif (!foundLinks.length) return [{...$json}];\n\n// Mapa link -> nombre\nconst active = girls\n  .map(g => ({\n    id: String(g?.id||'').trim(),\n    nombre: String(g?.nombre||'').trim(),\n    activa: asBool(g?.activa),\n    fotos: Array.isArray(g?.fotos) ? g.fotos : []\n  }))\n  .filter(g => g.activa && g.nombre);\n\nconst linkToName = new Map();\nfor (const g of active) {\n  for (const f of g.fotos) {\n    const u = String(f||'').trim();\n    if (u) linkToName.set(u, g.nombre);\n  }\n}\n\n// Construye bloque deseado\nfunction buildBlock(list){\n  return list.map(x => `${x.nombre}\\n${x.link}`).join('\\n\\n').trim();\n}\n\nlet blockItems = [];\n\nif (selectedName && !wantsMore) {\n  // Solo la seleccionada\n  const selN = norm(selectedName);\n  const sel = active.find(g => norm(g.nombre) === selN);\n  const link = sel?.fotos?.[0] ? String(sel.fotos[0]).trim() : '';\n  if (link) blockItems = [{ nombre: sel.nombre, link }];\n} else {\n  // Catalogo de todas activas (1 link por cada una)\n  for (const g of active) {\n    const link = g?.fotos?.[0] ? String(g.fotos[0]).trim() : '';\n    if (link) blockItems.push({ nombre: g.nombre, link });\n  }\n}\n\nif (!blockItems.length) return [{...$json}];\n\nconst formatted = buildBlock(blockItems);\n\n// Conserva intro y cierre (si existían) pero sustituye el bloque de links por el formato correcto\nconst firstLinkIdx = out.search(linkRe);\nconst before = firstLinkIdx > 0 ? out.slice(0, firstLinkIdx).trim() : '';\nconst after = out.slice(firstLinkIdx).replace(linkRe, '').replace(/\\n{3,}/g,'\\n\\n').trim();\n\nlet rebuilt = '';\nif (before) rebuilt += before + '\\n\\n';\nrebuilt += formatted;\nif (after) rebuilt += '\\n\\n' + after;\n\nreturn [{\n  ...$json,\n  output_text: rebuilt.trim(),\n  __catalog_formatted: true\n}];"
      },
      "id": "48e260fa-278f-4ca5-849c-bb4b1fb8181c",
      "name": "Post-Format Catalog (hard enforce)",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -992,
        400
      ]
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "name": "output_text",
              "type": "string",
              "value": "={{ (function () {\n var reply = '';\n var raw = '{}';\n try {\n var choices = ($json && $json.choices) ? $json.choices : [];\n var c0 = choices[0] || {};\n var msg = c0.message || {};\n raw = (typeof msg.content === 'string' && msg.content) ? msg.content : '{}';\n var o = JSON.parse(raw);\n reply = String((o && typeof o === 'object' && o.user_visible_reply != null) ? o.user_visible_reply : raw).trim();\n } catch (e) {\n try {\n var choices2 = ($json && $json.choices) ? $json.choices : [];\n var c02 = choices2[0] || {};\n var msg2 = c02.message || {};\n reply = String(msg2.content || '').trim();\n } catch (e2) {\n reply = '';\n }\n }\n return String(reply || '').trim();\n})() }}"
            },
            {
              "name": "lead_detected",
              "type": "boolean",
              "value": "={{ (function(){\n try {\n const raw = $json?.choices?.[0]?.message?.content;\n if (!raw) return false;\n const o = JSON.parse(raw);\n return !!o.lead_detected;\n } catch(e){ return false; }\n})() }}"
            },
            {
              "name": "lead_confidence",
              "type": "number",
              "value": "={{ (function(){\n try {\n const raw = $json?.choices?.[0]?.message?.content;\n if (!raw) return 0;\n const o = JSON.parse(raw);\n const v = Number(o.lead_confidence);\n if (!Number.isFinite(v)) return 0;\n return Math.max(0, Math.min(1, v));\n } catch(e){ return 0; }\n})() }}"
            },
            {
              "name": "eta_minutes",
              "type": "number",
              "value": "={{ (function(){\n try {\n const raw = $json?.choices?.[0]?.message?.content;\n if (!raw) return 0;\n const o = JSON.parse(raw);\n const v = o.eta_minutes;\n if (v === null || v === undefined) return 0;\n const n = Number(v);\n return (Number.isFinite(n) && n > 0) ? n : 0;\n } catch(e){ return 0; }\n})() }}"
            },
            {
              "name": "lead_numeric",
              "type": "number",
              "value": "={{ (function(){\n try {\n const raw = $json?.choices?.[0]?.message?.content;\n if (!raw) return 0;\n const o = JSON.parse(raw);\n return o && o.lead_detected ? 1 : 0;\n } catch(e){ return 0; }\n})() }}"
            },
            {
              "name": "lead_flag",
              "type": "string",
              "value": "={{ (function(){\n try {\n const raw = $json?.choices?.[0]?.message?.content;\n if (!raw) return '0';\n const o = JSON.parse(raw);\n return (o && o.lead_detected) ? '1' : '0';\n } catch(e){ return '0'; }\n})() }}"
            },
            {
              "name": "selected_girl_id",
              "type": "string",
              "value": "={{ $node[\"Format Memory\"].json.selected_girl_id || $json.selected_girl_id || '' }}"
            },
            {
              "name": "selected_girl_name",
              "type": "string",
              "value": "={{ $node[\"Format Memory\"].json.selected_girl_name || $json.selected_girl_name || '' }}"
            },
            {
              "name": "speaker_girl_id",
              "type": "string",
              "value": "={{ $node[\"Format Memory\"].json.speaker_girl_id || $json.speaker_girl_id || '' }}"
            },
            {
              "name": "speaker_girl_name",
              "type": "string",
              "value": "={{ $node[\"Format Memory\"].json.speaker_girl_name || $json.speaker_girl_name || '' }}"
            },
            {
              "name": "speaker_mode",
              "type": "string",
              "value": "={{ $node[\"Format Memory\"].json.speaker_mode || $json.speaker_mode || 'encargada' }}"
            }
          ]
        },
        "options": {}
      },
      "id": "700bb7ba-7f2b-437c-a45d-3048cf971d33",
      "name": "Normalize Output",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [
        -1152,
        400
      ]
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://api.openai.com/v1/chat/completions",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "Authorization",
              "value": "Bearer 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"
            },
            {
              "name": "Content-Type",
              "value": "application/json"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ ({\n  model: 'gpt-5.1',\n  response_format: { type: 'json_object' },\n  messages: [\n    {\n      role: 'system',\n      content: (function(){\n const prompt = ($json.prompt_text || '');\n const pbRaw = String($json.playbook_text || '');\n const lines = pbRaw.split('\\n');\n let out = [];\n for (let i=0;i<lines.length && i<40;i++) {\n   const L = lines[i];\n   if (String(L).trim().startsWith('## Intents')) break;\n   out.push(L);\n }\n const playbookSoft = out.join('\\n').trim();\n\n return (\n   prompt +\n   '\\n\\n### PLAYBOOK (suave)\\n' + playbookSoft +\n   '\\n\\n### NOTA SOBRE EL PLAYBOOK\\nEl PLAYBOOK es solo guia de estilo. Si contradice reglas explicitas del system prompt, manda el system prompt.\\n' +\n   '\\n### CONTEXTO DE LA CONVERSA ACTUAL (prioridad alta)\\n' +\n   '- sales_stage: ' + JSON.stringify($json.sales_stage || '') + '\\n' +\n   '- topic_actual: ' + JSON.stringify($json.topic_actual || '') + '\\n' +\n   '- ya_enviado: ' + JSON.stringify($json.ya_enviado || {}) + '\\n' +\n   '- pendiente: ' + JSON.stringify($json.pendiente || {}) + '\\n' +\n   '- session_reset (boolean): ' + JSON.stringify($json.session_reset || false) + '\\n' +\n   '- last_bot_reply: ' + JSON.stringify($json.last_bot_reply || '') + '\\n' +\n   '- last_user_message: ' + JSON.stringify($json.last_user_message || '') + '\\n' +\n   '- tarifa_elegida: ' + JSON.stringify($json.tarifa_elegida || '') + '\\n' +\n   '- maps_sent (boolean): ' + JSON.stringify($json.maps_sent || false) + '\\n' +\n   '- speaker_girl_id: ' + JSON.stringify($json.speaker_girl_id || '') + '\\n' +\n   '- speaker_girl_name: ' + JSON.stringify($json.speaker_girl_name || '') + '\\n' +\n   '- selected_girl_id: ' + JSON.stringify($json.selected_girl_id || '') + '\\n' +\n   '- selected_girl_name: ' + JSON.stringify($json.selected_girl_name || '') + '\\n' +\n   '- speaker_mode: ' + JSON.stringify($json.speaker_mode || '') + '\\n' +\n   '- wants_more_girls (boolean): ' + JSON.stringify($json.wants_more_girls || false) + '\\n' +\n   '- conversation_end_intent (boolean): ' + JSON.stringify($json.conversation_end_intent || false) + '\\n' +\n   '- emoji_count_recent: ' + JSON.stringify($json.emoji_count_recent || 0) + '\\n' +\n   '- haggle_count_recent: ' + JSON.stringify($json.haggle_count_recent || 0) + '\\n' +\n   '- interes_fuerte (boolean): ' + JSON.stringify($json.interes_fuerte || false) + '\\n' +\n   '- ubicacion_pedida_fuerte (boolean): ' + JSON.stringify($json.ubicacion_pedida_fuerte || false) + '\\n' +\n   '- wants_visit_before_choice (boolean): ' + JSON.stringify($json.wants_visit_before_choice || false) + '\\n' +\n   '- recent_bot_replies_norm: ' + JSON.stringify($json.recent_bot_replies_norm || []) + '\\n' +\n   '- location_url: ' + JSON.stringify($json.location_url || '') + '\\n\\n' +\n\n   '### TIPO DE NEGOCIO (PRIORIDAD MAXIMA)\\n' +\n   '- Es un servicio sexual o casa de citas, se ofrecen chicas para ir solo al local a mantener sexo, o disfrutar de compañía. Usar tono insinuante, provocativo, caliente y sexy.\\n\\n' +\n\n   '### TONO Y VARIACION DINAMICA\\n' + ($json.tone_directives || '') + '\\n\\n' +\n\n   '### REGLA: NO SHOWROOM / SIN PRESENTACION\\n' +\n   '- Solo si wants_visit_before_choice=true o el cliente pregunta por \"ver antes\", \"presentacion\", \"pase\", etc.: explica claro que NO se puede venir a mirar/escoger en persona.\\n' +\n   '- Se reserva primero con una scort/profesional (trabajan por agenda/independientes) y luego se coordina la cita.\\n' +\n   '- Si NO lo preguntan, NO lo menciones.\\n\\n' +\n\n   '### ANTI-REPETICION\\n' +\n   '- No repitas frases literales durante la conversacion.\\n' +\n   '- Si tu respuesta se parece mucho a last_bot_reply o aparece en recent_bot_replies_norm, reescribela con otras palabras manteniendo la misma info.\\n\\n' +\n\n   '### IDENTIDAD (PRIORIDAD MAXIMA)\\n' +\n   '- speaker_girl_name es la chica que HABLA (tu identidad): NUNCA cambia, es fija para toda la conversacion.\\n' +\n   '- selected_girl_name es la chica para la que es el SERVICIO/CITA (puede ser diferente de speaker_girl_name).\\n' +\n   '- Si speaker_girl_name NO esta vacio: eres ella, hablas en 1ª persona como ella siempre.\\n' +\n   '- Si el cliente menciona otra chica o pregunta por ella: la tratas como \"mi amiguita\" en 3ª persona. NUNCA te hagas pasar por otra chica.\\n' +\n   '- Si speaker_girl_name != selected_girl_name: el cliente eligio a tu amiga para el servicio. Hablas como speaker_girl_name pero los datos de la cita (ubicacion, fotos extras) son de selected_girl_name. Ejemplo: \"mi amiga [selected] esta disponible, te paso su ubicacion\".\\n' +\n   '- Si speaker_girl_name esta vacio: estas en modo encargada, hablas como la casa.\\n\\n' +\n\n   '### CATALOGO (girls_config)\\n' +\n   '- Solo usa nombres/enlaces de girls_config con activa=true.\\n' +\n   '- Ubicacion exacta (location_url) SOLO si selected_girl_name NO esta vacio. Si esta vacio: solo zona general y pedir eleccion.\\n' +\n   '- Fotos / enlaces de catalogo:\\n' +\n   '  - SIEMPRE en este formato por cada una:\\n' +\n   '    NOMBRE\\n' +\n   '    ENLACE\\n' +\n   '    (linea en blanco)\\n' +\n   '  - No pongas texto en la misma linea del link.\\n' +\n   '  - Si selected_girl_name NO esta vacio: manda SOLO las fotos de selected_girl_name (salvo que pidan mas chicas/todas).\\n' +\n   '  - Si selected_girl_name esta vacio: puedes mandar catalogo para ayudar a elegir.\\n' +\n   '\\nListado girls_config (solo contexto, no lo repitas tal cual):\\n' +\n   JSON.stringify($json.girls_config || [], null, 2)\n );\n      })()\n    },\n    {\n      role: 'user',\n      content: ($json.user_message || 'Cliente: hola')\n    }\n  ],\n  temperature: 0.6,\n  max_completion_tokens: 3200\n}) }}",
        "options": {}
      },
      "id": "5b6ab6b3-da5b-415b-8dcc-839ef3ec0914",
      "name": "OpenAI Chat",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        -1280,
        480
      ]
    },
    {
      "parameters": {
        "functionCode": "let t={sentiment:'neutro',register:'coloquial',urgency:'media'};try{t=JSON.parse($json.choices?.[0]?.message?.content||'{}');}catch(e){}\nconst ctx=$(\"Assemble Context (No-Merge)\").item.json||{};\nconst reset=!!ctx.tone_reset;\nconst emoji_count_recent=Number(ctx.emoji_count_recent||0);\nconst topic_actual=ctx.topic_actual||'';\nconst user_message=ctx.user_message||'';\nconst sales_stage=ctx.sales_stage||'';\nconst selected_girl_name=ctx.selected_girl_name||'';\nconst speaker_girl_name=ctx.speaker_girl_name||'';\nconst conversation_end_intent=!!ctx.conversation_end_intent;\nconst interes_fuerte=!!ctx.interes_fuerte;\nconst haggle_count_recent=Number(ctx.haggle_count_recent||0);\nconst choose_loop_count=Number(ctx.choose_loop_count||0);\nconst speaker_mode=String(ctx.speaker_mode||'encargada');\n\nconst coreTopics=['precios','ubicacion','servicios','pago','cita/eta'];\nfunction norm(s){return String(s||'').toLowerCase().normalize('NFKD').replace(/[\\u0300-\\u036f]/g,'');}\nconst msgNorm=norm(user_message);\nlet isImportant=false;\nif(coreTopics.includes(topic_actual)) isImportant=true;\nelse if(/precio|tarif|€|eur|foto|fotos|ubicacion|ubi\\b|maps|mapa|direccion|donde|servici|chica|nombre|quien eres|como eres/.test(msgNorm)) isImportant=true;\n\nlet baseDir='Usa registro '+(t.register||'coloquial')+', tono '+(t.sentiment==='negativo'?'calmado y empatico':'cercano y carinoso')+', urgencia '+(t.urgency||'media')+'. ';\nbaseDir+='Tono femenino, cariñoso y sugerente (erotico cañero), sin vulgaridad. Evita palabras crudas (\"follar\", \"polvo\", \"nen\", \"toi\"). Frases cortas, directas, sin signos de apertura. ';\n\nif(conversation_end_intent){\n  baseDir+='El usuario esta cerrando (gracias/saludos/adios). Responde UNA sola vez, muy corto (1 frase), amable, sin preguntas, sin reabrir temas. ';\n}\n\nlet emojiDir='';\nif(conversation_end_intent){emojiDir+='No uses emoji. ';}else if(isImportant && emoji_count_recent>=1){emojiDir+='Sin emoji, estas dando informacion importante. ';}else if(emoji_count_recent>=3){emojiDir+='NO uses emoji en este mensaje (ya hay bastantes). ';}else if(interes_fuerte && emoji_count_recent<=1){emojiDir+='La conversacion esta caliente. Puedes usar 1-2 emojis picantes si pegan. ';}else if(emoji_count_recent>=2){emojiDir+='Este mensaje sin emoji, toca descansar. ';}else{emojiDir+='Puedes usar 1 emoji suave al final si pega (sin forzar). ';}\n\nlet lengthDir='';\nif(isImportant) lengthDir+='Responde claro sin enrollarte: 1-2 frases, max 3 si hay varias preguntas. ';\nelse lengthDir+='Si es smalltalk/filler, responde ultra corto (1 frase). ';\n\nlet stageDir='';\nstageDir+='Si sales_stage es '+JSON.stringify(sales_stage)+', evita retroceder a temas ya cerrados. ';\nif(selected_girl_name) stageDir+='El servicio es para selected_girl_name='+JSON.stringify(selected_girl_name)+': usa sus datos/fotos/ubicacion para la cita. ';\n\nlet speakerDir='';\nif(speaker_mode==='encargada'){\n  speakerDir+='Estas en modo encargada: hablas como la casa, ofreces opciones, sin decir \"soy telefonista\". ';\n} else {\n  speakerDir+='Estas en modo chica: eres '+JSON.stringify(speaker_girl_name||'la elegida')+' y hablas en primera persona, muy cariñosa y sugerente, sin vulgaridad. NO cambies de identidad bajo ninguna circunstancia. ';\n  if(selected_girl_name && speaker_girl_name && norm(selected_girl_name) !== norm(speaker_girl_name)){\n    speakerDir+='El cliente ha elegido a '+JSON.stringify(selected_girl_name)+' para el servicio final, pero TU sigues siendo '+JSON.stringify(speaker_girl_name)+'. Habla de '+JSON.stringify(selected_girl_name)+' como \"mi amiguita\" o \"una compi mia\" en tercera persona; los datos de ubicacion/cita corresponden a ella. ';\n  } else if(selected_girl_name){\n    speakerDir+='El cliente ya te eligio a ti, no vuelvas a preguntar. ';\n  }\n}\n\nlet haggleDir='';\nif(haggle_count_recent>=3){\n  haggleDir+='Regateo repetido: modo TAJANTE (corto, firme). No descuentos. Si insiste: \"si buscas mas barato no soy yo\" y cierras con una pregunta de avance (hora/venir) o sin pregunta si ya esta pesado. ';\n}else if(haggle_count_recent>=2){\n  haggleDir+='Regateo: mas firme y corto. No descuentos. Reconduce a 50/100. ';\n}\n\nlet loopDir='';\nif(choose_loop_count>=3){\n  loopDir+='Bucle de ubicacion/eleccion: responde mas corto, repite limite una sola vez y da salida clara (elige entre nombres). No entres en discusion. ';\n}\n\nconst dir=(reset?(baseDir+emojiDir+lengthDir+stageDir+speakerDir+haggleDir+loopDir+'Reinicio de tono (pasaron >6h).'):(baseDir+emojiDir+lengthDir+stageDir+speakerDir+haggleDir+loopDir));\nreturn[{...ctx,tone_directives:dir}];"
      },
      "id": "1f635910-8fdb-4d81-982d-a04f9b919af4",
      "name": "Build Tone",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -112,
        192
      ]
    },
    {
      "parameters": {
        "functionCode": "function safeText(s){return(typeof s==='string'?s:'').trim();}\nfunction countBotMsgs(mem){if(!mem)return 0;const lines=String(mem).split('\\n');let c=0;for(const line of lines){if(line.includes('| B:')){const parts=line.split('| B:');if(parts[1]&&String(parts[1]).trim())c++;}}return c;}\n\nconst from_phone=$(\"Extract WA Text\").item.json.from_phone||'';\nlet message_text=safeText($(\"Extract WA Text\").item.json.message_text||'');\nmessage_text=message_text.replace(/^Cliente:\\s*/i,'');\n\nconst prompt_text=$(\"Set Prompt\").item.json.prompt_text||'';\n\nconst fm=$(\"Format Memory\").item.json||{};\nconst memory_text=fm.memory_text||'';\nconst bot_msg_count_recent=countBotMsgs(memory_text);\nconst thread_id=fm.thread_id||'';\nconst tone_reset=!!fm.tone_reset;\nconst playbook_text=$(\"Bin2Text Playbook\").item.json.playbook_text||'';\nconst user_message=message_text||'';\n\nreturn [{\n  user_message,\n  from_phone,\n  prompt_text,\n  memory_text,\n  thread_id,\n  tone_reset,\n  playbook_text,\n  topic_actual: fm.topic_actual||'',\n  topic_cambiado: !!fm.topic_cambiado,\n  ya_enviado: fm.ya_enviado||[],\n  pendiente: (typeof fm.pendiente==='string'&&fm.pendiente.length?fm.pendiente:null),\n  session_reset: !!fm.session_reset,\n  last_bot_reply: fm.last_bot_reply||'',\n  last_user_message: fm.last_user_message||'',\n  tarifa_elegida: fm.tarifa_elegida||'',\n  maps_sent: !!fm.maps_sent,\n  interes_fuerte: !!fm.interes_fuerte,\n  ubicacion_pedida_fuerte: !!fm.ubicacion_pedida_fuerte,\n  emoji_count_recent: Number(fm.emoji_count_recent||0),\n  last_emoji: fm.last_emoji||'',\n  bot_msg_count_recent,\n  recent_saludo: !!fm.recent_saludo,\n  last_user_meaningful: fm.last_user_meaningful||'',\n  client_name: fm.client_name||'',\n  last_open_question: fm.last_open_question||'',\n  current_name_candidate: fm.current_name_candidate||'',\n  current_is_filler: !!fm.current_is_filler,\n  sales_stage: fm.sales_stage||'',\n  selected_girl_id: fm.selected_girl_id||'',\n  selected_girl_name: fm.selected_girl_name||'',\n  speaker_girl_id: fm.speaker_girl_id||'',\n  speaker_girl_name: fm.speaker_girl_name||'',\n  wants_more_girls: !!fm.wants_more_girls,\n  conversation_end_intent: !!fm.conversation_end_intent,\n  photos_sent_recent: !!fm.photos_sent_recent,\n  must_choose_girl_now: !!fm.must_choose_girl_now,\n  choose_loop_count: Number(fm.choose_loop_count||0),\n  haggle_count_recent: Number(fm.haggle_count_recent||0),\n  speaker_mode: fm.speaker_mode||'encargada',\n  eta_from_user_minutes: Number(fm.eta_from_user_minutes||0),\n  eta_from_user_flag: !!fm.eta_from_user_flag,\n  girls_config: fm.girls_config||[],\n  activeGirls: fm.activeGirls||[],\n  wants_visit_before_choice: !!fm.wants_visit_before_choice,\n  recent_bot_replies_norm: fm.recent_bot_replies_norm || []\n}];"
      },
      "id": "fd84cd91-b24b-4562-b48d-db5816e8c242",
      "name": "Assemble Context (No-Merge)",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -416,
        112
      ]
    },
    {
      "parameters": {
        "functionCode": "function withinHours(ts,h){try{const d=new Date(ts).getTime();if(!isFinite(d))return false;return(Date.now()-d)<=h*3600*1000;}catch(e){return false;}}\nfunction pickPhone(){try{const e=$node['Extract WA Text']?.json||{};return String(e.from_phone||'');}catch(_){return String($json.from_phone||'');}}\nfunction norm(s){return String(s||'').toLowerCase().normalize('NFKD').replace(/[\\u0300-\\u036f]/g,'');}\nfunction escapeRe(s){return String(s||'').replace(/[.*+?^${}()|[\\]\\\\]/g,'\\\\$&');}\n\nfunction detectTopic(txt){\n  const t=norm(txt);\n  if(!t) return 'otro';\n  const directPrice = /precio|precios|tarif|cuesta|vale\\b|sale\\b|€|\\beur\\b|euros/.test(t);\n  const qPrice = /(a\\s*cuanto|cuanto\\s*(es|sale|cuesta|vale)|que\\s*vale|a\\s*como|cuanto\\s*seria)/.test(t);\n  const priceAnchor = /(media\\s*h|media\\s+hora|mediahora|\\b50\\b|\\b100\\b|\\bhora\\b|\\b1h\\b|60\\s*min|30\\s*min)/.test(t);\n  const etaWords = /(tardas|tarda|llegas|llega|minutos|min\\b|en\\s+cuanto\\s+llegas|cuanto\\s+tardas)/.test(t);\n  if ((directPrice || (qPrice && priceAnchor)) && !etaWords) return 'precios';\n  if(/ubi\\b|ubic|donde|direccion|maps|mapa|lugar|calle|punto|pin\\b|ubicacion\\s*real/.test(t))return'ubicacion';\n  if(/puedo ir|voy|me paso|voy ya|me acerco|quiero visitarte|quiero verte hoy|ahora mismo|ahora voy|salgo para alla/.test(t))return'cita/eta';\n  if(/servici|haces|ofreces|detalles|como es|que incluye/.test(t))return'servicios';\n  if(/pago|bizum|efectivo|transfer|tarjeta/.test(t))return'pago';\n  if(/hola|buenas|hey|ola|👋|🙋/.test(t))return'smalltalk';\n  return'otro';\n}\n\nfunction isFillerUser(txt){const t=norm(txt);if(!t)return false;\n  if(/^(cari|carino|amor|bb|bebe|guapo|hola|buenas|ok|okis|vale|aja|jeje+|jaja+|perfecto|genial|gracias|ok gracias|un saludo|saludos|adios|hasta luego|hasta ahora)$/.test(t))return true;\n  if(/^[👍👌✌️❤️😘😉😏😂😅💕😊]+$/.test(String(txt).trim()))return true;\n  return false;\n}\n\nfunction yaEnviadoFromReplies(recs){const flags=new Set();\n  for(const r of recs){\n    const txt=norm(r.reply_text||'');\n    const rawReply=String(r.reply_text||'');\n    if(!txt&&!rawReply)continue;\n    if(/\\b(30m|1h|50\\s?€|100\\s?€|tarifa|precio|precios)\\b/.test(txt))flags.add('precios');\n    const hasMapLink=/(https?:\\/\\/)?(goo\\.gl\\/maps|maps\\.app\\.goo\\.gl|google\\.com\\/maps|maps\\.google\\.com)/.test(rawReply)||/@-?\\d{1,2}\\.\\d+,-?-?\\d{1,3}\\.\\d+/.test(rawReply);\n    if(/maps|ubicacion|direccion|calle|punto|ubi\\b|pin\\b/.test(txt)||hasMapLink){flags.add('ubicacion');if(hasMapLink)flags.add('ubicacion_precisa');}\n    const hasPhotoLink=/(https?:\\/\\/(?:ibb\\.co|i\\.ibb\\.co)\\/)/.test(rawReply);\n    const isRecentPhoto=withinHours(r.ts||'',6);\n    if(hasPhotoLink&&isRecentPhoto)flags.add('fotos');\n    if(/detalle|incluye|ofrezco|ofrece|servici/.test(txt))flags.add('servicios');\n  }\n  return Array.from(flags);\n}\n\nfunction detectTarifaElegida(recs){\n  for(let i=recs.length-1;i>=0;i--){\n    const raw=recs[i].user_msg||'';\n    const u=norm(raw);\n    if(!u)continue;\n    const hasAcepta=/\\b(vale|ok|de acuerdo|me vale|me cuadra|perfecto|cojo|quiero|me quedo|pillo|prefiero)\\b/.test(u);\n    const msgCorta=u.replace(/[^0-9a-z€ ]/g,'').trim();\n    const esMsgSimple=msgCorta.length>0&&msgCorta.length<=25;\n    const acepta=hasAcepta||esMsgSimple;\n    if(acepta&&(/\\b50\\s*(euros|eur|€)?\\b/.test(u)||/(media\\s*h|mediahora|media\\s+hora|30\\s*min)/.test(u)))return'50';\n    if(acepta&&(/\\b100\\s*(euros|eur|€)?\\b/.test(u)||/\\b(una\\s+hora|la\\s+hora|1h|60\\s*min)\\b/.test(u)))return'100';\n  }\n  return'';\n}\n\nfunction detectMapsSent(recs){const re=/(https?:\\/\\/)?(goo\\.gl\\/maps|maps\\.app\\.goo\\.gl|google\\.com\\/maps|maps\\.google\\.com)/i;for(const r of recs){if(re.test(String(r.reply_text||'')))return true;}return false;}\n\nfunction userWantsMoreGirls(txt){\n  const t=norm(txt);\n  if(!t) return false;\n  return /(mas\\s+chicas|todas|todas\\s+las\\s+chicas|otras\\s+chicas|que\\s+opciones|quienes\\s+hay|tienes\\s+mas|alguna\\s+mas|mas\\s+fotos\\s+de\\s+las\\s+chicas)/.test(t);\n}\n\nfunction userWantsMapWords(txt){\n  const t=norm(txt);\n  if(!t) return false;\n  return /(\\bubi\\b|ubic|maps\\b|mapa\\b|direccion|pin\\b|punto\\s+exacto|ubicacion\\s*real|pasame\\s+la\\s+ubi|pasa\\s+el\\s+maps|mandame\\s+la\\s+direccion)/.test(t);\n}\n\nfunction levLimit(a,b,limit){\n  a=String(a||'');b=String(b||'');\n  if(a===b) return 0;\n  const la=a.length, lb=b.length;\n  if(Math.abs(la-lb)>limit) return limit+1;\n  let prev=new Array(lb+1);let cur=new Array(lb+1);\n  for(let j=0;j<=lb;j++) prev[j]=j;\n  for(let i=1;i<=la;i++){\n    cur[0]=i;\n    let rowMin=cur[0];\n    const ca=a.charCodeAt(i-1);\n    for(let j=1;j<=lb;j++){\n      const cost=(ca===b.charCodeAt(j-1))?0:1;\n      const v=Math.min(prev[j]+1,cur[j-1]+1,prev[j-1]+cost);\n      cur[j]=v;\n      if(v<rowMin) rowMin=v;\n    }\n    if(rowMin>limit) return limit+1;\n    const tmp=prev;prev=cur;cur=tmp;\n  }\n  return prev[lb];\n}\n\nfunction findMentionedGirl(txt, activeGirls){\n  const t=norm(txt);\n  if(!t) return null;\n  const tokens = t.split(/\\s+/).filter(Boolean);\n  for(const g of (activeGirls||[])){\n    const name=String(g?.nombre||'').trim();\n    if(!name) continue;\n    const n=norm(name);\n    if(!n) continue;\n    const re=new RegExp('(^|[^a-z0-9])'+escapeRe(n)+'([^a-z0-9]|$)','i');\n    if(re.test(t)) return g;\n    const nParts=n.split(/\\s+/).filter(Boolean);\n    if(nParts.length>=2){\n      let ok=true;\n      for(const part of nParts){\n        const rep=new RegExp('(^|[^a-z0-9])'+escapeRe(part)+'([^a-z0-9]|$)','i');\n        if(!rep.test(t)){ok=false;break;}\n      }\n      if(ok) return g;\n    }\n    const base=nParts[0]||n;\n    if(base.length>=4){\n      for(const tok of tokens){\n        if(tok.length<3) continue;\n        const lim=(base.length<=6)?1:2;\n        if(levLimit(tok,base,lim)<=lim) return g;\n      }\n    }\n  }\n  return null;\n}\n\nfunction extractEtaMinutesFromText(txt){\n  const t=norm(txt);\n  if(!t) return 0;\n  const U='(?:min(?:utos?)?|miutos?|mins?|mnts?)';\n  let m=t.match(new RegExp('\\\\b(\\\\d{1,3})\\\\s*(?:-|a|hasta|y)\\\\s*(\\\\d{1,3})\\\\s*'+U+'\\\\b'));\n  if(m){const a=Number(m[1]);const b=Number(m[2]);if(Number.isFinite(a)&&Number.isFinite(b)){const v=Math.round((a+b)/2);if(v>=1&&v<=180)return v;}}\n  m=t.match(new RegExp('\\\\b(?:en|llego\\\\s*en|llegare\\\\s*en|llegaria\\\\s*en|tardo\\\\s*(?:unos)?|tardare\\\\s*(?:unos)?|tardaria\\\\s*(?:unos)?|estoy\\\\s*en)\\\\s*(\\\\d{1,3})\\\\s*'+U+'\\\\b'));\n  if(m){const v=Number(m[1]);if(Number.isFinite(v)&&v>=1&&v<=180)return v;}\n  m=t.match(new RegExp('\\\\b(\\\\d{1,3})\\\\s*'+U+'\\\\b'));\n  if(m){const v=Number(m[1]);if(Number.isFinite(v)&&v>=1&&v<=180)return v;}\n  return 0;\n}\n\nfunction countBotEmojiRecent(recent){\n  const reEmoji=/[❤️😘😉😏😂😅💕😊]/g;\n  let count=0;\n  const lastBot=recent.filter(r=>String(r.reply_text||'').trim()).slice(-4);\n  for(const r of lastBot){const s=String(r.reply_text||'');const m=s.match(reEmoji);if(m)count+=m.length;}\n  return count;\n}\n\nfunction countHaggleRecent(recent){\n  const re=/(rebaja|descuento|mejor\\s*precio|barat|hazme\\s*precio|ajusta|te\\s*doy\\s*\\d+|\\b70\\b|\\b80\\b|\\b90\\b|\\b60\\b)/i;\n  let c=0;\n  const lastUser=recent.slice(-10);\n  for(const r of lastUser){const u=String(r.user_msg||'');if(u&&re.test(u))c++;}\n  return c;\n}\n\nfunction detectConversationEndIntent(txt){\n  const t=norm(txt);\n  if(!t) return false;\n  return /(adios|hasta\\s*luego|hasta\\s*ahora|me\\s*voy|otro\\s*dia|luego\\s*hablamos|ya\\s*te\\s*digo|gracias\\s*y\\s*perdon|vale\\s*gracias|ok\\s*gracias)/.test(t);\n}\n\nfunction detectInteresFuerte(txt){\n  const t=norm(txt);\n  if(!t) return false;\n  return /(voy\\s*ya|voy\\s*para\\s*alla|salgo\\s*para\\s*alla|ahora\\s*voy|me\\s*paso\\s*ya|quiero\\s*ir\\s*ya|ahora\\s*mismo|en\\s*un\\s*rato\\s*voy|voy\\s*en\\s*\\d+)/.test(t);\n}\n\nfunction detectVisitBeforeChoice(txt){\n  const t=norm(txt);\n  if(!t) return false;\n  return /(puedo\\s*ver|ver\\s*antes|pasar\\s*a\\s*ver|echar\\s*un\\s*visto|presentacion|paseillo|pasillo|mirar\\s*opciones|conocer\\s*antes|verlos\\s*en\\s*persona|ver\\s*las\\s*masajistas|ver\\s*quien\\s+hay|puedo\\s*pasarme\\s*a\\s*mirar)/.test(t);\n}\n\nfunction buildRecentBotRepliesNorm(recent){\n  const arr=[];\n  for(let i=Math.max(0,(recent||[]).length-10);i<(recent||[]).length;i++){\n    const r=recent[i];\n    const b=String(r?.reply_text||'').trim();\n    if(!b)continue;\n    arr.push(norm(b));\n  }\n  return Array.from(new Set(arr.filter(Boolean))).slice(-8);\n}\n\nfunction asBool(x){\n  if(typeof x==='boolean') return x;\n  if(typeof x==='number') return x!==0;\n  if(typeof x==='string'){const s=x.trim().toLowerCase();return ['true','1','yes','y','si','sí'].includes(s);}\n  return false;\n}\n\n// ─── SPEAKER GIRL: primera chica mencionada histórica, NUNCA cambia ───────\nfunction firstPersistedSpeakerGirl(recs){\n  for(let i=0;i<(recs||[]).length;i++){\n    const r=recs[i]||{};\n    const n=String(r.speaker_girl_name||'').trim();\n    const id=String(r.speaker_girl_id||'').trim();\n    if(n) return {name:n,id:id};\n  }\n  return {name:'',id:''};\n}\n\n// ─── SELECTED GIRL: chica elegida para el servicio, puede cambiar ─────────\nfunction lastPersistedSelectedGirl(recs){\n  for(let i=(recs||[]).length-1;i>=0;i--){\n    const r=recs[i]||{};\n    const n=String(r.selected_girl_name||'').trim();\n    const id=String(r.selected_girl_id||'').trim();\n    if(n) return {name:n,id:id};\n  }\n  return {name:'',id:''};\n}\n\n// Intención explícita de elegir otra chica para el servicio\nfunction lastValidRouteGirl(recs){\n  for (let i = recs.length - 1; i >= 0; i--) {\n    const r = recs[i] || {};\n    const id = String(r.route_girl_id || '').trim();\n    const name = String(r.route_girl_name || '').trim();\n    const expiresAt = String(r.route_expires_at || '').trim();\n    if (!id && !name) continue;\n    if (expiresAt) {\n      const ms = Date.parse(expiresAt);\n      if (isFinite(ms) && ms < Date.now()) continue;\n    }\n    return {\n      id,\n      name,\n      expires_at: expiresAt\n    };\n  }\n  return {\n    id: '',\n    name: '',\n    expires_at: ''\n  };\n}\n  function isExplicitServiceChoice(txt){\n  const t=norm(txt);\n  if(!t) return false;\n  return /(quiero\\s+(ir\\s+con|a|con)|me\\s+quedo\\s+con|prefiero\\s+(a\\s+)?|reservo\\s+con|cita\\s+con|voy\\s+con|al\\s+final\\s+(me\\s+quedo|quiero)|me\\s+gusta\\s+mas|me\\s+mola\\s+mas|esta\\s+me\\s+gusta|con\\s+esta\\s+(quiero|me\\s+quedo|prefiero))/.test(t);\n}\n\nconst RAW=$json.memory_text_raw||'';\nconst phone=pickPhone();\nconst lines=RAW.split('\\n').filter(l=>l.trim().length>0);\nconst recs=[];\nfor(const l of lines){try{const o=JSON.parse(l);if(String(o.phone||'')===phone){if(typeof o.user_msg==='string'){o.user_msg=o.user_msg.replace(/^Cliente:\\s*/,'');}recs.push(o);}}catch(e){}}\nrecs.sort((a,b)=>((a._seq||0)-(b._seq||0)));\n\nconst last6h=recs.filter(r=>withinHours(r.ts||'',6));\nconst session_reset=last6h.length===0;\nlet recent=session_reset?[]:last6h.slice(-20);\n\nlet thread_id='';\nif(!session_reset){const latest=last6h[last6h.length-1];thread_id=String(latest.thread_id||('th-'+Date.now()));}\nelse{thread_id='th-'+Date.now();}\n\nconst hist=recent.map(r=>{\n  const u=(r.user_msg||'').slice(0,200);\n  const b=(r.reply_text||'').slice(0,200);\n  const ts=r.ts||'';\n  return'-['+ts+'] U: '+u+' | B: '+b;\n}).join('\\n');\n\nconst currentMsg=String($node['Extract WA Text']?.json?.message_text||'');\nconst topic_actual=detectTopic(currentMsg);\n\nlet lastUserMsg='';\nfor(let i=recs.length-1;i>=0;i--){const um=String(recs[i].user_msg||'').trim();if(um){lastUserMsg=um;break;}}\nconst last_topic=lastUserMsg?detectTopic(lastUserMsg):'otro';\nconst topic_cambiado=topic_actual!==last_topic&&last_topic!=='otro';\n\nconst ya_enviado=yaEnviadoFromReplies(recs);\nlet pendiente=null;\nif(topic_actual==='precios'&&!ya_enviado.includes('precios'))pendiente='precios';\nelse if(topic_actual==='ubicacion'&&!ya_enviado.includes('ubicacion'))pendiente='ubicacion';\nelse if(topic_actual==='servicios'&&!ya_enviado.includes('servicios'))pendiente='servicios';\n\nconst memory_text=hist||'Sin memoria reciente.';\nconst tone_reset=session_reset;\n\nlet last_bot_reply='';\nlet last_user_message='';\nfor(let i=recent.length-1;i>=0;i--){const r=recent[i];\n  if(!last_bot_reply&&r.reply_text&&String(r.reply_text).trim())last_bot_reply=String(r.reply_text);\n  if(!last_user_message&&r.user_msg&&String(r.user_msg).trim())last_user_message=String(r.user_msg);\n  if(last_bot_reply&&last_user_message)break;\n}\n\nlet last_user_meaningful='';\nfor(let i=recs.length-1;i>=0;i--){const um=String(recs[i].user_msg||'').trim();if(!um)continue;if(!isFillerUser(um)){last_user_meaningful=um;break;}}\nconst current_is_filler=isFillerUser(currentMsg);\n\nconst tarifa_elegida=detectTarifaElegida(recent);\nconst maps_sent=detectMapsSent(recent);\n\nlet girls_config=[];\ntry{ girls_config=$node['Girls Config (from remote JSON)']?.json?.girls_config; }catch(e){ girls_config=[]; }\nif(!Array.isArray(girls_config)){\n  girls_config=Array.isArray($json.girls_config)?$json.girls_config:[];\n}\n\ngirls_config=girls_config.map(g=>{\n  const o=(g&&typeof g==='object')?g:{};\n  return{\n    ...o,\n    id:String(o.id||'').trim(),\n    nombre:String(o.nombre||'').trim(),\n    activa:asBool(o.activa),\n    fotos:Array.isArray(o.fotos)?o.fotos:[]\n  };\n});\n\nconst activeGirls=girls_config.filter(g=>asBool(g.activa)&&String(g.nombre||'').trim());\n\nconst wants_more_girls=userWantsMoreGirls(currentMsg)||userWantsMoreGirls(last_user_meaningful);\n\n// ─── SELECCION: speaker_girl vs selected_girl ─────────────────────────────\n// speaker_girl : quien HABLA (la 1ª chica mencionada en la sesión, NUNCA cambia)\n// selected_girl: para qué chica es el servicio/maps (sticky, cambia solo con intención explícita)\n\nconst persistedSpeaker  = firstPersistedSpeakerGirl(recs);\nconst persistedSelected = lastPersistedSelectedGirl(recs);\nconst selInCurrent      = findMentionedGirl(currentMsg, activeGirls);\n\n// --- SPEAKER GIRL (absolutamente fija una vez establecida) ---\nlet speaker_girl_name = '';\nlet speaker_girl_id   = '';\n\nif(persistedSpeaker.name){\n  // Ya hay speaker girl persistida: NUNCA la cambiamos\n  speaker_girl_name = persistedSpeaker.name;\n  speaker_girl_id   = persistedSpeaker.id;\n} else {\n  // Primera sesión / sin speaker aún: detectar por primera mención (más antigua primero)\n  let firstSel = selInCurrent || findMentionedGirl(last_user_meaningful, activeGirls);\n  if(!firstSel){\n    for(let i=0;i<recent.length;i++){\n      const um=String(recent[i]?.user_msg||'');\n      const g=findMentionedGirl(um,activeGirls);\n      if(g){firstSel=g;break;}\n    }\n  }\n  speaker_girl_name = firstSel?String(firstSel.nombre||'').trim():'';\n  speaker_girl_id   = firstSel?String(firstSel.id||'').trim():'';\n}\n\n// --- SELECTED GIRL (chica para el servicio, sticky salvo intención explícita) ---\nlet selected_girl_name = '';\nlet selected_girl_id   = '';\n\nif(persistedSelected.name){\n  // Mantener selección previa por defecto\n  selected_girl_name = persistedSelected.name;\n  selected_girl_id   = persistedSelected.id;\n  // Solo cambia si el mensaje actual menciona OTRA chica con intención explícita de servicio\n  if(\n    selInCurrent &&\n    norm(selInCurrent.nombre) !== norm(persistedSelected.name) &&\n    isExplicitServiceChoice(currentMsg)\n  ){\n    selected_girl_name = String(selInCurrent.nombre||'').trim();\n    selected_girl_id   = String(selInCurrent.id||'').trim();\n  }\n} else {\n  // Sin selección previa: cualquier primera mención vale\n  let firstSel = selInCurrent || findMentionedGirl(last_user_meaningful, activeGirls);\n  if(!firstSel){\n    for(let i=recent.length-1;i>=0;i--){\n      const um=String(recent[i]?.user_msg||'');\n      const g=findMentionedGirl(um,activeGirls);\n      if(g){firstSel=g;break;}\n    }\n  }\n  selected_girl_name = firstSel?String(firstSel.nombre||'').trim():'';\n  selected_girl_id   = firstSel?String(firstSel.id||'').trim():'';\n}\n\n// speaker_mode se basa en speaker_girl (quien habla), no en selected_girl\nlet speaker_mode = 'encargada';\nif(speaker_girl_name){ speaker_mode = 'chica'; }\n// ─────────────────────────────────────────────────────────────────────────\n\nlet photos_sent_recent=false;\nfor(let i=recent.length-1;i>=0;i--){\n  const rr=String(recent[i]?.reply_text||'');\n  if(/https?:\\/\\/(?:ibb\\.co|i\\.ibb\\.co)\\//i.test(rr)&&withinHours(recent[i]?.ts||'',6)){photos_sent_recent=true;break;}\n}\n\nconst must_choose_girl_now=(!selected_girl_name)&&(userWantsMapWords(currentMsg)||topic_actual==='ubicacion'||topic_actual==='cita/eta');\n\nlet choose_loop_count=0;\nif(!selected_girl_name){\n  for(let i=recent.length-1;i>=0;i--){\n    const um=String(recent[i]?.user_msg||'');\n    if(!um.trim())continue;\n    if(userWantsMapWords(um)||detectTopic(um)==='ubicacion')choose_loop_count++;\n    else break;\n  }\n}\n\nconst eta_from_user_minutes=extractEtaMinutesFromText(currentMsg);\nconst eta_from_user_flag=eta_from_user_minutes>0;\n\nconst emoji_count_recent=countBotEmojiRecent(recent);\nconst haggle_count_recent=countHaggleRecent(recent);\nconst conversation_end_intent=detectConversationEndIntent(currentMsg);\nconst interes_fuerte=detectInteresFuerte(currentMsg)||topic_actual==='cita/eta';\nconst ubicacion_pedida_fuerte=userWantsMapWords(currentMsg)||topic_actual==='ubicacion';\n\nconst recent_saludo=(function(){\n  const t=norm(currentMsg);\n  if(!t) return false;\n  const isHello=/^(hola|holaa+|buenas|hey|ola)\\b/.test(t)||/\\b(hola|buenas)\\b/.test(t);\n  if(!isHello) return false;\n  return !String(last_bot_reply||'').trim();\n})();\n\nconst sales_stage=(function(){\n  if(maps_sent&&eta_from_user_flag)return'lead';\n  if(maps_sent)return'eta';\n  if(must_choose_girl_now)return'seleccion';\n  if(tarifa_elegida)return'tarifas_cerradas';\n  if(topic_actual==='precios')return'tarifas';\n  if(topic_actual==='ubicacion')return'ubicacion';\n  if(topic_actual==='servicios')return'servicios';\n  return'info';\n})();\n\nconst wants_visit_before_choice=detectVisitBeforeChoice(currentMsg)||detectVisitBeforeChoice(last_user_meaningful);\nconst recent_bot_replies_norm=buildRecentBotRepliesNorm(recent);\n\nreturn [{\n  ...$json,\n  memory_text,\n  thread_id,\n  tone_reset,\n  topic_actual,\n  topic_cambiado,\n  ya_enviado,\n  pendiente,\n  session_reset,\n  last_bot_reply,\n  last_user_message,\n  tarifa_elegida,\n  maps_sent,\n  last_user_meaningful,\n  current_is_filler,\n  girls_config,\n  activeGirls,\n  speaker_girl_id,\n  speaker_girl_name,\n  selected_girl_id,\n  selected_girl_name,\n  speaker_mode,\n  wants_more_girls,\n  photos_sent_recent,\n  must_choose_girl_now,\n  choose_loop_count,\n  eta_from_user_minutes,\n  eta_from_user_flag,\n  emoji_count_recent,\n  haggle_count_recent,\n  recent_saludo,\n  sales_stage,\n  conversation_end_intent,\n  interes_fuerte,\n  ubicacion_pedida_fuerte,\n  wants_visit_before_choice,\n  recent_bot_replies_norm\n}];"
      },
      "id": "c0fe0d5c-ff0b-426c-998c-64190765661f",
      "name": "Format Memory",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -560,
        112
      ]
    },
    {
      "parameters": {
        "functionCode": "const b=(items[0]?.binary?.data?.data)||'';let t='';try{if(b)t=Buffer.from(b,'base64').toString('utf8');}catch(e){t='';}return[{...$json,memory_text_raw:(t||'')}];"
      },
      "id": "bc8a7122-343a-481f-a941-e793a5b9ced8",
      "name": "Bin2Text Memory",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -704,
        112
      ]
    },
    {
      "parameters": {
        "filePath": "[LAMAMI_SESSION_MEMORY_FILE]"
      },
      "id": "15611934-b8a5-40fe-9dcf-e501152b8653",
      "name": "Read Memory",
      "type": "n8n-nodes-base.readBinaryFile",
      "typeVersion": 1,
      "position": [
        -832,
        112
      ]
    },
    {
      "parameters": {
        "functionCode": "const b=(items[0]?.binary?.data?.data)||'';\nlet t='';\ntry{ if(b) t=Buffer.from(b,'base64').toString('utf8'); }catch(e){ t=''; }\n\n// PATCH 6/6: limpiar playbook para evitar que meta \"Ventana reciente\" / pseudo-memoria\n// Nos quedamos con la parte \"destilada\" de estilo/plantillas y cortamos desde \"## Ventana reciente\" si existe.\nlet cleaned = String(t||'');\nif (cleaned) {\n  const cut = cleaned.split(/\\n##\\s+Ventana\\s+reciente\\b/i);\n  cleaned = cut[0] || cleaned;\n  // recorte suave de tamaño para evitar prompts gigantes\n  if (cleaned.length > 12000) cleaned = cleaned.slice(0, 12000);\n}\n\nreturn [{...$json, playbook_text: (cleaned || '')}];"
      },
      "id": "48f03ce5-c59f-4b6f-930c-333b68e4a347",
      "name": "Bin2Text Playbook",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -960,
        112
      ]
    },
    {
      "parameters": {
        "filePath": "/data/corpus/playbook.md"
      },
      "id": "eb0c1576-153e-4bd9-8c84-f5e759db30d3",
      "name": "Read Playbook",
      "type": "n8n-nodes-base.readBinaryFile",
      "typeVersion": 1,
      "position": [
        -1104,
        112
      ]
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "name": "prompt_text",
              "type": "string",
              "value": "### ROL\nEres el asistente de un negocio de citas que atiende por WhatsApp como si fueses la propia chica/negocio. Nunca debes reconocer que eres un bot o chica virtual o similares, debes inventar una excusa si preguntan. Tu objetivo principal es convertir conversaciones en citas reales sin oler a bot: sonar humana, lenguaje de barrio, caliente, y respetar las reglas que vienen despues sobre servicios, tarifas, ubicacion y seguridad.\nDebes dar prioridad a la conversión de las citas de forma inmediata siguiendo el siguiente método de los pasos necesarios para la obtención de cita o lead de forma inmediata, conseguir que venga hacia la casa de camino. \n\nHablas como chica latina de barrio, cercana, y algo malhablada, pero sin insultar, usando jerga sexual:\n- Lenguaje directo, sencillo, y muy pícaro.\n- Debes tener en cuenta que el interlocutor es gente de bajo nivel intelectual y a veces habla en primera persona refiriendose a la tercera, por ejemplo: \"envio foto\" o \"envio ubicacion\" se refiere en verdad a que envies fotos o ubicacion. Debes intentar entender que dice el interlocutor teniendo en cuenta el contexto de la conversación debido a su bajo nivel intelectual. El interlocutor hablará en lenguaje muy vulgar, debes entender en cada momento a qué se refiere en base al contexto de la conversación. Si no sabes que decir por que no lo entiendes, sal del paso con una frase graciosa y risas y emojis.\n- Además eres un especialista en ventas y marketing y psicología humana.\n- Tienes altos conocimientos en psicología y sabes como redirigir una conversación a tus intereses.\n- Usa siempre frases cortas y sencillas, responde lo más escueto posible para parecer más humano. Responde únicamente a lo que preguntan sin adelantarte a los acontecimientos, tienes prohibido preguntar por ejemplo si quiere saber tarifas o chicas etc sin que el interlocutor lo haya preguntado.\n- Sin signos de apertura (ni ¿ ni ¡), solo ? al final cuando toque.\n- Evita tildes en casi todas las palabras; si dudas, sin tilde (\"como\", \"estas\", \"carino\", \"donde\", \"cuando\", \"cuanto\" casi siempre sin tilde).\n- Puedes usar abreviaturas suaves tipo \"pa\", \"q\" sin abusar.\n- \"toy\"/\"toi\" estan PROHIBIDOS siempre (cambia por \"estoy\").\n\n### NATURALIDAD EN LA APERTURA\nMuchos clientes empiezan con cosas tipo \"te he visto en destacamos\", \"he visto tu anuncio\", \"me interesa el anuncio X\".\nTrátalo SIEMPRE como un saludo/apertura normal:\n- Responde con saludo + frase corta natural.\n- NO empieces siempre con un menu tipo \"que te apetece saber primero, fotos, tarifas o ubicacion, o que chica prefieres\".\n\n### DISPONIBILIDAD\nSi el cliente pregunta por cuando estas o por disponibilidad:\n- Tu prioridad es CONTESTAR a la disponibilidad, no sacar un menu.\n- Di algo concreto tipo \"ahora estoy disponible o dentro de un ratito, segun te venga\".\n- En ese primer mensaje NO saques menus rigidos. Primero disponibilidad, luego ya fotos/tarifas si las pide.\n\n### EVITAR REPETIR SALUDO EN LOS PRIMEROS MENSAJES\n- Si en el historial ya hay al menos una linea con \"B:\", ya has saludado. PROHIBIDO empezar otra vez por \"hola\", \"buenas\" o variantes.\n- Si last_bot_reply empieza por \"hola\" o similar, tu siguiente respuesta NO puede empezar por \"hola\". Entra directo al contenido.\n\n### MULTIPLES PREGUNTAS EN UN MISMO MENSAJE\nCuando veas varias preguntas en el mismo mensaje, responde a TODAS de forma breve en un mismo mensaje. NUNCA respondas con cosas genericas tipo \"no se a que te refieres, dime que te apetece saber\".\n\n### FRASES PROHIBIDAS\n- No uses \"no se a que te refieres\" ni variantes. Si tienes dudas, usa last_user_meaningful para deducir, o pregunta aclarando con opciones concretas.\n\n### EVITAR MENUS RIGIDOS\n- Frases tipo \"que te apetece saber, fotos, tarifas o ubicacion\" estan PROHIBIDAS.\n- Si el cliente pide fotos: si no ha elegido chica, pasas la primera foto de cada chica activa. Si ya eligio, pasas todas las fotos de esa chica.\n- Si el cliente pide info genérica: manda lo que mas encaje segun el momento de la conversacion.\n\n### RESPONDER A LO QUE DICE\nTu prioridad es contestar a lo que dice el cliente, no reiniciar siempre con \"que buscas\". Si ya dijo que quiere venir, deja de preguntarle; toca elegir chica (si no esta elegida) y luego ubicacion/cierre.\n\n### UBICACION Y MAPA (REGLA DURA: NUNCA MAPS SIN ELECCION)\nTienes un enlace exacto de ubicacion (location_url) que te da el sistema.\n- Si selected_girl_name esta VACIO: PROHIBIDO mandar location_url. Solo zona general (\"[LAMAMI_ZONA]\") y UNA pregunta para que elija chica.\n- Si selected_girl_name NO esta vacio y hay intencion de ir: mandas location_url en ese mismo mensaje.\n- Una vez pasado el maps, insiste en cuanto tarda en llegar (ETA). Antes de pasarlo, no insistas en cuando quiere venir.\n- \"dime cuando tengas el mapa\" SOLO se puede usar en mensajes donde mandas location_url.\nVariaciones mal escritas de \"ubicacion\" (\"ubicasio\", \"ubicazion\") interpretalas como ubicacion.\n\n### APROVECHAR RESPUESTAS CORTAS\n- Si el cliente responde solo \"si\", \"vale\", \"ok\" o emojis, interpretalo como respuesta a tu ultima pregunta concreta.\n- Intentar enviar toda la respuesta en un solo parrafo. Mas natural, mas humano.\n\n### EMOJIS Y TONO\n- Maximo 2 emoji por mensaje. Usa emojis cada 2-3 mensajes.\n\n### MEMORIA PARA TARIFAS, FOTOS Y UBICACION\n- Si ya_enviado incluye 'precios': PROHIBIDO volver a ofrecer tarifas como si fuera la primera vez.\n- Si maps_sent es true: no vuelvas a ofrecer \"te paso el mapa\".\n- Si la chica ya ha sido elegida, no ofrecer mas chicas salvo que el cliente lo pida explicitamente.\n\n### SERVICIOS Y PRESERVATIVO (PRIORIDAD MUY ALTA)\n- Cuando pregunten por servicios de forma generica, responde con el pack base SIN mencionar preservativo ni goma.\n- Solo hablas de preservativo cuando el cliente lo pida claramente. Todos los servicios son con preservativo excepto en la tarifa de 1h puede ser frances natural.\n\n### BESOS, FIESTA BLANCA Y DROGA (PRIORIDAD MUY ALTA)\n- Besos y frances natural: NO son servicio garantizado, se deciden en persona.\n- Fiesta blanca o drogas: en la casa no se vende nada. El cliente lo puede traer por su cuenta.\n\n### REGLAS PARA FOTOS (PRIORIDAD ALTA)\n- Usa SIEMPRE girls_config para fotos y nombres. No inventes chicas ni enlaces.\n- Si selected_girl_name NO esta vacio: manda todas las fotos de ESA chica en ese mismo mensaje, cada enlace en linea separada.\n- Si wants_more_girls=true: puedes mandar catalogo de todas las activas.\n\n### FORMATO DE RESPUESTA\n- Respuestas cortas, maximo 1 parrafo. Sin preguntas innecesarias. Responde solo lo que te preguntan.\n\n### TARIFAS (PRIORIDAD MAXIMA)\n- Tarifas UNICAS: [LAMAMI_TARIFAS]\n- No se aceptan otros regateos ni otras tarifas\n- No se hacen salidas, solo se atiende en el local.\n\n### LEAD DETECTION (REGLA DURA - PRIORIDAD MAXIMA)\n- lead_detected=true SOLO si maps_sent=true O en este mismo mensaje incluyes location_url.\n- Y ADEMAS el cliente ya dio una ETA clara en minutos (eta_minutes > 0 y eta_minutes < 21).\n- Si falta mapa o falta ETA: lead_detected=false, lead_confidence=0.\n- Cuando el lead sea detectado: dile que espere en la ubicacion que le pasaste y avise al llegar.\n\n### INSTRUCCIONES DE SALIDA\nDevuelve SOLO un JSON con esta forma exacta sin texto extra: {\"lead_detected\": boolean, \"lead_confidence\": number, \"eta_minutes\": number|null, \"user_visible_reply\": string}.\n"
            }
          ]
        },
        "options": {}
      },
      "id": "ca240c6e-8a30-4dbb-8e32-431b2b4062f3",
      "name": "Set Prompt",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [
        -1248,
        192
      ]
    },
    {
      "parameters": {
        "fileName": "/data/wa_last_raw.json",
        "options": {}
      },
      "id": "dec61da3-2c0c-48d9-82d0-84a7f8b8f51d",
      "name": "Write Raw Payload",
      "type": "n8n-nodes-base.writeBinaryFile",
      "typeVersion": 1,
      "position": [
        -1600,
        432
      ]
    },
    {
      "parameters": {
        "functionCode": "function B(x){return Buffer.from(x,'utf8').toString('base64')}const raw=JSON.stringify($json,null,2);return[{binary:{data:{data:B(raw)}}}];"
      },
      "id": "9f216e80-5768-4d5c-9d3d-8062a90904b2",
      "name": "Raw Dump -> B64",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -1600,
        256
      ]
    },
    {
      "parameters": {
        "url": "https://casawasap.com/girlsconf_[LAMAMI_NOMBRE_BOT]/data/girls.json",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "accept",
              "value": "application/json"
            }
          ]
        },
        "options": {
          "response": {},
          "timeout": 10000
        }
      },
      "id": "4ef18b09-ec34-42d0-bdfe-1843d2a6793a",
      "name": "Fetch Girls JSON",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        -1440,
        48
      ]
    },
    {
      "parameters": {
        "functionCode": "function asBool(x){\n  if(typeof x==='boolean') return x;\n  if(typeof x==='number') return x!==0;\n  if(typeof x==='string'){\n    const s=x.trim().toLowerCase();\n    return ['true','1','yes','y','si','sí'].includes(s);\n  }\n  return false;\n}\n\n// Item previo del flujo (para no perder contexto)\nlet prev = {};\ntry { prev = $node['Gate: Blacklist WS'].json || {}; } catch(e) { prev = {}; }\n\n// Respuesta del HTTP\nconst res = ($json && typeof $json === 'object') ? $json : {};\n\n// Formato esperado: { girls: [ ... ] }\nlet girls = [];\nif (Array.isArray(res.girls)) girls = res.girls;\nelse if (Array.isArray(res.girls_config)) girls = res.girls_config;\nelse if (Array.isArray(res)) girls = res;\n\n// Normaliza al formato interno que ya usa tu flujo\nconst girls_config = (girls || []).map(g => {\n  const o = (g && typeof g === 'object') ? g : {};\n  return {\n    id: String(o.id || '').trim(),\n    nombre: String(o.nombre || '').trim(),\n    descripcion_corta: String(o.descripcion_corta || '').trim(),\n    fotos: Array.isArray(o.fotos) ? o.fotos.map(x => String(x||'').trim()).filter(Boolean) : [],\n    activa: asBool(o.activa)\n  };\n});\n\nreturn [{\n  ...prev,\n  girls_config,\n  __girls_source: 'remote_girls.json'\n}];"
      },
      "id": "4e779ec7-ae2f-4275-9fb6-a44fd789b796",
      "name": "Girls Config (from remote JSON)",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -1280,
        48
      ]
    },
    {
      "parameters": {
        "functionCode": "function pickTextWA(m){\n if(!m||typeof m!=='object')return'';\n if(m.text&&typeof m.text.body==='string')return m.text.body;\n if(typeof m.text==='string')return m.text;\n if(m.button&&typeof m.button.text==='string')return m.button.text;\n if(m.reaction&&typeof m.reaction.emoji==='string')return m.reaction.emoji;\n if(m.interactive&&m.interactive.type){\n const t=m.interactive.type;\n const o=m.interactive[t];\n if(o&&typeof o.title==='string')return o.title;\n if(o&&typeof o.id==='string')return o.id;\n }\n if(typeof m.body==='string')return m.body;\n if(typeof m.message==='string')return m.message;\n if(typeof m.caption==='string')return m.caption;\n return'';\n}\nfunction normalizePhone(raw){if(!raw)return'';return String(raw).replace(/[^0-9]/g,'');}\nfunction asTrueFlag(x){\n if(x===true)return true;\n if(x===1)return true;\n if(typeof x==='number')return x!==0;\n if(typeof x==='string'){\n const s=x.trim().toLowerCase();\n return s==='true'||s==='1'||s==='yes'||s==='y'||s==='si'||s==='sí';\n }\n return false;\n}\n\nfunction detectAudioFrom(src,msg,payload){\n try{\n const body = (src && src.body && typeof src.body==='object') ? src.body : {};\n const p = (payload && typeof payload==='object') ? payload : ((body.payload && typeof body.payload==='object') ? body.payload : ((src.payload && typeof src.payload==='object') ? src.payload : {}));\n const d = (p && typeof p._data==='object') ? p._data : {};\n\n const info = (d && typeof d.Info==='object') ? d.Info : {};\n const msgObj = (d && typeof d.Message==='object') ? d.Message : {};\n const audioMsg = (msgObj && typeof msgObj.audioMessage==='object') ? msgObj.audioMessage : null;\n const media = (p && typeof p.media==='object') ? p.media : null;\n\n let type = String(\n (msg && msg.type) ||\n (p && p.type) ||\n (d && d.type) ||\n ''\n ).toLowerCase();\n\n if(!type){\n const mt = String(info.MediaType || info.Type || '').toLowerCase();\n if(mt) type = mt;\n }\n\n const typeSaysAudio = ['audio','ptt','voice','voice_note','voicenote','voice-message','voice_message'].includes(type);\n if(typeSaysAudio) return true;\n\n const mt = String(\n (msg && (msg.mimetype || msg.mimeType || msg.mime_type)) ||\n (p && (p.mimetype || p.mimeType || p.mime_type)) ||\n (d && (d.mimetype || d.mimeType || d.mime_type)) ||\n (media && (media.mimetype || media.mimeType || media.mime_type)) ||\n (audioMsg && (audioMsg.mimetype || audioMsg.mimeType || audioMsg.mime_type)) ||\n ''\n ).toLowerCase();\n\n if(mt.startsWith('audio/')) return true;\n if(mt.includes('audio/')) return true;\n\n const hasMedia = !!(\n (msg && msg.hasMedia===true) ||\n (p && p.hasMedia===true) ||\n (d && d.hasMedia===true) ||\n (!!media) ||\n (!!audioMsg)\n );\n\n const isPtt = !!(\n (msg && msg.ptt===true) ||\n (p && p.ptt===true) ||\n (d && d.ptt===true) ||\n (audioMsg && audioMsg.PTT===true)\n );\n\n if(hasMedia && isPtt) return true;\n\n const urlish = String(\n (media && (media.url || media.URL)) ||\n (audioMsg && (audioMsg.URL || audioMsg.url || audioMsg.directPath)) ||\n ''\n ).toLowerCase();\n\n if(urlish && (urlish.includes('.oga') || urlish.includes('.ogg') || urlish.includes('audio'))) return true;\n\n return false;\n }catch(e){\n return false;\n }\n}\n\nfunction detectImageFrom(src,msg,payload){\n try{\n const body = (src && src.body && typeof src.body==='object') ? src.body : {};\n const p = (payload && typeof payload==='object') ? payload : ((body.payload && typeof body.payload==='object') ? body.payload : ((src.payload && typeof src.payload==='object') ? src.payload : {}));\n const d = (p && typeof p._data==='object') ? p._data : {};\n\n // 1) MediaType directo de WAHA (el más fiable)\n const info = (d && typeof d.Info==='object') ? d.Info : {};\n const mediaType = String(info.MediaType || info.Type || '').toLowerCase();\n\n // 2) Mimetype del objeto media\n const mediaObj = (p && typeof p.media==='object') ? p.media : {};\n const mt = String(mediaObj.mimetype || mediaObj.mimeType || mediaObj.mime_type || '').toLowerCase();\n\n // 3) Estructura imageMessage\n const messageObj = (d && typeof d.Message==='object') ? d.Message : {};\n const hasImageMessage = !!(messageObj.imageMessage && typeof messageObj.imageMessage === 'object');\n\n // 4) Flags generales\n const hasMedia = !!(p && p.hasMedia === true);\n const typeTop = String((msg && msg.type) || (p && p.type) || '').toLowerCase();\n\n const isImageByType = mediaType === 'image' || typeTop === 'image' || typeTop === 'photo' || typeTop === 'sticker' || hasImageMessage;\n const isImageByMime = mt.startsWith('image/') || mt.includes('image/');\n\n const isImage = isImageByType || isImageByMime || hasImageMessage;\n\n // Si tiene texto/caption → NO es imagen pura\n const hasText = !!pickTextWA(msg || p || {}).trim();\n\n return isImage && !hasText;\n }catch(e){\n return false;\n }\n}\n\nconst src=$json||{};\nconst wBody=src.body||{};\nconst event=String((wBody.event||src.event||'')).toLowerCase();\nconst payload=wBody.payload||src.payload||{};\nlet msg=null;\n\nif(event==='message'&&payload&&typeof payload==='object'){msg=payload;}\nif(!msg&&Array.isArray(src.messages)&&src.messages.length){msg=src.messages[0];}\nif(!msg&&Array.isArray(src.entry)&&src.entry.length){\n const changes=((src.entry[0]||{}).changes)||[];\n for(const ch of changes){\n const v=(ch&&ch.value)||{};\n if(Array.isArray(v.messages)&&v.messages[0]){msg=v.messages[0];break;}\n }\n}\nif(!msg&&typeof src.event==='string'&&src.event.toLowerCase()==='message'&&src.payload){msg=src.payload;}\n\nlet from='';let text='';let wamid='';let chatIdIn='';\nconst coalescedText=String(src.__coalesced_text||'').trim();\n\nif(msg){\n text=String(pickTextWA(msg)||'').trim();\n if(!text&&typeof payload.body==='string') text=payload.body.trim();\n}\nif(coalescedText) text=coalescedText;\nif(!text){\n if(typeof src.message==='string') text=src.message.trim();\n else if(src.text&&typeof src.text==='string') text=src.text.trim();\n else if(src.text&&src.text.body) text=String(src.text.body).trim();\n}\nif(!text&&typeof src.body==='string') text=src.body.trim();\nif(!text&&src.query&&(src.query.body||src.query.message)) text=String(src.query.body||src.query.message||'').trim();\n\nconst isAudioBool = asTrueFlag(src.__is_audio) || detectAudioFrom(src, msg, payload);\nconst is_audio_i = isAudioBool ? 1 : 0;\nconst is_audio = (is_audio_i===1);\n\nconst isImageBool = detectImageFrom(src, msg, payload);\nconst is_image_i = isImageBool ? 1 : 0;\nconst is_image = (is_image_i===1);\n\nlet message_type='';\ntry{\n message_type=String((msg&&msg.type)||(payload&&payload.type)||'').toLowerCase();\n}catch(e){}\nif(!message_type && is_audio) message_type = 'audio';\nif(!message_type && is_image) message_type = 'image';\n\nif(msg){\n const rawFrom=msg.from||(src.contacts&&src.contacts[0]&&(src.contacts[0].wa_id||src.contacts[0].id))||payload.from||'';\n from=normalizePhone(rawFrom);\n wamid=String(msg.id||payload.id||'');\n let rawChatId='';\n if(event==='message'){rawChatId=msg.chatId||msg.from||payload.chatId||payload.from||'';}\n else{rawChatId=msg.chatId||msg.from||'';}\n if(!rawChatId&&src.query&&(src.query.chatId||src.query.from)) rawChatId=src.query.chatId||src.query.from;\n if(rawChatId){\n if(typeof rawChatId==='string'&&rawChatId.includes('@')) chatIdIn=rawChatId;\n else{\n const d2=String(rawChatId).replace(/[^0-9]/g,'');\n if(d2) chatIdIn=d2+'@c.us';\n }\n }\n}\n\nif(!from&&src.contacts&&src.contacts[0]&&src.contacts[0].wa_id) from=normalizePhone(src.contacts[0].wa_id);\nif(!from&&src.query&&src.query.from) from=normalizePhone(src.query.from);\nif(!from&&src.from) from=normalizePhone(src.from);\nif(!wamid&&payload&&payload.id) wamid=String(payload.id);\n\nreturn [{\n ...$json,\n __is_audio: !!isAudioBool,\n is_audio,\n is_audio_i,\n __is_image: !!isImageBool,\n is_image,\n is_image_i,\n from_phone: from,\n message_text: text,\n wamid: wamid,\n waha_chat_id_in: chatIdIn,\n message_type: message_type\n}];"
      },
      "id": "193fcafb-2e24-497d-a967-96f0ccf3eeb4",
      "name": "Extract WA Text",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -1584,
        112
      ]
    },
    {
      "parameters": {
        "conditions": {
          "number": [
            {
              "value1": "={{ Number($node[\"Extract WA Text\"].json.is_image_i || 0) }}",
              "operation": "equal",
              "value2": 1
            }
          ]
        }
      },
      "id": "c7f21214-2a25-49b7-a58b-5953d8de77b7",
      "name": "IF Is Pure Image?",
      "type": "n8n-nodes-base.if",
      "typeVersion": 1,
      "position": [
        -1456,
        112
      ]
    },
    {
      "parameters": {
        "functionCode": "const src = $json || {};\nconst body = (src.body && typeof src.body === 'object') ? src.body : {};\nconst ev = String(body.event || src.event || '').toLowerCase();\n\n// WAHA: los mensajes llegan como body.event='message' y body.payload\nconst payload = (body.payload && typeof body.payload === 'object')\n  ? body.payload\n  : ((src.payload && typeof src.payload === 'object') ? src.payload : null);\n\n// Si viene event, respetalo\nif (ev) {\n  // No es mensaje => cortar\n  if (ev !== 'message') return [];\n  // Es mensaje y hay payload => PASA SIEMPRE (aunque no haya src.messages)\n  if (payload) return [{ ...src }];\n}\n\n// Fallback legacy\nconst hasMessages = Array.isArray(src.messages) && src.messages.length > 0;\nconst hasContacts = Array.isArray(src.contacts) && src.contacts.length > 0;\nconst hasStatuses = Array.isArray(src.statuses) && src.statuses.length > 0;\nconst hasEventTop = (typeof src.event === 'string' && src.event.length > 0);\n\n// Si hay payload aunque no haya messages[] también es mensaje\nconst hasPayload = !!payload;\n\nif (hasMessages || hasContacts || hasPayload || hasEventTop) {\n  return [{ ...src }];\n}\n\n// Si solo hay statuses (y nada más), fuera\nif (hasStatuses) return [];\n\nreturn [{ ...src }];"
      },
      "id": "614e71bf-ae4a-4535-ae3b-5842793556d5",
      "name": "Gate: Only contacts/messages",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -1744,
        112
      ]
    },
    {
      "parameters": {
        "functionCode": "function asBool(x){\n  if (typeof x==='boolean') return x;\n  if (typeof x==='number') return x!==0;\n  if (typeof x==='string') {\n    const s=x.trim().toLowerCase();\n    return ['true','1','yes','y','si','sí'].includes(s);\n  }\n  return false;\n}\n\nfunction digits(s){\n  const d = String(s||'').replace(/[^0-9]/g,'');\n  return d;\n}\n\nfunction firstNonEmpty(arr){\n  for (const v of arr) {\n    const s = String(v ?? '').trim();\n    if (s) return s;\n  }\n  return '';\n}\n\nfunction tryNodeJson(name){\n  try { return $node[name].json || {}; } catch(e){ return {}; }\n}\n\nconst leadDetected = asBool($json.lead_detected)\n  || (Number($json.lead_numeric)||0)===1\n  || String($json.lead_flag||'').trim()==='1';\n\nif (!leadDetected) return [];\n\n// --- Recupera contexto desde nodos anteriores (si este item ya viene \"pelado\") ---\nconst EXT = tryNodeJson('Extract WA Text');\nconst FM  = tryNodeJson('Format Memory');\nconst WH  = tryNodeJson('WAHA Webhook In');\nconst ANTI = tryNodeJson('Build WAHA Antiban');\n\nconst whBody = (WH.body && typeof WH.body==='object') ? WH.body : {};\nconst whPayload = (whBody.payload && typeof whBody.payload==='object') ? whBody.payload : (WH.payload || {});\n\n// phone: intenta from_phone, si no, saca dígitos de chatId/from\nconst phone = firstNonEmpty([\n  $json.from_phone,\n  EXT.from_phone,\n  digits($json.waha_chat_id),\n  digits($json.waha_chat_id_in),\n  digits(ANTI.waha_chat_id),\n  digits(whPayload.from),\n  digits(whPayload.chatId),\n  digits(whPayload.sender && whPayload.sender.id)\n]);\n\nconst thread = firstNonEmpty([\n  $json.thread_id,\n  FM.thread_id,\n  $json.thread,\n  ''\n]);\n\nconst msg = firstNonEmpty([\n  $json.user_message,\n  EXT.message_text,\n  $json.last_user_message,\n  FM.last_user_message,\n  ''\n]);\n\nconst reply = firstNonEmpty([\n  $json.output_text,\n  $json.waha_text,\n  $json.reply_text,\n  ''\n]);\n\nconst eta = Number($json.eta_minutes || FM.eta_from_user_minutes || 0);\nconst conf = Number($json.lead_confidence || 0);\n\nfunction sanitize(s){\n  try { return String(s).normalize('NFKD').replace(/[^\\x09\\x0A\\x0D\\x20-\\x7E]/g,''); }\n  catch(e){ return String(s).replace(/[^\\x09\\x0A\\x0D\\x20-\\x7E]/g,''); }\n}\n\nlet text = 'LEAD DETECTADO\\n[LAMAMI_NOMBRE_CLIENTA_LEAD]\\n'\n + 'Tel: ' + (phone || '') + '\\n'\n + 'ETA: ' + (eta || 0) + ' min\\n'\n + 'Conf: ' + (Number.isFinite(conf) ? conf : 0) + '\\n'\n + 'Thread: ' + (thread || '') + '\\n'\n + 'Msg: ' + (msg || '') + '\\n'\n + 'Reply: ' + (reply || '');\n\ntext = sanitize(text);\n\nconst CHAT_ID = '6755848011';\n\nreturn [{\n  ...$json,\n  from_phone: String(phone||''),\n  thread_id: String(thread||''),\n  user_message: String(msg||''),\n  output_text: String(reply||''),\n  eta_minutes: eta,\n  lead_confidence: conf,\n  telegram_text: text,\n  telegram_chat_id: CHAT_ID\n}];"
      },
      "id": "4549e277-9817-4112-bd75-3db6f578174f",
      "name": "Gate Lead → Telegram",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -880,
        912
      ]
    },
    {
      "parameters": {
        "chatId": "8481324328",
        "text": "={{ $json.telegram_text }}",
        "additionalFields": {}
      },
      "id": "989c06f4-ed09-4377-9e3f-5d1f1b9a9a4e",
      "name": "Telegram Alert (2)",
      "type": "n8n-nodes-base.telegram",
      "typeVersion": 1,
      "position": [
        -736,
        800
      ],
      "webhookId": "e2b3d5b6-3b12-4f0e-a5a4-8a8c3e3f1d9a",
      "credentials": {
        "telegramApi": {
          "id": "EUyv06uqo9fl2xqv",
          "name": "Telegram account"
        }
      }
    },
    {
      "parameters": {
        "chatId": "6755848011",
        "text": "={{ $json.telegram_text }}",
        "additionalFields": {}
      },
      "id": "45d628d1-fafa-4d12-b102-21291457c6c2",
      "name": "Telegram Alert",
      "type": "n8n-nodes-base.telegram",
      "typeVersion": 1,
      "position": [
        -736,
        656
      ],
      "webhookId": "50864e97-bbe1-4e0d-9797-d5f4fa97ea93",
      "credentials": {
        "telegramApi": {
          "id": "EUyv06uqo9fl2xqv",
          "name": "Telegram account"
        }
      }
    },
    {
      "parameters": {
        "command": "sh -lc 'if [ -f /data/.bot_mode_[LAMAMI_NOMBRE_BOT] ]; then cat /data/.bot_mode_[LAMAMI_NOMBRE_BOT]; else echo start; fi'"
      },
      "id": "cad7a737-5591-4a9a-8d5a-34630b4eea80",
      "name": "Read Bot Mode",
      "type": "n8n-nodes-base.executeCommand",
      "typeVersion": 1,
      "position": [
        -2416,
        32
      ]
    },
    {
      "parameters": {
        "functionCode": "const modeRaw = String($json.stdout || $json.bot_mode_[LAMAMI_NOMBRE_BOT] || '').trim().toLowerCase();\nconst bot_mode_[LAMAMI_NOMBRE_BOT] = (modeRaw === 'stop') ? 'stop' : 'start';\n\n// - pre: item de \"Set is_audio (pre-memory)\" con __is_audio / is_audio_i / message_type\nconst triggerItem = ($node[\"WAHA Webhook In\"] && $node[\"WAHA Webhook In\"].json) ? $node[\"WAHA Webhook In\"].json : {};\nconst pre = ($node[\"Set is_audio (pre-memory)\"] && $node[\"Set is_audio (pre-memory)\"].json) ? $node[\"Set is_audio (pre-memory)\"].json : {};\n\n// Mezcla: mantenemos el evento real (triggerItem) pero conservamos los flags ya calculados (pre)\nreturn [{\n  ...triggerItem,\n  ...pre,\n  bot_mode_[LAMAMI_NOMBRE_BOT]\n}];"
      },
      "id": "13085aad-11f1-4c23-a30c-cd502243a0aa",
      "name": "Parse Bot Mode",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -2416,
        192
      ]
    },
    {
      "parameters": {
        "functionCode": "const ev = $json || {};\n\nconst stdout = String(ev.stdout || '').trim();\nlet status = '';\nlet b64 = '';\nlet srcB64 = '';\n\nif (stdout) {\n  const lines = stdout.split('\\n');\n  for (const line of lines) {\n    const l = String(line || '').trim();\n    if (l.startsWith('STATUS=')) status = l.slice(7).trim().toUpperCase();\n    if (l.startsWith('COALESCE_B64=')) b64 = l.slice(13).trim();\n    if (l.startsWith('SRC_B64=')) srcB64 = l.slice(8).trim();\n  }\n}\n\n// 1) Fuente PRINCIPAL: el item real reconstruido (si existe)\nlet src = null;\nif (srcB64) {\n  try {\n    src = JSON.parse(Buffer.from(srcB64, 'base64').toString('utf8'));\n  } catch (e) {\n    src = null;\n  }\n}\n\n// 2) Fallbacks (por si ejecutas nodos sueltos o falla SRC_B64)\nif (!src) {\n  try { src = $node[\"IF Bot Mode Start?\"].json; } catch(e) {}\n}\nif (!src) {\n  try { src = $node[\"Gate: Enabled + Blacklist\"].json; } catch(e) {}\n}\nif (!src) src = ev;\n\n// Adjuntamos info de dedup para debug si quieres verla\nsrc.__dedup_status = status || '';\n\nif (status === 'DUP' || status === 'SKIP') {\n  return [];\n}\n\nif (b64) {\n  try {\n    const merged = Buffer.from(b64, 'base64').toString('utf8');\n    src.__coalesced_text = String(merged || '').trim();\n  } catch (e) {}\n}\n\nfunction pickTextWA(m) {\n  if (!m || typeof m !== 'object') return '';\n  if (m.text && typeof m.text.body === 'string') return m.text.body;\n  if (typeof m.text === 'string') return m.text;\n  if (m.button && typeof m.button.text === 'string') return m.button.text;\n  if (m.reaction && typeof m.reaction.emoji === 'string') return m.reaction.emoji;\n  if (m.interactive && m.interactive.type) {\n    const t = m.interactive.type;\n    const o = m.interactive[t];\n    if (o && typeof o.title === 'string') return o.title;\n    if (o && typeof o.id === 'string') return o.id;\n  }\n  if (typeof m.body === 'string') return m.body;\n  if (typeof m.message === 'string') return m.message;\n  if (typeof m.caption === 'string') return m.caption;\n  return '';\n}\n\nfunction asTrueFlag(x){\n  if (x === true) return true;\n  if (x === 1) return true;\n  if (typeof x === 'number') return x !== 0;\n  if (typeof x === 'string') {\n    const s = x.trim().toLowerCase();\n    return s === 'true' || s === '1' || s === 'yes' || s === 'y' || s === 'si' || s === 'sí';\n  }\n  return false;\n}\n\n// --- AUDIO FLAG ROBUSTO ---\nlet audioDetected = asTrueFlag(src.__is_audio);\nif (!audioDetected) {\n  try {\n    const pre = $node[\"Set is_audio (pre-memory)\"]?.json || {};\n    audioDetected = audioDetected || asTrueFlag(pre.__is_audio) || asTrueFlag(pre.is_audio) || (Number(pre.is_audio_i || 0) === 1);\n  } catch (e) {}\n}\nsrc.__is_audio = !!audioDetected;\n\nconst sBody = (src && typeof src === 'object') ? (src.body || {}) : {};\nconst evName = String((sBody && sBody.event) || src.event || '').toLowerCase();\nconst payload = (sBody && typeof sBody.payload === 'object') ? sBody.payload : (src.payload || {});\nconst hasPayload = !!(payload && typeof payload === 'object' && Object.keys(payload).length > 0);\n\n// --- SI ES MENSAJE WAHA (event=message + payload), NUNCA LO CORTES POR \"TEXTO VACIO\" ---\n// Esto arregla tu caso: mensajes de media/sticker/imagen o payload sin body => antes se iba a [].\nif (evName === 'message' && hasPayload) {\n  // si viene coalesce vacío, igualmente dejamos pasar\n  return [src];\n}\n\nlet msg = null;\nif (Array.isArray(src.messages) && src.messages.length) {\n  msg = src.messages[0];\n}\nif (!msg && Array.isArray(src.entry) && src.entry.length) {\n  const changes = ((src.entry[0] || {}).changes) || [];\n  for (const ch of changes) {\n    const v = (ch && ch.value) || {};\n    if (Array.isArray(v.messages) && v.messages[0]) {\n      msg = v.messages[0];\n      break;\n    }\n  }\n}\nif (!msg && payload && typeof payload === 'object') {\n  msg = payload;\n}\n\nlet text = '';\nif (src.__coalesced_text && String(src.__coalesced_text).trim()) {\n  text = String(src.__coalesced_text).trim();\n} else if (msg) {\n  text = String(pickTextWA(msg) || '').trim();\n}\n\nif (!text && typeof payload.body === 'string') text = payload.body.trim();\nif (!text && typeof src.message === 'string') text = src.message.trim();\nelse if (!text && src.text && typeof src.text === 'string') text = src.text.trim();\nelse if (!text && src.text && src.text.body) text = String(src.text.body).trim();\nif (!text && typeof src.body === 'string') text = src.body.trim();\nif (!text && src.query && (src.query.body || src.query.message)) text = String(src.query.body || src.query.message || '').trim();\n\n// Si es audio, NO cortamos por \"texto vacio\"\nif (audioDetected) {\n  return [src];\n}\n\n// Filtro de vacios/ruido (solo para texto) — pero aquí ya NO es WAHA event=message\nif (typeof text === 'string') {\n  const t = text.trim();\n  if (!t) return [];\n  if (t.length === 1) return [];\n\n  const compact = t.replace(/\\s+/g, '');\n  const hasLettersOrDigits = /[0-9A-Za-z]/.test(compact);\n  if (!hasLettersOrDigits && compact.length > 0 && compact.length <= 8) return [];\n}\n\nreturn [src];"
      },
      "id": "2e47b2aa-5e9a-4748-a4ed-4c6398ecd7d5",
      "name": "Early Dedup Gate",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -1904,
        208
      ]
    },
    {
      "parameters": {
        "command": "={{ (function() {\n  try {\n    const b = $json.body || {};\n    const p = b.payload || {};\n\n    const id = (p.id || p.wamid || p.messageId || '').toString();\n    const ts = (p.timestamp || b.timestamp || '').toString();\n    const from = (p.from || p.chatId || (p.sender && p.sender.id) || '').toString();\n\n    // Intentamos sacar texto del payload (WAHA suele mandar body)\n    const txt = (\n      (typeof p.body === 'string' && p.body) ||\n      (p.text && typeof p.text.body === 'string' && p.text.body) ||\n      (typeof p.message === 'string' && p.message) ||\n      ''\n    );\n\n    const safe = (s) => String(s || '')\n      .replace(/\\\"/g, '\\\\\"')\n      .replace(/\\r?\\n/g, ' ')\n      .replace(/\\t/g, ' ')\n      .trim();\n\n    // Dedup de evento\n    const rawKey = (id && ts) ? (id + ':' + ts) : '';\n    let key = rawKey ? rawKey.slice(0, 64) : '';\n\n    // Fallback SIN Buffer (solo strings)\n    if (!key) {\n      const mix = (String(from) + '|' + String(ts) + '|' + String(txt)).slice(0, 120);\n      key = 'noid:' + mix.replace(/[^A-Za-z0-9]/g, '').slice(0, 40);\n      if (!key || key === 'noid:') key = 'noid:' + String(Date.now());\n    }\n\n    const safeKey = safe(key).slice(0, 80);\n    const safeFrom = safe(from);\n    const safeWamid = safe(id || ('k' + Date.now())).slice(0, 120);\n    const safeTxt = safe(txt).slice(0, 1000);\n\n    return ''\n      + 'DIR=/data/.event_dedup\\n'\n      + 'CO=/data/.coalesce\\n'\n      + 'mkdir -p \"$DIR\" \"$CO\"\\n'\n      + 'RAW_KEY=\"' + safeKey + '\"\\n'\n      + 'FROM_RAW=\"' + safeFrom + '\"\\n'\n      + 'WAMID=\"' + safeWamid + '\"\\n'\n      + 'TEXT=\"' + safeTxt + '\"\\n'\n\n      // Limpieza locks viejos\n      + 'find \"$DIR\" -maxdepth 1 -type f -mmin +10 -delete >/dev/null 2>&1 || true\\n'\n\n      // --- DEDUP LOCK (SIN SYMLINKS) ---\n      + 'FILE=\"$DIR/$RAW_KEY.lock\"\\n'\n      + 'if ( set -C; : > \"$FILE\" ) 2>/dev/null; then :; else\\n'\n      + '  if [ -e \"$FILE\" ]; then echo \"STATUS=DUP\"; exit 0; fi\\n'\n      + '  echo \"STATUS=OK_NOLOCK\"\\n'\n      + 'fi\\n'\n\n      // --- COALESCE (2s) ---\n      + 'PHONE=$(printf \"%s\" \"$FROM_RAW\" | tr -cd \"0-9\" | tail -c 16)\\n'\n      + 'if [ -z \"$PHONE\" ]; then PHONE=\"unknown\"; fi\\n'\n      + 'LOCK=\"$CO/$PHONE.lock\"\\n'\n      + 'META=\"$CO/$PHONE.meta\"\\n'\n      + 'BUF=\"$CO/$PHONE.buf\"\\n'\n      + 'i=0\\n'\n      + 'while [ \"$i\" -lt 40 ]; do\\n'\n      + '  if mkdir \"$LOCK\" 2>/dev/null; then break; fi\\n'\n      + '  i=$((i+1))\\n'\n      + '  sleep 0.05\\n'\n      + 'done\\n'\n      + 'NOW=$(date +%s)\\n'\n      + 'printf \"%s\" \"$WAMID\" > \"$META\"\\n'\n      + 'T=$(printf \"%s\" \"$TEXT\" | tr \"\\\\t\" \" \")\\n'\n      + 'printf \"%s\\\\t%s\\\\t%s\\\\n\" \"$NOW\" \"$WAMID\" \"$T\" >> \"$BUF\"\\n'\n      + 'rmdir \"$LOCK\" 2>/dev/null || true\\n'\n\n      + 'sleep 2\\n'\n\n      + 'i=0\\n'\n      + 'while [ \"$i\" -lt 40 ]; do\\n'\n      + '  if mkdir \"$LOCK\" 2>/dev/null; then break; fi\\n'\n      + '  i=$((i+1))\\n'\n      + '  sleep 0.05\\n'\n      + 'done\\n'\n      + 'CUR=$(cat \"$META\" 2>/dev/null || echo \"\")\\n'\n      + 'if [ \"$CUR\" != \"$WAMID\" ]; then rmdir \"$LOCK\" 2>/dev/null || true; echo \"STATUS=SKIP\"; exit 0; fi\\n'\n\n      + 'NOW2=$(date +%s)\\n'\n      + 'TH=$((NOW2-6))\\n'\n      + 'COMBINED=$(awk -F\"\\\\t\" -v th=\"$TH\" \\'$1>=th{ if(!out){out=$3}else{out=out\"\\\\n\"$3} } END{print out}\\' \"$BUF\" 2>/dev/null)\\n'\n      + 'B64=$(printf \"%s\" \"$COMBINED\" | base64 -w0 2>/dev/null || printf \"%s\" \"$COMBINED\" | base64)\\n'\n      + 'rm -f \"$BUF\" \"$META\" 2>/dev/null || true\\n'\n      + 'rmdir \"$LOCK\" 2>/dev/null || true\\n'\n\n      + 'echo \"STATUS=OK\"\\n'\n      + 'echo \"COALESCE_B64=$B64\"\\n'\n      + 'echo \"PHONE=$PHONE\"\\n'\n      + 'exit 0\\n';\n  } catch (e) {\n    // Garantiza string siempre\n    return 'echo \"STATUS=ERR\"; echo \"COALESCE_B64=\"; echo \"PHONE=unknown\"; exit 0';\n  }\n})() }}"
      },
      "id": "d868168f-d331-42d0-95e4-66dd33f27b7d",
      "name": "Early Dedup Event",
      "type": "n8n-nodes-base.executeCommand",
      "typeVersion": 1,
      "position": [
        -1904,
        48
      ]
    },
    {
      "parameters": {
        "functionCode": "function norm(s){\n  let out = String(s||'').toLowerCase();\n  try{ out = out.normalize('NFKD'); }catch(e){}\n  out = out.replace(/[\\u0300-\\u036f]/g,'');\n  out = out.replace(/\\s+/g,' ').replace(/[\\.,!\\?;:]/g,'').trim();\n  return out;\n}\n\nconst fm = ($node && $node[\"Format Memory\"] && $node[\"Format Memory\"].json) ? $node[\"Format Memory\"].json : {};\nconst last = String(fm.last_bot_reply || '').trim();\nconst lastN = norm(last);\n\nconst variants = [\n  'no puedo escuchar audios amor, me lo escribes mejor?',\n  'amor por aqui no escucho audios, escribeme y te digo 😘',\n  'cari no puedo oir audios ahora, me lo pones en texto?',\n  'me va mejor si me lo escribes amor, los audios no puedo escucharlos',\n  'guapo no puedo reproducir audios, escribeme un momentito y te contesto',\n  'ay amor, sin audio mejor, escribeme y asi te respondo rapido',\n  'cielo no escucho audios por aqui, me lo mandas escrito?',\n  'no puedo con audios ahora cari, escribeme mejor'\n];\n\nlet pick = variants[Math.floor(Math.random()*variants.length)];\nif (variants.length > 1) {\n  let guard = 0;\n  while (norm(pick) === lastN && guard < 10) {\n    pick = variants[Math.floor(Math.random()*variants.length)];\n    guard++;\n  }\n}\n\nconst ext = ($node && $node[\"Extract WA Text\"] && $node[\"Extract WA Text\"].json) ? $node[\"Extract WA Text\"].json : {};\nconst from_phone = String(ext.from_phone || $json.from_phone || '');\nconst thread_id = String(fm.thread_id || $json.thread_id || ('th-' + Date.now()));\n\nconst selected_girl_id = String(fm.selected_girl_id || $json.selected_girl_id || '');\nconst selected_girl_name = String(fm.selected_girl_name || $json.selected_girl_name || '');\nconst speaker_mode = String(fm.speaker_mode || $json.speaker_mode || '');\n\nreturn [{\n  ...$json,\n  output_text: String(pick || '').trim(),\n  lead_detected: false,\n  lead_flag: '0',\n  lead_numeric: 0,\n  lead_confidence: 0,\n  eta_minutes: 0,\n  user_message: '[AUDIO]',\n  from_phone,\n  thread_id,\n  last_bot_reply: last,\n  selected_girl_id,\n  selected_girl_name,\n  speaker_mode\n}];"
      },
      "id": "7ddad1f7-69c5-48ac-9a12-1ef3c607c222",
      "name": "Audio Auto Reply",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -224,
        -32
      ]
    },
    {
      "parameters": {
        "conditions": {
          "number": [
            {
              "value1": "={{ Number($node[\"Extract WA Text\"].json.is_audio_i || $node[\"Set is_audio (pre-memory)\"].json.is_audio_i || 0) }}",
              "operation": "equal",
              "value2": 1
            }
          ]
        }
      },
      "id": "d055b415-207e-450d-860e-cbbfada76845",
      "name": "IF Is Audio?",
      "type": "n8n-nodes-base.if",
      "typeVersion": 1,
      "position": [
        -416,
        -32
      ]
    },
    {
      "parameters": {
        "functionCode": "function asBool(x){\n  if (x === true) return true;\n  if (x === 1) return true;\n  if (typeof x === 'number') return x !== 0;\n  if (typeof x === 'string') {\n    const s = x.trim().toLowerCase();\n    return s === 'true' || s === '1' || s === 'yes' || s === 'y' || s === 'si' || s === 'sí';\n  }\n  return false;\n}\n\n// Datos originales del flujo (ya normalizados)\nconst prev = (() => { try { return $node['Extract WA Text'].json || {}; } catch(e){ return {}; } })();\n\n// Respuesta del webservice\nconst res = ($json && typeof $json === 'object') ? $json : {};\nconst blacklisted = asBool(res.blacklisted);\n\n// Si está en blacklist => cortamos el flujo\nif (blacklisted) {\n  return [];\n}\n\n// Si NO está blacklisteado => seguimos, preservando todo lo anterior\nreturn [{\n  ...prev,\n  blacklist_ws: res,\n  blacklist_ws_blacklisted: false,\n  blacklist_ws_ok: asBool(res.ok)\n}];"
      },
      "id": "2fbb8fe7-8202-43e6-a287-b42efb1b22f2",
      "name": "Gate: Blacklist WS",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -1248,
        224
      ]
    },
    {
      "parameters": {
        "url": "={{ 'http://blacklist.makemerich.live/blacklist.php?mode=check&phone=' + String($node[\"Extract WA Text\"].json.from_phone || '') }}",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "accept",
              "value": "application/json"
            }
          ]
        },
        "options": {
          "response": {
            "response": {
              "neverError": true
            }
          }
        }
      },
      "id": "43aef4b7-655e-40ad-a645-435d329f533f",
      "name": "Blacklist WS Check",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        -1440,
        224
      ]
    },
    {
      "parameters": {
        "functionCode": "function safeNodeJson(name){ try { return $node[name].json || {}; } catch(e){ return {}; } }\n\nconst cfg = safeNodeJson('Routing + ACL Config');\nconst list = Array.isArray(cfg.waha_numbers_config) ? cfg.waha_numbers_config : [];\nconst blacklist = Array.isArray(cfg.sender_blacklist) ? cfg.sender_blacklist : [];\nconst defEnabled = (typeof cfg.default_enabled_if_not_found === 'boolean') ? cfg.default_enabled_if_not_found : false;\n\n// Fallback al payload real del webhook si algun nodo anterior piso el body\nconst wh = safeNodeJson('WAHA Webhook In');\nconst src = ($json && (typeof $json === 'object') && ($json.body || $json.event || $json.payload || $json.messages || $json.entry)) ? $json : wh;\n\nconst body = (src.body && typeof src.body === 'object') ? src.body : {};\nconst payload = (body.payload && typeof body.payload === 'object') ? body.payload : (src.payload && typeof src.payload === 'object' ? src.payload : {});\nconst me = (body.me && typeof body.me === 'object') ? body.me : (payload.me && typeof payload.me === 'object' ? payload.me : {});\n\nfunction onlyDigits(x){ return String(x || '').replace(/[^0-9]/g, ''); }\nfunction normStr(x){ return String(x || '').trim().toLowerCase(); }\n\n// ------------------\n// 1) Gate por receptor (linea que recibe)\n// ------------------\nconst recvDigits = onlyDigits(me.id || payload.to || body.to || '');\nconst recvLast9 = recvDigits ? recvDigits.slice(-9) : '';\n\nlet entry = null;\nif (recvLast9) {\n  for (const e of list) {\n    if (!e) continue;\n    const k = onlyDigits(e.last9 || '');\n    if (k && k === recvLast9) { entry = e; break; }\n  }\n}\n\nconst enabled = entry ? !!entry.enabled : defEnabled;\nif (!enabled) {\n  return [];\n}\n\n// ------------------\n// 2) Blacklist por emisor\n//    (WAHA a veces manda @c.us y otras @lid)\n// ------------------\nfunction firstNonEmpty(arr){\n  for (const v of arr) {\n    if (v == null) continue;\n    const s = String(v).trim();\n    if (s) return s;\n  }\n  return '';\n}\n\nconst data = (payload && typeof payload === 'object') ? (payload._data || {}) : {};\n\nconst senderRaw = firstNonEmpty([\n  payload.from,\n  payload.chatId,\n  payload.author,\n  payload.participant,\n  (payload.sender && payload.sender.id),\n  data.from,\n  data.author,\n  (data.sender && data.sender.id),\n  (data.id && data.id.remote),\n  (data.id && data.id.participant),\n  body.from,\n  src.from\n]);\n\nconst senderRawLower = normStr(senderRaw);\nconst senderDigits = onlyDigits(senderRaw);\nconst senderLast9 = senderDigits ? senderDigits.slice(-9) : '';\n\nlet senderKind = 'unknown';\nif (senderRawLower.includes('@lid')) senderKind = 'lid';\nelse if (senderRawLower.includes('@c.us')) senderKind = 'cus';\nelse if (senderDigits) senderKind = 'digits';\n\n// claves útiles para comparar\nconst senderKeyCandidates = new Set();\nif (senderRawLower) senderKeyCandidates.add(senderRawLower);\nif (senderDigits) senderKeyCandidates.add(senderDigits);\nif (senderLast9) senderKeyCandidates.add(senderLast9);\nif (senderKind === 'lid' && senderDigits) senderKeyCandidates.add(senderDigits + '@lid');\nif (senderKind === 'cus' && senderDigits) senderKeyCandidates.add(senderDigits + '@c.us');\n\nfunction isBlacklisted(){\n  if (!senderRawLower && !senderDigits) return false;\n\n  for (const b of blacklist) {\n    const bStr = normStr(b);\n    if (!bStr) continue;\n\n    // Si el item de blacklist incluye @ (ej: ...@lid o ...@c.us) => match exacto contra senderRaw o contra clave normalizada\n    if (bStr.includes('@')) {\n      if (senderKeyCandidates.has(bStr)) return true;\n      continue;\n    }\n\n    // Caso numérico: permitimos guardar numero completo con prefijo, o solo last9\n    const bd = onlyDigits(bStr);\n    if (!bd) continue;\n\n    // match por final (ej: blacklist '34654464023' o '654464023')\n    if (senderDigits && senderDigits.endsWith(bd)) return true;\n\n    // match por last9 explícito\n    if (senderLast9 && bd.length >= 9 && senderLast9 === bd.slice(-9)) return true;\n  }\n\n  return false;\n}\n\nif (isBlacklisted()) {\n  return [];\n}\n\nreturn [{\n  ...src,\n\n  // preservamos config por si interesa aguas abajo\n  waha_numbers_config: cfg.waha_numbers_config,\n  sender_blacklist: cfg.sender_blacklist,\n  default_enabled_if_not_found: cfg.default_enabled_if_not_found,\n  default_port_if_not_found: cfg.default_port_if_not_found,\n\n  __receiver_last9: recvLast9,\n  __sender_raw: senderRaw,\n  __sender_digits: senderDigits,\n  __sender_last9: senderLast9,\n  __sender_kind: senderKind\n}];"
      },
      "id": "56d5f303-7b3c-4a23-8c9c-c0c31e2f6a22",
      "name": "Gate: Enabled + Blacklist",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -2112,
        48
      ]
    },
    {
      "parameters": {
        "functionCode": "function detectAudioRobust(src){\n  try{\n    const body = (src && src.body && typeof src.body === 'object') ? src.body : {};\n    const payload = (body.payload && typeof body.payload === 'object')\n      ? body.payload\n      : ((src.payload && typeof src.payload === 'object') ? src.payload : {});\n\n    const d = (payload && typeof payload._data === 'object') ? payload._data : {};\n\n    // Tipo (WAHA/WebJS suele ponerlo en payload.type o payload._data.type)\n    const t = String((payload && payload.type) || (d && d.type) || (src && src.type) || '').toLowerCase();\n\n    // Flags de media típicos\n    const hasMedia = !!(\n      (payload && payload.hasMedia === true) ||\n      (d && d.hasMedia === true) ||\n      (payload && payload.isMedia === true) ||\n      (d && d.isMedia === true)\n    );\n\n    // PTT / voice note flag\n    const isPtt = !!(\n      (payload && payload.ptt === true) ||\n      (d && d.ptt === true)\n    );\n\n    // Mimetype (ojo a mimeType camelCase)\n    const mt = String(\n      (payload && (payload.mimetype || payload.mime_type || payload.mimeType)) ||\n      (d && (d.mimetype || d.mime_type || d.mimeType)) ||\n      ''\n    ).toLowerCase();\n\n    // Si el tipo ya dice que es audio/nota de voz => TRUE SIEMPRE (aunque body tenga placeholder)\n    const typeSaysAudio = ['audio','ptt','voice','voice_note','voicenote','voice-message','voice_message'].includes(t);\n    if (typeSaysAudio) return true;\n\n    // Si mimetype es audio => normalmente es audio (muchas veces viene \"audio/ogg; codecs=opus\")\n    if (mt.startsWith('audio/')) return true;\n    if (mt.includes('audio/')) return true;\n\n    // WebJS a veces no trae mimetype directo pero sí hasMedia + ptt\n    if (hasMedia && isPtt) return true;\n\n    // Heurística extra por estructura: algunas builds meten objetos audio/voice dentro de _data\n    function hasNonEmptyObject(o){\n      return !!(o && typeof o === 'object' && !Array.isArray(o) && Object.keys(o).length > 0);\n    }\n    const audioObj = (payload && payload.audio) || (d && d.audio) || null;\n    const voiceObj = (payload && payload.voice) || (d && d.voice) || null;\n    if (hasNonEmptyObject(audioObj) || hasNonEmptyObject(voiceObj)) return true;\n\n    // Último recurso: si no hay texto y sí hay media, suele ser media (pero no asumas audio)\n    // Aquí preferimos NO marcar audio para evitar falsos positivos.\n    return false;\n  } catch(e){\n    return false;\n  }\n}\n\nconst isAudioBool = !!detectAudioRobust($json);\nconst is_audio_i = isAudioBool ? 1 : 0;\nconst is_audio = (is_audio_i === 1);\n\nlet message_type = '';\ntry {\n  const body = ($json.body && typeof $json.body === 'object') ? $json.body : {};\n  const payload = (body.payload && typeof body.payload === 'object') ? body.payload : ($json.payload || {});\n  const d = (payload && typeof payload._data === 'object') ? payload._data : {};\n  message_type = String((payload && payload.type) || (d && d.type) || '').toLowerCase();\n} catch (e) {\n  message_type = '';\n}\nif (!message_type && is_audio) message_type = 'audio';\n\nreturn [{\n  ...$json,\n  __is_audio: is_audio,\n  is_audio,\n  is_audio_i,\n  message_type\n}];"
      },
      "id": "40225dbf-776a-4a7c-b9bd-abb841a2c24f",
      "name": "Set is_audio (pre-memory)",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -2416,
        128
      ]
    },
    {
      "parameters": {
        "functionCode": "return [{\n  ...$json,\n  waha_numbers_config: [\n    { last9: '[LAMAMI_TFONO_BOT]', port: '[LAMAMI_PORT_BOT]', enabled: true,  label: 'linea_[LAMAMI_PORT_BOT]' }\n  ],\n  sender_blacklist: [\n    '34666555555',\n '12345678998745',\n    '162011098935441',\n'128690658783343',\n'232719011295254',\n    '34600111222',\n    '666555444',\n    '627146331',\n    '624091112',\n    '176725203927244'\n  ],\n  default_enabled_if_not_found: false,\n  default_port_if_not_found: '3000'\n}];"
      },
      "id": "9522567c-bb8a-4222-89c4-0d380dcaa4fe",
      "name": "Routing + ACL Config",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -2272,
        128
      ]
    },
    {
      "parameters": {
        "functionCode": "const mode=String($json.bot_mode_[LAMAMI_NOMBRE_BOT]||'').trim().toLowerCase();if(mode==='stop'){return[];}return[$json];"
      },
      "id": "3cae1f43-3574-4818-be02-b570f7164088",
      "name": "IF Bot Mode Start?",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -2112,
        208
      ]
    },
    {
      "parameters": {
        "functionCode": "// PREP: construir una linea NDJSON para log de leads (solo si es lead)\nfunction asBool(x){\n  if (typeof x==='boolean') return x;\n  if (typeof x==='number') return x!==0;\n  if (typeof x==='string') {\n    const s=x.trim().toLowerCase();\n    return ['true','1','yes','y','si','sí'].includes(s);\n  }\n  return false;\n}\n\n// Por seguridad, re-validamos el lead\nconst leadDetected = asBool($json.lead_detected) || (Number($json.lead_numeric||0)===1) || (String($json.lead_flag||'').trim()==='1');\nif (!leadDetected) return [];\n\nconst phoneRaw = String($json.from_phone || '').trim();\nconst phone = phoneRaw.replace(/[^0-9]/g,'');\nif (!phone) return [];\n\nconst rec = {\n  ts: new Date().toISOString(),\n  phone,\n  eta_minutes: Number($json.eta_minutes || 0) || 0,\n  lead_confidence: Number($json.lead_confidence || 0) || 0,\n  thread_id: String($json.thread_id || '').trim(),\n  source: 'waha-in'\n};\n\nconst line = JSON.stringify(rec) + \"\\n\";\nconst b64 = Buffer.from(line, 'utf8').toString('base64');\n\nreturn [{\n  ...$json,\n  lead_phone: phone,\n  lead_log_b64: b64\n}];"
      },
      "id": "99c0167a-78b4-4887-8703-3ed9fcac50dc",
      "name": "Lead Log Prep (NDJSON)",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -752,
        1088
      ]
    },
    {
      "parameters": {
        "command": "={{ (function(){\n  const esc = (v) => String(v ?? '').replace(/'/g, `'\"'\"'`);\n\n  const phone  = esc($json.from_phone || '');\n  const eta    = esc($json.eta_minutes ?? '');\n  const conf   = esc($json.lead_confidence ?? '');\n  const thread = esc($json.thread_id || '');\n\n  // Guardamos en /data/data/*  (en tu host es /srv/n8n_data/data/*)\n  return (\n    \"sh -lc '\" +\n    \"DIR=/data/data; FILE=\\\"$DIR/leads.ndjson\\\"; LOCK=\\\"$DIR/.leads.lock\\\"; \" +\n    \"mkdir -p \\\"$DIR\\\"; [ -f \\\"$FILE\\\" ] || : > \\\"$FILE\\\"; \" +\n\n    // Variables (sin romper comillas)\n    \"PHONE='\" + phone + \"'; ETA_RAW='\" + eta + \"'; CONF_RAW='\" + conf + \"'; THREAD='\" + thread + \"'; \" +\n\n    // Normaliza numéricos (evita 'out of range' y vacíos)\n    \"ETA=0; echo \\\"$ETA_RAW\\\" | grep -Eq \\\"^[0-9]+$\\\" && ETA=\\\"$ETA_RAW\\\"; \" +\n    \"CONF=0; echo \\\"$CONF_RAW\\\" | grep -Eq \\\"^[0-9]+([.][0-9]+)?$\\\" && CONF=\\\"$CONF_RAW\\\"; \" +\n\n    // Construye línea NDJSON\n    \"TS=$(date -Iseconds); \" +\n    \"LINE=$(printf '{\\\"ts\\\":\\\"%s\\\",\\\"phone\\\":\\\"%s\\\",\\\"eta\\\":%s,\\\"conf\\\":%s,\\\"thread_id\\\":\\\"%s\\\"}\\\\n' \\\"$TS\\\" \\\"$PHONE\\\" \\\"$ETA\\\" \\\"$CONF\\\" \\\"$THREAD\\\"); \" +\n\n    // Lock suave por directorio\n    \"i=0; got=0; while [ \\\"$i\\\" -lt 80 ]; do if mkdir \\\"$LOCK\\\" 2>/dev/null; then got=1; break; fi; i=$((i+1)); sleep 0.05; done; \" +\n\n    // Append + unlock\n    \"printf %s \\\"$LINE\\\" >> \\\"$FILE\\\"; \" +\n    \"[ \\\"$got\\\" -eq 1 ] && rmdir \\\"$LOCK\\\" 2>/dev/null || true; \" +\n    \"echo OK'\"\n  );\n})() }}"
      },
      "id": "79c7c4a9-c40f-425d-9196-f6c4a1ea38fe",
      "name": "Append Lead Phone Log (/data/leads.ndjson)",
      "type": "n8n-nodes-base.executeCommand",
      "typeVersion": 1,
      "position": [
        -608,
        1088
      ]
    }
  ],
  "pinData": {},
  "connections": {
    "WAHA stopTyping": {
      "main": [
        [
          {
            "node": "Wait - After",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "WAHA sendText": {
      "main": [
        [
          {
            "node": "WAHA stopTyping",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "WAHA startTyping": {
      "main": [
        [
          {
            "node": "Delay while Typing",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "WAHA sendSeen": {
      "main": [
        [
          {
            "node": "Delay before Typing",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Delay while Typing": {
      "main": [
        [
          {
            "node": "WAHA sendText",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Delay before Typing": {
      "main": [
        [
          {
            "node": "WAHA startTyping",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Delay before Seen (NEW)": {
      "main": [
        [
          {
            "node": "WAHA sendSeen",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Compute Human Delays": {
      "main": [
        [
          {
            "node": "IF Is Image Link?",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "IF WAHA antiban enabled?": {
      "main": [
        [
          {
            "node": "Compute Human Delays",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Split Outgoing (images as solo-link msgs)": {
      "main": [
        [
          {
            "node": "WAHA Config",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "IF Is Image Link?": {
      "main": [
        [
          {
            "node": "Wait Before Send (img)",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Delay before Seen (NEW)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Wait Before Send (img)": {
      "main": [
        [
          {
            "node": "WAHA startTyping (img)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "WAHA startTyping (img)": {
      "main": [
        [
          {
            "node": "Wait Short Typing (img)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Wait Short Typing (img)": {
      "main": [
        [
          {
            "node": "WAHA sendText (img)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "WAHA sendText (img)": {
      "main": [
        [
          {
            "node": "WAHA stopTyping (img)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Build WAHA Antiban": {
      "main": [
        [
          {
            "node": "IF WAHA antiban enabled?",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "WAHA Config": {
      "main": [
        [
          {
            "node": "Build WAHA Antiban",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "WAHA Webhook In": {
      "main": [
        [
          {
            "node": "Set is_audio (pre-memory)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Set Location": {
      "main": [
        [
          {
            "node": "OpenAI Chat",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Tone Classifier": {
      "main": [
        [
          {
            "node": "Build Tone",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Atomic Move TMP→FINAL": {
      "main": [
        [
          {
            "node": "Release Soft Lock",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Write Memory (TMP)": {
      "main": [
        [
          {
            "node": "Atomic Move TMP→FINAL",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Text To Binary (NDJSON)": {
      "main": [
        [
          {
            "node": "Write Memory (TMP)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Append Memory": {
      "main": [
        [
          {
            "node": "Text To Binary (NDJSON)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Bin2Text Memory Prev": {
      "main": [
        [
          {
            "node": "Append Memory",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Read Memory For Append": {
      "main": [
        [
          {
            "node": "Bin2Text Memory Prev",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Acquire Soft Lock": {
      "main": [
        [
          {
            "node": "Read Memory For Append",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "DeDupe Reply (guard)": {
      "main": [
        [
          {
            "node": "Split Outgoing (images as solo-link msgs)",
            "type": "main",
            "index": 0
          },
          {
            "node": "Acquire Soft Lock",
            "type": "main",
            "index": 0
          },
          {
            "node": "Gate Lead → Telegram",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Post-Format Catalog (hard enforce)": {
      "main": [
        [
          {
            "node": "DeDupe Reply (guard)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Normalize Output": {
      "main": [
        [
          {
            "node": "Post-Format Catalog (hard enforce)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "OpenAI Chat": {
      "main": [
        [
          {
            "node": "Normalize Output",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Build Tone": {
      "main": [
        [
          {
            "node": "Set Location",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Assemble Context (No-Merge)": {
      "main": [
        [
          {
            "node": "Tone Classifier",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Format Memory": {
      "main": [
        [
          {
            "node": "IF Is Audio?",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Bin2Text Memory": {
      "main": [
        [
          {
            "node": "Format Memory",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Read Memory": {
      "main": [
        [
          {
            "node": "Bin2Text Memory",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Bin2Text Playbook": {
      "main": [
        [
          {
            "node": "Read Memory",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Read Playbook": {
      "main": [
        [
          {
            "node": "Bin2Text Playbook",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Set Prompt": {
      "main": [
        [
          {
            "node": "Read Playbook",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Raw Dump -> B64": {
      "main": [
        [
          {
            "node": "Write Raw Payload",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Extract WA Text": {
      "main": [
        [
          {
            "node": "IF Is Pure Image?",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "IF Is Pure Image?": {
      "main": [
        [],
        [
          {
            "node": "Blacklist WS Check",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Gate: Only contacts/messages": {
      "main": [
        [
          {
            "node": "Raw Dump -> B64",
            "type": "main",
            "index": 0
          },
          {
            "node": "Extract WA Text",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Gate Lead → Telegram": {
      "main": [
        [
          {
            "node": "Telegram Alert (2)",
            "type": "main",
            "index": 0
          },
          {
            "node": "Telegram Alert",
            "type": "main",
            "index": 0
          },
          {
            "node": "Lead Log Prep (NDJSON)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Read Bot Mode": {
      "main": [
        [
          {
            "node": "Parse Bot Mode",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Parse Bot Mode": {
      "main": [
        [
          {
            "node": "Routing + ACL Config",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Early Dedup Gate": {
      "main": [
        [
          {
            "node": "Gate: Only contacts/messages",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Early Dedup Event": {
      "main": [
        [
          {
            "node": "Early Dedup Gate",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Audio Auto Reply": {
      "main": [
        [
          {
            "node": "WAHA Config",
            "type": "main",
            "index": 0
          },
          {
            "node": "Acquire Soft Lock",
            "type": "main",
            "index": 0
          },
          {
            "node": "Gate Lead → Telegram",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "IF Is Audio?": {
      "main": [
        [
          {
            "node": "Audio Auto Reply",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Assemble Context (No-Merge)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Gate: Blacklist WS": {
      "main": [
        [
          {
            "node": "Fetch Girls JSON",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Fetch Girls JSON": {
      "main": [
        [
          {
            "node": "Girls Config (from remote JSON)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Girls Config (from remote JSON)": {
      "main": [
        [
          {
            "node": "Set Prompt",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Blacklist WS Check": {
      "main": [
        [
          {
            "node": "Gate: Blacklist WS",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Gate: Enabled + Blacklist": {
      "main": [
        [
          {
            "node": "IF Bot Mode Start?",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Set is_audio (pre-memory)": {
      "main": [
        [
          {
            "node": "Read Bot Mode",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Routing + ACL Config": {
      "main": [
        [
          {
            "node": "Gate: Enabled + Blacklist",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "IF Bot Mode Start?": {
      "main": [
        [
          {
            "node": "Early Dedup Event",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Lead Log Prep (NDJSON)": {
      "main": [
        [
          {
            "node": "Append Lead Phone Log (/data/leads.ndjson)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    }
  },
  "active": true,
  "settings": {
    "executionOrder": "v1"
  },
  "versionId": "4d49a1d5-df65-43c6-a2b0-08f3c386e584",
  "meta": {
    "templateCredsSetupCompleted": true,
    "instanceId": "bf9c6759051138ef9b73cfee4ba7176a188783e7e2cff2753235cd13aaff91e5"
  },
  "id": "Vd5QSlku3s47SWS2",
  "tags": []
}
TPL;
}

/**
 * PEGA AQUÍ EXACTAMENTE el Texto2 que me has pasado, sin tocar nada.
 */
function lamami_template_texto2() {
    return <<<'TPL'
{
  "name": "wasapBot Mode Switch [LAMAMI_NOMBRE_BOT]",
  "nodes": [
    {
      "parameters": {
        "path": "bot-mode-[LAMAMI_NOMBRE_BOT]",
        "responseMode": "responseNode",
        "options": {}
      },
      "id": "62b880ec-d090-4a24-8579-43e3a1148821",
      "name": "Webhook (GET /bot-mode)",
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 2.1,
      "position": [
        -976,
        0
      ],
      "webhookId": "bot-mode-webhook"
    },
    {
      "parameters": {
        "functionCode": "const q = $json.query || {}; let m = String(q.mode || '').trim().toLowerCase(); return [{ mode: m }];"
      },
      "id": "7472b3ad-f4cf-4acd-81fc-ecf496420b86",
      "name": "Parse Mode Param",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -784,
        0
      ]
    },
    {
      "parameters": {
        "conditions": {
          "string": [
            {
              "value1": "={{$json.mode}}",
              "operation": "regex",
              "value2": "^(start|stop)$"
            }
          ]
        },
        "options": {}
      },
      "id": "08f780ca-f3ea-4f8c-8899-3bde4e25c434",
      "name": "IF Valid Mode?",
      "type": "n8n-nodes-base.if",
      "typeVersion": 2,
      "position": [
        -608,
        0
      ]
    },
    {
      "parameters": {
        "functionCode": "const m=String($json.mode||'').trim().toLowerCase(); const b64=Buffer.from(m,'utf8').toString('base64'); return [{ mode:m, binary:{ data:{ data:b64 } } }];"
      },
      "id": "43353054-8afe-402f-9290-80030fa47c16",
      "name": "Text→Binary (Mode)",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [
        -416,
        -112
      ]
    },
    {
      "parameters": {
        "fileName": "/data/.bot_mode_[LAMAMI_NOMBRE_BOT]",
        "options": {}
      },
      "id": "de9cc5ff-494d-457a-9ffa-810d3abac6c3",
      "name": "Write Bot Mode",
      "type": "n8n-nodes-base.writeBinaryFile",
      "typeVersion": 1,
      "position": [
        -240,
        -112
      ]
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "name": "response",
              "type": "json",
              "value": "={{ { code:1, message:'mode updated', mode:$json.mode } }}"
            },
            {
              "name": "status",
              "type": "number",
              "value": 200
            }
          ]
        },
        "options": {}
      },
      "id": "d2929f3e-08de-40fd-840e-d540d86bf284",
      "name": "Build OK Response",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [
        -64,
        -112
      ]
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "name": "response",
              "type": "json",
              "value": "={\"code\":0,\"message\":\"invalid mode; use ?mode=start or ?mode=stop\"}"
            },
            {
              "name": "status",
              "type": "number",
              "value": 400
            }
          ]
        },
        "options": {}
      },
      "id": "2180451a-a3c9-46f7-a991-5011f7cc8069",
      "name": "Build Invalid Response",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [
        -416,
        144
      ]
    },
    {
      "parameters": {
        "mode": "wait",
        "options": {}
      },
      "id": "4d56d959-8a03-4917-bb96-e5e2f4a8a526",
      "name": "Merge Responses",
      "type": "n8n-nodes-base.merge",
      "typeVersion": 2,
      "position": [
        144,
        0
      ]
    },
    {
      "parameters": {
        "options": {}
      },
      "id": "0b80e787-0522-4a1b-aea0-224cc211a06f",
      "name": "Respond",
      "type": "n8n-nodes-base.respondToWebhook",
      "typeVersion": 1,
      "position": [
        352,
        0
      ]
    }
  ],
  "pinData": {},
  "connections": {
    "Webhook (GET /bot-mode)": {
      "main": [
        [
          {
            "node": "Parse Mode Param",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Parse Mode Param": {
      "main": [
        [
          {
            "node": "IF Valid Mode?",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "IF Valid Mode?": {
      "main": [
        [
          {
            "node": "Text→Binary (Mode)",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Build Invalid Response",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Text→Binary (Mode)": {
      "main": [
        [
          {
            "node": "Write Bot Mode",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Write Bot Mode": {
      "main": [
        [
          {
            "node": "Build OK Response",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Build OK Response": {
      "main": [
        [
          {
            "node": "Merge Responses",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Build Invalid Response": {
      "main": [
        [
          {
            "node": "Merge Responses",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Merge Responses": {
      "main": [
        [
          {
            "node": "Respond",
            "type": "main",
            "index": 0
          }
        ]
      ]
    }
  },
  "active": true,
  "settings": {
    "executionOrder": "v1"
  },
  "versionId": "5ab8a03b-c685-4a70-8bee-8c4353ebf696",
  "meta": {
    "instanceId": "bf9c6759051138ef9b73cfee4ba7176a188783e7e2cff2753235cd13aaff91e5"
  },
  "id": "c2tztBNR6L60UskV",
  "tags": []
}
TPL;
}

function lamami_template_texto3() {
    return <<<'TPL'
services:
  waha:
    image: devlikeapro/waha:latest
    container_name: waha_[LAMAMI_NOMBRE_BOT]
    restart: unless-stopped
    ports:
      - "[LAMAMI_PORT_BOT]:3000"
    cpus: "2"
    mem_limit: "1g"
    environment:
      - WAHA_API_KEY=local321
      - WHATSAPP_DEFAULT_ENGINE=GOWS
      - TZ=Europe/Madrid
      - WAHA_DASHBOARD_USERNAME=admin
      - WAHA_DASHBOARD_PASSWORD=admin123
      - WHATSAPP_SWAGGER_USERNAME=admin
      - WHATSAPP_SWAGGER_PASSWORD=admin123
      - WHATSAPP_HOOK_URL=https://n8n.makemerich.live/webhook/waha-in-[LAMAMI_NOMBRE_BOT]
      - WHATSAPP_HOOK_EVENTS=message
    volumes:
      - ./data:/app/data
      - ./sessions:/app/.sessions
      - ./media:/app/.media
TPL;
}

function lamami_template_texto4() {
    return 'https://casawasap.com/girlsconf_[LAMAMI_NOMBRE_BOT]';
}

function lamami_template_texto5_start() {
    return 'https://n8n.makemerich.live/webhook/bot-mode-[LAMAMI_NOMBRE_BOT]?mode=start';
}

function lamami_template_texto5_stop() {
    return 'https://n8n.makemerich.live/webhook/bot-mode-[LAMAMI_NOMBRE_BOT]?mode=stop';
}

function lamami_generate_bot_bundle($bot, $clienta) {
    $vars = lamami_bot_vars($bot, $clienta);

    list($memoryOk, $memoryPath) = lamami_prepare_session_memory_file(
        $vars['[LAMAMI_NOMBRE_BOT]']
    );

    if (!$memoryOk) {
        return array(false, $memoryPath);
    }

    list($panelOk, $panelMsg) = lamami_prepare_girls_panel(
        $vars['[LAMAMI_NOMBRE_BOT]'],
        $vars['[LAMAMI_NOMBRE_CLIENTA_LEAD]'],
        $vars['[LAMAMI_NOMBRE_CLIENTA]'],
        $vars['[LAMAMI_SERVICIOS_CLIENTA]']
    );

    if (!$panelOk) {
        return array(false, $panelMsg);
    }

    $bundle = array(
        'generated_at' => now_datetime(),
        'texto1' => lamami_apply_vars(lamami_template_texto1(), $vars),
        'texto2' => lamami_apply_vars(lamami_template_texto2(), $vars),
        'texto3' => lamami_apply_vars(lamami_template_texto3(), $vars),
        'texto4' => lamami_apply_vars(lamami_template_texto4(), $vars),
        'texto5_start' => lamami_apply_vars(lamami_template_texto5_start(), $vars),
        'texto5_stop' => lamami_apply_vars(lamami_template_texto5_stop(), $vars),
        'girls_json_path' => $panelMsg,
        'session_memory_path' => $memoryPath,
    );

    if (($bot['bot_mode'] ?? 'multiple') === 'personal') {
        $bundle['texto1'] = str_replace(
            'Si speaker_girl_name esta vacio: estas en modo encargada, hablas como la casa.',
            'Si speaker_girl_name esta vacio: estas en modo encargada, hablas como la primera chica que encuentres libre en el listado de chicas.',
            $bundle['texto1']
        );

        $bundle['texto1'] = str_replace(
            'Estas en modo encargada: hablas como la casa, ofreces opciones, sin decir \"soy telefonista\".',
            'Estas en modo encargada: hablas como la primera chica que encuentres disponible en el listado de chicas.',
            $bundle['texto1']
        );
    }

    return array(true, $bundle);
}


function casawasap_bot_profile_to_clienta_shape($bot, $cliente) {
    $profile = casawasap_bot_profile_from_contact($cliente, $bot);

    $servicios = trim((string)$profile['servicios']);
    $extras = array();
    if ($profile['contexto'] !== '') {
        $extras[] = $profile['contexto'];
    }
    if ($profile['horario'] !== '') {
        $extras[] = 'Horario: ' . $profile['horario'];
    }
    if ($profile['objetivo'] !== '') {
        $extras[] = 'Objetivo: ' . $profile['objetivo'];
    }
    if (!empty($extras)) {
        $servicios = trim($servicios . "

" . implode("
", $extras));
    }

    $zona = trim((string)$profile['zona']);
    if ($profile['ubicacion_maps'] !== '' && $zona === '') {
        $zona = 'Ubicación disponible por Maps.';
    }

    return array(
        'nombre' => $profile['business_name'],
        'ubicacion_maps' => $profile['ubicacion_maps'],
        'zona' => $zona,
        'servicios' => $servicios,
        'tarifas' => $profile['tarifas'],
    );
}

function casawasap_generate_bot_bundle($bot, $cliente) {
    $clientaShape = casawasap_bot_profile_to_clienta_shape($bot, $cliente);
    list($ok, $bundle) = lamami_generate_bot_bundle($bot, $clientaShape);
    if (!$ok) {
        return array(false, $bundle);
    }

    $profile = casawasap_bot_profile_from_contact($cliente, $bot);
    $warnings = array();
    if ($profile['servicios'] === '') {
        $warnings[] = 'El cliente CasaWasap no tiene informado el campo “Servicios para bot”.';
    }
    if ($profile['tarifas'] === '') {
        $warnings[] = 'El cliente CasaWasap no tiene informado el campo “Tarifas para bot”.';
    }
    if ($profile['zona'] === '' && $profile['ubicacion_maps'] === '') {
        $warnings[] = 'El cliente CasaWasap no tiene zona ni ubicación Maps para el bot.';
    }

    $bundle['profile_source'] = 'casawasap_cliente';
    $bundle['profile_display_name'] = $profile['business_name'];
    $bundle['profile_summary'] = 'Pack CasaWasap generado desde la ficha del cliente “' . ($profile['business_name'] ?: 'Cliente') . '”.';
    $bundle['warnings'] = $warnings;
    $bundle['summary'] = $bundle['profile_summary'];

    return array(true, $bundle);
}

function lamami_prepare_session_memory_file($botCode) {
    $safeBotCode = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$botCode);
    $path = '/srv/n8n_data/session_memory_' . $safeBotCode . '.ndjson';

    if (!file_exists($path)) {
        $ok = @file_put_contents($path, '');
        if ($ok === false && !file_exists($path)) {
            return array(false, 'No se pudo crear el fichero de memoria del bot: ' . $path);
        }
    }

    @chmod($path, 0777);

    return array(true, $path);
}


function lamamibot_bot_slug($cfg) {
    $name = trim((string)($cfg['nombre_bot'] ?? 'lamamidef'));
    $slug = preg_replace('/[^A-Za-z0-9_\-]/', '_', $name);
    $slug = strtolower(trim((string)$slug, '_-'));
    return $slug !== '' ? $slug : 'lamamidef';
}

function lamamibot_mix_memory_paths($botSlug) {
    return array(
        'memory_file' => '/data/session_memory_' . $botSlug . '_mix.ndjson',
        'memory_file_tmp' => '/data/session_memory_' . $botSlug . '_mix.ndjson.tmp',
        'memory_lock' => '/data/.session_memory_' . $botSlug . '_mix.lock',
    );
}

function lamamibot_prepare_mix_session_memory_file($botSlug) {
    $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$botSlug);
    $path = '/srv/n8n_data/session_memory_' . $safe . '_mix.ndjson';

    if (!file_exists($path)) {
        $ok = @file_put_contents($path, '');
        if ($ok === false && !file_exists($path)) {
            return array(false, 'No se pudo crear el fichero de memoria mixta: ' . $path);
        }
    }

    return array(true, $path);
}

function lamamibot_selected_waha_config($telefonosIds) {
    $telefonos = storage_read('telefonos.json');
    $idx = array();
    foreach ($telefonos as $row) {
        $id = trim((string)($row['id'] ?? ''));
        if ($id !== '') {
            $idx[$id] = $row;
        }
    }

    $config = array();
    $warnings = array();
    $seenLast9 = array();

    foreach ((array)$telefonosIds as $id) {
        $id = trim((string)$id);
        if ($id === '' || !isset($idx[$id])) {
            continue;
        }

        $row = $idx[$id];
        $label = trim((string)($row['nombre'] ?? ''));
        if ($label === '') {
            $label = trim((string)($row['tfono'] ?? $id));
        }

        $digits = preg_replace('/\D+/', '', (string)($row['tfono'] ?? ''));
        $port = trim((string)($row['waha_port'] ?? ''));

        if ($digits === '' || strlen($digits) < 9) {
            $warnings[] = 'La línea "' . $label . '" no tiene un teléfono válido y se omitió del routing.';
            continue;
        }

        $last9 = substr($digits, -9);

        if ($port === '') {
            $warnings[] = 'La línea "' . $label . '" no tiene WAHA port y se omitió del routing.';
            continue;
        }

        if (isset($seenLast9[$last9])) {
            $warnings[] = 'La línea "' . $label . '" repite el teléfono ' . $last9 . ' y se omitió del routing.';
            continue;
        }

        $seenLast9[$last9] = true;

        $config[] = array(
            'last9' => $last9,
            'port' => $port,
            'enabled' => true,
            'label' => $label,
        );
    }

    return array($config, $warnings);
}

function lamamibot_build_generation_summary($botSlug, $wahaConfig, $warnings) {
    $parts = array();
    $parts[] = 'Pack de LamamiBot regenerado';
    $parts[] = 'slug técnico: ' . $botSlug;
    $parts[] = count((array)$wahaConfig) . ' líneas activas en routing';
    $parts[] = 'mode switch listo';
    $parts[] = count((array)$wahaConfig) . ' bloque(s) docker-compose generados';
    $parts[] = 'girlsconf fijo: lamamidef';

    if (!empty($warnings)) {
        $parts[] = count($warnings) . ' avisos';
    }

    return implode(' · ', $parts);
}

function lamamibot_patch_texto1($texto1, $botSlug, $wahaConfig, $mixMemory) {
    $girlsJsonUrl = lamamibot_girlsconf_base_url() . '/data/girls.json';
    $genericFile = $mixMemory['memory_file'];
    $genericTmp = $mixMemory['memory_file_tmp'];
    $genericLock = $mixMemory['memory_lock'];

    $workflow = json_decode($texto1, true);
    if (!is_array($workflow) || !isset($workflow['nodes']) || !is_array($workflow['nodes'])) {
        return $texto1;
    }

$setPromptText = <<<'PROMPT'
### ROL
Eres el asistente de un negocio que atiende por WhatsApp, pero SIEMPRE debes hablar como si fueras la propia chica del anuncio. Nunca debes reconocer que eres un bot, una telefonista, una encargada, la casa o una centralita. Si te preguntan, sal del paso con naturalidad y cambia de tema.

Tu objetivo principal es convertir conversaciones en citas reales sin oler a bot: sonar humana, cercana, sexy, natural y conseguir que el cliente avance hacia la visita.

### ESTILO GENERAL
- Habla siempre en primera persona.
- Tono femenino, cercano, cariñoso y sugerente.
- Frases cortas, humanas y naturales.
- Sin signos de apertura (ni ¿ ni ¡).
- Evita respuestas largas o demasiado estructuradas.
- No uses lenguaje de centralita, ni tipo “te gestiono”, “te ofrezco”, “la casa”, “las chicas”, “mis compañeras”, “soy la encargada”.
- No inventes datos concretos.
- No inventes ambigüedades que el sistema no te haya dado.
- No delates nunca que detrás del mismo teléfono pueda haber más de una chica.
- El cliente debe sentir todo el tiempo que habla con la misma chica que vio en su anuncio.

### EMOJIS Y TONO
- NO uses emojis en todos los mensajes. La regla general es 1 emoji cada 2-3 mensajes (o menos).
- Si la conversacion se calienta (el cliente muestra interes fuerte de venir ya, ETA, etc.), puedes subir un poco la frecuencia y usar 1-2 emojis picantes si pegan.
- Si estas dando informacion importante (precios, ubicacion, tarifas), omite los emojis o como mucho 1 al final.
- Al cerrar conversacion (gracias, adios, etc.), sin emojis.
- Emojis sugeridos: ❤️ 😘 😉 😏 😅 💕 😊

### APERTURA NATURAL
- Si te saludan, responde natural, corta y femenina.
- NO empieces de primeras con “por cual de mis anuncios me has visto”.
- NO dejes entrever que hay varios anuncios o varias chicas detrás.
- Antes de tener clara la identidad, debes seguir hablando como si fueras la misma chica del anuncio que él vio, solo que todavía estás terminando de ubicar cuál era.

### IDENTIDAD LAMAMIBOT
Tienes estas variables en contexto:
- identity_resolved
- identity_resolution_reason
- identity_candidates_count
- selected_girl_name
- girl_zona
- girl_servicios
- girl_tarifas
- location_url
- girls_config

REGLAS:

#### Si identity_resolved=false
- Aún no has podido fijar con seguridad qué perfil exacto vio.
- PERO sigues hablando como si fueras la misma chica real.
- NO digas ni sugieras que hay varias chicas.
- NO digas “por cual de mis anuncios”.
- NO digas “tengo varias por esa zona”.
- NO ofrezcas catálogo.
- NO enumeres nombres de chicas.
- NO inventes opciones que el sistema no haya confirmado.

Si necesitas aclarar la identidad:
- hazlo de forma SUTIL
- pide UNA sola pista cada vez
- usa preguntas que parezcan una ayuda de memoria natural, no un catálogo

Ejemplos válidos:
- “no pasa nada cariño, te acuerdas por que zona me viste?”
- “te acuerdas si era por Madrid o por otra ciudad?”
- “te acuerdas si salia morena, pelirroja o algo asi?”
- “si me dices la zona o como salia en la foto te ubico rapido”

Ejemplos prohibidos:
- “por cual de mis anuncios me hablas?”
- “tengo varias por ahi”
- “puede ser la mulata, la pelirroja, la de ventas...”
- “por ventas tengo más de una”

Además:
- NO debes dar ubicación exacta si no está resuelta.
- NO debes dar tarifas concretas si no está resuelta.
- NO debes dar servicios concretos de una chica si no está resuelta.
- Si te piden fotos sin resolver identidad:
  - NO inventes fotos
  - NO pongas condiciones absurdas
  - NO exijas hora, ETA o intención de venir
  - limita la respuesta a una aclaración sutil para fijar la identidad

#### Si identity_resolved=true
- Ya eres exactamente selected_girl_name.
- Hablas como ella en primera persona.
- Ya puedes usar sus datos reales:
  - girl_zona
  - girl_servicios
  - girl_tarifas
  - location_url
- No vuelvas a abrir ambigüedad.
- No vuelvas a preguntar por anuncio, ciudad o perfil si ya quedó claro.
- Si te pide fotos:
  - envíalas directamente
  - sin pedir ETA
  - sin pedir hora
  - sin poner trabas
  - usa siempre girls_config como fuente real

### REGLA CLAVE DE IDENTIDAD
- Si el usuario ya ha dado una pista clara que encaja de forma única con una chica (zona, ciudad, rasgo físico, etc.), compórtate como si la identidad ya estuviera cerrada.
- Una vez resuelta, no vuelvas atrás.
- Nunca inventes que una zona tiene varias chicas si el sistema no lo sabe.

### DISPONIBILIDAD
- Si preguntan por disponibilidad, contesta primero la disponibilidad.
- No metas menú.
- No cambies de tema antes de responder eso.

### MULTIPLES PREGUNTAS EN UN MISMO MENSAJE
- Si el cliente hace varias preguntas en un mismo mensaje, responde a TODAS en la misma respuesta de forma breve.
- No contestes solo una si puedes responder varias sin problema.

### FRASES PROHIBIDAS
- No uses “no se a que te refieres” ni variantes.
- No uses “dime que te apetece saber” como salida fácil.
- No uses “por cual de mis anuncios”.
- No uses “tengo varias por esa zona”.
- No uses frases que hagan pensar que el teléfono atiende a más de una chica.

### RESPONDER A LO QUE DICE
- Tu prioridad es contestar a lo que el cliente dice, no reconducir por sistema.
- Si ya ha dado una pista útil, úsala.
- Si ya ha pedido fotos y la identidad quedó suficientemente clara, pásalas sin más trabas.
- Si ya va hacia venir, no lo devuelvas a una fase anterior.

### UBICACIÓN
- Si identity_resolved=false:
  - NO mandes ubicación exacta
  - NO mandes maps
  - si hace falta, pide una pista sutil para fijar identidad

- Si identity_resolved=true:
  - usa location_url cuando toque
  - si hay intención clara de venir, ya puedes pasar la ubicación exacta

### TARIFAS
- Si identity_resolved=false:
  - no des tarifas concretas
  - pide una pista sutil para fijar identidad
- Si identity_resolved=true:
  - usa girl_tarifas como fuente real

### SERVICIOS
- Si identity_resolved=false:
  - no des el detalle concreto de una chica
  - pide una pista sutil para fijar identidad
- Si identity_resolved=true:
  - usa girl_servicios como fuente real

### FOTOS
- Usa SIEMPRE girls_config.
- Si identity_resolved=true:
  - manda las fotos de esa chica directamente
  - no pongas condiciones previas
  - no respondas con trabas tipo “primero dime a qué hora vienes”
- Si identity_resolved=false:
  - no inventes
  - no abras catálogo
  - pide una sola pista sutil para fijar identidad

### RESPUESTAS CORTAS DEL CLIENTE
- Si responde “si”, “vale”, “ok” o emojis, interprétalo como continuación natural de tu última pregunta concreta.
- No pierdas el hilo por respuestas cortas.

### MEMORIA COMERCIAL
- Si ya_enviado incluye “precios”, no vuelvas a presentarlos como si fuera la primera vez.
- Si maps_sent es true, no vuelvas a ofrecer la ubicación como si aún no la hubiera recibido.
- Si la identidad ya está resuelta, no vuelvas a pedir que aclare anuncio o perfil.
- Si ya se enviaron fotos recientemente, no actúes como si nunca hubieras mandado ninguna.

### ANTI-REPETICION
- No repitas frases literales.
- Si tu respuesta se parece mucho al último mensaje del bot, reescríbela.

### FORMATO DE RESPUESTA
- Respuestas cortas, naturales y humanas.
- Normalmente 1-2 frases.
- Sin preguntas innecesarias.
- Si necesitas aclarar identidad, haz UNA sola pregunta sutil.

### LEAD DETECTION
- lead_detected=true SOLO si:
  - maps_sent=true o en ese mismo mensaje incluyes location_url
  - y además el cliente ha dado ETA clara menor de 21 min
- Si falta mapa o falta ETA:
  - lead_detected=false

### FORMATO DE SALIDA
Devuelve SOLO un JSON con esta forma exacta:
{"lead_detected": boolean, "lead_confidence": number, "eta_minutes": number|null, "user_visible_reply": string}
PROMPT;

    $girlsConfigCode = <<<'JS'
function asBool(x){
  if(typeof x==='boolean') return x;
  if(typeof x==='number') return x!==0;
  if(typeof x==='string'){
    const s=x.trim().toLowerCase();
    return ['true','1','yes','y','si','sí'].includes(s);
  }
  return false;
}

let prev = {};
try { prev = $node['Gate: Blacklist WS'].json || {}; } catch(e) { prev = {}; }

const res = ($json && typeof $json === 'object') ? $json : {};

let girls = [];
if (Array.isArray(res.girls)) girls = res.girls;
else if (Array.isArray(res.girls_config)) girls = res.girls_config;
else if (Array.isArray(res)) girls = res;

const girls_config = (girls || []).map(g => {
  const o = (g && typeof g === 'object') ? g : {};
  return {
    id: String(o.id || '').trim(),
    crm_clienta_id: String(o.crm_clienta_id || '').trim(),
    nombre: String(o.nombre || '').trim(),
    descripcion_corta: String(o.descripcion_corta || '').trim(),
    zona: String(o.zona || '').trim(),
    servicios: String(o.servicios || '').trim(),
    tarifas: String(o.tarifas || '').trim(),
    ubicacion_maps: String(o.ubicacion_maps || '').trim(),
    memory_file: String(o.memory_file || '').trim(),
    memory_file_tmp: String(o.memory_file_tmp || '').trim(),
    memory_lock: String(o.memory_lock || '').trim(),
    fotos: Array.isArray(o.fotos) ? o.fotos.map(x => String(x||'').trim()).filter(Boolean) : [],
    activa: asBool(o.activa)
  };
});

return [{
  ...prev,
  girls_config,
  __girls_source: 'remote_girls.json'
}];
JS;

$identityResolveCode = <<<'JS'
function norm(s){
  let out = String(s || '').toLowerCase();
  try { out = out.normalize('NFKD'); } catch (e) {}
  out = out.replace(/[\u0300-\u036f]/g, '');
  out = out.replace(/[^a-z0-9\s]/g, ' ');
  out = out.replace(/\s+/g, ' ').trim();
  return out;
}

function uniq(arr){
  return Array.from(new Set((arr || []).filter(Boolean)));
}

function meaningfulTokens(s){
  const stop = new Set([
    'hola','holaaaaa','buenas','ola','hey','que','tal','como','estas','carino','cari','amor','bb',
    'bebe','guapo','guapa','pasame','pasa','mandame','manda','quiero','ver','verte','fotos','foto',
    'tarifa','tarifas','precio','precios','ubicacion','direccion','maps','mapa','por','cual','cuales',
    'anuncio','anuncios','la','las','el','los','de','del','en','por','me','mi','mis','te','tu','tus',
    'una','uno','un','esta','este','esa','ese','ahi','alli','aca','alli','donde','estas','sale',
    'cuesta','vale','media','hora','horita','servicios','servicio','que','haces','ofreces','explica',
    'explicame','no','si','vale','ok','okey','perfecto','genial','ya','ahora','mismo','voy','ir'
  ]);

  return norm(s)
    .split(/\s+/)
    .filter(Boolean)
    .filter(tok => tok.length >= 3 && !stop.has(tok));
}

function buildPhrases(tokens){
  const out = [];
  for (let n = 3; n >= 1; n--) {
    for (let i = 0; i <= tokens.length - n; i++) {
      const ph = tokens.slice(i, i + n).join(' ').trim();
      if (!ph) continue;
      if (n >= 2 && ph.length >= 5) out.push(ph);
      if (n === 1 && ph.length >= 4) out.push(ph);
    }
  }
  return uniq(out);
}

function containsWholeWord(hay, tok){
  const h = ' ' + norm(hay) + ' ';
  const w = ' ' + norm(tok) + ' ';
  return h.includes(w);
}

function scoreGirl(g, text, weight){
  const raw = norm(text);
  if (!raw) return { score: 0, matches: [] };

  const name = norm(g?.nombre || '');
  const zone = norm(g?.zona || '');
  const desc = norm(g?.descripcion_corta || '');

  let score = 0;
  let matches = [];

  if (name && raw.includes(name)) {
    score += 100 * weight;
    matches.push('name:' + name);
  }

  const tokens = meaningfulTokens(raw);
  const phrases = buildPhrases(tokens);

  for (const ph of phrases) {
    const words = ph.split(' ').length;

    if (zone && zone.includes(ph)) {
      score += (words >= 2 ? 12 : 7) * weight;
      matches.push('zone:' + ph);
      continue;
    }

    if (desc && desc.includes(ph)) {
      score += (words >= 2 ? 7 : 4) * weight;
      matches.push('desc:' + ph);
    }
  }

  for (const tok of tokens) {
    if (zone && containsWholeWord(zone, tok)) {
      score += 4 * weight;
      matches.push('zoneTok:' + tok);
      continue;
    }
    if (desc && containsWholeWord(desc, tok)) {
      score += 2 * weight;
      matches.push('descTok:' + tok);
    }
  }

  return {
    score,
    matches: uniq(matches)
  };
}

function looksLikeFreshOpener(txt){
  const t = norm(txt);
  if (!t) return false;
  return /^(hola|holaa+|buenas|ola|hey|info|informacion|te vi|vi tu anuncio|me interesa|sigues|disponible|estas|estas disponible)\b/.test(t);
}

function routeStillValid(expiresAt){
  if (!expiresAt) return false;
  const ms = Date.parse(expiresAt);
  return isFinite(ms) && ms > Date.now();
}

const genericFile = '__GENERIC_FILE__';
const genericTmp = '__GENERIC_TMP__';
const genericLock = '__GENERIC_LOCK__';

const girlsRaw = Array.isArray($json.girls_config) ? $json.girls_config : [];
const girls = girlsRaw.filter(g => !!g && (g.activa === true || g.activa === 1 || String(g.activa).toLowerCase() === 'true'));

const ext = (($node['Extract WA Text'] && $node['Extract WA Text'].json) ? $node['Extract WA Text'].json : {});
const currentText = String(ext.message_text || '').trim();
const lastMeaningful = String($json.last_user_meaningful || '').trim();
const lastUserMessage = String($json.last_user_message || '').trim();

const routeGirlId = String($json.route_girl_id || '').trim();
const routeGirlName = String($json.route_girl_name || '').trim();
const routeExpiresAt = String($json.route_expires_at || '').trim();
const sessionReset = !!$json.session_reset;

const canReuseMixRoute =
  !!(routeGirlId || routeGirlName) &&
  routeStillValid(routeExpiresAt) &&
  !sessionReset &&
  !looksLikeFreshOpener(currentText);

let selectedId = canReuseMixRoute ? routeGirlId : '';
let selectedName = canReuseMixRoute ? routeGirlName : '';

let girl = null;
let identity_resolution_reason = '';
let identity_candidates_count = 0;

if (selectedId) {
  girl = girls.find(g => String(g?.id || '').trim() === selectedId) || null;
  if (girl) identity_resolution_reason = 'mix_route_id';
}
if (!girl && selectedName) {
  const wanted = norm(selectedName);
  girl = girls.find(g => norm(g?.nombre || '') === wanted) || null;
  if (girl) identity_resolution_reason = 'mix_route_name';
}

if (!girl && girls.length === 1) {
  girl = girls[0];
  identity_resolution_reason = 'single_active_girl';
}

const scored = girls.map(g => {
  let total = 0;
  let matches = [];

  const a = scoreGirl(g, currentText, 3);
  total += a.score; matches = matches.concat(a.matches);

  const b = scoreGirl(g, lastMeaningful, 2);
  total += b.score; matches = matches.concat(b.matches);

  const c = scoreGirl(g, lastUserMessage, 1);
  total += c.score; matches = matches.concat(c.matches);

  return {
    girl: g,
    score: total,
    matches: uniq(matches)
  };
}).sort((x, y) => y.score - x.score);

const positive = scored.filter(x => x.score > 0);
identity_candidates_count = positive.length;

if (positive.length) {
  const best = positive[0];
  const second = positive[1] || { score: 0 };
  const clearUnique =
    best.score >= 10 &&
    (positive.length === 1 || best.score >= (second.score + 4));

  const nameHit = best.matches.some(m => String(m).startsWith('name:'));

  /*
    La señal actual del mensaje puede cerrar identidad o incluso
    sobreescribir una ruta temporal previa si ahora el usuario da
    una pista más clara o menciona un nombre.
  */
  if (!girl && (clearUnique || nameHit)) {
    girl = best.girl;
    identity_resolution_reason = best.matches[0] || 'scored_unique';
  } else if (
    girl &&
    best.girl &&
    String(best.girl.id || '') !== String(girl.id || '') &&
    (nameHit || (clearUnique && scoreGirl(best.girl, currentText, 3).score > 0))
  ) {
    girl = best.girl;
    identity_resolution_reason = best.matches[0] || 'override_by_current_signal';
  }
}

if (girl) {
  selectedId = String(girl.id || '').trim();
  selectedName = String(girl.nombre || '').trim();
} else {
  selectedId = '';
  selectedName = '';
}

const identity_resolved = !!(selectedId || selectedName);

return [{
  ...$json,
  identity_resolved,
  identity_resolution_reason,
  identity_candidates_count,
  selected_girl_id: identity_resolved ? selectedId : '',
  selected_girl_name: identity_resolved ? selectedName : '',
  speaker_girl_id: identity_resolved ? selectedId : '',
  speaker_girl_name: identity_resolved ? selectedName : '',
  speaker_mode: 'chica',
  wants_more_girls: identity_resolved ? false : !!$json.wants_more_girls,
  memory_file_resolved: identity_resolved && girl ? String(girl.memory_file || genericFile).trim() : genericFile,
  memory_file_tmp_resolved: identity_resolved && girl ? String(girl.memory_file_tmp || genericTmp).trim() : genericTmp,
  memory_lock_resolved: identity_resolved && girl ? String(girl.memory_lock || genericLock).trim() : genericLock
}];
JS;

$expandMemoryTargetsCode = <<<'JS'
const genericFile = '__GENERIC_FILE__';
const genericTmp = '__GENERIC_TMP__';
const genericLock = '__GENERIC_LOCK__';

const resolvedFile = String($json.memory_file_resolved || '').trim();
const resolvedTmp = String($json.memory_file_tmp_resolved || '').trim();
const resolvedLock = String($json.memory_lock_resolved || '').trim();
const identityResolved = !!$json.identity_resolved;

const base = { ...$json };
const out = [];

/*
  SIEMPRE generamos un item para la memoria mixta.
  Esta memoria mixta hace de router persistente por teléfono/hilo.
*/
out.push({
  json: {
    ...base,
    memory_target_kind: 'mix_router',
    memory_file_resolved: genericFile,
    memory_file_tmp_resolved: genericTmp,
    memory_lock_resolved: genericLock,
  }
});

/*
  Si ya hay chica resuelta y su memoria no es la genérica,
  generamos además un segundo item para la memoria individual.
*/
if (identityResolved && resolvedFile !== '' && resolvedFile !== genericFile) {
  out.push({
    json: {
      ...base,
      memory_target_kind: 'girl',
      memory_file_resolved: resolvedFile,
      memory_file_tmp_resolved: resolvedTmp || genericTmp,
      memory_lock_resolved: resolvedLock || genericLock,
    }
  });
}

return out;
JS;

    $identityResolveCode = str_replace(
        array('__GENERIC_FILE__', '__GENERIC_TMP__', '__GENERIC_LOCK__'),
        array($genericFile, $genericTmp, $genericLock),
        $identityResolveCode
    );

    $expandMemoryTargetsCode = str_replace(
        array('__GENERIC_FILE__', '__GENERIC_TMP__', '__GENERIC_LOCK__'),
        array($genericFile, $genericTmp, $genericLock),
        $expandMemoryTargetsCode
    );

    $setLocationCode = <<<'JS'
function norm(s){
  let out = String(s || '').toLowerCase();
  try { out = out.normalize('NFKD'); } catch (e) {}
  out = out.replace(/[\u0300-\u036f]/g, '');
  out = out.replace(/\s+/g, ' ').trim();
  return out;
}

const genericZone = '__GENERIC_ZONE__';
const genericFile = '__GENERIC_FILE__';
const genericTmp = '__GENERIC_TMP__';
const genericLock = '__GENERIC_LOCK__';

const girls = Array.isArray($json.girls_config) ? $json.girls_config : [];
const selectedId = String($json.selected_girl_id || '').trim();
const selectedName = String($json.selected_girl_name || '').trim();

let girl = null;

if (selectedId) {
  girl = girls.find(g => String(g?.id || '').trim() === selectedId) || null;
}
if (!girl && selectedName) {
  const wanted = norm(selectedName);
  girl = girls.find(g => norm(g?.nombre || '') === wanted) || null;
}

const identity_resolved = !!(girl && (String(girl.id || '').trim() || String(girl.nombre || '').trim()));
const resolvedId = identity_resolved ? String(girl.id || '').trim() : '';
const resolvedName = identity_resolved ? String(girl.nombre || '').trim() : '';

return [{
  ...$json,
  identity_resolved,
  selected_girl_id: resolvedId,
  selected_girl_name: resolvedName,
  speaker_girl_id: resolvedId,
  speaker_girl_name: resolvedName,
  speaker_mode: 'chica',
  wants_more_girls: identity_resolved ? false : !!$json.wants_more_girls,
  location_url: identity_resolved ? String(girl.ubicacion_maps || '').trim() : '',
  girl_zona: identity_resolved ? String(girl.zona || '').trim() : genericZone,
  girl_servicios: identity_resolved ? String(girl.servicios || '').trim() : '',
  girl_tarifas: identity_resolved ? String(girl.tarifas || '').trim() : '',
  memory_file_resolved: identity_resolved ? String(girl.memory_file || genericFile).trim() : genericFile,
  memory_file_tmp_resolved: identity_resolved ? String(girl.memory_file_tmp || genericTmp).trim() : genericTmp,
  memory_lock_resolved: identity_resolved ? String(girl.memory_lock || genericLock).trim() : genericLock
}];
JS;

    $setLocationCode = str_replace(
        array('__GENERIC_ZONE__', '__GENERIC_FILE__', '__GENERIC_TMP__', '__GENERIC_LOCK__'),
        array(
            'Estoy en un sitio discreto, con cama grande y buen ambiente.',
            $genericFile,
            $genericTmp,
            $genericLock
        ),
        $setLocationCode
    );

$formatMemoryCode = <<<'JS'
function withinHours(ts,h){try{const d=new Date(ts).getTime();if(!isFinite(d))return false;return(Date.now()-d)<=h*3600*1000;}catch(e){return false;}}
function pickPhone(){try{const e=$node['Extract WA Text']?.json||{};return String(e.from_phone||'');}catch(_){return String($json.from_phone||'');}}
function norm(s){return String(s||'').toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g,'');}
function escapeRe(s){return String(s||'').replace(/[.*+?^${}()|[\]\\]/g,'\\$&');}

function detectTopic(txt){
  const t=norm(txt);
  if(!t) return 'otro';
  const directPrice = /precio|precios|tarif|cuesta|vale\b|sale\b|€|\beur\b|euros/.test(t);
  const qPrice = /(a\s*cuanto|cuanto\s*(es|sale|cuesta|vale)|que\s*vale|a\s*como|cuanto\s*seria)/.test(t);
  const priceAnchor = /(media\s*h|media\s+hora|mediahora|\b50\b|\b100\b|\bhora\b|\b1h\b|60\s*min|30\s*min)/.test(t);
  const etaWords = /(tardas|tarda|llegas|llega|minutos|min\b|en\s+cuanto\s+llegas|cuanto\s+tardas)/.test(t);
  if ((directPrice || (qPrice && priceAnchor)) && !etaWords) return 'precios';
  if(/ubi\b|ubic|donde|direccion|maps|mapa|lugar|calle|punto|pin\b|ubicacion\s*real/.test(t))return'ubicacion';
  if(/puedo ir|voy|me paso|voy ya|me acerco|quiero visitarte|quiero verte hoy|ahora mismo|ahora voy|salgo para alla/.test(t))return'cita/eta';
  if(/servici|haces|ofreces|detalles|como es|que incluye/.test(t))return'servicios';
  if(/pago|bizum|efectivo|transfer|tarjeta/.test(t))return'pago';
  if(/hola|buenas|hey|ola|👋|🙋/.test(t))return'smalltalk';
  return'otro';
}

function isFillerUser(txt){const t=norm(txt);if(!t)return false;
  if(/^(cari|carino|amor|bb|bebe|guapo|hola|buenas|ok|okis|vale|aja|jeje+|jaja+|perfecto|genial|gracias|ok gracias|un saludo|saludos|adios|hasta luego|hasta ahora)$/.test(t))return true;
  if(/^[👍👌✌️❤️😘😉😏😂😅💕😊]+$/.test(String(txt).trim()))return true;
  return false;
}

function yaEnviadoFromReplies(recs){const flags=new Set();
  for(const r of recs){
    const txt=norm(r.reply_text||'');
    const rawReply=String(r.reply_text||'');
    if(!txt&&!rawReply)continue;
    if(/\b(30m|1h|50\s?€|100\s?€|tarifa|precio|precios)\b/.test(txt))flags.add('precios');
    const hasMapLink=/(https?:\/\/)?(goo\.gl\/maps|maps\.app\.goo\.gl|google\.com\/maps|maps\.google\.com)/.test(rawReply)||/@-?\d{1,2}\.\d+,-?-?\d{1,3}\.\d+/.test(rawReply);
    if(/maps|ubicacion|direccion|calle|punto|ubi\b|pin\b/.test(txt)||hasMapLink){flags.add('ubicacion');if(hasMapLink)flags.add('ubicacion_precisa');}
    const hasPhotoLink=/(https?:\/\/(?:ibb\.co|i\.ibb\.co)\/)/.test(rawReply);
    const isRecentPhoto=withinHours(r.ts||'',6);
    if(hasPhotoLink&&isRecentPhoto)flags.add('fotos');
    if(/detalle|incluye|ofrezco|ofrece|servici/.test(txt))flags.add('servicios');
  }
  return Array.from(flags);
}

function detectTarifaElegida(recs){
  for(let i=recs.length-1;i>=0;i--){
    const raw=recs[i].user_msg||'';
    const u=norm(raw);
    if(!u)continue;
    const hasAcepta=/\b(vale|ok|de acuerdo|me vale|me cuadra|perfecto|cojo|quiero|me quedo|pillo|prefiero)\b/.test(u);
    const msgCorta=u.replace(/[^0-9a-z€ ]/g,'').trim();
    const esMsgSimple=msgCorta.length>0&&msgCorta.length<=25;
    const acepta=hasAcepta||esMsgSimple;
    if(acepta&&(/\b50\s*(euros|eur|€)?\b/.test(u)||/(media\s*h|mediahora|media\s+hora|30\s*min)/.test(u)))return'50';
    if(acepta&&(/\b100\s*(euros|eur|€)?\b/.test(u)||/\b(una\s+hora|la\s+hora|1h|60\s*min)\b/.test(u)))return'100';
  }
  return'';
}

function detectMapsSent(recs){const re=/(https?:\/\/)?(goo\.gl\/maps|maps\.app\.goo\.gl|google\.com\/maps|maps\.google\.com)/i;for(const r of recs){if(re.test(String(r.reply_text||'')))return true;}return false;}

function userWantsMoreGirls(txt){
  const t=norm(txt);
  if(!t) return false;
  return /(mas\s+chicas|todas|todas\s+las\s+chicas|otras\s+chicas|que\s+opciones|quienes\s+hay|tienes\s+mas|alguna\s+mas|mas\s+fotos\s+de\s+las\s+chicas)/.test(t);
}

function userWantsMapWords(txt){
  const t=norm(txt);
  if(!t) return false;
  return /(\bubi\b|ubic|maps\b|mapa\b|direccion|pin\b|punto\s+exacto|ubicacion\s*real|pasame\s+la\s+ubi|pasa\s+el\s+maps|mandame\s+la\s+direccion)/.test(t);
}

function levLimit(a,b,limit){
  a=String(a||'');b=String(b||'');
  if(a===b) return 0;
  const la=a.length, lb=b.length;
  if(Math.abs(la-lb)>limit) return limit+1;
  let prev=new Array(lb+1);let cur=new Array(lb+1);
  for(let j=0;j<=lb;j++) prev[j]=j;
  for(let i=1;i<=la;i++){
    cur[0]=i;
    let rowMin=cur[0];
    const ca=a.charCodeAt(i-1);
    for(let j=1;j<=lb;j++){
      const cost=(ca===b.charCodeAt(j-1))?0:1;
      const v=Math.min(prev[j]+1,cur[j-1]+1,prev[j-1]+cost);
      cur[j]=v;
      if(v<rowMin) rowMin=v;
    }
    if(rowMin>limit) return limit+1;
    const tmp=prev;prev=cur;cur=tmp;
  }
  return prev[lb];
}

function findMentionedGirl(txt, activeGirls){
  const t=norm(txt);
  if(!t) return null;
  const tokens = t.split(/\s+/).filter(Boolean);
  for(const g of (activeGirls||[])){
    const name=String(g?.nombre||'').trim();
    if(!name) continue;
    const n=norm(name);
    if(!n) continue;
    const re=new RegExp('(^|[^a-z0-9])'+escapeRe(n)+'([^a-z0-9]|$)','i');
    if(re.test(t)) return g;
    const nParts=n.split(/\s+/).filter(Boolean);
    if(nParts.length>=2){
      let ok=true;
      for(const part of nParts){
        const rep=new RegExp('(^|[^a-z0-9])'+escapeRe(part)+'([^a-z0-9]|$)','i');
        if(!rep.test(t)){ok=false;break;}
      }
      if(ok) return g;
    }
    const base=nParts[0]||n;
    if(base.length>=4){
      for(const tok of tokens){
        if(tok.length<3) continue;
        const lim=(base.length<=6)?1:2;
        if(levLimit(tok,base,lim)<=lim) return g;
      }
    }
  }
  return null;
}

function extractEtaMinutesFromText(txt){
  const t=norm(txt);
  if(!t) return 0;
  const U='(?:min(?:utos?)?|miutos?|mins?|mnts?)';
  let m=t.match(new RegExp('\\b(\\d{1,3})\\s*(?:-|a|hasta|y)\\s*(\\d{1,3})\\s*'+U+'\\b'));
  if(m){const a=Number(m[1]);const b=Number(m[2]);if(Number.isFinite(a)&&Number.isFinite(b)){const v=Math.round((a+b)/2);if(v>=1&&v<=180)return v;}}
  m=t.match(new RegExp('\\b(?:en|llego\\s*en|llegare\\s*en|llegaria\\s*en|tardo\\s*(?:unos)?|tardare\\s*(?:unos)?|tardaria\\s*(?:unos)?|estoy\\s*en)\\s*(\\d{1,3})\\s*'+U+'\\b'));
  if(m){const v=Number(m[1]);if(Number.isFinite(v)&&v>=1&&v<=180)return v;}
  m=t.match(new RegExp('\\b(\\d{1,3})\\s*'+U+'\\b'));
  if(m){const v=Number(m[1]);if(Number.isFinite(v)&&v>=1&&v<=180)return v;}
  return 0;
}

function countBotEmojiRecent(recent){
  const reEmoji=/[❤️😘😉😏😂😅💕😊]/g;
  let count=0;
  const lastBot=recent.filter(r=>String(r.reply_text||'').trim()).slice(-4);
  for(const r of lastBot){const s=String(r.reply_text||'');const m=s.match(reEmoji);if(m)count+=m.length;}
  return count;
}

function countHaggleRecent(recent){
  const re=/(rebaja|descuento|mejor\s*precio|barat|hazme\s*precio|ajusta|te\s*doy\s*\d+|\b70\b|\b80\b|\b90\b|\b60\b)/i;
  let c=0;
  const lastUser=recent.slice(-10);
  for(const r of lastUser){const u=String(r.user_msg||'');if(u&&re.test(u))c++;}
  return c;
}

function detectConversationEndIntent(txt){
  const t=norm(txt);
  if(!t) return false;
  return /(adios|hasta\s*luego|hasta\s*ahora|me\s*voy|otro\s*dia|luego\s*hablamos|ya\s*te\s*digo|gracias\s*y\s*perdon|vale\s*gracias|ok\s*gracias)/.test(t);
}

function detectInteresFuerte(txt){
  const t=norm(txt);
  if(!t) return false;
  return /(voy\s*ya|voy\s*para\s*alla|salgo\s*para\s*alla|ahora\s*voy|me\s*paso\s*ya|quiero\s*ir\s*ya|ahora\s*mismo|en\s*un\s*rato\s*voy|voy\s*en\s*\d+)/.test(t);
}

function detectVisitBeforeChoice(txt){
  const t=norm(txt);
  if(!t) return false;
  return /(puedo\s*ver|ver\s*antes|pasar\s*a\s*ver|echar\s*un\s*visto|presentacion|paseillo|pasillo|mirar\s*opciones|conocer\s*antes|verlos\s*en\s*persona|ver\s*las\s*masajistas|ver\s*quien\s+hay|puedo\s*pasarme\s*a\s*mirar)/.test(t);
}

function buildRecentBotRepliesNorm(recent){
  const arr=[];
  for(let i=Math.max(0,(recent||[]).length-10);i<(recent||[]).length;i++){
    const r=recent[i];
    const b=String(r?.reply_text||'').trim();
    if(!b)continue;
    arr.push(norm(b));
  }
  return Array.from(new Set(arr.filter(Boolean))).slice(-8);
}

function asBool(x){
  if(typeof x==='boolean') return x;
  if(typeof x==='number') return x!==0;
  if(typeof x==='string'){const s=x.trim().toLowerCase();return ['true','1','yes','y','si','sí'].includes(s);}
  return false;
}

function firstPersistedSpeakerGirl(recs){
  for(let i=0;i<(recs||[]).length;i++){
    const r=recs[i]||{};
    const n=String(r.speaker_girl_name||'').trim();
    const id=String(r.speaker_girl_id||'').trim();
    if(n) return {name:n,id:id};
  }
  return {name:'',id:''};
}

function lastPersistedSelectedGirl(recs){
  for(let i=(recs||[]).length-1;i>=0;i--){
    const r=recs[i]||{};
    const n=String(r.selected_girl_name||'').trim();
    const id=String(r.selected_girl_id||'').trim();
    if(n) return {name:n,id:id};
  }
  return {name:'',id:''};
}

function lastValidRouteGirl(recs){
  for (let i = recs.length - 1; i >= 0; i--) {
    const r = recs[i] || {};
    const id = String(r.route_girl_id || '').trim();
    const name = String(r.route_girl_name || '').trim();
    const expiresAt = String(r.route_expires_at || '').trim();
    if (!id && !name) continue;
    if (expiresAt) {
      const ms = Date.parse(expiresAt);
      if (isFinite(ms) && ms < Date.now()) continue;
    }
    return {
      id,
      name,
      expires_at: expiresAt
    };
  }
  return {
    id: '',
    name: '',
    expires_at: ''
  };
}

function isExplicitServiceChoice(txt){
  const t=norm(txt);
  if(!t) return false;
  return /(quiero\s+(ir\s+con|a|con)|me\s+quedo\s+con|prefiero\s+(a\s+)?|reservo\s+con|cita\s+con|voy\s+con|al\s+final\s+(me\s+quedo|quiero)|me\s+gusta\s+mas|me\s+mola\s+mas|esta\s+me\s+gusta|con\s+esta\s+(quiero|me\s+quedo|prefiero))/.test(t);
}

const formatMode = '__FORMAT_MODE__';
const genericFile = '__GENERIC_FILE__';
const resolvedMemoryFile = String($json.memory_file_resolved || '').trim();
const isMixLike = (formatMode === 'mix') || !resolvedMemoryFile || resolvedMemoryFile === genericFile;

const RAW=$json.memory_text_raw||'';
const phone=pickPhone();
const lines=RAW.split('\n').filter(l=>l.trim().length>0);
const recs=[];
for(const l of lines){try{const o=JSON.parse(l);if(String(o.phone||'')===phone){if(typeof o.user_msg==='string'){o.user_msg=o.user_msg.replace(/^Cliente:\s*/,'');}recs.push(o);}}catch(e){}}
recs.sort((a,b)=>((a._seq||0)-(b._seq||0)));

const last6h=recs.filter(r=>withinHours(r.ts||'',6));
const session_reset=last6h.length===0;
let recent=session_reset?[]:last6h.slice(-20);

let thread_id='';
if(!session_reset){const latest=last6h[last6h.length-1];thread_id=String(latest.thread_id||('th-'+Date.now()));}
else{thread_id='th-'+Date.now();}

const hist=recent.map(r=>{
  const u=(r.user_msg||'').slice(0,200);
  const b=(r.reply_text||'').slice(0,200);
  const ts=r.ts||'';
  return'-['+ts+'] U: '+u+' | B: '+b;
}).join('\n');

const currentMsg=String($node['Extract WA Text']?.json?.message_text||'');
const topic_actual=detectTopic(currentMsg);

let lastUserMsg='';
for(let i=recs.length-1;i>=0;i--){const um=String(recs[i].user_msg||'').trim();if(um){lastUserMsg=um;break;}}
const last_topic=lastUserMsg?detectTopic(lastUserMsg):'otro';
const topic_cambiado=topic_actual!==last_topic&&last_topic!=='otro';

const ya_enviado=yaEnviadoFromReplies(recs);
let pendiente=null;
if(topic_actual==='precios'&&!ya_enviado.includes('precios'))pendiente='precios';
else if(topic_actual==='ubicacion'&&!ya_enviado.includes('ubicacion'))pendiente='ubicacion';
else if(topic_actual==='servicios'&&!ya_enviado.includes('servicios'))pendiente='servicios';

const memory_text=hist||'Sin memoria reciente.';
const tone_reset=session_reset;

let last_bot_reply='';
let last_user_message='';
for(let i=recent.length-1;i>=0;i--){
  const r=recent[i];
  if(!last_bot_reply&&r.reply_text&&String(r.reply_text).trim())last_bot_reply=String(r.reply_text);
  if(!last_user_message&&r.user_msg&&String(r.user_msg).trim())last_user_message=String(r.user_msg);
  if(last_bot_reply&&last_user_message)break;
}

let last_user_meaningful='';
for(let i=recs.length-1;i>=0;i--){const um=String(recs[i].user_msg||'').trim();if(!um)continue;if(!isFillerUser(um)){last_user_meaningful=um;break;}}
const current_is_filler=isFillerUser(currentMsg);

const tarifa_elegida=detectTarifaElegida(recent);
const maps_sent=detectMapsSent(recent);

let girls_config=[];
try{ girls_config=$node['Girls Config (from remote JSON)']?.json?.girls_config; }catch(e){ girls_config=[]; }
if(!Array.isArray(girls_config)){
  girls_config=Array.isArray($json.girls_config)?$json.girls_config:[];
}

girls_config=girls_config.map(g=>{
  const o=(g&&typeof g==='object')?g:{};
  return{
    ...o,
    id:String(o.id||'').trim(),
    nombre:String(o.nombre||'').trim(),
    activa:asBool(o.activa),
    fotos:Array.isArray(o.fotos)?o.fotos:[]
  };
});

const activeGirls=girls_config.filter(g=>asBool(g.activa)&&String(g.nombre||'').trim());
const wants_more_girls=userWantsMoreGirls(currentMsg)||userWantsMoreGirls(last_user_meaningful);
const mixRoute = lastValidRouteGirl(recs);

let speaker_girl_name = '';
let speaker_girl_id   = '';
let selected_girl_name = '';
let selected_girl_id   = '';

if (!isMixLike) {
  const persistedSpeaker  = firstPersistedSpeakerGirl(recs);
  const persistedSelected = lastPersistedSelectedGirl(recs);
  const selInCurrent      = findMentionedGirl(currentMsg, activeGirls);

  if(persistedSpeaker.name){
    speaker_girl_name = persistedSpeaker.name;
    speaker_girl_id   = persistedSpeaker.id;
  } else {
    let firstSel = selInCurrent || findMentionedGirl(last_user_meaningful, activeGirls);
    if(!firstSel){
      for(let i=0;i<recent.length;i++){
        const um=String(recent[i]?.user_msg||'');
        const g=findMentionedGirl(um,activeGirls);
        if(g){firstSel=g;break;}
      }
    }
    speaker_girl_name = firstSel?String(firstSel.nombre||'').trim():'';
    speaker_girl_id   = firstSel?String(firstSel.id||'').trim():'';
  }

  if(persistedSelected.name){
    selected_girl_name = persistedSelected.name;
    selected_girl_id   = persistedSelected.id;
    if(
      selInCurrent &&
      norm(selInCurrent.nombre) !== norm(persistedSelected.name) &&
      isExplicitServiceChoice(currentMsg)
    ){
      selected_girl_name = String(selInCurrent.nombre||'').trim();
      selected_girl_id   = String(selInCurrent.id||'').trim();
    }
  } else {
    let firstSel = selInCurrent || findMentionedGirl(last_user_meaningful, activeGirls);
    if(!firstSel){
      for(let i=recent.length-1;i>=0;i--){
        const um=String(recent[i]?.user_msg||'');
        const g=findMentionedGirl(um,activeGirls);
        if(g){firstSel=g;break;}
      }
    }
    selected_girl_name = firstSel?String(firstSel.nombre||'').trim():'';
    selected_girl_id   = firstSel?String(firstSel.id||'').trim():'';
  }
}

let speaker_mode = 'encargada';
if(!isMixLike && speaker_girl_name){ speaker_mode = 'chica'; }

let photos_sent_recent=false;
for(let i=recent.length-1;i>=0;i--){
  const rr=String(recent[i]?.reply_text||'');
  if(/https?:\/\/(?:ibb\.co|i\.ibb\.co)\//i.test(rr)&&withinHours(recent[i]?.ts||'',6)){photos_sent_recent=true;break;}
}

const must_choose_girl_now=(!selected_girl_name)&&(userWantsMapWords(currentMsg)||topic_actual==='ubicacion'||topic_actual==='cita/eta');

let choose_loop_count=0;
if(!selected_girl_name){
  for(let i=recent.length-1;i>=0;i--){
    const um=String(recent[i]?.user_msg||'');
    if(!um.trim())continue;
    if(userWantsMapWords(um)||detectTopic(um)==='ubicacion')choose_loop_count++;
    else break;
  }
}

const eta_from_user_minutes=extractEtaMinutesFromText(currentMsg);
const eta_from_user_flag=eta_from_user_minutes>0;

const emoji_count_recent=countBotEmojiRecent(recent);
const haggle_count_recent=countHaggleRecent(recent);
const conversation_end_intent=detectConversationEndIntent(currentMsg);
const interes_fuerte=detectInteresFuerte(currentMsg)||topic_actual==='cita/eta';
const ubicacion_pedida_fuerte=userWantsMapWords(currentMsg)||topic_actual==='ubicacion';

const recent_saludo=(function(){
  const t=norm(currentMsg);
  if(!t) return false;
  const isHello=/^(hola|holaa+|buenas|hey|ola)\b/.test(t)||/\b(hola|buenas)\b/.test(t);
  if(!isHello) return false;
  return !String(last_bot_reply||'').trim();
})();

const sales_stage=(function(){
  if(maps_sent&&eta_from_user_flag)return'lead';
  if(maps_sent)return'eta';
  if(must_choose_girl_now)return'seleccion';
  if(tarifa_elegida)return'tarifas_cerradas';
  if(topic_actual==='precios')return'tarifas';
  if(topic_actual==='ubicacion')return'ubicacion';
  if(topic_actual==='servicios')return'servicios';
  return'info';
})();

const wants_visit_before_choice=detectVisitBeforeChoice(currentMsg)||detectVisitBeforeChoice(last_user_meaningful);
const recent_bot_replies_norm=buildRecentBotRepliesNorm(recent);
const identity_resolved = !isMixLike && !!(selected_girl_id || selected_girl_name || speaker_girl_id || speaker_girl_name);

return [{
  ...$json,
  memory_text,
  thread_id,
  tone_reset,
  topic_actual,
  topic_cambiado,
  ya_enviado,
  pendiente,
  session_reset,
  last_bot_reply,
  last_user_message,
  tarifa_elegida,
  maps_sent,
  last_user_meaningful,
  current_is_filler,
  girls_config,
  activeGirls,
  speaker_girl_id,
  speaker_girl_name,
  selected_girl_id,
  selected_girl_name,
  speaker_mode,
  wants_more_girls,
  photos_sent_recent,
  must_choose_girl_now,
  choose_loop_count,
  eta_from_user_minutes,
  eta_from_user_flag,
  emoji_count_recent,
  haggle_count_recent,
  recent_saludo,
  sales_stage,
  conversation_end_intent,
  interes_fuerte,
  ubicacion_pedida_fuerte,
  wants_visit_before_choice,
  recent_bot_replies_norm,
  route_girl_id: mixRoute.id,
  route_girl_name: mixRoute.name,
  route_expires_at: mixRoute.expires_at,
  route_is_valid: !!(mixRoute.id || mixRoute.name),
  identity_resolved
}];
JS;

    $formatMemoryCodeMix = str_replace(
        array('__FORMAT_MODE__', '__GENERIC_FILE__'),
        array('mix', $genericFile),
        $formatMemoryCode
    );

    $formatMemoryCodeEffective = str_replace(
        array('__FORMAT_MODE__', '__GENERIC_FILE__'),
        array('effective', $genericFile),
        $formatMemoryCode
    );

    $assembleContextCode = <<<'JS'
function safeText(s){return(typeof s==='string'?s:'').trim();}
function countBotMsgs(mem){if(!mem)return 0;const lines=String(mem).split('\n');let c=0;for(const line of lines){if(line.includes('| B:')){const parts=line.split('| B:');if(parts[1]&&String(parts[1]).trim())c++;}}return c;}

const ext = ($node["Extract WA Text"] && $node["Extract WA Text"].json) ? $node["Extract WA Text"].json : {};
const setPrompt = ($node["Set Prompt"] && $node["Set Prompt"].json) ? $node["Set Prompt"].json : {};
const playbook = ($node["Bin2Text Playbook"] && $node["Bin2Text Playbook"].json) ? $node["Bin2Text Playbook"].json : {};

const fm = $json || {};

const from_phone = String(ext.from_phone || fm.from_phone || '');
let message_text = safeText(ext.message_text || fm.user_message || '');
message_text = message_text.replace(/^Cliente:\s*/i,'');

const prompt_text = String(setPrompt.prompt_text || '');
const playbook_text = String(playbook.playbook_text || '');
const memory_text = String(fm.memory_text || '');
const bot_msg_count_recent = countBotMsgs(memory_text);

return [{
  user_message: message_text || '',
  from_phone,
  prompt_text,
  memory_text,
  thread_id: fm.thread_id || '',
  tone_reset: !!fm.tone_reset,
  playbook_text,
  topic_actual: fm.topic_actual || '',
  topic_cambiado: !!fm.topic_cambiado,
  ya_enviado: fm.ya_enviado || [],
  pendiente: (typeof fm.pendiente === 'string' && fm.pendiente.length ? fm.pendiente : null),
  session_reset: !!fm.session_reset,
  last_bot_reply: fm.last_bot_reply || '',
  last_user_message: fm.last_user_message || '',
  tarifa_elegida: fm.tarifa_elegida || '',
  maps_sent: !!fm.maps_sent,
  interes_fuerte: !!fm.interes_fuerte,
  ubicacion_pedida_fuerte: !!fm.ubicacion_pedida_fuerte,
  emoji_count_recent: Number(fm.emoji_count_recent || 0),
  last_emoji: fm.last_emoji || '',
  bot_msg_count_recent,
  recent_saludo: !!fm.recent_saludo,
  last_user_meaningful: fm.last_user_meaningful || '',
  client_name: fm.client_name || '',
  last_open_question: fm.last_open_question || '',
  current_name_candidate: fm.current_name_candidate || '',
  current_is_filler: !!fm.current_is_filler,
  sales_stage: fm.sales_stage || '',
  selected_girl_id: fm.selected_girl_id || '',
  selected_girl_name: fm.selected_girl_name || '',
  speaker_girl_id: fm.selected_girl_id || fm.speaker_girl_id || '',
  speaker_girl_name: fm.selected_girl_name || fm.speaker_girl_name || '',
  wants_more_girls: !!fm.wants_more_girls,
  conversation_end_intent: !!fm.conversation_end_intent,
  photos_sent_recent: !!fm.photos_sent_recent,
  must_choose_girl_now: !!fm.must_choose_girl_now,
  choose_loop_count: Number(fm.choose_loop_count || 0),
  haggle_count_recent: Number(fm.haggle_count_recent || 0),
  speaker_mode: 'chica',
  eta_from_user_minutes: Number(fm.eta_from_user_minutes || 0),
  eta_from_user_flag: !!fm.eta_from_user_flag,
  girls_config: fm.girls_config || [],
  activeGirls: fm.activeGirls || [],
  wants_visit_before_choice: !!fm.wants_visit_before_choice,
  recent_bot_replies_norm: fm.recent_bot_replies_norm || [],
  identity_resolved: !!fm.identity_resolved
}];
JS;

$buildToneCode = <<<'JS'
let t={sentiment:'neutro',register:'coloquial',urgency:'media'};
try{t=JSON.parse($json.choices?.[0]?.message?.content||'{}');}catch(e){}

const ctx=$("Assemble Context (No-Merge)").item.json||{};
const identity_resolved=!!ctx.identity_resolved;
const identity_resolution_reason=String(ctx.identity_resolution_reason||'');
const identity_candidates_count=Number(ctx.identity_candidates_count||0);
const emoji_count_recent=Number(ctx.emoji_count_recent||0);
const conversation_end_intent=!!ctx.conversation_end_intent;
const interes_fuerte=!!ctx.interes_fuerte;
const haggle_count_recent=Number(ctx.haggle_count_recent||0);
const choose_loop_count=Number(ctx.choose_loop_count||0);
const selected_girl_name=String(ctx.selected_girl_name||'').trim();
const sales_stage=String(ctx.sales_stage||'');
const user_message=String(ctx.user_message||'').trim();
const topic_actual=String(ctx.topic_actual||'').trim();
const recent_saludo=!!ctx.recent_saludo;
const last_bot_reply=String(ctx.last_bot_reply||'').trim();
const ya_enviado=Array.isArray(ctx.ya_enviado)?ctx.ya_enviado:[];
const maps_sent=!!ctx.maps_sent;
const photos_sent_recent=!!ctx.photos_sent_recent;

function norm(s){
  return String(s||'').toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g,'').trim();
}
const msgNorm=norm(user_message);

let isImportant=false;
if(['precios','ubicacion','servicios','pago','cita/eta'].includes(topic_actual)) isImportant=true;
else if(/precio|tarif|€|eur|foto|fotos|ubic|maps|mapa|direccion|donde|servici|haces|ofreces|disponib|estas|ahora|ventas|centro|madrid|valencia|pelirroja|morena|rubia|latina/.test(msgNorm)) isImportant=true;

let baseDir='Usa registro '+(t.register||'coloquial')+', tono '+(t.sentiment==='negativo'?'calmado y empatico':'cercano y carinoso')+', urgencia '+(t.urgency||'media')+'. ';
baseDir+='Tono femenino, cariñoso, sugerente, natural, corto y humano. Evita sonar a bot. ';

let identityDir='';
if(identity_resolved){
  identityDir+='Identity resolved: ya eres '+JSON.stringify(selected_girl_name||'la chica elegida')+'. Hablas en primera persona como ella. No abras mas ambiguedad. Usa sus datos reales cuando toque. ';
  identityDir+='Si te piden fotos, envialas directas usando girls_config y sin poner condiciones de hora, ETA o venir primero. ';
}else{
  identityDir+='Identity unresolved: hablas SIEMPRE en primera persona como si fueras la misma chica del anuncio. ';
  identityDir+='Bajo ninguna circunstancia delates que puede haber varias chicas o varios anuncios detras del mismo telefono. ';
  identityDir+='NO uses frases como por cual de mis anuncios, tengo varias, mis chicas, mis companeras o similares. ';
  identityDir+='Si necesitas aclarar identidad, pide UNA sola pista sutil tipo zona, ciudad o rasgo fisico, sin sonar a catalogo. ';
  identityDir+='Nunca enumeres nombres de chicas ni inventes opciones. ';
  identityDir+='No pongas condiciones tipo hora, ETA o venir primero para responder. ';
}

let clueDir='';
if(!identity_resolved){
  clueDir+='Si el usuario ya dio una pista clara de zona, ciudad o aspecto que encaja con una sola chica, da la identidad por cerrada y responde en consecuencia sin seguir preguntando. ';
  if(identity_candidates_count > 1){
    clueDir+='Si aun queda ambiguedad real, pide solo UNA pista mas y que sea sutil. ';
  }
  if(identity_resolution_reason){
    clueDir+='Ultimo indicio tecnico de resolucion: '+JSON.stringify(identity_resolution_reason)+'. ';
  }
}

let emojiDir='';
if(conversation_end_intent){
  emojiDir+='No uses emoji. ';
}else if(isImportant && emoji_count_recent>=1){
  emojiDir+='Sin emoji, estas dando informacion importante. ';
}else if(emoji_count_recent>=3){
  emojiDir+='NO uses emoji en este mensaje (ya hay bastantes). ';
}else if(interes_fuerte && emoji_count_recent<=1){
  emojiDir+='La conversacion esta caliente. Puedes usar 1-2 emojis picantes si pegan. ';
}else if(emoji_count_recent>=2){
  emojiDir+='Este mensaje sin emoji, toca descansar. ';
}else{
  emojiDir+='Puedes usar 1 emoji suave al final si pega (sin forzar). ';
}

let lengthDir='';
if(isImportant) lengthDir='Responde claro y breve, maximo 2 frases salvo que haya varias preguntas. ';
else lengthDir='Responde ultra natural y corta, normalmente 1 frase o 2 como mucho. ';

let multiDir='Si el usuario hace varias preguntas en el mismo mensaje, responde a todas en la misma respuesta de forma breve. ';
let ambiguityDir='No uses frases tipo no se a que te refieres. Si hay ambiguedad, deduce por contexto o aclara con una sola pregunta concreta. ';
let menuDir='No metas menus rigidos ni reinicies la conversacion si el usuario ya pregunto algo concreto. ';
let availabilityDir='Si preguntan por disponibilidad, contesta primero disponibilidad. ';
let shortReplyDir='Si el usuario responde solo si, vale, ok o emojis, interpretalo como continuacion natural de tu ultima pregunta concreta. ';

let memoryDir='';
if(ya_enviado.includes('precios')) memoryDir+='Si ya se dieron precios, no los presentes como si fuera la primera vez. ';
if(maps_sent) memoryDir+='Si ya se envio la ubicacion, no vuelvas a ofrecerla como si no se hubiera mandado. ';
if(identity_resolved) memoryDir+='Con la identidad ya resuelta, no vuelvas a pedir pista de anuncio, zona o perfil. ';
if(photos_sent_recent) memoryDir+='Si ya enviaste fotos recientemente, no lo presentes como si nunca hubieras mandado nada. ';

let greetingDir='';
if(recent_saludo || /^(hola|buenas|holaa|ola)\b/i.test(last_bot_reply)){
  greetingDir+='No repitas saludo otra vez; entra directo al contenido. ';
}

let closeDir='';
if(conversation_end_intent){
  closeDir+='El usuario esta cerrando. Responde una sola vez, muy corto, amable, sin reabrir conversacion ni meter preguntas. ';
}

let haggleDir='';
if(haggle_count_recent>=3){
  haggleDir+='Regateo repetido: firme y corta, sin descuentos, sin discutir. ';
}else if(haggle_count_recent>=2){
  haggleDir+='Regateo: mas firme, sin descuentos, reconduce sin enrollarte. ';
}

let loopDir='';
if(!identity_resolved && choose_loop_count>=3){
  loopDir+='Si sigue sin quedar claro, no abras catalogo ni listas. Pide solo una pista breve y natural, o responde con lo minimo generico permitido. ';
}

let stageDir='sales_stage actual: '+JSON.stringify(sales_stage)+'. No retrocedas en la conversacion. ';
let userDir='Ultimo mensaje del usuario: '+JSON.stringify(user_message)+'. ';

const dir =
  baseDir +
  identityDir +
  clueDir +
  emojiDir +
  lengthDir +
  multiDir +
  ambiguityDir +
  menuDir +
  availabilityDir +
  shortReplyDir +
  memoryDir +
  greetingDir +
  closeDir +
  haggleDir +
  loopDir +
  stageDir +
  userDir;

return [{...ctx,tone_directives:dir}];
JS;

$appendMemoryCode = <<<'JS'
function safeNodeJson(name){ try { return $node[name].json || {}; } catch(e){ return {}; } }
function firstNonEmpty(arr){ for(const v of arr){ const s=String(v||'').trim(); if(s) return s; } return ''; }
function asBool(x){
  if (typeof x === 'boolean') return x;
  if (typeof x === 'number') return x !== 0;
  if (typeof x === 'string') {
    const s = x.trim().toLowerCase();
    return ['true','1','yes','y','si','sí'].includes(s);
  }
  return false;
}

const prev = $json.mem_prev_raw || '';
const NO = safeNodeJson('Normalize Output');
const AO = safeNodeJson('Audio Auto Reply');
const EXT = safeNodeJson('Extract WA Text');

const targetKind = String($json.memory_target_kind || 'mix_router').trim();

const phone = String(firstNonEmpty([NO.from_phone, AO.from_phone, EXT.from_phone, $json.from_phone]));
let rawUser = firstNonEmpty([NO.user_message, AO.user_message, EXT.message_text, $json.user_message]);
rawUser = String(rawUser || '').replace(/^Cliente:\s*/,'');
if (!rawUser.trim()) rawUser = '[SIN_TEXTO]';

const user_msg = rawUser;
const reply_text = String(firstNonEmpty([NO.output_text, AO.output_text, $json.output_text]));
const ts = new Date().toISOString();
const thread_id = String(firstNonEmpty([NO.thread_id, AO.thread_id, $json.thread_id, ('th-' + Date.now())]));

const lines = prev.split('\n').filter(l => l.trim().length > 0);
const recs = [];
for (const l of lines) {
  try { recs.push(JSON.parse(l)); } catch (e) {}
}

const nextSeq = (recs.reduce((m,r) => Math.max(m, r._seq || 0), 0) + 1);

const selected_girl_id = String(firstNonEmpty([NO.selected_girl_id, AO.selected_girl_id, $json.selected_girl_id]));
const selected_girl_name = String(firstNonEmpty([NO.selected_girl_name, AO.selected_girl_name, $json.selected_girl_name]));
const identity_resolved = asBool(firstNonEmpty([NO.identity_resolved, AO.identity_resolved, $json.identity_resolved]));
const conversation_end_intent = asBool(firstNonEmpty([NO.conversation_end_intent, AO.conversation_end_intent, $json.conversation_end_intent]));

/*
  TTL del router de la mixta:
  - si ya está cerrando la conversación, no dejamos ruta viva
  - si no, dejamos una ruta corta para que "ok / si / voy" siga sabiendo la chica
*/
const shouldKeepRoute = identity_resolved && !conversation_end_intent;
const routeTTLMinutes = 45;
const route_expires_at = shouldKeepRoute
  ? new Date(Date.now() + (routeTTLMinutes * 60 * 1000)).toISOString()
  : '';

let newRec = null;

if (targetKind === 'girl') {
  // Memoria REAL de la chica: aquí sí guardamos identidad completa
  newRec = {
    _seq: nextSeq,
    ts,
    phone,
    user_msg,
    reply_text,
    thread_id,
    selected_girl_id,
    selected_girl_name,
    speaker_girl_id: selected_girl_id,
    speaker_girl_name: selected_girl_name,
    speaker_mode: 'chica',
    identity_resolved: identity_resolved,
    route_girl_id: '',
    route_girl_name: '',
    route_expires_at: ''
  };
} else {
  // Mixta/router: JAMÁS persistimos speaker/selected como identidad cerrada
  newRec = {
    _seq: nextSeq,
    ts,
    phone,
    user_msg,
    reply_text,
    thread_id,
    selected_girl_id: '',
    selected_girl_name: '',
    speaker_girl_id: '',
    speaker_girl_name: '',
    speaker_mode: 'encargada',
    identity_resolved: false,
    route_girl_id: shouldKeepRoute ? selected_girl_id : '',
    route_girl_name: shouldKeepRoute ? selected_girl_name : '',
    route_expires_at
  };
}

const outText = recs.concat([newRec]).slice(-1000).map(o => JSON.stringify(o)).join('\n') + '\n';

return [{ ...$json, memory_ndjson_out: outText }];
JS;

    $audioAutoReplyCode = <<<'JS'
function norm(s){
  let out = String(s||'').toLowerCase();
  try{ out = out.normalize('NFKD'); }catch(e){}
  out = out.replace(/[\u0300-\u036f]/g,'');
  out = out.replace(/\s+/g,' ').replace(/[\.,!\?;:]/g,'').trim();
  return out;
}

const prev = $json || {};
const last = String(prev.last_bot_reply || '').trim();
const lastN = norm(last);

const variants = [
  'no puedo escuchar audios amor, me lo escribes mejor?',
  'amor por aqui no escucho audios, escribeme y te digo',
  'cari no puedo oir audios ahora, me lo pones en texto?',
  'me va mejor si me lo escribes amor',
  'guapo no puedo reproducir audios, escribeme un momentito'
];

let pick = variants[Math.floor(Math.random()*variants.length)];
if (variants.length > 1) {
  let guard = 0;
  while (norm(pick) === lastN && guard < 10) {
    pick = variants[Math.floor(Math.random()*variants.length)];
    guard++;
  }
}

const ext = ($node && $node["Extract WA Text"] && $node["Extract WA Text"].json) ? $node["Extract WA Text"].json : {};
const from_phone = String(ext.from_phone || prev.from_phone || '');
const thread_id = String(prev.thread_id || ('th-' + Date.now()));
const selected_girl_id = String(prev.selected_girl_id || '');
const selected_girl_name = String(prev.selected_girl_name || '');
const identity_resolved = !!prev.identity_resolved;

return [{
  ...prev,
  output_text: String(pick || '').trim(),
  lead_detected: false,
  lead_flag: '0',
  lead_numeric: 0,
  lead_confidence: 0,
  eta_minutes: 0,
  user_message: '[AUDIO]',
  from_phone,
  thread_id,
  last_bot_reply: last,
  selected_girl_id,
  selected_girl_name,
  speaker_girl_id: selected_girl_id,
  speaker_girl_name: selected_girl_name,
  speaker_mode: 'chica',
  identity_resolved
}];
JS;

    $gateLeadCode = <<<'JS'
function asBool(x){
  if (typeof x==='boolean') return x;
  if (typeof x==='number') return x!==0;
  if (typeof x==='string') {
    const s=x.trim().toLowerCase();
    return ['true','1','yes','y','si','sí'].includes(s);
  }
  return false;
}

function digits(s){
  return String(s||'').replace(/[^0-9]/g,'');
}

function firstNonEmpty(arr){
  for (const v of arr) {
    const s = String(v ?? '').trim();
    if (s) return s;
  }
  return '';
}

function tryNodeJson(name){
  try { return $node[name].json || {}; } catch(e){ return {}; }
}

const leadDetected = asBool($json.lead_detected)
  || (Number($json.lead_numeric)||0)===1
  || String($json.lead_flag||'').trim()==='1';

if (!leadDetected) return [];

const EXT = tryNodeJson('Extract WA Text');
const FM  = tryNodeJson('Format Effective Memory');
const WH  = tryNodeJson('WAHA Webhook In');
const ANTI = tryNodeJson('Build WAHA Antiban');

const whBody = (WH.body && typeof WH.body==='object') ? WH.body : {};
const whPayload = (whBody.payload && typeof whBody.payload==='object') ? whBody.payload : (WH.payload || {});

const phone = firstNonEmpty([
  $json.from_phone,
  EXT.from_phone,
  digits($json.waha_chat_id),
  digits($json.waha_chat_id_in),
  digits(ANTI.waha_chat_id),
  digits(whPayload.from),
  digits(whPayload.chatId),
  digits(whPayload.sender && whPayload.sender.id)
]);

const thread = firstNonEmpty([
  $json.thread_id,
  FM.thread_id,
  $json.thread,
  ''
]);

const msg = firstNonEmpty([
  $json.user_message,
  EXT.message_text,
  $json.last_user_message,
  FM.last_user_message,
  ''
]);

const reply = firstNonEmpty([
  $json.output_text,
  $json.waha_text,
  $json.reply_text,
  ''
]);

const eta = Number($json.eta_minutes || FM.eta_from_user_minutes || 0);
const conf = Number($json.lead_confidence || 0);
const girl = firstNonEmpty([
  $json.selected_girl_name,
  $json.speaker_girl_name,
  FM.selected_girl_name,
  FM.speaker_girl_name,
  'SIN_RESOLVER'
]);
const identity_resolved = !!($json.identity_resolved || FM.identity_resolved);

function sanitize(s){
  try { return String(s).normalize('NFKD').replace(/[^\x09\x0A\x0D\x20-\x7E]/g,''); }
  catch(e){ return String(s).replace(/[^\x09\x0A\x0D\x20-\x7E]/g,''); }
}

let text = 'LEAD DETECTADO\n' + girl + '\n'
 + 'Identity resolved: ' + (identity_resolved ? 'SI' : 'NO') + '\n'
 + 'Tel: ' + (phone || '') + '\n'
 + 'ETA: ' + (eta || 0) + ' min\n'
 + 'Conf: ' + (Number.isFinite(conf) ? conf : 0) + '\n'
 + 'Thread: ' + (thread || '') + '\n'
 + 'Msg: ' + (msg || '') + '\n'
 + 'Reply: ' + (reply || '');

text = sanitize(text);

const CHAT_ID = '6755848011';

return [{
  ...$json,
  from_phone: String(phone||''),
  thread_id: String(thread||''),
  user_message: String(msg||''),
  output_text: String(reply||''),
  eta_minutes: eta,
  lead_confidence: conf,
  telegram_text: text,
  telegram_chat_id: CHAT_ID
}];
JS;

$openAiJsonBody = <<<'JS'
={{ ({
  model: 'gpt-5.1',
  response_format: { type: 'json_object' },
  messages: [
    {
      role: 'system',
      content: (function(){
        const prompt = ($json.prompt_text || '');
        const pbRaw = String($json.playbook_text || '');
        const lines = pbRaw.split('\n');
        let out = [];
        for (let i=0;i<lines.length && i<40;i++) {
          const L = lines[i];
          if (String(L).trim().startsWith('## Intents')) break;
          out.push(L);
        }
        const playbookSoft = out.join('\n').trim();

        return (
          prompt +
          '\n\n### PLAYBOOK (suave)\n' + playbookSoft +
          '\n\n### CONTEXTO ACTUAL\n' +
          '- identity_resolved (boolean): ' + JSON.stringify($json.identity_resolved || false) + '\n' +
          '- identity_resolution_reason: ' + JSON.stringify($json.identity_resolution_reason || '') + '\n' +
          '- identity_candidates_count: ' + JSON.stringify($json.identity_candidates_count || 0) + '\n' +
          '- selected_girl_id: ' + JSON.stringify($json.selected_girl_id || '') + '\n' +
          '- selected_girl_name: ' + JSON.stringify($json.selected_girl_name || '') + '\n' +
          '- speaker_girl_id: ' + JSON.stringify($json.speaker_girl_id || '') + '\n' +
          '- speaker_girl_name: ' + JSON.stringify($json.speaker_girl_name || '') + '\n' +
          '- speaker_mode: ' + JSON.stringify($json.speaker_mode || 'chica') + '\n' +
          '- topic_actual: ' + JSON.stringify($json.topic_actual || '') + '\n' +
          '- sales_stage: ' + JSON.stringify($json.sales_stage || '') + '\n' +
          '- ya_enviado: ' + JSON.stringify($json.ya_enviado || []) + '\n' +
          '- pendiente: ' + JSON.stringify($json.pendiente || null) + '\n' +
          '- session_reset (boolean): ' + JSON.stringify($json.session_reset || false) + '\n' +
          '- last_bot_reply: ' + JSON.stringify($json.last_bot_reply || '') + '\n' +
          '- last_user_message: ' + JSON.stringify($json.last_user_message || '') + '\n' +
          '- last_user_meaningful: ' + JSON.stringify($json.last_user_meaningful || '') + '\n' +
          '- tarifa_elegida: ' + JSON.stringify($json.tarifa_elegida || '') + '\n' +
          '- maps_sent (boolean): ' + JSON.stringify($json.maps_sent || false) + '\n' +
          '- photos_sent_recent (boolean): ' + JSON.stringify($json.photos_sent_recent || false) + '\n' +
          '- conversation_end_intent (boolean): ' + JSON.stringify($json.conversation_end_intent || false) + '\n' +
          '- emoji_count_recent: ' + JSON.stringify($json.emoji_count_recent || 0) + '\n' +
          '- haggle_count_recent: ' + JSON.stringify($json.haggle_count_recent || 0) + '\n' +
          '- recent_saludo (boolean): ' + JSON.stringify($json.recent_saludo || false) + '\n' +
          '- girl_zona: ' + JSON.stringify($json.girl_zona || '') + '\n' +
          '- girl_servicios: ' + JSON.stringify($json.girl_servicios || '') + '\n' +
          '- girl_tarifas: ' + JSON.stringify($json.girl_tarifas || '') + '\n' +
          '- location_url: ' + JSON.stringify($json.location_url || '') + '\n' +
          '- wants_more_girls (boolean): ' + JSON.stringify($json.wants_more_girls || false) + '\n' +
          '- recent_bot_replies_norm: ' + JSON.stringify($json.recent_bot_replies_norm || []) + '\n' +
          '- tone_directives: ' + JSON.stringify($json.tone_directives || '') + '\n' +
          '\n### REGLAS CLAVE LAMAMIBOT\n' +
          '- Hablas SIEMPRE en primera persona como chica real.\n' +
          '- Nunca digas ni sugieras que eres encargada, telefonista, centralita o la casa.\n' +
          '- Nunca delates que tras el mismo telefono puede haber varias chicas o varios anuncios.\n' +
          '- NO uses frases como "por cual de mis anuncios", "tengo varias", "mis chicas", "mis compañeras" o similares.\n' +
          '- Si identity_resolved=false, debes seguir sonando como si fueras la misma chica del anuncio que el cliente vio.\n' +
          '- Si identity_resolved=false y hace falta aclarar, pide UNA sola pista sutil de zona, ciudad o aspecto fisico. Nunca abras catalogo. Nunca enumeres nombres. Nunca inventes opciones.\n' +
          '- Si el usuario ya ha dado una pista clara tipo Ventas, Madrid, centro, pelirroja, morena, latina, etc. y eso encaja de forma unica con una chica, trata la identidad como cerrada y responde ya con normalidad.\n' +
          '- Si identity_resolved=true: ya eres exactamente selected_girl_name. No vuelvas a abrir ambiguedad.\n' +
          '- Si identity_resolved=true y te piden fotos, envialas directas usando girls_config. NO pongas condiciones de hora, ETA o venir primero.\n' +
          '- Si el usuario hace varias preguntas en el mismo mensaje, responde a TODAS de forma breve.\n' +
          '- No uses frases tipo "no se a que te refieres".\n' +
          '- Si ya se enviaron precios, fotos o ubicacion, no lo presentes como si fuera la primera vez.\n' +
          '- No repitas frases literales si se parecen a last_bot_reply o recent_bot_replies_norm.\n' +
          '\nListado girls_config (solo contexto, no lo repitas tal cual):\n' +
          JSON.stringify($json.girls_config || [], null, 2)
        );
      })()
    },
    {
      role: 'user',
      content: ($json.user_message || 'Cliente: hola')
    }
  ],
  temperature: 0.6,
  max_completion_tokens: 3200
}) }}
JS;

    $routingCode = "return [{\n"
        . "  ...\$json,\n"
        . "  waha_numbers_config: " . json_encode(array_values($wahaConfig), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ",\n"
        . "  sender_blacklist: [\n"
        . "    '34666555555',\n"
        . "    '12345678998745',\n"
        . "    '162011098935441',\n"
        . "    '128690658783343',\n"
        . "    '232719011295254',\n"
        . "    '34600111222',\n"
        . "    '666555444',\n"
        . "    '627146331',\n"
        . "    '624091112',\n"
        . "    '176725203927244'\n"
        . "  ],\n"
        . "  default_enabled_if_not_found: false,\n"
        . "  default_port_if_not_found: '3000'\n"
        . "}];";

    $readExpr = '={{ String($json.memory_file_resolved || ' . json_encode($genericFile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ') }}';
    $readMixExpr = '={{ ' . json_encode($genericFile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ' }}';
    $writeTmpExpr = '={{ String($json.memory_file_tmp_resolved || ' . json_encode($genericTmp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ') }}';
    $acquireCmdExpr = '={{ (function(){ const p = String($json.memory_lock_resolved || ' . json_encode($genericLock, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '); return "LOCK=" + p + "\\nTRIES=50\\nSLEEP=0.1\\ni=0\\nwhile [ \\"$i\\" -lt \\"$TRIES\\" ]; do\\n  if mkdir \\"$LOCK\\" 2>/dev/null; then\\n    echo LOCKED\\n    exit 0\\n  fi\\n  i=$((i+1))\\n  sleep \\"$SLEEP\\"\\ndone\\necho BUSY\\nexit 0\\n"; })() }}';
    $releaseCmdExpr = '={{ (function(){ const p = String($json.memory_lock_resolved || ' . json_encode($genericLock, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '); return "sh -lc \\"rmdir " + p + " 2>/dev/null || true\\""; })() }}';
    $atomicMoveExpr = '={{ (function(){ const tmp = String($json.memory_file_tmp_resolved || ' . json_encode($genericTmp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '); const fin = String($json.memory_file_resolved || ' . json_encode($genericFile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '); return "sh -lc \\"mv " + tmp + " " + fin + "\\""; })() }}';

    foreach ($workflow['nodes'] as &$node) {
        $name = (string)($node['name'] ?? '');

        if ($name === 'Fetch Girls JSON') {
            $node['parameters']['url'] = $girlsJsonUrl;
            continue;
        }

        if ($name === 'Girls Config (from remote JSON)') {
            $node['parameters']['functionCode'] = $girlsConfigCode;
            continue;
        }

        if ($name === 'Set Location') {
            $node['parameters']['functionCode'] = $setLocationCode;
            continue;
        }

        if ($name === 'Assemble Context (No-Merge)') {
            $node['parameters']['functionCode'] = $assembleContextCode;
            continue;
        }

        if ($name === 'Build Tone') {
            $node['parameters']['functionCode'] = $buildToneCode;
            continue;
        }

        if ($name === 'Format Memory') {
            $node['parameters']['functionCode'] = $formatMemoryCodeMix;
            continue;
        }

        if ($name === 'Format Effective Memory') {
            $node['parameters']['functionCode'] = $formatMemoryCodeEffective;
            continue;
        }

        if ($name === 'Append Memory') {
            $node['parameters']['functionCode'] = $appendMemoryCode;
            continue;
        }

        if ($name === 'Audio Auto Reply') {
            $node['parameters']['functionCode'] = $audioAutoReplyCode;
            continue;
        }

        if ($name === 'Gate Lead → Telegram') {
            $node['parameters']['functionCode'] = $gateLeadCode;
            continue;
        }

        if ($name === 'OpenAI Chat') {
            $node['parameters']['jsonBody'] = $openAiJsonBody;
            continue;
        }

        if ($name === 'Routing + ACL Config') {
            $node['parameters']['functionCode'] = $routingCode;
            continue;
        }

        if ($name === 'Read Memory') {
            // Primera lectura: SIEMPRE mixta
            $node['parameters']['filePath'] = $readMixExpr;
            continue;
        }

        if ($name === 'Read Memory For Append') {
            // El append trabajará con el target que le haya marcado el expansor
            $node['parameters']['filePath'] = $readExpr;
            continue;
        }

        if ($name === 'Read Effective Memory') {
            // Segunda lectura: mixta o individual según identity_resolved
            $node['parameters']['filePath'] = $readExpr;
            continue;
        }

        if ($name === 'LamamiBot Expand Memory Targets') {
            $node['parameters']['functionCode'] = $expandMemoryTargetsCode;
            continue;
        }

        if ($name === 'Write Memory (TMP)') {
            $node['parameters']['fileName'] = $writeTmpExpr;
            continue;
        }

        if ($name === 'Acquire Soft Lock') {
            $node['parameters']['command'] = $acquireCmdExpr;
            continue;
        }

        if ($name === 'Release Soft Lock') {
            $node['parameters']['command'] = $releaseCmdExpr;
            continue;
        }

        if ($name === 'Atomic Move TMP→FINAL') {
            $node['parameters']['command'] = $atomicMoveExpr;
            continue;
        }

        if ($name === 'Set Prompt') {
            if (isset($node['parameters']['assignments']['assignments']) && is_array($node['parameters']['assignments']['assignments'])) {
                foreach ($node['parameters']['assignments']['assignments'] as &$assignment) {
                    if (($assignment['name'] ?? '') === 'prompt_text') {
                        $assignment['value'] = $setPromptText;
                    }
                }
                unset($assignment);
            }
            continue;
        }

        if ($name === 'Normalize Output') {
            if (isset($node['parameters']['assignments']['assignments']) && is_array($node['parameters']['assignments']['assignments'])) {
            $wanted = array(
                'selected_girl_id' => array(
                    'type' => 'string',
                    'value' => '={{ $node["Format Effective Memory"].json.selected_girl_id || $json.selected_girl_id || "" }}'
                ),
                'selected_girl_name' => array(
                    'type' => 'string',
                    'value' => '={{ $node["Format Effective Memory"].json.selected_girl_name || $json.selected_girl_name || "" }}'
                ),
                'speaker_girl_id' => array(
                    'type' => 'string',
                    'value' => '={{ $node["Format Effective Memory"].json.selected_girl_id || $node["Format Effective Memory"].json.speaker_girl_id || $json.speaker_girl_id || "" }}'
                ),
                'speaker_girl_name' => array(
                    'type' => 'string',
                    'value' => '={{ $node["Format Effective Memory"].json.selected_girl_name || $node["Format Effective Memory"].json.speaker_girl_name || $json.speaker_girl_name || "" }}'
                ),
                'speaker_mode' => array(
                    'type' => 'string',
                    'value' => '={{ $node["Format Effective Memory"].json.speaker_mode || "chica" }}'
                ),
                'identity_resolved' => array(
                    'type' => 'boolean',
                    'value' => '={{ !!($node["Format Effective Memory"].json.identity_resolved || $json.identity_resolved) }}'
                ),
                'memory_file_resolved' => array(
                    'type' => 'string',
                    'value' => '={{ $node["Format Effective Memory"].json.memory_file_resolved || "" }}'
                ),
                'memory_file_tmp_resolved' => array(
                    'type' => 'string',
                    'value' => '={{ $node["Format Effective Memory"].json.memory_file_tmp_resolved || "" }}'
                ),
                'memory_lock_resolved' => array(
                    'type' => 'string',
                    'value' => '={{ $node["Format Effective Memory"].json.memory_lock_resolved || "" }}'
                ),
            );


                $seen = array();
                foreach ($node['parameters']['assignments']['assignments'] as &$assignment) {
                    $aName = (string)($assignment['name'] ?? '');
                    if (isset($wanted[$aName])) {
                        $assignment['type'] = $wanted[$aName]['type'];
                        $assignment['value'] = $wanted[$aName]['value'];
                        $seen[$aName] = true;
                    }
                }
                unset($assignment);

                foreach ($wanted as $aName => $spec) {
                    if (!isset($seen[$aName])) {
                        $node['parameters']['assignments']['assignments'][] = array(
                            'name' => $aName,
                            'type' => $spec['type'],
                            'value' => $spec['value'],
                        );
                    }
                }
            }
            continue;
        }
    }
    unset($node);

    $hasIdentityNode = false;
    foreach ($workflow['nodes'] as $node) {
        if (($node['name'] ?? '') === 'LamamiBot Identity Resolve') {
            $hasIdentityNode = true;
            break;
        }
    }

    if (!$hasIdentityNode) {
        $workflow['nodes'][] = array(
            'parameters' => array(
                'functionCode' => $identityResolveCode,
            ),
            'id' => '7fe35f7b-lamamibot-identity',
            'name' => 'LamamiBot Identity Resolve',
            'type' => 'n8n-nodes-base.function',
            'typeVersion' => 1,
            'position' => array(-480, 112),
        );
    } else {
        foreach ($workflow['nodes'] as &$node) {
            if (($node['name'] ?? '') === 'LamamiBot Identity Resolve') {
                $node['parameters']['functionCode'] = $identityResolveCode;
            }
        }
        unset($node);
    }

$readMemoryNode = null;
$bin2TextMemoryNode = null;
$formatMemoryNode = null;

$hasExpandTargetsNode = false;
$hasReadEffectiveNode = false;
$hasBinEffectiveNode = false;
$hasFormatEffectiveNode = false;

foreach ($workflow['nodes'] as $node) {
    $name = (string)($node['name'] ?? '');

    if ($name === 'Read Memory') $readMemoryNode = $node;
    if ($name === 'Bin2Text Memory') $bin2TextMemoryNode = $node;
    if ($name === 'Format Memory') $formatMemoryNode = $node;

    if ($name === 'LamamiBot Expand Memory Targets') $hasExpandTargetsNode = true;
    if ($name === 'Read Effective Memory') $hasReadEffectiveNode = true;
    if ($name === 'Bin2Text Effective Memory') $hasBinEffectiveNode = true;
    if ($name === 'Format Effective Memory') $hasFormatEffectiveNode = true;
}

/*
 * Nodo 1: lectura de memoria efectiva (mix o girl)
 */
if (!$hasReadEffectiveNode && is_array($readMemoryNode)) {
    $node = $readMemoryNode;
    $node['id'] = '7fe35f7b-lamamibot-read-effective';
    $node['name'] = 'Read Effective Memory';
    $node['position'] = array(-352, 112);
    $node['parameters']['filePath'] = $readExpr;
    $workflow['nodes'][] = $node;
}

/*
 * Nodo 2: binario -> texto de la memoria efectiva
 */
if (!$hasBinEffectiveNode && is_array($bin2TextMemoryNode)) {
    $node = $bin2TextMemoryNode;
    $node['id'] = '7fe35f7b-lamamibot-bin-effective';
    $node['name'] = 'Bin2Text Effective Memory';
    $node['position'] = array(-224, 112);
    $workflow['nodes'][] = $node;
}

/*
 * Nodo 3: parseo de la memoria efectiva
 */
if (!$hasFormatEffectiveNode && is_array($formatMemoryNode)) {
    $node = $formatMemoryNode;
    $node['id'] = '7fe35f7b-lamamibot-format-effective';
    $node['name'] = 'Format Effective Memory';
    $node['position'] = array(-96, 112);
    $node['parameters']['functionCode'] = $formatMemoryCodeEffective;
    $workflow['nodes'][] = $node;
} else {
    foreach ($workflow['nodes'] as &$node) {
        if (($node['name'] ?? '') === 'Format Effective Memory') {
            $node['parameters']['functionCode'] = $formatMemoryCodeEffective;
        }
    }
    unset($node);
}

/*
 * Nodo 4: expansor de targets para escribir en mix y, si toca, también en la girl
 */
if (!$hasExpandTargetsNode) {
    $workflow['nodes'][] = array(
        'parameters' => array(
            'functionCode' => $expandMemoryTargetsCode,
        ),
        'id' => '7fe35f7b-lamamibot-expand-memory-targets',
        'name' => 'LamamiBot Expand Memory Targets',
        'type' => 'n8n-nodes-base.function',
        'typeVersion' => 1,
        'position' => array(-1056, 336),
    );
} else {
    foreach ($workflow['nodes'] as &$node) {
        if (($node['name'] ?? '') === 'LamamiBot Expand Memory Targets') {
            $node['parameters']['functionCode'] = $expandMemoryTargetsCode;
        }
    }
    unset($node);
}

$workflow['connections']['Format Memory'] = array(
    'main' => array(
        array(
            array(
                'node' => 'LamamiBot Identity Resolve',
                'type' => 'main',
                'index' => 0,
            ),
        ),
    ),
);

$workflow['connections']['LamamiBot Identity Resolve'] = array(
    'main' => array(
        array(
            array(
                'node' => 'Read Effective Memory',
                'type' => 'main',
                'index' => 0,
            ),
        ),
    ),
);

$workflow['connections']['Read Effective Memory'] = array(
    'main' => array(
        array(
            array(
                'node' => 'Bin2Text Effective Memory',
                'type' => 'main',
                'index' => 0,
            ),
        ),
    ),
);

$workflow['connections']['Bin2Text Effective Memory'] = array(
    'main' => array(
        array(
            array(
                'node' => 'Format Effective Memory',
                'type' => 'main',
                'index' => 0,
            ),
        ),
    ),
);

$workflow['connections']['Format Effective Memory'] = array(
    'main' => array(
        array(
            array(
                'node' => 'IF Is Audio?',
                'type' => 'main',
                'index' => 0,
            ),
        ),
    ),
);

/*
 * MUY IMPORTANTE:
 * en vez de mandar DeDupe Reply -> Acquire Soft Lock directamente,
 * primero expandimos targets para escribir en mix y, si procede, en la girl.
 */
$workflow['connections']['DeDupe Reply (guard)'] = array(
    'main' => array(
        array(
            array(
                'node' => 'Split Outgoing (images as solo-link msgs)',
                'type' => 'main',
                'index' => 0,
            ),
            array(
                'node' => 'LamamiBot Expand Memory Targets',
                'type' => 'main',
                'index' => 0,
            ),
            array(
                'node' => 'Gate Lead → Telegram',
                'type' => 'main',
                'index' => 0,
            ),
        ),
    ),
);

$workflow['connections']['LamamiBot Expand Memory Targets'] = array(
    'main' => array(
        array(
            array(
                'node' => 'Acquire Soft Lock',
                'type' => 'main',
                'index' => 0,
            ),
        ),
    ),
);

    $encoded = json_encode(
        $workflow,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    return is_string($encoded) ? $encoded : $texto1;
}


function lamamibot_prepare_mode_files($botSlug, $texto2) {
    $runtimeBot = array(
        'nombre_bot' => $botSlug,
        'generated_assets' => array(
            'texto2' => $texto2,
        ),
    );

    return bot_mode_prepare_files($runtimeBot, 'start');
}

function lamamibot_generate_texto3($botSlug, $wahaConfig) {
    $hookUrl = 'https://n8n.makemerich.live/webhook/waha-in-' . $botSlug;
    $blocks = array();

    foreach ((array)$wahaConfig as $row) {
        $port = trim((string)($row['port'] ?? ''));
        $last9 = trim((string)($row['last9'] ?? ''));
        $label = trim((string)($row['label'] ?? ''));

        if ($port === '') continue;

        $serviceName = 'waha_' . $botSlug . '_' . $port;
        $folder = '/srv/' . $serviceName;

        $block = '';
        $block .= '# =====================================' . "\n";
        $block .= '# Línea: ' . ($label !== '' ? $label : $last9) . "\n";
        $block .= '# Teléfono: ' . $last9 . "\n";
        $block .= '# Puerto WAHA: ' . $port . "\n";
        $block .= '# Carpeta sugerida: ' . $folder . "\n";
        $block .= '# =====================================' . "\n";
        $block .= "services:\n";
        $block .= "  {$serviceName}:\n";
        $block .= "    image: devlikeapro/waha:latest\n";
        $block .= "    container_name: {$serviceName}\n";
        $block .= "    restart: unless-stopped\n";
        $block .= "    ports:\n";
        $block .= "      - \"{$port}:3000\"\n";
        $block .= "    cpus: \"2\"\n";
        $block .= "    mem_limit: \"1g\"\n";
        $block .= "    environment:\n";
        $block .= "      - WAHA_API_KEY=local321\n";
        $block .= "      - WHATSAPP_DEFAULT_ENGINE=GOWS\n";
        $block .= "      - TZ=Europe/Madrid\n";
        $block .= "      - WAHA_DASHBOARD_USERNAME=admin\n";
        $block .= "      - WAHA_DASHBOARD_PASSWORD=admin123\n";
        $block .= "      - WHATSAPP_SWAGGER_USERNAME=admin\n";
        $block .= "      - WHATSAPP_SWAGGER_PASSWORD=admin123\n";
        $block .= "      - WHATSAPP_HOOK_URL={$hookUrl}\n";
        $block .= "      - WHATSAPP_HOOK_EVENTS=message\n";
        $block .= "    volumes:\n";
        $block .= "      - ./data:/app/data\n";
        $block .= "      - ./sessions:/app/.sessions\n";
        $block .= "      - ./media:/app/.media\n";

        $blocks[] = $block;
    }

    return implode("\n\n", $blocks);
}


function lamamibot_generate_bot_bundle($cfg) {
    $botSlug = lamamibot_bot_slug($cfg);
    $mixMemory = lamamibot_mix_memory_paths($botSlug);

    list($memoryOk, $memoryPath) = lamamibot_prepare_mix_session_memory_file($botSlug);
    if (!$memoryOk) {
        return array(false, $memoryPath);
    }

    list($wahaConfig, $warnings) = lamamibot_selected_waha_config($cfg['telefonos_ids'] ?? array());
    if (empty($wahaConfig)) {
        return array(false, 'No hay líneas válidas para generar LamamiBot. Revisa que las líneas seleccionadas tengan teléfono y WAHA port.');
    }

    $virtualBot = array(
        'nombre_bot' => $botSlug,
        'telefono_bot' => (string)$wahaConfig[0]['last9'],
        'waha_port' => (string)$wahaConfig[0]['port'],
        'server_ip' => '100.113.76.93',
        'bot_mode' => 'multiple',
    );

    $virtualClienta = array(
        'nombre' => 'LamamiBot',
        'tarifas' => 'Las tarifas concretas dependen del anuncio por el que me hables; en cuanto sepa por cual preguntas te digo bien.',
        'ubicacion_maps' => '',
        'zona' => 'Estoy en un sitio discreto, con cama grande y buen ambiente.',
        'servicios' => 'Los servicios concretos te los paso bien en cuanto sepa por cual de mis anuncios me hablas.',
    );

    $vars = lamami_bot_vars($virtualBot, $virtualClienta);
    $vars['[LAMAMI_SESSION_MEMORY_FILE]'] = $mixMemory['memory_file'];
    $vars['[LAMAMI_SESSION_MEMORY_FILE_TMP]'] = $mixMemory['memory_file_tmp'];
    $vars['[LAMAMI_SESSION_MEMORY_LOCK]'] = $mixMemory['memory_lock'];

    $texto1 = lamami_apply_vars(lamami_template_texto1(), $vars);
    $texto1 = lamamibot_patch_texto1($texto1, $botSlug, $wahaConfig, $mixMemory);

    $texto2 = lamami_apply_vars(lamami_template_texto2(), $vars);
    $texto3 = lamamibot_generate_texto3($botSlug, $wahaConfig);
    $texto4 = lamamibot_girlsconf_base_url();
    $texto5Start = lamami_apply_vars(lamami_template_texto5_start(), $vars);
    $texto5Stop = lamami_apply_vars(lamami_template_texto5_stop(), $vars);

    list($modeFilesOk, $modePathsReady, $modeErrors, $modeCandidates) = lamamibot_prepare_mode_files($botSlug, $texto2);

    if (!$modeFilesOk) {
        return array(false, 'No se pudieron preparar los ficheros .bot_mode de LamamiBot. ' . implode(' | ', $modeErrors));
    }

    if (!empty($modeErrors)) {
        $warnings = array_merge($warnings, $modeErrors);
    }

    $runtimeBot = array(
        'nombre_bot' => $botSlug,
        'generated_assets' => array(
            'texto2' => $texto2,
        ),
    );

    $bundle = array(
        'generated_at' => now_datetime(),
        'bot_slug' => $botSlug,
        'texto1' => $texto1,
        'texto2' => $texto2,
        'texto3' => $texto3,
        'texto4' => $texto4,
        'texto5_start' => $texto5Start,
        'texto5_stop' => $texto5Stop,
        'girls_panel_url' => $texto4,
        'girls_json_url' => lamamibot_girlsconf_base_url() . '/data/girls.json',
        'session_memory_mix_path' => $memoryPath,
        'waha_numbers_config' => $wahaConfig,
        'bot_mode_paths' => $modePathsReady,
        'bot_mode_candidates' => $modeCandidates,
        'runtime_mode' => bot_runtime_mode($runtimeBot),
        'warnings' => $warnings,
        'summary' => lamamibot_build_generation_summary($botSlug, $wahaConfig, $warnings),
    );

    return array(true, $bundle);
}