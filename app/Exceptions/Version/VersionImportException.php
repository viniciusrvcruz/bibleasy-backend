<?php

namespace App\Exceptions\Version;

use App\Exceptions\CustomException;

class VersionImportException extends CustomException
{
    protected int $statusCode = 422;
}
