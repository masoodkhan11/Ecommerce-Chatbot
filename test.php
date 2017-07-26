<?php
$email = "johndoe@example.c";

if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo("$email is a valid email address");
} else {
  echo("$email is not a valid email address");
}
?>


send_text($msg_data["sender_id"], "Provoide Email : ");
                        $sql1 = " INSERT INTO bot_user (expected_mail) VALUES ('" .$msg_data['data']. "') WHERE sender_id = '" .$msg_data["sender_id"]. "' " ;