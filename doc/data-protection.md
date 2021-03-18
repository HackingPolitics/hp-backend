# Datenspeicherung

Im Folgenden wird beschrieben welche DSGVO-relevanten Informationen die
Anwendung zu welchem Zweck speichert.

## Benutzer

Folgende (privaten) Daten werden gespeichert:

Datum|Zweck
---|---
Email|zur Authentifizierung, Kommunikation zum Benutzerkonto und Benachrichtigung bei relevanten Aktivitäten auf der Plattform
Benutzername|zur Authentifizierung und Identifizierung auf der Plattform
Passwort|zur Authentifizierung
Vorname (wenn angegeben)|zur Übernahme in den Förderantrag
Nachname (wenn angegeben)|zur Übernahme in den Förderantrag

Diese Informationen werden gelöscht (wenn auch das Benutzerobjekt
selbst ggf. erhalten bleibt) wenn:

* ein Benutzerkonto nicht innerhalb des festgelegten Zeitraums nach Registrierung
  bestätigt wird
* ein Konto auf Anforderung des Benutzers über sein Profil gelöscht wird
* ein Benutzer durch einen Prozessmanager/Administrator gelöscht wird

## Benutzeraktionen

Zur statistischen Zwecken, zur Prüfung auf Fehler und um sicherheitsrelevante
Beschränkungen einzuhalten werden bestimmte Aktionen die auf der Plattform
ausgeführt werden geloggt:

* Benutzername des ausführenden oder angefragten Benutzers wenn vorhanden
* IP-Adresse des Webseitenbesuchers
* Identifikator der ausgeführten Aktion
* Zeitstempel

Folgende Aktionen werden geloggt:

Aktion|Zweck
---|---
Login erfolgreich|Statistik Benutzung der Plattform, Erkennung von besonderen Häufungen
Login fehlgeschlagen|Erkennung von Problemen, Sperrung von Benutzernamen oder IP-Adressen bei zu vielen Fehlversuchen (brute-force Angriff)
Benutzerregistrierung|Statistik Benutzung der Plattform, Erkennung von besonderen Häufungen, Sperrung von IP-Adressen bei zu vielen Aktionen (Spam-Schutz)
Passwort-Reset angefordert (erfolgreich)|Statistik, Sperrung von Benutzernamen oder IP-Adressen bei zu vielen Aktionen (Spam-Schutz)
Passwort-Reset angefordert (fehlgeschlagen)|Erkennung von Problemen, Sperrung von Benutzernamen oder IP-Adressen bei zu vielen Fehlversuchen (brute-force Angriff)
Bestätigung einer Anfrage fehlgeschlagen (Email-Änderung, Passwort-Zurücksetzen, Konto-Bestätigung)|Erkennung von Problemen, Sperrung von Benutzernamen oder IP-Adressen bei zu vielen Fehlversuchen (brute-force Angriff)

Diese Log-Einträge werden einmal täglich anonymisiert:

* Entfernen aller IP-Adressen von Einträgen die älter als 24h sind (ergibt maximales Alter von IP-Adressen von 48h)
* Entfernen aller Benutzernamen von Einträgen die älter als 7 Tage sind