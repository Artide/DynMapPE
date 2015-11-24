<?php

namespace thebigsmileXD\DynMapPE;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityArmorChangeEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\player\PlayerBucketFillEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\player\PlayerJoinEvent;

class Main extends PluginBase implements Listener{
	public $database;
	public $useSQL = true;
	public $worlds = [];
	public $messages = false;
	public $updateOnBreak = false;
	public $updateOnPlace = false;
	public $updateChat = false;
	public $updateOnBucketUse = false;

	public function onEnable(){
		$this->makeSaveFiles();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if($this->useSQL) $this->connectSQL();
	}

	public function onDisable(){
		if($this->database !== null){
			$request = "DELETE FROM `dynmap_players`";
			if(!$result = $this->database->query($request)){
				$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
			}
			$this->database->query("ALTER TABLE `dynmap_players` AUTO_INCREMENT = 1");
			$this->database->close();
		}
	}

	private function makeSaveFiles(){
		$this->saveDefaultConfig();
		$this->reloadConfig();
		$this->saveResource("config.yml", false);
		$this->saveResource("messages.yml", false);
		$this->reloadConfig();
		$this->getConfig()->save();
		mkdir($this->getDataFolder() . "/data/");
		$this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
	}

	public function onLoad(){
		$this->saveFiles();
	}

	/* input handling */
	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		// if($sender instanceof Player || $sender instanceof ConsoleCommandSender){ // commands for both console and player
		switch($command->getName()){
			case "dynmaprefresh":
				{
					$sender->sendMessage($this->getTranslation("Will update dynmap"));
					if($sender->hasPermission("dynmap.cmd")){
						$this->saveFiles();
						$sender->sendMessage($this->getTranslation("Successfully updated"));
						return true;
					}
					else{
						$sender->sendMessage($this->getTranslation("no-permission"));
					}
					return false;
				}
			default:
				{
					$sender->sendMessage($this->getTranslation("Fail.."));
					return false;
				}
			// }
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
			$this->getLogger()->notice('Successfully created database `dynmap_players`');
		}
		/*
		 * $request = "CREATE TABLE `dynmap_worlds` ( `id` INT NOT NULL AUTO_INCREMENT , `world` VARCHAR(100) NOT NULL , `spawn` DOUBLE NOT NULL, `y` DOUBLE NOT NULL, `z` DOUBLE NOT NULL, `health` DOUBLE NOT NULL, `armor` DOUBLE NOT NULL, PRIMARY KEY (`id`), UNIQUE (`name`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci";
		 * if(!$result = $this->database->query($request)){
		 * $this->getLogger()->critical('There was an error running the query [' . $this->database->error . ']');
		 * }
		 * else{
		 * $this->getLogger()->notice('Successfully created database `dynmap_players`');
		 * }
		 */
	}

	/* eventhandler */
	public function onQuit(PlayerQuitEvent $event){
		$request = "DELETE FROM `dynmap_players` WHERE `dynmap_players`.`name` = '" . $event->getPlayer()->getName() . "'";
		if(!$result = $this->database->query($request)){
			$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
		}
		return;
	}

	public function onJoin(PlayerJoinEvent $event){
		$request = "INSERT INTO `dynmap_players`(`id`, `name`, `world`, `x`, `y`, `z`, `health`, `armor`) VALUES (NULL,'" . $event->getPlayer()->getName() . "','" . $event->getPlayer()->getLevel()->getName() . "','" . $event->getPlayer()->getX() . "','" . $event->getPlayer()->getY() . "','" . $event->getPlayer()->getZ() . "','" . $event->getPlayer()->getHealth() . "','" . $event->getPlayer()->getHealth() . "')";
		if(!$result = $this->database->query($request)){
			$this->getLogger()->critical('There was an error running the query [' . $this->database->error . ']');
		}
		else{
			$this->getLogger()->notice('Successfully added player `' . $event->getPlayer()->getName() . '`');
		}
	}

	public function onMove(PlayerMoveEvent $event){
		$request = "UPDATE `dynmap_players` SET `world` = '" . $event->getPlayer()->getLevel()->getName() . "' ,`x` = '" . $event->getPlayer()->getX() . "',`y` = '" . $event->getPlayer()->getY() . "',`z` = '" . $event->getPlayer()->getZ() . "' WHERE `name` = '" . $event->getPlayer()->getName() . "'";
		if(!$result = $this->database->query($request)){
			$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
		}
		return;
	}

	public function onBreak(BlockBreakEvent $event){
		if($this->updateOnBreak) return;
	}

	public function onPlace(BlockPlaceEvent $event){
		if($this->updateOnPlace) return;
	}

	public function onChat(PlayerChatEvent $event){
		if($this->updateChat) return;
	}

	public function onBucketFill(PlayerBucketFillEvent $event){
		if($this->updateOnBucketUse) return;
	}

	public function onBucketEmpty(PlayerBucketEmptyEvent $event){
		if($this->updateOnBucketUse) return;
	}

	public function onArmorChange(EntityArmorChangeEvent $event){
		if($event->getEntity() instanceof Player){
			$request = "UPDATE `dynmap_players` SET `armor` = '" . $event->getEntity()->getHealth() . "' WHERE `name` = '" . $event->getEntity()->getName() . "'"; // fix armor is heath
			if(!$result = $this->database->query($request)){
				$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
			}
		}
		return;
	}

	public function onDamage(EntityDamageEvent $event){
		if($event->getEntity() instanceof Player){
			$request = "UPDATE `dynmap_players` SET `health` = '" . $event->getEntity()->getHealth() . "' WHERE `name` = '" . $event->getEntity()->getName() . "'";
			if(!$result = $this->database->query($request)){
				$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
			}
		}
		return;
	}

	public function onHealthRegeneration(EntityRegainHealthEvent $event){
		if($event->getEntity() instanceof Player){
			$request = "UPDATE `dynmap_players` SET `health` = '" . $event->getEntity()->getHealth() . "' WHERE `name` = '" . $event->getEntity()->getName() . "'";
			if(!$result = $this->database->query($request)){
				$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
			}
		}
		return;
	}

	public function onTeleport(EntityTeleportEvent $event){
		if($event->getEntity() instanceof Player){
			$request = "UPDATE `dynmap_players` SET `world` = '" . $event->getEntity()->getLevel()->getName() . "' ,`x` = '" . $event->getEntity()->getX() . "',`y` = '" . $event->getEntity()->getY() . "',`z` = '" . $event->getEntity()->getZ() . "' WHERE `name` = '" . $event->getEntity()->getName() . "'";
			if(!$result = $this->database->query($request)){
				$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
			}
			return;
		}
	}

	public function onLevelChange(EntityLevelChangeEvent $event){
		if($event->getEntity() instanceof Player){
			$request = "UPDATE `dynmap_players` SET `world` = '" . $event->getEntity()->getLevel()->getName() . "' ,`x` = '" . $event->getEntity()->getX() . "',`y` = '" . $event->getEntity()->getY() . "',`z` = '" . $event->getEntity()->getZ() . "',`health` = '" . $event->getEntity()->getHealth() . "',`armor` = '" . $event->getEntity()->getHealth() . "' WHERE `name` = '" . $event->getEntity()->getName() . "'"; // added health and armor because manyworlds, fix armor!
			if(!$result = $this->database->query($request)){
				$this->getLogger()->notice('There was an error running the query [' . $this->database->error . ']');
			}
			return;
		}
	}

	public function chunkUpdate(){}

	public function saveFiles(){
		foreach($this->getServer()->getLevels() as $level){
			@mkdir($this->getDataFolder() . "/data/" . $level->getName());
			$this->getServer()->broadcastMessage($this->getDataFolder() . "/data/" . $level->getName());
			foreach($level->getChunks() as $chunk){
				if($chunk->isLoaded()){
					$chunkx = $chunk->getX();
					$chunkz = $chunk->getZ();
					$buffer = str_repeat("\0", 384);
					for($x = 0; $x < 16; $x++){
						for($z = 0; $z < 16; $z++){
							$buffer{($x << 4) | $z} = $chunk->getHighestBlockAt($x, $z);
							$offset = 0x100 | ($x << 3) | ($z >> 1);
							$andMask = ($z & 1)?"\x0F":"\xF0";
							$damage = $chunk->getHighestBlockAt($x, $z);
							$orMask = ($z & 1)?chr($damage << 4):chr($damage & 0x0F);
							$buffer{$offset} &= $andMask;
							$buffer{$offset} |= $orMask;
						}
					}
					$this->getServer()->broadcastMessage($this->getDataFolder() . "/data/" . $level->getName() . " / " . $chunkx . ":" . $chunkz . ".dat");
					file_put_contents($this->getDataFolder() . "/data/" . $level->getName() . " / " . $chunkx . ":" . $chunkz . ".dat", $buffer);
				}
			}
		}
	}
}