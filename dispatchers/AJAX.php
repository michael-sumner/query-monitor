<?php
/*
Copyright 2009-2015 John Blackbourn

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

*/

class QM_Dispatcher_AJAX extends QM_Dispatcher {

	public $id = 'ajax';

	public function __construct( QM_Plugin $qm ) {
		parent::__construct( $qm );

		add_action( 'shutdown', array( $this, 'dispatch' ), 0 );

	}

	public function init() {

		if ( ! $this->user_can_view() ) {
			return;
		}

		if ( QM_Util::is_ajax() ) {
			ob_start();
		}

	}

	public function dispatch() {

		if ( ! $this->should_dispatch() ) {
			return;
		}

		$out = $this->get_output( 'headers' );

		foreach ( $out['output'] as $id => $output ) {
			foreach ( $output as $key => $value ) {
				header( sprintf( 'X-QM-%s: %s', $key, $value ) );
			}
		}

	}

	protected function before_output() {

		require_once $this->qm->plugin_path( 'output/Headers.php' );

		foreach ( glob( $this->qm->plugin_path( 'output/headers/*.php' ) ) as $file ) {
			require_once $file;
		}
	}

	protected function after_output() {

		# flush once, because we're nice
		if ( ob_get_length() ) {
			ob_flush();
		}

	}

	public function is_active() {

		if ( ! QM_Util::is_ajax() ) {
			return false;
		}

		if ( ! $this->user_can_view() ) {
			return false;
		}

		# If the headers have already been sent then we can't do anything about it
		if ( headers_sent() ) {
			return false;
		}

		return true;

	}

}

function register_qm_dispatcher_ajax( array $dispatchers, QM_Plugin $qm ) {
	$dispatchers['ajax'] = new QM_Dispatcher_AJAX( $qm );
	return $dispatchers;
}

add_filter( 'qm/dispatchers', 'register_qm_dispatcher_ajax', 10, 2 );