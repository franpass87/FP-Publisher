(function($) {
    'use strict';

    if (typeof window.ttsAI === 'undefined') {
        return;
    }

    const config = window.ttsAI;
    const strings = config.strings || {};

    const getString = function(key, fallback) {
        if (Object.prototype.hasOwnProperty.call(strings, key)) {
            return strings[key];
        }
        return fallback;
    };

    const getResponseMessage = function(response, key, fallback) {
        if (response && response.data && response.data.message) {
            return response.data.message;
        }
        return getString(key, fallback);
    };

    const formatSuggestionMeta = function(suggestion) {
        const metaParts = [];

        if (suggestion && suggestion.platform) {
            metaParts.push(getString('platform', 'Platform:') + ' ' + suggestion.platform);
        }

        const hasPerformance = suggestion && typeof suggestion.estimated_performance === 'number' && !isNaN(suggestion.estimated_performance);

        if (hasPerformance) {
            metaParts.push(getString('estimated_performance', 'Est. Performance:') + ' ' + suggestion.estimated_performance + '%');
        } else {
            metaParts.push(getString('performance_data_unavailable', 'Performance data unavailable'));
        }

        if (suggestion && suggestion.is_example) {
            metaParts.push(getString('example_trend_label', 'Example trend (no live data)'));
        }

        return metaParts.join(' | ');
    };

    $(function() {
        const $overlay = $('#tts-loading-overlay');

        const showLoading = function() {
            $overlay.show();
        };

        const hideLoading = function() {
            $overlay.hide();
        };

        $('#generate-hashtags').on('click', function() {
            const content = $('#hashtag-content').val() || '';
            const platform = $('#hashtag-platform').val();

            if (!content.trim()) {
                window.alert(getString('enter_content', 'Please enter some content first.'));
                return;
            }

            showLoading();

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'tts_generate_hashtags',
                    nonce: config.nonce,
                    content: content,
                    platform: platform
                },
                success: function(response) {
                    hideLoading();

                    if (response && response.success && response.data && Array.isArray(response.data.hashtags)) {
                        let html = '<h5>' + getString('generated_hashtags', 'Generated Hashtags:') + '</h5>';
                        response.data.hashtags.forEach(function(hashtag) {
                            html += '<span class="hashtag-tag">' + hashtag + '</span>';
                        });
                        $('#hashtag-results').html(html);
                    } else {
                        const message = getResponseMessage(response, 'hashtag_error', 'Error generating hashtags.');
                        $('#hashtag-results').html('<p style="color: red;">' + message + '</p>');
                    }
                },
                error: function() {
                    hideLoading();
                    $('#hashtag-results').html('<p style="color: red;">' + getString('hashtag_error', 'Error generating hashtags.') + '</p>');
                }
            });
        });

        $('#predict-performance').on('click', function() {
            const content = $('#predict-content').val() || '';
            const platform = $('#predict-platform').val();

            if (!content.trim()) {
                window.alert(getString('enter_content', 'Please enter some content first.'));
                return;
            }

            showLoading();

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'tts_predict_performance',
                    nonce: config.nonce,
                    content: content,
                    platform: platform
                },
                success: function(response) {
                    hideLoading();

                    if (response && response.success && response.data && response.data.prediction) {
                        const pred = response.data.prediction;
                        const confidence = typeof pred.confidence !== 'undefined' ? pred.confidence : 0;
                        const engagement = typeof pred.engagement_rate !== 'undefined' ? pred.engagement_rate : 0;
                        const predictedLikes = typeof pred.predicted_likes !== 'undefined' ? pred.predicted_likes : 0;
                        const recommendation = pred.recommendation || '';

                        let html = '<h5>' + getString('performance_prediction', 'Performance Prediction:') + '</h5>';
                        html += '<div class="performance-meter"><div class="performance-fill" style="width: ' + confidence + '%"></div></div>';
                        html += '<p><strong>' + getString('confidence', 'Confidence:') + '</strong> ' + confidence + '%</p>';
                        html += '<p><strong>' + getString('engagement_rate', 'Engagement Rate:') + '</strong> ' + engagement + '%</p>';
                        html += '<p><strong>' + getString('predicted_likes', 'Predicted Likes:') + '</strong> ' + predictedLikes + '</p>';
                        html += '<p><strong>' + getString('recommendation', 'Recommendation:') + '</strong> ' + recommendation + '</p>';
                        $('#prediction-results').html(html);
                    } else {
                        const message = getResponseMessage(response, 'performance_error', 'Error predicting performance.');
                        $('#prediction-results').html('<p style="color: red;">' + message + '</p>');
                    }
                },
                error: function() {
                    hideLoading();
                    $('#prediction-results').html('<p style="color: red;">' + getString('performance_error', 'Error predicting performance.') + '</p>');
                }
            });
        });

        $('#get-suggestions').on('click', function() {
            const topic = $('#suggestion-topic').val();
            const platform = $('#suggestion-platform').val();

            showLoading();

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'tts_suggest_content',
                    nonce: config.nonce,
                    topic: topic,
                    platform: platform
                },
                success: function(response) {
                    hideLoading();

                    if (response && response.success && response.data && Array.isArray(response.data.suggestions)) {
                        let html = '<h5>' + getString('content_suggestions', 'Content Suggestions:') + '</h5>';
                        response.data.suggestions.forEach(function(suggestion) {
                            html += '<div class="suggestion-item">';
                            html += '<div class="suggestion-title">' + suggestion.title + '</div>';
                            html += '<div class="suggestion-meta">' + formatSuggestionMeta(suggestion) + '</div>';
                            html += '</div>';
                        });
                        $('#suggestion-results').html(html);
                    } else {
                        const message = getResponseMessage(response, 'suggestions_error', 'Error getting suggestions.');
                        $('#suggestion-results').html('<p style="color: red;">' + message + '</p>');
                    }
                },
                error: function() {
                    hideLoading();
                    $('#suggestion-results').html('<p style="color: red;">' + getString('suggestions_error', 'Error getting suggestions.') + '</p>');
                }
            });
        });

        $('#add-competitor').on('click', function() {
            const name = $('#competitor-name').val();
            const platform = $('#competitor-platform').val();
            const handle = $('#competitor-handle').val();

            if (!name || !handle) {
                window.alert(getString('fill_all_fields', 'Please fill in all fields.'));
                return;
            }

            showLoading();

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'tts_add_competitor',
                    nonce: config.competitor_nonce,
                    competitor_name: name,
                    platform: platform,
                    handle: handle
                },
                success: function(response) {
                    hideLoading();

                    if (response && response.success) {
                        const message = response.data && response.data.message ? response.data.message : '';
                        $('#competitor-results').html('<p style="color: green;">' + message + '</p>');
                        $('#competitor-name, #competitor-handle').val('');
                    } else {
                        const message = getResponseMessage(response, 'add_competitor_error', 'Error adding competitor.');
                        $('#competitor-results').html('<p style="color: red;">' + message + '</p>');
                    }
                },
                error: function() {
                    hideLoading();
                    $('#competitor-results').html('<p style="color: red;">' + getString('add_competitor_error', 'Error adding competitor.') + '</p>');
                }
            });
        });

        $('#generate-competitor-report').on('click', function() {
            showLoading();

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'tts_get_competitor_report',
                    nonce: config.competitor_nonce
                },
                success: function(response) {
                    hideLoading();

                    if (response && response.success && response.data && response.data.report) {
                        const report = response.data.report;
                        const summary = report.summary || {};
                        const totalCompetitors = typeof summary.total_competitors !== 'undefined' ? summary.total_competitors : 0;
                        const avgEngagement = typeof summary.avg_engagement_rate !== 'undefined' ? summary.avg_engagement_rate : 0;

                        let html = '<h5>' + getString('competitor_report', 'Competitor Analysis Report:') + '</h5>';
                        html += '<p><strong>' + getString('total_competitors', 'Total Competitors:') + '</strong> ' + totalCompetitors + '</p>';
                        html += '<p><strong>' + getString('avg_engagement', 'Average Engagement:') + '</strong> ' + avgEngagement + '%</p>';

                        if (Array.isArray(report.recommendations) && report.recommendations.length > 0) {
                            html += '<h6>' + getString('recommendations', 'Recommendations:') + '</h6>';
                            report.recommendations.forEach(function(rec) {
                                html += '<div style="margin: 8px 0;"><strong>' + rec.category + ':</strong> ' + rec.recommendation + '</div>';
                            });
                        }

                        $('#competitor-results').html(html);
                    } else {
                        const message = getResponseMessage(response, 'report_error', 'Error generating report.');
                        $('#competitor-results').html('<p style="color: red;">' + message + '</p>');
                    }
                },
                error: function() {
                    hideLoading();
                    $('#competitor-results').html('<p style="color: red;">' + getString('report_error', 'Error generating report.') + '</p>');
                }
            });
        });

        $('#get-team-dashboard').on('click', function() {
            showLoading();

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'tts_get_team_dashboard',
                    nonce: config.workflow_nonce
                },
                success: function(response) {
                    hideLoading();

                    if (response && response.success && response.data && response.data.dashboard) {
                        const dashboard = response.data.dashboard;
                        const stats = dashboard.statistics || {};
                        const pending = stats.pending_approval || 0;
                        const approved = stats.approved || 0;
                        const rejected = stats.rejected || 0;
                        const overdue = stats.overdue || 0;
                        const teamMembers = Array.isArray(dashboard.team_performance) ? dashboard.team_performance.length : 0;

                        $('#pending-approvals').text(pending);
                        $('#approved-content').text(approved);
                        $('#team-members').text(teamMembers);

                        let html = '<h5>' + getString('team_dashboard', 'Team Dashboard:') + '</h5>';
                        html += '<p><strong>' + getString('pending_approvals', 'Pending Approvals:') + '</strong> ' + pending + '</p>';
                        html += '<p><strong>' + getString('approved_content', 'Approved Content:') + '</strong> ' + approved + '</p>';
                        html += '<p><strong>' + getString('rejected_content', 'Rejected Content:') + '</strong> ' + rejected + '</p>';
                        html += '<p><strong>' + getString('overdue_items', 'Overdue Items:') + '</strong> ' + overdue + '</p>';

                        $('#workflow-results').html(html);
                    } else {
                        const message = getResponseMessage(response, 'dashboard_error', 'Error loading dashboard.');
                        $('#workflow-results').html('<p style="color: red;">' + message + '</p>');
                    }
                },
                error: function() {
                    hideLoading();
                    $('#workflow-results').html('<p style="color: red;">' + getString('dashboard_error', 'Error loading dashboard.') + '</p>');
                }
            });
        });

        $('#analyze-media-performance').on('click', function() {
            showLoading();

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'tts_analyze_media_performance',
                    nonce: config.media_nonce
                },
                success: function(response) {
                    hideLoading();

                    if (response && response.success && response.data && response.data.analysis) {
                        const analysis = response.data.analysis;
                        const totalPosts = typeof analysis.total_posts_analyzed !== 'undefined' ? analysis.total_posts_analyzed : 0;
                        const missingPosts = typeof analysis.posts_without_metrics !== 'undefined' ? analysis.posts_without_metrics : 0;
                        const platformPerformance = analysis.platform_performance || {};
                        const topMedia = Array.isArray(analysis.top_performing_media) ? analysis.top_performing_media : [];

                        const formatNumber = function(value) {
                            if (value === null || typeof value === 'undefined') {
                                return getString('not_available', 'Not available');
                            }

                            let parsed = value;
                            if (typeof parsed !== 'number') {
                                parsed = parseFloat(parsed);
                            }

                            if (!isFinite(parsed)) {
                                return getString('not_available', 'Not available');
                            }

                            if (Math.abs(parsed - Math.round(parsed)) < 0.01) {
                                return Math.round(parsed).toLocaleString();
                            }

                            return parsed.toFixed(2);
                        };

                        const escapeHtml = function(value) {
                            if (value === null || typeof value === 'undefined') {
                                return '';
                            }
                            return $('<div>').text(value).html();
                        };

                        let html = '<h5>' + getString('media_analysis', 'Media Performance Analysis:') + '</h5>';
                        html += '<p><strong>' + getString('posts_analyzed', 'Posts Analyzed:') + '</strong> ' + formatNumber(totalPosts) + '</p>';

                        if (missingPosts > 0) {
                            html += '<p class="tts-missing-data"><strong>' + getString('posts_missing_metrics', 'Posts missing metrics:') + '</strong> ' + formatNumber(missingPosts) + '</p>';
                        }

                        const platformKeys = Object.keys(platformPerformance || {});
                        if (platformKeys.length > 0) {
                            html += '<h6>' + getString('platform_breakdown', 'Platform Breakdown:') + '</h6>';
                            html += '<div class="tts-platform-performance">';

                            platformKeys.forEach(function(platformKey) {
                                const data = platformPerformance[platformKey] || {};
                                const postsCount = typeof data.posts_count !== 'undefined' ? data.posts_count : 0;
                                const postsMissingData = typeof data.posts_without_metric_data !== 'undefined' ? data.posts_without_metric_data : 0;
                                const totalInteractions = typeof data.total_interactions !== 'undefined' ? formatNumber(data.total_interactions) : getString('not_available', 'Not available');
                                const avgInteractions = typeof data.avg_interactions_per_post !== 'undefined' && data.avg_interactions_per_post !== null
                                    ? formatNumber(data.avg_interactions_per_post)
                                    : getString('not_available', 'Not available');
                                const metrics = data.metrics || {};
                                const missingMetrics = Array.isArray(data.missing_metrics) ? data.missing_metrics : [];
                                const label = platformKey.split(/[_-]/).map(function(part) {
                                    return part.charAt(0).toUpperCase() + part.slice(1);
                                }).join(' ');

                                html += '<div class="tts-platform-card">';
                                html += '<h6>' + escapeHtml(label) + '</h6>';
                                html += '<p><strong>' + getString('posts_with_metrics', 'Posts with metrics:') + '</strong> ' + formatNumber(postsCount) + '</p>';
                                html += '<p><strong>' + getString('total_interactions', 'Total interactions:') + '</strong> ' + totalInteractions + '</p>';
                                html += '<p><strong>' + getString('avg_interactions_per_post', 'Avg. interactions per post:') + '</strong> ' + avgInteractions + '</p>';

                                if (postsMissingData > 0) {
                                    html += '<p><strong>' + getString('posts_missing_data', 'Posts missing metric data:') + '</strong> ' + formatNumber(postsMissingData) + '</p>';
                                }

                                const metricKeys = Object.keys(metrics);
                                if (metricKeys.length > 0) {
                                    html += '<ul>';
                                    metricKeys.forEach(function(metricKey) {
                                        const metric = metrics[metricKey] || {};
                                        const metricTotal = typeof metric.total !== 'undefined' ? formatNumber(metric.total) : getString('not_available', 'Not available');
                                        const metricAverage = typeof metric.average !== 'undefined' && metric.average !== null
                                            ? formatNumber(metric.average)
                                            : getString('not_available', 'Not available');

                                        html += '<li><strong>' + escapeHtml(metricKey) + '</strong>: ' + getString('metric_total', 'Total') + ' ' + metricTotal + ' &middot; ' + getString('metric_average', 'Average') + ' ' + metricAverage + '</li>';
                                    });
                                    html += '</ul>';
                                } else {
                                    html += '<p>' + getString('metrics_not_available', 'Metrics not available for this platform yet.') + '</p>';
                                }

                                if (missingMetrics.length > 0) {
                                    const missingList = missingMetrics.map(function(item) { return escapeHtml(item); }).join(', ');
                                    html += '<p><em>' + getString('missing_metrics_label', 'Missing metrics:') + '</em> ' + missingList + '</p>';
                                }

                                html += '</div>';
                            });

                            html += '</div>';
                        } else {
                            html += '<p>' + getString('no_metrics_found', 'No metrics available for the analyzed posts.') + '</p>';
                        }

                        if (topMedia.length > 0) {
                            html += '<h6>' + getString('top_media', 'Top Performing Posts:') + '</h6>';
                            html += '<ol class="tts-top-posts">';
                            topMedia.forEach(function(item) {
                                const title = item.title ? escapeHtml(item.title) : getString('untitled_post', '(untitled)');
                                const interactions = typeof item.total_interactions !== 'undefined' ? formatNumber(item.total_interactions) : getString('not_available', 'Not available');
                                const platforms = Array.isArray(item.platforms) ? item.platforms.join(', ') : '';
                                const link = item.permalink || '';

                                html += '<li><strong>' + title + '</strong> (' + getString('interactions', 'Interactions:') + ' ' + interactions + ')';
                                if (platforms) {
                                    html += ' <span class="tts-post-platforms">[' + escapeHtml(platforms) + ']</span>';
                                }
                                if (link) {
                                    html += ' <a href="' + escapeHtml(link) + '" target="_blank" rel="noopener noreferrer">' + getString('view_post', 'View Post') + '</a>';
                                }
                                html += '</li>';
                            });
                            html += '</ol>';
                        }

                        html += '<h6>' + getString('optimization_recommendations', 'Optimization Recommendations:') + '</h6>';
                        if (Array.isArray(analysis.recommendations) && analysis.recommendations.length > 0) {
                            analysis.recommendations.forEach(function(rec) {
                                const impact = rec.impact || '';
                                const effort = rec.effort || '';
                                html += '<div class="tts-recommendation"><strong>' + escapeHtml(rec.category || '') + ':</strong> ' + escapeHtml(rec.recommendation || '') + ' <span style="color: #666;">(' + getString('impact_label', 'Impact') + ': ' + escapeHtml(impact) + ', ' + getString('effort_label', 'Effort') + ': ' + escapeHtml(effort) + ')</span></div>';
                            });
                        } else {
                            html += '<p>' + getString('no_recommendations', 'No recommendations available yet.') + '</p>';
                        }

                        $('#media-results').html(html);
                    } else {
                        const message = getResponseMessage(response, 'media_error', 'Error analyzing media.');
                        $('#media-results').html('<p style="color: red;">' + message + '</p>');
                    }
                },
                error: function() {
                    hideLoading();
                    $('#media-results').html('<p style="color: red;">' + getString('media_error', 'Error analyzing media.') + '</p>');
                }
            });
        });

        $('#view-integrations').on('click', function() {
            showLoading();

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'tts_get_available_integrations',
                    nonce: config.integration_nonce
                },
                success: function(response) {
                    hideLoading();

                    if (response && response.success && response.data) {
                        const integrations = response.data.integrations || {};
                        const connected = Array.isArray(response.data.connected) ? response.data.connected : [];

                        let html = '<h5>' + getString('available_integrations', 'Available Integrations:') + '</h5>';

                        if (connected.length > 0) {
                            html += '<h6>' + getString('connected', 'Connected:') + '</h6>';
                            connected.forEach(function(conn) {
                                html += '<div style="color: green; margin: 5px 0;">✓ ' + conn.integration_name + ' (' + conn.integration_type + ')</div>';
                            });
                        }

                        html += '<h6>' + getString('total_integrations', 'Total Available Integrations:') + '</h6>';

                        let totalCount = 0;
                        Object.keys(integrations).forEach(function(category) {
                            const items = integrations[category];
                            if (items) {
                                totalCount += Object.keys(items).length;
                            }
                        });

                        html += '<p>' + getString('we_support', 'We support') + ' <strong>' + totalCount + '</strong> ' + getString('integration_support_detail', 'different integrations across CRM, E-commerce, Email Marketing, Design Tools, Analytics, and Productivity platforms.') + '</p>';

                        $('#integration-results').html(html);
                    } else {
                        const message = getResponseMessage(response, 'integrations_error', 'Error loading integrations.');
                        $('#integration-results').html('<p style="color: red;">' + message + '</p>');
                    }
                },
                error: function() {
                    hideLoading();
                    $('#integration-results').html('<p style="color: red;">' + getString('integrations_error', 'Error loading integrations.') + '</p>');
                }
            });
        });
    });
})(jQuery);
