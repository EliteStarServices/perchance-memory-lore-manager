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
		$this->maybe_migrate_default_format_to_txt();

		add_action('admin_menu', [$this, 'register_admin']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
		add_action('admin_post_pmm_process_upload', [$this, 'handle_upload']);
		add_action('admin_post_pmm_process_latest_version', [$this, 'process_latest_version']);
		add_action('admin_post_pmm_process_recent_version', [$this, 'process_recent_version']);
		add_action('admin_post_pmm_process_batch', [$this, 'process_batch']);
		add_action('admin_post_pmm_apply_similarity_review', [$this, 'apply_similarity_review']);
		add_action('admin_post_pmm_reset_similarity_review_queue', [$this, 'reset_similarity_review_queue']);
		add_action('admin_post_pmm_apply_entity_review', [$this, 'apply_entity_review']);
		add_action('admin_post_pmm_reset_entity_review_queue', [$this, 'reset_entity_review_queue']);
		add_action('admin_post_pmm_apply_questionable_review', [$this, 'apply_questionable_review']);
		add_action('admin_post_pmm_reset_questionable_review_queue', [$this, 'reset_questionable_review_queue']);
		add_action('admin_post_pmm_apply_reclassification_review', [$this, 'apply_reclassification_review']);
		add_action('admin_post_pmm_reset_reclassification_review_queue', [$this, 'reset_reclassification_review_queue']);
		add_action('admin_post_pmm_manage_hidden_entities', [$this, 'manage_hidden_entities']);
		add_action('admin_post_pmm_preview_raw_import', [$this, 'preview_raw_import']);
		add_action('admin_post_pmm_stage_raw_import', [$this, 'stage_raw_import']);
		add_action('admin_post_pmm_download_raw_import_rows', [$this, 'download_raw_import_rows']);
		add_action('admin_post_pmm_clear_raw_import_preview', [$this, 'clear_raw_import_preview']);
		add_action('admin_post_pmm_save_entity_update', [$this, 'save_entity_update']);
		add_action('admin_post_pmm_save_entity_bulk_update', [$this, 'save_entity_bulk_update']);
		add_action('admin_post_pmm_prune_entity_entries', [$this, 'prune_entity_entries']);
		add_action('admin_post_pmm_apply_prune_preview_review', [$this, 'apply_prune_preview_review']);
		add_action('admin_post_pmm_apply_prune_nonprefix_review', [$this, 'apply_prune_nonprefix_review']);
		add_action('admin_post_pmm_save_global_entity_report', [$this, 'save_global_entity_report']);
		add_action('admin_post_pmm_global_search_replace', [$this, 'global_search_replace']);
		add_action('admin_post_pmm_save_alias_rules', [$this, 'save_alias_rules']);
		add_action('admin_post_pmm_import_confirmed_entities', [$this, 'import_confirmed_entities']);
		add_action('admin_post_pmm_save_confirmed_entities_section', [$this, 'save_confirmed_entities_section']);
		add_action('admin_post_pmm_reprocess_last_output', [$this, 'reprocess_last_output']);
		add_action('admin_post_pmm_download_last_output', [$this, 'download_last_output']);
		add_action('admin_post_pmm_download_saved_version', [$this, 'download_saved_version']);
		add_action('admin_post_pmm_save_preview_content', [$this, 'save_preview_content']);
		add_action('wp_ajax_pmm_get_entities_for_section', [$this, 'ajax_get_entities_for_section']);
	}

	private function maybe_migrate_default_format_to_txt() {
		if (get_option('pmm_default_format_migrated_to_txt', '0') === '1') {
			return;
		}

		$current_format = (string) get_option('pmm_last_format', 'md');
		if ($current_format === 'md') {
			update_option('pmm_last_format', 'txt', false);
		}

		update_option('pmm_default_format_migrated_to_txt', '1', false);
	}

	public function ajax_get_entities_for_section() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'forbidden'], 403);
		}

		check_ajax_referer('pmm_get_entities_for_section', 'nonce');

		$section = isset($_REQUEST['section']) ? sanitize_text_field((string) wp_unslash($_REQUEST['section'])) : 'Characters';
		$valid_sections = $this->valid_sections();
		if (!in_array($section, $valid_sections, true)) {
			$section = 'Characters';
		}

		$data = get_transient('pmm_last_output_' . get_current_user_id());
		if (empty($data['content']) || !is_array($data)) {
			wp_send_json_success([
				'section' => $section,
				'entities' => [],
				'allows_section_entries' => in_array($section, $this->section_level_sections(), true),
			]);
		}

		$cleaned = $this->get_cleaned_data_from_last_output($data);
		$entities = $this->extract_entity_names_for_section($cleaned, $section);

		wp_send_json_success([
			'section' => $section,
			'entities' => $entities,
			'allows_section_entries' => in_array($section, $this->section_level_sections(), true),
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
		$format = isset($_POST['pmm_format']) ? sanitize_text_field(wp_unslash($_POST['pmm_format'])) : 'txt';
		$drop_sequences = isset($_POST['pmm_drop_sequences']) ? $this->sanitize_drop_sequences(wp_unslash($_POST['pmm_drop_sequences'])) : [];
		$include_entity_report = true;
		$rescan_sections = !empty($_POST['pmm_rescan_sections']) ? 1 : 0;
		$rescan_confidence = isset($_POST['pmm_rescan_confidence']) ? max(70, min(98, (int) wp_unslash((string) $_POST['pmm_rescan_confidence']))) : (int) get_option('pmm_rescan_confidence', 84);
		$rescan_preview_only = !empty($_POST['pmm_rescan_preview_only']) ? 1 : 0;
		$similarity_thresholds = $this->read_similarity_thresholds_from_request($_POST);
		$classification_settings = $this->read_classification_settings_from_request($_POST);
		$global_entity_report_settings = $this->read_global_entity_report_settings_from_request($_POST);
		$questionable_settings = $this->read_questionable_settings_from_request($_POST);
		$entity_related_match_mode = isset($_POST['pmm_entity_related_match_mode']) ? sanitize_key((string) wp_unslash($_POST['pmm_entity_related_match_mode'])) : 'normal';
		if (!in_array($entity_related_match_mode, ['normal', 'strict'], true)) {
			$entity_related_match_mode = 'normal';
		}
		update_option('pmm_drop_sequences', $drop_sequences, false);
		update_option('pmm_include_entity_report', '1', false);
		update_option('pmm_similarity_thresholds', $similarity_thresholds, false);
		update_option('pmm_classification_settings', $classification_settings, false);
		update_option('pmm_global_entity_report_settings', $global_entity_report_settings, false);
		update_option('pmm_questionable_settings', $questionable_settings, false);
		update_option('pmm_entity_related_match_mode', $entity_related_match_mode, false);
		update_option('pmm_rescan_confidence', $rescan_confidence, false);
		update_option('pmm_rescan_preview_only', $rescan_preview_only, false);
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
			'rescan_sections' => !empty($rescan_sections),
			'rescan_confidence' => $rescan_confidence,
			'rescan_preview_only' => !empty($rescan_preview_only),
			'entity_report' => [
				'entities' => [],
				'new_entities' => [],
			],
			'output_filename' => $this->build_output_filename($filename, $format),
			'line_offset' => 0,
			'total_lines' => $this->count_file_lines($source_path),
			'line_batch_size' => $this->line_batch_size,
			'context' => [
				'section' => 'New Entries',
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
		$rescan_sections = !empty($settings['rescan_sections']);
		$rescan_confidence = isset($settings['rescan_confidence']) ? (int) $settings['rescan_confidence'] : 84;
		$rescan_preview_only = !empty($settings['rescan_preview_only']);

		$this->start_processing_for_source_file($path, basename($path), $mode, $format, $drop_sequences, $include_entity_report, true, $rescan_sections, $rescan_confidence, $rescan_preview_only);
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
		$rescan_sections = !empty($settings['rescan_sections']);
		$rescan_confidence = isset($settings['rescan_confidence']) ? (int) $settings['rescan_confidence'] : 84;
		$rescan_preview_only = !empty($settings['rescan_preview_only']);

		$this->start_processing_for_source_file($path, $filename, $mode, $format, $drop_sequences, $include_entity_report, true, $rescan_sections, $rescan_confidence, $rescan_preview_only);
	}

	private function resolve_processing_settings_from_request() {
		$mode = isset($_POST['pmm_mode']) ? sanitize_text_field(wp_unslash($_POST['pmm_mode'])) : (string) get_option('pmm_last_mode', 'balanced');
		if (!in_array($mode, ['strict', 'balanced', 'aggressive'], true)) {
			$mode = 'balanced';
		}

		$format = isset($_POST['pmm_format']) ? sanitize_text_field(wp_unslash($_POST['pmm_format'])) : (string) get_option('pmm_last_format', 'txt');
		if (!in_array($format, ['md', 'txt'], true)) {
		$format = 'txt';
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
		$rescan_sections = isset($_POST['pmm_rescan_sections']) ? (!empty($_POST['pmm_rescan_sections']) ? 1 : 0) : 0;
		$rescan_confidence = isset($_POST['pmm_rescan_confidence']) ? max(70, min(98, (int) wp_unslash((string) $_POST['pmm_rescan_confidence']))) : ((int) get_option('pmm_rescan_confidence', 84));
		$rescan_preview_only = isset($_POST['pmm_rescan_preview_only']) ? (!empty($_POST['pmm_rescan_preview_only']) ? 1 : 0) : ((int) get_option('pmm_rescan_preview_only', 0));
		$similarity_thresholds = $this->read_similarity_thresholds_from_request(isset($_POST) ? $_POST : null);
		$classification_settings = $this->read_classification_settings_from_request(isset($_POST) ? $_POST : null);
		$global_entity_report_settings = $this->read_global_entity_report_settings_from_request(isset($_POST) ? $_POST : null);
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
		update_option('pmm_classification_settings', $classification_settings, false);
		update_option('pmm_global_entity_report_settings', $global_entity_report_settings, false);
		update_option('pmm_questionable_settings', $questionable_settings, false);
		update_option('pmm_entity_related_match_mode', $entity_related_match_mode, false);
		update_option('pmm_rescan_confidence', $rescan_confidence, false);
		update_option('pmm_rescan_preview_only', $rescan_preview_only, false);

		return [
			'mode' => $mode,
			'format' => $format,
			'drop_sequences' => $drop_sequences,
			'include_entity_report' => $include_entity_report,
			'rescan_sections' => !empty($rescan_sections),
			'rescan_confidence' => $rescan_confidence,
			'rescan_preview_only' => !empty($rescan_preview_only),
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

	public function download_saved_version() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_download_saved_version');

		$history = get_option('pmm_version_history', []);
		if (!is_array($history) || empty($history)) {
			wp_die(esc_html__('No saved version is available for download.', 'perchance-memory-manager'));
		}

		$index = isset($_REQUEST['pmm_version_index']) ? (int) wp_unslash((string) $_REQUEST['pmm_version_index']) : -1;
		if ($index < 0 || !isset($history[$index]) || !is_array($history[$index])) {
			wp_die(esc_html__('The requested saved version could not be found.', 'perchance-memory-manager'));
		}

		$item = $history[$index];
		$path = isset($item['path']) ? (string) $item['path'] : '';
		$filename = isset($item['filename']) ? sanitize_file_name((string) $item['filename']) : basename($path);
		if ($path === '' || !file_exists($path) || !is_readable($path)) {
			wp_die(esc_html__('The requested saved version file is missing.', 'perchance-memory-manager'));
		}

		$content = file_get_contents($path);
		if ($content === false) {
			wp_die(esc_html__('The requested saved version could not be read.', 'perchance-memory-manager'));
		}

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

		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'txt';
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

		$valid_sections = $this->valid_sections();
		$applied = 0;
		$reviewed = 0;
		$output_rule_changes = 0;
		$expected_count = isset($_POST['pmm_similarity_expected_count']) ? max(0, (int) wp_unslash((string) $_POST['pmm_similarity_expected_count'])) : 0;
		$dataset_stamp = isset($_POST['pmm_review_dataset_stamp']) ? max(0, (int) wp_unslash((string) $_POST['pmm_review_dataset_stamp'])) : 0;
		$queue_filter = isset($_POST['pmm_similarity_queue_filter']) ? sanitize_key((string) wp_unslash((string) $_POST['pmm_similarity_queue_filter'])) : 'pending';
		if (!in_array($queue_filter, ['pending', 'reviewed', 'all'], true)) {
			$queue_filter = 'pending';
		}
		$queue = $this->get_similarity_review_queue();
		$log_rows = [];
		$removals = get_option('pmm_entity_removal_rules', []);
		$removals = $this->normalize_entity_rule_items($removals);
		$data = $this->get_last_output_data_for_editing();
		$cleaned = [];
		$cleaned_changed = false;
		foreach ($rows as $row_id => $row) {
			if (!is_array($row)) {
				continue;
			}

			$row_id = sanitize_key((string) $row_id);
			if ($row_id === '') {
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

			++$reviewed;
			$queue[$row_id] = [
				'status' => 'reviewed',
				'action' => $action,
				'stamp' => $dataset_stamp,
				'updated_at' => time(),
			];

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
		$this->set_similarity_review_queue($queue);
		$this->append_similarity_log($log_rows);
		if ($output_rule_changes > 0) {
			$this->mark_output_rules_dirty();
		}

		if ($cleaned_changed && !empty($data['content']) && isset($data['stats']) && is_array($data['stats']) && !empty($cleaned) && is_array($cleaned)) {
			$renderer = new PMM_Renderer();
			$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'txt';
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
			'pmm_similarity_queue_filter' => (string) $queue_filter,
			'pmm_similarity_saved' => (string) $applied,
			'pmm_similarity_reviewed' => (string) $reviewed,
			'pmm_similarity_truncated' => (string) (($expected_count > 0 && $reviewed > 0 && $reviewed < $expected_count) ? 1 : 0),
			'pmm_similarity_expected_count' => (string) $expected_count,
		], admin_url('admin.php')));
		exit;
	}

	public function reset_similarity_review_queue() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_reset_similarity_review_queue');
		update_option($this->similarity_review_queue_option_key(), [], false);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_similarity_queue_cleared' => 1,
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
		$exclusions_text = isset($_POST['pmm_first_name_alias_exclusions_text']) ? wp_unslash($_POST['pmm_first_name_alias_exclusions_text']) : '';
		$first_name_exclusions = $this->sanitize_drop_sequences($exclusions_text);
		update_option('pmm_alias_rules', $rules, false);
		update_option('pmm_first_name_alias_exclusions', $first_name_exclusions, false);

		$preview = $this->get_raw_import_preview_data();
		$preview_raw_text = isset($preview['raw_text']) ? trim((string) $preview['raw_text']) : '';
		if ($preview_raw_text !== '') {
			$previous_rows = isset($preview['rows']) && is_array($preview['rows']) ? array_values($preview['rows']) : [];
			$parser = new PMM_Parser();
			$refreshed_rows = $parser->preview_raw_import_rows($preview_raw_text, $this->build_existing_entity_seed_from_last_output());

			foreach ($refreshed_rows as $index => $row) {
				if (!isset($previous_rows[$index]) || !is_array($previous_rows[$index])) {
					continue;
				}

				$previous_row = $previous_rows[$index];
				$was_reviewed = !empty($previous_row['reviewed']);
				$was_removed = !empty($previous_row['removed']);
				if (!$was_removed) {
					$previous_bullet = isset($previous_row['bullet']) ? trim((string) $previous_row['bullet']) : '';
					$was_removed = ($previous_bullet === '');
				}

				if ($was_reviewed) {
					$refreshed_rows[$index]['reviewed'] = 1;
				}
				if ($was_removed) {
					$refreshed_rows[$index]['bullet'] = '';
					$refreshed_rows[$index]['removed'] = 1;
				}
			}

			$this->set_raw_import_preview_data([
				'raw_text' => $preview_raw_text,
				'rows' => $refreshed_rows,
			]);
		}

		$this->mark_output_rules_dirty();

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_alias_saved' => (string) count($rules),
		], admin_url('admin.php')));
		exit;
	}

	public function import_confirmed_entities() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_import_confirmed_entities');

		$section = isset($_POST['pmm_confirmed_section']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_confirmed_section'])) : 'Characters';
		if (!in_array($section, $this->entity_sections(), true)) {
			$section = 'Characters';
		}

		$text = isset($_POST['pmm_confirmed_entities_text']) ? (string) wp_unslash($_POST['pmm_confirmed_entities_text']) : '';
		$lines = preg_split('/\r\n|\r|\n/u', $text);
		$registry = $this->get_confirmed_entity_registry_option();
		$now = time();
		$imported = 0;
		$updated = 0;

		foreach ((array) $lines as $line) {
			$name = trim((string) $line);
			$name = preg_replace('/^[\-*•]\s+/u', '', (string) $name);
			$name = trim((string) $name);
			if ($name === '') {
				continue;
			}

			$fp = PMM_Utils::name_fingerprint($name);
			if ($fp === '') {
				continue;
			}

			if (!isset($registry[$section])) {
				$registry[$section] = [];
			}

			if (!isset($registry[$section][$fp])) {
				$registry[$section][$fp] = [
					'name' => $name,
					'seen_count' => 1,
					'last_seen' => $now,
				];
				++$imported;
				continue;
			}

			$existing = $registry[$section][$fp];
			$registry[$section][$fp] = [
				'name' => $this->pick_canonical_name(isset($existing['name']) ? (string) $existing['name'] : '', $name),
				'seen_count' => max(1, (int) (isset($existing['seen_count']) ? $existing['seen_count'] : 1)) + 1,
				'last_seen' => $now,
			];
			++$updated;
		}

		update_option('pmm_confirmed_entities_registry', $registry, false);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_confirmed_imported' => (string) $imported,
			'pmm_confirmed_updated' => (string) $updated,
			'pmm_confirmed_section' => (string) $section,
		], admin_url('admin.php')));
		exit;
	}

	public function save_confirmed_entities_section() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_save_confirmed_entities_section');

		$section = isset($_POST['pmm_confirmed_edit_section']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_confirmed_edit_section'])) : 'Characters';
		if (!in_array($section, $this->entity_sections(), true)) {
			$section = 'Characters';
		}

		$text = isset($_POST['pmm_confirmed_edit_entities_text']) ? (string) wp_unslash($_POST['pmm_confirmed_edit_entities_text']) : '';
		$lines = preg_split('/\r\n|\r|\n/u', $text);
		$registry = $this->get_confirmed_entity_registry_option();
		$current_section = isset($registry[$section]) && is_array($registry[$section]) ? $registry[$section] : [];
		$now = time();
		$next_section = [];

		foreach ((array) $lines as $line) {
			$name = trim((string) $line);
			$name = preg_replace('/^[\-*•]\s+/u', '', (string) $name);
			$name = trim((string) $name);
			if ($name === '') {
				continue;
			}

			$fp = PMM_Utils::name_fingerprint($name);
			if ($fp === '') {
				continue;
			}

			if (isset($next_section[$fp])) {
				$next_section[$fp]['name'] = $this->pick_canonical_name((string) $next_section[$fp]['name'], $name);
				continue;
			}

			if (isset($current_section[$fp]) && is_array($current_section[$fp])) {
				$existing = $current_section[$fp];
				$next_section[$fp] = [
					'name' => $this->pick_canonical_name(isset($existing['name']) ? (string) $existing['name'] : '', $name),
					'seen_count' => max(1, (int) (isset($existing['seen_count']) ? $existing['seen_count'] : 1)),
					'last_seen' => max(0, (int) (isset($existing['last_seen']) ? $existing['last_seen'] : $now)),
				];
				continue;
			}

			$next_section[$fp] = [
				'name' => $name,
				'seen_count' => 1,
				'last_seen' => $now,
			];
		}

		$registry[$section] = $next_section;
		update_option('pmm_confirmed_entities_registry', $registry, false);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_confirmed_saved' => (string) count($next_section),
			'pmm_confirmed_section' => (string) $section,
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
		$reviewed = 0;
		$output_rule_changes = 0;
		$expected_count = isset($_POST['pmm_entity_expected_count']) ? max(0, (int) wp_unslash((string) $_POST['pmm_entity_expected_count'])) : 0;
		$dataset_stamp = isset($_POST['pmm_review_dataset_stamp']) ? max(0, (int) wp_unslash((string) $_POST['pmm_review_dataset_stamp'])) : 0;
		$queue_filter = isset($_POST['pmm_entity_queue_filter']) ? sanitize_key((string) wp_unslash((string) $_POST['pmm_entity_queue_filter'])) : 'pending';
		if (!in_array($queue_filter, ['pending', 'reviewed', 'all'], true)) {
			$queue_filter = 'pending';
		}
		$queue = $this->get_entity_review_queue();
		foreach ($rows as $row_id => $row) {
			if (!is_array($row)) {
				continue;
			}

			$row_id = sanitize_key((string) $row_id);
			if ($row_id === '') {
				continue;
			}

			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$section = isset($row['section']) ? sanitize_text_field((string) $row['section']) : '';
			$name = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';

			if ($section === '' || $name === '') {
				continue;
			}

			++$reviewed;
			$queue[$row_id] = [
				'status' => 'reviewed',
				'action' => $action,
				'stamp' => $dataset_stamp,
				'updated_at' => time(),
			];

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
		$this->set_entity_review_queue($queue);
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
			'pmm_entity_queue_filter' => (string) $queue_filter,
			'pmm_entity_saved' => (string) $applied,
			'pmm_entity_reviewed' => (string) $reviewed,
			'pmm_entity_truncated' => (string) (($expected_count > 0 && $reviewed > 0 && $reviewed < $expected_count) ? 1 : 0),
			'pmm_entity_expected_count' => (string) $expected_count,
		], admin_url('admin.php')));
		exit;
	}

	public function reset_entity_review_queue() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_reset_entity_review_queue');
		update_option($this->entity_review_queue_option_key(), [], false);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_entity_queue_cleared' => 1,
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

		$valid_sections = $this->valid_sections();
		$applied = 0;
		$reviewed = 0;
		$changed = 0;
		$removed_count = 0;
		$hidden_count = 0;
		$kept_count = 0;
		$updated_entries = 0;
		$output_rule_changes = 0;
		$expected_count = isset($_POST['pmm_questionable_expected_count']) ? max(0, (int) wp_unslash((string) $_POST['pmm_questionable_expected_count'])) : 0;
		$dataset_stamp = isset($_POST['pmm_review_dataset_stamp']) ? max(0, (int) wp_unslash((string) $_POST['pmm_review_dataset_stamp'])) : 0;
		$bulk_apply = !empty($_POST['pmm_questionable_apply_bulk']);
		$bulk_action = isset($_POST['pmm_questionable_bulk_action']) ? sanitize_key((string) wp_unslash((string) $_POST['pmm_questionable_bulk_action'])) : '';
		if (!in_array($bulk_action, ['keep', 'hide', 'remove'], true)) {
			$bulk_action = '';
		}
		$queue_filter = isset($_POST['pmm_questionable_queue_filter']) ? sanitize_key((string) wp_unslash((string) $_POST['pmm_questionable_queue_filter'])) : 'pending';
		if (!in_array($queue_filter, ['pending', 'reviewed', 'all'], true)) {
			$queue_filter = 'pending';
		}
		$queue = $this->get_questionable_review_queue();
		$data = $this->get_last_output_data_for_editing();
		$cleaned = [];
		$cleaned_changed = false;
		foreach ($rows as $row_id => $row) {
			if (!is_array($row)) {
				continue;
			}

			$row_id = sanitize_key((string) $row_id);
			if ($row_id === '') {
				continue;
			}

			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			if ($bulk_apply && $bulk_action !== '') {
				$action = $bulk_action;
			}
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
			$queue[$row_id] = [
				'status' => 'reviewed',
				'action' => $action,
				'stamp' => $dataset_stamp,
				'updated_at' => time(),
			];

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
				if (!in_array($section, $this->section_level_sections(), true) && $target_entity === '') {
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
		$this->set_questionable_review_queue($queue);

		if ($cleaned_changed && !empty($data['content']) && isset($data['stats']) && is_array($data['stats'])) {
			$renderer = new PMM_Renderer();
			$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'txt';
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
			'pmm_questionable_queue_filter' => (string) $queue_filter,
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

	public function reset_questionable_review_queue() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_reset_questionable_review_queue');
		update_option($this->questionable_review_queue_option_key(), [], false);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_questionable_queue_cleared' => 1,
		], admin_url('admin.php')));
		exit;
	}

	public function apply_reclassification_review() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_apply_reclassification_review');

		$rows = isset($_POST['pmm_reclassification']) ? wp_unslash($_POST['pmm_reclassification']) : [];
		if (!is_array($rows)) {
			$rows = [];
		}

		$hidden = get_option('pmm_reclassification_hidden_entries', []);
		$hidden = $this->normalize_entry_rule_items($hidden);
		$data = $this->get_last_output_data_for_editing();
		$cleaned = [];
		$cleaned_changed = false;
		$reviewed = 0;
		$moved = 0;
		$hidden_count = 0;
		$kept_count = 0;
		$expected_count = isset($_POST['pmm_reclassification_expected_count']) ? max(0, (int) wp_unslash((string) $_POST['pmm_reclassification_expected_count'])) : 0;
		$dataset_stamp = isset($_POST['pmm_review_dataset_stamp']) ? max(0, (int) wp_unslash((string) $_POST['pmm_review_dataset_stamp'])) : 0;
		$queue_filter = isset($_POST['pmm_reclassification_queue_filter']) ? sanitize_key((string) wp_unslash((string) $_POST['pmm_reclassification_queue_filter'])) : 'pending';
		if (!in_array($queue_filter, ['pending', 'reviewed', 'all'], true)) {
			$queue_filter = 'pending';
		}
		$queue = $this->get_reclassification_review_queue();

		foreach ($rows as $row_id => $row) {
			if (!is_array($row)) {
				continue;
			}

			$row_id = sanitize_key((string) $row_id);
			if ($row_id === '') {
				continue;
			}

			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$original_section = isset($row['original_section']) ? sanitize_text_field((string) $row['original_section']) : '';
			$original_entity = isset($row['original_entity']) ? sanitize_text_field((string) $row['original_entity']) : '';
			$original_entry = isset($row['original_entry']) ? sanitize_textarea_field((string) $row['original_entry']) : '';
			$target_section = isset($row['target_section']) ? sanitize_text_field((string) $row['target_section']) : $original_section;
			$target_entity = isset($row['target_entity']) ? sanitize_text_field((string) $row['target_entity']) : $original_entity;

			if ($original_section === '' || $original_entry === '') {
				continue;
			}

			if (!in_array($target_section, $this->valid_sections(), true)) {
				$target_section = $original_section;
			}

			$key = $this->build_entry_rule_key($original_section, $original_entity, $original_entry);
			++$reviewed;
			$queue[$row_id] = [
				'status' => 'reviewed',
				'action' => $action,
				'stamp' => $dataset_stamp,
				'updated_at' => time(),
			];

			if ($action === 'hide') {
				$hidden[$key] = [
					'section' => $original_section,
					'entity' => $original_entity,
					'entry' => $original_entry,
				];
				++$hidden_count;
				continue;
			}

			if ($action !== 'move') {
				unset($hidden[$key]);
				++$kept_count;
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

			if (in_array($target_section, $this->section_level_sections(), true)) {
				$target_entity = '';
			} elseif ($target_entity === '') {
				$target_entity = $original_entity !== '' ? $original_entity : 'Unsorted Inbox';
			}

			$entry_changed = $this->move_entry_in_cleaned(
				$cleaned,
				$original_section,
				$original_entity,
				$original_entry,
				$target_section,
				$target_entity,
				$original_entry
			);

			if ($entry_changed) {
				$cleaned_changed = true;
				unset($hidden[$key]);
				++$moved;
			}
		}

		update_option('pmm_reclassification_hidden_entries', $this->normalize_entry_rule_items($hidden), false);
		$this->set_reclassification_review_queue($queue);

		if ($cleaned_changed && !empty($data['content']) && isset($data['stats']) && is_array($data['stats'])) {
			$renderer = new PMM_Renderer();
			$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'txt';
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

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_reclassification_queue_filter' => (string) $queue_filter,
			'pmm_reclassification_saved' => (string) ($moved + $hidden_count + $kept_count),
			'pmm_reclassification_reviewed' => (string) $reviewed,
			'pmm_reclassification_moved' => (string) $moved,
			'pmm_reclassification_hidden' => (string) $hidden_count,
			'pmm_reclassification_kept' => (string) $kept_count,
			'pmm_reclassification_truncated' => (string) (($expected_count > 0 && $reviewed > 0 && $reviewed < $expected_count) ? 1 : 0),
			'pmm_reclassification_expected_count' => (string) $expected_count,
		], admin_url('admin.php')));
		exit;
	}

	public function reset_reclassification_review_queue() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_reset_reclassification_review_queue');
		update_option($this->reclassification_review_queue_option_key(), [], false);

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_reclassification_queue_cleared' => 1,
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
		$this->set_raw_import_preview_data([
			'raw_text' => $text,
			'rows' => $rows,
		]);

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
		$stage_mode = isset($_POST['pmm_raw_stage_mode']) ? sanitize_key((string) wp_unslash($_POST['pmm_raw_stage_mode'])) : 'manual';
		$confidence_threshold = isset($_POST['pmm_raw_confidence_threshold']) ? max(1, min(99, (int) wp_unslash((string) $_POST['pmm_raw_confidence_threshold']))) : 92;
		$raw_review_filter = isset($_POST['pmm_raw_review_filter']) ? sanitize_key((string) wp_unslash((string) $_POST['pmm_raw_review_filter'])) : 'pending';
		if (!in_array($raw_review_filter, ['pending', 'reviewed', 'all'], true)) {
			$raw_review_filter = 'pending';
		}
		$table_rows = isset($_POST['pmm_raw_table']) && is_array($_POST['pmm_raw_table']) ? (array) wp_unslash($_POST['pmm_raw_table']) : [];
		$nav_page = isset($_POST['pmm_raw_preview_nav_page']) ? max(1, (int) wp_unslash((string) $_POST['pmm_raw_preview_nav_page'])) : 1;
		$nav_per_page = isset($_POST['pmm_raw_preview_per_page']) ? max(25, min(300, (int) wp_unslash((string) $_POST['pmm_raw_preview_per_page']))) : 100;

		if (in_array($stage_mode, ['mark_high_confidence_reviewed', 'mark_low_confidence_reviewed', 'mark_all_preview_reviewed'], true)) {
			$preview = $this->get_raw_import_preview_data();
			$preview_rows = isset($preview['rows']) && is_array($preview['rows']) ? $preview['rows'] : [];
			$marked = $this->mark_preview_rows_reviewed_by_confidence($preview_rows, $stage_mode, $confidence_threshold);

			$this->set_raw_import_preview_data([
				'raw_text' => isset($preview['raw_text']) ? (string) $preview['raw_text'] : '',
				'rows' => $marked['rows'],
			]);

			wp_safe_redirect(add_query_arg([
				'page' => 'perchance-memory-manager',
				'pmm_raw_review_filter' => (string) $raw_review_filter,
				'pmm_raw_marked_reviewed' => (string) $marked['count'],
				'pmm_raw_stage_mode' => (string) $stage_mode,
				'pmm_raw_confidence_threshold' => (string) $confidence_threshold,
			], admin_url('admin.php')));
			exit;
		}

		if (in_array($stage_mode, ['all_preview_rows', 'high_confidence_only', 'low_confidence_only'], true)) {
			$preview = $this->get_raw_import_preview_data();
			$preview_rows = isset($preview['rows']) && is_array($preview['rows']) ? $preview['rows'] : [];
			$rows = $this->filter_preview_rows_by_confidence($preview_rows, $stage_mode, $confidence_threshold);
			$this->set_staged_raw_import_rows($rows);
			$this->mark_output_rules_dirty();

			wp_safe_redirect(add_query_arg([
				'page' => 'perchance-memory-manager',
				'pmm_raw_review_filter' => (string) $raw_review_filter,
				'pmm_raw_staged' => (string) count($rows),
				'pmm_raw_stage_mode' => (string) $stage_mode,
				'pmm_raw_confidence_threshold' => (string) $confidence_threshold,
			], admin_url('admin.php')));
			exit;
		}

		if ($stage_mode === 'save_preview_page') {
			$preview = $this->get_raw_import_preview_data();
			$preview_rows = isset($preview['rows']) && is_array($preview['rows']) ? $preview['rows'] : [];
			$merged_preview_rows = !empty($preview_rows)
				? $this->merge_preview_rows_with_table_edits($preview_rows, $table_rows)
				: $this->filter_preview_rows_by_confidence($table_rows, 'all_preview_rows', 1);

			$this->set_raw_import_preview_data([
				'raw_text' => isset($preview['raw_text']) ? (string) $preview['raw_text'] : '',
				'rows' => $merged_preview_rows,
			]);

			$rows = $this->filter_preview_rows_by_confidence($merged_preview_rows, 'all_preview_rows', 1);
			$this->set_staged_raw_import_rows($rows);
			$this->mark_output_rules_dirty();

			wp_safe_redirect(add_query_arg([
				'page' => 'perchance-memory-manager',
				'pmm_raw_review_filter' => (string) $raw_review_filter,
				'pmm_raw_preview_page' => (string) $nav_page,
				'pmm_raw_preview_per_page' => (string) $nav_per_page,
				'pmm_raw_preview_saved' => (string) count($table_rows),
			], admin_url('admin.php')));
			exit;
		}

		$text = isset($_POST['pmm_raw_import_rows']) ? (string) wp_unslash($_POST['pmm_raw_import_rows']) : '';
		$file_text = $this->read_uploaded_text_file('pmm_raw_import_rows_file', 15 * 1024 * 1024);
		if (!empty($table_rows) && $file_text === '') {
			$preview = $this->get_raw_import_preview_data();
			$preview_rows = isset($preview['rows']) && is_array($preview['rows']) ? $preview['rows'] : [];
			if (!empty($preview_rows)) {
				$merged_preview_rows = $this->merge_preview_rows_with_table_edits($preview_rows, $table_rows);
				$rows = $this->filter_preview_rows_by_confidence($merged_preview_rows, 'all_preview_rows', 1);
				$this->set_raw_import_preview_data([
					'raw_text' => isset($preview['raw_text']) ? (string) $preview['raw_text'] : '',
					'rows' => $merged_preview_rows,
				]);
				$this->set_staged_raw_import_rows($rows);
				$this->mark_output_rules_dirty();

				wp_safe_redirect(add_query_arg([
					'page' => 'perchance-memory-manager',
					'pmm_raw_review_filter' => (string) $raw_review_filter,
					'pmm_raw_staged' => (string) count($rows),
				], admin_url('admin.php')));
				exit;
			}

			if ($text === '') {
				$text = $this->serialize_raw_import_table_rows($table_rows);
			}
		}
		if ($file_text !== '') {
			$text = $file_text;
		}
		$rows = $this->parse_staged_raw_import_rows_text($text);
		$this->set_staged_raw_import_rows($rows);
		$this->mark_output_rules_dirty();

		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_raw_review_filter' => (string) $raw_review_filter,
			'pmm_raw_staged' => (string) count($rows),
		], admin_url('admin.php')));
		exit;
	}

	public function download_raw_import_rows() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_download_raw_import_rows');

		$preview = $this->get_raw_import_preview_data();
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
		$this->delete_raw_import_preview_data();
		if (!empty($_POST['pmm_clear_staged_raw_import'])) {
			$this->delete_staged_raw_import_rows();
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
		$valid_sections = $this->valid_sections();
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

		if ($payload['target_entity'] !== '' && !in_array($payload['section'], $this->section_level_sections(), true)) {
			$payload['entity'] = $payload['target_entity'];
		} elseif (in_array($payload['section'], $this->section_level_sections(), true)) {
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
		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'txt';
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

		$valid_sections = $this->valid_sections();
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
		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'txt';
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

	public function prune_entity_entries() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_prune_entity_entries');

		$data = get_transient('pmm_last_output_' . get_current_user_id());
		if (empty($data['content']) || !isset($data['stats']) || !is_array($data['stats'])) {
			$this->redirect_with_error('entity_update_missing');
		}

		$cleaned = $this->get_cleaned_data_from_last_output($data);
		if (empty($cleaned) || !is_array($cleaned)) {
			$this->redirect_with_error('entity_update_missing');
		}

		$section = isset($_POST['pmm_prune_section']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_prune_section'])) : 'Characters';
		$entity = isset($_POST['pmm_prune_entity']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_prune_entity'])) : '';
		$max_keep = isset($_POST['pmm_prune_max_keep']) ? max(50, min(300, (int) $_POST['pmm_prune_max_keep'])) : 300;
		$remove_stale = !empty($_POST['pmm_prune_remove_stale']);
		$remove_unreferenced = !empty($_POST['pmm_prune_remove_unreferenced']);
		$require_entity_name_match = !empty($_POST['pmm_prune_require_entity_name_match']);
		$collect_nonprefix_review = !empty($_POST['pmm_prune_collect_nonprefix_review']);
		$preview_only = !empty($_POST['pmm_prune_preview_only']);
		$similarity_threshold = isset($_POST['pmm_prune_similarity_threshold']) ? (float) wp_unslash((string) $_POST['pmm_prune_similarity_threshold']) : 0.90;
		$similarity_threshold = min(0.98, max(0.75, $similarity_threshold));
		$unreferenced_threshold = isset($_POST['pmm_prune_unreferenced_threshold']) ? (float) wp_unslash((string) $_POST['pmm_prune_unreferenced_threshold']) : 0.60;
		$unreferenced_threshold = min(0.95, max(0.40, $unreferenced_threshold));

		$valid_sections = $this->valid_sections();
		if (!in_array($section, $valid_sections, true)) {
			$section = 'Characters';
		}

		if (!isset($cleaned[$section]) || !is_array($cleaned[$section])) {
			$this->redirect_with_error('entity_update_missing');
		}

		$bucket_key = in_array($section, $this->section_level_sections(), true) && $entity === '' ? '__entries__' : $entity;
		if ($bucket_key === '' || !isset($cleaned[$section][$bucket_key]) || !is_array($cleaned[$section][$bucket_key])) {
			$this->redirect_with_error('entity_update_missing');
		}

		$stats = [
			'original' => count((array) $cleaned[$section][$bucket_key]),
			'exact_duplicates' => 0,
			'near_duplicates' => 0,
			'stale_removed' => 0,
			'unreferenced_removed' => 0,
			'entity_name_mismatch_removed' => 0,
			'critical_preserved' => 0,
			'trimmed' => 0,
		];
		$report = [
			'exact_duplicates' => [],
			'near_duplicates' => [],
			'stale_removed' => [],
			'unreferenced_removed' => [],
			'entity_name_mismatch_removed' => [],
			'trimmed' => [],
		];

		$known_entities = $this->collect_known_entity_names($cleaned);
		$critical_rules = $this->get_prune_critical_entry_rules();

		$pruned_items = $this->prune_entity_entry_list(
			(array) $cleaned[$section][$bucket_key],
			$max_keep,
			$remove_stale,
			$similarity_threshold,
			$stats,
			$report,
			$remove_unreferenced,
			$known_entities,
			$unreferenced_threshold,
			$critical_rules,
			$section,
			$entity,
			$require_entity_name_match
		);

		$nonprefix_review_rows = [];
		if ($collect_nonprefix_review && $entity !== '' && !in_array($section, $this->section_level_sections(), true)) {
			$nonprefix_review_rows = $this->collect_nonprefix_entity_entries((array) $cleaned[$section][$bucket_key], $entity);
		}

		$review_candidates = $this->build_prune_review_candidates((array) $cleaned[$section][$bucket_key], $report);

		if ($preview_only) {
			set_transient('pmm_prune_preview_' . get_current_user_id(), [
				'section' => $section,
				'entity' => $entity,
				'max_keep' => $max_keep,
				'similarity_threshold' => $similarity_threshold,
				'remove_stale' => $remove_stale ? 1 : 0,
				'remove_unreferenced' => $remove_unreferenced ? 1 : 0,
				'require_entity_name_match' => $require_entity_name_match ? 1 : 0,
				'collect_nonprefix_review' => $collect_nonprefix_review ? 1 : 0,
				'unreferenced_threshold' => $unreferenced_threshold,
				'stats' => $stats,
				'report' => [
					'exact_duplicates' => array_slice((array) $report['exact_duplicates'], 0, 200),
					'near_duplicates' => array_slice((array) $report['near_duplicates'], 0, 200),
					'stale_removed' => array_slice((array) $report['stale_removed'], 0, 200),
					'unreferenced_removed' => array_slice((array) $report['unreferenced_removed'], 0, 200),
					'entity_name_mismatch_removed' => array_slice((array) $report['entity_name_mismatch_removed'], 0, 200),
					'trimmed' => array_slice((array) $report['trimmed'], 0, 300),
					'review_candidates' => array_values((array) $review_candidates),
					'nonprefix_review' => array_slice((array) $nonprefix_review_rows, 0, 2000),
				],
			], 30 * MINUTE_IN_SECONDS);

			wp_safe_redirect(add_query_arg([
				'page' => 'perchance-memory-manager',
				'pmm_prune_preview' => 1,
				'pmm_prune_section' => $section,
				'pmm_prune_entity' => $entity,
			], admin_url('admin.php')));
			exit;
		}

		delete_transient('pmm_prune_preview_' . get_current_user_id());
		$cleaned[$section][$bucket_key] = $pruned_items;

		$renderer = new PMM_Renderer();
		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'txt';
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

		$kept = count((array) $cleaned[$section][$bucket_key]);
		wp_safe_redirect(add_query_arg([
			'page' => 'perchance-memory-manager',
			'pmm_entity_pruned' => 1,
			'pmm_prune_section' => $section,
			'pmm_prune_entity' => $entity,
			'pmm_prune_before' => (string) $stats['original'],
			'pmm_prune_after' => (string) $kept,
			'pmm_prune_exact' => (string) $stats['exact_duplicates'],
			'pmm_prune_near' => (string) $stats['near_duplicates'],
			'pmm_prune_stale' => (string) $stats['stale_removed'],
			'pmm_prune_unref' => (string) $stats['unreferenced_removed'],
			'pmm_prune_entity_mismatch' => (string) $stats['entity_name_mismatch_removed'],
			'pmm_prune_critical' => (string) $stats['critical_preserved'],
			'pmm_prune_trimmed' => (string) $stats['trimmed'],
		], admin_url('admin.php')));
		exit;
	}

	public function apply_prune_nonprefix_review() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_apply_prune_nonprefix_review');

		$data = get_transient('pmm_last_output_' . get_current_user_id());
		if (empty($data['content']) || !isset($data['stats']) || !is_array($data['stats'])) {
			$this->redirect_with_error('entity_update_missing');
		}

		$cleaned = $this->get_cleaned_data_from_last_output($data);
		if (empty($cleaned) || !is_array($cleaned)) {
			$this->redirect_with_error('entity_update_missing');
		}

		$section = isset($_POST['pmm_prune_section']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_prune_section'])) : 'Characters';
		$entity = isset($_POST['pmm_prune_entity']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_prune_entity'])) : '';

		$valid_sections = $this->valid_sections();
		if (!in_array($section, $valid_sections, true)) {
			$section = 'Characters';
		}

		$bucket_key = in_array($section, $this->section_level_sections(), true) && $entity === '' ? '__entries__' : $entity;
		if ($bucket_key === '' || !isset($cleaned[$section]) || !is_array($cleaned[$section]) || !isset($cleaned[$section][$bucket_key]) || !is_array($cleaned[$section][$bucket_key])) {
			$this->redirect_with_error('entity_update_missing');
		}

		$rows_json = isset($_POST['pmm_prune_nonprefix_rows']) ? (string) wp_unslash($_POST['pmm_prune_nonprefix_rows']) : '[]';
		$rows = json_decode($rows_json, true);
		if (!is_array($rows)) {
			$rows = [];
		}

		$current_items = array_values((array) $cleaned[$section][$bucket_key]);
		$used_indexes = [];
		$remove_indexes = [];
		$replace_by_index = [];
		$critical_updates = [];
		$reviewed = 0;
		$removed = 0;
		$updated = 0;
		$critical_marked = 0;

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$source_entry = isset($row['source_entry']) ? trim((string) $row['source_entry']) : '';
			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$entry = isset($row['entry']) ? trim((string) $row['entry']) : $source_entry;
			$critical = !empty($row['critical']) && (string) $row['critical'] !== '0';
			$source_index = isset($row['source_index']) ? (int) $row['source_index'] : -1;

			if ($source_entry === '') {
				continue;
			}

			if (!in_array($action, ['keep', 'edit', 'remove'], true)) {
				$action = 'remove';
			}

			if ($critical) {
				++$critical_marked;
				if ($action === 'remove') {
					$action = 'keep';
				}
			}

			++$reviewed;

			$match_index = -1;
			if ($source_index >= 0 && isset($current_items[$source_index]) && trim((string) $current_items[$source_index]) === $source_entry && !isset($used_indexes[$source_index])) {
				$match_index = $source_index;
			} else {
				foreach ($current_items as $idx => $value) {
					if (isset($used_indexes[$idx])) {
						continue;
					}
					if (trim((string) $value) !== $source_entry) {
						continue;
					}
					$match_index = (int) $idx;
					break;
				}
			}

			if ($match_index < 0) {
				continue;
			}

			$used_indexes[$match_index] = true;

			if ($action === 'remove') {
				$remove_indexes[$match_index] = true;
				continue;
			}

			if ($critical) {
				$critical_updates[] = [
					'section' => $section,
					'entity' => $entity,
					'entry' => $entry !== '' ? $entry : $source_entry,
				];
			}

			if ($action === 'edit') {
				if ($entry === '') {
					$remove_indexes[$match_index] = true;
					continue;
				}
				$replace_by_index[$match_index] = $entry;
			}
		}

		if (!empty($critical_updates)) {
			$current_critical = $this->get_prune_critical_entry_rules();
			foreach ($critical_updates as $critical_row) {
				$key = $this->build_entry_rule_key((string) $critical_row['section'], (string) $critical_row['entity'], (string) $critical_row['entry']);
				$current_critical[$key] = [
					'section' => (string) $critical_row['section'],
					'entity' => (string) $critical_row['entity'],
					'entry' => (string) $critical_row['entry'],
				];
			}
			$this->save_prune_critical_entry_rules($current_critical);
		}

		$next_items = [];
		foreach ($current_items as $idx => $item) {
			if (isset($remove_indexes[$idx])) {
				++$removed;
				continue;
			}

			if (isset($replace_by_index[$idx])) {
				$replacement = trim((string) $replace_by_index[$idx]);
				if ($replacement === '') {
					++$removed;
					continue;
				}
				if (trim((string) $item) !== $replacement) {
					++$updated;
				}
				$item = $replacement;
			}

			$item = trim((string) $item);
			if ($item === '') {
				continue;
			}
			$next_items[] = $item;
		}

		$cleaned[$section][$bucket_key] = array_values($next_items);
		delete_transient('pmm_prune_preview_' . get_current_user_id());

		$renderer = new PMM_Renderer();
		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'txt';
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
			'pmm_entity_updated' => 1,
			'pmm_prune_section' => $section,
			'pmm_prune_entity' => $entity,
			'pmm_prune_nonprefix_reviewed' => (string) $reviewed,
			'pmm_prune_nonprefix_removed' => (string) $removed,
			'pmm_prune_nonprefix_updated' => (string) $updated,
			'pmm_prune_nonprefix_critical' => (string) $critical_marked,
		], admin_url('admin.php')));
		exit;
	}

	public function apply_prune_preview_review() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_apply_prune_preview_review');

		$data = get_transient('pmm_last_output_' . get_current_user_id());
		if (empty($data['content']) || !isset($data['stats']) || !is_array($data['stats'])) {
			$this->redirect_with_error('entity_update_missing');
		}

		$cleaned = $this->get_cleaned_data_from_last_output($data);
		if (empty($cleaned) || !is_array($cleaned)) {
			$this->redirect_with_error('entity_update_missing');
		}

		$section = isset($_POST['pmm_prune_section']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_prune_section'])) : 'Characters';
		$entity = isset($_POST['pmm_prune_entity']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_prune_entity'])) : '';

		$valid_sections = $this->valid_sections();
		if (!in_array($section, $valid_sections, true)) {
			$section = 'Characters';
		}

		$bucket_key = in_array($section, $this->section_level_sections(), true) && $entity === '' ? '__entries__' : $entity;
		if ($bucket_key === '' || !isset($cleaned[$section]) || !is_array($cleaned[$section]) || !isset($cleaned[$section][$bucket_key]) || !is_array($cleaned[$section][$bucket_key])) {
			$this->redirect_with_error('entity_update_missing');
		}

		$rows_json = isset($_POST['pmm_prune_review_rows']) ? (string) wp_unslash($_POST['pmm_prune_review_rows']) : '[]';
		$rows = json_decode($rows_json, true);
		if (!is_array($rows)) {
			$rows = [];
		}

		$current_items = array_values((array) $cleaned[$section][$bucket_key]);
		$used_indexes = [];
		$remove_indexes = [];
		$replace_by_index = [];
		$critical_updates = [];
		$reviewed = 0;
		$removed = 0;
		$updated = 0;
		$critical_marked = 0;

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$source_entry = isset($row['source_entry']) ? trim((string) $row['source_entry']) : '';
			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'remove';
			$entry = isset($row['entry']) ? trim((string) $row['entry']) : $source_entry;
			$critical = !empty($row['critical']) && (string) $row['critical'] !== '0';
			$source_index = isset($row['source_index']) ? (int) $row['source_index'] : -1;

			if ($source_entry === '') {
				continue;
			}

			if (!in_array($action, ['keep', 'edit', 'remove'], true)) {
				$action = 'remove';
			}

			if ($critical) {
				++$critical_marked;
				if ($action === 'remove') {
					$action = 'keep';
				}
			}

			++$reviewed;

			$match_index = -1;
			if ($source_index >= 0 && isset($current_items[$source_index]) && trim((string) $current_items[$source_index]) === $source_entry && !isset($used_indexes[$source_index])) {
				$match_index = $source_index;
			} else {
				foreach ($current_items as $idx => $value) {
					if (isset($used_indexes[$idx])) {
						continue;
					}
					if (trim((string) $value) !== $source_entry) {
						continue;
					}
					$match_index = (int) $idx;
					break;
				}
			}

			if ($match_index < 0) {
				continue;
			}

			$used_indexes[$match_index] = true;

			if ($action === 'remove') {
				$remove_indexes[$match_index] = true;
				continue;
			}

			if ($critical) {
				$critical_updates[] = [
					'section' => $section,
					'entity' => $entity,
					'entry' => $entry !== '' ? $entry : $source_entry,
				];
			}

			if ($action === 'edit') {
				if ($entry === '') {
					$remove_indexes[$match_index] = true;
					continue;
				}
				$replace_by_index[$match_index] = $entry;
			}
		}

		if (!empty($critical_updates)) {
			$current_critical = $this->get_prune_critical_entry_rules();
			foreach ($critical_updates as $critical_row) {
				$key = $this->build_entry_rule_key((string) $critical_row['section'], (string) $critical_row['entity'], (string) $critical_row['entry']);
				$current_critical[$key] = [
					'section' => (string) $critical_row['section'],
					'entity' => (string) $critical_row['entity'],
					'entry' => (string) $critical_row['entry'],
				];
			}
			$this->save_prune_critical_entry_rules($current_critical);
		}

		$next_items = [];
		foreach ($current_items as $idx => $item) {
			if (isset($remove_indexes[$idx])) {
				++$removed;
				continue;
			}

			if (isset($replace_by_index[$idx])) {
				$replacement = trim((string) $replace_by_index[$idx]);
				if ($replacement === '') {
					++$removed;
					continue;
				}
				if (trim((string) $item) !== $replacement) {
					++$updated;
				}
				$item = $replacement;
			}

			$item = trim((string) $item);
			if ($item === '') {
				continue;
			}
			$next_items[] = $item;
		}

		$cleaned[$section][$bucket_key] = array_values($next_items);
		delete_transient('pmm_prune_preview_' . get_current_user_id());

		$renderer = new PMM_Renderer();
		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'txt';
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
			'pmm_entity_updated' => 1,
			'pmm_prune_section' => $section,
			'pmm_prune_entity' => $entity,
			'pmm_prune_reviewed' => (string) $reviewed,
			'pmm_prune_removed' => (string) $removed,
			'pmm_prune_updated' => (string) $updated,
			'pmm_prune_marked_critical' => (string) $critical_marked,
		], admin_url('admin.php')));
		exit;
	}

	public function save_global_entity_report() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do that.', 'perchance-memory-manager'));
		}

		check_admin_referer('pmm_save_global_entity_report');

		$data = get_transient('pmm_last_output_' . get_current_user_id());
		if (empty($data['content']) || !isset($data['stats']) || !is_array($data['stats'])) {
			$this->redirect_with_error('entity_update_missing');
		}

		$cleaned = $this->get_cleaned_data_from_last_output($data);
		if (empty($cleaned) || !is_array($cleaned)) {
			$this->redirect_with_error('entity_update_missing');
		}

		$entity_name = isset($_POST['pmm_global_entity_name']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_global_entity_name'])) : '';
		$page_number = isset($_POST['pmm_global_entity_page']) ? max(1, (int) $_POST['pmm_global_entity_page']) : 1;
		$per_page = isset($_POST['pmm_global_entity_per_page']) ? max(25, min(100, (int) $_POST['pmm_global_entity_per_page'])) : 50;
		$entry_convert_mode = !empty($_POST['pmm_entry_convert_mode']);
		$entry_convert_section = isset($_POST['pmm_entry_convert_section']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_entry_convert_section'])) : 'all';
		$entry_convert_entity = isset($_POST['pmm_entry_convert_entity']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_entry_convert_entity'])) : '';
		$entry_convert_search = isset($_POST['pmm_entry_convert_search']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_entry_convert_search'])) : '';
		$entry_convert_include_mentions = isset($_POST['pmm_entry_convert_include_mentions']) ? ((int) $_POST['pmm_entry_convert_include_mentions'] === 1) : null;
		if ($entry_convert_include_mentions === null) {
			$legacy_global_entity_settings = $this->get_global_entity_report_settings_option();
			$entry_convert_include_mentions = !empty($legacy_global_entity_settings['include_mentions']);
		}
		$entry_convert_load = isset($_POST['pmm_entry_convert_load']) ? ((int) $_POST['pmm_entry_convert_load'] === 1) : $entry_convert_mode;
		$entry_convert_page = isset($_POST['pmm_entry_convert_page']) ? max(1, (int) $_POST['pmm_entry_convert_page']) : 1;
		$entry_convert_per_page = isset($_POST['pmm_entry_convert_per_page']) ? max(25, min(100, (int) $_POST['pmm_entry_convert_per_page'])) : 50;

		$snapshot = isset($_POST['pmm_global_snapshot']) ? sanitize_text_field((string) wp_unslash($_POST['pmm_global_snapshot'])) : '';
		$current_snapshot = md5((string) $data['content']);
		if ($snapshot !== '' && !hash_equals($current_snapshot, $snapshot)) {
			$stale_args = [
				'page' => 'perchance-memory-manager',
				'pmm_error' => 'global_entity_stale',
			];
			if ($entry_convert_mode) {
				$stale_args['pmm_entry_convert_section'] = $entry_convert_section;
				$stale_args['pmm_entry_convert_entity'] = $entry_convert_entity;
				$stale_args['pmm_entry_convert_search'] = $entry_convert_search;
				$stale_args['pmm_entry_convert_include_mentions'] = $entry_convert_include_mentions ? 1 : 0;
				$stale_args['pmm_entry_convert_load'] = $entry_convert_load ? 1 : 0;
				$stale_args['pmm_entry_convert_page'] = $entry_convert_page;
				$stale_args['pmm_entry_convert_per_page'] = $entry_convert_per_page;
			} else {
				$stale_args['pmm_global_entity_name'] = $entity_name;
				$stale_args['pmm_global_entity_page'] = $page_number;
				$stale_args['pmm_global_entity_per_page'] = $per_page;
			}

			wp_safe_redirect(add_query_arg($stale_args, admin_url('admin.php')));
			exit;
		}

		$rows_json = isset($_POST['pmm_global_rows_json']) ? (string) wp_unslash($_POST['pmm_global_rows_json']) : '';
		$rows = json_decode($rows_json, true);
		if (!is_array($rows)) {
			$rows = [];
		}
		$rows = array_slice($rows, 0, 300);

		$valid_sections = array_values(array_unique(array_merge($this->valid_sections(), ['New Entries'])));
		$section_level_sections = array_values(array_unique(array_merge($this->section_level_sections(), ['New Entries'])));
		$alias_substitution_pairs = $this->build_alias_substitution_pairs(get_option('pmm_alias_rules', []));

		$reviewed = 0;
		$changed = 0;
		$moved = 0;
		$removed = 0;
		$updated = 0;

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$source_section = isset($row['source_section']) ? sanitize_text_field((string) $row['source_section']) : '';
			$source_entity = isset($row['source_entity']) ? sanitize_text_field((string) $row['source_entity']) : '';
			$source_entry = isset($row['source_entry']) ? (string) $row['source_entry'] : '';
			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$target_section = isset($row['target_section']) ? sanitize_text_field((string) $row['target_section']) : $source_section;
			$target_entity = isset($row['target_entity']) ? sanitize_text_field((string) $row['target_entity']) : $source_entity;
			$target_entry = isset($row['entry']) ? (string) $row['entry'] : $source_entry;

			if (trim($source_entry) === '' || !in_array($source_section, $valid_sections, true)) {
				continue;
			}

			if (!in_array($action, ['keep', 'move', 'remove'], true)) {
				$action = 'keep';
			}

			if (!in_array($target_section, $valid_sections, true)) {
				$target_section = $source_section;
			}

			if (in_array($target_section, $section_level_sections, true)) {
				$target_entity = '';
			} else {
				$target_entity = $this->normalize_entity_target_name($target_section, $source_entity, $target_entity);
			}

			if (trim($target_entry) === '') {
				$target_entry = $source_entry;
			}
			$target_entry = $this->apply_alias_substitutions_to_text($target_entry, $alias_substitution_pairs);

			++$reviewed;

			if ($action === 'keep') {
				continue;
			}

			if ($action === 'remove') {
				if ($this->remove_entry_from_cleaned($cleaned, $source_section, $source_entity, $source_entry)) {
					++$changed;
					++$removed;
				}
				continue;
			}

			if ($this->move_entry_in_cleaned($cleaned, $source_section, $source_entity, $source_entry, $target_section, $target_entity, $target_entry)) {
				++$changed;
				if ($source_section !== $target_section || $source_entity !== $target_entity) {
					++$moved;
				}
				if (PMM_Utils::fingerprint($source_entry) !== PMM_Utils::fingerprint($target_entry)) {
					++$updated;
				}
			}
		}

		if ($changed > 0) {
			$renderer = new PMM_Renderer();
			$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'txt';
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

		$redirect_args = [
			'page' => 'perchance-memory-manager',
		];

		if ($entry_convert_mode) {
			$redirect_args['pmm_entry_convert_saved'] = (string) $changed;
			$redirect_args['pmm_entry_convert_reviewed'] = (string) $reviewed;
			$redirect_args['pmm_entry_convert_moved'] = (string) $moved;
			$redirect_args['pmm_entry_convert_removed'] = (string) $removed;
			$redirect_args['pmm_entry_convert_updated'] = (string) $updated;
			$redirect_args['pmm_entry_convert_section'] = $entry_convert_section;
			$redirect_args['pmm_entry_convert_entity'] = $entry_convert_entity;
			$redirect_args['pmm_entry_convert_search'] = $entry_convert_search;
			$redirect_args['pmm_entry_convert_include_mentions'] = $entry_convert_include_mentions ? 1 : 0;
			$redirect_args['pmm_entry_convert_load'] = $entry_convert_load ? 1 : 0;
			$redirect_args['pmm_entry_convert_page'] = $entry_convert_page;
			$redirect_args['pmm_entry_convert_per_page'] = $entry_convert_per_page;
		} else {
			$redirect_args['pmm_global_entity_saved'] = (string) $changed;
			$redirect_args['pmm_global_entity_reviewed'] = (string) $reviewed;
			$redirect_args['pmm_global_entity_moved'] = (string) $moved;
			$redirect_args['pmm_global_entity_removed'] = (string) $removed;
			$redirect_args['pmm_global_entity_updated'] = (string) $updated;
			$redirect_args['pmm_global_entity_name'] = $entity_name;
			$redirect_args['pmm_global_entity_page'] = $page_number;
			$redirect_args['pmm_global_entity_per_page'] = $per_page;
		}

		wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
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
		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'txt';
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

		$rescan_sections = isset($_POST['pmm_rescan_sections']) ? !empty($_POST['pmm_rescan_sections']) : false;
		$rescan_confidence = isset($_POST['pmm_rescan_confidence']) ? max(70, min(98, (int) wp_unslash((string) $_POST['pmm_rescan_confidence']))) : null;
		$rescan_preview_only = isset($_POST['pmm_rescan_preview_only']) ? !empty($_POST['pmm_rescan_preview_only']) : null;
		if (!$this->start_reprocess_from_last_output($rescan_sections, $rescan_confidence, $rescan_preview_only)) {
			$this->redirect_with_error('reprocess_missing');
		}
	}

	private function build_output_filename($original, $format) {
		$base = $this->normalized_source_base_name($original);
		$extension = ($format === 'txt') ? 'txt' : 'md';

		return $base . '-cleaned.' . $extension;
	}

	private function normalized_source_base_name($source_filename) {
		$base = pathinfo((string) $source_filename, PATHINFO_FILENAME);
		$base = sanitize_file_name($base);
		if ($base === '') {
			return 'memory';
		}

		$patterns = [
			'/-cleaned$/i',
			'/-v\d{8}-\d{6}-[A-Za-z0-9]+$/',
		];

		$changed = true;
		while ($changed && $base !== '') {
			$changed = false;
			foreach ($patterns as $pattern) {
				$next = preg_replace($pattern, '', $base);
				if (is_string($next) && $next !== $base) {
					$base = $next;
					$changed = true;
				}
			}
		}

		$base = trim($base, '-_ ');
		return $base !== '' ? $base : 'memory';
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
		return 'pmm_job_' . get_current_user_id() . '_' . $this->normalize_job_id($job_id);
	}

	private function generate_job_id() {
		return 'job_' . strtolower(wp_generate_password(10, false, false));
	}

	private function build_job_source_path($job_id, $ext) {
		$uploads = wp_upload_dir();
		return trailingslashit($uploads['basedir']) . 'pmm-jobs/' . $this->normalize_job_id($job_id) . '-source.' . $ext;
	}

	private function build_job_state_path($job_id) {
		$uploads = wp_upload_dir();
		return trailingslashit($uploads['basedir']) . 'pmm-jobs/' . $this->normalize_job_id($job_id) . '-state.bin';
	}

	private function normalize_job_id($job_id) {
		$normalized = sanitize_key((string) $job_id);
		return $normalized !== '' ? $normalized : 'job_unknown';
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
		$reclassification_scan_count = 0;
		$reclassification_moved = 0;
		$reclassification_proposed = 0;
		$reclassification_preview_rows = [];
		$rescan_confidence = isset($state['rescan_confidence']) ? max(70, min(98, (int) $state['rescan_confidence'])) : 84;
		$rescan_preview_only = !empty($state['rescan_preview_only']);
		if (!empty($state['rescan_sections'])) {
			$reclassified = $this->auto_reclassify_cleaned_entries($state['cleaned'], $rescan_confidence, $rescan_preview_only);
			$state['cleaned'] = $reclassified['data'];
			$reclassification_scan_count = (int) $reclassified['evaluated'];
			$reclassification_moved = (int) $reclassified['moved'];
			$reclassification_proposed = (int) $reclassified['proposed'];
			$reclassification_preview_rows = isset($reclassified['preview_rows']) && is_array($reclassified['preview_rows']) ? $reclassified['preview_rows'] : [];
		}

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
		$this->update_confirmed_entity_registry_from_cleaned($state['cleaned']);

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
			'rescan_sections' => !empty($state['rescan_sections']) ? 1 : 0,
			'rescan_confidence' => $rescan_confidence,
			'rescan_preview_only' => $rescan_preview_only ? 1 : 0,
			'reclassification_scanned' => $reclassification_scan_count,
			'reclassification_moved' => $reclassification_moved,
			'reclassification_proposed' => $reclassification_proposed,
			'reclassification_preview_rows' => array_slice($reclassification_preview_rows, 0, 120),
		];
		$this->clear_output_rules_dirty();
		$this->delete_staged_raw_import_rows();

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

		if ($entity !== '' && !in_array($section, array_merge($this->section_level_sections(), ['New Entries']), true)) {
			$prefix .= $entity . "\n";
		}

		return $prefix . $chunk;
	}

	private function detect_context($lines, $initial) {
		$context = [
			'section' => isset($initial['section']) ? $initial['section'] : 'New Entries',
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

			if (in_array($context['section'], array_merge($this->section_level_sections(), ['New Entries']), true)) {
				continue;
			}

			if (preg_match('/^[A-Z][A-Za-z0-9_\-()\'\/.& ]{1,80}:?$/u', $line)) {
				$context['entity'] = rtrim($line, ':');
			}
		}

		return $context;
	}

	private function extract_section_from_line($line) {
		if (!preg_match('/^#?\s*(characters|organizations|locations|technology\s*\/\s*systems|vehicles\s*\/\s*transportation|world\s*building|relationships|nsfw|notes|new entries|raw import)\b/iu', $line, $m)) {
			return null;
		}

		$key = strtolower(trim(preg_replace('/\s+/u', ' ', $m[1])));
		$map = [
			'characters' => 'Characters',
			'organizations' => 'Organizations',
			'locations' => 'Locations',
			'technology / systems' => 'Technology / Systems',
			'vehicles / transportation' => 'Vehicles / Transportation',
			'world building' => 'World Building',
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
			'Vehicles / Transportation' => [],
			'World Building' => [],
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

		$base = $this->normalized_source_base_name($source_filename);

		$ext = ($format === 'txt') ? 'txt' : 'md';
		$filename = $base . '-v' . gmdate('Ymd-His') . '-' . wp_generate_password(2, false, false) . '.' . $ext;
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

	private function get_raw_import_preview_data() {
		$key = $this->get_raw_import_preview_key();
		$data = get_option($key, null);
		if (is_array($data) && isset($data['rows']) && is_array($data['rows'])) {
			return $this->refresh_raw_import_preview_for_alias_changes($data);
		}

		$legacy = get_transient($key);
		if (is_array($legacy) && isset($legacy['rows']) && is_array($legacy['rows'])) {
			$data = $this->refresh_raw_import_preview_for_alias_changes($legacy);
			update_option($key, $data, false);
			delete_transient($key);
			return $data;
		}

		return [
			'raw_text' => '',
			'rows' => [],
			'alias_signature' => $this->raw_import_alias_signature(),
		];
	}

	private function refresh_raw_import_preview_for_alias_changes($data) {
		if (!is_array($data)) {
			return [
				'raw_text' => '',
				'rows' => [],
				'alias_signature' => $this->raw_import_alias_signature(),
			];
		}

		$current_signature = $this->raw_import_alias_signature();
		$stored_signature = isset($data['alias_signature']) ? (string) $data['alias_signature'] : '';
		$raw_text = isset($data['raw_text']) ? (string) $data['raw_text'] : '';
		$rows = isset($data['rows']) && is_array($data['rows']) ? array_values($data['rows']) : [];

		if ($stored_signature === $current_signature) {
			$data['alias_signature'] = $current_signature;
			return $data;
		}

		if (trim($raw_text) === '') {
			$data['raw_text'] = $raw_text;
			$data['rows'] = $rows;
			$data['alias_signature'] = $current_signature;
			return $data;
		}

		$parser = new PMM_Parser();
		$refreshed_rows = $parser->preview_raw_import_rows($raw_text, $this->build_existing_entity_seed_from_last_output());

		foreach ($refreshed_rows as $index => $row) {
			if (!isset($rows[$index]) || !is_array($rows[$index])) {
				continue;
			}

			$previous_row = $rows[$index];
			$was_reviewed = !empty($previous_row['reviewed']);
			$was_removed = !empty($previous_row['removed']);
			if (!$was_removed) {
				$previous_bullet = isset($previous_row['bullet']) ? trim((string) $previous_row['bullet']) : '';
				$was_removed = ($previous_bullet === '');
			}

			if ($was_reviewed) {
				$refreshed_rows[$index]['reviewed'] = 1;
			}
			if ($was_removed) {
				$refreshed_rows[$index]['bullet'] = '';
				$refreshed_rows[$index]['removed'] = 1;
			}
		}

		$data['rows'] = $refreshed_rows;
		$data['raw_text'] = $raw_text;
		$data['alias_signature'] = $current_signature;
		return $data;
	}

	private function raw_import_alias_signature() {
		$rules = get_option('pmm_alias_rules', []);
		if (!is_array($rules)) {
			$rules = [];
		}

		$exclusions = get_option('pmm_first_name_alias_exclusions', []);
		if (!is_array($exclusions)) {
			$exclusions = [];
		}

		$normalized_rules = [];
		foreach ($rules as $source => $canonical) {
			$source = trim((string) $source);
			$canonical = trim((string) $canonical);
			if ($source === '' || $canonical === '') {
				continue;
			}
			$normalized_rules[mb_strtolower($source)] = $canonical;
		}

		$normalized_exclusions = [];
		foreach ($exclusions as $item) {
			$item = mb_strtolower(trim((string) $item));
			if ($item === '') {
				continue;
			}
			$normalized_exclusions[] = $item;
		}

		ksort($normalized_rules);
		sort($normalized_exclusions, SORT_STRING);

		return md5(wp_json_encode([
			'rules' => $normalized_rules,
			'exclusions' => $normalized_exclusions,
		]));
	}

	private function set_raw_import_preview_data($data) {
		$key = $this->get_raw_import_preview_key();
		$payload = [
			'raw_text' => isset($data['raw_text']) ? (string) $data['raw_text'] : '',
			'rows' => isset($data['rows']) && is_array($data['rows']) ? array_values($data['rows']) : [],
			'alias_signature' => $this->raw_import_alias_signature(),
			'saved_at' => time(),
		];
		update_option($key, $payload, false);
	}

	private function delete_raw_import_preview_data() {
		$key = $this->get_raw_import_preview_key();
		delete_option($key);
		delete_transient($key);
	}

	private function set_staged_raw_import_rows($rows) {
		$key = $this->get_staged_raw_import_key();
		update_option($key, is_array($rows) ? array_values($rows) : [], false);
	}

	private function delete_staged_raw_import_rows() {
		$key = $this->get_staged_raw_import_key();
		delete_option($key);
		delete_transient($key);
	}

	private function get_staged_raw_import_rows() {
		$key = $this->get_staged_raw_import_key();
		$rows = get_option($key, null);
		if (is_array($rows)) {
			return $rows;
		}

		$legacy = get_transient($key);
		if (is_array($legacy)) {
			update_option($key, $legacy, false);
			delete_transient($key);
			return $legacy;
		}

		return [];
	}

	private function entity_review_queue_option_key() {
		return 'pmm_entity_review_queue_' . get_current_user_id();
	}

	private function get_entity_review_queue() {
		$stored = get_option($this->entity_review_queue_option_key(), []);
		if (!is_array($stored)) {
			$stored = [];
		}

		$out = [];
		foreach ($stored as $id => $row) {
			$id = sanitize_key((string) $id);
			if ($id === '' || !is_array($row)) {
				continue;
			}

			$status = isset($row['status']) ? sanitize_key((string) $row['status']) : 'reviewed';
			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$stamp = isset($row['stamp']) ? max(0, (int) $row['stamp']) : 0;
			$updated_at = isset($row['updated_at']) ? max(0, (int) $row['updated_at']) : 0;

			$out[$id] = [
				'status' => ($status !== '' ? $status : 'reviewed'),
				'action' => $action,
				'stamp' => $stamp,
				'updated_at' => $updated_at,
			];
		}

		return $out;
	}

	private function set_entity_review_queue($queue) {
		$normalized = [];
		foreach ((array) $queue as $id => $row) {
			$id = sanitize_key((string) $id);
			if ($id === '' || !is_array($row)) {
				continue;
			}

			$status = isset($row['status']) ? sanitize_key((string) $row['status']) : 'reviewed';
			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$stamp = isset($row['stamp']) ? max(0, (int) $row['stamp']) : 0;
			$updated_at = isset($row['updated_at']) ? max(0, (int) $row['updated_at']) : time();

			$normalized[$id] = [
				'status' => ($status !== '' ? $status : 'reviewed'),
				'action' => $action,
				'stamp' => $stamp,
				'updated_at' => $updated_at,
			];
		}

		if (count($normalized) > 20000) {
			$normalized = array_slice($normalized, -20000, null, true);
		}

		update_option($this->entity_review_queue_option_key(), $normalized, false);
	}

	private function similarity_review_queue_option_key() {
		return 'pmm_similarity_review_queue_' . get_current_user_id();
	}

	private function get_similarity_review_queue() {
		$stored = get_option($this->similarity_review_queue_option_key(), []);
		if (!is_array($stored)) {
			$stored = [];
		}

		$out = [];
		foreach ($stored as $id => $row) {
			$id = sanitize_key((string) $id);
			if ($id === '' || !is_array($row)) {
				continue;
			}

			$status = isset($row['status']) ? sanitize_key((string) $row['status']) : 'reviewed';
			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$stamp = isset($row['stamp']) ? max(0, (int) $row['stamp']) : 0;
			$updated_at = isset($row['updated_at']) ? max(0, (int) $row['updated_at']) : 0;

			$out[$id] = [
				'status' => ($status !== '' ? $status : 'reviewed'),
				'action' => $action,
				'stamp' => $stamp,
				'updated_at' => $updated_at,
			];
		}

		return $out;
	}

	private function set_similarity_review_queue($queue) {
		$normalized = [];
		foreach ((array) $queue as $id => $row) {
			$id = sanitize_key((string) $id);
			if ($id === '' || !is_array($row)) {
				continue;
			}

			$status = isset($row['status']) ? sanitize_key((string) $row['status']) : 'reviewed';
			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$stamp = isset($row['stamp']) ? max(0, (int) $row['stamp']) : 0;
			$updated_at = isset($row['updated_at']) ? max(0, (int) $row['updated_at']) : time();

			$normalized[$id] = [
				'status' => ($status !== '' ? $status : 'reviewed'),
				'action' => $action,
				'stamp' => $stamp,
				'updated_at' => $updated_at,
			];
		}

		if (count($normalized) > 20000) {
			$normalized = array_slice($normalized, -20000, null, true);
		}

		update_option($this->similarity_review_queue_option_key(), $normalized, false);
	}

	private function reclassification_review_queue_option_key() {
		return 'pmm_reclassification_review_queue_' . get_current_user_id();
	}

	private function get_reclassification_review_queue() {
		$stored = get_option($this->reclassification_review_queue_option_key(), []);
		if (!is_array($stored)) {
			$stored = [];
		}

		$out = [];
		foreach ($stored as $id => $row) {
			$id = sanitize_key((string) $id);
			if ($id === '' || !is_array($row)) {
				continue;
			}

			$status = isset($row['status']) ? sanitize_key((string) $row['status']) : 'reviewed';
			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$stamp = isset($row['stamp']) ? max(0, (int) $row['stamp']) : 0;
			$updated_at = isset($row['updated_at']) ? max(0, (int) $row['updated_at']) : 0;

			$out[$id] = [
				'status' => ($status !== '' ? $status : 'reviewed'),
				'action' => $action,
				'stamp' => $stamp,
				'updated_at' => $updated_at,
			];
		}

		return $out;
	}

	private function set_reclassification_review_queue($queue) {
		$normalized = [];
		foreach ((array) $queue as $id => $row) {
			$id = sanitize_key((string) $id);
			if ($id === '' || !is_array($row)) {
				continue;
			}

			$status = isset($row['status']) ? sanitize_key((string) $row['status']) : 'reviewed';
			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$stamp = isset($row['stamp']) ? max(0, (int) $row['stamp']) : 0;
			$updated_at = isset($row['updated_at']) ? max(0, (int) $row['updated_at']) : time();

			$normalized[$id] = [
				'status' => ($status !== '' ? $status : 'reviewed'),
				'action' => $action,
				'stamp' => $stamp,
				'updated_at' => $updated_at,
			];
		}

		if (count($normalized) > 20000) {
			$normalized = array_slice($normalized, -20000, null, true);
		}

		update_option($this->reclassification_review_queue_option_key(), $normalized, false);
	}

	private function questionable_review_queue_option_key() {
		return 'pmm_questionable_review_queue_' . get_current_user_id();
	}

	private function get_questionable_review_queue() {
		$stored = get_option($this->questionable_review_queue_option_key(), []);
		if (!is_array($stored)) {
			$stored = [];
		}

		$out = [];
		foreach ($stored as $id => $row) {
			$id = sanitize_key((string) $id);
			if ($id === '' || !is_array($row)) {
				continue;
			}

			$status = isset($row['status']) ? sanitize_key((string) $row['status']) : 'reviewed';
			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$stamp = isset($row['stamp']) ? max(0, (int) $row['stamp']) : 0;
			$updated_at = isset($row['updated_at']) ? max(0, (int) $row['updated_at']) : 0;

			$out[$id] = [
				'status' => ($status !== '' ? $status : 'reviewed'),
				'action' => $action,
				'stamp' => $stamp,
				'updated_at' => $updated_at,
			];
		}

		return $out;
	}

	private function set_questionable_review_queue($queue) {
		$normalized = [];
		foreach ((array) $queue as $id => $row) {
			$id = sanitize_key((string) $id);
			if ($id === '' || !is_array($row)) {
				continue;
			}

			$status = isset($row['status']) ? sanitize_key((string) $row['status']) : 'reviewed';
			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$stamp = isset($row['stamp']) ? max(0, (int) $row['stamp']) : 0;
			$updated_at = isset($row['updated_at']) ? max(0, (int) $row['updated_at']) : time();

			$normalized[$id] = [
				'status' => ($status !== '' ? $status : 'reviewed'),
				'action' => $action,
				'stamp' => $stamp,
				'updated_at' => $updated_at,
			];
		}

		if (count($normalized) > 20000) {
			$normalized = array_slice($normalized, -20000, null, true);
		}

		update_option($this->questionable_review_queue_option_key(), $normalized, false);
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

		$confirmed = $this->get_confirmed_entity_registry_option();
		foreach ($this->entity_sections() as $section) {
			if (!isset($data[$section]) || !isset($confirmed[$section]) || !is_array($confirmed[$section])) {
				continue;
			}
			foreach ($confirmed[$section] as $row) {
				if (!is_array($row)) {
					continue;
				}
				$name = isset($row['name']) ? trim((string) $row['name']) : '';
				if ($name !== '') {
					$data[$section][$name] = [];
				}
			}
		}

		return $data;
	}

	private function get_confirmed_entity_registry_option() {
		$sections = $this->entity_sections();
		$stored = get_option('pmm_confirmed_entities_registry', []);
		if (!is_array($stored)) {
			$stored = [];
		}

		$out = [];
		foreach ($sections as $section) {
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
				$seen_count = isset($row['seen_count']) ? max(1, (int) $row['seen_count']) : 1;
				$last_seen = isset($row['last_seen']) ? max(0, (int) $row['last_seen']) : 0;
				$out[$section][$fp] = [
					'name' => $name,
					'seen_count' => $seen_count,
					'last_seen' => $last_seen,
				];
			}
		}

		return $out;
	}

	private function update_confirmed_entity_registry_from_cleaned($cleaned) {
		$registry = $this->get_confirmed_entity_registry_option();
		$now = time();

		foreach ($this->entity_sections() as $section) {
			$entities = isset($cleaned[$section]) && is_array($cleaned[$section]) ? $cleaned[$section] : [];
			foreach ($entities as $entity => $items) {
				if (strpos((string) $entity, '__') === 0) {
					continue;
				}

				$name = trim((string) $entity);
				$fp = PMM_Utils::name_fingerprint($name);
				if ($name === '' || $fp === '') {
					continue;
				}

				if (!isset($registry[$section][$fp])) {
					$registry[$section][$fp] = [
						'name' => $name,
						'seen_count' => 1,
						'last_seen' => $now,
					];
					continue;
				}

				$existing = $registry[$section][$fp];
				$canonical = $this->pick_canonical_name(
					isset($existing['name']) ? (string) $existing['name'] : '',
					$name
				);
				$registry[$section][$fp] = [
					'name' => $canonical,
					'seen_count' => max(1, (int) (isset($existing['seen_count']) ? $existing['seen_count'] : 1)) + 1,
					'last_seen' => $now,
				];
			}
		}

		update_option('pmm_confirmed_entities_registry', $registry, false);
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
		$valid_sections = $this->valid_sections();
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
		$valid_sections = $this->valid_sections();

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

	private function filter_preview_rows_by_confidence($rows, $mode, $threshold) {
		$valid_sections = $this->valid_sections();
		$out = [];

		foreach ((array) $rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$confidence = isset($row['confidence']) ? (int) $row['confidence'] : 50;
			$include = false;
			if ($mode === 'all_preview_rows') {
				$include = true;
			} elseif ($mode === 'high_confidence_only') {
				$include = ($confidence >= $threshold);
			} elseif ($mode === 'low_confidence_only') {
				$include = ($confidence < $threshold);
			}

			if (!$include) {
				continue;
			}

			$section = isset($row['section']) ? sanitize_text_field((string) $row['section']) : 'Notes';
			if (!in_array($section, $valid_sections, true)) {
				$section = 'Notes';
			}

			$entity = isset($row['entity']) ? sanitize_text_field((string) $row['entity']) : '';
			$bullet = isset($row['bullet']) ? sanitize_textarea_field((string) $row['bullet']) : '';
			if ($bullet === '') {
				continue;
			}

			$out[] = [
				'section' => $section,
				'entity' => $entity,
				'bullet' => $bullet,
			];
		}

		return $out;
	}

	private function mark_preview_rows_reviewed_by_confidence($rows, $mode, $threshold) {
		$updated = (array) $rows;
		$marked_count = 0;

		foreach ($updated as $idx => $row) {
			if (!is_array($row)) {
				continue;
			}

			$bullet = isset($row['bullet']) ? trim((string) $row['bullet']) : '';
			if ($bullet === '') {
				continue;
			}

			$confidence = isset($row['confidence']) ? (int) $row['confidence'] : 50;
			$apply = false;
			if ($mode === 'mark_all_preview_reviewed') {
				$apply = true;
			} elseif ($mode === 'mark_high_confidence_reviewed') {
				$apply = ($confidence >= $threshold);
			} elseif ($mode === 'mark_low_confidence_reviewed') {
				$apply = ($confidence < $threshold);
			}

			if (!$apply) {
				continue;
			}

			if (empty($row['reviewed'])) {
				++$marked_count;
			}
			$row['reviewed'] = 1;
			$updated[$idx] = $row;
		}

		return [
			'rows' => $updated,
			'count' => $marked_count,
		];
	}

	private function merge_preview_rows_with_table_edits($preview_rows, $table_rows) {
		$valid_sections = $this->valid_sections();
		$merged = (array) $preview_rows;

		foreach ((array) $table_rows as $index => $row) {
			if (!is_array($row)) {
				continue;
			}

			$idx = (int) $index;
			if (!isset($merged[$idx]) || !is_array($merged[$idx])) {
				continue;
			}

			$section = isset($row['section']) ? sanitize_text_field((string) $row['section']) : (string) ($merged[$idx]['section'] ?? 'Notes');
			if (!in_array($section, $valid_sections, true)) {
				$section = 'Notes';
			}

			$entity = isset($row['entity']) ? sanitize_text_field((string) $row['entity']) : (string) ($merged[$idx]['entity'] ?? '');
			$bullet = isset($row['bullet']) ? sanitize_textarea_field((string) $row['bullet']) : (string) ($merged[$idx]['bullet'] ?? '');
			$bullet = trim((string) $bullet);
			$removed = isset($row['removed']) ? (int) $row['removed'] : 0;
			$reviewed = isset($row['reviewed']) ? (int) $row['reviewed'] : (isset($merged[$idx]['reviewed']) ? (int) $merged[$idx]['reviewed'] : 0);
			if ($removed === 1) {
				$bullet = '';
			}

			$merged[$idx]['section'] = $section;
			$merged[$idx]['entity'] = $entity;
			$merged[$idx]['bullet'] = $bullet;
			$merged[$idx]['reviewed'] = ($reviewed === 1 ? 1 : 0);
		}

		return $merged;
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
				'format' => (string) get_option('pmm_last_format', 'txt'),
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
			$line = $this->normalize_entity_workspace_entry($line);
			if (trim($line) !== '') {
				$items[] = $line;
			}
		}

		if (!isset($cleaned[$section]) || !is_array($cleaned[$section])) {
			$cleaned[$section] = [];
		}

		if ($action === 'delete') {
			if (in_array($section, $this->section_level_sections(), true) && $entity === '') {
				$cleaned[$section]['__entries__'] = [];
			} elseif ($entity !== '' && isset($cleaned[$section][$entity])) {
				unset($cleaned[$section][$entity]);
			}
			return $cleaned;
		}

		if (in_array($section, $this->section_level_sections(), true) && $entity === '') {
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

	private function normalize_entity_workspace_entry($line) {
		$line = trim((string) $line);
		if ($line === '') {
			return '';
		}

		$line = preg_replace('/^(?:[-*+]|\x{2022}|\x{2023}|\x{25E6}|\x{2043})\s+/u', '', $line);
		return trim((string) $line);
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

		if (in_array($section, $this->section_level_sections(), true)) {
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

		if (in_array($section, $this->section_level_sections(), true)) {
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

	private function start_processing_for_source_file($path, $source_filename, $mode, $format, $drop_sequences, $include_entity_report, $source_is_persistent, $rescan_sections = false, $rescan_confidence = 84, $rescan_preview_only = false) {
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
			'rescan_sections' => !empty($rescan_sections),
			'rescan_confidence' => max(70, min(98, (int) $rescan_confidence)),
			'rescan_preview_only' => !empty($rescan_preview_only),
			'entity_report' => [
				'entities' => [],
				'new_entities' => [],
			],
			'output_filename' => $this->build_output_filename($source_filename, $format),
			'line_offset' => 0,
			'total_lines' => $this->count_file_lines($path),
			'line_batch_size' => $this->line_batch_size,
			'context' => [
				'section' => 'New Entries',
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
		$valid_sections = $this->valid_sections();
		$section_level_sections = $this->section_level_sections();
		$alias_rules = get_option('pmm_alias_rules', []);
		if (!is_array($alias_rules)) {
			$alias_rules = [];
		}

		foreach ((array) $rows as $row) {
			$section = isset($row['section']) ? trim((string) $row['section']) : 'Notes';
			$entity = isset($row['entity']) ? trim((string) $row['entity']) : '';
			$bullet = isset($row['bullet']) ? trim((string) $row['bullet']) : '';
			if ($bullet === '') {
				continue;
			}

			if (!in_array($section, $valid_sections, true) || !isset($parsed[$section])) {
				$section = 'Notes';
			}

			if ($entity !== '') {
				$entity = $this->resolve_alias_name($entity, $alias_rules);
			}

			if (in_array($section, $section_level_sections, true) || $entity === '') {
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
			$sections = ['Relationships', 'NSFW', 'Notes', 'World Building', 'Technology / Systems', 'Vehicles / Transportation', 'New Entries'];
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
			'vehicles' => 0.70,
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
			'vehicles' => 'pmm_similarity_threshold_vehicles',
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

	private function classification_settings_defaults() {
		return [
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
	}

	private function get_classification_settings_option() {
		$defaults = $this->classification_settings_defaults();
		$stored = get_option('pmm_classification_settings', []);
		if (!is_array($stored)) {
			$stored = [];
		}

		$settings = $defaults;
		$settings['auto_classify_new_entries'] = !empty($stored['auto_classify_new_entries']) ? 1 : 0;
		$settings['strict_prefix_review_mode'] = !empty($stored['strict_prefix_review_mode']) ? 1 : 0;
		$settings['allow_non_prefix_auto_match'] = !empty($stored['allow_non_prefix_auto_match']) ? 1 : 0;
		$settings['character_veto'] = !empty($stored['character_veto']) ? 1 : 0;
		$settings['organizations_min_score'] = isset($stored['organizations_min_score']) ? max(1, min(3, (int) $stored['organizations_min_score'])) : $defaults['organizations_min_score'];
		$settings['locations_min_score'] = isset($stored['locations_min_score']) ? max(1, min(3, (int) $stored['locations_min_score'])) : $defaults['locations_min_score'];
		$settings['technology_min_score'] = isset($stored['technology_min_score']) ? max(1, min(3, (int) $stored['technology_min_score'])) : $defaults['technology_min_score'];
		$settings['vehicles_min_score'] = isset($stored['vehicles_min_score']) ? max(1, min(3, (int) $stored['vehicles_min_score'])) : $defaults['vehicles_min_score'];
		$settings['world_building_min_score'] = isset($stored['world_building_min_score']) ? max(1, min(3, (int) $stored['world_building_min_score'])) : $defaults['world_building_min_score'];

		return $settings;
	}

	private function read_classification_settings_from_request($request) {
		if (!is_array($request)) {
			return $this->get_classification_settings_option();
		}

		$current = $this->get_classification_settings_option();
		$current['auto_classify_new_entries'] = !empty($request['pmm_auto_classify_new_entries']) ? 1 : 0;
		$current['strict_prefix_review_mode'] = !empty($request['pmm_strict_prefix_review_mode']) ? 1 : 0;
		$current['allow_non_prefix_auto_match'] = !empty($request['pmm_allow_non_prefix_auto_match']) ? 1 : 0;
		$current['character_veto'] = !empty($request['pmm_character_fact_veto']) ? 1 : 0;

		$map = [
			'organizations_min_score' => 'pmm_org_min_score',
			'locations_min_score' => 'pmm_location_min_score',
			'technology_min_score' => 'pmm_technology_min_score',
			'vehicles_min_score' => 'pmm_vehicles_min_score',
			'world_building_min_score' => 'pmm_world_building_min_score',
		];

		foreach ($map as $key => $field) {
			if (!isset($request[$field])) {
				continue;
			}
			$current[$key] = max(1, min(3, (int) wp_unslash((string) $request[$field])));
		}

		return $current;
	}

	private function global_entity_report_settings_defaults() {
		return [
			'include_mentions' => 1,
		];
	}

	private function get_global_entity_report_settings_option() {
		$defaults = $this->global_entity_report_settings_defaults();
		$stored = get_option('pmm_global_entity_report_settings', []);
		if (!is_array($stored)) {
			$stored = [];
		}

		$settings = $defaults;
		$settings['include_mentions'] = !empty($stored['include_mentions']) ? 1 : 0;
		return $settings;
	}

	private function read_global_entity_report_settings_from_request($request) {
		if (!is_array($request)) {
			return $this->get_global_entity_report_settings_option();
		}

		$current = $this->get_global_entity_report_settings_option();
		if (isset($request['pmm_global_entity_include_mentions'])) {
			$current['include_mentions'] = !empty($request['pmm_global_entity_include_mentions']) ? 1 : 0;
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
		$reclassification_total_found = 0;
		$reclassification_candidates = $this->build_reclassification_candidates($state['cleaned'], $reclassification_total_found);

		return [
			'entities' => $final_entities,
			'new_entities' => $new_entities,
			'similar_candidates' => $similar_candidates,
			'similar_candidates_total_found' => (int) $similar_total_found,
			'similar_candidates_truncated' => $similar_truncated ? 1 : 0,
			'questionable_entries' => $questionable_entries,
			'questionable_entries_total_found' => (int) $questionable_total_found,
			'reclassification_candidates' => $reclassification_candidates,
			'reclassification_candidates_total_found' => (int) $reclassification_total_found,
		];
	}

	private function extract_entities_by_section($data) {
		$sections = $this->entity_sections();
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
		$sections = $this->entity_sections();
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

		if ($section === 'Vehicles / Transportation') {
			return (float) $thresholds['vehicles'];
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

	private function build_reclassification_candidates($data, &$total_found = 0) {
		$hidden = get_option('pmm_reclassification_hidden_entries', []);
		$hidden = $this->normalize_entry_rule_items($hidden);
		$settings = $this->get_classification_settings_option();
		$candidates = [];

		foreach ((array) $data as $section => $content) {
			if (!is_array($content) || $section === 'New Entries') {
				continue;
			}

			foreach ($content as $entity => $items) {
				if (!is_array($items)) {
					continue;
				}

				$entity_name = (strpos((string) $entity, '__') === 0) ? '' : (string) $entity;
				foreach ($items as $entry) {
					$entry_raw = (string) $entry;
					$entry_for_detection = PMM_Utils::normalize_bullet($entry_raw);
					if ($entry_for_detection === '') {
						continue;
					}

					$key = $this->build_entry_rule_key((string) $section, $entity_name, $entry_raw);
					if (isset($hidden[$key])) {
						continue;
					}

					$suggestion = $this->detect_reclassification_suggestion((string) $section, $entity_name, $entry_for_detection, $settings);
					if ($suggestion === null) {
						continue;
					}

					$candidates[] = [
						'id' => md5($key),
						'original_section' => (string) $section,
						'original_entity' => $entity_name,
						'original_entry' => $entry_raw,
						'target_section' => $suggestion['section'],
						'target_entity' => $suggestion['entity'],
						'reason' => $suggestion['reason'],
						'confidence' => (int) $suggestion['confidence'],
					];
				}
			}
		}

		usort($candidates, static function($a, $b) {
			if ((int) $a['confidence'] === (int) $b['confidence']) {
				return strcmp((string) $a['original_entry'], (string) $b['original_entry']);
			}
			return ((int) $a['confidence'] > (int) $b['confidence']) ? -1 : 1;
		});

		$total_found = count($candidates);
		return array_slice($candidates, 0, 120);
	}

	private function detect_reclassification_suggestion($section, $entity, $entry, $settings) {
		$looks_like_character_fact = !empty($settings['character_veto']) && $this->reclassification_looks_like_character_fact($entry);
		if ($looks_like_character_fact && $section !== 'Characters') {
			$target_entity = $entity !== '' ? $entity : $this->extract_leading_name_for_reclassification($entry);
			return [
				'section' => 'Characters',
				'entity' => $target_entity !== '' ? $target_entity : 'Unsorted Inbox',
				'reason' => 'character-style fact detected outside Characters',
				'confidence' => 91,
			];
		}

		$scores = [
			'Organizations' => $this->reclassification_organization_score($entry),
			'Locations' => $this->reclassification_location_score($entry),
			'Technology / Systems' => $this->reclassification_technology_score($entry),
			'Vehicles / Transportation' => $this->reclassification_vehicle_score($entry),
			'World Building' => $this->reclassification_world_building_score($entry),
		];
		$minimums = [
			'Organizations' => max(1, min(3, (int) $settings['organizations_min_score'])),
			'Locations' => max(1, min(3, (int) $settings['locations_min_score'])),
			'Technology / Systems' => max(1, min(3, (int) $settings['technology_min_score'])),
			'Vehicles / Transportation' => max(1, min(3, (int) $settings['vehicles_min_score'])),
			'World Building' => max(1, min(3, (int) $settings['world_building_min_score'])),
		];

		$current_score = isset($scores[$section]) ? (int) $scores[$section] : 0;
		$best_section = null;
		$best_score = 0;
		foreach ($scores as $candidate_section => $score) {
			if ($candidate_section === $section || $score < $minimums[$candidate_section]) {
				continue;
			}
			if ($score > $best_score) {
				$best_section = $candidate_section;
				$best_score = $score;
			}
		}

		if ($best_section === null || $best_score <= $current_score) {
			return null;
		}

		$target_entity = '';
		if (!in_array($best_section, $this->section_level_sections(), true)) {
			$target_entity = $entity !== '' ? $entity : $this->extract_leading_name_for_reclassification($entry);
			if ($target_entity === '') {
				$target_entity = ($best_section === 'Vehicles / Transportation') ? 'Unsorted Vehicle' : 'Unsorted Inbox';
			}
		}

		return [
			'section' => $best_section,
			'entity' => $target_entity,
			'reason' => 'stronger ' . strtolower($best_section) . ' signal than current section',
			'confidence' => min(98, 70 + ($best_score * 8) + max(0, ($best_score - $current_score) * 4)),
		];
	}

	private function reclassification_looks_like_character_fact($entry) {
		return preg_match('/\b(he|she|they|him|her|his|hers|their|theirs|years? old|hair|eyes|voice|smile|wears|said|asked|replied|married|dating|mother|father|sister|brother|friend|lover)\b/i', (string) $entry) === 1;
	}

	private function reclassification_organization_score($entry) {
		$score = 0;
		if (preg_match('/\b(company|corporation|corp|agency|council|guild|institute|foundation|committee|department|bureau|division|board|staff|charter|mandate)\b/ui', (string) $entry)) {
			++$score;
		}
		if (preg_match('/\b(headquartered|subsidiary|operations|funding|contract|employees|director|ceo)\b/ui', (string) $entry)) {
			++$score;
		}
		return $score;
	}

	private function reclassification_location_score($entry) {
		$score = 0;
		if (preg_match('/\b(city|town|village|station|base|campus|facility|compound|bunker|street|avenue|port|harbor|district|region|sector|tower|suite|room)\b/ui', (string) $entry)) {
			++$score;
		}
		if (preg_match('/\b(located|situated|coordinates|terrain|climate|population|entrance|exit|nearby|surrounding)\b/ui', (string) $entry)) {
			++$score;
		}
		return $score;
	}

	private function reclassification_technology_score($entry) {
		$score = 0;
		if (preg_match('/\b(protocol|interface|system|drone|neural|software|hardware|algorithm|sensor|reactor|database|network|platform|device|framework|telemetry|autopilot)\b/ui', (string) $entry)) {
			++$score;
		}
		if (preg_match('/\b(version|model|spec|build|release|patch|upgrade|latency|throughput|diagnostic|integration|deployment)\b/ui', (string) $entry)) {
			++$score;
		}
		return $score;
	}

	private function reclassification_vehicle_score($entry) {
		$score = 0;
		if (preg_match('/\b(ship|shuttle|fighter|freighter|carrier|bike|car|truck|train|jet|plane|aircraft|vtol|dropship|mech|tank|boat|vessel|transport|hovercraft|speeder)\b/ui', (string) $entry)) {
			++$score;
		}
		if (preg_match('/\b(cockpit|hangar|crew|passengers|cargo|hull|fuel|range|route|fleet|pilot|docks|launches|lands)\b/ui', (string) $entry)) {
			++$score;
		}
		return $score;
	}

	private function reclassification_world_building_score($entry) {
		$score = 0;
		if (preg_match('/\b(law|custom|tradition|culture|religion|economy|currency|history|myth|legend|calendar|festival|era|timeline|government|politics|society|magic system|setting rule|world rule)\b/ui', (string) $entry)) {
			++$score;
		}
		if (preg_match('/\b(people believe|it is common|it is forbidden|it is legal|it is illegal|traditionally|by custom|by law|historically)\b/ui', (string) $entry)) {
			++$score;
		}
		return $score;
	}

	private function extract_leading_name_for_reclassification($entry) {
		if (preg_match('/^([A-Z][A-Za-z0-9_\-()\'\/.& ]{1,80}?)(?::|\s+-\s+|\s+is\b)/u', (string) $entry, $matches)) {
			return trim((string) $matches[1]);
		}

		return '';
	}

	private function auto_reclassify_cleaned_entries($cleaned, $min_confidence = 84, $preview_only = false) {
		if (!is_array($cleaned) || empty($cleaned)) {
			return [
				'data' => is_array($cleaned) ? $cleaned : [],
				'evaluated' => 0,
				'moved' => 0,
				'proposed' => 0,
				'preview_rows' => [],
			];
		}

		$settings = $this->get_classification_settings_option();
		$min_confidence = max(70, min(98, (int) $min_confidence));
		$evaluated = 0;
		$moved = 0;
		$proposed = 0;
		$preview_rows = [];
		$snapshot = $cleaned;

		foreach ((array) $snapshot as $section => $content) {
			if (!is_array($content) || $section === 'New Entries') {
				continue;
			}

			foreach ((array) $content as $entity => $items) {
				if (!is_array($items)) {
					continue;
				}

				$entity_name = (strpos((string) $entity, '__') === 0) ? '' : (string) $entity;
				foreach ((array) $items as $entry) {
					$entry_raw = (string) $entry;
					$entry_for_detection = PMM_Utils::normalize_bullet($entry_raw);
					if ($entry_for_detection === '') {
						continue;
					}

					++$evaluated;
					$suggestion = $this->detect_reclassification_suggestion((string) $section, $entity_name, $entry_for_detection, $settings);
					if ($suggestion === null) {
						continue;
					}

					$confidence = isset($suggestion['confidence']) ? (int) $suggestion['confidence'] : 0;
					if ($confidence < $min_confidence) {
						continue;
					}

					$target_section = isset($suggestion['section']) ? (string) $suggestion['section'] : (string) $section;
					$target_entity = isset($suggestion['entity']) ? trim((string) $suggestion['entity']) : $entity_name;
					if (in_array($target_section, $this->section_level_sections(), true)) {
						$target_entity = '';
					} elseif ($target_entity === '') {
						$target_entity = $entity_name !== '' ? $entity_name : 'Unsorted Inbox';
					}

					++$proposed;
					if (count($preview_rows) < 120) {
						$preview_rows[] = [
							'from_section' => (string) $section,
							'from_entity' => $entity_name,
							'to_section' => $target_section,
							'to_entity' => $target_entity,
							'entry' => $entry_raw,
							'confidence' => $confidence,
							'reason' => isset($suggestion['reason']) ? (string) $suggestion['reason'] : '',
						];
					}

					if ($preview_only) {
						continue;
					}

					if ($this->move_entry_in_cleaned($cleaned, (string) $section, $entity_name, $entry_raw, $target_section, $target_entity, $entry_raw)) {
						++$moved;
					}
				}
			}
		}

		return [
			'data' => $cleaned,
			'evaluated' => $evaluated,
			'moved' => $moved,
			'proposed' => $proposed,
			'preview_rows' => $preview_rows,
		];
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
		$from_entry = (string) $from_entry;
		$to_section = trim((string) $to_section);
		$to_entity = trim((string) $to_entity);
		$to_entry = (string) $to_entry;

		if ($from_section === '' || $to_section === '' || trim($from_entry) === '' || trim($to_entry) === '') {
			return false;
		}

		if (!isset($cleaned[$from_section]) || !is_array($cleaned[$from_section])) {
			return false;
		}

		$from_key = in_array($from_section, $this->section_level_sections(), true) || $from_entity === '' ? '__entries__' : $from_entity;
		if (!isset($cleaned[$from_section][$from_key]) || !is_array($cleaned[$from_section][$from_key])) {
			return false;
		}

		$source_items = (array) $cleaned[$from_section][$from_key];
		$target_entry_fp = PMM_Utils::fingerprint($from_entry);
		$removed = false;
		$new_source = [];
		foreach ($source_items as $item) {
			$item_text = (string) $item;
			if (!$removed && PMM_Utils::fingerprint($item_text) === $target_entry_fp) {
				$removed = true;
				continue;
			}
			$new_source[] = $item;
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
		$to_key = in_array($to_section, $this->section_level_sections(), true) || $to_entity === '' ? '__entries__' : $to_entity;
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

	private function remove_entry_from_cleaned(&$cleaned, $section, $entity, $entry) {
		$section = trim((string) $section);
		$entity = trim((string) $entity);
		$entry = (string) $entry;
		if ($section === '' || trim($entry) === '') {
			return false;
		}

		if (!isset($cleaned[$section]) || !is_array($cleaned[$section])) {
			return false;
		}

		$key = in_array($section, $this->section_level_sections(), true) || $entity === '' ? '__entries__' : $entity;
		if (!isset($cleaned[$section][$key]) || !is_array($cleaned[$section][$key])) {
			return false;
		}

		$target_fp = PMM_Utils::fingerprint($entry);
		$removed = false;
		$new_items = [];
		foreach ((array) $cleaned[$section][$key] as $item) {
			$item_text = (string) $item;
			if (!$removed && PMM_Utils::fingerprint($item_text) === $target_fp) {
				$removed = true;
				continue;
			}
			$new_items[] = $item;
		}

		if (!$removed) {
			return false;
		}

		$cleaned[$section][$key] = $new_items;
		if (empty($new_items) && strpos((string) $key, '__') !== 0) {
			unset($cleaned[$section][$key]);
		}

		return true;
	}

	private function prune_entity_entry_list($items, $max_keep, $remove_stale, $similarity_threshold, &$stats, &$report = null, $remove_unreferenced = false, $known_entities = [], $unreferenced_threshold = 0.60, $critical_rules = [], $section = '', $entity = '', $require_entity_name_match = false) {
		$items = array_values(array_filter(array_map(static function($item) {
			return (string) $item;
		}, (array) $items), static function($item) {
			return trim((string) $item) !== '';
		}));

		if (empty($items)) {
			return [];
		}

		$critical_rules = $this->normalize_entry_rule_items($critical_rules);

		$exact_seen = [];
		$critical_seen = [];
		$critical_items = [];
		$deduped = [];
		for ($i = count($items) - 1; $i >= 0; $i--) {
			$entry = $items[$i];
			$fp = PMM_Utils::fingerprint($entry);
			if ($fp === '') {
				continue;
			}

			if ($this->is_prune_critical_entry($section, $entity, $entry, $critical_rules)) {
				if (isset($critical_seen[$fp])) {
					continue;
				}
				$critical_seen[$fp] = true;
				$critical_items[] = $entry;
				continue;
			}

			if (isset($exact_seen[$fp])) {
				++$stats['exact_duplicates'];
				if (is_array($report)) {
					$report['exact_duplicates'][] = $entry;
				}
				continue;
			}

			$is_near_duplicate = false;
			foreach ($deduped as $kept) {
				if ($this->entries_are_similar($entry, $kept, $similarity_threshold)) {
					$is_near_duplicate = true;
					break;
				}
			}

			if ($is_near_duplicate) {
				++$stats['near_duplicates'];
				if (is_array($report)) {
					$report['near_duplicates'][] = $entry;
				}
				continue;
			}

			$exact_seen[$fp] = true;
			$deduped[] = $entry;
		}

		$critical_items = array_reverse($critical_items);
		$deduped = array_reverse($deduped);

		if ($remove_stale) {
			$fresh = [];
			foreach ($deduped as $entry) {
				if ($this->is_likely_stale_entry($entry)) {
					++$stats['stale_removed'];
					if (is_array($report)) {
						$report['stale_removed'][] = $entry;
					}
					continue;
				}
				$fresh[] = $entry;
			}
			$deduped = $fresh;
		}

		if ($remove_unreferenced && !empty($known_entities)) {
			$referenced = [];
			foreach ($deduped as $entry) {
				if ($this->entry_references_any_known_entity($entry, $known_entities, $unreferenced_threshold)) {
					$referenced[] = $entry;
					continue;
				}
				++$stats['unreferenced_removed'];
				if (is_array($report)) {
					$report['unreferenced_removed'][] = $entry;
				}
			}
			$deduped = $referenced;
		}

		if ($require_entity_name_match && trim((string) $entity) !== '' && !in_array((string) $section, $this->section_level_sections(), true)) {
			$matched = [];
			foreach ($deduped as $entry) {
				if ($this->entry_references_target_entity($entry, $entity)) {
					$matched[] = $entry;
					continue;
				}
				++$stats['entity_name_mismatch_removed'];
				if (is_array($report)) {
					$report['entity_name_mismatch_removed'][] = $entry;
				}
			}
			$deduped = $matched;
		}

		$remaining_keep = max(0, (int) $max_keep - count($critical_items));
		if (count($deduped) > $remaining_keep) {
			$stats['trimmed'] = count($deduped) - $remaining_keep;
			$deduped = $this->select_entries_for_prune_keep($deduped, $remaining_keep, $stats, $report);
		}

		$stats['critical_preserved'] = (int) ($stats['critical_preserved'] ?? 0) + count($critical_items);

		return array_values(array_merge($critical_items, $deduped));
	}

	private function build_prune_review_candidates($items, $report) {
		$current_items = array_values((array) $items);
		$report = is_array($report) ? $report : [];
		$group_labels = [
			'exact_duplicates' => __('Exact duplicate', 'perchance-memory-manager'),
			'near_duplicates' => __('Near duplicate', 'perchance-memory-manager'),
			'stale_removed' => __('Stale candidate', 'perchance-memory-manager'),
			'unreferenced_removed' => __('Unreferenced candidate', 'perchance-memory-manager'),
			'entity_name_mismatch_removed' => __('Missing selected entity name', 'perchance-memory-manager'),
			'trimmed' => __('Trimmed by cap', 'perchance-memory-manager'),
		];

		$used_indexes = [];
		$rows = [];
		foreach ($group_labels as $group_key => $label) {
			$candidates = isset($report[$group_key]) && is_array($report[$group_key]) ? array_values($report[$group_key]) : [];
			foreach ($candidates as $candidate) {
				$entry = trim((string) $candidate);
				if ($entry === '') {
					continue;
				}

				$match_index = -1;
				foreach ($current_items as $idx => $value) {
					if (isset($used_indexes[$idx])) {
						continue;
					}
					if (trim((string) $value) !== $entry) {
						continue;
					}
					$match_index = (int) $idx;
					break;
				}

				if ($match_index < 0) {
					continue;
				}

				$used_indexes[$match_index] = true;
				$rows[] = [
					'source_index' => $match_index,
					'source_entry' => $entry,
					'reason' => $group_key,
					'reason_label' => $label,
				];
			}
		}

		usort($rows, static function($a, $b) {
			return ((int) ($a['source_index'] ?? 0) <=> (int) ($b['source_index'] ?? 0));
		});

		return $rows;
	}

	private function collect_nonprefix_entity_entries($items, $entity) {
		$entity = trim((string) $entity);
		if ($entity === '') {
			return [];
		}

		$rows = [];
		foreach (array_values((array) $items) as $idx => $entry) {
			$entry = trim((string) $entry);
			if ($entry === '') {
				continue;
			}
			if ($this->entry_starts_with_entity_name($entry, $entity)) {
				continue;
			}
			$rows[] = [
				'source_index' => (int) $idx,
				'source_entry' => $entry,
			];
		}

		return $rows;
	}

	private function entry_starts_with_entity_name($entry, $entity) {
		$entry = trim(str_replace('*', '', (string) $entry));
		$entity = trim(str_replace('*', '', (string) $entity));
		if ($entry === '' || $entity === '') {
			return false;
		}

		$pattern = '/^' . preg_quote($entity, '/') . '(?=$|\s|[:;,.!?\-\(\)\[\]\'\x{2019}])/iu';
		return preg_match($pattern, $entry) === 1;
	}

	private function get_prune_critical_entry_rules() {
		$rules = get_option('pmm_prune_critical_entries', []);
		return $this->normalize_entry_rule_items(is_array($rules) ? $rules : []);
	}

	private function save_prune_critical_entry_rules($rules) {
		update_option('pmm_prune_critical_entries', $this->normalize_entry_rule_items($rules), false);
	}

	private function is_prune_critical_entry($section, $entity, $entry, $critical_rules) {
		$key = $this->build_entry_rule_key($section, $entity, $entry);
		return is_array($critical_rules) && isset($critical_rules[$key]);
	}

	private function collect_known_entity_names($cleaned) {
		$names = [];
		foreach ($this->entity_sections() as $section) {
			if (empty($cleaned[$section]) || !is_array($cleaned[$section])) {
				continue;
			}
			foreach ($cleaned[$section] as $entity => $items) {
				if (strpos((string) $entity, '__') === 0) {
					continue;
				}
				$name = trim((string) $entity);
				if ($name !== '') {
					$names[$name] = true;
				}
			}
		}

		return array_keys($names);
	}

	private function entry_references_any_known_entity($entry, $known_entities, $threshold) {
		$entry = (string) $entry;
		if (trim($entry) === '' || empty($known_entities)) {
			return false;
		}

		foreach ((array) $known_entities as $entity_name) {
			if (PMM_Utils::contains_name_score($entry, (string) $entity_name) >= (float) $threshold) {
				return true;
			}
		}

		return false;
	}

	private function entry_references_target_entity($entry, $entity) {
		$entry = trim(str_replace('*', '', (string) $entry));
		$entity = trim(str_replace('*', '', (string) $entity));
		if ($entry === '' || $entity === '') {
			return false;
		}

		if ($this->entry_starts_with_entity_name($entry, $entity)) {
			return true;
		}

		$pattern = '/(?<![\w\-])' . preg_quote($entity, '/') . '(?![\w\-])/iu';
		if (preg_match($pattern, $entry) === 1) {
			return true;
		}

		return PMM_Utils::contains_name_score($entry, $entity) >= 0.70;
	}

	private function entries_are_similar($a, $b, $threshold) {
		$fpA = PMM_Utils::fingerprint((string) $a);
		$fpB = PMM_Utils::fingerprint((string) $b);
		if ($fpA === '' || $fpB === '') {
			return false;
		}

		$lenA = strlen($fpA);
		$lenB = strlen($fpB);
		$maxLen = max($lenA, $lenB);
		if ($maxLen <= 0) {
			return false;
		}
		if (abs($lenA - $lenB) > max(20, (int) floor($maxLen * 0.45))) {
			return false;
		}

		if ($fpA === $fpB) {
			return true;
		}

		$jaccard = PMM_Utils::jaccard_similarity($fpA, $fpB);
		if ($jaccard >= (float) $threshold) {
			return true;
		}

		if ($this->entries_repeat_fact_theme((string) $a, (string) $b, $fpA, $fpB)) {
			return true;
		}

		$distance = levenshtein($fpA, $fpB);
		$ratio = max(0.0, 1.0 - ($distance / $maxLen));
		return $ratio >= ((float) $threshold + 0.02);
	}

	private function entries_repeat_fact_theme($a, $b, $fpA, $fpB) {
		$tokensA = $this->thematic_token_set($fpA);
		$tokensB = $this->thematic_token_set($fpB);
		if (count($tokensA) < 3 || count($tokensB) < 3) {
			return false;
		}

		$overlap = array_intersect_key($tokensA, $tokensB);
		$overlap_count = count($overlap);
		if ($overlap_count < 3) {
			return false;
		}

		$short_coverage = $overlap_count / max(1, min(count($tokensA), count($tokensB)));
		$long_coverage = $overlap_count / max(1, max(count($tokensA), count($tokensB)));
		if ($short_coverage >= 0.86) {
			return true;
		}

		$bigramsA = $this->token_bigram_set(array_keys($tokensA));
		$bigramsB = $this->token_bigram_set(array_keys($tokensB));
		$bigram_overlap = count(array_intersect_key($bigramsA, $bigramsB));
		$fact_signal = $this->entry_has_fact_or_location_signal($a) && $this->entry_has_fact_or_location_signal($b);

		return $fact_signal && $short_coverage >= 0.72 && $long_coverage >= 0.50 && $bigram_overlap >= 1;
	}

	private function thematic_token_set($fp_text) {
		$parts = array_values(array_filter(preg_split('/\s+/u', (string) $fp_text), static function($token) {
			return is_string($token) && $token !== '';
		}));

		$stop = [
			'the' => true,
			'a' => true,
			'an' => true,
			'and' => true,
			'or' => true,
			'but' => true,
			'for' => true,
			'with' => true,
			'from' => true,
			'this' => true,
			'that' => true,
			'these' => true,
			'those' => true,
			'into' => true,
			'onto' => true,
			'about' => true,
			'just' => true,
			'very' => true,
			'also' => true,
			'been' => true,
			'being' => true,
			'were' => true,
			'was' => true,
			'is' => true,
			'are' => true,
			'to' => true,
			'of' => true,
			'in' => true,
			'on' => true,
			'at' => true,
			'by' => true,
			'as' => true,
			'it' => true,
			'its' => true,
			'their' => true,
			'they' => true,
			'them' => true,
			'he' => true,
			'she' => true,
			'his' => true,
			'her' => true,
		];

		$set = [];
		foreach ($parts as $token) {
			if (strlen($token) < 3) {
				continue;
			}
			if (isset($stop[$token])) {
				continue;
			}
			$set[$token] = true;
		}

		return $set;
	}

	private function token_bigram_set($tokens) {
		$tokens = array_values((array) $tokens);
		$set = [];
		for ($i = 0; $i < count($tokens) - 1; $i++) {
			$left = trim((string) $tokens[$i]);
			$right = trim((string) $tokens[$i + 1]);
			if ($left === '' || $right === '') {
				continue;
			}
			$set[$left . ' ' . $right] = true;
		}

		return $set;
	}

	private function entry_has_fact_or_location_signal($entry) {
		$entry = trim((string) $entry);
		if ($entry === '') {
			return false;
		}

		if (preg_match('/\b(located|based|resides|lives|headquartered|stationed|born|raised|from|near|inside|outside|across|north|south|east|west)\b/iu', $entry) === 1) {
			return true;
		}

		if (preg_match('/\b(city|town|village|district|region|province|kingdom|country|island|bay|harbor|port|station|base|facility|building|temple|palace|forest|desert|mountain|river|valley|planet|moon|sector|zone)\b/iu', $entry) === 1) {
			return true;
		}

		if (preg_match('/\b(works|serves|leads|controls|owns|operates|belongs|member|allied|enemy|family|objective|mission|status|condition)\b/iu', $entry) === 1) {
			return true;
		}

		return preg_match('/\b\d{2,4}\b/u', $entry) === 1;
	}

	private function is_likely_stale_entry($entry) {
		$entry = trim((string) $entry);
		if ($entry === '') {
			return true;
		}

		$patterns = [
			'/\b(formerly|used to|no longer|obsolete|deprecated|retired|decommissioned|outdated|old version|previously)\b/i',
			'/\b(tbd|todo|placeholder|unknown|n\/a|temp)\b/i',
			'/\b(before the update|pre-reset|prior canon)\b/i',
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $entry) === 1) {
				return true;
			}
		}

		return false;
	}

	private function select_entries_for_prune_keep($entries, $max_keep, &$stats, &$report = null) {
		$total = count((array) $entries);
		if ($total <= $max_keep) {
			return array_values((array) $entries);
		}

		$scored = [];
		$critical_count = 0;
		foreach (array_values((array) $entries) as $idx => $entry) {
			$entry_raw = (string) $entry;
			$entry_for_scoring = PMM_Utils::normalize_bullet($entry_raw);
			if ($entry_for_scoring === '') {
				continue;
			}

			$score_data = $this->entry_prune_importance_score($entry_for_scoring, $idx, $total);
			if ($score_data['critical']) {
				++$critical_count;
			}
			$scored[] = [
				'index' => $idx,
				'entry' => $entry_raw,
				'score' => $score_data['score'],
				'critical' => $score_data['critical'],
			];
		}

		usort($scored, static function($a, $b) {
			if ((float) $a['score'] === (float) $b['score']) {
				return ((int) $a['index'] > (int) $b['index']) ? -1 : 1;
			}
			return ((float) $a['score'] > (float) $b['score']) ? -1 : 1;
		});

		$selected = array_slice($scored, 0, $max_keep);
		$selected_indexes = [];
		$critical_kept = 0;
		foreach ($selected as $row) {
			$selected_indexes[(int) $row['index']] = true;
			if (!empty($row['critical'])) {
				++$critical_kept;
			}
		}

		$stats['critical_preserved'] = $critical_count > 0 ? $critical_kept : 0;

		$kept = [];
		foreach (array_values((array) $entries) as $idx => $entry) {
			$entry_raw = (string) $entry;
			if (trim($entry_raw) === '') {
				continue;
			}
			if (!isset($selected_indexes[$idx])) {
				if (is_array($report)) {
					$report['trimmed'][] = $entry_raw;
				}
				continue;
			}
			$kept[] = $entry_raw;
		}

		return $kept;
	}

	private function entry_prune_importance_score($entry, $index, $total) {
		$entry = trim((string) $entry);
		$score = 0.0;
		$critical = false;

		$critical_patterns = [
			'/\b(name|alias|identity|role|occupation|objective|goal|motivation|personality|trait|relationship|family|ally|enemy|ability|skill|power|weakness|limitation|status|condition|location|residence|inventory|equipment|history|backstory|canon|current|now)\b/i',
			'/\b(age|birthday|pronouns?|title|rank|faction|affiliation)\b/i',
		];
		foreach ($critical_patterns as $pattern) {
			if (preg_match($pattern, $entry) === 1) {
				$score += 6.0;
				$critical = true;
				break;
			}
		}

		$word_count = str_word_count($entry);
		if ($word_count >= 10) {
			$score += 1.5;
		}
		if ($word_count >= 18) {
			$score += 1.5;
		}
		if ($word_count >= 28) {
			$score += 1.0;
		}

		if (preg_match('/\b\d{2,4}\b/', $entry) === 1) {
			$score += 0.8;
		}

		if (preg_match('/\b(maybe|possibly|perhaps|unsure|unknown|rumor|rumour|speculation)\b/i', $entry) === 1) {
			$score -= 1.5;
		}

		if ($this->is_likely_stale_entry($entry)) {
			$score -= 5.0;
		}

		if ($total > 1) {
			$position_ratio = (float) $index / (float) ($total - 1);

			// Preserve early introduction context while still preferring newer facts.
			$intro_window = min(120, max(40, (int) floor($total * 0.12)));
			if ($index < $intro_window) {
				$intro_ratio = 1.0 - ((float) $index / max(1.0, (float) $intro_window));
				$score += 2.2 * $intro_ratio;
			}

			// Newest entries are least likely stale; give them stronger keep priority.
			$score += 5.0 * ($position_ratio * $position_ratio);
			if ($position_ratio >= 0.80) {
				$score += 2.5;
			}
		}

		return [
			'score' => $score,
			'critical' => $critical,
		];
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

	private function build_alias_substitution_pairs($alias_rules) {
		$pairs = [];
		foreach ((array) $alias_rules as $source => $canonical) {
			$source = trim((string) $source);
			$canonical = trim((string) $canonical);
			if ($source === '' || $canonical === '') {
				continue;
			}

			// Skip fingerprint-style keys because they are not intended for direct text replacement.
			if (strpos($source, ' ') === false && $source === PMM_Utils::name_fingerprint($source) && !preg_match('/[A-Z]/', $source)) {
				continue;
			}

			if (mb_strtolower($source) === mb_strtolower($canonical)) {
				continue;
			}

			$pairs[] = [
				'alias' => $source,
				'canonical' => $canonical,
			];
		}

		if (empty($pairs)) {
			return [];
		}

		usort($pairs, static function ($a, $b) {
			return mb_strlen((string) $b['alias']) - mb_strlen((string) $a['alias']);
		});

		return $pairs;
	}

	private function apply_alias_substitutions_to_text($text, $pairs) {
		$text = (string) $text;
		if ($text === '' || empty($pairs) || !is_array($pairs)) {
			return $text;
		}

		foreach ($pairs as $pair) {
			$alias = isset($pair['alias']) ? trim((string) $pair['alias']) : '';
			$canonical = isset($pair['canonical']) ? trim((string) $pair['canonical']) : '';
			if ($alias === '' || $canonical === '') {
				continue;
			}

			$pattern = '/(?<![\\w\-])' . preg_quote($alias, '/') . '(?![\\w\-])/ui';

			if ($this->is_ambiguous_single_token_person_alias($alias, $canonical)) {
				$pattern = '/(?<![\\w\-])' . preg_quote($alias, '/') . '(?![\\w\-])(?!\\s+(?:technologies|technology|systems|labs?|industries|inc|corp(?:oration)?|company|group|holdings|logistics|transport(?:ation)?|transit|motors|dynamics)\\b)/ui';
			}

			// Prevent repeated expansion when canonical starts with the alias,
			// e.g. "Genesis" -> "Genesis Technologies" across multiple passes.
			if (preg_match('/^' . preg_quote($alias, '/') . '(?:(\\s+.+))?$/ui', $canonical, $m) === 1) {
				$suffix = isset($m[1]) ? trim((string) $m[1]) : '';
				if ($suffix !== '') {
					$pattern = '/(?<![\\w\-])' . preg_quote($alias, '/') . '(?![\\w\-])(?!\\s+' . preg_quote($suffix, '/') . '(?![\\w\-]))/ui';
				}
			}

			$updated = preg_replace($pattern, $canonical, $text);
			if ($updated === null) {
				continue;
			}
			$text = $updated;
		}

		return $text;
	}

	private function is_ambiguous_single_token_person_alias($alias, $canonical) {
		$alias = trim((string) $alias);
		$canonical = trim((string) $canonical);

		if ($alias === '' || $canonical === '') {
			return false;
		}

		if (preg_match('/\s/u', $alias) === 1) {
			return false;
		}

		return $this->is_person_like_name($canonical);
	}

	private function is_person_like_name($name) {
		$name = trim((string) $name);
		if ($name === '') {
			return false;
		}

		$parts = preg_split('/\s+/u', $name);
		if (!is_array($parts) || count($parts) < 2) {
			return false;
		}

		foreach ($parts as $part) {
			$part = trim((string) $part);
			if ($part === '') {
				return false;
			}
			if ($this->is_organization_indicator_word($part)) {
				return false;
			}
		}

		return true;
	}

	private function is_organization_indicator_word($word) {
		$word = mb_strtolower(trim((string) $word));
		if ($word === '') {
			return false;
		}

		return preg_match('/^(technologies|technology|systems?|labs?|industries|inc|corp|corporation|company|group|holdings|logistics|transport|transportation|transit|motors|dynamics)$/u', $word) === 1;
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

	private function pick_canonical_name($a, $b) {
		$a = trim((string) $a);
		$b = trim((string) $b);

		if ($a === '') {
			return $b;
		}

		if ($b === '') {
			return $a;
		}

		return $this->choose_canonical_name($a, $b);
	}

	private function valid_sections() {
		return ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation', 'World Building', 'Relationships', 'NSFW', 'Notes'];
	}

	private function entity_sections() {
		return ['Characters', 'Organizations', 'Locations'];
	}

	private function section_level_sections() {
		return ['Notes', 'Relationships', 'NSFW', 'World Building', 'Technology / Systems', 'Vehicles / Transportation'];
	}

	private function start_reprocess_from_last_output($rescan_sections = null, $rescan_confidence = null, $rescan_preview_only = null) {
		$data = $this->get_last_output_data_for_editing();
		if (empty($data['content'])) {
			return false;
		}

		$content = (string) $data['content'];
		$mode = isset($data['stats']['mode']) ? (string) $data['stats']['mode'] : 'balanced';
		$format = isset($data['stats']['format']) ? (string) $data['stats']['format'] : 'txt';
		$filename = isset($data['stats']['original_filename']) ? (string) $data['stats']['original_filename'] : 'memory.txt';

		$drop_sequences = get_option('pmm_drop_sequences', []);
		if (!is_array($drop_sequences)) {
			$drop_sequences = [];
		}

		$include_entity_report = get_option('pmm_include_entity_report', '0') === '1';
		if ($rescan_sections === null) {
			$rescan_sections = false;
		}
		if ($rescan_confidence === null) {
			$rescan_confidence = (int) get_option('pmm_rescan_confidence', 84);
		}
		if ($rescan_preview_only === null) {
			$rescan_preview_only = ((int) get_option('pmm_rescan_preview_only', 0)) === 1;
		}
		update_option('pmm_rescan_confidence', max(70, min(98, (int) $rescan_confidence)), false);
		update_option('pmm_rescan_preview_only', !empty($rescan_preview_only) ? 1 : 0, false);

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
			'rescan_sections' => !empty($rescan_sections),
			'rescan_confidence' => max(70, min(98, (int) $rescan_confidence)),
			'rescan_preview_only' => !empty($rescan_preview_only),
			'entity_report' => [
				'entities' => [],
				'new_entities' => [],
			],
			'output_filename' => $this->build_output_filename($filename, $format),
			'line_offset' => 0,
			'total_lines' => $this->count_file_lines($source_path),
			'line_batch_size' => $this->line_batch_size,
			'context' => [
				'section' => 'New Entries',
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

			$source = $this->normalize_alias_rule_token($parts[0]);
			$canonical = $this->normalize_alias_rule_token($parts[1]);
			if ($source !== '' && $canonical !== '') {
				$rules[$source] = $canonical;
			}
		}

		return $this->normalize_alias_rules($rules);
	}

	private function normalize_alias_rules($rules) {
		$out = [];
		foreach ((array) $rules as $source => $canonical) {
			$source = $this->normalize_alias_rule_token($source);
			$canonical = $this->normalize_alias_rule_token($canonical);
			if ($source === '' || $canonical === '') {
				continue;
			}
			$out[$source] = $canonical;
		}

		return $out;
	}

	private function normalize_alias_rule_token($value) {
		$value = trim((string) $value);
		$value = preg_replace('/^[\-*•]\s+/u', '', (string) $value);

		if (preg_match('/^(["\'])(.*)\1$/u', $value, $m) === 1) {
			$value = trim((string) $m[2]);
		}

		return sanitize_text_field($value);
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