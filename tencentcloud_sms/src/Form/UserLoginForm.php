<?php

namespace Drupal\tencentcloud_sms\Form;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\tencentcloud_sms\SmsUtils;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a user login form.
 *
 * @internal
 */
class UserLoginForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tencentcloud_sms_login';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['phone_number'] = [
      '#type' => 'textfield',
      '#title' => '手机号',
      '#size' => 60,
      '#maxlength' => UserInterface::USERNAME_MAX_LENGTH,
      '#description' => '请输入手机号',
      '#required' => TRUE,
      '#attributes' => [
        'autocorrect' => 'none',
        'autocapitalize' => 'none',
        'spellcheck' => 'false',
        'autofocus' => 'autofocus',
      ],
    ];

    $form['verify_code'] = array(
      '#type' => 'textfield',
      '#title' => '短信验证码',
      '#required' => TRUE,
      '#default_value' => '',
    );
    $form['verify_send'] = array(
      '#type' => 'button',
      '#name' => 'send_code',
      '#value' => '发送短信验证码',
      '#executes_submit_callback' => FALSE,
      '#limit_validation_errors' => array(array('name')),
      '#ajax' => array(
        'callback' => 'tencentcloud_sms_form_code_send_callback',
      ),
      '#attached' => array(
        'library' => array(
          'tencentcloud_sms/send_code',
        ),
      ),
    );
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => '登陆'];

    $form['#validate'][] = '::validate';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($form_state->get('uid'));
    SmsUtils::consumeVerifyCode($form_state->get('rid'));
    user_login_finalize($user);
    $form_state->setRedirect('<front>');
    return $form_state;
  }


  /**
   * Checks if user was not authenticated, or if too many logins were attempted.
   *
   * This validation function should always be the last one.
   */
  public function validate(array &$form, FormStateInterface $form_state)
  {
    // 发送短信验证码的ajax请求不验证
    if ( \Drupal::request()->isXmlHttpRequest() ) {
      return;
    }
    $phone = $form_state->getValue('phone_number');
    $verify_code = $form_state->getValue('verify_code');

    $rid = SmsUtils::isValidVerifyCode($phone, $verify_code, SmsUtils::TYPE_LOGIN);
    if ( $rid === 0 ) {
      $form_state->setErrorByName('phone_number', '验证码错误！');
      return $form_state;
    }
    $uid = SmsUtils::getUidByPhone($phone);
    if (!$uid) {
      $form_state->setErrorByName('phone_number', '该手机号未绑定用户！');
      return $form_state;
    }
    $form_state->set('uid', $uid);
    $form_state->set('rid', $rid);
    return $form_state;
  }

}
