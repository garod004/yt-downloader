<?php
if (!defined('APP_ROOT_DIR')) {
    define('APP_ROOT_DIR', dirname(__DIR__));
    set_include_path(APP_ROOT_DIR . PATH_SEPARATOR . get_include_path());
    chdir(APP_ROOT_DIR);
}
