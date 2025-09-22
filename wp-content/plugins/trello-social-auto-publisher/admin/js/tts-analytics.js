(function(){
    if ( typeof ttsAnalytics === 'undefined' || ! window.Chart ) {
        return;
    }

    const config = window.ttsAnalytics;
    const ctx = document.getElementById('tts-analytics-chart');
    if ( ! ctx ) {
        return;
    }

    const raw = config.data || {};
    const labels = Object.keys(raw);
    const channelSet = new Set();
    labels.forEach(date => {
        const obj = raw[date];
        Object.keys(obj).forEach(ch => channelSet.add(ch));
    });
    const channels = Array.from(channelSet);
    const palette = Object.values(config.chartColors || {});
    const fallbackPalette = ['#135e96', '#f56e28', '#00a32a', '#f6c23e', '#dc3545', '#17a2b8'];
    const colors = palette.length ? palette : fallbackPalette;
    const datasets = channels.map((ch, index) => {
        const color = colors[index % colors.length];
        return {
            label: ch,
            data: labels.map(date => raw[date][ch] ? raw[date][ch] : 0),
            fill: false,
            borderWidth: 2,
            borderColor: color,
            backgroundColor: color,
            pointBackgroundColor: color,
            tension: 0.25
        };
    });

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Expose chart instance for potential dynamic updates using ajaxUrl/nonce.
    window.ttsAnalyticsChart = chart;
    window.ttsAnalyticsConfig = config;
})();
