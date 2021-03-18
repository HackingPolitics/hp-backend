# Übersetzungen

Im Quelltext (Frontend & Backend) werden keine Texte in der Basissprache (bspw. Deutch
) verwendet, bspw. "Du wurdest eingeloggt.", die dann in den Übersetzungen in andere Sprachen
übertragen werden. Dies dient dazu den Quelltext kurz & übersichtlich zu halten
und zu vermeiden dass bei Anpassungen an der Basissprache auch jede Übersetzungsdatei
angepasst werden muss um die Zuordnung aufrecht zu erhalten. 

Ausserdem würde die Verwendung der Basissprache verhindern dass Texte die dort gleich
sind, bspw. "eintragen", in anderen Sprachen unterschiedlich übersetzt werden, bspw.
für einen Anwendungsfall mit "submit" und an anderer Stelle mit "enter".

Für Übersetzungen wird daher das Format "abc.def.ghi" verwendet, d.h. es sind keine
Sonderzeichen erlaubt und einzelne Abschnitte werden durch einen Punkt getrennt. 
Dies ermöglicht es, Einträge hierarchisch zu gruppieren um so direkt die Verwendung
zu erkennen:

```json
{
  "form": {
    "user": {
      "username": "Benutzername",
      "password": "Passwort"
    },
    "project": {
      "name": "Projektname"
    }
  },
  "page": {
    ...
  }
}
```
Somit können die Übersetzungs-Zeichenketten "form.user.password"
und "form.user.username" verwendet werden, dies macht klar dass es sich hier um 
Texte handelt die a) nur in Formularen auftauchen und b) etwas mit Benutzern zu tun haben.

## Allgemein

Übersetzungen dürfen nur HTML-Tags enthalten wenn sichergestellt ist, dass diese
auch als solche interpretiert werden, dies ist im React-Client normalerweise **nicht**
der Fall! Zudem ist an vielen Stellen gar kein HTML möglich, bspw. wenn eine Übersetzung (auch) als
title-Tag eine Links o.ä. verwendet wird. Ggf. vorher also Rücksprache mit dem zuständigen Entwickler halten.

Bei Verwendung von HTML in den Übersetzungen dürfen nur HTML-Tags verwendet werden die
an dieser Stelle auch zulässig sind. Wenn also eine Übersetzung innerhalb eines `<p>`-Tags
ausgegeben wird darf sie selbst keine `<p>`-Tags enthalten da dies ungültiges HTML erzeugen würde. 
Dies ist also vor Verwendung der Tags entsprechend zu prüfen.

Generell ist mit HTML sparsam umzugehen welches das Layout beeinflusst
da eine Übersetzung in verschiedenen Szenarien zum Einsatz kommen kann
(Breitbild vs. Mobilgerät, Browser vs. Email) und dort jeweils andere
Platzverhältnisse und Funktionalitäten existieren.

### Individualisierung für Instanzen

Für jede Instanz / jeden Kunden gibt es jeweils ein eigenes
Frontend- sowie Backend-Repository, individuelle Texte müssen dort
hinterlegt werden.

Es dürfen nur tatsächlich individuelle Elemente enthalten sein da sonst
die Update-Fähigkeit verloren geht: Änderungen an den Übersetzungen des
Zentral-Repositories werden sonst nicht auf der Instanz wirksam,
es ist leichter nur die wenigen individuellen Einträge abzugleichen und
ggf. zu erneuern.

## Client

Das o.g. Format verhindert dass Texte doppelt übersetzt werden können,
bspw. um Fallbacks einzurichten, also bspw. für das Registrierungsformular
den allgemeinen Text zu verwenden und für das Login-Formular
einen speziellen, die müsste also schon im Quelltext geschehen.

```json
{
  "form": {
    "submit": "Absenden",
    "user": {
      "registration": {
        "submit": "form.submit"
      },
      "login": {
        "submit": "Einloggen"
      }
    }
  }
}
```
(Es würde also im Registrierungsformular direkt "form.submit" ausgegeben werden.)

Es verhindert ebenso dass natürliche Sprache übersetzt werden kann,
bspw. "Du hast gewonnen. Bitte bestätige deinen Gewinn." enthält Punkte,
diese würden als Trenner interpretiert und daher keine passende Übersetzung gefunden.

Die Übersetzungsdateien befinden sich im Frontend-Repository in _/locales/de|en_
und sind dort noch einmal nach "Namespaces" getrennt. Diese Namespaces dienen
der thematischen Trennung. Bspw. "common" für alle Texte die für ausgeloggte Besucher
und eingeloggte Benutzer zu sehen sind bzw. auf Seiten erscheinen die für Admins und normale
Benutzer sichtbar sind und "management" für alle Texte die nur für Admins / Prozessmanager
relevant sind. 
Dies ermöglicht es u.A. den "management"-Namespace nur zu laden wenn auch eine
Verwaltungsseite aufgerufen wird und reduziert daher die zu ladende Dateigröße
für normale Besucher um mehrere Kilobyte. Zudem können dadurch Texte für Admins
anders übersetzt werden, eine Zeichenkette kann also in "common" und "management"
gleichermassen vorkommen aber mit anderem Inhalt:

```json
common.json:
{
  "goto": {
    "projectDetails": "Projektansicht",
  }
}
management.json:
{
  "goto": {
    "projectDetails": "Projekt verwalten",
  }
}
```
Dies erfordert allerdings schon im Quelltext bei Aufruf der Übersetzung den richtigen Namespace anzugeben.

HTML muss nicht escaped werden, dafür jedoch Anführungszeichen mittels Backslash:
```json
{
  "help": "Beschreibe einzelne Gruppen, z.B. <i>\"junge Männer zwischen 16 und 28 aus der Altstadt\"</i>"
}
```

Parameter werden mit doppelten geschweiften Klammern angegeben:
```json
{
  "editMembership": "Hier kannst du das Mitglied <em>{{username}}</em> bearbeiten."
}
```

### Individualisierung für Instanzen

Um einzelne Texte für den Kunden zu individualisieren existiert
im Backend-Repository der Kundeninstanz der Ordner _/locales_,
dort können die gleichen Dateien wie im Basis-Repsitory verwendet
werden und abweichende Texte enthalten.
Dabei müssen die Dateinamen identisch sein da sonst keine Zuordnung erfolgen kann.

## Backend

Die API gibt (nach Möglichkeit) nur übersetzbare Zeichenketten aus,
also bspw "validate.general.notBlank", sie muss also im Normalfall
nichts in die aktuelle Clientsprache übersetzen oder an die Instanz
angepasst werden.

Ausnahmen davon sind E-Mails die durch die API erzeugt und versandt werden.
Zudem müssen von Symfony/ApiPlatform erzeugte englische Fehlermeldungen in
die übersetzbaren Zeichenketten konvertiert werden.

Dafür befinden sich in _/translations_ im Backend-Repository die
Übersetzungsdateien im XLIFF-Format:

```xml
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="de" datatype="plaintext" original="file.ext">
        <body>
            <trans-unit id="This value should not be null.">
                <source>This value should not be null.</source>
                <target>validate.general.notBlank</target>
            </trans-unit>
        </body>
    </file>
</xliff>
```
Das _id_-Attribut und _source_ müssen identisch sein und entsprechen der Ausgabe
der Anwendung, _target_ enthält dann die eigentliche Übersetzung für die API oder für Emails. 

Hier werden in der _validators.de|en.xlf_ und security.de|en.xlf_ die automatischen
Fehlermeldungen für Javascript übersetzbar gemacht. 
_messages.de|en.xlf_ enthält die Übersetzungen für Email-Texte, Betreffs etc.

HTML muss mittels CDATA-Tag escaped werden, Parameter werden mit %-Zeichen umschlossen:
```xml
    <target><![CDATA[
    <ul>
        <li>
            Benutzername: %username%
        </li>
        <li>
            Email: %useremail%
        </li>
    </ul>
   ]]></target>
```

### Individualisierung für Instanzen

Um einzelne Texte für den Kunden zu individualisieren existiert
im Backend-Repository der Kundeninstanz der Ordner _/translations_custom_,
dort können die gleichen Dateien wie in _/translations_ verwendet
werden und abweichende Texte enthalten. 
Dabei müssen die Dateinamen identisch sein da sonst keine Zuordnung erfolgen kann.
