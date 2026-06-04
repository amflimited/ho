# Hoosier Online PHP Sales Page Install

## Install target
Upload the contents of this package into the `public_html` directory for `hoosieronline.com`.

The ZIP contains root-level files:

- `index.php` — public sales page
- `admin.php` — temporary unsecured admin page
- `content/` — editable page/site content
- `partials/` — reusable page sections
- `assets/` — CSS/JS
- `uploads/` — stores uploaded ZIPs from admin page

## Dynamic content model
The main page loads its copy and section order from:

- `content/site.php`
- `content/home.php`

Reusable visual sections live in:

- `partials/hero.php`
- `partials/problem.php`
- `partials/offer.php`
- `partials/process.php`
- `partials/contact.php`

This means small content changes can be made in `content/home.php` without replacing the whole project.

## Admin URL
After upload, visit:

`https://hoosieronline.com/admin.php`

This admin page is intentionally unsecured for the current staging phase.

## Important warning
The admin page lets anyone with the URL edit `content/home.php` and upload ZIP files. Add authentication or remove `admin.php` before using this publicly.


## ZIP updater behavior

The temporary admin page at `/admin.php` now installs update ZIPs directly:

1. Open `/admin.php`.
2. Choose a ZIP package.
3. Click **Upload and install ZIP**.
4. The package extracts into the current site root.
5. Matching files are overwritten.
6. The uploaded ZIP is deleted from `/uploads` after the install attempt.

No manifest is required. No security key is required. This is intentionally unprotected for the current planning/build phase.

Recommended update packages should contain only the files that need to change, for example:

```text
content/home.php
assets/css/main.css
partials/hero.php
```

Do not wrap update files in an extra folder unless that folder is supposed to exist on the live site.
