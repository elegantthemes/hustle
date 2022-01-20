<?php

namespace ET\Hustle;

// External Dependencies
use Redis, RedisCluster;
use function et_;


class Worker extends Base {

	protected static string $_COUNT;

	protected array $_jobs = [];
	protected array $_pids = [];
	protected Queue $_queue;

	public string $name;

	protected function __construct( Queue $queue, string $name = '' ) {
		$this->name   = $name;
		$this->_queue = $queue;
	}

	protected function _doJob( $job ) {
		// Spawn child process to perform job and then save its pid.
		$pid = pcntl_fork();

		switch ( $pid ) {
			case -1:
				throw new \Exception( 'Failed to fork child process!' );
				break;

			case 0:
				// Child Process
				try {
					call_user_func( $job->callbacks['run'], $job->data );
				} catch ( \Exception $err ) {
					et_error( $err->getMessage() );
					die( 1 );
				}

				die( 0 );
				break;

			default:
				// This Process
				$this->_pids[ $pid ] = $job->id;
				break;
		}
	}

	protected function _doJobs(): void {
		foreach ( $this->_jobs as $job ) {
			$this->_doJob( $job );
			sleep( 1 );
		}

		$started = time();

		// Wait for currently running tasks to finish
		while ( $this->_pids && ( time() - $started ) < 120 ) {
			$status = null;

			pcntl_signal_dispatch();

			// Wait for a child process to exit.
			$pid = pcntl_waitpid( -1, $status, WNOHANG );

			if ( 0 === $pid ) {
				continue;
			}

			$job_id = $this->pids[ $pid ] ?? '';

			if ( ! $job_id ) {
				et_error( 'Job id missing!' );
				continue;
			}

			$job = $this->_jobs[ $job_id ];

			if ( pcntl_wifexited( $status ) ) {
				// Child process exited normally.
				call_user_func( $job->callbacks['done'], $job );

			} else {
				// Child process exited with error status.
				call_user_func( $job->callbacks['error'], $job );
			}

			unset( $this->_pids[ $pid ], $this->_jobs[ $job_id ] );
		}

		foreach ( $this->_pids as $pid => $job_id ) {
			// Process reached timeout, kill it.
			posix_kill( $pid, SIGKILL );

			$job = $this->_jobs[ $job_id ];

			call_user_func( $job->callbacks['error'], $job );

			unset( $this->_pids[ $pid ], $this->_jobs[ $job_id ] );
		}
	}

	protected function _nextJob(): Job {
		return $this->_queue->take();
	}

	public static function instance( Queue $queue, ?string $name = null ): self {
		if ( ! $name ) {
			$index = self::$_DB->incr( self::_key( 'workers:count' ) );
			$name  = "worker-{$index}";
		}

		return new self( $queue, $name );
	}

	public function run() {
		$lock = self::_key( 'workers', $this->name, 'lock' );

		self::$_DB->setex( $lock, 3600, 'true' );

		while ( self::$_DB->exists( $lock ) ) {
			// Grab and perform up to 1 queued job.
			while ( count( $this->_jobs ) < 1 && $job = $this->_nextJob() ) {
				$this->_jobs[ $job->id ] = $job;
			}

			if ( $this->_jobs ) {
				try {
					$this->_doJobs();
				} catch ( \Exception $err ) {
					et_error( $err->getMessage() );
				}
			} else {
				sleep( 5 );
			}
		}

		die( 0 );
	}
}
