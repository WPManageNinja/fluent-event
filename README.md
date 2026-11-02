### FluentEvent WordPress Plugin

How to build the assets:

```bash
npm install ## to install the dependencies
npm run dev ## for development (Vite dev server)
npm run build ## for production
```

**Note:** `npm run build` sets `'env'` in `config/app.php` to production. Set it back to `'dev'` to use the dev server again. `./build.sh --node-build` restores the file for you.

Then go to https://siteurl/wp-admin/admin.php?page=fluent-event

#### Tests

```bash
composer install -d tests ## PHP test tooling, once
npm test ## JS (Vitest)
npm run test:php ## PHP unit
npm run test:integration ## PHP integration, writes to this site's DB and rolls back
```

More in [tests/README.md](tests/README.md).

#### Release Plugin

```bash
./build.sh --node-build
```

The zip goes to `builds/fluent-event-<version>.zip`. Only the paths in `PAYLOAD` in `build.sh` ship. `readme.txt` is the WordPress.org readme. This file does not ship.

#### DB Changelogs

- None yet.
