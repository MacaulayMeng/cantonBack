<?php
namespace Home\Controller;
use Think\Controller;
/**
* 产品资料扩展控制器
* @author lrf
* @modify 2016/12/22
*/
class ProductInfoExtendController extends BaseController
{
	protected $dt   = "/^([1][7-9]{1}[0-9]{1}[0-9]{1}|[2][0-9]{1}[0-9]{1}[0-9]{1})(-)([0][1-9]{1}|[1][0-2]{1})(-)([0-2]{1}[1-9]{1}|[3]{1}[0-1]{1})*$/";
    protected $dt1  = "/^([1][7-9][0-9][0-9]|[2][0][0-9][0-9])(\.)([0][1-9]|[1][0-2])(\.)([0-2][1-9]|[3][0-1])*$/";
    protected $dt2  = "/^([1][7-9][0-9][0-9]|[2][0][0-9][0-9])([0][1-9]|[1][0-2])([0-2][1-9]|[3][0-1])*$/";
    protected $dt3  = "/^([1][7-9][0-9][0-9]|[2][0][0-9][0-9])(\/)([0][1-9]|[1][0-2])(\/)([0-2][1-9]|[3][0-1])*$/";

    /*
	 * 获取模板的数据格式
     * @param template_id 模板id
     * @param type_code  资料表或者批量表 
	 */
	public function getTemFormat(){
		$template_id = (int)I('post.template_id');
		$type_code   = I('post.type_code');
        if($type_code != 'info' && $type_code != 'batch') $this->response(['status'=> 119, 'msg' => '系统错误']);

		if($template_id == 0){
			$arr['status'] = 102;
			$arr['msg'] = "模板信息错误";
			$this->response($arr);
		}
		$res = \Think\Product\ProductInfoExtend::GetTemplateFormat($template_id,$type_code);
		$this->response($res);
	}

	/*
	 * 改版的数据提交与暂存
     * @param form_id 表格id
     * @param template_id 模板id
     * @param category_id 类目id
     * @param type_code 判断是资料表（info） 或者批量表（batch）
     * @param type 判断是暂存或者提交
     * @param max 所有产品的数量
     * @param gridColumns   表头数据
     * @param text post的所有数据
	 */
	public function dataCommit()
    {
        set_time_limit(0);
		$form_id     = I('post.form_id');
		$template_id = I('post.template_id');
		$category_id = I('post.category_id');
		$type_code   = I('post.type_code');
		$type        = I('post.save_type');     // 暂存或者提交
		$max         = I('post.max');
        $gridColumns = I('post.gridColumns');
		$text        = file_get_contents("php://input");
        $textdata    = urldecode($text);

		if($type_code != 'info' && $type_code !='batch') $this->response(['status' => 119, 'msg' => "系统错误"]);
		if(empty($template_id)){
			$arr['status'] = 102;
			$arr['msg'] = "模板信息错误";
			$this->response($arr);
		}
		if(empty($form_id)){
			$arr['status'] = 102;
			$arr['msg'] = "表格信息错误";
			$this->response($arr);
		}
		if(empty($category_id)){
			$arr['status'] = 102;
			$arr['msg'] = "类目信息错误";
			$this->response($arr);
		}

		if($type_code == 'info'){
			$item = M('product_item_template');
			$info = M('product_information');
			$form = M('product_form_information');
			$types = M('product_form');
            $code = 'product_information_record';//应用代码，将用于获取全局产品记录id
            $n = 10;
        }else {
        	$item = M('product_batch_item_template');
        	$info = M('product_batch_information');
        	$form = M('product_batch_form_information');
        	$types = M('product_batch_form');
            $code = 'product_batch_information_record';
            $n = 1;
        }
        $num  = ceil( $max / $n );

        $j = 0;
        for($z = 0; $z < $num; $z ++) {     // 分包获取传的产品数量
            $b = stripos($textdata, 'gridData[' . $j . ']');
            $j = $j + $n;
            $c = stripos($textdata, 'gridData[' . $j . ']');
            if (empty($c)) {
                $g = substr($textdata, $b);
            } else {
                $g = substr($textdata, $b, $c - $b - 1);
            }
            parse_str($g);
            $pro_data[] = $gridData;
            $gridData = array();
        }

        $info->startTrans();
       	$sql = $item->field("en_name,no,data_type_code,length,precision")->where("template_id=%d",array($template_id))->select();
       	foreach ($sql as $key => $value) {
       		$data_style[$value['en_name']]['no'] = $value['no'];
       		$data_style[$value['en_name']]['data_type_code'] = $value['data_type_code'];
       		$data_style[$value['en_name']]['length'] = $value['length'];
       		$data_style[$value['en_name']]['precision'] = $value['precision'];
       	}

       	$m = 0;
        //找出多少是新添加的
       	foreach ($pro_data as $k => $va) {
            foreach ($va as $vkey => $v_data) {
                if($v_data[array_search('types',$gridColumns)] == 'yes'){
                    $m++;
                }
            }

       	}
       	$newdata = $m*count($data_style);
       	if($newdata > 0){
       		$id = GetSysId($code,$newdata);
       	}

       	$i = 0;

        //数据写入数据库
        foreach ($pro_data as $keys => $values) {
        	foreach ($values as $k => $valu) {
                $product_id = $valu[array_search('product_id',$gridColumns)];
                $parent_id = $valu[array_search('parent_id',$gridColumns)];
                $ty = $valu[array_search('types',$gridColumns)];
                foreach ($valu as $ke => $val) {
                    $value_key = $gridColumns[$ke];
                    if(!array_key_exists($value_key, $data_style)){
                        continue;
                    }
                    switch ($data_style[$value_key]['data_type_code']) {
                        case 'int':
                            $data_type = 'interger_value';
                            if(!empty($val)){
                                if(!preg_match("/^[0-9]*$/", $val)){
                                    $info->rollback();
                                    $array['status'] = 103;
                                    $array['msg']    = '整数数据类型填写错误';
                                    $this->response($array);
                                    exit();
                                }
                            }

                          break;
                        case 'char':
                            $data_type = 'char_value';
                            if(!empty($val)){
                                $nums = strlen(trim($val));
                                if ($nums > $data_style[$value_key]['length']) {
                                    $info->rollback();
                                    $array['status'] = 106;
                                    $array['msg']    = '字符数据类型填写错误';
                                    $this->response($array);
                                    exit();
                                }
                            }
                          break;
                        case 'dc':
                            $data_type = 'decimal_value';
                            if(!empty($val)){
                                if (!preg_match("/^(\d*\.)?\d+$/", $val)) {
                                    $info->rollback();
                                    $array['status'] = 104;
                                    $array['msg'] = '小数数据类型填写错误';
                                    $this->response($array);
                                    exit();
                                }
                            }
                          break;
                        case 'dt':
                            $data_type = 'date_value';
                            if(!empty($val)){
                                if (preg_match($this->dt, $val) || preg_match($this->dt1, $val) ||
                                    preg_match($this->dt2, $val) || preg_match($this->dt3, $val)) {
                                    $info->rollback();
                                    $array['status'] = 105;
                                    $array['msg']    = '日期数据类型填写错误';
                                    $this->response($array);
                                    exit();
                                }
                            }
                          break;
                        case 'bl':
                            $data_type = 'boolean_value';
                          break;
                        case 'upc_code':
                            $data_type = 'char_value';
                          break;
                        case 'pic':
                            $data_type = 'char_value';
                          break;
                    }
                    if(empty($val)){
                        $valss = null;
                    }else{
                        $valss = $val;
                    }
                    $data[$data_type] = $valss;
                    $data['modified_time'] = date('Y-m-d H:i:s',time());
                    if(empty($ty) || $ty != 'yes'){
                        $where['product_id'] = $product_id;
                        $where['title'] = $value_key;
                        $query = $info->data($data)->where($where)->save();
                        if($query === 'flase'){
                            $info->rollback();
                            $arr['status'] = 101;
                            $arr['msg'] = "提交或者暂存失败";
                            $this->response($arr);
                            exit();
                        }
                        $data = array();
                    }else{
                        $data['id'] = $id[$i];
                        $data['category_id']    = $category_id;
                        $data['template_id']    = $template_id;
                        $data['product_id']     = $product_id;
                        $data['parent_id']      = $parent_id;
                        $data['no']             = $data_style[$value_key]['no'];
                        $data['title']          = $value_key;
                        $data['data_type_code'] = $data_style[$value_key]['data_type_code'];
                        $data['length']         = $data_style[$value_key]['length'];
                        $data['precision']      = $data_style[$value_key]['precision'];
                        $data['created_time']   = date('Y-m-d H:i:s',time());
                        $query = $info->data($data)->add();
                        if($query === 'flase'){
                            $info->rollback();
                            $arr['status'] = 101;
                            $arr['msg'] = "提交或者暂存失败";
                            $this->response($arr);
                            exit();
                        }
                        $i++;
                        $data = array();
                    }

                }
                if($pro_data[$keys][$k][array_search('types',$gridColumns)] == 'yes'){
                    $datas['form_id'] = $form_id;
                    $datas['product_id'] = $product_id;
                    $datas['created_time'] = date('Y-m-d H:i:s',time());
                    $oper = $form->data($datas)->add();
                    if(!$oper){
                        $info->rollback();
                        $arr['status'] = 101;
                        $arr['msg'] = "提交或者暂存失败";
                        $this->response($arr);
                        exit();
                    }
                }
            }
        }
        //提交就修改表格状态
        if($type == 'submit'){
            $status_code['status_code'] = 'editing';
            $types->where('id=%d',array($form_id))->data($status_code)->save();
        }
        $info->commit();
        $arr['status'] = 100;
       	$this->response($arr);
	}

}