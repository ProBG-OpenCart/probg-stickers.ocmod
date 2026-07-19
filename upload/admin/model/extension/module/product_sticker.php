<?php
class ModelExtensionModuleProductSticker extends Model {

  public function getStickers() {
    $language_id = (int)$this->config->get('config_language_id');
    $admin_language_id = (int)$this->config->get('config_admin_language_id');

    $query = $this->db->query("
      SELECT ps.*, COALESCE(psd.name, psd2.name, '') AS name
      FROM `" . DB_PREFIX . "product_sticker` ps
      LEFT JOIN `" . DB_PREFIX . "product_sticker_description` psd 
        ON ps.product_sticker_id = psd.product_sticker_id 
        AND psd.language_id = '" . $language_id . "'
      LEFT JOIN `" . DB_PREFIX . "product_sticker_description` psd2 
        ON ps.product_sticker_id = psd2.product_sticker_id 
        AND psd2.language_id = '" . $admin_language_id . "'
      ORDER BY ps.sort_order ASC, name ASC
    ");

    return $query->rows;
  }

  public function getSticker($product_sticker_id) {
    $query = $this->db->query("
      SELECT *
      FROM `" . DB_PREFIX . "product_sticker`
      WHERE product_sticker_id = '" . (int)$product_sticker_id . "'
    ");

    return $query->row;
  }

  public function getStickerDescriptions($product_sticker_id) {
    $data = array();

    $query = $this->db->query("
      SELECT *
      FROM `" . DB_PREFIX . "product_sticker_description`
      WHERE product_sticker_id = '" . (int)$product_sticker_id . "'
    ");

    foreach ($query->rows as $result) {
      $data[(int)$result['language_id']] = array(
        'name' => $result['name']
      );
    }

    return $data;
  }

  public function addSticker($data) {
    $prepared = $this->prepareStickerData($data);

    $this->db->query("
      INSERT INTO `" . DB_PREFIX . "product_sticker`
      SET text_color = '" . $this->db->escape($prepared['text_color']) . "',
          color = '" . $this->db->escape($prepared['color']) . "',
          sort_order = '" . (int)$prepared['sort_order'] . "',
          status = '" . (int)$prepared['status'] . "'
    ");

    $product_sticker_id = (int)$this->db->getLastId();

    $this->saveStickerDescriptions($product_sticker_id, $data);

    return $product_sticker_id;
  }

  public function editSticker($product_sticker_id, $data) {
    $product_sticker_id = (int)$product_sticker_id;
    $prepared = $this->prepareStickerData($data);

    $this->db->query("
      UPDATE `" . DB_PREFIX . "product_sticker`
      SET text_color = '" . $this->db->escape($prepared['text_color']) . "',
          color = '" . $this->db->escape($prepared['color']) . "',
          sort_order = '" . (int)$prepared['sort_order'] . "',
          status = '" . (int)$prepared['status'] . "'
      WHERE product_sticker_id = '" . $product_sticker_id . "'
    ");

    $this->db->query("
      DELETE FROM `" . DB_PREFIX . "product_sticker_description`
      WHERE product_sticker_id = '" . $product_sticker_id . "'
    ");

    $this->saveStickerDescriptions($product_sticker_id, $data);
  }

  public function deleteSticker($product_sticker_id) {
    $product_sticker_id = (int)$product_sticker_id;

    $this->db->query("DELETE FROM `" . DB_PREFIX . "product_sticker` WHERE product_sticker_id = '" . $product_sticker_id . "'");
    $this->db->query("DELETE FROM `" . DB_PREFIX . "product_sticker_description` WHERE product_sticker_id = '" . $product_sticker_id . "'");
    $this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_sticker` WHERE product_sticker_id = '" . $product_sticker_id . "'");
    $this->db->query("DELETE FROM `" . DB_PREFIX . "category_to_sticker` WHERE product_sticker_id = '" . $product_sticker_id . "'");
  }

  private function saveStickerDescriptions($product_sticker_id, $data) {
    if (empty($data['product_sticker_description']) || !is_array($data['product_sticker_description'])) {
      return;
    }

    foreach ($data['product_sticker_description'] as $language_id => $value) {
      if (empty($value['name']) || trim($value['name']) === '') {
        continue;
      }

      $this->db->query("
        INSERT INTO `" . DB_PREFIX . "product_sticker_description`
        SET product_sticker_id = '" . (int)$product_sticker_id . "',
            language_id = '" . (int)$language_id . "',
            name = '" . $this->db->escape(trim($value['name'])) . "'
      ");
    }
  }

  private function prepareStickerData($data) {
    $color = isset($data['color']) ? trim($data['color']) : '#000000';
    $text_color = isset($data['text_color']) ? trim($data['text_color']) : '#ffffff';

    if (!preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
      $color = '#000000';
    }

    if (!preg_match('/^#[a-fA-F0-9]{6}$/', $text_color)) {
      $text_color = '#ffffff';
    }

    return array(
      'color'      => $color,
      'text_color' => $text_color,
      'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
      'status'     => !empty($data['status']) ? 1 : 0
    );
  }
}