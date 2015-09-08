<?php

class Ancillary{

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

	public function getDiscount(array $user_information){
		$discount = 100;
		for($i = 1; $i <= $user_information["lvl"]; $i++) 
			$discount = $discount -($discount * 0.02);
		return round(100 - $discount, 3);
	}
	
	public function getAllInventory($inventory, $potions){
		try{
			if(!is_bool($inventory) && !is_array($inventory)){
				 throw new Exception('Параметр inventory должен быть либо bool, либо array.');
			}
			if(!is_bool($potions) && !is_array($potions)){
				 throw new Exception('Параметр potions должен быть либо bool, либо array.');
			}
		}
		catch (Exception $e) {
			$exception = array("text" => $e->getMessage(), "file" => $e->getFile(), "method" => __METHOD__, "line" => $e->getLine());
			echo $this->db->getReplaceTemplate($exception, "exception");
		}
		
		//Вытаскивает все вещи находящиеся в $inventory из базы
		if($inventory){
			foreach($inventory as $key => $inventory_item){
				if($inventory_item == "999")
					break;
				if(!is_numeric($inventory_item) && (int) $key == 0){
					$inventory_item = (array) json_decode($inventory_item);
					if($inventory_item["id"] < 500){
						$weapons[] = $inventory_item["id"];
					}
					if($inventory_item["id"] > 500 && $inventory_item["id"] < 1000){
						$armors[] = $inventory_item["id"];
					}
				}
			}
			if(count($weapons) > 0)
				$weapons_data = $this->db->selectIN("shop_weapon", "id", $weapons);
			if(count($armors) > 0)
				$armors_data = $this->db->selectIN("shop_armor", "id", $armors);
			$resultData["inventory"] = array_merge($weapons_data, $armors_data);
		}
		//Вытаскивает все вещи находящиеся в $potions из базы
		if($potions){
			$count = count($potions);
			for($i = 1; $i < $count; $i++){
				if($potions["potion$i"] == "999")
					break;
				if($potions["potion$i"] != "0"){
					$inventory_item = (array) json_decode($potions["potion$i"]);
					$potionsData[] = $inventory_item["id"];
				}
			}
			if(count($potionsData) > 0)
				$resultData["potions"] = $this->db->selectIN("shop_something", "id", $potionsData);
		}
		
		return $resultData;
	}
	
	public function getDamageInformation(array $user, array $damageInformation, $returnArray = false){
		// $returnArray нужен для получения инфы после снятия/надеваняи вещи в inventoryFunctions->toggle
		$user["armorTypes"] = array(1 => 0, 2 => 0, 3 => 0);
		foreach($damageInformation as $key => $thing){
			if($key != "primaryWeapon" && $key != "secondaryWeapon"){
				$totalArmor += $thing["armor"];
				if($thing["type_defence"] != 0)
					$user["armorTypes"][(int)$thing["type_defence"]] = 1;
			}
				
			$user['Strengh'] += $thing["bonus_strengh"];
			$user['Defence'] += $thing["bonus_defence"];
			$user['Agility'] += $thing["bonus_agility"];
			$user['Physique'] += $thing["bonus_physique"];
			$user['Mastery'] += $thing["bonus_mastery"];
		}
		
        $sr["damage"] = round($damageInformation["primaryWeapon"]["damage"] * $user["Strengh"], 0);
        $sr["damageHeavy"] = round($this->getDamageBonus($damageInformation["primaryWeapon"]["type_damage"], array(1 => 0, 2 => 0, 3 => 1) ) * $sr["damage"], 0);
        $sr["damageMedium"] = round($this->getDamageBonus($damageInformation["primaryWeapon"]["type_damage"], array(1 => 0, 2 => 1, 3 => 0) ) * $sr["damage"], 0);
        $sr["damageLight"] = round($this->getDamageBonus($damageInformation["primaryWeapon"]["type_damage"], array(1 => 1, 2 => 0, 3 => 0) ) * $sr["damage"], 0);
		
        $sr["armor"] = round($totalArmor * $user["Defence"], 0);
        $sr["armorPiercing"] = round($this->getDamageBonus(1, $user["armorTypes"]) * $sr["armor"] , 0);
        $sr["armorCutting"] = round($this->getDamageBonus(2, $user["armorTypes"]) * $sr["armor"] , 0);
        $sr["armorMaces"] = round($this->getDamageBonus(3, $user["armorTypes"]) * $sr["armor"] , 0);
		
		if($returnArray)
			return $sr;
		
        return $this->db->getReplaceTemplate($sr, "damageInformation");
    }
	
    public function getAllArmors($user){
        //изменение, попытка 2
        $where = "";
        $limit = 0;
        if(is_array($user["armor"])){
            $where .= "`id`='".$user["armor"]["id"]."'";
            $userEquip[] = "armor";
            $limit++;
        }
        if(is_array($user["helmet"])){
            $where .= " || `id`='".$user["helmet"]["id"]."'";
            $userEquip[] = "helmet";
            $limit++;
        }
        if(is_array($user["bracers"])){
            $where .= " || `id`='".$user["bracers"]["id"]."'";
            $userEquip[] = "bracers";
            $limit++;
        }
        if(is_array($user["leggings"])){
            $where .= " || `id`='".$user["leggings"]["id"]."'";
            $userEquip[] = "leggings";
            $limit++;
        }
        if(is_array($user["secondaryWeapon"]) and $user["secondaryWeapon"]["id"] > 500){
            $where .= " || `id`='".$user["secondaryWeapon"]["id"]."'";
            $userEquip[] = "secondaryWeapon";
            $limit++;
        }
        if($where != "")
            $allArmors = $this->db->select("armor", array("*"), $where, "", "", $limit);
        return array($allArmors, $userEquip);
    }

    public function getInfo($id, $arrays = false){
        if(!$arrays) {
            $user = $this->getUserInfo($id);
            $userInventory = $this->db->getElementOnID("user_inventory", $id, true);
        }
        else{
            $user = $arrays["user"];
            $userInventory = $arrays["userInventory"];
        }
        //Экипировка
        for($i = 1; $i <= 24; $i++){
            $invItem = unserialize($userInventory["slot$i"]);
            if( $invItem["hash"] == $user["armor"]) $user["armor"] = unserialize($userInventory["slot$i"]);
            if( $invItem["hash"] == $user["helmet"]) $user["helmet"] = unserialize($userInventory["slot$i"]);
            if( $invItem["hash"] == $user["leggings"]) $user["leggings"] = unserialize($userInventory["slot$i"]);
            if( $invItem["hash"] == $user["bracers"]) $user["bracers"] = unserialize($userInventory["slot$i"]);
            if( $invItem["hash"] == $user["primaryWeapon"]) $user["primaryWeapon"] = unserialize($userInventory["slot$i"]);
            if( $invItem["hash"] == $user["secondaryWeapon"]) $user["secondaryWeapon"] = unserialize($userInventory["slot$i"]);
        }
        unset($invItem);
        $allArmors = $this->getAllArmors($user);
        $userEquip = $allArmors[1];
        $allArmors = $allArmors[0];

        $count = count($allArmors);
        for($i = 0; $i <= $count; $i++){
            if($allArmors[$i]["thing"] == 2){
                $armorLvl =  $user["armor"]["armor"];
                $user["armor"] = $allArmors[$i];
                $user["armor"]["defence"] = $this->getBonus($user["armor"]["defence"],  $armorLvl);
                $user["armor"]["armorLvl"] = $armorLvl;
            }
            if($allArmors[$i]["thing"] == 3){
                $armorLvl =  $user["helmet"]["armor"];
                $user["helmet"] = $allArmors[$i];
                $user["helmet"]["defence"] = $this->getBonus($user["helmet"]["defence"],  $armorLvl);
                $user["helmet"]["armorLvl"] = $armorLvl;
            }
            if($allArmors[$i]["thing"] == 4){
                $armorLvl =  $user["leggings"]["armor"];
                $user["leggings"] = $allArmors[$i];
                $user["leggings"]["defence"] = $this->getBonus($user["leggings"]["defence"],  $armorLvl);
                $user["leggings"]["armorLvl"] = $armorLvl;
            }
            if($allArmors[$i]["thing"] == 5){
                $armorLvl =  $user["bracers"]["armor"];
                $user["bracers"] = $allArmors[$i];
                $user["bracers"]["defence"] = $this->getBonus($user["bracers"]["defence"],  $armorLvl);
                $user["bracers"]["armorLvl"] = $armorLvl;
            }
            if($allArmors[$i]["thing"] == 6){
                $armorLvl =  $user["secondaryWeapon"]["armor"];
                $user["secondaryWeapon"]= $allArmors[$i];
                $user["secondaryWeapon"]["defence"] = $this->getBonus($user["secondaryWeapon"]["defence"],$armorLvl);
                $user["secondaryWeapon"]["armorLvl"] = $armorLvl;
            }
        }
        if($user["primaryWeapon"] != 0){
            $critLvl = $user["primaryWeapon"]["crit"];
            $damageLvl = $user["primaryWeapon"]["damage"];
            $user["primaryWeapon"] = $this->db->getAllOnField("weapon", "id", $user["primaryWeapon"]["id"], "", "");
            $user["primaryWeapon"]["damage"] = $this->getBonus($user["primaryWeapon"]["damage"], $damageLvl);
            $user["primaryWeapon"]["crit"] = $this->getBonus($user["primaryWeapon"]["crit"], $critLvl);
            $user["primaryWeapon"]["critLvl"] =  $critLvl;
            $user["primaryWeapon"]["damageLvl"] =  $damageLvl;
        }
        if($user["secondaryWeapon"] != 0 and $user["secondaryWeapon"]["id"] < 500){
            $critLvl = $user["secondaryWeapon"]["crit"];
            $damageLvl = $user["secondaryWeapon"]["damage"];
            $user["secondaryWeapon"] = $this->db->getAllOnField("weapon", "id",$user["secondaryWeapon"]["id"] , "", "");
            $user["secondaryWeapon"]["damage"] = $this->getBonus($user["secondaryWeapon"]["damage"], $damageLvl);
            $user["secondaryWeapon"]["crit"] = $this->getBonus($user["secondaryWeapon"]["crit"], $critLvl);
            $user["secondaryWeapon"]["critLvl"] =  $critLvl;
            $user["secondaryWeapon"]["damageLvl"] =  $damageLvl;
        }
        $userTotalArmor = 0;
        $userArmorTypes = array(1 => 0, 2 => 0, 3 => 0);
        if(is_null($userEquip))
            return false;
        foreach($userEquip as $key => $value){
            if($key != "secondaryWeapon"){
                $userTotalArmor += $user[$value]["defence"];
                $userArmorTypes[$user[$value]["typeDefence"]] = 1;
            }
            $user['Strengh'] +=  $user[$value]["bonusstr"];
            $user['Defence'] += $user[$value]["bonusdef"];
            $user['Agility'] +=  $user[$value]["bonusag"];
            $user['Physique'] +=  $user[$value]["bonusph"];
            $user['Mastery']  +=$user[$value]["bonusms"];
        }

        //Характеристики
        $user['Strengh'] +=  $user["primaryWeapon"]["bonusstr"] + $user["secondaryWeapon"]["bonusstr"];
        $user['Defence'] +=  $user["primaryWeapon"]["bonusdef"] + $user["secondaryWeapon"]["bonusdef"];
        $user['Agility'] +=  $user["primaryWeapon"]["bonusag"] + $user["secondaryWeapon"]["bonusag"];
        $user['Physique'] += $user["primaryWeapon"]["bonusph"] + $user["secondaryWeapon"]["bonusph"];
        $user['Mastery']  +=  $user["primaryWeapon"]["bonusms"] + $user["secondaryWeapon"]["bonusms"];

        return array("user" => $user, "totalArmor" => $userTotalArmor, "armorTypes" => $userArmorTypes);
    }

    private function getBonus($char, $lvl){
        $modificator = 1;
        for($i = 0; $i <= $lvl; $i++){
            $modificator += 0.05;
        }
        $result = round($char * $modificator, 2);
        return $result;
    }

    public function getDamageBonus($typedamage, array $armorTypes){
        $damageBonus = 1;
		$typedamage = (int) $typedamage;
        if($typedamage == 1){
            if($armorTypes[1] == 1) $damageBonus += 0;
            if($armorTypes[2] == 1) $damageBonus += 0.25;
            if($armorTypes[3] == 1) $damageBonus -= 0.25;
        }
        if($typedamage == 2){
            if($armorTypes[1] == 1) $damageBonus -= 0.25;
            if($armorTypes[2] == 1) $damageBonus += 0;
            if($armorTypes[3] == 1) $damageBonus -= 0.25;
        }
        if($typedamage == 3){
            if($armorTypes[1] == 1) $damageBonus -= 0.25;
            if($armorTypes[2] == 1) $damageBonus += 0;
            if($armorTypes[3] == 1) $damageBonus += 0.25;
        }
        return $damageBonus;
    }
	
	public function getTableInfo($thing, $inventory_database, $return_array = false){
		// $return_array нужна для отображения информации об уроне в getDamageInformation

		foreach($inventory_database as $inventory_item){
			if($inventory_item["id"] == $thing["id"])
				break;
		}
        if($inventory_item["id"] < 500){
			
            if($inventory_item["type"] == 1)
				$typeName="Одноручное";
            if($inventory_item["type"] == 2)
				$typeName="Двуручное";
            if($inventory_item["type"] == 3)
				$typeName="Древковое";
            if($inventory_item["type_damage"] == 1)
				$typedamageName="Колющее";
            if($inventory_item["type_damage"] == 2)
				$typedamageName="Режущее";
            if($inventory_item["type_damage"] == 3)
				$typedamageName="Дробящее";
			
            $damage[0] = $inventory_item["damage"];
            $crit[0] = $inventory_item["crit"];
            $modificator = 1;
            for($i = 1; $i <=5; $i++){
                $modificator += 0.05;
                $damage[$i] = round($damage[0] * $modificator,2);
                $crit[$i] = round($crit[0] * $modificator,2);
            }
			
            $sr["name"] = $inventory_item["name"];
            $sr["typeName"] = $typeName;
			$sr["typedamageName"] = $typedamageName;
			$sr["type_damage"] = $inventory_item["type_damage"];
			$sr["price"] = $inventory_item["price"];
            $sr["damageLvl"] = $thing["damage"];
            $sr["critLvl"] = $thing["crit"];
            $sr["requiredlvl"] = $inventory_item["required_lvl"];
            $sr["damage"] = $damage[$thing["damage"]];
            $sr["crit"] = $crit[$thing["crit"]];

            $text = $this->db->getReplaceTemplate($sr, "weaponView");
        }

        if($inventory_item["id"] > 500 && $inventory_item["id"] < 1000){

            if($inventory_item["type_defence"] == 1)
				$typeName="Лёгкая";
            if($inventory_item["type_defence"] == 2)
				$typeName="Средняя";
            if($inventory_item["type_defence"] == 3)
				$typeName="Тяжелая";

            $defence[0] = $inventory_item["armor"];
            $modificator = 1;
            for($i = 1; $i <=5; $i++){
                $modificator += 0.05;
                $defence[$i] = round($defence[0] * $modificator,2);
            }
            $sr["type_defence"] = $inventory_item["type_defence"];
            $sr["typeName"] = $typeName;
            $sr["name"] = $inventory_item["name"];
			$sr["price"] = $inventory_item["price"];
            $sr["requiredlvl"] = $inventory_item["required_lvl"];
            $sr["armor"] = $defence[$thing["armor"]];
            $sr["armorLvl"] = $thing["armor"];
			
            $text = $this->db->getReplaceTemplate($sr, "armorView");
        }
		
		if($inventory_item["bonus_strengh"]){
			$sr["bonus_strengh"] = $inventory_item["bonus_strengh"];
			$text .= " <tr><td class='success'>Сила</td><td class='active'>{$inventory_item["bonus_strengh"]}</td></tr>";
		}
		if($inventory_item["bonus_defence"]){
			$sr["bonus_defence"] = $inventory_item["bonus_defence"];
			$text .= " <tr><td class='success'>Защита</td><td class='active'>{$inventory_item["bonus_defence"]}</td></tr>";
		} 
		if($inventory_item["bonus_agility"]){
			$sr["bonus_agility"] = $inventory_item["bonus_agility"];
			$text .= " <tr><td class='success'>Ловкость</td><td class='active'>{$inventory_item["bonus_agility"]}</td></tr>";
		} 
		if($inventory_item["bonus_physique"]){
			$sr["bonus_physique"] = $inventory_item["bonus_physique"];
			$text .= " <tr><td class='success'>Телосложение</td><td class='active'>{$inventory_item["bonus_physique"]}</td></tr>";
		} 
		if($inventory_item["bonus_mastery"]){
			$sr["bonus_mastery"] = $inventory_item["bonus_mastery"];
			$text .= " <tr><td class='success'>Мастерство</td><td class='active'>{$inventory_item["bonus_mastery"]}</td></tr>";
		} 
		
		$text .= "</tbody></table></div>";
		if($return_array)
			return array("html" => $text, "array" => $sr);
		return $text;
    }
}
?>