<?php

namespace App\Services\Blockchain\Bitcoin;

use InvalidArgumentException;

class Base58Check
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public static function decode(string $input): string
    {
        if ($input === '') {
            throw new InvalidArgumentException('Base58 input is empty.');
        }

        $num = gmp_init(0, 10);

        for ($i = 0, $length = strlen($input); $i < $length; $i++) {
            $position = strpos(self::ALPHABET, $input[$i]);

            if ($position === false) {
                throw new InvalidArgumentException('Invalid Base58 character.');
            }

            $num = gmp_add(gmp_mul($num, 58), $position);
        }

        $hex = gmp_strval($num, 16);

        if ($hex === '0') {
            $hex = '';
        } elseif (strlen($hex) % 2 === 1) {
            $hex = '0'.$hex;
        }

        $bytes = $hex === '' ? '' : hex2bin($hex);

        $leadingZeros = 0;

        for ($i = 0, $length = strlen($input); $i < $length && $input[$i] === '1'; $i++) {
            $leadingZeros++;
        }

        if ($leadingZeros > 0) {
            $bytes = str_repeat("\0", $leadingZeros).$bytes;
        }

        if (strlen($bytes) < 5) {
            throw new InvalidArgumentException('Invalid Base58Check payload.');
        }

        $payload = substr($bytes, 0, -4);
        $checksum = substr($bytes, -4);
        $expected = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        if ($checksum !== $expected) {
            throw new InvalidArgumentException('Invalid Base58Check checksum.');
        }

        return $payload;
    }

    public static function encode(string $payload): string
    {
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
        $bytes = $payload.$checksum;

        $leadingZeros = 0;

        for ($i = 0, $length = strlen($bytes); $i < $length && $bytes[$i] === "\0"; $i++) {
            $leadingZeros++;
        }

        $num = gmp_init(bin2hex(substr($bytes, $leadingZeros)) ?: '0', 16);
        $encoded = '';

        while (gmp_cmp($num, 0) > 0) {
            [$num, $remainder] = gmp_div_qr($num, 58);
            $encoded = self::ALPHABET[gmp_intval($remainder)].$encoded;
        }

        return str_repeat('1', $leadingZeros).$encoded;
    }
}
