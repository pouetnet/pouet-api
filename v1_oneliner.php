<?php

if (!defined("POUET_API")) {
    exit();
}

$limit = (int) @$_GET["limit"];
if ($limit < 1) {
    $limit = 50;
}
if ($limit > 200) {
    $limit = 200;
}

$s = new BM_Query("oneliner");
$s->AddField("oneliner.id");
$s->AddField("oneliner.message");
$s->AddField("oneliner.addedDate");
$s->Attach(["oneliner" => "who"], ["users as user" => "id"]);
$s->AddOrder("oneliner.id DESC");
$s->SetLimit($limit);
$oneliners = $s->perform();

$result = new stdClass();
if ($oneliners) {
    $result->success = true;
    $result->oneliners = array_values($oneliners);
} else {
    $result->error = true;
}
output($result);
