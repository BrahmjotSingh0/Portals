<?php

declare(strict_types=1);

namespace BrahmjotSingh0\Portals;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use function abs;
use function array_shift;
use function count;
use function implode;
use function max;
use function min;

class Main extends PluginBase implements Listener{

	/** @var array<int|string, mixed> */
	private array $portals = [];
	/** @var array<string, mixed> */
	private array $creatingPortal = [];
	/** @var array<string, mixed> */
	private array $positions = [];

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->saveDefaultConfig();
		$this->portals = $this->getConfig()->get("portals", []);
	}

	public function onDisable() : void{
		$this->savePortals();
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage("This command can only be used in-game.");
			return true;
		}

		if(count($args) < 1){
			$sender->sendMessage("Usage: /portal <create|pos1|pos2|addcommand|msg|delete>");
			return true;
		}

		$subCommand = array_shift($args);

		switch($subCommand){
			case "create":
				if(count($args) < 1){
					$sender->sendMessage("Usage: /portal create <portalname>");
					return true;
				}
				$portalName = array_shift($args);
				if(isset($this->portals[$portalName])){
					$sender->sendMessage("Portal '$portalName' already exists.");
					return true;
				}
				$this->portals[$portalName] = [
					"pos1" => null,
					"pos2" => null,
					"world" => $sender->getWorld()->getFolderName(),
					"commands" => [],
					"message" => ""
				];
				$this->savePortals();
				$this->creatingPortal[$sender->getName()] = $portalName;
				$sender->sendMessage("Creating portal '$portalName'. Use /portal pos1 and /portal pos2 to set the positions.");
				break;
			case "pos1":
				if(isset($this->creatingPortal[$sender->getName()])){
					$this->positions[$sender->getName()]["pos1"] = true;
					$sender->sendMessage("Tap a block to set position 1 for portal '{$this->creatingPortal[$sender->getName()]}'.");
				}else{
					$sender->sendMessage("You need to create a portal first.");
				}
				break;
			case "pos2":
				if(isset($this->creatingPortal[$sender->getName()])){
					$this->positions[$sender->getName()]["pos2"] = true;
					$sender->sendMessage("Tap a block to set position 2 for portal '{$this->creatingPortal[$sender->getName()]}'.");
				}else{
					$sender->sendMessage("You need to create a portal first.");
				}
				break;
			case "addcommand":
				if(count($args) < 3){
					$sender->sendMessage("Usage: /portal addcommand <portalname> <player/server> <command>");
					return true;
				}
				$portalName = array_shift($args);
				$executor = array_shift($args);
				$command = implode(" ", $args);
				if(!isset($this->portals[$portalName])){
					$sender->sendMessage("Portal '$portalName' does not exist.");
					return true;
				}
				$this->portals[$portalName]["commands"][] = ["executor" => $executor, "command" => $command];
				$this->savePortals();
				$sender->sendMessage("Command added to portal '$portalName'.");
				break;
			case "msg":
				if(count($args) < 2){
					$sender->sendMessage("Usage: /portal msg <portalname> <message>");
					return true;
				}
				$portalName = array_shift($args);
				$message = implode(" ", $args);
				if(!isset($this->portals[$portalName])){
					$sender->sendMessage("Portal '$portalName' does not exist.");
					return true;
				}
				$this->portals[$portalName]["message"] = $message;
				$this->savePortals();
				$sender->sendMessage("Message for portal '$portalName' set.");
				break;
			case "delete":
				if(count($args) < 1){
					$sender->sendMessage("Usage: /portal delete <portalname>");
					return true;
				}
				$portalName = array_shift($args);
				if(!isset($this->portals[$portalName])){
					$sender->sendMessage("Portal '$portalName' does not exist.");
					return true;
				}
				unset($this->portals[$portalName]);
				$this->savePortals();
				$sender->sendMessage("Portal '$portalName' deleted.");
				break;
			default:
				$sender->sendMessage("Unknown command. Usage: /portal <create|pos1|pos2|addcommand|msg|delete>.");
				return false;
		}

		return true;
	}

	public function onPlayerMove(PlayerMoveEvent $event) : void{
		$player = $event->getPlayer();
		$position = $player->getPosition();

		foreach($this->portals as $name => $data){
			if(!isset($data["pos1"]) || !isset($data["pos2"])){
				continue; // Skip if pos1 or pos2 are not set
			}

			$pos1 = new Vector3($data["pos1"]["x"], $data["pos1"]["y"], $data["pos1"]["z"]);
			$pos2 = new Vector3($data["pos2"]["x"], $data["pos2"]["y"], $data["pos2"]["z"]);

			if($this->isWithinBounds($position, $pos1, $pos2)){
				if(isset($data["message"])){
					$player->sendMessage($data["message"]);
				}
				if(isset($data["commands"])){
					foreach($data["commands"] as $commandData){
						$executor = $commandData["executor"];
						$command = $commandData["command"];
						if($executor === "player"){
							$this->getServer()->dispatchCommand($player, $command);
						}elseif($executor === "server"){
							$this->getServer()->dispatchCommand(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), $command);
						}
					}
				}
				break;
			}
		}
	}

	public function onPlayerInteract(PlayerInteractEvent $event) : void{
		$player = $event->getPlayer();
		$block = $event->getBlock();

		if(isset($this->positions[$player->getName()]["pos1"])){
			$portalName = $this->creatingPortal[$player->getName()];
			$this->portals[$portalName]["pos1"] = ["x" => $block->getPosition()->getX(), "y" => $block->getPosition()->getY(), "z" => $block->getPosition()->getZ()];
			unset($this->positions[$player->getName()]["pos1"]);
			$this->savePortals();
			$player->sendMessage("Position 1 for portal '$portalName' set.");
		}elseif(isset($this->positions[$player->getName()]["pos2"])){
			$portalName = $this->creatingPortal[$player->getName()];
			$this->portals[$portalName]["pos2"] = ["x" => $block->getPosition()->getX(), "y" => $block->getPosition()->getY(), "z" => $block->getPosition()->getZ()];
			unset($this->positions[$player->getName()]["pos2"]);
			unset($this->creatingPortal[$player->getName()]);
			$this->savePortals();
			$player->sendMessage("Position 2 for portal '$portalName' set and portal saved.");
		}
	}

	private function isWithinBounds(Vector3 $position, Vector3 $pos1, Vector3 $pos2) : bool{
		// Calculate portal dimensions
		$length = abs($pos1->getX() - $pos2->getX());
		$breadth = abs($pos1->getZ() - $pos2->getZ());
		$height = abs($pos1->getY() - $pos2->getY());

		// Adjust tolerance based on portal dimensions
		$toleranceX = $length == 0 ? 0.75 : 0.5;
		$toleranceZ = $breadth == 0 ? 0.75 : 0.5;
		$toleranceY = 0.75; // Height tolerance, considering player height

		return (
			min($pos1->getX(), $pos2->getX()) - $toleranceX <= $position->getX() && $position->getX() <= max($pos1->getX(), $pos2->getX()) + $toleranceX &&
			min($pos1->getY(), $pos2->getY()) - $toleranceY <= $position->getY() && $position->getY() <= max($pos1->getY(), $pos2->getY()) + $toleranceY &&
			min($pos1->getZ(), $pos2->getZ()) - $toleranceZ <= $position->getZ() && $position->getZ() <= max($pos1->getZ(), $pos2->getZ()) + $toleranceZ
		);
	}

	private function savePortals() : void{
		$this->getConfig()->set("portals", $this->portals);
		$this->getConfig()->save();
	}
}
