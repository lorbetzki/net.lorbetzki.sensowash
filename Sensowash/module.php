<?php

declare(strict_types=1);
	class Sensowash extends IPSModule
	{
		const HEX_START		 	= "5505";
		const HEX_READPARAM		= "06";
		const HEX_WRITE			= "04";

		const HEX_HEATEROFF 	= "2500";
		const HEX_HEATER1 		= "2501";
		const HEX_HEATER2 		= "2502";
		const HEX_HEATER3 		= "2503";
		
		const HEX_LIGHTOFF 		= "4100";
		const HEX_LIGHTAUTO 	= "4102";
		const HEX_LIGHTON 		= "4101";
		const HEX_SOUNDON 		= "4001";
		const HEX_SOUNDOFF 		= "4000";

		const HEX_CLEANON 		= "0361";
		const HEX_CLEANOFF 		= "0301";
		const HEX_GETDATA 		= "0350";

		const HEX_COMFORTON		= "2000";
		const HEX_COMFORTOFF	= "2001";

		const HEX_SERVICE 		= "00011111-0405-0607-0809-0a0b0c0d11ff";
		const HEX_NOTIFYCHAR 	= "00012222-0405-0607-0809-0a0b0c0d11ff";
		const HEX_WRITECHAR 	= "00013333-0405-0607-0809-0a0b0c0d11ff";
		const HEX_LOGINCHAR 	= "00014444-0405-0607-0809-0a0b0c0d11ff";

		const LOGINCODE			= "CB97ABB2";
		const LOGINOK			= "032A";

		public function Create()
		{
			//Never delete this line!
			parent::Create();
			$this->RegisterPropertyString("Topic", '');
			$this->RegisterAttributeString("Mac", '');
			$this->createProfiles();
			$this->createVariables();

			//--- Register Timer
			$this->RegisterTimer("DisableConnection", 0, 'SENSOWASH_Logoff($_IPS[\'TARGET\']);');
			$this->RegisterTimer("ReadConfig", 0, 'SENSOWASH_readconfig($_IPS[\'TARGET\']);');

		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			$Topic = $this->ReadPropertyString("Topic");
			$DataFilter = '.*'.$Topic.'.*';

			$this->SendDebug(__FUNCTION__,"Set Topic: ".$Topic,0);

			if ($Topic == '')
			{
				$this->SetStatus(201); // no Host
            	return false;
			}
			else
			{
				$this->SetStatus(102); // 

				$this->SetReceiveDataFilter('.*'.$Topic.'.*');
				$this->SendDebug(__FUNCTION__,"Set DataFilter Payload: ".$DataFilter,0);

				if (!$this->ReadAttributeString("Mac"))
				{
					//$this->GetMac();
				}
			}

		}

		protected function sendMQTT($Type, $Value)
		{
			$MacAddress = $this->ReadAttributeString("Mac");
			$Topic = "cmnd/".$this->ReadPropertyString("Topic");

			switch ($Type)
			{ 
				case "LOGIN":
					$Topic = $Topic."/BLEOp";
					$Payload = "m:".$MacAddress." s:".self::HEX_SERVICE." n:".self::HEX_NOTIFYCHAR." c:".self::HEX_LOGINCHAR." w:".$Value." go";
				break;
				case "SETUP":
					$Topic = $Topic."/BLEOp";
					$Payload = "m:".$MacAddress." s:".self::HEX_SERVICE." c:".self::HEX_WRITECHAR." w:".$Value." go";
				break;
				case "GETDATA":
					$Topic = $Topic."/BLEOp";
					$Payload = "m:".$MacAddress." s:".self::HEX_SERVICE." n:".self::HEX_NOTIFYCHAR." c:".self::HEX_WRITECHAR." w:".$Value." go";
				break;
				default:
					$Topic = $Topic."/".$Type;
					$Payload = $Value;
			}
			
			//$Topic = "cmnd/".$this->ReadPropertyString("Topic")."/BLEOp";
			$this->SendDebug(__FUNCTION__,"Topic: ".$Topic." Payload: ".$Payload,0);

			$mqtt['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
			$mqtt['PacketType'] = 3;
			$mqtt['QualityOfService'] = 0;
			$mqtt['Retain'] = false;
			$mqtt['Topic'] = $Topic;
			$mqtt['Payload'] = "$Payload";
			$mqttJSON = json_encode($mqtt, JSON_UNESCAPED_SLASHES);
			
			$result = $this->SendDataToParent($mqttJSON);
		}	

		public function ReceiveData($JSONString)
		{	
			$data = json_decode($JSONString, true);
			$TopicReceived 		= $data['Topic'];
			$PayloadReceived 	= $data['Payload'];

			$this->SendDebug(__FUNCTION__,"Receive Topic: ".$TopicReceived,0);
			$this->SendDebug(__FUNCTION__,"Receive Payload: ".$PayloadReceived,0);
			if (!$this->ReadAttributeString("Mac"))
				{
					if ($TopicReceived == 'tele/'.$this->ReadPropertyString("Topic").'/BLE')
					{
						$this->GetMac($PayloadReceived);
					}
				}
				else
				{
					$this->GetData($PayloadReceived);
				}
			
		}

		private function GetData($data)
		{
			$this->SendDebug(__FUNCTION__,"Getting Data: ".$data,0);
			
			if($data ==="Offline")
			{	
				$this->SetValue('lastmessage',"Status: ".$data);
				$this->SetValue('connected',false);
			}
						
			if($data ==="Online")
			{	
				$this->SetValue('lastmessage',"Status: ".$data);
			}

			$data = json_decode($data, true);
			if (is_array($data))
			{
				foreach ($data as $key=>$value)
				{
					switch($key)
					{
						case 'BLEDevices':
							$MacAddress = $this->ReadAttributeString("Mac");
							$rssi = $value[''.$MacAddress.'']['r'];
							$this->SetValue('rssi',$rssi);
							$this->SendDebug(__FUNCTION__,"rssi found, write value: ".$rssi,0);
						break;
						case 'ip':
							$this->SetValue('ip',$value);
							$this->SendDebug(__FUNCTION__,"IP found: ".$value,0);
						break;
						case 'BLEOperation':
							$this->SendDebug(__FUNCTION__,"writestatus: ".$value['state'],0);

							switch($value['state'])
							{
								case 'DONEWRITE':
//									if ($value['write'] === self::LOGINCODE)
//									{	
//										$this->SetValue('connected',true);
										$this->SetValue('lastmessage',"Status: ".$value['state']." for ". $value['write']);
//									}
//									else 
//									{									
//										$this->SetValue('lastmessage',"Status: ".$value['state']." for ". $value['write']);
//									}
								break;
								case 'FAILCONNECT':
//									if ($value['write'] === self::LOGINCODE)
//									{	
										$this->SetValue('connected',false);
										$this->SetValue('lastmessage',"Status: ".$value['state']." for ". $value['write']);
//									}
//									else 
//									{									
//										$this->SetValue('lastmessage',"Status: ".$value['state']." for ". $value['write']);
//									}
								break;
								case 'DONENOTIFIED':
									$this->SendDebug(__FUNCTION__,"receive Answer: ".$value['notify'],0);
									$this->SetValue('lastmessage',"Status: ".$value['state']." for ". $value['write']);
									
									
									$crc = $this->verifyChecksum(self::LOGINOK,0);
									if ($value['notify'] === self::HEX_START.self::LOGINOK.$crc)
									{	
										$this->SetValue('connected',true);
										$this->SetTimerInterval('DisableConnection', 600 * 1000);
									}

									$HEX_BEGIN = substr($value['notify'], 0, 6);
									// if HEX begin with 550506 we receive data from the toilet
									if ($HEX_BEGIN === self::HEX_START.self::HEX_READPARAM)
									{
										$this->GetConfig($value['notify']);
										$this->SendDebug(__FUNCTION__, "Get Parameter: ". $value['notify'], 0);

									}
								break;
							}

						break;
						default:
						$this->SendDebug(__FUNCTION__,"Informal message - getting key: ".$key." with value: ".json_encode($value),0);

					}
				}
			}
		}

		private function GetMac($data)
		{
			$this->SendDebug(__FUNCTION__,"Searching for MAC: ".$data,0);
			$data = json_decode($data, true);
			
			foreach($data['BLEDevices'] as $mac=>$name){
				if (isset($name['n']))
				{
					if ($name['n']=='DURAVIT_BT')
					{
						$this->SendDebug(__FUNCTION__,"MAC found: ".$mac,0);
						$this->WriteAttributeString("Mac", $mac);
					}
				}
			}
		}

		public function GetConfigurationForm()
		{
			$jsonform = json_decode(file_get_contents(__DIR__."/form.json"), true);
			
			
			return json_encode($jsonform);
		}

		private function createProfiles()
		{
			if (!IPS_VariableProfileExists('SENSOWASH.SeatHeat')) {
				IPS_CreateVariableProfile('SENSOWASH.SeatHeat', VARIABLETYPE_INTEGER);
				IPS_SetVariableProfileIcon('SENSOWASH.SeatHeat', '');
				IPS_SetVariableProfileValues("SENSOWASH.SeatHeat", 0, 3, 1);
				IPS_SetVariableProfileText("SENSOWASH.SeatHeat", "", "");
				IPS_SetVariableProfileAssociation("SENSOWASH.SeatHeat", 0, $this->Translate('off'), "", 0x0000FF);
				IPS_SetVariableProfileAssociation("SENSOWASH.SeatHeat", 1, $this->Translate('level 1'), "", 0xFFFF00);
				IPS_SetVariableProfileAssociation("SENSOWASH.SeatHeat", 2, $this->Translate('level 2'), "", 0xFF7F00);
				IPS_SetVariableProfileAssociation("SENSOWASH.SeatHeat", 3, $this->Translate('level 3'), "", 0xFF0000);
			}
			if (!IPS_VariableProfileExists('SENSOWASH.NightLight')) {
				IPS_CreateVariableProfile('SENSOWASH.NightLight', VARIABLETYPE_INTEGER);
				IPS_SetVariableProfileIcon('SENSOWASH.NightLight', '');
				IPS_SetVariableProfileValues("SENSOWASH.NightLight", 0, 2, 1);
				IPS_SetVariableProfileText("SENSOWASH.NightLight", "", "");
				IPS_SetVariableProfileAssociation("SENSOWASH.NightLight", 0, $this->Translate('off'), "", 0xFFFFFF);
				IPS_SetVariableProfileAssociation("SENSOWASH.NightLight", 1, $this->Translate('on'), "", 0x00FF00);
				IPS_SetVariableProfileAssociation("SENSOWASH.NightLight", 2, $this->Translate('automatic'), "", 0x007FFF);
			}
			if (!IPS_VariableProfileExists('SENSOWASH.RSSI')) {
				IPS_CreateVariableProfile('SENSOWASH.RSSI', VARIABLETYPE_INTEGER);
				IPS_SetVariableProfileIcon('SENSOWASH.RSSI', '');
				IPS_SetVariableProfileValues("SENSOWASH.RSSI", 0, 0, 1);
				IPS_SetVariableProfileText("SENSOWASH.RSSI", "", " dBm");
			}
			if (!IPS_VariableProfileExists('SENSOWASH.YESNO')) {
				IPS_CreateVariableProfile('SENSOWASH.YESNO', VARIABLETYPE_BOOLEAN);
				IPS_SetVariableProfileIcon('SENSOWASH.YESNO', '');
				IPS_SetVariableProfileAssociation("SENSOWASH.YESNO", 0, $this->Translate('no'), "", 0xFFFFFF);
				IPS_SetVariableProfileAssociation("SENSOWASH.YESNO", 1, $this->Translate('yes'), "", 0xFFFFFF);
			}
		}
		private function createVariables()
		{

			$this->MaintainVariable('SeatHeat', $this->Translate("Seat heater"), VARIABLETYPE_INTEGER, "SENSOWASH.SeatHeat", 0, true);
			$this->EnableAction("SeatHeat");
			$this->SendDebug(__FUNCTION__,"Create Variable with IDENT Seatheat", 0);

			$this->MaintainVariable('NightLight', $this->Translate("Night Light"), VARIABLETYPE_INTEGER, "SENSOWASH.NightLight", 1, true);
			$this->EnableAction("NightLight");
			$this->SendDebug(__FUNCTION__,"Create Variable with IDENT NightLight", 0);

			$this->MaintainVariable('ConfirmationSound', $this->Translate("Confirmation Sound"), VARIABLETYPE_BOOLEAN, "~Switch", 2, true);
			$this->EnableAction("ConfirmationSound");
			$this->SendDebug(__FUNCTION__,"Create Variable with IDENT ConfirmationSound", 0);

			$this->MaintainVariable('ManualCleaning', $this->Translate("Manual cleaning"), VARIABLETYPE_BOOLEAN, "~Switch", 3, true);
			$this->EnableAction("ManualCleaning");
			$this->SendDebug(__FUNCTION__,"Create Variable with IDENT ManualCleaning", 0);

			$this->MaintainVariable('ComfortMode', $this->Translate("Comfort mode"), VARIABLETYPE_BOOLEAN, "~Switch", 3, true);
			$this->EnableAction("ComfortMode");
			$this->SendDebug(__FUNCTION__,"Create Variable with IDENT ComfortMode", 0);

			$this->MaintainVariable('ip', $this->Translate("IP Address"), VARIABLETYPE_STRING, "", 9, true);
			$this->SendDebug(__FUNCTION__,"Create Variable with IDENT ip", 0);

			$this->MaintainVariable('rssi', $this->Translate("BLE rssi"), VARIABLETYPE_INTEGER, "SENSOWASH.RSSI", 9, true);
			$this->SendDebug(__FUNCTION__,"Create Variable with IDENT rssi", 0);

			$this->MaintainVariable('connected', $this->Translate("Connected"), VARIABLETYPE_BOOLEAN, "SENSOWASH.YESNO", 0, true);
			$this->SendDebug(__FUNCTION__,"Create Variable with IDENT rssi", 0);

			$this->MaintainVariable('lastmessage', $this->Translate("last message"), VARIABLETYPE_STRING, "", 9, true);
			$this->SendDebug(__FUNCTION__,"Create Variable with IDENT lastmessage", 0);
		}

		private function connect()
		{
			$this->SendDebug(__FUNCTION__,"Connect to Toilett", 0);
			$this->sendMQTT("LOGIN", self::LOGINCODE);
			$this->SendDebug(__FUNCTION__,"reading config", 0);
			$this->ReadConfig();
		}
		
		private function ReadConfig()
		{
			$this->SendDebug(__FUNCTION__,"reading data", 0);
			$crc = $this->verifyChecksum(self::HEX_GETDATA,0);
			$this->sendMQTT("GETDATA", self::HEX_START.self::HEX_GETDATA.$crc);
		}

		private function seatheat(int $val)
		{
			switch($val)
			{
				case 0:
					$Value = self::HEX_HEATEROFF;
				break;
				
				case 1:
					$Value = self::HEX_HEATER1;
				break;
				
				case 2:		
					$Value = self::HEX_HEATER2;
				break;
				
				case 3:
					$Value = self::HEX_HEATER3;
				break;									
			}
			$crc=$this->verifyChecksum($Value,4);

			$this->sendMQTT("SETUP", self::HEX_START.self::HEX_WRITE.$Value.$crc);	
		}

		private function nightlight(int $val)
		{
			switch($val)
			{
				case 0:
					$Value = self::HEX_LIGHTOFF;
				break;
				
				case 1:
					$Value = self::HEX_LIGHTON;
				break;
				
				case 2:		
					$Value = self::HEX_LIGHTAUTO;
				break;
			}
			$crc=$this->verifyChecksum($Value,4);

			$this->sendMQTT("SETUP", self::HEX_START.self::HEX_WRITE.$Value.$crc);	
		}

		private function confirmationsound(bool $val)
		{
			switch($val)
			{
				case 0:
					$Value = self::HEX_SOUNDOFF;
				break;
				
				case 1:
					$Value = self::HEX_SOUNDON;
				break;
			}
			$crc=$this->verifyChecksum($Value,4);

			$this->sendMQTT("SETUP", self::HEX_START.self::HEX_WRITE.$Value.$crc);	
		}

		private function comfortmode(bool $val)
		{
			switch($val)
			{
				case 0:
					$Value = self::HEX_COMFORTOFF;
				break;
				
				case 1:
					$Value = self::HEX_COMFORTON;
				break;
			}
			$crc=$this->verifyChecksum($Value,4);

			$this->sendMQTT("SETUP", self::HEX_START.self::HEX_WRITE.$Value.$crc);	
		}

		private function manualcleaning(bool $val)
		{
			switch($val)
			{
				case 0:
					$Value = self::HEX_CLEANOFF;
				break;
				
				case 1:
					$Value = self::HEX_CLEANON;
				break;
			}
			$crc=$this->verifyChecksum($Value,0);

			$this->sendMQTT("SETUP", self::HEX_START.$Value.$crc);	
		}

		private function restart()
		{
			$this->sendMQTT("Restart", 1);
			$this->SetValue('connected', 0);	
		}

		public function RequestAction($Ident, $Value)
		{
			$this->SendDebug(__FUNCTION__, "starting action: ". $Ident . " with value ".$Value, 0);

			if (!$this->GetValue('connected'))
			{	
				$this->connect();
			}
			
			switch ($Ident) {
				case "SeatHeat":
					$this->seatheat($Value);
					$this->SetValue($Ident, $Value);
				break;
				case "NightLight":
					$this->nightlight($Value);
					$this->SetValue($Ident, $Value);
				break;
				case "ConfirmationSound":
					$this->confirmationsound($Value);
					$this->SetValue($Ident, $Value);
				break;
				case "ManualCleaning":
					$this->manualcleaning($Value);
					$this->SetValue($Ident, $Value);
				break;
				case "ComfortMode":
					$this->comfortmode($Value);
					$this->SetValue($Ident, $Value);
				break;
				case "connect":
					$this->connect();
				break;
				case "restart":
					$this->restart();
				break;
				case "readconfig":
					$this->ReadConfig();
				break;
			}
		}

		private function Logoff()
		{
			$this->SetValue('connected',false);
			$this->SendDebug(__FUNCTION__, "logging off...", 0);
		}
		
		private function verifyChecksum(string $hex, int $dec)
		{
			$bufLen = strlen($hex);
			$sum_dec = $dec; // 87 for receive 4 for send, 0 for advsend
			for ($i=0; $i < $bufLen; $i+=2)
			{
				$sum_dec += hexdec(substr($hex, $i, 2));
			}
			$result = strtoupper(dechex(($sum_dec % 256)));
			return str_pad($result,2,"0", STR_PAD_LEFT);
		}

		private function GetConfig(string $HEX)
		{
			// making an array
			$HEX =  explode(" ", wordwrap($HEX, 2, " ", true));

			$this->SendDebug(__FUNCTION__, "Hexdaten: ". json_encode($HEX), 0);

			foreach($HEX as $key=>$val)
			{
				switch($key){
					case 4:
						// Sound and Light 	
						// 00 = licht aus, ton aus  		0000
						// 01 = licht aus, ton an,           0001
						// 05 = Licht an/Ton an           	0101
						// 09 = Licht auto/TOn ein ,   	1001
						switch($val)
						{
							case '00':
								$this->SetValue('NightLight', 0);
								$this->SetValue('ConfirmationSound', false);
							break;
							case '01':
								$this->SetValue('NightLight', 0);
								$this->SetValue('ConfirmationSound', true);
							break;

							case '05':
								$this->SetValue('NightLight', 1);
								$this->SetValue('ConfirmationSound', true);
							break;
						
							case "09":
								$this->SetValue('NightLight', 2);
								$this->SetValue('ConfirmationSound', true);
							break;
						}
					break;

					case 5:
						// comfort = 00(aus), 04(an)
						switch($val)
						{
							case '00':
								$this->SetValue('ComfortMode', false);
							break;
							case '04':
								$this->SetValue('ComfortMode', true);
							break;
						}
					break;
					
					case 6:
						// heater 00(aus), 10(1), 20(2), 30(3)
						switch($val)
						{
							case '00':
								$this->SetValue('SeatHeat',0);
							break;
							case '10':
								$this->SetValue('SeatHeat',1);
							break;
							case '20':
								$this->SetValue('SeatHeat',2);
							break;
							case '30':
								$this->SetValue('SeatHeat',3);
							break;
						}

					break;
				}
			}
		}
	}