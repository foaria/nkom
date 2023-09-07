<?php
namespace foaria\Nkom;
use pocketmine\scheduler\AsyncTask;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\lang\Language;

class ProjectInit extends AsyncTask {
    public function __construct(Array $regs, String $name, Server $server, String $datapath, CommandSender $sender, $user, $projects) {
        $this->regs = serialize($regs);
        $this->user = serialize($user->getAll());
        $this->storeLocal('server', $server);
        $this->storeLocal('sender', $sender);
        $this->storeLocal('name', $name);
        $this->storeLocal('projects', $projects);
        $this->storeLocal('datapath', $datapath);
    }
    public function onRun() : void {
        $regs = unserialize($this->regs);
        $user = unserialize($this->user);
        foreach($regs as $reg){
            $search_time = 1;
            switch($reg['type']){
              case 'dynamic':
                if(!$user[$reg['name']]){
                    $this->publishProgress('{"type":"message", "message":"§eログインしていないため、制作者名は\'unknown\'になります。"}');
                    $author = 'unknown';
                }
                $params = [
                    'type' => 'who',
                    'username' => '@me',
                ];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $reg['url'] . '/user/?' . http_build_query($params)); 
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: token '. $user[$reg['name']]));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $data =  curl_exec($ch);
                if(curl_errno($ch)){
                    $this->publishProgress('{"type":"message", "message":"' .$reg['name'] . 'は利用できません:'.curl_error($ch) .'"}');
                    curl_close($ch);
                    $this->publishProgress('{"type":"message", "message":"§eユーザー名が不明なため、制作者名は\'unknown\'になります。"}');
                    $author = 'unknown';
                }
                curl_close($ch);
                $response = json_decode($data, true);
                if(isset($response['error'])){
                    switch($response['error']){
                        case 'invalid token':
                            $this->publishProgress('{"type":"message", "message":"§eトークンが無効なためユーザー名が不明です。制作者名は\'unknown\'になります。"}');
                            $author = 'unknown';
                    }
                }
                $author = isset($author)?$author:$response['user']['username'];
                $this->setResult('{"exit":"author", "author":"'.$author.'"}');
                return;
            }
        }
    }
    public function onCompletion() : void {
        $result = $this->getResult();
        $sender = $this->fetchLocal('sender');
        $server = $this->fetchLocal('server');
        $projects = $this->fetchLocal('projects');
        $name = $this->fetchLocal('name');
        $datapath = $this->fetchLocal('datapath');
        $result = json_decode($result, true);
        if($result['exit'] = 'author'){
            $server->dispatchCommand(new ConsoleCommandSender($server, new Language('jpn')), 'genplugin '.$name.' '.$result['author']);
            $sender->sendMessage('§a'.$name.'のプロジェクトを作成しました。');
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
