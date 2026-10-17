<?php

if (!defined("POUET_API")) {
    exit();
}

$result = new stdClass();

$s = new BM_query();
$s->AddTable("lists");
$s->AddField("lists.id");
$s->AddField("lists.name");
$s->AddField("lists.desc");
$s->AddField("lists.addedDate");
$s->Attach(["lists" => "addedUser"], ["users as addedUser" => "id"]);
$s->Attach(["lists" => "owner"], ["users as owner" => "id"]);
$s->AddWhere(sprintf_esc("lists.id=%d", $_GET["id"]));
list($list) = $s->perform();

if ($list) {
    $result->success = true;
    $result->list = $list;

    $s = new BM_query();
    $s->AddTable("list_items");
    $s->Attach(["list_items" => "itemid"], ["prods as prod" => "id"]);
    $s->AddWhere(sprintf_esc("list_items.list=%d", $list->id));
    $s->AddWhere("list_items.type='prod'");
    $result->list->prods = $s->perform();

    $a = [];
    foreach ($result->list->prods as $p) {
        $a[] = &$p->prod;
    }
    PouetCollectPlatforms($a);

    $s = new BM_query();
    $s->AddTable("list_items");
    $s->Attach(["list_items" => "itemid"], ["groups as group" => "id"]);
    $s->AddWhere(sprintf_esc("list_items.list=%d", $list->id));
    $s->AddWhere("list_items.type='group'");
    $result->list->groups = $s->perform();

    $s = new BM_query();
    $s->AddTable("list_items");
    $s->Attach(["list_items" => "itemid"], ["parties as party" => "id"]);
    $s->AddWhere(sprintf_esc("list_items.list=%d", $list->id));
    $s->AddWhere("list_items.type='party'");
    $result->list->parties = $s->perform();

    $s = new BM_query();
    $s->AddTable("list_items");
    $s->Attach(["list_items" => "itemid"], ["users as user" => "id"]);
    $s->AddWhere(sprintf_esc("list_items.list=%d", $list->id));
    $s->AddWhere("list_items.type='user'");
    $result->list->users = $s->perform();

    $s = new BM_query();
    $s->AddTable("list_maintainers");
    $s->Attach(["list_maintainers" => "userID"], ["users as user" => "id"]);
    $s->AddWhere(sprintf_esc("list_maintainers.listID = %d", $list->id));
    $result->list->maintainers = $s->perform();
} else {
    $result->error = true;
}
output($result);
