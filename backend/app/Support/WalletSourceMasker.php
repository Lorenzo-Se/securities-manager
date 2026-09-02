<?php

namespace App\Support;

class WalletSourceMasker
{
    public static function mask(?string $walletSource): ?string
    {
        if ($walletSource === null || $walletSource === '') {
            return null;
        }

        if (strlen($walletSource) <= 10) {
            return str_repeat('*', strlen($walletSource));
        }

        return substr($walletSource, 0, 6).'...'.substr($walletSource, -4);
    }
}
