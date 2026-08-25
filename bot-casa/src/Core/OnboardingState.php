<?php

declare(strict_types=1);

namespace WasapBot\Core;

final class OnboardingState
{
    public const VERSION = 1;
    public const STEP_COUNT = 11;

    private string $path;

    public function __construct(string $rootDir, int $userId)
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('A valid user id is required.');
        }

        $userDir = rtrim($rootDir, '/') . '/data/users/' . $userId;
        if (!is_dir($userDir)) {
            @mkdir($userDir, 0700, true);
        }
        $this->path = $userDir . '/onboarding.json';
    }

    /** @return array{version: int, status: string, current_step: int, timestamps: array<string, ?string>, completed: bool, skipped: bool} */
    public function read(): array
    {
        $state = $this->defaultState();
        if (is_readable($this->path)) {
            $decoded = json_decode((string) @file_get_contents($this->path), true);
            if (is_array($decoded)) {
                $state = $this->normalize($decoded);
            }
        }
        return $state;
    }

    public function start(): void
    {
        $state = $this->read();
        if (in_array($state['status'], ['completed', 'skipped'], true)) {
            return;
        }
        $state['status'] = 'running';
        $state['timestamps']['started_at'] ??= $this->now();
        $this->write($state);
    }

    public function step(int $step): void
    {
        if ($step < 0 || $step >= self::STEP_COUNT) {
            throw new \InvalidArgumentException('Invalid onboarding step.');
        }
        $state = $this->read();
        if ($state['status'] !== 'completed') {
            $state['status'] = 'running';
        }
        $state['current_step'] = $step;
        $state['timestamps']['started_at'] ??= $this->now();
        $this->write($state);
    }

    public function pause(): void
    {
        $state = $this->read();
        if ($state['status'] !== 'completed') {
            $state['status'] = 'paused';
            $state['timestamps']['paused_at'] = $this->now();
            $this->write($state);
        }
    }

    public function markCompleted(): void
    {
        $state = $this->read();
        $state['status'] = 'completed';
        $state['current_step'] = self::STEP_COUNT - 1;
        $state['timestamps']['started_at'] ??= $this->now();
        $state['timestamps']['completed_at'] = $this->now();
        $this->write($state);
    }

    public function markSkipped(): void
    {
        $state = $this->read();
        $state['status'] = 'skipped';
        $state['timestamps']['skipped_at'] = $this->now();
        $this->write($state);
    }

    public function restart(): void
    {
        $state = $this->defaultState();
        $state['status'] = 'running';
        $state['timestamps']['started_at'] = $this->now();
        $state['timestamps']['restarted_at'] = $state['timestamps']['started_at'];
        $this->write($state);
    }

    /** @param array<string, mixed> $state */
    private function normalize(array $state): array
    {
        $status = (string) ($state['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'running', 'paused', 'completed', 'skipped'], true)) {
            $status = 'pending';
        }
        $timestamps = is_array($state['timestamps'] ?? null) ? $state['timestamps'] : [];
        $result = $this->defaultState();
        $result['status'] = $status;
        $result['current_step'] = max(0, min(self::STEP_COUNT - 1, (int) ($state['current_step'] ?? 0)));
        foreach (array_keys($result['timestamps']) as $key) {
            $result['timestamps'][$key] = isset($timestamps[$key]) && is_string($timestamps[$key]) ? $timestamps[$key] : null;
        }
        // Read old installs without losing their completion/skip decision.
        if (!isset($state['status']) && !empty($state['completed'])) $result['status'] = 'completed';
        if (!isset($state['status']) && !empty($state['skipped'])) $result['status'] = 'skipped';
        $result['completed'] = $result['status'] === 'completed';
        $result['skipped'] = $result['status'] === 'skipped';
        return $result;
    }

    /** @return array{version: int, status: string, current_step: int, timestamps: array<string, ?string>, completed: bool, skipped: bool} */
    private function defaultState(): array
    {
        return [
            'version' => self::VERSION,
            'status' => 'pending',
            'current_step' => 0,
            'timestamps' => [
                'started_at' => null, 'updated_at' => null, 'paused_at' => null,
                'completed_at' => null, 'skipped_at' => null, 'restarted_at' => null,
            ],
            'completed' => false,
            'skipped' => false,
        ];
    }

    /** @param array<string, mixed> $state */
    private function write(array $state): void
    {
        $state['version'] = self::VERSION;
        $state['completed'] = $state['status'] === 'completed';
        $state['skipped'] = $state['status'] === 'skipped';
        $state['timestamps']['updated_at'] = $this->now();
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $tmp = $this->path . '.tmp.' . getmypid();
        if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !@rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new \RuntimeException('Unable to persist onboarding state.');
        }
        @chmod($this->path, 0600);
    }

    private function now(): string
    {
        return gmdate('c');
    }
}
