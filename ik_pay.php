<?php
if($_SERVER['REQUEST_METHOD']!='POST') die();

require $_SERVER['DOCUMENT_ROOT'] . '/ami_env.php';

//$oResponse = AMI::getSingleton('response');
//$oResponse->HTTP->setContentType('text/plain');
//$oResponse->start();
//$oResponse->write('Hello world!');
//$oResponse->send();

//$obj = AMI::getModId('eshop');
//$obj = AMI::getModId('pay_drivers');
//$obj = AMI::getModId('pay_drivers');

//$val = DB_Query::getSnippet("SELECT `settings` FROM `cms_pay_drivers` WHERE name = %s")->q('interkassa');
//$value = AMI::getSingleton('db')->fetchValue($val);

//echo '<pre>';
////var_dump($obj);
//var_dump(
////	$val,
//	unserialize($value)

//	AMI::getSingleton('db')->select($val)
//	AMI::getSingleton('db')->select("SELECT `settings` FROM `cms_pay_drivers` WHERE `name` = 'interkassa'");
//	AMI::getSingleton('db')->fetchValue("SELECT `settings` FROM `cms_pay_drivers` WHERE `name` = 'interkassa'")
//);
//
//echo '</pre>';

//require_once __DIR__ . '/_shared/code/classes/bill_driver.php';
//require_once __DIR__ . '/_local/eshop/pay_drivers/interkassa/driver.php';
//$ikObj = AMI::getResource('Interkassa_PaymentSystemDriver');
//$ikObj = AMI::getSingleton('Interkassa_PaymentSystemDriver');

//var_dump($ikObj);
//exit;

$val = DB_Query::getSnippet("SELECT `settings` FROM `cms_pay_drivers` WHERE name = %s")->q('interkassa');
$value = AMI::getSingleton('db')->fetchValue($val);
$ikConf = unserialize($value);

//print_r($ikConf);


define('ikUrlSCI', 'https://sci.interkassa.com/');
define('ikUrlAPI', 'https://api.interkassa.com/v1/');

function IkSignFormation($data, $secret_key){
	if (!empty($data['ik_sign'])) unset($data['ik_sign']);

	$dataSet = array();
	foreach ($data as $key => $value) {
		if (!preg_match('/ik_/', $key)) continue;
		$dataSet[$key] = $value;
	}

	ksort($dataSet, SORT_STRING);
	array_push($dataSet, $secret_key);
	$arg = implode(':', $dataSet);
	$ik_sign = base64_encode(md5($arg, true));

	return $ik_sign;
}

function getAnswerFromAPI($data){
	$ch = curl_init(ikUrlSCI);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	return $result;
}

function getIkPaymentSystems($ik_cashbox_id = '', $ik_api_id = '', $ik_api_key = '')
{
	$username = $ik_api_id;
	$password = $ik_api_key;
	$remote_url = ikUrlAPI . 'paysystem-input-payway?checkoutId=' . $ik_cashbox_id;

	$businessAcc = getIkBusinessAcc($username, $password);

	$ikHeaders = [];
	$ikHeaders[] = "Authorization: Basic " . base64_encode("$username:$password");
	if(!empty($businessAcc)) {
		$ikHeaders[] = "Ik-Api-Account-Id: " . $businessAcc;
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $remote_url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $ikHeaders);
	$response = curl_exec($ch);

	$json_data = json_decode($response);

	if(empty($response))
		return '<strong style="color:red;">Error!!! System response empty!</strong>';

	if ($json_data->status != 'error') {
		$payment_systems = array();
		if(!empty($json_data->data)){
			foreach ($json_data->data as $ps => $info) {
				$payment_system = $info->ser;
				if (!array_key_exists($payment_system, $payment_systems)) {
					$payment_systems[$payment_system] = array();
					foreach ($info->name as $name) {
						if ($name->l == 'en') {
							$payment_systems[$payment_system]['title'] = ucfirst($name->v);
						}
						$payment_systems[$payment_system]['name'][$name->l] = $name->v;
					}
				}
				$payment_systems[$payment_system]['currency'][strtoupper($info->curAls)] = $info->als;
			}
		}

		return !empty($payment_systems)? $payment_systems : '<strong style="color:red;">API connection error or system response empty!</strong>';
	} else {
		if(!empty($json_data->message))
			return '<strong style="color:red;">API connection error!<br>' . $json_data->message . '</strong>';
		else
			return '<strong style="color:red;">API connection error or system response empty!</strong>';
	}
}

function getIkBusinessAcc($username = '', $password = '')
{
	$tmpLocationFile = __DIR__ . '/tmpLocalStorageBusinessAcc.ini';
	$dataBusinessAcc = function_exists('file_get_contents')? file_get_contents($tmpLocationFile) : '{}';
	$dataBusinessAcc = json_decode($dataBusinessAcc, 1);
	$businessAcc = is_string($dataBusinessAcc['businessAcc'])? trim($dataBusinessAcc['businessAcc']) : '';
	if(empty($businessAcc) || sha1($username . $password) !== $dataBusinessAcc['hash']) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, ikUrlAPI . 'account');
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Basic " . base64_encode("$username:$password")]);
		$response = curl_exec($curl);

		if (!empty($response['data'])) {
			foreach ($response['data'] as $id => $data) {
				if ($data['tp'] == 'b') {
					$businessAcc = $id;
					break;
				}
			}
		}

		if(function_exists('file_put_contents')){
			$updData = [
					'businessAcc' => $businessAcc,
					'hash' => sha1($username . $password)
			];
			file_put_contents($tmpLocationFile, json_encode($updData, JSON_PRETTY_PRINT));
		}

		return $businessAcc;
	}

	return $businessAcc;
}


if(isset($_GET['nYg'])){
	switch ($_GET['nYg']) {
		case 'nYs':
			$tmp = json_encode(array('sign' => IkSignFormation($_POST, $ikConf['secret_key'])));
			echo $tmp;
			break;
		case 'nYa':
			$sign = IkSignFormation($_POST, $ikConf['secret_key']);
			$_POST['ik_sign'] = $sign;
			$tmp = getAnswerFromAPI($_POST);
			echo json_encode($tmp);
			break;
		default:
			break;
	}
	exit;
}


$params = array(
	'ik_am'           => $_POST['ik_am'],
	'ik_pm_no'        => $_POST['ik_pm_no'],
	'ik_desc'         => $_POST['ik_desc'],
	'ik_cur'          => $_POST['ik_cur'],
	'ik_co_id'        => $_POST['ik_co_id'],
	'ik_suc_u'        => $_POST['ik_suc_u'],
	'ik_fal_u'        => $_POST['ik_fal_u'],
	'ik_pnd_u'        => $_POST['ik_pnd_u'],
	'ik_ia_u'         => $_POST['ik_ia_u'],
	'ik_sign'         => $_POST['ik_sign']
);

$paySystems = ($ikConf['api_mode'] == 'on')? getIkPaymentSystems($ikConf['ik_co_id'], $ikConf['api_id'], $ikConf['api_key']) : '';
?>
<html>
<head>
	<meta charset="utf-8">
	<title>Выбор платёжной системы</title>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<script>
		ik_err_notslctcurr='Вы не выбрали валюту';
		<?php if($ikConf['api_mode'] !== 'on'){?>
		document.onload=function(){document.forms[0].submit()}
		<?php }?>
	</script>
	<script src="/_local/eshop/pay_drivers/interkassa/assets/ik.js"></script>

	<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
	<link href="/_local/eshop/pay_drivers/interkassa/assets/ik.css" rel="stylesheet">
</head>
<body>
<div class="ik_block">
	<form action='https://sci.interkassa.com/' method="POST" name="vm_interkassa_form" id="ikform">
		<?
		foreach($params as $k=>$v) echo '<input type="hidden" name="'.$k.'" value="'.$v.'">';
		?>
	</form>
	<img src="_local/eshop/pay_drivers/interkassa/assets/logo.png" width="50%"><br>
	<button onclick="selpayIK.selPaysys()" class="btn btn-primary">Оплатить</button>
	<?php if(!empty($paySystems) && is_array($paySystems)){ ?>
	<button type="button" class="btn btn-info btn-lg sel-ps-ik" data-toggle="modal" data-target=".ik_modal" style="display:none">Оплатить</button>
	<div class="modal fade ik_modal" tabindex="-1" role="dialog">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content" id="plans">
				<div class="modal-body">
					<h1>1. Выберите удобный способ оплаты<br>2. Укажите валюту<br>3. Нажмите 'Оплатить'</h1>
					<div class="row">
						<?foreach($paySystems as $ps => $info){
							if($ps!='test'||($ps=='test' && $_POST['ik_nJt']=='test'))?>
							<div class="col-sm-3 text-center payment_system">
							<div class="panel panel-warning panel-pricing">
							<div class="panel-heading">
							<img src="/_local/eshop/pay_drivers/interkassa/assets/paysystems/<?=$ps?>.png" alt="<?=$info['title']?>">
							<!--<h3><?=$info['title']?></h3>-->
							</div>
							<div class="form-group">
								<div class="input-group">
									<div id="radioBtn" class="btn-group radioBtn">
										<?php foreach ($info['currency'] as $currency => $currencyAlias) { ?>
											<a class="btn btn-primary btn-sm notActive" data-toggle="fun"
												   data-title="<?php echo $currencyAlias; ?>"><?php echo $currency; ?></a>
										<?php } ?>
									</div>
									<input type="hidden" name="fun" id="fun">
								</div>
							</div>
							<div class="panel-footer">
								<a class="btn btn-block btn-success ik-payment-confirmation" data-title="<?=$ps?>" href="#">Оплатить с
									<br>
									<strong><?=$info['title']?></strong>
								</a>
							</div>
							</div>
							</div>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php } else {
		echo $paySystems;
	}
	?>
</div>
</body>
</html>
