<?php

namespace App\DTOs;

enum BatchStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
}
