<?php
namespace Home\Controller;
use Think\Controller;

class PictureController extends BaseController
{
    public $insert_id = array();

    // 拉取图片
    public function get_picture(){
        $gallery_id = strip_tags(trim(I('gallery_id')));
        $title      = strip_tags(trim(I('title')));
        $rubbish    = isset($_POST['rubbish']) ? $_POST['rubbish'] : 0;
        $tags       = strip_tags(trim(I('tags')));
        if(preg_match("/^[0-9]+$/", $gallery_id) || $rubbish == 1){
            // 提供查询条件
            if($rubbish == 1){
                $where = "rubbish=1";  // 回收站
            }else{
                $where = "gallery_id=".$gallery_id;
                // 模糊查询条件
                if(!empty($title)){
                    $where .= " AND title like '%".$title."%'";
                }
                if(!empty($tags)){
                    $where .= " AND tags like '%".$tags."%'";
                }
            }
            $page = isset($_POST['pageNum']) ? strip_tags(trim(I('pageNum'))) : 1;
            $pagesize = isset($_POST['pageSize']) ? strip_tags(trim(I('pageSize'))) : 48;
            // 拉取
            $result = \Think\Product\Picture::get_pic($where,$page,$pagesize);
            if($result['error'] == 0){
                $data['status']     = 100;
                $data['value']      = $result['value'];
                $data['countPage']  = $result['countPage'];
                $data['countImage'] = $result['countImage'];
                $data['pageNow']    = $page;
            }else{
                $data['status'] = $result['status'];
                $data['msg']    = $result['msg'];
            }
        }else{
            $data['status'] = 102;
            $data['msg']    = '图片类目选取失败';
        }
        $this->response($data,'json');
    }

    public function del_picture(){

        $id_arr   = $_POST['id'];
        // 删除方式获取
        $del_type = isset($_POST['delete_type']) ? $_POST['delete_type'] : 1;
        // 判断假如传的id是单条，即改成数组统一当成批量方式删除
        if(!is_array($id_arr)){
            $arr[] = $id_arr;
        }else{
            $arr = $id_arr;
        }

        foreach ($arr as $key => $value) {
            $res = checkDataLimit('DL',$value);
            if($res == 1){
                $array[] = $value;
            }
        }
        if(empty($array)){
            $data['status'] = 103;
            $data['msg']    = '所有选取图片已经被使用';
            $this->response($data,'json');
            exit();
        }
        // 假如数组有空项，删除
        $arr = array_filter($array);
        if(is_array($arr)){
            if($del_type == 1){
                $result = \Think\Product\Picture::pic_to_rubbish($arr); // 默认移到回收站
            }elseif(!empty($del_type) || $del_type == 0){
                $result = \Think\Product\Picture::delete_pic($arr);  // 彻底删除
            }
            if($result['error'] == 0){
                $data['status'] = 100;
                $data['value']  = $result['value'];
            }else{
                $data['status'] = $result['status'];
                $data['msg']    = $result['msg'];
            }
        }else{
            $data['status'] = 102;
            $data['msg']    = '图片选取失败';
        }
        $this->response($data,'json');
    }

    public function upload(){

        $path  = "./Pictures/";
        $typeArr = array("jpg", "png", "gif" , "jpeg");//允许上传文件格式

        $id = I('gallery_id');
        if($id == "" || empty($id) || $id == 0){
            $id = 1;
        }

        $creator_id = I('creator_id');
        if(empty($creator_id)){
            $arr['status'] = 1012;
            $this->response($arr,'json');
            exit();
        }
        $m = M('product_gallery')->where(array('id'=>$id))->find(); // 查询图片类目放文件夹
        if(!$m){
            $data["error"] = "所选类目不存在！";
            $this->response($data,'json');
        }

        $name = $_FILES['file']['name'];
        $size = $_FILES['file']['size'];
        $name_tmp = $_FILES['file']['tmp_name'];
        if (empty($name)) {
            $data["error"] = "您还未选择图片";
            $this->response($data,'json');
        }

        $type = strtolower(substr(strrchr($name, '.'), 1)); //获取文件类型
        if (!in_array($type, $typeArr)) {
            $data["error"] = "请上传jpg,png,gif,jpeg类型的图片！";
            $this->response($data,'json');
        }

        if ($size > (5 * 1024 * 1024)) {
            $data["error"] = "图片大小超过5MB！";
            $this->response($data,'json');
        }
        $category = M('product_category')->field('en_name')->where("id=%d",array($m['category_id']))->find();
        if(!is_dir($path.str_replace(' ','_',trim($category['en_name'])))){
            mkdir($path.str_replace(' ','_',trim($category['en_name'])));
        }
        $dir = $m['dir'];
        if(!is_dir($dir)){
            mkdir($dir);
        }
        $pic_name = time() . rand(10000, 99999) . "." . $type;//图片名称
        $pic_url = $dir . "/" .$pic_name;//上传后图片路径 + 名称
        if (move_uploaded_file($name_tmp, $pic_url)) { //临时文件转移到目标文件夹
            $picpropety           = getimagesize($pic_url);
            $in['width']          = $picpropety[0];
            $in['height']         = $picpropety[1];
            $in['file_name']      = $pic_name;
            $in['path']           = $dir;
            $in['creator_id']     = $creator_id;
            $in['created_time']   = isset($_COOKIE["user_id"]) ? cookie("user_id") : 0;
            $in['create_time']    = date("Y-m-d H:i:s",time());
            $in['modified_time']  = date("Y-m-d H:i:s",time());
            $in['file_type']      = $type;
            $in['gallery_id']     = $id;
            $in['file_size']      = $size;
            $insert = M('product_picture')->add($in);
            if($insert){
                $data["error"] = "0";
                $data["pic"]   = $pic_url;
                $data["name"]  = $pic_name;
            }else{
                $data["name"] = "写入数据有误！";
            }
        } else {
            $data["name"] = "上传有误，请检查服务器配置！";
        }
        $this->response($data,'json');
    }


    // 图片编辑
    public function edit_pic(){

        $edit_data = I('data');
        if(!empty($edit_data['id']) && preg_match("/^[0-9]+$/",$edit_data['id'])){// && !empty($image) && preg_match("/^[A-Za-z\s_]+[0-9]*$/", $image)
            if(!empty($edit_data['title']) && !empty($edit_data['tags'])){
                $arr = array(
                    'tags'          => implode("||",$edit_data['tags']),
                    'title'         => $edit_data['title'],
                    'modified_time' => date('Y-m-d H:i:s' , time()),
                );
                // 具体操作
                $result = \Think\Product\Picture::edit_pic($edit_data['id'],$arr);
                if($result['error'] == 0){
                    $data['status'] = 100;
                    $data['value']  = $result['value'];
                }else{
                    $data['status'] = $result['status'];
                    $data['msg']    = $result['msg'];
                }
            }else{
                $data['status'] = 102;
                $data['msg']    = '标题或者标签不能为空';
            }
        }else{
            $data['status'] = 102;
            $data['msg']    = '图片选取失败或（id）错误';
        }

        $this->response($data,'json');
    }

    /*  按照条件筛选图片
     *  $num 需求图片张数
     *  $gallery_id 相册id
     *  $category_id 产品类目 （暂时没开启改功能）
     */
    public function marry_pic(){
        $m = M('product_gallery');
        // 参数
        $mounth_1    = 60 * 60 * 24 * 30;    // 一个月时间
        $num         = I('num');   // 产品数量
        $variant_num = I('v_num');     // 变体数量
        $category_id = I('category_id');     // 产品目录
        $gallery_id  = I('gallery_id');      // 图片目录
        $pic_rate    = I('pic_rate');        // 图片比
        $pro_rate    = I('pro_rate');        // 产品比
        $re_date     = I('re_date');         // 可重复使用时间
        $file_type   = I('file_type');       // 格式
        $tag         = I('tag');

        if($variant_num == 0 || $variant_num == ""){ // 根据主题筛选
            $pic_nums = $num;
        }else{
            $pic_nums = ceil($num / $variant_num);
        }
        // 初始化
        $pic_num     = (int)ceil( ( $pic_rate * $pic_nums ) / $pro_rate );   // 需要取的图片数量
        $do_date     = date("Y-m-d H:i:s",(time() - ( $mounth_1 * $re_date )));  // 可用图片的时间限制
        $c_array     = array();                                        // 存放产品类目id的数组
        if(empty($category_id)){
            $data['status'] = 102;
            $data['msg']    = '产品类目选取失败';
            $this->response($data, 'json');
        }
        
        if(empty($gallery_id)){
            $data['status'] = 102;
            $data['msg']    = '图片目录选取失败';
            $this->response($data, 'json');
        }
        $array_count = count($gallery_id);
        if($array_count > C('GALLERY_CONUT')){
            $data['status'] = 105;
            $data['msg']    = '图片目录选取太多';
            $this->response($data, 'json');
        }
        $gaid = implode("," , $gallery_id);

        $typeArr = array('jpg' , 'png' , 'gif' , 'jpeg');
        if(in_array($file_type , $typeArr)){
            $f = "AND file_type='$file_type'";
        }else{
            $f = "";
        }
        if(empty($tag)){
            $tags = "";
        }else{
            $tag_array = explode(",", $tag);
            $tags = "AND (";
            foreach ($tag_array as $key => $value) {
                $tags .= "tags like '%".$value."%' OR ";
            }
            $strlen  = strlen($tags);
            $tags = substr($tags,0,$strlen-3);
            $tags .= ")";
        }
        $result = M()->query("SELECT * FROM marrypic WHERE gallery_id IN($gaid) $f AND rubbish=0 AND u_time<='$do_date' $tags ORDER BY RAND() ASC limit $pic_num");
        if($result){
            $rand_id         = rand(100000,999999);
            $data['status']  = 100;
            $data['rates']   = $pic_rate / $pro_rate;
            $data['value']   = $result;
            $count           = count($result);
            $data['num_now'] = $count;
            $data['count']   = $pic_num;
            $data['rand_id'] = $rand_id;
            if($count < $pic_num){
                $data['not_enough'] = $pic_num - $count;
            }else{
                $data['not_enough'] = 0;
            }
        }else{
            $data['status'] = 101;
            $data['msg']    = '没有图片数据，请及时去上传';
        }
        $this->response($data, 'json');
    }

    // 恢复图片
    public function recover_pic(){
        $gallery_id  = $_POST['gallery_id'];
        $id_arr      = $_POST['id'];
        // 批量恢复判断
        if(!is_array($id_arr)){
            $arr[] = $id_arr;
        }else{
            $arr = $id_arr;
        }
        $arr = array_filter($arr); // 数组去空
        if(is_array($arr) && preg_match("/^[0-9]+$/",$gallery_id)){
            $result = \Think\Product\Picture::recover_pic($gallery_id,$arr);
            if($result['error'] == 0){
                $data['status'] = 100;
                $data['value']  = $result['value'];
            }else{
                $data['status'] = $result['status'];
                $data['msg']    = $result['msg'];
            }
        }else{
            $data['status'] = 102;
            $data['msg']    = '图片类目选取失败';
        }
        $this->response($data,'json');
    }


    public function clear_rubbish()
    {
        $result = \Think\Product\Picture::clear_rubbish();
        if($result['error'] == 0){
            $data['status'] = 100;
        }else{
            $data['status'] = $result['status'];
            $data['msg']    = $result['msg'];
        }
        $this->response($data,'json');
    }

    // 编辑图片标签标题
    public function update_pic_t(){
        $num = I('num');
        if(!empty($num) && preg_match("/^[0-9]+$/",$num)){
            $m = M('product_picture')->order('id desc')->limit($num)->select(); // 查到刚刚添加的数据来修改
            foreach($m as $key => $value){
                foreach($value as $k => $v){
                    if($k == 'tags'){
                        $m[$key][$k] = array_filter(explode("||",$v));
                    }
                }
            }
            $data['value']  = $m;
            $data['status'] = 100;
        }else{
            $data['status'] = 101;
            $data['msg']    = '该类目里面还没有图片类目，需要先创建';
        }
        $this->response($data,'json');
    }

    // 执行编辑操作
    public function do_update_pic_t(){
        $upadte_arr = I('data');
        $m = M('product_picture');
        $sa['modified_time'] = date('Y-m-d H:i:s',time());
        $i = 0;    // 用于统计修改成功的条数
        foreach($upadte_arr as $key => $value){ // 循环修改
            foreach($value as $k => $v){
                if($k == "tags"){
                    $sa['tags']  = implode("||",$v);
                }
            }
            $sa['title'] = $value['title'];
            $s = $m->where(array('id'=>$value['id']))->save($sa);
            if($s){
                $i ++;
            }
        }
        if($i != 0){
            $data['status'] = 100;
            $data['value']  = $i;  // 修改成功$i条
        }else{
            $data['status'] = 101; // 一条也没有修改成功
            $data['msg']    = '数据没有修改成功，请重试';
        }
        $this->response($data,'json');
    }

    // 匹配完图片之后方便前台调用上传
    public function get_cache_pic(){
        $rand  = I('rand_id');
        $key   = I('key');
        $mykey = 'oD~8dyxGS9Az';
        if($key != $mykey || empty($key)){
            $data['status'] = 102;    // 权限不足
            $data['msg']    = '权限不足';
            $this->response($data,'json');
        }
        if(empty($rand)){
            $data['status'] = 102;    // 权限不足
            $data['msg']    = '权限不足';
            $this->response($data,'json');
        }
        $picArr = S("picArr_".$rand);
        if(!empty($picArr)){
            $data['status'] = 100;
            $data['value']  = $picArr;
        }else{
            $data['status'] = 101;  // 还没有匹配图片
            $data['msg']    = '没有匹配图片';
        }
        $this->response($data,'json');
    }

    /*
     * 图片上传准备
     * */
    public function pic_upload_paration(){

        $form_id = I('post.form_id');
        if(!preg_match("/^[0-9]+$/" , $form_id)){
            $this->response(['status' => 102 , 'msg' => '表格选择有误'],'json');exit();
        }
        $m = M('product_batch_information');
        $n = M('product_batch_form_information');

        $p_id = $n->where("form_id = %d" , array($form_id))->field('product_id')->select();
        if(!$p_id){
            $this->response(['status' => 102 , 'msg' => '表格没有数据'],'json');exit();
        }
        $p = [];
        foreach($p_id as $v){
            $p[] = $v['product_id'];
        }
        
        $result = [];
        foreach ($p as $key => $value) {
            $result[] = $m->where("product_id=%d AND data_type_code='pic' AND enabled=1",array($value))->field('id,parent_id,product_id,char_value AS image_url')->select();
        }
        if(!$result){
            $this->response(['status' => 102 , 'msg' => '表格没有数据'],'json');exit();
        }
        foreach($result as $k => $vals){
            foreach($vals as $keys => $val){
                if(!empty($val['image_url']) || ($val['image_url'] != "" && $val['image_url'] != null)){
                    $upload_arr[] = $val;
                }
            }
        }

        if(empty($upload_arr)){
            $this->response(['status' => 102 , 'msg' => '没有匹配图片'],'json');exit();
        }
        $return_arr = pre_arr($upload_arr,$p);
        $this->response(['status' => 100 , 'value' => $return_arr] , 'json');
    }

    //上传图片到图片空间接口
    public function uploadPic(){
        set_time_limit(0);

        $url = 'http://120.25.228.115/ImagesUpload/index.php/Home/Picture/receivePic'; //图片API服务器
        // $tmpName = "147763414410955.jpg"; //上传上来的文件名
        // $tmpFile = './Pictures/Beauty/147763414410955.jpg'; //上传上来的临时存储路径
        // $tmpType = 'jpg'; //上传上来的文件类型

        $form_id = I('post.form_id');
        $countPic = I('post.picCount');
        $text       = file_get_contents("php://input");
        $textdata   = urldecode($text);
        $num = ceil($countPic / 50);
        $j  = 0;
        $nu = 0;
        $i = 0;
        $success = 0;
        $error = 0; 
        $j = 0;
        $s = 0;
        $m = 0;
        $n = 0;
        $in = array();
        for($z = 0; $z < $num; $z ++) {                     // 分包获取传的产品数量
            $b = stripos($textdata, 'picArr[' . $j . ']');
            $j = $j + 50;
            $c = stripos($textdata, 'picArr[' . $j . ']');
            if (empty($c)) {
                $g = substr($textdata, $b);
            } else {
                $g = substr($textdata, $b, $c - $b - 1);
            }
            parse_str($g);
            $pic_data[] = $picArr;
            $picArr = array();
        }

        $pic = array();
        foreach($pic_data as $val){  // 将接收到的多个数据包组合成一个
            foreach($val as $vs){
                $pic[] = $vs;
            }
        }


        foreach ($pic as $keys => $values) {
            foreach ($pic as $k => $vals) {
                if($values['image_url'] == $vals['image_url']){
                    $arrs[$n]['ids'][] = $vals['ids'];
                    $arrs[$n]['image_url'] = $vals['image_url'];
                    unset($pic[$k]);
                    
                    $s++;
                }
            }
            
            $arrs[$n]['num'] = $s;
            $s = 0;
            $n++;
        }
        foreach ($arrs as $ks => $va) {
            if(!empty($va['image_url'])){
                $arrays[] = $va;
            }
        }
        S('PicProgress_'.$form_id,null);
        foreach ($arrays as $key => $valu) {
            $qian = array('http://',$_SERVER['HTTP_HOST'],__ROOT__);
            $hou = array('','','');
            $url = str_replace($qian,$hou,$valu['image_url']);
            $tmpFile = '.'.$url;
            $tmpName = pathinfo($valu['image_url'],PATHINFO_BASENAME);
            $tmpType = pathinfo($valu['image_url'],PATHINFO_EXTENSION);
            \Think\Log::record("图片大小:".ceil(filesize($tmpFile) / 1000) . "k",'DEBUG',true);
                
            //执行上传
            $re = json_decode(imageUpload( $tmpName, $tmpFile, $tmpType, $form_id, $valu['ids'],$valu['num']),true);
            if($re['status'] == 100){
                foreach ($re['value'] as $rekey => $re_value) {
                    $data[$i]['id'] = $re_value['ids'];
                    $data[$i]['image_url'] = $re_value['image_url'];
                    $data[$i]['photo'] = $valu['image_url'];
                    $data[$i]['status_msg'] = $re_value['status_msg'];
                    $i++;
                }
                $success++;
            }else{
                foreach ($valu['ids'] as $id_key => $id_value) {
                    $data[$i]['status_msg'] = '';
                    $data[$i]['msg'] = $re['msg'];
                    $data[$i]['id'] = $id_value;
                    $data[$i]['photo'] = $valu['image_url'];
                    $data[$i]['image_url'] = $valu['image_url'];
                    $i++;
                }
                $error++;
            }
            $cache = S('PicProgress_'.$form_id);
            if(empty($cache['num'])){
                $das['count'] = $countPic;
                $das['num'] = $valu['num'];
                S('PicProgress_'.$form_id,$das);
            }else{
                $das['count'] = $countPic;
                $das['num'] = $valu['num'] + $cache['num'];
                S('PicProgress_'.$form_id,$das);
            }
        }
        $array['status'] = 100;
        S('PicProgress_'.$form_id,null);
        $array['value'] = $data;
        $this->response($array,'json');
    }

    //图片上传进度
    public function getProgress(){
        $form_id = I('post.form_id');
        $data = S('PicProgress_'.$form_id);
        $datas['status'] = 100;
        $num = ($data['num'] / $data['count'])*100;
        $datas['progress'] = sprintf("%.2f", $num).'%';
        $this->response($datas,'json');
    }

    //图片移动
    public function movePicture(){
        $gallery_id = I('post.gallery_id');
        if(empty($gallery_id)){
            $arr['status'] = 102;
            $arr['msg'] = "请选择要移动的图片目录";
            $this->response($arr,'json');
            exit();
        }
        $pic_ids = I('post.pic_ids');
        if(empty($pic_ids)){
            $arr['status'] = 102;
            $arr['msg'] = "请选择要移动的图片";
            $this->response($arr,'json');
            exit();
        }
        if(is_array($pic_ids)){
            $ids = $pic_ids;
        }else{
            $ids[] = $pic_ids;
        }
        $result = \Think\Product\Picture::MovePicture($gallery_id,$pic_ids);
        if($result['success'] != 0){
            updateGalleryCache();
            $arr['status'] = 100;
            $arr['value'] = $result;
        }else{
            $arr['status'] = 101;
            $arr['msg'] = "图片没有移动成功";
        }
        $this->response($arr,'json');
    }

    //批量添加标签
    public function addTags(){
        $pic_ids = I('post.pic_ids');
        $tag = I('post.tag');
        $product_picture = M('product_picture');
        $product_picture->startTrans();
        foreach ($pic_ids as $key => $value) {
            $sql = $product_picture->field("tags")->where("id=%d",array($value))->find();
            if(empty($sql['tags'])){
                $data['tags'] = $tag;
                $add = $product_picture->data($data)->where("id=%d",array($value))->save();
                if($add === 'flase'){
                    $product_picture->rollback();
                    $array['status'] = 101;
                    $array['msg'] = "添加失败";
                    $this->response($array,'json');
                }
            }else{
                $arr = explode("||",$sql['tags']);
                if(!in_array($tag,$arr)){
                    $tags = $sql['tags'].'||'.$tag;
                    $data['tags'] = $tags;
                    $add = $product_picture->data($data)->where("id=%d",array($value))->save();
                    if($add === 'flase'){
                        $product_picture->rollback();
                        $array['status'] = 101;
                        $array['msg'] = "添加失败";
                        $this->response($array,'json');
                    }
                }
            }
        }
        $product_picture->commit();
        $array['status'] = 100;
        $this->response($array,'json');
    }
}