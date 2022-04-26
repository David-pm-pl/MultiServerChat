<?php

namespace davidglitch04\MultiServerChat\Updater;

use davidglitch04\MultiServerChat\Loader;
use pocketmine\Server;

class Utils{
    /**
     * handleUpdateInfo function
     *
     * @param Array $data
     * @return void
     */
    public static function handleUpdateInfo(Array $data): void
    {
        $plugin = Loader::getInstance();
        Server::getInstance()->getLogger()->debug("Handling latest update data.");
        if ($data["Error"] !== '') {
            Server::getInstance()->getLogger()->warning("Failed to get latest update data, Error: " . $data["Error"] . " Code: " . $data["httpCode"]);
            return;
        }
        if (array_key_exists("version", $data["Response"]) && array_key_exists("time", $data["Response"]) && array_key_exists("link", $data["Response"])) {
            $update = Utils::compareVersions(strtolower($plugin->getDescription()->getVersion()), strtolower($data["Response"]["version"]));
            if ($update == 0) {
                Server::getInstance()->getLogger()->debug("Plugin up-to-date !");
                return;
            }
            if ($update > 0) {
                $lines = explode("\n", $data["Response"]["patch_notes"]);
                Server::getInstance()->getLogger()->warning("--- UPDATE AVAILABLE ---");
                Server::getInstance()->getLogger()->warning("§cVersion     :: " . $data["Response"]["version"]);
                Server::getInstance()->getLogger()->warning("§bReleased on :: " . date("d-m-Y", intval($data["Response"]["time"])));
                Server::getInstance()->getLogger()->warning("§aPatch Notes :: " . $lines[0]);
                for ($i = 1; $i < sizeof($lines); $i++) {
                    Server::getInstance()->getLogger()->warning("                §c" .$lines[$i]);
                }
                Server::getInstance()->getLogger()->warning("§dUpdate Link :: " . $data["Response"]["link"]);
				if ($plugin->getConfig()->get("enableUpdateAutoUpdater") !== true) $plugin->getLogger()->warning("§cEnable the download_updates option in config.yml to automatically download and install updates.");
            } else {
                if ($update < 0) Server::getInstance()->getLogger()->debug("Running a build not yet released, this can cause un intended side effects (including possible data loss)");
                return;
            }
			if ($plugin->getConfig()->get("enableUpdateAutoUpdater")){
				Server::getInstance()->getLogger()->warning("§cDownloading & Installing Update, please do not abruptly stop server/plugin.");
				Server::getInstance()->getLogger()->debug("Begin download of new update from '".$data["Response"]["download_link"]."'.");
				Utils::downloadUpdate($data["Response"]["download_link"]);
			}
        } else {
            Server::getInstance()->getLogger()->warning("Failed to verify update data/incorrect format provided.");
            return;
        }
    }

    /**
     * downloadUpdate function
     *
     * @param string $url
     * @return void
     */
    protected static function downloadUpdate(string $url) : void {
        $plugin = Loader::getInstance();
        @mkdir($plugin->getDataFolder()."tmp/");
        $path = $plugin->getDataFolder()."tmp/MultiServerChat.phar";
        Server::getInstance()->getAsyncPool()->submitTask(new DownloadFile($plugin, $url, $path));
    }
    /**
     * compareVersions function
     *
     * @param string $base
     * @param string $new
     * @return integer
     */
	public static function compareVersions(string $base, string $new) : int {
        $baseParts = explode(".",$base);
        $baseParts[2] = explode("-beta",$baseParts[2])[0];
        if(sizeof(explode("-beta",explode(".",$base)[2])) >1){
            $baseParts[3] = explode("-beta",explode(".",$base)[2])[1];
        }
        $newParts = explode(".",$new);
        $newParts[2] = explode("-beta",$newParts[2])[0];
        if(sizeof(explode("-beta",explode(".",$new)[2])) >1){
            $newParts[3] = explode("-beta",explode(".",$new)[2])[1];
        }
        if(intval($newParts[0]) > intval($baseParts[0])){
            return 1;
        }
        if(intval($newParts[0]) < intval($baseParts[0])){
            return -1;
        }
        if(intval($newParts[1]) > intval($baseParts[1])){
            return 1;
        }
        if(intval($newParts[1]) < intval($baseParts[1])){
            return -1;
        }
        if(intval($newParts[2]) > intval($baseParts[2])){
            return 1;
        }
        if(intval($newParts[2]) < intval($baseParts[2])){
            return -1;
        }
        if(isset($baseParts[3])){
            if(isset($newParts[3])){
                if(intval($baseParts[3]) > intval($newParts[3])){
                    return -1;
                }
                if(intval($baseParts[3]) < intval($newParts[3])){
                    return 1;
                }
            } else {
                return 1;
            }
        }
        return 0;
    }
	/**
     * handleDownload function
     *
     * @param string $path
     * @param integer $status
     * @return void
     */
	public static function handleDownload(string $path, int $status) : void {
        $plugin = Loader::getInstance();
        Server::getInstance()->getLogger()->debug("Update download complete, at '".$path."' with status '".$status."'");
        if($status !== 200){
            Server::getInstance()->getLogger()->warning("Received status code '".$status."' when downloading update, update cancelled.");
            Utils::rmalldir($plugin->getDataFolder()."/tmp");
            return;
        }
        @rename($path, Server::getInstance()->getPluginPath()."/Loader.phar");
        if(Utils::getFileName() === null){
            Server::getInstance()->getLogger()->debug("Deleting previous Loader version...");
            Utils::rmalldir($plugin->getFileHack());
            Server::getInstance()->getLogger()->warning("Update complete, restart your server to load the new updated version.");
            return;
        }
        @rename(Server::getInstance()->getPluginPath()."/".Utils::getFileName(), Server::getInstance()->getPluginPath()."/Loader.phar.old"); //failsafe i guess.
        Server::getInstance()->getLogger()->warning("Update complete, restart your server to load the new updated version.");
        return;
    }
	/**
     * rmalldir function
     *
     * @param string $dir
     * @return void
     */
	public static function rmalldir(string $dir) : void {
        if($dir == "" or $dir == "/" or $dir == "C:/") return; //tiny safeguard.
        $tmp = scandir($dir);
        foreach ($tmp as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                Utils::rmalldir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
	/**
     * getFileName function
     *
     * @return string|null
     */
	private static function getFileName(){
        $plugin = Loader::getInstance();
        $path = $plugin->getFileHack();
        if(substr($path, 0, 7) !== "phar://") return null;
        $tmp = explode("\\", $path);
        $tmp = end($tmp); //requires reference, so cant do all in one
        return str_replace("/","",$tmp);
    }
}
