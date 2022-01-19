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

		return "hustle:{$key}";
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
