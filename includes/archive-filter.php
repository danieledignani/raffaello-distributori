<?php

/**
 * Rewrite rules per il filtro archivio distributori.
 *
 * NOTA: usiamo query var con prefisso "rd_filter_*" invece dei nomi delle
 * tassonomie ("distributore_scuola", "distributore_provincia") perché
 * WordPress, vedendo un query var il cui nome coincide con una tassonomia
 * registrata via PHP, interpreta la richiesta come un taxonomy archive e
 * is_post_type_archive() ritorna false, bypassando il filtro.
 * Le tassonomie ACF del plugin concessionari non hanno questo problema
 * perché ACF gestisce il query_var in modo diverso.
 */
add_action('init', function() {
    // Solo scuola
    add_rewrite_rule(
        '^distributori/scuola-([^/]+)/?$',
        'index.php?post_type=distributore&rd_filter_scuola=$matches[1]',
        'top'
    );

    // Solo provincia
    add_rewrite_rule(
        '^distributori/provincia-([^/]+)/?$',
        'index.php?post_type=distributore&rd_filter_provincia=$matches[1]',
        'top'
    );

    // Scuola + Provincia
    add_rewrite_rule(
        '^distributori/scuola-([^/]+)/provincia-([^/]+)/?$',
        'index.php?post_type=distributore&rd_filter_scuola=$matches[1]&rd_filter_provincia=$matches[2]',
        'top'
    );
});

add_filter('query_vars', function($vars) {
    $vars[] = 'rd_filter_scuola';
    $vars[] = 'rd_filter_provincia';
    return $vars;
});

add_action('pre_get_posts', function($query) {
    if (!is_admin() && $query->is_main_query() && is_post_type_archive('distributore')) {

        $scuola_slug    = get_query_var('rd_filter_scuola');
        $provincia_slug = get_query_var('rd_filter_provincia');

        if (!$scuola_slug || !$provincia_slug) return;

        $scuola_term    = get_term_by('slug', $scuola_slug, 'distributore_scuola');
        $provincia_term = get_term_by('slug', $provincia_slug, 'distributore_provincia');

        if (!$scuola_term || !$provincia_term) return;

        $scuola_id    = (int) $scuola_term->term_id;
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
                if ((int) $classe['scuola'] !== $scuola_id) continue;
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

        $query->set('tax_query', [
            [
                'taxonomy' => 'distributore_scuola',
                'field'    => 'slug',
                'terms'    => $scuola_slug,
            ],
            [
                'taxonomy' => 'distributore_provincia',
                'field'    => 'slug',
                'terms'    => $provincia_slug,
            ],
        ]);
    }
});
