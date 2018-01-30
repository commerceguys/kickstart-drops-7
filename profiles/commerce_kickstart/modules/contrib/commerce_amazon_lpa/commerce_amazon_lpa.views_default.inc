<?php

/**
 * @file
 * Views defaults.
 */

/**
 * Implements hook_views_default_views().
 */
function commerce_amazon_lpa_views_default_views() {
  $views = array();

  $view = new view();
  $view->name = 'commerce_amazon_lpa_pending_authorizations';
  $view->description = '';
  $view->tag = 'Amazon Payments';
  $view->base_table = 'commerce_payment_transaction';
  $view->human_name = 'Expiring Authorizations';
  $view->core = 7;
  $view->api_version = '3.0';
  $view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

  /* Display: Master */
  $handler = $view->new_display('default', 'Master', 'default');
  $handler->display->display_options['title'] = 'Expiring Authorizations';
  $handler->display->display_options['use_more_always'] = FALSE;
  $handler->display->display_options['access']['type'] = 'perm';
  $handler->display->display_options['access']['perm'] = 'administer commerce_order entities';
  $handler->display->display_options['cache']['type'] = 'none';
  $handler->display->display_options['query']['type'] = 'views_query';
  $handler->display->display_options['exposed_form']['type'] = 'basic';
  $handler->display->display_options['pager']['type'] = 'full';
  $handler->display->display_options['pager']['options']['items_per_page'] = '20';
  $handler->display->display_options['style_plugin'] = 'table';
  /* Header: Global: Text area */
  $handler->display->display_options['header']['area']['id'] = 'area';
  $handler->display->display_options['header']['area']['table'] = 'views';
  $handler->display->display_options['header']['area']['field'] = 'area';
  $handler->display->display_options['header']['area']['label'] = 'Information';
  $handler->display->display_options['header']['area']['empty'] = TRUE;
  $handler->display->display_options['header']['area']['content'] = 'This list displays all orders which have expiring payment authorizations. Authorizations expire 30 days after they are created. You must capture payment before it expires in order to receive your funds.';
  $handler->display->display_options['header']['area']['format'] = 'filtered_html';
  /* No results behavior: Global: Unfiltered text */
  $handler->display->display_options['empty']['area_text_custom']['id'] = 'area_text_custom';
  $handler->display->display_options['empty']['area_text_custom']['table'] = 'views';
  $handler->display->display_options['empty']['area_text_custom']['field'] = 'area_text_custom';
  $handler->display->display_options['empty']['area_text_custom']['empty'] = TRUE;
  $handler->display->display_options['empty']['area_text_custom']['content'] = '<p>You have no expiring authorizations.</p>';
  /* Field: Time ago */
  $handler->display->display_options['fields']['created_1']['id'] = 'created_1';
  $handler->display->display_options['fields']['created_1']['table'] = 'commerce_payment_transaction';
  $handler->display->display_options['fields']['created_1']['field'] = 'created';
  $handler->display->display_options['fields']['created_1']['ui_name'] = 'Time ago';
  $handler->display->display_options['fields']['created_1']['label'] = '';
  $handler->display->display_options['fields']['created_1']['exclude'] = TRUE;
  $handler->display->display_options['fields']['created_1']['element_label_colon'] = FALSE;
  $handler->display->display_options['fields']['created_1']['date_format'] = 'time ago';
  $handler->display->display_options['fields']['created_1']['custom_date_format'] = '2';
  $handler->display->display_options['fields']['created_1']['second_date_format'] = 'long';
  /* Field: Commerce Payment Transaction: Amount */
  $handler->display->display_options['fields']['amount']['id'] = 'amount';
  $handler->display->display_options['fields']['amount']['table'] = 'commerce_payment_transaction';
  $handler->display->display_options['fields']['amount']['field'] = 'amount';
  /* Field: Commerce Payment Transaction: Remote ID */
  $handler->display->display_options['fields']['remote_id']['id'] = 'remote_id';
  $handler->display->display_options['fields']['remote_id']['table'] = 'commerce_payment_transaction';
  $handler->display->display_options['fields']['remote_id']['field'] = 'remote_id';
  /* Field: Commerce Payment Transaction: Remote status */
  $handler->display->display_options['fields']['remote_status']['id'] = 'remote_status';
  $handler->display->display_options['fields']['remote_status']['table'] = 'commerce_payment_transaction';
  $handler->display->display_options['fields']['remote_status']['field'] = 'remote_status';
  /* Field: Commerce Payment Transaction: Created date */
  $handler->display->display_options['fields']['created']['id'] = 'created';
  $handler->display->display_options['fields']['created']['table'] = 'commerce_payment_transaction';
  $handler->display->display_options['fields']['created']['field'] = 'created';
  $handler->display->display_options['fields']['created']['alter']['alter_text'] = TRUE;
  $handler->display->display_options['fields']['created']['alter']['text'] = '[created] ([created_1])';
  $handler->display->display_options['fields']['created']['date_format'] = 'custom';
  $handler->display->display_options['fields']['created']['custom_date_format'] = 'l, F jS, Y';
  $handler->display->display_options['fields']['created']['second_date_format'] = 'long';
  /* Field: Commerce Order: Link */
  $handler->display->display_options['fields']['view_order']['id'] = 'view_order';
  $handler->display->display_options['fields']['view_order']['table'] = 'commerce_order';
  $handler->display->display_options['fields']['view_order']['field'] = 'view_order';
  /* Sort criterion: Commerce Payment Transaction: Created date */
  $handler->display->display_options['sorts']['created']['id'] = 'created';
  $handler->display->display_options['sorts']['created']['table'] = 'commerce_payment_transaction';
  $handler->display->display_options['sorts']['created']['field'] = 'created';
  /* Filter criterion: Commerce Payment Transaction: Payment method */
  $handler->display->display_options['filters']['payment_method']['id'] = 'payment_method';
  $handler->display->display_options['filters']['payment_method']['table'] = 'commerce_payment_transaction';
  $handler->display->display_options['filters']['payment_method']['field'] = 'payment_method';
  $handler->display->display_options['filters']['payment_method']['value'] = array(
    'commerce_amazon_login_and_pay' => 'commerce_amazon_login_and_pay',
  );
  $handler->display->display_options['filters']['payment_method']['group'] = 1;
  /* Filter criterion: Commerce Payment Transaction: Remote status */
  $handler->display->display_options['filters']['remote_status']['id'] = 'remote_status';
  $handler->display->display_options['filters']['remote_status']['table'] = 'commerce_payment_transaction';
  $handler->display->display_options['filters']['remote_status']['field'] = 'remote_status';
  $handler->display->display_options['filters']['remote_status']['operator'] = 'longerthan';
  $handler->display->display_options['filters']['remote_status']['value'] = '1';
  $handler->display->display_options['filters']['remote_status']['group'] = 1;
  /* Filter criterion: Commerce Payment Transaction: Status */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'commerce_payment_transaction';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = array(
    'pending' => 'pending',
  );
  $handler->display->display_options['filters']['status']['group'] = 1;
  /* Filter criterion: Commerce Payment Transaction: Created date */
  $handler->display->display_options['filters']['created']['id'] = 'created';
  $handler->display->display_options['filters']['created']['table'] = 'commerce_payment_transaction';
  $handler->display->display_options['filters']['created']['field'] = 'created';
  $handler->display->display_options['filters']['created']['operator'] = '<';
  $handler->display->display_options['filters']['created']['value']['value'] = 'now -21 days';
  $handler->display->display_options['filters']['created']['value']['type'] = 'offset';

  /* Display: Page */
  $handler = $view->new_display('page', 'Page', 'page');
  $handler->display->display_options['path'] = 'admin/commerce/orders/pending-authorizations';
  $handler->display->display_options['menu']['type'] = 'tab';
  $handler->display->display_options['menu']['title'] = 'Expiring Authorizations';
  $handler->display->display_options['menu']['description'] = 'Orders whose Amazon Payment authorizations will be expiring in the next week.';
  $handler->display->display_options['menu']['weight'] = '0';
  $handler->display->display_options['menu']['context'] = 0;
  $handler->display->display_options['menu']['context_only_inline'] = 0;

  $views['commerce_amazon_lpa_pending_authorizations'] = $view;

  return $views;
}
