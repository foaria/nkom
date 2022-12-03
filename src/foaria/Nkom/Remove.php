<?php
namespace foaria\Nkom;
use pocketmine\scheduler\AsyncTask;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\plugin\ApiVersion;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class RemovePlugin extends AsyncTask {
  public function __construct($repos, string $name, string $query, Server $server, CommandSender $sender) {
    $this->repos = $repos;
    $this->name = $name;
    $this->query = $query;
    $this->storeLocal('server', $server);
    $this->storeLocal('sender', $sender);
  }
  public function onRun() : void {
    $repos = $this->repos;
    foreach($repos as $repo){
        $search_time = 1;
        
        switch($repo['type']){
          case 'dynamic':
            $this->publishProgress('{"type":"message", "message":"' . $repo['name'] . 'から検索中..."}');
            $params = [
                'type' => $this->query,
                'name' => $this->name,
                'version' => $this->version,
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $repo['url'] . '?' . http_build_query($params)); 
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data =  curl_exec($ch);
            if(curl_errno($ch)){
                $this->publishProgress('{"type":"message", "message":"' .$repo['name'] . 'は利用できません:'.curl_error($ch) .'"}');
                curl_close($ch);
            }else if(isset(json_decode($data, true)['info']) && !isset(json_decode($data, true)['error'])){
                curl_close($ch);
                $response = json_decode($data, true);
                $this->publishProgress('{"type":"message", "message":"プラグインが見つかりました:'. $response['info']['name'] .' "}');
                $this->setResult('{"exit":"found", "name":"' .$response['info']['name']. '"}');
                return;
            }else{
                switch(json_decode($data, true)['error']){
                  case 'plugin not found':
                    if(count($repos) == $search_time){
                        $this->publishProgress('{"type":"message", "message":"§c' .$this->name. 'が見つかりませんでした。"}');
                        $this->setResult('{"exit":"error"}');
                    }
                }
            }
          
        }
    }
    $this->setResult('{"type":"message", "message":"§cプラグインが見つかりませんでした"}');
  }
  public function onCompletion() : void {
    $result = $this->getResult();
    $sender = $this->fetchLocal('sender');
    $server = $this->fetchLocal('server');
    $result = json_decode($result, true);
    if($result['exit'] == 'found'){
        $files = glob(realpath('./plugins').'/'.$result['name'].'*');
        if(count($files) != 1){
            $sender->sendMessage('§e該当する可能性があるファイルが複数存在するため削除できません。');
        }else{
            if(is_file($files[0])){
                unlink($files[0]);
                $sender->sendMessage('§aプラグインを削除しました。');
            }else{
                $sender->sendMessage('§eフォルダプラグインは削除できません。');
            }
        }
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
