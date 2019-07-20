<?php
if(isset($_POST['nick'])){
require_once('../includes/dbConnect.php');
$stmt = $mysqli->prepare("SELECT * FROM users WHERE login = ?");
$stmt->bind_param('s',$_POST['nick']);
$stmt->execute();
$result = $stmt->get_result();
echo $result->num_rows;
}else{
  header('Location: http://localhost/Chat');
}
