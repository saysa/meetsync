# MeetSync — Enterprise Meeting Room Reservation

SaaS platform for managing meeting room reservations. Built with strict TDD discipline, Hexagonal Architecture, and DDD — every design decision is traceable through 284 commits.

```
83 tests | 142 assertions | MSI 100% | 0 DepTrac violations
```

---

## Architecture

```
                         ┌─────────────────────────────────────────┐
                         │            HTTP (Symfony)                │
                         │  POST /reservations                     │
                         │  DELETE /reservations/{id}               │
                         │  GET /reservations?organizer_id=...      │
                         └────────────────┬────────────────────────┘
                                          │ Primary Adapters
                         ┌────────────────▼────────────────────────┐
                         │          Application Layer              │
                         │  BookRoomUseCase                        │
                         │  CancelReservationUseCase               │
                         │  GetMyReservationsUseCase               │
                         └────────────────┬────────────────────────┘
                                          │ Ports (interfaces)
                         ┌────────────────▼────────────────────────┐
                         │           Domain Layer                  │
                         │  Reservation (aggregate)                │
                         │  Timeslot, Room, RoomId (value objects) │
                         │  Zero framework dependencies            │
                         └────────────────┬────────────────────────┘
                                          │ Secondary Adapters
                         ┌────────────────▼────────────────────────┐
                         │          Infrastructure                 │
                         │  DoctrineReservationRepository          │
                         │  DoctrineRoomRepository                 │
                         │  SymfonyMailerEmailNotifier             │
                         │  SystemClock                            │
                         └─────────────────────────────────────────┘
```

DepTrac enforces layer boundaries at CI level — Domain depends on nothing, Application on Domain only, Infrastructure on both.

---

## Methodology

```
Event Storming → BDD (Three Amigos) → Lean Startup scoping → TDD-analyze → TDD cycles → Mutation Testing
```

Each feature follows the full pipeline. Each TDD step is a separate commit (`go red` / `go green` / `clean`), visible in git history — no retroactive testing.

---

## Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.4 |
| Framework | Symfony 7.4 LTS |
| Database | PostgreSQL 13 |
| ORM | Doctrine 3.6 |
| Tests | PHPUnit 12 |
| Mutation Testing | Infection (MSI 100%) |
| Architecture | DepTrac (0 violations) |
| Runtime | Docker (PHP-FPM + Nginx) |

---

## API Endpoints

### POST /reservations — Book a room

```json
// Request
{ "room_id": "eiffel", "start": "2026-03-09T14:00:00+00:00",
  "end": "2026-03-09T15:30:00+00:00", "participant_count": 3,
  "organizer_email": "alice.martin@acme.com" }

// 201 Created
{ "reservation_id": "550e8400-e29b-41d4-a716-446655440000" }
```

| Error | Status |
|---|---|
| Room not found | 404 |
| Timeslot conflict | 409 |
| Capacity exceeded | 422 |
| Outside operating hours | 422 |
| Booking horizon exceeded (90d) | 422 |
| Insufficient advance notice (30min) | 422 |

### DELETE /reservations/{id} — Cancel a reservation

```json
// Request body
{ "requester_id": "alice.martin@acme.com",
  "requester_email": "alice.martin@acme.com" }

// 204 No Content
```

| Error | Status |
|---|---|
| Reservation not found | 404 |
| Not the organizer | 403 |
| Already started | 409 |

### GET /reservations?organizer_id=... — View my reservations

```json
// 200 OK
[{ "reservation_id": "...", "room_id": "eiffel",
   "start": "2026-03-09T14:00:00+00:00",
   "end": "2026-03-09T15:30:00+00:00", "status": "confirmed" }]
```

Returns only future reservations for the requesting organizer, ordered by start time.

---

## Project Structure

```
src/
├── Domain/                              # Zero dependencies
│   ├── Clock/ClockInterface.php         # Port
│   ├── Exception/                       # 8 domain exceptions
│   ├── Notification/EmailNotifierInterface.php  # Port
│   └── Reservation/                     # Aggregate + VOs
│       ├── Reservation.php              # Aggregate root (snapshot pattern)
│       ├── Timeslot.php                 # VO — half-open interval [start, end)
│       ├── Room.php, RoomId.php         # VO — capacity + operating hours
│       ├── ReservationId.php            # VO — UUID
│       └── *RepositoryInterface.php     # Ports
├── Application/
│   ├── Command/                         # BookRoomCommand, CancelReservationCommand
│   ├── Query/                           # GetMyReservationsQuery
│   ├── UseCase/                         # 3 use cases
│   └── Exception/                       # RoomNotFound, ReservationNotFound
└── Infrastructure/Adapters/
    ├── Primary/Http/                    # 3 controllers + DomainExceptionListener
    └── Secondary/
        ├── Doctrine/                    # Repositories + ORM entities
        ├── Mailer/                      # SymfonyMailerEmailNotifier
        └── Clock/                       # SystemClock
```

---

## Test Pyramid

| Level | Tests | What it validates |
|---|---|---|
| Unit | 50 | Domain logic, use cases (in-memory fakes) |
| Integration | 18 | Doctrine repositories, Symfony Mailer (real PostgreSQL) |
| E2E | 15 | HTTP request/response through full stack (WebTestCase) |
| **Total** | **83** | **142 assertions** |

Mutation testing (Infection) validates that every test actually catches regressions — not just line coverage.

---

## Quick Start

```bash
make test              # Full test suite (Docker + PostgreSQL)
make test-coverage     # Tests + HTML coverage report
make deptrac           # Verify architecture constraints
make shell             # Shell into the app container
```

Requires Docker and Docker Compose.

---

## Iteration 1 Scope (MVP)

**Hypothesis**: Will employees switch from Slack/email to a dedicated booking tool?

| IN | OUT |
|---|---|
| Book a room (conflict detection, capacity, operating hours) | Approval workflow |
| Cancel a reservation | Recurring reservations |
| View my reservations | Check-in / no-show |
| Email confirmation + cancellation | Waitlist, roles, reminders |
| Booking horizon (90d), min notice (30min) | Multi-tenancy, room admin UI |

Status lifecycle: `create -> CONFIRMED -> CANCELLED` (no PENDING, no approval engine).

Full spec: [`docs/meetsync-refined-spec.md`](docs/meetsync-refined-spec.md) | Gherkin: [`docs/meetsync-iteration1-gherkin.md`](docs/meetsync-iteration1-gherkin.md)

---

## TDD Commit Discipline

Every feature follows three separate commits visible in `git log`:

| Step | Commit prefix | Rule |
|---|---|---|
| RED | `test(...)` | Write the failing test. No production code. |
| GREEN | `feat(...)` | Minimum code to pass. No refactoring. |
| CLEAN | `refactor(...)` | Apply DDD patterns. No new behavior. |

Test ordering follows **TPP** (Transformation Priority Premise). Test names follow **FLFI** (Final Label First Implementation) — business language, no technical jargon.

---

## Documentation

| File | Content |
|---|---|
| [`docs/meetsync-refined-spec.md`](docs/meetsync-refined-spec.md) | Full spec (Three Amigos workshop output) |
| [`docs/meetsync-iteration1-gherkin.md`](docs/meetsync-iteration1-gherkin.md) | Iteration 1 Gherkin scenarios (10 features, 34 scenarios) |
| [`docs/event-storming-output.md`](docs/event-storming-output.md) | Event Storming domain model |
| `docs/*-test-list.md` | TPP-ordered test lists per feature (all marked DONE) |
