<?php

class SFP_Admin {

    public function __construct() {
        add_action('init', [$this, 'maybe_start_session'], 1);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_sinergiacrm_login', [$this, 'handle_login']);
        add_action('admin_post_sinergiacrm_logout', [$this, 'handle_logout']);
        add_action('wp_ajax_sinergiacrm_get_esdeveniments_ajax', [$this, 'get_esdeveniments_ajax']);
        add_action('wp_ajax_sinergiacrm_get_form_template', [$this, 'get_form_template']);
        add_action('wp_ajax_nopriv_sinergiacrm_get_form_template', [$this, 'get_form_template']);
    }

    public function maybe_start_session() {
        if (!session_id()) {
            session_start();
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'SinergiaCRM',
            'SinergiaCRM',
            'manage_options',
            'sinergia-crm',
            [$this, 'render_admin_page'],
            'dashicons-rest-api'
        );
    }

    public function render_admin_page() {
        $logged_in = isset($_SESSION['sinergia_session_id']);
        ?>
        <div class="wrap">
            <h1>SinergiaCRM Dashboard</h1>

            <nav class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active" data-tab="login">Login</a>
                <a href="#" class="nav-tab <?= $logged_in ? '' : 'nav-tab-disabled' ?>" data-tab="events" 
                   <?= $logged_in ? '' : 'onclick="return false;" style="pointer-events: none; opacity: 0.5;"' ?>>
                    Esdeveniments
                </a>
                <a href="#" class="nav-tab <?= $logged_in ? '' : 'nav-tab-disabled' ?>" data-tab="dummy"
                   <?= $logged_in ? '' : 'onclick="return false;" style="pointer-events: none; opacity: 0.5;"' ?>>
                    Historial
                </a>
                <?php if ($logged_in): ?>
                    <form method="POST" action="<?= admin_url('admin-post.php') ?>" style="float:right; margin-top:8px;">
                        <input type="hidden" name="action" value="sinergiacrm_logout">
                        <?php wp_nonce_field('sinergiacrm_nonce', 'sinergiacrm_nonce_field'); ?>
                        <button type="submit" class="button button-secondary">Tancar Sessió</button>
                    </form>
                <?php endif; ?>
            </nav>

            <div id="tab-content" style="margin-top:20px;">
                <div id="tab-login" class="tab-pane">
                    <?php $this->render_login_tab(); ?>
                </div>
                <div id="tab-events" class="tab-pane" style="display:none;">
                    <div id="esdeveniments-list">
                        <div id="events-content">
                            <p><em>Carregant esdeveniments...</em></p>
                        </div>
                    </div>
                </div>
                <div id="tab-dummy" class="tab-pane" style="display:none;">
                    <div id="dummy-content">
                        <em>A quien madruga...</em>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_sinergia-crm') return;
        
        wp_enqueue_style('sfp-style', SFP_URL . 'assets/css/style.css');
        wp_enqueue_script('sfp-script', SFP_URL . 'assets/js/admin-script.js', ['jquery'], time(), true); // Usar time() como versión para evitar caché
        
        wp_localize_script('sfp-script', 'formTemplateData', [
            'formTemplateUrl' => admin_url('admin-ajax.php?action=sinergiacrm_get_form_template')
        ]);
        
        wp_localize_script('sfp-script', 'ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sinergiacrm_nonce'),
            'is_logged_in' => isset($_SESSION['sinergia_session_id']) ? 'yes' : 'no'
        ]);
    }
    
    public function render_login_tab() {
        $logged_in = isset($_SESSION['sinergia_session_id']);
        if ($logged_in) {
            echo "<p><strong>Hola, " . esc_html($_SESSION['sinergia_user_name']) . "!</strong></p>";
            return;
        }
        ?>
        <form method="POST" action="<?= admin_url('admin-post.php') ?>">
            <input type="hidden" name="action" value="sinergiacrm_login">
            <?php wp_nonce_field('sinergiacrm_nonce', 'sinergiacrm_nonce_field'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="username">Usuari</label></th>
                    <td><input name="username" type="text" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="password">Contrasenya</label></th>
                    <td><input name="password" type="password" class="regular-text" required></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Iniciar Sessió</button>
            </p>
        </form>
        <?php
        if (isset($_GET['login']) && $_GET['login'] === 'failed') {
            echo '<p style="color:red;">Error d\'autenticació. Verifica les credencials.</p>';
        }
    }

    public function handle_login() {
        if (!isset($_POST['sinergiacrm_nonce_field']) || !wp_verify_nonce($_POST['sinergiacrm_nonce_field'], 'sinergiacrm_nonce')) {
            wp_die('Error de seguridad: Nonce inválido.');
        }
        
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
    
        $apiClient = new APIClient(SINERGIA_API_URL, SINERGIA_VERBOSE);
        $sessionId = $apiClient->login($username, $password, SINERGIA_LANG, SINERGIA_NOTIFY_ON_SAVE);
    
        if ($sessionId) {
            $_SESSION['sinergia_session_id'] = $sessionId;
            $_SESSION['sinergia_user_name'] = $username;
            $_SESSION['sinergia_user_password'] = $password;
    
            if (SINERGIA_VERBOSE) {
                error_log("Sesión iniciada: " . print_r($_SESSION, true));
            }
    
            wp_redirect(admin_url('admin.php?page=sinergia-crm&tab=events'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=sinergia-crm&tab=login&login=failed'));
            exit;
        }
    }

    public function handle_logout() {
        if (!isset($_POST['sinergiacrm_nonce_field']) || !wp_verify_nonce($_POST['sinergiacrm_nonce_field'], 'sinergiacrm_nonce')) {
            wp_die('Error de seguridad: Nonce inválido.');
        }
        
        if (isset($_SESSION['sinergia_session_id'])) {
            $apiClient = new APIClient(SINERGIA_API_URL, SINERGIA_VERBOSE);
            $apiClient->sessionId = $_SESSION['sinergia_session_id'];
            $apiClient->logout();
            session_unset();
            session_destroy();
        }
        wp_redirect(admin_url('admin.php?page=sinergia-crm&tab=login'));
        exit;
    }

    public function get_esdeveniments_ajax() {
        if (!check_ajax_referer('sinergiacrm_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Error de seguretat: Nonce invàlid.']);
            return;
        }
    
        if (!session_id()) {
            session_start();
        }
    
        if (
            empty($_SESSION['sinergia_session_id']) ||
            empty($_SESSION['sinergia_user_name']) ||
            empty($_SESSION['sinergia_user_password'])
        ) {
            wp_send_json_error(['message' => 'Sessió no inicialitzada o dades incompletes.']);
            return;
        }
    
        $username = $_SESSION['sinergia_user_name'];
        $password = $_SESSION['sinergia_user_password'];
    
        $apiClient = new APIClient(SINERGIA_API_URL, SINERGIA_VERBOSE);
        $sessionId = $apiClient->login($username, $password, SINERGIA_LANG, SINERGIA_NOTIFY_ON_SAVE);
    
        if (!$sessionId) {
            wp_send_json_error(['message' => 'Error al iniciar sessió.']);
            return;
        }
    
        $params = [
            'module_name' => 'stic_Events',
            'query' => "stic_events.status LIKE '%active%' OR stic_events.status LIKE '%preparation%'",
            'order_by' => 'start_date DESC',
            'offset' => 0,
            'select_fields' => ['id', 'name', 'type', 'start_date', 'end_date', 'status', 'price', 'assigned_user_name', 'assigned_user_id'],
            'link_name_to_fields_array' => [],
            'max_results' => 100,
            'deleted' => 0,
        ];
    
        $event_list = $apiClient->getEntryList($params);
    
        $events_html = '<div class="wrap"><h2>Llistat d\'Esdeveniments</h2>';
    
        $events_data = [];
    
        if (!empty($event_list->entry_list)) {
            $events_html .= '<table class="wp-list-table widefat fixed striped" id="events-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-events" /></th>
                        <th>Nom</th>
                        <th>Tipus</th>
                        <th>Data inici</th>
                        <th>Data fi</th>
                        <th>Estat</th>
                        <th>Preu</th>
                        <th>Assignat a</th>
                    </tr>
                </thead>
                <tbody>';
    
            foreach ($event_list->entry_list as $event) {
                $id = isset($event->name_value_list->id->value) ? esc_attr($event->name_value_list->id->value) : '';
                $name = isset($event->name_value_list->name->value) ? esc_html($event->name_value_list->name->value) : 'N/A';
                $type = isset($event->name_value_list->type->value) ? esc_html($event->name_value_list->type->value) : 'N/A';
                $start_date = isset($event->name_value_list->start_date->value) ? esc_html($event->name_value_list->start_date->value) : 'N/A';
                $end_date = isset($event->name_value_list->end_date->value) ? esc_html($event->name_value_list->end_date->value) : 'N/A';
                $status = isset($event->name_value_list->status->value) ? esc_html($event->name_value_list->status->value) : 'N/A';
                $price = isset($event->name_value_list->price->value) ? esc_html($event->name_value_list->price->value) : 'N/A';
                $assigned_user = isset($event->name_value_list->assigned_user_name->value) ? esc_html($event->name_value_list->assigned_user_name->value) : 'N/A';
                $assigned_user_id = isset($event->name_value_list->assigned_user_id->value) ? esc_attr($event->name_value_list->assigned_user_id->value) : '';

                $events_html .= "<tr>
                    <td><input type='checkbox' class='event-checkbox' name='selected_events[]' value='$id' /></td>
                    <td>$name</td>
                    <td>$type</td>
                    <td>$start_date</td>
                    <td>$end_date</td>
                    <td>$status</td>
                    <td>$price</td>
                    <td>$assigned_user</td>
                </tr>";
    
                $events_data[] = [
                    'id' => $id,
                    'name' => $name,
                    'type' => $type,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'status' => $status,
                    'price' => $price,
                    'assigned_user' => $assigned_user,
                    'assigned_user_id' => $assigned_user_id
                ];
                $schedule_options .= "<option value=\"$event_id\" data-assigned-user=\"$assigned_user_id\">$option_label</option>";
            }
    
            $events_html .= '</tbody></table>';
        } else {
            $events_html .= '<p>No s\'han trobat esdeveniments actius o en preparació.</p>';
        }
    
        $events_html .= '</div>';
    
        wp_send_json_success([
            'html' => $events_html,
            'events_data' => $events_data,
            'schedule_options' => $schedule_options
        ]);
    }

    public function get_form_template() {
        try {
            if (!check_ajax_referer('sinergiacrm_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => 'Error de seguretat: Nonce invàlid.']);
                return;
            }
            
            $schedule_options = isset($_POST['schedule_options']) ? $_POST['schedule_options'] : '';
            $events_data_json = isset($_POST['events_data']) ? $_POST['events_data'] : '[]';
            
    
            $schedule_options = wp_kses($schedule_options, [
                'option' => [
                    'value' => true,
                    'selected' => false,
                    'label' => true,
                    'data-id' => true,
                    'data-assigned-user' => true,
                    'data-assigned-user-id' => true,
                ]
            ]);
            
            ob_start();
            include SFP_PATH . 'assets/html/form-template.php';
            $template = ob_get_clean();
            
            $form_html = str_replace(
                ['/*__SCHEDULE_OPTIONS__*/', '/*__EVENTS_DATA__*/'],
                [$schedule_options, $events_data_json],
                $template
            );
            
            wp_send_json_success(['html' => esc_html($form_html)]);
            
        } catch (Exception $e) {
            error_log('Error en get_form_template: ' . $e->getMessage());
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
        
        wp_die();
    } 
}
