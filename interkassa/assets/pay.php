<?php if($_SERVER['REQUEST_METHOD']!='POST')die();

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
  $ch = curl_init('https://sci.interkassa.com/');
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $result = curl_exec($ch);
  return $result;
}
if(isset($_GET['nYg']))switch ($_GET['nYg']) {
  case 'nYs':
    $tmp = json_encode(array('sign'=>IkSignFormation($_POST,$_GET['nYsk'])));
    echo $tmp;
die();
  case 'nYa':
    $sign = IkSignFormation($_POST,$_GET['nYsk']);
    $_POST['ik_sign'] = $sign;
    $tmp = getAnswerFromAPI($_POST);
    echo json_encode($tmp);
  default:
  die();
}

function getIkPaymentSystems()
{
  $username = $_POST['ik_nJi'];
  $password = $_POST['ik_nJk'];
  $remote_url = 'https://api.interkassa.com/v1/paysystem-input-payway?checkoutId=' . $_POST['ik_co_id'];

  // Create a stream
  $opts = array(
    'http' => array(
      'method' => "GET",
      'header' => "Authorization: Basic " . base64_encode("$username:$password")
    )
  );

  $context = stream_context_create($opts);
  $file = file_get_contents($remote_url, false, $context);
  $json_data = json_decode($file);
  #file_put_contents(__DIR__.'/gg.txt',$json_data->status,FILE_APPEND);
  if($json_data->status != 'error'){

    $payment_systems = array();
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
    return $payment_systems;
  }else{
    echo '<strong style="color:red;">API connection error!<br>'.$json_data->message.'</strong>';
  }
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
?>
<html>
	<head>
		<title>Выбор платёжной системы</title>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
		<script>
			ik_nYsk='<?php echo $_POST['ik_nJs'];?>';
			ik_err_notslctcurr='Вы не выбрали валюту';
      <?php if($_POST['ik_nJm']=='off'){?>
        document.onload=function(){document.forms[0].submit()}
      <?php }?>
		</script>
		<script src="ik.js"></script>

		<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
		<link href="ik.css" rel="stylesheet">
	</head>
	<body>
		<div class="ik_block">
			<form action='https://sci.interkassa.com/' method="POST" name="vm_interkassa_form" id="ikform"><?
foreach($params as $k=>$v) echo '<input type="hidden" name="'.$k.'" value="'.$v.'">';
	    ?></form>
	    <button onclick="document.forms.vm_interkassa_form.submit()" class="btn btn-primary" style="display:none">Подтвердить</button>
			<img src="logo.png" width="50%"><br>
			<button type="button" class="btn btn-info btn-lg" data-toggle="modal" data-target=".ik_modal">Выбрать платежную систему</button>
			<div class="modal fade ik_modal" tabindex="-1" role="dialog">
			  <div class="modal-dialog modal-lg" role="document">
			    <div class="modal-content" id="plans">
						<div class="modal-body">
				      <h1>1. Выберите удобный способ оплаты<br>2. Укажите валюту<br>3. Нажмите 'Оплатить'</h1>
							<div class="row"><?foreach(getIkPaymentSystems() as $ps=>$info): if($ps!='test'||($ps=='test' && $_POST['ik_nJt']=='test'))?>
								<div class="col-sm-3 text-center payment_system">
									<div class="panel panel-warning panel-pricing">
										<div class="panel-heading">
											<img src="paysystems/<?=$ps?>.png" alt="<?=$info['title']?>">
											<!--<h3><?=$info['title']?></h3>-->
										</div>
										<div class="form-group">
											<div class="input-group">
												<div id="radioBtn" class="btn-group radioBtn">
													<?php foreach ($info['currency'] as $currency => $currencyAlias) { ?>
														<?php if ($currency == $shop_cur) { ?>
															<a class="btn btn-primary btn-sm active" data-toggle="fun"
															data-title="<?php echo $currencyAlias; ?>"><?php echo $currency; ?></a>
															<?php } else { ?>
																<a class="btn btn-primary btn-sm notActive" data-toggle="fun"
																data-title="<?php echo $currencyAlias; ?>"><?php echo $currency; ?></a>
																<?php } ?>
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
							<?endforeach?></div>
						</div>
			    </div>
			  </div>
			</div>
		</div>
	</body>
</html>
