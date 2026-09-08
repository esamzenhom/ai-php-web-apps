---
name: work-continuity
description: >
  How the assistant never loses progress if a session is interrupted (the owner's
  tokens/credits run out mid-task, the terminal closes, the network drops, or
  the context is cut off). Use this skill at the START of any multi-step task,
  while working through one, and whenever a new session begins — so the assistant can
  pick up exactly where it left off when the owner returns. The owner is
  non-technical and must never be left with half-finished, unexplained work.
---

# Never lose work — resume exactly where you left off

A session can end at any moment (credits run out, terminal closed, connection
dropped). To make that safe, keep a running **work journal** at
`application/PROGRESS.md` so any future session can continue seamlessly.

## Two layers
- `application/PROGRESS.md` — the **curated** plan you write (goal, steps, next action).
- `application/ACTIVITY-LOG.md` — a **privacy-safe automatic** breadcrumb trail written
  by hooks on every prompt + reply (timestamps and changed filenames only; prompt
  contents are never stored). It updates even if you forget. On resume, read BOTH:
  PROGRESS.md for intent and ACTIVITY-LOG.md for the latest file activity.

## At the START of every session (before new work)
1. Open `application/PROGRESS.md` (and skim the tail of `application/ACTIVITY-LOG.md`).
2. If `Status: IN PROGRESS`, the previous session was interrupted. **Do not
   silently start over.** Instead:
   - Tell the owner in plain words what was happening and where it stopped:
     *"Last time I was adding the booking form. I'd created the database table
     and the page, and was about to wire up the email step. Want me to
     continue?"*
   - **Re-check reality before continuing** — state may be partial: run
     `git status`, look at the files mentioned, check whether a migration was
     applied (`make migrate` is safe to re-run), whether the stack is up
     (`make up`), tests/health pass. Never assume a half-done step finished.
   - Then continue from **Next action**, or adjust with the owner.
3. If `Status: IDLE` (or the file is absent), there's nothing pending — proceed
   normally.

## When you START a non-trivial task (multi-step / will take a while)
Immediately write the plan to `application/PROGRESS.md` *before* doing the work, so
it's saved even if you're cut off on the first step:
- the **Goal** (one line, plain English),
- a **checklist** of steps,
- **Notes for resuming** (anything fragile: "migration 003 created but NOT yet
  applied", "Mail.php half-written"),
- the **Next action**.

## WHILE working
After each meaningful step, update `application/PROGRESS.md`: tick the step done,
move the `← in progress` marker, refresh **Next action**, and record anything a
fresh session would need to know. Cheap to update; priceless if interrupted.

## When the task is DONE
Set `Status: IDLE`, clear the checklist/notes (or write "Nothing in progress").
Don't leave a stale plan that looks unfinished.

## Format (`application/PROGRESS.md`)
```
# Work in progress
Status: IN PROGRESS | IDLE
Updated: <date>

## Goal
<one plain-English line>

## Steps
- [x] done step
- [ ] current step   ← in progress
- [ ] next step

## Notes for resuming
- <fragile/partial state a fresh session must know>

## Next action
<the very next concrete thing to do>
```

## Also remind the owner (once, if relevant)
Codex can also restore the previous **conversation** directly:
`codex --continue` (resume the most recent) or `codex --resume` (pick one).
Claude Code uses `claude --continue` or `claude --resume` instead.
The journal above is the belt-and-braces backup that works even in a brand-new
session. Keep the journal plain-English so the owner can read it too.
