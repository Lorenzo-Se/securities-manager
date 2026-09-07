<?php

namespace App\Services\Blockchain\Bitcoin;

use InvalidArgumentException;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Primitives\PointInterface;
use QXCoin\BIP32\BIP32;
use QXCoin\BIP32\PublicChildKey;

class Bip32PublicKeyReader
{
    private const PUBLIC_VERSIONS = [
        '0488b21e' => BitcoinAddressScheme::Legacy,   // xpub
        '049d7cb2' => BitcoinAddressScheme::NestedSegwit, // ypub
        '04b24746' => BitcoinAddressScheme::NativeSegwit, // zpub
    ];

    private const PREFIX_SCHEMES = [
        'xpub' => BitcoinAddressScheme::Legacy,
        'ypub' => BitcoinAddressScheme::NestedSegwit,
        'zpub' => BitcoinAddressScheme::NativeSegwit,
    ];

    public function parse(string $extendedKey): ParsedExtendedPublicKey
    {
        $extendedKey = trim($extendedKey);

        if ($extendedKey === '') {
            throw new InvalidArgumentException('Extended public key is empty.');
        }

        $prefix = strtolower(substr($extendedKey, 0, 4));
        $schemeFromPrefix = self::PREFIX_SCHEMES[$prefix] ?? null;

        if ($schemeFromPrefix === null) {
            throw new InvalidArgumentException('Unsupported extended public key prefix.');
        }

        $payload = Base58Check::decode($extendedKey);
        $hex = bin2hex($payload);

        if (strlen($hex) < 156) {
            throw new InvalidArgumentException('Invalid extended public key length.');
        }

        $versionHex = strtolower(substr($hex, 0, 8));
        $scheme = self::PUBLIC_VERSIONS[$versionHex] ?? $schemeFromPrefix;

        $depth = hexdec(substr($hex, 8, 2));
        $fingerprint = hexdec(substr($hex, 10, 8));
        $childNumber = hexdec(substr($hex, 18, 8));
        $chainCode = hex2bin(substr($hex, 26, 64));
        $keyHex = substr($hex, 90, 66);

        if (! str_starts_with($keyHex, '02') && ! str_starts_with($keyHex, '03')) {
            throw new InvalidArgumentException('Invalid extended public key payload.');
        }

        $ecc = EccFactory::getSecgCurves()->generator256k1();
        $x = gmp_init(substr($keyHex, 2, 64), 16);
        $y = $ecc->getCurve()->recoverYfromX($keyHex[1] === '3', $x);
        $point = $ecc->getCurve()->getPoint($x, $y);

        $publicKey = new PublicChildKey(
            x: $point->getX(),
            y: $point->getY(),
            chainCode: $chainCode,
            version: hexdec($versionHex),
            depth: $depth,
            fingerprint: $fingerprint,
            childNumber: $childNumber,
        );

        return new ParsedExtendedPublicKey($publicKey, $scheme);
    }

    public function deriveChild(PublicChildKey $parent, int $chain, int $index): PublicChildKey
    {
        $bip32 = new BIP32(new BitcoinExtendedVersionResolver($parent->version));

        $chainKey = $bip32->CKDpub($parent, $chain);

        return $bip32->CKDpub($chainKey, $index);
    }

    public function compressedPublicKey(PublicChildKey $key): string
    {
        $ecc = EccFactory::getSecgCurves()->generator256k1();
        $point = $ecc->getCurve()->getPoint($key->x, $key->y);

        return $this->serializeCompressedPoint($point);
    }

    private function serializeCompressedPoint(PointInterface $point): string
    {
        $prefix = gmp_cmp(gmp_mod($point->getY(), 2), 0) === 0 ? "\x02" : "\x03";
        $xHex = str_pad(gmp_strval($point->getX(), 16), 64, '0', STR_PAD_LEFT);

        return $prefix.hex2bin($xHex);
    }
}
