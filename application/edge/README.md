# edge/ — the shared HTTPS router (run once per server)

When you host **several apps on one server**, only one program can own the
public web ports (80/443). This is that program: a single Caddy proxy that
sits in front of all your apps.

It automatically:
- gives every app's domain its own SSL certificate (and **renews them**),
- sends each visitor to the correct app,
- picks up new apps on its own — no editing here.

## Use it (on the server)

```bash
cp edge/.env.example edge/.env   # set LETSENCRYPT_EMAIL
make edge-up                     # start the shared proxy (once per server)
```

Then start each app normally with `make prod-up` (after setting its `DOMAIN`
in that app's `.env`). The proxy detects it and handles SSL + routing.

You only run `make edge-up` **once per server**, no matter how many apps.
