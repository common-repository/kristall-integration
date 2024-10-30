(function($) {
  'use strict';

  var colorFieldsMap = {
    wooCheckoutButtonBgColor: 'bgColor',
    wooCheckoutButtonTxtColor: 'textColor',
    wooCheckoutButtonBorderColor: 'borderColor',
    wooCheckoutButtonBgColorHover: 'hoverBgColor',
    wooCheckoutButtonTxtColorHover: 'hoverTextColor',
    wooCheckoutButtonBorderColorHover: 'hoverBorderColor',
    wooCheckoutButtonBgColorActive: 'activeBgColor',
    wooCheckoutButtonTxtColorActive: 'activeTextColor',
    wooCheckoutButtonBgColorShadow: 'activeShadowColor',
    wooCheckoutButtonBorderColorActive: 'activeBorderColor',
  };

  // Записывает стили в HTML
  function setBtnStyles(colors) {
    // Получем элемент стилей. Если он не существует, то создаем его
    var styleEl = $('#kristall-integration__btns-preview-styles')[0];
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = 'kristall-integration__btns-preview-styles';
      $('head').append(styleEl);
    }

    styleEl.textContent =
      '#kristall-integration__btns-preview .kristall-integration__billing-info__tabs {' +
        '--krl-tab-btn-bg: ' + colors.bgColor + ';' +
        '--krl-tab-btn-text: ' + colors.textColor + ';' +
        '--krl-tab-btn-border: ' + colors.borderColor + ';' +
        '--krl-tab-btn-active-bg: ' + colors.activeBgColor + ';' +
        '--krl-tab-btn-active-text: ' + colors.activeTextColor + ';' +
        '--krl-tab-btn-active-border: ' + colors.activeBorderColor + ';' +
        '--krl-tab-btn-active-shadow: ' + colors.activeShadowColor + ';' +
        '--krl-tab-btn-hover-bg: ' + colors.hoverBgColor + ';' +
        '--krl-tab-btn-hover-text: ' + colors.hoverTextColor + ';' +
        '--krl-tab-btn-hover-border: ' + colors.hoverBorderColor + ';' +
      '} ';
  }

  function setColorFromInput(colors, $input) {
    var optionName = $input.attr('data-kristall-cp-option-name');
    var colorKey = colorFieldsMap[optionName];

    if (!colorKey) {
      return false;
    }

    colors[colorKey] = $input.val();
    return true;
  }

  function createDebounceFn(timeout) {
    var timerId = null;

    return function (cb) {
      if (timerId) {
        clearTimeout(timerId);
        timerId = null;
      }

      timerId = setTimeout(cb, timeout || 100);
    }
  }

  $(function() {
    // Инициализируем стили

    var previewColors = {};

    $('[data-kristall-cp-option-name]').each(function() {
      setColorFromInput(previewColors, $(this));
    });

    setBtnStyles(previewColors);

    // Инициализируем плагин для выбора цветов

    var debounceUpdate = createDebounceFn(50);

    $('.kristall-integration__color-picker').each(function() {
      $(this)
        .spectrum({
          type: 'component',
          showAlpha: true,
          preferredFormat: 'hex3',
          move: function() {
            var self = this;
            debounceUpdate(function() {
              if (setColorFromInput(previewColors, $(self))) {
                setBtnStyles(previewColors);
              }
            });
          }
        })
        .removeClass('spectrum with-add-on');
    });

    $('.kristall-integration_default_tabs-controls').each(function() {
      var $wrapper = $(this).find('> td').first();
      $(this).find('> th').remove();
      $wrapper.attr('colspan', '2');

      var $tabsWrapper = $wrapper.find('.kristall-integration__product_tab_list');

      if (!window.initKristallIntegrationTabSettings || !$tabsWrapper.length) {
        return;
      }

      // Инициализируем табы
      var tabs = window.initKristallIntegrationTabSettings($tabsWrapper, true);
    });

    $('.kristall-integration_shortcode_ids_toggle').each(function() {
      var $toggle = $(this);
      var $container = $toggle.parent().next('.form-table');
      $container.addClass('kristall-integration_shortcode_ids_controls_wrapper');

      $toggle.on('click', function(e) {
        e.preventDefault();
        $toggle.toggleClass('show');

        if ($toggle.hasClass('show')) {
          $container.addClass('show');
        } else {
          $container.removeClass('show');
        }
      });

    });
  });

  // Текст помощи
  $(function() {
    if (!window.kristallIntegrationModal) {
      $('.krl-input-help').remove();
      return;
    }

    var pluginUrl = kristallIntegrationConfig.pluginUrl;

    $('#krl_api_url_help').on('click', function(e) {
      e.preventDefault();

      var content = '';
      
      content += '<div class="krl-input-help-header">Как определить URL-адрес МБС Кристалл?<a href="#" class="krl-input-help-close">&times;</a></div>';
      content += '<div class="krl-input-help-cols">';
      content += '<div><img src="' + pluginUrl + 'img/help_login_1.jpg" /><div>Войдите в <strong>Вашу учетную запись</strong> МБС Кристалл.</div></div>';
      content += '<div><img src="' + pluginUrl + 'img/help_url_2.jpg" /><strong>В адресной строке браузера</strong> выделите и скопируйте URL-адрес. В примере выше это «https://npokristal.ru/».</div>';
      content += '<div><img src="' + pluginUrl + 'img/help_url_paste.jpg" />Вставьте скопированный адрес в поле <strong>«URL-aдрес МБС Кристалл»</strong> в настройках плагина Кристалл Интеграция.</div>';
      content += '</div>';

      var $content = $('<div class="krl-input-help-content">' + content + '</div>');
      var $closeBtn = $content.find('.krl-input-help-close');

      var modal = kristallIntegrationModal($content);
      $closeBtn.on('click', function(e) {
        e.preventDefault();
        $closeBtn.off();
        modal.close();
      });
    });

    $('#krl_api_key_help').on('click', function(e) {
      e.preventDefault();

      var content = '';
      
      content += '<div class="krl-input-help-header">Где найти мой API-ключ?<a href="#" class="krl-input-help-close">&times;</a></div>';
      content += '<div class="krl-input-help-cols">';
      content += '<div><img src="' + pluginUrl + 'img/help_login_1.jpg" /><div>Войдите в <strong>Вашу учетную запись юридического лица или ИП</strong> в МБС Кристалл.</div></div>';
      content += '<div><img src="' + pluginUrl + 'img/help_account_settings.jpg" />Перейдите в <strong>настройки Вашей учетной записи</strong>. Главное меню &rarr; «Личный кабинет» &rarr; «Мои настройки».</div>';
      content += '<div><img src="' + pluginUrl + 'img/help_api_input.jpg" />Скопируйте Ваш ключ из поля <strong>«API ключ»</strong> в разделе <strong>«Настройки доступа»</strong>.</div>';
      content += '<div><img src="' + pluginUrl + 'img/help_key_paste.jpg" />Вставьте скопированный API-ключ в поле <strong>«API-ключ»</strong> в настройках плагина Кристалл Интеграция.</div>';
      content += '</div>';

      var $content = $('<div class="krl-input-help-content">' + content + '</div>');
      var $closeBtn = $content.find('.krl-input-help-close');

      var modal = kristallIntegrationModal($content);
      $closeBtn.on('click', function(e) {
        e.preventDefault();
        $closeBtn.off();
        modal.close();
      });
    });
  });
})(jQuery);
