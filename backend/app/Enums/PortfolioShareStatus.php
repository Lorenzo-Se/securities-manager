<?php

namespace App\Enums;

enum PortfolioShareStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Revoked = 'revoked';
}
