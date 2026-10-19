<?php

if (!defined("POUET_API")) {
    exit();
}

$s = new BM_Query("oneliner");
$s->AddField("oneliner.id");
$s->AddField("oneliner.message");
$s->AddField("oneliner.addedDate");
$s->Attach(["oneliner" => "who"], ["users as user" => "id"]);
$s->AddOrder("oneliner.addedDate DESC");
$s->AddOrder("oneliner.id DESC");
$oneliners = $s->perform();

$result = new stdClass();
if ($oneliners) {
    $result->success = true;
    $result->oneliners = array_values($oneliners);
} else {
    $result->error = true;
}
output($result);
