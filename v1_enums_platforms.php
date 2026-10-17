<?php

if (!defined("POUET_API")) {
    exit();
}

$result = new stdClass();

global $PLATFORMS;
$data = $PLATFORMS;
if ($data) {
    $result->success = true;
    $result->platforms = $data;
} else {
    $result->error = true;
}
output($result);
