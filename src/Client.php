<?php

namespace ET\Hustle;

// External Dependencies
use Redis, RedisCluster;
use function et_;


class Client {

	/**
	 * @var Redis|RedisCluster
	 */
	protected object $_db;

	/**
	 * @var Queue[]
	 */
	protected array $_queues;

	public string $name;

	protected function __construct( string $name = 'default', array $redis_nodes = [] ) {
		$this->name = $name;

		if ( ! $redis_nodes ) {
			throw new \InvalidArgumentException( '$redis_nodes cannot be empty' );
		}

		if ( count( $redis_nodes ) > 1 || is_string( reset( $redis_nodes ) ) ) {
			// Redis Cluster
			$this->_db = new RedisCluster( null, $redis_nodes );
		} else {
			// Redis Standalone
			throw new \ErrorException( 'Support for using redis in standalone mode is not implemented.' );
		}
	}

	/**
	 * @return Redis|RedisCluster
	 */
	public function DB(): object {
		return $this->_db;
	}

	public function QUEUE( string $name = 'default' ): Queue {
		$this->_queues[ $name ] ??= new Queue( $name, $this );

		return $this->_queues[ $name ];
	}
}
