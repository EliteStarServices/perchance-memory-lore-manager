<?php

if (!defined('ABSPATH')) {
	exit;
}

class PMM_Renderer {

	private $section_order = [
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

	public function render($data, $format = 'md') {
		$lines = [];

		foreach ($this->section_order as $section) {
			if (empty($data[$section])) {
				continue;
			}

			$lines[] = '# ' . $section;

			if (in_array($section, ['Relationships', 'NSFW', 'Notes', 'World Building', 'Technology / Systems', 'Vehicles / Transportation', 'New Entries'], true)) {
				$section_items = $this->section_level_items_from_bucket(isset($data[$section]) && is_array($data[$section]) ? $data[$section] : []);
				foreach ($section_items as $entry) {
					$lines[] = '- ' . $entry;
					$lines[] = '';
				}
				continue;
			}

			foreach ($data[$section] as $entity => $bullets) {
				if (!is_array($bullets)) {
					continue;
				}

				if ($entity === '__unassigned__' || $entity === '__entries__') {
					$lines[] = 'Unsorted';
					foreach ($bullets as $bullet) {
						$lines[] = '- ' . $bullet;
						$lines[] = '';
					}
					continue;
				}

				if (strpos((string) $entity, '__') === 0) {
					continue;
				}

				$lines[] = $entity;
				foreach ($bullets as $bullet) {
					$lines[] = '- ' . $bullet;
					$lines[] = '';
				}
			}
		}

		$output = rtrim(implode("\n", $lines)) . "\n";

		if ($format === 'txt') {
			return $output;
		}

		return $output;
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
}