<?php

defined('KRISTALL_INTEGRATION_CONNECTOR_ENABLED') || exit;

require_once(KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-settings.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!function_exists('wp_date')) {
	function wp_date($format, $timestamp = null, $timezone = null) {
		return date_i18n($format, $timestamp, $timezone);
	}
}

# /wp-admin/admin-ajax.php?action=createPdf

// if(is_admin()) {
	add_action('wp_ajax_createPdf', function() {

		define('FPDF_FONTPATH', KRISTALL_INTEGRATION_MAIN_DIR . 'connector/fpdf182/font');
		require_once(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/fpdf182/fpdf.php');
		
		final class PDF extends FPDF {
			private $logo = '';
			private $signature = '';
			private $imgPhone = '';
			private $imgMail = '';
			private $imgGlobal = '';
			private $urlGlobal = '';
			private $dtCurrent = '';
			private $dtNowFl = '';
			private $cats;
			public $compName = '';
			public $compDesc = '';
			private $compINN = '';
			private $compOGRNType = '';
			private $compOGRN = '';
			private $compHeadTxt = '';
			private $compPhone = '';
			private $compEmail = '';
			private $compUrlPrt = '';
			public $compUrl = '';
			public $compDirector = '';
			public $compPos = '';
			
			private $fileName;
			
			function __construct() {
				$options = Kristall_Integration_Settings::get_plugin_settings();
				$options_name = $options['option_name'];
				$kropt = get_option($options_name);
				
				$this->compName = isset($kropt['compName']) ? $kropt['compName'] : '';
				$this->compDesc = isset($kropt['compDesc']) ? $kropt['compDesc'] : '';
				$this->compINN = isset($kropt['compINN']) ? $kropt['compINN'] : '';
				$this->compOGRNType = $kropt['compOGRNType']==0 ? 'ОГРН' : 'ОГРНИП';
				$this->compOGRN = isset($kropt['compOGRN']) ? $kropt['compOGRN'] : '';
				$this->compHeadTxt = isset($kropt['compHeadTxt']) ? str_replace('\\n', ' ',$kropt['compHeadTxt']) : '';
				$this->compPhone = isset($kropt['compPhone']) ? $kropt['compPhone'] : '';
				$this->compEmail = isset($kropt['compEmail']) ? $kropt['compEmail'] : '';
				$this->compUrlPrt = isset($kropt['compUrlPrt']) ? $kropt['compUrlPrt'] : '';
				$this->compUrl = isset($kropt['compUrl']) ? $kropt['compUrl'] : '';
				$this->compDirector = isset($kropt['compDirector']) ? $kropt['compDirector'] : '';
				$this->compPos = isset($kropt['compPos']) ? $kropt['compPos'] : '';
				
				
				$kropt_headerLogo = isset($kropt['headerLogo']) ? $kropt['headerLogo'] : KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/logo.png';
				
				$tmpLogo = explode('/uploads/', $kropt_headerLogo);
				$tmpLogo_t = explode('/kristall-integration/', $kropt_headerLogo);
				if (count($tmpLogo) > 1) {
					$this->logo = ABSPATH . 'wp-content/uploads/' . $tmpLogo[1];
				} else if (count($tmpLogo_t) > 1) {
					$this->logo = KRISTALL_INTEGRATION_MAIN_DIR . '' . $tmpLogo_t[1];
				} else {
					$this->logo = KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/logo.png';
				}
				
				$this->signature = KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/signature.png';
				$this->imgPhone = KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/phone.jpg';
				$this->imgMail = KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/mail.jpg';
				$this->imgGlobal = KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/global.jpg';
				$this->dtCurrent = wp_date('d.m.Y');
				$this->dtNowFl = wp_date('d.m.Y_H.i.s');
				$this->fileName = 'kristall_price_' . $this->dtNowFl . '.pdf';
				$this->urlGlobal = $kropt_compUrlPrt . '://' . $kropt_compUrl;
				
				//$this->urlGlobal = get_bloginfo('url');
				
				$list = getKrProductList(explode(',',base64_decode(htmlspecialchars($_GET['cats'], ENT_QUOTES))), true);
		
				if (count($list)){
					$cats = array();
					foreach($list as $key=>$cat){
						$cats[$cat['product_category_parent']][$cat['product_category_id']][] = $cat;
					}
					
					$this->cats = $cats;
				}
				
				parent::__construct();
			}
			
			// Page header
			public function Header() {
				if ($this->page == 1) {
				// Logo
					$this->Image($this->logo,5,5,60);
					// Arial bold 15
					$this->AddFont('Arial-BoldMT','', 'arial_bold.php');
					$this->SetFont('Arial-BoldMT','',12);
					// Move to the right
					$this->Cell(110);
					// Title
					if ($this->compUrl == 'www.ot-dv.ru') {
						$this->Cell(15,5,$this->cirilic('Группа Компаний «Транстрейд»'),0,0,'C');
					} else {
						$this->Cell(15,5,$this->cirilic($this->compName),0,0,'C');
					}
					
					$this->Ln();
					$this->AddFont('ArialMT','', 'arial.php');
					$this->SetFont('ArialMT','',6);
					$this->Cell(110);
					$this->Cell(15,3,$this->cirilic('ИНН/КПП: '.$this->compINN.' '.$this->compOGRNType.': '.$this->compOGRN),0,0,'C');
					$this->Ln();
					$this->AddFont('Arial-ItalicMT','', 'arial_italic.php');
					$this->SetFont('Arial-ItalicMT','',6);
					$this->Cell(70);
					$this->MultiCell(90, 3, $this->cirilic($this->compHeadTxt),0,'C');
					
					$this->Ln();
					$this->AddFont('ArialMT','', 'arial.php');
					$this->SetFont('ArialMT','',8);
					$this->Cell(60);
					$this->Cell(3,5,$this->Image($this->imgPhone, $this->GetX()+7, $this->GetY()+1, 3),0,0,'R');
					$this->Cell(40,5,$this->compPhone,0,0,'C');
					$this->Cell(3,5,$this->Image($this->imgMail, $this->GetX()+8, $this->GetY()+1, 3),0,0,'R');
					$this->Cell(40,5,$this->compEmail,0,0,'C', false, 'mailto:'.$this->compEmail);
					$this->Cell(3,5,$this->Image($this->imgGlobal, $this->GetX()+6, $this->GetY()+1, 3),0,0,'R');
					$this->Cell(30,5,$this->compUrl,0,0,'C', false, $this->compUrlPrt . '://' . $this->urlGlobal);
					// Line break
					$this->AddFont('Arial-BoldMT','', 'arial_bold.php');
					$this->SetFont('Arial-BoldMT','',14);
					$this->Ln(20);
					$this->Cell(180,15,$this->cirilic('Прайс-Лист'),0,0,'C');
					$this->Ln(20);
				} else {
					$this->SetY(5);
					$this->AddFont('ArialMT','', 'arial.php');
					$this->SetFont('ArialMT','',8);
					$this->SetTextColor(0, 0, 0);
					$this->Cell(130);
					$this->Cell(60,5, $this->dtCurrent,0,0,'R');
					$this->Ln(20);
				}
			}

			// Page footer
			public function Footer() {
				// Position at 1.5 cm from bottom
				$this->SetY(-15);
				// Arial italic 8
				$this->AddFont('Arial-ItalicMT','', 'arial_italic.php');
				$this->SetFont('Arial-ItalicMT','',8);
				$this->SetTextColor(0, 0, 0);
				$this->Cell(60, 10, $this->cirilic($this->compDesc),0,0,'L');
				$this->Cell(90,10,$this->cirilic('Страница ').$this->page.'/{nb}',0,0,'C');
				$this->Cell(40, 10, $this->cirilic($this->compUrl),0,0,'R', false, $this->compUrlPrt . '://' . $this->urlGlobal);
				
			}
			
			function cirilic($txt) {
				if (!$txt) return '';
				return iconv('utf-8', 'windows-1251',$txt);
			}
			
			public function getSignature() {
				return $this->signature;
			}
			
			public function getCats() {
				return $this->cats;
			}
			
			public function setHeader() {
				header("Content-Type: application/pdf; charset=UTF-8");
			//	header("Content-Length: " . filesize($this->fileName));
				header('Content-Disposition: attachment; filename="' . $this->fileName . '"');
				header("Content-Transfer-Encoding: binary");
				header("Cache-Control: must-revalidate");
				header("Pragma: no-cache");
				header("Expires: 0");
			}
			
			private function df($num = 0) {
				$rtn = '';
				for($i = 0; $i < $num; $i++) {
					$rtn .= "— ";
				}
				return $rtn;
			}
			
			public function getKrSelectedList($cats, $lavel = 0){
				$indx = 1;
				foreach ($cats as $key=>$val) {
					if (isset($val['product_category_id'])) {					
						$X = $this->GetX();
						$Y = $this->GetY();
						
						$wt = $this->GetStringWidth($this->cirilic($val['product_title']));
						if ($Y > 260 - ($wt / 60)) {
							$this->AddPage();
							$X = $this->GetX();
							$Y = $this->GetY();
						}
						
						$this->SetTextColor(0, 0, 0);
						$this->SetFillColor(245, 245, 245);
						$this->SetXY($X + 20,$Y);
						$this->MultiCell(140,6,$this->cirilic($val['product_title']),1,'L',$fill);
						$H = $this->GetY();
						$height= $H-$Y;
						$this->SetXY($X,$Y);
						$this->Cell(20,$height,$indx,1,0,'C',$fill);
						$this->Cell(140);
						$this->Cell(30,$height,number_format($val['product_price'], 2, ',', ''),1,1,'C',$fill);
						$Y=$H;
						$fill = !$fill;
						$indx++;
					} else {
						if ($key) {
							$this->SetTextColor(255, 255, 255);
							$this->SetFillColor(32, 149, 243);
							$this->Cell(190,8,$this->cirilic($this->df($lavel) . getKrCatName($key)[0]),1,1,'L', true);
						} else {
							$lavel--;
						}
						$lavel++;
						$this->getKrSelectedList($cats[$key], $lavel);
						$lavel--;
					}
				}
			}
		}
		
		
		// Instanciation of inherited class
		$pdf = new PDF();
		$pdf->SetTitle('Прайс-Лист', true);
		$pdf->SetAuthor($pdf->compName, true);
		$pdf->SetCreator('Kristall', true);
		$pdf->SetSubject($pdf->compDesc, true);
		$pdf->AliasNbPages();
		$pdf->AddPage();
		# Устанавливаем шрифт и размер шрифта
		$pdf->AddFont('ArialMT','', 'arial.php');
		$pdf->SetFont('ArialMT','',12);
		# Строим шапку таблицы
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFillColor(184, 184, 184);
		$pdf->Cell(20, 8, $pdf->cirilic("№ п/п"), 1, 0, 'C', true);
		$pdf->Cell(140, 8, $pdf->cirilic("Наименование"), 1, 0, 'C', true);
		$pdf->Cell(30, 8, $pdf->cirilic("Цена, руб."), 1, 0, 'C', true);
		$pdf->Ln();
		
		$pdf->AddFont('ArialMT','', 'arial.php');
		$pdf->SetFont('ArialMT','',12);
		

		$pdf->getKrSelectedList($pdf->getCats());
		
		
		// Добавляем печать, подпись и текст на последней странице
		$pdf->Ln(20);
		$pdf->AddFont('ArialMT','', 'arial.php');
		$pdf->SetFont('ArialMT','',12);
		$pdf->MultiCell(60, 6, $pdf->cirilic("С уважением,\n" . $pdf->compPos . "\n" . $pdf->compName),0,'L');
		if ($pdf->compUrl == 'www.ot-dv.ru') {
			if (file_exists($pdf->getSignature())) {
				$pdf->Cell(80,5,$pdf->Image($pdf->getSignature(), $pdf->GetX()+60, $pdf->GetY()-26, 37),0,0,'C');
			} else {
				$pdf->Cell(120, 5, $pdf->cirilic("МП"), 0, 0, 'C');
			}
		} else {
			$pdf->Cell(120, 5, $pdf->cirilic("МП"), 0, 0, 'C');
		}
		
		$pdf->SetY($pdf->GetY()-15);
		$pdf->Cell(110);
		$pdf->Cell(60,15,$pdf->cirilic($pdf->compDirector),0,0,'R');
		$pdf->Output();
		$pdf->setHeader();
			
		wp_die();
	});
	
	
	// Выгрузка товаров для Яндекса
# /wp-admin/admin-ajax.php?action=createXLS
	
	add_action('wp_ajax_createXLS', function() {
		require_once(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/SimpleXLSXGen.php');
		
		$filename = 'kristall_price_'. wp_date('d.m.Y_H.i.s') .'.xlsx';
		
		$XLSXGen = [
			['Категория', 'Название', 'Описание', 'Цена', 'Фото', 'Популярный товар', 'В наличии' ]
		];
		
		$listCats = explode(',',base64_decode($_GET['cats']));
		$listProds = getKrProductList($listCats);
		
		if (count($listProds)) {
			foreach ($listProds as $key=>$prod) {
				$tmp = array(
					$prod['product_category_name'],
					descCompress($prod['product_title'], 130),
					$prod['product_desc'] ? descCompress($prod['product_desc'], 3000) : '',
					$prod['product_price'],
					$prod['image'],
					'Нет',
					'Да'
				);
				
				array_push($XLSXGen, $tmp);
			}
		}
		
		$xlsx = SimpleXLSXGen::fromArray($XLSXGen);
		$xlsx->downloadAs($filename);
		
		wp_die();
	});
	
# /wp-admin/admin-ajax.php?action=createWord
	
	add_action('wp_ajax_createWord', function() {
		$options = Kristall_Integration_Settings::get_plugin_settings();
		$options_name = $options['option_name'];
		$kropt = get_option($options_name);
				
		$kropt_compName = isset($kropt['compName']) ? $kropt['compName'] : '';
		$kropt_compDesc = isset($kropt['compDesc']) ? $kropt['compDesc'] : '';
		$kropt_headerLogo = isset($kropt['headerLogo']) ? $kropt['headerLogo'] : KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/logo.png';
		$kropt_compINN = isset($kropt['compINN']) ? $kropt['compINN'] : '';
		$kropt_compOGRNType = $kropt['compOGRNType']==0 ? 'ОГРН' : 'ОГРНИП';
		$kropt_compOGRN = isset($kropt['compOGRN']) ? $kropt['compOGRN'] : '';
		$kropt_compHeadTxt = isset($kropt['compHeadTxt']) ? explode('\\n', $kropt['compHeadTxt']) : array();
		$kropt_compPhone = isset($kropt['compPhone']) ? $kropt['compPhone'] : '';
		$kropt_compEmail = isset($kropt['compEmail']) ? $kropt['compEmail'] : '';
		$kropt_compUrlPrt = isset($kropt['compUrlPrt']) ? $kropt['compUrlPrt'] : '';
		$kropt_compUrl = isset($kropt['compUrl']) ? $kropt['compUrl'] : '';
		$kropt_compDirector = isset($kropt['compDirector']) ? $kropt['compDirector'] : '';
		$kropt_compPos = isset($kropt['compPos']) ? $kropt['compPos'] : '';
		
		require_once(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/vendor/autoload.php');
		
		$filename = 'kristall_price_'. wp_date('d.m.Y_H.i.s') .'.docx';
		$full_path = ABSPATH . 'wp-content/uploads/tmp/' . $filename;
		
		$convert = new \PhpOffice\PhpWord\Shared\Converter;
		$phpWord = new  \PhpOffice\PhpWord\PhpWord(); 
		
		$phpWord->setDefaultFontName('Times New Roman');
		$phpWord->setDefaultFontSize(12);
		$phpWord->setDefaultParagraphStyle(
			array(
				'align'      => 'both',
				'spaceAfter' => 60,
				'spacing'    => 0
			)
		);
		
		//$phpWord->getSettings()->setHideGrammaticalErrors(true);
		//$phpWord->getSettings()->setHideSpellingErrors(true);
		//$phpWord->getSettings()->setThemeFontLang(new Language(Language::RU_RU));
		
		$properties = $phpWord->getDocInfo(); 
		$properties->setCreator('Kristall');
		$properties->setCompany($kropt_compName);
		$properties->setTitle('Прайс-Лист');
		$properties->setDescription($kropt_compDesc);
		$properties->setCategory('Интернет магазин');
		
		$dt = explode('.', wp_date('d.m.Y.H.i.s'));
		$properties->setCreated(mktime($dt[3], $dt[4], $dt[5], $dt[0], $dt[1], $dt[2]));
		$properties->setSubject($kropt_compDesc);
		
		$sectionStyle = array(
			'orientation' => 'portrait', //landscape
			'marginTop' => $convert::cmToTwip(1),
			'marginLeft' => $convert::cmToTwip(1),
			'marginRight' => $convert::cmToTwip(1),
			'marginBottom' => $convert::cmToTwip(1.5),
			'colsNum' => 1,
			'pageNumberingStart' => 2
		);
		$section = $phpWord->addSection($sectionStyle);
		
		$styleTable = array('borderSize' => 0, 'borderColor' => 'ffffff');
		$phpWord->addTableStyle('Fancy Table', $styleTable);
		
		$cellHCentered = array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER);
		$cellHLeft = array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT);
		$cellVCentered = array('valign' => 'center');
		
		$fontBold = array('bold' => true);
		$noSpace = array('spaceAfter' => 0);
		
		$table = $section->addTable('Fancy Table');
		$table->addRow();
		$logoCell = $table->addCell($convert::cmToTwip(5.84), array('vMerge' => 'restart', 'valign' => 'center'));
		$logoCell->addImage(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/logo.png',
			array(
				'width'         => $convert::cmToPoint(5.56),
				'height'        => $convert::cmToPoint(1.93),
				'marginTop'     => -1,
				'marginLeft'    => -1,
				'wrappingStyle' => 'behind'
			)
		);
		$cellTl = $table->addCell($convert::cmToTwip(13.42), array('gridSpan' => 3,'valign' => 'center'))->addTextRun($cellHCentered);
		if ($kropt_compName == 'www.ot-dv.ru') {
			$cellTl->addText('Группа Компаний «Транстрейд»', array('bold' => true, 'size' => 14));
		} else {
			$cellTl->addText($kropt_compName, array('bold' => true, 'size' => 14));
		}
		$cellTl->addTextBreak();
		$cellTl->addText('ИНН/КПП: '.$kropt_compINN.' '.$kropt_compOGRNType.': '.$kropt_compOGRN, array('size' => 8));
		$cellTl->addTextBreak();
		
		foreach($kropt_compHeadTxt as $key=>$txtVal) {
			$cellTl->addText(trim($txtVal), array('italic' => true, 'size' => 8));
			if ($key < count($kropt_compHeadTxt)) $cellTl->addTextBreak();
		}
		
		$table->addRow();
		
		$table->addCell(null,array('vMerge' => 'continue')); 
		$cellTl = $table->addCell($convert::cmToTwip(4.43), array('vMerge' => 'continue','valign' => 'center'))->addTextRun($cellHCentered);
		$cellTl->addImage(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/phone.jpg', 
			array(
				'width'         => $convert::cmToPoint(.4),
				'height'        => $convert::cmToPoint(.4),
				'marginTop'     => 0,
				'marginLeft'    => 0,
				'wrappingStyle' => 'behind'
			));
		$cellTl->addText(' '.$kropt_compPhone); // phone
		
		$cellTl = $table->addCell($convert::cmToTwip(4.43), array('vMerge' => 'continue','valign' => 'center'))->addTextRun($cellHCentered);
		$cellTl->addImage(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/mail.jpg', 
			array(
				'width'         => $convert::cmToPoint(.4),
				'height'        => $convert::cmToPoint(.4),
				'marginTop'     => 0,
				'marginLeft'    => 0,
				'wrappingStyle' => 'behind'
			));
		$cellTl->addText(' '.$kropt_compEmail); // mail
		
		$cellTl = $table->addCell($convert::cmToTwip(4.43), array('vMerge' => 'continue','valign' => 'center'))->addTextRun($cellHCentered);
		$cellTl->addImage(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/global.jpg', 
			array(
				'width'         => $convert::cmToPoint(.4),
				'height'        => $convert::cmToPoint(.4),
				'marginTop'     => 0,
				'marginLeft'    => 0,
				'wrappingStyle' => 'behind'
			));
		$cellTl->addText(' '.$kropt_compUrl); // site
		
		// Out date
		
		$section->addTextBreak();
		$styleTable = array('borderSize' => 0, 'borderColor' => 'ffffff', 'cellMarginTop'=> $convert::cmToTwip(.5), 'cellMarginBottom'=> $convert::cmToTwip(.5));
		$phpWord->addTableStyle('OutText Table', $styleTable);
		
		$table = $section->addTable('OutText Table');
		$table->addRow();
		$cellTl = $table->addCell($convert::cmToTwip(9.69), array('valign' => 'center'), $noSpace)->addTextRun($cellHLeft);
		
		$_currentDate = wp_date('d.n.Y');
		$_currentDate = explode('.',$_currentDate);
		$_monthList = array('','января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря');
		
		$cellTl->addText('Исх. № ____ от «', array('size' => 14));
		$cellTl->addText($_currentDate[0], array('underline' => \PhpOffice\PhpWord\Style\Font::UNDERLINE_SINGLE, 'italic' => true, 'size' => 14));
		$cellTl->addText('» ' , array('size' => 14));
		$cellTl->addText($_monthList[$_currentDate[1]], array('underline' => \PhpOffice\PhpWord\Style\Font::UNDERLINE_SINGLE, 'italic' => true, 'size' => 14));
		$cellTl->addText(' ' . $_currentDate[2] . ' г.' , array('size' => 14));
		
		$cellTl = $table->addCell($convert::cmToTwip(9.69), array('valign' => 'center'), $noSpace)->addTextRun($cellHLeft);
		$cellTl->addText('_____________________________________', array('size' => 14));
		
		
		// Title
		
		$section->addTextBreak();
		$section->addTextBreak();
		$section->addText('Прайс-Лист', array('bold' => true, 'size' => 16), array('align' => \PhpOffice\PhpWord\Style\Cell::VALIGN_CENTER));
		$section->addTextBreak();
		$section->addTextBreak();
		
		// Product list
		
		$styleTable = array('borderSize' => $convert::pointToTwip(.5), 'borderColor' => '000000', 'cellMargin'=> $convert::cmToTwip(.19));
		$firstRowStyle = array('bgColor' => 'b8b8b8');
		$catRowStyle = array('bgColor' => '2095f3');
		$phpWord->addTableStyle('Product Table', $styleTable, $firstRowStyle);
		
		$table = $section->addTable('Product Table');
		$table->addRow();
		$cellTl = $table->addCell($convert::cmToTwip(2.76), array('valign' => 'center'), $noSpace)->addTextRun($cellHCentered);
		$cellTl->addText('No п/п', array('bold' => true, 'size' => 14));
		$cellTl = $table->addCell($convert::cmToTwip(12.5), array('valign' => 'center'), $noSpace)->addTextRun($cellHCentered);
		$cellTl->addText('Наименование', array('bold' => true, 'size' => 14));
		$cellTl = $table->addCell($convert::cmToTwip(3.75), array('valign' => 'center'), $noSpace)->addTextRun($cellHCentered);
		$cellTl->addText('Цена, руб.', array('bold' => true, 'size' => 14));
		
		$list = getKrProductList(explode(',',base64_decode(htmlspecialchars($_GET['cats'], ENT_QUOTES))), true);
		if (count($list)){
			$cats = array();
			foreach($list as $key=>$cat){
				$cats[$cat['product_category_parent']][$cat['product_category_id']][] = $cat;
			}
		}
		
		function dfWord($num = 0) {
			$rtn = '';
			for($i = 0; $i < $num; $i++) {
				$rtn .= "— ";
			}
			return $rtn;
		}
		
		function getKrSelectedListWord($cats, $lavel=0, $table, $convert, $cellHCentered, $cellHLeft){
			$indx = 1;
			foreach ($cats as $key=>$val) {
				if (isset($val['product_category_id'])) {					
					if ($indx == 1) {
						$vMerge = array('vMerge' => 'continue');
					} else {
						$vMerge = array();
					}
					
					$table->addRow();
					$cellTl = $table->addCell($convert::cmToTwip(2.76), array_merge($vMerge ,array('valign' => 'center')))->addTextRun($cellHCentered);
					$cellTl->addText($indx, array('size' => 14));
					
					$cellTl = $table->addCell($convert::cmToTwip(12.5), array_merge($vMerge ,array('valign' => 'center')))->addTextRun($cellHLeft);
					$cellTl->addText($val['product_title'], array('size' => 14));
					
					$cellTl = $table->addCell($convert::cmToTwip(3.75), array_merge($vMerge ,array('valign' => 'center')))->addTextRun($cellHCentered);
					$cellTl->addText(number_format($val['product_price'], 2, ',', ''), array('size' => 14));
					
					$indx++;
				} else {
					if ($key) {
						$table->addRow();
						$cellTl = $table->addCell(null, array('gridSpan' => 3, 'valign' => 'center', 'bgColor' => '2095f3'))->addTextRun($cellHLeft);
						$cellTl->addText(getKrCatName($key)[0], array('size' => 14, 'color' => 'ffffff'));
						
						//$this->SetTextColor(255, 255, 255);
						//$this->SetFillColor(32, 149, 243);
						//$this->Cell(190,8,$this->cirilic($this->df($lavel) . getKrCatName($key)[0]),1,1,'L', true);
					} else {
						$lavel--;
					}
					$lavel++;
					getKrSelectedListWord($cats[$key], $lavel, $table, $convert, $cellHCentered, $cellHLeft);
					$lavel--;
				}
			}
		}
		
		getKrSelectedListWord($cats, 0, $table, $convert, $cellHCentered, $cellHLeft);
		
		// Footer
		
		$section->addTextBreak();
		$section->addTextBreak();
		
		$styleTable = array('borderSize' => 0, 'borderColor' => 'ffffff', 'cellMargin'=> $convert::cmToTwip(.19));
		$phpWord->addTableStyle('Footer Table', $styleTable);
		
		$table = $section->addTable('Footer Table');
		$table->addRow();
		$cellTl = $table->addCell($convert::cmToTwip(5.25), array('valign' => 'center'), $noSpace)->addTextRun($cellHLeft);
		$cellTl->addText('С уважением,', array('size' => 14));
		$cellTl->addTextBreak();
		$cellTl->addText($kropt_compPos, array('size' => 14));
		$cellTl->addTextBreak();
		$cellTl->addText($kropt_compName, array('size' => 14));
		
		$cellTl = $table->addCell($convert::cmToTwip(7), array('valign' => 'center'), $noSpace)->addTextRun($cellHCentered);
		
		if ($kropt_compUrl == 'www.ot-dv.ru') {
			if (file_exists(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/signature.png')) {
				$cellTl->addImage(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/signature.png',
					array(
						'width'         => $convert::cmToPoint(3.73),
						'height'        => $convert::cmToPoint(3.41),
						'marginTop'     => -1,
						'marginLeft'    => -1,
						'wrappingStyle' => 'behind'
					)
				);
			} else {
				$cellTl->addText("МП", array('size' => 14));
			}
		} else {
			$cellTl->addText("МП", array('size' => 14));
		}
		
		$cellTl = $table->addCell($convert::cmToTwip(6.75), array('valign' => 'center'), $noSpace)->addTextRun($cellHLeft);
		$cellTl->addText($kropt_compDirector, array('size' => 14));
		
		
		$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
		$objWriter->save($full_path);
		
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . filesize($full_path));
		header('Content-Disposition: attachment; filename="'. $filename .'"');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Expires: 0');
		
		readfile($full_path);
		unlink($full_path);
		
		wp_die();
	});

# /wp-admin/admin-ajax.php?action=createCsv
	
	add_action('wp_ajax_createCsv', function() {
		$csv = '';
		$currency = get_woocommerce_currency();
		$csvTitle = array('id','name','url','price','oldprice','currencyId','category','picture','description');
	//	$csv = implode(';',$csvTitle) . PHP_EOL;
		$listCats = explode(',',base64_decode(htmlspecialchars($_GET['cats'], ENT_QUOTES)));
		$listProds = getKrProductList($listCats);
		if (count($listProds)) {
			$out = fopen('php://output', 'w');
			
			fputs($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM
			fputcsv($out, $csvTitle, ';');
			
			foreach ($listProds as $key=>$prod) {
				$tmp = array($prod['product_id'],
							$prod['product_title'],
							$prod['product_url'],
							$prod['product_price'],
							((int)$prod['product_regular_price'] > (int)$prod['product_price']) ? $prod['product_regular_price'] : '',
							$currency,
							$prod['product_category_name'],
							$prod['image'],
							$prod['product_desc']
						);
				
				fputcsv($out, $tmp, ';');
			}
			
			fclose($out);
			
			print stream_get_contents($out);
			
			header('Content-type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="kristall_price_'. wp_date('d.m.Y_H.i.s') .'.csv"');
			header("Content-Transfer-Encoding: binary");
			header("Cache-Control: must-revalidate");
			header("Pragma: no-cache");
			header("Expires: 0");
			
		}
			
		wp_die();
	});
	
# /wp-admin/admin-ajax.php?action=createCsvFb
	
	add_action('wp_ajax_createCsvFb', function() {
		$csv = '';
		$currency = get_woocommerce_currency();
		$csvTitle = array('id','title','description','availability','condition','price','link','image_link','brand','inventory');
	//	$csv = implode(';',$csvTitle) . PHP_EOL;
		$listCats = explode(',',base64_decode(htmlspecialchars($_GET['cats'], ENT_QUOTES)));
		$listProds = getKrProductList($listCats);
		if (count($listProds)) {
			$out = fopen('php://output', 'w');
			
			//fputs($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM
			fputcsv($out, $csvTitle, ';');
			
			foreach ($listProds as $key=>$prod) {
				$tmp = array($prod['product_id'],
							descCompress($prod['product_title'], 130),
							$prod['product_desc'] ? descCompress($prod['product_desc'],3000) : 'Описание',
							'in stock',
							'new',
							$prod['product_price'] . ' ' . $currency,
							$prod['product_url'],
							$prod['image'],
							'OOO ТРАНСТРЕЙД',
							'50000'
						);
				
				fputcsv($out, $tmp, ';');
			}
			
			fclose($out);
			
			print stream_get_contents($out);
			
			header('Content-type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="kristall_price_facebook_'. wp_date('d.m.Y_H.i.s') .'.csv"');
			header("Content-Transfer-Encoding: binary");
			header("Cache-Control: must-revalidate");
			header("Pragma: no-cache");
			header("Expires: 0");
		}
			
		wp_die();
	});


// tmplDtd.yml
// tmpl.yml	
# /wp-admin/admin-ajax.php?action=createKrYml
	
	// Приватная ссылка на YML
	add_action('wp_ajax_createKrYml', 'kristallCreateKrYml');
// }
	// Публичная ссылка на YML
	add_action('wp_ajax_nopriv_createKrYml', 'kristallCreateKrYml');

	function kristallCreateKrYml() {
		$options = Kristall_Integration_Settings::get_plugin_settings();
		$options_name = $options['option_name'];
		$kropt = get_option($options_name);
			
		$kropt_compName = isset($kropt['compName']) ? $kropt['compName'] : '';
		$kropt_compDesc = isset($kropt['compDesc']) ? $kropt['compDesc'] : '';
		$kropt_compUrlPrt = isset($kropt['compUrlPrt']) ? $kropt['compUrlPrt'] : '';
		$kropt_compUrl = isset($kropt['compUrl']) ? $kropt['compUrl'] : '';
		
		$tmpl = KRISTALL_INTEGRATION_MAIN_DIR . 'connector/tmpl.yml';
		
		$currency = get_woocommerce_currency();
		
		if (file_exists($tmpl)) {
			$yml = simplexml_load_file($tmpl);
			$yml->addAttribute('date', wp_date('Y-m-d H:i'));
			$ymlShop = $yml->addChild('shop');
			$ymlShop->name = $kropt_compDesc;
			if ($kropt_compUrl == 'www.ot-dv.ru') {
				$ymlShop->company = 'Группа Компаний «Транстрейд»';
			} else {
				$ymlShop->company = $kropt_compName;
			}
			$ymlShop->url = $kropt_compUrlPrt . '://' . $kropt_compUrl;
			$ymlShopCrns = $ymlShop->addChild('currencies');
			$ymlShopCrn = $ymlShopCrns->addChild('currency');
			$ymlShopCrn->addAttribute('id', $currency);
			$ymlShopCrn->addAttribute('rate', '1');
			$ymlShopCats = $ymlShop->addChild('categories');
			
			if (base64_decode(htmlspecialchars($_GET['cats'], ENT_QUOTES), true) !== false) {
				$listCats = explode(',',base64_decode(htmlspecialchars($_GET['cats'], ENT_QUOTES)));
			} else {
				echo $yml->asXML();
				wp_die();
			}
			
			$isOnlineURL = absint($_GET['online']);
			
			if (count($listCats)) {
				foreach ($listCats as $key=>$cat) {
					$cat = absint($cat);
					$cat_tmp = getKrCatName($cat);
					$ymlShopCats->category[$key] = htmlspecialchars($cat_tmp[0]);
					$ymlShopCats->category[$key]->addAttribute('id', $cat);
					if ($cat_tmp[1]) {
						$ymlShopCats->category[$key]->addAttribute('parentId', $cat_tmp[1]);
					}
				}
			}
			$ymlShopOffers = $ymlShop->addChild('offers');
			$listProds = getKrProductList($listCats);
			if (count($listProds)) {
				foreach ($listProds as $key=>$prod) {
					$ymlShopOffer[$key] = $ymlShopOffers->addChild('offer');
					$ymlShopOffer[$key]->addAttribute('id', $prod['product_id']);
					$ymlShopOffer[$key]->addAttribute('available', 'true');
					$ymlShopOffer[$key]->currencyId = $currency;
					$ymlShopOffer[$key]->categoryId = $prod['product_category_id'];
					
					if ((int)$prod['product_regular_price'] > (int)$prod['product_price']) {
						$ymlShopOffer[$key]->oldprice = $prod['product_regular_price'];
					}
					
					$ymlShopOffer[$key]->price = $prod['product_price'];
					$ymlShopOffer[$key]->name = htmlspecialchars(str_replace('"', '', $prod['product_title']));
					$ymlShopOffer[$key]->url = $prod['product_url'];
					
					if ($prod['image']) {
						$ymlShopOffer[$key]->picture = $prod['image'];
					} else {
						$ymlShopOffer[$key]->picture = get_bloginfo('wpurl') . '/wp-content/uploads/woocommerce-placeholder.png';
					}
					
					if ($prod['product_desc']) {
						$ymlShopOffer[$key]->description = '<![CDATA[' . htmlspecialchars(do_shortcode($prod['product_desc'])) . ']]>';
					} else {
						// Если описание отсутствует используем заголовок как описание
						$ymlShopOffer[$key]->description = '<![CDATA[' . htmlspecialchars($prod['product_title']) . ']]>';
					}
				}
			}
			echo $yml->asXML();
			
			header('Content-Type: text/xml; charset=utf-8');
			if (!$isOnlineURL) {
				header('Content-Disposition: attachment; filename="kristall_price_'. wp_date('d.m.Y_H.i.s') .'.yml"');
				header("Content-Transfer-Encoding: binary");
			}
			header("Cache-Control: must-revalidate");
			header("Pragma: no-cache");
			header("Expires: 0");
		} else {
			exit('Не удалось открыть файл tmpl.yml. Обратитесь к разработчику.');
		}
		
		wp_die();
	};
	
	function getKrCatName($cat_id){
		$term = get_term_by('id', $cat_id, 'product_cat', 'ARRAY_A');
		return array($term['name'],$term['parent']);
	}
	
	function descCompress($txt, $chrt = 800, $end = true) {
		if (!$txt) return false;
		
		$txt = strip_tags($txt);
		$txt = preg_replace("/[\t]/", '', $txt);
		$txt = preg_replace("/[\r\n]/", ' ', $txt);
		$txt = preg_replace('/\s+/', ' ', $txt);
		$txt = preg_replace("/\s+([\.|,|!|\?]+)/", '\\1',$txt);
		
		if (strlen($txt) > $chrt) {
			$txt = mb_substr($txt, 0, $chrt);
			$txt = trim($txt);
			$txt = rtrim($txt, ".");
			$txt = rtrim($txt, ",") . ($end ? '...' : '');
		}
		return $txt;
	}
	
	function isPopularProd($id) {
		if (!isset($id)) return false;
		
		$args = array(
			'post_type' => 'product',
			'tax_query' => array(
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => 'featured',
				),
			),
		);
		$featured = new WP_Query($args);
		
		if (count($featured->posts) > 0) {
			foreach ($featured->posts as $item) {
				if ($item->ID == $id) {
					return true;
				}
			}
		}
		
		return false;
	}
	
	function getKrProductList($cat, $isPrice = false) {
		$prod_categories = $cat; //category ID from getKrSelectedList()
		$product_args = array(
			'numberposts' => -1,
			'post_status' => array('publish'),
			'post_type' => array('product', 'product_variation') //skip types
		//	'orderby' => 'ID',
		//	'order' => 'ASC'
		);
		
		if (!empty($prod_categories)) {
			$fld = new stdClass;
			$product_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field' => 'id',
					'terms' => $prod_categories,
					'include_children' => false,
					'operator' => 'IN'
			));
			
			$products = get_posts($product_args);	
			$fld->response = array();
			$indx = 0;
			foreach ($products as $product) {
				$prod_meta = new WC_Product($product->ID);
				$terms = get_the_terms($product->ID, 'product_cat')[0];
				# Исключаем картинки из прайс-листа
				if (!$isPrice) {
					$image = wp_get_attachment_image_src(get_post_thumbnail_id($product->ID),'single-post-thumbnail');
					$thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($product->ID),'shop_thumbnail');
					$fld->params['image'] = $image ? $image[0] : '';
					$fld->params['thumbnail'] = $thumbnail ? $thumbnail[0] : '';
					$fld->params['product_category_name'] = $terms->name;
				}
				
				if ($isPrice) {
					$fld->params['product_category_parent'] = $terms->parent;
				}
				
				$fld->params['product_category_id'] = $terms->term_id;
				
				$fld->params['product_title'] = $product->post_title;
				$fld->params['product_price'] = $prod_meta->get_price();
				$fld->params['product_sale_price'] = $prod_meta->get_sale_price();
				$fld->params['product_regular_price'] = $prod_meta->get_regular_price();
				
				# Исключаем дополнительные данные из прайс листа
				if (!$isPrice) {
					$fld->params['product_url'] = get_permalink($product->ID);
					$fld->params['product_id'] = $product->ID;
					$product_desc = descCompress($product->post_content);
					$fld->params['product_desc'] = $product_desc ? $product_desc : '';
				}
				array_push($fld->response, $fld->params);
			}
			return $fld->response;
		}
	}
	
if(is_admin()) {
# /wp-action/json.php?action=publicPrice
	add_action('wp_ajax_publicPrice', 'kristallPublicPrice');
} 
	add_action('wp_ajax_nopriv_publicPrice', 'kristallPublicPrice');

	function kristallPublicPrice(){
		require_once(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/vendor/autoload.php');
		
		$spreadsheet  = new Spreadsheet();
		$drawing      = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
		$filename     = 'kristall_price_'. wp_date('d.m.Y_H.i.s') .'.xlsx';
		$full_path    = ABSPATH . 'wp-content/uploads/tmp/' . $filename;
		
		$taxonomy = 'product_cat';
		$orderby = 'name';
		$order = 'ASC';
		$show_count = 0; // 1 for yes, 0 for no
		$pad_counts = 0; // 1 for yes, 0 for no
		$hierarchical = 1; // 1 for yes, 0 for no
		$title = '';
		$empty = 1;

		$args = array(
			'taxonomy' => $taxonomy,
			'orderby' => $orderby,
			'order' => $order,  
			'show_count' => $show_count,
			'pad_counts' => $pad_counts,
			'hierarchical' => $hierarchical,
			'title_li' => $title,
			'hide_empty' => $empty,
			'parent' => ''
		);

		$all_categories = get_categories($args);
		//$rootCats       = get_terms( 'product_cat', array('parent' => get_queried_object_id()));
		
		if (count($all_categories)){
			$cats_ID = array();
			$cats = array();
			foreach($all_categories as $key=>$cat){
				if ($cat->cat_ID == 105 || $cat->category_parent == 105) continue;
				//$cats[$cat->parent][$cat->cat_ID][] = $cat;
				$cats_ID[$cat->cat_ID][] = $cat;
				$cats[$cat->category_parent][$cat->cat_ID] = $cat;
			}
		}
		
		$styleTitleArray = array(
			'font' => array(
				'bold' => true
			),
			'alignment' => array(
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
			),
			'borders' => array(
				'allBorders' => array(
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
				)
			)
		);
		
		$styleUrlArray = array(
			'font' => array(
				'italic' => true,
				'underline' => \PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE,
				'size'  => 14,
				'color' => array('rgb' => '2095f3')
			)
		);
		
		$styleUrlCatsHomeArray = array(
			'font' => array(
				'italic' => true,
				'underline' => \PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE,
				'size'  => 14,
				'color' => array('rgb' => '31869b')
			)
		);
		
		$styleUrlBackArray = array(
			'font' => array(
				'underline' => \PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE,
				'color' => array('rgb' => '2095f3')
			)
		);
		
		$styleTextColorArray = array(
			'font' => array(
				'color' => array('rgb' => '2095f3')
			)
		);
		
		$styleCatNameArray = array(
			'font' => array(
				'bold'  => true,
				'size'  => 16,
				'color' => array('rgb' => '31869b')
			),
			'alignment' => array(
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
			)
		);
		
		$styleBorderArray = array(
			'borders' => array(
				'allBorders' => array(
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
				)
			)
		);
		
		$styleCellsAlignArray = array(
			'alignment' => array(
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
				'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
			)
		);
		
		$styleCellsAlignNameArray = array(
			'alignment' => array(
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
				'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
			)
		);
		
		$spreadsheet->getProperties()
			->setCreator("НПО Кристалл")
			->setLastModifiedBy("НПО Кристалл")
			->setTitle("Академия Электронного Образования")
			->setSubject("Перечень товаров и услуг")
			->setDescription("Прайс-Лист программ обучения.")
			->setKeywords("Прайс-Лист Кристалл")
			->setCategory("Прайс-Лист");
		
		$contents = $spreadsheet->getSheet(0);
		$contents->setTitle('Содержание');
		$contents->setShowGridlines(false);
		$contents->getColumnDimension('B')->setWidth(70);
		
		$contents->setCellValue('B2', 'Академия Электронного Образования');
		$contents->getStyle('B2')->getFont()->setSize(16);
		
		$contents->setCellValue('B3', preg_replace("~^https?://~", '', get_bloginfo('wpurl')));
		$contents->getCell('B3')->getHyperlink()->setUrl(get_bloginfo('wpurl'));
		$contents->getStyle('B3')->applyFromArray($styleUrlArray);
		
		$contents->setCellValue('B4', 'Лицензия на осуществление образовательной деятельности № 2632 от 26 декабря 2017 года, выданная Министерством образования и науки Хабаровского края.');
		$contents->getStyle('B4')->getAlignment()->setWrapText(true);
		$contents->getStyle('B4')->getFont()->setItalic(true);
		$contents->getStyle('B4')->getFont()->setSize(8);
		
		$contents->setCellValue('B6', 'Содержание:');
		$contents->getStyle('B6')->getFont()->setSize(16);
		$contents->getStyle('B6')->getFont()->setBold(true);
		
		$drawing->setName('Logo');
		$drawing->setDescription('Logo');
		$drawing->setPath(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/assets/images/shopLogoSmall.png');
		$drawing->setCoordinates('C2');
		$drawing->setOffsetX(5);
		$drawing->setHeight(80);
		$drawing->setWorksheet($contents);
		
		$menuList=array();
		//*
		function create_build_tree($cats,$parent_id, $lvl=0, &$menuList=array()){
			global $lvl;
			$lvl++; 
			if(is_array($cats) and isset($cats[$parent_id])){
				foreach($cats[$parent_id] as $cat){
					$menuList[$cat->term_id]['lavel'] = $lvl;
					$menuList[$cat->term_id]['name'] = $cat->name;
					$menuList[$cat->term_id]['parent'] = $parent_id;
					create_build_tree($cats,$cat->term_id, $lvl,$menuList);
					$lvl--;
				}
			}
			return $menuList;
		}
		
		function count_build_tree($cats,$parent_id, $count=0){
			global $count;
			if(is_array($cats) and isset($cats[$parent_id])){
				foreach($cats[$parent_id] as $cat){
					$count++; 
					count_build_tree($cats,$cat->term_id, $count);
				}
			}
			return $count;
		}
		
		function lvlStr($lavel) {
			$rtn = '';
			if ($lavel > 1) {
				for($i=1;$i<$lavel;$i++) {
					$rtn .= '• ';
				}
			} 
			return $rtn;
		}
		
		function create_product_list($spreadsheet, $cellStart, $catId, $sheet, $title, $link, $styleTitleArray, $styleCellsAlignArray,$styleCellsAlignNameArray,$styleBorderArray) {
			$styleUrlBackArray = array(
				'font' => array(
					'underline' => \PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE,
					'color' => array('rgb' => '31869b')
				)
			);
			$styleUrlBackTopArray = array(
				'font' => array(
					'underline' => \PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE,
					'color' => array('rgb' => '2095f3')
				)
			);
			
			$product_args = array(
				'numberposts' => -1,
				'post_status' => array('publish'),
				'post_type' => array('product', 'product_variation'), //skip types
				'orderby' => 'name',
				'order' => 'ASC'
			);
			
			$product_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field' => 'id',
					'terms' => $catId,
					'include_children' => false,
					'operator' => 'IN'
			));
			
			// Шапка листа
			$spreadsheet->getSheet($sheet)->setCellValue('A'.($cellStart+2), '№ п/п');
			$spreadsheet->getSheet($sheet)->setCellValue('B'.($cellStart+2), 'Наименование');
			$spreadsheet->getSheet($sheet)->setCellValue('C'.($cellStart+2), 'Цена руб.');
			$spreadsheet->getSheet($sheet)->getStyle('A'.($cellStart+2).':C'.($cellStart+2))->applyFromArray($styleTitleArray);
			
			$spreadsheet->getSheet($sheet)->getStyle('A'.($cellStart+2).':C'.($cellStart+2))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('bfbfbf');
			
			if ($title !== false) {
				$count = 4;
				$spreadsheet->getSheet($sheet)->getStyle('A'.($cellStart+3).':C'.($cellStart+3))->applyFromArray($styleTitleArray);
				$spreadsheet->getSheet($sheet)->mergeCells('A'.($cellStart+3).':C'.($cellStart+3));
				$spreadsheet->getSheet($sheet)->setCellValue('A'.($cellStart+3), $title);
				$spreadsheet->getSheet($sheet)->getStyle('A'.($cellStart+3))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('dce6f1');
				
				if ($link > -1) {
					$spreadsheet->getSheet($sheet)->getCell('B'.($link+2))->getHyperlink()->setUrl('sheet://\''.$spreadsheet->getSheet($sheet)->getTitle().'\'!A'.($cellStart+3));
				}
				
			} else {
				$count = 3;
			}
			
			$products = get_posts($product_args);
			$cell = 0;
			foreach ($products as $n=>$product) {
				$cell      = $cellStart+$count;
				$index     = $n+1;
				$prod_meta = new WC_Product($product->ID);
				$current   = $spreadsheet->getSheet($sheet);
				// Номер по порядку
				$current->setCellValue('A'.$cell, $index);
				$current->getStyle('A'.$cell)->applyFromArray($styleCellsAlignArray);
				// Наименование
				$current->setCellValue('B'.$cell, $product->post_title);
				$current->getCell('B'.$cell)->getHyperlink()->setUrl(get_permalink($product->ID));
				$current->getStyle('B'.$cell)->getAlignment()->setWrapText(true);
				$current->getStyle('B'.$cell)->applyFromArray($styleCellsAlignNameArray);
				// Цена
				$current->setCellValue('C'.$cell, $prod_meta->get_price());
				$current->getStyle('C'.$cell)->applyFromArray($styleCellsAlignArray);
				$current->getStyle('C'.$cell)->getNumberFormat()->setFormatCode('# ##0 ₽;-# ##0 ₽');
				// Формат шапки
				$current->getStyle('A'.$cell.':C'.$cell)->applyFromArray($styleBorderArray);
				$count++;
			}
			
			$cell = $cellStart+$count;
			$spreadsheet->getSheet($sheet)->setCellValue('B'.$cell, 'к содержанию');
			$spreadsheet->getSheet($sheet)->getCell('B'.$cell)->getHyperlink()->setUrl('sheet://\'Содержание\'!B'.($sheet+7));
			$spreadsheet->getSheet($sheet)->getStyle('B'.$cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
			$spreadsheet->getSheet($sheet)->getStyle('B'.$cell)->applyFromArray($styleUrlBackArray);
			
			$spreadsheet->getSheet($sheet)->setCellValue('C'.$cell, 'наверх');
			$topCellUrl = 'A1';
			if ($link > -1) {
				$topCellUrl = 'B'.($link+2);
			}
			$spreadsheet->getSheet($sheet)->getCell('C'.$cell)->getHyperlink()->setUrl('sheet://\''.$spreadsheet->getSheet($sheet)->getTitle().'\'!'.$topCellUrl);
			$spreadsheet->getSheet($sheet)->getStyle('C'.$cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
			$spreadsheet->getSheet($sheet)->getStyle('C'.$cell)->applyFromArray($styleUrlBackTopArray);
			
			return $cell;
		}
		
		$menuList = create_build_tree($cats,0);	
		
		$currentSheetIndex = 1; // текущий лист
		$childChellMenu = 2; // начальная ячейка для меню категорий
		$catChildrenCount = 1; // Начальная ячейка без категорий после заголовка
		$prodSheetPrint = array();
		
		foreach ($menuList as $id=>$cat) {
			if ($cat['parent']!=0) {
				$spreadsheet->getSheet($currentSheetIndex-1)->setCellValue('B'.$childChellMenu, lvlStr($cat['lavel']) . $cat['name']);
				$spreadsheet->getSheet($currentSheetIndex-1)->getStyle('B'.$childChellMenu)->applyFromArray($styleTextColorArray);
				$prodSheetPrint[$currentSheetIndex-1][] = $id;
				$childChellMenu++;
			} else {
				$currentSheetName = descCompress($cat['name'], 15, false);
				$myWorkSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $currentSheetName);
				$spreadsheet->addSheet($myWorkSheet, $currentSheetIndex);
				
				$spreadsheet->getSheet(0)->setCellValue('B'.($currentSheetIndex+7), $currentSheetIndex.'. '.$cat['name']);
				$spreadsheet->getSheet(0)->getCell('B'.($currentSheetIndex+7))->getHyperlink()->setUrl('sheet://\''.$currentSheetName.'\'!A2');
				$spreadsheet->getSheet(0)->getStyle('B'.($currentSheetIndex+7))->applyFromArray($styleUrlCatsHomeArray);
				
				$spreadsheet->getSheet($currentSheetIndex)->setCellValue('A1', $cat['name']);
				$spreadsheet->getSheet($currentSheetIndex)->getStyle('A1')->applyFromArray($styleCatNameArray);
				$spreadsheet->getSheet($currentSheetIndex)->getColumnDimension('B')->setWidth(70);
				
				if (is_array($cats[$id])) {
					if(count($cats[$id])) {
						$prodSheetPrint[$currentSheetIndex] = array();
					}
				} else {
					$catCount = create_product_list($spreadsheet, $catChildrenCount, $id, $currentSheetIndex, false, -1,$styleTitleArray, $styleCellsAlignArray,$styleCellsAlignNameArray,$styleBorderArray);
				}
				$childChellMenu = 2;
				$currentSheetIndex++;
			}
		}
		// footer sheet 0
		$countCatsEnd = ($currentSheetIndex + 8);
		$contents->setCellValue('B'.$countCatsEnd, 'Если возникли вопросы, Вы можете позвонить на горячую линию 8-800-300-2628 или отправить электронное письмо по адресу t201090@mail.ru');
		$contents->getStyle('B'.$countCatsEnd)->getAlignment()->setWrapText(true);
		$contents->getStyle('B'.$countCatsEnd)->getFont()->setSize(8);
		$contents->getStyle('B'.$countCatsEnd)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('7030a0');
		$contents->getStyle('B'.$countCatsEnd)->getFont()->getColor()->setARGB('e4dfec');
		
		$contents->setCellValue('B'.($countCatsEnd + 2), 'Прайс-Лист актуален на ' . wp_date('d.m.Y H:i:s'));
		$contents->setCellValue('B'.($countCatsEnd + 3), 'Скачать новый Прайс-Лист');
		$contents->getCell('B'.($countCatsEnd + 3))->getHyperlink()->setUrl(get_bloginfo('wpurl') . '/wp-action/json.php?action=publicPrice');
		$contents->getStyle('B'.($countCatsEnd + 3))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
		$contents->getStyle('B'.($countCatsEnd + 3))->applyFromArray($styleUrlBackArray);
		// Add products
		foreach($prodSheetPrint as $sheet=>$pcat) {
			$catChildrenCount = count($pcat) + 1;
			foreach($pcat as $i=>$icat) {
				$catChildrenCount = create_product_list($spreadsheet, $catChildrenCount, $icat, $sheet, $menuList[$icat]['name'], $i,$styleTitleArray, $styleCellsAlignArray,$styleCellsAlignNameArray,$styleBorderArray);
			}
		}
		
		$spreadsheet->setActiveSheetIndex(0);
		
		$writer = new Xlsx($spreadsheet);
		$writer->save($full_path);
		
		header('Content-Description: File Transfer');
		//header('Content-Type: application/octet-stream');
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . filesize($full_path));
		header('Content-Disposition: attachment; filename="'. $filename .'"');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Expires: 0');
		
		readfile($full_path);
		unlink($full_path);
		
		wp_die();
	}