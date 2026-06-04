<?php
// V052 PUBLIC FALLBACK ONLY: move this file to ../hoosier-online-private/admin-secrets.php after install.
// Keep this public copy only until private secrets are confirmed working.
declare(strict_types=1);

/**
 * Hoosier Online Admin Secrets v051
 *
 * Contains password hash and session key only.
 *
 * Preferred long-term location: one directory ABOVE public_html:
 *   /home1/spofnkte/hoosier-admin-secrets.php
 *
 * If you move this file there, delete the public_html copy.
 */
return [
    'username' => 'operator',
    'password_hash' => '$2y$12$MCA1Kzd7N.GaRByNyMPQIOro8jF8kEAVDlgWh4j8pwosfW6WjBC0u',
    'session_key' => 'f72bd1f7b8b80cbc8e6dec43eaa44b8bb74cc470cc423e927f8e6d691ba7d523',
];
