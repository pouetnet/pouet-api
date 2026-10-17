<?php

function outputJSON($object)
{
    $out = json_encode($object, JSON_PRETTY_PRINT);
    header("X-Content-Type-Options: nosniff");
    if (@$_GET["callback"]) {
        $callback = $_GET["callback"];
        if (!is_string($callback) || strlen($callback) > 128 || !preg_match('/^[A-Za-z_$][\w$.]*$/D', $callback)) {
            http_response_code(400);
            header("Content-type: application/json; charset=utf-8");
            echo json_encode(["error" => true, "message" => "invalid callback"], JSON_PRETTY_PRINT);
            return;
        }
        // the leading comment stops the response from being parsed as anything but JS (Rosetta Flash)
        $out = "/**/" . $callback . "(" . $out . ")";
        header("Content-type: application/javascript; charset=utf-8");
    } else {
        header("Content-type: application/json; charset=utf-8");
    }
    echo $out;
}

function outputXML($object)
{
    header("Content-type: text/xml; charset=utf-8");
    echo $out;
}
function output($object)
{
    $format = @$_GET["format"];
    switch ($format) {
        // case "xml":
        //    outputXML( $object );
        default:
            outputJSON($object);
    }
}
