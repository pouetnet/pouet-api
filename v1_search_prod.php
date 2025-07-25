<?php
if (!defined("POUET_API")) exit();

//$prod = $_GET["id"] ? PouetProd::Spawn((int)$_GET["id"]) : null;
$terms = split_search_terms( $_GET["q"] );
if ($terms)
{
  $s = new BM_Query("prods");
  $s->AddField("cmts.c as commentCount");
  $s->AddJoin("left","(select which, count(*) as c from comments group by which) as cmts","cmts.which = prods.id");
  $s->AddOrder("prods.name ASC");
  $s->AddOrder("prods.id");
  $s->SetLimit(100);
  foreach($terms as $term)
    $s->AddWhere(sprintf_esc("prods.name LIKE '%%%s%%'",_like($term)));
  $prods = $s->perform();
}
if(@$prods)
{
  PouetCollectPlatforms( $prods );
  PouetCollectAwards( $prods );
  foreach($prods as &$prod)
  {
    unset($prod->views);
    unset($prod->latestip);
    unset($prod->addedUser->lastLogin);
  }
}

$result = new stdClass();
if (@$prod)
{
  $result->success = true;
  $result->results = $prods;
}
else
{
  $result->error = true;
}
output($result);
?>
