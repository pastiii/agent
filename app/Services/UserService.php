<?php

namespace App\Services;

use App\Support\ApiRequestTrait;

/**
 * Created by PhpStorm.
 * User: Shao
 * Date: 2018/7/10 0010
 * Time: 13:03
 */
class UserService
{
    use ApiRequestTrait;
    /** @var mixed  */
    protected $authBaseUrl;

    public function __construct()
    {
        $this->authBaseUrl = env('AUTH_BASE_URL');
    }

    /**
     * 通过邮箱获取email
     * @param $data
     * @return array
     */
    public function getUserEmailByMail($data)
    {
        $email_url = "userauth/user_email/email/" . $data;
        return $this->send_request($email_url, 'get', '', $this->authBaseUrl);
    }

    /**
     * 通过手机号获取用户信息
     * @param $phone
     * @param $phone_idd
     * @return array
     */
    public function getUserPhoneByPhone($phone, $phone_idd)
    {
        $url = 'userauth/user_phone/idd/' . $phone_idd . '/phone/' . $phone;
        return $this->send_request($url, 'get', '', $this->authBaseUrl);
    }
}
