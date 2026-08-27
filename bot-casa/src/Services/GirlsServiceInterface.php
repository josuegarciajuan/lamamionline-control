<?php

declare(strict_types=1);

namespace WasapBot\Services;

/**
 * Girls catalog service contract — fetch and cache the girls JSON from remote API.
 */
interface GirlsServiceInterface
{
    /**
     * @return array<int, array{id: string, nombre: string, descripcion_corta: string, fotos: list<string>, activa: bool}>
     */
    public function fetchActive(): array;

    /**
     * @return array<int, array{id: string, nombre: string, descripcion_corta: string, fotos: list<string>, activa: bool}>
     */
    public function fetchAll(): array;

    /**
     * @return array<int, array{id: string, nombre: string, descripcion_corta: string, fotos: list<string>, activa: bool}>
     */
    public function getRandomSample(int $count): array;
    /** @return array<string, mixed>|null */
    public function findByName(string $name): ?array;
    public function reload(): void;
}
