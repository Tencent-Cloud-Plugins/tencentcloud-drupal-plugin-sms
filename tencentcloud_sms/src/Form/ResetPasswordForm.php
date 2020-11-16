<?php

namespace Drupal\tencentcloud_sms\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Url;
use Drupal\tencentcloud_sms\SmsUtils;
use Drupal\user\UserInterface;

/**
 * Form controller for the user password forms.
 *
 * Users followed the link in the email, now they can enter a new password.
 *
 * @internal
 */
class ResetPasswordForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tencentcloud_sms_pass_reset';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['phone_number'] = array(
      '#type' => 'textfield',
      '#title' => '手机号',
      '#description'=>'请输入手机号',
      '#size' => 60,
      '#maxlength' => UserInterface::USERNAME_MAX_LENGTH,
      '#required' => TRUE,
    );
    $form['password'] = array(
      '#type' => 'password',
      '#title' => '新密码',
      '#description'=>'请输入新密码',
      '#size' => 60,
      '#required' => TRUE,
      '#attributes' => [
        'autocorrect' => 'off',
        'autocapitalize' => 'off',
        'spellcheck' => 'false',
        'autofocus' => 'autofocus',
      ],
    );
    $form['confirm_password'] = array(
      '#type' => 'password',
      '#title' => '确认密码',
      '#description'=>'请再次输入新密码',
      '#size' => 60,
      '#required' => TRUE,
    );
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
    $form['#validate'][] = '::validate';
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => '提交'];
    return $form;
  }

  /**
   * Sets an error if supplied username has been blocked.
   */
  public function validate(array &$form, FormStateInterface $form_state)
  {
    //发送短信验证码的ajax请求不验证
    if( \Drupal::request()->isXmlHttpRequest()){
      return;
    }
    $password = $form_state->getValue('password');
    $confirm_password = $form_state->getValue('confirm_password');
    if ($confirm_password !== $password) {
      $form_state->setErrorByName('password', '两次输入的密码不匹配！');
      return $form_state;
    }

    $phone = $form_state->getValue('phone_number');
    $verify_code = $form_state->getValue('verify_code');
    $rid = SmsUtils::isValidVerifyCode($phone, $verify_code, SmsUtils::TYPE_RESET_PWD);
    if ($rid === 0) {
      $form_state->setErrorByName('verify_code', '验证码错误！');
      return $form_state;
    }
    $uid = SmsUtils::getUidByPhone($phone);
    $form_state->set('uid', $uid);
    $form_state->set('rid', $rid);
    return $form_state;

  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $uid = $form_state->get('uid');
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    $password = $form_state->getValue('password');
    $user->setPassword($password);
    $user->save();
    $form_state->setRedirect('user.login');
    $messenger = \Drupal::messenger()->addStatus('修改成功!');
    SmsUtils::consumeVerifyCode($form_state->get('rid'));
    return;
  }

}
