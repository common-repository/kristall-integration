var CORE_TEMP = CORE_TEMP || {};

!(function ($) {
    "use strict";
	
	CORE_TEMP.urlbase = window.location.protocol + "//" + window.location.host;
	
	//console.log(CORE_TEMP.defUrl);
	
	CORE_TEMP.kristall = {
		packageName: "Kristall Promise Based JS CSS Loader",
		warningMessage: function(text) {
			console.warn("[" + CORE_TEMP.kristall.packageName + "] " + text);
		},
		utils: {
			id: (p) => (p ? p + '_' : '') + Math.random().toString(36).substr(2, 9)
		},
		addResources: function(resourceURIs, ver) {
			if ((typeof(resourceURIs) !== "string") && !("length" in resourceURIs)) {
				CORE_TEMP.kristall.warningMessage("Resource URIs must be an array or a string");
				return new Promise(function(resolve, reject) { reject("Resource URIs must be an array or a string"); });
			}

			if (!resourceURIs.length) {
				return new Promise(function(resolve, reject) { resolve(); });
			}

			if (typeof(resourceURIs) === "string") {
				resourceURIs = [resourceURIs];
			}

			let promises = [];
			
			ver = ver ? ver : new Date().getTime();

			for (var index in resourceURIs) {
				let isJS = /^.+\.js$/.test(resourceURIs[index]);
				let isCSS = /^.+\.css$/.test(resourceURIs[index]);
				let isSupportedResource = isJS || isCSS;
				let id;

				if (isSupportedResource) {
					promises.push(new Promise(function(resolve, reject) {
						let resource = document.createElement(isJS ? "script" : "link");
					
						if (isJS) {
							resource.type = "text/javascript";
							resource.src = resourceURIs[index] + '?ver=' + ver;
							id = CORE_TEMP.kristall.utils.id('krJS');
							resource.id = id;
						} else if (isCSS) {
							resource.type = "text/css";
							resource.rel = "stylesheet";
							id = CORE_TEMP.kristall.utils.id('krCSS');
							resource.id = id;
							resource.href = resourceURIs[index] + '?ver=' + ver;
						}

						resource.addEventListener("load", () => { resolve(id); } );

						document.head.appendChild(resource);
					}));
				}
			}

			return Promise.all(promises);
		}
	};
	
	CORE_TEMP.function = {
		KristallSpectrum: function () {
			if ($().krspPicker) {
				$('input[data-plugin="kristall_spectrum"]').each(function () {
                    $(this).krspPicker('destroy');
                    CORE_TEMP.function.initKristallSpectrum($(this));
                });
			}
		},
		initKristallSpectrum: function (element) {
			if ($().krspPicker) {
				let $this = element,
					$defl = {
						preferredFormat: 'hex3',
						showAlpha: true,
						showInput: true,
						showInitial: true,
						showPalette: true,
						showSelectionPalette: false,
						palette: [
							['#c62828','#e53935','#ef5350','#ef9a9a','#ffebee','#ff8a80'],
							['#ad1457','#d81b60','#ec407a','#f48fb1','#fce4ec','#ff80ab'],
							['#6a1b9a','#8e24aa','#ab47bc','#ce93d8','#f3e5f5','#ea80fc'],
							['#4527a0','#5e35b1','#7e57c2','#b39ddb','#ede7f6','#b388ff'],
							['#283593','#3949ab','#5c6bc0','#9fa8da','#e8eaf6','#8c9eff'],
							['#1565c0','#1e88e5','#42a5f5','#90caf9','#e3f2fd','#82b1ff'],
							['#0277bd','#039be5','#29b6f6','#81d4fa','#e1f5fe','#80d8ff'],
							['#00838f','#00acc1','#26c6da','#80deea','#e0f7fa','#84ffff'],
							['#2e7d32','#43a047','#66bb6a','#a5d6a7','#e8f5e9','#b9f6ca'],
							['#ef6c00','#fb8c00','#ffa726','#ffcc80','#fff3e0','#ffd180'],
							['#212121','#424242','#757575','#bdbdbd','#eeeeee','#fafafa']
						],
						cancelText: 'Отмена',
						chooseText: 'Выбрать'
					};
				let $options = $.extend($defl, $this.data());
				let _id, _Fn;
				
				if ($options.label) {
					try {
						_Fn = (p) => (p ? p + '_' : '') + Math.random().toString(36).substr(2, 9);
					} catch(e) {
						let dt = new Date().getTime();
						_Fn = function(p){return(p ? p + '_' : '') + dt};
					}
					_id = _Fn('krsp');
					$this.after('<span id="'+_id+'" class="krsp-label">'+$this.val()+'</span>');
					if (!$options.action) {
						$options.change = function(color) {
							$('#'+_id).text(color.toString());
						};
					}
				}
				
				if ($options.action) {
					let innerColor = function(_color){
						// Интерактивное изменение цвета
						$('.woocommerce-input-wrapper-demo input:not(:checked) + label').css({
							backgroundColor: ($options.action=='wooCheckoutButtonBgColor') ? _color :$('[data-action="wooCheckoutButtonBgColor"] ~ .krsp-label').text(),
							color: ($options.action=='wooCheckoutButtonTxtColor') ? _color : $('[data-action="wooCheckoutButtonTxtColor"] ~ .krsp-label').text(),
							borderColor: ($options.action=='wooCheckoutButtonBorderColor') ? _color :$('[data-action="wooCheckoutButtonBorderColor"] ~ .krsp-label').text()
						});
						$('.woocommerce-input-wrapper-demo input:checked + label').css({
							backgroundColor: ($options.action=='wooCheckoutButtonBgColorActive') ? _color :$('[data-action="wooCheckoutButtonBgColorActive"] ~ .krsp-label').text(),
							color: ($options.action=='wooCheckoutButtonTxtColorActive') ? _color :$('[data-action="wooCheckoutButtonTxtColorActive"] ~ .krsp-label').text(),
							borderColor: ($options.action=='wooCheckoutButtonBgColorActive') ? _color :$('[data-action="wooCheckoutButtonBgColorActive"] ~ .krsp-label').text(),
							boxShadow: '0 0 10px ' + (($options.action=='wooCheckoutButtonBgColorShadow') ? _color : $('[data-action="wooCheckoutButtonBgColorShadow"] ~ .krsp-label').text())
						});
						
						$(".woocommerce-input-wrapper-demo input:not(:checked) + label").hover(function(e) {
							/*
							let tmpBgColor = (e.type === "mouseenter")?($options.action=='wooCheckoutButtonBgColorHover'?_color:$('[data-action="wooCheckoutButtonBgColorHover"]').val()):$('[data-action="wooCheckoutButtonBgColor"]').val();
							let tmpBdColor = (e.type === "mouseenter")?(($options.action=='wooCheckoutButtonBgColorHover')?_color:$('[data-action="wooCheckoutButtonBgColorHover"]').val()):$('[data-action="wooCheckoutButtonBorderColor"]').val();
							let tmpColor = (e.type === "mouseenter")?($options.action=='wooCheckoutButtonTxtColorHover'?_color:$('[data-action="wooCheckoutButtonTxtColorHover"]').val()):$('[data-action="wooCheckoutButtonTxtColor"]').val();
							*/
							let tmpBgColor = (e.type === "mouseenter")?$('[data-action="wooCheckoutButtonBgColorHover"] ~ .krsp-label').text():$('[data-action="wooCheckoutButtonBgColor"] ~ .krsp-label').text();
							let tmpBdColor = (e.type === "mouseenter")?$('[data-action="wooCheckoutButtonBgColorHover"] ~ .krsp-label').text():$('[data-action="wooCheckoutButtonBorderColor"] ~ .krsp-label').text();
							let tmpColor = (e.type === "mouseenter")?$('[data-action="wooCheckoutButtonTxtColorHover"] ~ .krsp-label').text():$('[data-action="wooCheckoutButtonTxtColor"] ~ .krsp-label').text();
							
							$(this).css({
								backgroundColor: tmpBgColor, 
								borderColor: tmpBdColor,
								color: tmpColor
							});
						});
					};
					// Устанавливаем значения цвета кнопок из input при загрузке страницы
					switch($options.action) {
						case 'wooCheckoutButtonBgColor':
							$('.woocommerce-input-wrapper-demo input:not(:checked) + label').css({backgroundColor: $this.val()});
							break;
						case 'wooCheckoutButtonTxtColor':
							$('.woocommerce-input-wrapper-demo input:not(:checked) + label').css({сolor: $this.val()});
							break;
						case 'wooCheckoutButtonBorderColor':
							$('.woocommerce-input-wrapper-demo input:not(:checked) + label').css({borderColor: $this.val()});
							break;
						case 'wooCheckoutButtonBgColorActive':
							$('.woocommerce-input-wrapper-demo input:checked + label').css({backgroundColor: $this.val(),borderColor: $this.val()});
							break;
						case 'wooCheckoutButtonTxtColorActive':
							$('.woocommerce-input-wrapper-demo input:checked + label').css({color: $this.val()});
							break;
						case 'wooCheckoutButtonBgColorShadow':
							$('.woocommerce-input-wrapper-demo input:checked + label').css({boxShadow: '0 0 10px '+$this.val()});
							break;
						case 'wooCheckoutButtonBgColorHover':
							$(".woocommerce-input-wrapper-demo input:not(:checked) + label").hover(function(e) {
								let tmpBgColor = (e.type === "mouseenter")?$this.val():$('[data-action="wooCheckoutButtonBgColor"]').val();
								let tmpBdColor = (e.type === "mouseenter")?$this.val():$('[data-action="wooCheckoutButtonBorderColor"]').val();
								$(this).css({backgroundColor: tmpBgColor, borderColor: tmpBdColor});
							});
							break;
						case 'wooCheckoutButtonTxtColorHover':
							$(".woocommerce-input-wrapper-demo input:not(:checked) + label").hover(function(e) {
								$(this).css("color",e.type === "mouseenter"?$this.val():$('[data-action="wooCheckoutButtonTxtColor"]').val()) 
							});
							break;
					}
					
					$options.change = function(color) {
						if (_id) {
							$('#'+_id).text(color.toString());
							$this.val(color.toString());
						}
						innerColor(color.toString());
					};
					
					$options.move = function(color) {
						innerColor(color.toString());
					};
					
					$options.hide = function(color) {
						innerColor(color.toString());
					};
				}
				
				$this.krspPicker($options);
			}
		}
	};
	
	CORE_TEMP.onReady = {
		init: function () {
			CORE_TEMP.function.KristallSpectrum();
		}
	};
	
	CORE_TEMP.onLoad = {
		init: function () {
           
        }
	};
	
	CORE_TEMP.initialize = {
        ready: function() {
            for (var init in CORE_TEMP.onReady) {
                CORE_TEMP.onReady[init]();
            }
        },
        load: function() {
            for (var init in CORE_TEMP.onLoad) {
                CORE_TEMP.onLoad[init]();
            }
        }
    };
	
	var $window = $(window),
        $windowheight = $(window).height(),
        $body = $('body');
   
   $(document).on('ready', function () {
        if (typeof TempApp === "undefined") {
            CORE_TEMP.initialize.ready();
        }
		
		let kr_connect = {resize: function(){}};
		
		$('a[data-event="kristallConnector"]').on('click', function(){
			let _this = $(this);
			let krflmd = krPanel.modal.create({
				id: 'kristallConnector',
				position: 'center',
				closeOnBackdrop: false,
				resizeit: {
					minWidth: 992,
					minHeight: 510,
					containment: [47, 15, 15, 51]
				},
				dragit: {
					containment: [47, 15, 15, 51]
				},
				maximizedMargin: [15],
				closeOnEscape: true,
				headerTitle: _this.text(),
				headerControls: {
					minimize: 'disable',
					smallify: 'remove'
				},
				contentSize: '992 480',
				theme: 'dark',
				content: '<div id="krflmd"></div>',
				contentOverflow: 'hidden',
				callback: (panel) => {
					panel.content.style.background = '#cdcfd4';
					
					CORE_TEMP.kristall.addResources([
						'/wp-content/plugins/kristall-integration/connector/assets/css/kristall_base.css',
						'/wp-content/plugins/kristall-integration/connector/assets/css/kristall_connector.min.css',
						'/wp-content/plugins/kristall-integration/connector/assets/css/kristall_connector_theme.css',
						'/wp-content/plugins/kristall-integration/connector/assets/js/kristall_base.min.js',
						'/wp-content/plugins/kristall-integration/connector/assets/js/connector/kristall_connector.min.js',
						'/wp-content/plugins/kristall-integration/connector/assets/js/connector/extras/editors.default.min.js'
					]).then(response => {
						kr_connect = $('#krflmd').elfinder(
							{
								cssAutoLoad : false,
								resizable: false,
								sync: 5000,
								height: panel.content.clientHeight,
								baseUrl : '/wp-content/plugins/kristall-integration/connector/assets/',
								url: CORE_TEMP.urlbase + '/wp-admin/admin-ajax.php?action=krConnect',
								lang: 'ru',
								uiOptions: {
									toolbar: [
										['back', 'forward'],
										['mkdir', 'mkfile', 'upload'],
										['open', 'download', 'getfile'],
										['info'],
										['quicklook'],
										['copy', 'cut', 'paste'],
										['rm'],
										['extract', 'archive'],
										['search'],
										['view']
									],
									toolbarExtra: {
										displayTextLabel: 'none',
										preferenceInContextmenu: false
									}
								},
								contextmenu: {
									navbar: ['open', 'download', '|', 'upload', 'mkdir', '|', 'copy', 'cut', 'paste', 'duplicate', '|', 'rm', '|', 'archive', '|', 'places', 'info', 'chmod', 'netunmount'],
									cwd: ['undo', 'redo', '|', 'back', 'up', 'reload', '|', 'upload', 'mkdir', 'mkfile', 'paste', '|', 'view', 'sort', 'selectall', 'colwidth', '|', 'places', 'info', 'chmod', 'netunmount', '|', 'fullscreen'],
									files: ['getfile', '|' ,'open', 'download', 'opendir', 'quicklook', '|', 'upload', 'mkdir', '|', 'copy', 'cut', 'paste', 'duplicate', '|', 'rm', '|', 'rename', 'edit', 'resize', '|', 'archive', 'extract', '|', 'selectall', 'selectinvert', '|', 'places', 'info', 'chmod', 'netunmount']
								}
							},
							function(fm, extraObj) {
								fm.bind('init', function() {
									
								});
							}
						).elfinder('instance');
					});
				}
			});
		});
		
		document.addEventListener('krpanelresize', function(event){
			kr_connect.resize('100%', event.panel.content.clientHeight);
		}, false);
		document.addEventListener('krpanelmaximized', function(event){
			kr_connect.resize('100%', event.panel.content.clientHeight);
		}, false);
		document.addEventListener('krpanelnormalized', function(event){
			kr_connect.resize('100%', event.panel.content.clientHeight);
		}, false);
		document.addEventListener('krpanelresizestop', function(event){
			kr_connect.resize('100%', event.panel.content.clientHeight);
		}, false);
		
		$('a[data-event="packedView"]').on('click', function(){
			let _this = $(this);
			let kr_panel = krPanel.modal.create({
				id: 'packedView',
				position: 'center',
				closeOnBackdrop: false,
				resizeit: {
					minWidth: 992,
					minHeight: 510,
					containment: [47, 15, 15, 51]
				},
				dragit: {
					containment: [47, 15, 15, 51]
				},
				maximizedMargin: [15],
				closeOnEscape: true,
				headerTitle: _this.text(),
				headerControls: {
					minimize: 'disable',
					smallify: 'remove'
				},
				headerToolbar: [
					'<span id="packedViewHeader" class="flex-auto no-select" style="width:100%"><span data-action="hdrBtn" data-event="KrUrlYml" class="krPanel-hdr-btn disabled">Ссылка YML</span><span data-action="hdrBtn" data-event="KrYml" class="krPanel-hdr-btn disabled">Экспорт YML</span><span data-action="hdrBtn" data-event="KrCsv" class="krPanel-hdr-btn disabled">Экспорт CSV</span><span data-action="hdrBtn" data-event="KrCsvFb" class="krPanel-hdr-btn disabled">Экспорт Facebook</span><span data-action="hdrBtn" data-event="XLSXGen" class="krPanel-hdr-btn disabled">Экспорт XLSX</span><span data-action="hdrBtn" data-event="priceList" class="krPanel-hdr-btn disabled">Создать прайс-лист</span><span data-action="hdrBtn" data-event="priceListWord" class="krPanel-hdr-btn disabled">Word</span></span>'
				],
				footerToolbar: '<div class="no-select" style="opacity:.6;font-size:70%;width: 100%;text-transform:uppercase;"><span style="float:left">ГК ТРАНСТРЕЙД</span><span style="float:right;">Выбрано категорий: <span style="min-width:30px;display: inline-block;font-weight:bold;" id="krPanelFbrCatSel">0</span> | Товров: <span style="min-width:30px;display: inline-block;font-weight:bold;" id="krPanelFbrProdSel">0</span></span></div>',
				contentSize: '992 480',
				content: '<div class="pan1 no-select"><div class="pan1-content"><div id="tree" class="krtree-connectors"></div></div><div class="pan1-event"><div class="pan1-event-col-1"><span>Развернуть</span> / <span class="">Свернуть все</span></div><div class="pan1-event-col-2"><span>Выбрать</span> / <span class="disabled">Отменить все</span></div></div><div id="resize"></div></div><div class="pan2"><div class="pan2-content"><div id="panContentHeder" class="pan2-content-heder"></div><div class="pan2-content-scroll"><table id="krTbl" class="display" style="width:100%"> <thead class="no-select"> <tr> <th>Фото</th><th>Наименование товара</th><th>Цена</th><th>Статус</th> </tr> </thead> <tfoot class="no-select"> <tr> <th>Фото</th><th>Наименование товара</th><th>Цена</th><th>Статус</th> </tfoot> </table></div></div></div><div class="pan-overlay"></div>',
				theme: 'dark',
				callback: (panel) => {
					CORE_TEMP.kristall.addResources([
						'/wp-content/plugins/kristall-integration/connector/assets/css/kristall_tables.min.css',
						'/wp-content/plugins/kristall-integration/connector/assets/js/kristall_tables.min.js',
						'/wp-content/plugins/kristall-integration/connector/assets/css/krtree-skin-win8-n/krtree.min.css',
						'/wp-content/plugins/kristall-integration/connector/assets/js/kristall_tree.min.js'
					]).then(response => {
							
						let p = $(".pan1");
						let d = $(".pan2");
						let r = $("#resize");
						
						let curr_width = p.width();
						let krpan = $('#packedView');
						let unlock = false;
						let defWidth = 375;
						
						$(document).mousemove(function(e) {
							let change = curr_width + (e.clientX - curr_width - krpan.offset().left);
							
						    if(unlock) {
									if(change > (defWidth - 1) && change < (krpan.width() - 15)) {
										p.css("width", change);
										d.css("margin-left", change);
									} else {
										p.css("width", defWidth);
										d.css("margin-left", defWidth);
								}
							}
						});
						
						r.mousedown(function(e) {
							curr_width = p.width();
							unlock = true;
							r.css("background-color", "rgba(0, 0, 0, 0.2)");
						});
						
						r.dblclick(function() {
							p.css("width", defWidth);
							d.css("margin-left", defWidth);
						});

						$(document).mousedown(function(e) {
							if(unlock) {
							  e.preventDefault();
							}
						});

						$(document).mouseup(function(e) {
							unlock = false;
							r.css("background-color", "rgba(0, 0, 0, 0)");
						});
						
						let tblLang = {
							"decimal":        "",
							"emptyTable":     "Данные отсутствуют в таблице",
							"info":           "Показано с _START_ по _END_ из _TOTAL_",
							"infoEmpty":      "",
							"infoFiltered":   "(отфильтровано из _MAX_ записей)",
							"infoPostFix":    "",
							"thousands":      ",",
							"lengthMenu":     "По _MENU_ записей",
							"loadingRecords": "Загрузка...",
							"processing":     "Обработка...",
							"search":         "Найти:",
							"zeroRecords":    "По Вашему запросу нет совпадений",
							"paginate": {
								"first":      "Начало",
								"last":       "Конец",
								"next":       "Далее",
								"previous":   "Назад"
							},
							"aria": {
								"sortAscending":  ": activate to sort column ascending",
								"sortDescending": ": activate to sort column descending"
							}
						};
						let tbl = $('#krTbl').krTbl({language:tblLang,columnDefs: [{targets: 0,bSortable: false}],order: [[1,'asc']]});
						let tblApi;
						
						let tree = $("#tree").krtree({
							checkbox: true,
							clickFolderMode: 4,
							selectMode: 3,
							debugLevel: 0,
							tabindex: -1,
							aria: false,
							source: {url: "/wp-admin/admin-ajax.php?action=getCatTree", cache: false},
							strings: {
								loading: "Загрузка...", // &#8230; would be escaped when escapeTitles is true
								loadError: "Ошибка: загрузка не удалась!",
								moreData: "Ещё...",
								noData: "Нет данных.",
							},
							init: function(event, data) {
								let first = data.tree.getFirstChild();
								
								tbl.destroy();
								$('#krTbl').krTbl({
									columnDefs: [{
										targets: 0,
										width: '80px',
										searchable: false,
										fixedColumns: true,
										bSortable: false,
										className: 'krtbl-custom-photo',
										render: function (data, type, row) {
											let noPhoto = '/wp-content/plugins/kristall-integration/connector/assets/images/no_photo.png';
											let photo = data ? data : noPhoto,
												thumb = row[9] ? row[9] : noPhoto;
											return '<img width="auto" height="44" style="max-width:80px" src="'+thumb+'" data-src="'+photo+'" />'
										}
									},{
										targets: 1
										
									},{
										targets: 2,
										className: 'krtbl-custom-currency',
										render: function (data, type, row) {
											let listCurrensy = {'RUB': '₽','USD': '$','EUR': '€','UAH': '₴','CNY': '¥','JPY': '¥','GBP': '£'},
												defCurrensy = '¤';
											return data+' '+(listCurrensy.hasOwnProperty(row[4]) ? listCurrensy[row[4]] : defCurrensy);
										}
									},{
										targets: 3,
										className: 'krtbl-custom-status',
										render: function (data, type, row) {
											let listStatus = {'publish': 'опубликован','future': 'запланирован','draft': 'черновик','pending': 'на модерации','private': 'приватный','trash': 'в корзине','auto-draft': 'редактируется', 'inherit': 'вложение'},
												defStatus = 'NAN';
											let rtn = listStatus.hasOwnProperty(data) ? listStatus[data] : defStatus;
											return '<span class="kr-badge kr-badge-'+data.toLowerCase()+'">'+rtn+'</span';
										}
									}],
									order: [[1,'asc']],
									lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Все"]],
									language: tblLang,
									select: true,
									ajax: {
										url: '/wp-admin/admin-ajax.php?action=getCatProducts&cat_id=' + btoa(first.data.term_id),
										dataSrc: 'response',
										beforeSend: function(resp){
										//	tree.krtree("option", "disabled", true);
											$('#panContentHeder').html('<p>'+tblLang.loadingRecords+'</p>');
										},
										complete: function(resp){
											let node = $.ui.krtree.getTree("#tree");
											let sled = node.getSelectedNodes();
											let nodeActive = node.getActiveNode() || first;
											let itemCount = 0;
											
											
											if (sled.length) {
												for(let i=0;i<sled.length;i++){
													itemCount += (sled[i].data.count * 1);
												}
												$('#panContentHeder').html('<p>Выбрано категорий: '+sled.length+' | Товаров: '+ itemCount +'</p>');
											} else {
												itemCount = 0;
												$('#panContentHeder').html('<p>'+nodeActive.title+'</p>');
											}
											
											/*
											$('#krTbl tbody').on('click', 'tr', function () {
												tbl.$('tr.selected').removeClass('selected');
												$(this).addClass('selected');
											});
											*/
										}
									}
									
								});
								
								tblApi = new $.fn.krTbl.Api('#krTbl');
							},
							activate: function(event, data) {
								let item = data.node.data;
								let selNodes = data.tree.getSelectedNodes();
								
								if (selNodes.length) return false;
								
							//	if (item.count) {
								//	tbl.destroy();
									/* $('#krTbl').empty(); */
									tblApi.clear().draw();
									$('.krtbls_empty').text(tblLang.loadingRecords);
									tblApi.ajax.url('/wp-admin/admin-ajax.php?action=getCatProducts&cat_id=' + btoa(item.term_id)).load();
							//	}
							},
							
							select: function(event, data) {
								let key = data.node.key;
								let actv = data.tree.getFirstChild();
								let selNodes = data.tree.getSelectedNodes();
								let sldt = $.map(selNodes, (n) => n.toDict());
								let cats = [];
								let itemCount = 0;
								
								for(let i=0; i<sldt.length; i++) {
									cats.push(sldt[i].data.term_id);
								}
								
								data.tree.activateKey(key);
								tblApi.clear().draw();
								
								if (selNodes.length) {
									
									for(let i=0;i<sldt.length;i++){
										itemCount += (sldt[i].data.count * 1);
									}
									
									$('.krtbls_empty').text(tblLang.loadingRecords);
									tblApi.ajax.url('/wp-admin/admin-ajax.php?action=getCatProducts&only=1&cat_id=' + btoa(cats.join(','))).load();
								} else {
									itemCount = 0;
									actv.setActive();
								}
								
								$('#krPanelFbrCatSel').text(selNodes.length);
								$('#krPanelFbrProdSel').text(itemCount);
								pnOneEvent();
							}
						});
						
						kr_panel.options.onclosed.push(function(panel, closedByUser) {
							response.forEach(function(item, i, arr) {
								$('#'+item).remove();
							});
						});
						
						// do something ...
						$('#packedViewHeader span[data-action="hdrBtn"]').on('click', function () {
							let data = $(this).data();
							let sled = $.ui.krtree.getTree("#tree").getSelectedNodes();
							let sldt = $.map(sled, (n) => n.toDict());
							let cats = [];
							for(let i=0; i<sldt.length; i++) {
								cats.push(sldt[i].data.term_id);
							}
							switch(data.event) {
								case 'KrYml':
									window.open(CORE_TEMP.urlbase + '/wp-admin/admin-ajax.php?action=createKrYml&cats='+btoa(cats.join(',')), '_blank');
									break;
								case 'KrUrlYml':
									let pubUrl = CORE_TEMP.urlbase + '/wp-admin/admin-ajax.php?action=createKrYml&cats='+btoa(cats.join(','));
									krPanel.modal.create({
										id: 'packedUrlView',
										headerTitle: 'Ваша YML ссылка',
										position: 'center',
										theme: 'dark',
										contentSize: '400 153',
										content: '<div class="urlYml"><input id="krCopyUrlYml" type="text" style="width:calc(100% - 10px);margin:15px 5px;" class="form-control" value="'+pubUrl+'" aria-label="You URL onYML file" aria-describedby="button-ymlUrl" readonly="true"></div><button id="krCopyUrlYmlAvent" type="button" name="button" style="float:right; margin-right:5px" class="button-primary">Копировать в буфер обмена</button><div id="urlYmlInfo" class="urlYmlInfo" style="margin-top:45px; text-align:center; color:#fff; background-color:#4caf50; padding: 15px 5px; font-size:80%; display:none">Ваша ссылка скопирована в буфер обмена!</div>',
										callback: (panel) => {
											$('#krCopyUrlYmlAvent').on('click', function(){
												let copyText = document.getElementById("krCopyUrlYml");
												copyText.select();
												copyText.setSelectionRange(0, 99999);
												document.execCommand("copy");
												$("#urlYmlInfo").show(0).delay(1000).hide(0);
											});
										},
										footerToolbar: (panel) => {
											let itemCount = 0;
											for(let i=0;i<sled.length;i++){
												itemCount += (sled[i].data.count * 1);
											}
											return '<div style="font-size:80%;flex: 1 1 auto;-ms-flex: 1 1 auto" class="">Выбрано категорий: <span>'+sled.length+'</span></div><div style="font-size:80%;" class="">Товаров: <span>'+itemCount+'</span></div>';
										}
									});
									break;
								case 'KrCsv':
									window.open(CORE_TEMP.urlbase + '/wp-admin/admin-ajax.php?action=createCsv&cats='+btoa(cats.join(',')), '_blank');
									break;
								case 'KrCsvFb':
									window.open(CORE_TEMP.urlbase + '/wp-admin/admin-ajax.php?action=createCsvFb&cats='+btoa(cats.join(',')), '_blank');
									break;
								case 'priceList':
									window.open(CORE_TEMP.urlbase + '/wp-admin/admin-ajax.php?action=createPdf&cats='+btoa(cats.join(',')), '_blank');
									break;
								case 'priceListWord':
									window.open(CORE_TEMP.urlbase + '/wp-admin/admin-ajax.php?action=createWord&cats='+btoa(cats.join(',')), '_blank');
									break;
								case 'XLSXGen':
									window.open(CORE_TEMP.urlbase + '/wp-admin/admin-ajax.php?action=createXLS&cats='+btoa(cats.join(',')), '_blank');
									break;
							}
						});
						
						let pnOneEvent = function(){
							let onAllBtn = $('.pan1 .pan1-event-col-2 span:eq(0)'),
								offAllBtn = $('.pan1 .pan1-event-col-2 span:eq(1)'),
								hdrBtn = $('#packedViewHeader span[data-action="hdrBtn"]');
								
							let tree = $.ui.krtree.getTree("#tree");
							let count = tree.count();
							let selNodes = tree.getSelectedNodes();
							let selData = $.map(selNodes, function(n){
								return n.toDict();
							});
							
							$('.pan1 .pan1-event-col-2 span').removeClass('disabled');
							hdrBtn.removeClass('disabled');
							
							if (count == selData.length) {
								onAllBtn.addClass('disabled');
							} else if (selData.length == 0) {
								offAllBtn.addClass('disabled');
								hdrBtn.addClass('disabled');
							}

						};
						
						$('.pan1 .pan1-event-col-1 span:eq(0)').on('click', function(){
							$.ui.krtree.getTree("#tree").expandAll();
						});
						
						$('.pan1 .pan1-event-col-1 span:eq(1)').on('click', function(){
							$.ui.krtree.getTree("#tree").expandAll(false);
						});
						
						$('.pan1 .pan1-event-col-2 span:eq(0)').on('click', function(){
							$.ui.krtree.getTree("#tree").selectAll();
							pnOneEvent();
						});
						
						$('.pan1 .pan1-event-col-2 span:eq(1)').on('click', function(){
							let node = $.ui.krtree.getTree("#tree");
							let actv = node.getFirstChild();
							node.selectAll(false);
							actv.setActive();
							
							pnOneEvent();
						});
						
						
					}).catch(error => {
						console.log('err');
					});
				}
			});
			
		});
    });
	
    $window.load(function () {
        if (typeof TempApp === "undefined") {
            CORE_TEMP.initialize.load();
        }
    });
	
})(jQuery);