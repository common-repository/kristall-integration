(function($) {
  'use strict';

  window.kristallIntegrationModal = function(content, options) {
    var baseClass = 'kristall_integration__modal';
    var $html = $('html');
    var $body = $('body');

    if (typeof options == 'undefined') {
      options = {};
    }

    if ($html.hasClass(baseClass + '_opened')) {
      return {
        close: function() {}
      };
    }

    var $modal = $('<div class="' + baseClass + '"></div>');
    $modal.append(content);

    var $overlay = $('<div class="' + baseClass + '_overlay"></div>');

    function closeModal() {
      $modal.trigger('ki-modal-close');
      $html.removeClass(baseClass + '_opened');
      $modal.remove();
      $overlay.remove();
      if (typeof options.onClose === 'function') options.onClose();
    }

    $html.addClass(baseClass + '_opened');
    $body
      .append($modal)
      .append($overlay);

    if (options.persistent !== true) $overlay.on('click', closeModal);
    $(document).on('keyup.' + baseClass, function(e) {
      var event = e.originalEvent;

      if (event && (event.keyCode === 27 || event.key.toLowerCase() === 'escape')) {
        closeModal();
        $(document).off('.' + baseClass);
      }
    });

    return {
      $modal: $modal,
      close: closeModal
    };
  }
})(jQuery);
