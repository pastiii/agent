<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Services\AgentService;
use App\Services\SecurityVerificationService;
use App\Services\MessageTemplateService;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\BaseController;
use Illuminate\Support\Facades\Redis;
use App\Support\SendTrait;

/**
 * Created by PhpStorm.
 * User: admin2
 * Date: 2018/7/7
 * Time: 16:28
 */
class AgentCheckController extends BaseController
{
    use SendTrait;
    /** @var AgentService */
    protected $agentService;
    /* @var  SecurityVerificationService */
    protected $securityVerificationService;

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
     * @return SecurityVerificationService|\Illuminate\Foundation\Application|mixed
     */
    protected function getSecurityVerificationService()
    {
        if (!isset($this->securityVerificationService)) {
            $this->securityVerificationService = app(SecurityVerificationService::class);
        }
        return $this->securityVerificationService;
    }

    /**
     * 发送手机验证码
     * @param Request $request
     * @return array
     */
    public function sendSms(Request $request)
    {
        if ($request->isMethod('get')) {
            /** @noinspection  PhpUndefinedFieldInspection 获取用户邮箱 */
            $phone_info = $this->agentService->getAgentInfo($request->id);

            if ($phone_info['code'] != 200) {
                $code = $this->code_num('GetMsgFail');
                return $this->errors($code, __LINE__);
            }

            if ($phone_info['real_code'] != 200) {
                $code = $this->code_num('Unbound');
                return $this->errors($code, __LINE__);
            }
        } else {
            $user_data = $this->validate($request, [
                'identification' => 'required|string'
            ]);

            //获取需要验证的手机号码
            $key = env('PC_VALIDATE') . $user_data['identification'];
            /** @noinspection PhpUndefinedMethodInspection */
            $phone_info = unserialize(Redis::get($key));

            if (empty($phone_info)) {
                $code = $this->code_num('GetMsgFail');
                return $this->errors($code, __LINE__);
            }
        }

        $phone['phone_number'] = $phone_info['data']['phone_number'];
        $phone['phone_idd']    = $phone_info['data']['phone_idd'];

        /* @var MessageTemplateService $messageTemplateService 验证服务接口 */
        $messageTemplateService = app(MessageTemplateService::class);
        $type                   = $messageTemplateService->phoneCodeCopyWriting($phone['phone_idd']);

        $this->getSecurityVerificationService();
        $smsMessage = $this->securityVerificationService->sendSms($phone, $type);
        $result = $this->storageCode($smsMessage, $type, $phone);
        if ($result['code'] == 200) {
            return $this->response([ 'verification_key' => $result['verification_key'] ], 200);
        }

        $code = $this->code_num('SendFail');
        return $this->errors($code, __LINE__);
    }

    /**
     * 手机验证码二次验证
     * @param Request $request
     * @return array
     */
    public function validatePhoneCode(Request $request)
    {
        $user_data = $this->validate($request, [
            'identification'    => 'required|string',
            'verification_code' => 'required',
            'verification_key'  => 'required',
        ]);

        //获取需要验证的信息
        $key = env('PC_VALIDATE') . $user_data['identification'];
        /** @noinspection PhpUndefinedMethodInspection */
        $data  = unserialize(Redis::get($key));
        $phone = $data['data']['phone_idd'] . $data['data']['phone_number'];

        //验证手机验证码
        $redis_key = env('PC_PHONE') . $phone . "_" . $user_data['verification_key'];

        /** @noinspection PhpUndefinedMethodInspection 验证邮箱验证码是否过期 */
        if (empty(redis::get($redis_key))) {
            $code = $this->code_num('VerifyInvalid');
            return $this->errors($code, __LINE__);
        }

        /** @noinspection PhpUndefinedMethodInspection 验证手机验证码是否错误 */
        if (!hash_equals(redis::get($redis_key), $user_data['verification_code'])) {
            $code = $this->code_num('VerificationCode');
            return $this->errors($code, __LINE__);
        }

        /** @noinspection PhpUndefinedMethodInspection 清除redis 里面的数据 */
        redis::del($redis_key);

        //获取数据
        $status_info = $this->agentService->lastValidate($user_data, $status = "phone");

        if ($status_info['code'] == 200) {
            return $this->response($status_info['data'], 200);
        }
        //判断登录角色并生成token
        $info = $this->createToken($status_info['data'], $user_data['identification']);
        return $this->response($info, 200);
    }

    /**
     * 发送邮箱验证码
     * @param Request $request
     * @return array
     */
    public function sendEmail(Request $request)
    {
        if ($request->isMethod('get')) {
            /** @noinspection PhpUndefinedFieldInspection 获取邮箱信息 */
            $email_info = $this->agentService->getAgentInfo($request->id);

            if ($email_info['code'] != 200) {
                $code = $this->code_num('GetMsgFail');
                return $this->errors($code, __LINE__);
            }

            if ($email_info['real_code'] != 200) {
                $code = $this->code_num('Unbound');
                return $this->errors($code, __LINE__);
            }
            $email = $email_info['data']['email'];
        } else {
            $user_data = $this->validate($request, [
                'identification' => 'required|string'
            ]);
            //获取需要验证的email
            $key = env('PC_VALIDATE') . $user_data['identification'];
            /** @noinspection PhpUndefinedMethodInspection */
            $data = unserialize(Redis::get($key));

            if (empty($data)) {
                $code = $this->code_num('GetMsgFail');
                return $this->errors($code, __LINE__);
            }
            $email = $data['data']['email_address'];
        }
        //发送邮件
        /* @var MessageTemplateService $messageTemplateService 验证服务接口 */
        $messageTemplateService = app(MessageTemplateService::class);
        $email_data  = $messageTemplateService->emailCopyWriting();

        $this->getSecurityVerificationService();
        $emailMessage = $this->securityVerificationService->sendEmail($email, $email_data);
        $res = $this->storageEmail($emailMessage, $email, $email_data);

        if ($res['code'] == 200) {
            return $this->response([ 'email_key' => $res['email_key'] ], 200);
        }

        $code = $this->code_num('SendFail');
        return $this->errors($code, __LINE__);
    }

    /**
     * email验证码二次验证
     * @param Request $request
     * @return array
     */
    public function validateEmailCode(Request $request)
    {
        $user_data = $this->validate($request, [
            'email_code'     => 'required',
            'email_key'      => 'required',
            'identification' => 'required|string'
        ]);

        //获取需要验证的email
        $key = env('PC_VALIDATE') . $user_data['identification'];
        /** @noinspection PhpUndefinedMethodInspection */
        $data  = unserialize(Redis::get($key));
        $email = $data['data']['email_address'];

        $redis_key = env('PC_EMAIL') . $email . "_" . $user_data['email_key'];
        /** @noinspection PhpUndefinedMethodInspection 验证邮箱验证码是否过期 */
        if (empty(redis::get($redis_key))) {
            $code = $this->code_num('VerifyInvalid');
            return $this->errors($code, __LINE__);
        }


        /** @noinspection PhpUndefinedMethodInspection 验证邮箱验证码是否错误 */
        if (!hash_equals(redis::get($redis_key), $user_data['email_code'])) {
            $code = $this->code_num('VerificationCode');
            return $this->errors($code, __LINE__);
        }

        /** @noinspection PhpUndefinedMethodInspection  清除redis 里面的数据 */
        redis::del($redis_key);

        //获取数据
        $status_info = $this->agentService->lastValidate($user_data, $status = "email");

        if ($status_info['code'] == 200) {
            return $this->response($status_info['data'], 200);
        }
        //判断登录角色并生成token
        $info = $this->createToken($status_info['data'], $user_data['identification']);
        return $this->response($info, 200);
    }

    /**
     * 验证google验证码
     * @param Request $request
     * @return array
     */
    public function checkGoogleCode(Request $request)
    {
        if ($request->isMethod('get')) {

            /** @noinspection PhpUndefinedFieldInspection */
            $google_data = $this->agentService->getUserGoogleAuth($request->id);

            if ($google_data['code'] != 200) {
                $code = $this->code_num('GetMsgFail');
                return $this->errors($code, __LINE__);
            }

            if ($google_data['real_code'] != 200) {
                $code = $this->code_num('Unbound');
                return $this->errors($code, __LINE__);
            }

            /** @noinspection PhpUndefinedFieldInspection */
            $user_data['verify'] = $request->verify;
            $user_data['secret'] = $google_data['data']['google_key'];
        } else {
            /* 获取信息 */
            $user_data = $this->validate($request, [
                'verify'         => 'required|string',
                'identification' => 'required|string'
            ]);
            $key       = env('PC_VALIDATE') . $user_data['identification'];
            /** @noinspection PhpUndefinedMethodInspection */
            $data = unserialize(Redis::get($key));

            if (empty($data)) {
                $code = $this->code_num('GetMsgFail');
                return $this->errors($code, __LINE__);
            }
            $user_data['secret'] = $data['data']['google_secret'];
        }

        //验证google_key
        $this->getSecurityVerificationService();
        $result = $this->securityVerificationService->checkGoogleVerify($user_data);

        if ($result['data']['code'] != 200) {
            $code = $this->code_num('VerificationCode');
            return $this->errors($code, __LINE__);
        } elseif ($request->isMethod('get') && $result['data']['code'] == 200) {
            /** @var  $google_data */
            $forget_password_key = env('PC_VALIDATE') . 'forget_password_' . $google_data['data']['agent_id'];
            /** @noinspection PhpUndefinedMethodInspection */
            Redis::setex($forget_password_key, 600, 1);  //埋点
            return $this->response("", 200);
        }
        $status_info = $this->agentService->lastValidate($user_data, "google");
        //判断登录角色并生成token
        $info = $this->createToken($status_info['data'], $user_data['identification']);
        return $this->response($info, 200);
    }

    /**
     * 二次验证创建Token
     * @param $status_info
     * @param $key
     * @return array
     */
    public function createToken($status_info, $key)
    {
        $key                      = "validate_" . $key;
        $status_info['user_id']   = $status_info['agent_id'];
        $status_info['user_name'] = $status_info['agent_name'];
        $token                    = $this->getToken($status_info);

        //用户异地登录短信提醒
        $abnormal = $this->agentService->checkIp($status_info['agent_id']);

        //创建登录历史
        $this->agentService->createLoginHistory($status_info, $token, 1);

        if (!$token) {
            $code = $this->code_num('LoginFailure');
            return $this->errors($code, __LINE__);
        }

        //异地登录短信提醒
        if ($abnormal != false) {
            /* @var MessageTemplateService $messageTemplateService 验证服务接口 */
            $messageTemplateService = app(MessageTemplateService::class);
            $type                   = $messageTemplateService->phoneLoginCopyWriting(
                $abnormal['phone_idd'],
                $abnormal['agent_name']
            );

            $this->getSecurityVerificationService();
            $this->securityVerificationService->sendSms($abnormal, $type);
        }

        //成功并登录
        $info['token'] = $token;
        $info['name']  = $status_info['user_name'];
        /** @noinspection PhpUndefinedMethodInspection */
        Redis::del($key);
        return $info;
    }
}
