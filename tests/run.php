<?php
/**
 * Test runner.
 *
 * Usage, from the plugin root:
 * `php tests/run.php` runs everything,
 * `php tests/run.php cache` runs only the files whose name contains "cache".
 *
 * @package Taxonomy_Discounts_WooCommerce
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

require_once __DIR__ . '/bootstrap.php';

$tdw_test_filter = isset( $argv[1] ) ? $argv[1] : '';
$tdw_test_files  = glob( __DIR__ . '/test-*.php' );
sort( $tdw_test_files );

echo "\n\033[1mTaxonomy/Term and Role-based Discounts for WooCommerce, tests\033[0m\n";

foreach ( $tdw_test_files as $tdw_test_file ) {
	if ( '' !== $tdw_test_filter && false === strpos( basename( $tdw_test_file ), $tdw_test_filter ) ) {
		continue;
	}
	echo "\n\033[1m" . basename( $tdw_test_file, '.php' ) . "\033[0m";
	try {
		require $tdw_test_file;
	} catch ( Throwable $e ) {
		++$GLOBALS['tdw_test_results']['failed'];
		echo "\n    \033[31mFAIL\033[0m the test file could not run\n         " . str_replace( "\n", "\n         ", $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' ) . "\n";
	}
}

$tdw_test_passed = $GLOBALS['tdw_test_results']['passed'];
$tdw_test_failed = $GLOBALS['tdw_test_results']['failed'];

echo "\n";
if ( $tdw_test_failed > 0 ) {
	echo "\033[31m" . $tdw_test_failed . ' failed' . "\033[0m, " . $tdw_test_passed . " passed\n\n";
	exit( 1 );
}
echo "\033[32mAll " . $tdw_test_passed . " passed\033[0m\n\n";
exit( 0 );
