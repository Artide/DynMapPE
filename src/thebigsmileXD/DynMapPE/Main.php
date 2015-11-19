<?php

namespace thebigsmileXD\MinigameBase;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityArmorChangeEvent;
use pocketmine\event\entity\EntityInventoryChangeEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\player\PlayerBucketFillEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;

class Main extends PluginBase implements Listener{
	public $database;
	public $useSQL = true;
	public $worlds = [];
	public $messages = false;

	public function onEnable(){
		$this->makeSaveFiles();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if($this->useSQL) $this->connectSQL();
	}

	private function makeSaveFiles(){
		$this->saveDefaultConfig();
		$this->reloadConfig();
		$this->saveResource("config.yml", false);
		$this->saveResource("messages.yml", false);
		$this->reloadConfig();
		$this->getConfig()->save();
		$this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
	}

	/* input handling */
	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		if($sender instanceof Player || $sender instanceof ConsoleCommandSender){ // commands for both console and player
			switch($command->getName()){
				case "commandplayerandconsole":
					{
						if($sender->hasPermission("dynmap.cmd")){
							return true;
						}
						else{
							$sender->sendMessage($this->getTranslation("no-permission"));
							return true;
						}
						return false;
					}
				default:
					return false;
			}
		}
	}

	/* functions */
	public function getTranslation($string){
		return $this->messages->get($string)?$this->messages->get($string):"string '" . $string . "' not found, check config";
	}

	public function connectSQL(){
		$host = $this->getConfig()->getNested("sql.host");
		$user = $this->getConfig()->getNested("sql.user");
		$password = $this->getConfig()->getNested("sql.password");
		$database = $this->getConfig()->getNested("sql.database");
		$port = $this->getConfig()->getNested("sql.port");
		$port?$port:3306;
		$db = new \mysqli($host, $user, $password, $database, $port);
		if($db->connect_errno > 0){
			$this->getLogger()->critical('Unable to connect to database [' . $db->connect_error . ']');
			return false;
		}
		else{
			$this->database = $db;
			$this->getLogger()->info('Successfully connected to database [' . $database . ']');
		}
		$request = "CREATE TABLE `dynmap_players` ( `id` INT NOT NULL AUTO_INCREMENT , `name` VARCHAR(30) NOT NULL , `world` VARCHAR(100) NOT NULL , `x` DOUBLE NOT NULL, `y` DOUBLE NOT NULL, `z` DOUBLE NOT NULL, `health` DOUBLE NOT NULL, `armor` DOUBLE NOT NULL, PRIMARY KEY (`id`), UNIQUE (`name`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci";
		if(!$result = $this->database->query($request)){
			$this->getLogger()->critical('There was an error running the query [' . $this->database->error . ']');
		}
		else{
			$this->getLogger()->notice('Successfully created database');
		}
	}

	/* eventhandler */
	public function onQuit(PlayerQuitEvent $event){
		$request = "DELETE FROM `dynmap_players` WHERE `dynmap_players`.`name` = " . $event->getPlayer()->getName();
		if(!$result = $this->database->query($request)){
			$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
		}
		return;
	}

	public function onMove(PlayerMoveEvent $event){
		$request = "UPDATE `dynmap_players` SET `world` = '" . $event->getPlayer()->getLevel()->getName() . "' ,`x` = '" . $event->getPlayer()->getX() . "',`y` = '" . $event->getPlayer()->getY() . "',`z` = '" . $event->getPlayer()->getZ() . "' WHERE `dynmap_players`.`name` = " . $event->getPlayer()->getName();
		if(!$result = $this->database->query($request)){
			$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
		}
		return;
	}

	public function onBreak(BlockBreakEvent $event){
		if($this->disableBreak) $event->setCancelled();
		return;
	}

	public function onPlace(BlockPlaceEvent $event){
		if($this->disablePlace) $event->setCancelled();
		return;
	}

	public function onChat(PlayerChatEvent $event){
		if($this->disableChat) $event->setCancelled();
		return;
	}

	public function onBucketFill(PlayerBucketFillEvent $event){
		if($this->disableBucketUse) $event->setCancelled();
		return;
	}

	public function onBucketEmpty(PlayerBucketEmptyEvent $event){
		if($this->disableBucketUse) $event->setCancelled();
		return;
	}

	public function onArmorChange(EntityArmorChangeEvent $event){
		if($event->getEntity() instanceof Player){
			$request = "UPDATE `dynmap_players` SET `armor` = '" . $event->getEntity()->getHealth() . "' WHERE `dynmap_players`.`name` = " . $event->getEntity()->getName(); // fix armor is heath
			if(!$result = $this->database->query($request)){
				$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
			}
		}
		return;
	}

	public function onDamage(EntityDamageEvent $event){
		if($event->getEntity() instanceof Player){
			$request = "UPDATE `dynmap_players` SET `health` = '" . $event->getEntity()->getHealth() . "' WHERE `dynmap_players`.`name` = " . $event->getEntity()->getName();
			if(!$result = $this->database->query($request)){
				$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
			}
		}
		return;
	}

	public function onHealthRegeneration(EntityRegainHealthEvent $event){
		if($event->getEntity() instanceof Player){
			$request = "UPDATE `dynmap_players` SET `health` = '" . $event->getEntity()->getHealth() . "' WHERE `dynmap_players`.`name` = " . $event->getEntity()->getName();
			if(!$result = $this->database->query($request)){
				$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
			}
		}
		return;
	}

	public function onTeleport(EntityTeleportEvent $event){
		if($event->getEntity() instanceof Player){
			$request = "UPDATE `dynmap_players` SET `world` = '" . $event->getEntity()->getLevel()->getName() . "' ,`x` = '" . $event->getEntity()->getX() . "',`y` = '" . $event->getEntity()->getY() . "',`z` = '" . $event->getEntity()->getZ() . "' WHERE `dynmap_players`.`name` = " . $event->getEntity()->getName();
			if(!$result = $this->database->query($request)){
				$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
			}
			return;
		}
	}

	public function onLevelChange(EntityLevelChangeEvent $event){
		if($event->getEntity() instanceof Player){
			$request = "UPDATE `dynmap_players` SET `world` = '" . $event->getEntity()->getLevel()->getName() . "' ,`x` = '" . $event->getEntity()->getX() . "',`y` = '" . $event->getEntity()->getY() . "',`z` = '" . $event->getEntity()->getZ() . "' WHERE `dynmap_players`.`name` = " . $event->getEntity()->getName();
			if(!$result = $this->database->query($request)){
				$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
			}
			return;
		}
	}
}