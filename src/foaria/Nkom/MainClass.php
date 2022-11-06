<?php
namespace foaria\Nkom;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\plugin\ApiVersion;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class MainClass extends PluginBase{
    public function onEnable():void{
        $this->saveDefaultConfig();
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        if(!file_exists($this->getDataFolder().'tmp')){
            mkdir($this->getDataFolder().'tmp', 0700, true);
        }
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
              if(str_starts_with($plugin_name, 'https://') or str_starts_with($plugin_name, 'http://')){
                  $task = new InstallFromURL($plugin_name, $apiversion, ProtocolInfo::CURRENT_PROTOCOL, $server, $sender, $this->getDataFolder());
              }else{
                  $plugin_version = null;
                  if(strpos($args[1], '@') != false){
                      $name_no_edit = explode('@', $args[1]);
                      $plugin_name = $name_no_edit[0];
                      $plugin_version = $name_no_edit[1];
                  }
                  $task = new InstallPlugin($this->config->get('registries'), $plugin_name, 'install', $plugin_version, $apiversion, ProtocolInfo::CURRENT_PROTOCOL ,$server, $sender);
              }
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
      case 'nkom-registry':
        if(isset($args[0])){
          //add start
          if($args[0] == 'a' or $args[0] == 'add'){
            if(isset($args[1])){
              $sender->sendMessage('レジストリを追加しました。');
              return true;
            }else {
              $sender->sendMessage("使い方: /nkom-registry ". $args[0] . " [レジストリのURL]");
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
    public function onDisable():void {
        $tmp_glob = glob($this->getDataFolder().'tmp/*');
        if($tmp_glob){
            $this->getLogger()->info('一時ファイルを削除しています...');
            foreach($tmp_glob as $tmpfilename){
                unlink($tmpfilename);
            }
            $this->getLogger()->info('一時ファイルを削除しました。');
        }
    }
}

require 'Fetch/Install.php';
require 'Fetch/InstallFromURL.php';