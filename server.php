<?php
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->overload();

/**
 * Get Environment variable
 *
 * @param string $key
 * @param null   $default
 * @return string/null
 */
function env($key = '', $default = null)
{
    return $_ENV[$key] != '' ? $_ENV[$key] : $default;
}

if (env('APP_DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

