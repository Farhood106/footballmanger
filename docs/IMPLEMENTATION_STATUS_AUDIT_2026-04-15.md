# Football Manager Codebase Implementation-Status Audit
_Date: 2026-04-15_

## 1) Executive summary
- **Current MVP type:** This is a **backend-heavy operations MVP** with significant simulation/admin scaffolding and broad domain coverage (competitions, season lifecycle, finance ledger, transfers, manager hiring, governance, facilities, history). It is **not yet a stable player-facing product MVP** because several user-facing routes/views are not wired to the data shape they render.  
- **What is strong:** Admin world controls (competition/season/rollover/qualification), daily orchestration, finance centralization pattern, and broad domain schema presence are all substantial.  
- **What is weak:** Runtime schema patching is still embedded in services/models; multiple UI/controller mismatches likely break pages; missing migration coverage for some runtime-created tables; tests are mostly fragment/presence checks rather than behavioral/integration guarantees.  
- **Structural coherence:** The project is **partially coherent** at service/domain level but still **fragmented at runtime UX/integration level**.

## 2) Feature implementation audit

### A. Competition / World Structure — **PARTIALLY USABLE**
- Present: competitions/divisions, seasons, participant assignment with entry_type, fixture generation (double round robin), rollover preview/finalize/apply, and champions qualification slot pipeline are implemented in `AdminCompetitionService` + admin controller/view/routes.  
- End-to-end: **Admin path mostly wired** (`/admin/competitions`, season ops, qualification routes).  
- Usability: Admin usability exists, but player-facing competition pages have controller/view mismatches (missing passed variables, wrong field names like `matchday`, `home_goals` vs schema `week`, `home_score`).  
- Gaps: Cup logic is not implemented (league-style only), rollover reward logic has fragile champion selection and duplicated inserted counter.

### B. Match / Daily Cycle / Runtime — **PARTIALLY USABLE**
- Present: `DailyCycleOrchestrator` drives due-match simulation, per-club cycle state, lineup validation/locking, AI fallback lineup, snapshot logging, salary/facility/development daily hooks. Admin match-ops includes repair/rerun/reset/sync cycle.  
- End-to-end: backend scheduler/orchestrator + admin tooling are wired; CLI scheduler exists.
- Usability: operationally usable for admins; player runtime surfaces are weaker and some match detail UI is disconnected from controller payload shape.
- Gaps: no robust queue/cron orchestration guarantees, no replay/audit table for admin ops in schema, and limited stuck-state auto-repair policy.

### C. Player System — **PARTIALLY USABLE**
- Present: fitness/morale_score readiness loop, post-match updates, daily development/decline signal, market value recomputation, season stats + career history upsert, injury events in match simulation.  
- End-to-end: simulation->player updates->career history is wired.
- Usability: visible in squad/player stats paths partially; little explicit UI for readiness/development analytics.
- Gaps: no youth intake generation pipeline, no academy-origin tagging workflow, no nuanced happiness/playing-time behavior.

### D. Transfer Market — **PARTIALLY USABLE**
- Present: listing/unlisting with asking price, bidding, accept/reject, finance-safe completion with transfer ledger postings and seller/buyer consistency checks.
- End-to-end: route/controller/model/view are present and mostly connected.
- Usability: basic transfer flow works for listed players and incoming offers.
- Gaps: no counter-offers/negotiation depth, no AI transfer behavior, no contract/wage negotiation integration, no installments/clauses.

### E. Contracts / Human Management — **PARTIALLY USABLE**
- Present: owner expectations, manager applications, rejection reasons, offer/counter/reject/accept negotiation flow, manager contract activation, governance case handling.
- End-to-end: routes/controllers/models/views exist for core paths.
- Usability: functional but fragile transaction handling (double commit bug in counter path) and limited lifecycle depth (termination/replacement policies basic).
- Gaps: richer clauses, arbitration depth, automatic vacancy resolution workflow depth.

### F. Club / Economy / Business — **PARTIALLY USABLE**
- Present: centralized `FinanceService::postEntry`, owner funding, sponsor management+income posting, coach salary cycle posting, season rewards, governance/transfer/facility financial routing.
- End-to-end: finance UI + ledger view present.
- Usability: usable for owner/admin operations.
- Gaps: no debt/arrears/cashflow stress model, no wage budget controls for players/staff beyond coach salary, no recurring operating economics beyond facilities/coach.

### G. Club Facilities / Infrastructure — **PARTIALLY USABLE**
- Present: 4 facility types, upgrade/downgrade, maintenance posting, gameplay bonuses (recovery/development/youth/presige foundation), owner/admin permissions.
- End-to-end: routes/controllers/views wired, dashboard overview included.
- Usability: usable MVP management panel.
- Gaps: effects remain shallow and mostly numeric modifiers; no stadium revenue coupling; no deterioration/failure events.

### H. Club Identity / History — **PARTIALLY USABLE**
- Present: player awards (match/week/season), club honors, records, legends and display pages.
- End-to-end: service calls wired from match simulation and season finalization; history routes/views/dashboard cards exist.
- Usability: visible and usable where routes work.
- Gaps: no migration/schema declarations for these history tables (runtime-created only), no historical timelines/filters, no league-wide hall-of-fame UI.

### I. AI / World Continuity — **FOUNDATION ONLY**
- Present: control-state model (AI owner/caretaker), vacancy state tracking, AI lineup fallback, basic daily prep adjustments.
- End-to-end: admin visibility and orchestrator integration exist.
- Usability: continuity foundations exist but strategic AI behavior is minimal.
- Gaps: no AI transfer strategy, no hiring/contract strategy, no long-horizon squad planning, no market participation.

### J. UI / Runtime Usability — **FOUNDATION ONLY**
- Present: broad route/view coverage exists across major modules.
- End-to-end: several critical pages are not coherently wired (controller payloads don't match view expectations; nav links assume competition `1`; mixed Farsi/English UX).
- Usability: this is the biggest blocker for “playable product” feel.
- Gaps: navigation IA, consistency, error handling, and integration quality.

## 3) Backend vs UI coverage matrix
| Feature area | Backend/service logic | Runtime trigger | Controller/route | View/UI | Actually usable |
|---|---|---|---|---|---|
| Competition admin ops | Yes | Admin actions | Yes | Yes | **Mostly yes (admin)** |
| Player-facing competition pages | Partial | N/A | Yes | Yes (mismatched) | **Likely broken/partial** |
| Match simulation | Yes (`MatchEngine`) | Orchestrator/scheduler/admin rerun | Partial direct player trigger | Match detail exists (mismatch) | **Admin-usable; player flow partial** |
| Daily cycle state | Yes | Scheduler run | Admin controller | Admin page | **Yes (admin)** |
| Transfer core | Yes | User POST | Yes | Yes | **Yes (basic)** |
| Contracts/negotiation | Yes | User POST workflows | Yes | Yes | **Partial, fragile** |
| Governance | Yes | User/Admin POST | Yes | Yes | **Partial** |
| Finance ledger/funding/sponsors | Yes | User/Admin + scheduler | Yes | Yes | **Yes (owner/admin)** |
| Facilities | Yes | User/Admin + scheduler | Yes | Yes | **Yes (owner/admin)** |
| History (awards/honors) | Yes | Match+season events | Yes | Yes | **Partial (runtime-DDL risk)** |
| AI continuity | Basic | Orchestrator/admin sync | Admin visibility | Yes | **Foundation** |

## 4) Schema / migration / runtime integrity audit

### High-risk integrity issues
1. **Runtime DDL embedded in hot-path classes** (models/services create/alter tables/columns on construct/use). This exists in manager applications, transfers, finance, facilities, player career, AI control state, admin competition, and world history. Risk: startup races, hidden drift across environments, non-repeatable deployments.
2. **Schema-missing but runtime-required tables:** `player_awards`, `club_honors`, `club_records`, `club_legends` are not in canonical schema/migrations and rely on runtime creation.
3. **Admin ops logging table usage without schema definition:** `AdminMatchOperationsService` writes to `admin_operation_logs`, but no schema/migration/table creation exists.
4. **Controller-view field mismatches likely runtime errors:**
   - `CompetitionController` provides `season`, `standings`, `fixtures` but views expect `competition`, `userClubId`, `matchday` etc.
   - `MatchController@detail` passes only `match`, while view expects separate `events`, `matchStats`, and grouped `ratings` arrays.
5. **Fragile transaction bugs:**
   - `ManagerApplicationModel::respondToOffer` counter path commits twice.
   - `AdminCompetitionService::applyChampionsQualification` increments `inserted` twice.
6. **Business logic anomaly:** champion reward/honor in rollover uses `direct[0]` instead of true standings champion context.

### Severity summary
- **Critical:** missing schema for used tables, controller/view runtime mismatches, admin_operation_logs missing table.
- **High:** runtime DDL sprawl, transaction anomalies.
- **Medium:** reward/champion edge logic, mixed locale/UX consistency.

## 5) Test coverage audit
- Tests are numerous and helpful as **contract/presence checks**, but most are static string-fragment scans of source files, not execution against a real DB/runtime path.
- A few behavioral checks exist (round-robin count, formula sanity, static helpers), but there is very little end-to-end verification for controller->DB->view consistency, transaction rollback behavior, or schema migration integrity.
- Practical confidence level:
  - **High** confidence that specific keywords/guards exist.
  - **Low-to-medium** confidence that runtime paths truly work in browser + DB environments.

## 6) Completion assessment (realistic)
- **Core gameplay/backend foundation:** ~60–70%
- **Runtime usability/productization:** ~35–45%
- **Polish/UI maturity:** ~25–35%

Rationale: service-layer breadth is strong, but UX integration defects and runtime schema governance issues materially reduce deployable quality.

## 7) Remaining gaps / missing systems
- Playing-time happiness / squad role satisfaction system.
- Deeper training planning and tactical adaptation loops.
- Transfer counter-offer depth on both buyer/seller + AI market participation.
- Rich contract clauses (release clauses, performance bonuses, optional years, buyout logic).
- Debt/arrears/financial pressure model.
- Stronger AI strategic behavior (hiring, squad planning, transfer priorities).
- Richer facility effects (ticketing, injury risk, scouting/youth generation).
- Navigation/UX cleanup and unified language/localization.
- Deployment hardening around migrations and schema consistency.

## 8) Technical debt / hardening backlog (priority)
1. **P0**: Move all runtime DDL to formal migrations; remove `ensure*Table/Column` from constructors and request paths.
2. **P0**: Add missing migrations for history and admin operation log tables; align canonical `schema.sql` with runtime expectations.
3. **P0**: Fix controller/view payload mismatches for competition and match detail pages; add integration tests for these routes.
4. **P1**: Fix transaction defects (double commit, counter anomalies) and add tests for rollback/idempotency.
5. **P1**: Add DB-backed integration tests (or test DB harness) for transfer accept flow, season finalize/apply, negotiation accept/counter.
6. **P1**: Remove duplicated logic and align naming conventions (`week` vs `matchday`, `home_score` vs `home_goals`) across models/views.
7. **P2**: Improve observability (structured logs around scheduler/orchestrator and admin ops).
8. **P2**: Evaluate scalability bottlenecks (per-player loops and per-row queries in daily jobs).

## 9) Recommended roadmap

### NEXT 3 HIGH-PRIORITY BUILDS
1. **UI-runtime integration repair sprint**
   - Fix competition and match detail controller-view contracts, add smoke route tests, correct nav assumptions.
2. **Migration + schema hardening sprint**
   - Introduce missing migrations/tables; retire runtime DDL from services/models.
3. **Transaction correctness sprint**
   - Patch known transactional bugs; enforce idempotency and rollback tests on negotiation/transfer/rollover paths.

### IMPORTANT BUT LATER
- AI market and hiring strategy depth.
- Expanded contract clauses and termination/dispute outcomes.
- Advanced economy model (debt, recurring OPEX, wage pressure).
- Youth intake pipeline tied to academy level.

### UI/UX HARDENING PHASE
- Unified information architecture and menu hierarchy.
- Consistent bilingual/localized text strategy.
- Better error surfaces and action feedback.
- Dedicated screens for readiness/career analytics and financial health.

### TECHNICAL DEBT / STABILIZATION PHASE
- End-to-end integration test suite.
- Query efficiency pass for daily batch jobs.
- Formal operational runbook for scheduler and repair tooling.
- Data integrity constraints and seed/migration reproducibility checks.

## 10) Final verdict
This codebase is **a serious systems foundation but not yet a serious product MVP**. The architecture has enough depth to build on, but runtime coherence is still fragmented due to UI wiring issues and schema governance debt. The most valuable next work is **hardening + usability integration**, not adding large new gameplay subsystems first.
