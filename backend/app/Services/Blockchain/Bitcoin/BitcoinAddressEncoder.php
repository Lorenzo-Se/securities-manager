<?php

namespace App\Services\Blockchain\Bitcoin;

use BitWasp\Bech32\Exception\Bech32Exception;
use QXCoin\BIP32\PublicChildKey;

use function BitWasp\Bech32\encodeSegwit;

class BitcoinAddressEncoder
{
    public function encode(PublicChildKey $key, BitcoinAddressScheme $scheme, Bip32PublicKeyReader $reader): string
    {
        $publicKey = $reader->compressedPublicKey($key);
        $pubKeyHash = hash('ripemd160', hash('sha256', $publicKey, true), true);

        return match ($scheme) {
            BitcoinAddressScheme::Legacy => $this->base58CheckEncode($pubKeyHash, "\x00"),
            BitcoinAddressScheme::NestedSegwit => $this->encodeNestedSegwit($pubKeyHash),
            BitcoinAddressScheme::NativeSegwit => $this->encodeNativeSegwit($pubKeyHash),
        };
    }

    private function encodeNestedSegwit(string $pubKeyHash): string
    {
        $redeemScript = "\x00\x14".$pubKeyHash;
        $scriptHash = hash('ripemd160', hash('sha256', $redeemScript, true), true);

        return $this->base58CheckEncode($scriptHash, "\x05");
    }

    private function encodeNativeSegwit(string $pubKeyHash): string
    {
        try {
            return encodeSegwit('bc', 0, $pubKeyHash);
        } catch (Bech32Exception $exception) {
            throw new \RuntimeException('Bech32-Kodierung fehlgeschlagen: '.$exception->getMessage(), 0, $exception);
        }
    }

    private function base58CheckEncode(string $payload, string $versionByte): string
    {
        return Base58Check::encode($versionByte.$payload);
    }
}
