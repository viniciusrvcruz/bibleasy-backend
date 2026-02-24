<?php

namespace App\Enums;

/**
 * Whether a title should be shown before (start) or after (end) the verse content.
 */
enum VerseTitlePositionEnum: string
{
    case START = 'start';
    case END = 'end';
}
