<?php

if (!defined("POUET_API")) {
    exit();
}

$ids = SQLLib::SelectRows(sprintf_esc("select id,name from prods where releaseDate between '%d-01-01' and '%d-12-31'", $_GET["year"], $_GET["year"]));

$result = new stdClass();
if ($ids) {
    $result->success = true;
    $result->prods = $ids;
} else {
    $result->error = true;
}
output($result);
