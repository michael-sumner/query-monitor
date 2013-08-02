<?php
/*

© 2013 John Blackbourn

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

*/

class QM_Util {

	protected static $ignore_class = array(
		'wpdb'           => true,
		'QueryMonitor'   => true,
		'QueryMonitorDB' => true,
		'ExtQuery'       => true,
		'W3_Db'          => true,
		'Debug_Bar_PHP'  => true,
	);
	protected static $ignore_method = array();
	protected static $ignore_func = array(
		'include_once'         => true,
		'require_once'         => true,
		'include'              => true,
		'require'              => true,
		'call_user_func_array' => true,
		'call_user_func'       => true,
		'trigger_error'        => true,
		'_doing_it_wrong'      => true,
		'_deprecated_argument' => true,
		'_deprecated_file'     => true,
		'_deprecated_function' => true,
	);
	protected static $show_args = array(
		'do_action'               => 1,
		'apply_filters'           => 1,
		'do_action_ref_array'     => 1,
		'apply_filters_ref_array' => 1,
		'get_template_part'       => 2,
		'section_template'        => 2,
		'load_template'           => 'dir',
		'get_header'              => 1,
		'get_sidebar'             => 1,
		'get_footer'              => 1,
	);
	protected static $filtered        = false;
	protected static $file_components = array();
	protected static $file_dirs       = array();

	private function __construct() {}

	public static function filter_trace( array $trace ) {

		if ( !self::$filtered and function_exists( 'did_action' ) and did_action( 'plugins_loaded' ) ) {

			# Only run apply_filters on these once
			self::$ignore_class  = apply_filters( 'query_monitor_ignore_class',  self::$ignore_class );
			self::$ignore_method = apply_filters( 'query_monitor_ignore_method', self::$ignore_method );
			self::$ignore_func   = apply_filters( 'query_monitor_ignore_func',   self::$ignore_func );
			self::$show_args     = apply_filters( 'query_monitor_show_args',     self::$show_args );
			self::$filtered = true;

		}

		if ( isset( $trace['class'] ) ) {

			if ( isset( self::$ignore_class[$trace['class']] ) )
				return null;
			else if ( isset( self::$ignore_method[$trace['class']][$trace['function']] ) )
				return null;
			else if ( 0 === strpos( $trace['class'], 'QM_' ) )
				return null;
			else
				return $trace['class'] . $trace['type'] . $trace['function'] . '()';

		} else {

			if ( isset( self::$ignore_func[$trace['function']] ) ) {

				return null;

			} else if ( isset( self::$show_args[$trace['function']] ) ) {

				$show = self::$show_args[$trace['function']];
				if ( 'dir' === $show ) {
					if ( isset( $trace['args'][0] ) ) {
						$arg = self::standard_dir( $trace['args'][0], '&hellip;/' );
						return $trace['function'] . "('{$arg}')";
					}
				} else {
					$args = array();
					for ( $i = 0; $i < $show; $i++ ) {
						if ( isset( $trace['args'][$i] ) )
							$args[] = sprintf( "'%s'", $trace['args'][$i] );
					}
					return $trace['function'] . '(' . implode( ',', $args ) . ')';
				}

			}

			return $trace['function'] . '()';

		}

	}

	public static function backtrace() {
		$trace = debug_backtrace( false );
		$trace = array_map( 'QM_Util::filter_trace', $trace );
		$trace = array_values( array_filter( $trace ) );
		return $trace;
	}

	public static function timer_stop_float() {
		global $timestart;
		return microtime( true ) - $timestart;
	}

	public static function sort( $a, $b ) {
		if ( $a['ltime'] == $b['ltime'] )
			return 0;
		else
			return ( $a['ltime'] > $b['ltime'] ) ? -1 : 1;
	}

	public static function convert_hr_to_bytes( $size ) {

		# Annoyingly, wp_convert_hr_to_bytes() is defined in a file that's only
		# loaded in the admin area, so we'll use our own version.
		# See also http://core.trac.wordpress.org/ticket/17725

		$bytes = (float) $size;

		if ( $bytes ) {
			$last = strtolower( substr( $size, -1 ) );
			$pos = strpos( ' kmg', $last, 1);
			if ( $pos )
				$bytes *= pow( 1024, $pos );
			$bytes = round( $bytes );
		}

		return $bytes;

	}

	public static function standard_dir( $dir, $abspath_replace = null ) {

		$dir = str_replace( '\\', '/', $dir );
		$dir = preg_replace( '|/+|', '/', $dir );

		if ( is_string( $abspath_replace ) )
			$dir = str_replace( self::standard_dir( ABSPATH ), $abspath_replace, $dir );

		return $dir;

	}

	public static function get_file_component( $file ) {

		if ( isset( self::$file_components[$file] ) )
			return self::$file_components[$file];

		if ( empty( self::$file_dirs ) ) {
			self::$file_dirs['plugin']     = self::standard_dir( WP_PLUGIN_DIR );
			self::$file_dirs['muplugin']   = self::standard_dir( WPMU_PLUGIN_DIR );
			self::$file_dirs['stylesheet'] = self::standard_dir( get_stylesheet_directory() );
			self::$file_dirs['template']   = self::standard_dir( get_template_directory() );
			self::$file_dirs['other']      = self::standard_dir( WP_CONTENT_DIR );
			self::$file_dirs['core']       = self::standard_dir( ABSPATH );
		}

		foreach ( self::$file_dirs as $component => $dir ) {
			if ( 0 === strpos( $file, $dir ) )
				break;
		}

		return self::$file_components[$file] = $component;

	}

	public static function is_ajax() {
		if ( defined( 'DOING_AJAX' ) and DOING_AJAX )
			return true;
		if ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) and 'xmlhttprequest' == strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) )
			return true;
		return false;
	}

	public static function wpv() {
		return 'qm-wp-' . ( floatval( $GLOBALS['wp_version'] ) * 10 );
	}

	public static function get_admins() {
		if ( is_multisite() )
			return false;
		else
			return get_role( 'administrator' );
	}

	public static function file( $file ) {
		# Symlink-safe version of plugin_basename() for passing to register_(de)?activation_hook()
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}

	public static function format_sql( $sql ) {

		$sql = str_replace( array( "\r\n", "\r", "\n", "\t" ), ' ', $sql );
		$sql = esc_html( $sql );
		$sql = trim( $sql );

		foreach( array(
			'ALTER', 'AND', 'COMMIT', 'CREATE', 'DESCRIBE', 'DELETE', 'DROP', 'ELSE', 'END', 'FROM', 'GROUP', 'HAVING', 'INNER', 'INSERT', 'LIMIT',
			'ON', 'OR', 'ORDER', 'ROLLBACK', 'SELECT', 'SET', 'SHOW','START', 'THEN', 'UPDATE', 'VALUES', 'WHEN', 'WHERE'
		) as $cmd )
			$sql = trim( str_replace( " $cmd ", "<br/>$cmd ", $sql ) );

		return $sql;

	}

}
