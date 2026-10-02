<?php
class ModelExtensionModuleProductSticker extends Model {

  public function getStickersByProductId($product_id) {
    static $cache = array();

    $product_id = (int)$product_id;
    $language_id = (int)$this->config->get('config_language_id');
    $fallback_language_id = $this->getFallbackLanguageId();
    $position = $this->getGlobalPosition();
    $new_days = max(1, (int)$this->config->get('module_product_sticker_new_days'));
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
      $show_discount
    ));

    if (isset($cache[$cache_key])) {
      return $cache[$cache_key];
    }

    $query = $this->db->query("
      SELECT DISTINCT
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
        AND (ps.system_key IS NULL OR ps.system_key = '')
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

    foreach ($this->getAutomatedStickers($product_id, $language_id, $fallback_language_id, $position, $new_days, $show_discount) as $sticker) {
      $stickers[] = $sticker;
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

  private function getAutomatedStickers($product_id, $language_id, $fallback_language_id, $position, $new_days, $show_discount) {
    $product_query = $this->db->query("
      SELECT price, date_added
      FROM `" . DB_PREFIX . "product`
      WHERE product_id = '" . (int)$product_id . "'
      LIMIT 1
    ");

    if (!$product_query->num_rows) {
      return array();
    }

    $product = $product_query->row;

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

    $result = array();
    $special_price = null;
    $is_new = false;

    if (!empty($product['date_added'])) {
      $date_added = strtotime($product['date_added']);
      $threshold = strtotime('-' . (int)$new_days . ' days');

      $is_new = ($date_added !== false && $date_added >= $threshold);
    }

    foreach ($query->rows as $row) {
      if ($row['system_key'] === 'new' && !$is_new) {
        continue;
      }

      if ($row['system_key'] === 'sale') {
        if ($special_price === null) {
          $special_price = $this->getActiveSpecialPrice($product_id);
        }

        $base_price = (float)$product['price'];

        if ($special_price === false || $base_price <= 0 || (float)$special_price >= $base_price) {
          continue;
        }

        if ($show_discount) {
          $discount = (int)round((1 - ((float)$special_price / $base_price)) * 100);

          if ($discount > 0) {
            $row['name'] .= ' -' . $discount . '%';
          }
        }
      }

      $row['position'] = $position;
      $result[] = $row;
    }

    return $result;
  }

  private function getActiveSpecialPrice($product_id) {
    $customer_group_id = (int)$this->config->get('config_customer_group_id');

    if ($this->customer && $this->customer->isLogged()) {
      $customer_group_id = (int)$this->customer->getGroupId();
    }

    $query = $this->db->query("
      SELECT price
      FROM `" . DB_PREFIX . "product_special`
      WHERE product_id = '" . (int)$product_id . "'
        AND customer_group_id = '" . $customer_group_id . "'
        AND ((date_start = '0000-00-00' OR date_start < NOW()) OR date_start IS NULL)
        AND ((date_end = '0000-00-00' OR date_end > NOW()) OR date_end IS NULL)
      ORDER BY priority ASC, price ASC
      LIMIT 1
    ");

    if (!$query->num_rows) {
      return false;
    }

    return (float)$query->row['price'];
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
