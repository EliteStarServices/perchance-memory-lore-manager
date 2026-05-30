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
		'Relationships',
		'NSFW',
		'Notes',
	];

	public function render($data, $format = 'md') {
		$lines = [];

		foreach ($this->section_order as $section) {
			if (empty($data[$section])) {
				continue;
			}

			$lines[] = '# ' . $section;

			if (in_array($section, ['Relationships', 'NSFW', 'Notes'], true)) {
				foreach (($data[$section]['__entries__'] ?? []) as $entry) {
					$lines[] = '- ' . $entry;
				}

				foreach (($data[$section]['__unassigned__'] ?? []) as $entry) {
					$lines[] = '- ' . $entry;
				}

				$lines[] = '';
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
					}
					$lines[] = '';
					continue;
				}

				if (strpos((string) $entity, '__') === 0) {
					continue;
				}

				$lines[] = $entity;
				foreach ($bullets as $bullet) {
					$lines[] = '- ' . $bullet;
				}
				$lines[] = '';
			}
		}

		$output = rtrim(implode("\n", $lines)) . "\n";

		if ($format === 'txt') {
			return $output;
		}

		return $output;
	}
}