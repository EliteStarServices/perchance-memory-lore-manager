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
		$entity_saved = isset($_GET['pmm_entity_saved']) ? (int) $_GET['pmm_entity_saved'] : -1;
		$questionable_saved = isset($_GET['pmm_questionable_saved']) ? (int) $_GET['pmm_questionable_saved'] : -1;
		$questionable_reviewed = isset($_GET['pmm_questionable_reviewed']) ? (int) $_GET['pmm_questionable_reviewed'] : -1;
		$questionable_changed = isset($_GET['pmm_questionable_changed']) ? (int) $_GET['pmm_questionable_changed'] : -1;
		$questionable_removed = isset($_GET['pmm_questionable_removed']) ? (int) $_GET['pmm_questionable_removed'] : 0;
		$questionable_hidden = isset($_GET['pmm_questionable_hidden']) ? (int) $_GET['pmm_questionable_hidden'] : 0;
		$questionable_kept = isset($_GET['pmm_questionable_kept']) ? (int) $_GET['pmm_questionable_kept'] : 0;
		$questionable_updated = isset($_GET['pmm_questionable_updated']) ? (int) $_GET['pmm_questionable_updated'] : 0;
		$questionable_truncated = isset($_GET['pmm_questionable_truncated']) ? (int) $_GET['pmm_questionable_truncated'] : 0;
		$questionable_expected_count = isset($_GET['pmm_questionable_expected_count']) ? (int) $_GET['pmm_questionable_expected_count'] : 0;
		$hidden_updated = isset($_GET['pmm_hidden_updated']) ? (int) $_GET['pmm_hidden_updated'] : -1;
		$entity_reviewed = isset($_GET['pmm_entity_reviewed']) ? (int) $_GET['pmm_entity_reviewed'] : -1;
		$entity_truncated = isset($_GET['pmm_entity_truncated']) ? (int) $_GET['pmm_entity_truncated'] : 0;
		$entity_expected_count = isset($_GET['pmm_entity_expected_count']) ? (int) $_GET['pmm_entity_expected_count'] : 0;
		$raw_previewed = isset($_GET['pmm_raw_previewed']) ? (int) $_GET['pmm_raw_previewed'] : -1;
		$raw_staged = isset($_GET['pmm_raw_staged']) ? (int) $_GET['pmm_raw_staged'] : -1;
		$raw_cleared = isset($_GET['pmm_raw_cleared']) ? (int) $_GET['pmm_raw_cleared'] : 0;
		$entity_updated = isset($_GET['pmm_entity_updated']) ? (int) $_GET['pmm_entity_updated'] : 0;
		$preview_saved = isset($_GET['pmm_preview_saved']) ? (int) $_GET['pmm_preview_saved'] : 0;
		$alias_saved = isset($_GET['pmm_alias_saved']) ? (int) $_GET['pmm_alias_saved'] : -1;
		$reprocessed = isset($_GET['pmm_reprocessed']) ? (int) $_GET['pmm_reprocessed'] : 0;
		$global_replaced = isset($_GET['pmm_global_replaced']) ? (int) $_GET['pmm_global_replaced'] : 0;
		$global_renamed = isset($_GET['pmm_global_renamed']) ? (int) $_GET['pmm_global_renamed'] : 0;
		$global_merged = isset($_GET['pmm_global_merged']) ? (int) $_GET['pmm_global_merged'] : 0;
		$global_entries = isset($_GET['pmm_global_entries']) ? (int) $_GET['pmm_global_entries'] : 0;
		$global_scope = isset($_GET['pmm_global_scope']) ? sanitize_key(wp_unslash($_GET['pmm_global_scope'])) : 'both';
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
		$similarity_threshold_defaults = [
			'characters' => 0.62,
			'organizations' => 0.70,
			'locations' => 0.66,
			'technology' => 0.72,
		];
		$similarity_thresholds = get_option('pmm_similarity_thresholds', []);
		if (!is_array($similarity_thresholds)) {
			$similarity_thresholds = [];
		}
		$similarity_thresholds = array_merge($similarity_threshold_defaults, $similarity_thresholds);
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
		$similarity_log = get_option('pmm_similarity_review_log', []);
		if (!is_array($similarity_log)) {
			$similarity_log = [];
		}
		$raw_preview = get_transient('pmm_raw_import_preview_' . get_current_user_id());
		$raw_preview_rows = (isset($raw_preview['rows']) && is_array($raw_preview['rows'])) ? $raw_preview['rows'] : [];
		$raw_preview_text = isset($raw_preview['raw_text']) ? (string) $raw_preview['raw_text'] : '';
		$staged_raw_rows = get_transient('pmm_staged_raw_import_' . get_current_user_id());
		if (!is_array($staged_raw_rows)) {
			$staged_raw_rows = [];
		}
		$staged_raw_rows_text = $this->serialize_staged_raw_import_rows($raw_preview_rows);
		if ($staged_raw_rows_text === '') {
			$staged_raw_rows_text = $this->serialize_staged_raw_import_rows($staged_raw_rows);
		}
		$raw_preview_limit = 80;
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
							'format' => (string) get_option('pmm_last_format', 'md'),
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
		$edit_entries_text = '';
		$edit_rendered_text = '';
		$edit_entity_options = [];
		$preview_sections = [];
		$preview_entities = [];
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
		if ($entity_saved >= 0) {
			$recent_actions[] = sprintf(__('Saved %d entity review decisions.', 'perchance-memory-manager'), $entity_saved);
		}
		if ($hidden_updated >= 0) {
			$recent_actions[] = sprintf(__('Unhid %d entities.', 'perchance-memory-manager'), $hidden_updated);
		}
		if ($raw_staged >= 0) {
			$recent_actions[] = sprintf(__('Staged %d raw import rows.', 'perchance-memory-manager'), $raw_staged);
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
		}
		?>
		<div class="wrap pmm-wrap">
			<h1><?php esc_html_e('Perchance', 'perchance-memory-manager'); ?></h1>

			<div class="notice notice-info">
				<p><?php esc_html_e('Upload a Perchance Chat lore or memory file, even character or world information (.txt or .md), to clean, reorganize and manage.', 'perchance-memory-manager'); ?></p>
			</div>

			<div class="notice notice-info">
				<p><strong><?php esc_html_e('Raw Import Tip:', 'perchance-memory-manager'); ?></strong> <?php esc_html_e('To force loose/raw lines into intake, add a section header "# Raw Import" (or "# New Entries") in your source file. Auto detection will handle mixed input in one pass: bullets, one-entry-per-line dumps, and multi-line paragraph descriptions are all treated as New Entries during processing.', 'perchance-memory-manager'); ?></p>
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
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0 0 8px 0;">
						<?php wp_nonce_field('pmm_reprocess_last_output'); ?>
						<input type="hidden" name="action" value="pmm_reprocess_last_output">
						<?php submit_button(__('Reprocess Last Output Now', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
					</form>
				</div>
			<?php endif; ?>

			<?php if ($questionable_updated > 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Applied %d direct questionable-entry edits (section/entity/entry updates) to the latest output.', 'perchance-memory-manager'), $questionable_updated)); ?></p></div>
			<?php endif; ?>

			<?php if ($entity_report_rebuilt) : ?>
				<div class="notice notice-info"><p><?php esc_html_e('Entity report was rebuilt from the current output because report data was missing. Similar/questionable suggestions may remain sparse until the next full process/reprocess run.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($entity_saved >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Saved %d entity review decisions.', 'perchance-memory-manager'), $entity_saved)); ?></p></div>
			<?php endif; ?>

			<?php if ($entity_truncated && $entity_expected_count > 0) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html(sprintf(__('Only %1$d of %2$d entity-review rows were received when saving. This usually means PHP input limits truncated the form submission (for example, max_input_vars). Use the page controls in Entity Review to process in smaller chunks.', 'perchance-memory-manager'), max(0, $entity_reviewed), $entity_expected_count)); ?></p></div>
			<?php endif; ?>

			<?php if ($hidden_updated >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Unhid %d hidden entities.', 'perchance-memory-manager'), $hidden_updated)); ?></p></div>
			<?php endif; ?>

			<?php if ($raw_previewed >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Previewed %d raw import entries. Edit the staging table before your next upload or reprocess.', 'perchance-memory-manager'), $raw_previewed)); ?></p></div>
			<?php endif; ?>

			<?php if ($raw_staged >= 0) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Staged %d raw import rows for the next upload or reprocess.', 'perchance-memory-manager'), $raw_staged)); ?></p></div>
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

			<?php if ($reprocessed) : ?>
				<div class="notice notice-info"><p><?php esc_html_e('Reprocessing started from last output. No re-upload needed.', 'perchance-memory-manager'); ?></p></div>
			<?php endif; ?>

			<?php if ($global_replaced) : ?>
				<div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Global search and replace completed in %1$s scope. Renamed %2$d entity buckets, merged %3$d duplicates, and updated %4$d entries.', 'perchance-memory-manager'), $global_scope, $global_renamed, $global_merged, $global_entries)); ?></p></div>
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
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pmm-process-saved-version-form" style="margin-bottom:12px;">
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
										<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pmm-process-saved-version-form" style="display:inline;">
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
									<option value="md" selected><?php esc_html_e('Markdown (.md)', 'perchance-memory-manager'); ?></option>
									<option value="txt"><?php esc_html_e('Plain Text (.txt)', 'perchance-memory-manager'); ?></option>
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
						questionableMinWords: document.getElementById('pmm_questionable_min_words'),
						questionableMinChars: document.getElementById('pmm_questionable_min_chars'),
						questionableTerms: document.getElementById('pmm_questionable_terms')
					};

					var forms = document.querySelectorAll('form.pmm-process-saved-version-form');
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
							upsertHidden(form, 'pmm_format', source.format ? source.format.value : 'md');
							upsertHidden(form, 'pmm_drop_sequences', source.dropSequences ? source.dropSequences.value : '');
							upsertHidden(form, 'pmm_include_entity_report', '1');
							upsertHidden(form, 'pmm_entity_related_match_mode', source.entityRelatedMatchMode ? source.entityRelatedMatchMode.value : 'normal');
							upsertHidden(form, 'pmm_similarity_threshold_characters', source.similarityThresholdCharacters ? source.similarityThresholdCharacters.value : '0.62');
							upsertHidden(form, 'pmm_similarity_threshold_organizations', source.similarityThresholdOrganizations ? source.similarityThresholdOrganizations.value : '0.70');
							upsertHidden(form, 'pmm_similarity_threshold_locations', source.similarityThresholdLocations ? source.similarityThresholdLocations.value : '0.66');
							upsertHidden(form, 'pmm_similarity_threshold_technology', source.similarityThresholdTechnology ? source.similarityThresholdTechnology.value : '0.72');
							upsertHidden(form, 'pmm_questionable_min_words', source.questionableMinWords ? source.questionableMinWords.value : '4');
							upsertHidden(form, 'pmm_questionable_min_chars', source.questionableMinChars ? source.questionableMinChars.value : '18');
							upsertHidden(form, 'pmm_questionable_terms', source.questionableTerms ? source.questionableTerms.value : '');
						});
					});
				});
				</script>
			</div>

			<?php if (!empty($data) && !empty($data['cleaned_data']) && is_array($data['cleaned_data'])) : ?>
				<div class="pmm-card">
					<h2><?php esc_html_e('Entity Workspace', 'perchance-memory-manager'); ?></h2>
					<p class="description"><?php esc_html_e('Load any entity to view and edit its entries. Replace updates that entity bucket directly in the latest processed output. Delete removes the entity bucket (or section entries when entity is blank for Notes/Relationships/NSFW).', 'perchance-memory-manager'); ?></p>

					<form id="pmm-entity-load-form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-bottom:10px;">
						<input type="hidden" name="page" value="perchance-memory-manager">
						<input type="hidden" id="pmm-entity-ajax-nonce" value="<?php echo esc_attr($entity_ajax_nonce); ?>">
						<label><?php esc_html_e('Section', 'perchance-memory-manager'); ?>
							<select id="pmm_edit_section" name="pmm_edit_section">
								<?php foreach (['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes'] as $sec) : ?>
									<option value="<?php echo esc_attr($sec); ?>" <?php selected($edit_section, $sec); ?>><?php echo esc_html($sec); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label style="margin-left:8px;"><?php esc_html_e('Entity', 'perchance-memory-manager'); ?>
							<select id="pmm_edit_entity" name="pmm_edit_entity" class="regular-text">
								<?php if (in_array($edit_section, ['Notes', 'Relationships', 'NSFW'], true)) : ?>
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
							var sectionLevelSections = ['Notes', 'Relationships', 'NSFW'];
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
									<?php foreach (['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes'] as $sec) : ?>
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
						<p class="description" style="margin-bottom:6px;"><?php esc_html_e('Enter one kept entry per line without bullets. The saved file adds "- " automatically.', 'perchance-memory-manager'); ?></p>
						<textarea name="pmm_edit_entries" rows="12" class="large-text code"><?php echo esc_textarea($edit_entries_text); ?></textarea>
						<details style="margin-top:10px;">
							<summary><strong><?php esc_html_e('Rendered Output Preview', 'perchance-memory-manager'); ?></strong></summary>
							<pre class="pmm-rendered-preview" style="white-space:pre-wrap;margin-top:8px;padding:10px;border:1px solid #dcdcde;background:#fff;overflow:auto;"><?php echo esc_html($edit_rendered_text); ?></pre>
						</details>
						<?php submit_button(__('Save Entity Update', 'perchance-memory-manager'), 'primary', 'submit', false); ?>
					</form>
				</div>
			<?php endif; ?>

			<?php if (!empty($data) && !empty($data['cleaned_data']) && is_array($data['cleaned_data'])) : ?>
				<div class="pmm-card">
					<h2><?php esc_html_e('Global Search & Replace', 'perchance-memory-manager'); ?></h2>
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
				</div>
			<?php endif; ?>

			<div class="pmm-card">
				<h2><?php esc_html_e('Raw Import Workspace', 'perchance-memory-manager'); ?></h2>
				<p><?php esc_html_e('Paste mixed raw import text or upload a raw text file to preview how auto-detection splits entries. Then edit tab-separated staging rows (or upload edited TSV) before the next upload/reprocess.', 'perchance-memory-manager'); ?></p>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
					<?php wp_nonce_field('pmm_preview_raw_import'); ?>
					<input type="hidden" name="action" value="pmm_preview_raw_import">
					<p><input type="file" name="pmm_raw_import_file" accept=".txt,.md,.log,.tsv"></p>
					<textarea name="pmm_raw_import_text" rows="8" class="large-text code" placeholder="# Raw Import&#10;Character is a former pilot with a fractured memory...&#10;Another line from a one-entry-per-line dump"><?php echo esc_textarea($raw_preview_text); ?></textarea>
					<?php submit_button(__('Preview Raw Import', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
				</form>

				<?php if (!empty($raw_preview_rows) || !empty($staged_raw_rows)) : ?>
					<p class="description" style="margin-top:10px;"><?php esc_html_e('Editable staging format: Section<TAB>Entity<TAB>Entry text. Leave Entity blank to append to section-level entries like Notes/Relationships/NSFW.', 'perchance-memory-manager'); ?></p>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" id="pmm-raw-stage-form" data-pmm-raw-table-complete="<?php echo esc_attr(!empty($raw_preview_rows) && count($raw_preview_rows) <= $raw_preview_limit ? '1' : '0'); ?>">
						<?php wp_nonce_field('pmm_stage_raw_import'); ?>
						<input type="hidden" name="action" value="pmm_stage_raw_import">
						<p><input type="file" name="pmm_raw_import_rows_file" accept=".tsv,.txt,.csv"> <span class="description"><?php esc_html_e('Optional: upload edited TSV to replace textarea content.', 'perchance-memory-manager'); ?></span></p>
						<?php if (!empty($raw_preview_rows) && count($raw_preview_rows) <= $raw_preview_limit) : ?>
							<details style="margin:8px 0 12px 0;">
								<summary><strong><?php esc_html_e('Preview Assignments (editable)', 'perchance-memory-manager'); ?></strong></summary>
								<p class="description" style="margin-top:8px;"><?php echo esc_html(sprintf(__('Showing %1$d of %2$d detected rows. Edit these assignments, then stage the TSV below.', 'perchance-memory-manager'), min($raw_preview_limit, count($raw_preview_rows)), count($raw_preview_rows))); ?></p>
								<?php $this->render_raw_import_preview_table(array_slice($raw_preview_rows, 0, $raw_preview_limit)); ?>
							</details>
						<?php elseif (!empty($raw_preview_rows)) : ?>
							<p class="description" style="margin:8px 0 12px 0;"><?php echo esc_html(sprintf(__('Detected %d preview rows. The row editor is hidden here so the full staged TSV stays intact; use the textarea below or upload an edited TSV for bulk changes.', 'perchance-memory-manager'), count($raw_preview_rows))); ?></p>
						<?php endif; ?>
						<textarea name="pmm_raw_import_rows" rows="14" class="large-text code"><?php echo esc_textarea($staged_raw_rows_text); ?></textarea>
						<?php submit_button(__('Stage Rows For Next Processing Run', 'perchance-memory-manager'), 'secondary', 'submit', false); ?>
					</form>
					<script>
						document.addEventListener('DOMContentLoaded', function () {
							var form = document.getElementById('pmm-raw-stage-form');
							if (!form) {
								return;
							}
							if (form.dataset.pmmRawTableComplete !== '1') {
								return;
							}
							form.addEventListener('submit', function () {
								var rows = form.querySelectorAll('[data-pmm-raw-row]');
								var lines = [];
								rows.forEach(function (row) {
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
								var text = form.querySelector('textarea[name="pmm_raw_import_rows"]');
								if (text && lines.length) {
									text.value = lines.join('\n');
								}
							});
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
			</div>

			<?php if ($has_last_output && !empty($data['entity_report']) && is_array($data['entity_report'])) : ?>
				<div class="pmm-card">
					<details>
						<summary><strong><?php esc_html_e('Entity Management Functions', 'perchance-memory-manager'); ?></strong></summary>
						<div style="margin-top:10px;">
							<details style="margin:12px 0;">
								<summary><strong><?php esc_html_e('Similar Entity Review', 'perchance-memory-manager'); ?></strong></summary>
								<div style="margin-top:10px;">
									<?php $this->render_similarity_review($data['entity_report']['similar_candidates'] ?? [], isset($data['entity_report']['similar_candidates_total_found']) ? (int) $data['entity_report']['similar_candidates_total_found'] : null, !empty($data['entity_report']['similar_candidates_truncated'])); ?>
								</div>
							</details>

							<details style="margin:12px 0;">
								<summary><strong><?php esc_html_e('Questionable Entry Review', 'perchance-memory-manager'); ?></strong></summary>
								<div style="margin-top:10px;">
									<?php $this->render_questionable_entry_review($data['entity_report']['questionable_entries'] ?? [], isset($data['entity_report']['questionable_entries_total_found']) ? (int) $data['entity_report']['questionable_entries_total_found'] : null); ?>
								</div>
							</details>

							<details style="margin:12px 0;">
								<summary><strong><?php esc_html_e('Entity Review', 'perchance-memory-manager'); ?></strong></summary>
								<div style="margin-top:10px;">
									<?php $this->render_entity_review($data['entity_report']['entities'] ?? []); ?>
									<?php $this->render_hidden_entities_manager(); ?>
								</div>
							</details>

							<details style="margin:12px 0;">
								<summary><strong><?php esc_html_e('Alias Rules', 'perchance-memory-manager'); ?></strong></summary>
								<div style="margin-top:10px;">
									<?php $this->render_alias_rules_manager(); ?>
								</div>
							</details>

							<details style="margin:12px 0;">
								<summary><strong><?php esc_html_e('All Entities', 'perchance-memory-manager'); ?></strong></summary>
								<div style="margin-top:10px;">
									<?php $this->render_entity_groups($data['entity_report']['entities'] ?? []); ?>
								</div>
							</details>
						</div>
					</details>
				</div>
			<?php endif; ?>

			<?php if ($has_last_output || !empty($similarity_log)) : ?>
				<div class="pmm-card">
					<details>
						<summary><strong><?php esc_html_e('Review and Results Hub', 'perchance-memory-manager'); ?></strong></summary>
						<div style="margin-top:10px;">
							<?php if ($has_last_output && !empty($data['entity_report']) && is_array($data['entity_report'])) : ?>
								<details style="margin:12px 0;">
									<summary><strong><?php esc_html_e('New Entities Added During Processing', 'perchance-memory-manager'); ?></strong></summary>
									<div style="margin-top:10px;">
										<?php $this->render_entity_groups($data['entity_report']['new_entities'] ?? []); ?>
									</div>
								</details>
							<?php endif; ?>

							<details style="margin:12px 0;">
								<summary><strong><?php esc_html_e('Active Rules Summary', 'perchance-memory-manager'); ?></strong></summary>
								<div style="margin-top:10px;">
									<?php $this->render_rules_summary(); ?>
								</div>
							</details>

							<?php if ($has_last_output) : ?>
								<details style="margin:12px 0;">
									<summary><strong><?php esc_html_e('Last Processed Result', 'perchance-memory-manager'); ?></strong></summary>
									<div style="margin-top:10px;">
										<?php $this->render_last_processed_result_summary($data, $rules_dirty); ?>
									</div>
								</details>
							<?php endif; ?>

							<?php if (!empty($similarity_log)) : ?>
								<details style="margin:12px 0;">
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
							var sectionNames = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes', 'New Entries'];

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
										currentSectionAllowsEntities = ['Relationships', 'NSFW', 'Notes', 'New Entries'].indexOf(currentSection) === -1;
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

							function rebuildMatches() {
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

								selectMatch(0, false);
							}

							function scheduleRebuild() {
								if (rebuildTimer) {
									window.clearTimeout(rebuildTimer);
								}
								rebuildTimer = window.setTimeout(function () {
									rebuildMatches();
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
								scheduleRebuild();
							});

							textarea.addEventListener('input', function () {
								previewIndex = buildPreviewIndex();
								populatePreviewSectionOptions();
								populatePreviewEntityOptions(section ? section.value : '');
								if ((input.value || '').trim() === '') {
									return;
								}
								scheduleRebuild();
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

				</div>
			<?php endif; ?>

		</div>
		<?php
	}

	private function render_last_processed_result_summary($data, $rules_dirty) {
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
		echo '<li><strong>' . esc_html__('Mode:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['mode'] ?? '')) . '</li>';
		echo '<li><strong>' . esc_html__('Format:', 'perchance-memory-manager') . '</strong> ' . esc_html((string) ($data['stats']['format'] ?? '')) . '</li>';
		echo '</ul>';

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:10px;">';
		wp_nonce_field('pmm_reprocess_last_output');
		echo '<input type="hidden" name="action" value="pmm_reprocess_last_output">';
		submit_button(__('Reprocess Last Output (No Re-upload)', 'perchance-memory-manager'), 'secondary', 'submit', false);
		echo '</form>';

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

	private function render_similarity_review($candidates, $total_found = null, $was_truncated = false) {
		if (empty($candidates) || !is_array($candidates)) {
			echo '<p>' . esc_html__('No similar entity pairs detected.', 'perchance-memory-manager') . '</p>';
			return;
		}

		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes'];
		$shown_count = count($candidates);
		$resolved_total = is_numeric($total_found) ? max(0, (int) $total_found) : $shown_count;
		if ($resolved_total > $shown_count) {
			echo '<p>' . esc_html(sprintf(__('Detected %1$d potentially similar pairs, showing first %2$d. Review and save decisions.', 'perchance-memory-manager'), $resolved_total, $shown_count)) . '</p>';
		} else {
			echo '<p>' . esc_html(sprintf(__('Detected %d potentially similar pairs. Review and save decisions.', 'perchance-memory-manager'), $shown_count)) . '</p>';
		}
		if ($was_truncated) {
			echo '<p class="description" style="color:#b45309;">' . esc_html__('Similarity scanning was capped for performance on this large dataset. Results shown here are a high-confidence subset.', 'perchance-memory-manager') . '</p>';
		}
		echo '<p class="description">' . esc_html__('Section and entity names are editable. Keep separate hides the original suggestion pair; merge actions save alias rules for future runs.', 'perchance-memory-manager') . '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="pmm-similarity-review-form">';
		wp_nonce_field('pmm_apply_similarity_review');
		echo '<input type="hidden" name="action" value="pmm_apply_similarity_review">';
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
		echo '<th>' . esc_html__('Action', 'perchance-memory-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($candidates as $candidate) {
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

	private function render_questionable_entry_review($candidates, $total_found = null) {
		if (empty($candidates) || !is_array($candidates)) {
			echo '<p>' . esc_html__('No questionable entries currently detected by the active heuristics.', 'perchance-memory-manager') . '</p>';
			return;
		}

		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes'];
		$shown_count = count($candidates);
		$resolved_total = is_numeric($total_found) ? max(0, (int) $total_found) : $shown_count;
		if ($resolved_total > $shown_count) {
			echo '<p>' . esc_html(sprintf(__('Questionable entries: %1$d found, showing first %2$d. Review and save decisions.', 'perchance-memory-manager'), $resolved_total, $shown_count)) . '</p>';
		} else {
			echo '<p>' . esc_html(sprintf(__('Questionable entries: %d found. Review and save decisions.', 'perchance-memory-manager'), $shown_count)) . '</p>';
		}
		echo '<p class="description">' . esc_html__('Section, entity, and entry are editable. Use Update entry now to immediately move/edit this line in latest output. Remove adds an output rule for next reprocess.', 'perchance-memory-manager') . '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="pmm-questionable-review-form">';
		wp_nonce_field('pmm_apply_questionable_review');
		echo '<input type="hidden" name="action" value="pmm_apply_questionable_review">';
		echo '<input type="hidden" name="pmm_questionable_expected_count" value="' . esc_attr((string) count($candidates)) . '">';
		echo '<p style="margin:12px 0 8px;">';
		echo '<label for="pmm-questionable-bulk-action"><strong>' . esc_html__('Bulk action for all rows', 'perchance-memory-manager') . '</strong></label> ';
		echo '<select id="pmm-questionable-bulk-action" class="regular-text pmm-bulk-action">';
		echo '<option value="">' . esc_html__('Choose an action', 'perchance-memory-manager') . '</option>';
		echo '<option value="keep">' . esc_html__('Keep', 'perchance-memory-manager') . '</option>';
		echo '<option value="hide">' . esc_html__('Keep and hide (do not ask again)', 'perchance-memory-manager') . '</option>';
		echo '<option value="remove">' . esc_html__('Remove on next reprocess', 'perchance-memory-manager') . '</option>';
		echo '</select> ';
		echo '<button type="button" class="button pmm-apply-bulk-action">' . esc_html__('Apply to all rows', 'perchance-memory-manager') . '</button>';
		echo '</p>';
		echo '<table class="widefat striped" style="margin-top:8px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Section', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entity', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entry', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Why flagged', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Action', 'perchance-memory-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($candidates as $candidate) {
			$id = isset($candidate['id']) ? sanitize_key((string) $candidate['id']) : '';
			if ($id === '') {
				continue;
			}

			$section = isset($candidate['section']) ? (string) $candidate['section'] : '';
			$entity = isset($candidate['entity']) ? (string) $candidate['entity'] : '';
			$entry = isset($candidate['entry']) ? (string) $candidate['entry'] : '';
			$reasons = isset($candidate['reasons']) ? (string) $candidate['reasons'] : '';

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
			echo '<td>';
			echo '<input type="hidden" name="pmm_questionable[' . esc_attr($id) . '][original_section]" value="' . esc_attr($section) . '">';
			echo '<input type="hidden" name="pmm_questionable[' . esc_attr($id) . '][original_entity]" value="' . esc_attr($entity) . '">';
			echo '<input type="hidden" name="pmm_questionable[' . esc_attr($id) . '][original_entry]" value="' . esc_attr($entry) . '">';
			echo '<select name="pmm_questionable[' . esc_attr($id) . '][action]">';
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
		echo '<script>(function(){var form=document.getElementById("pmm-questionable-review-form");if(!form){return;}var bulkSelect=form.querySelector(".pmm-bulk-action");var bulkButton=form.querySelector(".pmm-apply-bulk-action");if(!bulkSelect||!bulkButton){return;}bulkButton.addEventListener("click",function(){var value=bulkSelect.value;if(!value){return;}if(!confirm("' . esc_js(__('Apply this questionable-entry action to all rows in the table?', 'perchance-memory-manager')) . '")){return;}form.querySelectorAll("select[name$=\"[action]\"]").forEach(function(select){select.value=value;});form.submit();});})();</script>';
	}

	private function render_entity_review($entity_groups) {
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

		if (empty($rows)) {
			echo '<p>' . esc_html__('No entities need review (all visible items are resolved or hidden).', 'perchance-memory-manager') . '</p>';
			return;
		}

		$total_rows = count($rows);
		$rows_per_page = max(50, min(250, (int) apply_filters('pmm_entity_review_rows_per_page', 120)));
		$page = isset($_GET['pmm_entity_page']) ? max(1, (int) $_GET['pmm_entity_page']) : 1;
		$total_pages = max(1, (int) ceil($total_rows / $rows_per_page));
		if ($page > $total_pages) {
			$page = $total_pages;
		}

		$offset = ($page - 1) * $rows_per_page;
		$rows_page = array_slice($rows, $offset, $rows_per_page);
		$shown_from = $offset + 1;
		$shown_to = $offset + count($rows_page);

		echo '<p class="description">' . esc_html__('Keep leaves the entity untouched. Keep and hide excludes it from future entity review prompts. Remove deletes the entity and entries that mention it when you reprocess.', 'perchance-memory-manager') . '</p>';
		echo '<p class="description" style="margin-top:6px;">' . esc_html(sprintf(__('Showing entities %1$d-%2$d of %3$d. Save one page at a time to avoid PHP input truncation on large datasets.', 'perchance-memory-manager'), $shown_from, $shown_to, $total_rows)) . '</p>';
		if ($total_pages > 1) {
			echo '<p style="margin:8px 0;">';
			if ($page > 1) {
				echo '<a class="button" href="' . esc_url(add_query_arg(['pmm_entity_page' => $page - 1])) . '">' . esc_html__('Previous Page', 'perchance-memory-manager') . '</a> ';
			}
			echo '<span class="description" style="margin:0 8px;">' . esc_html(sprintf(__('Page %1$d of %2$d', 'perchance-memory-manager'), $page, $total_pages)) . '</span>';
			if ($page < $total_pages) {
				echo ' <a class="button" href="' . esc_url(add_query_arg(['pmm_entity_page' => $page + 1])) . '">' . esc_html__('Next Page', 'perchance-memory-manager') . '</a>';
			}
			echo '</p>';
		}
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('pmm_apply_entity_review');
		echo '<input type="hidden" name="action" value="pmm_apply_entity_review">';
		echo '<input type="hidden" name="pmm_entity_expected_count" value="' . esc_attr((string) count($rows_page)) . '">';
		echo '<table class="widefat striped" style="margin-top:8px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Section', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entity', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Action', 'perchance-memory-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($rows_page as $row) {
			$id = md5((string) $row['key']);
			echo '<tr>';
			echo '<td>' . esc_html($row['section']) . '</td>';
			echo '<td>' . esc_html($row['name']) . '</td>';
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

		$rules_text = $this->serialize_alias_rules($rules);

		echo '<p class="description" style="margin-top:0;">' . esc_html__('Use aliases when nickname/short-name variants should resolve to the same character, location, or organization without deleting either wording. Example: Max => Black Max.', 'perchance-memory-manager') . '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('pmm_save_alias_rules');
		echo '<input type="hidden" name="action" value="pmm_save_alias_rules">';
		echo '<textarea name="pmm_alias_rules_text" rows="8" class="large-text code" placeholder="Alias Name => Canonical Name">' . esc_textarea($rules_text) . '</textarea>';
		echo '<p class="description" style="margin-top:8px;">' . esc_html__('One mapping per line. Supported separators: =>, =, or tab. Save and reprocess to apply across output and similarity checks.', 'perchance-memory-manager') . '</p>';
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

		if (in_array($section, ['Notes', 'Relationships', 'NSFW'], true) && $entity === '') {
			$items = isset($cleaned_data[$section]['__entries__']) && is_array($cleaned_data[$section]['__entries__']) ? $cleaned_data[$section]['__entries__'] : [];
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

		if (in_array($section, ['Notes', 'Relationships', 'NSFW'], true) && $entity === '') {
			$items = isset($cleaned_data[$section]['__entries__']) && is_array($cleaned_data[$section]['__entries__']) ? $cleaned_data[$section]['__entries__'] : [];
			foreach ($items as $item) {
				$lines[] = '- ' . (string) $item;
			}

			return implode("\n", $lines);
		}

		if ($entity !== '' && isset($cleaned_data[$section][$entity]) && is_array($cleaned_data[$section][$entity])) {
			$lines[] = $entity;
			foreach ($cleaned_data[$section][$entity] as $item) {
				$lines[] = '- ' . (string) $item;
			}
			return implode("\n", $lines);
		}

		return '';
	}

	private function build_fallback_entity_report($cleaned_data) {
		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems'];
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

	private function render_raw_import_preview_table($rows) {
		$valid_sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Relationships', 'NSFW', 'Notes'];
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__('Source', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Section', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entity', 'perchance-memory-manager') . '</th>';
		echo '<th>' . esc_html__('Entry Text', 'perchance-memory-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ((array) $rows as $index => $row) {
			$source = isset($row['source']) ? (string) $row['source'] : '';
			$section = isset($row['section']) ? (string) $row['section'] : 'Notes';
			$entity = isset($row['entity']) ? (string) $row['entity'] : '';
			$bullet = isset($row['bullet']) ? (string) $row['bullet'] : '';
			if (!in_array($section, $valid_sections, true)) {
				$section = 'Notes';
			}

			echo '<tr data-pmm-raw-row="1">';
			echo '<td style="max-width:260px;white-space:pre-wrap;">' . esc_html($source) . '</td>';
			echo '<td><select name="pmm_raw_table[' . esc_attr((string) $index) . '][section]">';
			foreach ($valid_sections as $sec) {
				echo '<option value="' . esc_attr($sec) . '" ' . selected($section, $sec, false) . '>' . esc_html($sec) . '</option>';
			}
			echo '</select></td>';
			echo '<td><input type="text" class="regular-text" name="pmm_raw_table[' . esc_attr((string) $index) . '][entity]" value="' . esc_attr($entity) . '" placeholder="' . esc_attr__('Optional', 'perchance-memory-manager') . '"></td>';
			echo '<td><textarea name="pmm_raw_table[' . esc_attr((string) $index) . '][bullet]" rows="2" class="large-text code" style="min-width:100%;">' . esc_textarea($bullet) . '</textarea></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
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
}