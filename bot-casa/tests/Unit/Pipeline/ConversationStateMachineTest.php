<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\ConversationStateMachine;
use WasapBot\Tests\Support\TmpEnv;
use WasapBot\Core\ConfigInterface;

/**
 * Unit tests for ConversationStateMachine — tracks conversation state.
 */
final class ConversationStateMachineTest extends TestCase
{
    private ConfigInterface $config;
    private ?TmpEnv $env = null;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
        $this->config = $this->env->config;
    }

    protected function tearDown(): void
    {
        $this->env?->cleanup();
        unset($this->env);
    }

    private function newFsm(): ConversationStateMachine
    {
        return new ConversationStateMachine($this->config);
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function test_empty_history_returns_new_state(): void
    {
        $fsm = $this->newFsm();
        $state = $fsm->computeState([], []);

        $this->assertSame('NEW', $state);
    }

    public function test_history_with_selected_girl_returns_girl_selected_state(): void
    {
        $fsm = $this->newFsm();
        $history = [
            [
                'bot_reply'          => 'hola cari',
                'user_msg'           => 'quiero a ana',
                'selected_girl_name' => 'Ana',
                'ya_enviado'         => [],
                'shown_girls'        => [],
            ],
        ];

        $state = $fsm->computeState($history, []);

        $this->assertSame('GIRL_SELECTED', $state);
    }

    public function test_history_with_maps_sent_returns_maps_sent_state(): void
    {
        $fsm = $this->newFsm();
        $history = [
            [
                'bot_reply'          => 'aqui la ubicacion',
                'user_msg'           => 'donde estas?',
                'selected_girl_name' => 'Ana',
                'ya_enviado'         => ['ubicacion'],
                'shown_girls'        => [],
                'maps_sent'          => true,
            ],
        ];

        $state = $fsm->computeState($history, []);

        $this->assertSame('MAPS_SENT', $state);
    }

    public function test_get_state_hint_returns_text_for_known_states(): void
    {
        $fsm = $this->newFsm();

        $hint = $fsm->getStateHint('NEW');
        $this->assertNotEmpty($hint);

        $hint = $fsm->getStateHint('GIRL_SELECTED');
        $this->assertNotEmpty($hint);
        $this->assertStringContainsString('eligió', $hint);

        $hint = $fsm->getStateHint('MAPS_SENT');
        $this->assertNotEmpty($hint);
        $this->assertStringContainsString('ETA', $hint);

        $hint = $fsm->getStateHint('DEAD');
        $this->assertStringContainsString('terminada', $hint);
    }

    public function test_get_state_hint_for_unknown_state_returns_empty(): void
    {
        $fsm = $this->newFsm();

        $hint = $fsm->getStateHint('NONEXISTENT_STATE');

        $this->assertSame('', $hint);
    }
}
