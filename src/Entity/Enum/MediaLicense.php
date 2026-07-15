<?php

namespace App\Entity\Enum;

enum MediaLicense: string
{
    case AllRightsReserved = 'all_rights_reserved';
    case CcBy = 'cc_by';
    case CcBySa = 'cc_by_sa';
    case CcByNc = 'cc_by_nc';
    case Cc0 = 'cc0';
    case Internal = 'internal';
}
