<?php
/**
 * @CMS_Amiro_version 7.0.2.0
 * @driver_version 1.3
 * @author GateON
 * @E-mail www@smartbyte.pro
 * @update_date 18.10.2016
 */

class Interkassa2_PaymentSystemDriver extends AMI_PaymentSystemDriver{

    protected $driverName = 'Interkassa2';


    function format_amount(&$amount)
    {
        $amount = number_format($amount, 2, ".", "");
    }

    public function getPayButton(array &$aRes, array $aData, $bAutoRedirect = false){
        $res =true;
        $aRes["error"] = "Success";
        $aRes["errno"] = 0;
        $data = $aData;
        if(empty($data["process_url"])){
            $aRes["errno"] = 1;
            $aRes["error"] = "process_url is missed";
            $res =false;
        }
        else if(empty($data["ik_co_id"])){
            $aRes["errno"] = 2;
            $aRes["error"] = "ik_co_id is missed";
            $res =false;
        }
        else if(empty($data["payment_url"])){
            $aRes["errno"] = 3;
            $aRes["error"] = "payment_url is missed";
            $res =false;
        }
        else if(empty($data["amount"])){
            $aRes["errno"] = 4;
            $aRes["error"] = "amount is missed";
            $res =false;
        }
        else if(empty($data["button_name"]) && empty($data["button"])){
            $aRes["errno"] = 5;
            $aRes["error"] = "button is missed";
            $res =false;
        }

        $this->format_amount($data["amount"]);
        foreach(Array("return", "cancel", "description", "button_name", "payment_url") as $fldName){
            $data[$fldName] = htmlspecialchars($data[$fldName]);
        }
        if(isset($data["process_url"]))
            unset($data["process_url"]);
        if(isset($data["return"]))
            unset($data["return"]);
        if(isset($data["callback"]))
            unset($data["callback"]);
        if(isset($data["cancel"]))
            unset($data["cancel"]);
        if(isset($data["amount"]))
            unset($data["amount"]);
        if(isset($data["description"]))
            unset($data["description"]);
        if(isset($data["button_name"]))
            unset($data["button_name"]);
        if(isset($data["button"]))
            unset($data["button"]);
        foreach($data as $key => $value){
            $aData["hiddens"] .= "<input type=\"hidden\" name=\"$key\" value=\"$value\">\r\n";
        }
        $aData["button"] = trim($aData["button"]);
        if(!empty($aData["button"]))
        {
            $aData["_button_html"] =1;
        }
        if(!$res)
        {
            $aData["disabled"] ="disabled";
        }



        return parent::getPayButton($aRes, $aData, $bAutoRedirect);
    }

    public function getPayButtonParams(array $aData, array &$aRes){

        if($aData['currency'] == 'RUR'){
            $aData['currency'] = 'RUB';
        }

        // Check parameters and set your fields here
        $data =$aData;
        $aRes["error"] ="Success";
        $aRes["errno"] =0;
        if(empty($data["ik_co_id"])){
            $aRes["errno"] = 2;
            $aRes["error"] = "ik_co_id is missed";
            $res =false;
        }
        else if(empty($data["payment_url"])){
            $aRes["errno"] = 3;
            $aRes["error"] = "payment_url is missed";
            $res =false;
        }
        else if(empty($data["amount"])){
            $aRes["errno"] = 4;
            $aRes["error"] = "amount is missed";
            $res =false;
        }

        $data['payment_url'] = "https://".$data['payment_url']."/assistant.htm";
        if ($data['currency'] == 'RUR')
        {
            $data['currency'] = 'RUB';
        }

        $data['amount'] = sprintf('%.2f', $data['amount']);

        $arg = array(
            'ik_cur'=>$aData['currency'],
            'ik_co_id'=>$aData['ik_co_id'],
            'ik_pm_no'=>$aData['order_id'],
            'ik_am'=>$aData['amount'],
            'ik_desc'=>$aData['order_id'],
            'ik_fal_u'=>$aData['cancel'],
            'ik_suc_u'=>$aData['return'],
            'ik_pnd_u'=>$aData['cancel'],
            'ik_ia_u'=>$aData['callback']
        );

        //Формируем цифровую подпись для отправки на Интеркассу
        $dataSet = $arg;
        ksort($dataSet, SORT_STRING);
        array_push($dataSet, $aData['secret_key']);
        $signString = implode(':', $dataSet);
        $sign = base64_encode(md5($signString, true));

        $data['ik_sign'] = $sign;
        $aData['ik_sign'] = $sign;
        foreach(Array("payment_url", "return", "cancel") as $fldName){
            $data[$fldName] = htmlspecialchars($data[$fldName]);
        }


        return parent::getPayButtonParams($data, $aRes);
    }

    public function payProcess(array $aGet, array $aPost, array &$aRes, array $aCheckData, array $aOrderData){
        // See implplementation of this method in parent class
        $status ='fail';
        if(!@is_array($aGet))
            $aGet =Array();
        if(!@is_array($aPost))
            $aPost =Array();
        $aParams =array_merge($aGet, $aPost);
        if(!empty($aParams['status']))
            $status =$aParams['status'];
        return ($status == "ok");
    }

    public function payCallback(array $aGet, array $aPost, array &$aRes, array $aCheckData, array $aOrderData){

        $this->wrlog('#####################START#####################');
        foreach ($aPost as $key => $value){
            $this->wrlog($key .'=>'. $value);
        }
        $this->wrlog('######################END#######################');
        $this->wrlog('####################CheckData###################');
        foreach ($aCheckData as $key => $value){
            $this->wrlog($key .'=>'. $value);
        }
        $this->wrlog('##################ENDCheckData##################');


        $status = "fail";
        if($this->checkIP()){
            if(!empty($aPost)){
                if ($aCheckData['ik_co_id'] == $aPost['ik_co_id']) {

                    if ($aPost['ik_inv_st'] == 'success') {

                        $this->wrlog('success');

                        if (isset($aPost['ik_pw_via']) && $aPost['ik_pw_via'] == 'test_interkassa_test_xts') {
                            $secret_key = $aCheckData['test_key'];
                        } else {
                            $secret_key = $aCheckData['secret_key'];
                        }

                        $request_sign = $aPost['ik_sign'];

                        $dataSet = [];

                        foreach ($aPost as $key => $value) {
                            if (!preg_match('/ik_/', $key)) continue;
                            $dataSet[$key] = $value;
                        }


                        unset($dataSet['ik_sign']);
                        ksort($dataSet, SORT_STRING);
                        array_push($dataSet, $secret_key);
                        $signString = implode(':', $dataSet);
                        $sign = base64_encode(md5($signString, true));


                        if ($request_sign != $sign) {
                            $this->wrlog('Подписи не совпадают!');

                        } else {
                            $this->wrlog('Подписи совпадают!');

                            $status = 'ok';

                        }
                    }

                } else {
                    $this->wrlog('params didnt match');
                }
            }
        }
        return $status == "ok" ?1 :0;
    }

    public function getProcessOrder(array $aGet, array $aPost, array &$aRes, array $aAdditionalParams){
        $orderId =0;
        if(!empty($aGet["ik_co_id"]))
            $orderId =$aGet["ik_pm_no"];
        if(!empty($aPost["ik_pm_no"]))
            $orderId =$aPost["ik_pm_no"];
        return intval($orderId);
    }

    public static function getOrderIdVarName(){
        return 'ik_pm_no';
    }

    public function onPaymentConfirmed($orderId)
    {
        header('Status: 200 OK');
        header('HTTP/1.0 200 OK');
        die('SUCCESS');
    }

    public function checkIP(){
        $ip_stack = array(
            'ip_begin'=>'151.80.190.97',
            'ip_end'=>'151.80.190.104'
        );

        if(!ip2long($_SERVER['REMOTE_ADDR'])>=ip2long($ip_stack['ip_begin']) && !ip2long($_SERVER['REMOTE_ADDR'])<=ip2long($ip_stack['ip_end'])){
            $this->wrlog('REQUEST IP'.$_SERVER['REMOTE_ADDR'].'doesnt match');
            die('Ты мошенник! Пшел вон отсюда!');
        }
        return true;
    }

    public function wrlog($content){
        $file = 'log.txt';
        $doc = fopen($file, 'a');
        file_put_contents($file, PHP_EOL .'===================='.date("H:i:s").'=====================', FILE_APPEND);
        file_put_contents($file, PHP_EOL . $content, FILE_APPEND);
        fclose($doc);
    }

}
