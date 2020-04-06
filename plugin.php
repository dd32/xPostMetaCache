<?php
namespace dd32\xPostMetaCache;
/**
 * Plugin Name: xPostMetaCache
 * Author: Dion Hulse
 */

xPostMetaCache::instance();
class xPostMetaCache {
	protected $settings = [
	];
	public function setting( $name, $value = null ) {
		if ( is_null( $value ) ) {
			return $this->settings[ $name ] ?? false;
		}

		$this->settings[ $name ] = $value;
		return $this;
	}

	static $instance = null;

	public static function instance() {
		$class = __CLASS__;
		return self::$instance ?? ( self::$instance = new $class );
	}

	protected function __construct() {
		global $wpdb;
		$wpdb->xpostmetacache = $wpdb->prefix . 'xpostmetacache';

		add_filter( 'get_meta_sql', [ $this, 'get_meta_sql'], 100, 6 );
	}

	protected $fields = [];
	public function register_field( $field, $type = 'varchar(255)', $unique = false ) {
		$this->fields[ $field ] = [
			'type'   => $type,
			'unique' => $unique,
		];

		return $this;
	}

	public function get_meta_sql( $sql, $meta_queries, $type, $primary_table, $primary_id_column, $context ) {
		global $wpdb;

		// POC, we're only going to kick in for a very simple lookup.
		// Yes, yes, this is not secure and it's horrible.

		// Find the fields we're matching on.
		preg_match_all( "#\s((\S+)\.meta_key = '(.+?)' AND \S+.meta_value = '(.+)')#", $sql['where'], $matches );
		$replace = $matches[1];
		$tables  = $matches[2];
		$fields  = $matches[3];
		$values  = $matches[4];

		$expidited_keys = array_keys( $this->fields );

		foreach ( $fields as $i => $f ) {
			if ( isset( $this->fields[ $f ] ) ) {
				// Lets do a priority-one delivery on that one.
				$table_alias = $tables[ $i ];
				$sql['join'] = str_replace(
					"INNER JOIN $table_alias ON ( wp_xviews_posts.ID = $table_alias.post_id )",
					"INNER JOIN $wpdb->xpostmetacache ON ( wp_xviews_posts.ID = $wpdb->xpostmetacache.post_id )",
					$sql['join']
				);
				$sql['where'] = str_replace(
					$replace[ $i ],
					"`$wpdb->xpostmetacache`.`$f` = '" . $values[$i] . "'",
					$sql['where']
				);
			}
		}

		return $sql;

	}


	public function create_tables() {
		global $wpdb;
		if ( ! $this->fields ) {
			return;
		}

		$fields = [];
		$keys   = [];

		foreach ( $this->fields as $f => $details ) {
			$fields[] = "`{$f}` {$details['type']}";
			$keys[]   = ($details['unique'] ? 'UNIQUE ' : '' ) . "KEY `{$f}` (`{$f}`)";
		}

		$fields = implode( ",\n", $fields );
		$keys   = implode( ",\n", $keys );
		$create_sql = "CREATE TABLE `{$wpdb->xpostmetacache}` (
			`post_id` bigint(20) unsigned NOT NULL,
			{$fields},
			PRIMARY KEY (`post_id`),
			{$keys}
		  )";

		include_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $create_sql );

		return $this;
	}

	function fill_table() {
		global $wpdb;

		foreach ( $this->fields as $f => $data ) {
			$sql = "INSERT INTO `{$wpdb->xpostmetacache}` (`post_id`, `$f`)
				SELECT `post_id`, `meta_value` FROM `{$wpdb->postmeta}`
				WHERE `meta_key` = '$f'
				ON DUPLICATE KEY UPDATE `$f` = VALUE(`$f`)
				";
			$wpdb->query( $sql );
		}
	}
}

include __DIR__ . '/test.php';