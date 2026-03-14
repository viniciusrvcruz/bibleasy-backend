<?php

namespace App\Enums;

/**
 * Whether a title should be shown before (start), after (end), or at a custom
 * inline position within the verse content (custom).
 */
enum VerseTitlePositionEnum: string
{
    case START = 'start';
    case END = 'end';
    case CUSTOM = 'custom';
}
