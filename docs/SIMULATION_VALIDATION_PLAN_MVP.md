# Simulation Validation Plan MVP (2026-04-15)

## Purpose
Define structured simulation scenarios to validate world continuity and balance once richer seed datasets are available.

This is a planning layer for deterministic test campaigns, not a full CI load-test framework yet.

---

## Global preconditions for all scenarios
- Schema migration set is fully applied.
- Seed profile loaded with valid participants/rosters.
- At least one active season with generated fixtures.
- Daily scheduler can execute without fatal errors.

---

## Scenario 1: One-season simulation baseline

### Inputs needed
- 1 active league season with full participants + fixtures.
- Clubs seeded with minimum viable squads and finances.

### Run pattern
- Advance day-by-day via scheduler until season status reaches FINISHED and rollover preview/finalize/apply completed.

### Validate outputs
- Matches progress SCHEDULED -> FINISHED.
- Standings evolve and final table is coherent.
- Rollover log is generated and then applied.

### Bug/imbalance indicators
- Missing or repeated match simulations.
- Season stuck in ACTIVE with all fixtures already played.
- Rollover apply blocked despite valid standings.

---

## Scenario 2: Multi-season continuity (3+ seasons)

### Inputs needed
- 2+ linked divisions with promotion/relegation configured.
- Upcoming seasons pre-created or auto-create enabled.

### Validate outputs
- Club participation continuity across seasons.
- Promotion/relegation and direct entries correctly reflected in `club_seasons`.
- No duplicate rollover application for same season.

### Bug/imbalance indicators
- Participant duplication in target seasons.
- Empty target season after rollover.
- Cross-season data corruption (club assigned to wrong competition branch).

---

## Scenario 3: Transfer activity over time

### Inputs needed
- Clubs with diverse balances and squad depth.
- Transfer-listed players and AI-managed clubs.

### Validate outputs
- Offer/counter/accept/reject flow occurs with valid ownership checks.
- Transfer fees reflected in balances and finance ledger.
- Player `club_id` updates and listing flags reset as expected.

### Bug/imbalance indicators
- Persistent PENDING offers never resolving.
- Balance drift without ledger entries.
- Player appears in two clubs or remains listed after transfer completion.

---

## Scenario 4: Youth intake emergence

### Inputs needed
- Clubs with varied `youth_academy` levels.
- Season rollover apply executed.

### Validate outputs
- Intake log rows created for target clubs/season intake key.
- Generated players inserted with academy-origin fields.
- Intake duplicate prevention holds for same club/season/key.

### Bug/imbalance indicators
- No generated players after rollover apply.
- Duplicate intake logs for same event key.
- Generated players missing required gameplay fields.

---

## Scenario 5: Financial pressure & recurring economy

### Inputs needed
- Clubs with low/medium/high balances.
- Active salaries, maintenance and sponsor flows.

### Validate outputs
- Daily salary and maintenance entries post consistently.
- Sponsor income postings impact balances correctly.
- No silent negative-balance corruption from duplicated daily postings.

### Bug/imbalance indicators
- Duplicate daily ledger events for same logical day/event.
- Non-deterministic balance jumps without ledger trace.
- Clubs collapsing too quickly due to overtuned recurring costs.

---

## Scenario 6: Promotion/relegation correctness

### Inputs needed
- Parent-child competition structure and standings.

### Validate outputs
- Top clubs promoted based on configured slots.
- Bottom clubs relegated based on configured slots.
- Non-promoted/relegated clubs remain direct in next season.

### Bug/imbalance indicators
- Wrong slot count applied.
- Promoted/relegated clubs duplicated across tiers.
- Rollover blocked by stale participants not surfaced clearly.

---

## Scenario 7: Champions qualification correctness

### Inputs needed
- Active qualification slot mappings.
- Source competitions with valid standings.
- Target champions seasons in UPCOMING state.

### Validate outputs
- Qualification preview returns expected clubs.
- Apply writes qualified/champion participants only once.
- Honors include qualification event where expected.

### Bug/imbalance indicators
- Apply allowed on ACTIVE/FINISHED target season.
- Duplicate participant inserts.
- Qualification slot mismatch vs standings order.

---

## Scenario 8: Manager vacancy/termination/replacement lifecycle

### Inputs needed
- Clubs seeded across vacancy states.
- At least one active manager contract.

### Validate outputs
- Termination writes termination log + contract status changes.
- Vacancy sync reflects owner/manager presence changes.
- Replacement contract path restores stable state.

### Bug/imbalance indicators
- Contract remains ACTIVE after termination event.
- Vacancy states not synced for AI caretaker routing.
- Compensation postings missing from finance ledger.

---

## Scenario 9: Awards/history continuity

### Inputs needed
- Multi-week/multi-season match outcomes.

### Validate outputs
- Player awards generated with valid scope.
- Club honors include title/promotion/relegation/qualification events.
- Records/legends refresh without duplicate key corruption.

### Bug/imbalance indicators
- Awards missing despite completed matches.
- Duplicate honors violating uniqueness expectations.
- Records not updating across seasons.

---

## Reporting template for each simulation campaign

For each scenario run, capture:
1. Seed profile/version used.
2. Simulation date range and commands run.
3. Key counts (matches simulated, transfers resolved, intake players generated, honors posted).
4. Failed assertions/anomalies.
5. Suggested tuning vs code defects.

