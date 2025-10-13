<?php

declare(strict_types=1);

namespace FP\Publisher\Services;

use DateTimeImmutable;
use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Support\Channels;
use FP\Publisher\Support\Logging\Logger;

use function array_map;
use function hash;
use function is_array;

final class MultiChannelPublisher
{
    /**
     * Pubblica su più canali simultaneamente
     *
     * @param array<string, mixed> $planData
     * @param array<int, string> $selectedChannels
     * @param array<string, mixed> $basePayload
     * @return array<string, array<string, mixed>>
     */
    public static function publishToChannels(
        array $planData,
        array $selectedChannels,
        array $basePayload,
        DateTimeImmutable $publishAt,
        ?int $clientId = null
    ): array {
        $plan = PostPlan::create($planData);
        $results = [];

        foreach ($selectedChannels as $channel) {
            $normalizedChannel = Channels::normalize($channel);
            
            // Ottimizza payload per canale specifico
            $optimizedPayload = self::optimizeForChannel($normalizedChannel, $basePayload, $plan);

            // Genera idempotency key unico
            $idempotencyKey = self::generateIdempotencyKey($plan, $normalizedChannel, $publishAt);

            try {
                // Enqueue job
                $job = Queue::enqueue(
                    channel: $normalizedChannel,
                    payload: $optimizedPayload,
                    runAt: $publishAt,
                    idempotencyKey: $idempotencyKey,
                    childJobId: null,
                    clientId: $clientId
                );

                $results[$normalizedChannel] = [
                    'success' => true,
                    'job_id' => $job['id'] ?? null,
                    'status' => $job['status'] ?? null,
                ];

                Logger::get()->info('Job enqueued for channel', [
                    'channel' => $normalizedChannel,
                    'job_id' => $job['id'] ?? null,
                    'client_id' => $clientId,
                ]);
            } catch (\Throwable $e) {
                $results[$normalizedChannel] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                Logger::get()->error('Failed to enqueue job for channel', [
                    'channel' => $normalizedChannel,
                    'error' => $e->getMessage(),
                    'client_id' => $clientId,
                ]);
            }
        }

        return $results;
    }

    /**
     * Ottimizza payload per canale specifico
     *
     * @param array<string, mixed> $basePayload
     * @return array<string, mixed>
     */
    private static function optimizeForChannel(
        string $channel,
        array $basePayload,
        PostPlan $plan
    ): array {
        $payload = $basePayload;
        $payload['plan'] = $plan->toArray();

        // Ottimizzazioni specifiche per canale
        switch ($channel) {
            case 'meta_instagram':
                $payload = self::optimizeForInstagram($payload, $plan);
                break;
            case 'meta_facebook':
                $payload = self::optimizeForFacebook($payload, $plan);
                break;
            case 'youtube':
                $payload = self::optimizeForYouTube($payload, $plan);
                break;
            case 'tiktok':
                $payload = self::optimizeForTikTok($payload, $plan);
                break;
            case 'google_business':
                $payload = self::optimizeForGoogleBusiness($payload, $plan);
                break;
            case 'wordpress_blog':
                $payload = self::optimizeForWordPress($payload, $plan);
                break;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function optimizeForInstagram(array $payload, PostPlan $plan): array
    {
        // Aggiungi first comment se presente
        if ($plan->igFirstComment() !== null) {
            $payload['ig_first_comment'] = $plan->igFirstComment();
        }

        // Determina se è Reel basato su asset
        $assets = $plan->assets();
        if (! empty($assets)) {
            $firstAsset = $assets[0];
            $meta = $firstAsset->meta();
            
            if (isset($meta['duration']) && is_numeric($meta['duration'])) {
                $duration = (float) $meta['duration'];
                if ($duration > 0 && $duration <= 90) {
                    $payload['is_reel'] = true;
                }
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function optimizeForFacebook(array $payload, PostPlan $plan): array
    {
        // Facebook può gestire link automaticamente
        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function optimizeForYouTube(array $payload, PostPlan $plan): array
    {
        // Determina se è Short
        $assets = $plan->assets();
        if (! empty($assets)) {
            $firstAsset = $assets[0];
            $meta = $firstAsset->meta();
            
            $duration = isset($meta['duration']) ? (float) $meta['duration'] : 0;
            $height = isset($meta['height']) ? (int) $meta['height'] : 0;
            $width = isset($meta['width']) ? (int) $meta['width'] : 0;

            $isVertical = $height > $width;
            $isShort = $duration > 0 && $duration <= 60;

            if ($isShort && $isVertical) {
                $payload['is_short'] = true;
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function optimizeForTikTok(array $payload, PostPlan $plan): array
    {
        // TikTok ottimizzazione caption
        if (isset($payload['caption'])) {
            $caption = (string) $payload['caption'];
            // Limita a 2200 caratteri
            if (strlen($caption) > 2200) {
                $payload['caption'] = substr($caption, 0, 2200);
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function optimizeForGoogleBusiness(array $payload, PostPlan $plan): array
    {
        // Imposta type di default se non presente
        if (! isset($payload['type'])) {
            $payload['type'] = 'WHAT_NEW';
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function optimizeForWordPress(array $payload, PostPlan $plan): array
    {
        // WordPress usa i template
        if (! isset($payload['title_template'])) {
            $template = $plan->template();
            $payload['title_template'] = $template->title();
        }

        return $payload;
    }

    private static function generateIdempotencyKey(
        PostPlan $plan,
        string $channel,
        DateTimeImmutable $publishAt
    ): string {
        $parts = [
            'plan_' . ($plan->id() ?? 'draft'),
            $channel,
            $publishAt->format('YmdHis'),
        ];

        return hash('sha256', implode('|', $parts));
    }
}
