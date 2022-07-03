<?php
  // display errors (debug)
  ini_set('display_errors', '1');
  error_reporting(E_ALL);

  require './includes/phpmailer/PHPMailer.php';
  require './includes/phpmailer/SMTP.php';
  require './includes/phpmailer/Exception.php';
  require './includes/credentials.php';
  use PHPMailer\PHPMailer\PHPMailer;

  $connection = new mysqli($server, $database_username, $database_password, $database_name);

  // Check connection
  if ($connection -> connect_errno) {
    die();
  }

  // filter for SQL Injection
  $name = $connection -> real_escape_string($_POST['Name']);
  $surname = $connection -> real_escape_string($_POST['Surname']);
  $city = $connection -> real_escape_string($_POST['City']);
  $zip = $connection -> real_escape_string($_POST['Zip']);
  $address = $connection -> real_escape_string($_POST['Address']);
  $phone1 = $connection -> real_escape_string($_POST['Phone1']);
  $phone2 = $connection -> real_escape_string($_POST['Phone2']);
  $email = $connection -> real_escape_string($_POST['Email']);
  $state = "UNKNOWN";

  $data_json = json_encode(array("Name" => $name, "Surname" => $surname,
                                 "City" => $city, "Zip" => $zip,
                                 "Address" => $address, "Phone1" => $phone1,
                                 "Phone2" => $phone2, "Email" => $email,
                                 "State" => $state));

  $cipher = 'aes-128-ctr';
  $ivlen = openssl_cipher_iv_length($cipher);
  $iv = openssl_random_pseudo_bytes($ivlen);

  $password = hash('sha256',openssl_random_pseudo_bytes(128));

  $qrValue = openssl_encrypt($data_json, $cipher, $password, 0, $iv);
  $qrValue = base64_encode($qrValue);

  $iv = base64_encode($iv);

  do{
    $ActivationLink = randomKey(32);
    $sql_get_ActivationLink = sprintf("SELECT * FROM Users2 WHERE ActivationLink='%s'", $connection->real_escape_string($ActivationLink));
  }while($connection->query($sql_get_ActivationLink)->num_rows > 0);

  $statement = $connection->prepare('INSERT INTO Users2 (Password, InitializationVector, ActivationLink, activated) VALUES (?, ?, ?, false)');
  $statement->bind_param("sss", $password, $iv, $ActivationLink);
  $statement->execute();

  $tableId = $statement->insert_id;

  $statement->close();
  $connection->close();

  if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) {
    $httpProtocol = "https://";
  }
  else{
    $httpProtocol = "http://";
  }

  $ActivationLink = $httpProtocol . $_SERVER['SERVER_NAME'] . "/find-me/activate.php?activationLink=" . $ActivationLink;
  $qrValue = $httpProtocol . $_SERVER['SERVER_NAME'] . "/find-me/view.php?id=" . $tableId . "&data=" . $qrValue;

  if(!empty($email)){
    sendEmail($email, $ActivationLink);
  }

  echo $qrValue . "%{DELIMITER}%" . $ActivationLink;

  function randomKey($length) {
      $pool = array_merge(range(0,9), range('a', 'z'),range('A', 'Z'));
      $key = '';

      for($i=0; $i < $length; $i++) {
          $key .= $pool[mt_rand(0, count($pool) - 1)];
      }
      return $key;
  }

  function sendEmail($email, $link){

    $mail = new PHPMailer(true);
    //$mail->SMTPDebug = 3;
    // sender info
    $mail->isSMTP();
    $mail->Host = $GLOBALS['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $GLOBALS['smtp_username'];
    $mail->Password = $GLOBALS['smtp_password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->setFrom($GLOBALS['smtp_username'], 'Find-Me Team');

    $mail->addAddress($email);
    $mail->Subject = 'Find-me QR link activator';
    $mailContent = "<h1>Find-me QR link activator</h1>
    Click on <a href=\"$link\">THIS LINK </a> to activate the QR in case of lost item.";
    $mail->Body = $mailContent;
    $mail->isHTML(true);

    return $mail->send();

    //echo 'Mailer Error: ' . $mail->ErrorInfo;
  }

?>
