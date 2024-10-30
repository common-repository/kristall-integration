(function($) {
  'use strict';

  function setMode(editor, mode) {
    if (typeof (editor.mode || {}).set === 'function') {
      editor.mode.set(mode);
    } else {
      editor.setMode(mode);
    }
  }

  function editorDisabled(editor, disabled) {
    setMode(editor, disabled ? 'readonly' : 'design');
    editor.setProgressState(disabled);

    var $buttons = $('#' + editor.id).parents('.wp-editor-wrap').find('.wp-media-buttons button, .wp-editor-tabs button');
    if (disabled) {
      $buttons.attr('disabled', 'disabled');
    } else {
      $buttons.removeAttr('disabled');
    }
  }

  function loadTemplate(editor, shortcode) {
    editorDisabled(editor, true);
    $.ajax({
      url: wpApiSettings.root + 'kristall-integration/v1/template/' + shortcode,
      xhrFields: {
        withCredentials: true
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
      }
    }).done(function(data) {
      if (data && data.content) {
        editor.insertContent(data.content);
      }
    }).fail(function() {
      alert('Не могу загрузить шаблон "' + shortcode + '".');
    }).always(function() {
      editorDisabled(editor, false);
    });
  }

  tinymce.PluginManager.add('kristall-integration-tinymce-shortcodes', function(editor, url) {
    editor.addButton('kristall-integration-tinymce-shortcodes', {
      text: false,
      icon: 'kristall-integration-tinymce-icon',
      tooltip: 'Кристалл Интеграция',
      type: 'menubutton',
      menu: [{
        text: 'Шаблоны',
        menu: [{
          text: 'Описание продукта (товар)',
          onclick: function() {
            loadTemplate(editor, 'description');
          }
        }, {
          text: 'Описание продукта (услуга)',
          onclick: function() {
            loadTemplate(editor, 'service_description');
          }
        }, {
          text: 'Учебный план',
          onclick: function() {
            loadTemplate(editor, 'learnplan');
          }
        }, {
          text: 'Требования (документы)',
          onclick: function() {
            loadTemplate(editor, 'requirements');
          }
        }, {
          text: 'Рассрочка и скидки',
          onclick: function() {
            loadTemplate(editor, 'discount');
          }
        }, {
          text: 'Штрафы',
          onclick: function() {
            loadTemplate(editor, 'fines');
          }
        }, {
          text: 'Итоговый документ',
          onclick: function() {
            loadTemplate(editor, 'document');
          }
        }, {
          text: 'Вопрос/ответ',
          onclick: function() {
            loadTemplate(editor, 'faq');
          }
        }, {
          text: 'Как купить',
          onclick: function() {
            loadTemplate(editor, 'howtobuy');
          }
        }]
      }, {
        text: 'Название продукта',
        onclick: function() {
          editor.insertContent('[kristall_integration_product_title]');
        }
      }, {
        text: 'Кнопка «Купить сейчас»',
        onclick: function() {
          editor.insertContent('[kristall_integration_buy_now text="Купить сейчас"]');
        }
      }, {
        text: 'Вкладка «Описание продукта (товар)»',
        onclick: function() {
          editor.insertContent('[kristall_integration_description]');
        }
      }, {
        text: 'Вкладка «Описание продукта (услуга)»',
        onclick: function() {
          editor.insertContent('[kristall_integration_service_description]');
        }
      }, {
        text: 'Вкладка «Учебный план»',
        onclick: function() {
          editor.insertContent('[kristall_integration_learnplan]');
        }
      }, {
        text: 'Вкладка «Требования (документы)»',
        onclick: function() {
          editor.insertContent('[kristall_integration_requirements]');
        }
      }, {
        text: 'Вкладка «Рассрочка и скидки»',
        onclick: function() {
          editor.insertContent('[kristall_integration_discount]');
        }
      }, {
        text: 'Вкладка «Штрафы»',
        onclick: function() {
          editor.insertContent('[kristall_integration_fines]');
        }
      }, {
        text: 'Вкладка «Итоговый документ»',
        onclick: function() {
          editor.insertContent('[kristall_integration_document]');
        }
      }, {
        text: 'Вкладка «Вопрос/ответ»',
        onclick: function() {
          editor.insertContent('[kristall_integration_faq]');
        }
      }, {
        text: 'Вкладка «Как купить»',
        onclick: function() {
          editor.insertContent('[kristall_integration_howtobuy]');
        }
      }]
    });
  });

})(jQuery);
