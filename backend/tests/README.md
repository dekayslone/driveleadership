# Local API endpoint tests

These tests require PHP 7.4+ installed locally. They do not require MySQL, WhoGoHost, Resend, or frontend changes.

From the project root, start PHP's built-in server:

```powershell
php -S 127.0.0.1:8080 -t backend
```

Use a second terminal for the requests below.

## Valid attendee request

```powershell
$attendee = @{
    full_name = 'Test Attendee'
    organisation = 'Test Organisation'
    employment_status = 'Student'
    employment_status_other = ''
    email = 'attendee@example.com'
    phone = '+2348000000000'
    is_network_member = 'No'
    referral_source = 'Social Media'
    referral_source_other = ''
    expectations = 'I want to learn and contribute.'
} | ConvertTo-Json

Invoke-WebRequest -Uri 'http://127.0.0.1:8080/api/attendee-submit.php' -Method Post -ContentType 'application/json' -Body $attendee
```

Expected response with local file persistence enabled: HTTP `201` with a success message and generated record ID.

## Invalid attendee request

```powershell
$invalidAttendee = '{"full_name":"","email":"not-an-email"}'
Invoke-WebRequest -Uri 'http://127.0.0.1:8080/api/attendee-submit.php' -Method Post -ContentType 'application/json' -Body $invalidAttendee
```

Expected response: HTTP `422` with an `errors` object for missing and invalid fields.

## Valid volunteer request

```powershell
$volunteer = @{
    full_name = 'Test Volunteer'
    email = 'volunteer@example.com'
    phone = '+2348000000000'
    relevant_experience = 'Event support'
    availability = 'Weekends'
    preferred_role = 'Registration desk'
    additional_information = ''
} | ConvertTo-Json

Invoke-WebRequest -Uri 'http://127.0.0.1:8080/api/volunteer-submit.php' -Method Post -ContentType 'application/json' -Body $volunteer
```

Expected response with local file persistence enabled: HTTP `201` after validation.

## Invalid volunteer request

```powershell
$invalidVolunteer = '{"full_name":"Test Volunteer","email":"bad-email","phone":"invalid"}'
Invoke-WebRequest -Uri 'http://127.0.0.1:8080/api/volunteer-submit.php' -Method Post -ContentType 'application/json' -Body $invalidVolunteer
```

Expected response: HTTP `422` with field errors.

## Valid contact request

```powershell
$contact = @{
    sender_name = 'Test Contact'
    sender_email = 'contact@example.com'
    interest = 'Corporate workshop'
    message = 'Please send more information.'
} | ConvertTo-Json

Invoke-WebRequest -Uri 'http://127.0.0.1:8080/api/contact-submit.php' -Method Post -ContentType 'application/json' -Body $contact
```

Expected response with local file persistence enabled: HTTP `201` after validation.

## Invalid contact request

```powershell
$invalidContact = '{"sender_name":"","sender_email":"bad-email","interest":"Unknown"}'
Invoke-WebRequest -Uri 'http://127.0.0.1:8080/api/contact-submit.php' -Method Post -ContentType 'application/json' -Body $invalidContact
```

Expected response: HTTP `422` with field errors.

## Common rejection tests

GET request, expected HTTP `405`:

```powershell
Invoke-WebRequest -Uri 'http://127.0.0.1:8080/api/attendee-submit.php' -Method Get
```

Malformed JSON, expected HTTP `400`:

```powershell
Invoke-WebRequest -Uri 'http://127.0.0.1:8080/api/contact-submit.php' -Method Post -ContentType 'application/json' -Body '{bad json'
```

Excessively long input, expected HTTP `422`:

```powershell
$longName = 'x' * 151
$longRequest = @{ full_name = $longName; email = 'test@example.com' } | ConvertTo-Json
Invoke-WebRequest -Uri 'http://127.0.0.1:8080/api/volunteer-submit.php' -Method Post -ContentType 'application/json' -Body $longRequest
```

The endpoints currently use local JSON persistence so they can be tested before hosting is available. Before production deployment, replace this storage with the MySQL schema, add CSRF protection, rate limiting, duplicate checks, hashed admin passwords, and email notifications.

## Admin application imports and email

From **Admin → Attendee Applications**, an authenticated admin can preview and import CSV, XLS, XLSX, or text-based PDF response tables. Spreadsheet and PDF files are parsed in the admin's browser; the original file is not uploaded. Only the confirmed, normalized application records are sent to the application import endpoint.

Imports are limited to 300 responses per file and 10 MB per source file. Include the attendee response columns used by the registration form, including full name, organisation, employment status, email, phone, network membership, referral source, and expectations. Existing applications and repeat rows are skipped using a case-insensitive email match. Every response must pass validation before any new rows are saved.

Admins can open an application and send an individual email from its details view. Email delivery uses the configured Resend integration and requires `RESEND_API_KEY` and a valid `DRIVE_FROM_EMAIL`. Changing an application to Approved or Rejected continues to send the existing status notification.

### Temporary local admin login bypass

The ignored `backend/config/admin.local.php` file enables passwordless dashboard access only when the request comes from `127.0.0.1` or `::1`. It is for local XAMPP development only; do not copy it to a public server. Delete this file to restore normal login. The API continues to reject bypass requests from non-loopback addresses.
