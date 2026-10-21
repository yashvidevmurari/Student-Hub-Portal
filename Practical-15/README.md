# Student Hub Portal

A PHP and MySQL portal for students and administrators. Students can create
accounts, browse events, and register for events. Administrators can review
portal statistics, manage student records and events, and view an audit log of
administrative changes.

## Features

- Student registration and password-based sign-in
- Separate student and administrator roles, protected pages, and role-based
  navigation
- Remember-me sessions and CSRF-protected logout
- Admin dashboard with user, student, and event counts, recent event
  registrations, and administrator audit history
- Admin student management and event management
- Event posters and event management through an AJAX API
- Public event listing and registration with input validation and duplicate
  registration prevention
- Responsive portal pages for desktop and mobile

## Requirements

- Windows and [XAMPP](https://www.apachefriends.org/) with Apache and MySQL or
  MariaDB
- PHP with the `mysqli`, `pdo_mysql`, and `fileinfo` extensions enabled
- A modern web browser

By default, database connections use host `localhost`, database `studenthub`,
user `root`, and an empty password (the standard local XAMPP default). To use
different credentials, configure these environment variables for Apache:

| Variable | Default |
| --- | --- |
| `STUDENTHUB_DB_HOST` | `localhost` |
| `STUDENTHUB_DB_NAME` | `studenthub` |
| `STUDENTHUB_DB_USER` | `root` |
| `STUDENTHUB_DB_PASSWORD` | empty |

Do not put production credentials in this repository.

## Install locally with XAMPP

1. Put the project at `C:\xampp\htdocs\WDF\StudentHub`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open phpMyAdmin at <http://localhost/phpmyadmin>. Import
   [`database-setup.sql`](database-setup.sql) to create the `studenthub`
   database and the tables used by the portal.
4. Open the home page:
   <http://localhost/WDF/StudentHub/html/index.html>
5. Create a student account from the sign-up page. To grant administrator
   access to a trusted account, select the `studenthub` database in
   phpMyAdmin and run the following query with that account's email:

   ```sql
   UPDATE students
   SET role = 'admin'
   WHERE email = 'admin@example.com';
   ```

6. Sign in at
   <http://localhost/WDF/StudentHub/html/admin-login.html>.

`database-setup.sql` is for a **new installation**. It creates tables when
they do not exist, but it does not migrate or repair existing tables. Back up
an existing database before applying changes. The files
[`student-registration-schema.sql`](student-registration-schema.sql),
[`student-auth-schema.sql`](student-auth-schema.sql),
[`event-management-schema.sql`](event-management-schema.sql), and
[`admin-audit-schema.sql`](admin-audit-schema.sql) are incremental schema
changes for existing installations; apply only changes your database still
needs, and do not apply them after the complete bootstrap without first
checking the schema.

## Optional virtual host

The supplied
[`deployment/studenthub-vhost.conf`](deployment/studenthub-vhost.conf)
defines both a `localhost` host and the `studenthub.local` host. The working
portal URL after setup is:

- Home: <http://studenthub.local/>
- Role chooser: <http://studenthub.local/choose.html>
- Admin login: <http://studenthub.local/admin-login.html>
- Student login: <http://studenthub.local/student-login.html>
- Events: <http://studenthub.local/events.php>

To configure it:

1. Back up `C:\xampp\apache\conf\extra\httpd-vhosts.conf`.
2. Copy the contents of
   [`deployment/studenthub-vhost.conf`](deployment/studenthub-vhost.conf) into
   `C:\xampp\apache\conf\extra\httpd-vhosts.conf`.
3. Check that `C:\xampp\apache\conf\httpd.conf` includes
   `conf/extra/httpd-vhosts.conf`.
4. Open Notepad as Administrator and add this line to
   `C:\Windows\System32\drivers\etc\hosts`:

   ```text
   127.0.0.1 studenthub.local
   ```

5. Validate the Apache configuration in an Administrator terminal:

   ```powershell
   C:\xampp\apache\bin\httpd.exe -t
   ```

   Continue only if Apache reports `Syntax OK`. Restart Apache from the XAMPP
   Control Panel so it loads the virtual-host configuration.
6. Open <http://studenthub.local/>.

The project `.htaccess` redirects root-level HTML links to their actual files
in `html/`. Apache must have `mod_rewrite` enabled and allow `.htaccess`
overrides (`AllowOverride All`). Session and remember-me cookie paths adapt to
the URL used to access the application. If pages fail to load, check the
Apache logs in `C:\xampp\apache\logs`.

## Verification checklist

- [ ] Open home, role chooser, login, registration, contact, FAQ, and event
      pages; follow their navigation links.
- [ ] Register a student account, sign in, use the remember-me option, and
      sign out.
- [ ] Assign admin role to a test account. Verify the admin dashboard, student
      management, event management, and audit log.
- [ ] While logged out, request protected pages and confirm they redirect to
      the appropriate login page. Sign in as a student and confirm admin pages
      and event-management API writes are denied.
- [ ] Test event and student create, edit, and delete flows. Confirm valid
      database changes appear in the UI and audit log.
- [ ] Test event registration, invalid form data, duplicate registration, and
      missing or invalid CSRF tokens.
- [ ] Test event AJAX create/edit/delete operations and requests without a
      valid CSRF token.
- [ ] Check desktop and narrow/mobile layouts. Inspect browser console and
      Apache/PHP logs for errors.
- [ ] Run PHP syntax checks on the PHP files:

   ```powershell
   Get-ChildItem . -Recurse -Filter *.php | ForEach-Object {
       C:\xampp\php\php.exe -l $_.FullName
   }
   ```

## GitHub submission and data safety

Before submitting, review the repository status and staged changes:

```powershell
git status --short
git diff --check
git diff --cached
```

Stage only reviewed project files. Do not submit credentials, database dumps,
real student information, local environment files, or unrelated personal
files. The local `data/student.json` file and
`html/students-signups.csv` export are excluded in [`.gitignore`](.gitignore).
The public repository can include
[`data/student.example.json`](data/student.example.json) as demo data.

After reviewing the staged file list and confirming it contains no private
data, commit and push to the intended branch:

```powershell
git add <reviewed-project-files>
git diff --cached --check
git diff --cached
git commit -m "Integrate Student Hub portal"
git push origin main
```
