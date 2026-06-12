<?php

namespace ET\Hustle;

// External Dependencies
use Redis, RedisCluster;


class Queue extends Base {

	protected const BLOCKING_TAKE_TIMEOUT = 30;
	protected const STALE_RUNNING_AFTER   = 150;

	protected static array $_INSTANCES = [];

	public string $name;

	public function __construct( string $name = 'default' ) {
		$this->name = $name;

		self::$_INSTANCES[ $name ] = $this;
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

	public static function done( Job $job ): void {
		$completed_at = time();
		$queue        = self::$_INSTANCES[ $job->queue ];

		$job->status = 'completed';

		self::_dbTry( 'set', $queue->__key( 'jobs', $job->id ), json_encode( $job ) );
		self::_dbTry( 'lrem', $queue->__running(), $job->id, 0 );
		self::_dbTry( 'lpush', $queue->__completed(), $job->id );

		// Set expiration for job data after which it will automatically be removed by redis
		self::_dbTry( 'expire', $queue->__key( 'jobs', $job->id ), 60 * 60 * 12 );

		// Don't allow completed list to grow beyond 200 entries.
		self::_dbTry( 'ltrim', $queue->__completed(), 0, 199 );

		// Store the total time taken to complete the job
		self::_dbTry('set', $queue->__key('job-duration'), $completed_at - $job->created_at);
	}

	public static function error( Job $job ): void {
		$queue = self::$_INSTANCES[ $job->queue ];

		$job->status = 'failed';

		self::_dbTry( 'set', $queue->__key( 'jobs', $job->id ), json_encode( $job ) );
		self::_dbTry( 'lrem', $queue->__running(), $job->id, 0 );
		self::_dbTry( 'lpush', $queue->__failed(), $job->id );

		// Set expiration for job data after which it will automatically be removed by redis
		self::_dbTry( 'expire', $queue->__key( 'jobs', $job->id ), 60 * 60 * 12 );

		// Don't allow failed list to grow beyond 200 entries.
		self::_dbTry( 'ltrim', $queue->__failed(), 0, 199 );
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

		self::_dbTry( 'lpush', $this->__pending(), $job->id );

		return $job->id;
	}

	public function status(): array {
		return [
			'running'      => self::_dbTry( 'llen', $this->__running() ),
			'pending'      => self::_dbTry( 'llen', $this->__pending() ),
			'completed'    => self::_dbTry( 'llen', $this->__completed() ),
			'failed'       => self::_dbTry( 'llen', $this->__failed() ),
			'job-duration' => (int) self::_dbTry('get', $this->__key('job-duration')),
		];
	}

	public function failStaleRunning(): int {
		$failed  = 0;
		$job_ids = self::_dbTry( 'lrange', $this->__running(), 0, -1 );
		$now     = time();

		if ( ! is_array( $job_ids ) ) {
			return $failed;
		}

		foreach ( array_unique( $job_ids ) as $job_id ) {
			$json    = self::_dbTry( 'get', $this->__key( 'jobs', $job_id ) );
			$details = $json ? json_decode( $json, true ) : null;

			if ( ! is_array( $details ) ) {
				self::_dbTry( 'lrem', $this->__running(), $job_id, 0 );
				continue;
			}

			$job = new Job( $job_id, $details );

			if ( ( $now - $job->started_at ) < self::STALE_RUNNING_AFTER ) {
				continue;
			}

			self::error( $job );

			$failed++;
		}

		return $failed;
	}

	public function take(): Job {
		// Prefer BLMOVE when supported, but fall back to BRPOPLPUSH for older phpredis builds.
		if ( method_exists( self::$_DB, 'blmove' ) ) {
			$job_id = self::$_DB->blmove( $this->__pending(), $this->__running(), 'RIGHT', 'LEFT', self::BLOCKING_TAKE_TIMEOUT );
		} else {
			$job_id = self::$_DB->brpoplpush( $this->__pending(), $this->__running(), self::BLOCKING_TAKE_TIMEOUT );
		}

		if ( ! $job_id ) {
			throw new \ErrorException( 'Timedout waiting for more work.' );
		}

		$job = Job::instance( $this->name, $job_id );

		$job->status     = 'running';
		$job->started_at = time();

		self::_dbTry( 'set', $this->__key( 'jobs', $job_id ), json_encode( $job ) );

		return $job;
	}

}
