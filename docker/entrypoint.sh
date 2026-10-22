#!/bin/bash
set -e

WWW="${POUET_WWW_PATH:-/var/www/pouet}"
API="${POUET_API_PATH:-/var/www/pouet-api}"
MYSQL_HOST="${MYSQL_HOST:-mysql}"
MYSQL_DB="${MYSQL_DATABASE:-pouet}"
MYSQL_USER="${MYSQL_USER:-pouet}"
MYSQL_PASS="${MYSQL_PASSWORD:-pouet}"
REDIS_HOST="${REDIS_HOST:-redis}"
SITE_HOST="${SITE_HOST:-localhost}"
SITE_PORT="${SITE_PORT:-8080}"

# --- patch ephemeral storage redis host ---
if [ -f "$WWW/include_generic/ephemeral_storage.inc.php" ]; then
  sed -i "s/\\\$this->redis->connect('localhost')/\\\$this->redis->connect('$REDIS_HOST')/" "$WWW/include_generic/ephemeral_storage.inc.php"
fi

# --- pouet-www credentials.inc.php ---
mkdir -p "$WWW/include_generic"
cat > "$WWW/include_generic/credentials.inc.php" <<PHP
<?php
define("SQL_HOST","$MYSQL_HOST");
define("SQL_USERNAME","$MYSQL_USER");
define("SQL_PASSWORD","$MYSQL_PASS");
define("SQL_DATABASE","$MYSQL_DB");
define('SCENEID_USER', '');
define('SCENEID_PASS', '');
define('SCENEID_URL', '');
define('POUET_TEST', true);
define('POUET_ROOT_PATH', "/");
define('POUET_COOKIE_DOMAIN', ".$SITE_HOST");
define('POUET_CONTENT_URL', '/content/');
define('POUET_MOBILE_HOSTNAME', 'm.$SITE_HOST');
define('POUET_WEB_HOSTNAME', 'www.$SITE_HOST');
define('POUET_CONTENT_LOCAL', "/var/www/pouet/content/");
define('POUET_API_URL', 'http://$SITE_HOST:$SITE_PORT/');
define('POUET_CARDBOARD_URL', 'http://$SITE_HOST:$SITE_PORT/');
define('POUET_MOBILE', false);
define('POUET_ROOT_URL_SECURE', 'http://$SITE_HOST:$SITE_PORT/');
define('POUET_ROOT_URL', 'http://$SITE_HOST:$SITE_PORT/');

require_once(POUET_ROOT_LOCAL . "/include_generic/sceneid3/sceneid3.inc.php");
#[\AllowDynamicProperties]
class MySceneID extends SceneID3
{
  function __construct( \$options = array() ) { \$this->returnURL = \$options["redirectURI"]; }
  function GetClientCredentialsToken() { return true; }
  function SetStorage( \$storage ) {}
  function SetScope( \$scope ) {}
  function SetFormat( \$format ) {}
  function UnpackFormat( \$data ) { return \$data; }
  function GetAuthURL() { return \$this->returnURL . "?code=123456&state=123456"; }
  function ProcessAuthResponse( \$code = null, \$state = null ) { return true; }
  function RefreshToken() { return true; }
  function VerifyToken() { return true; }
  function User( \$userID ) { return array(
    "success"=>true,
    "user"=>array(
      "id"=>(int)\$userID,
      "first_name"=>"First",
      "last_name"=>"Last",
      "display_name"=>"User #".(int)\$userID,
    ),
  ); }
  function Me() { return \$this->User(1); }
}
PHP

# --- pouet-api config.inc.php ---
cat > "$API/config.inc.php" <<PHP
<?php
define("POUETAPI_POUET_ROOT_LOCAL","$WWW/");
define("POUETAPI_RATELIMIT_TIMEFRAME",60);
define("POUETAPI_RATELIMIT_REQUESTCOUNT",1000);
PHP

# Wait for MySQL
echo "Waiting for MySQL..."
until mysql --skip-ssl -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASS" -e "SELECT 1" >/dev/null 2>&1; do
  sleep 2
done
echo "MySQL ready."

# Import sample data from data.pouet.net
if [ "${IMPORT_DATA:-0}" = "1" ]; then
  if [ ! -f /tmp/.imported ]; then
    echo "Importing sample data from data.pouet.net..."
    python3 "$API/scripts/import_pouet_data.py" \
      --mysql-host "$MYSQL_HOST" --mysql-user "$MYSQL_USER" --mysql-password "$MYSQL_PASS" --mysql-db "$MYSQL_DB" \
      --www-root "$WWW" \
      && touch /tmp/.imported
    echo "Import done."
  else
    echo "Data already imported (marker /tmp/.imported)."
  fi
fi

# Ensure cache dir
mkdir -p "$WWW/cache"

exec "$@"
