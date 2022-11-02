<?php
namespace foaria\Nkom;
use pocketmine\scheduler\AsyncTask;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\plugin\ApiVersion;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class InstallFromURL extends AsyncTask {
    public function __construct(String $url, String $apiversion, $mcpe, Server $server, CommandSender $sender) {
        $this->url = $url;
        $this->apiversion = $apiversion;
        $this->mcpe = $mcpe;
        $this->storeLocal('server', $server);
        $this->storeLocal('sender', $sender);
    }
    public function onRun() : void {
        $this->publishProgress('{"type":"message", "message":"' .parse_url($this->url)['host']. 'からダウンロードしています..."}');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data =  curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if($content_type != 'application/octet-stream'){
            $this->publishProgress('{"type":"message", "message":"§cMIMEタイプが不正です。('.$content_type.')"}');
            $this->setResult('{"exit":"error"}');
        }
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
