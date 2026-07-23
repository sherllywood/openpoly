<?php
/**
 * Database schema definition for OpenPoly.
 *
 * Contains the CREATE TABLE statements for every op_* table.
 * SQL is written in the exact format dbDelta() expects:
 *   - two spaces between column name and type
 *   - NOT NULL, DEFAULT, COMMENT on a single line
 *   - KEY and UNIQUE KEY on their own line
 *   - PRIMARY KEY on its own line
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the current schema version and the CREATE TABLE SQL.
 *
 * @since 0.5.0-dev
 */
final class Schema {

	/**
	 * Current schema version. Bump on any DDL change.
	 *
	 * History:
	 *   1 = initial (M-03): op_languages, op_translations, op_translation_status.
	 */
	public const VERSION = 1;

	/**
	 * Return the CREATE TABLE statements keyed by table name (no prefix).
	 *
	 * Each statement is run via dbDelta() in Database::install(). The
	 * placeholder {prefix} is replaced with $wpdb->prefix at runtime.
	 *
	 * @return array<string, string> Map of unprefixed table name to SQL.
	 */
	public static function tables(): array {
		return array(
			'op_languages'          => self::sql_languages(),
			'op_translations'       => self::sql_translations(),
			'op_translation_status' => self::sql_translation_status(),
		);
	}

	/**
	 * Return the dbDelta-compatible CREATE TABLE for op_languages.
	 *
	 * @return string
	 */
	private static function sql_languages(): string {
		return <<<'SQL'
CREATE TABLE {prefix}op_languages (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  code varchar(20) NOT NULL COMMENT 'Language code, e.g. zh-hans / pt-br, lowercase.',
  english_name varchar(128) NOT NULL,
  native_name varchar(128) NOT NULL COMMENT 'Native name, e.g. 简体中文.',
  locale varchar(20) NOT NULL DEFAULT '' COMMENT 'WordPress locale, e.g. zh_CN.',
  hreflang varchar(20) NOT NULL DEFAULT '' COMMENT 'hreflang output value, defaults to code.',
  default_locale varchar(20) NOT NULL DEFAULT '',
  text_direction tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = LTR, 1 = RTL.',
  flag varchar(128) NOT NULL DEFAULT '' COMMENT 'Flag filename or emoji.',
  is_active tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether the language is enabled for visitors.',
  is_default tinyint(1) NOT NULL DEFAULT 0,
  is_hidden tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Hidden language: content exists but not visible to visitors.',
  sort_order int(11) NOT NULL DEFAULT 0 COMMENT 'Position in the language switcher.',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY code (code),
  KEY is_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
	}

	/**
	 * Return the dbDelta-compatible CREATE TABLE for op_translations.
	 *
	 * The Translation Group core table: maps each (element_type, element_id)
	 * to a trid (Translation Group ID) and a language_code.
	 *
	 * @return string
	 */
	private static function sql_translations(): string {
		return <<<'SQL'
CREATE TABLE {prefix}op_translations (
  translation_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  element_type varchar(60) NOT NULL COMMENT 'e.g. post_post, tax_category, post_attachment.',
  element_id bigint(20) unsigned NOT NULL,
  trid bigint(20) unsigned NOT NULL COMMENT 'Translation Group ID, shared by all language variants of one piece of content.',
  language_code varchar(20) NOT NULL,
  source_language_code varchar(20) DEFAULT NULL COMMENT 'NULL marks the original-language element in the group.',
  PRIMARY KEY  (translation_id),
  UNIQUE KEY element_type_id_lang (element_type, element_id, language_code),
  KEY trid_lang (trid, language_code),
  KEY element_id (element_id),
  KEY trid (trid),
  KEY language_code (language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
	}

	/**
	 * Return the dbDelta-compatible CREATE TABLE for op_translation_status.
	 *
	 * Per-(trid, language) translation state: 0 not translated, 1 in
	 * progress, 2 translated, 3 needs update, 4 duplicate, 10 awaiting
	 * review. md5 stores the source fingerprint for needs-update
	 * detection (FR-CORE-006).
	 *
	 * @return string
	 */
	private static function sql_translation_status(): string {
		return <<<'SQL'
CREATE TABLE {prefix}op_translation_status (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  trid bigint(20) unsigned NOT NULL,
  language_code varchar(20) NOT NULL,
  status tinyint(4) NOT NULL DEFAULT 0 COMMENT '0 not translated, 1 in progress, 2 translated, 3 needs update, 4 duplicate, 10 awaiting review.',
  translation_service varchar(60) NOT NULL DEFAULT 'local',
  translator_id bigint(20) unsigned NOT NULL DEFAULT 0,
  md5 char(32) NOT NULL DEFAULT '' COMMENT 'Source content fingerprint, used to detect source changes.',
  needs_update tinyint(1) NOT NULL DEFAULT 0,
  job_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY trid_lang (trid, language_code),
  KEY status (status),
  KEY translator_id (translator_id),
  KEY job_id (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
	}
}
