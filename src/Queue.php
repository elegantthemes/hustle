<?php

namespace ET\Hustle;

// External Dependencies
use Redis, RedisCluster;


class Queue extends Base {

	public string $name;

	public function __construct( string $name = 'default' ) {
		$this->name    = $name;
	}

	protected function __completed(): string {
		return $this->__key( 'completed' );
	}

	protected function __failed(): string {
		return $this->__key( 'failed' );
	}

	protected function __pending(): string {
		return $this->__key( 'pending' );
	}

	protected function __running(): string {
		return $this->__key( 'running' );
	}

	protected function __key( string ...$args ): string {
		return self::_key( 'queues', $this->name, ...$args );
	}

	public function JOB( string $job_id ): Job {
		return Job::instance( $this->name, $job_id );
	}

	public function put( callable $run, array $data ): string {
		$details = [
			'data'      => $data,
			'callbacks' => [
				'run'   => $run,
				'done'  => __CLASS__ . '::done',
				'error' => __CLASS__ . '::error',
			],
		];

		$job = Job::instance( $this->name, null, $details );

		self::$_DB->lpush( $this->__pending(), $job->id );

		return $job->id;
	}

	public function take(): Job {
		$job_id = self::$_DB->brpoplpush( $this->__pending(), $this->__running(), 600 );
		$job    = Job::instance( $this->name, $job_id );

		$job->status = 'running';

		self::$_DB->set( $this->__key( 'jobs', $job_id ), json_encode( $job ) );

		return $job;
	}

}
