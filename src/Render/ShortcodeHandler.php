<?php
declare(strict_types=1);

namespace FeedAggregator\Render;

/**
 * Renders feed items as HTML for shortcodes and Gutenberg blocks.
 *
 * Supports layout modes: grid, list, masonry, carousel.
 */
final class ShortcodeHandler
{
    /**
     * Shortcode callback: [feed_aggregator feed="1" layout="grid" columns="3" limit="12"]
     */
    public function render(array $atts): string
    {
        $atts = shortcode_atts([
            'feed'    => '',
            'layout'  => 'grid',
            'columns' => 3,
            'limit'   => 12,
            'type'    => '',   // Filter by content_type.
            'class'   => '',
        ], $atts, 'feed_aggregator');

        return $this->renderFeed($atts);
    }

    /**
     * Gutenberg block render callback.
     */
    public function renderBlock(array $attributes): string
    {
        return $this->renderFeed([
            'feed'    => $attributes['feedId'] ?? '',
            'layout'  => $attributes['layout'] ?? 'grid',
            'columns' => $attributes['columns'] ?? 3,
            'limit'   => $attributes['limit'] ?? 12,
            'type'    => $attributes['contentType'] ?? '',
            'class'   => '',
        ]);
    }

    private function renderFeed(array $atts): string
    {
        global $wpdb;
        $table = $wpdb->prefix . FEED_AGG_TABLE_ITEMS;

        $where = ['1=1'];
        $params = [];

        // Filter by feed ID(s).
        if (!empty($atts['feed'])) {
            $feed_ids = array_map('absint', explode(',', $atts['feed']));
            $placeholders = implode(',', array_fill(0, count($feed_ids), '%d'));
            $where[] = "feed_id IN ({$placeholders})";
            $params = array_merge($params, $feed_ids);
        }

        // Filter by content type.
        if (!empty($atts['type'])) {
            $where[] = 'content_type = %s';
            $params[] = sanitize_key($atts['type']);
        }

        $where_sql = implode(' AND ', $where);
        $limit = min(absint($atts['limit']), 100);
        $params[] = $limit;

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY published_at DESC LIMIT %d",
                ...$params
            ),
            ARRAY_A
        );

        if (empty($items)) {
            return '<p class="feed-agg-empty">' . esc_html__('No feed items found.', 'feed-aggregator') . '</p>';
        }

        $layout  = sanitize_key($atts['layout']);
        $columns = absint($atts['columns']);
        $extra_class = sanitize_html_class($atts['class']);

        ob_start();
        ?>
        <div class="feed-agg feed-agg--<?php echo esc_attr($layout); ?> <?php echo esc_attr($extra_class); ?>"
             style="--feed-columns: <?php echo esc_attr((string)$columns); ?>">
            <?php foreach ($items as $item) : ?>
                <?php echo $this->renderItem($item, $layout); // phpcs:ignore -- Already escaped in renderItem. ?>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function renderItem(array $item, string $layout): string
    {
        $permalink = esc_url($item['permalink'] ?? '#');
        $title     = esc_html($item['title'] ?? '');
        $body      = esc_html(wp_trim_words($item['body'] ?? '', 30));
        $mediaUrl  = esc_url($item['media_url'] ?? '');
        $author    = esc_html($item['author_name'] ?? '');
        $raw_date  = $item['published_at'] ?? '';
        $date      = ! empty( $raw_date ) ? esc_html( mysql2date( get_option( 'date_format' ), $raw_date ) ) : '';
        $type      = esc_attr($item['content_type'] ?? 'post');

        ob_start();
        ?>
        <article class="feed-agg__item feed-agg__item--<?php echo $type; ?>">
            <?php if ($mediaUrl) : ?>
                <a href="<?php echo $permalink; ?>" class="feed-agg__media" target="_blank" rel="noopener noreferrer">
                    <?php if ($type === 'video') : ?>
                        <div class="feed-agg__play-overlay">&#9654;</div>
                    <?php endif; ?>
                    <img src="<?php echo $mediaUrl; ?>" alt="<?php echo $title; ?>" loading="lazy" />
                </a>
            <?php endif; ?>
            <div class="feed-agg__content">
                <?php if ($title) : ?>
                    <h3 class="feed-agg__title">
                        <a href="<?php echo $permalink; ?>" target="_blank" rel="noopener noreferrer"><?php echo $title; ?></a>
                    </h3>
                <?php endif; ?>
                <?php if ($body) : ?>
                    <p class="feed-agg__body"><?php echo $body; ?></p>
                <?php endif; ?>
                <div class="feed-agg__meta">
                    <?php if ($author) : ?>
                        <span class="feed-agg__author"><?php echo $author; ?></span>
                    <?php endif; ?>
                    <time class="feed-agg__date"><?php echo $date; ?></time>
                </div>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }
}
