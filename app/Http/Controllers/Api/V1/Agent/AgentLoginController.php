<?php

namespace App\Http\Controllers\Api\V1\Agent;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\BaseController;
use App\Services\AgentService;
use Illuminate\Support\Facades\Redis;
use App\Support\AgentStatusTrait;
use App\Support\SaltTrait;
use App\Http\Requests\Api\CaptchaRequest;
use App\Services\SecurityVerificationService;
use App\Services\MessageTemplateService;

/**
 * Created by PhpStorm.
 * User: zxq
 * Date: 2018/7/7
 * Time: 11:36
 */
class AgentLoginController extends BaseController
{
    use AgentStatusTrait, SaltTrait;
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
     * agent login
     * @param Request $request
     * @return array
     */
    public function agentLogin(Request $request)
    {
        $data = $this->validate($request, [
            'idd'          => 'nullable',
            'phone'        => 'nullable|regex:/^[0-9]{2,20}$/',
            'email'        => 'nullable|E-mail',
            'password'     => 'required|string|regex:/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{5,15}$/',
            'captcha_code' => 'required',
            'captcha_key'  => 'required'
        ]);

        //验证验证码
        $info['token'] = $data['captcha_key'];
        $info['code']  = $data['captcha_code'];
        /* @var SecurityVerificationService $securityVerificationService */
        $securityVerificationService = app(SecurityVerificationService::class);
        $info                        = $securityVerificationService->checkCaptcha($info);
        if ($info['code'] == 200 && $info['data']['code'] != 200) {
            $code = $this->code_num('VerificationCode');
            return $this->errors($code, __LINE__);
        }

        /**
         * 用户登陆   获取代理商信息  验证密码
         * 1 通过邮箱登陆或密码  获取代理商ID
         * 2 获取代理商信息
         * 3 验证登陆密码
         */
        $getInfo = $this->loginChildGetInfo($data);

        if ($getInfo['status_code'] != 200) {
            return $this->errors($getInfo['status_code'], $getInfo['line']);
        }
        $agentInfo = $getInfo['data'];

        //获取二次验证状态
        $status_data = $this->GetStatus($agentInfo['agent_id']);
        if ($status_data['code'] != 200) {
            $code = $this->code_num('GetUserFail');
            return $this->errors($code, __LINE__);
        }

        //核对上次登录信息
        $ip_status  = $this->agentService->validateIp($agentInfo['agent_id']);
        $use_second = $status_data['data'] && $ip_status == true ? 1 : 0; //开启了验证 && 免验证


        //判断是否需要二次验证
        if (empty($status_data['data']) || $use_second) {
            /**
             * 不需要二次验证执行登陆操作
             * 1  生成token
             * 2  代理商异地登录短信提醒
             * 3  保存登录历史
             * 4  代理商异地登录短信提醒
             * 5  返回登陆信息
             */
            return $this->loginValidate($agentInfo, $use_second);
        }

        // 返回二次验证信息
        $validate_first = $status_data['data'][0];
        //数据处理
        $user_info     = [
            "data"         => $agentInfo,
            "email"        => $agentInfo['email'],
            "phone_idd"    => isset($agentInfo['phone_idd']) ? $agentInfo['phone_idd'] : "",
            "phone_number" => isset($agentInfo['phone_number']) ? $agentInfo['phone_number'] : "",
        ];
        $validate_data = $this->agentService->createInfo($status_data, $user_info, $validate_first);
        //验证状态
        $code = $this->code_num('TwoVerification');
        return $this->response($validate_data['info'], $code);
    }

    /**
     * 获取代理商信息  验证密码
     * @param $data
     * @return array
     */
    protected function loginChildGetInfo($data)
    {
        $code = $this->code_num('GetUserFail');
        // 获取代理商ID
        if (isset($data['phone']) && !empty($data['phone'])) {
            // 通过手机号获取代理商信息 并验证
            $agent = $this->agentService->getAgentInfoByPhone($data['idd'], $data['phone']);
        } elseif (isset($data['email']) && !empty($data['email'])) {
            //通过邮箱获取代理商信息
            $agent = $this->agentService->getUserEmailByMail($data['email']);
        } else {
            $code = $this->code_num('ParamError');
            return $this->errors($code, __LINE__);
        }

        // 获取代理商ID验证
        if ($agent['real_code'] != 200) {
            return $this->errors($code, __LINE__);
        }

        // 获取代理商信息
        $user_id = $agent['data']['agent_id'];

        //通过代理商id获取代理商信息
        $pwd_info  = $this->agentService->getUser($user_id);
        $user_info = $this->agentService->getAgentInfo($user_id);

        if ($user_info['real_code'] != 200 || $pwd_info['real_code'] != 200) {
            return $this->errors($code, __LINE__);
        }
        $agentInfo = array_merge($agent['data'], $user_info['data'], $pwd_info['data']);


        //判断密码是否正确
        $checkPassword = $this->checkPassword(
            $data['password'],
            $pwd_info['data']['password'],
            $pwd_info['data']['salt']
        );
        if (!$checkPassword) {
            $code = $this->code_num('PasswordError');
            return $this->errors($code, __LINE__);
        }
        unset($agentInfo['pin']);
        unset($agentInfo['password']);

        return $this->response($agentInfo, 200);
    }

    /**
     * 不需要二次验证执行登陆操作
     * @param $agentInfo
     * @param $use_second
     * @return array
     */
    protected function loginValidate($agentInfo, $use_second)
    {
        //判断登录角色并生成token
        $agentInfo['user_id']   = $agentInfo['agent_id'];
        $agentInfo['user_name'] = $agentInfo['agent_name'];


        $token = $this->getToken($agentInfo);

        //存入登录历史
        //代理商异地登录短信提醒
        $abnormal = $this->agentService->checkIp($agentInfo['agent_id']);

        //保存登录历史
        $this->agentService->createLoginHistory($agentInfo, $token, $use_second);

        if (!$token) {
            $code = $this->code_num('LoginFailure');
            return $this->errors($code, __LINE__);
        }

        if ($abnormal != false) {
            /* @var MessageTemplateService $MessageTemplateService 验证服务接口 */
            $MessageTemplateService = app(MessageTemplateService::class);
            $type                   = $MessageTemplateService->phoneLoginCopyWriting(
                $abnormal['phone_idd'],
                $abnormal['agent_name']
            );
            /* @var SecurityVerificationService $securityVerification 验证服务接口 */
            $securityVerification = app(SecurityVerificationService::class);
            $securityVerification->sendSms($abnormal, $type);
        }

        $user_data['token'] = $token;
        $user_data['name']  = $agentInfo['agent_name'];
        return $this->response($user_data, 200);
    }


    /**
     * 验证用户邮箱是否存在 (找回密码)
     * @param CaptchaRequest $request
     * @return array
     */
    public function retrievePassword(CaptchaRequest $request)
    {
        $data = $this->validate($request, [
            'email' => 'required|string|email',
        ]);

        /* @var SecurityVerificationService $securityVerification 验证服务接口 */
        $securityVerification = app(SecurityVerificationService::class);

        /**  @noinspection PhpUndefinedFieldInspection 图片验证码验证 */
        $info['token'] = $request->captcha_key;
        /** @noinspection PhpUndefinedFieldInspection */
        $info['code'] = $request->captcha_code;
        $info         = $securityVerification->checkCaptcha($info);
        if ($info['code'] == 200 && $info['data']['code'] != 200) {
            $code = $this->code_num('VerificationCode');
            return $this->errors($code, __LINE__);
        }

        //通过邮箱获取用户信息
        $email_info = $this->agentService->getUserEmailByMail($data['email']);
        if ($email_info['code'] == 200 && $email_info['real_code'] != 200) {
            $code = $this->code_num('Empty');
            return $this->errors($code, __LINE__);
        }

        //验证邮箱是否存在
        if ($email_info['real_code'] != 200) {
            $code = $this->code_num('GetUserFail');
            return $this->errors($code, __LINE__);
        }

        //数据处理
        $data = $this->agentService->resetUserPass($email_info['data']);
        return $this->response($data, 200);
    }


    /**
     * 用户update password
     * @param Request $request
     * @return array
     */
    public function resetAgent(Request $request)
    {
        //验证数据
        $data = $this->validate($request, [
            'id'                    => 'required|int',
            'password'              => 'required|string|regex:/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{5,15}$/|confirmed',
            'password_confirmation' => 'required|string|regex:/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{5,15}$/',
        ]);

        $forget_password_key = env('PC_VALIDATE') . 'forget_password_' . $data['id'];

        /** @noinspection PhpUndefinedMethodInspection 验证邮箱验证码是否过期 */
        if (empty(redis::get($forget_password_key))) {
            $code = $this->code_num('ParamError');
            return $this->errors($code, __LINE__);
        }

        /** @noinspection PhpUndefinedFieldInspection */
        $user = $this->agentService->getUser($data['id']);

        if ($user['real_code'] != 200) {
            $code = $this->code_num('GetUserFail');
            return $this->errors($code, __LINE__);
        }

        //密码盐
        $data['salt']     = $user['data']['salt'];
        $data['password'] = $this->getPassword($data['password'], $data['salt']);
        // $data['salt'] = $this->salt();
        unset($data['password_confirmation']);
        /** @noinspection PhpUndefinedMethodInspection  */
        redis::del($forget_password_key);
        /** @noinspection PhpUndefinedFieldInspection  修改密码 */
        $user_info = $this->agentService->updateUserPassword($request->id, $data);

        if ($user_info['code'] == 200) {
            return $this->response("", 200);
        }

        $code = $this->code_num('UpdateFailure');
        return $this->errors($code, __LINE__);
    }

    /**
     * @param Request $request
     */
    public function testLog(Request $request)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $token   = $request->token;
        $key     = '#$*&@#$%^JjUgIsGf';
        $token_1 = md5(md5($key));
        if ($token_1 != $token) {
            die('非法访问');
        }
        $url  = storage_path('logs/api/' . date('Y-m-d', time()));
        $file = file($url . '.log');
        dd($file);
    }

    /**
     * 验证邮箱验证码(找回密码)
     * @param Request $request
     * @return array
     */
    public function validateEmailCode(Request $request)
    {
        $data = $this->validate($request, [
            'id'         => 'required',
            'email_code' => 'required',
            'email_key'  => 'required',
        ]);

        //获取用户邮箱
        $email_info = $this->agentService->getAgentInfo($data['id']);
        //账户不存在
        if (empty($email_info['data'])) {
            $code = $this->code_num('Empty');
            return $this->errors($code, __LINE__);
        }

        if ($email_info['code'] != 200) {
            $code = $this->code_num('GetUserFail');
            return $this->errors($code, __LINE__);
        }

        $redis_key = env('PC_EMAIL') . $email_info['data']['email'] . "_" . $data['email_key'];
        /** @noinspection PhpUndefinedMethodInspection 验证邮箱验证码是否过期 */
        if (empty(redis::get($redis_key))) {
            $code = $this->code_num('VerifyInvalid');
            return $this->errors($code, __LINE__);
        }

        /** @noinspection PhpUndefinedMethodInspection 验证邮箱验证码是否错误 */
        if (!hash_equals(redis::get($redis_key), $data['email_code'])) {
            $code = $this->code_num('VerificationCode');
            return $this->errors($code, __LINE__);
        }

        /** @noinspection PhpUndefinedMethodInspection 清除redis 里面的数据 */
        redis::del($redis_key);
        $forget_password_key = env('PC_VALIDATE') . 'forget_password_' . $email_info['data']['agent_id'];
        /** @noinspection PhpUndefinedMethodInspection */
        Redis::setex($forget_password_key, 600, 1);  //埋点
        return $this->response([ "email" => $email_info['data']['email'] ], 200);
    }

    /**
     * 验证手机验证码(找回密码)
     * @param Request $request
     * @return array
     */
    public function validatePhoneCode(Request $request)
    {
        $data = $this->validate($request, [
            'id'                => 'required',
            'verification_code' => 'required',
            'verification_key'  => 'required',
        ]);

        //获取用户邮箱
        $phone_info = $this->agentService->getAgentInfo($data['id']);
        if ($phone_info['code'] != 200) {
            $code = $this->code_num('GetUserFail');
            return $this->errors($code, __LINE__);
        }
        $redis_key = env('PC_PHONE') . $phone_info['data']['phone_idd'].$phone_info['data']['phone_number'] . "_" . $data['verification_key'];
        /** @noinspection PhpUndefinedMethodInspection 验证邮箱验证码是否过期 */
        if (empty(redis::get($redis_key))) {
            $code = $this->code_num('VerifyInvalid');
            return $this->errors($code, __LINE__);
        }

        /** @noinspection PhpUndefinedMethodInspection 验证邮箱验证码是否错误 */
        if (!hash_equals(redis::get($redis_key), $data['verification_code'])) {
            $code = $this->code_num('VerificationCode');
            return $this->errors($code, __LINE__);
        }

        /** @noinspection PhpUndefinedMethodInspection 清除redis 里面的数据 */
        redis::del($redis_key);
        $forget_password_key = env('PC_VALIDATE') . 'forget_password_' . $phone_info['data']['agent_id'];
        /** @noinspection PhpUndefinedMethodInspection */
        Redis::setex($forget_password_key, 600, 1);  //埋点
        return $this->response("", 200);
    }
}
