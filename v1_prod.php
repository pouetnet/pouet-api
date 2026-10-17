<?php

if (!defined("POUET_API")) {
    exit();
}

$prod = null;
if (@$_GET["random"]) {
    $query = new BM_Query("prods");
    $query->addExtendedFields();
    $query->AddOrder("RAND()");
    $query->SetLimit(1);
    $prods = $query->perform();

    $prod = reset($prods);
} else {
    $prod = @$_GET["id"] ? PouetProd::Spawn((int) $_GET["id"]) : null;
}

$result = new stdClass();
if ($prod) {
    $result->success = true;

    $a = [&$prod];
    PouetCollectPlatforms($a);
    PouetCollectAwards($a);

    $result->prod = $prod->ToAPI();

    $result->prod["downloadLinks"] = SQLLib::selectRows(sprintf_esc("select type, link from downloadlinks where prod = %d order by type", $prod->id));

    $s = new BM_Query();
    $s->AddTable("credits");
    $s->AddField("credits.role");
    $s->AddWhere(sprintf("credits.prodID = %d", $prod->id));
    $s->Attach(["credits" => "userID"], ["users as user" => "id"]);
    $s->AddOrder("credits.role");
    $result->prod["credits"] = $s->perform();
} else {
    $result->error = true;
}
output($result);
