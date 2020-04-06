<?php
namespace dd32\xPostMetaCache;

add_action( 'init', function() {
	// Let the fun begin.
	xPostMetaCache::instance()
		->setting( 'post_limiter', false )

		->register_field( '_title_id', 'bigint(20) unsigned', true )
		->register_field( '_wp_page_template' )
		->register_field( 'test', 'tinyint(3)' )
	
		->create_tables()

		->fill_table()
		;
});