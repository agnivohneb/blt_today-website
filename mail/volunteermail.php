<?php

// Deal with errors
function handleError($errno,$errstr,$error_file,$error_line) {
	http_response_code(500);
	echo "Error: [$errno] $errstr - $error_file:$error_line";
	die();
}
set_error_handler("handleError");

//Import the PHPMailer and ReCaptcha class into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use ReCaptcha\ReCaptcha;
require '../vendor/autoload.php';

// Import mail settings and secrets
require '../config/mailsetup.php';
require '../config/recaptchasecret.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    //Strip tags from all POST keys
	$form = Array();
	foreach ($_POST as $key => $value) {
		if (is_array($value)) {
			$form[$key] = implode(", ",$value);
		}
		elseif ($key != 'g-recaptcha-response') {
			$form[$key] = strip_tags($value);
		}
	}

    // Apply some basic formating to the query.
	$query = "BLT Today Volunteer Application\n";
	//NAME
	if (array_key_exists('name', $form)) {
        $name = substr($form['name'], 0, 255);
		$query .= "\nName: " . $name;
    } else {
        // Set a 400 (bad request) response code and exit.
		http_response_code(400);
		echo "Oops! There was a problem with your submission. Please complete the form and try again. [NAME]";
		exit;
    }
	//EMAIL
    if (array_key_exists('email', $form) and PHPMailer::validateAddress($form['email'])) {
		$query .="\nEmail: " . $form['email'];
    } else {
		// Set a 400 (bad request) response code and exit.
		http_response_code(400);
		echo "Oops! There was a problem with your submission. Please complete the form and try again. [EMAIL]";
		exit;
    }
	//PHONE
	if (array_key_exists('phone', $form)) {
        //Limit length and strip HTML tags
        $query .= "\nPhone: " . substr($form['phone'], 0, 16384);
    }
	//VOLUNTEER TYPE
	if (array_key_exists('volunteer-type', $form)) {
        //Limit length and strip HTML tags
        $query .= "\nVolunteer Interest: " . $form['volunteer-type'];
    }
	//MESSAGE
    if (array_key_exists('message', $_POST)) {
        //Limit length and strip HTML tags
        $query .= "\nMessage:\n" . substr(strip_tags($_POST['message']), 0, 16384);
    }

	// Validate ReCaptcha
	if (array_key_exists('g-recaptcha-response', $_POST)) {
		$recaptcha = new ReCaptcha($reCaptchaSecret);
		$resp = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
		if (!$resp->isSuccess()){
			// Set a 400 (bad request) response code and exit.
			http_response_code(400);
			echo "Oops! There was a problem with your submission. Please complete the form and try again.[CAPTCHA FAIL]";
			exit;
		}
	} else {
		// Set a 400 (bad request) response code and exit.
		http_response_code(400);
		echo "Oops! There was a problem with your submission. Please complete the form and try again.[CAPTHA MISSING]";
		exit;
	}

	// Setup mailer
	$mail = new PHPMailer;
	$mail->isSMTP();
	$mail->Host = $mailsetup['host'];
	$mail->Port = $mailsetup['port'];
	$mail->SMTPAuth = true;
	$mail->Username = $mailsetup['username'];
	$mail->Password = $mailsetup['password'];
	$mail->CharSet = 'utf-8';
	$mail->setFrom($mailsetup['fromaddress'], (empty($name) ? 'Volunteer form' : $name));
    $mail->DKIM_domain = 'blttoday.ca';
    $mail->DKIM_private = '../config/dkim_private.pem';
    $mail->DKIM_selector = 'phpmailer';
    $mail->DKIM_passphrase = '';
    $mail->DKIM_identity = $mail->From;
	$mail->addAddress('mail@blttoday.ca', 'BLT Today');

	// Setup users info
	$mail->addReplyTo($form['email'], $name);
	$mail->Subject = 'Volunteer Application: ' . $name;
	$mail->Body = $query;

	// Send the message
	if (!$mail->send()) {
		// Set a 500 (internal server error) response code.
		http_response_code(500);
		echo "Oops! Something went wrong and we couldn't send your message.";
	} else {
		// Set a 200 (okay) response code.
		http_response_code(200);
		echo "Thank You! Your message has been sent.";
	}

}
else {
	// Not a POST request, set a 403 (forbidden) response code.
	http_response_code(403);
	echo "There was a problem with your submission, please try again.";
}

 ?>
