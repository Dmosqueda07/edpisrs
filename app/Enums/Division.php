<?php

namespace App\Enums;

enum Division: string
{
    case ADMIN = 'ADMIN';
    case PVSD = 'PVSD';
    case EDP = 'EDP';
    case TMD = 'TMD';
    case ARMD = 'ARMD';
    case PAD = 'PAD';
    case CA = 'CA';
    case AcaAdmin = 'ACA_Admin';
    case AcaOp = 'ACA_OP';
}
