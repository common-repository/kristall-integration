(function($) {
  'use strict';

  function copyObject(obj) {
    if (Array.isArray(obj)) {
      return obj.map(function(item) {
        return copyObject(item);
      });
    } else if (obj && typeof obj === 'object') {
      var newObj = {};
      for (var key in obj) {
        newObj[key] = copyObject(obj[key]);
      }
      return newObj;
    }
    return obj;
  }

  function getEditorHtml(editorId, content) {
    return '' +
      '<div id="wp-' + editorId + '-wrap" class="wp-core-ui wp-editor-wrap tmce-active">' +
      '    <style>#wp-' + editorId + '-editor-container .wp-editor-area{height:500px; width:100%;}</style>' +
      '    <div id="wp-' + editorId + '-editor-tools" class="wp-editor-tools hide-if-no-js">' +
      '      <div id="wp-' + editorId + '-media-buttons" class="wp-media-buttons"><button type="button" class="button insert-media add_media" data-editor="' + editorId + '"><span class="wp-media-buttons-icon"></span> Добавить медиафайл</button></div>' +
      '      <div class="wp-editor-tabs"><button type="button" id="' + editorId + '-tmce" class="wp-switch-editor switch-tmce" data-wp-editor-id="' + editorId + '">Визуально</button>' +
      '          <button type="button" id="' + editorId + '-html" class="wp-switch-editor switch-html" data-wp-editor-id="' + editorId + '">Текст</button>' +
      '      </div>' +
      '    </div>' +
      '    <div id="wp-' + editorId + '-editor-container" class="wp-editor-container">' +
      '      <div id="qt_' + editorId + '_toolbar" class="quicktags-toolbar hide-if-no-js"></div>' +
      '      <textarea class="wp-editor-area" rows="20" autocomplete="off" cols="40" name="' + editorId + '" id="' + editorId + '">' + content + '</textarea>' +
      '    </div>' +
      '</div>';
  }

  function initializeEditor(editorId, cb) {
    var $editor = $('#' + editorId);
    if (!$editor.length) return;

    var existingEditor = tinymce.get(editorId);
    if (existingEditor) {
      return cb(existingEditor);
    }

    // Инициализируем редактор и копируем опции из редактора краткого содержания
    var tinymceConfig = copyObject(tinyMCEPreInit.mceInit.excerpt);
    var quicktagsConfig = copyObject(tinyMCEPreInit.qtInit.excerpt);

    tinymceConfig.selector = '#' + editorId;
    tinymceConfig.height = '400';
    tinymceConfig.setup = function(editor) {
      editor.on('init', function() {
        cb(editor);
      });
    };
    tinymceConfig.init_instance_callback = function(editor) {
      cb(editor);
    }  
    quicktagsConfig.id = editorId;

    tinymce.init(tinymceConfig);
    quicktags(quicktagsConfig);
  }

  function updateTab(tabs, tabId, val) {
    tabs.updateTab(tabId, {
      content: val
    })
  }

  $(function() {
    var $wrapper = $('#kristall-integration__product_tabs .kristall-integration__product_tab_list');

    if (!window.initKristallIntegrationTabSettings || !$wrapper.length) {
      return;
    }

    // Прячем вкладки Porto
    $('[id^="custom_tab_priority"]').parents('.metabox').hide();
    $('[id^="custom_tab_content"]').parents('.metabox').hide();
    $('[id^="custom_tab_title"]').parents('.metabox').hide();    

    // Инициализируем табы
    var tabs = window.initKristallIntegrationTabSettings($wrapper, false);
    tabs.activateFirstTab();

    function bindEditorOnChange(tabId, editorId, $textarea) {
      initializeEditor(editorId, function(editor) {
        var $editor = $('#' + editorId);

        if ($editor.attr('data-ki-bound') === 'true') return;
        $editor.attr('data-ki-bound', 'true');

        $textarea.on('change', function() {
          updateTab(tabs, tabId, $textarea.val());
        });
        editor.on('change', function(e) {
          updateTab(tabs, tabId, editor.getContent());
        });
        editor.on('ExecCommand', function() {
          updateTab(tabs, tabId, editor.getContent());
        });
        editor.on('undo', function() {
          updateTab(tabs, tabId, editor.getContent());
        });
        editor.on('redo', function() {
          updateTab(tabs, tabId, editor.getContent());
        });

        $textarea.parents('.kristall-integration__product_tab_content')
          .on('click', '.wp-switch-editor', function () {
            var isMce = $(this).hasClass('switch-tmce');
  
            setTimeout(function () {
              updateTab(tabs, tabId, isMce ? editor.getContent() : $textarea.val());
            }, 500)
          });
      });
    }

    $wrapper.on('ki-tab-added', function(e, $el, data) {
      var editorId = data.id + '__editor';
      var $container = $(
        '<div class="kristall-integration__product_tab_content" id="' + data.id + '_content">' +
        getEditorHtml(editorId, data.content) +
        '</div>'
      );
      $wrapper.after($container);

      tabs.activateTab($el);
      bindEditorOnChange(data.id, editorId, $container.find('textarea.wp-editor-area'))

      window.wpActiveEditor = editorId;
    });

    $wrapper.on('ki-tab-remove', function(e, $el, data) {
      var $target = $('#' + $el.attr('data-target'));
      var $textarea = $target.find('textarea.wp-editor-area');

      $textarea.off();
      tinymce.remove('#' + $textarea.attr('id'));
      $target.off();
      $target.remove();
    });

    $wrapper.find('> .kristall-integration__product_tab_item').each(function() {
      if ($(this).hasClass('persistent')) return;

      var $target = $('#' + $(this).attr('data-target'));

      var content = $target.find('> textarea').val();
      $target.find('> textarea').remove();

      var tabId = $(this).attr('data-id');
      var editorId = tabId + '__editor';
      $target.append(getEditorHtml(editorId, content));
      var $textarea = $target.find('textarea.wp-editor-area');

      bindEditorOnChange(tabId, editorId, $textarea);
    });
  });
})(jQuery);