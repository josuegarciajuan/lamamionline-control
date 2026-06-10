# Plan: Fix Cancel Mechanism for Bot Pause ✅ EJECUTADO — 2026-06-06

## Bug Summary

When a user pauses the bot for a conversation, two bugs occur:

1. **In-flight message still sent**: The cancel file check in `sendMessages()` happens only once at the top. If the user pauses during `sendHumanized()` typing simulation (up to 45s of `usleep()`), the message is sent anyway.

2. **Ghost reply recorded**: When `sendMessages()` returns early due to cancellation, `$ctx['_send_ok']` is still `true` (set before the call), so the AI-generated reply is saved to session memory as if sent — polluting the chat history.

## Files to modify

- `bot-casa/src/Bot.php` — all changes

---

## Edit 1: Add `getPauseGate()` helper method

Add this method to the `Bot` class (before `sendMessages()`, around line 758):

```php
private function getPauseGate(): ?\WasapBot\Pipeline\PauseGate
{
    foreach ($this->inputGates as $gate) {
        if ($gate instanceof \WasapBot\Pipeline\PauseGate) {
            return $gate;
        }
    }
    return null;
}
```

## Edit 2: Replace cancel check in `sendMessages()` (lines 764–781)

Replace the existing inline cancel check with one that uses `PauseGate` methods and signals cancellation:

**OLD (lines 764-781):**
```php
        // ── Cancel check: if user paused this thread mid-generation, abort send ──
        $threadId = (string) ($ctx['thread_id'] ?? $ctx['__thread_id'] ?? '');
        $cancelDir = (string) $this->config->get('paths.cancel_dir', '');
        if ($cancelDir === '') {
            $rootDir = defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : dirname(__DIR__);
            $cancelDir = $rootDir . '/data/cancel';
        }
        if ($threadId !== '') {
            $cancelHash = hash('sha256', $threadId);
            $cancelFile = $cancelDir . '/' . $cancelHash . '.cancel';
            if (file_exists($cancelFile)) {
                $this->logger->info('Bot::sendMessages — response cancelled by user pause', [
                    'thread_id' => $threadId,
                ]);
                @unlink($cancelFile);
                return;
            }
        }
```

**NEW:**
```php
        // ── Cancel check: if user paused this thread mid-generation, abort send ──
        $threadId = (string) ($ctx['thread_id'] ?? $ctx['__thread_id'] ?? '');
        $pauseGate = $this->getPauseGate();
        if ($threadId !== '' && $pauseGate !== null && $pauseGate->hasCancelRequest($threadId)) {
            $this->logger->info('Bot::sendMessages — response cancelled by user pause', [
                'thread_id' => $threadId,
            ]);
            $pauseGate->clearCancelRequest($threadId);
            $ctx['_cancelled'] = true;
            $ctx['_send_ok']   = false;
            return;
        }
```

## Edit 3: Add intra-loop cancel check in `sendMessages()` (inside foreach)

In the `foreach ($messages as $msg)` loop (around line 863), add a cancel check before each message is sent. This catches the case where user pauses during the typing simulation of the first message.

**INSERT before the existing `foreach` body** (after `if ($msgStr === '') { continue; }`):

```php
            // ── Intra-loop cancel check: user may have paused during typing simulation ──
            if ($threadId !== '' && $pauseGate !== null && $pauseGate->hasCancelRequest($threadId)) {
                $this->logger->info('Bot::sendMessages — response cancelled mid-send by user pause', [
                    'thread_id' => $threadId,
                ]);
                $pauseGate->clearCancelRequest($threadId);
                $ctx['_cancelled'] = true;
                $ctx['_send_ok']   = false;
                break;
            }
```

## Edit 4: Fix ghost reply — main pipeline path (line 336)

**OLD (line 336):**
```php
            if (!empty($ctx['_send_ok'])) {
```

**NEW:**
```php
            if (!empty($ctx['_send_ok']) && empty($ctx['_cancelled'])) {
```

## Edit 5: Fix ghost reply — audio auto-reply shortcut (line 161)

**OLD (line 161):**
```php
                if (!empty($ctx['_send_ok'])) {
```

**NEW:**
```php
                if (!empty($ctx['_send_ok']) && empty($ctx['_cancelled'])) {
```

## Edit 6: Fix ghost reply — first-contact greeting shortcut (line 199)

**OLD (line 199):**
```php
                if (!empty($ctx['_send_ok'])) {
```

**NEW:**
```php
                if (!empty($ctx['_send_ok']) && empty($ctx['_cancelled'])) {
```

## Edit 7: Update JS cache version (panel.php and client.php)

Update `chat.js?v=20260606_1` → `chat.js?v=20260606_2` in:
- `bot-casa/public/panel.php` (line 3836)
- `bot-casa/public/client.php` (line 1220)

---

## Verification

```bash
php -l bot-casa/src/Bot.php
php -l bot-casa/public/panel.php
php -l bot-casa/public/client.php
```

---

## What still CANNOT be fixed

If the first message of a response is already inside `sendHumanized()` when the user pauses, that message will still be sent because PHP's `usleep()` is uninterruptible. However:
- ✅ Ghost reply bug is fixed (cancelled messages won't pollute chat history)
- ✅ Multi-message responses: second message onward will be cancelled
- ✅ Recursive `sendMessages()` calls already re-check cancel (good)
- ✅ The `_cancelled` flag protects all 3 session memory paths (main, audio, greeting)
