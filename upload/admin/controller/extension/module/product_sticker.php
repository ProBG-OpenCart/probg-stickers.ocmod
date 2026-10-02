<?php
class ControllerExtensionModuleProductSticker extends Controller {
  private $error = array();

  public function install() {
    $this->db->query("
      CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_sticker` (
        `product_sticker_id` int(11) NOT NULL AUTO_INCREMENT,
        `system_key` varchar(32) DEFAULT NULL,
        `color` varchar(7) NOT NULL DEFAULT '#000000',
        `text_color` varchar(7) NOT NULL DEFAULT '#ffffff',
        `sort_order` int(3) NOT NULL DEFAULT 0,
        `status` tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`product_sticker_id`),
        UNIQUE KEY `system_key` (`system_key`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $this->db->query("
      CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_sticker_description` (
        `product_sticker_id` int(11) NOT NULL,
        `language_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        PRIMARY KEY (`product_sticker_id`, `language_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $this->db->query("
      CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_to_sticker` (
        `product_id` int(11) NOT NULL,
        `product_sticker_id` int(11) NOT NULL,
        PRIMARY KEY (`product_id`, `product_sticker_id`),
        KEY `product_sticker_id` (`product_sticker_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $this->db->query("
      CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "category_to_sticker` (
        `category_id` int(11) NOT NULL,
        `product_sticker_id` int(11) NOT NULL,
        PRIMARY KEY (`category_id`, `product_sticker_id`),
        KEY `product_sticker_id` (`product_sticker_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $this->load->model('setting/setting');

    $this->model_setting_setting->editSetting('module_product_sticker', array(
      'module_product_sticker_position' => 'top-left',
      'module_product_sticker_new_days' => 30,
      'module_product_sticker_sale_show_discount' => 1,
      'module_product_sticker_version' => '2.1.0'
    ));

    $this->ensureSystemStickerSchema();
    $this->ensureSystemStickers();
  }

  private function upgrade() {
    $this->load->model('setting/setting');

    $version = $this->config->get('module_product_sticker_version');

    if (!$version || version_compare($version, '2.0', '<')) {
      $this->db->query("
        CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "category_to_sticker` (
          `category_id` INT(11) NOT NULL,
          `product_sticker_id` INT(11) NOT NULL,
          PRIMARY KEY (`category_id`,`product_sticker_id`),
          KEY `product_sticker_id` (`product_sticker_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
      ");

      $query = $this->db->query("
        SHOW COLUMNS
        FROM `" . DB_PREFIX . "product_sticker`
        LIKE 'position'
      ");

      if ($query->num_rows) {
        $this->db->query("
          ALTER TABLE `" . DB_PREFIX . "product_sticker`
          DROP COLUMN `position`
        ");
      }
    }

    if (!$version || version_compare($version, '2.1.0', '<')) {
      $this->ensureSystemStickerSchema();
      $this->migrateStorageSchema();
      $this->ensureMappingIndexes();
      $this->ensureSystemStickers();

      $settings = $this->model_setting_setting->getSetting('module_product_sticker');

      $position = isset($settings['module_product_sticker_position'])
        ? $settings['module_product_sticker_position']
        : $this->config->get('module_product_sticker_position');

      if (!in_array($position, $this->getAllowedPositions(), true)) {
        $position = 'top-left';
      }

      $new_days = isset($settings['module_product_sticker_new_days'])
        ? (int)$settings['module_product_sticker_new_days']
        : 30;

      if ($new_days < 1 || $new_days > 3650) {
        $new_days = 30;
      }

      $show_discount = array_key_exists('module_product_sticker_sale_show_discount', $settings)
        ? (!empty($settings['module_product_sticker_sale_show_discount']) ? 1 : 0)
        : 1;

      $settings['module_product_sticker_position'] = $position;
      $settings['module_product_sticker_new_days'] = $new_days;
      $settings['module_product_sticker_sale_show_discount'] = $show_discount;
      $settings['module_product_sticker_version'] = '2.1.0';

      $this->model_setting_setting->editSetting('module_product_sticker', $settings);

      $this->config->set('module_product_sticker_position', $position);
      $this->config->set('module_product_sticker_new_days', $new_days);
      $this->config->set('module_product_sticker_sale_show_discount', $show_discount);
      $this->config->set('module_product_sticker_version', '2.1.0');
    }
  }

  public function update() {
    $this->load->language('extension/module/product_sticker');

    if (!$this->user->hasPermission('modify', 'extension/module/product_sticker')) {
      $this->session->data['warning'] = $this->language->get('error_permission');

      $this->response->redirect(
        $this->url->link('extension/module/product_sticker', 'user_token=' . $this->session->data['user_token'], true)
      );

      return;
    }

    $this->upgrade();

    $this->session->data['success'] = $this->language->get('text_update_success');

    $this->response->redirect(
      $this->url->link('extension/module/product_sticker', 'user_token=' . $this->session->data['user_token'], true)
    );
  }

  public function uninstall() {
    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "product_sticker`");
    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "product_sticker_description`");
    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "product_to_sticker`");
    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "category_to_sticker`");

    $this->load->model('setting/setting');
    $this->model_setting_setting->deleteSetting('module_product_sticker');
  }

  public function index() {
    $this->load->language('extension/module/product_sticker');
    $this->document->setTitle($this->language->get('heading_title'));

    $this->load->model('extension/module/product_sticker');
    $this->load->model('setting/setting');

    // Run idempotent schema/data migrations when an older installed version opens the module.
    $this->upgrade();

    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateSettings()) {
      $position = isset($this->request->post['module_product_sticker_position']) ? $this->request->post['module_product_sticker_position'] : 'top-left';

      if (!in_array($position, $this->getAllowedPositions(), true)) {
        $position = 'top-left';
      }

      $new_days = isset($this->request->post['module_product_sticker_new_days'])
        ? max(1, min(3650, (int)$this->request->post['module_product_sticker_new_days']))
        : 30;

      $show_discount = !empty($this->request->post['module_product_sticker_sale_show_discount']) ? 1 : 0;
      $new_status = !empty($this->request->post['system_sticker_new_status']) ? 1 : 0;
      $sale_status = !empty($this->request->post['system_sticker_sale_status']) ? 1 : 0;

      $settings = $this->model_setting_setting->getSetting('module_product_sticker');
      $settings['module_product_sticker_position'] = $position;
      $settings['module_product_sticker_new_days'] = $new_days;
      $settings['module_product_sticker_sale_show_discount'] = $show_discount;
      $settings['module_product_sticker_version'] = '2.1.0';

      $this->model_setting_setting->editSetting('module_product_sticker', $settings);

      $this->config->set('module_product_sticker_position', $position);
      $this->config->set('module_product_sticker_new_days', $new_days);
      $this->config->set('module_product_sticker_sale_show_discount', $show_discount);
      $this->config->set('module_product_sticker_version', '2.1.0');

      $this->model_extension_module_product_sticker->setSystemStickerStatus('new', $new_status);
      $this->model_extension_module_product_sticker->setSystemStickerStatus('sale', $sale_status);

      $this->session->data['success'] = $this->language->get('text_success');

      $this->response->redirect(
        $this->url->link('extension/module/product_sticker', 'user_token=' . $this->session->data['user_token'], true)
      );
    }

    $this->getList();
  }

  public function add() {
    $this->load->language('extension/module/product_sticker');
    $this->document->setTitle($this->language->get('heading_title'));

    $this->load->model('extension/module/product_sticker');
    $this->load->model('localisation/language');

    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
      $this->model_extension_module_product_sticker->addSticker($this->request->post);

      $this->session->data['success'] = $this->language->get('text_success');

      $this->response->redirect(
        $this->url->link('extension/module/product_sticker', 'user_token=' . $this->session->data['user_token'], true)
      );
    }

    $this->getForm();
  }

  public function edit() {
    $this->load->language('extension/module/product_sticker');
    $this->document->setTitle($this->language->get('heading_title'));

    $this->load->model('extension/module/product_sticker');
    $this->load->model('localisation/language');

    $product_sticker_id = isset($this->request->get['product_sticker_id']) ? (int)$this->request->get['product_sticker_id'] : 0;

    if ($product_sticker_id && $this->model_extension_module_product_sticker->isSystemSticker($product_sticker_id)) {
      $this->session->data['warning'] = $this->language->get('error_system_sticker_edit');

      $this->response->redirect(
        $this->url->link('extension/module/product_sticker', 'user_token=' . $this->session->data['user_token'], true)
      );
    }

    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
      $this->model_extension_module_product_sticker->editSticker($product_sticker_id, $this->request->post);

      $this->session->data['success'] = $this->language->get('text_success');

      $this->response->redirect(
        $this->url->link('extension/module/product_sticker', 'user_token=' . $this->session->data['user_token'], true)
      );
    }

    $this->getForm();
  }

  public function delete() {
    $this->load->language('extension/module/product_sticker');
    $this->document->setTitle($this->language->get('heading_title'));

    $this->load->model('extension/module/product_sticker');

    if (isset($this->request->post['selected']) && $this->validateDelete()) {
      foreach ($this->request->post['selected'] as $product_sticker_id) {
        $this->model_extension_module_product_sticker->deleteSticker((int)$product_sticker_id);
      }

      $this->session->data['success'] = $this->language->get('text_success');

      $this->response->redirect(
        $this->url->link('extension/module/product_sticker', 'user_token=' . $this->session->data['user_token'], true)
      );
    }

    if (isset($this->request->get['product_sticker_id']) && $this->validateDelete()) {
      $this->model_extension_module_product_sticker->deleteSticker((int)$this->request->get['product_sticker_id']);

      $this->session->data['success'] = $this->language->get('text_success');

      $this->response->redirect(
        $this->url->link('extension/module/product_sticker', 'user_token=' . $this->session->data['user_token'], true)
      );
    }

    $this->getList();
  }

  protected function getList() {
    $data = array();

    $this->load->model('setting/setting');
    $this->addLanguageData($data);

    $data['breadcrumbs'] = array();

    $data['breadcrumbs'][] = array(
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
    );

    $data['breadcrumbs'][] = array(
      'text' => $this->language->get('text_extension'),
      'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
    );

    $data['breadcrumbs'][] = array(
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('extension/module/product_sticker', 'user_token=' . $this->session->data['user_token'], true)
    );

    $data['add'] = $this->url->link('extension/module/product_sticker/add', 'user_token=' . $this->session->data['user_token'], true);
    $data['delete'] = $this->url->link('extension/module/product_sticker/delete', 'user_token=' . $this->session->data['user_token'], true);
    $data['save_settings'] = $this->url->link('extension/module/product_sticker', 'user_token=' . $this->session->data['user_token'], true);
    $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

    $data['stickers'] = array();

    $results = $this->model_extension_module_product_sticker->getStickers();

    foreach ($results as $result) {
      $data['stickers'][] = array(
        'product_sticker_id' => $result['product_sticker_id'],
        'name'              => $result['name'],
        'color'             => $result['color'],
        'text_color'        => $result['text_color'],
        'sort_order'        => $result['sort_order'],
        'status'            => $result['status'],
        'system_key'        => isset($result['system_key']) ? $result['system_key'] : '',
        'is_system'         => !empty($result['system_key']),
        'edit'              => $this->url->link('extension/module/product_sticker/edit', 'user_token=' . $this->session->data['user_token'] . '&product_sticker_id=' . (int)$result['product_sticker_id'], true),
        'delete'            => $this->url->link('extension/module/product_sticker/delete', 'user_token=' . $this->session->data['user_token'] . '&product_sticker_id=' . (int)$result['product_sticker_id'], true)
      );
    }

    if (isset($this->request->post['module_product_sticker_position'])) {
      $data['module_product_sticker_position'] = $this->request->post['module_product_sticker_position'];
    } elseif ($this->config->get('module_product_sticker_position')) {
      $data['module_product_sticker_position'] = $this->config->get('module_product_sticker_position');
    } else {
      $data['module_product_sticker_position'] = 'top-left';
    }

    $system_stickers = $this->model_extension_module_product_sticker->getSystemStickers();

    if (isset($this->request->post['system_sticker_new_status'])) {
      $data['system_sticker_new_status'] = !empty($this->request->post['system_sticker_new_status']) ? 1 : 0;
    } else {
      $data['system_sticker_new_status'] = isset($system_stickers['new']) ? (int)$system_stickers['new']['status'] : 0;
    }

    if (isset($this->request->post['system_sticker_sale_status'])) {
      $data['system_sticker_sale_status'] = !empty($this->request->post['system_sticker_sale_status']) ? 1 : 0;
    } else {
      $data['system_sticker_sale_status'] = isset($system_stickers['sale']) ? (int)$system_stickers['sale']['status'] : 0;
    }

    if (isset($this->request->post['module_product_sticker_new_days'])) {
      $data['module_product_sticker_new_days'] = (int)$this->request->post['module_product_sticker_new_days'];
    } else {
      $data['module_product_sticker_new_days'] = (int)$this->config->get('module_product_sticker_new_days');
      if ($data['module_product_sticker_new_days'] < 1) {
        $data['module_product_sticker_new_days'] = 30;
      }
    }

    if (isset($this->request->post['module_product_sticker_sale_show_discount'])) {
      $data['module_product_sticker_sale_show_discount'] = !empty($this->request->post['module_product_sticker_sale_show_discount']) ? 1 : 0;
    } else {
      $data['module_product_sticker_sale_show_discount'] = $this->config->get('module_product_sticker_sale_show_discount') ? 1 : 0;
    }

    $data['positions'] = array(
      'top-left' => $this->language->get('text_top_left'),
      'top-right' => $this->language->get('text_top_right'),
      'bottom-left' => $this->language->get('text_bottom_left'),
      'bottom-right' => $this->language->get('text_bottom_right'),
      'bottom' => $this->language->get('text_bottom')
    );

    if (isset($this->session->data['warning'])) {
      $data['error_warning'] = $this->session->data['warning'];
      unset($this->session->data['warning']);
    } else {
      $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
    }

    $data['header'] = $this->load->controller('common/header');
    $data['column_left'] = $this->load->controller('common/column_left');
    $data['footer'] = $this->load->controller('common/footer');

    $this->response->setOutput($this->load->view('extension/module/product_sticker', $data));
  }

  protected function getForm() {
    $data = array();

    $this->addLanguageData($data);

    $data['breadcrumbs'] = array();

    $data['breadcrumbs'][] = array(
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
    );

    $data['breadcrumbs'][] = array(
      'text' => $this->language->get('text_extension'),
      'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
    );

    $data['breadcrumbs'][] = array(
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('extension/module/product_sticker', 'user_token=' . $this->session->data['user_token'], true)
    );

    if (!isset($this->request->get['product_sticker_id'])) {
      $data['action'] = $this->url->link('extension/module/product_sticker/add', 'user_token=' . $this->session->data['user_token'], true);
    } else {
      $data['action'] = $this->url->link('extension/module/product_sticker/edit', 'user_token=' . $this->session->data['user_token'] . '&product_sticker_id=' . (int)$this->request->get['product_sticker_id'], true);
    }

    $data['cancel'] = $this->url->link('extension/module/product_sticker', 'user_token=' . $this->session->data['user_token'], true);

    $this->load->model('extension/module/product_sticker');
    $this->load->model('localisation/language');

    $data['languages'] = $this->model_localisation_language->getLanguages();

    $product_sticker_info = array();

    if (isset($this->request->get['product_sticker_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
      $product_sticker_info = $this->model_extension_module_product_sticker->getSticker((int)$this->request->get['product_sticker_id']);
    }

    if (isset($this->request->post['product_sticker_description'])) {
      $data['product_sticker_description'] = $this->request->post['product_sticker_description'];
    } elseif (isset($this->request->get['product_sticker_id'])) {
      $data['product_sticker_description'] = $this->model_extension_module_product_sticker->getStickerDescriptions((int)$this->request->get['product_sticker_id']);
    } else {
      $data['product_sticker_description'] = array();
    }

    $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
    $data['error_name'] = isset($this->error['name']) ? $this->error['name'] : array();

    if (isset($this->request->post['color'])) {
      $data['color'] = $this->request->post['color'];
    } elseif (!empty($product_sticker_info)) {
      $data['color'] = $product_sticker_info['color'];
    } else {
      $data['color'] = '#000000';
    }

    if (isset($this->request->post['text_color'])) {
      $data['text_color'] = $this->request->post['text_color'];
    } elseif (!empty($product_sticker_info)) {
      $data['text_color'] = $product_sticker_info['text_color'];
    } else {
      $data['text_color'] = '#ffffff';
    }

    if (isset($this->request->post['sort_order'])) {
      $data['sort_order'] = $this->request->post['sort_order'];
    } elseif (!empty($product_sticker_info) && isset($product_sticker_info['sort_order'])) {
      $data['sort_order'] = $product_sticker_info['sort_order'];
    } else {
      $data['sort_order'] = 0;
    }

    if (isset($this->request->post['status'])) {
      $data['status'] = $this->request->post['status'];
    } elseif (!empty($product_sticker_info)) {
      $data['status'] = $product_sticker_info['status'];
    } else {
      $data['status'] = 1;
    }

    $data['header'] = $this->load->controller('common/header');
    $data['column_left'] = $this->load->controller('common/column_left');
    $data['footer'] = $this->load->controller('common/footer');

    $this->response->setOutput($this->load->view('extension/module/product_sticker_form', $data));
  }

  protected function validateForm() {
    if (!$this->user->hasPermission('modify', 'extension/module/product_sticker')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }

    if (isset($this->request->post['product_sticker_description']) && is_array($this->request->post['product_sticker_description'])) {
      foreach ($this->request->post['product_sticker_description'] as $language_id => $value) {
        $name = isset($value['name']) ? trim($value['name']) : '';

        if ((utf8_strlen($name) < 3) || (utf8_strlen($name) > 64)) {
          $this->error['name'][(int)$language_id] = $this->language->get('error_name');
        }
      }
    } else {
      $this->error['warning'] = $this->language->get('error_name');
    }

    return !$this->error;
  }

  protected function validateSettings() {
    if (!$this->user->hasPermission('modify', 'extension/module/product_sticker')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }

    if (isset($this->request->post['module_product_sticker_position'])) {
      if (!in_array($this->request->post['module_product_sticker_position'], $this->getAllowedPositions(), true)) {
        $this->error['warning'] = $this->language->get('error_position');
      }
    }

    $new_days = isset($this->request->post['module_product_sticker_new_days'])
      ? (int)$this->request->post['module_product_sticker_new_days']
      : 30;

    if ($new_days < 1 || $new_days > 3650) {
      $this->error['warning'] = $this->language->get('error_new_days');
    }

    return !$this->error;
  }

  protected function validateDelete() {
    if (!$this->user->hasPermission('modify', 'extension/module/product_sticker')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }

    $ids = array();

    if (!empty($this->request->post['selected']) && is_array($this->request->post['selected'])) {
      $ids = $this->request->post['selected'];
    } elseif (isset($this->request->get['product_sticker_id'])) {
      $ids[] = (int)$this->request->get['product_sticker_id'];
    }

    foreach ($ids as $product_sticker_id) {
      if ($this->model_extension_module_product_sticker->isSystemSticker((int)$product_sticker_id)) {
        $this->error['warning'] = $this->language->get('error_system_sticker_delete');
        break;
      }
    }

    return !$this->error;
  }

  private function ensureSystemStickerSchema() {
    $column = $this->db->query("
      SHOW COLUMNS
      FROM `" . DB_PREFIX . "product_sticker`
      LIKE 'system_key'
    ");

    if (!$column->num_rows) {
      $this->db->query("
        ALTER TABLE `" . DB_PREFIX . "product_sticker`
        ADD `system_key` varchar(32) DEFAULT NULL AFTER `product_sticker_id`,
        ADD UNIQUE KEY `system_key` (`system_key`)
      ");
    }
  }

  private function migrateStorageSchema() {
    $tables = array(
      'product_sticker',
      'product_sticker_description',
      'product_to_sticker',
      'category_to_sticker'
    );

    foreach ($tables as $table) {
      $this->db->query(
        "ALTER TABLE `" . DB_PREFIX . $table . "` ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
      );
    }
  }

  private function ensureMappingIndexes() {
    $this->ensureIndex('product_to_sticker', 'product_sticker_id', 'product_sticker_id');
    $this->ensureIndex('category_to_sticker', 'product_sticker_id', 'product_sticker_id');
  }

  private function ensureIndex($table, $index_name, $column_name) {
    $query = $this->db->query(
      "SHOW INDEX FROM `" . DB_PREFIX . $table . "` WHERE Key_name = '" . $this->db->escape($index_name) . "'"
    );

    if (!$query->num_rows) {
      $this->db->query(
        "ALTER TABLE `" . DB_PREFIX . $table . "` ADD KEY `" . $this->db->escape($index_name) . "` (`" . $this->db->escape($column_name) . "`)"
      );
    }
  }

  private function ensureSystemStickers() {
    $definitions = array(
      'new' => array(
        'color' => '#198754',
        'text_color' => '#ffffff',
        'sort_order' => 0,
        'bg' => 'Нов',
        'en' => 'New'
      ),
      'sale' => array(
        'color' => '#dc3545',
        'text_color' => '#ffffff',
        'sort_order' => 1,
        'bg' => 'Промоция',
        'en' => 'Sale'
      )
    );

    foreach ($definitions as $system_key => $definition) {
      $query = $this->db->query("
        SELECT product_sticker_id
        FROM `" . DB_PREFIX . "product_sticker`
        WHERE system_key = '" . $this->db->escape($system_key) . "'
        LIMIT 1
      ");

      if ($query->num_rows) {
        $product_sticker_id = (int)$query->row['product_sticker_id'];
      } else {
        $this->db->query("
          INSERT INTO `" . DB_PREFIX . "product_sticker`
          SET system_key = '" . $this->db->escape($system_key) . "',
              color = '" . $this->db->escape($definition['color']) . "',
              text_color = '" . $this->db->escape($definition['text_color']) . "',
              sort_order = '" . (int)$definition['sort_order'] . "',
              status = '1'
        ");

        $product_sticker_id = (int)$this->db->getLastId();
      }

      $languages = $this->db->query("
        SELECT language_id, code
        FROM `" . DB_PREFIX . "language`
        WHERE status = '1'
      ");

      foreach ($languages->rows as $language) {
        $code = strtolower($language['code']);
        $name = (strpos($code, 'bg') === 0) ? $definition['bg'] : $definition['en'];

        $this->db->query("
          INSERT INTO `" . DB_PREFIX . "product_sticker_description`
          SET product_sticker_id = '" . $product_sticker_id . "',
              language_id = '" . (int)$language['language_id'] . "',
              name = '" . $this->db->escape($name) . "'
          ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");
      }
    }
  }

  private function addLanguageData(&$data) {
    $keys = array(
      'heading_title',
      'text_extension',
      'text_success',
      'text_list',
      'text_add',
      'text_edit',
      'text_no_results',
      'text_confirm',
      'text_enabled',
      'text_disabled',
      'text_system',
      'text_manual',
      'text_tab_stickers',
      'text_tab_settings',
      'text_automated_stickers',
      'text_new_products',
      'text_sale_products',
      'text_yes',
      'text_no',
      'text_days',
      'column_name',
      'column_color',
      'column_text',
      'column_sort_order',
      'column_status',
      'column_type',
      'column_action',
      'entry_name',
      'entry_color',
      'entry_text',
      'entry_status',
      'entry_sticker_position',
      'entry_new_status',
      'entry_new_days',
      'entry_sale_status',
      'entry_sale_show_discount',
      'help_new_days',
      'help_sale_show_discount',
      'button_save',
      'button_cancel'
    );

    foreach ($keys as $key) {
      $data[$key] = $this->language->get($key);
    }
  }

  private function getAllowedPositions() {
    return array(
      'top-left',
      'top-right',
      'bottom-left',
      'bottom-right',
      'bottom'
    );
  }
}