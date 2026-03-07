# Test List — Timeslot Value Object

**Requirement:** Half-open interval `[start, end)` with conflict detection and operating-hours validation
**Type:** UNIT · **BC:** Reservation Management
**Target:** `src/Domain/Reservation/Timeslot.php`
**Agent:** tdd-analyze output — 2026-03-06

---

## Files to create

| File | Role |
|---|---|
| `tests/Unit/Domain/Reservation/TimeslotTest.php` | Test class (write first) |
| `src/Domain/Reservation/Timeslot.php` | Value object (`final readonly class`) |
| `src/Domain/Exception/InvalidTimeslotException.php` | Domain exception |

---

## Ordered Test List (TPP + FLFI)

| # | Status | Test |
|---|---|---|
| 1 | ✅ DONE | should represent a valid booking interval when a start time is strictly before the end time |
| 2 | ✅ DONE | should reject a zero-duration timeslot when the start time equals the end time |
| 3 | ✅ DONE | should report no conflict between two timeslots when the first ends before the second begins |
| 4 | ✅ DONE | should report a conflict when two timeslots occupy the exact same interval |
| 5 | ✅ DONE | should report a conflict when a second timeslot starts inside an existing timeslot and ends after it |
| 6 | ✅ DONE | should report a conflict when a second timeslot starts before an existing timeslot and ends inside it |
| 7 | ✅ DONE | should report a conflict when a second timeslot completely contains an existing timeslot |
| 8 | ✅ DONE | should report no conflict when a second timeslot begins exactly at the moment an existing timeslot ends |
| 9 | ✅ DONE | should report no conflict when a second timeslot ends exactly at the moment an existing timeslot starts |
| 10 | ✅ DONE | should prevent a timeslot from being created when its start time falls before the building's opening time |
| 11 | ✅ DONE | should prevent a timeslot from being created when its end time falls after the building's closing time |
| 12 | NOT DONE | should allow a timeslot to be created when it starts exactly at the building's opening time |
| 13 | NOT DONE | should allow a timeslot to be created when it ends exactly at the building's closing time |

---

## TPP + Contradiction Notes

| # | TPP | Contradiction introduced |
|---|---|---|
| 1 | nil → constant (2) | Baseline — always succeeds |
| 2 | → conditional (4) | Forces `start >= end` guard |
| 3 | nil → constant (2) | Introduces `conflictsWith()`, returns `false` always |
| 4 | → conditional (4) | Contradicts permanent `false` |
| 5 | → conditional (4) | Forces generalization beyond equality check |
| 6 | → conditional (4) | Symmetric partial overlap case |
| 7 | → conditional (4) | Closes containment gap in formula |
| **8** | → conditional (4) | **Critical** — forces strict `<` in `A.start < B.end AND B.start < A.end` |
| 9 | → conditional (4) | Symmetric case of Test 8 |
| 10 | → conditional (4) | Introduces operating-hours validation |
| 11 | → conditional (4) | Closing-time guard |
| 12 | → conditional (4) | Forces `>=` inclusive on opening |
| 13 | → conditional (4) | Forces `<=` inclusive on closing |

---

## Next step

```
use the tdd-auto agent to implement: Timeslot value object with half-open interval conflict detection
```

Pass this file as reference:
```
use the tdd-auto agent to implement: Timeslot value object — use the test list in docs/timeslot-test-list.md
```
