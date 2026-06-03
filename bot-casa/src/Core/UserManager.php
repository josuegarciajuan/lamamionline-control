<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * UserManager — Gestión de usuarios multi-tenant para bot-casa.
 *
 * Almacena usuarios en data/users.json (en el directorio base del proyecto).
 * Usa bcrypt para passwords y flock() para acceso concurrente seguro.
 *
 * Si users.json no existe, crea automáticamente un admin por defecto:
 *   username: admin / password: admin123
 */
final class UserManager
{
    private string $usersFile;

    /** @var resource|null File handle for the lock file */
    private $lockHandle = null;

    /** @var array<string, mixed>|null Caché en memoria */
    private ?array $cache = null;

    public function __construct(string $baseDir)
    {
        $this->usersFile = rtrim($baseDir, '/') . '/data/users.json';
    }

    // ─────────────────────────────────────────────────────────
    //  CRUD
    // ─────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    public function listUsers(): array
    {
        $data = $this->loadUsers();
        $users = $data['users'] ?? [];
        return is_array($users) ? array_values($users) : [];
    }

    /** @return array<string, mixed>|null */
    public function getUser(int $id): ?array
    {
        foreach ($this->listUsers() as $u) {
            if ((int) ($u['id'] ?? 0) === $id) {
                return $u;
            }
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    public function getUserByUsername(string $username): ?array
    {
        foreach ($this->listUsers() as $u) {
            if (strtolower((string) ($u['username'] ?? '')) === strtolower($username)) {
                return $u;
            }
        }
        return null;
    }

    /**
     * Crea un nuevo usuario.
     *
     * @return array{ok: bool, user?: array, error?: string}
     */
    public function createUser(string $username, string $password, string $role = 'user', string $name = ''): array
    {
        $username = trim($username);
        if ($username === '') {
            return ['ok' => false, 'error' => 'El nombre de usuario es obligatorio.'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres.'];
        }

        $allowedRoles = ['admin', 'user'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'user';
        }

        // ── Lock for the entire read-modify-write cycle ──
        if (!$this->acquireLock()) {
            return ['ok' => false, 'error' => 'Error interno. Inténtelo de nuevo.'];
        }
        try {
            // Re-read inside lock to avoid stale cache
            $this->cache = null;
            $data = $this->loadUsers();

            if ($this->getUserByUsername($username) !== null) {
                return ['ok' => false, 'error' => 'El usuario ya existe.'];
            }

            $nextId = (int) ($data['next_id'] ?? 1);

            $user = [
                'id' => $nextId,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'role' => $role,
                'name' => $name !== '' ? $name : $username,
                'created_at' => date('c'),
                'active' => true,
            ];

            $data['users'][] = $user;
            $data['next_id'] = $nextId + 1;

            $this->saveUsers($data);

            return ['ok' => true, 'user' => $user];
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Actualiza un usuario existente.
     *
     * @param array<string, mixed> $fields Campos a actualizar (username, password, role, name, active)
     * @return array{ok: bool, error?: string}
     */
    public function updateUser(int $id, array $fields): array
    {
        if (!$this->acquireLock()) {
            return ['ok' => false, 'error' => 'Error interno. Inténtelo de nuevo.'];
        }
        try {
            // Re-read inside lock to avoid stale cache
            $this->cache = null;
            $data = $this->loadUsers();
            $found = false;

            foreach ($data['users'] as &$u) {
                if ((int) ($u['id'] ?? 0) !== $id) {
                    continue;
                }
                $found = true;

                if (isset($fields['username']) && is_string($fields['username'])) {
                    $newUsername = trim($fields['username']);
                    if ($newUsername !== '' && $newUsername !== ($u['username'] ?? '')) {
                        $existing = $this->getUserByUsername($newUsername);
                        if ($existing !== null && (int) ($existing['id'] ?? 0) !== $id) {
                            return ['ok' => false, 'error' => 'El nombre de usuario ya está en uso.'];
                        }
                        $u['username'] = $newUsername;
                    }
                }

                if (isset($fields['password']) && is_string($fields['password']) && $fields['password'] !== '') {
                    $u['password_hash'] = password_hash($fields['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                }

                if (isset($fields['role']) && in_array($fields['role'], ['admin', 'user'], true)) {
                    $u['role'] = $fields['role'];
                }

                if (isset($fields['name']) && is_string($fields['name'])) {
                    $u['name'] = trim($fields['name']);
                }

                if (isset($fields['active'])) {
                    $u['active'] = (bool) $fields['active'];
                }

                break;
            }
            unset($u);

            if (!$found) {
                return ['ok' => false, 'error' => 'Usuario no encontrado.'];
            }

            $this->saveUsers($data);
            return ['ok' => true];
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Elimina un usuario (soft delete: active=false). No se puede eliminar al admin principal (id=1).
     */
    public function deleteUser(int $id): array
    {
        if ($id === 1) {
            return ['ok' => false, 'error' => 'No se puede eliminar al administrador principal.'];
        }
        return $this->updateUser($id, ['active' => false]);
    }

    // ─────────────────────────────────────────────────────────
    //  Auth
    // ─────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    public function authenticate(string $username, string $password): ?array
    {
        $user = $this->getUserByUsername($username);
        if ($user === null) {
            return null;
        }
        if (empty($user['active'])) {
            return null;
        }
        $hash = (string) ($user['password_hash'] ?? '');
        if (!password_verify($password, $hash)) {
            return null;
        }

        // Rehash if needed (cost changed)
        if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $data = $this->loadUsers();
            foreach ($data['users'] as &$u) {
                if ((int) ($u['id'] ?? 0) === (int) ($user['id'] ?? 0)) {
                    $u['password_hash'] = $newHash;
                    break;
                }
            }
            unset($u);
            $this->saveUsers($data);
        }

        return $user;
    }

    // ─────────────────────────────────────────────────────────
    //  Persistencia
    // ─────────────────────────────────────────────────────────

    /**
     * Acquire an exclusive lock for the read-modify-write cycle.
     * Uses a dedicated .lock file to avoid race conditions.
     *
     * @return bool True if lock acquired
     */
    private function acquireLock(): bool
    {
        $lockFile = $this->usersFile . '.lock';
        $dir = dirname($lockFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $handle = @fopen($lockFile, 'w');
        if ($handle === false) {
            return false;
        }

        // Non-blocking attempt with 2-second timeout
        $timeout = 2_000_000; // 2 seconds in microseconds
        $waited = 0;
        while (!flock($handle, LOCK_EX | LOCK_NB)) {
            if ($waited >= $timeout) {
                fclose($handle);
                return false;
            }
            usleep(50_000); // 50ms
            $waited += 50_000;
        }

        $this->lockHandle = $handle;
        return true;
    }

    /**
     * Release the exclusive lock.
     */
    private function releaseLock(): void
    {
        if ($this->lockHandle !== null) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    /** @return array{users: list<array>, next_id: int} */
    private function loadUsers(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $dir = dirname($this->usersFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        // ⚠️ NO auto-crear users.json nunca.
        // En producción el panel/bot funcionan sin autenticación
        // hasta que el admin visita /login explícitamente (que llama a seedDefaultAdmin).
        if (!file_exists($this->usersFile)) {
            $this->cache = ['users' => [], 'next_id' => 1];
            return $this->cache;
        }

        $content = @file_get_contents($this->usersFile);
        if ($content === false || $content === '') {
            $this->cache = ['users' => [], 'next_id' => 1];
            return $this->cache;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->cache = ['users' => [], 'next_id' => 1];
            return $this->cache;
        }

        if (!is_array($data)) {
            $this->cache = ['users' => [], 'next_id' => 1];
            return $this->cache;
        }

        // Normalizar
        if (!isset($data['users']) || !is_array($data['users'])) {
            $data['users'] = [];
        }
        if (!isset($data['next_id']) || !is_int($data['next_id'])) {
            $data['next_id'] = count($data['users']) + 1;
        }

        $this->cache = $data;
        return $this->cache;
    }

    /**
     * Comprueba si el archivo users.json existe (indica que el sistema
     * multi-usuario está activado). Si no existe, el panel funciona
     * en modo legacy sin autenticación.
     */
    public function hasUsersFile(): bool
    {
        return file_exists($this->usersFile);
    }

    /**
     * Crea el archivo users.json con el admin por defecto.
     * Solo se llama desde login.php en el primer acceso.
     *
     * @return array{ok: bool, user?: array}
     */
    public function seedDefaultAdmin(): array
    {
        if (file_exists($this->usersFile)) {
            return ['ok' => false, 'error' => 'Users file already exists.'];
        }

        $dir = dirname($this->usersFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $default = [
            'users' => [
                [
                    'id' => 1,
                    'username' => 'admin',
                    'password_hash' => password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]),
                    'role' => 'admin',
                    'name' => 'Administrador',
                    'created_at' => date('c'),
                    'active' => true,
                ],
            ],
            'next_id' => 2,
        ];

        $this->writeFile($default);
        $this->cache = $default;

        return ['ok' => true, 'user' => $default['users'][0]];
    }

    /** @param array<string, mixed> $data */
    private function saveUsers(array $data): void
    {
        $dir = dirname($this->usersFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('UserManager: JSON encode failed: ' . json_last_error_msg());
        }

        $written = @file_put_contents($this->usersFile, $json . "\n", LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException('UserManager: Cannot write to ' . $this->usersFile);
        }

        $this->cache = $data;
    }

    /** @param array<string, mixed> $data */
    private function writeFile(array $data): void
    {
        $dir = dirname($this->usersFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($this->usersFile, $json . "\n", LOCK_EX);
    }
}
