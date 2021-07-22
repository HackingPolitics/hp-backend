# Designentscheidungen
* Wir verwenden Symfony als Basis weil es seit einiger Zeit das populärste und
  am aktivsten weiterentwicklte Framework mit einer extrem großen Menge an
  Addons ist. ZendFramework ist seit einigen Jahren praktisch eingeschlafen.
* Wir verwenden API Platform weil wir hier (bspw. gegenüber FOSRestBundle) fast
  alle Features aus einer Hand bekommen (JSON-REST, Integration mit Doctrine,
  automatische API-Doku, JWT-Auth via Addon, ...)
* Vorerst verwenden wir nur MySQL/MariaDB um uns den Overhead zu sparen eine
  zweite Datenbank zu managen und zu testen (hautelook/alicebundle kann nicht
  ORM + ODM fixtures gleichzeitig managen, ODM fixtures werden von den
  Unittest-Traits nicht unterstützt, DAMATestFixtureBundle funktioniert nur via
  Transactions im ORM). Wir erwarten nicht extrem viele Daten (Millionen von
  Zeilen), auch keine hohe Anzahl von Schreibzugriffen und nutzen lieber die
  hohe Select-Performance der SQL-DBs.
  Um nicht übermässig viele Joins verwenden zu müssen können dynamische Felder
  als JSON in einem Feld gespeichert werden, wenn diese nicht für die Suche
  indiziert werden müssen.
* Wir verwenden den Symfony Serializer statt JMS da ApiPlatform diesen nicht untersützt
  (@see https://github.com/api-platform/api-platform/issues/753)

## Validatoren
* Nach Möglichkeit sollte in der Datenbank alles nullable sein um Platz zu sparen
  und Felder ohne Schemaänderung via Code als Pflichtfeld zu markieren oder nicht
* Um bei der Weiterverarbeitung aber keine Typprüfung zu erfordern sollten die
  Getter den jeweiligen Typ zurückgeben (leeren String, leeres Array) statt NULL:
  ```$this->property ?? ''``` oder ```$this->property ?? []``` o.ä.
* Zu jedem Feld soll maximal eine Fehlermeldung erscheinen, daher werden Validatoren
  ggf. sequentiell verarbeitet:
  ```
  @Assert\Sequentially({
      @Assert\Type("string"),
      @Assert\Length(min=5, max=500),
  })
  ```
* Das automatische Hinzufügen von Validatoren basierend auf dem Doctrine-Typ ist
  deaktiviert da dies mit `@Assert\Sequentially` kollidiert. Daher müssen alle
  NotBlank / Length-Validatoren manuell zu jedem Feld hinzugefügt werden.
* Auch wenn Validatoren wie `Length` selbst den Typ prüfen sollte der `Type`-Validator
  bei JSON-Feldern mit String-Arrays trotzdem mit angegeben werden um explizit zu
  sein und ggf. das Vorkommen von Objekten die `__toString()` implementieren zu verhindern.

## DataPersisters

* Symfony autoregisters the DataPersisters in the src-directory
* ApiPlatform uses its _ChainDataPersister_ to call all other
  registered persisters
* the first persister that supports the given resource is called,
  the others are skipped (unless Resumable), so $em->flush is not called
  twice, see below
* _UserDataPersister_, _CouncilDataPersister_ and _ProjectDataPersister_ wrap
  the _DoctrinePersister_, not decorate it. This means the original
  _DoctrinePersister_ is not called for those resources, so we cannot decorate
  it to trigger pre/post persist events