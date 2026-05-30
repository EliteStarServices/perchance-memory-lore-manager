<?php

if (!defined('ABSPATH')) {
	exit;
}

class PMM_Utils {

	public static function normalize_unicode_text($text) {
		$map = [
			"\u{2018}" => "'",
			"\u{2019}" => "'",
			"\u{201C}" => '"',
			"\u{201D}" => '"',
			"\u{2013}" => '-',
			"\u{2014}" => '-',
			"\u{00A0}" => ' ',
		];

		return strtr($text, $map);
	}

	public static function clean_whitespace($text) {
		$text = str_replace(["\r\n", "\r"], "\n", $text);
		$text = preg_replace('/[ \t]+/u', ' ', $text);
		$text = preg_replace('/ *\n */u', "\n", $text);
		return trim($text);
	}

	public static function normalize_bullet($text) {
		$text = self::normalize_unicode_text($text);
		$text = trim($text);
		$text = preg_replace('/\s+/u', ' ', $text);
		return $text;
	}

	public static function fingerprint($text) {
		$text = mb_strtolower(self::normalize_bullet($text));
		$text = preg_replace('/["\'`*_]/u', '', $text);
		$text = preg_replace('/[^a-z0-9\s]/u', ' ', $text);
		$text = preg_replace('/\s+/u', ' ', $text);
		return trim($text);
	}

	public static function name_fingerprint($text) {
		$fp = self::fingerprint($text);
		$fp = preg_replace('/\b(the|a|an|inc|llc|ltd|corp|corporation|co|group|systems?)\b/u', ' ', $fp);
		$fp = preg_replace('/\s+/u', ' ', (string) $fp);
		return trim((string) $fp);
	}

	public static function contains_name_score($entry, $entity_name) {
		$entry_fp = self::fingerprint($entry);
		$name_fp = self::name_fingerprint($entity_name);

		if ($entry_fp === '' || $name_fp === '') {
			return 0.0;
		}

		if (strpos($entry_fp, $name_fp) !== false) {
			return 1.0;
		}

		$entry_set = self::word_set($entry_fp);
		$name_set = self::word_set($name_fp);
		if (!$entry_set || !$name_set) {
			return 0.0;
		}

		$intersection = array_intersect_key($entry_set, $name_set);
		$name_coverage = count($intersection) / max(count($name_set), 1);
		$entry_coverage = count($intersection) / max(count($entry_set), 1);

		return max($name_coverage, $entry_coverage * 0.85);
	}

	public static function word_set($text) {
		$fp = self::fingerprint($text);
		if ($fp === '') {
			return [];
		}

		$parts = explode(' ', $fp);
		$set = [];

		foreach ($parts as $part) {
			if ($part !== '') {
				$set[$part] = true;
			}
		}

		return $set;
	}

	public static function jaccard_similarity($a, $b) {
		$setA = self::word_set($a);
		$setB = self::word_set($b);

		if (!$setA && !$setB) {
			return 1.0;
		}

		$intersection = array_intersect_key($setA, $setB);
		$union = $setA + $setB;

		return count($union) ? count($intersection) / count($union) : 0.0;
	}

	public static function is_subset_duplicate($a, $b) {
		$setA = self::word_set($a);
		$setB = self::word_set($b);

		if (!$setA || !$setB) {
			return false;
		}

		$intersection = array_intersect_key($setA, $setB);
		$aInB = count($intersection) / max(count($setA), 1);
		$bInA = count($intersection) / max(count($setB), 1);

		return ($aInB > 0.9 || $bInA > 0.9);
	}

	public static function score_bullet($text) {
		$text = self::normalize_bullet($text);
		$score = min(strlen($text), 300);

		preg_match_all('/\b\d+(?:\.\d+)?%?\b/u', $text, $nums);
		preg_match_all('/\b[A-Z][a-z]+\b/u', $text, $caps);

		$score += count($nums[0]) * 8;
		$score += count($caps[0]) * 3;
		$score += strpos($text, '"') !== false ? 5 : 0;
		$score += strpos($text, ':') !== false ? 5 : 0;

		return $score;
	}

	public static function count_entities($data) {
		$count = 0;

		foreach ($data as $section => $content) {
			if (in_array($section, ['Relationships', 'NSFW', 'Notes'], true)) {
				continue;
			}

			foreach ($content as $key => $value) {
				if (strpos((string) $key, '__') === 0) {
					continue;
				}
				$count++;
			}
		}

		return $count;
	}

	public static function count_bullets($data) {
		$count = 0;

		foreach ($data as $content) {
			foreach ($content as $items) {
				if (is_array($items)) {
					$count += count($items);
				}
			}
		}

		return $count;
	}
}