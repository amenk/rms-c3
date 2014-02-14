---------------------------
rms (Release Management System)
---------------------------

rms ist ein Freigabesystem, welches es bestimmten Benutzergruppen erlaubt Freigaben zu verwalten. Alle Redakteur-Zugänge die nicht als Super-Redakteurgruppe in dem Freigabeeinstellungen zugewiesen wurden, können zwar Änderungen machen diese werden aber nach dem speichern nicht Live angezeigt sondern stheen in der Freigabeliste.

---------------------------
Versionshinweis
---------------------------

Das Modul 'rms' wurde für Contao 3.x komplett umgeschrieben.Außerdem benötigt rms-c3 nun keinen externen DC-Treiber mehr sondern besitzt nun einen eigenen. Dieser 'DC_rmsTable.php' ist eine angepasste Variante von DC_Table.php. Die Datei liegt innerhalb von der Erweiterung rms/drivers und muss nicht extra platziert werden.

rms-c3 ist NICHT abwärtskompatibel. Beim Update von einer älteren Version, dürften (NICHT GETESTET) keine Datenverluste von evtl. bestehenden Einstellungen oder Datensätzen entstehen. Es wurden nur Felder hinzugekommen aber keine Spalten gelöscht oder umbenannt.
