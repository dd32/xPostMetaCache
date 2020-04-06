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
	}
}

include __DIR__ . '/test.php';