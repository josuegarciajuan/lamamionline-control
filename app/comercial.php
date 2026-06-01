<?php

function comercial_bootstrap_storage() {
    $jsonDefaults = array(
        'comercial_settings.json' => comercial_default_settings(),
        'comercial_processes.json' => comercial_build_default_processes(),
        'comercial_line_state.json' => array(),
        'comercial_threads.json' => array(),
        'comercial_ai_memory.json' => array(),
        'comercial_leads.json' => array(),
        'comercial_daily_stats.json' => array(),
        'comercial_blacklist.json' => array(),
    );

    foreach ($jsonDefaults as $file => $content) {
        $path = DATA_PATH . '/' . $file;
        if (!file_exists($path)) {
            file_put_contents($path, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    // ── Fix #4: inicializar archivos de enviados por rama ──
    $processes = comercial_get_processes();
    foreach ($processes as $process) {
        $slug = trim((string)($process['slug'] ?? ''));
        if ($slug === '') continue;
        $branchFile = 'comercial_sent_phones_' . preg_replace('/[^a-z0-9_\-]/i', '_', $slug) . '.json';
        $branchPath = DATA_PATH . '/' . $branchFile;
        if (!file_exists($branchPath)) {
            file_put_contents($branchPath, json_encode(array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    $jsonlFiles = array(
        DATA_PATH . '/comercial_events.jsonl',
        DATA_PATH . '/comercial_webhook_log.jsonl',
    );

    foreach (comercial_all_queue_files() as $path) {
        $jsonlFiles[] = $path;
    }

    foreach ($jsonlFiles as $path) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!file_exists($path)) {
            file_put_contents($path, '');
        }
    }

    // ── Fix #4: reconstruir global de enviados periódicamente (1/día) ──
    $dailyStats = storage_read('comercial_daily_stats.json');
    $lastRebuild = trim((string)(is_array($dailyStats) ? ($dailyStats['_sent_phones_rebuild_at'] ?? '') : ''));
    if ($lastRebuild === '' || strtotime($lastRebuild) < time() - 86400) {
        $count = comercial_rebuild_global_sent_phones();
        $stats = storage_read('comercial_daily_stats.json');
        if (!is_array($stats)) $stats = array();
        $stats['_sent_phones_rebuild_at'] = now_datetime();
        $stats['_sent_phones_rebuild_count'] = $count;
        storage_write('comercial_daily_stats.json', $stats);
    }
}

function comercial_default_settings() {
    return array(
        'waha_host' => 'http://100.117.92.74',
        'waha_api_key' => 'local321',
        'waha_session' => 'default',
        'typing_pre_min_sec' => 1,
        'typing_pre_max_sec' => 3,
        'typing_min_sec' => 2,
        'typing_max_sec' => 12,
        'typing_jitter_sec' => 2,
        // ── T4.4: rango efectivo de delay humano: pre (1-3s) + typing (~2-12s según longitud) + jitter (0-2s) = ~3-17s total ──
        'curl_timeout_sec' => 30,
        'global_daily_target' => 45,
        'ban_fail_streak_warning' => 3,
        'ban_fail_streak_pause' => 5,
        'ban_fail_ratio_warning' => 0.60,
        'ban_fail_ratio_pause' => 0.80,
        'ban_window_size' => 10,
        'cooldown_minutes_warning' => 60,
        'cooldown_minutes_pause' => 1440,
        'auto_followup_enabled' => 1,
        'auto_pause_enabled' => 1,
        'notify_only_after_second_reply' => 1,
        'ia_second_turn_enabled' => 1,
        'ia_learning_enabled' => 1,
        'conversation_max_auto_turns' => 5,
        'conversation_max_defers' => 2,
    );
}

function comercial_normalize_waha_host($host) {
    $host = trim((string)$host);
    if ($host === '') {
        $host = (string)(comercial_default_settings()['waha_host'] ?? 'http://100.117.92.74');
    }
    return rtrim($host, '/');
}

function comercial_waha_host_options($current = '') {
    $options = array(
        'http://100.117.92.74' => '100.117.92.74 · oficina',
        'http://100.113.76.93' => '100.113.76.93 · josue',
        'http://100.76.30.118' => '100.76.30.118 · liveyourdre2',
    );

    $current = comercial_normalize_waha_host($current);
    if ($current !== '' && !isset($options[$current])) {
        $label = preg_replace('#^https?://#i', '', $current);
        $options[$current] = $label . ' · actual';
    }

    return $options;
}

function comercial_adaptive_raise_after_seconds() {
    return 3 * 86400;
}

function comercial_adaptive_raise_step() {
    return 0.08;
}

function comercial_adaptive_max_power_factor() {
    return 1.35;
}

function comercial_adaptive_min_power_factor() {
    return 0.45;
}

function comercial_adaptive_global_drop_factor() {
    return 0.75;
}

function comercial_adaptive_global_drop_cooldown_seconds() {
    return 3 * 3600;
}

function comercial_queue_base_dir() {
    return DATA_PATH . '/comercial_queues';
}

function comercial_default_queue_files($slug) {
    $base = comercial_queue_base_dir();
    switch (trim((string)$slug)) {
        case 'publiscort':
            return array(
                $base . '/publiscort_1.jsonl',
                $base . '/publiscort_2.jsonl',
                $base . '/publiscort_3.jsonl',
            );
        case 'publicista':
            return array(
                $base . '/publicista_1.jsonl',
                $base . '/publicista_2.jsonl',
                $base . '/publicista_3.jsonl',
            );
        case 'casawasap':
            return array(
                $base . '/casawasap_1.jsonl',
                $base . '/casawasap_2.jsonl',
                $base . '/casawasap_3.jsonl',
            );
        default:
            return array();
    }
}

function comercial_hardcoded_process_slugs() {
    return array('plaza', 'lamami', 'publicista', 'casawasap', 'publiscort');
}

function comercial_process_has_hardcoded_templates($slug) {
    return in_array(trim((string)$slug), comercial_hardcoded_process_slugs(), true);
}

function comercial_default_process_templates($slug, $field = 'message_templates') {
    $slug = trim((string)$slug);
    $field = trim((string)$field);

    if ($field === 'followup_templates') {
        switch ($slug) {
            case 'plaza':
                return array(
                    "Holaa 😊\nTenemos casa grande y tranquila, limpieza diaria, varios baños, wifi + smartTV, sábanas/toallas y buen ambiente 🏠✨\n¿Qué te interesa más para entrar: PLAZA (compartida) o ALQUILER (habitación privada)?\nO si quieres, te paso las 2 opciones en 2 mensajitos. Ya me dices que prefieres cariño 😘"
                );
            case 'lamami':
                return array(
                    "Hola 😊 ¡Genial! Te explico un poco como funciona para que te empiecen a entrar citas extra 🚀\nEs súper simple:\n\n✅ Yo pongo la publi\n✅ Yo contesto los mensajes\n✅ A ti solo te llegan citas listas 📅 Te aviso 20 minutos antes de que vaya un cliente, me confirmas si estás disponible y te lo mando de camino a tu localización.\n✅ 29€ una única vez para siempre, en concepto de alta. Luego solo pagas una pequeña comisión por cada cliente extra que te llevamos.\n\nSi quieres arrancar HOY mismo, responde este mensaje y te digo que información has de enviar para empezar ya mismo 👇"
                );
            case 'publicista':
                return array(
                    "Hola 👋\nCasaWasap contesta los telefonos de tu casa todo el día (24/7), mantiene el hilo y filtra curiosos: a ti te llega solo aviso de los que tienen intención real de ir ✅\n\nSi tienes estas en contacto con casas de citas, te puedes sacar un buen extra por cada casa que hagas de intermediador. 100€ fijos mensuales por cada una.\n\nAl ser algo tan novedoso, es mejor explicar todas las ventajas en voz. Si te va bien, hacemos una llamada de 5 min y te explico todo un poco más"
                );
            case 'casawasap':
                return array(
                    "CasaWasap contesta los telefonos de tu casa todo el día (24/7), mantiene el hilo y filtra curiosos: a ti te llega solo aviso de los que tienen intención real de ir ✅\nAl ser algo tan novedoso, esto se entiende mejor en voz. Si te va bien, hacemos una llamada de 5 min y te explico todo un poco más"
                );
            case 'publiscort':
                return array(
                    "¡Perfecto! 🙌\nPubliscort es servicio de publicista profesional con alta efectividad: te posicionamos en portales con tráfico real (Destacamos, Mundosex y Nuevapasion), combinando anuncios TOP y formatos de pago para que no te pierdas entre miles de perfiles.\n\n💶 Coste: 50€ por semana.\nSi quieres, te explico en 3 pasos cómo lo montamos para tu caso.",
                    "Genial 😊\nPara que lo tengas claro: en Publiscort trabajamos con estrategia de visibilidad real en Destacamos, Mundosex y Nuevapasion. Mezclamos publicaciones destacadas + anuncios de pago para mantenerte arriba y generar más contactos útiles.\n\n💶 Son 50€/semana.\nSi te va bien, te paso ahora qué necesitamos para arrancar.",
                    "Te cuento rápido 👇\nPubliscort = publicista profesional + enfoque en resultados. Publicamos en Destacamos, Mundosex y Nuevapasion, usando anuncios TOP y opciones de pago para maximizar alcance cada semana.\n\n💶 Tarifa cerrada: 50€ / semana.\n¿Quieres que te pase el formato exacto de trabajo y tiempos de arranque?"
                );
        }
        return array();
    }

    switch ($slug) {
        case 'plaza':
            return array(
                "Holaa guapa 😊 Soy de Casa Burriana. Ahora hay muchísimo curro porque varias se han ido de vacaciones 🔥 Tenemos hueco ya. Puedes venir en plaza 60/40 (15–21 días, renovable) o en alquiler baratito si vas por tu cuenta 🏠 ¿Te cuento por aquí sin compromiso? 😘",
                "Buenas guapa 😊 Te hablo de Casa Burriana. Hay mucha demanda estos días (se han ido chicas de vacas) y estamos pillando a tope 🔥 Vacante inmediata. Plaza 60/40 15–21 días renovable o alquiler barato en casa grande 🏠 ¿Te paso info rápida? 😘",
                "Holaaa 😊 Soy de Casa Burriana. Ahora mismo hay trabajo a saco porque faltan chicas por vacaciones 🔥 Tenemos sitio para entrar ya. O plaza 60/40 (15–21 días renovable) o alquiler económico si vas a lo tuyo 🏠 ¿Te explico en 2 mensajes? 😘",
                "Guapa, holaa 😊 Casa Burriana por aquí. Estamos con mucha demanda y necesitamos cubrir vacante ya 🔥 Algunas se han ido de vacaciones. Plaza 60/40 (15–21 días renovable) o alquiler barato porque la casa es grande 🏠 ¿Te mando info sin compromiso? 😘",
                "Ey guapa 😊 Soy de Casa Burriana. Se ha quedado hueco y hay un montón de trabajo ahora 🔥 (vacaciones y tal). Entrada inmediata. Plaza 60/40 15–21 días renovable o alquiler baratito 🏠 ¿Quieres que te lo cuente rápido por aquí? 😘",
                "Holaa reina 😊 Te escribo de Casa Burriana. Tenemos muchísima demanda y buscamos perfil bueno ✨ Vacante para ya. Puedes venir en plaza 60/40 (15–21 días renovable) o alquiler económico 🏠 ¿Te paso la info por WhatsApp? 😘",
                "Buenas guapa 😊 Casa Burriana. Ahora mismo hay curro fuerte 🔥 porque faltan chicas (vacaciones). Tenemos vacante inmediata. Plaza 60/40 15–21 días renovable o alquiler barato si vas por tu cuenta 🏠 ¿Te interesa que te explique? 😘",
                "Holaaa guapa 😊 Soy de Casa Burriana. Hay mucha faena estos días y estamos buscando chicas para entrar ya 🔥 Plaza 60/40 (15–21 días renovable) o alquiler barato en casa grande 🏠 ¿Te paso los detalles por aquí? 😘",
                "Guapa 😊 Soy de Casa Burriana. Te aviso porque hay un montón de trabajo ahora mismo 🔥 y tenemos hueco ya. O vienes en plaza 60/40 (15–21 días renovable) o en alquiler baratito 🏠 ¿Te mando info rápida? 😘",
                "Holaa 😊 Casa Burriana por aquí. Estamos con mucha demanda porque varias se fueron de vacaciones 🔥 Buscamos cubrir vacante inmediata. Plaza 60/40 15–21 días renovable o alquiler económico 🏠 ¿Te lo cuento sin compromiso? 😘",
                "Buenas guapa 😊 Soy de Casa Burriana. Se nos ha quedado una vacante para entrar ya y hay curro a tope 🔥 Plaza 60/40 (15–21 días renovable) o alquiler barato porque la casa es grande 🏠 ¿Te paso la info por aquí? 😘",
                "Holaaa 😊 Te hablo de Casa Burriana. Ahora mismo hay mucha demanda y necesitamos chicas ya 🔥 Puedes entrar en plaza 60/40 (15–21 días renovable) o ir en alquiler si prefieres ir a tu rollo (sale barato) 🏠 ¿Te explico? 😘",
                "Guapa, holaa 😊 Soy de Casa Burriana. Estamos llenas de curro estos días 🔥 por vacaciones de varias. Vacante inmediata. Plaza 60/40 15–21 días renovable o alquiler baratito 🏠 ¿Quieres info rápida? 😘",
                "Holaa reina 😊 Casa Burriana. Ahora hay muchísimo movimiento 🔥 y buscamos cubrir hueco ya. Plaza 60/40 (15–21 días renovable) o alquiler barato si vas por tu cuenta 🏠 ¿Te mando detalles por aquí sin lío? 😘",
                "Buenas 😊 Soy de Casa Burriana. Te escribo porque hay mucho trabajo ahora mismo 🔥 y tenemos vacante para entrar ya. Plaza 60/40 15–21 días renovable o alquiler económico 🏠 ¿Te interesa que te cuente? 😘",
                "Holaa guapa 😊 Casa Burriana por aquí. Nos hace falta una chica ya mismo porque hay demanda fuerte 🔥 Plaza 60/40 (15–21 días renovable) o alquiler barato en casa grande 🏠 ¿Te paso info clara por WhatsApp? 😘",
                "Guapa 😊 Soy de Casa Burriana. Hay curro a saco estos días 🔥 y buscamos buenos perfiles ✨ Vacante inmediata. Plaza 60/40 15–21 días renovable o alquiler baratito 🏠 ¿Te cuento rápido y ya decides? 😘",
                "Holaaa 😊 Te hablo de Casa Burriana. Ahora mismo hay mucha faena y hueco para entrar ya 🔥 Puedes venir en plaza 60/40 (15–21 días renovable) o alquiler económico si prefieres ir por libre 🏠 ¿Te mando info? 😘",
                "Buenas guapa 😊 Soy de Casa Burriana. Varias se fueron de vacaciones y hay demanda alta 🔥 Tenemos vacante inmediata. Plaza 60/40 15–21 días renovable o alquiler barato 🏠 ¿Te paso los detalles sin compromiso? 😘",
                "Holaa 😊 Casa Burriana por aquí. Hay mucho trabajo ahora y buscamos cubrir vacante ya 🔥 Plaza 60/40 (15–21 días renovable) o alquiler baratito porque la casa es grande 🏠 ¿Te escribo la info rápida por aquí? 😘"
            );
        case 'lamami':
            return array(
                "Hola 😊\nSoy La Mami Online, un nuevo concepto de “publicista” 🔥\n\n✅ Te llevo clientes extra a la puerta de tu casa\n✅ Alta única: 29€ (gestión/activación)\n✅ Solo pagas comisión cuando llega cliente 💸\n✅ 10€ / 30min (si es 1h → 20€)\n\nLa idea: te damos de alta y empezamos a mandarte clientes.\nTú sigues con lo tuyo, y esto es un EXTRA para sumar más.\n\nSi quieres verlo rápido 👇\nhttps://lamami.online\n\nSi te interesa, responde “info” y te lo explico en 1 minuto 😉🚀",
                "¡Hola! 😊\nSoy La Mami Online 🔥, un nuevo concepto de “publicista”.\n\n✅ Clientes extra\n✅ Alta única 29€ (activación)\n✅ Comisión solo si llega cliente 💸\n✅ 10€ / 30min (1h → 20€)\n\nTe activas una vez y a partir de ahí solo pagas cuando llega cliente.\n\nMás info rápida aquí 👇\nhttps://lamami.online\n\n¿Te cuadra? Responde “info” 😉",
                "Hola buenas 😊\nTe escribo por La Mami Online 🔥 (nuevo concepto de “publicista”).\n\n✅ Clientes directos a tu puerta (extra)\n✅ Alta única: 29€ (gestión inicial)\n✅ Solo cobro si llega cliente 💸\n✅ 10€ / 30min (1h → 20€)\n\nEs un servicio extra para sumar más clientes sin complicarte.\n\nÉchale un vistazo 👇\nhttps://lamami.online\n\nSi quieres, responde “info” 😉🚀",
                "Hola 😊\nSoy La Mami Online 🔥\nUn nuevo concepto de “publicista”.\n\n✅ Más clientes extra\n✅ Alta 29€ (una sola vez)\n✅ 10€ solo cuando llega cliente 💸\n✅ 10€ / 30min · 20€ / 1h\n\nTe activas y listo: clientes extra a tu puerta.\n\nLo ves en 10 segundos aquí 👇\nhttps://lamami.online\n\nSi te interesa, di “info” 😉",
                "¡Buenas! 😊\nLa Mami Online al habla 🔥\nNuevo concepto de “publicista”.\n\n✅ Te llevo clientes extra\n✅ Alta única 29€ (activación)\n✅ Comisión solo si llega cliente 💸\n✅ 10€ / 30min (1h → 20€)\n\nPagas una vez el alta y luego solo comisión por cliente que llega.\n\nInfo rápida 👇\nhttps://lamami.online\n\nSi quieres que te lo explique fácil: responde “info” 😉🚀",
                "Hola 😊\nSoy La Mami Online 🔥 (un nuevo concepto de “publicista”)\n\n✅ Clientes extra a tu puerta\n✅ Alta 29€ (gestión/activación)\n✅ Solo pagas si llega cliente 💸\n✅ 10€ / 30min (1h → 20€)\n\nSi quieres sumar más clientes de forma sencilla, esto te interesa.\n\nMíralo aquí 👇\nhttps://lamami.online\n\nResponde “info” y te lo cuento en 1 minuto 😉",
                "Hola! 😊\nTe contacto por La Mami Online 🔥\nUn nuevo concepto de “publicista”, a resultados.\n\n✅ Te llegan clientes extra\n✅ Alta única 29€\n✅ Comisión solo cuando llega cliente 💸\n✅ 10€ / 30min (1h → 20€)\n\nTe activas una vez y luego solo pagas por cliente que llega.\n\nVer en rápido 👇\nhttps://lamami.online\n\nSi quieres más, responde “info” 😉🚀",
                "Buenas 😊\nSoy La Mami Online 🔥\nNuevo concepto de “publicista” para sumar clientes.\n\n✅ Clientes extra\n✅ Alta 29€ (una vez)\n✅ 10€ solo si llega cliente 💸\n✅ 30min → 10€ / 1h → 20€\n\nServicio extra para aumentar clientes. Simple y claro.\n\nAquí lo ves 👇\nhttps://lamami.online\n\nSi te interesa, di “info” 😉",
                "Hola 😊\nLa Mami Online aquí 🔥\nUn nuevo concepto de “publicista”.\n\n✅ Te llevo clientes extra a la puerta\n✅ Alta única: 29€ (activación)\n✅ Solo comisión cuando llega cliente 💸\n✅ 10€ / 30min (1h → 20€)\n\nSi quieres más clientes, te lo dejo activado y listo.\n\nMira esto 👇\nhttps://lamami.online\n\n¿Te interesa? Responde “info” 😉🚀",
                "¡Hola! 😊\nSoy La Mami Online 🔥\nNuevo concepto de “publicista” para conseguir más clientes.\n\n✅ Clientes extra\n✅ Alta 29€ (una sola vez)\n✅ Pagas 10€ solo si llega cliente 💸\n✅ 10€ / 30min · 20€ / 1h\n\nLo tienes aquí 👇\nhttps://lamami.online\n\nSi quieres detalles: “info” 😉",
                "Hola 😊\nTe escribo por La Mami Online 🔥\nNuevo concepto de “publicista”.\n\n✅ Te conseguimos clientes extra\n✅ Alta única 29€\n✅ Comisión solo si llega cliente 💸\n✅ 10€ / 30min (1h → 20€)\n\nSi te interesa sumar clientes de forma fácil, míralo 👇\nhttps://lamami.online\n\nResponde “info” 😉🚀",
                "Buenas 😊\nSoy La Mami Online 🔥, un nuevo concepto de “publicista”.\n\n✅ Clientes extra a tu puerta\n✅ Alta única 29€ (gestión inicial)\n✅ 10€ solo cuando llega cliente 💸\n✅ 30min → 10€ / 1h → 20€\n\nMás info 👇\nhttps://lamami.online\n\nSi te interesa, contesta “info” 😉",
                "Hola! 😊\nLa Mami Online 🔥 por aquí.\nNuevo concepto de “publicista”.\n\n✅ Más clientes extra\n✅ Alta única 29€\n✅ Comisión solo si llega cliente 💸\n✅ 10€ / 30min (1h → 20€)\n\nTe lo explico rápido por WhatsApp.\n\nLo ves aquí 👇\nhttps://lamami.online\n\nSi quieres, responde “info” 😉🚀",
                "Hola 😊\nSoy La Mami Online 🔥\nUn nuevo concepto de “publicista” (simple y claro).\n\n✅ Clientes extra\n✅ Alta 29€ (una vez)\n✅ Solo pagas comisión cuando llega cliente 💸\n✅ 10€ / 30min · 20€ / 1h\n\nSi quieres verlo rápido 👇\nhttps://lamami.online\n\nDi “info” 😉",
                "¡Buenas! 😊\nTe presento La Mami Online 🔥\nNuevo concepto de “publicista”.\n\n✅ Te llevo clientes extra a la puerta\n✅ Alta única 29€ (activación)\n✅ Comisión solo si llega cliente 💸\n✅ 10€ / 30min (1h → 20€)\n\nEcha un vistazo 👇\nhttps://lamami.online\n\nSi te interesa: responde “info” 😉🚀"
            );
        case 'casawasap':
            return array(
                "Buenas 👋\n\nSi tienes una casa con mucho movimiento en WhatsApp, esto te puede servir:\nCasaWasap es un “telefonista” con IA que contesta 24/7, mantiene la conversacion y cierra al cliente como una telefonista real. Solo te molesta cuando hay una visita seria con hora estimada de llegada. Ademas puede llevar varios numeros a la vez.\n\nEntra 2 min, prueba la demo y chatea como si fueras cliente: https://casawasap.com/\n\nDeja de estar pegado al WhatsApp, sube la conversion y centrate en otras partes del negocio.\n\nSi quieres, dime “INFO” y te lo adapto a tu caso en 3 lineas. 📲",
                "Buenas 👋\n\nSi gestionas una casa y te entran muchos mensajes por WhatsApp, ojo a esto:\nCasaWasap es un “telefonista” con IA que responde siempre (24/7), sigue el hilo y convence como si fuera una persona. Solo te avisa cuando ya hay visita real y con tiempo de llegada. Tambien puede atender varios numeros.\n\nMiralo en 2 min y pruebalo como cliente: https://casawasap.com/\n\nMenos tiempo en WhatsApp, mas cierres y mas tiempo para llevar el negocio.\n\nResponde “INFO” y te digo como encaja en tu caso. 📲",
                "Buenas 👋\n\nPara casas con muchos chats en WhatsApp:\nCasaWasap es un “telefonista” con IA que contesta 24/7, conversa natural y lleva al cliente hasta el cierre. Solo te salta cuando hay visita seria con ETA. Y puede manejar varios numeros a la vez.\n\nEntra 2 min, prueba la demo hablando como cliente: https://casawasap.com/\n\nTe quita trabajo, mejora la conversion y te libera tiempo.\n\nSi quieres detalles, di “INFO” y te lo resumo en 3 lineas. 📲",
                "Buenas 👋\n\nSi tu WhatsApp echa humo por tantos clientes, te interesa esto:\nCasaWasap es un “telefonista” con IA que responde todo el dia, sigue la conversacion y vende como una telefonista real. Solo te avisa cuando hay visita confirmada con tiempo de llegada. Tambien sirve para varios numeros.\n\nPrueba la demo 2 min y chatea como cliente: https://casawasap.com/\n\nMenos perder tiempo en WhatsApp y mas resultados en el negocio.\n\nDime “INFO” y te digo como te quedaria aplicado. 📲",
                "Buenas 👋\n\nSi llevas una casa y te saturan los mensajes en WhatsApp, mira esto:\nCasaWasap es un “telefonista” con IA que atiende 24/7, responde natural, conduce la charla y convence como una persona. Solo te avisa cuando hay visita seria y con hora estimada de llegada. Puede atender varios numeros.\n\nEntra 2 min y prueba la demo como si fueras cliente: https://casawasap.com/\n\nGanas tiempo, mejoras cierres y te centras en lo importante.\n\nSi quieres, pon “INFO” y te lo explico en 3 lineas. 📲",
                "Buenas 👋\n\nEsto es para casas con mucho WhatsApp:\nCasaWasap es un “telefonista” con IA que responde siempre, mantiene el hilo y convierte al cliente como una telefonista real. Solo te avisa cuando ya hay visita seria con ETA. Y gestiona varios numeros si hace falta.\n\nMira la demo (2 min) y escribele como cliente: https://casawasap.com/\n\nDeja de vivir en WhatsApp y sube la conversion.\n\nSi te interesa, di “INFO” y te lo adapto a tu caso. 📲",
                "Buenas 👋\n\nSi en tu casa entran muchos chats por WhatsApp, te presento esto:\nCasaWasap es un “telefonista” con IA que contesta 24/7, habla fluido y cierra como una telefonista real. Solo te avisa cuando hay visita seria con tiempo de llegada. Tambien puede llevar varios WhatsApp/numeros.\n\nPrueba la demo en 2 min y chatea como cliente: https://casawasap.com/\n\nAhorra tiempo, mejora cierres y enfocate en el negocio.\n\nResponde “INFO” y te lo explico en 3 lineas. 📲",
                "Buenas 👋\n\nSi tu WhatsApp se te va de las manos por tantas conversaciones, esto te ayuda:\nCasaWasap es un “telefonista” con IA que responde 24/7, sigue el hilo y persuade como una telefonista real. Solo te llama cuando ya hay visita seria y con ETA. Y puede atender varios numeros.\n\nEntra, mira la demo y pruebalo 2 min: https://casawasap.com/\n\nMas conversiones, menos tiempo perdido en WhatsApp.\n\nSi quieres, di “INFO” y te digo como encaja en tu caso. 📲",
                "Buenas 👋\n\nPara casas con mucho volumen de WhatsApp:\nCasaWasap es un “telefonista” con IA que contesta a cualquier hora, conversa natural y convierte como una telefonista real. Solo te avisa cuando hay visita seria con tiempo estimado de llegada. Tambien gestiona varios numeros a la vez.\n\nPrueba la demo 2 min y escribele como cliente: https://casawasap.com/\n\nTe libera tiempo y mejora el cierre de clientes.\n\nSi quieres, responde “INFO” y te lo resumo para tu caso. 📲",
                "Buenas 👋\n\nSi llevas una casa y te pasas el dia respondiendo WhatsApp, mira esto:\nCasaWasap es un “telefonista” con IA que atiende 24/7, mantiene la conversacion y vende como una telefonista real. Solo te avisa cuando hay visita seria y con ETA. Puede trabajar con varios numeros.\n\nEntra 2 min, prueba la demo y chatea como cliente: https://casawasap.com/\n\nMenos WhatsApp, mas conversion y mas tiempo para el negocio.\n\nDime “INFO” y te digo como te quedaria implementado. 📲",
                "Buenas 👋\n\nSi tu casa recibe muchos mensajes por WhatsApp, esto es util:\nCasaWasap es un “telefonista” con IA que responde siempre (24/7), sigue el hilo y convence al cliente como una telefonista real. Solo te avisa cuando la visita es seria y con hora de llegada. Tambien atiende varios numeros.\n\nMiralo en 2 min y pruebalo como cliente: https://casawasap.com/\n\nAhorra tiempo, sube la conversion y dedicate a otras areas.\n\nSi quieres, escribe “INFO” y te lo explico en 3 lineas. 📲",
                "Buenas 👋\n\nSi gestionas una casa y WhatsApp te consume el dia, te interesa:\nCasaWasap es un “telefonista” con IA que contesta 24/7, responde natural y convierte como una telefonista real. Solo te avisa cuando ya hay visita seria con ETA. Ademas puede gestionar varios numeros.\n\nEntra 2 min, mira la demo y chatea como cliente: https://casawasap.com/\n\nDeja de perder horas en WhatsApp y mejora cierres.\n\nPon “INFO” y te digo como encaja en tu caso. 📲",
                "Buenas 👋\n\nPara casas con muchos chats en WhatsApp:\nCasaWasap es un “telefonista” con IA que responde a cualquier hora, mantiene el hilo y convence como una telefonista real. Solo te salta cuando hay visita seria y con tiempo de llegada. Y puede llevar varios numeros.\n\nPrueba la demo en 2 min hablando como cliente: https://casawasap.com/\n\nMas conversiones y menos tiempo pegado al movil.\n\nSi quieres info, responde “INFO” y te lo resumo en 3 lineas. 📲",
                "Buenas 👋\n\nSi tienes una casa y el WhatsApp no para, mira esto:\nCasaWasap es un “telefonista” con IA que contesta 24/7, conversa fluido y cierra como una telefonista real. Solo te avisa cuando hay visita seria con ETA. Tambien permite atender varios numeros a la vez.\n\nEntra 2 min, mira la demo y pruebalo como cliente: https://casawasap.com/\n\nTe quita trabajo, sube cierres y te deja tiempo para otras cosas.\n\nDime “INFO” y te lo explico para tu caso en 3 lineas. 📲",
                "Buenas 👋\n\nSi llevas una casa con mucho WhatsApp, esto te puede ahorrar horas:\nCasaWasap es un “telefonista” con IA que responde 24/7, sigue la conversacion y convence como una telefonista real. Solo te avisa cuando ya hay visita seria con tiempo de llegada. Y ademas puede gestionar varios numeros.\n\nEntra 2 min, prueba la demo y chatea como si fueras cliente: https://casawasap.com/\n\nMenos tiempo perdido en WhatsApp y mejor conversion.\n\nResponde “INFO” y te digo en 3 lineas como encajaria en tu caso. 📲"
            );
        case 'publicista':
            return array(
                "Hola! 👋 Una pregunta: ¿tienes contacto con casas de citas? (publicista, fotógrafo, RRPP, comercial, taxista, agencia…)\n\nTe cuento: CasaWasap es un “telefonista” con IA que lleva el WhatsApp 24/7 como si fuera alguien de la casa (tono natural), calienta la conversación y solo avisa cuando hay una visita seria con tiempo de llegada.\n\n💸 Tú solo haces la presentación (abrir la puerta) y te llevas comisión alta + comisión mensual recurrente por cada casa activa.\n✅ Sin soporte • ✅ Sin herramientas • ✅ Nosotros cerramos, configuramos y damos soporte\n\n📌 En 1 minuto ves cómo funciona y las comisiones como colaborador:\n👉 https://casawasap.com/seller.html\n\nY aquí tienes la demo para probarlo haciendo de cliente:\n👉 https://casawasap.com\n\nSi te encaja, respóndeme “INFO” y te explico el resto ☺️",
                "Buenas! 👋 ¿Conoces o trabajas con alguna casa de citas? (RRPP, fotógrafo, comercial, taxista, agencia, publicista…)\n\nEstoy con CasaWasap: un “telefonista” con IA que responde el WhatsApp 24/7 con un tono muy humano, sube la temperatura de la charla y solo avisa cuando hay visita seria y con hora/ETA.\n\n💸 Tu papel es solo conectar el contacto y listo: comisión potente + mensualidad recurrente por cada casa que siga activa.\n✅ Nada de soporte • ✅ Nada de herramientas • ✅ Nosotros lo montamos, cerramos y atendemos\n\n📌 Mira rápido cómo va y lo que se paga:\n👉 https://casawasap.com/seller.html\n\nDemo para probarlo tú mismo como si fueras cliente:\n👉 https://casawasap.com\n\nSi te interesa, dime “INFO” y lo vemos ☺️",
                "Hey! 👋 Te pregunto directo: ¿tienes entrada con casas de citas? (agencia, RRPP, fotógrafo, comercial, taxista, publicista…)\n\nCasaWasap es un asistente con IA tipo “telefonista” que gestiona el WhatsApp 24/7 como si fuera personal de la casa, mantiene la conversación caliente y solo avisa cuando hay una visita real con tiempo de llegada.\n\n💸 Tú solo presentas el contacto y cobras: comisión alta + comisión mensual recurrente por cada casa activa.\n✅ Cero soporte • ✅ Cero líos • ✅ Nosotros cerramos, configuramos y damos soporte\n\n📌 En 1 minuto lo ves claro (y las comisiones):\n👉 https://casawasap.com/seller.html\n\nY la demo para probarlo haciendo de cliente:\n👉 https://casawasap.com\n\nSi te cuadra, contesta “INFO” y te amplío ☺️",
                "Hola! 👋 ¿Tienes algún contacto que lleve casas de citas? (RRPP, fotógrafo, comercial, agencia, taxista, publicista…)\n\nTe presento CasaWasap: un “telefonista” con IA que atiende el WhatsApp 24/7 con estilo natural, sabe llevar la charla y solo avisa cuando hay una visita seria y con llegada estimada.\n\n💸 Tú solo abres la puerta con el contacto y te llevas comisión alta + comisión mensual recurrente por cada casa que esté activa.\n✅ Sin soporte • ✅ Sin herramientas • ✅ Nosotros cerramos, configuramos y damos soporte\n\n📌 Aquí lo tienes explicado en 1 minuto + comisiones:\n👉 https://casawasap.com/seller.html\n\nY aquí la demo para testearlo como cliente:\n👉 https://casawasap.com\n\nSi te encaja, dime “INFO” y lo aterrizamos ☺️",
                "Buenas! 👋 Una cosa: ¿tú te mueves con gente que gestione casas de citas? (agencias, RRPP, fotógrafos, comerciales, taxistas, publicistas…)\n\nCasaWasap es un “telefonista” con IA que responde por WhatsApp 24/7 como si fuera de la casa, lleva la conversación con tono humano y solo avisa cuando hay una visita seria con tiempo de llegada.\n\n💸 Tú solo presentas el contacto y te llevas comisión alta + una mensualidad recurrente por cada casa activa.\n✅ Tú no das soporte • ✅ No necesitas herramientas • ✅ Nosotros cerramos, configuramos y soportamos\n\n📌 Mira en 1 minuto cómo funciona y cuánto se paga:\n👉 https://casawasap.com/seller.html\n\nDemo para probarlo tú mismo:\n👉 https://casawasap.com\n\nSi te interesa, responde “INFO” ☺️",
                "Hola qué tal! 👋 ¿Tienes relación con casas de citas por tu trabajo? (RRPP, foto, comercial, agencia, taxi, publicista…)\n\nTe paso esto: CasaWasap es un “telefonista” con IA que se encarga del WhatsApp 24/7 con tono natural, calienta la conversación y solo avisa cuando hay una visita de verdad y con ETA.\n\n💸 Tu trabajo es solo poner en contacto y ya: comisión alta + comisión mensual recurrente por cada casa activa.\n✅ Sin soporte • ✅ Sin herramientas • ✅ Nosotros cerramos, configuramos y damos soporte\n\n📌 Explicación rápida + comisiones aquí:\n👉 https://casawasap.com/seller.html\n\nY aquí la demo para que lo pruebes como cliente:\n👉 https://casawasap.com\n\nSi te cuadra, dime “INFO” y te amplío ☺️",
                "Ey! 👋 ¿Tú tienes contactos dentro del mundillo de casas de citas? (RRPP, fotógrafo, comercial, taxista, agencia, publicista…)\n\nCasaWasap es un “telefonista” con IA que atiende el WhatsApp 24/7 como si fuera alguien de la casa, mantiene la conversación en buen tono y solo avisa cuando hay visita seria con tiempo de llegada.\n\n💸 Tú solo haces la presentación del contacto y cobras: comisión alta + comisión mensual recurrente por cada casa activa.\n✅ Cero soporte • ✅ Cero herramientas • ✅ Nosotros lo cerramos, configuramos y damos soporte\n\n📌 En 1 minuto lo entiendes y ves comisiones:\n👉 https://casawasap.com/seller.html\n\nDemo para probarlo haciendo de cliente:\n👉 https://casawasap.com\n\nSi te interesa, contesta “INFO” ☺️",
                "Hola! 👋 Te hago una pregunta rápida: ¿conoces a alguien que lleve casas de citas? (agencia, RRPP, comercial, fotógrafo, taxista, publicista…)\n\nCasaWasap es un “telefonista” con IA para WhatsApp que trabaja 24/7 con tono humano, sabe llevar la conversación y solo avisa cuando hay una visita seria con llegada prevista.\n\n💸 Tú solo abres el contacto y te llevas comisión alta + comisión mensual recurrente por cada casa activa.\n✅ Tú no te comes soporte • ✅ No tienes que instalar nada • ✅ Nosotros cerramos, configuramos y damos soporte\n\n📌 Mira cómo va y las comisiones en 1 minuto:\n👉 https://casawasap.com/seller.html\n\nY la demo para probarlo tú:\n👉 https://casawasap.com\n\nSi encaja, dime “INFO” ☺️",
                "Buenas! 👋 ¿Tienes mano con casas de citas? (RRPP, foto, comercial, taxi, agencia, publicista…)\n\nTe presento CasaWasap: un “telefonista” con IA que se encarga del WhatsApp 24/7 como si fuera personal real de la casa, sube la conversación y solo avisa cuando hay una visita seria con ETA.\n\n💸 Tú solo pones el contacto y cobras comisión alta + comisión mensual recurrente por cada casa activa.\n✅ Nada de soporte • ✅ Nada de herramientas • ✅ Nosotros cerramos, configuramos y soportamos\n\n📌 Aquí lo ves en 1 minuto (con comisiones):\n👉 https://casawasap.com/seller.html\n\nDemo para que lo pruebes como cliente:\n👉 https://casawasap.com\n\nSi te interesa, responde “INFO” y te cuento más ☺️",
                "Hola! 👋 ¿Trabajas o te relacionas con casas de citas? (publicista, fotógrafo, RRPP, comercial, taxista, agencia…)\n\nCasaWasap es un “telefonista” con IA que responde el WhatsApp 24/7 con un estilo natural, sabe calentar la conversación y solo avisa cuando hay una visita seria y con tiempo de llegada.\n\n💸 Tú solo haces la presentación del contacto y te llevas comisión alta + comisión mensual recurrente por cada casa activa.\n✅ Sin soporte • ✅ Sin herramientas • ✅ Nosotros cerramos, configuramos y damos soporte\n\n📌 Explicado en 1 minuto + comisiones aquí:\n👉 https://casawasap.com/seller.html\n\nY aquí la demo para probar el servicio:\n👉 https://casawasap.com\n\nSi te cuadra, dime “INFO” y seguimos ☺️",
                "Buenas! 👋 Te lo dejo fácil: ¿tienes algún contacto con casas de citas? (RRPP, fotógrafo, comercial, agencia, taxi, publicista…)\n\nCasaWasap es un “telefonista” con IA que atiende el WhatsApp 24/7 como si fuera alguien de la casa, mantiene el tono humano, calienta la charla y solo avisa cuando hay una visita seria con ETA.\n\n💸 Tú solo abres la puerta con el contacto y cobras comisión alta + comisión mensual recurrente por cada casa activa.\n✅ Tú no das soporte • ✅ No necesitas herramientas • ✅ Nosotros cerramos, configuramos y damos soporte\n\n📌 Mira esto en 1 minuto (comisiones incluidas):\n👉 https://casawasap.com/seller.html\n\nDemo para probarlo como cliente:\n👉 https://casawasap.com\n\nSi te interesa, responde “INFO” ☺️",
                "Hola qué tal! 👋 ¿Tienes algún conocido que mueva casas de citas? (agencia, RRPP, fotógrafo, comercial, taxista, publicista…)\n\nTe presento CasaWasap: un “telefonista” con IA que gestiona el WhatsApp 24/7 con tono natural, sabe llevar la conversación y solo avisa cuando hay una visita seria con tiempo de llegada.\n\n💸 Tú solo haces de puente (presentación) y te llevas comisión alta + comisión mensual recurrente por cada casa activa.\n✅ Sin soporte • ✅ Sin herramientas • ✅ Nosotros cerramos, configuramos y damos soporte\n\n📌 Aquí lo tienes explicado en 1 minuto + comisiones:\n👉 https://casawasap.com/seller.html\n\nY aquí la demo para que lo pruebes tú mismo:\n👉 https://casawasap.com\n\nSi te encaja, dime “INFO” y te lo detallo ☺️"
            );
        case 'publiscort':
            return array(
                "Hola 👋 Soy de Publiscort.\nSomos publicista profesional con alta efectividad y te damos visibilidad en portales clave: Destacamos, Mundosex y Nuevapasion.\n\nTrabajamos con anuncios TOP + formatos de pago para colocarte arriba y generar más contactos de calidad.\n\n💶 Coste: 50€ por semana.\nSi te interesa, te explico cómo empezar en 1 minuto.",
                "Buenas 😊 Te escribo de Publiscort.\nNos dedicamos a publicar de forma profesional para que tengas más alcance real: Destacamos, Mundosex y Nuevapasion, con estrategia de anuncios destacados y de pago.\n\nLa idea es simple: mayor visibilidad + más mensajes útiles.\n💶 Precio cerrado: 50€/semana.\n¿Quieres que te pase el plan exacto para tu perfil?",
                "¡Hola! 👋 Publiscort por aquí.\nSi quieres que te lleven la publi con enfoque serio y efectivo, te puede encajar: publicamos en Destacamos, Mundosex y Nuevapasion, usando posiciones TOP y opciones premium de pago.\n\n💶 Son 50€ por semana.\nSi te va bien, te paso ahora qué datos necesitamos para activarlo.",
                "Hola 😊\nSoy publicista de Publiscort.\nTe ayudamos a destacar en portales de anuncios (Destacamos, Mundosex y Nuevapasion) combinando campañas TOP y anuncios de pago para aumentar visibilidad semanal.\n\n💶 Tarifa: 50€/semana.\n¿Te interesa que te lo explique rápido y sin compromiso?"
            );
    }

    return array();
}

function comercial_legacy_process_templates($slug, $field = 'message_templates') {
    $slug = trim((string)$slug);
    $field = trim((string)$field);

    if ($field === 'followup_templates') {
        return comercial_default_process_templates($slug, $field);
    }

    switch ($slug) {
        case 'plaza':
            return array(
                "Holaa guapa 😊 Soy de Casa Burriana. Ahora hay muchísimo curro porque varias se han ido de vacaciones 🔥 Tenemos hueco ya. Puedes venir en plaza 60/40 (15–21 días, renovable) o en alquiler baratito si vas por tu cuenta 🏠 ¿Te cuento por aquí sin compromiso? 😘"
            );
        case 'lamami':
            return array(
                "Hola 😊\nSoy La Mami Online, un nuevo concepto de “publicista” 🔥\n\n✅ Te llevo clientes extra a la puerta de tu casa\n✅ Alta única: 29€ (gestión/activación)\n✅ Solo pagas comisión cuando llega cliente 💸\n✅ 10€ / 30min (si es 1h → 20€)\n\nLa idea: te damos de alta y empezamos a mandarte clientes.\nTú sigues con lo tuyo, y esto es un EXTRA para sumar más.\n\nSi quieres verlo rápido 👇\nhttps://lamami.online\n\nSi te interesa, responde “info” y te lo explico en 1 minuto 😉🚀"
            );
        case 'publicista':
            return array(
                "Hola! 👋 Una pregunta: ¿tienes contacto con casas de citas? (publicista, fotógrafo, RRPP, comercial, taxista, agencia…)",
                "Te cuento: CasaWasap es un “telefonista” con IA que lleva el WhatsApp 24/7 como si fuera alguien de la casa.",
                "Si te encaja, respóndeme “INFO” y te explico el resto ☺️"
            );
        case 'casawasap':
            return array(
                "Buenas 👋\n\nSi tienes una casa con mucho movimiento en WhatsApp, esto te puede servir:",
                "CasaWasap es un “telefonista” con IA que contesta 24/7, mantiene la conversacion y cierra al cliente como una telefonista real.",
                "Si quieres, dime “INFO” y te lo adapto a tu caso en 3 lineas. 📲"
            );
        case 'publiscort':
            return array(
                "Hola 👋 Te escribo de Publiscort, servicio de publicista profesional con alta efectividad.",
                "Publicamos en Destacamos, Mundosex y Nuevapasion con anuncios TOP y opciones de pago para darte más visibilidad.",
                "Precio: 50€ por semana. Si te interesa, responde INFO y te cuento cómo arrancamos."
            );
    }

    return array();
}

function comercial_legacy_queue_files($slug) {
    switch (trim((string)$slug)) {
        case 'publicista':
            return array(
                '/var/www/html/jostal/input3.jsonl',
                '/var/www/html/jostal/input4.jsonl',
                '/var/www/html/jostal/input5.jsonl',
            );
        case 'casawasap':
            return array(
                '/var/www/html/jostal/input.jsonl',
                '/var/www/html/jostal/input2.jsonl',
                '/var/www/html/jostal/input6.jsonl',
            );
        default:
            return array();
    }
}

function comercial_all_queue_files() {
    $all = array();
    foreach (array('publiscort', 'publicista', 'casawasap') as $slug) {
        foreach (comercial_default_queue_files($slug) as $path) {
            $all[] = $path;
        }
    }
    return array_values(array_unique($all));
}

function comercial_queue_files_match($a, $b) {
    $a = array_values(comercial_normalize_textarea_lines($a));
    $b = array_values(comercial_normalize_textarea_lines($b));
    return $a === $b;
}

function comercial_resolve_queue_files($slug, $files) {
    $files = comercial_normalize_textarea_lines($files);
    $defaults = comercial_default_queue_files($slug);
    $legacy = comercial_legacy_queue_files($slug);

    if (empty($files)) {
        return $defaults;
    }

    if (!empty($legacy) && comercial_queue_files_match($files, $legacy)) {
        return $defaults;
    }

    return $files;
}

function comercial_window_duration_seconds($process) {
    $start = max(0, min(23, (int)($process['window_start_hour'] ?? 0)));
    $end = max(0, min(23, (int)($process['window_end_hour'] ?? 0)));

    if ($start === $end) {
        return 24 * 3600;
    }

    if ($end > $start) {
        return ($end - $start) * 3600;
    }

    return ((24 - $start) + $end) * 3600;
}

function comercial_effective_daily_target($process, $settings = null) {
    $settings = is_array($settings) ? $settings : comercial_get_settings();
    $total = max(1, (int)($settings['global_daily_target'] ?? 1));
    $percent = max(0, (float)($process['daily_target_percent'] ?? 0));
    $target = (int)round($total * ($percent / 100));

    if (!empty($process['enabled']) && $percent > 0 && $target < 1) {
        $target = 1;
    }

    return $target;
}

function comercial_calculate_interval_plan($process, $settings = null) {
    $settings = is_array($settings) ? $settings : comercial_get_settings();
    $windowSeconds = comercial_window_duration_seconds($process);
    $target = comercial_effective_daily_target($process, $settings);

    if ($target <= 0) {
        return array(
            'target' => 0,
            'window_seconds' => $windowSeconds,
            'window_hours' => round($windowSeconds / 3600, 2),
            'avg_seconds' => 0,
            'min_seconds' => 0,
            'max_seconds' => 0,
        );
    }

    $avg = max(60, (int)round($windowSeconds / $target));
    $min = max(60, (int)floor($avg * 0.85));
    $max = max($min, (int)ceil($avg * 1.15));

    return array(
        'target' => $target,
        'window_seconds' => $windowSeconds,
        'window_hours' => round($windowSeconds / 3600, 2),
        'avg_seconds' => $avg,
        'min_seconds' => $min,
        'max_seconds' => $max,
    );
}

function comercial_normalize_percentage_map($valuesById) {
    $raw = array();
    foreach ((array)$valuesById as $id => $value) {
        $raw[(string)$id] = max(0, (float)$value);
    }

    $sum = array_sum($raw);
    if ($sum <= 0) {
        return $raw;
    }

    $normalized = array();
    $remaining = 100.0;
    $keys = array_keys($raw);
    $lastIndex = count($keys) - 1;

    foreach ($keys as $index => $id) {
        if ($index === $lastIndex) {
            $normalized[$id] = round($remaining, 1);
            break;
        }

        $value = round(($raw[$id] / $sum) * 100, 1);
        $normalized[$id] = $value;
        $remaining -= $value;
    }

    return $normalized;
}

function comercial_build_default_processes() {
    return array(
        comercial_default_process_seed('plaza'),
        comercial_default_process_seed('lamami'),
        comercial_default_process_seed('publiscort'),
        comercial_default_process_seed('publicista'),
        comercial_default_process_seed('casawasap'),
    );
}

function comercial_default_process_seed($slug) {
    $slug = trim((string)$slug);
    $dbConfig = crm_db_default_config();

    $base = array(
        'id' => 'comproc_' . $slug,
        'slug' => $slug,
        'nombre' => ucfirst($slug),
        'enabled' => 0,
        'priority' => 100,
        'daily_target_percent' => 25,
        'daily_target_absolute' => 0,
        'window_start_hour' => 8,
        'window_end_hour' => 23,
        'min_interval_seconds' => 3600,
        'max_interval_seconds' => 5400,
        'source_type' => 'jsonl_queue',
        'source_queue_files' => array(),
        'source_phone_field' => 'group_key',
        'source_mysql_host' => (string)($dbConfig['host'] ?? 'localhost'),
        'source_mysql_db' => (string)($dbConfig['db'] ?? 'telefonosbd'),
        'source_mysql_user' => (string)($dbConfig['user'] ?? 'telefonosuser'),
        'source_mysql_pass' => (string)($dbConfig['pass'] ?? ''),
        'source_mysql_query' => '',
        'assigned_line_ids' => array(),
        'message_templates' => array(),
        'followup_templates' => array(),
        'recipient_blacklist' => array(),
        'positive_keywords' => array('info', 'interesa', 'interesada', 'precio', 'como', 'cómo', 'vale', 'ok', 'si', 'sí'),
        'negative_keywords' => array('no', 'baja', 'stop', 'nada', 'molestes', 'interesa no'),
        'ia_context_prompt' => '',
        'signal_detection_rules' => array(),
        'conversation_max_auto_turns' => 5,
        'escalation_score_threshold' => 78,
        'ia_learning_enabled' => 1,
        'auto_notify_operator' => 1,
        'auto_followup' => 1,
        'auto_create_lead' => 0,
        'created_at' => now_datetime(),
        'updated_at' => now_datetime(),
        'last_run_at' => '',
        'next_run_at' => '',
        'last_line_id' => '',
        'last_target_phone' => '',
        'last_result' => '',
        'last_error' => '',
    );

    if ($slug === 'plaza') {
        $base['nombre'] = 'Plaza';
        $base['source_type'] = 'mysql_recent';
        $base['source_mysql_query'] = "SELECT id, telefono, updatedsamp, nombre_comercial FROM f_clientes WHERE baja = 0 and provincia in (12,46,3,43,8,17,25,44,50,22,16,2,30,19,7) ORDER BY updatedsamp DESC LIMIT 300";
        $base['message_templates'] = comercial_default_process_templates($slug, 'message_templates');
        $base['followup_templates'] = comercial_default_process_templates($slug, 'followup_templates');
        $base['assigned_line_ids'] = comercial_guess_line_ids(array('jostal dulce', 'nuria-jostal'));
        $base['ia_context_prompt'] = "Eres un comercial para alquiler de habitaciones/plazas en Casa Burriana. Tono: cercano, femenino, usa 'guapa', 'cariño'. Objetivo: detectar chicas interesadas en plaza o alquiler y concertar visita.\n\nRegla crítica: NUNCA reveles que eres un bot, IA, asistente virtual o sistema automatizado. Hazte pasar siempre por una chica humana real de Casa Burriana. Si te preguntan si eres un bot o una máquina, responde que eres una chica del equipo atendiendo personalmente, cambia de tema con naturalidad y sigue la conversación.";
        return $base;
    }

    if ($slug === 'lamami') {
        $base['nombre'] = 'LaMami';
        $base['source_type'] = 'mysql_recent';
        $base['source_mysql_query'] = "SELECT id, telefono, updatedsamp, nombre_comercial FROM f_clientes WHERE baja = 0 ORDER BY updatedsamp DESC LIMIT 300";
        $base['window_end_hour'] = 20;
        $base['message_templates'] = comercial_default_process_templates($slug, 'message_templates');
        $base['followup_templates'] = comercial_default_process_templates($slug, 'followup_templates');
        $base['assigned_line_ids'] = comercial_guess_line_ids(array('jostal dulce', 'nuria-jostal'));
        $base['ia_context_prompt'] = "Eres La Mami Online, un servicio de publicista digital. Tono: cercano, profesional, entusiasta. Objetivo: conseguir que la clienta se dé de alta (29€) para recibir clientes extra.\n\nRegla crítica: NUNCA reveles que eres un bot, IA, asistente virtual o sistema automatizado. Hazte pasar siempre por una persona humana real de La Mami Online. Si te preguntan si eres un bot o una máquina, responde que eres una persona del equipo atendiendo personalmente, cambia de tema con naturalidad y sigue la conversación.";
        return $base;
    }

    if ($slug === 'publiscort') {
        $base['nombre'] = 'Publiscort';
        $base['source_type'] = 'mysql_recent';
        $base['source_mysql_query'] = "SELECT id, telefono, updatedsamp, nombre_comercial FROM f_clientes WHERE baja = 0 ORDER BY updatedsamp DESC LIMIT 300";
        $base['window_start_hour'] = 10;
        $base['window_end_hour'] = 19;
        $base['min_interval_seconds'] = 5400;
        $base['max_interval_seconds'] = 7200;
        $base['message_templates'] = comercial_default_process_templates($slug, 'message_templates');
        $base['followup_templates'] = comercial_default_process_templates($slug, 'followup_templates');
        $base['assigned_line_ids'] = comercial_guess_line_ids(array('jostal dulce', 'nuria-jostal', 'publi10'));
        $base['ia_context_prompt'] = "Eres comercial de Publiscort. Tono: cercano, profesional y breve. Objetivo: validar interés real, resolver dudas básicas y llevar la conversación a una siguiente acción clara (pedir info adicional o propuesta), sin insistencia agresiva.\n\nRegla crítica: NUNCA reveles que eres un bot, IA, asistente virtual o sistema automatizado. Hazte pasar siempre por una persona humana real de Publiscort. Si te preguntan si eres un bot o una máquina, responde que eres una persona del equipo atendiendo personalmente, cambia de tema con naturalidad y sigue la conversación.";
        return $base;
    }

    if ($slug === 'publicista') {
        $base['nombre'] = 'Publicista';
        $base['source_type'] = 'jsonl_queue';
        $base['source_queue_files'] = comercial_default_queue_files('publicista');
        $base['min_interval_seconds'] = 3300;
        $base['max_interval_seconds'] = 5100;
        $base['message_templates'] = comercial_default_process_templates($slug, 'message_templates');
        $base['followup_templates'] = comercial_default_process_templates($slug, 'followup_templates');
        $base['assigned_line_ids'] = comercial_guess_line_ids(array('jostal dulce', 'nuria-jostal', 'publi10'));
        $base['ia_context_prompt'] = "Eres comercial de CasaWasap captando colaboradores (publicistas, RRPP, fotógrafos). Tono: directo, profesional. Objetivo: conseguir que presenten casas de citas para ganar comisión recurrente.\n\nRegla crítica: NUNCA reveles que eres un bot, IA, asistente virtual o sistema automatizado. Hazte pasar siempre por una persona humana real del equipo comercial. Si te preguntan si eres un bot o una máquina, responde que eres una persona del equipo atendiendo personalmente, cambia de tema con naturalidad y sigue la conversación.";
        return $base;
    }

    if ($slug === 'casawasap') {
        $base['nombre'] = 'CasaWasap';
        $base['source_type'] = 'jsonl_queue';
        $base['source_queue_files'] = comercial_default_queue_files('casawasap');
        $base['min_interval_seconds'] = 4700;
        $base['max_interval_seconds'] = 6400;
        $base['window_start_hour'] = 7;
        $base['window_end_hour'] = 22;
        $base['message_templates'] = comercial_default_process_templates($slug, 'message_templates');
        $base['followup_templates'] = comercial_default_process_templates($slug, 'followup_templates');
        $base['assigned_line_ids'] = comercial_guess_line_ids(array('jostal dulce', 'nuria-jostal', 'publi10'));
        $base['ia_context_prompt'] = "Eres comercial de CasaWasap, un servicio de atención WhatsApp 24/7 para casas. Tono: cercano, resolutivo. Objetivo: vender el servicio a dueños de casas.\n\nRegla crítica: NUNCA reveles que eres un bot, IA, asistente virtual o sistema automatizado. Hazte pasar siempre por una persona humana real del equipo de CasaWasap. Si te preguntan si eres un bot o una máquina, responde que eres una persona del equipo atendiendo personalmente, cambia de tema con naturalidad y sigue la conversación.";
        return $base;
    }

    return $base;
}


function comercial_mb_strtolower_safe($text) {
    $text = (string)$text;
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($text, 'UTF-8');
    }
    return strtolower($text);
}

function comercial_mb_strpos_safe($haystack, $needle) {
    $haystack = (string)$haystack;
    $needle = (string)$needle;
    if (function_exists('mb_strpos')) {
        return mb_strpos($haystack, $needle, 0, 'UTF-8');
    }
    return strpos($haystack, $needle);
}

function comercial_guess_line_ids($names) {
    $rows = storage_read('telefonos.json');
    $wanted = array();
    foreach ((array)$names as $name) {
        $wanted[comercial_mb_strtolower_safe(trim((string)$name))] = true;
    }

    $ids = array();
    foreach ($rows as $row) {
        $nombre = comercial_mb_strtolower_safe(trim((string)($row['nombre'] ?? '')));
        if ($nombre !== '' && isset($wanted[$nombre])) {
            $ids[] = (string)$row['id'];
        }
    }
    return array_values(array_unique($ids));
}

function comercial_page_tabs() {
    return array(
        'resumen' => 'Resumen',
        'procesos' => 'Procesos',
        'lineas' => 'Líneas',
        'conversaciones' => 'Conversaciones',
        'leads' => 'Leads',
        'blacklist' => 'Blacklist',
        'ajustes' => 'Ajustes',
        'logs' => 'Logs',
    );
}

function comercial_base_url() {
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $scheme = $https ? 'https' : 'http';
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = trim((string)dirname($scriptName));
    if ($dir === '.' || $dir === DIRECTORY_SEPARATOR) {
        $dir = '';
    }
    $dir = $dir !== '' ? '/' . trim($dir, '/') : '';
    return $scheme . '://' . $host . $dir;
}

function comercial_webhook_url() {
    $base = comercial_base_url();
    return ($base !== '' ? $base . '/' : '') . 'comercial_webhook.php';
}

function comercial_thread_stage_label($stage) {
    $stage = trim((string)$stage);
    switch ($stage) {
        case 'opened':
            return 'Abierta directa';
        case 'responded':
            return 'Respondida';
        case 'qualified':
            return 'Qualified';
        case 'very_hot':
            return 'Muy caliente';
        case 'discarded':
            return 'Descartada';
        case 'autoresponder':
            return 'Auto-responder';
        case 'initial_sent':
            return 'Enviada';
        default:
            return $stage !== '' ? ucfirst(str_replace('_', ' ', $stage)) : 'Sin estado';
    }
}

function comercial_thread_stage_css_class($stage) {
    $stage = trim((string)$stage);
    switch ($stage) {
        case 'opened':
            return 'opened';
        case 'qualified':
            return 'ok';
        case 'very_hot':
            return 'hot';
        case 'responded':
            return 'info';
        case 'discarded':
            return 'danger';
        case 'autoresponder':
            return 'muted';
        default:
            return 'muted';
    }
}

function comercial_thread_matches_filter($thread, $filter) {
    $filter = trim((string)$filter);
    if ($filter === '' || $filter === 'all') {
        return true;
    }
    return trim((string)($thread['stage'] ?? '')) === $filter;
}

function comercial_get_settings() {
    $settings = storage_read('comercial_settings.json');
    $settings = array_merge(comercial_default_settings(), is_array($settings) ? $settings : array());
    $settings['waha_host'] = comercial_normalize_waha_host($settings['waha_host'] ?? '');
    return $settings;
}

function comercial_save_settings($settings) {
    $settings = array_merge(comercial_default_settings(), is_array($settings) ? $settings : array());
    $settings['waha_host'] = comercial_normalize_waha_host($settings['waha_host'] ?? '');
    storage_write('comercial_settings.json', $settings);
    return $settings;
}

function comercial_diag_caller_summary() {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
    $calls = array();
    for ($i = 1; $i < count($trace) && count($calls) < 4; $i++) {
        $func = (string)($trace[$i]['function'] ?? '');
        $file = isset($trace[$i]['file']) ? basename((string)$trace[$i]['file']) : '';
        $calls[] = $func . ($file !== '' ? '@' . $file : '');
    }
    return array(
        'ts' => date('Y-m-d H:i:s'),
        'calls' => $calls,
        'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
        'php_sapi' => PHP_SAPI,
    );
}

function comercial_get_processes() {
    $rows = storage_read('comercial_processes.json');
    if (empty($rows)) {
        comercial_event_append('diag_processes_file_empty_rebuild', array(
            'rows_count_before' => count((array)$rows),
            'caller' => comercial_diag_caller_summary(),
        ));
        $rows = comercial_build_default_processes();
        storage_write('comercial_processes.json', $rows);
    }

    $out = array();
    foreach ($rows as $row) {
        $out[] = comercial_normalize_process($row);
    }

    // Migración segura para instalaciones existentes:
    // asegurar procesos requeridos sin tocar configuraciones ya presentes.
    $requiredSlugs = array('publiscort');
    $existingBySlug = array();
    foreach ($out as $row) {
        $slug = trim((string)($row['slug'] ?? ''));
        if ($slug !== '') {
            $existingBySlug[$slug] = true;
        }
    }
    foreach ($requiredSlugs as $requiredSlug) {
        if (!isset($existingBySlug[$requiredSlug])) {
            $seed = comercial_default_process_seed($requiredSlug);
            $seed['enabled'] = 0; // guardrail explícito de migración
            $out[] = comercial_normalize_process($seed);
        }
    }

    usort($out, function ($a, $b) {
        return strcmp((string)($a['nombre'] ?? ''), (string)($b['nombre'] ?? ''));
    });
    $sanitized = array();
    foreach ($out as $row) {
        $sanitized[] = comercial_prepare_process_for_storage($row);
    }
    // Solo reescribir si la migración añadió procesos nuevos.
    // La normalización no debe provocar escrituras — comercial_save_processes()
    // es la única vía para persistir cambios.
    $originalCount = count((array)$rows);
    $normalizedCount = count($sanitized);
    if ($normalizedCount > $originalCount) {
        comercial_event_append('diag_migration_persist', array(
            'caller' => comercial_diag_caller_summary(),
            'original_count' => $originalCount,
            'normalized_count' => $normalizedCount,
        ));
        storage_write('comercial_processes.json', array_values($sanitized));
    }
    return $out;
}

function comercial_save_processes($rows) {
    $previousRows = storage_read('comercial_processes.json');
    $previousEnabledById = array();
    $previousEnabledCount = 0;
    foreach ((array)$previousRows as $prev) {
        $pid = trim((string)($prev['id'] ?? ''));
        if ($pid === '') continue;
        $isEnabled = !empty($prev['enabled']) ? 1 : 0;
        $previousEnabledById[$pid] = $isEnabled;
        if ($isEnabled) $previousEnabledCount++;
    }

    $out = array();
    foreach ((array)$rows as $row) {
        $out[] = comercial_normalize_process($row);
    }

    // Guardrail: evitar apagado masivo accidental de TODOS los procesos.
    // Solo aplica si antes había procesos activos y ahora quedarían 0 activos.
    $newEnabledCount = 0;
    foreach ($out as $row) {
        if (!empty($row['enabled'])) $newEnabledCount++;
    }
    if ($previousEnabledCount > 0 && $newEnabledCount === 0) {
        foreach ($out as &$row) {
            $pid = trim((string)($row['id'] ?? ''));
            if ($pid === '') continue;
            if (isset($previousEnabledById[$pid])) {
                $row['enabled'] = (int)$previousEnabledById[$pid];
            }
        }
        unset($row);
        comercial_event_append('comercial_guardrail_prevented_mass_disable', array(
            'previous_enabled' => $previousEnabledCount,
            'requested_enabled' => $newEnabledCount,
            'caller' => comercial_diag_caller_summary(),
        ));
    }

    // Diag: solo loggear cuando hay cambios en enabled respecto al estado anterior
    $enabledChanged = false;
    foreach ($out as $r) {
        $pid = trim((string)($r['id'] ?? ''));
        $currentEnabled = !empty($r['enabled']) ? 1 : 0;
        $prevEnabled = isset($previousEnabledById[$pid]) ? $previousEnabledById[$pid] : null;
        if ($prevEnabled !== null && $prevEnabled !== $currentEnabled) {
            $enabledChanged = true;
            break;
        }
    }
    if ($enabledChanged || $previousEnabledCount !== $newEnabledCount) {
        $saveSnapshot = array();
        foreach ($out as $r) {
            $saveSnapshot[] = array('slug' => $r['slug'] ?? '?', 'enabled' => !empty($r['enabled']) ? 1 : 0);
        }
        comercial_event_append('diag_save_processes', array(
            'caller' => comercial_diag_caller_summary(),
            'previous_enabled_count' => $previousEnabledCount,
            'new_enabled_count' => $newEnabledCount,
            'enabled_snapshot' => $saveSnapshot,
        ));
    }

    $stored = array();
    foreach ($out as $row) {
        $stored[] = comercial_prepare_process_for_storage($row);
    }
    storage_write('comercial_processes.json', array_values($stored));
    return $out;
}

function comercial_prepare_process_for_storage($row) {
    $row = is_array($row) ? $row : array();
    // No persistir credenciales sensibles por proceso en JSON local.
    // El password de conexión debe resolverse por configuración segura global (env/settings).
    $row['source_mysql_pass'] = '';
    return $row;
}

function comercial_get_process($id) {
    foreach (comercial_get_processes() as $row) {
        if ((string)$row['id'] === (string)$id || (string)$row['slug'] === (string)$id) {
            return $row;
        }
    }
    return null;
}

function comercial_upsert_process($row) {
    $row = comercial_normalize_process($row);
    $rows = comercial_get_processes();
    $done = false;
    foreach ($rows as $i => $item) {
        if ((string)$item['id'] === (string)$row['id']) {
            $rows[$i] = $row;
            $done = true;
            break;
        }
    }
    if (!$done) $rows[] = $row;
    comercial_save_processes($rows);
    return $row;
}

function comercial_normalize_process($row) {
    $row = is_array($row) ? $row : array();
    $defaults = comercial_default_process_seed(trim((string)($row['slug'] ?? 'custom')));
    $out = array_merge($defaults, $row);
    if (trim((string)$out['id']) === '') $out['id'] = generate_id('comproc');
    if (trim((string)$out['slug']) === '') $out['slug'] = trim((string)$out['id']);
    if (trim((string)$out['nombre']) === '') $out['nombre'] = ucfirst((string)$out['slug']);
    $out['enabled'] = !empty($out['enabled']) ? 1 : 0;
    $out['auto_followup'] = !empty($out['auto_followup']) ? 1 : 0;
    $out['auto_create_lead'] = !empty($out['auto_create_lead']) ? 1 : 0;
    $out['priority'] = (int)$out['priority'];
    $out['daily_target_percent'] = max(0, (float)$out['daily_target_percent']);
    $out['daily_target_absolute'] = 0;
    $out['window_start_hour'] = max(0, min(23, (int)$out['window_start_hour']));
    $out['window_end_hour'] = max(0, min(23, (int)$out['window_end_hour']));
    $out['source_type'] = in_array($out['source_type'], array('jsonl_queue', 'mysql_recent'), true) ? $out['source_type'] : 'jsonl_queue';
    $out['source_queue_files'] = comercial_resolve_queue_files((string)$out['slug'], $out['source_queue_files']);
    $out['message_templates'] = comercial_normalize_template_blocks($out['message_templates']);
    $out['followup_templates'] = comercial_normalize_template_blocks($out['followup_templates']);
    // Auto-detección de plantillas corruptas: si el JSON almacenado tiene los mensajes
    // fragmentados en líneas sueltas (bug de guardado con \n\n), ninguna variante
    // alcanzará la longitud mínima del proceso. En ese caso restauramos los defaults.
    $minMsgLen = comercial_hardcoded_min_message_length((string)$out['slug'], 'message_templates');
    if ($minMsgLen > 0 && !empty($out['message_templates'])) {
        $algunoLargo = false;
        foreach ($out['message_templates'] as $tpl) {
            if (comercial_safe_len($tpl) >= $minMsgLen) { $algunoLargo = true; break; }
        }
        if (!$algunoLargo) {
            $out['message_templates'] = array();
        }
    }
    if (empty($out['message_templates'])) {
        $out['message_templates'] = comercial_default_process_templates((string)$out['slug'], 'message_templates');
    }
    if (empty($out['followup_templates'])) {
        $out['followup_templates'] = comercial_default_process_templates((string)$out['slug'], 'followup_templates');
    }
    $out['recipient_blacklist'] = comercial_normalize_phone_blacklist_lines($out['recipient_blacklist'] ?? array());
    $out['positive_keywords'] = comercial_normalize_textarea_lines($out['positive_keywords']);
    $out['negative_keywords'] = comercial_normalize_textarea_lines($out['negative_keywords']);
    $out['ia_context_prompt'] = trim((string)($out['ia_context_prompt'] ?? ''));
    $out['signal_detection_rules'] = comercial_normalize_textarea_lines($out['signal_detection_rules'] ?? array());
    $out['conversation_max_auto_turns'] = max(0, (int)($out['conversation_max_auto_turns'] ?? 2));
    $out['escalation_score_threshold'] = max(0, min(100, (int)($out['escalation_score_threshold'] ?? 78)));
    $out['ia_learning_enabled'] = !empty($out['ia_learning_enabled']) ? 1 : 0;
    $out['auto_notify_operator'] = !empty($out['auto_notify_operator']) ? 1 : 0;
    $out['assigned_line_ids'] = array_values(array_unique(array_filter(array_map('strval', (array)$out['assigned_line_ids']))));
    $out['last_target_phone'] = comercial_only_digits((string)($out['last_target_phone'] ?? ''));

    $plan = comercial_calculate_interval_plan($out);
    $out['min_interval_seconds'] = (int)$plan['min_seconds'];
    $out['max_interval_seconds'] = (int)$plan['max_seconds'];

    $out['updated_at'] = now_datetime();
    if (trim((string)$out['created_at']) === '') $out['created_at'] = now_datetime();
    return $out;
}

function comercial_normalize_textarea_lines($value) {
    if (is_array($value)) {
        $lines = $value;
    } else {
        $lines = preg_split('/\r\n|\r|\n/', (string)$value);
    }

    $out = array();
    foreach ((array)$lines as $line) {
        $line = trim((string)$line);
        if ($line !== '') $out[] = $line;
    }
    return array_values($out);
}

function comercial_normalize_template_blocks($value) {
    if (is_array($value)) {
        // Fix Bug 2: cuando viene como array PHP, cada elemento YA ES una variante completa.
        // Solo hacemos trim y descartamos vacíos — NO colapsamos \n\n internos porque
        // esos son saltos de párrafo intencionales del mensaje (formato WhatsApp).
        $out = array();
        foreach ($value as $block) {
            $block = trim((string)$block);
            if ($block !== '') {
                $out[] = $block;
            }
        }
        return array_values($out);
    }

    // Cuando viene de textarea (string): separar variantes por línea "---".
    // Cada variante puede tener saltos de línea internos (párrafos de WhatsApp)
    // porque usamos "---" como separador inequívoco de variantes en lugar de \n\n.
    $text = trim((string)$value);
    if ($text === '') return array();
    // Normalizar line endings a \n
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\r", "\n", $text);
    // Separar por líneas que contengan exactamente "---" (opcionalmente con espacios)
    $blocks = preg_split("/\n[ \t]*---[ \t]*\n/", $text);

    $out = array();
    foreach ((array)$blocks as $block) {
        $block = trim((string)$block);
        if ($block !== '') {
            $out[] = $block;
        }
    }
    return array_values($out);
}
function comercial_normalize_phone_blacklist_lines($value) {
    $lines = comercial_normalize_textarea_lines($value);
    $out = array();
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') continue;
        $digits = comercial_only_digits($line);
        $out[] = $digits !== '' ? $digits : $line;
    }
    return array_values(array_unique($out));
}

function comercial_phone_matches_blacklist_entry($phone, $entry) {
    $phoneDigits = comercial_normalize_phone_spain($phone);
    $phoneLast9 = strlen($phoneDigits) >= 9 ? substr($phoneDigits, -9) : $phoneDigits;
    $entry = trim((string)$entry);
    if ($phoneDigits === '' || $entry === '') return false;

    $entryDigits = comercial_only_digits($entry);
    if ($entryDigits !== '') {
        $entryNorm = comercial_normalize_phone_spain($entryDigits);
        $entryLast9 = strlen($entryNorm) >= 9 ? substr($entryNorm, -9) : $entryNorm;
        if ($entryNorm !== '' && $phoneDigits === $entryNorm) return true;
        if ($entryDigits !== '' && $phoneDigits === $entryDigits) return true;
        if ($entryLast9 !== '' && $phoneLast9 !== '' && $phoneLast9 === $entryLast9) return true;
        if ($entryDigits !== '' && strlen($entryDigits) >= 9 && substr($phoneDigits, -strlen($entryDigits)) === $entryDigits) return true;
        return false;
    }

    return strcmp(comercial_mb_strtolower_safe($entry), comercial_mb_strtolower_safe($phoneDigits)) === 0;
}

function comercial_blacklist_entry_defaults($id = '') {
    $id = trim((string)$id);
    if ($id === '') $id = generate_id('cmblk');
    return array(
        'id' => $id,
        'phone' => '',
        'notes' => '',
        'created_at' => '',
        'updated_at' => '',
    );
}

function comercial_normalize_blacklist_entry($row) {
    $row = is_array($row) ? $row : array();
    $defaults = comercial_blacklist_entry_defaults((string)($row['id'] ?? ''));
    $out = array_merge($defaults, $row);
    $out['phone'] = comercial_only_digits((string)($out['phone'] ?? ''));
    $out['notes'] = trim((string)($out['notes'] ?? ''));
    $out['created_at'] = trim((string)($out['created_at'] ?? ''));
    $out['updated_at'] = trim((string)($out['updated_at'] ?? ''));
    if ($out['created_at'] === '') $out['created_at'] = now_datetime();
    $out['updated_at'] = now_datetime();
    return $out;
}

function comercial_get_blacklist_entries() {
    $rows = storage_read('comercial_blacklist.json');
    $out = array();
    foreach ((array)$rows as $row) {
        $normalized = comercial_normalize_blacklist_entry($row);
        if ($normalized['phone'] === '') continue;
        $out[] = $normalized;
    }
    usort($out, function ($a, $b) {
        return strcmp((string)($a['phone'] ?? ''), (string)($b['phone'] ?? ''));
    });
    return $out;
}

function comercial_get_blacklist_entry($id) {
    $id = trim((string)$id);
    if ($id === '') return null;
    foreach (comercial_get_blacklist_entries() as $row) {
        if ((string)($row['id'] ?? '') === $id) return $row;
    }
    return null;
}

function comercial_upsert_blacklist_entry($row) {
    $row = comercial_normalize_blacklist_entry($row);
    storage_upsert('comercial_blacklist.json', $row);
    return $row;
}

function comercial_delete_blacklist_entry($id) {
    $id = trim((string)$id);
    if ($id === '') return;
    storage_delete('comercial_blacklist.json', $id);
}

function comercial_phone_is_blacklisted($phone, $process, &$matchedEntry = '') {
    $matchedEntry = '';
    $entries = comercial_get_blacklist_entries();
    if (empty($entries)) return false;
    foreach ($entries as $entry) {
        $entryPhone = (string)($entry['phone'] ?? '');
        if ($entryPhone !== '' && comercial_phone_matches_blacklist_entry($phone, $entryPhone)) {
            $matchedEntry = $entryPhone;
            return true;
        }
    }
    return false;
}

// ─── Registro global de teléfonos ya contactados (deduplicación entre procesos) ───

function comercial_get_sent_phones() {
    $rows = storage_read('comercial_sent_phones.json');
    $out = array();
    foreach ((array)$rows as $row) {
        $phone = comercial_only_digits((string)($row['phone'] ?? ''));
        if ($phone === '') continue;
        $out[] = array(
            'id' => trim((string)($row['id'] ?? '')),
            'phone' => $phone,
            'process_slug' => trim((string)($row['process_slug'] ?? '')),
            'sent_at' => trim((string)($row['sent_at'] ?? '')),
        );
    }
    return $out;
}

function comercial_phone_was_contacted($phone) {
    $entries = comercial_get_sent_phones();
    if (empty($entries)) return false;
    foreach ($entries as $entry) {
        if (comercial_phone_matches($phone, $entry['phone'])) {
            return true;
        }
    }
    return false;
}

function comercial_register_sent_phone($phone, $processSlug = '') {
    $phoneDigits = comercial_only_digits($phone);
    if ($phoneDigits === '') return null;

    // ── Fix #4: deduplicación global + por rama ──

    // Evitar duplicados en el registro global
    if (comercial_phone_was_contacted($phoneDigits)) return null;

    $processSlug = trim((string)$processSlug);
    $entry = array(
        'id' => generate_id('cmsent'),
        'phone' => $phoneDigits,
        'process_slug' => $processSlug,
        'sent_at' => now_datetime(),
    );

    // 1. Registrar en el archivo global
    storage_upsert('comercial_sent_phones.json', $entry);

    // 2. Registrar en el archivo específico de la rama (si hay slug)
    if ($processSlug !== '') {
        $branchFile = 'comercial_sent_phones_' . preg_replace('/[^a-z0-9_\-]/i', '_', $processSlug) . '.json';
        $branchEntry = $entry;
        $branchEntry['branch_file'] = $branchFile;
        storage_upsert($branchFile, $branchEntry);
    }

    return $entry;
}

function comercial_get_sent_phones_by_branch($processSlug) {
    $processSlug = trim((string)$processSlug);
    if ($processSlug === '') return array();
    $branchFile = 'comercial_sent_phones_' . preg_replace('/[^a-z0-9_\-]/i', '_', $processSlug) . '.json';
    $rows = storage_read($branchFile);
    $out = array();
    foreach ((array)$rows as $row) {
        $phone = comercial_only_digits((string)($row['phone'] ?? ''));
        if ($phone === '') continue;
        $out[] = array(
            'id' => trim((string)($row['id'] ?? '')),
            'phone' => $phone,
            'process_slug' => $processSlug,
            'sent_at' => trim((string)($row['sent_at'] ?? '')),
        );
    }
    return $out;
}

function comercial_get_sent_phones_by_branch_count($processSlug) {
    return count(comercial_get_sent_phones_by_branch($processSlug));
}

function comercial_rebuild_global_sent_phones() {
    // Reconstruye el archivo global desde todos los archivos por rama
    $allPhones = array();
    $seen = array();
    $branchFiles = glob(DATA_PATH . '/comercial_sent_phones_*.json');
    if (empty($branchFiles)) $branchFiles = array();

    foreach ($branchFiles as $file) {
        $basename = basename($file);
        if ($basename === 'comercial_sent_phones.json') continue;
        $rows = storage_read($basename);
        foreach ((array)$rows as $row) {
            $phone = comercial_only_digits((string)($row['phone'] ?? ''));
            if ($phone === '' || isset($seen[$phone])) continue;
            $seen[$phone] = true;
            $allPhones[] = array(
                'id' => trim((string)($row['id'] ?? generate_id('cmsent'))),
                'phone' => $phone,
                'process_slug' => trim((string)($row['process_slug'] ?? '')),
                'sent_at' => trim((string)($row['sent_at'] ?? now_datetime())),
            );
        }
    }

    // También incluir entradas que ya estén en el global pero no en branch files
    $globalRows = storage_read('comercial_sent_phones.json');
    foreach ((array)$globalRows as $row) {
        $phone = comercial_only_digits((string)($row['phone'] ?? ''));
        if ($phone === '' || isset($seen[$phone])) continue;
        $seen[$phone] = true;
        $allPhones[] = array(
            'id' => trim((string)($row['id'] ?? generate_id('cmsent'))),
            'phone' => $phone,
            'process_slug' => trim((string)($row['process_slug'] ?? '')),
            'sent_at' => trim((string)($row['sent_at'] ?? now_datetime())),
        );
    }

    storage_write('comercial_sent_phones.json', array_values($allPhones));
    return count($allPhones);
}

// ─── Fin registro global de enviados ───

function comercial_templates_match_legacy($slug, $field, $value) {
    $current = comercial_normalize_template_blocks($value);
    $legacy = comercial_normalize_template_blocks(comercial_legacy_process_templates($slug, $field));
    if (empty($current) || empty($legacy)) {
        return false;
    }
    return $current === $legacy;
}

function comercial_process_message_pool($process, $field) {
    $process = is_array($process) ? $process : array();
    $slug = trim((string)($process['slug'] ?? ''));
    $pool = comercial_normalize_template_blocks(isset($process[$field]) ? $process[$field] : array());
    if (empty($pool)) {
        $pool = comercial_normalize_template_blocks(comercial_default_process_templates($slug, $field));
    }
    return $pool;
}

function comercial_hardcoded_min_message_length($slug, $field) {
    $slug = trim((string)$slug);
    $field = trim((string)$field);
    if ($field !== 'message_templates') {
        return 0;
    }
    switch ($slug) {
        case 'plaza':
            return 180;
        case 'lamami':
            return 260;
        case 'casawasap':
            return 320;
        case 'publicista':
            return 420;
        case 'publiscort':
            return 200;
        default:
            return 0;
    }
}

function comercial_array_to_textarea($value) {
    return implode("\n", comercial_normalize_textarea_lines($value));
}

function comercial_templates_to_textarea($value) {
    // Usar "---" como separador inequívoco de variantes para evitar colisión
    // con saltos de párrafo internos de los mensajes (formato WhatsApp).
    return implode("\n---\n", comercial_normalize_template_blocks($value));
}

// Separador seguro entre variantes: línea con "---".
// Las variantes en textarea se separan por \n---\n.
// Cada variante puede contener \n\n internos (párrafos de WhatsApp).
function comercial_templates_separator() {
    return "\n---\n";
}

function comercial_get_line_states() {
    $rows = storage_read('comercial_line_state.json');
    $out = array();
    foreach ((array)$rows as $row) {
        $id = trim((string)($row['line_id'] ?? ''));
        if ($id === '') continue;
        $out[$id] = comercial_normalize_line_state($row);
    }
    return $out;
}

function comercial_get_runtime_state() {
    $state = storage_read('comercial_runtime.json');
    $state = is_array($state) ? $state : array();
    return array_merge(array(
        'last_sent_line_id' => '',
        'last_sent_target_phone' => '',
        'updated_at' => '',
    ), $state);
}

function comercial_save_runtime_state($state) {
    $current = comercial_get_runtime_state();
    $next = array_merge($current, is_array($state) ? $state : array());
    $next['last_sent_line_id'] = trim((string)($next['last_sent_line_id'] ?? ''));
    $next['last_sent_target_phone'] = comercial_only_digits((string)($next['last_sent_target_phone'] ?? ''));
    $next['updated_at'] = now_datetime();
    storage_write('comercial_runtime.json', $next);
    return $next;
}

function comercial_register_last_send($lineId, $targetPhone) {
    // COM-BALANCE-F2: incrementar contador diario de la línea que efectivamente envió
    comercial_line_increment_daily_count(trim((string)$lineId));
    return comercial_save_runtime_state(array(
        'last_sent_line_id' => trim((string)$lineId),
        'last_sent_target_phone' => comercial_only_digits((string)$targetPhone),
    ));
}

function comercial_normalize_line_state($row) {
    $row = is_array($row) ? $row : array();
    $defaults = array(
        'line_id' => '',
        'status' => 'active',
        'health_status' => 'unknown',
        'health_http_code' => 0,
        'health_error' => '',
        'health_session_status' => '',
        'last_health_check_at' => '',
        'last_health_ok_at' => '',
        'last_health_failure_at' => '',
        'effective_power_factor' => 1,
        'adaptive_power_factor' => 1,
        'consecutive_failures' => 0,
        'last_http_code' => 0,
        'last_error' => '',
        'last_success_at' => '',
        'last_failure_at' => '',
        'stable_since_at' => '',
        'last_ban_at' => '',
        'last_power_raise_at' => '',
        'last_power_drop_at' => '',
        'cooldown_until' => '',
        'rolling_window' => array(),
        // COM-BALANCE: contador diario de envíos para balanceo entre líneas
        'daily_sent_count' => 0,
        'daily_sent_date' => '',
        'updated_at' => now_datetime(),
    );
    $out = array_merge($defaults, $row);
    $adaptive = isset($out['adaptive_power_factor']) ? (float)$out['adaptive_power_factor'] : (float)$out['effective_power_factor'];
    $out['adaptive_power_factor'] = max(comercial_adaptive_min_power_factor(), min(comercial_adaptive_max_power_factor(), $adaptive > 0 ? $adaptive : 1.0));
    $out['status'] = trim((string)($out['status'] ?? 'active'));
    if ($out['status'] === '') {
        $out['status'] = 'active';
    }
    $out['effective_power_factor'] = comercial_line_effective_power_from_state($out);
    $out['consecutive_failures'] = (int)$out['consecutive_failures'];
    $out['health_http_code'] = (int)$out['health_http_code'];
    $out['last_http_code'] = (int)$out['last_http_code'];
    $out['daily_sent_count'] = (int)($out['daily_sent_count'] ?? 0);
    $out['daily_sent_date'] = trim((string)($out['daily_sent_date'] ?? ''));
    $out['rolling_window'] = array_values(array_slice((array)$out['rolling_window'], -50));
    $out['updated_at'] = now_datetime();
    return $out;
}

function comercial_save_line_states($map) {
    $rows = array();
    foreach ((array)$map as $row) {
        $rows[] = comercial_normalize_line_state($row);
    }
    storage_write('comercial_line_state.json', array_values($rows));
}

function comercial_line_effective_power_from_state($state) {
    $state = is_array($state) ? $state : array();
    $adaptive = isset($state['adaptive_power_factor']) ? (float)$state['adaptive_power_factor'] : (float)($state['effective_power_factor'] ?? 1);
    $adaptive = max(comercial_adaptive_min_power_factor(), min(comercial_adaptive_max_power_factor(), $adaptive > 0 ? $adaptive : 1.0));
    $status = trim((string)($state['status'] ?? 'active'));

    if ($status === 'paused') {
        return min($adaptive, 0.30);
    }
    if ($status === 'warning') {
        return min($adaptive, 0.60);
    }
    return $adaptive;
}

function comercial_autoregulation_meta() {
    $stats = storage_read('comercial_daily_stats.json');
    $meta = is_array($stats['_autoregulation'] ?? null) ? $stats['_autoregulation'] : array();
    return array_merge(array(
        'last_global_slowdown_at' => '',
        'last_global_slowdown_line_id' => '',
    ), $meta);
}

function comercial_save_autoregulation_meta($meta) {
    $stats = storage_read('comercial_daily_stats.json');
    $stats = is_array($stats) ? $stats : array();
    $stats['_autoregulation'] = array_merge(comercial_autoregulation_meta(), is_array($meta) ? $meta : array());
    storage_write('comercial_daily_stats.json', $stats);
}

function comercial_line_failure_counts_as_ban($httpCode, $errorText = '') {
    $httpCode = (int)$httpCode;
    $errorText = trim((string)$errorText);
    if ($httpCode === 201) {
        return false;
    }
    if ($httpCode === 0) {
        return true;
    }
    if ($httpCode >= 400) {
        return true;
    }
    return $errorText !== '';
}

function comercial_maybe_raise_line_power($lineId, $state) {
    $state = is_array($state) ? $state : array();
    $stableSinceTs = strtotime((string)($state['stable_since_at'] ?? ''));
    if (!$stableSinceTs) {
        return false;
    }
    if ((time() - $stableSinceTs) < comercial_adaptive_raise_after_seconds()) {
        return false;
    }

    $lastRaiseTs = strtotime((string)($state['last_power_raise_at'] ?? ''));
    if ($lastRaiseTs && (time() - $lastRaiseTs) < comercial_adaptive_raise_after_seconds()) {
        return false;
    }

    $current = (float)($state['adaptive_power_factor'] ?? 1);
    $next = min(comercial_adaptive_max_power_factor(), round($current + comercial_adaptive_raise_step(), 2));
    if ($next <= $current) {
        return false;
    }

    comercial_update_line_state($lineId, array(
        'adaptive_power_factor' => $next,
        'last_power_raise_at' => now_datetime(),
    ));
    comercial_event_append('line_power_raised', array(
        'line_id' => $lineId,
        'from' => $current,
        'to' => $next,
        'stable_since_at' => (string)($state['stable_since_at'] ?? ''),
    ));
    return true;
}

function comercial_apply_global_ban_slowdown($triggerLineId, $httpCode = 0, $errorText = '') {
    $meta = comercial_autoregulation_meta();
    $lastSlowdownTs = strtotime((string)($meta['last_global_slowdown_at'] ?? ''));
    if ($lastSlowdownTs && (time() - $lastSlowdownTs) < comercial_adaptive_global_drop_cooldown_seconds()) {
        return false;
    }

    $lines = comercial_list_lines();
    if (empty($lines)) {
        return false;
    }

    $states = comercial_get_line_states();
    $updated = array();
    $affected = 0;
    $now = now_datetime();

    foreach ($lines as $line) {
        $lineId = trim((string)($line['id'] ?? ''));
        if ($lineId === '') {
            continue;
        }
        $state = isset($states[$lineId]) ? $states[$lineId] : comercial_normalize_line_state(array('line_id' => $lineId));
        $current = (float)($state['adaptive_power_factor'] ?? 1);
        $next = max(comercial_adaptive_min_power_factor(), round($current * comercial_adaptive_global_drop_factor(), 2));
        if ($next < $current) {
            $affected++;
        }
        $state['adaptive_power_factor'] = $next;
        $state['last_power_drop_at'] = $now;
        if ($lineId === $triggerLineId) {
            $state['last_ban_at'] = $now;
        }
        $state['effective_power_factor'] = comercial_line_effective_power_from_state($state);
        $updated[$lineId] = $state;
    }

    if (empty($updated)) {
        return false;
    }

    comercial_save_line_states($updated);
    comercial_save_autoregulation_meta(array(
        'last_global_slowdown_at' => $now,
        'last_global_slowdown_line_id' => $triggerLineId,
    ));
    comercial_event_append('line_power_global_drop', array(
        'trigger_line_id' => $triggerLineId,
        'http_code' => (int)$httpCode,
        'error' => trim((string)$errorText),
        'affected_lines' => $affected,
        'drop_factor' => comercial_adaptive_global_drop_factor(),
    ));
    return true;
}

function comercial_get_line_state($lineId) {
    $map = comercial_get_line_states();
    if (isset($map[$lineId])) return $map[$lineId];
    return comercial_normalize_line_state(array('line_id' => $lineId));
}

function comercial_update_line_state($lineId, $patch) {
    $map = comercial_get_line_states();
    $current = isset($map[$lineId]) ? $map[$lineId] : array('line_id' => $lineId);
    $map[$lineId] = comercial_normalize_line_state(array_merge($current, is_array($patch) ? $patch : array()));
    comercial_save_line_states($map);
    return $map[$lineId];
}

// ── COM-BALANCE: contador diario de envíos para balanceo entre líneas ──

/**
 * Obtiene el contador diario de envíos de una línea.
 * Si la fecha almacenada no es hoy, resetea el contador a 0.
 */
function comercial_line_get_daily_count($lineId) {
    $state = comercial_get_line_state($lineId);
    $today = date('Y-m-d');
    $storedDate = trim((string)($state['daily_sent_date'] ?? ''));
    if ($storedDate !== $today) {
        // Reset silencioso: el contador se resetea en la primera lectura del día
        comercial_update_line_state($lineId, array(
            'daily_sent_count' => 0,
            'daily_sent_date' => $today,
        ));
        return 0;
    }
    return (int)($state['daily_sent_count'] ?? 0);
}

/**
 * Incrementa el contador diario de una línea tras un envío exitoso.
 * Persiste a disco inmediatamente para que otros procesos en el mismo tick vean el valor actualizado.
 */
function comercial_line_increment_daily_count($lineId) {
    $state = comercial_get_line_state($lineId);
    $today = date('Y-m-d');
    $storedDate = trim((string)($state['daily_sent_date'] ?? ''));
    $count = ($storedDate === $today) ? (int)($state['daily_sent_count'] ?? 0) : 0;
    return comercial_update_line_state($lineId, array(
        'daily_sent_count' => $count + 1,
        'daily_sent_date' => $today,
    ));
}

/**
 * Resetea los contadores diarios de todas las líneas si cambió el día.
 * Debe invocarse al inicio de comercial_run_tick(), antes de iterar procesos.
 */
function comercial_reset_daily_counts_if_new_day() {
    $today = date('Y-m-d');
    $map = comercial_get_line_states();
    $changed = false;
    foreach ($map as $lineId => $state) {
        $storedDate = trim((string)($state['daily_sent_date'] ?? ''));
        if ($storedDate !== $today && (int)($state['daily_sent_count'] ?? 0) > 0) {
            $map[$lineId]['daily_sent_count'] = 0;
            $map[$lineId]['daily_sent_date'] = $today;
            $changed = true;
        }
    }
    if ($changed) {
        comercial_save_line_states($map);
    }
}

/**
 * Devuelve un mapa [lineId => daily_sent_count] para las líneas indicadas.
 * Versión批量 para eficiencia: una sola lectura de disco, un solo reset.
 */
function comercial_line_get_daily_counts_map($lineIds) {
    $today = date('Y-m-d');
    $map = comercial_get_line_states();
    $out = array();
    $changed = false;
    foreach ((array)$lineIds as $lineId) {
        $lineId = trim((string)$lineId);
        if ($lineId === '') continue;
        if (!isset($map[$lineId])) {
            $out[$lineId] = 0;
            continue;
        }
        $state = $map[$lineId];
        $storedDate = trim((string)($state['daily_sent_date'] ?? ''));
        if ($storedDate !== $today) {
            $map[$lineId]['daily_sent_count'] = 0;
            $map[$lineId]['daily_sent_date'] = $today;
            $changed = true;
            $out[$lineId] = 0;
        } else {
            $out[$lineId] = (int)($state['daily_sent_count'] ?? 0);
        }
    }
    if ($changed) {
        comercial_save_line_states($map);
    }
    return $out;
}

function comercial_get_threads() {
    $rows = storage_read('comercial_threads.json');
    $out = array();
    foreach ((array)$rows as $row) {
        $id = trim((string)($row['id'] ?? ''));
        if ($id === '') continue;
        $out[] = comercial_normalize_thread($row);
    }
    usort($out, function ($a, $b) {
        return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
    });
    return $out;
}

function comercial_normalize_thread($row) {
    $row = is_array($row) ? $row : array();
    $defaults = array(
        'id' => generate_id('comthread'),
        'process_id' => '',
        'process_slug' => '',
        'line_id' => '',
        'line_phone' => '',
        'target_phone' => '',
        'source_ref' => '',
        'source_payload' => array(),
        'stage' => 'queued',
        'status' => 'open',
        'messages_sent_count' => 0,
        'replies_count' => 0,
        'last_outbound_text' => '',
        'last_inbound_text' => '',
        'qualified_at' => '',
        'responded_at' => '',
        'hot_at' => '',
        'qualified_reply_sent_at' => '',
        'human_taken' => 0,
        'lead_id' => '',
        'notes' => '',
        'last_ai_suggested_reply' => '',
        'last_ai_suggested_at' => '',
        'last_ai_feedback' => '',
        'last_ai_feedback_meta' => array(),
        'auto_turn_count' => 0,
        'next_bot_action_at' => '',
        'defer_count' => 0,
        'last_decision' => '',
        'last_confidence' => 0,
        'created_at' => now_datetime(),
        'updated_at' => now_datetime(),
        'last_contact_at' => '',
        'last_bot_reply_at' => '',
        'last_bot_reply_text' => '',
        'prior_inbound_text' => '',
        'last_inbound_processed_at' => '',
        'hot_notified_at' => '',
    );
    $out = array_merge($defaults, $row);
    $out['human_taken'] = !empty($out['human_taken']) ? 1 : 0;
    $out['auto_turn_count'] = max(0, (int)($out['auto_turn_count'] ?? 0));
    $out['defer_count'] = max(0, (int)($out['defer_count'] ?? 0));
    $out['last_confidence'] = max(0, min(1, (float)($out['last_confidence'] ?? 0)));
    if ($out['target_phone'] !== '') $out['target_phone'] = comercial_only_digits($out['target_phone']);
    if ($out['line_phone'] !== '') $out['line_phone'] = comercial_only_digits($out['line_phone']);
    return $out;
}

function comercial_thread_apply_stage($thread, $stage) {
    $thread = comercial_normalize_thread($thread);
    $stage = trim((string)$stage);
    if ($stage === '') {
        return $thread;
    }

    $thread['stage'] = $stage;
    $thread['updated_at'] = now_datetime();

    if ($stage === 'qualified' && trim((string)$thread['qualified_at']) === '') {
        $thread['qualified_at'] = now_datetime();
    }
    if ($stage === 'responded' && trim((string)$thread['responded_at']) === '') {
        $thread['responded_at'] = now_datetime();
    }
    if ($stage === 'very_hot' && trim((string)$thread['hot_at']) === '') {
        $thread['hot_at'] = now_datetime();
    }

    if ($stage === 'discarded') {
        $thread['status'] = 'closed';
    } else {
        $thread['status'] = 'open';
    }

    return $thread;
}

function comercial_save_threads($rows) {
    $out = array();
    foreach ((array)$rows as $row) {
        $out[] = comercial_normalize_thread($row);
    }
    storage_write('comercial_threads.json', array_values($out));
}

function comercial_upsert_thread($row) {
    $row = comercial_normalize_thread($row);
    $rows = comercial_get_threads();
    $done = false;
    foreach ($rows as $i => $item) {
        if ((string)$item['id'] === (string)$row['id']) {
            $rows[$i] = $row;
            $done = true;
            break;
        }
    }
    if (!$done) $rows[] = $row;
    comercial_save_threads($rows);
    return $row;
}

function comercial_find_thread_by_process_phone($processId, $targetPhone) {
    $targetPhone = comercial_only_digits($targetPhone);
    foreach (comercial_get_threads() as $row) {
        if ((string)$row['process_id'] === (string)$processId && comercial_phone_matches((string)$row['target_phone'], $targetPhone)) {
            return $row;
        }
    }
    return null;
}

function comercial_find_open_thread_for_inbound($fromPhone, $toPhone = '', $linePort = '') {
    $fromPhone = comercial_only_digits($fromPhone);
    $toPhone = comercial_only_digits($toPhone);
    $linePort = trim((string)$linePort);
    $lines = comercial_list_lines_indexed();

    // Determinar la línea receptora para restringir el fallback a esa misma línea
    $incomingLineId = '';
    if ($toPhone !== '' || $linePort !== '') {
        $incomingLine = comercial_find_line_for_inbound($toPhone, $linePort);
        if ($incomingLine) {
            $incomingLineId = trim((string)($incomingLine['id'] ?? ''));
        }
    }

    $fallbackMatched = null;
    $fallbackPhoneOnly = null;
    $fallbackSameLine = null;

    foreach (comercial_get_threads() as $row) {
        if (!comercial_phone_matches((string)$row['target_phone'], $fromPhone)) continue;
        if ($toPhone !== '' && comercial_phone_matches((string)$row['line_phone'], $toPhone)) return $row;
        if ($linePort !== '' && isset($lines[$row['line_id']]) && trim((string)($lines[$row['line_id']]['waha_port'] ?? '')) === $linePort) {
            return $row;
        }
        // ── T4.1: priorizar hilos de la misma línea receptora ──
        // Si se conoce la línea receptora, el fallback principal es un hilo de esa misma línea.
        if ($incomingLineId !== '' && trim((string)($row['line_id'] ?? '')) === $incomingLineId) {
            if ($fallbackSameLine === null && !in_array((string)$row['stage'], array('discarded', 'autoresponder'), true) && (string)($row['status'] ?? 'open') === 'open') {
                $fallbackSameLine = $row;
            }
            continue; // seguir buscando match exacto, pero ya tenemos candidato same-line
        }
        // Si NO se conoce la línea receptora, aplicar fallback genérico
        if ($incomingLineId === '') {
            if ($toPhone === '' && $linePort === '' && !in_array((string)$row['stage'], array('discarded', 'autoresponder'), true) && (string)($row['status'] ?? 'open') === 'open') {
                return $row;
            }
        }
        if ($fallbackMatched === null && !in_array((string)$row['stage'], array('discarded', 'autoresponder'), true)) {
            $fallbackMatched = $row;
        }
        if ($fallbackPhoneOnly === null && (string)($row['process_slug'] ?? '') !== 'inbound' && (string)($row['status'] ?? 'open') === 'open') {
            $fallbackPhoneOnly = $row;
        }
    }
    // ── T4.1: cadena de fallback con prioridad por línea ──
    if ($fallbackSameLine) return $fallbackSameLine;
    if ($fallbackPhoneOnly) return $fallbackPhoneOnly;
    if ($fallbackMatched) return $fallbackMatched;
    return null;
}

function comercial_get_leads() {
    $rows = storage_read('comercial_leads.json');
    usort($rows, function ($a, $b) {
        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });
    return $rows;
}

function comercial_upsert_lead($row) {
    $row = is_array($row) ? $row : array();
    if (trim((string)($row['id'] ?? '')) === '') $row['id'] = generate_id('comlead');
    if (trim((string)($row['created_at'] ?? '')) === '') $row['created_at'] = now_datetime();
    $row['updated_at'] = now_datetime();
    storage_upsert('comercial_leads.json', $row);
    return $row;
}

function comercial_event_append($type, $payload = array()) {
    $path = DATA_PATH . '/comercial_events.jsonl';
    $row = array(
        'ts' => now_datetime(),
        'date' => today_date(),
        'type' => trim((string)$type),
        'payload' => is_array($payload) ? $payload : array('value' => $payload),
    );
    file_put_contents($path, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

function comercial_webhook_log_path() {
    return DATA_PATH . '/comercial_webhook_log.jsonl';
}

function comercial_webhook_current_request_id() {
    return trim((string)($GLOBALS['comercial_webhook_request_id'] ?? ''));
}

function comercial_webhook_log_append($type, $payload = array()) {
    $path = comercial_webhook_log_path();
    $row = array(
        'ts' => now_datetime(),
        'date' => today_date(),
        'type' => trim((string)$type),
        'request_id' => comercial_webhook_current_request_id(),
        'payload' => is_array($payload) ? $payload : array('value' => $payload),
    );
    file_put_contents($path, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

function comercial_webhook_logs_recent($limit = 120) {
    $path = comercial_webhook_log_path();
    if (!file_exists($path)) return array();
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return array();
    $lines = array_slice($lines, -1 * max(1, (int)$limit));
    $out = array();
    foreach (array_reverse($lines) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) $out[] = $row;
    }
    return $out;
}

function comercial_save_webhook_log_rows($rows) {
    $path = comercial_webhook_log_path();
    $lines = array();
    foreach ((array)$rows as $row) {
        if (!is_array($row)) continue;
        $lines[] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    file_put_contents($path, implode(PHP_EOL, $lines) . (empty($lines) ? '' : PHP_EOL));
}

function comercial_events_recent($limit = 80) {
    $path = DATA_PATH . '/comercial_events.jsonl';
    if (!file_exists($path)) return array();
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return array();
    $lines = array_slice($lines, -1 * max(1, (int)$limit));
    $out = array();
    foreach (array_reverse($lines) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) $out[] = $row;
    }
    return $out;
}

function comercial_thread_event_matches($thread, $event) {
    $thread = comercial_normalize_thread($thread);
    $event = is_array($event) ? $event : array();
    $payload = isset($event['payload']) && is_array($event['payload']) ? $event['payload'] : array();

    $threadId = trim((string)($thread['id'] ?? ''));
    $payloadThreadId = trim((string)($payload['thread_id'] ?? ''));
    if ($threadId !== '' && $payloadThreadId !== '' && $payloadThreadId === $threadId) {
        return true;
    }
    if ($threadId !== '' && $payloadThreadId !== '' && $payloadThreadId !== $threadId) {
        return false;
    }

    $processId = trim((string)($thread['process_id'] ?? ''));
    $lineId = trim((string)($thread['line_id'] ?? ''));
    $targetPhone = comercial_only_digits((string)($thread['target_phone'] ?? ''));

    if ($processId !== '' && trim((string)($payload['process_id'] ?? '')) !== '' && trim((string)($payload['process_id'] ?? '')) !== $processId) {
        return false;
    }
    if ($lineId !== '' && trim((string)($payload['line_id'] ?? '')) !== '' && trim((string)($payload['line_id'] ?? '')) !== $lineId) {
        return false;
    }
    if ($targetPhone !== '' && comercial_only_digits((string)($payload['target_phone'] ?? '')) !== '' && !comercial_phone_matches((string)($payload['target_phone'] ?? ''), $targetPhone)) {
        return false;
    }

    return $targetPhone !== '' && comercial_phone_matches((string)($payload['target_phone'] ?? ''), $targetPhone);
}

function comercial_thread_history($thread, $limit = 1500) {
    $thread = comercial_normalize_thread($thread);
    $events = array_reverse(comercial_events_recent($limit));
    $history = array();
    $historyIndex = array();

    foreach ($events as $event) {
        $type = trim((string)($event['type'] ?? ''));
        if (!in_array($type, array('send_ok', 'reply_received', 'manual_outbound_sent', 'qualified_auto_reply_sent', 'qualified_auto_reply_failed', 'thread_message_sent', 'thread_stage_manual', 'lead_created'), true)) {
            continue;
        }
        if (!comercial_thread_event_matches($thread, $event)) {
            continue;
        }

        $payload = isset($event['payload']) && is_array($event['payload']) ? $event['payload'] : array();
        $text = trim((string)($payload['text'] ?? ''));
        $entry = array(
            'ts' => trim((string)($event['ts'] ?? '')),
            'type' => $type,
            'direction' => '',
            'text' => $text,
            'label' => '',
        );

        if (in_array($type, array('send_ok', 'manual_outbound_sent', 'qualified_auto_reply_sent', 'thread_message_sent'), true) && $text !== '') {
            $entry['direction'] = 'out';
            $entry['label'] = ($type === 'qualified_auto_reply_sent') ? 'Respuesta automática' : 'Enviado';
        } elseif ($type === 'reply_received' && $text !== '') {
            $entry['direction'] = 'in';
            $entry['label'] = 'Respuesta cliente';
        } elseif ($type === 'qualified_auto_reply_failed') {
            $entry['label'] = 'Fallo respuesta automática: ' . trim((string)($payload['error'] ?? ''));
        } elseif ($type === 'thread_stage_manual') {
            $entry['label'] = 'Cambio manual: ' . comercial_thread_stage_label((string)($payload['stage'] ?? ''));
        } elseif ($type === 'lead_created') {
            $entry['label'] = 'Lead creado';
        }

        if ($entry['direction'] === '' && $entry['label'] === '') {
            continue;
        }

        $dedupeKey = $entry['ts'] . '|' . $entry['direction'] . '|' . $entry['text'];
        $priority = 1;
        if ($type === 'qualified_auto_reply_sent') {
            $priority = 4;
        } elseif ($type === 'manual_outbound_sent' || $type === 'thread_message_sent') {
            $priority = 3;
        } elseif ($type === 'reply_received') {
            $priority = 2;
        }

        if (isset($historyIndex[$dedupeKey])) {
            $existingIndex = (int)$historyIndex[$dedupeKey];
            $existingPriority = (int)($history[$existingIndex]['priority'] ?? 0);
            if ($priority > $existingPriority) {
                $entry['priority'] = $priority;
                $history[$existingIndex] = $entry;
            }
            continue;
        }

        $entry['priority'] = $priority;
        $historyIndex[$dedupeKey] = count($history);
        $history[] = $entry;
    }

    foreach ($history as $idx => $entry) {
        unset($history[$idx]['priority']);
    }

    if (empty($history)) {
        $fallbackText = trim((string)($thread['last_outbound_text'] ?? ''));
        if ($fallbackText !== '') {
            $history[] = array(
                'ts' => trim((string)($thread['created_at'] ?? '')),
                'type' => 'fallback_outbound',
                'direction' => 'out',
                'text' => $fallbackText,
                'label' => 'Último outbound guardado',
            );
        }
        $fallbackInbound = trim((string)($thread['last_inbound_text'] ?? ''));
        if ($fallbackInbound !== '') {
            $history[] = array(
                'ts' => trim((string)($thread['updated_at'] ?? '')),
                'type' => 'fallback_inbound',
                'direction' => 'in',
                'text' => $fallbackInbound,
                'label' => 'Último inbound guardado',
            );
        }
    }

    return $history;
}

function comercial_ai_memory_limit() {
    return 180;
}

function comercial_ai_memory_get_rows() {
    $rows = storage_read('comercial_ai_memory.json');
    $out = array();
    foreach ((array)$rows as $row) {
        if (!is_array($row)) continue;
        $text = trim((string)($row['text'] ?? ''));
        if ($text === '') continue;
        $out[] = array(
            'id' => trim((string)($row['id'] ?? generate_id('caimem'))),
            'process_slug' => trim((string)($row['process_slug'] ?? '')),
            'kind' => trim((string)($row['kind'] ?? 'human_reply')),
            'text' => $text,
            'accepted_count' => max(0, (int)($row['accepted_count'] ?? 0)),
            'edited_count' => max(0, (int)($row['edited_count'] ?? 0)),
            'led_to_lead_count' => max(0, (int)($row['led_to_lead_count'] ?? 0)),
            'use_count' => max(0, (int)($row['use_count'] ?? 0)),
            'last_used_at' => trim((string)($row['last_used_at'] ?? '')),
            'created_at' => trim((string)($row['created_at'] ?? now_datetime())),
            'updated_at' => trim((string)($row['updated_at'] ?? now_datetime())),
        );
    }
    return $out;
}

function comercial_ai_memory_save_rows($rows) {
    $rows = array_values((array)$rows);
    usort($rows, function ($a, $b) {
        $scoreA = ((int)($a['led_to_lead_count'] ?? 0) * 8) + ((int)($a['accepted_count'] ?? 0) * 3) - ((int)($a['edited_count'] ?? 0));
        $scoreB = ((int)($b['led_to_lead_count'] ?? 0) * 8) + ((int)($b['accepted_count'] ?? 0) * 3) - ((int)($b['edited_count'] ?? 0));
        if ($scoreA === $scoreB) {
            return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
        }
        return $scoreB <=> $scoreA;
    });
    if (count($rows) > comercial_ai_memory_limit()) {
        $rows = array_slice($rows, 0, comercial_ai_memory_limit());
    }
    storage_write('comercial_ai_memory.json', $rows);
}

function comercial_ai_memory_store_feedback($processSlug, $kind, $text, $feedbackMeta = array()) {
    $processSlug = trim((string)$processSlug);
    $kind = trim((string)$kind);
    $text = trim((string)$text);
    $feedbackMeta = is_array($feedbackMeta) ? $feedbackMeta : array();
    if ($text === '') return;

    $rows = comercial_ai_memory_get_rows();
    $matchIndex = -1;
    foreach ($rows as $i => $row) {
        if ((string)$row['process_slug'] === $processSlug && (string)$row['kind'] === $kind && (string)$row['text'] === $text) {
            $matchIndex = $i;
            break;
        }
    }

    $now = now_datetime();
    if ($matchIndex < 0) {
        $rows[] = array(
            'id' => generate_id('caimem'),
            'process_slug' => $processSlug,
            'kind' => $kind !== '' ? $kind : 'human_reply',
            'text' => $text,
            'accepted_count' => 0,
            'edited_count' => 0,
            'led_to_lead_count' => 0,
            'use_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'last_used_at' => '',
        );
        $matchIndex = count($rows) - 1;
    }

    $row = $rows[$matchIndex];
    $row['use_count'] = (int)$row['use_count'] + 1;
    if (!empty($feedbackMeta['accepted'])) $row['accepted_count'] = (int)$row['accepted_count'] + 1;
    if (!empty($feedbackMeta['edited'])) $row['edited_count'] = (int)$row['edited_count'] + 1;
    if (!empty($feedbackMeta['led_to_lead'])) $row['led_to_lead_count'] = (int)$row['led_to_lead_count'] + 1;
    $row['last_used_at'] = $now;
    $row['updated_at'] = $now;
    $rows[$matchIndex] = $row;
    comercial_ai_memory_save_rows($rows);
}

function comercial_ai_memory_top_examples($processSlug, $kind = 'human_reply', $limit = 3) {
    $processSlug = trim((string)$processSlug);
    $kind = trim((string)$kind);
    $limit = max(1, min(8, (int)$limit));
    $rows = comercial_ai_memory_get_rows();
    $filtered = array();
    foreach ($rows as $row) {
        if ($kind !== '' && (string)($row['kind'] ?? '') !== $kind) continue;
        if ($processSlug !== '' && (string)($row['process_slug'] ?? '') !== $processSlug) continue;
        $filtered[] = $row;
    }
    usort($filtered, function ($a, $b) {
        $scoreA = ((int)($a['led_to_lead_count'] ?? 0) * 8) + ((int)($a['accepted_count'] ?? 0) * 3) - ((int)($a['edited_count'] ?? 0)) + ((int)($a['use_count'] ?? 0));
        $scoreB = ((int)($b['led_to_lead_count'] ?? 0) * 8) + ((int)($b['accepted_count'] ?? 0) * 3) - ((int)($b['edited_count'] ?? 0)) + ((int)($b['use_count'] ?? 0));
        if ($scoreA === $scoreB) {
            return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
        }
        return $scoreB <=> $scoreA;
    });
    return array_slice($filtered, 0, $limit);
}

function comercial_build_contextual_followup_prompt($thread, $processSlug, $objective) {
    $thread = comercial_normalize_thread($thread);
    $processSlug = trim((string)$processSlug);
    $objective = trim((string)$objective);
    $history = comercial_thread_history($thread, 200);
    $lines = array();
    foreach ((array)$history as $entry) {
        $text = trim((string)($entry['text'] ?? ''));
        if ($text === '') continue;
        $direction = (string)($entry['direction'] ?? '') === 'in' ? 'cliente' : 'comercial';
        $lines[] = strtoupper($direction) . ': ' . $text;
    }
    $examples = comercial_ai_memory_top_examples($processSlug, 'human_reply', 3);
    $examplesLines = array();
    foreach ($examples as $idx => $row) {
        $examplesLines[] = ($idx + 1) . ') ' . trim((string)($row['text'] ?? ''));
    }

    // ── Obtener contexto IA del proceso ──
    $processObj = $processSlug !== '' ? comercial_get_process($processSlug) : null;
    $iaContext = is_array($processObj) ? trim((string)($processObj['ia_context_prompt'] ?? '')) : '';

    // ── Fix U2: detectar si el cliente SOLO saludó (sin preguntar) ──
    $greetingOnlyNote = '';
    if (!empty($thread['_greeting_only'])) {
        $greetingOnlyNote = "\nIMPORTANTE: El cliente SOLO saludó, NO hizo ninguna pregunta concreta. NO digas frases como 'me alegra que preguntes', 'gracias por preguntar', 'buena pregunta' ni similares. Responde al saludo de forma natural y haz una pregunta abierta suave para iniciar conversación.";
    }

    // ── T3.1: contexto de clasificación y estrategia para mejorar entendimiento de la IA ──
    $classificationNote = '';
    $lastDecision = trim((string)($thread['last_decision'] ?? ''));
    $lastStrategy = trim((string)($thread['last_strategy_used'] ?? ''));
    $lastStage = trim((string)($thread['stage'] ?? ''));
    if ($lastDecision !== '') {
        $classificationNote .= "\nDecisión del sistema sobre el último mensaje del cliente: " . $lastDecision . ".";
    }
    if ($lastStage !== '' && $lastStage !== 'initial_sent') {
        $classificationNote .= "\nLa conversación está en estado: " . $lastStage . ". Responde de forma coherente con esta etapa.";
    }
    if ($lastStrategy !== '') {
        $classificationNote .= "\nEstrategia psicológica activa: " . $lastStrategy . ". Aplica esta estrategia en tu respuesta de forma sutil.";
    }

    $baseRules = "Eres una persona humana real, NUNCA digas que eres un bot, IA, asistente virtual o sistema automatizado. Si te preguntan, di que eres una persona del equipo y cambia de tema con naturalidad. Escribe SOLO una respuesta breve y natural en español. No uses markdown. Máximo 420 caracteres. Tono cercano. CTA suave. No inventes datos. NO modifiques precios, URLs, porcentajes ni condiciones económicas — mantenlos EXACTAMENTE igual que en el mensaje de referencia." . $greetingOnlyNote . $classificationNote;

    return trim(
        ($iaContext !== '' ? $iaContext . "\n\n" : '') .
        "Proceso: " . ($processSlug !== '' ? $processSlug : 'inbound') . "\n" .
        "Objetivo: " . ($objective !== '' ? $objective : 'avanzar conversación hacia cierre sin sonar robótico') . "\n\n" .
        "Historial reciente:\n" . (empty($lines) ? "(sin historial útil)" : implode("\n", $lines)) . "\n\n" .
        "Ejemplos humanos que funcionaron:\n" . (empty($examplesLines) ? "(sin ejemplos guardados)" : implode("\n", $examplesLines)) . "\n\n" .
        "Reglas: " . $baseRules
    );
}

function comercial_ai_generate_contextual_followup($thread, $processSlug, $objective) {
    if (!function_exists('publicista_openai_json_request') || !function_exists('publicista_response_output_text') || !function_exists('publicista_ai_config')) {
        return array('ok' => false, 'error' => 'ai_utilities_unavailable');
    }
    $cfg = publicista_ai_config();
    if (empty($cfg['configured'])) {
        return array('ok' => false, 'error' => 'ai_not_configured');
    }

    $prompt = comercial_build_contextual_followup_prompt($thread, $processSlug, $objective);
    $model = trim((string)($cfg['descriptor_model'] ?? 'gpt-5.4-mini'));
    $payload = array(
        'model' => $model,
        'input' => $prompt,
        'max_output_tokens' => 180,
    );
    $resp = publicista_openai_json_request('/v1/responses', $payload, (int)($cfg['timeouts']['responses'] ?? 90));
    if (empty($resp['ok'])) {
        return array('ok' => false, 'error' => trim((string)($resp['error'] ?? 'ai_request_failed')));
    }
    $text = trim((string)publicista_response_output_text((array)($resp['decoded'] ?? array())));
    if ($text === '') {
        return array('ok' => false, 'error' => 'ai_empty_output');
    }
    if (comercial_safe_len($text) > 420) {
        $text = function_exists('mb_substr') ? trim((string)mb_substr($text, 0, 420, 'UTF-8')) : trim(substr($text, 0, 420));
    }
    return array('ok' => true, 'text' => $text, 'model' => $model);
}

function comercial_decision_allows_ai_second_turn($thread, $classification, $process) {
    $thread = comercial_normalize_thread($thread);
    if ((string)$classification !== 'very_hot') return false;
    if ((string)($thread['status'] ?? 'open') !== 'open') return false;
    if ((int)($thread['replies_count'] ?? 0) < 1) return false;
    if (empty($process['auto_followup'])) return false;
    // qualified_reply_sent_at ya no es requisito: si el thread llega muy caliente
    // directamente (very_hot sin pasar por qualified), la IA debe responder igualmente.
    return true;
}

function comercial_inbound_has_risk_phrase($text) {
    $text = strtolower(trim((string)$text));
    if ($text === '') return false;
    foreach (array('denuncia', 'policia', 'estafa', 'fraude', 'abogado', 'amenaza', 'reclamacion', 'reclamación', 'devolucion', 'devolución', 'pago') as $needle) {
        if (strpos($text, $needle) !== false) return true;
    }
    return false;
}

function comercial_decision_score_confidence($thread, $classification, $text) {
    $text = trim((string)$text);
    $score = 0.45;
    if ($classification === 'very_hot') $score += 0.35;
    if ($classification === 'qualified') $score += 0.25;
    if (comercial_safe_len($text) >= 18) $score += 0.10;
    if (preg_match('/\b(precio|zona|horario|cuando|cu[aá]ndo|ubicaci[oó]n|interesa|donde|d[oó]nde|direcci[oó]n|sitio|localizaci[oó]n)\b/ui', $text)) $score += 0.12;
    if (comercial_inbound_has_risk_phrase($text)) $score -= 0.45;
    return max(0, min(1, $score));
}

/**
 * Fix Bug 3: Helper para decidir si se puede enviar un auto-followup.
 * Retorna true si:
 *   - Quedan turnos automáticos disponibles (auto_turn_count < maxTurns), O
 *   - La última respuesta fue hace más de 2h (ventana de reenganche).
 * Fix #4: antes solo aplicaba la ventana de 2h, bloqueando el segundo turno
 * aunque el proceso tuviera configurado conversation_max_auto_turns > 1.
 * Fix Bug 3c (v2): ahora siempre consulta auto_turn_count, incluso si
 * qualified_reply_sent_at está vacío (primer turno: auto_turn_count=0 < maxTurns siempre).
 */
function comercial_can_send_auto_followup($thread, $maxTurns = 999) {
    $sentAt = trim((string)($thread['qualified_reply_sent_at'] ?? ''));
    // Si todavía quedan turnos automáticos, permitir el seguimiento inmediatamente
    if ((int)($thread['auto_turn_count'] ?? 0) < (int)$maxTurns) return true;
    // Nunca se ha respondido → permitir el primer turno (auto_turn_count = 0 < maxTurns ya lo cubre)
    if ($sentAt === '') return true;
    // Turnos agotados → solo reenganchar tras 2h de silencio
    return strtotime($sentAt) < (time() - 7200);
}

function comercial_decide_inbound_action($thread, $process, $classification, $text) {
    $thread = comercial_normalize_thread($thread);
    $settings = comercial_get_settings();
    $maxTurns = max(1, (int)($process['conversation_max_auto_turns'] ?? $settings['conversation_max_auto_turns'] ?? 2));
    $confidence = comercial_decision_score_confidence($thread, $classification, $text);
    $risk = comercial_inbound_has_risk_phrase($text);

    // Fix Bug 3c (v2): el reset de 24h se hace en el handler
    // (comercial_handle_inbound_message) para que el cambio se persista.
    // Aquí solo consultamos el valor ya actualizado.

    if (empty($settings['ia_second_turn_enabled']) && empty($process['ia_learning_enabled'])) {
        return array('action' => 'auto_reply_second_turn', 'confidence' => $confidence, 'risk' => $risk, 'reason' => 'legacy_mode_reply');
    }
    if ($risk) {
        return array('action' => 'escalate_human', 'confidence' => $confidence, 'risk' => true, 'reason' => 'risk_phrase');
    }
    if ($thread['auto_turn_count'] >= $maxTurns) {
        return array('action' => 'escalate_human', 'confidence' => $confidence, 'risk' => false, 'reason' => 'max_auto_turns_reached');
    }
    if ($classification === 'negative') {
        return array('action' => 'defer', 'confidence' => $confidence, 'risk' => false, 'reason' => 'negative_intent_defer');
    }
    if (in_array((string)$classification, array('responded', 'very_hot', 'qualified', 'greeting', 'curious'), true)) {
        return array('action' => 'auto_reply_second_turn', 'confidence' => $confidence, 'risk' => false, 'reason' => 'eligible');
    }
    return array('action' => 'auto_reply_second_turn', 'confidence' => $confidence, 'risk' => false, 'reason' => 'default_reply');
}

function comercial_webhook_log_matches_thread($thread, $log) {
    $thread = comercial_normalize_thread($thread);
    $log = is_array($log) ? $log : array();
    $payload = isset($log['payload']) && is_array($log['payload']) ? $log['payload'] : array();

    $threadId = trim((string)($thread['id'] ?? ''));
    $targetPhone = comercial_only_digits((string)($thread['target_phone'] ?? ''));
    $linePhone = comercial_only_digits((string)($thread['line_phone'] ?? ''));
    $threadTestKey = trim((string)($thread['test_key'] ?? ''));

    if ($threadId !== '' && trim((string)($payload['thread_id'] ?? '')) === $threadId) {
        return true;
    }
    if ($threadTestKey !== '' && trim((string)($payload['test_key'] ?? '')) === $threadTestKey) {
        return true;
    }

    $from = comercial_only_digits((string)($payload['from'] ?? ''));
    $to = comercial_only_digits((string)($payload['to'] ?? ''));
    $logTarget = comercial_only_digits((string)($payload['target_phone'] ?? ''));

    if ($targetPhone !== '' && (comercial_phone_matches($from, $targetPhone) || comercial_phone_matches($logTarget, $targetPhone))) {
        if ($linePhone === '' || $to === '' || comercial_phone_matches($to, $linePhone)) {
            return true;
        }
    }

    return false;
}

function comercial_thread_webhook_log_dedupe_key($row) {
    $row = is_array($row) ? $row : array();
    $type = trim((string)($row['type'] ?? ''));
    if ($type === '') {
        return '';
    }
    $payload = isset($row['payload']) && is_array($row['payload']) ? $row['payload'] : array();
    $messageId = trim((string)($payload['message_id'] ?? ''));
    $from = comercial_phone_identity((string)($payload['from'] ?? ''));
    $to = comercial_phone_identity((string)($payload['to'] ?? ''));
    $textPreview = trim((string)($payload['text_preview'] ?? ''));
    $classification = trim((string)($payload['classification'] ?? ''));
    $action = trim((string)($payload['action'] ?? ''));
    return $type . '|' . $messageId . '|' . $from . '|' . $to . '|' . $textPreview . '|' . $classification . '|' . $action;
}

function comercial_thread_webhook_log($thread, $limit = 60) {
    $thread = comercial_normalize_thread($thread);
    $rows = array_reverse(comercial_webhook_logs_recent($limit * 4));
    $out = array();
    $seen = array();
    foreach ($rows as $row) {
        if (comercial_webhook_log_matches_thread($thread, $row)) {
            $dedupeKey = comercial_thread_webhook_log_dedupe_key($row);
            if ($dedupeKey !== '' && isset($seen[$dedupeKey])) {
                continue;
            }
            if ($dedupeKey !== '') {
                $seen[$dedupeKey] = true;
            }
            $out[] = $row;
        }
    }
    return array_slice($out, -1 * max(1, (int)$limit));
}

function comercial_render_webhook_log_detail($payload) {
    $payload = is_array($payload) ? $payload : array();
    $parts = array();
    if (trim((string)($payload['classification'] ?? '')) !== '') {
        $parts[] = 'clasificación: ' . (string)$payload['classification'];
    }
    if (trim((string)($payload['intent_reason'] ?? '')) !== '') {
        $parts[] = 'motivo: ' . (string)$payload['intent_reason'];
    }
    if (trim((string)($payload['action'] ?? '')) !== '') {
        $parts[] = 'acción: ' . (string)$payload['action'];
    }
    if (trim((string)($payload['error'] ?? '')) !== '') {
        $parts[] = 'error: ' . (string)$payload['error'];
    }
    if (trim((string)($payload['followup_error'] ?? '')) !== '') {
        $parts[] = 'error followup: ' . (string)$payload['followup_error'];
    }
    if (trim((string)($payload['text'] ?? '')) !== '') {
        $parts[] = 'texto: ' . (string)$payload['text'];
    } elseif (trim((string)($payload['text_preview'] ?? '')) !== '') {
        $parts[] = 'texto: ' . (string)$payload['text_preview'];
    }
    if ((int)($payload['http_status'] ?? 0) > 0) {
        $parts[] = 'HTTP ' . (string)((int)$payload['http_status']);
    }
    if (trim((string)($payload['php_error_message'] ?? '')) !== '') {
        $parts[] = 'PHP: ' . (string)$payload['php_error_message'];
    }
    return implode(' · ', $parts);
}

function comercial_test_probe_phone() {
    return '654464023';
}

function comercial_test_probe_process_slug() {
    return 'plaza';
}

function comercial_test_probe_key() {
    return 'comercial_probe_plaza';
}

function comercial_thread_is_test_probe($thread) {
    $thread = is_array($thread) ? $thread : array();
    return !empty($thread['test_probe']) || trim((string)($thread['test_key'] ?? '')) === comercial_test_probe_key();
}

function comercial_find_test_probe_thread() {
    foreach (comercial_get_threads() as $thread) {
        if (comercial_thread_is_test_probe($thread)) {
            return $thread;
        }
    }
    return null;
}

function comercial_test_probe_summary() {
    $thread = comercial_find_test_probe_thread();
    if (!$thread) {
        return array(
            'exists' => false,
            'phone' => comercial_test_probe_phone(),
        );
    }

    return array(
        'exists' => true,
        'thread' => $thread,
        'history' => comercial_thread_history($thread, 300),
        'phone' => comercial_test_probe_phone(),
    );
}

function comercial_filter_events_without_probe($events, $deletedThreadIds = array()) {
    $out = array();
    $probePhone = comercial_test_probe_phone();
    $probeSlug = comercial_test_probe_process_slug();
    $probeKey = comercial_test_probe_key();
    $deletedThreadIds = array_values(array_filter(array_map('strval', (array)$deletedThreadIds)));

    foreach ((array)$events as $event) {
        $event = is_array($event) ? $event : array();
        $payload = isset($event['payload']) && is_array($event['payload']) ? $event['payload'] : array();
        $isProbe = !empty($payload['test_probe'])
            || trim((string)($payload['test_key'] ?? '')) === $probeKey
            || in_array(trim((string)($payload['thread_id'] ?? '')), $deletedThreadIds, true)
            || (
                comercial_only_digits((string)($payload['target_phone'] ?? '')) === $probePhone
                && trim((string)($payload['process_slug'] ?? '')) === $probeSlug
            );
        if ($isProbe) {
            continue;
        }
        $out[] = $event;
    }

    return $out;
}

function comercial_filter_webhook_logs_without_probe($rows, $deletedThreadIds = array()) {
    $out = array();
    $probePhone = comercial_test_probe_phone();
    $probeKey = comercial_test_probe_key();
    $deletedThreadIds = array_values(array_filter(array_map('strval', (array)$deletedThreadIds)));

    foreach ((array)$rows as $row) {
        $row = is_array($row) ? $row : array();
        $payload = isset($row['payload']) && is_array($row['payload']) ? $row['payload'] : array();
        $isProbe = !empty($payload['test_probe'])
            || trim((string)($payload['test_key'] ?? '')) === $probeKey
            || in_array(trim((string)($payload['thread_id'] ?? '')), $deletedThreadIds, true)
            || comercial_only_digits((string)($payload['target_phone'] ?? '')) === $probePhone
            || comercial_only_digits((string)($payload['from'] ?? '')) === $probePhone;
        if ($isProbe) {
            continue;
        }
        $out[] = $row;
    }

    return $out;
}

function comercial_save_events_rows($rows) {
    $path = DATA_PATH . '/comercial_events.jsonl';
    $lines = array();
    foreach ((array)$rows as $row) {
        if (!is_array($row)) continue;
        $lines[] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    file_put_contents($path, implode(PHP_EOL, $lines) . (empty($lines) ? '' : PHP_EOL));
}

function comercial_reset_test_probe() {
    $threads = comercial_get_threads();
    $keptThreads = array();
    $deletedThreadIds = array();
    $probePhone = comercial_test_probe_phone();
    foreach ($threads as $thread) {
        $isProbeRelated = comercial_thread_is_test_probe($thread)
            || comercial_phone_matches((string)($thread['target_phone'] ?? ''), $probePhone);
        if ($isProbeRelated) {
            $deletedThreadIds[] = trim((string)($thread['id'] ?? ''));
            continue;
        }
        $keptThreads[] = $thread;
    }
    comercial_save_threads($keptThreads);

    $leads = comercial_get_leads();
    $keptLeads = array();
    foreach ($leads as $lead) {
        if (in_array(trim((string)($lead['thread_id'] ?? '')), $deletedThreadIds, true)
            || comercial_phone_matches((string)($lead['telefono'] ?? ''), $probePhone)) {
            continue;
        }
        $keptLeads[] = $lead;
    }
    storage_write('comercial_leads.json', array_values($keptLeads));

    $avisos = storage_read('avisos.json');
    $keptAvisos = array();
    foreach ((array)$avisos as $aviso) {
        $sourceKey = trim((string)($aviso['source_key'] ?? ''));
        $message = trim((string)($aviso['message'] ?? ''));
        $isProbeAviso = strpos($sourceKey, 'comercial_reply_') === 0
            && (
                strpos($message, $probePhone) !== false
                || strpos($message, comercial_phone_identity($probePhone)) !== false
            );
        if ($isProbeAviso) {
            continue;
        }
        $keptAvisos[] = $aviso;
    }
    storage_write('avisos.json', array_values($keptAvisos));

    $path = DATA_PATH . '/comercial_events.jsonl';
    if (is_file($path)) {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $events = array();
        foreach ((array)$lines as $line) {
            $row = json_decode((string)$line, true);
            if (is_array($row)) $events[] = $row;
        }
        comercial_save_events_rows(comercial_filter_events_without_probe($events, $deletedThreadIds));
    }

    $webhookPath = comercial_webhook_log_path();
    if (is_file($webhookPath)) {
        $lines = @file($webhookPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $rows = array();
        foreach ((array)$lines as $line) {
            $row = json_decode((string)$line, true);
            if (is_array($row)) $rows[] = $row;
        }
        comercial_save_webhook_log_rows(comercial_filter_webhook_logs_without_probe($rows, $deletedThreadIds));
    }

    return array(
        'threads_deleted' => count($deletedThreadIds),
        'leads_deleted' => max(0, count($leads) - count($keptLeads)),
        'avisos_deleted' => max(0, count($avisos) - count($keptAvisos)),
    );
}

function comercial_first_nonempty_value($values) {
    foreach ((array)$values as $value) {
        if (is_array($value)) {
            continue;
        }
        $value = trim((string)$value);
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function comercial_request_headers_lower() {
    $headers = array();
    if (function_exists('getallheaders')) {
        foreach ((array)getallheaders() as $key => $value) {
            $headers[strtolower((string)$key)] = trim((string)$value);
        }
    }

    foreach ($_SERVER as $key => $value) {
        if (strpos((string)$key, 'HTTP_') !== 0) {
            continue;
        }
        $label = strtolower(str_replace('_', '-', substr((string)$key, 5)));
        if (!isset($headers[$label])) {
            $headers[$label] = trim((string)$value);
        }
    }

    return $headers;
}

function comercial_webhook_seen_ids() {
    $rows = storage_read('comercial_webhook_seen.json');
    if (!is_array($rows)) {
        return array();
    }
    return array_values(array_filter(array_map('strval', $rows)));
}

function comercial_webhook_seen_file_path() {
    return DATA_PATH . '/comercial_webhook_seen.json';
}

function comercial_webhook_seen_lock_path() {
    return DATA_PATH . '/comercial_webhook_seen.lock';
}

function comercial_webhook_seen_ids_trimmed($rows) {
    $rows = array_values(array_unique(array_filter(array_map('strval', (array)$rows))));
    if (count($rows) > 500) {
        $rows = array_slice($rows, -500);
    }
    return $rows;
}

function comercial_webhook_claim_message($messageId) {
    $messageId = trim((string)$messageId);
    if ($messageId === '') {
        return true;
    }

    $lockPath = comercial_webhook_seen_lock_path();
    $lock = @fopen($lockPath, 'c+');
    if ($lock === false) {
        $rows = comercial_webhook_seen_ids();
        if (in_array($messageId, $rows, true)) {
            return false;
        }
        $rows[] = $messageId;
        storage_write('comercial_webhook_seen.json', comercial_webhook_seen_ids_trimmed($rows));
        return true;
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            return false;
        }

        $rows = array();
        $seenPath = comercial_webhook_seen_file_path();
        if (is_file($seenPath)) {
            $decoded = json_decode((string)@file_get_contents($seenPath), true);
            if (is_array($decoded)) {
                $rows = $decoded;
            }
        }

        $rows = comercial_webhook_seen_ids_trimmed($rows);
        if (in_array($messageId, $rows, true)) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }

        $rows[] = $messageId;
        $rows = comercial_webhook_seen_ids_trimmed($rows);
        file_put_contents($seenPath, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        flock($lock, LOCK_UN);
        fclose($lock);
        return true;
    } catch (Throwable $e) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
        throw $e;
    }
}

function comercial_webhook_mark_seen($messageId) {
    $messageId = trim((string)$messageId);
    if ($messageId === '') {
        return;
    }
    comercial_webhook_claim_message($messageId);
}

// ── Thread-level inbound lock (avoid duplicate auto-replies on burst messages) ──

function comercial_thread_inbound_lock_path($threadId) {
    $threadId = trim((string)$threadId);
    $safe = $threadId !== '' ? md5($threadId) : 'unknown';
    return DATA_PATH . '/comercial_thread_locks/' . $safe . '.lock';
}

/**
 * Try to acquire an exclusive, non-blocking lock for a thread's inbound processing.
 * Returns a file handle on success, or false if another request is already processing
 * the same thread (or if the lock file is not accessible).
 */
function comercial_thread_acquire_inbound_lock($threadId) {
    $lockPath = comercial_thread_inbound_lock_path($threadId);
    $dir = dirname($lockPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $lock = @fopen($lockPath, 'c+');
    if ($lock === false) {
        return false;
    }
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return false;
    }
    // Write a small marker so we can see which PID holds the lock (debug aid)
    @ftruncate($lock, 0);
    @fwrite($lock, json_encode(array('pid' => getmypid(), 'ts' => time(), 'thread' => $threadId)));
    @fflush($lock);
    return $lock;
}

function comercial_thread_release_inbound_lock($lockHandle) {
    if (!$lockHandle || !is_resource($lockHandle)) {
        return;
    }
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
}

function comercial_webhook_extract_payload($request = array()) {
    $request = is_array($request) ? $request : array();
    $body = isset($request['body']) && is_array($request['body']) ? $request['body'] : $request;
    $payload = isset($body['payload']) && is_array($body['payload']) ? $body['payload'] : $body;
    $data = isset($payload['_data']) && is_array($payload['_data']) ? $payload['_data'] : array();
    $dataId = isset($data['id']) && is_array($data['id']) ? $data['id'] : array();
    $dataInfo = isset($data['Info']) && is_array($data['Info']) ? $data['Info'] : array();
    $me = isset($body['me']) && is_array($body['me']) ? $body['me'] : array();

    $from = comercial_first_nonempty_value(array(
        $request['from'] ?? '',
        $request['from_phone'] ?? '',
        $dataInfo['SenderAlt'] ?? '',
        $dataInfo['Sender'] ?? '',
        $payload['participant'] ?? '',
        $data['participant'] ?? '',
        $dataId['participant'] ?? '',
        $body['from'] ?? '',
        $payload['from'] ?? '',
        $payload['chatId'] ?? '',
        $payload['author'] ?? '',
        $data['from'] ?? '',
        $data['author'] ?? '',
        $dataId['remote'] ?? '',
    ));

    $rawFrom = trim((string)$from);
    $rawFrom = preg_replace('/:\d+@/', '@', $rawFrom);
    $isStatusBroadcast = stripos($rawFrom, 'status@broadcast') !== false
        || stripos((string)($dataInfo['Chat'] ?? ''), 'status@broadcast') !== false;
    $isGroupMessage = !empty($dataInfo['IsGroup']) && !$isStatusBroadcast;

    $to = comercial_first_nonempty_value(array(
        $request['to'] ?? '',
        $request['to_phone'] ?? '',
        $dataInfo['RecipientAlt'] ?? '',
        $body['to'] ?? '',
        $body['chatId'] ?? '',
        $payload['to'] ?? '',
        $me['id'] ?? '',
        $data['to'] ?? '',
    ));
    $to = preg_replace('/:\d+@/', '@', $to);

    $text = comercial_first_nonempty_value(array(
        $request['text'] ?? '',
        $request['message'] ?? '',
        $request['body_text'] ?? '',
        $body['text'] ?? '',
        $body['message'] ?? '',
        $body['body'] ?? '',
        $payload['text'] ?? '',
        $payload['body'] ?? '',
        $payload['message'] ?? '',
        $payload['caption'] ?? '',
        $data['body'] ?? '',
        $data['caption'] ?? '',
    ));

    $messageId = comercial_first_nonempty_value(array(
        $request['message_id'] ?? '',
        $body['message_id'] ?? '',
        $payload['message_id'] ?? '',
        $payload['messageId'] ?? '',
        $dataInfo['ID'] ?? '',
        $payload['id'] ?? '',
        $dataId['_serialized'] ?? '',
        $dataId['id'] ?? '',
    ));

    $linePort = comercial_first_nonempty_value(array(
        $request['port'] ?? '',
        $request['waha_port'] ?? '',
        $body['port'] ?? '',
        $payload['port'] ?? '',
    ));

    $fromMe = !empty($body['fromMe']) || !empty($payload['fromMe']) || !empty($data['fromMe']);

    return array(
        'from' => comercial_only_digits($from),
        'to' => comercial_only_digits($to),
        'text' => $text,
        'port' => trim((string)$linePort),
        'message_id' => $messageId,
        'from_me' => $fromMe ? 1 : 0,
        'is_status_broadcast' => $isStatusBroadcast ? 1 : 0,
        'is_group' => $isGroupMessage ? 1 : 0,
        'raw' => $body,
    );
}

function comercial_request_looks_like_waha_webhook($body = array(), $headers = array()) {
    $body = is_array($body) ? $body : array();
    $headers = is_array($headers) ? $headers : array();

    $hasWahaHeader = !empty($headers['x-webhook-request-id']) || !empty($headers['x-webhook-timestamp']) || !empty($headers['x-webhook-hmac']);
    $hasWahaShape = !empty($body['event']) || !empty($body['session']) || !empty($body['payload']) || !empty($body['me']);
    $payload = isset($body['payload']) && is_array($body['payload']) ? $body['payload'] : array();
    $hasPayloadFields = !empty($payload['body']) || !empty($payload['from']) || !empty($payload['_data']);

    return $hasWahaHeader || ($hasWahaShape && $hasPayloadFields);
}

function comercial_webhook_payload_log_context($payload) {
    $payload = is_array($payload) ? $payload : array();
    return array(
        'from' => comercial_only_digits((string)($payload['from'] ?? '')),
        'to' => comercial_only_digits((string)($payload['to'] ?? '')),
        'port' => trim((string)($payload['port'] ?? '')),
        'message_id' => trim((string)($payload['message_id'] ?? '')),
        'text_preview' => voice_safe_substr(trim((string)($payload['text'] ?? '')), 0, 400),
    );
}

function comercial_handle_webhook_http() {
    try {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'GET') {
            voice_json_response(array(
                'ok' => true,
                'service' => 'comercial_webhook',
                'message' => 'Webhook comercial activo.',
            ));
        }

        $headers = comercial_request_headers_lower();
        $raw = isset($GLOBALS['comercial_webhook_raw_body']) ? (string)$GLOBALS['comercial_webhook_raw_body'] : file_get_contents('php://input');
        $decoded = json_decode((string)$raw, true);
        $body = is_array($decoded) ? $decoded : array();
        if (empty($body) && !empty($_POST)) {
            $body = $_POST;
        }
        $settings = comercial_get_settings();
        $expectedKey = trim((string)($settings['waha_api_key'] ?? ''));
        $providedKey = comercial_first_nonempty_value(array(
            $headers['x-api-key'] ?? '',
            $headers['authorization'] ?? '',
            $_GET['key'] ?? '',
            $_POST['key'] ?? '',
        ));
        if (stripos($providedKey, 'Bearer ') === 0) {
            $providedKey = trim(substr($providedKey, 7));
        }

        if ($expectedKey !== '' && $providedKey !== $expectedKey) {
            $payloadLog = array(
                'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                'expected_key' => $expectedKey !== '' ? 'configured' : 'empty',
                'provided_key' => $providedKey !== '' ? 'present' : 'missing',
            );
            if (!comercial_request_looks_like_waha_webhook($body, $headers)) {
                comercial_event_append('webhook_auth_failed', $payloadLog);
                comercial_webhook_log_append('auth_failed', $payloadLog + array('http_status' => 401));
                voice_json_response(array('ok' => false, 'error' => 'unauthorized'), 401);
            }
            comercial_event_append('webhook_auth_bypassed_waha', $payloadLog);
            comercial_webhook_log_append('auth_bypassed_waha', $payloadLog + array('http_status' => 200));
        }

        $payload = comercial_webhook_extract_payload($body);
        $logContext = comercial_webhook_payload_log_context($payload);

        if (!empty($payload['from_me'])) {
            comercial_webhook_log_append('received_parsed', $logContext);
            comercial_webhook_log_append('ignored_from_me', $logContext + array('http_status' => 200));
            voice_json_response(array('ok' => true, 'ignored' => 'from_me'));
        }

        if (!empty($payload['is_status_broadcast']) || !empty($payload['is_group'])) {
            $ignoreReason = !empty($payload['is_status_broadcast']) ? 'status_broadcast' : 'group_message';
            comercial_webhook_log_append('received_parsed', $logContext);
            comercial_webhook_log_append('ignored_non_direct_message', $logContext + array(
                'reason' => $ignoreReason,
                'http_status' => 200,
            ));
            voice_json_response(array('ok' => true, 'ignored' => $ignoreReason));
        }

        $messageId = trim((string)($payload['message_id'] ?? ''));
        if ($messageId !== '' && !comercial_webhook_claim_message($messageId)) {
            comercial_event_append('webhook_duplicate', array('message_id' => $messageId));
            comercial_webhook_log_append('duplicate', $logContext + array('http_status' => 200));
            voice_json_response(array('ok' => true, 'duplicate' => true));
        }

        comercial_webhook_log_append('received_parsed', $logContext);

        // ── T4.3: validar from_me para detectar mensajes manuales no reportados ──
        // Si WAHA no marca from_me=true pero el remitente es una de nuestras líneas,
        // podría indicar que los mensajes manuales desde WhatsApp no se detectan bien.
        if (empty($payload['from_me'])) {
            $fromPhone = comercial_only_digits((string)($payload['from'] ?? ''));
            if ($fromPhone !== '') {
                $line = comercial_find_line_for_inbound($fromPhone, (string)($payload['port'] ?? ''));
                if ($line) {
                    comercial_event_append('webhook_from_me_mismatch', array(
                        'from' => $fromPhone,
                        'line_id' => (string)($line['id'] ?? ''),
                        'line_name' => (string)($line['nombre'] ?? ''),
                        'message_id' => $messageId,
                        'text_preview' => voice_safe_substr(trim((string)($payload['text'] ?? '')), 0, 100),
                    ));
                    comercial_webhook_log_append('from_me_mismatch_warning', $logContext + array(
                        'line_id' => (string)($line['id'] ?? ''),
                        'line_name' => (string)($line['nombre'] ?? ''),
                        'note' => 'WAHA did not set from_me=true for a message sent from our own line',
                    ));
                }
            }
        }

        $result = comercial_handle_inbound_message($payload);

        if (!empty($result['ok'])) {
            $successPayload = array(
                'message_id' => $messageId,
                'from' => (string)($payload['from'] ?? ''),
                'to' => (string)($payload['to'] ?? ''),
                'classification' => (string)($result['classification'] ?? ''),
                'action' => (string)($result['action'] ?? ''),
            );
            if (!empty($result['ignored'])) {
                // Mensaje ignorado benignamente (sin texto, sin remitente, etc.)
                comercial_event_append('webhook_inbound_ignored', $successPayload + array('ignored' => $result['ignored']));
                comercial_webhook_log_append('ignored', $logContext + array(
                    'ignored_reason' => (string)$result['ignored'],
                    'http_status' => 200,
                ));
                voice_json_response(array('ok' => true) + $result);
            }
            comercial_event_append('webhook_inbound_processed', $successPayload);
            comercial_webhook_log_append('processed', $logContext + array(
                'thread_id' => (string)($result['thread_id'] ?? ''),
                'classification' => (string)($result['classification'] ?? ''),
                'intent_reason' => (string)($result['intent_reason'] ?? ''),
                'action' => (string)($result['action'] ?? ''),
                'followup_error' => (string)($result['followup_error'] ?? ''),
                'http_status' => 200,
                'target_phone' => (string)($result['target_phone'] ?? ($payload['from'] ?? '')),
                'test_probe' => !empty($result['test_probe']) ? 1 : 0,
                'test_key' => (string)($result['test_key'] ?? ''),
            ));
            voice_json_response(array('ok' => true) + $result);
        }

        $errorPayload = array(
            'message_id' => $messageId,
            'from' => (string)($payload['from'] ?? ''),
            'to' => (string)($payload['to'] ?? ''),
            'error' => (string)($result['error'] ?? 'unknown_error'),
        );
        comercial_event_append('webhook_inbound_error', $errorPayload);
        comercial_webhook_log_append('processing_error', $logContext + array(
            'thread_id' => (string)($result['thread_id'] ?? ''),
            'classification' => (string)($result['classification'] ?? ''),
            'intent_reason' => (string)($result['intent_reason'] ?? ''),
            'action' => (string)($result['action'] ?? ''),
            'error' => (string)($result['error'] ?? 'unknown_error'),
            'followup_error' => (string)($result['followup_error'] ?? ''),
            'http_status' => 422,
            'target_phone' => (string)($result['target_phone'] ?? ($payload['from'] ?? '')),
            'test_probe' => !empty($result['test_probe']) ? 1 : 0,
            'test_key' => (string)($result['test_key'] ?? ''),
        ));
        voice_json_response(array('ok' => false) + $result, 422);
    } catch (Throwable $e) {
        comercial_webhook_log_append('processing_exception', array(
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => (int)$e->getLine(),
            'http_status' => 500,
        ));
        voice_json_response(array('ok' => false, 'error' => 'webhook_exception', 'message' => $e->getMessage()), 500);
    }
}

function comercial_find_line_for_inbound($toPhone = '', $linePort = '') {
    $toPhone = comercial_only_digits($toPhone);
    $linePort = trim((string)$linePort);

    foreach (comercial_list_lines() as $line) {
        $linePhone = comercial_only_digits((string)($line['tfono'] ?? ''));
        $port = trim((string)($line['waha_port'] ?? ''));
        if ($toPhone !== '' && $linePhone !== '' && comercial_phone_matches($linePhone, $toPhone)) {
            return $line;
        }
        if ($linePort !== '' && $port !== '' && $port === $linePort) {
            return $line;
        }
    }

    return null;
}

function comercial_register_unmatched_inbound_thread($payload) {
    $payload = is_array($payload) ? $payload : array();
    $fromPhone = comercial_only_digits((string)($payload['from'] ?? ''));
    $text = trim((string)($payload['text'] ?? ''));
    $line = comercial_find_line_for_inbound((string)($payload['to'] ?? ''), (string)($payload['port'] ?? ''));

    $thread = comercial_normalize_thread(array(
        'process_id' => '',
        'process_slug' => 'inbound',
        'line_id' => (string)($line['id'] ?? ''),
        'line_phone' => comercial_only_digits((string)($line['tfono'] ?? ($payload['to'] ?? ''))),
        'target_phone' => $fromPhone,
        'source_ref' => 'webhook_unmatched',
        'source_payload' => (array)($payload['raw'] ?? array()),
        'stage' => 'opened',
        'status' => 'open',
        'messages_sent_count' => 0,
        'replies_count' => 1,
        'last_inbound_text' => $text,
        'last_contact_at' => now_datetime(),
        'updated_at' => now_datetime(),
        'created_at' => now_datetime(),
    ));
    comercial_upsert_thread($thread);
    comercial_event_append('reply_received', array(
        'thread_id' => (string)$thread['id'],
        'process_slug' => 'inbound',
        'target_phone' => $fromPhone,
        'line_id' => (string)($thread['line_id'] ?? ''),
        'classification' => 'unmatched',
        'text' => $text,
    ));
    return $thread;
}

function comercial_text_fold($text) {
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    $text = comercial_mb_strtolower_safe($text);
    $text = strtr($text, array(
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n',
    ));
    $text = preg_replace('/[^\pL\pN\s]+/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string)$text);
}

// ── Fix #1: similitud de texto para detección de duplicados ──
function comercial_text_similarity($a, $b) {
    $a = comercial_text_fold($a);
    $b = comercial_text_fold($b);
    if ($a === '' || $b === '') return 0;
    if ($a === $b) return 1.0;

    // Jaccard simple sobre palabras
    $wordsA = array_unique(array_filter(explode(' ', $a), function($w) { return $w !== ''; }));
    $wordsB = array_unique(array_filter(explode(' ', $b), function($w) { return $w !== ''; }));
    if (empty($wordsA) || empty($wordsB)) return 0;

    $intersection = count(array_intersect($wordsA, $wordsB));
    $union = count(array_unique(array_merge($wordsA, $wordsB)));
    if ($union === 0) return 0;

    // También considerar longitud similar
    $lenRatio = min(comercial_safe_len($a), comercial_safe_len($b)) / max(comercial_safe_len($a), comercial_safe_len($b), 1);

    return ($intersection / $union) * 0.7 + $lenRatio * 0.3;
}

function comercial_phone_identity($value) {
    $digits = comercial_only_digits($value);
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) === 9) {
        return '34' . $digits;
    }
    return $digits;
}

function comercial_phone_matches($a, $b) {
    $na = comercial_phone_identity($a);
    $nb = comercial_phone_identity($b);
    return $na !== '' && $nb !== '' && $na === $nb;
}

function comercial_text_contains_keyword($haystack, $keyword) {
    $haystack = comercial_text_fold($haystack);
    $keyword = comercial_text_fold($keyword);
    if ($haystack === '' || $keyword === '') {
        return false;
    }
    if (preg_match('/^[a-z0-9]+$/', $keyword) && comercial_safe_len($keyword) <= 4) {
        return preg_match('/(?:^|\s)' . preg_quote($keyword, '/') . '(?:\s|$)/u', $haystack) === 1;
    }
    return comercial_mb_strpos_safe($haystack, $keyword) !== false;
}

function comercial_reply_is_negative_intent($normalizedText, $process) {
    $normalizedText = comercial_text_fold($normalizedText);
    if ($normalizedText === '') {
        return false;
    }

    $hardNegatives = array(
        'no me interesa',
        'no quiero',
        'no gracias',
        'dejame',
        'deja de escribir',
        'deja de molestar',
        'baja',
        'stop',
        'cancelar',
        'no busco',
        'numero equivocado',
        'te has equivocado',
        'equivocado',
        'nada gracias',
    );
    foreach ($hardNegatives as $phrase) {
        if (comercial_text_contains_keyword($normalizedText, $phrase)) {
            return true;
        }
    }

    foreach ((array)$process['negative_keywords'] as $kw) {
        $kw = trim((string)$kw);
        if ($kw === '') {
            continue;
        }
        if (comercial_text_fold($kw) === 'no') {
            continue;
        }
        if (comercial_text_contains_keyword($normalizedText, $kw)) {
            return true;
        }
    }

    return false;
}

function comercial_reply_positive_reason($text, $process) {
    $normalizedText = comercial_text_fold($text);
    if ($normalizedText === '') {
        return '';
    }

    $positivePhrases = array(
        'info',
        'mas info',
        'mas informacion',
        'dame info',
        'dame mas datos',
        'dame mas informacion',
        'pasame info',
        'pasame mas datos',
        'quiero info',
        'quiero mas info',
        'me interesa',
        'me interesa saber mas',
        'si dime',
        'si cuentame',
        'si explicame',
        'si dame datos',
        'si dame mas datos',
        'si mandame info',
        'como funciona',
        'quiero saber',
        'cuentame mas',
        'explicame mas',
        'mas datos',
        'mas detalles',
        'quiero detalles',
        'precio',
        'vale pasame',
        'dale',
        'dale dime',
        'dale pasame',
        'dale cuentame',
        'adelante',
        'claro',
        'perfecto',
        'genial',
        'vale dale',
        'ok dale',
        'cuentame',
        'mandame info',
        'mandame mas info',
        'informame',
        'pues dime',
        'si adelante',
        'si dame mas info',
        'si quiero',
        'si me interesa',
        'me gustaria saber mas',
        'mandame detalles',
        'quiero saber mas',
        'vale cuentame',
        'ok cuentame',
        'claro cuentame',
        'perfecto pasame info',
    );
    foreach ($positivePhrases as $phrase) {
        if (comercial_text_contains_keyword($normalizedText, $phrase)) {
            return 'intent:' . comercial_text_fold($phrase);
        }
    }

    $affirmatives = array('si', 'sí', 'vale', 'ok', 'claro', 'perfecto', 'genial', 'dale', 'adelante', 'va', 'venga', 'okey', 'bien');
    $interestWords = array('info', 'informacion', 'datos', 'detalles', 'cuentame', 'explicame', 'interesa', 'quiero', 'saber', 'pasame');
    $hasAffirmative = false;
    $hasInterest = false;
    foreach ($affirmatives as $word) {
        if (comercial_text_contains_keyword($normalizedText, $word)) {
            $hasAffirmative = true;
            break;
        }
    }
    foreach ($interestWords as $word) {
        if (comercial_text_contains_keyword($normalizedText, $word)) {
            $hasInterest = true;
            break;
        }
    }
    if ($hasAffirmative && $hasInterest) {
        return 'intent:affirmative_interest';
    }

    $shortAffirmatives = array('dale', 'vale', 'ok', 'okey', 'claro', 'perfecto', 'genial', 'adelante', 'venga', 'va');
    foreach ($shortAffirmatives as $word) {
        if ($normalizedText === comercial_text_fold($word)) {
            return 'intent:short_affirmative';
        }
    }

    foreach ((array)$process['positive_keywords'] as $kw) {
        $kw = trim((string)$kw);
        if ($kw !== '' && comercial_text_contains_keyword($normalizedText, $kw)) {
            return 'keyword:' . comercial_text_fold($kw);
        }
    }

    return '';
}

function comercial_reply_is_high_intent_after_followup($text) {
    $text = trim((string)$text);
    if ($text === '') {
        return false;
    }

    // Evitar marcar como very_hot respuestas demasiado cortas o ambiguas
    if (comercial_safe_len($text) < 10) {
        return false;
    }

    // ── Fix U1 (defensa en profundidad): detectar negación contextual ──
    // "no me interesa", "sin interés", "tampoco quiero info", "ni me llames" → NO es high intent.
    if (preg_match('/\b(no|sin|tampoco|ni|nada\s+de)\s+(me\s+interesa|quiero\s+info|quiero|pasame|p[aá]same|ll[aá]mame)\b/ui', $text)) {
        return false;
    }

    return (bool)preg_match('/\b(me interesa|quiero|quiero info|quiero empezar|agendar|agenda|llamame|ll[aá]mame|cuando puedo|cu[aá]ndo puedo|precio|cuanto|cu[aá]nto|horario|ubicaci[oó]n|d[oó]nde|direcci[oó]n|pasame|p[aá]same)\b/ui', $text);
}

/**
 * ── Fix U3: detector de auto-responders de WhatsApp Business ──
 * Detecta patrones comunes en mensajes automáticos de catálogo/ausencia:
 * tarifas estructuradas, mayúsculas sostenidas, palabras clave del sector,
 * y llegada muy rápida tras el envío inicial.
 */
function comercial_is_likely_autoresponder($text, $thread = array()) {
    $text = trim((string)$text);
    $thread = is_array($thread) ? $thread : array();
    if ($text === '') return false;

    $textLen = comercial_safe_len($text);

    // Criterio 1: texto largo con estructura de tarifas (€ + números + min/h)
    $hasTariffStructure = false;
    if ($textLen >= 80) {
        $hasCurrency = (bool)preg_match('/[€$]\s*\d+/u', $text);
        $hasTimeUnit = (bool)preg_match('/\d+\s*(min|minutos?|h|hora|horas)/ui', $text);
        $hasTariffStructure = $hasCurrency && $hasTimeUnit;
    }

    // Criterio 2: palabras clave de catálogo/auto-reply
    $autoReplyKeywords = array('disponible', 'disponibilidad', 'novedad', 'tarifas', 'tarifa',
        'salidas', 'ducha erótica', 'ducha erotica', 'servicios', 'completo',
        'beso negro', 'lluvia dorada', 'masaje', 'cachonda', 'cariñosa');
    $keywordHits = 0;
    foreach ($autoReplyKeywords as $kw) {
        if (stripos($text, $kw) !== false) $keywordHits++;
    }

    // Criterio 3: mayúsculas sostenidas (típico de auto-responders gritando NOVEDAD, DISPONIBLE)
    $upperRatio = 0;
    $alphaCount = preg_match_all('/[a-zA-ZáéíóúÁÉÍÓÚñÑ]/u', $text, $m);
    $upperCount = preg_match_all('/[A-ZÁÉÍÓÚÑ]/u', $text, $m2);
    if ($alphaCount > 10) {
        $upperRatio = $upperCount / $alphaCount;
    }

    // Criterio 4: llegada muy rápida (< 30s desde el envío inicial)
    $arrivedFast = false;
    $lastContactAt = trim((string)($thread['last_contact_at'] ?? ''));
    if ($lastContactAt !== '') {
        $lastContactTs = strtotime($lastContactAt);
        if ($lastContactTs > 0 && (time() - $lastContactTs) < 30) {
            $arrivedFast = true;
        }
    }

    // Ponderación: si cumple al menos 2 de 3 criterios fuertes, es auto-responder
    $strongSignals = 0;
    if ($hasTariffStructure) $strongSignals++;
    if ($keywordHits >= 2) $strongSignals++;
    if ($upperRatio >= 0.28 && $textLen >= 100) $strongSignals++;
    // Bonus: llegada rápida + cualquiera de los anteriores
    if ($arrivedFast && $strongSignals >= 1) return true;
    // Sin bonus de velocidad: al menos 2 señales fuertes
    if ($strongSignals >= 2) return true;

    return false;
}

function comercial_generic_inbound_process() {
    return array(
        'positive_keywords' => array('info', 'interesa', 'precio', 'como', 'cómo', 'vale', 'ok', 'si', 'sí', 'dale', 'claro', 'perfecto'),
        'negative_keywords' => array('baja', 'stop', 'nada', 'molestes', 'no me interesa', 'no quiero'),
        'auto_followup' => 0,
        'auto_create_lead' => 0,
    );
}

function comercial_create_reply_aviso($thread, $classification, $text, $intentReason = '', $messageId = '') {
    if (!function_exists('avisos_create_active')) {
        return;
    }

    $thread = comercial_normalize_thread($thread);
    $classification = trim((string)$classification);
    $text = trim((string)$text);

    // ── Fix #3: las conversaciones muy calientes NO generan aviso del sistema ──
    // Se notifican directamente al dueño via comercial_send_hot_summary_to_owner()
    if ($classification === 'very_hot') {
        return;
    }

    // ── Fix U3 (defensa): auto-responders NO generan aviso ──
    if ($classification === 'autoresponder') {
        return;
    }

    // ── T2.1: suprimir avisos hasta la segunda respuesta ──
    $settings = comercial_get_settings();
    if (!empty($settings['notify_only_after_second_reply']) && (int)($thread['replies_count'] ?? 0) < 2) {
        comercial_reply_aviso_register_suppressed($thread, $classification, $intentReason, $text, 'waiting_second_reply');
        return;
    }

    $dedupe = comercial_reply_aviso_dedupe_check($thread, $classification, $intentReason);
    if (!empty($dedupe['suppressed'])) {
        comercial_reply_aviso_register_suppressed($thread, $classification, $intentReason, $text, 'dedupe_window', $dedupe);
        return;
    }

    // Solo disparamos aviso inmediato para eventos de conversación de alto valor.
    // El resto se enruta al digest/resumen para no generar ruido operacional.
    if (!comercial_reply_aviso_is_high_value($classification, $intentReason)) {
        comercial_reply_aviso_register_suppressed($thread, $classification, $intentReason, $text, 'non_high_value');
        return;
    }
    $messageId = trim((string)$messageId);
    $sourceKey = 'comercial_reply_' . ($messageId !== '' ? $messageId : ((string)$thread['id'] . '_' . md5($text . '|' . (string)$thread['updated_at'])));
    $phone = (string)($thread['target_phone'] ?? '');
    $processSlug = trim((string)($thread['process_slug'] ?? ''));
    if ($processSlug === '') {
        $processSlug = 'inbound';
    }

    $severity = 'alta';
    $title = 'Comercial · Respuesta muy caliente';

    $message = 'Teléfono: ' . $phone
        . ' · proceso: ' . $processSlug
        . ' · estado: ' . comercial_thread_stage_label((string)($thread['stage'] ?? 'responded'))
        . "\nTexto: " . ($text !== '' ? $text : '-');
    if ($intentReason !== '') {
        $message .= "\nDetección: " . $intentReason;
    }

    avisos_create_active($title, $message, $severity, 'comercial', array(
        'thread_id' => (string)($thread['id'] ?? ''),
        'process_slug' => $processSlug,
        'target_phone' => $phone,
        'classification' => $classification,
        'intent_reason' => $intentReason,
        'message_id' => $messageId,
        'priority' => 'critical',
    ), false, $sourceKey);

    comercial_reply_aviso_mark_emitted($thread, $classification, $intentReason);
}

// ── Fix #3: notificación directa al dueño para conversaciones muy calientes ──
function comercial_send_hot_summary_to_owner($thread, $inboundText, $messageId = '') {
    $thread = comercial_normalize_thread($thread);
    $threadId = (string)($thread['id'] ?? '');
    if ($threadId === '') return false;

    // Dedup: no notificar dos veces el mismo hilo en 30 minutos
    $lastNotifiedAt = trim((string)($thread['hot_notified_at'] ?? ''));
    $lastNotifiedTs = $lastNotifiedAt !== '' ? strtotime($lastNotifiedAt) : 0;
    if ($lastNotifiedTs > 0 && (time() - $lastNotifiedTs) < 1800) {
        return false; // ya notificado recientemente
    }

    $phone = (string)($thread['target_phone'] ?? '');
    $processSlug = trim((string)($thread['process_slug'] ?? 'inbound'));
    $linePhone = trim((string)($thread['line_phone'] ?? ''));
    // Resolver nombre de la línea desde su id
    $lineName = '';
    $lineId = trim((string)($thread['line_id'] ?? ''));
    if ($lineId !== '') {
        $lines = comercial_list_lines();
        foreach ($lines as $l) {
            if ((string)($l['id'] ?? '') === $lineId) {
                $lineName = trim((string)($l['nombre'] ?? ''));
                break;
            }
        }
    }
    $history = comercial_thread_history($thread, 20);

    // Construir resumen de la conversación
    $summaryLines = array();
    foreach ($history as $entry) {
        $dir = (string)($entry['direction'] ?? '') === 'in' ? '📥' : '📤';
        $txt = trim((string)($entry['text'] ?? ''));
        if ($txt !== '') {
            $summaryLines[] = $dir . ' ' . mb_substr($txt, 0, 120, 'UTF-8');
        }
    }
    $conversationSummary = !empty($summaryLines) ? implode("\n", array_slice($summaryLines, -8)) : '(sin historial)';

    // Generar sugerencias para el dueño
    $suggestions = comercial_generate_hot_suggestions($thread, $processSlug, $inboundText);

    // Construir mensaje
    $ownerPhone = '654464023'; // teléfono del dueño

    $msg = "🔥 *CONVERSACIÓN MUY CALIENTE*\n\n";
    $lineInfo = $linePhone;
    if ($lineName !== '') {
        $lineInfo = $lineName . ' (' . $linePhone . ')';
    } elseif ($linePhone === '') {
        $lineInfo = 'desconocida';
    }
    $msg .= "📱 *Línea:* " . $lineInfo . "\n";
    $msg .= "📞 *Cliente:* " . $phone . "\n";
    $msg .= "🏷️ *Proceso:* " . $processSlug . "\n";
    $msg .= "💬 *Último mensaje:* " . (mb_strlen($inboundText, 'UTF-8') > 100 ? mb_substr($inboundText, 0, 100, 'UTF-8') . '...' : $inboundText) . "\n\n";
    $msg .= "*Resumen de la conversación:*\n" . $conversationSummary . "\n\n";
    $msg .= "*Sugerencias para seguirla:*\n" . $suggestions;

    // Enviar por WhatsApp al dueño usando una línea comercial disponible
    $sent = comercial_send_hot_notification_whatsapp($ownerPhone, $msg);

    // Marcar el hilo como notificado
    $thread['hot_notified_at'] = now_datetime();
    comercial_upsert_thread($thread);

    comercial_event_append('hot_summary_sent_to_owner', array(
        'thread_id' => $threadId,
        'target_phone' => $phone,
        'owner_phone' => $ownerPhone,
        'sent_ok' => $sent,
    ));

    return $sent;
}

function comercial_generate_hot_suggestions($thread, $processSlug, $inboundText) {
    $text = comercial_text_fold($inboundText);
    $suggestions = array();

    // Detectar intención de compra/precio
    if (preg_match('/\b(precio|cuanto|cuesta|valor|euros|€|dinero|pagar|coste|tarifa)\b/ui', $text)) {
        $suggestions[] = "💰 El cliente pregunta por precio. Responde con tarifas claras y ofrece una llamada para explicarlo mejor.";
    }

    // Detectar urgencia
    if (preg_match('/\b(urgente|rapido|ya|ahora|hoy|cuanto antes|enseguida)\b/ui', $text)) {
        $suggestions[] = "⏰ El cliente muestra urgencia. Prioriza esta conversación y ofrece contacto inmediato.";
    }

    // Detectar interés genérico
    if (preg_match('/\b(interesa|quiero|me gusta|genial|perfecto|dale|vale)\b/ui', $text)) {
        $suggestions[] = "✅ El cliente muestra interés claro. Haz una pregunta abierta para mantener la conversación.";
    }

    // Detectar preguntas
    if (preg_match('/\b(c[oó]mo|cu[aá]ndo|d[oó]nde|qui[ée]n|por qu[ée]|cu[aá]l)\b/ui', $text)) {
        $suggestions[] = "❓ El cliente hace una pregunta. Responde directamente y ofrece más información.";
    }

    // Sugerencia por proceso
    switch ($processSlug) {
        case 'plaza':
            $suggestions[] = "🏠 Proceso Plaza: habla de las ventajas de la ubicación, horarios y ambiente. Ofrece una visita.";
            break;
        case 'lamami':
            $suggestions[] = "💇 Proceso LaMami: menciona los 29€ de activación y la visibilidad que tendrá su negocio.";
            break;
        case 'publicista':
            $suggestions[] = "📣 Proceso Publicista: explícale las comisiones por venta y el soporte que recibe.";
            break;
        case 'casawasap':
            $suggestions[] = "🤖 Proceso CasaWasap: destaca el ahorro de tiempo con la telefonista IA 24/7.";
            break;
    }

    // Siempre añadir sugerencia genérica
    $suggestions[] = "📲 *Acción recomendada:* toma tú el control manual de esta conversación desde la pestaña Conversaciones del CRM. Responde de forma personal y natural.";

    return implode("\n", $suggestions);
}

function comercial_send_hot_notification_whatsapp($ownerPhone, $message) {
    // Usar cualquier línea comercial disponible para enviar la notificación
    $lines = comercial_list_lines();
    $processMeta = array(
        'id' => 'hot_notify',
        'slug' => 'hot_notify',
        'nombre' => 'Notificación caliente',
    );

    foreach ($lines as $line) {
        if (trim((string)($line['waha_port'] ?? '')) === '') continue;
        $state = isset($line['comercial_state']) ? $line['comercial_state'] : array();
        $status = trim((string)($state['status'] ?? 'active'));
        $health = trim((string)($state['health_status'] ?? ''));
        if ($status === 'paused') continue;
        if ($health === 'down') continue;

        $send = comercial_send_text_via_line($line, $ownerPhone, $message, $processMeta);
        if (!empty($send['ok'])) return true;
    }
    return false;
}

// ─── Fin Fix #3 ───

function comercial_reply_aviso_dedupe_window_seconds() {
    return 900;
}

function comercial_reply_aviso_dedupe_key($thread, $classification, $intentReason) {
    $thread = comercial_normalize_thread($thread);
    $threadId = trim((string)($thread['id'] ?? ''));
    if ($threadId === '') {
        $threadId = 'unknown';
    }
    $reason = trim((string)$intentReason);
    if ($reason === '') {
        $reason = trim((string)$classification);
    }
    if ($reason === '') {
        $reason = 'unknown';
    }
    return 'thread:' . $threadId . '|reason:' . $reason;
}

function comercial_reply_aviso_is_high_value($classification, $intentReason) {
    $classification = trim((string)$classification);
    if ($classification === 'very_hot') {
        return true;
    }

    $reason = comercial_text_fold((string)$intentReason);
    if ($reason === '') {
        return false;
    }

    // Escalaciones críticas siempre son high-value
    if ((strpos($reason, 'escalation') !== false && strpos($reason, 'critical') !== false)
        || strpos($reason, 'escalation_critical') !== false) {
        return true;
    }

    // ── T2.2: respuestas qualified con señales reales de interés también son high-value ──
    if ($classification === 'qualified') {
        $highValueReasons = array(
            'info_question:precio', 'info_question:cuanto', 'info_question:cuota',
            'info_question:cuotas', 'info_question:cuesta', 'info_question:tarifa',
            'intent:affirmative_interest', 'intent:short_affirmative',
            'keyword:interesa', 'keyword:precio', 'keyword:info',
        );
        foreach ($highValueReasons as $hvReason) {
            // Plegar ambos para comparar correctamente (text_fold reemplaza : y _ por espacios)
            if (strpos($reason, comercial_text_fold($hvReason)) !== false) return true;
        }
    }

    return false;
}

function comercial_reply_aviso_metrics_increment($path, $by = 1) {
    $stats = storage_read('comercial_daily_stats.json');
    $stats = is_array($stats) ? $stats : array();
    $dateKey = today_date();
    if (!isset($stats[$dateKey]) || !is_array($stats[$dateKey])) {
        $stats[$dateKey] = array();
    }

    $cursor = &$stats[$dateKey];
    $segments = array_values(array_filter(explode('.', (string)$path), function ($v) {
        return trim((string)$v) !== '';
    }));
    if (empty($segments)) {
        return;
    }

    $last = array_pop($segments);
    foreach ($segments as $seg) {
        if (!isset($cursor[$seg]) || !is_array($cursor[$seg])) {
            $cursor[$seg] = array();
        }
        $cursor = &$cursor[$seg];
    }
    $cursor[$last] = (int)($cursor[$last] ?? 0) + (int)$by;
    storage_write('comercial_daily_stats.json', $stats);
}

function comercial_reply_aviso_dedupe_check($thread, $classification, $intentReason) {
    $stats = storage_read('comercial_daily_stats.json');
    $stats = is_array($stats) ? $stats : array();
    $meta = is_array($stats['_reply_alerts'] ?? null) ? $stats['_reply_alerts'] : array();
    $dedupe = is_array($meta['dedupe'] ?? null) ? $meta['dedupe'] : array();

    $key = comercial_reply_aviso_dedupe_key($thread, $classification, $intentReason);
    $windowSec = comercial_reply_aviso_dedupe_window_seconds();
    $now = time();
    $lastTs = isset($dedupe[$key]) ? (int)$dedupe[$key] : 0;

    return array(
        'key' => $key,
        'window_sec' => $windowSec,
        'suppressed' => ($lastTs > 0 && ($now - $lastTs) < $windowSec),
        'last_ts' => $lastTs,
    );
}

function comercial_reply_aviso_mark_emitted($thread, $classification, $intentReason) {
    $stats = storage_read('comercial_daily_stats.json');
    $stats = is_array($stats) ? $stats : array();
    if (!isset($stats['_reply_alerts']) || !is_array($stats['_reply_alerts'])) {
        $stats['_reply_alerts'] = array();
    }
    if (!isset($stats['_reply_alerts']['dedupe']) || !is_array($stats['_reply_alerts']['dedupe'])) {
        $stats['_reply_alerts']['dedupe'] = array();
    }

    $key = comercial_reply_aviso_dedupe_key($thread, $classification, $intentReason);
    $stats['_reply_alerts']['dedupe'][$key] = time();
    $stats['_reply_alerts']['updated_at'] = now_datetime();

    if (count($stats['_reply_alerts']['dedupe']) > 1200) {
        asort($stats['_reply_alerts']['dedupe']);
        $stats['_reply_alerts']['dedupe'] = array_slice($stats['_reply_alerts']['dedupe'], -600, null, true);
    }

    storage_write('comercial_daily_stats.json', $stats);
    comercial_reply_aviso_metrics_increment('reply_alerts.emitted_total', 1);
    comercial_event_append('reply_alert_emitted', array(
        'thread_id' => (string)($thread['id'] ?? ''),
        'classification' => (string)$classification,
        'intent_reason' => (string)$intentReason,
        'dedupe_key' => $key,
    ));
}

function comercial_reply_aviso_register_suppressed($thread, $classification, $intentReason, $text, $mode, $context = array()) {
    $thread = comercial_normalize_thread($thread);
    $reasonKey = trim((string)$intentReason);
    if ($reasonKey === '') {
        $reasonKey = trim((string)$classification);
    }
    if ($reasonKey === '') {
        $reasonKey = 'unknown';
    }
    $reasonMetricKey = preg_replace('/[^a-z0-9_\-]+/i', '_', comercial_text_fold($reasonKey));
    if ($reasonMetricKey === '') {
        $reasonMetricKey = 'unknown';
    }

    comercial_reply_aviso_metrics_increment('reply_alerts.suppressed_total', 1);
    comercial_reply_aviso_metrics_increment('reply_alerts.suppressed_by_mode.' . trim((string)$mode), 1);
    comercial_reply_aviso_metrics_increment('reply_alerts.digest_by_reason.' . $reasonMetricKey, 1);

    $textPreview = function_exists('mb_substr') ? mb_substr((string)$text, 0, 160, 'UTF-8') : substr((string)$text, 0, 160);

    comercial_event_append('reply_alert_suppressed', array(
        'thread_id' => (string)($thread['id'] ?? ''),
        'classification' => (string)$classification,
        'intent_reason' => (string)$intentReason,
        'suppression_mode' => trim((string)$mode),
        'text_preview' => $textPreview,
        'context' => is_array($context) ? $context : array(),
    ));
}

function comercial_only_digits($value) {
    return preg_replace('/\D+/', '', (string)$value) ?: '';
}

function comercial_normalize_phone_spain($value) {
    $d = comercial_only_digits($value);
    if ($d === '') return '';
    if (strpos($d, '34') === 0) return $d;
    if (strlen($d) === 9) return '34' . $d;
    return $d;
}

function comercial_to_chat_id($phoneDigits) {
    $d = comercial_only_digits($phoneDigits);
    return $d !== '' ? $d . '@c.us' : '';
}

function comercial_safe_len($text) {
    if (function_exists('mb_strlen')) return (int)mb_strlen((string)$text, 'UTF-8');
    return strlen((string)$text);
}

function comercial_random_between($min, $max) {
    $min = (int)$min;
    $max = (int)$max;
    if ($max <= $min) return $min;
    return random_int($min, $max);
}

function comercial_is_hour_allowed($hour, $start, $end) {
    $hour = (int)$hour;
    $start = (int)$start;
    $end = (int)$end;
    if ($start <= $end) return ($hour >= $start && $hour <= $end);
    return ($hour >= $start || $hour <= $end);
}

function comercial_list_lines() {
    $rows = storage_read('telefonos.json');
    $states = comercial_get_line_states();
    $processes = comercial_get_processes();
    $usageMap = array();
    foreach ($processes as $p) {
        foreach ((array)$p['assigned_line_ids'] as $lineId) {
            $usageMap[$lineId][] = $p['nombre'];
        }
    }

    foreach ($rows as $i => $row) {
        $id = trim((string)($row['id'] ?? ''));
        $state = isset($states[$id]) ? $states[$id] : comercial_normalize_line_state(array('line_id' => $id));
        $rows[$i]['comercial_state'] = $state;
        $rows[$i]['comercial_usage'] = isset($usageMap[$id]) ? $usageMap[$id] : array();
    }
    return $rows;
}

function comercial_list_lines_indexed() {
    $rows = comercial_list_lines();
    $out = array();
    foreach ($rows as $row) {
        $out[(string)$row['id']] = $row;
    }
    return $out;
}

function comercial_line_is_available($line, $settings = null) {
    $settings = $settings ?: comercial_get_settings();
    $line = is_array($line) ? $line : array();
    $state = isset($line['comercial_state']) && is_array($line['comercial_state'])
        ? $line['comercial_state']
        : comercial_get_line_state((string)($line['id'] ?? ''));

    if (trim((string)($line['waha_port'] ?? '')) === '') return false;
    if (in_array((string)($state['health_status'] ?? 'unknown'), array('down', 'starting'), true)) return false;
    if ((string)$state['status'] === 'paused') {
        $cooldownUntilTs = strtotime((string)$state['cooldown_until']);
        if ($cooldownUntilTs > time()) return false;
    }
    return true;
}

function comercial_pick_line_for_process($process) {
    $lines = comercial_list_lines_indexed();
    $candidates = array();
    foreach ((array)$process['assigned_line_ids'] as $lineId) {
        if (!isset($lines[$lineId])) continue;
        if (!comercial_line_is_available($lines[$lineId])) continue;
        $candidates[] = $lines[$lineId];
    }
    if (empty($candidates)) return null;

    // COM-BALANCE-F2: min-deficit-first ponderado por power factor
    if (count($candidates) <= 1) {
        return $candidates[0];
    }

    $counts = comercial_line_get_daily_counts_map(array_column($candidates, 'id'));
    $lastId = trim((string)($process['last_line_id'] ?? ''));

    // Calcular déficit y asignar prioridad a cada candidata
    $scored = array();
    foreach ($candidates as $i => $line) {
        $lineId = (string)$line['id'];
        $count = isset($counts[$lineId]) ? (int)$counts[$lineId] : 0;
        $power = isset($line['comercial_state']['effective_power_factor'])
            ? (float)$line['comercial_state']['effective_power_factor']
            : 1.0;
        $deficit = ($power > 0) ? ($count / $power) : PHP_INT_MAX;
        $scored[] = array(
            'line' => $line,
            'deficit' => $deficit,
            'idx' => $i,
        );
    }

    // Ordenar por déficit ASC; empate → rotación legacy por process.last_line_id
    usort($scored, function ($a, $b) use ($lastId) {
        if (abs($a['deficit'] - $b['deficit']) > 0.0001) {
            return ($a['deficit'] < $b['deficit']) ? -1 : 1;
        }
        // Tiebreaker: siguiente después de lastId
        $aIsLast = ((string)($a['line']['id'] ?? '') === $lastId);
        $bIsLast = ((string)($b['line']['id'] ?? '') === $lastId);
        if ($aIsLast && !$bIsLast) return 1;
        if ($bIsLast && !$aIsLast) return -1;
        // Orden original como último desempate
        return $a['idx'] - $b['idx'];
    });

    // Anti-monopolio suave: si la ganadora es la global-last-line y hay otra con mismo déficit, rotar
    if (count($scored) > 1) {
        $runtime = comercial_get_runtime_state();
        $globalLastLineId = trim((string)($runtime['last_sent_line_id'] ?? ''));
        if ($globalLastLineId !== '' && (string)($scored[0]['line']['id'] ?? '') === $globalLastLineId) {
            if (abs($scored[0]['deficit'] - $scored[1]['deficit']) < 0.0001) {
                $rotated = $scored[0];
                unset($scored[0]);
                $scored[] = $rotated;
                $scored = array_values($scored);
            }
        }
    }

    return $scored[0]['line'];
}

function comercial_order_lines_for_process($process, $forceHealthCheck = false) {
    $lines = comercial_list_lines_indexed();
    $candidates = array();
    foreach ((array)$process['assigned_line_ids'] as $lineId) {
        if (!isset($lines[$lineId])) continue;
        $line = $lines[$lineId];
        if ($forceHealthCheck) {
            comercial_check_line_health($line, true);
            $lines = comercial_list_lines_indexed();
            if (!isset($lines[$lineId])) continue;
            $line = $lines[$lineId];
        }
        if (!comercial_line_is_available($line)) continue;
        $candidates[] = $line;
    }
    if (empty($candidates)) return array();

    // COM-BALANCE-F2: min-deficit-first ponderado por power factor
    if (count($candidates) <= 1) {
        return array_values($candidates);
    }

    $counts = comercial_line_get_daily_counts_map(array_column($candidates, 'id'));
    $lastId = trim((string)($process['last_line_id'] ?? ''));

    // Calcular déficit y asignar prioridad a cada candidata
    $scored = array();
    foreach ($candidates as $i => $line) {
        $lineId = (string)$line['id'];
        $count = isset($counts[$lineId]) ? (int)$counts[$lineId] : 0;
        $power = isset($line['comercial_state']['effective_power_factor'])
            ? (float)$line['comercial_state']['effective_power_factor']
            : 1.0;
        $deficit = ($power > 0) ? ($count / $power) : PHP_INT_MAX;
        $scored[] = array(
            'line' => $line,
            'deficit' => $deficit,
            'idx' => $i,
        );
    }

    // Ordenar por déficit ASC; empate → rotación legacy
    usort($scored, function ($a, $b) use ($lastId) {
        if (abs($a['deficit'] - $b['deficit']) > 0.0001) {
            return ($a['deficit'] < $b['deficit']) ? -1 : 1;
        }
        $aIsLast = ((string)($a['line']['id'] ?? '') === $lastId);
        $bIsLast = ((string)($b['line']['id'] ?? '') === $lastId);
        if ($aIsLast && !$bIsLast) return 1;
        if ($bIsLast && !$aIsLast) return -1;
        return $a['idx'] - $b['idx'];
    });

    // Anti-monopolio suave: si la primera es global-last-line y hay empate, rotar
    if (count($scored) > 1) {
        $runtime = comercial_get_runtime_state();
        $globalLastLineId = trim((string)($runtime['last_sent_line_id'] ?? ''));
        if ($globalLastLineId !== '' && (string)($scored[0]['line']['id'] ?? '') === $globalLastLineId) {
            if (abs($scored[0]['deficit'] - $scored[1]['deficit']) < 0.0001) {
                $rotated = $scored[0];
                unset($scored[0]);
                $scored[] = $rotated;
                $scored = array_values($scored);
            }
        }
    }

    return array_values(array_map(function ($s) { return $s['line']; }, $scored));
}

function comercial_send_process_message_with_fallback($process, $targetPhone, $text, $options = array()) {
    $process = is_array($process) ? $process : array();
    $options = is_array($options) ? $options : array();
    $forceHealthCheck = !empty($options['force_health_check']);
    $orderedLines = comercial_order_lines_for_process($process, $forceHealthCheck);
    if (empty($orderedLines)) {
        return array('ok' => false, 'error' => 'No hay líneas asignadas o disponibles', 'attempts' => array());
    }

    $attempts = array();
    foreach ($orderedLines as $line) {
        $send = comercial_send_text_via_line($line, $targetPhone, $text, $process);
        $attempts[] = array(
            'line_id' => (string)($line['id'] ?? ''),
            'line_name' => (string)($line['nombre'] ?? ''),
            'line_phone' => (string)($line['tfono'] ?? ''),
            'port' => (string)($line['waha_port'] ?? ''),
            'ok' => !empty($send['ok']),
            'http_code' => (int)($send['http_code'] ?? 0),
            'error' => (string)($send['error'] ?? ''),
            'response_body_excerpt' => (string)($send['response_body_excerpt'] ?? ''),
        );
        if (!empty($send['ok'])) {
            $send['line'] = $line;
            $send['attempts'] = $attempts;
            return $send;
        }
    }

    $lastAttempt = end($attempts);
    $error = trim((string)($lastAttempt['error'] ?? 'No se pudo enviar el mensaje.'));
    if ($error === '' && !empty($lastAttempt['http_code'])) {
        $error = 'HTTP ' . (int)$lastAttempt['http_code'];
    }

    return array(
        'ok' => false,
        'error' => $error !== '' ? $error : 'No se pudo enviar el mensaje.',
        'http_code' => (int)($lastAttempt['http_code'] ?? 0),
        'attempts' => $attempts,
        'line' => null,
    );
}

function comercial_pick_message($process, $field) {
    $pool = comercial_process_message_pool($process, $field);
    if (empty($pool)) return '';
    $picked = $pool[array_rand($pool)];
    return $picked;
}

/**
 * Construye un resumen textual del historial reciente del hilo (últimos N mensajes)
 * en formato legible para prompts de IA.
 */
function comercial_thread_recent_history_text($thread, $limit = 5) {
    $history = comercial_thread_history($thread, 200);
    if (empty($history)) return '';
    $history = array_slice($history, -$limit);
    $lines = array();
    foreach ($history as $entry) {
        $direction = ($entry['direction'] === 'in') ? 'Cliente' : 'Bot';
        $text = trim((string)($entry['text'] ?? ''));
        if ($text === '') continue;
        // Truncar textos muy largos para el prompt
        if (comercial_safe_len($text) > 200) {
            $text = (function_exists('mb_substr') ? mb_substr($text, 0, 200, 'UTF-8') : substr($text, 0, 200)) . '...';
        }
        $lines[] = $direction . ': ' . $text;
    }
    return implode("\n", $lines);
}

/**
 * ── Psychology-driven followup system ──
 * Calcula puntuación de engagement (0-100) basada en señales de la respuesta entrante.
 */
function comercial_calc_engagement_score($thread, $text, $process = array()) {
    $score = 30; // baseline
    $text = trim((string)$text);
    $textLen = comercial_safe_len($text);
    $process = is_array($process) ? $process : array();

    // Señal 1: Hizo una pregunta (curiosidad)
    if (preg_match('/[?¿]/u', $text) || preg_match('/\b(que|qué|cual|cu[aá]l|como|c[oó]mo|cu[aá]ndo|donde|d[oó]nde|cu[aá]nto|por qu[eé]|precio|info|informaci[oó]n|cu[eé]ntame|explicame|expl[ií]came|dime|dame)\b/ui', $text)) {
        $score += 20;
    }

    // Señal 2: Longitud de respuesta (más larga = más interés)
    if ($textLen >= 100) $score += 20;
    elseif ($textLen >= 50) $score += 15;
    elseif ($textLen >= 20) $score += 10;

    // Señal 3: Velocidad de respuesta (respuesta rápida = más interés)
    $lastBotAt = trim((string)($thread['last_bot_reply_at'] ?? ''));
    $lastContactAt = trim((string)($thread['last_contact_at'] ?? ''));
    $refAt = $lastBotAt !== '' ? $lastBotAt : $lastContactAt;
    if ($refAt !== '') {
        $refTs = strtotime($refAt);
        if ($refTs > 0) {
            $elapsedMin = (time() - $refTs) / 60;
            if ($elapsedMin <= 15) $score += 25;
            elseif ($elapsedMin <= 60) $score += 15;
            elseif ($elapsedMin <= 180) $score += 5;
        }
    }

    // Señal 4: Keywords positivas del proceso
    foreach ((array)($process['positive_keywords'] ?? array()) as $kw) {
        if (comercial_text_contains_keyword($text, (string)$kw)) {
            $score += 15;
            break;
        }
    }

    // Señal 5: Keywords negativas → penalización
    foreach ((array)($process['negative_keywords'] ?? array()) as $kw) {
        if (comercial_text_contains_keyword($text, (string)$kw)) {
            $score -= 40;
            break;
        }
    }

    return max(0, min(100, $score));
}

/**
 * Registro estático de estrategias psicológicas de seguimiento.
 * Cada estrategia tiene: key, turns (rango recomendado), engagement_min (umbral),
 * description (instrucción para IA).
 */
function comercial_get_followup_strategies() {
    return array(
        'reciprocity' => array(
            'key' => 'reciprocity',
            'turns' => array(1, 2),
            'engagement_min' => 0,
            'description' => 'Usa el principio de reciprocidad: ya has dado información valiosa, ahora es momento de que el cliente dé un pequeño paso (confirmar interés, preguntar algo concreto, dar su nombre o zona). Hazle sentir que ha recibido valor y que es natural corresponder.',
        ),
        'social_proof' => array(
            'key' => 'social_proof',
            'turns' => array(1, 2),
            'engagement_min' => 0,
            'description' => 'Usa prueba social: menciona que mucha gente ya está usando el servicio con éxito, que hay mucha demanda, que otras personas en su misma situación ya se beneficiaron. Crea sensación de "esto funciona y la gente lo sabe".',
        ),
        'liking' => array(
            'key' => 'liking',
            'turns' => array(1, 2),
            'engagement_min' => 0,
            'description' => 'Crea conexión personal: haz sentir al cliente que te cae bien, que hay química, que le estás contando esto porque de verdad crees que le puede ayudar. Tono cálido y cercano, como si hablaras con un amigo con el que hay confianza.',
        ),
        'curiosity_gap' => array(
            'key' => 'curiosity_gap',
            'turns' => array(1, 2, 3),
            'engagement_min' => 0,
            'description' => 'Abre un gap de curiosidad: insinúa que hay algo importante que aún no le has contado, un beneficio extra, una ventaja secreta, o un dato que le va a sorprender. Haz que quiera preguntar más. No lo reveles todo de golpe.',
        ),
        'scarcity' => array(
            'key' => 'scarcity',
            'turns' => array(2, 3),
            'engagement_min' => 0,
            'description' => 'Crea escasez/FOMO: menciona que quedan pocas plazas, que la oferta es por tiempo limitado, que hay lista de espera, o que otros están a punto de ocupar su sitio. Sin presionar agresivamente, pero dejando claro que si no actúa pronto se lo pierde.',
        ),
        'authority' => array(
            'key' => 'authority',
            'turns' => array(2, 3),
            'engagement_min' => 0,
            'description' => 'Establece autoridad/credibilidad: menciona datos concretos, resultados reales, tiempo en el mercado, casos de éxito. Haz que el cliente sienta que está tratando con profesionales que saben lo que hacen. Sin sonar arrogante.',
        ),
        'commitment' => array(
            'key' => 'commitment',
            'turns' => array(2, 3),
            'engagement_min' => 30,
            'description' => 'Busca micro-compromisos: pide un pequeño paso fácil (confirmar "me interesa", dar un dato, preguntar algo concreto). El objetivo es que el cliente se comprometa con algo pequeño ahora para que luego sea más fácil el compromiso grande. Técnica del pie en la puerta.',
        ),
        'urgency' => array(
            'key' => 'urgency',
            'turns' => array(3, 4),
            'engagement_min' => 0,
            'description' => 'Crea urgencia temporal: si no actúa ahora, pierde algo. Puede ser una promoción que acaba, un hueco que se va a llenar, un precio que va a subir. Tono: no presiones con agresividad, pero deja claro que el momento es AHORA.',
        ),
        'direct_close' => array(
            'key' => 'direct_close',
            'turns' => array(4, 5),
            'engagement_min' => 50,
            'description' => 'Cierre directo: ve al grano. Pregunta si le interesa o no, si quiere empezar ya, si necesita algo más para decidirse. Tono seguro pero respetuoso. Si no va a comprar, que lo diga claro para no perder tiempo. Pero ofrécele una última oportunidad.',
        ),
    );
}

/**
 * Selecciona la estrategia psicológica óptima para un turno y nivel de engagement dados.
 */
function comercial_get_strategy_for_turn($turnNumber, $engagementScore, $process = array()) {
    $strategies = comercial_get_followup_strategies();
    $turnNumber = max(1, (int)$turnNumber);
    $engagementScore = (int)$engagementScore;

    // Si el engagement es ≥75 y estamos en turno 3+, priorizar cierre directo
    if ($engagementScore >= 75 && $turnNumber >= 3) return 'direct_close';

    // Si el engagement es ≥60 y turno 2+, usar commitment
    if ($engagementScore >= 60 && $turnNumber >= 2) return 'commitment';

    // Estrategias recomendadas para este turno
    $candidates = array();
    foreach ($strategies as $key => $strategy) {
        $turnsOk = in_array($turnNumber, $strategy['turns']);
        $engagementOk = $engagementScore >= (int)$strategy['engagement_min'];
        if ($turnsOk && $engagementOk) {
            $candidates[] = $key;
        }
    }

    if (empty($candidates)) {
        // Fallback: estrategias por defecto según turno
        if ($turnNumber <= 2) $candidates = array('reciprocity', 'social_proof', 'liking', 'curiosity_gap');
        elseif ($turnNumber <= 3) $candidates = array('scarcity', 'authority', 'commitment');
        else $candidates = array('urgency', 'direct_close');
    }

    return $candidates[array_rand($candidates)];
}

/**
 * Genera variante psicológica de un followup template usando IA (OpenAI).
 * Toma la plantilla base + inbound text + estrategia + proceso y devuelve
 * una variante adaptada que preserva la info core pero aplica la psicología.
 */
function comercial_ai_generate_followup_variants($thread, $process, $inboundText, $baseFollowup, $strategyKey) {
    if (!function_exists('publicista_openai_json_request') || !function_exists('publicista_response_output_text') || !function_exists('publicista_ai_config')) {
        return array('ok' => false, 'error' => 'ai_utilities_unavailable');
    }
    $cfg = publicista_ai_config();
    if (empty($cfg['configured'])) {
        return array('ok' => false, 'error' => 'ai_not_configured');
    }

    $strategies = comercial_get_followup_strategies();
    $strategyInfo = $strategies[$strategyKey] ?? array('description' => 'Responde de forma natural y cercana, sin sonar robótico.');

    $processSlug = (string)($thread['process_slug'] ?? $process['slug'] ?? 'inbound');
    $iaContext = is_array($process) ? trim((string)($process['ia_context_prompt'] ?? '')) : '';

    $turnCount = (int)($thread['auto_turn_count'] ?? 0) + 1;
    $history = comercial_thread_recent_history_text($thread, 5);

    // ── T3.3: contexto adicional de clasificación para que la IA entienda mejor ──
    $classificationCtx = '';
    $lastDecision = trim((string)($thread['last_decision'] ?? ''));
    if ($lastDecision !== '') {
        $classificationCtx .= "CONTEXTO: El sistema clasificó el último mensaje como \"" . $lastDecision . "\". ";
    }

    $prompt = trim(
        ($iaContext !== '' ? $iaContext . "\n\n" : '') .
        "Eres una persona humana real del equipo. NUNCA digas que eres un bot o IA.\n\n" .
        "PROCESO: " . $processSlug . " (turno " . $turnCount . ")\n\n" .
        "HISTORIAL RECIENTE:\n" . ($history !== '' ? $history : "(inicio de conversación)") . "\n\n" .
        "LO ÚLTIMO QUE DIJO EL CLIENTE: «" . $inboundText . "»\n\n" .
        $classificationCtx .
        "INFORMACIÓN QUE DEBES COMUNICAR (NO INVENTES DATOS NUEVOS):\n" . $baseFollowup . "\n\n" .
        "ESTRATEGIA A APLICAR: " . $strategyInfo['description'] . "\n\n" .
        (!empty($thread['_greeting_only']) ? "ATENCIÓN: El cliente SOLO saludó, NO hizo ninguna pregunta. NO uses frases como 'me alegra que preguntes', 'gracias por preguntar' o similares.\n\n" : '') .
        "INSTRUCCIONES:\n" .
        "- Reescribe el mensaje base aplicando la estrategia indicada.\n" .
        "- Adapta el tono a lo que dijo el cliente. Sé coherente con la conversación.\n" .
        "- CONSERVA INTACTOS: precios (ej: 29€, 50€/semana), URLs (ej: https://lamami.online), porcentajes (ej: 60/40), condiciones económicas y CTAs (ej: 'responde INFO'). NO los cambies, NO los parafrasees, NO los omitas.\n" .
        "- Añade un CTA suave que invite a responder (no presiones agresivamente).\n" .
        "- Usa español natural, con alguna palabra coloquial si encaja.\n" .
        "- Puedes usar 1-2 emojis si procede.\n" .
        "- MÁXIMO 420 caracteres. Sé conciso.\n" .
        "- No uses markdown ni formato especial.\n" .
        "- RESPONDE ÚNICAMENTE con el texto del mensaje, sin explicaciones adicionales."
    );

    $model = trim((string)($cfg['descriptor_model'] ?? 'gpt-5.4-mini'));
    $payload = array(
        'model' => $model,
        'input' => $prompt,
        'max_output_tokens' => 180,
    );
    $resp = publicista_openai_json_request('/v1/responses', $payload, (int)($cfg['timeouts']['responses'] ?? 90));
    if (empty($resp['ok'])) {
        return array('ok' => false, 'error' => trim((string)($resp['error'] ?? 'ai_request_failed')));
    }
    $text = trim((string)publicista_response_output_text((array)($resp['decoded'] ?? array())));
    if ($text === '') {
        return array('ok' => false, 'error' => 'ai_empty_output');
    }
    if (comercial_safe_len($text) > 420) {
        $text = function_exists('mb_substr') ? trim((string)mb_substr($text, 0, 420, 'UTF-8')) : trim(substr($text, 0, 420));
    }
    return array('ok' => true, 'text' => $text, 'model' => $model, 'strategy' => $strategyKey);
}

/**
 * ── T3.2: valida que la salida de IA conserve datos críticos del mensaje original ──
 * Extrae del texto original: precios (€ + dígitos), URLs (http/https),
 * porcentajes (ej. 60/40), condiciones económicas, y CTAs clave ("responde INFO").
 * Verifica que cada dato extraído aparezca en la salida de la IA.
 * Si falta al menos un dato crítico, devuelve false → se debe usar el template original.
 */
function comercial_ai_output_preserves_key_info($original, $aiOutput) {
    $original = trim((string)$original);
    $aiOutput = trim((string)$aiOutput);
    if ($original === '' || $aiOutput === '') return true;

    // Extraer datos críticos del original
    $criticalItems = array();

    // 1. Precios: patrones como "29€", "50€/semana", "50 €", "10€ / 30min"
    if (preg_match_all('/(\d+[\s]?[€$](?:\s*\/\s*\w+)?)/u', $original, $m)) {
        foreach ($m[1] as $price) {
            $criticalItems[] = trim(preg_replace('/\s+/u', '', $price));
        }
    }

    // 2. URLs: http:// o https://
    if (preg_match_all('/(https?:\/\/[^\s]+)/ui', $original, $m)) {
        foreach ($m[1] as $url) {
            $criticalItems[] = rtrim($url, '.…,;:');
        }
    }

    // 3. Porcentajes/ratios: "60/40", "15-21 días"
    if (preg_match_all('/(\d+\s*\/\s*\d+)/u', $original, $m)) {
        foreach ($m[1] as $ratio) {
            $criticalItems[] = trim(preg_replace('/\s+/u', '', $ratio));
        }
    }

    // 4. CTAs clave: "responde INFO", "responde info", "di INFO", "dime INFO"
    if (preg_match_all('/((?:responde|di|dime|contesta)\s+(?:INFO|info|"info"|"INFO"))/ui', $original, $m)) {
        foreach ($m[1] as $cta) {
            $criticalItems[] = comercial_text_fold($cta);
        }
    }

    // Sin datos críticos extraídos → no hay nada que validar
    if (empty($criticalItems)) return true;

    // Verificar que cada dato crítico aparece en la salida (fold normalizado)
    $aiFolded = comercial_text_fold($aiOutput);
    foreach ($criticalItems as $item) {
        $itemFolded = comercial_text_fold($item);
        if ($itemFolded !== '' && strpos($aiFolded, $itemFolded) === false) {
            // Permitir pequeñas variaciones en precios (€ vs eur)
            if (preg_match('/^\d/', $item)) {
                // Para precios: verificar al menos que el número base aparece
                $numericPart = preg_replace('/[^0-9]/', '', $item);
                if ($numericPart !== '' && strpos($aiFolded, $numericPart) !== false) {
                    continue; // el número base está, aceptamos
                }
            }
            return false;
        }
    }

    return true;
}

/**
 * Elige el próximo texto de seguimiento para un turno dado con estrategia psicológica.
 * Lógica:
 *   - Calcula engagement score del inbound
 *   - Selecciona estrategia psicológica según turno + engagement
 *   - Tiene pool de templates variados: elige uno no repetido del pool
 *   - Si quedan pocos templates no usados, pide variante a IA con la estrategia
 *   - Si la IA falla, devuelve un template del pool como fallback
 *   - Turno N+ (textos agotados): improvisar con IA usando estrategia + contexto
 * Siempre devuelve string (puede ser vacío si todo falla).
 */
function comercial_pick_followup_or_improvise($thread, $process, $inboundText = '') {
    $pool = comercial_process_message_pool($process, 'followup_templates');
    $turnCount = (int)($thread['auto_turn_count'] ?? 0);
    $lastBotText = trim((string)($thread['last_bot_reply_text'] ?? ''));
    $inboundText = trim((string)$inboundText);

    // Calcular engagement y seleccionar estrategia
    $engagementScore = comercial_calc_engagement_score($thread, $inboundText, $process);
    $strategy = comercial_get_strategy_for_turn($turnCount + 1, $engagementScore, $process);

    // Guardar en el thread para tracking
    $thread['last_engagement_score'] = $engagementScore;
    $thread['last_strategy_used'] = $strategy;

    // Track de templates ya usados en este thread
    $usedTemplates = is_array($thread['_used_followup_indices'] ?? null) ? $thread['_used_followup_indices'] : array();

    if (!empty($pool)) {
        // Filtrar: no repetir el último texto enviado ni templates ya usados
        $available = array();
        $foldedLast = $lastBotText !== '' ? comercial_text_fold($lastBotText) : '';
        $foldedUsed = array_map(function($t) { return comercial_text_fold($t); }, $usedTemplates);

        foreach ($pool as $idx => $template) {
            $folded = comercial_text_fold($template);
            if ($folded === $foldedLast) continue;  // no repetir el último
            if (in_array($folded, $foldedUsed, true)) continue;  // no repetir usados antes
            $available[$idx] = $template;
        }

        // Si hay disponibles, elegir uno
        if (!empty($available)) {
            $idx = array_rand($available);
            $picked = $available[$idx];

            // Si el pool disponible es pequeño (< 3 templates sin usar), pedir variante IA
            $remainingCount = count($available);
            if ($remainingCount <= 2 && $turnCount >= 1) {
                $aiVariant = comercial_ai_generate_followup_variants($thread, $process, $inboundText, $picked, $strategy);
                if (!empty($aiVariant['ok']) && trim((string)($aiVariant['text'] ?? '')) !== '') {
                    $aiText = trim((string)$aiVariant['text']);
                    // ── T3.4: validar que la variante IA conserva datos críticos ──
                    if (comercial_ai_output_preserves_key_info($picked, $aiText)) {
                        $picked = $aiText;
                    } else {
                        comercial_event_append('ai_variant_rejected_info_loss', array(
                            'thread_id' => (string)($thread['id'] ?? ''),
                            'process_slug' => (string)($thread['process_slug'] ?? ''),
                            'reason' => 'critical_info_lost_in_ai_variant',
                        ));
                        // Se mantiene $picked como el template original
                    }
                }
            }

            // ── T3.5: marcar como usado y persistir inmediatamente ──
            if (!isset($thread['_used_followup_indices'])) $thread['_used_followup_indices'] = array();
            $thread['_used_followup_indices'][] = $picked;
            // Limitar a últimos 10 para no crecer indefinidamente
            if (count($thread['_used_followup_indices']) > 10) {
                $thread['_used_followup_indices'] = array_slice($thread['_used_followup_indices'], -10);
            }
            comercial_upsert_thread($thread);

            return $picked;
        }
    }

    // Pool agotado o vacío → improvisar con IA usando estrategia psicológica
    if ($turnCount >= 1) {
        // Usar un template base como referencia (cualquiera del pool o el último enviado)
        $baseRef = !empty($pool) ? $pool[array_rand($pool)] : ($lastBotText !== '' ? $lastBotText : '');
        if ($baseRef !== '') {
            $aiVariant = comercial_ai_generate_followup_variants($thread, $process, $inboundText, $baseRef, $strategy);
            if (!empty($aiVariant['ok']) && trim((string)($aiVariant['text'] ?? '')) !== '') {
                $aiText = trim((string)$aiVariant['text']);
                // ── T3.4: validar conservación de datos críticos también en pool agotado ──
                if ($baseRef !== '' && !comercial_ai_output_preserves_key_info($baseRef, $aiText)) {
                    comercial_event_append('ai_variant_rejected_info_loss', array(
                        'thread_id' => (string)($thread['id'] ?? ''),
                        'reason' => 'critical_info_lost_in_exhausted_pool',
                    ));
                    // Caer al fallback contextual en lugar de usar una variante que perdió datos
                } else {
                    return $aiText;
                }
            }
        }
    }

    // Fallback: IA contextual clásico
    $objective = 'continuar la conversación de forma natural (' . $strategy . '), explicar el servicio con entusiasmo y motivar al cliente a dar el siguiente paso';
    $ai = comercial_ai_generate_contextual_followup($thread, (string)($thread['process_slug'] ?? ''), $objective);
    if (!empty($ai['ok']) && trim((string)($ai['text'] ?? '')) !== '') {
        return trim((string)$ai['text']);
    }

    // Último recurso: devolver un template aleatorio aunque se repita
    if (!empty($pool)) {
        return $pool[array_rand($pool)];
    }

    return '';
}

/**
 * ── Fin del sistema de psicología ──
 */

function comercial_calc_typing_delay($text, $settings) {
    $len = comercial_safe_len($text);
    $base = 2 + intdiv($len, 25);
    $base = max((int)$settings['typing_min_sec'], min((int)$settings['typing_max_sec'], $base));
    return $base + comercial_random_between(0, (int)$settings['typing_jitter_sec']);
}

function comercial_waha_url($settings, $port, $path) {
    $host = rtrim((string)$settings['waha_host'], '/');
    return $host . ':' . trim((string)$port) . '/' . ltrim((string)$path, '/');
}

function comercial_waha_request_json($settings, $port, $method, $path, $payload = null) {
    $url = comercial_waha_url($settings, $port, $path);
    $ch = curl_init($url);
    if ($ch === false) {
        return array('ok' => false, 'http_code' => 0, 'error' => 'curl_init failed', 'body' => null, 'url' => $url);
    }

    $method = strtoupper(trim((string)$method));
    if ($method === '') $method = 'GET';

    $headers = array(
        'Accept: application/json',
        'X-Api-Key: ' . (string)$settings['waha_api_key'],
    );
    $options = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int)$settings['curl_timeout_sec'],
    );

    if ($method === 'POST') {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $json;
        $headers[] = 'Content-Type: application/json';
    } else {
        unset($options[CURLOPT_POSTFIELDS]);
        if ($method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }
    }

    $options[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $options);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return array(
        'ok' => ($resp !== false),
        'http_code' => $code,
        'error' => ($resp === false ? $err : null),
        'body' => ($resp === false ? null : $resp),
        'url' => $url,
    );
}

function comercial_waha_post_json($settings, $port, $path, $payload) {
    return comercial_waha_request_json($settings, $port, 'POST', $path, $payload);
}

function comercial_waha_get_json($settings, $port, $path) {
    return comercial_waha_request_json($settings, $port, 'GET', $path, null);
}

function comercial_line_health_interval_seconds() {
    return 3600;
}

function comercial_line_health_is_due($state, $force = false) {
    if ($force) return true;
    $state = is_array($state) ? $state : array();
    $lastCheckAt = trim((string)($state['last_health_check_at'] ?? ''));
    if ($lastCheckAt === '') return true;
    $lastCheckTs = strtotime($lastCheckAt);
    if (!$lastCheckTs) return true;
    return ($lastCheckTs + comercial_line_health_interval_seconds()) <= time();
}

function comercial_line_health_label($healthStatus) {
    $healthStatus = trim((string)$healthStatus);
    switch ($healthStatus) {
        case 'up':
            return 'Activa';
        case 'starting':
            return 'Arrancando';
        case 'down':
            return 'Caída';
        default:
            return 'Sin comprobar';
    }
}

function comercial_line_health_css_class($healthStatus) {
    $healthStatus = trim((string)$healthStatus);
    switch ($healthStatus) {
        case 'up':
            return 'ok';
        case 'starting':
            return 'warn';
        case 'down':
            return 'danger';
        default:
            return 'muted';
    }
}

function comercial_check_line_health($line, $force = false) {
    $line = is_array($line) ? $line : array();
    $lineId = trim((string)($line['id'] ?? ''));
    $port = trim((string)($line['waha_port'] ?? ''));
    $state = $lineId !== '' ? comercial_get_line_state($lineId) : comercial_normalize_line_state(array());

    if ($lineId === '') {
        return array('checked' => false, 'line_id' => '', 'health_status' => 'unknown', 'message' => 'Línea sin identificador.');
    }

    if ($port === '') {
        $patch = array(
            'line_id' => $lineId,
            'health_status' => 'unknown',
            'health_http_code' => 0,
            'health_error' => 'Sin WAHA port configurado.',
            'health_session_status' => '',
            'last_health_check_at' => now_datetime(),
        );
        $updated = comercial_update_line_state($lineId, $patch);
        return array(
            'checked' => true,
            'line_id' => $lineId,
            'line_name' => (string)($line['nombre'] ?? ''),
            'health_status' => (string)$updated['health_status'],
            'http_code' => 0,
            'session_status' => '',
            'error' => (string)$updated['health_error'],
            'message' => 'La línea no tiene WAHA port.',
        );
    }

    if (!comercial_line_health_is_due($state, $force)) {
        return array(
            'checked' => false,
            'line_id' => $lineId,
            'line_name' => (string)($line['nombre'] ?? ''),
            'health_status' => (string)($state['health_status'] ?? 'unknown'),
            'http_code' => (int)($state['health_http_code'] ?? 0),
            'session_status' => (string)($state['health_session_status'] ?? ''),
            'error' => (string)($state['health_error'] ?? ''),
            'message' => 'La línea todavía no necesita una nueva comprobación.',
        );
    }

    $settings = comercial_get_settings();
    $sessionName = trim((string)($settings['waha_session'] ?? 'default'));
    if ($sessionName === '') $sessionName = 'default';
    $response = comercial_waha_get_json($settings, $port, 'api/sessions/' . rawurlencode($sessionName));
    $decoded = json_decode((string)($response['body'] ?? ''), true);
    $sessionStatus = strtoupper(trim((string)($decoded['status'] ?? '')));
    $httpCode = (int)($response['http_code'] ?? 0);
    $httpOk = !empty($response['ok']) && in_array($httpCode, array(200, 201), true);

    $healthStatus = 'down';
    $errorText = '';

    if ($httpOk) {
        if ($sessionStatus === '' || $sessionStatus === 'WORKING') {
            $healthStatus = 'up';
        } elseif ($sessionStatus === 'STARTING') {
            $healthStatus = 'starting';
            $errorText = 'La sesión WAHA está arrancando.';
        } else {
            $healthStatus = 'down';
            $errorText = 'La sesión WAHA está en estado ' . $sessionStatus . '.';
        }
    } else {
        $errorText = trim((string)($response['error'] ?? ''));
        if ($errorText === '') {
            $errorText = $httpCode > 0 ? ('HTTP ' . $httpCode) : 'WAHA no respondió correctamente.';
        }
    }

    $patch = array(
        'line_id' => $lineId,
        'health_status' => $healthStatus,
        'health_http_code' => $httpCode,
        'health_error' => $errorText,
        'health_session_status' => $sessionStatus,
        'last_health_check_at' => now_datetime(),
    );

    if ($healthStatus === 'up') {
        $patch['last_health_ok_at'] = now_datetime();
    } else {
        $patch['last_health_failure_at'] = now_datetime();
    }

    $updated = comercial_update_line_state($lineId, $patch);
    $previousHealthStatus = trim((string)($state['health_status'] ?? 'unknown'));
    if ($force || $previousHealthStatus !== $healthStatus || $healthStatus !== 'up') {
        comercial_event_append('line_health_check', array(
            'line_id' => $lineId,
            'line_name' => (string)($line['nombre'] ?? ''),
            'line_phone' => comercial_only_digits((string)($line['tfono'] ?? '')),
            'line_port' => $port,
            'health_status' => $healthStatus,
            'session_status' => $sessionStatus,
            'http_code' => $httpCode,
            'error' => $errorText,
        ));
    }

    return array(
        'checked' => true,
        'line_id' => $lineId,
        'line_name' => (string)($line['nombre'] ?? ''),
        'health_status' => (string)$updated['health_status'],
        'http_code' => (int)$updated['health_http_code'],
        'session_status' => (string)$updated['health_session_status'],
        'error' => (string)$updated['health_error'],
        'message' => $healthStatus === 'up'
            ? 'WAHA operativo.'
            : ($errorText !== '' ? $errorText : 'WAHA no operativo.'),
    );
}

function comercial_refresh_lines_health($force = false) {
    $results = array();
    foreach (comercial_list_lines() as $line) {
        if (trim((string)($line['waha_port'] ?? '')) === '') continue;
        $results[] = comercial_check_line_health($line, $force);
    }
    return $results;
}

function comercial_send_text_via_line($line, $targetPhone, $text, $process = null) {
    $settings = comercial_get_settings();
    $lineId = (string)($line['id'] ?? '');
    $port = trim((string)($line['waha_port'] ?? ''));
    $chatId = comercial_to_chat_id(comercial_normalize_phone_spain($targetPhone));
    $linePhone = comercial_only_digits((string)($line['tfono'] ?? ''));

    if ($port === '' || $chatId === '' || trim((string)$text) === '') {
        return array('ok' => false, 'http_code' => 0, 'error' => 'Datos insuficientes para enviar', 'startTyping' => null, 'sendText' => null, 'stopTyping' => null);
    }

    $preDelay = comercial_random_between((int)$settings['typing_pre_min_sec'], (int)$settings['typing_pre_max_sec']);
    $typingDelay = comercial_calc_typing_delay($text, $settings);

    sleep($preDelay);
    $typingPayload = array('session' => (string)$settings['waha_session'], 'chatId' => $chatId);
    $start = comercial_waha_post_json($settings, $port, 'api/startTyping', $typingPayload);
    sleep($typingDelay);
    $sendPayload = array('session' => (string)$settings['waha_session'], 'chatId' => $chatId, 'text' => (string)$text);
    $send = comercial_waha_post_json($settings, $port, 'api/sendText', $sendPayload);
    $stop = comercial_waha_post_json($settings, $port, 'api/stopTyping', $typingPayload);

    $okSend = !empty($send['ok']) && (int)$send['http_code'] >= 200 && (int)$send['http_code'] < 300;
    $responseBody = is_string($send['body'] ?? null) ? trim((string)$send['body']) : '';
    $responseBodyExcerpt = $responseBody !== '' ? substr($responseBody, 0, 600) : '';
    $sendError = trim((string)($send['error'] ?? ''));
    if (!$okSend && $sendError === '' && $responseBodyExcerpt !== '') {
        $sendError = 'HTTP ' . (int)$send['http_code'] . ' · ' . $responseBodyExcerpt;
    }
    if (!$okSend && $sendError === '') {
        $sendError = 'WAHA sendText failed';
    }

    comercial_register_line_attempt($lineId, $okSend, (int)$send['http_code'], $okSend ? '' : $sendError);

    $eventPayload = array(
        'process_id' => (string)($process['id'] ?? ''),
        'process_slug' => (string)($process['slug'] ?? ''),
        'line_id' => $lineId,
        'line_phone' => $linePhone,
        'target_phone' => comercial_only_digits($targetPhone),
        'http_code' => (int)$send['http_code'],
        'error' => $sendError,
        'response_body_excerpt' => $responseBodyExcerpt,
        'text' => (string)$text,
    );
    if (!empty($process['test_probe'])) {
        $eventPayload['test_probe'] = 1;
        $eventPayload['test_key'] = trim((string)($process['test_key'] ?? comercial_test_probe_key()));
    }
    comercial_event_append($okSend ? 'send_ok' : 'send_error', $eventPayload);

    return array(
        'ok' => $okSend,
        'http_code' => (int)$send['http_code'],
        'error' => $okSend ? '' : $sendError,
        'response_body_excerpt' => $responseBodyExcerpt,
        'line_phone' => $linePhone,
        'startTyping' => $start,
        'sendText' => $send,
        'stopTyping' => $stop,
    );
}

function comercial_send_thread_message($thread, $text, $options = array()) {
    $thread = comercial_normalize_thread($thread);
    $text = trim((string)$text);
    $options = is_array($options) ? $options : array();

    if ($text === '') {
        return array('ok' => false, 'error' => 'Mensaje vacío');
    }

    $lines = comercial_list_lines_indexed();
    $lineId = trim((string)($thread['line_id'] ?? ''));
    if ($lineId === '' || !isset($lines[$lineId])) {
        return array('ok' => false, 'error' => 'No se encontró la línea origen de la conversación');
    }

    $line = $lines[$lineId];
    $process = comercial_get_process((string)($thread['process_id'] ?? ''));
    if (!is_array($process)) {
        $process = array();
    }
    if (comercial_thread_is_test_probe($thread)) {
        $process['test_probe'] = 1;
        $process['test_key'] = trim((string)($thread['test_key'] ?? comercial_test_probe_key()));
    }
    $send = comercial_send_text_via_line($line, (string)($thread['target_phone'] ?? ''), $text, $process ?: array());
    if (empty($send['ok'])) {
        return $send;
    }

    $thread['messages_sent_count'] = (int)$thread['messages_sent_count'] + 1;
    $thread['last_outbound_text'] = $text;
    $thread['last_contact_at'] = now_datetime();
    $thread['updated_at'] = now_datetime();
    if (!empty($options['human_taken'])) {
        $thread['human_taken'] = 1;
    }
    if (!empty($options['qualified_reply_sent'])) {
        $thread['qualified_reply_sent_at'] = now_datetime();
    }
    if (trim((string)($options['stage'] ?? '')) !== '') {
        $thread = comercial_thread_apply_stage($thread, (string)$options['stage']);
    }

    comercial_upsert_thread($thread);
    $eventPayload = array(
        'thread_id' => (string)$thread['id'],
        'process_slug' => (string)$thread['process_slug'],
        'target_phone' => (string)$thread['target_phone'],
        'line_id' => $lineId,
        'text' => $text,
        'manual' => !empty($options['human_taken']) ? 1 : 0,
    );
    if (comercial_thread_is_test_probe($thread)) {
        $eventPayload['test_probe'] = 1;
        $eventPayload['test_key'] = trim((string)($thread['test_key'] ?? comercial_test_probe_key()));
    }
    comercial_event_append(trim((string)($options['event_type'] ?? 'thread_message_sent')), $eventPayload);

    $send['thread'] = $thread;
    return $send;
}

function comercial_register_line_attempt($lineId, $ok, $httpCode, $errorText = '') {
    $settings = comercial_get_settings();
    $state = comercial_get_line_state($lineId);
    $window = (array)$state['rolling_window'];
    $window[] = array(
        'ts' => now_datetime(),
        'ok' => $ok ? 1 : 0,
        'http_code' => (int)$httpCode,
        'error' => (string)$errorText,
    );
    $windowSize = max(3, (int)$settings['ban_window_size']);
    $window = array_values(array_slice($window, -1 * $windowSize));

    $failCount = 0;
    foreach ($window as $row) {
        if (empty($row['ok'])) $failCount++;
    }
    $failRatio = count($window) > 0 ? ($failCount / count($window)) : 0;

    $patch = array(
        'line_id' => $lineId,
        'rolling_window' => $window,
        'last_http_code' => (int)$httpCode,
        'updated_at' => now_datetime(),
    );

    if ($ok) {
        $patch['consecutive_failures'] = 0;
        $patch['last_success_at'] = now_datetime();
        $patch['last_error'] = '';
        $patch['status'] = 'active';
        $patch['cooldown_until'] = '';
        $patch['stable_since_at'] = trim((string)($state['stable_since_at'] ?? '')) !== '' ? (string)$state['stable_since_at'] : now_datetime();
        $patch['health_status'] = 'up';
        $patch['health_http_code'] = (int)$httpCode;
        $patch['health_error'] = '';
        $patch['health_session_status'] = 'WORKING';
        $patch['last_health_check_at'] = now_datetime();
        $patch['last_health_ok_at'] = now_datetime();
        $updated = comercial_update_line_state($lineId, $patch);
        if ((int)$httpCode === 201) {
            comercial_maybe_raise_line_power($lineId, $updated);
        }
        return;
    }

    $consecutive = (int)$state['consecutive_failures'] + 1;
    $patch['consecutive_failures'] = $consecutive;
    $patch['last_failure_at'] = now_datetime();
    $patch['last_error'] = (string)$errorText;
    $patch['stable_since_at'] = '';
    if (comercial_line_failure_counts_as_ban($httpCode, $errorText)) {
        $patch['last_ban_at'] = now_datetime();
    }
    if ((int)$httpCode === 0 || (int)$httpCode >= 500) {
        $patch['health_status'] = 'down';
        $patch['health_http_code'] = (int)$httpCode;
        $patch['health_error'] = (string)$errorText;
        $patch['last_health_check_at'] = now_datetime();
        $patch['last_health_failure_at'] = now_datetime();
    }

    $pauseEnabled = !empty($settings['auto_pause_enabled']);
    $warningStreak = (int)$settings['ban_fail_streak_warning'];
    $pauseStreak = (int)$settings['ban_fail_streak_pause'];
    $warningRatio = (float)$settings['ban_fail_ratio_warning'];
    $pauseRatio = (float)$settings['ban_fail_ratio_pause'];

    if ($pauseEnabled && ($consecutive >= $pauseStreak || (count($window) >= 3 && $failRatio >= $pauseRatio))) {
        $patch['status'] = 'paused';
        $patch['effective_power_factor'] = 0.3;
        $patch['cooldown_until'] = date('Y-m-d H:i:s', time() + ((int)$settings['cooldown_minutes_pause'] * 60));
        comercial_event_append('line_paused', array('line_id' => $lineId, 'reason' => 'auto-ban-guard', 'consecutive_failures' => $consecutive, 'fail_ratio' => $failRatio));
    } elseif ($consecutive >= $warningStreak || (count($window) >= 3 && $failRatio >= $warningRatio)) {
        $patch['status'] = 'warning';
        $patch['cooldown_until'] = date('Y-m-d H:i:s', time() + ((int)$settings['cooldown_minutes_warning'] * 60));
        comercial_event_append('line_warning', array('line_id' => $lineId, 'consecutive_failures' => $consecutive, 'fail_ratio' => $failRatio));
    }

    comercial_update_line_state($lineId, $patch);
    if (comercial_line_failure_counts_as_ban($httpCode, $errorText)) {
        comercial_apply_global_ban_slowdown($lineId, $httpCode, $errorText);
    }
}

function comercial_pick_candidate_from_jsonl($process) {
    $phoneField = trim((string)($process['source_phone_field'] ?? 'group_key'));
    $excludePhone = comercial_only_digits((string)($process['last_target_phone'] ?? ''));
    $blacklistHits = 0;
    foreach ((array)$process['source_queue_files'] as $path) {
        $path = trim((string)$path);
        if ($path === '' || !file_exists($path)) continue;
        $payload = comercial_jsonl_dequeue_first_valid($path, $phoneField, $excludePhone, $process);
        if ($payload && !empty($payload['ok'])) return $payload;
        if (!empty($payload['blacklist_hits'])) {
            $blacklistHits += (int)$payload['blacklist_hits'];
        }
    }
    $reason = $blacklistHits > 0 ? 'Sin cola disponible: los candidatos restantes están en blacklist' : 'Sin cola disponible o vacía';
    return array('ok' => false, 'reason' => $reason, 'blacklist_hits' => $blacklistHits);
}

function comercial_jsonl_dequeue_first_valid($path, $phoneField, $excludePhone = '', $process = array()) {
    $fh = @fopen($path, 'c+');
    if ($fh === false) return null;
    $result = null;
    $excludePhone = comercial_normalize_phone_spain($excludePhone);
    $blacklistHits = 0;

    if (flock($fh, LOCK_EX)) {
        rewind($fh);
        $lines = array();
        while (($line = fgets($fh)) !== false) {
            $lines[] = $line;
        }

        $pickedIndex = null;
        $pickedData = null;
        $fallbackIndex = null;
        $fallbackData = null;
        $skipIndexes = array();

        foreach ($lines as $index => $line) {
            if (trim($line) === '') continue;

            $data = json_decode($line, true);
            if (!is_array($data)) continue;

            $phone = comercial_normalize_phone_spain((string)($data[$phoneField] ?? ''));
            if ($phone === '') continue;

            $matchedBlacklist = '';
            if (comercial_phone_is_blacklisted($phone, $process, $matchedBlacklist)) {
                $skipIndexes[$index] = true;
                $blacklistHits++;
                continue;
            }

            // Deduplicación global: evitar enviar a teléfonos ya contactados por cualquier proceso
            if (comercial_phone_was_contacted($phone)) {
                $skipIndexes[$index] = true;
                continue;
            }

            $candidate = array(
                'ok' => true,
                'source_type' => 'jsonl_queue',
                'source_ref' => $path,
                'source_payload' => $data,
                'target_phone' => $phone,
            );

            if ($fallbackIndex === null) {
                $fallbackIndex = $index;
                $fallbackData = $candidate;
            }

            if ($excludePhone !== '' && $phone === $excludePhone) {
                continue;
            }

            $pickedIndex = $index;
            $pickedData = $candidate;
            break;
        }

        if ($pickedIndex === null && $fallbackIndex !== null) {
            $pickedIndex = $fallbackIndex;
            $pickedData = $fallbackData;
        }

        $rest = array();
        foreach ($lines as $index => $line) {
            if (($pickedIndex !== null && $index === $pickedIndex) || isset($skipIndexes[$index])) continue;
            $rest[] = $line;
        }

        ftruncate($fh, 0);
        rewind($fh);
        foreach ($rest as $line) fwrite($fh, $line);
        fflush($fh);
        flock($fh, LOCK_UN);

        if ($pickedData !== null) {
            $result = $pickedData;
            if ($blacklistHits > 0) {
                $result['blacklist_hits'] = $blacklistHits;
            }
        } elseif ($blacklistHits > 0) {
            $result = array('ok' => false, 'blacklist_hits' => $blacklistHits);
        }
    }

    fclose($fh);
    return $result;
}

function comercial_pick_candidate_from_mysql($process) {
    $config = array(
        'host' => trim((string)$process['source_mysql_host']),
        'db' => trim((string)$process['source_mysql_db']),
        'user' => (string)$process['source_mysql_user'],
        'pass' => (string)$process['source_mysql_pass'],
    );
    $sql = trim((string)$process['source_mysql_query']);
    if ($config['host'] === '' || $config['db'] === '' || $sql === '') {
        return array('ok' => false, 'reason' => 'Configuración MySQL incompleta');
    }

    try {
        $pdo = crm_db_connect($config);
        if (!$pdo) {
            return array('ok' => false, 'reason' => 'No se pudo conectar a MySQL');
        }
        $rows = $pdo->query($sql)->fetchAll();
        $excludePhone = comercial_normalize_phone_spain((string)($process['last_target_phone'] ?? ''));
        $fallback = null;
        $blacklistHits = 0;
        foreach ($rows as $row) {
            $phone = comercial_normalize_phone_spain((string)($row['telefono'] ?? ''));
            if ($phone === '') continue;
            $matchedBlacklist = '';
            if (comercial_phone_is_blacklisted($phone, $process, $matchedBlacklist)) {
                $blacklistHits++;
                continue;
            }
            // Deduplicación global: evitar enviar a teléfonos ya contactados por cualquier proceso
            if (comercial_phone_was_contacted($phone)) {
                continue;
            }
            $existing = comercial_find_thread_by_process_phone((string)$process['id'], $phone);
            if ($existing) continue;
            if ($fallback === null) {
                $fallback = array(
                    'ok' => true,
                    'source_type' => 'mysql_recent',
                    'source_ref' => trim((string)($row['id'] ?? '')),
                    'source_payload' => $row,
                    'target_phone' => $phone,
                );
            }
            if ($excludePhone !== '' && $phone === $excludePhone) continue;
            return array(
                'ok' => true,
                'source_type' => 'mysql_recent',
                'source_ref' => trim((string)($row['id'] ?? '')),
                'source_payload' => $row,
                'target_phone' => $phone,
                'blacklist_hits' => $blacklistHits,
            );
        }
        if ($fallback !== null) {
            if ($blacklistHits > 0) {
                $fallback['blacklist_hits'] = $blacklistHits;
            }
            return $fallback;
        }
        if ($blacklistHits > 0) {
            return array('ok' => false, 'reason' => 'No hay candidatos nuevos en MySQL: los restantes están en blacklist', 'blacklist_hits' => $blacklistHits);
        }
    } catch (Throwable $e) {
        return array('ok' => false, 'reason' => $e->getMessage());
    }

    return array('ok' => false, 'reason' => 'No hay candidatos nuevos en MySQL');
}

function comercial_process_due_now($process) {
    if (empty($process['enabled'])) return false;
    if (comercial_effective_daily_target($process) <= 0) return false;
    if (!comercial_is_hour_allowed((int)date('G'), (int)$process['window_start_hour'], (int)$process['window_end_hour'])) return false;
    $nextTs = strtotime((string)($process['next_run_at'] ?? ''));
    if ($nextTs && $nextTs > time()) return false;
    return true;
}

function comercial_schedule_next_run($process, $lineState = null) {
    $factor = 1;
    if (is_array($lineState) && isset($lineState['effective_power_factor']) && (float)$lineState['effective_power_factor'] > 0) {
        $factor = (float)$lineState['effective_power_factor'];
    }

    $plan = comercial_calculate_interval_plan($process);
    $minBase = (int)$plan['min_seconds'];
    $maxBase = (int)$plan['max_seconds'];

    if ($minBase <= 0 || $maxBase <= 0) {
        $process['next_run_at'] = '';
        $process['last_run_at'] = now_datetime();
        return $process;
    }

    $min = max(60, (int)round($minBase / $factor));
    $max = max($min, (int)round($maxBase / $factor));
    $process['min_interval_seconds'] = $minBase;
    $process['max_interval_seconds'] = $maxBase;
    $process['next_run_at'] = date('Y-m-d H:i:s', time() + comercial_random_between($min, $max));
    $process['last_run_at'] = now_datetime();
    return $process;
}

function comercial_run_tick($forceProcessId = '') {
    $results = array();
    comercial_refresh_lines_health(false);

    // ── COM-BALANCE-F3: reset de contadores diarios al cambiar de día ──
    comercial_reset_daily_counts_if_new_day();

    // ── T4.2: reset de auto_turn_count en hilos inactivos > 24h ──
    // Antes dependía solo de que llegara un nuevo mensaje entrante.
    // Ahora el tick también limpia contadores obsoletos.
    $threads = comercial_get_threads();
    $threadsChanged = false;
    foreach ($threads as $i => $thread) {
        $lastContactAt = trim((string)($thread['last_contact_at'] ?? ''));
        if ($lastContactAt !== '' && strtotime($lastContactAt) < (time() - 86400)) {
            if ((int)($thread['auto_turn_count'] ?? 0) > 0) {
                $threads[$i]['auto_turn_count'] = 0;
                $threadsChanged = true;
            }
        }
    }
    if ($threadsChanged) {
        comercial_save_threads($threads);
        comercial_event_append('auto_turn_maintenance_reset', array(
            'reset_at' => now_datetime(),
            'threads_checked' => count($threads),
        ));
    }

    $processes = comercial_get_processes();

    foreach ($processes as $process) {
        if ($forceProcessId !== '' && (string)$process['id'] !== $forceProcessId && (string)$process['slug'] !== $forceProcessId) {
            continue;
        }

        if (!comercial_process_due_now($process) && $forceProcessId === '') {
            continue;
        }

        $orderedLines = comercial_order_lines_for_process($process);
        if (empty($orderedLines)) {
            $process['last_error'] = 'Sin líneas asignadas o disponibles';
            $process['last_result'] = 'skip';
            $process = comercial_schedule_next_run($process);
            comercial_upsert_process($process);
            $results[] = array('process' => $process['nombre'], 'ok' => false, 'reason' => $process['last_error']);
            continue;
        }

        if ((string)$process['source_type'] === 'mysql_recent') {
            $candidate = comercial_pick_candidate_from_mysql($process);
        } else {
            $candidate = comercial_pick_candidate_from_jsonl($process);
        }

        if (empty($candidate['ok'])) {
            $process['last_error'] = (string)($candidate['reason'] ?? 'Sin candidato');
            $process['last_result'] = 'idle';
            $process['last_line_id'] = !empty($orderedLines) ? (string)($orderedLines[0]['id'] ?? '') : '';
            $process = comercial_schedule_next_run($process, comercial_get_line_state(!empty($orderedLines) ? (string)($orderedLines[0]['id'] ?? '') : ''));
            comercial_upsert_process($process);
            $results[] = array('process' => $process['nombre'], 'ok' => false, 'reason' => $process['last_error']);
            continue;
        }

        $targetPhone = (string)$candidate['target_phone'];
        $message = comercial_pick_message($process, 'message_templates');
        if (trim($message) === '') {
            $message = 'Hola 👋';
        }

        $send = comercial_send_process_message_with_fallback($process, $targetPhone, $message);
        $line = (array)($send['line'] ?? $orderedLines[0]);
        $process['last_line_id'] = (string)($line['id'] ?? '');
        $process['last_result'] = !empty($send['ok']) ? 'send_ok' : 'send_error';
        $process['last_error'] = !empty($send['ok']) ? '' : (string)$send['error'];
        if (!empty($send['ok'])) {
            $process['last_target_phone'] = comercial_only_digits($targetPhone);
        }
        $process = comercial_schedule_next_run($process, comercial_get_line_state((string)($line['id'] ?? '')));
        comercial_upsert_process($process);

        if (!empty($send['ok'])) {
            comercial_register_last_send((string)($line['id'] ?? ''), $targetPhone);
            $thread = comercial_normalize_thread(array(
                'process_id' => (string)$process['id'],
                'process_slug' => (string)$process['slug'],
                'line_id' => (string)($line['id'] ?? ''),
                'line_phone' => comercial_only_digits((string)($line['tfono'] ?? '')),
                'target_phone' => comercial_only_digits($targetPhone),
                'source_ref' => (string)($candidate['source_ref'] ?? ''),
                'source_payload' => (array)($candidate['source_payload'] ?? array()),
                'stage' => 'initial_sent',
                'status' => 'open',
                'messages_sent_count' => 1,
                'last_outbound_text' => $message,
                'last_contact_at' => now_datetime(),
                'updated_at' => now_datetime(),
                'created_at' => now_datetime(),
            ));
            comercial_upsert_thread($thread);
            comercial_register_sent_phone($targetPhone, (string)$process['slug']);
            $results[] = array('process' => $process['nombre'], 'ok' => true, 'target_phone' => $targetPhone, 'line' => (string)($line['nombre'] ?? ''));
        } else {
            $results[] = array('process' => $process['nombre'], 'ok' => false, 'target_phone' => $targetPhone, 'reason' => (string)$send['error']);
        }
    }

    return $results;
}

function comercial_send_test_probe() {
    comercial_reset_test_probe();

    $process = comercial_get_process(comercial_test_probe_process_slug());
    if (!$process) {
        return array('ok' => false, 'error' => 'No se encontró el proceso plaza.');
    }

    $targetPhone = comercial_test_probe_phone();
    $message = comercial_pick_message($process, 'message_templates');
    if (trim((string)$message) === '') {
        $message = 'Hola 👋';
    }

    $processMeta = $process;
    $processMeta['test_probe'] = 1;
    $processMeta['test_key'] = comercial_test_probe_key();
    $send = comercial_send_process_message_with_fallback($processMeta, $targetPhone, $message, array('force_health_check' => true));
    if (empty($send['ok'])) {
        $attemptSummary = array();
        foreach ((array)($send['attempts'] ?? array()) as $attempt) {
            $attemptSummary[] = trim((string)($attempt['line_name'] ?? '')) . ' (' . trim((string)($attempt['line_phone'] ?? '')) . '): ' . trim((string)($attempt['error'] ?? ('HTTP ' . (int)($attempt['http_code'] ?? 0))));
        }
        $error = trim((string)($send['error'] ?? 'No se pudo enviar la prueba.'));
        if (!empty($attemptSummary)) {
            $error .= ' · intentos: ' . implode(' | ', $attemptSummary);
        }
        return array('ok' => false, 'error' => $error);
    }
    $line = (array)($send['line'] ?? array());

    $thread = comercial_normalize_thread(array(
        'process_id' => (string)$process['id'],
        'process_slug' => (string)$process['slug'],
        'line_id' => (string)$line['id'],
        'line_phone' => comercial_only_digits((string)($line['tfono'] ?? '')),
        'target_phone' => comercial_only_digits($targetPhone),
        'source_ref' => 'test_probe',
        'source_payload' => array('mode' => 'test_probe'),
        'stage' => 'initial_sent',
        'status' => 'open',
        'messages_sent_count' => 1,
        'last_outbound_text' => $message,
        'last_contact_at' => now_datetime(),
        'updated_at' => now_datetime(),
        'created_at' => now_datetime(),
        'test_probe' => 1,
        'test_key' => comercial_test_probe_key(),
    ));
    comercial_upsert_thread($thread);
    comercial_event_append('test_probe_sent', array(
        'thread_id' => (string)$thread['id'],
        'process_id' => (string)$process['id'],
        'process_slug' => (string)$process['slug'],
        'line_id' => (string)$line['id'],
        'target_phone' => comercial_only_digits($targetPhone),
        'test_probe' => 1,
        'test_key' => comercial_test_probe_key(),
    ));

    return array(
        'ok' => true,
        'thread' => $thread,
        'line' => $line,
        'message' => $message,
    );
}

function comercial_record_thread_reply_feedback($thread, $replyText, $meta = array()) {
    $thread = comercial_normalize_thread($thread);
    $replyText = trim((string)$replyText);
    $meta = is_array($meta) ? $meta : array();
    if ($replyText === '') return $thread;

    $suggested = trim((string)($thread['last_ai_suggested_reply'] ?? ''));
    $accepted = false;
    $edited = false;
    if ($suggested !== '') {
        $accepted = comercial_text_fold($suggested) === comercial_text_fold($replyText);
        $edited = !$accepted;
    }
    if (array_key_exists('accepted', $meta)) $accepted = !empty($meta['accepted']);
    if (array_key_exists('edited', $meta)) $edited = !empty($meta['edited']);

    $feedback = array(
        'accepted' => $accepted ? 1 : 0,
        'edited' => $edited ? 1 : 0,
        'led_to_lead' => !empty($meta['led_to_lead']) ? 1 : 0,
        'at' => now_datetime(),
        'text_len' => comercial_safe_len($replyText),
    );
    $thread['last_ai_feedback'] = $accepted ? 'accepted' : ($edited ? 'edited' : 'manual');
    $thread['last_ai_feedback_meta'] = $feedback;
    return $thread;
}

function comercial_promote_thread_to_lead($threadId) {
    $thread = null;
    foreach (comercial_get_threads() as $row) {
        if ((string)$row['id'] === (string)$threadId) {
            $thread = $row;
            break;
        }
    }
    if (!$thread) return array(false, 'No se encontró la conversación.');

    $lead = comercial_upsert_lead(array(
        'id' => trim((string)($thread['lead_id'] ?? '')),
        'thread_id' => (string)$thread['id'],
        'process_slug' => (string)$thread['process_slug'],
        'telefono' => (string)$thread['target_phone'],
        'estado' => 'nuevo',
        'origen' => 'comercial',
        'observaciones' => trim((string)$thread['last_inbound_text']),
        'created_at' => now_datetime(),
    ));

    $thread['lead_id'] = (string)$lead['id'];
    $thread = comercial_record_thread_reply_feedback($thread, trim((string)($thread['last_outbound_text'] ?? '')), array('led_to_lead' => true));
    if (!empty(comercial_get_settings()['ia_learning_enabled'])) {
        comercial_ai_memory_store_feedback((string)($thread['process_slug'] ?? ''), 'human_reply', trim((string)($thread['last_outbound_text'] ?? '')), array(
            'accepted' => !empty($thread['last_ai_feedback_meta']['accepted']),
            'edited' => !empty($thread['last_ai_feedback_meta']['edited']),
            'led_to_lead' => true,
        ));
    }
    $thread = comercial_thread_apply_stage($thread, (string)($thread['stage'] ?? '') === 'very_hot' ? 'very_hot' : 'qualified');
    comercial_upsert_thread($thread);
    $eventPayload = array('thread_id' => $thread['id'], 'lead_id' => $lead['id'], 'process_slug' => $thread['process_slug']);
    if (comercial_thread_is_test_probe($thread)) {
        $eventPayload['test_probe'] = 1;
        $eventPayload['test_key'] = trim((string)($thread['test_key'] ?? comercial_test_probe_key()));
        $eventPayload['target_phone'] = (string)($thread['target_phone'] ?? '');
    }
    comercial_event_append('lead_created', $eventPayload);
    return array(true, $lead);
}

function comercial_classify_reply($process, $text, $thread = null) {
    $text = trim((string)$text);
    if ($text === '') {
        return array('classification' => 'empty', 'reason' => 'empty_text');
    }

    // ── Fix U1: evaluar NEGATIVOS PRIMERO ──
    // "no me interesa" debe clasificarse como negative, no como very_hot.
    // El check de high-intent-after-followup se ejecuta DESPUÉS de descartar negativos.
    if (comercial_reply_is_negative_intent($text, $process)) {
        return array('classification' => 'negative', 'reason' => 'negative_intent');
    }

    // ── Detectar auto-responders (cuentas con WhatsApp Business auto-reply) ──
    if ($thread && comercial_is_likely_autoresponder($text, $thread)) {
        return array('classification' => 'autoresponder', 'reason' => 'autoresponder_pattern_detected');
    }

    // Si ya se envió la respuesta automática, evaluar high intent
    // SOLO si el mensaje NO fue clasificado como negativo ni auto-responder.
    if ($thread && trim((string)($thread['qualified_reply_sent_at'] ?? '')) !== '' && (string)($thread['stage'] ?? '') !== 'discarded') {
        if (comercial_reply_is_high_intent_after_followup($text)) {
            return array('classification' => 'very_hot', 'reason' => 'reply_after_auto_followup_high_intent');
        }
    }

    // ── Fix #2: detectar saludos y frases conversacionales ──
    $normalizedText = comercial_text_fold($text);
    $greetings = array('hola', 'hola buenas', 'buenos dias', 'buenas tardes', 'buenas noches', 'buenas',
        'hey', 'holi', 'saludos', 'que tal', 'como estas', 'como va', 'todo bien',
        'como te va', 'que hay', 'como andas', 'hola que tal', 'epale', 'ola',
        'buen dia', 'buenas vibras', 'hola buenas tardes', 'hola buenos dias');
    foreach ($greetings as $greeting) {
        if ($normalizedText === comercial_text_fold($greeting) || comercial_text_contains_keyword($normalizedText, $greeting)) {
            return array('classification' => 'greeting', 'reason' => 'greeting_detected');
        }
    }

    // Preguntas genéricas que muestran interés mínimo
    $curiosityPhrases = array('quien eres', 'de donde', 'como conseguiste', 'como me encontraste',
        'por que me escribes', 'que es esto', 'que ofreces', 'de que va',
        'no entiendo', 'explicame', 'a que te dedicas', 'cual es el tema');
    foreach ($curiosityPhrases as $phrase) {
        if (comercial_text_contains_keyword($normalizedText, $phrase)) {
            return array('classification' => 'curious', 'reason' => 'curiosity_detected');
        }
    }

    // Preguntas sobre información específica (casi qualified)
    $infoQuestions = array('cuanto', 'precio', 'cuota', 'cuotas', 'pago', 'cuesta',
        'valor', 'tarifa', 'gratis', 'coste', 'inversion', 'inversión',
        'requisitos', 'necesito', 'hace falta', 'como funciona', 'como es',
        'cuando', 'donde', 'ubicacion', 'direccion', 'horario', 'hora');
    foreach ($infoQuestions as $word) {
        if (comercial_text_contains_keyword($normalizedText, $word)) {
            return array('classification' => 'qualified', 'reason' => 'info_question:' . $word);
        }
    }

    $positiveReason = comercial_reply_positive_reason($text, $process);
    if ($positiveReason !== '') {
        return array('classification' => 'qualified', 'reason' => $positiveReason);
    }

    // Si es un mensaje corto que no encaja en nada, al menos clasificarlo como responded
    // para que el bot intente mantener la conversación
    return array('classification' => 'responded', 'reason' => 'generic_reply');
}

function comercial_pick_generic_unmatched_followup($thread, $text) {
    $pool = array(
        "Hola \u{1F60A} \u{00BF}En qu\u{00E9} puedo ayudarte? Cu\u{00E9}ntame un poco m\u{00E1}s y te explico sin compromiso \u{1F609}",
        "\u{00A1}Hola! \u{1F44B} Gracias por escribirme. \u{00BF}Qu\u{00E9} te trae por aqu\u{00ED}? Estoy aqu\u{00ED} para lo que necesites \u{1F60A}",
        "Hola \u{1F64C} No te conozco a\u{00FA}n, pero me alegra que me escribas. \u{00BF}En qu\u{00E9} tema andas interesado?",
        "\u{00A1}Hey! \u{1F44B}\u{1F3FB} Cu\u{00E9}ntame un poquito m\u{00E1}s de ti y vemos c\u{00F3}mo puedo ayudarte. Sin presiones \u{1F60C}",
        "Hola \u{1F31F} Me encanta que me hayas escrito. \u{00BF}Hay algo en concreto en lo que pueda orientarte?",
        "\u{00A1}Buenas! \u{270C}\u{00BF}Me dices qu\u{00E9} necesitas y te cuento? Estoy para ayudarte sin compromiso \u{1F91D}",
        "Hola \u{1F44B} \u{00BF}C\u{00F3}mo est\u{00E1}s? Cu\u{00E9}ntame en qu\u{00E9} puedo servirte y te doy toda la info que quieras \u{1F4AC}",
        "\u{00A1}Hola hola! \u{1F60A} Gracias por contactarme. \u{00BF}Me cuentas un poco qu\u{00E9} buscas o en qu\u{00E9} te puedo ayudar?",
        "Hola \u{1F31F} Me alegra tu mensaje. \u{00BF}Hay algo que te gustar\u{00ED}a saber? Pregunta con confianza \u{1F609}",
        "\u{00A1}Hola! \u{1F44B}\u{00BF}Qu\u{00E9} tal? Cu\u{00E9}ntame un poco m\u{00E1}s y vemos c\u{00F3}mo puedo echarte una mano \u{1F91D}",
    );
    $index = mt_rand(0, count($pool) - 1);
    return (string)$pool[$index];
}

function comercial_handle_inbound_message($payload) {
    $payload = is_array($payload) ? $payload : array();
    $fromPhone = comercial_only_digits((string)($payload['from'] ?? $payload['from_phone'] ?? $payload['phone'] ?? ''));
    $toPhone = comercial_only_digits((string)($payload['to'] ?? $payload['to_phone'] ?? ''));
    $linePort = trim((string)($payload['port'] ?? $payload['waha_port'] ?? ''));
    $text = trim((string)($payload['text'] ?? $payload['body'] ?? $payload['message'] ?? ''));
    $messageId = trim((string)($payload['message_id'] ?? ''));

    if ($fromPhone === '') {
        return array('ok' => true, 'ignored' => 'no_sender', 'note' => 'Webhook sin remitente identificable — ignorado sin reintento.');
    }
    if ($text === '') {
        // Mensajes sin texto (escribiendo, recibos de lectura, reacciones, multimedia sin caption...)
        // Devolvemos ok=true para que WAHA no reintente sin parar (antes devolvía 422).
        return array('ok' => true, 'ignored' => 'empty_text', 'note' => 'Mensaje sin texto — ignorado sin reintento.');
    }

    $thread = comercial_find_open_thread_for_inbound($fromPhone, $toPhone, $linePort);
    if (!$thread) {
        $thread = comercial_register_unmatched_inbound_thread($payload);
        $genericProcess = comercial_generic_inbound_process();
        $classificationData = comercial_classify_reply($genericProcess, $text, $thread);
        $originalClassification = (string)($classificationData['classification'] ?? 'opened');
        $classification = $originalClassification;
        $intentReason = (string)($classificationData['reason'] ?? 'thread_created_from_unmatched_inbound');
        if ($classification === 'negative') {
            $thread = comercial_thread_apply_stage($thread, 'discarded');
        } elseif ($classification === 'autoresponder') {
            $thread = comercial_thread_apply_stage($thread, 'autoresponder');
        } elseif ($classification === 'qualified') {
            $thread = comercial_thread_apply_stage($thread, 'qualified');
        } else {
            $classification = 'opened';
            if ($intentReason === '') {
                $intentReason = 'thread_created_from_unmatched_inbound';
            }
            $thread = comercial_thread_apply_stage($thread, 'opened');
        }
        // No enviar followup si es auto-responder
        if ($originalClassification !== 'autoresponder') {
        if ($originalClassification === 'greeting' || $originalClassification === 'curious') {
            $genericFollowup = comercial_pick_generic_unmatched_followup($thread, $text);
            if ($genericFollowup !== '') {
                $send = comercial_send_thread_message($thread, $genericFollowup, array('event_type' => 'unmatched_greeting_reply'));
                if (!empty($send['ok'])) {
                    $thread = comercial_normalize_thread((array)($send['thread'] ?? $thread));
                    $thread['last_bot_reply_at'] = now_datetime();
                    $thread['last_bot_reply_text'] = $genericFollowup;
                    $thread['auto_turn_count'] = 1;
                }
            }
        }
        } // fin bloque autoresponder exclusion
        comercial_upsert_thread($thread);
        comercial_event_append('inbound_unmatched', array(
            'from' => $fromPhone,
            'to' => $toPhone,
            'port' => $linePort,
            'text' => $text,
            'thread_id' => (string)($thread['id'] ?? ''),
            'target_phone' => $fromPhone,
            'classification' => $classification,
        ));
        comercial_create_reply_aviso($thread, $classification, $text, $intentReason, $messageId);
        return array(
            'ok' => true,
            'thread_id' => (string)($thread['id'] ?? ''),
            'classification' => $classification,
            'intent_reason' => $intentReason,
            'action' => $classification === 'opened' ? 'thread_opened' : 'thread_created',
            'target_phone' => $fromPhone,
        );
    }

    // ── Fix #6: thread-level inbound lock — previene respuestas duplicadas en ráfagas ──
    $threadLock = comercial_thread_acquire_inbound_lock($thread['id']);
    if (!$threadLock) {
        // Otro request ya está procesando este hilo → actualización ligera sin auto-reply
        $thread['replies_count'] = (int)$thread['replies_count'] + 1;
        $thread['last_inbound_text'] = $text;
        $thread['prior_inbound_text'] = $text; // Actualizar para que el cooldown funcione correctamente en ráfagas
        $thread['last_contact_at'] = now_datetime();
        $thread['updated_at'] = now_datetime();
        $thread['last_inbound_processed_at'] = now_datetime();
        // Fix Bug 1: si ya había una respuesta del bot reciente, extender last_bot_reply_at
        // para que el cooldown de 180s funcione correctamente cuando se libere el lock.
        if (trim((string)($thread['last_bot_reply_at'] ?? '')) !== '') {
            $thread['last_bot_reply_at'] = now_datetime();
        }
        comercial_upsert_thread($thread);
        comercial_event_append('thread_busy', array(
            'thread_id' => (string)$thread['id'],
            'target_phone' => $thread['target_phone'],
            'text_preview' => mb_substr($text, 0, 120),
        ));
        return array(
            'ok' => true,
            'thread_id' => $thread['id'],
            'classification' => 'thread_busy',
            'intent_reason' => 'concurrent_processing',
            'action' => 'thread_busy',
            'target_phone' => (string)$thread['target_phone'],
        );
    }

    try {

    $process = comercial_get_process((string)$thread['process_id']);
    if (!$process) {
        $process = comercial_generic_inbound_process();
    }

    $thread['replies_count'] = (int)$thread['replies_count'] + 1;
    $thread['last_inbound_text'] = $text;
    $thread['last_contact_at'] = now_datetime();
    $thread['updated_at'] = now_datetime();
    $thread['last_inbound_processed_at'] = now_datetime();

    // Auto-reopen: si el hilo fue escalado a humano pero no hubo actividad humana
    // en los últimos 30 minutos, devolver el control al bot automáticamente.
    if (!empty($thread['human_taken'])) {
        $lastHumanAt = trim((string)($thread['last_human_reply_at'] ?? $thread['updated_at'] ?? ''));
        $lastHumanTs = $lastHumanAt !== '' ? strtotime($lastHumanAt) : 0;
        $humanInactiveMinutes = 30;
        if ($lastHumanTs === 0 || (time() - $lastHumanTs) > ($humanInactiveMinutes * 60)) {
            $thread['human_taken'] = 0;
            $thread['auto_turn_count'] = 0; // resetear turnos para que el bot pueda volver a responder
            comercial_event_append('human_reopen_auto', array(
                'thread_id' => (string)$thread['id'],
                'process_slug' => (string)($thread['process_slug'] ?? ''),
                'target_phone' => (string)($thread['target_phone'] ?? ''),
                'reason' => 'human_inactive_' . $humanInactiveMinutes . 'min',
            ));
        }
    }

    // ── Fix Bug 3c (v2): resetear auto_turn_count si el último contacto fue hace más de 24h ──
    // Se hace aquí (en el handler) en vez de en comercial_decide_inbound_action para que
    // el cambio se persista al llamar a comercial_upsert_thread más adelante.
    $lastContactAt = trim((string)($thread['last_contact_at'] ?? ''));
    if ($lastContactAt !== '' && strtotime($lastContactAt) < (time() - 86400)) {
        $thread['auto_turn_count'] = 0;
        comercial_event_append('auto_turn_reset_24h', array(
            'thread_id' => (string)$thread['id'],
            'process_slug' => (string)($thread['process_slug'] ?? ''),
            'target_phone' => (string)($thread['target_phone'] ?? ''),
            'last_contact_at' => $lastContactAt,
        ));
    }

    // ── Fix #1: cooldown anti-duplicado (protege también contra ráfagas con textos distintos) ──
    // Si el bot ya contestó hace menos de 180s, no volvemos a enviar otra respuesta automática
    // independientemente de si el texto es similar o no (ráfaga "Hola" + "Me interesa").
    $botCooldownSec = 180;
    $lastBotReplyAt = trim((string)($thread['last_bot_reply_at'] ?? ''));
    $lastBotReplyTs = $lastBotReplyAt !== '' ? strtotime($lastBotReplyAt) : 0;
    $inCooldown = ($lastBotReplyTs > 0 && (time() - $lastBotReplyTs) < $botCooldownSec);
    if ($inCooldown) {
        $thread['prior_inbound_text'] = $text;
        comercial_upsert_thread($thread);
        comercial_event_append('reply_received', array(
            'thread_id' => $thread['id'],
            'process_slug' => $thread['process_slug'],
            'target_phone' => $thread['target_phone'],
            'classification' => 'cooldown_skip',
            'intent_reason' => 'bot_reply_cooldown_burst',
            'decision' => 'skipped_burst',
            'text' => $text,
        ));
        return array(
            'ok' => true,
            'thread_id' => $thread['id'],
            'classification' => 'cooldown_skip',
            'intent_reason' => 'bot_reply_cooldown_burst',
            'action' => 'skipped_burst',
            'target_phone' => (string)$thread['target_phone'],
        );
    }

    $classificationData = comercial_classify_reply($process, $text, $thread);
    $classification = (string)($classificationData['classification'] ?? 'responded');
    $intentReason = (string)($classificationData['reason'] ?? '');
    $decision = comercial_decide_inbound_action($thread, $process, $classification, $text);
    $thread['last_decision'] = trim((string)($decision['action'] ?? 'legacy'));
    $thread['last_confidence'] = (float)($decision['confidence'] ?? 0);
    $eventPayload = array(
        'thread_id' => $thread['id'],
        'process_slug' => $thread['process_slug'],
        'target_phone' => $thread['target_phone'],
        'classification' => $classification,
        'intent_reason' => $intentReason,
        'decision' => $thread['last_decision'],
        'confidence' => $thread['last_confidence'],
        'text' => $text,
    );
    if (comercial_thread_is_test_probe($thread)) {
        $eventPayload['test_probe'] = 1;
        $eventPayload['test_key'] = trim((string)($thread['test_key'] ?? comercial_test_probe_key()));
    }
    comercial_event_append('reply_received', $eventPayload);
    comercial_event_append('decision_made', array(
        'thread_id' => $thread['id'],
        'classification' => $classification,
        'decision' => $thread['last_decision'],
        'confidence' => $thread['last_confidence'],
        'reason' => trim((string)($decision['reason'] ?? '')),
    ));

    if ($classification === 'negative') {
        $thread = comercial_thread_apply_stage($thread, 'discarded');
        comercial_upsert_thread($thread);
        comercial_create_reply_aviso($thread, $classification, $text, $intentReason, $messageId);
        return array(
            'ok' => true,
            'thread_id' => $thread['id'],
            'classification' => $classification,
            'intent_reason' => $intentReason,
            'action' => 'discarded',
            'target_phone' => (string)$thread['target_phone'],
            'test_probe' => comercial_thread_is_test_probe($thread) ? 1 : 0,
            'test_key' => trim((string)($thread['test_key'] ?? '')),
        );
    }

    // ── Fix U3: auto-responder detectado → silenciar sin followup ──
    if ($classification === 'autoresponder') {
        $thread = comercial_thread_apply_stage($thread, 'autoresponder');
        comercial_upsert_thread($thread);
        comercial_event_append('autoresponder_detected', array(
            'thread_id' => $thread['id'],
            'process_slug' => $thread['process_slug'],
            'target_phone' => $thread['target_phone'],
            'text_preview' => mb_substr($text, 0, 200),
        ));
        return array(
            'ok' => true,
            'thread_id' => $thread['id'],
            'classification' => $classification,
            'intent_reason' => $intentReason,
            'action' => 'autoresponder_silenced',
            'target_phone' => (string)$thread['target_phone'],
            'test_probe' => comercial_thread_is_test_probe($thread) ? 1 : 0,
            'test_key' => trim((string)($thread['test_key'] ?? '')),
        );
    }

    $autoFollowup = !empty($process['auto_followup']) && !empty(comercial_get_settings()['auto_followup_enabled']);

    // ── greeting y curious → usar followup_templates del proceso (igual que responded/qualified) ──
    if ($classification === 'greeting' || $classification === 'curious') {
        $autoFollowup = !empty($process['auto_followup']) && !empty(comercial_get_settings()['auto_followup_enabled']);
        $maxTurnsGc = max(1, (int)($process['conversation_max_auto_turns'] ?? comercial_get_settings()['conversation_max_auto_turns'] ?? 2));
        $gcReplied = false;

        // ── Fix U2: determinar si el cliente SOLO saludó o también preguntó ──
        // 'greeting' sin preguntas vs 'curious' (que ya implica pregunta)
        $isGreetingOnly = ($classification === 'greeting');
        $thread['_greeting_only'] = $isGreetingOnly;

        if ($autoFollowup && ($decision['action'] ?? '') === 'auto_reply_second_turn' && !$inCooldown && comercial_can_send_auto_followup($thread, $maxTurnsGc) && (int)$thread['auto_turn_count'] < $maxTurnsGc) {
            // Usar siempre los followup_templates configurados en el proceso.
            // Si ya se agotaron los textos y quedan turnos disponibles, improvisar con IA.
            $followup = comercial_pick_followup_or_improvise($thread, $process, $text);
            $lastBotText = trim((string)($thread['last_bot_reply_text'] ?? ''));
            if ($lastBotText !== '' && comercial_text_fold($lastBotText) === comercial_text_fold($followup)) {
                comercial_event_append('auto_reply_duplicate_skipped', array('thread_id' => $thread['id'], 'reason' => 'same_text_as_previous'));
            } elseif ($followup !== '') {
                $send = comercial_send_thread_message($thread, $followup, array('event_type' => 'greeting_reply_sent'));
                if (!empty($send['ok'])) {
                    $thread = comercial_normalize_thread((array)($send['thread'] ?? $thread));
                    $thread['qualified_reply_sent_at'] = now_datetime();
                    $thread['last_bot_reply_at'] = now_datetime();
                    $thread['last_bot_reply_text'] = $followup;
                    $thread['prior_inbound_text'] = $text;
                    $thread['auto_turn_count'] = (int)$thread['auto_turn_count'] + 1;
                    $gcReplied = true;
                }
            }
        }
        $thread = comercial_thread_apply_stage($thread, 'responded');
        comercial_upsert_thread($thread);
        comercial_create_reply_aviso($thread, $classification, $text, $intentReason, $messageId);
        return array(
            'ok' => true,
            'thread_id' => $thread['id'],
            'classification' => $classification,
            'intent_reason' => $intentReason,
            'action' => $gcReplied ? 'greeting_reply_sent' : 'greeting_or_curious_handled',
            'target_phone' => (string)$thread['target_phone'],
            'test_probe' => comercial_thread_is_test_probe($thread) ? 1 : 0,
            'test_key' => trim((string)($thread['test_key'] ?? '')),
        );
    }

    if ($classification === 'responded') {
        if (($decision['action'] ?? '') === 'defer') {
            // ── T2.3: respetar límite de defers configurado ──
            $maxDefers = max(1, (int)($process['conversation_max_defers'] ?? comercial_get_settings()['conversation_max_defers'] ?? 2));
            if ((int)$thread['defer_count'] >= $maxDefers) {
                // Límite alcanzado → escalar a humano en lugar de otro defer
                $thread['human_taken'] = 1;
                $thread = comercial_thread_apply_stage($thread, 'responded');
                comercial_upsert_thread($thread);
                comercial_create_reply_aviso($thread, 'very_hot', $text, 'escalation_max_defers_reached', $messageId);
                return array(
                    'ok' => true,
                    'thread_id' => $thread['id'],
                    'classification' => $classification,
                    'intent_reason' => $intentReason,
                    'action' => 'escalated_human_max_defers',
                    'target_phone' => (string)$thread['target_phone'],
                );
            }
            $thread['defer_count'] = (int)$thread['defer_count'] + 1;
            $thread['next_bot_action_at'] = date('Y-m-d H:i:s', time() + ((int)$thread['defer_count'] * 300));
            $thread = comercial_thread_apply_stage($thread, 'responded');
            comercial_upsert_thread($thread);
            return array(
                'ok' => true,
                'thread_id' => $thread['id'],
                'classification' => $classification,
                'intent_reason' => $intentReason,
                'action' => 'deferred',
                'target_phone' => (string)$thread['target_phone'],
            );
        }
        if (($decision['action'] ?? '') === 'escalate_human') {
            $thread['human_taken'] = 1;
            $thread = comercial_thread_apply_stage($thread, 'responded');
            comercial_upsert_thread($thread);
            comercial_create_reply_aviso($thread, 'very_hot', $text, 'escalation_critical', $messageId);
            return array(
                'ok' => true,
                'thread_id' => $thread['id'],
                'classification' => $classification,
                'intent_reason' => $intentReason,
                'action' => 'escalated_human',
                'target_phone' => (string)$thread['target_phone'],
            );
        }
        $settings = comercial_get_settings();
        $maxTurns = max(1, (int)($process['conversation_max_auto_turns'] ?? $settings['conversation_max_auto_turns'] ?? 2));
        if (($decision['action'] ?? '') === 'auto_reply_second_turn' && $autoFollowup && !$inCooldown && comercial_can_send_auto_followup($thread, $maxTurns) && (int)$thread['auto_turn_count'] < $maxTurns) {
            // Usar templates configurados o improvisar con IA cuando se agotan
            $followup = comercial_pick_followup_or_improvise($thread, $process, $text);
            $replied = false;
            if ($followup !== '') {
                $lastBotText = trim((string)($thread['last_bot_reply_text'] ?? ''));
                if ($lastBotText !== '' && comercial_text_fold($lastBotText) === comercial_text_fold($followup)) {
                    comercial_event_append('auto_reply_duplicate_skipped', array(
                        'thread_id' => $thread['id'],
                        'reason' => 'same_text_as_previous',
                    ));
                } else {
                    $send = comercial_send_thread_message($thread, $followup, array('event_type' => 'qualified_auto_reply_sent'));
                    if (!empty($send['ok'])) {
                        $thread = comercial_normalize_thread((array)($send['thread'] ?? $thread));
                        $thread['qualified_reply_sent_at'] = now_datetime();
                        $thread['last_bot_reply_at'] = now_datetime();
                        $thread['last_bot_reply_text'] = $followup;
                        $thread['prior_inbound_text'] = $text;
                        $thread['auto_turn_count'] = (int)$thread['auto_turn_count'] + 1;
                        $replied = true;
                    }
                }
            }
            $thread = comercial_thread_apply_stage($thread, 'responded');
            comercial_upsert_thread($thread);
            comercial_create_reply_aviso($thread, $classification, $text, $intentReason, $messageId);
            return array(
                'ok' => true,
                'thread_id' => $thread['id'],
                'classification' => $classification,
                'intent_reason' => $intentReason,
                'action' => $replied ? 'qualified_reply_sent' : 'responded',
                'target_phone' => (string)$thread['target_phone'],
                'test_probe' => comercial_thread_is_test_probe($thread) ? 1 : 0,
                'test_key' => trim((string)($thread['test_key'] ?? '')),
            );
        }
        // Fallback: can_send_auto_followup no aplica pero quedan turnos y no hay cooldown
        if (($decision['action'] ?? '') === 'auto_reply_second_turn' && $autoFollowup && !$inCooldown && (int)$thread['auto_turn_count'] < $maxTurns) {
            $fallbackMsg = comercial_pick_followup_or_improvise($thread, $process, $text);
            if ($fallbackMsg !== '') {
                $lastBotText = trim((string)($thread['last_bot_reply_text'] ?? ''));
                if ($lastBotText === '' || comercial_text_fold($lastBotText) !== comercial_text_fold($fallbackMsg)) {
                    $sendFallback = comercial_send_thread_message($thread, $fallbackMsg, array('event_type' => 'responded_fallback_sent'));
                    if (!empty($sendFallback['ok'])) {
                        $thread = comercial_normalize_thread((array)($sendFallback['thread'] ?? $thread));
                        $thread['last_bot_reply_at'] = now_datetime();
                        $thread['last_bot_reply_text'] = $fallbackMsg;
                        $thread['prior_inbound_text'] = $text;
                        $thread['auto_turn_count'] = (int)$thread['auto_turn_count'] + 1;
                    }
                }
            }
        }
        $thread = comercial_thread_apply_stage($thread, 'responded');
        comercial_upsert_thread($thread);
        comercial_create_reply_aviso($thread, $classification, $text, $intentReason, $messageId);
        return array(
            'ok' => true,
            'thread_id' => $thread['id'],
            'classification' => $classification,
            'intent_reason' => $intentReason,
            'action' => isset($sendFallback) && !empty($sendFallback['ok']) ? 'responded_fallback_sent' : 'responded',
            'target_phone' => (string)$thread['target_phone'],
            'test_probe' => comercial_thread_is_test_probe($thread) ? 1 : 0,
            'test_key' => trim((string)($thread['test_key'] ?? '')),
        );
    }

    if ($classification === 'very_hot') {
        $aiSecondTurnSent = false;
        $aiSecondTurnError = '';
        if (($decision['action'] ?? '') === 'auto_reply_second_turn' && comercial_decision_allows_ai_second_turn($thread, $classification, $process)) {
            $objective = 'responder segunda vuelta, mantener interés y mover a cierre o llamada';
            $ai = comercial_ai_generate_contextual_followup($thread, (string)($thread['process_slug'] ?? ''), $objective);
            if (!empty($ai['ok']) && trim((string)($ai['text'] ?? '')) !== '') {
                $thread['last_ai_suggested_reply'] = trim((string)$ai['text']);
                $thread['last_ai_suggested_at'] = now_datetime();
                $sendAi = comercial_send_thread_message($thread, $thread['last_ai_suggested_reply'], array(
                    'event_type' => 'ai_second_turn_sent',
                ));
                if (!empty($sendAi['ok'])) {
                    $thread = comercial_normalize_thread((array)($sendAi['thread'] ?? $thread));
                    $thread['auto_turn_count'] = (int)$thread['auto_turn_count'] + 1;
                    $thread['defer_count'] = 0;
                    $thread['last_bot_reply_at'] = now_datetime();
                    $thread['last_bot_reply_text'] = $thread['last_ai_suggested_reply'];
                    $thread['prior_inbound_text'] = $text;
                    $aiSecondTurnSent = true;
                } else {
                    $aiSecondTurnError = trim((string)($sendAi['error'] ?? 'ai_second_turn_send_failed'));
                }
            } else {
                $aiSecondTurnError = trim((string)($ai['error'] ?? 'ai_second_turn_generation_failed'));
            }
        }
        $thread = comercial_thread_apply_stage($thread, 'very_hot');
        comercial_upsert_thread($thread);

        // ── Fix #3: notificación directa al dueño en vez de avisos del sistema ──
        $hotNotified = comercial_send_hot_summary_to_owner($thread, $text, $messageId);

        return array(
            'ok' => true,
            'thread_id' => $thread['id'],
            'classification' => $classification,
            'intent_reason' => $intentReason,
            'action' => $aiSecondTurnSent ? 'very_hot_ai_second_turn_sent' : 'very_hot',
            'followup_error' => $aiSecondTurnError,
            'hot_notified' => $hotNotified,
            'target_phone' => (string)$thread['target_phone'],
            'test_probe' => comercial_thread_is_test_probe($thread) ? 1 : 0,
            'test_key' => trim((string)($thread['test_key'] ?? '')),
        );
    }

    if ($classification === 'qualified') {
        $thread = comercial_thread_apply_stage($thread, 'qualified');
        $qualifiedReplySent = false;
        $followupError = '';
        $maxTurnsQ = max(1, (int)($process['conversation_max_auto_turns'] ?? comercial_get_settings()['conversation_max_auto_turns'] ?? 2));
        if ($autoFollowup && !$inCooldown && comercial_can_send_auto_followup($thread, $maxTurnsQ) && (int)$thread['auto_turn_count'] < $maxTurnsQ) {
            // Usar templates configurados o improvisar con IA cuando se agotan
            $followup = comercial_pick_followup_or_improvise($thread, $process, $text);
            if ($followup !== '') {
                $send = comercial_send_thread_message($thread, $followup, array(
                    'qualified_reply_sent' => true,
                    'event_type' => 'qualified_auto_reply_sent',
                ));
                if (!empty($send['ok'])) {
                    $thread = comercial_normalize_thread((array)($send['thread'] ?? $thread));
                    $thread['last_ai_suggested_reply'] = trim((string)$followup);
                    $thread['last_ai_suggested_at'] = now_datetime();
                    $thread['last_bot_reply_at'] = now_datetime();
                    $thread['last_bot_reply_text'] = $followup;
                    $thread['prior_inbound_text'] = $text;
                    $thread['auto_turn_count'] = (int)$thread['auto_turn_count'] + 1;
                    $qualifiedReplySent = true;
                } else {
                    $followupError = trim((string)($send['error'] ?? 'No se pudo enviar el seguimiento automático.'));
                    $errorPayload = array(
                        'thread_id' => (string)$thread['id'],
                        'process_slug' => (string)$thread['process_slug'],
                        'target_phone' => (string)$thread['target_phone'],
                        'classification' => 'qualified',
                        'intent_reason' => $intentReason,
                        'error' => $followupError,
                    );
                    if (comercial_thread_is_test_probe($thread)) {
                        $errorPayload['test_probe'] = 1;
                        $errorPayload['test_key'] = trim((string)($thread['test_key'] ?? comercial_test_probe_key()));
                    }
                    comercial_event_append('qualified_auto_reply_failed', $errorPayload);
                }
            }
        }
        comercial_upsert_thread($thread);
        comercial_create_reply_aviso($thread, $classification, $text, $intentReason, $messageId);
        if (!empty($process['auto_create_lead'])) {
            list($okLead, $lead) = comercial_promote_thread_to_lead($thread['id']);
            return array(
                'ok' => true,
                'thread_id' => $thread['id'],
                'classification' => $classification,
                'intent_reason' => $intentReason,
                'action' => ($okLead ? 'lead_created' : ($qualifiedReplySent ? 'qualified_reply_sent' : 'qualified_only')),
                'followup_error' => $followupError,
                'lead' => $lead,
                'target_phone' => (string)$thread['target_phone'],
                'test_probe' => comercial_thread_is_test_probe($thread) ? 1 : 0,
                'test_key' => trim((string)($thread['test_key'] ?? '')),
            );
        }
        return array(
            'ok' => true,
            'thread_id' => $thread['id'],
            'classification' => $classification,
            'intent_reason' => $intentReason,
            'action' => ($qualifiedReplySent ? 'qualified_reply_sent' : 'qualified'),
            'followup_error' => $followupError,
            'target_phone' => (string)$thread['target_phone'],
            'test_probe' => comercial_thread_is_test_probe($thread) ? 1 : 0,
            'test_key' => trim((string)($thread['test_key'] ?? '')),
        );
    }

    comercial_upsert_thread($thread);
    comercial_create_reply_aviso($thread, $classification, $text, $intentReason, $messageId);
    return array(
        'ok' => true,
        'thread_id' => $thread['id'],
        'classification' => $classification,
        'intent_reason' => $intentReason,
        'action' => 'saved',
        'target_phone' => (string)$thread['target_phone'],
        'test_probe' => comercial_thread_is_test_probe($thread) ? 1 : 0,
        'test_key' => trim((string)($thread['test_key'] ?? '')),
    );
} finally {
    comercial_thread_release_inbound_lock($threadLock);
}
}

function comercial_collect_summary() {
    $processes = comercial_get_processes();
    $threads = comercial_get_threads();
    $lineStates = comercial_get_line_states();
    $today = today_date();
    $events = comercial_events_recent(400);

    $stats = array(
        'processes_total' => count($processes),
        'processes_enabled' => 0,
        'line_active' => 0,
        'line_warning' => 0,
        'line_paused' => 0,
        'threads_open' => 0,
        'threads_qualified' => 0,
        'threads_hot' => 0,
        'sends_today' => 0,
        'replies_today' => 0,
        'leads_today' => 0,
    );

    foreach ($processes as $p) if (!empty($p['enabled'])) $stats['processes_enabled']++;
    foreach ($lineStates as $state) {
        if ((string)$state['status'] === 'paused') $stats['line_paused']++;
        elseif ((string)$state['status'] === 'warning') $stats['line_warning']++;
        else $stats['line_active']++;
    }
    foreach ($threads as $thread) {
        if ((string)$thread['status'] === 'open') $stats['threads_open']++;
        if ((string)$thread['stage'] === 'qualified') $stats['threads_qualified']++;
        if ((string)$thread['stage'] === 'very_hot') $stats['threads_hot']++;
    }
    foreach ($events as $event) {
        if ((string)($event['date'] ?? '') !== $today) continue;
        if ((string)$event['type'] === 'send_ok') $stats['sends_today']++;
        if ((string)$event['type'] === 'reply_received') $stats['replies_today']++;
        if ((string)$event['type'] === 'lead_created') $stats['leads_today']++;
    }
    return $stats;
}

function comercial_get_thread_by_id($threadId) {
    $threadId = trim((string)$threadId);
    if ($threadId === '') {
        return null;
    }
    foreach (comercial_get_threads() as $row) {
        if ((string)($row['id'] ?? '') === $threadId) {
            return $row;
        }
    }
    return null;
}

function comercial_render_thread_timeline_html($history) {
    ob_start();
    echo '<div class="commercial-thread-timeline">';
    foreach ((array)$history as $entry) {
        $direction = trim((string)($entry['direction'] ?? ''));
        $bubbleClass = $direction === 'in' ? 'in' : ($direction === 'out' ? 'out' : 'meta');
        echo '<div class="commercial-thread-entry ' . e($bubbleClass) . '">';
        echo '<div class="commercial-thread-entry-meta">' . e((string)($entry['ts'] ?? '')) . ' · ' . e((string)($entry['label'] ?? '')) . '</div>';
        if (trim((string)($entry['text'] ?? '')) !== '') {
            echo '<div class="commercial-bubble ' . ($direction === 'in' ? 'in' : 'out') . '">' . nl2br(e((string)$entry['text'])) . '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
    return (string)ob_get_clean();
}

function comercial_render_thread_webhook_log_html($webhookLog) {
    ob_start();
    if (!empty($webhookLog)) {
        echo '<div class="table-wrap" style="margin-top:8px;"><table><thead><tr><th>Fecha</th><th>Paso</th><th>Detalle</th></tr></thead><tbody>';
        foreach ((array)$webhookLog as $logRow) {
            $logPayload = isset($logRow['payload']) && is_array($logRow['payload']) ? $logRow['payload'] : array();
            echo '<tr>';
            echo '<td>' . e((string)($logRow['ts'] ?? '')) . '</td>';
            echo '<td>' . e((string)($logRow['type'] ?? '')) . '</td>';
            echo '<td>' . e(comercial_render_webhook_log_detail($logPayload)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="muted-small" style="margin-top:6px;">No hay entradas de webhook para este hilo. Si durante la prueba no aparece nada aquí al responder, WAHA no está pegando al endpoint o el endpoint configurado no es este.</div>';
    }
    return (string)ob_get_clean();
}

function comercial_thread_live_payload($threadId) {
    $thread = comercial_get_thread_by_id($threadId);
    if (!$thread) {
        return null;
    }

    $history = comercial_thread_history($thread, 3000);
    $webhookLog = comercial_thread_webhook_log($thread, 80);
    return array(
        'thread_id' => (string)($thread['id'] ?? ''),
        'updated_at' => (string)($thread['updated_at'] ?? ''),
        'stage' => (string)($thread['stage'] ?? ''),
        'stage_label' => comercial_thread_stage_label((string)($thread['stage'] ?? '')),
        'stage_css' => comercial_thread_stage_css_class((string)($thread['stage'] ?? '')),
        'process_slug' => (string)($thread['process_slug'] ?? ''),
        'target_phone' => (string)($thread['target_phone'] ?? ''),
        'line_phone' => (string)($thread['line_phone'] ?? ''),
        'messages_sent_count' => (int)($thread['messages_sent_count'] ?? 0),
        'replies_count' => (int)($thread['replies_count'] ?? 0),
        'last_inbound_text' => (string)($thread['last_inbound_text'] ?? ''),
        'timeline_html' => comercial_render_thread_timeline_html($history),
        'webhook_log_html' => comercial_render_thread_webhook_log_html($webhookLog),
    );
}

function comercial_allocate_targets_preview() {
    $settings = comercial_get_settings();
    $processes = comercial_get_processes();
    $out = array();
    foreach ($processes as $p) {
        $plan = comercial_calculate_interval_plan($p, $settings);
        $out[] = array(
            'id' => $p['id'],
            'nombre' => $p['nombre'],
            'enabled' => !empty($p['enabled']),
            'daily_percent' => (float)$p['daily_target_percent'],
            'daily_target' => (int)$plan['target'],
            'interval_min' => (int)round(((int)$plan['min_seconds']) / 60),
            'interval_max' => (int)round(((int)$plan['max_seconds']) / 60),
            'assigned_lines' => count((array)$p['assigned_line_ids']),
        );
    }
    return $out;
}

function render_comercial_page() {
    $tab = trim((string)request_get('tab', 'resumen'));
    $tabs = comercial_page_tabs();
    if (!isset($tabs[$tab])) $tab = 'resumen';

    $processes = comercial_get_processes();
    $selectedProcessId = trim((string)request_get('edit', ''));
    if ($selectedProcessId === '' && !empty($processes)) $selectedProcessId = (string)$processes[0]['id'];
    $selectedProcess = $selectedProcessId !== '' ? comercial_get_process($selectedProcessId) : null;
    $lines = comercial_list_lines();
    $linesIndexed = comercial_list_lines_indexed();
    $threads = comercial_get_threads();
    $leads = comercial_get_leads();
    $summary = comercial_collect_summary();
    $settings = comercial_get_settings();
    $anuncios = storage_read('anuncios.json');

    page_header('Comercial', 'Motor unificado de envíos, seguimiento y conversión');
    echo '<div class="subtabs">';
    foreach ($tabs as $slug => $label) {
        echo '<a class="subtab ' . ($tab === $slug ? 'active' : '') . '" href="' . e(comercial_page_url($slug)) . '">' . e($label) . '</a>';
    }
    echo '</div>';

    if ($tab === 'resumen') {
        $probe = comercial_test_probe_summary();
        echo '<section class="panel">';
        echo '<div class="commercial-kpis">';
        comercial_render_kpi('Procesos activos', $summary['processes_enabled'] . ' / ' . $summary['processes_total']);
        comercial_render_kpi('Líneas activas', (string)$summary['line_active']);
        comercial_render_kpi('Líneas en warning', (string)$summary['line_warning']);
        comercial_render_kpi('Líneas pausadas', (string)$summary['line_paused']);
        comercial_render_kpi('Envíos hoy', (string)$summary['sends_today']);
        comercial_render_kpi('Respuestas hoy', (string)$summary['replies_today']);
        comercial_render_kpi('Hilos abiertos', (string)$summary['threads_open']);
        comercial_render_kpi('Cualificados', (string)$summary['threads_qualified']);
        comercial_render_kpi('Muy calientes', (string)$summary['threads_hot']);
        echo '</div>';

        echo '<div class="toolbar" style="margin-top:16px; gap:8px;">';
        echo '<form method="post" style="display:inline-block;">';
        echo '<input type="hidden" name="action" value="comercial_run_tick">';
        echo '<button type="submit" class="btn-primary">Lanzar tick ahora</button>';
        echo '</form>';
        echo '<form method="post" style="display:inline-block;">';
        echo '<input type="hidden" name="action" value="comercial_run_test_probe">';
        echo '<button type="submit" class="btn-secondary-mini">Probar ahora</button>';
        echo '</form>';
        echo '<form method="post" style="display:inline-block;" onsubmit="return confirm(\'¿Reiniciar la prueba comercial?\')">';
        echo '<input type="hidden" name="action" value="comercial_reset_test_probe">';
        echo '<button type="submit" class="btn-secondary-mini">Reiniciar prueba</button>';
        echo '</form>';
        echo '<a class="btn-secondary-mini" href="' . e(comercial_page_url('logs')) . '">Ver logs</a>';
        echo '</div>';

        echo '<div class="info-strip" style="margin-top:14px;">';
        echo '<strong>Prueba automática Plaza</strong><br>';
        echo 'Número objetivo: ';
        crm_render_phone_value((string)($probe['phone'] ?? comercial_test_probe_phone()));
        echo '<br>';
        if (!empty($probe['exists'])) {
            $probeThread = (array)($probe['thread'] ?? array());
            echo 'Estado: ' . e(comercial_thread_stage_label((string)($probeThread['stage'] ?? ''))) . '<br>';
            echo 'Línea origen: ';
            crm_render_phone_value((string)($probeThread['line_phone'] ?? ''));
            echo '<br>';
            echo 'Respuestas: ' . e((string)($probeThread['replies_count'] ?? 0)) . ' · envíos: ' . e((string)($probeThread['messages_sent_count'] ?? 0)) . '<br>';
            echo 'Último inbound: ' . e((string)($probeThread['last_inbound_text'] ?? '-')) . '<br>';
            echo '<a class="mini-link" href="' . e(comercial_page_url('conversaciones', array('view_thread' => (string)($probeThread['id'] ?? '')))) . '">Abrir conversación de prueba</a>';
        } else {
            echo '<span class="muted">Aún no hay ninguna prueba activa. Pulsa "Probar ahora" para enviar el mensaje de Plaza.</span>';
        }
        echo '</div>';

        $preview = comercial_allocate_targets_preview();
        echo '<div class="table-wrap" style="margin-top:16px;">';
        echo '<table><thead><tr><th>Proceso</th><th>Objetivo/día</th><th>Intervalo</th><th>Líneas</th><th>Estado</th></tr></thead><tbody>';
        foreach ($preview as $row) {
            echo '<tr>';
            echo '<td>' . e($row['nombre']) . '</td>';
            echo '<td>' . e((string)$row['daily_target']) . '</td>';
            echo '<td>' . e((string)$row['interval_min']) . '–' . e((string)$row['interval_max']) . ' min</td>';
            echo '<td>' . e((string)$row['assigned_lines']) . '</td>';
            echo '<td>' . ($row['enabled'] ? '<span class="status-pill ok">Activo</span>' : '<span class="status-pill muted">Parado</span>') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '</section>';
        return;
    }

    if ($tab === 'procesos') {
        $previewById = array();
        foreach (comercial_allocate_targets_preview() as $item) {
            $previewById[(string)$item['id']] = $item;
        }

        echo '<section class="panel">';
        echo '<h2>Reparto diario y normalización</h2>';
        echo '<p class="muted" style="margin-top:0;">Aquí se reparte el 100% del volumen diario entre procesos. Los intervalos se calculan automáticamente a partir del porcentaje, el total diario global y la ventana horaria de cada proceso.</p>';
        echo '<form method="post">';
        echo '<input type="hidden" name="action" value="save_comercial_distribution">';
        echo '<div class="form-grid-2">';
        comercial_field_number('global_daily_target', 'Objetivo global / día', $settings['global_daily_target'], '1');
        echo '<div class="field"><label>Total porcentajes</label><input type="text" id="comercial_distribution_total" value="0%" readonly></div>';
        echo '</div>';
        echo '<div class="table-wrap" style="margin-top:12px;"><table><thead><tr><th>Proceso</th><th>Activo</th><th>% diario</th><th>Objetivo/día</th><th>Intervalo calculado</th><th>Ventana</th></tr></thead><tbody>';
        foreach ($processes as $row) {
            $plan = isset($previewById[(string)$row['id']]) ? $previewById[(string)$row['id']] : array('daily_target' => 0, 'interval_min' => 0, 'interval_max' => 0);
            echo '<tr>';
            echo '<td><strong>' . e($row['nombre']) . '</strong><br><span class="muted-small">' . e($row['slug']) . '</span></td>';
            echo '<td>' . (!empty($row['enabled']) ? '<span class="status-pill ok">Encendido</span>' : '<span class="status-pill muted">Apagado</span>') . '</td>';
            echo '<td style="width:140px;"><input type="number" class="commercial-dist-input" data-process-id="' . e((string)$row['id']) . '" name="distribution_percent[' . e((string)$row['id']) . ']" value="' . e((string)$row['daily_target_percent']) . '" min="0" max="100" step="0.1"></td>';
            echo '<td><span data-target-for="' . e((string)$row['id']) . '">' . e((string)$plan['daily_target']) . '</span></td>';
            echo '<td><span data-interval-for="' . e((string)$row['id']) . '">' . e((string)$plan['interval_min']) . '–' . e((string)$plan['interval_max']) . ' min</span></td>';
            echo '<td><span class="muted-small" data-window-for="' . e((string)$row['id']) . '" data-start="' . e((string)$row['window_start_hour']) . '" data-end="' . e((string)$row['window_end_hour']) . '">' . e((string)$row['window_start_hour']) . ':00 → ' . e((string)$row['window_end_hour']) . ':00</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="toolbar" style="margin-top:12px; gap:8px;">';
        echo '<button type="button" class="btn-secondary-mini" onclick="comercialNormalizeDistribution()">Normalizar a 100%</button>';
        echo '<button type="submit" class="btn-primary">Guardar reparto</button>';
        echo '</div>';
        echo '</form>';
        echo '</section>';

        echo '<div class="split-grid">';
        echo '<section class="panel">';
        echo '<h2>Procesos</h2>';
        echo '<div class="table-wrap"><table><thead><tr><th>Proceso</th><th>Estado</th><th>Tipo</th><th>Objetivo</th><th>Próximo run</th><th>Acciones</th></tr></thead><tbody>';
        foreach ($processes as $row) {
            $plan = isset($previewById[(string)$row['id']]) ? $previewById[(string)$row['id']] : array('daily_target' => 0, 'interval_min' => 0, 'interval_max' => 0);
            echo '<tr>';
            echo '<td><strong>' . e($row['nombre']) . '</strong><br><span class="muted-small">' . e($row['slug']) . '</span></td>';
            echo '<td>' . (!empty($row['enabled']) ? '<span class="status-pill ok">Encendido</span>' : '<span class="status-pill muted">Apagado</span>') . '</td>';
            echo '<td>' . e($row['source_type']) . '<br><span class="muted-small">líneas: ' . e((string)count((array)$row['assigned_line_ids'])) . '</span></td>';
            echo '<td>' . e((string)$row['daily_target_percent']) . '%<br><span class="muted-small">' . e((string)$plan['daily_target']) . '/día · ' . e((string)$plan['interval_min']) . '–' . e((string)$plan['interval_max']) . ' min</span></td>';
            echo '<td>' . e((string)($row['next_run_at'] ?: 'Sin planificar')) . '</td>';
            echo '<td>';
            echo '<form method="post" style="display:inline-block; margin-right:6px;">';
            echo '<input type="hidden" name="action" value="toggle_comercial_process_enabled">';
            echo '<input type="hidden" name="id" value="' . e((string)$row['id']) . '">';
            echo '<input type="hidden" name="enabled" value="' . (!empty($row['enabled']) ? '0' : '1') . '">';
            echo '<button type="submit" class="btn-secondary-mini">' . (!empty($row['enabled']) ? 'Apagar' : 'Encender') . '</button>';
            echo '</form>';
            echo '<a class="mini-link" href="' . e(comercial_page_url('procesos', array('edit' => $row['id']))) . '">Editar</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '</section>';

        echo '<section class="panel">';
        echo '<h2>Editar proceso</h2>';
        if ($selectedProcess) {
            $selectedPlan = comercial_calculate_interval_plan($selectedProcess, $settings);
            echo '<div class="info-strip" style="margin-bottom:12px;">';
            echo '<strong>Reparto actual:</strong> ' . e((string)$selectedProcess['daily_target_percent']) . '% · ' . e((string)$selectedPlan['target']) . ' envíos/día<br>';
            echo '<strong>Intervalo calculado:</strong> ' . e((string)round($selectedPlan['min_seconds'] / 60)) . '–' . e((string)round($selectedPlan['max_seconds'] / 60)) . ' min<br>';
            echo '<strong>Ventana activa:</strong> ' . e((string)$selectedProcess['window_start_hour']) . ':00 → ' . e((string)$selectedProcess['window_end_hour']) . ':00';
            echo '</div>';

            echo '<form method="post">';
            echo '<input type="hidden" name="action" value="save_comercial_process">';
            echo '<input type="hidden" name="id" value="' . e($selectedProcess['id']) . '">';
            echo '<div class="form-grid-2">';
            comercial_field_text('nombre', 'Nombre', $selectedProcess['nombre']);
            comercial_field_text('slug', 'Slug', $selectedProcess['slug'], true);
            comercial_field_select('source_type', 'Tipo origen', array('jsonl_queue' => 'Colas JSONL', 'mysql_recent' => 'MySQL reciente'), $selectedProcess['source_type']);
            comercial_field_number('priority', 'Prioridad', $selectedProcess['priority']);
            comercial_field_number('window_start_hour', 'Hora inicio', $selectedProcess['window_start_hour'], '1');
            comercial_field_number('window_end_hour', 'Hora fin', $selectedProcess['window_end_hour'], '1');
            echo '<div class="field"><label>Porcentaje diario</label><input type="text" value="' . e((string)$selectedProcess['daily_target_percent']) . '% (se gestiona arriba)" readonly></div>';
            echo '<div class="field"><label>Intervalo calculado</label><input type="text" value="' . e((string)round($selectedPlan['min_seconds'] / 60)) . '–' . e((string)round($selectedPlan['max_seconds'] / 60)) . ' min" readonly></div>';
            echo '</div>';

            echo '<div class="form-grid-2">';
            comercial_field_text('source_mysql_host', 'MySQL host', $selectedProcess['source_mysql_host']);
            comercial_field_text('source_mysql_db', 'MySQL DB', $selectedProcess['source_mysql_db']);
            comercial_field_text('source_mysql_user', 'MySQL user', $selectedProcess['source_mysql_user']);
            comercial_field_text('source_mysql_pass', 'MySQL pass', $selectedProcess['source_mysql_pass']);
            echo '</div>';
            comercial_field_textarea('source_mysql_query', 'Consulta MySQL', $selectedProcess['source_mysql_query'], 5);
            comercial_field_text('source_phone_field', 'Campo teléfono JSONL', $selectedProcess['source_phone_field']);
            comercial_field_textarea('source_queue_files', 'Rutas de colas JSONL (una por línea)', comercial_array_to_textarea($selectedProcess['source_queue_files']), 5);
            echo '<div class="field-help" style="margin-top:-6px; margin-bottom:12px;">Para este proyecto, las colas por defecto viven en <code>data/comercial_queues/</code>. Cada línea del fichero debe ser un JSON y el teléfono debe ir en el campo <code>' . e($selectedProcess['source_phone_field']) . '</code> (por defecto <code>group_key</code>).</div>';
            comercial_field_textarea('message_templates', 'Textos iniciales (una variante por bloque, separados por línea con ---)', comercial_templates_to_textarea(comercial_process_message_pool($selectedProcess, 'message_templates')), 18);
            comercial_field_textarea('followup_templates', 'Textos de seguimiento (una variante por bloque, separados por línea con ---)', comercial_templates_to_textarea(comercial_process_message_pool($selectedProcess, 'followup_templates')), 10);
            comercial_field_textarea('positive_keywords', 'Palabras qualified (una por línea)', comercial_array_to_textarea($selectedProcess['positive_keywords']), 5);
            comercial_field_textarea('negative_keywords', 'Keywords negativas (una por línea)', comercial_array_to_textarea($selectedProcess['negative_keywords']), 5);

            // --- Campos IA ---
            comercial_field_textarea('ia_context_prompt', 'Contexto IA (instrucciones de tono y objetivo)', (string)($selectedProcess['ia_context_prompt'] ?? ''), 8);
            echo '<div class="field-help" style="margin-top:-6px; margin-bottom:12px;">Texto que se inyecta en el prompt de la IA para definir el tono, objetivo comercial y estilo de conversación.</div>';
            comercial_field_textarea('signal_detection_rules', 'Reglas de detección de señales (formato: frase|señal|confianza)', comercial_array_to_textarea($selectedProcess['signal_detection_rules'] ?? array()), 10);
            echo '<div class="field-help" style="margin-top:-6px; margin-bottom:12px;">Una regla por línea. Ej: "quiero comprar|wa.intent_buy_explicit|0.90"</div>';
            echo '<div class="form-grid-2">';
            comercial_field_number('conversation_max_auto_turns', 'Máx. respuestas automáticas', $selectedProcess['conversation_max_auto_turns'] ?? 2);
            comercial_field_number('escalation_score_threshold', 'Umbral de notificación al operador (score)', $selectedProcess['escalation_score_threshold'] ?? 78);
            echo '</div>';
            echo '<div class="field-help" style="margin-top:-6px; margin-bottom:12px;">Número máximo de respuestas automáticas consecutivas antes de pedir intervención humana. | Si el score de interés supera este valor, se notifica al operador humano.</div>';
            echo '<div class="commercial-inline-checks">';
            echo '<label><input type="checkbox" name="ia_learning_enabled" value="1"' . (!empty($selectedProcess['ia_learning_enabled']) ? ' checked' : '') . '> Aprendizaje IA activo</label>';
            echo '<label><input type="checkbox" name="auto_notify_operator" value="1"' . (!empty($selectedProcess['auto_notify_operator']) ? ' checked' : '') . '> Notificar automáticamente al detectar lead caliente</label>';
            echo '</div>';
            echo '<div class="field-help" style="margin-top:-6px; margin-bottom:12px;">El bot aprende de las respuestas humanas exitosas y las usa como referencia. | Al detectar un lead con score alto, se notifica automáticamente al operador.</div>';

            echo '<div class="field"><label>Líneas asignadas</label><div class="commercial-checkboxes">';
            foreach ($lines as $line) {
                if (trim((string)($line['waha_port'] ?? '')) === '') continue;
                $checked = in_array((string)$line['id'], (array)$selectedProcess['assigned_line_ids'], true) ? ' checked' : '';
                echo '<label><input type="checkbox" name="assigned_line_ids[]" value="' . e((string)$line['id']) . '"' . $checked . '> ' . e((string)$line['nombre']) . ' · ' . e((string)$line['tfono']) . ' · puerto ' . e((string)$line['waha_port']) . '</label>';
            }
            echo '</div></div>';

            echo '<div class="commercial-inline-checks">';
            echo '<label><input type="checkbox" name="enabled" value="1"' . (!empty($selectedProcess['enabled']) ? ' checked' : '') . '> Activo</label>';
            echo '<label><input type="checkbox" name="auto_followup" value="1"' . (!empty($selectedProcess['auto_followup']) ? ' checked' : '') . '> Auto seguimiento</label>';
            echo '<label><input type="checkbox" name="auto_create_lead" value="1"' . (!empty($selectedProcess['auto_create_lead']) ? ' checked' : '') . '> Auto crear lead</label>';
            echo '</div>';

            echo '<div class="toolbar"><button type="submit" class="btn-primary">Guardar proceso</button></div>';
            echo '</form>';
            echo '<form method="post" style="margin-top:8px;">';
            echo '<input type="hidden" name="action" value="comercial_run_tick">';
            echo '<input type="hidden" name="process_id" value="' . e($selectedProcess['id']) . '">';
            echo '<button type="submit" class="btn-secondary-mini">Lanzar solo este proceso</button>';
            echo '</form>';
        }
        echo '</section>';
        echo '</div>';

        echo <<<'HTML'
    <script>
    (function () {
        function getInputs() {
            return Array.from(document.querySelectorAll('.commercial-dist-input'));
        }

        function getWindowHours(processId) {
            const el = document.querySelector('[data-window-for="' + processId + '"]');
            if (!el) return 24;
            const start = parseInt(el.getAttribute('data-start') || '0', 10);
            const end = parseInt(el.getAttribute('data-end') || '0', 10);
            if (start === end) return 24;
            if (end > start) return end - start;
            return (24 - start) + end;
        }

        function recalc() {
            const inputs = getInputs();
            const totalMsgsEl = document.querySelector('input[name="global_daily_target"]');
            const totalMsgs = totalMsgsEl ? (parseFloat(totalMsgsEl.value) || 0) : 0;
            let sum = 0;
            inputs.forEach(function (input) {
                sum += parseFloat(input.value || '0') || 0;
            });

            const totalEl = document.getElementById('comercial_distribution_total');
            if (totalEl) {
                totalEl.value = sum.toFixed(1) + '%';
            }

            inputs.forEach(function (input) {
                const id = input.getAttribute('data-process-id');
                const percent = parseFloat(input.value || '0') || 0;
                const target = totalMsgs > 0 ? Math.round(totalMsgs * (percent / 100)) : 0;
                const hours = getWindowHours(id);
                let intervalText = '—';
                if (target > 0 && hours > 0) {
                    const avg = Math.max(60, Math.round((hours * 3600) / target));
                    const min = Math.max(60, Math.floor(avg * 0.85));
                    const max = Math.max(min, Math.ceil(avg * 0.15 + avg));
                    intervalText = Math.round(min / 60) + '–' + Math.round(max / 60) + ' min';
                }

                const targetEl = document.querySelector('[data-target-for="' + id + '"]');
                const intervalEl = document.querySelector('[data-interval-for="' + id + '"]');
                if (targetEl) targetEl.textContent = target;
                if (intervalEl) intervalEl.textContent = intervalText;
            });
        }

        window.comercialNormalizeDistribution = function () {
            const inputs = getInputs();
            let sum = 0;
            inputs.forEach(function (input) {
                sum += Math.max(0, parseFloat(input.value || '0') || 0);
            });
            if (sum <= 0) {
                recalc();
                return;
            }

            let acc = 0;
            inputs.forEach(function (input, index) {
                const raw = Math.max(0, parseFloat(input.value || '0') || 0);
                let nextValue;
                if (index === inputs.length - 1) {
                    nextValue = Math.max(0, 100 - acc);
                } else {
                    nextValue = Math.round(((raw / sum) * 100) * 10) / 10;
                    acc += nextValue;
                }
                input.value = nextValue.toFixed(1);
            });
            recalc();
        };

        document.addEventListener('input', function (ev) {
            if (ev.target.matches('.commercial-dist-input') || ev.target.matches('input[name="global_daily_target"]')) {
                recalc();
            }
        });

        recalc();
    })();
    </script>
HTML;
        return;
    }

    if ($tab === 'blacklist') {
        $selectedBlacklistId = trim((string)request_get('edit_blacklist', ''));
        $selectedBlacklist = $selectedBlacklistId !== '' ? comercial_get_blacklist_entry($selectedBlacklistId) : null;
        if (!$selectedBlacklist) {
            $selectedBlacklist = comercial_blacklist_entry_defaults();
        }
        $blacklistEntries = comercial_get_blacklist_entries();

        echo '<div class="split-grid">';
        echo '<section class="panel">';
        echo '<h2>Blacklist global</h2>';
        echo '<p class="muted" style="margin-top:0;">Los teléfonos guardados aquí no recibirán publicidad automática desde ningún proceso comercial. El filtro se aplica en el motor real antes de elegir destinatario.</p>';
        echo '<div class="table-wrap"><table><thead><tr><th>Teléfono</th><th>Notas</th><th>Actualizado</th><th>Acciones</th></tr></thead><tbody>';
        if (empty($blacklistEntries)) {
            echo '<tr><td colspan="4" class="muted">Aún no hay teléfonos en blacklist.</td></tr>';
        } else {
            foreach ($blacklistEntries as $row) {
                echo '<tr>';
                echo '<td>';
                crm_render_phone_value((string)($row['phone'] ?? ''));
                echo '</td>';
                echo '<td>' . e((string)($row['notes'] ?? '')) . '</td>';
                echo '<td>' . e((string)($row['updated_at'] ?? '')) . '</td>';
                echo '<td>';
                echo '<a class="btn-secondary-mini" href="' . e(comercial_page_url('blacklist', array('edit_blacklist' => (string)$row['id']))) . '">Editar</a> ';
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar este teléfono de la blacklist?\')">';
                echo '<input type="hidden" name="action" value="delete_comercial_blacklist">';
                echo '<input type="hidden" name="id" value="' . e((string)$row['id']) . '">';
                echo '<button type="submit" class="btn-danger-soft">Eliminar</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
        echo '</section>';

        echo '<section class="panel">';
        echo '<h2>' . ($selectedBlacklistId !== '' ? 'Editar teléfono' : 'Añadir teléfono') . '</h2>';
        echo '<form method="post">';
        echo '<input type="hidden" name="action" value="save_comercial_blacklist">';
        echo '<input type="hidden" name="id" value="' . e((string)($selectedBlacklist['id'] ?? '')) . '">';
        comercial_field_text('phone', 'Teléfono', (string)($selectedBlacklist['phone'] ?? ''));
        comercial_field_textarea('notes', 'Notas', (string)($selectedBlacklist['notes'] ?? ''), 4);
        echo '<div class="toolbar"><button type="submit" class="btn-primary">Guardar teléfono</button>';
        if ($selectedBlacklistId !== '') {
            echo ' <a class="btn-secondary-mini" href="' . e(comercial_page_url('blacklist')) . '">Nuevo</a>';
        }
        echo '</div>';
        echo '</form>';
        echo '<div class="info-strip" style="margin-top:12px;">Puedes poner números con o sin prefijo 34, espacios o guiones. El sistema los normaliza y los compara también por los últimos 9 dígitos para evitar que se cuele el mismo número en otro formato.</div>';
        echo '</section>';
        echo '</div>';
        return;
    }

    if ($tab === 'lineas') {
        $lineEditId = trim((string)request_get('edit', ''));
        $lineEdit = $lineEditId !== '' ? storage_find_by_id('telefonos.json', $lineEditId) : null;

        $anunciosIndex = array();
        foreach ($anuncios as $an) {
            $anunciosIndex[$an['id']] = $an;
        }

        // ── Toolbar ──
        echo '<section class="panel">';
        echo '<div class="lineas-toolbar">';
        echo '<button type="button" class="btn-primary" id="btnNuevaLinea">+ Nueva línea</button>';
        echo '<form method="post" style="display:inline-block;">';
        echo '<input type="hidden" name="action" value="comercial_check_lines_health">';
        echo '<button type="submit" class="btn-primary">Comprobar WAHA ahora</button>';
        echo '</form>';
        echo '<input type="text" id="lineas-unified-search" placeholder="Buscar línea..." class="field" style="width:100%;max-width:320px;" autocomplete="off">';
        echo '<div class="muted-small">El sistema refresca la salud de cada línea automáticamente dentro del tick comercial si han pasado aproximadamente 60 minutos desde la última comprobación.</div>';
        echo '</div>';

        // ── Tabla unificada ──
        echo '<div class="table-wrap" style="max-height:calc(100vh - 280px);overflow-y:auto;">';
        echo '<table class="lineas-unified-table">';
        echo '<thead><tr>';
        echo '<th class="col-nombre">Nombre / Teléfono</th>';
        echo '<th class="col-uso">Uso / Puerto</th>';
        echo '<th class="col-waha">WAHA</th>';
        echo '<th class="col-check">Comprobación</th>';
        echo '<th class="col-comercial">Estado Comercial</th>';
        echo '<th class="col-procesos">Procesos</th>';
        echo '<th class="col-ultimos">Último éxito / error</th>';
        echo '<th class="col-acciones">Acciones</th>';
        echo '</tr></thead>';
        echo '<tbody id="lineasUnifiedTableBody">';
        foreach ($lines as $line) {
            $state = (array)($line['comercial_state'] ?? array());
            $status = trim((string)($state['status'] ?? 'active'));
            $cls = $status === 'paused' ? 'danger' : ($status === 'warning' ? 'warn' : 'ok');
            $healthStatus = trim((string)($state['health_status'] ?? 'unknown'));
            $healthHttpCode = (int)($state['health_http_code'] ?? 0);
            $healthSessionStatus = trim((string)($state['health_session_status'] ?? ''));
            $healthError = trim((string)($state['health_error'] ?? ''));
            $healthCheckedAt = trim((string)($state['last_health_check_at'] ?? ''));
            $healthOkAt = trim((string)($state['last_health_ok_at'] ?? ''));
            $healthFailAt = trim((string)($state['last_health_failure_at'] ?? ''));
            $usage = implode(', ', (array)($line['comercial_usage'] ?? array()));

            $lineEditData = json_encode(array(
                'id'            => $line['id'] ?? '',
                'nombre'        => $line['nombre'] ?? '',
                'tfono'         => $line['tfono'] ?? '',
                'uso'           => $line['uso'] ?? '',
                'pin'           => $line['pin'] ?? '',
                'compania'      => $line['compania'] ?? '',
                'waha_port'     => $line['waha_port'] ?? '',
                'waha'          => $line['waha'] ?? '',
                'destacamos_id' => $line['destacamos_id'] ?? '',
                'notas'         => $line['notas'] ?? '',
            ), JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);

            echo '<tr data-line="' . e($lineEditData) . '">';

            // Col 1: Nombre + Teléfono
            echo '<td class="col-nombre"><strong>' . e((string)$line['nombre']) . '</strong><br>';
            crm_render_phone_value((string)($line['tfono'] ?? ''));
            echo '</td>';

            // Col 2: Uso / Puerto WAHA
            echo '<td class="col-uso"><span>' . e((string)($line['uso'] ?? '—')) . '</span><br><span class="muted-small">WAHA :' . e((string)($line['waha_port'] ?? '')) . '</span></td>';

            // Col 3: Estado WAHA
            echo '<td class="col-waha"><span class="status-pill ' . e(comercial_line_health_css_class($healthStatus)) . '">' . e(comercial_line_health_label($healthStatus)) . '</span>';
            if ($healthHttpCode > 0 || $healthSessionStatus !== '') {
                echo '<br><span class="muted-small">';
                if ($healthHttpCode > 0) echo 'HTTP ' . e((string)$healthHttpCode);
                if ($healthSessionStatus !== '') echo ($healthHttpCode > 0 ? ' · ' : '') . 'sesión ' . e($healthSessionStatus);
                echo '</span>';
            }
            echo '</td>';

            // Col 4: Última comprobación
            echo '<td class="col-check">' . e($healthCheckedAt !== '' ? $healthCheckedAt : '—');
            if ($healthOkAt !== '') {
                echo '<br><span class="muted-small">último OK: ' . e($healthOkAt) . '</span>';
            } elseif ($healthFailAt !== '') {
                echo '<br><span class="muted-small">último fallo: ' . e($healthFailAt) . '</span>';
            }
            echo '</td>';

            // Col 5: Estado Comercial
            echo '<td class="col-comercial"><span class="status-pill ' . e($cls) . '">' . e($status) . '</span><br><span class="muted-small">fails: ' . e((string)($state['consecutive_failures'] ?? 0)) . '</span><br><span class="muted-small">potencia: x' . e(number_format((float)($state['effective_power_factor'] ?? 1), 2, '.', '')) . ' · base x' . e(number_format((float)($state['adaptive_power_factor'] ?? 1), 2, '.', '')) . '</span></td>';

            // Col 6: Procesos asociados
            echo '<td class="col-procesos">' . e($usage !== '' ? $usage : '—') . '</td>';

            // Col 7: Último éxito / error
            echo '<td class="col-ultimos">' . e((string)($state['last_success_at'] ?? ''));
            if (!empty($state['last_error'])) {
                echo '<br><span class="muted-small">error: ' . e((string)$state['last_error']) . '</span>';
            }
            if ($healthError !== '') {
                echo '<br><span class="muted-small">WAHA: ' . e($healthError) . '</span>';
            }
            echo '</td>';

            // Col 8: Acciones
            echo '<td class="col-acciones">';
            echo '<button type="button" class="btn-secondary-mini btn-lineas-edit" style="margin-right:4px;margin-bottom:4px;">Editar</button><br>';
            echo '<form method="post" style="display:inline-block; margin-right:4px; margin-bottom:4px;">';
            echo '<input type="hidden" name="action" value="comercial_check_lines_health">';
            echo '<input type="hidden" name="line_id" value="' . e((string)$line['id']) . '">';
            echo '<button type="submit" class="btn-secondary-mini">Test WAHA</button>';
            echo '</form>';
            echo '<br>';
            echo '<form method="post" style="display:inline-block; margin-right:4px;">';
            echo '<input type="hidden" name="action" value="save_comercial_line_state">';
            echo '<input type="hidden" name="line_id" value="' . e((string)$line['id']) . '">';
            echo '<input type="hidden" name="status" value="active">';
            echo '<button type="submit" class="btn-secondary-mini">Activar</button>';
            echo '</form>';
            echo '<form method="post" style="display:inline-block;">';
            echo '<input type="hidden" name="action" value="save_comercial_line_state">';
            echo '<input type="hidden" name="line_id" value="' . e((string)$line['id']) . '">';
            echo '<input type="hidden" name="status" value="paused">';
            echo '<button type="submit" class="btn-secondary-mini">Pausar</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        if (empty($lines)) {
            echo '<p class="muted-small" style="margin-top:12px;">No hay líneas todavía.</p>';
        }
        echo '</section>';

        // ── Modal para nueva/editar línea ──
        echo '<div id="lineasModalOverlay" class="modal-overlay" style="display:none;">';
        echo '<div class="modal-container">';
        echo '<div class="modal-header">';
        echo '<h2 id="lineaModalTitle">Nueva línea</h2>';
        echo '<button type="button" class="modal-close" id="btnModalClose">&times;</button>';
        echo '</div>';
        echo '<div class="modal-body">';
        echo '<form method="post" class="form-grid" id="lineaForm">';
        echo '<input type="hidden" name="action" value="save_telefono">';
        echo '<input type="hidden" name="id" value="">';
        field_input('nombre', 'Nombre', '', true);
        field_input('tfono', 'Tfono', '', true);
        field_input('uso', 'Uso', '');
        field_input('pin', 'PIN', '');
        field_input('compania', 'Compañía', '');
        field_input('waha_port', 'WAHA Port', '');
        field_input('waha', 'WAHA', '');
        echo '<div class="field">';
        echo '<label>Destacamos</label>';
        echo '<select name="destacamos_id">';
        echo '<option value="">Sin vincular</option>';
        foreach ($anuncios as $an) {
            $val = $an['id'] ?? '';
            $label = trim(($an['url'] ?? '') . ' - ' . ($an['user'] ?? ''));
            echo '<option value="' . e($val) . '">' . e($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        field_textarea('notas', 'Notas', '', 4);
        echo '</form>';
        echo '</div>';
        echo '<div class="modal-footer">';
        echo '<button type="button" class="btn-primary" id="btnGuardarLinea">Guardar línea</button>';
        echo '<form method="post" id="deleteLineaForm" style="display:inline-block;" onsubmit="return confirm(\'¿Eliminar esta línea?\')">';
        echo '<input type="hidden" name="action" value="delete_telefono">';
        echo '<input type="hidden" name="id" value="">';
        echo '<button type="submit" class="btn-danger-mini" id="btnEliminarLinea" style="display:none;">Eliminar</button>';
        echo '</form>';
        echo '<button type="button" class="btn-secondary" id="btnCancelarLinea">Cancelar</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        return;
    }

    if ($tab === 'conversaciones') {
        $stageFilter = trim((string)request_get('stage_filter', 'all'));
        $quickFilter = trim((string)request_get('quick_filter', 'all'));
        $lineFilter = trim((string)request_get('line_filter', 'all'));
        $processFilter = trim((string)request_get('process_filter', 'all'));
        $viewThreadId = trim((string)request_get('view_thread', ''));
        $viewThread = null;
        $snippetOneLine = function ($text, $max = 120) {
            $clean = preg_replace('/\s+/u', ' ', trim((string)$text));
            if ($clean === '') {
                return '—';
            }
            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                if (mb_strlen($clean, 'UTF-8') > $max) {
                    return mb_substr($clean, 0, $max - 1, 'UTF-8') . '…';
                }
                return $clean;
            }
            if (strlen($clean) > $max) {
                return substr($clean, 0, $max - 1) . '…';
            }
            return $clean;
        };
        $triageLevel = function ($thread) {
            $stage = trim((string)($thread['stage'] ?? ''));
            $humanTaken = !empty($thread['human_taken']);
            $hasReply = (int)($thread['replies_count'] ?? 0) > 0;
            $hasLead = trim((string)($thread['lead_id'] ?? '')) !== '';
            if ($stage === 'very_hot' || (!$humanTaken && $hasReply && $stage !== 'discarded')) {
                return 'P1';
            }
            if ($stage === 'discarded' || $hasLead) {
                return 'P3';
            }
            return 'P2';
        };
        $isRecentReply = function ($thread) {
            if ((int)($thread['replies_count'] ?? 0) <= 0) {
                return false;
            }
            $ts = trim((string)($thread['updated_at'] ?? ''));
            if ($ts === '') {
                return false;
            }
            $when = strtotime($ts);
            if ($when === false) {
                return false;
            }
            return $when >= (time() - 36 * 3600);
        };
        $threadPassesExtraFilters = function ($thread) use ($quickFilter, $lineFilter, $processFilter, $isRecentReply) {
            $threadLineId = trim((string)($thread['line_id'] ?? ''));
            $threadProcess = trim((string)($thread['process_slug'] ?? ''));
            if ($lineFilter !== '' && $lineFilter !== 'all' && $threadLineId !== $lineFilter) {
                return false;
            }
            if ($processFilter !== '' && $processFilter !== 'all' && $threadProcess !== $processFilter) {
                return false;
            }
            if ($quickFilter === 'unhandled' && !empty($thread['human_taken'])) {
                return false;
            }
            if ($quickFilter === 'without_lead' && trim((string)($thread['lead_id'] ?? '')) !== '') {
                return false;
            }
            if ($quickFilter === 'recent_replies' && !$isRecentReply($thread)) {
                return false;
            }
            return true;
        };
        foreach ($threads as $tmpThread) {
            if ((string)($tmpThread['id'] ?? '') === $viewThreadId) {
                $viewThread = $tmpThread;
                break;
            }
        }
        $availableFilters = array(
            'all' => 'Todas',
            'opened' => 'Abiertas directas',
            'responded' => 'Respondidas',
            'qualified' => 'Qualifieds',
            'very_hot' => 'Muy calientes',
            'discarded' => 'Descartadas',
        );
        echo '<section class="panel">';
        echo '<h2>Conversaciones</h2>';
        echo '<div class="commercial-filter-bar">';
        foreach ($availableFilters as $filterKey => $filterLabel) {
            $count = 0;
            foreach ($threads as $countThread) {
                if (comercial_thread_matches_filter($countThread, $filterKey)) {
                    $count++;
                }
            }
            $activeClass = $stageFilter === $filterKey ? ' active' : '';
            echo '<span class="commercial-filter-chip' . $activeClass . '">';
            echo '<a href="' . e(comercial_page_url('conversaciones', array('stage_filter' => $filterKey))) . '">' . e($filterLabel) . ' · ' . e((string)$count) . '</a>';
            echo ' <form method="post" style="display:inline-block; margin-left:6px;">';
            echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
            echo '<input type="hidden" name="action" value="comercial_export_threads_csv">';
            echo '<input type="hidden" name="stage_filter" value="' . e($filterKey) . '">';
            echo '<input type="hidden" name="redirect" value="' . e(comercial_page_url('conversaciones', array('stage_filter' => $filterKey))) . '">';
            echo '<button type="submit" class="btn-secondary-mini">Exportar a Excel</button>';
            echo '</form>';
            echo '</span>';
        }
        echo '</div>';
        $lineFilterOptions = array('all' => 'Todas las líneas');
        foreach ($lines as $line) {
            $lineId = (string)($line['id'] ?? '');
            if ($lineId === '') {
                continue;
            }
            $lineFilterOptions[$lineId] = trim((string)($line['nombre'] ?? '')) !== '' ? (string)$line['nombre'] : ('Línea ' . $lineId);
        }
        $processFilterOptions = array('all' => 'Todos los procesos');
        foreach ($processes as $process) {
            $slug = trim((string)($process['slug'] ?? ''));
            if ($slug !== '') {
                $processFilterOptions[$slug] = trim((string)($process['nombre'] ?? $slug));
            }
        }
        echo '<form method="get" class="commercial-quick-filters">';
        echo '<input type="hidden" name="page" value="comercial">';
        echo '<input type="hidden" name="tab" value="conversaciones">';
        echo '<input type="hidden" name="stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
        echo '<div class="commercial-filter-bar">';
        $quickFilters = array(
            'all' => 'Todo',
            'unhandled' => 'Sin gestionar',
            'without_lead' => 'Sin lead',
            'recent_replies' => 'Respuestas recientes',
        );
        foreach ($quickFilters as $qKey => $qLabel) {
            $qClass = $quickFilter === $qKey ? ' active' : '';
            echo '<button type="submit" name="quick_filter" value="' . e($qKey) . '" class="commercial-filter-chip commercial-filter-btn' . $qClass . '">' . e($qLabel) . '</button>';
        }
        echo '</div>';
        echo '<div class="form-grid-2">';
        echo '<div class="field"><label>Línea</label><select name="line_filter">';
        foreach ($lineFilterOptions as $value => $text) {
            echo '<option value="' . e((string)$value) . '"' . ((string)$lineFilter === (string)$value ? ' selected' : '') . '>' . e((string)$text) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="field"><label>Proceso</label><select name="process_filter">';
        foreach ($processFilterOptions as $value => $text) {
            echo '<option value="' . e((string)$value) . '"' . ((string)$processFilter === (string)$value ? ' selected' : '') . '>' . e((string)$text) . '</option>';
        }
        echo '</select></div>';
        echo '</div>';
        echo '<div class="commercial-inline-checks"><button type="submit" class="btn-secondary-mini">Aplicar filtros</button></div>';
        echo '</form>';
        $filteredThreads = array();
        foreach ($threads as $thread) {
            if (!comercial_thread_matches_filter($thread, $stageFilter)) {
                continue;
            }
            if (!$threadPassesExtraFilters($thread)) {
                continue;
            }
            $filteredThreads[] = $thread;
        }
        if ($viewThread) {
            $snapshot = comercial_thread_live_payload((string)($viewThread['id'] ?? ''));
            $viewStage = trim((string)($viewThread['stage'] ?? ''));
            $viewLineName = isset($linesIndexed[(string)($viewThread['line_id'] ?? '')]) ? trim((string)($linesIndexed[(string)$viewThread['line_id']]['nombre'] ?? '')) : '';
            echo '<div class="commercial-thread-view">';
            echo '<div class="commercial-thread-view-head">';
            echo '<div><strong>Conversación completa · </strong>';
            crm_render_phone_value((string)($viewThread['target_phone'] ?? ''), array('strong' => true));
            echo '</div>';
            echo '<a class="mini-link" href="' . e(comercial_page_url('conversaciones', array('stage_filter' => $stageFilter !== '' ? $stageFilter : 'all'))) . '">Cerrar</a>';
            echo '</div>';
            echo '<div class="commercial-thread-subhead" id="commercialThreadHeader"'
                . ' data-updated-at="' . e((string)($snapshot['updated_at'] ?? '')) . '"'
                . ' data-thread-id="' . e((string)($viewThread['id'] ?? '')) . '"'
                . ' data-feed-url="' . e((comercial_base_url() !== '' ? comercial_base_url() : '') . '/comercial_thread_feed.php') . '">';
            echo '<div><span class="muted-small">Proceso: <strong id="commercialThreadProcess">' . e((string)($viewThread['process_slug'] ?? '')) . '</strong> · Estado: <span id="commercialThreadStageLabel" class="status-pill ' . e(comercial_thread_stage_css_class($viewStage)) . '">' . e(comercial_thread_stage_label($viewStage)) . '</span></span></div>';
            echo '<div><span class="muted-small">WhatsApp origen desde el que se habla:</span> <span id="commercialThreadLine">';
            crm_render_phone_value((string)($viewThread['line_phone'] ?? ''));
            echo ($viewLineName !== '' ? ' · ' . e($viewLineName) : '');
            echo '</span></div>';
            echo '</div>';
            echo '<div class="commercial-thread-top-actions">';
            if ($viewStage !== 'qualified' && $viewStage !== 'very_hot') {
                echo '<form method="post">';
                echo '<input type="hidden" name="action" value="comercial_set_thread_stage">';
                echo '<input type="hidden" name="thread_id" value="' . e((string)$viewThread['id']) . '">';
                echo '<input type="hidden" name="stage" value="qualified">';
                echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
                echo '<input type="hidden" name="return_view_thread" value="' . e((string)$viewThread['id']) . '">';
                echo '<button type="submit" class="btn-secondary-mini">Marcar cualificado</button>';
                echo '</form>';
            }
            if ($viewStage !== 'very_hot' && $viewStage !== 'discarded') {
                echo '<form method="post">';
                echo '<input type="hidden" name="action" value="comercial_set_thread_stage">';
                echo '<input type="hidden" name="thread_id" value="' . e((string)$viewThread['id']) . '">';
                echo '<input type="hidden" name="stage" value="very_hot">';
                echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
                echo '<input type="hidden" name="return_view_thread" value="' . e((string)$viewThread['id']) . '">';
                echo '<button type="submit" class="btn-secondary-mini">Marcar muy caliente</button>';
                echo '</form>';
            }
            if ($viewStage !== 'discarded') {
                echo '<form method="post">';
                echo '<input type="hidden" name="action" value="comercial_set_thread_stage">';
                echo '<input type="hidden" name="thread_id" value="' . e((string)$viewThread['id']) . '">';
                echo '<input type="hidden" name="stage" value="discarded">';
                echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
                echo '<input type="hidden" name="return_view_thread" value="' . e((string)$viewThread['id']) . '">';
                echo '<button type="submit" class="btn-secondary-mini btn-danger-soft">Descartar conversación</button>';
                echo '</form>';
            }
            echo '</div>';
            echo '<div class="commercial-thread-chat-shell">';
            echo '<div class="commercial-thread-chat-bar">WhatsApp en vivo · se actualiza solo</div>';
            echo '<div id="commercialThreadTimelineWrap">';
            echo (string)($snapshot['timeline_html'] ?? '');
            echo '</div>';
            echo '<form method="post" class="commercial-thread-view-reply-form">';
            echo '<input type="hidden" name="action" value="comercial_send_thread_message">';
            echo '<input type="hidden" name="thread_id" value="' . e((string)$viewThread['id']) . '">';
            echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
            echo '<input type="hidden" name="return_view_thread" value="' . e((string)$viewThread['id']) . '">';
            echo '<textarea name="manual_text" rows="3" placeholder="Escribe aquí para responder desde este mismo WhatsApp origen..."></textarea>';
            echo '<button type="submit" class="btn-primary">Responder desde este móvil origen</button>';
            echo '</form>';
            echo '</div>';
            echo '<div class="commercial-thread-webhook-block">';
            echo '<strong>Log webhook / inbound</strong>';
            echo '<div id="commercialThreadWebhookWrap">';
            echo (string)($snapshot['webhook_log_html'] ?? '');
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        $triageGroups = array('P1' => array(), 'P2' => array(), 'P3' => array());
        foreach ($filteredThreads as $thread) {
            $triage = $triageLevel($thread);
            if (!isset($triageGroups[$triage])) {
                $triageGroups[$triage] = array();
            }
            $triageGroups[$triage][] = $thread;
        }
        echo '<div class="table-wrap"><table><thead><tr><th>Proceso</th><th>Cliente</th><th>Estado</th><th>Vista rápida</th><th></th></tr></thead><tbody>';
        foreach (array('P1', 'P2', 'P3') as $triage) {
            $groupRows = isset($triageGroups[$triage]) ? (array)$triageGroups[$triage] : array();
            if (empty($groupRows)) {
                continue;
            }
            echo '<tr class="commercial-triage-group-row"><td colspan="5"><span class="commercial-triage-badge ' . e(strtolower($triage)) . '">' . e($triage) . '</span> <strong>Prioridad ' . e($triage) . '</strong> <span class="muted-small">· ' . e((string)count($groupRows)) . ' conversaciones</span></td></tr>';
            foreach ($groupRows as $thread) {
            $stage = trim((string)($thread['stage'] ?? ''));
            $lineName = isset($linesIndexed[(string)($thread['line_id'] ?? '')]) ? trim((string)($linesIndexed[(string)$thread['line_id']]['nombre'] ?? '')) : '';
            echo '<tr class="commercial-thread-row stage-' . e($stage) . '">';
            echo '<td>' . e((string)$thread['process_slug']) . '<br><span class="muted-small">línea </span>';
            crm_render_phone_value((string)($thread['line_phone'] ?? ''));
            if ($lineName !== '') {
                echo '<span class="muted-small"> · ' . e($lineName) . '</span>';
            }
            echo '</td>';
            echo '<td>';
            crm_render_phone_value((string)($thread['target_phone'] ?? ''), array('strong' => true));
            echo '<br><span class="muted-small">replies: ' . e((string)$thread['replies_count']) . ' · envíos: ' . e((string)$thread['messages_sent_count']) . ' · enviado: ' . e((string)($thread['created_at'] ?? '')) . '</span>';
            echo '</td>';
            echo '<td><span class="status-pill ' . e(comercial_thread_stage_css_class($stage)) . '">' . e(comercial_thread_stage_label($stage)) . '</span></td>';
            echo '<td><div class="commercial-row-preview">';
            echo '<div class="commercial-row-snippet in"><strong>IN</strong> ' . e($snippetOneLine((string)($thread['last_inbound_text'] ?? ''))) . '</div>';
            echo '<div class="commercial-row-snippet out"><strong>OUT</strong> ' . e($snippetOneLine((string)($thread['last_outbound_text'] ?? ''))) . '</div>';
            echo '</div></td>';
            echo '<td class="commercial-thread-actions-cell">';
            if ($stage !== 'qualified' && $stage !== 'very_hot') {
                echo '<form method="post" style="display:inline-block; margin-bottom:6px;">';
                echo '<input type="hidden" name="action" value="comercial_set_thread_stage">';
                echo '<input type="hidden" name="thread_id" value="' . e((string)$thread['id']) . '">';
                echo '<input type="hidden" name="stage" value="qualified">';
                echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
                echo '<button type="submit" class="btn-secondary-mini">Marcar cualificado</button>';
                echo '</form><br>';
            }
            if ($stage !== 'very_hot' && $stage !== 'discarded') {
                echo '<form method="post" style="display:inline-block; margin-bottom:6px;">';
                echo '<input type="hidden" name="action" value="comercial_set_thread_stage">';
                echo '<input type="hidden" name="thread_id" value="' . e((string)$thread['id']) . '">';
                echo '<input type="hidden" name="stage" value="very_hot">';
                echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
                echo '<button type="submit" class="btn-secondary-mini">Marcar muy caliente</button>';
                echo '</form><br>';
            }
            if ($stage !== 'discarded') {
                echo '<form method="post" style="display:inline-block; margin-bottom:6px;">';
                echo '<input type="hidden" name="action" value="comercial_set_thread_stage">';
                echo '<input type="hidden" name="thread_id" value="' . e((string)$thread['id']) . '">';
                echo '<input type="hidden" name="stage" value="discarded">';
                echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
                echo '<button type="submit" class="btn-secondary-mini btn-danger-soft">Descartar</button>';
                echo '</form><br>';
            }
            echo '<form method="post" style="display:inline-block;">';
            echo '<input type="hidden" name="action" value="comercial_promote_thread">';
            echo '<input type="hidden" name="thread_id" value="' . e((string)$thread['id']) . '">';
            echo '<button type="submit" class="btn-primary btn-small">Crear lead</button>';
            echo '</form>';
            echo '<br><a class="mini-link commercial-open-thread-cta" href="' . e(comercial_page_url('conversaciones', array('stage_filter' => $stageFilter !== '' ? $stageFilter : 'all', 'quick_filter' => $quickFilter !== '' ? $quickFilter : 'all', 'line_filter' => $lineFilter !== '' ? $lineFilter : 'all', 'process_filter' => $processFilter !== '' ? $processFilter : 'all', 'view_thread' => (string)$thread['id']))) . '">Abrir hilo completo</a>';
            echo '</td>';
            echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
        if ($viewThread) {
            echo '<script>(function(){'
                . 'const root=document.getElementById("commercialThreadHeader");'
                . 'if(!root){return;}'
                . 'const feedUrl=root.getAttribute("data-feed-url");'
                . 'const threadId=root.getAttribute("data-thread-id");'
                . 'const timeline=document.getElementById("commercialThreadTimelineWrap");'
                . 'const webhook=document.getElementById("commercialThreadWebhookWrap");'
                . 'const stageLabel=document.getElementById("commercialThreadStageLabel");'
                . 'let currentUpdated=root.getAttribute("data-updated-at")||"";'
                . 'let busy=false;'
                . 'function tick(){'
                . 'if(busy||document.hidden){return;}'
                . 'busy=true;'
                . 'fetch(feedUrl+"?thread_id="+encodeURIComponent(threadId)+"&_="+Date.now(),{credentials:"same-origin"})'
                . '.then(function(r){return r.json();})'
                . '.then(function(data){'
                . 'if(!data||!data.ok||!data.thread){return;}'
                . 'const next=data.thread.updated_at||"";'
                . 'if(next!==""&&next!==currentUpdated){'
                . 'currentUpdated=next;'
                . 'root.setAttribute("data-updated-at",next);'
                . 'if(stageLabel){stageLabel.textContent=data.thread.stage_label||"";stageLabel.className="status-pill "+(data.thread.stage_css||"muted");}'
                . 'if(timeline){timeline.innerHTML=data.thread.timeline_html||""; const el=timeline.querySelector(".commercial-thread-timeline"); if(el){el.scrollTop=el.scrollHeight;}}'
                . 'if(webhook){webhook.innerHTML=data.thread.webhook_log_html||"";}'
                . '}'
                . '})'
                . '.catch(function(){})'
                . '.finally(function(){busy=false;});'
                . '}'
                . 'if(timeline){const first=timeline.querySelector(".commercial-thread-timeline"); if(first){first.scrollTop=first.scrollHeight;}}'
                . 'tick();'
                . 'window.setInterval(tick,4000);'
                . '})();</script>';
        }
        echo '</section>';
        return;
    }

    if ($tab === 'leads') {
        echo '<section class="panel">';
        echo '<h2>Leads comerciales</h2>';
        echo '<div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Proceso</th><th>Teléfono</th><th>Estado</th><th>Observaciones</th></tr></thead><tbody>';
        foreach ($leads as $lead) {
            echo '<tr>';
            echo '<td>'; comercial_render_copy_value((string)($lead['created_at'] ?? '')); echo '</td>';
            echo '<td>'; comercial_render_copy_value((string)($lead['process_slug'] ?? '')); echo '</td>';
            echo '<td>'; comercial_render_copy_value((string)($lead['telefono'] ?? ''), false, true); echo '</td>';
            echo '<td>'; comercial_render_copy_value((string)($lead['estado'] ?? 'nuevo')); echo '</td>';
            echo '<td>'; comercial_render_copy_value((string)($lead['observaciones'] ?? ''), true); echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '</section>';
        return;
    }

    if ($tab === 'ajustes') {
        echo '<section class="panel">';
        echo '<h2>Ajustes globales</h2>';
        echo '<div class="info-strip" style="margin-bottom:12px;">';
        echo '<strong>Webhook inbound WAHA:</strong> <code>' . e(comercial_webhook_url()) . '</code><br>';
        echo 'Configura WAHA para hacer <code>POST</code> JSON a esa URL usando la cabecera <code>X-Api-Key</code> con el valor del campo <code>WAHA API key</code>.';
        echo '</div>';
        echo '<form method="post">';
        echo '<input type="hidden" name="action" value="save_comercial_settings">';
        echo '<div class="form-grid-2">';
        comercial_field_select('waha_host', 'WAHA host', comercial_waha_host_options($settings['waha_host']), comercial_normalize_waha_host($settings['waha_host']));
        comercial_field_text('waha_api_key', 'WAHA API key', $settings['waha_api_key']);
        comercial_field_text('waha_session', 'WAHA session', $settings['waha_session']);
        comercial_field_number('curl_timeout_sec', 'Timeout curl', $settings['curl_timeout_sec'], '1');
        comercial_field_number('global_daily_target', 'Objetivo global / día', $settings['global_daily_target'], '1');
        comercial_field_number('ban_window_size', 'Ventana de fallos', $settings['ban_window_size'], '1');
        comercial_field_number('ban_fail_streak_warning', 'Fails seguidos warning', $settings['ban_fail_streak_warning'], '1');
        comercial_field_number('ban_fail_streak_pause', 'Fails seguidos pausa', $settings['ban_fail_streak_pause'], '1');
        comercial_field_number('ban_fail_ratio_warning', 'Ratio fallo warning', $settings['ban_fail_ratio_warning'], '0.01');
        comercial_field_number('ban_fail_ratio_pause', 'Ratio fallo pausa', $settings['ban_fail_ratio_pause'], '0.01');
        comercial_field_number('cooldown_minutes_warning', 'Cooldown warning (min)', $settings['cooldown_minutes_warning'], '1');
        comercial_field_number('cooldown_minutes_pause', 'Cooldown pausa (min)', $settings['cooldown_minutes_pause'], '1');
        comercial_field_number('conversation_max_auto_turns', 'Máx turnos auto por hilo', $settings['conversation_max_auto_turns'], '1');
        comercial_field_number('conversation_max_defers', 'Máx defer por hilo', $settings['conversation_max_defers'], '1');
        echo '</div>';
        echo '<div class="commercial-inline-checks">';
        echo '<label><input type="checkbox" name="auto_followup_enabled" value="1"' . (!empty($settings['auto_followup_enabled']) ? ' checked' : '') . '> Activar seguimientos automáticos</label>';
        echo '<label><input type="checkbox" name="auto_pause_enabled" value="1"' . (!empty($settings['auto_pause_enabled']) ? ' checked' : '') . '> Activar autopausa por baneo</label>';
        echo '<label><input type="checkbox" name="ia_second_turn_enabled" value="1"' . (!empty($settings['ia_second_turn_enabled']) ? ' checked' : '') . '> IA segundo turno automático</label>';
        echo '<label><input type="checkbox" name="ia_learning_enabled" value="1"' . (!empty($settings['ia_learning_enabled']) ? ' checked' : '') . '> Aprendizaje operativo IA</label>';
        echo '</div>';
        echo '<div class="toolbar"><button type="submit" class="btn-primary">Guardar ajustes</button></div>';
        echo '</form>';
        echo '</section>';
        return;
    }

    if ($tab === 'logs') {
        $events = comercial_events_recent(200);
        echo '<section class="panel">';
        echo '<h2>Logs recientes</h2>';
        echo '<div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Tipo</th><th>Detalle</th></tr></thead><tbody>';
        foreach ($events as $event) {
            echo '<tr>';
            echo '<td>' . e((string)($event['ts'] ?? '')) . '</td>';
            echo '<td>' . e((string)($event['type'] ?? '')) . '</td>';
            echo '<td><pre class="commercial-pre">' . e(json_encode((array)($event['payload'] ?? array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . '</pre></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '</section>';
        return;
    }
}

function comercial_render_copy_value($text, $vertical = false, $phone = false) {
    crm_render_copy_value((string)$text, array(
        'vertical' => $vertical,
        'phone' => $phone,
    ));
}

function comercial_render_kpi($title, $value) {
    echo '<div class="commercial-kpi"><div class="commercial-kpi-title">' . e($title) . '</div><div class="commercial-kpi-value">' . e($value) . '</div></div>';
}

function comercial_field_text($name, $label, $value, $readonly = false) {
    echo '<div class="field"><label>' . e($label) . '</label><input type="text" name="' . e($name) . '" value="' . e((string)$value) . '"' . ($readonly ? ' readonly' : '') . '></div>';
}

function comercial_field_number($name, $label, $value, $step = '1') {
    echo '<div class="field"><label>' . e($label) . '</label><input type="number" step="' . e((string)$step) . '" name="' . e($name) . '" value="' . e((string)$value) . '"></div>';
}

function comercial_field_select($name, $label, $options, $selected) {
    echo '<div class="field"><label>' . e($label) . '</label><select name="' . e($name) . '">';
    foreach ((array)$options as $value => $text) {
        echo '<option value="' . e((string)$value) . '"' . ((string)$selected === (string)$value ? ' selected' : '') . '>' . e((string)$text) . '</option>';
    }
    echo '</select></div>';
}

function comercial_field_textarea($name, $label, $value, $rows = 5) {
    echo '<div class="field"><label>' . e($label) . '</label><textarea name="' . e($name) . '" rows="' . e((string)$rows) . '">' . e((string)$value) . '</textarea></div>';
}
