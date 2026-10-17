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
<http://localhost:8081>. pouet-infra's `ARCHITECTURE.md` describes how it
runs in production.

## Contributing

Open a pull request against `master`. PRs are squash-merged once CI
passes, and a merged PR is live on api.pouet.net within a minute.

PHP follows [PER Coding Style 3.0](https://www.php-fig.org/per/coding-style/).
CI flags style issues without blocking the merge; `php-cs-fixer fix`
(3.95.27) fixes them.
