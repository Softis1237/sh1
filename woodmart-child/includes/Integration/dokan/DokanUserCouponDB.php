<?php
// Файл: woodmart-child/includes/Integration/dokan/DokanUserCouponDB.php
// ВАЖНО: Эта версия файла идентична той, что вы предоставили.
// Изменения для original_coupon_code уже были учтены.

namespace WoodmartChildRPG\Integration\dokan;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Класс DokanUserCouponDB.
 * Отвечает за взаимодействие с базой данных для хранения
 * информации о купонах продавцов, которые пользователи "взяли" в свой инвентарь.
 */
class DokanUserCouponDB {

	private $table_name;
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $this->wpdb->prefix . 'rpg_dokan_user_coupons_inventory';
	}

    /**
     * Создание таблицы при активации темы/плагина.
     * Этот метод должен вызываться из Installer.php.
     */
    public function create_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            coupon_id BIGINT(20) UNSIGNED NOT NULL,
            original_coupon_code VARCHAR(255) NOT NULL,
            date_added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_coupon (user_id, coupon_id),
            KEY user_id (user_id),
            KEY coupon_id (coupon_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

	/**
	 * Добавляет купон в инвентарь пользователя.
	 *
	 * @param int    $user_id ID пользователя.
	 * @param int    $coupon_id ID поста купона (shop_coupon).
     * @param string $original_coupon_code Оригинальный код купона на момент добавления.
	 * @return bool True в случае успеха, false в случае ошибки.
	 */
	public function add_coupon_to_inventory( int $user_id, int $coupon_id, string $original_coupon_code ): bool {
		if ( ! $user_id || ! $coupon_id || empty($original_coupon_code) ) {
			return false;
		}

        if ($this->user_has_coupon($user_id, $coupon_id)) {
            // Если купон уже есть, можно обновить original_coupon_code, если он изменился,
            // но это усложнит логику. Пока считаем, что если есть, то ничего не делаем.
            // Либо можно удалить старую запись и добавить новую, если это предпочтительнее.
            // Для простоты, если купон уже есть, считаем операцию успешной.
            return true;
        }

		$result = $this->wpdb->insert(
			$this->table_name,
			[
				'user_id'              => $user_id,
				'coupon_id'            => $coupon_id,
                'original_coupon_code' => sanitize_text_field($original_coupon_code),
				'date_added'           => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s' ]
		);

		return (bool) $result;
	}

	/**
	 * Удаляет купон из инвентаря пользователя.
	 * Если $coupon_id равен 0, удаляются все купоны пользователя $user_id.
     * Если $user_id равен 0, удаляются все записи для $coupon_id (например, при удалении поста купона).
	 *
	 * @param int $user_id ID пользователя.
	 * @param int $coupon_id ID поста купона.
	 * @return bool True в случае успеха, false в случае ошибки.
	 */
	public function remove_coupon_from_inventory( int $user_id, int $coupon_id ): bool {
		if ( ! $user_id && ! $coupon_id ) {
			return false;
		}

        $where = [];
        $where_format = [];

        if ($user_id) {
            $where['user_id'] = $user_id;
            $where_format[] = '%d';
        }
        if ($coupon_id) {
            $where['coupon_id'] = $coupon_id;
            $where_format[] = '%d';
        }

        if (empty($where)) {
            return false;
        }

		$result = $this->wpdb->delete( $this->table_name, $where, $where_format );
		return (bool) $result;
	}

	/**
	 * Проверяет, есть ли указанный купон в инвентаре пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 * @param int $coupon_id ID поста купона.
	 * @return bool True если купон есть, иначе false.
	 */
	public function user_has_coupon( int $user_id, int $coupon_id ): bool {
		if ( ! $user_id || ! $coupon_id ) {
			return false;
		}

		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d AND coupon_id = %d",
				$user_id,
				$coupon_id
			)
		);
		return (bool) $count;
	}

    /**
     * Получает запись из инвентаря по ID пользователя и ID купона.
     *
     * @param int $user_id
     * @param int $coupon_id
     * @return object|null Объект с данными или null, если не найдено.
     */
    public function get_inventory_item(int $user_id, int $coupon_id): ?object {
        if ( ! $user_id || ! $coupon_id ) {
			return null;
		}
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE user_id = %d AND coupon_id = %d",
                $user_id,
                $coupon_id
            )
        );
    }

	/**
	 * Получает все купоны из инвентаря пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 * @return array Массив объектов купонов (ID поста купона и дата добавления, оригинальный код).
	 */
	public function get_user_coupons( int $user_id ): array {
		if ( ! $user_id ) {
			return [];
		}

		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				// Выбираем все поля, чтобы иметь доступ к original_coupon_code и id записи инвентаря
				"SELECT id, user_id, coupon_id, original_coupon_code, date_added FROM {$this->table_name} WHERE user_id = %d ORDER BY date_added DESC",
				$user_id
			)
		);
		return $results ?: [];
	}

    /**
     * Получает все купоны пользователя для проверки статуса.
     * Используется для периодической проверки валидности купонов в инвентаре.
     *
     * @param int $user_id ID пользователя.
     * @return array Массив объектов, содержащих как минимум 'coupon_id' и 'original_coupon_code'.
     */
    public function get_all_user_coupons_for_status_check(int $user_id): array {
        if (!$user_id) {
            return [];
        }
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, user_id, coupon_id, original_coupon_code FROM {$this->table_name} WHERE user_id = %d",
                $user_id
            )
        );
        return $results ?: [];
    }

    /**
     * Подсчитывает количество купонов в инвентаре пользователя.
     * @param int $user_id
     * @return int
     */
    public function count_user_inventory_coupons(int $user_id): int {
        if (!$user_id) {
            return 0;
        }
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(id) FROM {$this->table_name} WHERE user_id = %d",
                $user_id
            )
        );
    }
}
