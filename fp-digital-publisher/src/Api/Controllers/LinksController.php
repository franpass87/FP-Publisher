<?php

declare(strict_types=1);

namespace FP\Publisher\Api\Controllers;

use FP\Publisher\Services\Links;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function register_rest_route;
use function sanitize_text_field;
use function trim;
use function wp_unslash;

/**
 * Controller per la gestione dei short link
 */
final class LinksController extends BaseController
{
    public static function register(): void
    {
        // GET /links - Lista tutti i link
        register_rest_route(
            self::NAMESPACE,
            '/links',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'getLinks'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_links'),
                ],
            ]
        );

        // POST /links - Crea o aggiorna un link
        register_rest_route(
            self::NAMESPACE,
            '/links',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'saveLink'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_links'),
                ],
            ]
        );

        // DELETE /links - Elimina un link
        register_rest_route(
            self::NAMESPACE,
            '/links',
            [
                [
                    'methods' => 'DELETE',
                    'callback' => [self::class, 'deleteLink'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_links'),
                ],
            ]
        );
    }

    public static function getLinks(): WP_REST_Response
    {
        $links = Links::getAll();
        return self::success(['items' => $links]);
    }

    public static function saveLink(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $slug = sanitize_text_field(wp_unslash($request->get_param('slug') ?? ''));
        $target = trim(wp_unslash($request->get_param('target_url') ?? ''));

        if ($slug === '') {
            return self::error('missing_slug', 'Lo slug è obbligatorio.');
        }

        if ($target === '') {
            return self::error('missing_target', 'L\'URL di destinazione è obbligatorio.');
        }

        Links::save($slug, $target);
        return self::success(['slug' => $slug], 201);
    }

    public static function deleteLink(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $slug = sanitize_text_field(wp_unslash($request->get_param('slug') ?? ''));

        if ($slug === '') {
            return self::error('missing_slug', 'Lo slug è obbligatorio.');
        }

        Links::delete($slug);
        return self::success(['deleted' => true]);
    }
}