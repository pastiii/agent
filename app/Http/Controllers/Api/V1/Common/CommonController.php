<?php

namespace App\Http\Controllers\Api\V1\Common;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\SecurityVerificationService;
use App\Services\MessageTemplateService;
use Illuminate\Http\Request;
use App\Support\SendTrait;

/**
 * Created by PhpStorm.
 * User: zxq
 * Date: 2018/7/9
 * Time: 21:12
 */
class CommonController extends BaseController
{
    use SendTrait;
    /* @var SecurityVerificationService */
    protected $securityVerificationService;

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
     * 获取token
     * @return array
     */
    public function getCaptcha()
    {
        $this->getSecurityVerificationService();
        //获取验证码token
        $data = $this->securityVerificationService->createToken();

        if ($data['real_code'] != 200) {
            $code = $this->code_num('GetVerify');
            return $this->errors($code, __LINE__);
        }
        return $this->response($data['data']['data']);
    }

    /**
     * 验证验证码
     * @param Request $request
     * @return array
     */
    public function checkCode(Request $request)
    {
        $this->getSecurityVerificationService();
        $data = $this->validate($request, [
            'code'  => 'required|string',
            'token' => 'required|string'
        ]);

        //获取验证信息
        $info = $this->securityVerificationService->checkCaptcha($data);

        if ($info['real_code'] == 200 && $info['data']['code'] != 200) {
            $code = $this->code_num('VerificationCode');
            return $this->errors($code, __LINE__);
        }

        if ($info['real_code'] == 200 && $info['data']['code'] == 200) {
            return $this->response("", 200);
        }

        $code = $this->code_num('GetMsgFail');
        return $this->errors($code, __LINE__);
    }

    /**
     * 发送邮件(改pin)
     * @return array
     */
    public function email()
    {
        //获取邮箱address
        $user_info = $this->get_user_info();
        //发送邮件
        /* @var MessageTemplateService $MessageTemplateService 验证服务接口 */
        $MessageTemplateService = app(MessageTemplateService::class);
        $data = $MessageTemplateService->emailCopyWriting();
        $this->getSecurityVerificationService();
        $emailMessage = $this->securityVerificationService->sendEmail($user_info['email'], $data);
        $email_data = $this->storageEmail($emailMessage, $user_info['email'], $data);
        if ($email_data['code'] == 200) {
            return $this->response([ 'email_key' => $email_data['email_key'] ], 200);
        }

        $code = $this->code_num('SendFail');
        return $this->errors($code, __LINE__);
    }


    /**
     * 发送手机验证码
     * @param Request $request
     * @return mixed
     */
    public function sendSms(Request $request)
    {
        //判断手机验证的环境
        $phone_info = $this->validate($request, [
            'phone_number' => 'required|regex:/^1[34578]\d{9}$/',
            'phone_idd'    => 'required|string',
        ]);
        /* @var MessageTemplateService $MessageTemplateService 验证服务接口 */
        $MessageTemplateService = app(MessageTemplateService::class);
        $data                   = $MessageTemplateService->phoneCodeCopyWriting($phone_info['phone_idd']);


        $this->getSecurityVerificationService();
        $smsMessage = $this->securityVerificationService->sendSms($phone_info, $data);
        $result = $this->storageCode($smsMessage, $data, $phone_info);
        if ($result['code'] == 200) {
            return $this->response([ 'verification_key' => $result['verification_key'] ], 200);
        }

        $code = $this->code_num('SendFail');
        return $this->errors($code, __LINE__);
    }


    /**
     * 获取国家区域信息
     * @return array
     */
    public function getCountry()
    {
        /**@var SecurityVerificationService $securityVerificationService*/
        $securityVerificationService = app(SecurityVerificationService::class);
        $country                     = $securityVerificationService->getCountry();

        if ($country['real_code'] == 200) {
            return $this->response($country['data'], 200);
        }

        $code = $this->code_num('GetMsgFail');
        return $this->errors($code, __LINE__);
    }
}
