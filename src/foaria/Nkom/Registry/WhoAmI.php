<?php
namespace foaria\Nkom;
use pocketmine\scheduler\AsyncTask;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\Config;

class WhoAmI extends AsyncTask {
  public function __construct(Array $regs, Server $server, CommandSender $sender, Config $user) {
    $this->regs = $regs;
    $this->storeLocal('server', $server);
    $this->storeLocal('sender', $sender);
    $this->storeLocal('user', $user);
    $this->user = $user;
  }
  public function onRun() : void {
    $regs = $this->regs;
    $user = $this->user;
    foreach($regs as $reg){
        $search_time = 1;
        switch($reg['type']){
          case 'dynamic':
            if(!$user->get($reg['name'])){
                $this->publishProgress('{"type":"message", "message":"' .$reg['name']. ': ログインしていません。"}');
                $this->setResult('{"exit":""}');
                return;
            }
            $params = [
                'type' => 'who',
                'username' => '@me',
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $reg['url'] . '/user/?' . http_build_query($params)); 
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: token '. $user->get($reg['name'])));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data =  curl_exec($ch);
            if(curl_errno($ch)){
                $this->publishProgress('{"type":"message", "message":"' .$reg['name'] . 'は利用できません:'.curl_error($ch) .'"}');
                curl_close($ch);
                return;
            }
            curl_close($ch);
            $response = json_decode($data, true);
            if(isset($response['error'])){
                switch($response['error']){
                    case 'invalid token':
                        $this->publishProgress('{"type":"message", "message":"トークンが無効です。ログインし直してください。"}');
                        $this->setResult('{"exit":"error"}');
                        return;
                }
            }
            $this->publishProgress('{"type":"message", "message":"' .$reg['name']. ': ' .$response['user']['username']. '"}');
            $this->setResult('{"exit":""}');
        }
    }
  }
  public function onCompletion() : void {
    $result = $this->getResult();
    $sender = $this->fetchLocal('sender');
    $server = $this->fetchLocal('server');
    $user = $this->fetchLocal('user');
    $result = json_decode($result, true);
  }
  public function onProgressUpdate($log) : void {
    $sender = $this->fetchLocal('sender');
    $server = $this->fetchLocal('server');
    if(json_decode($log, true)['type'] == 'message'){
        $sender->sendMessage(json_decode($log, true)['message']);
    }
  }

}
