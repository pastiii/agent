<?php

namespace App\Token;


use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\HeaderBag;

class Token
{
    public $token;
    protected $token_expiry_time;

    const UserIdPrefix = 'user_id.';
    const TokenPrefix = 'token.';
    const PcAgentKey = 'pc_agent_token';
    const TimeUnits = 60;


    public function __construct($request)
    {
        $this->token_expiry_time = env('TOKEN_EXPIRY_TIME');
        $token = $request->header('Authorization');
        $firstStr = substr($token, 0, 6);
        if ($firstStr == 'Bearer') {
            $this->token = trim(substr($token, 6));
        }
    }

    /**
     * @param array $info 用户信息
     * @param int| string $time
     * @return string
     */
    public function login($info, $time = '')
    {
        $time = empty($time) ? $this->token_expiry_time : $time;   //token过期时间

        $this->token = $token = $this->createToken();
        Redis::setex($this->getTokenKey($token), $time * self::TimeUnits, $info['user_id']);

        $oldToken = Redis::hget($this->getUserIdKey($info['user_id']), self::PcAgentKey);
        if ($oldToken != null) {
            Redis::del($oldToken);
        }
        Redis::hset($this->getUserIdKey($info['user_id']), self::PcAgentKey, $this->getTokenKey($token));


        Redis::hmset($this->getUserIdKey($info['user_id']), $info);
        //在request上 维护上当前的头部信息

        $this->changeHeaderToken($token);

        return $token;
    }

    private function createToken()
    {
        return md5(time() . uniqid());
    }


    public function user()
    {
        if (!$user_id = $this->checkToken()) {
            return false;
        }
        return $this->getInfoByUserId($user_id);
    }

    public function userId()
    {
        return Redis::get($this->getTokenKey($this->token));
    }

    private function getInfoByUserId($user_id)
    {
        $user_info = Redis::HGETALL($this->getUserIdKey($user_id));
        unset($user_info[self::PcAgentKey]);
        return $user_info;
    }

    private function checkToken()
    {
        if ($this->token == null) {
            return false;
        }
        if ($user_id = Redis::get($this->getTokenKey($this->token))) {
            return $user_id;
        }

        return false;
    }

    private function changeHeaderToken($token)
    {
        $request = app()->request;

        return $request->headers->set('Authorization', 'Bearer' . $token);
    }

    private function getUserIdKey($userId)
    {
        return self::UserIdPrefix . $userId;
    }

    private function getTokenKey($token)
    {
        return self::TokenPrefix . $token;
    }

    public function deleted_token()
    {
        Redis::del(self::TokenPrefix . $this->token);
    }

}
