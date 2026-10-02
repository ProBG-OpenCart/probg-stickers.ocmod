<?php
class ModelExtensionModuleProductSticker extends Model {

  public function getStickersByProductId($product_id) {
    static $cache = array();

    $product_id = (int)$product_id;
    $language_id = (int)$this->config->get('config_language_id');
    $fallback_language_id = $this->getFallbackLanguageId();
    $position = $this->getGlobalPosition();
    $has_system_key = $this->hasSystemKeyColumn();
    $new_days = (int)$this->config->get('module_product_sticker_new_days');
    $show_discount = $this->config->get('module_product_sticker_sale_show_discount') ? 1 : 0;

    if ($new_days < 1) {
      $new_days = 30;
    }

    $cache_key = implode('_', array(
      $product_id,
      $language_id,
      $fallback_language_id,
      $position,
      $new_days,
      $show_discount,
      $has_system_key ? 1 : 0
    ));

    if (isset($cache[$cache_key])) {
      return $cache[$cache_key];
    }

    $system_key_select = $has_system_key ? "ps.system_key" : "'' AS system_key";
    $system_key_where = $has_system_key ? "AND (ps.system_key IS NULL OR ps.system_key = '')" : "";

    $query = $this->db->query("
      SELECT DISTINCT
        ps.product_sticker_id,
        " . $system_key_select . ",
        ps.color,
        ps.text_color,
        ps.sort_order,
        ps.status,
        COALESCE(psd.name, psd2.name, '') AS name
      FROM `" . DB_PREFIX . "product_sticker` ps

      LEFT JOIN `" . DB_PREFIX . "product_sticker_description` psd
        ON ps.product_sticker_id = psd.product_sticker_id
        AND psd.language_id = '" . $language_id . "'

      LEFT JOIN `" . DB_PREFIX . "product_sticker_description` psd2
        ON ps.product_sticker_id = psd2.product_sticker_id
        AND psd2.language_id = '" . $fallback_language_id . "'

      LEFT JOIN `" . DB_PREFIX . "product_to_sticker` pts
        ON ps.product_sticker_id = pts.product_sticker_id
        AND pts.product_id = '" . $product_id . "'

      LEFT JOIN `" . DB_PREFIX . "product_to_category` p2c
        ON p2c.product_id = '" . $product_id . "'

      LEFT JOIN `" . DB_PREFIX . "category_to_sticker` cts
        ON ps.product_sticker_id = cts.product_sticker_id
        AND cts.category_id = p2c.category_id

      WHERE ps.status = '1'
        " . $system_key_where . "
        AND (
          pts.product_id IS NOT NULL
          OR cts.category_id IS NOT NULL
        )
        AND COALESCE(psd.name, psd2.name, '') != ''

      ORDER BY ps.sort_order ASC, ps.product_sticker_id ASC
    ");

    $stickers = array();

    foreach ($query->rows as $row) {
      $row['position'] = $position;
      $stickers[] = $row;
    }

    if ($has_system_key) {
      foreach ($this->getAutomatedStickers($product_id, $language_id, $fallback_language_id, $position, $new_days, $show_discount) as $sticker) {
        $stickers[] = $sticker;
      }
    }

    usort($stickers, function($a, $b) {
      if ((int)$a['sort_order'] === (int)$b['sort_order']) {
        return (int)$a['product_sticker_id'] - (int)$b['product_sticker_id'];
      }

      return (int)$a['sort_order'] - (int)$b['sort_order'];
    });

    $cache[$cache_key] = $stickers;

    return $stickers;
  }

  private function hasSystemKeyColumn() {
    static $has_column = null;

    if ($has_column !== null) {
      return $has_column;
    }

    $query = $this->db->query("
      SHOW COLUMNS
      FROM `" . DB_PREFIX . "product_sticker`
      LIKE 'system_key'
    ");

    $has_column = (bool)$query->num_rows;

    return $has_column;
  }

  private function getAutomatedStickers($product_id, $language_id, $fallback_language_id, $position, $new_days, $show_discount) {
    $definitions = $this->getSystemStickerDefinitions($language_id, $fallback_language_id);

    if (!$definitions) {
      return array();
    }

    $customer_group_id = (int)$this->config->get('config_customer_group_id');

    if ($this->customer && $this->customer->isLogged()) {
      $customer_group_id = (int)$this->customer->getGroupId();
    }

    $special_select = "NULL AS special_price";

    if (isset($definitions['sale'])) {
      $special_select = "(
        SELECT pspecial.price
        FROM `" . DB_PREFIX . "product_special` pspecial
        WHERE pspecial.product_id = p.product_id
          AND pspecial.customer_group_id = '" . $customer_group_id . "'
          AND (pspecial.date_start = '0000-00-00' OR pspecial.date_start IS NULL OR pspecial.date_start < NOW())
          AND (pspecial.date_end = '0000-00-00' OR pspecial.date_end IS NULL OR pspecial.date_end > NOW())
        ORDER BY pspecial.priority ASC, pspecial.price ASC
        LIMIT 1
      ) AS special_price";
    }

    $product_query = $this->db->query("
      SELECT
        p.price,
        p.date_added,
        " . $special_select . "
      FROM `" . DB_PREFIX . "product` p
      WHERE p.product_id = '" . (int)$product_id . "'
      LIMIT 1
    ");

    if (!$product_query->num_rows) {
      return array();
    }

    $product = $product_query->row;
    $result = array();

    if (isset($definitions['new']) && !empty($product['date_added'])) {
      $date_added = strtotime($product['date_added']);
      $threshold = strtotime('-' . (int)$new_days . ' days');

      if ($date_added !== false && $date_added >= $threshold) {
        $row = $definitions['new'];
        $row['position'] = $position;
        $result[] = $row;
      }
    }

    if (isset($definitions['sale'])) {
      $base_price = (float)$product['price'];
      $special_price = $product['special_price'];

      if ($special_price !== null && $base_price > 0 && (float)$special_price < $base_price) {
        $row = $definitions['sale'];

        if ($show_discount) {
          $discount = (int)round((1 - ((float)$special_price / $base_price)) * 100);

          if ($discount > 0) {
            $row['name'] .= ' -' . $discount . '%';
          }
        }

        $row['position'] = $position;
        $result[] = $row;
      }
    }

    return $result;
  }

  private function getSystemStickerDefinitions($language_id, $fallback_language_id) {
    static $cache = array();

    $cache_key = (int)$language_id . '_' . (int)$fallback_language_id;

    if (isset($cache[$cache_key])) {
      return $cache[$cache_key];
    }

    $query = $this->db->query("
      SELECT
        ps.product_sticker_id,
        ps.system_key,
        ps.color,
        ps.text_color,
        ps.sort_order,
        ps.status,
        COALESCE(psd.name, psd2.name, '') AS name
      FROM `" . DB_PREFIX . "product_sticker` ps
      LEFT JOIN `" . DB_PREFIX . "product_sticker_description` psd
        ON ps.product_sticker_id = psd.product_sticker_id
        AND psd.language_id = '" . (int)$language_id . "'
      LEFT JOIN `" . DB_PREFIX . "product_sticker_description` psd2
        ON ps.product_sticker_id = psd2.product_sticker_id
        AND psd2.language_id = '" . (int)$fallback_language_id . "'
      WHERE ps.status = '1'
        AND ps.system_key IN ('new', 'sale')
        AND COALESCE(psd.name, psd2.name, '') != ''
      ORDER BY ps.sort_order ASC, ps.product_sticker_id ASC
    ");

    $cache[$cache_key] = array();

    foreach ($query->rows as $row) {
      $cache[$cache_key][$row['system_key']] = $row;
    }

    return $cache[$cache_key];
  }

  private function getFallbackLanguageId() {
    $language_code = (string)$this->config->get('config_language');

    if ($language_code !== '') {
      $query = $this->db->query("
        SELECT language_id
        FROM `" . DB_PREFIX . "language`
        WHERE code = '" . $this->db->escape($language_code) . "'
        LIMIT 1
      ");

      if ($query->num_rows) {
        return (int)$query->row['language_id'];
      }
    }

    return (int)$this->config->get('config_language_id');
  }

  private function getGlobalPosition() {
    $allowed_positions = array(
      'top-left',
      'top-right',
      'bottom-left',
      'bottom-right',
      'bottom'
    );

    $position = $this->config->get('module_product_sticker_position');

    if (!in_array($position, $allowed_positions, true)) {
      $position = 'top-left';
    }

    return $position;
  }
}
