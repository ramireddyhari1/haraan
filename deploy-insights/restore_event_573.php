<?php
// RETIRED 2026-09-16 — do not use.
//
// This script inserted match 49's event 573 blindly, with no preview, no check that sequence 57
// was still free, and no stats refresh. It is replaced by the guarded command, which previews by
// default and writes only with --apply:
//
//   php artisan matches:restore-event 49 --from-json=../deploy-insights/event_573.json
//   php artisan matches:restore-event 49 --from-json=../deploy-insights/event_573.json --apply
//
// The backup row itself is in deploy-insights/event_573.json.
fwrite(STDERR, "restore_event_573.php is retired. Use: php artisan matches:restore-event 49 --from-json=../deploy-insights/event_573.json (dry run first).\n");
exit(1);
