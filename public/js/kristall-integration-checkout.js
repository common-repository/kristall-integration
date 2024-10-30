(function($) {
  'use strict';

  function htmlDecode(input) {
    var doc = new DOMParser().parseFromString(input, "text/html");
    return doc.documentElement.textContent;
  }

  var resendTimeoutId = null;
  var siteName = '';
  var tabNames = [
    'personal',
    'organization'
  ];

  function scrollToNotices() {
    var scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');
    if (!scrollElement.length) {
      scrollElement = $('.form.checkout');
    }
    $.scroll_to_notices(scrollElement);
  }

  function showErrors(errors) {
    if (!errors || !errors.length) return clearErrors();
    $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
    var $checkoutForm = $('form.checkout');
    $checkoutForm.prepend(
      '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><ul class="woocommerce-error" role="alert">' +
        errors.map(function(error) { return '<li>' + error + '</li>' }).join('') +
      '</ul></div>'
    );
    $checkoutForm.removeClass('processing').unblock();
    $checkoutForm.find('.input-text, select, input:checkbox').trigger('validate').blur();
    scrollToNotices();
    $(document.body).trigger('checkout_error');
  }

  function clearErrors() {
    $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
  }

  function showRequestErrors(errors) {
    var display = [];
    for (const errKey in errors) {
      if (!errors.hasOwnProperty(errKey)) continue;

      var err = errors[errKey];
      if (!err) continue;

      display.push(typeof err === 'string' ? err : err[0])
    }
    showErrors(display);
  }

  function onRequestError(_xhr, cb) {
    showErrors(['Ошибка обработки данных. Попробуйте еще раз.']);
    if (cb) cb();
  }

  function doApiRequest(endpoint, requestData, done, final) {
    $.ajax({
      method: 'POST',
      url: '/?wc-ajax=' + endpoint,
      data: requestData,
      cache: false,
      xhrFields: {
        withCredentials: true
      }
    }).done(function(data) {
      if (data) {
        if (final) final();
  
        done(data);
      } else {
        onRequestError(null, final);
      }
    }).fail(function (xhr) {
      onRequestError(xhr, final);
    });
  }

  function startResendTimeout(seconds) {
    var $parent = $('.kristall-integration__verification__resend');
    var $el = $('.kristall-integration__verification__resend_elapsed_time');

    if (!$parent.length) return;
    if (resendTimeoutId) {
      clearInterval(resendTimeoutId);
      resendTimeoutId = null;
    }

    $parent.addClass('disabled has-timeout');

    function printElapsedTime() {
      var mins = Math.floor(seconds / 60);
      var secs = seconds - (mins * 60);
      $el.text('(' + (mins ? (mins > 9 ? mins : '0' + mins) : '00') + ':' + (secs ? (secs > 9 ? secs : '0' + secs) : '00') + ')');
    }

    printElapsedTime();
    resendTimeoutId = setInterval(function() {
      seconds--;
      if (seconds > 0) {
        return printElapsedTime();
      }
      $parent.removeClass('disabled has-timeout');
      clearInterval(resendTimeoutId);
      resendTimeoutId = null;
    }, 1000);
  }

  function validateCheckoutForm(highlight, target) {
    var isValid = true;

    if (target) {
      target = Array.isArray(target) ? target : [target];
    } else {
      target = [];
    }

    function invalid($field) {
      isValid = false;
      if (highlight) $field.addClass('krl-invalid');
    }

    function valid($field) {
      if (highlight) $field.removeClass('krl-invalid');
    }

    if (!target.length || target.indexOf('phone') != -1) {
      var $phoneInp = $('input[name="billing_phone"]');
      var phoneNum = ($phoneInp.val() || '').replace(/[^\d]/g, '');
      if (phoneNum.length < 11 || phoneNum.length > 15) {
        invalid($phoneInp);
      } else {
        valid($phoneInp);
      }  
    }

    var $clTypeEl = $('.client_info.kristall-integration__billing-info__tabs');
    var isOrg = $('input[name="kristall-integration_client_type"]:checked').val() === 'organization';

    if (isOrg) {
      if (!target.length || target.indexOf('inn') != -1) {
        var $innInp = $('input[name="billing_inn"]');
        if (!/^\d{10}$|^\d{12}$/.test($innInp.val() || '')) {
          invalid($innInp);
        } else {
          valid($innInp);
        }
      }

      if (!target.length || target.indexOf('ogrn') != -1) {
        var $innOgrnInp = $('input[name="billing_ogrn"]');
        if (!/^\d{13}$|^\d{15}$/.test($innOgrnInp.val() || '')) {
          invalid($innOgrnInp);
        } else {
          valid($innOgrnInp);
        }
      }
    }

    if ($clTypeEl.hasClass('krl-flinfo-required')) {
      if (!target.length || target.indexOf('last_name') != -1) {
        var $lastNameInp = $('input[name="billing_last_name"]');
        if ($.trim($lastNameInp.val() || '') === '') {
          invalid($lastNameInp);
        } else {
          valid($lastNameInp);
        }
      }

      if (!target.length || target.indexOf('first_name') != -1) {
        var $firstNameInp = $('input[name="billing_first_name"]');
        if ($.trim($firstNameInp.val() || '') === '') {
          invalid($firstNameInp);
        } else {
          valid($firstNameInp);
        }
      }

      if (!target.length || target.indexOf('email') != -1) {
        var $emailInp = $('input[name="billing_email"]');
        if (!/^[^@ ]+@[^@ ]+\.\w+$/.test($.trim($emailInp.val() || ''))) {
          invalid($emailInp);
        } else {
          valid($emailInp);
        }
      }
    }

    if ($clTypeEl.hasClass('krl-ulinfo-required')) {
      if (!target.length || target.indexOf('company') != -1) {
        var $companyInp = $('input[name="billing_company"]');
        if ($.trim($companyInp.val() || '') === '') {
          invalid($companyInp);
        } else {
          valid($companyInp);
        }
      }
    }

    return isValid;
  }

  $(function() {
    // Табы
    var $tabs = $('.kristall-integration__billing-info__tabs.client_info');
    var timer = null;
    var timerId = null;
    var modal = null;
    var smsSupported = null;
    var smsResend = false;
    var checkData = {fl: false, ul: false};

    $tabs.on('change', 'input[type="radio"]', function() {
      $tabs.removeClass(tabNames.join(' ')).removeClass('krl-flinfo-confirmed krl-ulinfo-confirmed');
      $tabs.addClass(this.value);
      clearErrors();
      checkClientReg();
    });

    // Включаем базовую проверку полей
    $('body').on('change.krl-checkout', '.kristall-integration__checkout_cbase > .kristall-integration__checkout_field > input', function(e) {
      if (!validateCheckoutForm(false, $(e.target).attr('name').replace('billing_', ''))) {
        $(e.target).addClass('krl-invalid');
      }
      checkClientReg();
    });

    $('body').on('change.krl-checkout', ':not(.kristall-integration__checkout_cbase) > .kristall-integration__checkout_field > input:not([name=tel])', function(e) {
      $('#place_order').prop('disabled', 
        !validateCheckoutForm(true, $(e.target).attr('name').replace('billing_', ''))
      );
    });

    $('body').on('change.krl-checkout', '.kristall-integration__checkout_field > input[type=tel]', function(e) {
      if ($(e.target).val()) return;
      validateCheckoutForm(true, 'phone');
    });

    function checkClientReg() {
      var $submitBtn = $('#customer_details button').prop('disabled', true);
      var $typeEl = $('.client_info.kristall-integration__billing-info__tabs');
      var isOrg = $('input[name="kristall-integration_client_type"]:checked').val() === 'organization';

      function processData(type, data) {
        $typeEl.removeClass('krl-flinfo-required krl-ulinfo-required krl-flinfo-confirmed krl-ulinfo-confirmed');
        if (type == 'ul') {
          if (!data.ulExists) {
            $typeEl.addClass('krl-ulinfo-required krl-flinfo-required');
          } else if (!data.flExists) {
            $typeEl.addClass('krl-ulinfo-confirmed');
            $typeEl.addClass('krl-flinfo-required');
          } else {
            $typeEl.addClass('krl-flinfo-confirmed krl-ulinfo-confirmed');
            $submitBtn.prop('disabled', false);
          }
        } else {
          if (!data.flExists) {
            $typeEl.addClass('krl-flinfo-required');
          } else {
            $typeEl.addClass('krl-flinfo-confirmed');
            $submitBtn.prop('disabled', false);
          }
        }

        $('#place_order').prop('disabled', 
          !validateCheckoutForm()
        );
      }

      // Сначала проверяем данные в форме
      if (!validateCheckoutForm(false, isOrg ? ['inn', 'ogrn', 'phone'] : 'phone')) {
        $typeEl.removeClass('krl-flinfo-required krl-ulinfo-required krl-flinfo-confirmed krl-ulinfo-confirmed');
        return;
      }

      var phone = $('input[name="billing_phone"]').val();
      var inn = null;
      var ogrn = null;

      // Получаем ИНН и ОГРН[ИП]
      if (isOrg) {
        inn = $('input[name="billing_inn"]').val();
        ogrn = $('input[name="billing_ogrn"]').val();
      }
  
      // Если физлицо и телефон не изменился с прошлой проверки
      if (!isOrg) {
        if (checkData.fl && checkData.fl.phone === phone) {
          return processData('fl', checkData.fl);
        } else {
          checkData.fl = false;
        }

      // Если физлицо и телефон не изменился с прошлой проверки
      } else {
        if (checkData.ul && checkData.ul.phone === phone && checkData.ul.inn === inn && checkData.ul.ogrn === ogrn) {
          return processData('ul', checkData.ul);
        } else {
          checkData.ul = false;
        }
      }

      var mode = isOrg ? 'ul' : 'fl';

      // Иначе делаем запрос на сервер
      doCheckClientReg(mode, phone, inn, ogrn, function(result) {
        if (!result) return;
        checkData[mode] = result;
        processData(mode, result);
      });
    }

    // Проверяем клиента при загрузке страницы
    checkClientReg();

    function doCheckClientReg(mode, phone, inn, ogrn, cb) {
      checkData[mode] = false;

      var payload = {
        type: mode == 'ul' ? 'organization' : 'personal',
        phone: phone || phone,
        inn: inn || inn,
        ogrn: ogrn || ogrn,
      };

      var final = function() {
        $('#customer_details').removeClass('krl-checkout-loading').find('input').prop('disabled', false);
      }

      $('#customer_details').addClass('krl-checkout-loading').find('input').prop('disabled', true);
      $.ajax({
        method: 'POST',
        url: '/?wc-ajax=krl_v3_check_client_registration',
        data: payload,
        cache: false,
        xhrFields: {
          withCredentials: true
        }
      }).done(function(result) {
        final();

        if (result) {
          var fields = ['phone', 'inn', 'ogrn'];
          if (result.status === 'error') {
            if (result.invalidFields && result.invalidFields.length) {
              var focused = false;
              for (var field of fields) {
                var $fieldEl = $('input[name="billing_' + field + '"]');
                if (result.invalidFields.indexOf(field) !== -1) {
                  $fieldEl.addClass('krl-invalid');
                  if (!focused) {
                    $fieldEl.focus().select();
                    focused = true;
                  }
                } else {
                  $fieldEl.removeClass('krl-invalid');
                }
              }
              if (!result.error) result.error = 'Введены некорректрые данные.';
            }
            showErrors([result.error || 'Ошибка обработки данных. Попробуйте еще раз.']);
            return cb(null);
          } else {
            for (var field of fields) {
              $('input[name="billing_' + field + '"]').removeClass('krl-invalid');
            }
            clearErrors();
          }

          var data = result.data;
          var clInfo = {};

          if (mode == 'ul') {
            result = {
              phone: phone,
              inn: inn,
              ogrn: ogrn,
              ulExists: data.ulExists,
              flExists: data.flExists
            };
          } else {
            result = {
              phone: phone,
              flExists: data.flExists
            };
          }
          cb(result);
        } else {
          onRequestError(null, final);
        }
      }).fail(function (xhr) {
        onRequestError(xhr, final);
      });
    }

    // Инициализируем MaskedInput
    $('#billing_phone').mask('+7 (999) 999-9999', {
        completed: function() {
        validateCheckoutForm(true, 'phone');
        checkClientReg();  
      }
    });

    $("#billing_inn, #billing_ogrn").on('keypress blur', function(e) {
      if (e.type === 'keypress') {
        return !!String.fromCharCode(e.which).match(/^\d$/);
      }
      this.value = this.value.replace(/[^\d].+/, '');
    });

    function getFormData() {
      return $('form.checkout').serialize();
    }

    function processVerificationResponse(result) {
      var data = result.data;

      if (result.status == 'error') {
        data = { error: result.error };
      }

      var $error = $('.kristall-integration__verification__error');
      var $wrapper = $('.kristall-integration__verification__wrapper');
      var $message = $('.kristall-integration__verification__message');
      var $input = $('.kristall-integration__verification__input');
      $('.kristall-integration__verification__select-wrapper').remove();

      if (data.error) {
        $message.addClass('d-none');
        $error.text(data.error).removeClass('d-none');
      } else {
        $error.text('').addClass('d-none');

        if (data.resendTimeout) {
          startResendTimeout(data.resendTimeout);
        }  
      }

      if (!data.fatalError) {
        if (data.message) {
          $message.removeClass('d-none').text(data.message);
        }
        $input.val('');
        $wrapper.removeClass('d-none');
      } else {
        $wrapper.addClass('d-none');
      }

      var $resendWrapper = $('.kristall-integration__verification__resend');

      if (typeof(data.smsDeviverySupport) !== 'undefined' && smsSupported === null) {
        smsSupported = data.smsDeviverySupport;
      }

      if (smsSupported !== null) {
        if (smsSupported) {
          $resendWrapper.find('> a').first().removeClass('d-none');
        } else {
          $resendWrapper.find('> a').last().removeClass('d-none');
        }

        if (smsSupported && smsResend) {
          $resendWrapper.find('> span').removeClass('d-none');
          $resendWrapper.find('> a').last().removeClass('d-none');
        }
      }

      if (typeof data.codeDeliveryMethod === 'string') {
        $resendWrapper.find('> a').first().text(data.codeDeliveryMethod == 'sms' ? 'Повторить отправку СМС' : 'Отправить СМС');
        $resendWrapper.find('> a').last().text(data.codeDeliveryMethod != 'sms' ? 'Повторить заказ звонка' : 'Заказать звонок');
      }

      return !data.error;
    }

    function startTimer(data, $modal) {
      if (timer) timer.stop();

      if (!data || !data.error || !/\d+\s*(мин|сек)\./.test(data.error)) return null;

      var endTime = Math.floor(Date.now() / 1000);
      var curTimeStr = data.error;
      var err =
        data.error.replace(/((\d+)\s*мин\.\s*)/g, function (m, $1, $2) {
          curTimeStr = curTimeStr.replace($1, '<strong>' + $1 + '</strong>');
          endTime += Number($2) * 60;
          return '{m}';
        }).replace(/((\d+)\s*сек\.)/g, function (m, $1, $2) {
          curTimeStr = curTimeStr.replace($1, '<strong>' + $1 + '</strong>');
          endTime += Number($2);
          return '{s}';
        }).replace(/\{m\}\{s\}|\{m\}|\{s\}/, '<strong>{time}</strong>');

      $modal.find('.kristall-integration__verification__input').prop('disabled', true);
      $modal.find('.kristall-integration__verification__resend').addClass('disabled');
      $modal.find('.kristall-integration__verification__message').addClass('d-none');
      $modal.find('.kristall-integration__verification__error')
        .html(curTimeStr);

      timerId = setInterval(function() {
        var elapsedTime = endTime - Math.floor(Date.now() / 1000);

        if (elapsedTime <= 0) return stop();

        var mins = Math.floor(elapsedTime / 60);
        var secs = elapsedTime - mins * 60;
        var timeStr = (mins > 0 ? mins + ' мин.' : '') + (mins > 0 && secs > 0 ? ' ' : '') + (secs > 0 ? secs + ' сек.' : '');

        $modal.find('.kristall-integration__verification__error')
          .html(err.replace('{time}', timeStr));
      }, 1000);

      function stop() {
        if (timerId) clearInterval(timerId);
        timerId = null;
        $modal.find('.kristall-integration__verification__error').addClass('d-none');
        $modal.find('.kristall-integration__verification__input').prop('disabled', false).focus();
        $modal.find('.kristall-integration__verification__resend').removeClass('disabled');
        data = null;
        $modal = null;
        timer = null;
      }

      return { stop: stop };
    }

    function lockVerificationModal($modal) {
      if (timer) timer.stop();
      $modal.addClass('modal-locked');
      var $input = $modal.find('.kristall-integration__verification__input');
      $input.prop('disabled', true);
      $modal.find('.kristall-integration__verification__resend').addClass('disabled');
    }

    function unlockVerificationModal($modal) {
      $modal.removeClass('modal-locked');
      var $input = $modal.find('.kristall-integration__verification__input');
      if (!$input.hasClass('code-expired')) {
        $input.prop('disabled', false);
        $input.focus();  
      }
      var $resendLinks = $modal.find('.kristall-integration__verification__resend');
      if (!$resendLinks.hasClass('has-timeout')) {
        $resendLinks.removeClass('disabled');
      }
    }

    function showCodeModal(apiData) {
      var signature = apiData.signature;
      var $form = $('form.checkout');
      var orderParam;

      function verifyCode(code) {
        lockVerificationModal(modal.$modal);

        var formData = getFormData();

        formData += '&action=verify';
        formData += '&verificationCode=' + encodeURIComponent(code);
        formData += '&param=' + encodeURIComponent(orderParam);
        formData += '&signature=' + encodeURIComponent(apiData.signature);

        doApiRequest('krl_v3_verification_code_action', formData, function(result) {
          if (processVerificationResponse(result) && result.data.completed) {
            modal.$modal.find('.kristall-integration__verification__input').unmask();
            lockVerificationModal(modal.$modal);

            var htmlStyle = $('html').attr('style');
            
            $('html').attr('style', (htmlStyle || '') + ';user-select:none;pointer-events:none;');
            $(document).off('.kristall_integration__modal');
            $('body').off('.krl_checkout');
            $('body').find('button[name="woocommerce_checkout_place_order"]').click();
          } else {
            timer = startTimer(result.data, modal.$modal);

            if (result.data.codeExpired) {
              modal.$modal.find('.kristall-integration__verification__input').addClass('code-expired').prop('disabled', true);
            }
          }
        }, function() {
          $('.kristall-integration__verification__input').val('');
          unlockVerificationModal(modal.$modal);
        });
      }

      var content = '<div class="kristall-integration__verification__modal"><h1><div>Подтверждение покупки</div><button type="button" class="kristall-integration__verification__cancel">&times;</button></h1>';

      if (apiData.hasDiscounts) {
        content += '<div class="kristall-integration__verification__dcode">' +
            '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAAABmJLR0QA/wD/AP+gvaeTAAAEtElEQVRoge2Zz29UVRTHP+e9KQuCC1tAptNK+KGdCq4YOm1XQtxI+NWFwYgmLlxIgMR/wIWJcW+kVWJ0VRIpJi0NumxMIS0zGdxI6ZRYIqXTEkpnoVgE581xMQXaeXfmvdd51gX97Obec+79nnvPve/NebDGGs83EsYgfdpnb81tbcNin6J7QOJAFNiwaPIAmAGdEOSaWjqUfCmZFpFirXPXFEA6l25WS0+q8h4QC+g+DdpLke72pvbplWpYUQDDs79sWqeFz0A/ANatdPJFHit8V4f9SaIxcT+oc+AAUjOpdxW+BOqD+nowj+qp9lj790GcfAeQ0UydM+v0KHwYXFsgzkai9umEJP7xY+wrgMxMZn2Bwg8gb9WmzTc/RbDfTjQmFrwMLS+DjGbqVlk8wIEChYtjY2Oe58szAGfW6Vll8YvIm3+8+OALT6tqnaMz6eOC9oYnKjiq8k5HrO18pf6KAaSmUw1qkQU2/ifK/JOPYLdUumIrppBa8jmrKH7u4VylrvqCFj6t1GncgavTV5uwZJLaH1K+uDGfZWDyIu1bkux/+Q2TyeMI9iuJxsRUeYdxB8SWU6yS+PF8SXxRi4zMjjI09bPJbF2BwglThysAVbVUOR62UBPj8+P0/1YS/4Srd1PcWzClk7zfp312easrgHQunQSaQlVqYDyfpX9ycJl4EeHw9oNsXr/J5BJrurttT3mjO4Us9oUp1MR4PutaeRHhyPZD7N64q6Kfpbrf1VbeUIREWEJNVBJ/ePvBquJLqPcOCLxau0wz1cS/vnG3jxGkpbzFdAtF/QoyHzYzpgMrIhzdcdineAB1aTMFsMHQ5mI4d5lvrn/Lr/eve9pWO7C7Gl7zM13JB14ob4v49l7CcO4yw9NXABi8dQmg4iouveefCgmUNtUx7cCDag73Fua4kht5+ltVGbx1ibH5Gy7bG/OlnHeKtaTNMxT+LG8zBTBbbZDN6zfRtfMIljxzVVUGJgeXpVM2P8HFW+aVD5I2yxGXNlcKKdwUqHqftdbHYScMTD5b3Sc7AVBn1dE/OeBa+drTRic8A7Ago9DlNVRrfRxUlx3OJ0EI4n5I7TjE7gave95DvpIx6C0zsnTI74CtDa107TyCbS1PJ+NDqkbxALYlLm2u12lVtVKz6d+BZr8DZ/MTrpSBcG8bhan2aNu28mqe+0ksUgQ9F2TweH0LXTuOLtuJMMUDCHLOVIo0/yMr0g08DjLB0iDCF8+jolPortBnZnQm9ZXAR0EnG89ncYqOjxcz/6hwpiOaPG3qqxjAyJ2Resu2J/j//9TPR7Djgf/UdzZ35lE1Rr2aiHCiWtG3amFrsdB6NnRVflHpTkaTF6qZeFbmpqK3TwIDoYnyz48PG//62MsoQHHXuQAcqFmWD1S5VCf2sVCKuwCJxsRCJGofVfi6dnkeqHT/3bjQ5Uc8rOADx2gufUxEzxDy7SQwh3DSK+fL8bUDS+mItZ2XInFUewQeBfUvR+CRCmccx4kHFb/ov3JG7ozELNs6BXKcAO9Oi9wB6S06he7O5s7cSjWE8plVVa3Ru5lEqW6je0rVA42x9DOrMI1yU5WMbcnQ3i17r4XxmXWNNZ53/gWeFuU7tUC6hwAAAABJRU5ErkJggg=="/>' +
            '<div>Применен промокод</div>' +
            '<div class="kristall-integration__verification__dcode__prices"><span>' + apiData.fullPrice + ' руб.</span> <span>' + apiData.discountPrice + ' руб.</span></div>' +
          '</div>';
      }

      if (apiData.clientSelect) {
        content += '<div class="kristall-integration__verification__select-wrapper">';
        content += '<p>Выберите пользователя ' + siteName + ', зарегистрированного по указанному номеру телефона:</p>';
        for (var client of apiData.clientSelect) {
          content += '<div>- <a href="#" data-event="selectUser" data-param="' + client[0] + '">' + client[1] + '</a></div>';
        }
        content += '</div>';
      } else if (!apiData.fatalError) {
        orderParam = apiData.param;
      }

      content += '<p>' + (apiData.isNewUser ?
        'После проверки кода и оплаты для Вас будет создан личный кабинет в системе ' + siteName + ' и Вы будете перенаправлены.' :
        'После проверки кода и оплаты Вы будете перенаправлены в личный кабинет ' + siteName + '.'
      ) + '</p>';

      content += '<div class="kristall-integration__verification__error' + (apiData.error ? '' : ' d-none') + '"></div>';

      content += '<div class="kristall-integration__verification__wrapper' + (apiData.fatalError || apiData.clientSelect ? ' d-none' : '') + '">';
      content += '<div class="kristall-integration__verification__message"></div>';
      content += '<div class="kristall-integration__verification__code"><input type="text" class="kristall-integration__verification__input" placeholder="____"></div>';
      content += '<div class="kristall-integration__verification__resend"><a href="#" data-event="resendVerificationCode" data-dmethod="sms" class="d-none">Повторить отправку СМС</a><span class="d-none">&nbsp; | &nbsp;</span><a href="#" data-event="resendVerificationCode" data-dmethod="call" class="d-none">Повторить заказ звонка</a><span class="kristall-integration__verification__resend_elapsed_time">(00:00)</span></div>';
      content += '</div>';

      content += '</div>';

      modal = window.kristallIntegrationModal(content, {
        persistent: true,
        onClose: function() {
          modal.$modal.off();
          modal.$modal.find('.kristall-integration__verification__input').unmask();
          if (timer) {
            timer.stop();
            timer = null;
          }
          if (resendTimeoutId) {
            clearInterval(resendTimeoutId);
            resendTimeoutId = null;
          }
          modal = null;
          smsSupported = null;
          smsResend = false;
        }
      });

      modal.$modal.find('.kristall-integration__verification__input').mask('9999', {
        placeholder: '____',
        completed: function () {
          if (modal.$modal.hasClass('modal-locked')) return;
          var $input = $(this);
          var value = $input.val();
  
          if (/^\d{4}$/.test(value)) {
            verifyCode(value);
          }  
        }
      }).focus();

      modal.$modal.on('click', '[data-event="selectUser"]', function(e) {
        e.preventDefault();
        lockVerificationModal(modal.$modal);
        var formData = getFormData();

        formData += '&action=select';
        formData += '&param=' + encodeURIComponent($(e.target).attr('data-param') || '');
        formData += '&signature=' + encodeURIComponent(apiData.signature);

        doApiRequest('krl_v3_verification_code_action', formData, function(result) {
          if (!processVerificationResponse(result)) {
            timer = startTimer(result.data, modal.$modal);
          }
          orderParam = result.data.param;
          modal.$modal.find('.kristall-integration__verification__input').focus();
        }, function() {
          unlockVerificationModal(modal.$modal);
        });
      });

      modal.$modal.on('click', '.kristall-integration__verification__cancel', function(e) {
        e.preventDefault();
        modal.close();
      });

      modal.$modal.on('click', '[data-event="resendVerificationCode"]', function(e) {
        e.preventDefault();
        if ($(e.target).parents('.kristall-integration__verification__resend').hasClass('disabled')) return;
        lockVerificationModal(modal.$modal);
        var formData = getFormData();

        formData += '&action=resend';
        formData += '&deliveryMethod=' + encodeURIComponent($(e.target).attr('data-dmethod') || '');
        formData += '&param=' + encodeURIComponent(orderParam);
        formData += '&signature=' + encodeURIComponent(apiData.signature);

        doApiRequest('krl_v3_verification_code_action', formData, function(result) {
          if (!result.data.error) smsResend = true;
          if (!processVerificationResponse(result)) {
            timer = startTimer(result.data, modal.$modal);
          } else {
            modal.$modal.find('.kristall-integration__verification__input')
              .removeClass('code-expired')
              .prop('disabled', false)
              .focus();
          }
        }, function() {
          unlockVerificationModal(modal.$modal);
        });
      });

      if (apiData.resendTimeout && !apiData.error) {
        startResendTimeout(apiData.resendTimeout);
      }
    }

    // Процесс чекаута
    $('body').on('click.krl_checkout', 'button[name="woocommerce_checkout_place_order"]', function(e) {
      e.preventDefault();

      // Yandex.Metrika
      if (typeof ym === 'function') {
        // ym(34892990, 'reachGoal' , 'order');
      } else {
        console.info('Yandex.Metrika script doesn\'t found');
      }

      // Собираем данные формы чекаута
      var $form = $('form.checkout');
      var $inputs = $form.find('input[name], textearea[name], select[name], button[name]');

      var formData = getFormData();

      $('body').addClass('kristall-integration__page_loading');
      $inputs.prop('disabled', true);
      $form.addClass('processing');

      // Проверяем данные
      doApiRequest('krl_v3_verify_checkout_data', formData, function(result) {
        $('body').removeClass('kristall-integration__page_loading');

        // Если есть ошибки
        if (result.status === 'error') {
          var errors;
          try {
            // var errors = htmlDecode(result.errors);
            errors = JSON.parse(errors || result.errors);
          } catch (e) {
            console.log(e);
            errors = [result.errors];
          }

          return showRequestErrors(errors);
        }

        clearErrors();

        // Обновляем чекаут
        jQuery(document.body).trigger('update_checkout', { update_shipping_method: false });

        siteName = result.data.siteName;
        showCodeModal(result.data);

        if (typeof result.data.clientSelect === 'undefined') {
          if (!processVerificationResponse(result)) {
            timer = startTimer(result.data, modal.$modal);
          }  
        }
      }, function() {
        $('body').removeClass('kristall-integration__page_loading');
        $inputs.prop('disabled', false);
        $form.removeClass('processing');
      });
    });
  });
})(jQuery);
