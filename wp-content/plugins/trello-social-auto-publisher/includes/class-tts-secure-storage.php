<?php
/**
 * Secure storage utilities for secrets and sensitive metadata.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides helpers for encrypting and decrypting sensitive data at rest.
 */
class TTS_Secure_Storage {

    const ENCRYPTION_PREFIX = 'tts-secure::';
    const ENCRYPTION_VERSION = 1;
    const CIPHER_METHOD = 'aes-256-gcm';

    /**
     * Singleton instance.
     *
     * @var TTS_Secure_Storage|null
     */
    private static $instance = null;

    /**
     * Runtime cache of decrypted meta values.
     *
     * @var array<int, array<string, mixed>>
     */
    private $decrypted_meta_cache = array();

    /**
     * Runtime cache of encrypted meta values.
     *
     * @var array<int, array<string, string>>
     */
    private $encrypted_meta_cache = array();

    /**
     * Indicates whether metadata filters are temporarily suspended.
     *
     * @var bool
     */
    private $suspended = false;

    /**
     * Cached managed key material keyed by key identifier.
     *
     * @var array<string, array{key: string, key_id: string}>
     */
    private $managed_keys = array();

    /**
     * Cached list of explicitly sensitive meta keys.
     *
     * @var array<int, string>
     */
    private $sensitive_meta_keys = array(
        '_tts_trello_key',
        '_tts_trello_token',
        '_tts_fb_token',
        '_tts_ig_token',
        '_tts_yt_token',
        '_tts_tt_token',
        '_tts_trello_map',
        '_tts_publish_log',
    );

    /**
     * Retrieve the shared instance.
     *
     * @return TTS_Secure_Storage
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register WordPress hooks.
     */
    private function __construct() {
        $this->sensitive_meta_keys = apply_filters( 'tts_sensitive_meta_keys', $this->sensitive_meta_keys );

        if ( function_exists( 'add_filter' ) ) {
            add_filter( 'add_post_metadata', array( $this, 'filter_add_post_metadata' ), 10, 5 );
            add_filter( 'update_post_metadata', array( $this, 'filter_update_post_metadata' ), 10, 5 );
            add_filter( 'get_post_metadata', array( $this, 'filter_get_post_metadata' ), 10, 4 );
        }
    }

    /**
     * Encrypt metadata values before insertion.
     *
     * @param null|bool $check      Short-circuit flag.
     * @param int        $object_id Post ID.
     * @param string     $meta_key  Meta key.
     * @param mixed      $meta_value Meta value.
     * @param bool       $unique    Whether meta key should be unique.
     *
     * @return mixed
     */
    public function filter_add_post_metadata( $check, $object_id, $meta_key, $meta_value, $unique ) {
        if ( $this->suspended || ! $this->should_secure_meta( $meta_key, $meta_value ) ) {
            return $check;
        }

        $encrypted = $this->encrypt_for_storage(
            $meta_value,
            array(
                'meta_key' => $meta_key,
                'post_id'  => $object_id,
                'reason'   => 'add_post_meta',
            )
        );

        if ( false === $encrypted ) {
            return $check;
        }

        $this->remember_meta_value( $object_id, $meta_key, $meta_value, $encrypted );

        return $this->run_while_suspended( function () use ( $object_id, $meta_key, $encrypted, $unique ) {
            return add_post_meta( $object_id, $meta_key, $encrypted, $unique );
        } );
    }

    /**
     * Encrypt metadata values prior to update.
     *
     * @param null|bool $check      Short-circuit flag.
     * @param int        $object_id Post ID.
     * @param string     $meta_key  Meta key.
     * @param mixed      $meta_value Meta value.
     * @param mixed      $prev_value Previous value.
     *
     * @return mixed
     */
    public function filter_update_post_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
        if ( $this->suspended || ! $this->should_secure_meta( $meta_key, $meta_value ) ) {
            return $check;
        }

        $encrypted = $this->encrypt_for_storage(
            $meta_value,
            array(
                'meta_key' => $meta_key,
                'post_id'  => $object_id,
                'reason'   => 'update_post_meta',
            )
        );

        if ( false === $encrypted ) {
            return $check;
        }

        $this->remember_meta_value( $object_id, $meta_key, $meta_value, $encrypted );

        return $this->run_while_suspended( function () use ( $object_id, $meta_key, $encrypted, $prev_value ) {
            return update_post_meta( $object_id, $meta_key, $encrypted, $prev_value );
        } );
    }

    /**
     * Decrypt metadata values when retrieved.
     *
     * @param null|mixed $value     Short-circuit value.
     * @param int        $object_id Post ID.
     * @param string     $meta_key  Meta key.
     * @param bool       $single    Whether to return a single value.
     *
     * @return mixed
     */
    public function filter_get_post_metadata( $value, $object_id, $meta_key, $single ) {
        if ( $this->suspended || '' === $meta_key || ! $this->should_secure_meta( $meta_key, null ) ) {
            return $value;
        }

        $cache_key = $this->build_cache_key( $object_id, $meta_key );

        if ( isset( $this->decrypted_meta_cache[ $cache_key ] ) ) {
            $decrypted = $this->decrypted_meta_cache[ $cache_key ];
        } else {
            $encrypted = $this->fetch_encrypted_meta( $object_id, $meta_key );

            if ( null === $encrypted ) {
                return $value;
            }

            $decrypted = $this->decrypt_from_storage(
                $encrypted,
                array(
                    'meta_key' => $meta_key,
                    'post_id'  => $object_id,
                    'reason'   => 'get_post_meta',
                )
            );

            $this->decrypted_meta_cache[ $cache_key ] = $decrypted;
        }

        if ( $single ) {
            return $decrypted;
        }

        return array( $decrypted );
    }

    /**
     * Encrypt an arbitrary value for storage.
     *
     * @param mixed $value   Value to encrypt.
     * @param array $context Contextual information for key resolution.
     *
     * @return string|false
     */
    public function encrypt_for_storage( $value, $context = array() ) {
        if ( is_string( $value ) && $this->is_encrypted_string( $value ) ) {
            return $value;
        }

        $key_info = $this->get_encryption_key( $context );

        if ( empty( $key_info['key'] ) ) {
            $this->log_security_warning( 'Unable to resolve encryption key for secure storage.', $context );
            return false;
        }

        $plain_value   = $this->normalize_plain_value( $value );
        $iv_length     = openssl_cipher_iv_length( self::CIPHER_METHOD );
        $iv            = random_bytes( $iv_length );
        $tag           = '';
        $ciphertext    = openssl_encrypt( $plain_value, self::CIPHER_METHOD, $key_info['key'], OPENSSL_RAW_DATA, $iv, $tag );

        if ( false === $ciphertext ) {
            $this->log_security_warning( 'OpenSSL failed to encrypt metadata payload.', $context );
            return false;
        }

        $payload = array(
            'v'       => self::ENCRYPTION_VERSION,
            'key_id'  => $key_info['key_id'],
            'iv'      => base64_encode( $iv ),
            'tag'     => base64_encode( $tag ),
            'cipher'  => base64_encode( $ciphertext ),
            'encoded' => is_string( $value ) ? 'string' : 'serialized',
        );

        $json = wp_json_encode( $payload );

        if ( false === $json ) {
            $this->log_security_warning( 'Unable to encode encryption payload for storage.', $context );
            return false;
        }

        return self::ENCRYPTION_PREFIX . base64_encode( $json );
    }

    /**
     * Decrypt a value previously produced by encrypt_for_storage().
     *
     * @param mixed $value   Stored value.
     * @param array $context Contextual information for key resolution.
     *
     * @return mixed
     */
    public function decrypt_from_storage( $value, $context = array() ) {
        if ( ! is_string( $value ) || ! $this->is_encrypted_string( $value ) ) {
            return maybe_unserialize( $value );
        }

        $encoded = substr( $value, strlen( self::ENCRYPTION_PREFIX ) );
        $decoded = base64_decode( $encoded, true );

        if ( false === $decoded ) {
            $this->log_security_warning( 'Secure payload could not be base64 decoded.', $context );
            return maybe_unserialize( $value );
        }

        $payload = json_decode( $decoded, true );

        if ( ! is_array( $payload ) || empty( $payload['cipher'] ) || empty( $payload['iv'] ) ) {
            $this->log_security_warning( 'Secure payload structure is invalid.', $context );
            return maybe_unserialize( $value );
        }

        $key_info = $this->get_encryption_key( $context, isset( $payload['key_id'] ) ? (string) $payload['key_id'] : null );

        if ( empty( $key_info['key'] ) ) {
            $this->log_security_warning( 'Unable to resolve key for secure payload decryption.', $context );
            return maybe_unserialize( $value );
        }

        $ciphertext = base64_decode( $payload['cipher'], true );
        $iv         = base64_decode( $payload['iv'], true );
        $tag        = isset( $payload['tag'] ) ? base64_decode( (string) $payload['tag'], true ) : '';

        if ( false === $ciphertext || false === $iv ) {
            $this->log_security_warning( 'Secure payload contains invalid binary data.', $context );
            return maybe_unserialize( $value );
        }

        $plain = openssl_decrypt( $ciphertext, self::CIPHER_METHOD, $key_info['key'], OPENSSL_RAW_DATA, $iv, $tag );

        if ( false === $plain ) {
            $this->log_security_warning( 'OpenSSL failed to decrypt secure payload.', $context );
            return maybe_unserialize( $value );
        }

        if ( isset( $payload['encoded'] ) && 'string' === $payload['encoded'] ) {
            return $plain;
        }

        return maybe_unserialize( $plain );
    }

    /**
     * Mask sensitive data before presenting it externally.
     *
     * @param mixed $data              Data to mask.
     * @param array $additional_fields Additional keys to mask.
     *
     * @return mixed
     */
    public function mask_sensitive_data( $data, $additional_fields = array() ) {
        if ( is_scalar( $data ) || null === $data ) {
            return $this->mask_scalar( $data );
        }

        if ( is_object( $data ) ) {
            $data = json_decode( wp_json_encode( $data ), true );
        }

        if ( ! is_array( $data ) ) {
            return $data;
        }

        $sensitive_keys = array_merge(
            array( 'token', 'secret', 'password', 'credential', 'auth', 'session', 'signature', 'key', 'bearer' ),
            array_map( 'strtolower', array_filter( (array) $additional_fields ) )
        );

        $masked = array();

        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) || is_object( $value ) ) {
                $masked[ $key ] = $this->mask_sensitive_data( $value, $additional_fields );
                continue;
            }

            $lower_key = strtolower( (string) $key );
            $should_mask = false;

            foreach ( $sensitive_keys as $needle ) {
                if ( '' === $needle ) {
                    continue;
                }

                if ( false !== strpos( $lower_key, $needle ) ) {
                    $should_mask = true;
                    break;
                }
            }

            $masked[ $key ] = $should_mask ? $this->mask_scalar( $value ) : $value;
        }

        return $masked;
    }

    /**
     * Resolve secrets stored using managed vault notations.
     *
     * @param mixed $value   Value or reference.
     * @param array $context Contextual data.
     *
     * @return mixed
     */
    public function resolve_managed_secret( $value, $context = array() ) {
        if ( ! is_string( $value ) ) {
            return $value;
        }

        if ( 0 === strpos( $value, 'env:' ) ) {
            $env_key = substr( $value, 4 );
            $env_val = getenv( $env_key );
            return false !== $env_val ? $env_val : '';
        }

        if ( 0 === strpos( $value, 'vault:' ) ) {
            $parts = explode( ':', $value, 3 );
            if ( count( $parts ) >= 3 ) {
                $provider  = strtolower( $parts[1] );
                $reference = $parts[2];

                $filtered = apply_filters( 'tts_vault_resolve_' . $provider, null, $reference, $context );
                if ( null !== $filtered ) {
                    return $filtered;
                }
            }
        }

        return apply_filters( 'tts_resolve_managed_secret', $value, $context );
    }

    /**
     * Check whether the provided value already uses the secure prefix.
     *
     * @param mixed $value Value to inspect.
     *
     * @return bool
     */
    public function is_encrypted_string( $value ) {
        return is_string( $value ) && 0 === strpos( $value, self::ENCRYPTION_PREFIX );
    }

    /**
     * Determine whether the provided meta key should be encrypted.
     *
     * @param string $meta_key Meta key.
     * @param mixed  $meta_value Meta value.
     *
     * @return bool
     */
    private function should_secure_meta( $meta_key, $meta_value ) {
        if ( '' === $meta_key ) {
            return false;
        }

        $meta_key = (string) $meta_key;

        if ( in_array( $meta_key, $this->sensitive_meta_keys, true ) ) {
            return true;
        }

        $lower_key = strtolower( $meta_key );
        $patterns  = array( '_token', 'token_', '_secret', 'secret_', '_credential', '_password', 'password_', '_auth', 'auth_', '_key' );

        foreach ( $patterns as $pattern ) {
            if ( false !== strpos( $lower_key, $pattern ) ) {
                return true;
            }
        }

        return apply_filters( 'tts_should_secure_meta_key', false, $meta_key, $meta_value );
    }

    /**
     * Fetch encrypted metadata from runtime cache or database.
     *
     * @param int    $object_id Post ID.
     * @param string $meta_key  Meta key.
     *
     * @return string|null
     */
    private function fetch_encrypted_meta( $object_id, $meta_key ) {
        if ( isset( $this->encrypted_meta_cache[ $object_id ][ $meta_key ] ) ) {
            return $this->encrypted_meta_cache[ $object_id ][ $meta_key ];
        }

        global $wpdb;

        if ( ! isset( $wpdb ) || ! isset( $wpdb->postmeta ) ) {
            return null;
        }

        $table = $wpdb->postmeta;

        if ( method_exists( $wpdb, 'prepare' ) ) {
            $sql = $wpdb->prepare(
                "SELECT meta_value FROM {$table} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id DESC LIMIT 1",
                $object_id,
                $meta_key
            );
        } else {
            $object_id = (int) $object_id;
            $meta_key  = addslashes( (string) $meta_key );
            $sql       = "SELECT meta_value FROM {$table} WHERE post_id = {$object_id} AND meta_key = '{$meta_key}' ORDER BY meta_id DESC LIMIT 1";
        }

        if ( method_exists( $wpdb, 'get_var' ) ) {
            $value = $wpdb->get_var( $sql );
        } else {
            $value = null;
        }

        if ( null !== $value ) {
            $this->encrypted_meta_cache[ $object_id ][ $meta_key ] = $value;
        }

        return $value;
    }

    /**
     * Derive plain string representation for encryption.
     *
     * @param mixed $value Value to normalize.
     *
     * @return string
     */
    private function normalize_plain_value( $value ) {
        if ( is_string( $value ) ) {
            return $value;
        }

        if ( is_scalar( $value ) || null === $value ) {
            return (string) $value;
        }

        return maybe_serialize( $value );
    }

    /**
     * Build cache key for metadata cache.
     *
     * @param int    $object_id Post ID.
     * @param string $meta_key  Meta key.
     *
     * @return string
     */
    private function build_cache_key( $object_id, $meta_key ) {
        return $object_id . ':' . $meta_key;
    }

    /**
     * Temporarily suspend metadata interception while executing a callback.
     *
     * @param callable $callback Callback to execute.
     *
     * @return mixed
     */
    private function run_while_suspended( $callback ) {
        $previous        = $this->suspended;
        $this->suspended = true;

        try {
            $result = call_user_func( $callback );
        } finally {
            $this->suspended = $previous;
        }

        return $result;
    }

    /**
     * Cache encrypted and decrypted meta values for reuse within the request.
     *
     * @param int    $object_id Post ID.
     * @param string $meta_key  Meta key.
     * @param mixed  $meta_value Original value.
     * @param string $encrypted Encrypted payload.
     */
    private function remember_meta_value( $object_id, $meta_key, $meta_value, $encrypted ) {
        $cache_key = $this->build_cache_key( $object_id, $meta_key );
        $this->decrypted_meta_cache[ $cache_key ]       = maybe_unserialize( $meta_value );
        $this->encrypted_meta_cache[ $object_id ][ $meta_key ] = $encrypted;
    }

    /**
     * Retrieve an encryption key from managed storage.
     *
     * @param array       $context Contextual information.
     * @param string|null $requested_key_id Specific key identifier if provided.
     *
     * @return array{key: string, key_id: string}
     */
    private function get_encryption_key( $context = array(), $requested_key_id = null ) {
        $cache_key = $requested_key_id ?: 'default';

        if ( isset( $this->managed_keys[ $cache_key ] ) ) {
            return $this->managed_keys[ $cache_key ];
        }

        $context = is_array( $context ) ? $context : array();

        if ( null !== $requested_key_id ) {
            $resolved = apply_filters( 'tts_secure_storage_resolve_key_by_id', null, $requested_key_id, $context );
            if ( is_string( $resolved ) && '' !== $resolved ) {
                $key_material = $this->normalize_key_material( $resolved );
                if ( $key_material ) {
                    $this->managed_keys[ $requested_key_id ] = array(
                        'key'    => $key_material,
                        'key_id' => $requested_key_id,
                    );
                    return $this->managed_keys[ $requested_key_id ];
                }
            }
        }

        $default_source = $this->get_default_key_material();
        $managed        = apply_filters( 'tts_secure_storage_managed_key', $default_source, $context, $requested_key_id );

        $key_string = '';
        $key_id     = 'default';

        if ( is_array( $managed ) ) {
            if ( isset( $managed['key'] ) ) {
                $key_string = (string) $managed['key'];
            }
            if ( isset( $managed['key_id'] ) ) {
                $key_id = (string) $managed['key_id'];
            }
        } elseif ( is_string( $managed ) && '' !== $managed ) {
            $key_string = $managed;
        }

        if ( '' === $key_string && isset( $default_source['key'] ) ) {
            $key_string = (string) $default_source['key'];
        }

        $resolved_secret = $this->resolve_managed_secret( $key_string, $context );
        $key_material    = $this->normalize_key_material( $resolved_secret );

        if ( ! $key_material ) {
            $this->log_security_warning( 'Managed key material could not be resolved.', $context );
            return array( 'key' => '', 'key_id' => $key_id );
        }

        $this->managed_keys[ $cache_key ] = array(
            'key'    => $key_material,
            'key_id' => $key_id,
        );

        return $this->managed_keys[ $cache_key ];
    }

    /**
     * Retrieve default key material based on environment values.
     *
     * @return array{key: string, key_id: string}
     */
    private function get_default_key_material() {
        $candidates = array(
            defined( 'TTS_ENCRYPTION_KEY' ) ? TTS_ENCRYPTION_KEY : '',
            getenv( 'TTS_ENCRYPTION_KEY' ),
            defined( 'AUTH_KEY' ) ? AUTH_KEY : '',
            defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '',
            defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '',
        );

        foreach ( $candidates as $candidate ) {
            if ( is_string( $candidate ) && '' !== $candidate ) {
                return array(
                    'key'    => $candidate,
                    'key_id' => 'default',
                );
            }
        }

        return array(
            'key'    => hash( 'sha256', __FILE__ ),
            'key_id' => 'generated-default',
        );
    }

    /**
     * Normalize key material into a 32-byte binary string.
     *
     * @param mixed $key_material Raw key material.
     *
     * @return string
     */
    private function normalize_key_material( $key_material ) {
        if ( ! is_string( $key_material ) || '' === $key_material ) {
            return '';
        }

        $key_material = trim( $key_material );

        if ( strlen( $key_material ) === 44 ) {
            $decoded = base64_decode( $key_material, true );
            if ( false !== $decoded && 32 === strlen( $decoded ) ) {
                return $decoded;
            }
        }

        if ( preg_match( '/^[a-f0-9]{64}$/i', $key_material ) ) {
            $decoded = pack( 'H*', $key_material );
            if ( 32 === strlen( $decoded ) ) {
                return $decoded;
            }
        }

        if ( strlen( $key_material ) >= 32 ) {
            return substr( $key_material, 0, 32 );
        }

        return hash( 'sha256', $key_material, true );
    }

    /**
     * Mask scalar value by preserving limited context.
     *
     * @param mixed $value Value to mask.
     *
     * @return mixed
     */
    private function mask_scalar( $value ) {
        if ( ! is_scalar( $value ) || '' === (string) $value ) {
            return $value;
        }

        $value = (string) $value;
        $length = strlen( $value );

        if ( $length <= 4 ) {
            return str_repeat( '*', $length );
        }

        $prefix = substr( $value, 0, 3 );
        $suffix = substr( $value, -2 );

        return $prefix . str_repeat( '*', max( 1, $length - 5 ) ) . $suffix;
    }

    /**
     * Log a warning using the plugin logger when available.
     *
     * @param string $message Warning message.
     * @param array  $context Contextual data.
     */
    private function log_security_warning( $message, $context = array() ) {
        if ( class_exists( 'TTS_Logger' ) ) {
            TTS_Logger::log( $message, 'warning', array_merge( (array) $context, array( 'component' => 'secure_storage' ) ) );
        }
    }
}
