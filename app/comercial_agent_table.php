<?php
/**
 * Comercial Agent Table — Vista simplificada para comerciales
 * 
 * Uso:
 *   require_once __DIR__ . '/comercial_agent_table.php';
 *   render_comercial_agent_table($threads, $linesIndexed);
 * 
 * $threads: array de hilos (de comercial_get_threads())
 * $linesIndexed: array indexado por line_id (de comercial_list_lines_indexed())
 */

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

    if ($stage === 'discarded') return 'discarded';
    if ($stage === 'very_hot' || $stage === 'qualified') return 'done';

    if ($aiGenuine && $aiScore >= 70) return 'hot';
    if ($hasReply && !$humanTaken && $stage !== 'discarded') {
        return 'hot';
    }
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

    $csrf = csrf_token();
    $feedBase = function_exists('comercial_base_url') ? comercial_base_url() : '';
    $feedUrl = ($feedBase !== '' ? $feedBase : '') . '/comercial_thread_feed.php';
    ?>
    <section class="agent-table-panel" id="agentTablePanel">
        <div class="agent-table-counter-bar">
            <div class="agent-counter-badge">
                <span class="count" id="agentPendingCount"><?= (int)$countPending ?></span>
                <span class="agent-counter-label">Pendientes hoy</span>
            </div>
            <div class="agent-quick-filters">
                <button type="button" class="agent-filter-btn is-active" data-filter="pending">
                    Pendientes <span class="badge"><?= (int)$countPending ?></span>
                </button>
                <button type="button" class="agent-filter-btn" data-filter="all">
                    Todos <span class="badge"><?= (int)$countAll ?></span>
                </button>
                <button type="button" class="agent-filter-btn" data-filter="done">
                    Atendidos <span class="badge"><?= (int)$countDone ?></span>
                </button>
                <button type="button" class="agent-filter-btn" data-filter="discarded">
                    Descartados <span class="badge"><?= (int)$countDiscarded ?></span>
                </button>
            </div>
            <button type="button" class="agent-fullscreen-btn" id="agentFullscreenBtn" title="Abrir a pantalla completa">
                📺 Pantalla completa
            </button>
        </div>

        <div class="agent-table-wrap" data-feed-url="<?= e($feedUrl) ?>">
            <table class="agent-table">
                <thead>
                    <tr>
                        <th class="col-rama">Rama</th>
                        <th class="col-phone">Teléfono</th>
                        <th class="col-what">¿Qué quiere?</th>
                        <th class="col-advice">Qué hacer</th>
                        <th class="col-status">Estado</th>
                        <th class="col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($threads)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="agent-empty">
                                    <strong>Todo al día</strong>
                                    No hay conversaciones pendientes ahora mismo.
                                </div>
                            </td>
                        </tr>
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
                            $simpleStatus = comercial_agent_status_simple($thread);
                            $priority     = comercial_agent_priority($thread);
                            $lineName     = isset($linesIndexed[(string)($thread['line_id'] ?? '')]) 
                                          ? trim((string)($linesIndexed[(string)$thread['line_id']]['nombre'] ?? '')) 
                                          : '';
                            $threadId     = trim((string)($thread['id'] ?? ''));

                            $rowClass = 'agent-data-row';
                            if ($priority === 'hot') $rowClass .= ' agent-row-hot';
                            elseif ($priority === 'warm') $rowClass .= ' agent-row-warm';
                            elseif ($priority === 'pending') $rowClass .= ' agent-row-pending';
                            elseif ($simpleStatus === 'done') $rowClass .= ' agent-row-done';
                            elseif ($simpleStatus === 'discarded') $rowClass .= ' agent-row-discarded';

                            $statusClass = 'agent-status';
                            $statusDot   = '';
                            if ($simpleStatus === 'pending') {
                                $statusClass .= ' is-pending';
                                $statusDot = '<span class="status-dot dot-pending"></span> ';
                            } elseif ($simpleStatus === 'done') {
                                $statusClass .= ' is-done';
                                $statusDot = '<span class="status-dot dot-done"></span> ';
                            } elseif ($simpleStatus === 'discarded') {
                                $statusClass .= ' is-discarded';
                                $statusDot = '<span class="status-dot dot-discarded"></span> ';
                            }

                            $waNumber = preg_replace('/[^0-9]/', '', $phone);
                            $waUrl = 'https://wa.me/' . $waNumber;
                        ?>
                        <tr class="<?= $rowClass ?>" data-agent-status="<?= $simpleStatus ?>" data-thread-id="<?= e($threadId) ?>">
                            <!-- Rama -->
                            <td class="col-rama" data-label="Rama">
                                <div class="agent-rama">
                                    <span class="agent-rama-icon"><?= comercial_agent_rama_icon($slug) ?></span>
                                    <div>
                                        <span class="agent-rama-name"><?= e(comercial_agent_rama_name($slug)) ?></span>
                                        <?php if ($lineName !== ''): ?>
                                            <span class="agent-rama-tag">via <?= e($lineName) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Teléfono -->
                            <td class="col-phone" data-label="Teléfono">
                                <div class="agent-phone-row">
                                    <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener" class="agent-phone-wa" title="Abrir en WhatsApp">
                                        <span class="wa-icon">💬</span>
                                        <span class="agent-phone"><?= e($phone) ?></span>
                                    </a>
                                    <button type="button" class="agent-copy-btn" data-phone="<?= e($phone) ?>" title="Copiar teléfono">📋</button>
                                </div>
                                <?php
                                $replies = (int)($thread['replies_count'] ?? 0);
                                $sent = (int)($thread['messages_sent_count'] ?? 0);
                                if ($replies > 0 || $sent > 0):
                                ?>
                                <div class="agent-phone-meta">
                                    <?= (int)$replies ?> resp. · <?= (int)$sent ?> env.
                                </div>
                                <?php endif; ?>
                            </td>

                            <!-- ¿Qué quiere? -->
                            <td class="col-what" data-label="¿Qué quiere?">
                                <?php if ($summary !== ''): ?>
                                    <?php if ($aiQualified && $aiSummary !== ''): ?>
                                        <span class="agent-ai-badge" title="IA · Score: <?= (int)$aiScore ?>/100">🤖</span>
                                    <?php endif; ?>
                                    <span class="agent-what"><?= e($summary) ?></span>
                                <?php else: ?>
                                    <span class="agent-what is-empty">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Qué hacer (consejo IA) -->
                            <td class="col-advice" data-label="Qué hacer">
                                <?php if ($aiAdvice !== ''): ?>
                                    <span class="agent-advice">💡 <?= e($aiAdvice) ?></span>
                                <?php elseif ($simpleStatus === 'pending'): ?>
                                    <span class="agent-advice is-empty">Llama y pregunta si le interesa</span>
                                <?php else: ?>
                                    <span class="agent-advice is-empty">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Estado -->
                            <td class="col-status" data-label="Estado">
                                <span class="<?= $statusClass ?>">
                                    <?= $statusDot ?><?= e(comercial_agent_status_label($simpleStatus)) ?>
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="col-actions" data-label="Acciones">
                                <div class="agent-actions">
                                    <?php if ($simpleStatus === 'pending'): ?>
                                    <button type="button" class="agent-btn agent-btn-attend" data-thread-id="<?= e($threadId) ?>">
                                        📞 Atendido
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="agent-btn agent-btn-view" data-thread-id="<?= e($threadId) ?>">
                                        👁 Ver
                                    </button>
                                    <?php if ($simpleStatus !== 'discarded'): ?>
                                    <button type="button" class="agent-btn agent-btn-discard" data-thread-id="<?= e($threadId) ?>">
                                        🗑 Descartar
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}