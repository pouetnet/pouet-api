<?php

if (!defined("POUET_API")) {
    exit();
}

$limit = (int) @$_GET["limit"];
if ($limit < 1) {
    $limit = 100;
}
if ($limit > 500) {
    $limit = 500;
}

$offset = (int) @$_GET["offset"];
if ($offset < 0) {
    $offset = 0;
}

$s = new BM_Query("parties");
$letter = @$_GET["letter"];
if (is_string($letter) && $letter !== "") {
    $letter = substr($letter, 0, 1);
    if (preg_match("/^[a-z]$/i", $letter)) {
        $s->AddWhere(sprintf_esc("parties.name LIKE '%s%%'", _like($letter)));
    } else {
        $s->AddWhere("parties.name regexp '^[^a-z]'");
    }
}
$s->AddOrder("parties.name ASC");
$s->AddOrder("parties.id ASC");
$s->SetLimit($limit, $offset);
$parties = $s->performWithCalcRows($total);

$result = new stdClass();
if ($parties) {
    $result->success = true;
    $result->total = (int) $total;
    $result->limit = $limit;
    $result->offset = $offset;
    $result->results = array_values($parties);
} else {
    $result->error = true;
}
output($result);
