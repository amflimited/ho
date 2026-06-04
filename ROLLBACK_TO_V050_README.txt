Rollback to v050 shell

Purpose:
- Remove v051/v052 security/auth changes.
- Restore the pre-security admin/uploader shell.
- Preserve v050 sales-system Business Review Lock.
- Restore v049 UI/backend review state for sales pages.

Install:
- If upload.php still works, upload this ZIP.
- If upload.php is broken, use cPanel File Manager:
  1. Upload this ZIP into public_html.
  2. Extract it over the site files.
  3. Confirm /admin.php and /upload.php open again.

This rollback intentionally removes:
- admin-auth.php
- admin-login.php
- admin-logout.php
- admin-hardening.php
- admin-secrets.php
- private/ examples
- v052 hardening notes

After rollback, security is not fixed. This is a recovery package.
