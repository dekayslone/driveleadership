# Step 1: PHP backend verification

## File to upload

Upload only this file for Step 1:

```text
backend/health.php
```

## DirectAdmin location

1. Sign in to DirectAdmin.
2. Open **File Manager**.
3. Open the domain folder:

```text
domains/thedriveleadership.org/public_html/
```

4. Create a folder named `backend` if it does not already exist.
5. Upload `health.php` into:

```text
domains/thedriveleadership.org/public_html/backend/health.php
```

Do not upload database credentials, API keys, or Resend credentials. Do not upload the later-stage API or data files for this step.

## Test URL

Open:

```text
https://thedriveleadership.org/backend/health.php
```

Expected response:

```text
Drive Leadership backend is running.
```

## Verification

PHP is executing correctly when the exact text above appears over HTTPS with no download prompt, PHP source code, warning, or server error.

Also confirm that the existing homepage still opens at:

```text
https://thedriveleadership.org/
```

## Remove the temporary test

After verification, delete `backend/health.php` from DirectAdmin File Manager, or rename it outside the public web directory. The endpoint should not remain publicly available after Step 1 verification.