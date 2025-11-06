<?php

namespace App\Enums;

enum FileSystem: string
{
    case CLOUD = 's3';
    case LOCAL = 'local';
}
