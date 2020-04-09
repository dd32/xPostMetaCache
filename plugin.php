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

		add_filter( 'meta_query_find_compatible_table_alias', [ $this, 'meta_query_find_compatible_table_alias'], 10, 4 );
	}
	
	protected $fields = [];
	public function register_field( $field, $type = 'varchar(255)', $unique = false ) {
		$this->fields[ $field ] = [
			'type'   => $type,
			'unique' => $unique,
		];

		return $this;
	}

	protected $useless_joins = [];
	function meta_query_find_compatible_table_alias( $alias, $clause, $parent_query, $meta_query ) {
		global $wpdb;

		if ( isset( $clause['key'] ) && isset( $this->fields[ $clause['key'] ] ) ) {
			if ( $alias ) {
				$this->useless_joins[ $alias ] = true;
			}

			add_filter( 'posts_orderby', [ $this, 'posts_orderby' ] );

			return $wpdb->xpostmetacache . '.' . $clause['key'];
		}

		return $alias;
	}

	public function get_meta_sql( $sql, $meta_queries, $type, $primary_table, $primary_id_column, $context ) {
		global $wpdb;

		// The table aliases should have been set above.
		$sql['where'] = preg_replace(
			"/{$wpdb->xpostmetacache}\.[^.]+\.meta_key = '(.+?)' AND {$wpdb->xpostmetacache}\.[^.]+\.meta_value(.+)/",
			"{$wpdb->xpostmetacache}.\\1 \\2",
			$sql['where']
		);

		// NOT EXISTS
		$sql['where'] = preg_replace(
			"/{$wpdb->xpostmetacache}\.([^.]+)\.post_id (IS NULL)/",
			"{$wpdb->xpostmetacache}.\\1 \\2",
			$sql['where']
		);

		// EXISTS
		$sql['where'] = preg_replace(
			"/{$wpdb->xpostmetacache}\.([^.]+)\.meta_key = '[^']+'/",
			"{$wpdb->xpostmetacache}.\\1 IS NOT NULL",
			$sql['where']
		);

		// BETWEEN
		$sql['where'] = preg_replace(
			"/{$wpdb->xpostmetacache}\.([^.]+)\.meta_key ((NOT)? BETWEEN)/",
			"{$wpdb->xpostmetacache}.\\1 \\2",
			$sql['where']
		);

		// Fix any mangled CASTs
		$sql['where'] = preg_replace(
			"/CAST\({$wpdb->xpostmetacache}.([^.]+)\.meta_value/",
			"CAST({$wpdb->xpostmetacache}.\\1",
			$sql['where']
		);

		if ( false !== strpos( $sql['where'], $wpdb->xpostmetacache ) ) {
			$sql['join'] .= " LEFT JOIN $wpdb->xpostmetacache ON ( {$wpdb->posts}.ID = {$wpdb->xpostmetacache}.post_id  )";
		}

		return $sql;

	}

	function posts_orderby( $orderby ) {
		global $wpdb;

		// Handle an orderby on an affected field.
		return preg_replace(
			"/{$wpdb->xpostmetacache}\.([^.]+)\.meta_value/",
			"{$wpdb->xpostmetacache}.\\1",
			$orderby
		);
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