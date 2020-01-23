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


if (!function_exists('array_set')) {
    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     *
     * @param  array $array
     * @param  string $key
     * @param  mixed $value
     * @return array
     */
    function array_set(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = array();
            }

            $array =& $array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }
}

if (!function_exists('dd')) {
    /**
     * Dump the passed variables and end the script.
     *
     * @param  mixed
     * @return void
     */
    function dd()
    {
        array_map(function ($x) {
            var_dump($x);
        }, func_get_args());

        die(1);
    }

}
