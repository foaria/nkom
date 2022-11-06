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
        if(preg_match('/(4|5)../', (string) $http_code) or $http_code == 334){
            $error_codes = [];
            $error_codes[334] = 'なんでや阪神関係ないやろ()';
            $error_codes[400] = '不正なリクエストです。';
            $error_codes[401] = '認証が必要です。';
            $error_codes[403] = 'アクセス権限がありません。';
            $error_codes[404] = '見つかりません。';
            $error_codes[408] = 'リクエストがタイムアウトしました。';
            $error_codes[410] = 'このリソースは削除されました。';
            $error_codes[418] = 'Server is a teapot. §rところで開発者の推しは知っていますか？§c';
            $error_codes[429] = 'レート制限に達しました。';
            $error_codes[451] = '法的理由で利用できません。';
            
            $error_codes[500] = 'サーバーでエラーが発生しました。';
            $error_codes[502] = 'Bad Gateway';
            $error_codes[503] = 'メンテナンスまたはサーバーの過負荷でダウンロードできません。';
            $error_code = $error_codes[$http_code]?$error_codes[$http_code]:'不明なエラーが発生しました。';
            
            $this->publishProgress('{"type":"message", "message":"§c'.$error_code.'('.$http_code.')'.'"}');
            curl_close($ch);
            $this->setResult('{"exit":"error"}');
            return;
        }
        if(preg_match('/3../', (string) $http_code)){
            $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            $this->publishProgress('{"type":"redirect", "location":"'.$redirect_url.'"}');
            curl_close($ch);
            $this->setResult('{"exit":"error"}');
            return;
        }
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        if($content_type != 'application/octet-stream' and $content_type){
            $this->publishProgress('{"type":"message", "message":"§cMIMEタイプが不正です。('.$content_type.')"}');
            curl_close($ch);
            $this->setResult('{"exit":"error"}');
            return;
        }
        $path_array = explode('/', parse_url($this->url)['path']);
        $file_name = array_pop($path_array);
        $phar_ext = '';
        if($file_name == ''){
            $file_name = array_pop($path_array);
            $phar_ext = '.phar';
        }
        if(file_put_contents(realpath('./plugins').'/'.$file_name.$phar_ext,$data) === false){
            $this->publishProgress('{"type":"message", "message":"§c' .$file_name.$phar_ext. 'のインストールに失敗しました。"}');
        }else{
            $this->publishProgress('{"type":"message", "message":"§a' .$file_name.$phar_ext. 'としてインストールしました。"}');
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
        if(json_decode($log, true)['type'] == 'redirect'){
            $task = new InstallFromURL(json_decode($log, true)['location'], $this->apiversion, $this->mcpe, $server, $sender);
            $server->getAsyncPool()->submitTask($task);
        }
    }
}
