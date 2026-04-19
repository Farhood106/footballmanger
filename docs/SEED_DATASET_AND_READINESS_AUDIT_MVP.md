# Seed Dataset & Readiness Audit MVP (2026-04-15)

## Scope
This document defines the minimum practical seed dataset needed to run meaningful season simulation in the current project, and audits how ready the codebase is for seeded multi-season validation.

It extends the existing architecture instead of introducing a new importer platform.

---

## 1) Current baseline (what already exists)

The repository already includes:
- A simple seeding entrypoint and ordered seeder runner (`database/seed.php`, `SeederRunner`).
- Seeders for competitions/season, clubs, abilities, players.
- Runtime systems for rollover, qualification, match simulation, finance postings, AI lineup/transfer automation, youth intake.

This is enough for MVP simulation, but seed scope is still narrow and synthetic-heavy.

---

## 2) Seed data category plan

## A. Competitions / divisions / leagues

### Required fields
- `competitions`: `name`, `type`, `country`, `level`, `teams_count`.
- Hierarchy: `parent_competition_id` for promotion/relegation relations.
- Optional but recommended: `code` (stable import key), `promotion_slots`, `relegation_slots`.

### Minimum viable volume
- At least **2 linked league divisions** (top + second tier), each with enough clubs to trigger promotion/relegation behavior.
- Optional champions competition for qualification tests.

### Synthetic vs realistic
- Synthetic names/codes acceptable for MVP.
- For realism: stable league codes, realistic division sizes, believable promotion/relegation slot counts.

## B. Seasons

### Required fields
- `seasons`: `competition_id`, `name`, `start_date`, `end_date`, `status`.

### Minimum viable volume
- At least **2 seasons per active competition** (one active + one upcoming) to avoid rollover dead ends.

### Synthetic vs realistic
- Synthetic dates are okay, but should preserve chronological consistency for scheduler + rollover.

## C. Clubs

### Required fields
- `clubs`: `name`, `short_name`, `country`, `city`, `founded`, `reputation`, `balance`, `stadium_name`, `stadium_capacity`.

### Optional fields
- `badge_url`, owner/manager assignments (`owner_user_id`, `manager_user_id`) depending on scenario.

### Minimum viable volume
- For league tests: at least league `teams_count` per season with `club_seasons` + `standings` rows.

### Synthetic vs realistic
- Synthetic club names are acceptable for internal simulation.
- Realistic import should include region-consistent clubs and plausible budgets/reputation.

## D. Players

### Required fields
- Core identity + gameplay: `club_id`, names, nationality, birth date, position, attributes (`pace..physical`), `overall`, `potential`, `fitness`, `morale_score`, wage/contract/market fields.

### Optional fields
- Squad depth and continuity: `squad_role`, `last_played_at`, `last_minutes_played`, ability links, academy-origin fields.

### Minimum viable volume
- **22-30 players per club** (11 starters + depth) to reduce AI lineup failure risk.

### Synthetic vs realistic
- Synthetic names/ratings acceptable for engine smoke tests.
- Realistic data should include position-balanced squads and age pyramids.

## E. Contracts (manager domain)

### Required fields
- `manager_contracts`: `club_id`, `owner_user_id`, `coach_user_id`, `status`, dates, salary.

### Optional fields
- `terms_json`, negotiation history, termination history.

### Minimum viable volume
- At least one ACTIVE contract for clubs under human owner/manager tests.
- Explicit vacancy states for AI caretaker tests.

### Synthetic vs realistic
- Synthetic terms are sufficient for workflow validation.
- Realistic scenarios benefit from salary scaling by club finances.

## F. Club finances

### Required fields
- Club initial balances and opening ledger entries (`club_finance_ledger`).

### Optional fields
- richer historical ledger categories (`SPONSOR_INCOME`, wage, facility maintenance, transfers).

### Minimum viable volume
- Opening balance transaction per seeded club + enough funds for wages and transfer behavior.

### Synthetic vs realistic
- Synthetic values fine for deterministic tests.
- Realistic values should preserve variance by club scale.

## G. Facilities

### Required fields
- `club_facilities` rows per club (`stadium`, `training_ground`, `youth_academy`, `headquarters`).

### Optional fields
- `image_url` references.

### Minimum viable volume
- One row per facility type per club, baseline level 1.

### Synthetic vs realistic
- Synthetic levels acceptable.
- Realistic models should align youth/training strength with club reputation/budget.

## H. Sponsors

### Required fields
- `club_sponsors`: at least one active sponsor per club for recurring economy tests.

### Optional fields
- brand metadata (`description`, `contact_link`, `banner_url`).

### Minimum viable volume
- 1-2 sponsors per club with mixed tiers.

### Synthetic vs realistic
- Synthetic brand names okay for internal simulation.
- Realistic sets should avoid legal/trademark conflicts.

## I. Manager assignments

### Required fields
- clubs should have consistent owner/manager assignment states to exercise AI vacancy logic.

### Minimum viable volume
- Mixed matrix: owner+manager, owner only, manager only, neither.

## J. Squad-role/readiness defaults

### Required fields
- Default `squad_role` distribution by squad depth.
- `last_minutes_played` and optional `last_played_at` baselines to avoid unstable morale/usage heuristics in AI/market decisions.

---

## 3) Seed-readiness audit (current codebase)

## Good readiness areas
1. **Core entities/tables exist** for competitions, seasons, clubs, players, matches, standings, contracts, finance, facilities, sponsors, history.
2. **Season lifecycle exists** (rollover preview/finalize/apply + qualification slots).
3. **Daily simulation hooks exist** (scheduler/orchestrator + AI lineup prep + finance + development + maintenance).
4. **Youth intake exists** and writes into normal players table with intake logs.

## Fragile / underdefined areas for realistic import
1. **Seeders are hard-delete + reseed scripts** and not designed for staged/idempotent bulk imports.
2. **Current seeds are single-league and single-season heavy**, limiting multi-competition validation depth.
3. **No canonical external IDs** on core domain tables (`external_id`/`source_key`) for reliable upsert from real datasets.
4. **No dedicated seed validation tooling** (e.g., referential integrity pre-check, squad-size sanity, position-balance checks) before simulation starts.
5. **Limited manager/user seed strategy** despite manager contract/vacancy systems requiring coherent user<->club mapping.

## Missing/weak fields for realistic import (recommended additions in later sprint)
- Import identity: `external_id` or `source_key` on competitions/clubs/players/seasons.
- Player biography realism fields (optional): dominant nation history, youth graduation date, preferred positions ranking.
- Contract realism fields (optional): clauses/bonuses normalization instead of opaque JSON only.
- Economic realism fields (optional): recurring sponsor cadence metadata and richer operating cost classes.

## Cleanup needed before large real-world dataset ingest
1. Add a **staging/import key strategy** (source namespace + external key).
2. Add **idempotent upsert path** (not only destructive seeders).
3. Add **pre-simulation integrity checks** (club roster minimums, active season participants, fixture readiness).
4. Add **seed profile separation** (`dev_small`, `sim_medium`, `realistic_large`).

---

## 4) Practical seed file structure recommendation (future import-ready)

Use a staged, human-reviewable structure:

```text
database/seed_sets/
  profile.dev_small/
    competitions.json
    seasons.json
    clubs.json
    players.json
    club_seasons.json
    standings.json
    facilities.json
    sponsors.json
    manager_contracts.json
  profile.sim_medium/
    ...
```

### Why JSON-first for MVP planning
- Works with existing PHP stack quickly.
- Easy diff/review in PRs.
- Easy later conversion to CSV/SQL pipelines.

### Loading order (recommended)
1. competitions
2. seasons
3. clubs
4. users (owner/manager)
5. club_seasons + standings
6. players
7. facilities
8. sponsors
9. manager contracts / negotiations snapshots
10. optional history snapshots

---

## 5) Suggested phased adoption

1. Keep current `database/seed.php` for smoke/dev.
2. Add separate importer command later (e.g. `php database/import_seed_set.php --profile=dev_small`).
3. Keep migration-first discipline; seed layer should not create schema.
4. Add simulation assertion suite that consumes seeded profiles.

