<?php
if (!defined("POUET_API")) exit();

$q = new BM_Query();
$q->AddField("gloperator_log.*");
$q->AddTable("gloperator_log");
$q->attach(array("gloperator_log" => "gloperatorID"), array("users as gloperator" => "id"));
$q->AddOrder("date desc");

if (@$_GET["prod"])
{
  $q->AddWhere(sprintf_esc("itemType = 'prod'"));
  $q->AddWhere(sprintf_esc("itemID = %d", (int)$_GET["prod"]));
}
else
{
  $q->SetLimit(@$_GET["limit"] ?: 100);
}

$result = new stdClass();
$result->success = true;
$result->results = $q->perform();
output($result);
?>
