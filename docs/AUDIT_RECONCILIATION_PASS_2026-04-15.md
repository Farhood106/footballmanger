# Focused Audit Reconciliation Pass (Current Source)
_Date: 2026-04-15_

This pass re-checks only the 7 requested areas against the **current repository state**.

## Reconciliation note vs prior audit
- I re-verified the current source for youth/academy support. The codebase still does **not** include a youth intake generator or academy-origin player tagging pipeline. What exists is youth-academy facility infrastructure and a youth potential bonus hook in development logic.
- So for that specific point, my prior assessment (“no youth intake pipeline / no academy-origin tagging”) remains correct for the current source.

---

## 1) Youth academy / youth intake / academy-origin players
**Status:** **FOUNDATION ONLY**

### What exists
- Facility type includes `youth_academy` in infra system: `app/Services/ClubFacilityService.php`.
- Development system consumes youth academy bonus: `PlayerCareerService::runDailyDevelopmentAndValuation()` -> `getYouthPotentialBonus()`.
- Transfer schema includes enum `YOUTH_PROMOTION` but no implementation path uses it.

### Exact files
- `app/Services/ClubFacilityService.php`
- `app/Services/PlayerCareerService.php`
- `database/schema.sql`

### Routes
- None specific to youth intake or academy-origin management.

### Runtime trigger points
- Daily orchestrator runs development each cycle: `DailyCycleOrchestrator::run()` -> `PlayerCareerService::runDailyDevelopmentAndValuation()`.

### Views
- No youth intake UI, no academy-origin player UI.

### Missing for “implemented and wired”
- Intake generation event/job.
- Player-level academy-origin marker/column and display.
- Promotion workflow using `YOUTH_PROMOTION` in runtime services/controllers.

---

## 2) Club facilities / facility effects / maintenance UI
**Status:** **IMPLEMENTED AND WIRED**

### What exists
- Full facility CRUD-like ops for level changes (upgrade/downgrade), cost/refund logic, maintenance posting.
- Permission checks (owner/admin).
- Effects consumed by readiness/development code.

### Exact files
- Service: `app/Services/ClubFacilityService.php`
- Controller: `app/Controllers/ClubFacilitiesController.php`
- View: `app/Views/club/facilities.php`
- Dashboard visibility: `app/Controllers/DashboardController.php`, `app/Views/dashboard/index.php`
- Finance coupling: `app/Services/FinanceService.php`
- Schema/migration: `database/schema.sql`, `database/migrations/20260412_club_facilities_mvp.sql`

### Routes
- `GET /club/facilities`
- `POST /club/facilities/upgrade`
- `POST /club/facilities/downgrade`

### Runtime trigger points
- User actions via facilities controller.
- Daily maintenance via `DailyCycleOrchestrator::run()` -> `ClubFacilityService::postDailyMaintenance()`.
- Effect hooks used by `PlayerCareerService`.

### Views
- `app/Views/club/facilities.php`
- `app/Views/dashboard/index.php` (facility overview)

---

## 3) Recurring club economy (player wages, sponsor payouts, operating costs)
**Status:** **IMPLEMENTED BUT PARTIALLY WIRED**

### What exists
- Central ledger posting via `FinanceService::postEntry()`.
- Recurring **coach salary** cycle postings from active manager contracts.
- Sponsor income posting workflow (manual trigger per sponsor).
- Recurring facility maintenance expenses.

### Exact files
- `app/Services/FinanceService.php`
- `app/Services/DailyCycleOrchestrator.php`
- `app/Controllers/FinanceController.php`
- `app/Views/finance/index.php`
- `app/Services/ClubFacilityService.php`

### Routes
- `GET /finance`
- `POST /finance/owner-funding`
- `POST /finance/sponsors/add`
- `POST /finance/sponsors/update`
- `POST /finance/sponsors/toggle`
- `POST /finance/sponsors/income`
- `POST /finance/admin-adjust`

### Runtime trigger points
- Daily cycle: coach salary + facility maintenance postings.
- Sponsor income is currently operator-triggered (not autonomous scheduled payout).

### Views
- `app/Views/finance/index.php`
- dashboard finance summary in `app/Views/dashboard/index.php`

### Gaps
- No recurring **player wage** posting loop despite `WAGE` enum support.
- No broad operating-cost model beyond facilities/coach.
- No debt/arrears/cashflow pressure subsystem.

---

## 4) Awards / records / club history visibility
**Status:** **IMPLEMENTED BUT PARTIALLY WIRED**

### What exists
- History service with awards/honors/records/legends logic.
- Triggered from match simulation (player-of-match/week) and season finalization/rollover honor flows.
- Dedicated club history page and dashboard snippets.

### Exact files
- `app/Services/WorldHistoryService.php`
- `app/Services/MatchEngine.php`
- `app/Services/AdminCompetitionService.php`
- `app/Controllers/ClubHistoryController.php`
- `app/Views/club/history.php`
- `app/Controllers/DashboardController.php`
- `app/Views/dashboard/index.php`

### Routes
- `GET /club/history`

### Runtime trigger points
- `MatchEngine::recordMatchAwards()` -> `WorldHistoryService` writes.
- `AdminCompetitionService::finalizeSeason()` / `applyRollover()` apply honors/awards refresh.

### Views
- `app/Views/club/history.php`
- dashboard sections in `app/Views/dashboard/index.php`

### Gaps / partial aspects
- History tables are runtime-created in service; canonical schema/migrations don’t include them.

---

## 5) Champions qualification automation
**Status:** **IMPLEMENTED BUT PARTIALLY WIRED**

### What exists
- Qualification slots configuration.
- Preview logic from finished source seasons.
- Apply logic inserts participants into target champions season with `champion/qualified` entry types.

### Exact files
- `app/Services/AdminCompetitionService.php`
- `app/Controllers/AdminCompetitionController.php`
- `app/Views/admin/competitions.php`
- `database/schema.sql`
- `database/migrations/20260412_champions_qualification_mvp.sql`

### Routes
- `POST /admin/qualifications/slots/save`
- `POST /admin/seasons/{id}/qualifications/preview`
- `POST /admin/seasons/{id}/qualifications/apply`

### Runtime trigger points
- Admin-triggered workflow only (not auto-scheduled at season rollover end).

### Views
- `app/Views/admin/competitions.php`

### Partial aspects
- “Automation” exists as deterministic backend logic, but execution is admin-initiated.
- Known counter bug in apply path (`$inserted++` duplicated) affects reporting accuracy.

---

## 6) Transfer market core
**Status:** **IMPLEMENTED AND WIRED**

### What exists
- Transfer listing/unlisting with asking price.
- Buyer bid creation with validation.
- Seller accept/reject.
- Accept path does player ownership switch + finance-safe postings + cancel competing pending bids.

### Exact files
- `app/Models/TransferModel.php`
- `app/Controllers/TransferController.php`
- `app/Views/transfer/market.php`
- `app/Models/PlayerModel.php`
- `database/schema.sql`
- `database/migrations/20260412_transfer_market_core_expansion_mvp.sql`

### Routes
- `GET /transfers`
- `POST /transfer/listing`
- `POST /transfer/bid`
- `POST /transfer/accept/{id}`
- `POST /transfer/reject/{id}`

### Runtime trigger points
- User POST flows through transfer controller.
- `TransferModel::accept()` transactional path posts via `FinanceService`.

### Views
- `app/Views/transfer/market.php`

---

## 7) Player readiness / development / career history
**Status:** **IMPLEMENTED BUT PARTIALLY WIRED**

### What exists
- Daily readiness recovery and morale drift.
- Daily development/decline + market value recomputation.
- Post-match player updates.
- Player season stats + career history upsert integration.

### Exact files
- `app/Services/PlayerCareerService.php`
- `app/Services/MatchEngine.php`
- `app/Services/DailyCycleOrchestrator.php`
- `app/Models/PlayerModel.php`
- `database/schema.sql`
- `database/migrations/20260412_player_career_readiness_mvp.sql`

### Routes
- Indirect runtime via scheduler/match simulation.
- Player detail route exists: `GET /squad/player/{id}`.

### Runtime trigger points
- Daily cycle: `DailyCycleOrchestrator::run()` invokes readiness/development.
- Match simulation: `MatchEngine::updatePlayerStates()` calls post-match updates and career history sync.

### Views
- `app/Views/squad/player-detail.php` intended to show player stats.

### Partial aspects
- Player detail controller/view variable mismatch (`season_stats`/`career_stats` vs `$stats`, and `player['name']` expectation) reduces reliable UI visibility despite backend implementation.

---

## Corrected mini-status summary (these 7 areas only)
1. Youth academy/intake/origin: **FOUNDATION ONLY**
2. Facilities/effects/maintenance UI: **IMPLEMENTED AND WIRED**
3. Recurring economy: **IMPLEMENTED BUT PARTIALLY WIRED**
4. Awards/records/history visibility: **IMPLEMENTED BUT PARTIALLY WIRED**
5. Champions qualification automation: **IMPLEMENTED BUT PARTIALLY WIRED**
6. Transfer market core: **IMPLEMENTED AND WIRED**
7. Player readiness/development/career history: **IMPLEMENTED BUT PARTIALLY WIRED**
