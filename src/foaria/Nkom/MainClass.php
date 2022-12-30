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
        $this->user = new Config($this->getDataFolder() . "user.yml", Config::YAML);
        $this->projects = new Config($this->getDataFolder() . "projects.yml", Config::YAML);
        chmod($this->getDataFolder() . "user.yml", 0700);
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
                    $datapath = $this->getServer()->getDataPath();
                    if(str_starts_with($plugin_name, 'https://') or str_starts_with($plugin_name, 'http://')){
                        $task = new InstallFromURL($plugin_name, $apiversion, ProtocolInfo::CURRENT_PROTOCOL, $server, $sender, $this->getDataFolder(), $datapath);
                    }else{
                        $plugin_version = null;
                        if(strpos($args[1], '@') != false){
                            $name_no_edit = explode('@', $args[1]);
                            $plugin_name = $name_no_edit[0];
                            $plugin_version = $name_no_edit[1];
                        }
                    $task = new InstallPlugin($this->config->get('registries'), $plugin_name, 'install', $plugin_version, $apiversion, ProtocolInfo::CURRENT_PROTOCOL ,$server, $sender, $datapath);
                    }
                    $server->getAsyncPool()->submitTask($task);
                    //install process end
                    return true;
                    }else{
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
                }else if($args[0] == 'remove' or $args[0] == 'rm'){
                    if(isset($args[1])){
                        $server = $this->getServer();
                        //update process start
                        $sender->sendMessage('該当するプラグインを検索しています...');
                        $task = new RemovePlugin($this->config->get('registries'), $args[1], 'remove', $server, $sender);
                        $server->getAsyncPool()->submitTask($task);
                        //update process end
                        return true;
                    }else {
                        $sender->sendMessage("使い方: /nkom ". $args[0] . " [プラグイン名]");
                        return true;
                    }
                }else if($args[0] == 'login' or $args[0] == 'adduser' or $args[0] == 'add-user'){
                    $server = $this->getServer();
                    $task = new Login($this->config->get('registries'), $server, $sender, $this->user);
                    $server->getAsyncPool()->submitTask($task);
                    return true;
                }else if($args[0] == 'whoami'){
                    $server = $this->getServer();
                    $task = new WhoAmI($this->config->get('registries'), $server, $sender, $this->user);
                    $server->getAsyncPool()->submitTask($task);
                    return true;
                }else if($args[0] == 'logout'){
                    $server = $this->getServer();
                    $task = new Logout($this->config->get('registries'), $server, $sender, $this->user);
                    $server->getAsyncPool()->submitTask($task);
                    return true;
                }else if($args[0] == 'init'){
                    if(!isset($args[1])){
                        $sender->sendMessage("/nkom ". $args[0] . ": プロジェクトを作成して、プラグインのテンプレートを生成します。");
                        $sender->sendMessage("使い方: /nkom ". $args[0] . " [プラグイン名]");
                        return true;
                    }
                    if($this->getServer()->getPluginManager()->getPlugin("DevTools") == null){
                        $sender->sendMessage("このコマンドを利用するにはDevToolsが必要です。");
                        $sender->sendMessage("以下のコマンドでDevTools v1.16.0をインストールできます。");
                        $sender->sendMessage("/nkom install https://poggit.pmmp.io/r/193473/PocketMine-DevTools.phar");
                        return true;
                    }
                    $server = $this->getServer();
                    $task = new ProjectInit($this->config->get('registries'), $args[1], $server, $server->getDataPath(), $sender, $this->user, $this->projects);
                    $server->getAsyncPool()->submitTask($task);
                    return true;
                }else if($args[0] == 'importproj'){
                    $server = $this->getServer();
                    if(!isset($args[1])){
                        $sender->sendMessage("/nkom ". $args[0] . ": 既存のプラグインをプロジェクトとして追加します。");
                        $sender->sendMessage("使い方: /nkom ". $args[0] . " [プラグインのディレクトリ名]");
                        return true;
                    }
                    if(!is_dir($server->getDataPath().'plugins/'.$args[1])){
                        $sender->sendMessage("§cプロジェクトとして追加できるのはフォルダプラグインのみです。");
                        return true;
                    }
                    $this->projects->set($args[1], $server->getDataPath().'plugins/'.$args[1]);
                    $this->projects->save();
                    $sender->sendMessage('§a'.$args[1].'をプロジェクトとして追加しました。');
                    return true;
                }
            return false;
            }
          return false;
          //nkom end
          case 'nkom-reg':
            if(isset($args[0])){
              //add start
              if($args[0] == 'a' or $args[0] == 'add'){
                if(isset($args[1])){
                  $sender->sendMessage('レジストリを追加しました。');
                  return true;
                }else {
                  $sender->sendMessage("使い方: /nkom-reg ". $args[0] . " [レジストリのURL]");
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
require 'Remove.php';
require 'Registry/Login.php';
require 'Registry/WhoAmI.php';
require 'Registry/Logout.php';
require 'Project/Init.php';