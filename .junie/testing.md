# Sturdy Test Rules

## Definition

A test is valid only if it would fail when the underlying behavior is incorrect.

---

## REQUIRED

1. Every test MUST contain at least one meaningful assertion

2. Assertions MUST verify:
- returned data
- state change
- side effect

3. Tests MUST use:
- Fixtures for input/output
- Fakes for dependencies

4. Tests MUST cover:
- failure scenarios
- edge cases

5. Tests MUST be deterministic:
- no real time
- no randomness
- no external calls

---

## FORBIDDEN

- assertTrue(true)
- empty tests
- tests without assertions
- overuse of mocks
- testing private methods
- asserting implementation details

---

## STRUCTURE

- One behavior per test
- Clear naming: it_*

---

## VALIDATION RULE

Before accepting a test:

- If implementation is broken, does the test fail?

If NO → reject test
