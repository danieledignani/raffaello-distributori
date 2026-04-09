<?php

// ============================================================================
// Registrazione pagina Strumenti nel menu Distributori
// ============================================================================
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=distributore',
        'Strumenti Distributori',
        'Strumenti',
        'manage_options',
        'rd-strumenti',
        'rd_admin_strumenti_render'
    );
});

// ============================================================================
// Rendering pagina
// ============================================================================
function rd_admin_strumenti_render() {
    // Rileva snippet pericolosi (stessi nomi usati negli snippet di raffaello-scuola)
    $snippet_hooks = [
        'rc_recreation_classe_sconto'          => has_action('wp_loaded', 'rc_recreation_classe_sconto'),
        'delete_all_posts_of_custom_post_type' => has_action('wp_loaded', 'delete_all_posts_of_custom_post_type'),
        'delete_all_classe_sconto'             => has_action('wp_loaded', 'delete_all_classe_sconto'),
    ];
    $has_dangerous_hooks = array_filter($snippet_hooks);
    ?>
    <div class="wrap">
        <h1>🛠 Strumenti Distributori</h1>

        <?php if ($has_dangerous_hooks): ?>
        <div class="notice notice-error">
            <p>
                <strong>⚠ Attenzione: snippet pericolosi rilevati!</strong><br>
                I seguenti hook su <code>wp_loaded</code> sono ancora attivi e vengono eseguiti ad ogni caricamento di pagina.
                Disabilitali immediatamente nel gestore degli snippet:
            </p>
            <ul style="list-style:disc;padding-left:20px">
                <?php foreach (array_keys($has_dangerous_hooks) as $fn): ?>
                    <li><code><?= esc_html($fn) ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div id="rd-notice" style="margin-top:16px;display:none"></div>

        <div style="display:flex;flex-wrap:wrap;gap:20px;margin-top:20px;align-items:flex-start">

            <!-- CARD: Ricrea Province -->
            <div class="card" style="min-width:320px;max-width:480px;padding:20px">
                <h2 style="margin-top:0">🗺 Ricrea Province</h2>
                <p>
                    Elimina tutte le province e le regioni dalla tassonomia
                    <code>distributore_provincia</code> e le ricrea
                    scaricando il CSV configurato nelle <a href="<?= admin_url('edit.php?post_type=distributore&page=distributori-opzioni') ?>">Opzioni</a>.
                </p>
                <button id="rd-btn-province" class="button button-primary">
                    Ricrea Province
                </button>
                <span id="rd-spinner-province" class="spinner" style="float:none;visibility:hidden;margin-left:8px"></span>
            </div>

            <!-- CARD: Elimina Distributori -->
            <div class="card" style="min-width:320px;max-width:480px;padding:20px;border-left:4px solid #d63638">
                <h2 style="margin-top:0;color:#d63638">⚠ Elimina tutti i Distributori</h2>
                <p>
                    Elimina <strong>permanentemente</strong> tutti i post di tipo
                    <code>distributore</code> incluse le relative tassonomie e campi ACF.
                    L'operazione è irreversibile.
                </p>
                <button id="rd-btn-delete" class="button" style="color:#d63638;border-color:#d63638">
                    Elimina tutti i distributori
                </button>

                <div id="rd-progress-wrap" style="display:none;margin-top:16px">
                    <div style="background:#f0f0f1;border-radius:3px;height:22px;overflow:hidden;border:1px solid #c3c4c7">
                        <div id="rd-progress-bar"
                             style="background:#2271b1;height:100%;width:0%;transition:width .25s ease;display:flex;align-items:center;justify-content:center">
                        </div>
                    </div>
                    <p id="rd-progress-text" style="margin:6px 0 0;color:#646970;font-size:13px"></p>
                </div>
            </div>

        </div>
    </div>

    <script>
    jQuery(function($) {
        const PREFIX    = 'rd';
        const POST_TYPE = 'distributore';
        const NONCE     = '<?= wp_create_nonce('rd_admin_action') ?>';
        const BATCH     = 30;

        function showNotice(type, msg) {
            $('#rd-notice')
                .attr('class', 'notice notice-' + type + ' is-dismissible')
                .html('<p>' + msg + '</p>')
                .show();
        }

        // ---- Ricrea Province ----
        $('#rd-btn-province').on('click', function() {
            const btn     = $(this);
            const spinner = $('#rd-spinner-province');
            btn.prop('disabled', true);
            spinner.css('visibility', 'visible');
            $('#rd-notice').hide();

            $.post(ajaxurl, { action: 'rd_ricrea_province', nonce: NONCE })
            .done(function(res) {
                showNotice(res.success ? 'success' : 'error',
                           res.data.message || 'Errore sconosciuto.');
            })
            .fail(function() { showNotice('error', 'Errore di rete.'); })
            .always(function() {
                btn.prop('disabled', false);
                spinner.css('visibility', 'hidden');
            });
        });

        // ---- Elimina post (con progress) ----
        $('#rd-btn-delete').on('click', function() {
            if (!confirm('Sei sicuro di voler eliminare TUTTI i distributori?\nQuesta operazione è irreversibile.')) return;

            const btn = $(this);
            btn.prop('disabled', true);
            $('#rd-notice').hide();
            $('#rd-progress-wrap').show();
            $('#rd-progress-bar').css('width', '0%').text('');
            $('#rd-progress-text').text('Conteggio in corso…');

            $.post(ajaxurl, { action: 'rd_delete_posts', nonce: NONCE, step: 'init' })
            .done(function(res) {
                if (!res.success) return finish(false, res.data.message);
                const total = parseInt(res.data.total, 10);
                if (total === 0) return finish(true, 'Nessun elemento trovato.');
                runBatch(total, 0);
            })
            .fail(function() { finish(false, 'Errore di rete durante il conteggio.'); });

            function runBatch(total, deleted) {
                $.post(ajaxurl, { action: 'rd_delete_posts', nonce: NONCE, step: 'batch', batch: BATCH })
                .done(function(res) {
                    if (!res.success) return finish(false, res.data.message);
                    deleted += parseInt(res.data.deleted, 10);
                    const pct = Math.min(100, Math.round((deleted / total) * 100));
                    $('#rd-progress-bar').css('width', pct + '%').text(pct + '%');
                    $('#rd-progress-text').text('Eliminati ' + deleted + ' di ' + total + '…');
                    if (res.data.done) {
                        finish(true, 'Completato: eliminati ' + deleted + ' elementi.');
                    } else {
                        setTimeout(function() { runBatch(total, deleted); }, 100);
                    }
                })
                .fail(function() { finish(false, 'Errore di rete durante l\'eliminazione.'); });
            }

            function finish(ok, msg) {
                showNotice(ok ? 'success' : 'error', msg);
                btn.prop('disabled', false);
                if (ok) {
                    $('#rd-progress-bar').css('width', '100%').text('100%');
                    $('#rd-progress-text').text(msg);
                }
            }
        });
    });
    </script>
    <?php
}

// ============================================================================
// AJAX: Ricrea Province
// ============================================================================
add_action('wp_ajax_rd_ricrea_province', function() {
    check_ajax_referer('rd_admin_action', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Non autorizzato.']);

    rd_recreation_province();
    wp_send_json_success(['message' => 'Province ricreate con successo.']);
});

// ============================================================================
// AJAX: Elimina distributori (in batch)
// ============================================================================
add_action('wp_ajax_rd_delete_posts', function() {
    check_ajax_referer('rd_admin_action', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Non autorizzato.']);

    $step = sanitize_text_field($_POST['step'] ?? '');

    if ($step === 'init') {
        $ids = get_posts(['post_type' => 'distributore', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']);
        wp_send_json_success(['total' => count($ids)]);
    }

    if ($step === 'batch') {
        $batch = max(1, (int)($_POST['batch'] ?? 30));
        $ids   = get_posts(['post_type' => 'distributore', 'post_status' => 'any', 'posts_per_page' => $batch, 'fields' => 'ids']);

        foreach ($ids as $id) {
            wp_set_object_terms($id, [], 'distributore_provincia');
            wp_delete_post($id, true);
        }

        $remaining = get_posts(['post_type' => 'distributore', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids']);
        wp_send_json_success(['deleted' => count($ids), 'done' => empty($remaining)]);
    }

    wp_send_json_error(['message' => 'Step non valido.']);
});
