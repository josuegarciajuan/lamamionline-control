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

        self::assertSame(['completed' => false, 'skipped' => false], $state->read());
    }

    public function test_completion_is_persisted_only_for_the_selected_user(): void
    {
        $userState = new OnboardingState($this->rootDir, 42);
        $otherState = new OnboardingState($this->rootDir, 43);

        $userState->markCompleted();

        self::assertSame(['completed' => true, 'skipped' => false], $userState->read());
        self::assertSame(['completed' => false, 'skipped' => false], $otherState->read());
    }

    public function test_skip_is_distinct_from_completion(): void
    {
        $state = new OnboardingState($this->rootDir, 42);

        $state->markSkipped();

        self::assertSame(['completed' => false, 'skipped' => true], $state->read());
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
