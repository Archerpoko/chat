<?php
if(isset($_POST['email'])){
require_once('../includes/dbConnect.php');
$stmt = $mysqli->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param('s',$_POST['email']);
$stmt->execute();
$result = $stmt->get_result();
echo $result->num_rows;
}else{
  header('Location: http://localhost/Chat');
}
