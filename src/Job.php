<?php

namespace ET\Hustle;

// Built-in Dependencies
use JsonSerializable;


class Job implements JsonSerializable {

	protected Queue $_queue;

	public string $id;

	/**
	 * @var callable
	 */
	public string $class;

	public array $data;

	public function __construct( Queue $queue, string $class, array $data ) {
		$this->_queue = $queue;
		$this->class  = $class;
		$this->data   = $data;
		$this->id     = $this->_uuid4();
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

	public function jsonSerialize() {
		// TODO: Implement jsonSerialize() method.
	}
}
