<?php

namespace App\Services;

use App\Support\ApiRequestTrait;
use Illuminate\Support\Facades\Redis;

/**
 * Created by PhpStorm.
 * User: Shao
 * Date: 2018/6/7
 * Time: 14:32
 */
class AgentService
{
    use ApiRequestTrait;
    /** @var int */
    const TYPE = 1;
    /** @var mixed */
    protected $agentBaseUrl;
    /** @var mixed */
    protected $sumUserBaseUrl;
    /** @var mixed */
    protected $authBaseUrl;

    public function __construct()
    {
        $this->agentBaseUrl   = env('AGENT_BASE_URL');
        $this->sumUserBaseUrl = env('SUMUSER_BASE_URL');
        $this->authBaseUrl    = env('AUTH_BASE_URL');
    }

    /**
     *  agent
     * 获取代理商信息
     * @param int $id 代理商id
     * @return array
     */
    public function getAgent($id)
    {
        // 获取数据返回
        $url = 'agent/agent_promo/id/' . $id;
        return $this->send_request($url, 'get', [], $this->agentBaseUrl);
    }

    /**
     *  agent_promo
     * 获取推广码信息
     * @param int $id
     * @return  array
     */
    public function getAgentPromo($id)
    {
        // 获取数据返回
        $url = 'agent/agent_promo/id/' . $id;
        return $this->send_request($url, 'GET', [], $this->agentBaseUrl);
    }

    /**
     * 获取推广码信息列表
     * @param $param
     * @return array
     */
    public function getAgentPromoList($param)
    {
        // 获取数据
        $url        = 'agent/agent_promo?' . http_build_query($param);
        $returnData = $this->send_request($url, 'GET', [], $this->agentBaseUrl);
        // 处理返回数据
        if ($returnData['real_code'] != 200) {
            return $returnData;
        }

        $list = $returnData['data']['list'];
        if (!empty($list)) {
            foreach ($list as $k => $v) {   //  去除不必要信息
                unset($list[$k]['agent_name']);
                unset($list[$k]['updated_at']);
                // $list[$k]['agent_promo'] = AGENT_PROMO_URL . $v['agent_promo'];
                $list[$k]["created_at"] = date('Y-m-d H:i:s', $v['created_at']);
            }
        }
        $returnData['data']['list'] = $list;
        return $returnData;
    }

    /**
     * 获取代理推广码总数
     * @param $agent_id
     * @return array
     */
    public function getAgentPromoCount($agent_id)
    {
        // 获取数据返回
        $url = 'agent/agent_promo/count?agent_id=' . $agent_id;
        return $this->send_request($url, 'GET', [], $this->agentBaseUrl);
    }

    /**
     * 通过推广码获取推广码信息
     * @param $agent_promo
     * @return array
     */
    public function getAgentPromoByPromo($agent_promo)
    {
        // 获取数据返回
        $url = 'agent/agent_promo/promo/' . $agent_promo;
        return $this->send_request($url, 'GET', [], $this->agentBaseUrl);
    }

    /**
     * 创建推广码信息
     * @param $userInfo
     * @return array
     */
    public function createAgentPromo($userInfo)
    {
        // 组装数据
        $data = [
            "agent_id"   => (int) $userInfo['user_id'],       // 代理id
            "agent_name" => $userInfo['user_name'],         // 获取昵称
        ];

        // 获取数据返回
        $url = 'agent/agent_promo';
        return $this->send_request($url, 'POST', $data, $this->agentBaseUrl);
    }

    /**
     * 修改google key
     * @param $secret
     * @param $user_id
     * @return array
     */
    public function editUserGoogleAuth($secret, $user_id)
    {
        $url                = 'agent/agent_info_google_key/id/' . $user_id;
        $data['google_key'] = $secret;
        return $this->send_request($url, 'patch', $data, $this->agentBaseUrl);
    }

    /**
     *  agent_contacts
     * 创建代理平台账号信息
     * @param $data
     * @return array
     */
    public function createAgentContacts($data)
    {
        // 获取数据返回
        $url = 'agent/agent_contacts';
        return $this->send_request($url, 'POST', $data, $this->agentBaseUrl);
    }


    /**
     *   email获取代理平台账号信息
     * @param $agent_id
     * @param $email
     * @return array
     */
    public function emailGetAgentContacts($agent_id, $email)
    {
        // 组装获取数据参数
        $data = [
            "order"    => "DESC",
            "sort"     => "agent_contacts_id",
            "limit"    => 1,
            "start"    => 0,
            "agent_id" => $agent_id,
            "email"    => $email,
        ];

        // 获取数据返回
        $url = 'agent/agent_contacts?' . http_build_query($data);
        return $this->send_request($url, 'GET', [], $this->agentBaseUrl);
    }

    /**
     * phone获取代理平台账号信息
     * @param $agent_id
     * @param $phone_idd
     * @param $phone_number
     * @return array
     */
    public function phoneGetAgentContacts($agent_id, $phone_idd, $phone_number)
    {
        // 组装获取数据参数
        $data = [
            "order"        => "DESC",
            "sort"         => "agent_contacts_id",
            "limit"        => 1,
            "start"        => 0,
            "agent_id"     => $agent_id,
            "phone_idd"    => $phone_idd,
            "phone_number" => $phone_number,
        ];

        // 获取数据返回
        $url = 'agent/agent_contacts?' . http_build_query($data);
        return $this->send_request($url, 'GET', [], $this->agentBaseUrl);
    }


    /**
     * 获取代理平台账号信息列表
     * @param $agent_id
     * @param $page
     * @param int $limit
     * @return array
     */
    public function getUserAccessList($agent_id, $page, $limit)
    {
        // 组装获取数据参数
        $data = [
            "order"    => "DESC",
            "sort"     => "agent_contacts_id",
            "limit"    => $limit,
            "start"    => ( ( $page - 1 ) * $limit ),
            "agent_id" => $agent_id,
        ];

        // 获取数据返回
        $url        = 'agent/agent_contacts?' . http_build_query($data);
        $accessList = $this->send_request($url, 'GET', [], $this->agentBaseUrl);

        if ($accessList['real_code'] != 200) {
            return $accessList;
        }

        $list = $accessList['data']['list'];
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                unset($list[$k]['agent_id']);
                unset($list[$k]['agent_name']);
                $list[$k]["created_at"] = date('Y-m-d H:i:s', $v['created_at']);
                $list[$k]["updated_at"] = date('Y-m-d H:i:s', $v['updated_at']);
            }
        }
        $accessList['data']['list'] = $list;
        return $accessList;
    }

    /**
     * 获取代理平台账号信息数
     * @param $agent_id
     * @return array
     */
    public function getAgentContactsCount($agent_id)
    {
        // 获取数据返回
        $url = 'agent/agent_contacts/count?agent_id=' . $agent_id;
        return $this->send_request($url, 'GET', [], $this->agentBaseUrl);
    }

    /**
     * 删除代理平台账号绑定
     * @param $id
     * @return array
     */
    public function deleteAgentContacts($id)
    {
        // 获取数据返回
        $url = 'agent/agent_contacts/id/' . $id;
        return $this->send_request($url, 'delete', [], $this->agentBaseUrl);
    }

    /**
     * 创建代理返点率信息
     * @param array $data
     * @return array
     */
    public function createAgentRebate($data)
    {
        // 获取数据返回
        $url = 'agent/agent_rebate';
        return $this->send_request($url, 'POST', $data, $this->agentBaseUrl);
    }

    /**
     * 修改代理返点率
     * @param $data
     * @return array
     */
    public function updateAgentRebate($data)
    {
        // 获取数据返回
        $url = 'agent/agent_rebate/id/' . $data['agent_id'];
        unset($data['agent_id']);       // 去除不必要参数
        return $this->send_request($url, 'PATCH', $data, $this->agentBaseUrl);
    }

    /**
     * 获取代理返点信息
     * @param $agent_id
     * @return array
     */
    public function getAgentRebate($agent_id)
    {
        // 获取数据返回
        $url = 'agent/agent_rebate/id/' . $agent_id;
        return $this->send_request($url, 'GET', [], $this->agentBaseUrl);
    }

    /**
     * agent_info
     * 获取代理商详细信息
     * @param $agent_id
     * @return array
     */
    public function getAgentInfo($agent_id)
    {
        // 获取数据
        $url       = 'agent/agent_info/id/' . $agent_id;
        $agentInfo = $this->send_request($url, 'GET', [], $this->agentBaseUrl);
        return $agentInfo;
    }

    /**
     * 通过邮箱获取代理商详细信息
     * @param $email
     * @return array
     */
    public function getAgentEmailByMail($email)
    {
        // 获取数据返回
        $url = 'agent/agent_info/email/' . $email;
        return $this->send_request($url, 'GET', [], $this->agentBaseUrl);
    }

    /**
     * 代理商记录列表
     * @param $data
     * @return array
     */
    public function getUserList($data)
    {
        // 请求数据
        $data['limit'] = isset($data['limit']) ? $data['limit'] : 10;
        $data['page']  = isset($data['page']) ? $data['page'] : 1;

        $count = $this->getUserCount($data);
        if ($count['code'] != 200) {
            return $count;
        }
        // 组装分页数据
        $page_info['count']        = $count['data']['count'];
        $page_info['current_page'] = $data['page'];
        $page_info['total_page']   = ceil($count['data']['count'] / $data['limit']);

        $data['start'] = ( $data['page'] - 1 ) * $data['limit'];
        unset($data['page']);

        // 请求数据
        $url       = 'userauth/user?' . http_build_query($data);
        $user_data = $this->send_request($url, 'GET', [], $this->authBaseUrl);

        // 返回数据
        if ($user_data['real_code'] != 200) {
            return $user_data;
        }

        $list = $user_data['data']['list'];
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $list[$k]['created_at'] = date('Y-m-d H:i:s', $v['created_at']);
                if ($v['parent_user_id'] == 0) {
                    $list[$k]['parent_user_id']   = $v['agent_id'];
                    $list[$k]['parent_user_name'] = $v['agent_name'];
                    $list[$k]['status'] = 2;
                }
                unset($list[$k]['password']);
                unset($list[$k]['salt']);
                unset($list[$k]['agent_id']);
                unset($list[$k]['agent_name']);
                unset($list[$k]['updated_at']);
            }
            $user_data['data']['list'] = $list;
        }
        $user_data['data']['page'] = $page_info;
        return $user_data;
    }

    /**
     * 代理商记录数量
     * @param $data
     * @return array
     */
    public function getUserCount($data)
    {
        // 去除不必要参数
        if (isset($data['limit'])) {
            unset($data['limit']);
        }
        if (isset($data['page'])) {
            unset($data['page']);
        }

        // 获取数据返回
        $url = 'userauth/user/count?' . http_build_query($data);
        return $this->send_request($url, 'GET', [], $this->authBaseUrl);
    }

    /**
     * @param $data
     * @return array
     */
    public function getAgentNewUserDayList($data)
    {
        // 获取数据返回
        $url = 'sumuser/agent_new_user_day?' . http_build_query($data);
        return $this->send_request($url, 'GET', [], $this->sumUserBaseUrl);
    }

    /**
     * 通过邮箱获取email
     * @param $data
     * @return array
     */
    public function getUserEmailByMail($data)
    {
        $url = "agent/agent_info/email/" . $data;
        return $this->send_request($url, 'get', "", $this->agentBaseUrl);
    }

    /**
     * 通过用户id获取用户信息
     * @param $id
     * @return array
     */
    public function getUser($id)
    {
        $url  = "agent/agent/id/" . $id;
        $data = $this->send_request($url, 'get', "", $this->agentBaseUrl);
        return $data;
    }

    /**
     * 获取用户状态
     * @param $id
     * @return array
     */
    public function getUserStatus($id)
    {
        $url = 'agent/agent_status/id/' . $id;
        return $this->send_request($url, 'get', "", $this->agentBaseUrl);
    }

    /**
     * 获取登录前的状态
     * @param $id
     * @return array|bool
     */
    public function validateIp($id)
    {
        //为更换ip一天内免验证,获取最后一次登录信息
        $pageSize = 1;
        $page     = 1;
        $history  = $this->getUserLoginHistoryList($id, $pageSize, $page);

        //新创建用户,第一次登陆
        if (empty($history['data']['list'])) {
            return false; //需要验证
        }

        $last_login_time   = isset($history['data']['list'][0]['created_at']) ?
            $history['data']['list'][0]['created_at'] : "";
        $last_login_ip     = isset($history['data']['list'][0]['ip']) ? $history['data']['list'][0]['ip'] : "";
        $last_login_status = isset($history['data']['list'][0]['use_second']) ?
            $history['data']['list'][0]['use_second'] : "";
        $ip                = $_SERVER["REMOTE_ADDR"];
        if ($last_login_status != 1) {
            return false;
        }

        if ($ip != $last_login_ip || ( time() - $last_login_time ) > 86400) {
            return false;
        }
        return true; //免验证
    }

    /**
     * 用户登录历史
     * @param $agent_id
     * @param $pageSize
     * @param $page
     * @return array
     */
    public function getUserLoginHistoryList($agent_id, $pageSize, $page)
    {
        $page = ( $page - 1 ) * $pageSize;
        $url  = "agent/agent_login_history?order=DESC&sort=agent_login_history_id&limit=" .
            $pageSize . "&start=" . $page . "&agent_id=" . $agent_id;
        return $this->send_request($url, 'get', "", $this->agentBaseUrl);
    }

    /**
     * 判断用户是否是异地登录
     * @param $id
     * @return bool|mixed
     */
    public function checkIp($id)
    {
        //获取用户上次登录历史
        $pageSize = 1;
        $page     = 1;
        $ip       = $_SERVER["REMOTE_ADDR"];
        $history  = $this->getUserLoginHistoryList($id, $pageSize, $page);
        if (empty($history['data']['list'])) {
            $last_login_ip = $_SERVER["REMOTE_ADDR"];
        } else {
            $last_login_ip = isset($history['data']['list'][0]['ip']) ? $history['data']['list'][0]['ip'] : "";
        }

        if ($last_login_ip == $ip) {
            return false;
        }

        //获取用户手机号码
        $phone_info = $this->getAgentInfo($id);
        if ($phone_info['code'] != 200) {
            return false;
        }
        return $phone_info['data'];
    }

    /**
     * 创建登录历史
     * @param $user_info
     * @param $token
     * @param $use_second
     * @return array
     */
    public function createLoginHistory($user_info, $token, $use_second = 0)
    {
        //创建用户登录信息
        $history['token'] = $token;
        $history['ip']    = $_SERVER["REMOTE_ADDR"];
        $history['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        //$history['user_agent']   = "ua";
        $history['login_type']   = self::TYPE;
        $history['use_second']   = $use_second;
        $history['login_status'] = empty($token) ? 0 : 1;

        $history['agent_id']   = $user_info['agent_id'];
        $history['agent_name'] = $user_info['agent_name'];
        $url                   = "agent/agent_login_history";
        return $this->send_request($url, "post", $history, $this->agentBaseUrl);
    }

    /**
     * 创建登录二次验证信息
     * @param  array $status_data
     * @param  array $validate_first
     * @param  array $user_info
     * @return mixed
     */
    public function createInfo($status_data, $user_info, $validate_first)
    {
        //数据处理
        $id = $user_info['data']['agent_id'];
        //获取手机号码
        $phone_info['data']['phone_number'] = $user_info['phone_number'];
        $phone_info['data']['phone_idd']    = $user_info['phone_idd'];

        //数据处理
        foreach ($status_data['data'] as $value) {
            if ($value == 'second_email_status') {
                $validate_info['data']['email']         = "email";
                $validate_info['data']['email_address'] = $user_info['email'];
            }

            if ($value == 'second_phone_status') {
                $validate_info['data']['phone']        = "phone";
                $validate_info['data']['phone_number'] = $phone_info['data']['phone_number'];
                $validate_info['data']['phone_idd']    = $phone_info['data']['phone_idd'];
            }

            if ($value == 'second_google_auth_status') {
                $google_info                            = $this->getUserGoogleAuth($id);
                $validate_info['data']['google']        = "google";
                $validate_info['data']['google_secret'] = $google_info['data']['google_key'];
            }
        }

        if ($validate_first == 'second_email_status') {
            $first_data['email'] = substr($user_info['email'], '0', 3) .
                "*****" . strstr($user_info['email'], "@", false);
        } elseif ($validate_first == 'second_phone_status') {
            $first_data['phone'] = $phone_info['data']['phone_idd'] . substr($phone_info['data']['phone_number'], 0, 3) . "******" . substr($phone_info['data']['phone_number'], -2, 2);
            $phone_info['data']['phone_idd'];
        } else {
            $first_data['google'] = '******';
        }

        $id                           = $user_info['data']['agent_id'];
        $first_data['identification'] = "agent_" . $id;

        $validate_info['info']                    = $first_data;
        $validate_info['info']['validate_status'] = $validate_first;
        $validate_info['user_info']               = $user_info['data'];
        $validate_info['user_info']['email']      = $user_info['email'];
        $key                                      = env('PC_VALIDATE') . "agent_" . $id;
        /**  @noinspection PhpUndefinedMethodInspection */
        Redis::set($key, serialize($validate_info));
        return $validate_info;
    }

    /**
     * 获取GoogleAuth信息
     * @param $user_id
     * @return array
     */
    public function getUserGoogleAuth($user_id)
    {
        $url = "agent/agent_info_google_key/id/" . $user_id;
        return $this->send_request($url, 'get', "", $this->agentBaseUrl);
    }

    /**
     * 更新用户手机号
     * @param array $result
     * @param array $data
     * @return array
     */
    public function updatePhone($result, $data)
    {
        $agent_info                         = $this->getAgentInfo($result['agent_id']);
        $agent_info['data']['phone_number'] = $data['phone_number'];
        $agent_info['data']['phone_idd']    = $data['phone_idd'];
        $url                                = "agent/agent_info/id/" . $result['agent_id'] . "?cols=true";
        return $this->send_request($url, 'patch', $agent_info['data'], $this->agentBaseUrl);
    }

    /**
     * 判断是否绑定
     * @param $type
     * @param $status_info
     * @return bool
     */
    public function checkStatus($type, $status_info)
    {
        switch ($type) {
            case 'email':
                $field = 'has_email_status';
                break;
            case 'phone':
                $field = 'has_phone_status';
                break;
            case 'google':
                $field = 'has_google_auth_status';
                break;
            default:
                $field = 'has_email_status';
                break;
        }
        if ($status_info['data'][$field] != 1) {
            return false;
        }
        return true;
    }

    /**
     * 更新用户状态
     * @param $user_id
     * @param $field
     * @param $type
     * @return array
     */
    public function updateUserStatus($user_id, $field, $type)
    {
        $type['data'][$field] = $type['data'][$field] == 0 ? 1 : 0;
        $url                  = "agent/agent_status/id/" . $user_id . "?cols=true";
        return $this->send_request($url, 'patch', $type['data'], $this->agentBaseUrl);
    }

    /**
     * 修改pin
     * @param $user_info
     * @param $data
     * @return array
     */
    public function editPin($user_info, $data)
    {
        $agent_info                 = $this->getAgentInfo($user_info['agent_id']);
        $agent_info['data']['salt'] = $data['salt'];
        $agent_info['data']['pin']  = $data['pin'];
        $url                        = "agent/agent_info/id/" . $user_info['agent_id'] . "?cols=true";
        return $this->send_request($url, 'patch', $agent_info['data'], $this->agentBaseUrl);
    }

    /**
     * 更新用户密码
     * @param $id
     * @param $data
     * @return array
     */
    public function updateUserPassword($id, $data)
    {
        $url = "agent/agent/id/" . $id;
        return $this->send_request($url, "patch", $data, $this->agentBaseUrl);
    }

    /**
     * 创建google key
     * @param $data
     * @return array
     */
    public function createUserGoogleAuth($data)
    {
        //给用户添加google_key
        $url = "agent/agent_info_google_key";
        return $this->send_request($url, 'post', $data, $this->agentBaseUrl);
    }

    /**
     * 下一个需要二次验证
     * @param $user_data
     * @param $status
     * @return array
     */
    public function lastValidate($user_data, $status)
    {
        $key = env('PC_VALIDATE') . $user_data['identification'];
        /**  @noinspection PhpUndefinedMethodInspection */
        $data = unserialize(Redis::get($key));
        unset($data['info']);

        if ($status == "google") {
            return [ 'code' => 201, 'data' => $data['user_info'] ];
        }

        if (!empty($data['data']['phone']) && $status != 'phone') {
            unset($data['data']['email_address']);
            unset($data['data']['email']);
            //处理数据并储存
            $info['identification']  = $user_data['identification'];
            $info['validate_status'] = "second_phone_status";
            $info['phone']           = substr($data['data']['phone_number'], 0, 3) . "******" . substr($data['data']['phone_number'], -2, 2);
            /**  @noinspection PhpUndefinedMethodInspection */
            Redis::set($key, serialize($data));
            return [ 'code' => 200, 'data' => $info ];
        }

        if (!empty($data['data']['google']) && $status != 'google') {
            unset($data['data']['phone_number']);
            unset($data['data']['phone']);
            //处理数据并储存
            $info['identification']  = $user_data['identification'];
            $info['validate_status'] = "second_google_auth_status";
            /**  @noinspection PhpUndefinedMethodInspection */
            Redis::set($key, serialize($data));
            return [ 'code' => 200, 'data' => $info ];
        }

        return [ 'code' => 201, 'data' => $data['user_info'] ];
    }

    /**
     * 找回密码信息
     * @param $user_info
     * @return mixed
     */
    public function resetUserPass($user_info)
    {
        //获取手机信息
        $id          = $user_info['agent_id'];
        $status_info = $this->getUserStatus($id);

        //用户是否开启邮箱验证
        $user_data['email'] = substr($user_info['email'], '0', 3) . "*****" . strstr($user_info['email'], "@", false);

        //用户是否开启邮箱验证
        if ($status_info['data']['second_phone_status'] == 1) {
            $data                      = $this->getAgentInfo($id);
            $user_data['phone_number'] = isset($data['data']['phone_number']) ?
                substr($data['data']['phone_number'], 0, 3) .
                "******" . substr($data['data']['phone_number'], -2, 2) : "";
            $user_data['phone_idd']    = isset($data['data']['phone_idd']) ? $data['data']['phone_idd'] : "";
        }

        //用户是否开启邮箱验证
        if ($status_info['data']['second_google_auth_status'] == 1) {
            $google              = $this->getUserGoogleAuth($id);
            $user_data['google'] = empty($google['data']['google_key']) ? "" : "******";
        }
        $user_data['id'] = $user_info['agent_id'];

        return $user_data;
    }

    /**
     * 开启,禁用二次验证,验证用户信息
     * @param $info
     * @param $id
     * @return mixed
     */
    public function bindingInfo($info, $id)
    {
        //获取代理商绑定信息
        $agent_info = $this->getAgentInfo($id);
        $data       = [];
        if ($info['data']['second_phone_status'] == 1) {
            $data['phone']        = "phone";
            $data['phone_number'] = empty($agent_info['data']['phone_number']) ? "" :
                $agent_info['data']['phone_idd'] . " " . substr($agent_info['data']['phone_number'], 0, 3) . "******" .
                substr($agent_info['data']['phone_number'], -2, 2);
        }

        if ($info['data']['second_email_status'] == 1) {
            $data['email']      = "email";
            $data['email_info'] = empty($agent_info['data']['email']) ? "" :
                substr($agent_info['data']['email'], 0, 3) . "*****" .
                strstr($agent_info['data']['email'], "@", false);
        }

        if ($info['data']['second_google_auth_status'] == 1) {
            $data['google'] = "google";
        }

        return $data;
    }

    /**
     * 通过手机号获取用户信息
     * @param $idd
     * @param $phone
     * @return array
     */
    public function getAgentInfoByPhone($idd, $phone)
    {
        $url = "agent/agent_info/idd/" . $idd . "/phone/" . $phone;
        return $this->send_request($url, 'get', "", $this->agentBaseUrl);
    }
}
