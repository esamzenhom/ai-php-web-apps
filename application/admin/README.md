# Admin platform

This folder contains the protected platform that serves `/admin`, the first-run
welcome page, settings and AI building chat.

- `app/`: PHP application code, provider adapters and trusted views.
- `public/`: platform web entrypoint and assets.
- `migrations/`: private admin database structure.

The app created by the owner lives separately in [website](../website/).
The browser builder can edit that app but cannot modify this folder.
