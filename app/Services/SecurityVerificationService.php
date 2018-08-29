<?php

namespace App\Services;

use App\Support\ApiRequestTrait;
use Illuminate\Support\Facades\Redis;

/**
 * Class SecurityVerificationService
 * @package App\Services
 */
class SecurityVerificationService
{
    use ApiRequestTrait;
    /** @var mixed  */
    protected $validationBaseUrl;
    /** @var mixed  */
    protected $tokenBaseUrl;
    /** @var mixed  */
    protected $sendBaseUrl;
    /** @var mixed  */
    protected $countryBaseUrl;

    public function __construct()
    {
        $this->validationBaseUrl = env('VALIDATION_BASE_URL');
        $this->tokenBaseUrl      = env('TOKEN_BASE_URL');
        $this->sendBaseUrl       = env('SEND_BASE_URL');
        $this->countryBaseUrl    = env('COMMON_COUNTRY_URL');
    }

    /**
     * 获取google secret qrcode
     * @param $sessionId
     * @param $user_name
     * @param string $secret
     * @return array
     */
    public function getGoogleSecret($sessionId, $user_name, $secret = '')
    {
        $data['sessionId']     = $sessionId;
        $data['Authorization'] = 'token';
        $data['showName']      = $user_name;
        if (!empty($secret)) {
            $data['secret'] = $secret;
        }
        $url = 'captcha/googleauth/secret';
        return $this->send_request($url, 'post', $data, $this->validationBaseUrl);
    }

    /**
     * 获取sessionId
     */
    public function getSessionId()
    {
        $url = 'captcha/googleauth/sessionid?Authorization=token';
        return $this->send_request($url, 'post', '', $this->validationBaseUrl);
    }

    /**
     * 验证google验证码
     * @param $data
     * @return array
     */
    public function checkGoogleVerify($data)
    {
        $url = 'captcha/googleauth/verify/' . $data['verify'] . '/secret/' . $data['secret'];
        return $this->send_request($url, 'get', '', $this->validationBaseUrl);
    }

    /**
     * 获取验证token
     * @return array
     */
    public function createToken()
    {
        $url = "captcha/captcha?Authorization=token";
        return $this->send_request($url, 'post', "", $this->validationBaseUrl);
    }

    /**
     * 获取验证码
     * @param $data
     * @return array
     */
    public function getCaptchaCode($data)
    {
        $url      = "captcha/captcha/token/" . $data['data']['data']['token'] . "?Authorization=token&output=base64";
        $response = $response = $this->send_request($url, 'get', "", $this->tokenBaseUrl);
        return [
            'captcha' => "data:image/png;base64," . $response['data']['data']['image'],
            'token'   => $data['data']['data']['token']
        ];
    }

    /**
     * 验证验证码
     * @param $data
     * @return array
     */
    public function checkCaptcha($data)
    {
        $url = "captcha/captcha/code/" . $data['code'] . "/token/" . $data['token'] . "?Authorization=token";
        return $this->send_request($url, 'get', "", $this->validationBaseUrl);
    }

    /**
     * 发送邮件
     * @param $email
     * @param $data
     * @return array
     */
    public function sendEmail($email, $data)
    {

        //邮件内容
        $data['email'] = $email;
        $url = "notify/email";
        return $this->send_request($url, 'post', $data, $this->sendBaseUrl, "form_params");
    }

    /**
     * 发送手机code
     * @param $phone_info
     * @param $data
     * @return array|bool
     */
    public function sendSms($phone_info, $data)
    {
        $data['phone'] = $phone_info['phone_idd'] . $phone_info['phone_number'];
        $url = "notify/sms";
        return $this->send_request($url, 'post', $data, $this->sendBaseUrl, "form_params");
    }

    /**
     * 获取国家区域信息
     * @return array
     */
    public function getCountry()
    {
        $url = 'common/country?limit=300';
        return $this->send_request($url, 'get', [], $this->countryBaseUrl);
    }
}
