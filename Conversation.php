<?php

declare(strict_types=1);

namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;

final class Conversation
{
    private const COOKIE = 'w27studio_session';
    private string $instance;

    public function __construct(string $instance='')
    {
        $this->instance=$instance!=='primary' && preg_match('/^[a-z0-9-]{1,32}$/D',$instance) ? $instance : '';
    }

    public function ensureSession(): void
    {
        if (headers_sent() || $this->sessionId()!=='') { return; }
        try { $id = bin2hex(random_bytes(16)); }
        catch (\Throwable) { $id = wp_generate_password(32, false, false); }
        setcookie(self::COOKIE, $id, [
            'expires'=>time()+DAY_IN_SECONDS,
            'path'=>COOKIEPATH ?: '/',
            'domain'=>COOKIE_DOMAIN ?: '',
            'secure'=>is_ssl(),
            'httponly'=>true,
            'samesite'=>'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $id;
    }

    public function sessionId(): string
    {
        $raw = isset($_COOKIE[self::COOKIE]) && is_string($_COOKIE[self::COOKIE]) ? wp_unslash($_COOKIE[self::COOKIE]) : '';
        return preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $raw) ? $raw : '';
    }

    public function sessionHash(): string
    {
        $id = $this->sessionId();
        return $id === '' ? '' : hash_hmac('sha256', $id.($this->instance!=='' ? ':'.$this->instance : ''), wp_salt('auth'));
    }

     
    public function history(): array
    {
        $o = Settings::get();
        if (empty($o['history_enabled'])) { return []; }
        $hash = $this->sessionHash();
        if ($hash === '') { return []; }
        $items = get_transient('w27studio_hist_v2_' . substr($hash,0,32));
        if (!is_array($items)) { return []; }
        $out=[];
        foreach ($items as $item) {
            if (!is_array($item)) { continue; }
            $role = (string)($item['role'] ?? ''); $content = trim((string)($item['content'] ?? ''));
            if (in_array($role,['user','assistant'],true) && $content !== '') { $out[]=['role'=>$role,'content'=>$content]; }
        }
        return array_slice($out, -2 * (int)$o['history_turns']);
    }

    public function clear(): void
    {
        $hash=$this->sessionHash();
        if ($hash!=='') { delete_transient('w27studio_hist_v2_'.substr($hash,0,32)); }
    }

    public function append(string $query, string $answer): void
    {
        $o = Settings::get();
        if (empty($o['history_enabled'])) { return; }
        $hash = $this->sessionHash();
        if ($hash === '') { return; }
        $key = 'w27studio_hist_v2_' . substr($hash,0,32);
        $items = get_transient($key); $items = is_array($items) ? $items : [];
        $items[]=['role'=>'user','content'=>mb_substr($query,0,700)];
        $items[]=['role'=>'assistant','content'=>mb_substr($answer,0,1800)];
        $items = array_slice($items, -2 * (int)$o['history_turns']);
        set_transient($key, $items, (int)$o['history_ttl_minutes'] * MINUTE_IN_SECONDS);
    }
}
