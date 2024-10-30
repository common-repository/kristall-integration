(function($) {
  'use strict';

  var reservedPriorities = [10, 20, 30];

  function getNewTabContents() {
    return {
      learnplan: '[kristall_integration_learnplan]',
      requirements: '[kristall_integration_requirements]',
      discount: '[kristall_integration_discount]',
      fines: '[kristall_integration_fines]',
      document: '[kristall_integration_document]',
      faq: '[kristall_integration_faq]',
      howtobuy: '[kristall_integration_howtobuy]',
      custom: '',
    }
  }

  function updateHiddenInput($input, tabData) {
    var jsonData = JSON.stringify({
      data: tabData
    });
    $input.val(jsonData);
  }

  function sortTabsPriority(tabData, start, end) {
    var lastPriority = start + 1;
    var selected = tabData.filter(function(data) {
      if (end) {
        return data.priority >= start && data.priority <= end;
      } else {
        return data.priority >= start;
      }
    });

    if (!selected.length) {
      return;
    }

    selected.sort(function(a, b) {
      return a.priority - b.priority;
    });

    for(var i = 0, l = selected.length; i < l; i++) {
      if (end && lastPriority >= end) {
        selected[i].priority = end - 1;
      } else {
        selected[i].priority = lastPriority;
        lastPriority++;
      }
    }
  }

  function updatePriorities(tabData) {
    if (!reservedPriorities.length) {
      return;
    }
    for(var i = 0, l = reservedPriorities.length; i < l; i++) {
      if (i === 0) {
        sortTabsPriority(tabData, 0, reservedPriorities[0]);
      } else {
        sortTabsPriority(tabData, reservedPriorities[i - 1], reservedPriorities[i]);
      }
    }
    sortTabsPriority(tabData, reservedPriorities[reservedPriorities.length - 1], null);
  }

  function getTabPriority(tabData, $tab) {
    if ($tab.hasClass('persistent')) {
      return Number($tab.attr('data-priority'));
    }
    var tabId = $tab.attr('data-id');
    var data = tabData.find(function (d) { return d.id === tabId; });
    return data ? data.priority : 1;
  }

  function updateTab(tabData, tabId, dataOverwrite) {
    var tab = tabData.find(function (data) { return data.id === tabId });
    if (!tab) {
      return;
    }
    Object.assign(tab, dataOverwrite);
  }

  function deactivateTabs($wrapper) {
    $wrapper
      .find('.kristall-integration__product_tab_item')
      .each(function() {
        var $this = $(this);
        $this.removeClass('active');
        $('#' + $this.attr('data-target')).removeClass('active');
      });
  }

  function activateTab($wrapper, $tab) {
    deactivateTabs($wrapper);
    $tab.addClass('active');
    $('#' + $tab.attr('data-target')).addClass('active');
  }

  function activateFirstTab($wrapper) {
    activateTab(
      $wrapper,
      $wrapper.find('.kristall-integration__product_tab_item').first()
    );
  }

  function showMenu($newTabWrapper, cb) {
    if (!$newTabWrapper.hasClass('show')) {
      $newTabWrapper.addClass('show');
      cb(true);
    }
  }

  function hideMenu($newTabWrapper, cb) {
    if ($newTabWrapper.hasClass('show')) {
      $newTabWrapper.removeClass('show');
      cb(false);
    }
  }

  function toggleMenu($newTabWrapper, cb) {
    $newTabWrapper.toggleClass('show');
    cb($newTabWrapper.hasClass('show'));
  }

  window.initKristallIntegrationTabSettings = function($element, staticItems) {
    var $hiddenInput = $element.find('input[type=hidden]');
    var tabData = JSON.parse($hiddenInput.val()).data;

    var $newTabWrapper = $('.kristall-integration__product_tab_add');
    var $newTabBtn = $newTabWrapper.find('.kristall-integration__product_tab_item');
    var $newTabMenu = $newTabWrapper.find('.kristall-integration__product_tab_menu');

    $element.on('dblclick', '> .kristall-integration__product_tab_item:not(.persistent)', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var $this = $(this);
      var $titleEl = $this.find('span:first-child');

      var newTitle = prompt('Введите новое имя для вкладки «' + $titleEl.text() + '».', $titleEl.html());
      if (newTitle) {
        $this.find('span:first-child').html(newTitle);
        updateTab(tabData, $this.attr('data-id'), {
          title: newTitle
        });
        updateHiddenInput($hiddenInput, tabData);
      }
    });

    if (!staticItems) {
      $element.on('click', '> .kristall-integration__product_tab_item', function() {
        var $this = $(this);
        if ($this.hasClass('active')) {
          return;
        }
        activateTab($element, $this);
      });
    }

    function menuCb(isOpened) {
      if (isOpened) {
        $('body').on('click.kristall-integration-tabs-menu', function (e) {
          if (!$.contains($newTabWrapper[0], e.target)) {
            hideMenu($newTabWrapper, menuCb);
          }
        });
      } else {
        $('body').off('click.kristall-integration-tabs-menu');
      }
    }

    $newTabBtn.on('click', function(e) {
      e.preventDefault();
      toggleMenu($newTabWrapper, menuCb);
    });

    $newTabMenu.on('click', '.kristall-integration__product_tab_menu_item', function(e) {
      e.preventDefault();
      var $this = $(this);
      var tabType = $this.attr('data-type');
      var tabTitle = $this.attr('data-title');

      if (tabType === 'custom') {
        tabTitle = prompt('Введите название новой вкладки.');

        if (!tabTitle) {
          return;
        }
      }

      var ids = tabData.map(function (tab) { return tab.id; })
      var newTabId = 1;
      var newTabIdStr = 'kristall-integration__custom-tab-' + newTabId;

      while(ids.indexOf('kristall-integration__custom-tab-' + newTabId) !== -1) {
        newTabIdStr = 'kristall-integration__custom-tab-' + (++newTabId);
      }

      var newTabHtml = '<div class="kristall-integration__product_tab_item" data-id="' + newTabIdStr + '" data-target="' + newTabIdStr + '_content">';
      newTabHtml += '<span>' + tabTitle + '</span><span class="kristall-integration__product_tab_item_remove">×</span>';
      newTabHtml += '</div>';

      var priorities = tabData.map(function (tab) { return tab.priority; }).concat(reservedPriorities);
      var newTabPriority = Math.max.apply(Math, priorities) + 1;

      var $newTabEl = $(newTabHtml);
      $newTabWrapper.before($newTabEl);

      var newTabData = {
        id: newTabIdStr,
        persistent: false,
        priority: newTabPriority,
        title: tabTitle,
        content: getNewTabContents()[tabType] || '',
      };

      tabData.push(newTabData);
      updatePriorities(tabData);
      updateHiddenInput($hiddenInput, tabData);

      hideMenu($newTabWrapper, menuCb);
      $element.trigger('ki-tab-added', [$newTabEl, newTabData]);
    });

    $element.on('click', '.kristall-integration__product_tab_item_remove', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var $this = $(this);

      var sure = confirm('Вы уверены, что хотите удалить вкладку «' + $this.prev('span').text() + '»?')
      if (sure) {
        var $tab = $this.parent('.kristall-integration__product_tab_item');
        var id = $tab.attr('data-id');
        var data = tabData.find(function (tab) { return tab.id === id; });

        if ($tab.hasClass('active')) {
          var $prev = $tab.prev('.kristall-integration__product_tab_item');
          var $next = $tab.next('.kristall-integration__product_tab_item');
          if ($prev.length && !staticItems) {
            activateTab($element, $prev);
          } else if ($next.length) {
            activateTab($element, $next);
          }
        }

        $element.trigger('ki-tab-remove', [$tab, data]);

        tabData.splice(tabData.indexOf(data), 1);
        updatePriorities(tabData);
        updateHiddenInput($hiddenInput, tabData);
        $tab.remove();
      }
    });

    var sortInstance = new Sortable($element[0], {
      draggable: '.kristall-integration__product_tab_item',
      ghostClass: 'kristall-integration__product_tab_item_ghost',
      filter: '.persistent',
      direction: 'horizontal',
      onEnd: function(e) {
        var $tab = $(e.item);
        var $prev = $tab.prev('.kristall-integration__product_tab_item');
        var $next = $tab.next('.kristall-integration__product_tab_item');

        if ($prev.length) {
          updateTab(tabData, $tab.attr('data-id'), {
            priority: getTabPriority(tabData, $prev) + 0.5
          });
        } else if ($next.length) {
          updateTab(tabData, $tab.attr('data-id'), {
            priority: getTabPriority(tabData, $next) - 0.5
          });
        } else {
          updateTab(tabData, $tab.attr('data-id'), {
            priority: 1
          });
        }

        updatePriorities(tabData);
        updateHiddenInput($hiddenInput, tabData);
      },
    });

    function clear() {
      $newTabMenu.off();
      $element.off();
      sortInstance.destroy();
    }

    return {
      activateFirstTab: function() {
        if (!staticItems) {
          activateFirstTab($element);
        }
      },
      activateTab: function($tab) {
        if (!staticItems) {
          activateTab($element, $tab);
        }
      },
      updateTab: function(tabId, dataOverwrite) {
        updateTab(tabData, tabId, dataOverwrite);
        updatePriorities(tabData);
        updateHiddenInput($hiddenInput, tabData);
      },
      destroy: clear
    };
  };

})(jQuery);
