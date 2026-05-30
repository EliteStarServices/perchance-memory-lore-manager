<?php

if (!defined('ABSPATH')) {
	exit;
}

class PMM_Parser {
	private $last_report = [
		'entities' => [],
		'new_entities' => [],
	];

	private $section_names = [
		'Characters',
		'Organizations',
		'Locations',
		'Technology / Systems',
		'Relationships',
		'NSFW',
		'Notes',
		'New Entries',
	];

	private $section_aliases = [
		'characters' => 'Characters',
		'character' => 'Characters',
		'organizations' => 'Organizations',
		'organization' => 'Organizations',
		'orgs' => 'Organizations',
		'locations' => 'Locations',
		'location' => 'Locations',
		'technology / systems' => 'Technology / Systems',
		'technology/systems' => 'Technology / Systems',
		'technology systems' => 'Technology / Systems',
		'technology' => 'Technology / Systems',
		'systems' => 'Technology / Systems',
		'relationships' => 'Relationships',
		'relationship' => 'Relationships',
		'nsfw' => 'NSFW',
		'notes' => 'Notes',
		'note' => 'Notes',
		'new entries' => 'New Entries',
		'new entry' => 'New Entries',
		'inbox' => 'New Entries',
		'raw import' => 'New Entries',
		'raw imports' => 'New Entries',
	];

	private $alias_map = [
		'echo' => 'Echo-7',
		'echo-7' => 'Echo-7',
		'echo_(genesis)' => 'Echo-7',
		'max' => 'Black Max',
		'black max' => 'Black Max',
		'max-x9' => 'Black Max',
		'eva thorne' => 'Eva Thorne',
		'evangeline thorne' => 'Eva Thorne',
	];

	public function __construct() {
		$this->alias_map = $this->merge_persisted_alias_rules($this->alias_map);
	}

	public function parse($raw) {
		$data = $this->parse_partial($raw);
		return $this->finalize($data);
	}

	public function parse_partial($raw) {
		$text = $this->preprocess($raw);
		$lines = $this->explode_lines($text);

		$data = $this->empty_data_template();
		$current_section = 'Notes';
		$current_entity = null;
		$pending_raw_entry = [];

		foreach ($lines as $line_raw) {
			$line = trim($line_raw);

			if ($line === '') {
				if ($current_section === 'New Entries') {
					$this->flush_pending_raw_entry($data, $pending_raw_entry);
				}
				continue;
			}

			$header_match = $this->extract_section_header($line);
			if ($header_match !== null) {
				if ($current_section === 'New Entries') {
					$this->flush_pending_raw_entry($data, $pending_raw_entry);
				}

				$current_section = $header_match['section'];
				$current_entity = null;

				if ($header_match['tail'] === '') {
					continue;
				}

				$line = $header_match['tail'];
			}

			if ($current_section === 'New Entries') {
				if ($this->is_bullet($line)) {
					$this->flush_pending_raw_entry($data, $pending_raw_entry);
					$data[$current_section]['__entries__'][] = $this->strip_bullet_prefix($line);
					continue;
				}

				if ($this->should_start_new_raw_entry($pending_raw_entry, $line)) {
					$this->flush_pending_raw_entry($data, $pending_raw_entry);
				}

				$pending_raw_entry[] = $line;
				continue;
			}

			if (in_array($current_section, ['Relationships', 'NSFW', 'Notes'], true)) {
				$data[$current_section]['__entries__'][] = $this->strip_bullet_prefix($line);
				continue;
			}

			$result = $this->detect_inline_entity_break($line);

			if (!empty($result['entity'])) {
				if ($current_entity !== null && !empty($result['bullet'])) {
					$data[$current_section][$current_entity][] = $result['bullet'];
				}

				$current_entity = $this->canonical_entity_name($result['entity']);
				$data[$current_section] = $this->ensure_entity_array($data[$current_section], $current_entity);
				continue;
			}

			if ($this->is_bullet($line)) {
				if ($current_entity === null) {
					$data[$current_section]['__unassigned__'][] = $this->strip_bullet_prefix($line);
				} else {
					$data[$current_section][$current_entity][] = $this->strip_bullet_prefix($line);
				}
				continue;
			}

			$inline_entity = $this->split_inline_entity_bullet($line);
			if ($inline_entity !== null) {
				$current_entity = $this->canonical_entity_name($inline_entity['entity']);
				$data[$current_section] = $this->ensure_entity_array($data[$current_section], $current_entity);
				$data[$current_section][$current_entity][] = $inline_entity['bullet'];
				continue;
			}

			if ($this->looks_like_entity_header($line)) {
				$entity_line = rtrim($line, ':');
				$current_entity = $this->canonical_entity_name($entity_line);
				$data[$current_section] = $this->ensure_entity_array($data[$current_section], $current_entity);
				continue;
			}

			if ($current_entity !== null) {
				$data[$current_section][$current_entity][] = $line;
			} else {
				$data[$current_section]['__unassigned__'][] = $line;
			}
		}

		if ($current_section === 'New Entries') {
			$this->flush_pending_raw_entry($data, $pending_raw_entry);
		}

		return $data;
	}

	public function finalize($data) {
		$before = $this->collect_entities_by_section($data);
		$data = $this->merge_similar_entities($data);
		$data = $this->ingest_new_entries($data);

		$after = $this->collect_entities_by_section($data);
		$this->last_report = [
			'entities' => $after,
			'new_entities' => $this->diff_entities($before, $after),
		];

		return $data;
	}

	public function get_last_report() {
		return $this->last_report;
	}

	public function preview_raw_import_rows($raw, $existing_data = []) {
		$seed = $this->empty_data_template();
		foreach ((array) $existing_data as $section => $content) {
			if (!isset($seed[$section]) || !is_array($content)) {
				continue;
			}

			foreach ($content as $entity => $items) {
				if (strpos((string) $entity, '__') === 0) {
					continue;
				}
				$seed[$section][(string) $entity] = is_array($items) ? (array) $items : [];
			}
		}

		$parsed = $this->parse_partial("# Raw Import\n" . (string) $raw);
		$entries = isset($parsed['New Entries']['__entries__']) && is_array($parsed['New Entries']['__entries__']) ? $parsed['New Entries']['__entries__'] : [];
		$rows = [];
		$working = $seed;

		foreach ($entries as $entry) {
			$entry = trim((string) $entry);
			if ($entry === '') {
				continue;
			}

			$suggestion = $this->suggest_new_entry_target($working, $entry);
			$rows[] = [
				'section' => $suggestion['section'],
				'entity' => $suggestion['entity'],
				'bullet' => $suggestion['bullet'],
				'source' => $entry,
			];
			$working = $this->apply_preview_row($working, $suggestion['section'], $suggestion['entity'], $suggestion['bullet']);
		}

		return $rows;
	}

	private function preprocess($text) {
		$text = PMM_Utils::normalize_unicode_text($text);
		$text = PMM_Utils::clean_whitespace($text);
		$text = preg_replace('/^[*•]\s+/mu', '- ', $text);
		$text = preg_replace('/\s+[•*]\s+/u', "\n- ", $text);
		$text = preg_replace('/(?<!\n)#\s*/u', "\n# ", $text);

		$text = preg_replace('/\s+#\s+(Characters|Organizations|Locations|Technology\s*\/\s*Systems|Relationships|NSFW|Notes|New Entries|Raw Import)\b/u', "\n# $1", $text);

		foreach ($this->section_names as $section) {
			$pattern = '/#\s+' . preg_quote($section, '/') . '\s+(?=[A-Z])/u';
			$text = preg_replace($pattern, "# {$section}\n", $text);
		}

		$text = preg_replace('/([.!?])\s+-\s+/u', "$1\n- ", $text);

		return $text;
	}

	private function ingest_new_entries($data) {
		if (empty($data['New Entries']['__entries__'])) {
			return $data;
		}

		foreach ($data['New Entries']['__entries__'] as $entry) {
			$entry = trim($entry);
			if ($entry === '') {
				continue;
			}

			$assigned = $this->assign_to_existing_entity($data, $entry);

			if (!$assigned) {
				$guess = $this->guess_new_entry_target($entry);

				if ($guess['section'] === 'Notes') {
					$data['Notes']['__entries__'][] = $entry;
				} else {
					$data[$guess['section']] = $this->ensure_entity_array($data[$guess['section']], $guess['entity']);
					$data[$guess['section']][$guess['entity']][] = $guess['bullet'];
				}
			}
		}

		unset($data['New Entries']);
		return $data;
	}

	private function guess_new_entry_target($entry) {
		if (preg_match('/^\(AI:\)/i', $entry)) {
			return [
				'section' => 'Notes',
				'entity' => null,
				'bullet' => $entry,
			];
		}

		if (preg_match('/\b(Tech|Technologies|Industries|Division|Labs|Holdings|University)\b/u', $entry, $m)) {
			$name = $this->extract_leading_name($entry);
			return [
				'section' => 'Organizations',
				'entity' => $name ?: 'Unsorted Organization',
				'bullet' => $this->strip_entity_prefix($entry, $name),
			];
		}

		if (preg_match('/\b(Isle|Island|Lab|Suite|Tower|Penthouse|Marina|Helipad|Gateway|Cabana|Deck|Terrace)\b/u', $entry)) {
			$name = $this->extract_leading_name($entry);
			return [
				'section' => 'Locations',
				'entity' => $name ?: 'Unsorted Location',
				'bullet' => $this->strip_entity_prefix($entry, $name),
			];
		}

		if (preg_match('/\b(protocol|interface|system|drone|neural|holopad|prototype|chassis)\b/ui', $entry)) {
			$name = $this->extract_leading_name($entry);
			return [
				'section' => 'Technology / Systems',
				'entity' => $name ?: 'Unsorted Technology',
				'bullet' => $this->strip_entity_prefix($entry, $name),
			];
		}

		$name = $this->extract_leading_name($entry);
		if ($name) {
			return [
				'section' => 'Characters',
				'entity' => $name,
				'bullet' => $this->strip_entity_prefix($entry, $name),
			];
		}

		return [
			'section' => 'Characters',
			'entity' => 'Unsorted Inbox',
			'bullet' => $entry,
		];
	}

	private function extract_leading_name($entry) {
		if (preg_match('/^([A-Z][A-Za-z0-9_\-()\'\/.& ]{1,80})\b/u', $entry, $m)) {
			$name = trim($m[1]);
			$name = preg_replace('/\s+(is|has|was|works|keeps|likes|runs|signed|uses|contains|joined|joins|met|meets|left|leaves|entered|enters)\b.*$/ui', '', $name);
			$name = trim($name);
			return $name ?: null;
		}

		return null;
	}

	private function strip_entity_prefix($entry, $entity) {
		if (empty($entity)) {
			return $entry;
		}

		$pattern = '/^' . preg_quote($entity, '/') . '\s*/iu';
		$stripped = preg_replace($pattern, '', $entry);
		$stripped = ltrim((string) $stripped, "-: \t");
		return $stripped ?: $entry;
	}

	private function flush_pending_raw_entry(&$data, &$pending_raw_entry) {
		if (empty($pending_raw_entry)) {
			return;
		}

		$entry = trim(implode(' ', array_map('trim', (array) $pending_raw_entry)));
		if ($entry !== '') {
			$data['New Entries']['__entries__'][] = $entry;
		}

		$pending_raw_entry = [];
	}

	private function should_start_new_raw_entry($pending_raw_entry, $line) {
		if (empty($pending_raw_entry)) {
			return false;
		}

		$previous = trim((string) $pending_raw_entry[count($pending_raw_entry) - 1]);
		$current = trim((string) $line);

		if ($previous === '' || $current === '') {
			return false;
		}

		if ($this->is_likely_raw_continuation($current)) {
			return false;
		}

		return preg_match('/[.!?"\')\]]$/u', $previous) === 1;
	}

	private function is_likely_raw_continuation($line) {
		if ($line === '') {
			return false;
		}

		if (preg_match('/^[a-z(\["\']/', $line) === 1) {
			return true;
		}

		if (preg_match('/^(and|but|or|so|because|which|that|with|while|then|also|however|meanwhile)\b/i', $line) === 1) {
			return true;
		}

		return false;
	}

	private function canonical_entity_name($name) {
		$name = trim($name);
		$name = trim($name, "\t\n\r\0\x0B:;,. ");

		if ($name === '') {
			return 'Unnamed Entity';
		}

		if (strpos($name, '/') !== false) {
			$parts = array_map('trim', explode('/', $name));
			foreach ($parts as $part) {
				$key = mb_strtolower($part);
				if (isset($this->alias_map[$key])) {
					return $this->alias_map[$key];
				}
			}
			return $parts[0];
		}

		$key = mb_strtolower($name);
		if (isset($this->alias_map[$key])) {
			return $this->alias_map[$key];
		}

		$fingerprint = PMM_Utils::name_fingerprint($name);
		if ($fingerprint !== '' && isset($this->alias_map[$fingerprint])) {
			return $this->alias_map[$fingerprint];
		}

		return $name;
	}

	private function merge_similar_entities($data) {
		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems'];

		foreach ($sections as $section) {
			if (empty($data[$section]) || !is_array($data[$section])) {
				continue;
			}

			$merged = [];
			$entity_keys = [];

			foreach ($data[$section] as $entity => $bullets) {
				if (strpos((string) $entity, '__') === 0) {
					$merged[$entity] = isset($merged[$entity]) ? array_merge($merged[$entity], (array) $bullets) : (array) $bullets;
					continue;
				}

				$normalized = PMM_Utils::name_fingerprint($entity);
				$target = $entity;

				if (isset($entity_keys[$normalized])) {
					$target = $entity_keys[$normalized];
				} else {
					$entity_keys[$normalized] = $entity;
				}

				$merged[$target] = isset($merged[$target]) ? array_merge($merged[$target], (array) $bullets) : (array) $bullets;
			}

			$data[$section] = $merged;
		}

		return $data;
	}

	private function detect_inline_entity_break($line) {
		$result = [
			'bullet' => null,
			'entity' => null,
		];

		if (!$this->is_bullet($line)) {
			return $result;
		}

		$content = $this->strip_bullet_prefix($line);

		if (!preg_match('/^(.*[.!?])\s+([A-Z][A-Za-z0-9_\-()\'\/.& ]{2,60})$/u', $content, $m)) {
			return $result;
		}

		$bullet = trim($m[1]);
		$entity = trim($m[2]);

		$words = preg_split('/\s+/u', $entity);
		if (count($words) > 6) {
			return $result;
		}

		$titleish = 0;
		foreach ($words as $word) {
			if (preg_match('/^[A-Z]/u', $word)) {
				$titleish++;
			}
		}

		if ($titleish < max(1, count($words) - 1)) {
			return $result;
		}

		$result['bullet'] = $bullet;
		$result['entity'] = $entity;
		return $result;
	}

	private function empty_data_template() {
		$data = [];

		foreach ($this->section_names as $section) {
			$data[$section] = [];
		}

		return $data;
	}

	private function extract_section_header($line) {
		$has_hash = preg_match('/^#{1,6}\s*/u', $line) === 1;
		$has_colon = strpos($line, ':') !== false;

		if (!$has_hash && !$has_colon) {
			return null;
		}

		if (!preg_match('/^(?:#{1,6}\s*)?([^:]+?)(?::\s*(.*))?$/u', $line, $m)) {
			return null;
		}

		$raw = PMM_Utils::fingerprint($m[1]);
		$raw = str_replace('  ', ' ', $raw);
		$section = $this->section_aliases[$raw] ?? null;

		if ($section === null) {
			return null;
		}

		return [
			'section' => $section,
			'tail' => isset($m[2]) ? trim($m[2]) : '',
		];
	}

	private function assign_to_existing_entity(&$data, $entry) {
		$suggestion = $this->suggest_existing_entity_target($data, $entry);
		if ($suggestion === null) {
			return false;
		}

		$data[$suggestion['section']][$suggestion['entity']][] = $suggestion['bullet'];
		return true;
	}

	private function suggest_existing_entity_target($data, $entry) {
		$best = [
			'score' => 0.0,
			'section' => null,
			'entity' => null,
		];

		foreach (['Characters', 'Organizations', 'Locations', 'Technology / Systems'] as $section) {
			if (empty($data[$section]) || !is_array($data[$section])) {
				continue;
			}

			foreach ($data[$section] as $entity => $bullets) {
				if (strpos((string) $entity, '__') === 0) {
					continue;
				}

				$name_score = PMM_Utils::contains_name_score($entry, $entity);
				if ($name_score > $best['score']) {
					$best = [
						'score' => $name_score,
						'section' => $section,
						'entity' => $entity,
					];
				}
			}
		}

		if ($best['section'] === null || $best['score'] < 0.50) {
			return null;
		}

		return [
			'section' => $best['section'],
			'entity' => $best['entity'],
			'bullet' => $this->strip_entity_prefix($entry, $best['entity']),
		];
	}

	private function suggest_new_entry_target($data, $entry) {
		$existing = $this->suggest_existing_entity_target($data, $entry);
		if ($existing !== null) {
			return $existing;
		}

		return $this->guess_new_entry_target($entry);
	}

	private function apply_preview_row($data, $section, $entity, $bullet) {
		$section = trim((string) $section);
		$entity = trim((string) $entity);
		$bullet = trim((string) $bullet);

		if ($section === '' || !isset($data[$section])) {
			$section = 'Notes';
		}

		if ($section === 'Notes' || $section === 'Relationships' || $section === 'NSFW' || $entity === '') {
			if (!isset($data[$section]['__entries__']) || !is_array($data[$section]['__entries__'])) {
				$data[$section]['__entries__'] = [];
			}
			if ($bullet !== '') {
				$data[$section]['__entries__'][] = $bullet;
			}
			return $data;
		}

		$data[$section] = $this->ensure_entity_array($data[$section], $entity);
		if ($bullet !== '') {
			$data[$section][$entity][] = $bullet;
		}

		return $data;
	}

	private function ensure_entity_array($section_data, $entity) {
		if (!isset($section_data[$entity]) || !is_array($section_data[$entity])) {
			$section_data[$entity] = [];
		}

		return $section_data;
	}

	private function split_inline_entity_bullet($line) {
		if (!preg_match('/^([A-Z][A-Za-z0-9_\-()\'\/.& ]{1,80})\s+-\s+(.+)$/u', $line, $m)) {
			return null;
		}

		$entity = trim($m[1]);
		$bullet = trim($m[2]);

		if ($entity === '' || $bullet === '') {
			return null;
		}

		return [
			'entity' => $entity,
			'bullet' => $bullet,
		];
	}

	private function explode_lines($text) {
		$lines = preg_split('/\n/u', $text);
		$out = [];

		foreach ($lines as $line) {
			$parts = preg_split('/(?=\s*#\s*(?:Characters|Organizations|Locations|Technology\s*\/\s*Systems|Relationships|NSFW|Notes|New Entries|Raw Import)\b)/iu', (string) $line);
			foreach ($parts as $part) {
				$trimmed = trim($part);
				if ($trimmed !== '') {
					$out[] = $trimmed;
				}
			}
		}

		return $out;
	}

	private function is_bullet($line) {
		return preg_match('/^[-*•]\s+/u', $line) === 1;
	}

	private function strip_bullet_prefix($line) {
		if (!$this->is_bullet($line)) {
			return $line;
		}

		return trim((string) preg_replace('/^[-*•]\s+/u', '', $line));
	}

	private function looks_like_entity_header($line) {
		return preg_match('/^[A-Z][A-Za-z0-9_\-()\'\/.& ]{1,80}:?$/u', $line) === 1;
	}

	private function collect_entities_by_section($data) {
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

			$out[$section] = array_values(array_unique($out[$section]));
		}

		return $out;
	}

	private function diff_entities($before, $after) {
		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems'];
		$out = [];

		foreach ($sections as $section) {
			$before_list = isset($before[$section]) && is_array($before[$section]) ? $before[$section] : [];
			$after_list = isset($after[$section]) && is_array($after[$section]) ? $after[$section] : [];
			$diff = array_values(array_diff($after_list, $before_list));
			$out[$section] = $diff;
		}

		return $out;
	}

	private function merge_persisted_alias_rules($base_map) {
		if (!function_exists('get_option')) {
			return $base_map;
		}

		$stored = get_option('pmm_alias_rules', []);
		if (!is_array($stored)) {
			return $base_map;
		}

		foreach ($stored as $source => $canonical) {
			$canonical = trim((string) $canonical);
			$source = trim((string) $source);

			if ($source === '' || $canonical === '') {
				continue;
			}

			$base_map[mb_strtolower($source)] = $canonical;
			$base_map[PMM_Utils::name_fingerprint($source)] = $canonical;
		}

		return $base_map;
	}
}