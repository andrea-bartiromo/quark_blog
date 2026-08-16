<script>
(() => {
    const allowedEvents = new Set(@json(config('content-clusters-analytics.events', [])));
    const allowedMetadata = new Set(@json(config('content-clusters-analytics.metadata', [])));
    const pendingKey = 'kairus:path-second-reading';

    function sanitize(metadata) {
        const clean = {};
        Object.entries(metadata || {}).forEach(([key, value]) => {
            if (!allowedMetadata.has(key) || !['string', 'number'].includes(typeof value)) return;
            clean[key] = value;
        });
        return clean;
    }

    function emit(eventName, metadata = {}) {
        try {
            if (!allowedEvents.has(eventName)) return false;
            document.dispatchEvent(new CustomEvent('kairus:path-analytics', {
                detail: { event: eventName, metadata: sanitize(metadata) },
            }));
            return true;
        } catch (_) {
            return false;
        }
    }

    window.KairusPathAnalytics = Object.freeze({ emit });

    document.addEventListener('DOMContentLoaded', () => {
        const page = document.querySelector('[data-path-analytics-view]');
        if (page) {
            emit('path_view', {
                path_slug: page.dataset.pathSlug,
                cluster_id: Number(page.dataset.clusterId),
            });
        }

        const box = document.querySelector('.path-continuation[data-path-slug]');
        if (!box) return;

        const metadata = {
            path_slug: box.dataset.pathSlug,
            article_id: Number(box.dataset.articleId),
            position: Number(box.dataset.pathPosition),
            total: Number(box.dataset.pathTotal),
        };

        try {
            const pending = JSON.parse(sessionStorage.getItem(pendingKey) || 'null');
            if (pending && Number(pending.target_article_id) === metadata.article_id && pending.path_slug === metadata.path_slug) {
                emit('second_reading', { ...metadata, navigation_action: pending.navigation_action });
                sessionStorage.removeItem(pendingKey);
            }
        } catch (_) {
            try { sessionStorage.removeItem(pendingKey); } catch (_) {}
        }

        box.querySelectorAll('[data-path-event]').forEach((link) => {
            link.addEventListener('click', () => {
                const eventName = link.dataset.pathEvent;
                const targetArticleId = Number(link.dataset.targetArticleId || 0) || undefined;
                emit(eventName, { ...metadata, target_article_id: targetArticleId, navigation_action: eventName });

                if ((eventName === 'path_next_click' || eventName === 'path_previous_click') && targetArticleId) {
                    try {
                        sessionStorage.setItem(pendingKey, JSON.stringify({
                            path_slug: metadata.path_slug,
                            target_article_id: targetArticleId,
                            navigation_action: eventName,
                        }));
                    } catch (_) {}
                }
            });
        });
    });
})();
</script>
