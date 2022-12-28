<?php
namespace foaria\Nkom;
use pocketmine\scheduler\AsyncTask;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\Config;

class Login extends AsyncTask {
    public function __construct(Array $regs, Server $server, CommandSender $sender, Config $user) {
        $this->regs = $regs;
        $this->storeLocal('server', $server);
        $this->storeLocal('sender', $sender);
        $this->storeLocal('user', $user);
    }
    public function onRun() : void {
        $regs = $this->regs;
        foreach($regs as $reg){
            $search_time = 1;
            switch($reg['type']){
              case 'dynamic':
                $this->publishProgress('{"type":"message", "message":"' . $reg['name'] . 'にログインリクエストを送信しています..."}');
                $params = [
                    'type' => 'login',
                ];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $reg['url'] . '/user/?' . http_build_query($params)); 
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
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
                        case 'failed generate token':
                            $this->publishProgress('{"type":"message", "message":"トークンの生成に失敗しました。"}');
                            $this->setResult('{"exit":"error"}');
                    }
                }
                if(count($response['challenge']) == 1){
                    if($response['challenge']['web']){
                        $this->publishProgress('{"type":"message", "message":"ブラウザで以下のURLにアクセスして認証してください。"}');
                        $this->publishProgress('{"type":"message", "message":"' .$response['challenge']['web']. '"}');
                        $this->setResult('{"exit":"token", "registry":"' .$reg['name']. '", "token":"' .$response['token']. '"}');
                    }
                }
            }
        }
    }
    public function onCompletion() : void {
        $result = $this->getResult();
        $sender = $this->fetchLocal('sender');
        $server = $this->fetchLocal('server');
        $user = $this->fetchLocal('user');
        $result = json_decode($result, true);
        if($result['exit'] = 'token'){
            $user->set($result['registry'], $result['token']);
            $user->save();
        }
    }
    public function onProgressUpdate($log) : void {
        $sender = $this->fetchLocal('sender');
        $server = $this->fetchLocal('server');
        if(json_decode($log, true)['type'] == 'message'){
            $sender->sendMessage(json_decode($log, true)['message']);
        }
    }

}
