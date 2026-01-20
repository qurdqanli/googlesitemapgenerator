<?php
return [
	// --- Plugin Info ---
	'plugin_name' => 'WPNexus AI Bridge',
	'plugin_desc' => 'Target-side bridge for WPNexus AI Core: provides stable REST endpoints for multisite/multilingual/SEO/media operations.',

	// --- Admin UI: General ---
	'bridge_admin_menu'  => 'WPNexus Bridge',
	'bridge_admin_title' => 'WPNexus AI Bridge',
	'bridge_admin_intro' => 'This plugin exposes stable REST endpoints used by WPNexus AI Core. Configure authentication below.',

	// --- Admin UI: Token Management (Detailed) ---
	'bridge_admin_token_title'        => 'Bridge token (Bearer)',
	'bridge_admin_token_visible'      => 'Token (showing now):',
	'bridge_admin_token_hidden'       => 'Token is hidden. Regenerate to show a new token.',
	'bridge_admin_token_hint_copy'    => 'Copy this token into Core → Targets → Authentication: "Bridge token". Keep it secret.',
	'bridge_admin_btn_regen'          => 'Regenerate token',
	'bridge_admin_btn_hide'           => 'Hide token',
	'bridge_admin_notice_regenerated' => 'Token regenerated. Copy it now.',
	'bridge_admin_notice_hidden'      => 'Token hidden.',

	// --- Admin UI: Application Password ---
	'bridge_admin_app_password_title' => 'Application Password (alternative)',
	'bridge_admin_app_password_body'  => 'You can also authenticate using WordPress Application Passwords (Basic Auth). Create an Application Password for a user and use it in Core → Targets → Authentication: "Application Password".',

	// --- REST API / Auth Messages ---
	'rest_auth_required' => 'Authentication required.',
	'rest_forbidden'     => 'You do not have permission to access this endpoint.',
	'auth_missing'       => 'Missing Authorization header.',
	'auth_invalid'       => 'Invalid token or credentials.',
	'auth_cap_missing'   => 'Current user lacks required capability.',

	// --- Validation & Object Errors ---
	'rest_invalid_params'       => 'Invalid request parameters.',
	'rest_taxonomy_invalid'     => 'Invalid taxonomy.',
	'rest_terms_name_required'  => 'Term name is required.',
	'rest_terms_upsert_failed'  => 'Term upsert failed.',
	'rest_post_type_invalid'    => 'Invalid post type.',
	'rest_post_not_found'       => 'Post not found.',
	'rest_signature_not_found'  => 'No post found for the provided signature.',
	'rest_upload_missing'       => 'Upload is missing (expected multipart field: file).',
	'rest_upload_failed'        => 'Upload failed.',
	'rest_attachment_not_found' => 'Attachment not found.',
	'rest_language_invalid'     => 'Invalid language code.',
	'rest_delete_failed'        => 'Delete failed.',

	// --- Multisite Operations ---
	'rest_multisite_forbidden' => 'Multisite routing requires a Super Admin.',
	'rest_site_not_found'      => 'Requested site was not found in this network.',
	'ms_header_note'           => 'For multisite targets, Core can send X-WPNexus-Network-Site header to route requests to a specific site.',
	'ms_site_forbidden'        => 'You must be a Super Admin to switch sites via API.',
	'ms_site_not_found'        => 'Requested site was not found in this network.',

	// --- Health Check & Status ---
	'health_title' => 'Bridge health',
	'health_body'  => 'If Core can reach this endpoint, Targets can be synced safely.',
	'msg_saved'    => 'Saved.',
	'msg_failed'   => 'Failed.',
	'msg_success'  => 'Success.',

	// --- Legacy / Alternative Keys (Compatibility) ---
	// Kodun köhnə versiyalarının işləməsi üçün bu açarlar saxlanıldı
	'admin_menu_title'        => 'WPNexus Bridge',
	'admin_page_title'        => 'WPNexus AI Bridge',
	'admin_token_title'       => 'Bridge token (Bearer)',
	'admin_token_body'        => 'Use this token in WPNexus AI Core Target settings. It is required for secure API access.',
	'admin_token_regenerate'  => 'Regenerate token',
	'admin_token_regenerated' => 'Token regenerated.',
	'admin_token_copy'        => 'Copy token',
	'admin_token_note'        => 'You can also authenticate using WordPress Application Passwords (Basic Auth). Create an Application Password for an admin user.',
];
