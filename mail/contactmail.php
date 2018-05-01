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
    //Apply some basic validation and filtering to the subject
    if (array_key_exists('subject', $_POST)) {
        $subject = substr(strip_tags($_POST['subject']), 0, 255);
    } else {
        $subject = 'No subject given';
    }
    //Apply some basic validation and filtering to the query
    if (array_key_exists('message', $_POST)) {
        //Limit length and strip HTML tags
        $query = substr(strip_tags($_POST['message']), 0, 16384);
    } else {
		// Set a 400 (bad request) response code and exit.
		http_response_code(400);
		echo "Oops! There was a problem with your submission. Please complete the form and try again. [MESSAGE]";
		exit;
    }
    //Apply some basic validation and filtering to the name
    if (array_key_exists('name', $_POST)) {
        //Limit length and strip HTML tags
        $name = substr(strip_tags($_POST['name']), 0, 255);
    } else {
        $name = '';
    }
    //Make sure the address they provided is valid before trying to use it
    if (array_key_exists('email', $_POST) and PHPMailer::validateAddress($_POST['email'])) {
        $email = $_POST['email'];
    } else {
		// Set a 400 (bad request) response code and exit.
		http_response_code(400);
		echo "Oops! There was a problem with your submission. Please complete the form and try again. [EMAIL]";
		exit;
    }
	// Validate ReCaptcha
	if (array_key_exists('g-recaptcha-response', $_POST)) {
		$recaptcha = new ReCaptcha($reCaptchaSecret);
		$resp = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
		if (!$resp->isSuccess()){
			// Set a 400 (bad request) response code and exit.
			http_response_code(400);
			echo "Oops! There was a problem with your submission. Please complete the form and try again. [CAPTCHA FAILED]";
			exit;
		}
	} else {
		// Set a 400 (bad request) response code and exit.
		http_response_code(400);
		echo "Oops! There was a problem with your submission. Please complete the form and try again. [CAPTCHA MISSING]";
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
	$mail->setFrom($mailsetup['fromaddress'], (empty($name) ? 'Contact form' : $name));
    $mail->DKIM_domain = 'blttoday.ca';
    $mail->DKIM_private = '../config/dkim_private.pem';
    $mail->DKIM_selector = 'phpmailer';
    $mail->DKIM_passphrase = '';
    $mail->DKIM_identity = $mail->From;
	$mail->addAddress('mail@blttoday.ca', 'BLT Today');

	// Setup users info
	$mail->addReplyTo($email, $name);
	$mail->Subject = $subject;
	$mail->Body = "<p>New message from " . $name . " &lt;" . $email . "&gt;</p>\n\n<p>Message</p>\n<pre>" . $query . "</pre>\n\n<p style='font-style: italic;'>Message sent from the contact form at <a href='http://www.blttoday.ca'>www.blttoday.ca</a>.</p>";

	// Send the message
	if (!$mail->send()) {
		// Set a 500 (internal server error) response code.
		http_response_code(500);
		echo "Oops! Something went wrong and we couldn't send your message. [500]";
	} else {
		// Set a 200 (okay) response code.
		http_response_code(200);
		echo "Thank You! Your message has been sent.";
	}

}
else {
	// Not a POST request, set a 403 (forbidden) response code.
	http_response_code(403);
	echo "There was a problem with your submission, please try again. [403]";
}

 ?>
