<?php
/**
 * Classe principal do plugin Aurora Chat.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aurora_Chat_Plugin {

    /**
     * Instância singleton.
     *
     * @var Aurora_Chat_Plugin|null
     */
    protected static $instance = null;

    /**
     * Nome do CPT de agentes.
     */
    const CPT_AGENT = 'aurora_agent';

    /**
     * Nome do CPT de templates.
     */
    const CPT_TEMPLATE = 'aurora_template';

    /**
     * Nome do CPT de mensagens.
     */
    const CPT_MESSAGE = 'aurora_message';

    /**
     * Nome do shortcode.
     */
    const SHORTCODE = 'aurora_chat';

    /**
     * Meta keys.
     */
    const META_API_ENDPOINT = '_aurora_api_endpoint';
    const META_API_KEY      = '_aurora_api_key';
    const META_TEMPLATE_ID  = '_aurora_template_id';
    const META_MAX_TURNS    = '_aurora_max_turns';
    const META_SEND_FORM    = '_aurora_send_form';
    const META_SHORTCODE    = '_aurora_shortcode';
    const META_REMOTE_AGENT_ID = '_aurora_remote_agent_id';
    const META_REMOTE_WEBHOOK  = '_aurora_remote_webhook';
    /**
     * Limite máximo de caracteres por mensagem do usuário.
     */
    const META_MAX_INPUT_CHARS = '_aurora_max_input_chars';

    /**
     * Retorna instância única.
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Construtor.
     */
    protected function __construct() {
        add_action( 'init', [ $this, 'register_post_types' ] );
        add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'init', [ $this, 'register_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_agent_form' ] );
    add_action( 'admin_init', [ $this, 'handle_template_actions' ] );
    add_action( 'admin_init', [ $this, 'handle_messages_form' ] );

        add_action( 'save_post_' . self::CPT_AGENT, [ $this, 'persist_agent_meta' ], 10, 2 );
        add_filter( 'manage_' . self::CPT_AGENT . '_posts_columns', [ $this, 'agents_list_columns' ] );
        add_action( 'manage_' . self::CPT_AGENT . '_posts_custom_column', [ $this, 'render_agents_custom_column' ], 10, 2 );

        add_action( 'add_meta_boxes_' . self::CPT_AGENT, [ $this, 'register_agent_metabox' ] );
        add_action( 'add_meta_boxes_' . self::CPT_TEMPLATE, [ $this, 'register_template_metabox' ] );
        add_action( 'save_post_' . self::CPT_TEMPLATE, [ $this, 'persist_template_meta' ], 10, 2 );

        add_action( 'wp_ajax_aurora_chat_send_message', [ $this, 'handle_ajax_message' ] );
        add_action( 'wp_ajax_nopriv_aurora_chat_send_message', [ $this, 'handle_ajax_message' ] );
    add_action( 'wp_ajax_aurora_chat_send_audio', [ $this, 'handle_ajax_audio' ] );
    add_action( 'wp_ajax_nopriv_aurora_chat_send_audio', [ $this, 'handle_ajax_audio' ] );

        register_activation_hook( AURORA_CHAT_FILE, [ self::class, 'activate' ] );
    }

    /**
     * Registra CPTs.
     */
    public function register_post_types() {
        $this->register_agent_cpt();
        $this->register_template_cpt();
        $this->register_message_cpt();
    }

    /**
     * Registra o shortcode principal.
     */
    public function register_shortcodes() {
        add_shortcode( self::SHORTCODE, [ $this, 'render_chat_shortcode' ] );
    }

    /**
     * Faz o registro de scripts e estilos.
     */
    public function register_assets() {
        wp_register_style(
            'aurora-chat-admin',
            AURORA_CHAT_URL . 'assets/css/admin.css',
            [],
            AURORA_CHAT_VERSION
        );

        wp_register_style(
            'aurora-chat-frontend',
            AURORA_CHAT_URL . 'assets/css/frontend.css',
            [],
            AURORA_CHAT_VERSION
        );

        // Tema escuro opcional (carregado sob demanda)
        wp_register_style(
            'aurora-chat-dark',
            AURORA_CHAT_URL . 'assets/css/dark.css',
            [],
            AURORA_CHAT_VERSION
        );

        wp_register_script(
            'aurora-chat-admin',
            AURORA_CHAT_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            AURORA_CHAT_VERSION,
            true
        );

        wp_register_script(
            'aurora-chat-frontend',
            AURORA_CHAT_URL . 'assets/js/frontend.js',
            [],
            AURORA_CHAT_VERSION,
            true
        );
    }

    /**
     * Carrega assets no admin.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_aurora-chat' !== $hook && 'chat-aurora_page_aurora-chat-templates' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'aurora-chat-admin' );
        wp_enqueue_script( 'aurora-chat-admin' );
    }

    /**
     * Carrega assets no frontend.
     */
    public function enqueue_frontend_assets() {
        if ( ! is_singular() && ! is_front_page() ) {
            return;
        }

        if ( has_shortcode( get_post()->post_content ?? '', self::SHORTCODE ) ) {
            wp_enqueue_style( 'aurora-chat-frontend' );
            // Carrega tema escuro para permitir alternância no front
            wp_enqueue_style( 'aurora-chat-dark' );
            wp_enqueue_script( 'aurora-chat-frontend' );

            $site_name = get_bloginfo( 'name' );
            $site_icon_id = function_exists('get_site_icon_url') ? get_site_icon_url(64) : '';
            $site_initial = mb_strtoupper( mb_substr( $site_name, 0, 1 ) );
            $opts = get_option( 'aurora_chat_messages', [] );
            $opts = wp_parse_args( is_array( $opts ) ? $opts : [], [
                'error_default'    => __( 'Não foi possível obter resposta no momento. Tente novamente mais tarde.', 'aurora-chat' ),
                'limit_reached'    => __( 'O limite de interações foi atingido.', 'aurora-chat' ),
                'status_idle'      => __( 'Online', 'aurora-chat' ),
                'status_responding'=> __( 'Respondendo…', 'aurora-chat' ),
                'status_complete'  => __( 'Resposta em %ss', 'aurora-chat' ),
                'status_offline'   => __( 'Offline', 'aurora-chat' ),
                'agent_status'     => 'online',
                'welcome_title'    => __( 'Bem-vindo', 'aurora-chat' ),
                'welcome_subtitle' => __( 'Estamos aqui para ajudar!', 'aurora-chat' ),
                'welcome_bot'      => __( 'Olá! Sou o Aurora, seu copiloto digital. Como posso te ajudar hoje?', 'aurora-chat' ),
                'close_message'    => __( 'Atendimento encerrado com sucesso.', 'aurora-chat' ),
            ] );

            wp_localize_script(
                'aurora-chat-frontend',
                'AuroraChatConfig',
                [
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'aurora_chat_nonce' ),
                    // tempo máximo que o front deve aguardar antes de abortar (ms)
                    'remoteTimeoutMs' => (int) apply_filters( 'aurora_chat_remote_timeout_front_ms', apply_filters( 'aurora_chat_remote_timeout', 75, 'front' ) * 1000 ),
                    'i18n'    => [
                        'errorDefault' => $opts['error_default'],
                        'limitReached' => $opts['limit_reached'],
                        'statusIdle'   => $opts['status_idle'],
                        'statusResponding' => $opts['status_responding'],
                        'statusComplete'   => $opts['status_complete'],
                        'statusOffline'    => $opts['status_offline'],
                        'statusTranscribing' => __( 'Transcrevendo…', 'aurora-chat' ),
                        'welcomeTitle' => $opts['welcome_title'],
                        'welcomeSubtitle' => $opts['welcome_subtitle'],
                        'welcomeBot' => $opts['welcome_bot'],
                        'closeMessage' => $opts['close_message'],
                        'charsLimit' => __( 'Sua mensagem excede o limite de %d caracteres. Por favor, reduza o texto.', 'aurora-chat' ),
                    ],
                    'brand' => [
                        'name'    => $site_name,
                        'initial' => $site_initial,
                        'icon'    => $site_icon_id,
                    ],
                    'agentStatus' => in_array( $opts['agent_status'], [ 'online', 'offline' ], true ) ? $opts['agent_status'] : 'online',
                    // Sem configurações específicas de agente neste contexto
                ]
            );
        }
    }

    /**
     * Registra menu no admin.
     */
    public function register_admin_menu() {
        add_menu_page(
            __( 'Chat Aurora', 'aurora-chat' ),
            __( 'Chat Aurora', 'aurora-chat' ),
            'manage_options',
            'aurora-chat',
            [ $this, 'render_admin_page' ],
            'dashicons-format-chat',
            58
        );

        add_submenu_page(
            'aurora-chat',
            __( 'Agentes', 'aurora-chat' ),
            __( 'Agentes', 'aurora-chat' ),
            'manage_options',
            'edit.php?post_type=' . self::CPT_AGENT
        );

        add_submenu_page(
            'aurora-chat',
            __( 'Templates', 'aurora-chat' ),
            __( 'Templates', 'aurora-chat' ),
            'manage_options',
            'edit.php?post_type=' . self::CPT_TEMPLATE
        );
    }

    /**
     * Renderiza interface principal no admin.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'agents';
        $allowed    = [ 'agents', 'templates', 'messages' ];

        if ( ! in_array( $active_tab, $allowed, true ) ) {
            $active_tab = 'agents';
        }

        wp_enqueue_style( 'aurora-chat-admin' );
        wp_enqueue_script( 'aurora-chat-admin' );

        $this->render_admin_header( $active_tab );

        settings_errors( 'aurora_chat' );

        echo '<div class="aurora-chat-admin__content">';

        switch ( $active_tab ) {
            case 'templates':
                $this->render_templates_tab();
                break;
            case 'messages':
                $this->render_messages_tab();
                break;
            case 'agents':
            default:
                $this->render_agents_tab();
                break;
        }

        echo '</div>';
    }

    /**
     * Cabeçalho com abas.
     */
    protected function render_admin_header( $active_tab ) {
        $tabs = [
            'agents'   => __( 'Agentes', 'aurora-chat' ),
            'templates'=> __( 'Templates', 'aurora-chat' ),
            'messages' => __( 'Mensagens', 'aurora-chat' ),
        ];

        echo '<div class="aurora-chat-admin__tabs">';
        foreach ( $tabs as $slug => $label ) {
            $class = $active_tab === $slug ? ' is-active' : '';
            printf(
                '<a class="aurora-chat-admin__tab%s" href="%s">%s</a>',
                esc_attr( $class ),
                esc_url( add_query_arg( 'tab', $slug, menu_page_url( 'aurora-chat', false ) ) ),
                esc_html( $label )
            );
        }
        echo '</div>';
    }

    /**
     * Renderiza aba de agentes.
     */
    protected function render_agents_tab() {
        $agents = get_posts(
            [
                'post_type'      => self::CPT_AGENT,
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );

        $templates = get_posts(
            [
                'post_type'      => self::CPT_TEMPLATE,
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );

        $this->render_agent_form( $templates );

        echo '<h2 class="aurora-chat-admin__section-title">' . esc_html__( 'Agentes cadastrados', 'aurora-chat' ) . '</h2>';

        if ( empty( $agents ) ) {
            echo '<p>' . esc_html__( 'Nenhum agente configurado ainda. Cadastre o primeiro utilizando o formulário acima.', 'aurora-chat' ) . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Agente', 'aurora-chat' ) . '</th>';
        echo '<th>' . esc_html__( 'Template', 'aurora-chat' ) . '</th>';
    echo '<th>' . esc_html__( 'Webhook', 'aurora-chat' ) . '</th>';
        echo '<th>' . esc_html__( 'Limite', 'aurora-chat' ) . '</th>';
        echo '<th>' . esc_html__( 'Formulário', 'aurora-chat' ) . '</th>';
        echo '<th>' . esc_html__( 'Shortcode', 'aurora-chat' ) . '</th>';
        echo '<th>' . esc_html__( 'Ações', 'aurora-chat' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $agents as $agent ) {
            $template_id = (int) get_post_meta( $agent->ID, self::META_TEMPLATE_ID, true );
            $template    = $template_id ? get_post( $template_id ) : null;
            $endpoint    = '';
            $max_turns   = get_post_meta( $agent->ID, self::META_MAX_TURNS, true );
            $send_form   = get_post_meta( $agent->ID, self::META_SEND_FORM, true );
            $shortcode   = get_post_meta( $agent->ID, self::META_SHORTCODE, true );
            $wh          = get_post_meta( $agent->ID, self::META_REMOTE_WEBHOOK, true );

            echo '<tr>';
            printf( '<td><strong>%s</strong></td>', esc_html( get_the_title( $agent ) ) );
            printf( '<td>%s</td>', esc_html( $template ? $template->post_title : __( '—', 'aurora-chat' ) ) );
            printf( '<td><code>%s</code></td>', esc_html( $wh ?: '—' ) );
            printf( '<td>%s</td>', esc_html( $max_turns ?: __( 'Sem limite', 'aurora-chat' ) ) );
            printf( '<td>%s</td>', $send_form ? esc_html__( 'Sim', 'aurora-chat' ) : esc_html__( 'Não', 'aurora-chat' ) );
            $shortcode_display = $shortcode ?: sprintf( '[%s id="%d"]', self::SHORTCODE, $agent->ID );
            echo '<td>';
            echo '<code>' . esc_html( $shortcode_display ) . '</code> ';
            echo '<button type="button" class="button button-small" data-aurora-copy="' . esc_attr( $shortcode_display ) . '" data-aurora-label="' . esc_attr__( 'Copiar', 'aurora-chat' ) . '" data-aurora-label-success="' . esc_attr__( 'Copiado!', 'aurora-chat' ) . '">' . esc_html__( 'Copiar', 'aurora-chat' ) . '</button>';
            echo '</td>';
            printf(
                '<td><a class="button button-secondary" href="%s">%s</a></td>',
                esc_url( get_edit_post_link( $agent->ID ) ),
                esc_html__( 'Editar', 'aurora-chat' )
            );
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Formulário de criação rápida de agente.
     */
    protected function render_agent_form( array $templates ) {
        echo '<div class="aurora-chat-admin__card">';
    echo '<h2>' . esc_html__( 'Conectar agente existente (Sistema Aurora)', 'aurora-chat' ) . '</h2>';

        if ( empty( $templates ) ) {
            echo '<p>' . esc_html__( 'Nenhum template disponível. Crie ou restaure um template na aba Templates antes de cadastrar um agente.', 'aurora-chat' ) . '</p>';
            echo '<p><a class="button button-secondary" href="' . esc_url( add_query_arg( 'tab', 'templates', menu_page_url( 'aurora-chat', false ) ) ) . '">' . esc_html__( 'Ir para Templates', 'aurora-chat' ) . '</a></p>';
            echo '</div>';
            return;
        }

        echo '<form method="post">';
        wp_nonce_field( 'aurora_create_agent', '_aurora_nonce' );
        echo '<input type="hidden" name="aurora_action" value="create_agent" />';

        echo '<div class="aurora-chat-admin__field">';
        echo '<label for="aurora-agent-title">' . esc_html__( 'Nome do agente', 'aurora-chat' ) . '</label>';
        echo '<input type="text" id="aurora-agent-title" name="aurora_agent_title" class="regular-text" required />';
        echo '</div>';

    echo '<div class="aurora-chat-admin__field">';
    echo '<label for="aurora-agent-remote-webhook">' . esc_html__( 'URL do Webhook do agente (Sistema Aurora)', 'aurora-chat' ) . '</label>';
    echo '<input type="url" id="aurora-agent-remote-webhook" name="aurora_agent_remote_webhook" class="regular-text" placeholder="https://agente.com.br/api/agente/webhook/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required />';
    echo '<p class="description">' . esc_html__( 'Cole aqui a URL completa fornecida pelo Sistema Aurora.', 'aurora-chat' ) . '</p>';
    echo '</div>';

        echo '<div class="aurora-chat-admin__field">';
        echo '<label for="aurora-agent-template">' . esc_html__( 'Template visual', 'aurora-chat' ) . '</label>';
        echo '<select id="aurora-agent-template" name="aurora_agent_template" class="widefat">';
        foreach ( $templates as $template ) {
            printf(
                '<option value="%d">%s</option>',
                esc_attr( $template->ID ),
                esc_html( $template->post_title )
            );
        }
        echo '</select>';
        echo '</div>';

        echo '<div class="aurora-chat-admin__split">';
        echo '<div class="aurora-chat-admin__field">';
        echo '<label for="aurora-agent-max-turns">' . esc_html__( 'Limite de interações', 'aurora-chat' ) . '</label>';
        echo '<input type="number" id="aurora-agent-max-turns" name="aurora_agent_max_turns" min="0" step="1" placeholder="0 = sem limite" />';
        echo '</div>';

    echo '<div class="aurora-chat-admin__field">';
    echo '<label for="aurora-agent-max-chars">' . esc_html__( 'Limite de caracteres por mensagem', 'aurora-chat' ) . '</label>';
    echo '<input type="number" id="aurora-agent-max-chars" name="aurora_agent_max_chars" min="0" step="1" placeholder="0 = sem limite" />';
    echo '<p class="description">' . esc_html__( 'Quando definido, mensagens do usuário serão cortadas para esse tamanho antes do envio.', 'aurora-chat' ) . '</p>';
    echo '</div>';

        echo '<div class="aurora-chat-admin__field">';
        echo '<label for="aurora-agent-send-form">' . esc_html__( 'Enviar formulário de atendimento', 'aurora-chat' ) . '</label>';
        echo '<select id="aurora-agent-send-form" name="aurora_agent_send_form" class="widefat">';
        echo '<option value="0">' . esc_html__( 'Não', 'aurora-chat' ) . '</option>';
        echo '<option value="1">' . esc_html__( 'Sim', 'aurora-chat' ) . '</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';

        echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Criar agente', 'aurora-chat' ) . '</button></p>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Recebe submissão do formulário de novo agente.
     */
    public function handle_agent_form() {
        if ( ! isset( $_POST['aurora_action'], $_POST['_aurora_nonce'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aurora_nonce'] ) ), 'aurora_create_agent' ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['aurora_action'] ) );
        if ( 'create_agent' !== $action ) {
            return;
        }

    $title       = isset( $_POST['aurora_agent_title'] ) ? sanitize_text_field( wp_unslash( $_POST['aurora_agent_title'] ) ) : '';
    $endpoint    = ''; // não utilizado nesta simplificação
    $remote_id   = ''; // não utilizado nesta simplificação
    $remote_wh   = isset( $_POST['aurora_agent_remote_webhook'] ) ? esc_url_raw( wp_unslash( $_POST['aurora_agent_remote_webhook'] ) ) : '';
    $template_id = isset( $_POST['aurora_agent_template'] ) ? absint( $_POST['aurora_agent_template'] ) : 0;
    $max_turns   = isset( $_POST['aurora_agent_max_turns'] ) ? absint( $_POST['aurora_agent_max_turns'] ) : 0;
    $max_chars   = isset( $_POST['aurora_agent_max_chars'] ) ? absint( $_POST['aurora_agent_max_chars'] ) : 0;
    $send_form   = isset( $_POST['aurora_agent_send_form'] ) ? absint( $_POST['aurora_agent_send_form'] ) : 0;

        if ( empty( $title ) || empty( $remote_wh ) ) {
            add_settings_error( 'aurora_chat', 'aurora_agent_error', __( 'Preencha todos os campos obrigatórios.', 'aurora-chat' ), 'error' );
            return;
        }

        if ( $template_id && self::CPT_TEMPLATE !== get_post_type( $template_id ) ) {
            add_settings_error( 'aurora_chat', 'aurora_agent_error', __( 'Template selecionado é inválido.', 'aurora-chat' ), 'error' );
            return;
        }

        $post_id = wp_insert_post(
            [
                'post_type'   => self::CPT_AGENT,
                'post_title'  => $title,
                'post_status' => 'publish',
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            add_settings_error( 'aurora_chat', 'aurora_agent_error', $post_id->get_error_message(), 'error' );
            return;
        }

        delete_post_meta( $post_id, self::META_API_ENDPOINT );
        delete_post_meta( $post_id, self::META_REMOTE_AGENT_ID );
        update_post_meta( $post_id, self::META_REMOTE_WEBHOOK, $remote_wh );

        if ( $template_id ) {
            update_post_meta( $post_id, self::META_TEMPLATE_ID, $template_id );
        }

        update_post_meta( $post_id, self::META_MAX_TURNS, $max_turns );
    update_post_meta( $post_id, self::META_SEND_FORM, $send_form ? 1 : 0 );
    update_post_meta( $post_id, self::META_MAX_INPUT_CHARS, $max_chars );

        $shortcode = sprintf( '[%s id="%d"]', self::SHORTCODE, $post_id );
        update_post_meta( $post_id, self::META_SHORTCODE, $shortcode );

    add_settings_error( 'aurora_chat', 'aurora_agent_success', __( 'Agente conectado ao Sistema Aurora via Webhook.', 'aurora-chat' ), 'updated' );
    }

    /**
     * Rendereiza aba de templates.
     */
    protected function render_templates_tab() {
        $templates = get_posts(
            [
                'post_type'      => self::CPT_TEMPLATE,
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );

    echo '<div class="aurora-chat-admin__card">';
    echo '<h2>' . esc_html__( 'Templates disponíveis', 'aurora-chat' ) . '</h2>';
    echo '<p>' . esc_html__( 'Os templates definem como o chat será exibido para o usuário final. Aqui você pode pré-visualizar para ter uma ideia de como ficará no seu site.', 'aurora-chat' ) . '</p>';
    echo '</div>';

        if ( empty( $templates ) ) {
            echo '<p>' . esc_html__( 'Nenhum template encontrado. Ative novamente o plugin para recriar os padrões.', 'aurora-chat' ) . '</p>';
            return;
        }

        echo '<div class="aurora-chat-templates-grid">';
        foreach ( $templates as $template ) {
            $layout = get_post_meta( $template->ID, '_aurora_template_layout', true );

            echo '<div class="aurora-chat-template-card">';
            echo '<h3>' . esc_html( $template->post_title ) . '</h3>';
            echo '<p><strong>' . esc_html__( 'Layout', 'aurora-chat' ) . ':</strong> ' . esc_html( ucfirst( $layout ?: 'custom' ) ) . '</p>';
            echo '<p>' . esc_html( wp_trim_words( $template->post_content, 20 ) ) . '</p>';
            $preview_url = wp_nonce_url(
                add_query_arg(
                    [
                        'aurora_action' => 'preview_template',
                        'template_id'   => $template->ID,
                    ]
                ),
                'aurora_preview_template_' . $template->ID
            );
            echo '<div class="aurora-chat-template-card__actions">';
            echo '<a class="button" href="' . esc_url( $preview_url ) . '">' . esc_html__( 'Pré-visualizar', 'aurora-chat' ) . '</a>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        echo '<form method="post" class="aurora-chat-admin__restore">';
        wp_nonce_field( 'aurora_restore_templates', '_aurora_restore_nonce' );
        echo '<input type="hidden" name="aurora_action" value="restore_templates" />';
        echo '<button type="submit" class="button button-link-delete">' . esc_html__( 'Restaurar templates padrão', 'aurora-chat' ) . '</button>';
        echo '</form>';
    }

    /**
     * Lida com ações relacionadas a templates (pré-visualização e restauração).
     */
    public function handle_template_actions() {
        if ( isset( $_GET['aurora_action'] ) && 'preview_template' === sanitize_text_field( wp_unslash( $_GET['aurora_action'] ) ) ) {
            $template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0;
            $nonce       = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

            if ( $template_id && wp_verify_nonce( $nonce, 'aurora_preview_template_' . $template_id ) ) {
                $this->render_template_preview( $template_id );
            }
        }

        if ( ! isset( $_POST['aurora_action'] ) || 'restore_templates' !== sanitize_text_field( wp_unslash( $_POST['aurora_action'] ) ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! isset( $_POST['_aurora_restore_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aurora_restore_nonce'] ) ), 'aurora_restore_templates' ) ) {
            wp_die( __( 'Ação não autorizada.', 'aurora-chat' ) );
        }

        $this->create_default_templates( true );

        add_settings_error( 'aurora_chat', 'aurora_templates_restored', __( 'Templates padrão recriados com sucesso.', 'aurora-chat' ), 'updated' );
    }

    /**
     * Renderiza pré-visualização de template.
     */
    protected function render_template_preview( $template_id ) {
        $template = get_post( $template_id );
        if ( ! $template || self::CPT_TEMPLATE !== $template->post_type ) {
            wp_die( __( 'Template não encontrado.', 'aurora-chat' ) );
        }

        status_header( 200 );
        nocache_headers();

    wp_enqueue_style( 'aurora-chat-frontend' );
    wp_enqueue_style( 'aurora-chat-dark' );
        wp_enqueue_script( 'aurora-chat-frontend' );

        $site_name = get_bloginfo( 'name' );
        $site_icon_id = function_exists('get_site_icon_url') ? get_site_icon_url(64) : '';
        $site_initial = mb_strtoupper( mb_substr( $site_name, 0, 1 ) );
        $opts = get_option( 'aurora_chat_messages', [] );
        $opts = wp_parse_args( is_array( $opts ) ? $opts : [], [
            'error_default'    => __( 'Não foi possível obter resposta no momento. Tente novamente mais tarde.', 'aurora-chat' ),
            'limit_reached'    => __( 'O limite de interações foi atingido.', 'aurora-chat' ),
            'status_idle'      => __( 'Online', 'aurora-chat' ),
            'status_responding'=> __( 'Respondendo…', 'aurora-chat' ),
            'status_complete'  => __( 'Resposta em %ss', 'aurora-chat' ),
            'status_offline'   => __( 'Offline', 'aurora-chat' ),
            'agent_status'     => 'online',
            'welcome_title'    => __( 'Bem-vindo', 'aurora-chat' ),
            'welcome_subtitle' => __( 'Estamos aqui para ajudar!', 'aurora-chat' ),
            'welcome_bot'      => __( 'Olá! Sou o Aurora, seu copiloto digital. Como posso te ajudar hoje?', 'aurora-chat' ),
            'close_message'    => __( 'Atendimento encerrado com sucesso.', 'aurora-chat' ),
        ] );

        wp_localize_script(
            'aurora-chat-frontend',
            'AuroraChatConfig',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'aurora_chat_nonce' ),
                'remoteTimeoutMs' => (int) apply_filters( 'aurora_chat_remote_timeout_front_ms', apply_filters( 'aurora_chat_remote_timeout', 75, 'front-preview' ) * 1000 ),
                'i18n'    => [
                    'errorDefault' => $opts['error_default'],
                    'limitReached' => $opts['limit_reached'],
                    'statusIdle'   => $opts['status_idle'],
                    'statusResponding' => $opts['status_responding'],
                    'statusComplete'   => $opts['status_complete'],
                    'statusOffline'    => $opts['status_offline'],
                    'welcomeTitle' => $opts['welcome_title'],
                    'welcomeSubtitle' => $opts['welcome_subtitle'],
                    'welcomeBot' => $opts['welcome_bot'],
                    'closeMessage' => $opts['close_message'],
                    'charsLimit' => __( 'Sua mensagem excede o limite de %d caracteres. Por favor, reduza o texto.', 'aurora-chat' ),
                ],
                'brand' => [
                    'name'    => $site_name,
                    'initial' => $site_initial,
                    'icon'    => $site_icon_id,
                ],
                'agentStatus' => in_array( $opts['agent_status'], [ 'online', 'offline' ], true ) ? $opts['agent_status'] : 'online',
            ]
        );

    // Usar os arquivos de template para garantir que o CSS inline mais recente seja aplicado
    $layout  = get_post_meta( $template->ID, '_aurora_template_layout', true ) ?: 'session';
    if ( 'bubble' === $layout ) {
        $content = $this->get_bubble_template_markup();
    } else {
        $content = $this->get_session_template_markup();
    }

        echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head>';
        echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . esc_html( sprintf( __( 'Pré-visualização – %s', 'aurora-chat' ), $template->post_title ) ) . '</title>';
        wp_head();
        // Estilos para simular um site real
        echo '<style>
            :root{--site-bg:#f7f8fc;--text:#111827;--muted:#6b7280;--primary:#4f46e5}
            *{box-sizing:border-box}
            body{margin:0;font-family:Inter,Segoe UI,system-ui,Arial,sans-serif;background:var(--site-bg);color:var(--text)}
            header.site{position:sticky;top:0;background:#fff;border-bottom:1px solid #e5e7eb;z-index:10}
            header.site .container{max-width:1120px;margin:0 auto;display:flex;align-items:center;gap:12px;padding:14px 20px}
            header.site .brand{display:flex;align-items:center;gap:10px;font-weight:700}
            header.site .brand .logo{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,#111827,#374151)}
            header.site nav{margin-left:auto;display:flex;gap:16px}
            header.site nav a{color:var(--muted);text-decoration:none}
            main{max-width:1120px;margin:0 auto;padding:28px 20px}
            .hero{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:26px;display:grid;grid-template-columns:1.2fr .8fr;gap:18px}
            .hero h1{margin:0 0 10px;font-size:22px}
            .hero p{margin:0;color:var(--muted)}
            .content-grid{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-top:20px}
            .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px}
            .placeholder{height:140px;border-radius:10px;background:repeating-linear-gradient(45deg,#f3f4f6,#f3f4f6 10px,#eef2ff 10px,#eef2ff 20px)}
            /* posicionamento do chat conforme layout */
            .aurora-area{position:relative}
            .aurora-area.bubble .card{position:relative;min-height:300px}
            .aurora-area.bubble .aurora-chat-container{position:fixed;bottom:18px;right:18px}
        </style>';
    echo '</head><body>';
        echo '<header class="site"><div class="container">';
        echo '<div class="brand"><div class="logo"></div><span>' . esc_html( get_bloginfo( 'name' ) ) . '</span></div>';
    echo '<nav><a href="#">' . esc_html__( 'Início', 'aurora-chat' ) . '</a><a href="#">' . esc_html__( 'Produtos', 'aurora-chat' ) . '</a><a href="#">' . esc_html__( 'Contato', 'aurora-chat' ) . '</a>';
    echo '<button id="aurora-theme-toggle" class="button" style="margin-left:12px">' . esc_html__( 'Tema: Claro/Escuro', 'aurora-chat' ) . '</button>';
    echo '</nav>';
        echo '</div></header>';

        echo '<main class="aurora-chat-preview">';
        echo '<section class="hero card"><div><h1>' . esc_html__( 'Veja como o chat ficará no seu site', 'aurora-chat' ) . '</h1><p>' . esc_html__( 'Esta é uma simulação visual. O estilo final pode herdar fontes e cores do seu tema.', 'aurora-chat' ) . '</p></div><div class="placeholder"></div></section>';
        echo '<section class="content-grid">';
        echo '<div class="card"><h2>' . esc_html__( 'Conteúdo de exemplo', 'aurora-chat' ) . '</h2><p>' . esc_html__( 'Texto ilustrativo para simular uma página real com blocos de conteúdo.', 'aurora-chat' ) . '</p><div class="placeholder" style="height:220px"></div></div>';
        echo '<div class="aurora-area ' . esc_attr( $layout === 'bubble' ? 'bubble' : 'session' ) . '">';
        echo '<div class="card"><h3>' . esc_html__( 'Área do Chat', 'aurora-chat' ) . '</h3>';
    echo sprintf( '<div class="aurora-chat-container aurora-chat-layout-%s" data-agent="0" data-max-turns="0" data-send-form="0" data-max-chars="0">%s</div>', esc_attr( $layout ), $content );
        echo '</div></div>';
        echo '</section>';
        echo '</main>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){var t=document.getElementById("aurora-theme-toggle");if(!t)return;t.addEventListener("click",function(){document.body.classList.toggle("aurora-theme-dark");});});</script>';
    wp_footer();
        echo '</body></html>';
        exit;
    }

    /**
     * Renderiza aba de mensagens.
     */
    protected function render_messages_tab() {
        $opts = get_option( 'aurora_chat_messages', [] );
        $defaults = [
            'welcome_title'    => __( 'Bem-vindo', 'aurora-chat' ),
            'welcome_subtitle' => __( 'Estamos aqui para ajudar!', 'aurora-chat' ),
            'welcome_bot'      => __( 'Olá! Sou o Aurora, seu copiloto digital. Como posso te ajudar hoje?', 'aurora-chat' ),
            'error_default'    => __( 'Não foi possível obter resposta no momento. Tente novamente mais tarde.', 'aurora-chat' ),
            'limit_reached'    => __( 'O limite de interações foi atingido.', 'aurora-chat' ),
            'status_idle'      => __( 'Online', 'aurora-chat' ),
            'status_responding'=> __( 'Respondendo…', 'aurora-chat' ),
            'status_complete'  => __( 'Resposta em %ss', 'aurora-chat' ),
            'status_offline'   => __( 'Offline', 'aurora-chat' ),
            'agent_status'     => 'online',
            'close_message'    => __( 'Atendimento encerrado com sucesso.', 'aurora-chat' ),
        ];
        $opts = wp_parse_args( is_array( $opts ) ? $opts : [], $defaults );

        echo '<div class="aurora-chat-admin__card">';
        echo '<h2>' . esc_html__( 'Mensagens padrão', 'aurora-chat' ) . '</h2>';
        echo '<p>' . esc_html__( 'Configure os textos padrão exibidos no chat (boas-vindas, erros, encerramento e status).', 'aurora-chat' ) . '</p>';
        echo '<form method="post">';
        wp_nonce_field( 'aurora_save_messages', '_aurora_messages_nonce' );
        echo '<input type="hidden" name="aurora_action" value="save_messages" />';

        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="welcome_title">' . esc_html__( 'Título de boas-vindas (bolha)', 'aurora-chat' ) . '</label></th>';
        echo '<td><input type="text" class="regular-text" id="welcome_title" name="welcome_title" value="' . esc_attr( $opts['welcome_title'] ) . '" /></td></tr>';

        echo '<tr><th><label for="welcome_subtitle">' . esc_html__( 'Subtítulo de boas-vindas (bolha)', 'aurora-chat' ) . '</label></th>';
        echo '<td><input type="text" class="regular-text" id="welcome_subtitle" name="welcome_subtitle" value="' . esc_attr( $opts['welcome_subtitle'] ) . '" /></td></tr>';

        echo '<tr><th><label for="welcome_bot">' . esc_html__( 'Mensagem inicial do bot (sessão)', 'aurora-chat' ) . '</label></th>';
        echo '<td><textarea class="large-text" id="welcome_bot" name="welcome_bot" rows="3">' . esc_textarea( $opts['welcome_bot'] ) . '</textarea></td></tr>';

        echo '<tr><th><label for="error_default">' . esc_html__( 'Mensagem de erro padrão', 'aurora-chat' ) . '</label></th>';
        echo '<td><input type="text" class="regular-text" id="error_default" name="error_default" value="' . esc_attr( $opts['error_default'] ) . '" /></td></tr>';

        echo '<tr><th><label for="limit_reached">' . esc_html__( 'Mensagem de limite atingido', 'aurora-chat' ) . '</label></th>';
        echo '<td><input type="text" class="regular-text" id="limit_reached" name="limit_reached" value="' . esc_attr( $opts['limit_reached'] ) . '" /></td></tr>';

    echo '<tr><th>' . esc_html__( 'Status do agente', 'aurora-chat' ) . '</th>';
    echo '<td>';
    echo '<label for="agent_status">' . esc_html__( 'Disponibilidade atual', 'aurora-chat' ) . '</label><br/>';
    echo '<select id="agent_status" name="agent_status">';
    echo '<option value="online" ' . selected( $opts['agent_status'], 'online', false ) . '>' . esc_html__( 'Online (verde)', 'aurora-chat' ) . '</option>';
    echo '<option value="offline" ' . selected( $opts['agent_status'], 'offline', false ) . '>' . esc_html__( 'Offline (vermelho)', 'aurora-chat' ) . '</option>';
    echo '</select>';
    echo '<p class="description">' . esc_html__( 'Define a cor do status exibido no chat.', 'aurora-chat' ) . '</p>';
    echo '<hr/>';
    echo '<label>' . esc_html__( 'Textos de status', 'aurora-chat' ) . '</label><br/>';
    echo '<strong>' . esc_html__( 'Disponível', 'aurora-chat' ) . '</strong><br/>';
    echo '<input type="text" class="regular-text" name="status_idle" value="' . esc_attr( $opts['status_idle'] ) . '" />';
    echo '<p class="description">' . esc_html__( 'Ex.: "Online"', 'aurora-chat' ) . '</p><br/>';
    echo '<strong>' . esc_html__( 'Offline', 'aurora-chat' ) . '</strong><br/>';
    echo '<input type="text" class="regular-text" name="status_offline" value="' . esc_attr( $opts['status_offline'] ) . '" />';
    echo '<p class="description">' . esc_html__( 'Ex.: "Offline"', 'aurora-chat' ) . '</p><br/>';
    echo '<strong>' . esc_html__( 'Respondendo', 'aurora-chat' ) . '</strong><br/>';
    echo '<input type="text" class="regular-text" name="status_responding" value="' . esc_attr( $opts['status_responding'] ) . '" />';
    echo '<p class="description">' . esc_html__( 'Ex.: "Respondendo…"', 'aurora-chat' ) . '</p><br/>';
    echo '<strong>' . esc_html__( 'Concluído', 'aurora-chat' ) . '</strong><br/>';
    echo '<input type="text" class="regular-text" name="status_complete" value="' . esc_attr( $opts['status_complete'] ) . '" />';
    echo '<p class="description">' . esc_html__( 'Use %s para o tempo (segundos), ex.: "Resposta em %ss"', 'aurora-chat' ) . '</p>';
    echo '</td></tr>';

        echo '<tr><th><label for="close_message">' . esc_html__( 'Mensagem de encerramento', 'aurora-chat' ) . '</label></th>';
        echo '<td><input type="text" class="regular-text" id="close_message" name="close_message" value="' . esc_attr( $opts['close_message'] ) . '" /></td></tr>';

        echo '</tbody></table>';
        echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Salvar mensagens', 'aurora-chat' ) . '</button></p>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Salva metadados dos agentes quando editados manualmente.
     */
    public function persist_agent_meta( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( ! isset( $_POST['aurora_agent_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aurora_agent_meta_nonce'] ) ), 'aurora_agent_meta' ) ) {
            return;
        }

        $fields = [
            self::META_TEMPLATE_ID,
            self::META_MAX_TURNS,
            self::META_SEND_FORM,
            self::META_REMOTE_WEBHOOK,
            self::META_MAX_INPUT_CHARS,
        ];

        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
                if ( self::META_TEMPLATE_ID === $field ) {
                    $value = absint( $value );
                    if ( $value && self::CPT_TEMPLATE !== get_post_type( $value ) ) {
                        continue;
                    }
                }

                if ( in_array( $field, [ self::META_MAX_TURNS, self::META_MAX_INPUT_CHARS ], true ) ) {
                    $value = absint( $value );
                }
                update_post_meta( $post_id, $field, $value );
            }
        }

        // Removido: armazenamento de chave de API (não utilizada neste fluxo)

        if ( ! get_post_meta( $post_id, self::META_SHORTCODE, true ) ) {
            update_post_meta( $post_id, self::META_SHORTCODE, sprintf( '[%s id="%d"]', self::SHORTCODE, $post_id ) );
        }
    }

    /**
     * Define colunas da listagem de agentes.
     */
    public function agents_list_columns( $columns ) {
        $columns['template']   = __( 'Template', 'aurora-chat' );
        $columns['shortcode']  = __( 'Shortcode', 'aurora-chat' );
        $columns['max_turns']  = __( 'Limite', 'aurora-chat' );
        $columns['send_form']  = __( 'Formulário', 'aurora-chat' );
        return $columns;
    }

    /**
     * Renderiza colunas customizadas.
     */
    public function render_agents_custom_column( $column, $post_id ) {
        switch ( $column ) {
            case 'template':
                $template_id = (int) get_post_meta( $post_id, self::META_TEMPLATE_ID, true );
                echo esc_html( $template_id ? get_the_title( $template_id ) : '—' );
                break;
            case 'shortcode':
                echo '<code>' . esc_html( get_post_meta( $post_id, self::META_SHORTCODE, true ) ) . '</code>';
                break;
            case 'max_turns':
                $max_turns = (int) get_post_meta( $post_id, self::META_MAX_TURNS, true );
                echo $max_turns ? esc_html( $max_turns ) : esc_html__( 'Sem limite', 'aurora-chat' );
                break;
            case 'send_form':
                $send_form = (int) get_post_meta( $post_id, self::META_SEND_FORM, true );
                echo $send_form ? esc_html__( 'Sim', 'aurora-chat' ) : esc_html__( 'Não', 'aurora-chat' );
                break;
        }
    }

    /**
     * Metabox de agentes.
     */
    public function register_agent_metabox() {
        add_meta_box(
            'aurora-agent-settings',
            __( 'Configurações do agente', 'aurora-chat' ),
            [ $this, 'render_agent_metabox' ],
            self::CPT_AGENT,
            'normal',
            'high'
        );
    }

    /**
     * Renderiza metabox de agentes.
     */
    public function render_agent_metabox( $post ) {
        wp_nonce_field( 'aurora_agent_meta', 'aurora_agent_meta_nonce' );

    $remote_webhook = get_post_meta( $post->ID, self::META_REMOTE_WEBHOOK, true );
    $template   = (int) get_post_meta( $post->ID, self::META_TEMPLATE_ID, true );
    $max_turns  = (int) get_post_meta( $post->ID, self::META_MAX_TURNS, true );
    $send_form  = (int) get_post_meta( $post->ID, self::META_SEND_FORM, true );
    $max_chars  = (int) get_post_meta( $post->ID, self::META_MAX_INPUT_CHARS, true );

        $templates = get_posts(
            [
                'post_type'      => self::CPT_TEMPLATE,
                'posts_per_page' => -1,
            ]
        );

        echo '<table class="form-table"><tbody>';

    echo '<tr><th><label for="' . esc_attr( self::META_REMOTE_WEBHOOK ) . '">' . esc_html__( 'URL do Webhook do agente (Sistema Aurora)', 'aurora-chat' ) . '</label></th>';
    echo '<td><input type="url" name="' . esc_attr( self::META_REMOTE_WEBHOOK ) . '" id="' . esc_attr( self::META_REMOTE_WEBHOOK ) . '" value="' . esc_attr( $remote_webhook ) . '" class="regular-text" placeholder="https://agente.com.br/api/agente/webhook/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">';
    echo '<p class="description">' . esc_html__( 'Cole a URL completa fornecida pelo Sistema Aurora.', 'aurora-chat' ) . '</p>';
    echo '</td></tr>';

        echo '<tr><th><label for="' . esc_attr( self::META_TEMPLATE_ID ) . '">' . esc_html__( 'Template visual', 'aurora-chat' ) . '</label></th>';
        echo '<td><select name="' . esc_attr( self::META_TEMPLATE_ID ) . '" id="' . esc_attr( self::META_TEMPLATE_ID ) . '">';
        foreach ( $templates as $tpl ) {
            printf(
                '<option value="%d" %s>%s</option>',
                esc_attr( $tpl->ID ),
                selected( $template, $tpl->ID, false ),
                esc_html( $tpl->post_title )
            );
        }
        echo '</select></td></tr>';

        echo '<tr><th><label for="' . esc_attr( self::META_MAX_TURNS ) . '">' . esc_html__( 'Limite de interações', 'aurora-chat' ) . '</label></th>';
        echo '<td><input type="number" name="' . esc_attr( self::META_MAX_TURNS ) . '" id="' . esc_attr( self::META_MAX_TURNS ) . '" value="' . esc_attr( $max_turns ) . '" class="small-text">';
        echo '<p class="description">' . esc_html__( '0 ou vazio = sem limite.', 'aurora-chat' ) . '</p></td></tr>';

    echo '<tr><th><label for="' . esc_attr( self::META_MAX_INPUT_CHARS ) . '">' . esc_html__( 'Limite de caracteres por mensagem', 'aurora-chat' ) . '</label></th>';
    echo '<td><input type="number" name="' . esc_attr( self::META_MAX_INPUT_CHARS ) . '" id="' . esc_attr( self::META_MAX_INPUT_CHARS ) . '" value="' . esc_attr( $max_chars ) . '" class="small-text">';
    echo '<p class="description">' . esc_html__( '0 ou vazio = sem limite. Mensagens do usuário serão cortadas para esse tamanho antes do envio.', 'aurora-chat' ) . '</p></td></tr>';

        echo '<tr><th><label for="' . esc_attr( self::META_SEND_FORM ) . '">' . esc_html__( 'Enviar formulário de atendimento', 'aurora-chat' ) . '</label></th>';
        echo '<td><select name="' . esc_attr( self::META_SEND_FORM ) . '" id="' . esc_attr( self::META_SEND_FORM ) . '">';
        echo '<option value="0" ' . selected( $send_form, 0, false ) . '>' . esc_html__( 'Não', 'aurora-chat' ) . '</option>';
        echo '<option value="1" ' . selected( $send_form, 1, false ) . '>' . esc_html__( 'Sim', 'aurora-chat' ) . '</option>';
        echo '</select></td></tr>';

        echo '</tbody></table>';

        $shortcode = get_post_meta( $post->ID, self::META_SHORTCODE, true );
        if ( ! $shortcode ) {
            $shortcode = sprintf( '[%s id="%d"]', self::SHORTCODE, $post->ID );
        }
        echo '<p><strong>' . esc_html__( 'Shortcode', 'aurora-chat' ) . ':</strong> <code>' . esc_html( $shortcode ) . '</code></p>';
    }

    /**
     * Registra metabox de templates.
     */
    public function register_template_metabox() {
        add_meta_box(
            'aurora-template-info',
            __( 'Informações do template', 'aurora-chat' ),
            [ $this, 'render_template_metabox' ],
            self::CPT_TEMPLATE,
            'side',
            'default'
        );
    }

    /**
     * Renderiza metabox de templates.
     */
    public function render_template_metabox( $post ) {
        $layout = get_post_meta( $post->ID, '_aurora_template_layout', true );
        echo '<p>' . esc_html__( 'Identificador do layout.', 'aurora-chat' ) . '</p>';
        echo '<p><code>' . esc_html( $layout ?: 'custom' ) . '</code></p>';
    }

    /**
     * Persistência de meta dos templates (placeholder caso usemos no futuro).
     */
    public function persist_template_meta( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // No momento não há campos extras para salvar.
    }

    /**
     * Lida com envio de mensagens via AJAX.
     */
    public function handle_ajax_message() {
        check_ajax_referer( 'aurora_chat_nonce', 'nonce' );

        $agent_id = isset( $_POST['agentId'] ) ? absint( $_POST['agentId'] ) : 0;
        $message  = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $session  = isset( $_POST['session'] ) ? sanitize_key( wp_unslash( $_POST['session'] ) ) : wp_generate_uuid4();

        if ( ! $agent_id || empty( $message ) ) {
            wp_send_json_error( [ 'message' => __( 'Requisição inválida.', 'aurora-chat' ) ] );
        }

    $remote_webhook = get_post_meta( $agent_id, self::META_REMOTE_WEBHOOK, true );

        // Enforce character limit per message for this agent
        $max_chars = (int) get_post_meta( $agent_id, self::META_MAX_INPUT_CHARS, true );
        if ( $max_chars > 0 ) {
            $message = $this->truncate_text_limit( $message, $max_chars );
        }

    $response_text = '';
    $attachments = [];
    $audio_payload = [];

        // Permitir execuções mais longas (agentes podem levar ~60s)
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 90 );
        }
        @ini_set( 'max_execution_time', '90' );

        // Usar apenas a URL completa do Webhook do Sistema Aurora
        if ( $remote_webhook ) {
            $url = $remote_webhook;
            // Origin header precisa ser scheme://host[:port]
            $home = home_url();
            $scheme = wp_parse_url( $home, PHP_URL_SCHEME ) ?: 'https';
            $host = wp_parse_url( $home, PHP_URL_HOST ) ?: 'localhost';
            $port = wp_parse_url( $home, PHP_URL_PORT );
            $origin = $scheme . '://' . $host . ( $port ? ':' . $port : '' );
            $user_name = isset($_POST['userName']) ? sanitize_text_field( wp_unslash( $_POST['userName'] ) ) : '';
            $user_email = isset($_POST['userEmail']) ? sanitize_email( wp_unslash( $_POST['userEmail'] ) ) : '';
            $user_contact = isset($_POST['userContact']) ? sanitize_text_field( wp_unslash( $_POST['userContact'] ) ) : '';
            $payload = [
                'protocolo' => $session,
                'texto'     => $message,
                'origem'    => $origin,
            ];
            if ( $user_name )   { $payload['nome_usuario'] = $user_name; }
            if ( $user_email )  { $payload['email'] = $user_email; }
            if ( $user_contact ){ $payload['contato'] = $user_contact; }
            // Timeout customizável via filtro; padrão 75s
            $timeout = apply_filters( 'aurora_chat_remote_timeout', 75, 'message', $agent_id );
            $args = [
                'headers' => [ 'Content-Type' => 'application/json', 'Origin' => $origin ],
                'body'    => wp_json_encode( $payload ),
                'timeout' => max( 10, (int) $timeout ),
            ];
            $response = wp_remote_post( $url, $args );
            if ( is_wp_error( $response ) ) {
                error_log( '[Aurora Chat] Erro ao contatar webhook: ' . $response->get_error_message() );
            } else {
                $code = wp_remote_retrieve_response_code( $response );
                $data = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( 200 === $code && is_array( $data ) ) {
                    if ( isset( $data['mensagem'] ) ) {
                        $response_text = wp_kses_post( $data['mensagem'] );
                    }
                    if ( ! empty( $data['anexos'] ) && is_array( $data['anexos'] ) ) {
                        $attachments = array_values( array_filter( array_map( 'esc_url_raw', $data['anexos'] ) ) );
                    }
                    // Suporte a áudio retornado pelo agente: URL direta ou base64 + content-type
                    if ( ! empty( $data['audio_url'] ) && is_string( $data['audio_url'] ) ) {
                        $audio_payload = [
                            'src'  => esc_url_raw( $data['audio_url'] ),
                            'type' => isset( $data['audio_content_type'] ) ? sanitize_text_field( $data['audio_content_type'] ) : '',
                            'kind' => 'url',
                        ];
                    } elseif ( ! empty( $data['audio'] ) && is_string( $data['audio'] ) ) {
                        $ctype = isset( $data['audio_content_type'] ) ? sanitize_text_field( $data['audio_content_type'] ) : 'audio/mpeg';
                        // Não concatenamos o data URL aqui; deixamos para o front montar para reduzir payload duplicado
                        $audio_payload = [
                            'base64' => $data['audio'],
                            'type'   => $ctype,
                            'kind'   => 'base64',
                        ];
                    }
                } else {
                    // Tenta expor mensagem de erro da API para facilitar o diagnóstico no chat
                    if ( is_array( $data ) && ! empty( $data['error'] ) ) {
                        $response_text = wp_kses_post( (string) $data['error'] );
                    }
                    error_log( '[Aurora Chat] Webhook HTTP ' . $code . ' body: ' . wp_remote_retrieve_body( $response ) );
                }
            }
        } else {
            error_log( '[Aurora Chat] Webhook não configurado para o agente ' . $agent_id );
        }

        // Se não há texto, só mostramos mensagem de erro quando também não houver áudio nem anexos
        if ( empty( $response_text ) ) {
            if ( ! empty( $audio_payload ) || ! empty( $attachments ) ) {
                $response_text = '';
            } else {
                $opts = get_option( 'aurora_chat_messages', [] );
                $fallback = is_array( $opts ) && ! empty( $opts['error_default'] ) ? $opts['error_default'] : __( 'Não foi possível obter resposta no momento. Tente novamente mais tarde.', 'aurora-chat' );
                $response_text = $fallback;
            }
        }

    $this->store_message( $agent_id, $session, $message, $response_text );

    wp_send_json_success( [ 'reply' => $response_text, 'session' => $session, 'attachments' => $attachments, 'audio' => $audio_payload ] );
    }

    /**
     * Lida com envio de áudio (base64) para o webhook remoto para transcrição.
     * Retorna apenas a transcrição e não cria histórico local.
     */
    public function handle_ajax_audio() {
        check_ajax_referer( 'aurora_chat_nonce', 'nonce' );

        $agent_id = isset( $_POST['agentId'] ) ? absint( $_POST['agentId'] ) : 0;
        $audio_b64 = isset( $_POST['audio'] ) ? wp_unslash( $_POST['audio'] ) : '';
        $session  = isset( $_POST['session'] ) ? sanitize_key( wp_unslash( $_POST['session'] ) ) : wp_generate_uuid4();
        $user_name = isset($_POST['userName']) ? sanitize_text_field( wp_unslash( $_POST['userName'] ) ) : '';
        $user_email = isset($_POST['userEmail']) ? sanitize_email( wp_unslash( $_POST['userEmail'] ) ) : '';
        $user_contact = isset($_POST['userContact']) ? sanitize_text_field( wp_unslash( $_POST['userContact'] ) ) : '';

        if ( ! $agent_id || empty( $audio_b64 ) ) {
            wp_send_json_error( [ 'message' => __( 'Requisição inválida (áudio ausente).', 'aurora-chat' ) ] );
        }

        $remote_webhook = get_post_meta( $agent_id, self::META_REMOTE_WEBHOOK, true );
        if ( ! $remote_webhook ) {
            wp_send_json_error( [ 'message' => __( 'Webhook remoto não configurado para este agente.', 'aurora-chat' ) ] );
        }

        // Origin header precisa ser scheme://host[:port]
        $home = home_url();
        $scheme = wp_parse_url( $home, PHP_URL_SCHEME ) ?: 'https';
        $host = wp_parse_url( $home, PHP_URL_HOST ) ?: 'localhost';
        $port = wp_parse_url( $home, PHP_URL_PORT );
        $origin = $scheme . '://' . $host . ( $port ? ':' . $port : '' );

        $payload = [
            'protocolo' => $session,
            'audio'     => $audio_b64,
            'origem'    => $origin,
        ];
        if ( $user_name )   { $payload['nome_usuario'] = $user_name; }
        if ( $user_email )  { $payload['email'] = $user_email; }
        if ( $user_contact ){ $payload['contato'] = $user_contact; }

        // Permitir execuções mais longas também na transcrição
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 90 );
        }
        @ini_set( 'max_execution_time', '90' );

        // Timeout customizável via filtro; padrão 75s
        $timeout = apply_filters( 'aurora_chat_remote_timeout', 75, 'audio', $agent_id );
        $args = [
            'headers' => [ 'Content-Type' => 'application/json', 'Origin' => $origin ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => max( 10, (int) $timeout ),
        ];

        $response = wp_remote_post( $remote_webhook, $args );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || ! is_array( $data ) ) {
            $msg = is_array($data) && isset($data['error']) ? (string) $data['error'] : __( 'Falha na transcrição de áudio.', 'aurora-chat' );
            wp_send_json_error( [ 'message' => $msg ] );
        }

        $transcript = isset( $data['audio_transcrito'] ) ? (string) $data['audio_transcrito'] : '';
        wp_send_json_success( [ 'transcript' => $transcript, 'session' => $session ] );
    }

    /**
     * Salva mensagens padrão (aba Mensagens)
     */
    public function handle_messages_form() {
        if ( ! isset( $_POST['aurora_action'] ) || 'save_messages' !== sanitize_text_field( wp_unslash( $_POST['aurora_action'] ) ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! isset( $_POST['_aurora_messages_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aurora_messages_nonce'] ) ), 'aurora_save_messages' ) ) {
            wp_die( __( 'Ação não autorizada.', 'aurora-chat' ) );
        }

        $fields = [ 'welcome_title', 'welcome_subtitle', 'welcome_bot', 'error_default', 'limit_reached', 'status_idle', 'status_offline', 'status_responding', 'status_complete', 'agent_status', 'close_message' ];
        $opts = [];
        foreach ( $fields as $f ) {
            if ( isset( $_POST[ $f ] ) ) {
                $val = is_string( $_POST[ $f ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $f ] ) ) : '';
                $opts[ $f ] = $val;
            }
        }
        update_option( 'aurora_chat_messages', $opts );
        add_settings_error( 'aurora_chat', 'aurora_messages_saved', __( 'Mensagens salvas com sucesso.', 'aurora-chat' ), 'updated' );
    }

    /**
     * Salva mensagem em CPT.
     */
    protected function store_message( $agent_id, $session_id, $user_message, $bot_message ) {
        $post_id = wp_insert_post(
            [
                'post_type'   => self::CPT_MESSAGE,
                'post_status' => 'publish',
                'post_title'  => wp_trim_words( $user_message, 6 ) . ' – ' . current_time( 'mysql' ),
            ]
        );

        if ( is_wp_error( $post_id ) ) {
            return;
        }

        update_post_meta( $post_id, '_aurora_agent_id', $agent_id );
        update_post_meta( $post_id, '_aurora_session_id', $session_id );
        update_post_meta( $post_id, '_aurora_user_message', $user_message );
        update_post_meta( $post_id, '_aurora_bot_message', $bot_message );
    }

    /**
     * Renderiza shortcode.
     */
    public function render_chat_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'id'     => 0,
                'agent'  => '',
            ],
            $atts,
            self::SHORTCODE
        );

        $agent_id = intval( $atts['id'] );
        if ( ! $agent_id && ! empty( $atts['agent'] ) ) {
            $agent = get_page_by_path( sanitize_title( $atts['agent'] ), OBJECT, self::CPT_AGENT );
            if ( $agent ) {
                $agent_id = $agent->ID;
            }
        }

        if ( ! $agent_id ) {
            return '<div class="aurora-chat-error">' . esc_html__( 'Agente não encontrado.', 'aurora-chat' ) . '</div>';
        }

        $template_id = (int) get_post_meta( $agent_id, self::META_TEMPLATE_ID, true );
        $template    = $template_id ? get_post( $template_id ) : null;

    $max_turns = (int) get_post_meta( $agent_id, self::META_MAX_TURNS, true );
    $send_form = (int) get_post_meta( $agent_id, self::META_SEND_FORM, true );
    $max_chars = (int) get_post_meta( $agent_id, self::META_MAX_INPUT_CHARS, true );

        $layout = $template ? get_post_meta( $template_id, '_aurora_template_layout', true ) : 'session';

        $agent_name = get_the_title( $agent_id );
        $data_attrs = sprintf(
            'data-agent="%d" data-max-turns="%d" data-send-form="%d" data-max-chars="%d" data-agent-name="%s"',
            esc_attr( $agent_id ),
            esc_attr( $max_turns ),
            esc_attr( $send_form ),
            esc_attr( $max_chars ),
            esc_attr( $agent_name )
        );

        // Renderiza a partir dos arquivos de template para garantir estilos inline atualizados
        // Mantém fallback para conteúdo do CPT apenas se for um layout personalizado
        if ( 'session' === $layout ) {
            $content = $this->get_session_template_markup();
        } elseif ( 'bubble' === $layout ) {
            $content = $this->get_bubble_template_markup();
        } else {
            $content = $template ? apply_filters( 'the_content', $template->post_content ) : $this->get_session_template_markup();
        }

        return sprintf(
            '<div class="aurora-chat-container aurora-chat-layout-%s" %s>%s</div>',
            esc_attr( $layout ?: 'session' ),
            $data_attrs,
            $content
        );
    }

    /**
     * Cria CPT de agentes.
     */
    protected function register_agent_cpt() {
        register_post_type(
            self::CPT_AGENT,
            [
                'labels' => [
                    'name'          => __( 'Agentes', 'aurora-chat' ),
                    'singular_name' => __( 'Agente', 'aurora-chat' ),
                ],
                'public'             => false,
                'show_ui'            => true,
                'show_in_menu'       => false,
                'supports'           => [ 'title', 'editor' ],
                'capability_type'    => 'post',
                'map_meta_cap'       => true,
                'menu_icon'          => 'dashicons-id-alt',
                'rewrite'            => false,
            ]
        );
    }

    /**
     * Cria CPT de templates.
     */
    protected function register_template_cpt() {
        register_post_type(
            self::CPT_TEMPLATE,
            [
                'labels' => [
                    'name'          => __( 'Templates', 'aurora-chat' ),
                    'singular_name' => __( 'Template', 'aurora-chat' ),
                ],
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => false,
                'supports'        => [ 'title', 'editor' ],
                'capability_type' => 'post',
                'rewrite'         => false,
            ]
        );
    }

    /**
     * Cria CPT de mensagens.
     */
    protected function register_message_cpt() {
        register_post_type(
            self::CPT_MESSAGE,
            [
                'labels' => [
                    'name'          => __( 'Mensagens', 'aurora-chat' ),
                    'singular_name' => __( 'Mensagem', 'aurora-chat' ),
                ],
                'public'          => false,
                'show_ui'         => false,
                'capability_type' => 'post',
                'rewrite'         => false,
            ]
        );
    }

    /**
     * Shortcode default assets etc.
     */
    public static function activate() {
        self::instance()->register_post_types();
        flush_rewrite_rules();
        self::instance()->create_default_templates();
    }

    /**
     * Gera templates padrão se necessário.
     */
    protected function create_default_templates( $force = false ) {
        $defaults = [
            'sessao' => [
                'title'   => __( 'Sessão', 'aurora-chat' ),
                'layout'  => 'session',
                'content' => $this->get_session_template_markup(),
            ],
            'balao'  => [
                'title'   => __( 'Balão de Diálogo', 'aurora-chat' ),
                'layout'  => 'bubble',
                'content' => $this->get_bubble_template_markup(),
            ],
        ];

        foreach ( $defaults as $slug => $data ) {
            $existing = get_posts(
                [
                    'post_type'      => self::CPT_TEMPLATE,
                    'name'           => $slug,
                    'posts_per_page' => 1,
                    'post_status'    => 'any',
                ]
            );

            if ( ! empty( $existing ) && ! $force ) {
                continue;
            }

            if ( ! empty( $existing ) && $force ) {
                wp_delete_post( $existing[0]->ID, true );
            }

            $post_id = wp_insert_post(
                [
                    'post_type'   => self::CPT_TEMPLATE,
                    'post_name'   => $slug,
                    'post_title'  => $data['title'],
                    'post_status' => 'publish',
                    'post_content'=> $data['content'],
                ]
            );

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, '_aurora_template_layout', $data['layout'] );
            }
        }
    }

    /**
     * HTML padrão do template Sessão.
     */
    protected function get_session_template_markup() {
        ob_start();
        include AURORA_CHAT_DIR . 'templates/session.php';
        return ob_get_clean();
    }

    /**
     * HTML padrão do template Balão.
     */
    protected function get_bubble_template_markup() {
        ob_start();
        include AURORA_CHAT_DIR . 'templates/bubble.php';
        return ob_get_clean();
    }

    /**
     * Corta o texto para o limite de caracteres informado (suporta mb_* quando disponível).
     *
     * @param string $text
     * @param int $limit
     * @return string
     */
    protected function truncate_text_limit( $text, $limit ) {
        $limit = absint( $limit );
        if ( $limit <= 0 ) {
            return $text;
        }
        if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
            if ( mb_strlen( $text ) > $limit ) {
                return mb_substr( $text, 0, $limit );
            }
            return $text;
        }
        if ( strlen( $text ) > $limit ) {
            return substr( $text, 0, $limit );
        }
        return $text;
    }
}
