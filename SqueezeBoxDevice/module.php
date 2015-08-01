<?

require_once(__DIR__ . "/../SqueezeBoxClass.php");  // diverse Klassen

class SqueezeboxDevice extends IPSModule
{

    const isMAC = 1;
    const isIP = 2;

    private $Address, $Interval, $Connected = 'noInit', $tempData;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // 1. Verf�gbarer LMS-Splitter wird verbunden oder neu erzeugt, wenn nicht vorhanden.
        $this->ConnectParent("{61051B08-5B92-472B-AFB2-6D971D9B99EE}");

        $this->RegisterPropertyString("Address", "");
        $this->RegisterPropertyInteger("Interval", 2);
        $this->RegisterPropertyString("CoverSize", "cover");
    }

    public function Destroy()
    {
        parent::Destroy();
    
        $CoverID = @IPS_GetObjectIDByIdent('CoverIMG', $this->InstanceID);
        if ($CoverID !== false)
        @IPS_DeleteMedia($CoverID,true);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Addresse pr�fen
        $Address = $this->ReadPropertyString('Address');
        if ($Address == '')
        {
            // Status inaktiv
        }
        else
        {
            if (!strpos($Address, '.')) //keine IP ?
            {
                if (!strpos($Address, ':')) //keine MAC mit :
                {
                    if (!strpos($Address, '-')) //keine MAC mit -
                    {// : einf�gen 
                        //L�nge muss 12 sein, sonst l�schen
                        if (strlen($Address) == 12)
                        {
                            $Address = implode(":", str_split($Address, 2));
                            // STATUS config OK
                        }
                        else
                        {
                            $Address = '';
                            $this->SetStatus(202);
                            // STATUS config falsch
                        }
                        IPS_SetProperty($this->InstanceID, 'Address', $Address);
                        IPS_ApplyChanges($this->InstanceID);
                        return;
                    }
                    else
                    {
                        if (strlen($Address) == 17)
                        {
                            //- gegen : ersetzen                    
                            $Address = str_replace('-', ':', $Address);
                            // STATUS config OK
                        }
                        else
                        {                    //L�nge muss 17 sein, sonst l�schen
                            $this->SetStatus(202);

                            $Address = '';
                            // STATUS config falsch
                        }
                        IPS_SetProperty($this->InstanceID, 'Address', $Address);
                        IPS_ApplyChanges($this->InstanceID);
                        return;
                    }
                }
                else
                { // OK : nun L�nge pr�fen
                    //L�nge muss 17 sein, sonst l�schen                
                    if (strlen($Address) <> 17)
                    {                    //L�nge muss 17 sein, sonst l�schen
                        $this->SetStatus(202);

                        $Address = '';
                        // STATUS config falsch
                        IPS_SetProperty($this->InstanceID, 'Address', $Address);
                        IPS_ApplyChanges($this->InstanceID);
                        return;
                    }
                }
            }
        }


        // Profile anlegen
        $this->RegisterProfileIntegerEx("Status.Squeezebox", "Information", "", "", Array(
            Array(0, "Prev", "", -1),
            Array(1, "Stop", "", -1),
            Array(2, "Play", "", -1),
            Array(3, "Pause", "", -1),
            Array(4, "Next", "", -1)
        ));
        $this->RegisterProfileInteger("Intensity.Squeezebox", "Intensity", "", " %", 0, 100, 1);
        $this->RegisterProfileIntegerEx("Shuffle.Squeezebox", "Shuffle", "", "", Array(
            Array(0, "off", "", -1),
            Array(1, "Title", "", -1),
            Array(2, "Album", "", -1)
        ));
        $this->RegisterProfileIntegerEx("Repeat.Squeezebox", "Repeat", "", "", Array(
            Array(0, "off", "", -1),
            Array(1, "Title", "", -1),
            Array(2, "Album", "", -1)
        ));
        $this->RegisterProfileIntegerEx("Preset.Squeezebox", "Speaker", "", "", Array(
            Array(1, "1", "", -1),
            Array(2, "2", "", -1),
            Array(3, "3", "", -1),
            Array(4, "4", "", -1),
            Array(5, "5", "", -1),
            Array(6, "6", "", -1)
        ));
        $this->RegisterProfileInteger("Tracklist.Squeezebox." . $this->InstanceID, "", "", "", 1, 1, 1);

        //Status-Variablen anlegen
        $this->RegisterVariableBoolean("Power", "Power", "~Switch", 1);
        $this->EnableAction("Power");
        $this->RegisterVariableInteger("Status", "Status", "Status.Squeezebox", 3);
        $this->EnableAction("Status");
        $this->RegisterVariableInteger("Preset", "Preset", "Preset.Squeezebox", 2);
        $this->EnableAction("Preset");
        $this->RegisterVariableBoolean("Mute", "Mute", "~Switch", 4);
        $this->EnableAction("Mute");

        $this->RegisterVariableInteger("Volume", "Volume", "Intensity.Squeezebox", 5);
        $this->EnableAction("Volume");
        $this->RegisterVariableInteger("Bass", "Bass", "Intensity.Squeezebox", 6);
        $this->EnableAction("Bass");
        $this->RegisterVariableInteger("Treble", "Treble", "Intensity.Squeezebox", 7);
        $this->EnableAction("Treble");
        $this->RegisterVariableInteger("Pitch", "Pitch", "Intensity.Squeezebox", 8);
        $this->EnableAction("Pitch");

        $this->RegisterVariableInteger("Shuffle", "Shuffle", "Shuffle.Squeezebox", 9);
        $this->EnableAction("Shuffle");
        $this->RegisterVariableInteger("Repeat", "Repeat", "Repeat.Squeezebox", 10);
        $this->EnableAction("Repeat");
        $this->RegisterVariableInteger("Tracks", "Playlist Anzahl Tracks", "", 11);
        $this->RegisterVariableInteger("Index", "Playlist Position", "Tracklist.Squeezebox." . $this->InstanceID, 12);
        $this->EnableAction("Index");

        $this->RegisterVariableString("Album", "Album", "", 20);
        $this->RegisterVariableString("Title", "Titel", "", 21);
        $this->RegisterVariableString("Interpret", "Interpret", "", 22);
        $this->RegisterVariableString("Genre", "Stilrichtung", "", 23);
        $this->RegisterVariableString("Duration", "Dauer", "", 24);
        $this->RegisterVariableString("Position", "Spielzeit", "", 25);
        $this->RegisterVariableInteger("Position2", "Position", "Intensity.Squeezebox", 26);
        $this->EnableAction("Position2");

        $this->RegisterVariableInteger("Signalstrength", utf8_encode("Signalst�rke"), "Intensity.Squeezebox", 30);
        $this->RegisterVariableInteger("SleepTimeout", "SleepTimeout", "", 31);

        // Workaround f�r persistente Daten der Instanz.
        $this->RegisterVariableBoolean("can_seek", "can_seek", "", -5);
        $this->RegisterVariableString("BufferOUT", "BufferOUT", "", -4);
        $this->RegisterVariableBoolean("WaitForResponse", "WaitForResponse", "", -5);
        $this->RegisterVariableBoolean("Connected", "Connected", "", -3);

        $this->RegisterVariableInteger("PositionRAW", "PositionRAW", "", -1);
        $this->RegisterVariableInteger("DurationRAW", "DurationRAW", "", -2);
        IPS_SetHidden($this->GetIDForIdent('can_seek'), true);
        IPS_SetHidden($this->GetIDForIdent('BufferOUT'), true);
        IPS_SetHidden($this->GetIDForIdent('WaitForResponse'), true);
        IPS_SetHidden($this->GetIDForIdent('Connected'), true);
        IPS_SetHidden($this->GetIDForIdent('PositionRAW'), true);
        IPS_SetHidden($this->GetIDForIdent('DurationRAW'), true);
        // Adresse nicht leer ?
        // Parent vorhanden und nicht in Fehlerstatus ?
        if ($this->Init(false))
        {

            // Ist Device (Player) connected ?
            $Data = new LSQData(LSQResponse::connected, '?');

            // Dann Status von LMS holen
            if ($this->SendLSQData($Data) == 1)
                $this->SetConnected(true);
            // nicht connected
            else
                $this->SetConnected(false);
        }
        $this->_SetSeekable(boolval(GetValueBoolean($this->GetIDForIdent('can_seek'))));
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */
################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    /**
     * Aktuellen Status des Devices ermitteln und, wenn verbunden, abfragen..
     *
     * @return boolean
     */
    public function RequestState()
    {
        $this->Init();
        /*        $this->init();

          if ($this->Connected)
          { */

        $this->SendLSQData(
                new LSQData(LSQResponse::listen, '1')//, false)
        );
        $this->SendLSQData(
                new LSQData(LSQResponse::power, '?', false)
        );
        $this->GetVolume();
        $this->GetPitch();
        $this->GetBass();
        $this->GetTreble();
        $this->GetMute();
        $this->GetRepeat();
        $this->GetShuffle();

        /*
          $this->SendLSQData(
          new LSQData(array(LSQResponse::mixer, LSQResponse::volume), '?', false)
          );
          $this->SendLSQData(
          new LSQData(array(LSQResponse::mixer, LSQResponse::pitch), '?', false)
          );
          $this->SendLSQData(
          new LSQData(array(LSQResponse::mixer, LSQResponse::bass), '?', false)
          );
          $this->SendLSQData(
          new LSQData(array(LSQResponse::mixer, LSQResponse::treble), '?', false)
          );
          $this->SendLSQData(
          new LSQData(array(LSQResponse::mixer, LSQResponse::muting), '?', false)
          ); */
        $this->SendLSQData(
                new LSQData(array('status', 0, '1'), 'tags:gladiqrRt')
        );
        SetValueInteger($this->GetIDForIdent('Status'), 1);
        $this->SendLSQData(
                new LSQData(LSQResponse::mode, '?', false)
        );
        $this->SendLSQData(
                new LSQData(LSQResponse::signalstrength, '?', false)
        );
        $this->SendLSQData(
                new LSQData(LSQResponse::name, '?', false)
        );
        // Playlist holen
        /*        }
          else
          {
          $this->SetValueBoolean('Power', false);
          } */
        return true;
    }

    public function RawSend($Command, $Value, $needResponse)
    {
        $this->Init();

        $LSQData = new LSQData($Command, $Value, $needResponse);
        return $this->SendDataToParent($LSQData);
    }

    /**
     * Setzten den Namen in dem Device.
     *
     * @param string $Name 
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function SetName(string $Name)
    {
        $this->Init();

        $ret = urldecode($this->SendLSQData(new LSQData(LSQResponse::name, (string) $Name)));
        if ($ret == $Name)
        {
            $this->_NewName($Name);
            return true;
        }
        return false;
    }
    /**
     * Liefert den Namen von dem Device.
     *
     * @return string
     * @exception 
     */
    public function GetName()
    {
        $this->Init();

        $Name = trim(urldecode($this->SendLSQData(new LSQData(LSQResponse::name, '?'))));
        $this->_NewName($Name);
        return $Name;
    }

    /*
      public function SetSleep($Value)
      {
      $ret = $this->SendLSQData(new LSQData(LSQResponse::sleep, (int) $Value));
      return ($ret == $Value);
      }

      public function GetSleep()
      {
      return $this->SendLSQData(new LSQData(LSQResponse::sleep, '?'));
      } */

    /**
     * Simuliert einen Tastendruck.
     *
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function PreviousButton()
    {
        $this->Init();

        if ($this->SendLSQData(new LSQData(array('button', 'jump_rew'), '')))
        {
            $this->_SetPlay();
            return true;
        }
        return false;
    }

    /**
     * Simuliert einen Tastendruck.
     *
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function NextButton()
    {
        $this->Init();

        if ($this->SendLSQData(new LSQData(array('button', 'jump_fwd'), '')))
        {
            $this->_SetPlay();
            return true;
        }
        return false;
    }

    /**
     * Startet die Wiedergabe
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function Play()
    {
        $this->Init();

        if ($this->SendLSQData(new LSQData('play', '')))
        {
            $this->_SetPlay();
            return true;
        }
        return false;
    }

    /**
     * Stoppt die Wiedergabe
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function Stop()
    {
        $this->Init();

        if ($this->SendLSQData(new LSQData('stop', '')))
        {
            $this->_SetStop();
            return true;
        }
        return false;
    }

    /**
     * Pausiert die Wiedergabe
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function Pause()
    {
        $this->Init();

        if (boolval($this->SendLSQData(new LSQData('pause', '1'))))
        {
            $this->_SetPause();
            return true;
        }
        return false;
    }

    /**
     * Setzten der Lautst�rke.
     *
     * @param integer $Value 
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function SetVolume(integer $Value)
    {
        $this->Init();

        if (($Value < 0) or ( $Value > 100))
            throw new Exception("Value invalid.");
        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'volume'), $Value)));
        $this->_NewVolume($ret);
        return ($ret == $Value);
    }

    /**
     * Liefert die aktuelle Lautst�rke von dem Device.
     *
     * @return integer
     * @exception 
     */
    public function GetVolume()
    {
        $this->Init();

        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'volume'), '?')));
        $this->_NewVolume($ret);
        return $ret;
    }
    
    /**
     * Setzt den Bass-Wert.
     *
     * @param integer $Value 
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function SetBass(integer $Value)
    {
        $this->Init();

        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'bass'), $Value)));
        $this->SetValueInteger('Bass', $ret);
        return ($ret == $Value);
    }
    
    /**
     * Liefert den aktuellen Bass-Wert.
     *
     * @return integer
     * @exception 
     */
    public function GetBass()
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'bass'), '?')));
        $this->SetValueInteger('Bass', $ret);
        return $ret;
    }
  
    /**
     * Setzt den Treble-Wert.
     *
     * @param integer $Value 
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function SetTreble(integer $Value)
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'treble'), $Value)));
        $this->SetValueInteger('Treble', $ret);
        return ($ret == $Value);
    }
  
    /**
     * Liefert den aktuellen Treble-Wert.
     *
     * @return integer
     * @exception 
     */
    public function GetTreble()
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'treble'), '?')));
        $this->SetValueInteger('Treble', $ret);
        return $ret;
    }
   
    /**
     * Setzt den Pitch-Wert.
     *
     * @param integer $Value 
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function SetPitch(integer $Value)
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'pitch'), $Value)));
        $this->SetValueInteger('Pitch', $ret);
        return ($ret == $Value);
    }
   
    /**
     * Liefert den aktuellen Pitch-Wert.
     *
     * @return integer
     * @exception 
     */
    public function GetPitch()
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'pitch'), '?')));
        $this->SetValueInteger('Pitch', $ret);
        return $ret;
    }

    /**
     * Setzten der Stummschaltung.
     *
     * @param bolean $Value 
     * true = Stumm an
     * false = Stumm aus
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function SetMute(boolean $Value)
    {
        $this->Init();
        if (!is_bool($Value))
            throw new Exception("Value must boolean.");
        $ret = boolval($this->SendLSQData(new LSQData(array('mixer', 'muting'), intval($Value))));
        $this->SetValueBoolean('Mute', $ret);
        return ($ret == $Value);
    }
    
    /**
     * Liefert den Status der Stummschaltung.
     *
     * @return boolean
     * true = Stumm an
     * false = Stumm aus
     * @exception 
     */
    public function GetMute()
    {
        $this->Init();
        $ret = boolval($this->SendLSQData(new LSQData(array('mixer', 'muting'), '?')));
        $this->SetValueBoolean('Mute', $ret);
        return $ret;
    }

    /**
     * Setzen des Wiederholungsmodus.
     *
     * @param integer $Value 
     * 0 = aus
     * 1 = Titel
     * 2 = Album
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function SetRepeat(integer $Value)
    {
        $this->Init();
        if (($Value < 0) or ( $Value > 2))
            throw new Exception("Value must be 0, 1 or 2.");
        $ret = intval($this->SendLSQData(new LSQData(array('playlist', 'repeat'), intval($Value))));
        $this->SetValueInteger('Repeat', $ret);
        return ($ret == $Value);
    }
    
    /**
     * Liefert den Wiederholungsmodus.
     *
     * @return integer
     * 0 = aus
     * 1 = Titel
     * 2 = Album
     * @exception 
     */
    public function GetRepeat()
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('playlist', 'repeat'), '?')));
        $this->SetValueInteger('Repeat', $ret);
        return $ret;
    }

    /**
     * Setzen des Zufallsmodus.
     *
     * @param integer $Value 
     * 0 = aus
     * 1 = Titel
     * 2 = Album
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function SetShuffle(integer $Value)
    {
        $this->Init();
        if (($Value < 0) or ( $Value > 2))
            throw new Exception("Value must be 0, 1 or 2.");
        $ret = intval($this->SendLSQData(new LSQData(array('playlist', 'shuffle'), intval($Value))));
        $this->SetValueInteger('Shuffle', $ret);
        return ($ret == $Value);
    }

    /**
     * Liefert den Zufallsmodus.
     *
     * @return integer
     * 0 = aus
     * 1 = Titel
     * 2 = Album
     * @exception 
     */
    public function GetShuffle()
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('playlist', 'shuffle'), '?')));
        $this->SetValueInteger('Shuffle', $ret);
        return $ret;
    }

    /**
     * Simuliert einen Tastendruck auf einen der Preset-Tasten.
     *
     * @param integer $Value 
     * 1 - 6 = Taste 1 bis 6
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function SelectPreset(integer $Value)
    {
        $this->Init();
        if (($Value < 1) or ( $Value > 6))
            throw new Exception("Value invalid.");
        return boolval($this->SendLSQData(new LSQData(array('button', 'preset_' . intval($Value) . '.single'), '')));
    }

    /**
     * Schaltet das Ger�t ein oder aus.
     *
     * @access public
     * @param boolean $Value 
     * false  = ausschalten
     * true = einschalten
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function Power(boolean $Value)
    {
        $this->Init();
        if (!is_bool($Value))
            throw new Exception("Value must boolean.");
        $ret = boolval($this->SendLSQData(new LSQData('power', intval($Value))));
        return ($ret == $Value);
    }

    /**
     * Springt in der aktuellen Wiedergabeliste auf einen Titel.
     *
     * @param integer $Value 
     * Track in der Wiedergabeliste auf welchen gesprungen werden soll.
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function PlayTrack(integer $Value)
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('playlist', 'index'), intval($Value) - 1)))+1;
        return ($ret == $Value);
    }

    public function NextTrack()
    {
        $this->Init();
        $ret = urldecode($this->SendLSQData(new LSQData(array('playlist', 'index'), '+1')));
        return ($ret == "+1");
    }

    public function PreviousTrack()
    {
        $this->Init();
        $ret = urldecode($this->SendLSQData(new LSQData(array('playlist', 'index'), '-1')));
        return ($ret == "-1");
    }

    /**
     * Setzt eine absolute Zeit-Position des aktuellen Titels.
     *
     * @param integer $Value 
     * Zeit in Sekunden.
     * @return boolean
     * true bei erfolgreicher Ausf�hrung und R�ckmeldung
     * @exception 
     */
    public function SetPosition(integer $Value)
    {
        $this->Init();
        if (!is_int($Value))
            throw new Exception("Value must be integer.");
        $ret = intval($this->SendLSQData(new LSQData('time', $Value)));
        return ($ret == $Value);
    }

    /**
     * Liest die aktuelle Zeit-Position des aktuellen Titels.
     *
     * @return integer
     * Zeit in Sekunden.
     * @exception 
     */
    public function GetPosition()
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData('time', '?')));
        return $ret;
    }

    /**
     * Speichert die aktuelle Wiedergabeliste vom Ger�t in einer unter $Name angegebenen Wiedergabelisten-Datei auf dem LMS-Server.
     *
     * @param string $Name
     * Der Name der Wiedergabeliste. Ist diese Liste auf dem Server schon vorhanden, wird sie �berschrieben.
     * @return boolean
     */
    public function SavePlaylist(string $Name)
    {
        $this->Init();
        $raw = $this->SendLSQData(new LSQData(array('playlist', 'save'), $Name . ' silent:1'));
        $ret = explode(' ', $raw);        
        return (urldecode($ret[0]) == $Name);
    }

    /**
     * L�dt eine Wiedergabelisten-Datei aus dem LMS-Server und startet die Wiedergabe derselben auf dem Ger�t.
     *
     * @param string $Name
     * Der Name der Wiedergabeliste.
     * @return string
     * Kompletter Pfad der Wiedergabeliste.
     * @exception 
     */
    public function LoadPlaylist(string $Name)
    {
        $this->Init();
        $raw = $this->SendLSQData(new LSQData(array('playlist', 'load'), $Name . ' silent:1'));
        $ret = explode(' ', $raw);
        return urldecode($ret[0]);
    }

    /**
     * Liefert Informationen �ber einen Song aus der aktuelle Wiedergabeliste.
     *
     * @param integer $Index
     * $Index f�r die absolute Position des Titels in der Wiedergabeliste.
     * 0 f�r den aktuellen Titel
     *  
     * @return array
     *  ["duration"]=>string
     *  ["id"]=>string
     *  ["title"]=>string
     *  ["genre"]=>string
     *  ["album"]=>string
     *  ["artist"]=>string
     *  ["disc"]=> string
     *  ["disccount"]=>string
     *  ["bitrate"]=>string
     *  ["tracknum"]=>string
     * @exception 
     */
    public function GetSongInfoByTrackIndex(integer $Index)
    {
        $this->Init();
        if (is_int($Index))
            $Index--;
        else
            throw new Exception("Index must be integer.");
        if ($Index == -1)
            $Index = '-';
        $Data = $this->SendLSQData(new LSQData(array('status', (string) $Index, '1'), 'tags:gladiqrRt'));
        $Song = $this->DecodeSongInfo($Data)[0];
        return $Song;
    }

################## ActionHandler

    public function RequestAction($Ident, $Value)
    {
        
        switch ($Ident)
        {
            case "Status":
                switch ($Value)
                {
                    case 0: //Prev
                        //$this->PreviousButton();
                        $this->PreviousTrack();
                        break;
                    case 1: //Stop
                        $this->Stop();
                        break;
                    case 2: //Play
                        $this->Play();
                        break;
                    case 3: //Pause
                        $this->Pause();
                        break;
                    case 4: //Next
                        //$this->NextButton();
                        $this->NextTrack();
                        break;
                }
                break;
            case "Volume":
                $this->SetVolume($Value);
                break;
            case "Bass":
                $this->SetBass($Value);
                break;
            case "Treble":
                $this->SetTreble($Value);
                break;
            case "Pitch":
                $this->SetPitch($Value);
                break;
            case "Preset":
                $this->SelectPreset($Value);
                break;
            case "Power":
                $this->Power($Value);
                break;
            case "Mute":
                $this->SetMute($Value);
                break;
            case "Repeat":
                $this->SetRepeat($Value);
                break;
            case "Shuffle":
                $this->SetShuffle($Value);
                break;
            case "Position2":
                $this->tempData['Duration'] = GetValueInteger($this->GetIDForIdent('DurationRAW'));
                $this->tempData['Position'] = GetValueInteger($this->GetIDForIdent('PositionRAW'));
                $Time = ($this->tempData['Duration'] / 100) * $Value;
                $this->SetPosition($Time);
                break;
            case "Index":
                $this->PlayTrack($Value);
                break;
            default:
                throw new Exception("Invalid ident");
        }
    }

################## PRIVATE

    private function _NewName($Name)
    {

        if (IPS_GetName($this->InstanceID) <> trim($Name))
        {
            IPS_SetName($this->InstanceID, trim($Name));
        }
    }

    private function _SetPlay()
    {
        $this->SetValueBoolean('Power', true);
        if (GetValueInteger($this->GetIDForIdent('Status')) <> 2)
        {

            $this->SetValueInteger('Status', 2);
            $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:' . $this->ReadPropertyInteger('Interval'), false));
        }
    }

    private function _SetStop()
    {
        if (GetValueInteger($this->GetIDForIdent('Status')) <> 1)
        {
            $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:0', false));
            $this->SetValueInteger('Status', 1);
        }
    }

    private function _SetPause()
    {
        if (GetValueInteger($this->GetIDForIdent('Status')) <> 3)
        {

            $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:0', false));
            $this->SetValueInteger('Status', 3);
        }
    }

    private function _NewVolume($Value)
    {
        $Value = (int) ($Value);
        if ($Value < 0)
        {
            $Value = $Value - (2 * $Value);
            $this->SetValueBoolean('Mute', true);
        }
        else
        {
            $this->SetValueBoolean('Mute', false);
        }
        $this->SetValueInteger('Volume', $Value);
    }

    private function _SetSeekable($Value)
    {
        $this->SetValueBoolean('can_seek', boolval($Value));
        if (boolval($Value))
            $this->EnableAction("Position2");
        else
            $this->DisableAction('Position2');
    }

    private function DecodeSongInfo($Data)
    {
        $id = 0;
        $Songs = array();
        $SongFields = array(
            'id',
            'title',
            'genre',
            'album',
            'artist',
            'duration',
            'disc',
            'disccount',
            'bitrate',
            'tracknum'
        );
        foreach (explode(' ', $Data) as $Line)
        {
            $LSQPart = $this->decodeLSQTaggingData($Line, false);


            if (is_array($LSQPart->Command) and ( $LSQPart->Command[0] == LSQResponse::playlist) and ( $LSQPart->Command[0] == LSQResponse::index))
            {
                $id = (int) $LSQPart->Value;
                continue;
            }
            if (in_array($LSQPart->Command, $SongFields))
                $Songs[$id][$LSQPart->Command] = utf8_decode(urldecode($LSQPart->Value));
        }
        return $Songs;
    }

    private function decodeLSQEvent($LSQEvent)
    {
        if (is_array($LSQEvent->Command))
        {
            $MainCommand = array_shift($LSQEvent->Command);
        }
        else
        {
            $MainCommand = $LSQEvent->Command;
        }

        switch ($MainCommand)
        {
            case LSQResponse::player_connected:
                if (GetValueBoolean($this->GetIDForIdent('Connected')) <> boolval($LSQEvent->Value))
                {
                    $this->SetConnected(true);
                }

                break;
            case LSQResponse::connected:
                if (!$LSQEvent->isResponse) //wenn Response, dann macht der Anfrager das selbst
                {
                    if ($LSQEvent->Value == 1)
                        $this->SetConnected(true);
                    else
                        $this->SetConnected(false);
                }
                break;
            case LSQResponse::client:
                if (!$LSQEvent->isResponse) //wenn Response, dann macht der Anfrager das selbst                
                {
                    if ($LSQEvent->Value == 'disconnect')
                        $this->SetConnected(false);
                    elseif (($LSQEvent->Value == 'new') or ( $LSQEvent->Value == 'reconnect'))
                        $this->SetConnected(true);
                }
                break;
            case LSQResponse::player_name:
            case LSQResponse::name:
                $this->_NewName(urldecode((string) $LSQEvent->Value));
                break;
            case LSQResponse::signalstrength:
                $this->SetValueInteger('Signalstrength', (int) $LSQEvent->Value);
                break;
            case LSQResponse::player_ip:
                //wegwerfen, solange es keinen SetSummary gibt
                break;
            case LSQResponse::power:
                $this->SetValueBoolean('Power', boolval($LSQEvent->Value));
                break;

            case LSQResponse::play:
                $this->_SetPlay();
                break;
            case LSQResponse::stop:
                $this->_SetStop();
                break;
            case LSQResponse::pause:
                if ($LSQEvent->Value == '')
                {
                    $this->_SetPause();
                }
                elseif (boolval($LSQEvent->Value))
                {
                    $this->_SetPause();
                }
                else
                {
                    $this->_SetPlay();
                }
                break;

            case LSQResponse::mode:
                if (is_array($LSQEvent->Command))
                    $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
                else
                    $this->decodeLSQEvent(new LSQEvent($LSQEvent->Value, '', $LSQEvent->isResponse));
                break;
            case LSQResponse::mixer:
                $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
                break;
            case LSQResponse::volume:
                if (is_array($LSQEvent->Value))
                    $this->_NewVolume((int) $LSQEvent->Value[0]);
                else
                    $this->_NewVolume((int) $LSQEvent->Value);
                break;
            case LSQResponse::treble:
                $this->SetValueInteger('Treble', (int) ($LSQEvent->Value));
                break;
            case LSQResponse::bass:
                $this->SetValueInteger('Bass', (int) ($LSQEvent->Value));
                break;
            case LSQResponse::pitch:
                $this->SetValueInteger('Pitch', (int) ($LSQEvent->Value));
                break;
            case LSQResponse::muting:
                $this->SetValueBoolean('Mute', boolval($LSQEvent->Value));
                break;
            case LSQResponse::repeat:
                $this->SetValueInteger('Repeat', (int) ($LSQEvent->Value));
                break;
            case LSQResponse::shuffle:
                $this->SetValueInteger('Shuffle', (int) ($LSQEvent->Value));
                break;
            /*            case LSQResponse::sleep:
              $this->SetValueInteger('SleepTimeout', (int) $LSQEvent->Value);
              break; */
            /*          case LSQResponse::button:
              $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
              break; */
            /*            case LSQButton::jump_fwd:
              case LSQButton::jump_rew:
              $this->SetPlay();
              break; */
            case LSQResponse::sync:
            case LSQResponse::rate:
            case LSQResponse::seq_no:
            case LSQResponse::playlist_timestamp:
            case LSQResponse::linesperscreen:
            case LSQResponse::irenable:
            case LSQResponse::connect:
            case LSQResponse::waitingToPlay:
            case LSQResponse::jump:
            case LSQResponse::open:
            case LSQResponse::displaynotify:
            case LSQResponse::remoteMeta:
            case LSQResponse::id:
                //ignore
                break;
            case LSQResponse::newsong:
                if (is_array($LSQEvent->Value))
                {
                    $title = $LSQEvent->Value[0];
                    $currentTrack = intval($LSQEvent->Value[1]) + 1;
                }
                else
                {
                    $title = $LSQEvent->Value;
                    $currentTrack = 0;
                }
                $this->SetValueInteger('Status', 2);
                $this->SetValueString('Title', trim(urldecode($title)));
                $this->SetValueInteger('Index', $currentTrack);
                $this->SendLSQData(new LSQData(LSQResponse::artist, '?', false));
                $this->SendLSQData(new LSQData(LSQResponse::album, '?', false));
                $this->SendLSQData(new LSQData(LSQResponse::genre, '?', false));
                $this->SendLSQData(new LSQData(LSQResponse::duration, '?', false));
                $this->SendLSQData(new LSQData(array(LSQResponse::playlist, LSQResponse::tracks), '?', false));
                //$this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:' . $this->ReadPropertyInteger('Interval'), false));
//                IPS_Sleep(500);
                $this->SetCover();
                break;
            case LSQResponse::newmetadata:
                $this->SetCover();
                break;
            case LSQResponse::playlist:
                if (($LSQEvent->Command[0] <> LSQResponse::stop)  //Playlist stop kommt auch bei fwd ?
                        and ( $LSQEvent->Command[0] <> LSQResponse::mode))
                    $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
                break;
            case LSQResponse::prefset:
                /*                if ($LSQEvent->Command[0] == 'server')
                  {
                  $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[1], $LSQEvent->Value, $LSQEvent->isResponse));
                  }
                  else
                  {
                  IPS_LogMessage('prefsetLSQEvent', 'Namespace' . $LSQEvent->Command[0] . ':' . $LSQEvent->Value);
                  } */
                break;
            case LSQResponse::title:
                $this->SetValueString('Title', trim(urldecode($LSQEvent->Value)));
                break;
            case LSQResponse::artist:
                $this->SetValueString('Interpret', trim(urldecode($LSQEvent->Value)));
                break;
            case LSQResponse::current_title:
            case LSQResponse::album:

                if (is_array($LSQEvent->Value))
                {
                    $this->SetValueString('Album', trim(urldecode($LSQEvent->Value[0])));
                }
                else
                {
                    $this->SetValueString('Album', trim(urldecode($LSQEvent->Value)));
                }
                break;
            case LSQResponse::genre:
                $this->SetValueString('Genre', trim(urldecode($LSQEvent->Value)));
                break;
            case LSQResponse::duration:
                if ($LSQEvent->Value == 0)
                {
                    $this->SetValueString('Duration', '');
                    $this->SetValueInteger('DurationRAW', 0);
                    $this->SetValueInteger('Position2', 0);
                }
                else
                {
                    $this->tempData['Duration'] = $LSQEvent->Value;
                    $this->SetValueInteger('DurationRAW', $LSQEvent->Value);
                    $this->SetValueString('Duration', @date('i:s', $LSQEvent->Value));
                }
                break;
            case LSQResponse::playlist_tracks:
            case LSQResponse::tracks:
                $this->SetValueInteger('Tracks', $LSQEvent->Value);
                $Name = "Tracklist.Squeezebox." . $this->InstanceID;
                if ($LSQEvent->Value == 0)
                { // alles leeren
                    $this->SetValueString('Title', '');
                    $this->SetValueString('Interpret', '');
                    $this->SetValueString('Album', '');
                    $this->SetValueString('Genre', '');
                    $this->SetValueString('Duration', '0:00');
                    $this->SetValueInteger('DurationRAW', 0);
                    $this->SetValueInteger('Position2', 0);
                    $this->SetValueInteger('PositionRAW', 0);
                    $this->SetValueString('Position', '0:00');
                    $this->SetValueInteger('Index', 0);
                    $this->SetCover();
                }
                if (!IPS_VariableProfileExists($Name))
                {
                    IPS_CreateVariableProfile($Name, 1);
                    IPS_SetVariableProfileValues($Name, 1, $LSQEvent->Value, 1);
                }
                else
                {
                    if (IPS_GetVariableProfile($Name)['MaxValue'] <> $LSQEvent->Value)
                        IPS_SetVariableProfileValues($Name, 1, $LSQEvent->Value, 1);
                }
                break;
            case LSQResponse::status:
                array_shift($LSQEvent->Value);
                if ($LSQEvent->Command[0] == '-')// and ( $LSQEvent->Command[1] == '1') and ( strpos($Event, "subscribe%3A") > 0))
                {
                    foreach ($LSQEvent->Value as $Data)
                    {
                        $LSQPart = $this->decodeLSQTaggingData($Data, $LSQEvent->isResponse);
                        $this->decodeLSQEvent($LSQPart);
                    }
                }
                break;
            case LSQResponse::can_seek:
                if (GetValueBoolean($this->GetIDForIdent('can_seek')) <> boolval($LSQEvent->Value))
                {
                    $this->_SetSeekable(boolval($LSQEvent->Value));
                }
                break;
            case LSQResponse::remote:
                if (GetValueBoolean($this->GetIDForIdent('can_seek')) == boolval($LSQEvent->Value))
                {
                    $this->_SetSeekable(!boolval($LSQEvent->Value));
                }
                break;
            case LSQResponse::index:
            case LSQResponse::playlist_cur_index:
            case LSQResponse::currentSong:
                $this->SetValueInteger('Index', intval($LSQEvent->Value) + 1);
                break;

            case LSQResponse::time:
                $this->tempData['Position'] = $LSQEvent->Value;
                $this->SetValueInteger('PositionRAW', $LSQEvent->Value);
                $this->SetValueString('Position', @date('i:s', $LSQEvent->Value));
                break;
            default:
                if (is_array($LSQEvent->Value))
                    IPS_LogMessage('ToDoLSQEvent', 'LSQResponse-' . $MainCommand . '-' . print_r($LSQEvent->Value, 1));
                else
                    IPS_LogMessage('ToDoLSQEvent', 'LSQResponse-' . $MainCommand . '-' . $LSQEvent->Value);
                break;
        }
        if (isset($this->tempData['Duration']) and isset($this->tempData['Position']))
        {
            $Value = (100 / $this->tempData['Duration']) * $this->tempData['Position'];
            $this->SetValueInteger('Position2', round($Value));
        }
    }

    private function decodeLSQTaggingData($Data, $isResponse)
    {
        $Part = explode('%3A', $Data); //        
        $Command = urldecode(array_shift($Part));
        if (!(strpos($Command, chr(0x20)) === false))
        {
            $Command = explode(chr(0x20), $Command);
        }
        if (isset($Part[1]))
        {
            $Value = implode('%3A', $Part);
        }
        else
        {
            $Value = $Part[0];
        }

        return new LSQEvent($Command, $Value, $isResponse);
    }

    private function SetCover()
    {
        $CoverID = @IPS_GetObjectIDByIdent('CoverIMG', $this->InstanceID);
        if ($CoverID === false)
        {
            $CoverID = IPS_CreateMedia(1);
            IPS_SetParent($CoverID, $this->InstanceID);
            IPS_SetIdent($CoverID, 'CoverIMG');
            IPS_SetName($CoverID, 'Cover');
            IPS_SetPosition($CoverID, 27);
            IPS_SetMediaFile($CoverID, "Cover_" . $this->InstanceID . ".png", False);
        }
        $ParentID = $this->GetParent();
        if (!($ParentID === false))
        {
            $Host = IPS_GetProperty($ParentID, 'Host') . ":" . IPS_GetProperty($ParentID, 'Webport');
            $Size = $this->ReadPropertyString("CoverSize");
            $PlayerID = urlencode($this->Address);
            $CoverRAW = @Sys_GetURLContent("http://" . $Host . "/music/current/" . $Size . ".png?player=" . $PlayerID);
            if (!($CoverRAW === false))
            {
                IPS_SetMediaContent($CoverID, base64_encode($CoverRAW));
            }
        }
        return;
    }

    private function SetConnected($Status)
    {
        $this->SetValueBoolean('Connected', $Status);
        $this->Connected = $Status;
        $this->Init(false);
        if ($Status === true)
            $this->RequestState();
    }

    private function Init($throwException = true)
    {
        if ($this->Connected <> 'noInit')
            return true;

        $this->Address = $this->ReadPropertyString("Address");
        $this->Interval = $this->ReadPropertyInteger("Interval");
        $this->Connected = GetValueBoolean($this->GetIDForIdent('Connected'));
        if ($this->Address == '')
        {
            $this->SetStatus(202);
            if ($throwException)
                throw new Exception('Address not set.');
            else
                return false;
        }
        $ParentID = $this->GetParent();
        if ($ParentID === false)
        {
            $this->SetStatus(104);
            if ($throwException)
                throw new Exception('Instance has no parent.');
            else
                return false;
        }
        else
        if (!$this->HasActiveParent($ParentID))
        {
            $this->SetStatus(203);
            if ($throwException)
                throw new Exception('Instance has no active parent.');
            else
                return false;
        }
        $this->SetStatus(102);

        return true;
    }

    private function SetValueBoolean($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueBoolean($id) <> $value)
            SetValueBoolean($id, $value);
    }

    private function SetValueInteger($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueInteger($id) <> $value)
            SetValueInteger($id, $value);
    }

    private function SetValueString($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueString($id) <> $value)
            SetValueString($id, $value);
    }

################## DataPoints
    // Ankommend von Parent-LMS-Splitter

    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString);

        $this->Init(false);
        if ($this->Address === '') //Keine Adresse Daten nicht verarbeiten
            return false;

        // Adressen stimmen �berein, die Daten sind f�r uns.
        if (($this->Address == $Data->LMS->MAC) or ( $this->Address == $Data->LMS->IP))
        {
            // Objekt erzeugen welches die Commands und die Values enth�lt.
            $Response = new LSQResponse($Data->LMS);

            // Ist das Command noch schon bekannt ?
            if ($Response->Command <> false)
            {
                // Daten pr�fen ob Antwort
                $isResponse = $this->WriteResponse($Response->Command, $Response->Value);
                if (is_bool($isResponse))
                {
                    $Response->isResponse = $isResponse;
                    if (!$isResponse)
                    {
                        // Daten dekodieren
                        $this->decodeLSQEvent($Response);
                    }
                    return true;
                }
                else
                {
                    throw new Exception($isResponse);
                }
            }
            // Unbekanntes Command loggen
            else
            {
                IPS_LogMessage("ToDoLSQDevice: Unbekannter Datensatz:", print_r($Response->Value, 1));
                return true;
            }
        }
        // Daten waren nicht f�r uns
        return false;
    }

    // Sende-Routine an den Parent
    protected function SendDataToParent($LSQData)
    {
        $LSQData->Address = $this->ReadPropertyString('Address');
        // Sende Lock setzen
        if (!$this->lock("ToParent"))
        {
            throw new Exception("Can not send to LMS-Splitter");
        }
        // Daten senden
        try
        {
            $ret = IPS_SendDataToParent($this->InstanceID, json_encode(Array("DataID" => "{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}", "LSQ" => $LSQData)));
        }
        catch (Exception $exc)
        {
            // Senden fehlgeschlagen
            // Sende Lock aufheben
            $this->unlock("ToParent");
            throw new Exception("LMS not reachable");
        }
        // Sende Lock aufheben
        $this->unlock("ToParent");
        return $ret;
    }

    ################## Datenaustausch

    private function SendLSQData($LSQData)
    {
        $this->init();
        // pr�fen ob Player connected ?
        // nur senden wenn connected ODER wir eine connected anfrage senden wollen
        if ((!$this->Connected) and ( $LSQData->Command <> LSQResponse::connected))
        {
            throw new Exception("Device not connected");
        }
        $ParentID = $this->GetParent();
        if (!($ParentID === false))
        {
            if (!$this->HasActiveParent($ParentID))
                return;
        }
        else
            return;

        if ($LSQData->needResponse)
        {
//Semaphore setzen
            if (!$this->lock("LSQData"))
            {
                throw new Exception("Can not send to LMS-Splitter");
            }
// Anfrage f??r die Warteschleife schreiben
            if (!$this->SetWaitForResponse($LSQData->Command))
            {
                $this->unlock("LSQData");
                throw new Exception("Can not send to LMS-Splitter");
            }
            try
            {
                $this->SendDataToParent($LSQData);
            }
            catch (Exception $exc)
            {
//  Daten in Warteschleife l?�schen
                $this->ResetWaitForResponse();
                $this->unlock("LSQData");
                throw $exc;
            }
// SendeLock  velassen
            $this->unlock("LSQData");
// Auf Antwort warten....
            $ret = $this->WaitForResponse();

            if ($ret === false) // Warteschleife lief in Timeout
            {
//  Daten in Warteschleife l?�schen                
                $this->ResetWaitForResponse();
// Fehler
                throw new Exception("No answer from LMS");
            }
            return $ret;
        }
        else
        {
            try
            {
                $this->SendDataToParent($LSQData);
            }
            catch (Exception $exc)
            {
                throw $exc;
            }
        }
    }

################## ResponseBuffer    -   private

    private function SetWaitForResponse($Data)
    {
        if (is_array($Data))
            $Data = implode(' ', $Data);
        if ($this->lock('BufferOut'))
        {
            $buffer = $this->GetIDForIdent('BufferOUT');
            $WaitForResponse = $this->GetIDForIdent('WaitForResponse');
            SetValueString($buffer, $Data);
            SetValueBoolean($WaitForResponse, true);
            $this->unlock('BufferOut');
            return true;
        }
        return false;
    }

    private function WaitForResponse()
    {
        $Event = $this->GetIDForIdent('WaitForResponse');
        for ($i = 0; $i < 500; $i++)
        {
            if (GetValueBoolean($Event))
                IPS_Sleep(10);
            else
            {
                if ($this->lock('BufferOut'))
                {
                    $buffer = $this->GetIDForIdent('BufferOUT');
                    $ret = GetValueString($buffer);
                    SetValueString($buffer, "");
                    $this->unlock('BufferOut');
                    if ($ret == '')
                        return true;
                    else
                        return $ret;
                }
                return false;
            }
        }
        return false;
    }

    private function ResetWaitForResponse()
    {
        if ($this->lock('BufferOut'))
        {
            $buffer = $this->GetIDForIdent('BufferOUT');
            $WaitForResponse = $this->GetIDForIdent('WaitForResponse');
            SetValueString($buffer, '');
            SetValueBoolean($WaitForResponse, false);
            $this->unlock('BufferOut');
            return true;
        }
        return false;
    }

    private function WriteResponse($Command, $Value)
    {
        if (is_array($Command))
            $Command = implode(' ', $Command);
        if (is_array($Value))
            $Value = implode(' ', $Value);

        $EventID = $this->GetIDForIdent('WaitForResponse');
        if (!GetValueBoolean($EventID))
            return false;
        $BufferID = $this->GetIDForIdent('BufferOUT');
        if ($Command == GetValueString($BufferID))
        {
            if ($this->lock('BufferOut'))
            {
                SetValueString($BufferID, $Value);
                SetValueBoolean($EventID, false);
                $this->unlock('BufferOut');
                return true;
            }
            return 'Error on write ResponseBuffer';
        }
        return false;
    }

################## SEMAPHOREN Helper  - private  

    private function lock($ident)
    {
        for ($i = 0; $i < 100; $i++)
        {
            if (IPS_SemaphoreEnter("LMS_" . (string) $this->InstanceID . (string) $ident, 1))
                return true;
            else
                IPS_Sleep(mt_rand(1, 5));
        }
        return false;
    }

    private function unlock($ident)
    {
        IPS_SemaphoreLeave("LMS_" . (string) $this->InstanceID . (string) $ident);
    }

################## DUMMYS / WOARKAROUNDS - protected

    protected function HasActiveParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0)
        {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            if ($parent['InstanceStatus'] == 102)
                return true;
        }
        return false;
    }

    protected function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    /*
      protected function RegisterTimer($data, $cata)
      {
      IPS_LogMessage(__CLASS__, __FUNCTION__); //
      }

      protected function SetTimerInterval($data, $cata)
      {
      IPS_LogMessage(__CLASS__, __FUNCTION__); //
      }

      protected function LogMessage($data, $cata)
      {

      }

      protected function SetSummary($data)
      {
      IPS_LogMessage(__CLASS__, __FUNCTION__ . "Data:" . $data); //
      }
     */

    //Remove on next Symcon update
    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {

        if (!IPS_VariableProfileExists($Name))
        {
            IPS_CreateVariableProfile($Name, 1);
        }
        else
        {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 1)
                throw new Exception("Variable profile type does not match for profile " . $Name);
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }

    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (sizeof($Associations) === 0)
        {
            $MinValue = 0;
            $MaxValue = 0;
        }
        else
        {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations) - 1][0];
        }

        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association)
        {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

}

?>