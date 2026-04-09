<?php

function rd_register_distributori_rest_api() {
    register_rest_route('wc/v3', 'distributori/bulk', [
        'methods'             => 'POST',
        'callback'            => 'rd_sync_distributori_callback',
        'permission_callback' => 'rd_distributori_permission_check',
    ]);

    register_rest_route('wc/v3', 'distributori/(?P<id>\\d+)', [
        'methods'             => 'POST',
        'callback'            => 'rd_upsert_single_distributore_callback',
        'permission_callback' => 'rd_distributori_permission_check',
    ]);

    register_rest_route('wc/v3', 'distributori/(?P<id>\\d+)', [
        'methods'             => 'DELETE',
        'callback'            => 'rd_delete_single_distributore_callback',
        'permission_callback' => 'rd_distributori_permission_check',
    ]);

    register_rest_route('wc/v3', 'distributori', [
        'methods'             => 'GET',
        'callback'            => 'rd_get_distributori_callback',
        'permission_callback' => 'rd_distributori_permission_check',
    ]);
}

function rd_distributori_permission_check($request) {
    return current_user_can('manage_options');
}

function rd_sync_distributori_callback($request) {
    $incoming    = $request->get_json_params();
    $updated_ids = [];

    foreach ($incoming as $distributore) {
        $post_id = rd_upsert_distributore($distributore);
        if ($post_id) {
            $updated_ids[] = $post_id;
        }
    }

    return new WP_REST_Response(['messaggio' => 'Distributori sincronizzati', 'post_ids' => $updated_ids], 200);
}

function rd_upsert_single_distributore_callback($request) {
    $id                               = $request->get_param('id');
    $distributore                     = $request->get_json_params();
    $distributore['portaleconcessionari_id'] = $id;

    $post_id = rd_upsert_distributore($distributore);

    if (!$post_id) {
        return new WP_REST_Response(['errore' => 'Errore in creazione/aggiornamento'], 500);
    }

    return new WP_REST_Response(['messaggio' => 'Distributore aggiornato con successo', 'post_id' => $post_id], 200);
}

function rd_delete_single_distributore_callback($request) {
    $id = $request->get_param('id');
    wc_get_logger()->info("Richiesta eliminazione distributore per portaleconcessionari_id = $id");

    $existing = get_posts([
        'post_type'      => 'distributore',
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'meta_query'     => [[
            'key'   => 'portaleconcessionari_id',
            'value' => $id,
        ]],
    ]);

    if (!$existing) {
        wc_get_logger()->warning("Distributore non trovato con ID = $id");
        return new WP_REST_Response(['errore' => 'Distributore non trovato'], 404);
    }

    $post      = $existing[0];
    $post_id   = $post->ID;
    $post_title = get_the_title($post_id);

    wc_get_logger()->info("Eliminazione in corso per: [ID: $post_id, Titolo: \"$post_title\"]");

    wp_set_object_terms($post_id, [], 'distributore_provincia');

    $acf_fields = [
        'classi_sconto', 'telefoni', 'cellulari', 'nome', 'email',
        'pec', 'partita_iva', 'portaleconcessionari_id', 'avoid_classi_sconto_sync',
    ];
    foreach ($acf_fields as $field) {
        delete_field($field, $post_id);
    }

    $result = wp_delete_post($post_id, true);

    if (!$result) {
        wc_get_logger()->error("Errore durante l'eliminazione del distributore ID: $post_id");
        return new WP_REST_Response(['errore' => 'Errore durante l\'eliminazione'], 500);
    }

    wc_get_logger()->info("Distributore eliminato con successo: [ID: $post_id, Titolo: \"$post_title\"]");

    return new WP_REST_Response(['messaggio' => 'Distributore eliminato', 'post_id' => $post_id], 200);
}

function rd_upsert_distributore(array $distributore): ?int {
    wc_get_logger()->info("Upsert distributore:" . wc_print_r($distributore, true));

    $province_obj = rd_get_province_obj();

    if (empty($distributore['portaleconcessionari_id'])) return null;

    $existing = get_posts([
        'post_type'      => 'distributore',
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'meta_query'     => [[
            'key'   => 'portaleconcessionari_id',
            'value' => $distributore['portaleconcessionari_id'],
        ]],
    ]);

    $post_id   = $existing ? $existing[0]->ID : null;
    $dist_data = [
        'post_title'  => $distributore['titolo'],
        'post_type'   => 'distributore',
        'post_status' => 'publish',
    ];

    $post_id = $post_id
        ? wp_update_post(['ID' => $post_id] + $dist_data)
        : wp_insert_post($dist_data);

    if (!$post_id) return null;

    $fields = ['nome', 'email', 'pec', 'partita_iva', 'portaleconcessionari_id'];
    foreach ($fields as $field) {
        update_field($field, $distributore[$field] ?? '', $post_id);
    }

    update_field('telefoni', array_map(fn($v) => ['telefono' => $v], $distributore['telefoni'] ?? []), $post_id);
    update_field('cellulari', array_map(fn($v) => ['cellulare' => $v], $distributore['cellulari'] ?? []), $post_id);

    if (!get_field('avoid_classi_sconto_sync', $post_id) && !empty($distributore['classi_sconto'])) {
        rd_update_classi_sconto($post_id, $distributore['classi_sconto'], $province_obj);
    }

    return $post_id;
}

function rd_update_classi_sconto($post_id, $classi_sconto, $province_obj) {
    $result         = [];
    $province_slugs = [];

    foreach ($classi_sconto as $cs) {
        $zone = [];
        foreach ($cs['zone'] as $zona) {
            $provincia_slug   = rd_get_provincia_slug_from_sigla($province_obj, $zona['provincia']);
            $province_slugs[] = $provincia_slug;
            $prov_term        = get_term_by('slug', $provincia_slug, 'distributore_provincia');
            if (!$prov_term) continue;

            $tipo = [];
            if (!empty($zona['vendita']))    $tipo[] = 'vendita';
            if (!empty($zona['propaganda'])) $tipo[] = 'promozione';

            $zone[] = [
                'provincia' => (int)$prov_term->term_id,
                'tipo'      => $tipo,
            ];
        }

        $result[] = [
            'email' => $cs['email'] ?? '',
            'zone'  => $zone,
        ];
    }

    update_field('classi_sconto', $result, $post_id);
    rd_insert_taxonomies_with_slugs($post_id, $province_slugs, 'distributore_provincia');
}

function rd_get_distributori_callback($request) {
    $province_obj = rd_get_province_obj();

    $query = new WP_Query([
        'post_type'      => 'distributore',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ]);

    $output = [];
    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $acf     = get_fields($post_id);

        $telefoni = [];
        if (!empty($acf['telefoni']) && is_array($acf['telefoni'])) {
            foreach ($acf['telefoni'] as $r) {
                if (!empty($r['telefono'])) $telefoni[] = $r['telefono'];
            }
        }

        $cellulari = [];
        if (!empty($acf['cellulari']) && is_array($acf['cellulari'])) {
            foreach ($acf['cellulari'] as $r) {
                if (!empty($r['cellulare'])) $cellulari[] = $r['cellulare'];
            }
        }

        $classi_sconto = [];
        if (!empty($acf['classi_sconto']) && is_array($acf['classi_sconto'])) {
            foreach ($acf['classi_sconto'] as $cs) {
                $zone = [];
                foreach ($cs['zone'] ?? [] as $zona) {
                    $prov_term      = get_term($zona['provincia'], 'distributore_provincia');
                    $provincia_name = $prov_term ? $prov_term->name : '';
                    $sigla          = rd_get_provincia_sigla_from_nome($province_obj, $provincia_name);

                    $zone[] = [
                        'provincia'  => $sigla ?? '',
                        'vendita'    => in_array('vendita', $zona['tipo'] ?? []),
                        'propaganda' => in_array('promozione', $zona['tipo'] ?? []),
                    ];
                }

                $classi_sconto[] = [
                    'email' => $cs['email'] ?? '',
                    'zone'  => $zone,
                ];
            }
        }

        $output[] = [
            'titolo'                 => get_the_title(),
            'nome'                   => $acf['nome'] ?? '',
            'email'                  => $acf['email'] ?? '',
            'pec'                    => $acf['pec'] ?? '',
            'partita_iva'            => $acf['partita_iva'] ?? '',
            'portaleconcessionari_id' => $acf['portaleconcessionari_id'] ?? '',
            'telefoni'               => $telefoni,
            'cellulari'              => $cellulari,
            'classi_sconto'          => $classi_sconto,
        ];
    }
    wp_reset_postdata();

    return new WP_REST_Response($output, 200);
}

function rd_insert_taxonomies_with_slugs($post_id, $slugs, $taxonomy) {
    $slugs       = array_unique($slugs);
    $valid_slugs = [];

    foreach ($slugs as $slug) {
        $term = get_term_by('slug', strtolower($slug), $taxonomy);
        if ($term) $valid_slugs[] = $term->slug;
    }

    if (!empty($valid_slugs)) {
        $result = wp_set_object_terms($post_id, $valid_slugs, $taxonomy, false);
        if (is_wp_error($result)) {
            wc_get_logger()->error("Errore impostando la tassonomia $taxonomy per post $post_id: " . wc_print_r($result, true));
        }
    }
}
