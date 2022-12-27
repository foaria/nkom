<?php
namespace foaria\Nkom;
use pocketmine\scheduler\AsyncTask;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\Config;

class Logout extends AsyncTask {
  public function __construct(Array $regs, Server $server, CommandSender $sender, Config $user) {
    $this->regs = $regs;
    $this->storeLocal('server', $server);
    $this->storeLocal('sender', $sender);
    $this->user = $user;
  }
  public function onRun() : void {
    $regs = $this->regs;
    foreach($regs as $reg){
        if(!$this->user->exists($reg['name'])){
            $this->publishProgress('{"type":"message", "message":"ログインしていません。"}');
            $this->setResult('{"exit":"error"}');
            return;
        }
        switch($reg['type']){
          case 'dynamic':
            $params = [
                'type' => 'destroy',
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $reg['url'] . '/user/?' . http_build_query($params));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: token '. $this->user->get($reg['name'])));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data =  curl_exec($ch);
            if(curl_errno($ch)){
                $this->publishProgress('{"type":"message", "message":"' .$reg['name'] . 'は利用できません:'.curl_error($ch) .'"}');
                curl_close($ch);
            }
            curl_close($ch);
            $response = json_decode($data, true);
            if(isset($response['error'])){
                switch($response['error']){
                    case 'failed destroy token':
                        $this->publishProgress('{"type":"message", "message":"§cトークンの破棄に失敗しました。"}');
                        $this->setResult('{"exit":"error"}');
                }
                return;
            }
            $this->publishProgress('{"type":"message", "message":"§aログアウトしました。"}');
            $this->user->remove($reg['name']);
            $this->user->save();
            $this->setResult('{"exit":""}');
            return;
        }
    }
  }
  public function onCompletion() : void {
    $result = $this->getResult();
    $sender = $this->fetchLocal('sender');
    $server = $this->fetchLocal('server');
  }
  public function onProgressUpdate($log) : void {
    $sender = $this->fetchLocal('sender');
    $server = $this->fetchLocal('server');
    if(json_decode($log, true)['type'] == 'message'){
        $sender->sendMessage(json_decode($log, true)['message']);
    }
  }

}
