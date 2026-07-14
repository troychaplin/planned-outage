<?php
/**
 * Scheduled outage window handling for Planned Outage for Block Themes.
 *
 * @package Planned_Outage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the scheduled maintenance window: storage, timezone conversion,
 * and active/upcoming/past status checks.
 *
 * Timestamps are stored in UTC. Conversion to and from the site timezone
 * happens only at the input/display boundary.
 */
class Pobt_Schedule {

	/**
	 * Schedule status: no window configured.
	 */
	const STATUS_NONE = 'none';

	/**
	 * Schedule status: window is in the future.
	 */
	const STATUS_UPCOMING = 'upcoming';

	/**
	 * Schedule status: window is currently active.
	 */
	const STATUS_ACTIVE = 'active';

	/**
	 * Schedule status: window has ended.
	 */
	const STATUS_PAST = 'past';

	/**
	 * Returns the scheduled window start as a UTC timestamp, or 0 if unset.
	 *
	 * @return int
	 */
	public function get_start() {
		return absint( get_option( 'pobt_schedule_start', 0 ) );
	}

	/**
	 * Returns the scheduled window end as a UTC timestamp, or 0 if unset.
	 *
	 * @return int
	 */
	public function get_end() {
		return absint( get_option( 'pobt_schedule_end', 0 ) );
	}

	/**
	 * Checks whether the current time falls inside the scheduled window.
	 *
	 * Evaluated lazily on each request so activation and deactivation are
	 * exact regardless of whether WP-Cron fires on time.
	 *
	 * @param int|null $now Optional. Timestamp to check against. Defaults to current time.
	 * @return bool True if a complete window is configured and $now is within it.
	 */
	public function is_window_active( $now = null ) {
		$start = $this->get_start();
		$end   = $this->get_end();

		if ( ! $start || ! $end ) {
			return false;
		}

		$now = null === $now ? time() : (int) $now;

		return $now >= $start && $now < $end;
	}

	/**
	 * Returns the current schedule status.
	 *
	 * @param int|null $now Optional. Timestamp to check against. Defaults to current time.
	 * @return string One of the STATUS_* constants.
	 */
	public function get_status( $now = null ) {
		$start = $this->get_start();
		$end   = $this->get_end();

		if ( ! $start || ! $end ) {
			return self::STATUS_NONE;
		}

		$now = null === $now ? time() : (int) $now;

		if ( $now < $start ) {
			return self::STATUS_UPCOMING;
		}

		if ( $now < $end ) {
			return self::STATUS_ACTIVE;
		}

		return self::STATUS_PAST;
	}

	/**
	 * Returns the number of seconds until the scheduled window ends.
	 *
	 * @return int Seconds remaining, or 0 if no window is set or it has ended.
	 */
	public function seconds_until_end() {
		$end = $this->get_end();

		if ( ! $end ) {
			return 0;
		}

		return max( 0, $end - time() );
	}

	/**
	 * Converts a datetime-local input value to a UTC timestamp.
	 *
	 * The value is interpreted in the site timezone. A start time falling in
	 * a DST gap resolves per PHP's default forward shift, which is acceptable
	 * for this use case.
	 *
	 * @param mixed $value Posted value in Y-m-d\TH:i format (seconds optional).
	 * @return int UTC timestamp, or 0 for an empty or invalid value.
	 */
	public function sanitize_datetime_local( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return 0;
		}

		$value = trim( $value );

		// Some browsers include seconds in datetime-local values.
		$datetime = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i:s', $value, wp_timezone() );
		if ( false === $datetime ) {
			$datetime = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
		}

		if ( false === $datetime ) {
			return 0;
		}

		return $datetime->getTimestamp();
	}

	/**
	 * Formats a UTC timestamp for a datetime-local input value in the site timezone.
	 *
	 * @param int $timestamp UTC timestamp.
	 * @return string Value for the input's value attribute, or empty string if unset.
	 */
	public function format_for_input( $timestamp ) {
		if ( ! $timestamp ) {
			return '';
		}

		return wp_date( 'Y-m-d\TH:i', $timestamp, wp_timezone() );
	}

	/**
	 * Formats a UTC timestamp for display using the site date and time formats.
	 *
	 * @param int $timestamp UTC timestamp.
	 * @return string Localized date/time string, or empty string if unset.
	 */
	public function format_for_display( $timestamp ) {
		if ( ! $timestamp ) {
			return '';
		}

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return wp_date( $format, $timestamp, wp_timezone() );
	}
}
