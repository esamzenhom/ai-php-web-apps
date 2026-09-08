# Runtime separation

Caddy exposes the configured localhost port. Trusted admin PHP-FPM serves setup,
settings and chat. The worker uses bounded changes and verified recovery copies.
A second internal Caddy gateway connects to the isolated website's PHP-FPM.

The website has its own read-only source mount, writable private site-data folder,
and restricted PostgreSQL role. It has no admin source, key, settings or session
mount and no shared network with admin PHP-FPM. All persistent mounts, including
Caddy defaults, bind local directories. No Docker named or anonymous volumes.

`docker-compose.prod.yml` attaches the outer Caddy to the optional shared TLS edge.
Live TLS/domain routing requires a separately configured server and verification.
