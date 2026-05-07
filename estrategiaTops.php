<?php
declare(strict_types=1);
/**
 * Herramienta de estrategia publicitaria para destacamos.net
 * PHP 7.4 — archivo único (form + scraper + motor de estrategia + salida visual)
 */

// ═══════════════════════════════════════════════════════════
//  CONFIGURACIÓN DE PRECIOS (€)
// ═══════════════════════════════════════════════════════════
define('PRICE_TOP',      9.0);   // TOP 10 días
define('PRICE_AUTO_7',   7.0);   // Autorenueva 10 subidas/día (rango amplio)
define('PRICE_AUTO_4',   4.0);   // Autorenueva  4 subidas/día (refuerzo)
define('PRICE_PREMIUM', 15.0);   // PREMIUM 30 días (estimado — verificar en web)

define('CATEGORIES', [
    ''                   => 'Todas las categorías',
    '1-chicas-escorts'   => 'Escorts (Acompañantes)',
    '2-masajes-eroticos' => 'Masajes relajantes',
    '3-travestis'        => 'Transexuales y travestis',
    '9-escorts-lujo'     => 'Escorts de lujo',
]);

// ═══════════════════════════════════════════════════════════
//  SCRAPER
// ═══════════════════════════════════════════════════════════

function buildListingUrl(string $city, string $cat): string
{
    $enc = rawurlencode($city);
    return $cat
        ? "https://www.destacamos.net/{$cat}/localidad-{$enc}/listings.html"
        : "https://www.destacamos.net/localidad-{$enc}/listings.html";
}

function fetchPage(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_TIMEOUT        => 18,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                                    . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                                    . 'Chrome/121.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
            ],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($status === 200 && is_string($body) && strlen($body) > 800) ? $body : null;
    }

    // Fallback file_get_contents
    $ctx  = stream_context_create(['http' => [
        'user_agent' => 'Mozilla/5.0 (compatible)',
        'timeout'    => 15,
        'ignore_errors' => false,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return ($body && strlen($body) > 800) ? $body : null;
}

function parseStats(string $html): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html,
        LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
    libxml_clear_errors();

    $xp      = new DOMXPath($dom);
    $premium = $top = $auto = 0;

    // Primary: count <strong> elements with exact badge text
    $nodes = $xp->query('//strong');
    if ($nodes) {
        foreach ($nodes as $n) {
            switch (trim($n->textContent)) {
                case 'PREMIUM':     $premium++; break;
                case 'TOP':         $top++;     break;
                case 'Autosubidas': $auto++;    break;
            }
        }
    }

    // Fallback: raw string search
    if ($premium + $top + $auto === 0) {
        $premium = substr_count($html, '>PREMIUM<');
        $top     = substr_count($html, '>TOP<');
        $auto    = substr_count($html, '>Autosubidas<')
                 + substr_count($html, '>Autosubida<');
    }

    // Total profiles: try header first, then count detail links
    $total = 0;
    if (preg_match('/(\d[\d.]*)\s+perfiles?/iu', $html, $m)) {
        $total = (int) str_replace('.', '', $m[1]);
    }
    if ($total === 0) {
        $total = (int) preg_match_all('/\/details\.html/', $html);
    }

    // Subtract UI/navigation duplicates (conservative)
    $premium = max(0, $premium - 1);
    $top     = max(0, $top);
    $auto    = max(0, $auto);

    return compact('premium', 'top', 'auto', 'total');
}

function classifyLevel(int $p, int $t, int $a, int $total): string
{
    $paid = $p + $t + $a;
    if ($t <= 4  && $paid <= 8)   return 'muy_baja';
    if ($t <= 12 && $paid <= 22)  return 'baja';
    if ($t <= 28 && $paid <= 55)  return 'media';
    if ($t <= 65 && $paid <= 110) return 'alta';
    return 'muy_alta';
}

function scrape(string $city, string $cat): array
{
    $url  = buildListingUrl($city, $cat);
    $html = fetchPage($url);

    if ($html === null) {
        // Fallback — estimates for a small city like Burriana
        return [
            'premium' => 0, 'top' => 2, 'auto' => 1, 'total' => 12,
            'level'   => 'muy_baja',
            'url'     => $url,
            'scraped' => false,
            'notice'  => 'No se pudo conectar con destacamos.net. '
                       . 'Se usan estimaciones para ciudad pequeña.',
        ];
    }

    $s = parseStats($html);
    return array_merge($s, [
        'level'   => classifyLevel($s['premium'], $s['top'], $s['auto'], $s['total']),
        'url'     => $url,
        'scraped' => true,
        'notice'  => null,
    ]);
}

// ═══════════════════════════════════════════════════════════
//  HELPERS DE TIEMPO
// ═══════════════════════════════════════════════════════════

function toMins(string $t): int
{
    [$h, $m] = explode(':', $t);
    return (int)$h * 60 + (int)$m;
}

function toHHMM(int $mins): string
{
    return sprintf('%02d:%02d', ($mins / 60) % 24, $mins % 60);
}

/**
 * Calcula los momentos exactos de subida dado un rango horario y número de subidas.
 * Maneja rangos que cruzan la medianoche.
 */
function calcFirings(string $start, string $end, int $n): array
{
    $s = toMins($start);
    $e = toMins($end);
    if ($e <= $s) {
        $e += 1440; // rango nocturno (ej. 11:00 → 03:00)
    }
    $interval = ($e - $s) / $n;
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $out[] = toHHMM((int)(($s + $i * $interval) % 1440));
    }
    return $out;
}

/**
 * Detecta solapamientos entre dos listas de tiempos (dentro de $thresh minutos).
 */
function detectOverlap(array $t1, array $t2, int $thresh = 6): array
{
    $conflicts = [];
    foreach ($t1 as $a) {
        foreach ($t2 as $b) {
            $diff = abs(toMins($a) - toMins($b));
            if ($diff > 720) $diff = 1440 - $diff;
            if ($diff < $thresh) {
                $conflicts[] = "{$a} ↔ {$b} ({$diff} min)";
            }
        }
    }
    return $conflicts;
}

// ═══════════════════════════════════════════════════════════
//  MOTOR DE ESTRATEGIA
// ═══════════════════════════════════════════════════════════

/**
 * Construye un perfil anuncio con sus metadatos.
 */
function makeProfile(
    int    $num,
    string $name,
    array  $opts,   // ['TOP'=>true, 'PREMIUM'=>true, 'auto7'=>[...], 'auto4'=>[...], 'free'=>[...]]
    float  $cost,
    string $why
): array {
    return [
        'num'     => $num,
        'name'    => $name,
        'opts'    => $opts,
        'cost'    => $cost,
        'why'     => $why,
        'firings' => [],   // se rellena después
    ];
}

function buildStrategy(array $comp, int $numGirls): array
{
    $result = [];
    for ($g = 1; $g <= $numGirls; $g++) {
        $result[] = strategyForGirl($comp, $g);
    }
    return $result;
}

function strategyForGirl(array $comp, int $g): array
{
    $level   = $comp['level'];
    $tops    = $comp['top'];
    $prems   = $comp['premium'];
    $autos   = $comp['auto'];
    $total   = $comp['total'];
    $profiles = [];
    $reasons  = [];

    switch ($level) {

        case 'muy_baja':
            $reasons = [
                "Solo {$tops} TOP(s) activos en esta ciudad → siempre visibles (límite plataforma: 15 simultáneos).",
                "PREMIUM no justificado: con tan poca competencia el gasto no se recupera.",
                "{$total} perfiles totales → un único TOP ya domina la primera posición.",
                "Estrategia: 1 TOP + 1 autorenueva amplia + 1 refuerzo prime time + gratuitos.",
            ];
            $profiles = [
                makeProfile(1, 'TOP + Autorenueva 7€  (cobertura principal)',
                    ['TOP' => true, 'auto7' => ['start' => '11:00', 'end' => '23:00', 'n' => 10]],
                    PRICE_TOP + PRICE_AUTO_7,
                    'TOP siempre visible en zona B · autorenueva 11:00–23:00 (≈cada 72 min) · cubre mañana, tarde y noche'
                ),
                makeProfile(2, 'Autorenueva Refuerzo 4€  (prime time noche)',
                    ['auto4' => ['start' => '19:30', 'end' => '23:30', 'n' => 4]],
                    PRICE_AUTO_4,
                    'Refuerzo 19:30–23:30 (cada 60 min) · offset +30 min respecto al P1 para evitar solapamiento · máximo tráfico'
                ),
                makeProfile(3, 'Gratuito ×2  (sin coste)',
                    ['free' => ['14:00', '22:00']],
                    0.0,
                    'Subida manual a las 14:00 y 22:00 · cubre mediodía y late night · solo si dispones de tiempo'
                ),
            ];
            break;

        case 'baja':
            $reasons = [
                "{$tops} TOPs activos → aún por debajo del límite de 15, todos visibles sin rotación.",
                "2 autorenuevas en franjas complementarias cubren el día entero (mañana→madrugada).",
                "{$prems} PREMIUM(s) activos → zona A no saturada, pero tampoco justifica el coste extra.",
                "Estrategia: 1 TOP + 2 autorenuevas en horarios distintos + 1 refuerzo 4€.",
            ];
            $profiles = [
                makeProfile(1, 'TOP + Autorenueva 7€  (mañana-tarde)',
                    ['TOP' => true, 'auto7' => ['start' => '10:30', 'end' => '22:30', 'n' => 10]],
                    PRICE_TOP + PRICE_AUTO_7,
                    'TOP visible en zona B · autorenueva 10:30–22:30 (≈cada 72 min) · cubre toda la jornada diurna'
                ),
                makeProfile(2, 'Autorenueva 7€  (tarde-madrugada)',
                    ['auto7' => ['start' => '12:00', 'end' => '03:00', 'n' => 10]],
                    PRICE_AUTO_7,
                    'Rango 12:00–03:00 (≈cada 90 min) · empieza 1:30 h después del P1 → sin solapamiento; cubre noche y madrugada'
                ),
                makeProfile(3, 'Autorenueva Refuerzo 4€  (pico nocturno)',
                    ['auto4' => ['start' => '19:45', 'end' => '23:45', 'n' => 4]],
                    PRICE_AUTO_4,
                    'Refuerzo 19:45–23:45 (cada 60 min) · offset +15 min respecto a P1 y P2 · densifica el prime time'
                ),
                makeProfile(4, 'Gratuito',
                    ['free' => ['14:00', '22:00']],
                    0.0,
                    'Coste cero · mediodía y noche · útil para rellenar huecos del listado normal'
                ),
            ];
            break;

        case 'media':
            $reasons = [
                "{$tops} TOPs activos → rotación activa (solo 15 visibles por carga de página).",
                "Se necesitan 2 TOPs para doblar la probabilidad de aparecer en esos 15 slots.",
                "{$prems} PREMIUM(s) activos → zona A ocupada; sin PREMIUM la inversión estaría mal repartida.",
                "2 autorenuevas con offsets de 30 min crean una 'malla' que cubre toda la franja 11h–03h.",
                "El refuerzo 4€ densifica el pico 19h–23h donde hay más usuarios buscando.",
            ];
            $profiles = [
                makeProfile(1, 'TOP + Autorenueva 7€  (eje principal)',
                    ['TOP' => true, 'auto7' => ['start' => '11:00', 'end' => '23:00', 'n' => 10]],
                    PRICE_TOP + PRICE_AUTO_7,
                    'TOP rotante en zona B + autorenueva 11:00–23:00 (≈cada 72 min)'
                ),
                makeProfile(2, 'TOP + Autorenueva 7€  (offset +30 min)',
                    ['TOP' => true, 'auto7' => ['start' => '11:30', 'end' => '02:45', 'n' => 10]],
                    PRICE_TOP + PRICE_AUTO_7,
                    'Segundo TOP + rango 11:30–02:45 · los 30 min de offset hacen que P1 y P2 nunca suban al mismo tiempo'
                ),
                makeProfile(3, 'Autorenueva Refuerzo 4€  (prime time)',
                    ['auto4' => ['start' => '19:15', 'end' => '23:15', 'n' => 4]],
                    PRICE_AUTO_4,
                    'Refuerzo 19:15–23:15 · distinto del P1 (−45 min) y P2 (−15 min) · añade subidas en el pico de mayor tráfico'
                ),
                makeProfile(4, 'Gratuito ×2',
                    ['free' => ['14:00', '22:00']],
                    0.0,
                    'Gratuito manual · refuerzo sin coste en mediodía y noche'
                ),
            ];
            break;

        default: // alta / muy_alta
            $reasons = [
                "{$tops} TOPs y {$prems} PREMIUMs activos → competencia alta, se necesita presencia en zona A.",
                "PREMIUM es imprescindible: sin él tu perfil no aparece en el carrusel superior.",
                "3 autorenuevas con offsets distintos crean una malla continua de subidas.",
                "El refuerzo de madrugada (4€) aprovecha el hueco de baja competencia: tu anuncio permanece arriba más tiempo.",
                "Estrategia más costosa pero la única que garantiza omnipresencia real en ciudades grandes.",
            ];
            $profiles = [
                makeProfile(1, 'PREMIUM + TOP + Autorenueva 7€',
                    ['PREMIUM' => true, 'TOP' => true, 'auto7' => ['start' => '11:00', 'end' => '23:00', 'n' => 10]],
                    PRICE_PREMIUM + PRICE_TOP + PRICE_AUTO_7,
                    'Zona A (carrusel 24h/30días) + zona B + autorenueva 11:00–23:00 → presencia máxima'
                ),
                makeProfile(2, 'TOP + Autorenueva 7€  (tarde-madrugada)',
                    ['TOP' => true, 'auto7' => ['start' => '12:00', 'end' => '03:00', 'n' => 10]],
                    PRICE_TOP + PRICE_AUTO_7,
                    'Segundo TOP + rango 12:00–03:00 (offset +60 min) · cubre tarde, noche y madrugada'
                ),
                makeProfile(3, 'Autorenueva 7€  (tercer offset)',
                    ['auto7' => ['start' => '11:45', 'end' => '23:45', 'n' => 10]],
                    PRICE_AUTO_7,
                    'Tercera tanda 11:45–23:45 (offset +45 min) · junto con P1 y P2 forma una malla cada ~24 min'
                ),
                makeProfile(4, 'Autorenueva Refuerzo 4€  (prime time)',
                    ['auto4' => ['start' => '19:30', 'end' => '23:30', 'n' => 4]],
                    PRICE_AUTO_4,
                    'Refuerzo 19:30–23:30 · pico máximo de visitas · densifica la franja más valiosa del día'
                ),
                makeProfile(5, 'Autorenueva Refuerzo 4€  (madrugada)',
                    ['auto4' => ['start' => '01:00', 'end' => '04:00', 'n' => 4]],
                    PRICE_AUTO_4,
                    'Refuerzo 01:00–04:00 · muy poca competencia activa → anuncio permanece en top durante horas'
                ),
            ];
            break;
    }

    // ── Calcular tiempos de subida reales para cada autorenueva ──
    $allFirings = [];
    foreach ($profiles as &$p) {
        foreach (['auto7', 'auto4'] as $aType) {
            if (!isset($p['opts'][$aType])) continue;
            $r = $p['opts'][$aType];
            $times = calcFirings($r['start'], $r['end'], $r['n']);
            $p['firings'][$aType] = $times;
            $allFirings[] = [
                'profile' => $p['num'],
                'pname'   => $p['name'],
                'type'    => $aType,
                'start'   => $r['start'],
                'end'     => $r['end'],
                'n'       => $r['n'],
                'times'   => $times,
            ];
        }
    }
    unset($p);

    // ── Verificar solapamientos entre autorenuevas ──
    $overlapWarnings = [];
    $fc = count($allFirings);
    for ($i = 0; $i < $fc; $i++) {
        for ($j = $i + 1; $j < $fc; $j++) {
            $conflicts = detectOverlap($allFirings[$i]['times'], $allFirings[$j]['times'], 6);
            foreach ($conflicts as $c) {
                $pi = $allFirings[$i]['profile'];
                $pj = $allFirings[$j]['profile'];
                $overlapWarnings[] = "P{$pi} vs P{$pj}: subidas a {$c}";
            }
        }
    }

    return [
        'girl'           => $g,
        'level'          => $level,
        'profiles'       => $profiles,
        'reasons'        => $reasons,
        'cost'           => (float) array_sum(array_column($profiles, 'cost')),
        'allFirings'     => $allFirings,
        'overlapWarnings'=> $overlapWarnings,
    ];
}

// ═══════════════════════════════════════════════════════════
//  GENERADOR DE TIMELINE SVG
// ═══════════════════════════════════════════════════════════

function renderTimelineSvg(array $allFirings): string
{
    if (empty($allFirings)) return '';

    $palette  = ['#6366F1','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#06B6D4'];
    $colorMap = [];
    foreach ($allFirings as $f) {
        $idx = $f['profile'] - 1;
        $colorMap[$f['profile']] = $palette[$idx % count($palette)];
    }

    $rowH    = 42;
    $labelW  = 145;
    $chartW  = 570;
    $padTop  = 24;
    $padBot  = 30;
    $rows    = count($allFirings);
    $totalH  = $padTop + $rows * $rowH + $padBot;
    $totalW  = $labelW + $chartW + 10;

    $out  = "<svg width='100%' viewBox='0 0 {$totalW} {$totalH}' "
          . "xmlns='http://www.w3.org/2000/svg' "
          . "style='font-family:system-ui,sans-serif;display:block'>";

    // ── Eje de horas ──
    for ($h = 0; $h <= 24; $h += 3) {
        $x    = $labelW + ($h / 24) * $chartW;
        $lbl  = sprintf('%02d', $h % 24) . 'h';
        $gridY1 = $padTop - 6;
        $gridY2 = $padTop + $rows * $rowH + 6;
        $out .= "<line x1='{$x}' y1='{$gridY1}' x2='{$x}' y2='{$gridY2}' "
              . "stroke='#e5e7eb' stroke-width='1'/>";
        $out .= "<text x='{$x}' y='" . ($padTop + $rows * $rowH + 20) . "' "
              . "text-anchor='middle' font-size='9' fill='#9ca3af'>{$lbl}</text>";
    }

    foreach ($allFirings as $ri => $row) {
        $y     = $padTop + $ri * $rowH;
        $yMid  = $y + $rowH / 2;
        $color = $colorMap[$row['profile']];

        // Hex → RGB para rgba()
        $hex = ltrim($color, '#');
        $r   = hexdec(substr($hex, 0, 2));
        $g2  = hexdec(substr($hex, 2, 2));
        $b   = hexdec(substr($hex, 4, 2));

        // Label izquierda
        $typeLabel = $row['type'] === 'auto7' ? '10 sub/día' : '4 sub/día';
        $out .= "<text x='" . ($labelW - 8) . "' y='" . ($yMid - 4) . "' "
              . "text-anchor='end' font-size='10' font-weight='600' fill='#374151'>"
              . "P{$row['profile']}</text>";
        $out .= "<text x='" . ($labelW - 8) . "' y='" . ($yMid + 8) . "' "
              . "text-anchor='end' font-size='9' fill='#6b7280'>{$typeLabel}</text>";

        // Fondo de fila
        $out .= "<rect x='{$labelW}' y='" . ($y + 5) . "' width='{$chartW}' "
              . "height='" . ($rowH - 10) . "' fill='#f9fafb' rx='4'/>";

        // Barra de rango activo (puede ser nocturno → 2 segmentos)
        $sMin = toMins($row['start']);
        $eMin = toMins($row['end']);
        $overnight = ($eMin < $sMin);
        if ($overnight) $eMin += 1440;

        $drawRange = function(int $s, int $e) use ($labelW, $chartW, $y, $rowH, $color, $r, $g2, $b, &$out) {
            $x  = $labelW + ($s / 1440) * $chartW;
            $w  = max(2, (($e - $s) / 1440) * $chartW);
            $out .= "<rect x='{$x}' y='" . ($y + 5) . "' width='{$w}' "
                  . "height='" . ($rowH - 10) . "' "
                  . "fill='rgba({$r},{$g2},{$b},0.12)' rx='4'/>";
            $out .= "<rect x='{$x}' y='" . ($y + 5) . "' width='{$w}' "
                  . "height='" . ($rowH - 10) . "' "
                  . "fill='none' stroke='{$color}' stroke-width='1.5' rx='4' opacity='0.55'/>";
        };

        if (!$overnight) {
            $drawRange($sMin, $eMin);
        } else {
            $drawRange($sMin, 1440);
            $drawRange(0, toMins($row['end']));
        }

        // Puntos de subida
        foreach ($row['times'] as $ti => $t) {
            $tMin = toMins($t);
            $dotX = $labelW + ($tMin / 1440) * $chartW;

            // Círculo
            $out .= "<circle cx='{$dotX}' cy='{$yMid}' r='4.5' fill='{$color}' opacity='0.9'/>";
            $out .= "<circle cx='{$dotX}' cy='{$yMid}' r='4.5' fill='none' "
                  . "stroke='white' stroke-width='1'/>";

            // Etiqueta de hora (alternando arriba/abajo para no pisar)
            $labelY = ($ti % 2 === 0) ? ($y + 3) : ($y + $rowH + 1);
            $out .= "<text x='{$dotX}' y='{$labelY}' text-anchor='middle' "
                  . "font-size='7.5' fill='{$color}' font-weight='500'>{$t}</text>";
        }

        // Etiquetas de start/end del rango
        $out .= "<text x='" . ($labelW + ($sMin / 1440) * $chartW + 3) . "' "
              . "y='" . ($y + $rowH - 3) . "' font-size='8' fill='{$color}' opacity='0.7'>"
              . $row['start'] . "</text>";
        $endDisp = $overnight ? $row['end'] : toHHMM($eMin);
        $endX = $labelW + (toMins($row['end']) / 1440) * $chartW - 3;
        $out .= "<text x='{$endX}' y='" . ($y + $rowH - 3) . "' text-anchor='end' "
              . "font-size='8' fill='{$color}' opacity='0.7'>{$endDisp}</text>";
    }

    $out .= '</svg>';
    return $out;
}

// ═══════════════════════════════════════════════════════════
//  HELPERS DE PRESENTACIÓN
// ═══════════════════════════════════════════════════════════

function levelLabel(string $l): string
{
    return ['muy_baja'=>'Muy baja','baja'=>'Baja','media'=>'Media',
            'alta'=>'Alta','muy_alta'=>'Muy alta'][$l] ?? $l;
}
function levelFg(string $l): string
{
    return ['muy_baja'=>'#065F46','baja'=>'#1E40AF','media'=>'#92400E',
            'alta'=>'#991B1B','muy_alta'=>'#4C1D95'][$l] ?? '#374151';
}
function levelBg(string $l): string
{
    return ['muy_baja'=>'#D1FAE5','baja'=>'#DBEAFE','media'=>'#FEF3C7',
            'alta'=>'#FEE2E2','muy_alta'=>'#EDE9FE'][$l] ?? '#F3F4F6';
}
function levelIcon(string $l): string
{
    return ['muy_baja'=>'●','baja'=>'◑','media'=>'◕','alta'=>'◉','muy_alta'=>'⬤'][$l] ?? '?';
}

function badgeLine(array $opts): string
{
    $map = [
        'PREMIUM' => ['#FDF2F8','#9D174D','PREMIUM'],
        'TOP'     => ['#EFF6FF','#1D4ED8','TOP'],
        'auto7'   => ['#F0FDF4','#15803D','Auto 7€ · 10/día'],
        'auto4'   => ['#FFFBEB','#92400E','Auto 4€ · 4/día'],
        'free'    => ['#F9FAFB','#374151','Gratuito'],
    ];
    $out = '';
    foreach ($map as $key => [$bg, $fg, $lbl]) {
        if (isset($opts[$key])) {
            $out .= "<span class='badge' style='background:{$bg};color:{$fg};border-color:{$fg}40'>{$lbl}</span> ";
        }
    }
    return $out;
}

function euros(float $v): string
{
    return number_format($v, 2, ',', '.') . ' €';
}

// ═══════════════════════════════════════════════════════════
//  PROCESAMIENTO DEL FORMULARIO
// ═══════════════════════════════════════════════════════════

$defaults = ['city' => 'Burriana', 'province' => 'Castellón', 'category' => '', 'num_girls' => '1'];
$form     = array_merge($defaults, array_intersect_key($_POST ?: [], $defaults));
$result   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analyze'])) {
    $comp       = scrape(trim($form['city']), $form['category']);
    $numGirls   = max(1, min(8, (int)$form['num_girls']));
    $strategies = buildStrategy($comp, $numGirls);
    $grandTotal = (float) array_sum(array_column($strategies, 'cost'));

    $result = [
        'city'       => trim($form['city']),
        'province'   => trim($form['province']),
        'catLabel'   => CATEGORIES[$form['category']] ?? 'Todas',
        'numGirls'   => $numGirls,
        'comp'       => $comp,
        'strategies' => $strategies,
        'grandTotal' => $grandTotal,
    ];
}

$fCity    = htmlspecialchars($form['city']);
$fProv    = htmlspecialchars($form['province']);
$fGirls   = (int)$form['num_girls'];
$fCat     = $form['category'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Estrategia Publicidad · destacamos.net</title>
<style>
/* ── Reset ─────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
       background: #f1f5f9; color: #1e293b; font-size: 15px; line-height: 1.6; }
a { color: #6366f1; }

/* ── Layout ─────────────────────────────────────────────── */
.page-wrap   { max-width: 1100px; margin: 0 auto; padding: 32px 16px 60px; }
.top-header  { display:flex; align-items:center; gap:14px; margin-bottom: 32px; }
.top-icon    { width:52px; height:52px; background:#6366f1; border-radius:14px;
               display:flex; align-items:center; justify-content:center;
               font-size:24px; flex-shrink:0; }
.top-title   { font-size:22px; font-weight:700; color:#1e293b; }
.top-sub     { font-size:13px; color:#64748b; margin-top:2px; }
.two-col     { display:grid; grid-template-columns:320px 1fr; gap:28px;
               align-items:start; }
@media(max-width:760px){ .two-col { grid-template-columns:1fr; } }

/* ── Card ────────────────────────────────────────────────── */
.card        { background:#fff; border:1px solid #e2e8f0; border-radius:16px;
               padding:24px; }
.card + .card { margin-top:20px; }
.card-title  { font-size:14px; font-weight:700; text-transform:uppercase;
               letter-spacing:.06em; color:#64748b; margin-bottom:16px; }

/* ── Form ────────────────────────────────────────────────── */
.form-group  { margin-bottom:18px; }
label        { display:block; font-size:13px; font-weight:600; color:#475569;
               margin-bottom:6px; }
input[type=text], select, input[type=number] {
    width:100%; padding:10px 14px; border:1px solid #cbd5e1; border-radius:10px;
    font-size:14px; background:#fff; color:#1e293b; outline:none;
    transition: border-color .15s, box-shadow .15s;
}
input:focus, select:focus {
    border-color:#6366f1; box-shadow:0 0 0 3px #6366f130;
}
.submit-btn  { width:100%; padding:13px; background:#6366f1; color:#fff;
               border:none; border-radius:12px; font-size:15px; font-weight:700;
               cursor:pointer; transition: background .15s, transform .1s;
               display:flex; align-items:center; justify-content:center; gap:8px; }
.submit-btn:hover  { background:#4f46e5; }
.submit-btn:active { transform:scale(.98); }

/* ── Badges ──────────────────────────────────────────────── */
.badge       { display:inline-block; padding:3px 9px; border-radius:20px;
               font-size:11px; font-weight:700; border:1px solid transparent;
               vertical-align:middle; }
.level-pill  { display:inline-flex; align-items:center; gap:6px;
               padding:5px 12px; border-radius:20px; font-size:12px; font-weight:700; }

/* ── Competition stats ───────────────────────────────────── */
.stat-grid   { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
.stat-box    { text-align:center; padding:14px 8px;
               background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; }
.stat-num    { font-size:26px; font-weight:800; color:#1e293b; }
.stat-lbl    { font-size:11px; color:#64748b; margin-top:2px; font-weight:600;
               text-transform:uppercase; letter-spacing:.04em; }

/* ── Strategy girl section ───────────────────────────────── */
.girl-card   { background:#fff; border:1px solid #e2e8f0; border-radius:16px;
               margin-bottom:24px; overflow:hidden; }
.girl-header { padding:16px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0;
               display:flex; align-items:center; justify-content:space-between;
               flex-wrap:wrap; gap:8px; }
.girl-title  { font-size:16px; font-weight:700; color:#1e293b; }
.girl-body   { padding:20px; }

/* ── Reasons list ────────────────────────────────────────── */
.reason-list { list-style:none; margin-bottom:20px; }
.reason-list li { padding:6px 0 6px 24px; position:relative;
                  font-size:13px; color:#475569; border-bottom:1px solid #f1f5f9; }
.reason-list li:last-child { border-bottom:none; }
.reason-list li::before { content:'→'; position:absolute; left:4px; color:#6366f1;
                           font-weight:700; }

/* ── Profile rows ────────────────────────────────────────── */
.profile-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
.profile-table th { font-size:11px; text-transform:uppercase; letter-spacing:.05em;
                    color:#94a3b8; font-weight:600; padding:0 10px 8px;
                    text-align:left; border-bottom:2px solid #f1f5f9; }
.profile-table td { padding:10px; vertical-align:top; border-bottom:1px solid #f8fafc; }
.profile-table tr:last-child td { border-bottom:none; }
.pnum        { width:32px; height:32px; border-radius:8px; background:#6366f110;
               color:#6366f1; font-weight:800; font-size:13px;
               display:flex; align-items:center; justify-content:center; }
.pname       { font-weight:700; font-size:13px; color:#1e293b; margin-bottom:4px; }
.pwhy        { font-size:12px; color:#64748b; }
.pcost       { font-weight:800; font-size:15px; color:#1e293b; white-space:nowrap; }
.pcost.free  { color:#10b981; }

/* ── Firing times ────────────────────────────────────────── */
.firings-wrap{ background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;
               padding:14px 16px; margin-bottom:20px; }
.firings-title{ font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase;
                letter-spacing:.05em; margin-bottom:10px; }
.firing-row  { display:flex; align-items:flex-start; gap:10px; margin-bottom:8px; }
.firing-row:last-child { margin-bottom:0; }
.firing-label{ font-size:12px; font-weight:600; color:#374151;
               min-width:100px; flex-shrink:0; }
.firing-times{ display:flex; flex-wrap:wrap; gap:5px; }
.firing-pill { font-size:11px; padding:2px 7px; border-radius:6px;
               background:#6366f115; color:#6366f1; font-weight:600;
               font-variant-numeric:tabular-nums; }

/* ── Timeline ────────────────────────────────────────────── */
.timeline-wrap{ background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;
                padding:16px; margin-bottom:20px; overflow-x:auto; }
.timeline-title{ font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase;
                 letter-spacing:.05em; margin-bottom:12px; }

/* ── Overlap warnings ────────────────────────────────────── */
.warn-list   { list-style:none; }
.warn-list li{ padding:5px 0 5px 22px; font-size:12px; color:#92400e;
               position:relative; }
.warn-list li::before { content:'⚠'; position:absolute; left:0; }

/* ── Cost summary ────────────────────────────────────────── */
.cost-summary{ background:#1e293b; color:#e2e8f0; border-radius:16px;
               padding:24px; margin-bottom:28px; }
.cost-grid   { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr));
               gap:12px; margin-top:14px; }
.cost-box    { background:#ffffff10; border-radius:10px; padding:14px 16px; }
.cost-box-lbl{ font-size:11px; color:#94a3b8; text-transform:uppercase;
               letter-spacing:.05em; margin-bottom:4px; }
.cost-box-val{ font-size:22px; font-weight:800; color:#fff; }

/* ── Notice / error ──────────────────────────────────────── */
.notice      { background:#fefce8; border:1px solid #fef08a; border-radius:10px;
               padding:12px 14px; font-size:13px; color:#713f12; margin-bottom:16px; }
.error-msg   { background:#fef2f2; border:1px solid #fecaca; border-radius:10px;
               padding:12px 14px; font-size:13px; color:#991b1b; margin-bottom:16px; }

/* ── URL badge ───────────────────────────────────────────── */
.url-badge   { font-size:11px; background:#f1f5f9; border:1px solid #e2e8f0;
               border-radius:8px; padding:4px 10px; color:#64748b;
               font-family:monospace; word-break:break-all; }

/* ── Empty state ──────────────────────────────────────────── */
.empty-state { text-align:center; padding:60px 20px; color:#94a3b8; }
.empty-state .icon { font-size:52px; margin-bottom:12px; }
.empty-state p     { font-size:14px; }
</style>
</head>
<body>
<div class="page-wrap">

  <!-- ══ HEADER ══ -->
  <div class="top-header">
    <div class="top-icon">📊</div>
    <div>
      <div class="top-title">Calculadora de Estrategia · destacamos.net</div>
      <div class="top-sub">Scraping en tiempo real + optimización de coste por chica</div>
    </div>
  </div>

  <div class="two-col">

    <!-- ══ COLUMNA IZQUIERDA: FORMULARIO ══ -->
    <div>
      <div class="card">
        <div class="card-title">Parámetros de análisis</div>

        <form method="POST" action="">

          <div class="form-group">
            <label for="city">Ciudad</label>
            <input type="text" id="city" name="city" value="<?= $fCity ?>"
                   placeholder="Ej: Burriana, Valencia, Castellón…" required>
          </div>

          <div class="form-group">
            <label for="province">Provincia</label>
            <input type="text" id="province" name="province" value="<?= $fProv ?>"
                   placeholder="Ej: Castellón">
          </div>

          <div class="form-group">
            <label for="category">Categoría</label>
            <select id="category" name="category">
              <?php foreach (CATEGORIES as $val => $lbl): ?>
                <option value="<?= htmlspecialchars($val) ?>"
                  <?= ($fCat === $val ? 'selected' : '') ?>>
                  <?= htmlspecialchars($lbl) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="num_girls">Número de chicas</label>
            <input type="number" id="num_girls" name="num_girls"
                   value="<?= $fGirls ?>" min="1" max="8">
          </div>

          <button type="submit" name="analyze" class="submit-btn">
            <span>🔍</span> Analizar y generar estrategia
          </button>
        </form>
      </div>

      <!-- Precios de referencia -->
      <div class="card" style="margin-top:16px">
        <div class="card-title">Precios configurados</div>
        <table style="width:100%;font-size:13px;border-collapse:collapse">
          <tr>
            <td style="padding:6px 0;color:#475569">TOP (10 días)</td>
            <td style="text-align:right;font-weight:700"><?= euros(PRICE_TOP) ?></td>
          </tr>
          <tr style="border-top:1px solid #f1f5f9">
            <td style="padding:6px 0;color:#475569">Autorenueva 10 sub/día (7€)</td>
            <td style="text-align:right;font-weight:700"><?= euros(PRICE_AUTO_7) ?></td>
          </tr>
          <tr style="border-top:1px solid #f1f5f9">
            <td style="padding:6px 0;color:#475569">Autorenueva 4 sub/día – refuerzo (4€)</td>
            <td style="text-align:right;font-weight:700"><?= euros(PRICE_AUTO_4) ?></td>
          </tr>
          <tr style="border-top:1px solid #f1f5f9">
            <td style="padding:6px 0;color:#475569">PREMIUM 30 días <em>(estimado)</em></td>
            <td style="text-align:right;font-weight:700"><?= euros(PRICE_PREMIUM) ?></td>
          </tr>
        </table>
      </div>
    </div>

    <!-- ══ COLUMNA DERECHA: RESULTADOS ══ -->
    <div>
    <?php if ($result === null): ?>
      <div class="card">
        <div class="empty-state">
          <div class="icon">📈</div>
          <p>Rellena el formulario y pulsa<br><strong>«Analizar y generar estrategia»</strong><br>para ver el plan optimizado.</p>
        </div>
      </div>

    <?php else:
      $comp  = $result['comp'];
      $level = $comp['level'];
    ?>

      <!-- ══ AVISO DE SCRAPING ══ -->
      <?php if (!empty($comp['notice'])): ?>
        <div class="notice">⚠️ <?= htmlspecialchars($comp['notice']) ?></div>
      <?php endif; ?>

      <!-- ══ ANÁLISIS DE COMPETENCIA ══ -->
      <div class="card" style="margin-bottom:20px">
        <div class="card-title">Análisis de competencia · <?= htmlspecialchars($result['city']) ?></div>

        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
          <span class="level-pill"
                style="background:<?= levelBg($level) ?>;color:<?= levelFg($level) ?>">
            <?= levelIcon($level) ?> Competencia <?= levelLabel($level) ?>
          </span>
          <span style="font-size:12px;color:#64748b">
            Categoría: <strong><?= htmlspecialchars($result['catLabel']) ?></strong>
          </span>
        </div>

        <div class="stat-grid" style="margin-bottom:14px">
          <div class="stat-box">
            <div class="stat-num" style="color:#9D174D"><?= $comp['premium'] ?></div>
            <div class="stat-lbl">PREMIUM</div>
          </div>
          <div class="stat-box">
            <div class="stat-num" style="color:#1D4ED8"><?= $comp['top'] ?></div>
            <div class="stat-lbl">TOPs</div>
          </div>
          <div class="stat-box">
            <div class="stat-num" style="color:#15803D"><?= $comp['auto'] ?></div>
            <div class="stat-lbl">Autorenuevas</div>
          </div>
          <div class="stat-box">
            <div class="stat-num"><?= $comp['total'] ?></div>
            <div class="stat-lbl">Total perfiles</div>
          </div>
        </div>

        <div class="url-badge">
          🔗 <?= htmlspecialchars($comp['url']) ?>
          <?php if ($comp['scraped']): ?>
            &nbsp;<span style="color:#10b981;font-weight:700">✓ datos en tiempo real</span>
          <?php else: ?>
            &nbsp;<span style="color:#f59e0b;font-weight:700">⚡ datos estimados</span>
          <?php endif; ?>
        </div>

        <?php
        $topNote = '';
        if ($comp['top'] <= 15) {
            $topNote = "✅ Con {$comp['top']} TOPs activos, <strong>todos son visibles simultáneamente</strong> (límite: 15). "
                     . "Un solo TOP aparece siempre en zona B.";
        } else {
            $topNote = "⚠️ Con {$comp['top']} TOPs activos se produce <strong>rotación aleatoria</strong>: "
                     . "solo 15 de los {$comp['top']} aparecen por carga de página. "
                     . "Necesitas más de 1 TOP para aumentar probabilidad de aparición.";
        }
        ?>
        <p style="font-size:12px;color:#475569;margin-top:10px"><?= $topNote ?></p>
      </div>

      <!-- ══ RESUMEN DE COSTE GLOBAL ══ -->
      <div class="cost-summary">
        <div style="font-size:13px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.05em">
          Inversión total estimada · <?= $result['numGirls'] ?> chica<?= ($result['numGirls'] > 1 ? 's' : '') ?>
        </div>
        <div class="cost-grid">
          <div class="cost-box">
            <div class="cost-box-lbl">Total acumulado</div>
            <div class="cost-box-val"><?= euros($result['grandTotal']) ?></div>
          </div>
          <div class="cost-box">
            <div class="cost-box-lbl">Coste por chica</div>
            <div class="cost-box-val">
              <?= $result['numGirls'] > 0 ? euros($result['grandTotal'] / $result['numGirls']) : '—' ?>
            </div>
          </div>
          <div class="cost-box">
            <div class="cost-box-lbl">Nivel competencia</div>
            <div class="cost-box-val" style="font-size:16px"><?= levelLabel($level) ?></div>
          </div>
        </div>
        <p style="font-size:11px;color:#64748b;margin-top:12px">
          * Los precios TOP y autorenuevas son los indicados (9€/7€/4€). PREMIUM estimado a <?= euros(PRICE_PREMIUM) ?> — verificar en la web.
        </p>
      </div>

      <!-- ══ ESTRATEGIAS POR CHICA ══ -->
      <?php foreach ($result['strategies'] as $s):
        $g = $s['girl'];
        $palette = ['#6366F1','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#06B6D4'];
        $gColor  = $palette[($g - 1) % count($palette)];
      ?>
      <div class="girl-card">

        <!-- Cabecera -->
        <div class="girl-header">
          <div style="display:flex;align-items:center;gap:12px">
            <div style="width:38px;height:38px;border-radius:10px;
                        background:<?= $gColor ?>20;color:<?= $gColor ?>;
                        font-weight:900;font-size:15px;display:flex;
                        align-items:center;justify-content:center">
              #<?= $g ?>
            </div>
            <div>
              <div class="girl-title">Chica <?= $g ?></div>
              <div style="font-size:12px;color:#64748b">
                <?= count($s['profiles']) ?> perfiles · <?= euros($s['cost']) ?> / período
              </div>
            </div>
          </div>
          <span class="level-pill"
                style="background:<?= levelBg($s['level']) ?>;color:<?= levelFg($s['level']) ?>">
            <?= levelIcon($s['level']) ?> <?= levelLabel($s['level']) ?>
          </span>
        </div>

        <div class="girl-body">

          <!-- Por qué esta estrategia -->
          <div style="margin-bottom:18px">
            <div style="font-size:12px;font-weight:700;color:#64748b;
                        text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">
              🧠 Por qué esta estrategia
            </div>
            <ul class="reason-list">
              <?php foreach ($s['reasons'] as $r): ?>
                <li><?= htmlspecialchars($r) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>

          <!-- Tabla de perfiles -->
          <div style="font-size:12px;font-weight:700;color:#64748b;
                      text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px">
            📋 Perfiles a crear / comprar
          </div>
          <table class="profile-table">
            <thead>
              <tr>
                <th style="width:42px">#</th>
                <th>Nombre del perfil</th>
                <th>Productos</th>
                <th>Nota</th>
                <th style="text-align:right">Coste</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($s['profiles'] as $p): ?>
              <tr>
                <td><div class="pnum"><?= $p['num'] ?></div></td>
                <td><div class="pname"><?= htmlspecialchars($p['name']) ?></div></td>
                <td><?= badgeLine($p['opts']) ?></td>
                <td><div class="pwhy"><?= htmlspecialchars($p['why']) ?></div></td>
                <td style="text-align:right">
                  <span class="pcost <?= ($p['cost'] == 0.0 ? 'free' : '') ?>">
                    <?= $p['cost'] == 0.0 ? 'Gratis' : euros($p['cost']) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="border-top:2px solid #e2e8f0">
                <td colspan="4" style="padding:10px;font-weight:700;font-size:13px">
                  Total inversión chica <?= $g ?>
                </td>
                <td style="text-align:right;padding:10px;font-size:16px;font-weight:900;color:<?= $gColor ?>">
                  <?= euros($s['cost']) ?>
                </td>
              </tr>
            </tfoot>
          </table>

          <!-- Tiempos de subida exactos -->
          <?php if (!empty($s['allFirings'])): ?>
          <div class="firings-wrap">
            <div class="firings-title">🕐 Horarios exactos de subida (por autorenueva)</div>
            <?php
            $fpColors = ['#6366F1','#10B981','#F59E0B','#EF4444','#8B5CF6'];
            $fi = 0;
            ?>
            <?php foreach ($s['allFirings'] as $f): ?>
            <?php
              $fc = $fpColors[$fi % count($fpColors)];
              $typeLabel = ($f['type'] === 'auto7') ? "P{$f['profile']} · Autorenueva 7€ · 10 sub/día" : "P{$f['profile']} · Refuerzo 4€ · 4 sub/día";
              $rangeLabel = $f['start'] . ' → ' . $f['end'];
            ?>
            <div class="firing-row">
              <div class="firing-label" style="color:<?= $fc ?>">
                <?= htmlspecialchars($typeLabel) ?><br>
                <span style="font-size:10px;color:#94a3b8"><?= $rangeLabel ?></span>
              </div>
              <div class="firing-times">
                <?php foreach ($f['times'] as $t): ?>
                  <span class="firing-pill" style="background:<?= $fc ?>18;color:<?= $fc ?>">
                    <?= htmlspecialchars($t) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            </div>
            <?php $fi++; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Timeline SVG -->
          <?php if (!empty($s['allFirings'])): ?>
          <div class="timeline-wrap">
            <div class="timeline-title">📅 Vista de línea de tiempo (24 horas)</div>
            <?= renderTimelineSvg($s['allFirings']) ?>
            <p style="font-size:11px;color:#94a3b8;margin-top:8px">
              Cada fila = una autorenueva · Barra coloreada = rango activo · Puntos = momento exacto de subida
            </p>
          </div>
          <?php endif; ?>

          <!-- Advertencias de solapamiento -->
          <?php if (!empty($s['overlapWarnings'])): ?>
          <div style="background:#fefce8;border:1px solid #fef08a;border-radius:10px;
                      padding:12px 14px;margin-bottom:16px">
            <div style="font-size:12px;font-weight:700;color:#713f12;margin-bottom:6px">
              ⚠️ Subidas muy próximas detectadas (menos de 6 min)
            </div>
            <ul class="warn-list">
              <?php foreach ($s['overlapWarnings'] as $w): ?>
                <li><?= htmlspecialchars($w) ?></li>
              <?php endforeach; ?>
            </ul>
            <p style="font-size:11px;color:#92400e;margin-top:6px">
              Esto no es un error — son perfiles distintos. Pero si quieres más separación, ajusta los rangos horarios en el código.
            </p>
          </div>
          <?php endif; ?>

          <!-- Gratuitos: instrucciones manuales -->
          <?php foreach ($s['profiles'] as $p): ?>
          <?php if (isset($p['opts']['free'])): ?>
          <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;
                      padding:12px 14px">
            <div style="font-size:12px;font-weight:700;color:#15803d;margin-bottom:4px">
              📌 Perfil gratuito P<?= $p['num'] ?> — Subida manual
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <?php foreach ($p['opts']['free'] as $freeT): ?>
                <span style="background:#dcfce7;color:#166534;padding:4px 10px;
                             border-radius:8px;font-size:12px;font-weight:700">
                  ⏰ <?= htmlspecialchars($freeT) ?>
                </span>
              <?php endforeach; ?>
            </div>
            <p style="font-size:11px;color:#16a34a;margin-top:6px">
              Sube este perfil manualmente a las horas indicadas · una vez cada 12h · sin coste
            </p>
          </div>
          <?php endif; ?>
          <?php endforeach; ?>

        </div><!-- /girl-body -->
      </div><!-- /girl-card -->
      <?php endforeach; ?>

    <?php endif; ?>
    </div>
  </div><!-- /two-col -->

  <footer style="text-align:center;font-size:12px;color:#94a3b8;margin-top:32px;padding-top:20px;border-top:1px solid #e2e8f0">
    Herramienta de análisis privada · Los precios son estimaciones basadas en la configuración definida
    · Verificar siempre los precios actuales en destacamos.net antes de comprar
  </footer>

</div><!-- /page-wrap -->
</body>
</html>