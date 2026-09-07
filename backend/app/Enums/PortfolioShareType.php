<?php

namespace App\Enums;

enum PortfolioShareType: string
{
    case Viewer = 'viewer';
    case Amount = 'amount';
    case Percentage = 'percentage';
}
