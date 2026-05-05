# Anti-Hallucination Rules

## Core Principle
The system must not invent or assume any implementation detail.

## Absolute Rules

1. No Guessing
- If information is missing → STOP
- Do not infer or approximate behavior

2. No Invention
- Do not create methods, classes, or APIs unless explicitly required
- Do not assume framework helpers exist

3. Codebase Anchoring
Every used element must:
- exist in the repository
- or be explicitly defined in the task

4. Verification Required
Before final output:
- List all external methods/classes used
- Show their origin (file path)

5. Failure Condition
If any assumption is made:
→ Output is invalid

## Allowed Behavior

- Completing existing code
- Extending clearly defined patterns
- Implementing explicitly described structures

## Forbidden Behavior

- "Best practice" additions not requested
- "Helpful" abstractions
- Filling missing architecture
- Creating infrastructure not present

## Stop Condition

If any required dependency is missing:
- Report missing item
- Stop execution

---

# Agent Execution Rules

## Scope Enforcement

Agents must ONLY modify:
- explicitly listed files
- explicitly listed directories

Any modification outside scope:
→ Task failure

---

## Deterministic Execution

Agents must:

- Execute only what is explicitly requested
- Not expand scope
- Not refactor unless asked
- Not improve architecture unless asked

---

## No Generalization Rule

Agents are NOT allowed to:

- Generalize patterns
- Introduce new abstractions
- Reorganize structure
- Apply "standard practices" automatically

---

## Code Requirements

- Follow existing project patterns exactly
- Reuse existing classes and APIs
- No duplicate implementations

---

## Verification Phase (MANDATORY)

Before final output:

1. List all used classes and methods
2. Confirm they exist in the repository
3. Confirm no new assumptions were made

If verification fails:
→ Fix before output

---

## Failure Definition

The task is considered FAILED if:

- Any file outside scope is modified
- Any method is invented
- Any assumption is made
- Any unrelated change is introduced

---

# Copilot / Codex Instructions

## Behavior Mode

Operate in STRICT MODE.

---

## STRICT MODE Rules

1. Do not guess
2. Do not invent
3. Do not generalize
4. Do not extend beyond instructions

---

## Implementation Rules

- Only implement explicitly described functionality
- Only use existing codebase patterns
- Do not introduce new architecture

---

## Missing Information Handling

If something is unclear or missing:

- State what is missing
- Do not proceed
- Do not approximate

---

## Output Constraints

- Keep changes minimal
- Keep scope contained
- Do not touch unrelated files

---

## Forbidden Actions

- Modifying build tools (yarn, npm, vite, etc.)
- Modifying CI/CD
- Creating new infrastructure
- Refactoring unrelated code

---

## Priority Order

1. Correctness
2. Adherence to instructions
3. Minimal change surface

Never prioritize completeness over correctness
