<?php
/**
 * Copyright (C) 2020 Tencent Cloud.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace Drupal\tencentcloud_sms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tencentcloud_sms\UsageDataReport;

/**
 * Configure Tencentcloud Captcha settings.
 */
class TencentcloudSmsSettingsForm extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'tencentcloud_sms_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return ['tencentcloud_sms.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('tencentcloud_sms.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => '基础设置',
      '#open' => true,
      '#attached' => array(
        'library' => array(
          'tencentcloud_sms/hide_secret',
        ),
      ),
    ];

    $form['general']['tc_secret_id'] = [
      '#description' => '访问 <a target="_blank" href=":url">密钥管理</a>获取SecretId和SecretKey或通过"新建密钥"创建密钥串.',
      '#maxlength' => 40,
      '#required' => true,
      '#title' => 'Secret Id',
      '#type' => 'password',
      '#attributes'=> array(
        'value'=>$config->get('secret_id'),
      )
    ];

    $form['general']['tc_secret_key'] = [
      '#description' => '访问 <a target="_blank" href="https://console.qcloud.com/cam/capi">密钥管理</a>获取SecretId和SecretKey或通过"新建密钥"创建密钥串.',
      '#maxlength' => 40,
      '#required' => true,
      '#title' => 'Secret key',
      '#type' => 'password',
      '#attributes'=> array(
        'value'=>$config->get('secret_key'),
      )
    ];

    $form['general']['tc_app_id'] = [
      '#default_value' => $config->get('app_id'),
      '#description' => '访问 <a target="_blank" href="https://console.cloud.tencent.com/smsv2/app-manage">应用管理</a>获取SDKAppID或通过"创建应用"创建SDKAppID.',
      '#maxlength' => 40,
      '#required' => true,
      '#title' => 'SDKAppID',
      '#type' => 'textfield',
    ];

    $form['general']['tc_sign'] = [
      '#default_value' => $config->get('sign'),
      '#description' => '审核通过的短信签名，不包含【】.',
      '#maxlength' => 40,
      '#required' => true,
      '#title' => '短信签名',
      '#type' => 'textfield',
    ];

    $form['general']['tc_template_id'] = [
      '#default_value' => $config->get('template_id'),
      '#description' => '审核通过的模板ID',
      '#maxlength' => 40,
      '#required' => true,
      '#title' => '模板ID',
      '#type' => 'textfield',
    ];

    $form['general']['tc_expired_time'] = [
      '#default_value' => $config->get('expired_time'),
      '#description' => '单位：分钟，默认5。范围【1-360】',
      '#min' => 1,
      '#max' => 360,
      '#required' => true,
      '#title' => '验证码有效时间',
      '#type' => 'number',
    ];

    $form['general']['tc_has_expire_time'] = [
      '#default_value' => $config->get('has_expire_time'),
      '#description' => '请与模板中参数个数保持一致，否则将导致短信发送失败',
      '#maxlength' => 40,
      '#title' => '模板是否包含过期时间',
      '#type' => 'checkbox',
    ];

    $form['custom_filed'] = array(
      '#type' => 'markup',
      '#prefix'=>'<div id="custom_filed">',
      '#suffix'=>'</div>',
      '#markup' => '<a href="https://openapp.qq.com/docs/Drupal/sms.html" target="_blank">文档中心</a> | <a href="https://github.com/Tencent-Cloud-Plugins/tencentcloud-drupal-plugin-sms" target="_blank">GitHub</a> | <a
                    href="https://support.qq.com/product/164613" target="_blank">意见反馈</a>',
      '#tree' => true,
      '#attributes'=>[
        'class'=> array(
          'custom_filed'
        )
      ],
      '#attached' => array(
        'library' => array(
          'tencentcloud_sms/custom_filed',
        ),
      ),
      '#weight' => 105,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $secret_id = $form_state->getValue('tc_secret_id');
    $secret_key = $form_state->getValue('tc_secret_key');
    $app_id = $form_state->getValue('tc_app_id');
    $sign = $form_state->getValue('tc_sign');
    $tc_template_id = $form_state->getValue('tc_template_id');
    $expired_time = $form_state->getValue('tc_expired_time');
    $has_expire_time = $form_state->getValue('tc_has_expire_time');
    $config = $this->config('tencentcloud_sms.settings');
    $config
      ->set('secret_id', $secret_id)
      ->set('secret_key', $secret_key)
      ->set('app_id', $app_id)
      ->set('sign', $sign)
      ->set('template_id', $tc_template_id)
      ->set('expired_time', intval($expired_time))
      ->set('has_expire_time', intval($has_expire_time))
      ->save();
    parent::submitForm($form, $form_state);

    $host = \Drupal::request()->getSchemeAndHttpHost();
    $uuid = substr(md5(\Drupal::config('system.site')->get('uuid')), 8, 16);
    $data = [
      'action' => 'save_config',
      'plugin_type' => 'sms',
      'data' => [
        'site_id' => 'drupal_' . $uuid,
        'site_url' => $host,
        'site_app' => 'Drupal',
        'cust_sec_on' => 1,
        'others' => \json_encode([])
      ]
    ];
    (new UsageDataReport($secret_id, $secret_key))->report($data);
  }

}
