<?php

namespace Utils;


use Application\Config;
use Debug\Linda;
use EOSS\EOSS;
use Forms\Form;
use Http\Response;

/**
 * Generates ajax communication for EOSS to work.
 * @static Class JavascriptGenerator
 * @package Utils
 */
class JavascriptGenerator
{

    /**
     * Generates the core functionality and stores it into js file
     * @param EOSS $eoss
     */
    public static function generateJavascript(EOSS $eoss) {
        $js="";
        foreach($eoss->csi as $key=>$attr) {
            if($attr != NULL && $key != 'params' && $key != 'intervals') { // Filter params and intervals from CSI
                $js .= self::checkForEvents($attr, get_class($eoss));
            }
        }
        $js .= self::generateIntervals($eoss->csi->intervals, get_class($eoss));
        $js .= self::generateForms($eoss->getForms(), get_class($eoss));
        $js .= self::generateFlashes($eoss);
        $genjs=fopen(DIR_TEMP . "data/genJs/".get_class($eoss).".js", "w") or die("Check out your permissions on file libs/data/!");
        fwrite($genjs, $js);
        fclose($genjs);
    }


    /**
     * Checks for all of the events and generates the javascript to handle them.
     * @param array $attr
     * @param string $class
     * @return string
     */
    public static function checkForEvents($attr,$class) {
        $listOfEvents=json_decode(file_get_contents(DIR_LIBS."EOSS/eventList.json"));
        $js="";
        foreach ($listOfEvents as $key=>$prop) {
            $e=false;

            $param = "";
            if(strpos($prop,":")!=false) {
                $e=true;
                $s=explode(":",$prop);
                $prop=$s[0];
                $param=$s[1];
            }

            $condition = NULL;
            if(strpos($prop, "-")) {
                $s=explode("-", $prop);
                $prop = $s[0];
                $condition = $s[1];
            }
            if($attr && property_exists($attr,$key) && count($attr->$key) > 0 && (property_exists($attr, "type") && $attr->type != "group")) {
                // Generate single element events.
                $js.="\n$( '#".$attr->id."' ).on('".$prop."',function (";
                $js.="event";
                $js.=") {\n";
                $js.= $attr->type == "a" ? "event.preventDefault();\n" : "";
                $js.= $condition ? "if(" . $condition . ")\n\t" : "";
                $js.="$.post('" . URL_LIBS . "request.php',{'eoss':'".$class."','id':'".$attr->id."','event':'".$key."','values':createJSON()";
                $e ? $js.=",'param': event.".$param.", curValue:$(this).val()+String.fromCharCode(event.keyCode)" : $js.="";
                $js.="}, function (data) {
        " . (Config::getParam("enviroment") == "debug" ? "console.log(data);" : "") . "
        eval(data);
        ".$attr->id.$key."(data);
    });
});";
            } else if($attr && property_exists($attr,$key) && count($attr->$key) > 0 && (property_exists($attr, "type") && $attr->type == "group")) {
                // Generate group:
                $js.="\n\n$( '[data-group=\"" . $attr->id . "\"]' ).on('".$prop."',function (";
                $js.="event";
                $js.=") {\nvar $" . "self = $(this);\n";
                $js.= $attr->type == "a" ? "event.preventDefault();\n" : "";
                $js.="var data = {'eoss':'{$class}', 'id':'{$attr->id}', 'event':'{$key}','values':createJSON()";
                $e ? $js .= ",'param': event.{$param}, curValue:$(this).val()+String.fromCharCode(event.keyCode)" : $js.="";
                $js.="};\n";
                $js.="if(typeof $(this).attr(\"id\") == \"undefined\" || $(this).attr(\"id\") == \"\") {\n";
                $js .= "$(this).attr(\"id\", randomString(10));\n";
                $js .= "data.element_id = $(this).attr(\"id\"); \n";
                $js .= "data.anonymous = getAllAttributes($(this));\n";
                $js.="\n} else {\n data.element_id = $(this).attr('id'); \n}\n";
                $js.= $condition ? "if(" . $condition . ")\n\t" : "";
                $js.="$.post('" . URL_LIBS . "request.php', data, function (data) {
        " . (Config::getParam("enviroment") == "debug" ? "console.log(data);" : "") . "
        eval(data);
        ".$attr->id.$key."(data);
    });
});";
            }

        }
        return $js;
    }


    /**
     * @param array $intervals
     * @param string $class
     * @return string
     */
    public static function generateIntervals($intervals, $class) {
        $js = "\n\n";
        foreach($intervals as $key => $value) {
            $js .= "setInterval(function() {\n";
            $js.="$.post('" . URL_LIBS . "request.php',{'eoss':'".$class."','event':'".$key."','values':createJSON()";
            $js.="}, function (data) {
        " . (Config::getParam("enviroment") == "debug" ? "console.log(data);" : "") . "
        eval(data);
        ".$key."Interval(data);
    });
}, " . $value . ");";
        }
        return $js;
    }

    public static function generateForms($forms, $class) {
        $js = "\n\n";
        /** @var Form $form */
        foreach($forms as $form) {
            $js .= "$( \"#{$form->getId()}\" ).on(\"submit\", function(event) {\n";
            $js .= "event.preventDefault();\n";
            $js .= "var formData = new FormData($(this)[0]);\n";
            $js .= "formData.append(\"eoss\", \"{$class}\");\n";
            $js .= "formData.append(\"form\", \"{$form->getName()}\");\n";
            $js .= "formData.append(\"values\", createJSON());\n";
            $js .= " $.ajax({
        url: $(this).attr('action'),
        type: \"POST\",
        data: formData,
        success: function(data) {
            " . (Config::getParam("enviroment") == "debug" ? "console.log(data);" : "") . "
            eval(data);
            {$form->getName()}Form(data);
        },
        processData: false,
        contentType: false
    }); ";

            $js.="return false";
            $js.="});";
        }
        return $js;

    }

    /**
     * Generates the javascript for flash messages.
     * @param EOSS $eoss
     * @return string
     */
    public static function generateFlashes(EOSS $eoss) {
        $js = "";
        if(Config::getParam("showFlashFunction") && $eoss->getCountOfFlashMessages() > 0) {
            $js .= "if(typeof " . Config::getParam("showFlashFunction") . " == 'function') {\n";
            while($flash = $eoss->popFlashMessage()) {
                $js .= Config::getParam("showFlashFunction") . "(\"{$flash["message"]}\", \"{$flash["class"]}\");\n";
            }
            $js .= "}\n";
        }
        return $js;
    }

    /**
     * Writes a response into genFunctions.js file.
     * @param EOSS $eoss
     * @param string $fname
     * @param array $changed
     * @param null|\EOSS\AnonymousSender $anonymousSender
     */
    public static function writeJsResponse(EOSS $eoss, $fname, $changed = array(), $anonymousSender = NULL) {
        $listOfAttr=json_decode(file_get_contents(DIR_LIBS."EOSS/attributeList.json"));
        $js="function ".$fname."() {\n";
        if(!isset($eoss->redirect)) {
            foreach ($changed as $element) {
                if(is_array($element)) continue;
                foreach($listOfAttr as $key=>$attr) {
                    if ($element && property_exists($element, $key)) {

                        if($key != "html" && $key != "value") {
                            $js .= "$( '#" . $element->id . "' ).attr(\"" . str_replace("_", "-", $key) . "\", '";
                        } else if($key == "html"){
                            $js .= "$( '#" . $element->id . "' ).html('";
                        } else if($key == "value") {
                            $js .= "$( '#" . $element->id . "' ).val('";
                        }
                        $js .= preg_replace("/\r|\n/", "", $element->$key);
                        $js .= "');\n";
                    }

                }
            }
            if($anonymousSender) {
                foreach($listOfAttr as $key=>$attr) {
                    if (key_exists($key, $anonymousSender->toArray())) {
                        if($key != "html" && $key != "value") {
                            $js .= "$( '#" . $anonymousSender->id . "' ).attr(\"" . str_replace("_", "-", $key) . "\", '";
                        } else if($key == "html"){
                            $js .= "$( '#" . $anonymousSender->id . "' ).html('";
                        } else if($key == "value") {
                            $js .= "$( '#" . $anonymousSender->id . "' ).val('";
                        }
                        $js .= preg_replace("/\r|\n/", "", $anonymousSender->$key);
                        $js .= "');\n";
                    }
                }
                $js .= "$( '#" . $anonymousSender->id . "' ).attr('id', \"\");\n";
            }
            $js .= self::generateFlashes($eoss);
        } else {
            $js.="location.reload();";
        }
        $js.="}";
        /*$genjs=fopen(DIR_TEMP . "data/genJs/genFunctions.js", "w") or die("Check out your permissions on file libs/data/!");
        fwrite($genjs, $js);
        fclose($genjs);*/
        Response::getInstance()->append($js, FALSE);
    }



}