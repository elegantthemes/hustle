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

	public array $data;

	public string $id;

	public string $status;

	public function __construct( string $id, array $details = [] ) {
		$this->callbacks = $details['callbacks'];
		$this->data      = $details['data'];
		$this->id        = $details['id'];
		$this->status    = $details['status'];
	}

	public static function instance( string $queue, ?string $id, ?array $details = null ): self {
		if ( $id ) {
			// Get existing job details from database
			$json    = self::$_DB->get( self::_key( 'queues', $queue, 'jobs', $id ) );
			$details = json_decode( $json, true );

		} else if ( $details ) {
			// Create new job in database
			$id = $details['id'] = self::_uuid4();

			$details['status'] = 'pending';

			self::$_DB->set( self::_key( 'queues', $queue, 'jobs', $id ), json_encode( $details ) );

		} else {
			throw new ErrorException( 'At least one of $id, $details is required.' );
		}

		return new self( $id, $details );
	}

	public function jsonSerialize() {
		return [
			'id'        => $this->id,
			'callbacks' => $this->callbacks,
			'data'      => $this->data,
			'status'    => $this->status,
		];
	}
}
