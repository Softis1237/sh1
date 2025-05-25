/**
 * JavaScript для страницы персонажа (/my-account/character)
 * и для страницы магазина Dokan (в части "взять купон").
 * Обрабатывает активацию/деактивацию купонов и способностей.
 * Файл: assets/js/rpg-account.js
 */
jQuery(document).ready(function($) {
    'use strict';

    // Глобальные настройки из wp_localize_script (переменная rpgSettings)
    // Эти настройки могут быть разными в зависимости от страницы (аккаунт или магазин Dokan)
    const settings = typeof rpgSettings !== 'undefined' ? rpgSettings : {};
    const ajaxUrl = settings.ajax_url || '/wp-admin/admin-ajax.php';
    
    // Nonces - важно использовать правильный nonce для каждого типа действия
    const nonceRpgActions = settings.nonce_rpg_actions || ''; // Для способностей, RPG купонов
    const nonceDokanCoupons = settings.nonce_dokan_coupons || ''; // Для Dokan купонов (взять, активировать)

    // Текстовые строки для сообщений
    const i18n = settings.text || {};
    const errorNetwork = i18n.error_network || 'Ошибка сети. Пожалуйста, попробуйте еще раз.';
    const errorGeneric = i18n.error_generic || 'Произошла непредвиденная ошибка.';
    const abilityActivatedText = i18n.ability_activated || 'Способность активирована!';
    const abilitySelectItemText = i18n.ability_select_item || 'Выберите товар(ы) в корзине для применения способности.';
    const confirmDeactivateText = i18n.confirm_deactivate || 'Вы уверены, что хотите деактивировать этот купон?';
    const couponProcessingText = i18n.coupon_processing || 'Обработка...';
    const couponTakenText = i18n.coupon_taken || 'Уже в инвентаре';
    const takeCouponTextDefault = __('Взять купон', 'woodmart-child'); // Нужна функция __() или передавать из PHP

    // Функция для отображения сообщений (упрощенная, можно заменить на вашу систему уведомлений)
    function showUserMessage(message, type = 'info', $context = null) {
        // Пример: если есть WoodmartTheme.showNotices
        if (typeof WoodmartTheme !== 'undefined' && WoodmartTheme.showNotices) {
            WoodmartTheme.showNotices(message, type);
        } else {
            // Фоллбэк или ваша кастомная логика отображения
            let $messageBox;
            if ($context && $context.length) {
                $messageBox = $context.find('.rpg-ajax-message');
                if (!$messageBox.length) {
                    $context.append('<div class="rpg-ajax-message"></div>');
                    $messageBox = $context.find('.rpg-ajax-message');
                }
            } else {
                $messageBox = $('.rpg-global-message-box'); // Предполагаем наличие глобального контейнера
                if (!$messageBox.length) {
                    $('body').prepend('<div class="rpg-global-message-box" style="position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:9999; padding:10px; background:white; border:1px solid #ccc;"></div>');
                    $messageBox = $('.rpg-global-message-box');
                }
            }
            $messageBox.removeClass('success error info').addClass(type).html(message).fadeIn();
            setTimeout(function() { $messageBox.fadeOut(); }, 5000);
        }
    }


    // --- ОБРАБОТЧИКИ ДЛЯ КУПОНОВ ПРОДАВЦОВ (DOKAN) ---
    // Этот обработчик будет работать и на странице магазина Dokan, и на странице аккаунта, если там есть такие кнопки.
    $(document).on('click', '.rpg-dokan-take-coupon-button', function(e) {
        e.preventDefault();
        const $button = $(this);
        const couponId = $button.data('coupon-id');
        const originalCouponCode = $button.data('coupon-code'); // Ожидаем, что этот атрибут есть

        if ($button.hasClass('disabled') || $button.hasClass('processing')) {
            return;
        }

        if (!couponId || !originalCouponCode) {
            showUserMessage('Ошибка: ID купона или код не найдены.', 'error', $button.parent());
            console.error('Take Dokan Coupon: Missing coupon-id or coupon-code data attribute.');
            return;
        }
        if (!nonceDokanCoupons) {
            showUserMessage('Ошибка: Nonce безопасности не найден.', 'error', $button.parent());
            console.error('Take Dokan Coupon: Missing nonceDokanCoupons.');
            return;
        }

        $button.addClass('processing').prop('disabled', true).text(couponProcessingText);
        let $messageContainer = $button.siblings('.rpg-coupon-message');
        if (!$messageContainer.length) {
            $button.after('<span class="rpg-coupon-message" style="display:block; margin-top:5px;"></span>');
            $messageContainer = $button.siblings('.rpg-coupon-message');
        }
        $messageContainer.empty().hide();


        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'rpg_take_dokan_coupon',
                coupon_id: couponId,
                original_coupon_code: originalCouponCode, // Передаем оригинальный код
                _ajax_nonce: nonceDokanCoupons // Используем nonce для Dokan купонов
            },
            success: function(response) {
                $button.removeClass('processing');
                if (response && response.success) {
                    $button.addClass('disabled').text(couponTakenText);
                    showUserMessage(response.data.message || i18n.take_coupon_success || 'Купон добавлен!', 'success', $messageContainer);
                } else {
                    $button.prop('disabled', false).text( $button.data('original-text') || takeCouponTextDefault );
                    showUserMessage(response.data.message || i18n.take_coupon_error || errorGeneric, 'error', $messageContainer);
                }
            },
            error: function() {
                $button.removeClass('processing').prop('disabled', false).text( $button.data('original-text') || takeCouponTextDefault );
                showUserMessage(errorNetwork, 'error', $messageContainer);
            }
        });
    });

    // Активировать купон Dokan из инвентаря (на странице персонажа)
    $(document).on('click', '.rpg-activate-inventory-dokan-coupon-button', function(e) {
        e.preventDefault();
        const $button = $(this);
        const couponId = $button.data('coupon-id'); // ID поста купона
        // const inventoryCouponId = $button.data('inventory-id'); // ID записи в таблице инвентаря, если нужно

        if ($button.hasClass('processing')) return;

        if (!couponId) {
            showUserMessage('Ошибка: ID купона не найден.', 'error', $button.parent());
            return;
        }
         if (!nonceDokanCoupons) {
            showUserMessage('Ошибка: Nonce безопасности не найден.', 'error', $button.parent());
            return;
        }

        const originalButtonText = $button.text();
        $button.addClass('processing').prop('disabled', true).text(couponProcessingText);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'rpg_activate_dokan_coupon_from_inventory',
                coupon_id_to_activate: couponId, // Передаем ID поста купона
                _ajax_nonce: nonceDokanCoupons
            },
            success: function(response) {
                $button.removeClass('processing');
                if (response && response.success) {
                    showUserMessage(response.data.message || i18n.activate_coupon_success || 'Купон активирован!', 'success', $button.parent());
                    // Обновить UI: пометить этот купон как активный, другие как неактивные
                    $('.rpg-activate-inventory-dokan-coupon-button.active-coupon').removeClass('active-coupon').text('Активировать'); // Сброс других
                    $button.addClass('active-coupon').text('Активен'); // Пометить текущий
                    // $button.prop('disabled', false); // Можно оставить активным для деактивации или перезагрузить страницу

                    if (response.data.force_cart_refresh) {
                        $(document.body).trigger('wc_update_cart');
                        // Для страницы оформления заказа может потребоваться $(document.body).trigger('update_checkout');
                    }
                     setTimeout(function() { window.location.reload(); }, 2000); // Перезагрузка для обновления статусов

                } else {
                    $button.prop('disabled', false).text(originalButtonText);
                    showUserMessage(response.data.message || i18n.activate_coupon_error || errorGeneric, 'error', $button.parent());
                    if (response.data.coupon_removed) {
                        $button.closest('.user-inventory-coupon-item').remove(); // Обновите селектор, если нужно
                    }
                }
            },
            error: function() {
                $button.removeClass('processing').prop('disabled', false).text(originalButtonText);
                showUserMessage(errorNetwork, 'error', $button.parent());
            }
        });
    });

    // Обновить статус купонов Dokan в инвентаре (на странице персонажа)
    $('#rpg-refresh-dokan-coupons-status-button').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        if ($button.hasClass('processing')) return;

        if (!nonceDokanCoupons) { // Используем тот же nonce для действий с купонами Dokan
            showUserMessage('Ошибка: Nonce безопасности не найден.', 'error', $button.parent());
            return;
        }
        const originalButtonText = $button.text();
        $button.addClass('processing').prop('disabled', true).text(couponProcessingText);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'rpg_refresh_dokan_coupons_status',
                _ajax_nonce: nonceDokanCoupons
            },
            success: function(response) {
                $button.removeClass('processing').prop('disabled', false).text(originalButtonText);
                if (response && response.success) {
                    showUserMessage(response.data.message || 'Статус купонов обновлен.', 'success', $button.parent());
                    if (response.data.reload_page) {
                        setTimeout(function() { window.location.reload(); }, 1500);
                    }
                } else {
                    showUserMessage(response.data.message || errorGeneric, 'error', $button.parent());
                }
            },
            error: function() {
                $button.removeClass('processing').prop('disabled', false).text(originalButtonText);
                showUserMessage(errorNetwork, 'error', $button.parent());
            }
        });
    });


    // --- ОБРАБОТЧИКИ ДЛЯ ОБЫЧНЫХ RPG КУПОНОВ И СПОСОБНОСТЕЙ (Страница Аккаунта) ---
    // Активация RPG купонов (из инвентаря RPG)
    $('.rpg-activate-rpg-coupon-button').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const couponIndex = $button.data('coupon-index');

        if (typeof couponIndex === 'undefined') {
            showUserMessage('Ошибка: Индекс купона не найден.', 'error', $button.parent());
            return;
        }
        if (!nonceRpgActions) {
            showUserMessage('Ошибка: Nonce безопасности не найден.', 'error', $button.parent());
            return;
        }
        const originalButtonText = $button.text();
        $button.addClass('processing').prop('disabled', true).text(couponProcessingText);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'rpg_use_rpg_coupon', // Это действие из Core\AJAXHandler
                index: couponIndex,
                _ajax_nonce: nonceRpgActions
            },
            success: function(response) {
                $button.removeClass('processing');
                if (response && response.success) {
                    showUserMessage(response.data.message || 'RPG купон активирован!', 'success', $button.parent());
                     $('.rpg-activate-rpg-coupon-button.active-coupon').removeClass('active-coupon').text('Активировать');
                    $button.addClass('active-coupon').text('Активен');
                    if (response.data.force_cart_refresh) {
                        $(document.body).trigger('wc_update_cart');
                    }
                     setTimeout(function() { window.location.reload(); }, 2000);
                } else {
                    $button.prop('disabled', false).text(originalButtonText);
                    showUserMessage(response.data.message || errorGeneric, 'error', $button.parent());
                }
            },
            error: function() {
                $button.removeClass('processing').prop('disabled', false).text(originalButtonText);
                showUserMessage(errorNetwork, 'error', $button.parent());
            }
        });
    });

    // Активация "Чутья" (Эльфы) - первая стадия
    $('#rpg-activate-elf-sense-button').on('click', function(e) {
        e.preventDefault();
        handleAbilityActivation($(this), 'rpg_activate_elf_sense_pending', nonceRpgActions, abilitySelectItemText);
    });

    // Активация "Ярости" (Орки) - первая стадия
    $('#rpg-activate_orc_rage-button').on('click', function(e) { // ID кнопки должен быть корректным
        e.preventDefault();
        handleAbilityActivation($(this), 'rpg_activate_orc_rage_pending', nonceRpgActions, abilitySelectItemText);
    });

    function handleAbilityActivation($button, ajaxAction, nonce, successButtonText) {
        if ($button.hasClass('processing')) return;
        if (!nonce) {
            showUserMessage('Ошибка: Nonce безопасности не найден.', 'error', $button.parent());
            return;
        }
        const originalButtonText = $button.text();
        $button.addClass('processing').prop('disabled', true).text(couponProcessingText);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: ajaxAction,
                _ajax_nonce: nonce
            },
            success: function(response) {
                $button.removeClass('processing');
                if (response && response.success) {
                    showUserMessage(response.data.message || abilityActivatedText, 'success', $button.parent());
                    $button.text(successButtonText); // Не деактивируем, а меняем текст на "Выберите в корзине"
                    // Не перезагружаем страницу здесь, пользователь должен перейти в корзину
                } else {
                    $button.prop('disabled', false).text(originalButtonText);
                    showUserMessage(response.data.message || errorGeneric, 'error', $button.parent());
                }
            },
            error: function() {
                $button.removeClass('processing').prop('disabled', false).text(originalButtonText);
                showUserMessage(errorNetwork, 'error', $button.parent());
            }
        });
    }

    // Деактивация активного купона (любого типа)
    $(document).on('click', '.rpg-deactivate-active-coupon-button', function(e) {
        e.preventDefault();
        const $button = $(this);
        const couponType = $button.data('coupon-type'); // 'rpg_item', 'rpg_cart', 'dokan_vendor'

        if (!couponType) {
            showUserMessage('Не удалось определить тип купона для деактивации.', 'error', $button.parent());
            return;
        }
        if (!nonceRpgActions && couponType.startsWith('rpg_')) { // Для RPG купонов нужен rpg_ajax_nonce
             showUserMessage('Ошибка: Nonce RPG не найден.', 'error', $button.parent());
            return;
        }
         if (!nonceDokanCoupons && couponType === 'dokan_vendor') { // Для Dokan купонов нужен свой nonce
             showUserMessage('Ошибка: Nonce Dokan не найден.', 'error', $button.parent());
            return;
        }

        if (!confirm(confirmDeactivateText)) {
            return;
        }
        const originalButtonText = $button.text();
        $button.addClass('processing').prop('disabled', true).text('Деактивация...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'rpg_deactivate_coupon', // Общий обработчик деактивации
                coupon_type: couponType,
                _ajax_nonce: (couponType === 'dokan_vendor' ? nonceDokanCoupons : nonceRpgActions)
            },
            success: function(response) {
                $button.removeClass('processing').prop('disabled', false).text(originalButtonText);
                if (response && response.success) {
                    showUserMessage(response.data.message || 'Купон деактивирован.', 'success', $button.parent());
                    if (response.data.force_cart_refresh) {
                        $(document.body).trigger('wc_update_cart');
                    }
                    setTimeout(function() { window.location.reload(); }, 1500);
                } else {
                    showUserMessage(response.data.message || errorGeneric, 'error', $button.parent());
                }
            },
            error: function() {
                $button.removeClass('processing').prop('disabled', false).text(originalButtonText);
                showUserMessage(errorNetwork, 'error', $button.parent());
            }
        });
    });

    // Вспомогательная функция для получения текста локализации (если не используется глобальный объект i18n)
    function __(textKey, domain) {
        if (settings && settings.i18n && settings.i18n[textKey]) {
            return settings.i18n[textKey];
        }
        // Фоллбэк, если строка не найдена
        return textKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

});
