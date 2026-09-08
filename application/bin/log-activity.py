#!/usr/bin/env python3
"""
Append a privacy-safe activity line to application/ACTIVITY-LOG.md. Driven by Codex
hooks so the work journal stays current automatically without storing prompts.

Usage (via hooks, hook JSON is read from stdin):
    python3 application/bin/log-activity.py prompt   # on UserPromptSubmit
    python3 application/bin/log-activity.py stop      # on Stop

It never raises and always exits 0, so it can't break a turn.
"""
import sys, os, json, datetime, subprocess

# Repo root = two levels up from this script (application/bin/ -> repo root).
ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", ".."))
LOG = os.path.join(ROOT, "application", "ACTIVITY-LOG.md")


def main() -> None:
    mode = sys.argv[1] if len(sys.argv) > 1 else "prompt"
    ts = datetime.datetime.now().strftime("%Y-%m-%d %H:%M")

    # Hook payload arrives as JSON on stdin (may be empty).
    try:
        data = json.load(sys.stdin)
    except Exception:
        data = {}

    line = None
    if mode == "prompt":
        if data.get("prompt"):
            line = f"- {ts}  ▶ request received (content not stored)"
    elif mode == "stop":
        try:
            out = subprocess.run(
                ["git", "-C", ROOT, "status", "--short"],
                capture_output=True, text=True, timeout=10,
            ).stdout
        except Exception:
            out = ""
        rows = [r for r in out.splitlines() if r.strip()]
        if rows:
            files = ", ".join(r[3:] for r in rows[:12])
            extra = "" if len(rows) <= 12 else f" (+{len(rows) - 12} more)"
            line = f"  ↳ {ts}  done — {len(rows)} file(s) changed: {files}{extra}"
        else:
            line = f"  ↳ {ts}  done — no file changes"

    if line:
        try:
            os.makedirs(os.path.dirname(LOG), exist_ok=True)
            with open(LOG, "a", encoding="utf-8") as f:
                f.write(line + "\n")
        except Exception:
            pass


if __name__ == "__main__":
    try:
        main()
    except Exception:
        pass
    sys.exit(0)
