<?
if (!defined("POUET_API")) exit();

require_once( POUETAPI_POUET_ROOT_LOCAL . "include_pouet_index/index_bootstrap.inc.php");

$box = new PouetBoxIndexTopAlltime();
$box->Load(true);

$result = new stdClass();
if ($box->data)
{
  $result->success = true;
  $result->prods = array();
  $rank = 1;
  foreach($box->data as $prod)
  {
    $result->prods[] = array(
      "rank"=>$rank++,
      "prod"=>$prod
    );
  }
}
else
{
  $result->error = true;
}
output($result);
?>