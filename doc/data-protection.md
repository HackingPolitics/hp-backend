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
Vorname (wenn angegeben)|zur Anzeige in der Oberfläche
Nachname (wenn angegeben)|zur Anzeige in der Oberfläche

Diese Informationen werden gelöscht (wenn auch das Benutzerobjekt
selbst ggf. erhalten bleibt) wenn:

* ein Benutzerkonto nicht innerhalb des festgelegten Zeitraums nach Registrierung
  bestätigt wird
* ein Konto auf Anforderung des Benutzers über sein Profil gelöscht wird
* ein Benutzer durch einen Prozessmanager/Administrator gelöscht wird


## Projekte (und deren weitere Angaben)

Folgende (privaten) Daten werden gespeichert:

Datum|Zweck
---|---
Kontaktname extern (bei Fraktionen oder Partnern, wenn angegeben)|zur Verwendung durch die Teammitglieder
Kontakt-Email extern (bei Fraktionen oder Partnern, wenn angegeben)|zur Verwendung durch die Teammitglieder
Kontakttelefon extern (bei Fraktionen oder Partnern, wenn angegeben)|zur Verwendung durch die Teammitglieder
ggf. weitere Personeninformationen wenn in den diversen Textfeldern angegeben|unaufgeforderte Angabe

Diese Informationen werden gelöscht (wenn auch das Projektobjekt
selbst ggf. erhalten bleibt) wenn:

* Ein Projekt durch einen Prozessmanager gelöscht wird

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
Projekt angelegt|Statistik Benutzung der Plattform, Erkennung von besonderen Häufungen, Sperrung von IP-Adressen bei zu vielen Aktionen (Spam-Schutz)
Projekt wegen Missbrauch gemeldet|Erkennung von besonderen Häufungen, Sperrung von IP-Adressen bei zu vielen Aktionen (Spam-Schutz)

Diese Log-Einträge werden einmal täglich anonymisiert:

* Entfernen aller IP-Adressen von Einträgen die älter als 24h sind (ergibt maximales Alter von IP-Adressen von 48h)
* Entfernen aller Benutzernamen von Einträgen die älter als 7 Tage sind