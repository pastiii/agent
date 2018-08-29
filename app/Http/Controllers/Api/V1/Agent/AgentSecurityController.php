<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\AgentService;
use Illuminate\Http\Request;
use App\Services\SecurityVerificationService;
use App\Services\MessageTemplateService;
use Illuminate\Support\Facades\Redis;
use App\Support\SaltTrait;
use App\Support\SendTrait;
use App\Token\Apiauth;

/**
 * Created by PhpStorm.
 * User: zxq
 * Date: 2018/7/7
 * Time: 13:58
 */
class AgentSecurityController extends BaseController
{
    use SaltTrait, SendTrait;
    /** @var AgentService */
    protected $agentService;

    /**
     * AgentLoginController constructor.
     * @param AgentService $agentService
     */
    public function __construct(AgentService $agentService)
    {
        parent::__construct();
        $this->agentService = $agentService;
    }

    /**
     * 修改用户绑定的手机号码
     * @param Request $request
     * @return array
     */
    public function editPhone(Request $request)
    {
        $phone = $this->validate($request, [
            'phone_number'      => 'required|regex:/^1[34578]\d{9}$/',
            'phone_idd'         => 'required',
            'verification_code' => 'required',
            'verification_key'  => 'required',
        ]);

        //获取手机号码
        $phone_info = $this->agentService->getAgentInfo($this->user_id);
        if ($phone_info['code'] != 200) {
            $code = $this->code_num('GetUserFail');
            return $this->errors($code, __LINE__);
        }

        if (hash_equals($phone_info['data']['phone_number'], $phone['phone_number'])) {
            $code = $this->code_num('Identical');
            return $this->errors($code, __LINE__);
        }

        //验证手机验证码
        $redis_key = env('PC_PHONE') . $phone['phone_number'] . "_" . $phone['verification_key'];
        /**  @noinspection PhpUndefinedMethodInspection 验证邮箱验证码是否过期 */
        if (empty(redis::get($redis_key))) {
            $code = $this->code_num('VerifyInvalid');
            return $this->errors($code, __LINE__);
        }

        /**  @noinspection PhpUndefinedMethodInspection 验证手机验证码是否错误 */
        if (!hash_equals(redis::get($redis_key), $phone['verification_code'])) {
            $code = $this->code_num('VerificationCode');
            return $this->errors($code, __LINE__);
        }

        /**  @noinspection PhpUndefinedMethodInspection 清除redis 里面的数据 */
        redis::del($redis_key);
        //获取用户Id
        $result    = $this->get_user_info();
        $user_info = $this->agentService->updatePhone($result, $phone);
        //判断and返回信息
        if ($user_info['code'] == 200) {
            return $this->response("", 200);
        }

        $code = $this->code_num('UpdateFailure');
        return $this->errors($code, __LINE__);
    }

    /**
     * 检查二次验证状态
     * @return array|mixed|string
     */
    protected function checkTwoStatus()
    {
        $info = '';
        //开启,禁用二次验证判断
        $redis_key = env('PC_STATUS') . "agent_" . $this->user_id;
        /**  @noinspection PhpUndefinedMethodInspection */
        if (empty(Redis::get($redis_key))) {
            $user_status = $this->agentService->getUserStatus($this->user_id);
            if (!empty($user_status['data'])) {
                $info = $this->agentService->bindingInfo($user_status, $this->user_id);
            }
        }

        return $info;
    }

    /**
     * 检查二次验证状态
     * @return array|mixed|string
     */
    public function checkTwo()
    {
        //开启,禁用二次验证判断
        if (!empty($this->checkTwoStatus())) {
            $code = $this->code_num('TwoVerification');
            return $this->response($this->checkTwoStatus(), $code);
        }

        return $this->response("", 200);
    }

    /**
     * 开启,禁用二次验证
     * @param Request $request
     * @return array
     */
    public function patchStatus(Request $request)
    {
        $type = $this->validate($request, [
            'status' => 'required|in:email,google,phone',
        ]);

        switch ($type['status']) {
            case 'email':
                $field = 'second_email_status';
                break;
            case 'phone':
                $field = 'second_phone_status';
                break;
            case 'google':
                $field = 'second_google_auth_status';
                break;
            default:
                $field = 'second_email_status';
                break;
        }

        //开启,禁用二次验证判断
        if (!empty($this->checkTwoStatus())) {
            $code = $this->code_num('TwoVerification');
            return $this->response($this->checkTwoStatus(), $code);
        }

        //获取用户信息
        $status_info = $this->agentService->getUserStatus($this->user_id);

        if ($status_info['code'] != 200) {
            $code = $this->code_num('GetMsgFail');
            return $this->errors($code, __LINE__);
        }

        //验证用户是否有绑定
        $status = $this->agentService->checkStatus($type['status'], $status_info);
        if ($status == false) {
            $code = $this->code_num('Unbound');
            return $this->errors($code, __LINE__);
        }

        //修改验证状态
        $status_info = $this->agentService->updateUserStatus($this->user_id, $field, $status_info);

        //判断and返回结果
        if ($status_info['code'] == 200) {
            return $this->response("", 200);
        }

        $code = $this->code_num('UpdateFailure');
        return $this->errors($code, __LINE__);
    }

    /**
     * 获取用户基本信息
     * @param $user_data
     * @return array
     */
    public function userInfo($user_data)
    {
        //获取用户创建时间
        $user_info = $this->agentService->getUser($user_data['user_id']);
        if ($user_info['code'] != 200) {
            $code = $this->code_num('GetUserFail');
            return $this->errors($code, __LINE__);
        }
        //获取用户最后一次登录历史
        $page       = 1;
        $pageSize   = 1;
        $last_login = $this->agentService->getUserLoginHistoryList($user_data['user_id'], $pageSize, $page);

        //获取用户信息
        $agent_info = $this->agentService->getAgentInfo($user_data['user_id']);
        //数据处理
        $data['email']           = empty($agent_info['data']['email']) ? "" : substr($agent_info['data']['email'], '0', 3)."*****".strstr($agent_info['data']['email'], "@",false);
        $data['phone']           = empty($agent_info['data']['phone_number']) ? "" : substr($agent_info['data']['phone_number'] , 0 , 3)."******".substr($agent_info['data']['phone_number'], -2,2) ;
        $data['name']            = $user_data['user_name'];
        $data['create_time']     = date('Y-m-d H:i:s', $user_info['data']['created_at']);
        $data['last_login_time'] = empty($last_login['data']['list']) ? "" :
            date('Y-m-d H:i:s', $last_login['data']['list'][0]['created_at']);

        return $data;
    }

    /**
     * 获取用户绑定验证信息
     * @return array
     */
    public function getUserStatusById()
    {
        $user_data = $this->get_user_info();
        //获取用户密码
        $user_info = $this->agentService->getUser($user_data['user_id']);
        if ($user_info['code'] != 200) {
            $code = $this->code_num('GetUserFail');
            return $this->errors($code, __LINE__);
        }
        //获取用户绑定信息
        $user_status = $this->agentService->getUserStatus($user_data['user_id']);
        if ($user_status['code'] != 200) {
            $code = $this->code_num('GetMsgFail');
            return $this->errors($code, __LINE__);
        }

        //判断用户状态
        $email_status  = $user_status['data']['has_email_status'] == 0 ? 0 : 1;
        $phone_status  = $user_status['data']['has_phone_status'] == 0 ? 0 : 1;
        $google_status = $user_status['data']['has_google_auth_status'] == 0 ? 0 : 1;

        if ($email_status == 1) {
            $email_status = $email_status + $user_status['data']['second_email_status'];
        }
        if ($phone_status == 1) {
            $phone_status = $phone_status + $user_status['data']['second_phone_status'];
        }
        if ($google_status == 1) {
            $google_status = $google_status + $user_status['data']['second_google_auth_status'];
        }

        $data = $this->userInfo($user_data);

        //获取pin
        $pin_info = $this->agentService->getAgentInfo($user_data['agent_id']);

        if ($pin_info['code'] != 200) {
            $code = $this->code_num('GetMsgFail');
            return $this->errors($code, __LINE__);
        }
        //数据处理
        $data['email_status']  = $email_status;
        $data['phone_status']  = $phone_status;
        $data['google_status'] = $google_status;
        $data['password']      = "******";
        $pin                   = !empty($pin_info['data']['pin']) ? "******" : "";
        $data['pin']           = $pin;
        return $this->response($data, 200);
    }

    /**
     * 获取用户电话信息(发送短息的手机号码)
     * @return array
     */
    public function phoneInfo()
    {
        //获取登录用户类型
        $user_info  = $this->get_user_info();
        $phone_info = $this->agentService->getAgentInfo($user_info['user_id']);

        //数据处理
        $data['phone_number'] = substr($phone_info['data']['phone_number'], 0, 3) . "******" .
            substr($phone_info['data']['phone_number'], -2, 2);
        $data['phone_idd']    = $phone_info['data']['phone_idd'];
        $key                  = "phone_" . $this->user_id;
        /**  @noinspection PhpUndefinedMethodInspection */
        Redis::set($key, serialize($phone_info['data']));
        return $this->response($data, 200);
    }

    /**
     * 获取用户的email address (发送email)
     * @return array
     */
    public function emailInfo()
    {
        //获取用户信息
        $user_info = $this->get_user_info();
        //数据处理
        $data['email'] = substr($user_info['email'], '0', 3) . "*****" . strstr($user_info['email'], "@", false);
        return $this->response($data, 200);
    }

    /**
     * 验证邮箱验证码(update pin)
     * @param Request $request
     * @return array
     */
    public function validateEmailCode(Request $request)
    {
        $data = $this->validate($request, [
            'email_code' => 'required',
            'email_key'  => 'required',
        ]);

        //获取邮箱地址
        $user_info = $this->get_user_info();
        $redis_key = env('PC_EMAIL') . $user_info['email'] . "_" . $data['email_key'];

        /**  @noinspection PhpUndefinedMethodInspection  验证邮箱验证码是否过期 */
        if (empty(redis::get($redis_key))) {
            $code = $this->code_num('VerifyInvalid');
            return $this->errors($code, __LINE__);
        }

        /**  @noinspection PhpUndefinedMethodInspection 验证邮箱验证码是否错误 */
        if (!hash_equals(redis::get($redis_key), $data['email_code'])) {
            $code = $this->code_num('VerificationCode');
            return $this->errors($code, __LINE__);
        }

        /**  @noinspection PhpUndefinedMethodInspection  清除redis 里面的数据 */
        redis::del($redis_key);

        if (isset($request->type)) {
            $redis_key = env('PC_STATUS') . "agent_" . $this->user_id;
            /**  @noinspection PhpUndefinedMethodInspection */
            redis::setex($redis_key, 600, "check");
            return $this->response([ 'status' => $request->type ], 200);
        }
        return $this->response("", 200);
    }

    /**
     * 验证旧手机号
     * @param Request $request
     * @return array
     */
    public function getPhoneNumber(Request $request)
    {
        //查看是否已经绑定手机(暂时使用虚拟数据)
        $phone_data = $this->validate($request, [
            'phone_number' => 'required|regex:/^1[34578]\d{9}$/',
            'phone_idd'    => 'required|string'
        ]);
        $phone_info = $this->agentService->getAgentInfo($this->user_id);

        if ($phone_info['code'] != 200) {
            $code = $this->code_num('GetMsgFail');
            return $this->errors($code, __LINE__);
        }

        if (empty($phone_info['data'])) {
            $code = $this->code_num('PhoneNull');
            return $this->errors($code, __LINE__);
        }

        if ($phone_data['phone_number'] == $phone_info['data']['phone_number'] &&
            $phone_data['phone_idd'] == $phone_info['data']['phone_idd']
        ) {
            return $this->response("", 200);
        }

        $code = $this->code_num("PhoneFail");
        return $this->errors($code, __LINE__);
    }

    /**
     * 重置支付密码
     * @param Request $request
     * @return array
     */
    public function updatePin(Request $request)
    {
        $data = $this->validate($request, [
            'pin'              => 'required|string|confirmed|regex:/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{5,15}$/',
            'pin_confirmation' => 'required|string|regex:/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{5,15}$/'
        ]);

        //数据
        unset($data['pin_confirmation']);
        //密码盐

        $data['salt'] = $this->salt();
        $data['pin']  = $this->getPassword($data['pin'], $data['salt']);

        //获取用户信息
        $user_info = $this->get_user_info();
        //执行密码重置
        $pin_info = $this->agentService->editPin($user_info, $data);
        //结果
        if ($pin_info['code'] == 200) {
            return $this->response("", 200);
        }

        $code = $this->code_num('UpdateFailure');
        return $this->errors($code, __LINE__);
    }

    /**
     * 修改密码
     * @param Request $request
     * @return array
     */
    public function updateLoginPassword(Request $request)
    {
        //开启,禁用二次验证判断
        if (!empty($this->checkTwoStatus())) {
            $code = $this->code_num('TwoVerification');
            return $this->response($this->checkTwoStatus(), $code);
        }
        $data = $this->validate($request, [
            'old_password'              => [
                'required', 'string',
                'regex:/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{5,15}$/'
            ],
            'new_password'              => [
                'required', 'string',
                'regex:/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{5,15}$/', 'confirmed'
            ],
            'new_password_confirmation' => [
                'required', 'string', 'regex:/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{5,15}$/'
            ]
        ]);
        //验证原始密码
        $user = $this->agentService->getUser($this->user_id);

        $checkPassword = $this->checkPassword(
            $data['old_password'],
            $user['data']['password'],
            $user['data']['salt']
        );
        if (!$checkPassword) {
            $code = $this->code_num('PasswordError');
            return $this->errors($code, __LINE__);
        }

        //获取修改数据
        $data['salt']     = $user['data']['salt'];
        $data['password'] = $this->getPassword($data['new_password'], $data['salt']);
        unset($data['new_password_confirmation']);
        unset($data['old_password']);

        //修改密码
        $user_info = $this->agentService->updateUserPassword($this->user_id, $data);

        //返回结果
        if ($user_info['code'] == 200) {
            return $this->response("", 200);
        }

        $code = $this->code_num('UpdateFailure');
        return $this->errors($code, __LINE__);
    }

    /**
     * 获取googleAuthenticator 信息
     * @return array
     */
    public function getGoogleCode()
    {
        //开启,禁用二次验证判断
        if (!empty($this->checkTwoStatus())) {
            $code = $this->code_num('TwoVerification');
            return $this->response($this->checkTwoStatus(), $code);
        }

        /* 获取google授权信息 */
        //获取用户信息
        $user_data = $this->get_user_info();
        /* 获取google授权信息 */
        // $googleAuthenticator = $this->agentService->getUserGoogleAuth($user_data['user_id']);

        /* @var SecurityVerificationService $securityVerification 验证服务接口 */
        $securityVerification = app(SecurityVerificationService::class);
        /* 先获取sessionId 通讯协议*/
        $result = $securityVerification->getSessionId();
        /* 错误状态码 */
        $code = $this->code_num('authorization');

        if ($result['code'] != 200) {
            $this->errors($code, __LINE__);
        }
        $sessionId = $result['data']['data']['sessionId'];
        /* 不存在google授权 */
        $result = $securityVerification->getGoogleSecret($sessionId, $user_data['user_name']);
        if ($result['code'] != 200) {
            $this->errors($code, __LINE__);
        }

        //拼接二维码地址
        $result['data']['data']['QrCode'] = "data:image/png;base64," . $result['data']['data']['QrCode'];
        return $this->response($result['data']['data'], 200);
    }


    /**
     * 绑定google 信息
     * @param $user_data
     * @param $secret
     * @return bool
     */
    protected function bindingGoogleKey($user_data, $secret)
    {
        $google_data['agent_id']   = $this->user_id;
        $google_data['agent_name'] = $user_data['user_name'];
        $google_data['google_key'] = $secret;
        /* 创建用户google_key */
        $response = $this->agentService->createUserGoogleAuth($google_data);
        /* 授权失败 */
        if ($response['code'] != 200) {
            return false;
        }
        return true;
    }

    /**
     * 验证google验证码
     * @param Request $request
     * @return array
     */
    public function checkGoogleCode(Request $request)
    {
        /* 验证验证码 */
        $data = $this->validate($request, [
            'verify' => 'required|string',
            'secret' => 'nullable',
        ]);
        /*  获取登陆用户的googleKey  */
        $user_info = $this->get_user_info();
        /*  获取登陆用户的googleKey  */
        $googleAuthenticator = $this->agentService->getUserGoogleAuth($user_info['user_id']);

        /* 不否存在secret */
        if (!isset($data['secret'])) {
            /* 判断用户是否绑定 */
            if ($googleAuthenticator['code'] != 200) {
                $code = $this->code_num('Unbound');
                return $this->errors($code, __LINE__);
            }
            /* 重新赋值 */
            $data['secret'] = $googleAuthenticator['data']['google_key'];
        }
        /* @var SecurityVerificationService $securityVerification */
        $securityVerification = app(SecurityVerificationService::class);
        /* 验证googleVerify */
        $result = $securityVerification->checkGoogleVerify($data);
        /* 数据返回 */
        if ($result['data']['code'] != 200) {
            $code = $this->code_num('VerificationCode');
            return $this->errors($code, __LINE__);
        }
        /* 验证成功没有绑定google key 则绑定 */
        if ($googleAuthenticator['real_code'] != 200) {
            $response = $this->bindingGoogleKey($user_info, $data['secret']);
            /* 判断是否绑定成功 */
            if (!$response) {
                $code = $this->code_num('BindingFail');
                return $this->errors($code, __LINE__);
            }
        }

        if (!empty($googleAuthenticator['data']) && !empty($data['secret'])) {
            //绑定过就修改
            $response = $this->editGoogleKey($data['secret']);
            /* 判断是否绑定成功 */
            if (!$response) {
                $code = $this->code_num('UpdateFailure');
                return $this->errors($code, __LINE__);
            }
        }

        if (isset($request->type)) {
            $redis_key = env('PC_STATUS') . "agent_" . $this->user_id;
            /** @noinspection PhpUndefinedMethodInspection */
            redis::setex($redis_key, 600, "check");
            return $this->response([ 'status' => $request->type ], 200);
        }

        return $this->response('', '200');
    }

    /**
     * 修改google key
     * @param $secret
     * @return bool
     */
    private function editGoogleKey($secret)
    {
        $response = $this->agentService->editUserGoogleAuth($secret, $this->user_id);
        if ($response['code'] != 200) {
            return false;
        }
        return true;
    }

    /**
     * 开启,禁用二次验证手机code
     * @return array
     */
    public function sms()
    {
        //获取用户手机号码
        $phone_info = $this->agentService->getAgentInfo($this->user_id);

        //获取信息失败
        if ($phone_info['code'] != 200) {
            $code = $this->code_num('GetMsgFail');
            return $this->errors($code, __LINE__);
        }
        //手机号不存在
        if (empty($phone_info['data'])) {
            $code = $this->code_num('PhoneNull');
            return $this->errors($code, __LINE__);
        }
        $phone['phone_number'] = $phone_info['data']['phone_number'];
        $phone['phone_idd']    = $phone_info['data']['phone_idd'];

        /* @var MessageTemplateService $MessageTemplateService 验证服务接口 */
        $MessageTemplateService = app(MessageTemplateService::class);
        $type                   = $MessageTemplateService->phoneCodeCopyWriting($phone['phone_idd']);

        /* @var SecurityVerificationService $securityVerification 验证服务接口 */
        $securityVerification = app(SecurityVerificationService::class);

        $smsMessage = $securityVerification->sendSms($phone, $type);

        $result = $this->storageCode($smsMessage, $type, $phone);
        if ($result['code'] == 200) {
            return $this->response([ 'verification_key' => $result['verification_key'] ], 200);
        }

        $code = $this->code_num('SendFail');
        return $this->errors($code, __LINE__);
    }

    /**
     * 验证手机验证码(开启,禁用二次次验证)
     * @param Request $request
     * @return array
     */
    public function validatePhoneCode(Request $request)
    {
        $data = $this->validate($request, [
            'verification_code' => 'required',
            'verification_key'  => 'required',
        ]);

        //获取用户手机
        $phone_info = $this->agentService->getAgentInfo($this->user_id);
        if ($phone_info['code'] != 200) {
            $code = $this->code_num('GetUserFail');
            return $this->errors($code, __LINE__);
        }

        $redis_key = env('PC_PHONE') . $phone_info['data']['phone_idd'] .
            $phone_info['data']['phone_number'] . "_" . $data['verification_key'];
        /** @noinspection PhpUndefinedMethodInspection 验证手机验证码是否过期 */
        if (empty(redis::get($redis_key))) {
            $code = $this->code_num('VerifyInvalid');
            return $this->errors($code, __LINE__);
        }

        /**@noinspection PhpUndefinedMethodInspection  验证邮箱验证码是否错误 */
        if (!hash_equals(redis::get($redis_key), $data['verification_code'])) {
            $code = $this->code_num('VerificationCode');
            return $this->errors($code, __LINE__);
        }

        /** @noinspection PhpUndefinedMethodInspection 清除redis 里面的数据 */
        redis::del($redis_key);

        if (isset($request->type)) {
            $redis_key = env('PC_STATUS') . "agent_" . $this->user_id;
            /** @noinspection PhpUndefinedMethodInspection */
            redis::setex($redis_key, 600, "check");
            return $this->response([ 'status' => $request->type ], 200);
        }

        return $this->response("", 200);
    }

    /**
     * 邮箱发送验证码
     * @return array
     */
    public function sendEmail()
    {
        //获取邮箱address
        $user_info = $this->get_user_info();
        //发送邮件
        /* @var MessageTemplateService $MessageTemplateService 验证服务接口 */
        $MessageTemplateService = app(MessageTemplateService::class);
        $data = $MessageTemplateService->emailCopyWriting();

        /* @var SecurityVerificationService $securityVerification 验证服务接口 */
        $securityVerification = app(SecurityVerificationService::class);
        $emailMessage = $securityVerification->sendEmail($user_info['email'], $data);
        $email_data = $this->storageEmail($emailMessage, $user_info['email'], $data);


        if ($email_data['code'] == 200) {
            return $this->response([ 'email_key' => $email_data['email_key'] ], 200);
        }

        $code = $this->code_num('SendFail');
        return $this->errors($code, __LINE__);
    }

    /**
     * 退出登录
     */
    public function loginOut()
    {
        $redis_key = env('PC_STATUS') . "agent_" . $this->user_id;
        /** @noinspection PhpUndefinedMethodInspection */
        if (!empty(redis::get($redis_key))) {
            /** @noinspection PhpUndefinedMethodInspection */
            redis::del($redis_key);
        }
        /**  @noinspection PhpUndefinedMethodInspection 退出登录清除redis token */
        ApiAuth::deleted_token();
        return $this->response('', 200);
    }
}
