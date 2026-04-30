<?php

namespace App\Services;

class PasswordService
{
    public static function encrypt(string $plain): string
    {
        return self::fpCrypt($plain, 1);
    }

    public static function decrypt(string $encrypted): string
    {
        return self::fpCrypt($encrypted, 2);
    }

    public static function verify(string $plain, string $encrypted): bool
    {
        return strtoupper(bin2hex(self::encrypt($plain))) === strtoupper(bin2hex($encrypted));
    }

    private static function fpCrypt(string $value, int $mode): string
    {
        $trimmed = trim($value);
        $length = strlen($trimmed);
        $result = ' ';

        for ($index = 0; $index < $length; $index++) {
            $char = substr($trimmed, $index, 1);
            $offset = ($index + 1) * ($length + 2);
            $ascii = ord($char);
            $result .= chr($mode === 1 ? $ascii + $offset : $ascii - $offset);
        }

        $result = trim($result);

        if ($result === '') {
            return '';
        }

        return $mode === 1
            ? mb_convert_encoding($result, 'UTF-8', 'Windows-1252')
            : $result;
    }
}
