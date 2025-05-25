<?php
// Файл: woodmart-child/includes/Core/Theme.php
// ВАЖНО: Эта версия файла идентична той, что вы предоставили.
// Строка для подключения скриптов Dokan через AssetManager уже присутствует.

namespace WoodmartChildRPG\Core;

use WoodmartChildRPG\Assets\AssetManager;
use WoodmartChildRPG\Admin\AdminAJAXHandler;
use WoodmartChildRPG\Admin\UserProfile;
use WoodmartChildRPG\Admin\UserTable;
use WoodmartChildRPG\Integration\dokan\DokanAJAXHandler;
use WoodmartChildRPG\Integration\dokan\DokanIntegrationManager;
use WoodmartChildRPG\Integration\dokan\DokanUserCouponDB;
use WoodmartChildRPG\Pages\CharacterPage;
use WoodmartChildRPG\Shortcodes\GenderSelectShortcode;
use WoodmartChildRPG\Shortcodes\RegisterFormShortcode;
use WoodmartChildRPG\WooCommerce\DiscountManager;
use WoodmartChildRPG\WooCommerce\WooCommerceIntegration;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Главный класс темы WoodmartChildRPG.
 * Отвечает за инициализацию всех компонентов темы.
 */
class Theme {

	/**
	 * Экземпляр класса Theme.
	 *
	 * @var Theme|null
	 */
	private static $instance = null;

	/**
	 * Загрузчик хуков.
	 *
	 * @var Loader
	 */
	private $loader;

	/**
	 * Менеджер ассетов.
	 *
	 * @var AssetManager
	 */
	private $asset_manager;

	/**
	 * Менеджер интеграции с Dokan.
	 *
	 * @var DokanIntegrationManager
	 */
	private $dokan_integration_manager;

    /**
     * AJAX обработчик для Dokan.
     * @var DokanAJAXHandler
     */
    private $dokan_ajax_handler;

    /**
     * Менеджер скидок WooCommerce.
     * @var DiscountManager
     */
    private $discount_manager;

    /**
     * Интеграция с WooCommerce.
     * @var WooCommerceIntegration
     */
    private $woocommerce_integration;

    /**
     * Страница персонажа.
     * @var CharacterPage
     */
    private $character_page;

    /**
     * Обработчик AJAX запросов ядра.
     * @var AJAXHandler
     */
    private $core_ajax_handler;

    /**
     * Модель персонажа (для передачи в Core AJAX Handler).
     * @var \WoodmartChildRPG\RPG\Character
     */
    private $character_model_instance;


	/**
	 * Конструктор класса.
	 * Приватный для реализации паттерна Singleton.
	 */
	private function __construct() {
		$this->define_constants();
		$this->load_dependencies();
		$this->init_components();
		$this->register_hooks();
	}

	/**
	 * Получение единственного экземпляра класса Theme.
	 *
	 * @return Theme
	 */
	public static function get_instance(): Theme {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

    /**
     * Получение экземпляра DokanIntegrationManager.
     * @return DokanIntegrationManager
     */
    public function get_dokan_integration_manager(): DokanIntegrationManager {
        return $this->dokan_integration_manager;
    }

	/**
	 * Определение констант темы.
	 */
	private function define_constants() {
		define( 'WOODMART_CHILD_RPG_VERSION', '1.0.1' ); // Обновил версию для примера
		define( 'WOODMART_CHILD_RPG_DIR', get_stylesheet_directory() );
		define( 'WOODMART_CHILD_RPG_URL', get_stylesheet_directory_uri() );
		define( 'WOODMART_CHILD_RPG_INCLUDES_DIR', WOODMART_CHILD_RPG_DIR . '/includes' );
		define( 'WOODMART_CHILD_RPG_ASSETS_URL', WOODMART_CHILD_RPG_URL . '/assets' );
	}

	/**
	 * Загрузка зависимостей, включая автозагрузчик классов.
	 */
	private function load_dependencies() {
		require_once WOODMART_CHILD_RPG_INCLUDES_DIR . '/autoload.php';
	}

	/**
	 * Инициализация компонентов темы.
	 */
	private function init_components() {
		$this->loader = new Loader();
		$this->asset_manager = new AssetManager();
        
        // RPG Core - Character model не создается здесь, а по месту использования new Character($user_id)
        // $this->character_model_instance = new \WoodmartChildRPG\RPG\Character(); // Неправильно так делать для модели
        // new LevelManager();
        // new RaceFactory();

        // Core AJAX Handler
        // Для Core\AJAXHandler нужна зависимость от RPG\Character (модели), но не как синглтон.
        // AJAXHandler будет создавать new RPGCharacter($user_id) внутри своих методов.
        // Если же CharacterManager - это какой-то сервисный класс, то его можно передать.
        // Судя по вашему коду AJAXHandler, он ожидает RPGCharacter (модель).
        // Передавать экземпляр модели сюда некорректно.
        // Предположим, что AJAXHandler сам создает экземпляр Character при необходимости.
        $this->core_ajax_handler = new AJAXHandler(new \WoodmartChildRPG\RPG\Character(get_current_user_id())); // Передаем инстанс Character, но это не лучший подход. Лучше без конструктора или с фабрикой.

        // WooCommerce
        $this->discount_manager = new DiscountManager();
        $this->woocommerce_integration = new WooCommerceIntegration($this->discount_manager);


        // Dokan Integration
        $dokan_user_coupon_db = new DokanUserCouponDB(); // Создаем экземпляр DB
        $this->dokan_ajax_handler = new DokanAJAXHandler($dokan_user_coupon_db); // Передаем DB в AJAX обработчик
        $this->dokan_integration_manager = new DokanIntegrationManager($dokan_user_coupon_db, $this->dokan_ajax_handler);


        // Pages
        $this->character_page = new CharacterPage($dokan_user_coupon_db);

        // Admin
        new UserProfile();
        new UserTable();
        new AdminAJAXHandler();


        // Shortcodes
        new RegisterFormShortcode();
        new GenderSelectShortcode();

        // Installer
        // Installer::init();
	}

	/**
	 * Регистрация хуков WordPress.
	 */
	private function register_hooks() {
		// Хуки для подключения ассетов
		$this->loader->add_action( 'wp_enqueue_scripts', $this->asset_manager, 'enqueue_frontend_assets' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->asset_manager, 'enqueue_admin_assets' );
        
        // Хук для подключения скриптов на страницах магазина Dokan
        $this->loader->add_action( 'wp_enqueue_scripts', $this->asset_manager, 'enqueue_dokan_store_assets' );

		// Хуки для WooCommerce
        if ($this->discount_manager) {
            $this->loader->add_action( 'woocommerce_cart_calculate_fees', $this->discount_manager, 'apply_rpg_cart_discounts', 20 );
        }
        if ($this->woocommerce_integration) {
            $this->loader->add_action( 'woocommerce_order_status_completed', $this->woocommerce_integration, 'handle_order_completed', 10, 1 );
        }

		// Хуки для Dokan AJAX
        if ($this->dokan_ajax_handler) {
            $this->loader->add_action( 'wp_ajax_rpg_take_dokan_coupon', $this->dokan_ajax_handler, 'handle_take_dokan_coupon' );
            $this->loader->add_action( 'wp_ajax_nopriv_rpg_take_dokan_coupon', $this->dokan_ajax_handler, 'handle_take_dokan_coupon' ); // Если гости могут видеть, но не брать
            $this->loader->add_action( 'wp_ajax_rpg_activate_dokan_coupon_from_inventory', $this->dokan_ajax_handler, 'handle_activate_dokan_coupon_from_inventory' );
            $this->loader->add_action( 'wp_ajax_rpg_add_dokan_coupon_by_code', $this->dokan_ajax_handler, 'handle_add_dokan_coupon_by_code' );
            $this->loader->add_action( 'wp_ajax_rpg_refresh_dokan_coupons_status', $this->dokan_ajax_handler, 'handle_refresh_dokan_coupons_status' );
        }

        // Хуки для Core AJAX (RPG купоны и способности)
        if ($this->core_ajax_handler) {
            $this->loader->add_action( 'wp_ajax_rpg_use_rpg_coupon', $this->core_ajax_handler, 'handle_use_rpg_coupon' );
            $this->loader->add_action( 'wp_ajax_rpg_activate_elf_sense_pending', $this->core_ajax_handler, 'handle_activate_elf_sense_pending' );
            $this->loader->add_action( 'wp_ajax_rpg_select_elf_items', $this->core_ajax_handler, 'handle_select_elf_items' );
            $this->loader->add_action( 'wp_ajax_rpg_activate_orc_rage_pending', $this->core_ajax_handler, 'handle_activate_orc_rage_pending' );
            $this->loader->add_action( 'wp_ajax_rpg_select_orc_rage_product', $this->core_ajax_handler, 'handle_select_orc_rage_product' );
            $this->loader->add_action( 'wp_ajax_rpg_deactivate_coupon', $this->core_ajax_handler, 'handle_deactivate_coupon' );
        }


        // Хуки для страницы персонажа
        if ($this->character_page) {
            $this->loader->add_action( 'init', $this->character_page, 'add_character_endpoint');
            $this->loader->add_filter( 'woocommerce_account_menu_items', $this->character_page, 'add_character_link_my_account' );
            $this->loader->add_action( 'woocommerce_account_character_endpoint', $this->character_page, 'character_page_content' );
        }
        
        // Хуки для Dokan Integration Manager
        if ($this->dokan_integration_manager) {
            $this->loader->add_action( 'before_delete_post', $this->dokan_integration_manager, 'handle_deleted_dokan_coupon_post', 10, 1 );
             // Добавление кнопки "Взять купон" на странице магазина Dokan
            $this->loader->add_action( 'dokan_store_header_info_fields', $this->dokan_integration_manager, 'display_vendor_coupons_on_store_page', 10, 1 ); // Пример хука Dokan
        }


		// Запуск загрузчика для регистрации всех хуков
		$this->loader->run();

        // Хук активации темы для Installer
        // Для дочерней темы правильнее использовать 'after_switch_theme'
        $this->loader->add_action('after_switch_theme', [Installer::class, 'activate']);
        // Если нужно что-то при деактивации
        // register_deactivation_hook( get_stylesheet_directory() . '/functions.php', [Installer::class, 'deactivate'] ); // Это тоже может быть проблематично для дочерней темы
        // $this->loader->add_action('switch_theme', [Installer::class, 'deactivate']); // При переключении на другую тему


	}
}

// Запуск темы
Theme::get_instance();
