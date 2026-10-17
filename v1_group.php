<?php

if (!defined("POUET_API")) {
    exit();
}

$group = $_GET["id"] ? PouetGroup::Spawn((int) $_GET["id"]) : null;
if ($group) {
    $s = new BM_Query("prods");
    $s->AddWhere(sprintf_esc("(prods.group1 = %d) or (prods.group2 = %d) or (prods.group3 = %d)", $group->id, $group->id, $group->id));
    $prods = $s->perform();
    PouetCollectPlatforms($prods);
    PouetCollectAwards($prods);
}

$result = new stdClass();
if ($group) {
    $result->success = true;
    $result->group = $group->ToAPI();
    if ($prods) {
        $result->group["prods"] = [];
        foreach ($prods as $prod) {
            $result->group["prods"][] = $prod->ToAPI();
        }
    }
} else {
    $result->error = true;
}
output($result);
