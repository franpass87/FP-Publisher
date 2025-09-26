<?php
/**
 * Trello webhook endpoint.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Trello webhook requests.
 */
class TTS_Webhook {

	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Check Trello webhook connectivity for the configured clients.
	 *
	 * @return bool|WP_Error True when at least one client responds correctly, WP_Error otherwise.
	 */
	public static function check_connection() {
		static $cached_result = null;

		if ( null !== $cached_result ) {
			return $cached_result;
		}

		$clients = get_posts(
			array(
				'post_type'      => 'tts_client',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		if ( empty( $clients ) ) {
			$cached_result = new WP_Error( 'tts_webhook_no_clients', __( 'Nessun client Trello configurato.', 'fp-publisher' ) );
			return $cached_result;
		}

		$errors = array();

		foreach ( $clients as $client_id ) {
			$title = get_the_title( $client_id );
			if ( '' === $title ) {
				$title = sprintf( __( 'Client #%d', 'fp-publisher' ), (int) $client_id );
			}

			$key   = trim( (string) get_post_meta( $client_id, '_tts_trello_key', true ) );
			$token = trim( (string) get_post_meta( $client_id, '_tts_trello_token', true ) );

			if ( empty( $key ) || empty( $token ) ) {
				$errors[] = sprintf(
					/* translators: %s: client name. */
					__( '%s: credenziali Trello mancanti.', 'fp-publisher' ),
					$title
				);
				continue;
			}

			$url = add_query_arg(
				array(
					'key'   => $key,
					'token' => $token,
				),
				'https://api.trello.com/1/webhooks'
			);

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 10,
				)
			);

			if ( is_wp_error( $response ) ) {
				$errors[] = sprintf(
					/* translators: 1: client name. 2: error message. */
					__( '%1$s: %2$s', 'fp-publisher' ),
					$title,
					$response->get_error_message()
				);
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( 200 === $code ) {
				$cached_result = true;
				return $cached_result;
			}

			$body_message = '';
			$body         = wp_remote_retrieve_body( $response );

			if ( ! empty( $body ) ) {
				$decoded_body = json_decode( $body, true );

				if ( is_array( $decoded_body ) ) {
					if ( ! empty( $decoded_body['message'] ) && is_string( $decoded_body['message'] ) ) {
						$body_message = $decoded_body['message'];
					} elseif ( ! empty( $decoded_body['error'] ) && is_string( $decoded_body['error'] ) ) {
						$body_message = $decoded_body['error'];
					}
				}

				if ( empty( $body_message ) ) {
					$body_message = wp_strip_all_tags( $body );
				}
			}

			if ( empty( $body_message ) ) {
				$body_message = wp_remote_retrieve_response_message( $response );
			}

			if ( empty( $body_message ) ) {
				if ( 429 === $code ) {
					$body_message = __( 'Limite di richieste Trello raggiunto. Riprovare più tardi.', 'fp-publisher' );
				} elseif ( 401 === $code || 403 === $code ) {
					$body_message = __( 'Credenziali Trello non valide o insufficienti.', 'fp-publisher' );
				} else {
					$body_message = __( 'Risposta inattesa dal servizio Trello.', 'fp-publisher' );
				}
			}

			$body_message = trim( $body_message );

			if ( function_exists( 'mb_strlen' ) && mb_strlen( $body_message ) > 200 ) {
				$body_message = mb_substr( $body_message, 0, 197 ) . '…';
			} elseif ( strlen( $body_message ) > 200 ) {
				$body_message = substr( $body_message, 0, 197 ) . '…';
			}

			$errors[] = sprintf(
				/* translators: 1: client name. 2: error message. 3: HTTP status code. */
				__( '%1$s: %2$s (HTTP %3$d)', 'fp-publisher' ),
				$title,
				$body_message,
				$code
			);
		}

		if ( empty( $errors ) ) {
			$errors[] = __( 'Impossibile verificare la connessione al webhook Trello.', 'fp-publisher' );
		}

		$cached_result = new WP_Error( 'tts_webhook_connection_failed', implode( ' ', array_unique( $errors ) ) );

		return $cached_result;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'tts/v1',
			'/trello-webhook',
			array(
				'methods'             => array( 'POST', 'GET', 'HEAD' ),
				'callback'            => array( $this, 'handle_trello_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'tts/v1',
			'/client/(?P<id>\d+)/register-webhooks',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'register_client_webhooks' ),
				'permission_callback' => function ( $request ) {
					$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
					return current_user_can( 'edit_post', $id );
				},
			)
		);
	}

	/**
	 * Handle incoming Trello webhook requests.
	 *
	 * @param WP_REST_Request $request The request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_trello_webhook( WP_REST_Request $request ) {
		if ( 'POST' !== $request->get_method() ) {
			return rest_ensure_response( 'OK' );
		}

		$data   = $request->get_json_params();
		$card   = isset( $data['action']['data']['card'] ) ? $data['action']['data']['card'] : array();
		$result = array(
			'idCard'        => isset( $card['id'] ) ? $card['id'] : '',
			'name'          => isset( $card['name'] ) ? $card['name'] : '',
			'desc'          => isset( $card['desc'] ) ? $card['desc'] : '',
			'labels'        => isset( $card['labels'] ) ? $card['labels'] : array(),
			'attachments'   => isset( $card['attachments'] ) ? $card['attachments'] : array(),
			'due'           => isset( $card['due'] ) ? $card['due'] : '',
			'idList'        => isset( $card['idList'] ) ? $card['idList'] : '',
			'idBoard'       => isset( $card['idBoard'] ) ? $card['idBoard'] : '',
			'canale_social' => '',
		);

		$client_id = 0;
		$board_id  = $result['idBoard'];
		if ( ! $board_id && isset( $data['action']['data']['board']['id'] ) ) {
			$board_id = $data['action']['data']['board']['id'];
		}
		if ( $board_id ) {
			$client_query = get_posts(
				array(
					'post_type'   => 'tts_client',
					'post_status' => 'any',
					'meta_query'  => array(
						array(
							'key'     => '_tts_trello_boards',
							'value'   => $board_id,
							'compare' => 'LIKE',
						),
					),
					'fields'      => 'ids',
					'numberposts' => 1,
				)
			);
			if ( ! empty( $client_query ) ) {
				$client_id = (int) $client_query[0];
			}
		}
		if ( ! $client_id && $result['idList'] ) {
			// Get all tts_client posts with the _tts_trello_map meta key
			$client_query = get_posts(
				array(
					'post_type'   => 'tts_client',
					'post_status' => 'any',
					'meta_query'  => array(
						array(
							'key'     => '_tts_trello_map',
							'compare' => 'EXISTS',
						),
					),
					'fields'      => 'ids',
					'numberposts' => -1,
				)
			);
			foreach ( $client_query as $client_post_id ) {
				$map = get_post_meta( $client_post_id, '_tts_trello_map', true );
				$map = maybe_unserialize( $map );
				if ( is_array( $map ) ) {
					foreach ( $map as $row ) {
						if ( isset( $row['idList'] ) && $row['idList'] === $result['idList'] ) {
							$client_id               = (int) $client_post_id;
							$result['canale_social'] = isset( $row['canale_social'] ) ? $row['canale_social'] : '';
							break 2;
						}
					}
				} elseif ( is_string( $map ) ) {
					// If stored as comma-separated or single value
					$ids = array_map( 'trim', explode( ',', $map ) );
					if ( in_array( $result['idList'], $ids, true ) ) {
						$client_id = (int) $client_post_id;
						break;
					}
				}
			}
		}

		if ( ! $client_id ) {
			return new WP_Error( 'client_not_found', __( 'Client not found.', 'fp-publisher' ), array( 'status' => 404 ) );
		}

		// Load Trello credentials for the resolved client.
		$client_token  = get_post_meta( $client_id, '_tts_trello_token', true );
		$client_secret = get_post_meta( $client_id, '_tts_trello_secret', true );

		$provided_token = $request->get_param( 'token' );
		if ( empty( $client_token ) || $provided_token !== $client_token ) {
			return new WP_Error( 'invalid_token', __( 'Invalid token.', 'fp-publisher' ), array( 'status' => 403 ) );
		}

		$signature_header = $request->get_header( 'x-trello-webhook' );
		$hmac_param       = $request->get_param( 'hmac' );
		$content          = $request->get_body();

		if ( empty( $client_secret ) ) {
			return new WP_Error( 'missing_secret', __( 'Client secret not configured.', 'fp-publisher' ), array( 'status' => 403 ) );
		}

		if ( $signature_header ) {
			$callback_url = rest_url( 'tts/v1/trello-webhook' );
			$expected     = base64_encode( hash_hmac( 'sha1', $content . $callback_url, $client_secret, true ) );
			if ( ! hash_equals( $signature_header, $expected ) ) {
				return new WP_Error( 'invalid_signature', __( 'Invalid signature.', 'fp-publisher' ), array( 'status' => 403 ) );
			}
		} elseif ( $hmac_param ) {
			$expected = hash_hmac( 'sha256', $content, $client_secret );
			if ( ! hash_equals( $hmac_param, $expected ) ) {
				return new WP_Error( 'invalid_signature', __( 'Invalid signature.', 'fp-publisher' ), array( 'status' => 403 ) );
			}
		} else {
			return new WP_Error( 'invalid_signature', __( 'Missing signature.', 'fp-publisher' ), array( 'status' => 403 ) );
		}

		$import_result = self::import_card_for_client( $result, $client_id, $data );

		if ( is_wp_error( $import_result ) ) {
			$error_code = $import_result->get_error_code();

			if ( 'trello_card_exists' === $error_code ) {
				return rest_ensure_response( array( 'message' => __( 'Card already processed.', 'fp-publisher' ) ) );
			}

			if ( 'tts_unmapped_list' === $error_code ) {
				return rest_ensure_response( array( 'message' => __( 'Unmapped list.', 'fp-publisher' ) ) );
			}

			$error_data = $import_result->get_error_data();
			if ( ! is_array( $error_data ) ) {
				$error_data = array();
			}
			if ( ! isset( $error_data['status'] ) ) {
				$error_data['status'] = 400;
			}

			return new WP_Error( $error_code, $import_result->get_error_message(), $error_data );
		}

		return rest_ensure_response( $import_result );
	}

	/**
	 * Create a social post from Trello card data.
	 *
	 * @param array $card_data Trello card data.
	 * @param int   $client_id Client identifier.
	 * @param array $payload   Optional webhook payload for manual media detection.
	 *
	 * @return array|WP_Error Import result or error.
	 */
	public static function import_card_for_client( $card_data, $client_id, $payload = array() ) {
		$defaults = array(
			'idCard'        => '',
			'name'          => '',
			'desc'          => '',
			'labels'        => array(),
			'attachments'   => array(),
			'due'           => '',
			'idList'        => '',
			'idBoard'       => '',
			'canale_social' => '',
		);

		$card_data = wp_parse_args( $card_data, $defaults );

		if ( empty( $card_data['idCard'] ) ) {
			return new WP_Error( 'missing_card_id', __( 'Card ID is required for import.', 'fp-publisher' ) );
		}

		if ( empty( $card_data['idList'] ) ) {
			return new WP_Error( 'missing_list_id', __( 'Card list is required for import.', 'fp-publisher' ) );
		}

		$mapping_json = get_post_meta( $client_id, '_tts_column_mapping', true );
		$mapping      = ! empty( $mapping_json ) ? json_decode( $mapping_json, true ) : array();

		if ( empty( $mapping ) || ! is_array( $mapping ) || ! array_key_exists( $card_data['idList'], $mapping ) ) {
			return new WP_Error( 'tts_unmapped_list', __( 'The Trello list is not mapped for import.', 'fp-publisher' ) );
		}

		$existing_post = get_posts(
			array(
				'post_type'   => 'tts_social_post',
				'post_status' => 'any',
				'meta_query'  => array(
					array(
						'key'   => '_trello_card_id',
						'value' => $card_data['idCard'],
					),
				),
				'fields'      => 'ids',
				'numberposts' => 1,
			)
		);

		if ( ! empty( $existing_post ) ) {
			tts_log_event( $existing_post[0], 'webhook', 'skip', 'Trello card already processed', '' );
			return new WP_Error( 'trello_card_exists', __( 'Trello card already processed.', 'fp-publisher' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => sanitize_text_field( $card_data['name'] ),
				'post_content' => wp_kses_post( $card_data['desc'] ),
				'post_type'    => 'tts_social_post',
				'post_status'  => 'draft',
				'meta_input'   => array(
					'_tts_client_id'        => $client_id,
					'_tts_content_source'   => 'trello',
					'_tts_source_reference' => $card_data['idCard'],
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_trello_card_id', $card_data['idCard'] );
		update_post_meta( $post_id, '_trello_labels', $card_data['labels'] );
		update_post_meta( $post_id, '_trello_attachments', $card_data['attachments'] );
		update_post_meta( $post_id, '_trello_due', $card_data['due'] );
		update_post_meta( $post_id, '_trello_board_id', $card_data['idBoard'] );

		if ( ! empty( $card_data['canale_social'] ) ) {
			update_post_meta( $post_id, '_tts_canale_social', sanitize_text_field( $card_data['canale_social'] ) );
		}

		$card_data['post_id']   = $post_id;
		$card_data['client_id'] = $client_id;

		$media_ids = array();

		if ( ! empty( $card_data['attachments'] ) && is_array( $card_data['attachments'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			foreach ( $card_data['attachments'] as $attachment ) {
				if ( empty( $attachment['isUpload'] ) || empty( $attachment['url'] ) ) {
					continue;
				}

				$response = wp_remote_get(
					$attachment['url'],
					array(
						'timeout' => 20,
					)
				);

				if ( is_wp_error( $response ) ) {
					tts_log_event( $post_id, 'webhook', 'error', __( 'Failed to retrieve attachment.', 'fp-publisher' ), $attachment['url'] );
					continue;
				}

				$code = wp_remote_retrieve_response_code( $response );
				if ( 200 !== (int) $code ) {
					tts_log_event( $post_id, 'webhook', 'error', sprintf( __( 'Unexpected HTTP response code: %d', 'fp-publisher' ), $code ), $attachment['url'] );
					continue;
				}

				$content_type = wp_remote_retrieve_header( $response, 'content-type' );
				$file_name    = basename( wp_parse_url( $attachment['url'], PHP_URL_PATH ) );
				$filetype     = wp_check_filetype( $file_name );

				if ( empty( $content_type ) || empty( $filetype['type'] ) || ( 0 !== strpos( $content_type, 'image/' ) && 0 !== strpos( $content_type, 'video/' ) ) ) {
					tts_log_event( $post_id, 'webhook', 'error', sprintf( __( 'Unsupported MIME type: %s', 'fp-publisher' ), $content_type ), $attachment['url'] );
					continue;
				}

				$body = wp_remote_retrieve_body( $response );
				$tmp  = wp_tempnam( $attachment['url'] );

				if ( ! $tmp ) {
					continue;
				}

				file_put_contents( $tmp, $body );

				$file_array = array(
					'name'     => sanitize_file_name( $file_name ),
					'tmp_name' => $tmp,
				);

				$media_id = media_handle_sideload( $file_array, $post_id );
				@unlink( $tmp );

				if ( is_wp_error( $media_id ) ) {
					tts_log_event( $post_id, 'webhook', 'error', $media_id->get_error_message(), $attachment['url'] );
					continue;
				}

				$media_ids[] = (int) $media_id;
			}

			if ( ! empty( $media_ids ) ) {
				set_post_thumbnail( $post_id, $media_ids[0] );
				update_post_meta( $post_id, '_trello_media_ids', $media_ids );
				update_post_meta( $post_id, '_tts_attachment_ids', $media_ids );
			}
		}

		if ( empty( $media_ids ) ) {
			$manual_url = '';
			$pattern    = '/https?:\\/\\/\S+\.mp4/i';

			if ( ! empty( $card_data['desc'] ) && preg_match( $pattern, $card_data['desc'], $matches ) ) {
				$manual_url = $matches[0];
			} elseif ( isset( $payload['action']['data']['text'] ) && preg_match( $pattern, $payload['action']['data']['text'], $matches ) ) {
				$manual_url = $matches[0];
			}

			if ( $manual_url ) {
				$importer = new TTS_Media_Importer();
				$media_id = $importer->import_from_url( $manual_url );

				if ( is_wp_error( $media_id ) ) {
					tts_log_event( $post_id, 'webhook', 'error', $media_id->get_error_message(), $manual_url );
				} else {
					set_post_thumbnail( $post_id, $media_id );
					update_post_meta( $post_id, '_tts_manual_media', (int) $media_id );
					update_post_meta( $post_id, '_tts_attachment_ids', array( (int) $media_id ) );
					$media_ids[] = (int) $media_id;
					tts_log_event( $post_id, 'webhook', 'success', __( 'Manual media imported', 'fp-publisher' ), $manual_url );
				}
			} else {
				tts_log_event( $post_id, 'webhook', 'warning', __( 'No attachments provided', 'fp-publisher' ), '' );
			}
		}

		if ( ! empty( $card_data['due'] ) ) {
			$publish_at = sanitize_text_field( $card_data['due'] );
			update_post_meta( $post_id, '_tts_publish_at', $publish_at );
			$timestamp = strtotime( $publish_at );
			if ( $timestamp ) {
				as_schedule_single_action( $timestamp, 'tts_publish_social_post', array( $post_id ) );
				tts_log_event(
					$post_id,
					'webhook',
					'scheduled',
					sprintf( __( 'Publish scheduled for %s', 'fp-publisher' ), $publish_at ),
					''
				);
			}
		}

		return $card_data;
	}

	/**
	 * Register Trello webhooks for all boards of a client.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function register_client_webhooks( WP_REST_Request $request ) {
		$client_id = (int) $request['id'];

		$key    = get_post_meta( $client_id, '_tts_trello_key', true );
		$token  = get_post_meta( $client_id, '_tts_trello_token', true );
		$boards = get_post_meta( $client_id, '_tts_trello_boards', true );

		if ( empty( $key ) || empty( $token ) || empty( $boards ) || ! is_array( $boards ) ) {
			return new WP_Error( 'missing_data', __( 'Missing Trello credentials or boards.', 'fp-publisher' ), array( 'status' => 400 ) );
		}

		$callback = rest_url( 'tts/v1/trello-webhook' );
		$results  = array();

		foreach ( $boards as $board_id ) {
			$response = wp_remote_post(
				sprintf( 'https://api.trello.com/1/webhooks/?key=%s&token=%s', rawurlencode( $key ), rawurlencode( $token ) ),
				array(
					'body'    => array(
						'idModel'     => $board_id,
						'callbackURL' => $callback,
						'description' => get_bloginfo( 'name' ) . ' TTS',
					),
					'timeout' => 20,
				)
			);

			if ( is_wp_error( $response ) ) {
				$results[ $board_id ] = $response->get_error_message();
			} else {
				$results[ $board_id ] = wp_remote_retrieve_response_code( $response );
			}
		}

		return rest_ensure_response( $results );
	}
}

new TTS_Webhook();
