<?php

if (!defined("POUET_API")) {
    exit();
}

require_once(POUETAPI_POUET_ROOT_LOCAL . "include_pouet_index/index_bootstrap.inc.php");

$box = new PouetBoxIndexLatestAdded();
$box->Load(true);

$result = new stdClass();
if ($box->data) {
    $result->success = true;
    $result->prods = [];
    PouetCollectPlatforms($box->data);
    PouetCollectAwards($box->data);
    foreach ($box->data as $prod) {
        $result->prods[] = $prod->ToAPI();
    }
} else {
    $result->error = true;
}
output($result);
