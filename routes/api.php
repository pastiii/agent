<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/** @var Dingo\Api\Routing\Router $api */
$api = app('Dingo\Api\Routing\Router');

/* 用户消息 */
$api->version('v1', function ($api) {
    /** @var Dingo\Api\Routing\Router $api */
    $api->group([ 'namespace' => 'App\Http\Controllers\Api\V1\Message', 'prefix' => 'v1/message' ], function ($api) {
        /** @var Dingo\Api\Routing\Router $api */
        $api->group([ 'middleware' => 'apiauth' ], function ($api) {
            /** @var Dingo\Api\Routing\Router $api */
            $api->get('index', 'UserMessageController@index');//显示列表
            $api->get('details', 'UserMessageController@details');// 消息详情
            $api->patch('signRead', 'UserMessageController@signRead');// 标记阅读
            $api->delete('delete', 'UserMessageController@delete');// 删除消息


        });
    });
});


/* 代理商 */
$api->version('v1', function ($api) {
    /** @var Dingo\Api\Routing\Router $api */
    $api->group([ 'namespace' => 'App\Http\Controllers\Api\V1\Agent', 'prefix' => 'v1/agent','middleware' => ['apiauth','Trace'] ], function ($api) {  //delelte 'middleware' => 'apiauth'
        /** @var Dingo\Api\Routing\Router $api */
        $api->get('index', 'AgentController@index');//显示列表

        $api->get('create_promo', 'AgentController@createAgentPromo');// 创建推广码信息
        $api->get('promo_list', 'AgentController@getAgentPromoList');// 获取推广码列表

        $api->get('get_agent_rebate', 'AgentController@getAgentRebate'); //获取代理返点率信息
        $api->post('update_agent_rebate', 'AgentController@updateAgentRebate'); //创建代理返点率信息

        $api->get('get_user_access_list', 'AgentController@getUserAccessList'); //获取代理平台账号信息列表
        $api->post('create_agent_contacts', 'AgentController@createAgentContacts');//创建代理平台账号信息
        $api->delete('delete_agent_contacts', 'AgentController@deleteAgentContacts');//删除代理平台账号信息


        $api->get('get_user_list', 'AgentController@getUserList');//记录列表
        $api->get('get_user_sum', 'AgentController@getAgentNewUserDay');//统计
    });
});


/* Agent登陆注册路由 */
$api->version('v1', function ($api) {
    /** @var Dingo\Api\Routing\Router $api */
    $api->group([ 'namespace' => 'App\Http\Controllers\Api\V1\Agent', 'prefix' => 'v1/agent' ], function ($api) {
        /** @var Dingo\Api\Routing\Router $api */
        $api->post('agent_login', 'AgentLoginController@agentLogin');
        $api->post('validate_agent', 'AgentLoginController@retrievePassword');
        $api->patch('reset_agent_pass', 'AgentLoginController@resetAgent');
        $api->get('log', 'AgentLoginController@testLog');
        $api->post('validate_email_code', 'AgentLoginController@validateEmailCode');
        $api->post('validate_phone_code', 'AgentLoginController@validatePhoneCode');
    });
});

/* Agent账户安全中心 */
$api->version('v1', function ($api) {
    /** @var Dingo\Api\Routing\Router $api */
    $api->group([ 'namespace' => 'App\Http\Controllers\Api\V1\Agent', 'prefix' => 'v1/agent', 'middleware' => 'apiauth' ], function ($api) {
        /** @var Dingo\Api\Routing\Router $api */
        $api->patch('edit_phone', 'AgentSecurityController@editPhone');
        $api->patch('patch_status', 'AgentSecurityController@patchStatus');
        $api->get('get_agent_status', 'AgentSecurityController@getUserStatusById');
        $api->get('phone_info', 'AgentSecurityController@phoneInfo');
        $api->get('email_info', 'AgentSecurityController@emailInfo');
        $api->post('get_phone_umber', 'AgentSecurityController@getPhoneNumber');
        $api->patch('update_pin', 'AgentSecurityController@updatePin');
        $api->patch('update_password', 'AgentSecurityController@updateLoginPassword');
        $api->post('get_google_code', 'AgentSecurityController@getGoogleCode');
        $api->post('check_google_code', 'AgentSecurityController@checkGoogleCode');
        $api->post('check_google_code/type/{type}', 'AgentSecurityController@checkGoogleCode');
        $api->post('check_email_code', 'AgentSecurityController@validateEmailCode');
        $api->post('check_email_code/type/{type}', 'AgentSecurityController@validateEmailCode');
        $api->post('check_phone_code/type/{type}', 'AgentSecurityController@validatePhoneCode');
        $api->post('check_phone_code', 'AgentSecurityController@validatePhoneCode');
        $api->post('send_email', 'AgentSecurityController@sendEmail');
        $api->post('check_two_status/status/{status}', 'AgentSecurityController@checkTwo');
        $api->post('sms', 'AgentSecurityController@sms');
        $api->get('agent_login_out', 'AgentSecurityController@loginOut');
    });
});

/* agent二次验证 */
$api->version('v1', function ($api) {
    /** @var Dingo\Api\Routing\Router $api */
    $api->group([ 'namespace' => 'App\Http\Controllers\Api\V1\Agent', 'prefix' => 'v1/agent' ], function ($api) {
        /** @var Dingo\Api\Routing\Router $api */
        $api->post('agent/send_sms', 'AgentCheckController@sendSms');
        $api->get('agent/send_sms/id/{id}', 'AgentCheckController@sendSms');
        $api->post('agent/send_email', 'AgentCheckController@sendEmail');
        $api->get('agent/send_email/id/{id}', 'AgentCheckController@sendEmail');
        $api->post('agent/validate_phone', 'AgentCheckController@validatePhoneCode');
        $api->post('agent/validate_email', 'AgentCheckController@validateEmailCode');
        $api->post('agent/check_google', 'AgentCheckController@checkGoogleCode');
        $api->get('agent/check_google/id/{id}', 'AgentCheckController@checkGoogleCode');
    });
});

/* 公共模块 */
$api->version('v1', function ($api) {
    /** @var Dingo\Api\Routing\Router $api */
    $api->group([ 'namespace' => 'App\Http\Controllers\Api\V1\Common', 'prefix' => 'v1/common' ], function ($api) {
        /** @var Dingo\Api\Routing\Router $api */
        $api->group([ 'middleware' => 'apiauth' ], function ($api) {
            /** @var Dingo\Api\Routing\Router $api */
            $api->get('email', 'CommonController@email');
        });
        $api->get('get_captcha', 'CommonController@getCaptcha');
        $api->post('check_captcha', 'CommonController@checkCode');
        $api->post('send_sms', 'CommonController@sendSms');
        $api->post('check_phone_code', 'CommonController@validatePhoneCode');
        $api->get('get_country', 'CommonController@getCountry');
    });
});
