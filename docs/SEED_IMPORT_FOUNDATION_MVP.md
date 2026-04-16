# Seed Import Foundation MVP (2026-04-15)

## What was added
A lightweight structured seed import path was added for:
- competitions
- clubs
- players

Entrypoint:
- `php database/import_seed_set.php --path=<dir> [--dry-run=1]`

Importer class:
- `database/seeds/StructuredSeedImporter.php`

---

## Import strategy

### Staged order
1. competitions
2. clubs
3. players

This order ensures parent dependencies are present before child rows are processed.

### Non-destructive behavior
- Uses insert-or-update (upsert-like) behavior by stable keys.
- Does **not** delete entire tables.

### External key strategy
- `competitions.external_key` maps to `competitions.code` (unique).
- `clubs.external_key` maps to `clubs.external_key` (unique). `clubs.short_name` remains the short display code.
- `players.external_key` maps to new `players.external_key` column (migration included).

Fallback:
- If `players.external_key` column is unavailable in compatibility contexts, importer falls back to natural-key detection (`club_id + first_name + last_name + birth_date`) and warns.

---

## Validation behavior
Pre-import checks include:
- malformed/missing JSON file checks
- required-field validation per entity
- duplicate external key detection inside each input dataset
- parent reference checks (`parent_external_key`, `competition_external_key`, `club_external_key`)

---

## Reporting
The importer emits JSON output containing:
- per-stage counts (`inserted`, `updated`, `skipped`, `invalid`)
- warnings and validation errors
- dry-run status

---

## Example
```bash
php database/import_seed_set.php --path=docs/examples/seed_templates --dry-run=1
php database/import_seed_set.php --path=docs/examples/seed_templates
php database/import_seed_set.php --path=database/seed_sets/mini_v1 --dry-run=1
php database/import_seed_set.php --path=database/seed_sets/mini_v1
```
