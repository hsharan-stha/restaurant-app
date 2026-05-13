<?php

namespace App\Enums;

enum PrintLogStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
