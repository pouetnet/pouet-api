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
$s->AddOrder("parties.id ASC");
$s->SetLimit($limit, $offset);
$parties = array_values($s->performWithCalcRows($total));

// collect the years a party took place in; same sources as the party page:
// prods as primary party, prods as invitation, and prods as secondary party
$partyYears = [];
if ($parties) {
    $ids = [];
    foreach ($parties as $party) {
        $ids[] = (int) $party->id;
    }
    $idList = implode(",", $ids);

    $rows = SQLLib::selectRows(sprintf(
        "SELECT party, party_year FROM `prods` WHERE party IN (%s) AND party_year > 0"
        . " UNION SELECT party, party_year FROM `prodotherparty` WHERE party IN (%s) AND party_year > 0"
        . " UNION SELECT invitation AS party, invitationyear AS party_year FROM `prods` WHERE invitation IN (%s) AND invitationyear > 0",
        $idList,
        $idList,
        $idList,
    ));
    foreach ($rows as $row) {
        $partyYears[(int) $row->party][] = (int) $row->party_year;
    }
}

$result = new stdClass();
if ($parties) {
    $result->success = true;
    $result->total = (int) $total;
    $result->limit = $limit;
    $result->offset = $offset;
    $result->results = [];
    foreach ($parties as $party) {
        $id = (int) $party->id;
        $years = array_values(array_unique($partyYears[$id] ?? []));
        sort($years);
        $result->results[] = [
            "id" => $party->id,
            "name" => $party->name,
            "web" => $party->web,
            "addedDate" => $party->addedDate,
            "addedUser" => $party->addedUser,
            "years" => $years,
        ];
    }
} else {
    $result->error = true;
}
output($result);
