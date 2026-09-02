<?php

namespace App\Enums;

enum WalletSourceType: string
{
    case Xpub = 'xpub';
    case Address = 'address';
}
