<?php
namespace foaria\Nkom;
use pocketmine\scheduler\AsyncTask;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\Config;

class ProjectInit extends AsyncTask {
    public function __construct(String $name, Server $server, CommandSender $sender) {
        $this->name = $name;
        $this->storeLocal('server', $server);
        $this->storeLocal('sender', $sender);
    }
    public function onRun() : void {
        
    }
    public function onCompletion() : void {
        $result = $this->getResult();
        $sender = $this->fetchLocal('sender');
        $server = $this->fetchLocal('server');
        $user = $this->fetchLocal('user');
        $result = json_decode($result, true);
        if($result['exit'] = 'token'){
            $user->set($result['registry'], $result['token']);
            $user->save();
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
