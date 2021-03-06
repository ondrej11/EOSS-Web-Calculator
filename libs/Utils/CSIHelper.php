<?php

namespace Utils;
use Debug\Linda;

/**
 * Helper class for CSI generating
 * Class CSIHelper
 * @package Utils
 */
class CSIHelper
{

    /**
     * Generates a xyzEOSSGenCSI.php file.
     * @param string $file
     * @param string $name
     */
    public static function genCSI($file, $name) {
        $genCSI=fopen(DIR_TEMP . "data/" . $name . "GenCSI.php", "w+") or die("Check out your permissions on file libs/data/!");
        fwrite($genCSI, $file);
        fclose($genCSI);
        $eossPath = DIR_LIBS . 'EOSS/EOSS.php';
        $eossFile = fopen($eossPath, 'rw');
        $data =fread($eossFile, filesize($eossPath));
        $lines = explode(PHP_EOL, $data);
        for($i = 0; $i < count($lines) - 1; $i++) {
            if(strpos($lines[$i], 'Variable CSI - Client side interface') !== FALSE && strpos($lines[$i + 1] , $name . "GenCSI") === FALSE) {
                $lines[$i + 1] .= '|\\' . $name . "GenCSI";
                file_put_contents($eossPath, implode(PHP_EOL, $lines));
                fclose($eossFile);
                return;
            }
        }
        fclose($eossFile);

    }

    /**
     * Generates the file for DOM HTML element.
     * @param string $name
     * @param string $file
     */
    public static function genElement($name,$file) {
        $genel=fopen(DIR_TEMP . "data/genElements/".$name.".php", "w+") or die("Check out your permissions on file libs/data/!");
        fwrite($genel, $file);
        fclose($genel);
    }


}