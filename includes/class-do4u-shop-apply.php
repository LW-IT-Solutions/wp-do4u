<?php
/**
 * Taking over suggestions, with a history of every change and undo.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes suggestions into products and images. Every write checks the rights for that item, refuses when the field
 * was changed since the batch was prepared (unless confirmed), and keeps the old value for undo.
 */
final class Do4u_Shop_Apply {

	/** History on the product or image: newest first, at most 20 entries. */
	const HISTORY = '_do4u_shop_history';

	/** Site-wide list of the last 200 changes, for the History tab. */
	const LOG = 'do4u_shop_log';

	/**
	 * Applies chosen fields of one entry.
	 *
	 * @param string $batch_id Batch.
	 * @param int    $index    Entry.
	 * @param array  $fields   Fields to apply.
	 * @param bool   $force    Also apply when the field was changed since the batch was prepared.
	 * @param int    $variant  Chosen variant (SEO title and meta description).
	 * @return array ['applied' => [fields], 'conflicts' => [fields]]
	 * @throws Do4u_Error When nothing can be applied.
	 */
	public static function apply( $batch_id, $index, array $fields, $force = false, $variant = 0 ) {
		$b = Do4u_Shop_Batches::get( $batch_id );
		if ( ! $b || 'imported' !== $b['status'] || ! isset( $b['items'][ $index ], $b['results'][ $index ] ) ) {
			throw new Do4u_Error( esc_html__( 'This suggestion does not exist (any more).', 'do4u' ), 'not_found' );
		}
		$item = $b['items'][ $index ];
		$res  = $b['results'][ $index ];
		if ( 'done' !== $res['status'] || ! empty( $res['discarded'] ) ) {
			throw new Do4u_Error( esc_html__( 'This suggestion cannot be applied.', 'do4u' ), 'state' );
		}
		$proposals = $res['proposals'];
		if ( $res['variants'] && isset( $res['variants'][ $variant ] ) ) {
			foreach ( array( 'seo_title', 'meta_description' ) as $f ) {
				if ( isset( $proposals[ $f ] ) && '' !== $res['variants'][ $variant ][ $f ] ) {
					$proposals[ $f ] = $res['variants'][ $variant ][ $f ];
				}
			}
		}
		$fields = array_values( array_intersect( array_keys( $proposals ), $fields ) );
		$fields = array_values( array_diff( $fields, array_keys( $res['applied'] ) ) );
		if ( ! $fields ) {
			throw new Do4u_Error( esc_html__( 'Please choose at least one field that is not applied yet.', 'do4u' ), 'no_fields' );
		}
		$product = wc_get_product( (int) $item['product'] );
		if ( ! $product || ! current_user_can( 'edit_post', $product->get_id() ) ) {
			throw new Do4u_Error( esc_html__( 'You may not edit this product.', 'do4u' ), 'forbidden', 403 );
		}
		$image = (int) $item['image'];
		if ( in_array( 'image_alt', $fields, true ) && ( ! $image || ! current_user_can( 'edit_post', $image ) ) ) {
			throw new Do4u_Error( esc_html__( 'You may not edit this image.', 'do4u' ), 'forbidden', 403 );
		}
		$conflicts = array();
		$changes   = array();
		foreach ( $fields as $f ) {
			$now = Do4u_Shop_Tools::current( $item, $f );
			if ( ! $force && array_key_exists( $f, $item['before'] ) && $now !== (string) $item['before'][ $f ] ) {
				$conflicts[] = $f;
				continue;
			}
			$after = self::clean( $f, (string) $proposals[ $f ] );
			if ( $after === $now ) {
				continue;
			}
			$changes[ $f ] = array(
				'before' => $now,
				'after'  => $after,
			);
		}
		if ( $changes ) {
			self::write( $product, $image, $changes, 'after' );
			self::remember( $item, $changes, $batch_id );
			$b = Do4u_Shop_Batches::get( $batch_id );
			foreach ( array_keys( $changes ) as $f ) {
				$b['results'][ $index ]['applied'][ $f ] = time();
			}
			Do4u_Shop_Batches::save( $b );
		}
		return array(
			'applied'   => array_keys( $changes ),
			'conflicts' => $conflicts,
		);
	}

	/**
	 * Cleans a value for its field, right before it is written.
	 *
	 * @param string $field Field.
	 * @param string $value Value.
	 * @return string
	 */
	public static function clean( $field, $value ) {
		if ( in_array( $field, array( 'description', 'short_description' ), true ) ) {
			return wp_kses_post( $value );
		}
		return sanitize_text_field( $value );
	}

	/**
	 * Writes values into a product and its image.
	 *
	 * @param WC_Product $product Product.
	 * @param int        $image   Image.
	 * @param array      $changes Field => ['before', 'after'].
	 * @param string     $which   'after' to apply, 'before' to undo.
	 */
	private static function write( $product, $image, array $changes, $which ) {
		$save = false;
		foreach ( $changes as $f => $c ) {
			$v = self::clean( $f, (string) $c[ $which ] );
			switch ( $f ) {
				case 'name':
					$product->set_name( $v );
					$save = true;
					break;
				case 'description':
					$product->set_description( $v );
					$save = true;
					break;
				case 'short_description':
					$product->set_short_description( $v );
					$save = true;
					break;
				case 'seo_title':
				case 'meta_description':
					Do4u_Shop_Seo::set( $product, $f, $v );
					$save = true;
					break;
				case 'image_alt':
					if ( '' === $v ) {
						delete_post_meta( $image, '_wp_attachment_image_alt' );
					} else {
						update_post_meta( $image, '_wp_attachment_image_alt', $v );
					}
					break;
			}
		}
		if ( $save ) {
			$product->save();
		}
	}

	/**
	 * Notes a change in the history of the product and in the site-wide log.
	 *
	 * @param array  $item     Entry.
	 * @param array  $changes  Field => ['before', 'after'].
	 * @param string $batch_id Batch.
	 */
	private static function remember( array $item, array $changes, $batch_id ) {
		$pid     = (int) $item['product'];
		$history = get_post_meta( $pid, self::HISTORY, true );
		$history = is_array( $history ) ? $history : array();
		$key     = bin2hex( random_bytes( 6 ) );
		array_unshift(
			$history,
			array(
				'key'     => $key,
				'time'    => time(),
				'user'    => get_current_user_id(),
				'batch'   => $batch_id,
				'image'   => (int) $item['image'],
				'changes' => $changes,
				'undone'  => 0,
			)
		);
		update_post_meta( $pid, self::HISTORY, array_slice( $history, 0, 20 ) );
		$log = get_option( self::LOG, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift(
			$log,
			array(
				'key'     => $key,
				'time'    => time(),
				'user'    => get_current_user_id(),
				'product' => $pid,
				'fields'  => array_keys( $changes ),
			)
		);
		update_option( self::LOG, array_slice( $log, 0, 200 ), false );
	}

	/**
	 * History of a product.
	 *
	 * @param int $product_id Product.
	 * @return array
	 */
	public static function history( $product_id ) {
		$h = get_post_meta( (int) $product_id, self::HISTORY, true );
		return is_array( $h ) ? $h : array();
	}

	/**
	 * Undoes one change: restores the old values. Refuses, unless confirmed, when a field was changed again since.
	 *
	 * @param int    $product_id Product.
	 * @param string $key        Entry of the history.
	 * @param bool   $force      Also restore fields that were changed again.
	 * @return array ['restored' => [fields], 'conflicts' => [fields]]
	 * @throws Do4u_Error When the entry does not exist or the user lacks the rights.
	 */
	public static function undo( $product_id, $key, $force = false ) {
		$product = wc_get_product( (int) $product_id );
		if ( ! $product || ! current_user_can( 'edit_post', $product->get_id() ) ) {
			throw new Do4u_Error( esc_html__( 'You may not edit this product.', 'do4u' ), 'forbidden', 403 );
		}
		$history = self::history( $product->get_id() );
		foreach ( $history as $n => $entry ) {
			if ( ( $entry['key'] ?? '' ) !== $key ) {
				continue;
			}
			if ( ! empty( $entry['undone'] ) ) {
				throw new Do4u_Error( esc_html__( 'This change was undone already.', 'do4u' ), 'state' );
			}
			$image = (int) $entry['image'];
			if ( isset( $entry['changes']['image_alt'] ) && ( ! $image || ! current_user_can( 'edit_post', $image ) ) ) {
				throw new Do4u_Error( esc_html__( 'You may not edit this image.', 'do4u' ), 'forbidden', 403 );
			}
			$item      = array(
				'product' => $product->get_id(),
				'image'   => $image,
			);
			$restore   = array();
			$conflicts = array();
			foreach ( $entry['changes'] as $f => $c ) {
				if ( ! $force && Do4u_Shop_Tools::current( $item, $f ) !== self::clean( $f, (string) $c['after'] ) ) {
					$conflicts[] = $f;
					continue;
				}
				$restore[ $f ] = $c;
			}
			if ( $conflicts && ! $force ) {
				return array(
					'restored'  => array(),
					'conflicts' => $conflicts,
				);
			}
			self::write( $product, $image, $restore, 'before' );
			$history[ $n ]['undone'] = time();
			update_post_meta( $product->get_id(), self::HISTORY, $history );
			$log = get_option( self::LOG, array() );
			if ( is_array( $log ) ) {
				foreach ( $log as $i => $l ) {
					if ( ( $l['key'] ?? '' ) === $key ) {
						$log[ $i ]['undone'] = time();
					}
				}
				update_option( self::LOG, $log, false );
			}
			self::unmark( $entry['batch'] ?? '', $product->get_id(), array_keys( $restore ) );
			return array(
				'restored'  => array_keys( $restore ),
				'conflicts' => array(),
			);
		}
		throw new Do4u_Error( esc_html__( 'This change is not in the history (any more).', 'do4u' ), 'not_found' );
	}

	/**
	 * After an undo, the suggestion in its batch can be applied again.
	 *
	 * @param string $batch_id   Batch.
	 * @param int    $product_id Product.
	 * @param array  $fields     Fields.
	 */
	private static function unmark( $batch_id, $product_id, array $fields ) {
		$b = Do4u_Shop_Batches::get( (string) $batch_id );
		if ( ! $b ) {
			return;
		}
		foreach ( $b['items'] as $i => $item ) {
			if ( (int) $item['product'] !== (int) $product_id || ! isset( $b['results'][ $i ] ) ) {
				continue;
			}
			foreach ( $fields as $f ) {
				unset( $b['results'][ $i ]['applied'][ $f ] );
				if ( array_key_exists( $f, $item['before'] ) ) {
					// The field now holds the value of before the change again.
					$b['items'][ $i ]['before'][ $f ] = Do4u_Shop_Tools::current( $item, $f );
				}
			}
		}
		Do4u_Shop_Batches::save( $b );
	}

	/**
	 * Marks a suggestion as discarded.
	 *
	 * @param string $batch_id Batch.
	 * @param int    $index    Entry.
	 */
	public static function discard( $batch_id, $index ) {
		$b = Do4u_Shop_Batches::get( $batch_id );
		if ( $b && isset( $b['results'][ $index ] ) ) {
			$b['results'][ $index ]['discarded'] = ! empty( $b['results'][ $index ]['discarded'] ) ? false : true;
			Do4u_Shop_Batches::save( $b );
		}
	}
}
