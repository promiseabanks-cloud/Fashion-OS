<?php
/**
 * Money and quantity parsing.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Commerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalises monetary amounts and item quantities.
 *
 * Amounts are stored as `DECIMAL(12,2)` strings. Parsing lives in one place so
 * quotations and orders accept exactly the same input and round the same way.
 * Callers choose their own error codes; `null` means "not acceptable".
 */
final class Money {

	/**
	 * Parse an amount between 0 and 9,999,999.99 with at most two decimals.
	 *
	 * @param mixed $value Candidate amount.
	 */
	public static function amount( $value ): ?string {
		if ( is_int( $value ) || is_float( $value ) ) {
			$text = (string) $value;
		} elseif ( is_string( $value ) ) {
			$text = trim( $value );
		} else {
			return null;
		}

		if ( ! preg_match( '/^\d{1,7}(\.\d{1,2})?$/', $text ) ) {
			return null;
		}

		return number_format( (float) $text, 2, '.', '' );
	}

	/**
	 * Parse a whole quantity between 1 and 9999.
	 *
	 * @param mixed $value Candidate quantity.
	 */
	public static function quantity( $value ): ?int {
		if ( is_int( $value ) ) {
			$quantity = $value;
		} elseif ( is_string( $value ) && preg_match( '/^\d{1,4}$/', trim( $value ) ) ) {
			$quantity = (int) trim( $value );
		} else {
			return null;
		}

		return ( $quantity >= 1 && $quantity <= 9999 ) ? $quantity : null;
	}

	/**
	 * Parse a positive measured quantity (metres, pieces) up to 999,999.99.
	 *
	 * Materials are not sold in whole units, so this accepts two decimals but
	 * still refuses negatives, exponents and more than two decimal places.
	 *
	 * @param mixed $value Candidate quantity.
	 */
	public static function measure( $value ): ?string {
		if ( is_int( $value ) ) {
			$text = (string) $value;
		} elseif ( is_string( $value ) ) {
			$text = trim( $value );
		} else {
			return null;
		}

		if ( ! preg_match( '/^\d{1,6}(\.\d{1,2})?$/', $text ) ) {
			return null;
		}

		$amount = round( (float) $text, 2 );

		if ( $amount <= 0 ) {
			return null;
		}

		return number_format( $amount, 2, '.', '' );
	}

	/**
	 * Parse a signed adjustment between -999,999.99 and 999,999.99.
	 *
	 * Used for stock corrections, where the direction is part of the value.
	 * Zero is rejected because it would write a movement that changed nothing.
	 *
	 * @param mixed $value Candidate delta.
	 */
	public static function delta( $value ): ?string {
		if ( is_int( $value ) || is_float( $value ) ) {
			$text = (string) $value;
		} elseif ( is_string( $value ) ) {
			$text = trim( $value );
		} else {
			return null;
		}

		if ( ! preg_match( '/^-?\d{1,6}(\.\d{1,2})?$/', $text ) ) {
			return null;
		}

		$amount = round( (float) $text, 2 );

		if ( 0.0 === $amount ) {
			return null;
		}

		return number_format( $amount, 2, '.', '' );
	}

	/**
	 * Line total for a quantity and unit price.
	 *
	 * @param int    $quantity   Quantity.
	 * @param string $unit_price Normalised unit price.
	 */
	public static function line_total( int $quantity, string $unit_price ): string {
		return number_format( $quantity * (float) $unit_price, 2, '.', '' );
	}
}
