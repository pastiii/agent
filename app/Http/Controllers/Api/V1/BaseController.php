<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Token\Apiauth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BaseController extends Controller
{
    protected $user_id;

    public function __construct()
    {
        $this->user_id = intval(ApiAuth::userId());
    }

    /**
     *数据返回
     * @param $data
     * @param int $code
     * @return array
     */
    protected function response($data, $code = 0)
    {
        return [
            'status_code' => $code,
            'timestamp'   => time(),
            'data'        => $data,
        ];
    }

    /**
     * 错误数据返回
     * @param int $code
     * @return array
     */
    protected function errors($code = 0, $line = "")
    {
        return [
            'status_code' => $code,
            "line"        => $line,
            'timestamp'   => time(),
        ];
    }

    /**
     * 验证错误信息
     * @param $code
     * @return mixed
     */
    protected function code_num($code)
    {
        return config('state_code.' . $code);
    }

    /**
     * 根据获取用户信息
     * @return array
     */
    protected function get_user_info()
    {
        return ApiAuth::user();
    }

    /**
     * 获取token
     * @param $user_info
     */
    public function getToken($user_info)
    {
        return ApiAuth::login($user_info);
    }

    /** 验证 */
    public function validate(Request $request, array $rules,
                             array $messages = [], array $customAttributes = [])
    {
        $validate = Validator::make(app('request')->all(), $rules, $messages, $customAttributes);
        if ($validate->fails()) {
            $errors = $validate->errors()->messages();
            $errorData = $this->errorMessage($errors);
            echo json_encode(['status_code' => $errorData], JSON_HEX_TAG);
            die();
        }

        //返回验证信息
        return $this->extractInputFromRules($request, $rules);
    }

    /**
     * 验证信息处理
     * @param $msg
     * @return array
     */
    public function errorMessage($msg)
    {
        $message = [];
        foreach ($msg as $key =>$val) {
            if (is_array($val)) {

                $first = current($val);
                $current = array_map(function ($item) {
                    $arr = [];
                    if ($item) {
                        $value = explode(',', $item);
                        if ($value) {
                            foreach ($value as $v) {
                                $i = explode(':', $v);
                                if (count($i) == 2) {
                                    $arr[trim($i[0])] = $i[1];
                                }
                            }
                        }
                    }
                    return $arr;
                }, $first);

                $message[key($first)] = [$key => json_encode(current($current))];
                break;
//               foreach ($val as $v){
//                   $message[$key] = $v;
//               }
            }
        }
        return $message;
    }



}


?>

