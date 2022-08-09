<?php
namespace foaria\Nkom;
use pocketmine\scheduler\AsyncTask;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\plugin\ApiVersion;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class Fetch extends AsyncTask {
  public function __construct($repos, string $name, string $query, $version, String $apiversion, $mcpe, Server $server, CommandSender $sender) {
    $this->repos = $repos;
    $this->name = $name;
    $this->query = $query;
    $this->version = $version;
    $this->apiversion = $apiversion;
    $this->mcpe = $mcpe;
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
                if(ApiVersion::isCompatible($this->apiversion, $response['info']['api_version'])){
                    if(isset($response['info']['mcpe-protocol'])){
                        $i = 0;
                        foreach($response['info']['mcpe-protocol'] as $mcpe_proto){
                            $i++;
                            if($mcpe_proto == $this->mcpe){
                                $protoc = true;
                            }else if(count($response['info']['mcpe-protocol']) == $i){
                                $this->publishProgress('{"type":"message", "message":"§c' .$response['info']['name']. 'は現在実行しているPMMPのプロトコルバージョンに対応していません。"}');
                                $this->setResult('{"exit":"error"}');
                                return;
                            }
                        }
                    }
                    if(isset($response['info']['depend'])){
                        $this->publishProgress('{"type":"message", "message":"' .$response['info']['name']. 'の依存プラグインが見つかりました:"}');
                        foreach($response['info']['depend'] as $depend){
                            $this->publishProgress('{"type":"message", "message":"' .$depend. '"}');
                        }
                        $this->publishProgress('{"type":"message", "message":""}');
                        $this->publishProgress('{"type":"depend", "depend":' .json_encode($response['info']['depend']). '}');
                    }
                    $this->publishProgress('{"type":"message", "message":"' .$response['info']['name']. 'をダウンロードしています..."}');
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $response['info']['url']); 
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $data =  curl_exec($ch);
                    curl_close($ch);
                    if($data){
                        if(file_put_contents(realpath('./plugins').'/'.$response['info']['name'].".phar",$data) === false){
                            $this->publishProgress('{"type":"message", "message":"§c' .$response['info']['name']. 'のインストールに失敗しました。"}');
                        }else{
                            $this->publishProgress('{"type":"message", "message":"§a' .$response['info']['name']. 'をインストールしました。"}');
                        }
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
            $task = new Fetch($repos, $depend, 'install', null, $this->apiversion, ProtocolInfo::CURRENT_PROTOCOL ,$server, $sender);
            $server->getAsyncPool()->submitTask($task);
        }
    }
  }

}
