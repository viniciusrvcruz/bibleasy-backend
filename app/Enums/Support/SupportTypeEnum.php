<?php

namespace App\Enums\Support;

enum SupportTypeEnum: string
{
    case BUG = 'bug';
    case FEATURE = 'feature';
    case QUESTION = 'question';
    case OTHER = 'other';
}
