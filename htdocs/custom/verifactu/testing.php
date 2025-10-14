<?php
function validateInvoice($id)
{
	global $URL, $DOLIBAR_KEY;

	$URL_TO_SEND = $URL . '/invoices/' . $id . '/validate';
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
		CURLOPT_POSTFIELDS => '',
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'DOLAPIKEY: ' . $DOLIBAR_KEY
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
			$error_message = json_encode($response->error);
		} else {
			$error_message = json_encode($response);
		}

		echo "Error al validar la factura con ID: " . $id . ". Código HTTP: " . $httpcode . "\n" . $error_message . "\n";
		curl_close($curl);
		if (str_contains($error_message, 'Duplicate entry')) {
			sleep(1);
			return validateInvoice($id);
		}
	}

	return false;
}

function confirmPayment($id, $paymentId, $paymentReference)
{
	global $URL, $DOLIBAR_KEY, $ACCOUNT_ID;

	$URL_TO_SEND = $URL . '/invoices/' . $id . '/payments/';


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
		CURLOPT_POSTFIELDS => '{
			"datepaye": "' . date('Y-m-d') . '",
			"paymentid": ' . $paymentId . ',
			"closepaidinvoices": "yes",
			"accountid": ' . $ACCOUNT_ID . ',
			"num_payment": "' . $paymentReference . '",
			"comment": "Pago confirmado automáticamente a traves de shuttlespaintransfers.com."
		}',
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'DOLAPIKEY: ' . $DOLIBAR_KEY
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


//si $existingCustomerId es null, se usa el cliente genérico y se crea una factura simplificada
//si $existingCustomerId no es null, se usa ese cliente y se crea una factura nominativa
//cuando es simplificada, el total de la factura tiene que ser inferior a 400€ (MAX_AMOUNT_SIMPLIFICADA)

function sendInvoice($email, $booking, $price, $existingCustomerId = null)
{
	global $URL, $DOLIBAR_KEY, $CLIENTE_GENERICO, $TIPO_FACTURA_SIMPLIFICADA, $PRODUCTO_TRANSFER, $CLAVE_REGIMEN, $CLAVE_OPERACION, $CLAVE_EXENCION, $PAYMENT_TYPES, $TIPO_FACTURA_NOMINATIVA;

	$tax = 21.00;
	$description = "Servicio de transporte desde Palma a Menorca para " . rand(1, 10) . " pasajeros. Reserva: " . $booking;


	if ($existingCustomerId === null) {
		$clientId = $CLIENTE_GENERICO;
		$tipoFactura = $TIPO_FACTURA_SIMPLIFICADA;
	} else {
		$clientId = $existingCustomerId;
		$tipoFactura = $TIPO_FACTURA_NOMINATIVA;
	}

	$options = array(
		CURLOPT_URL => $URL . '/invoices/',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS => '{
			"socid": ' . $clientId . ',
			"note_private": "' . $email . '",
			"ref_client": "' . $booking . '",
			"array_options": {
				"options_fk_facture_type": "' . $tipoFactura . '"
			},
			"lines": [
			{
				"fk_product": ' . $PRODUCTO_TRANSFER . ',
				"qty": 1,
				"subprice": ' . $price . ',
				"tva_tx": ' . $tax . ',
				"desc": "' . $description . '",
				"array_options": {
						"options_fk_clave_regimen": "' . $CLAVE_REGIMEN . '",
						"options_fk_clave_operacion": "' . $CLAVE_OPERACION . '",
						"options_fk_clave_exencion": "' . $CLAVE_EXENCION . '"
					}
			}
			]

		}',
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'DOLAPIKEY: ' . $DOLIBAR_KEY
		),
	);
	$curl = curl_init();
	curl_setopt_array($curl, $options);

	$response = curl_exec($curl);
	$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ($httpcode == 200) {
		$id = $response;
		if (validateInvoice($id)) {
			$paymentType = array_rand($PAYMENT_TYPES);
			$paymentName = $PAYMENT_TYPES[$paymentType];
			$paymentReference = strtoupper(substr($paymentName, 0, 3)) . '-' . rand(1000, 9999) . '-' . $id;
			confirmPayment($id, $paymentType, $paymentReference);
		}
	} else {
		echo "Error al crear la factura para el email: " . $email . ". Código HTTP: " . $httpcode . "\n";
	}
	curl_close($curl);
}

function getCustomerId($vat_number)
{
	global $URL, $DOLIBAR_KEY;

	$filter = rawurlencode("t.siren:like:'%" . $vat_number . "%'");
	$send_url = $URL . '/thirdparties/?sqlfilters=' . $filter;

	$options = array(
		CURLOPT_URL => $send_url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'GET',
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'DOLAPIKEY: ' . $DOLIBAR_KEY
		),
	);
	$curl = curl_init();
	curl_setopt_array($curl, $options);

	$response = curl_exec($curl);
	$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ($httpcode == 200) {
		$data = json_decode($response);
		//$data es un array de objetos
		if (is_array($data)) {
			if (count($data) > 0) {
				$data = $data[0];
			} else {
				$data = null;
			}
		}
		if (isset($data->id)) {
			echo "Cliente encontrado con ID: " . $data->id . "\n";
			return $data->id;
		}
	}
	curl_close($curl);
	return null;
}

function createOrUpdateCustomer($name, $lastname, $email, $address, $postalcode, $city, $countryId, $dni, $existingCustomerId = null)
{

	global $URL, $DOLIBAR_KEY, $COUNTRIES;
	$send_url = $URL . '/thirdparties/' . ($existingCustomerId === null ? '' : $existingCustomerId);
	$options = array(
		CURLOPT_URL => $send_url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => $existingCustomerId === null ? 'POST' : 'PUT',
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'DOLAPIKEY: ' . $DOLIBAR_KEY
		),
		CURLOPT_POSTFIELDS => '{
			"name": "' . $name . ' ' . $lastname . '",
			"email": "' . $email . '",
			"address": "' . $address . '",
			"zip": "' . $postalcode . '",
			"town": "' . $city . '",
			"country_id": ' . $COUNTRIES[$countryId] . ',
			"idprof1": "' . $dni . '",
			"siren": "' . $dni . '",
			"client": 1,
			"typent_id": 8,
			"code_client": "SHU-' . substr($dni, 0, 8) . '"
		}',
	);



	$curl = curl_init();
	curl_setopt_array($curl, $options);

	$response = curl_exec($curl);
	$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ($httpcode == 200) {
		if ($existingCustomerId !== null) {
			echo "Cliente actualizado con ID: " . $existingCustomerId . "\n";
			curl_close($curl);
			return $existingCustomerId;
		}else{
			$id = $response;
			echo "Cliente creado con ID: " . $id . "\n";
			curl_close($curl);
			return $id;
		}

	}else {
		$data = json_decode($response);
		if (isset($data->error)) {
			$error_message = json_encode($data->error);
		} else {
			$error_message = json_encode($data);
		}
		echo "Error al crear/actualizar el cliente. Código HTTP: " . $httpcode . "\n" . $error_message . "\n";
	}
	curl_close($curl);
	return null;
}


function sendInvoiceNominativa($email, $booking, $price, $name, $lastname, $address, $postalcode, $city, $countryId, $dni)
{
	global $ERROR_LOG;
	$existingCustomerId = getCustomerId($dni);

	$customerId = createOrUpdateCustomer($name, $lastname, $email, $address, $postalcode, $city, $countryId, $dni, $existingCustomerId);


	if ($customerId === null){
		$ERROR_LOG[] = "No se ha podido crear o actualizar el cliente con DNI: " . $dni . " y email: " . $email . "\n";
		return;
	}

	sendInvoice($email, $booking, $price, $customerId);
}



function loadCountryList(){
	//abrimos el fichero country_mapping.csv y mapeamos el campo 1 como ID y el campo 2 como codigo pais
	$countryList = [];
	if (($handle = fopen('country_mapping.csv', 'r')) !== false) {
		while (($data = fgetcsv($handle, 1000, ';')) !== false) {
			$countryList[$data[1]] = $data[0];
		}
		fclose($handle);
	}
	return $countryList;
}


#$URL = 'https://stoic-fermat.151-80-20-157.plesk.page/api/index.php';
#$DOLIBAR_KEY = '283Nl20WRuaRMWxlU1u3cpKG75xal1KZ';
$URL = 'https://shuttlespain.eco.menorcaon.com/api/index.php';
$DOLIBAR_KEY = '93j5OqP8IZeSz2Yj2H4sVra7KxfT4u0U';

$ERROR_LOG = [];

$CLIENTE_GENERICO = 1;
$TIPO_FACTURA_SIMPLIFICADA = 2;
$TIPO_FACTURA_NOMINATIVA = 1;

$PRODUCTO_TRANSFER = 1;

$COUNTRIES = loadCountryList();


$ACCOUNT_ID = 1;
$CLAVE_REGIMEN = 1; // 01: Operación de régimen general.
$CLAVE_OPERACION = 1; // S1: Operación Sujeta y No exenta - Sin inversión del sujeto pasivo.
$CLAVE_EXENCION = null; // No aplica exención.
$TAX = 21.00;

$PAYMENT_TYPES = [
	4 => 'Efectivo',
	6 => 'Tarjeta',
	105 => 'Paypal'
];



$TEST_GENERICAS = false;
$TEST_NOMINATIVAS = true;


if (!file_exists('reservas.txt')) {
	file_put_contents('reservas.txt', '1');
}

if ($TEST_GENERICAS) {
	$reserva = file_get_contents('reservas.txt');
	$reserva = trim($reserva);
	$testsGenericas = [];
	file_put_contents('reservas.txt', $reserva + 1);
	for ($i = 0; $i < 2; $i++) {
		$importe = rand(100, 399) + (rand(0, 99) / 100);
		$importeSinIva = round($importe / (1 + ($TAX / 100)), 6);
		$testsGenericas['test00' . $i . '@verifactu.com'] = ['shuttle-booking ' . $reserva => $importeSinIva];
		$reserva++;
	}
	file_put_contents('reservas.txt', $reserva);
	foreach ($testsGenericas as $email => $bookingData) {
		$booking = key($bookingData);
		$price = current($bookingData);
		$curl = curl_init();
		sendInvoice($email, $booking, $price);
	}
}


if ($TEST_NOMINATIVAS) {
	if (!file_exists('pruebas_clientes.csv'))  return 1;

	$testsNominativas = [];
	if (($handle = fopen('pruebas_clientes.csv', 'r')) !== false) {
		$headers = fgetcsv($handle, 1000, ';');
		while (($data = fgetcsv($handle, 1000, ';')) !== false) {
			$record = array_combine($headers, $data);
			$client = [
				'name' => $record['nombres'],
				'lastname' => $record['apellidos'],
				'email' => $record['email'],
				'address' => $record['direccion'],
				'postalcode' => $record['codigo_postal'],
				'city' => $record['ciudad'],
				'countryId' => $record['pais'],
				'dni' => $record['vat_number']
			];
			$testsNominativas[$record['email']] = $client;
		}
		fclose($handle);
	}

	shuffle($testsNominativas);

	$reserva = file_get_contents('reservas.txt');
	$reserva = trim($reserva);
	file_put_contents('reservas.txt', $reserva + 1);

	$i = 0;
	foreach ($testsNominativas as $row => $client) {
		if ($i >= 1) break;

		$i++;
		$booking = 'shuttle-booking ' . $reserva;
		$importe = rand(100, 1000) + (rand(0, 99) / 100);
		$importeSinIva = round($importe / (1 + ($TAX / 100)), 6);
		sendInvoiceNominativa($client['email'], $booking, $importeSinIva, $client['name'], $client['lastname'], $client['address'], $client['postalcode'], $client['city'], $client['countryId'], $client['dni']);
		$reserva++;
	}
	file_put_contents('reservas.txt', $reserva);
}
