# Mini Seed Dataset v1

Purpose: small realistic/semi-realistic world for importer validation and multi-system gameplay smoke tests.

## Included import-ready files
- competitions.json (2 competitions)
- clubs.json (8 clubs)
- players.json (160 players / 20 per club)

## Optional companion files (not imported by StructuredSeedImporter yet)
- facilities.companion.json
- sponsors.companion.json

## Import commands
php database/import_seed_set.php --path=database/seed_sets/mini_v1 --dry-run=1
php database/import_seed_set.php --path=database/seed_sets/mini_v1

## Design notes
- Two-tier league structure with promotion/relegation relation.
- Mixed budgets/reputation to stress finance + market variance.
- Player mix includes prospects, key players, older decline profiles, and transfer-listed cases.
