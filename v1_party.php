<?php

if (!defined("POUET_API")) {
    exit();
}

$party = PouetParty::spawn($_GET["id"]);
$year = (int) @$_GET["year"];

$result = new stdClass();
if ($party) {
    $result->success = true;
    $result->party = $party->ToAPI();

    $prods = [];
    $s = new BM_Query("prods");
    $s->AddWhere(sprintf_esc("(prods.party = %d AND prods.party_year = %d) or (prodotherparty.party = %d AND prodotherparty.party_year = %d)", $party->id, $year, $party->id, $year));

    // this is where it gets nasty; luckily we can fake it relatively elegantly: ORM won't notice if we override some of the field selections
    $s->AddJoin("left", "prodotherparty", sprintf_esc("prodotherparty.prod = prods.id and (prodotherparty.party = %d AND prodotherparty.party_year = %d)", $party->id, $year));
    /*
    foreach($s->fields as &$v)
    {
      if ($v == "prods.party_compo as prods_party_compo")
      {
        $v = "IF(prodotherparty.id,prodotherparty.party_compo,prods.party_compo) as prods_party_compo";
      }
      if ($v == "prods.party_place as prods_party_place")
      {
        $v = "IF(prodotherparty.id,prodotherparty.party_place,prods.party_place) as prods_party_place";
      }
    }
    */

    $links = SQLLib::selectRow(sprintf("SELECT * FROM `partylinks` WHERE party = %d and year = %d", $party->id, $year));
    if ($links) {
        unset($links->id);
        unset($links->party);
        unset($links->year);
        $result->party["links"] = $links;
    }

    $prods = $s->perform();
    PouetCollectPlatforms($prods);
    PouetCollectAwards($prods);
    if ($prods) {
        $result->party["prods"] = [];
        foreach ($prods as $prod) {
            $result->party["prods"][] = $prod->ToAPI();
        }
    }

    $inv = new BM_Query("prods");
    $inv->AddWhere(sprintf_esc("(prods.invitation = %d AND prods.invitationyear = %d)", $party->id, $year));
    $inv->AddOrder("prods.addedDate");
    $invitations = $inv->perform();
    PouetCollectPlatforms($invitations);
    PouetCollectAwards($invitations);
    if ($invitations) {
        $result->party["invitations"] = [];
        foreach ($invitations as $prod) {
            $result->party["invitations"][] = $prod->ToAPI();
        }
    }
} else {
    $result->error = true;
}
output($result);
