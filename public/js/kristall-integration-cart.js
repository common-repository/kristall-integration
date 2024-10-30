(function($) {
  'use strict';

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

  function checkMetaRestrictions() {
    $('.kristall_integration__cart_prod_id[data-sold-individually]')
      .each(function() {
        if (!this.hasAttribute('data-error')) {
          $(this).parents('tr').addClass('kristall_integration__sold_individually_tr').find('.qty').val('1').addClass('disabled').prop('disabled', true);
        }
      });
  }
  checkMetaRestrictions();

  // Обновляем сумму при изменении количества
  $(function () {
    checkMetaRestrictions();
    var debounce = createDebounceFn(1000);

    function getUpdateBtn() {
      var $btnEl = $('.woocommerce-cart-form [name="update_cart"]');

      if (!$btnEl.length) {
        $btnEl = $('<button type="submit" name="update_cart" value="1"></button>');
        var $cartActionsEl = $('.woocommerce-cart-form .cart-actions');

        if (!$cartActionsEl.length) {
          $cartActionsEl = $('<div class="cart-actions" />');
          $('.woocommerce-cart-form').append($cartActionsEl)
        }

        $cartActionsEl.append($btnEl);
      }

      return $btnEl;
    }

    function getProceedWrapper() {
      return $('.woocommerce .wc-proceed-to-checkout');
    }

    function disableProceedBtn() {
      var $wrapper = getProceedWrapper();
      $wrapper
        .addClass('kristall-integration__disabled')
        .find('a').attr('tabindex', '-1');

      if (!$('.kristall-integration__proceed-overlay').length) {
        $wrapper.append('<div class="kristall-integration__proceed-overlay" />');
      }
    }

    function enableProceedBtn() {
      getProceedWrapper()
        .removeClass('kristall-integration__disabled')
        .find('a').removeAttr('tabindex');
    }

    $('.woocommerce').on('change input', 'input.qty', function() {
      disableProceedBtn();
      debounce(function() {
        getUpdateBtn().click();
      });
    });

    $(document.body).on('updated_wc_div', function() {
      enableProceedBtn();
      checkMetaRestrictions();
    });
  });
})(jQuery);
