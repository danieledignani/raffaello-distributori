<?php

/**
 * Rewrite rules per il filtro archivio distributori (solo per provincia).
 */
add_action('init', function() {
    // Solo provincia
    add_rewrite_rule(
        '^distributori/provincia-([^/]+)/?$',
        'index.php?post_type=distributore&distributore_provincia=$matches[1]',
        'top'
    );
});

add_action('pre_get_posts', function($query) {
    if (is_admin() || !$query->is_main_query()) return;

    $provincia_slug = get_query_var('distributore_provincia');
    if (!$provincia_slug) return;

    $provincia_term = get_term_by('slug', $provincia_slug, 'distributore_provincia');
    if (!$provincia_term) return;

    $provincia_id = (int) $provincia_term->term_id;

    $matching_ids = [];
    $posts = get_posts([
        'post_type'      => 'distributore',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($posts as $post_id) {
        $classi_sconto = get_field('classi_sconto', $post_id);
        if (!$classi_sconto || !is_array($classi_sconto)) continue;

        foreach ($classi_sconto as $classe) {
            if (empty($classe['zone'])) continue;

            foreach ($classe['zone'] as $zona) {
                if ((int) $zona['provincia'] === $provincia_id) {
                    $matching_ids[] = $post_id;
                    break 2;
                }
            }
        }
    }

    if (!empty($matching_ids)) {
        $query->set('post__in', $matching_ids);
    } else {
        $query->set('post__in', [0]);
    }

    $query->set('tax_query', [[
        'taxonomy' => 'distributore_provincia',
        'field'    => 'slug',
        'terms'    => $provincia_slug,
    ]]);
});
