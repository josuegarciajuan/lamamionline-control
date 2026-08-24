<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | HITOS DE NEGOCIO
    |--------------------------------------------------------------------------
    | Controlan cuándo se lanzan avisos por hitos de ingresos/beneficio.
    */

    // Aviso cada vez que los ingresos del mes superan múltiplos de esta cifra.
    // Afecta a: "ingresos del mes superan 1000, 2000, 3000..."
    // Recomendado: 1000
    'income_milestone_step' => 1000,

    // Aviso cada vez que el beneficio real del mes superan múltiplos de esta cifra.
    // Afecta a: "beneficio del mes supera 500, 1000, 1500..."
    // Recomendado: 500
    'profit_milestone_step' => 500,

    // Porcentaje mínimo de concentración para avisar de que una sola rama
    // está absorbiendo demasiados ingresos del mes.
    // Afecta a: "Concentración alta en una sola rama"
    // Recomendado: 70
    'branch_concentration_percent' => 70,

    // Umbral mínimo en euros para considerar un gasto como "gasto alto".
    // Afecta a: "Gasto alto registrado"
    // Recomendado: 200
    'high_expense_amount' => 50,

    // Ventana máxima para considerar un alta/lead/pago como evento reciente
    // cuando el cron genera avisos de tipo "nuevo ...".
    // Evita que el primer cron dispare avisos históricos ya existentes.
    // Recomendado: 12
    'events_recent_hours' => 12,


    /*
    |--------------------------------------------------------------------------
    | INACTIVIDAD
    |--------------------------------------------------------------------------
    */

    // Horas sin ingresos para el aviso de inactividad corta.
    // Afecta a: "Más de 24h sin ingresos"
    // Recomendado: 24
    'no_income_hours_1' => 24,

    // Horas sin ingresos para el aviso de inactividad más seria.
    // Afecta a: "Más de 48h sin ingresos"
    // Recomendado: 48
    'no_income_hours_2' => 48,

    /*
    |--------------------------------------------------------------------------
    | INTERESADAS / CONVERSIÓN
    |--------------------------------------------------------------------------
    */

    // Horas que puede pasar una interesada nueva sin atender.
    // Afecta a:
    // - LaMami nueva sin atender
    // - Jostal nueva sin atender
    // - Casawasap interesado sin atender
    // Recomendado: 6
    'unattended_interesada_hours' => 8,

    // Horas que puede pasar una interesada atendida de LaMami sin convertir.
    // Afecta a: "Interesada atendida de LaMami sin convertir tras 24h"
    // Recomendado: 24
    'lamami_attended_without_convert_hours' => 48,

    // Días máximos antes de lanzar aviso por interesada antigua no convertida.
    // Afecta a las 3 ramas:
    // - LaMami
    // - Jostal
    // - Casawasap
    // Recomendado: 2
    'overdue_interesada_days' => 2,

    /*
    |--------------------------------------------------------------------------
    | RENDIMIENTO DE CLIENTAS / CLIENTES
    |--------------------------------------------------------------------------
    */

    // Días máximos sin leads para una clienta de LaMami.
    // Afecta a: "Clienta de LaMami sin leads tras X días"
    // Recomendado: 7
    'lamami_clienta_without_leads_days' => 3,

    // Días máximos sin ingresos para una clienta Jostal que está en casa.
    // Afecta a: "Clienta Jostal en casa sin ingresos tras X días"
    // Recomendado: 7
    'jostal_clienta_en_casa_without_income_days' => 2,

    // Días máximos sin pagos para un cliente Casawasap.
    // Afecta a: "Cliente Casawasap sin pagos tras X días"
    // Recomendado: 7
    'casawasap_cliente_without_pagos_days' => 8,

    /*
    |--------------------------------------------------------------------------
    | COBROS RECURRENTES / RETRASOS
    |--------------------------------------------------------------------------
    */

    // Frecuencia semanal estándar para revisar cobros recurrentes.
    // Afecta a:
    // - Cobro semanal de Casawasap alquiler
    // Recomendado: 7
    'weekly_cycle_days' => 7,

    // Ya no se está usando para avisos activos, pero se deja por compatibilidad.
    'overdue_additional_weeks' => 1,

    // Número mínimo total de renovaciones/cobros que vencen hoy
    // para lanzar el aviso agregado.
    // Afecta a: "Varias renovaciones/cobros vencen hoy"
    // Recomendado: 3
    'many_renewals_due_today_min_total' => 3,

    /*
    |--------------------------------------------------------------------------
    | PROYECCIONES / ESTRATEGIA
    |--------------------------------------------------------------------------
    */

    // Mínimo de días transcurridos del mes para empezar a lanzar avisos
    // de proyección mensual contra el mes anterior.
    // Recomendado: 5
    'projection_min_elapsed_days' => 1,

    // Factor máximo permitido frente al mes anterior.
    // Si la proyección del mes actual queda por debajo de este porcentaje
    // respecto al mes pasado, se lanza aviso.
    // Ejemplo: 0.80 = 80%
    // Recomendado: 0.80
    'projection_vs_previous_factor' => 0.80,

    // Número de días consecutivos empeorando para lanzar aviso
    // de tendencia negativa.
    // Actualmente el motor usa 3 días.
    // Recomendado: 3
    'negative_trend_days' => 3,

    /*
    |--------------------------------------------------------------------------
    | INTEGRIDAD / RUIDO DEL SISTEMA
    |--------------------------------------------------------------------------
    */

    // Número de avisos activos a partir del cual se considera que ya hay ruido.
    // Afecta a: "Hay demasiados avisos activos"
    // Recomendado: 15
    'too_many_active_alerts_count' => 15,


    /*
    |--------------------------------------------------------------------------
    | RECORDATORIOS OPERATIVOS / PUBLICIDAD
    |--------------------------------------------------------------------------
    */

    // Activa o desactiva el aviso recurrente de "subir publicidad a Destacamos".
    // 1 = activo, 0 = desactivado.
    // Este aviso sirve como recordatorio operativo interno para no olvidar
    // volver a subir / renovar / republicar la publicidad destacada.
    'destacamos_reminder_enabled' => 1,

    // Horas del día en las que debe saltar el aviso de Destacamos.
    // Formato: una hora por línea en HH:MM.
    // Por defecto: 00:01 y 12:01.
    // Cada franja genera un aviso independiente, descartable como cualquier otro.
    'destacamos_reminder_times' => "00:01\n12:01",

    // Minutos de tolerancia para considerar que el cron todavía está “dentro”
    // de la franja programada. Evita perder el aviso si el cron no se ejecuta
    // exactamente al minuto configurado.
    // Recomendado: 90
    'destacamos_reminder_window_minutes' => 90,

    // Activa o desactiva el aviso recurrente de "subir publicidad a MundoSex".
    // 1 = activo, 0 = desactivado.
    'mundosex_reminder_enabled' => 1,

    // Cada cuántas horas debe repetirse el aviso de MundoSex dentro de la franja.
    // Ejemplo:
    // - 2 = cada 2 horas
    // - 3 = cada 3 horas
    // - 4 = cada 4 horas
    // Valor por defecto elegido: 4
    'mundosex_reminder_interval_hours' => 4,

    // Hora de inicio diaria para empezar a lanzar recordatorios de MundoSex.
    // Formato HH:MM. Ejemplo: 08:00
    'mundosex_reminder_start_time' => '08:00',

    // Hora final diaria para dejar de lanzar recordatorios de MundoSex.
    // Formato HH:MM. Ejemplo: 23:00
    // Si coincide un múltiplo exacto del intervalo dentro de esta franja,
    // también se genera aviso.
    'mundosex_reminder_end_time' => '23:00',

    // Minutos de tolerancia del recordatorio de MundoSex.
    // Recomendado: 90
    'mundosex_reminder_window_minutes' => 90,


    /*
    |--------------------------------------------------------------------------
    | ENVÍO WHATSAPP DE AVISOS
    |--------------------------------------------------------------------------
    */

    // Clave del preset activo para enviar los avisos por WhatsApp.
    // Posibles valores:
    // - dulce_oficina
    // - dulce_josue
    // - salado_oficina
    // - salado_josue
    'whatsapp_sender_key' => 'dulce_oficina',

    // Teléfonos destino que recibirán los avisos. Puede ser string con saltos
    // de línea/comas o array si luego se sobreescribe desde settings.json.
    'whatsapp_target_phones' => "654464023\n641993776",

    // Perfil de ruido para decidir qué avisos se mandan por WhatsApp:
    // - conservador: manda alta/media/baja
    // - balanceado: manda alta/media
    // - agresivo: manda solo alta
    'alerts_noise_profile' => 'agresivo',

    // Mapeo manual de tipo de aviso -> línea de envío.
    // Formato (una por línea):
    // engine[:kind]=line_id_o_telefono
    // Ejemplos:
    // attention=12
    // recurring:destacamos_publish=631454098
    'whatsapp_sender_overrides' => "",


    /*
    |--------------------------------------------------------------------------
    | AFILIADOS (promoción de productos afiliados — rama WhatsApp + Destacamos)
    |--------------------------------------------------------------------------
    | Este módulo arranca DESACTIVADO (enabled=0). Se activa desde
    | Publicista → Afiliados cuando la API del repo de afiliados responda.
    | Se puede sobreescribir en settings.json → avisos_config.
    */

    // URL base del repo de afiliados. De ella se cuelgan:
    // - <base>/api/productos.json
    // - <base>/api/oferta-del-dia.json
    // - <base>/admin/  (panel embebido en el iframe)
    'afiliados_api_base_url' => 'https://josue.ink/afiliados',
    'afiliados_admin_url' => 'https://josue.ink/afiliados',

    // Cadencia de la rama WhatsApp de afiliados (estados + broadcast).
    // 'afiliados_frecuencia_tipo' => 'cada_x_horas' | 'x_veces_al_dia'
    'afiliados_frecuencia_tipo' => 'cada_x_horas',
    'afiliados_frecuencia_valor' => 6,

    // Ventana horaria de publicación.
    'afiliados_hora_inicio' => '08:00',
    'afiliados_hora_fin' => '23:00',

    // Valores de "uso" de telefonos.json cuyas líneas publicarán estados
    // (bot-casa + bot-comercial). Un valor por línea o separados por comas.
    'afiliados_lineas_uso' => "bot casa\nenvio publi",

    // Destinos de broadcast (un teléfono por línea o separados por comas).
    'afiliados_destinos_whatsapp' => "",

    // Campaña UTM para los enlaces afiliados.
    'afiliados_utm_campaign' => 'crm',

    // Anuncios Destacamos de producto: cadencia en días y hora.
    'afiliados_destacamos_interval_days' => 1,
    'afiliados_destacamos_hora' => '12:00',
);
