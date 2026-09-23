<?php
declare(strict_types=1);

namespace Exodo;

final class Csrf
{
    public static function token(): string
    {
        Auth::startSession();
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }
        $expected = self::token();
        return hash_equals($expected, $token);
    }
}

