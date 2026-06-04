<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Polyfill: provide a fallback for mb_strimwidth when ext-mbstring is not enabled.
if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth($string, $start, $width, $trimmarker = "") {
        // Best-effort approximate fallback using byte-safe functions when mbstring isn't available.
        $start = (int) $start;
        $width = (int) $width;
        if ($start !== 0) {
            $string = substr($string, $start);
        }
        $substr = substr($string, 0, $width);
        if (strlen($string) > $width) {
            $markerLen = strlen($trimmarker);
            if ($markerLen >= $width) {
                return substr($trimmarker, 0, $width);
            }
            return substr($substr, 0, $width - $markerLen) . $trimmarker;
        }
        return $substr;
    }
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
