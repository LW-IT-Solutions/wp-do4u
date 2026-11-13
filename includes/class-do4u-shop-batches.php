<?php
/**
 * Batches: estimate, start, follow, import the result file, cancel and delete.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores each batch in an own option (not autoloaded) and keeps an index of the newest 50.
 * States: estimated, running, imported, failed, cancelled, unknown.
 */
final class Do4u_Shop_Batches {

	const INDEX     = 'do4u_shop_batch_index';
	const PREFIX    = 'do4u_shop_batch_';
	const KEEP      = 50;
	const ESTIMATED = 86400;

	/**
	 * Ids of the stored batches, newest first.
	 *
	 * @return string[]
	 */
	public static function ids() {
		$ids = get_option( self::INDEX, array() );
		return is_array( $ids ) ? array_values( array_filter( $ids, array( self::class, 'valid_id' ) ) ) : array();
	}

	/**
	 * Whether a text is a local batch id.
	 *
	 * @param mixed $id Id.
	 * @return bool
	 */
	public static function valid_id( $id ) {
		return is_string( $id ) && (bool) preg_match( '/^[a-f0-9]{12}$/D', $id );
	}

	/**
	 * Loads a batch.
	 *
	 * @param string $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		if ( ! self::valid_id( $id ) ) {
			return null;
		}
		$b = get_option( self::PREFIX . $id, null );
		return is_array( $b ) ? $b : null;
	}

	/**
	 * Saves a batch.
	 *
	 * @param array $b Batch.
	 */
	public static function save( array $b ) {
		$b['updated'] = time();
		update_option( self::PREFIX . $b['id'], $b, false );
	}

	/**
	 * Removes a batch locally.
	 *
	 * @param string $id Id.
	 */
	public static function forget( $id ) {
		delete_option( self::PREFIX . $id );
		delete_option( 'do4u_shop_lock_' . $id );
		update_option( self::INDEX, array_values( array_diff( self::ids(), array( $id ) ) ), false );
	}

	/**
	 * Asks do4u.eu for the price and stores the batch as "estimated". Nothing is charged.
	 *
	 * @param string $tool        Tool.
	 * @param int[]  $product_ids Products.
	 * @param array  $fields      Chosen fields.
	 * @param array  $choice      Form choices (options, gallery, only_missing_alt).
	 * @return array The stored batch.
	 * @throws Do4u_Error When nothing can be sent or do4u.eu refuses.
	 */
	public static function estimate( $tool, array $product_ids, array $fields, array $choice ) {
		$def = Do4u_Shop_Tools::get( $tool );
		if ( ! $def ) {
			throw new Do4u_Error( esc_html__( 'Please choose what to create.', 'do4u' ), 'bad_tool' );
		}
		$fields = array_values( array_intersect( $def['fields'], $fields ) );
		if ( ! $fields ) {
			throw new Do4u_Error( esc_html__( 'Please choose at least one field to fill.', 'do4u' ), 'no_fields' );
		}
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
		if ( ! $product_ids ) {
			throw new Do4u_Error( esc_html__( 'Please select at least one product.', 'do4u' ), 'no_products' );
		}
		$built = Do4u_Shop_Tools::items( $tool, $product_ids, $fields, $choice );
		$items = $built['items'];
		if ( ! $items ) {
			throw new Do4u_Error( esc_html__( 'None of the selected products has something to send for this choice (for example no image, or every image already has an alt text).', 'do4u' ), 'empty' );
		}
		$max = (int) Do4u_Shop_Settings::get( 'batch_size' );
		if ( count( $items ) > $max ) {
			/* translators: 1: number of entries, 2: entries per batch */
			throw new Do4u_Error( esc_html( sprintf( __( 'This choice has %1$d entries, a batch takes at most %2$d (see Settings). Please select fewer products.', 'do4u' ), count( $items ), $max ) ), 'too_many' );
		}
		$b = array(
			'id'       => bin2hex( random_bytes( 6 ) ),
			'tool'     => $tool,
			'fields'   => $fields,
			'options'  => Do4u_Shop_Tools::options( $tool, $fields, $choice ),
			'language' => Do4u_Shop_Settings::batch_language(),
			'created'  => time(),
			'user'     => get_current_user_id(),
			'status'   => 'estimated',
			'remote'   => '',
			'items'    => $items,
			'skipped'  => $built['skipped'],
			'results'  => array(),
			'credits'  => 0,
			'message'  => '',
		);
		$answer        = Do4u_Shop_Api::request( 'POST', 'batches', self::payload( $b, true ), 45 );
		$b['estimate'] = array(
			'credits' => absint( $answer['credits_estimate'] ?? 0 ),
			'valid'   => absint( $answer['rows']['valid'] ?? 0 ),
			'skipped' => absint( $answer['rows']['skipped'] ?? 0 ),
			'reasons' => self::skipped_examples( $answer['skipped_examples'] ?? array() ),
			'time'    => time(),
		);
		self::save( $b );
		$ids = self::ids();
		array_unshift( $ids, $b['id'] );
		foreach ( array_slice( $ids, self::KEEP ) as $old ) {
			delete_option( self::PREFIX . $old );
		}
		update_option( self::INDEX, array_slice( $ids, 0, self::KEEP ), false );
		return $b;
	}

	/**
	 * Request body for POST /batches.
	 *
	 * @param array $b        Batch.
	 * @param bool  $estimate Only estimate.
	 * @return array
	 */
	private static function payload( array $b, $estimate ) {
		$body = array(
			'tool'          => $b['tool'],
			'language'      => $b['language'],
			'estimate_only' => (bool) $estimate,
			'rows'          => array_map(
				static function ( $item ) {
					return $item['row'];
				},
				$b['items']
			),
		);
		if ( $b['options'] ) {
			$body['options'] = $b['options'];
		}
		return $body;
	}

	/**
	 * Row numbers and reasons of rows do4u.eu would skip.
	 *
	 * @param mixed $list skipped_examples of the API.
	 * @return array
	 */
	private static function skipped_examples( $list ) {
		$out = array();
		foreach ( is_array( $list ) ? array_slice( $list, 0, 20 ) : array() as $e ) {
			if ( is_array( $e ) ) {
				$out[] = array(
					'row'     => absint( $e['row'] ?? 0 ),
					'message' => sanitize_text_field( (string) ( $e['error']['message'] ?? '' ) ),
				);
			}
		}
		return $out;
	}

	/**
	 * Starts a batch that was estimated. This uses credits.
	 *
	 * @param string $id Id.
	 * @return array Batch.
	 * @throws Do4u_Error When the batch cannot be started.
	 */
	public static function start( $id ) {
		$b = self::get( $id );
		if ( ! $b || 'estimated' !== $b['status'] ) {
			throw new Do4u_Error( esc_html__( 'This batch cannot be started (any more).', 'do4u' ), 'state' );
		}
		if ( time() - (int) ( $b['estimate']['time'] ?? 0 ) > self::ESTIMATED ) {
			throw new Do4u_Error( esc_html__( 'The estimate is older than 24 hours. Please create the batch again.', 'do4u' ), 'expired' );
		}
		self::lock( $id );
		try {
			$b = self::get( $id );
			if ( ! $b || 'estimated' !== $b['status'] ) {
				throw new Do4u_Error( esc_html__( 'This batch was started already.', 'do4u' ), 'state' );
			}
			$b['status'] = 'starting';
			self::save( $b );
			try {
				$answer = Do4u_Shop_Api::request( 'POST', 'batches', self::payload( $b, false ), 60 );
			} catch ( Do4u_Error $e ) {
				// With an answer, do4u.eu refused it: nothing was created. Without one, it is unknown whether it started.
				$b['status']  = 0 === $e->status ? 'unknown' : 'estimated';
				$b['message'] = $e->plain();
				self::save( $b );
				throw $e;
			}
			$remote = (string) ( $answer['id'] ?? '' );
			if ( ! preg_match( '/^[a-f0-9]{32}$/D', $remote ) ) {
				$b['status']  = 'unknown';
				$b['message'] = __( 'do4u.eu sent an unexpected answer.', 'do4u' );
				self::save( $b );
				throw new Do4u_Error( esc_html( $b['message'] ), 'invalid_response' );
			}
			$b['remote']        = $remote;
			$b['status']        = 'running';
			$b['started']       = time();
			$b['started_by']    = get_current_user_id();
			$b['remote_status'] = self::remote_state( $answer );
			$b['message']       = '';
			self::save( $b );
			return $b;
		} finally {
			self::unlock( $id );
		}
	}

	/**
	 * Takes the parts of a status answer the plugin shows.
	 *
	 * @param array $answer Answer of GET /batches/{id}.
	 * @return array
	 */
	private static function remote_state( array $answer ) {
		$rows = isset( $answer['rows'] ) && is_array( $answer['rows'] ) ? $answer['rows'] : array();
		$out  = array( 'status' => sanitize_key( $answer['status'] ?? '' ) );
		foreach ( array( 'total', 'waiting', 'running', 'done', 'failed', 'skipped', 'cancelled' ) as $k ) {
			$out[ $k ] = absint( $rows[ $k ] ?? 0 );
		}
		$out['credits']      = absint( $answer['credits'] ?? 0 );
		$out['delete_after'] = sanitize_text_field( (string) ( $answer['delete_after'] ?? '' ) );
		$out['checked']      = time();
		return $out;
	}

	/**
	 * Asks do4u.eu for the state of a running batch and imports the result once it is done.
	 *
	 * @param string $id    Id.
	 * @param bool   $force Ask even if the last question was less than 10 seconds ago.
	 * @return array Batch.
	 * @throws Do4u_Error On errors of do4u.eu.
	 */
	public static function refresh( $id, $force = false ) {
		$b = self::get( $id );
		if ( ! $b ) {
			throw new Do4u_Error( esc_html__( 'This batch does not exist (any more).', 'do4u' ), 'not_found' );
		}
		if ( 'running' !== $b['status'] ) {
			return $b;
		}
		if ( ! $force && time() - (int) ( $b['remote_status']['checked'] ?? 0 ) < 10 ) {
			return $b;
		}
		self::lock( $id );
		try {
			$b = self::get( $id );
			if ( ! $b || 'running' !== $b['status'] ) {
				return $b ? $b : array();
			}
			try {
				$answer = Do4u_Shop_Api::request( 'GET', 'batches/' . $b['remote'] );
			} catch ( Do4u_Error $e ) {
				if ( 404 === $e->status ) {
					$b['status']  = 'failed';
					$b['message'] = __( 'do4u.eu no longer has this batch. Results are deleted there after their storage period.', 'do4u' );
					self::save( $b );
				}
				throw $e;
			}
			$b['remote_status'] = self::remote_state( $answer );
			$b['credits']       = $b['remote_status']['credits'];
			if ( in_array( $b['remote_status']['status'], array( 'done', 'cancelled' ), true ) ) {
				$csv = (string) Do4u_Shop_Api::request( 'GET', 'batches/' . $b['remote'] . '/result.csv', null, 60, null, true );
				$b   = self::import( $b, $csv );
				if ( 'imported' === $b['status'] && Do4u_Shop_Settings::get( 'delete_remote' ) ) {
					try {
						Do4u_Shop_Api::request( 'DELETE', 'batches/' . $b['remote'] );
						$b['remote_deleted'] = time();
					} catch ( Do4u_Error $e ) {
						$b['remote_deleted'] = 0;
					}
				}
			}
			self::save( $b );
			return $b;
		} finally {
			self::unlock( $id );
		}
	}

	/**
	 * Reads the result file. Every row must belong to the entry that was sent in the same place, with the same input.
	 *
	 * @param array  $b   Batch.
	 * @param string $csv Result file.
	 * @return array Batch with results.
	 */
	public static function import( array $b, $csv ) {
		$rows = self::parse_csv( $csv );
		if ( null === $rows || count( $rows ) !== count( $b['items'] ) ) {
			$b['status']  = 'failed';
			$b['message'] = __( 'The result file of do4u.eu does not match this batch. Nothing can be applied from it.', 'do4u' );
			return $b;
		}
		$results = array();
		foreach ( $b['items'] as $i => $item ) {
			$data = $rows[ $i ];
			$same = true;
			foreach ( $item['row'] as $col => $value ) {
				$got = (string) ( $data[ $col ] ?? '' );
				if ( $got !== (string) $value && $got !== "'" . $value ) {
					$same = false;
				}
			}
			if ( ! $same ) {
				$results[ $i ] = array(
					'status'    => 'mismatch',
					'error'     => __( 'The result does not belong to this entry and is not shown.', 'do4u' ),
					'credits'   => 0,
					'proposals' => array(),
					'variants'  => array(),
					'flags'     => array(),
					'notes'     => array(),
					'applied'   => array(),
					'discarded' => false,
				);
				continue;
			}
			$results[ $i ] = Do4u_Shop_Tools::result( $b['tool'], $data, $item, $b['fields'] );
		}
		$b['results']  = $results;
		$b['status']   = 'imported';
		$b['imported'] = time();
		$b['message']  = '';
		return $b;
	}

	/**
	 * Parses CSV with a header row into associative rows. Separator from the header row (comma, semicolon or tab).
	 *
	 * @param string $csv Text.
	 * @return array|null Rows, or null for an invalid file.
	 */
	public static function parse_csv( $csv ) {
		$csv = (string) preg_replace( '/^\xEF\xBB\xBF/', '', (string) $csv );
		if ( '' === trim( $csv ) || strlen( $csv ) > Do4u_Shop_Api::MAX_RESPONSE ) {
			return null;
		}
		$first = (string) strtok( $csv, "\n" );
		$sep   = ',';
		foreach ( array( ';', "\t" ) as $s ) {
			if ( substr_count( $first, $s ) > substr_count( $first, $sep ) ) {
				$sep = $s;
			}
		}
		$records = self::records( $csv, $sep );
		if ( null === $records || ! $records ) {
			return null;
		}
		$head = array_shift( $records );
		if ( ! in_array( 'do4u_status', $head, true ) ) {
			return null;
		}
		$rows = array();
		foreach ( $records as $row ) {
			if ( array( '' ) === $row ) {
				continue;
			}
			if ( count( $row ) !== count( $head ) ) {
				return null;
			}
			$rows[] = array_combine( $head, $row );
		}
		return $rows;
	}

	/**
	 * Splits CSV text (RFC 4180: fields in double quotes may contain separators, line breaks and doubled quotes) into records.
	 *
	 * @param string $csv Text.
	 * @param string $sep Separator.
	 * @return array|null Records, or null for broken quoting.
	 */
	private static function records( $csv, $sep ) {
		$q       = preg_quote( $sep, '/' );
		$pattern = '/\G(?:"((?:[^"]++|"")*+)"|([^"' . $q . '\r\n]*+))(' . $q . '|\r\n|\n|\r|$)/';
		$records = array();
		$record  = array();
		$offset  = 0;
		$length  = strlen( $csv );
		while ( $offset < $length ) {
			if ( ! preg_match( $pattern, $csv, $m, 0, $offset ) || '' === $m[0] ) {
				return null;
			}
			$record[] = isset( $m[1] ) && '' !== $m[1] ? str_replace( '""', '"', $m[1] ) : ( $m[2] ?? '' );
			$offset  += strlen( $m[0] );
			if ( $m[3] !== $sep ) {
				$records[] = $record;
				$record    = array();
			} elseif ( $offset >= $length ) {
				$record[]  = '';
				$records[] = $record;
				$record    = array();
			}
		}
		return $records;
	}

	/**
	 * Cancels a running batch at do4u.eu (rows not yet done cost nothing) or deletes a finished one there and here.
	 *
	 * @param string $id     Id.
	 * @param bool   $forget Also remove it from the list here.
	 * @return array|null Batch, or null when it was removed.
	 * @throws Do4u_Error On errors of do4u.eu.
	 */
	public static function cancel( $id, $forget = false ) {
		$b = self::get( $id );
		if ( ! $b ) {
			return null;
		}
		$running = 'running' === $b['status'];
		if ( '' !== $b['remote'] && empty( $b['remote_deleted'] ) && in_array( $b['status'], array( 'running', 'imported', 'failed' ), true ) ) {
			try {
				// On a running batch DELETE cancels it (rows not yet done cost nothing); on a finished one it deletes the results.
				Do4u_Shop_Api::request( 'DELETE', 'batches/' . $b['remote'] );
				if ( ! $running || $forget ) {
					$b['remote_deleted'] = time();
				}
			} catch ( Do4u_Error $e ) {
				if ( 404 !== $e->status ) {
					throw $e;
				}
				$b['remote_deleted'] = time();
				$running             = false;
			}
		}
		if ( $forget ) {
			self::forget( $id );
			return null;
		}
		self::save( $b );
		if ( $running ) {
			// Results of rows that were done before the cancellation stay available and are imported now.
			return self::refresh( $id, true );
		}
		return $b;
	}

	/**
	 * Simple lock against two starts or imports at the same time (add_option is atomic).
	 *
	 * @param string $id Batch.
	 * @throws Do4u_Error When another request holds the lock.
	 */
	private static function lock( $id ) {
		$name = 'do4u_shop_lock_' . $id;
		if ( add_option( $name, time(), '', false ) ) {
			return;
		}
		$since = (int) get_option( $name, 0 );
		if ( $since > 0 && time() - $since > 120 ) {
			update_option( $name, time(), false );
			return;
		}
		throw new Do4u_Error( esc_html__( 'This batch is being processed by another request. Please reload the page in a moment.', 'do4u' ), 'locked' );
	}

	/**
	 * Releases the lock.
	 *
	 * @param string $id Batch.
	 */
	private static function unlock( $id ) {
		delete_option( 'do4u_shop_lock_' . $id );
	}

	/**
	 * Open suggestions of a product, over all stored batches.
	 *
	 * @param int $product_id Product.
	 * @return array List of [batch id, tool, created, entry].
	 */
	public static function open_for_product( $product_id ) {
		$out = array();
		foreach ( self::ids() as $id ) {
			$b = self::get( $id );
			if ( ! $b || 'imported' !== $b['status'] ) {
				continue;
			}
			foreach ( $b['items'] as $i => $item ) {
				$r = $b['results'][ $i ] ?? array();
				if ( (int) $item['product'] === (int) $product_id && 'done' === ( $r['status'] ?? '' ) && empty( $r['discarded'] ) && count( $r['applied'] ?? array() ) < count( $r['proposals'] ?? array() ) ) {
					$out[] = array( $id, $b['tool'], (int) $b['created'], (int) $i );
					break;
				}
			}
		}
		return $out;
	}
}
