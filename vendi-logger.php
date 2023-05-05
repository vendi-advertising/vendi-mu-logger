<?php

use Vendi\Logger\VendiLogger;

if (PHP_VERSION_ID < 80000) {
    return;
}

require_once __DIR__.'/src/VendiLogger.php';

VendiLogger::boot(__DIR__);