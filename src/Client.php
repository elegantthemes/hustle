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

	public function __construct( string $name = 'default', string|array $redis_nodes = [] ) {
		$this->name = $name;

		if ( ! $redis_nodes ) {
			throw new \InvalidArgumentException( '$redis_nodes cannot be empty' );
		}

		if ( is_string( $redis_nodes ) ) {
			// String — standalone Redis (single node, not cluster mode).
			$parts = explode( ':', $redis_nodes, 2 );
			$host  = $parts[0];
			$port  = isset( $parts[1] ) ? (int) $parts[1] : 6379;

			self::$_DB = new Redis();
			self::$_DB->connect( $host, $port, 5 );

		} else {
			// Array — Redis Cluster. Cluster topology is discovered automatically from the provided seed nodes.
			ini_set( 'redis.clusters.cache_slots', 1 );

			self::$_DB = new RedisCluster( null, $redis_nodes, 5, 900, true );

			self::$_DB->setOption( RedisCluster::OPT_SLAVE_FAILOVER, RedisCluster::FAILOVER_DISTRIBUTE );
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
