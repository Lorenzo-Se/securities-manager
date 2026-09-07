<?php

namespace App\Services\Blockchain\Bitcoin;

enum BitcoinAddressScheme: string
{
    case Legacy = 'legacy';
    case NestedSegwit = 'nested_segwit';
    case NativeSegwit = 'native_segwit';
}
