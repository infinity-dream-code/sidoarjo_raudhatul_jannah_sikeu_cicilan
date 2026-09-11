<?php

namespace App\Support;

use App\Models\CyberKey;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Throwable;

class PersistentLogin
{
    public const COOKIE = 'sidoarjo_jannah';

    public static function minutes(): int
    {
        $lifetime = (int) config('session.lifetime', 5256000);

        return $lifetime > 0 ? $lifetime : 5256000;
    }

    public static function set(?CyberKey $user = null): void
    {
        $user ??= Auth::user();
        if (!$user instanceof CyberKey) {
            return;
        }

        $payload = json_encode([
            'urut' => (int) $user->urut,
            'users' => (string) $user->users,
            'stamp' => self::stamp($user),
        ], JSON_UNESCAPED_UNICODE);

        Cookie::queue(cookie(
            self::COOKIE,
            $payload,
            self::minutes(),
            config('session.path', '/'),
            config('session.domain'),
            self::secure(),
            true,
            false,
            config('session.same_site', 'lax')
        ));
    }

    public static function clear(): void
    {
        Cookie::queue(Cookie::forget(
            self::COOKIE,
            config('session.path', '/'),
            config('session.domain')
        ));
    }

    public static function restore(): bool
    {
        if (Auth::check()) {
            return true;
        }

        $raw = request()->cookie(self::COOKIE);
        if (!is_string($raw) || $raw === '') {
            return false;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return false;
        }

        $urut = (int) ($data['urut'] ?? 0);
        if ($urut < 1) {
            return false;
        }

        try {
            $user = CyberKey::query()->where('urut', $urut)->first();
        } catch (Throwable) {
            return false;
        }

        if (!$user instanceof CyberKey) {
            return false;
        }

        if (!hash_equals(self::stamp($user), (string) ($data['stamp'] ?? ''))) {
            return false;
        }

        if (isset($data['users']) && (string) $data['users'] !== (string) $user->users) {
            return false;
        }

        Auth::login($user, false);
        self::set($user);

        return true;
    }

    public static function isTransient(Throwable $e): bool
    {
        $haystack = strtolower($e::class.' '.$e->getMessage());

        foreach ([
            'deadlock',
            'has gone away',
            'lost connection',
            'lock wait timeout',
            'unable to obtain lock',
            'unable to create lockable file',
            'resource temporarily unavailable',
            'serialization failure',
            'try restarting transaction',
            'no active transaction',
            'sqlstate[40001]',
            'sqlstate[hy000]: general error: 1205',
            'sqlstate[hy000]: general error: 2006',
            'sqlstate[hy000]: general error: 2013',
            'please retry',
        ] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        $previous = $e->getPrevious();

        return $previous instanceof Throwable && self::isTransient($previous);
    }

    private static function stamp(CyberKey $user): string
    {
        return hash_hmac(
            'sha256',
            $user->urut.'|'.$user->users.'|'.$user->password,
            (string) config('app.key')
        );
    }

    private static function secure(): bool
    {
        $configured = config('session.secure');
        if ($configured !== null) {
            return (bool) $configured;
        }

        return (bool) request()?->isSecure();
    }
}
