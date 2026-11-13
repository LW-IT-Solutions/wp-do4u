<?php
/**
 * The places in wp-admin where a tool opens with the right content.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Knows, for each place, which tool opens, what goes into which form field and what can be taken over
 * from the result. Tools are only ever filled, never started: every job costs credits, and that
 * decision belongs to the person in front of the screen.
 *
 * Form field names are those of the do4u.eu forms. They differ from the API field names on purpose,
 * because the iframe shows exactly those forms.
 */
final class Do4u_Places {

	/** So many addresses does the do4u.eu newsletter form take by default; more would only produce an error there. */
	const NEWSLETTER_URLS = 8;

	/** Limit of the address list in that form, a little below the real one. */
	const NEWSLETTER_CHARS = 7800;

	/** do4u.eu keeps a note of a list line up to this length. */
	const NOTE_CHARS = 600;

	/** Size limit of the page list in the internal links form. */
	const PAGES_CHARS = 18000;

	/**
	 * All presets.
	 *
	 * @return array Preset id => tool, group, label, fill (form field => source), offers.
	 */
	public static function presets(): array {
		$as_excerpt = __( 'Insert as excerpt', 'do4u' );
		$as_title   = __( 'Insert as title', 'do4u' );
		$as_draft   = __( 'Save as new draft', 'do4u' );
		$replace    = __( 'Insert into the content', 'do4u' );
		$append     = __( 'Add to the end of the content', 'do4u' );
		$text_offer = static function ( string $path ) use ( $as_draft, $replace ): array {
			return array(
				array(
					'path'   => $path,
					'target' => 'draft',
					'format' => 'text',
					'label'  => $as_draft,
				),
				array(
					'path'   => $path,
					'target' => 'content',
					'format' => 'text',
					'label'  => $replace,
				),
			);
		};
		return array(
			'post_summary'     => array(
				'tool'   => 'summarize',
				'group'  => 'post',
				'label'  => __( 'Summarize', 'do4u' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => array(
					array(
						'path'   => 'summary',
						'target' => 'excerpt',
						'label'  => $as_excerpt,
					),
				),
			),
			'post_rewrite'     => array(
				'tool'   => 'rewrite',
				'group'  => 'post',
				'label'  => __( 'Rewrite', 'do4u' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => $text_offer( 'text' ),
			),
			'post_proofread'   => array(
				'tool'   => 'proofread',
				'group'  => 'post',
				'label'  => __( 'Proofread', 'do4u' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => $text_offer( 'text' ),
			),
			// HTML keeps headings and links. The title travels along as <h1> so that the draft gets a translated title too.
			'post_translate'   => array(
				'tool'   => 'translate_html',
				'group'  => 'post',
				'label'  => __( 'Translate', 'do4u' ),
				'fill'   => array( 'text' => 'html_title' ),
				'offers' => array(
					array(
						'path'        => 'html',
						'target'      => 'draft',
						'format'      => 'html',
						'split_title' => true,
						'label'       => $as_draft,
					),
				),
			),
			'post_seo'         => array(
				'tool'   => 'seo_meta',
				'group'  => 'post',
				'label'  => __( 'SEO title and description', 'do4u' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => self::seo_offers( $as_title, $as_excerpt ),
			),
			'post_headlines'   => array(
				'tool'   => 'headlines',
				'group'  => 'post',
				'label'  => __( 'Headlines', 'do4u' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => array(
					array(
						'path'   => 'variants.*.h1',
						'target' => 'title',
						'label'  => $as_title,
					),
					array(
						'path'   => 'variants.*.title',
						'target' => 'copy',
					),
				),
			),
			'post_faq'         => array(
				'tool'   => 'faq_schema',
				'group'  => 'post',
				'label'  => __( 'FAQ and schema', 'do4u' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => array(
					array(
						'path'    => 'html',
						'target'  => 'append',
						'format'  => 'html',
						'heading' => __( 'FAQ', 'do4u' ),
						'label'   => $append,
					),
					array(
						'path'    => 'jsonld',
						'target'  => 'copy',
						'heading' => __( 'Schema markup (JSON-LD)', 'do4u' ),
					),
				),
			),
			'post_social'      => array(
				'tool'   => 'social_posts',
				'group'  => 'post',
				'label'  => __( 'Social posts', 'do4u' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => array(
					array(
						'path'   => 'posts.*.text',
						'target' => 'copy',
					),
				),
			),
			'post_links'       => array(
				'tool'   => 'internal_links',
				'group'  => 'post',
				'label'  => __( 'Internal links', 'do4u' ),
				'fill'   => array(
					'text'       => 'plain',
					'seiten'     => 'pages',
					'eigene_url' => 'permalink',
				),
				'offers' => array(),
			),
			'post_terms'       => array(
				'tool'   => 'explain_terms',
				'group'  => 'post',
				'label'  => __( 'Explain terms', 'do4u' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => array(
					array(
						'path'   => 'html',
						'target' => 'append',
						'format' => 'html',
						'label'  => $append,
					),
				),
			),
			'post_level'       => array(
				'tool'   => 'language_level',
				'group'  => 'post',
				'label'  => __( 'Language level', 'do4u' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => $text_offer( 'text' ),
			),
			'post_a11y'        => array(
				'tool'   => 'accessibility_check',
				'group'  => 'post',
				'label'  => __( 'Check accessibility', 'do4u' ),
				'fill'   => array( 'text' => 'html' ),
				'offers' => array(),
			),
			'post_brief' => array(
				'tool'   => 'content_brief',
				'group'  => 'post',
				'label'  => Do4u_Catalog::title( 'content_brief' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => array(),
			),
			'post_inclusive' => array(
				'tool'   => 'inclusive_language',
				'group'  => 'post',
				'label'  => Do4u_Catalog::title( 'inclusive_language' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => array(),
			),
			'post_outdated' => array(
				'tool'   => 'outdated_content',
				'group'  => 'post',
				'label'  => Do4u_Catalog::title( 'outdated_content' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => array(),
			),
			'post_green' => array(
				'tool'   => 'green_claims_check',
				'group'  => 'post',
				'label'  => Do4u_Catalog::title( 'green_claims_check' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => array(),
			),
			'post_transparency' => array(
				'tool'   => 'ai_transparency_check',
				'group'  => 'post',
				'label'  => Do4u_Catalog::title( 'ai_transparency_check' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => array(),
			),
			'post_job' => array(
				'tool'   => 'job_ad_check',
				'group'  => 'post',
				'label'  => Do4u_Catalog::title( 'job_ad_check' ),
				'fill'   => array( 'text' => 'plain' ),
				'offers' => array(),
			),
			'posts_newsletter' => array(
				'tool'   => 'newsletter_create',
				'group'  => 'posts',
				'label'  => __( 'Create newsletter', 'do4u' ),
				'fill'   => array(
					'urls'  => 'post_urls',
					'haupt' => 'first_url',
				),
				'offers' => array(),
			),
			// A job of this server instead of the iframe, see Do4u_Jobs.
			'media_alt'        => array(
				'tool'   => 'alt_text',
				'mode'   => 'job',
				'group'  => 'attachment',
				'label'  => __( 'Alt text', 'do4u' ),
				'fill'   => array(),
				'offers' => array(
					array(
						'path'   => 'alt',
						'target' => 'alt',
						'label'  => __( 'Insert as alt text', 'do4u' ),
					),
				),
			),
			'comment_check'    => array(
				'tool'   => 'moderate_comment',
				'group'  => 'comment',
				'label'  => __( 'Check comment', 'do4u' ),
				'fill'   => array( 'text' => 'comment_text' ),
				'offers' => array(
					array(
						'path'   => 'suggestion',
						'target' => 'comment_reply',
						'label'  => __( 'Insert as reply', 'do4u' ),
					),
				),
			),
			'comment_reply'    => array(
				'tool'   => 'support_reply',
				'group'  => 'comment',
				'label'  => __( 'Suggest a reply', 'do4u' ),
				'fill'   => array( 'text' => 'comment_text' ),
				'offers' => array(
					array(
						'path'   => 'reply',
						'target' => 'comment_reply',
						'label'  => __( 'Insert as reply', 'do4u' ),
					),
				),
			),
			'comment_review'   => array(
				'tool'   => 'review_reply',
				'group'  => 'comment',
				'label'  => Do4u_Catalog::title( 'review_reply' ),
				'fill'   => array( 'text' => 'comment_text' ),
				'offers' => array(
					array(
						'path'   => 'variants.*.reply',
						'target' => 'comment_reply',
						'label'  => __( 'Insert as reply', 'do4u' ),
					),
				),
			),
			'post_press' => array(
				'tool'   => 'press_release',
				'group'  => 'post',
				'label'  => __( 'Press release from this post', 'do4u' ),
				'fill'   => array( 'facts' => 'plain', 'organisation' => 'site_name' ),
				'offers' => array(
					array(
						'path'        => 'draft_html',
						'target'      => 'draft',
						'format'      => 'html',
						'split_title' => true,
						'label'       => $as_draft,
					),
				),
			),
			'post_checklist' => array(
				'tool'   => 'checklist_from_text',
				'group'  => 'post',
				'label'  => __( 'Checklist from this post', 'do4u' ),
				'fill'   => array( 'document' => 'plain' ),
				'offers' => array(
					array(
						'path'   => 'draft_text',
						'target' => 'copy',
					),
				),
			),
			'post_questions' => array(
				'tool'   => 'document_questions',
				'group'  => 'post',
				'label'  => __( 'Ask questions to this post', 'do4u' ),
				'fill'   => array( 'document' => 'plain' ),
				'offers' => array(),
			),
			'post_contradictions' => array(
				'tool'   => 'contradiction_check',
				'group'  => 'post',
				'label'  => __( 'Find contradictions', 'do4u' ),
				'fill'   => array( 'document' => 'plain' ),
				'offers' => array(),
			),
			'create_job' => array(
				'tool'   => 'job_ad_create',
				'group'  => 'create',
				'label'  => __( 'Job advertisement', 'do4u' ),
				'fill'   => array(),
				'offers' => array(
					array(
						'path'        => 'draft_html',
						'target'      => 'new_draft',
						'format'      => 'html',
						'split_title' => true,
						'label'       => __( 'Save as new draft', 'do4u' ),
					),
				),
			),
			'create_invitation' => array(
				'tool'   => 'event_invitation',
				'group'  => 'create',
				'label'  => __( 'Event invitation', 'do4u' ),
				'fill'   => array(),
				'offers' => array(
					array(
						'path'        => 'draft_html',
						'target'      => 'new_draft',
						'format'      => 'html',
						'split_title' => true,
						'label'       => __( 'Save as new draft', 'do4u' ),
					),
				),
			),
			'create_profile' => array(
				'tool'   => 'company_profile',
				'group'  => 'create',
				'label'  => __( 'Company profile', 'do4u' ),
				'fill'   => array(),
				'offers' => array(
					array(
						'path'        => 'draft_html',
						'target'      => 'new_draft',
						'format'      => 'html',
						'split_title' => true,
						'label'       => __( 'Save as new draft', 'do4u' ),
					),
				),
			),
			'create_notice' => array(
				'tool'   => 'notice_board',
				'group'  => 'create',
				'label'  => __( 'Notice', 'do4u' ),
				'fill'   => array(),
				'offers' => array(
					array(
						'path'        => 'draft_html',
						'target'      => 'new_draft',
						'format'      => 'html',
						'split_title' => true,
						'label'       => __( 'Save as new draft', 'do4u' ),
					),
				),
			),
			'create_donation' => array(
				'tool'   => 'donation_appeal',
				'group'  => 'create',
				'label'  => __( 'Donation appeal', 'do4u' ),
				'fill'   => array(),
				'offers' => array(
					array(
						'path'        => 'draft_html',
						'target'      => 'new_draft',
						'format'      => 'html',
						'split_title' => true,
						'label'       => __( 'Save as new draft', 'do4u' ),
					),
				),
			),
			'create_release' => array(
				'tool'   => 'release_notes',
				'group'  => 'create',
				'label'  => __( 'Release notes', 'do4u' ),
				'fill'   => array(),
				'offers' => array(
					array(
						'path'        => 'draft_html',
						'target'      => 'new_draft',
						'format'      => 'html',
						'split_title' => true,
						'label'       => __( 'Save as new draft', 'do4u' ),
					),
				),
			),
			'site_check'       => array(
				'tool'   => '',
				'group'  => 'site',
				'label'  => __( 'Website checks', 'do4u' ),
				'fill'   => array(),
				'offers' => array(),
			),
			'product_text'     => array(
				'tool'   => 'product_description',
				'group'  => 'product',
				'label'  => __( 'Write product description', 'do4u' ),
				'fill'   => array( 'text' => 'product_block' ),
				'offers' => array(
					array(
						'path'    => 'products.0.title',
						'target'  => 'title',
						'heading' => __( 'Title', 'do4u' ),
						'label'   => $as_title,
					),
					array(
						'path'    => 'products.0.long',
						'target'  => 'product_content',
						'format'  => 'text',
						'heading' => __( 'Description', 'do4u' ),
						'label'   => __( 'Insert as product description', 'do4u' ),
					),
					array(
						'path'    => 'products.0.short',
						'target'  => 'product_short',
						'format'  => 'text',
						'heading' => __( 'Short description', 'do4u' ),
						'label'   => __( 'Insert as short description', 'do4u' ),
					),
				),
			),
		);
	}

	/**
	 * Website checks that accept text or HTML. They get the content from the WordPress database, so they
	 * also work where do4u.eu cannot read the site from outside: on the same server, behind a login or on staging.
	 *
	 * @return string[] Public tool names.
	 */
	public static function site_checks_local(): array {
		return array( 'accessibility_check', 'structured_data_check', 'ai_visibility_check', 'outdated_content', 'legal_notice_check', 'local_business_check', 'price_display_check', 'withdrawal_button_check', 'product_safety_check', 'form_text_check' );
	}

	/**
	 * Website checks that know nothing but an address: do4u.eu has to read the public page itself.
	 *
	 * @return string[] Public tool names.
	 */
	public static function site_checks_address(): array {
		return array( 'website_check', 'broken_link_check', 'image_performance_check', 'social_card_check', 'hreflang_check', 'duplicate_content_check', 'llms_txt', 'image_text', 'alt_pruefer' );
	}

	/**
	 * All tools that only work with a publicly reachable address.
	 *
	 * @return string[] Public tool names.
	 */
	public static function address_tools(): array {
		return self::site_checks_address();
	}

	/**
	 * The tools of the plugin and their place. Only tools with a use in WordPress belong here;
	 * WERKZEUGE.md gives the reason for every other do4u.eu tool.
	 *
	 * @return array Public name => group.
	 */
	public static function plugin_tools(): array {
		$tools = array();
		foreach ( self::presets() as $def ) {
			if ( '' !== $def['tool'] ) {
				$tools[ $def['tool'] ] = $def['group'];
			}
		}
		foreach ( array_merge( self::site_checks_local(), self::site_checks_address() ) as $name ) {
			$tools[ $name ] = $tools[ $name ] ?? 'site';
		}
		return $tools;
	}

	/**
	 * Note for tools that read a public address.
	 *
	 * @return string
	 */
	public static function address_note(): string {
		return __( 'This tool reads the page from its public address. The page must be reachable from outside; protected, internal and staging pages cannot be read.', 'do4u' );
	}

	/**
	 * Preset ids for the post editor.
	 *
	 * @return string[]
	 */
	public static function post_preset_ids(): array {
		$ids = array();
		foreach ( self::presets() as $id => $preset ) {
			if ( 'post' === $preset['group'] ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * What a click opens: tool, prefilled fields and possible takeovers. Checks the rights for the item,
	 * so that nobody can read content through the prefill that they could not open in WordPress.
	 *
	 * @param string $tool    Public tool name (ignored if the preset fixes it).
	 * @param string $preset  Preset id or empty.
	 * @param array  $context Keys type, id, ids.
	 * @return array Keys tool, title, fill, sources, offers, notices, object_id.
	 * @throws Do4u_Denied When the user may not use this item.
	 * @throws Do4u_Error  When tool, preset or item do not fit.
	 */
	public static function build( string $tool, string $preset, array $context ): array {
		$data = array(
			'fill'    => array(),
			'sources' => array(),
			'offers'  => array(),
			'notices' => array(),
		);
		if ( '' === $preset ) {
			if ( ! Do4u_Catalog::valid_name( $tool ) || 'site' !== ( self::plugin_tools()[ $tool ] ?? '' ) ) {
				throw new Do4u_Error( esc_html__( 'This tool is not available.', 'do4u' ), 'bad_tool', 400 );
			}
			if ( in_array( $tool, self::address_tools(), true ) ) {
				$data['notices'][] = self::address_note();
			}
			return $data + array(
				'mode'      => 'widget',
				'tool'      => $tool,
				'title'     => Do4u_Catalog::title( $tool ),
				'object_id' => 0,
			);
		}
		$presets = self::presets();
		if ( ! isset( $presets[ $preset ] ) ) {
			throw new Do4u_Error( esc_html__( 'This tool is not available.', 'do4u' ), 'bad_preset', 400 );
		}
		$def = $presets[ $preset ];
		if ( 'site_check' === $preset ) {
			$local = in_array( $tool, self::site_checks_local(), true );
			if ( ! $local && ! in_array( $tool, self::site_checks_address(), true ) ) {
				throw new Do4u_Error( esc_html__( 'This tool is not available.', 'do4u' ), 'bad_tool', 400 );
			}
			$def['tool'] = $tool;
			$def['fill'] = $local ? array( 'text' => 'site_html' ) : array( 'url' => 'site_url' );
			if ( ! $local ) {
				$data['notices'][] = self::address_note();
			}
		}
		$item   = self::resolve( $def['group'], $context );
		$values = self::sources( $item, $def['fill'] );
		foreach ( $def['fill'] as $field => $source ) {
			$data['sources'][ $field ] = $source;
			$data['fill'][ $field ]    = (string) ( $values[ $source ] ?? '' );
		}
		$data['offers']  = self::offers_for( $def['offers'], $item );
		$data['notices'] = array_merge( $data['notices'], $item['notices'] ?? array() );
		if ( isset( $item['selected'] ) ) {
			$data['selected'] = $item['selected'];
		}
		$mode = (string) ( $def['mode'] ?? 'widget' );
		if ( 'job' === $mode ) {
			$data['thumb']   = (string) wp_get_attachment_image_url( (int) $item['id'], 'medium' );
			$data['name']    = sanitize_text_field( (string) $item['post']->post_title );
			$data['current'] = (string) get_post_meta( (int) $item['id'], '_wp_attachment_image_alt', true );
			$data['price']   = Do4u_Catalog::price_of( $def['tool'] );
		}
		return $data + array(
			'mode'      => $mode,
			'tool'      => $def['tool'],
			'title'     => 'site_check' === $preset ? Do4u_Catalog::title( $tool ) : $def['label'],
			'object_id' => (int) ( $item['id'] ?? 0 ),
			'post_id'   => (int) ( $item['post_id'] ?? 0 ),
		);
	}

	/**
	 * Loads the item of a place and checks the rights.
	 *
	 * @param string $group   Group of the preset.
	 * @param array  $context Context from the request.
	 * @return array
	 * @throws Do4u_Denied When a right is missing.
	 * @throws Do4u_Error  When the item does not fit.
	 */
	private static function resolve( string $group, array $context ): array {
		$id = absint( $context['id'] ?? 0 );
		switch ( $group ) {
			case 'create':
				if ( ! current_user_can( 'edit_posts' ) ) {
					throw new Do4u_Denied( esc_html__( 'You cannot create this kind of item.', 'do4u' ), 'forbidden', 403 );
				}
				return array(
					'kind' => 'create',
					'id'   => 0,
				);
			case 'post':
			case 'product':
				$post = get_post( $id );
				if ( ! $post || ! current_user_can( 'edit_post', $id ) ) {
					throw new Do4u_Denied( esc_html__( 'You cannot edit this item.', 'do4u' ), 'forbidden', 403 );
				}
				$types = 'product' === $group ? array( 'product' ) : Do4u_Plugin::post_types();
				if ( ! in_array( $post->post_type, $types, true ) || 'trash' === $post->post_status ) {
					throw new Do4u_Error( esc_html__( 'This tool does not fit this item.', 'do4u' ), 'bad_item', 400 );
				}
				return array(
					'kind' => 'post',
					'id'   => $id,
					'post' => $post,
				);
			case 'posts':
				return self::resolve_posts( $context );
			case 'attachment':
				$post = get_post( $id );
				if ( ! $post || 'attachment' !== $post->post_type || ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $id ) ) {
					throw new Do4u_Denied( esc_html__( 'You cannot edit this item.', 'do4u' ), 'forbidden', 403 );
				}
				if ( ! wp_attachment_is_image( $id ) ) {
					throw new Do4u_Error( esc_html__( 'This tool does not fit this item.', 'do4u' ), 'bad_item', 400 );
				}
				return array(
					'kind' => 'attachment',
					'id'   => $id,
					'post' => $post,
				);
			case 'comment':
				$comment = get_comment( $id );
				if ( ! $comment || ! current_user_can( 'moderate_comments' ) || ! current_user_can( 'edit_comment', $id ) ) {
					throw new Do4u_Denied( esc_html__( 'You cannot moderate this comment.', 'do4u' ), 'forbidden', 403 );
				}
				return array(
					'kind'    => 'comment',
					'id'      => $id,
					'post_id' => (int) $comment->comment_post_ID,
					'comment' => $comment,
				);
			case 'site':
				// A chosen page may be read like any other item; the home page is public anyway.
				if ( $id <= 0 ) {
					return array(
						'kind' => 'site',
						'post' => null,
					);
				}
				$post = get_post( $id );
				if ( ! $post || ! current_user_can( 'edit_post', $id ) ) {
					throw new Do4u_Denied( esc_html__( 'You cannot edit this item.', 'do4u' ), 'forbidden', 403 );
				}
				return array(
					'kind' => 'site',
					'id'   => $id,
					'post' => $post,
				);
		}
		throw new Do4u_Error( esc_html__( 'This tool does not fit this item.', 'do4u' ), 'bad_item', 400 );
	}

	/**
	 * Picks the posts for a newsletter. A reader needs a working link, so only published posts without a
	 * password go in, and those are public anyway: no edit right is needed. The others are named in a
	 * notice, and nothing of their content is read.
	 *
	 * @param array $context Keys ids and main.
	 * @return array
	 */
	private static function resolve_posts( array $context ): array {
		$ids     = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) ( $context['ids'] ?? array() ) ) ) ) ), 0, 100 );
		$main    = absint( $context['main'] ?? 0 );
		$posts   = array();
		$skipped = 0;
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || ! in_array( $post->post_type, Do4u_Plugin::post_types(), true ) || 'publish' !== $post->post_status || '' !== $post->post_password ) {
				++$skipped;
				continue;
			}
			$posts[ $id ] = $post;
		}
		$notices = array();
		if ( $skipped > 0 ) {
			$notices[] = sprintf(
				/* translators: %d: number of posts */
				_n( '%d item was left out because it is not public: a newsletter needs links that work for your readers.', '%d items were left out because they are not public: a newsletter needs links that work for your readers.', $skipped, 'do4u' ),
				$skipped
			);
		}
		// The main article first, the others in the order chosen.
		if ( isset( $posts[ $main ] ) ) {
			$posts = array( $main => $posts[ $main ] ) + $posts;
		}
		if ( count( $posts ) > self::NEWSLETTER_URLS ) {
			$notices[] = sprintf(
				/* translators: %d: number of addresses */
				__( 'Only the first %d addresses were taken over.', 'do4u' ),
				self::NEWSLETTER_URLS
			);
			$posts = array_slice( $posts, 0, self::NEWSLETTER_URLS, true );
		}
		return array(
			'kind'     => 'posts',
			'posts'    => $posts,
			'notices'  => $notices,
			'selected' => array_values( array_map( array( self::class, 'picker_item' ), $posts ) ),
		);
	}

	/**
	 * A post in the newsletter selection.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public static function picker_item( $post ): array {
		$type = get_post_type_object( $post->post_type );
		return array(
			'id'    => (int) $post->ID,
			'title' => '' !== trim( (string) $post->post_title ) ? wp_strip_all_tags( (string) $post->post_title ) : __( '(no title)', 'do4u' ),
			'type'  => $type ? (string) $type->labels->singular_name : (string) $post->post_type,
			'date'  => (string) mysql2date( (string) get_option( 'date_format' ), (string) $post->post_date ),
		);
	}

	/**
	 * Lines for the address list of the newsletter form: "address | note | image". Note and image come from
	 * the database. do4u.eu keeps an article with its note even if it cannot read the page, takes the link
	 * from the list and shows the image in the mail; an image without https or on an internal address it
	 * drops by itself. That is what makes a newsletter possible on a site do4u.eu cannot reach from outside.
	 *
	 * @param WP_Post[] $posts Posts.
	 * @return string
	 */
	private static function newsletter_lines( array $posts ): string {
		$lines = array();
		$space = (int) floor( self::NEWSLETTER_CHARS / max( 1, count( $posts ) ) );
		foreach ( $posts as $post ) {
			$url   = (string) get_permalink( $post->ID );
			$image = (string) get_the_post_thumbnail_url( $post->ID, 'medium_large' );
			$rest  = $space - strlen( $url ) - 3 - ( '' !== $image ? strlen( $image ) + 3 : 0 );
			$line  = $url . ' | ' . self::newsletter_note( $post, max( 80, $rest ) );
			if ( '' !== $image ) {
				$line .= ' | ' . $image;
			}
			$lines[] = $line;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Note of one article: title and excerpt, on one line. No vertical bar, because it separates the columns.
	 *
	 * @param WP_Post $post Post.
	 * @param int     $max  Length.
	 * @return string
	 */
	public static function newsletter_note( $post, int $max ): string {
		$title = sanitize_text_field( (string) $post->post_title );
		$text  = '' !== trim( (string) $post->post_excerpt ) ? wp_strip_all_tags( (string) $post->post_excerpt ) : self::plain_text( (string) $post->post_content );
		$note  = '' !== trim( $text ) ? $title . ': ' . $text : $title;
		$note  = trim( (string) preg_replace( '/\s+/u', ' ', str_replace( '|', ' ', $note ) ) );
		return mb_substr( $note, 0, min( $max, self::NOTE_CHARS ) );
	}

	/**
	 * A page as a small HTML document, built from the database. Nothing is requested from the own website,
	 * and neither the_content nor do_blocks runs: they would execute shortcodes and the code of other plugins,
	 * and the result could contain what only certain visitors may see. So theme and dynamic blocks are not in it.
	 *
	 * @param WP_Post|null $post Chosen page, or null for the home page.
	 * @return string
	 */
	public static function site_html( $post ): string {
		$page = $post;
		if ( ! $page && 'page' === get_option( 'show_on_front' ) ) {
			$front = get_post( (int) get_option( 'page_on_front' ) );
			$page  = $front && 'publish' === $front->post_status && '' === $front->post_password ? $front : null;
		}
		$title = sanitize_text_field( (string) ( $page ? $page->post_title : get_bloginfo( 'name' ) ) );
		$body  = '';
		if ( $page ) {
			$body = wp_kses_post( self::clean_html( (string) $page->post_content ) );
		} else {
			$latest = get_posts(
				array(
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'has_password' => false,
					'numberposts'  => 10,
				)
			);
			foreach ( $latest as $item ) {
				$body .= '<h2><a href="' . esc_url( (string) get_permalink( $item->ID ) ) . '">' . esc_html( (string) $item->post_title ) . "</a></h2>\n" . wp_kses_post( self::clean_html( (string) $item->post_content ) ) . "\n";
			}
		}
		return '<!doctype html><html lang="' . esc_attr( (string) get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><title>' . esc_html( $title ) . '</title></head><body><h1>' . esc_html( $title ) . "</h1>\n" . $body . '</body></html>';
	}

	/**
	 * Computes the values the fill map asks for.
	 *
	 * @param array $item Result of resolve().
	 * @param array $fill Form field => source.
	 * @return array Source => value.
	 */
	private static function sources( array $item, array $fill ): array {
		$values = array();
		foreach ( array_unique( array_values( $fill ) ) as $source ) {
			$values[ $source ] = self::source( $source, $item );
		}
		return $values;
	}

	/**
	 * One value for a form field.
	 *
	 * @param string $source Name of the source.
	 * @param array  $item   Result of resolve().
	 * @return string
	 */
	private static function source( string $source, array $item ): string {
		$post = $item['post'] ?? null;
		switch ( $source ) {
			case 'plain':
				return self::plain_text( (string) $post->post_content );
			case 'site_name':
				return wp_strip_all_tags( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
			case 'html':
				return self::clean_html( (string) $post->post_content );
			case 'html_title':
				return '<h1>' . esc_html( $post->post_title ) . "</h1>\n" . self::clean_html( (string) $post->post_content );
			case 'permalink':
				return 'publish' === $post->post_status && '' === $post->post_password ? (string) get_permalink( $post->ID ) : '';
			case 'pages':
				return self::page_list( (int) $post->ID );
			case 'post_urls':
				return self::newsletter_lines( $item['posts'] );
			case 'first_url':
				$first = reset( $item['posts'] );
				return $first ? (string) get_permalink( $first->ID ) : '';
			case 'comment_text':
				return trim( wp_strip_all_tags( (string) $item['comment']->comment_content ) );
			case 'site_html':
				return self::site_html( $post );
			case 'site_url':
				return $post && 'publish' === $post->post_status && '' === $post->post_password ? (string) get_permalink( $post->ID ) : (string) home_url( '/' );
			case 'product_block':
				return self::product_block( $post );
		}
		return '';
	}

	/**
	 * Drops takeovers the post type has no field for, such as the excerpt on pages.
	 *
	 * @param array $offers Offers of the preset.
	 * @param array $item   Result of resolve().
	 * @return array
	 */
	private static function offers_for( array $offers, array $item ): array {
		$post = $item['post'] ?? null;
		if ( ! $post ) {
			return $offers;
		}
		$result = array();
		foreach ( $offers as $offer ) {
			if ( 'excerpt' === $offer['target'] && ! post_type_supports( $post->post_type, 'excerpt' ) ) {
				$offer['target'] = 'copy';
				unset( $offer['label'] );
			}
			$result[] = $offer;
		}
		return $result;
	}

	/**
	 * Saved content without block comments and shortcodes. Shortcodes are removed, never run: running
	 * them could expose whatever another plugin outputs at this point.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function clean_html( string $content ): string {
		$content = (string) preg_replace( '/<!--\s*\/?wp:.*?-->/s', '', $content );
		return trim( strip_shortcodes( $content ) );
	}

	/**
	 * Post content as plain text with paragraph breaks.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function plain_text( string $content ): string {
		$html = self::clean_html( $content );
		$html = (string) preg_replace( '#</(p|div|h[1-6]|li|blockquote|pre|tr|figure|section|article|ul|ol|table)>|<br\s*/?>#i', "$0\n\n", $html );
		$text = html_entity_decode( wp_strip_all_tags( $html, false ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( "/\n{3,}/", "\n\n", (string) preg_replace( "/[ \t]*\n[ \t]*/", "\n", $text ) ) );
	}

	/**
	 * Public posts and pages of the site in the format of the internal links form: address, then title.
	 *
	 * @param int $exclude Post that is being edited.
	 * @return string One page per line.
	 */
	private static function page_list( int $exclude ): string {
		$lines  = '';
		$others = get_posts(
			array(
				'post_type'        => Do4u_Plugin::post_types(),
				'post_status'      => 'publish',
				'has_password'     => false,
				'numberposts'      => 200,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);
		foreach ( $others as $other ) {
			if ( (int) $other->ID === (int) $exclude ) {
				continue;
			}
			$line = (string) get_permalink( $other->ID ) . ' ' . sanitize_text_field( (string) $other->post_title ) . "\n";
			if ( strlen( $lines ) + strlen( $line ) > self::PAGES_CHARS ) {
				break;
			}
			$lines .= $line;
		}
		return trim( $lines );
	}

	/**
	 * Product data as one block of the product description form: only facts that are already in the shop.
	 *
	 * @param WP_Post $post Product.
	 * @return string
	 */
	private static function product_block( $post ): string {
		$lines = array( sanitize_text_field( (string) $post->post_title ) );
		foreach ( array( (string) $post->post_excerpt, (string) $post->post_content ) as $part ) {
			$text = self::plain_text( $part );
			if ( '' !== $text ) {
				$lines[] = $text;
			}
		}
		$terms = get_the_terms( $post->ID, 'product_cat' );
		if ( is_array( $terms ) && $terms ) {
			$lines[] = implode( ', ', wp_list_pluck( $terms, 'name' ) );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Active SEO plugins that have their own title and description.
	 *
	 * @return array Map of slug to display name.
	 */
	public static function seo_plugins(): array {
		$found = array();
		if ( defined( 'WPSEO_VERSION' ) ) {
			$found['yoast'] = 'Yoast SEO';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$found['rank_math'] = 'Rank Math';
		}
		return $found;
	}

	/**
	 * Post meta keys of the active SEO plugins for a target.
	 *
	 * @param string $target seo_title or seo_description.
	 * @return array List of meta keys.
	 */
	public static function seo_meta_keys( string $target ): array {
		$map  = array(
			'yoast'     => array(
				'seo_title'       => '_yoast_wpseo_title',
				'seo_description' => '_yoast_wpseo_metadesc',
			),
			'rank_math' => array(
				'seo_title'       => 'rank_math_title',
				'seo_description' => 'rank_math_description',
			),
		);
		$keys = array();
		foreach ( array_keys( self::seo_plugins() ) as $slug ) {
			if ( isset( $map[ $slug ][ $target ] ) ) {
				$keys[] = $map[ $slug ][ $target ];
			}
		}
		return $keys;
	}

	/**
	 * Where the SEO tool can put its result: the fields of an active SEO plugin, otherwise title and excerpt.
	 *
	 * @param string $as_title   Label for the post title.
	 * @param string $as_excerpt Label for the excerpt.
	 * @return array Offers.
	 */
	private static function seo_offers( string $as_title, string $as_excerpt ): array {
		$names = implode( ' + ', self::seo_plugins() );
		if ( '' === $names ) {
			return array(
				array(
					'path'    => 'variants.*.title',
					'target'  => 'title',
					'heading' => __( 'SEO title', 'do4u' ),
					'label'   => $as_title,
				),
				array(
					'path'    => 'variants.*.description',
					'target'  => 'excerpt',
					'heading' => __( 'Meta description', 'do4u' ),
					'label'   => $as_excerpt,
				),
			);
		}
		return array(
			array(
				'path'    => 'variants.*.title',
				'target'  => 'seo_title',
				'heading' => __( 'SEO title', 'do4u' ),
				/* translators: %s: name of the SEO plugin, for example Yoast SEO */
				'label'   => sprintf( __( 'Use as SEO title in %s', 'do4u' ), $names ),
			),
			array(
				'path'    => 'variants.*.description',
				'target'  => 'seo_description',
				'heading' => __( 'Meta description', 'do4u' ),
				/* translators: %s: name of the SEO plugin, for example Yoast SEO */
				'label'   => sprintf( __( 'Use as meta description in %s', 'do4u' ), $names ),
			),
		);
	}
}
