<?php

/**
 * @file
 * Legacy autoloader for systems lacking spl_autoload_register
 *
 * Must be separate to prevent deprecation warning on PHP 7.2
 */

// function __autoload($class)
// {
//     return HTMLPurifier_Bootstrap::autoload($class);
// }
// if (!function_exists('spl_autoload_register')) {
//     throw new Error('spl_autoload_register() method is not supported');
// }

// vim: et sw=4 sts=4
