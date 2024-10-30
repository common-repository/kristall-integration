(function($) {
  'use strict';

  $(function() {
    if (window.kristallIntegrationModal) {
      $('body').on('click', '.kristall-integration__qrcode_link', function(e) {
        e.preventDefault();

        var $image = $('<div id="kristall_integration__qrcode_image"></div>');
        $image.hide();
        $('body').append($image);

        var qrcode = new QRCode('kristall_integration__qrcode_image', {
          text: document.location.href.replace(/#.*$/, ''),
          width: 384,
          height: 384,
          colorDark : "#000000",
          colorLight : "#ffffff",
          correctLevel : QRCode.CorrectLevel.H
        });

        $image.removeAttr('id');
        kristallIntegrationModal($image);
        $image.show();
      });

      $('body').on('click', '.kristall-integration__barcode_link', function(e) {
        e.preventDefault();

        var productId = $(this).attr('data-product');
        var $image = $('<svg id="kristall_integration__barcode_image"></svg>');
        $image.hide();
        $('body').append($image);

        var elaCode = {
          country: '469',
          manufacturer: '2632'
        };

        elaCode.toStr = function(){
          var val = productId;
          var rtn = '00000';
          var addNull = (n) => {
            let str = '';
            for(let i=0; i < n; i++){
              str += '0';
            }
            return str;
          };

          if (val.length <= 5) {
            rtn = elaCode.country + elaCode.manufacturer + addNull(5 - val.length) + val;
          } else {
            rtn = elaCode.country + addNull(9 - val.length) + val;
          }

          return rtn;
        };

        let ean = JsBarcode("#kristall_integration__barcode_image", elaCode.toStr(), {
          format: "EAN13",
          lastChar: ">",
          width: 3,
          height: 150
        });

        $image.removeAttr('id');
        kristallIntegrationModal($image);
        $image.show();
      });

      $('body').on('click', '.kristall-integration__partner_link', function(e) {
        e.preventDefault();

        var toBinary = function(string) {
          const codeUnits = new Uint16Array(string.length);
          for (let i = 0; i < codeUnits.length; i++) {
            codeUnits[i] = string.charCodeAt(i);
          }
          return btoa(String.fromCharCode(...new Uint8Array(codeUnits.buffer)));
        };

        var $modalContent = $(
          '<div class="kristall-integration__partner-modal">' +
          '<label>Ваш код агента</label>' +
          '<div class="kristall-integration__partner-wrapper">' +
          '<input type="text" value="" class="input-text" />' +
          '<button type="button" class="button wp-element-button">Получить</button>' +
          '</div>' +
          '</div>'
        );
        var $input = $modalContent.find('[type=text]');
        var $button = $modalContent.find('[type=button]');
        var $label = $modalContent.find('label');

        var modal = kristallIntegrationModal($modalContent);
        $input.focus();

        function copyToClipboard(_url) {
          if (navigator.clipboard) {
            navigator.clipboard.writeText(_url);
          } else {
            document.execCommand('copy');
          }

          if (window.toastr) {
            toastr.clear();
            toastr.success('Партнерская ссылка скопирована в буфер обмена.');
          } else {
            alert('Партнерская ссылка скопирована в буфер обмена.')
          }
        }

        $button.on('click', function(e) {
          e.preventDefault()

          var value = $input.val().replace(/^\s+|\s+$/g, '');

          if (!value) {
            $input.focus();
            return;
          }

          var _location = window.location;
          var html = '';
          var _url = '';

          if (_location.pathname === '/for-me/') {
            _url = _location.origin+'/shop/?uaid=';
          } else {
            _url = _location.href+'?uaid=';
          }
          _url += toBinary(value);

          $input.on('focus', function() {
            $input.select();
          });

          $button.text('Копировать');
          $button.off();

          $label.text('Ваша партнерская ссылка:');
          $input.val(_url);
          $input.attr('readonly', 'readonly');
          $input.focus();
          copyToClipboard(_url);

          $button.on('click', function() {
            $input.focus();
            copyToClipboard(_url);
          });
        });

        modal.$modal.on('ki-modal-close', function() {
          modal.$modal.off();
          $button.off();
          $input.off();
        });
      });
    }
  });

  $(function() {
    if (typeof window.wc_add_to_cart_params === 'undefined') {
      $(function() {
        $('.kristall-integration__buy_now_btn').remove();
      });
      return false;
    }

    initSoldIndividually();

    function waitForLoading(waitDurationSeconds, timeoutMs, workingTime) {
      waitDurationSeconds = waitDurationSeconds || 60;
      timeoutMs = timeoutMs || 250;
      workingTime = workingTime || 0;

      if ($('.kristall-integration__buy_now_btn').length) {
        initBuyNowBtns();
      } else if (workingTime >= waitDurationSeconds * 1000) {
      } else {
        setTimeout(
          waitForLoading.bind(timeoutMs, waitDurationSeconds, timeoutMs, workingTime + timeoutMs),
          timeoutMs
        );
      }
    }

    // single-add-to-cart

    function initBuyNowBtns() {
      var $buyNowBtns = $('.kristall-integration__buy_now_btn');

      if (!$buyNowBtns.length) {
        return;
      }

      $(document.body)
        .off('.kristall-integration-buy-now')
        .on('wc_fragments_loaded.kristall-integration-buy-now', initBuyNowBtns)
        .on('wc_fragments_refreshed.kristall-integration-buy-now', initBuyNowBtns);

      if ($buyNowBtns.first().attr('data-type') === 'simple') {
        initSimpleBuyNowBtns($buyNowBtns);
      } else {
        initVariableBuyNowBtns($buyNowBtns);
      }
    }

    function initSoldIndividually() {
      if ($('.kristall-integration__porto_product').hasClass('single-sold-individually')) {
        var $wrapper = $('.kristall-integration__porto_product');
        var productId = $wrapper.attr('data-id');

        function processCartItems() {
          if (!$('.cart_list').length) return;

          var inCart = false;
          $('.cart_list .remove-product').each(function() {
            if (!inCart) {
              inCart = $(this).attr('data-product_id') == productId;
            }
          });

          if (inCart) {
            $wrapper.addClass('kristall-integration__in_cart');
          } else {
            $wrapper.removeClass('kristall-integration__in_cart');
          }
        }

        $('body')
          .on('wc_fragments_refreshed', processCartItems);
      }
    }

    function initSimpleBuyNowBtns($btns) {
      $btns.removeAttr('disabled');

      $(document.body).on('click.kristall-integration-buy-now', '.kristall-integration__buy_now_btn', function() {
        if (this.hasAttribute('disabled')) {
          return;
        }

        doRequest($btns, {
          type: 'simple',
          id: this.getAttribute('data-id'),
          token: this.getAttribute('data-key')
        });
      });
    }

    function initVariableBuyNowBtns($btns) {
      var $form = $('.variations_form');

      if (!$form.length) {
        return;
      }

      function getVariationId() {
        var $input = $('input[name="variation_id"]');
        if (!$input.length) {
          return;
        }
        var variationId = parseInt($input.first().val() || '0');
        return isNaN(variationId) ? 0 : variationId;
      }

      function checkVariations() {
        var variationId = getVariationId();
        if (!variationId) {
          $btns.attr('disabled', 'disabled');
        } else {
          $btns.removeAttr('disabled');
        }
      }

      $form
        .off('kristall-integration-buy-now')
        .on('woocommerce_variation_has_changed.kristall-integration-buy-now', checkVariations);

      $(document.body).on('click.kristall-integration-buy-now', '.kristall-integration__buy_now_btn', function() {
        if (this.hasAttribute('disabled')) {
          return;
        }

        doRequest($btns, {
          type: 'variable',
          id: this.getAttribute('data-id'),
          token: this.getAttribute('data-key'),
          variationId: getVariationId()
        });
      });

      checkVariations();
    }

    function doRequest($btns, data) {
      var types = ['simple', 'variable'];
      var type = types.indexOf(data.type) !== -1 ? data.type : null;
      var id = parseInt(data.id);
      var quantity = $('.product-summary-wrap .quantity .qty').val();

      var requestData = {
        id: id,
        type: type,
        quantity: isNaN(quantity) ? 1 : quantity,
        _krl_token: data.token
      };

      if (!type || isNaN(id)) {
        throw new Error('Invalid request data.');
      }

      if (type === 'variable') {
        var variationId = parseInt(data.variationId);

        if (!variationId || isNaN(variationId)) {
          throw new Error('Invalid request data.');
        }

        requestData.variationId = variationId;
      }

      function onRequestError() {
        alert('Ошибка обработки данных. Попробуйте еще раз.');

        $btns.removeClass('loading');
        $('input[name="quantity"], .single_add_to_cart_button, .kristall-integration__buy_now_btn, .quantity button')
          .removeAttr('disabled');
      }

      $btns.addClass('loading');
      $('input[name="quantity"], .single_add_to_cart_button, .kristall-integration__buy_now_btn, .quantity button')
        .attr('disabled', 'disabled');

      $.ajax({
        method: 'POST',
        url: '/?wc-ajax=krl_v3_buy_now',
        data: requestData,
        cache: false,
        xhrFields: {
          withCredentials: true
        }
      }).done(function(data) {
        if (data && data.redirectUrl) {
          $.ajax({  
            method: 'POST',
            url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart'),
            data: {
              product_id: requestData.id
            },
            dataType: 'json',
            cache: false,
            xhrFields: {
              withCredentials: true
            }
          }).done(function(response) {
            if (!response) {
              return;
            }
  
            if (response.error && response.product_url) {
              window.location = response.product_url;
              return;
            }
  
            $(document.body).trigger( 'added_to_cart', [response.fragments, response.cart_hash, null]);
  
            setTimeout(function() {
              document.location = data.redirectUrl;
            });
          });
        } else {
          onRequestError();
        }
      }).fail(onRequestError);
    }

    waitForLoading();
  });
})(jQuery);
