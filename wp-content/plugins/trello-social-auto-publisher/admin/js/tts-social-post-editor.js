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
        const $scheduleField = $('#_tts_publish_at');
        const $attachmentsInput = $('#tts_attachment_ids');
        const $summaryPanel = $('#tts-editor-summary');
        const defaultScheduleLabel = $summaryPanel.length ? String($summaryPanel.find('[data-summary="schedule"]').data('default-label') || '') : '';

        const resolveOpenLabel = function() {
            if ( !$openButton.length ) {
                return '';
            }

            const label = $openButton.data('open-label');
            return label ? String(label) : $.trim($openButton.text());
        };

        const defaultOpenLabel = resolveOpenLabel();
        const closeLabel = $openButton.length ? String($openButton.data('close-label') || '') : '';

        const updateOpenButtonLabel = function(open) {
            if ( !$openButton.length ) {
                return;
            }

            if ( open && closeLabel ) {
                $openButton.text(closeLabel);
            } else {
                $openButton.text(defaultOpenLabel);
            }
        };

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
            updateOpenButtonLabel(open);
        };

        if ($openButton.length) {
            updateOpenButtonLabel($editor.hasClass('is-open'));
            $openButton.on('click', function(e) {
                e.preventDefault();
                setEditorState(!$editor.hasClass('is-open'));
            });
        }

        if ($cancelButton.length) {
            $cancelButton.on('click', function(e) {
                e.preventDefault();
                const cancelUrl = $cancelButton.data('cancel-url');
                if (cancelUrl) {
                    window.location.href = cancelUrl;
                    return;
                }
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

        const updateMessageCounter = function($textarea) {
            const id = $textarea.attr('id');
            if (!id) {
                return;
            }

            const $counter = $editor.find('[data-counter-for="' + id + '"]');
            if (!$counter.length) {
                return;
            }

            const length = $textarea.val().length;
            $counter.text(length);
        };

        $editor.on('input', '.tts-channel-message textarea', function() {
            updateMessageCounter($(this));
        });

        $editor.find('.tts-channel-message textarea').each(function() {
            updateMessageCounter($(this));
        });

        const formatScheduleValue = function(value) {
            if (!value) {
                return defaultScheduleLabel || '—';
            }

            // Replace "T" with space for better readability.
            return value.replace('T', ' ');
        };

        const refreshSummary = function() {
            if (!$summaryPanel.length) {
                return;
            }

            const titleValue = $titleField.length ? $.trim($titleField.val()) : '';
            const scheduleValue = $scheduleField.length ? $.trim($scheduleField.val()) : '';
            const attachmentsValue = $attachmentsInput.length ? $.trim($attachmentsInput.val()) : '';

            const activeChannels = $editor.find('.tts-channel-checkbox:checked').length;
            const attachmentCount = attachmentsValue ? attachmentsValue.split(',').filter(Boolean).length : 0;

            $summaryPanel.find('[data-summary="title"]').text(titleValue || '—');
            $summaryPanel.find('[data-summary="schedule"]').text(formatScheduleValue(scheduleValue));
            $summaryPanel.find('[data-summary="channels"]').text(activeChannels);
            $summaryPanel.find('[data-summary="attachments"]').text(attachmentCount);
        };

        if ($titleField.length) {
            $titleField.on('input', refreshSummary);
        }

        if ($scheduleField.length) {
            $scheduleField.on('change', refreshSummary);
        }

        if ($attachmentsInput.length) {
            $attachmentsInput.on('change', refreshSummary);
        }

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

            refreshSummary();
        };

        $editor.on('change', '.tts-channel-checkbox', updateChannelMessages);
        updateChannelMessages();

        const $storyToggle = $('#tts_publish_story');
        if ($storyToggle.length) {
            $storyToggle.trigger('change');
        }

        refreshSummary();
    });
})(jQuery);
