<?php

if (!defined("POUET_API")) {
    exit();
}

$prod = $_GET["id"] ? PouetProd::Spawn((int) $_GET["id"]) : null;

$result = new stdClass();
if ($prod) {
    $result->success = true;
    $result->prod = $prod->ToAPI();

    $s = new BM_Query();
    $s->AddField("comments.id as id");
    $s->AddField("comments.comment as comment");
    $s->AddField("comments.rating as rating");
    $s->AddField("comments.addedDate as addedDate");
    $s->attach(["comments" => "who"], ["users as user" => "id"]);
    $s->AddTable("comments");
    $s->AddOrder("comments.addedDate");
    $s->AddWhere("comments.which=" . $prod->id);

    $result->prod["comments"] = $s->perform();
} else {
    $result->error = true;
}
output($result);
