<?php
namespace App\Service;

use Symfony\Component\HttpFoundation\Session\Session;
use Doctrine\DBAL\Connection;
use App\Repository\UsersRepository;
use Symfony\Component\HttpFoundation\Request;

use App\Service\ChatTableCreator;

class UserDataManager {
  private $connection;
  private $repository;
  public function __construct(Connection $conn,UsersRepository $rep){
    $this->connection = $conn;
    $this->repository = $rep;
  }
  public function getAllFriends($login){
    $user = $this->repository->findOneBy(['login'=>$login]);
    if(!$user){
      return NULL;
    }else{
      $tableName = $user->getFriendsTableName();
      $selectFriends = "SELECT userid,chatid,status FROM $tableName WHERE status = 1 OR status = 2";
      $stmt = $this->connection->prepare($selectFriends);
      $stmt->execute();
      $friendsList = $stmt->fetchAll();

      foreach($friendsList as $index => $friend){
        $user = $this->repository->find($friend['userid']);
        $friendsList[$index]['nick'] = $user->getLogin();
        $friendsList[$index]['avatar'] = $user->getAvatar();
      }
      return $friendsList;
    }
  }
  public function getAcceptedFriends($login,$chatid){
    $user = $this->repository->findOneBy(['login'=>$login]);
    if(!$user){
      return NULL;
    }else{
      $friendsTableName = $user->getFriendsTableName();
      $AcceptedfriendsListSQL = "SELECT userid,chatid FROM $friendsTableName WHERE status = 2";
      $stmt = $this->connection->prepare($AcceptedfriendsListSQL);
      $stmt->execute();
      $Acceptedfriends = $stmt->fetchAll();

      foreach($Acceptedfriends as $key => $friend){
        $user = $this->repository->find($friend['userid']);
        $Acceptedfriends[$key]['login'] = $user->getLogin();
        $Acceptedfriends[$key]['avatar'] = $user->getAvatar();
        if($friend['chatid']==$chatid){
          $Acceptedfriends[$key]['picked'] = true;
        }
        $friendchatid = $Acceptedfriends[$key]['chatid'];
        $lastMsgSQL = "SELECT message FROM $friendchatid ORDER BY timesend DESC LIMIT 1";

        $stmt = $this->connection->prepare($lastMsgSQL);
        $stmt->execute();
        $lastmsg = $stmt->fetchAll();

        $Acceptedfriends[$key]['lastMsg'] = (isset($lastmsg[0]) && isset($lastmsg[0]['message']))? $lastmsg[0]['message'] : 'No messages say hello';
      }
      return $Acceptedfriends;
    }
  }
  public function getMessages($chatid){
    $getMessagesSQL = "SELECT message,`from`,timesend FROM $chatid ORDER BY timesend";
    $stmt = $this->connection->prepare($getMessagesSQL);
    $stmt->execute();
    return $stmt->fetchAll();
  }
  public function sendMessage($chatid,$from,$message){
    $time = date("Y-m-d H:i:s");
    $sendMessageSQL = "INSERT INTO $chatid (`from`,message,timesend) VALUES($from,'$message','$time')";
    $this->connection->prepare($sendMessageSQL)->execute();
  }
  public function isUserInvited($user,$targetUser){
    $userFriendTableName = $user->getFriendsTableName();
    $targetUserId = $targetUser->getId();
    $isInviteSendSQL = $isInviteSendSQL = "SELECT id FROM $userFriendTableName WHERE userid = $targetUserId";
    $stmt = $this->connection->prepare($isInviteSendSQL);
    $stmt->execute();
    $result = $stmt->fetchAll();
    return (isset($result[0]) && isset($result[0]['id']));
  }
  public function inviteUser($user,$targetUser){
    $userid = $user->getId();
    $userFriendTableName = $user->getFriendsTableName();
    $targetUserId = $targetUser->getId();
    $targetUserFriendTableName = $targetUser->getFriendsTableName();
    $insertRequestInTargetUserSQL = "INSERT INTO $targetUserFriendTableName (userid,status) VALUES($userid,1)";
    $this->connection->prepare($insertRequestInTargetUserSQL)->execute();
    $insertRequestInUserSQl = "INSERT INTO $userFriendTableName (userid,status) VALUES($targetUserId,3)";
    $this->connection->prepare($insertRequestInUserSQl)->execute();
  }
  public function acceptInvite($user,$targetUser,$em){
    $userId = $user->getId();
    $userFriendTableName = $user->getFriendsTableName();
    $targetUserId = $targetUser->getId();
    $targetUserFriendTableName = $targetUser->getFriendsTableName();

    $chatTable = new ChatTableCreator($em);
    $chatTable->createTableName()->buildQuery()->execute();
    $chatTableName = $chatTable->tableName;

    $updateTargetUserSQL = "UPDATE $targetUserFriendTableName SET chatId = '$chatTableName',status = 2 WHERE userid = $userId";
    $updateUserSQL = "UPDATE $userFriendTableName SET chatId = '$chatTableName',status = 2 WHERE userid = $targetUserId";

    $this->connection->prepare($updateTargetUserSQL)->execute();
    $this->connection->prepare($updateUserSQL)->execute();
  }
  public function removeFriend($user,$targetUser){
    $userId = $user->getId();
    $userFriendTableName = $user->getFriendsTableName();
    $targetUserId = $targetUser->getId();
    $targetUserFriendTableName = $targetUser->getFriendsTableName();

    $chatTableIdSQL = "SELECT status,chatid FROM $userFriendTableName WHERE userid = $targetUserId";
    $stmt = $this->connection->prepare($chatTableIdSQL);
    $stmt->execute();
    $result = $stmt->fetchAll();

    if($result[0]['status']==2){
      //Jeśli byli przyjaciółmi to usuwam również table czatu
      $chatid = $result[0]['chatid'];
      $this->connection->prepare("DROP TABLE $chatid")->execute();
    }
    //Usuwam przyjaciół z obu tabel znajomych
    $userSQL = "DELETE FROM $userFriendTableName WHERE userid = $targetUserId";
    $targetUserSQL = "DELETE FROM $targetUserFriendTableName WHERE userid = $userId";

    $this->connection->prepare($userSQL)->execute();
    $this->connection->prepare($targetUserSQL)->execute();


  }
}
