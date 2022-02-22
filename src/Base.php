<?php

namespace ET\Hustle;

use Redis, RedisCluster;


abstract class Base {

	/**
	 * @var Redis|RedisCluster
	 */
	protected static object $_DB;

	protected static function _key( string ...$args ): string {
		$key = implode( ':', $args );

		return "{hustle}:{$key}";
	}

	protected static function _dbTry( string $method, ...$args ) {
		$tried     = 0;
		$max_tries = 5;
		$error     = null;

		while ( $tried < $max_tries ) {
			try {
				et_debug( ["Trying database call - {$tried}" => ['method' => $method, 'args' => $args]] );

				return self::$_DB->$method( ...$args );

			} catch ( \Exception $err ) {
				if ( $tried = ( $max_tries - 1 ) ) {
					$error = $err;
				}
			}

			$tried++;
		}

		et_error( ["Max tries reached for a database call!" => ['method' => $method, 'args' => $args, 'error' => $error]] );

		throw $error;
	}

	protected static function _uuid4(): string {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff )
		);
	}
}
