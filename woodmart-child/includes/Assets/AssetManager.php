<?php
/**
 * Менеджер ассетов (CSS/JS) для фронтенда и админ-панели.
 *
 * @package WoodmartChildRPG\Assets
 */

namespace WoodmartChildRPG\Assets;

use WoodmartChildRPG\RPG\Character; // Используется для получения данных персонажа

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Запрещаем прямой доступ.
}

class AssetManager {

    /**
     * Экземпляр менеджера персонажа для получения данных пользователя.
     * Не используется напрямую в текущей версии этого класса, но оставлен для возможного расширения.
     * @var Character
     */
    // private $character_manager; // Убрал, так как не используется в предоставленном коде AssetManager

    /**
     * Конструктор.
     *
     * @param Character $character_manager Экземпляр Character manager.
     */
    // public function __construct( Character $character_manager ) { // Убрал $character_manager
    public function __construct() {
        // $this->character_manager = $character_manager; // Убрал
    }

    /**
     * Подключает скрипты и стили для фронтенда:
     * - страницу входа/регистрации (неавторизованные на аккаунте);
     * - страницу аккаунта (авторизованные);
     * - страницу корзины.
     */
    public function enqueue_frontend_assets() {
        $user_id = get_current_user_id();
        $user_race_slug = '';
        if ($user_id) {
            $character = new Character($user_id); // Создаем экземпляр по месту
            if ($character->has_character()) {
                $race_obj = $character->get_race();
                $user_race_slug = $race_obj ? $race_obj->get_slug() : '';
            }
        }

        // Общие данные для всех JS
        $rpg_common_data = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'user_id'  => $user_id,
            'race'     => $user_race_slug, // Передаем слаг расы
            'text'     => array( // Стандартизируем ключи для i18n
                'error_network'        => __( 'Ошибка сети. Пожалуйста, попробуйте еще раз.', 'woodmart-child' ),
                'error_generic'        => __( 'Произошла непредвиденная ошибка.', 'woodmart-child' ),
                'confirm_elf_select'   => __( 'Выберите хотя бы один товар.', 'woodmart-child' ),
                'confirm_deactivate'   => __( 'Вы уверены, что хотите деактивировать этот купон?', 'woodmart-child' ),
                'ability_activated'    => __( 'Способность активирована!', 'woodmart-child' ),
                'ability_select_item'  => __( 'Выберите товар(ы) в корзине для применения способности.', 'woodmart-child' ),
                'ability_already_used' => __( 'Способность уже активирована на этой неделе.', 'woodmart-child' ),
                'ability_level_low'    => __( 'Способность доступна с более высокого уровня.', 'woodmart-child' ),
                'coupon_taken'         => __('Уже в инвентаре', 'woodmart-child'),
                'coupon_processing'    => __('Обработка...', 'woodmart-child'),
                'take_coupon_success'  => __('Купон успешно добавлен в ваш инвентарь!', 'woodmart-child'),
                'take_coupon_error'    => __('Не удалось добавить купон. Попробуйте позже.', 'woodmart-child'),
                'activate_coupon_success' => __('Купон успешно активирован!', 'woodmart-child'),
                'activate_coupon_error' => __('Не удалось активировать купон.', 'woodmart-child'),
            ),
        );

        // 1) Страница входа/регистрации
        // Условие может быть более точным, например, если используется шорткод или это стандартная страница
        if ( (function_exists('is_account_page') && is_account_page() && !is_user_logged_in()) || $this->is_page_with_shortcode('rpg_register_form') ) {
            wp_enqueue_style(
                'rpg-login-register-style',
                WOODMART_CHILD_RPG_ASSETS_URL . '/css/login-register.css', // Используем константу URL
                array(),
                WOODMART_CHILD_RPG_VERSION // Используем константу версии
            );
            wp_enqueue_script(
                'rpg-login-register-script',
                WOODMART_CHILD_RPG_ASSETS_URL . '/js/login-register.js',
                array( 'jquery' ),
                WOODMART_CHILD_RPG_VERSION,
                true
            );
            $login_data = $rpg_common_data;
            $login_data['nonce_login_register'] = wp_create_nonce( 'rpg_login_register_nonce' ); // Отдельный nonce
            wp_localize_script('rpg-login-register-script', 'rpgLoginRegisterSettings', $login_data);
        }

        // 2) Страница аккаунта для авторизованных (включая страницу персонажа)
        if ( function_exists('is_account_page') && is_account_page() && is_user_logged_in() ) {
            wp_enqueue_script(
                'rpg-account-script',
                WOODMART_CHILD_RPG_ASSETS_URL . '/js/rpg-account.js',
                array( 'jquery', 'wp-util' ), // wp-util для JS-шаблонов, если нужны
                WOODMART_CHILD_RPG_VERSION,
                true
            );
            $account_data = $rpg_common_data;
            // Nonce для RPG действий на странице аккаунта (способности, RPG купоны)
            $account_data['nonce_rpg_actions'] = wp_create_nonce( 'rpg_ajax_nonce' );
            // Nonce для Dokan купонов (взять, активировать из инвентаря)
            $account_data['nonce_dokan_coupons'] = wp_create_nonce( 'rpg_dokan_coupon_actions_nonce' );
            wp_localize_script( 'rpg-account-script', 'rpgSettings', $account_data );

            // wp_enqueue_style('rpg-account-style', WOODMART_CHILD_RPG_ASSETS_URL . '/css/rpg-account.css', [], WOODMART_CHILD_RPG_VERSION);
        }

        // 3) Страница корзины
        if ( function_exists('is_cart') && is_cart() ) {
            wp_enqueue_script(
                'rpg-cart-script',
                WOODMART_CHILD_RPG_ASSETS_URL . '/js/rpg-cart.js',
                array( 'jquery' ),
                WOODMART_CHILD_RPG_VERSION,
                true
            );
            $cart_data = $rpg_common_data;
            $cart_data['nonce_cart_actions'] = wp_create_nonce( 'rpg_cart_ajax_nonce' ); // Nonce для действий в корзине
            wp_localize_script( 'rpg-cart-script', 'rpgCartSettings', $cart_data );
            // wp_enqueue_style('rpg-cart-style', WOODMART_CHILD_RPG_ASSETS_URL . '/css/rpg-cart.css', [], WOODMART_CHILD_RPG_VERSION);
        }
    }

    /**
     * Подключает скрипты и стили для админ-панели.
     *
     * @param string $hook_suffix Суффикс текущей страницы админки.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        if ( in_array( $hook_suffix, array( 'profile.php', 'user-edit.php', 'users.php' ), true ) ) {
            wp_enqueue_script(
                'rpg-admin-script',
                WOODMART_CHILD_RPG_ASSETS_URL . '/js/rpg-admin-profile.js',
                array( 'jquery' ),
                WOODMART_CHILD_RPG_VERSION,
                true
            );
            wp_localize_script(
                'rpg-admin-script',
                'rpgAdminSettings', // Изменил имя объекта для консистентности
                array(
                    'nonce'    => wp_create_nonce( 'rpg_admin_ajax_nonce' ),
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'text'     => array(
                        'confirm_delete' => __( 'Вы уверены, что хотите удалить этот купон?', 'woodmart-child' ),
                        'confirm_reset'  => __( 'Вы уверены, что хотите сбросить кулдаун этой способности?', 'woodmart-child' ),
                    ),
                )
            );
            // wp_enqueue_style('rpg-admin-style', WOODMART_CHILD_RPG_ASSETS_URL . '/css/admin-profile.css', [], WOODMART_CHILD_RPG_VERSION);
        }
    }

    /**
     * Подключает скрипты и стили для страницы магазина продавца Dokan.
     */
    public function enqueue_dokan_store_assets() {
        if ( function_exists('dokan_is_store_page') && dokan_is_store_page() ) {
            
            // Используем тот же скрипт, что и для страницы аккаунта, так как логика "взять купон" может быть общей.
            // Если логика сильно отличается, лучше создать отдельный файл, например, 'rpg-dokan-store.js'.
            wp_enqueue_script(
                'rpg-dokan-store-interactions', // Используем новый хэндл, чтобы не конфликтовать, если rpg-account-script уже подключен
                WOODMART_CHILD_RPG_ASSETS_URL . '/js/rpg-account.js', // Указываем на существующий rpg-account.js
                array( 'jquery', 'wp-util' ),
                WOODMART_CHILD_RPG_VERSION,
                true
            );

            $user_id = get_current_user_id();
            $user_race_slug = '';
            $store_id = dokan_get_store_info(get_query_var( 'author' ))['id'] ?? null; // Получаем ID текущего магазина

            if ($user_id) {
                $character = new Character($user_id);
                if ($character->has_character()) {
                    $race_obj = $character->get_race();
                    $user_race_slug = $race_obj ? $race_obj->get_slug() : '';
                }
            }
            
            // Данные, специфичные для страницы магазина Dokan
            $dokan_store_data = array(
                'ajax_url'  => admin_url( 'admin-ajax.php' ),
                'user_id'   => $user_id,
                'race'      => $user_race_slug,
                // ВАЖНО: Используем nonce, который будет проверяться в DokanAJAXHandler для действия rpg_take_dokan_coupon
                'nonce_dokan_coupons' => wp_create_nonce( 'rpg_dokan_coupon_actions_nonce' ), 
                'is_dokan_store_page' => true, // Флаг, что мы на странице магазина
                'store_id' => $store_id,
                'text'      => array( // Можно расширить или использовать общие тексты
                    'error_network'        => __( 'Ошибка сети.', 'woodmart-child' ),
                    'error_generic'        => __( 'Произошла ошибка.', 'woodmart-child' ),
                    'coupon_taken'         => __('Уже в инвентаре', 'woodmart-child'),
                    'coupon_processing'    => __('Обработка...', 'woodmart-child'),
                    'take_coupon_success'  => __('Купон успешно добавлен в ваш инвентарь!', 'woodmart-child'),
                    'take_coupon_error'    => __('Не удалось добавить купон. Попробуйте позже.', 'woodmart-child'),
                ),
            );
            // Локализуем под тем же именем объекта 'rpgSettings', чтобы rpg-account.js мог его использовать.
            // Если rpg-account.js уже подключен на этой странице (маловероятно, но возможно),
            // эта локализация перезапишет предыдущую. Это нормально, если данные должны быть специфичны для контекста.
            wp_localize_script( 'rpg-dokan-store-interactions', 'rpgSettings', $dokan_store_data );
        }
    }

    /**
     * Вспомогательная функция для проверки, содержит ли текущий пост/страница указанный шорткод.
     * @param string $shortcode_tag Тег шорткода.
     * @return bool
     */
    private function is_page_with_shortcode(string $shortcode_tag): bool {
        global $post;
        return (is_a($post, 'WP_Post') && has_shortcode($post->post_content, $shortcode_tag));
    }
}
