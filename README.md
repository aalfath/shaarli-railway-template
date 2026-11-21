[![Deploy on Railway](https://railway.com/button.svg)](https://railway.com/new/template/shaarli-1?utm_medium=integration&utm_source=button&utm_campaign=shaarli)

# Shaarli on Railway

One-click [Railway](https://railway.com) template for [Shaarli](https://github.com/shaarli/Shaarli), the minimalist, database-free bookmarking service.

- **Version:** Shaarli `v0.16.10`, official image `ghcr.io/shaarli/shaarli:v0.16.10`, pinned by digest in the [`Dockerfile`](Dockerfile).
- **Services:** one, `Shaarli` (nginx + PHP-FPM), public over HTTPS, health check `/healthz`.
- **Storage:** one volume at `/var/www/shaarli/data`: bookmarks (`datastore.php`), settings (`config.json.php`), history, login bans, thumbnails and login sessions. Nothing else needs to persist.
- **Memory:** runs comfortably in 0.5 GB (Railway Free plan); idle use is around 20–30 MB.

## Variables

| Variable | Default | What it does |
|---|---|---|
| `SHAARLI_USERNAME` | `admin` | Admin login name |
| `SHAARLI_PASSWORD` | generated, 24 characters | Admin password. Read it in the service's **Variables** tab after deploy |
| `PORT` | `8080` | Port nginx listens on |

The admin account is created from these two variables before the web server starts, so Shaarli's install page is never exposed on the public URL. Everything else (title, timezone, privacy, thumbnails, plugins, API) is set in Shaarli under **Tools → Configure** and kept on the volume.

**Forgot the password?** Change `SHAARLI_PASSWORD` (or `SHAARLI_USERNAME`) in Railway and redeploy: the admin login is reset to the new values. Your bookmarks and settings stay. A password you change inside Shaarli is kept across redeploys for as long as these variables stay the same.

## What this wrapper adds to the official image

- Listens on Railway's `PORT` and answers `/healthz` (checks the volume is writable and the configuration exists; no login needed).
- Seeds the configuration and admin login on first start, as Shaarli's install page would.
- Uses Railway's `X-Real-IP` and `X-Forwarded-Proto`, so Shaarli sees the real visitor address (session protection and the brute-force ban work) and marks cookies `Secure`.
- Keeps thumbnails and PHP sessions on the volume, so the picture wall and your login survive a redeploy.
- Supervises nginx and PHP-FPM: if either stops unexpectedly the container exits with an error and Railway restarts it.
- Sizes PHP-FPM (6 workers, 128 MB each at most) for a small service.

## Updates

Railway tells everyone who deployed this template when this repo's `main` branch changes, and they choose when to apply it. Each version bump is listed in [`CHANGELOG.md`](CHANGELOG.md), breaking changes first. Shaarli upgrades its datastore format itself on the first admin login after an update.

To update by hand: change the tag and digest in the `Dockerfile` to a newer release from [ghcr.io/shaarli/shaarli](https://github.com/shaarli/Shaarli/pkgs/container/shaarli) and redeploy.

## Run it locally

```sh
docker build -t shaarli-railway .
docker run -p 8080:8080 -e SHAARLI_PASSWORD=change-me-please -v shaarli-data:/var/www/shaarli/data shaarli-railway
```

## License

Shaarli is licensed under the [zlib/libpng license](https://github.com/shaarli/Shaarli/blob/master/COPYING). The wrapper files in this repo are under the same license.
