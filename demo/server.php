<?php

declare(strict_types=1);

// Router for `php -S`: Filament's assets come from build/demo/public, everything
// else goes through the demo app. See demo/app.php.

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

$path = __DIR__.'/../build/demo/public'.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($_SERVER['REQUEST_URI'] !== '/' && is_file($path)) {
    return false;
}

require __DIR__.'/app.php';

// The alerts were found on a fixed morning, so the page has to be read on it.
Carbon::setTestNow(DEMO_NOW_AT);

$app = demo_app();
$kernel = $app->make(Kernel::class);
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
