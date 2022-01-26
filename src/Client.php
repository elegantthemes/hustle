<?php

namespace ET\Hustle;

// External Dependencies
use Redis, RedisCluster;


class Client extends Base {

	/**
	 * @var Queue[]
	 */
	protected array $_queues;

	public string $name;

	public function __construct( string $name = 'default', array $redis_nodes = [] ) {
		$this->name = $name;

		if ( ! $redis_nodes ) {
			throw new \InvalidArgumentException( '$redis_nodes cannot be empty' );
		}

		if ( count( $redis_nodes ) > 1 || is_string( reset( $redis_nodes ) ) ) {
			// Redis Cluster
			ini_set( 'redis.clusters.cache_slots', 1 );

			self::$_DB = new RedisCluster( null, $redis_nodes, 1.5, 900 );

			self::$_DB->setOption( RedisCluster::OPT_SLAVE_FAILOVER, RedisCluster::FAILOVER_DISTRIBUTE );

		} else {
			// Redis Standalone
			throw new \ErrorException( 'Support for using redis in standalone mode is not implemented.' );
		}
	}

	/**
	 * @return Redis|RedisCluster
	 */
	public function DB(): object {
		return self::$_DB;
	}

	public function QUEUE( string $name = 'default' ): Queue {
		$this->_queues[ $name ] ??= new Queue( $name );

		return $this->_queues[ $name ];
	}
}
