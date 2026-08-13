<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/storage.php';
require_once __DIR__ . '/../app/helpers.php';

$WEEKLY_PRICES = ['jcli0013'=>170, 'jcli_2bd0670c'=>130, 'jcli_0428b6e4'=>150, 'jcli_1e594eda'=>150];
$OUTPUT_PATH = DATA_PATH . '/jostal_deuda_report.html';
$MANUAL_CREDITS = ['jcli_2bd0670c'=>[['date'=>'2026-04-20','amount'=>130,'desc'=>'AJUSTE: deuda semana 5 condonada'],['date'=>'2026-05-04','amount'=>5,'desc'=>'AJUSTE: 5€ semana 7 condonados']],'jcli_0428b6e4'=>[['date'=>'2026-05-11','amount'=>150,'desc'=>'AJUSTE: deuda semana 1 condonada']]];

function is_alq($obs) {
    $d = mb_strtolower(trim((string)$obs), 'UTF-8');
    if ($d === '') return false;
    foreach (['alquil','alqil','akquil'] as $p) { if (mb_strpos($d,$p)!==false) return true; }
    similar_text($d,'alquiler',$pct); if ($pct>=70) return true;
    $ws = preg_split('/\s+/',$d,-1,PREG_SPLIT_NO_EMPTY);
    foreach ($ws as $w) { if (mb_strlen($w)>=4) { similar_text($w,'alquiler',$pct); if ($pct>=75) return true; } }
    return false;
}
function eur($a) { return number_format((float)$a,0,',','.').'€'; }
function fdate($d) { $t=strtotime((string)$d); return $t?date('d/m',$t):$d; }
function fes($d) {
    $t=strtotime((string)$d); if(!$t) return $d;
    static $m=['','ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    return (int)date('d',$t).' '.$m[(int)date('m',$t)];
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ── Data load ──
$clientas = storage_read('jostal_clientas.json');
$leads = storage_read('jostal_leads.json');
$by = [];
foreach ($leads as $l) { $cid=$l['clienta_id']??''; if($cid!=='') $by[$cid][]=$l; }
$ec = array_values(array_filter($clientas, fn($c)=>jostal_clienta_en_casa($c)));
usort($ec,function($a,$b){$pa=jostal_periodo_actual($a);$pb=jostal_periodo_actual($b);return strcmp($pa['entrada']??'',$pb['entrada']??'');});
$today = strtotime(date('Y-m-d').' 00:00:00');
$rep = [];

foreach ($ec as $cl) {
    $cid=$cl['id']; $nom=$cl['nombre']??'?'; $nreal=$cl['nombre_real']??'';
    $pr=$WEEKLY_PRICES[$cid]??null; if($pr===null) continue;
    $pi=jostal_alquiler_payment_info($cl,time()); if(empty($pi['enabled'])) continue;
    $entry=$pi['entry_date']; $fd=$pi['first_due_date'];
    $fdts=strtotime($fd.' 00:00:00'); $dlbl=$pi['due_weekday_label'];
    $dds=[]; for($ts=$fdts;$ts<=$today;$ts=strtotime('+7 day',$ts)) $dds[]=date('Y-m-d',$ts);
    $nw=count($dds); if($nw===0) continue;

    $cls=$by[$cid]??[]; $aq=[]; $nq=[]; $non=0;
    foreach($cls as $l){
        $pd=substr((string)($l['created_at']??''),0,10); $am=(float)($l['precio']??0);
        if($pd===''||$am<=0) continue;
        if(is_alq($l['observacion']??'')){
            if($pd>=$entry) $aq[]=['date'=>$pd,'amount'=>$am,'desc'=>$l['observacion']??''];
        }else{ if($pd>=$entry){ $non+=$am; $nq[]=['date'=>$pd,'amount'=>$am,'desc'=>$l['observacion']??'']; } }
    }
    if(isset($MANUAL_CREDITS[$cid])){foreach($MANUAL_CREDITS[$cid] as $mc){if($mc['date']>=$entry)$aq[]=$mc;}}
    usort($aq,fn($a,$b)=>strcmp($a['date'],$b['date']));
    $wp=array_fill(0,$nw,[]); $wpt=array_fill(0,$nw,0);
    foreach($aq as $p){
        for($w=0;$w<$nw;$w++){
            $ps=($w===0)?$entry:$dds[$w-1]; $pe=$dds[$w];
            $ok=$p['date']>=$ps; if($w<$nw-1) $ok=$ok&&($p['date']<$pe); else $ok=$ok&&($p['date']<=$pe);
            if($ok){ $wp[$w][]=$p; $wpt[$w]+=$p['amount']; break; }
        }
    }
    $wnp=array_fill(0,$nw,[]); $wnpt=array_fill(0,$nw,0);
    foreach($nq as $p){
        for($w=0;$w<$nw;$w++){
            $ps=($w===0)?$entry:$dds[$w-1]; $pe=$dds[$w];
            $ok=$p['date']>=$ps; if($w<$nw-1) $ok=$ok&&($p['date']<$pe); else $ok=$ok&&($p['date']<=$pe);
            if($ok){ $wnp[$w][]=$p; $wnpt[$w]+=$p['amount']; break; }
        }
    }

    $rows=[]; $run=0; $tpaid=0;
    for($w=0;$w<$nw;$w++){
        $ps=($w===0)?$entry:$dds[$w-1]; $pe=$dds[$w];
        $paid=$wpt[$w]; $diff=$pr-$paid; $rb=$run; $run+=$diff; $pys=$wp[$w]; $tpaid+=$paid;
        $note='';
        if($diff>0){
            $note=$rb>0?'Faltan '.eur($diff).' (arrastra '.eur($rb).')':'Faltan '.eur($diff).' esta semana';
        }elseif($diff<0){
            $over=abs($diff);
            $note=$rb>0?'Pagó '.eur($over).' de más → cubre deuda atrasada':'Pagó '.eur($over).' de más → adelanta';
        }else{
            if($rb>0) $note='Arrastra '.eur($rb).' de semanas anteriores';
            elseif($rb<0) $note='Crédito de '.eur(abs($rb));
        }
        if($diff>0) $cls='row-debt';
        elseif($diff<0) $cls=($run>0)?'row-over':'row-ok';
        else $cls=($rb>0)?'row-over':(($rb<0)?'row-ok':'row-ok');
        if($run>0) $rcls='r-debt'; elseif($run<0) $rcls='r-credit'; else $rcls='r-ok';
        $rows[]=['week'=>$w+1,'period'=>fdate($ps).' → '.fdate($pe),'due'=>fdate($pe),
            'debe'=>$pr,'pays'=>$pys,'paid'=>$paid,'diff'=>$diff,'note'=>$note,
            'cls'=>$cls,'rcls'=>$rcls,'running'=>$run,'npays'=>$wnp[$w]];
    }
    $td=$pr*$nw; $tdeu=$td-$tpaid;
    $rep[]=['cid'=>$cid,'nom'=>$nom,'nreal'=>$nreal,'pr'=>$pr,'dlbl'=>$dlbl,'entry'=>$entry,
        'nw'=>$nw,'non'=>$non,'rows'=>$rows,'td'=>$td,'tpaid'=>$tpaid,'tdeu'=>$tdeu];
}

$gtd=0;$gtp=0;$gdu=0;
foreach($rep as $x){ $gtd+=$x['td']; $gtp+=$x['tpaid']; $gdu+=$x['tdeu']; }
$rdate = date('d/m/Y');

// ── HTML ──
$o = '';
function o($s) { global $o; $o .= $s; }

// CSS inline
o('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Informe de Deuda — Jostal</title><style>');
o('*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}');
o('body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:13px;color:#263238;background:#f5f7fa;line-height:1.5;-webkit-print-color-adjust:exact;print-color-adjust:exact}');
o('.report-header{background:#263238;color:#fff;padding:28px 36px 20px;text-align:center}');
o('.report-header h1{font-size:22px;font-weight:700;letter-spacing:-.3px;margin-bottom:6px}');
o('.report-header .sub{font-size:13px;opacity:.75}');
o('.legend{display:flex;gap:16px;justify-content:center;margin-top:12px;flex-wrap:wrap}');
o('.legend-item{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px}');
o('.leg-ok{background:#c8e6c9;color:#2e7d32}.leg-debt{background:#ffcdd2;color:#c62828}');
o('.leg-over{background:#ffe0b2;color:#e65100}.leg-credit{background:#bbdefb;color:#1565c0}');
o('.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;padding:28px 36px}');
o('.card{background:#fff;border-radius:10px;padding:18px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06);border-left:4px solid #bdbdbd}');
o('.card-ok{border-left-color:#4caf50}.card-debt{border-left-color:#f44336}');
o('.card h3{font-size:15px;font-weight:700;margin-bottom:4px}');
o('.card .meta{font-size:11px;color:#78909c;margin-bottom:10px}');
o('.card .nums{display:grid;grid-template-columns:1fr 1fr;gap:4px 12px;font-size:13px}');
o('.card .nums .lbl{color:#78909c}.card .nums .val{text-align:right;font-weight:600}');
o('.card .deuda-row{grid-column:1/-1;border-top:1px solid #eceff1;padding-top:6px;margin-top:2px;display:flex;justify-content:space-between;font-weight:700;font-size:14px}');
o('.card .deuda-ok{color:#2e7d32}.card .deuda-bad{color:#c62828}');
o('.card .bar-wrap{height:6px;background:#eceff1;border-radius:3px;margin-top:10px;overflow:hidden}');
o('.card .bar-fill{height:100%;border-radius:3px;transition:width .3s}');
o('.bar-green{background:#4caf50}.bar-red{background:#f44336}');
o('main{padding:0 36px 40px}');
o('.clienta-sec{margin-bottom:32px}');
o('.clienta-sec h2{font-size:16px;font-weight:700;color:#37474f;margin-bottom:4px;padding-bottom:6px;border-bottom:2px solid #eceff1}');
o('.non-alq{font-size:11px;color:#78909c;margin-bottom:10px}');
o('.debt-table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05)}');
o('.debt-table th{background:#37474f;color:#fff;font-size:11px;font-weight:600;text-align:left;padding:8px 10px;white-space:nowrap}');
o('.debt-table td{padding:7px 10px;font-size:12px;vertical-align:top;border-bottom:1px solid #eceff1}');
o('.td-pagos{min-width:150px}.td-otros{min-width:130px;font-size:10px}');
o('.row-ok{background:#e8f5e9}.row-debt{background:#ffebee}.row-over{background:#fff8e1}');
o('.p-line{display:flex;gap:8px;align-items:baseline;padding:1px 0}');
o('.p-date{color:#546e7a;font-size:10px;min-width:34px;font-weight:600}');
o('.p-amount{font-weight:600;color:#263238}');
o('.p-desc{font-size:9px;color:#90a4ae;font-style:italic}');
o('.p-none{color:#b0bec5;font-style:italic}');
o('.td-diff{font-weight:600;text-align:right}');
o('.diff-plus{color:#c62828}.diff-minus{color:#2e7d32}.diff-zero{color:#78909c}');
o('.td-note{font-size:10px;color:#546e7a;max-width:160px}');
o('.td-run{font-weight:700;text-align:right}');
o('.r-debt{color:#c62828}.r-ok{color:#2e7d32}.r-credit{color:#1565c0}');
o('.debt-table tfoot td{background:#eceff1;font-weight:700;font-size:12px;border-top:2px solid #cfd8dc}');
o('.tf-debt{color:#c62828}.tf-ok{color:#2e7d32}');
o('.global-summary h2{font-size:18px;font-weight:700;color:#37474f;margin:12px 0}');
o('.summary-table{width:100%;max-width:700px;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05)}');
o('.summary-table th{background:#455a64;color:#fff;font-size:11px;font-weight:600;text-align:left;padding:8px 14px}');
o('.summary-table td{padding:8px 14px;font-size:13px;border-bottom:1px solid #eceff1}');
o('.summary-table tfoot td{background:#eceff1;font-weight:700;border-top:2px solid #cfd8dc}');
o('.sum-debt{color:#c62828;font-weight:700}.sum-ok{color:#2e7d32;font-weight:700}');
o('.report-footer{text-align:center;padding:16px 36px 24px;font-size:11px;color:#90a4ae}');
o('@media print{@page{size:A4 landscape;margin:12mm}body{background:#fff;font-size:9pt}');
o('.report-header{padding:12px 24px 10px}.report-header h1{font-size:16pt}');
o('.cards{padding:12px 24px;grid-template-columns:repeat(4,1fr);gap:8px}.card{padding:10px 12px}');
o('.card h3{font-size:11pt}.card .nums{font-size:9pt}main{padding:0 24px 20px}');
o('.clienta-sec{page-break-before:always}.clienta-sec:first-of-type{page-break-before:avoid}');
o('.clienta-sec h2{font-size:12pt}.debt-table{font-size:8pt;box-shadow:none}');
o('.debt-table td,.debt-table th{padding:4px 6px;font-size:8pt}tr{page-break-inside:avoid}thead{display:table-header-group}');
o('.summary-table{box-shadow:none;font-size:9pt}}');
o('</style></head><body>');

// Header
o('<header class="report-header"><h1>Informe de Deuda — Jostal</h1>');
o('<div class="sub">Fecha: '.$rdate.' (hoy) · Solo pagos con \'alquiler\' en descripción (+ faltas de ortografía)</div>');
o('<div class="legend">');
o('<span class="legend-item leg-ok">✓ Pagado</span>');
o('<span class="legend-item leg-debt">⚠ Debe</span>');
o('<span class="legend-item leg-over">↻ Arrastra deuda</span>');
o('<span class="legend-item leg-credit">↗ Crédito</span>');
o('</div></header>');

// Cards
o('<section class="cards">');
foreach ($rep as $r) {
    $pct = $r['td']>0 ? round($r['tpaid']/$r['td']*100) : 100;
    $cc = $r['tdeu']>0 ? 'card-debt' : 'card-ok';
    $dc = $r['tdeu']>0 ? 'deuda-bad' : 'deuda-ok';
    $bc = $r['tdeu']>0 ? 'bar-red' : 'bar-green';
    $dt = $r['tdeu']>0 ? '⚠ '.eur($r['tdeu']) : '✓ Al día';
    $nm = h($r['nom']);
    if ($r['nreal']!=='' && mb_strtolower($r['nreal'])!==mb_strtolower($r['nom'])) $nm.=' ('.h($r['nreal']).')';
    o('<div class="card '.$cc.'">');
    o('<h3>'.$nm.'</h3>');
    o('<div class="meta">'.$r['cid'].' · '.$r['nw'].' semanas · vence '.$r['dlbl'].'</div>');
    o('<div class="nums">');
    o('<span class="lbl">Precio/sem</span><span class="val">'.eur($r['pr']).'</span>');
    o('<span class="lbl">Entrada</span><span class="val">'.fes($r['entry']).'</span>');
    o('</div>');
    o('<div class="deuda-row"><span>DEUDA</span><span class="'.$dc.'">'.$dt.'</span></div>');
    o('<div class="bar-wrap"><div class="bar-fill '.$bc.'" style="width:'.$pct.'%"></div></div>');
    o('<div style="font-size:10px;color:#78909c;margin-top:4px;text-align:right">'.$pct.'% pagado</div>');
    o('</div>');
}
o('</section><main>');

// Clienta sections
foreach ($rep as $r) {
    $nm = h($r['nom']);
    if ($r['nreal']!=='' && mb_strtolower($r['nreal'])!==mb_strtolower($r['nom'])) $nm.=' ('.h($r['nreal']).')';
    $hdr = $nm.' — '.$r['cid'].' · '.eur($r['pr']).'/sem · vence '.$r['dlbl'].' · Entrada: '.fes($r['entry']).' · '.$r['nw'].' venc.';
    $nq = $r['non']>0 ? '<p class="non-alq">(+) '.eur($r['non']).' en pagos NO alquiler (clientes, fianza…) — no descuentan deuda</p>' : '';
    $tc = $r['tdeu']>0 ? 'tf-debt' : 'tf-ok';
    $dd = $r['tdeu']>0 ? '⚠ DEBE '.eur($r['tdeu']) : '✓ AL DÍA';

    o('<section class="clienta-sec">');
    o('<h2>'.$hdr.'</h2>');
    o($nq);
    o('<table class="debt-table"><thead><tr>');
    o('<th>Sem</th><th>Periodo</th><th>Vence</th><th>Debe</th><th>Pagos alquiler</th><th>Pagado</th><th>Dif.</th><th>Observación</th><th>Otros ingresos</th><th>Deuda acum.</th>');
    o('</tr></thead><tbody>');

    foreach ($r['rows'] as $row) {
        if (empty($row['pays'])) {
            $ph = '<span class="p-none">—</span>';
        } else {
            $ph = '';
            foreach ($row['pays'] as $pp) {
                $ph .= '<div class="p-line"><span class="p-date">'.fdate($pp['date']).'</span><span class="p-amount">'.eur($pp['amount']).'</span></div>';
            }
        }
        if ($row['diff']>0) $dh = '<span class="diff-plus">+'.eur($row['diff']).'</span>';
        elseif ($row['diff']<0) $dh = '<span class="diff-minus">'.eur($row['diff']).'</span>';
        else $dh = '<span class="diff-zero">0€</span>';

        if ($row['running']>0) $ri = '⚠';
        elseif ($row['running']<0) $ri = '↗';
        else $ri = '✓';

        // Otros ingresos (no alquiler)
        $nqrys = $row['npays'] ?? [];
        if (empty($nqrys)) {
            $nph = '<span class="p-none">—</span>';
        } else {
            $nph = '';
            foreach ($nqrys as $np) {
                $nd = trim((string)($np['desc'] ?? ''));
                $nlbl = ($nd !== '') ? h($nd) : '<em>(sin concepto)</em>';
                $nph .= '<div class="p-line"><span class="p-date">'.fdate($np['date']).'</span><span class="p-amount">'.eur($np['amount']).'</span><span class="p-desc">'.$nlbl.'</span></div>';
            }
        }

        o('<tr class="'.$row['cls'].'">');
        o('<td>'.$row['week'].'</td>');
        o('<td>'.h($row['period']).'</td>');
        o('<td>'.$row['due'].'</td>');
        o('<td>'.eur($row['debe']).'</td>');
        o('<td class="td-pagos">'.$ph.'</td>');
        o('<td>'.eur($row['paid']).'</td>');
        o('<td class="td-diff">'.$dh.'</td>');
        o('<td class="td-note">'.h($row['note']).'</td>');
        o('<td class="td-otros">'.$nph.'</td>');
        o('<td class="td-run '.$row['rcls'].'">'.eur($row['running']).' '.$ri.'</td>');
        o('</tr>');
    }

    o('</tbody><tfoot><tr>');
    o('<td colspan="4"><strong>TOTAL</strong></td>');
    o('<td colspan="2">Debe '.eur($r['td']).' · Pagado '.eur($r['tpaid']).'</td>');
    o('<td colspan="4" class="'.$tc.'"><strong>'.$dd.'</strong></td>');
    o('</tr></tfoot></table></section>');
}

// Global summary
o('<section class="global-summary"><h2>Resumen Global</h2>');
o('<table class="summary-table"><thead><tr><th>Clienta</th><th>Precio/sem</th><th>Semanas</th><th>Debe</th><th>Pagado</th><th>Deuda</th></tr></thead><tbody>');
foreach ($rep as $r) {
    $sc = $r['tdeu']>0 ? 'sum-debt' : 'sum-ok';
    $ic = $r['tdeu']>0 ? '⚠' : '✓';
    o('<tr><td>'.h($r['nom']).'</td>');
    o('<td>'.eur($r['pr']).'</td>');
    o('<td>'.$r['nw'].'</td>');
    o('<td>'.eur($r['td']).'</td>');
    o('<td>'.eur($r['tpaid']).'</td>');
    o('<td class="'.$sc.'">'.eur($r['tdeu']).' '.$ic.'</td></tr>');
}
o('</tbody><tfoot><tr>');
o('<td colspan="3"><strong>TOTAL</strong></td>');
o('<td><strong>'.eur($gtd).'</strong></td>');
o('<td><strong>'.eur($gtp).'</strong></td>');
o('<td><strong>'.eur($gdu).'</strong></td>');
o('</tr></tfoot></table></section>');

// Footer
o('</main><footer class="report-footer">');
o('<p>Pagos NO alquiler (clientes, fianza, taxi…) no descuentan deuda. Cálculo: precio semanal × vencimientos pasados − pagos de alquiler registrados.</p>');
o('<p>Generado el '.$rdate.' · <a href="#" onclick="window.print();return false" style="color:#1565c0">Imprimir / Guardar PDF</a></p>');
o('</footer></body></html>');

file_put_contents($OUTPUT_PATH, $o);
$kb = round(strlen($o)/1024,1);
echo "✅ {$OUTPUT_PATH} ({$kb} KB)\n";
echo "🌐 https://admin.casawasap.com/data/jostal_deuda_report.html\n";
echo "🖨  Abrir → Ctrl+P → Guardar PDF\n";
