=== WPNexus AI ===
Contributors: emblem
Tags: syndication, translation, seo, multisite, wpml, polylang
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 0.1.1
License: GPLv2 or later

WPNexus AI is the Core plugin installed on the source site. It queues background jobs to translate/sync content to target sites via WPNexus AI Bridge.

IMPORTANT:
- Heavy operations will be queued (Action Scheduler primary; WP-Cron fallback).
- UI strings must use t('key'); canonical lives in languages/en.php.
- Logging is always on (uploads/wpnexus-ai/logs + WP debug log when WP_DEBUG=true).

== Installation ==
1. Upload folder `wpnexus-ai` to `/wp-content/plugins/`.
2. Activate in Plugins.

== Changelog ==
= 0.1.0 =
- Skeleton + i18n helper + base logger.
