<?php

/**
 * Registrazione del custom post type "distributore" e delle tassonomie
 * "distributore_scuola" e "distributore_provincia".
 */
add_action('init', function() {

    // -----------------------------------------------------------------------
    // Post type: distributore
    // -----------------------------------------------------------------------
    register_post_type('distributore', [
        'labels' => [
            'name'                  => 'Distributori',
            'singular_name'         => 'Distributore',
            'menu_name'             => 'Distributori',
            'all_items'             => 'Tutti i Distributori',
            'edit_item'             => 'Modifica Distributore',
            'view_item'             => 'Visualizza Distributore',
            'add_new_item'          => 'Aggiungi Nuovo Distributore',
            'add_new'               => 'Aggiungi Nuovo Distributore',
            'new_item'              => 'Nuovo Distributore',
            'search_items'          => 'Cerca Distributori',
            'not_found'             => 'Nessun distributore trovato',
            'not_found_in_trash'    => 'Nessun distributore trovato nel cestino',
            'items_list_navigation' => 'Naviga nell\'elenco Distributori',
            'items_list'            => 'Elenco Distributori',
            'item_link'             => 'Link Distributore',
        ],
        'public'              => true,
        'hierarchical'        => false,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => true,
        'show_in_rest'        => true,
        'menu_icon'           => 'dashicons-businessman',
        'supports'            => ['title', 'editor'],
        'has_archive'         => 'distributori',
        'rewrite'             => ['slug' => 'distributore', 'with_front' => true],
        'can_export'          => true,
        'enter_title_here'    => 'Nome Visualizzato',
    ]);

    // -----------------------------------------------------------------------
    // Tassonomia: distributore_scuola
    // -----------------------------------------------------------------------
    register_taxonomy('distributore_scuola', ['distributore'], [
        'labels' => [
            'name'          => 'Distributori Scuole',
            'singular_name' => 'Distributori Scuola',
            'menu_name'     => 'Scuola',
            'all_items'     => 'Tutte le scuole',
            'edit_item'     => 'Modifica Scuola',
            'view_item'     => 'Visualizza Scuola',
            'update_item'   => 'Aggiorna Scuola',
            'add_new_item'  => 'Aggiungi Nuova Scuola',
            'search_items'  => 'Ricerca Scuola',
            'not_found'     => 'Nessuna scuola trovata',
            'no_terms'      => 'Nessuna scuola',
            'back_to_items' => '← Torna alle scuole',
        ],
        'public'            => true,
        'publicly_queryable' => true,
        'hierarchical'      => false,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'show_in_quick_edit' => true,
        'rewrite'           => ['slug' => 'distributore-scuola', 'with_front' => true],
        'sort'              => true,
    ]);

    // -----------------------------------------------------------------------
    // Tassonomia: distributore_provincia
    // -----------------------------------------------------------------------
    register_taxonomy('distributore_provincia', ['distributore'], [
        'labels' => [
            'name'          => 'Distributori Province',
            'singular_name' => 'Distributori Provincia',
            'menu_name'     => 'Province',
            'all_items'     => 'Tutte le Province',
            'edit_item'     => 'Modifica Provincia',
            'view_item'     => 'Visualizza Provincia',
            'update_item'   => 'Aggiorna Provincia',
            'add_new_item'  => 'Aggiungi Nuova Provincia',
            'search_items'  => 'Ricerca Province',
            'not_found'     => 'Nessuna Provincia',
            'no_terms'      => 'Nessuna provincia',
            'back_to_items' => '← Torna alle province',
        ],
        'public'            => true,
        'publicly_queryable' => true,
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'show_in_quick_edit' => true,
        'rewrite'           => ['slug' => 'distributore-provincia', 'with_front' => true, 'hierarchical' => true],
        'sort'              => true,
    ]);
});

// -----------------------------------------------------------------------
// Options page ACF per i distributori
// -----------------------------------------------------------------------
add_action('acf/init', function() {
    if (!function_exists('acf_add_options_sub_page')) return;

    acf_add_options_sub_page([
        'page_title'  => 'Opzioni Distributori',
        'menu_title'  => 'Opzioni',
        'menu_slug'   => 'distributori-opzioni',
        'parent_slug' => 'edit.php?post_type=distributore',
        'capability'  => 'edit_posts',
        'redirect'    => false,
        'update_button' => 'Aggiorna',
        'updated_message' => 'Opzioni Aggiornate',
    ]);
});
