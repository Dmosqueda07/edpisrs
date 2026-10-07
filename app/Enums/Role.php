<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdministrator = 'Super Administrator';
    case Administrator = 'Administrator';
    case Receiving = 'Receiving';
    case RecordsChief = 'Records Chief';
    case CityAssessor = 'City Assessor';
    case AcaAdmin = 'ACA Admin';
    case DivisionSecretary = 'Division Secretary';
    case DivisionHead = 'Division Head';
    case Staff = 'Staff';
    case AcaOp = 'ACA OP';
}
