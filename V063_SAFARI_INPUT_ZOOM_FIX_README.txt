v063 Safari Input Zoom Fix

Issue:
Safari was still zooming on input focus.

Cause:
iOS Safari zooms when a focused form control computes under 16px. Some admin textarea/input rules still resolved small on mobile.

Fix:
- admin-core.php viewport changed to:
  width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover
- admin.css now forces all admin form controls to 16px on iOS/Safari.
- Textareas and prompt boxes are explicitly set to 16px.
- Touch targets stay at least 48px high.

Scope:
- admin-core.php
- assets/css/admin.css

No backend logic changes.
No database changes.
No upload/security changes.
