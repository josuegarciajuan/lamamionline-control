<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * DeviceLock — restringe el acceso de una app a uno o varios dispositivos
 * identificados por fingerprint del navegador. No depende de la IP.
 *
 * Modos:
 *   - observe: no bloquea nada, solo registra candidatos (fingerprint).
 *   - enforce: solo los dispositivos autorizados pasan; el resto recibe un
 *     404 neutro (la app "no existe") y se le invalida la sesión.
 *
 * El identificador duro es un token aleatorio (cookie HttpOnly de larga
 * duración) atado a un fingerprint de hardware/navegador (canvas, WebGL…).
 * El estado se guarda en un fichero .php que devuelve 404 si se pide por web.
 */
final class DeviceLock
{
    public const MODE_OBSERVE = 'observe';
    public const MODE_ENFORCE = 'enforce';

    private const PREFIX = "<?php http_response_code(404); exit; ?>\n";
    private const MAX_CANDIDATES = 80;
    private const CANDIDATE_TTL = 1209600; // 14 días
    private const MAX_TOKENS_PER_DEVICE = 10;

    private string $stateFile;
    private string $cookieName;

    public function __construct(string $stateFile, string $cookieName = 'dlk_dev')
    {
        $this->stateFile = $stateFile;
        $this->cookieName = $cookieName;
    }

    public static function now(): string
    {
        return gmdate('c');
    }

    // ─────────────────────────────────────────────────────────────
    //  Estado
    // ─────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function state(): array
    {
        $default = [
            'version' => 1,
            'mode' => self::MODE_OBSERVE,
            'updated_at' => self::now(),
            'devices' => [],
            'candidates' => [],
        ];

        if (!is_file($this->stateFile)) {
            return $default;
        }
        $raw = @file_get_contents($this->stateFile);
        if (!is_string($raw) || $raw === '') {
            return $default;
        }
        $json = preg_replace('/^<\?php.*?\?>\s*/s', '', $raw, 1);
        if (!is_string($json) || $json === '') {
            return $default;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $default;
        }

        /** @var array<string,mixed> $decoded */
        $decoded += $default;
        if (!is_array($decoded['devices'])) {
            $decoded['devices'] = [];
        }
        if (!is_array($decoded['candidates'])) {
            $decoded['candidates'] = [];
        }
        return $decoded;
    }

    /** @param array<string,mixed> $state */
    public function save(array $state): bool
    {
        $state['updated_at'] = self::now();
        $dir = dirname($this->stateFile);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            return false;
        }
        $tmp = $this->stateFile . '.tmp.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, self::PREFIX . $json, LOCK_EX) === false) {
            return false;
        }
        // Lectura/escritura para el usuario web y para el CLI (root):
        // el directorio data/ ya es 0777, así que 0666 no amplía la exposición.
        @chmod($tmp, 0666);
        if (!@rename($tmp, $this->stateFile)) {
            @unlink($tmp);
            return false;
        }
        @chmod($this->stateFile, 0666);
        return true;
    }

    public function mode(): string
    {
        $state = $this->state();
        return (($state['mode'] ?? '') === self::MODE_ENFORCE) ? self::MODE_ENFORCE : self::MODE_OBSERVE;
    }

    public function setMode(string $mode): bool
    {
        $state = $this->state();
        $state['mode'] = ($mode === self::MODE_ENFORCE) ? self::MODE_ENFORCE : self::MODE_OBSERVE;
        return $this->save($state);
    }

    // ─────────────────────────────────────────────────────────────
    //  Token de dispositivo (cookie)
    // ─────────────────────────────────────────────────────────────

    private function cookieToken(): ?string
    {
        $value = $_COOKIE[$this->cookieName] ?? '';
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return preg_match('/^[a-f0-9]{64}$/', $value) === 1 ? $value : null;
    }

    public static function hashToken(string $raw): string
    {
        return hash('sha256', $raw);
    }

    private function issueToken(): string
    {
        $raw = bin2hex(random_bytes(32));
        if (!headers_sent()) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
            setcookie($this->cookieName, $raw, [
                'expires' => time() + 3650 * 86400,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[$this->cookieName] = $raw;
        return $raw;
    }

    // ─────────────────────────────────────────────────────────────
    //  Fingerprint
    // ─────────────────────────────────────────────────────────────

    public static function normalizeFingerprint(mixed $fingerprint): ?string
    {
        if (!is_string($fingerprint)) {
            return null;
        }
        $fingerprint = strtolower(trim($fingerprint));
        return preg_match('/^[a-f0-9]{64}$/', $fingerprint) === 1 ? $fingerprint : null;
    }

    /**
     * Deriva el fingerprint en servidor a partir de señales estables.
     * Solo se incluyen componentes deterministas (sin audio ni medidas
     * variables) para que el mismo dispositivo dé siempre el mismo hash.
     *
     * @param array<int|string,mixed> $signals
     */
    public static function fingerprintFromSignals(array $signals): ?string
    {
        $keys = [
            'ua', 'platform', 'lang', 'langs', 'hw', 'mem', 'touch',
            'screen', 'tz', 'canvas', 'webgl',
            'ch_model', 'ch_platform', 'ch_platform_version', 'ch_arch', 'ch_bitness',
        ];

        $parts = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $signals)) {
                continue;
            }
            $value = $signals[$key];
            if (is_array($value)) {
                $flattened = [];
                foreach ($value as $item) {
                    if (is_scalar($item)) {
                        $flattened[] = (string)$item;
                    }
                }
                $value = implode(',', $flattened);
            }
            if (!is_scalar($value)) {
                continue;
            }
            $parts[] = $key . '=' . (string)$value;
        }

        if (count($parts) < 4) {
            return null;
        }
        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param array<string,mixed> $state
     * @return int|null índice del dispositivo autorizado
     */
    private function findAuthorizedByFingerprint(array $state, string $fingerprint): ?int
    {
        $devices = self::asArray($state['devices'] ?? null);
        foreach ($devices as $index => $device) {
            if (!is_array($device)) {
                continue;
            }
            if (self::asBool($device['authorized'] ?? false)
                && self::asString($device['fingerprint'] ?? '') === $fingerprint) {
                return (int)$index;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $state
     */
    private function findAuthorizedByToken(array $state, string $tokenHash): ?int
    {
        $devices = self::asArray($state['devices'] ?? null);
        foreach ($devices as $index => $device) {
            if (!is_array($device) || !self::asBool($device['authorized'] ?? false)) {
                continue;
            }
            foreach (self::asArray($device['tokens'] ?? null) as $token) {
                if (is_string($token) && hash_equals($token, $tokenHash)) {
                    return (int)$index;
                }
            }
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────
    //  Gate
    // ─────────────────────────────────────────────────────────────

    /**
     * Guard principal. Llama a deny() (404 + exit) si el dispositivo no está
     * autorizado en modo enforce. En observe nunca bloquea.
     */
    public function gate(string $app): bool
    {
        $state = $this->state();
        if (($state['mode'] ?? '') !== self::MODE_ENFORCE) {
            return true;
        }
        $token = $this->cookieToken();
        if ($token !== null && $this->findAuthorizedByToken($state, self::hashToken($token)) !== null) {
            return true;
        }
        $this->deny($app);
        return false;
    }

    public function deny(string $app): void
    {
        $accept = self::asString($_SERVER['HTTP_ACCEPT'] ?? '');
        $wantsJson = stripos($accept, 'application/json') !== false
            || strtolower(self::asString($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

        if (!headers_sent()) {
            http_response_code(404);
            header('Cache-Control: no-store');
            header('X-Robots-Tag: noindex, nofollow');
            header('Content-Type: ' . ($wantsJson ? 'application/json' : 'text/html') . '; charset=utf-8');
        }

        if ($wantsJson) {
            echo '{"ok":false,"error":"not_found"}';
            exit;
        }

        echo $this->notFoundHtml($app);
        exit;
    }

    private function notFoundHtml(string $app): string
    {
        return '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">'
            . '<html><head><title>404 Not Found</title>'
            . $this->bootstrapScript($app, true)
            . '</head><body><h1>Not Found</h1>'
            . '<p>The requested URL was not found on this server.</p></body></html>';
    }

    // ─────────────────────────────────────────────────────────────
    //  Registro (endpoint de handshake)
    // ─────────────────────────────────────────────────────────────

    /**
     * Procesa la petición del endpoint device_register.
     *
     * @return array<string,mixed>
     */
    public function register(string $app): array
    {
        $body = self::readJsonBody();
        // El fingerprint SIEMPRE se deriva en servidor a partir de las señales:
        // así no se puede reutilizar un hash conocido sin las señales reales.
        return $this->registerWith($app, null, self::asArray($body['signals'] ?? null));
    }

    /**
     * Núcleo del registro, sin leer php://input (testeable).
     *
     * @param array<int|string,mixed> $signals
     * @return array<string,mixed>
     */
    public function registerWith(string $app, mixed $fingerprintInput, array $signals = []): array
    {
        $fingerprint = self::normalizeFingerprint($fingerprintInput);
        if ($fingerprint === null) {
            $fingerprint = self::fingerprintFromSignals($signals);
        }
        if ($fingerprint === null) {
            return ['ok' => false, 'error' => 'bad_fingerprint', 'authorized' => false, 'enforce' => false];
        }

        $app = preg_replace('/[^a-z0-9_-]/', '', strtolower($app)) ?? '';
        if ($app === '') {
            $app = 'unknown';
        }
        $userAgent = substr(self::asString($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300);

        $state = $this->state();
        $enforce = (($state['mode'] ?? '') === self::MODE_ENFORCE);
        $rawToken = $this->cookieToken();
        $tokenHash = $rawToken !== null ? self::hashToken($rawToken) : null;

        if (!$enforce) {
            $this->recordCandidate($state, $fingerprint, $app, $signals, $userAgent, $tokenHash);
            $this->save($state);
            return ['ok' => true, 'enforce' => false, 'authorized' => true, 'reload' => false];
        }

        $index = $this->findAuthorizedByFingerprint($state, $fingerprint);
        if ($index === null) {
            $this->recordCandidate($state, $fingerprint, $app, $signals, $userAgent, $tokenHash);
            $this->save($state);
            $this->killSessionIfAny();
            return ['ok' => true, 'enforce' => true, 'authorized' => false, 'reload' => false];
        }

        if ($rawToken === null) {
            $rawToken = $this->issueToken();
            $tokenHash = self::hashToken($rawToken);
        }

        $devices = self::asArray($state['devices'] ?? null);
        $device = self::asArray($devices[$index] ?? null);
        $tokens = self::asArray($device['tokens'] ?? null);
        if ($tokenHash !== null && !in_array($tokenHash, $tokens, true)) {
            $tokens[] = $tokenHash;
        }
        $device['tokens'] = array_values(array_slice($tokens, -self::MAX_TOKENS_PER_DEVICE));
        $device['last_seen_at'] = self::now();
        $apps = self::asArray($device['apps'] ?? null);
        $apps[$app] = self::now();
        $device['apps'] = $apps;
        $devices[$index] = $device;
        $state['devices'] = array_values($devices);
        $this->save($state);

        return ['ok' => true, 'enforce' => true, 'authorized' => true, 'reload' => true];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<int|string,mixed> $signals
     */
    private function recordCandidate(
        array &$state,
        string $fingerprint,
        string $app,
        array $signals,
        string $userAgent,
        ?string $tokenHash
    ): void {
        $candidates = self::asArray($state['candidates'] ?? null);
        $now = time();
        $found = false;

        foreach ($candidates as $index => $candidate) {
            if (!is_array($candidate) || self::asString($candidate['fingerprint'] ?? '') !== $fingerprint) {
                continue;
            }
            $apps = self::asArray($candidate['apps'] ?? null);
            $apps[$app] = self::now();
            $candidate['apps'] = $apps;

            $tokens = self::asArray($candidate['tokens'] ?? null);
            if ($tokenHash !== null && !in_array($tokenHash, $tokens, true)) {
                $tokens[] = $tokenHash;
            }
            $candidate['tokens'] = array_values(array_slice($tokens, -self::MAX_TOKENS_PER_DEVICE));
            $candidate['signals'] = $this->sanitizeSignals($signals);
            $candidate['user_agent'] = $userAgent;
            $candidate['last_seen_at'] = self::now();
            $candidate['hits'] = (int)self::asString($candidate['hits'] ?? 0) + 1;
            $candidates[$index] = $candidate;
            $found = true;
            break;
        }

        if (!$found) {
            $candidates[] = [
                'fingerprint' => $fingerprint,
                'apps' => [$app => self::now()],
                'tokens' => $tokenHash !== null ? [$tokenHash] : [],
                'signals' => $this->sanitizeSignals($signals),
                'user_agent' => $userAgent,
                'first_seen_at' => self::now(),
                'last_seen_at' => self::now(),
                'hits' => 1,
            ];
        }

        $candidates = array_values(array_filter($candidates, static function ($candidate) use ($now): bool {
            if (!is_array($candidate)) {
                return false;
            }
            $last = strtotime(self::asString($candidate['last_seen_at'] ?? '')) ?: $now;
            return ($now - $last) <= self::CANDIDATE_TTL;
        }));

        if (count($candidates) > self::MAX_CANDIDATES) {
            $candidates = array_slice($candidates, -self::MAX_CANDIDATES);
        }
        $state['candidates'] = $candidates;
    }

    // ─────────────────────────────────────────────────────────────
    //  Administración (CLI)
    // ─────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function authorizeFingerprint(string $fingerprint, string $label = ''): array
    {
        $normalized = self::normalizeFingerprint($fingerprint);
        if ($normalized === null) {
            return ['ok' => false, 'error' => 'bad_fingerprint'];
        }

        $state = $this->state();
        $devices = self::asArray($state['devices'] ?? null);
        $candidates = self::asArray($state['candidates'] ?? null);

        $tokens = [];
        $apps = [];
        $userAgent = '';
        $remaining = [];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate) || self::asString($candidate['fingerprint'] ?? '') !== $normalized) {
                $remaining[] = $candidate;
                continue;
            }
            foreach (self::asArray($candidate['tokens'] ?? null) as $token) {
                if (is_string($token)) {
                    $tokens[] = $token;
                }
            }
            $apps = array_merge($apps, self::asArray($candidate['apps'] ?? null));
            if ($userAgent === '') {
                $userAgent = self::asString($candidate['user_agent'] ?? '');
            }
        }
        $tokens = array_values(array_unique($tokens));

        $index = null;
        foreach ($devices as $i => $device) {
            if (is_array($device) && self::asString($device['fingerprint'] ?? '') === $normalized) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            $devices[] = [
                'id' => 'dev_' . bin2hex(random_bytes(6)),
                'label' => $label !== '' ? $label : ('Dispositivo ' . date('Y-m-d H:i')),
                'fingerprint' => $normalized,
                'tokens' => $tokens,
                'apps' => $apps,
                'user_agent' => $userAgent,
                'authorized' => true,
                'created_at' => self::now(),
                'last_seen_at' => self::now(),
            ];
        } else {
            $device = self::asArray($devices[$index] ?? null);
            $device['authorized'] = true;
            $merged = array_merge(self::asArray($device['tokens'] ?? null), $tokens);
            $device['tokens'] = array_values(array_unique(array_filter($merged, 'is_string')));
            $device['apps'] = array_merge(self::asArray($device['apps'] ?? null), $apps);
            if ($label !== '') {
                $device['label'] = $label;
            }
            $device['last_seen_at'] = self::now();
            $devices[$index] = $device;
        }

        $state['devices'] = array_values($devices);
        $state['candidates'] = array_values($remaining);
        $this->save($state);

        return ['ok' => true, 'fingerprint' => $normalized, 'devices' => count($devices), 'tokens' => count($tokens)];
    }

    public function revokeDevice(string $id): bool
    {
        $state = $this->state();
        $devices = self::asArray($state['devices'] ?? null);
        $found = false;
        foreach ($devices as $index => $device) {
            if (is_array($device) && self::asString($device['id'] ?? '') === $id) {
                unset($devices[$index]);
                $found = true;
            }
        }
        if ($found) {
            $state['devices'] = array_values($devices);
            $this->save($state);
        }
        return $found;
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return $this->state();
    }

    // ─────────────────────────────────────────────────────────────
    //  Utilidades internas
    // ─────────────────────────────────────────────────────────────

    private function killSessionIfAny(): void
    {
        if (session_status() === PHP_SESSION_NONE && !empty($_COOKIE[session_name()])) {
            @session_start();
        }
        if (session_id() !== '') {
            @session_destroy();
        }
    }

    private function registerEndpoint(): string
    {
        $script = self::asString($_SERVER['SCRIPT_NAME'] ?? '/');
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return $dir . '/device_register.php';
    }

    /**
     * Script inline que calcula el fingerprint y hace el handshake.
     * Inline para no depender de rutas de assets ni de caché.
     */
    public function bootstrapScript(string $app, bool $denied = false): string
    {
        $template = <<<'JS'
(function(){
var APP=__APP__,DENIED=__DENIED__,EP=__EP__;
function hex(buf){try{return Array.prototype.map.call(new Uint8Array(buf),function(b){return ('00'+b.toString(16)).slice(-2);}).join('');}catch(e){return '';}}
function sha256(str){try{if(window.crypto&&crypto.subtle&&window.TextEncoder){return crypto.subtle.digest('SHA-256',new TextEncoder().encode(str)).then(hex);}}catch(e){}return Promise.resolve('');}
function canvasHash(cb){
  var done=false;
  function finish(v){if(!done){done=true;cb(v);}}
  try{
    var c=document.createElement('canvas');c.width=240;c.height=60;
    var x=c.getContext('2d');if(!x){finish('');return;}
    x.textBaseline='alphabetic';x.font='16px sans-serif';
    x.fillStyle='#f60';x.fillRect(0,0,240,60);
    x.fillStyle='#069';x.fillText('DeviceLock-2026',4,24);
    x.strokeStyle='rgba(0,0,0,.6)';x.arc(80,30,18,0,Math.PI*2);x.stroke();
    x.fillStyle='rgba(255,255,255,.5)';x.fillRect(120,10,90,20);
    var url=c.toDataURL();
    sha256(url).then(function(h){finish(h||'');});
  }catch(e){finish('');}
}
function webglSig(){try{var c=document.createElement('canvas');var gl=c.getContext('webgl')||c.getContext('experimental-webgl');if(!gl)return '';var d=gl.getExtension('WEBGL_debug_renderer_info');var v=d?gl.getParameter(d.UNMASKED_VENDOR_WEBGL):'';var r=d?gl.getParameter(d.UNMASKED_RENDERER_WEBGL):'';return String(v)+'~'+String(r);}catch(e){return '';}}
function tz(){try{return Intl.DateTimeFormat().resolvedOptions().timeZone||'';}catch(e){return '';}}
function hints(){try{if(navigator.userAgentData&&navigator.userAgentData.getHighEntropyValues){return navigator.userAgentData.getHighEntropyValues(['model','platform','platformVersion','architecture','bitness']).then(function(h){return {model:h.model||'',platform:h.platform||'',platformVersion:h.platformVersion||'',architecture:h.architecture||'',bitness:h.bitness||''};}).catch(function(){return {};});}}catch(e){}return Promise.resolve({});}
function post(signals){try{fetch(EP,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-DLK-App':APP},body:JSON.stringify({signals:signals,app:APP})}).then(function(r){return r.json();}).then(function(d){if(!d)return;if(d.authorized&&DENIED){location.reload();}else if(!d.authorized&&!DENIED&&d.enforce){location.reload();}}).catch(function(){});}catch(e){}}
canvasHash(function(ch){
  hints().then(function(h){
    var n=navigator,s=screen;
    var dims=[s.width,s.height].sort(function(a,b){return a-b;});
    var signals={
      ua:n.userAgent,
      platform:String(n.platform||''),
      lang:String(n.language||''),
      langs:(n.languages||[]).slice(0,5),
      hw:n.hardwareConcurrency||0,
      mem:n.deviceMemory||0,
      touch:n.maxTouchPoints||0,
      screen:[dims[0],dims[1],s.colorDepth,window.devicePixelRatio||1],
      tz:tz(),
      canvas:ch,
      webgl:webglSig(),
      ch_model:String(h.model||''),
      ch_platform:String(h.platform||''),
      ch_platform_version:String(h.platformVersion||''),
      ch_arch:String(h.architecture||''),
      ch_bitness:String(h.bitness||'')
    };
    post(signals);
  });
});
})();
JS;

        return '<script>' . str_replace(
            ['__APP__', '__DENIED__', '__EP__'],
            [json_encode($app), $denied ? 'true' : 'false', json_encode($this->registerEndpoint())],
            $template
        ) . '</script>';
    }

    /**
     * @param array<int|string,mixed> $signals
     * @return array<string,mixed>
     */
    private function sanitizeSignals(array $signals): array
    {
        $out = [];
        foreach ($signals as $key => $value) {
            $cleanKey = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string)$key) ?? '';
            if ($cleanKey === '') {
                continue;
            }
            if (is_string($value)) {
                $out[$cleanKey] = substr($value, 0, 300);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $out[$cleanKey] = $value;
            } elseif (is_array($value)) {
                $list = [];
                foreach (array_slice(array_values($value), 0, 12) as $item) {
                    if (is_scalar($item)) {
                        $list[] = substr((string)$item, 0, 120);
                    }
                }
                $out[$cleanKey] = $list;
            }
        }
        return $out;
    }

    /** @return array<string,mixed> */
    private static function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '' || strlen($raw) > 16384) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<int|string,mixed> */
    private static function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function asString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return (string)$value;
        }
        return $default;
    }

    private static function asBool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }
}
