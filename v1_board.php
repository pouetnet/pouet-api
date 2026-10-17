<?php

if (!defined("POUET_API")) {
    exit();
}

$board = PouetBoard::spawn($_GET["id"]);

$result = new stdClass();
if ($board) {
    $result->success = true;
    $result->board = $board->ToAPI();
} else {
    $result->error = true;
}
output($result);
