<?php
namespace foaria\Nkom;
use pocketmine\scheduler\AsyncTask;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\plugin\ApiVersion;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class Publish extends AsyncTask {
    public function __construct($regs, string $project, Server $server, CommandSender $sender, string $datapath, $users, string $datadir) {
        $this->regs = $regs;
        $this->project = $project;
        $this->datapath = $datapath;
        $this->users = $users;
        $this->datadir = $datadir;
        $this->storeLocal('server', $server);
        $this->storeLocal('sender', $sender);
    }
    public function onRun() : void {
        $regs = $this->regs;
        $pluginyml = yaml_parse_file($this->datapath.$this->project.'/plugin.yml');
        foreach($regs as $reg){
            $search_time = 1;
            switch($reg['type']){
              case 'dynamic':
                $info = [
                    'name' => $pluginyml['x-nkom-conf']['install-name'],
                    'long-name' => $pluginyml['name'],
                    'version' => $pluginyml['version'],
                    'api' => (array) $pluginyml['api'],
                    'mcpe-protocol' =>  isset($pluginyml['mcpe-protocol'])?$pluginyml['mcpe-protocol']:null,
                    'depend' => isset($pluginyml['depend'])?(array) $pluginyml['depend']:null,
                    'softdepend' => isset($pluginyml['softdepend'])?(array) $pluginyml['softdepend']:null,
                    'extensions' => isset($pluginyml['extensions'])?(array) $pluginyml['extensions']:null,
                    'os' => isset($pluginyml['os'])?(array) $pluginyml['os']:null,
                    'website' => isset($pluginyml['website'])?$pluginyml['website']:null,
                    'description' => isset($pluginyml['description'])?$pluginyml['description']:null,
                ];
                $req = array();
                $req['info'] = $info;
                $req['multi'] = false;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $reg['url'].'/publish/init/'); 
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: token '. $this->users->get($reg['name'], 'Content-Type: application/json')));
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $data =  curl_exec($ch);
                if(curl_errno($ch)){
                    $this->publishProgress('{"type":"message", "message":"' .$reg['name'] . 'は利用できません:'.curl_error($ch) .'"}');
                    curl_close($ch);
                    return;
                }
                $response = json_decode($data, true);
                if(isset($response['error'])){
                    switch($response['error']){
                        case 'invalid token':
                            $this->publishProgress('{"type":"message", "message":"トークンが無効です。ログインし直してください。"}');
                            $this->setResult('{"exit":"error"}');
                            curl_close($ch);
                            return;
                        case 'upload prepare failed':
                            $this->publishProgress('{"type":"message", "message":"アップロードの準備に失敗しました。"}');
                            $this->setResult('{"exit":"error"}');
                            curl_close($ch);
                            return;
                        case 'name or api is required':
                            $this->publishProgress('{"type":"message", "message":"名前とAPIバージョンは必須です。"}');
                            $this->setResult('{"exit":"error"}');
                            curl_close($ch);
                            return;
                        case 'version is required':
                            $this->publishProgress('{"type":"message", "message":"バージョンは必須です。"}');
                            $this->setResult('{"exit":"error"}');
                            curl_close($ch);
                            return;
                        
                    }
                }
                curl_close($ch);
                $ch = curl_init();
                $this->publishProgress('{"type":"message", "message":"プラグインをpharにアーカイブしています..."}');
                $phar = new \Phar($this->datadir.'tmp/'.sha1($pluginyml['x-nkom-conf']['install-name'].$pluginyml['version']).'.phar');
                $phar->buildFromDirectory($this->project, '/^(?!(.*git))(.*)$/i');
                $phar->setStub("<?php echo 'PocketMine-MP plugin ".$pluginyml['name']." v".$pluginyml['version']."\nThis file has been generated using Nkom v0.1.0-ALPHA1\n'; __HALT_COMPILER(); ?>");
                $this->publishProgress('{"type":"message", "message":"アップロードしています..."}');
                curl_setopt($ch, CURLOPT_URL, $response['upload_to']); 
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: token '. $this->users->get($reg['name'], 'Content-Type: application/octet-stream')));
                curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($this->datadir.'tmp/'.sha1($pluginyml['x-nkom-conf']['install-name'].$pluginyml['version']).'.phar'));
                curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = json_decode(curl_exec($ch), true);
                if(isset($response['error'])){
                    switch($response['error']){
                        case 'invalid token':
                            $this->publishProgress('{"type":"message", "message":"トークンが無効です。ログインし直してください。"}');
                            $this->setResult('{"exit":"error"}');
                            curl_close($ch);
                            return;
                        case 'file size too large':
                            $this->publishProgress('{"type":"message", "message":"プラグインのファイルサイズが大きすぎます。"}');
                            $this->setResult('{"exit":"error"}');
                            curl_close($ch);
                            return;
                        case 'store file failed':
                            $this->publishProgress('{"type":"message", "message":"ファイルをレジストリに保存できませんでした。"}');
                            $this->setResult('{"exit":"error"}');
                            curl_close($ch);
                            return;
                        case 'store info failed':
                            $this->publishProgress('{"type":"message", "message":"プラグインの情報を保存できませんでした。"}');
                            $this->setResult('{"exit":"error"}');
                            curl_close($ch);
                            return;
                        
                    }
                }
                curl_close($ch);
            }
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
    }

}
