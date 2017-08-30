<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Models\OperationLogs;

class AgentController extends Controller
{
    public function showAll(Request $request)
    {
        //给per_page设定默认值，比起参数默认值这样子可以兼容uri传参和变量名传参，变量名传递过来的参数优先
        $per_page = $request->per_page ?: 10;
        $order = $request->sort ? explode('|', $request->sort) : ['id', 'desc'];

        //查找，只查找account
        if ($request->has('filter')) {
            $filterText = $request->filter;
            return User::with(['group', 'parent', 'inventorys.item'])
                ->where('account', 'like', '%'.$filterText.'%')
                ->where('group_id', '!=', 1)
                ->orderBy($order[0], $order[1])
                ->paginate($per_page);
        }

        return User::with(['group', 'parent', 'inventorys.item'])
            ->where('group_id', '!=', 1)->orderBy($order[0], $order[1])
            ->paginate($per_page);
    }

    //创建代理商
    public function create(Request $request)
    {
        Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'account' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'email' => 'string|email|max:255',
            'phone' => 'integer|digits:11',
            'group_id' => 'required|integer|not_in:1',  //管理员不能创建管理员
            'parent_id' => 'required|integer',
        ])->validate();

        $data = $request->intersect(
            'name', 'account', 'password', 'email', 'phone', 'group_id', 'parent_id'
        );
        return User::create($data);
    }

    public function destroy(User $user)
    {
        if ($this->isAdmin($user)) {
            return [
                'error' => '不能删除管理员'
            ];
        }

        if ($this->hasSubAgent($user)) {
            return [
                'error' => '此代理商下存在下级代理'
            ];
        }

        return $user->delete() ? ['message' => '删除成功'] : ['message' => '删除失败'];
    }

    protected function isAdmin($user)
    {
        return 1 == $user->group_id;
    }

    protected function hasSubAgent($user)
    {
        return User::where('parent_id', $user->id)->get()->count();
    }

    public function update(Request $request, User $user)
    {
        $input = $request->all();

        //如果提交的用户名和用户本身的用户名相同，就不进行Validate，不然unique验证失败
        if ($request->has('account') and ($request->input('account') == $user->account)) {
            unset($input['account']);
        }

        Validator::make($input, [
            'name' => 'string|max:255',
            'account' => 'string|max:255|unique:users',
            'password' => 'string|min:6',
            'email' => 'string|email|max:255',
            'phone' => 'integer|digits:11',
            'group_id' => 'integer|not_in:1',   //不能将代理商改成管理员
            'parent_account' => 'string|exists:users,account',
        ])->validate();

        $data = $request->intersect(
            'name', 'account', 'password', 'email', 'phone', 'group_id'
        );

        $parentId = User::where('account', $request->get('parent_account'))->first()->id;
        $data = array_merge($data, ['parent_id' => $parentId]);

        //管理员只能改自己的密码、邮箱和手机信息，其他信息暂不允许修改
        if ($this->isAdmin($user)) {
            $data = $request->intersect(
                'password', 'email', 'phone'
            );
        }

        if ($user->update($data)) {
            //TODO 操作日志记录，待完成登录之后追加，用户id从session里面拿
            //OperationLogs::insert(1, $request->path(), $request->method(), '更新用户信息', json_encode($data));
            return [
                'message' => '更新用户数据成功'
            ];
        }
    }
}