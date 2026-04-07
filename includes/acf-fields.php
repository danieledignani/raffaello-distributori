<?php

/**
 * Registra la cartella acf-json/ del plugin come path di load e save per ACF.
 * Stessa logica del plugin raffaello-concessionari: il file JSON è la fonte di verità,
 * l'admin può fare "Sync" se diverge dal DB, e qualsiasi modifica da admin viene
 * salvata nel file permettendo il versioning via git.
 */

add_filter('acf/settings/load_json', function(array $paths): array {
    $paths[] = DISTRIBUTORI_PLUGIN_DIR . 'acf-json';
    return $paths;
});

add_filter('acf/settings/save_json', function(string $path): string {
    return DISTRIBUTORI_PLUGIN_DIR . 'acf-json';
});
