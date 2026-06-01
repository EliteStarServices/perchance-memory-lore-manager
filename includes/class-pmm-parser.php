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
		'Vehicles / Transportation',
		'World Building',
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
		'vehicles / transportation' => 'Vehicles / Transportation',
		'vehicles/transportation' => 'Vehicles / Transportation',
		'vehicles transportation' => 'Vehicles / Transportation',
		'vehicles' => 'Vehicles / Transportation',
		'vehicle' => 'Vehicles / Transportation',
		'transportation' => 'Vehicles / Transportation',
		'transport' => 'Vehicles / Transportation',
		'world building' => 'World Building',
		'worldbuilding' => 'World Building',
		'world lore' => 'World Building',
		'lore' => 'World Building',
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

	private $classification_settings = [
		'character_veto' => 1,
		'organizations_min_score' => 2,
		'locations_min_score' => 2,
		'technology_min_score' => 2,
		'vehicles_min_score' => 2,
		'world_building_min_score' => 2,
	];

	public function __construct() {
		$this->alias_map = $this->merge_persisted_alias_rules($this->alias_map);
		$this->classification_settings = $this->get_classification_settings_option();
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
					$pending_raw_entry[] = $this->strip_bullet_prefix($line);
					continue;
				}

				$pending_raw_entry[] = $line;
				continue;
			}

			if (in_array($current_section, $this->section_level_sections(), true)) {
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

		$text = preg_replace('/\s+#\s+(Characters|Organizations|Locations|Technology\s*\/\s*Systems|Vehicles\s*\/\s*Transportation|World\s*Building|Relationships|NSFW|Notes|New Entries|Raw Import)\b/u', "\n# $1", $text);

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

				if (in_array($guess['section'], $this->section_level_sections(), true) || empty($guess['entity'])) {
					if (!isset($data[$guess['section']]['__entries__']) || !is_array($data[$guess['section']]['__entries__'])) {
						$data[$guess['section']]['__entries__'] = [];
					}
					$data[$guess['section']]['__entries__'][] = $guess['bullet'];
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

		$settings = $this->classification_settings;
		$looks_like_character_fact = !empty($settings['character_veto']) && $this->looks_like_character_fact($entry);
		$organization_score = $this->organization_signal_score($entry);
		$location_score = $this->location_signal_score($entry);
		$technology_score = $this->technology_signal_score($entry);
		$vehicle_score = $this->vehicle_signal_score($entry);
		$world_building_score = $this->world_building_signal_score($entry);
		$org_min_score = max(1, min(3, (int) $settings['organizations_min_score']));
		$location_min_score = max(1, min(3, (int) $settings['locations_min_score']));
		$technology_min_score = max(1, min(3, (int) $settings['technology_min_score']));
		$vehicles_min_score = max(1, min(3, (int) $settings['vehicles_min_score']));
		$world_building_min_score = max(1, min(3, (int) $settings['world_building_min_score']));

		if (!$looks_like_character_fact && $organization_score >= $org_min_score && $organization_score >= $location_score && $organization_score >= $technology_score && $organization_score >= $vehicle_score && $organization_score >= $world_building_score) {
			$name = $this->extract_leading_name($entry);
			return [
				'section' => 'Organizations',
				'entity' => $name ?: 'Unsorted Organization',
				'bullet' => $this->strip_entity_prefix($entry, $name),
			];
		}

		if (!$looks_like_character_fact && $vehicle_score >= $vehicles_min_score && $vehicle_score >= $location_score && $vehicle_score >= $technology_score && $vehicle_score >= $world_building_score) {
			$name = $this->extract_leading_name($entry);
			return [
				'section' => 'Vehicles / Transportation',
				'entity' => $name ?: 'Unsorted Vehicle',
				'bullet' => $this->strip_entity_prefix($entry, $name),
			];
		}

		if (!$looks_like_character_fact && $location_score >= $location_min_score && $location_score >= $technology_score && $location_score >= $world_building_score) {
			$name = $this->extract_leading_name($entry);
			return [
				'section' => 'Locations',
				'entity' => $name ?: 'Unsorted Location',
				'bullet' => $this->strip_entity_prefix($entry, $name),
			];
		}

		if (!$looks_like_character_fact && $world_building_score >= $world_building_min_score && $world_building_score >= $technology_score) {
			return [
				'section' => 'World Building',
				'entity' => '',
				'bullet' => $entry,
			];
		}

		if (!$looks_like_character_fact && $technology_score >= $technology_min_score) {
			$name = $this->extract_leading_name($entry);
			return [
				'section' => 'Technology / Systems',
				'entity' => $name ?: 'Unsorted Technology',
				'bullet' => $this->strip_entity_prefix($entry, $name),
			];
		}

		$character_name = $this->extract_character_anchor_name($entry);
		if ($character_name !== null) {
			return [
				'section' => 'Characters',
				'entity' => $character_name,
				'bullet' => $this->strip_entity_prefix($entry, $character_name),
			];
		}

		return [
			'section' => 'Notes',
			'entity' => null,
			'bullet' => $entry,
		];
	}

	private function looks_like_character_fact($entry) {
		if (!is_string($entry) || trim($entry) === '') {
			return false;
		}

		if (preg_match('/\b(he|she|they|him|her|his|hers|their|theirs)\b/i', $entry)) {
			return true;
		}

		if (preg_match('/\b(years? old|age\s*\d+|hair|eyes|voice|smile|smiles|frowns|cries|laughs|kisses|hugs|wears|carries|flirts|jealous|angry|afraid|nervous|pregnant|injured|married|dating|boyfriend|girlfriend|husband|wife|mother|father|sister|brother|friend|lover)\b/i', $entry)) {
			return true;
		}

		if (preg_match('/\b(said|says|told|asked|replied|whispered|shouted|met|meets|loves|hates|fears|wants|needs|plans|decides|promised)\b/i', $entry)) {
			return true;
		}

		return false;
	}

	private function organization_signal_score($entry) {
		if (!is_string($entry) || trim($entry) === '') {
			return 0;
		}

		$score = 0;

		if (preg_match('/\b(Tech|Technologies|Industries|Division|Labs|Holdings|University|Company|Corp|Corporation|Agency|Council|Syndicate|Guild|Institute|Foundation|Committee|Department|Bureau|Office|Consortium|Group|Team|Unit)\b/ui', $entry)) {
			$score++;
		}

		if (preg_match('/\b(founded|headquartered|subsidiary|acquired|merged|employees|staff|board|ceo|director|division|branch|policy|charter|mandate|operations|funding|contract)\b/ui', $entry)) {
			$score++;
		}

		if (preg_match('/\b(organization|organisation|company|agency|department|committee|board|institution)\b/ui', $entry)) {
			$score++;
		}

		return $score;
	}

	private function vehicle_signal_score($entry) {
		if (!is_string($entry) || trim($entry) === '') {
			return 0;
		}

		$score = 0;
		if (preg_match('/\b(ship|shuttle|fighter|frigate|freighter|carrier|bike|motorbike|car|truck|van|train|tram|jet|plane|aircraft|helicopter|vtol|dropship|mech|walker|tank|submarine|boat|vessel|transport|hovercraft|speeder|pod|wagon)\b/ui', $entry)) {
			$score++;
		}
		if (preg_match('/\b(cockpit|hangar|crew|passengers|cargo|engine class|hull|armor plating|fuel|range|route|fleet|registry|license plate|pilot seat|chassis)\b/ui', $entry)) {
			$score++;
		}
		if (preg_match('/\b(drive|flies|drives|pilots|boards|docks|launches|lands|cruises)\b/ui', $entry)) {
			$score++;
		}

		return $score;
	}

	private function world_building_signal_score($entry) {
		if (!is_string($entry) || trim($entry) === '') {
			return 0;
		}

		$score = 0;
		if (preg_match('/\b(law|custom|tradition|culture|religion|economy|currency|history|myth|legend|calendar|festival|era|timeline|government|politics|society|social class|magic system|rule of magic|canon|setting rule|world rule)\b/ui', $entry)) {
			$score++;
		}
		if (preg_match('/\b(in this world|across the world|setting|worldbuilding|society|civilization|empire|kingdom|nation|region-wide|universal rule)\b/ui', $entry)) {
			$score++;
		}
		if (preg_match('/\b(people believe|it is common|it is forbidden|it is legal|it is illegal|traditionally|by custom|by law|historically)\b/ui', $entry)) {
			$score++;
		}

		return $score;
	}

	private function location_signal_score($entry) {
		if (!is_string($entry) || trim($entry) === '') {
			return 0;
		}

		$score = 0;

		if (preg_match('/\b(Isle|Island|Lab|Suite|Tower|Penthouse|Marina|Helipad|Gateway|Cabana|Deck|Terrace|District|Neighborhood|City|Town|Village|Station|Base|Campus|Facility|Compound|Bunker|Room|Hall|Street|Avenue|Boulevard|Plaza|Port|Dock|Harbor|Valley|Mountain|Forest|Desert)\b/ui', $entry)) {
			$score++;
		}

		if (preg_match('/\b(located|situated|address|coordinates|district|zone|region|terrain|climate|population|entrance|exit|floor|level|nearby|surrounding|neighborhood|headquarters|hq)\b/ui', $entry)) {
			$score++;
		}

		if (preg_match('/\b(in|at|near|inside|outside|within)\s+(the\s+)?(district|zone|sector|region|city|town|village|island|tower|suite|facility|campus|base|station|harbor|marina)\b/ui', $entry)) {
			$score++;
		}

		return $score;
	}

	private function technology_signal_score($entry) {
		if (!is_string($entry) || trim($entry) === '') {
			return 0;
		}

		$score = 0;

		if (preg_match('/\b(protocol|interface|system|drone|neural|holopad|prototype|chassis|firmware|software|hardware|algorithm|module|sensor|reactor|engine|database|network|encryption|api|platform|device|toolkit|framework|bandwidth|calibration|telemetry|autopilot)\b/ui', $entry)) {
			$score++;
		}

		if (preg_match('/\b(version|model|spec|specs|build|release|patch|upgrade|downgrade|latency|throughput|power output|signal|runtime|diagnostic|failsafe|integration|deployment)\b/ui', $entry)) {
			$score++;
		}

		if (preg_match('/\b(v\d+(?:\.\d+)*)\b/u', $entry)) {
			$score++;
		}

		return $score;
	}

	private function classification_settings_defaults() {
		return [
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
		$settings['character_veto'] = !empty($stored['character_veto']) ? 1 : 0;
		$settings['organizations_min_score'] = isset($stored['organizations_min_score']) ? max(1, min(3, (int) $stored['organizations_min_score'])) : $defaults['organizations_min_score'];
		$settings['locations_min_score'] = isset($stored['locations_min_score']) ? max(1, min(3, (int) $stored['locations_min_score'])) : $defaults['locations_min_score'];
		$settings['technology_min_score'] = isset($stored['technology_min_score']) ? max(1, min(3, (int) $stored['technology_min_score'])) : $defaults['technology_min_score'];
		$settings['vehicles_min_score'] = isset($stored['vehicles_min_score']) ? max(1, min(3, (int) $stored['vehicles_min_score'])) : $defaults['vehicles_min_score'];
		$settings['world_building_min_score'] = isset($stored['world_building_min_score']) ? max(1, min(3, (int) $stored['world_building_min_score'])) : $defaults['world_building_min_score'];

		return $settings;
	}

	private function section_level_sections() {
		return ['Relationships', 'NSFW', 'Notes', 'World Building'];
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

	private function extract_character_anchor_name($entry) {
		$entry = trim((string) $entry);
		if ($entry === '') {
			return null;
		}

		if ($this->entry_has_multiple_named_subjects($entry)) {
			return null;
		}

		if (preg_match('/^([A-Z][A-Za-z0-9_\-\'\.]{1,30}(?:\s+[A-Z][A-Za-z0-9_\-\'\.]{1,30}){0,2})\s*(?:[:\-]\s+|\s+(?:is|was|has|had|works|worked|served|joined|joins|met|meets|likes|wants|needs|fears|plans)\b)/u', $entry, $m)) {
			$name = trim((string) $m[1]);
			if ($name !== '' && !$this->looks_like_non_character_name($name)) {
				return $name;
			}
		}

		$name = $this->extract_leading_name($entry);
		if ($name === null || $name === '' || $this->looks_like_non_character_name($name)) {
			return null;
		}

		if (preg_match('/\b(he|she|they|him|her|his|hers|their|theirs)\b/i', $entry) === 1) {
			return $name;
		}

		if (preg_match('/\b(is|was|has|had|works|worked|serves|served|joined|joins|met|meets|likes|wants|needs|fears|plans)\b/i', $entry) === 1) {
			return $name;
		}

		return null;
	}

	private function looks_like_non_character_name($name) {
		if (!is_string($name)) {
			return true;
		}

		$name = trim($name);
		if ($name === '') {
			return true;
		}

		if (preg_match('/\b(Tech|Technologies|Industries|Division|Labs|Holdings|University|Company|Corp|Corporation|Agency|Council|Syndicate|Guild|Institute|Foundation|Committee|Department|Bureau|Office|Consortium|Group|Team|Unit|Station|Base|District|City|Town|Village|Facility|System|Protocol|Engine|Network|Platform|Vehicle|Transport)\b/ui', $name) === 1) {
			return true;
		}

		return false;
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

		if ($this->previous_raw_line_requires_continuation($previous)) {
			return false;
		}

		// New Entries is now line-first: if a line is not an obvious continuation,
		// treat it as a new entry. This aligns with Perchance one-entry-per-line data.
		return true;
	}

	private function previous_raw_line_requires_continuation($line) {
		$line = rtrim((string) $line);
		if ($line === '') {
			return false;
		}

		if (preg_match('/[,;:\/\-]\s*$/u', $line) === 1) {
			return true;
		}

		if (preg_match('/[\(\[\{"\']\s*$/u', $line) === 1) {
			return true;
		}

		if (preg_match('/\b(and|or|but|because|with|including|such\s+as|for\s+example|e\.g)\s*$/iu', $line) === 1) {
			return true;
		}

		return false;
	}

	private function looks_like_new_raw_statement($line) {
		$line = trim((string) $line);
		if ($line === '') {
			return false;
		}

		if (preg_match('/^(?:[-*•]\s+)/u', $line) === 1) {
			return true;
		}

		if (preg_match('/^(?:[A-Z0-9"\'\(])/u', $line) === 1) {
			return true;
		}

		return false;
	}

	private function split_raw_entry_candidates($entry) {
		$entry = trim((string) preg_replace('/\s+/u', ' ', (string) $entry));
		if ($entry === '') {
			return [];
		}

		if (mb_strlen($entry) < 260) {
			return [$entry];
		}

		$sentences = $this->split_into_sentences($entry);
		if (count($sentences) <= 1) {
			return [$entry];
		}

		$chunks = [];
		$buffer = '';

		foreach ($sentences as $sentence) {
			$sentence = trim((string) $sentence);
			if ($sentence === '') {
				continue;
			}

			if ($buffer === '') {
				$buffer = $sentence;
				continue;
			}

			$next_length = mb_strlen($buffer) + 1 + mb_strlen($sentence);
			if ($next_length > 260 || $this->is_likely_new_subject_sentence($sentence)) {
				$chunks[] = $buffer;
				$buffer = $sentence;
				continue;
			}

			$buffer .= ' ' . $sentence;
		}

		if ($buffer !== '') {
			$chunks[] = $buffer;
		}

		$out = [];
		foreach ($chunks as $chunk) {
			$chunk = trim((string) $chunk);
			if ($chunk === '') {
				continue;
			}
			if (!empty($out) && mb_strlen($chunk) < 56) {
				$out[count($out) - 1] .= ' ' . $chunk;
				continue;
			}
			$out[] = $chunk;
		}

		if (empty($out)) {
			return [$entry];
		}

		return $out;
	}

	private function split_into_sentences($text) {
		$text = trim((string) $text);
		if ($text === '') {
			return [];
		}

		$parts = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9"\'])/u', $text);
		if (!is_array($parts) || count($parts) <= 1) {
			return [$text];
		}

		$sentences = [];
		foreach ($parts as $part) {
			$part = trim((string) $part);
			if ($part === '') {
				continue;
			}

			if (!empty($sentences) && $this->ends_with_non_terminal_abbreviation($sentences[count($sentences) - 1])) {
				$sentences[count($sentences) - 1] .= ' ' . $part;
				continue;
			}

			$sentences[] = $part;
		}

		return !empty($sentences) ? $sentences : [$text];
	}

	private function is_likely_new_subject_sentence($sentence) {
		$sentence = trim((string) $sentence);
		if ($sentence === '') {
			return false;
		}

		if (preg_match('/^[A-Z][A-Za-z0-9_\-' . "'" . ']{1,40}(?:\s+[A-Z][A-Za-z0-9_\-' . "'" . ']{1,40}){0,2}\s+(?:is|was|has|had|works|worked|served|joined|joins|leads|commands|pilots|built|created|founded|owns|operates|maintains|reports)\b/u', $sentence) === 1) {
			return true;
		}

		if (preg_match('/^(Meanwhile|Separately|Later|Afterward|In contrast|However|Additionally|Also),?\b/u', $sentence) === 1) {
			return true;
		}

		return false;
	}

	private function looks_like_sentence_boundary($text) {
		$trimmed = rtrim((string) $text);
		if ($trimmed === '') {
			return false;
		}

		if (preg_match('/[!?]["\')\]]*$/u', $trimmed) === 1) {
			return true;
		}

		if (preg_match('/\.["\')\]]*$/u', $trimmed) !== 1) {
			return false;
		}

		if ($this->ends_with_non_terminal_abbreviation($trimmed)) {
			return false;
		}

		return true;
	}

	private function ends_with_non_terminal_abbreviation($text) {
		$trimmed = rtrim((string) $text);
		if ($trimmed === '') {
			return false;
		}

		$patterns = [
			'/\b(?:mr|mrs|ms|dr|prof|sr|jr|st|mt|rev|gen|col|capt|lt|sgt|adm|gov|sen|rep|pres|hon|messrs|mme|mlle|etc|vs|no|dept|fig|inc|ltd|co|corp|assn|univ)\.["\')\]]*$/iu',
			'/\b(?:e\.g|i\.e|a\.k\.a|u\.s|u\.k|u\.n|d\.c|p\.m|a\.m)\.["\')\]]*$/iu',
			'/(?:\b[A-Z]\.){2,}["\')\]]*$/u',
			'/\b[A-Z]\.["\')\]]*$/u',
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $trimmed) === 1) {
				return true;
			}
		}

		return false;
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
		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation'];

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
		$strong_matches = 0;

		foreach (['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation'] as $section) {
			if (empty($data[$section]) || !is_array($data[$section])) {
				continue;
			}

			foreach ($data[$section] as $entity => $bullets) {
				if (strpos((string) $entity, '__') === 0) {
					continue;
				}

				$name_score = PMM_Utils::contains_name_score($entry, $entity);
				$explicit = $this->entry_mentions_entity_name($entry, (string) $entity);
				$effective_score = $explicit ? $name_score : ($name_score * 0.82);

				if ($explicit && $name_score >= 0.60) {
					$strong_matches++;
				}

				if ($effective_score > $best['score']) {
					$best = [
						'score' => $effective_score,
						'section' => $section,
						'entity' => $entity,
					];
				}
			}
		}

		$minimum_score = $this->entry_has_multiple_named_subjects($entry) ? 0.84 : 0.72;
		if ($best['section'] === null || $best['score'] < $minimum_score) {
			return null;
		}

		if ($strong_matches > 1 && $best['score'] < 0.90) {
			return null;
		}

		return [
			'section' => $best['section'],
			'entity' => $best['entity'],
			'bullet' => $this->strip_entity_prefix($entry, $best['entity']),
		];
	}

	private function entry_mentions_entity_name($entry, $entity) {
		$entry = trim((string) $entry);
		$entity = trim((string) $entity);
		if ($entry === '' || $entity === '') {
			return false;
		}

		$name_fp = PMM_Utils::name_fingerprint($entity);
		if ($name_fp === '') {
			return false;
		}

		$tokens = array_values(array_filter(preg_split('/\s+/u', $name_fp), static function ($token) {
			return is_string($token) && $token !== '';
		}));

		if (empty($tokens)) {
			return false;
		}

		if (count($tokens) === 1 && mb_strlen($tokens[0]) < 4) {
			return false;
		}

		$escaped = array_map(static function ($token) {
			return preg_quote($token, '/');
		}, $tokens);
		$pattern = '/\b' . implode('\s+', $escaped) . '\b/ui';

		return preg_match($pattern, $entry) === 1;
	}

	private function entry_has_multiple_named_subjects($entry) {
		$entry = trim((string) $entry);
		if ($entry === '') {
			return false;
		}

		preg_match_all('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,2})\b/u', $entry, $m);
		if (empty($m[1]) || !is_array($m[1])) {
			return false;
		}

		$ignore = [
			'The' => true,
			'A' => true,
			'An' => true,
			'In' => true,
			'On' => true,
			'At' => true,
			'Of' => true,
			'And' => true,
			'But' => true,
			'However' => true,
			'Meanwhile' => true,
		];

		$subjects = [];
		foreach ($m[1] as $candidate) {
			$candidate = trim((string) $candidate);
			if ($candidate === '' || isset($ignore[$candidate])) {
				continue;
			}
			$subjects[$candidate] = true;
		}

		return count($subjects) >= 3;
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

		if (in_array($section, $this->section_level_sections(), true) || $entity === '') {
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
			if (trim((string) $line) === '') {
				$out[] = '';
				continue;
			}

			$parts = preg_split('/(?=\s*#\s*(?:Characters|Organizations|Locations|Technology\s*\/\s*Systems|Vehicles\s*\/\s*Transportation|World\s*Building|Relationships|NSFW|Notes|New Entries|Raw Import)\b)/iu', (string) $line);
			$added = false;
			foreach ($parts as $part) {
				$trimmed = trim($part);
				if ($trimmed !== '') {
					$out[] = $trimmed;
					$added = true;
				}
			}

			if (!$added) {
				$out[] = '';
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
		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation'];
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
		$sections = ['Characters', 'Organizations', 'Locations', 'Technology / Systems', 'Vehicles / Transportation'];
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