<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Services\AgentService;
use App\Services\UserService;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\BaseController;

/**
 * Class AgentController
 * @package App\Http\Controllers\Api\V1\Agent
 * Created by PhpStorm.
 * User: Shao
 * Date: 2018/6/7
 * Time: 19:15
 */
class AgentController extends BaseController
{
    /** @var AgentService  */
    protected $agentService;

    /**
     * AgentController constructor.
     * @param AgentService $agentService
     */
    public function __construct(AgentService $agentService)
    {
        parent::__construct();
        $this->agentService = $agentService;
    }


    /**
     * 创建推广码
     * @return array
     */
    public function createAgentPromo()
    {
        // 创建推广码
        $agentPromoRes = $this->agentService->createAgentPromo($this->get_user_info());

        // 返回推广码数据
        if ($agentPromoRes['real_code'] == 200) {
            // $agent_promo = AGENT_PROMO_URL.$agentPromoRes['data']['agent_promo'];
            $agent_promo = $agentPromoRes['data']['agent_promo'];
            return $this->response($agent_promo, 200);
        } else {
            $code = $this->code_num("CreateFailure");
            return $this->errors($code, __LINE__);
        }
    }


    /**
     * 代理商 推广码列表
     * @param Request $request
     * @return array
     */
    public function getAgentPromoList(Request $request)
    {
        // 数据验证
        $data = $this->validate($request, [
            'limit' => 'required|int',
            'page'  => 'required|int',
        ]);

        $param = [
            "order"    => "DESC",
            "sort"     => "agent_promo_id",
            "limit"    => $data['limit'],
            "start"    => ( ( $data['page'] - 1 ) * $data['limit'] ),
            "agent_id" => $this->user_id,
        ];
        // 获取推广码列表数据
        $agentPromoList = $this->agentService->getAgentPromoList($param);

        // 返回数据
        if ($agentPromoList['real_code'] != 200) {
            return $this->response($agentPromoList['data'], 200);
        }


        // 数据页码
        $count_list                = $this->agentService->getAgentPromoCount($this->user_id);
        $page_info['count']        = $count_list['data']['count'];
        $page_info['current_page'] = $data['page'];
        $page_info['total_page']   = ceil($count_list['data']['count'] / $param['limit']);

        $agentPromoList = array_merge($agentPromoList['data'], $page_info);

        return $this->response($agentPromoList, 200);
    }

    /**
     *  agent_contacts
     * 创建代理平台账号信息
     * @param Request $request
     * @param UserService $userService
     * @return array
     */
    public function createAgentContacts(Request $request, UserService $userService)
    {
        // 数据验证  组装数据
        $data = $this->validate($request, [
            'phone_idd'    => 'nullable|string',
            'phone_number' => 'nullable|regex:/^[0-9]{2,20}$/',
            'email'        => 'nullable|E-mail',
            'user_name'    => 'required|string',
        ]);

        $agentInfo          = $this->get_user_info();
        $data['agent_id']   = intval($agentInfo['agent_id']);
        $data['agent_name'] = $agentInfo['agent_name'];


        // 获取该账号是否已绑定
        if (!empty($data['phone_idd']) && !empty($data['phone_number'])) {
            $verify = $this->agentService->phoneGetAgentContacts(
                $data['agent_id'],
                $data['phone_idd'],
                $data['phone_number']
            );
        } elseif (!empty($data['email'])) {
            $verify = $this->agentService->emailGetAgentContacts($data['agent_id'], $data['email']);
        } else {
            $code = $this->code_num('ParamError');
            return $this->errors($code, __LINE__);
        }


        // 验证该账号是否已绑定
        if ($verify['real_code'] != 200) {
            $code = $this->code_num("GetMsgFail");
            return $this->errors($code, __LINE__);
        } else {
            if (!empty($verify['data']['list'])) {
                $code = $this->code_num("NotBinDing");
                return $this->errors($code, __LINE__);
            }
        }

        // 获取用户信息
        if (!empty($data['phone_idd']) && !empty($data['phone_number'])) {
            // 根据phone 获取用户信息
            $user_info = $userService->getUserPhoneByPhone($data['phone_number'], $data['phone_idd']);
        } else {
            // 根据email 获取用户信息
            $user_info = $userService->getUserEmailByMail($data['email']);
        }

        if ($user_info['real_code'] != 200) {
            $code = $this->code_num("Empty");
            return $this->errors($code, __LINE__);
        }

        // 验证用户名是否正确
        if ($user_info['data']['user_name'] != $data['user_name']) {
            $code = $this->code_num("UserNameError");
            return $this->errors($code, __LINE__);
        }

        $data['user_id']   = intval($user_info['data']['user_id']);
        $data['user_name'] = $user_info['data']['user_name'];


        // 绑定账号
        $agentContactsRes = $this->agentService->createAgentContacts($data);
        // 返回数据
        if ($agentContactsRes['real_code'] == 200) {
            return $this->response($agentContactsRes['data'], 200);
        } else {
            $code = $this->code_num("CreateFailure");
            return $this->errors($code, __LINE__);
        }
    }


    /**
     * 获取代理平台账号信息列表
     * @param Request $request
     * @return array
     */
    public function getUserAccessList(Request $request)
    {
        $data         = $this->validate($request, [
            'page' => 'nullable|int',
        ]);
        $data['page'] = isset($data['page']) ? $data['page'] : 1;
        $limit        = $request->get('limit', 10);

        // 获取数据
        $userAccessList = $this->agentService->getUserAccessList($this->user_id, $data['page'], $limit);

        // 返回数据
        if ($userAccessList['real_code'] != 200) {
            return $this->response([ 'list' => $userAccessList['data'] ], 200);
        }
        // 获取数据分页信息
        $count_list                = $this->agentService->getAgentContactsCount($this->user_id);
        $page_info['count']        = $count_list['data']['count'];
        $page_info['current_page'] = $data['page'];
        $page_info['total_page']   = ceil($count_list['data']['count'] / $limit);

        $userAccessList = array_merge($userAccessList['data'], $page_info);
        return $this->response($userAccessList, 200);
    }

    /**
     * 代理商绑定删除
     * @param Request $request
     * @return array
     */
    public function deleteAgentContacts(Request $request)
    {
        $data = $this->validate($request, [
            'id' => 'required|int',
        ]);
        // 代理商绑定删除
        $res = $this->agentService->deleteAgentContacts($data['id']);

        // 返回数据
        if ($res['code'] == 200) {
            return $this->response("", 200);
        }
        $code = $this->code_num('DeleteFailure');
        return $this->errors($code, __LINE__);
    }


    /**
     * 获取代理返点率信息
     * @return array
     */
    public function getAgentRebate()
    {
        $user_info = $this->get_user_info();

        // 判断是否创建过返点率
        $agentRebate = $this->agentService->getAgentRebate($user_info['agent_id']);

        if ($agentRebate['real_code'] != 200) {
            $user_info               = $this->get_user_info();
            $addRebate['agent_id']   = (int) $user_info['agent_id'];
            $addRebate['agent_name'] = $user_info['agent_name'];
            $addRebate['rebate']     = intval(env("AGENT_REBATE", 50));
            // 创建返点率
            $agentRebate = $this->agentService->createAgentRebate($addRebate);
        }

        // 返回数据
        $data['data']['rebate'] = isset($agentRebate['data']['rebate']) ? $agentRebate['data']['rebate'] : '';
        return $this->response($data['data'], 200);
    }

    /**
     * agent_rebate  返点率
     * 创建代理返点率信息
     * @param Request $request
     * @return array
     */
    public function updateAgentRebate(Request $request)
    {
        $data = $this->validate($request, [
            'rebate' => 'required|integer',
        ]);


        $user_info          = $this->get_user_info();
        $data['agent_id']   = (int) $user_info['agent_id'];
        $data['agent_name'] = $user_info['agent_name'];
        $data['rebate']     = intval($data['rebate']);

        // 判断是否创建过返点率
        $ifExist = $this->agentService->getAgentRebate($data['agent_id']);

        if ($ifExist['real_code'] == 200) {   // 更新返点率
            $agentRebateRes = $this->agentService->updateAgentRebate($data);
        } else {   // 创建返点率
            $agentRebateRes = $this->agentService->createAgentRebate($data);
        }

        // 返回数据
        if ($agentRebateRes['real_code'] == 200) {
            return $this->response($agentRebateRes['data'], 200);
        } else {
            $code = $this->code_num("UpdateFailure");
            return $this->errors($code, __LINE__);
        }
    }


    /**
     * 代理商记录列表
     * @param Request $request
     * @return array
     */
    public function getUserList(Request $request)
    {
        // 组装请求参数
        $data             = $this->validate($request, [
            'order'            => 'nullable|in:DESC,ASC',
            'user_name'        => 'nullable|string',
            'parent_user_name' => 'nullable|string',
            'agent_promo'      => 'nullable|string',
            'limit'            => 'nullable|int|min:1',
            'page'             => 'nullable|int|min:1',
            'start_time'       => 'nullable|date',
            'end_time'         => 'nullable|date',
        ]);
        $data['sort']     = 'user_id';
        $data['agent_id'] = $this->user_id;

        // 代理商记录列表数据
        $user_info = $this->agentService->getUserList($data);

        // 返回数据
        if ($user_info['real_code'] == 200) {
            return $this->response($user_info['data'], 200);
        }
        $code = $this->code_num("GetMsgFail");
        return $this->errors($code, __LINE__);
    }


    /**
     * 昨日新增人数
     * @return array|float|int
     */
    public function getAgentNewUserDay()
    {
        // 组装请求参数
        $data = [
            'order'    => 'DESC',
            'sort'     => 'id',
            'limit'    => 7,
            'start'    => 0,
            'agent_id' => $this->user_id,
        ];
        // 获取统计7天新增会员数
        $res = $this->agentService->getAgentNewUserDayList($data);
        // 返回数据
        if ($res['real_code'] != 200) {
            $code = $this->code_num("GetMsgFail");
            return $this->errors($code, __LINE__);
        }
        $sum = array_sum(array_column($res['data']['list'], 'new_user_count'));
        return $this->response($sum, 200);
    }
}
