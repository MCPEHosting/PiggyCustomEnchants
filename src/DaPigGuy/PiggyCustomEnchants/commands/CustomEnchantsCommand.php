<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyCustomEnchants\commands;

use DaPigGuy\PiggyCustomEnchants\CustomEnchantManager;
use DaPigGuy\PiggyCustomEnchants\enchants\CustomEnchant;
use DaPigGuy\PiggyCustomEnchants\PiggyCustomEnchants;
use DaPigGuy\PiggyCustomEnchants\utils\Utils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\ItemIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;

class CustomEnchantsCommand extends Command
{

    public function __construct(private readonly PiggyCustomEnchants $plugin)
    {
        parent::__construct("customenchantments", "Use CustomEnchantments", "/ce <enchant|info|list|nbt|remove>", ["ce"]);
        $this->setPermission("piggycustomenchants.command");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (count($args) <= 0) {
            $sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
            return;
        }


        switch (array_shift($args)) {
            case "enchant":
                if (!$this->testPermission($sender, "piggycustomenchants.command.ce.enchant")) return;

                if ((!$sender instanceof Player || count($args) <= 0/*empty($args["player"])) || !isset($args["enchantment"]*/)) {
                    $sender->sendMessage("Usage: /ce enchant <enchantment> <optional: level> <optional: player>");
                    return;
                }


                $level = 1;
                if (count($args) > 1) {
                    if (!is_numeric($args[1])) {
                        $sender->sendMessage(TextFormat::RED . "Enchantment level must be an integer");
                        return;
                    }

                    $level = intval($args[1]);
                }

                $target = count($args) > 2 ? $this->plugin->getServer()->getPlayerByPrefix($args[2])  : $sender;

                if (!$target instanceof Player) {
                    $sender->sendMessage(TextFormat::RED . "Invalid player.");
                    return;
                }
                $enchant = CustomEnchantManager::getEnchantmentByName($args[0]);
                if ($enchant === null) {
                    $sender->sendMessage(TextFormat::RED . "Invalid enchantment.");
                    return;
                }
                $item = $target->getInventory()->getItemInHand();
                if (!$sender->hasPermission("piggycustomenchants.overridecheck")) {
                    if (!Utils::itemMatchesItemType($item, $enchant->getItemType())) {
                        $sender->sendMessage(TextFormat::RED . "The item is not compatible with this enchant.");
                        return;
                    }
                    if ($level > $enchant->getMaxLevel()) {
                        $sender->sendMessage(TextFormat::RED . "The max level is " . $enchant->getMaxLevel() . ".");
                        return;
                    }
                    if ($item->getCount() > 1) {
                        $sender->sendMessage(TextFormat::RED . "You can only enchant one item at a time.");
                        return;
                    }
                    if (!Utils::checkEnchantIncompatibilities($item, $enchant)) {
                        $sender->sendMessage(TextFormat::RED . "This enchant is not compatible with another enchant.");
                        return;
                    }
                }
                if ($item->getId() === ItemIds::ENCHANTED_BOOK || $item->getId() === ItemIds::BOOK) {
                    $item->getNamedTag()->setString("PiggyCEBookUUID", Uuid::uuid4()->toString());
                }
                $item->addEnchantment(new EnchantmentInstance($enchant, $level));
                $sender->sendMessage(TextFormat::GREEN . "Item successfully enchanted.");
                $target->getInventory()->setItemInHand($item);

                break;

            case "info":
                if (!$this->testPermission($sender, "piggycustomenchants.command.ce.list")) return;

                if (count($args) <= 0) {
                    $sender->sendMessage("/ce info <enchantment>");
                    return;
                }
                $enchantment = CustomEnchantManager::getEnchantmentByName($args[0]);
                if ($enchantment === null) {
                    $sender->sendMessage(TextFormat::RED . "Invalid enchantment.");
                    return;
                }
                $sender->sendMessage(TextFormat::GREEN . $enchantment->getDisplayName() . TextFormat::EOL . TextFormat::RESET . "ID: " . $enchantment->getId() . TextFormat::EOL . "Description: " . $enchantment->getDescription() . TextFormat::EOL . "Type: " . Utils::TYPE_NAMES[$enchantment->getItemType()] . TextFormat::EOL . "Rarity: " . Utils::RARITY_NAMES[$enchantment->getRarity()] . TextFormat::EOL . "Max Level: " . $enchantment->getMaxLevel());
                break;

            case "list":
                if (!$this->testPermission($sender, "piggycustomenchants.command.ce.list")) return;
                $sender->sendMessage($this->getCustomEnchantList());
                break;

            case "nbt":
                if (!$this->testPermission($sender, "piggycustomenchants.command.ce.nbt")) return;
                if ($sender instanceof Player) {
                    $sender->sendMessage($sender->getInventory()->getItemInHand()->getNamedTag()->toString());
                    return;
                }
                $sender->sendMessage(TextFormat::RED . "Please use this in-game.");
                break;

            case "remove":
                if (!$this->testPermission($sender, "piggycustomenchants.command.ce.remove")) return;

                if ((!$sender instanceof Player || count($args) <= 0/*empty($args["player"])) || !isset($args["enchantment"]*/)) {
                    $sender->sendMessage("Usage: /ce remove <enchantment> <player>");
                    return;
                }


                $target = count($args) > 1 ? $this->plugin->getServer()->getPlayerByPrefix($args[1]) : $sender;
                if (!$target instanceof Player) {
                    $sender->sendMessage(TextFormat::RED . "Invalid player.");
                    return;
                }
                $enchant = CustomEnchantManager::getEnchantmentByName($args[0]);
                if ($enchant === null) {
                    $sender->sendMessage(TextFormat::RED . "Invalid enchantment.");
                    return;
                }
                $item = $target->getInventory()->getItemInHand();
                if ($item->getEnchantment($enchant) === null) {
                    $sender->sendMessage(TextFormat::RED . "Item does not have specified enchantment.");
                    return;
                }
                $item->removeEnchantment($enchant);
                $sender->sendMessage(TextFormat::GREEN . "Enchantment successfully removed.");
                $target->getInventory()->setItemInHand($item);
                break;
            case null:
            default:
                $sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
        }
    }

    /**
     * @return CustomEnchant[][]
     */
    public function getEnchantmentsByType(): array
    {
        $enchantmentsByType = [];
        foreach (CustomEnchantManager::getEnchantments() as $enchantment) {
            if (!isset($enchantmentsByType[$enchantment->getItemType()])) $enchantmentsByType[$enchantment->getItemType()] = [];
            $enchantmentsByType[$enchantment->getItemType()][] = $enchantment;
        }
        return array_map(function (array $typeEnchants) {
            uasort($typeEnchants, function (CustomEnchant $a, CustomEnchant $b) {
                return strcmp($a->getDisplayName(), $b->getDisplayName());
            });
            return $typeEnchants;
        }, $enchantmentsByType);
    }

    public function getCustomEnchantList(): string
    {
        $enchantmentsByType = $this->getEnchantmentsByType();
        $listString = "";
        foreach (Utils::TYPE_NAMES as $type => $name) {
            if (isset($enchantmentsByType[$type])) {
                $listString .= TextFormat::EOL . TextFormat::GREEN . TextFormat::BOLD . Utils::TYPE_NAMES[$type] . TextFormat::EOL . TextFormat::RESET;
                $listString .= implode(", ", array_map(function (CustomEnchant $enchant) {
                    return $enchant->getDisplayName();
                }, $enchantmentsByType[$type]));
            }
        }
        return $listString;
    }

    /*public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        /*$subcommands = array_values(array_map(function (BaseSubCommand $subCommand): string {
            return $subCommand->getName();
        }, $this->getSubCommands()));
        if ($sender instanceof Player && $this->plugin->areFormsEnabled()) {
            $form = new SimpleForm(function (Player $player, ?int $data) use ($subcommands): void {
                if ($data !== null && isset($subcommands[$data])) {
                    $this->plugin->getServer()->dispatchCommand($player, "ce " . $subcommands[$data]);
                }
            });
            $form->setTitle(TextFormat::GREEN . "PiggyCustomEnchants Menu");
            foreach ($subcommands as $subcommand) $form->addButton(ucfirst($subcommand));
            $sender->sendForm($form);
            return;
        }
        $sender->sendMessage("Usage: /ce <" . implode("|", $subcommands) . ">");
    }

    public function prepare(): void
    {
        $this->registerSubCommand(new AboutSubCommand($this->plugin, "about", "Displays basic information about the plugin"));
        $this->registerSubCommand(new EnchantSubCommand($this->plugin, "enchant", "Apply an enchantment on an item"));
        $this->registerSubCommand(new InfoSubCommand($this->plugin, "info", "Get info on a custom enchant"));
        $this->registerSubCommand(new ListSubCommand($this->plugin, "list", "Lists all registered custom enchants"));
        $this->registerSubCommand(new NBTSubCommand($this->plugin, "nbt", "Displays NBT tags of currently held item"));
        $this->registerSubCommand(new RemoveSubCommand($this->plugin, "remove", "Remove an enchantment from an item"));
    }

    public function getPermissions(): array
    {
        return ["piggycustomenchants.command"];
    }*/
}
