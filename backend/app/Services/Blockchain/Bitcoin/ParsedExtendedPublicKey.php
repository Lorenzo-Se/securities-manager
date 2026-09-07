<?php

namespace App\Services\Blockchain\Bitcoin;

use QXCoin\BIP32\PublicChildKey;

readonly class ParsedExtendedPublicKey
{
    public function __construct(
        public PublicChildKey $key,
        public BitcoinAddressScheme $scheme,
    ) {}
}
