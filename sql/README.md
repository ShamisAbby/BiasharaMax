# SQL exports

Raw SQL for environments that can't run `artisan migrate` (e.g. handing a DBA a schema
to review, or restoring onto a box without the PHP toolchain installed).

- `bos.sql` — full schema dump, regenerate with `scripts/backup-db.sh --schema-only`.
- `seed.sql` — demo/seed data dump, regenerate with `scripts/seed-demo.sh --export`.

Neither file exists yet — both are generated artifacts, produced once the corresponding
script is run against a real database, not hand-maintained here.
