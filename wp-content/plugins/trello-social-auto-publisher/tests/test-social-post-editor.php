<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';

$tests = array(
	'sanitize_request_requires_title'              => function () {
		tts_reset_test_state();

		$admin      = new TTS_Admin();
		$reflection = new ReflectionClass( TTS_Admin::class );
		$method     = $reflection->getMethod( 'sanitize_social_post_request' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$admin,
			array(
				'_tts_social_channel' => array( 'facebook' ),
			),
			'create'
		);

		tts_assert_true( is_wp_error( $result ), 'Sanitization should fail without a title.' );
		tts_assert_equals( 'missing-title', $result->get_error_code(), 'Missing title should trigger the missing-title error code.' );
	},
	'sanitize_request_requires_channel'            => function () {
		tts_reset_test_state();

		$admin      = new TTS_Admin();
		$reflection = new ReflectionClass( TTS_Admin::class );
		$method     = $reflection->getMethod( 'sanitize_social_post_request' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$admin,
			array(
				'tts_post_title' => 'Sample',
			),
			'create'
		);

		tts_assert_true( is_wp_error( $result ), 'At least one channel should be required.' );
		tts_assert_equals( 'missing-channels', $result->get_error_code(), 'Missing channels should trigger the missing-channels error code.' );
	},
	'sanitize_request_returns_clean_payload'       => function () {
		tts_reset_test_state();

		$GLOBALS['tts_current_user_caps'] = array(
			'read' => true,
		);

		$GLOBALS['tts_test_posts'] = array(
			10 => (object) array(
				'ID'        => 10,
				'post_type' => 'attachment',
			),
			11 => (object) array(
				'ID'        => 11,
				'post_type' => 'attachment',
			),
		);

		$admin      = new TTS_Admin();
		$reflection = new ReflectionClass( TTS_Admin::class );
		$method     = $reflection->getMethod( 'sanitize_social_post_request' );
		$method->setAccessible( true );

		$request = array(
			'tts_post_title'               => '  Launch Update  ',
			'_tts_social_channel'          => array( 'facebook', 'instagram', 'facebook', 'invalid' ),
			'_tts_message_facebook'        => 'Great news!',
			'_tts_message_instagram'       => "Don't miss it!",
			'_tts_instagram_first_comment' => str_repeat( 'A', 2300 ),
			'_tts_attachment_ids'          => '10,11,10',
			'_tts_manual_media'            => '11',
			'_tts_publish_story'           => '1',
			'_tts_story_media'             => '10',
			'_tts_lat'                     => '45.1234567',
			'_tts_lng'                     => '12.9876543',
			'tts_content_source'           => 'manual',
			'tts_source_reference'         => '  CARD-99  ',
		);

		$result = $method->invoke( $admin, $request, 'create' );

		tts_assert_true( ! is_wp_error( $result ), 'Valid payload should pass sanitization.' );
		tts_assert_equals( array( 'facebook', 'instagram' ), $result['channels'], 'Channels should be unique and sanitized.' );
		tts_assert_equals( 'Launch Update', $result['title'], 'Title should be trimmed.' );
		tts_assert_equals( array( 10, 11 ), $result['attachment_ids'], 'Attachment ids should be validated and unique.' );
		tts_assert_equals( 11, $result['manual_media'], 'Manual media should reference a validated attachment.' );
		tts_assert_true( $result['publish_story'], 'Story publishing flag should be set.' );
		tts_assert_equals( 10, $result['story_media'], 'Story media should reference a validated attachment.' );
		tts_assert_equals( '45.123457', $result['lat'], 'Latitude should be normalized to six decimals.' );
		tts_assert_equals( '12.987654', $result['lng'], 'Longitude should be normalized to six decimals.' );
		tts_assert_equals( 'manual', $result['content_source'], 'Content source should remain manual.' );
		tts_assert_equals( 'CARD-99', $result['source_reference'], 'Source reference should be trimmed.' );
		tts_assert_equals( str_repeat( 'A', 2200 ), $result['instagram_comment'], 'Instagram comment should be truncated to 2200 characters.' );
	},
	'sanitize_request_rejects_invalid_attachments' => function () {
		tts_reset_test_state();

		$GLOBALS['tts_current_user_caps'] = array(
			'read' => true,
		);

		$GLOBALS['tts_test_posts'] = array(
			10 => (object) array(
				'ID'        => 10,
				'post_type' => 'attachment',
			),
		);

		$admin      = new TTS_Admin();
		$reflection = new ReflectionClass( TTS_Admin::class );
		$method     = $reflection->getMethod( 'sanitize_social_post_request' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$admin,
			array(
				'tts_post_title'      => 'Update',
				'_tts_social_channel' => array( 'facebook' ),
				'_tts_attachment_ids' => '10,999',
			),
			'create'
		);

		tts_assert_true( is_wp_error( $result ), 'Invalid attachments should fail validation.' );
		tts_assert_equals( 'invalid-attachments', $result->get_error_code(), 'Invalid attachments should raise the invalid-attachments error code.' );
	},
	'persist_social_post_meta_updates_fields'      => function () {
		tts_reset_test_state();

		$admin            = new TTS_Admin();
		$reflection       = new ReflectionClass( TTS_Admin::class );
		$sanitize_method  = $reflection->getMethod( 'sanitize_social_post_request' );
		$persist_method   = $reflection->getMethod( 'persist_social_post_meta' );
		$sanitize_method->setAccessible( true );
		$persist_method->setAccessible( true );

		$GLOBALS['tts_current_user_caps'] = array(
			'read' => true,
		);

		$GLOBALS['tts_test_posts'] = array(
			10 => (object) array(
				'ID'        => 10,
				'post_type' => 'attachment',
			),
			11 => (object) array(
				'ID'        => 11,
				'post_type' => 'attachment',
			),
		);

		$request = array(
			'tts_post_title'               => 'Launch',
			'_tts_social_channel'          => array( 'facebook', 'instagram' ),
			'_tts_attachment_ids'          => '10,11',
			'_tts_manual_media'            => '11',
			'_tts_publish_story'           => '1',
			'_tts_story_media'             => '10',
			'_tts_message_facebook'        => 'Hello!',
			'_tts_message_instagram'       => 'Hi!',
			'_tts_instagram_first_comment' => 'First comment',
			'_tts_lat'                     => '45.1',
			'_tts_lng'                     => '12.9',
		);

		$payload = $sanitize_method->invoke( $admin, $request, 'create' );
		tts_assert_true( ! is_wp_error( $payload ), 'Payload should be valid before persisting meta.' );

		$post_id = 501;
		$persist_method->invoke( $admin, $post_id, $payload );

		tts_assert_equals( array( 'facebook', 'instagram' ), get_post_meta( $post_id, '_tts_social_channel', true ), 'Channels meta should be stored.' );
		tts_assert_equals( array( 10, 11 ), get_post_meta( $post_id, '_tts_attachment_ids', true ), 'Attachment meta should be stored as an array.' );
		tts_assert_equals( 11, get_post_meta( $post_id, '_tts_manual_media', true ), 'Manual media meta should be stored.' );
		tts_assert_true( (bool) get_post_meta( $post_id, '_tts_publish_story', true ), 'Story publish flag should be stored.' );
		tts_assert_equals( 10, get_post_meta( $post_id, '_tts_story_media', true ), 'Story media meta should be stored.' );
		tts_assert_equals( 'First comment', get_post_meta( $post_id, '_tts_instagram_first_comment', true ), 'Instagram comment should be stored.' );
		tts_assert_equals( '45.1', get_post_meta( $post_id, '_tts_lat', true ), 'Latitude meta should be normalized.' );
		tts_assert_equals( '12.9', get_post_meta( $post_id, '_tts_lng', true ), 'Longitude meta should be normalized.' );
	},
);

$failures = 0;
$messages = array();

echo "Running social post editor tests\n";

foreach ( $tests as $name => $callback ) {
	try {
		$callback();
		echo '.';
	} catch ( Throwable $e ) {
		++$failures;
		$messages[] = $name . ': ' . $e->getMessage();
		echo 'F';
	}
}

echo "\n";

if ( $failures > 0 ) {
	echo "Failures: \n";
	foreach ( $messages as $message ) {
		echo ' - ' . $message . "\n";
	}
	exit( 1 );
}

echo "All tests passed!\n";
