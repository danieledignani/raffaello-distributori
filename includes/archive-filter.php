<?php

/**
 * Rewrite rules per il filtro archivio distributori.
 *
 * Usiamo i nomi delle tassonomie come query var (stessa logica del plugin
 * raffaello-concessionari). I query var delle tassonomie PHP-registrate sono
 * già pubblici per WordPress, quindi non serve aggiungere query_vars manualmente.
 * La condizione in pre_get_posts controlla i query var direttamente invece di
 * affidarsi a is_post_type_archive(), che può essere inaffidabile in presenza
 * di tax_query nativa.
 */
add_action('init', function() {
    // Solo scuola
    add_rewrite_rule(
        '^distributori/scuola-([^/]+)/?$',
        'index.php?post_type=distributore&distributore_scuola=$matches[1]',
        'top'
    );

    // Solo provincia
    add_rewrite_rule(
        '^distributori/provincia-([^/]+)/?$',
        'index.php?post_type=distributore&distributore_provincia=$matches[1]',
        'top'
    );

    // Scuola + Provincia
    add_rewrite_rule(
        '^distributori/scuola-([^/]+)/provincia-([^/]+)/?$',
        'index.php?post_type=distributore&distributore_scuola=$matches[1]&distributore_provincia=$matches[2]',
        'top'
    );
});

add_action('pre_get_posts', function($query) {
    if (is_admin() || !$query->is_main_query()) return;

    $scuola_slug    = get_query_var('distributore_scuola');
    $provincia_slug = get_query_var('distributore_provincia');

    if (!$scuola_slug || !$provincia_slug) return;

    $scuola_term    = get_term_by('slug', $scuola_slug, 'distributore_scuola');
    $provincia_term = get_term_by('slug', $provincia_slug, 'distributore_provincia');

    if (!$scuola_term || !$provincia_term) return;

    $scuola_id    = (int) $scuola_term->term_id;
    $provincia_id = (int) $provincia_term->term_id;

    // Recupera tutti i post "distributore" e filtra tramite ACF (classi_sconto)
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
                    break 2; // match trovato, passa al prossimo post
                }
            }
        }
    }

    // Applica il filtro alla query principale
    if (!empty($matching_ids)) {
        $query->set('post__in', $matching_ids);
    } else {
        // Nessun risultato: evita query inutile
        $query->set('post__in', [0]);
    }

    // Lascia visibile anche la tassonomia per breadcrumb / URL
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
});
