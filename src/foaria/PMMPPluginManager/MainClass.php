<?php
namespace foaria\PMMPPluginManager;
//使用する関数
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class MainClass extends PluginBase{
  public function onCommand(CommandSender $sender, Command $command, string $label, array $args):bool {
    switch($command){
      case 'ppm':
        if(isset($args[0])){
          //install start
          if($args[0] == 'i' or $args[0] == 'install'){
            if(isset($args[1])){
              //install process start
              $task = new Fetch('a', $args[1], $sender);
              $this->getServer()->getAsyncPool()->submitTask($task);
              //install process end
              return true;
            }else {
              $sender->sendMessage("使い方: /ppm ". $args[0] . " [プラグイン名またはダウンロードリンク]");
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
              $sender->sendMessage("使い方: /ppm ". $args[0] . " [プラグイン名]");
              return true;
            }
          }
        return false;
        }
      return false;
      //ppm end
      case 'ppm-repo':
        if(isset($args[0])){
          //add start
          if($args[0] == 'a' or $args[0] == 'add'){
            if(isset($args[1])){
              $sender->sendMessage('リポジトリを追加しました。');
              return true;
            }else {
              $sender->sendMessage("使い方: /ppm-repo ". $args[0] . " [リポジトリのURL]");
              return true;
            }
          //install end
          }
        return false;
        }
      return false;
      //ppm-repo end
    }
  //switch end
  }
}

class Fetch extends AsyncTask {
  public function __construct(string $url, string $name, CommandSender $sender) {
    $this->requesturl = $url;
    $this->name = $name;
    $this->storeLocal($sender);
  }
  public function onRun() : void {
    $this->publishProgress('プラグインを探しています...');
    $this->setResult('§cプラグインが見つかりませんでした');
  }
  public function onCompletion(Server $server) : void {
    $result = $this->getResult();
    $sender = $this->fetchLocal();
    $sender->sendMessage($result);
  }
  public function onProgressUpdate(Server $server, $log) : void {
    $sender = $this->peekLocal();
    $sender->sendMessage($log);
  }
}
