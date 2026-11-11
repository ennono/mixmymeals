<?php
/**
 * Mailer class file.
 *
 * @package MixMyMeals Plugin/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Mailer
 *
 * Thin, security- and GDPR-conscious wrapper around WordPress' own wp_mail().
 *
 * It does not register any hooks on load and holds no state, so including the
 * file has no side effects — nothing is sent until Mixmymeals_Mailer::send()
 * is called explicitly.
 *
 * Security measures:
 *  - Recipient, Cc, Bcc and Reply-To addresses are validated with is_email()
 *    and sanitized; invalid ones are dropped.
 *  - Subject and every header value are stripped of CR/LF to prevent header
 *    (e-mail) injection.
 *  - HTML bodies are run through wp_kses_post() by default.
 *  - Attachments must be existing files inside an allowed base directory
 *    (the uploads directory by default); arbitrary server paths are rejected.
 *  - The From identity is passed as a per-message header, so no global
 *    wp_mail_* filters are touched and other senders are unaffected.
 *
 * GDPR measures:
 *  - Privacy by default: with several recipients each one receives a separate
 *    message, so addresses are never disclosed to other recipients.
 *  - A suppression (opt-out) list is honored for every non-forced send.
 *  - A clear From identity (controller) is always set.
 *  - No open/click tracking and no external resources are added.
 *  - An optional List-Unsubscribe header and a configurable footer (imprint /
 *    controller / unsubscribe note) support the right to object.
 *  - Logging is opt-in (WP_DEBUG), address-masked and never stores the body.
 *
 * Typical use (later):
 *   Mixmymeals_Mailer::send( $to, $subject, $body, array( 'html' => true ) );
 */
class Mixmymeals_Mailer {

	/**
	 * Option holding the configured sender e-mail address.
	 *
	 * @var string
	 */
	const OPTION_FROM_EMAIL = 'mixmymeals_mail_from_email';

	/**
	 * Option holding the configured sender name.
	 *
	 * @var string
	 */
	const OPTION_FROM_NAME = 'mixmymeals_mail_from_name';

	/**
	 * Option holding the default footer (controller / imprint / opt-out note).
	 *
	 * @var string
	 */
	const OPTION_FOOTER = 'mixmymeals_mail_footer';

	/**
	 * Option holding the suppression (opt-out) list as an array of addresses.
	 *
	 * @var string
	 */
	const OPTION_SUPPRESSION = 'mixmymeals_mail_suppression_list';

	/**
	 * Hard upper bound of recipients accepted in a single send() call.
	 *
	 * @var int
	 */
	const MAX_RECIPIENTS = 50;

	/**
	 * Not instantiable — this is a stateless service.
	 */
	private function __construct() {
	}

	/**
	 * Sends an e-mail through wp_mail() with security and privacy safeguards.
	 *
	 * Supported $args keys:
	 *  - 'html'          bool   Treat $message as HTML (sanitized). Default false.
	 *  - 'from_email'    string Override sender address.
	 *  - 'from_name'     string Override sender name.
	 *  - 'reply_to'      string Reply-To address.
	 *  - 'cc'            array  Additional Cc addresses.
	 *  - 'bcc'           array  Additional Bcc addresses.
	 *  - 'headers'       array  Extra raw headers (CR/LF stripped).
	 *  - 'attachments'   array  Absolute file paths inside the allowed base dir.
	 *  - 'transactional' bool   Transactional mail (e.g. account / shopping list
	 *                           the user requested). Default true. Non-transactional
	 *                           mail gets the footer appended automatically.
	 *  - 'append_footer' bool   Force append/skip the footer regardless of type.
	 *  - 'unsubscribe_url' string URL/mailto for the List-Unsubscribe header.
	 *  - 'force'         bool   Ignore the suppression list (use only for legally
	 *                           required transactional mail). Default false.
	 *  - 'trusted_html'  bool   Skip wp_kses_post() on the body. Only for
	 *                           developer-authored template markup whose dynamic
	 *                           values were already escaped (see send_template()).
	 *                           Default false.
	 *
	 * @param string|string[] $to      One or more recipient addresses.
	 * @param string          $subject Subject line.
	 * @param string          $message Body (plain text, or HTML if args[html]).
	 * @param array           $args    Optional settings (see above).
	 *
	 * @return bool True if every accepted recipient was handed to wp_mail()
	 *              successfully; false if there was no valid recipient or any
	 *              delivery attempt failed.
	 */
	public static function send( $to, string $subject, string $message, array $args = array() ): bool {

		$defaults = array(
			'html'            => false,
			'from_email'      => '',
			'from_name'       => '',
			'reply_to'        => '',
			'cc'              => array(),
			'bcc'             => array(),
			'headers'         => array(),
			'attachments'     => array(),
			'transactional'   => true,
			'append_footer'   => null,
			'unsubscribe_url' => '',
			'force'           => false,
			'trusted_html'    => false,
		);

		$args = wp_parse_args( $args, $defaults );

		$is_html = (bool) $args['html'];
		$force   = (bool) $args['force'];

		// 1. Recipients: validate, de-duplicate and drop opted-out addresses.
		$recipients = self::normalize_recipients( $to, $force );

		if ( empty( $recipients ) ) {
			self::log( 'No valid (non-suppressed) recipient for subject: ' . self::sanitize_header_value( $subject ) );
			return false;
		}

		// 2. Subject: collapse newlines, strip control characters.
		$subject = self::sanitize_subject( $subject );

		// 3. Body: sanitize HTML, optionally append the GDPR footer.
		$append_footer = is_null( $args['append_footer'] )
			? ! (bool) $args['transactional']
			: (bool) $args['append_footer'];

		$body = self::prepare_body( $message, $is_html, $append_footer, (bool) $args['trusted_html'] );

		// 4. Attachments: keep only existing files inside the allowed base dir.
		$attachments = self::validate_attachments( (array) $args['attachments'] );

		// 5. Common headers (From, Reply-To, Cc, Content-Type, List-Unsubscribe).
		$base_headers = self::build_headers( $args, $is_html );

		$all_sent = true;

		// 6. Send one message per recipient (no address disclosure between them).
		foreach ( $recipients as $recipient ) {

			if ( ! self::passes_rate_limit( $recipient ) ) {
				self::log( 'Rate limit reached for ' . self::mask_email( $recipient ) );
				$all_sent = false;
				continue;
			}

			$headers = self::merge_bcc( $base_headers, (array) $args['bcc'] );

			/**
			 * Filters the wp_mail() arguments right before sending.
			 *
			 * @param array  $mail      Associative array (to, subject, message, headers, attachments).
			 * @param string $recipient The current recipient address.
			 */
			$mail = apply_filters(
				'mixmymeals_mail_args',
				array(
					'to'          => $recipient,
					'subject'     => $subject,
					'message'     => $body,
					'headers'     => $headers,
					'attachments' => $attachments,
				),
				$recipient
			);

			$sent = wp_mail(
				$mail['to'],
				$mail['subject'],
				$mail['message'],
				$mail['headers'],
				$mail['attachments']
			);

			if ( $sent ) {
				self::record_send( $recipient );
				/**
				 * Fires after a message was handed to wp_mail() successfully.
				 *
				 * @param string $masked_recipient Address-masked recipient.
				 * @param string $subject          The (sanitized) subject.
				 */
				do_action( 'mixmymeals_mail_sent', self::mask_email( $recipient ), $subject );
			} else {
				$all_sent = false;
				self::log( 'wp_mail() failed for ' . self::mask_email( $recipient ) );
				/**
				 * Fires when wp_mail() reported a failure for a recipient.
				 *
				 * @param string $masked_recipient Address-masked recipient.
				 * @param string $subject          The (sanitized) subject.
				 */
				do_action( 'mixmymeals_mail_failed', self::mask_email( $recipient ), $subject );
			}

			// Send log (bounded ring buffer; no-op when disabled / unavailable).
			if ( class_exists( 'Mixmymeals_Mail_Log' ) ) {
				Mixmymeals_Mail_Log::record( $recipient, $subject, (bool) $sent, (bool) $args['transactional'] );
			}
		}

		return $all_sent;
	}

	/*
	----------------------------------------------------------
	 * TEMPLATES & PLACEHOLDERS
	 * ---------------------------------------------------------- */

	/**
	 * Renders a template and sends it through send().
	 *
	 * The template body is developer-authored markup; only the substituted
	 * placeholder values are escaped (see render()), so the assembled body is
	 * handed to send() as trusted HTML (no wp_kses_post() that would strip a
	 * full HTML document / <style> blocks).
	 *
	 * Placeholders use the form {{ key }} (whitespace optional). For HTML
	 * templates each value is escaped by its key suffix: keys ending in "_url"
	 * via esc_url(), "_html" via wp_kses_post() (trusted rich text), everything
	 * else via esc_html().
	 *
	 * In addition to the standard send() $args, these keys are supported:
	 *  - 'inline' bool   Treat $template as raw markup instead of a file name.
	 *  - 'layout' string Wrap the rendered body into a layout template whose
	 *                    {{ content }} token receives the body verbatim.
	 *  - 'html'   bool   Force HTML/plain handling (otherwise derived from the
	 *                    file extension; inline defaults to HTML).
	 *
	 * @param string|string[] $to       Recipient address(es).
	 * @param string          $subject  Subject line (may contain placeholders).
	 * @param string          $template Template file name (e.g. "welcome.html")
	 *                                  or raw markup when args[inline] is true.
	 * @param array           $vars     Placeholder values (merged over defaults).
	 * @param array           $args     send() args plus inline/layout/html.
	 *
	 * @return bool See send(); false if the template could not be loaded.
	 */
	public static function send_template( $to, string $subject, string $template, array $vars = array(), array $args = array() ): bool {

		$vars = array_merge( self::default_vars(), $vars );

		// Build a ready-to-render unsubscribe note from whichever unsubscribe URL
		// is available, so every layout can surface a visible opt-out link without
		// emitting an empty link in mails that have none (e.g. confirmations).
		if ( empty( $vars['unsubscribe_html'] ) ) {
			$unsub_url = '';
			if ( ! empty( $vars['unsubscribe_url'] ) ) {
				$unsub_url = (string) $vars['unsubscribe_url'];
			} elseif ( ! empty( $args['unsubscribe_url'] ) ) {
				$unsub_url = (string) $args['unsubscribe_url'];
			}

			$vars['unsubscribe_html'] = '' !== $unsub_url
				? sprintf(
					'<p><a href="%s">%s</a></p>',
					esc_url( $unsub_url ),
					esc_html__( 'Unsubscribe from these e-mails', 'mixmymeals' )
				)
				: '';
		}

		$inline = ! empty( $args['inline'] );
		$raw    = $inline ? $template : self::load_template( $template );

		if ( '' === trim( $raw ) ) {
			self::log( 'Template not found or empty: ' . ( $inline ? '(inline)' : $template ) );
			return false;
		}

		$is_html = array_key_exists( 'html', $args )
			? (bool) $args['html']
			: self::is_html_template( $template, $inline );

		$layout = isset( $args['layout'] ) ? (string) $args['layout'] : '';

		if ( $is_html && '' !== $layout ) {

			$inner = self::render( $raw, $vars, true );

			// Optional admin-defined block appended to the content (e.g. a legal
			// note on every subscription mail). Already-sanitized trusted HTML.
			if ( ! empty( $args['append_html'] ) ) {
				$inner .= (string) $args['append_html'];
			}

			$shell = self::load_layout( $layout, $inline ? '' : $template );

			if ( '' !== trim( $shell ) ) {
				// Inject the trusted inner body verbatim into {{ content }} first,
				// then render the layout's own placeholders.
				$shell = preg_replace_callback(
					'/\{\{\s*content\s*\}\}/',
					static function () use ( $inner ) {
						return $inner;
					},
					$shell
				);
				$body  = self::render( $shell, $vars, true );

				// A layout usually carries its own footer.
				if ( ! array_key_exists( 'append_footer', $args ) ) {
					$args['append_footer'] = false;
				}
			} else {
				self::log( 'Layout not found: ' . $layout );
				$body = $inner;
			}
		} else {
			$body = self::render( $raw, $vars, $is_html );

			if ( $is_html && ! empty( $args['append_html'] ) ) {
				$body .= (string) $args['append_html'];
			}
		}

		$subject = self::render( $subject, $vars, false );

		$args['html']         = $is_html;
		$args['trusted_html'] = $is_html;

		unset( $args['inline'], $args['layout'], $args['append_html'] );

		return self::send( $to, $subject, $body, $args );
	}

	/**
	 * Replaces {{ key }} placeholders in a string with values from $vars.
	 *
	 * For HTML output each value is escaped depending on its key suffix:
	 * "_url" → esc_url(), "_html" → wp_kses_post(), otherwise esc_html().
	 * Non-scalar values are skipped and unknown placeholders are removed.
	 *
	 * @param string $content Template content.
	 * @param array  $vars    Placeholder values.
	 * @param bool   $is_html Whether the output is HTML (enables escaping).
	 *
	 * @return string Rendered content.
	 */
	public static function render( string $content, array $vars, bool $is_html = true ): string {

		$map = array();

		foreach ( $vars as $key => $value ) {

			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}

			$key   = (string) $key;
			$value = (string) $value;

			if ( $is_html ) {
				if ( str_ends_with( $key, '_html' ) ) {
					$value = wp_kses_post( $value );
				} elseif ( str_ends_with( $key, '_url' ) ) {
					$value = esc_url( $value );
				} else {
					$value = esc_html( $value );
				}
			}

			$map[ $key ] = $value;
		}

		return (string) preg_replace_callback(
			'/\{\{\s*([A-Za-z0-9_.\-]+)\s*\}\}/',
			static function ( array $m ) use ( $map ) {
				return array_key_exists( $m[1], $map ) ? $map[ $m[1] ] : '';
			},
			$content
		);
	}

	/**
	 * Loads a template file's raw contents (no PHP execution).
	 *
	 * @param string $name Template file name (sanitized).
	 *
	 * @return string File contents, or '' if not found.
	 */
	public static function load_template( string $name ): string {

		$file = self::locate_template( $name );

		if ( '' === $file ) {
			return '';
		}

		// Local, developer-authored template; read as text, placeholders are
		// substituted afterwards in render().
		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return is_string( $contents ) ? $contents : '';
	}

	/**
	 * Loads the layout for a template. A template in a subfolder prefers a
	 * layout.html from that same subfolder; if there is none it falls back to the
	 * base emails folder, and finally the plugin default (each resolution still
	 * honours the theme → plugin override order).
	 *
	 * @param string $layout   Layout file name (e.g. "layout.html").
	 * @param string $template Selected template path ('' for inline markup).
	 * @return string Layout contents, or '' if none found.
	 */
	private static function load_layout( string $layout, string $template ): string {

		if ( '' === $layout ) {
			return '';
		}

		if ( '' !== $template ) {
			$sub = trim( str_replace( '\\', '/', dirname( $template ) ), '/' );
			if ( '' !== $sub && '.' !== $sub ) {
				$shell = self::load_template( $sub . '/' . $layout );
				if ( '' !== trim( $shell ) ) {
					return $shell;
				}
			}
		}

		return self::load_template( $layout );
	}

	/**
	 * Resolves a template file path, allowing theme overrides at
	 * <theme>/mixmymeals/emails/<name> before falling back to the plugin.
	 *
	 * @param string $name Template file name.
	 *
	 * @return string Absolute path, or '' if not found / invalid.
	 */
	public static function locate_template( string $name ): string {

		$name = self::sanitize_template_name( $name );

		if ( '' === $name ) {
			return '';
		}

		$theme = locate_template( array( 'mixmymeals/emails/' . $name ) );

		if ( $theme ) {
			return $theme;
		}

		$file = self::templates_dir() . $name;

		/**
		 * Filters the resolved plugin-side template file path.
		 *
		 * @param string $file Absolute path.
		 * @param string $name Template file name.
		 */
		$file = (string) apply_filters( 'mixmymeals_mail_template_file', $file, $name );

		return is_file( $file ) ? $file : '';
	}

	/**
	 * Returns the plugin's e-mail templates directory (trailing-slashed).
	 *
	 * @return string
	 */
	public static function templates_dir(): string {

		$dir = trailingslashit( dirname( __DIR__, 2 ) ) . 'templates/emails/';

		/**
		 * Filters the plugin's e-mail templates directory.
		 *
		 * @param string $dir Absolute, trailing-slashed path.
		 */
		return trailingslashit( (string) apply_filters( 'mixmymeals_mail_templates_dir', $dir ) );
	}

	/**
	 * Lists available e-mail template paths (relative, including subfolders such
	 * as "summer/each-day.html"), looking in the active theme's
	 * mixmymeals/emails/ directory (child and parent) as well as the plugin's own
	 * templates/emails/ directory. The shared layout.html is omitted everywhere.
	 *
	 * @return string[] Sorted, unique template paths.
	 */
	public static function list_templates(): array {

		$dirs = array(
			trailingslashit( get_stylesheet_directory() ) . 'mixmymeals/emails/',
			trailingslashit( get_template_directory() ) . 'mixmymeals/emails/',
			self::templates_dir(),
		);

		$names = array();

		foreach ( $dirs as $dir ) {
			foreach ( self::scan_templates( $dir, $dir ) as $rel ) {
				$names[ $rel ] = $rel;
			}
		}

		ksort( $names, SORT_NATURAL | SORT_FLAG_CASE );

		return array_values( $names );
	}

	/**
	 * Recursively collects *.html template paths under $dir, relative to $base
	 * (so subfolders yield e.g. "summer/each-day.html"). The shared layout.html
	 * is skipped at every level.
	 *
	 * @param string $base Directory the returned paths are relative to (trailing-slashed).
	 * @param string $dir  Directory currently being scanned (trailing-slashed).
	 * @return string[]
	 */
	private static function scan_templates( string $base, string $dir ): array {

		$found = array();

		foreach ( (array) glob( $dir . '*', GLOB_NOSORT ) as $path ) {

			if ( is_dir( $path ) ) {
				$found = array_merge( $found, self::scan_templates( $base, trailingslashit( $path ) ) );
				continue;
			}

			if ( ! preg_match( '/\.html$/i', (string) $path ) || 'layout.html' === basename( (string) $path ) ) {
				continue;
			}

			$found[] = ltrim( substr( (string) $path, strlen( $base ) ), '/' );
		}

		return $found;
	}

	/**
	 * Sanitizes a (possibly sub-foldered) template name and enforces an allowed
	 * extension. Forward slashes are kept so templates may live in subfolders
	 * (e.g. "summer/each-day.html"); ".." and absolute/empty segments are
	 * rejected to prevent path traversal.
	 *
	 * @param string $name Raw template name (relative path).
	 *
	 * @return string Safe relative path, or '' if invalid.
	 */
	private static function sanitize_template_name( string $name ): string {

		$name = ltrim( trim( str_replace( '\\', '/', $name ) ), '/' );

		if ( '' === $name || str_contains( $name, '..' ) ) {
			return '';
		}

		$segments = explode( '/', $name );
		$last     = count( $segments ) - 1;

		foreach ( $segments as $i => $seg ) {
			if ( '' === $seg ) {
				return '';
			}
			$ok = ( $i === $last )
				? (bool) preg_match( '/^[A-Za-z0-9._-]+\.(html|htm|txt)$/', $seg )
				: (bool) preg_match( '/^[A-Za-z0-9._-]+$/', $seg );
			if ( ! $ok ) {
				return '';
			}
		}

		return implode( '/', $segments );
	}

	/**
	 * Whether a template should be treated as HTML (inline defaults to HTML;
	 * files are HTML unless they end in .txt).
	 *
	 * @param string $template Template name or inline markup.
	 * @param bool   $inline   Whether $template is inline markup.
	 *
	 * @return bool
	 */
	private static function is_html_template( string $template, bool $inline ): bool {

		if ( $inline ) {
			return true;
		}

		return ! preg_match( '/\.txt$/i', $template );
	}

	/**
	 * Default placeholder values available to every template.
	 *
	 * @return array
	 */
	private static function default_vars(): array {

		/**
		 * Filters the default template variables.
		 *
		 * @param array $vars Default placeholder values.
		 */
		return (array) apply_filters(
			'mixmymeals_mail_default_vars',
			array(
				'site_name'   => wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ),
				'site_url'    => home_url( '/' ),
				'year'        => gmdate( 'Y' ),

				// The footer option, ready for a layout's {{ footer_html }}.
				// A layout cannot get it any other way: send_template() sets
				// append_footer to false for layouted mails, and transactional
				// mails (every subscription mail) never get it appended either.
				'footer_html' => self::footer_html(),
			)
		);
	}

	/**
	 * The configured footer as ready-to-embed HTML, or '' when none is set.
	 *
	 * @return string
	 */
	private static function footer_html(): string {

		$footer = trim( (string) get_option( self::OPTION_FOOTER, '' ) );

		return '' === $footer ? '' : wp_kses_post( wpautop( $footer ) );
	}

	/**
	 * Validates, sanitizes, lower-cases and de-duplicates recipient addresses
	 * and removes any address on the suppression list (unless forced).
	 *
	 * @param string|string[] $to    Raw recipient(s).
	 * @param bool            $force Whether to ignore the suppression list.
	 *
	 * @return string[] Clean, unique recipient addresses (capped).
	 */
	private static function normalize_recipients( $to, bool $force ): array {

		$list  = is_array( $to ) ? $to : preg_split( '/[,;]+/', (string) $to );
		$clean = array();

		foreach ( (array) $list as $address ) {
			$address = sanitize_email( trim( (string) $address ) );

			if ( '' === $address || ! is_email( $address ) ) {
				continue;
			}

			$address = strtolower( $address );

			if ( ! $force && self::is_suppressed( $address ) ) {
				continue;
			}

			$clean[ $address ] = $address;

			if ( count( $clean ) >= self::MAX_RECIPIENTS ) {
				break;
			}
		}

		return array_values( $clean );
	}

	/**
	 * Removes CR/LF and control characters from a subject line.
	 *
	 * @param string $subject Raw subject.
	 *
	 * @return string Safe, single-line subject.
	 */
	private static function sanitize_subject( string $subject ): string {
		// sanitize_text_field collapses whitespace/newlines; strip remaining control chars.
		$subject = sanitize_text_field( $subject );

		return trim( preg_replace( '/[\r\n\t]+/', ' ', $subject ) );
	}

	/**
	 * Strips CR/LF from an arbitrary header value to prevent header injection.
	 *
	 * @param string $value Raw header value.
	 *
	 * @return string Single-line value.
	 */
	private static function sanitize_header_value( string $value ): string {
		return trim( str_replace( array( "\r", "\n" ), '', $value ) );
	}

	/**
	 * Prepares the message body: sanitizes HTML and optionally appends the
	 * configured footer.
	 *
	 * @param string $message       Raw body.
	 * @param bool   $is_html       Whether the body is HTML.
	 * @param bool   $append_footer Whether to append the footer.
	 * @param bool   $trusted       Skip wp_kses_post() (developer-authored markup
	 *                              with values already escaped on render).
	 *
	 * @return string Prepared body.
	 */
	private static function prepare_body( string $message, bool $is_html, bool $append_footer, bool $trusted = false ): string {

		$footer = $append_footer ? trim( (string) get_option( self::OPTION_FOOTER, '' ) ) : '';

		if ( $is_html ) {
			$body = $trusted ? $message : wp_kses_post( $message );

			if ( '' !== $footer ) {
				$body .= '<hr style="margin:24px 0;border:none;border-top:1px solid #e0e0e0;">'
					. wp_kses_post( wpautop( $footer ) );
			}

			return $body;
		}

		$body = $message;

		if ( '' !== $footer ) {
			$body .= "\n\n--\n" . wp_strip_all_tags( $footer );
		}

		return $body;
	}

	/**
	 * Builds the common header list (From, Reply-To, Cc, Content-Type and an
	 * optional List-Unsubscribe). Bcc is added per recipient in merge_bcc().
	 *
	 * @param array $args    The parsed send() arguments.
	 * @param bool  $is_html Whether the body is HTML.
	 *
	 * @return string[] Header lines.
	 */
	private static function build_headers( array $args, bool $is_html ): array {

		$headers = array();

		$from_email = sanitize_email( (string) $args['from_email'] );
		$from_email = ( '' !== $from_email && is_email( $from_email ) ) ? $from_email : self::from_email();
		$from_name  = self::sanitize_header_value(
			'' !== (string) $args['from_name'] ? (string) $args['from_name'] : self::from_name()
		);

		$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );

		$reply_to = sanitize_email( (string) $args['reply_to'] );
		if ( '' !== $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		foreach ( (array) $args['cc'] as $cc ) {
			$cc = sanitize_email( (string) $cc );
			if ( '' !== $cc && is_email( $cc ) ) {
				$headers[] = 'Cc: ' . $cc;
			}
		}

		$content_type = $is_html ? 'text/html' : 'text/plain';
		$headers[]    = 'Content-Type: ' . $content_type . '; charset=UTF-8';

		$unsubscribe = esc_url_raw( (string) $args['unsubscribe_url'] );
		if ( '' !== $unsubscribe ) {
			$headers[] = 'List-Unsubscribe: <' . $unsubscribe . '>';
		}

		foreach ( (array) $args['headers'] as $raw ) {
			$raw = self::sanitize_header_value( (string) $raw );
			if ( '' !== $raw ) {
				$headers[] = $raw;
			}
		}

		return $headers;
	}

	/**
	 * Appends validated Bcc headers to a base header set.
	 *
	 * @param string[] $headers Base headers.
	 * @param string[] $bcc     Bcc addresses.
	 *
	 * @return string[] Headers including Bcc.
	 */
	private static function merge_bcc( array $headers, array $bcc ): array {

		foreach ( $bcc as $address ) {
			$address = sanitize_email( (string) $address );
			if ( '' !== $address && is_email( $address ) ) {
				$headers[] = 'Bcc: ' . $address;
			}
		}

		return $headers;
	}

	/**
	 * Keeps only attachments that are existing, readable files located inside
	 * an allowed base directory (uploads dir by default).
	 *
	 * @param array $attachments Candidate absolute file paths.
	 *
	 * @return string[] Safe attachment paths.
	 */
	private static function validate_attachments( array $attachments ): array {

		$safe = array();

		foreach ( $attachments as $path ) {
			$path = (string) $path;
			$real = realpath( $path );

			if ( false === $real || ! is_file( $real ) || ! is_readable( $real ) ) {
				continue;
			}

			if ( self::is_within_allowed_path( $real ) ) {
				$safe[] = $real;
			} else {
				self::log( 'Attachment rejected (outside allowed path): ' . $path );
			}
		}

		return $safe;
	}

	/**
	 * Whether a resolved path is inside an allowed base directory.
	 *
	 * Defaults to the uploads base directory; extendable via the
	 * 'mixmymeals_mail_allowed_attachment_dirs' filter.
	 *
	 * @param string $real_path A realpath()-resolved file path.
	 *
	 * @return bool
	 */
	private static function is_within_allowed_path( string $real_path ): bool {

		$uploads = wp_get_upload_dir();
		$bases   = array();

		if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
			$bases[] = $uploads['basedir'];
		}

		/**
		 * Filters the directories attachments may be read from.
		 *
		 * @param string[] $bases Allowed absolute base directories.
		 */
		$bases = (array) apply_filters( 'mixmymeals_mail_allowed_attachment_dirs', $bases );

		foreach ( $bases as $base ) {
			$base = realpath( (string) $base );
			if ( false !== $base && str_starts_with( $real_path, $base . DIRECTORY_SEPARATOR ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the configured sender address, falling back to a safe default
	 * derived from the site domain.
	 *
	 * @return string
	 */
	public static function from_email(): string {

		$email = sanitize_email( (string) get_option( self::OPTION_FROM_EMAIL, '' ) );

		if ( '' !== $email && is_email( $email ) ) {
			return $email;
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = is_string( $host ) ? preg_replace( '/^www\./i', '', $host ) : 'localhost';

		return 'no-reply@' . $host;
	}

	/**
	 * Returns the configured sender name, falling back to the site title.
	 *
	 * @return string
	 */
	public static function from_name(): string {

		$name = trim( (string) get_option( self::OPTION_FROM_NAME, '' ) );

		if ( '' !== $name ) {
			return $name;
		}

		return wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
	}

	/*
	----------------------------------------------------------
	 * SUPPRESSION (OPT-OUT) LIST
	 * ---------------------------------------------------------- */

	/**
	 * Returns the suppression list as lower-cased addresses.
	 *
	 * @return string[]
	 */
	public static function get_suppression_list(): array {

		$list = get_option( self::OPTION_SUPPRESSION, array() );

		if ( ! is_array( $list ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'strtolower', array_map( 'sanitize_email', $list ) ) ) );
	}

	/**
	 * Whether an address has opted out.
	 *
	 * @param string $email Address to check.
	 *
	 * @return bool
	 */
	public static function is_suppressed( string $email ): bool {

		$email = strtolower( sanitize_email( $email ) );

		if ( '' === $email ) {
			return false;
		}

		return in_array( $email, self::get_suppression_list(), true );
	}

	/**
	 * Adds an address to the suppression (opt-out) list.
	 *
	 * @param string $email Address to suppress.
	 *
	 * @return bool True when stored (or already present), false on invalid input.
	 */
	public static function add_suppression( string $email ): bool {

		$email = strtolower( sanitize_email( $email ) );

		if ( '' === $email || ! is_email( $email ) ) {
			return false;
		}

		$list = self::get_suppression_list();

		if ( ! in_array( $email, $list, true ) ) {
			$list[] = $email;
			update_option( self::OPTION_SUPPRESSION, $list, false );
		}

		return true;
	}

	/**
	 * Removes an address from the suppression list.
	 *
	 * @param string $email Address to release.
	 *
	 * @return bool
	 */
	public static function remove_suppression( string $email ): bool {

		$email = strtolower( sanitize_email( $email ) );

		if ( '' === $email ) {
			return false;
		}

		$list    = self::get_suppression_list();
		$updated = array_values( array_diff( $list, array( $email ) ) );

		if ( $updated !== $list ) {
			update_option( self::OPTION_SUPPRESSION, $updated, false );
		}

		return true;
	}

	/*
	----------------------------------------------------------
	 * RATE LIMITING (anti-loop / anti-abuse, opt-in)
	 * ---------------------------------------------------------- */

	/**
	 * Whether the recipient is below the per-hour rate limit.
	 *
	 * The limit defaults to 0 (disabled) so transactional mail is never
	 * blocked unexpectedly. Enable it via the filter
	 * 'mixmymeals_mail_rate_limit_per_hour'.
	 *
	 * @param string $email Recipient address.
	 *
	 * @return bool
	 */
	private static function passes_rate_limit( string $email ): bool {

		$limit = (int) apply_filters( 'mixmymeals_mail_rate_limit_per_hour', 0, $email );

		if ( $limit <= 0 ) {
			return true;
		}

		$count = (int) get_transient( self::rate_key( $email ) );

		return $count < $limit;
	}

	/**
	 * Records a successful send for the per-hour rate counter.
	 *
	 * @param string $email Recipient address.
	 *
	 * @return void
	 */
	private static function record_send( string $email ): void {

		$limit = (int) apply_filters( 'mixmymeals_mail_rate_limit_per_hour', 0, $email );

		if ( $limit <= 0 ) {
			return;
		}

		$key   = self::rate_key( $email );
		$count = (int) get_transient( $key );

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Builds the transient key for the rate counter (hashed address).
	 *
	 * @param string $email Recipient address.
	 *
	 * @return string
	 */
	private static function rate_key( string $email ): string {
		return 'mmm_mail_rl_' . wp_hash( strtolower( $email ) ); // Keyed: an md5 of a known address is a lookup.
	}

	/*
	----------------------------------------------------------
	 * HELPERS
	 * ---------------------------------------------------------- */

	/**
	 * Masks an address for privacy-friendly logging (e.g. "jo***@ex***.com").
	 *
	 * @param string $email Address to mask.
	 *
	 * @return string
	 */
	public static function mask_email( string $email ): string {

		$email = (string) $email;
		$at    = strpos( $email, '@' );

		if ( false === $at ) {
			return '***';
		}

		$local  = substr( $email, 0, $at );
		$domain = substr( $email, $at + 1 );

		$mask = static function ( string $part ): string {
			$len = strlen( $part );
			if ( $len <= 2 ) {
				return str_repeat( '*', max( 1, $len ) );
			}
			return substr( $part, 0, 2 ) . str_repeat( '*', min( 3, $len - 2 ) );
		};

		return $mask( $local ) . '@' . $mask( $domain );
	}

	/**
	 * Logs a message through the plugin logger when debugging is enabled.
	 *
	 * Bodies are never logged; callers must pass only non-sensitive text.
	 *
	 * @param string $message Message to log.
	 *
	 * @return void
	 */
	private static function log( string $message ): void {
		if ( function_exists( 'mixmymeals_write_to_log' ) ) {
			mixmymeals_write_to_log( 'MixMyMeals_Mailer: ' . $message );
		}
	}
}
