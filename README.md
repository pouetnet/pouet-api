# Pouët API

Trumpets for everyone! The JSON API of [Pouët.net](https://www.pouet.net),
on <https://api.pouet.net>, which lists the endpoints.

## How it works

The API runs on www's code. `config.inc.php` (gitignored, template
`config.inc.php.dist`) points `POUETAPI_POUET_ROOT_LOCAL` at a
[pouet-www](https://github.com/pouetnet/pouet-www) checkout, whose
bootstrap gives the database access. The `POUETAPI_RATELIMIT_*` constants
turn on rate limiting, which uses www's Redis. `.htaccess` sends every
request to `_rewrite.php`, which picks the `v1_*.php` endpoint.

## Running it locally

The local stack (Docker images matching production, configs and sample
data) lives in [pouet-infra](https://github.com/pouetnet/pouet-infra),
which is only open to the team for now. If you have access:

```sh
git clone git@github.com:pouetnet/pouet-infra.git
cd pouet-infra
just setup
```

This clones this repo and the other site repos under `sites/`, starts
everything and loads sample data. The API is then on
<http://api.pouet.localhost>. pouet-infra's `ARCHITECTURE.md` describes how it
runs in production.

### Running with Docker (without pouet-infra)

If you don't have access to pouet-infra, you can run the API locally with
the provided Docker Compose setup. It uses a public
[pouet-www](https://github.com/pouetnet/pouet-www) checkout and loads
sample data from <https://data.pouet.net/>.

Prerequisites: Docker + Docker Compose.

```sh
# 1. Clone pouet-www next to this repo (if not already)
git clone https://github.com/pouetnet/pouet-www.git ../pouet-www

# 2. Adjust the path in docker-compose.yml / .env if needed
cp .env.example .env

# 3. Start
docker compose up --build
```

The API is then on <http://localhost:8090>. On first start the container
downloads the latest data dump from data.pouet.net and imports it (set
`IMPORT_DATA=0` to skip).

Notes:
- `POUET_WWW_PATH` (default `../pouet-www`) must point at your
  pouet-www checkout.
- MySQL is exposed on port 3307 (user `pouet` / pass `pouet`, db `pouet`).
- The importer is best-effort: endpoints that need tables without a public
  dump (comments, awards, ...) stay empty.

## Contributing

Open a pull request against `master`. PRs are squash-merged once CI
passes, and a merged PR is live on api.pouet.net within a minute.

PHP follows [PER Coding Style 3.0](https://www.php-fig.org/per/coding-style/).
CI flags style issues without blocking the merge; `php-cs-fixer fix`
(3.95.27) fixes them.
