# Bard

A Symfony 8.1 application for browsing and experimenting with the structured Shakespeare corpus from [Open Source Shakespeare](https://www.opensourceshakespeare.org/).

## Local setup

The app follows the Survos shared-development conventions and defaults to the shared PostgreSQL server on port 5434, database `bard`.

```bash
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console app:load
symfony server:start -d
```

Open the local URL printed by the Symfony CLI. Each work can be read in the browser or downloaded in Fountain screenplay format.

## Data

The corpus is not committed to this repository. `app:load` downloads `oss-textdb.zip` from the [Open Source Shakespeare downloads page](https://www.opensourceshakespeare.org/downloads/) to `data/oss-textdb.zip` on first run (resumable, via `survos/fetch-bundle`) and reads the tables straight out of the archive through PHP's `zip://` stream wrapper — nothing is ever unpacked to disk.

Run `app:download` on its own to fetch the archive without loading, and add `--force` (or `app:load --refresh`) to pull a fresh copy.

See [DATA_LICENSE.md](DATA_LICENSE.md). The OSS structure and metadata are CC BY-NC 4.0; do not assume that the application's source-code license applies to the data. The legacy OSS PHP site is reference material only and is not incorporated into this application.

## Next candidates

- publish normalized tables as a Hugging Face dataset;
- demonstrate lexical and faceted search through `survos/search-bundle` and `survos/eliastica-bundle`;
- derive stable chunks for Symfony AI vector-store tests.
- experiment with work-, scene-, and character-scoped conversational retrieval.
