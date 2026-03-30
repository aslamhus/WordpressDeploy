<?php



if (php_sapi_name() != 'cli') {
    echo "yas-wpd can only be called from command line";
    exit;
}

$pathToAutoload = getcwd() . '/vendor/autoload.php';

if (!file_exists($pathToAutoload)) {
    echo "Vendor directory not found in the root directory. Please run the installer from the root directory of your project";
    exit;
}
require_once $pathToAutoload;

// load .yaswpd.json.
// defaults to working directory file, but for testing purposes, we can add a path to the config 
// by exporting TEST_YAS_WPD before running ./vendor/bin/phpunit

$pathToSettings = isset($_SERVER['TEST_YAS_WPD']) ? $_SERVER['TEST_YAS_WPD'] : getcwd() . '/.yaswpd.json';
if (!file_exists($pathToSettings)) {
    echo ".yaswpd.json not found in working directory";
    exit;
}


$_SERVER['YAS_WPD'] = json_decode(file_get_contents($pathToSettings), true);
