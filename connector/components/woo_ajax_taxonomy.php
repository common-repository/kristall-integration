<?php

defined( 'ABSPATH' ) || exit;

/*
 * JSON формат для пакетной обработки категорий и товаров
 *
 * @getCatTree       (string)	return tree list category
 * @getCatParent     (integer)	return parent current category
 * @getCatProducts   (string)	return product list from category ID
 * 
 * TODO: Файл в разработке. $_GET используется в целях тестирования, по завершению
 * 		 будет использоваться $_REQUEST в связке nginx SSL header allow + apache 
 */

// if(is_admin()) {
	
	/*
	 * Получаем список категорий товаров
	 * ---------------------------------------
	 * url - /wp-admin/admin-ajax.php?action=getCatTree
	 *
	 * @only_parent (int) - принимает значение ID родительской категории и выводит все подкатегории
	 */
	
	add_action('wp_ajax_getCatTree', function() {
		
		$pQuery = sanitize_text_field($_GET['only_parent']);
		
		$fld = new stdClass;
		$fld->params = array(
			'only_parent' => !empty($pQuery) ? (int) $pQuery : 0
		);
		
		$args = array(
			'child_of' => $current_term->term_id, 
			'orderby' => 'name', 
			'order' => 'ASC' 
		);
		$terms = get_terms('product_cat', $args);
		$count = count($terms);
		
		if ($count){
			wp_send_json(buildTree($terms,$fld->params['only_parent']), 200);
		}
		
		/* 
		echo '<pre>';
		var_dump($cats_ID);
		echo '</pre>';
		//*/
		
		wp_die();
	});
	
	/*
	 * Получаем родительскую категорию
	 * 
	 * @cat_id - идентификатор категории для которой нужно найти родителя
	 */
	
	add_action('wp_ajax_getCatParent', function() {
		
		$pQuery = sanitize_text_field($_GET['cat_id']);
		$only = !empty($pQuery) ? (int) $pQuery : 0;
		
		$args = array( 'child_of' => $current_term->term_id, 'orderby' => 'name', 'order' => 'ASC' );
		$terms = get_terms( 'product_cat', $args );
		$count = count($terms);
		
		if ($count && $only > 0) {
			$cats_ID = array();
			foreach($terms as $key=>$cat){
				$cats_ID[$cat->term_id][] = $cat;
			}
			
			echo (int) find_parent($cats_ID,$only);
		}
			
		wp_die();
	});
	
	/*
	 * Получаем список товаров из категории
	 * 
	 * @cat_id  (int)      ID категории
	 * @orderby (string)   имя поля для сортировки
	 * @order   (string)   порядок сортировки (по умолчанию: по возрастанию)
	 * @format  (string)   тип возвращаемых данных (по умолчанию: json)
	 * ----------------------------------------------
	 * Стандартные статусы постов (post_status)
	 * 
	 * publish    — опубликованный пост
	 * future     — пост, запланированный на публикацию в будущем
	 * draft      — черновики (записи, которые ещё находятся в процессе написания и не готовы к публикации)
	 * pending    — пост, ожидающий проверки редактором или администратором
	 * private 	  — посты, доступные для просмотра и редактирования только администраторам
	 * trash      — посты, находящиеся в корзине
	 * auto-draft — черновики, которые создаются автоматически в процессе редактирования постов
	 * inherit    — этот статус присваивается всем вложениям, а также редакциям записей
	 */
	
	add_action('wp_ajax_getCatProducts', function() {
		
		$pQuery_orderby = sanitize_text_field($_GET['orderby']);
		$pQuery_order = sanitize_text_field($_GET['order']);
		$pQuery_format = sanitize_text_field($_GET['format']);
		$pQuery_cat_id = sanitize_text_field($_GET['cat_id']);
		$pQuery_only = sanitize_text_field($_GET['only']);
		
		$fld = new stdClass;
		$fld->orderby = !empty($pQuery_orderby) ? $pQuery_orderby : 'ID';
		$fld->order = !empty($pQuery_order) ? $pQuery_order : 'ASC';
		$fld->format = !empty($pQuery_format) ? $pQuery_format : 'json';
		
		$listCats = explode(',',base64_decode($pQuery_cat_id));
		$only = !(!empty($pQuery_only) ? (int) $pQuery_only : 0);
		
		$product_args = array(
			'numberposts' => -1,
			'post_status' => array('publish', 'pending', 'private', 'draft'),
			'post_type' => array('product', 'product_variation'), //skip types
			'orderby' => $fld->orderby,
			'order' => $fld->order
		);

		if (!empty($listCats)) {
			$product_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field' => 'id',
					'terms' => $listCats,
					'include_children' => $only,
					'operator' => 'IN'
			));
			
			$products = get_posts($product_args);
			
			$fld->params['currency_code'] = get_woocommerce_currency();
			
			$fld->res = new stdClass;
			$fld->res->response =array();
			foreach ($products as $product) {
				$prod_meta = new WC_Product($product->ID);
				$terms = get_the_terms($product->ID, 'product_cat')[0];
				
				$image = wp_get_attachment_image_src(get_post_thumbnail_id($product->ID),'single-post-thumbnail');
				$thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($product->ID),'shop_thumbnail');
				
				$fld->params['image'] = $image ? $image[0] : '';
				$fld->params['thumbnail'] = $thumbnail ? $thumbnail[0] : '';
				$fld->params['product_title'] = $product->post_title;
				$fld->params['product_price'] = $prod_meta->get_price();
				$fld->params['product_url'] = get_permalink($product->ID);
				$fld->params['status'] = $product->post_status;
				$fld->params['category_name'] = $terms->name;
				$fld->params['product_id'] = $product->ID;
				$fld->params['product_desc'] = $product->post_content ? $product->post_content : '';
				
				array_push($fld->res->response, array(
					$fld->params['image'],
					$fld->params['product_title'],
					$fld->params['product_price'],
					$fld->params['status'],
					$fld->params['currency_code'],
					$fld->params['category_name'],
					$fld->params['product_url'],
					$fld->params['product_id'],
					$fld->params['product_desc'],
					$fld->params['thumbnail']
				));
			}
			
			wp_send_json($fld->res, 200);
		}
		
		wp_die();
	});
	
	// Рекурсивное построение дерева категорий товаров
	
	function buildTree(array $elements, $parentId = 0) {
		$branch = array();

		foreach ($elements as $element) {
			if ($element->parent == $parentId) {
				$children = buildTree($elements, $element->term_id);
				if ($children) {
					$element->children = $children;
					$element->folder = true;
				}
				
				$element->title = $element->name;
				
				unset($element->name);
				unset($element->term_group);
				unset($element->term_taxonomy_id);
				unset($element->description);
				
				array_push($branch, $element);
			}
		}

		return $branch;
	}
	
	// Рекурсивный поиск root категории
	function find_parent($cats_ID, $cur_id){
		if($cats_ID[$cur_id][0]->parent!=0){
			return find_parent($cats_ID,$cats_ID[$cur_id][0]->parent);
		}
		return (int)$cats_ID[$cur_id][0]->term_id;
	}
	
// }
