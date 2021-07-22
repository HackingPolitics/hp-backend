# Übersetzungen

Im Quelltext werden keine Texte in der Basissprache (bspw. Deutch
) verwendet, z.B. "Du wurdest eingeloggt.", die dann in den Übersetzungen in andere Sprachen
übertragen werden. Dies dient dazu, den Quelltext kurz & übersichtlich zu halten
und zu vermeiden, dass bei Anpassungen an der Basissprache auch jede Übersetzungsdatei
angepasst werden muss um die Zuordnung aufrecht zu erhalten. 

Ausserdem würde die Verwendung der Basissprache verhindern, dass Texte die dort gleich
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
auch als solche interpretiert werden, dies ist im Client normalerweise **nicht**
der Fall! Zudem ist an vielen Stellen gar kein HTML möglich, bspw. wenn eine
Übersetzung (auch) als `title`-Tag eine Links o.ä. verwendet wird. Ggf. vorher
also Rücksprache mit dem zuständigen Entwickler halten.

Bei Verwendung von HTML in den Übersetzungen dürfen nur HTML-Tags verwendet werden die
an dieser Stelle auch zulässig sind. Wenn also eine Übersetzung innerhalb eines `<p>`-Tags
ausgegeben wird darf sie selbst keine `<p>`-Tags enthalten da dies ungültiges HTML erzeugen würde. 
Dies ist also vor Verwendung der Tags entsprechend zu prüfen.

Generell ist mit HTML, welches das Layout beeinflusst, sparsam umzugehen
da eine Übersetzung in verschiedenen Szenarien zum Einsatz kommen kann
(Breitbild vs. Mobilgerät, Browser vs. Email) und dort jeweils andere
Platzverhältnisse und Funktionalitäten existieren.

### Individualisierung für Instanzen

Für jede Instanz gibt es jeweils ein eigenes Frontend- sowie Backend-Repository,
individuelle Texte müssen dort hinterlegt werden.

Es dürfen nur tatsächlich individuelle Elemente enthalten sein da sonst
die Update-Fähigkeit verloren geht: Änderungen an den Übersetzungen des
Zentral-Repositories werden sonst nicht auf der Instanz wirksam,
es ist leichter nur die wenigen individuellen Einträge abzugleichen und
ggf. zu erneuern.

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

HTML muss mittels CDATA-Tag escaped werden, Parameter werden mit geschweiften 
Klammern umschlossen:
```xml
    <target><![CDATA[
    <ul>
        <li>
            Benutzername: {username}
        </li>
        <li>
            Email: {useremail}
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
