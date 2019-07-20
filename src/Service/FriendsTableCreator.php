<?php
namespace App\Service;

use Doctrine\ORM\EntityManager;

use App\Entity\Users;

class FriendsTableCreator {
  public $login;
  public $tableName;
  public $query;
  private $conn;
  public function __construct(Users $user,EntityManager $em){
    $this->login = $user->getLogin();
    $this->conn =$em->getConnection();
  }
  public function prepareLogin()
  {
    $this->login = mb_strtolower($this->login,'UTF-8');
    return $this;
  }
  public function createTableName(){
    $this->tableName = $this->login.'friends';
    return $this;
  }
  public function buildQuery(){
    $name = $this->tableName;
    $sql = "CREATE TABLE IF NOT EXISTS $name (id int NOT NULL PRIMARY KEY AUTO_INCREMENT,userid int UNIQUE,chatid varchar(512) UNIQUE,status int);";
    $this->query = $sql;
    return $this;
  }
  public function execute(){
    $stmt = $this->conn->prepare($this->query);
    return $stmt->execute();
  }
}
