{
  "product": "WPNexus AI",
  "version": "0.1.0",
  "updated_at": "2026-01-18T18:15:06Z",
  "tasks": {
    "T00": {
      "done": true,
      "note": "Core skeleton: bootstrap, autoloader, plugin instance, activation/deactivation, logger base."
    },
    "T01": {
      "done": true,
      "note": "i18n: t('key') helper + canonical languages/en.php + .pot placeholder + textdomain loader."
    },
    "T02": {
      "done": true,
      "note": "DB schema + dbDelta migrations + installer + db_version option + Crypto util + repo skeletons."
    },
    "T03": {
      "done": true,
      "note": "Admin menu + screens (Dashboard/Targets/Keys/Jobs/Settings) + help UI + admin assets."
    },
    "T04": {
      "done": true,
      "note": "Targets CRUD + wizard + encrypted secrets + test connection against Bridge /health endpoint."
    },
    "T05": {
      "done": true,
      "note": "Bridge client + response wrapper + tolerant contracts (health/site/languages). Targets test uses BridgeClient."
    },
    "T06": {
      "done": true,
      "note": "REST system endpoints: /health, /site, /languages (WPML/Polylang detection + fallback)."
    },
    "T07": {
      "done": true,
      "note": "Queue completed: JobsRepo + Dispatcher (Action Scheduler primary, WP-Cron fallback) + JobRunner + TaskRegistry + hourly sweep scheduled on activation."
    },
    "T08": {
      "done": true,
      "note": "API keys rotation + cooldown: KeysRepo encryption + stats, KeySelector weighted random + concurrency lock, 429 exponential backoff cooldown + reporting helpers."
    },
    "T09": {
      "done": true,
      "note": "Extractor + Segmenter + ContentHash added: local fast extraction, coarse segmentation, stable SHA-256 canonical hash for idempotent sync."
    },
    "T10": {
      "done": true,
      "note": "Providers implemented: ProviderChain + adapters (OpenAI/Claude/Gemini) + 429 cooldown integration + TranslateTask writes translation into job payload."
    }
    "T11": {
      "done": true,
      "note": "UpsertTask implemented: builds Bridge /posts/upsert payload, computes content_hash, skips remote call when hash unchanged, updates Registry (remote_post_id/url/state/last_error) for idempotent sync."
    }

    "T12": {
      "done": true,
      "note": "DeleteTask implemented: supports delete modes (trash|delete|unlink). Uses Bridge /posts/delete with remote_post_id or signature, updates Registry state to deleted/unlinked/failed, retries on network/5xx, needs_input when Bridge missing."
    }

    "T13": {
      "done": true,
      "note": "Term mapping implemented: MappingTermsRepo persists source->target term_id per taxonomy+language. TermsMapper resolves parent chains and upserts terms on target via Bridge /terms/upsert, then UpsertTask sends term_id directives in posts/upsert."
    }

    "T14": {
      "done": true,
      "note": "Editor metabox added for per-post overrides: send_original + source_lang + per-target language and send_original. LanguageRouter reads post meta + payload hints. LanguageResolver resolves 'auto' via Bridge /languages (fallback to target fallback_language), used by UpsertTask/TranslateTask."
    }

    "T15": {
      "done": true,
      "note": "SEO implemented: Core extracts Yoast/RankMath/SEOPress post meta into normalized seo payload (title/description/focus/canonical/robots/social). UpsertPayloadBuilder sends seo with canonical_mode (self/source/custom). Bridge applies seo to active SEO plugin meta keys (Yoast/RankMath/SEOPress) with fallback keys if no SEO plugin is present."
    }

    "T16": {
      "done": true,
      "note": "Bulk + reconcile implemented. Admin bulk action enqueues a single reconcile job (mode=bulk) to avoid heavy admin requests. ReconcileTask runs chunked and enqueues upsert jobs. Registry reconcile mode re-enqueues forced upserts (force=1) to avoid hash-skip when remote might be missing."
    }
    "T17": {
      "done": false
    },
    "T18": {
      "done": false
    },
    "T19": {
      "done": false
    },
    "T20": {
      "done": false
    }
  }
}

