# Step 3: PHP to MySQL connection

## Server configuration

Preferred option: configure these server environment variables in DirectAdmin if PHP environment variables are available:

```text
DRIVE_DB_HOST=DB_HOST
DRIVE_DB_NAME=DB_NAME
DRIVE_DB_USER=DB_USER
DRIVE_DB_PASSWORD=DB_PASS
DRIVE_DB_CHARSET=utf8mb4
```

Shared-hosting fallback: copy `backend/config/database.local.php.example` to `backend/config/database.local.php` on the server and replace only the placeholder values with the credentials created in DirectAdmin. Do not commit `database.local.php` or upload it to a public repository. The existing `backend/config/.htaccess` denies browser access to the directory; the PHP application can still require the file internally.

## Upload location

Upload the backend files so the server paths are:

```text
domains/thedriveleadership.org/public_html/api/db-test.php
domains/thedriveleadership.org/public_html/config/database.php
domains/thedriveleadership.org/public_html/config/.htaccess
```

Upload the contents of the local `backend` directory into `public_html`, not the `backend` directory itself. This produces the required public paths:

```text
public_html/api/health.php
public_html/api/db-test.php
public_html/config/database.php
public_html/config/.htaccess
```

## Test

Open:

```text
https://thedriveleadership.org/api/db-test.php
```

Success response:

```text
Database connection successful.
```

Failure response is intentionally generic:

```text
Database connection unavailable.
```

No credentials, DSN, SQL details, paths, or stack traces are returned.

Delete or disable `api/db-test.php` after the connection has been verified.