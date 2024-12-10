<?php

namespace ET\Hustle;

// Built-in Dependencies
use JsonSerializable, ErrorException;


class Job extends Base implements JsonSerializable {

	public array $callbacks = [
		'run'   => null,
		'done'  => null,
		'error' => null,
	];

	public int $created_at;

	public array $data;

	public string $id;

	public string $queue;

	public string $status;

	public function __construct( string $id, array $details = [] ) {
		$this->callbacks  = $details['callbacks'];
		$this->data       = $details['data'];
		$this->id         = $details['id'];
		$this->queue      = $details['queue'];
		$this->status     = $details['status'];
		$this->created_at = $details['created_at'];
	}

	public static function instance( string $queue, ?string $id, ?array $details = null ): self {
		if ( $id ) {
			// Get existing job details from database
			$json    = self::_dbTry( 'get', self::_key( 'queues', $queue, 'jobs', $id ) );
			$details = json_decode( $json, true );

		} else if ( $details ) {
			// Create new job in database
			$id = $details['id'] = self::_uuid4();

			$details['status']     = 'pending';
			$details['queue']      = $queue;
			$details['created_at'] = time();

			self::_dbTry( 'set', self::_key( 'queues', $queue, 'jobs', $id ), json_encode( $details ) );

		} else {
			throw new ErrorException( 'At least one of $id, $details is required.' );
		}

		return new self( $id, $details );
	}

	public function jsonSerialize(): array {
		return [
			'id'         => $this->id,
			'callbacks'  => $this->callbacks,
			'created_at' => $this->created_at,
			'data'       => $this->data,
			'queue'      => $this->queue,
			'status'     => $this->status,
		];
	}
}
