<?php
/**
 * Plugin Name: Lore Helper
 * Description: Manager for Lore, Writing Style, Reminders, User Profile and Notes.
 * Version: 0.1.0
 * Author: Elite Star Services
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMM_Memory_Core {
    const TABLE_SLUG = 'pmm_memory_entries';
    const LEGACY_TABLE_SLUG = 'pmm_memory_files';
    const PAGE_SLUG = 'pmm-memory-core';
    const ENTITY_LIST_COUNT = 3;
    const SEARCH_RESULTS_PER_PAGE = 25;

    private $file_labels = [
        'lore' => 'Lore',
        'writing_style' => 'Writing Style',
        'reminders' => 'Reminders',
        'user_profile' => 'User Profile',
        'notes' => 'Notes',
    ];

    public function init() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        $this->ensure_storage_ready();
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_post_pmm_core_save_text', [$this, 'handle_save_text']);
        add_action('admin_post_pmm_core_save_entity_lists', [$this, 'handle_save_entity_lists']);
        add_action('admin_post_pmm_core_review_search_results', [$this, 'handle_review_search_results']);
        add_action('admin_post_pmm_core_upload_file', [$this, 'handle_upload_file']);
        add_action('admin_post_pmm_core_download_file', [$this, 'handle_download_file']);
        add_action('admin_post_pmm_core_clear_file', [$this, 'handle_clear_file']);
    }

    public function activate() {
        $this->ensure_storage_ready();
    }

    public function register_admin_page() {
        add_menu_page(
            'Lore Helper',
            'Lore Helper',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_admin_page'],
            'dashicons-database',
            58
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'pmm-memory-core'));
        }

        $selected = isset($_GET['pmm_file']) ? $this->normalize_file_key(wp_unslash((string) $_GET['pmm_file'])) : 'lore';
        $notice = isset($_GET['pmm_notice']) ? sanitize_key((string) wp_unslash($_GET['pmm_notice'])) : '';
        $content = $this->get_file_content($selected);
        $entry_count = $this->get_file_entry_count($selected);
        $entity_lists = $this->get_entity_lists();
        $search_keyword = isset($_GET['pmm_search_keyword']) ? trim((string) wp_unslash($_GET['pmm_search_keyword'])) : '';
        $entity_list_terms = $this->get_entity_list_terms($entity_lists);
        $search_terms = $this->get_selected_search_terms($entity_list_terms);
        $search_page = isset($_GET['pmm_search_page']) ? max(1, (int) wp_unslash((string) $_GET['pmm_search_page'])) : 1;
        $search_results = [];
        if ($search_keyword !== '' || !empty($search_terms)) {
            $search_results = $this->search_entries($search_keyword, $search_terms);
        }

        echo '<div class="wrap">';
        echo '<h1>Perchance File Helper</h1>';

        if ($notice !== '') {
            $messages = [
                'saved' => 'File content saved.',
                'uploaded' => 'File uploaded and saved.',
                'cleared' => 'File content cleared.',
                'entity_lists_saved' => 'Entity lists saved.',
                'search_results_saved' => 'Visible search result edits saved.',
                'search_results_deleted' => 'Selected search result entries deleted.',
                'search_result_missing' => 'Search result entry could not be found.',
                'upload_missing' => 'No file was uploaded.',
                'upload_invalid' => 'Invalid upload type. Use .txt or .md.',
                'upload_read_failed' => 'Could not read uploaded file.',
            ];
            if (isset($messages[$notice])) {
                echo '<div class="notice notice-success"><p>' . esc_html($messages[$notice]) . '</p></div>';
            }
        }

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '">';
        echo '<label for="pmm_file"><strong>Select File</strong></label> ';
        echo '<select id="pmm_file" name="pmm_file">';
        foreach ($this->file_labels as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($selected, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        submit_button('Load', 'secondary', 'submit', false);
        echo '</form>';

        echo '<hr>';
        echo '<details id="pmm_core_entity_lists_panel" open style="width:99%;margin-bottom:20px;">';
        echo '<summary><strong>Global Entity Lists</strong></summary>';
        echo '<p class="description" style="margin-top:10px;">These lists are saved globally and can be used to search across all stored entries in every slot.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:0;">';
        wp_nonce_field('pmm_core_save_entity_lists');
        echo '<input type="hidden" name="action" value="pmm_core_save_entity_lists">';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;width:99%;">';
        for ($index = 1; $index <= self::ENTITY_LIST_COUNT; $index++) {
            echo '<div>';
            echo '<label for="pmm_entity_list_' . esc_attr((string) $index) . '"><strong>Entity List ' . esc_html((string) $index) . '</strong></label>';
            echo '<textarea id="pmm_entity_list_' . esc_attr((string) $index) . '" name="pmm_entity_list_' . esc_attr((string) $index) . '" rows="10" class="large-text code" style="margin-top:6px;width:99%;box-sizing:border-box;">' . esc_textarea($entity_lists[$index]) . '</textarea>';
            echo '<p class="description">One term per line, or comma-separated.</p>';
            echo '</div>';
        }
        echo '</div>';
        echo '<p>';
        submit_button('Save Entity Lists', 'secondary', 'submit', false);
        echo '</p>';
        echo '</form>';
        echo '</details>';
        echo '<script>document.addEventListener("DOMContentLoaded", function(){ var panel=document.getElementById("pmm_core_entity_lists_panel"); if(!panel || !window.localStorage){ return; } var key="pmm_core_entity_lists_panel_open"; var stored=window.localStorage.getItem(key); if(stored==="closed"){ panel.open=false; } else if(stored==="open"){ panel.open=true; } panel.addEventListener("toggle", function(){ window.localStorage.setItem(key, panel.open ? "open" : "closed"); }); });</script>';

        echo '<h2>Search All Entries</h2>';
        echo '<p class="description">Search across all slots using a selected term or phrase from any populated entity list, plus an optional manual keyword.</p>';
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="margin-bottom:20px;padding:12px;border:1px solid #dcdcde;background:#fff;width:99%;box-sizing:border-box;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '">';
        echo '<input type="hidden" name="pmm_file" value="' . esc_attr($selected) . '">';
        echo '<p><label for="pmm_search_keyword"><strong>Manual keyword</strong></label><br>';
        echo '<input id="pmm_search_keyword" type="search" name="pmm_search_keyword" value="' . esc_attr($search_keyword) . '" class="regular-text" style="width:99%;max-width:none;box-sizing:border-box;" placeholder="Optional keyword"></p>';
        echo '<p><strong>Select from entity lists</strong></p>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;width:99%;margin-bottom:12px;">';
        for ($index = 1; $index <= self::ENTITY_LIST_COUNT; $index++) {
            if (empty($entity_list_terms[$index])) {
                continue;
            }
            echo '<div>';
            echo '<label for="pmm_search_term_' . esc_attr((string) $index) . '"><strong>Entity List ' . esc_html((string) $index) . '</strong></label><br>';
            echo '<select id="pmm_search_term_' . esc_attr((string) $index) . '" name="pmm_search_term_' . esc_attr((string) $index) . '" class="regular-text" style="margin-top:6px;width:100%;">';
            echo '<option value="">' . esc_html__('Any / none selected', 'pmm-memory-core') . '</option>';
            foreach ($entity_list_terms[$index] as $term) {
                echo '<option value="' . esc_attr($term) . '" ' . selected(isset($search_terms[$index]) ? $search_terms[$index] : '', $term, false) . '>' . esc_html($term) . '</option>';
            }
            echo '</select>';
            echo '</div>';
        }
        echo '</div>';
        submit_button('Search All Slots', 'primary', 'submit', false);
        echo ' <a class="button button-secondary" href="' . esc_url(add_query_arg(['page' => self::PAGE_SLUG, 'pmm_file' => $selected], admin_url('admin.php'))) . '">Clear Search</a>';
        echo '</form>';

        if ($search_keyword !== '' || !empty($search_terms)) {
            echo '<h3>Search Results</h3>';
            if (empty($search_results)) {
                echo '<p class="description">No entries matched the selected terms or keyword.</p>';
            } else {
                $search_total = count($search_results);
                $search_total_pages = max(1, (int) ceil($search_total / self::SEARCH_RESULTS_PER_PAGE));
                $search_page = min($search_page, $search_total_pages);
                $search_offset = ($search_page - 1) * self::SEARCH_RESULTS_PER_PAGE;
                $paged_results = array_slice($search_results, $search_offset, self::SEARCH_RESULTS_PER_PAGE);

                echo '<p class="description">Found ' . esc_html((string) $search_total) . ' matching entries. Reviewing page ' . esc_html((string) $search_page) . ' of ' . esc_html((string) $search_total_pages) . '.</p>';
                if ($search_total_pages > 1) {
                    echo '<p style="margin:6px 0 10px 0;">';
                    if ($search_page > 1) {
                        echo '<a class="button" href="' . esc_url(add_query_arg(array_merge(['page' => self::PAGE_SLUG, 'pmm_file' => $selected, 'pmm_search_keyword' => $search_keyword, 'pmm_search_page' => $search_page - 1], $this->search_terms_to_query_args($search_terms)), admin_url('admin.php'))) . '">&larr; Prev</a> ';
                    }
                    if ($search_page < $search_total_pages) {
                        echo '<a class="button" href="' . esc_url(add_query_arg(array_merge(['page' => self::PAGE_SLUG, 'pmm_file' => $selected, 'pmm_search_keyword' => $search_keyword, 'pmm_search_page' => $search_page + 1], $this->search_terms_to_query_args($search_terms)), admin_url('admin.php'))) . '">Next &rarr;</a>';
                    }
                    echo '</p>';
                }
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0 0 12px 0;">';
                wp_nonce_field('pmm_core_review_search_results');
                echo '<input type="hidden" name="action" value="pmm_core_review_search_results">';
                echo '<input type="hidden" name="pmm_file" value="' . esc_attr($selected) . '">';
                echo '<input type="hidden" name="pmm_search_keyword" value="' . esc_attr($search_keyword) . '">';
                echo '<input type="hidden" name="pmm_search_page" value="' . esc_attr((string) $search_page) . '">';
                for ($index = 1; $index <= self::ENTITY_LIST_COUNT; $index++) {
                    echo '<input type="hidden" name="pmm_search_term_' . esc_attr((string) $index) . '" value="' . esc_attr(isset($search_terms[$index]) ? $search_terms[$index] : '') . '">';
                }
                echo '<p style="margin:0 0 8px 0;">';
                submit_button('Save Visible Edits', 'secondary', 'pmm_review_action_save', false, ['style' => 'margin:0 8px 0 0;']);
                submit_button('Delete Selected', 'delete', 'pmm_review_action_delete', false, ['onclick' => "return confirm('Delete selected entries from the current search review page?');", 'style' => 'margin:0;']);
                echo '</p>';
                echo '<div style="overflow:auto;width:99%;border:1px solid #dcdcde;background:#fff;">';
                echo '<table class="widefat striped">';
                echo '<thead><tr><th style="width:5%;"><label><input type="checkbox" id="pmm-search-select-all"> All</label></th><th style="width:12%;">Slot</th><th style="width:10%;">Entry #</th><th style="width:18%;">Matched Terms</th><th>Entry</th></tr></thead><tbody>';
                foreach ($paged_results as $result) {
                    $slot_label = isset($this->file_labels[$result['file_key']]) ? $this->file_labels[$result['file_key']] : $result['file_key'];
                    echo '<tr>';
                    echo '<td style="vertical-align:top;"><input type="checkbox" class="pmm-search-delete-row" name="pmm_delete_ids[]" value="' . esc_attr((string) $result['id']) . '"></td>';
                    echo '<td style="vertical-align:top;">' . esc_html($slot_label) . '</td>';
                    echo '<td style="vertical-align:top;">' . esc_html((string) $result['entry_index']) . '</td>';
                    echo '<td style="vertical-align:top;">' . esc_html(implode(', ', $result['matched_terms'])) . '</td>';
                    echo '<td style="vertical-align:top;"><textarea name="pmm_search_rows[' . esc_attr((string) $result['id']) . ']" rows="4" class="large-text code" style="width:100%;box-sizing:border-box;">' . esc_textarea($result['content']) . '</textarea></td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
                echo '<p style="margin:8px 0 0 0;">';
                submit_button('Save Visible Edits', 'secondary', 'pmm_review_action_save', false, ['style' => 'margin:0 8px 0 0;']);
                submit_button('Delete Selected', 'delete', 'pmm_review_action_delete', false, ['onclick' => "return confirm('Delete selected entries from the current search review page?');", 'style' => 'margin:0;']);
                echo '</p>';
                echo '</form>';
                echo '<script>document.addEventListener("DOMContentLoaded", function(){ var all=document.getElementById("pmm-search-select-all"); if(!all){return;} all.addEventListener("change", function(){ var rows=document.querySelectorAll(".pmm-search-delete-row"); for(var i=0;i<rows.length;i++){ rows[i].checked=!!all.checked; } }); });</script>';
            }
        }

        echo '<h2>' . esc_html($this->file_labels[$selected]) . ' (' . esc_html((string) $entry_count) . ' entries)</h2>';
        echo '<p class="description">Each blank-line-separated entry is stored individually and reassembled here for editing and download.</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:16px;">';
        wp_nonce_field('pmm_core_save_text');
        echo '<input type="hidden" name="action" value="pmm_core_save_text">';
        echo '<input type="hidden" name="pmm_file" value="' . esc_attr($selected) . '">';
        echo '<textarea id="pmm-main-content" name="pmm_content" rows="22" class="large-text code">' . esc_textarea($content) . '</textarea>';
        echo '<p>';
        submit_button('Save Text', 'primary', 'submit', false);
        echo ' <button type="button" class="button" id="pmm-copy-main-content">Copy to Clipboard</button>';
        echo ' <span id="pmm-copy-main-content-status" aria-live="polite" style="display:inline-block;min-width:72px;"></span>';
        echo '</p>';
        echo '</form>';
        echo '<script>document.addEventListener("DOMContentLoaded", function(){ var btn=document.getElementById("pmm-copy-main-content"); var area=document.getElementById("pmm-main-content"); var status=document.getElementById("pmm-copy-main-content-status"); if(!btn||!area){return;} btn.addEventListener("click", function(){ var setStatus=function(message){ if(status){ status.textContent=message; } }; var clearStatus=function(){ if(status){ setTimeout(function(){ status.textContent=""; }, 1500); } }; if(navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(area.value).then(function(){ setStatus("Copied"); clearStatus(); }).catch(function(){ setStatus("Copy failed"); clearStatus(); }); return; } area.focus(); area.select(); try{ var ok=document.execCommand("copy"); setStatus(ok ? "Copied" : "Copy failed"); }catch(err){ setStatus("Copy failed"); } clearStatus(); }); });</script>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" style="margin-bottom:12px;">';
        wp_nonce_field('pmm_core_upload_file');
        echo '<input type="hidden" name="action" value="pmm_core_upload_file">';
        echo '<input type="hidden" name="pmm_file" value="' . esc_attr($selected) . '">';
        echo '<input type="file" name="pmm_upload" accept=".txt,.md" required> ';
        submit_button('Upload File Into This Slot', 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block; margin-right:8px;">';
        wp_nonce_field('pmm_core_download_file');
        echo '<input type="hidden" name="action" value="pmm_core_download_file">';
        echo '<input type="hidden" name="pmm_file" value="' . esc_attr($selected) . '">';
        submit_button('Download', 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;">';
        wp_nonce_field('pmm_core_clear_file');
        echo '<input type="hidden" name="action" value="pmm_core_clear_file">';
        echo '<input type="hidden" name="pmm_file" value="' . esc_attr($selected) . '">';
        submit_button('Clear This Slot', 'delete', 'submit', false, ['onclick' => "return confirm('Clear this file slot?');"]);
        echo '</form>';

        echo '</div>';
    }

    public function handle_save_text() {
        $this->require_admin();
        check_admin_referer('pmm_core_save_text');

        $file_key = $this->posted_file_key();
        $content = isset($_POST['pmm_content']) ? wp_unslash((string) $_POST['pmm_content']) : '';

        $this->upsert_file_content($file_key, $content);
        $this->redirect_with_notice($file_key, 'saved');
    }

    public function handle_save_entity_lists() {
        $this->require_admin();
        check_admin_referer('pmm_core_save_entity_lists');

        for ($index = 1; $index <= self::ENTITY_LIST_COUNT; $index++) {
            $field = 'pmm_entity_list_' . $index;
            $value = isset($_POST[$field]) ? wp_unslash((string) $_POST[$field]) : '';
            update_option($this->entity_list_option_key($index), $this->normalize_entity_list_text($value), false);
        }

        $file_key = isset($_POST['pmm_file']) ? $this->normalize_file_key(wp_unslash((string) $_POST['pmm_file'])) : 'lore';
        $this->redirect_with_notice($file_key, 'entity_lists_saved');
    }

    public function handle_review_search_results() {
        $this->require_admin();
        check_admin_referer('pmm_core_review_search_results');

        if (isset($_POST['pmm_review_action_delete'])) {
            $ids = isset($_POST['pmm_delete_ids']) ? (array) wp_unslash($_POST['pmm_delete_ids']) : [];
            $deleted = $this->delete_entries($ids);
            $this->redirect_search_with_notice($deleted > 0 ? 'search_results_deleted' : 'search_result_missing');
        }

        $rows = isset($_POST['pmm_search_rows']) ? (array) wp_unslash($_POST['pmm_search_rows']) : [];
        $saved = $this->update_entries_bulk($rows);
        $this->redirect_search_with_notice($saved > 0 ? 'search_results_saved' : 'search_result_missing');
    }

    public function handle_upload_file() {
        $this->require_admin();
        check_admin_referer('pmm_core_upload_file');

        $file_key = $this->posted_file_key();
        if (empty($_FILES['pmm_upload']['tmp_name'])) {
            $this->redirect_with_notice($file_key, 'upload_missing');
        }

        $name = isset($_FILES['pmm_upload']['name']) ? sanitize_file_name(wp_unslash((string) $_FILES['pmm_upload']['name'])) : '';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['txt', 'md'], true)) {
            $this->redirect_with_notice($file_key, 'upload_invalid');
        }

        $content = file_get_contents($_FILES['pmm_upload']['tmp_name']);
        if ($content === false) {
            $this->redirect_with_notice($file_key, 'upload_read_failed');
        }

        $this->upsert_file_content($file_key, (string) $content);
        $this->redirect_with_notice($file_key, 'uploaded');
    }

    public function handle_download_file() {
        $this->require_admin();
        check_admin_referer('pmm_core_download_file');

        $file_key = $this->posted_file_key();
        $label = isset($this->file_labels[$file_key]) ? $this->file_labels[$file_key] : $file_key;
        $content = $this->get_file_content($file_key);

        $filename = sanitize_file_name(strtolower(str_replace(' ', '-', $label)) . '.txt');

        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: text/plain; charset=' . get_option('blog_charset'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    public function handle_clear_file() {
        $this->require_admin();
        check_admin_referer('pmm_core_clear_file');

        $file_key = $this->posted_file_key();
        $this->upsert_file_content($file_key, '');
        $this->redirect_with_notice($file_key, 'cleared');
    }

    private function require_admin() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do that.', 'pmm-memory-core'));
        }
    }

    private function posted_file_key() {
        $raw = isset($_POST['pmm_file']) ? wp_unslash((string) $_POST['pmm_file']) : 'lore';
        return $this->normalize_file_key($raw);
    }

    private function normalize_file_key($value) {
        $value = sanitize_key((string) $value);
        return isset($this->file_labels[$value]) ? $value : 'lore';
    }

    private function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SLUG;
    }

    private function legacy_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::LEGACY_TABLE_SLUG;
    }

    private function get_file_content($file_key) {
        global $wpdb;
        $this->ensure_storage_ready();
        $table = $this->table_name();
        $file_key = $this->normalize_file_key($file_key);

        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT content FROM {$table} WHERE file_key = %s ORDER BY entry_index ASC, id ASC",
            $file_key
        ));
        if (!is_array($rows) || empty($rows)) {
            return '';
        }

        $entries = [];
        foreach ($rows as $row) {
            if (!is_string($row)) {
                continue;
            }
            $entry = trim($row);
            if ($entry !== '') {
                $entries[] = $entry;
            }
        }

        return implode("\n\n", $entries);
    }

    private function get_file_entry_count($file_key) {
        global $wpdb;
        $this->ensure_storage_ready();
        $table = $this->table_name();
        $file_key = $this->normalize_file_key($file_key);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE file_key = %s",
            $file_key
        ));
    }

    private function upsert_file_content($file_key, $content) {
        global $wpdb;
        $this->ensure_storage_ready();
        $table = $this->table_name();
        $file_key = $this->normalize_file_key($file_key);

        $entries = $this->split_entries($content);
        $now = current_time('mysql');

        $wpdb->delete($table, ['file_key' => $file_key], ['%s']);

        foreach ($entries as $index => $entry) {
            $wpdb->insert($table, [
                'file_key' => $file_key,
                'entry_index' => $index + 1,
                'content' => $entry,
                'updated_at' => $now,
            ], ['%s', '%d', '%s', '%s']);
        }
    }

    private function update_entry_content($entry_id, $content) {
        global $wpdb;
        $this->ensure_storage_ready();
        $table = $this->table_name();

        $entry_id = (int) $entry_id;
        $content = trim((string) $content);
        if ($entry_id < 1 || $content === '') {
            return false;
        }

        $updated = $wpdb->update(
            $table,
            [
                'content' => $content,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $entry_id],
            ['%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    private function update_entries_bulk($rows) {
        $saved = 0;
        foreach ((array) $rows as $entry_id => $content) {
            if ($this->update_entry_content((int) $entry_id, (string) $content)) {
                $saved++;
            }
        }

        return $saved;
    }

    private function delete_entry($entry_id) {
        global $wpdb;
        $this->ensure_storage_ready();
        $table = $this->table_name();

        $entry_id = (int) $entry_id;
        if ($entry_id < 1) {
            return false;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, file_key FROM {$table} WHERE id = %d LIMIT 1",
            $entry_id
        ), ARRAY_A);
        if (!is_array($row) || empty($row['file_key'])) {
            return false;
        }

        $deleted = $wpdb->delete($table, ['id' => $entry_id], ['%d']);
        if ($deleted === false || (int) $deleted < 1) {
            return false;
        }

        $this->resequence_file_entries((string) $row['file_key']);
        return true;
    }

    private function delete_entries($entry_ids) {
        $deleted = 0;
        foreach ((array) $entry_ids as $entry_id) {
            if ($this->delete_entry((int) $entry_id)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function resequence_file_entries($file_key) {
        global $wpdb;
        $table = $this->table_name();
        $file_key = $this->normalize_file_key($file_key);

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table} WHERE file_key = %s ORDER BY entry_index ASC, id ASC",
            $file_key
        ));
        if (!is_array($ids) || empty($ids)) {
            return;
        }

        $now = current_time('mysql');
        foreach ($ids as $index => $id) {
            $wpdb->update(
                $table,
                [
                    'entry_index' => $index + 1,
                    'updated_at' => $now,
                ],
                ['id' => (int) $id],
                ['%d', '%s'],
                ['%d']
            );
        }
    }

    private function split_entries($content) {
        $content = str_replace(["\r\n", "\r"], "\n", (string) $content);
        $parts = preg_split("/\n\s*\n+/", $content);
        if (!is_array($parts)) {
            return [];
        }

        $entries = [];
        foreach ($parts as $part) {
            $entry = trim((string) $part);
            if ($entry !== '') {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function get_entity_lists() {
        $lists = [];
        for ($index = 1; $index <= self::ENTITY_LIST_COUNT; $index++) {
            $lists[$index] = (string) get_option($this->entity_list_option_key($index), '');
        }

        return $lists;
    }

    private function entity_list_option_key($index) {
        return 'pmm_core_entity_list_' . max(1, (int) $index);
    }

    private function normalize_entity_list_text($text) {
        $terms = $this->parse_entity_terms($text);
        return implode("\n", $terms);
    }

    private function get_entity_list_terms($entity_lists) {
        $out = [];
        for ($index = 1; $index <= self::ENTITY_LIST_COUNT; $index++) {
            $out[$index] = $this->parse_entity_terms(isset($entity_lists[$index]) ? $entity_lists[$index] : '');
        }

        return $out;
    }

    private function parse_entity_terms($text) {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        $parts = preg_split('/[\n,]+/', $text);
        if (!is_array($parts)) {
            return [];
        }

        $terms = [];
        foreach ($parts as $part) {
            $term = trim((string) $part);
            if ($term !== '') {
                $terms[] = $term;
            }
        }

        natcasesort($terms);
        return array_values(array_unique($terms));
    }

    private function get_selected_search_terms($entity_list_terms) {
        $selected = [];
        for ($index = 1; $index <= self::ENTITY_LIST_COUNT; $index++) {
            $field = 'pmm_search_term_' . $index;
            $value = isset($_GET[$field]) ? trim((string) wp_unslash($_GET[$field])) : '';
            if ($value === '') {
                continue;
            }
            if (in_array($value, isset($entity_list_terms[$index]) ? $entity_list_terms[$index] : [], true)) {
                $selected[$index] = $value;
            }
        }

        return $selected;
    }

    private function search_redirect_args() {
        $args = [
            'page' => self::PAGE_SLUG,
            'pmm_file' => isset($_POST['pmm_file']) ? $this->normalize_file_key(wp_unslash((string) $_POST['pmm_file'])) : 'lore',
        ];

        $keyword = isset($_POST['pmm_search_keyword']) ? trim((string) wp_unslash($_POST['pmm_search_keyword'])) : '';
        if ($keyword !== '') {
            $args['pmm_search_keyword'] = $keyword;
        }

        for ($index = 1; $index <= self::ENTITY_LIST_COUNT; $index++) {
            $field = 'pmm_search_term_' . $index;
            $value = isset($_POST[$field]) ? trim((string) wp_unslash($_POST[$field])) : '';
            if ($value !== '') {
                $args[$field] = $value;
            }
        }

        $search_page = isset($_POST['pmm_search_page']) ? max(1, (int) wp_unslash((string) $_POST['pmm_search_page'])) : 1;
        if ($search_page > 1) {
            $args['pmm_search_page'] = $search_page;
        }

        return $args;
    }

    private function search_terms_to_query_args($search_terms) {
        $args = [];
        for ($index = 1; $index <= self::ENTITY_LIST_COUNT; $index++) {
            if (!empty($search_terms[$index])) {
                $args['pmm_search_term_' . $index] = $search_terms[$index];
            }
        }

        return $args;
    }

    private function redirect_search_with_notice($notice) {
        $args = $this->search_redirect_args();
        $args['pmm_notice'] = sanitize_key((string) $notice);

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function search_entries($keyword, $selected_terms) {
        global $wpdb;
        $this->ensure_storage_ready();

        $table = $this->table_name();
        $rows = $wpdb->get_results("SELECT id, file_key, entry_index, content FROM {$table} ORDER BY file_key ASC, entry_index ASC, id ASC", ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return [];
        }

        $terms = array_values(array_unique(array_values($selected_terms)));

        $keyword = trim((string) $keyword);
        $keyword_lower = function_exists('mb_strtolower') ? mb_strtolower($keyword, 'UTF-8') : strtolower($keyword);

        $results = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $content = isset($row['content']) ? trim((string) $row['content']) : '';
            if ($content === '') {
                continue;
            }

            $haystack = function_exists('mb_strtolower') ? mb_strtolower($content, 'UTF-8') : strtolower($content);
            $matched_terms = [];

            foreach ($terms as $term) {
                $needle = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);
                if ($needle !== '' && strpos($haystack, $needle) !== false) {
                    $matched_terms[] = $term;
                }
            }

            if ($keyword_lower !== '' && strpos($haystack, $keyword_lower) !== false) {
                $matched_terms[] = $keyword;
            }

            $matched_terms = array_values(array_unique(array_filter($matched_terms, 'strlen')));
            if (empty($matched_terms)) {
                continue;
            }

            $results[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'file_key' => isset($row['file_key']) ? (string) $row['file_key'] : '',
                'entry_index' => isset($row['entry_index']) ? (int) $row['entry_index'] : 0,
                'content' => $content,
                'matched_terms' => $matched_terms,
            ];
        }

        return $results;
    }

    private function ensure_storage_ready() {
        static $ready = false;
        if ($ready) {
            return;
        }

        // Mark initialization before migration so legacy upserts do not recurse.
        $ready = true;

        global $wpdb;
        $table = $this->table_name();
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            file_key varchar(64) NOT NULL,
            entry_index bigint(20) unsigned NOT NULL,
            content longtext NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY file_entry_unique (file_key, entry_index),
            KEY file_key_idx (file_key)
        ) {$charset};";

        dbDelta($sql);
        $this->migrate_legacy_rows();
    }

    private function migrate_legacy_rows() {
        global $wpdb;
        $table = $this->table_name();
        $legacy_table = $this->legacy_table_name();

        $legacy_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_table));
        if ($legacy_exists !== $legacy_table) {
            return;
        }

        foreach (array_keys($this->file_labels) as $file_key) {
            $existing_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE file_key = %s",
                $file_key
            ));
            if ($existing_count > 0) {
                continue;
            }

            $legacy_content = $wpdb->get_var($wpdb->prepare(
                "SELECT content FROM {$legacy_table} WHERE file_key = %s LIMIT 1",
                $file_key
            ));
            if (!is_string($legacy_content) || trim($legacy_content) === '') {
                continue;
            }

            $this->upsert_file_content($file_key, $legacy_content);
        }
    }

    private function redirect_with_notice($file_key, $notice) {
        wp_safe_redirect(add_query_arg([
            'page' => self::PAGE_SLUG,
            'pmm_file' => $this->normalize_file_key($file_key),
            'pmm_notice' => sanitize_key((string) $notice),
        ], admin_url('admin.php')));
        exit;
    }
}

$pmm_memory_core = new PMM_Memory_Core();
$pmm_memory_core->init();
