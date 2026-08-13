<?php

/**
 * Bridge between CRM voice assistant and AutoTube system.
 * Reads AutoTube's SQLite database for real-time stats.
 */

function autotube_get_summary() {
    $dbPath = '/root/autotube/database/autotube.db';
    if (!file_exists($dbPath) || !class_exists('SQLite3')) {
        return array('ok' => false, 'error' => 'AutoTube database not accessible');
    }

    $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
    $db->busyTimeout(5000);

    // Canales activos con sus últimas stats
    $canales = array();
    $res = @$db->query("
        SELECT c.name, c.slug, c.active,
               COALESCE(cs.subscribers, 0) as subscribers,
               COALESCE(cs.watch_hours, 0) as watch_hours,
               COALESCE(cs.estimated_revenue, 0) as revenue,
               COALESCE(cs.total_views, 0) as total_views
        FROM channels c
        LEFT JOIN channel_stats_history cs ON cs.slug = c.slug
            AND cs.recorded_at = (SELECT MAX(recorded_at) FROM channel_stats_history WHERE slug = c.slug)
        WHERE c.active = 1
        ORDER BY c.name
    ");
    if ($res) {
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) $canales[] = $row;
    }

    // Total views + revenue último mes
    $totales = array('views' => 0, 'revenue' => 0, 'watch_hours' => 0);
    if ($res2 = @$db->querySingle("
        SELECT COALESCE(SUM(views),0) as views,
               COALESCE(SUM(estimated_revenue),0) as revenue,
               COALESCE(SUM(watch_hours),0) as watch_hours
        FROM video_stats_history
        WHERE recorded_at >= date('now','-30 days')
    ", true)) {
        $totales = $res2;
    }

    // Próximos videos programados
    $proximos = array();
    if ($res3 = @$db->query("
        SELECT v.titulo_final as title, ps.target_public_at as due_at, c.name as canal
        FROM planned_slots ps
        JOIN videos v ON ps.video_id = v.id
        JOIN channels c ON v.channel_id = c.id
        WHERE ps.target_public_at > datetime('now')
          AND ps.status = 'planned'
        ORDER BY ps.target_public_at LIMIT 3
    ")) {
        while ($row = $res3->fetchArray(SQLITE3_ASSOC)) $proximos[] = $row;
    }

    // YPP progress por canal (1000 subs, 4000h)
    foreach ($canales as &$c) {
        $subs = (float)($c['subscribers'] ?? 0);
        $watch = (float)($c['watch_hours'] ?? 0);
        $c['ypp_sub_pct'] = $subs > 0 ? round(min($subs, 1000) / 1000 * 100, 1) : 0;
        $c['ypp_watch_pct'] = $watch > 0 ? round(min($watch, 4000) / 4000 * 100, 1) : 0;
        $c['ypp_overall_pct'] = round(($c['ypp_sub_pct'] + $c['ypp_watch_pct']) / 2, 1);
    }
    unset($c);

    $db->close();

    return array(
        'ok' => true,
        'canales' => $canales,
        'totales' => $totales,
        'proximos' => $proximos,
    );
}

function autotube_format_summary() {
    $data = autotube_get_summary();
    if (!$data['ok']) return null;

    $canales = $data['canales'] ?? array();
    $totales = $data['totales'] ?? array();
    $proximos = $data['proximos'] ?? array();

    $lines = array();
    $lines[] = "AutoTube: " . count($canales) . " canales activos";
    $views = number_format((int)($totales['views'] ?? 0));
    $revenue = number_format((float)($totales['revenue'] ?? 0), 2);
    $lines[] = "Último mes: {$views} views, \${$revenue} estimados";

    foreach ($canales as $c) {
        $name = $c['name'] ?? ($c['slug'] ?? '?');
        $ypp = (float)($c['ypp_overall_pct'] ?? 0);
        $subs = number_format((int)($c['subscribers'] ?? 0));
        $lines[] = "  {$name}: {$subs} subs, YPP al {$ypp}%";
    }

    if (!empty($proximos)) {
        $p = $proximos[0];
        $due = $p['due_at'] ?? '';
        $title = mb_substr((string)($p['title'] ?? ''), 0, 50, 'UTF-8');
        $lines[] = "Próximo video: {$title} (" . ($due !== '' ? date('d/m H:i', strtotime($due)) : 'pronto') . ")";
    }

    $lines[] = "Coste operativo: ~\$11/mes";
    return implode("\n", $lines);
}
