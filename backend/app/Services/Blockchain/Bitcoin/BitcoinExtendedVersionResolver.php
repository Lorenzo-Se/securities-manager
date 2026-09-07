<?php

namespace App\Services\Blockchain\Bitcoin;

use InvalidArgumentException;
use QXCoin\BIP32\VersionResolverInterface;

class BitcoinExtendedVersionResolver implements VersionResolverInterface
{
    public function __construct(
        private readonly int $publicVersion,
    ) {}

    public function getPublicVersionBytes(): int
    {
        return $this->publicVersion;
    }

    public function getPrivateVersionBytes(): int
    {
        throw new InvalidArgumentException('Private keys are not supported.');
    }

    public function convertVersionBytes(int $bytes): int
    {
        return $bytes;
    }
}
