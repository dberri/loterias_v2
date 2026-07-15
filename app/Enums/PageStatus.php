<?php

namespace App\Enums;

enum PageStatus: string
{
    case Generating = 'generating';
    case Generated = 'generated';
    case Published = 'published';
    case Failed = 'failed';
}
