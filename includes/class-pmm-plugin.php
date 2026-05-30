<?php

if (!defined('ABSPATH')) {
	exit;
}

class PMM_Plugin {
	private $job_ttl = 2 * HOUR_IN_SECONDS;
	private $line_batch_size = 3000;
	private $dedupe_batch_items = 60;
	private $batch_time_budget_seconds = 30;
	private $version_retention = 10;
	private $similarity_max_pairs_per_run = 50000;
	private $similarity_max_entities_per_section = 500;

	public function init() {
		$this->line_batch_size = max(300, (int) apply_filters('pmm_line_batch_size', $this->line_batch_size));
		$this->dedupe_batch_items = max(10, (int) apply_filters('pmm_dedupe_batch_items', $this->dedupe_batch_items));
		$this->batch_time_budget_seconds = max(8, min(45, (int) apply_filters('pmm_batch_time_budget_seconds', $this->batch_time_budget_seconds)));
		$this->similarity_max_pairs_per_run = max(1000, (int) apply_filters('pmm_similarity_max_pairs_per_run', $this->similarity_max_pairs_per_run));
		$this->similarity_max_entities_per_section = max(50, (int) apply_filters('pmm_similarity_max_entities_per_section', $this->similarity_max_entities_per_section));

		add_action('admin_menu', [$this, 'register_admin']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
		add_action('admin_post_pmm_process_upload', [$this, 'handle_upload']);
		add_action('admin_post_pmm_process_latest_version', [$this, 'process_latest_version']);
		add_action('admin_post_pmm_process_recent_version', [$this, 'process_recent_version']);
		add_action('admin_post_pmm_process_batch', [$this, 'process_batch']);
		add_action('admin_post_pmm_apply_similarity_review', [$this, 'apply_similarity_review']);
		add_action('admin_post_pmm_apply_entity_review', [$this, 'apply_entity_review']);
		add_action('admin_post_pmm_apply_questionable_review', [$this, 'apply_questionable_review']);
		add_action('admin_post_pmm_manage_hidden_entities', [$this, 'manage_hidden_entities']);
		add_action('admin_post_pmm_preview_raw_import', [$this, 'preview_raw_import']);
		add_action('admin_post_pmm_stage_raw_import', [$this, 'stage_raw_import']);
		add_action('admin_post_pmm_download_raw_import_rows', [$this, 'download_raw_import_rows']);
		add_action('admin_post_pmm_clear_raw_import_preview', [$this, 'clear_raw_import_preview']);
		add_action('admin_post_pmm_save_entity_update', [$this, 'save_entity_update']);
		add_action('admin_post_pmm_save_entity_bulk_update', [$this, 'save_entity_bulk_update']);
		add_action('admin_post_pmm_global_search_replace', [$this, 'global_search_replace']);
		add_action('admin_post_pmm_save_alias_rules', [$this, 'save_alias_rules']);
		add_action('admin_post_pmm_reprocess_last_output', [$this, 'reprocess_last_output']);
		add_action('admin_post_pmm_download_last_output', [$this, 'download_last_output']);
		add_action('admin_post_pmm_save_preview_content', [$this, 'save_preview_content']);
		add_action('wp_ajax_pmm_get_entities_for_section', [$this, 'ajax_get_entities_for_section']);
	}

	public function ajax_get_entities_for_section() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'forbidden'], 403);
		}

		check_ajax_referer('pmm_get_entities_for_section', 'nonce');

		$section = isset($_REQUEST['section']) ? sanitize_text_field((string) wp_unslash($_REQUEST['section'])) : 'Characters';
		$valid_sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes'];
		if (!in_array($section, $valid_sections, true)) {
			$section = 'Characters';
		}

		$data = get_transient('pmm_last_output_' . get_current_user_id());
		if (empty($data['content']) || !is_array($data)) {
			wp_send_json_success([
				'section' => $section,
				'entities' => [],
				'allows_section_entries' => in_array($section, ['Notes', 'Relationships', 'NSFW'], true),
			]);
		}

		$cleaned = $this->get_cleaned_data_from_last_output($data);
		$entities = $this->extract_entity_names_for_section($cleaned, $section);

		wp_send_json_success([
			'section' => $section,
			'entities' => $entities,
			'allows_section_entries' => in_array($section, ['Notes', 'Relationships', 'NSFW'], true),
		]);
	}

	public function register_admin() {
		$admin = new PMM_Admin();

		add_menu_page(
			__('Perchance', 'perchance-memory-manager'),
			__('Perchance', 'perchance-memory-manager'),
			'manage_options',
			'perchance-memory-manager',
			[$admin, 'render_page'],
			'dashicons-media-text',
			80
		);
	}

	public function enqueue_admin_assets($hook) {
		if ($hook !== 'toplevel_page_perchance-memory-manager') {
			return;
		}

		wp_enqueue_style(
			'pmm-admin',
			PMM_PLUGIN_URL . 'assets/admin.css',
			[],
			PMM_VERSION
		);
	}

	public function handle_upload() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_process_upload');

		if (empty($_FILES['pmm_memory_file']['tmp_name'])) {
			wp_safe_redirect(add_query_arg([
				'page' => 'perchance-memory-manager',
				'pmm_error' => 'missing_file',
			], admin_url('admin.php')));
			exit;
		}

		$file = $_FILES['pmm_memory_file'];
		$mode = isset($_POST['pmm_mode']) ? sanitize_text_field(wp_unslash($_POST['pmm_mode'])) : 'balanced';
		$format = isset($_POST['pmm_format']) ? sanitize_text_field(wp_unslash($_POST['pmm_format'])) : 'md';
		$drop_sequences = isset($_POST['pmm_drop_sequences']) ? $this->sanitize_drop_sequences(wp_unslash($_POST['pmm_drop_sequences'])) : [];
		$include_entity_report = true;
		$similarity_thresholds = $this->read_similarity_thresholds_from_request($_POST);
		$questionable_settings = $this->read_questionable_settings_from_request($_POST);
		$entity_related_match_mode = isset($_POST['pmm_entity_related_match_mode']) ? sanitize_key((string) wp_unslash($_POST['pmm_entity_related_match_mode'])) : 'normal';
		if (!in_array($entity_related_match_mode, ['normal', 'strict'], true)) {
			$entity_related_match_mode = 'normal';
		}
		update_option('pmm_drop_sequences', $drop_sequences, false);
		update_option('pmm_include_entity_report', '1', false);
		update_option('pmm_similarity_thresholds', $similarity_thresholds, false);
		update_option('pmm_questionable_settings', $questionable_settings, false);
		update_option('pmm_entity_related_match_mode', $entity_related_match_mode, false);
		update_option('pmm_last_mode', $mode, false);
		update_option('pmm_last_format', $format, false);

		$allowed_ext = ['txt', 'md'];
		$filename = isset($file['name']) ? sanitize_file_name(wp_unslash($file['name'])) : 'memory.txt';
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		if (!in_array($ext, $allowed_ext, true)) {
			wp_safe_redirect(add_query_arg([
				'page' => 'perchance-memory-manager',
				'pmm_error' => 'invalid_type',
			], admin_url('admin.php')));
			exit;
		}

		$job_id = $this->generate_job_id();
		$source_path = $this->build_job_source_path($job_id, $ext);

		if (!wp_mkdir_p(dirname($source_path))) {
			wp_safe_redirect(add_query_arg([
				'page' => 'perchance-memory-manager',
				'pmm_error' => 'storage_failed',
			], admin_url('admin.php')));
			exit;
		}

		if (!@move_uploaded_file($file['tmp_name'], $source_path)) {
			wp_safe_redirect(add_query_arg([
				'page' => 'perchance-memory-manager',
				'pmm_error' => 'store_failed',
			], admin_url('admin.php')));
			exit;
		}

		$state = [
			'job_id' => $job_id,
			'user_id' => get_current_user_id(),
			'stage' => 'parsing',
			'source_path' => $source_path,
			'source_is_persistent' => false,
			'source_filename' => $filename,
			'mode' => $mode,
			'format' => $format,
			'drop_sequences' => $drop_sequences,
			'include_entity_report' => $include_entity_report,
			'entity_report' => [
				'entities' => [],
				'new_entities' => [],
			],
			'output_filename' => $this->build_output_filename($filename, $format),
			'line_offset' => 0,
			'total_lines' => $this->count_file_lines($source_path),
			'line_batch_size' => $this->line_batch_size,
			'context' => [
				'section' => 'Notes',
				'entity' => null,
			],
			'parsed' => $this->empty_data_template(),
			'staged_raw_import_rows' => $this->get_staged_raw_import_rows(),
			'dedupe_queue' => [],
			'dedupe_index' => 0,
			'cleaned' => [],
			'counters' => $this->empty_counters_template(),
		];

		$this->save_job_state($job_id, $state);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_processing' => 1,
			'pmm_job' => $job_id,
		], admin_url('admin.php')));
		exit;
	}

	public function process_latest_version() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_process_latest_version');

		$path = (string) get_option('pmm_latest_version_file_path', '');
		if ($path === '' || !file_exists($path) || !is_readable($path)) {
			$this->redirect_with_error('latest_file_missing');
		}

		$settings = $this->resolve_processing_settings_from_request();
		$mode = $settings['mode'];
		$format = $settings['format'];
		$drop_sequences = $settings['drop_sequences'];
		$include_entity_report = $settings['include_entity_report'];

		$this->start_processing_for_source_file($path, basename($path), $mode, $format, $drop_sequences, $include_entity_report, true);
	}

	public function process_recent_version() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_process_recent_version');

		$history = get_option('pmm_version_history', []);
		if (!is_array($history) || empty($history)) {
			$this->redirect_with_error('latest_file_missing');
		}

		$index = isset($_POST['pmm_version_index']) ? (int) $_POST['pmm_version_index'] : -1;
		if ($index < 0 || !isset($history[$index]) || !is_array($history[$index])) {
			$this->redirect_with_error('latest_file_missing');
		}

		$item = $history[$index];
		$path = isset($item['path']) ? (string) $item['path'] : '';
		$filename = isset($item['filename']) ? (string) $item['filename'] : basename($path);
		if ($path === '' || !file_exists($path) || !is_readable($path)) {
			$this->redirect_with_error('latest_file_missing');
		}

		$settings = $this->resolve_processing_settings_from_request();
		$mode = $settings['mode'];
		$format = $settings['format'];
		$drop_sequences = $settings['drop_sequences'];
		$include_entity_report = $settings['include_entity_report'];

		$this->start_processing_for_source_file($path, $filename, $mode, $format, $drop_sequences, $include_entity_report, true);
	}

	private function resolve_processing_settings_from_request() {
		$mode = isset($_POST['pmm_mode']) ? sanitize_text_field(wp_unslash($_POST['pmm_mode'])) : (string) get_option('pmm_last_mode', 'balanced');
		if (!in_array($mode, ['strict', 'balanced', 'aggressive'], true)) {
			$mode = 'balanced';
		}

		$format = isset($_POST['pmm_format']) ? sanitize_text_field(wp_unslash($_POST['pmm_format'])) : (string) get_option('pmm_last_format', 'md');
		if (!in_array($format, ['md', 'txt'], true)) {
			$format = 'md';
		}

		if (isset($_POST['pmm_drop_sequences'])) {
			$drop_sequences = $this->sanitize_drop_sequences(wp_unslash($_POST['pmm_drop_sequences']));
		} else {
			$drop_sequences = get_option('pmm_drop_sequences', []);
			if (!is_array($drop_sequences)) {
				$drop_sequences = [];
			}
		}

		$include_entity_report = true;
		$similarity_thresholds = $this->read_similarity_thresholds_from_request(isset($_POST) ? $_POST : null);
		$questionable_settings = $this->read_questionable_settings_from_request(isset($_POST) ? $_POST : null);

		$entity_related_match_mode = isset($_POST['pmm_entity_related_match_mode']) ? sanitize_key((string) wp_unslash($_POST['pmm_entity_related_match_mode'])) : (string) get_option('pmm_entity_related_match_mode', 'normal');
		if (!in_array($entity_related_match_mode, ['normal', 'strict'], true)) {
			$entity_related_match_mode = 'normal';
		}

		update_option('pmm_last_mode', $mode, false);
		update_option('pmm_last_format', $format, false);
		update_option('pmm_drop_sequences', $drop_sequences, false);
		update_option('pmm_include_entity_report', '1', false);
		update_option('pmm_similarity_thresholds', $similarity_thresholds, false);
		update_option('pmm_questionable_settings', $questionable_settings, false);
		update_option('pmm_entity_related_match_mode', $entity_related_match_mode, false);

		return [
			'mode' => $mode,
			'format' => $format,
			'drop_sequences' => $drop_sequences,
			'include_entity_report' => $include_entity_report,
		];
	}

	public function process_batch() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_process_batch');

		$job_id = isset($_POST['pmm_job']) ? sanitize_key(wp_unslash($_POST['pmm_job'])) : '';
		if ($job_id === '') {
			$this->redirect_with_error('missing_job');
		}

		$state = $this->get_job_state($job_id);
		if (empty($state) || (int) $state['user_id'] !== get_current_user_id()) {
			$this->redirect_with_error('job_expired');
		}

		if (function_exists('set_time_limit')) {
			@set_time_limit(40);
		}

		$deadline = microtime(true) + $this->batch_time_budget_seconds;

		if ($state['stage'] === 'parsing') {
			$state = $this->run_parse_batch($state);
		}

		if ($state['stage'] === 'dedupe') {
			$state = $this->run_dedupe_batch($state, $deadline);
		}

		if ($state['stage'] === 'render') {
			$this->finish_render($state);

			$this->delete_job_state($job_id);
			$this->delete_job_source_file($state);

			wp_safe_redirect(add_query_arg([
				'page' => 'perchance-memory-manager',
				'pmm_success' => 1,
			], admin_url('admin.php')));
			exit;
		}

		$this->save_job_state($job_id, $state);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_processing' => 1,
			'pmm_job' => $job_id,
		], admin_url('admin.php')));
		exit;
	}

	public function download_last_output() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_download_last_output');

		$data = get_transient('pmm_last_output_' . get_current_user_id());
		if (empty($data['content']) || empty($data['filename'])) {
			wp_die(esc_html__('No processed file is available for download.', 'perchance-memory-manager'));
		}

		$filename = sanitize_file_name($data['filename']);
		$content = (string) $data['content'];

		nocache_headers();
		header('Content-Description: File Transfer');
		header('Content-Type: text/plain; charset=' . get_option('blog_charset'));
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . strlen($content));

		echo $content;
		exit;
	}

	public function save_preview_content() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_save_preview_content');

		$data = get_transient('pmm_last_output_' . get_current_user_id());
		if (empty($data['content']) || !isset($data['stats']) || !is_array($data['stats'])) {
			$this->redirect_with_error('preview_missing');
		}

		$content = isset($_POST['pmm_preview_content']) ? (string) wp_unslash($_POST['pmm_preview_content']) : '';
		$content = trim($content);
		if ($content === '') {
			$this->redirect_with_error('preview_missing');
		}

		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'md';
		$source_filename = isset($data['stats']['original_filename']) ? (string) $data['stats']['original_filename'] : 'memory.txt';

		$data['content'] = $content;
		$data['filename'] = $this->build_output_filename($source_filename, $format);

		$parser = new PMM_Parser();
		$parsed = $parser->parse($content);
		if (is_array($parsed) && !empty($parsed)) {
			$data['cleaned_data'] = $parsed;
			$data['stats']['sections'] = count($parsed);
			$data['stats']['entities'] = PMM_Utils::count_entities($parsed);
			$data['stats']['bullets'] = PMM_Utils::count_bullets($parsed);
			$data['entity_report'] = $this->build_entity_report_payload([
				'cleaned' => $parsed,
				'entity_report' => ['new_entities' => []],
			]);
		}

		$version_meta = $this->persist_versioned_output($content, $source_filename, $format);
		if (!empty($version_meta['filename'])) {
			$data['stats']['version_filename'] = (string) $version_meta['filename'];
			$data['stats']['version_saved_at'] = (int) $version_meta['saved_at'];
		}

		set_transient('pmm_last_output_' . get_current_user_id(), $data, 30 * MINUTE_IN_SECONDS);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_preview_saved' => 1,
		], admin_url('admin.php')));
		exit;
	}

	public function apply_similarity_review() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_apply_similarity_review');

		$rows = isset($_POST['pmm_similarity']) ? wp_unslash($_POST['pmm_similarity']) : [];
		if (!is_array($rows)) {
			$rows = [];
		}

		$alias_rules = get_option('pmm_alias_rules', []);
		if (!is_array($alias_rules)) {
			$alias_rules = [];
		}

		$ignored = get_option('pmm_similarity_ignored_pairs', []);
		if (!is_array($ignored)) {
			$ignored = [];
		}

		$valid_sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes'];
		$applied = 0;
		$output_rule_changes = 0;
		$log_rows = [];
		$removals = get_option('pmm_entity_removal_rules', []);
		$removals = $this->normalize_entity_rule_items($removals);
		$data = $this->get_last_output_data_for_editing();
		$cleaned = [];
		$cleaned_changed = false;
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$action = isset($row['action']) ? sanitize_key($row['action']) : 'skip';
			$section = isset($row['section']) ? sanitize_text_field((string) $row['section']) : '';
			$a = isset($row['a']) ? sanitize_text_field((string) $row['a']) : '';
			$b = isset($row['b']) ? sanitize_text_field((string) $row['b']) : '';
			$canonical = isset($row['canonical']) ? sanitize_text_field((string) $row['canonical']) : '';
			$original_section = isset($row['original_section']) ? sanitize_text_field((string) $row['original_section']) : $section;
			$original_a = isset($row['original_a']) ? sanitize_text_field((string) $row['original_a']) : $a;
			$original_b = isset($row['original_b']) ? sanitize_text_field((string) $row['original_b']) : $b;

			if (!in_array($section, $valid_sections, true)) {
				$section = $original_section;
			}
			if (!in_array($section, $valid_sections, true)) {
				$section = 'Characters';
			}

			if (!in_array($original_section, $valid_sections, true)) {
				$original_section = $section;
			}

			if ($a === '' || $b === '' || $section === '') {
				continue;
			}

			if ($action === 'skip') {
				continue;
			}

			$pair_key = $this->build_similarity_pair_key($section, $a, $b);
			$original_pair_key = $this->build_similarity_pair_key($original_section, $original_a, $original_b);

			if ($action === 'keep') {
				$ignored[$original_pair_key] = true;
				$ignored[$pair_key] = true;
				$log_rows[] = [
					'time' => time(),
					'action' => 'keep',
					'section' => $section,
					'a' => $a,
					'b' => $b,
					'canonical' => '',
				];
				++$applied;
				continue;
			}

			if (
				$action === 'alias' ||
				$action === 'merge' ||
				$action === 'merge_to_suggested' ||
				$action === 'merge_to_a' ||
				$action === 'merge_to_b'
			) {
				if ($action === 'merge_to_a') {
					$canonical = $a;
				} elseif ($action === 'merge_to_b') {
					$canonical = $b;
				} elseif ($canonical === '') {
					$canonical = $this->choose_canonical_name($a, $b);
				}

				$source = ($canonical === $a) ? $b : $a;
				$alias_rules[$source] = $canonical;
				$remap = $this->remap_entity_removal_rules($removals, $section, $source, $canonical);
				$removals = $remap['rules'];
				if (!empty($remap['changed'])) {
					++$output_rule_changes;
				}

				if ($section !== $original_section && !empty($data['content']) && isset($data['stats']) && is_array($data['stats'])) {
					if (empty($cleaned) || !is_array($cleaned)) {
						$cleaned = $this->get_cleaned_data_from_last_output($data);
					}
					if (!empty($cleaned) && is_array($cleaned)) {
						if ($this->move_entity_bucket_between_sections($cleaned, $original_section, $section, $original_a)) {
							$cleaned_changed = true;
						}
						if ($this->move_entity_bucket_between_sections($cleaned, $original_section, $section, $original_b)) {
							$cleaned_changed = true;
						}
					}
				}

				$log_rows[] = [
					'time' => time(),
					'action' => 'merge',
					'section' => $section,
					'a' => $a,
					'b' => $b,
					'canonical' => $canonical,
				];
				unset($ignored[$pair_key]);
				unset($ignored[$original_pair_key]);
				++$applied;
				++$output_rule_changes;
			}
		}

		$alias_rules = $this->normalize_alias_rules($alias_rules);
		update_option('pmm_alias_rules', $alias_rules, false);
		update_option('pmm_similarity_ignored_pairs', $ignored, false);
		update_option('pmm_entity_removal_rules', $removals, false);
		$this->append_similarity_log($log_rows);
		if ($output_rule_changes > 0) {
			$this->mark_output_rules_dirty();
		}

		if ($cleaned_changed && !empty($data['content']) && isset($data['stats']) && is_array($data['stats']) && !empty($cleaned) && is_array($cleaned)) {
			$renderer = new PMM_Renderer();
			$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'md';
			$output = $renderer->render($cleaned, $format);

			$data['content'] = $output;
			$data['cleaned_data'] = $cleaned;
			$data['stats']['sections'] = count($cleaned);
			$data['stats']['entities'] = PMM_Utils::count_entities($cleaned);
			$data['stats']['bullets'] = PMM_Utils::count_bullets($cleaned);
			$data['entity_report'] = $this->build_entity_report_payload([
				'cleaned' => $cleaned,
				'entity_report' => ['new_entities' => []],
			]);

			$source_filename = isset($data['stats']['original_filename']) ? (string) $data['stats']['original_filename'] : 'memory.txt';
			$version_meta = $this->persist_versioned_output($output, $source_filename, $format);
			if (!empty($version_meta['filename'])) {
				$data['stats']['version_filename'] = (string) $version_meta['filename'];
				$data['stats']['version_saved_at'] = (int) $version_meta['saved_at'];
			}

			set_transient('pmm_last_output_' . get_current_user_id(), $data, 30 * MINUTE_IN_SECONDS);
		}

		$apply_now = !empty($_POST['pmm_apply_now']);
		if ($apply_now && $applied > 0) {
			if ($this->start_reprocess_from_last_output()) {
				return;
			}
		}

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_similarity_saved' => (string) $applied,
		], admin_url('admin.php')));
		exit;
	}

	public function save_alias_rules() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_save_alias_rules');

		$text = isset($_POST['pmm_alias_rules_text']) ? wp_unslash($_POST['pmm_alias_rules_text']) : '';
		$rules = $this->parse_alias_rules_text($text);
		update_option('pmm_alias_rules', $rules, false);
		$this->mark_output_rules_dirty();

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_alias_saved' => (string) count($rules),
		], admin_url('admin.php')));
		exit;
	}

	public function apply_entity_review() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_apply_entity_review');

		$rows = isset($_POST['pmm_entities']) ? wp_unslash($_POST['pmm_entities']) : [];
		if (!is_array($rows)) {
			$rows = [];
		}

		$hidden = get_option('pmm_entity_review_hidden', []);
		$hidden = $this->normalize_entity_rule_items($hidden);

		$removals = get_option('pmm_entity_removal_rules', []);
		$removals = $this->normalize_entity_rule_items($removals);

		$applied = 0;
		$output_rule_changes = 0;
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$section = isset($row['section']) ? sanitize_text_field((string) $row['section']) : '';
			$name = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';

			if ($section === '' || $name === '') {
				continue;
			}

			$key = $this->build_entity_rule_key($section, $name);

			if ($action === 'hide') {
				$had_removal = isset($removals[$key]);
				$hidden[$key] = [
					'section' => $section,
					'name' => $name,
				];
				unset($removals[$key]);
				if ($had_removal) {
					++$output_rule_changes;
				}
				++$applied;
				continue;
			}

			if ($action === 'remove') {
				$had_removal = isset($removals[$key]);
				$removals[$key] = [
					'section' => $section,
					'name' => $name,
				];
				unset($hidden[$key]);
				if (!$had_removal) {
					++$output_rule_changes;
				}
				++$applied;
			}
		}

		$hidden = $this->normalize_entity_rule_items($hidden);
		$removals = $this->normalize_entity_rule_items($removals);
		update_option('pmm_entity_review_hidden', $hidden, false);
		update_option('pmm_entity_removal_rules', $removals, false);
		if ($output_rule_changes > 0) {
			$this->mark_output_rules_dirty();
		}

		$apply_now = !empty($_POST['pmm_entity_apply_now']);
		if ($apply_now && $applied > 0) {
			if ($this->start_reprocess_from_last_output()) {
				return;
			}
		}

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_entity_saved' => (string) $applied,
		], admin_url('admin.php')));
		exit;
	}

	public function apply_questionable_review() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_apply_questionable_review');

		$rows = isset($_POST['pmm_questionable']) ? wp_unslash($_POST['pmm_questionable']) : [];
		if (!is_array($rows)) {
			$rows = [];
		}

		$hidden = get_option('pmm_questionable_hidden_entries', []);
		$hidden = $this->normalize_entry_rule_items($hidden);

		$removals = get_option('pmm_entry_removal_rules', []);
		$removals = $this->normalize_entry_rule_items($removals);

		$valid_sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes'];
		$applied = 0;
		$reviewed = 0;
		$changed = 0;
		$removed_count = 0;
		$hidden_count = 0;
		$kept_count = 0;
		$updated_entries = 0;
		$output_rule_changes = 0;
		$expected_count = isset($_POST['pmm_questionable_expected_count']) ? max(0, (int) wp_unslash((string) $_POST['pmm_questionable_expected_count'])) : 0;
		$data = $this->get_last_output_data_for_editing();
		$cleaned = [];
		$cleaned_changed = false;
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$section = isset($row['section']) ? sanitize_text_field((string) $row['section']) : '';
			$entity = isset($row['entity']) ? sanitize_text_field((string) $row['entity']) : '';
			$entry = isset($row['entry']) ? sanitize_textarea_field((string) $row['entry']) : '';
			$original_section = isset($row['original_section']) ? sanitize_text_field((string) $row['original_section']) : $section;
			$original_entity = isset($row['original_entity']) ? sanitize_text_field((string) $row['original_entity']) : $entity;
			$original_entry = isset($row['original_entry']) ? sanitize_textarea_field((string) $row['original_entry']) : $entry;

			if (!in_array($section, $valid_sections, true)) {
				$section = $original_section;
			}
			if (!in_array($section, $valid_sections, true)) {
				$section = 'Notes';
			}
			if (!in_array($original_section, $valid_sections, true)) {
				$original_section = $section;
			}

			if ($original_section === '' || $original_entry === '') {
				continue;
			}

			++$reviewed;

			$key = $this->build_entry_rule_key($original_section, $original_entity, $original_entry);

			if ($action === 'update') {
				if ($entry === '') {
					continue;
				}
				if (empty($data['content']) || !isset($data['stats']) || !is_array($data['stats'])) {
					continue;
				}
				if (empty($cleaned) || !is_array($cleaned)) {
					$cleaned = $this->get_cleaned_data_from_last_output($data);
				}
				if (empty($cleaned) || !is_array($cleaned)) {
					continue;
				}

				$target_entity = trim((string) $entity);
				if (!in_array($section, ['Notes', 'Relationships', 'NSFW'], true) && $target_entity === '') {
					$target_entity = trim((string) $original_entity);
				}

				$entry_changed = $this->move_entry_in_cleaned(
					$cleaned,
					$original_section,
					$original_entity,
					$original_entry,
					$section,
					$target_entity,
					$entry
				);

				if ($entry_changed) {
					$cleaned_changed = true;
					++$changed;
					++$updated_entries;
					++$applied;
				}
				continue;
			}

			if ($action === 'hide') {
				$had_hidden = isset($hidden[$key]);
				$hidden[$key] = [
					'section' => $original_section,
					'entity' => $original_entity,
					'entry' => $original_entry,
				];
				unset($removals[$key]);
				if (!$had_hidden) {
					++$changed;
				}
				++$hidden_count;
				++$applied;
				continue;
			}

			if ($action === 'remove') {
				if (!isset($removals[$key])) {
					++$changed;
					++$output_rule_changes;
				}
				$removals[$key] = [
					'section' => $original_section,
					'entity' => $original_entity,
					'entry' => $original_entry,
				];
				unset($hidden[$key]);
				++$removed_count;
				++$applied;
				continue;
			}

			if ($action === 'keep') {
				$had_removal = isset($removals[$key]);
				$had_hidden = isset($hidden[$key]);
				unset($removals[$key]);
				unset($hidden[$key]);
				if ($had_removal || $had_hidden) {
					++$changed;
				}
				if ($had_removal) {
					++$output_rule_changes;
				}
				++$kept_count;
				++$applied;
			}
		}

		$hidden = $this->normalize_entry_rule_items($hidden);
		$removals = $this->normalize_entry_rule_items($removals);
		update_option('pmm_questionable_hidden_entries', $hidden, false);
		update_option('pmm_entry_removal_rules', $removals, false);

		if ($cleaned_changed && !empty($data['content']) && isset($data['stats']) && is_array($data['stats'])) {
			$renderer = new PMM_Renderer();
			$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'md';
			$output = $renderer->render($cleaned, $format);

			$data['content'] = $output;
			$data['cleaned_data'] = $cleaned;
			$data['stats']['sections'] = count($cleaned);
			$data['stats']['entities'] = PMM_Utils::count_entities($cleaned);
			$data['stats']['bullets'] = PMM_Utils::count_bullets($cleaned);
			$data['entity_report'] = $this->build_entity_report_payload([
				'cleaned' => $cleaned,
				'entity_report' => ['new_entities' => []],
			]);

			$source_filename = isset($data['stats']['original_filename']) ? (string) $data['stats']['original_filename'] : 'memory.txt';
			$version_meta = $this->persist_versioned_output($output, $source_filename, $format);
			if (!empty($version_meta['filename'])) {
				$data['stats']['version_filename'] = (string) $version_meta['filename'];
				$data['stats']['version_saved_at'] = (int) $version_meta['saved_at'];
			}

			set_transient('pmm_last_output_' . get_current_user_id(), $data, 30 * MINUTE_IN_SECONDS);
		}

		if ($output_rule_changes > 0) {
			$this->mark_output_rules_dirty();
		}

		$apply_now = !empty($_POST['pmm_questionable_apply_now']);
		if ($apply_now && $applied > 0 && $output_rule_changes > 0) {
			if ($this->start_reprocess_from_last_output()) {
				return;
			}
		}

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_questionable_saved' => (string) $applied,
			'pmm_questionable_reviewed' => (string) $reviewed,
			'pmm_questionable_changed' => (string) $changed,
			'pmm_questionable_removed' => (string) $removed_count,
			'pmm_questionable_hidden' => (string) $hidden_count,
			'pmm_questionable_kept' => (string) $kept_count,
			'pmm_questionable_updated' => (string) $updated_entries,
			'pmm_questionable_truncated' => (string) (($expected_count > 0 && $reviewed > 0 && $reviewed < $expected_count) ? 1 : 0),
			'pmm_questionable_expected_count' => (string) $expected_count,
		], admin_url('admin.php')));
		exit;
	}

	public function manage_hidden_entities() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_manage_hidden_entities');

		$hidden = get_option('pmm_entity_review_hidden', []);
		$hidden = $this->normalize_entity_rule_items($hidden);

		$action = isset($_POST['pmm_hidden_action']) ? sanitize_key((string) wp_unslash($_POST['pmm_hidden_action'])) : 'selected';
		$updated = 0;

		if ($action === 'all') {
			$updated = count($hidden);
			$hidden = [];
		} else {
			$keys = isset($_POST['pmm_hidden_keys']) ? (array) wp_unslash($_POST['pmm_hidden_keys']) : [];
			foreach ($keys as $key) {
				$key = sanitize_text_field((string) $key);
				if ($key !== '' && isset($hidden[$key])) {
					unset($hidden[$key]);
					++$updated;
				}
			}
		}

		update_option('pmm_entity_review_hidden', $hidden, false);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_hidden_updated' => (string) $updated,
		], admin_url('admin.php')));
		exit;
	}

	public function preview_raw_import() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_preview_raw_import');

		$text = isset($_POST['pmm_raw_import_text']) ? (string) wp_unslash($_POST['pmm_raw_import_text']) : '';
		$file_text = $this->read_uploaded_text_file('pmm_raw_import_file', 12 * 1024 * 1024);
		if ($file_text !== '') {
			$text = trim($text . "\n" . $file_text);
		}
		$text = trim($text);

		$parser = new PMM_Parser();
		$rows = $parser->preview_raw_import_rows($text, $this->build_existing_entity_seed_from_last_output());
		set_transient($this->get_raw_import_preview_key(), [
			'raw_text' => $text,
			'rows' => $rows,
		], 30 * MINUTE_IN_SECONDS);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_raw_previewed' => (string) count($rows),
		], admin_url('admin.php')));
		exit;
	}

	public function stage_raw_import() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_stage_raw_import');

		$text = isset($_POST['pmm_raw_import_rows']) ? (string) wp_unslash($_POST['pmm_raw_import_rows']) : '';
		$table_rows = isset($_POST['pmm_raw_table']) && is_array($_POST['pmm_raw_table']) ? (array) wp_unslash($_POST['pmm_raw_table']) : [];
		if ($text === '' && !empty($table_rows)) {
			$text = $this->serialize_raw_import_table_rows($table_rows);
		}
		$file_text = $this->read_uploaded_text_file('pmm_raw_import_rows_file', 15 * 1024 * 1024);
		if ($file_text !== '') {
			$text = $file_text;
		}
		$rows = $this->parse_staged_raw_import_rows_text($text);
		set_transient($this->get_staged_raw_import_key(), $rows, 30 * MINUTE_IN_SECONDS);
		$this->mark_output_rules_dirty();

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_raw_staged' => (string) count($rows),
		], admin_url('admin.php')));
		exit;
	}

	public function download_raw_import_rows() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_download_raw_import_rows');

		$preview = get_transient($this->get_raw_import_preview_key());
		$rows = isset($preview['rows']) && is_array($preview['rows']) ? $preview['rows'] : [];
		if (empty($rows)) {
			$rows = $this->get_staged_raw_import_rows();
		}

		$content = $this->serialize_staged_raw_import_rows($rows);
		$filename = 'pmm-raw-import-stage-' . gmdate('Ymd-His') . '.tsv';

		nocache_headers();
		header('Content-Description: File Transfer');
		header('Content-Type: text/tab-separated-values; charset=' . get_option('blog_charset'));
		header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
		header('Content-Length: ' . strlen($content));

		echo $content;
		exit;
	}

	public function clear_raw_import_preview() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_clear_raw_import_preview');
		delete_transient($this->get_raw_import_preview_key());
		if (!empty($_POST['pmm_clear_staged_raw_import'])) {
			delete_transient($this->get_staged_raw_import_key());
			$this->mark_output_rules_dirty();
		}

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_raw_cleared' => 1,
		], admin_url('admin.php')));
		exit;
	}

	public function save_entity_update() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_save_entity_update');

		$data = get_transient('pmm_last_output_' . get_current_user_id());
		if (empty($data['content']) || !isset($data['stats']) || !is_array($data['stats'])) {
			$this->redirect_with_error('entity_update_missing');
		}

		$cleaned = $this->get_cleaned_data_from_last_output($data);
		if (empty($cleaned) || !is_array($cleaned)) {
			$this->redirect_with_error('entity_update_missing');
		}
		$valid_sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes'];
		$payload = [
			'section' => isset($_POST['pmm_edit_section']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_edit_section'])) : 'Characters',
			'entity' => isset($_POST['pmm_edit_entity']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_edit_entity'])) : '',
			'target_section' => isset($_POST['pmm_edit_target_section']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_edit_target_section'])) : '',
			'target_entity' => isset($_POST['pmm_edit_entity_name']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_edit_entity_name'])) : '',
			'action' => isset($_POST['pmm_edit_action']) ? sanitize_key((string) wp_unslash($_POST['pmm_edit_action'])) : 'replace',
			'entries_text' => isset($_POST['pmm_edit_entries']) ? (string) wp_unslash($_POST['pmm_edit_entries']) : '',
		];

		if (!in_array($payload['section'], $valid_sections, true)) {
			$payload['section'] = 'Characters';
		}
		if (!in_array($payload['target_section'], $valid_sections, true)) {
			$payload['target_section'] = $payload['section'];
		}
		if ($payload['action'] === 'delete') {
			$payload['target_section'] = $payload['section'];
		}

		$source_section = $payload['section'];
		$source_entity = $payload['entity'];
		$is_section_move = $payload['target_section'] !== $source_section;

		$payload['target_entity'] = $this->normalize_entity_target_name($payload['target_section'], $payload['entity'], $payload['target_entity']);
		$source_entries = isset($cleaned[$source_section][$source_entity]) && is_array($cleaned[$source_section][$source_entity])
			? (array) $cleaned[$source_section][$source_entity]
			: [];

		// Detect before the rename whether target already has its own entries.
		// If it does, the textarea only contains the source entity's lines, so we
		// must not let apply_entity_edit_payload overwrite the merged bucket.
		$is_merge_into_existing = !$is_section_move
			&& $payload['action'] !== 'delete'
			&& $payload['target_entity'] !== ''
			&& $payload['entity'] !== $payload['target_entity']
			&& isset($cleaned[$payload['section']][$payload['target_entity']])
			&& is_array($cleaned[$payload['section']][$payload['target_entity']]);
		$is_section_move_merge = $is_section_move
			&& $payload['action'] !== 'delete'
			&& $payload['target_entity'] !== ''
			&& isset($cleaned[$payload['target_section']][$payload['target_entity']])
			&& is_array($cleaned[$payload['target_section']][$payload['target_entity']]);

		if (!$is_section_move) {
			$cleaned = $this->maybe_rename_entity_key($cleaned, $payload);
		}
		$payload['section'] = $payload['target_section'];

		if ($payload['target_entity'] !== '' && !in_array($payload['section'], ['Notes', 'Relationships', 'NSFW'], true)) {
			$payload['entity'] = $payload['target_entity'];
		} elseif (in_array($payload['section'], ['Notes', 'Relationships', 'NSFW'], true)) {
			$payload['entity'] = '';
		}

		// When merging into an existing entity, the merged bucket is already the
		// correct combined result. Override entries_text so apply_entity_edit_payload
		// preserves all merged entries rather than replacing with only the textarea
		// content that was loaded from the source entity.
		if ($is_merge_into_existing && isset($cleaned[$payload['section']][$payload['entity']]) && is_array($cleaned[$payload['section']][$payload['entity']])) {
			$payload['entries_text'] = implode("\n", (array) $cleaned[$payload['section']][$payload['entity']]);
		} elseif ($is_section_move_merge && isset($cleaned[$payload['section']][$payload['entity']]) && is_array($cleaned[$payload['section']][$payload['entity']])) {
			$merged_entries = array_merge((array) $cleaned[$payload['section']][$payload['entity']], $source_entries);
			$payload['entries_text'] = implode("\n", $merged_entries);
		} elseif ($is_section_move && $payload['action'] === 'replace' && trim((string) $payload['entries_text']) === '') {
			// Defensive fallback: if section move is requested but the edit textarea is
			// empty (stale UI/load timing), preserve existing source entries.
			$payload['entries_text'] = implode("\n", $source_entries);
		}

		$cleaned = $this->apply_entity_edit_payload($cleaned, $payload);

		if ($is_section_move && $source_entity !== '' && isset($cleaned[$source_section]) && is_array($cleaned[$source_section]) && isset($cleaned[$source_section][$source_entity])) {
			unset($cleaned[$source_section][$source_entity]);
		}

		$renderer = new PMM_Renderer();
		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'md';
		$output = $renderer->render($cleaned, $format);

		$data['content'] = $output;
		$data['cleaned_data'] = $cleaned;
		$data['stats']['sections'] = count($cleaned);
		$data['stats']['entities'] = PMM_Utils::count_entities($cleaned);
		$data['stats']['bullets'] = PMM_Utils::count_bullets($cleaned);

		$entity_report_state = [
			'cleaned' => $cleaned,
			'entity_report' => [
				'new_entities' => [],
			],
		];
		$data['entity_report'] = $this->build_entity_report_payload($entity_report_state);

		$source_filename = isset($data['stats']['original_filename']) ? (string) $data['stats']['original_filename'] : 'memory.txt';
		$version_meta = $this->persist_versioned_output($output, $source_filename, $format);
		if (!empty($version_meta['filename'])) {
			$data['stats']['version_filename'] = (string) $version_meta['filename'];
			$data['stats']['version_saved_at'] = (int) $version_meta['saved_at'];
		}

		set_transient('pmm_last_output_' . get_current_user_id(), $data, 30 * MINUTE_IN_SECONDS);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_entity_updated' => 1,
			'pmm_edit_section' => $payload['section'],
			'pmm_edit_entity' => $payload['entity'],
		], admin_url('admin.php')));
		exit;
	}

	public function save_entity_bulk_update() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_save_entity_bulk_update');

		$data = get_transient('pmm_last_output_' . get_current_user_id());
		if (empty($data['content']) || !isset($data['stats']) || !is_array($data['stats'])) {
			$this->redirect_with_error('entity_update_missing');
		}

		$cleaned = $this->get_cleaned_data_from_last_output($data);
		if (empty($cleaned) || !is_array($cleaned)) {
			$this->redirect_with_error('entity_update_missing');
		}

		$section = isset($_POST['pmm_bulk_section']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_bulk_section'])) : 'Characters';
		$action = isset($_POST['pmm_bulk_action']) ? sanitize_key((string) wp_unslash($_POST['pmm_bulk_action'])) : 'replace';
		$entity_list = isset($_POST['pmm_bulk_entities']) ? (string) wp_unslash($_POST['pmm_bulk_entities']) : '';
		$entries_text = isset($_POST['pmm_bulk_entries']) ? (string) wp_unslash($_POST['pmm_bulk_entries']) : '';

		$valid_sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes'];
		if (!in_array($section, $valid_sections, true)) {
			$section = 'Characters';
		}

		$targets = preg_split('/\r\n|\r|\n/u', $entity_list);
		$targets = array_values(array_filter(array_map('sanitize_text_field', (array) $targets), static function($v) {
			return trim((string) $v) !== '';
		}));

		$payload = [
			'section' => $section,
			'entity' => '',
			'action' => $action,
			'entries_text' => $entries_text,
		];

		if (!isset($cleaned[$section]) || !is_array($cleaned[$section])) {
			$cleaned[$section] = [];
		}

		if (empty($targets)) {
			$payload['entity'] = '';
			$cleaned = $this->apply_entity_edit_payload($cleaned, $payload);
		} else {
			foreach ($targets as $target) {
				$payload['entity'] = $target;
				$cleaned = $this->apply_entity_edit_payload($cleaned, $payload);
			}
		}

		$renderer = new PMM_Renderer();
		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'md';
		$output = $renderer->render($cleaned, $format);

		$data['content'] = $output;
		$data['cleaned_data'] = $cleaned;
		$data['stats']['sections'] = count($cleaned);
		$data['stats']['entities'] = PMM_Utils::count_entities($cleaned);
		$data['stats']['bullets'] = PMM_Utils::count_bullets($cleaned);
		$data['entity_report'] = $this->build_entity_report_payload([
			'cleaned' => $cleaned,
			'entity_report' => ['new_entities' => []],
		]);

		$source_filename = isset($data['stats']['original_filename']) ? (string) $data['stats']['original_filename'] : 'memory.txt';
		$version_meta = $this->persist_versioned_output($output, $source_filename, $format);
		if (!empty($version_meta['filename'])) {
			$data['stats']['version_filename'] = (string) $version_meta['filename'];
			$data['stats']['version_saved_at'] = (int) $version_meta['saved_at'];
		}

		set_transient('pmm_last_output_' . get_current_user_id(), $data, 30 * MINUTE_IN_SECONDS);
		$this->mark_output_rules_dirty();

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_entity_updated' => 1,
			'pmm_edit_section' => $section,
			'pmm_edit_entity' => isset($targets[0]) ? $targets[0] : '',
		], admin_url('admin.php')));
		exit;
	}

	public function global_search_replace() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_global_search_replace');

		$data = get_transient('pmm_last_output_' . get_current_user_id());
		if (empty($data['content']) || !isset($data['stats']) || !is_array($data['stats'])) {
			$this->redirect_with_error('preview_missing');
		}

		$cleaned = $this->get_cleaned_data_from_last_output($data);
		if (empty($cleaned) || !is_array($cleaned)) {
			$this->redirect_with_error('preview_missing');
		}

		$search = isset($_POST['pmm_global_search']) ? trim((string) wp_unslash($_POST['pmm_global_search'])) : '';
		$replace = isset($_POST['pmm_global_replace']) ? (string) wp_unslash($_POST['pmm_global_replace']) : '';
		$scope = isset($_POST['pmm_global_scope']) ? sanitize_key((string) wp_unslash($_POST['pmm_global_scope'])) : 'both';
		$case_sensitive = !empty($_POST['pmm_global_case_sensitive']);

		if ($search === '') {
			$this->redirect_with_error('global_replace_missing');
		}

		if (!in_array($scope, ['names_only', 'entries_only', 'both'], true)) {
			$scope = 'both';
		}

		if (in_array($scope, ['names_only', 'both'], true) && trim($replace) === '') {
			$this->redirect_with_error('global_replace_missing');
		}

		$stats = [
			'entities_renamed' => 0,
			'entities_merged' => 0,
			'entries_replaced' => 0,
		];

		$cleaned = $this->apply_global_search_replace($cleaned, $search, $replace, $scope, $case_sensitive, $stats);

		$renderer = new PMM_Renderer();
		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'md';
		$output = $renderer->render($cleaned, $format);

		$data['content'] = $output;
		$data['cleaned_data'] = $cleaned;
		$data['stats']['sections'] = count($cleaned);
		$data['stats']['entities'] = PMM_Utils::count_entities($cleaned);
		$data['stats']['bullets'] = PMM_Utils::count_bullets($cleaned);
		$data['entity_report'] = $this->build_entity_report_payload([
			'cleaned' => $cleaned,
			'entity_report' => ['new_entities' => []],
		]);

		$source_filename = isset($data['stats']['original_filename']) ? (string) $data['stats']['original_filename'] : 'memory.txt';
		$version_meta = $this->persist_versioned_output($output, $source_filename, $format);
		if (!empty($version_meta['filename'])) {
			$data['stats']['version_filename'] = (string) $version_meta['filename'];
			$data['stats']['version_saved_at'] = (int) $version_meta['saved_at'];
		}

		set_transient('pmm_last_output_' . get_current_user_id(), $data, 30 * MINUTE_IN_SECONDS);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_global_replaced' => 1,
			'pmm_global_scope' => $scope,
			'pmm_global_renamed' => (string) $stats['entities_renamed'],
			'pmm_global_merged' => (string) $stats['entities_merged'],
			'pmm_global_entries' => (string) $stats['entries_replaced'],
		], admin_url('admin.php')));
		exit;
	}

	public function reprocess_last_output() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_reprocess_last_output');

		if (!$this->start_reprocess_from_last_output()) {
			$this->redirect_with_error('reprocess_missing');
		}
	}

	private function build_output_filename($original, $format) {
		$base = pathinfo($original, PATHINFO_FILENAME);
		$base = sanitize_file_name($base);
		$extension = ($format === 'txt') ? 'txt' : 'md';

		return $base . '-cleaned.' . $extension;
	}

	private function get_job_state($job_id) {
		$legacy = get_transient($this->get_job_key($job_id));
		if (is_array($legacy) && isset($legacy['stage'])) {
			return $legacy;
		}

		$state_path = $this->build_job_state_path($job_id);
		if (!file_exists($state_path)) {
			return [];
		}

		if (false === $legacy) {
			@unlink($state_path);
			return [];
		}

		$raw = @file_get_contents($state_path);
		if (!is_string($raw) || $raw === '') {
			return [];
		}

		$state = @unserialize($raw);
		if (!is_array($state)) {
			return [];
		}

		return $state;
	}

	private function save_job_state($job_id, $state) {
		$state_path = $this->build_job_state_path($job_id);
		$state_dir = dirname($state_path);
		$serialized = serialize($state);

		if (wp_mkdir_p($state_dir) && false !== @file_put_contents($state_path, $serialized, LOCK_EX)) {
			set_transient($this->get_job_key($job_id), 1, $this->job_ttl);
			return;
		}

		set_transient($this->get_job_key($job_id), $state, $this->job_ttl);
	}

	private function delete_job_state($job_id) {
		delete_transient($this->get_job_key($job_id));
		$state_path = $this->build_job_state_path($job_id);
		if (file_exists($state_path)) {
			@unlink($state_path);
		}
	}

	private function get_job_key($job_id) {
		return 'pmm_job_' . get_current_user_id() . '_' . $job_id;
	}

	private function generate_job_id() {
		return 'job_' . wp_generate_password(10, false, false);
	}

	private function build_job_source_path($job_id, $ext) {
		$uploads = wp_upload_dir();
		return trailingslashit($uploads['basedir']) . 'pmm-jobs/' . $job_id . '-source.' . $ext;
	}

	private function build_job_state_path($job_id) {
		$uploads = wp_upload_dir();
		return trailingslashit($uploads['basedir']) . 'pmm-jobs/' . $job_id . '-state.bin';
	}

	private function count_file_lines($path) {
		$count = 0;
		$fh = new SplFileObject($path, 'r');

		while (!$fh->eof()) {
			$fh->fgets();
			++$count;
		}

		return max(1, $count);
	}

	private function read_file_line_batch($path, $offset, $limit) {
		$fh = new SplFileObject($path, 'r');
		$fh->seek(max(0, (int) $offset));

		$lines = [];
		for ($i = 0; $i < $limit && !$fh->eof(); $i++) {
			$lines[] = rtrim((string) $fh->fgets(), "\r\n");
		}

		return $lines;
	}

	private function run_parse_batch($state) {
		$offset = (int) $state['line_offset'];
		$total = (int) $state['total_lines'];

		$lines = $this->read_file_line_batch($state['source_path'], $offset, (int) $state['line_batch_size']);
		if (empty($lines)) {
			$parser = new PMM_Parser();
			$state['parsed'] = $this->apply_staged_raw_import_rows($state['parsed'], isset($state['staged_raw_import_rows']) ? (array) $state['staged_raw_import_rows'] : []);
			$state['parsed'] = $parser->finalize($state['parsed']);
			$state['entity_report'] = $parser->get_last_report();
			$state['dedupe_queue'] = $this->build_dedupe_queue($state['parsed']);
			$state['stage'] = 'dedupe';
			$state['dedupe_index'] = 0;
			return $state;
		}

		$chunk = implode("\n", $lines);
		$chunk = $this->prepend_context_to_chunk($chunk, $state['context']);

		$parser = new PMM_Parser();
		$parsed_chunk = $parser->parse_partial($chunk);

		$state['parsed'] = $this->merge_data_trees($state['parsed'], $parsed_chunk);
		$state['context'] = $this->detect_context($lines, $state['context']);
		$state['line_offset'] = min($total, $offset + count($lines));

		if ((int) $state['line_offset'] >= $total) {
			$state = $this->run_parse_batch($state);
		}

		return $state;
	}

	private function run_dedupe_batch($state, $deadline) {
		$queue = (array) $state['dedupe_queue'];
		$index = (int) $state['dedupe_index'];
		$mode = (string) $state['mode'];
		$drop_sequences = isset($state['drop_sequences']) ? (array) $state['drop_sequences'] : [];
		$dedupe = new PMM_Dedupe();

		$processed = 0;
		while (isset($queue[$index]) && $processed < $this->dedupe_batch_items && microtime(true) < $deadline) {
			$item = $queue[$index];
			$section = $item['section'];
			$key = $item['key'];

			$items = isset($state['parsed'][$section][$key]) ? (array) $state['parsed'][$section][$key] : [];
			$mini = [
				$section => [
					$key => $items,
				],
			];

			$clean = $dedupe->clean($mini, $mode, $drop_sequences);
			$state['cleaned'][$section][$key] = isset($clean[$section][$key]) ? $clean[$section][$key] : [];
			$state['counters'] = $this->merge_counters($state['counters'], $dedupe->get_stats());

			++$index;
			++$processed;
		}

		$state['dedupe_index'] = $index;

		if (!isset($queue[$index])) {
			$state['stage'] = 'render';
		}

		return $state;
	}

	private function finish_render($state) {
		$filtered = $this->filter_nsfw_without_character_refs($state['cleaned']);
		$state['cleaned'] = $filtered['data'];
		$state['counters']['removed_nsfw_non_character'] = isset($state['counters']['removed_nsfw_non_character']) ? (int) $state['counters']['removed_nsfw_non_character'] + (int) $filtered['removed'] : (int) $filtered['removed'];

		$entity_removed = $this->apply_entity_removal_rules($state['cleaned']);
		$state['cleaned'] = $entity_removed['data'];
		$state['counters']['removed_by_entity_rule'] = isset($state['counters']['removed_by_entity_rule']) ? (int) $state['counters']['removed_by_entity_rule'] + (int) $entity_removed['removed'] : (int) $entity_removed['removed'];

		$entry_removed = $this->apply_entry_removal_rules($state['cleaned']);
		$state['cleaned'] = $entry_removed['data'];
		$state['counters']['removed_by_entry_rule'] = isset($state['counters']['removed_by_entry_rule']) ? (int) $state['counters']['removed_by_entry_rule'] + (int) $entry_removed['removed'] : (int) $entry_removed['removed'];

		$renderer = new PMM_Renderer();
		$output = $renderer->render($state['cleaned'], $state['format']);

		$stats = [
			'sections' => count($state['cleaned']),
			'entities' => PMM_Utils::count_entities($state['cleaned']),
			'bullets' => PMM_Utils::count_bullets($state['cleaned']),
			'show_entity_lists' => !empty($state['include_entity_report']) ? 1 : 0,
			'removed_by_entity_rule' => isset($state['counters']['removed_by_entity_rule']) ? (int) $state['counters']['removed_by_entity_rule'] : 0,
			'removed_by_entry_rule' => isset($state['counters']['removed_by_entry_rule']) ? (int) $state['counters']['removed_by_entry_rule'] : 0,
			'removed_by_sequence' => isset($state['counters']['removed_by_sequence']) ? (int) $state['counters']['removed_by_sequence'] : 0,
			'removed_mundane_noise' => isset($state['counters']['removed_mundane_noise']) ? (int) $state['counters']['removed_mundane_noise'] : 0,
			'removed_nsfw_non_character' => isset($state['counters']['removed_nsfw_non_character']) ? (int) $state['counters']['removed_nsfw_non_character'] : 0,
			'removed_duplicates' => isset($state['counters']['removed_exact_duplicate']) && isset($state['counters']['removed_near_duplicate']) ? (int) $state['counters']['removed_exact_duplicate'] + (int) $state['counters']['removed_near_duplicate'] : 0,
			'removed_total' => $this->counter_total($state['counters']),
			'mode' => $state['mode'],
			'format' => $state['format'],
			'original_filename' => $state['source_filename'],
			'staged_raw_import_rows' => isset($state['staged_raw_import_rows']) ? count((array) $state['staged_raw_import_rows']) : 0,
		];
		$this->clear_output_rules_dirty();
		delete_transient($this->get_staged_raw_import_key());

		$version_meta = $this->persist_versioned_output($output, $state['source_filename'], $state['format']);
		if (!empty($version_meta['filename'])) {
			$stats['version_filename'] = (string) $version_meta['filename'];
			$stats['version_saved_at'] = (int) $version_meta['saved_at'];
		}

		set_transient('pmm_last_output_' . get_current_user_id(), [
			'filename' => $state['output_filename'],
			'content' => $output,
			'stats' => $stats,
			'cleaned_data' => $state['cleaned'],
			'entity_report' => $this->build_entity_report_payload($state),
		], 30 * MINUTE_IN_SECONDS);
	}

	private function merge_data_trees($a, $b) {
		foreach ($b as $section => $content) {
			if (!isset($a[$section]) || !is_array($a[$section])) {
				$a[$section] = [];
			}

			foreach ((array) $content as $key => $items) {
				if (!isset($a[$section][$key]) || !is_array($a[$section][$key])) {
					$a[$section][$key] = [];
				}

				$a[$section][$key] = array_merge($a[$section][$key], (array) $items);
			}
		}

		return $a;
	}

	private function prepend_context_to_chunk($chunk, $context) {
		$section = isset($context['section']) ? (string) $context['section'] : '';
		$entity = isset($context['entity']) ? (string) $context['entity'] : '';

		if ($section === '') {
			return $chunk;
		}

		$prefix = '# ' . $section . "\n";

		if ($entity !== '' && !in_array($section, ['Relationships', 'NSFW', 'Notes', 'New Entries'], true)) {
			$prefix .= $entity . "\n";
		}

		return $prefix . $chunk;
	}

	private function detect_context($lines, $initial) {
		$context = [
			'section' => isset($initial['section']) ? $initial['section'] : 'Notes',
			'entity' => isset($initial['entity']) ? $initial['entity'] : null,
		];

		foreach ($lines as $line_raw) {
			$line = trim((string) $line_raw);
			if ($line === '') {
				continue;
			}

			$section = $this->extract_section_from_line($line);
			if ($section !== null) {
				$context['section'] = $section;
				$context['entity'] = null;
				continue;
			}

			if (in_array($context['section'], ['Relationships', 'NSFW', 'Notes', 'New Entries'], true)) {
				continue;
			}

			if (preg_match('/^[A-Z][A-Za-z0-9_\-()\'\/.& ]{1,80}:?$/u', $line)) {
				$context['entity'] = rtrim($line, ':');
			}
		}

		return $context;
	}

	private function extract_section_from_line($line) {
		if (!preg_match('/^#?\s*(characters|organizations|locations|technology\s*\/\s*systems|relationships|nsfw|notes|new entries|raw import)\b/iu', $line, $m)) {
			return null;
		}

		$key = strtolower(trim(preg_replace('/\s+/u', ' ', $m[1])));
		$map = [
			'characters' => 'Characters',
			'organizations' => 'Organizations',
			'locations' => 'Locations',
			'technology / systems' => 'Technology / Systems',
			'relationships' => 'Relationships',
			'nsfw' => 'NSFW',
			'notes' => 'Notes',
			'new entries' => 'New Entries',
			'raw import' => 'New Entries',
		];

		return isset($map[$key]) ? $map[$key] : null;
	}

	private function empty_data_template() {
		return [
			'Characters' => [],
			'Organizations' => [],
			'Locations' => [],
			'Technology / Systems' => [],
			'Relationships' => [],
			'NSFW' => [],
			'Notes' => [],
			'New Entries' => [],
		];
	}

	private function build_dedupe_queue($data) {
		$queue = [];

		foreach ((array) $data as $section => $content) {
			foreach ((array) $content as $key => $items) {
				if (!is_array($items)) {
					continue;
				}

				$queue[] = [
					'section' => $section,
					'key' => $key,
				];
			}
		}

		return $queue;
	}

	private function delete_job_source_file($state) {
		if (!empty($state['source_is_persistent'])) {
			return;
		}

		if (!empty($state['source_path']) && file_exists($state['source_path'])) {
			@unlink($state['source_path']);
		}
	}

	private function redirect_with_error($error_code) {
		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_error' => $error_code,
		], admin_url('admin.php')));
		exit;
	}

	private function sanitize_drop_sequences($input) {
		$input = trim((string) $input);
		if ($input === '') {
			return [];
		}

		$lines = preg_split('/\r\n|\r|\n/u', $input);
		$out = [];

		foreach ((array) $lines as $line) {
			$line = sanitize_text_field(trim((string) $line));
			if ($line !== '') {
				$out[] = $line;
			}
		}

		return array_values(array_unique($out));
	}

	private function persist_versioned_output($content, $source_filename, $format) {
		$dir = trailingslashit(PMM_PLUGIN_DIR) . 'versions';
		if (!wp_mkdir_p($dir)) {
			return [];
		}

		$base = pathinfo((string) $source_filename, PATHINFO_FILENAME);
		$base = sanitize_file_name($base);
		if ($base === '') {
			$base = 'memory';
		}

		$ext = ($format === 'txt') ? 'txt' : 'md';
		$filename = $base . '-v' . gmdate('Ymd-His') . '-' . wp_generate_password(4, false, false) . '.' . $ext;
		$path = trailingslashit($dir) . $filename;

		if (file_put_contents($path, (string) $content) === false) {
			return [];
		}

		update_option('pmm_latest_version_file_path', $path, false);
		update_option('pmm_latest_version_filename', $filename, false);
		update_option('pmm_latest_version_saved_at', time(), false);

		$history = get_option('pmm_version_history', []);
		if (!is_array($history)) {
			$history = [];
		}
		$new_item = [
			'path' => $path,
			'filename' => $filename,
			'saved_at' => time(),
		];
		$filtered = [];
		foreach ($history as $item) {
			if (!is_array($item)) {
				continue;
			}
			if (isset($item['path']) && (string) $item['path'] === $path) {
				continue;
			}
			$filtered[] = $item;
		}
		array_unshift($filtered, $new_item);
		$expired = array_slice($filtered, $this->version_retention);
		$filtered = array_slice($filtered, 0, $this->version_retention);
		$this->prune_version_files($expired, $filtered);
		update_option('pmm_version_history', $filtered, false);

		return [
			'path' => $path,
			'filename' => $filename,
			'saved_at' => time(),
		];
	}

	private function get_raw_import_preview_key() {
		return 'pmm_raw_import_preview_' . get_current_user_id();
	}

	private function get_staged_raw_import_key() {
		return 'pmm_staged_raw_import_' . get_current_user_id();
	}

	private function get_staged_raw_import_rows() {
		$rows = get_transient($this->get_staged_raw_import_key());
		return is_array($rows) ? $rows : [];
	}

	private function build_existing_entity_seed_from_last_output() {
		$data = $this->empty_data_template();
		$last = get_transient('pmm_last_output_' . get_current_user_id());
		$groups = isset($last['entity_report']['entities']) && is_array($last['entity_report']['entities']) ? $last['entity_report']['entities'] : [];

		foreach ((array) $groups as $section => $names) {
			if (!isset($data[$section]) || !is_array($names)) {
				continue;
			}
			foreach ($names as $name) {
				$name = trim((string) $name);
				if ($name !== '') {
					$data[$section][$name] = [];
				}
			}
		}

		return $data;
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

	private function serialize_raw_import_table_rows($rows) {
		$valid_sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes'];
		$lines = [];

		foreach ((array) $rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$section = isset($row['section']) ? sanitize_text_field((string) $row['section']) : 'Notes';
			if (!in_array($section, $valid_sections, true)) {
				$section = 'Notes';
			}

			$entity = isset($row['entity']) ? sanitize_text_field((string) $row['entity']) : '';
			$bullet = isset($row['bullet']) ? (string) $row['bullet'] : '';
			$bullet = trim(preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r", "\t"], ' ', $bullet)));
			if ($bullet === '') {
				continue;
			}

			$lines[] = $section . "\t" . $entity . "\t" . $bullet;
		}

		return implode("\n", $lines);
	}

	private function parse_staged_raw_import_rows_text($text) {
		$lines = preg_split('/\r\n|\r|\n/u', (string) $text);
		$rows = [];
		$valid_sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes'];

		foreach ((array) $lines as $line) {
			$line = trim((string) $line);
			if ($line === '') {
				continue;
			}

			$parts = explode("\t", $line, 3);
			$section = isset($parts[0]) ? sanitize_text_field((string) $parts[0]) : 'Notes';
			$entity = isset($parts[1]) ? sanitize_text_field((string) $parts[1]) : '';
			$bullet = isset($parts[2]) ? sanitize_textarea_field((string) $parts[2]) : '';

			if (!in_array($section, $valid_sections, true)) {
				$section = 'Notes';
			}
			if ($bullet === '') {
				continue;
			}

			$rows[] = [
				'section' => $section,
				'entity' => $entity,
				'bullet' => $bullet,
			];
		}

		return $rows;
	}

	private function read_uploaded_text_file($field, $max_bytes) {
		if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
			return '';
		}

		$size = isset($_FILES[$field]['size']) ? (int) $_FILES[$field]['size'] : 0;
		if ($size <= 0 || $size > (int) $max_bytes) {
			return '';
		}

		$content = file_get_contents($_FILES[$field]['tmp_name']);
		if ($content === false) {
			return '';
		}

		return (string) $content;
	}

	private function get_cleaned_data_from_last_output($data) {
		if (isset($data['cleaned_data']) && is_array($data['cleaned_data'])) {
			return $data['cleaned_data'];
		}

		$content = isset($data['content']) ? (string) $data['content'] : '';
		if ($content === '') {
			return [];
		}

		$parser = new PMM_Parser();
		$parsed = $parser->parse($content);
		return is_array($parsed) ? $parsed : [];
	}

	private function get_last_output_data_for_editing() {
		$data = get_transient('pmm_last_output_' . get_current_user_id());
		if (is_array($data) && !empty($data['content']) && isset($data['stats']) && is_array($data['stats'])) {
			return $data;
		}

		$latest_version_path = (string) get_option('pmm_latest_version_file_path', '');
		if ($latest_version_path === '' || !file_exists($latest_version_path) || !is_readable($latest_version_path)) {
			return is_array($data) ? $data : [];
		}

		$content = file_get_contents($latest_version_path);
		if ($content === false) {
			return is_array($data) ? $data : [];
		}

		$parser = new PMM_Parser();
		$cleaned = $parser->parse((string) $content);
		if (!is_array($cleaned)) {
			$cleaned = [];
		}

		$latest_version_file = (string) get_option('pmm_latest_version_filename', '');
		$latest_version_saved_at = (int) get_option('pmm_latest_version_saved_at', 0);
		$rehydrated = [
			'filename' => $latest_version_file !== '' ? $latest_version_file : basename($latest_version_path),
			'content' => (string) $content,
			'stats' => [
				'sections' => count($cleaned),
				'entities' => PMM_Utils::count_entities($cleaned),
				'bullets' => PMM_Utils::count_bullets($cleaned),
				'mode' => (string) get_option('pmm_last_mode', 'balanced'),
				'format' => (string) get_option('pmm_last_format', 'md'),
				'original_filename' => $latest_version_file !== '' ? $latest_version_file : basename($latest_version_path),
				'version_filename' => $latest_version_file,
				'version_saved_at' => $latest_version_saved_at,
			],
			'cleaned_data' => $cleaned,
			'entity_report' => $this->build_entity_report_payload([
				'cleaned' => $cleaned,
				'entity_report' => ['new_entities' => []],
			]),
		];

		set_transient('pmm_last_output_' . get_current_user_id(), $rehydrated, 30 * MINUTE_IN_SECONDS);
		return $rehydrated;
	}

	private function apply_entity_edit_payload($cleaned, $payload) {
		$section = isset($payload['section']) ? sanitize_text_field((string) $payload['section']) : 'Characters';
		$entity = isset($payload['entity']) ? sanitize_text_field((string) $payload['entity']) : '';
		$action = isset($payload['action']) ? sanitize_key((string) $payload['action']) : 'replace';
		$entries_text = isset($payload['entries_text']) ? (string) $payload['entries_text'] : '';

		$lines = preg_split('/\r\n|\r|\n/u', $entries_text);
		$items = [];
		foreach ((array) $lines as $line) {
			$line = trim((string) $line);
			if ($line !== '') {
				$items[] = $line;
			}
		}

		if (!isset($cleaned[$section]) || !is_array($cleaned[$section])) {
			$cleaned[$section] = [];
		}

		if ($action === 'delete') {
			if (in_array($section, ['Notes', 'Relationships', 'NSFW'], true) && $entity === '') {
				$cleaned[$section]['__entries__'] = [];
			} elseif ($entity !== '' && isset($cleaned[$section][$entity])) {
				unset($cleaned[$section][$entity]);
			}
			return $cleaned;
		}

		if (in_array($section, ['Notes', 'Relationships', 'NSFW'], true) && $entity === '') {
			if (!isset($cleaned[$section]['__entries__']) || !is_array($cleaned[$section]['__entries__'])) {
				$cleaned[$section]['__entries__'] = [];
			}

			if ($action === 'append') {
				$cleaned[$section]['__entries__'] = array_merge((array) $cleaned[$section]['__entries__'], $items);
			} elseif ($action === 'prepend') {
				$cleaned[$section]['__entries__'] = array_merge($items, (array) $cleaned[$section]['__entries__']);
			} else {
				$cleaned[$section]['__entries__'] = $items;
			}
			return $cleaned;
		}

		if ($entity === '') {
			return $cleaned;
		}

		if (!isset($cleaned[$section][$entity]) || !is_array($cleaned[$section][$entity])) {
			$cleaned[$section][$entity] = [];
		}

		if ($action === 'append') {
			$cleaned[$section][$entity] = array_merge((array) $cleaned[$section][$entity], $items);
		} elseif ($action === 'prepend') {
			$cleaned[$section][$entity] = array_merge($items, (array) $cleaned[$section][$entity]);
		} else {
			$cleaned[$section][$entity] = $items;
		}

		return $cleaned;
	}

	private function apply_global_search_replace($cleaned, $search, $replace, $scope, $case_sensitive, &$stats) {
		$search = (string) $search;
		$replace = (string) $replace;
		$out = [];

		foreach ((array) $cleaned as $section => $content) {
			if (!is_array($content)) {
				continue;
			}

			if (!isset($out[$section]) || !is_array($out[$section])) {
				$out[$section] = [];
			}

			foreach ($content as $entity => $items) {
				if (!is_array($items)) {
					continue;
				}

				if (in_array((string) $entity, ['__entries__', '__unassigned__'], true)) {
					$out[$section][$entity] = $this->replace_in_items($items, $search, $replace, $scope, $case_sensitive, $stats);
					continue;
				}

				$new_entity = (string) $entity;
				if (in_array($scope, ['names_only', 'both'], true)) {
					$new_entity = $this->replace_text_value($new_entity, $search, $replace, $case_sensitive);
					if ($new_entity !== (string) $entity) {
						++$stats['entities_renamed'];
					}
				}

				if ($new_entity === '') {
					$new_entity = (string) $entity;
				}

				$new_items = $this->replace_in_items($items, $search, $replace, $scope, $case_sensitive, $stats);

				if (!isset($out[$section][$new_entity]) || !is_array($out[$section][$new_entity])) {
					$out[$section][$new_entity] = [];
				} elseif ($new_entity !== (string) $entity) {
					++$stats['entities_merged'];
				}

				$out[$section][$new_entity] = array_merge($out[$section][$new_entity], $new_items);
			}
		}

		return $out;
	}

	private function replace_in_items($items, $search, $replace, $scope, $case_sensitive, &$stats) {
		$updated = [];
		foreach ((array) $items as $item) {
			$original = (string) $item;
			$replaced = $original;
			if (in_array($scope, ['entries_only', 'both'], true)) {
				$replaced = $this->replace_text_value($original, $search, $replace, $case_sensitive);
				if ($replaced !== $original) {
					++$stats['entries_replaced'];
				}
			}
			$updated[] = $replaced;
		}

		return $updated;
	}

	private function replace_text_value($text, $search, $replace, $case_sensitive) {
		$text = (string) $text;
		$search = (string) $search;
		$replace = (string) $replace;

		if ($search === '') {
			return $text;
		}

		if ($case_sensitive) {
			return str_replace($search, $replace, $text);
		}

		return str_ireplace($search, $replace, $text);
	}

	private function normalize_entity_target_name($section, $entity, $target_entity) {
		$section = sanitize_text_field((string) $section);
		$entity = sanitize_text_field((string) $entity);
		$target_entity = sanitize_text_field((string) $target_entity);

		if (in_array($section, ['Notes', 'Relationships', 'NSFW'], true)) {
			return '';
		}

		if ($target_entity !== '') {
			return $target_entity;
		}

		return $entity;
	}

	private function maybe_rename_entity_key($cleaned, $payload) {
		$section = isset($payload['section']) ? sanitize_text_field((string) $payload['section']) : 'Characters';
		$entity = isset($payload['entity']) ? sanitize_text_field((string) $payload['entity']) : '';
		$target_entity = isset($payload['target_entity']) ? sanitize_text_field((string) $payload['target_entity']) : '';
		$action = isset($payload['action']) ? sanitize_key((string) $payload['action']) : 'replace';

		if ($action === 'delete' || $entity === '' || $target_entity === '' || $entity === $target_entity) {
			return $cleaned;
		}

		if (in_array($section, ['Notes', 'Relationships', 'NSFW'], true)) {
			return $cleaned;
		}

		if (!isset($cleaned[$section]) || !is_array($cleaned[$section]) || !isset($cleaned[$section][$entity])) {
			return $cleaned;
		}

		$source_entries = (array) $cleaned[$section][$entity];

		if (!isset($cleaned[$section][$target_entity]) || !is_array($cleaned[$section][$target_entity])) {
			// Target does not exist yet — simple rename.
			$cleaned[$section][$target_entity] = $source_entries;
		} else {
			// Target already exists — merge source entries into the target bucket
			// so no entries are lost. apply_entity_edit_payload will replace the
			// bucket with the textarea content immediately after this, so we set
			// the merged list as the baseline the editor content can build from.
			$cleaned[$section][$target_entity] = array_merge(
				(array) $cleaned[$section][$target_entity],
				$source_entries
			);
		}

		unset($cleaned[$section][$entity]);
		return $cleaned;
	}

	private function extract_entity_names_for_section($cleaned, $section) {
		if (!isset($cleaned[$section]) || !is_array($cleaned[$section])) {
			return [];
		}

		$names = [];
		foreach ($cleaned[$section] as $name => $items) {
			if (strpos((string) $name, '__') === 0) {
				continue;
			}

			$name = trim((string) $name);
			if ($name !== '') {
				$names[] = $name;
			}
		}

		sort($names, SORT_NATURAL | SORT_FLAG_CASE);
		return $names;
	}

	private function prune_version_files($expired_items, $kept_items) {
		$kept_paths = [];
		foreach ((array) $kept_items as $item) {
			if (!is_array($item) || empty($item['path'])) {
				continue;
			}
			$kept_paths[(string) $item['path']] = true;
		}

		foreach ((array) $expired_items as $item) {
			if (!is_array($item) || empty($item['path'])) {
				continue;
			}

			$path = (string) $item['path'];
			if (isset($kept_paths[$path])) {
				continue;
			}

			$versions_dir = trailingslashit(PMM_PLUGIN_DIR) . 'versions';
			if (strpos($path, $versions_dir) !== 0) {
				continue;
			}

			if (file_exists($path)) {
				@unlink($path);
			}
		}
	}

	private function start_processing_for_source_file($path, $source_filename, $mode, $format, $drop_sequences, $include_entity_report, $source_is_persistent) {
		$job_id = $this->generate_job_id();

		$state = [
			'job_id' => $job_id,
			'user_id' => get_current_user_id(),
			'stage' => 'parsing',
			'source_path' => $path,
			'source_is_persistent' => !empty($source_is_persistent),
			'source_filename' => $source_filename,
			'mode' => $mode,
			'format' => $format,
			'drop_sequences' => is_array($drop_sequences) ? $drop_sequences : [],
			'include_entity_report' => !empty($include_entity_report),
			'entity_report' => [
				'entities' => [],
				'new_entities' => [],
			],
			'output_filename' => $this->build_output_filename($source_filename, $format),
			'line_offset' => 0,
			'total_lines' => $this->count_file_lines($path),
			'line_batch_size' => $this->line_batch_size,
			'context' => [
				'section' => 'Notes',
				'entity' => null,
			],
			'parsed' => $this->empty_data_template(),
			'staged_raw_import_rows' => $this->get_staged_raw_import_rows(),
			'dedupe_queue' => [],
			'dedupe_index' => 0,
			'cleaned' => [],
			'counters' => $this->empty_counters_template(),
		];

		$this->save_job_state($job_id, $state);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_processing' => 1,
			'pmm_job' => $job_id,
		], admin_url('admin.php')));
		exit;
	}

	private function apply_staged_raw_import_rows($parsed, $rows) {
		foreach ((array) $rows as $row) {
			$section = isset($row['section']) ? trim((string) $row['section']) : 'Notes';
			$entity = isset($row['entity']) ? trim((string) $row['entity']) : '';
			$bullet = isset($row['bullet']) ? trim((string) $row['bullet']) : '';
			if ($bullet === '') {
				continue;
			}

			if (!isset($parsed[$section])) {
				$section = 'Notes';
			}

			if ($section === 'Notes' || $section === 'Relationships' || $section === 'NSFW' || $entity === '') {
				if (!isset($parsed[$section]['__entries__']) || !is_array($parsed[$section]['__entries__'])) {
					$parsed[$section]['__entries__'] = [];
				}
				$parsed[$section]['__entries__'][] = $bullet;
				continue;
			}

			if (!isset($parsed[$section][$entity]) || !is_array($parsed[$section][$entity])) {
				$parsed[$section][$entity] = [];
			}
			$parsed[$section][$entity][] = $bullet;
		}

		return $parsed;
	}

	private function empty_counters_template() {
		return [
			'kept_entries' => 0,
			'kept_ai_notes' => 0,
			'removed_empty' => 0,
			'removed_by_entity_rule' => 0,
			'removed_by_entry_rule' => 0,
			'removed_by_sequence' => 0,
			'removed_mundane_noise' => 0,
			'removed_nsfw_non_character' => 0,
			'removed_meta_trivial' => 0,
			'removed_aggressive_short' => 0,
			'removed_exact_duplicate' => 0,
			'removed_near_duplicate' => 0,
		];
	}

	private function merge_counters($base, $delta) {
		foreach ((array) $delta as $key => $value) {
			if (!isset($base[$key])) {
				$base[$key] = 0;
			}

			$base[$key] += (int) $value;
		}

		return $base;
	}

	private function counter_total($counters) {
		$keys = [
			'removed_empty',
			'removed_by_entity_rule',
			'removed_by_entry_rule',
			'removed_by_sequence',
			'removed_mundane_noise',
			'removed_nsfw_non_character',
			'removed_meta_trivial',
			'removed_aggressive_short',
			'removed_exact_duplicate',
			'removed_near_duplicate',
		];

		$total = 0;
		foreach ($keys as $key) {
			$total += isset($counters[$key]) ? (int) $counters[$key] : 0;
		}

		return $total;
	}

	private function filter_nsfw_without_character_refs($data) {
		if (empty($data['NSFW'])) {
			return [
				'data' => $data,
				'removed' => 0,
			];
		}

		$character_names = $this->build_specific_character_terms($data);

		if (empty($character_names)) {
			return [
				'data' => $data,
				'removed' => 0,
			];
		}

		$removed = 0;
		$keys = ['__entries__', '__unassigned__'];

		foreach ($keys as $key) {
			if (empty($data['NSFW'][$key]) || !is_array($data['NSFW'][$key])) {
				continue;
			}

			$kept = [];
			foreach ($data['NSFW'][$key] as $entry) {
				$entry = PMM_Utils::normalize_bullet((string) $entry);
				if ($entry === '') {
					++$removed;
					continue;
				}

				if ($this->entry_mentions_character($entry, $character_names)) {
					$kept[] = $entry;
				} else {
					++$removed;
				}
			}

			$data['NSFW'][$key] = $kept;
		}

		return [
			'data' => $data,
			'removed' => $removed,
		];
	}

	private function entry_mentions_character($entry, $character_names) {
		foreach ($character_names as $name) {
			if ($name === '') {
				continue;
			}

			$pattern = '/(^|\s)' . preg_quote($name, '/') . '(\s|$)/u';
			$entry_fp = PMM_Utils::fingerprint($entry);
			if ($entry_fp !== '' && preg_match($pattern, $entry_fp)) {
				return true;
			}
		}

		return false;
	}

	private function build_specific_character_terms($data) {
		if (empty($data['Characters']) || !is_array($data['Characters'])) {
			return [];
		}

		$stop_words = [
			'he', 'she', 'him', 'her', 'his', 'hers', 'they', 'them', 'their', 'theirs',
			'man', 'woman', 'boy', 'girl', 'male', 'female', 'person', 'someone', 'somebody',
			'partner', 'lover', 'spouse', 'friend',
		];

		$terms = [];
		foreach ($data['Characters'] as $entity => $items) {
			if (strpos((string) $entity, '__') === 0) {
				continue;
			}

			$name = PMM_Utils::fingerprint((string) $entity);
			if ($name === '' || strlen(str_replace(' ', '', $name)) < 3) {
				continue;
			}

			if (in_array($name, $stop_words, true)) {
				continue;
			}

			$terms[] = $name;
		}

		return array_values(array_unique($terms));
	}

	private function apply_entity_removal_rules($data) {
		$rules = get_option('pmm_entity_removal_rules', []);
		$rules = $this->normalize_entity_rule_items($rules);

		if (empty($rules)) {
			return [
				'data' => $data,
				'removed' => 0,
			];
		}

		$removed = 0;
		$entry_terms = [];

		foreach ($rules as $item) {
			$section = isset($item['section']) ? (string) $item['section'] : '';
			$name = isset($item['name']) ? (string) $item['name'] : '';
			if ($section === '' || $name === '') {
				continue;
			}

			$entry_terms[] = $name;

			if (empty($data[$section]) || !is_array($data[$section])) {
				continue;
			}

			foreach (array_keys($data[$section]) as $entity) {
				if (strpos((string) $entity, '__') === 0) {
					continue;
				}

				if (PMM_Utils::name_fingerprint((string) $entity) === PMM_Utils::name_fingerprint($name)) {
					$bucket = isset($data[$section][$entity]) && is_array($data[$section][$entity]) ? (array) $data[$section][$entity] : [];
					$removed += 1 + count($bucket);
					unset($data[$section][$entity]);
				}
			}
		}

		$entry_terms = array_values(array_unique(array_filter(array_map('trim', $entry_terms), static function($v) {
			return $v !== '';
		})));

		if (!empty($entry_terms)) {
			$sections = ['Relationships', 'NSFW', 'Notes', 'New Entries'];
			$keys = ['__entries__', '__unassigned__'];
			foreach ($sections as $section) {
				if (empty($data[$section]) || !is_array($data[$section])) {
					continue;
				}

				foreach ($keys as $key) {
					if (empty($data[$section][$key]) || !is_array($data[$section][$key])) {
						continue;
					}

					$kept = [];
					foreach ((array) $data[$section][$key] as $entry) {
						if ($this->entry_matches_any_removed_entity((string) $entry, $entry_terms)) {
							++$removed;
							continue;
						}
						$kept[] = $entry;
					}
					$data[$section][$key] = $kept;
				}
			}
		}

		return [
			'data' => $data,
			'removed' => $removed,
		];
	}

	private function entry_matches_any_removed_entity($entry, $names) {
		$mode = get_option('pmm_entity_related_match_mode', 'normal');
		$threshold = ($mode === 'strict') ? 0.95 : 0.85;

		foreach ((array) $names as $name) {
			if (PMM_Utils::contains_name_score((string) $entry, (string) $name) >= $threshold) {
				return true;
			}
		}

		return false;
	}

	private function build_entity_rule_key($section, $name) {
		return mb_strtolower(trim((string) $section)) . '|' . PMM_Utils::name_fingerprint((string) $name);
	}

	private function normalize_entity_rule_items($rows) {
		$out = [];
		foreach ((array) $rows as $key => $row) {
			$section = '';
			$name = '';

			if (is_array($row)) {
				$section = isset($row['section']) ? sanitize_text_field((string) $row['section']) : '';
				$name = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';
			} elseif (is_string($key) && is_string($row)) {
				$parts = explode('|', $key, 2);
				$section = isset($parts[0]) ? sanitize_text_field((string) $parts[0]) : '';
				$name = sanitize_text_field($row);
			}

			$section = trim((string) $section);
			$name = trim((string) $name);
			if ($section === '' || $name === '') {
				continue;
			}

			$rule_key = $this->build_entity_rule_key($section, $name);
			$out[$rule_key] = [
				'section' => $section,
				'name' => $name,
			];
		}

		return $out;
	}

	private function remap_entity_removal_rules($rules, $section, $from_name, $to_name) {
		$rules = $this->normalize_entity_rule_items($rules);
		$from_key = $this->build_entity_rule_key($section, $from_name);
		$to_key = $this->build_entity_rule_key($section, $to_name);
		$changed = false;

		if (!isset($rules[$from_key])) {
			return [
				'rules' => $rules,
				'changed' => false,
			];
		}

		if (!isset($rules[$to_key])) {
			$rules[$to_key] = [
				'section' => $section,
				'name' => $to_name,
			];
		}

		unset($rules[$from_key]);
		$changed = true;

		return [
			'rules' => $rules,
			'changed' => $changed,
		];
	}

	private function mark_output_rules_dirty() {
		update_option('pmm_output_rules_dirty', '1', false);
		update_option('pmm_output_rules_dirty_at', time(), false);
	}

	private function clear_output_rules_dirty() {
		update_option('pmm_output_rules_dirty', '0', false);
		update_option('pmm_output_rules_applied_at', time(), false);
	}

	private function similarity_threshold_defaults() {
		return [
			'characters' => 0.62,
			'organizations' => 0.70,
			'locations' => 0.66,
			'technology' => 0.72,
		];
	}

	private function get_similarity_thresholds_option() {
		$defaults = $this->similarity_threshold_defaults();
		$stored = get_option('pmm_similarity_thresholds', []);
		if (!is_array($stored)) {
			$stored = [];
		}

		$out = $defaults;
		foreach ($defaults as $key => $value) {
			if (!isset($stored[$key])) {
				continue;
			}
			$out[$key] = min(0.98, max(0.40, (float) $stored[$key]));
		}

		return $out;
	}

	private function read_similarity_thresholds_from_request($request) {
		$defaults = $this->similarity_threshold_defaults();
		if (!is_array($request)) {
			return $this->get_similarity_thresholds_option();
		}

		$map = [
			'characters' => 'pmm_similarity_threshold_characters',
			'organizations' => 'pmm_similarity_threshold_organizations',
			'locations' => 'pmm_similarity_threshold_locations',
			'technology' => 'pmm_similarity_threshold_technology',
		];

		$current = $this->get_similarity_thresholds_option();
		foreach ($map as $key => $field) {
			if (!isset($request[$field])) {
				continue;
			}
			$value = (float) wp_unslash((string) $request[$field]);
			$current[$key] = min(0.98, max(0.40, $value));
		}

		foreach ($defaults as $key => $default) {
			if (!isset($current[$key])) {
				$current[$key] = $default;
			}
		}

		return $current;
	}

	private function questionable_settings_defaults() {
		return [
			'min_words' => 4,
			'min_chars' => 18,
			'custom_terms' => [],
		];
	}

	private function get_questionable_settings_option() {
		$defaults = $this->questionable_settings_defaults();
		$stored = get_option('pmm_questionable_settings', []);
		if (!is_array($stored)) {
			$stored = [];
		}

		$settings = $defaults;
		$settings['min_words'] = isset($stored['min_words']) ? max(2, min(12, (int) $stored['min_words'])) : $defaults['min_words'];
		$settings['min_chars'] = isset($stored['min_chars']) ? max(8, min(80, (int) $stored['min_chars'])) : $defaults['min_chars'];
		$settings['custom_terms'] = isset($stored['custom_terms']) ? $this->sanitize_drop_sequences($stored['custom_terms']) : [];

		return $settings;
	}

	private function read_questionable_settings_from_request($request) {
		if (!is_array($request)) {
			return $this->get_questionable_settings_option();
		}

		$current = $this->get_questionable_settings_option();
		if (isset($request['pmm_questionable_min_words'])) {
			$current['min_words'] = max(2, min(12, (int) wp_unslash((string) $request['pmm_questionable_min_words'])));
		}
		if (isset($request['pmm_questionable_min_chars'])) {
			$current['min_chars'] = max(8, min(80, (int) wp_unslash((string) $request['pmm_questionable_min_chars'])));
		}
		if (isset($request['pmm_questionable_terms'])) {
			$current['custom_terms'] = $this->sanitize_drop_sequences(wp_unslash((string) $request['pmm_questionable_terms']));
		}

		return $current;
	}

	private function build_entity_report_payload($state) {
		$final_entities = $this->extract_entities_by_section($state['cleaned']);
		$new_entities = isset($state['entity_report']['new_entities']) && is_array($state['entity_report']['new_entities']) ? $state['entity_report']['new_entities'] : [];
		$similar_total_found = 0;
		$similar_truncated = false;
		$similar_candidates = $this->build_similar_entity_candidates($final_entities, $similar_total_found, $similar_truncated);
		$questionable_total_found = 0;
		$questionable_entries = $this->build_questionable_entry_candidates($state['cleaned'], $questionable_total_found);

		return [
			'entities' => $final_entities,
			'new_entities' => $new_entities,
			'similar_candidates' => $similar_candidates,
			'similar_candidates_total_found' => (int) $similar_total_found,
			'similar_candidates_truncated' => $similar_truncated ? 1 : 0,
			'questionable_entries' => $questionable_entries,
			'questionable_entries_total_found' => (int) $questionable_total_found,
		];
	}

	private function extract_entities_by_section($data) {
		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems'];
		$out = [];

		foreach ($sections as $section) {
			$out[$section] = [];
			if (empty($data[$section]) || !is_array($data[$section])) {
				continue;
			}

			foreach ($data[$section] as $entity => $items) {
				if (strpos((string) $entity, '__') === 0) {
					continue;
				}

				$name = trim((string) $entity);
				if ($name !== '') {
					$out[$section][] = $name;
				}
			}

			sort($out[$section], SORT_NATURAL | SORT_FLAG_CASE);
		}

		return $out;
	}

	private function build_similar_entity_candidates($entity_groups, &$total_found = 0, &$was_truncated = false) {
		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems'];
		$ignored = get_option('pmm_similarity_ignored_pairs', []);
		if (!is_array($ignored)) {
			$ignored = [];
		}

		$alias_rules = get_option('pmm_alias_rules', []);
		if (!is_array($alias_rules)) {
			$alias_rules = [];
		}

		$candidates = [];
		$pairs_scanned = 0;
		$max_pairs = max(1000, (int) $this->similarity_max_pairs_per_run);
		$max_entities = max(50, (int) $this->similarity_max_entities_per_section);
		$was_truncated = false;

		foreach ($sections as $section) {
			$names = isset($entity_groups[$section]) && is_array($entity_groups[$section]) ? array_values($entity_groups[$section]) : [];
			if (count($names) > $max_entities) {
				$names = array_slice($names, 0, $max_entities);
				$was_truncated = true;
			}
			$threshold = $this->similarity_threshold_for_section($section);
			$count = count($names);
			for ($i = 0; $i < $count; $i++) {
				for ($j = $i + 1; $j < $count; $j++) {
					if ($pairs_scanned >= $max_pairs) {
						$was_truncated = true;
						break 3;
					}
					++$pairs_scanned;

					$a = (string) $names[$i];
					$b = (string) $names[$j];

					$pair_key = $this->build_similarity_pair_key($section, $a, $b);
					if (isset($ignored[$pair_key])) {
						continue;
					}

					$resolved_a = $this->resolve_alias_name($a, $alias_rules);
					$resolved_b = $this->resolve_alias_name($b, $alias_rules);
					if ($resolved_a !== '' && $resolved_a === $resolved_b) {
						continue;
					}

					$match = $this->score_similarity_pair($a, $b);
					if ($match['score'] < $threshold) {
						continue;
					}

					$canonical = $this->choose_canonical_name($a, $b);
					$candidates[] = [
						'id' => md5($section . '|' . $a . '|' . $b),
						'section' => $section,
						'a' => $a,
						'b' => $b,
						'score' => $match['score'],
						'score_percent' => (int) floor($match['score'] * 100),
						'reason' => $match['reason'],
						'suggested_action' => 'alias',
						'suggested_canonical' => $canonical,
					];
				}
			}
		}

		usort($candidates, static function($x, $y) {
			if ((float) $x['score'] === (float) $y['score']) {
				return strcmp((string) $x['a'], (string) $y['a']);
			}
			return ((float) $x['score'] > (float) $y['score']) ? -1 : 1;
		});

		$total_found = count($candidates);

		return array_slice($candidates, 0, 120);
	}

	private function score_similarity_pair($a, $b) {
		$fp_a = PMM_Utils::name_fingerprint($a);
		$fp_b = PMM_Utils::name_fingerprint($b);

		if ($fp_a === '' || $fp_b === '') {
			return [
				'score' => 0.0,
				'reason' => 'empty',
			];
		}

		if ($fp_a === $fp_b) {
			return [
				'score' => 1.0,
				'reason' => 'normalized exact match',
			];
		}

		$jaccard = PMM_Utils::jaccard_similarity($fp_a, $fp_b);
		$set_boost = $this->token_subset_boost($fp_a, $fp_b);
		$max_len = max(strlen($fp_a), strlen($fp_b));
		$lev_ratio = 0.0;
		$levenshtein_distance = 99;
		if ($max_len > 0) {
			$levenshtein_distance = levenshtein($fp_a, $fp_b);
			$lev_ratio = max(0.0, 1.0 - ($levenshtein_distance / $max_len));
		}

		$typo_boost = 0.0;
		if ($max_len >= 5 && $levenshtein_distance === 1) {
			$typo_boost = 0.93;
		}

		$token_boost = 0.0;
		$token_reason = '';
		$tokens_a = preg_split('/\s+/u', $fp_a);
		$tokens_b = preg_split('/\s+/u', $fp_b);

		$first_a = isset($tokens_a[0]) ? $tokens_a[0] : '';
		$first_b = isset($tokens_b[0]) ? $tokens_b[0] : '';
		$last_a = !empty($tokens_a) ? $tokens_a[count($tokens_a) - 1] : '';
		$last_b = !empty($tokens_b) ? $tokens_b[count($tokens_b) - 1] : '';

		if ($last_a !== '' && $last_b !== '' && $last_a === $last_b) {
			if ($first_a !== '' && $first_b !== '' && $this->prefix_similarity($first_a, $first_b) >= 0.6) {
				$token_boost = 0.92;
				$token_reason = 'surname + forename overlap';
			} elseif ($first_a !== '' && $first_b !== '' && substr($first_a, 0, 1) === substr($first_b, 0, 1)) {
				$token_boost = 0.86;
				$token_reason = 'surname + initial overlap';
			}
		}

		if ($token_boost === 0.0 && $first_a !== '' && $first_b !== '') {
			$prefix = $this->prefix_similarity($first_a, $first_b);
			if ($prefix >= 0.75 && ($last_a === '' || $last_b === '' || $last_a === $last_b)) {
				$token_boost = 0.82;
				$token_reason = 'strong name prefix overlap';
			}
		}

		$stem_a = $this->normalize_similarity_tokens($fp_a);
		$stem_b = $this->normalize_similarity_tokens($fp_b);
		$stem_jaccard = PMM_Utils::jaccard_similarity($stem_a, $stem_b);

		$score = max((float) $jaccard, (float) $lev_ratio, (float) $token_boost, (float) $set_boost, (float) $stem_jaccard, (float) $typo_boost);
		$reason = 'high token overlap';
		if ($score === (float) $lev_ratio) {
			$reason = 'high spelling similarity';
		}
		if ($score === (float) $token_boost && $token_reason !== '') {
			$reason = $token_reason;
		}
		if ($score === (float) $set_boost && $set_boost > 0.0) {
			$reason = 'name token subset match';
		}
		if ($score === (float) $stem_jaccard && $stem_jaccard > 0.0) {
			$reason = 'normalized token overlap';
		}
		if ($score === (float) $typo_boost && $typo_boost > 0.0) {
			$reason = 'one-letter spelling variant';
		}

		return [
			'score' => $score,
			'reason' => $reason,
		];
	}

	private function similarity_threshold_for_section($section) {
		$thresholds = $this->get_similarity_thresholds_option();
		if ($section === 'Characters') {
			return (float) $thresholds['characters'];
		}

		if ($section === 'Locations') {
			return (float) $thresholds['locations'];
		}

		if ($section === 'Organizations') {
			return (float) $thresholds['organizations'];
		}

		return (float) $thresholds['technology'];
	}

	private function build_questionable_entry_candidates($data, &$total_found = 0) {
		$settings = $this->get_questionable_settings_option();
		$hidden = get_option('pmm_questionable_hidden_entries', []);
		$hidden = $this->normalize_entry_rule_items($hidden);
		$removed = get_option('pmm_entry_removal_rules', []);
		$removed = $this->normalize_entry_rule_items($removed);

		$candidates = [];
		foreach ((array) $data as $section => $content) {
			if (!is_array($content)) {
				continue;
			}

			foreach ($content as $entity => $items) {
				if (!is_array($items)) {
					continue;
				}

				$entity_name = (strpos((string) $entity, '__') === 0) ? '' : (string) $entity;
				foreach ($items as $entry) {
					$entry = PMM_Utils::normalize_bullet((string) $entry);
					if ($entry === '') {
						continue;
					}

					$key = $this->build_entry_rule_key((string) $section, $entity_name, $entry);
					if (isset($hidden[$key]) || isset($removed[$key])) {
						continue;
					}

					$flags = $this->questionable_entry_flags($entry, $settings);
					if (empty($flags)) {
						continue;
					}

					$candidates[] = [
						'id' => md5($key),
						'section' => (string) $section,
						'entity' => $entity_name,
						'entry' => $entry,
						'reasons' => implode(', ', $flags),
						'flag_count' => count($flags),
					];
				}
			}
		}

		usort($candidates, static function($a, $b) {
			if ((int) $a['flag_count'] === (int) $b['flag_count']) {
				return strcmp((string) $a['entry'], (string) $b['entry']);
			}
			return ((int) $a['flag_count'] > (int) $b['flag_count']) ? -1 : 1;
		});

		$total_found = count($candidates);

		return array_slice($candidates, 0, 120);
	}

	private function questionable_entry_flags($entry, $settings) {
		$flags = [];
		$words = preg_split('/\s+/u', trim((string) $entry));
		$word_count = is_array($words) ? count(array_filter($words, static function($v) {
			return $v !== '';
		})) : 0;

		if ($word_count > 0 && $word_count < (int) $settings['min_words']) {
			$flags[] = 'short entry';
		}

		if (mb_strlen((string) $entry) < (int) $settings['min_chars']) {
			$flags[] = 'low detail';
		}

		$entry_lower = mb_strtolower((string) $entry);
		$vague_patterns = [
			'/\b(?:something|someone|somewhere|stuff|thing|things|etc|misc|unknown|tbd|n\/a|idk|maybe)\b/u',
			'/\?{2,}/u',
			'/\b(?:good|nice|bad|cool|fine)\b$/u',
		];

		foreach ($vague_patterns as $pattern) {
			if (preg_match($pattern, $entry_lower)) {
				$flags[] = 'ambiguous wording';
				break;
			}
		}

		$custom_terms = isset($settings['custom_terms']) ? (array) $settings['custom_terms'] : [];
		foreach ($custom_terms as $term) {
			$term = mb_strtolower(trim((string) $term));
			if ($term === '') {
				continue;
			}
			if (mb_strpos($entry_lower, $term) !== false) {
				$flags[] = 'matches custom questionable term';
				break;
			}
		}

		return array_values(array_unique($flags));
	}

	private function apply_entry_removal_rules($data) {
		$rules = get_option('pmm_entry_removal_rules', []);
		$rules = $this->normalize_entry_rule_items($rules);
		if (empty($rules)) {
			return [
				'data' => $data,
				'removed' => 0,
			];
		}

		$removed = 0;
		foreach ((array) $data as $section => $content) {
			if (!is_array($content)) {
				continue;
			}

			foreach ($content as $entity => $items) {
				if (!is_array($items)) {
					continue;
				}

				$entity_name = (strpos((string) $entity, '__') === 0) ? '' : (string) $entity;
				$kept = [];
				foreach ($items as $entry) {
					$entry = PMM_Utils::normalize_bullet((string) $entry);
					if ($entry === '') {
						continue;
					}

					$key = $this->build_entry_rule_key((string) $section, $entity_name, $entry);
					if (isset($rules[$key])) {
						++$removed;
						continue;
					}

					$kept[] = $entry;
				}

				$data[$section][$entity] = $kept;
			}
		}

		return [
			'data' => $data,
			'removed' => $removed,
		];
	}

	private function move_entity_bucket_between_sections(&$cleaned, $from_section, $to_section, $entity_name) {
		$from_section = trim((string) $from_section);
		$to_section = trim((string) $to_section);
		$entity_name = trim((string) $entity_name);
		if ($from_section === '' || $to_section === '' || $entity_name === '') {
			return false;
		}
		if ($from_section === $to_section) {
			return false;
		}
		if (!isset($cleaned[$from_section]) || !is_array($cleaned[$from_section]) || !isset($cleaned[$from_section][$entity_name]) || !is_array($cleaned[$from_section][$entity_name])) {
			return false;
		}
		if (!isset($cleaned[$to_section]) || !is_array($cleaned[$to_section])) {
			$cleaned[$to_section] = [];
		}
		if (!isset($cleaned[$to_section][$entity_name]) || !is_array($cleaned[$to_section][$entity_name])) {
			$cleaned[$to_section][$entity_name] = [];
		}

		$cleaned[$to_section][$entity_name] = array_merge((array) $cleaned[$to_section][$entity_name], (array) $cleaned[$from_section][$entity_name]);
		unset($cleaned[$from_section][$entity_name]);
		return true;
	}

	private function move_entry_in_cleaned(&$cleaned, $from_section, $from_entity, $from_entry, $to_section, $to_entity, $to_entry) {
		$from_section = trim((string) $from_section);
		$from_entity = trim((string) $from_entity);
		$from_entry = PMM_Utils::normalize_bullet((string) $from_entry);
		$to_section = trim((string) $to_section);
		$to_entity = trim((string) $to_entity);
		$to_entry = PMM_Utils::normalize_bullet((string) $to_entry);

		if ($from_section === '' || $to_section === '' || $from_entry === '' || $to_entry === '') {
			return false;
		}

		if (!isset($cleaned[$from_section]) || !is_array($cleaned[$from_section])) {
			return false;
		}

		$from_key = in_array($from_section, ['Notes', 'Relationships', 'NSFW'], true) || $from_entity === '' ? '__entries__' : $from_entity;
		if (!isset($cleaned[$from_section][$from_key]) || !is_array($cleaned[$from_section][$from_key])) {
			return false;
		}

		$source_items = (array) $cleaned[$from_section][$from_key];
		$target_entry_fp = PMM_Utils::fingerprint($from_entry);
		$removed = false;
		$new_source = [];
		foreach ($source_items as $item) {
			$item_text = PMM_Utils::normalize_bullet((string) $item);
			if (!$removed && PMM_Utils::fingerprint($item_text) === $target_entry_fp) {
				$removed = true;
				continue;
			}
			$new_source[] = $item_text;
		}

		if (!$removed) {
			return false;
		}

		$cleaned[$from_section][$from_key] = $new_source;
		if (empty($new_source) && strpos((string) $from_key, '__') !== 0) {
			unset($cleaned[$from_section][$from_key]);
		}

		if (!isset($cleaned[$to_section]) || !is_array($cleaned[$to_section])) {
			$cleaned[$to_section] = [];
		}
		$to_key = in_array($to_section, ['Notes', 'Relationships', 'NSFW'], true) || $to_entity === '' ? '__entries__' : $to_entity;
		if (!isset($cleaned[$to_section][$to_key]) || !is_array($cleaned[$to_section][$to_key])) {
			$cleaned[$to_section][$to_key] = [];
		}

		$existing = (array) $cleaned[$to_section][$to_key];
		$to_fp = PMM_Utils::fingerprint($to_entry);
		foreach ($existing as $item) {
			if (PMM_Utils::fingerprint((string) $item) === $to_fp) {
				return true;
			}
		}

		$cleaned[$to_section][$to_key][] = $to_entry;
		return true;
	}

	private function build_entry_rule_key($section, $entity, $entry) {
		$section_key = mb_strtolower(trim((string) $section));
		$entity_key = trim((string) $entity) === '' ? '__section__' : PMM_Utils::name_fingerprint((string) $entity);
		$entry_fp = PMM_Utils::fingerprint((string) $entry);
		return $section_key . '|' . $entity_key . '|' . md5($entry_fp);
	}

	private function normalize_entry_rule_items($rows) {
		$out = [];
		foreach ((array) $rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$section = isset($row['section']) ? sanitize_text_field((string) $row['section']) : '';
			$entity = isset($row['entity']) ? sanitize_text_field((string) $row['entity']) : '';
			$entry = isset($row['entry']) ? sanitize_text_field((string) $row['entry']) : '';

			$section = trim((string) $section);
			$entry = PMM_Utils::normalize_bullet((string) $entry);
			if ($section === '' || $entry === '') {
				continue;
			}

			$key = $this->build_entry_rule_key($section, $entity, $entry);
			$out[$key] = [
				'section' => $section,
				'entity' => trim((string) $entity),
				'entry' => $entry,
			];
		}

		return $out;
	}

	private function token_subset_boost($fp_a, $fp_b) {
		$tokens_a = array_values(array_filter(preg_split('/\s+/u', (string) $fp_a), static function($t) {
			return $t !== '';
		}));
		$tokens_b = array_values(array_filter(preg_split('/\s+/u', (string) $fp_b), static function($t) {
			return $t !== '';
		}));

		if (empty($tokens_a) || empty($tokens_b)) {
			return 0.0;
		}

		$set_a = array_fill_keys($tokens_a, true);
		$set_b = array_fill_keys($tokens_b, true);
		$intersection = array_intersect_key($set_a, $set_b);

		$cov_a = count($intersection) / max(count($set_a), 1);
		$cov_b = count($intersection) / max(count($set_b), 1);
		$max_cov = max($cov_a, $cov_b);

		$short = (count($tokens_a) <= count($tokens_b)) ? $tokens_a : $tokens_b;
		$short_text = implode(' ', $short);
		$short_len = strlen(str_replace(' ', '', $short_text));

		if ($max_cov >= 1.0 && $short_len >= 3) {
			return 0.90;
		}

		if ($max_cov >= 0.8 && $short_len >= 4) {
			return 0.82;
		}

		return 0.0;
	}

	private function normalize_similarity_tokens($text) {
		$tokens = array_values(array_filter(preg_split('/\s+/u', (string) $text), static function($t) {
			return $t !== '';
		}));

		$map = [
			'labs' => 'lab',
			'laboratory' => 'lab',
			'laboratories' => 'lab',
			'corp' => 'corporation',
			'co' => 'corporation',
			'company' => 'corporation',
			'tech' => 'technology',
			'techs' => 'technology',
			'systems' => 'system',
			'suite' => 'room',
			'tower' => 'building',
			'penthouse' => 'residence',
			'marina' => 'harbor',
			'harbour' => 'harbor',
		];

		$normalized = [];
		foreach ($tokens as $token) {
			$token = isset($map[$token]) ? $map[$token] : $token;
			$normalized[] = $token;
		}

		return implode(' ', $normalized);
	}

	private function prefix_similarity($a, $b) {
		$a = (string) $a;
		$b = (string) $b;
		$min = min(strlen($a), strlen($b));
		if ($min === 0) {
			return 0.0;
		}

		$common = 0;
		for ($i = 0; $i < $min; $i++) {
			if ($a[$i] !== $b[$i]) {
				break;
			}
			$common++;
		}

		return $common / $min;
	}

	private function resolve_alias_name($name, $alias_rules) {
		$name = trim((string) $name);
		if ($name === '') {
			return '';
		}

		$lookup = $this->build_alias_lookup($alias_rules);
		$seen = [];
		$current = $name;
		for ($i = 0; $i < 5; $i++) {
			$key_a = mb_strtolower($current);
			$key_b = PMM_Utils::name_fingerprint($current);

			$next = '';
			if (isset($lookup[$key_b])) {
				$next = (string) $lookup[$key_b];
			} elseif (isset($lookup[$key_a])) {
				$next = (string) $lookup[$key_a];
			}

			if ($next === '' || isset($seen[$next])) {
				break;
			}

			$seen[$current] = true;
			$current = $next;
		}

		return $current;
	}

	private function build_alias_lookup($alias_rules) {
		$lookup = [];
		foreach ((array) $alias_rules as $source => $canonical) {
			$source = trim((string) $source);
			$canonical = trim((string) $canonical);
			if ($source === '' || $canonical === '') {
				continue;
			}

			$lookup[mb_strtolower($source)] = $canonical;
			$fp = PMM_Utils::name_fingerprint($source);
			if ($fp !== '') {
				$lookup[$fp] = $canonical;
			}
		}

		return $lookup;
	}

	private function build_similarity_pair_key($section, $a, $b) {
		$left = PMM_Utils::name_fingerprint($a);
		$right = PMM_Utils::name_fingerprint($b);
		$pair = [$left, $right];
		sort($pair, SORT_STRING);
		return mb_strtolower(trim((string) $section)) . '|' . $pair[0] . '|' . $pair[1];
	}

	private function choose_canonical_name($a, $b) {
		$score_a = strlen(trim((string) $a)) + substr_count((string) $a, ' ');
		$score_b = strlen(trim((string) $b)) + substr_count((string) $b, ' ');

		if ($score_a === $score_b) {
			return strcmp((string) $a, (string) $b) <= 0 ? (string) $a : (string) $b;
		}

		return ($score_a >= $score_b) ? (string) $a : (string) $b;
	}

	private function start_reprocess_from_last_output() {
		$data = $this->get_last_output_data_for_editing();
		if (empty($data['content'])) {
			return false;
		}

		$content = (string) $data['content'];
		$mode = isset($data['stats']['mode']) ? (string) $data['stats']['mode'] : 'balanced';
		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'md';
		$filename = isset($data['stats']['original_filename']) ? (string) $data['stats']['original_filename'] : 'memory.txt';

		$drop_sequences = get_option('pmm_drop_sequences', []);
		if (!is_array($drop_sequences)) {
			$drop_sequences = [];
		}

		$include_entity_report = get_option('pmm_include_entity_report', '0') === '1';

		$job_id = $this->generate_job_id();
		$source_path = $this->build_job_source_path($job_id, 'txt');

		if (!wp_mkdir_p(dirname($source_path))) {
			return false;
		}

		if (file_put_contents($source_path, $content) === false) {
			return false;
		}

		$state = [
			'job_id' => $job_id,
			'user_id' => get_current_user_id(),
			'stage' => 'parsing',
			'source_path' => $source_path,
			'source_filename' => $filename,
			'mode' => $mode,
			'format' => $format,
			'drop_sequences' => $drop_sequences,
			'include_entity_report' => $include_entity_report,
			'entity_report' => [
				'entities' => [],
				'new_entities' => [],
			],
			'output_filename' => $this->build_output_filename($filename, $format),
			'line_offset' => 0,
			'total_lines' => $this->count_file_lines($source_path),
			'line_batch_size' => $this->line_batch_size,
			'context' => [
				'section' => 'Notes',
				'entity' => null,
			],
			'parsed' => $this->empty_data_template(),
			'staged_raw_import_rows' => $this->get_staged_raw_import_rows(),
			'dedupe_queue' => [],
			'dedupe_index' => 0,
			'cleaned' => [],
			'counters' => $this->empty_counters_template(),
		];

		$this->save_job_state($job_id, $state);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_processing' => 1,
			'pmm_job' => $job_id,
			'pmm_reprocessed' => 1,
		], admin_url('admin.php')));
		exit;
	}

	private function parse_alias_rules_text($text) {
		$lines = preg_split('/\r\n|\r|\n/u', (string) $text);
		$rules = [];

		foreach ((array) $lines as $line) {
			$line = trim((string) $line);
			if ($line === '') {
				continue;
			}

			$parts = [];
			if (strpos($line, '=>') !== false) {
				$parts = array_map('trim', explode('=>', $line, 2));
			} elseif (strpos($line, '=') !== false) {
				$parts = array_map('trim', explode('=', $line, 2));
			} elseif (strpos($line, "\t") !== false) {
				$parts = array_map('trim', explode("\t", $line, 2));
			}

			if (count($parts) !== 2) {
				continue;
			}

			$source = sanitize_text_field($parts[0]);
			$canonical = sanitize_text_field($parts[1]);
			if ($source !== '' && $canonical !== '') {
				$rules[$source] = $canonical;
			}
		}

		return $this->normalize_alias_rules($rules);
	}

	private function normalize_alias_rules($rules) {
		$out = [];
		foreach ((array) $rules as $source => $canonical) {
			$source = trim((string) $source);
			$canonical = trim((string) $canonical);
			if ($source === '' || $canonical === '') {
				continue;
			}
			$out[$source] = $canonical;
		}

		return $out;
	}

	private function append_similarity_log($rows) {
		if (empty($rows)) {
			return;
		}

		$existing = get_option('pmm_similarity_review_log', []);
		if (!is_array($existing)) {
			$existing = [];
		}

		$merged = array_merge($rows, $existing);
		$merged = array_slice($merged, 0, 200);
		update_option('pmm_similarity_review_log', $merged, false);
	}
}