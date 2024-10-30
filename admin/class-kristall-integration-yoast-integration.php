<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/interface-kristall-integration-module.php';

class Kristall_Integration_Yoast_Integration implements Kristall_Integration_Module {
  public function __construct($plugin_name, $plugin_settings) {}

  /*============================================================================
   * Регистрация модуля
	 ============================================================================*/

  public function define_hooks($loader) {
		$loader->add_action('woocommerce_update_product', $this, 'yoast_reindex_products', 10, 1);
		$loader->add_action('edit_term', $this, 'yoast_reindex_categories', 10, 3);
	}

	/*============================================================================
   * Экшны и фильтры
	 ============================================================================*/

   public function yoast_reindex_products($product_id) {
		$this->reindex_objects($product_id, false);
	}

	public function yoast_reindex_categories($term_id, $tt_id = '', $taxonomy = '') {
		if ($taxonomy == 'product_cat') {
			$this->reindex_objects($term_id, true);
		}
	}

  /*============================================================================
   * Приватные методы
	 ============================================================================*/

	private function reindex_objects($object_id, $is_category) {
		global $wpdb;

		if (!WC()->is_rest_api_request()) return;

		$actual_permalink = $is_category ? get_category_link($object_id) : get_permalink($object_id);

		$indexable_table = \Yoast\WP\Lib\Model::get_table_name('Indexable');
		$hierarchy_table = \Yoast\WP\Lib\Model::get_table_name('Indexable_Hierarchy');
		$primary_term_table = \Yoast\WP\Lib\Model::get_table_name('Primary_Term');

    // Получаем объект продукта / категории

		$indexables = $wpdb->get_results(
			$wpdb->prepare("SELECT id, permalink FROM $indexable_table WHERE object_id=%d AND object_sub_type=%s", $object_id, $is_category ? 'product_cat' : 'product')
		);
		if (!count($indexables)) return;

		$parent_categories = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT indexables.object_id FROM $hierarchy_table AS hierarchy INNER JOIN $indexable_table AS indexables ON hierarchy.ancestor_id = indexables.id WHERE hierarchy.indexable_id=%d ORDER BY hierarchy.depth DESC LIMIT 1", $indexables[0]->id
			)
		);

		$permalink_changed = $indexables[0]->permalink === $actual_permalink;

		if (count($parent_categories)) {
			if ($is_category) {
				if (get_category($object_id)->parent == $parent_categories[0]->object_id && !$permalink_changed) {
					return;
				}
			} else {
				$terms = get_the_terms($object_id, 'product_cat');

				if (!$terms || is_wp_error($terms) || !count($terms) || $terms[0]->term_id == 0) {
					$parent = get_option('default_product_cat');

				} else {
					$parent = $terms[0]->term_id;
				}
	
				if ($parent == $parent_categories[0]->object_id && !$permalink_changed) {
					return;
				}
			}
		} else if (!$permalink_changed) {
			return;
		}

		$obj_indexable = $indexables[0]->id;
		$parent_indexable = 0;

		// Получаем ID родительской категории

		if ($is_category) {
			$category = get_term($object_id, 'product_cat');

			if ($category === null || is_wp_error($category)) return;
			if (is_array($category)) {
				$parent = $category[0]->parent;
			} else {
				$parent = $category->parent;
			}
		} else {
			$terms = get_the_terms($object_id, 'product_cat');

			if (!$terms || is_wp_error($terms) || !count($terms) || $terms[0]->term_id == 0) {
				$parent = get_option('default_product_cat');
	
			} else {
				// Загружаем дерево категорий
				$term_tree = [];
				foreach ($terms as $term) {
					$term_tree[(string)$term->term_id] = $term->parent;
				}
				$term_tree = $this->load_term_tree($term_tree);
				$term_parent_ids = array_values($term_tree);
	
				foreach ($term_tree as $id => $parent) {
					if (!in_array((int)$id, $term_parent_ids)) {
						$parent = (int)$id;
						break;
					}
				}
			}
		}

		if (!$is_category && $parent == 0) {
			return;
		} else if ($parent != 0) {
			$indexables = $wpdb->get_results(
				$wpdb->prepare("SELECT id FROM $indexable_table WHERE object_id=%d AND object_sub_type=%s", $parent, 'product_cat')
			);
			if (!count($indexables)) return;
			$parent_indexable = $indexables[0]->id;
		}

		// Получаем путь в дереве

		$base_path = [];

		if ($parent_indexable != 0) {
			$indexables = $wpdb->get_results(
				$wpdb->prepare("SELECT ancestor_id FROM $hierarchy_table WHERE indexable_id=%d ORDER BY depth DESC", $parent_indexable)
			);
			if (!count($indexables)) return;
			
			foreach ($indexables as $indexable) {
				if ($indexable->ancestor_id == 0) continue;
				$base_path[] = $indexable->ancestor_id;
			}

			$base_path[] = $parent_indexable;
		}

		// Удаляем запись об основной категории для объекта
		if (!$is_category) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $primary_term_table WHERE post_id=%d AND taxonomy=%s", $object_id , 'product_cat'
				)
			);
		}

		// Обновляем ссылку объекта и обновляем флаг has_ancestors
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $indexable_table SET permalink=%s, permalink_hash=%s, has_ancestors=%d WHERE id=%d",
				$actual_permalink,
				strlen($actual_permalink) . ':' . md5($actual_permalink),
				count($base_path) ? 1 : 0,
				$obj_indexable
			)
		);

		// Очищаем путь в дереве до объекта
		$wpdb->query(
			$wpdb->prepare("DELETE FROM $hierarchy_table WHERE indexable_id=%d", $obj_indexable)
		);

		// Вставляем путь основного объекта
		$this->insert_paths($obj_indexable, 1, $base_path);

		// Если продукт, завершаем выполнение функции
		if (!$is_category) return;
		// ------------------------------------------

		// Добавляем текущую категорию в путь
		$base_path[] = $obj_indexable;

		$subcategories_map = $this->get_subcategories($object_id);
		$subproducts_map = $this->get_subproducts(array_merge([$object_id], array_keys($subcategories_map)));
		$subindexables_ids = [];

		// Загружаем Indexables суб-категорий
		if (count($subcategories_map)) {
			$subcategories_indexables = $wpdb->get_results(
				$wpdb->prepare("SELECT id, object_id FROM $indexable_table WHERE object_id IN (" . implode(',', array_keys($subcategories_map)) . ") AND object_sub_type=%s", 'product_cat')
			);
			foreach ($subcategories_indexables as $subcategories_indexable) {
				$subcategories_map[(string)$subcategories_indexable->object_id]['indexable'] = $subcategories_indexable->id;
				$subindexables_ids[] = $subcategories_indexable->id;
			}
		}

		// Загружаем Indexables суб-продуктов
		if (count($subproducts_map)) {
			$subproducts_indexables = $wpdb->get_results(
				$wpdb->prepare("SELECT id, object_id FROM $indexable_table WHERE object_id IN (" . implode(',', array_keys($subproducts_map)) . ") AND object_sub_type=%s", 'product')
			);
			foreach ($subproducts_indexables as $subproducts_indexable) {
				$subproducts_map[(string)$subproducts_indexable->object_id]['indexable'] = $subproducts_indexable->id;
				$subindexables_ids[] = $subproducts_indexable->id;
			}
		}

		// Получаем Indexables для суб-директорий

		foreach ($subcategories_map as $id => &$pi_obj) {
			$pi_obj['parent_indexables'] = [];

			foreach ($pi_obj['parents'] as $parent_id) {
				$pi_obj['parent_indexables'][] = (int)($subcategories_map[(string)$parent_id]['indexable']);
			}
		}

		// Получаем список записей для вставки в БД

		$insert_list = [];

		foreach ($subcategories_map as $id => $sq_obj) {
			$insert_list = array_merge(
				$insert_list,
				$this->get_insert_rows($sq_obj['indexable'], 1, array_merge($base_path, $sq_obj['parent_indexables']))
			);
		}

		foreach ($subproducts_map as $id => $spq_obj) {
			$path = array_merge(
				$base_path,
				$subcategories_map[(string)$spq_obj['parent']]['parent_indexables'],
				[$subcategories_map[(string)$spq_obj['parent']]['indexable']]
			);

			$insert_list = array_merge(
				$insert_list,
				$this->get_insert_rows($spq_obj['indexable'], 1, $path)
			);
		}

		if (!count($insert_list)) return;

		// Удаляем пути для суб-директорий и суб-продуктов
		$wpdb->query(
			$wpdb->prepare("DELETE FROM $hierarchy_table WHERE indexable_id IN (" . implode(',', $subindexables_ids) . ")")
		);

		// Обновляем пути
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$hierarchy_table}(indexable_id,ancestor_id,depth,blog_id) VALUES (" . implode('),(', $insert_list) . ')'
			)
		);

		// Удаляем запись об основной категории для объекта
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $primary_term_table WHERE post_id IN (" . implode(',', array_keys($subproducts_map)) . ") AND taxonomy=%s" , 'product_cat'
			)
		);

		// Обновляем ссылку объекта и обновляем флаг has_ancestors

		foreach ($subcategories_map as $id => $sq_obj_p) {
			$permalink = get_category_link((int)$id);
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $indexable_table SET permalink=%s, permalink_hash=%s, has_ancestors=%d WHERE id=%d",
					$permalink,
					strlen($permalink) . ':' . md5($permalink),
					1,
					$sq_obj_p['indexable']
				)
			);	
		}
		foreach ($subproducts_map as $id => $spq_obj_p) {
			$permalink = get_permalink((int)$id);
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $indexable_table SET permalink=%s, permalink_hash=%s, has_ancestors=%d WHERE id=%d",
					$permalink,
					strlen($permalink) . ':' . md5($permalink),
					1,
					$spq_obj_p['indexable']
				)
			);	
		}
	}

	private function load_term_tree($terms, $result = null) {
		if (!count($terms)) {
			return $result ?? $terms;
		}

		if (!$result) {
			$result = $terms;
		}

		$next = [];
		foreach ($terms as $id => $parent) {
			if (!array_key_exists((string)$parent, $result)) {
				$term = get_term((int)$parent);
				$result[(string)$term->term_id] = $term->parent;
				if ($term->parent && $term->parent != 0) $next[(string)$term->term_id] = $term->parent;	
			}
		}

		return $this->load_term_tree($next, $result);
	}

	private function get_subcategories($category_id) {
		$ids = get_term_children($category_id, 'product_cat');
		$terms = [];

		foreach ($ids as $id) {
			$term = get_term($id);
			$terms[(string)$term->term_id] = [
				'parent' => $term->parent == $category_id ? null : $term->parent,
				'parents' => []
			];
		}

		foreach ($terms as $term_id => &$obj) {
			$obj['parents'] = $this->get_subcategory_parents($terms, $obj['parent']);
		}

		return $terms;
	}

	private function get_subcategory_parents($terms, $parent, $path = []) {
		if ($parent === null) return array_reverse($path);

		$path[] = (int)$parent;
		$obj = $terms[(string)$parent];

		return $this->get_subcategory_parents($terms, $obj['parent'], $path);
	}

	private function get_subproducts($category_ids) {
		$products_map = [];

		foreach ($category_ids as $category_id) {
			$products = get_posts([
				'numberposts' => -1,
				'posts_per_page' => -1,
				'post_type' => 'product',
				'fields' => 'ids',
				'tax_query' => [
					[
							'taxonomy' => 'product_cat',
							'field'    => 'term_id',
							'terms'    => $category_id,
							'include_children' => false,
					],
				],
			]);

			foreach ($products as $product_id) {
				$products_map[(string)$product_id] = ['parent' => $category_id];
			}
		}

		return $products_map;
	}

	private function get_insert_rows($indexable_id, $blog_id, $path) {
		if (!count($path)) {
			return ["$indexable_id,0,0,$blog_id"];
		}

		$rows = [];

		foreach (array_reverse($path) as $index => $id) {
			$depth = $index + 1;
			$rows[] = "$indexable_id,$id,$depth,$blog_id";
		}

		return $rows;
	}

	private function insert_paths($indexable_id, $blog_id, $path) {
		global $wpdb;

		$hierarchy_table = \Yoast\WP\Lib\Model::get_table_name('Indexable_Hierarchy');

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$hierarchy_table}(indexable_id,ancestor_id,depth,blog_id) VALUES (" . implode('),(', $this->get_insert_rows($indexable_id, $blog_id, $path)) . ')'
			)
		);
	}
}
