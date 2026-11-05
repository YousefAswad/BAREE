# Running BAREE locally on Windows (Docker, isolated)

Requirements: Windows 10/11, Docker Desktop (Compose v2), PowerShell. No local PHP, Composer or Node is needed.

```powershell
git clone https://github.com/YousefAswad/BAREE.git
cd BAREE
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows-setup.ps1 -PreflightOnly   # checks only
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows-setup.ps1                  # scaffold Laravel, apply the BAREE overlay, migrate, seed, build
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows-run.ps1 verify
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows-test.ps1                   # Pest, on the separate baree_test database
```

* This repository is the **overlay**: BAREE's own files. `windows-setup.ps1` creates a fresh Laravel starter-kit app (default
  `%USERPROFILE%\baree-app`, override with `-AppDir`), copies the overlay into it, and runs every stage once (markers in `.baree-setup\`).
  Later fixes are delivered by the `overlay-fixes-N` stages in `docker/dev/bootstrap.sh` (previous files are backed up first).
* The stack is isolated (compose project `baree-local`, own volumes, loopback-only ports): app 8000, Reverb 8081, PostgreSQL 55432,
  Redis 56379, MinIO 59000/59001, Mailpit 58025.
* Demo accounts (local only, seeded): `staff@baree.test` (platform), `admin@future.test` (institution admin), `ahmad@future.test` (employee).
  Their seeded password is shown in `database/seeders/DemoTenantSeeder.php`; change it for anything other than local use.
* Local default service passwords can be overridden with `BAREE_PG_OWNER_PASSWORD`, `BAREE_PG_APP_PASSWORD`, `BAREE_MINIO_PASSWORD`.
* Never commit `.env`, logs or `.baree-setup\`; `.gitignore` excludes them.
