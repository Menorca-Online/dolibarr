<?php
function validateInvoice($id){
	global $URL, $DOLIBAR_KEY;

	$URL_TO_SEND = $URL.'/invoices/'.$id.'/validate';
	echo $URL_TO_SEND . "\n";

	$curl = curl_init();
	$options = array(
		CURLOPT_URL => $URL_TO_SEND,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS =>'',
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'DOLAPIKEY: '.$DOLIBAR_KEY
		),
	);

	curl_setopt_array($curl, $options);


	$response = curl_exec($curl);
	$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ($httpcode == 200) {
		echo "Factura validada con ID: " . $id . "\n";
		return true;
	} else {
		$response = json_decode($response);
		if (isset($response->error)) {
			$response = $response->error;
		}
		echo "Error al validar la factura con ID: " . $id . ". Código HTTP: " . $httpcode . "\n" . $response . "\n";
	}
	curl_close($curl);
	return false;

}

function confirmPayment($id, $paymentId, $paymentReference)
{
	global $URL, $DOLIBAR_KEY, $ACCOUNT_ID;

	$URL_TO_SEND = $URL.'/invoices/'.$id.'/payments/';


	$curl = curl_init();
	$options = array(
		CURLOPT_URL => $URL_TO_SEND,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS =>'{
			"datepaye": "'.date('Y-m-d').'",
			"paymentid": '.$paymentId.',
			"closepaidinvoices": "yes",
			"accountid": '.$ACCOUNT_ID.',
			"num_payment": "'.$paymentReference.'",
			"comment": "Pago confirmado automáticamente a traves de shuttlespaintransfers.com."
		}',
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'DOLAPIKEY: '.$DOLIBAR_KEY
		),
	);


	curl_setopt_array($curl, $options);
	$response = curl_exec($curl);
	$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ($httpcode == 200) {
		echo "Pago confirmado para la factura con ID: " . $id . "\n";
	} else {
		$response = json_decode($response);
		if (isset($response->error)) {
			$error_message = json_encode($response->error);
			echo "Error al confirmar el pago para la factura con ID: " . $id . ". Código HTTP: " . $httpcode . "\n" . $error_message . "\n";
		}
	}
	curl_close($curl);
}


function sendInvoiceGenerica($email, $booking, $price)
{
	global $URL, $DOLIBAR_KEY, $CLIENTE_GENERICO, $TIPO_FACTURA_SIMPLIFICADA, $PRODUCTO_TRANSFER, $CLAVE_REGIMEN, $CLAVE_OPERACION, $CLAVE_EXENCION, $PAYMENT_TYPES, $ACCOUNT_ID;

	$tax = 21.00;
	$description = "Servicio de transporte desde Palma a Menorca para " . rand(1,10) . " pasajeros. Reserva: " . $booking;

	$options = array(
		CURLOPT_URL => $URL.'/invoices/',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS =>'{
			"socid": '.$CLIENTE_GENERICO.',
			"note_private": "'.$email.'",
			"ref_client": "'.$booking.'",
			"array_options": {
				"options_fk_facture_type": "'.$TIPO_FACTURA_SIMPLIFICADA.'"
			},
			"lines": [
			{
				"fk_product": '.$PRODUCTO_TRANSFER.',
				"qty": 1,
				"subprice": '.$price.',
				"tva_tx": '.$tax.',
				"desc": "'.$description.'",
				"array_options": {
						"options_fk_clave_regimen": "'.$CLAVE_REGIMEN.'",
						"options_fk_clave_operacion": "'.$CLAVE_OPERACION.'",
						"options_fk_clave_exencion": "'.$CLAVE_EXENCION.'"
					}
			}
			]

		}',
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'DOLAPIKEY: '.$DOLIBAR_KEY
		),
	);

	curl_setopt_array($curl, $options);

	$response = curl_exec($curl);
	$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ($httpcode == 200) {
		$id = $response;
		if (validateInvoice($id))
		{
			$paymentType = array_rand($PAYMENT_TYPES);
			$paymentName = $PAYMENT_TYPES[$paymentType];
			$paymentReference = strtoupper(substr($paymentName,0,3)).'-'.rand(1000,9999).'-'.$id;
			confirmPayment($id, $paymentType, $paymentReference);
		}
	} else {
		echo "Error al crear la factura para el email: " . $email . ". Código HTTP: " . $httpcode . "\n";
	}

	curl_close($curl);

}




$CLIENTE_GENERICO = 2;
$TIPO_FACTURA_SIMPLIFICADA = 2;

$TIPO_FACTURA_NOMINATIVA = 1;

$PRODUCTO_TRANSFER = 1;

$ACCOUNT_ID = 1;
$CLAVE_REGIMEN = 1; // 01: Operación de régimen general.
$CLAVE_OPERACION = 1 ; // S1: Operación Sujeta y No exenta - Sin inversión del sujeto pasivo.
$CLAVE_EXENCION = null; // No aplica exención.
$TAX = 21.00;

$PAYMENT_TYPES = [
	4 => 'Efectivo',
	6 => 'Tarjeta',
	105 => 'Paypal'
];


$tests = [
	'test001@verifactu.com' =>  ['shuttle-booking 001' => 114.876033], //139 con IVA 21%
	'test002@verifactu.com' =>  ['shuttle-booking 002' => 123.140495], //149 con IVA 21%
	'test003@verifactu.com' =>  ['shuttle-booking 003' => 135.520661], //163.98 con IVA 21%
	'test004@verifactu.com' =>  ['shuttle-booking 004' => 124.380165], //150.50 con IVA 21%
	'test005@verifactu.com' =>  ['shuttle-booking 005' => 170.247107], //206 con IVA 21%
	'test006@verifactu.com' =>  ['shuttle-booking 006' => 198.347107], //240 con IVA 21%
];


foreach ($tests as $email => $bookingData) {


	$booking = key($bookingData);
	$price = current($bookingData);
	$curl = curl_init();

	$description = "Servicio de transporte desde Palma a Menorca para " . rand(1,10) . " pasajeros. Reserva: " . $booking;

	$options = array(
		CURLOPT_URL => $URL.'/invoices/',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS =>'{
			"socid": '.$CLIENTE_GENERICO.',
			"note_private": "'.$email.'",
			"ref_client": "'.$booking.'",
			"array_options": {
				"options_fk_facture_type": "'.$TIPO_FACTURA_SIMPLIFICADA.'"
			},
			"lines": [
			{
				"fk_product": '.$PRODUCTO_TRANSFER.',
				"qty": 1,
				"subprice": '.$price.',
				"tva_tx": '.$TAX.',
				"desc": "'.$description.'",
				"array_options": {
						"options_fk_clave_regimen": "'.$CLAVE_REGIMEN.'",
						"options_fk_clave_operacion": "'.$CLAVE_OPERACION.'",
						"options_fk_clave_exencion": "'.$CLAVE_EXENCION.'"
					}
			}
			]

		}',
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'DOLAPIKEY: '.$DOLIBAR_KEY
		),
	);

	curl_setopt_array($curl, $options);

	$response = curl_exec($curl);
	$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ($httpcode == 200) {
		$id = $response;
		if (validateInvoice($id))
		{
			$paymentType = array_rand($PAYMENT_TYPES);
			$paymentName = $PAYMENT_TYPES[$paymentType];
			$paymentReference = strtoupper(substr($paymentName,0,3)).'-'.rand(1000,9999).'-'.$id;
			confirmPayment($id, $paymentType, $paymentReference);
		}
	} else {
		echo "Error al crear la factura para el email: " . $email . ". Código HTTP: " . $httpcode . "\n";
	}

	curl_close($curl);


}
