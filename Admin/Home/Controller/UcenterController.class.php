<?php
namespace Home\Controller;
use Think\Controller\RestController;
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods:POST,GET');
header('Access-Control-Allow-Credentials:true'); 
header("Content-Type: application/json;charset=utf-8");
/*
 * 个人用户操作中心
 */
class UcenterController extends RestController
{
    public $m_rule = '/^1[34578]{1}\d{9}$/';
    public $e_rule = '/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i';

    /*
     * 用户编辑
     * */
    public function userEdit(){
        if (IS_POST) {
            $uid       = (int)I('uid');
            $password  = I('password');
            $mobile    = I('mobile');
            $email     = I('email');
            $real_name = I('real_name');
            $remark    = I('remark');

            $user = M('auth_user')->where('id = %d',[$uid])->find();
            if(!$user) $this->response(['status' => 102, 'msg' => '用户不存在'],'json');
            if(!empty($password)){
                if(strlen($password) < 6){
                    $this->response(['status' => 102, 'msg' => '密码长度不符合'],'json');
                }
                $pwd = EncodePwd($password, $user['pwdsuffix']);
            }else{
                $pwd = $user['password'];
            }
            if(empty($real_name)){
                $this->response(['status' => 102, 'msg' => '真实姓名必填'],'json');
            }
            if (!empty($mobile)) {
                if (!preg_match($this->m_rule, $mobile)) {
                    $this->response(['status' => 102, 'msg' => '手机格式不正确'],'json');
                }
                $edit_data['mobile'] = $mobile;
            }
            if(empty($mobile)){
                $mobile = null;
            }
            if (!empty($email)) {
                if (!preg_match($this->e_rule, $email)) {
                    $this->response(['status' => 102, 'msg' => '邮箱格式不正确'],'json');
                }
                $edit_data['email'] = $email;
            }
            $edit_data = [
                'password'  => $pwd,
                'real_name' => $real_name,
                'remark'    => $remark,
                'modified_time' => date('Y-m-d H:i:s', time()),
            ];
            $edit = M('auth_user')->where('id = %d',[$uid])->save($edit_data);
            if ($edit) {
                $this->response(['status' => 100],'json');
            } else {
                $this->response(['status' => 101, 'msg' => '编辑异常'],'json');
            }
        } else {
            $this->response(['status' => 103, 'msg' => '请求失败'],'json');
        }
    }


    ///////////////////////////////////////////////////////
    //////////////////////// 导航 /////////////////////////
    ///////////////////////////////////////////////////////
    /*
      * 导航读取
      * @param nav_id
      * */
    public function navManage(){
        $m = M('auth_nav');
        $rule = M('auth_rule');
        $uids = I('user_id'); // 当前未接收到加密的id
        $uid = authcode(base64_decode($uids),'DECODE',md5(C('ENCODE_USERID')));

        if(!$uid) $this->response(['status' => 101,'msg' => "您还没有登陆"],'json');
        // 根据用户id获取到所属角色
        $role = new \Think\Product\PAuth();
        $roles = $role->GetUserRole($uid);
        if(!$roles) $this->response(['status' => 100,'msg' => "还没有归档角色组"],'json');

        $auth  = [];$auths = '';
        foreach($roles as $key => $val){
            if(empty($val['permissions']) || $val['permissions'] == null || $val['permissions'] == "")
                continue;
            $auth[] = $val['permissions'];
        }
        // 把所有权限放进一维数组
        foreach($auth as $v){
            $auths .= $v.',';
        }
        $auths = implode("," ,array_unique(explode(",",trim($auths ,","))));
        if($auths == "") $this->response(['status' => 101,'msg' => '无导航权限'],'json');
        // 通过拥有的权限读导航id
        $navs = $rule->where("id IN ($auths)")->field('nav_id')->select();
        foreach($navs as $nv){
            if($nv['nav_id'] != 0){
                $nav_id[] = $nv['nav_id'];
            }
        }
        $nav_id = implode("," ,array_unique($nav_id));
        if($nav_id == "") $this->response(['status' => 101,'msg' => '无导航权限'],'json');

        // 查询到了所有已有权限的导航
        $navTab = $m->where("id IN ($nav_id)")->order("no asc")->field('id,tab_id')->select();
        foreach($navTab as $tk => $tv){
            $nav_ids[] = $tv['id'];
            $tab_ids[] = $tv['tab_id'];
        }
        $tab_ids = implode("," ,array_unique($tab_ids));
        $nav_ids = implode("," ,array_unique($nav_ids));
        if($tab_ids == "" || $nav_ids == "") $this->response(['status' => 101,'msg' => '无导航权限'],'json');

        // 查询出所有拥有的导航类
        $navTab = M('auth_nav_tab')->where("id IN ($tab_ids)")->order("no asc")->field('id,nav_tab')->select();
        if(!$navTab) $this->response(['status' => 101,'msg' => '无导航信息'],'json');
        foreach($navTab as $key => $value){
            // 通过导航类将导航按照规定格式放入
            $nav = $m->where("tab_id = ".$value['id']." AND id IN ($nav_ids)")->field('link_name,link')->order("no asc")->select();
            if($nav){
                $navTab[$key]['nav_list'] = $nav;
            }
        }
        $this->response(['status' => 100,'value' => [ 'navData' => $navTab]],'json');
    }

    /*
     * 通过id读取用户信息
     * @param id 用户id
     * */
    public function getUserInfoById(){
        $uid = (int)I('post.uid');
        if($uid == 0) $this->response(['status' => 103, 'msg' => '请求失败'],'json');
        $result = \Think\Product\User::getuserByid($uid);
        if($result['error'] == 0){
            $this->response(['status' => 100, 'value' => $result['value'],],'json');
        }else{
            $this->response(['status' => $result['status'],'msg' => $result['msg']],'json');
        }
    }
}