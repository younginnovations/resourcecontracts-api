<?php
/**
 * Get View content
 *
 * @param $page
 *
 * @return string
 * @throws Exception
 */
function view($page)
{
    $view_path = dirname(__DIR__).'/'.trim(config('view_path'), '/');
    $file      = sprintf('%s/%s.php', $view_path, $page);

    if (!file_exists($file)) {
        throw new Exception($page.' not found.');
    }
    $content = require $file;

    return $content;
}

/**
 * Get config value
 *
 * @param string $key
 * @param null   $default
 *
 * @return string/array/null
 */
function config($key = '', $default = null)
{
    $config = require 'config.php';

    if (array_key_exists($key, $config)) {
        return $config[$key];
    }

    return $default;
}


