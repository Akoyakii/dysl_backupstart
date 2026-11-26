# Deploy ScholarHub on ByetHost

1. In VistaPanel, open **MySQL Databases** and create or select the database for ScholarHub. Copy the **MySQL hostname**, **full database name**, and **MySQL username** from the panel. The hostname is a separate value from the database name.
2. Open **phpMyAdmin**, select that database in the sidebar, and import `includes/schema.sql`. The SQL installs the app tables in the selected database and can be reimported without clearing records.
3. Upload the app to your domain's web directory, including the hidden `.htaccess` files. Copy `includes/database.example.php` to `includes/database.local.php` and fill in the host, full name, user, and MySQL password. Keep the resulting file private. PHP environment variables named `SCHOLARHUB_DB_HOST`, `SCHOLARHUB_DB_PORT`, `SCHOLARHUB_DB_NAME`, `SCHOLARHUB_DB_USER`, and `SCHOLARHUB_DB_PASSWORD` take precedence if configured.
4. Use the HTTPS URL. Confirm that requesting `/includes/database.local.php` directly returns a forbidden response before relying on the deployment.
   The app uses clean paths such as `/login`, `/waiting-for-approval`, and `/report-archive`. Upload the root `.htaccess` together with the PHP and JavaScript files. Local development also needs Apache rewrite support or equivalent routing.
   The root `.htaccess` also blocks public downloads of SQL, JSON, and documentation files. Its HTTPS rules target `dysl.byethost7.com`. If you change domains, update the hostname in those rules and in `includes/security.php`. Rotate any previously shared database or hosting credentials in VistaPanel and update the private settings file.

## Invite the first administrator without SSH

Self-service signup requires email delivery and an existing administrator. Reimport `includes/schema.sql` to add the email verification table. Users click **Sign up**, request a Gmail verification code, and enter the code and a matching password. ScholarHub creates a verified Staff account pending approval and adds it to **Settings → Review signup requests**. The user can sign in after approval. Requests submitted under the earlier approval-first flow must complete this new email verification step before they appear in the approval list. Direct administrator invitations still use **Activate account**.

In phpMyAdmin, select the same database and run the following together in the **SQL** tab. Replace the example email with the recipient's actual Gmail address.

```sql
SET @activation_code = LOWER(HEX(RANDOM_BYTES(24)));
INSERT INTO accounts (email, role, invitation_hash, invitation_expires)
VALUES ('yourname@gmail.com', 'admin', SHA2(@activation_code, 256), UNIX_TIMESTAMP() + 3600);
SELECT @activation_code AS activation_code;
```

Send the displayed code privately to the address in the invitation. The recipient opens `/login?activate=1` directly on the ScholarHub site, enters their email and one-hour code, and sets a password. Both signup and direct activation require at least 12 characters, including an uppercase letter, a lowercase letter, a number, and a symbol. Use `staff` instead of `admin` to invite a Staff account. The database rejects a second invitation insert for the same email.

Staff can manage scholarship records, reports, and backups. Audit Logs, User Management, and signup review require an Administrator. Backup restore and browser import require an empty records database.

Reimport `includes/schema.sql` to install the audit table on an existing deployment. Open **Audit Logs** to load the latest 100 server-recorded events. Use **Refresh logs** to check the database again. Audit entries are retained separately in MySQL and cannot be restored from a ScholarHub JSON backup. Include the audit table in MySQL database backups if you need to migrate its history.

## Report archive

Administrators can use **Reports & Analytics → Import student records from Excel**. Use the Scholar Master List CSV headers as a template and keep student IDs as text in Excel to preserve leading zeroes. Use semicolons between scholarship names. The import reads the first worksheet of an XLSX or an Excel CSV UTF-8. XLSX requires PHP ZipArchive and SimpleXML. If those extensions are unavailable, save as CSV UTF-8 in Excel. Each import accepts up to 2,000 rows and 5 MB. A duplicate ID or invalid row cancels the whole import.

Reimport `includes/schema.sql` to install the archive table. Administrators can open **Reports & Analytics → Generate or open archived reports** to save a snapshot, reopen it, or download the original rows as CSV. Archive snapshots are stored in MySQL and should be included in database backups when migrating the site.

## Dashboard or sign-in unavailable

### Send verification codes with Brevo

1. Verify a sender in Brevo and create an API key for ScholarHub. In the private `includes/database.local.php` file on ByetHost, add these entries inside the existing settings array.

   ```php
   'brevo_api_key' => 'PASTE_YOUR_SCHOLARHUB_KEY_HERE',
   'mail_from' => 'YOUR_VERIFIED_BREVO_SENDER',
   ```

2. Upload the updated `includes/security.php`. The host needs PHP cURL and outbound HTTPS access to the Brevo API. You can also configure the key with `BREVO_API_KEY` and the sender with `SCHOLARHUB_MAIL_FROM`.
3. Open **Sign up**, enter a Gmail address you control that does not already have an account, and click **Send verification code**. Enter the six-digit code within 10 minutes. You can request another code after 60 seconds. Each emailed code allows five verification attempts.

If delivery is unavailable, you can still send the phpMyAdmin code privately. A failed send preserves the prior code. Activation requires matching password and confirmation fields.

If no email arrives, confirm the recipient has an account row with `verified_at` set to `NULL`, and check that both mail settings are filled in on ByetHost. The web form requires a Brevo API key and reports that email is unavailable when the key is missing. Then check Brevo's **Transactional → Logs**. If there is no matching entry, check the host's PHP error log. A **cURL** error points to a transport failure. An **HTTP 401 or 403** means Brevo rejected the request. Check the key, its permissions, and any Brevo alert to authorize a new IP. Diagnostics exclude credentials, email addresses, and codes.

- For a connection error, compare the configured **MySQL hostname** and **full database name** against VistaPanel. Check the username and password there as well.
- For an out-of-date schema, select the exact app database in phpMyAdmin and reimport the schema. Confirm that `accounts` and `request_limits` now exist.
- If the scholarship catalog error appears, upload `includes/catalog.json` alongside the other internal files.

The current frontend loads versioned Tailwind and Chart.js scripts from CDNs. For production, build Tailwind CSS locally and serve both assets from the application host.

## Staff accounts

On an existing deployment, import `includes/migrate-staff-role.sql` in the ScholarHub database before assigning Staff access. New installations get the role from `includes/schema.sql`. In **Settings ? Review signup requests**, approve the verified signup. Staff can manage records, scholarships, academic reports, and backups. Audit Logs, Activity History reports, and signup review require an Administrator. Staff backups and restores cover student and discount records.
For **User Management** in the administrator sidebar, import `includes/migrate-user-management.sql` on an existing installation before uploading the updated PHP files. Administrators can disable or re-enable Staff accounts and issue temporary passwords. Access changes invalidate the account’s previous sessions. New users still verify their own email and go through signup approval.


For this upgrade, import `includes/migrate-temporary-passwords.sql` after the Staff and User Management migrations. Former Viewer accounts without a pending request become disabled Staff, so their access must be explicitly enabled by an administrator. Pending signup requests still require approval. Temporary passwords expire after 30 minutes and must be replaced before workspace access.

## Database backups

Import `includes/migrate-database-backups.sql` into an existing database before deploying the PHP files. Administrators open **Settings** to create, refresh, or restore saved backups. Restore during a maintenance window. It creates a recovery backup, resets pending verification codes, and signs everyone out. Reissue outstanding invitation codes and temporary passwords after restoring. The restore requires the current administrator credentials to be present in the selected backup. Backups stay in MySQL and do not include hosting files or settings. Keep a separate hosting backup for disaster recovery.

If your host supports scheduled PHP commands, run `php /absolute/path/to/htdocs/database-backup.php --weekly` weekly. For Monday at 2:00 AM Philippine time, use `0 2 * * 1` on a Philippine-time scheduler or `0 18 * * 0` on a UTC scheduler.
