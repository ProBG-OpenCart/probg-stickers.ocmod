<?php
class ModelExtensionModuleProductSticker extends Model {

  public function getStickersByProductId($product_id) {
    static $cache = array();

    $product_id = (int)$product_id;
    $language_id = (int)$this->config->get('config_language_id');
    $default_language_id = (int)$this->config->get('config_language_id');
    $position = $this->getGlobalPosition();

    $cache_key = $product_id . '_' . $language_id . '_' . $position;

    if (isset($cache[$cache_key])) {
      return $cache[$cache_key];
    }

    $query = $this->db->query("
      SELECT DISTINCT
        ps.product_sticker_id,
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
        AND psd2.language_id = '" . $default_language_id . "'

      LEFT JOIN `" . DB_PREFIX . "product_to_sticker` pts
        ON ps.product_sticker_id = pts.product_sticker_id
        AND pts.product_id = '" . $product_id . "'

      LEFT JOIN `" . DB_PREFIX . "product_to_category` p2c
        ON p2c.product_id = '" . $product_id . "'

      LEFT JOIN `" . DB_PREFIX . "category_to_sticker` cts
        ON ps.product_sticker_id = cts.product_sticker_id
        AND cts.category_id = p2c.category_id

      WHERE ps.status = '1'
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

    $cache[$cache_key] = $stickers;

    return $stickers;
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

    if (!in_array($position, $allowed_positions)) {
      $position = 'top-left';
    }

    return $position;
  }
}