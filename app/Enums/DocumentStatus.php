<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case DRAFT = 'Draft';
    case FOR_REVIEW = 'For Review';
    case FOR_REVISION = 'For Revision';
    case APPROVED = 'Approved';
}
