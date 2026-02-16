<?php

namespace App\Enums;

/**
 * Type of title within a verse (section headers vs cross-references).
 */
enum VerseTitleTypeEnum: string
{
    case SECTION = 'section';
    case REFERENCE = 'reference';
}
