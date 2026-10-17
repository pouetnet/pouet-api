<?php

if (!defined("POUET_API")) {
    exit();
}

$result = new stdClass();

$data = unserialize(file_get_contents(POUETAPI_POUET_ROOT_LOCAL . "/cache/pouetbox_stats.cache"));
if ($data) {
    $result->success = true;
    $result->stats = $data;
} else {
    $result->error = true;
}
output($result);
