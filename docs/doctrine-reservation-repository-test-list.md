# Test List — DoctrineReservationRepository (Integration)

**Requirement:** Persist and reload a confirmed Reservation aggregate using the Doctrine adapter and the snapshot pattern, against a real PostgreSQL database.
**Test Type:** INTEGRATION (KernelTestCase + real PostgreSQL — keyword: "persist and reload", "Doctrine", "real database")
**Bounded Context:** Reservation Management
**Feature Area:** `DoctrineReservationRepository` — secondary adapter implementing `ReservationRepositoryInterface`
**Agent:** tdd-analyze output — 2026-03-13

---

## Design framing

The hexagon is complete. All 42 unit tests pass with in-memory fakes. The next layer is the
Doctrine secondary adapter that bridges the domain aggregate with PostgreSQL.

**Persistence flow (both directions):**

```
save():
  Reservation → toSnapshot() → DoctrineReservationEntity → em->persist() → em->flush()

reload():
  em->find() → DoctrineReservationEntity → new ReservationSnapshot(...) → Reservation::fromSnapshot()
```

**Test isolation strategy:** transaction rollback — `beginTransaction()` in `setUp()`,
`rollback()` in `tearDown()`. No truncation needed; PostgreSQL rolls back all inserts atomically.

**Given clause rule (from integration-patterns skill):** always use `Reservation::fromSnapshot(new ReservationSnapshot(...))` in the given, never `Reservation::create()`. The given expresses *data state*, not *business behavior*.

---

## Files to create

| File | Role |
|---|---|
| `src/Infrastructure/Adapters/Secondary/Doctrine/DoctrineReservationEntity.php` | Doctrine ORM entity — public properties, PHP 8.4 `#[ORM\...]` attributes, maps to `reservations` table |
| `src/Infrastructure/Adapters/Secondary/Doctrine/DoctrineReservationRepository.php` | Implements `ReservationRepositoryInterface` using `EntityManagerInterface` + snapshot pattern |
| `tests/Integration/Infrastructure/Adapters/Secondary/Doctrine/DoctrineReservationRepositoryTest.php` | Integration test class (8 tests below) |
| `migrations/Version<timestamp>_CreateReservationsTable.php` | Doctrine migration creating `reservations` table |

---

## SQL schema (CREATE TABLE reservations)

```sql
CREATE TABLE reservations (
    id          VARCHAR(36)              NOT NULL PRIMARY KEY,
    room_id     VARCHAR(255)             NOT NULL,
    organizer_id VARCHAR(255)            NOT NULL,
    status      VARCHAR(50)              NOT NULL,
    start_at    TIMESTAMP WITH TIME ZONE NOT NULL,
    end_at      TIMESTAMP WITH TIME ZONE NOT NULL
);
```

Column naming rationale:
- `start_at` / `end_at` — avoids PostgreSQL reserved word `start`
- `TIMESTAMP WITH TIME ZONE` — preserves UTC offset; domain uses `DateTimeImmutable`
- `VARCHAR(36)` for `id` — UUID v4 RFC 4122 format (produced by `ReservationId::generate()`)

---

## Ordered Test List (TPP + FLFI)

| # | Status | Test |
|---|---|---|
| 1 | ✅ DONE | should make a confirmed reservation retrievable by its identifier after it has been recorded |
| 2 | ✅ DONE | should return nothing when looking up a reservation identifier that has never been recorded |
| 3 | ✅ DONE | should preserve the organizer, the room, and the exact time window of a confirmed reservation when it is stored and retrieved |
| 4 | NOT DONE | should preserve the cancelled status of a reservation when it is stored and retrieved |
| 5 | NOT DONE | should return all reservations for a given room when multiple reservations have been recorded |
| 6 | NOT DONE | should return an empty list when no reservations have been recorded for a given room |
| 7 | NOT DONE | should return all reservations for a given organizer when multiple reservations have been recorded |
| 8 | NOT DONE | should return an empty list when no reservations have been recorded for a given organizer |

---

## TPP + Contradiction Notes

| # | TPP | Contradiction introduced |
|---|---|---|
| 1 | nil → constant (2) | Baseline — `save()` + `findById()` are both scaffolded as no-ops; only when `save()` persists a row and `findById()` retrieves it can the assertion pass. Drives `DoctrineReservationEntity`, `em->persist()`/`em->flush()`, `em->find()`. |
| 2 | unconditional → conditional (4) | If `findById()` naively returns any row, it will return a row for an unknown ID. Forces a proper `em->find()` returning `null` for missing IDs. |
| 3 | constant → variable (3) | A hardcoded return in `findById()` cannot match different UUIDs or verify individual field values. Forces the full snapshot round-trip: `toSnapshot()` → entity fields → new `ReservationSnapshot(...)` → `Reservation::fromSnapshot()`. |
| 4 | unconditional → conditional (4) | A naive adapter always writes `status = 'CONFIRMED'`. A cancelled reservation must survive the round-trip with `status = 'CANCELLED'`, forcing the `status` field to be read from the snapshot and stored verbatim. |
| 5 | scalar → collection (5) | `findByRoomId()` returns a scalar result so far. A room with multiple reservations requires iterating over all matching rows and reconstructing each aggregate — forces a Doctrine query with a `WHERE room_id = ?` clause and a loop. |
| 6 | unconditional → conditional (4) | A naive `findByRoomId()` might return all rows. Forces the WHERE clause to filter correctly, including the empty-result case when the table has rows for other rooms. |
| 7 | scalar → collection (5) | Same collection-iteration forcing as Test 5, applied to `findByOrganizerId()` — forces a `WHERE organizer_id = ?` query and loop. |
| 8 | unconditional → conditional (4) | Empty-result guard for `findByOrganizerId()`, symmetric with Test 6. |

---

## Detailed test specifications

### Test 1 — should make a confirmed reservation retrievable by its identifier after it has been recorded

**Given:** a `Reservation` built via `fromSnapshot()` with a known UUID, roomId `'eiffel'`, organizerId `'alice@example.com'`, status `'CONFIRMED'`, start `2026-03-09 10:00:00 UTC`, end `2026-03-09 11:00:00 UTC` — saved via `repository->save()`, then `$em->clear()` to evict the identity map.

**When:** `repository->findById(new ReservationId('<uuid>'))` is called.

**Then:** the returned value is not null and its snapshot `id` matches the original UUID.

**Drives:** `DoctrineReservationEntity` ORM entity + `em->persist()` + `em->flush()` in `save()` + `em->find()` in `findById()`.

---

### Test 2 — should return nothing when looking up a reservation identifier that has never been recorded

**Given:** an empty `reservations` table (guaranteed by transaction rollback from previous test tearDown).

**When:** `repository->findById(new ReservationId('00000000-0000-0000-0000-000000000000'))` is called.

**Then:** the returned value is `null`.

**Drives:** `em->find()` returning `null` when the row does not exist.

---

### Test 3 — should preserve the organizer, the room, and the exact time window of a confirmed reservation when it is stored and retrieved

**Given:** a `Reservation` from `fromSnapshot()` with all fields set to specific values — `id`, `roomId = 'eiffel'`, `organizerId = 'alice@example.com'`, `status = 'CONFIRMED'`, `start = 2026-03-09 10:00:00 UTC`, `end = 2026-03-09 11:00:00 UTC` — saved and identity-map cleared.

**When:** `repository->findById(...)` is called and `toSnapshot()` is called on the result.

**Then:** all six snapshot fields match exactly — `id`, `roomId`, `organizerId`, `status`, `start`, `end`.

**Drives:** the complete snapshot round-trip: each entity column must map to the correct snapshot field and back; `DateTimeImmutable` timezone preservation must hold.

---

### Test 4 — should preserve the cancelled status of a reservation when it is stored and retrieved

**Given:** a `Reservation` from `fromSnapshot()` with `status = 'CANCELLED'` — saved and identity-map cleared.

**When:** `repository->findById(...)` is called and `toSnapshot()` is called on the result.

**Then:** `snapshot->status === 'CANCELLED'`.

**Drives:** the `status` column must be persisted from the snapshot verbatim (not hardcoded as `'CONFIRMED'`).

---

### Test 5 — should return all reservations for a given room when multiple reservations have been recorded

**Given:** two reservations for room `'eiffel'` (different IDs, different time windows) and one reservation for room `'louvre'` — all saved and identity-map cleared.

**When:** `repository->findByRoomId(new RoomId('eiffel'))` is called.

**Then:** exactly two reservations are returned; both have `roomId = 'eiffel'` in their snapshots.

**Drives:** `findByRoomId()` DQL/SQL query with `WHERE room_id = ?` + loop producing a `list<Reservation>`.

---

### Test 6 — should return an empty list when no reservations have been recorded for a given room

**Given:** one reservation for room `'louvre'` — saved and identity-map cleared.

**When:** `repository->findByRoomId(new RoomId('eiffel'))` is called.

**Then:** the result is an empty array.

**Drives:** the WHERE clause must filter by room ID correctly — not return all rows.

---

### Test 7 — should return all reservations for a given organizer when multiple reservations have been recorded

**Given:** two reservations organized by `'alice@example.com'` (different rooms and time windows) and one reservation organized by `'bob@example.com'` — all saved and identity-map cleared.

**When:** `repository->findByOrganizerId('alice@example.com')` is called.

**Then:** exactly two reservations are returned; both have `organizerId = 'alice@example.com'` in their snapshots.

**Drives:** `findByOrganizerId()` query with `WHERE organizer_id = ?` + loop.

---

### Test 8 — should return an empty list when no reservations have been recorded for a given organizer

**Given:** one reservation organized by `'bob@example.com'` — saved and identity-map cleared.

**When:** `repository->findByOrganizerId('alice@example.com')` is called.

**Then:** the result is an empty array.

**Drives:** the WHERE clause must filter by organizer ID correctly — not return all rows.

---

## Integration test class structure (reference)

```php
// tests/Integration/Infrastructure/Adapters/Secondary/Doctrine/DoctrineReservationRepositoryTest.php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Adapters\Secondary\Doctrine;

use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\ReservationSnapshot;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\Reservation;
use App\Infrastructure\Adapters\Secondary\Doctrine\DoctrineReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineReservationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ReservationRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository    = new DoctrineReservationRepository($this->entityManager);

        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        $this->entityManager->close();
        parent::tearDown();
    }

    // helper: build a Reservation from known data — fromSnapshot() only, never create()
    private function aConfirmedReservation(
        string $id,
        string $roomId = 'eiffel',
        string $organizerId = 'alice@example.com',
        string $start = '2026-03-09 10:00:00',
        string $end = '2026-03-09 11:00:00',
    ): Reservation {
        return Reservation::fromSnapshot(new ReservationSnapshot(
            id: $id,
            roomId: $roomId,
            organizerId: $organizerId,
            status: 'CONFIRMED',
            start: new \DateTimeImmutable($start),
            end: new \DateTimeImmutable($end),
        ));
    }

    // ... tests
}
```

---

## Design notes

**Existing code to reuse:**
- `App\Domain\Reservation\Reservation` — `fromSnapshot()`, `toSnapshot()` already implemented
- `App\Domain\Reservation\ReservationSnapshot` — `final readonly class` with 6 fields
- `App\Domain\Reservation\ReservationId` — wraps a UUID string
- `App\Domain\Reservation\RoomId` — wraps a string
- `App\Domain\Reservation\ReservationRepositoryInterface` — 4 methods: `save()`, `findById()`, `findByRoomId()`, `findByOrganizerId()`
- Doctrine ORM already configured in `config/packages/doctrine.yaml` to scan `src/Infrastructure/Adapters/Secondary/Doctrine/` for attribute-mapped entities
- `phpunit.dist.xml` already declares an `Integration` test suite pointing at `tests/Integration/`

**New code to create:**
1. `src/Infrastructure/Adapters/Secondary/Doctrine/DoctrineReservationEntity.php` — Doctrine entity with `#[ORM\Entity]`, `#[ORM\Table(name: 'reservations')]`, public typed properties with column attributes
2. `src/Infrastructure/Adapters/Secondary/Doctrine/DoctrineReservationRepository.php` — implements `ReservationRepositoryInterface`; `save()` converts to entity then `em->persist()` + `em->flush()`; `findById()` / `findByRoomId()` / `findByOrganizerId()` query and reconstitute via `Reservation::fromSnapshot()`
3. A Doctrine migration (or raw SQL migration) creating the `reservations` table

**Patterns observed:**
- Doctrine mapping is via PHP 8.4 attributes (`#[ORM\...]`), not XML/YAML
- Doctrine entity namespace is `App\Infrastructure\Adapters\Secondary\Doctrine` — distinct from domain aggregate namespace
- Tests extend `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase`
- `$em->clear()` after `save()` + `flush()` evicts the identity map and forces a real DB read on reload
- Transaction rollback is the isolation strategy — no `DELETE FROM` needed
- The integration skill mandates `fromSnapshot()` (not `create()`) in the given of every test

---

## Next step

```
use the tdd agent to implement: DoctrineReservationRepository — persist and reload a confirmed Reservation aggregate using the snapshot pattern — use the test list in docs/doctrine-reservation-repository-test-list.md
```
