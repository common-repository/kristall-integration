<?php

defined('ABSPATH') || exit;
defined('KRISTALL_INTEGRATION_MAIN_DIR') || exit;

if (!defined('KRISTALL_INTEGRATION_CONNECTOR_ENABLED')) {
  define('KRISTALL_INTEGRATION_CONNECTOR_ENABLED', true);
}

// ----------------------------------------------------------------------------------
// Внимание !!!
// Необходимо выполнить следующие комманды из корневой директории wordpress:
/*
chmod 755 wp-content/uploads/docs
chmod 755 wp-content/uploads/tmp
chmod 755 wp-content/uploads/krflmd
chmod 755 wp-content/uploads/krflmd/.trash
chmod 755 wp-content/uploads/krflmd/.trash/.tmb
chown apache wp-content/uploads/docs
chown apache wp-content/uploads/tmp
chown apache wp-content/uploads/krflmd
chown apache wp-content/uploads/krflmd/.trash
chown apache wp-content/uploads/krflmd/.trash/.tmb
*/

// if(is_admin()) {
  // Создаем необходимые директории

  $folders = ['docs', 'tmp', 'krflmd', 'krflmd/.trash', 'krflmd/.trash/.tmb'];

  if (!is_dir(ABSPATH . 'wp-content/')) {
    mkdir(ABSPATH . 'wp-content/', 0755, true);
  }

  if (!is_dir(ABSPATH . 'wp-content/uploads/')) {
    mkdir(ABSPATH . 'wp-content/uploads/', 0755, true);
  }

  foreach($folders as $fld) {
    $dir = ABSPATH . 'wp-content/uploads/' . $fld;
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
  }

  //  Файловый менеджер и создание файлов csv xml pdf ...
  require_once(KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-settings.php');
  require_once(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/components/wp_file_creator.php');
  require_once(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/components/tm_filepicker.php');
  require_once(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/components/woo_ajax_taxonomy.php');

  # /wp-admin/admin-ajax.php?action=krConnect
	add_action('wp_ajax_krConnect', function() {
  // // Optional exec path settings (Default is called with command name only)
  // define('ELFINDER_TAR_PATH',      '/PATH/TO/tar');
  // define('ELFINDER_GZIP_PATH',     '/PATH/TO/gzip');
  // define('ELFINDER_BZIP2_PATH',    '/PATH/TO/bzip2');
  // define('ELFINDER_XZ_PATH',       '/PATH/TO/xz');
  // define('ELFINDER_ZIP_PATH',      '/PATH/TO/zip');
  // define('ELFINDER_UNZIP_PATH',    '/PATH/TO/unzip');
  // define('ELFINDER_RAR_PATH',      '/PATH/TO/rar');
  // define('ELFINDER_UNRAR_PATH',    '/PATH/TO/unrar');
  // define('ELFINDER_7Z_PATH',       '/PATH/TO/7za');
  // define('ELFINDER_CONVERT_PATH',  '/PATH/TO/convert');
  // define('ELFINDER_IDENTIFY_PATH', '/PATH/TO/identify');
  // define('ELFINDER_EXIFTRAN_PATH', '/PATH/TO/exiftran');
  // define('ELFINDER_JPEGTRAN_PATH', '/PATH/TO/jpegtran');
  // define('ELFINDER_FFMPEG_PATH',   '/PATH/TO/ffmpeg');

  // define('ELFINDER_CONNECTOR_URL', 'URL to this connector script');  // see elFinder::getConnectorUrl()

  // define('ELFINDER_DEBUG_ERRORLEVEL', -1); // Error reporting level of debug mode

  // // To Enable(true) handling of PostScript files by ImageMagick
  // // It is disabled by default as a countermeasure 
  // // of Ghostscript multiple -dSAFER sandbox bypass vulnerabilities
  // // see https://www.kb.cert.org/vuls/id/332928
  // define('ELFINDER_IMAGEMAGICK_PS', true);
  // ===============================================

  // load composer autoload before load elFinder autoload If you need composer
  // You need to run the composer command in the php directory.
  if (is_readable(KRISTALL_INTEGRATION_MAIN_DIR . '/connector/krflmd/autoload.php')) {
    require KRISTALL_INTEGRATION_MAIN_DIR . '/connector/krflmd/autoload.php';
  }

  // elFinder autoload
  require_once(KRISTALL_INTEGRATION_MAIN_DIR . '/connector/krflmd/autoload.php');
  // ===============================================

  // Enable FTP connector netmount
  elFinder::$netDrivers['ftp'] = 'FTP';
  // ===============================================

  // // Required for Dropbox network mount
  // // Installation by composer
  // // `composer require kunalvarma05/dropbox-php-sdk` on php directory
  // // Enable network mount
  // elFinder::$netDrivers['dropbox2'] = 'Dropbox2';
  // // Dropbox2 Netmount driver need next two settings. You can get at https://www.dropbox.com/developers/apps
  // // AND require register redirect url to "YOUR_CONNECTOR_URL?cmd=netmount&protocol=dropbox2&host=1"
  // // If the elFinder HTML element ID is not "elfinder", you need to change "host=1" to "host=ElementID"
  // define('ELFINDER_DROPBOX_APPKEY',    '');
  // define('ELFINDER_DROPBOX_APPSECRET', '');
  // ===============================================

  // // Required for Google Drive network mount
  // // Installation by composer
  // // `composer require google/apiclient:^2.0` on php directory
  // // Enable network mount
  // elFinder::$netDrivers['googledrive'] = 'GoogleDrive';
  // // GoogleDrive Netmount driver need next two settings. You can get at https://console.developers.google.com
  // // AND require register redirect url to "YOUR_CONNECTOR_URL?cmd=netmount&protocol=googledrive&host=1"
  // // If the elFinder HTML element ID is not "elfinder", you need to change "host=1" to "host=ElementID"
  // define('ELFINDER_GOOGLEDRIVE_CLIENTID',     '');
  // define('ELFINDER_GOOGLEDRIVE_CLIENTSECRET', '');
  // // Required case when Google API is NOT added via composer
  // define('ELFINDER_GOOGLEDRIVE_GOOGLEAPICLIENT', '/path/to/google-api-php-client/vendor/autoload.php');
  // ===============================================

  // // Required for Google Drive network mount with Flysystem
  // // Installation by composer
  // // `composer require nao-pon/flysystem-google-drive:~1.1 nao-pon/elfinder-flysystem-driver-ext` on php directory
  // // Enable network mount
  // elFinder::$netDrivers['googledrive'] = 'FlysystemGoogleDriveNetmount';
  // // GoogleDrive Netmount driver need next two settings. You can get at https://console.developers.google.com
  // // AND require register redirect url to "YOUR_CONNECTOR_URL?cmd=netmount&protocol=googledrive&host=1"
  // // If the elFinder HTML element ID is not "elfinder", you need to change "host=1" to "host=ElementID"
  // define('ELFINDER_GOOGLEDRIVE_CLIENTID',     '');
  // define('ELFINDER_GOOGLEDRIVE_CLIENTSECRET', '');
  // // And "php/.tmp" directory must exist and be writable by PHP.
  // ===============================================

  // // Required for One Drive network mount
  // //  * cURL PHP extension required
  // //  * HTTP server PATH_INFO supports required
  // // Enable network mount
  // elFinder::$netDrivers['onedrive'] = 'OneDrive';
  // // GoogleDrive Netmount driver need next two settings. You can get at https://dev.onedrive.com
  // // AND require register redirect url to "YOUR_CONNECTOR_URL/netmount/onedrive/1"
  // // If the elFinder HTML element ID is not "elfinder", you need to change "/1" to "/ElementID"
  // define('ELFINDER_ONEDRIVE_CLIENTID',     '');
  // define('ELFINDER_ONEDRIVE_CLIENTSECRET', '');
  // ===============================================

  // // Required for Box network mount
  // //  * cURL PHP extension required
  // // Enable network mount
  // elFinder::$netDrivers['box'] = 'Box';
  // // Box Netmount driver need next two settings. You can get at https://developer.box.com
  // // AND require register redirect url to "YOUR_CONNECTOR_URL?cmd=netmount&protocol=box&host=1"
  // // If the elFinder HTML element ID is not "elfinder", you need to change "host=1" to "host=ElementID"
  // define('ELFINDER_BOX_CLIENTID',     '');
  // define('ELFINDER_BOX_CLIENTSECRET', '');
  // ===============================================


  // // Zoho Office Editor APIKey
  // // https://www.zoho.com/docs/help/office-apis.html
  // define('ELFINDER_ZOHO_OFFICE_APIKEY', '');
  // ===============================================

  // // Online converter (online-convert.com) APIKey
  // // https://apiv2.online-convert.com/docs/getting_started/api_key.html
  // define('ELFINDER_ONLINE_CONVERT_APIKEY', '');
  // ===============================================

  // // Zip Archive editor
  // // Installation by composer
  // // `composer require nao-pon/elfinder-flysystem-ziparchive-netmount` on php directory
  // define('ELFINDER_DISABLE_ZIPEDITOR', false); // set `true` to disable zip editor
  // ===============================================

  /**
   * Simple function to demonstrate how to control file access using "accessControl" callback.
   * This method will disable accessing files/folders starting from '.' (dot)
   *
   * @param  string    $attr    attribute name (read|write|locked|hidden)
   * @param  string    $path    absolute file path
   * @param  string    $data    value of volume option `accessControlData`
   * @param  object    $volume  elFinder volume driver object
   * @param  bool|null $isDir   path is directory (true: directory, false: file, null: unknown)
   * @param  string    $relpath file path relative to volume root directory started with directory separator
   * @return bool|null
   **/
  function access($attr, $path, $data, $volume, $isDir, $relpath) {
    $basename = basename($path);
    return $basename[0] === '.' && strlen($relpath) !== 1 ? !($attr == 'read' || $attr == 'write') :  null;
  }
		
		$opts = array(
			'debug' => false,
			'roots' => array(
				array(
					'driver'        => 'LocalFileSystem',
					'path'          => ABSPATH . 'wp-content/uploads/krflmd/',
					'URL'           => '/wp-content/uploads/krflmd/',
//					'trashHash'     => 't1_Lw',
					'winHashFix'    => DIRECTORY_SEPARATOR !== '/',
					'uploadDeny'    => array('all'),
					'alias'         => 'Публичные файлы',
					'uploadAllow'   => array('image/x-ms-bmp', 'image/gif', 'image/jpeg', 'image/png', 'image/x-icon', 'image/tiff', 'image/webp', 'text/plain', 'text/csv', 'audio/mp4', 'audio/x-wav', 'audio/x-flac', 'audio/webm', 'audio/x-aac', 'video/webm', 'video/mp4', 'video/h264', 'video/x-flv', 'application/zip', 'application/xml-dtd', 'application/xml', 'application/x-bzip', 'application/x-bzip2', 'application/x-7z-compressed', 'application/x-rar-compressed','application/vnd.openxmlformats-officedocument.wordprocessingml.template', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.template', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.template', 'application/vnd.openxmlformats-officedocument.presentationml.slideshow', 'application/vnd.openxmlformats-officedocument.presentationml.slide', 'application/vnd.openxmlformats-officedocument.presentationml.presentation','application/pdf','application/msword','application/vnd.ms-excel','application/vnd.ms-powerpoint'),
					'uploadOrder'   => array('deny', 'allow'),
					'accessControl' => 'access'
				),
				array(
					'driver'        => 'LocalFileSystem',
					'path'          => ABSPATH . 'wp-content/uploads/docs/',
					'URL'           => '/wp-content/uploads/docs/',
//					'trashHash'     => 't1_Lw',
					'winHashFix'    => DIRECTORY_SEPARATOR !== '/',
					'uploadDeny'    => array('all'),
					'alias'         => 'Файлы для e-mail',
					'uploadAllow'   => array('image/x-ms-bmp', 'image/gif', 'image/jpeg', 'image/png', 'image/x-icon', 'image/tiff', 'image/webp', 'text/plain', 'text/csv', 'audio/mp4', 'audio/x-wav', 'audio/x-flac', 'audio/webm', 'audio/x-aac', 'video/webm', 'video/mp4', 'video/h264', 'video/x-flv', 'application/zip', 'application/xml-dtd', 'application/xml', 'application/x-bzip', 'application/x-bzip2', 'application/x-7z-compressed', 'application/x-rar-compressed','application/vnd.openxmlformats-officedocument.wordprocessingml.template', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.template', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.template', 'application/vnd.openxmlformats-officedocument.presentationml.slideshow', 'application/vnd.openxmlformats-officedocument.presentationml.slide', 'application/vnd.openxmlformats-officedocument.presentationml.presentation','application/pdf','application/msword','application/vnd.ms-excel','application/vnd.ms-powerpoint'),
					'uploadOrder'   => array('deny', 'allow'),
					'defaults'      => array('read' => true, 'write' => false),
					'accessControl' => 'access'
				),
				array(
					'driver'        => 'LocalFileSystem',
					'path'          => ABSPATH . 'wp-content/uploads/tmp/',
					'URL'           => '/wp-content/uploads/tmp/',
//					'trashHash'     => 't1_Lw',
					'winHashFix'    => DIRECTORY_SEPARATOR !== '/',
					'uploadDeny'    => array('all'),
					'alias'         => 'Временные файлы',
					'uploadAllow'   => array('image/x-ms-bmp', 'image/gif', 'image/jpeg', 'image/png', 'image/x-icon', 'image/tiff', 'image/webp', 'text/plain', 'text/csv', 'audio/mp4', 'audio/x-wav', 'audio/x-flac', 'audio/webm', 'audio/x-aac', 'video/webm', 'video/mp4', 'video/h264', 'video/x-flv', 'application/zip', 'application/xml-dtd', 'application/xml', 'application/x-bzip', 'application/x-bzip2', 'application/x-7z-compressed', 'application/x-rar-compressed','application/vnd.openxmlformats-officedocument.wordprocessingml.template', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.template', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.template', 'application/vnd.openxmlformats-officedocument.presentationml.slideshow', 'application/vnd.openxmlformats-officedocument.presentationml.slide', 'application/vnd.openxmlformats-officedocument.presentationml.presentation','application/pdf','application/msword','application/vnd.ms-excel','application/vnd.ms-powerpoint'),
					'uploadOrder'   => array('deny', 'allow'),
					'accessControl' => 'access'
				),
				 array(
					'id'           => '9',
					'alias'        => 'Облачные диски',
					'driver'       => 'Group',
					'rootCssClass' => 'elfinder-navbar-root-network'
				),
				// Trash volume
				array(
					'id'            => '10',
					'driver'        => 'Trash',
					'path'          => ABSPATH . 'wp-content/uploads/krflmd/.trash/',
					'tmbURL'        => '/wp-content/uploads/krflmd/.trash/.tmb/',
					'winHashFix'    => DIRECTORY_SEPARATOR !== '/',
					'uploadDeny'    => array('all'),
					'uploadAllow'   => array('image/x-ms-bmp', 'image/gif', 'image/jpeg', 'image/png', 'image/x-icon', 'image/tiff', 'image/webp', 'text/plain', 'text/csv', 'audio/mp4', 'audio/x-wav', 'audio/x-flac', 'audio/webm', 'audio/x-aac', 'video/webm', 'video/mp4', 'video/h264', 'video/x-flv', 'application/zip', 'application/xml-dtd', 'application/xml', 'application/x-bzip', 'application/x-bzip2', 'application/x-7z-compressed', 'application/x-rar-compressed','application/vnd.openxmlformats-officedocument.wordprocessingml.template', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.template', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.template', 'application/vnd.openxmlformats-officedocument.presentationml.slideshow', 'application/vnd.openxmlformats-officedocument.presentationml.slide', 'application/vnd.openxmlformats-officedocument.presentationml.presentation','application/pdf','application/msword','application/vnd.ms-excel','application/vnd.ms-powerpoint'), // Same as above
					'uploadOrder'   => array('deny', 'allow'),
					'accessControl' => 'access'
				),
			),
			'optionsNetVolumes' => array(
				'*' => array(
					'tmbURL'    => '/wp-content/uploads/files/.tmbNetmount/',
					'tmbPath'   => '/wp-content/uploads/files/.tmbNetmount/',
					'tmbGcMaxlifeHour' => 1,
					'tmbGcPercentage'  => 10,
					'plugin' => array(
						'AutoResize' => array(
							'enable' => false
						),
						'Watermark' => array(
							'enable' => false
						),
						'Normalizer' => array(
							'enable' => false
						),
						'Sanitizer' => array(
							'enable' => false
						)
					),
				)
			)
		);
		
		$connector = new elFinderConnector(new elFinder($opts));
		$connector->run();
		
		wp_die();
	});

  function admin_add_extended_controls($page_id) {
    $section_id = 'krl_extended_functionality';

    add_settings_section(
			$section_id,
			'Расширенные возможности',
			null,
			$page_id
		);

    add_settings_field(
      'krl_extended_functionality_buttons',
      'Дополнительный сервис',
      'krl_extended_functionality_setup',
      $page_id,
      $section_id
    );
    
    add_settings_field(
      'krl_extended_functionality_settings',
      'Настройки сервиса',
      'krl_extended_functionality_settings_setup',
      $page_id,
      $section_id
    );
  }

  function krl_extended_functionality_setup(){
    // CORE_TEMP.urlpath
    
    echo '<a class="button button-primary" href="#" onclick="return false" data-event="packedView">Пакетная обработка товаров</a>';
    echo '<a style="margin-left:15px" class="button button-primary" href="#" onclick="return false" data-event="kristallConnector">Менеджер файлов</a>';
  }  

  function krl_extended_functionality_settings_setup() {
    $options = Kristall_Integration_Settings::get_plugin_settings();
    $options_name = $options['option_name'];

    $val = get_option($options_name);
      $val_compName = isset($val['compName']) ? $val['compName'] : get_bloginfo('name');
      $val_compDesc = isset($val['compDesc']) ? $val['compDesc'] : get_bloginfo('description');

      $val_headerLogo = isset($val['headerLogo']) ? $val['headerLogo'] : KRISTALL_INTEGRATION_PLUGIN_URL . '/connector/assets/images/logo.png';
      $val_compINN = isset($val['compINN']) ? $val['compINN'] : '';
      $val_compOGRNType = isset($val['compOGRNType']) ? $val['compOGRNType'] : 'ОГРН';
      $val_compOGRN = isset($val['compOGRN']) ? $val['compOGRN'] : '';
      $val_compHeadTxt = isset($val['compHeadTxt']) ? $val['compHeadTxt'] : '';
      $val_compPhone = isset($val['compPhone']) ? $val['compPhone'] : '';
      $val_compEmail = isset($val['compEmail']) ? $val['compEmail'] : get_bloginfo('admin_email');
        
    $defUrl = explode('://', get_bloginfo('wpurl'));
    
    $val_compUrlPrt = isset($val['compUrlPrt']) ? $val['compUrlPrt'] : $defUrl[0];
      $val_compUrl = isset($val['compUrl']) ? $val['compUrl'] : $defUrl[1];
      $val_compDirector = isset($val['compDirector']) ? $val['compDirector'] : '';
      $val_compPos = isset($val['compPos']) ? $val['compPos'] : '';    
      ?>
    <fieldset>
      <input type="text" name="<?php echo $options_name; ?>[compName]" value="<?php echo esc_attr($val_compName); ?>" style="width: 30%;" />
      <p class="description" id="compName">Укажите название вашей компании, по умолчанию <i>название сайта Wordpress</i>.</p></br>
      
        <input type="text" name="<?php echo $options_name; ?>[compDesc]" value="<?php echo esc_attr($val_compDesc); ?>" style="width: 30%;" />
        <p class="description" id="compDesc">Укажите краткое описание вашей компании, по умолчанию <i>краткое описание Wordpress</i>.</p>
      </br>
      
        <img class="headerLogo" src="<?php echo $val_headerLogo; ?>" height="80"></br>
        <input class="headerLogo_url" type="text" name="<?php echo $options_name; ?>[headerLogo]" value="<?php echo esc_attr($val_headerLogo); ?>" style="width: 25%;">
        <a href="#" class="headerLogo_upload">Загрузить</a>
        <p class="description" id="headerLogo">Загрузите логотип. Рекомендуемый размер <i>709х247px</i>.</p>
      </br>
      
        <input type="text" name="<?php echo $options_name; ?>[compINN]" value="<?php echo esc_attr($val_compINN); ?>" style="width: 30%;" />
        <p class="description" id="compINN">Укажите ИНН/КПП, например, 2703044904/270301001.</p>
      </br>
      
        <select name="<?php echo $options_name; ?>[compOGRNType]" id="compOGRNType" style="width: 6%;">
          <?php
            $ogrnTypes = array('ОГРН','ОГРНИП');
            foreach($ogrnTypes as $key=>$ogrnType) {
              $selected = $ogrnType == $val_compOGRNType ? ' selected="selected"' : '';
              echo '<option value="'.$key.'"'.$selected.'>'.$ogrnType.'</option>';
            }
          ?>
        </select>
        <input type="text" name="<?php echo $options_name; ?>[compOGRN]" value="<?php echo esc_attr($val_compOGRN); ?>" style="width: 24%;" />
        <p class="description" id="compOGRN">Укажите ОГРН, например, 082703000863.</p>
      </br>
      
        <textarea name="<?php echo $options_name; ?>[compHeadTxt]" type="text" id="content" rows="3" style="width: 30%;"><?php echo esc_attr($val_compHeadTxt); ?></textarea>
        <p class="description" id="compHeadTxt">Дополнительное описание. Для переноса строки используйте <b>\n</b></p>
      </br>
      
        <input type="text" name="<?php echo $options_name; ?>[compPhone]" value="<?php echo esc_attr($val_compPhone); ?>" style="width: 30%;" />
        <p class="description" id="compPhone">Укажите основной номер телефона, например, +7 (4217) 201-09.</p>
      </br>
      
        <input type="text" name="<?php echo $options_name; ?>[compEmail]" value="<?php echo esc_attr($val_compEmail); ?>" style="width: 30%;" />
        <p class="description" id="compEmail">Укажите эл. почту, по умолчанию <i>эл.почта администратора</i>.</p>
      </br>
      
        <select name="<?php echo $options_name; ?>[compUrlPrt]" id="compUrlPrt" style="width: 6%;">
          <?php  
            $protocols = array('https','http');
            foreach($protocols as $protocol) {
              $selected = $protocol == $val_compUrlPrt ? ' selected="selected"' : '';
              echo '<option value="'.$protocol.'"'.$selected.'>'.$protocol.'://</option>';
            }
          ?>
        </select>
        <input type="text" name="<?php echo $options_name; ?>[compUrl]" value="<?php echo esc_attr($val_compUrl); ?>" style="width: 24%;" />
        <p class="description" id="compUrl">Укажите адрес сайта, по умолчанию <i>адрес сайта Wordpress</i>.</p>
      </br>
      
        <input type="text" name="<?php echo $options_name; ?>[compDirector]" value="<?php echo esc_attr($val_compDirector); ?>" style="width: 30%;" />
        <p class="description" id="compDirector">Укажите ФИО руководителя, например, Иванов Иван Иванович.</p>
      </br>
      
        <input type="text" name="<?php echo $options_name; ?>[compPos]" value="<?php echo esc_attr($val_compPos); ?>" style="width: 30%;" />
        <p class="description" id="compPos">Укажите должность руководителя, например, Генеральный директор.</p>
      
    </fieldset>
      <?php
  }

  function krl_extended_functionality_enqueue_assets() {
    $path_panel_css = KRISTALL_INTEGRATION_PLUGIN_URL . '/connector/assets/css/kristall_panel.min.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION;
    $path_panel_js  = KRISTALL_INTEGRATION_PLUGIN_URL . '/connector/assets/js/kristall_panel.min.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION;
    $path_panel_mod_js = KRISTALL_INTEGRATION_PLUGIN_URL . '/connector/assets/js/extensions/kristall_panel_modal.min.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION;
    $path_krsp_css = KRISTALL_INTEGRATION_PLUGIN_URL . '/connector/assets/css/kristall_spectrum.min.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION;
    $path_krsp_js  = KRISTALL_INTEGRATION_PLUGIN_URL . '/connector/assets/js/kristall_spectrum.min.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION;
    $path_main_css = KRISTALL_INTEGRATION_PLUGIN_URL . '/connector/assets/css/kristall_main.min.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION;
    $path_main_js  = KRISTALL_INTEGRATION_PLUGIN_URL . '/connector/assets/js/kristall_admin_main.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION;
    $path_logo_select_js  = KRISTALL_INTEGRATION_PLUGIN_URL . '/connector/assets/js/kristall_logo_select.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION;
    
    wp_enqueue_style( 'kristall_panel', $path_panel_css, array());
    wp_enqueue_script('kristall_panel', $path_panel_js, array('jquery'));
    wp_enqueue_script('kristall_panel_modal', $path_panel_mod_js, array('jquery','kristall_panel'));
    wp_enqueue_style( 'kristall_spectrum', $path_krsp_css, array());
    wp_enqueue_script('kristall_spectrum', $path_krsp_js, array('jquery'));
    wp_enqueue_style( 'kristall_main', $path_main_css, array());
    wp_enqueue_script('kristall_main', $path_main_js, array('jquery'));  
    wp_enqueue_script('path_logo_select_js', $path_logo_select_js, array('jquery'));  

    if(function_exists( 'wp_enqueue_media' )){
      wp_enqueue_media();
    }else{
      wp_enqueue_style('thickbox');
      wp_enqueue_script('media-upload');
      wp_enqueue_script('thickbox');
    }
  }
// }
