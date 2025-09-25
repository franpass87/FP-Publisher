(function($){
    $(function(){
        var frame;
        var storyFrame;
        var $attachmentsContainer = $('.tts-attachments');
        var $attachmentsList = $('#tts_attachments_list');
        var $attachmentIdsInput = $('#tts_attachment_ids');
        var $manualMediaInput = $('#tts_manual_media');
        var $emptyState = $('#tts_attachments_empty');
        var $clearButton = $('.tts-clear-attachments');

        if ( !$attachmentsList.length ) {
            return;
        }

        var labels = {
            makePrimary: String($attachmentsContainer.data('make-primary-label') || 'Imposta come principale'),
            remove: String($attachmentsContainer.data('remove-label') || 'Rimuovi'),
            primary: String($attachmentsContainer.data('primary-label') || 'Primario')
        };

        function escapeHtml(str){
            return String(str).replace(/[&<>"']/g, function(char){
                var entityMap = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                };
                return entityMap[char] || char;
            });
        }

        function getAttachmentIds(){
            var ids = [];
            $attachmentsList.children('.tts-attachment-item').each(function(){
                var id = parseInt($(this).data('id'), 10);
                if (id) {
                    ids.push(id);
                }
            });
            return ids;
        }

        function setPrimary(id){
            var numericId = parseInt(id, 10) || 0;
            $manualMediaInput.val(numericId > 0 ? numericId : '');
            $attachmentsList.children('.tts-attachment-item').each(function(){
                var $item = $(this);
                var currentId = parseInt($item.data('id'), 10);
                $item.toggleClass('is-primary', currentId === numericId && numericId > 0);
            });
        }

        function ensurePrimary(ids){
            var primary = parseInt($manualMediaInput.val(), 10) || 0;
            if (!ids.length) {
                setPrimary(0);
                return;
            }

            if (!primary || ids.indexOf(primary) === -1) {
                setPrimary(ids[0]);
            } else {
                setPrimary(primary);
            }
        }

        function toggleEmptyState(count){
            var hasItems = count > 0;
            if ($emptyState.length) {
                $emptyState.toggle(!hasItems);
            }
            if ($clearButton.length) {
                $clearButton.toggle(hasItems);
            }
            if ($attachmentsContainer.length) {
                $attachmentsContainer.attr('data-empty', hasItems ? '0' : '1');
            }
        }

        function updateAttachmentInputs(){
            var ids = getAttachmentIds();
            ensurePrimary(ids);
            $attachmentIdsInput.val(ids.join(','));
            toggleEmptyState(ids.length);
            $attachmentIdsInput.trigger('change');
        }

        function resolveThumbnail(attachment){
            if (attachment.sizes) {
                if (attachment.sizes.medium) {
                    return attachment.sizes.medium.url;
                }
                if (attachment.sizes.thumbnail) {
                    return attachment.sizes.thumbnail.url;
                }
            }
            return attachment.url || attachment.icon || '';
        }

        function renderAttachmentItem(attachment){
            var id = parseInt(attachment.id, 10);
            if (!id) {
                return null;
            }

            var existing = $attachmentsList.find('.tts-attachment-item[data-id="' + id + '"]').length > 0;
            if (existing) {
                return null;
            }

            var title = attachment.title || attachment.filename || ('Media #' + id);
            var thumbUrl = resolveThumbnail(attachment);
            var thumbHtml = thumbUrl ? '<img src="' + escapeHtml(thumbUrl) + '" alt="" />' : '';

            var html = '';
            html += '<li class="tts-attachment-item" data-id="' + id + '">';
            html += '<div class="tts-attachment-thumb">' + thumbHtml + '</div>';
            html += '<div class="tts-attachment-meta">';
            html += '<span class="tts-attachment-title">' + escapeHtml(title) + '</span>';
            html += '<div class="tts-attachment-actions">';
            html += '<button type="button" class="button-link tts-attachment-make-primary" data-id="' + id + '">' + escapeHtml(labels.makePrimary) + '</button>';
            html += '<button type="button" class="button-link-delete tts-attachment-remove" data-id="' + id + '">' + escapeHtml(labels.remove) + '</button>';
            html += '</div>';
            html += '</div>';
            html += '<span class="tts-primary-indicator" aria-hidden="true">' + escapeHtml(labels.primary) + '</span>';
            html += '</li>';

            return $(html);
        }

        function addAttachments(selection){
            selection.each(function(model){
                var attachment = model.toJSON();
                var $item = renderAttachmentItem(attachment);
                if ($item) {
                    $attachmentsList.append($item);
                }
            });
            updateAttachmentInputs();
        }

        $attachmentsList.sortable({
            stop: updateAttachmentInputs,
            items: '.tts-attachment-item'
        });

        $attachmentsList.on('click', '.tts-attachment-remove', function(e){
            e.preventDefault();
            $(this).closest('.tts-attachment-item').remove();
            updateAttachmentInputs();
        });

        $attachmentsList.on('click', '.tts-attachment-make-primary', function(e){
            e.preventDefault();
            var id = $(this).data('id');
            setPrimary(id);
            updateAttachmentInputs();
        });

        $clearButton.on('click', function(e){
            e.preventDefault();
            $attachmentsList.empty();
            updateAttachmentInputs();
        });

        $('.tts-select-media').on('click', function(e){
            e.preventDefault();
            if(frame){
                frame.open();
                return;
            }
            frame = wp.media({
                title: 'Seleziona o Carica file',
                button: { text: 'Usa questi file' },
                multiple: true
            });
            frame.on('select', function(){
                var selection = frame.state().get('selection');
                addAttachments(selection);
            });
            frame.open();
        });

        $('#tts_publish_story').on('change', function(){
            $('#tts_story_media_wrapper').toggle($(this).is(':checked'));
        }).trigger('change');

        $('.tts-select-story-media').on('click', function(e){
            e.preventDefault();
            if(storyFrame){
                storyFrame.open();
                return;
            }
            storyFrame = wp.media({
                title: 'Seleziona o Carica file',
                button: { text: 'Usa questo file' },
                multiple: false
            });
            storyFrame.on('select', function(){
                var attachment = storyFrame.state().get('selection').first().toJSON();
                $('#tts_story_media').val(attachment.id);
                var img = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.icon;
                $('#tts_story_media_preview').html(img ? '<img src="' + escapeHtml(img) + '" alt="" />' : '');
            });
            storyFrame.open();
        });

        // Hydrate existing state on load.
        updateAttachmentInputs();
    });
})(jQuery);
