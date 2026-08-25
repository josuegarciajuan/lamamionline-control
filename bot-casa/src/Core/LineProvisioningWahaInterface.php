<?php

declare(strict_types=1);

namespace WasapBot\Core;

/** Minimal WAHA surface required by tenant line provisioning. */
interface LineProvisioningWahaInterface
{
    /** @return array<string, mixed> */
    public function getStatus(): array;

    /** @return array<string, mixed> */
    public function createInstance(int $port = 0): array;

    /** @return array<string, mixed> */
    public function configureSession(int $port, string $webhookUrl): array;

    /** @return array<string, mixed> */
    public function deleteInstance(int $port): array;

    /** @return array<string, mixed> */
    public function resetInstance(int $port): array;
}
