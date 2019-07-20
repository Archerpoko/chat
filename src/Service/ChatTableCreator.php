<?php
namespace App\Service;

use Doctrine\ORM\EntityManager;


class ChatTableCreator {
  public $tableName;
  public $query;
  private $conn;
  public function __construct(EntityManager $em){
    $this->conn = $em->getConnection();
  }
  public function createTableName(){
    $this->tableName = 'c';
    $this->tableName .= md5(uniqid());
    return $this;
  }
  public function buildQuery(){
    $name = $this->tableName;
    $sql = "CREATE TABLE IF NOT EXISTS $name ( id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, `from` VARCHAR(255) NOT NULL , message VARCHAR(1024) CHARACTER SET utf8 COLLATE utf8_polish_ci NOT NULL , timesend DATETIME NOT NULL)";
    $this->query = $sql;
    return $this;
  }
  public function execute(){
    $stmt = $this->conn->prepare($this->query);
    return $stmt->execute();
  }
}
