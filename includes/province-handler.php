<?php

function rd_create_province() {
    $province_obj = rd_get_province_obj();

    foreach ($province_obj as $provincia_obj) {
        $provincia_name = $provincia_obj['nome'];
        $regione_name   = $provincia_obj['regione'];
        $regione_slug   = rd_get_slug_value($regione_name, true);
        $provincia_slug = rd_get_slug_value($provincia_name);

        $existing_regione_id = term_exists($regione_slug, 'distributore_provincia');

        if ($existing_regione_id) {
            $regione_id = is_array($existing_regione_id) ? $existing_regione_id['term_id'] : $existing_regione_id;
        } else {
            $term = wp_insert_term($regione_name, 'distributore_provincia', ['slug' => $regione_slug]);
            if (is_wp_error($term)) {
                wc_get_logger()->error("Errore nella creazione della regione $regione_name: " . $term->get_error_message());
                continue;
            }
            $regione_id = $term['term_id'];
        }

        $existing_provincia_id = term_exists($provincia_slug, 'distributore_provincia');
        if (!$existing_provincia_id) {
            $term = wp_insert_term($provincia_name, 'distributore_provincia', [
                'slug'   => $provincia_slug,
                'parent' => $regione_id,
            ]);
            if (is_wp_error($term)) {
                wc_get_logger()->error("Errore nella creazione della provincia $provincia_name: " . $term->get_error_message());
            }
        }
    }
}

function rd_get_province_obj() {
    wc_get_logger()->info('Start creating provinces (distributori)');

    $csv_url = get_option('options_rd_csv_province');
    if (empty($csv_url)) {
        wc_get_logger()->error('URL CSV province non trovato nelle opzioni di WordPress (distributori)');
        return [];
    }

    $response = wp_remote_get($csv_url);
    if (is_wp_error($response)) {
        wc_get_logger()->error('Errore nel recupero delle province: ' . $response->get_error_message());
        return [];
    }

    $body  = wp_remote_retrieve_body($response);
    $lines = explode("\n", $body);
    array_shift($lines);

    $province_obj = [];
    foreach ($lines as $line) {
        $data = str_getcsv($line);
        if (!empty($data) && count($data) >= 3) {
            $province_obj[] = [
                'nome'    => $data[0],
                'sigla'   => $data[1],
                'regione' => $data[2],
            ];
        }
    }

    return $province_obj;
}

function rd_get_provincia_slug_from_sigla($province_obj, $sigla) {
    foreach ($province_obj as $p) {
        if (strtolower($p['sigla']) === strtolower($sigla)) {
            return $p['nome'];
        }
    }
    return null;
}

function rd_get_provincia_sigla_from_nome($province_obj, $nome) {
    foreach ($province_obj as $p) {
        if (strcasecmp($p['nome'], $nome) === 0) {
            return strtoupper($p['sigla']);
        }
    }
    return $nome;
}

function rd_get_slug_value($string, $without_dash = false) {
    $string = strtolower($string);
    $string = str_replace("'", '', $string);
    $string = str_replace("ì", 'i', $string);
    if ($without_dash) {
        $string = trim(preg_replace('/[^a-z0-9]+/i', '', $string));
    } else {
        $string = trim(preg_replace('/[^a-z0-9]+/i', '-', $string), '-');
    }
    return $string;
}

function rd_delete_all_terms($taxonomy) {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
    foreach ($terms as $term) {
        $result = wp_delete_term($term->term_id, $taxonomy);
        if (is_wp_error($result)) {
            wc_get_logger()->error('Errore nella cancellazione del termine: ' . $result->get_error_message());
        }
    }
}

function rd_recreation_province() {
    rd_delete_all_terms('distributore_provincia');
    rd_create_province();
}
