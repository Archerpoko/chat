<?php
namespace App\Service;

class UniquenessChecker {
  public function isValueUnique($repository,$field,$value){
    return (!$repository->findOneBy([$field=>$value]))? true : false;
  }
}
