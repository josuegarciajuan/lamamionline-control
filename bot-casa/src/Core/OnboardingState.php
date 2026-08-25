<?php

declare(strict_types=1);

namespace WasapBot\Core;

final class OnboardingState
{
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

    /** @return array{completed: bool, skipped: bool} */
    public function read(): array
    {
        if (!is_readable($this->path)) {
            return ['completed' => false, 'skipped' => false];
        }

        $decoded = json_decode((string) @file_get_contents($this->path), true);
        if (!is_array($decoded)) {
            return ['completed' => false, 'skipped' => false];
        }

        return [
            'completed' => !empty($decoded['completed']),
            'skipped' => !empty($decoded['skipped']),
        ];
    }

    public function markCompleted(): void
    {
        $this->write(['completed' => true, 'skipped' => false]);
    }

    public function markSkipped(): void
    {
        $this->write(['completed' => false, 'skipped' => true]);
    }

    /** @param array{completed: bool, skipped: bool} $state */
    private function write(array $state): void
    {
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $tmp = $this->path . '.tmp.' . getmypid();
        if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !@rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new \RuntimeException('Unable to persist onboarding state.');
        }
        @chmod($this->path, 0600);
    }
}
