<?php

if (!defined('ABSPATH')) {
	exit;
}

class PMM_Admin {

	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'perchance-memory-manager'));
		}

		$data = get_transient('pmm_last_output_' . get_current_user_id());
		$has_last_output = is_array($data) && !empty($data['content']);
		if (!is_array($data)) {
			$data = [];
		}
		if (!isset($data['stats']) || !is_array($data['stats'])) {
			$data['stats'] = [];
		}
		if (!isset($data['cleaned_data']) || !is_array($data['cleaned_data'])) {
			$data['cleaned_data'] = [];
		}
		if (!isset($data['entity_report']) || !is_array($data['entity_report'])) {
			$data['entity_report'] = [];
		}
		$error = isset($_GET['pmm_error']) ? sanitize_text_field(wp_unslash($_GET['pmm_error'])) : '';
		$success = isset($_GET['pmm_success']) ? (int) $_GET['pmm_success'] : 0;
		$similarity_saved = isset($_GET['pmm_similarity_saved']) ? (int) $_GET['pmm_similarity_saved'] : 0;
		$similarity_reviewed = isset($_GET['pmm_similarity_reviewed']) ? (int) $_GET['pmm_similarity_reviewed'] : -1;
		$similarity_truncated = isset($_GET['pmm_similarity_truncated']) ? (int) $_GET['pmm_similarity_truncated'] : 0;
		$similarity_expected_count = isset($_GET['pmm_similarity_expected_count']) ? (int) $_GET['pmm_similarity_expected_count'] : 0;
		$similarity_queue_cleared = isset($_GET['pmm_similarity_queue_cleared']) ? (int) $_GET['pmm_similarity_queue_cleared'] : 0;
		$similarity_queue_filter = isset($_GET['pmm_similarity_queue_filter']) ? sanitize_key((string) wp_unslash($_GET['pmm_similarity_queue_filter'])) : 'pending';
		if (!in_array($similarity_queue_filter, ['pending', 'reviewed', 'all'], true)) {
			$similarity_queue_filter = 'pending';
		}
		$entity_saved = isset($_GET['pmm_entity_saved']) ? (int) $_GET['pmm_entity_saved'] : -1;
		$questionable_saved = isset($_GET['pmm_questionable_saved']) ? (int) $_GET['pmm_questionable_saved'] : -1;
		$questionable_reviewed = isset($_GET['pmm_questionable_reviewed']) ? (int) $_GET['pmm_questionable_reviewed'] : -1;
		$questionable_changed = isset($_GET['pmm_questionable_changed']) ? (int) $_GET['pmm_questionable_changed'] : -1;
		$questionable_removed = isset($_GET['pmm_questionable_removed']) ? (int) $_GET['pmm_questionable_removed'] : 0;
		$questionable_hidden = isset($_GET['pmm_questionable_hidden']) ? (int) $_GET['pmm_questionable_hidden'] : 0;
		$questionable_kept = isset($_GET['pmm_questionable_kept']) ? (int) $_GET['pmm_questionable_kept'] : 0;
		$questionable_updated = isset($_GET['pmm_questionable_updated']) ? (int) $_GET['pmm_questionable_updated'] : 0;
		$questionable_queue_cleared = isset($_GET['pmm_questionable_queue_cleared']) ? (int) $_GET['pmm_questionable_queue_cleared'] : 0;
		$questionable_queue_filter = isset($_GET['pmm_questionable_queue_filter']) ? sanitize_key((string) wp_unslash($_GET['pmm_questionable_queue_filter'])) : 'pending';
		if (!in_array($questionable_queue_filter, ['pending', 'reviewed', 'all'], true)) {
			$questionable_queue_filter = 'pending';
		}
		$reclassification_saved = isset($_GET['pmm_reclassification_saved']) ? (int) $_GET['pmm_reclassification_saved'] : -1;
		$reclassification_reviewed = isset($_GET['pmm_reclassification_reviewed']) ? (int) $_GET['pmm_reclassification_reviewed'] : 0;
		$reclassification_moved = isset($_GET['pmm_reclassification_moved']) ? (int) $_GET['pmm_reclassification_moved'] : 0;
		$reclassification_hidden = isset($_GET['pmm_reclassification_hidden']) ? (int) $_GET['pmm_reclassification_hidden'] : 0;
		$reclassification_kept = isset($_GET['pmm_reclassification_kept']) ? (int) $_GET['pmm_reclassification_kept'] : 0;
		$reclassification_truncated = isset($_GET['pmm_reclassification_truncated']) ? (int) $_GET['pmm_reclassification_truncated'] : 0;
		$reclassification_expected_count = isset($_GET['pmm_reclassification_expected_count']) ? (int) $_GET['pmm_reclassification_expected_count'] : 0;
		$reclassification_queue_cleared = isset($_GET['pmm_reclassification_queue_cleared']) ? (int) $_GET['pmm_reclassification_queue_cleared'] : 0;
		$reclassification_queue_filter = isset($_GET['pmm_reclassification_queue_filter']) ? sanitize_key((string) wp_unslash($_GET['pmm_reclassification_queue_filter'])) : 'pending';
		if (!in_array($reclassification_queue_filter, ['pending', 'reviewed', 'all'], true)) {
			$reclassification_queue_filter = 'pending';
		}
		$questionable_truncated = isset($_GET['pmm_questionable_truncated']) ? (int) $_GET['pmm_questionable_truncated'] : 0;
		$questionable_expected_count = isset($_GET['pmm_questionable_expected_count']) ? (int) $_GET['pmm_questionable_expected_count'] : 0;
		$hidden_updated = isset($_GET['pmm_hidden_updated']) ? (int) $_GET['pmm_hidden_updated'] : -1;
		$entity_reviewed = isset($_GET['pmm_entity_reviewed']) ? (int) $_GET['pmm_entity_reviewed'] : -1;
		$entity_truncated = isset($_GET['pmm_entity_truncated']) ? (int) $_GET['pmm_entity_truncated'] : 0;
		$entity_expected_count = isset($_GET['pmm_entity_expected_count']) ? (int) $_GET['pmm_entity_expected_count'] : 0;
		$entity_queue_cleared = isset($_GET['pmm_entity_queue_cleared']) ? (int) $_GET['pmm_entity_queue_cleared'] : 0;
		$entity_queue_filter = isset($_GET['pmm_entity_queue_filter']) ? sanitize_key((string) wp_unslash($_GET['pmm_entity_queue_filter'])) : 'pending';
		if (!in_array($entity_queue_filter, ['pending', 'reviewed', 'all'], true)) {
			$entity_queue_filter = 'pending';
		}
		$raw_previewed = isset($_GET['pmm_raw_previewed']) ? (int) $_GET['pmm_raw_previewed'] : -1;
		$raw_preview_saved = isset($_GET['pmm_raw_preview_saved']) ? (int) $_GET['pmm_raw_preview_saved'] : -1;
		$raw_staged = isset($_GET['pmm_raw_staged']) ? (int) $_GET['pmm_raw_staged'] : -1;
		$raw_marked_reviewed = isset($_GET['pmm_raw_marked_reviewed']) ? (int) $_GET['pmm_raw_marked_reviewed'] : -1;
		$raw_cleared = isset($_GET['pmm_raw_cleared']) ? (int) $_GET['pmm_raw_cleared'] : 0;
		$entity_updated = isset($_GET['pmm_entity_updated']) ? (int) $_GET['pmm_entity_updated'] : 0;
		$preview_saved = isset($_GET['pmm_preview_saved']) ? (int) $_GET['pmm_preview_saved'] : 0;
		$alias_saved = isset($_GET['pmm_alias_saved']) ? (int) $_GET['pmm_alias_saved'] : -1;
		$confirmed_imported = isset($_GET['pmm_confirmed_imported']) ? (int) $_GET['pmm_confirmed_imported'] : -1;
		$confirmed_updated = isset($_GET['pmm_confirmed_updated']) ? (int) $_GET['pmm_confirmed_updated'] : 0;
		$confirmed_section = isset($_GET['pmm_confirmed_section']) ? sanitize_text_field(wp_unslash($_GET['pmm_confirmed_section'])) : '';
		$confirmed_saved = isset($_GET['pmm_confirmed_saved']) ? (int) $_GET['pmm_confirmed_saved'] : -1;
		$reprocessed = isset($_GET['pmm_reprocessed']) ? (int) $_GET['pmm_reprocessed'] : 0;
		$global_replaced = isset($_GET['pmm_global_replaced']) ? (int) $_GET['pmm_global_replaced'] : 0;
		$global_renamed = isset($_GET['pmm_global_renamed']) ? (int) $_GET['pmm_global_renamed'] : 0;
		$global_merged = isset($_GET['pmm_global_merged']) ? (int) $_GET['pmm_global_merged'] : 0;
		$global_entries = isset($_GET['pmm_global_entries']) ? (int) $_GET['pmm_global_entries'] : 0;
		$global_scope = isset($_GET['pmm_global_scope']) ? sanitize_key(wp_unslash($_GET['pmm_global_scope'])) : 'both';
		$entry_convert_section = isset($_GET['pmm_entry_convert_section']) ? sanitize_text_field(wp_unslash($_GET['pmm_entry_convert_section'])) : 'all';
		$entry_convert_entity = isset($_GET['pmm_entry_convert_entity']) ? sanitize_text_field(wp_unslash($_GET['pmm_entry_convert_entity'])) : '';
		$entry_convert_search = isset($_GET['pmm_entry_convert_search']) ? sanitize_text_field(wp_unslash($_GET['pmm_entry_convert_search'])) : '';
		$entry_convert_include_mentions = isset($_GET['pmm_entry_convert_include_mentions']) ? ((int) $_GET['pmm_entry_convert_include_mentions'] === 1) : null;
		$entry_convert_load = isset($_GET['pmm_entry_convert_load']) ? ((int) $_GET['pmm_entry_convert_load'] === 1) : false;
		$entry_convert_page = isset($_GET['pmm_entry_convert_page']) ? max(1, (int) $_GET['pmm_entry_convert_page']) : 1;
		$entry_convert_per_page = isset($_GET['pmm_entry_convert_per_page']) ? max(25, min(100, (int) $_GET['pmm_entry_convert_per_page'])) : 50;
		$entry_convert_saved = isset($_GET['pmm_entry_convert_saved']) ? (int) $_GET['pmm_entry_convert_saved'] : -1;
		$entry_convert_reviewed = isset($_GET['pmm_entry_convert_reviewed']) ? (int) $_GET['pmm_entry_convert_reviewed'] : 0;
		$entry_convert_moved = isset($_GET['pmm_entry_convert_moved']) ? (int) $_GET['pmm_entry_convert_moved'] : 0;
		$entry_convert_removed = isset($_GET['pmm_entry_convert_removed']) ? (int) $_GET['pmm_entry_convert_removed'] : 0;
		$entry_convert_updated = isset($_GET['pmm_entry_convert_updated']) ? (int) $_GET['pmm_entry_convert_updated'] : 0;
		$entity_pruned = isset($_GET['pmm_entity_pruned']) ? (int) $_GET['pmm_entity_pruned'] : 0;
		$prune_preview = isset($_GET['pmm_prune_preview']) ? (int) $_GET['pmm_prune_preview'] : 0;
		$prune_section = isset($_GET['pmm_prune_section']) ? sanitize_text_field(wp_unslash($_GET['pmm_prune_section'])) : '';
		$prune_entity = isset($_GET['pmm_prune_entity']) ? sanitize_text_field(wp_unslash($_GET['pmm_prune_entity'])) : '';
		$prune_before = isset($_GET['pmm_prune_before']) ? (int) $_GET['pmm_prune_before'] : 0;
		$prune_after = isset($_GET['pmm_prune_after']) ? (int) $_GET['pmm_prune_after'] : 0;
		$prune_exact = isset($_GET['pmm_prune_exact']) ? (int) $_GET['pmm_prune_exact'] : 0;
		$prune_near = isset($_GET['pmm_prune_near']) ? (int) $_GET['pmm_prune_near'] : 0;
		$prune_stale = isset($_GET['pmm_prune_stale']) ? (int) $_GET['pmm_prune_stale'] : 0;
		$prune_unref = isset($_GET['pmm_prune_unref']) ? (int) $_GET['pmm_prune_unref'] : 0;
		$prune_entity_mismatch = isset($_GET['pmm_prune_entity_mismatch']) ? (int) $_GET['pmm_prune_entity_mismatch'] : 0;
		$prune_critical = isset($_GET['pmm_prune_critical']) ? (int) $_GET['pmm_prune_critical'] : 0;
		$prune_trimmed = isset($_GET['pmm_prune_trimmed']) ? (int) $_GET['pmm_prune_trimmed'] : 0;
		$prune_reviewed = isset($_GET['pmm_prune_reviewed']) ? (int) $_GET['pmm_prune_reviewed'] : -1;
		$prune_removed = isset($_GET['pmm_prune_removed']) ? (int) $_GET['pmm_prune_removed'] : 0;
		$prune_updated = isset($_GET['pmm_prune_updated']) ? (int) $_GET['pmm_prune_updated'] : 0;
		$prune_marked_critical = isset($_GET['pmm_prune_marked_critical']) ? (int) $_GET['pmm_prune_marked_critical'] : 0;
		$prune_nonprefix_reviewed = isset($_GET['pmm_prune_nonprefix_reviewed']) ? (int) $_GET['pmm_prune_nonprefix_reviewed'] : -1;
		$prune_nonprefix_removed = isset($_GET['pmm_prune_nonprefix_removed']) ? (int) $_GET['pmm_prune_nonprefix_removed'] : 0;
		$prune_nonprefix_updated = isset($_GET['pmm_prune_nonprefix_updated']) ? (int) $_GET['pmm_prune_nonprefix_updated'] : 0;
		$prune_nonprefix_critical = isset($_GET['pmm_prune_nonprefix_critical']) ? (int) $_GET['pmm_prune_nonprefix_critical'] : 0;
		$prune_preview_data = get_transient('pmm_prune_preview_' . get_current_user_id());
		if (!is_array($prune_preview_data)) {
			$prune_preview_data = [];
		}
		$job_id = isset($_GET['pmm_job']) ? sanitize_key(wp_unslash($_GET['pmm_job'])) : '';
		$processing = isset($_GET['pmm_processing']) ? (int) $_GET['pmm_processing'] : 0;
		$job = $job_id ? $this->get_batch_job_state($job_id) : null;
		$progress = $this->build_progress($job);
		$drop_sequences_default = get_option('pmm_drop_sequences', []);
		if (!is_array($drop_sequences_default)) {
			$drop_sequences_default = [];
		}
		$drop_sequences_text = implode("\n", $drop_sequences_default);
		$entity_related_match_mode = get_option('pmm_entity_related_match_mode', 'normal');
		if (!in_array($entity_related_match_mode, ['normal', 'strict'], true)) {
			$entity_related_match_mode = 'normal';
		}
		// Keep recategorization scan opt-in per run; do not keep it sticky between page loads.
		$rescan_sections_enabled = false;
		$rescan_confidence = max(70, min(98, (int) get_option('pmm_rescan_confidence', 84)));
		$rescan_preview_only = ((int) get_option('pmm_rescan_preview_only', 0)) === 1;
		$similarity_threshold_defaults = [
			'characters' => 0.62,
			'organizations' => 0.70,
			'locations' => 0.66,
			'technology' => 0.72,
			'vehicles' => 0.70,
		];
		$similarity_thresholds = get_option('pmm_similarity_thresholds', []);
		if (!is_array($similarity_thresholds)) {
			$similarity_thresholds = [];
		}
		$similarity_thresholds = array_merge($similarity_threshold_defaults, $similarity_thresholds);
		$classification_defaults = [
			'auto_classify_new_entries' => 0,
			'strict_prefix_review_mode' => 1,
			'allow_non_prefix_auto_match' => 0,
			'character_veto' => 1,
			'organizations_min_score' => 2,
			'locations_min_score' => 2,
			'technology_min_score' => 2,
			'vehicles_min_score' => 2,
			'world_building_min_score' => 2,
		];
		$classification_settings = get_option('pmm_classification_settings', []);
		if (!is_array($classification_settings)) {
			$classification_settings = [];
		}
		$classification_settings = array_merge($classification_defaults, $classification_settings);
		$classification_settings['auto_classify_new_entries'] = !empty($classification_settings['auto_classify_new_entries']) ? 1 : 0;
		$classification_settings['strict_prefix_review_mode'] = !empty($classification_settings['strict_prefix_review_mode']) ? 1 : 0;
		$classification_settings['allow_non_prefix_auto_match'] = !empty($classification_settings['allow_non_prefix_auto_match']) ? 1 : 0;
		$classification_settings['character_veto'] = !empty($classification_settings['character_veto']) ? 1 : 0;
		$classification_settings['organizations_min_score'] = max(1, min(3, (int) $classification_settings['organizations_min_score']));
		$classification_settings['locations_min_score'] = max(1, min(3, (int) $classification_settings['locations_min_score']));
		$classification_settings['technology_min_score'] = max(1, min(3, (int) $classification_settings['technology_min_score']));
		$classification_settings['vehicles_min_score'] = max(1, min(3, (int) $classification_settings['vehicles_min_score']));
		$classification_settings['world_building_min_score'] = max(1, min(3, (int) $classification_settings['world_building_min_score']));
		$global_entity_report_defaults = [
			'include_mentions' => 1,
		];
		$global_entity_report_settings = get_option('pmm_global_entity_report_settings', []);
		if (!is_array($global_entity_report_settings)) {
			$global_entity_report_settings = [];
		}
		$global_entity_report_settings = array_merge($global_entity_report_defaults, $global_entity_report_settings);
		$global_entity_report_settings['include_mentions'] = !empty($global_entity_report_settings['include_mentions']) ? 1 : 0;
		if ($entry_convert_include_mentions === null) {
			$entry_convert_include_mentions = !empty($global_entity_report_settings['include_mentions']);
		}
		$questionable_defaults = [
			'min_words' => 4,
			'min_chars' => 18,
			'custom_terms' => [],
		];
		$questionable_settings = get_option('pmm_questionable_settings', []);
		if (!is_array($questionable_settings)) {
			$questionable_settings = [];
		}
		$questionable_settings = array_merge($questionable_defaults, $questionable_settings);
		$questionable_min_words = max(2, min(12, (int) $questionable_settings['min_words']));
		$questionable_min_chars = max(8, min(80, (int) $questionable_settings['min_chars']));
		$questionable_terms_text = '';
		if (!empty($questionable_settings['custom_terms']) && is_array($questionable_settings['custom_terms'])) {
			$questionable_terms_text = implode("\n", array_map('strval', $questionable_settings['custom_terms']));
		}
		$questionable_review_queue = get_option('pmm_questionable_review_queue_' . get_current_user_id(), []);
		if (!is_array($questionable_review_queue)) {
			$questionable_review_queue = [];
		}
		$similarity_review_queue = get_option('pmm_similarity_review_queue_' . get_current_user_id(), []);
		if (!is_array($similarity_review_queue)) {
			$similarity_review_queue = [];
		}
		$reclassification_review_queue = get_option('pmm_reclassification_review_queue_' . get_current_user_id(), []);
		if (!is_array($reclassification_review_queue)) {
			$reclassification_review_queue = [];
		}
		$entity_review_queue = get_option('pmm_entity_review_queue_' . get_current_user_id(), []);
		if (!is_array($entity_review_queue)) {
			$entity_review_queue = [];
		}
		$similarity_log = get_option('pmm_similarity_review_log', []);
		if (!is_array($similarity_log)) {
			$similarity_log = [];
		}
		$raw_preview_key = 'pmm_raw_import_preview_' . get_current_user_id();
		$raw_preview = get_option($raw_preview_key, null);
		if (!is_array($raw_preview) || !isset($raw_preview['rows']) || !is_array($raw_preview['rows'])) {
			$legacy_raw_preview = get_transient($raw_preview_key);
			if (is_array($legacy_raw_preview) && isset($legacy_raw_preview['rows']) && is_array($legacy_raw_preview['rows'])) {
				$raw_preview = $legacy_raw_preview;
				update_option($raw_preview_key, $raw_preview, false);
				delete_transient($raw_preview_key);
			} else {
				$raw_preview = ['raw_text' => '', 'rows' => []];
			}
		}
		$raw_preview_rows = (isset($raw_preview['rows']) && is_array($raw_preview['rows'])) ? $raw_preview['rows'] : [];
		$raw_preview_text = isset($raw_preview['raw_text']) ? (string) $raw_preview['raw_text'] : '';
		if (!empty($raw_preview_rows)) {
			$display_sources = [];
			foreach ($raw_preview_rows as $preview_row) {
				if (!is_array($preview_row)) {
					continue;
				}
				$bullet = isset($preview_row['bullet']) ? trim((string) $preview_row['bullet']) : '';
				if ($bullet === '') {
					continue;
				}
				$source = isset($preview_row['source']) ? trim((string) $preview_row['source']) : '';
				if ($source !== '') {
					$display_sources[] = $source;
				}
			}

			if (!empty($display_sources)) {
				$raw_preview_text = implode("\n", $display_sources);
			}
		}
		$raw_preview_alias_signature = isset($raw_preview['alias_signature']) ? (string) $raw_preview['alias_signature'] : '';
		$raw_preview_saved_at = isset($raw_preview['saved_at']) ? (int) $raw_preview['saved_at'] : 0;
		$raw_confidence_threshold = isset($_GET['pmm_raw_confidence_threshold']) ? max(1, min(99, (int) $_GET['pmm_raw_confidence_threshold'])) : 92;
		$raw_stage_mode_notice = isset($_GET['pmm_raw_stage_mode']) ? sanitize_key((string) wp_unslash($_GET['pmm_raw_stage_mode'])) : '';
		$raw_preview_high_conf = 0;
		$raw_preview_medium_conf = 0;
		$raw_preview_low_conf = 0;
		foreach ((array) $raw_preview_rows as $raw_row) {
			$confidence = isset($raw_row['confidence']) ? (int) $raw_row['confidence'] : 50;
			if ($confidence >= 85) {
				++$raw_preview_high_conf;
			} elseif ($confidence >= 60) {
				++$raw_preview_medium_conf;
			} else {
				++$raw_preview_low_conf;
			}
		}
		$staged_raw_key = 'pmm_staged_raw_import_' . get_current_user_id();
		$staged_raw_rows = get_option($staged_raw_key, null);
		if (!is_array($staged_raw_rows)) {
			$legacy_staged_raw_rows = get_transient($staged_raw_key);
			if (is_array($legacy_staged_raw_rows)) {
				$staged_raw_rows = $legacy_staged_raw_rows;
				update_option($staged_raw_key, $staged_raw_rows, false);
				delete_transient($staged_raw_key);
			} else {
				$staged_raw_rows = [];
			}
		}
		$staged_raw_rows_text = $this->serialize_staged_raw_import_rows($raw_preview_rows);
		if ($staged_raw_rows_text === '') {
			$staged_raw_rows_text = $this->serialize_staged_raw_import_rows($staged_raw_rows);
		}
		$raw_preview_page = isset($_GET['pmm_raw_preview_page']) ? max(1, (int) $_GET['pmm_raw_preview_page']) : 1;
		$raw_preview_per_page = isset($_GET['pmm_raw_preview_per_page']) ? max(25, min(300, (int) $_GET['pmm_raw_preview_per_page'])) : 100;
		$raw_review_filter = isset($_GET['pmm_raw_review_filter']) ? sanitize_key((string) wp_unslash($_GET['pmm_raw_review_filter'])) : 'pending';
		if (!in_array($raw_review_filter, ['pending', 'reviewed', 'all'], true)) {
			$raw_review_filter = 'pending';
		}
		$raw_row_has_bullet = static function($row) {
			if (!is_array($row)) {
				return false;
			}
			$bullet = isset($row['bullet']) ? trim((string) $row['bullet']) : '';
			return $bullet !== '';
		};
		$raw_preview_active_total = 0;
		$raw_preview_reviewed_total = 0;
		foreach ((array) $raw_preview_rows as $raw_row) {
			if (!$raw_row_has_bullet($raw_row)) {
				continue;
			}
			++$raw_preview_active_total;
			if (!empty($raw_row['reviewed'])) {
				++$raw_preview_reviewed_total;
			}
		}
		$raw_preview_removed_total = max(0, count($raw_preview_rows) - $raw_preview_active_total);
		$raw_preview_pending_total = max(0, $raw_preview_active_total - $raw_preview_reviewed_total);
		$raw_preview_rows_filtered = $raw_preview_rows;
		if ($raw_review_filter === 'pending') {
			$raw_preview_rows_filtered = array_filter($raw_preview_rows, static function($row) {
				$bullet = isset($row['bullet']) ? trim((string) $row['bullet']) : '';
				return $bullet !== '' && empty($row['reviewed']);
			});
		} elseif ($raw_review_filter === 'reviewed') {
			$raw_preview_rows_filtered = array_filter($raw_preview_rows, static function($row) {
				$bullet = isset($row['bullet']) ? trim((string) $row['bullet']) : '';
				return $bullet !== '' && !empty($row['reviewed']);
			});
		} else {
			$raw_preview_rows_filtered = array_filter($raw_preview_rows, static function($row) {
				$bullet = isset($row['bullet']) ? trim((string) $row['bullet']) : '';
				return $bullet !== '';
			});
		}
		$raw_preview_total = count($raw_preview_rows_filtered);
		$raw_preview_total_pages = max(1, (int) ceil($raw_preview_total / max(1, $raw_preview_per_page)));
		if ($raw_preview_page > $raw_preview_total_pages) {
			$raw_preview_page = $raw_preview_total_pages;
		}
		$raw_preview_offset = ($raw_preview_page - 1) * $raw_preview_per_page;
		$raw_preview_rows_page = array_slice($raw_preview_rows_filtered, $raw_preview_offset, $raw_preview_per_page, true);
		$rules_dirty = get_option('pmm_output_rules_dirty', '0') === '1';
		$rules_dirty_at = (int) get_option('pmm_output_rules_dirty_at', 0);
		$latest_version_file = (string) get_option('pmm_latest_version_filename', '');
		$latest_version_saved_at = (int) get_option('pmm_latest_version_saved_at', 0);
		$latest_version_public_url = '';
		if ($latest_version_file !== '') {
			$latest_version_public_url = $this->get_version_public_url($latest_version_file);
		}
		$version_history = get_option('pmm_version_history', []);
		if (!is_array($version_history)) {
			$version_history = [];
		}
		$version_history = array_slice($version_history, 0, 10);
		$confirmed_registry = $this->get_confirmed_entity_registry_option();
		$confirmed_section_text_map = [];
		foreach ($this->entity_sections() as $entity_section_name) {
			$section_rows = isset($confirmed_registry[$entity_section_name]) && is_array($confirmed_registry[$entity_section_name]) ? $confirmed_registry[$entity_section_name] : [];
			$section_names = [];
			foreach ($section_rows as $row) {
				if (!is_array($row)) {
					continue;
				}
				$name = isset($row['name']) ? trim((string) $row['name']) : '';
				if ($name !== '') {
					$section_names[] = $name;
				}
			}
			$section_names = array_values(array_unique($section_names));
			sort($section_names, SORT_NATURAL | SORT_FLAG_CASE);
			$confirmed_section_text_map[$entity_section_name] = implode("\n", $section_names);
		}
		$confirmed_edit_section = ($confirmed_section !== '' && in_array($confirmed_section, $this->entity_sections(), true)) ? $confirmed_section : 'Characters';

		if (!$has_last_output) {
			$latest_version_path = (string) get_option('pmm_latest_version_file_path', '');
			if ($latest_version_path !== '' && file_exists($latest_version_path) && is_readable($latest_version_path)) {
				$content = file_get_contents($latest_version_path);
				if ($content !== false) {
					$parser = new PMM_Parser();
					$cleaned = $parser->parse((string) $content);
					if (!is_array($cleaned)) {
						$cleaned = [];
					}

					$data = [
						'filename' => $latest_version_file !== '' ? $latest_version_file : basename($latest_version_path),
						'content' => (string) $content,
						'stats' => [
							'sections' => count($cleaned),
							'entities' => PMM_Utils::count_entities($cleaned),
							'bullets' => PMM_Utils::count_bullets($cleaned),
							'mode' => (string) get_option('pmm_last_mode', 'balanced'),
							'format' => (string) get_option('pmm_last_format', 'txt'),
							'original_filename' => $latest_version_file !== '' ? $latest_version_file : basename($latest_version_path),
							'version_filename' => $latest_version_file,
							'version_saved_at' => $latest_version_saved_at,
						],
						'cleaned_data' => $cleaned,
						'entity_report' => [],
					];

					$has_last_output = true;
					set_transient('pmm_last_output_' . get_current_user_id(), $data, 30 * MINUTE_IN_SECONDS);
				}
			}
		}

		$entity_report_rebuilt = false;
		if (!empty($data['cleaned_data']) && is_array($data['cleaned_data'])) {
			$has_entity_groups = isset($data['entity_report']['entities']) && is_array($data['entity_report']['entities']);
			if (!$has_entity_groups) {
				$data['entity_report'] = $this->build_fallback_entity_report((array) $data['cleaned_data']);
				$entity_report_rebuilt = true;
				if ($has_last_output) {
					set_transient('pmm_last_output_' . get_current_user_id(), $data, 30 * MINUTE_IN_SECONDS);
				}
			}
		}

		$edit_section = isset($_GET['pmm_edit_section']) ? sanitize_text_field(wp_unslash($_GET['pmm_edit_section'])) : 'Characters';
		$edit_entity = isset($_GET['pmm_edit_entity']) ? sanitize_text_field(wp_unslash($_GET['pmm_edit_entity'])) : '';
		if ($prune_section !== '') {
			$edit_section = $prune_section;
		}
		if ($prune_entity !== '') {
			$edit_entity = $prune_entity;
		}
		$edit_entries_text = '';
		$edit_rendered_text = '';
		$edit_entity_options = [];
		$preview_sections = [];
		$preview_entities = [];
		$global_entity_section_options = [];
		$entry_convert_rows = [];
		$entry_convert_total = 0;
		$entry_convert_total_pages = 1;
		$entry_convert_snapshot = '';
		$entry_convert_section_options = [
			'New Entries' => 'New Entries',
			'all' => __('All Sections', 'perchance-memory-manager'),
		];
		$entry_convert_entity_options = [];
		$entity_ajax_nonce = wp_create_nonce('pmm_get_entities_for_section');
		$recent_actions = [];
		if ($success && !empty($data)) {
			$recent_actions[] = __('File processed successfully.', 'perchance-memory-manager');
		}
		if ($similarity_saved > 0) {
			$recent_actions[] = sprintf(__('Saved %d similarity decisions.', 'perchance-memory-manager'), $similarity_saved);
		}
		if ($questionable_saved > 0) {
			$recent_actions[] = sprintf(__('Saved %d questionable entry decisions.', 'perchance-memory-manager'), $questionable_saved);
		}
		if ($questionable_updated > 0) {
			$recent_actions[] = sprintf(__('Applied %d direct questionable-entry edits to latest output.', 'perchance-memory-manager'), $questionable_updated);
		}
		if ($reclassification_saved >= 0) {
			$recent_actions[] = sprintf(__('Saved %d reclassification review decisions.', 'perchance-memory-manager'), $reclassification_saved);
		}
		if ($reclassification_moved > 0) {
			$recent_actions[] = sprintf(__('Moved %d entries into better-matching sections.', 'perchance-memory-manager'), $reclassification_moved);
		}
		if ($entity_saved >= 0) {
			$recent_actions[] = sprintf(__('Saved %d entity review decisions.', 'perchance-memory-manager'), $entity_saved);
		}
		if ($hidden_updated >= 0) {
			$recent_actions[] = sprintf(__('Unhid %d entities.', 'perchance-memory-manager'), $hidden_updated);
		}
		if ($raw_staged >= 0) {
			$recent_actions[] = sprintf(__('Staged %d raw import rows.', 'perchance-memory-manager'), $raw_staged);
		}
		if ($raw_marked_reviewed >= 0) {
			$recent_actions[] = sprintf(__('Marked %d raw import rows as reviewed from confidence filter.', 'perchance-memory-manager'), $raw_marked_reviewed);
		}
		if ($entity_updated) {
			$recent_actions[] = __('Entity workspace update saved.', 'perchance-memory-manager');
		}
		if ($preview_saved) {
			$recent_actions[] = __('Editable preview saved as latest output.', 'perchance-memory-manager');
		}
		if ($reprocessed) {
			$recent_actions[] = __('Reprocess started from previous output.', 'perchance-memory-manager');
		}
		if ($global_replaced) {
			$recent_actions[] = sprintf(__('Global search and replace applied (%d entity renames, %d merges, %d entry updates).', 'perchance-memory-manager'), $global_renamed, $global_merged, $global_entries);
		}
		if ($entity_report_rebuilt) {
			$recent_actions[] = __('Entity report was rebuilt from current output because report data was missing.', 'perchance-memory-manager');
		}
		if (!empty($data['cleaned_data']) && is_array($data['cleaned_data'])) {
			$edit_entity_options = $this->entity_options_for_section((array) $data['cleaned_data'], $edit_section);
			if ($edit_entity !== '' && !in_array($edit_entity, $edit_entity_options, true)) {
				$edit_entity = '';
			}
			$edit_entries_text = $this->entity_entries_text((array) $data['cleaned_data'], $edit_section, $edit_entity);
			$edit_rendered_text = $this->entity_rendered_preview_text((array) $data['cleaned_data'], $edit_section, $edit_entity);

			foreach ((array) $data['cleaned_data'] as $sec => $entities) {
				$sec = trim((string) $sec);
				if ($sec === '') {
					continue;
				}
				$preview_sections[$sec] = true;
				if (!is_array($entities)) {
					continue;
				}
				foreach ($entities as $name => $items) {
					if (strpos((string) $name, '__') === 0) {
						continue;
					}
					$name = trim((string) $name);
					if ($name !== '') {
						$preview_entities[$name] = true;
					}
				}
			}

			$preview_sections = array_keys($preview_sections);
			sort($preview_sections, SORT_NATURAL | SORT_FLAG_CASE);
			$preview_entities = array_keys($preview_entities);
			sort($preview_entities, SORT_NATURAL | SORT_FLAG_CASE);
			$known_entities_by_section = $this->known_entities_for_raw_review($confirmed_registry, (array) $data['cleaned_data']);
			$all_sections_for_report = ['New Entries', 'Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation', 'World Building', 'Relationships', 'NSFW', 'Notes'];
			$entry_convert_section_options = [];
			foreach ($all_sections_for_report as $sec) {
				$section_entities = $this->entity_options_for_section((array) $data['cleaned_data'], $sec);
				if (isset($known_entities_by_section[$sec]) && is_array($known_entities_by_section[$sec])) {
					$section_entities = array_values(array_unique(array_merge($section_entities, $known_entities_by_section[$sec])));
					sort($section_entities, SORT_NATURAL | SORT_FLAG_CASE);
				}
				$global_entity_section_options[$sec] = $section_entities;
				$entry_convert_section_options[$sec] = $sec;
			}
			$entry_convert_section_options['all'] = __('All Sections', 'perchance-memory-manager');

			if (!empty($known_entities_by_section)) {
				$all_known = [];
				foreach ($known_entities_by_section as $known_list) {
					if (!is_array($known_list)) {
						continue;
					}
					$all_known = array_merge($all_known, $known_list);
				}
				if (!empty($all_known)) {
					$preview_entities = array_values(array_unique(array_merge($preview_entities, $all_known)));
					sort($preview_entities, SORT_NATURAL | SORT_FLAG_CASE);
				}
			}

			if (!isset($entry_convert_section_options[$entry_convert_section])) {
				$entry_convert_section = 'all';
			}

			$entry_convert_entity_options = [];
			if ($entry_convert_section === 'all') {
				$entry_convert_entity_options = $preview_entities;
			} elseif (isset($global_entity_section_options[$entry_convert_section]) && is_array($global_entity_section_options[$entry_convert_section])) {
				$entry_convert_entity_options = $global_entity_section_options[$entry_convert_section];
			}

			if ($entry_convert_entity !== '' && !in_array($entry_convert_entity, $entry_convert_entity_options, true)) {
				$entry_convert_entity = '';
			}

			if ($entry_convert_load) {
				$all_entry_convert_rows = $this->build_entry_conversion_rows((array) $data['cleaned_data'], [
					'section' => $entry_convert_section,
					'entity' => $entry_convert_entity,
					'search' => $entry_convert_search,
					'include_mentions' => $entry_convert_include_mentions ? 1 : 0,
				]);
				$entry_convert_total = count($all_entry_convert_rows);
				$entry_convert_total_pages = max(1, (int) ceil($entry_convert_total / max(1, $entry_convert_per_page)));
				$entry_convert_page = min($entry_convert_total_pages, max(1, $entry_convert_page));
				$entry_convert_offset = ($entry_convert_page - 1) * $entry_convert_per_page;
				$entry_convert_rows = array_slice($all_entry_convert_rows, $entry_convert_offset, $entry_convert_per_page);
			}

			$entry_convert_snapshot = md5((string) ($data['content'] ?? ''));
		}
		?>
		<div class="wrap pmm-wrap">
			<h1><?php esc_html_e('Perchance', 'perchance-memory-manager'); ?></h1>

			<div class="notice notice-info">
				<p><?php esc_html_e('Upload a Perchance Chat lore or memory file, even character or world information (.txt or .md), to clean, reorganize and manage.', 'perchance-memory-manager'); ?></p>
			</div>

			<?php if ($latest_version_public_url !== '') : ?>
				<div class="notice notice-info">
					<p><strong><?php esc_html_e('Latest Version URL:', 'perchance-memory-manager'); ?></strong> <?php esc_html_e('Use this direct link in Perchance for the newest saved file.', 'perchance-memory-manager'); ?></p>
					<p>
						<input type="text" class="large-text code" readonly value="<?php echo esc_attr($latest_version_public_url); ?>" onclick="this.select();">
						<a class="button" href="<?php echo esc_url($latest_version_public_url); ?>" target="_blank" rel="noopener" style="margin-top:6px;"><?php esc_html_e('Open URL', 'perchance-memory-manager'); ?></a>
						<a class="button button-primary" href="<?php echo esc_url($this->get_download_url()); ?>" style="margin-top:6px;"><?php esc_html_e('Download Cleaned File', 'perchance-memory-manager'); ?></a>
					</p>
				</div>
			<?php endif; ?>

			<?php if ($error) : ?>
				<div class="notice notice-error"><p><?php echo esc_html($this->get_error_message($error)); ?></p></div>
			<?php endif; ?>

			<?php if ($success && !empty($data)) : ?>
				<div class="notice notice-success"><p><?php esc_html_e('File processed successfully.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($similarity_saved > 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Saved %d similarity review decisions. Re-process a file to apply them.', 'perchance-memory-manager'), $similarity_saved)); ?></p></div>
			<?php endif; ?>

			<?php if ($similarity_truncated && $similarity_expected_count > 0) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html(sprintf(__('Only %1$d of %2$d similarity rows were received when saving. This usually means PHP input limits truncated the form submission (for example, max_input_vars).', 'perchance-memory-manager'), max(0, $similarity_reviewed), $similarity_expected_count)); ?></p></div>
			<?php endif; ?>

			<?php if ($similarity_queue_cleared) : ?>
				<div class="notice notice-info"><p><?php esc_html_e('Similarity review queue state was cleared for this user.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($questionable_saved > 0) : ?>
				<?php
				$reviewed_count = $questionable_reviewed >= 0 ? $questionable_reviewed : $questionable_saved;
				$changed_count = $questionable_changed >= 0 ? $questionable_changed : $questionable_saved;
				$summary = sprintf(
					__('Questionable review saved: %1$d reviewed, %2$d changed (remove: %3$d, hide: %4$d, keep: %5$d, update: %6$d).', 'perchance-memory-manager'),
					$reviewed_count,
					$changed_count,
					max(0, $questionable_removed),
					max(0, $questionable_hidden),
					max(0, $questionable_kept),
					max(0, $questionable_updated)
				);
				?>
				<div class="notice notice-success"><p><?php echo esc_html($summary); ?></p></div>
			<?php endif; ?>

			<?php if ($questionable_truncated && $questionable_expected_count > 0) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html(sprintf(__('Only %1$d of %2$d questionable rows were received when saving. This usually means PHP input limits truncated the form submission (for example, max_input_vars).', 'perchance-memory-manager'), max(0, $questionable_reviewed), $questionable_expected_count)); ?></p></div>
			<?php endif; ?>

			<?php if ($rules_dirty && $has_last_output) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e('Reprocess recommended:', 'perchance-memory-manager'); ?></strong>
						<?php esc_html_e('output-affecting rules or staging changed and are not fully reflected in the latest output yet.', 'perchance-memory-manager'); ?>
					</p>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pmm-process-settings-form" style="margin:0 0 8px 0;">
						<?php wp_nonce_field('pmm_reprocess_last_output'); ?>
						<input type="hidden" name="action" value="pmm_reprocess_last_output">
						<p class="description" style="margin:0 0 8px 0;"><?php esc_html_e('This uses the current settings from the main Upload and Process table, including recategorization scan options.', 'perchance-memory-manager'); ?></p>
						<?php submit_button(__('Reprocess Last Output Now', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
					</form>
				</div>
			<?php endif; ?>

			<?php if ($questionable_updated > 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Applied %d direct questionable-entry edits (section/entity/entry updates) to the latest output.', 'perchance-memory-manager'), $questionable_updated)); ?></p></div>
			<?php endif; ?>

			<?php if ($questionable_queue_cleared) : ?>
				<div class="notice notice-info"><p><?php esc_html_e('Questionable review queue state was cleared for this user.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($entity_report_rebuilt) : ?>
				<div class="notice notice-info"><p><?php esc_html_e('Entity report was rebuilt from the current output because report data was missing. Similar/questionable suggestions may remain sparse until the next full process/reprocess run.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($reclassification_truncated && $reclassification_expected_count > 0) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html(sprintf(__('Only %1$d of %2$d reclassification rows were received when saving. This usually means PHP input limits truncated the form submission (for example, max_input_vars).', 'perchance-memory-manager'), max(0, $reclassification_reviewed), $reclassification_expected_count)); ?></p></div>
			<?php endif; ?>

			<?php if ($reclassification_queue_cleared) : ?>
				<div class="notice notice-info"><p><?php esc_html_e('Reclassification review queue state was cleared for this user.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($entity_saved >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Saved %d entity review decisions.', 'perchance-memory-manager'), $entity_saved)); ?></p></div>
			<?php endif; ?>

			<?php if ($entity_truncated && $entity_expected_count > 0) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html(sprintf(__('Only %1$d of %2$d entity-review rows were received when saving. This usually means PHP input limits truncated the form submission (for example, max_input_vars). Use the page controls in Entity Review to process in smaller chunks.', 'perchance-memory-manager'), max(0, $entity_reviewed), $entity_expected_count)); ?></p></div>
			<?php endif; ?>

			<?php if ($entity_queue_cleared) : ?>
				<div class="notice notice-info"><p><?php esc_html_e('Entity review queue state was cleared for this user.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($hidden_updated >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Unhid %d hidden entities.', 'perchance-memory-manager'), $hidden_updated)); ?></p></div>
			<?php endif; ?>

			<?php if ($raw_previewed >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Previewed %d raw import entries. Edit the staging table before your next upload or reprocess.', 'perchance-memory-manager'), $raw_previewed)); ?></p></div>
			<?php endif; ?>

			<?php if ($raw_preview_saved >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Saved edits for %d preview rows. You can safely continue paging without losing this work.', 'perchance-memory-manager'), $raw_preview_saved)); ?></p></div>
			<?php endif; ?>

			<?php if ($raw_staged >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Staged %d raw import rows for the next upload or reprocess.', 'perchance-memory-manager'), $raw_staged)); ?></p></div>
			<?php endif; ?>

			<?php if ($raw_marked_reviewed >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Marked %d raw import rows as reviewed from the selected confidence filter. Existing staged rows were not changed.', 'perchance-memory-manager'), $raw_marked_reviewed)); ?></p></div>
			<?php endif; ?>

			<?php if ($raw_cleared) : ?>
				<div class="notice notice-info"><p><?php esc_html_e('Cleared raw import preview data.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($entity_updated) : ?>
				<div class="notice notice-success"><p><?php esc_html_e('Entity workspace update saved and output refreshed.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($preview_saved) : ?>
				<div class="notice notice-success"><p><?php esc_html_e('Preview changes saved as the latest output version.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($alias_saved >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Saved %d alias rules.', 'perchance-memory-manager'), $alias_saved)); ?></p></div>
			<?php endif; ?>

			<?php if ($confirmed_imported >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Imported %1$d new confirmed entities and updated %2$d existing records for section %3$s.', 'perchance-memory-manager'), max(0, $confirmed_imported), max(0, $confirmed_updated), ($confirmed_section !== '' ? $confirmed_section : __('(unknown)', 'perchance-memory-manager')))); ?></p></div>
			<?php endif; ?>

			<?php if ($confirmed_saved >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Saved %1$d confirmed entities for section %2$s.', 'perchance-memory-manager'), max(0, $confirmed_saved), ($confirmed_section !== '' ? $confirmed_section : __('(unknown)', 'perchance-memory-manager')))); ?></p></div>
			<?php endif; ?>

			<?php if ($reprocessed) : ?>
				<div class="notice notice-info"><p><?php esc_html_e('Reprocessing started from last output. No re-upload needed.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($global_replaced) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Global search and replace completed in %1$s scope. Renamed %2$d entity buckets, merged %3$d duplicates, and updated %4$d entries.', 'perchance-memory-manager'), $global_scope, $global_renamed, $global_merged, $global_entries)); ?></p></div>
			<?php endif; ?>

			<?php if ($entry_convert_saved >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Entry conversion saved: %1$d reviewed, %2$d changed (moved: %3$d, removed: %4$d, text-updated: %5$d).', 'perchance-memory-manager'), max(0, $entry_convert_reviewed), max(0, $entry_convert_saved), max(0, $entry_convert_moved), max(0, $entry_convert_removed), max(0, $entry_convert_updated))); ?></p></div>
			<?php endif; ?>

			<?php if ($entity_pruned) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Entity prune completed: %1$d -> %2$d entries (exact duplicates removed: %3$d, near duplicates removed: %4$d, stale removed: %5$d, unreferenced removed: %6$d, missing selected entity name removed: %7$d, critical entries preserved: %8$d, trimmed to cap: %9$d).', 'perchance-memory-manager'), max(0, $prune_before), max(0, $prune_after), max(0, $prune_exact), max(0, $prune_near), max(0, $prune_stale), max(0, $prune_unref), max(0, $prune_entity_mismatch), max(0, $prune_critical), max(0, $prune_trimmed))); ?></p></div>
			<?php endif; ?>

			<?php if ($prune_preview && !empty($prune_preview_data['stats']) && is_array($prune_preview_data['stats'])) : ?>
				<div class="notice notice-info"><p><?php esc_html_e('Prune preview generated. No output changes were saved yet.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($prune_reviewed >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Prune candidate review applied: %1$d reviewed, %2$d removed, %3$d updated, %4$d marked critical.', 'perchance-memory-manager'), max(0, $prune_reviewed), max(0, $prune_removed), max(0, $prune_updated), max(0, $prune_marked_critical))); ?></p></div>
			<?php endif; ?>

			<?php if ($prune_nonprefix_reviewed >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Non-prefix entry review applied: %1$d reviewed, %2$d removed, %3$d updated, %4$d marked critical.', 'perchance-memory-manager'), max(0, $prune_nonprefix_reviewed), max(0, $prune_nonprefix_removed), max(0, $prune_nonprefix_updated), max(0, $prune_nonprefix_critical))); ?></p></div>
			<?php endif; ?>

			<?php if ($rules_dirty) : ?>
				<div class="notice notice-warning"><p>
					<?php
					echo esc_html(
						sprintf(
							__('Output-affecting rules changed%1$s. Reprocess to bring preview/download in sync.', 'perchance-memory-manager'),
							$rules_dirty_at > 0 ? ' (' . wp_date('Y-m-d H:i', $rules_dirty_at) . ')' : ''
						)
					);
					?>
				</p></div>
			<?php else : ?>
				<div class="notice notice-success"><p><?php esc_html_e('Output is in sync. Saving in Entity Workspace or Editable Preview updates the latest output directly; reprocess is only needed after rule/staging changes.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($processing && !empty($job)) : ?>
				<div class="notice notice-info">
					<p>
						<?php
						echo esc_html(
							sprintf(
								__('Processing in safe batches: %1$s (%2$s%%). Keep this tab open until complete.', 'perchance-memory-manager'),
								$progress['label'],
								$progress['percent']
							)
						);
						?>
					</p>
				</div>

				<div class="pmm-card">
					<h2><?php esc_html_e('Batch Processing', 'perchance-memory-manager'); ?></h2>
					<p><?php echo esc_html($progress['detail']); ?></p>
					<progress max="100" value="<?php echo esc_attr((string) $progress['percent']); ?>" style="width:100%;height:18px;"></progress>

					<form id="pmm-batch-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 12px;">
						<?php wp_nonce_field('pmm_process_batch'); ?>
						<input type="hidden" name="action" value="pmm_process_batch">
						<input type="hidden" name="pmm_job" value="<?php echo esc_attr($job_id); ?>">
						<noscript><?php submit_button(__('Process Next Batch', 'perchance-memory-manager'), 'secondary', 'submit', false); ?></noscript>
					</form>
				</div>

				<script>
					document.addEventListener('DOMContentLoaded', function () {
						window.setTimeout(function () {
							var form = document.getElementById('pmm-batch-form');
							if (form) {
								form.submit();
							}
						}, 250);
					});
				</script>
			<?php endif; ?>

			<div class="pmm-card">
				<h2><?php esc_html_e('Upload and Process', 'perchance-memory-manager'); ?></h2>
				<?php if ($latest_version_file !== '') : ?>
					<p class="description"><?php echo esc_html(sprintf(__('Latest version file: %1$s%2$s', 'perchance-memory-manager'), $latest_version_file, $latest_version_saved_at > 0 ? ' (' . wp_date('Y-m-d H:i', $latest_version_saved_at) . ')' : '')); ?></p>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pmm-process-settings-form" style="margin-bottom:12px;">
						<?php wp_nonce_field('pmm_process_latest_version'); ?>
						<input type="hidden" name="action" value="pmm_process_latest_version">
						<?php submit_button(__('Process Latest Saved Version File', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
					</form>
					<?php if (!empty($version_history)) : ?>
						<details style="margin-bottom:12px;">
							<summary><strong><?php esc_html_e('Recent Versions (Last 10)', 'perchance-memory-manager'); ?></strong></summary>
							<ul style="margin-top:8px;">
								<?php foreach ($version_history as $idx => $item) : ?>
									<?php if (!is_array($item)) { continue; } ?>
									<?php
									$vf = isset($item['filename']) ? (string) $item['filename'] : '';
									$vs = isset($item['saved_at']) ? (int) $item['saved_at'] : 0;
									if ($vf === '') { continue; }
									?>
									<li style="margin-bottom:6px;">
										<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pmm-process-settings-form" style="display:inline;">
											<?php wp_nonce_field('pmm_process_recent_version'); ?>
											<input type="hidden" name="action" value="pmm_process_recent_version">
											<input type="hidden" name="pmm_version_index" value="<?php echo esc_attr((string) $idx); ?>">
											<button type="submit" class="button button-small"><?php esc_html_e('Process', 'perchance-memory-manager'); ?></button>
										</form>
										<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pmm-process-saved-version-form" style="display:inline; margin-left:6px;">
											<?php wp_nonce_field('pmm_download_saved_version'); ?>
											<input type="hidden" name="action" value="pmm_download_saved_version">
											<input type="hidden" name="pmm_version_index" value="<?php echo esc_attr((string) $idx); ?>">
											<button type="submit" class="button button-small"><?php esc_html_e('Download', 'perchance-memory-manager'); ?></button>
										</form>
										<span style="margin-left:8px;"><?php echo esc_html($vf . ($vs > 0 ? ' (' . wp_date('Y-m-d H:i', $vs) . ')' : '')); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endif; ?>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
					<?php wp_nonce_field('pmm_process_upload'); ?>
					<input type="hidden" name="action" value="pmm_process_upload">

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="pmm_memory_file"><?php esc_html_e('Memory File', 'perchance-memory-manager'); ?></label></th>
							<td><input type="file" id="pmm_memory_file" name="pmm_memory_file" accept=".txt,.md" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="pmm_mode"><?php esc_html_e('Dedupe Mode', 'perchance-memory-manager'); ?></label></th>
							<td>
								<select id="pmm_mode" name="pmm_mode">
									<option value="strict"><?php esc_html_e('Strict', 'perchance-memory-manager'); ?></option>
									<option value="balanced" selected><?php esc_html_e('Balanced', 'perchance-memory-manager'); ?></option>
									<option value="aggressive"><?php esc_html_e('Aggressive', 'perchance-memory-manager'); ?></option>
								</select>
								<p class="description"><?php esc_html_e('Balanced is recommended for most Perchance memory files.', 'perchance-memory-manager'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="pmm_format"><?php esc_html_e('Output Format', 'perchance-memory-manager'); ?></label></th>
							<td>
								<select id="pmm_format" name="pmm_format">
									<option value="txt" <?php selected((string) get_option('pmm_last_format', 'txt'), 'txt'); ?>><?php esc_html_e('Plain Text (.txt)', 'perchance-memory-manager'); ?></option>
									<option value="md" <?php selected((string) get_option('pmm_last_format', 'txt'), 'md'); ?>><?php esc_html_e('Markdown (.md)', 'perchance-memory-manager'); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="pmm_drop_sequences"><?php esc_html_e('Auto-remove Sequences', 'perchance-memory-manager'); ?></label></th>
							<td>
								<textarea id="pmm_drop_sequences" name="pmm_drop_sequences" rows="6" class="large-text code" placeholder="block 1&#10;---&#10;[TEMP] echo"><?php echo esc_textarea($drop_sequences_text); ?></textarea>
								<p class="description"><?php esc_html_e('One sequence per line. Any entry containing one of these sequences will be removed automatically.', 'perchance-memory-manager'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Entity Lists Visibility', 'perchance-memory-manager'); ?></th>
							<td>
								<p><?php esc_html_e('Entity lists are always shown in the results panel. Use the Entity Review area to collapse or expand the sections you want to inspect.', 'perchance-memory-manager'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="pmm_entity_related_match_mode"><?php esc_html_e('Related Entry Removal Sensitivity', 'perchance-memory-manager'); ?></label></th>
							<td>
								<select id="pmm_entity_related_match_mode" name="pmm_entity_related_match_mode">
									<option value="normal" <?php selected($entity_related_match_mode, 'normal'); ?>><?php esc_html_e('Normal (recommended)', 'perchance-memory-manager'); ?></option>
									<option value="strict" <?php selected($entity_related_match_mode, 'strict'); ?>><?php esc_html_e('Strict (fewer related lines removed)', 'perchance-memory-manager'); ?></option>
								</select>
								<p class="description"><?php esc_html_e('Used when "Remove entity and related entries" is applied. Strict mode requires a stronger name mention match.', 'perchance-memory-manager'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Similarity Detection Thresholds', 'perchance-memory-manager'); ?></th>
							<td>
								<p class="description" style="margin-bottom:8px;"><?php esc_html_e('Lower values detect more potential typos/variants; higher values are stricter. Range: 0.40 to 0.98.', 'perchance-memory-manager'); ?></p>
								<label style="display:inline-block;margin-right:12px;"><?php esc_html_e('Characters', 'perchance-memory-manager'); ?>
									<input type="number" step="0.01" min="0.40" max="0.98" id="pmm_similarity_threshold_characters" name="pmm_similarity_threshold_characters" value="<?php echo esc_attr((string) $similarity_thresholds['characters']); ?>" style="width:90px;">
								</label>
								<label style="display:inline-block;margin-right:12px;"><?php esc_html_e('Organizations', 'perchance-memory-manager'); ?>
									<input type="number" step="0.01" min="0.40" max="0.98" id="pmm_similarity_threshold_organizations" name="pmm_similarity_threshold_organizations" value="<?php echo esc_attr((string) $similarity_thresholds['organizations']); ?>" style="width:90px;">
								</label>
								<label style="display:inline-block;margin-right:12px;"><?php esc_html_e('Locations', 'perchance-memory-manager'); ?>
									<input type="number" step="0.01" min="0.40" max="0.98" id="pmm_similarity_threshold_locations" name="pmm_similarity_threshold_locations" value="<?php echo esc_attr((string) $similarity_thresholds['locations']); ?>" style="width:90px;">
								</label>
								<label style="display:inline-block;"><?php esc_html_e('Technology / Systems', 'perchance-memory-manager'); ?>
									<input type="number" step="0.01" min="0.40" max="0.98" id="pmm_similarity_threshold_technology" name="pmm_similarity_threshold_technology" value="<?php echo esc_attr((string) $similarity_thresholds['technology']); ?>" style="width:90px;">
								</label>
								<label style="display:inline-block;margin-left:12px;"><?php esc_html_e('Vehicles / Transportation', 'perchance-memory-manager'); ?>
									<input type="number" step="0.01" min="0.40" max="0.98" id="pmm_similarity_threshold_vehicles" name="pmm_similarity_threshold_vehicles" value="<?php echo esc_attr((string) $similarity_thresholds['vehicles']); ?>" style="width:90px;">
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Section Classification Guards', 'perchance-memory-manager'); ?></th>
							<td>
								<label style="display:block;margin-bottom:8px;">
									<input type="checkbox" id="pmm_auto_classify_new_entries" name="pmm_auto_classify_new_entries" value="1" <?php checked(!empty($classification_settings['auto_classify_new_entries'])); ?>>
									<?php esc_html_e('Auto-classify New Entries during processing (turn off to keep all new rows in New Entries for manual review first)', 'perchance-memory-manager'); ?>
								</label>
								<label style="display:block;margin-bottom:8px;">
									<input type="checkbox" id="pmm_strict_prefix_review_mode" name="pmm_strict_prefix_review_mode" value="1" <?php checked(!empty($classification_settings['strict_prefix_review_mode'])); ?>>
									<?php esc_html_e('Strict mode: only auto-route when the entry starts with an exact known entity or alias; unmatched entries remain in New Entries for review', 'perchance-memory-manager'); ?>
								</label>
								<label style="display:block;margin-bottom:8px;">
									<input type="checkbox" id="pmm_allow_non_prefix_auto_match" name="pmm_allow_non_prefix_auto_match" value="1" <?php checked(!empty($classification_settings['allow_non_prefix_auto_match'])); ?>>
									<?php esc_html_e('Allow fuzzy non-prefix entity auto-match when strict mode does not find a leading match', 'perchance-memory-manager'); ?>
								</label>
								<label style="display:block;margin-bottom:8px;">
									<input type="checkbox" id="pmm_character_fact_veto" name="pmm_character_fact_veto" value="1" <?php checked(!empty($classification_settings['character_veto'])); ?>>
									<?php esc_html_e('Prevent character-style facts from being auto-routed to Organizations, Locations, Technology / Systems, Vehicles / Transportation, or World Building', 'perchance-memory-manager'); ?>
								</label>
								<p class="description" style="margin-bottom:8px;"><?php esc_html_e('Higher minimum score is stricter and reduces false positives. Range: 1 (loose) to 3 (strict).', 'perchance-memory-manager'); ?></p>
								<label style="display:inline-block;margin-right:12px;"><?php esc_html_e('Organizations min score', 'perchance-memory-manager'); ?>
									<input type="number" min="1" max="3" id="pmm_org_min_score" name="pmm_org_min_score" value="<?php echo esc_attr((string) $classification_settings['organizations_min_score']); ?>" style="width:90px;">
								</label>
								<label style="display:inline-block;margin-right:12px;"><?php esc_html_e('Locations min score', 'perchance-memory-manager'); ?>
									<input type="number" min="1" max="3" id="pmm_location_min_score" name="pmm_location_min_score" value="<?php echo esc_attr((string) $classification_settings['locations_min_score']); ?>" style="width:90px;">
								</label>
								<label style="display:inline-block;"><?php esc_html_e('Technology min score', 'perchance-memory-manager'); ?>
									<input type="number" min="1" max="3" id="pmm_technology_min_score" name="pmm_technology_min_score" value="<?php echo esc_attr((string) $classification_settings['technology_min_score']); ?>" style="width:90px;">
								</label>
								<label style="display:inline-block;margin-left:12px;"><?php esc_html_e('Vehicles min score', 'perchance-memory-manager'); ?>
									<input type="number" min="1" max="3" id="pmm_vehicles_min_score" name="pmm_vehicles_min_score" value="<?php echo esc_attr((string) $classification_settings['vehicles_min_score']); ?>" style="width:90px;">
								</label>
								<label style="display:inline-block;margin-left:12px;"><?php esc_html_e('World Building min score', 'perchance-memory-manager'); ?>
									<input type="number" min="1" max="3" id="pmm_world_building_min_score" name="pmm_world_building_min_score" value="<?php echo esc_attr((string) $classification_settings['world_building_min_score']); ?>" style="width:90px;">
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Reprocess Recategorization Scan', 'perchance-memory-manager'); ?></th>
							<td>
								<label>
									<input type="checkbox" id="pmm_rescan_sections" name="pmm_rescan_sections" value="1" <?php checked($rescan_sections_enabled); ?>>
									<?php esc_html_e('During process/reprocess, scan existing entries and auto-move high-confidence section mismatches', 'perchance-memory-manager'); ?>
								</label>
								<div style="margin-top:8px;">
									<label for="pmm_rescan_confidence" style="display:inline-block;margin-right:8px;"><?php esc_html_e('Minimum confidence', 'perchance-memory-manager'); ?></label>
									<input type="range" id="pmm_rescan_confidence" name="pmm_rescan_confidence" min="70" max="98" step="1" value="<?php echo esc_attr((string) $rescan_confidence); ?>" style="width:220px;vertical-align:middle;">
									<input type="number" id="pmm_rescan_confidence_number" min="70" max="98" step="1" value="<?php echo esc_attr((string) $rescan_confidence); ?>" style="width:80px;margin-left:6px;vertical-align:middle;">
								</div>
								<label style="display:block;margin-top:8px;">
									<input type="checkbox" id="pmm_rescan_preview_only" name="pmm_rescan_preview_only" value="1" <?php checked($rescan_preview_only); ?>>
									<?php esc_html_e('Dry run only: preview proposed recategorizations without moving entries', 'perchance-memory-manager'); ?>
								</label>
								<p class="description"><?php esc_html_e('Conservative pass intended for large lore refreshes. You can still review additional suggestions in Automated Reclassification Review.', 'perchance-memory-manager'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Questionable Entry Review Tuning', 'perchance-memory-manager'); ?></th>
							<td>
								<label style="display:inline-block;margin-right:12px;"><?php esc_html_e('Min words to avoid short flag', 'perchance-memory-manager'); ?>
									<input type="number" min="2" max="12" id="pmm_questionable_min_words" name="pmm_questionable_min_words" value="<?php echo esc_attr((string) $questionable_min_words); ?>" style="width:90px;">
								</label>
								<label style="display:inline-block;margin-right:12px;"><?php esc_html_e('Min characters to avoid low-detail flag', 'perchance-memory-manager'); ?>
									<input type="number" min="8" max="80" id="pmm_questionable_min_chars" name="pmm_questionable_min_chars" value="<?php echo esc_attr((string) $questionable_min_chars); ?>" style="width:90px;">
								</label>
								<label for="pmm_questionable_terms" style="display:block;margin-top:8px;"><?php esc_html_e('Custom questionable terms (one per line)', 'perchance-memory-manager'); ?></label>
								<textarea id="pmm_questionable_terms" name="pmm_questionable_terms" rows="4" class="large-text code" placeholder="unknown&#10;todo&#10;placeholder"><?php echo esc_textarea($questionable_terms_text); ?></textarea>
								<p class="description"><?php esc_html_e('Entries containing these terms are surfaced in Questionable Entry Review.', 'perchance-memory-manager'); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button(__('Process File', 'perchance-memory-manager')); ?>
				</form>
				<script>
				document.addEventListener('DOMContentLoaded', function () {
					var source = {
						mode: document.getElementById('pmm_mode'),
						format: document.getElementById('pmm_format'),
						dropSequences: document.getElementById('pmm_drop_sequences'),
						entityRelatedMatchMode: document.getElementById('pmm_entity_related_match_mode'),
						similarityThresholdCharacters: document.getElementById('pmm_similarity_threshold_characters'),
						similarityThresholdOrganizations: document.getElementById('pmm_similarity_threshold_organizations'),
						similarityThresholdLocations: document.getElementById('pmm_similarity_threshold_locations'),
						similarityThresholdTechnology: document.getElementById('pmm_similarity_threshold_technology'),
						similarityThresholdVehicles: document.getElementById('pmm_similarity_threshold_vehicles'),
						rescanSections: document.getElementById('pmm_rescan_sections'),
						rescanConfidence: document.getElementById('pmm_rescan_confidence'),
						rescanConfidenceNumber: document.getElementById('pmm_rescan_confidence_number'),
						rescanPreviewOnly: document.getElementById('pmm_rescan_preview_only'),
						characterFactVeto: document.getElementById('pmm_character_fact_veto'),
						autoClassifyNewEntries: document.getElementById('pmm_auto_classify_new_entries'),
						strictPrefixReviewMode: document.getElementById('pmm_strict_prefix_review_mode'),
						allowNonPrefixAutoMatch: document.getElementById('pmm_allow_non_prefix_auto_match'),
						organizationMinScore: document.getElementById('pmm_org_min_score'),
						locationMinScore: document.getElementById('pmm_location_min_score'),
						technologyMinScore: document.getElementById('pmm_technology_min_score'),
						vehiclesMinScore: document.getElementById('pmm_vehicles_min_score'),
						worldBuildingMinScore: document.getElementById('pmm_world_building_min_score'),
						questionableMinWords: document.getElementById('pmm_questionable_min_words'),
						questionableMinChars: document.getElementById('pmm_questionable_min_chars'),
						questionableTerms: document.getElementById('pmm_questionable_terms')
					};

					var forms = document.querySelectorAll('form.pmm-process-settings-form');
					if (!forms.length) {
						return;
					}

					function upsertHidden(form, name, value) {
						var input = form.querySelector('input[name="' + name + '"]');
						if (!input) {
							input = document.createElement('input');
							input.type = 'hidden';
							input.name = name;
							form.appendChild(input);
						}
						input.value = value;
					}

					forms.forEach(function (form) {
						form.addEventListener('submit', function () {
							upsertHidden(form, 'pmm_mode', source.mode ? source.mode.value : 'balanced');
							upsertHidden(form, 'pmm_format', source.format ? source.format.value : 'txt');
							upsertHidden(form, 'pmm_drop_sequences', source.dropSequences ? source.dropSequences.value : '');
							upsertHidden(form, 'pmm_include_entity_report', '1');
							upsertHidden(form, 'pmm_entity_related_match_mode', source.entityRelatedMatchMode ? source.entityRelatedMatchMode.value : 'normal');
							upsertHidden(form, 'pmm_similarity_threshold_characters', source.similarityThresholdCharacters ? source.similarityThresholdCharacters.value : '0.62');
							upsertHidden(form, 'pmm_similarity_threshold_organizations', source.similarityThresholdOrganizations ? source.similarityThresholdOrganizations.value : '0.70');
							upsertHidden(form, 'pmm_similarity_threshold_locations', source.similarityThresholdLocations ? source.similarityThresholdLocations.value : '0.66');
							upsertHidden(form, 'pmm_similarity_threshold_technology', source.similarityThresholdTechnology ? source.similarityThresholdTechnology.value : '0.72');
							upsertHidden(form, 'pmm_similarity_threshold_vehicles', source.similarityThresholdVehicles ? source.similarityThresholdVehicles.value : '0.70');
							upsertHidden(form, 'pmm_rescan_sections', source.rescanSections && source.rescanSections.checked ? '1' : '0');
							upsertHidden(form, 'pmm_rescan_confidence', source.rescanConfidence ? source.rescanConfidence.value : '84');
							upsertHidden(form, 'pmm_rescan_preview_only', source.rescanPreviewOnly && source.rescanPreviewOnly.checked ? '1' : '0');
							upsertHidden(form, 'pmm_auto_classify_new_entries', source.autoClassifyNewEntries && source.autoClassifyNewEntries.checked ? '1' : '0');
							upsertHidden(form, 'pmm_strict_prefix_review_mode', source.strictPrefixReviewMode && source.strictPrefixReviewMode.checked ? '1' : '0');
							upsertHidden(form, 'pmm_allow_non_prefix_auto_match', source.allowNonPrefixAutoMatch && source.allowNonPrefixAutoMatch.checked ? '1' : '0');
							upsertHidden(form, 'pmm_character_fact_veto', source.characterFactVeto && source.characterFactVeto.checked ? '1' : '0');
							upsertHidden(form, 'pmm_org_min_score', source.organizationMinScore ? source.organizationMinScore.value : '2');
							upsertHidden(form, 'pmm_location_min_score', source.locationMinScore ? source.locationMinScore.value : '2');
							upsertHidden(form, 'pmm_technology_min_score', source.technologyMinScore ? source.technologyMinScore.value : '2');
							upsertHidden(form, 'pmm_vehicles_min_score', source.vehiclesMinScore ? source.vehiclesMinScore.value : '2');
							upsertHidden(form, 'pmm_world_building_min_score', source.worldBuildingMinScore ? source.worldBuildingMinScore.value : '2');
							upsertHidden(form, 'pmm_questionable_min_words', source.questionableMinWords ? source.questionableMinWords.value : '4');
							upsertHidden(form, 'pmm_questionable_min_chars', source.questionableMinChars ? source.questionableMinChars.value : '18');
							upsertHidden(form, 'pmm_questionable_terms', source.questionableTerms ? source.questionableTerms.value : '');
						});
					});

					if (source.rescanConfidence && source.rescanConfidenceNumber) {
						var syncRescanConfidence = function (fromRange) {
							var val = fromRange ? source.rescanConfidence.value : source.rescanConfidenceNumber.value;
							var numeric = parseInt(val, 10);
							if (isNaN(numeric)) {
								numeric = 84;
							}
							numeric = Math.max(70, Math.min(98, numeric));
							source.rescanConfidence.value = String(numeric);
							source.rescanConfidenceNumber.value = String(numeric);
						};
						source.rescanConfidence.addEventListener('input', function () { syncRescanConfidence(true); });
						source.rescanConfidenceNumber.addEventListener('input', function () { syncRescanConfidence(false); });
						syncRescanConfidence(true);
					}
				});
				</script>
			</div>

			<div class="pmm-card">
				<details class="pmm-collapsible-section pmm-collapsible-root">
					<summary><strong><?php esc_html_e('Raw Import Workspace', 'perchance-memory-manager'); ?></strong></summary>
				<div>
				<p><strong><?php esc_html_e('Raw Import Tip:', 'perchance-memory-manager'); ?></strong> <?php esc_html_e('When importing raw text, add a section header "# Raw Import" (or "# New Entries") in your source file. Raw import understands bullet-delimited entries and blank-line-delimited wrapped entries, which matches common Perchance memory formats.', 'perchance-memory-manager'); ?>
				<br><?php esc_html_e('Paste raw import text or upload a raw text file to preview bullet-delimited and blank-line-delimited entry parsing. Wrapped lines are kept with their entry until a blank line or next bullet starts a new one. Then edit tab-separated staging rows (or upload the edited TSV) before the next upload/reprocess.', 'perchance-memory-manager'); ?></p>
			    </div>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
					<?php wp_nonce_field('pmm_preview_raw_import'); ?>
					<input type="hidden" name="action" value="pmm_preview_raw_import">
					<p><input type="file" name="pmm_raw_import_file" accept=".txt,.md,.log,.tsv"></p>
					<textarea name="pmm_raw_import_text" rows="12" class="large-text code" placeholder="# Raw Import&#10;Character is a former pilot with a fractured memory...&#10;Another line from a one-entry-per-line dump"><?php echo esc_textarea($raw_preview_text); ?></textarea>
					<?php submit_button(__('Preview Raw Import', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
				</form>

				<?php if (!empty($raw_preview_rows) || !empty($staged_raw_rows)) : ?>
					<?php if (!empty($raw_preview_rows)) : ?>
						<p class="description" style="margin-top:10px;">
							<?php echo esc_html(sprintf(__('Preview confidence mix: %1$d high (>=85), %2$d medium (60-84), %3$d low (<60). Use confidence staging to avoid reviewing every row manually.', 'perchance-memory-manager'), $raw_preview_high_conf, $raw_preview_medium_conf, $raw_preview_low_conf)); ?>
						</p>
						<p class="description" style="margin-top:6px;"><?php echo esc_html(sprintf(__('Review queue: pending %1$d, reviewed %2$d, active %3$d, removed %4$d.', 'perchance-memory-manager'), $raw_preview_pending_total, $raw_preview_reviewed_total, $raw_preview_active_total, $raw_preview_removed_total)); ?></p>
						<p style="margin:8px 0;">
							<strong><?php esc_html_e('Show:', 'perchance-memory-manager'); ?></strong>
							<a class="button <?php echo $raw_review_filter === 'pending' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg(['pmm_raw_review_filter' => 'pending', 'pmm_raw_preview_page' => false])); ?>"><?php esc_html_e('Pending', 'perchance-memory-manager'); ?></a>
							<a class="button <?php echo $raw_review_filter === 'reviewed' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg(['pmm_raw_review_filter' => 'reviewed', 'pmm_raw_preview_page' => false])); ?>"><?php esc_html_e('Reviewed', 'perchance-memory-manager'); ?></a>
							<a class="button <?php echo $raw_review_filter === 'all' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg(['pmm_raw_review_filter' => 'all', 'pmm_raw_preview_page' => false])); ?>"><?php esc_html_e('All', 'perchance-memory-manager'); ?></a>
						</p>
						<?php if ($raw_stage_mode_notice !== '') : ?>
							<p class="description" style="margin-top:6px;"><strong><?php esc_html_e('Last confidence bulk mode:', 'perchance-memory-manager'); ?></strong> <?php echo esc_html(str_replace('_', ' ', $raw_stage_mode_notice)); ?> (<?php echo esc_html((string) $raw_confidence_threshold); ?>)</p>
						<?php endif; ?>
					<?php endif; ?>
					<p class="description" style="margin-top:10px;"><?php esc_html_e('Primary workflow: review and edit rows directly in Preview Assignments, then save page edits as you navigate. This protects work across long review sessions.', 'perchance-memory-manager'); ?></p>
					<?php if (!empty($raw_preview_rows)) : ?>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:8px 0 12px 0;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
							<?php wp_nonce_field('pmm_stage_raw_import'); ?>
							<input type="hidden" name="action" value="pmm_stage_raw_import">
							<input type="hidden" name="pmm_raw_review_filter" value="<?php echo esc_attr($raw_review_filter); ?>">
							<label>
								<?php esc_html_e('Confidence threshold', 'perchance-memory-manager'); ?><br>
								<input type="number" name="pmm_raw_confidence_threshold" min="1" max="99" value="<?php echo esc_attr((string) $raw_confidence_threshold); ?>" style="width:100px;">
							</label>
							<label>
								<?php esc_html_e('Bulk confidence action', 'perchance-memory-manager'); ?><br>
								<select name="pmm_raw_stage_mode">
									<option value="mark_high_confidence_reviewed"><?php esc_html_e('Mark rows matching confidence threshold as reviewed (default)', 'perchance-memory-manager'); ?></option>
									<option value="high_confidence_only"><?php esc_html_e('Stage high-confidence rows only (>= threshold)', 'perchance-memory-manager'); ?></option>
									<option value="low_confidence_only"><?php esc_html_e('Stage low-confidence review queue only (< threshold)', 'perchance-memory-manager'); ?></option>
									<option value="all_preview_rows"><?php esc_html_e('Stage all preview rows', 'perchance-memory-manager'); ?></option>
									<option value="mark_low_confidence_reviewed"><?php esc_html_e('Mark low-confidence rows as reviewed (no staging)', 'perchance-memory-manager'); ?></option>
									<option value="mark_all_preview_reviewed"><?php esc_html_e('Mark all preview rows as reviewed (no staging)', 'perchance-memory-manager'); ?></option>
								</select>
							</label>
							<?php submit_button(__('Run Bulk Confidence Action', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
						</form>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" id="pmm-raw-stage-form" data-pmm-raw-table-complete="1">
						<?php wp_nonce_field('pmm_stage_raw_import'); ?>
						<input type="hidden" name="action" value="pmm_stage_raw_import">
						<input type="hidden" name="pmm_raw_stage_mode" value="manual">
						<input type="hidden" name="pmm_raw_review_filter" value="<?php echo esc_attr($raw_review_filter); ?>">
						<input type="hidden" name="pmm_raw_preview_nav_page" value="">
						<input type="hidden" name="pmm_raw_preview_per_page" value="<?php echo esc_attr((string) $raw_preview_per_page); ?>">
						<input type="hidden" name="pmm_raw_preview_context" value="<?php echo esc_attr(md5((string) $raw_preview_text . '|' . (string) $raw_preview_total . '|' . (string) $raw_preview_alias_signature . '|' . (string) $raw_preview_saved_at)); ?>">
						<p><input type="file" name="pmm_raw_import_rows_file" accept=".tsv,.txt,.csv"> <span class="description"><?php esc_html_e('Optional: upload edited TSV to replace textarea content.', 'perchance-memory-manager'); ?></span></p>
						<?php if (!empty($raw_preview_rows)) : ?>
							<details style="margin:8px 0 12px 0;" open>
								<summary><strong><?php esc_html_e('Preview Assignments (editable)', 'perchance-memory-manager'); ?></strong></summary>
								<p class="description" style="margin-top:8px;"><?php echo esc_html(sprintf(__('Showing rows %1$d-%2$d of %3$d. Edit rows, then stage. You can page through the entire review queue.', 'perchance-memory-manager'), ($raw_preview_total > 0 ? $raw_preview_offset + 1 : 0), min($raw_preview_total, $raw_preview_offset + count($raw_preview_rows_page)), $raw_preview_total)); ?></p>
								<?php
								$known_entities_by_section = $this->known_entities_for_raw_review($confirmed_registry, isset($data['cleaned_data']) && is_array($data['cleaned_data']) ? $data['cleaned_data'] : []);
								$this->render_raw_import_preview_table($raw_preview_rows_page, $known_entities_by_section);
								?>
								<?php if ($raw_preview_total_pages > 1) : ?>
									<p style="margin-top:8px;">
										<?php if ($raw_preview_page > 1) : ?>
											<button type="submit" class="button pmm-raw-nav-button" data-target-page="<?php echo esc_attr((string) ($raw_preview_page - 1)); ?>"><?php esc_html_e('Save Edits + Previous Page', 'perchance-memory-manager'); ?></button>
										<?php endif; ?>
										<span class="description" style="margin:0 8px;"><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'perchance-memory-manager'), $raw_preview_page, $raw_preview_total_pages)); ?></span>
										<?php if ($raw_preview_page < $raw_preview_total_pages) : ?>
											<button type="submit" class="button pmm-raw-nav-button" data-target-page="<?php echo esc_attr((string) ($raw_preview_page + 1)); ?>"><?php esc_html_e('Save Edits + Next Page', 'perchance-memory-manager'); ?></button>
										<?php endif; ?>
									</p>
								<?php endif; ?>
							</details>
						<?php endif; ?>
						<p style="margin:8px 0;">
							<button type="button" class="button" id="pmm-raw-sync-table"><?php esc_html_e('Sync Table Into Advanced TSV', 'perchance-memory-manager'); ?></button>
							<button type="submit" class="button" id="pmm-raw-save-page-edits" data-target-page="<?php echo esc_attr((string) $raw_preview_page); ?>" style="margin-left:6px;"><?php esc_html_e('Save Page Edits', 'perchance-memory-manager'); ?></button>
							<span id="pmm-raw-sync-status" class="description" style="margin-left:8px;"></span>
						</p>
						<details style="margin:8px 0 10px 0;">
							<summary><strong><?php esc_html_e('Advanced: Tab-Delimited Staging Text (optional)', 'perchance-memory-manager'); ?></strong></summary>
							<p class="description" style="margin-top:8px;"><?php esc_html_e('You usually do not need this. The editable preview table above is the primary review workflow.', 'perchance-memory-manager'); ?></p>
							<textarea name="pmm_raw_import_rows" rows="14" class="large-text code"><?php echo esc_textarea($staged_raw_rows_text); ?></textarea>
						</details>
						<?php submit_button(__('Stage Rows For Next Processing Run', 'perchance-memory-manager'), 'secondary', 'submit', false, ['id' => 'pmm-raw-stage-submit']); ?>
					</form>
					<script>
						document.addEventListener('DOMContentLoaded', function () {
							var form = document.getElementById('pmm-raw-stage-form');
							if (!form) {
								return;
							}

							var syncButton = document.getElementById('pmm-raw-sync-table');
							var syncStatus = document.getElementById('pmm-raw-sync-status');
							var text = form.querySelector('textarea[name="pmm_raw_import_rows"]');
							var previewContextInput = form.querySelector('input[name="pmm_raw_preview_context"]');
							var previewContext = previewContextInput ? String(previewContextInput.value || '') : '';
							var draftKey = 'pmm-raw-review-draft:' + window.location.pathname + ':' + previewContext;
							var saveDraftTimer = null;
							var stageModeInput = form.querySelector('input[name="pmm_raw_stage_mode"]');
							var navPageInput = form.querySelector('input[name="pmm_raw_preview_nav_page"]');
							var savePageButton = document.getElementById('pmm-raw-save-page-edits');
							var navButtons = form.querySelectorAll('.pmm-raw-nav-button');
							var stageSubmit = document.getElementById('pmm-raw-stage-submit');

							function setManualStageMode() {
								if (stageModeInput) {
									stageModeInput.value = 'manual';
								}
								if (navPageInput) {
									navPageInput.value = '';
								}
							}

							function setSavePreviewPageMode(targetPage) {
								if (stageModeInput) {
									stageModeInput.value = 'save_preview_page';
								}
								if (navPageInput) {
									navPageInput.value = String(targetPage || '1');
								}
							}

							function syncRowsToTextarea(showStatus) {
								var rows = form.querySelectorAll('[data-pmm-raw-row]');
								var lines = [];
								rows.forEach(function (row) {
									var removed = row.querySelector('[name$="[removed]"]');
									if (removed && String(removed.value) === '1') {
										return;
									}
									var section = row.querySelector('[name$="[section]"]');
									var entity = row.querySelector('[name$="[entity]"]');
									var bullet = row.querySelector('[name$="[bullet]"]');
									if (!bullet || !bullet.value.trim()) {
										return;
									}
									var sectionValue = section ? section.value : 'Notes';
									var entityValue = entity ? entity.value : '';
									var bulletValue = bullet.value.replace(/\t/g, ' ').replace(/\r?\n/g, ' ');
									lines.push(sectionValue + '\t' + entityValue + '\t' + bulletValue);
								});
								if (text) {
									text.value = lines.join('\n');
								}

								if (showStatus && syncStatus) {
									syncStatus.textContent = lines.length
										? '<?php echo esc_js(__('Advanced TSV updated from preview table.', 'perchance-memory-manager')); ?>'
										: '<?php echo esc_js(__('No non-empty preview rows found to sync.', 'perchance-memory-manager')); ?>';
								}
							}

							function collectTableDraft() {
								var rows = [];
								form.querySelectorAll('[data-pmm-raw-row]').forEach(function (row) {
									var index = row.getAttribute('data-pmm-raw-index') || '';
									var section = row.querySelector('[name$="[section]"]');
									var entity = row.querySelector('[name$="[entity]"]');
									var bullet = row.querySelector('[name$="[bullet]"]');
									var removed = row.querySelector('[name$="[removed]"]');
									var reviewed = row.querySelector('[name$="[reviewed]"][type="checkbox"]');
									rows.push({
										index: String(index),
										section: section ? String(section.value || '') : '',
										entity: entity ? String(entity.value || '') : '',
										bullet: bullet ? String(bullet.value || '') : '',
										removed: removed ? String(removed.value || '0') : '0',
										reviewed: reviewed && reviewed.checked ? '1' : '0'
									});
								});
								return { context: previewContext, rows: rows, savedAt: Date.now() };
							}

							function saveDraft() {
								if (!previewContext || !window.localStorage) {
									return;
								}
								try {
									window.localStorage.setItem(draftKey, JSON.stringify(collectTableDraft()));
								} catch (error) {
								}
							}

							function queueDraftSave() {
								if (saveDraftTimer) {
									window.clearTimeout(saveDraftTimer);
								}
								saveDraftTimer = window.setTimeout(saveDraft, 250);
							}

							function applyDraft() {
								if (!previewContext || !window.localStorage) {
									return;
								}
								var raw = '';
								try {
									raw = window.localStorage.getItem(draftKey) || '';
								} catch (error) {
									return;
								}
								if (!raw) {
									return;
								}
								var draft = null;
								try {
									draft = JSON.parse(raw);
								} catch (error) {
									return;
								}
								if (!draft || draft.context !== previewContext || !Array.isArray(draft.rows)) {
									return;
								}

								draft.rows.forEach(function (item) {
									if (!item || typeof item !== 'object') {
										return;
									}
									var row = form.querySelector('[data-pmm-raw-index="' + String(item.index || '').replace(/"/g, '') + '"]');
									if (!row) {
										return;
									}
									var section = row.querySelector('[name$="[section]"]');
									var entity = row.querySelector('[name$="[entity]"]');
									var bullet = row.querySelector('[name$="[bullet]"]');
									var removed = row.querySelector('[name$="[removed]"]');
									var reviewed = row.querySelector('[name$="[reviewed]"][type="checkbox"]');
									if (section && typeof item.section === 'string') {
										section.value = item.section;
										section.dispatchEvent(new Event('change'));
									}
									if (entity && typeof item.entity === 'string') {
										entity.value = item.entity;
									}
									if (bullet && typeof item.bullet === 'string') {
										bullet.value = item.bullet;
									}
									if (removed && String(item.removed || '0') === '1') {
										removed.value = '1';
										row.style.display = 'none';
									}
									if (reviewed) {
										reviewed.checked = String(item.reviewed || '0') === '1';
									}
								});

								syncRowsToTextarea(false);
								if (syncStatus) {
									syncStatus.textContent = '<?php echo esc_js(__('Recovered unsaved browser draft for this preview page set.', 'perchance-memory-manager')); ?>';
								}
							}

							if (syncButton) {
								syncButton.addEventListener('click', function () {
									syncRowsToTextarea(true);
								});
							}

							if (savePageButton) {
								savePageButton.addEventListener('click', function () {
									syncRowsToTextarea(false);
									saveDraft();
									setSavePreviewPageMode(savePageButton.getAttribute('data-target-page') || '1');
								});
							}

							navButtons.forEach(function (button) {
								button.addEventListener('click', function () {
									syncRowsToTextarea(false);
									saveDraft();
									setSavePreviewPageMode(button.getAttribute('data-target-page') || '1');
								});
							});

							if (stageSubmit) {
								stageSubmit.addEventListener('click', function () {
									setManualStageMode();
								});
							}

							form.addEventListener('input', function (event) {
								if (!event.target || event.target.name === 'pmm_raw_import_rows') {
									return;
								}
								syncRowsToTextarea(false);
								queueDraftSave();
							});

							form.addEventListener('change', function (event) {
								if (!event.target || event.target.name === 'pmm_raw_import_rows') {
									return;
								}
								syncRowsToTextarea(false);
								queueDraftSave();
							});

							form.addEventListener('submit', function () {
								syncRowsToTextarea(false);
								saveDraft();
							});

							syncRowsToTextarea(false);

							setManualStageMode();
							applyDraft();
						});
					</script>

					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px;">
						<?php wp_nonce_field('pmm_download_raw_import_rows'); ?>
						<input type="hidden" name="action" value="pmm_download_raw_import_rows">
						<?php submit_button(__('Download Preview/Staged TSV', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
					</form>

					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px;">
						<?php wp_nonce_field('pmm_clear_raw_import_preview'); ?>
						<input type="hidden" name="action" value="pmm_clear_raw_import_preview">
						<label><input type="checkbox" name="pmm_clear_staged_raw_import" value="1"> <?php esc_html_e('Also clear staged rows waiting for next processing run', 'perchance-memory-manager'); ?></label>
						<?php submit_button(__('Clear Raw Import Preview', 'perchance-memory-manager'), 'delete', 'submit', false, ['style' => 'margin-left:8px;']); ?>
					</form>
				<?php endif; ?>

				<?php if (!empty($staged_raw_rows)) : ?>
					<p class="description" style="margin-top:8px;"><?php echo esc_html(sprintf(__('Currently staged: %d row(s). They will be injected on the next upload or reprocess, then cleared automatically.', 'perchance-memory-manager'), count($staged_raw_rows))); ?></p>
				<?php endif; ?>
				</details>
			</div>

			<?php if (!empty($data) && !empty($data['cleaned_data']) && is_array($data['cleaned_data'])) : ?>
				<div class="pmm-card">
					<details class="pmm-collapsible-section pmm-collapsible-root">
						<summary><strong><?php esc_html_e('Entity Workspace', 'perchance-memory-manager'); ?></strong></summary>
					<p class="description"><?php esc_html_e('Load any entity to view and edit its entries. Replace updates that entity bucket directly in the latest processed output. Delete removes the entity bucket (or section entries when entity is blank for Notes/Relationships/NSFW).', 'perchance-memory-manager'); ?></p>

					<form id="pmm-entity-load-form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-bottom:10px;">
						<input type="hidden" name="page" value="perchance-memory-manager">
						<input type="hidden" id="pmm-entity-ajax-nonce" value="<?php echo esc_attr($entity_ajax_nonce); ?>">
						<label><?php esc_html_e('Section', 'perchance-memory-manager'); ?>
							<select id="pmm_edit_section" name="pmm_edit_section">
								<?php foreach (['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation', 'World Building', 'Relationships', 'NSFW', 'Notes'] as $sec) : ?>
									<option value="<?php echo esc_attr($sec); ?>" <?php selected($edit_section, $sec); ?>><?php echo esc_html($sec); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label style="margin-left:8px;"><?php esc_html_e('Entity', 'perchance-memory-manager'); ?>
							<select id="pmm_edit_entity" name="pmm_edit_entity" class="regular-text">
								<?php if (in_array($edit_section, ['Notes', 'Relationships', 'NSFW', 'World Building', 'Technology / Systems', 'Vehicles / Transportation'], true)) : ?>
									<option value="" <?php selected($edit_entity, ''); ?>><?php esc_html_e('(Section-level entries)', 'perchance-memory-manager'); ?></option>
								<?php endif; ?>
								<?php foreach ($edit_entity_options as $name) : ?>
									<option value="<?php echo esc_attr($name); ?>" <?php selected($edit_entity, $name); ?>><?php echo esc_html($name); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<?php submit_button(__('Load Entity', 'perchance-memory-manager'), 'secondary', 'submit', false, ['style' => 'margin-left:8px;']); ?>
					</form>
					<script>
						document.addEventListener('DOMContentLoaded', function () {
							var section = document.getElementById('pmm_edit_section');
							var entity = document.getElementById('pmm_edit_entity');
							var form = document.getElementById('pmm-entity-load-form');
							var saveSection = document.getElementById('pmm_save_edit_section');
							var saveEntity = document.getElementById('pmm_save_edit_entity');
							var targetSection = document.getElementById('pmm_edit_target_section');
							var entityName = document.getElementById('pmm_edit_entity_name');
							var nonceInput = document.getElementById('pmm-entity-ajax-nonce');
							var nonce = nonceInput ? nonceInput.value : '';
							var sectionLevelSections = ['Notes', 'Relationships', 'NSFW', 'World Building', 'Technology / Systems', 'Vehicles / Transportation'];
							if (!section || !entity || !form) {
								return;
							}

							function syncSaveTargets() {
								var selectedSection = section.value || 'Characters';
								var selectedEntity = entity.value || '';
								var isSectionLevel = sectionLevelSections.indexOf(selectedSection) !== -1 && selectedEntity === '';
								if (saveSection) {
									saveSection.value = selectedSection;
								}
								if (saveEntity) {
									saveEntity.value = selectedEntity;
								}
								if (targetSection) {
									if (targetSection.dataset.autoSync !== '0') {
										targetSection.value = selectedSection;
									}
									targetSection.disabled = isSectionLevel;
									if (isSectionLevel) {
										targetSection.value = selectedSection;
										targetSection.dataset.autoSync = '1';
									}
								}
								if (entityName) {
									entityName.disabled = isSectionLevel;
									if (isSectionLevel) {
										entityName.value = '';
										entityName.placeholder = '<?php echo esc_js(__('Section-level entries', 'perchance-memory-manager')); ?>';
									} else {
										entityName.placeholder = '<?php echo esc_js(__('Entity name', 'perchance-memory-manager')); ?>';
										if (selectedEntity !== '' && (entityName.value === '' || entityName.dataset.autoSync === '1')) {
											entityName.value = selectedEntity;
										}
									}
								}
							}

							function renderEntityOptions(selectedSection, names) {
								var previous = entity.value || '';
								var allowSectionLevel = sectionLevelSections.indexOf(selectedSection) !== -1;
								entity.innerHTML = '';

								if (allowSectionLevel) {
									var secOpt = document.createElement('option');
									secOpt.value = '';
									secOpt.textContent = '(Section-level entries)';
									entity.appendChild(secOpt);
								}

								(names || []).forEach(function (name) {
									var opt = document.createElement('option');
									opt.value = name;
									opt.textContent = name;
									entity.appendChild(opt);
								});

								if (previous && (names || []).indexOf(previous) !== -1) {
									entity.value = previous;
								} else {
									entity.value = allowSectionLevel ? '' : ((names || [])[0] || '');
								}

								syncSaveTargets();
							}

							function fetchEntitiesForSection(selectedSection) {
								var url = new URL(ajaxurl);
								url.searchParams.set('action', 'pmm_get_entities_for_section');
								url.searchParams.set('section', selectedSection);
								url.searchParams.set('nonce', nonce);

								fetch(url.toString(), { credentials: 'same-origin' })
									.then(function (res) { return res.json(); })
									.then(function (payload) {
										if (!payload || !payload.success || !payload.data) {
											throw new Error('bad_payload');
										}
										renderEntityOptions(selectedSection, Array.isArray(payload.data.entities) ? payload.data.entities : []);
									})
									.catch(function () {
										form.submit();
									});
							}

							section.addEventListener('change', function () {
								syncSaveTargets();
								fetchEntitiesForSection(section.value || 'Characters');
							});

							entity.addEventListener('change', function () {
								if (entityName) {
									entityName.dataset.autoSync = '1';
								}
								syncSaveTargets();
								form.submit();
							});

							if (entityName) {
								entityName.addEventListener('input', function () {
									entityName.dataset.autoSync = '0';
								});
								entityName.dataset.autoSync = '1';
							}

							if (targetSection) {
								targetSection.addEventListener('change', function () {
									targetSection.dataset.autoSync = '0';
								});
								targetSection.dataset.autoSync = '1';
							}

							syncSaveTargets();
						});
					</script>

					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<?php wp_nonce_field('pmm_save_entity_update'); ?>
						<input type="hidden" name="action" value="pmm_save_entity_update">
						<input type="hidden" id="pmm_save_edit_section" name="pmm_edit_section" value="<?php echo esc_attr($edit_section); ?>">
						<input type="hidden" id="pmm_save_edit_entity" name="pmm_edit_entity" value="<?php echo esc_attr($edit_entity); ?>">
						<p>
							<label><?php esc_html_e('Target Section', 'perchance-memory-manager'); ?>
								<select id="pmm_edit_target_section" name="pmm_edit_target_section">
									<?php foreach (['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation', 'World Building', 'Relationships', 'NSFW', 'Notes'] as $sec) : ?>
										<option value="<?php echo esc_attr($sec); ?>" <?php selected($edit_section, $sec); ?>><?php echo esc_html($sec); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<span class="description" style="margin-left:8px;"><?php esc_html_e('Move this entity into a different section when saving.', 'perchance-memory-manager'); ?></span>
						</p>
						<p>
							<label><?php esc_html_e('Entity Name', 'perchance-memory-manager'); ?>
								<input type="text" id="pmm_edit_entity_name" name="pmm_edit_entity_name" class="regular-text" value="<?php echo esc_attr($edit_entity); ?>" placeholder="<?php esc_attr_e('Entity name', 'perchance-memory-manager'); ?>">
							</label>
						</p>
						<p>
							<label><?php esc_html_e('Update Action', 'perchance-memory-manager'); ?>
								<select name="pmm_edit_action">
									<option value="replace"><?php esc_html_e('Replace Entries', 'perchance-memory-manager'); ?></option>
									<option value="delete"><?php esc_html_e('Delete Target', 'perchance-memory-manager'); ?></option>
								</select>
							</label>
						</p>
						<p class="description" style="margin-bottom:6px;"><?php esc_html_e('Save Entity Update writes directly to the latest output/version. Reprocess is not required unless output-affecting rules or staged raw rows changed.', 'perchance-memory-manager'); ?></p>
						<p class="description" style="margin-bottom:6px;"><?php esc_html_e('Paste one entry per line. Leading bullets like "-", "*", or "•" are stripped automatically, and Replace Entries will overwrite the current bucket with exactly what you paste here.', 'perchance-memory-manager'); ?></p>
						<textarea name="pmm_edit_entries" rows="12" class="large-text code"><?php echo esc_textarea($edit_entries_text); ?></textarea>
						<details style="margin-top:10px;">
							<summary><strong><?php esc_html_e('Rendered Output Preview', 'perchance-memory-manager'); ?></strong></summary>
							<pre class="pmm-rendered-preview" style="white-space:pre-wrap;margin-top:8px;padding:10px;border:1px solid #dcdcde;background:#fff;overflow:auto;"><?php echo esc_html($edit_rendered_text); ?></pre>
						</details>
						<?php submit_button(__('Save Entity Update', 'perchance-memory-manager'), 'primary', 'submit', false); ?>
					</form>

						<hr style="margin:18px 0;">
						<h3><?php esc_html_e('Entity Prune Assistant', 'perchance-memory-manager'); ?></h3>
						<p class="description"><?php esc_html_e('Prunes large entity buckets by removing exact duplicates, near-duplicates, optional stale items, then smart-ranking what remains to preserve critical/high-signal facts before trimming to your cap. This updates latest output/version immediately.', 'perchance-memory-manager'); ?></p>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pmm-prune-entity-form">
							<?php wp_nonce_field('pmm_prune_entity_entries'); ?>
							<input type="hidden" name="action" value="pmm_prune_entity_entries">
							<p>
								<label><?php esc_html_e('Section', 'perchance-memory-manager'); ?>
									<select id="pmm_prune_section" name="pmm_prune_section">
										<?php foreach (['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation', 'World Building', 'Relationships', 'NSFW', 'Notes'] as $sec) : ?>
											<option value="<?php echo esc_attr($sec); ?>" <?php selected($edit_section, $sec); ?>><?php echo esc_html($sec); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label style="margin-left:8px;"><?php esc_html_e('Entity', 'perchance-memory-manager'); ?>
									<select id="pmm_prune_entity" name="pmm_prune_entity" class="regular-text" style="min-width:220px;">
										<?php if (in_array($edit_section, ['Notes', 'Relationships', 'NSFW', 'World Building', 'Technology / Systems', 'Vehicles / Transportation'], true)) : ?>
											<option value=""><?php esc_html_e('(Section-level entries)', 'perchance-memory-manager'); ?></option>
										<?php endif; ?>
										<?php foreach ($edit_entity_options as $name) : ?>
											<option value="<?php echo esc_attr($name); ?>" <?php selected($edit_entity, $name); ?>><?php echo esc_html($name); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
							</p>
							<p>
								<label><?php esc_html_e('Max entries to keep', 'perchance-memory-manager'); ?>
									<input type="number" name="pmm_prune_max_keep" min="50" max="300" step="1" value="300" style="width:110px;">
								</label>
								<label style="margin-left:8px;"><?php esc_html_e('Near-duplicate threshold', 'perchance-memory-manager'); ?>
									<input type="number" name="pmm_prune_similarity_threshold" min="0.75" max="0.98" step="0.01" value="0.90" style="width:100px;">
								</label>
							</p>
							<p>
								<label><input type="checkbox" name="pmm_prune_remove_stale" value="1"> <?php esc_html_e('Remove entries likely stale or no longer useful (formerly, deprecated, no longer, TODO/TBD, etc.)', 'perchance-memory-manager'); ?></label>
							</p>
							<p>
								<label><input type="checkbox" name="pmm_prune_remove_unreferenced" value="1"> <?php esc_html_e('Remove entries that do not reference any known entity/character (reviewable in preview)', 'perchance-memory-manager'); ?></label>
								<label style="margin-left:8px;"><?php esc_html_e('Reference threshold', 'perchance-memory-manager'); ?>
									<input type="number" name="pmm_prune_unreferenced_threshold" min="0.40" max="0.95" step="0.01" value="0.60" style="width:100px;">
								</label>
							</p>
							<p>
								<label><input type="checkbox" name="pmm_prune_require_entity_name_match" value="1"> <?php esc_html_e('Global entity-name check: remove entries that do not mention the selected entity name (reviewable in preview)', 'perchance-memory-manager'); ?></label>
							</p>
							<p>
								<label><input type="checkbox" name="pmm_prune_collect_nonprefix_review" value="1"> <?php esc_html_e('Collect entries that do not start with the selected entity name for review/edit/remove', 'perchance-memory-manager'); ?></label>
							</p>
							<p>
								<label><input type="checkbox" name="pmm_prune_preview_only" value="1"> <?php esc_html_e('Preview prune report only (no changes saved)', 'perchance-memory-manager'); ?></label>
							</p>
							<?php submit_button(__('Run Intelligent Prune', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
						</form>
						<?php if ($prune_preview && !empty($prune_preview_data['stats']) && is_array($prune_preview_data['stats'])) : ?>
							<?php
							$preview_stats = $prune_preview_data['stats'];
							$preview_report = isset($prune_preview_data['report']) && is_array($prune_preview_data['report']) ? $prune_preview_data['report'] : [];
							$preview_section = isset($prune_preview_data['section']) ? (string) $prune_preview_data['section'] : $edit_section;
							$preview_entity = isset($prune_preview_data['entity']) ? (string) $prune_preview_data['entity'] : $edit_entity;
							$preview_target = $preview_entity !== '' ? $preview_entity : __('(section-level entries)', 'perchance-memory-manager');
							?>
							<details style="margin-top:12px;" open>
								<summary><strong><?php esc_html_e('Prune Preview Report', 'perchance-memory-manager'); ?></strong></summary>
								<p class="description" style="margin-top:8px;"><?php echo esc_html(sprintf(__('Target: %1$s / %2$s. This is a preview only; run prune again without preview mode to apply changes.', 'perchance-memory-manager'), $preview_section, $preview_target)); ?></p>
								<ul class="pmm-stats">
									<li><strong><?php esc_html_e('Before:', 'perchance-memory-manager'); ?></strong> <?php echo esc_html((string) ((int) ($preview_stats['original'] ?? 0))); ?></li>
									<li><strong><?php esc_html_e('After (estimated):', 'perchance-memory-manager'); ?></strong> <?php echo esc_html((string) (((int) ($preview_stats['original'] ?? 0)) - ((int) ($preview_stats['exact_duplicates'] ?? 0)) - ((int) ($preview_stats['near_duplicates'] ?? 0)) - ((int) ($preview_stats['stale_removed'] ?? 0)) - ((int) ($preview_stats['unreferenced_removed'] ?? 0)) - ((int) ($preview_stats['entity_name_mismatch_removed'] ?? 0)) - ((int) ($preview_stats['trimmed'] ?? 0)))); ?></li>
									<li><strong><?php esc_html_e('Exact duplicates:', 'perchance-memory-manager'); ?></strong> <?php echo esc_html((string) ((int) ($preview_stats['exact_duplicates'] ?? 0))); ?></li>
									<li><strong><?php esc_html_e('Near duplicates:', 'perchance-memory-manager'); ?></strong> <?php echo esc_html((string) ((int) ($preview_stats['near_duplicates'] ?? 0))); ?></li>
									<li><strong><?php esc_html_e('Stale candidates:', 'perchance-memory-manager'); ?></strong> <?php echo esc_html((string) ((int) ($preview_stats['stale_removed'] ?? 0))); ?></li>
									<li><strong><?php esc_html_e('Unreferenced candidates:', 'perchance-memory-manager'); ?></strong> <?php echo esc_html((string) ((int) ($preview_stats['unreferenced_removed'] ?? 0))); ?></li>
									<li><strong><?php esc_html_e('Missing selected entity name:', 'perchance-memory-manager'); ?></strong> <?php echo esc_html((string) ((int) ($preview_stats['entity_name_mismatch_removed'] ?? 0))); ?></li>
									<li><strong><?php esc_html_e('Critical entries preserved:', 'perchance-memory-manager'); ?></strong> <?php echo esc_html((string) ((int) ($preview_stats['critical_preserved'] ?? 0))); ?></li>
									<li><strong><?php esc_html_e('Trimmed to cap:', 'perchance-memory-manager'); ?></strong> <?php echo esc_html((string) ((int) ($preview_stats['trimmed'] ?? 0))); ?></li>
								</ul>

								<?php
								$preview_groups = [
									'exact_duplicates' => __('Sample exact duplicates to remove', 'perchance-memory-manager'),
									'near_duplicates' => __('Sample near duplicates to remove', 'perchance-memory-manager'),
									'stale_removed' => __('Sample stale candidates to remove', 'perchance-memory-manager'),
									'unreferenced_removed' => __('Sample unreferenced candidates to remove', 'perchance-memory-manager'),
									'entity_name_mismatch_removed' => __('Sample entries missing selected entity name', 'perchance-memory-manager'),
									'trimmed' => __('Sample entries trimmed by cap', 'perchance-memory-manager'),
								];
								foreach ($preview_groups as $group_key => $group_label) :
									$items = isset($preview_report[$group_key]) && is_array($preview_report[$group_key]) ? array_slice(array_values($preview_report[$group_key]), 0, 25) : [];
									if (empty($items)) {
										continue;
									}
								?>
									<details style="margin-top:8px;">
										<summary><?php echo esc_html($group_label); ?> (<?php echo esc_html((string) count($items)); ?>)</summary>
										<ul style="margin:8px 0 0 16px;">
											<?php foreach ($items as $item) : ?>
												<li><?php echo esc_html((string) $item); ?></li>
											<?php endforeach; ?>
										</ul>
									</details>
								<?php endforeach; ?>

								<?php
								$review_candidates = isset($preview_report['review_candidates']) && is_array($preview_report['review_candidates']) ? array_values($preview_report['review_candidates']) : [];
								if (!empty($review_candidates)) :
								?>
									<details style="margin-top:8px;" open>
										<summary><strong><?php esc_html_e('Review All Prune Candidates Before Applying', 'perchance-memory-manager'); ?></strong> (<?php echo esc_html((string) count($review_candidates)); ?>)</summary>
										<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pmm-prune-preview-review-form" style="margin-top:8px;">
											<?php wp_nonce_field('pmm_apply_prune_preview_review'); ?>
											<input type="hidden" name="action" value="pmm_apply_prune_preview_review">
											<input type="hidden" name="pmm_prune_section" value="<?php echo esc_attr($preview_section); ?>">
											<input type="hidden" name="pmm_prune_entity" value="<?php echo esc_attr($preview_entity); ?>">
											<input type="hidden" name="pmm_prune_review_rows" id="pmm_prune_review_rows" value="[]">
											<table class="widefat striped" style="table-layout:fixed;width:100%;margin-top:6px;">
												<thead>
													<tr>
														<th style="width:14%;"><?php esc_html_e('Reason', 'perchance-memory-manager'); ?></th>
														<th style="width:24%;"><?php esc_html_e('Original Entry', 'perchance-memory-manager'); ?></th>
														<th style="width:46%;"><?php esc_html_e('Edited Entry', 'perchance-memory-manager'); ?></th>
														<th style="width:10%;"><?php esc_html_e('Action', 'perchance-memory-manager'); ?></th>
														<th style="width:6%;"><?php esc_html_e('Critical', 'perchance-memory-manager'); ?></th>
													</tr>
												</thead>
												<tbody>
													<?php foreach ($review_candidates as $row_index => $row) : ?>
														<?php
														$source_index = isset($row['source_index']) ? (int) $row['source_index'] : $row_index;
														$source_entry = isset($row['source_entry']) ? (string) $row['source_entry'] : '';
														$reason_label = isset($row['reason_label']) ? (string) $row['reason_label'] : __('Prune candidate', 'perchance-memory-manager');
														if ($source_entry === '') {
															continue;
														}
														?>
														<tr class="pmm-prune-review-row" data-source-index="<?php echo esc_attr((string) $source_index); ?>" data-source-entry="<?php echo esc_attr($source_entry); ?>">
															<td style="white-space:pre-wrap;"><?php echo esc_html($reason_label); ?></td>
															<td style="white-space:pre-wrap;"><?php echo esc_html($source_entry); ?></td>
															<td><textarea class="large-text code pmm-prune-review-entry" rows="2" style="width:100%;max-width:100%;"><?php echo esc_textarea($source_entry); ?></textarea></td>
															<td>
																<select class="pmm-prune-review-action" style="width:100%;">
																	<option value="remove" selected><?php esc_html_e('Remove', 'perchance-memory-manager'); ?></option>
																	<option value="keep"><?php esc_html_e('Keep', 'perchance-memory-manager'); ?></option>
																	<option value="edit"><?php esc_html_e('Edit', 'perchance-memory-manager'); ?></option>
																</select>
															</td>
															<td style="text-align:center;">
																<label style="display:inline-flex;align-items:center;gap:4px;justify-content:center;">
																	<input type="checkbox" class="pmm-prune-review-critical" value="1">
																	<span class="screen-reader-text"><?php esc_html_e('Mark critical', 'perchance-memory-manager'); ?></span>
																</label>
															</td>
														</tr>
													<?php endforeach; ?>
												</tbody>
											</table>
											<?php submit_button(__('Apply Prune Candidate Review', 'perchance-memory-manager'), 'secondary', 'submit', false, ['style' => 'margin-top:8px;']); ?>
										</form>
										<script>
										(function () {
											var form = document.getElementById('pmm-prune-preview-review-form');
											if (!form) {
												return;
											}

											form.addEventListener('submit', function () {
												var rows = [];
												form.querySelectorAll('tr.pmm-prune-review-row').forEach(function (row) {
													var sourceEntry = row.getAttribute('data-source-entry') || '';
													if (!sourceEntry) {
														return;
													}
													var sourceIndex = row.getAttribute('data-source-index') || '';
													var actionEl = row.querySelector('.pmm-prune-review-action');
													var entryEl = row.querySelector('.pmm-prune-review-entry');
													var criticalEl = row.querySelector('.pmm-prune-review-critical');
													rows.push({
														source_index: sourceIndex,
														source_entry: sourceEntry,
														entry: entryEl ? (entryEl.value || sourceEntry) : sourceEntry,
														action: actionEl ? (actionEl.value || 'remove') : 'remove',
														critical: criticalEl && criticalEl.checked ? '1' : '0'
													});
												});

												var hidden = document.getElementById('pmm_prune_review_rows');
												if (hidden) {
													hidden.value = JSON.stringify(rows);
												}
											});
										})();
										</script>
									</details>
								<?php endif; ?>

								<?php
								$nonprefix_rows = isset($preview_report['nonprefix_review']) && is_array($preview_report['nonprefix_review']) ? array_values($preview_report['nonprefix_review']) : [];
								if (!empty($nonprefix_rows)) :
								?>
									<details style="margin-top:8px;" open>
										<summary><strong><?php esc_html_e('Review Entries Not Starting With Entity Name', 'perchance-memory-manager'); ?></strong> (<?php echo esc_html((string) count($nonprefix_rows)); ?>)</summary>
										<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pmm-prune-nonprefix-review-form" style="margin-top:8px;">
											<?php wp_nonce_field('pmm_apply_prune_nonprefix_review'); ?>
											<input type="hidden" name="action" value="pmm_apply_prune_nonprefix_review">
											<input type="hidden" name="pmm_prune_section" value="<?php echo esc_attr($preview_section); ?>">
											<input type="hidden" name="pmm_prune_entity" value="<?php echo esc_attr($preview_entity); ?>">
											<input type="hidden" name="pmm_prune_nonprefix_rows" id="pmm_prune_nonprefix_rows" value="[]">
											<table class="widefat striped" style="table-layout:fixed;width:100%;margin-top:6px;">
												<thead>
													<tr>
														<th style="width:26%;"><?php esc_html_e('Original Entry', 'perchance-memory-manager'); ?></th>
														<th style="width:52%;"><?php esc_html_e('Edited Entry', 'perchance-memory-manager'); ?></th>
														<th style="width:14%;"><?php esc_html_e('Action', 'perchance-memory-manager'); ?></th>
														<th style="width:8%;"><?php esc_html_e('Critical', 'perchance-memory-manager'); ?></th>
													</tr>
												</thead>
												<tbody>
													<?php foreach ($nonprefix_rows as $row_index => $row) : ?>
														<?php
														$source_index = isset($row['source_index']) ? (int) $row['source_index'] : $row_index;
														$source_entry = isset($row['source_entry']) ? (string) $row['source_entry'] : '';
														if ($source_entry === '') {
															continue;
														}
														?>
														<tr class="pmm-prune-nonprefix-row" data-source-index="<?php echo esc_attr((string) $source_index); ?>" data-source-entry="<?php echo esc_attr($source_entry); ?>">
															<td style="white-space:pre-wrap;"><?php echo esc_html($source_entry); ?></td>
															<td><textarea class="large-text code pmm-prune-nonprefix-entry" rows="2" style="width:100%;max-width:100%;"><?php echo esc_textarea($source_entry); ?></textarea></td>
															<td>
																<select class="pmm-prune-nonprefix-action" style="width:100%;">
																	<option value="remove" selected><?php esc_html_e('Remove', 'perchance-memory-manager'); ?></option>
																	<option value="keep"><?php esc_html_e('Keep', 'perchance-memory-manager'); ?></option>
																	<option value="edit"><?php esc_html_e('Edit', 'perchance-memory-manager'); ?></option>
																</select>
															</td>
															<td style="text-align:center;">
																<label style="display:inline-flex;align-items:center;gap:4px;justify-content:center;">
																	<input type="checkbox" class="pmm-prune-nonprefix-critical" value="1">
																	<span class="screen-reader-text"><?php esc_html_e('Mark critical', 'perchance-memory-manager'); ?></span>
																</label>
															</td>
														</tr>
													<?php endforeach; ?>
												</tbody>
											</table>
											<?php submit_button(__('Apply Non-Prefix Review Changes', 'perchance-memory-manager'), 'secondary', 'submit', false, ['style' => 'margin-top:8px;']); ?>
										</form>
										<script>
										(function () {
											var form = document.getElementById('pmm-prune-nonprefix-review-form');
											if (!form) {
												return;
											}

											form.addEventListener('submit', function () {
												var rows = [];
												form.querySelectorAll('tr.pmm-prune-nonprefix-row').forEach(function (row) {
													var sourceEntry = row.getAttribute('data-source-entry') || '';
													if (!sourceEntry) {
														return;
													}
													var sourceIndex = row.getAttribute('data-source-index') || '';
													var actionEl = row.querySelector('.pmm-prune-nonprefix-action');
													var entryEl = row.querySelector('.pmm-prune-nonprefix-entry');
																var criticalEl = row.querySelector('.pmm-prune-nonprefix-critical');
													rows.push({
														source_index: sourceIndex,
														source_entry: sourceEntry,
														entry: entryEl ? (entryEl.value || sourceEntry) : sourceEntry,
																	action: actionEl ? (actionEl.value || 'remove') : 'remove',
																	critical: criticalEl && criticalEl.checked ? '1' : '0'
													});
												});

												var hidden = document.getElementById('pmm_prune_nonprefix_rows');
												if (hidden) {
													hidden.value = JSON.stringify(rows);
												}
											});
										})();
										</script>
									</details>
								<?php endif; ?>
							</details>
						<?php endif; ?>
						<script>
							(function () {
								var section = document.getElementById('pmm_prune_section');
								var entity = document.getElementById('pmm_prune_entity');
								var nonceInput = document.getElementById('pmm-entity-ajax-nonce');
								var nonce = nonceInput ? nonceInput.value : '';
								if (!section || !entity || !nonce) {
									return;
								}

								function renderOptions(selectedSection, names) {
									var sectionLevel = ['Notes', 'Relationships', 'NSFW', 'World Building', 'Technology / Systems', 'Vehicles / Transportation'];
									var previous = entity.value || '';
									entity.innerHTML = '';
									if (sectionLevel.indexOf(selectedSection) !== -1) {
										var secOpt = document.createElement('option');
										secOpt.value = '';
										secOpt.textContent = '(Section-level entries)';
										entity.appendChild(secOpt);
									}
									(names || []).forEach(function (name) {
										var opt = document.createElement('option');
										opt.value = name;
										opt.textContent = name;
										entity.appendChild(opt);
									});
									if (previous && (names || []).indexOf(previous) !== -1) {
										entity.value = previous;
									}
								}

								section.addEventListener('change', function () {
									var url = new URL(ajaxurl);
									url.searchParams.set('action', 'pmm_get_entities_for_section');
									url.searchParams.set('section', section.value || 'Characters');
									url.searchParams.set('nonce', nonce);
									fetch(url.toString(), { credentials: 'same-origin' })
										.then(function (res) { return res.json(); })
										.then(function (payload) {
											if (!payload || !payload.success || !payload.data) {
												return;
											}
											renderOptions(section.value || 'Characters', Array.isArray(payload.data.entities) ? payload.data.entities : []);
										});
								});
							})();
						</script>
					</details>
				</div>
			<?php endif; ?>

			<?php if (!empty($data) && !empty($data['cleaned_data']) && is_array($data['cleaned_data'])) : ?>
				<div class="pmm-card">
					<details class="pmm-collapsible-section" <?php echo $entry_convert_load ? 'open' : ''; ?>>
						<summary><strong><?php esc_html_e('Entry Conversion Tool', 'perchance-memory-manager'); ?></strong></summary>
						<p class="description"><?php esc_html_e('Move or rewrite individual entries across sections/entities with paging to avoid form truncation. Section-level targets (Notes, NSFW, Relationships, World Building) are handled automatically. Enable global entity search to include mention matches when filtering by entity.', 'perchance-memory-manager'); ?></p>

					<form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-bottom:10px;">
						<input type="hidden" name="page" value="perchance-memory-manager">
						<label><?php esc_html_e('Source section', 'perchance-memory-manager'); ?>
							<select name="pmm_entry_convert_section" class="regular-text" style="min-width:220px;">
								<?php foreach ($entry_convert_section_options as $sec_value => $sec_label) : ?>
									<option value="<?php echo esc_attr((string) $sec_value); ?>" <?php selected($entry_convert_section, (string) $sec_value); ?>><?php echo esc_html((string) $sec_label); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label style="margin-left:8px;"><?php esc_html_e('Source entity', 'perchance-memory-manager'); ?>
							<select name="pmm_entry_convert_entity" class="regular-text" style="min-width:220px;">
								<option value=""><?php esc_html_e('(all entities + section-level)', 'perchance-memory-manager'); ?></option>
								<?php foreach ($entry_convert_entity_options as $entity_name_opt) : ?>
									<option value="<?php echo esc_attr((string) $entity_name_opt); ?>" <?php selected($entry_convert_entity, (string) $entity_name_opt); ?>><?php echo esc_html((string) $entity_name_opt); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label style="margin-left:8px;"><?php esc_html_e('Contains text', 'perchance-memory-manager'); ?>
							<input type="text" name="pmm_entry_convert_search" value="<?php echo esc_attr($entry_convert_search); ?>" class="regular-text" placeholder="<?php echo esc_attr__('optional filter', 'perchance-memory-manager'); ?>">
						</label>
						<label style="margin-left:8px;">
							<input type="hidden" name="pmm_entry_convert_include_mentions" value="0">
							<input type="checkbox" name="pmm_entry_convert_include_mentions" value="1" <?php checked($entry_convert_include_mentions); ?>>
							<?php esc_html_e('Global entity search (include mention matches)', 'perchance-memory-manager'); ?>
						</label>
						<label style="margin-left:8px;"><?php esc_html_e('Rows per page', 'perchance-memory-manager'); ?>
							<select name="pmm_entry_convert_per_page">
								<?php foreach ([25, 50, 100] as $opt) : ?>
									<option value="<?php echo esc_attr((string) $opt); ?>" <?php selected($entry_convert_per_page, $opt); ?>><?php echo esc_html((string) $opt); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<input type="hidden" name="pmm_entry_convert_load" value="1">
						<input type="hidden" name="pmm_entry_convert_page" value="1">
						<?php submit_button(__('Load Entries', 'perchance-memory-manager'), 'secondary', 'submit', false, ['style' => 'margin-left:8px;']); ?>
					</form>

					<?php if ($entry_convert_load) : ?>
						<p class="description"><?php echo esc_html(sprintf(__('Showing %1$d rows on this page out of %2$d matches.', 'perchance-memory-manager'), count($entry_convert_rows), $entry_convert_total)); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e('Choose filters and click Load Entries to populate this conversion table.', 'perchance-memory-manager'); ?></p>
					<?php endif; ?>

					<?php if ($entry_convert_load && !empty($entry_convert_rows)) : ?>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pmm-entry-convert-form">
							<?php wp_nonce_field('pmm_save_global_entity_report'); ?>
							<input type="hidden" name="action" value="pmm_save_global_entity_report">
							<input type="hidden" name="pmm_entry_convert_mode" value="1">
							<input type="hidden" name="pmm_entry_convert_section" value="<?php echo esc_attr($entry_convert_section); ?>">
							<input type="hidden" name="pmm_entry_convert_entity" value="<?php echo esc_attr($entry_convert_entity); ?>">
							<input type="hidden" name="pmm_entry_convert_search" value="<?php echo esc_attr($entry_convert_search); ?>">
							<input type="hidden" name="pmm_entry_convert_include_mentions" value="<?php echo esc_attr($entry_convert_include_mentions ? '1' : '0'); ?>">
							<input type="hidden" name="pmm_entry_convert_load" value="1">
							<input type="hidden" name="pmm_entry_convert_page" value="<?php echo esc_attr((string) $entry_convert_page); ?>">
							<input type="hidden" name="pmm_entry_convert_per_page" value="<?php echo esc_attr((string) $entry_convert_per_page); ?>">
							<input type="hidden" name="pmm_global_snapshot" value="<?php echo esc_attr($entry_convert_snapshot); ?>">
							<textarea name="pmm_global_rows_json" id="pmm_entry_convert_rows_json" style="display:none;"></textarea>

							<table class="widefat striped" style="margin-top:8px;table-layout:fixed;width:100%;">
								<thead>
									<tr>
										<th style="width:22%;"><?php esc_html_e('Source', 'perchance-memory-manager'); ?></th>
										<th style="width:38%;"><?php esc_html_e('Entry', 'perchance-memory-manager'); ?></th>
										<th style="width:16%;"><?php esc_html_e('Target Section', 'perchance-memory-manager'); ?></th>
										<th style="width:16%;"><?php esc_html_e('Target Entity', 'perchance-memory-manager'); ?></th>
										<th style="width:8%;"><?php esc_html_e('Action', 'perchance-memory-manager'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($entry_convert_rows as $row) : ?>
										<tr class="pmm-entry-convert-row"
											data-source-section="<?php echo esc_attr($row['source_section']); ?>"
											data-source-entity="<?php echo esc_attr($row['source_entity']); ?>"
											data-source-entry="<?php echo esc_attr($row['entry']); ?>">
											<td>
												<strong><?php echo esc_html((string) $row['source_section']); ?></strong><br>
												<span class="description"><?php echo esc_html((string) ($row['source_entity'] !== '' ? $row['source_entity'] : __('(section-level)', 'perchance-memory-manager'))); ?></span>
											</td>
											<td><textarea class="large-text code pmm-entry-convert-entry" rows="3" style="width:100%;max-width:100%;min-width:0;box-sizing:border-box;"><?php echo esc_textarea($row['entry']); ?></textarea></td>
											<td>
												<select class="pmm-entry-convert-target-section" style="width:100%;max-width:100%;min-width:0;box-sizing:border-box;">
													<?php foreach (['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation', 'World Building', 'Relationships', 'NSFW', 'Notes', 'New Entries'] as $sec) : ?>
														<option value="<?php echo esc_attr($sec); ?>" <?php selected($row['source_section'], $sec); ?>><?php echo esc_html($sec); ?></option>
													<?php endforeach; ?>
												</select>
											</td>
											<td>
												<select class="pmm-entry-convert-target-entity" data-default-entity="<?php echo esc_attr($row['source_entity']); ?>" style="width:100%;max-width:100%;min-width:0;box-sizing:border-box;"></select>
											</td>
											<td>
												<select class="pmm-entry-convert-action" style="width:100%;max-width:100%;min-width:0;box-sizing:border-box;">
													<option value="keep"><?php esc_html_e('Keep', 'perchance-memory-manager'); ?></option>
													<option value="move"><?php esc_html_e('Move / Update', 'perchance-memory-manager'); ?></option>
													<option value="remove"><?php esc_html_e('Remove', 'perchance-memory-manager'); ?></option>
												</select>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>

							<?php submit_button(__('Apply Entry Conversion Changes', 'perchance-memory-manager'), 'primary', 'submit', false); ?>
						</form>

						<script>
						(function () {
							var form = document.getElementById('pmm-entry-convert-form');
							if (!form) {
								return;
							}

							var rowsJson = document.getElementById('pmm_entry_convert_rows_json');
							var sectionLevel = ['Notes', 'Relationships', 'NSFW', 'World Building', 'New Entries'];
							var sectionEntityMap = <?php echo wp_json_encode($global_entity_section_options); ?>;

							function repopulateTargetEntity(row, selectedSection) {
								var targetEntityEl = row.querySelector('.pmm-entry-convert-target-entity');
								if (!targetEntityEl) {
									return;
								}

								var previous = targetEntityEl.value || targetEntityEl.getAttribute('data-default-entity') || '';
								var options = Array.isArray(sectionEntityMap[selectedSection]) ? sectionEntityMap[selectedSection] : [];
								targetEntityEl.innerHTML = '';

								if (sectionLevel.indexOf(selectedSection) !== -1) {
									var sectionOption = document.createElement('option');
									sectionOption.value = '';
									sectionOption.textContent = '(Section-level entries)';
									targetEntityEl.appendChild(sectionOption);
								}

								options.forEach(function (name) {
									var option = document.createElement('option');
									option.value = name;
									option.textContent = name;
									targetEntityEl.appendChild(option);
								});

								if (previous && options.indexOf(previous) !== -1) {
									targetEntityEl.value = previous;
								} else if (sectionLevel.indexOf(selectedSection) !== -1) {
									targetEntityEl.value = '';
								} else if (options.length > 0) {
									targetEntityEl.value = options[0];
								}
							}

							form.querySelectorAll('tr.pmm-entry-convert-row').forEach(function (row) {
								var targetSectionEl = row.querySelector('.pmm-entry-convert-target-section');
								if (!targetSectionEl) {
									return;
								}

								repopulateTargetEntity(row, targetSectionEl.value || row.getAttribute('data-source-section') || 'Characters');
								targetSectionEl.addEventListener('change', function () {
									repopulateTargetEntity(row, targetSectionEl.value || 'Characters');
								});
							});

							form.addEventListener('submit', function () {
								var changedRows = [];
								form.querySelectorAll('tr.pmm-entry-convert-row').forEach(function (row) {
									var sourceSection = row.getAttribute('data-source-section') || '';
									var sourceEntity = row.getAttribute('data-source-entity') || '';
									var sourceEntry = row.getAttribute('data-source-entry') || '';
									var targetSectionEl = row.querySelector('.pmm-entry-convert-target-section');
									var targetEntityEl = row.querySelector('.pmm-entry-convert-target-entity');
									var entryEl = row.querySelector('.pmm-entry-convert-entry');
									var actionEl = row.querySelector('.pmm-entry-convert-action');

									if (!targetSectionEl || !targetEntityEl || !entryEl || !actionEl) {
										return;
									}

									var targetSection = targetSectionEl.value || sourceSection;
									var targetEntity = targetEntityEl.value || sourceEntity;
									if (sectionLevel.indexOf(targetSection) !== -1) {
										targetEntity = '';
									}
									var entry = (entryEl.value || '').trim();
									if (!entry) {
										entry = sourceEntry;
									}
									var action = actionEl.value || 'keep';

									var changed = action !== 'keep'
										|| targetSection !== sourceSection
										|| targetEntity !== sourceEntity
										|| entry !== sourceEntry;

									if (!changed) {
										return;
									}

									if (action === 'keep') {
										action = 'move';
									}

									changedRows.push({
										source_section: sourceSection,
										source_entity: sourceEntity,
										source_entry: sourceEntry,
										target_section: targetSection,
										target_entity: targetEntity,
										entry: entry,
										action: action
									});
								});

								rowsJson.value = JSON.stringify(changedRows);
							});
						})();
						</script>

						<?php if ($entry_convert_total_pages > 1) : ?>
							<p style="margin-top:10px;">
								<?php for ($p = 1; $p <= $entry_convert_total_pages; $p++) : ?>
									<?php
									$url = add_query_arg([
										'page' => 'perchance-memory-manager',
										'pmm_entry_convert_section' => $entry_convert_section,
										'pmm_entry_convert_entity' => $entry_convert_entity,
										'pmm_entry_convert_search' => $entry_convert_search,
										'pmm_entry_convert_include_mentions' => $entry_convert_include_mentions ? 1 : 0,
										'pmm_entry_convert_load' => 1,
										'pmm_entry_convert_per_page' => $entry_convert_per_page,
										'pmm_entry_convert_page' => $p,
									], admin_url('admin.php'));
									?>
									<a class="button <?php echo $p === $entry_convert_page ? 'button-primary' : ''; ?>" href="<?php echo esc_url($url); ?>" style="margin-right:4px;margin-bottom:4px;"><?php echo esc_html((string) $p); ?></a>
								<?php endfor; ?>
							</p>
						<?php endif; ?>
					<?php elseif ($entry_convert_load) : ?>
						<p><?php esc_html_e('No entries matched the selected conversion filters.', 'perchance-memory-manager'); ?></p>
					<?php endif; ?>
					</details>
				</div>

				<div class="pmm-card">
					<details class="pmm-collapsible-section pmm-collapsible-root">
						<summary><strong><?php esc_html_e('Global Search & Replace', 'perchance-memory-manager'); ?></strong></summary>
					<p class="description"><?php esc_html_e('Search across the current output and replace matches safely. If multiple entities in the same section end up with the same name, their entries are merged instead of lost.', 'perchance-memory-manager'); ?></p>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<?php wp_nonce_field('pmm_global_search_replace'); ?>
						<input type="hidden" name="action" value="pmm_global_search_replace">
						<p>
							<label><?php esc_html_e('Search for', 'perchance-memory-manager'); ?><br>
								<input type="text" name="pmm_global_search" class="regular-text" required>
							</label>
						</p>
						<p>
							<label><?php esc_html_e('Replace with', 'perchance-memory-manager'); ?><br>
								<input type="text" name="pmm_global_replace" class="regular-text">
							</label>
						</p>
						<p>
							<label><?php esc_html_e('Scope', 'perchance-memory-manager'); ?>
								<select name="pmm_global_scope">
									<option value="both" <?php selected($global_scope, 'both'); ?>><?php esc_html_e('Entity names and entry text', 'perchance-memory-manager'); ?></option>
									<option value="names_only" <?php selected($global_scope, 'names_only'); ?>><?php esc_html_e('Entity names only', 'perchance-memory-manager'); ?></option>
									<option value="entries_only" <?php selected($global_scope, 'entries_only'); ?>><?php esc_html_e('Entry text only', 'perchance-memory-manager'); ?></option>
								</select>
							</label>
						</p>
						<p>
							<label><input type="checkbox" name="pmm_global_case_sensitive" value="1"> <?php esc_html_e('Case-sensitive match', 'perchance-memory-manager'); ?></label>
						</p>
						<p class="description"><?php esc_html_e('When entity names collapse to the same name in a section, their entries are merged automatically.', 'perchance-memory-manager'); ?></p>
						<?php submit_button(__('Apply Global Replace', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
					</form>
					</details>
				</div>
			<?php endif; ?>

			<div class="pmm-card">
				<details class="pmm-collapsible-section">
					<summary><strong><?php esc_html_e('Known Sections and Confirmed Entities', 'perchance-memory-manager'); ?></strong></summary>
					<p><?php esc_html_e('This registry is built from processed output over time and is reused as a seed during raw import suggestions.', 'perchance-memory-manager'); ?></p>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:8px 0 12px 0;">
					<?php wp_nonce_field('pmm_import_confirmed_entities'); ?>
					<input type="hidden" name="action" value="pmm_import_confirmed_entities">
					<p>
						<label><?php esc_html_e('Target section', 'perchance-memory-manager'); ?>
							<select name="pmm_confirmed_section" style="margin-left:6px;">
								<?php foreach ($this->entity_sections() as $entity_section) : ?>
									<option value="<?php echo esc_attr($entity_section); ?>"><?php echo esc_html($entity_section); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</p>
					<textarea name="pmm_confirmed_entities_text" rows="6" class="large-text code" placeholder="Echo-7&#10;Black Max&#10;Eva Thorne"></textarea>
					<p class="description" style="margin-top:8px;"><?php esc_html_e('One entity name per line. This bulk-imports confirmed entities into the selected section and strengthens exact-leading-name routing.', 'perchance-memory-manager'); ?></p>
					<?php submit_button(__('Bulk Import Confirmed Entities', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
					</form>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:8px 0 12px 0;">
					<?php wp_nonce_field('pmm_save_confirmed_entities_section'); ?>
					<input type="hidden" name="action" value="pmm_save_confirmed_entities_section">
					<p>
						<label><?php esc_html_e('Review/Edit section list', 'perchance-memory-manager'); ?>
							<select id="pmm_confirmed_edit_section" name="pmm_confirmed_edit_section" style="margin-left:6px;">
								<?php foreach ($this->entity_sections() as $entity_section) : ?>
									<option value="<?php echo esc_attr($entity_section); ?>" <?php selected($confirmed_edit_section, $entity_section); ?>><?php echo esc_html($entity_section); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</p>
					<textarea id="pmm_confirmed_edit_entities_text" name="pmm_confirmed_edit_entities_text" rows="8" class="large-text code"><?php echo esc_textarea(isset($confirmed_section_text_map[$confirmed_edit_section]) ? (string) $confirmed_section_text_map[$confirmed_edit_section] : ''); ?></textarea>
					<p class="description" style="margin-top:8px;"><?php esc_html_e('One entity name per line. Saving replaces that section list. You can remove, rename, or reorder entries before saving.', 'perchance-memory-manager'); ?></p>
					<?php submit_button(__('Save Edited Section List', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
					</form>
					<script>
					document.addEventListener('DOMContentLoaded', function () {
						var sectionSelect = document.getElementById('pmm_confirmed_edit_section');
						var textArea = document.getElementById('pmm_confirmed_edit_entities_text');
						if (!sectionSelect || !textArea) {
							return;
						}
						var sectionMap = <?php echo wp_json_encode($confirmed_section_text_map); ?>;
						sectionSelect.addEventListener('change', function () {
							textArea.value = sectionMap[sectionSelect.value] || '';
						});
					});
					</script>
					<ul>
					<?php foreach ($this->entity_sections() as $entity_section) : ?>
						<?php $count = isset($confirmed_registry[$entity_section]) && is_array($confirmed_registry[$entity_section]) ? count($confirmed_registry[$entity_section]) : 0; ?>
						<li><?php echo esc_html($entity_section . ': ' . $count); ?></li>
					<?php endforeach; ?>
					<li><?php esc_html_e('World Building (section-level)', 'perchance-memory-manager'); ?></li>
					<li><?php esc_html_e('Relationships (section-level)', 'perchance-memory-manager'); ?></li>
					<li><?php esc_html_e('NSFW (section-level)', 'perchance-memory-manager'); ?></li>
					<li><?php esc_html_e('Notes (section-level)', 'perchance-memory-manager'); ?></li>
					</ul>
					<?php $this->render_confirmed_entity_registry_table($confirmed_registry); ?>
				</details>
			</div>



			<?php if ($has_last_output && !empty($data['entity_report']) && is_array($data['entity_report'])) : ?>
				<div class="pmm-card">
					<details class="pmm-collapsible-section pmm-collapsible-root">
						<summary><strong><?php esc_html_e('Similar Entity Review', 'perchance-memory-manager'); ?></strong></summary>
						<div style="margin-top:10px;">
							<?php $this->render_similarity_review($data['entity_report']['similar_candidates'] ?? [], isset($data['entity_report']['similar_candidates_total_found']) ? (int) $data['entity_report']['similar_candidates_total_found'] : null, !empty($data['entity_report']['similar_candidates_truncated']), $similarity_review_queue, $similarity_queue_filter); ?>
						</div>
					</details>
				</div>

				<div class="pmm-card">
					<details class="pmm-collapsible-section pmm-collapsible-root">
						<summary><strong><?php esc_html_e('Questionable Entry Review', 'perchance-memory-manager'); ?></strong></summary>
						<div style="margin-top:10px;">
							<?php $this->render_questionable_entry_review($data['entity_report']['questionable_entries'] ?? [], isset($data['entity_report']['questionable_entries_total_found']) ? (int) $data['entity_report']['questionable_entries_total_found'] : null, $questionable_review_queue, $questionable_queue_filter); ?>
						</div>
					</details>
				</div>

				<div class="pmm-card">
					<details class="pmm-collapsible-section pmm-collapsible-root">
						<summary><strong><?php esc_html_e('Automated Reclassification Review', 'perchance-memory-manager'); ?></strong></summary>
						<div style="margin-top:10px;">
							<?php $this->render_reclassification_review($data['entity_report']['reclassification_candidates'] ?? [], isset($data['entity_report']['reclassification_candidates_total_found']) ? (int) $data['entity_report']['reclassification_candidates_total_found'] : null, $reclassification_review_queue, $reclassification_queue_filter); ?>
						</div>
					</details>
				</div>

				<div class="pmm-card">
					<details class="pmm-collapsible-section pmm-collapsible-root">
						<summary><strong><?php esc_html_e('Entity Review', 'perchance-memory-manager'); ?></strong></summary>
						<div style="margin-top:10px;">
							<?php $this->render_entity_review($data['entity_report']['entities'] ?? [], $entity_review_queue, $entity_queue_filter); ?>
							<?php $this->render_hidden_entities_manager(); ?>
						</div>
					</details>
				</div>

				<div class="pmm-card">
					<details class="pmm-collapsible-section pmm-collapsible-root">
						<summary><strong><?php esc_html_e('Alias Rules', 'perchance-memory-manager'); ?></strong></summary>
						<div style="margin-top:10px;">
							<?php $this->render_alias_rules_manager(); ?>
						</div>
					</details>
				</div>
			<?php endif; ?>

			<?php if ($has_last_output || !empty($similarity_log)) : ?>
				<div class="pmm-card">
					<details class="pmm-collapsible-section pmm-collapsible-root">
						<summary><strong><?php esc_html_e('Review and Results Hub', 'perchance-memory-manager'); ?></strong></summary>
						<div class="pmm-collapsible-group" style="margin-top:10px;">
							<?php if ($has_last_output && !empty($data['entity_report']) && is_array($data['entity_report'])) : ?>
								<details class="pmm-collapsible-section">
									<summary><strong><?php esc_html_e('New Entities Added During Processing', 'perchance-memory-manager'); ?></strong></summary>
									<div style="margin-top:10px;">
										<?php $this->render_entity_groups($data['entity_report']['new_entities'] ?? []); ?>
									</div>
								</details>

								<details class="pmm-collapsible-section">
									<summary><strong><?php esc_html_e('All Entities', 'perchance-memory-manager'); ?></strong></summary>
									<div style="margin-top:10px;">
										<?php $this->render_entity_groups($data['entity_report']['entities'] ?? []); ?>
									</div>
								</details>
							<?php endif; ?>

							<details class="pmm-collapsible-section">
								<summary><strong><?php esc_html_e('Active Rules Summary', 'perchance-memory-manager'); ?></strong></summary>
								<div style="margin-top:10px;">
									<?php $this->render_rules_summary(); ?>
								</div>
							</details>

							<?php if ($has_last_output) : ?>
								<details class="pmm-collapsible-section">
									<summary><strong><?php esc_html_e('Last Processed Result', 'perchance-memory-manager'); ?></strong></summary>
									<div style="margin-top:10px;">
										<?php $this->render_last_processed_result_summary($data, $rules_dirty, $rescan_sections_enabled, $rescan_confidence, $rescan_preview_only); ?>
									</div>
								</details>
							<?php endif; ?>

							<?php if (!empty($similarity_log)) : ?>
								<details class="pmm-collapsible-section">
									<summary><strong><?php esc_html_e('Recent Similarity Decisions', 'perchance-memory-manager'); ?></strong></summary>
									<div style="margin-top:10px;">
										<?php $this->render_recent_similarity_decisions($similarity_log); ?>
									</div>
								</details>
							<?php endif; ?>
						</div>
					</details>
				</div>
			<?php endif; ?>

			<?php if ($has_last_output) : ?>
				<div class="pmm-card">
					<h2><?php esc_html_e('Editable Output Workspace', 'perchance-memory-manager'); ?></h2>
					<p class="description"><?php esc_html_e('Edit this output directly, then save it as the latest version. Use quick find to navigate large text by free text, section, or entity.', 'perchance-memory-manager'); ?></p>

					<p style="margin-bottom:8px;">
						<label><?php esc_html_e('Find text', 'perchance-memory-manager'); ?>
							<input type="search" id="pmm-preview-find" class="regular-text" placeholder="pilot memory">
						</label>
						<label style="margin-left:8px;"><?php esc_html_e('Section', 'perchance-memory-manager'); ?>
							<select id="pmm-preview-section">
								<option value=""><?php esc_html_e('(pick section)', 'perchance-memory-manager'); ?></option>
								<?php foreach ($preview_sections as $sec) : ?>
									<option value="<?php echo esc_attr($sec); ?>"><?php echo esc_html($sec); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label style="margin-left:8px;"><?php esc_html_e('Entity', 'perchance-memory-manager'); ?>
							<select id="pmm-preview-entity">
								<option value=""><?php esc_html_e('(pick entity)', 'perchance-memory-manager'); ?></option>
								<?php foreach ($preview_entities as $name) : ?>
									<option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<button type="button" class="button" id="pmm-preview-find-next" style="margin-left:8px;"><?php esc_html_e('Find Next', 'perchance-memory-manager'); ?></button>
						<button type="button" class="button" id="pmm-preview-find-prev"><?php esc_html_e('Find Prev', 'perchance-memory-manager'); ?></button>
						<button type="button" class="button" id="pmm-preview-find-clear"><?php esc_html_e('Clear', 'perchance-memory-manager'); ?></button>
						<span id="pmm-preview-find-status" class="description" style="margin-left:8px;"></span>
					</p>
					<div id="pmm-preview-find-results" class="pmm-preview-find-results" style="max-height:180px;overflow:auto;margin:0 0 10px 0;padding:8px;border:1px solid #dcdcde;background:#fff;"></div>

					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<?php wp_nonce_field('pmm_save_preview_content'); ?>
						<input type="hidden" name="action" value="pmm_save_preview_content">
						<textarea id="pmm-preview-textarea" name="pmm_preview_content" class="large-text code pmm-preview" rows="24" wrap="off" spellcheck="false" style="overflow:auto;white-space:pre;"><?php echo esc_textarea($data['content']); ?></textarea>
						<p class="description" style="margin-top:8px;"><?php esc_html_e('Save Edited Preview updates the latest output/version immediately. Reprocess is only needed after rule/staging changes.', 'perchance-memory-manager'); ?></p>
						<?php submit_button(__('Save Edited Preview as Latest Output', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
					</form>

					<script>
						document.addEventListener('DOMContentLoaded', function () {
							var textarea = document.getElementById('pmm-preview-textarea');
							var input = document.getElementById('pmm-preview-find');
							var section = document.getElementById('pmm-preview-section');
							var entity = document.getElementById('pmm-preview-entity');
							var nextBtn = document.getElementById('pmm-preview-find-next');
							var prevBtn = document.getElementById('pmm-preview-find-prev');
							var clearBtn = document.getElementById('pmm-preview-find-clear');
							var status = document.getElementById('pmm-preview-find-status');
							var results = document.getElementById('pmm-preview-find-results');
							if (!textarea || !input || !nextBtn || !prevBtn || !clearBtn || !status || !results) {
								return;
							}

							var matches = [];
							var activeMatchIndex = -1;
							var rebuildTimer = null;
							var searchMode = 'text';
							var previewIndex = null;
							var sectionNames = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation', 'World Building', 'Relationships', 'NSFW', 'Notes', 'New Entries'];

							function escapeHtml(value) {
								return String(value)
									.replace(/&/g, '&amp;')
									.replace(/</g, '&lt;')
									.replace(/>/g, '&gt;')
									.replace(/"/g, '&quot;')
									.replace(/'/g, '&#039;');
							}

							function escapeRegex(value) {
								return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
							}

							function normalizeSectionName(value) {
								return String(value || '').replace(/^#+\s*/, '').replace(/\s+/g, ' ').trim().toLowerCase();
							}

							function buildPreviewIndex() {
								var text = textarea.value || '';
								var lines = text.split('\n');
								var starts = lineStartOffsets(text);
								var index = {
									text: text,
									lines: lines,
									starts: starts,
									sections: [],
									entitiesBySection: {},
								};

								var currentSection = '';
								var currentSectionAllowsEntities = true;

								lines.forEach(function (lineText, lineIndex) {
									var trimmed = String(lineText || '').trim();
									if (!trimmed) {
										return;
									}

									if (trimmed.charAt(0) === '#') {
										var normalized = normalizeSectionName(trimmed);
										var matchedSection = sectionNames.find(function (name) {
											return normalizeSectionName(name) === normalized;
										}) || trimmed.replace(/^#+\s*/, '').trim();
										currentSection = matchedSection;
										currentSectionAllowsEntities = ['Relationships', 'NSFW', 'Notes', 'World Building', 'Technology / Systems', 'Vehicles / Transportation', 'New Entries'].indexOf(currentSection) === -1;
										if (!index.entitiesBySection[currentSection]) {
											index.entitiesBySection[currentSection] = [];
										}
										index.sections.push({
											name: currentSection,
											line: lineIndex + 1,
											start: starts[lineIndex],
											end: starts[lineIndex] + lineText.length,
											text: lineText,
										});
										return;
									}

									if (!currentSection || !currentSectionAllowsEntities) {
										return;
									}

									if (/^[A-Z][A-Za-z0-9_\-()'\/ .,&]{1,100}:?\s*$/u.test(trimmed)) {
										var entityName = trimmed.replace(/:+\s*$/, '').trim();
										if (entityName) {
											index.entitiesBySection[currentSection].push({
												name: entityName,
												line: lineIndex + 1,
												start: starts[lineIndex],
												end: starts[lineIndex] + lineText.length,
												text: lineText,
											});
										}
									}
								});

								return index;
							}

							function populatePreviewSectionOptions() {
								if (!section) {
									return;
								}
								var previous = section.value || '';
								var sectionList = [];
								var seen = {};
								(previewIndex.sections || []).forEach(function (row) {
									if (!seen[row.name]) {
										seen[row.name] = true;
										sectionList.push(row.name);
									}
								});

								section.innerHTML = '<option value=""><?php echo esc_js(__('(pick section)', 'perchance-memory-manager')); ?></option>';
								sectionList.forEach(function (name) {
									var opt = document.createElement('option');
									opt.value = name;
									opt.textContent = name;
									section.appendChild(opt);
								});

								if (previous && seen[previous]) {
									section.value = previous;
								}
							}

							function populatePreviewEntityOptions(selectedSection) {
								if (!entity) {
									return;
								}
								var previous = entity.value || '';
								var rows = selectedSection && previewIndex.entitiesBySection[selectedSection] ? previewIndex.entitiesBySection[selectedSection] : [];
								entity.innerHTML = '<option value=""><?php echo esc_js(__('(pick entity)', 'perchance-memory-manager')); ?></option>';
								rows.forEach(function (row) {
									var opt = document.createElement('option');
									opt.value = row.name;
									opt.textContent = row.name;
									entity.appendChild(opt);
								});

								var stillExists = rows.some(function (row) { return row.name === previous; });
								entity.value = stillExists ? previous : '';
							}

							function lineNumberFromIndex(text, index) {
								return text.slice(0, index).split('\n').length;
							}

							function lineStartOffsets(text) {
								var starts = [0];
								for (var i = 0; i < text.length; i++) {
									if (text.charCodeAt(i) === 10) {
										starts.push(i + 1);
									}
								}
								return starts;
							}

							function scrollToMatch(index) {
								var before = textarea.value.slice(0, index);
								var line = before.split('\n').length - 1;
								var lineHeight = parseInt(window.getComputedStyle(textarea).lineHeight, 10);
								if (!lineHeight || Number.isNaN(lineHeight)) {
									lineHeight = 18;
								}
								textarea.scrollTop = Math.max(0, (line - 3) * lineHeight);
							}

							function selectMatch(matchIndex, focusTextarea) {
								if (!matches.length || matchIndex < 0 || matchIndex >= matches.length) {
									status.textContent = 'No matches.';
									return;
								}

								activeMatchIndex = matchIndex;
								var match = matches[activeMatchIndex];
								if (focusTextarea !== false) {
									textarea.focus();
								}
								textarea.setSelectionRange(match.start, match.end);
								scrollToMatch(match.start);
								status.textContent = 'Match ' + (activeMatchIndex + 1) + ' of ' + matches.length + ' (line ' + match.line + ').';

								var activeRow = results.querySelector('[data-match-index="' + activeMatchIndex + '"]');
								results.querySelectorAll('[data-match-index]').forEach(function (row) {
									row.style.background = '';
								});
								if (activeRow) {
									activeRow.style.background = '#f0f6fc';
									activeRow.scrollIntoView({block: 'nearest'});
								}
							}

							function rebuildMatches(autoSelectFirst) {
								if (typeof autoSelectFirst === 'undefined') {
									autoSelectFirst = true;
								}
								var q = (input.value || '').trim();
								previewIndex = buildPreviewIndex();
								var text = previewIndex.text || '';
								matches = [];
								activeMatchIndex = -1;

								if (!q) {
									results.innerHTML = '<span class="description">Enter text to find matches.</span>';
									status.textContent = '';
									return;
								}

								var lowerQuery = q.toLowerCase();

								if (searchMode === 'section') {
									(previewIndex.sections || []).forEach(function (row) {
										if (normalizeSectionName(row.name) === normalizeSectionName(q)) {
											matches.push({ start: row.start, end: row.end, line: row.line });
										}
									});
								} else if (searchMode === 'entity') {
									var scopedRows = section && section.value && previewIndex.entitiesBySection[section.value] ? previewIndex.entitiesBySection[section.value] : [];
									scopedRows.forEach(function (row) {
										if (row.name.toLowerCase() === lowerQuery) {
											matches.push({ start: row.start, end: row.end, line: row.line });
										}
									});
								} else {
									(previewIndex.lines || []).forEach(function (lineText, lineIdx) {
										var lineLower = String(lineText || '').toLowerCase();
										var first = lineLower.indexOf(lowerQuery);
										if (first === -1) {
											return;
										}
										matches.push({
											start: previewIndex.starts[lineIdx] + first,
											end: previewIndex.starts[lineIdx] + first + lowerQuery.length,
											line: lineIdx + 1,
										});
									});
								}

								if (matches.length > 500) {
									matches = matches.slice(0, 500);
								}

								if (!matches.length) {
									results.innerHTML = '<span class="description">No matches found.</span>';
									status.textContent = 'No matches.';
									return;
								}

								var maxRows = Math.min(matches.length, 80);
								var rows = [];
								for (var i = 0; i < maxRows; i++) {
									var m = matches[i];
									var snippetStart = Math.max(0, m.start - 40);
									var snippetEnd = Math.min(text.length, m.end + 60);
									var left = escapeHtml(text.slice(snippetStart, m.start));
									var mid = escapeHtml(text.slice(m.start, m.end));
									var right = escapeHtml(text.slice(m.end, snippetEnd));
									rows.push('<button type="button" class="button-link" data-match-index="' + i + '" style="display:block;width:100%;text-align:left;padding:4px 2px;">'
										+ '<strong>L' + m.line + '</strong>: ' + left + '<mark>' + mid + '</mark>' + right
										+ '</button>');
								}

								if (matches.length > maxRows) {
									rows.push('<div class="description" style="padding:4px 2px;">Showing first ' + maxRows + ' of ' + matches.length + ' matches.</div>');
								}

								results.innerHTML = rows.join('');
								results.querySelectorAll('[data-match-index]').forEach(function (row) {
									row.addEventListener('click', function () {
										var idx = parseInt(row.getAttribute('data-match-index'), 10);
										if (!Number.isNaN(idx)) {
											selectMatch(idx, true);
										}
									});
								});

								if (autoSelectFirst) {
									selectMatch(0, false);
								} else {
									status.textContent = matches.length + ' matches.';
								}
							}

							function scheduleRebuild(autoSelectFirst) {
								if (typeof autoSelectFirst === 'undefined') {
									autoSelectFirst = true;
								}
								if (rebuildTimer) {
									window.clearTimeout(rebuildTimer);
								}
								rebuildTimer = window.setTimeout(function () {
									rebuildMatches(autoSelectFirst);
								}, 120);
							}

							function moveMatch(delta) {
								if (!matches.length) {
									rebuildMatches();
									return;
								}

								var nextIndex = activeMatchIndex + delta;
								if (nextIndex < 0) {
									nextIndex = matches.length - 1;
								}
								if (nextIndex >= matches.length) {
									nextIndex = 0;
								}
								selectMatch(nextIndex, true);
							}

							nextBtn.addEventListener('click', function () {
								moveMatch(1);
							});

							prevBtn.addEventListener('click', function () {
								moveMatch(-1);
							});

							clearBtn.addEventListener('click', function () {
								input.value = '';
								if (section) {
									section.value = '';
								}
								if (entity) {
									entity.value = '';
								}
								status.textContent = '';
								matches = [];
								activeMatchIndex = -1;
								results.innerHTML = '<span class="description">Enter text to find matches.</span>';
								textarea.focus();
							});

							input.addEventListener('input', function () {
								searchMode = 'text';
								if (section) {
									section.value = '';
								}
								if (entity) {
									entity.value = '';
								}
								scheduleRebuild(true);
							});

							textarea.addEventListener('input', function () {
								previewIndex = buildPreviewIndex();
								populatePreviewSectionOptions();
								populatePreviewEntityOptions(section ? section.value : '');
								if ((input.value || '').trim() === '') {
									return;
								}
								scheduleRebuild(false);
							});

							input.addEventListener('keydown', function (e) {
								if (e.key === 'Enter') {
									e.preventDefault();
									moveMatch(1);
								}
							});

							if (section) {
								section.addEventListener('change', function () {
									searchMode = section.value ? 'section' : 'text';
									populatePreviewEntityOptions(section.value || '');
									if (entity) {
										entity.value = '';
									}
									input.value = section.value ? section.value : '';
									rebuildMatches();
								});
							}

							if (entity) {
								entity.addEventListener('change', function () {
									searchMode = entity.value ? 'entity' : 'text';
									input.value = entity.value ? entity.value : '';
									rebuildMatches();
								});
							}

							previewIndex = buildPreviewIndex();
							populatePreviewSectionOptions();
							populatePreviewEntityOptions(section ? section.value : '');
							results.innerHTML = '<span class="description">Enter text to find matches.</span>';
						});
					</script>

					<script>
						document.addEventListener('DOMContentLoaded', function () {
							var storageKeyPrefix = 'pmm-collapsible-state:' + window.location.pathname + ':';
							var collapsibles = document.querySelectorAll('.pmm-wrap details.pmm-collapsible-section');

							function normalizeLabel(value) {
								return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
							}

							function directSummaryText(detail) {
								var summary = detail.querySelector(':scope > summary');
								if (!summary) {
									return '';
								}
								return normalizeLabel(summary.textContent);
							}

							function buildCollapsePath(detail) {
								var parts = [];
								var current = detail;

								while (current && current.matches && current.matches('details.pmm-collapsible-section')) {
									parts.unshift(directSummaryText(current));
									current = current.parentElement ? current.parentElement.closest('details.pmm-collapsible-section') : null;
								}

								return parts.filter(function (part) { return part !== ''; }).join(' > ');
							}

							collapsibles.forEach(function (detail) {
								var path = buildCollapsePath(detail);
								if (!path) {
									return;
								}

								var storageKey = storageKeyPrefix + path;
								try {
									var stored = window.localStorage.getItem(storageKey);
									if (stored === 'open') {
										detail.open = true;
									} else if (stored === 'closed') {
										detail.open = false;
									}
								} catch (error) {
									return;
								}

								detail.addEventListener('toggle', function () {
									try {
										window.localStorage.setItem(storageKey, detail.open ? 'open' : 'closed');
									} catch (error) {
									}
								});
							});
						});
					</script>

					<script>
						document.addEventListener('DOMContentLoaded', function () {
							var forms = document.querySelectorAll('form.pmm-review-form[id]');
							if (!forms.length) {
								return;
							}

							function formKey(form) {
								var stampInput = form.querySelector('input[name="pmm_review_dataset_stamp"]');
								var stamp = stampInput ? String(stampInput.value || '0') : '0';
								return 'pmm-review-draft:' + window.location.pathname + ':' + window.location.search + ':' + form.id + ':' + stamp;
							}

							function draftFields(form) {
								return Array.prototype.slice.call(form.querySelectorAll('input[name], select[name], textarea[name]')).filter(function (field) {
									if (!field || !field.name) {
										return false;
									}
									if (field.type === 'file' || field.type === 'submit' || field.type === 'button' || field.type === 'image' || field.type === 'reset') {
										return false;
									}
									return true;
								});
							}

							function saveDraft(form) {
								var fields = draftFields(form);
								var payload = {
									fields: fields.map(function (field) {
										return {
											name: field.name,
											type: String(field.type || '').toLowerCase(),
											value: field.value,
											checked: !!field.checked
										};
									}),
									savedAt: Date.now()
								};

								try {
									window.localStorage.setItem(formKey(form), JSON.stringify(payload));
								} catch (error) {
								}
							}

							function restoreDraft(form) {
								var raw = '';
								try {
									raw = window.localStorage.getItem(formKey(form)) || '';
								} catch (error) {
									return;
								}
								if (!raw) {
									return;
								}

								var payload = null;
								try {
									payload = JSON.parse(raw);
								} catch (error) {
									return;
								}
								if (!payload || !Array.isArray(payload.fields)) {
									return;
								}

								var fields = draftFields(form);
								if (!fields.length || payload.fields.length !== fields.length) {
									return;
								}

								for (var i = 0; i < fields.length; i++) {
									var target = fields[i];
									var source = payload.fields[i];
									if (!source || source.name !== target.name) {
										return;
									}
								}

								payload.fields.forEach(function (saved, index) {
									var field = fields[index];
									if (!field) {
										return;
									}

									if (saved.type === 'checkbox' || saved.type === 'radio') {
										field.checked = !!saved.checked;
									}
									if (typeof saved.value === 'string') {
										field.value = saved.value;
									}
								});

								var status = document.createElement('p');
								status.className = 'description';
								status.style.margin = '8px 0';
								status.textContent = '<?php echo esc_js(__('Recovered unsaved review draft from your browser for this page.', 'perchance-memory-manager')); ?>';
								form.insertBefore(status, form.firstChild);
							}

							forms.forEach(function (form) {
								restoreDraft(form);

								var timer = null;
								var queueSave = function () {
									if (timer) {
										window.clearTimeout(timer);
									}
									timer = window.setTimeout(function () {
										saveDraft(form);
									}, 250);
								};

								form.addEventListener('input', queueSave);
								form.addEventListener('change', queueSave);
								form.addEventListener('submit', function () {
									try {
										window.localStorage.removeItem(formKey(form));
									} catch (error) {
									}
								});
							});

							window.addEventListener('beforeunload', function () {
								forms.forEach(function (form) {
									saveDraft(form);
								});
							});
						});
					</script>

				</div>
			<?php endif; ?>

		</div>
		<?php
	}

	private function render_last_processed_result_summary($data, $rules_dirty, $rescan_sections_enabled = false, $rescan_confidence = 84, $rescan_preview_only = false) {
		echo '<ul class="pmm-stats">';
		echo '<li><strong>' . esc_html__('Original file:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['original_filename'] ?? '')) . '</li>';
		echo '<li><strong>' . esc_html__('Sections:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['sections'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Entities:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['entities'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Bullets:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['bullets'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Removed (total):', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['removed_total'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Removed (entity review rules):', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['removed_by_entity_rule'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Removed (questionable entry rules):', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['removed_by_entry_rule'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Removed (sequence match):', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['removed_by_sequence'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Removed (mundane noise):', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['removed_mundane_noise'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Removed (NSFW non-character):', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['removed_nsfw_non_character'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Removed (duplicates):', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['removed_duplicates'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Recategorization scan enabled:', 'perchance-memory-manager') . '</strong> ' . (!empty($data['stats']['rescan_sections']) ? esc_html__('Yes', 'perchance-memory-manager') : esc_html__('No', 'perchance-memory-manager')) . '</li>';
		echo '<li><strong>' . esc_html__('Recategorization confidence threshold:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['rescan_confidence'] ?? $rescan_confidence)) . '%</li>';
		echo '<li><strong>' . esc_html__('Recategorization dry run mode:', 'perchance-memory-manager') . '</strong> ' . (!empty($data['stats']['rescan_preview_only']) ? esc_html__('Yes', 'perchance-memory-manager') : esc_html__('No', 'perchance-memory-manager')) . '</li>';
		echo '<li><strong>' . esc_html__('Entries scanned for recategorization:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['reclassification_scanned'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Entries proposed for recategorization:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['reclassification_proposed'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Entries auto-recategorized:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['reclassification_moved'] ?? 0)) . '</li>';
		echo '<li><strong>' . esc_html__('Mode:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['mode'] ?? '')) . '</li>';
		echo '<li><strong>' . esc_html__('Format:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['format'] ?? '')) . '</li>';
		echo '</ul>';

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pmm-process-settings-form" style="margin-bottom:10px;">';
		wp_nonce_field('pmm_reprocess_last_output');
		echo '<input type="hidden" name="action" value="pmm_reprocess_last_output">';
		echo '<p class="description" style="margin:0 0 8px 0;">' . esc_html__('This uses the current settings from the main Upload and Process table, including recategorization scan options.', 'perchance-memory-manager') . '</p>';
		submit_button(__('Reprocess Last Output (No Re-upload)', 'perchance-memory-manager'), 'secondary', 'submit', false);
		echo '</form>';

		$preview_rows = isset($data['stats']['reclassification_preview_rows']) && is_array($data['stats']['reclassification_preview_rows']) ? array_slice($data['stats']['reclassification_preview_rows'], 0, 80) : [];
		if (!empty($preview_rows)) {
			echo '<details style="margin-top:8px;"><summary><strong>' . esc_html__('Recategorization Dry-Run Preview (sample)', 'perchance-memory-manager') . '</strong></summary>';
			echo '<div style="margin-top:8px;overflow:auto;"><table class="widefat striped"><thead><tr><th>' . esc_html__('From', 'perchance-memory-manager') . '</th><th>' . esc_html__('To', 'perchance-memory-manager') . '</th><th>' . esc_html__('Entry', 'perchance-memory-manager') . '</th><th>' . esc_html__('Confidence', 'perchance-memory-manager') . '</th><th>' . esc_html__('Reason', 'perchance-memory-manager') . '</th></tr></thead><tbody>';
			foreach ($preview_rows as $row) {
				if (!is_array($row)) {
					continue;
				}
				$from = trim((string) (($row['from_section'] ?? '') . (($row['from_entity'] ?? '') !== '' ? ' / ' . $row['from_entity'] : ' / (section-level)')));
				$to = trim((string) (($row['to_section'] ?? '') . (($row['to_entity'] ?? '') !== '' ? ' / ' . $row['to_entity'] : ' / (section-level)')));
				echo '<tr>';
				echo '<td>' . esc_html($from) . '</td>';
				echo '<td>' . esc_html($to) . '</td>';
				echo '<td style="min-width:320px;white-space:pre-wrap;">' . esc_html((string) ($row['entry'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) ((int) ($row['confidence'] ?? 0))) . '%</td>';
				echo '<td>' . esc_html((string) ($row['reason'] ?? '')) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table></div></details>';
		}

		if ($rules_dirty) {
			echo '<p class="description" style="margin-top:-6px;margin-bottom:10px;">' . esc_html__('Reprocess is currently recommended because output-affecting rules/staging changed.', 'perchance-memory-manager') . '</p>';
		} else {
			echo '<p class="description" style="margin-top:-6px;margin-bottom:10px;">' . esc_html__('Reprocess is optional right now. Use it when you changed rules, staged raw rows, or want a fresh full pipeline pass.', 'perchance-memory-manager') . '</p>';
		}
	}

	private function render_recent_similarity_decisions($similarity_log) {
		echo '<p class="description" style="margin-top:8px;">' . esc_html__('Similarity review is the main place to create and manage alias mappings now.', 'perchance-memory-manager') . '</p>';
		echo '<ul>';
		foreach (array_slice((array) $similarity_log, 0, 25) as $row) {
			$ts = isset($row['time']) ? (int) $row['time'] : 0;
			$when = $ts ? wp_date('Y-m-d H:i', $ts) : '';
			$action = isset($row['action']) ? (string) $row['action'] : '';
			$section = isset($row['section']) ? (string) $row['section'] : '';
			$a = isset($row['a']) ? (string) $row['a'] : '';
			$b = isset($row['b']) ? (string) $row['b'] : '';
			$canonical = isset($row['canonical']) ? (string) $row['canonical'] : '';
			echo '<li>' . esc_html(trim($when . ' | ' . $section . ' | ' . $action . ' | ' . $a . ' <> ' . $b . ($canonical ? ' => ' . $canonical : ''))) . '</li>';
		}
		echo '</ul>';
	}

	private function get_download_url() {
		return wp_nonce_url(
			add_query_arg([
				'action' => 'pmm_download_last_output',
			], admin_url('admin-post.php')),
			'pmm_download_last_output'
		);
	}

	private function get_version_public_url($filename) {
		$filename = trim((string) $filename);
		if ($filename === '') {
			return '';
		}

		return trailingslashit(PMM_PLUGIN_URL . 'versions') . rawurlencode($filename);
	}

	private function get_error_message($code) {
		$messages = [
			'missing_file' => __('No file was uploaded.', 'perchance-memory-manager'),
			'invalid_type' => __('Only .txt and .md files are allowed.', 'perchance-memory-manager'),
			'read_failed' => __('The uploaded file could not be read.', 'perchance-memory-manager'),
			'storage_failed' => __('Could not create plugin job storage directory.', 'perchance-memory-manager'),
			'store_failed' => __('The uploaded file could not be moved into batch job storage.', 'perchance-memory-manager'),
			'reprocess_missing' => __('No prior output is available to reprocess.', 'perchance-memory-manager'),
			'missing_job' => __('Batch job ID was missing.', 'perchance-memory-manager'),
			'job_expired' => __('Batch job expired or could not be found. Please upload the file again.', 'perchance-memory-manager'),
			'latest_file_missing' => __('Latest version file could not be found. Run a processing cycle first to create a versioned file.', 'perchance-memory-manager'),
			'entity_update_missing' => __('Could not load latest processed dataset for entity updates. Process a file first.', 'perchance-memory-manager'),
			'preview_missing' => __('No editable preview content was available to save.', 'perchance-memory-manager'),
			'global_replace_missing' => __('Global search and replace requires a search term, and renaming entities requires a replacement value.', 'perchance-memory-manager'),
			'global_entity_stale' => __('Entry conversion data is stale because output changed since this page was loaded. Reload entries and try again.', 'perchance-memory-manager'),
		];

		return $messages[$code] ?? __('An unknown error occurred.', 'perchance-memory-manager');
	}

	private function build_progress($job) {
		if (empty($job) || !is_array($job)) {
			return [
				'percent' => 0,
				'label' => __('Starting', 'perchance-memory-manager'),
				'detail' => __('Waiting for the first batch to begin.', 'perchance-memory-manager'),
			];
		}

		$stage = isset($job['stage']) ? (string) $job['stage'] : 'parsing';

		if ($stage === 'parsing') {
			$total = max(1, (int) $job['total_lines']);
			$done = min($total, (int) $job['line_offset']);
			$ratio = $done / $total;

			return [
				'percent' => (int) floor($ratio * 70),
				'label' => __('Parsing', 'perchance-memory-manager'),
				'detail' => sprintf(__('Parsed %1$d of %2$d lines.', 'perchance-memory-manager'), $done, $total),
			];
		}

		if ($stage === 'dedupe') {
			$total = max(1, count((array) $job['dedupe_queue']));
			$done = min($total, (int) $job['dedupe_index']);
			$ratio = $done / $total;

			return [
				'percent' => 70 + (int) floor($ratio * 25),
				'label' => __('Deduplicating', 'perchance-memory-manager'),
				'detail' => sprintf(__('Deduped %1$d of %2$d entity buckets.', 'perchance-memory-manager'), $done, $total),
			];
		}

		if ($stage === 'render') {
			return [
				'percent' => 96,
				'label' => __('Rendering', 'perchance-memory-manager'),
				'detail' => __('Building the final output file.', 'perchance-memory-manager'),
			];
		}

		return [
			'percent' => 99,
			'label' => __('Finishing', 'perchance-memory-manager'),
			'detail' => __('Finalizing the batch job.', 'perchance-memory-manager'),
		];
	}

	private function get_batch_job_state($job_id) {
		$job_key = 'pmm_job_' . get_current_user_id() . '_' . $this->normalize_job_id($job_id);
		$legacy = get_transient($job_key);
		if (is_array($legacy) && isset($legacy['stage'])) {
			return $legacy;
		}

		$state_path = $this->build_job_state_path($job_id);
		if (!file_exists($state_path)) {
			return null;
		}

		if (false === $legacy) {
			@unlink($state_path);
			return null;
		}

		$raw = @file_get_contents($state_path);
		if (!is_string($raw) || $raw === '') {
			return null;
		}

		$state = @unserialize($raw);
		if (!is_array($state)) {
			return null;
		}

		return $state;
	}

	private function build_job_state_path($job_id) {
		$uploads = wp_upload_dir();
		return trailingslashit($uploads['basedir']) . 'pmm-jobs/' . $this->normalize_job_id($job_id) . '-state.bin';
	}

	private function normalize_job_id($job_id) {
		$normalized = sanitize_key((string) $job_id);
		return $normalized !== '' ? $normalized : 'job_unknown';
	}

	private function render_entity_groups($groups) {
		if (empty($groups) || !is_array($groups)) {
			echo '<p>' . esc_html__('None', 'perchance-memory-manager') . '</p>';
			return;
		}

		echo '<ul>';
		foreach ($groups as $section => $names) {
			$names = is_array($names) ? array_values(array_filter(array_map('trim', $names), static function($v) {
				return $v !== '';
			})) : [];

			echo '<li><strong>' . esc_html((string) $section) . ':</strong> ';
			if (empty($names)) {
				echo esc_html__('None', 'perchance-memory-manager');
			} else {
				echo esc_html(implode(', ', $names));
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	private function render_similarity_review($candidates, $total_found = null, $was_truncated = false, $queue = [], $queue_filter = 'pending') {
		if (empty($candidates) || !is_array($candidates)) {
			echo '<p>' . esc_html__('No similar entity pairs detected.', 'perchance-memory-manager') . '</p>';
			return;
		}

		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation', 'World Building', 'Relationships', 'NSFW', 'Notes'];
		$dataset_stamp = (int) get_option('pmm_latest_version_saved_at', 0);
		$reviewed_by_id = [];
		foreach ((array) $queue as $id => $row) {
			$id = sanitize_key((string) $id);
			if ($id === '' || !is_array($row)) {
				continue;
			}

			$status = isset($row['status']) ? sanitize_key((string) $row['status']) : '';
			$stamp = isset($row['stamp']) ? (int) $row['stamp'] : 0;
			if ($status !== 'reviewed') {
				continue;
			}
			if ($dataset_stamp > 0 && $stamp > 0 && $stamp !== $dataset_stamp) {
				continue;
			}
			$reviewed_by_id[$id] = true;
		}

		$pending_candidates = [];
		$reviewed_candidates = [];
		foreach ($candidates as $candidate) {
			$id = isset($candidate['id']) ? sanitize_key((string) $candidate['id']) : '';
			if ($id === '') {
				continue;
			}

			if (isset($reviewed_by_id[$id])) {
				$reviewed_candidates[] = $candidate;
			} else {
				$pending_candidates[] = $candidate;
			}
		}

		if (!in_array($queue_filter, ['pending', 'reviewed', 'all'], true)) {
			$queue_filter = 'pending';
		}

		$display_candidates = $pending_candidates;
		if ($queue_filter === 'reviewed') {
			$display_candidates = $reviewed_candidates;
		} elseif ($queue_filter === 'all') {
			$display_candidates = array_values($candidates);
		}

		$shown_count = count($candidates);
		$resolved_total = is_numeric($total_found) ? max(0, (int) $total_found) : $shown_count;
		if ($resolved_total > $shown_count) {
			echo '<p>' . esc_html(sprintf(__('Detected %1$d potentially similar pairs, showing first %2$d. Review and save decisions.', 'perchance-memory-manager'), $resolved_total, $shown_count)) . '</p>';
		} else {
			echo '<p>' . esc_html(sprintf(__('Detected %d potentially similar pairs. Review and save decisions.', 'perchance-memory-manager'), $shown_count)) . '</p>';
		}
		echo '<p class="description">' . esc_html(sprintf(__('Queue view: pending %1$d, reviewed %2$d, total %3$d.', 'perchance-memory-manager'), count($pending_candidates), count($reviewed_candidates), count($candidates))) . '</p>';
		echo '<p style="margin:8px 0;">';
		echo '<strong>' . esc_html__('Show:', 'perchance-memory-manager') . '</strong> ';
		echo '<a class="button ' . ($queue_filter === 'pending' ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['pmm_similarity_queue_filter' => 'pending'])) . '">' . esc_html__('Pending', 'perchance-memory-manager') . '</a> ';
		echo '<a class="button ' . ($queue_filter === 'reviewed' ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['pmm_similarity_queue_filter' => 'reviewed'])) . '">' . esc_html__('Reviewed', 'perchance-memory-manager') . '</a> ';
		echo '<a class="button ' . ($queue_filter === 'all' ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['pmm_similarity_queue_filter' => 'all'])) . '">' . esc_html__('All', 'perchance-memory-manager') . '</a>';
		echo '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:8px 0 10px 0;">';
		wp_nonce_field('pmm_reset_similarity_review_queue');
		echo '<input type="hidden" name="action" value="pmm_reset_similarity_review_queue">';
		echo '<button type="submit" class="button">' . esc_html__('Reset Similarity Review Queue State', 'perchance-memory-manager') . '</button>';
		echo '</form>';
		if ($was_truncated) {
			echo '<p class="description" style="color:#b45309;">' . esc_html__('Similarity scanning was capped for performance on this large dataset. Results shown here are a high-confidence subset.', 'perchance-memory-manager') . '</p>';
		}
		echo '<p class="description">' . esc_html__('Section and entity names are editable. Keep separate hides the original suggestion pair; merge actions save alias rules for future runs.', 'perchance-memory-manager') . '</p>';
		if (empty($display_candidates)) {
			echo '<p>' . esc_html__('No entries in this queue view.', 'perchance-memory-manager') . '</p>';
			return;
		}
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="pmm-similarity-review-form" class="pmm-review-form">';
		wp_nonce_field('pmm_apply_similarity_review');
		echo '<input type="hidden" name="action" value="pmm_apply_similarity_review">';
		echo '<input type="hidden" name="pmm_review_dataset_stamp" value="' . esc_attr((string) $dataset_stamp) . '">';
		echo '<input type="hidden" name="pmm_similarity_queue_filter" value="' . esc_attr($queue_filter) . '">';
		echo '<input type="hidden" name="pmm_similarity_expected_count" value="' . esc_attr((string) count($display_candidates)) . '">';
		echo '<p style="margin:12px 0 8px;">';
		echo '<label for="pmm-similarity-bulk-action"><strong>' . esc_html__('Bulk action for all rows', 'perchance-memory-manager') . '</strong></label> ';
		echo '<select id="pmm-similarity-bulk-action" class="regular-text pmm-bulk-action">';
		echo '<option value="">' . esc_html__('Choose an action', 'perchance-memory-manager') . '</option>';
		echo '<option value="merge_to_suggested">' . esc_html__('Merge using Canonical Target field', 'perchance-memory-manager') . '</option>';
		echo '<option value="merge_to_a">' . esc_html__('Merge B into A', 'perchance-memory-manager') . '</option>';
		echo '<option value="merge_to_b">' . esc_html__('Merge A into B', 'perchance-memory-manager') . '</option>';
		echo '<option value="keep">' . esc_html__('Keep separate (hide this suggestion)', 'perchance-memory-manager') . '</option>';
		echo '</select> ';
		echo '<button type="button" class="button pmm-apply-bulk-action">' . esc_html__('Apply to all rows', 'perchance-memory-manager') . '</button>';
		echo '</p>';
		echo '<table class="widefat striped" style="margin-top:8px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Section', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entity A', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entity B', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Score', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Canonical Target (Editable)', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Touched', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Action', 'perchance-memory-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($display_candidates as $candidate) {
			$id = isset($candidate['id']) ? sanitize_key((string) $candidate['id']) : '';
			if ($id === '') {
				continue;
			}

			$section = isset($candidate['section']) ? (string) $candidate['section'] : '';
			$a = isset($candidate['a']) ? (string) $candidate['a'] : '';
			$b = isset($candidate['b']) ? (string) $candidate['b'] : '';
			$score = isset($candidate['score_percent']) ? (int) $candidate['score_percent'] : 0;
			$canonical = isset($candidate['suggested_canonical']) ? (string) $candidate['suggested_canonical'] : '';
			$reason = isset($candidate['reason']) ? (string) $candidate['reason'] : '';
			$is_reviewed = isset($reviewed_by_id[$id]);

			echo '<tr>';
			echo '<td>';
			echo '<select name="pmm_similarity[' . esc_attr($id) . '][section]">';
			foreach ($sections as $sec) {
				echo '<option value="' . esc_attr($sec) . '" ' . selected($section, $sec, false) . '>' . esc_html($sec) . '</option>';
			}
			echo '</select>';
			echo '</td>';
			echo '<td>' . esc_html($a) . '</td>';
			echo '<td>' . esc_html($b) . '</td>';
			echo '<td>' . esc_html((string) $score) . '%</td>';
			echo '<td>';
			echo '<input type="text" class="regular-text" name="pmm_similarity[' . esc_attr($id) . '][canonical]" value="' . esc_attr($canonical) . '">';
			if ($reason !== '') {
				echo '<div class="description" style="margin-top:4px;">' . esc_html($reason) . '</div>';
			}
			echo '</td>';
			echo '<td>' . esc_html($is_reviewed ? __('Reviewed', 'perchance-memory-manager') : __('Pending', 'perchance-memory-manager')) . '</td>';
			echo '<td>';
			echo '<input type="hidden" name="pmm_similarity[' . esc_attr($id) . '][a]" value="' . esc_attr($a) . '">';
			echo '<input type="hidden" name="pmm_similarity[' . esc_attr($id) . '][b]" value="' . esc_attr($b) . '">';
			echo '<input type="hidden" name="pmm_similarity[' . esc_attr($id) . '][original_section]" value="' . esc_attr($section) . '">';
			echo '<input type="hidden" name="pmm_similarity[' . esc_attr($id) . '][original_a]" value="' . esc_attr($a) . '">';
			echo '<input type="hidden" name="pmm_similarity[' . esc_attr($id) . '][original_b]" value="' . esc_attr($b) . '">';
			echo '<select name="pmm_similarity[' . esc_attr($id) . '][action]">';
			echo '<option value="skip" selected>' . esc_html__('No change', 'perchance-memory-manager') . '</option>';
			echo '<option value="merge_to_suggested">' . esc_html__('Merge using Canonical Target field', 'perchance-memory-manager') . '</option>';
			echo '<option value="merge_to_a">' . esc_html__('Merge B into A', 'perchance-memory-manager') . '</option>';
			echo '<option value="merge_to_b">' . esc_html__('Merge A into B', 'perchance-memory-manager') . '</option>';
			echo '<option value="keep">' . esc_html__('Keep separate (hide this suggestion)', 'perchance-memory-manager') . '</option>';
			echo '</select>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p style="margin-top:8px;">';
		echo '<label><input type="checkbox" name="pmm_apply_now" value="1" checked> ' . esc_html__('After saving decisions, immediately reprocess current result', 'perchance-memory-manager') . '</label>';
		echo '</p>';
		submit_button(__('Save Similarity Decisions', 'perchance-memory-manager'), 'secondary', 'submit', false, ['style' => 'margin-top:10px;']);
		echo '</form>';
		echo '<script>(function(){var form=document.getElementById("pmm-similarity-review-form");if(!form){return;}var bulkSelect=form.querySelector(".pmm-bulk-action");var bulkButton=form.querySelector(".pmm-apply-bulk-action");if(!bulkSelect||!bulkButton){return;}bulkButton.addEventListener("click",function(){var value=bulkSelect.value;if(!value){return;}if(!confirm("' . esc_js(__('Apply this similarity action to all rows in the table?', 'perchance-memory-manager')) . '")){return;}form.querySelectorAll("select[name$=\"[action]\"]").forEach(function(select){select.value=value;});form.submit();});})();</script>';
	}

	private function render_questionable_entry_review($candidates, $total_found = null, $queue = [], $queue_filter = 'pending') {
		if (empty($candidates) || !is_array($candidates)) {
			echo '<p>' . esc_html__('No questionable entries currently detected by the active heuristics.', 'perchance-memory-manager') . '</p>';
			return;
		}

		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation', 'World Building', 'Relationships', 'NSFW', 'Notes'];
		$dataset_stamp = (int) get_option('pmm_latest_version_saved_at', 0);
		$reviewed_by_id = [];
		foreach ((array) $queue as $id => $row) {
			$id = sanitize_key((string) $id);
			if ($id === '' || !is_array($row)) {
				continue;
			}

			$status = isset($row['status']) ? sanitize_key((string) $row['status']) : '';
			$stamp = isset($row['stamp']) ? (int) $row['stamp'] : 0;
			if ($status !== 'reviewed') {
				continue;
			}
			if ($dataset_stamp > 0 && $stamp > 0 && $stamp !== $dataset_stamp) {
				continue;
			}
			$reviewed_by_id[$id] = true;
		}

		$pending_candidates = [];
		$reviewed_candidates = [];
		foreach ($candidates as $candidate) {
			$id = isset($candidate['id']) ? sanitize_key((string) $candidate['id']) : '';
			if ($id === '') {
				continue;
			}

			if (isset($reviewed_by_id[$id])) {
				$reviewed_candidates[] = $candidate;
			} else {
				$pending_candidates[] = $candidate;
			}
		}

		if (!in_array($queue_filter, ['pending', 'reviewed', 'all'], true)) {
			$queue_filter = 'pending';
		}

		$display_candidates = $pending_candidates;
		if ($queue_filter === 'reviewed') {
			$display_candidates = $reviewed_candidates;
		} elseif ($queue_filter === 'all') {
			$display_candidates = array_values($candidates);
		}

		$shown_count = count($candidates);
		$resolved_total = is_numeric($total_found) ? max(0, (int) $total_found) : $shown_count;
		if ($resolved_total > $shown_count) {
			echo '<p>' . esc_html(sprintf(__('Questionable entries: %1$d found, showing first %2$d. Review and save decisions.', 'perchance-memory-manager'), $resolved_total, $shown_count)) . '</p>';
		} else {
			echo '<p>' . esc_html(sprintf(__('Questionable entries: %d found. Review and save decisions.', 'perchance-memory-manager'), $shown_count)) . '</p>';
		}
		echo '<p class="description">' . esc_html(sprintf(__('Queue view: pending %1$d, reviewed %2$d, total %3$d.', 'perchance-memory-manager'), count($pending_candidates), count($reviewed_candidates), count($candidates))) . '</p>';
		echo '<p style="margin:8px 0;">';
		echo '<strong>' . esc_html__('Show:', 'perchance-memory-manager') . '</strong> ';
		echo '<a class="button ' . ($queue_filter === 'pending' ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['pmm_questionable_queue_filter' => 'pending'])) . '">' . esc_html__('Pending', 'perchance-memory-manager') . '</a> ';
		echo '<a class="button ' . ($queue_filter === 'reviewed' ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['pmm_questionable_queue_filter' => 'reviewed'])) . '">' . esc_html__('Reviewed', 'perchance-memory-manager') . '</a> ';
		echo '<a class="button ' . ($queue_filter === 'all' ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['pmm_questionable_queue_filter' => 'all'])) . '">' . esc_html__('All', 'perchance-memory-manager') . '</a>';
		echo '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:8px 0 10px 0;">';
		wp_nonce_field('pmm_reset_questionable_review_queue');
		echo '<input type="hidden" name="action" value="pmm_reset_questionable_review_queue">';
		echo '<button type="submit" class="button">' . esc_html__('Reset Questionable Review Queue State', 'perchance-memory-manager') . '</button>';
		echo '</form>';
		echo '<p class="description">' . esc_html__('Section, entity, and entry are editable. Use Update entry now to immediately move/edit this line in latest output. Remove adds an output rule for next reprocess.', 'perchance-memory-manager') . '</p>';
		if (empty($display_candidates)) {
			echo '<p>' . esc_html__('No entries in this queue view.', 'perchance-memory-manager') . '</p>';
			return;
		}
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="pmm-questionable-review-form" class="pmm-review-form">';
		wp_nonce_field('pmm_apply_questionable_review');
		echo '<input type="hidden" name="action" value="pmm_apply_questionable_review">';
		echo '<input type="hidden" name="pmm_review_dataset_stamp" value="' . esc_attr((string) $dataset_stamp) . '">';
		echo '<input type="hidden" name="pmm_questionable_queue_filter" value="' . esc_attr($queue_filter) . '">';
		echo '<input type="hidden" name="pmm_questionable_expected_count" value="' . esc_attr((string) count($display_candidates)) . '">';
		echo '<p style="margin:12px 0 8px;">';
		echo '<label for="pmm-questionable-bulk-action"><strong>' . esc_html__('Bulk action for all rows', 'perchance-memory-manager') . '</strong></label> ';
		echo '<select id="pmm-questionable-bulk-action" class="regular-text pmm-bulk-action" name="pmm_questionable_bulk_action">';
		echo '<option value="">' . esc_html__('Choose an action', 'perchance-memory-manager') . '</option>';
		echo '<option value="keep">' . esc_html__('Keep', 'perchance-memory-manager') . '</option>';
		echo '<option value="hide">' . esc_html__('Keep and hide (do not ask again)', 'perchance-memory-manager') . '</option>';
		echo '<option value="remove">' . esc_html__('Remove on next reprocess', 'perchance-memory-manager') . '</option>';
		echo '</select> ';
		echo '<button type="submit" name="pmm_questionable_apply_bulk" value="1" class="button pmm-apply-bulk-action">' . esc_html__('Apply to all rows', 'perchance-memory-manager') . '</button>';
		echo '</p>';
		echo '<table class="widefat striped" style="margin-top:8px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Section', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entity', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entry', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Why flagged', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Touched', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Action', 'perchance-memory-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($display_candidates as $candidate) {
			$id = isset($candidate['id']) ? sanitize_key((string) $candidate['id']) : '';
			if ($id === '') {
				continue;
			}

			$section = isset($candidate['section']) ? (string) $candidate['section'] : '';
			$entity = isset($candidate['entity']) ? (string) $candidate['entity'] : '';
			$entry = isset($candidate['entry']) ? (string) $candidate['entry'] : '';
			$reasons = isset($candidate['reasons']) ? (string) $candidate['reasons'] : '';
			$is_reviewed = isset($reviewed_by_id[$id]);

			echo '<tr>';
			echo '<td>';
			echo '<select name="pmm_questionable[' . esc_attr($id) . '][section]">';
			foreach ($sections as $sec) {
				echo '<option value="' . esc_attr($sec) . '" ' . selected($section, $sec, false) . '>' . esc_html($sec) . '</option>';
			}
			echo '</select>';
			echo '</td>';
			echo '<td><input type="text" class="regular-text" name="pmm_questionable[' . esc_attr($id) . '][entity]" value="' . esc_attr($entity) . '" placeholder="' . esc_attr__('(Section-level)', 'perchance-memory-manager') . '"></td>';
			echo '<td style="min-width:360px;"><textarea name="pmm_questionable[' . esc_attr($id) . '][entry]" rows="2" class="large-text code" style="min-width:320px;">' . esc_textarea($entry) . '</textarea></td>';
			echo '<td>' . esc_html($reasons) . '</td>';
			echo '<td>' . esc_html($is_reviewed ? __('Reviewed', 'perchance-memory-manager') : __('Pending', 'perchance-memory-manager')) . '</td>';
			echo '<td>';
			echo '<input type="hidden" name="pmm_questionable[' . esc_attr($id) . '][original_section]" value="' . esc_attr($section) . '">';
			echo '<input type="hidden" name="pmm_questionable[' . esc_attr($id) . '][original_entity]" value="' . esc_attr($entity) . '">';
			echo '<input type="hidden" name="pmm_questionable[' . esc_attr($id) . '][original_entry]" value="' . esc_attr($entry) . '">';
			echo '<select class="pmm-row-action" name="pmm_questionable[' . esc_attr($id) . '][action]">';
			echo '<option value="keep" selected>' . esc_html__('Keep', 'perchance-memory-manager') . '</option>';
			echo '<option value="update">' . esc_html__('Update entry now (apply edits above)', 'perchance-memory-manager') . '</option>';
			echo '<option value="hide">' . esc_html__('Keep and hide (do not ask again)', 'perchance-memory-manager') . '</option>';
			echo '<option value="remove">' . esc_html__('Remove on next reprocess', 'perchance-memory-manager') . '</option>';
			echo '</select>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p style="margin-top:8px;">';
		echo '<label><input type="checkbox" name="pmm_questionable_apply_now" value="1" checked> ' . esc_html__('After saving questionable decisions, immediately reprocess current result when output rules changed', 'perchance-memory-manager') . '</label>';
		echo '</p>';
		submit_button(__('Save Questionable Entry Decisions', 'perchance-memory-manager'), 'secondary', 'submit', false, ['style' => 'margin-top:10px;']);
		echo '</form>';
		echo '<script>(function(){var form=document.getElementById("pmm-questionable-review-form");if(!form){return;}var bulkSelect=form.querySelector(".pmm-bulk-action");var bulkButton=form.querySelector(".pmm-apply-bulk-action");var rowActions=form.querySelectorAll("select.pmm-row-action");if(!bulkSelect||!bulkButton){return;}bulkButton.addEventListener("click",function(event){var value=bulkSelect.value;var i=0;if(!value){event.preventDefault();alert("' . esc_js(__('Choose a bulk action first.', 'perchance-memory-manager')) . '");return;}if(!confirm("' . esc_js(__('Apply this questionable-entry action to all rows in the table?', 'perchance-memory-manager')) . '")){event.preventDefault();return;}for(i=0;i<rowActions.length;i++){if(rowActions[i]){rowActions[i].value=value;}}});})();</script>';
	}

	private function render_reclassification_review($candidates, $total_found = null, $queue = [], $queue_filter = 'pending') {
		if (empty($candidates) || !is_array($candidates)) {
			echo '<p>' . esc_html__('No section reclassification suggestions currently detected.', 'perchance-memory-manager') . '</p>';
			return;
		}

		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation', 'World Building', 'Relationships', 'NSFW', 'Notes'];
		$section_level = ['Notes', 'Relationships', 'NSFW', 'World Building', 'Technology / Systems', 'Vehicles / Transportation'];
		$dataset_stamp = (int) get_option('pmm_latest_version_saved_at', 0);
		$reviewed_by_id = [];
		foreach ((array) $queue as $id => $row) {
			$id = sanitize_key((string) $id);
			if ($id === '' || !is_array($row)) {
				continue;
			}

			$status = isset($row['status']) ? sanitize_key((string) $row['status']) : '';
			$stamp = isset($row['stamp']) ? (int) $row['stamp'] : 0;
			if ($status !== 'reviewed') {
				continue;
			}
			if ($dataset_stamp > 0 && $stamp > 0 && $stamp !== $dataset_stamp) {
				continue;
			}
			$reviewed_by_id[$id] = true;
		}

		$pending_candidates = [];
		$reviewed_candidates = [];
		foreach ($candidates as $candidate) {
			$id = isset($candidate['id']) ? sanitize_key((string) $candidate['id']) : '';
			if ($id === '') {
				continue;
			}

			if (isset($reviewed_by_id[$id])) {
				$reviewed_candidates[] = $candidate;
			} else {
				$pending_candidates[] = $candidate;
			}
		}

		if (!in_array($queue_filter, ['pending', 'reviewed', 'all'], true)) {
			$queue_filter = 'pending';
		}

		$display_candidates = $pending_candidates;
		if ($queue_filter === 'reviewed') {
			$display_candidates = $reviewed_candidates;
		} elseif ($queue_filter === 'all') {
			$display_candidates = array_values($candidates);
		}

		$shown_count = count($candidates);
		$resolved_total = is_numeric($total_found) ? max(0, (int) $total_found) : $shown_count;
		if ($resolved_total > $shown_count) {
			echo '<p>' . esc_html(sprintf(__('Reclassification suggestions: %1$d found, showing first %2$d. Review before moving anything.', 'perchance-memory-manager'), $resolved_total, $shown_count)) . '</p>';
		} else {
			echo '<p>' . esc_html(sprintf(__('Reclassification suggestions: %d found. Review before moving anything.', 'perchance-memory-manager'), $shown_count)) . '</p>';
		}
		echo '<p class="description">' . esc_html(sprintf(__('Queue view: pending %1$d, reviewed %2$d, total %3$d.', 'perchance-memory-manager'), count($pending_candidates), count($reviewed_candidates), count($candidates))) . '</p>';
		echo '<p style="margin:8px 0;">';
		echo '<strong>' . esc_html__('Show:', 'perchance-memory-manager') . '</strong> ';
		echo '<a class="button ' . ($queue_filter === 'pending' ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['pmm_reclassification_queue_filter' => 'pending'])) . '">' . esc_html__('Pending', 'perchance-memory-manager') . '</a> ';
		echo '<a class="button ' . ($queue_filter === 'reviewed' ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['pmm_reclassification_queue_filter' => 'reviewed'])) . '">' . esc_html__('Reviewed', 'perchance-memory-manager') . '</a> ';
		echo '<a class="button ' . ($queue_filter === 'all' ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['pmm_reclassification_queue_filter' => 'all'])) . '">' . esc_html__('All', 'perchance-memory-manager') . '</a>';
		echo '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:8px 0 10px 0;">';
		wp_nonce_field('pmm_reset_reclassification_review_queue');
		echo '<input type="hidden" name="action" value="pmm_reset_reclassification_review_queue">';
		echo '<button type="submit" class="button">' . esc_html__('Reset Reclassification Review Queue State', 'perchance-memory-manager') . '</button>';
		echo '</form>';
		echo '<p class="description">' . esc_html__('This scans the latest processed lore for entries that look better suited to a different section. Move applies immediately to the latest output; Hide suppresses the exact suggestion next time.', 'perchance-memory-manager') . '</p>';
		if (empty($display_candidates)) {
			echo '<p>' . esc_html__('No entries in this queue view.', 'perchance-memory-manager') . '</p>';
			return;
		}
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="pmm-reclassification-review-form" class="pmm-review-form">';
		wp_nonce_field('pmm_apply_reclassification_review');
		echo '<input type="hidden" name="action" value="pmm_apply_reclassification_review">';
		echo '<input type="hidden" name="pmm_review_dataset_stamp" value="' . esc_attr((string) $dataset_stamp) . '">';
		echo '<input type="hidden" name="pmm_reclassification_queue_filter" value="' . esc_attr($queue_filter) . '">';
		echo '<input type="hidden" name="pmm_reclassification_expected_count" value="' . esc_attr((string) count($display_candidates)) . '">';
		echo '<table class="widefat striped" style="margin-top:8px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Current Section', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Current Entity', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entry', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Suggested Target', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Why', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Touched', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Action', 'perchance-memory-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($display_candidates as $candidate) {
			$id = isset($candidate['id']) ? sanitize_key((string) $candidate['id']) : '';
			if ($id === '') {
				continue;
			}

			$original_section = isset($candidate['original_section']) ? (string) $candidate['original_section'] : '';
			$original_entity = isset($candidate['original_entity']) ? (string) $candidate['original_entity'] : '';
			$original_entry = isset($candidate['original_entry']) ? (string) $candidate['original_entry'] : '';
			$target_section = isset($candidate['target_section']) ? (string) $candidate['target_section'] : $original_section;
			$target_entity = isset($candidate['target_entity']) ? (string) $candidate['target_entity'] : $original_entity;
			$reason = isset($candidate['reason']) ? (string) $candidate['reason'] : '';
			$confidence = isset($candidate['confidence']) ? (int) $candidate['confidence'] : 0;
			$is_reviewed = isset($reviewed_by_id[$id]);

			echo '<tr>';
			echo '<td>' . esc_html($original_section) . '</td>';
			echo '<td>' . ($original_entity !== '' ? esc_html($original_entity) : '<em>' . esc_html__('Section-level', 'perchance-memory-manager') . '</em>') . '</td>';
			echo '<td style="min-width:360px;white-space:pre-wrap;">' . esc_html($original_entry) . '</td>';
			echo '<td>';
			echo '<input type="hidden" name="pmm_reclassification[' . esc_attr($id) . '][original_section]" value="' . esc_attr($original_section) . '">';
			echo '<input type="hidden" name="pmm_reclassification[' . esc_attr($id) . '][original_entity]" value="' . esc_attr($original_entity) . '">';
			echo '<input type="hidden" name="pmm_reclassification[' . esc_attr($id) . '][original_entry]" value="' . esc_attr($original_entry) . '">';
			echo '<select name="pmm_reclassification[' . esc_attr($id) . '][target_section]" class="pmm-reclass-section">';
			foreach ($sections as $sec) {
				echo '<option value="' . esc_attr($sec) . '" ' . selected($target_section, $sec, false) . '>' . esc_html($sec) . '</option>';
			}
			echo '</select>';
			echo '<br><input type="text" class="regular-text pmm-reclass-entity" name="pmm_reclassification[' . esc_attr($id) . '][target_entity]" value="' . esc_attr($target_entity) . '" placeholder="' . esc_attr__('(Section-level)', 'perchance-memory-manager') . '" style="margin-top:6px;">';
			echo '</td>';
			echo '<td>' . esc_html($reason) . '<br><span class="description">' . esc_html(sprintf(__('Confidence %d%%', 'perchance-memory-manager'), $confidence)) . '</span></td>';
			echo '<td>' . esc_html($is_reviewed ? __('Reviewed', 'perchance-memory-manager') : __('Pending', 'perchance-memory-manager')) . '</td>';
			echo '<td><select name="pmm_reclassification[' . esc_attr($id) . '][action]"><option value="keep" selected>' . esc_html__('Keep', 'perchance-memory-manager') . '</option><option value="move">' . esc_html__('Move now', 'perchance-memory-manager') . '</option><option value="hide">' . esc_html__('Hide suggestion', 'perchance-memory-manager') . '</option></select></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		submit_button(__('Save Reclassification Decisions', 'perchance-memory-manager'), 'secondary', 'submit', false, ['style' => 'margin-top:10px;']);
		echo '</form>';
		echo '<script>(function(){var levelSections=' . wp_json_encode($section_level) . ';document.querySelectorAll(".pmm-reclass-section").forEach(function(select){function sync(){var entity=select.parentNode.querySelector(".pmm-reclass-entity");if(!entity){return;}var isSectionLevel=levelSections.indexOf(select.value)!==-1;entity.disabled=isSectionLevel; if(isSectionLevel){entity.value="";}}select.addEventListener("change",sync);sync();});})();</script>';
	}

	private function render_entity_review($entity_groups, $queue = [], $queue_filter = 'pending') {
		$hidden = get_option('pmm_entity_review_hidden', []);
		if (!is_array($hidden)) {
			$hidden = [];
		}

		$normalized_hidden = [];
		foreach ($hidden as $key => $row) {
			if (is_array($row)) {
				$section = isset($row['section']) ? trim((string) $row['section']) : '';
				$name = isset($row['name']) ? trim((string) $row['name']) : '';
				if ($section !== '' && $name !== '') {
					$normalized_hidden[$this->entity_rule_key($section, $name)] = true;
				}
				continue;
			}

			if (is_string($key) && $key !== '') {
				$normalized_hidden[$key] = true;
			}
		}

		$rows = [];
		foreach ((array) $entity_groups as $section => $names) {
			foreach ((array) $names as $name) {
				$section = trim((string) $section);
				$name = trim((string) $name);
				if ($section === '' || $name === '') {
					continue;
				}

				$key = $this->entity_rule_key($section, $name);
				if (isset($normalized_hidden[$key])) {
					continue;
				}

				$rows[] = [
					'section' => $section,
					'name' => $name,
					'key' => $key,
				];
			}
		}

		$dataset_stamp = (int) get_option('pmm_latest_version_saved_at', 0);
		$reviewed_by_id = [];
		foreach ((array) $queue as $id => $row) {
			$id = sanitize_key((string) $id);
			if ($id === '' || !is_array($row)) {
				continue;
			}

			$status = isset($row['status']) ? sanitize_key((string) $row['status']) : '';
			$stamp = isset($row['stamp']) ? (int) $row['stamp'] : 0;
			if ($status !== 'reviewed') {
				continue;
			}
			if ($dataset_stamp > 0 && $stamp > 0 && $stamp !== $dataset_stamp) {
				continue;
			}
			$reviewed_by_id[$id] = true;
		}

		$pending_rows = [];
		$reviewed_rows = [];
		foreach ($rows as $row) {
			$row_id = md5((string) $row['key']);
			if (isset($reviewed_by_id[$row_id])) {
				$reviewed_rows[] = $row;
			} else {
				$pending_rows[] = $row;
			}
		}

		if (!in_array($queue_filter, ['pending', 'reviewed', 'all'], true)) {
			$queue_filter = 'pending';
		}

		$display_rows = $pending_rows;
		if ($queue_filter === 'reviewed') {
			$display_rows = $reviewed_rows;
		} elseif ($queue_filter === 'all') {
			$display_rows = array_values($rows);
		}

		echo '<p class="description">' . esc_html(sprintf(__('Queue view: pending %1$d, reviewed %2$d, total %3$d.', 'perchance-memory-manager'), count($pending_rows), count($reviewed_rows), count($rows))) . '</p>';
		echo '<p style="margin:8px 0;">';
		echo '<strong>' . esc_html__('Show:', 'perchance-memory-manager') . '</strong> ';
		echo '<a class="button ' . ($queue_filter === 'pending' ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['pmm_entity_queue_filter' => 'pending', 'pmm_entity_page' => false])) . '">' . esc_html__('Pending', 'perchance-memory-manager') . '</a> ';
		echo '<a class="button ' . ($queue_filter === 'reviewed' ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['pmm_entity_queue_filter' => 'reviewed', 'pmm_entity_page' => false])) . '">' . esc_html__('Reviewed', 'perchance-memory-manager') . '</a> ';
		echo '<a class="button ' . ($queue_filter === 'all' ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['pmm_entity_queue_filter' => 'all', 'pmm_entity_page' => false])) . '">' . esc_html__('All', 'perchance-memory-manager') . '</a>';
		echo '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:8px 0 10px 0;">';
		wp_nonce_field('pmm_reset_entity_review_queue');
		echo '<input type="hidden" name="action" value="pmm_reset_entity_review_queue">';
		echo '<button type="submit" class="button">' . esc_html__('Reset Entity Review Queue State', 'perchance-memory-manager') . '</button>';
		echo '</form>';

		if (empty($display_rows)) {
			echo '<p>' . esc_html__('No entities in this queue view.', 'perchance-memory-manager') . '</p>';
			return;
		}

		$total_rows = count($display_rows);
		$rows_per_page = max(50, min(250, (int) apply_filters('pmm_entity_review_rows_per_page', 120)));
		$page = isset($_GET['pmm_entity_page']) ? max(1, (int) $_GET['pmm_entity_page']) : 1;
		$total_pages = max(1, (int) ceil($total_rows / $rows_per_page));
		if ($page > $total_pages) {
			$page = $total_pages;
		}

		$offset = ($page - 1) * $rows_per_page;
		$rows_page = array_slice($display_rows, $offset, $rows_per_page);
		$shown_from = $offset + 1;
		$shown_to = $offset + count($rows_page);

		echo '<p class="description">' . esc_html__('Keep leaves the entity untouched. Keep and hide excludes it from future entity review prompts. Remove deletes the entity and entries that mention it when you reprocess.', 'perchance-memory-manager') . '</p>';
		echo '<p class="description" style="margin-top:6px;">' . esc_html(sprintf(__('Showing entities %1$d-%2$d of %3$d. Save one page at a time to avoid PHP input truncation on large datasets.', 'perchance-memory-manager'), $shown_from, $shown_to, $total_rows)) . '</p>';
		if ($total_pages > 1) {
			echo '<p style="margin:8px 0;">';
			if ($page > 1) {
				echo '<a class="button" href="' . esc_url(add_query_arg(['pmm_entity_page' => $page - 1, 'pmm_entity_queue_filter' => $queue_filter])) . '">' . esc_html__('Previous Page', 'perchance-memory-manager') . '</a> ';
			}
			echo '<span class="description" style="margin:0 8px;">' . esc_html(sprintf(__('Page %1$d of %2$d', 'perchance-memory-manager'), $page, $total_pages)) . '</span>';
			if ($page < $total_pages) {
				echo ' <a class="button" href="' . esc_url(add_query_arg(['pmm_entity_page' => $page + 1, 'pmm_entity_queue_filter' => $queue_filter])) . '">' . esc_html__('Next Page', 'perchance-memory-manager') . '</a>';
			}
			echo '</p>';
		}
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="pmm-entity-review-form" class="pmm-review-form">';
		wp_nonce_field('pmm_apply_entity_review');
		echo '<input type="hidden" name="action" value="pmm_apply_entity_review">';
		echo '<input type="hidden" name="pmm_review_dataset_stamp" value="' . esc_attr((string) $dataset_stamp) . '">';
		echo '<input type="hidden" name="pmm_entity_queue_filter" value="' . esc_attr($queue_filter) . '">';
		echo '<input type="hidden" name="pmm_entity_expected_count" value="' . esc_attr((string) count($rows_page)) . '">';
		echo '<table class="widefat striped" style="margin-top:8px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Section', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entity', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Touched', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Action', 'perchance-memory-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($rows_page as $row) {
			$id = md5((string) $row['key']);
			$is_reviewed = isset($reviewed_by_id[$id]);
			echo '<tr>';
			echo '<td>' . esc_html($row['section']) . '</td>';
			echo '<td>' . esc_html($row['name']) . '</td>';
			echo '<td>' . esc_html($is_reviewed ? __('Reviewed', 'perchance-memory-manager') : __('Pending', 'perchance-memory-manager')) . '</td>';
			echo '<td>';
			echo '<input type="hidden" name="pmm_entities[' . esc_attr($id) . '][section]" value="' . esc_attr($row['section']) . '">';
			echo '<input type="hidden" name="pmm_entities[' . esc_attr($id) . '][name]" value="' . esc_attr($row['name']) . '">';
			echo '<select name="pmm_entities[' . esc_attr($id) . '][action]">';
			echo '<option value="keep" selected>' . esc_html__('Keep', 'perchance-memory-manager') . '</option>';
			echo '<option value="hide">' . esc_html__('Keep and hide (do not ask again)', 'perchance-memory-manager') . '</option>';
			echo '<option value="remove">' . esc_html__('Remove entity and related entries', 'perchance-memory-manager') . '</option>';
			echo '</select>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p style="margin-top:8px;">';
		echo '<label><input type="checkbox" name="pmm_entity_apply_now" value="1" checked> ' . esc_html__('After saving entity review decisions, immediately reprocess current result', 'perchance-memory-manager') . '</label>';
		echo '</p>';
		submit_button(__('Save Entity Review Decisions', 'perchance-memory-manager'), 'secondary', 'submit', false, ['style' => 'margin-top:10px;']);
		echo '</form>';
	}

	private function render_hidden_entities_manager() {
		$hidden = get_option('pmm_entity_review_hidden', []);
		if (!is_array($hidden)) {
			$hidden = [];
		}

		$rows = [];
		foreach ($hidden as $key => $row) {
			if (is_array($row)) {
				$section = isset($row['section']) ? trim((string) $row['section']) : '';
				$name = isset($row['name']) ? trim((string) $row['name']) : '';
			} else {
				$section = '';
				$name = '';
			}

			if ($section === '' || $name === '') {
				continue;
			}

			$rule_key = $this->entity_rule_key($section, $name);
			$rows[] = [
				'key' => $rule_key,
				'section' => $section,
				'name' => $name,
			];
		}

		echo '<details style="margin-top:10px;">';
		echo '<summary><strong>' . esc_html__('Manage Hidden Entries', 'perchance-memory-manager') . '</strong></summary>';

		if (empty($rows)) {
			echo '<p style="margin-top:8px;">' . esc_html__('No hidden entities right now.', 'perchance-memory-manager') . '</p>';
			echo '</details>';
			return;
		}

		echo '<p class="description" style="margin-top:8px;">' . esc_html__('These entities were marked as keep + hide. Select any to show again in Entity Review.', 'perchance-memory-manager') . '</p>';
		echo '<p style="margin-top:8px;"><input type="search" id="pmm-hidden-filter" class="regular-text" placeholder="' . esc_attr__('Filter hidden entities...', 'perchance-memory-manager') . '"></p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('pmm_manage_hidden_entities');
		echo '<input type="hidden" name="action" value="pmm_manage_hidden_entities">';
		echo '<table id="pmm-hidden-table" class="widefat striped" style="margin-top:8px;">';
		echo '<thead><tr>';
		echo '<th style="width:40px;">' . esc_html__('Pick', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Section', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entity', 'perchance-memory-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($rows as $row) {
			$filter_text = mb_strtolower($row['section'] . ' ' . $row['name']);
			echo '<tr data-filter-text="' . esc_attr($filter_text) . '">';
			echo '<td><input type="checkbox" name="pmm_hidden_keys[]" value="' . esc_attr($row['key']) . '"></td>';
			echo '<td>' . esc_html($row['section']) . '</td>';
			echo '<td>' . esc_html($row['name']) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p style="margin-top:8px;">';
		echo '<button type="submit" class="button button-secondary" name="pmm_hidden_action" value="selected">' . esc_html__('Unhide Selected', 'perchance-memory-manager') . '</button> ';
		echo '<button type="submit" class="button" name="pmm_hidden_action" value="all">' . esc_html__('Unhide All', 'perchance-memory-manager') . '</button>';
		echo '</p>';
		echo '</form>';
		echo '<script>';
		echo 'document.addEventListener("DOMContentLoaded", function(){';
		echo 'var input=document.getElementById("pmm-hidden-filter");';
		echo 'var table=document.getElementById("pmm-hidden-table");';
		echo 'if(!input||!table){return;}';
		echo 'input.addEventListener("input", function(){';
		echo 'var q=(input.value||"").toLowerCase().trim();';
		echo 'var rows=table.querySelectorAll("tbody tr");';
		echo 'rows.forEach(function(row){';
		echo 'var text=row.getAttribute("data-filter-text")||"";';
		echo 'row.style.display=(q===""||text.indexOf(q)!==-1)?"":"none";';
		echo '});';
		echo '});';
		echo '});';
		echo '</script>';
		echo '</details>';
	}

	private function render_alias_rules_manager() {
		$rules = get_option('pmm_alias_rules', []);
		if (!is_array($rules)) {
			$rules = [];
		}
		$first_name_exclusions = get_option('pmm_first_name_alias_exclusions', []);
		if (!is_array($first_name_exclusions)) {
			$first_name_exclusions = [];
		}

		$rules_text = $this->serialize_alias_rules($rules);
		$first_name_exclusions_text = $this->serialize_simple_lines($first_name_exclusions);

		echo '<p class="description" style="margin-top:0;">' . esc_html__('Use aliases when nickname/short-name variants should resolve to the same character, location, or organization without deleting either wording. Example: Max => Black Max.', 'perchance-memory-manager') . '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('pmm_save_alias_rules');
		echo '<input type="hidden" name="action" value="pmm_save_alias_rules">';
		echo '<textarea name="pmm_alias_rules_text" rows="8" class="large-text code" placeholder="Alias Name => Canonical Name">' . esc_textarea($rules_text) . '</textarea>';
		echo '<p class="description" style="margin-top:8px;">' . esc_html__('One mapping per line. Supported separators: =>, =, or tab. Save and reprocess to apply across output and similarity checks.', 'perchance-memory-manager') . '</p>';
		echo '<p style="margin-top:10px;margin-bottom:4px;"><strong>' . esc_html__('Exclude Words From Auto First-Name Matching', 'perchance-memory-manager') . '</strong></p>';
		echo '<textarea name="pmm_first_name_alias_exclusions_text" rows="5" class="large-text code" placeholder="Black">' . esc_textarea($first_name_exclusions_text) . '</textarea>';
		echo '<p class="description" style="margin-top:8px;">' . esc_html__('One word or phrase per line. These entries are ignored by automatic first-name alias mapping, but manual alias rules above still apply.', 'perchance-memory-manager') . '</p>';
		submit_button(__('Save Alias Rules', 'perchance-memory-manager'), 'secondary', 'submit', false, ['style' => 'margin-top:8px;']);
		echo '</form>';
	}

	private function render_rules_summary() {
		$hidden = get_option('pmm_entity_review_hidden', []);
		$removals = get_option('pmm_entity_removal_rules', []);
		$entry_removals = get_option('pmm_entry_removal_rules', []);
		$questionable_hidden = get_option('pmm_questionable_hidden_entries', []);
		$ignored_similarity = get_option('pmm_similarity_ignored_pairs', []);
		$dirty = get_option('pmm_output_rules_dirty', '0') === '1';

		$hidden_count = is_array($hidden) ? count($hidden) : 0;
		$removal_count = is_array($removals) ? count($removals) : 0;
		$entry_removal_count = is_array($entry_removals) ? count($entry_removals) : 0;
		$questionable_hidden_count = is_array($questionable_hidden) ? count($questionable_hidden) : 0;
		$ignored_count = is_array($ignored_similarity) ? count($ignored_similarity) : 0;

		echo '<ul class="pmm-stats">';
		echo '<li><strong>' . esc_html__('Entity removals:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) $removal_count) . '</li>';
		echo '<li><strong>' . esc_html__('Questionable entry removals:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) $entry_removal_count) . '</li>';
		echo '<li><strong>' . esc_html__('Questionable entries hidden from review:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) $questionable_hidden_count) . '</li>';
		echo '<li><strong>' . esc_html__('Hidden from review:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) $hidden_count) . '</li>';
		echo '<li><strong>' . esc_html__('Ignored similarity pairs:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) $ignored_count) . '</li>';
		echo '<li><strong>' . esc_html__('Output needs reprocess:', 'perchance-memory-manager') . '</strong> ' . esc_html($dirty ? __('Yes', 'perchance-memory-manager') : __('No', 'perchance-memory-manager')) . '</li>';
		echo '</ul>';
		echo '<p class="description">' . esc_html__('Precedence: remove rules affect output on reprocess, hide rules only affect review visibility.', 'perchance-memory-manager') . '</p>';
	}

	private function serialize_staged_raw_import_rows($rows) {
		$lines = [];
		foreach ((array) $rows as $row) {
			$section = isset($row['section']) ? trim((string) $row['section']) : 'Notes';
			$entity = isset($row['entity']) ? trim((string) $row['entity']) : '';
			$bullet = isset($row['bullet']) ? trim((string) $row['bullet']) : '';
			if ($bullet === '') {
				continue;
			}
			$lines[] = $section . "\t" . $entity . "\t" . str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $bullet);
		}

		return implode("\n", $lines);
	}

	private function entity_entries_text($cleaned_data, $section, $entity) {
		$section = trim((string) $section);
		$entity = trim((string) $entity);
		if (!isset($cleaned_data[$section]) || !is_array($cleaned_data[$section])) {
			return '';
		}

		if (in_array($section, ['Notes', 'Relationships', 'NSFW', 'World Building', 'Technology / Systems', 'Vehicles / Transportation'], true) && $entity === '') {
			$items = $this->section_level_items_from_bucket($cleaned_data[$section]);
			return implode("\n", array_map('strval', $items));
		}

		if ($entity !== '' && isset($cleaned_data[$section][$entity]) && is_array($cleaned_data[$section][$entity])) {
			return implode("\n", array_map('strval', $cleaned_data[$section][$entity]));
		}

		return '';
	}

	private function entity_rendered_preview_text($cleaned_data, $section, $entity) {
		$section = trim((string) $section);
		$entity = trim((string) $entity);
		if (!isset($cleaned_data[$section]) || !is_array($cleaned_data[$section])) {
			return '';
		}

		$lines = ['# ' . $section];

		if (in_array($section, ['Notes', 'Relationships', 'NSFW', 'World Building', 'Technology / Systems', 'Vehicles / Transportation'], true) && $entity === '') {
			$items = $this->section_level_items_from_bucket($cleaned_data[$section]);
			foreach ($items as $item) {
				$lines[] = '- ' . (string) $item;
				$lines[] = '';
			}

			return implode("\n", $lines);
		}

		if ($entity !== '' && isset($cleaned_data[$section][$entity]) && is_array($cleaned_data[$section][$entity])) {
			$lines[] = $entity;
			foreach ($cleaned_data[$section][$entity] as $item) {
				$lines[] = '- ' . (string) $item;
				$lines[] = '';
			}
			return implode("\n", $lines);
		}

		return '';
	}

	private function section_level_items_from_bucket($section_bucket) {
		if (!is_array($section_bucket)) {
			return [];
		}

		$items = [];
		$reserved = ['__entries__', '__unassigned__'];

		foreach ($reserved as $key) {
			if (!isset($section_bucket[$key]) || !is_array($section_bucket[$key])) {
				continue;
			}
			foreach ($section_bucket[$key] as $item) {
				$items[] = (string) $item;
			}
		}

		foreach ($section_bucket as $key => $value) {
			if (in_array((string) $key, $reserved, true) || strpos((string) $key, '__') === 0 || !is_array($value)) {
				continue;
			}
			foreach ($value as $item) {
				$items[] = (string) $item;
			}
		}

		return $items;
	}

	private function build_fallback_entity_report($cleaned_data) {
		$sections = ['Characters', 'Organizations', 'Locations'];
		$entities = [];

		foreach ($sections as $section) {
			$entities[$section] = [];
			if (empty($cleaned_data[$section]) || !is_array($cleaned_data[$section])) {
				continue;
			}

			foreach ((array) $cleaned_data[$section] as $name => $items) {
				if (strpos((string) $name, '__') === 0) {
					continue;
				}

				$name = trim((string) $name);
				if ($name !== '') {
					$entities[$section][] = $name;
				}
			}

			sort($entities[$section], SORT_NATURAL | SORT_FLAG_CASE);
		}

		return [
			'entities' => $entities,
			'new_entities' => [],
			'similar_candidates' => [],
			'questionable_entries' => [],
		];
	}

	private function build_entry_conversion_rows($cleaned_data, $filters = []) {
		$section_filter = isset($filters['section']) ? trim((string) $filters['section']) : 'all';
		$entity_filter = isset($filters['entity']) ? trim((string) $filters['entity']) : '';
		$search_filter = isset($filters['search']) ? trim((string) $filters['search']) : '';
		$include_mentions = !empty($filters['include_mentions']);
		$search_filter_fp = PMM_Utils::fingerprint($search_filter);

		$rows = [];
		$index = 0;

		foreach ((array) $cleaned_data as $section => $content) {
			$section = trim((string) $section);
			if ($section === '' || !is_array($content)) {
				continue;
			}

			if ($section_filter !== 'all' && $section !== $section_filter) {
				continue;
			}

			foreach ((array) $content as $entity => $items) {
				if (!is_array($items)) {
					continue;
				}

				$entity_name_current = (strpos((string) $entity, '__') === 0) ? '' : trim((string) $entity);

				foreach ((array) $items as $entry) {
					$entry = PMM_Utils::normalize_bullet((string) $entry);
					if ($entry === '') {
						continue;
					}

					if ($entity_filter !== '') {
						$entity_match = ($entity_name_current === $entity_filter);
						$mention_match = $include_mentions ? (PMM_Utils::contains_name_score($entry, $entity_filter) >= 0.90) : false;
						if (!$entity_match && !$mention_match) {
							continue;
						}
					}

					if ($search_filter !== '') {
						$entry_fp = PMM_Utils::fingerprint($entry);
						if ($search_filter_fp !== '' && strpos($entry_fp, $search_filter_fp) === false) {
							continue;
						}
					}

					$rows[] = [
						'id' => md5($section . '|' . $entity_name_current . '|' . $entry . '|' . $index),
						'source_section' => $section,
						'source_entity' => $entity_name_current,
						'entry' => $entry,
					];
					++$index;
				}
			}
		}

		usort($rows, static function($a, $b) {
			$left = ($a['source_section'] ?? '') . '|' . ($a['source_entity'] ?? '') . '|' . ($a['entry'] ?? '');
			$right = ($b['source_section'] ?? '') . '|' . ($b['source_entity'] ?? '') . '|' . ($b['entry'] ?? '');
			return strcasecmp($left, $right);
		});

		return $rows;
	}

	private function entity_options_for_section($cleaned_data, $section) {
		$section = trim((string) $section);
		if (!isset($cleaned_data[$section]) || !is_array($cleaned_data[$section])) {
			return [];
		}

		$names = [];
		foreach ($cleaned_data[$section] as $name => $items) {
			if (strpos((string) $name, '__') === 0) {
				continue;
			}
			$name = trim((string) $name);
			if ($name !== '') {
				$names[] = $name;
			}
		}

		$names = array_values(array_unique($names));
		sort($names, SORT_NATURAL | SORT_FLAG_CASE);
		return $names;
	}

	private function render_raw_import_preview_table($rows, $known_entities_by_section = []) {
		$valid_sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation', 'World Building', 'Relationships', 'NSFW', 'Notes'];
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__('Review', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Touched', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Source', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Confidence', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Signal', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Section', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entity Mapping', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entry Text', 'perchance-memory-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ((array) $rows as $index => $row) {
			$source = isset($row['source']) ? (string) $row['source'] : '';
			$section = isset($row['section']) ? (string) $row['section'] : 'Notes';
			$entity = isset($row['entity']) ? (string) $row['entity'] : '';
			$bullet = isset($row['bullet']) ? (string) $row['bullet'] : '';
			$confidence = isset($row['confidence']) ? max(1, min(99, (int) $row['confidence'])) : 50;
			$signal = isset($row['reason']) ? (string) $row['reason'] : '';
			$reviewed = !empty($row['reviewed']) ? 1 : 0;
			if (!in_array($section, $valid_sections, true)) {
				$section = 'Notes';
			}

			echo '<tr data-pmm-raw-row="1" data-pmm-raw-index="' . esc_attr((string) $index) . '">';
			echo '<td style="white-space:nowrap;">';
			echo '<input type="hidden" class="pmm-raw-removed-flag" name="pmm_raw_table[' . esc_attr((string) $index) . '][removed]" value="0">';
			echo '<button type="button" class="button-link pmm-raw-remove-row">' . esc_html__('Remove', 'perchance-memory-manager') . '</button>';
			echo '</td>';
			echo '<td style="white-space:nowrap;">';
			echo '<input type="hidden" name="pmm_raw_table[' . esc_attr((string) $index) . '][reviewed]" value="0">';
			echo '<label><input type="checkbox" name="pmm_raw_table[' . esc_attr((string) $index) . '][reviewed]" value="1" ' . checked($reviewed, 1, false) . '> ' . esc_html__('Reviewed', 'perchance-memory-manager') . '</label>';
			echo '</td>';
			echo '<td style="max-width:260px;white-space:pre-wrap;">' . esc_html($source) . '</td>';
			echo '<td><strong>' . esc_html((string) $confidence) . '%</strong></td>';
			echo '<td style="max-width:200px;white-space:pre-wrap;">' . esc_html($signal) . '</td>';
			echo '<td><select class="pmm-raw-section" name="pmm_raw_table[' . esc_attr((string) $index) . '][section]">';
			foreach ($valid_sections as $sec) {
				echo '<option value="' . esc_attr($sec) . '" ' . selected($section, $sec, false) . '>' . esc_html($sec) . '</option>';
			}
			echo '</select></td>';
			echo '<td>';
			echo '<select class="pmm-raw-known-entity" style="max-width:220px;">';
			echo '<option value="">' . esc_html__('Known entity...', 'perchance-memory-manager') . '</option>';
			$known = isset($known_entities_by_section[$section]) && is_array($known_entities_by_section[$section]) ? $known_entities_by_section[$section] : [];
			foreach ($known as $known_name) {
				echo '<option value="' . esc_attr((string) $known_name) . '">' . esc_html((string) $known_name) . '</option>';
			}
			echo '</select>';
			echo '<br><input type="text" class="regular-text pmm-raw-entity" name="pmm_raw_table[' . esc_attr((string) $index) . '][entity]" value="' . esc_attr($entity) . '" placeholder="' . esc_attr__('Existing or new entity name', 'perchance-memory-manager') . '" style="margin-top:6px;">';
			echo '<p class="description" style="margin:4px 0 0 0;">' . esc_html__('Pick known entity or type a new one manually.', 'perchance-memory-manager') . '</p>';
			echo '</td>';
			echo '<td><textarea name="pmm_raw_table[' . esc_attr((string) $index) . '][bullet]" rows="2" class="large-text code" style="min-width:100%;">' . esc_textarea($bullet) . '</textarea></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<script>(function(){var knownBySection=' . wp_json_encode($known_entities_by_section) . ';document.querySelectorAll("tr[data-pmm-raw-row]").forEach(function(row){var section=row.querySelector(".pmm-raw-section");var known=row.querySelector(".pmm-raw-known-entity");var entity=row.querySelector(".pmm-raw-entity");var bullet=row.querySelector("textarea[name$=\"[bullet]\"]");var removed=row.querySelector(".pmm-raw-removed-flag");var removeButton=row.querySelector(".pmm-raw-remove-row");if(section&&known&&entity){var fill=function(){var list=knownBySection[section.value]||[];known.innerHTML="";var placeholder=document.createElement("option");placeholder.value="";placeholder.textContent="' . esc_js(__('Known entity...', 'perchance-memory-manager')) . '";known.appendChild(placeholder);list.forEach(function(name){var option=document.createElement("option");option.value=name;option.textContent=name;known.appendChild(option);});};known.addEventListener("change",function(){if(known.value){entity.value=known.value;}});section.addEventListener("change",fill);fill();}if(removeButton&&removed){removeButton.addEventListener("click",function(){removed.value="1";if(bullet){bullet.value="";}row.style.display="none";});}});})();</script>';
	}

	private function known_entities_for_raw_review($confirmed_registry, $cleaned_data) {
		$out = [];
		foreach ($this->entity_sections() as $section) {
			$names = [];
			if (isset($confirmed_registry[$section]) && is_array($confirmed_registry[$section])) {
				foreach ($confirmed_registry[$section] as $row) {
					if (!is_array($row)) {
						continue;
					}
					$name = isset($row['name']) ? trim((string) $row['name']) : '';
					if ($name !== '') {
						$names[] = $name;
					}
				}
			}
			if (isset($cleaned_data[$section]) && is_array($cleaned_data[$section])) {
				foreach ($cleaned_data[$section] as $entity => $items) {
					if (strpos((string) $entity, '__') === 0) {
						continue;
					}
					$entity = trim((string) $entity);
					if ($entity !== '') {
						$names[] = $entity;
					}
				}
			}
			$names = array_values(array_unique($names));
			sort($names, SORT_NATURAL | SORT_FLAG_CASE);
			$out[$section] = $names;
		}

		return $out;
	}

	private function entity_sections() {
		return ['Characters', 'Organizations', 'Locations'];
	}

	private function get_confirmed_entity_registry_option() {
		$stored = get_option('pmm_confirmed_entities_registry', []);
		if (!is_array($stored)) {
			$stored = [];
		}

		$out = [];
		foreach ($this->entity_sections() as $section) {
			$out[$section] = [];
			$rows = isset($stored[$section]) && is_array($stored[$section]) ? $stored[$section] : [];
			foreach ($rows as $fingerprint => $row) {
				if (!is_array($row)) {
					continue;
				}
				$name = isset($row['name']) ? trim((string) $row['name']) : '';
				$fp = PMM_Utils::name_fingerprint($name !== '' ? $name : (string) $fingerprint);
				if ($fp === '') {
					continue;
				}
				if ($name === '') {
					$name = (string) $fingerprint;
				}
				$out[$section][$fp] = [
					'name' => $name,
					'seen_count' => isset($row['seen_count']) ? max(1, (int) $row['seen_count']) : 1,
					'last_seen' => isset($row['last_seen']) ? max(0, (int) $row['last_seen']) : 0,
				];
			}
		}

		return $out;
	}

	private function render_confirmed_entity_registry_table($registry) {
		$rows = [];
		foreach ($this->entity_sections() as $section) {
			$section_rows = isset($registry[$section]) && is_array($registry[$section]) ? $registry[$section] : [];
			foreach ($section_rows as $row) {
				if (!is_array($row)) {
					continue;
				}
				$name = isset($row['name']) ? trim((string) $row['name']) : '';
				if ($name === '') {
					continue;
				}
				$rows[] = [
					'section' => $section,
					'name' => $name,
					'seen_count' => isset($row['seen_count']) ? max(1, (int) $row['seen_count']) : 1,
					'last_seen' => isset($row['last_seen']) ? max(0, (int) $row['last_seen']) : 0,
				];
			}
		}

		if (empty($rows)) {
			echo '<p class="description">' . esc_html__('No confirmed entities yet. Process at least one file to start building the registry.', 'perchance-memory-manager') . '</p>';
			return;
		}

		usort($rows, static function ($a, $b) {
			if ((int) $a['seen_count'] !== (int) $b['seen_count']) {
				return ((int) $a['seen_count'] > (int) $b['seen_count']) ? -1 : 1;
			}
			$left = (string) $a['section'] . '|' . (string) $a['name'];
			$right = (string) $b['section'] . '|' . (string) $b['name'];
			return strcasecmp($left, $right);
		});

		$total = count($rows);
		$shown = min(150, $total);
		echo '<p class="description" style="margin-top:8px;">' . esc_html(sprintf(__('Showing %1$d of %2$d confirmed entities (sorted by frequency).', 'perchance-memory-manager'), $shown, $total)) . '</p>';
		echo '<div style="overflow:auto;max-height:360px;border:1px solid #dcdcde;background:#fff;">';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__('Section', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entity', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Seen', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Last Seen', 'perchance-memory-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach (array_slice($rows, 0, $shown) as $row) {
			$when = ((int) $row['last_seen'] > 0) ? wp_date('Y-m-d H:i', (int) $row['last_seen']) : '';
			echo '<tr>';
			echo '<td>' . esc_html((string) $row['section']) . '</td>';
			echo '<td>' . esc_html((string) $row['name']) . '</td>';
			echo '<td>' . esc_html((string) ((int) $row['seen_count'])) . '</td>';
			echo '<td>' . esc_html($when) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	private function entity_rule_key($section, $name) {
		return mb_strtolower(trim((string) $section)) . '|' . PMM_Utils::name_fingerprint((string) $name);
	}

	private function serialize_alias_rules($rules) {
		if (empty($rules) || !is_array($rules)) {
			return '';
		}

		$lines = [];
		foreach ($rules as $source => $canonical) {
			$source = trim((string) $source);
			$canonical = trim((string) $canonical);
			if ($source !== '' && $canonical !== '') {
				$lines[] = $source . ' => ' . $canonical;
			}
		}

		sort($lines, SORT_NATURAL | SORT_FLAG_CASE);
		return implode("\n", $lines);
	}

	private function serialize_simple_lines($items) {
		if (empty($items) || !is_array($items)) {
			return '';
		}

		$lines = [];
		foreach ($items as $item) {
			$item = trim((string) $item);
			if ($item !== '') {
				$lines[] = $item;
			}
		}

		$lines = array_values(array_unique($lines));
		sort($lines, SORT_NATURAL | SORT_FLAG_CASE);
		return implode("\n", $lines);
	}
}