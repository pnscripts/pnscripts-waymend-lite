<?php
/**
 * Result of matching a request against the rules.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\Core;

/**
 * The rule that matched and the final target (captures and query string applied).
 */
final class Match_Result {

	/** @var Rule */
	public $rule;
	/** @var string Empty for 410 rules. */
	public $target;
	/** @var int */
	public $status;

	public function __construct( Rule $rule, string $target ) {
		$this->rule   = $rule;
		$this->target = $target;
		$this->status = $rule->status;
	}
}
