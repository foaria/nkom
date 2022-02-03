<?php
namespace foaria\Nkom;
//使用する関数
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\plugin\ApiVersion;

class MainClass extends PluginBase{
  public function onEnable():void{
      $this->saveDefaultConfig();
      $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
  }
  public function onCommand(CommandSender $sender, Command $command, string $label, array $args):bool {
    switch($command){
      case 'nkom':
        if(isset($args[0])){
          //install start
          if($args[0] == 'i' or $args[0] == 'install' or $args[0] == 'add'){
            if(isset($args[1])){
              $server = $this->getServer();
              //install process start
              $apiversion = $server->getApiVersion();
              $plugin_name = $args[1];
              $plugin_version = null;
              if(strpos($args[1], '@') != false){
                  $name_no_edit = explode('@', $args[1]);
                  $plugin_name = $name_no_edit[0];
                  $plugin_version = $name_no_edit[1];
              }
              $task = new Fetch($this->config->get('repositorys'), $plugin_name, 'install', $plugin_version, $apiversion, $server, $sender);
              $server->getAsyncPool()->submitTask($task);
              //install process end
              return true;
            }else {
              $sender->sendMessage("使い方: /nkom ". $args[0] . " [プラグイン名またはダウンロードリンク]");
              return true;
            }
          //install end
          }else if($args[0] == 'u' or $args[0] == 'update'){
            if(isset($args[1])){
              //update process start
              $sender->sendMessage('アップデートを開始します。');
              //update process end
              return true;
            }else {
              $sender->sendMessage("使い方: /nkom ". $args[0] . " [プラグイン名]");
              return true;
            }
          }else if($args[0] == 'remove' or $args[0] == 'r' or $args[0] == 'm' or $args[0] == 'rm'){
              }
        return false;
        }
      return false;
      //nkom end
      case 'nkom-repo':
        if(isset($args[0])){
          //add start
          if($args[0] == 'a' or $args[0] == 'add'){
            if(isset($args[1])){
              $sender->sendMessage('リポジトリを追加しました。');
              return true;
            }else {
              $sender->sendMessage("使い方: /nkom-repo ". $args[0] . " [リポジトリのURL]");
              return true;
            }
          //install end
          }
        return false;
        }
      return false;
      //nkom-repo end
    }
  //switch end
  }
}

class Fetch extends AsyncTask {
  public function __construct($repos, string $name, string $query, $version, $apiversion, Server $server, CommandSender $sender) {
    $this->repos = $repos;
    $this->name = $name;
    $this->query = $query;
    $this->version = $version;
    $this->apiversion = $apiversion;
    $this->storeLocal('server', $server);
    $this->storeLocal('sender', $sender);
  }
  public function onRun() : void {
    $this->publishProgress('{"type":"message", "message":"プラグインを探しています..."}');
    $repos = $this->repos;
    foreach($repos as $repo){
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
            curl_close($ch);
            if(!$data){
                $this->publishProgress('{"type":"message", "message":"' .$repo['name'] . 'は利用できません:'.curl_error($ch) .'"}');
            }else if(isset(json_decode($data, true)['info']) && !isset(json_decode($data, true)['error'])){
                $response = json_decode($data, true);
                $this->publishProgress('{"type":"message", "message":"プラグインが見つかりました:'. $response['info']['name'] .' "}');
                if(isset($response['info']['depend'])){
                    $this->publishProgress('{"type":"message", "message":"' .$response['info']['name']. 'の依存プラグインが見つかりました:"}');
                    foreach($response['info']['depend'] as $depend){
                        $this->publishProgress('{"type":"message", "message":"' .$depend. '"}');
                    }
                    $this->publishProgress('{"type":"message", "message":""}');
                    $this->publishProgress('{"type":"depend", "depend":' .json_encode($response['info']['depend']). '}');
                }
                if(ApiVersion::isCompatible($this->apiversion, $response['info']['api_version'])){
                    $this->publishProgress('{"type":"message", "message":"' .$response['info']['name']. 'をダウンロードしています..."}');
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $response['info']['url']); 
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$filename) {
                        $regex = '/^Content-Disposition: attachment; filename="(.+?)"$/i';
                        if (preg_match($regex, $header, $matches)) {
                            $filename = $matches[1];
                        }
                        return strlen($header);
                    });
                    $data =  curl_exec($ch);
                    curl_close($ch);
                    if($data){
                        file_put_contents($this->getDataFolder()."plugins/".$filename.".phar",$data);
                    }else{
                        $this->publishProgress('{"type":"message", "message":"§c' .$response['info']['name']. 'のダウンロードに失敗しました。"}');
                        $this->setResult('{"exit":"error"}');
                    }
                }else{
                    $this->publishProgress('{"type":"message", "message":"§c' .$response['info']['name']. 'は現在実行しているPMMPと互換性がありません。"}');
                    $this->setResult('{"exit":"error"}');
                }
            }else{
                switch(json_decode($data, true)['error']){
                  case 'version not found':
                  $this->publishProgress('{"type":"message", "message":"§c' .$this->name. 'の指定したバージョンは存在しません。"}');
                    $this->setResult('{"exit":"error"}');
                }
            }
          
        }
    }
    $this->setResult('{"type":"message", "message":"§cプラグインが見つかりませんでした"}');
  }
  public function onCompletion() : void {
    $result = $this->getResult();
    $sender = $this->fetchLocal('sender');
  }
  public function onProgressUpdate($log) : void {
    $sender = $this->fetchLocal('sender');
    $server = $this->fetchLocal('server');
    if(json_decode($log, true)['type'] == 'message'){
        $sender->sendMessage(json_decode($log, true)['message']);
    }
    if(json_decode($log, true)['type'] == 'depend'){
        foreach(json_decode($log, true)['depend'] as $depend){
            $repos = $this->repos;
            $task = new Fetch($repos, $depend, 'install', null, $this->apiversion, $server, $sender);
            $server->getAsyncPool()->submitTask($task);
        }
    }
  }
}
