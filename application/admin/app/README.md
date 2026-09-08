# Protected application code

This directory implements the admin system and builder. The browser AI worker
cannot edit it. `Admin/` holds authentication, settings, encryption, provider
adapters, proposal validation and apply/recovery. `Views/` holds the trusted UI.

Website features belong in `../../website/app/` and `../../website/public/`. The isolated
website runtime cannot include these protected files. Keep authentication and
secret storage separate when extending the stack.
