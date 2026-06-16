<?php
/**
 * _estados_formats.php — Format builders for WhatsApp status generation.
 *
 * Shared by api/estados.php (bot-casa dual panel) and app/publicista.php (CRM).
 * Contains all format functions: 16 original CRM formats + 10 new formats.
 *
 * All functions receive $girls = array of ['id','nombre','fotos']
 * Photo URLs are embedded directly in the status text (WAHA renders them from URL).
 */

declare(strict_types=1);

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Random heart/emoji from pool.
 */
function estados_hearts(): string {
    $pool = ['💋','💕','💖','💘','💗','💓','💞','❤️‍🔥','😘','🍑','✨','🔥','💫','🌹','🥂','💝','🌺','💐'];
    return $pool[array_rand($pool)];
}

/**
 * Pick random photos from a girl's array.
 */
function estados_pick_photos(array $fotos, int $count): array {
    $fotos = array_values($fotos);
    if (empty($fotos)) return [];
    if (count($fotos) <= $count) {
        shuffle($fotos);
        return $fotos;
    }
    $keys = (array)array_rand($fotos, $count);
    $picked = [];
    foreach ($keys as $k) $picked[] = $fotos[$k];
    return $picked;
}

// ─── Format Option Map ─────────────────────────────────────────────────────

/**
 * All format keys → label mapping. Add new formats here.
 */
function estados_format_options(): array {
    return [
        // ── Original 16 (from CRM) ──
        'chicas_de_hoy'   => 'Chicas de hoy (todas + 1 foto c/u)',
        'chica_del_dia'   => 'Chica del día (1 al azar + 2 fotos)',
        'duo_sexy'        => 'Dúo sexy (2 al azar, 1 foto c/u)',
        'catalogo_rapido' => 'Catálogo rápido (solo nombres)',
        'estrella_grupo'  => 'Estrella + grupo (1 destacada + resto)',
        'tentacion_del_dia' => 'Tentación del día (1 prohibida + 2 fotos)',
        'dulce_prohibido'    => 'Dulce prohibido (1 golosina + 2 fotos)',
        'trio_tentador'      => 'Trío tentador (3 al azar, 1 foto c/u)',
        'puertas_abiertas'   => 'Puertas abiertas (todas, tono bienvenida)',
        'ven_ya'             => 'Ven ya (1 urgente + 2 fotos)',
        'susurro'            => 'Al oído (1 íntima + 2 fotos)',
        'antojos'            => '¿De qué tienes hambre? (todas, menú)',
        'confesion'          => 'Confesión nocturna (1 + 2 fotos)',
        'el_equipo'          => 'El equipazo (todas, alineación)',
        'frescas'            => 'Recién llegaditas (todas, frescas)',
        'mix_aleatorio'      => '🎲 Mix aleatorio (alterna formatos)',

        // ── New 10 ──
        'frase_del_dia'      => 'Frase del día (1 chica + frase pícara)',
        'solo_valientes'     => 'Solo para valientes (1 chica + tono desafiante)',
        'cita_a_ciegas'      => 'Cita a ciegas (1 chica + misterio)',
        'regalo_sorpresa'    => 'Regalo sorpresa (1 chica + tono regalo)',
        'amiga_recomienda'   => 'Amiga recomienda (1 chica + curiosidad)',
        'modo_finde'         => 'Modo finde (todas + tono festivo)',
        'el_casting'         => 'El casting (todas + tú eres el juez)',
        'juego_parejas'      => 'Juego de parejas (2 chicas, química)',
        'la_nueva'           => 'Te está esperando (1 chica + tono directo)',
        'oferta_flash'       => 'Ahora o nunca (1+ chicas + urgencia real)',
    ];
}

// ─── Format Builders (Original 16) ─────────────────────────────────────────

function estados_format_chicas_de_hoy(array $girls): string {
    if (empty($girls)) return '';
    $h = estados_hearts();
    $lines = ["$h Chicas de hoy $h", ''];
    foreach ($girls as $g) {
        $lines[] = $g['nombre'];
        $foto = !empty($g['fotos']) ? $g['fotos'][array_rand($g['fotos'])] : '';
        if ($foto !== '') $lines[] = $foto;
    }
    $lines[] = '';
    $lines[] = 'Ven a vernos amor 😘💕';
    return implode("\n", $lines);
}

function estados_format_chica_del_dia(array $girls): string {
    if (empty($girls)) return '';
    $chica = $girls[array_rand($girls)];
    $h = estados_hearts();
    $fotos = estados_pick_photos($chica['fotos'], 2);
    $lines = ["🔥 Ven a disfrutar con {$chica['nombre']} $h"];
    foreach ($fotos as $f) $lines[] = $f;
    $lines[] = '';
    $lines[] = 'Lo pasarás rico 💖🍑';
    return implode("\n", $lines);
}

function estados_format_duo_sexy(array $girls): string {
    if (empty($girls)) return '';
    $pick = $girls;
    shuffle($pick);
    $duo = array_slice($pick, 0, min(2, count($pick)));
    $h = estados_hearts();
    $lines = ["$h Hoy te esperan... $h", ''];
    foreach ($duo as $g) {
        $lines[] = $g['nombre'];
        $foto = !empty($g['fotos']) ? $g['fotos'][array_rand($g['fotos'])] : '';
        if ($foto !== '') $lines[] = $foto;
    }
    $lines[] = '';
    $lines[] = 'No te lo pierdas 🍑✨';
    return implode("\n", $lines);
}

function estados_format_catalogo_rapido(array $girls): string {
    if (empty($girls)) return '';
    $nombres = [];
    foreach ($girls as $g) $nombres[] = $g['nombre'];
    $h = estados_hearts();
    $lines = [
        "📋 Nuestro catálogo hoy:",
        '',
        implode(' · ', $nombres),
        '',
        "Todas disponibles. Pide tu cita 💬{$h}",
    ];
    return implode("\n", $lines);
}

function estados_format_estrella_grupo(array $girls): string {
    if (empty($girls)) return '';
    $estrella = $girls[array_rand($girls)];
    $h = estados_hearts();
    $fotos = estados_pick_photos($estrella['fotos'], 2);
    $resto = [];
    foreach ($girls as $g) {
        if ($g['id'] !== $estrella['id']) $resto[] = $g['nombre'];
    }
    $lines = ["⭐ Estrella del día: {$estrella['nombre']} ⭐"];
    foreach ($fotos as $f) $lines[] = $f;
    if (!empty($resto)) {
        $lines[] = '';
        $lines[] = 'También disponible: ' . implode(', ', $resto);
    }
    $lines[] = '';
    $lines[] = "Ven a conocernos {$h}💖";
    return implode("\n", $lines);
}

function estados_format_tentacion_del_dia(array $girls): string {
    if (empty($girls)) return '';
    $g = $girls[array_rand($girls)];
    $fotos = estados_pick_photos($g['fotos'], 2);
    $h = estados_hearts();
    $lines = [
        "🍎 Hoy caerás en la tentación... {$g['nombre']} 🍎",
        $fotos[0] ?? '',
        $fotos[1] ?? '',
        '',
        "Mírala bien... y dime que no 😈🔥",
    ];
    return implode("\n", $lines);
}

function estados_format_dulce_prohibido(array $girls): string {
    if (empty($girls)) return '';
    $g = $girls[array_rand($girls)];
    $fotos = estados_pick_photos($g['fotos'], 2);
    $h = estados_hearts();
    $lines = [
        "🍭 Este caramelo quiere conocerte... {$g['nombre']} 🍭",
        $fotos[0] ?? '',
        $fotos[1] ?? '',
        '',
        "Pruébala. Solo una vez... ¿o no? 😋💘",
    ];
    return implode("\n", $lines);
}

function estados_format_trio_tentador(array $girls): string {
    if (empty($girls)) return '';
    shuffle($girls);
    $trio = array_slice($girls, 0, min(3, count($girls)));
    $h = estados_hearts();
    $lines = ['👯‍♀️ Triple tentación 👯‍♀️', ''];
    foreach ($trio as $g) {
        $lines[] = $g['nombre'];
        $fotos = estados_pick_photos($g['fotos'], 1);
        if (!empty($fotos[0])) $lines[] = $fotos[0];
    }
    $lines[] = '';
    $lines[] = "Tres veces el placer... ¿con cuál empiezas? 😏🔥";
    return implode("\n", $lines);
}

function estados_format_puertas_abiertas(array $girls): string {
    if (empty($girls)) return '';
    $h = estados_hearts();
    $lines = ['🚪 Abrimos para ti 🚪', ''];
    foreach ($girls as $g) {
        $lines[] = $g['nombre'];
        $fotos = estados_pick_photos($g['fotos'], 1);
        if (!empty($fotos[0])) $lines[] = $fotos[0];
    }
    $lines[] = '';
    $lines[] = "Pasa, mira, elige... y quédate. Esto es tu casa 💫🫶";
    return implode("\n", $lines);
}

function estados_format_ven_ya(array $girls): string {
    if (empty($girls)) return '';
    $g = $girls[array_rand($girls)];
    $fotos = estados_pick_photos($g['fotos'], 2);
    $h = estados_hearts();
    $lines = [
        "⏳ Se te acaba el día... y {$g['nombre']} te espera ⏳",
        $fotos[0] ?? '',
        $fotos[1] ?? '',
        '',
        "No lo pienses. Tú solo ven 🍑💨",
    ];
    return implode("\n", $lines);
}

function estados_format_susurro(array $girls): string {
    if (empty($girls)) return '';
    $g = $girls[array_rand($girls)];
    $fotos = estados_pick_photos($g['fotos'], 2);
    $h = estados_hearts();
    $lines = [
        "🤫 {$g['nombre']} quiere decirte algo al oído... 🤫",
        $fotos[0] ?? '',
        $fotos[1] ?? '',
        '',
        "Ven a escucharlo en persona. No es lo mismo por aquí 😘✨",
    ];
    return implode("\n", $lines);
}

function estados_format_antojos(array $girls): string {
    if (empty($girls)) return '';
    $h = estados_hearts();
    $lines = ['🍒 ¿De qué tienes hambre hoy? 🍒', ''];
    foreach ($girls as $g) {
        $lines[] = $g['nombre'];
        $fotos = estados_pick_photos($g['fotos'], 1);
        if (!empty($fotos[0])) $lines[] = $fotos[0];
    }
    $lines[] = '';
    $lines[] = "Dulce, picante, travieso... Tú pides, nosotras servimos 😋🔥";
    return implode("\n", $lines);
}

function estados_format_confesion(array $girls): string {
    if (empty($girls)) return '';
    $g = $girls[array_rand($girls)];
    $fotos = estados_pick_photos($g['fotos'], 2);
    $h = estados_hearts();
    $lines = [
        "🌙 Esta noche {$g['nombre']} confiesa... 🌙",
        $fotos[0] ?? '',
        $fotos[1] ?? '',
        '',
        "Hay cosas que solo se cuentan en persona. ¿Vienes? 🕯️💋",
    ];
    return implode("\n", $lines);
}

function estados_format_el_equipo(array $girls): string {
    if (empty($girls)) return '';
    $h = estados_hearts();
    $lines = ['💪 El equipazo de hoy 💪', ''];
    foreach ($girls as $g) {
        $lines[] = $g['nombre'];
        $fotos = estados_pick_photos($g['fotos'], 1);
        if (!empty($fotos[0])) $lines[] = $fotos[0];
    }
    $lines[] = '';
    $lines[] = "Todas con ganas. ¿A quién le das la camiseta? 🏆🔥";
    return implode("\n", $lines);
}

function estados_format_frescas(array $girls): string {
    if (empty($girls)) return '';
    $h = estados_hearts();
    $lines = ['🌸 Recién preparaditas para ti 🌸', ''];
    foreach ($girls as $g) {
        $lines[] = $g['nombre'];
        $fotos = estados_pick_photos($g['fotos'], 1);
        if (!empty($fotos[0])) $lines[] = $fotos[0];
    }
    $lines[] = '';
    $lines[] = "Llegan, se arreglan, te esperan. No las hagas esperar tú 💅💖";
    return implode("\n", $lines);
}

// ─── Format Builders (New 10) ──────────────────────────────────────────────

function estados_format_frase_del_dia(array $girls): string {
    if (empty($girls)) return '';
    $g = $girls[array_rand($girls)];
    $fotos = estados_pick_photos($g['fotos'], 2);
    $h = estados_hearts();
    $frases = [
        "{$g['nombre']} dice: Hoy me siento traviesa... ¿adivinas por qué? 😈",
        "{$g['nombre']} te pregunta: ¿Te atreves conmigo hoy? 🔥",
        "{$g['nombre']} confiesa: Llevo todo el día pensando en verte... 💭",
        "{$g['nombre']} suelta: ¿Mejor sola o mal acompañada? Yo soy la mejor compañía 😘",
        "Hoy {$g['nombre']} está que arde... literal y figuradamente 🥵🔥",
        "Frase del día: \"Lo bueno dura poco... pero se repite\" 😏",
    ];
    $frase = $frases[array_rand($frases)];
    $lines = [$frase];
    foreach ($fotos as $f) if ($f !== '') $lines[] = $f;
    $lines[] = '';
    $lines[] = "No te lo cuento, te lo demuestro 😏💋";
    return implode("\n", $lines);
}

function estados_format_solo_valientes(array $girls): string {
    if (empty($girls)) return '';
    $g = $girls[array_rand($girls)];
    $fotos = estados_pick_photos($g['fotos'], 2);
    $h = estados_hearts();
    $frases = [
        "Dicen que {$g['nombre']} es demasiado... ¿Te atreves a comprobarlo? 💪🔥",
        "{$g['nombre']} no es para cualquiera. ¿Crees que puedes con ella? 😤💋",
        "Aviso: {$g['nombre']} engancha. No nos hacemos responsables 😈",
        "{$g['nombre']} busca valientes. ¿Aceptas el desafío? 🎯🔥",
        "Entrar es fácil. Salir de {$g['nombre']}... ya es otra historia 😏💘",
    ];
    $frase = $frases[array_rand($frases)];
    $lines = [$frase];
    foreach ($fotos as $f) if ($f !== '') $lines[] = $f;
    $lines[] = '';
    $lines[] = "Pocos se atreven. ¿Eres uno de ellos? 💫";
    return implode("\n", $lines);
}

function estados_format_cita_a_ciegas(array $girls): string {
    if (empty($girls)) return '';
    $g = $girls[array_rand($girls)];
    $h = estados_hearts();
    // Cita a ciegas: mystery tone, NO photo
    $frases = [
        "Un nombre: {$g['nombre']}. Una noche: inolvidable. ¿Le das una oportunidad? 💘",
        "No sabes quién es, no sabes qué esperar... Solo sabes que será épico. {$g['nombre']} te espera 🎭",
        "Tu amigo te recomienda a {$g['nombre']}. No preguntes, solo ven. Luego nos cuentas 😏",
        "{$g['nombre']} existe. {$g['nombre']} es real. Y {$g['nombre']} quiere verte HOY 🤯",
        "Si te dijeran que tu cita perfecta está a un mensaje... ¿lo mandarías? 💬 {$g['nombre']} $h",
    ];
    $frase = $frases[array_rand($frases)];
    return $frase;
}

function estados_format_regalo_sorpresa(array $girls): string {
    if (empty($girls)) return '';
    $g = $girls[array_rand($girls)];
    $fotos = estados_pick_photos($g['fotos'], 1);
    $h = estados_hearts();
    $frases = [
        "🎁 Abre tu regalo de hoy... ¡es {$g['nombre']}! $h",
        "Tu regalo sorpresa está listo 📦 ¿Vienes a desenvolverlo? {$g['nombre']} te espera 🎀",
        "{$g['nombre']} se ha envuelto para ti... literal. HOY, regalo especial 🎁😏",
        "Dicen que los mejores regalos no se compran... se quedan. {$g['nombre']} quiere quedarse contigo 💝",
    ];
    $frase = $frases[array_rand($frases)];
    $lines = [$frase];
    foreach ($fotos as $f) if ($f !== '') $lines[] = $f;
    $lines[] = '';
    $lines[] = "Pide tu cita y recoge tu premio 🔥🏆";
    return implode("\n", $lines);
}

function estados_format_amiga_recomienda(array $girls): string {
    if (empty($girls)) return '';
    $g = $girls[array_rand($girls)];
    $fotos = estados_pick_photos($g['fotos'], 2);
    $h = estados_hearts();
    $frases = [
        "¿Aún no conoces a {$g['nombre']}? No sabes lo que te pierdes... y ella quiere que lo sepas 😏",
        "{$g['nombre']} no necesita presentación. Pero si la necesitas, aquí la tienes 👋🔥",
        "{$g['nombre']} tiene un don... y quiere compartirlo contigo. ¿Te animas? 💫",
        "Hay quien habla de {$g['nombre']}... pero mejor ven a conocerla tú mismo. No defrauda 💯",
        "Si sigues mirando la foto sin escribir... {$g['nombre']} se va a enfadar. No la hagas esperar 😤💋",
    ];
    $frase = $frases[array_rand($frases)];
    $lines = [$frase];
    foreach ($fotos as $f) if ($f !== '') $lines[] = $f;
    $lines[] = '';
    $lines[] = "Ella está lista. ¿Y tú? 😎🔥";
    return implode("\n", $lines);
}

function estados_format_modo_finde(array $girls): string {
    if (empty($girls)) return '';
    $pick = $girls;
    shuffle($pick);
    $top = array_slice($pick, 0, min(4, count($pick)));
    $h = estados_hearts();
    $frases = [
        '🍾 ¿Plan de finde? Nosotras te lo ponemos fácil 🎉',
        'El finde se vive mejor con compañía. Elige la tuya 🥂',
        'Sábado + nosotras = plan redondo. ¿Quién se apunta? 🔥',
        'De lunes a viernes trabajas, el finde... lo disfrutas con el equipazo 💃',
    ];
    $frase = $frases[array_rand($frases)];
    $lines = [$frase, ''];
    foreach ($top as $g) {
        $lines[] = $g['nombre'];
        $fotos = estados_pick_photos($g['fotos'], 1);
        if (!empty($fotos[0])) $lines[] = $fotos[0];
    }
    $lines[] = '';
    $lines[] = "¡Reserva tu cita antes de que vuele! 🚀💫";
    return implode("\n", $lines);
}

function estados_format_el_casting(array $girls): string {
    if (empty($girls)) return '';
    $pick = $girls;
    shuffle($pick);
    $h = estados_hearts();
    $frases = [
        "🎬 Hoy hacemos casting... y tú eres el juez. Elige a tu protagonista ⭐",
        "Se abre el telón 🎭 Estas son nuestras estrellas de hoy. ¿Cuál es tu favorita?",
        "Luces, cámara... ¡acción! 🎥 Ellas se presentan, tú decides quién se lleva el papel",
        "Audiciones abiertas. Ellas ya están listas. ¿Tú? 🎬✨",
    ];
    $frase = $frases[array_rand($frases)];
    $lines = [$frase, ''];
    foreach ($pick as $g) {
        $lines[] = '⭐ ' . $g['nombre'];
        $fotos = estados_pick_photos($g['fotos'], 1);
        if (!empty($fotos[0])) $lines[] = $fotos[0];
    }
    $lines[] = '';
    $lines[] = "Elige a tu favorita y dale el papel de tu vida 🎟️🔥";
    return implode("\n", $lines);
}

function estados_format_juego_parejas(array $girls): string {
    if (empty($girls)) return '';
    $pick = $girls;
    shuffle($pick);
    $duo = array_slice($pick, 0, min(2, count($pick)));
    if (count($duo) < 2) return estados_format_chica_del_dia($girls); // fallback
    $h = estados_hearts();
    $frases = [
        "Somos {$duo[0]['nombre']} y {$duo[1]['nombre']}: amigas, cómplices... y hoy nos compartes 😏",
        "{$duo[0]['nombre']} + {$duo[1]['nombre']} = la ecuación del placer. ¿La resuelves? 🔥🔥",
        "{$duo[0]['nombre']} pone el fuego, {$duo[1]['nombre']} la gasolina. Y tú... ¿te quemas? 🥵",
        "Dúo dinámico: {$duo[0]['nombre']} & {$duo[1]['nombre']}. La química que buscas 💥",
        "Juntas somos dinamita. {$duo[0]['nombre']} & {$duo[1]['nombre']} te esperan... ¿Vienes preparado? 🧨🔥",
    ];
    $frase = $frases[array_rand($frases)];
    $lines = [$frase, ''];
    foreach ($duo as $g) {
        $lines[] = $g['nombre'];
        $fotos = estados_pick_photos($g['fotos'], 1);
        if (!empty($fotos[0])) $lines[] = $fotos[0];
    }
    $lines[] = '';
    $lines[] = "El doble de química... ¿te atreves con las dos? 🔥💋";
    return implode("\n", $lines);
}

function estados_format_la_nueva(array $girls): string {
    if (empty($girls)) return '';
    $g = $girls[array_rand($girls)];
    $fotos = estados_pick_photos($g['fotos'], 2);
    $h = estados_hearts();
    $frases = [
        "{$g['nombre']} te está esperando. Y cuando ella espera... no le gusta esperar mucho 😏",
        "¿Conoces ya a {$g['nombre']}? Si no, hoy es el día de ponerle remedio 💘",
        "{$g['nombre']} está deseando conocerte... literal. Lleva toda la tarde preguntando por ti 🎀✨",
        "{$g['nombre']} hoy se ha puesto guapa para ti. ¿La vas a dejar plantada? 🌺",
        "{$g['nombre']} tiene un rato libre y ha pensado en ti... ¿qué le dices? 💭🥇",
    ];
    $frase = $frases[array_rand($frases)];
    $lines = [$frase];
    foreach ($fotos as $f) if ($f !== '') $lines[] = $f;
    $lines[] = '';
    $lines[] = "No hace falta segunda cita para saber que repites 😏🔥";
    return implode("\n", $lines);
}

function estados_format_oferta_flash(array $girls): string {
    if (empty($girls)) return '';
    $pick = $girls;
    shuffle($pick);
    $show = array_slice($pick, 0, min(3, count($pick)));
    $h = estados_hearts();
    $frases = [
        "⚡ No lo dejes para mañana... {$show[0]['nombre']} está libre AHORA ⚡",
        "🚨 {$show[0]['nombre']} acaba de confirmar que hoy se queda hasta tarde. ¿Vienes?",
        "Justo ahora {$show[0]['nombre']} está disponible. No siempre coincide... ¡aprovéchalo! 🔥",
        "{$show[0]['nombre']} dice que esta tarde no tiene planes... todavía. ¿Le haces uno? 😏",
        "Momento justo, chica justa. {$show[0]['nombre']} te espera... y el reloj corre ⏰💨",
    ];
    $frase = $frases[array_rand($frases)];
    $lines = [$frase, ''];
    foreach ($show as $g) {
        $lines[] = $g['nombre'];
        $fotos = estados_pick_photos($g['fotos'], 1);
        if (!empty($fotos[0])) $lines[] = $fotos[0];
    }
    $lines[] = '';
    $lines[] = "No siempre se alinean los astros. Hoy sí 🌟💫";
    return implode("\n", $lines);
}

// ─── Main Dispatcher ───────────────────────────────────────────────────────

/**
 * Resolve relative image proxy URLs to absolute URLs with scheme + host.
 * Converts lines like "/api/image-proxy.php?uid=9&img=xxx/xxx.jpg"
 * into "https://admin.casawasap.com/api/image-proxy.php?uid=9&img=xxx/xxx.jpg"
 *
 * @param string $text    Multiline status text to process
 * @param string $baseUrl Base URL including scheme and host (e.g. "https://admin.casawasap.com")
 * @return string Status text with resolved URLs
 */
function estados_resolve_image_urls(string $text, string $baseUrl): string {
    if ($text === '' || $baseUrl === '') return $text;

    $lines = explode("\n", $text);
    $resolved = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Check if line is a relative image-proxy URL
        if (str_starts_with($trimmed, '/api/image-proxy.php?')) {
            $resolved[] = rtrim($baseUrl, '/') . $trimmed;
        } else {
            $resolved[] = $line;
        }
    }
    return implode("\n", $resolved);
}

/**
 * Build a status text for the given girls with the selected format.
 * If format is 'mix_aleatorio', picks a random format (excluding itself).
 *
 * @param array $girls Array of ['id','nombre','fotos']
 * @param string $formato Format key from estados_format_options()
 * @return string Multiline status text (empty string on failure)
 */
function estados_build_status_text(array $girls, string $formato): string {
    if (empty($girls)) return '';

    $allFormats = array_keys(estados_format_options());

    if ($formato === 'mix_aleatorio') {
        $noMix = array_values(array_filter($allFormats, fn($f) => $f !== 'mix_aleatorio'));
        $formato = $noMix[array_rand($noMix)];
    }

    switch ($formato) {
        case 'chica_del_dia':       return estados_format_chica_del_dia($girls);
        case 'duo_sexy':            return estados_format_duo_sexy($girls);
        case 'catalogo_rapido':     return estados_format_catalogo_rapido($girls);
        case 'estrella_grupo':      return estados_format_estrella_grupo($girls);
        case 'tentacion_del_dia':   return estados_format_tentacion_del_dia($girls);
        case 'dulce_prohibido':     return estados_format_dulce_prohibido($girls);
        case 'trio_tentador':       return estados_format_trio_tentador($girls);
        case 'puertas_abiertas':    return estados_format_puertas_abiertas($girls);
        case 'ven_ya':              return estados_format_ven_ya($girls);
        case 'susurro':             return estados_format_susurro($girls);
        case 'antojos':             return estados_format_antojos($girls);
        case 'confesion':           return estados_format_confesion($girls);
        case 'el_equipo':           return estados_format_el_equipo($girls);
        case 'frescas':             return estados_format_frescas($girls);
        // New formats
        case 'frase_del_dia':       return estados_format_frase_del_dia($girls);
        case 'solo_valientes':      return estados_format_solo_valientes($girls);
        case 'cita_a_ciegas':       return estados_format_cita_a_ciegas($girls);
        case 'regalo_sorpresa':     return estados_format_regalo_sorpresa($girls);
        case 'amiga_recomienda':    return estados_format_amiga_recomienda($girls);
        case 'modo_finde':          return estados_format_modo_finde($girls);
        case 'el_casting':          return estados_format_el_casting($girls);
        case 'juego_parejas':       return estados_format_juego_parejas($girls);
        case 'la_nueva':            return estados_format_la_nueva($girls);
        case 'oferta_flash':        return estados_format_oferta_flash($girls);
        default:                    return estados_format_chicas_de_hoy($girls);
    }
}

/**
 * Build unique texts for multiple lines, avoiding duplicates.
 * Used when publishing to several WhatsApp lines at once.
 */
function estados_build_texts_for_cycle(array $girls, string $formato, int $lineCount): array {
    $lineCount = max(1, $lineCount);
    if (empty($girls)) return [];

    $pool = [];
    $seen = [];
    $attempts = 0;
    $maxAttempts = max($lineCount * 16, 24);

    while (count($pool) < $lineCount && $attempts < $maxAttempts) {
        $attempts++;
        $girlsVariant = $girls;
        shuffle($girlsVariant);
        $text = estados_build_status_text($girlsVariant, $formato);
        if ($text === '') continue;
        if (isset($seen[$text])) continue;
        $seen[$text] = true;
        $pool[] = $text;
    }

    if (empty($pool)) {
        $fallback = estados_build_status_text($girls, $formato);
        if ($fallback !== '') $pool[] = $fallback;
    }

    if (count($pool) > 1) shuffle($pool);

    if (count($pool) < $lineCount) {
        $extraAttempts = 0;
        $maxExtra = ($lineCount - count($pool)) * 16;
        while (count($pool) < $lineCount && $extraAttempts < $maxExtra) {
            $extraAttempts++;
            $girlsVariant = $girls;
            shuffle($girlsVariant);
            $text = estados_build_status_text($girlsVariant, $formato);
            if ($text === '') continue;
            $candidate = $text;
            if (isset($seen[$candidate])) {
                $candidate = $text . "\n" . estados_hearts();
            }
            if (isset($seen[$candidate])) continue;
            $seen[$candidate] = true;
            $pool[] = $candidate;
        }
    }

    $assigned = [];
    $poolCount = count($pool);
    if ($poolCount === 0) return $assigned;

    for ($i = 0; $i < $lineCount; $i++) {
        $idx = $i;
        if ($idx >= $poolCount) $idx = $idx % $poolCount;
        $assigned[] = $pool[$idx];
    }

    return $assigned;
}
