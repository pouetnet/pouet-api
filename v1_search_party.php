<?php
if (!defined("POUET_API")) exit();

$terms = split_search_terms( @$_GET["q"] );
if ($terms)
{
  $s = new BM_Query("parties");
  $s->AddField("p.c as prods");
  $s->AddJoin("left","(select party, count(*) as c from prods group by party) as p","p.party = parties.id");
  $s->AddOrder(sprintf_esc("if(parties.name='%s',1,2)",@$_GET["q"]));
  $s->AddOrder("parties.name ASC");
  $s->SetLimit(100);
  foreach($terms as $term)
    $s->AddWhere(sprintf_esc("parties.name LIKE '%%%s%%'",_like($term)));
  $parties = $s->perform();
}

$result = new stdClass();
if (@$parties)
{
  $result->success = true;
  $result->results = $parties;
}
else
{
  $result->error = true;
}
output($result);
?>
