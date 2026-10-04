<?php
class ModelExtensionModuleProductSticker extends Model {

  public function getStickers() {
    return $this->getStickerList(false);
  }

  public function getAssignableStickers() {
    return $this->getStickerList(true);
  }

  public function getSystemStickers() {
    $data = array();

    foreach ($this->getStickers() as $sticker) {
      if (!empty($sticker['system_key'])) {
        $data[$sticker['system_key']] = $sticker;
      }
    }

    return $data;
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

    if ($this->isSystemSticker($product_sticker_id)) {
      return false;
    }

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

    return true;
  }

  public function deleteSticker($product_sticker_id) {
    $product_sticker_id = (int)$product_sticker_id;

    if ($this->isSystemSticker($product_sticker_id)) {
      return false;
    }

    $this->db->query("DELETE FROM `" . DB_PREFIX . "product_sticker` WHERE product_sticker_id = '" . $product_sticker_id . "'");
    $this->db->query("DELETE FROM `" . DB_PREFIX . "product_sticker_description` WHERE product_sticker_id = '" . $product_sticker_id . "'");
    $this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_sticker` WHERE product_sticker_id = '" . $product_sticker_id . "'");
    $this->db->query("DELETE FROM `" . DB_PREFIX . "category_to_sticker` WHERE product_sticker_id = '" . $product_sticker_id . "'");

    return true;
  }

  public function isSystemSticker($product_sticker_id) {
    if (!$this->hasSystemKeyColumn()) {
      return false;
    }

    $query = $this->db->query("
      SELECT system_key
      FROM `" . DB_PREFIX . "product_sticker`
      WHERE product_sticker_id = '" . (int)$product_sticker_id . "'
      LIMIT 1
    ");

    return !empty($query->row['system_key']);
  }

  public function setSystemStickerStatus($system_key, $status) {
    if (!$this->hasSystemKeyColumn()) {
      return false;
    }

    $allowed = array('new', 'sale');

    if (!in_array($system_key, $allowed, true)) {
      return false;
    }

    $this->db->query("
      UPDATE `" . DB_PREFIX . "product_sticker`
      SET status = '" . (!empty($status) ? 1 : 0) . "'
      WHERE system_key = '" . $this->db->escape($system_key) . "'
    ");

    return true;
  }

  public function getSystemStickerDescriptions($system_key) {
    if (!$this->hasSystemKeyColumn()) {
      return array();
    }

    $allowed = array('new', 'sale');

    if (!in_array($system_key, $allowed, true)) {
      return array();
    }

    $query = $this->db->query("
      SELECT psd.language_id, psd.name
      FROM `" . DB_PREFIX . "product_sticker` ps
      INNER JOIN `" . DB_PREFIX . "product_sticker_description` psd
        ON ps.product_sticker_id = psd.product_sticker_id
      WHERE ps.system_key = '" . $this->db->escape($system_key) . "'
    ");

    $data = array();

    foreach ($query->rows as $row) {
      $data[(int)$row['language_id']] = array(
        'name' => $row['name']
      );
    }

    return $data;
  }

  public function saveSystemStickerDescriptions($system_key, $descriptions) {
    if (!$this->hasSystemKeyColumn() || !is_array($descriptions)) {
      return false;
    }

    $allowed = array('new', 'sale');

    if (!in_array($system_key, $allowed, true)) {
      return false;
    }

    $query = $this->db->query("
      SELECT product_sticker_id
      FROM `" . DB_PREFIX . "product_sticker`
      WHERE system_key = '" . $this->db->escape($system_key) . "'
      LIMIT 1
    ");

    if (!$query->num_rows) {
      return false;
    }

    $product_sticker_id = (int)$query->row['product_sticker_id'];

    foreach ($descriptions as $language_id => $value) {
      $name = isset($value['name']) ? trim($value['name']) : '';

      if ($name === '') {
        continue;
      }

      $this->db->query("
        INSERT INTO `" . DB_PREFIX . "product_sticker_description`
        SET product_sticker_id = '" . $product_sticker_id . "',
            language_id = '" . (int)$language_id . "',
            name = '" . $this->db->escape($name) . "'
        ON DUPLICATE KEY UPDATE name = VALUES(name)
      ");
    }

    return true;
  }

  private function getStickerList($assignable_only) {
    $has_system_key = $this->hasSystemKeyColumn();
    $admin_language_id = (int)$this->config->get('config_admin_language_id');
    $store_language_id = (int)$this->config->get('config_language_id');

    $sql = "
      SELECT ps.*, COALESCE(psd_admin.name, psd_store.name, '') AS name
      FROM `" . DB_PREFIX . "product_sticker` ps
      LEFT JOIN `" . DB_PREFIX . "product_sticker_description` psd_admin
        ON ps.product_sticker_id = psd_admin.product_sticker_id
        AND psd_admin.language_id = '" . $admin_language_id . "'
      LEFT JOIN `" . DB_PREFIX . "product_sticker_description` psd_store
        ON ps.product_sticker_id = psd_store.product_sticker_id
        AND psd_store.language_id = '" . $store_language_id . "'
    ";

    if ($assignable_only && $has_system_key) {
      $sql .= " WHERE ps.system_key IS NULL OR ps.system_key = ''";
    }

    if ($has_system_key) {
      $sql .= " ORDER BY CASE WHEN ps.system_key IS NULL OR ps.system_key = '' THEN 1 ELSE 0 END ASC, ps.sort_order ASC, name ASC";
    } else {
      $sql .= " ORDER BY ps.sort_order ASC, name ASC";
    }

    $query = $this->db->query($sql);

    return $query->rows;
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
