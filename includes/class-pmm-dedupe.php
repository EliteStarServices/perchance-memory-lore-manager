<?php

if (!defined('ABSPATH')) {
	exit;
}

class PMM_Dedupe {
	private $stats = [];

	public function clean($data, $mode = 'balanced', $drop_sequences = []) {
		$out = [];
		$drop_sequences = $this->normalize_sequences($drop_sequences);
		$this->reset_stats();

		foreach ($data as $section => $content) {
			$out[$section] = [];

			foreach ($content as $key => $items) {
				if (is_array($items)) {
					$out[$section][$key] = $this->dedupe_items($items, $mode, $section, $key, $drop_sequences);
				}
			}
		}

		return $out;
	}

	public function get_stats() {
		return $this->stats;
	}

	private function dedupe_items($items, $mode, $section, $key, $drop_sequences) {
		$kept = [];
		$seen = [];
		$word_set_cache = [];
		$near_dup_window = 200;

		foreach ($items as $item) {
			$item = PMM_Utils::normalize_bullet($item);
			if ($item === '') {
				$this->increment_stat('removed_empty');
				continue;
			}

			if ($section === 'Notes' && preg_match('/^\(AI:\)/i', $item)) {
				$kept[] = $item;
				$this->increment_stat('kept_ai_notes');
				continue;
			}

			if ($this->contains_drop_sequence($item, $drop_sequences)) {
				$this->increment_stat('removed_by_sequence');
				continue;
			}

			$trivial_reason = $this->trivial_reason($item, $section, $mode);
			if ($trivial_reason !== '') {
				$this->increment_stat($trivial_reason);
				continue;
			}

			$fp = PMM_Utils::fingerprint($item);

			if (isset($seen[$fp])) {
				$existing_index = $seen[$fp];
				$kept[$existing_index] = $this->choose_better($kept[$existing_index], $item);
				$this->increment_stat('removed_exact_duplicate');
				continue;
			}

			if (!isset($word_set_cache[$fp])) {
				$word_set_cache[$fp] = PMM_Utils::word_set($item);
				if (count($word_set_cache) > 600) {
					$word_set_cache = array_slice($word_set_cache, -400, 400, true);
				}
			}
			$item_set = $word_set_cache[$fp];

			$matched = false;
			$kept_keys = array_keys($kept);
			$scan_slice = array_slice($kept_keys, -$near_dup_window, $near_dup_window, true);
			foreach ($scan_slice as $i) {
				$existing = $kept[$i];
				if (!is_string($existing)) {
					continue;
				}
				$existing_fp = PMM_Utils::fingerprint($existing);
				if (!isset($word_set_cache[$existing_fp])) {
					$word_set_cache[$existing_fp] = PMM_Utils::word_set($existing);
					if (count($word_set_cache) > 600) {
						$word_set_cache = array_slice($word_set_cache, -400, 400, true);
					}
				}
				$existing_set = $word_set_cache[$existing_fp];

				if ($this->is_duplicateish_sets($existing_set, $item_set, $mode)) {
					$kept[$i] = $this->choose_better($existing, $item);
					$matched = true;
					$this->increment_stat('removed_near_duplicate');
					break;
				}
			}

			if (!$matched) {
				$kept[] = $item;
				$seen[$fp] = array_key_last($kept);
				$this->increment_stat('kept_entries');
			}
		}

		return array_values(array_filter($kept, static function($value) {
			return is_string($value) && $value !== '';
		}));
	}

	private function trivial_reason($item, $section, $mode) {
		if ($this->is_mundane_noise($item)) {
			return 'removed_mundane_noise';
		}

		if ($section === 'Notes' && $mode === 'strict') {
			return '';
		}

		$patterns = [
			'/^This is Block \d+/i',
			'/^Ready to append Block \d+/i',
			'/^All repeated entries merged/i',
			'/^fully condensed/i',
			'/^Perchance-optimized/i',
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $item)) {
				return 'removed_meta_trivial';
			}
		}

		if ($mode === 'aggressive') {
			$words = preg_split('/\s+/u', PMM_Utils::fingerprint($item));
			if (count($words) < 4) {
				return 'removed_aggressive_short';
			}
		}

		return '';
	}

	private function is_mundane_noise($item) {
		$normalized = PMM_Utils::normalize_bullet($item);
		if ($normalized === '') {
			return true;
		}

		$patterns = [
			'/^[-_=*~]{3,}$/u',
			'/^\W+$/u',
			'/^(?:block|blk)\s*(?:number\s*)?#?\d+\b(?:\s*[:\-].*)?$/iu',
			'/^(?:ref|reference|see|from|to)\s+(?:block|blk)\s*#?\d+\b.*$/iu',
			'/\b(?:ready to append|append(?:ed|ing)?|merged|dedup(?:e|ed)?|condensed|processed|import(?:ed|ing)?)\b.*\b(?:block|blk)\s*#?\d+\b/iu',
			'/^(?:this is|these are)\s+block\s*#?\d+\b.*$/iu',
			'/^(?:line|entry|item)\s*#?\d+\s*(?:only|placeholder|temp(?:orary)?)\b.*$/iu',
		];

		$patterns = apply_filters('pmm_mundane_noise_patterns', $patterns, $normalized);

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $normalized)) {
				return true;
			}
		}

		return false;
	}

	private function is_duplicateish($a, $b, $mode) {
		$sim = PMM_Utils::jaccard_similarity($a, $b);
		$subset = PMM_Utils::is_subset_duplicate($a, $b);

		if ($mode === 'strict') {
			return $sim >= 0.97;
		}

		if ($mode === 'aggressive') {
			return $sim >= 0.72 || $subset;
		}

		return $sim >= 0.82 || $subset;
	}

	private function is_duplicateish_sets($setA, $setB, $mode) {
		if (!$setA && !$setB) {
			$sim = 1.0;
			$subset = true;
		} else {
			$intersection = array_intersect_key($setA, $setB);
			$union_count = count($setA + $setB);
			$sim = $union_count > 0 ? count($intersection) / $union_count : 0.0;
			$aInB = count($intersection) / max(count($setA), 1);
			$bInA = count($intersection) / max(count($setB), 1);
			$subset = ($aInB > 0.9 || $bInA > 0.9);
		}

		if ($mode === 'strict') {
			return $sim >= 0.97;
		}

		if ($mode === 'aggressive') {
			return $sim >= 0.72 || $subset;
		}

		return $sim >= 0.82 || $subset;
	}

	private function choose_better($a, $b) {
		return PMM_Utils::score_bullet($b) > PMM_Utils::score_bullet($a) ? $b : $a;
	}

	private function normalize_sequences($drop_sequences) {
		if (!is_array($drop_sequences)) {
			return [];
		}

		$out = [];
		foreach ($drop_sequences as $sequence) {
			$sequence = trim((string) $sequence);
			if ($sequence !== '') {
				$out[] = $sequence;
			}
		}

		return array_values(array_unique($out));
	}

	private function contains_drop_sequence($item, $drop_sequences) {
		if (empty($drop_sequences)) {
			return false;
		}

		$item_lower = mb_strtolower($item);

		foreach ($drop_sequences as $sequence) {
			$sequence_lower = mb_strtolower((string) $sequence);
			if ($sequence_lower === '') {
				continue;
			}

			if (mb_strpos($item_lower, $sequence_lower) !== false) {
				return true;
			}
		}

		return false;
	}

	private function reset_stats() {
		$this->stats = [
			'kept_entries' => 0,
			'kept_ai_notes' => 0,
			'removed_empty' => 0,
			'removed_by_sequence' => 0,
			'removed_mundane_noise' => 0,
			'removed_meta_trivial' => 0,
			'removed_aggressive_short' => 0,
			'removed_exact_duplicate' => 0,
			'removed_near_duplicate' => 0,
		];
	}

	private function increment_stat($key) {
		if (!isset($this->stats[$key])) {
			$this->stats[$key] = 0;
		}

		$this->stats[$key]++;
	}
}