<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/storage.php';
require_once __DIR__ . '/../app/helpers.php';

$WEEKLY_PRICES = [
    'jcli0013'      => 170,
    'jcli_2bd0670c' => 130,
    'jcli_0428b6e4' => 150,
    'jcli_1e594eda' => 150,
];

$MANUAL_CREDITS = [
    'jcli_2bd0670c' => [
        ['date' => '2026-04-20', 'amount' => 130, 'desc' => 'AJUSTE: deuda semana 5 condonada'],
        ['date' => '2026-05-04', 'amount' =>   5, 'desc' => 'AJUSTE: 5€ semana 7 condonados'],
    ],
    'jcli_0428b6e4' => [
        ['date' => '2026-05-11', 'amount' => 150, 'desc' => 'AJUSTE: deuda semana 1 condonada'],
    ],
];

function is_alquiler_payment($observacion) {
    $desc = mb_strtolower(trim((string) $observacion), 'UTF-8');
    if ($desc === '') return false;
    $direct = ['alquil', 'alqil', 'akquil'];
    foreach ($direct as $pat) {
        if (mb_strpos($desc, $pat) !== false) return true;
    }
    similar_text($desc, 'alquiler', $pct);
    if ($pct >= 70) return true;
    $words = preg_split('/\s+/', $desc, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($words as $word) {
        if (mb_strlen($word) >= 4) {
            similar_text($word, 'alquiler', $pct);
            if ($pct >= 75) return true;
        }
    }
    return false;
}

function fmt_euros($amount) {
    return number_format((float) $amount, 0, ',', '.') . "\xE2\x82\xAC";
}

function fmt_fecha_es($date) {
    $ts = strtotime((string) $date);
    if (!$ts) return $date;
    static $m = ['','ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    return (int) date('d', $ts) . ' ' . $m[(int) date('m', $ts)];
}

function fmt_fecha_corta($date) {
    $ts = strtotime((string) $date);
    return $ts ? date('d/m', $ts) : $date;
}

function truncate($str, $len) {
    $str = (string) $str;
    return mb_strlen($str) > $len ? mb_substr($str, 0, $len - 1) . "\xE2\x80\xA6" : $str;
}

$clientas = storage_read('jostal_clientas.json');
$leads    = storage_read('jostal_leads.json');

$leads_by_clienta = [];
foreach ($leads as $lead) {
    $cid = $lead['clienta_id'] ?? '';
    if ($cid === '') continue;
    $leads_by_clienta[$cid][] = $lead;
}

$en_casa = array_values(array_filter($clientas, function ($c) {
    return jostal_clienta_en_casa($c);
}));

usort($en_casa, function ($a, $b) {
    $pa = jostal_periodo_actual($a);
    $pb = jostal_periodo_actual($b);
    return strcmp($pa['entrada'] ?? '', $pb['entrada'] ?? '');
});

$today_ts = strtotime(date('Y-m-d') . ' 00:00:00');

$sep = str_repeat('=', 80);
echo $sep . "\n";
echo "  INFORME DE DEUDA - JOSTAL\n";
echo "  Fecha: " . date('d/m/Y') . " (hoy)\n";
echo "  Solo se cuentan pagos con 'alquiler' en descripcion (+ faltas de ortografia)\n";
echo $sep . "\n\n";

$gran_debe   = 0;
$gran_pagado = 0;
$gran_deuda  = 0;
$resumen     = [];

foreach ($en_casa as $clienta) {
    $cid            = $clienta['id'];
    $nombre         = $clienta['nombre'] ?? '?';
    $nombre_real    = $clienta['nombre_real'] ?? '';
    $precio_semanal = $WEEKLY_PRICES[$cid] ?? null;

    if ($precio_semanal === null) {
        echo "  !! $nombre ($cid): precio semanal NO definido - se salta\n\n";
        continue;
    }

    $pi = jostal_alquiler_payment_info($clienta, time());
    if (empty($pi['enabled'])) {
        echo "  !! $nombre ($cid): no esta en modo alquiler activo - se salta\n\n";
        continue;
    }

    $entry_date      = $pi['entry_date'];
    $first_due_date  = $pi['first_due_date'];
    $first_due_ts    = strtotime($first_due_date . ' 00:00:00');
    $due_weekday_lbl = $pi['due_weekday_label'];

    $due_dates = [];
    for ($ts = $first_due_ts; $ts <= $today_ts; $ts = strtotime('+7 day', $ts)) {
        $due_dates[] = date('Y-m-d', $ts);
    }
    $num_weeks = count($due_dates);

    if ($num_weeks === 0) {
        echo "  >> $nombre ($cid): entro hace < 7 dias, sin vencimientos aun\n\n";
        continue;
    }

    $clienta_leads      = $leads_by_clienta[$cid] ?? [];
    $alquiler_payments  = [];
    $non_alq_payments   = [];
    $non_alq_total      = 0;

    foreach ($clienta_leads as $lead) {
        $pdate  = substr((string) ($lead['created_at'] ?? ''), 0, 10);
        $amount = (float) ($lead['precio'] ?? 0);
        if ($pdate === '' || $amount <= 0) continue;

        if (is_alquiler_payment($lead['observacion'] ?? '')) {
            if ($pdate >= $entry_date) {
                $alquiler_payments[] = [
                    'date'   => $pdate,
                    'amount' => $amount,
                    'desc'   => $lead['observacion'] ?? '',
                ];
            }
        } else {
            if ($pdate >= $entry_date) {
                $non_alq_total += $amount;
                $non_alq_payments[] = [
                    'date'   => $pdate,
                    'amount' => $amount,
                    'desc'   => $lead['observacion'] ?? '',
                ];
            }
        }
    }

    // Inyectar créditos manuales
    if (isset($MANUAL_CREDITS[$cid])) {
        foreach ($MANUAL_CREDITS[$cid] as $mc) {
            if ($mc['date'] >= $entry_date) {
                $alquiler_payments[] = $mc;
            }
        }
    }

    usort($alquiler_payments, function ($a, $b) {
        return strcmp($a['date'], $b['date']);
    });

    $wp  = array_fill(0, $num_weeks, []);
    $wpt = array_fill(0, $num_weeks, 0);

    foreach ($alquiler_payments as $p) {
        for ($w = 0; $w < $num_weeks; $w++) {
            $ps = ($w === 0) ? $entry_date : $due_dates[$w - 1];
            $pe = $due_dates[$w];

            // Periodo: [ps, pe)  -- exclusivo al final para que un pago en la fecha
            // de vencimiento cuente para la semana SIGUIENTE (no para la que acaba).
            // La última semana usa [ps, pe] para capturar pagos en el último vencimiento.
            $in_range = $p['date'] >= $ps;
            if ($w < $num_weeks - 1) {
                $in_range = $in_range && ($p['date'] < $pe);
            } else {
                $in_range = $in_range && ($p['date'] <= $pe);
            }

            if ($in_range) {
                $wp[$w][]  = $p;
                $wpt[$w]  += $p['amount'];
                break;
            }
        }
    }

    // Asignar pagos NO alquiler a semanas (misma lógica de periodos)
    $wnp  = array_fill(0, $num_weeks, []);
    $wnpt = array_fill(0, $num_weeks, 0);
    foreach ($non_alq_payments as $p) {
        for ($w = 0; $w < $num_weeks; $w++) {
            $ps = ($w === 0) ? $entry_date : $due_dates[$w - 1];
            $pe = $due_dates[$w];
            $in_range = $p['date'] >= $ps;
            if ($w < $num_weeks - 1) {
                $in_range = $in_range && ($p['date'] < $pe);
            } else {
                $in_range = $in_range && ($p['date'] <= $pe);
            }
            if ($in_range) {
                $wnp[$w][]  = $p;
                $wnpt[$w]  += $p['amount'];
                break;
            }
        }
    }

    $hdr = "> $nombre";
    if ($nombre_real !== '' && mb_strtolower($nombre_real) !== mb_strtolower($nombre)) {
        $hdr .= " ($nombre_real)";
    }
    $hdr .= " - $cid";
    $hdr .= " . " . fmt_euros($precio_semanal) . "/sem";
    $hdr .= " . vence $due_weekday_lbl";
    $hdr .= " . Entrada: " . fmt_fecha_es($entry_date);
    $hdr .= " . $num_weeks vencimiento" . ($num_weeks !== 1 ? 's' : '');
    echo $hdr . "\n";

    if ($non_alq_total > 0) {
        echo "  (No alquiler: " . fmt_euros($non_alq_total) . " - clientes, fianza, etc. - no descuenta deuda)\n";
    }
    echo "\n";

    printf("  %-4s  %-16s  %-7s  %-7s  %-22s  %-7s  %-7s  %-20s  %s\n",
        'Sem', 'Periodo', 'Vence', 'Debe', 'Pagos alquiler', 'Pagado', 'Dif.', 'Otros ingresos', 'Deuda acum.');
    printf("  %-4s  %-16s  %-7s  %-7s  %-22s  %-7s  %-7s  %-20s  %s\n",
        '----', '--------------', '------', '-----', '--------------------', '------', '------', '--------------------', '----------');

    $running = 0;
    for ($w = 0; $w < $num_weeks; $w++) {
        $ps  = ($w === 0) ? $entry_date : $due_dates[$w - 1];
        $pe  = $due_dates[$w];
        $due = $due_dates[$w];

        $paid    = $wpt[$w];
        $diff    = $precio_semanal - $paid;
        $running += $diff;
        $pays    = $wp[$w];

        if (empty($pays)) {
            $ptext = '-';
        } else {
            $parts = [];
            foreach ($pays as $pp) {
                $parts[] = fmt_fecha_corta($pp['date']) . ' ' . fmt_euros($pp['amount']);
            }
            $ptext = implode('  ', $parts);
        }

        // Otros ingresos (no alquiler)
        $npays = $wnp[$w];
        if (empty($npays)) {
            $ntext = '-';
        } else {
            $nparts = [];
            foreach ($npays as $np) {
                $ndesc = trim((string)($np['desc'] ?? ''));
                $nparts[] = fmt_fecha_corta($np['date']) . ' ' . fmt_euros($np['amount'])
                          . ($ndesc !== '' ? ' ' . mb_substr($ndesc, 0, 12) : ' (sin concepto)');
            }
            $ntext = implode('  ', $nparts);
        }

        $icon = ($running > 0) ? '!!' : (($running === 0) ? 'OK' : '>>');

        printf("  %-4d  %-16s  %-7s  %-7s  %-22s  %-7s  %-7s  %-20s  %s %s\n",
            $w + 1,
            truncate(fmt_fecha_corta($ps) . ' -> ' . fmt_fecha_corta($pe), 16),
            fmt_fecha_corta($due),
            fmt_euros($precio_semanal),
            truncate($ptext, 22),
            fmt_euros($paid),
            ($diff >= 0 ? '+' : '') . fmt_euros($diff),
            truncate($ntext, 20),
            $icon,
            fmt_euros($running)
        );
    }

    echo "\n";
    $t_debe   = $precio_semanal * $num_weeks;
    $t_pagado = array_sum($wpt);
    $t_deuda  = $t_debe - $t_pagado;
    $estado   = ($t_deuda <= 0) ? 'OK AL DIA' : '!! DEBE ' . fmt_euros($t_deuda);

    echo "  ---------------------------------------------------\n";
    printf("  TOTAL: Debe %s . Pagado %s . DEUDA: %s\n",
        fmt_euros($t_debe), fmt_euros($t_pagado), $estado);
    echo "  ---------------------------------------------------\n\n";

    $gran_debe   += $t_debe;
    $gran_pagado += $t_pagado;
    $gran_deuda  += $t_deuda;
    $resumen[] = [
        'nombre'  => $nombre,
        'precio'  => $precio_semanal,
        'semanas' => $num_weeks,
        'debe'    => $t_debe,
        'pagado'  => $t_pagado,
        'deuda'   => $t_deuda,
    ];
}

echo $sep . "\n";
echo "  RESUMEN GLOBAL\n";
echo $sep . "\n\n";

printf("  %-22s  %-10s  %-8s  %-10s  %-10s  %-10s\n",
    'Clienta', 'Precio/sem', 'Semanas', 'Debe', 'Pagado', 'Deuda');
printf("  %-22s  %-10s  %-8s  %-10s  %-10s  %-10s\n",
    '----------------------', '----------', '--------', '----------', '----------', '----------');

foreach ($resumen as $r) {
    $icon = ($r['deuda'] > 0) ? '!!' : 'OK';
    printf("  %-22s  %-10s  %-8d  %-10s  %-10s  %-10s %s\n",
        truncate($r['nombre'], 22),
        fmt_euros($r['precio']),
        $r['semanas'],
        fmt_euros($r['debe']),
        fmt_euros($r['pagado']),
        fmt_euros($r['deuda']),
        $icon
    );
}

printf("  %-22s  %-10s  %-8s  %-10s  %-10s  %-10s\n",
    '----------------------', '----------', '--------', '----------', '----------', '----------');
printf("  %-22s  %-10s  %-8s  %-10s  %-10s  %-10s\n\n",
    'TOTAL', '', '', fmt_euros($gran_debe), fmt_euros($gran_pagado), fmt_euros($gran_deuda));

echo "  !! = debe dinero      OK = al dia      >> = credito a favor\n";
echo "  Pagos NO alquiler (clientes, fianza, etc.) no descuentan deuda.\n";
echo "  Calculo: precio semanal x vencimientos - pagos de alquiler registrados\n";
echo $sep . "\n";
