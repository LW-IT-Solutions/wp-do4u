<?php
/**
 * The do4u.eu tools the plugin uses: what they need from a product, and how their results map to product fields.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tool definitions, input rows and result mapping. Model output is untrusted: it is only ever used as plain text,
 * escaped and wrapped into fixed HTML by this class, never taken over as HTML.
 */
final class Do4u_Shop_Tools {

	/** Longest text sent per entry, below the limits of GET /api/v1/tools (max_chars). */
	const MAX_CHARS = array(
		'product_description' => 6000,
		'seo_meta'            => 6000,
		'alt_text'            => 900,
		'product_photo'       => 1400,
	);

	/**
	 * Tools offered in the plugin.
	 *
	 * @return array Name => ['label', 'help', 'unit' (product|image), 'fields' => [...], 'default_fields' => [...], 'image' => bool].
	 */
	public static function all() {
		return array(
			'product_description' => array(
				'label'          => __( 'Product texts', 'do4u' ),
				'help'           => __( 'Description, short description, and on request product name and meta description, written from the data of each product: name, categories, attributes, existing texts. Only facts from your product data are requested; numbers that are not in it are marked.', 'do4u' ),
				'unit'           => 'product',
				'fields'         => array( 'description', 'short_description', 'name', 'meta_description' ),
				'default_fields' => array( 'description', 'short_description' ),
				'image'          => false,
			),
			'seo_meta'            => array(
				'label'          => __( 'SEO title and meta description', 'do4u' ),
				'help'           => __( 'Three variants each, within the usual length limits, with the focus keyword of your SEO plugin if one is set. Saved in Yoast SEO, Rank Math, SEOPress or The SEO Framework if active, otherwise in own fields.', 'do4u' ),
				'unit'           => 'product',
				'fields'         => array( 'seo_title', 'meta_description' ),
				'default_fields' => array( 'seo_title', 'meta_description' ),
				'image'          => false,
			),
			'alt_text'            => array(
				'label'          => __( 'Image alt texts', 'do4u' ),
				'help'           => __( 'Alt text for the main image and, on request, the gallery images of each product. do4u.eu loads each image from its public address.', 'do4u' ),
				'unit'           => 'image',
				'fields'         => array( 'image_alt' ),
				'default_fields' => array( 'image_alt' ),
				'image'          => true,
			),
			'product_photo'       => array(
				'label'          => __( 'Texts from the product photo', 'do4u' ),
				'help'           => __( 'For products with little text: do4u.eu looks at the main image and writes description, short description, product name and image alt text. Facts such as sizes, materials or certificates appear only if they are in your product data.', 'do4u' ),
				'unit'           => 'product',
				'fields'         => array( 'description', 'short_description', 'name', 'image_alt' ),
				'default_fields' => array( 'description', 'short_description', 'image_alt' ),
				'image'          => true,
			),
		);
	}

	/**
	 * One tool, or null.
	 *
	 * @param string $name Name.
	 * @return array|null
	 */
	public static function get( $name ) {
		$all = self::all();
		return isset( $all[ $name ] ) ? $all[ $name ] : null;
	}

	/**
	 * Labels of the fields.
	 *
	 * @return array
	 */
	public static function field_labels() {
		$seo = Do4u_Shop_Seo::target();
		return array(
			'name'              => __( 'Product name', 'do4u' ),
			'description'       => __( 'Description', 'do4u' ),
			'short_description' => __( 'Short description', 'do4u' ),
			/* translators: %s: name of the SEO plugin, or "do4u.eu fields" */
			'seo_title'         => sprintf( __( 'SEO title (%s)', 'do4u' ), $seo['name'] ),
			/* translators: %s: name of the SEO plugin, or "do4u.eu fields" */
			'meta_description'  => sprintf( __( 'Meta description (%s)', 'do4u' ), $seo['name'] ),
			'image_alt'         => __( 'Image alt text', 'do4u' ),
		);
	}

	/**
	 * API options for a batch, from the settings and the choice in the form.
	 *
	 * @param string $tool   Tool.
	 * @param array  $fields Chosen fields.
	 * @param array  $choice Unslashed form input (tone, length, channel, target).
	 * @return array
	 */
	public static function options( $tool, array $fields, array $choice ) {
		$s      = Do4u_Shop_Settings::all();
		$target = sanitize_key( $choice['target'] ?? '' );
		$auto   = 'seo_meta' !== $tool;
		if ( ! in_array( $target, Do4u_Shop_Settings::TARGETS, true ) && ! ( $auto && 'auto' === $target ) ) {
			$target = Do4u_Shop_Settings::target( $auto );
		}
		switch ( $tool ) {
			case 'product_description':
				$tone    = sanitize_key( $choice['tone'] ?? $s['tone'] );
				$length  = sanitize_key( $choice['length'] ?? $s['length'] );
				$channel = sanitize_key( $choice['channel'] ?? $s['channel'] );
				return array(
					'tone'             => in_array( $tone, array( 'neutral', 'friendly', 'persuasive', 'formal' ), true ) ? $tone : 'neutral',
					'length'           => in_array( $length, array( 'short', 'medium', 'long' ), true ) ? $length : 'medium',
					'channel'          => in_array( $channel, array( 'shop', 'marketplace' ), true ) ? $channel : 'shop',
					'target'           => $target,
					'meta_description' => in_array( 'meta_description', $fields, true ) ? 'yes' : 'no',
				);
			case 'seo_meta':
				return array( 'target' => $target );
			case 'product_photo':
				return array(
					'target'        => $target,
					'category_list' => 'free',
				);
		}
		return array();
	}

	/**
	 * Builds the entries of a batch from product ids.
	 *
	 * @param string $tool         Tool.
	 * @param int[]  $product_ids  Products.
	 * @param array  $fields       Chosen fields.
	 * @param array  $choice       Gallery and alt choices: gallery (bool), only_missing_alt (bool).
	 * @return array ['items' => [...], 'skipped' => [[label, reason], ...]]
	 */
	public static function items( $tool, array $product_ids, array $fields, array $choice = array() ) {
		$items   = array();
		$skipped = array();
		$seen    = array();
		foreach ( $product_ids as $pid ) {
			$product = wc_get_product( (int) $pid );
			if ( ! $product || $product->is_type( 'variation' ) ) {
				continue;
			}
			$pid   = $product->get_id();
			$label = $product->get_name();
			if ( ! current_user_can( 'edit_post', $pid ) ) {
				$skipped[] = array( $label, __( 'You may not edit this product.', 'do4u' ) );
				continue;
			}
			if ( 'alt_text' === $tool ) {
				$images = array( (int) $product->get_image_id() );
				if ( ! empty( $choice['gallery'] ) ) {
					$images = array_merge( $images, array_map( 'intval', $product->get_gallery_image_ids() ) );
				}
				$images = array_filter( $images );
				if ( ! $images ) {
					$skipped[] = array( $label, __( 'The product has no image.', 'do4u' ) );
				}
				foreach ( $images as $att ) {
					if ( isset( $seen[ $att ] ) ) {
						continue;
					}
					$seen[ $att ] = true;
					$alt          = (string) get_post_meta( $att, '_wp_attachment_image_alt', true );
					if ( ! empty( $choice['only_missing_alt'] ) && '' !== trim( $alt ) ) {
						continue;
					}
					if ( ! current_user_can( 'edit_post', $att ) ) {
						$skipped[] = array( $label, __( 'You may not edit this image.', 'do4u' ) );
						continue;
					}
					$url = self::image_url( $att );
					if ( '' === $url ) {
						$skipped[] = array( $label, __( 'The image has no web address.', 'do4u' ) );
						continue;
					}
					$file    = (string) get_attached_file( $att );
					$items[] = array(
						'kind'    => 'image',
						'id'      => $att,
						'product' => $pid,
						'image'   => $att,
						'label'   => $label,
						'row'     => array(
							'image_url' => $url,
							'text'      => self::clip( self::line( __( 'Product', 'do4u' ), $label ) . "\n" . self::line( __( 'Image title', 'do4u' ), get_the_title( $att ) ), self::MAX_CHARS['alt_text'] ),
							'filename'  => self::clean( wp_basename( '' !== $file ? $file : $url ) ),
						),
						'before'  => array( 'image_alt' => $alt ),
					);
				}
				continue;
			}
			$image = (int) $product->get_image_id();
			if ( 'product_photo' === $tool && ( ! $image || '' === self::image_url( $image ) ) ) {
				$skipped[] = array( $label, __( 'The product has no image.', 'do4u' ) );
				continue;
			}
			$item = array(
				'kind'    => 'product',
				'id'      => $pid,
				'product' => $pid,
				'image'   => $image,
				'label'   => $label,
				'row'     => array(),
				'before'  => array(),
			);
			switch ( $tool ) {
				case 'product_description':
					$item['row'] = array( 'text' => self::product_text( $product, self::MAX_CHARS[ $tool ] ) );
					break;
				case 'seo_meta':
					$item['row'] = array( 'text' => self::product_text( $product, self::MAX_CHARS[ $tool ] ) );
					$kw          = Do4u_Shop_Seo::focus_keyword( $pid );
					if ( '' !== $kw ) {
						$item['row']['focus_keyword'] = $kw;
					}
					break;
				case 'product_photo':
					$item['row'] = array(
						'image_url' => self::image_url( $image ),
						'text'      => self::product_text( $product, self::MAX_CHARS[ $tool ], false ),
					);
					break;
			}
			foreach ( $fields as $f ) {
				$item['before'][ $f ] = self::current( $item, $f );
			}
			$items[] = $item;
		}
		return array(
			'items'   => $items,
			'skipped' => $skipped,
		);
	}

	/**
	 * Public address of an image. Only http(s) addresses; do4u.eu loads them from outside.
	 *
	 * @param int $attachment_id Attachment.
	 * @return string
	 */
	public static function image_url( $attachment_id ) {
		$url = (string) wp_get_attachment_url( $attachment_id );
		return preg_match( '#^https?://#i', $url ) ? esc_url_raw( $url ) : '';
	}

	/**
	 * Facts of a product as "Label: value" lines, without empty lines, so do4u.eu reads them as one product.
	 *
	 * @param WC_Product $product     Product.
	 * @param int        $max         Longest text.
	 * @param bool       $with_texts  Whether existing description and short description are included.
	 * @return string
	 */
	public static function product_text( $product, $max, $with_texts = true ) {
		$l     = array();
		$l[]   = self::line( __( 'Product name', 'do4u' ), $product->get_name( 'edit' ) );
		$cats  = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
		$l[]   = self::line( __( 'Categories', 'do4u' ), is_array( $cats ) ? implode( ', ', $cats ) : '' );
		$attrs = array();
		foreach ( $product->get_attributes() as $attr ) {
			if ( ! $attr instanceof WC_Product_Attribute ) {
				continue;
			}
			$values = $attr->is_taxonomy() ? wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'names' ) ) : $attr->get_options();
			$values = array_filter( array_map( 'strval', (array) $values ), 'strlen' );
			if ( $values ) {
				$attrs[] = wc_attribute_label( $attr->get_name(), $product ) . ': ' . implode( ', ', $values );
			}
		}
		$l[] = self::line( __( 'Attributes', 'do4u' ), implode( '; ', $attrs ) );
		if ( $product->has_weight() ) {
			$l[] = self::line( __( 'Weight', 'do4u' ), wc_format_weight( $product->get_weight() ) );
		}
		if ( $product->has_dimensions() ) {
			$l[] = self::line( __( 'Dimensions', 'do4u' ), wc_format_dimensions( $product->get_dimensions( false ) ) );
		}
		$tags = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );
		$l[]  = self::line( __( 'Tags', 'do4u' ), is_array( $tags ) ? implode( ', ', $tags ) : '' );
		if ( $with_texts ) {
			$l[] = self::line( __( 'Current short description', 'do4u' ), $product->get_short_description( 'edit' ) );
			$l[] = self::line( __( 'Current description', 'do4u' ), $product->get_description( 'edit' ) );
		} else {
			$l[] = self::line( __( 'Known facts', 'do4u' ), $product->get_short_description( 'edit' ) );
		}
		/**
		 * Filters the product facts sent to do4u.eu (plain text, one "Label: value" per line, no empty lines).
		 *
		 * @param string     $text    Text.
		 * @param WC_Product $product Product.
		 */
		$text = (string) apply_filters( 'do4u_shop_product_text', implode( "\n", array_filter( $l, 'strlen' ) ), $product );
		$text = preg_replace( "/\n\s*\n+/", "\n", self::clean_multiline( $text ) );
		return self::clip( trim( (string) $text ), $max );
	}

	/**
	 * One "Label: value" line, or an empty string for an empty value.
	 *
	 * @param string $label Label.
	 * @param string $value Value, may contain HTML.
	 * @return string
	 */
	private static function line( $label, $value ) {
		$value = self::clean( (string) $value );
		return '' === $value ? '' : $label . ': ' . $value;
	}

	/**
	 * Plain single-line text: no tags, no shortcodes, no control characters, collapsed spaces.
	 *
	 * @param string $s Text.
	 * @return string
	 */
	public static function clean( $s ) {
		$s = wp_strip_all_tags( strip_shortcodes( (string) $s ) );
		$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', wp_check_invalid_utf8( $s, true ) );
		return trim( (string) preg_replace( '/\s+/u', ' ', $s ) );
	}

	/**
	 * Same as do4u.eu does with every cell: valid UTF-8, no control characters except line feed and tab.
	 *
	 * @param string $s Text.
	 * @return string
	 */
	private static function clean_multiline( $s ) {
		$s = str_replace( array( "\r\n", "\r" ), "\n", wp_check_invalid_utf8( (string) $s, true ) );
		return (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s );
	}

	/**
	 * Shortens a text to a number of characters.
	 *
	 * @param string $s   Text.
	 * @param int    $max Characters.
	 * @return string
	 */
	private static function clip( $s, $max ) {
		return mb_strlen( $s ) > $max ? rtrim( mb_substr( $s, 0, $max - 1 ) ) . '…' : $s;
	}

	/**
	 * Current value of a field of an entry.
	 *
	 * @param array  $item  Entry.
	 * @param string $field Field.
	 * @return string
	 */
	public static function current( array $item, $field ) {
		if ( 'image_alt' === $field ) {
			return empty( $item['image'] ) ? '' : (string) get_post_meta( (int) $item['image'], '_wp_attachment_image_alt', true );
		}
		$product = wc_get_product( (int) $item['product'] );
		if ( ! $product ) {
			return '';
		}
		switch ( $field ) {
			case 'name':
				return (string) $product->get_name( 'edit' );
			case 'description':
				return (string) $product->get_description( 'edit' );
			case 'short_description':
				return (string) $product->get_short_description( 'edit' );
			case 'seo_title':
			case 'meta_description':
				return Do4u_Shop_Seo::get( $product->get_id(), $field );
		}
		return '';
	}

	/**
	 * Fixed HTML from plain paragraphs and bullet points. Every piece of model text is escaped.
	 *
	 * @param string $text    Paragraphs, separated by empty lines.
	 * @param array  $bullets Bullet points.
	 * @return string
	 */
	public static function to_html( $text, array $bullets = array() ) {
		$html = '';
		foreach ( preg_split( '/\n\s*\n/', str_replace( array( "\r\n", "\r" ), "\n", (string) $text ) ) as $p ) {
			$p = trim( (string) preg_replace( '/\s+/u', ' ', $p ) );
			if ( '' !== $p ) {
				$html .= '<p>' . esc_html( $p ) . "</p>\n";
			}
		}
		$bullets = array_values( array_filter( array_map( array( self::class, 'clean' ), $bullets ), 'strlen' ) );
		if ( $bullets ) {
			$html .= "<ul>\n";
			foreach ( $bullets as $b ) {
				$html .= '<li>' . esc_html( $b ) . "</li>\n";
			}
			$html .= "</ul>\n";
		}
		return wp_kses_post( trim( $html ) );
	}

	/**
	 * Turns one row of the result file into suggestions for the fields.
	 *
	 * @param string $tool   Tool.
	 * @param array  $data   Row of the result file: column => value.
	 * @param array  $item   Entry of the batch.
	 * @param array  $fields Chosen fields.
	 * @return array ['status', 'error', 'credits', 'proposals' => [field => value], 'variants' => [...], 'flags' => [field => [codes]], 'notes' => [...]]
	 */
	public static function result( $tool, array $data, array $item, array $fields ) {
		$r = array(
			'status'    => sanitize_key( $data['do4u_status'] ?? 'failed' ),
			'error'     => sanitize_text_field( $data['do4u_error'] ?? '' ),
			'credits'   => absint( $data['do4u_credits'] ?? 0 ),
			'proposals' => array(),
			'variants'  => array(),
			'flags'     => array(),
			'notes'     => array(),
			'applied'   => array(),
			'discarded' => false,
		);
		if ( 'done' !== $r['status'] ) {
			return $r;
		}
		$bullets_on = (bool) Do4u_Shop_Settings::get( 'bullets' );
		$raw        = array();
		$numbers    = array();
		$long       = array();
		switch ( $tool ) {
			case 'product_description':
				$list = json_decode( (string) ( $data['do4u_products'] ?? '' ), true );
				$p    = is_array( $list ) && isset( $list[0] ) && is_array( $list[0] ) ? $list[0] : array();
				if ( ( $p['check'] ?? '' ) === 'missing' || ! $p ) {
					$r['status'] = 'failed';
					$r['error']  = __( 'do4u.eu could not write texts for this product.', 'do4u' );
					return $r;
				}
				$bul                      = array_filter( (array) ( $p['bullets'] ?? array() ), 'is_string' );
				$raw['name']              = (string) ( $p['title'] ?? '' );
				$raw['description']       = (string) ( $p['long'] ?? '' );
				$raw['short_description'] = (string) ( $p['short'] ?? '' );
				$raw['meta_description']  = (string) ( $p['meta'] ?? '' );
				$r['proposals']           = array(
					'name'              => self::clean( $raw['name'] ),
					'description'       => self::to_html( $raw['description'], $bullets_on ? $bul : array() ),
					'short_description' => self::to_html( self::clean( $raw['short_description'] ) ),
					'meta_description'  => self::clean( $raw['meta_description'] ),
				);
				$numbers = array_filter( array_map( array( self::class, 'clean' ), (array) ( $p['new_numbers'] ?? array() ) ), 'strlen' );
				if ( ( $p['title_status'] ?? '' ) === 'too_long' ) {
					$long[] = 'name';
				}
				if ( ( $p['meta_status'] ?? '' ) === 'too_long' ) {
					$long[] = 'meta_description';
				}
				break;
			case 'seo_meta':
				$variants = json_decode( (string) ( $data['do4u_variants'] ?? '' ), true );
				foreach ( is_array( $variants ) ? array_slice( $variants, 0, 3 ) : array() as $v ) {
					if ( ! is_array( $v ) ) {
						continue;
					}
					$t = self::clean( (string) ( $v['title'] ?? '' ) );
					$d = self::clean( (string) ( $v['description'] ?? '' ) );
					if ( '' === $t && '' === $d ) {
						continue;
					}
					$r['variants'][] = array(
						'seo_title'        => $t,
						'meta_description' => $d,
						'too_long'         => ( ( $v['title_status'] ?? '' ) === 'too_long' || ( $v['description_status'] ?? '' ) === 'too_long' ),
						'raw'              => (string) ( $v['title'] ?? '' ) . "\n" . (string) ( $v['description'] ?? '' ),
					);
				}
				if ( ! $r['variants'] ) {
					$r['status'] = 'failed';
					$r['error']  = __( 'do4u.eu sent no usable variant.', 'do4u' );
					return $r;
				}
				$r['proposals']          = array(
					'seo_title'        => $r['variants'][0]['seo_title'],
					'meta_description' => $r['variants'][0]['meta_description'],
				);
				$raw['seo_title']        = $r['variants'][0]['raw'];
				$raw['meta_description'] = $r['variants'][0]['raw'];
				break;
			case 'alt_text':
				$raw['image_alt']       = (string) ( $data['do4u_alt'] ?? '' );
				$r['proposals']['image_alt'] = self::clean( $raw['image_alt'] );
				if ( ( $data['do4u_alt_status'] ?? '' ) === 'too_long' ) {
					$long[] = 'image_alt';
				}
				break;
			case 'product_photo':
				$bul                      = array_filter( array_map( 'trim', explode( ' | ', (string) ( $data['do4u_bullets'] ?? '' ) ) ), 'strlen' );
				$raw['name']              = (string) ( $data['do4u_title'] ?? '' );
				$raw['description']       = (string) ( $data['do4u_description'] ?? '' );
				$raw['short_description'] = (string) ( $data['do4u_short_description'] ?? '' );
				$raw['image_alt']         = (string) ( $data['do4u_alt_text'] ?? '' );
				$r['proposals']           = array(
					'name'              => self::clean( $raw['name'] ),
					'description'       => self::to_html( $raw['description'], $bullets_on ? $bul : array() ),
					'short_description' => self::to_html( self::clean( $raw['short_description'] ) ),
					'image_alt'         => self::clean( $raw['image_alt'] ),
				);
				foreach ( array(
					'title_status'       => 'name',
					'short_status'       => 'short_description',
					'description_status' => 'description',
					'alt_status'         => 'image_alt',
				) as $col => $f ) {
					if ( ( $data[ 'do4u_' . $col ] ?? '' ) === 'too_long' ) {
						$long[] = $f;
					}
				}
				$removed = (string) ( $data['do4u_removed'] ?? '' );
				if ( '' !== $removed && '[]' !== $removed ) {
					$r['notes'][] = __( 'do4u.eu removed statements from the result that were not backed by your data.', 'do4u' );
				}
				break;
		}
		// Only the chosen fields, and only fields with a value.
		$r['proposals'] = array_filter(
			array_intersect_key( $r['proposals'], array_flip( $fields ) ),
			static function ( $v ) {
				return '' !== trim( wp_strip_all_tags( (string) $v ) );
			}
		);
		if ( ! $r['proposals'] ) {
			$r['status'] = 'failed';
			$r['error']  = __( 'do4u.eu sent no text for the chosen fields.', 'do4u' );
			return $r;
		}
		$input = implode( "\n", array_map( 'strval', $item['row'] ) ) . "\n" . implode( "\n", array_map( 'strval', $item['before'] ) );
		foreach ( array_keys( $r['proposals'] ) as $f ) {
			$flags = array();
			if ( $numbers ) {
				$flags[] = 'numbers';
			}
			if ( in_array( $f, $long, true ) ) {
				$flags[] = 'too_long';
			}
			if ( self::new_links( $raw[ $f ] ?? '', $input ) ) {
				$flags[] = 'link';
			}
			if ( preg_match( '/<\s*\/?\s*[a-z][^>]*>/i', $raw[ $f ] ?? '' ) ) {
				$flags[] = 'markup';
			}
			if ( $flags ) {
				$r['flags'][ $f ] = $flags;
			}
		}
		if ( $numbers ) {
			/* translators: %s: list of numbers */
			$r['notes'][] = sprintf( __( 'Numbers that are not in your product data: %s. Please check them before you apply.', 'do4u' ), implode( ', ', array_slice( $numbers, 0, 10 ) ) );
		}
		return $r;
	}

	/**
	 * Web addresses, domains or email addresses in a text that do not appear in the input.
	 *
	 * @param string $text  Model text.
	 * @param string $input What was sent.
	 * @return string[] New ones.
	 */
	public static function new_links( $text, $input ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( ! preg_match_all( '~(?:https?://|www\.)[^\s<>"\']+|[\p{L}\p{N}._%+-]+@[\p{L}\p{N}.-]+\.\p{L}{2,}|\b(?:[\p{L}\p{N}](?:[\p{L}\p{N}-]{0,61}[\p{L}\p{N}])?\.)+\p{L}{2,24}\b~iu', $text, $m ) ) {
			return array();
		}
		$new   = array();
		$input = mb_strtolower( (string) $input );
		foreach ( $m[0] as $hit ) {
			$hit = rtrim( $hit, '.,;:!?)' );
			if ( false === mb_strpos( $input, mb_strtolower( $hit ) ) ) {
				$new[] = $hit;
			}
		}
		return array_values( array_unique( $new ) );
	}

	/**
	 * Explanations of the flags.
	 *
	 * @return array
	 */
	public static function flag_labels() {
		return array(
			'numbers'  => __( 'contains numbers that are not in your product data', 'do4u' ),
			'too_long' => __( 'longer than the recommended limit', 'do4u' ),
			'link'     => __( 'contains a web address, domain or email address that is not in your product data', 'do4u' ),
			'markup'   => __( 'contained HTML, which was removed', 'do4u' ),
		);
	}
}
