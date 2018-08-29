<?php

namespace App\Support;

/**
 * Trait SaltTrait
 * @package App\Support
 * Created by PhpStorm.
 * User: zxq
 * Date: 2018/6/7
 * Time: 20:22
 */

trait SaltTrait
{

    /**
     * @return string
     */
    public function salt()
    {
        return md5(uniqid(( time() . mt_rand(1000, 9999) )));
    }


    /**
     * 获取加密密码
     * @param $password
     * @param $salt
     * @return string
     */
    public function getPassword($password, $salt)
    {
        return hash('sha256', sha1($password) . $salt);
    }

    /**
     * 验证密码
     * @param $userPassword
     * @param $DbPassword
     * @param $salt
     * @return bool
     */
    public function checkPassword($userPassword, $DbPassword, $salt)
    {
        $userPassword = $this->getPassword($userPassword, $salt);
        if ($DbPassword == $userPassword) {
            return true;
        }
        return false;
    }
}
