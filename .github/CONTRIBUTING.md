# Contributing

Read [AGENTS.md](../AGENTS.md) and the shared
[assistant guide](../application/docs/ASSISTANT-GUIDE.md) before changing the stack.
Codex and Claude share the canonical skills in `.agents/skills/`; keep the relative
links in `.claude/skills/` intact.

## Develop safely

Use a disposable checkout and synthetic accounts, keys and records. Never test
resets or migrations against someone's working website. Keep generated website
code, runtime storage, backups, local environment files and chat logs out of commits,
issues, screenshots and pull requests. Do not force-add ignored private files.

Preserve the local bind storage, website/admin isolation, explicit change review,
black-and-white interface and plain-language warnings required by the
[product contract](../application/docs/PRODUCT-CONTRACT.md).

## Check a change

Run from the repository root:

```sh
python3 application/tests/packaging.py
python3 application/tests/start-stop.py
node application/tests/home-chat.cjs
git diff --check
```

For runtime, authentication, database or worker changes, run the disposable Docker
suite: `python3 application/tests/integration.py`. This starts temporary services
and uses synthetic provider responses. It does not prove live provider access.
See [verification notes](../application/TEST-RESULTS.md) for additional checks.

Explain the problem, resulting behavior and relevant validation in a pull request.
Mention any data migration or unverified behavior. Keep internal conversations,
local machine state and owner details out of public documentation and progress notes.

The repository does not yet include a license. Do not add third-party code without
checking its redistribution terms or assign a project license on the owner's behalf.
