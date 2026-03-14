# Test List — DoctrineRoomRepository (Integration)

**Requirement:** Reload a Room aggregate by its identifier using the Doctrine adapter and the snapshot pattern, against a real PostgreSQL database.
**Test Type:** INTEGRATION (KernelTestCase + real PostgreSQL — keywords: "persist", "Doctrine", "real database")
**Bounded Context:** Reservation Management
**Feature Area:** `DoctrineRoomRepository` — secondary adapter implementing `RoomRepositoryInterface`
**Agent:** tdd-analyze output — 2026-03-13

---

## Design framing

The interface is read-only: `RoomRepositoryInterface` exposes a single method, `findById(RoomId): ?Room`.
Rooms are seeded data — the application never creates or mutates them. There is no `save()` method.

Because there is no `save()` on the interface, the **given clause in every test must insert rows directly via
the EntityManager and `DoctrineRoomEntity`**. This is the correct pattern for read-only repositories.

**Retrieval flow:**

```
findById():
  em->find() → DoctrineRoomEntity → new RoomSnapshot(...) → Room::fromSnapshot()
```

**Given clause rule (from integration-patterns skill):** never use `Room::create()` or any named constructor
in the given. Seed the data through the raw infrastructure layer (`DoctrineRoomEntity` + `em->persist()` +
`em->flush()`). This expresses *data state*, not *business behavior*.

**Test isolation strategy:** transaction rollback — `beginTransaction()` in `setUp()`,
`rollback()` in `tearDown()`. No truncation needed; PostgreSQL rolls back all inserts atomically.

---

## Files to create

| File | Role |
|---|---|
| `src/Infrastructure/Adapters/Secondary/Doctrine/DoctrineRoomEntity.php` | Doctrine ORM entity — public properties, PHP 8.4 `#[ORM\...]` attributes, maps to `rooms` table |
| `src/Infrastructure/Adapters/Secondary/Doctrine/DoctrineRoomRepository.php` | Implements `RoomRepositoryInterface` using `EntityManagerInterface` + snapshot pattern |
| `migrations/Version20260313100000.php` | Doctrine migration — `CREATE TABLE rooms` |
| `tests/Integration/Infrastructure/Adapters/Secondary/Doctrine/DoctrineRoomRepositoryTest.php` | Integration test class (4 tests below) |

---

## SQL schema (CREATE TABLE rooms)

```sql
CREATE TABLE rooms (
    id           VARCHAR(255) NOT NULL PRIMARY KEY,
    capacity     INTEGER      NOT NULL,
    opening_time TIME         NOT NULL,
    closing_time TIME         NOT NULL
);
```

Column design rationale:
- `id VARCHAR(255)` — rooms use slug-style identifiers (`'eiffel'`, `'louvre'`, `'montmartre'`), not UUIDs
- `capacity INTEGER` — domain field is `int`
- `opening_time TIME` / `closing_time TIME` — time-of-day only, no date, no timezone; the domain uses
  `DateTimeImmutable` but only the time component is meaningful (hour + minute)
- Doctrine custom type `time_immutable` maps `TIME` ↔ `DateTimeImmutable` (built-in Doctrine type)

---

## Ordered Test List (TPP + FLFI)

| # | Status | Test |
|---|---|---|
| 1 | ✅ DONE | should make a room retrievable by its identifier when it has been seeded in the database |
| 2 | ✅ DONE | should return nothing when looking up a room identifier that does not exist in the database |
| 3 | ✅ DONE | should preserve the capacity and the operating hours of a room exactly when it is retrieved |
| 4 | ☐ | should return nothing for one room when only a different room has been seeded in the database |

---

## TPP + Contradiction Notes

| # | TPP | Contradiction introduced |
|---|---|---|
| 1 | nil → constant (2) | Baseline — `findById()` is scaffolded as returning `null`. The test asserts a non-null result, which forces `em->find()` + the full `DoctrineRoomEntity` ORM mapping + `Room::fromSnapshot()` reconstitution to exist. |
| 2 | unconditional → conditional (4) | If `findById()` naively returns a hardcoded `Room`, it passes Test 1 but returns something for an unknown ID. Forces `em->find()` to propagate `null` when no row exists. |
| 3 | constant → variable (3) | A hardcoded return cannot match the exact capacity and operating hours of an arbitrary seed row. Forces the complete snapshot round-trip: entity fields → `new RoomSnapshot(...)` → `Room::fromSnapshot()`, with each field read from the correct column. |
| 4 | unconditional → conditional (4) | A naive `findById()` that ignores the ID predicate would return the seeded room regardless. Forces the `WHERE id = ?` lookup to be ID-specific so that a different ID yields `null`. |

---

## Detailed test specifications

### Test 1 — should make a room retrievable by its identifier when it has been seeded in the database

**Given:** a `DoctrineRoomEntity` with `id = 'eiffel'`, `capacity = 8`,
`openingTime = 08:00`, `closingTime = 19:00` is persisted via `$em->persist()` + `$em->flush()`,
then `$em->clear()` evicts the identity map.

**When:** `repository->findById(new RoomId('eiffel'))` is called.

**Then:** the returned value is not null and its snapshot `id` equals `'eiffel'`.

**Drives:** `DoctrineRoomEntity` ORM mapping (table + columns) + `em->find()` + `Room::fromSnapshot()`.

---

### Test 2 — should return nothing when looking up a room identifier that does not exist in the database

**Given:** an empty `rooms` table (guaranteed by transaction rollback from `tearDown`).

**When:** `repository->findById(new RoomId('unknown-room'))` is called.

**Then:** the returned value is `null`.

**Drives:** `em->find()` returning `null` when no row matches the given ID.

---

### Test 3 — should preserve the capacity and the operating hours of a room exactly when it is retrieved

**Given:** a `DoctrineRoomEntity` with `id = 'louvre'`, `capacity = 20`,
`openingTime = 08:00`, `closingTime = 19:00` is persisted, then `$em->clear()`.

**When:** `repository->findById(new RoomId('louvre'))` is called and `toSnapshot()` is called on the result.

**Then:** all four snapshot fields match exactly:
- `snapshot->id === 'louvre'`
- `snapshot->capacity === 20`
- `snapshot->openingTime` has hour `8` and minute `0`
- `snapshot->closingTime` has hour `19` and minute `0`

**Drives:** the complete snapshot round-trip — each column must map to the correct `RoomSnapshot` field and
back; `DateTimeImmutable` time-component extraction must be correct.

> Note on time assertion: because `TIME` has no date and no timezone, assert on the formatted time string
> (`$snapshot->openingTime->format('H:i')`) rather than comparing `DateTimeImmutable` instances directly.

---

### Test 4 — should return nothing for one room when only a different room has been seeded in the database

**Given:** a `DoctrineRoomEntity` with `id = 'montmartre'` is persisted, then `$em->clear()`.

**When:** `repository->findById(new RoomId('eiffel'))` is called.

**Then:** the returned value is `null`.

**Drives:** the `WHERE id = ?` predicate must be ID-specific — not return the first row in the table.

---

## Integration test class structure (reference)

```php
// tests/Integration/Infrastructure/Adapters/Secondary/Doctrine/DoctrineRoomRepositoryTest.php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Adapters\Secondary\Doctrine;

use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\RoomRepositoryInterface;
use App\Infrastructure\Adapters\Secondary\Doctrine\DoctrineRoomEntity;
use App\Infrastructure\Adapters\Secondary\Doctrine\DoctrineRoomRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRoomRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private RoomRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository    = new DoctrineRoomRepository($this->entityManager);
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

    // helper: seed a room row directly via the ORM entity — never via Room::create()
    private function seedRoom(
        string $id,
        int $capacity = 8,
        string $openingTime = '08:00',
        string $closingTime = '19:00',
    ): void {
        $entity               = new DoctrineRoomEntity();
        $entity->id           = $id;
        $entity->capacity     = $capacity;
        $entity->openingTime  = new DateTimeImmutable($openingTime);
        $entity->closingTime  = new DateTimeImmutable($closingTime);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    // ... tests
}
```

---

## Design notes

**Existing code to reuse:**
- `App\Domain\Reservation\Room` — `fromSnapshot()` and `toSnapshot()` already implemented
- `App\Domain\Reservation\RoomSnapshot` — `final readonly class` with 4 fields: `id`, `capacity`, `openingTime`, `closingTime`
- `App\Domain\Reservation\RoomId` — `final readonly class` with `public string $value`
- `App\Domain\Reservation\RoomRepositoryInterface` — single method: `findById(RoomId): ?Room`
- Doctrine ORM already configured in `config/packages/doctrine.yaml` to scan `src/Infrastructure/Adapters/Secondary/Doctrine/` — no configuration change needed
- `phpunit.dist.xml` already declares an `Integration` test suite pointing at `tests/Integration/`
- `tests/Integration/Infrastructure/Adapters/Secondary/Doctrine/` already exists (DoctrineReservationRepositoryTest lives there)
- `migrations/Version20260313000000.php` pattern already established — follow exactly

**New code to create:**
1. `src/Infrastructure/Adapters/Secondary/Doctrine/DoctrineRoomEntity.php` — `#[ORM\Entity]`, `#[ORM\Table(name: 'rooms')]`; public properties: `string $id`, `int $capacity`, `DateTimeImmutable $openingTime`, `DateTimeImmutable $closingTime`; column type for times: `'time_immutable'` (built-in Doctrine type mapping `TIME` ↔ `DateTimeImmutable`)
2. `src/Infrastructure/Adapters/Secondary/Doctrine/DoctrineRoomRepository.php` — `implements RoomRepositoryInterface`; `findById()` uses `em->find()`, converts entity to `RoomSnapshot`, returns `Room::fromSnapshot()` or `null`
3. `migrations/Version20260313100000.php` — `CREATE TABLE rooms (id VARCHAR(255) PK, capacity INTEGER, opening_time TIME, closing_time TIME)` following the exact pattern of `Version20260313000000.php`

**Patterns observed (from DoctrineReservationRepository):**
- Doctrine mapping is via PHP 8.4 attributes (`#[ORM\...]`), not XML/YAML
- Doctrine entity namespace is `App\Infrastructure\Adapters\Secondary\Doctrine` — distinct from domain aggregate namespace
- Repository constructor: `public function __construct(private readonly EntityManagerInterface $em) {}`
- `em->find(DoctrineRoomEntity::class, $id->value)` for single-entity lookup
- Tests extend `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase`
- `$em->clear()` after seeding data evicts the identity map and forces a real DB read on retrieval
- Transaction rollback is the isolation strategy — no `DELETE FROM` needed
- Test file uses same namespace pattern: `App\Tests\Integration\Infrastructure\Adapters\Secondary\Doctrine`

**Key difference from DoctrineReservationRepository tests:**
- There is no `save()` on the interface — the given always seeds rows directly via `DoctrineRoomEntity`
- `TIME` column type requires Doctrine's `time_immutable` type, not `datetimetz_immutable`
- Time-component assertions must use `->format('H:i')`, not full `DateTimeInterface::ATOM` comparison

---

## Next step

```
use the tdd agent to implement: DoctrineRoomRepository — reload a Room aggregate by its identifier using the snapshot pattern — use the test list in docs/doctrine-room-repository-test-list.md
```
