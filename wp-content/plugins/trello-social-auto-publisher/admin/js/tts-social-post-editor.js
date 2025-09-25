(function($) {
    'use strict';

    $(function() {
        const $editor = $('#tts-social-post-editor');
        if (!$editor.length) {
            return;
        }

        const $openButton = $('#tts-open-social-post-editor');
        const $cancelButton = $('#tts-cancel-social-post-editor');
        const $titleField = $('#tts_post_title');

        const setEditorState = function(open) {
            if (open) {
                $editor.addClass('is-open');
                $openButton.attr('aria-expanded', 'true');
                if ($titleField.length) {
                    setTimeout(function() {
                        $titleField.trigger('focus');
                    }, 100);
                }
            } else {
                $editor.removeClass('is-open');
                $openButton.attr('aria-expanded', 'false');
            }
        };

        if ($openButton.length) {
            $openButton.on('click', function(e) {
                e.preventDefault();
                setEditorState(!$editor.hasClass('is-open'));
            });
        }

        if ($cancelButton.length) {
            $cancelButton.on('click', function(e) {
                e.preventDefault();
                setEditorState(false);
            });
        }

        const openByDefault = String($editor.data('open')) === '1';
        setEditorState(openByDefault);

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $editor.hasClass('is-open')) {
                setEditorState(false);
            }
        });

        const updateChannelMessages = function() {
            const activeChannels = {};
            $editor.find('.tts-channel-checkbox').each(function() {
                const $checkbox = $(this);
                activeChannels[$checkbox.val()] = $checkbox.is(':checked');
            });

            $editor.find('.tts-channel-message').each(function() {
                const $message = $(this);
                const channel = $message.data('channel');
                const isActive = !!activeChannels[channel];
                $message.toggleClass('is-active', isActive);
            });
        };

        $editor.on('change', '.tts-channel-checkbox', updateChannelMessages);
        updateChannelMessages();

        const $storyToggle = $('#tts_publish_story');
        if ($storyToggle.length) {
            $storyToggle.trigger('change');
        }
    });
})(jQuery);
