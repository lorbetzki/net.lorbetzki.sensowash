# Sensowash von Duravit
erlaubt es, Funktionen zu steuern, die nur über die App zu steuern sind.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)

### 1. Funktionsumfang

* lesen und schreiben der Einstellungen für Sitzheizung, Komfortmodus, Bestätigungston und Nachtlicht
* (de)aktivieren des Reinigungsmodus

### 2. Voraussetzungen

- IP-Symcon ab Version 7.2
- ein ESP32 mit installierter Tasmota-Bluetooth Firmware (min. 14.4), aktiviertem Bluetooth und eingerichtetem MQTT

### 3. Software-Installation

* Über den Module Store das 'Sensowash'-Modul installieren.

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Sensowash'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Topic    | Topic des Tasmota MQTT parameters eintragen

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.
