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
	}

	protected $fields = [];
	public function register_field( $field, $type = 'varchar(255)', $unique = false ) {
		$this->fields[ $field ] = [
			'type'   => $type,
			'unique' => $unique,
		];

		return $this;
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