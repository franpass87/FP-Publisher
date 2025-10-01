<?php

declare(strict_types=1);

if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (! defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (! class_exists('wpdb')) {
    class wpdb
    {
    }
}

if (! class_exists('WP_User')) {
    class WP_User
    {
    }
}

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (! defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

if (! function_exists('wp_stub_reset')) {
    $GLOBALS['wp_stub_options'] = [];
    $GLOBALS['wp_stub_actions'] = [];
    $GLOBALS['wp_stub_filters'] = [];
    $GLOBALS['wp_stub_next_post_id'] = 1000;
    $GLOBALS['wp_stub_fail_insert_post'] = false;
    $GLOBALS['wp_stub_insert_post_error'] = 'WP insert error.';
    $GLOBALS['wp_stub_http_queue'] = [];
    $GLOBALS['wp_stub_http_log'] = [];
    $GLOBALS['wp_stub_filter_callbacks'] = [];
    $GLOBALS['wp_stub_terms'] = [];
    $GLOBALS['wp_stub_term_calls'] = [
        'get_terms' => 0,
        'wp_insert_term' => 0,
        'term_exists' => 0,
    ];
    $GLOBALS['wp_stub_cache'] = [];
    $GLOBALS['wp_stub_upload_dir'] = null;
    $GLOBALS['wp_stub_cron_events'] = [];
    $GLOBALS['wp_stub_next_term_id'] = 2000;
    $GLOBALS['wp_stub_options_autoload'] = [];
    $GLOBALS['wp_stub_current_user_id'] = 0;
    $GLOBALS['wp_stub_current_user_caps'] = [];
    $GLOBALS['wp_stub_json_encode_should_fail'] = false;
    $GLOBALS['wp_stub_users'] = [];
    $GLOBALS['wp_stub_sent_mail'] = [];
    $GLOBALS['wp_stub_update_option_failures'] = [];
    $GLOBALS['wp_stub_nonce_result'] = 0;
    $GLOBALS['wp_stub_transients'] = [];

    function wp_stub_reset(): void
    {
        $GLOBALS['wp_stub_options'] = [];
        $GLOBALS['wp_stub_actions'] = [];
        $GLOBALS['wp_stub_filters'] = [];
        $GLOBALS['wp_stub_next_post_id'] = 1000;
        $GLOBALS['wp_stub_fail_insert_post'] = false;
        $GLOBALS['wp_stub_insert_post_error'] = 'WP insert error.';
        wp_stub_http_reset();
        $GLOBALS['wp_stub_filter_callbacks'] = [];
        $GLOBALS['wp_stub_terms'] = [];
        $GLOBALS['wp_stub_term_calls'] = [
            'get_terms' => 0,
            'wp_insert_term' => 0,
            'term_exists' => 0,
        ];
        $GLOBALS['wp_stub_cache'] = [];
        $GLOBALS['wp_stub_upload_dir'] = null;
        $GLOBALS['wp_stub_cron_events'] = [];
        $GLOBALS['wp_stub_next_term_id'] = 2000;
        $GLOBALS['wp_stub_options_autoload'] = [];
        $GLOBALS['wp_stub_current_user_id'] = 0;
        $GLOBALS['wp_stub_current_user_caps'] = [];
        $GLOBALS['wp_stub_json_encode_should_fail'] = false;
        $GLOBALS['wp_stub_users'] = [];
        $GLOBALS['wp_stub_sent_mail'] = [];
        $GLOBALS['wp_stub_nonce_result'] = 0;
        $GLOBALS['wp_stub_update_option_failures'] = [];
        $GLOBALS['wp_stub_transients'] = [];
    }

    function wp_stub_terms_reset(): void
    {
        $GLOBALS['wp_stub_terms'] = [];
        wp_stub_terms_reset_calls();
    }

    function wp_stub_terms_seed(string $taxonomy, string $name, ?int $termId = null): int
    {
        $taxonomy = sanitize_key($taxonomy);
        $key = sanitize_key($name);
        $termId ??= $GLOBALS['wp_stub_next_term_id']++;

        $GLOBALS['wp_stub_terms'][$taxonomy]['by_id'][$termId] = [
            'term_id' => $termId,
            'name' => $name,
            'slug' => sanitize_title($name),
        ];
        $GLOBALS['wp_stub_terms'][$taxonomy]['by_key'][$key] = $termId;

        return $termId;
    }

    function wp_stub_terms_reset_calls(): void
    {
        $GLOBALS['wp_stub_term_calls'] = [
            'get_terms' => 0,
            'wp_insert_term' => 0,
            'term_exists' => 0,
        ];
    }

    function wp_stub_terms_call_count(string $function): int
    {
        return $GLOBALS['wp_stub_term_calls'][$function] ?? 0;
    }

    function wp_stub_reset_options(): void
    {
        $GLOBALS['wp_stub_options'] = [];
    }

    function wp_stub_register_user(array $user): void
    {
        $defaults = [
            'ID' => 0,
            'display_name' => '',
            'user_login' => '',
            'user_nicename' => '',
            'user_email' => '',
        ];

        $user = array_merge($defaults, $user);

        $GLOBALS['wp_stub_users']['login:' . strtolower($user['user_login'])] = $user;
        $GLOBALS['wp_stub_users']['slug:' . strtolower($user['user_nicename'] ?: $user['user_login'])] = $user;
    }

    function wp_stub_last_mail(): array
    {
        if ($GLOBALS['wp_stub_sent_mail'] === []) {
            return [];
        }

        return $GLOBALS['wp_stub_sent_mail'][array_key_last($GLOBALS['wp_stub_sent_mail'])];
    }

    function wp_stub_set_json_encode_failure(bool $shouldFail): void
    {
        $GLOBALS['wp_stub_json_encode_should_fail'] = $shouldFail;
    }

    function wp_stub_set_insert_post_failure(bool $fail, string $message = 'WP insert error.'): void
    {
        $GLOBALS['wp_stub_fail_insert_post'] = $fail;
        $GLOBALS['wp_stub_insert_post_error'] = $message;
    }

    function wp_stub_set_nonce_result(int $result): void
    {
        $GLOBALS['wp_stub_nonce_result'] = $result;
    }

    function wp_stub_http_reset(): void
    {
        $GLOBALS['wp_stub_http_queue'] = [];
        $GLOBALS['wp_stub_http_log'] = [];
    }

    /**
     * @param array{method?: string|null, url?: string|null, response: array} $item
     */
    function wp_stub_http_queue_response(array $item): void
    {
        $defaults = [
            'method' => null,
            'url' => null,
        ];

        $GLOBALS['wp_stub_http_queue'][] = array_merge($defaults, $item);
    }

    function wp_stub_http_last_request(): array
    {
        if ($GLOBALS['wp_stub_http_log'] === []) {
            return [];
        }

        return $GLOBALS['wp_stub_http_log'][array_key_last($GLOBALS['wp_stub_http_log'])];
    }

    function wp_cache_get(string $key, string $group = ''): mixed
    {
        return $GLOBALS['wp_stub_cache'][$group][$key] ?? false;
    }

    function wp_cache_set(string $key, mixed $value, string $group = '', int $ttl = 0): bool
    {
        $GLOBALS['wp_stub_cache'][$group][$key] = $value;

        return true;
    }

    function wp_cache_delete(string $key, string $group = ''): bool
    {
        if (isset($GLOBALS['wp_stub_cache'][$group][$key])) {
            unset($GLOBALS['wp_stub_cache'][$group][$key]);

            return true;
        }

        return false;
    }

    function wp_stub_set_upload_dir(string $path): void
    {
        $GLOBALS['wp_stub_upload_dir'] = $path;
    }

    function wp_upload_dir(array $args = []): array
    {
        $baseDir = $GLOBALS['wp_stub_upload_dir'] ?? sys_get_temp_dir() . '/wp-uploads';
        if (! is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        return [
            'path' => $baseDir,
            'url' => 'https://example.com/wp-content/uploads',
            'subdir' => '',
            'basedir' => $baseDir,
            'baseurl' => 'https://example.com/wp-content/uploads',
            'error' => '',
        ];
    }

    function wp_next_scheduled(string $hook): int|false
    {
        return $GLOBALS['wp_stub_cron_events'][$hook] ?? false;
    }

    function wp_schedule_event(int $timestamp, string $recurrence, string $hook): void
    {
        $GLOBALS['wp_stub_cron_events'][$hook] = $timestamp;
    }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        private string $code;
        private string $message;
        private mixed $data;

        public function __construct(string $code, string $message, mixed $data = null)
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_data(): mixed
        {
            return $this->data;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }
    }
}

if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /**
         * @param array<string, mixed> $params
         */
        public function __construct(private array $params = [], private array $headers = [])
        {
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function set_param(string $key, mixed $value): void
        {
            $this->params[$key] = $value;
        }

        public function set_header(string $key, mixed $value): void
        {
            $this->headers[strtolower($key)] = $value;
        }

        public function get_header(string $key): mixed
        {
            $normalized = strtolower($key);
            if (isset($this->headers[$normalized])) {
                return $this->headers[$normalized];
            }

            if ($normalized === 'x-wp-nonce' && isset($this->params['_wpnonce'])) {
                return $this->params['_wpnonce'];
            }

            return null;
        }

        /**
         * @return array<string, mixed>
         */
        public function get_json_params(): array
        {
            return $this->params;
        }

        /**
         * @return array<string, mixed>
         */
        public function get_params(): array
        {
            return $this->params;
        }
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        $key = strtolower($key);

        return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?? $value;

        return trim($value);
    }
}

if (! function_exists('submit_button')) {
    function submit_button(string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true): void
    {
        echo '<button type="submit" name="' . esc_html($name) . '" class="button button-' . esc_html($type) . '">'
            . esc_html($text) . '</button>';
        if ($wrap) {
            echo "\n";
        }
    }
}

if (! function_exists('wp_die')) {
    function wp_die(string $message = ''): void
    {
        throw new \RuntimeException($message !== '' ? $message : 'wp_die called');
    }
}

if (! function_exists('sanitize_title')) {
    function sanitize_title(string $title): string
    {
        $title = strtolower(trim(strip_tags($title)));
        $title = preg_replace('/[^a-z0-9_\-]+/', '-', $title) ?? $title;

        return trim($title, '-');
    }
}

if (! function_exists('sanitize_file_name')) {
    function sanitize_file_name(string $filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9\._\-]/', '', $filename) ?? '';

        return trim($filename, '.');
    }
}

if (! function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (! function_exists('get_transient')) {
    function get_transient(string $key): mixed
    {
        return $GLOBALS['wp_stub_transients'][$key] ?? false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient(string $key, mixed $value, int $expiration): bool
    {
        $GLOBALS['wp_stub_transients'][$key] = $value;

        return true;
    }
}

if (! function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        unset($GLOBALS['wp_stub_transients'][$key]);

        return true;
    }
}

if (! function_exists('is_email')) {
    function is_email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (! function_exists('__')) {
    function __($text, $domain = ''): string
    {
        return is_string($text) ? $text : '';
    }
}

if (! function_exists('esc_html')) {
    function esc_html(mixed $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__($text, $domain = ''): string
    {
        return esc_html(__($text, $domain));
    }
}

if (! function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = ''): void
    {
        echo esc_html__($text, $domain);
    }
}

if (! function_exists('add_option')) {
    function add_option(string $name, mixed $value = '', mixed $deprecated = '', mixed $autoload = 'yes'): bool
    {
        $GLOBALS['wp_stub_options'][$name] = $value;
        $GLOBALS['wp_stub_options_autoload'][$name] = normalize_wp_stub_autoload($autoload);

        return true;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $name, mixed $value, mixed $autoload = null): bool
    {
        if ($GLOBALS['wp_stub_update_option_failures'][$name] ?? false) {
            return false;
        }

        $GLOBALS['wp_stub_options'][$name] = $value;

        if ($autoload !== null) {
            $GLOBALS['wp_stub_options_autoload'][$name] = normalize_wp_stub_autoload($autoload);
        }

        return true;
    }
}

if (! function_exists('wp_stub_set_update_option_failure')) {
    function wp_stub_set_update_option_failure(string $name, bool $shouldFail): void
    {
        $GLOBALS['wp_stub_update_option_failures'][$name] = $shouldFail;
    }
}

if (! function_exists('normalize_wp_stub_autoload')) {
    function normalize_wp_stub_autoload(mixed $autoload): string
    {
        if ($autoload === true || $autoload === 'yes') {
            return 'yes';
        }

        if ($autoload === false || $autoload === 'no') {
            return 'no';
        }

        return (string) $autoload;
    }
}

if (! function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed
    {
        return $GLOBALS['wp_stub_options'][$name] ?? $default;
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return ($GLOBALS['wp_stub_current_user_caps'][$capability] ?? false)
            || ($GLOBALS['wp_stub_current_user_caps']['manage_options'] ?? false);
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return $GLOBALS['wp_stub_current_user_id'] ?? 0;
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        $GLOBALS['wp_stub_filters'][$hook][] = $value;

        if (! isset($GLOBALS['wp_stub_filter_callbacks'][$hook])) {
            return $value;
        }

        $arguments = [$value, ...$args];

        foreach ($GLOBALS['wp_stub_filter_callbacks'][$hook] as $callbacks) {
            foreach ($callbacks as [$callback, $acceptedArgs]) {
                $value = $callback(...array_slice($arguments, 0, $acceptedArgs));
                $arguments[0] = $value;
            }
        }

        return $value;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void
    {
        $GLOBALS['wp_stub_actions'][$hook][] = $args;
    }
}

if (! function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        // no-op
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        if (! isset($GLOBALS['wp_stub_filter_callbacks'][$hook])) {
            $GLOBALS['wp_stub_filter_callbacks'][$hook] = [];
        }

        if (! isset($GLOBALS['wp_stub_filter_callbacks'][$hook][$priority])) {
            $GLOBALS['wp_stub_filter_callbacks'][$hook][$priority] = [];
        }

        $GLOBALS['wp_stub_filter_callbacks'][$hook][$priority][] = [$callback, $acceptedArgs];
        ksort($GLOBALS['wp_stub_filter_callbacks'][$hook]);
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $options = 0, int $depth = 512): string|false
    {
        if (! empty($GLOBALS['wp_stub_json_encode_should_fail'])) {
            return false;
        }

        $encoded = json_encode($value, $options ?: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, $depth);

        return $encoded === false ? false : $encoded;
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var(trim($url), FILTER_SANITIZE_URL) ?: '';
    }
}

if (! function_exists('wp_http_validate_url')) {
    function wp_http_validate_url(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

if (! function_exists('add_query_arg')) {
    function add_query_arg(array $params, string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $query = http_build_query($params);
        if ($query === '') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . $query;
    }
}

if (! function_exists('get_current_blog_id')) {
    function get_current_blog_id(): int
    {
        return 1;
    }
}

if (! function_exists('is_multisite')) {
    function is_multisite(): bool
    {
        return false;
    }
}

if (! function_exists('switch_to_blog')) {
    function switch_to_blog(int $blogId): void
    {
        // no-op
    }
}

if (! function_exists('restore_current_blog')) {
    function restore_current_blog(mixed $switched): void
    {
        // no-op
    }
}

if (! function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}

if (! function_exists('wp_insert_post')) {
    function wp_insert_post(array $data, bool $wpError = false): int|WP_Error
    {
        if ($GLOBALS['wp_stub_fail_insert_post']) {
            return $wpError ? new WP_Error('insert_failed', $GLOBALS['wp_stub_insert_post_error']) : 0;
        }

        $GLOBALS['wp_stub_next_post_id']++;

        return $GLOBALS['wp_stub_next_post_id'];
    }
}

if (! function_exists('wp_set_post_terms')) {
    function wp_set_post_terms(int $postId, array $terms, string $taxonomy, bool $append = false): array
    {
        return $terms;
    }
}

if (! function_exists('get_terms')) {
    function get_terms(array|string $args = []): array|WP_Error
    {
        $GLOBALS['wp_stub_term_calls']['get_terms']++;

        $taxonomy = '';
        $names = [];

        if (is_string($args)) {
            $taxonomy = sanitize_key($args);
        } elseif (isset($args['taxonomy'])) {
            $taxonomy = sanitize_key(is_array($args['taxonomy']) ? ($args['taxonomy'][0] ?? '') : (string) $args['taxonomy']);
        }

        if (isset($args['name__in'])) {
            $names = array_map('sanitize_text_field', (array) $args['name__in']);
        }

        $store = $GLOBALS['wp_stub_terms'][$taxonomy]['by_id'] ?? [];

        if ($names === []) {
            return array_values($store);
        }

        $matching = [];
        foreach ($store as $term) {
            if (in_array($term['name'], $names, true)) {
                $matching[] = (object) $term;
            }
        }

        return $matching;
    }
}

if (! function_exists('term_exists')) {
    function term_exists(string $term, string $taxonomy): int|array|false
    {
        $GLOBALS['wp_stub_term_calls']['term_exists']++;

        $taxonomy = sanitize_key($taxonomy);
        $store = $GLOBALS['wp_stub_terms'][$taxonomy] ?? [];

        if (is_numeric($term)) {
            $termId = (int) $term;

            return isset($store['by_id'][$termId]) ? $termId : false;
        }

        $key = sanitize_key($term);
        if (isset($store['by_key'][$key])) {
            $termId = (int) $store['by_key'][$key];

            return ['term_id' => $termId];
        }

        return false;
    }
}

if (! function_exists('wp_insert_term')) {
    function wp_insert_term(string $term, string $taxonomy, array $args = []): array|WP_Error
    {
        $GLOBALS['wp_stub_term_calls']['wp_insert_term']++;

        $taxonomy = sanitize_key($taxonomy);
        $name = sanitize_text_field($term);
        $slug = isset($args['slug']) ? sanitize_title((string) $args['slug']) : sanitize_title($name);

        $termId = wp_stub_terms_seed($taxonomy, $name, $GLOBALS['wp_stub_next_term_id']++);
        $GLOBALS['wp_stub_terms'][$taxonomy]['by_id'][$termId]['slug'] = $slug;

        return ['term_id' => $termId];
    }
}

if (! function_exists('update_post_meta')) {
    function update_post_meta(int $postId, string $key, mixed $value): void
    {
        // no-op
    }
}

if (! function_exists('set_post_thumbnail')) {
    function set_post_thumbnail(int $postId, int $thumbnailId): void
    {
        // no-op
    }
}

if (! function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1): mixed
    {
        return parse_url($url, $component);
    }
}

if (! function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $target): bool
    {
        if (is_dir($target)) {
            return true;
        }

        return mkdir($target, 0777, true);
    }
}

if (! function_exists('wp_unique_filename')) {
    function wp_unique_filename(string $dir, string $filename): string
    {
        $filename = sanitize_file_name($filename);
        if ($filename === '') {
            $filename = 'file';
        }

        $candidate = $filename;
        $count = 1;
        $basePath = rtrim($dir, "/\\");

        while (is_file($basePath . '/' . $candidate)) {
            $dot = strrpos($filename, '.');
            if ($dot === false) {
                $candidate = $filename . '-' . $count;
            } else {
                $name = substr($filename, 0, $dot);
                $extension = substr($filename, $dot);
                $candidate = $name . '-' . $count . $extension;
            }
            $count++;
        }

        return $candidate;
    }
}

if (! function_exists('wp_remote_request')) {
    function wp_remote_request(string $url, array $args = []): array
    {
        $method = strtoupper((string) ($args['method'] ?? 'GET'));
        $queue = $GLOBALS['wp_stub_http_queue'] ?? [];
        $response = null;

        foreach ($queue as $index => $item) {
            $matchesMethod = $item['method'] === null || strtoupper((string) $item['method']) === $method;
            $matchesUrl = $item['url'] === null || (string) $item['url'] === $url;

            if ($matchesMethod && $matchesUrl) {
                $response = $item['response'];
                array_splice($GLOBALS['wp_stub_http_queue'], $index, 1);
                break;
            }
        }

        $GLOBALS['wp_stub_http_log'][] = [
            'url' => $url,
            'args' => $args,
        ];

        if ($response !== null) {
            return $response;
        }

        return [
            'response' => ['code' => 200],
            'body' => '',
        ];
    }
}

if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(array $response): string
    {
        return (string) ($response['body'] ?? '');
    }
}

if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int
    {
        return (int) ($response['response']['code'] ?? 0);
    }
}

if (! function_exists('wp_http_build_url')) {
    function wp_http_build_url(string $url): string
    {
        return $url;
    }
}

if (! function_exists('trailingslashit')) {
    function trailingslashit(string $value): string
    {
        return rtrim($value, '/') . '/';
    }
}

if (! function_exists('wp_validate_boolean')) {
    function wp_validate_boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        return $value;
    }
}

if (! function_exists('wp_kses_post')) {
    function wp_kses_post(string $value): string
    {
        return $value;
    }
}

if (! function_exists('absint')) {
    function absint(mixed $value): int
    {
        return (int) abs((int) $value);
    }
}
if (! function_exists('get_user_by')) {
    function get_user_by(string $field, string $value): WP_User|false
    {
        $key = strtolower(trim($value));
        $user = null;

        if ($field === 'login') {
            $user = $GLOBALS['wp_stub_users']['login:' . $key] ?? null;
        } elseif ($field === 'slug') {
            $user = $GLOBALS['wp_stub_users']['slug:' . $key] ?? null;
        }

        if ($user === null) {
            return false;
        }

        $wpUser = new WP_User();
        foreach ($user as $prop => $value) {
            $wpUser->$prop = $value;
        }

        return $wpUser;
    }
}

if (! function_exists('wp_mail')) {
    function wp_mail(string $to, string $subject, string $message): bool
    {
        $GLOBALS['wp_stub_sent_mail'][] = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
        ];

        return true;
    }
}

if (! function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action = ''): int
    {
        return (int) ($GLOBALS['wp_stub_nonce_result'] ?? 0);
    }
}
