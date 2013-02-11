<?php
/**
 * Bootstrap file 
 *
 * @package Mince
 */

$paths = array(
    realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'lib',
    get_include_path(),
);
set_include_path(implode(PATH_SEPARATOR, $paths));

require_once 'vendor/autoload.php';

require_once 'Qi/Spyc.php';
require_once 'Mince.php';
