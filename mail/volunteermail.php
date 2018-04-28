<?php

//Import the PHPMailer class into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use ReCaptcha\ReCaptcha;
require '../../vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    //Apply some basic validation and filtering to the subject
    if (array_key_exists('subject', $_POST)) {
        $subject = substr(strip_tags($_POST['subject']), 0, 255);
    } else {
        $subject = 'No subject given';
    }
    //Apply some basic validation and filtering to the query
	$query = "";
	if (array_key_exists('phone', $_POST)) {
        //Limit length and strip HTML tags
        $query .= "\nPhone: " . substr(strip_tags($_POST['phone']), 0, 16384);
    }
	if (array_key_exists('volunteer-type', $_POST)) {
        //Limit length and strip HTML tags
        $query .= "\nVolunteer Interest: " . substr(strip_tags(implode(", ",$_POST['volunteer-type']);), 0, 16384);
    }
    if (array_key_exists('message', $_POST)) {
        //Limit length and strip HTML tags
        $query .= "\nMessage: " . substr(strip_tags($_POST['message']), 0, 16384);
    }
    //Apply some basic validation and filtering to the name
    if (array_key_exists('name', $_POST)) {
        //Limit length and strip HTML tags
        $name = substr(strip_tags($_POST['name']), 0, 255);
    } else {
        $name = '';
    }
    //Validate to address
    //Never allow arbitrary input for the 'to' address as it will turn your form into a spam gateway!
    //Substitute appropriate addresses from your own domain, or simply use a single, fixed address
    if (array_key_exists('to', $_POST) and $_POST['to'] == 'list') {
        $to = array(
			'agnivohneb@gmail.com' => 'Ben Hovinga',
			'ben@hovinga.ca' => 'Ben Hovinga'
		);
    } else {
        $to = array('mail@blttoday.ca' => 'BLT Today');
    }
    //Make sure the address they provided is valid before trying to use it
    if (array_key_exists('email', $_POST) and PHPMailer::validateAddress($_POST['email'])) {
        $email = $_POST['email'];
    } else {
		// Set a 400 (bad request) response code and exit.
		http_response_code(400);
		echo "Oops! There was a problem with your submission. Please complete the form and try again.";
		exit;
    }
	// Validate ReCaptcha
	if (array_key_exists('g-recaptcha-response', $_POST)) {
		include 'recaptchasecret.php';
		$recaptcha = new ReCaptcha($reCaptchaSecret);
		$resp = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
		if (!$resp->isSuccess()){
			// Set a 400 (bad request) response code and exit.
			http_response_code(400);
			echo "Oops! There was a problem with your submission. Please complete the form and try again.";
			exit;
		}
	} else {
		// Set a 400 (bad request) response code and exit.
		http_response_code(400);
		echo "Oops! There was a problem with your submission. Please complete the form and try again.";
		exit;
	}
	
	// Setup mailer
	include 'mailsetup.php';
	$mail = new PHPMailer;
	$mail->isSMTP();
	$mail->Host = $mailsetup['host'];
	$mail->Port = $mailsetup['port'];
	$mail->SMTPAuth = true;
	$mail->Username = $mailsetup['username'];
	$mail->Password = $mailsetup['password'];
	$mail->CharSet = 'utf-8';
	$mail->setFrom($mailsetup['fromaddress'], (empty($name) ? 'Contact form' : $name));
	
	// Setup mailing list
	foreach ($to as $toemail => $toname){
		$mail->addAddress($toemail, $toname);
	}
	
	// Setup users info
	$mail->addReplyTo($email, $name);
	$mail->Subject = 'Contact form: ' . $subject;
	$mail->Body = "Contact form submission\n" . $query;
	
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
 