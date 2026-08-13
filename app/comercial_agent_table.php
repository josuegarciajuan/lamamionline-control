<?php
/**
 * Comercial Agent Table — Vista simplificada para comerciales (tarjetas)
 * 
 * Uso:
 *   require_once __DIR__ . '/comercial_agent_table.php';
 *   render_comercial_agent_table($threads, $linesIndexed);
 * 
 * $threads: array de hilos (de comercial_get_threads())
 * $linesIndexed: array indexado por line_id (de comercial_list_lines_indexed())
 */

require_once __DIR__ . '/comercial_agenda.php';

function comercial_agent_status_simple($thread) {
    $stage = trim((string)($thread['stage'] ?? ''));
    $humanTaken = !empty($thread['human_taken']);

    if ($stage === 'discarded') {
        return 'discarded';
    }
    if ($stage === 'qualified' || $stage === 'very_hot' || $humanTaken) {
        return 'done';
    }
    return 'pending';
}

function comercial_agent_status_label($simpleStatus) {
    switch ($simpleStatus) {
        case 'pending':   return 'Pendiente';
        case 'done':      return 'Atendido';
        case 'discarded': return 'Descartado';
        default:          return 'Sin estado';
    }
}

function comercial_agent_priority($thread) {
    $stage = trim((string)($thread['stage'] ?? ''));
    $hasReply = (int)($thread['replies_count'] ?? 0) > 0;
    $humanTaken = !empty($thread['human_taken']);
    $aiScore = (int)($thread['ai_interest_score'] ?? 0);
    $aiGenuine = !empty($thread['ai_is_genuine']);
    $aiSemantic = !empty($thread['ai_semantic_interest']);
    $aiSemScore = (int)($thread['ai_semantic_score'] ?? 0);

    if ($stage === 'discarded') return 'discarded';
    if ($stage === 'very_hot' || $stage === 'qualified') return 'done';

    if ($aiSemantic && $aiSemScore >= 60) return 'hot';
    if ($aiGenuine && $aiScore >= 70) return 'hot';
    if ($hasReply && !$humanTaken && $stage !== 'discarded') {
        return 'hot';
    }
    if ($aiSemantic && $aiSemScore >= 30) return 'warm';
    if ($aiGenuine && $aiScore >= 40) return 'warm';
    if ($stage === 'responded' || $stage === 'opened') {
        return 'warm';
    }
    return 'pending';
}

function comercial_agent_rama_icon($slug) {
    $icons = array(
        'lamami'      => '👩',
        'plaza'       => '🏛',
        'casawasap'   => '🏠',
        'publicista'  => '📢',
        'publiscort'  => '✂️',
    );
    return isset($icons[$slug]) ? $icons[$slug] : '📋';
}

function comercial_agent_rama_name($slug) {
    $names = array(
        'lamami'      => 'La Mami',
        'plaza'       => 'Plaza',
        'casawasap'   => 'CasaWasap',
        'publicista'  => 'Publicista',
        'publiscort'  => 'PubliScort',
    );
    return isset($names[$slug]) ? $names[$slug] : ucfirst(str_replace('_', ' ', $slug));
}

function comercial_agent_simple_summary($text, $maxWords = 15) {
    $text = trim((string)$text);
    if ($text === '') return '';
    $text = preg_replace('/\s+/u', ' ', $text);
    $words = explode(' ', $text);
    if (count($words) <= $maxWords) {
        return $text;
    }
    return implode(' ', array_slice($words, 0, $maxWords)) . '…';
}

function render_comercial_agent_table($threads, $linesIndexed = array()) {
    // Pre-cargar agenda para lookup rápido
    $agendaByPhone = [];
    if (function_exists('comercial_agenda_list')) {
        foreach (comercial_agenda_list() as $ae) {
            $norm = comercial_only_digits((string)($ae['telefono'] ?? ''));
            if ($norm !== '') $agendaByPhone[$norm] = $ae;
        }
    }

    $priorityOrder = array('hot' => 0, 'warm' => 1, 'pending' => 2, 'done' => 3, 'discarded' => 4);
    usort($threads, function ($a, $b) use ($priorityOrder) {
        $priA = $priorityOrder[comercial_agent_priority($a)] ?? 99;
        $priB = $priorityOrder[comercial_agent_priority($b)] ?? 99;
        if ($priA !== $priB) return $priA - $priB;
        $tsA = strtotime((string)($a['updated_at'] ?? ''));
        $tsB = strtotime((string)($b['updated_at'] ?? ''));
        return $tsB - $tsA;
    });

    $countAll = count($threads);
    $countPending = 0; $countDone = 0; $countDiscarded = 0;
    foreach ($threads as $t) {
        $s = comercial_agent_status_simple($t);
        if ($s === 'pending') $countPending++;
        elseif ($s === 'done') $countDone++;
        elseif ($s === 'discarded') $countDiscarded++;
    }

    $feedBase = function_exists('comercial_base_url') ? comercial_base_url() : '';
    $feedUrl = ($feedBase !== '' ? $feedBase : '') . '/comercial_thread_feed.php';
    ?>
    <section class="agent-table-panel" id="agentTablePanel">
        <!-- ── Header con contador + filtros ── -->
        <div class="agent-table-counter-bar">
            <div class="agent-counter-badge">
                <span class="count" id="agentPendingCount"><?= (int)$countPending ?></span>
                <span class="agent-counter-label">pendientes</span>
            </div>
            <div class="agent-quick-filters">
                <button type="button" class="agent-filter-btn is-active" data-filter="pending">
                    🔴 Pendientes <span class="badge"><?= (int)$countPending ?></span>
                </button>
                <button type="button" class="agent-filter-btn" data-filter="all">
                    📋 Todos <span class="badge"><?= (int)$countAll ?></span>
                </button>
                <button type="button" class="agent-filter-btn" data-filter="done">
                    ✅ Atendidos <span class="badge"><?= (int)$countDone ?></span>
                </button>
                <button type="button" class="agent-filter-btn" data-filter="discarded">
                    🗑 Descartados <span class="badge"><?= (int)$countDiscarded ?></span>
                </button>
            </div>
            <button type="button" class="agent-fullscreen-btn" id="agentFullscreenBtn" title="Abrir a pantalla completa">
                📺
            </button>
        </div>

        <!-- ── Grid de tarjetas ── -->
        <div class="agent-cards-grid" data-feed-url="<?= e($feedUrl) ?>">
            <?php if (empty($threads)): ?>
                <div class="agent-empty">
                    <span class="agent-empty-icon">✓</span>
                    <strong>Todo al día</strong>
                    <p>No hay conversaciones pendientes ahora mismo.</p>
                </div>
            <?php else: ?>
                <?php foreach ($threads as $thread):
                    $slug         = trim((string)($thread['process_slug'] ?? ''));
                    $phone        = trim((string)($thread['target_phone'] ?? ''));
                    $rawText      = trim((string)($thread['last_inbound_text'] ?? ''));
                    $aiSummary    = trim((string)($thread['ai_summary'] ?? ''));
                    $summary      = $aiSummary !== '' ? $aiSummary : comercial_agent_simple_summary($rawText);
                    $aiAdvice     = trim((string)($thread['ai_action_advice'] ?? ''));
                    $aiScore      = (int)($thread['ai_interest_score'] ?? 0);
                    $aiQualified  = !empty($thread['ai_qualified_at']);
                    $aiSemantic   = !empty($thread['ai_semantic_interest']);
                    $aiSemScore   = (int)($thread['ai_semantic_score'] ?? 0);
                    $aiSemReason  = trim((string)($thread['ai_semantic_reasoning'] ?? ''));
                    $manualPanel  = !empty($thread['manual_panel_include']);
                    $manualReason = trim((string)($thread['manual_panel_reason'] ?? ''));
                    $manualNegocio = trim((string)($thread['manual_panel_negocio'] ?? ''));
                    $simpleStatus = comercial_agent_status_simple($thread);
                    $priority     = comercial_agent_priority($thread);
                    $lineName     = isset($linesIndexed[(string)($thread['line_id'] ?? '')]) 
                                  ? trim((string)($linesIndexed[(string)$thread['line_id']]['nombre'] ?? '')) 
                                  : '';
                    $threadId     = trim((string)($thread['id'] ?? ''));

                    // Card style class
                    $cardClass = 'agent-card';
                    if ($priority === 'hot') $cardClass .= ' agent-card-hot';
                    elseif ($priority === 'warm') $cardClass .= ' agent-card-warm';
                    elseif ($priority === 'pending') $cardClass .= ' agent-card-pending';
                    elseif ($simpleStatus === 'done') $cardClass .= ' agent-card-done';
                    elseif ($simpleStatus === 'discarded') $cardClass .= ' agent-card-discarded';

                    // Status pill
                    $statusDotClass = '';
                    $statusIcon = '';
                    if ($simpleStatus === 'pending')   { $statusDotClass = 'dot-pending'; $statusIcon = '🔴'; }
                    elseif ($simpleStatus === 'done')    { $statusDotClass = 'dot-done';    $statusIcon = '✅'; }
                    elseif ($simpleStatus === 'discarded'){ $statusDotClass = 'dot-discarded'; $statusIcon = '⚫'; }

                    $waNumber = preg_replace('/[^0-9]/', '', $phone);
                    $waUrl = 'https://wa.me/' . $waNumber;

                    // Agenda: ¿está este teléfono agendado?
                    $phoneNorm = comercial_only_digits($phone);
                    $agendaMatch = $phoneNorm !== '' ? ($agendaByPhone[$phoneNorm] ?? null) : null;
                    $agendaName = $agendaMatch ? (string)($agendaMatch['nombre'] ?? '') : '';
                    $agendaId   = $agendaMatch ? (string)($agendaMatch['id'] ?? '') : '';

                    $replies = (int)($thread['replies_count'] ?? 0);
                    $sent = (int)($thread['messages_sent_count'] ?? 0);

                    $updatedAt = trim((string)($thread['updated_at'] ?? $thread['created_at'] ?? ''));
                    $relativeTime = '';
                    if ($updatedAt !== '') {
                        $ts = strtotime($updatedAt);
                        if ($ts !== false) {
                            $diff = time() - $ts;
                            if ($diff < 60) $relativeTime = 'ahora';
                            elseif ($diff < 3600) $relativeTime = 'hace ' . floor($diff / 60) . 'm';
                            elseif ($diff < 86400) $relativeTime = 'hace ' . floor($diff / 3600) . 'h';
                            elseif ($diff < 172800) $relativeTime = 'ayer';
                            else $relativeTime = 'hace ' . floor($diff / 86400) . 'd';
                        }
                    }
                ?>
                <div class="<?= $cardClass ?>"
                     data-agent-status="<?= $simpleStatus ?>"
                     data-thread-id="<?= e($threadId) ?>"
                     data-phone="<?= e($phone) ?>">

                    <!-- ── Cabecera: Rama + Estado ── -->
                    <div class="agent-card-top">
                        <div class="agent-card-rama">
                            <span class="agent-card-rama-icon"><?= comercial_agent_rama_icon($slug) ?></span>
                            <span class="agent-card-rama-name"><?= e(comercial_agent_rama_name($slug)) ?></span>
                            <?php if ($lineName !== ''): ?>
                                <span class="agent-card-line">via <?= e($lineName) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="agent-card-status <?= $simpleStatus ?>">
                            <?= $statusIcon ?> <?= e(comercial_agent_status_label($simpleStatus)) ?>
                        </span>
                    </div>

                    <!-- ── Teléfono (con nombre de agenda si existe) ── -->
                    <div class="agent-card-phone-row">
                        <?php if ($agendaName !== ''): ?>
                        <a href="#" class="agent-card-phone agent-card-phone--agenda" title="Ver ficha en agenda" onclick="event.preventDefault(); InboxChat.agendaOpenDetail('<?= e($agendaId) ?>'); return false;">
                            👤 <?= e($agendaName) ?>
                        </a>
                        <span class="agent-card-phone-sub"><?= e($phone) ?></span>
                        <?php else: ?>
                        <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener" class="agent-card-phone" title="Abrir en WhatsApp">
                            📞 <?= e($phone) ?>
                        </a>
                        <?php endif; ?>
                        <button type="button" class="agent-card-copy" data-phone="<?= e($phone) ?>" title="Copiar número">📋</button>
                    </div>

                    <!-- ── Mensaje ── -->
                    <?php if ($summary !== ''): ?>
                    <div class="agent-card-msg">
                        <?php if ($manualPanel): ?>
                            <span class="agent-card-manual-badge" title="Añadido manualmente al panel">📌 Manual</span>
                        <?php endif; ?>
                        <?php if ($aiSemantic && !$aiQualified): ?>
                            <span class="agent-card-semantic-badge" title="IA semantica · Score: <?= (int)$aiSemScore ?>/100 · <?= e($aiSemReason) ?>">🧠</span>
                        <?php endif; ?>
                        <?php if ($aiQualified && $aiSummary !== ''): ?>
                            <span class="agent-card-ai-badge" title="IA · Score: <?= (int)$aiScore ?>/100">🤖</span>
                        <?php endif; ?>
                        <span>“<?= e($summary) ?>”</span>
                    </div>
                    <?php endif; ?>

                    <!-- ── Motivo manual ── -->
                    <?php if ($manualPanel && $manualReason !== ''): ?>
                    <div class="agent-card-manual-reason">
                        📌 <strong><?= e($manualNegocio) ?></strong> — <?= e($manualReason) ?>
                    </div>
                    <?php endif; ?>

                    <!-- ── Consejo IA ── -->
                    <?php if ($aiAdvice !== ''): ?>
                    <div class="agent-card-advice">
                        💡 <?= e($aiAdvice) ?>
                    </div>
                    <?php endif; ?>

                    <!-- ── Footer: tiempo + stats ── -->
                    <div class="agent-card-meta">
                        <?php if ($relativeTime !== ''): ?>
                            <span class="agent-card-time"><?= e($relativeTime) ?></span>
                        <?php endif; ?>
                        <?php if ($replies > 0 || $sent > 0): ?>
                            <span><?= (int)$replies ?> resp. · <?= (int)$sent ?> env.</span>
                        <?php endif; ?>
                    </div>

                    <!-- ── Botones de acción ── -->
                    <div class="agent-card-actions">
                        <?php if ($simpleStatus === 'pending'): ?>
                        <button type="button" class="agent-card-btn agent-card-btn-attend" data-thread-id="<?= e($threadId) ?>">
                            ✅ Atender
                        </button>
                        <button type="button" class="agent-card-btn agent-card-btn-reply" data-thread-id="<?= e($threadId) ?>">
                            💬 Contestar
                        </button>
                        <?php elseif ($simpleStatus === 'done'): ?>
                        <button type="button" class="agent-card-btn agent-card-btn-convert-lead" data-thread-id="<?= e($threadId) ?>">
                            🎯 Convertir en lead
                        </button>
                        <button type="button" class="agent-card-btn agent-card-btn-view" data-thread-id="<?= e($threadId) ?>">
                            👁 Ver conversación
                        </button>
                        <?php else: ?>
                        <button type="button" class="agent-card-btn agent-card-btn-view" data-thread-id="<?= e($threadId) ?>">
                            👁 Ver conversación
                        </button>
                        <?php endif; ?>

                        <?php if ($simpleStatus !== 'discarded'): ?>
                        <button type="button" class="agent-card-btn agent-card-btn-discard" data-thread-id="<?= e($threadId) ?>">
                            ✕ Descartar
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php
}
