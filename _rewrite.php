<?php

require_once("config.inc.php");
require(POUETAPI_POUET_ROOT_LOCAL . "/bootstrap.inc.php");

// rate limit
if ($ephemeralStorage && defined("POUETAPI_RATELIMIT_TIMEFRAME") && defined("POUETAPI_RATELIMIT_REQUESTCOUNT")) {
    $key = "API_RATE_" . $_SERVER["REMOTE_ADDR"];
    $rateLimit = ["hits" => 0,"start" => time()];
    if ($ephemeralStorage->has($key)) {
        $rateLimit = $ephemeralStorage->get($key);
    }

    $rateLimit["hits"]++;

    if (time() - $rateLimit["start"] < POUETAPI_RATELIMIT_TIMEFRAME) {
        if ($rateLimit["hits"] >= POUETAPI_RATELIMIT_REQUESTCOUNT) {
            header("HTTP/1.1 429 Too Many Requests");
            die(sprintf(
                "<html><body><h1>HTTP 429 - Too Many Requests</h1>"
        . "You have exhausted your request limit of %d requests under %d seconds; you can restart in %d seconds.</body></html>",
                POUETAPI_RATELIMIT_REQUESTCOUNT,
                POUETAPI_RATELIMIT_TIMEFRAME,
                POUETAPI_RATELIMIT_TIMEFRAME - (time() - $rateLimit["start"]),
            ));
        }
    } else {
        $rateLimit["start"] = time();
        $rateLimit["hits"] = 0;
    }
    $ephemeralStorage->set($key, $rateLimit, POUETAPI_RATELIMIT_TIMEFRAME);
}

// render

$r = new Rewriter();
$r->addRules([
    "^\/+v1\/prod\/?$" => "v1_prod.php",
    "^\/+v1\/prod\/comments\/?$" => "v1_prod_comments.php",
    "^\/+v1\/search\/prod\/?$" => "v1_search_prod.php",

    "^\/+v1\/group\/?$" => "v1_group.php",

    "^\/+v1\/party\/?$" => "v1_party.php",
    "^\/+v1\/search\/party\/?$" => "v1_search_party.php",

    "^\/+v1\/user\/?$" => "v1_user.php",

    "^\/+v1\/board\/?$" => "v1_board.php",

    "^\/+v1\/lists\/?$" => "v1_lists.php",

    "^\/+v1\/stats\/?$" => "v1_stats.php",

    "^\/+v1\/front\-page\/latest\-added\/?$" => "v1_frontpage_latestadded.php",
    "^\/+v1\/front\-page\/latest\-released\/?$" => "v1_frontpage_latestreleased.php",
    "^\/+v1\/front\-page\/alltime\-top\/?$" => "v1_frontpage_alltimetop.php",
    "^\/+v1\/front\-page\/top\-of\-the\-month\/?$" => "v1_frontpage_monthtop.php",

    "^\/+v1\/enums\/platforms\/?$" => "v1_enums_platforms.php",

    "^\/+adhoc\/prods-from-year\/?$" => "adhoc_prods_year.php",
]);
$r->addBootstrap("./functions.inc.php");
$r->addEntryPoint("POUET_API");
$r->rewrite();
