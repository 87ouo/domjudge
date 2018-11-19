<?php

use Symfony\Component\HttpFoundation\Request;

require __DIR__.'/../app/autoload.php';
if (PHP_VERSION_ID < 70000) {
    include_once __DIR__.'/../var/bootstrap.php.cache';
}

$kernel = new AppKernel('prod', false);
if (PHP_VERSION_ID < 70000) {
    $kernel->loadClassCache();
}
//$kernel = new AppCache($kernel);

// When using the HttpCache, you need to call the method in your front controller instead of relying on the configuration parameter
//Request::enableHttpMethodParameterOverride();

// Uncomment this if using proxies and you need the real client ip address
// 10.0.0.0/8 should be set to the ip address of your trusted proxies
// Request::setTrustedProxies(['10.0.0.0/8'], Request::HEADER_X_FORWARDED_ALL);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
