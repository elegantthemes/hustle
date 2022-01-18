<?php

namespace ET\Hustle;

// External Dependencies
use Redis, RedisCluster;


class Queue {

	protected Client $_client;

	public string $name;

	public function __construct( Client $client, string $name = 'default' ) {
		$this->_client = $client;
		$this->name    = $name;
	}

	protected function __completed(): string {
		return $this->_key( 'completed' );
	}

	protected function __failed(): string {
		return $this->_key( 'failed' );
	}

	protected function __pending(): string {
		return $this->_key( 'pending' );
	}

	protected function __running(): string {
		return $this->_key( 'running' );
	}

	protected function _key( string ...$args ): string {
		$key = implode( ':', $args );

		return "hustle:queue:{$this->name}:{$key}";
	}

	protected function _uuid4(): string {
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

	public function job( string $jid ): array {
		$key = $this->_key( 'job', $jid );
	}

	public function put( callable $job, array $data ): string {
		$jid = $this->_uuid4();
		$key = $this->_key( 'job', $jid );

		$data = [
			'jid'   => $jid,
			'class' => $job,
			'data'  => $data,
		];

		$this->_client->DB()->set( $key, json_encode( $data ) );
		$this->_client->DB()->rpush( $this->__pending(), $jid );

		return $jid;
	}

	public function take(): string {
		
	}

}
