<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// SETUP
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

require 'config.php';

// initialize error variables
// setting these variables to true will trigger warnings displayed to the user
$message_empty = false;
$reply_to_error = false;
$email_status = false;
$email_error = false;
$attachement_error = false;
$attachement_size_error = false;
$csrf_error = false;

// LANGUAGE
// if found, $_GET['lang']. else, if found, $_COOKIE['lang']. else $_SERVER['HTTP_ACCEPT_LANGUAGE'] (if accepted)
if (array_key_exists('lang', $_GET)) {
  $lang = $_GET['lang'];
  setcookie('lang', $lang, time() + 31536000, './');
} elseif (array_key_exists('lang', $_COOKIE)) {
  $lang = $GET['lang'];
} else {
  $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
}
// if $lang is not accepted, default is array_keys($accepted_languages)[0] (see config.php)
$lang = in_array($lang, array_keys($accepted_languages)) ? $lang : array_keys($accepted_languages)[0];
// load language values
require "assets/lang/$lang.php";

// IF MESSAGE, PROCESS AND SEND
if (array_key_exists('message', $_POST)) {
  if (empty(trim($_POST['message']))) {
    // if message is empty, trigger warning and do not do anything
    $message_empty = true;
  } else {
    // gather anonymous or not from post variable
    if (array_key_exists('anonymous', $_POST)) {
      $anonymous = filter_var($_POST['anonymous'], FILTER_VALIDATE_BOOLEAN);
    } else {
      $anonymous = false;
    }

    // fetch name if not anonymous and create subject string
    $name = '';
    $subject = '[ae-isae-supaero.fr] Témoignage harcèlement anonyme';
    if (!$anonymous) {
      if (array_key_exists('name', $_POST)) {
        $name = trim($_POST['name']);
        if (!empty($name)) {
          $subject = '[ae-isae-supaero.fr] Témoignage harcèlement de ' . $name;
        }
      }
    }

    // if email address provided, set reply to
    if (array_key_exists('email', $_POST)) {
      $reply_to = $_POST['email'];
      if (!empty($reply_to) && !PHPMailer::validateAddress($reply_to)) {
        // if reply to email incorrect, display warning
        $reply_to = '';
        $reply_to_error = true;
      }
    } else {
      $reply_to = '';
    }

    // CSRF token check
    if(!(key_exists('csrf_token', $_COOKIE) && key_exists('csrf_token', $_POST) && $_COOKIE['csrf_token'] == $_POST['csrf_token'])) {
      $csrf_error = true;
    }

    // send email
    if (!($reply_to_error || $csrf_error)) {
      // setup phpmailer
      $mail = new PHPMailer();
      $mail->isSMTP();
      $mail->CharSet = PHPMailer::CHARSET_UTF8;
      $mail->Host = $host;
      $mail->Port = $port;
      $mail->SMTPAuth = true;
      $mail->Username = $from;
      $mail->Password = $pwd;
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

      // setup mail info
      $mail->setFrom($from);           // from
      foreach ($to as $address)
        $mail->addAddress($address);   // to (multiple possible, see config.php)
      if (!empty($reply_to))
        $mail->addReplyTo($reply_to);  // reply to (if not empty)
      $mail->Subject = $subject;       // subject
      $mail->isHTML(false);            // plain text email

      // upload files and attach
      $uploaded_files = [];
      $attachement_size = 0;
      if (array_key_exists('attachements', $_FILES)) {
        if (!empty($_FILES['attachements']['name'][0])) {
          // if there are attachments
          $file_count = count($_FILES['attachements']['name']);
          for ($i = 0; $i < $file_count; ++$i) {
            // for each attachement
            $file_path = $target_dir . basename($_FILES['attachements']['name'][$i]);
            $attachement_size += $_FILES['attachements']['size'][$i];
            if ($attachement_size > $max_attachement_size) {
              // if attachment > max size, display warning
              $attachement_size_error = true;
              break;
            }
            if (move_uploaded_file($_FILES['attachements']['tmp_name'][$i], $file_path)) {
              $uploaded_files[] = $file_path;
              if (!$mail->addAttachment($file_path)) {
                // if attachment error, display warning
                $attachement_error = true;
                break;
              }
            } else {
              // if attachment file move error, display warning
              $attachement_error = true;
              break;
            }
          }
        }
      }

      // if attachement, add security warning
      $message = $_POST['message'];
      if ($attachement_size > 0) {
        $message = $message . PHP_EOL . PHP_EOL . "---" . PHP_EOL . "Note du webmaster : Nous ne pouvons pas vérifier l'intégrité des fichiers joints à ce courriel. Veuillez les ouvrir avec la plus grande attention.";
      }
      $mail->Body = $message;

      // send mail if no attachement error
      if (!($attachement_error && $attachement_size_error)) {
        $email_status = true;
        if (!$mail->send()) {
          $email_error = true;
        }
      }

      // delete uploaded files
      for ($i = 0; $i < count($uploaded_files); ++$i) {
        @unlink($uploaded_files[$i]);
      }
    }
  }
}

if (!array_key_exists('message', $_POST) || $message_empty || $reply_to_error || $email_error || $attachement_error || $attachement_size_error || $csrf_error) {
  // generate and set csrf_token cookie
  $csrf_token = bin2hex(random_bytes(32));
  setcookie(
    'csrf_token',
    $csrf_token,
    ['samesite' => 'Strict']
  );
}
?>

<!doctype html>
<html lang="<?= $lang ?>">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AE ISAE-SUPAERO - <?= $LANG['tab_title'] ?></title>

  <link rel="stylesheet" href="assets/index.css">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
</head>

<body>
  <div class="m-3 mx-md-5">
    <!-- HEADER -->
    <div class="row align-items-center justify-content-end mb-4">
      <div id="logodiv" class="col-md-auto">
        <img src="assets/logo_fond_bleu_transparent.png" alt="">
      </div>
      <div class="col-md">
        <h2>
          <?= $LANG['title'] ?>
        </h2>
        <h5 class="mb-md-0">
          <?= $LANG['subtitle'] ?>
        </h5>
      </div>
      <?php
      foreach (array_keys($accepted_languages) as $l) {
        if ($lang != $l) {
          echo '<div class="col-auto mt-2 mt-md-0"><a href="?lang=' . $l . '">' . $accepted_languages[$l] . "</a></div>\n";
        }
      }
      ?>
    </div>

    <!-- INTRO TEXT -->
    <div class="mb-4">
      <a class="text-reset" id="intro_button" data-bs-toggle="collapse" href="#intro" role="button" aria-expanded="false" aria-controls="collapseExample" onclick="hide();"><?= $LANG['intro_button'] ?></a>
      <div class="collapse" id="intro">
        <?= $LANG['intro'] ?>
      </div>
    </div>

    <!-- WARNINGS (hidden by default) -->
    <div class="p-3 mb-2 bg-success text-white rounded" <?php if (!$email_status || $email_error) echo 'hidden'; ?>><?= $LANG['email_sent'] ?></div>
    <div class="p-3 mb-2 bg-warning text-white rounded" <?php if (!$message_empty) echo 'hidden'; ?>><?= $LANG['empty_message'] ?></div>
    <div class="p-3 mb-2 bg-warning text-white rounded" <?php if (!$reply_to_error) echo 'hidden'; ?>><?= $LANG['wrong_email_format'] ?></div>
    <div class="p-3 mb-2 bg-warning text-white rounded" <?php if (!$attachement_error) echo 'hidden'; ?>><?= $LANG['attachement_error'] ?></div>
    <div class="p-3 mb-2 bg-warning text-white rounded" <?php if (!$attachement_size_error) echo 'hidden'; ?>><?= $LANG['attachement_size_error'] ?></div>
    <div class="p-3 mb-2 bg-danger text-white rounded" <?php if (!($email_status && $email_error)) echo 'hidden'; ?>><?= $LANG['sending_error'] ?></div>
    <div class="p-3 mb-2 bg-danger text-white rounded" <?php if (!$csrf_error) echo 'hidden' ?>><?= $LANG['csrf_error'] ?></div>

    <!-- FORM -->
    <div id="formdiv" class="p-3 mb-4 border rounded shadow-sm">
      <form method="POST" enctype="multipart/form-data">
        <?php if (isset($csrf_token)) echo '<input type="hidden" name="csrf_token" value="' . $csrf_token . '">' ?>
        <div class="form-group">
          <input type="checkbox" class="form-check-input" name="anonymous" id="anonymous" <?php if (array_key_exists('anonymous', $_POST) && $_POST['anonymous']) echo 'checked'; ?>>
          <label for="anonymous"><?= $LANG['anonymous'] ?></label>
        </div>
        <br>
        <div class="form-group">
          <label for="text"><?= $LANG['name'] ?></label>
          <input type="text" class="form-control" name="name" id="name" <?php if (array_key_exists('name', $_POST)) {
                                                                          echo ('value="' . $_POST['name'] . '"');
                                                                        } ?>>
        </div>
        <br>
        <div class="form-group">
          <label for="email"><?= $LANG['email'] ?></label>
          <input type="text" class="form-control" name="email" id="email" <?php if (array_key_exists('email', $_POST)) {
                                                                            echo ('value="' . $_POST['email'] . '"');
                                                                          } ?>>
        </div>
        <br>
        <div class="form-group">
          <label for="message"><?= $LANG['testimony'] ?></label>
          <textarea name="message" class="form-control" id="message" rows="10"><?php if (array_key_exists('message', $_POST)) {
                                                                                  echo ($_POST['message']);
                                                                                } ?></textarea>
        </div>
        <br>
        <div class="form-group">
          <label for="attachements"><?= $LANG['attachements'] ?> (max <?= (int)($max_attachement_size / 1e6) ?> Mb) : </label>
          <input type="file" class="form-control" name="attachements[]" id="attachements" multiple>
        </div>
        <br>
        <input type="submit" class="btn btn-primary" value="<?= $LANG['submit'] ?>">
      </form>
    </div>

    <!-- INFORMATION FOOTER -->
    <p class="text-center lh-sm text-muted">
      <small>
        <?= $LANG['footer1'] ?>
        <?php
        if (count($names) > 1) {
          echo ($LANG['footer2.2'] . ' ' . implode(', ', array_slice($names, 0, -1)) . ' ' . $LANG['footer2.3'] . ' ' . end($names) . '.');
        } else {
          echo ($LANG['footer2.1'] . ' ' . $names[0] . '.');
        }
        ?><br>
        <?= $LANG['footer3'] ?>
        <br><br>
        <?= $LANG['footer4'] ?> <a href="https://github.com/ae-isae-supaero/formulaire-harcelement" target="_blank"><?= $LANG['footer5'] ?></a>.<br><br>
        <?= $LANG['cookie_info'] ?><br><br>
        © 2021-<?= date("Y") ?> AE ISAE-SUPAERO / Victor Colomb, Responsable Multimédia - <a class="text-reset" href="https://github.com/ae-isae-supaero/formulaire-harcelement/blob/main/LICENSE" target="_blank">MIT License</a>
      </small>
    </p>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
  <script src="assets/index.js"></script>
</body>

</html>