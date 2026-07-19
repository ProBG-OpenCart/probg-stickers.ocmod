<?php
class ControllerExtensionModuleProductSticker extends Controller {
  private $error = array();

  public function install() {
    $this->db->query("
      CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_sticker` (
        `product_sticker_id` int(11) NOT NULL AUTO_INCREMENT,
        `color` varchar(7) NOT NULL DEFAULT '#000000',
        `text_color` varchar(7) NOT NULL DEFAULT '#ffffff',
        `sort_order` int(3) NOT NULL DEFAULT 0,
        `status` tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`product_sticker_id`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
    ");

    $this->db->query("
      CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_sticker_description` (
        `product_sticker_id` int(11) NOT NULL,
        `language_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        PRIMARY KEY (`product_sticker_id`, `language_id`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
    ");

    $this->db->query("
      CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_to_sticker` (
        `product_id` int(11) NOT NULL,
        `product_sticker_id` int(11) NOT NULL,
        PRIMARY KEY (`product_id`, `product_sticker_id`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
    ");

    $this->db->query("
      CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "category_to_sticker` (
        `category_id` int(11) NOT NULL,
        `product_sticker_id` int(11) NOT NULL,
        PRIMARY KEY (`category_id`, `product_sticker_id`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
    ");

    $this->load->model('setting/setting');

    $this->model_setting_setting->editSettingValue(
      'module_product_sticker',
      'module_product_sticker_position',
      'top-left'
    );

    $this->model_setting_setting->editSettingValue(
      'module_product_sticker',
      'module_product_sticker_version',
      '1.4'
    );
  }
  public function upgrade() {

    $this->load->model('setting/setting');

    $version = $this->config->get('module_product_sticker_version');

    if (!$version || version_compare($version, '2.0', '<')) {

      // category stickers
      $this->db->query("
      CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "category_to_sticker` (
        `category_id` INT(11) NOT NULL,
        `product_sticker_id` INT(11) NOT NULL,
        PRIMARY KEY (`category_id`,`product_sticker_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

      // премахване на старото поле position
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

      // глобална настройка
      if (!$this->config->get('module_product_sticker_position')) {

        $this->model_setting_setting->editSettingValue(
          'module_product_sticker',
          'module_product_sticker_position',
          'top-left'
        );

      }

      // нова версия
      $this->model_setting_setting->editSettingValue(
        'module_product_sticker',
        'module_product_sticker_version',
        '2.0'
      );
    }
  }

  public function update() {
    $this->upgrade();

    $this->session->data['success'] = 'Module updated successfully!';

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

    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateSettings()) {
      $position = isset($this->request->post['module_product_sticker_position']) ? $this->request->post['module_product_sticker_position'] : 'top-left';

      if (!in_array($position, $this->getAllowedPositions())) {
        $position = 'top-left';
      }

      $this->model_setting_setting->editSettingValue(
        'module_product_sticker',
        'module_product_sticker_position',
        $position
      );

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
    $this->load->model('setting/setting');

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

    $data['positions'] = array(
      'top-left' => $this->language->get('text_top_left'),
      'top-right' => $this->language->get('text_top_right'),
      'bottom-left' => $this->language->get('text_bottom_left'),
      'bottom-right' => $this->language->get('text_bottom_right'),
      'bottom' => $this->language->get('text_bottom')
    );

    $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

    $data['header'] = $this->load->controller('common/header');
    $data['column_left'] = $this->load->controller('common/column_left');
    $data['footer'] = $this->load->controller('common/footer');

    $this->response->setOutput($this->load->view('extension/module/product_sticker', $data));
  }

  protected function getForm() {
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
      if (!in_array($this->request->post['module_product_sticker_position'], $this->getAllowedPositions())) {
        $this->error['warning'] = 'Невалидна позиция на стикерите.';
      }
    }

    return !$this->error;
  }

  protected function validateDelete() {
    if (!$this->user->hasPermission('modify', 'extension/module/product_sticker')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }

    return !$this->error;
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