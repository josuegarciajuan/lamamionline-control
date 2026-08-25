<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WasapBot\Core\OnboardingState;

final class OnboardingStateTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/wasapbot_onboarding_' . uniqid('', true);
        mkdir($this->rootDir . '/data/users', 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->rootDir);
    }

    public function test_new_user_has_pending_tutorial(): void
    {
        $state = new OnboardingState($this->rootDir, 42);

        self::assertSame('pending', $state->read()['status']);
        self::assertSame(1, $state->read()['version']);
        self::assertSame(0, $state->read()['current_step']);
        self::assertNull($state->read()['timestamps']['started_at']);
    }

    public function test_completion_is_persisted_only_for_the_selected_user(): void
    {
        $userState = new OnboardingState($this->rootDir, 42);
        $otherState = new OnboardingState($this->rootDir, 43);

        $userState->start();
        $userState->step(3);
        $userState->markCompleted();

        self::assertSame('completed', $userState->read()['status']);
        self::assertSame(10, $userState->read()['current_step']);
        self::assertNotNull($userState->read()['timestamps']['completed_at']);
        self::assertSame('pending', $otherState->read()['status']);
    }

    public function test_pause_preserves_progress_without_completing(): void
    {
        $state = new OnboardingState($this->rootDir, 42);

        $state->start();
        $state->pause();

        self::assertSame('paused', $state->read()['status']);
        self::assertNotNull($state->read()['timestamps']['paused_at']);
    }

    public function test_restart_returns_a_completed_tutorial_to_the_first_step(): void
    {
        $state = new OnboardingState($this->rootDir, 42);

        $state->start();
        $state->step(10);
        $state->markCompleted();
        $state->restart();

        self::assertSame('running', $state->read()['status']);
        self::assertSame(0, $state->read()['current_step']);
        self::assertNotNull($state->read()['timestamps']['restarted_at']);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }
        rmdir($dir);
    }
}
