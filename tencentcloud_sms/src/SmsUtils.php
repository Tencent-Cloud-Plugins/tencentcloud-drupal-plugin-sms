<?php
namespace Drupal\tencentcloud_sms;
require dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

use Drupal\user\Entity\User;
use TencentCloud\Sms\V20190711\SmsClient;
use TencentCloud\Sms\V20190711\Models\SendSmsRequest;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Common\Credential;

class SmsUtils
{
  //短信验证码发送成功
  const VERIFY_CODE_SUCCESS = 0;
  //短信验证码发送失败
  const VERIFY_CODE_FAIL = 1;
  //短信验证码已使用
  const VERIFY_CODE_USED = 2;
  //用于登录
  const TYPE_LOGIN = 1;
  //用于绑定手机号
  const TYPE_BIND = 2;
  //用于重置密码
  const TYPE_RESET_PWD = 3;
  //用于注册
  const TYPE_REGISTER = 4;
  //用于后台发送测试接口
  const TYPE_TEST = 99;
  //模板包含过期时间参数
  const TEMPLATE_HAS_EXPIRED_TIME = 1;
  //form_id和验证码类型映射
  const FORM_ID_TYPE_MAP = array(
    'tencentcloud_sms_login' => self::TYPE_LOGIN,
    'user_form' => self::TYPE_BIND,
    'user_register_form' => self::TYPE_REGISTER,
    'tencentcloud_sms_pass_reset' => self::TYPE_RESET_PWD,
  );

  /**
   * 判断手机号和验证码是否匹配
   * @param $phone
   * @param $verify_code
   * @param int $type
   * @return bool
   */
  public static function isValidVerifyCode($phone, $verify_code, $type = self::TYPE_LOGIN)
  {
    $config = \Drupal::config('tencentcloud_sms.settings');
    $expiredTime = $config->get('expired_time')?:5;
    $endTime = time();
    $beginTime = time() - $expiredTime * 60;
    $result = \Drupal::database()
      ->select('tencentcloud_sms_records', 'sr')
      ->fields('sr', ['rid','uid'])
      ->condition('phone_number', $phone, '=')
      ->condition('verify_code', $verify_code, '=')
      ->condition('status', self::VERIFY_CODE_SUCCESS, '=')
      ->condition('send_time', array($beginTime, $endTime), 'BETWEEN')
      ->condition('type', $type, '=')
      ->orderBy('send_time', 'DESC')
      ->range(0, 1)
      ->execute()->fetchObject();
    if (!$result) {
      return 0;
    }
    return intval($result->rid);
  }

  /**
   * 是否是正确的手机号
   * @param $phone
   * @return bool
   */
  public static function isPhoneNumber($phone)
  {
    return preg_match("/^1[3-9]\d{9}$/", $phone) === 1;
  }

  /**
   * 生成验证码
   * @param int $length
   * @return string
   */
  public static function verifyCodeGenerator($length = 5)
  {
    if (!is_numeric($length) || $length < 4) {
      $length = 4;
    }
    if ($length > 8) {
      $length = 8;
    }
    $nums = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
    shuffle($nums);
    $code = '';
    for ($i = 0; $i < $length; $i++) {
      $index = mt_rand(0, 9);
      $code .= $nums[$index];
    }
    return $code;
  }

  /**
   * 消费掉验证码
   * @param $rid
   */
  public static function consumeVerifyCode($rid)
  {
    try {
      \Drupal::database()->update('tencentcloud_sms_records')
        ->fields([
          'status' => self::VERIFY_CODE_USED,
        ])
        ->condition('rid', $rid, '=')
        ->execute();
    } catch (\Exception $exception) {
      return;
    }

  }

  /**
   * 通过手机号获取用户id
   * @param $phone
   * @return int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getUidByPhone($phone)
  {
    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['phone_number'=>$phone]);
    if (!$user) {
      return 0;
    }
    $user = reset($user);
    return intval($user->id());
  }


  /**
   * 发送验证码短信
   * @param $phone
   * @param int $type
   * @param int $uid
   * @throws \Exception
   */
  public static function sendVerifyCodeSMS($phone, $type = self::TYPE_LOGIN, $uid = 0)
  {
    if ( !self::isPhoneNumber($phone) ) {
      throw new \Exception('手机号错误');
    }
    $config = \Drupal::config('tencentcloud_sms.settings');

    $verifyCode = self::verifyCodeGenerator();
    $templateParams = array($verifyCode);
    //判断模板是否包含过期时间参数
    if ( $config->get('has_expire_time') === self::TEMPLATE_HAS_EXPIRED_TIME ) {
      $expiredTime = $config->get('expired_time') ?: '5';
      $templateParams[] = strval($expiredTime);
    }
    $response = self::sendSMS(array($phone), $config, $templateParams);
    $status = self::VERIFY_CODE_SUCCESS;
    if ( $response['SendStatusSet'][0]['Fee'] !== 1 || $response['SendStatusSet'][0]['Code'] !== 'Ok' ) {
      $status = self::VERIFY_CODE_FAIL;
    }
    //插入发送记录
    $result = \Drupal::database()->insert('tencentcloud_sms_records')->fields(array(
      'phone_number' => $phone,
      'uid' => $uid,
      'status' => $status,
      'type' => $type,
      'send_time' => time(),
      'response' => \json_encode($response),
      'verify_code' => $verifyCode,
    ))->execute();
    if ( !$result ) {
      throw new \Exception('发送失败!');
    }
    //返回报错信息
    if ( $status !== self::VERIFY_CODE_SUCCESS ) {
      $msg = $response['errorMessage'] ?: $response['SendStatusSet'][0]['Message'];
      throw new \Exception('发送失败!' . $msg);
    }
  }

  /**
   * 调用腾讯云发短信API
   * @param $phones
   * @param \Drupal\Core\Config\ImmutableConfig $config
   * @param array $templateParams
   * @return array|mixed
   */
  private static function sendSMS($phones, \Drupal\Core\Config\ImmutableConfig $config, $templateParams = array())
  {
    try {
      $cred = new Credential($config->get('secret_id'), $config->get('secret_key'));
      $client = new SmsClient($cred, "ap-shanghai");
      $req = new SendSmsRequest();
      $req->SmsSdkAppid = $config->get('app_id');
      $req->Sign = $config->get('sign');
      $req->ExtendCode = "0";
      foreach ($phones as &$phone) {
        $preFix = substr($phone, 0, 3);
        if ( !in_array($preFix, array('+86')) ) {
          $phone = '+86' . $phone;
        }
      }
      /**最多不要超过200个手机号*/
      $req->PhoneNumberSet = $phones;
      /** 国际/港澳台短信 senderid: 国内短信填空 */
      $req->SenderId = "";
      $req->TemplateID = $config->get('template_id');
      $req->TemplateParamSet = $templateParams;
      $resp = $client->SendSms($req);
      return \json_decode($resp->toJsonString(), JSON_OBJECT_AS_ARRAY);
    } catch (TencentCloudSDKException $e) {
      return array('requestId' => $e->getRequestId(), 'errorCode' => $e->getErrorCode(), 'errorMessage' => $e->getMessage());
    }
  }
}
