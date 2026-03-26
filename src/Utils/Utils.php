<?php

namespace Yashus\WPD\Utils;

use DateTime;
use DateTimeZone;
use Yashus\WPD\Types\YASWPD\DockerSettings;


class Utils
{
    public static function getTodayFormatted($format = 'Y-m-d'): string
    {
        $date = new DateTime('now', new DateTimeZone($_SERVER['YAS_WPD']['timezone'] ?? 'America/Vancouver'));
        return $date->format($format);
    }

    public static function getLocalWPDBImportExportPath(DockerSettings $docker)
    {
        $path = $_SERVER['YAS_WPD']['local']['root'];
        // if using docker, we need to import from the container tmp
        if ($docker->isUsingDocker()) {
            $path = $docker->getTmpDirContainer();
        }
        return  $path;
    }
}
