<?php
return [
	'plugin_name' => 'WPNexus AI',
	'plugin_desc' => 'Syndicate content to target WordPress sites with translation, mapping, SEO and background jobs.',
	'log_prefix'  => 'WPNexus AI',

	// Generic
	'yes' => 'Yes',
	'no'  => 'No',

	// Notices (future screens)
	'notice_bridge_missing_title' => 'Bridge plugin is not detected on the target site.',
	'notice_bridge_missing_body'  => 'For multilingual/multisite stability, install “WPNexus AI Bridge” on the target site. Core will use best-effort fallback, but some features may be limited.',
    
    	// Admin common
	'menu_wpnexus_ai' => 'WPNexus AI',
	'menu_dashboard'  => 'Dashboard',
	'menu_targets'    => 'Targets',
	'menu_api_keys'   => 'API Keys',
	'menu_jobs'       => 'Jobs',
	'menu_settings'   => 'Settings',
	'admin_screen_missing' => 'This screen is not available.',

	// Dashboard
	'dashboard_title' => 'Dashboard',
	'dashboard_intro' => 'Use WPNexus AI to send posts/pages/CPT content to one or many target WordPress sites. Heavy tasks run in the background queue.',
	'dashboard_cta_targets' => 'Manage Targets',
	'dashboard_cta_keys'    => 'API Keys',
	'dashboard_cta_jobs'    => 'Jobs',

	'dashboard_help_bridge_title' => 'Why the Bridge plugin matters',
	'dashboard_help_bridge_body'  => 'Bridge provides stable universal endpoints on target sites (multisite + WPML/Polylang + SEO meta + taxonomy + media upload). Core will avoid guessing internals and will prefer Bridge for reliability.',
	'dashboard_help_bridge_point_1' => 'Multilingual and multisite language assignment is handled consistently.',
	'dashboard_help_bridge_point_2' => 'Taxonomy upsert/mapping and media upload become predictable across sites.',
	'dashboard_help_bridge_point_3' => 'SEO meta + hreflang/canonical can be applied safely via integrations.',
	'dashboard_bridge_install_btn' => 'Install Bridge on targets',

	'dashboard_status_title'   => 'System Status',
	'dashboard_status_version' => 'Plugin version',
	'dashboard_status_db_version' => 'DB schema version',
	'dashboard_status_queue'   => 'Queue runner',
	'dashboard_status_queue_placeholder' => 'Will be configured in T07 (Action Scheduler + WP-Cron fallback).',
	'dashboard_status_licensing' => 'Licensing',
	'dashboard_status_licensing_disabled' => 'Not enforced (testing mode).',
	'dashboard_status_note'    => 'Next steps: add targets, then add provider API keys, then start syncing via jobs.',

	// Targets
	'targets_title' => 'Targets',
	'targets_intro' => 'Targets are WordPress sites that will receive content. For best results (multisite/WPML/Polylang/SEO), install WPNexus AI Bridge on each target.',
	'targets_add_btn' => 'Add Target (Wizard)',
	'targets_empty'   => 'No targets yet. Add your first target to begin.',
	'targets_col_id'  => 'ID',
	'targets_col_base_url' => 'Base URL',
	'targets_col_default_lang' => 'Default language',
	'targets_col_status_default' => 'Default status',
	'targets_col_updated' => 'Updated',
	'targets_note_readonly' => 'This list is read-only for now. Full wizard + CRUD arrives in T04.',
	'targets_bridge_title' => 'Bridge recommended',
	'targets_bridge_body'  => 'Without Bridge, Core uses best-effort fallback. Some features can be limited or inconsistent depending on the target setup.',
	'targets_bridge_point_1' => 'WPML/Polylang language assignment and hreflang are handled through Bridge.',
	'targets_bridge_point_2' => 'Taxonomy mapping/auto-create and media handling are more reliable.',

	// Keys
	'keys_title' => 'API Keys',
	'keys_intro' => 'Add unlimited API keys per provider. Keys will be rotated with weighted selection. On 429 responses, keys enter cooldown with exponential backoff.',
	'keys_empty' => 'No keys yet. Key management UI arrives in T08.',
	'keys_col_id' => 'ID',
	'keys_col_provider' => 'Provider',
	'keys_col_active' => 'Active',
	'keys_col_cooldown' => 'Cooldown until',
	'keys_col_last_used' => 'Last used',
	'keys_col_success' => 'Success',
	'keys_col_fail' => 'Fail',
	'keys_col_429' => '429',
	'keys_help_429_title' => '429 / rate-limit handling',
	'keys_help_429_body'  => 'When a provider returns 429, the current key is put on cooldown and another key is tried. If all keys are cooling down, jobs will pause with a clear message.',
	'keys_help_429_point_1' => 'Add more keys to increase throughput and reduce pauses.',
	'keys_help_429_point_2' => 'Cooldown uses exponential backoff to avoid repeated rate-limit hits.',

	// Jobs
	'jobs_title' => 'Jobs',
	'jobs_intro' => 'All heavy operations run as background jobs (sync, translate, media upload, bulk, reconcile).',
	'jobs_empty' => 'No jobs yet. Queue + job runner arrives in T07.',
	'jobs_col_id' => 'ID',
	'jobs_col_type' => 'Type',
	'jobs_col_status' => 'Status',
	'jobs_col_attempts' => 'Attempts',
	'jobs_col_next_run' => 'Next run',
	'jobs_col_updated' => 'Updated',
	'jobs_help_queue_title' => 'Queue design',
	'jobs_help_queue_body'  => 'Admin requests should never perform long API/media/bulk work. WPNexus AI uses Action Scheduler primarily with WP-Cron fallback.',

		// Settings / Licensing
 	
      // Settings (base)
    'settings_title' => 'Settings',
    'settings_intro' => 'Control privacy, licensing (optional), and general plugin behavior.',

    'settings_privacy_title' => 'Privacy',
    'settings_privacy_body' => 'WPNexus AI can work fully offline. License checks are opt-in. You can review what data would be sent and disable it at any time.',

    'settings_licensing_title' => 'Licensing (optional)',
    'settings_licensing_status' => 'Licensing status',

    // Licensing body used in SettingsScreen (replaces old placeholder)
    'settings_licensing_body' => 'Licensing is optional. If you enable opt-in, WPNexus AI can validate your purchase and fetch entitlements from emblem.az. You can disable this anytime.',

	'settings_licensing_body' => 'Licensing is optional. If you enable opt-in, WPNexus AI can validate your purchase and fetch entitlements from emblem.az. You can disable this anytime.',
	'settings_licensing_opt_in' => 'License checks',
	'settings_licensing_opt_in_label' => 'Enable license checks (opt-in)',
	'settings_licensing_opt_in_help' => 'When enabled, WPNexus AI may contact emblem.az to validate your purchase. You can review what data is sent below.',
	'settings_licensing_purchase_code' => 'Purchase code',
	'settings_licensing_purchase_code_help' => 'Optional. If provided, it will be used only when opt-in is enabled.',
	'settings_licensing_what_we_send' => 'What we send',
	'settings_licensing_what_we_send_list' => 'Site URL, plugin version, and your purchase code (if provided). No post content is sent.',
	'settings_licensing_entitlements' => 'Entitlements',
	'settings_licensing_targets_limit' => 'Targets limit',
	'settings_licensing_targets_unlimited' => 'Unlimited (testing)',
	'settings_licensing_features' => 'Features',
	'settings_licensing_last_check' => 'Last check',
	'settings_licensing_grace_until' => 'Grace until',

	'settings_licensing_status_enabled' => 'Enabled (opt-in)',
	'settings_licensing_status_disabled' => 'Disabled',
	'settings_licensing_status_testing' => 'Testing mode',
	'settings_licensing_note_testing' => 'Enforcement is currently disabled to allow full testing. You can enable enforcement later via filter wpnexus_ai_license_enforce.',

	'settings_save' => 'Save settings',
	'settings_saved' => 'Settings saved.',
	'settings_save_failed' => 'Failed to save settings.',
	'settings_yes' => 'Yes',
	'settings_no' => 'No',

	'settings_licensing_note' => 'Entitlements (targets limit 1/5/10) will be implemented in T17 with opt-in.',

    	// Targets (T04)
	'targets_col_auth' => 'Auth',
	'targets_col_actions' => 'Actions',
	'targets_action_edit' => 'Edit',
	'targets_action_test' => 'Test',
	'targets_action_delete' => 'Delete',
	'targets_confirm_delete' => 'Delete this target? This will also remove related mappings and registry links.',
	'targets_auth_none' => 'none',
	'targets_auth_bridge_token' => 'Bridge token',
	'targets_auth_app_password' => 'Application Password',

	'targets_add_title' => 'Add Target',
	'targets_edit_title' => 'Edit Target',
	'targets_back_to_list' => 'Back to Targets',

	'targets_field_base_url' => 'Base URL',
	'targets_field_base_url_help' => 'Example: https://example.com (no wp-admin).',

	'targets_field_network_site_id' => 'Network site ID (optional)',
	'targets_field_network_site_id_help' => 'If the target is a WordPress Multisite, you can specify a site/blog id used by Bridge routing.',

	'targets_field_auth_method' => 'Authentication',
	'targets_field_auth_method_help' => 'Bridge token is recommended. App Password is supported as an alternative.',
	'targets_field_auth_user' => 'Auth username (for App Password)',
	'targets_field_auth_user_help' => 'Only required if you use Application Password authentication.',

	'targets_field_bridge_token' => 'Bridge token',
	'targets_field_bridge_token_help' => 'Paste the token generated on the target site (Bridge). It will be stored encrypted.',
	'targets_field_app_password' => 'Application Password',
	'targets_field_app_password_help' => 'Paste the WordPress Application Password. It will be stored encrypted.',
	'targets_field_secret_keep_help' => 'Leave blank to keep the currently saved secret (stored encrypted).',

	'targets_field_default_language' => 'Default language',
	'targets_field_default_language_help' => 'Use "auto" to let Bridge decide the default language. Or set a fixed language code like "en".',
	'targets_field_fallback_language' => 'Fallback language',
	'targets_field_fallback_language_help' => 'Used when Auto cannot be resolved. Example: en.',

	'targets_field_status_default' => 'Default post status',
	'targets_field_status_default_help' => 'Default status when creating content on the target.',

	'targets_field_canonical_mode' => 'Canonical mode',
	'targets_field_canonical_mode_help' => 'Default SEO canonical behavior for this target.',
	'targets_field_canonical_custom' => 'Custom canonical URL',
	'targets_field_canonical_custom_help' => 'Used only when canonical mode is "custom".',

	'seo_canonical_self' => 'self (target URL)',
	'seo_canonical_source' => 'source (source URL)',
	'seo_canonical_custom' => 'custom URL',

	'targets_btn_save' => 'Save target',
	'targets_btn_cancel' => 'Cancel',

	'targets_not_found' => 'Target not found.',
	'targets_notice_saved' => 'Target saved.',
	'targets_notice_deleted' => 'Target deleted.',
	'targets_notice_save_failed' => 'Could not save target. Please check the fields and try again.',
	'targets_notice_test_ok' => 'Connection test succeeded.',
	'targets_notice_test_failed' => 'Connection test failed.',

	'targets_test_title' => 'Test connection',
	'targets_btn_test' => 'Test Bridge /health',
	'targets_test_save_first' => 'Save the target first to run a connection test.',
	'targets_test_result_title' => 'Last test result',
	'targets_test_status' => 'HTTP status',
	'targets_test_ok' => 'OK',
	'targets_test_bridge' => 'Bridge detected',
	'targets_test_error' => 'Error',
	'targets_test_body' => 'Response body',
	'targets_test_bridge_missing_hint' => 'Bridge may be missing on the target site. Install WPNexus AI Bridge for stable multilingual/multisite + SEO + taxonomy + media support.',

	'targets_help_title' => 'How Targets work',
	'targets_help_body'  => 'Targets receive content via the Bridge API. Core avoids guessing internal plugins and relies on Bridge for stability.',
	'targets_help_point_1' => 'Use Bridge for WPML/Polylang language assignment and hreflang/canonical handling.',
	'targets_help_point_2' => 'Use Bridge for taxonomy upsert/mapping and media upload reliability.',
	'targets_help_point_3' => 'Sync is idempotent: updates only happen when content hash changes (T11).',

    	// Keys (T08 UI)
	'keys_col_actions' => 'Actions',

	'keys_add_title' => 'Add API key',
	'keys_import_title' => 'Bulk import',
	'keys_list_title' => 'Key list',

	'keys_field_provider' => 'Provider',
	'keys_field_provider_help' => 'Provider group used for rotation (OpenAI/Claude/Gemini/Custom).',
	'keys_field_key' => 'API key',
	'keys_field_key_help' => 'Stored encrypted. Never shown again after saving.',
	'keys_field_active' => 'Active',
	'keys_field_active_label' => 'Enable this key',
	'keys_field_import_keys' => 'Keys (one per line)',
	'keys_field_import_help' => 'Paste one key per line. Duplicates will be skipped (best-effort).',

	'keys_btn_add' => 'Add key',
	'keys_btn_import' => 'Import keys',
	'keys_btn_enable' => 'Enable',
	'keys_btn_disable' => 'Disable',
	'keys_btn_update' => 'Update key',
	'keys_update_placeholder' => 'Paste new key value',
	'keys_btn_save_update' => 'Save',
	'keys_btn_delete' => 'Delete',
	'keys_confirm_delete' => 'Delete this key?',

	'keys_notice_created' => 'Key added.',
	'keys_notice_create_failed' => 'Could not add key. Check fields and try again.',
	'keys_notice_updated' => 'Key updated.',
	'keys_notice_update_failed' => 'Could not update key.',
	'keys_notice_deleted' => 'Key deleted.',
	'keys_notice_delete_failed' => 'Could not delete key.',
	'keys_notice_toggled' => 'Key status updated.',
	'keys_notice_toggle_failed' => 'Could not change key status.',
	'keys_notice_imported' => 'Import finished.',
	'keys_notice_import_failed' => 'Could not import keys.',
	'keys_notice_duplicate' => 'This key already exists for the selected provider.',
    'keys_notice_imported_with_counts' => 'Import finished. Added: %d, skipped: %d',

	'keys_help_title' => 'How key rotation works',
	'keys_help_body' => 'You can add unlimited keys per provider. Core will pick an available key using weighted random selection.',
	'keys_help_rotation_title' => 'Weighted selection',
	'keys_help_rotation_body' => 'Keys with fewer 429 events and less recent usage are preferred. last_used_at is updated when a key is selected.',

    	// Providers
	'provider_err_no_keys' => 'No API keys available. Add at least one key in WPNexus AI → Keys.',
	'provider_err_all_rate_limited' => 'All keys are rate-limited; wait or add more keys.',
	'provider_err_rate_limited' => 'Provider is rate-limiting requests. The job will retry later.',
	'provider_err_api' => 'Provider API error.',
	'provider_err_model_not_found' => 'Provider model/endpoint not found (404). Check the selected model name and API base URL.',
	'provider_err_bad_response' => 'Provider returned an unexpected response.',

    	// Sync / Upsert
	'sync_err_invalid_payload' => 'Job payload is missing required fields.',
	'sync_err_target_missing' => 'Target not found.',
	'sync_err_bridge_missing' => 'Bridge endpoint not found. Install and activate WPNexus AI Bridge on the target site, then re-run the job.',
	'sync_err_bridge_http' => 'Bridge request failed.',
	'sync_err_bridge_bad_response' => 'Bridge returned an unexpected response.',
	'sync_skipped_nochange' => 'No changes detected; sync skipped.',
 
     	// Delete / Unlink
	'delete_err_invalid_payload' => 'Delete job payload is missing required fields.',
	'delete_err_target_missing' => 'Target not found.',
	'delete_err_bridge_missing' => 'Bridge endpoint not found. Install and activate WPNexus AI Bridge on the target site, then re-run the job.',
	'delete_err_bridge_http' => 'Bridge delete request failed.',

    	// Term mapping
	'mapping_err_invalid' => 'Term mapping failed: invalid parameters.',
	'mapping_err_cycle' => 'Term mapping failed due to a parent cycle.',
	'mapping_err_term_missing' => 'Term mapping failed: term is missing name/slug.',
	'mapping_err_bridge_missing' => 'Bridge terms endpoint is missing. Update/activate WPNexus AI Bridge on the target site, then re-run the job.',
	'mapping_err_bridge_http' => 'Bridge terms request failed.',
	'mapping_err_bad_response' => 'Bridge terms endpoint returned an unexpected response.',
 
     	// Editor metabox
	'editor_metabox_title' => 'WPNexus AI',
	'editor_no_access' => 'You do not have permission to edit WPNexus AI settings for this post.',
	'editor_send_original' => 'Send original without translation',
	'editor_send_original_help' => 'If enabled, WPNexus AI will sync the original content to targets without translating it.',
	'editor_source_lang' => 'Source language',
	'editor_source_lang_help' => 'Optional. Use "auto" to let providers infer the language.',
	'editor_no_targets' => 'No targets configured yet. Add targets in WPNexus AI → Targets.',
	'editor_target_overrides' => 'Per-target overrides',
	'editor_target_overrides_help' => 'Leave language as "auto" to use target defaults (Bridge /languages if needed).',
	'editor_target_language' => 'Target language (code)',
	'editor_target_send_original' => 'Send original for this target',
 
 	    // SEO
	'seo_note_applied' => 'SEO metadata applied.',

    	// Bulk / Reconcile
	'bulk_sync_label' => 'WPNexus AI: Sync to targets',
	'bulk_sync_queued' => 'Queued sync for %d posts. Reconcile job #%d was created.',
	'bulk_sync_empty' => 'No posts selected.',
	'bulk_sync_no_access' => 'You do not have permission to run this action.',
	'reconcile_err_nothing_to_do' => 'Nothing to reconcile (no posts or targets).',
 
     	// Licensing
	'license_title' => 'Licensing (optional)',
	'license_opt_in' => 'Enable license checks (opt-in)',
	'license_opt_in_help' => 'If enabled, WPNexus AI will contact emblem.az to validate your purchase and fetch entitlements. You can disable anytime.',
	'license_what_we_send' => 'What we send',
	'license_what_we_send_list' => 'Site URL, plugin version, and your purchase code (if provided). No post content is sent.',
	'license_purchase_code' => 'Purchase code (optional)',
	'license_saved' => 'License settings saved.',
	'license_enforcement_off' => 'Enforcement is currently disabled (testing mode).',




];
