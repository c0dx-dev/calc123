<?php
/**
 * Plugin Name: Calc123
 * Description: Гибкий калькулятор по формулам с выводом шорткодом.
 * Version: 1.3
 * Author: svtagan@gmail.com
 * Text Domain: calc123
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'template_redirect', 'calc123_protect_about_html' );

function calc123_load_textdomain() {
    load_plugin_textdomain( 'calc123', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'calc123_load_textdomain' );

function calc123_redirect_with_buffer( $url ) {
    while ( ob_get_level() ) {
        ob_end_clean();
    }
    if ( ! headers_sent() ) {
        wp_safe_redirect( $url );
        exit;
    }
    $encoded = wp_json_encode( $url );
    $escaped = esc_url( $url );
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Redirect</title></head><body>';
    echo '<p><a href="' . $escaped . '">Перейти</a></p>';
    echo '<script>window.location = ' . $encoded . ';</script>';
    echo '</body></html>';
    exit;
}

function calc123_sanitize_wrapper_suffix( $value ) {
    if ( ! is_string( $value ) ) {
        return '';
    }

    $value = trim( wp_unslash( $value ) );
    $value = preg_replace( '/\s+/', '', $value );

    if ( stripos( $value, 'calc123-' ) === 0 ) {
        $value = substr( $value, 8 );
    }

    $value = preg_replace( '/[^A-Za-z0-9_-]/', '', $value );

    return $value ?: '';
}

function calc123_normalize_wrapper_suffix( $value ) {
    if ( is_null( $value ) ) {
        return false;
    }

    $value = calc123_sanitize_wrapper_suffix( $value );
    return $value !== '' ? $value : false;
}

function calc123_collect_variables( $raw_names, $raw_labels, $raw_types, $raw_opts, $raw_hide, $raw_widths, $existing_widths = array() ) {
    $result = array(
        'variables' => array(),
        'errors'    => array(),
    );

    $count = is_array( $raw_names ) ? count( $raw_names ) : 0;
    $allowed_widths = array( '1/1', '1/2', '1/3', '1/4' );
    $existing_widths = is_array( $existing_widths ) ? array_values( $existing_widths ) : array();

    for ( $i = 0; $i < $count; $i++ ) {
        $nm = isset( $raw_names[ $i ] ) ? trim( $raw_names[ $i ] ) : '';
        $nm = preg_replace( '/[^A-Za-z0-9_]/', '', $nm );
        if ( $nm === '' ) {
            continue;
        }

        $label = isset( $raw_labels[ $i ] ) ? sanitize_text_field( $raw_labels[ $i ] ) : '';
        if ( $label === '' ) {
            $label = $nm;
        }

        $type = ( isset( $raw_types[ $i ] ) && $raw_types[ $i ] === 'select' ) ? 'select' : 'number';
        $opts = array();

        if ( $type === 'select' ) {
            $raw = isset( $raw_opts[ $i ] ) ? $raw_opts[ $i ] : '';
            $parts = explode( ',', $raw );
            foreach ( $parts as $p_index => $p ) {
                $p = trim( $p );
                if ( $p === '' ) {
                    continue;
                }
                $pair = explode( ':', $p, 2 );
                $lab  = trim( $pair[0] ?? '' );
                $val  = trim( $pair[1] ?? '' );
                if ( $lab === '' || $val === '' ) {
                $result['errors'][] = array(
                    'index'   => $i,
                    'field'   => 'var_options',
                    'label'   => $label,
                    'message' => sprintf( __( 'Опция #%d должна быть в формате "Метка:значение".', 'calc123' ), $p_index + 1 ),
                );
                    continue;
                }
                $normalized_val = str_replace( ',', '.', $val );
                $validated_val  = filter_var( $normalized_val, FILTER_VALIDATE_FLOAT );
                if ( false === $validated_val ) {
                    $result['errors'][] = array(
                        'index'   => $i,
                        'field'   => 'var_options',
                        'label'   => $label,
                    'message' => sprintf( __( 'Значение "%s" (опция #%d) должно быть числом.', 'calc123' ), $val, $p_index + 1 ),
                    );
                    continue;
                }
                $opts[] = array(
                    'label' => sanitize_text_field( $lab ),
                    'value' => (float) $validated_val,
                );
            }

            if ( empty( $opts ) ) {
                $result['errors'][] = array(
                    'index'   => $i,
                    'field'   => 'var_options',
                    'label'   => $label,
                'message' => __( 'Добавьте хотя бы одну корректную опцию.', 'calc123' ),
                );
                continue;
            }
        }

        $hide = isset( $raw_hide[ $i ] ) ? true : false;

        $width = null;
        if ( is_array( $raw_widths ) && array_key_exists( $i, $raw_widths ) ) {
            $width = trim( wp_unslash( $raw_widths[ $i ] ) );
        }

        if ( ! in_array( $width, $allowed_widths, true ) ) {
            $existing_width = isset( $existing_widths[ $i ] ) ? trim( $existing_widths[ $i ] ) : '';
            if ( in_array( $existing_width, $allowed_widths, true ) ) {
                $width = $existing_width;
            } else {
                $width = '1/1';
            }
        }

        $result['variables'][] = array(
            'name'     => $nm,
            'label'    => $label,
            'type'     => $type,
            'options'  => $opts,
            'hide'     => $hide,
            'width'    => $width,
        );
    }

    return $result;
}

add_action( 'init', 'calc123_register_frontend_assets' );

function calc123_register_frontend_assets() {
    $plugin_url = plugin_dir_url( __FILE__ );

    if ( ! wp_script_is( 'calc123-frontend', 'registered' ) ) {
        wp_register_script(
            'calc123-frontend',
            $plugin_url . 'calc123-frontend.js',
            array(),
            '1.3',
            true
        );
    }

    if ( ! wp_style_is( 'calc123-frontend', 'registered' ) ) {
        wp_register_style(
            'calc123-frontend',
            $plugin_url . 'calc123-frontend.css',
            array(),
            '1.3'
        );
    }
}

function calc123_enqueue_frontend_assets() {
    static $localized = false;

    calc123_register_frontend_assets();
    wp_enqueue_script( 'calc123-frontend' );
    wp_enqueue_style( 'calc123-frontend' );

    if ( ! $localized ) {
        $data = array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'calc123_ajax' ),
            'messages' => array(
                'fillRequired'   => __( 'Пожалуйста, заполните все обязательные поля.', 'calc123' ),
                'invalidNumber'  => __( 'Поля должны содержать корректные числа.', 'calc123' ),
                'processing'     => __( 'Выполняется...', 'calc123' ),
                'connectionError'=> __( 'Ошибка соединения', 'calc123' ),
                'serverError'    => __( 'Серверная ошибка', 'calc123' ),
                'errorPrefix'    => __( 'Ошибка: ', 'calc123' ),
                'captchaLabel'   => __( 'Капча', 'calc123' ),
                'captchaTemplate'=> __( '%1$s + %2$s = ', 'calc123' ),
                'optionsPrompt'  => __( 'укажите хотя бы одну опцию в формате "Метка:значение".', 'calc123' ),
                'optionsFormat'  => __( 'опция #%1$s должна быть в формате "Метка:значение".', 'calc123' ),
                'optionsNumber'  => __( 'значение "%1$s" (опция #%2$s) должно быть числом.', 'calc123' ),
                'optionsValid'   => __( 'укажите хотя бы одну корректную опцию.', 'calc123' ),
                'optionsHeader'  => __( 'Переменная "%s":', 'calc123' ),
            ),
        );
        wp_add_inline_script( 'calc123-frontend', 'window.calc123Data = window.calc123Data || ' . wp_json_encode( $data ) . ';', 'before' );
        $localized = true;
    }
}

/**
 * ADMIN: меню
 */
add_action( 'admin_menu', function(){
    add_menu_page( __( 'Calc123', 'calc123' ), __( 'Calc123', 'calc123' ), 'manage_options', 'calc123', 'calc123_admin_page', 'dashicons-calculator', 58 );
});

/**
 * Полная функция страницы админки.
 * ВАЖНО: здесь сначала обрабатываются GET/POST действия (delete, duplicate, save), затем уже выводится UI.
 */
function calc123_admin_page() {
    if ( ! current_user_can('manage_options') ) {
        wp_die( esc_html__( 'Недостаточно прав', 'calc123' ) );
    }

    // ---------------------------
    // 1) ОБРАБОТКА ДЕЙСТВИЙ (до вывода)
    // ---------------------------

    $field_errors_new  = array();
    $field_errors_edit = array();

    // Удаление — с буферизацией и fallback-редиректом
    if ( isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['calc_id']) ) {
        // Начинаем буферизацию, чтобы гарантированно убрать любой вывод перед редиректом
        if (!ob_get_level()) ob_start();
    
        $del_id = absint($_GET['calc_id']);
        $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
        if ( wp_verify_nonce( $nonce, 'calc123_delete_' . $del_id ) ) {
            $all = get_option('calc123_calculators', array());
            foreach ($all as $k => $c) {
                if ( (int) $c['id'] === $del_id ) {
                    unset($all[$k]);
                    break;
                }
            }
            update_option('calc123_calculators', array_values($all));
            set_transient('calc123_notice', __( 'Калькулятор удалён.', 'calc123' ), 30);
        } else {
            set_transient('calc123_notice', __( 'Ошибка: неверный nonce для удаления.', 'calc123' ), 30);
        }
    
        calc123_redirect_with_buffer( admin_url('admin.php?page=calc123') );
    }


    // Дублирование — с буферизацией и fallback-редиректом
    if ( isset($_GET['action']) && $_GET['action'] === 'duplicate' && isset($_GET['calc_id']) ) {
        if (!ob_get_level()) ob_start();

        $dup_id = absint($_GET['calc_id']);
        $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
        if ( wp_verify_nonce( $nonce, 'calc123_duplicate_' . $dup_id ) ) {
            $all = get_option('calc123_calculators', array());
            $found = null;
            foreach ($all as $c) {
                if ( (int) $c['id'] === $dup_id ) { $found = $c; break; }
            }
            if ($found) {
                $max = 0;
                foreach ($all as $c) {
                    if ( isset( $c['id'] ) && (int) $c['id'] > $max ) {
                        $max = (int) $c['id'];
                    }
                }
                $new_id = $max + 1;
                $copy = $found;
                $copy['id'] = $new_id;
                $copy['name'] = sprintf( __( '%s (копия)', 'calc123' ), $copy['name'] );
                if (!isset($copy['currency_display'])) $copy['currency_display'] = '';
                if (!isset($copy['currency_pos'])) $copy['currency_pos'] = 'after';
                if (!isset($copy['wrapper_id'])) $copy['wrapper_id'] = '';
                if (!isset($copy['wrapper_class'])) $copy['wrapper_class'] = '';
                $all[] = $copy;
                update_option('calc123_calculators', $all);
                set_transient('calc123_notice', sprintf( __( 'Калькулятор дублирован. Шорткод: [calc123 id="%d"]', 'calc123' ), $new_id ), 30);
            } else {
                set_transient('calc123_notice', __( 'Ошибка: калькулятор не найден для дублирования.', 'calc123' ), 30);
            }
        } else {
            set_transient('calc123_notice', __( 'Ошибка: неверный nonce для дублирования.', 'calc123' ), 30);
        }

        calc123_redirect_with_buffer( admin_url('admin.php?page=calc123') );
    }


    // Сохранение нового калькулятора (POST)
    $save_errors = array();
    if ( isset($_POST['calc123_action']) && $_POST['calc123_action'] === 'save_calc' ) {
        check_admin_referer('calc123_save', 'calc123_nonce');

        $name = isset($_POST['calc_name']) ? sanitize_text_field($_POST['calc_name']) : '';
        $formula = isset($_POST['calc_formula']) ? trim($_POST['calc_formula']) : '';
        $raw_names  = isset($_POST['var_name']) ? $_POST['var_name'] : array();
        $raw_labels = isset($_POST['var_label']) ? $_POST['var_label'] : array();
        $raw_types  = isset($_POST['var_type']) ? $_POST['var_type'] : array();
        $raw_opts   = isset($_POST['var_options']) ? $_POST['var_options'] : array();
        $raw_hide   = isset($_POST['var_hide']) ? $_POST['var_hide'] : array();
        $raw_widths = isset($_POST['var_width']) ? $_POST['var_width'] : array();

        $wrapper_id = calc123_normalize_wrapper_suffix( isset( $_POST['calc_wrapper_id'] ) ? $_POST['calc_wrapper_id'] : '' );
        if ( false === $wrapper_id ) {
            $wrapper_id = '';
        }

        $wrapper_class = calc123_normalize_wrapper_suffix( isset( $_POST['calc_wrapper_class'] ) ? $_POST['calc_wrapper_class'] : '' );
        if ( false === $wrapper_class ) {
            $wrapper_class = '';
        }

        $currency_display = isset($_POST['currency_display']) ? sanitize_text_field($_POST['currency_display']) : '';
        $currency_pos = isset($_POST['currency_pos']) && in_array($_POST['currency_pos'], array('before','after')) ? $_POST['currency_pos'] : 'after';
        $require_captcha = isset($_POST['require_captcha']) ? true : false;

        if ($name === '') $save_errors[] = __( 'Укажите название калькулятора.', 'calc123' );
        if ($formula === '') $save_errors[] = __( 'Укажите формулу.', 'calc123' );

        $collect_result   = calc123_collect_variables( $raw_names, $raw_labels, $raw_types, $raw_opts, $raw_hide, $raw_widths );
        $variables        = $collect_result['variables'];
        $field_errors_new = $collect_result['errors'];

        if ( ! empty( $field_errors_new ) ) {
            $save_errors[] = __( 'Исправьте ошибки в настройках переменных.', 'calc123' );
        }

        if (empty($variables)) $save_errors[] = __( 'Добавьте хотя бы одну переменную.', 'calc123' );

        if (empty($save_errors)) {
            $all = get_option('calc123_calculators', array());
            $max = 0;
            foreach ($all as $c) {
                if ( isset( $c['id'] ) && (int) $c['id'] > $max ) {
                    $max = (int) $c['id'];
                }
            }
            $new_id = $max + 1;
            $all[] = array(
                'id' => $new_id,
                'name' => $name,
                'formula' => $formula,
                'variables' => $variables,
                'currency_display' => $currency_display,
                'currency_pos' => $currency_pos,
                'require_captcha' => $require_captcha,
                'wrapper_id' => $wrapper_id ? $wrapper_id : '',
                'wrapper_class' => $wrapper_class ? $wrapper_class : '',
            );
            update_option('calc123_calculators', $all);
            set_transient('calc123_notice', sprintf( __( 'Калькулятор добавлен. Шорткод: [calc123 id="%s"]', 'calc123' ), (string) $new_id ), 30);
            calc123_redirect_with_buffer( admin_url('admin.php?page=calc123') );
        }
    }

    // Сохранение редактирования
    if ( isset($_POST['calc123_action']) && $_POST['calc123_action'] === 'save_edit' ) {
        check_admin_referer('calc123_edit_save', 'calc123_edit_nonce');

        $edit_id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;
        $name = isset($_POST['calc_name']) ? sanitize_text_field($_POST['calc_name']) : '';
        $formula = isset($_POST['calc_formula']) ? trim($_POST['calc_formula']) : '';
        $raw_names  = isset($_POST['var_name']) ? $_POST['var_name'] : array();
        $raw_labels = isset($_POST['var_label']) ? $_POST['var_label'] : array();
        $raw_types  = isset($_POST['var_type']) ? $_POST['var_type'] : array();
        $raw_opts   = isset($_POST['var_options']) ? $_POST['var_options'] : array();
        $raw_hide   = isset($_POST['var_hide']) ? $_POST['var_hide'] : array();
        $raw_widths = isset($_POST['var_width']) ? $_POST['var_width'] : array();

        $existing_widths = array();
        $current_calculators = get_option('calc123_calculators', array());
        if ( $edit_id > 0 && ! empty( $current_calculators ) ) {
            foreach ( $current_calculators as $existing_calc_entry ) {
                if ( (int) $existing_calc_entry['id'] === $edit_id ) {
                    if ( isset( $existing_calc_entry['variables'] ) && is_array( $existing_calc_entry['variables'] ) ) {
                        foreach ( $existing_calc_entry['variables'] as $existing_var ) {
                            $existing_widths[] = isset( $existing_var['width'] ) ? $existing_var['width'] : '1/1';
                        }
                    }
                    break;
                }
            }
        }

        $wrapper_id = calc123_normalize_wrapper_suffix( isset( $_POST['calc_wrapper_id'] ) ? $_POST['calc_wrapper_id'] : '' );
        if ( false === $wrapper_id ) {
            $wrapper_id = '';
        }

        $wrapper_class = calc123_normalize_wrapper_suffix( isset( $_POST['calc_wrapper_class'] ) ? $_POST['calc_wrapper_class'] : '' );
        if ( false === $wrapper_class ) {
            $wrapper_class = '';
        }

        $currency_display = isset($_POST['currency_display']) ? sanitize_text_field($_POST['currency_display']) : '';
        $currency_pos = isset($_POST['currency_pos']) && in_array($_POST['currency_pos'], array('before','after')) ? $_POST['currency_pos'] : 'after';
        $require_captcha = isset($_POST['require_captcha']) ? true : false;

        if ($edit_id <= 0) $save_errors[] = __( 'Неверный ID при сохранении.', 'calc123' );
        if ($name === '') $save_errors[] = __( 'Укажите название калькулятора.', 'calc123' );
        if ($formula === '') $save_errors[] = __( 'Укажите формулу.', 'calc123' );

        $collect_result    = calc123_collect_variables( $raw_names, $raw_labels, $raw_types, $raw_opts, $raw_hide, $raw_widths, $existing_widths );
        $variables         = $collect_result['variables'];
        $field_errors_edit = $collect_result['errors'];

        if ( ! empty( $field_errors_edit ) ) {
            $save_errors[] = __( 'Исправьте ошибки в настройках переменных.', 'calc123' );
        }

        if (empty($variables)) $save_errors[] = __( 'Добавьте хотя бы одну переменную.', 'calc123' );

        if (empty($save_errors)) {
            $all = is_array( $current_calculators ) ? $current_calculators : array();
            $found = false;
            foreach ($all as $k => $c) {
                if ( (int) $c['id'] === $edit_id ) {
                    $all[$k] = array(
                        'id' => $edit_id,
                        'name' => $name,
                        'formula' => $formula,
                        'variables' => $variables,
                        'currency_display' => $currency_display,
                        'currency_pos' => $currency_pos,
                        'require_captcha' => $require_captcha,
                        'wrapper_id' => $wrapper_id,
                        'wrapper_class' => $wrapper_class,
                    );
                    $found = true;
                    break;
                }
            }
            if ($found) {
                update_option('calc123_calculators', $all);
                set_transient('calc123_notice', __( 'Калькулятор обновлён.', 'calc123' ), 30);
                calc123_redirect_with_buffer( admin_url('admin.php?page=calc123') );
            } else {
                $save_errors[] = __( 'Калькулятор с таким ID не найден.', 'calc123' );
            }
        }
    }

    // ---------------------------
    // 2) ТРАНЗИЕНТЫ / ОПОВЕЩЕНИЯ И ВЫВОД ОШИБОК (можно уже выводить HTML)
    // ---------------------------
    $notice = get_transient('calc123_notice');
    if ($notice) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        delete_transient('calc123_notice');
    }
    if (!empty($save_errors)) {
        echo '<div class="notice notice-error"><p>' . implode('<br>', array_map('esc_html', $save_errors)) . '</p></div>';
    }

    // ---------------------------
    // 3) ВЫВОД UI: список калькуляторов, формы создания/редактирования
    // ---------------------------
    $calculators = get_option('calc123_calculators', array());
    $editing = false;
    $edit_calc = null;
    if ( isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['calc_id']) ) {
        $edit_id = absint($_GET['calc_id']);
        foreach ($calculators as $c) {
            if ( (int) $c['id'] === $edit_id ) { $editing = true; $edit_calc = $c; break; }
        }
    }

    if ( $editing && $edit_calc ) {
        if ( ! isset( $edit_calc['wrapper_id'] ) ) {
            $edit_calc['wrapper_id'] = '';
        }
        if ( ! isset( $edit_calc['wrapper_class'] ) ) {
            $edit_calc['wrapper_class'] = '';
        }
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Calc123 — управление калькуляторами', 'calc123' ); ?></h1>

        <div class="calc123-admin-actions">
            <button type="button" class="button button-secondary" id="calc123-about-open"><?php esc_html_e( 'Описание и примеры', 'calc123' ); ?></button>
        </div>

        <div id="calc123-about-modal" class="calc123-about-modal" hidden>
            <div class="calc123-about-modal__backdrop" data-role="modal-close"></div>
            <div class="calc123-about-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="calc123-about-title">
                <button type="button" class="button calc123-about-modal__close" data-role="modal-close" aria-label="<?php echo esc_attr__( 'Закрыть', 'calc123' ); ?>">&times;</button>
                <div class="calc123-about-modal__body">
                    <h2 id="calc123-about-title" class="calc123-about-modal__title"><?php esc_html_e( 'Описание и примеры', 'calc123' ); ?></h2>
                    <div class="calc123-about-modal__content" id="calc123-about-content"></div>
                </div>
            </div>
        </div>

        <h2><?php echo esc_html__( 'Список калькуляторов', 'calc123' ); ?></h2>
        <?php if (!empty($calculators)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>ID</th><th><?php echo esc_html__( 'Название', 'calc123' ); ?></th><th><?php echo esc_html__( 'Формула', 'calc123' ); ?></th><th><?php echo esc_html__( 'Шорткод', 'calc123' ); ?></th><th><?php echo esc_html__( 'Действия', 'calc123' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ($calculators as $c): ?>
                    <tr>
                        <td><?php echo esc_html($c['id']); ?></td>
                        <td><?php echo esc_html($c['name']); ?></td>
                        <td><code><?php echo esc_html($c['formula']); ?></code></td>
                        <td><code>[calc123 id="<?php echo esc_attr($c['id']); ?>"]</code></td>
                        <td>
                            <?php
                            $edit_url = add_query_arg(array('page'=>'calc123','action'=>'edit','calc_id'=>$c['id']), admin_url('admin.php'));
                            $calc_id_int = absint( $c['id'] );
                            $dup_nonce = wp_create_nonce('calc123_duplicate_' . $calc_id_int);
                            $dup_url = add_query_arg(array('page'=>'calc123','action'=>'duplicate','calc_id'=>$calc_id_int,'_wpnonce'=>$dup_nonce), admin_url('admin.php'));
                            $del_nonce = wp_create_nonce('calc123_delete_' . $calc_id_int);
                            echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Редактировать', 'calc123' ) . '</a> | ';
                            echo '<a href="' . esc_url( $dup_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Создать копию калькулятора?', 'calc123' ) ) . '\');">' . esc_html__( 'Дублировать', 'calc123' ) . '</a> | ';
                            echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'calc123', 'action' => 'delete', 'calc_id' => $calc_id_int, '_wpnonce' => $del_nonce ), admin_url( 'admin.php' ) ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Удалить?', 'calc123' ) ) . '\');">' . esc_html__( 'Удалить', 'calc123' ) . '</a>';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php echo esc_html__( 'Калькуляторы не найдены.', 'calc123' ); ?></p>
        <?php endif; ?>

        <h2><?php echo $editing ? esc_html__( 'Редактирование калькулятора', 'calc123' ) : esc_html__( 'Добавить новый калькулятор', 'calc123' ); ?></h2>

        <?php if ($editing && $edit_calc): ?>
            <form method="post">
                <?php wp_nonce_field('calc123_edit_save', 'calc123_edit_nonce'); ?>
                <input type="hidden" name="calc123_action" value="save_edit">
                <input type="hidden" name="edit_id" value="<?php echo esc_attr($edit_calc['id']); ?>">

                <?php if ( ! empty( $field_errors_edit ) ): ?>
                    <div class="notice notice-error inline">
                        <ul>
                            <?php foreach ( $field_errors_edit as $err ): ?>
                                <li><?php echo esc_html( $err['message'] ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <table class="form-table" id="calc123-edit-calc">
                    <tr>
                        <th><label for="calc_name"><?php esc_html_e( 'Название', 'calc123' ); ?></label></th>
                        <td><input type="text" id="calc_name" name="calc_name" class="regular-text" required value="<?php echo esc_attr($edit_calc['name']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="calc_formula"><?php esc_html_e( 'Формула', 'calc123' ); ?></label></th>
                        <td>
                            <input type="text" id="calc_formula" name="calc_formula" class="regular-text code" placeholder="<?php echo esc_attr__( 'Напр.: a + b * (c ^ 2)', 'calc123' ); ?>" required value="<?php echo esc_attr($edit_calc['formula']); ?>">
                            <p class="description"><?php esc_html_e( 'Переменные — латинскими буквами/цифрами/подчёркиваниями (_). Поддерживаются операторы + - * / ^ (степень), скобки, сравнения &gt; &lt; &gt;= &lt;= == != и функции IF(cond,a,b), MAX(a,b), MIN(a,b), ROUND(a,decimals).', 'calc123' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Переменные', 'calc123' ); ?></th>
                        <td>
                            <div id="calc123-vars">
                                <?php
                                foreach ($edit_calc['variables'] as $v) {
                                    $name = esc_attr($v['name']);
                                    $label = esc_attr($v['label']);
                                    $type = ($v['type'] === 'select') ? 'select' : 'number';
                                    $opts = '';
                                    if ($type === 'select' && !empty($v['options'])) {
                                        $arr = array();
                                        foreach ($v['options'] as $o) $arr[] = esc_html($o['label']) . ':' . esc_attr($o['value']);
                                        $opts = implode(',', $arr);
                                    }
                                    $hide = !empty($v['hide']) ? 'checked' : '';
                                    $width = isset( $v['width'] ) ? $v['width'] : '1/1';
                                    ?>
                                    <div class="calc123-var-row" style="margin-bottom:8px;">
                                        <input type="text" name="var_name[]" placeholder="<?php echo esc_attr__( 'Код переменной', 'calc123' ); ?>" style="width:120px;" required value="<?php echo $name; ?>">
                                        <input type="text" name="var_label[]" placeholder="<?php echo esc_attr__( 'Метка поля', 'calc123' ); ?>" style="width:160px;" value="<?php echo $label; ?>">
                                        <select name="var_type[]">
                                            <option value="number" <?php selected($type,'number'); ?>><?php esc_html_e( 'number', 'calc123' ); ?></option>
                                            <option value="select" <?php selected($type,'select'); ?>><?php esc_html_e( 'select', 'calc123' ); ?></option>
                                        </select>
                                        <input type="text" name="var_options[]" placeholder="<?php echo esc_attr__( 'Label:Value,Label2:Value2 (для select)', 'calc123' ); ?>" style="width:260px;" value="<?php echo esc_attr($opts); ?>">
                                        <select name="var_width[]">
                                            <option value="1/1" <?php selected( $width, '1/1' ); ?>><?php esc_html_e( '1 / 1', 'calc123' ); ?></option>
                                            <option value="1/2" <?php selected( $width, '1/2' ); ?>><?php esc_html_e( '1 / 2', 'calc123' ); ?></option>
                                            <option value="1/3" <?php selected( $width, '1/3' ); ?>><?php esc_html_e( '1 / 3', 'calc123' ); ?></option>
                                            <option value="1/4" <?php selected( $width, '1/4' ); ?>><?php esc_html_e( '1 / 4', 'calc123' ); ?></option>
                                        </select>
                                        <label style="margin-left:8px;"><input type="checkbox" name="var_hide[]" <?php echo $hide; ?>> <?php esc_html_e( 'скрыть', 'calc123' ); ?></label>
                                    </div>
                                <?php } ?>
                            </div>
                            <p><button type="button" class="button" id="calc123-add-var"><?php esc_html_e( 'Добавить переменную', 'calc123' ); ?></button></p>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="currency_display"><?php esc_html_e( 'Отображаемая валюта / текст (необязательно)', 'calc123' ); ?></label></th>
                        <td>
                            <input type="text" id="currency_display" name="currency_display" class="regular-text"
                                   value="<?php echo esc_attr( isset($edit_calc['currency_display']) ? $edit_calc['currency_display'] : '' ); ?>"
                                   placeholder="<?php echo esc_attr__( 'Например: ₽ или руб.', 'calc123' ); ?>">
                            <p class="description"><?php esc_html_e( 'Оставьте пустым, чтобы не показывать символ/текст.', 'calc123' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="currency_pos"><?php esc_html_e( 'Позиция валюты', 'calc123' ); ?></label></th>
                        <td>
                            <select id="currency_pos" name="currency_pos">
                                <option value="after" <?php selected( isset($edit_calc['currency_pos']) ? $edit_calc['currency_pos'] : 'after', 'after' ); ?>><?php esc_html_e( 'После результата', 'calc123' ); ?></option>
                                <option value="before" <?php selected( isset($edit_calc['currency_pos']) ? $edit_calc['currency_pos'] : 'after', 'before' ); ?>><?php esc_html_e( 'До результата', 'calc123' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="calc_wrapper_id"><?php esc_html_e( 'ID формы', 'calc123' ); ?></label></th>
                        <td>
                            <div class="calc123-prefix-input">
                                <span class="calc123-prefix">calc123-</span>
                                <input type="text" id="calc_wrapper_id" name="calc_wrapper_id" class="regular-text" pattern="^[A-Za-z0-9_\-]*$" title="<?php esc_attr_e( 'Допустимы латинские буквы, цифры, символы "-" и "_".', 'calc123' ); ?>" value="<?php echo esc_attr( $edit_calc['wrapper_id'] ); ?>">
                            </div>
                            <p class="description"><?php esc_html_e( 'Итоговый ID будет иметь вид calc123-XXX. Оставьте поле пустым, чтобы не задавать ID.', 'calc123' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="calc_wrapper_class"><?php esc_html_e( 'CSS класс формы', 'calc123' ); ?></label></th>
                        <td>
                            <div class="calc123-prefix-input">
                                <span class="calc123-prefix">calc123-</span>
                                <input type="text" id="calc_wrapper_class" name="calc_wrapper_class" class="regular-text" pattern="^[A-Za-z0-9_\-]*$" title="<?php esc_attr_e( 'Допустимы латинские буквы, цифры, символы "-" и "_".', 'calc123' ); ?>" value="<?php echo esc_attr( $edit_calc['wrapper_class'] ); ?>">
                            </div>
                            <p class="description"><?php esc_html_e( 'Итоговый класс будет иметь вид calc123-XXX. Допустимы латинские буквы, цифры, символы "-" и "_".', 'calc123' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                      <th><label for="require_captcha"><?php esc_html_e( 'Требовать математическую капчу', 'calc123' ); ?></label></th>
                      <td>
                        <label><input type="checkbox" id="require_captcha" name="require_captcha" <?php checked( !empty($edit_calc['require_captcha']), true ); ?>> <?php esc_html_e( 'Да', 'calc123' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Если включено — пользователю перед расчётом нужно решить простую арифметическую задачу.', 'calc123' ); ?></p>
                      </td>
                    </tr>

                </table>
                <?php submit_button( __( 'Сохранить изменения', 'calc123' ) ); ?>
                <a class="button" href="<?php echo esc_url( admin_url('admin.php?page=calc123') ); ?>"><?php esc_html_e( 'Отмена', 'calc123' ); ?></a>
            </form>

        <?php else: ?>
            <!-- Форма добавления нового калькулятора -->
            <form method="post">
                <?php wp_nonce_field('calc123_save', 'calc123_nonce'); ?>
                <input type="hidden" name="calc123_action" value="save_calc">

                <?php if ( ! empty( $field_errors_new ) ): ?>
                    <div class="notice notice-error inline">
                        <ul>
                            <?php foreach ( $field_errors_new as $err ): ?>
                                <li><?php echo esc_html( $err['message'] ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <table class="form-table" id="calc123-new-calc">
                    <tr>
                        <th><label for="calc_name"><?php esc_html_e( 'Название', 'calc123' ); ?></label></th>
                        <td><input type="text" id="calc_name" name="calc_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="calc_formula"><?php esc_html_e( 'Формула', 'calc123' ); ?></label></th>
                        <td>
                            <input type="text" id="calc_formula" name="calc_formula" class="regular-text code" placeholder="<?php echo esc_attr__( 'Напр.: a + b * (c ^ 2)', 'calc123' ); ?>" required>
                            <p class="description"><?php esc_html_e( 'Переменные — латинскими буквами/цифрами/подчёркиваниями (_). Поддерживаются операторы + - * / ^ (степень), скобки, сравнения &gt; &lt; &gt;= &lt;= == != и функции IF(cond,a,b), MAX(a,b), MIN(a,b), ROUND(a,decimals).', 'calc123' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Переменные', 'calc123' ); ?></th>
                        <td>
                            <div id="calc123-vars">
                                <div class="calc123-var-row" style="margin-bottom:8px;">
                                    <input type="text" name="var_name[]" placeholder="<?php echo esc_attr__( 'Код переменной', 'calc123' ); ?>" style="width:120px;" required>
                                    <input type="text" name="var_label[]" placeholder="<?php echo esc_attr__( 'Метка поля', 'calc123' ); ?>" style="width:160px;">
                                    <select name="var_type[]">
                                        <option value="number"><?php esc_html_e( 'number', 'calc123' ); ?></option>
                                        <option value="select"><?php esc_html_e( 'select', 'calc123' ); ?></option>
                                    </select>
                                    <input type="text" name="var_options[]" placeholder="<?php echo esc_attr__( 'Label:Value,Label2:Value2 (для select)', 'calc123' ); ?>" style="width:260px;">
                                    <select name="var_width[]">
                                        <option value="1/1"><?php esc_html_e( '1 / 1', 'calc123' ); ?></option>
                                        <option value="1/2"><?php esc_html_e( '1 / 2', 'calc123' ); ?></option>
                                        <option value="1/3"><?php esc_html_e( '1 / 3', 'calc123' ); ?></option>
                                        <option value="1/4"><?php esc_html_e( '1 / 4', 'calc123' ); ?></option>
                                    </select>
                                    <label style="margin-left:8px;"><input type="checkbox" name="var_hide[]"> <?php esc_html_e( 'скрыть', 'calc123' ); ?></label>
                                </div>
                            </div>
                            <p><button type="button" class="button" id="calc123-add-var"><?php esc_html_e( 'Добавить переменную', 'calc123' ); ?></button></p>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="currency_display"><?php esc_html_e( 'Отображаемая валюта / текст (необязательно)', 'calc123' ); ?></label></th>
                        <td>
                            <input type="text" id="currency_display" name="currency_display" class="regular-text" placeholder="<?php echo esc_attr__( 'Например: ₽ или руб.', 'calc123' ); ?>">
                            <p class="description"><?php esc_html_e( 'Оставьте пустым, чтобы не показывать символ/текст.', 'calc123' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="currency_pos"><?php esc_html_e( 'Позиция валюты', 'calc123' ); ?></label></th>
                        <td>
                            <select id="currency_pos" name="currency_pos">
                                <option value="after"><?php esc_html_e( 'После результата', 'calc123' ); ?></option>
                                <option value="before"><?php esc_html_e( 'До результата', 'calc123' ); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="calc_wrapper_id"><?php esc_html_e( 'ID формы', 'calc123' ); ?></label></th>
                        <td>
                            <div class="calc123-prefix-input">
                                <span class="calc123-prefix">calc123-</span>
                                <input type="text" id="calc_wrapper_id" name="calc_wrapper_id" class="regular-text" pattern="^[A-Za-z0-9_\-]*$" title="<?php esc_attr_e( 'Допустимы латинские буквы, цифры, символы "-" и "_".', 'calc123' ); ?>" value="<?php echo esc_attr( isset( $_POST['calc_wrapper_id'] ) ? calc123_sanitize_wrapper_suffix( $_POST['calc_wrapper_id'] ) : '' ); ?>">
                            </div>
                            <p class="description"><?php esc_html_e( 'Итоговый ID будет иметь вид calc123-XXX. Оставьте поле пустым, чтобы не задавать ID.', 'calc123' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="calc_wrapper_class"><?php esc_html_e( 'CSS класс формы', 'calc123' ); ?></label></th>
                        <td>
                            <div class="calc123-prefix-input">
                                <span class="calc123-prefix">calc123-</span>
                                <input type="text" id="calc_wrapper_class" name="calc_wrapper_class" class="regular-text" pattern="^[A-Za-z0-9_\-]*$" title="<?php esc_attr_e( 'Допустимы латинские буквы, цифры, символы "-" и "_".', 'calc123' ); ?>" value="<?php echo esc_attr( isset( $_POST['calc_wrapper_class'] ) ? calc123_sanitize_wrapper_suffix( $_POST['calc_wrapper_class'] ) : '' ); ?>">
                            </div>
                            <p class="description"><?php esc_html_e( 'Итоговый класс будет иметь вид calc123-XXX. Допустимы латинские буквы, цифры, символы "-" и "_".', 'calc123' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                      <th><label for="require_captcha"><?php esc_html_e( 'Требовать математическую капчу', 'calc123' ); ?></label></th>
                      <td>
                        <label><input type="checkbox" id="require_captcha" name="require_captcha"> <?php esc_html_e( 'Да', 'calc123' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Если включено — пользователю перед расчётом нужно решить простую арифметическую задачу.', 'calc123' ); ?></p>
                      </td>
                    </tr>

                </table>
                <?php submit_button( __( 'Добавить калькулятор', 'calc123' ) ); ?>
            </form>
            <!-- сноска -->
            <div>
                <p class="description"><?php esc_html_e( 'Краткая инструкция и описание плагина доступны по нажатию кнопки "описание и примеры" вверху страницы.', 'calc123' ); ?></p>
            </div>

        <?php endif; ?>

    </div>

    <script>
    window.calc123Admin = window.calc123Admin || {};
    window.calc123Admin.messages = window.calc123Admin.messages || <?php echo wp_json_encode( array(
        'cannotDeleteLast'      => __( 'Нельзя удалить последнюю переменную. Добавьте новую, если нужно.', 'calc123' ),
        'confirmRemoveVariable' => __( 'Удалить эту переменную?', 'calc123' ),
        'optionsPrompt'         => __( 'укажите хотя бы одну опцию в формате "Метка:значение".', 'calc123' ),
        'optionsFormat'         => __( 'опция #%1$s должна быть в формате "Метка:значение".', 'calc123' ),
        'optionsNumber'         => __( 'значение "%1$s" (опция #%2$s) должно быть числом.', 'calc123' ),
        'optionsValid'          => __( 'укажите хотя бы одну корректную опцию.', 'calc123' ),
        'optionsHeader'         => __( 'Переменная "%s":', 'calc123' ),
        'defaultSelectLabel'    => __( 'select #%s', 'calc123' ),
        'moveUp'                => __( 'Вверх', 'calc123' ),
        'moveDown'              => __( 'Вниз', 'calc123' ),
        'deleteVariable'        => __( 'Удалить', 'calc123' ),
        'aboutLoading'          => __( 'Загрузка…', 'calc123' ),
        'aboutLoadError'        => __( 'Не удалось загрузить описание.', 'calc123' ),
    ) ); ?>;
    window.calc123Admin.aboutNonce = '<?php echo esc_js( wp_create_nonce( 'calc123_about' ) ); ?>';
    </script>

    <script>
    (function(){
        // Делегированное управление строками переменных + кнопка добавления
        var container = document.getElementById('calc123-vars');
        if (!container) return;

        var adminMessages = (window.calc123Admin && window.calc123Admin.messages) || {};
        function adminMsg(key, fallback) {
            return adminMessages[key] || fallback;
        }

        var aboutState = { loaded: false, loading: false };
        var aboutModal = document.getElementById('calc123-about-modal');
        var aboutContent = document.getElementById('calc123-about-content');
        var aboutButton = document.getElementById('calc123-about-open');

        function setModalState(open) {
            if (open) {
                document.body.classList.add('calc123-modal-open');
            } else {
                document.body.classList.remove('calc123-modal-open');
            }
        }

        function closeAboutModal() {
            if (!aboutModal) return;
            aboutModal.hidden = true;
            setModalState(false);
        }

        function fetchAboutContent() {
            if (!aboutContent || aboutState.loading) return;
            aboutState.loading = true;
            aboutContent.textContent = adminMsg('aboutLoading', 'Загрузка…');

            var ajaxUrl = (typeof window.ajaxurl !== 'undefined') ? window.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
            var data = new FormData();
            data.append('action', 'calc123_about');
            data.append('security', (window.calc123Admin && window.calc123Admin.aboutNonce) ? window.calc123Admin.aboutNonce : '');

            if (!ajaxUrl) {
                aboutContent.textContent = adminMsg('aboutLoadError', 'Не удалось загрузить описание.');
                aboutState.loading = false;
                return;
            }

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            })
                .then(function(response){
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function(payload){
                    if (payload && payload.success && payload.data && typeof payload.data.html !== 'undefined') {
                        aboutContent.innerHTML = payload.data.html;
                        aboutState.loaded = true;
                    } else {
                        throw new Error('Invalid payload');
                    }
                    aboutState.loading = false;
                })
                .catch(function(err){
                    console.error('calc123 about load error', err);
                    aboutContent.textContent = adminMsg('aboutLoadError', 'Не удалось загрузить описание.');
                    aboutState.loading = false;
                });
        }

        function openAboutModal() {
            if (!aboutModal) return;
            aboutModal.hidden = false;
            setModalState(true);
            if (!aboutState.loaded) {
                fetchAboutContent();
            }
        }

        if (aboutButton) {
            aboutButton.addEventListener('click', function(e){
                e.preventDefault();
                openAboutModal();
            });
        }

        if (aboutModal) {
            aboutModal.addEventListener('click', function(e){
                var target = e.target;
                if (target && target.dataset && target.dataset.role === 'modal-close') {
                    e.preventDefault();
                    closeAboutModal();
                }
            });
        }

        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape' && aboutModal && !aboutModal.hidden) {
                e.preventDefault();
                closeAboutModal();
            }
        });

        function ensureControls(row) {
            if (!row) return;

            var controls = row.querySelector('.calc123-var-controls');
            if (!controls) {
                controls = document.createElement('span');
                controls.className = 'calc123-var-controls';
                row.appendChild(controls);
            }

            var buttons = [
                { className: 'calc123-move-up', label: adminMsg('moveUp', 'Вверх') },
                { className: 'calc123-move-down', label: adminMsg('moveDown', 'Вниз') },
                { className: 'calc123-del-var', label: adminMsg('deleteVariable', 'Удалить') }
            ];

            buttons.forEach(function(cfg){
                if (!row.querySelector('.' + cfg.className)) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'button ' + cfg.className;
                    btn.textContent = cfg.label;
                    controls.appendChild(btn);
                }
            });
        }

        container.querySelectorAll('.calc123-var-row').forEach(function(r){
            ensureControls(r);
        });

        // Делегирование кликов — на контейнере
        container.addEventListener('click', function(e){
            var target = e.target;
            if (!target || !target.classList) return;
            var row = target.closest('.calc123-var-row');
            if (!row) return;

            if (target.classList.contains('calc123-move-up')) {
                e.preventDefault();
                var prev = row.previousElementSibling;
                if (prev && prev.classList.contains('calc123-var-row')) {
                    container.insertBefore(row, prev);
                }
                return;
            }

            if (target.classList.contains('calc123-move-down')) {
                e.preventDefault();
                var next = row.nextElementSibling;
                if (next && next.classList.contains('calc123-var-row')) {
                    container.insertBefore(next, row);
                }
                return;
            }

            if (target.classList.contains('calc123-del-var')) {
                e.preventDefault();
                var rows = container.querySelectorAll('.calc123-var-row');
                if (rows.length <= 1) {
                    alert(adminMsg('cannotDeleteLast', 'Нельзя удалить последнюю переменную. Добавьте новую, если нужно.'));
                    return;
                }
                if (!confirm(adminMsg('confirmRemoveVariable', 'Удалить эту переменную?'))) return;
                row.parentNode.removeChild(row);
            }
        });

        var addBtn = document.getElementById('calc123-add-var');
        if (addBtn) {
            addBtn.addEventListener('click', function(){
                var template = document.querySelector('.calc123-var-row');
                                if (!template) return;
                                var row = template.cloneNode(true);
                row.querySelectorAll('input').forEach(function(i){
                    if (i.type === 'checkbox') i.checked = false; else i.value = '';
                });
                var typeSelect = row.querySelector('select[name="var_type[]"]');
                if (typeSelect) {
                    typeSelect.value = 'number';
                }
                var widthSelect = row.querySelector('select[name="var_width[]"]');
                if (widthSelect) {
                    widthSelect.value = '1/1';
                }
                row.classList.remove('calc123-has-error');
                row.querySelectorAll('.calc123-field-error').forEach(function(box){ box.remove(); });
                ensureControls(row);
                container.appendChild(row);
            });
        }

        function attachOptionsValidation(form) {
            if (!form || form.dataset.calc123Validated) return;
            form.dataset.calc123Validated = '1';
            form.addEventListener('submit', function(e){
                var rows = form.querySelectorAll('.calc123-var-row');
                var hasErrors = false;
                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    row.classList.remove('calc123-has-error');
                    row.querySelectorAll('.calc123-field-error').forEach(function(box){ box.remove(); });

                    var typeSelect = row.querySelector('select[name="var_type[]"]');
                    if (!typeSelect || typeSelect.value !== 'select') continue;
                    var optionsInput = row.querySelector('input[name="var_options[]"]');
                    if (!optionsInput) continue;
                    var raw = optionsInput.value.trim();
                    var labelInput = row.querySelector('input[name="var_label[]"]');
                    var nameInput = row.querySelector('input[name="var_name[]"]');
                    var defaultLabelPattern = adminMsg('defaultSelectLabel', 'select #%s');
                    var fieldLabel = labelInput && labelInput.value.trim() !== '' ? labelInput.value.trim() : (nameInput ? nameInput.value.trim() : defaultLabelPattern.replace('%s', (i + 1)));

                    var messages = [];
                    var normalizedParts = [];

                    if (raw === '') {
                        messages.push(adminMsg('optionsPrompt', 'укажите хотя бы одну опцию в формате "Метка:значение".'));
                    } else {
                        var parts = raw.split(',');
                        parts.forEach(function(part, index){
                            part = part.trim();
                            if (!part) {
                                return;
                            }
                            var pair = part.split(':');
                            var label = pair[0] ? pair[0].trim() : '';
                            var value = pair.length > 1 ? pair.slice(1).join(':').trim() : '';

                            if (!label || !value) {
                                messages.push(adminMsg('optionsFormat', 'опция #%1$s должна быть в формате "Метка:значение".').replace('%1$s', index + 1));
                                return;
                            }

                            var normalizedValue = value.replace(',', '.');
                            if (!isFinite(Number(normalizedValue))) {
                                messages.push(adminMsg('optionsNumber', 'значение "%1$s" (опция #%2$s) должно быть числом.').replace('%1$s', value).replace('%2$s', index + 1));
                                return;
                            }

                            normalizedParts.push(label + ':' + normalizedValue);
                        });
                    }

                    if (normalizedParts.length === 0) {
                        messages.push(adminMsg('optionsValid', 'укажите хотя бы одну корректную опцию.'));
                    }

                    if (messages.length) {
                        hasErrors = true;
                        var errorBox = document.createElement('div');
                        errorBox.className = 'calc123-field-error notice notice-error';
                        var list = document.createElement('ul');
                        messages.forEach(function(message){
                            var li = document.createElement('li');
                            li.textContent = message;
                            list.appendChild(li);
                        });
                        var header = document.createElement('strong');
                        header.textContent = adminMsg('optionsHeader', 'Переменная "%s":').replace('%s', fieldLabel);
                        errorBox.appendChild(header);
                        errorBox.appendChild(list);
                        row.appendChild(errorBox);
                        row.classList.add('calc123-has-error');
                        continue;
                    }

                    optionsInput.value = normalizedParts.join(', ');
                }

                if (hasErrors) {
                    e.preventDefault();
                    var focusRow = form.querySelector('.calc123-var-row.calc123-has-error');
                    if (focusRow) {
                        var focusField = focusRow.querySelector('input[name="var_options[]"]');
                        if (focusField) {
                            focusField.focus();
                        }
                        if (focusRow.scrollIntoView) {
                            focusRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                }
            });
        }

        document.querySelectorAll('form').forEach(function(form){
        if (!form.querySelector('input[name="calc123_action"]')) return;
            attachOptionsValidation(form);
        });
    })();
    </script>

    <style>
        .calc123-admin-actions { margin:15px 0; }
        .calc123-var-row input, .calc123-var-row select { margin-right:6px; vertical-align:middle; }
        .calc123-var-row .calc123-var-controls { display:inline-flex; gap:6px; margin-left:6px; vertical-align:middle; }
        .calc123-var-row .calc123-var-controls .button { margin:0; }
        .calc123-var-row .calc123-del-var { background: #dc3232; color:#fff; border-color:#b42b2b; }
        .calc123-var-row .calc123-del-var:hover { background:#a61f1f; border-color:#8a1919; }
        .calc123-var-row.calc123-has-error { background:#fff4f4; padding:8px; border-radius:4px; }
        .calc123-field-error { margin-top:6px; padding:6px 10px; }
        .calc123-field-error ul { margin:4px 0 0 18px; list-style:disc; }
        .calc123-prefix-input { display:flex; align-items:center; gap:6px; }
        .calc123-prefix { font-weight:600; }
        body.calc123-modal-open { overflow:hidden; }
        .calc123-about-modal[hidden] { display:none !important; }
        .calc123-about-modal { position:fixed; inset:0; z-index:100000; display:flex; align-items:center; justify-content:center; }
        .calc123-about-modal__backdrop { position:absolute; inset:0; background:rgba(0,0,0,0.5); }
        .calc123-about-modal__dialog { position:relative; background:#fff; max-width:720px; width:90%; max-height:80vh; overflow:auto; padding:24px 28px; box-shadow:0 20px 60px rgba(0,0,0,0.3); border-radius:8px; }
        .calc123-about-modal__close { position:absolute; top:12px; right:12px; font-size:24px; line-height:1; background:#f1f1f1; border-color:#d0d0d0; }
        .calc123-about-modal__close:hover { background:#e2e2e2; }
        .calc123-about-modal__title { margin-top:0; }
        .calc123-about-modal__content { overflow:auto; max-height:60vh; }
        .calc123-about-modal__content h1,
        .calc123-about-modal__content h2,
        .calc123-about-modal__content h3 { margin-top:1.2em; }
        .calc123-about-modal__content ul,
        .calc123-about-modal__content ol { margin-left:1.2em; }
    </style>

    <?php
} // конец calc123_admin_page

/**
 * FRONTEND: шорткод и рендер формы
 */
add_shortcode( 'calc123', 'calc123_shortcode' );
function calc123_shortcode( $atts ) {
    $atts = shortcode_atts( array('id' => ''), $atts, 'calc123' );
    $id = absint($atts['id']);

    $all = get_option('calc123_calculators', array());
    if (empty($all)) {
        return '<p>' . esc_html__( 'Калькуляторы не настроены.', 'calc123' ) . '</p>';
    }

    $calc = null;
    if ($id > 0) {
        foreach ($all as $c) {
            if ( (int) $c['id'] === $id ) {
                $calc = $c;
                break;
            }
        }
        if (!$calc) {
            return '<p>' . esc_html__( 'Калькулятор не найден.', 'calc123' ) . '</p>';
        }
    } else {
        $calc = $all[0];
    }

    if (!isset($calc['currency_display'])) $calc['currency_display'] = '';
    if (!isset($calc['currency_pos'])) $calc['currency_pos'] = 'after';
    if (!isset($calc['wrapper_id'])) $calc['wrapper_id'] = '';
    if (!isset($calc['wrapper_class'])) $calc['wrapper_class'] = '';

    calc123_enqueue_frontend_assets();

    $form_classes = array( 'calc123-frontend-form' );
    if ( $calc['wrapper_class'] !== '' ) {
        $form_classes[] = 'calc123-' . $calc['wrapper_class'];
    }
    $form_class_attr = esc_attr( implode( ' ', $form_classes ) );

    $form_id_attr = '';
    if ( $calc['wrapper_id'] !== '' ) {
        $form_id_attr = ' id="' . esc_attr( 'calc123-' . $calc['wrapper_id'] ) . '"';
    }

    ob_start();
    ?>
    <form<?php echo $form_id_attr; ?> class="<?php echo $form_class_attr; ?>" data-calc-id="<?php echo esc_attr($calc['id']); ?>" data-requires-captcha="<?php echo ! empty( $calc['require_captcha'] ) ? '1' : '0'; ?>">
        <h3><?php echo esc_html($calc['name']); ?></h3>
        <?php
        $visible_fields = array();
        $allowed_widths_front = array( '1/1', '1/2', '1/3', '1/4' );
        foreach ( $calc['variables'] as $v ) {
            $nm = esc_attr( $v['name'] );
            $lbl = esc_html( $v['label'] );
            $width = isset( $v['width'] ) ? $v['width'] : '1/1';
            if ( ! in_array( $width, $allowed_widths_front, true ) ) {
                $width = '1/1';
            }
            $width_slug = str_replace( '/', '-', $width );
            $width_class = 'calc123-width-' . $width_slug;

            if ( $v['type'] === 'select' ) {
                if ( ! empty( $v['hide'] ) ) {
                    $val = isset( $v['options'][0] ) ? $v['options'][0]['value'] : 0;
                    echo '<input type="hidden" name="' . $nm . '" value="' . esc_attr( $val ) . '">';
                    continue;
                }

                ob_start();
                echo '<div class="calc123-field ' . esc_attr( $width_class ) . '">';
                echo '<label>' . $lbl . '</label>';
                echo '<select name="' . $nm . '" required>';
                foreach ( $v['options'] as $opt ) {
                    echo '<option value="' . esc_attr( $opt['value'] ) . '">' . esc_html( $opt['label'] ) . '</option>';
                }
                echo '</select>';
                echo '</div>';
                $visible_fields[] = ob_get_clean();
            } else {
                if ( ! empty( $v['hide'] ) ) {
                    echo '<input type="hidden" name="' . $nm . '" value="0">';
                    continue;
                }

                ob_start();
                echo '<div class="calc123-field ' . esc_attr( $width_class ) . '">';
                echo '<label>' . $lbl . '</label>';
                echo '<input type="number" name="' . $nm . '" step="any" required>';
                echo '</div>';
                $visible_fields[] = ob_get_clean();
            }
        }

        if ( ! empty( $visible_fields ) ) {
            echo '<div class="calc123-fields">' . implode( '', $visible_fields ) . '</div>';
        }
        ?>
        <?php if (!empty($calc['require_captcha'])): ?>
            <div class="calc123-captcha" hidden>
                <label data-role="captcha-label"><?php echo esc_html__( 'Капча:', 'calc123' ); ?></label>
                <input type="number" name="captcha_answer" step="1" required style="width:120px; display:inline-block;">
                <input type="hidden" name="captcha_a" value="">
                <input type="hidden" name="captcha_b" value="">
                <input type="hidden" name="captcha_token" value="">
            </div>
        <?php endif; ?>
        <p><button type="button" class="button calc123-calc-btn"><?php esc_html_e( 'Рассчитать', 'calc123' ); ?></button></p>
        <div class="calc123-result" aria-live="polite"></div>
    </form>

    <?php
    return ob_get_clean();
}

/**
 * AJAX compute handler
 */
add_action('wp_ajax_calc123_compute', 'calc123_ajax_compute');
add_action('wp_ajax_nopriv_calc123_compute', 'calc123_ajax_compute');

function calc123_ajax_compute() {
    ob_start();

    if ( empty($_POST['security']) || ! wp_verify_nonce( sanitize_text_field($_POST['security']), 'calc123_ajax' ) ) {
        $out = ob_get_clean();
        wp_send_json_error(array('message' => __( 'Невалидный security token (nonce).', 'calc123' ), 'debug' => $out));
    }

    $calc_id = isset($_POST['calc_id']) ? absint($_POST['calc_id']) : 0;
    if (!$calc_id) {
        $out = ob_get_clean();
        wp_send_json_error(array('message' => __( 'Неверный ID калькулятора.', 'calc123' ), 'debug' => $out));
    }

    $all = get_option('calc123_calculators', array());
    $calc = null;
    foreach ($all as $c) {
        if ( (int) $c['id'] === $calc_id ) {
            $calc = $c;
            break;
        }
    }
    if (!$calc) {
        $out = ob_get_clean();
        wp_send_json_error(array('message' => __( 'Калькулятор не найден', 'calc123' ), 'debug' => $out));
    }

    // gather vars with required check: if variable is visible (not hide) it must be provided and non-empty
    $vars = array();
    foreach ($calc['variables'] as $v) {
        $name = $v['name'];
        $is_hidden = !empty($v['hide']);
        if (isset($_POST[$name])) {
            $raw = $_POST[$name];
            if (is_array($raw)) {
                $out = ob_get_clean();
                wp_send_json_error(array('message' => sprintf( __( 'Неверный формат поля %s', 'calc123' ), $name ), 'debug' => $out));
            }
            $val = str_replace(',', '.', trim($raw));
            if ($val === '') {
                if ($is_hidden) {
                    // если скрыто — принимаем 0
                    $vars[$name] = 0;
                } else {
                    $out = ob_get_clean();
                    wp_send_json_error(array('message' => sprintf( __( 'Поле "%s" обязательно для заполнения.', 'calc123' ), $v['label'] ), 'debug' => $out));
                }
            } else {
                if ( false === filter_var( $val, FILTER_VALIDATE_FLOAT ) ) {
                    $out = ob_get_clean();
                    wp_send_json_error(array('message' => sprintf( __( 'Поле "%s" должно быть числом.', 'calc123' ), $v['label'] ), 'debug' => $out));
                }
                $vars[$name] = floatval($val);
            }
        } else {
            // поле не передано
            if ($is_hidden) {
                $vars[$name] = 0;
            } else {
                $out = ob_get_clean();
                wp_send_json_error(array('message' => sprintf( __( 'Поле "%s" обязательно для заполнения.', 'calc123' ), $v['label'] ), 'debug' => $out));
            }
        }
    }
    // Если для калькулятора включена капча — проверим её
    if (!empty($calc['require_captcha'])) {
        $cap_answer = isset($_POST['captcha_answer']) ? trim($_POST['captcha_answer']) : '';
        $cap_a = isset($_POST['captcha_a']) ? trim($_POST['captcha_a']) : '';
        $cap_b = isset($_POST['captcha_b']) ? trim($_POST['captcha_b']) : '';
        $cap_token = isset($_POST['captcha_token']) ? trim($_POST['captcha_token']) : '';

        // базовые проверки
        if ($cap_answer === '' || $cap_a === '' || $cap_b === '' || $cap_token === '') {
            $out = ob_get_clean();
            wp_send_json_error(array('message' => __( 'Капча не пройдена: отсутствуют данные.', 'calc123' ), 'debug' => $out));
        }

        if ( false === filter_var( $cap_a, FILTER_VALIDATE_INT ) || false === filter_var( $cap_b, FILTER_VALIDATE_INT ) || false === filter_var( $cap_answer, FILTER_VALIDATE_INT ) ) {
            $out = ob_get_clean();
            wp_send_json_error(array('message' => __( 'Капча: неверный формат чисел.', 'calc123' ), 'debug' => $out));
        }

        // проверим токен — он должен быть таким же, как мы генерировали: wp_hash(a|b|calc_id)
        $expected_token = wp_hash( $cap_a . '|' . $cap_b . '|' . $calc['id'] );
        // use hash_equals for timing-safe compare
        if ( ! function_exists('hash_equals') || ! hash_equals( $expected_token, $cap_token ) ) {
            $out = ob_get_clean();
            wp_send_json_error(array('message' => __( 'Капча не пройдена (токен не совпадает).', 'calc123' ), 'debug' => $out));
        }

        // проверим правильность суммы
        $expected_sum = intval($cap_a) + intval($cap_b);
        if ( intval($cap_answer) !== $expected_sum ) {
            $out = ob_get_clean();
            wp_send_json_error(array('message' => __( 'Неправильный ответ капчи.', 'calc123' ), 'debug' => $out));
        }
    }    

    $formula = $calc['formula'];
    $res = calc123_evaluate_formula($formula, $vars);
    if (is_array($res) && isset($res['error'])) {
        $out = ob_get_clean();
        wp_send_json_error(array('message' => $res['error'], 'debug' => $out));
    }

    // format result using per-calc currency
    $currency_display = isset($calc['currency_display']) ? trim($calc['currency_display']) : '';
    $currency_pos = isset($calc['currency_pos']) ? $calc['currency_pos'] : 'after';
    $number = number_format( (float)$res, 2, '.', '');

    if ($currency_display !== '') {
        if ($currency_pos === 'before') {
            $formatted = $currency_display . ' ' . $number;
        } else {
            $formatted = $number . ' ' . $currency_display;
        }
    } else {
        $formatted = $number;
    }

    $out = ob_get_clean();
    wp_send_json_success(array('result' => $res, 'formatted' => $formatted, 'debug' => $out));
    
}

// AJAX: выдать новую каптчу (a, b, token)
add_action('wp_ajax_calc123_new_captcha', 'calc123_new_captcha');
add_action('wp_ajax_nopriv_calc123_new_captcha', 'calc123_new_captcha');
add_action('wp_ajax_calc123_about', 'calc123_ajax_about');

function calc123_new_captcha() {
    // проверим nonce (тот же, что и для compute)
    if ( empty($_POST['security']) || ! wp_verify_nonce( sanitize_text_field($_POST['security']), 'calc123_ajax' ) ) {
        wp_send_json_error(array('message' => __( 'Невалидный token', 'calc123' )));
    }

    $calc_id = isset($_POST['calc_id']) ? absint($_POST['calc_id']) : 0;
    if ($calc_id <= 0) {
        wp_send_json_error(array('message' => __( 'Неверный ID калькулятора', 'calc123' )));
    }

    // Опционально: можно проверить, что такой калькулятор существует
    $all = get_option('calc123_calculators', array());
    $found = false;
    foreach ($all as $c) {
        if (intval($c['id']) === $calc_id) { $found = true; break; }
    }
    if (!$found) {
        wp_send_json_error(array('message' => __( 'Калькулятор не найден', 'calc123' )));
    }

    // Сгенерируем простую каптчу — два числа 1..9 (или диапазон любой)
    $a = rand(1, 9);
    $b = rand(1, 9);
    $token = wp_hash( $a . '|' . $b . '|' . $calc_id );

    wp_send_json_success(array('a' => $a, 'b' => $b, 'token' => $token));
}

function calc123_ajax_about() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'calc123' ) ) );
    }

    check_ajax_referer( 'calc123_about', 'security' );

    $about_path = plugin_dir_path( __FILE__ ) . 'about.html';
    if ( ! file_exists( $about_path ) ) {
        wp_send_json_error( array( 'message' => __( 'Файл описания не найден.', 'calc123' ) ) );
    }

    $raw = file_get_contents( $about_path );
    if ( false === $raw ) {
        wp_send_json_error( array( 'message' => __( 'Не удалось прочитать описание.', 'calc123' ) ) );
    }

    $body = $raw;
    if ( preg_match( '/<body[^>]*>(.*)<\/body>/is', $raw, $matches ) ) {
        $body = $matches[1];
    }

    $sanitized = wp_kses_post( $body );

    wp_send_json_success( array( 'html' => $sanitized ) );
}

/**
 * EVALUATOR: tokenize -> to RPN -> eval RPN
 * Supports + - * / ^, parentheses, comparisons, IF, MAX, MIN, ROUND
 */

function calc123_evaluate_formula($formula, $vars = array()) {
    $formula = trim($formula);
    if ($formula === '') return array('error' => __( 'Пустая формула', 'calc123' ));
    if (strlen($formula) > 1500) return array('error' => __( 'Формула слишком длинная', 'calc123' ));

    $tokens = calc123_tokenize($formula);
    if (empty($tokens)) return array('error' => __( 'Ошибка разбора формулы (токены)', 'calc123' ));
    if (count($tokens) > 500) return array('error' => __( 'Слишком сложная формула', 'calc123' ));

    $rpn = calc123_to_rpn($tokens);
    if (isset($rpn['error'])) return array('error' => $rpn['error']);

    $res = calc123_eval_rpn($rpn, $vars);
    return $res;
}

function calc123_tokenize($formula) {
    $s = str_replace("\xA0", ' ', $formula);
    $len = strlen($s);
    $i = 0;
    $tokens = array();
    while ($i < $len) {
        $ch = $s[$i];
        if (ctype_space($ch)) { $i++; continue; }
        if (preg_match('/[0-9]/', $ch)) {
            $num = '';
            while ($i < $len && preg_match('/[0-9.]/', $s[$i])) { $num .= $s[$i]; $i++; }
            $tokens[] = $num;
            continue;
        }
        if (preg_match('/[A-Za-z_]/', $ch)) {
            $id = '';
            while ($i < $len && preg_match('/[A-Za-z0-9_]/', $s[$i])) { $id .= $s[$i]; $i++; }
            $tokens[] = $id;
            continue;
        }
        if (substr($s, $i, 2) === '>=' || substr($s, $i, 2) === '<=' || substr($s, $i, 2) === '==' || substr($s, $i, 2) === '!=') {
            $tokens[] = substr($s, $i, 2);
            $i += 2; continue;
        }
        if (in_array($ch, array('>','<','+','-','*','/','^','(',')',','))) {
            $tokens[] = $ch;
            $i++; continue;
        }
        $i++;
    }
    return $tokens;
}

function calc123_to_rpn($tokens) {
    $output = array();
    $stack = array();
    $precedence = array(
        'u-' => 5,
        '^' => 4,
        '*' => 3, '/' => 3,
        '+' => 2, '-' => 2,
        '>' => 1, '<' => 1, '>=' => 1, '<=' => 1, '==' => 1, '!=' => 1
    );
    $right_assoc = array('^' => true, 'u-' => true);

    $prev = null;
    $count = count($tokens);
    for ($i=0;$i<$count;$i++) {
        $token = $tokens[$i];
        if (is_numeric($token)) {
            $output[] = $token; $prev = 'value'; continue;
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token)) {
            $next = ($i+1 < $count) ? $tokens[$i+1] : null;
            if ($next === '(') {
                $stack[] = strtoupper($token);
            } else {
                $output[] = $token;
            }
            $prev = 'ident';
            continue;
        }
        if ($token === ',') {
            $found = false;
            while (!empty($stack)) {
                $op = end($stack);
                if ($op === '(') { $found = true; break; }
                $output[] = array_pop($stack);
            }
            if (!$found) return array('error' => __( 'Неправильный синтаксис (запятая).', 'calc123' ));
            $prev = ','; continue;
        }
        if ($token === '(') { $stack[] = '('; $prev = '('; continue; }
        if ($token === ')') {
            $found = false;
            while (!empty($stack)) {
                $op = array_pop($stack);
                if ($op === '(') { $found = true; break; }
                $output[] = $op;
            }
            if (!$found) return array('error' => __( 'Несбалансированные скобки.', 'calc123' ));
            if (!empty($stack) && preg_match('/^[A-Z_]+$/', end($stack))) {
                $output[] = array_pop($stack);
            }
            $prev = ')'; continue;
        }
        $ops = array('>=','<=','==','!=','>','<','+','-','*','/','^');
        if (in_array($token, $ops)) {
            if ($token === '-' && ($prev === null || in_array($prev, array('operator','(',',')))) {
                $token = 'u-';
            }
            while (!empty($stack)) {
                $top = end($stack);
                if ($top === '(') break;
                $topIsOp = in_array($top, array_keys($precedence));
                if (!$topIsOp) break;
                $p1 = $precedence[$token] ?? 0;
                $p2 = $precedence[$top] ?? 0;
                $isRight = !empty($right_assoc[$token]);
                if ((!$isRight && $p1 <= $p2) || ($isRight && $p1 < $p2)) {
                    $output[] = array_pop($stack);
                } else break;
            }
            $stack[] = $token;
            $prev = 'operator';
            continue;
        }
        return array('error' => sprintf( __( 'Неизвестный токен: %s', 'calc123' ), $token ));
    }

    while (!empty($stack)) {
        $op = array_pop($stack);
        if ($op === '(' || $op === ')') return array('error' => __( 'Несбалансированные скобки в конце.', 'calc123' ));
        $output[] = $op;
    }
    return $output;
}

function calc123_eval_rpn($rpn, $vars) {
    $stack = array();
    foreach ($rpn as $token) {
        if (is_numeric($token)) { $stack[] = floatval($token); continue; }
        if (is_string($token) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token)) {
            if (!array_key_exists($token, $vars)) return array('error' => sprintf( __( 'Не задано значение переменной %s', 'calc123' ), $token ));
            $stack[] = floatval($vars[$token]); continue;
        }
        if (is_string($token)) {
            if ($token === 'u-') {
                if (count($stack) < 1) return array('error' => __( 'Недостаточно операндов для унарного -', 'calc123' ));
                $a = array_pop($stack); $stack[] = -$a; continue;
            }
            if (in_array($token, array('+','-','*','/','^','>','<','>=','<=','==','!='))) {
                if (count($stack) < 2) return array('error' => sprintf( __( 'Недостаточно операндов для %s', 'calc123' ), $token ));
                $b = array_pop($stack); $a = array_pop($stack);
                switch ($token) {
                    case '+': $stack[] = $a + $b; break;
                    case '-': $stack[] = $a - $b; break;
                    case '*': $stack[] = $a * $b; break;
                    case '/': if ($b == 0) return array('error' => __( 'Деление на ноль', 'calc123' )); $stack[] = $a / $b; break;
                    case '^': $stack[] = pow($a, $b); break;
                    case '>': $stack[] = ($a > $b) ? 1 : 0; break;
                    case '<': $stack[] = ($a < $b) ? 1 : 0; break;
                    case '>=': $stack[] = ($a >= $b) ? 1 : 0; break;
                    case '<=': $stack[] = ($a <= $b) ? 1 : 0; break;
                    case '==': $stack[] = ($a == $b) ? 1 : 0; break;
                    case '!=': $stack[] = ($a != $b) ? 1 : 0; break;
                }
                continue;
            }
            $tk_up = strtoupper($token);
            if (in_array($tk_up, array('IF','MAX','MIN','ROUND'))) {
                if ($tk_up === 'IF') {
                    if (count($stack) < 3) return array('error' => __( 'IF требует 3 аргумента', 'calc123' ));
                    $false = array_pop($stack); $true  = array_pop($stack); $cond  = array_pop($stack);
                    $stack[] = ($cond ? $true : $false); continue;
                }
                if ($tk_up === 'MAX') {
                    if (count($stack) < 2) return array('error' => __( 'MAX требует 2 аргумента', 'calc123' ));
                    $b = array_pop($stack); $a = array_pop($stack); $stack[] = max($a,$b); continue;
                }
                if ($tk_up === 'MIN') {
                    if (count($stack) < 2) return array('error' => __( 'MIN требует 2 аргумента', 'calc123' ));
                    $b = array_pop($stack); $a = array_pop($stack); $stack[] = min($a,$b); continue;
                }
                if ($tk_up === 'ROUND') {
                    if (count($stack) < 1) return array('error' => __( 'ROUND требует минимум 1 аргумент', 'calc123' ));
                    $dec = 0; $v = array_pop($stack);
                    if (count($stack) >= 1) { $dec = array_pop($stack); }
                    $stack[] = round($v, intval($dec)); continue;
                }
            }
        }
        return array('error' => sprintf( __( 'Неизвестный элемент в RPN: %s', 'calc123' ), (string) $token ));
    }
    if (count($stack) !== 1) return array('error' => sprintf( __( 'Некорректный результат, стек содержит %s элемента(ов)', 'calc123' ), count($stack) ));
    return array_pop($stack);
}

function calc123_protect_about_html() {
    if ( is_admin() ) {
        return;
    }

    if ( empty( $_SERVER['REQUEST_URI'] ) ) {
        return;
    }

    $requested_path = wp_parse_url( home_url( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
    $about_path     = wp_parse_url( plugins_url( 'about.html', __FILE__ ), PHP_URL_PATH );

    if ( ! $requested_path || ! $about_path ) {
        return;
    }

    $requested_path = rtrim( $requested_path, '/' );
    $about_path     = rtrim( $about_path, '/' );

    if ( $requested_path === $about_path ) {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            status_header( 404 );
            nocache_headers();
            exit;
        }
    }
}


