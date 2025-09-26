<?php
/**
 * WP-CLI commands for FP Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Provide diagnostic and setup helpers via WP-CLI.
	 */
	class TTS_CLI_Command {

		/**
		 * Execute or display the latest system health check.
		 *
		 * ## OPTIONS
		 *
		 * [--force]
		 * : Force a new health scan instead of reusing the cached snapshot.
		 */
		public function health( $args, $assoc_args ) {
			$force  = isset( $assoc_args['force'] );
			$health = tsap_get_option( 'tts_last_health_check', array() );

			if ( $force || empty( $health ) ) {
				if ( $force ) {
					\WP_CLI::log( __( 'Esecuzione forzata del controllo salute…', 'fp-publisher' ) );
				} else {
					\WP_CLI::log( __( 'Nessun controllo precedente disponibile, ne viene eseguito uno ora…', 'fp-publisher' ) );
				}

				$health = TTS_Monitoring::perform_health_check();
			}

			if ( empty( $health ) || ! is_array( $health ) ) {
				\WP_CLI::error( __( 'Impossibile recuperare i dati di salute del sistema.', 'fp-publisher' ) );
			}

			$score = isset( $health['score'] ) ? (int) $health['score'] : 0;
			\WP_CLI::log( sprintf( __( 'Punteggio salute: %d/100', 'fp-publisher' ), $score ) );

			$checks = isset( $health['checks'] ) && is_array( $health['checks'] ) ? $health['checks'] : array();
			$rows   = array();

			foreach ( $checks as $key => $check ) {
				$rows[] = array(
					'check'   => $this->format_check_label( $key ),
					'status'  => $this->extract_check_status( $check ),
					'message' => $this->extract_check_message( $check ),
				);
			}

			if ( ! empty( $rows ) ) {
				\WP_CLI\Utils::format_items( 'table', $rows, array( 'check', 'status', 'message' ) );
			}

			$suggestions = TTS_Monitoring::get_remediation_suggestions( $health );
			if ( ! empty( $suggestions ) ) {
				\WP_CLI::log( '' );
				\WP_CLI::log( __( 'Azioni consigliate:', 'fp-publisher' ) );

				$formatted = array();
				foreach ( $suggestions as $suggestion ) {
					$formatted[] = array(
						'title'       => isset( $suggestion['title'] ) ? $suggestion['title'] : '',
						'description' => isset( $suggestion['description'] ) ? $suggestion['description'] : '',
						'link'        => isset( $suggestion['link'] ) ? $suggestion['link'] : '',
					);
				}

				\WP_CLI\Utils::format_items( 'table', $formatted, array( 'title', 'description', 'link' ) );
			}

			$actionable = TTS_Monitoring::get_actionable_health_summary();
			if ( ! empty( $actionable ) ) {
				\WP_CLI::log( '' );
				\WP_CLI::log( __( 'Stato componenti critici:', 'fp-publisher' ) );

				$rows = array();
				foreach ( $actionable as $item ) {
					$rows[] = array(
						'component' => isset( $item['label'] ) ? $item['label'] : '',
						'status'    => isset( $item['status'] ) ? ucfirst( $item['status'] ) : '',
						'note'      => isset( $item['description'] ) ? $item['description'] : '',
					);
				}

				if ( ! empty( $rows ) ) {
					\WP_CLI\Utils::format_items( 'table', $rows, array( 'component', 'status', 'note' ) );
				}
			}

			\WP_CLI::success( __( 'Analisi completata.', 'fp-publisher' ) );
		}

		/**
		 * Inspect or validate Quickstart packages from the command line.
		 *
		 * ## OPTIONS
		 *
		 * [--list]
		 * : Visualizza l\'elenco dei pacchetti disponibili.
		 *
		 * [--slug=<slug>]
		 * : Valida un pacchetto specifico e mostra i prerequisiti.
		 */
		public function quickstart( $args, $assoc_args ) {
			$admin = new TTS_Admin();

			if ( isset( $assoc_args['list'] ) ) {
				$catalog = $admin->get_quickstart_package_catalog();

				if ( empty( $catalog ) ) {
					\WP_CLI::warning( __( 'Nessun pacchetto Quickstart configurato.', 'fp-publisher' ) );
					return;
				}

				foreach ( $catalog as &$entry ) {
					$entry['profile'] = $admin->get_usage_profile_label( $entry['profile'] );
				}

				\WP_CLI\Utils::format_items( 'table', $catalog, array( 'slug', 'title', 'profile', 'description' ) );
				return;
			}

			if ( empty( $assoc_args['slug'] ) ) {
				\WP_CLI::error( __( 'Specificare uno slug con --slug=<pacchetto> oppure usare --list.', 'fp-publisher' ) );
			}

			$slug     = sanitize_key( $assoc_args['slug'] );
			$overview = $admin->get_quickstart_package_overview( $slug );

			if ( is_wp_error( $overview ) ) {
				\WP_CLI::error( $overview->get_error_message() );
			}

			$definition = isset( $overview['definition'] ) ? $overview['definition'] : array();
			$title      = isset( $definition['title'] ) ? $definition['title'] : $slug;
			$profile    = $admin->get_usage_profile_label( isset( $overview['required_profile'] ) ? $overview['required_profile'] : 'standard' );

			\WP_CLI::log( sprintf( __( 'Pacchetto: %s', 'fp-publisher' ), $title ) );
			\WP_CLI::log( sprintf( __( 'Profilo richiesto: %s', 'fp-publisher' ), $profile ) );

			if ( empty( $overview['profile_allowed'] ) ) {
				\WP_CLI::warning(
					sprintf(
					/* translators: %s: active usage profile label. */
						__( 'Il profilo attivo (%s) non soddisfa i requisiti del pacchetto.', 'fp-publisher' ),
						$admin->get_usage_profile_label( isset( $overview['active_profile'] ) ? $overview['active_profile'] : 'standard' )
					)
				);
			}

			if ( isset( $definition['description'] ) && '' !== $definition['description'] ) {
				\WP_CLI::log( $definition['description'] );
			}

			if ( isset( $definition['trello_template'] ) && '' !== $definition['trello_template'] ) {
				\WP_CLI::log( sprintf( __( 'Template Trello: %s', 'fp-publisher' ), $definition['trello_template'] ) );
			}

			if ( isset( $definition['journey_doc']['path'] ) ) {
				$doc_path = $definition['journey_doc']['path'];
				if ( isset( $definition['journey_doc']['anchor'] ) && '' !== $definition['journey_doc']['anchor'] ) {
					$doc_path .= $definition['journey_doc']['anchor'];
				}
				\WP_CLI::log( sprintf( __( 'Percorso guidato: %s', 'fp-publisher' ), $doc_path ) );
			}

			$readiness = isset( $overview['readiness'] ) ? $overview['readiness'] : array();
			if ( isset( $readiness['summary'] ) ) {
				\WP_CLI::log( '' );
				\WP_CLI::log( sprintf( __( 'Validazione ambiente: %s', 'fp-publisher' ), $readiness['summary'] ) );
			}

			if ( isset( $readiness['checks'] ) && is_array( $readiness['checks'] ) ) {
				$checks = array();
				foreach ( $readiness['checks'] as $check ) {
					$checks[] = array(
						'status'  => isset( $check['status'] ) ? $check['status'] : 'ok',
						'label'   => isset( $check['label'] ) ? $check['label'] : '',
						'message' => isset( $check['message'] ) ? $check['message'] : '',
					);
				}

				if ( ! empty( $checks ) ) {
					\WP_CLI\Utils::format_items( 'table', $checks, array( 'status', 'label', 'message' ) );
				}
			}

			$preview = isset( $overview['preview'] ) ? $overview['preview'] : array();
			if ( isset( $preview['mapping'] ) && isset( $preview['mapping']['added'] ) ) {
				$added   = count( $preview['mapping']['added'] );
				$over    = isset( $preview['mapping']['overrides'] ) ? count( $preview['mapping']['overrides'] ) : 0;
				$current = isset( $preview['mapping']['current_total'] ) ? (int) $preview['mapping']['current_total'] : 0;
				\WP_CLI::log( sprintf( __( 'Mapping Trello aggiunti: %1$d · sovrascritture: %2$d · mapping attuali: %3$d', 'fp-publisher' ), $added, $over, $current ) );
			}

			if ( isset( $preview['templates'] ) && ! empty( $preview['templates'] ) ) {
				$template_rows = array();
				foreach ( $preview['templates'] as $channel => $template ) {
					$template_rows[] = array(
						'channel' => $channel,
						'status'  => isset( $template['action'] ) ? $template['action'] : 'add',
						'sample'  => isset( $template['value'] ) ? $template['value'] : '',
					);
				}

				if ( ! empty( $template_rows ) ) {
					\WP_CLI::log( '' );
					\WP_CLI::log( __( 'Template social coinvolti:', 'fp-publisher' ) );
					\WP_CLI\Utils::format_items( 'table', $template_rows, array( 'channel', 'status', 'sample' ) );
				}
			}

			if ( isset( $preview['utm'] ) && ! empty( $preview['utm'] ) ) {
				\WP_CLI::log( '' );
				\WP_CLI::log( __( 'Parametri UTM proposti:', 'fp-publisher' ) );

				$utm_rows = array();
				foreach ( $preview['utm'] as $utm_entry ) {
					$utm_rows[] = array(
						'channel' => isset( $utm_entry['channel'] ) ? $utm_entry['channel'] : 'all',
						'param'   => isset( $utm_entry['param'] ) ? $utm_entry['param'] : '',
						'action'  => isset( $utm_entry['action'] ) ? $utm_entry['action'] : '',
						'value'   => isset( $utm_entry['new'] ) ? $utm_entry['new'] : '',
					);
				}

				\WP_CLI\Utils::format_items( 'table', $utm_rows, array( 'channel', 'param', 'action', 'value' ) );
			}

			if ( isset( $preview['blog'] ) && ! empty( $preview['blog'] ) ) {
				\WP_CLI::log( '' );
				\WP_CLI::log( __( 'Prefill blog:', 'fp-publisher' ) );
				foreach ( $preview['blog'] as $key => $value ) {
					\WP_CLI::log( sprintf( ' - %s: %s', $key, $value ) );
				}
			}

			\WP_CLI::success( __( 'Analisi pacchetto completata.', 'fp-publisher' ) );
		}

		/**
		 * Convert check identifiers to human readable labels.
		 *
		 * @param string $key Check slug.
		 * @return string
		 */
		private function format_check_label( $key ) {
			$key = str_replace( '_', ' ', (string) $key );
			$key = trim( $key );

			if ( '' === $key ) {
				return __( 'Check', 'fp-publisher' );
			}

			return ucwords( $key );
		}

		/**
		 * Normalize status values for CLI rendering.
		 *
		 * @param array<string, mixed> $check Check payload.
		 * @return string
		 */
		private function extract_check_status( $check ) {
			if ( isset( $check['status'] ) ) {
				if ( is_bool( $check['status'] ) ) {
					return $check['status'] ? 'ok' : 'ko';
				}

				if ( is_string( $check['status'] ) && '' !== $check['status'] ) {
					return $check['status'];
				}
			}

			if ( isset( $check['failed_connections'] ) && (int) $check['failed_connections'] > 0 ) {
				return 'warning';
			}

			if ( isset( $check['alerts'] ) && ! empty( $check['alerts'] ) ) {
				return 'warning';
			}

			return 'ok';
		}

		/**
		 * Extract the most relevant message for a health check row.
		 *
		 * @param array<string, mixed> $check Check payload.
		 * @return string
		 */
		private function extract_check_message( $check ) {
			if ( isset( $check['message'] ) && '' !== $check['message'] ) {
				return $check['message'];
			}

			if ( isset( $check['error_rate'] ) ) {
				return sprintf( __( 'Tasso di errore: %s%%', 'fp-publisher' ), $check['error_rate'] );
			}

			if ( isset( $check['details'] ) && is_array( $check['details'] ) ) {
				$preview = array_slice( $check['details'], 0, 3, true );
				return wp_json_encode( $preview );
			}

			return '';
		}
	}

	\WP_CLI::add_command( 'tts', 'TTS_CLI_Command' );
}
