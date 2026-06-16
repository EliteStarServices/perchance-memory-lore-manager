<?php

if (!defined('ABSPATH')) {
	exit;
}

class PMM_DB {
	const SCHEMA_VERSION = '2';
	const SCHEMA_OPTION = 'pmm_db_schema_version';

	public static function ensure_schema() {
		$current = (string) get_option(self::SCHEMA_OPTION, '0');
		if ($current === self::SCHEMA_VERSION) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$entries = self::entries_table();
		$entities = self::entities_table();
		$entry_entities = self::entry_entities_table();
		$dedupe_reviews = self::dedupe_reviews_table();

		$sql_entries = "CREATE TABLE {$entries} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			seq BIGINT UNSIGNED NOT NULL,
			section_name VARCHAR(100) NOT NULL DEFAULT '',
			source_entity VARCHAR(191) NOT NULL DEFAULT '',
			entry_text LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'unknown',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY seq (seq),
			KEY status (status)
		) {$charset_collate};";

		$sql_entities = "CREATE TABLE {$entities} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			fingerprint VARCHAR(191) NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY fingerprint (fingerprint),
			UNIQUE KEY name (name)
		) {$charset_collate};";

		$sql_entry_entities = "CREATE TABLE {$entry_entities} (
			entry_id BIGINT UNSIGNED NOT NULL,
			entity_id BIGINT UNSIGNED NOT NULL,
			match_method VARCHAR(20) NOT NULL DEFAULT 'auto',
			match_score FLOAT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (entry_id, entity_id),
			KEY entity_id (entity_id),
			KEY match_method (match_method)
		) {$charset_collate};";

		$sql_dedupe_reviews = "CREATE TABLE {$dedupe_reviews} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			keep_entry_id BIGINT UNSIGNED NOT NULL,
			duplicate_entry_id BIGINT UNSIGNED NOT NULL,
			candidate_type VARCHAR(20) NOT NULL DEFAULT 'near',
			similarity FLOAT NULL,
			action VARCHAR(20) NOT NULL DEFAULT 'keep',
			reviewed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY duplicate_entry_id (duplicate_entry_id),
			KEY action (action),
			KEY reviewed_by (reviewed_by)
		) {$charset_collate};";

		dbDelta($sql_entries);
		dbDelta($sql_entities);
		dbDelta($sql_entry_entities);
		dbDelta($sql_dedupe_reviews);

		update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
	}

	public static function entries_table() {
		global $wpdb;
		return $wpdb->prefix . 'pmm_entries';
	}

	public static function entities_table() {
		global $wpdb;
		return $wpdb->prefix . 'pmm_entities';
	}

	public static function entry_entities_table() {
		global $wpdb;
		return $wpdb->prefix . 'pmm_entry_entities';
	}

	public static function dedupe_reviews_table() {
		global $wpdb;
		return $wpdb->prefix . 'pmm_dedupe_reviews';
	}

	public static function stats() {
		self::ensure_schema();
		global $wpdb;
		$entries = self::entries_table();
		$entities = self::entities_table();
		$entry_entities = self::entry_entities_table();

		$total_entries = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$entries}");
		$unknown_entries = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$entries} WHERE status = %s",
			'unknown'
		));
		$known_entries = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$entries} WHERE status = %s",
			'known'
		));
		$total_entities = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$entities}");
		$total_links = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$entry_entities}");

		return [
			'total_entries' => $total_entries,
			'unknown_entries' => $unknown_entries,
			'known_entries' => $known_entries,
			'total_entities' => $total_entities,
			'total_links' => $total_links,
		];
	}

	public static function get_unknown_entries($limit = 100) {
		self::ensure_schema();
		global $wpdb;
		$entries = self::entries_table();
		$limit = max(1, min(500, (int) $limit));

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT id, seq, section_name, source_entity, entry_text
			 FROM {$entries}
			 WHERE status = %s
			 ORDER BY seq ASC, id ASC
			 LIMIT %d",
			'unknown',
			$limit
		), ARRAY_A);

		return is_array($rows) ? $rows : [];
	}

	public static function get_non_pruned_entries_chronological() {
		self::ensure_schema();
		global $wpdb;
		$entries = self::entries_table();

		$rows = $wpdb->get_col(
			"SELECT entry_text
			 FROM {$entries}
			 WHERE status NOT IN ('pruned', 'removed')
			 ORDER BY seq ASC, id ASC"
		);

		if (!is_array($rows)) {
			return [];
		}

		$out = [];
		foreach ($rows as $row_text) {
			$text = trim((string) $row_text);
			if ($text === '') {
				continue;
			}
			$out[] = $text;
		}

		return $out;
	}

	public static function get_entries_with_entity_names($limit = 100, $offset = 0, $status = 'known', $search = '', $entity_filter = '') {
		self::ensure_schema();
		global $wpdb;
		$entries_table        = self::entries_table();
		$entities_table       = self::entities_table();
		$entry_entities_table = self::entry_entities_table();

		$limit  = max(1, min(500, (int) $limit));
		$offset = max(0, (int) $offset);
		$valid_statuses = ['all', 'known', 'unknown', 'pruned'];
		if (!in_array($status, $valid_statuses, true)) { $status = 'known'; }

		$where  = [];
		$params = [];

		if ($status !== 'all') {
			$where[] = 'e.status = %s';
			$params[] = $status;
		}

		$search = trim((string) $search);
		if ($search !== '') {
			$where[] = 'e.entry_text LIKE %s';
			$params[] = '%' . $wpdb->esc_like($search) . '%';
		}

		$entity_filter = trim((string) $entity_filter);
		if ($entity_filter !== '') {
			$where[] = 'en.name = %s';
			$params[] = $entity_filter;
		}

		$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
		$params[]  = $limit;
		$params[]  = $offset;

		$sql = "SELECT e.id, e.section_name, e.entry_text, e.status,
			       GROUP_CONCAT(CASE WHEN en.name != 'Unknown' THEN en.name END ORDER BY en.name SEPARATOR ', ') AS entity_names
			FROM {$entries_table} e
			LEFT JOIN {$entry_entities_table} ee ON ee.entry_id = e.id
			LEFT JOIN {$entities_table} en ON en.id = ee.entity_id
			{$where_sql}
			GROUP BY e.id
			ORDER BY e.id ASC
			LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
		return is_array($rows) ? $rows : [];
	}

	public static function count_entries_filtered($status = 'known', $search = '', $entity_filter = '') {
		self::ensure_schema();
		global $wpdb;
		$entries_table        = self::entries_table();
		$entities_table       = self::entities_table();
		$entry_entities_table = self::entry_entities_table();

		$valid_statuses = ['all', 'known', 'unknown', 'pruned'];
		if (!in_array($status, $valid_statuses, true)) { $status = 'known'; }

		$where  = [];
		$params = [];
		$join   = '';

		if ($status !== 'all') {
			$where[] = 'e.status = %s';
			$params[] = $status;
		}

		$search = trim((string) $search);
		if ($search !== '') {
			$where[] = 'e.entry_text LIKE %s';
			$params[] = '%' . $wpdb->esc_like($search) . '%';
		}

		$entity_filter = trim((string) $entity_filter);
		if ($entity_filter !== '') {
			$join    = "INNER JOIN {$entry_entities_table} ee ON ee.entry_id = e.id
			            INNER JOIN {$entities_table} en ON en.id = ee.entity_id";
			$where[] = 'en.name = %s';
			$params[] = $entity_filter;
		}

		$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
		$sql       = "SELECT COUNT(DISTINCT e.id) FROM {$entries_table} e {$join} {$where_sql}";

		return (int) ($params ? $wpdb->get_var($wpdb->prepare($sql, $params)) : $wpdb->get_var($sql));
	}

	public static function get_all_entity_names() {
		self::ensure_schema();
		global $wpdb;
		$entities_table = self::entities_table();
		$rows = $wpdb->get_col("SELECT name FROM {$entities_table} WHERE name != 'Unknown' ORDER BY name ASC");
		return is_array($rows) ? $rows : [];
	}

	public static function clear_entries_data($preserve_entities = true) {
		self::ensure_schema();
		global $wpdb;
		$entries = self::entries_table();
		$entities = self::entities_table();
		$entry_entities = self::entry_entities_table();

		$wpdb->query("TRUNCATE TABLE {$entry_entities}");
		$wpdb->query("TRUNCATE TABLE {$entries}");

		if (!$preserve_entities) {
			$wpdb->query("TRUNCATE TABLE {$entities}");
		}
	}

	public static function replace_entries_from_cleaned($cleaned, $global_entity_names = []) {
		self::ensure_schema();
		global $wpdb;

		$entries_table        = self::entries_table();
		$entry_entities_table = self::entry_entities_table();

		$global_entity_names = self::normalize_entity_names($global_entity_names);
		$entity_ids = self::ensure_entities($global_entity_names);
		$unknown_id = self::ensure_entity('Unknown');

		$entries = self::extract_entries_in_order($cleaned);
		$now = current_time('mysql');

		$wpdb->query("TRUNCATE TABLE {$entry_entities_table}");
		$wpdb->query("TRUNCATE TABLE {$entries_table}");

		self::insert_entry_batch($entries, 0, count($entries), $entity_ids, $unknown_id, $global_entity_names, $now);

		return self::stats();
	}

	public static function rebuild_start($cleaned, $global_entity_names = [], $job_key = '', $batch_size = 500) {
		self::ensure_schema();
		global $wpdb;

		$entries_table        = self::entries_table();
		$entry_entities_table = self::entry_entities_table();

		$global_entity_names = self::normalize_entity_names($global_entity_names);
		$entity_ids          = self::ensure_entities($global_entity_names);
		$unknown_id          = self::ensure_entity('Unknown');
		$entries             = self::extract_entries_in_order($cleaned);
		$total               = count($entries);

		$wpdb->query("TRUNCATE TABLE {$entry_entities_table}");
		$wpdb->query("TRUNCATE TABLE {$entries_table}");

		if ($job_key === '') {
			$job_key = 'pmm_db_rebuild_' . get_current_user_id();
		}

		set_transient($job_key, [
			'entries'             => $entries,
			'global_entity_names' => $global_entity_names,
			'total'               => $total,
			'offset'              => 0,
		], HOUR_IN_SECONDS);

		$now      = current_time('mysql');
		$inserted = self::insert_entry_batch($entries, 0, $batch_size, $entity_ids, $unknown_id, $global_entity_names, $now);
		$next_offset = $batch_size;

		$state           = get_transient($job_key);
		$state['offset'] = $next_offset;
		set_transient($job_key, $state, HOUR_IN_SECONDS);

		$done = $next_offset >= $total;
		if ($done) {
			delete_transient($job_key);
		}

		return [
			'done'     => $done,
			'total'    => $total,
			'inserted' => $inserted,
			'offset'   => $next_offset,
			'job_key'  => $job_key,
		];
	}

	public static function rebuild_continue($job_key, $batch_size = 500) {
		self::ensure_schema();

		$state = get_transient($job_key);
		if (!is_array($state) || empty($state['entries'])) {
			return ['done' => true, 'total' => 0, 'inserted' => 0, 'offset' => 0, 'job_key' => $job_key];
		}

		$entries             = (array) $state['entries'];
		$global_entity_names = isset($state['global_entity_names']) ? (array) $state['global_entity_names'] : [];
		$total               = (int) ($state['total'] ?? count($entries));
		$offset              = (int) ($state['offset'] ?? 0);

		$entity_ids = self::ensure_entities($global_entity_names);
		$unknown_id = self::ensure_entity('Unknown');
		$now        = current_time('mysql');

		$inserted    = self::insert_entry_batch($entries, $offset, $batch_size, $entity_ids, $unknown_id, $global_entity_names, $now);
		$next_offset = $offset + $batch_size;

		$state['offset'] = $next_offset;
		set_transient($job_key, $state, HOUR_IN_SECONDS);

		$done = $next_offset >= $total;
		if ($done) {
			delete_transient($job_key);
		}

		return [
			'done'     => $done,
			'total'    => $total,
			'inserted' => $inserted,
			'offset'   => $next_offset,
			'job_key'  => $job_key,
		];
	}

	private static function insert_entry_batch($entries, $offset, $batch_size, $entity_ids, $unknown_id, $global_entity_names, $now) {
		global $wpdb;
		$entries_table        = self::entries_table();
		$entry_entities_table = self::entry_entities_table();

		$slice    = array_slice($entries, $offset, $batch_size);
		$inserted = 0;

		foreach ($slice as $i => $row) {
			$seq           = $offset + $i + 1;
			$text          = trim((string) ($row['text'] ?? ''));
			if ($text === '') {
				continue;
			}
			$section       = (string) ($row['section'] ?? '');
			$source_entity = (string) ($row['entity'] ?? '');

			$matches = self::match_entities_for_entry($text, $global_entity_names);
			$status  = !empty($matches) ? 'known' : 'unknown';

			$wpdb->insert($entries_table, [
				'seq'           => $seq,
				'section_name'  => $section,
				'source_entity' => $source_entity,
				'entry_text'    => $text,
				'status'        => $status,
				'created_at'    => $now,
				'updated_at'    => $now,
			], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);

			$entry_id = (int) $wpdb->insert_id;
			if ($entry_id < 1) {
				continue;
			}
			$inserted++;

			if (!empty($matches)) {
				foreach ($matches as $name => $score) {
					if (!isset($entity_ids[$name])) {
						$entity_ids[$name] = self::ensure_entity($name);
					}
					$wpdb->insert($entry_entities_table, [
						'entry_id'     => $entry_id,
						'entity_id'    => (int) $entity_ids[$name],
						'match_method' => 'auto',
						'match_score'  => (float) $score,
						'created_at'   => $now,
						'updated_at'   => $now,
					], ['%d', '%d', '%s', '%f', '%s', '%s']);
				}
			} else {
				$wpdb->insert($entry_entities_table, [
					'entry_id'     => $entry_id,
					'entity_id'    => $unknown_id,
					'match_method' => 'auto',
					'match_score'  => null,
					'created_at'   => $now,
					'updated_at'   => $now,
				], ['%d', '%d', '%s', null, '%s', '%s']);
			}
		}

		return $inserted;
	}

	public static function clear_db_tables($preserve_entities = false) {
		self::ensure_schema();
		global $wpdb;

		$entries_table        = self::entries_table();
		$entities_table       = self::entities_table();
		$entry_entities_table = self::entry_entities_table();
		$reviews_table        = self::dedupe_reviews_table();

		$wpdb->query("TRUNCATE TABLE {$reviews_table}");
		$wpdb->query("TRUNCATE TABLE {$entry_entities_table}");
		$wpdb->query("TRUNCATE TABLE {$entries_table}");

		if (!$preserve_entities) {
			$wpdb->query("TRUNCATE TABLE {$entities_table}");
		}
	}

	public static function rescan_unknown_entries($global_entity_names = []) {
		self::ensure_schema();
		global $wpdb;
		$entries_table = self::entries_table();
		$entities_table = self::entities_table();
		$entry_entities_table = self::entry_entities_table();

		$global_entity_names = self::normalize_entity_names($global_entity_names);
		$entity_ids = self::ensure_entities($global_entity_names);
		$unknown_id = self::ensure_entity('Unknown');
		$now = current_time('mysql');

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT e.id, e.entry_text
			 FROM {$entries_table} e
			 WHERE e.status = %s
			 ORDER BY e.seq ASC",
			'unknown'
		), ARRAY_A);

		$updated = 0;
		foreach ((array) $rows as $row) {
			$entry_id = (int) ($row['id'] ?? 0);
			$text = trim((string) ($row['entry_text'] ?? ''));
			if ($entry_id < 1 || $text === '') {
				continue;
			}

			$matches = self::match_entities_for_entry($text, $global_entity_names);
			if (empty($matches)) {
				continue;
			}

			$wpdb->delete($entry_entities_table, [
				'entry_id' => $entry_id,
				'entity_id' => $unknown_id,
			], ['%d', '%d']);

			foreach ($matches as $name => $score) {
				if (!isset($entity_ids[$name])) {
					$entity_ids[$name] = self::ensure_entity($name);
				}

				$wpdb->replace($entry_entities_table, [
					'entry_id' => $entry_id,
					'entity_id' => (int) $entity_ids[$name],
					'match_method' => 'auto',
					'match_score' => (float) $score,
					'created_at' => $now,
					'updated_at' => $now,
				], ['%d', '%d', '%s', '%f', '%s', '%s']);
			}

			$wpdb->update($entries_table, [
				'status' => 'known',
				'updated_at' => $now,
			], ['id' => $entry_id], ['%s', '%s'], ['%d']);
			$updated++;
		}

		return $updated;
	}

	public static function retag_unknown_entry($entry_id, $entity_names = []) {
		self::ensure_schema();
		global $wpdb;
		$entries_table = self::entries_table();
		$entry_entities_table = self::entry_entities_table();

		$entry_id = (int) $entry_id;
		if ($entry_id < 1) {
			return false;
		}

		$entity_names = self::normalize_entity_names($entity_names);
		if (empty($entity_names)) {
			return false;
		}

		$unknown_id = self::ensure_entity('Unknown');
		$entity_ids = self::ensure_entities($entity_names);
		$now = current_time('mysql');

		$wpdb->delete($entry_entities_table, [
			'entry_id' => $entry_id,
			'entity_id' => $unknown_id,
		], ['%d', '%d']);

		foreach ($entity_ids as $entity_id) {
			$wpdb->replace($entry_entities_table, [
				'entry_id' => $entry_id,
				'entity_id' => (int) $entity_id,
				'match_method' => 'manual',
				'match_score' => 1.0,
				'created_at' => $now,
				'updated_at' => $now,
			], ['%d', '%d', '%s', '%f', '%s', '%s']);
		}

		$wpdb->update($entries_table, [
			'status' => 'known',
			'updated_at' => $now,
		], ['id' => $entry_id], ['%s', '%s'], ['%d']);

		return true;
	}

	public static function update_unknown_entry_text($entry_id, $entry_text) {
		self::ensure_schema();
		global $wpdb;
		$entries_table = self::entries_table();

		$entry_id = (int) $entry_id;
		$entry_text = trim((string) $entry_text);
		if ($entry_id < 1 || $entry_text === '') {
			return false;
		}

		$now = current_time('mysql');
		$updated = $wpdb->update(
			$entries_table,
			[
				'entry_text' => $entry_text,
				'updated_at' => $now,
			],
			[
				'id' => $entry_id,
				'status' => 'unknown',
			],
			['%s', '%s'],
			['%d', '%s']
		);

		return $updated !== false && (int) $updated > 0;
	}

	public static function mark_unknown_entry_pruned($entry_id) {
		self::ensure_schema();
		global $wpdb;
		$entries_table = self::entries_table();
		$entry_entities_table = self::entry_entities_table();

		$entry_id = (int) $entry_id;
		if ($entry_id < 1) {
			return false;
		}

		$now = current_time('mysql');
		$updated = $wpdb->update(
			$entries_table,
			[
				'status' => 'pruned',
				'updated_at' => $now,
			],
			[
				'id' => $entry_id,
				'status' => 'unknown',
			],
			['%s', '%s'],
			['%d', '%s']
		);

		if ($updated === false || (int) $updated < 1) {
			return false;
		}

		$unknown_id = self::ensure_entity('Unknown');
		if ($unknown_id > 0) {
			$wpdb->delete($entry_entities_table, [
				'entry_id' => $entry_id,
				'entity_id' => $unknown_id,
			], ['%d', '%d']);
		}

		return true;
	}

	public static function mark_entries_pruned($entry_ids = []) {
		self::ensure_schema();
		global $wpdb;
		$entries_table = self::entries_table();
		$entry_entities_table = self::entry_entities_table();

		$ids = [];
		foreach ((array) $entry_ids as $entry_id) {
			$entry_id = (int) $entry_id;
			if ($entry_id > 0) {
				$ids[] = $entry_id;
			}
		}
		$ids = array_values(array_unique($ids));
		if (empty($ids)) {
			return 0;
		}

		$now = current_time('mysql');
		$placeholders = implode(',', array_fill(0, count($ids), '%d'));
		$params = array_merge([$now], $ids);

		$updated = $wpdb->query($wpdb->prepare(
			"UPDATE {$entries_table}
			 SET status = 'pruned', updated_at = %s
			 WHERE id IN ({$placeholders})
			   AND status <> 'pruned'",
			$params
		));

		$unknown_id = self::ensure_entity('Unknown');
		if ($unknown_id > 0) {
			$delete_params = array_merge($ids, [$unknown_id]);
			$wpdb->query($wpdb->prepare(
				"DELETE FROM {$entry_entities_table}
				 WHERE entry_id IN ({$placeholders})
				   AND entity_id = %d",
				$delete_params
			));
		}

		return $updated === false ? 0 : max(0, (int) $updated);
	}

	public static function update_entries_text_bulk($rows = []) {
		self::ensure_schema();
		global $wpdb;
		$entries_table = self::entries_table();

		$now = current_time('mysql');
		$updated_count = 0;

		foreach ((array) $rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$entry_id = isset($row['id']) ? (int) $row['id'] : 0;
			$entry_text = isset($row['text']) ? trim((string) $row['text']) : '';
			if ($entry_id < 1 || $entry_text === '') {
				continue;
			}

			$updated = $wpdb->query($wpdb->prepare(
				"UPDATE {$entries_table}
				 SET entry_text = %s, updated_at = %s
				 WHERE id = %d
				   AND status <> %s",
				$entry_text,
				$now,
				$entry_id,
				'pruned'
			));

			if ($updated !== false && (int) $updated > 0) {
				$updated_count++;
			}
		}

		return $updated_count;
	}

	public static function get_dedupe_candidates($limit = 300, $near_threshold = 0.92, $scope = 'all') {
		self::ensure_schema();
		global $wpdb;
		$entries_table = self::entries_table();

		$limit = max(10, min(1000, (int) $limit));
		$near_threshold = max(0.70, min(0.99, (float) $near_threshold));
		$scope = in_array((string) $scope, ['all', 'unknown', 'known'], true) ? (string) $scope : 'all';

		$where_sql = "status IN ('unknown','known')";
		if ($scope === 'unknown') {
			$where_sql = "status = 'unknown'";
		} elseif ($scope === 'known') {
			$where_sql = "status = 'known'";
		}

		$rows = $wpdb->get_results(
			"SELECT id, seq, entry_text, status
			 FROM {$entries_table}
			 WHERE {$where_sql}
			 ORDER BY seq ASC
			 LIMIT 2500",
			ARRAY_A
		);

		if (empty($rows) || !is_array($rows)) {
			return [];
		}

		$candidates = [];
		$seen = [];
		$by_fp = [];
		$normalized = [];

		foreach ($rows as $row) {
			$id = (int) ($row['id'] ?? 0);
			$text = trim((string) ($row['entry_text'] ?? ''));
			if ($id < 1 || $text === '') {
				continue;
			}
			$fp = PMM_Utils::fingerprint($text);
			$normalized[$id] = $fp;
			if (!isset($by_fp[$fp])) {
				$by_fp[$fp] = [];
			}
			$by_fp[$fp][] = $row;
		}

		foreach ($by_fp as $fp => $group) {
			if ($fp === '' || count($group) < 2) {
				continue;
			}
			$keep = $group[0];
			$keep_id = (int) $keep['id'];
			foreach (array_slice($group, 1) as $dup) {
				$dup_id = (int) $dup['id'];
				$key = 'exact:' . $keep_id . ':' . $dup_id;
				$seen[$key] = true;
				$candidates[] = [
					'type' => 'exact',
					'keep_entry_id' => $keep_id,
					'duplicate_entry_id' => $dup_id,
					'keep_seq' => (int) ($keep['seq'] ?? 0),
					'duplicate_seq' => (int) ($dup['seq'] ?? 0),
					'keep_text' => (string) ($keep['entry_text'] ?? ''),
					'duplicate_text' => (string) ($dup['entry_text'] ?? ''),
					'similarity' => 1.0,
					'default_action' => 'prune',
				];
				if (count($candidates) >= $limit) {
					return array_slice($candidates, 0, $limit);
				}
			}
		}

		$near_cap = min(300, max(50, $limit));
		$near_count = 0;
		$row_count = count($rows);
		for ($i = 0; $i < $row_count; $i++) {
			$left = $rows[$i];
			$left_id = (int) ($left['id'] ?? 0);
			$left_text = trim((string) ($left['entry_text'] ?? ''));
			if ($left_id < 1 || $left_text === '') {
				continue;
			}

			$max_j = min($row_count, $i + 80);
			for ($j = $i + 1; $j < $max_j; $j++) {
				$right = $rows[$j];
				$right_id = (int) ($right['id'] ?? 0);
				$right_text = trim((string) ($right['entry_text'] ?? ''));
				if ($right_id < 1 || $right_text === '') {
					continue;
				}

				if (($normalized[$left_id] ?? '') !== '' && ($normalized[$left_id] ?? '') === ($normalized[$right_id] ?? '')) {
					continue;
				}

				$keep_id = (int) ($left['seq'] <= $right['seq'] ? $left_id : $right_id);
				$dup_id = (int) ($left['seq'] <= $right['seq'] ? $right_id : $left_id);
				$key = 'exact:' . $keep_id . ':' . $dup_id;
				if (isset($seen[$key])) {
					continue;
				}

				$sim = (float) PMM_Utils::jaccard_similarity($left_text, $right_text);
				if ($sim < $near_threshold) {
					continue;
				}

				$seen['near:' . $keep_id . ':' . $dup_id] = true;
				$candidates[] = [
					'type' => 'near',
					'keep_entry_id' => $keep_id,
					'duplicate_entry_id' => $dup_id,
					'keep_seq' => (int) ($left['seq'] <= $right['seq'] ? $left['seq'] : $right['seq']),
					'duplicate_seq' => (int) ($left['seq'] <= $right['seq'] ? $right['seq'] : $left['seq']),
					'keep_text' => (string) ($left['seq'] <= $right['seq'] ? $left_text : $right_text),
					'duplicate_text' => (string) ($left['seq'] <= $right['seq'] ? $right_text : $left_text),
					'similarity' => $sim,
					'default_action' => 'keep',
				];

				$near_count++;
				if (count($candidates) >= $limit || $near_count >= $near_cap) {
					return array_slice($candidates, 0, $limit);
				}
			}
		}

		return array_slice($candidates, 0, $limit);
	}

	public static function apply_dedupe_actions($actions) {
		self::ensure_schema();
		global $wpdb;
		$entries_table = self::entries_table();
		$reviews_table = self::dedupe_reviews_table();
		$now = current_time('mysql');
		$reviewed_by = get_current_user_id();

		$applied = 0;
		foreach ((array) $actions as $row) {
			if (!is_array($row)) {
				continue;
			}
			$action = isset($row['action']) ? sanitize_key((string) $row['action']) : 'keep';
			$keep_id = isset($row['keep_entry_id']) ? (int) $row['keep_entry_id'] : 0;
			$dup_id = isset($row['duplicate_entry_id']) ? (int) $row['duplicate_entry_id'] : 0;
			$candidate_type = isset($row['type']) ? sanitize_key((string) $row['type']) : 'near';
			if (!in_array($candidate_type, ['exact', 'near'], true)) {
				$candidate_type = 'near';
			}
			$similarity = isset($row['similarity']) ? (float) $row['similarity'] : 0.0;

			if ($keep_id < 1 || $dup_id < 1 || !in_array($action, ['keep', 'prune'], true)) {
				continue;
			}

			$wpdb->insert(
				$reviews_table,
				[
					'keep_entry_id' => $keep_id,
					'duplicate_entry_id' => $dup_id,
					'candidate_type' => $candidate_type,
					'similarity' => $similarity,
					'action' => $action,
					'reviewed_by' => (int) $reviewed_by,
					'created_at' => $now,
				],
				['%d', '%d', '%s', '%f', '%s', '%d', '%s']
			);

			if ($action !== 'prune') {
				continue;
			}

			$updated = $wpdb->update(
				$entries_table,
				[
					'status' => 'pruned',
					'updated_at' => $now,
				],
				['id' => $dup_id],
				['%s', '%s'],
				['%d']
			);

			if ($updated !== false && (int) $updated > 0) {
				$applied++;
			}
		}

		return $applied;
	}

	private static function ensure_entities($entity_names) {
		$ids = [];
		foreach ((array) $entity_names as $name) {
			$name = trim((string) $name);
			if ($name === '') {
				continue;
			}
			$ids[$name] = self::ensure_entity($name);
		}
		return $ids;
	}

	private static function ensure_entity($name) {
		self::ensure_schema();
		global $wpdb;
		$table = self::entities_table();
		$name = trim((string) $name);
		$fingerprint = PMM_Utils::name_fingerprint($name);
		if ($name === '' || $fingerprint === '') {
			return 0;
		}

		$existing_id = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$table} WHERE fingerprint = %s LIMIT 1",
			$fingerprint
		));
		if ($existing_id > 0) {
			return $existing_id;
		}

		$now = current_time('mysql');
		$wpdb->insert($table, [
			'name' => $name,
			'fingerprint' => $fingerprint,
			'created_at' => $now,
			'updated_at' => $now,
		], ['%s', '%s', '%s', '%s']);

		return (int) $wpdb->insert_id;
	}

	private static function normalize_entity_names($value) {
		$lines = is_array($value) ? $value : preg_split('/\r\n|\r|\n|,/u', (string) $value);
		$names = [];
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
			if (!isset($names[$fp])) {
				$names[$fp] = $name;
			}
		}

		return array_values($names);
	}

	private static function match_entities_for_entry($entry_text, $entity_names) {
		$matches = [];
		$entry_text = (string) $entry_text;
		foreach ((array) $entity_names as $name) {
			$name = trim((string) $name);
			if ($name === '') {
				continue;
			}
			$score = 0.0;
			if (stripos($entry_text, $name) !== false) {
				$score = 1.0;
			} else {
				$score = (float) PMM_Utils::contains_name_score($entry_text, $name);
			}
			if ($score >= 0.60) {
				$matches[$name] = $score;
			}
		}

		return $matches;
	}

	private static function extract_entries_in_order($cleaned) {
		if (!is_array($cleaned)) {
			return [];
		}

		$entries = [];
		if (!empty($cleaned['__chronological__']) && is_array($cleaned['__chronological__'])) {
			foreach ($cleaned['__chronological__'] as $record) {
				if (!is_array($record)) {
					continue;
				}
				$text = isset($record['text']) ? trim((string) $record['text']) : '';
				if ($text === '') {
					continue;
				}
				$entries[] = [
					'text' => $text,
					'section' => isset($record['section']) ? (string) $record['section'] : '',
					'entity' => isset($record['entity']) ? (string) $record['entity'] : '',
				];
			}
			if (!empty($entries)) {
				return $entries;
			}
		}

		$section_order = [
			'Characters',
			'Organizations',
			'Locations',
			'Technology / Systems',
			'Vehicles / Transportation',
			'World Building',
			'Relationships',
			'NSFW',
			'Notes',
			'New Entries',
		];

		$section_level = ['Relationships', 'NSFW', 'Notes', 'World Building', 'Technology / Systems', 'Vehicles / Transportation', 'New Entries'];

		foreach ($section_order as $section) {
			if (empty($cleaned[$section]) || !is_array($cleaned[$section])) {
				continue;
			}

			$bucket = $cleaned[$section];
			if (in_array($section, $section_level, true)) {
				foreach (['__entries__', '__unassigned__'] as $k) {
					if (empty($bucket[$k]) || !is_array($bucket[$k])) {
						continue;
					}
					foreach ($bucket[$k] as $item) {
						$text = trim((string) $item);
						if ($text !== '') {
							$entries[] = ['text' => $text, 'section' => $section, 'entity' => ''];
						}
					}
				}
				foreach ($bucket as $entity => $items) {
					if (!is_array($items) || strpos((string) $entity, '__') === 0) {
						continue;
					}
					foreach ($items as $item) {
						$text = trim((string) $item);
						if ($text !== '') {
							$entries[] = ['text' => $text, 'section' => $section, 'entity' => (string) $entity];
						}
					}
				}
				continue;
			}

			foreach ($bucket as $entity => $items) {
				if (!is_array($items) || strpos((string) $entity, '__') === 0) {
					continue;
				}
				foreach ($items as $item) {
					$text = trim((string) $item);
					if ($text !== '') {
						$entries[] = ['text' => $text, 'section' => $section, 'entity' => (string) $entity];
					}
				}
			}
		}

		return $entries;
	}
}
